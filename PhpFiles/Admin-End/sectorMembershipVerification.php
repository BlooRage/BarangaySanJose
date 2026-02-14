<?php
session_start();
require_once "../General/connection.php";
require_once "../General/security.php";

requireRoleSession(['Admin', 'Employee']);

function normalizeResidentId($value): ?string {
    $id = trim((string)$value);
    if ($id === '' || !preg_match('/^\\d{10}$/', $id)) {
        return null;
    }
    return $id;
}

function extractMarkerFromRemarks($remarks): string {
    $text = trim((string)$remarks);
    if ($text === '') return '';
    $parts = explode(';', $text);
    return trim((string)($parts[0] ?? ''));
}

function extractSectorKeyFromMarker(string $marker): string {
    $m = trim((string)$marker);
    if ($m === '') return '';
    if (stripos($m, 'sector:') !== 0) return '';
    $raw = trim(substr($m, strlen('sector:')));
    if ($raw === '') return '';
    $parts = explode(':', $raw, 2);
    return trim((string)($parts[0] ?? ''));
}

function extractSectorSideFromMarker(string $marker): string {
    $m = trim((string)$marker);
    if ($m === '') return '';
    if (stripos($m, 'sector:') !== 0) return '';
    $raw = trim(substr($m, strlen('sector:')));
    $parts = explode(':', $raw, 3);
    $side = strtolower(trim((string)($parts[1] ?? '')));
    if ($side === 'front' || $side === 'back') return $side;
    return '';
}

function toPublicPath($path): ?string {
    $path = trim((string)$path);
    if ($path === '') {
        return null;
    }

    $normalized = str_replace("\\", "/", $path);
    $normalized = preg_replace('#/+#', '/', $normalized);

    $parts = explode('/', $normalized);
    $cleanParts = [];
    foreach ($parts as $part) {
        if ($part === '' || $part === '.') continue;
        if ($part === '..') { array_pop($cleanParts); continue; }
        $cleanParts[] = $part;
    }
    $normalized = '/' . implode('/', $cleanParts);

    $marker = '/UnifiedFileAttachment/';
    $markerPos = stripos($normalized, $marker);
    if ($markerPos !== false) {
        $public = substr($normalized, $markerPos);
        return '..' . $public;
    }

    $webRoot = realpath(__DIR__ . "/../..");
    if ($webRoot) {
        $rootNorm = str_replace("\\", "/", $webRoot);
        if (strpos($normalized, $rootNorm) === 0) {
            $rel = substr($normalized, strlen($rootNorm));
            if ($rel === '') return null;
            if ($rel[0] !== '/') $rel = '/' . $rel;
            return '../' . ltrim($rel, '/');
        }
    }

    return '../' . ltrim($normalized, '/');
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['fetch_sector_applications'])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Not found']);
    exit;
}

