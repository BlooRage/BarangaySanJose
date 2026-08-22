<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once "../General/connection.php";
require_once "../General/security.php";

requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin']);

$sql = "
    SELECT
        r.resident_id,
        r.firstname,
        r.middlename,
        r.lastname,
        r.suffix,
        u.archived_at
    FROM residentinformationtbl r
    LEFT JOIN useraccountstbl u ON r.user_id = u.user_id
    LEFT JOIN statuslookuptbl s ON r.status_id_resident = s.status_id
    WHERE s.status_name = 'Archived'
      AND s.status_type = 'Resident'
    ORDER BY u.archived_at DESC, r.resident_id DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => $conn->error]);
    exit;
}

$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $row = pii_decrypt_resident_row($row) ?? $row;
    $middleInitial = trim((string)($row['middlename'] ?? ''));
    $row['full_name'] = trim(implode(' ', array_filter([
        trim((string)($row['firstname'] ?? '')),
        $middleInitial !== '' ? substr($middleInitial, 0, 1) . '.' : '',
        trim((string)($row['lastname'] ?? '')),
        trim((string)($row['suffix'] ?? '')),
    ], static fn($part): bool => $part !== '')));
    unset($row['firstname'], $row['middlename'], $row['lastname'], $row['suffix']);
    $row['archived_at'] = $row['archived_at']
        ? date('Y-m-d', strtotime($row['archived_at']))
        : null;
    $data[] = $row;
}

echo json_encode($data);
