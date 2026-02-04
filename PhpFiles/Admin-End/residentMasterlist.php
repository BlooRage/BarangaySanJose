<?php
session_start();
require_once "../General/connection.php";

/* ================================
   HANDLE STATUS UPDATE (FORM POST)
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['button-saveStatus'])) {

    $residentId = isset($_POST['input-appId']) ? (int)$_POST['input-appId'] : 0;
    $uiStatus   = $_POST['select-newStatus'] ?? '';

    $statusMap = [
        'PENDING'  => 'PendingVerification',
        'APPROVED' => 'VerifiedResident',
        'DENIED'   => 'NotVerified'
    ];

    if ($residentId <= 0 || !isset($statusMap[$uiStatus])) {
        http_response_code(400);
        exit('Invalid request');
    }

    $dbStatusName = $statusMap[$uiStatus];

    $stmt = $conn->prepare("
        UPDATE residentinformationtbl
        SET status_id_resident = (
            SELECT status_id
            FROM statuslookuptbl
            WHERE status_name = ?
              AND status_type = 'Resident'
            LIMIT 1
        )
        WHERE resident_id = ?
    ");
    if (!$stmt) {
        http_response_code(500);
        exit('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param("si", $dbStatusName, $residentId);
    $stmt->execute();
    $stmt->close();

    header("Location: ../../Admin-End/residentMasterlist.php");
    exit;
}


/* ================================
   FETCH RESIDENT DATA FOR JS
================================ */
if (isset($_GET['fetch'])) {

    header('Content-Type: application/json; charset=utf-8');

    $search = trim($_GET['search'] ?? '');

    $sql = "
        SELECT
            r.resident_id,
            r.user_id,
            r.firstname,
            r.middlename,
            r.lastname,
            r.suffix,
            r.sex,
            r.birthdate,
            r.civil_status,
            r.head_of_family,
            r.voter_status,

            /* stored in DB: occupation is 0/1 (tinyint) */
            r.occupation,
            r.occupation_detail,

            /* ✅ WHAT TO DISPLAY IN MODAL */
            CASE
              WHEN r.occupation = 1
                   AND r.occupation_detail IS NOT NULL
                   AND TRIM(r.occupation_detail) <> ''
                THEN r.occupation_detail
              ELSE 'Unemployed'
            END AS occupation_display,

            r.religion,
            r.sector_membership,
            s.status_name AS status,

            a.street_number AS house_number,
            a.street_name,
            a.subdivision,
            a.area_number,
            a.house_ownership,
            a.house_type,
            a.residency_duration,

            CONCAT(
                e.first_name, ' ',
                IFNULL(CONCAT(LEFT(e.middle_name, 1), '. '), ''),
                e.last_name,
                IF(e.suffix IS NOT NULL AND e.suffix != '', CONCAT(' ', e.suffix), '')
            ) AS emergency_full_name,
            e.phone_number AS emergency_contact_number,
            e.relationship AS emergency_relationship,
            e.address AS emergency_address

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
        LEFT JOIN emergencycontacttbl e ON e.user_id = r.user_id
    ";

    if ($search !== '') {
        $sql .= " WHERE
            r.resident_id LIKE ? OR
            r.firstname LIKE ? OR
            r.lastname LIKE ? OR
            CONCAT(r.firstname, ' ', r.lastname) LIKE ?
        ";
    }

    $sql .= " ORDER BY r.resident_id DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
        exit;
    }

    if ($search !== '') {
        $like = "%$search%";
        $stmt->bind_param("ssss", $like, $like, $like, $like);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $fullName =
            $row['firstname'] . ' ' .
            ($row['middlename'] ? $row['middlename'][0] . '. ' : '') .
            $row['lastname'] .
            ($row['suffix'] ? ' ' . $row['suffix'] : '');

        $row['full_name'] = trim($fullName);
        $data[] = $row;
    }

    echo json_encode($data);
    exit;
}

/* ================================
   Fallback if accessed directly
================================ */
http_response_code(404);
exit('Not found');