try {
    // Pull all sector-marked uploads, newest first; group by (resident, base sector key).
    // If the proof is an ID, we expect two attachments tagged as:
    // - sector:<KEY>:front
    // - sector:<KEY>:back
    $stmt = $conn->prepare("
        SELECT
            uf.attachment_id,
            uf.source_id AS resident_id,
            uf.file_name,
            uf.file_path,
            uf.upload_timestamp,
            uf.remarks,
            uf.id_number,
            dt.document_type_name,
            dt.document_category,
            s.status_name AS verify_status,
            r.firstname,
            r.middlename,
            r.lastname,
            r.suffix,
            r.sex,
            r.birthdate,
            r.sector_membership,
            a.unit_number,
            a.street_number AS house_number,
            a.street_name,
            a.phase_number,
            a.subdivision,
            a.area_number
        FROM unifiedfileattachmenttbl uf
        LEFT JOIN documenttypelookuptbl dt
            ON uf.document_type_id = dt.document_type_id
        LEFT JOIN statuslookuptbl s
            ON uf.status_id_verify = s.status_id
        INNER JOIN residentinformationtbl r
            ON r.resident_id = uf.source_id
        LEFT JOIN residentaddresstbl a
            ON a.address_id = (
                SELECT a2.address_id
                FROM residentaddresstbl a2
                WHERE a2.resident_id = r.resident_id
                ORDER BY a2.address_id DESC
                LIMIT 1
            )
        WHERE uf.source_type = 'ResidentProfiling'
          AND uf.remarks LIKE 'sector:%'
        ORDER BY uf.upload_timestamp DESC, uf.attachment_id DESC
    ");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $groups = [];
    while ($row = $res->fetch_assoc()) {
        $residentId = normalizeResidentId($row['resident_id'] ?? '');
        if (!$residentId) {
            continue;
        }

        $marker = extractMarkerFromRemarks($row['remarks'] ?? '');
        $markerLower = strtolower($marker);
        if ($markerLower === '' || strpos($markerLower, 'sector:') !== 0) {
            // If remarks was changed in the future, we only trust marker="sector:...".
            continue;
        }

        $sectorKey = extractSectorKeyFromMarker($marker);
        if ($sectorKey === '') continue;
        $groupKey = $residentId . '|' . strtolower($sectorKey);

        $side = extractSectorSideFromMarker($markerLower);
        $slot = $side !== '' ? $side : 'single';

        $fullName = trim(
            (string)($row['firstname'] ?? '') . ' ' .
            ((string)($row['middlename'] ?? '') !== '' ? ((string)$row['middlename'])[0] . '. ' : '') .
            (string)($row['lastname'] ?? '') .
            ((string)($row['suffix'] ?? '') !== '' ? ' ' . (string)$row['suffix'] : '')
        );

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'resident_id' => $residentId,
                'full_name' => $fullName,
                'sex' => (string)($row['sex'] ?? ''),
                'birthdate' => (string)($row['birthdate'] ?? ''),
                'sector_membership' => (string)($row['sector_membership'] ?? ''),
                'unit_number' => (string)($row['unit_number'] ?? ''),
                'house_number' => (string)($row['house_number'] ?? ''),
                'street_name' => (string)($row['street_name'] ?? ''),
                'phase_number' => (string)($row['phase_number'] ?? ''),
                'subdivision' => (string)($row['subdivision'] ?? ''),
                'area_number' => (string)($row['area_number'] ?? ''),
                'sector_key' => $sectorKey,
                // Use a stable base marker for UI label extraction.
                'marker' => 'sector:' . $sectorKey,
                'documents' => []
            ];
        }

        if (isset($groups[$groupKey]['documents'][$slot])) {
            continue; // already have the latest doc for this slot
        }

        $groups[$groupKey]['documents'][$slot] = [
            'slot' => $slot,
            'attachment_id' => (int)($row['attachment_id'] ?? 0),
            'remarks' => (string)($row['remarks'] ?? ''),
            'marker' => $marker,
            'verify_status' => (string)($row['verify_status'] ?? 'PendingReview'),
            'file_name' => (string)($row['file_name'] ?? ''),
            'document_type_name' => (string)($row['document_type_name'] ?? ''),
            'document_category' => (string)($row['document_category'] ?? ''),
            'file_url' => toPublicPath($row['file_path'] ?? ''),
            'upload_timestamp' => (string)($row['upload_timestamp'] ?? ''),
        ];
    }
    $stmt->close();

    $rows = [];
    foreach ($groups as $g) {
        $docs = $g['documents'];

        // Normalize order for UI: front, back, single.
        $docList = [];
        foreach (['front', 'back', 'single'] as $slot) {
            if (isset($docs[$slot])) $docList[] = $docs[$slot];
        }

        // Compute a single application status from its latest attachments.
        $status = 'PendingReview';
        $hasAny = false;
        $anyRejected = false;
        $anyPending = false;
        $allVerified = true;
        foreach ($docList as $d) {
            $hasAny = true;
            $s = strtolower((string)($d['verify_status'] ?? ''));
            if ($s === 'rejected') $anyRejected = true;
            if ($s === 'pendingreview') $anyPending = true;
            if ($s !== 'verified') $allVerified = false;
        }
        if (!$hasAny) {
            $status = 'PendingReview';
        } elseif ($anyRejected) {
            $status = 'Rejected';
        } elseif ($anyPending) {
            $status = 'PendingReview';
        } elseif ($allVerified) {
            $status = 'Verified';
        } else {
            $status = 'PendingReview';
        }

        $attachmentIds = array_values(array_filter(array_map(static function ($d) {
            return (int)($d['attachment_id'] ?? 0);
        }, $docList)));

        $rows[] = [
            'resident_id' => $g['resident_id'],
            'full_name' => $g['full_name'],
            'sex' => $g['sex'],
            'birthdate' => $g['birthdate'],
            'sector_membership' => $g['sector_membership'],
            'unit_number' => $g['unit_number'],
            'house_number' => $g['house_number'],
            'street_name' => $g['street_name'],
            'phase_number' => $g['phase_number'],
            'subdivision' => $g['subdivision'],
            'area_number' => $g['area_number'],
            'sector_key' => $g['sector_key'],
            'marker' => $g['marker'],
            'verify_status' => $status,
            'attachment_ids' => $attachmentIds,
            'documents' => $docList,
            // Backward-compat for older UI code paths.
            'attachment_id' => (int)($attachmentIds[0] ?? 0)
        ];
    }

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
