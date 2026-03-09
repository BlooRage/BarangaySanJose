<?php
session_start();
require_once "../General/connection.php";
require_once "../General/security.php";

requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin', 'Employee']);

function normalize_simple($value) {
    $value = strtolower(trim((string)$value));
    return preg_replace('/[^a-z0-9]/', '', $value);
}

function normalize_phase($value) {
    $value = normalize_simple($value);
    $value = preg_replace('/^(phase|ph)/', '', $value);
    return $value;
}

function normalize_subdivision($value) {
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/\bsubdivision\b/i', '', $value);
    $value = preg_replace('/\bsubd\.?\b/i', '', $value);
    return normalize_simple($value);
}

function normalize_street($value) {
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/\bstreet\b/i', '', $value);
    $value = preg_replace('/\bst\.?\b/i', '', $value);
    return normalize_simple($value);
}

function ensure_head_verification_table(mysqli $conn): void {
    $sql = "
        CREATE TABLE IF NOT EXISTS householdheadverificationtbl (
            verification_id INT AUTO_INCREMENT PRIMARY KEY,
            group_key VARCHAR(255) NOT NULL,
            address_id VARCHAR(100) DEFAULT NULL,
            address_display VARCHAR(255) DEFAULT NULL,
            area_number VARCHAR(64) DEFAULT NULL,
            selected_resident_id VARCHAR(100) DEFAULT NULL,
            decision_status ENUM('Pending', 'Approved', 'Declined') NOT NULL DEFAULT 'Pending',
            remarks TEXT DEFAULT NULL,
            decided_by_user_id VARCHAR(100) DEFAULT NULL,
            decided_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_group_key (group_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $conn->query($sql);
}

function fetch_head_rows(mysqli $conn): array {
    $sql = "
        SELECT
            r.resident_id,
            r.firstname,
            r.middlename,
            r.lastname,
            r.suffix,
            a.street_number AS house_number,
            a.street_name,
            a.phase_number,
            a.subdivision,
            a.area_number,
            a.address_id
        FROM residentinformationtbl r
        LEFT JOIN statuslookuptbl s ON r.status_id_resident = s.status_id
        LEFT JOIN residentaddresstbl a
            ON a.address_id = (
                SELECT a2.address_id
                FROM residentaddresstbl a2
                WHERE a2.resident_id = r.resident_id
                ORDER BY a2.address_id DESC
                LIMIT 1
            )
        WHERE r.head_of_family = 1
          AND (s.status_name <> 'Archived' OR s.status_name IS NULL)
        ORDER BY r.resident_id DESC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $row['group_key'] = implode('|', [
            normalize_simple($row['house_number'] ?? ''),
            normalize_street($row['street_name'] ?? ''),
            normalize_phase($row['phase_number'] ?? ''),
            normalize_subdivision($row['subdivision'] ?? ''),
            normalize_simple($row['area_number'] ?? '')
        ]);
        if (trim((string)$row['group_key'], '|') === '') {
            $row['group_key'] = 'unknown|' . (string)$row['resident_id'];
        }
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function fetch_decisions(mysqli $conn): array {
    ensure_head_verification_table($conn);
    $map = [];
    $res = $conn->query("
        SELECT
            group_key, address_id, address_display, area_number,
            selected_resident_id, decision_status, remarks, decided_by_user_id, decided_at, updated_at
        FROM householdheadverificationtbl
    ");
    if (!$res) return $map;
    while ($row = $res->fetch_assoc()) {
        $key = trim((string)($row['group_key'] ?? ''));
        if ($key === '') continue;
        $map[$key] = $row;
    }
    return $map;
}

function upsert_decision(
    mysqli $conn,
    string $groupKey,
    ?string $addressId,
    ?string $addressDisplay,
    ?string $areaNumber,
    ?string $selectedResidentId,
    string $decisionStatus,
    ?string $remarks,
    ?string $decidedByUserId
): void {
    ensure_head_verification_table($conn);
    $sql = "
        INSERT INTO householdheadverificationtbl
            (group_key, address_id, address_display, area_number, selected_resident_id, decision_status, remarks, decided_by_user_id, decided_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            address_id = VALUES(address_id),
            address_display = VALUES(address_display),
            area_number = VALUES(area_number),
            selected_resident_id = VALUES(selected_resident_id),
            decision_status = VALUES(decision_status),
            remarks = VALUES(remarks),
            decided_by_user_id = VALUES(decided_by_user_id),
            decided_at = NOW()
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Failed to save decision.');
    $stmt->bind_param("ssssssss", $groupKey, $addressId, $addressDisplay, $areaNumber, $selectedResidentId, $decisionStatus, $remarks, $decidedByUserId);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Failed to save decision.');
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) $payload = $_POST;

    $action = strtolower(trim((string)($payload['action'] ?? '')));
    if (!in_array($action, ['approve_head_group', 'decline_head_group'], true)) {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Unsupported action.']);
        exit;
    }

    $groupKey = trim((string)($payload['group_key'] ?? ''));
    $approvedResidentId = trim((string)($payload['approved_resident_id'] ?? ''));
    $remarks = trim((string)($payload['remarks'] ?? ''));
    $decidedByUserId = trim((string)($_SESSION['user_id'] ?? ''));

    if ($groupKey === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Group key is required.']);
        exit;
    }

    $rows = fetch_head_rows($conn);
    $groupResidentIds = [];
    $addressId = null;
    $addressDisplay = null;
    $areaNumber = null;
    foreach ($rows as $row) {
        if ((string)$row['group_key'] !== $groupKey) continue;
        $rid = trim((string)($row['resident_id'] ?? ''));
        if ($rid !== '') $groupResidentIds[] = $rid;
        if ($addressId === null) $addressId = (string)($row['address_id'] ?? '');
        if ($areaNumber === null) $areaNumber = (string)($row['area_number'] ?? '');
        if ($addressDisplay === null) {
            $parts = [];
            if (!empty($row['house_number'])) $parts[] = $row['house_number'];
            if (!empty($row['street_name'])) $parts[] = $row['street_name'] . ' Street';
            if (!empty($row['phase_number'])) $parts[] = $row['phase_number'];
            if (!empty($row['subdivision'])) $parts[] = $row['subdivision'];
            if (!empty($row['area_number'])) $parts[] = $row['area_number'];
            $addressDisplay = $parts ? implode(', ', $parts) : null;
        }
    }
    $groupResidentIds = array_values(array_unique($groupResidentIds));

    if (empty($groupResidentIds) && $action !== 'decline_head_group') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No pending applicants found for this group.']);
        exit;
    }

    if ($action === 'approve_head_group') {
        if ($approvedResidentId === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Approved resident is required.']);
            exit;
        }
        if (!in_array($approvedResidentId, $groupResidentIds, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Selected resident is not part of this group.']);
            exit;
        }
    }

    $conn->begin_transaction();
    try {
        if (!empty($groupResidentIds)) {
            $upd = $conn->prepare("UPDATE residentinformationtbl SET head_of_family = ? WHERE resident_id = ?");
            if (!$upd) throw new Exception('Failed to prepare application update.');
            foreach ($groupResidentIds as $residentId) {
                $flag = ($action === 'approve_head_group' && $residentId === $approvedResidentId) ? 1 : 0;
                $upd->bind_param("is", $flag, $residentId);
                if (!$upd->execute()) throw new Exception('Failed to update applicant status.');
            }
            $upd->close();
        }

        if ($action === 'approve_head_group') {
            upsert_decision(
                $conn,
                $groupKey,
                $addressId,
                $addressDisplay,
                $areaNumber,
                $approvedResidentId,
                'Approved',
                $remarks !== '' ? $remarks : null,
                $decidedByUserId !== '' ? $decidedByUserId : null
            );
        } else {
            upsert_decision(
                $conn,
                $groupKey,
                $addressId,
                $addressDisplay,
                $areaNumber,
                null,
                'Declined',
                $remarks !== '' ? $remarks : null,
                $decidedByUserId !== '' ? $decidedByUserId : null
            );
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => $action === 'approve_head_group' ? 'Head application approved.' : 'Head application declined.']);
    } catch (Throwable $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if (isset($_GET['fetch'])) {
    header('Content-Type: application/json; charset=utf-8');
    $rows = fetch_head_rows($conn);
    $decisions = fetch_decisions($conn);

    $groups = [];
    foreach ($rows as $row) {
        $key = (string)$row['group_key'];
        $fullName = trim(
            ($row['firstname'] ?? '') . ' ' .
            (!empty($row['middlename']) ? $row['middlename'][0] . '. ' : '') .
            ($row['lastname'] ?? '') .
            (!empty($row['suffix']) ? ' ' . $row['suffix'] : '')
        );
        $addressParts = [];
        if ($row['house_number']) $addressParts[] = $row['house_number'];
        if ($row['street_name']) $addressParts[] = $row['street_name'] . ' Street';
        if ($row['phase_number']) $addressParts[] = $row['phase_number'];
        if ($row['subdivision']) $addressParts[] = $row['subdivision'];
        if ($row['area_number']) $addressParts[] = $row['area_number'];
        $addressDisplay = $addressParts ? implode(', ', $addressParts) : '-';

        if (!isset($groups[$key])) {
            $decision = $decisions[$key] ?? null;
            $groups[$key] = [
                'group_key' => $key,
                'address_id' => $row['address_id'] ?? ($decision['address_id'] ?? ''),
                'address_display' => $addressDisplay !== '-' ? $addressDisplay : ($decision['address_display'] ?? '-'),
                'area_number' => $row['area_number'] ?? ($decision['area_number'] ?? ''),
                'verification_status' => $decision['decision_status'] ?? 'Pending',
                'selected_resident_id' => $decision['selected_resident_id'] ?? '',
                'decided_by_user_id' => $decision['decided_by_user_id'] ?? '',
                'decided_at' => $decision['decided_at'] ?? '',
                'updated_at' => $decision['updated_at'] ?? '',
                'households' => []
            ];
        }

        $groups[$key]['households'][] = [
            'resident_id' => $row['resident_id'],
            'head_full_name' => $fullName,
            'member_count' => 1
        ];
    }

    foreach ($decisions as $key => $decision) {
        if (isset($groups[$key])) continue;
        $groups[$key] = [
            'group_key' => $key,
            'address_id' => $decision['address_id'] ?? '',
            'address_display' => $decision['address_display'] ?? '-',
            'area_number' => $decision['area_number'] ?? '',
            'verification_status' => $decision['decision_status'] ?? 'Pending',
            'selected_resident_id' => $decision['selected_resident_id'] ?? '',
            'decided_by_user_id' => $decision['decided_by_user_id'] ?? '',
            'decided_at' => $decision['decided_at'] ?? '',
            'updated_at' => $decision['updated_at'] ?? '',
            'households' => []
        ];
    }

    $data = array_values($groups);
    foreach ($data as &$group) {
        $group['household_count'] = count($group['households']);
    }
    unset($group);

    usort($data, static function ($a, $b) {
        return strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? ''));
    });

    echo json_encode($data);
    exit;
}

http_response_code(404);
echo json_encode(['success' => false, 'message' => 'Not found']);

