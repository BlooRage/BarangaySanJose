<?php
session_start();
require_once "../General/connection.php";

if (isset($_GET['fetch'])) {
    header('Content-Type: application/json; charset=utf-8');

    $search = trim($_GET['search'] ?? '');

    /* ===============================
       FETCH HEADS OF FAMILY
    =============================== */
    $sql = "
        SELECT
            r.resident_id,
            r.user_id,
            r.firstname,
            r.middlename,
            r.lastname,
            r.suffix,
            r.birthdate,
            r.head_of_family,

            a.street_number AS house_number,
            a.street_name,
            a.phase_number,
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

        /* ===============================
           FORMAT NAME & ADDRESS
        =============================== */
        $fullName =
            $row['firstname'] . ' ' .
            ($row['middlename'] ? $row['middlename'][0] . '. ' : '') .
            $row['lastname'] .
            ($row['suffix'] ? ' ' . $row['suffix'] : '');

        $row['full_name'] = trim($fullName);
        $row['head_full_name'] = trim($fullName);

        $addressParts = [];
        if ($row['house_number']) $addressParts[] = $row['house_number'];
        if ($row['phase_number']) $addressParts[] = 'Phase ' . $row['phase_number'];
        if ($row['street_name']) {
            $street = $row['street_name'];
            $isBlock = preg_match('/^block\\s+/i', $street) === 1;
            $addressParts[] = $isBlock ? $street : ($street . ' Street');
        }
        if ($row['subdivision']) $addressParts[] = $row['subdivision'];
        if ($row['area_number']) $addressParts[] = $row['area_number'];

        $row['address_display'] = $addressParts ? implode(', ', $addressParts) : '—';

        /* ===============================
           HOUSEHOLD MEMBERS
        =============================== */
        $adults = [];
        $minors = [];

        if (!empty($row['house_number']) && !empty($row['street_name'])) {

            $normHouse = strtolower(preg_replace('/[\s\-\.]/', '', $row['house_number']));
            $normStreet = strtolower(
                preg_replace('/( street| st\.?|\.|\s+)/', '', $row['street_name'])
            );
            $normPhase = strtolower(preg_replace('/[\\s\\-\\.]/', '', (string)($row['phase_number'] ?? '')));

            $memberSql = "
                SELECT
                    r.resident_id,
                    r.firstname,
                    r.middlename,
                    r.lastname,
                    r.suffix,
                    r.birthdate
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
                WHERE (s.status_name <> 'Archived' OR s.status_name IS NULL)
                  AND LOWER(REPLACE(REPLACE(REPLACE(IFNULL(a.street_number,''),' ',''),'-',''),'.','')) = ?
                  AND LOWER(
                        REPLACE(
                          REPLACE(
                            REPLACE(
                              REPLACE(IFNULL(a.street_name,''),' street',''),
                            ' st.',''),
                          ' st',''),
                        '.','')
                      ) = ?
                  AND LOWER(REPLACE(REPLACE(REPLACE(IFNULL(a.phase_number,''),' ',''),'-',''),'.','')) = ?
            ";

            $memberStmt = $conn->prepare($memberSql);
            $memberStmt->bind_param("sss", $normHouse, $normStreet, $normPhase);
            $memberStmt->execute();
            $memberRes = $memberStmt->get_result();

            while ($m = $memberRes->fetch_assoc()) {

                $mFullName =
                    $m['firstname'] . ' ' .
                    ($m['middlename'] ? $m['middlename'][0] . '. ' : '') .
                    $m['lastname'] .
                    ($m['suffix'] ? ' ' . $m['suffix'] : '');

                $age = null;
                if (!empty($m['birthdate'])) {
                    $dob = new DateTime($m['birthdate']);
                    $age = (new DateTime())->diff($dob)->y;
                }

                $entry = [
                    'name' => trim($mFullName),
                    'age' => $age
                ];

                if ($age !== null && $age >= 18) {
                    $adults[] = $entry;
                } else {
                    $minors[] = $entry;
                }
            }

            $memberStmt->close();
        }

        $row['adults'] = $adults;
        $row['minors'] = $minors;
        $row['adult_count'] = count($adults);
        $row['minor_count'] = count($minors);

        $data[] = $row;
    }

    echo json_encode($data);
    exit;
}

http_response_code(404);
exit("Not found");
