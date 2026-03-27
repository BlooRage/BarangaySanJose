<?php
session_start();
require_once "../General/connection.php";
require_once "../General/security.php";
require_once "../General/uniqueIDGenerate.php";

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

function hof_clean_text($value): string {
    return trim((string)$value);
}

function hof_normalize_subdivision_label($value): string {
    $value = hof_clean_text($value);
    if ($value === '') {
        return '';
    }
    $value = preg_replace('/\bsubdivision\b/i', '', $value);
    $value = preg_replace('/\bsubd\.?\b/i', '', $value);
    $value = trim(preg_replace('/\s+/', ' ', (string)$value));
    return $value === '' ? '' : $value . ' Subdivision';
}

function hof_normalize_phase_label($value): string {
    $value = hof_clean_text($value);
    if ($value === '') {
        return '';
    }
    $value = preg_replace('/^\s*(phase|ph)\.?\s*/i', '', $value);
    $value = trim(preg_replace('/\s+/', ' ', (string)$value));
    return $value === '' ? '' : 'Phase ' . $value;
}

function hof_normalize_street_label($value): string {
    $value = hof_clean_text($value);
    if ($value === '') {
        return '';
    }
    if (!preg_match('/\bst(?:reet)?\.?$/i', $value)) {
        $value .= ' Street';
    }
    return trim(preg_replace('/\s+/', ' ', $value));
}

function hof_normalize_lot_label($value): string {
    $value = hof_clean_text($value);
    if ($value === '') {
        return '';
    }
    $value = preg_replace('/^\s*lot\s*/i', '', $value);
    $value = trim(preg_replace('/\s+/', ' ', (string)$value));
    return $value === '' ? '' : 'Lot ' . $value;
}

function hof_normalize_block_label($value): string {
    $value = hof_clean_text($value);
    if ($value === '') {
        return '';
    }
    $value = preg_replace('/^\s*block\s*/i', '', $value);
    $value = preg_replace('/^\s*blk\.?\s*/i', '', $value);
    $value = trim(preg_replace('/\s+/', ' ', (string)$value));
    return $value === '' ? '' : 'Block ' . $value;
}

function hof_is_lot_block_address(array $row): bool {
    $houseNumber = hof_clean_text($row['house_number'] ?? '');
    $phaseNumber = hof_clean_text($row['phase_number'] ?? '');
    $streetName = hof_clean_text($row['street_name'] ?? '');

    if ($houseNumber !== '' && preg_match('/^\s*lot\b/i', $houseNumber)) {
        return true;
    }
    if ($phaseNumber !== '' && preg_match('/^\s*(block|blk\.?)\b/i', $phaseNumber)) {
        return true;
    }
    if ($streetName !== '' && preg_match('/^\s*(block|blk\.?)\b/i', $streetName)) {
        return true;
    }

    return $houseNumber !== '' && $phaseNumber !== '' && $streetName === '';
}

function hof_build_address_display(array $row): string {
    $houseNumber = hof_clean_text($row['house_number'] ?? '');
    $streetName = hof_clean_text($row['street_name'] ?? '');
    $phaseNumber = hof_clean_text($row['phase_number'] ?? '');
    $subdivision = hof_normalize_subdivision_label($row['subdivision'] ?? '');

    if (hof_is_lot_block_address($row)) {
        $leadParts = array_values(array_filter([
            hof_normalize_lot_label($houseNumber),
            hof_normalize_block_label($phaseNumber),
        ], static fn($value) => $value !== ''));

        $tailParts = [];
        if ($streetName !== '') {
            if (preg_match('/^\s*(phase|ph)\b/i', $streetName) || preg_match('/^\s*\d+[a-z]?\s*$/i', $streetName)) {
                $tailParts[] = hof_normalize_phase_label($streetName);
            } else {
                $tailParts[] = $streetName;
            }
        }
        if ($subdivision !== '') {
            $tailParts[] = $subdivision;
        }

        $addressParts = [];
        if ($leadParts !== []) {
            $addressParts[] = implode(' ', $leadParts);
        }
        if ($tailParts !== []) {
            $addressParts[] = implode(', ', $tailParts);
        }

        return $addressParts ? implode(', ', $addressParts) : '-';
    }

    $streetLine = trim(implode(' ', array_filter([
        $houseNumber,
        hof_normalize_street_label($streetName),
    ], static fn($value) => $value !== '')));

    $addressParts = array_values(array_filter([
        $streetLine,
        $phaseNumber !== '' ? hof_normalize_phase_label($phaseNumber) : '',
        $subdivision,
    ], static fn($value) => $value !== ''));

    return $addressParts ? implode(', ', $addressParts) : '-';
}

