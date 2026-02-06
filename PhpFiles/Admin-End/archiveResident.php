<?php
header('Content-Type: application/json; charset=utf-8');

require_once "../General/connection.php";

$sql = "
    SELECT
        r.resident_id,
        CONCAT(
            r.firstname, ' ',
            IFNULL(CONCAT(LEFT(r.middlename, 1), '. '), ''),
            r.lastname,
            IF(r.suffix IS NOT NULL AND r.suffix != '', CONCAT(' ', r.suffix), '')
        ) AS full_name,
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
    $row['archived_at'] = $row['archived_at']
        ? date('Y-m-d', strtotime($row['archived_at']))
        : null;
    $data[] = $row;
}

echo json_encode($data);
