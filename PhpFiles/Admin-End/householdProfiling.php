<?php
session_start();
require_once "../General/connection.php";

/* =====================================================
   FETCH HEADS OF FAMILY (AJAX)
===================================================== */
if (isset($_GET['fetch'])) {
    header('Content-Type: application/json; charset=utf-8');

    $search = trim($_GET['search'] ?? '');

    $sql = "
        SELECT
            r.resident_id,
            r.firstname,
            r.middlename,
            r.lastname,
            r.suffix,
            r.head_of_family,
            r.sex,
            r.civil_status,
            r.voter_status,
            r.occupation,
            r.occupation_detail,

            CASE
              WHEN r.occupation = 1
                   AND r.occupation_detail IS NOT NULL
                   AND TRIM(r.occupation_detail) <> ''
                THEN r.occupation_detail
              ELSE 'Unemployed'
            END AS occupation_display,

            a.street_number AS house_number,
            a.street_name,
            a.subdivision,
            a.area_number

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
    ";

    if ($search !== '') {
        $sql .= "
          AND (
            r.resident_id LIKE ? OR
            r.firstname LIKE ? OR
            r.lastname LIKE ? OR
            CONCAT(r.firstname, ' ', r.lastname) LIKE ?
          )
        ";
    }

    $sql .= " ORDER BY r.resident_id DESC";

    $stmt = $conn->prepare($sql);

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

        $addressParts = [];

        $streetLine = '';
        if (!empty($row['house_number'])) $streetLine .= $row['house_number'];
        if (!empty($row['street_name'])) {
            $streetLine .= ($streetLine !== '' ? ' ' : '') . $row['street_name'] . ' Street';
        }
        if ($streetLine !== '') $addressParts[] = $streetLine;
        if (!empty($row['subdivision'])) $addressParts[] = $row['subdivision'];
        if (!empty($row['area_number'])) $addressParts[] = $row['area_number'];

        $row['full_name'] = trim($fullName);
        $row['address_display'] = $addressParts ? implode(", ", $addressParts) : "—";

        $data[] = $row;
    }

    echo json_encode($data);
    exit;
}

http_response_code(404);
exit("Not found");