function ensure_head_verification_table(mysqli $conn): void {
    $sql = "
        CREATE TABLE IF NOT EXISTS householdheadverificationtbl (
            verification_id INT NOT NULL PRIMARY KEY,
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
    idg_ensure_numeric_generated_key($conn, 'householdheadverificationtbl', 'verification_id', 'INT NOT NULL');
}

function fetch_head_rows(mysqli $conn): array {
    $sql = "
        SELECT
            r.resident_id,
            r.firstname,
            r.middlename,
            r.lastname,
            r.suffix,
            r.sex,
            r.birthdate,
            r.birthplace,
            r.baranagayresidency,
            r.civil_status,
            r.family_role,
            r.voter_status,
            r.occupation,
            r.occupation_detail,
            r.religion,
            r.sector_membership,
            a.street_number AS house_number,
            a.unit_number,
            a.street_name,
            a.phase_number,
            a.subdivision,
            a.area_number,
            a.house_type,
            a.house_ownership,
            a.residency_duration,
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
        $row = pii_decrypt_resident_row($row) ?? $row;
        $row = pii_decrypt_resident_address_row($row) ?? $row;
        $row = pii_decrypt_assoc($row, [
            'firstname',
            'middlename',
            'lastname',
            'suffix',
            'birthdate',
            'birthplace',
            'baranagayresidency',
            'civil_status',
            'family_role',
            'occupation_detail',
            'religion',
            'sector_membership',
            'house_number',
            'street_name',
            'phase_number',
            'subdivision',
            'house_type',
            'house_ownership',
            'residency_duration',
        ]) ?? $row;

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
        $row = pii_decrypt_assoc($row, ['address_display']) ?? $row;
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
    $verificationId = GenerateHouseholdHeadVerificationID($conn);
    if ($verificationId === false) {
        throw new Exception('Failed to generate household verification ID.');
    }
    $verificationIdInt = (int)$verificationId;
    $sql = "
        INSERT INTO householdheadverificationtbl
            (verification_id, group_key, address_id, address_display, area_number, selected_resident_id, decision_status, remarks, decided_by_user_id, decided_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
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
    $stmt->bind_param("issssssss", $verificationIdInt, $groupKey, $addressId, $addressDisplay, $areaNumber, $selectedResidentId, $decisionStatus, $remarks, $decidedByUserId);
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
            $addressDisplay = hof_build_address_display($row);
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
        $addressDisplay = hof_build_address_display($row);

        if (!isset($groups[$key])) {
            $decision = $decisions[$key] ?? null;
            $groups[$key] = [
                'group_key' => $key,
                'address_id' => $row['address_id'] ?? ($decision['address_id'] ?? ''),
                'address_display' => $addressDisplay !== '-' ? $addressDisplay : ($decision['address_display'] ?? '-'),
                'area_number' => $row['area_number'] ?? ($decision['area_number'] ?? ''),
                'address_details' => [
                    'unit_number' => $row['unit_number'] ?? '',
                    'house_number' => $row['house_number'] ?? '',
                    'street_name' => $row['street_name'] ?? '',
                    'phase_number' => $row['phase_number'] ?? '',
                    'subdivision' => $row['subdivision'] ?? '',
                    'area_number' => $row['area_number'] ?? '',
                    'house_type' => $row['house_type'] ?? '',
                    'house_ownership' => $row['house_ownership'] ?? '',
                    'residency_duration' => $row['residency_duration'] ?? ''
                ],
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
            'member_count' => 1,
            'sex' => $row['sex'] ?? '',
            'birthdate' => $row['birthdate'] ?? '',
            'birthplace' => $row['birthplace'] ?? '',
            'barangay_residency' => $row['baranagayresidency'] ?? '',
            'civil_status' => $row['civil_status'] ?? '',
            'family_role' => $row['family_role'] ?? '',
            'voter_status' => $row['voter_status'] ?? '',
            'occupation' => $row['occupation'] ?? '',
            'occupation_detail' => $row['occupation_detail'] ?? '',
            'religion' => $row['religion'] ?? '',
            'sector_membership' => $row['sector_membership'] ?? ''
        ];
    }

    foreach ($decisions as $key => $decision) {
        if (isset($groups[$key])) continue;
        $groups[$key] = [
            'group_key' => $key,
            'address_id' => $decision['address_id'] ?? '',
            'address_display' => $decision['address_display'] ?? '-',
            'area_number' => $decision['area_number'] ?? '',
            'address_details' => [
                'unit_number' => '',
                'house_number' => '',
                'street_name' => '',
                'phase_number' => '',
                'subdivision' => '',
                'area_number' => $decision['area_number'] ?? '',
                'house_type' => '',
                'house_ownership' => '',
                'residency_duration' => ''
            ],
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
