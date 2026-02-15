<?php
session_start();
require_once "../General/connection.php";
require_once "../General/security.php";

requireRoleSession(['Admin', 'Employee']);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$mode = trim((string)($_POST['mode'] ?? 'create_only')); // create_only | backfill
$dryRun = (int)($_POST['dry_run'] ?? 1) === 1;

try {
    $createSql = "
        CREATE TABLE IF NOT EXISTS residentsectormembershiptbl (
          id INT NOT NULL AUTO_INCREMENT,
          resident_id VARCHAR(10) NOT NULL,
          sector_key VARCHAR(64) NOT NULL,
          sector_status_id INT NOT NULL,
          latest_attachment_id INT NULL,
          remarks TEXT NULL,
          upload_timestamp DATETIME NULL,
          last_update_user_id VARCHAR(64) NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uq_resident_sector (resident_id, sector_key),
          KEY idx_resident (resident_id),
          KEY idx_sector (sector_key),
          KEY idx_sector_status (sector_status_id),
          KEY idx_latest_attachment (latest_attachment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    if (!$dryRun) {
        if (!$conn->query($createSql)) {
            throw new Exception("Create table failed: " . $conn->error);
        }
    }

    $backfillCount = 0;
    if ($mode === 'backfill') {
        // Determine latest status per (resident, sector marker) from unifiedfileattachmenttbl.
        $stmt = $conn->prepare("
            SELECT
                uf.source_id AS resident_id,
                uf.remarks,
                uf.attachment_id,
                uf.status_id_verify,
                uf.upload_timestamp,
                s.status_name AS verify_status
            FROM unifiedfileattachmenttbl uf
            LEFT JOIN statuslookuptbl s
                ON uf.status_id_verify = s.status_id
            WHERE uf.source_type = 'ResidentProfiling'
              AND uf.remarks LIKE 'sector:%'
            ORDER BY uf.upload_timestamp DESC, uf.attachment_id DESC
        ");
        if (!$stmt) {
            throw new Exception("Prepare failed (backfill scan): " . $conn->error);
        }
        $stmt->execute();
        $res = $stmt->get_result();

        $seen = [];
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $residentId = trim((string)($row['resident_id'] ?? ''));
            if (!preg_match('/^\\d{10}$/', $residentId)) continue;
            $remarks = trim((string)($row['remarks'] ?? ''));
            if ($remarks === '') continue;
	            $marker = trim((string)(explode(';', $remarks)[0] ?? ''));
	            $lower = strtolower($marker);
	            if (strpos($lower, 'sector:') !== 0) continue;
	            $sectorKeyRaw = trim(substr($marker, strlen('sector:')));
	            $sectorKey = trim((string)(explode(':', $sectorKeyRaw, 2)[0] ?? ''));
	            if ($sectorKey === '') continue;

            $k = $residentId . '|' . strtolower($sectorKey);
            if (isset($seen[$k])) continue;
            $seen[$k] = true;

            $statusLower = strtolower(trim((string)($row['verify_status'] ?? '')));
            $remarksReason = null;
            if (strpos($statusLower, 'rejected') !== false || strpos($statusLower, 'denied') !== false) {
                $parts = array_values(array_filter(array_map('trim', explode(';', (string)($row['remarks'] ?? '')))));
                foreach ($parts as $p) {
                    if (stripos($p, 'reason=') === 0) {
                        $remarksReason = trim(substr($p, strlen('reason=')));
                        break;
                    }
                }
            }

            $rows[] = [
                'resident_id' => $residentId,
                'sector_key' => $sectorKey,
                'sector_status_id' => (int)($row['status_id_verify'] ?? 0),
                'latest_attachment_id' => (int)($row['attachment_id'] ?? 0),
                'remarks' => $remarksReason,
                'upload_timestamp' => (string)($row['upload_timestamp'] ?? ''),
            ];
        }
        $stmt->close();

        if (!$dryRun && $rows) {
            $up = $conn->prepare("
                INSERT INTO residentsectormembershiptbl
                    (resident_id, sector_key, sector_status_id, latest_attachment_id, remarks, upload_timestamp, last_update_user_id)
                VALUES (?, ?, ?, ?, ?, ?, NULL)
                ON DUPLICATE KEY UPDATE
                    sector_status_id = VALUES(sector_status_id),
                    latest_attachment_id = VALUES(latest_attachment_id),
                    remarks = VALUES(remarks),
                    upload_timestamp = VALUES(upload_timestamp),
                    last_update_user_id = NULL,
                    updated_at = CURRENT_TIMESTAMP
            ");
            if (!$up) throw new Exception("Prepare failed (backfill upsert): " . $conn->error);
            foreach ($rows as $r) {
                $up->bind_param(
                    "ssiiss",
                    $r['resident_id'],
                    $r['sector_key'],
                    $r['sector_status_id'],
                    $r['latest_attachment_id'],
                    $r['remarks'],
                    $r['upload_timestamp']
                );
                if (!$up->execute()) {
                    throw new Exception("Backfill upsert failed: " . $up->error);
                }
                $backfillCount++;
            }
            $up->close();
        } else {
            $backfillCount = count($rows);
        }
    }

    echo json_encode([
        'success' => true,
        'dry_run' => $dryRun,
        'mode' => $mode,
        'backfill_rows' => $backfillCount
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
