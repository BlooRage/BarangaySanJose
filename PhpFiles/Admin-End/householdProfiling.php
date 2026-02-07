<?php
session_start();
require_once "../General/connection.php";

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $famHeadId = trim($_POST['fam_head_id'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');

    if ($famHeadId === '' || $lastName === '' || $firstName === '' || $birthdate === '') {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing.']);
        exit;
    }

    $dob = DateTime::createFromFormat('Y-m-d', $birthdate);
    if (!$dob || $dob->format('Y-m-d') !== $birthdate) {
        echo json_encode(['success' => false, 'message' => 'Invalid birthdate format.']);
        exit;
    }

    $checkStmt = $conn->prepare("
        SELECT resident_id
        FROM residentinformationtbl
        WHERE resident_id = ? AND head_of_family = 1
        LIMIT 1
    ");
    $checkStmt->bind_param("s", $famHeadId);
    $checkStmt->execute();
    $checkRes = $checkStmt->get_result();
    $checkStmt->close();

    if ($checkRes->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Family head not found.']);
        exit;
    }

    $middleName = $middleName !== '' ? $middleName : null;
    $suffix = $suffix !== '' ? $suffix : null;

    $insertStmt = $conn->prepare("
        INSERT INTO householdmemberinfotbl
            (fam_head_id, last_name, first_name, middle_name, suffix, birthdate)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $insertStmt->bind_param("ssssss", $famHeadId, $lastName, $firstName, $middleName, $suffix, $birthdate);

    if ($insertStmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add household member.']);
    }
    $insertStmt->close();
    exit;
}

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
        if ($row['street_name']) $addressParts[] = $row['street_name'] . ' Street';
        if ($row['phase_number']) $addressParts[] = $row['phase_number'];
        if ($row['subdivision']) $addressParts[] = $row['subdivision'];
        if ($row['area_number']) $addressParts[] = $row['area_number'];

        $row['address_display'] = $addressParts ? implode(', ', $addressParts) : '—';

        /* ===============================
           HOUSEHOLD MEMBERS
        =============================== */
        $adults = [];
        $minors = [];

        if (!empty($row['house_number']) && !empty($row['street_name'])) {

            $normHouse = normalize_simple($row['house_number']);
            $normStreet = normalize_street($row['street_name']);
            $normPhase = normalize_phase($row['phase_number'] ?? '');
            $normSubd = normalize_subdivision($row['subdivision'] ?? '');

            $houseExpr = "LOWER(
                REPLACE(
                    REPLACE(
                        REPLACE(
                            REPLACE(
                                REPLACE(
                                    REPLACE(IFNULL(a.street_number,''),' ',''),'-',''),
                                '.',''),
                            '/',''),
                        ',',''),
                    '_','')
            )";
            $streetExpr = "REPLACE(
                REPLACE(
                    REPLACE(
                        REPLACE(
                            REPLACE(
                                REPLACE(
                                    REPLACE(
                                        REPLACE(LOWER(IFNULL(a.street_name,'')),' street',''),
                                    'street',''),
                                ' st.',''),
                            ' st',''),
                        '.',''),
                    ' ',''),
                '-',''),
            '/','')";
            $streetExpr = "REPLACE(REPLACE(REPLACE($streetExpr,',',''),'_',''),'#','')";
            $phaseBaseExpr = "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(a.phase_number,''),' ',''),'-',''),'.',''),'/',''))";
            $phaseExpr = "CASE
                WHEN $phaseBaseExpr LIKE 'phase%' THEN SUBSTRING($phaseBaseExpr, 6)
                WHEN $phaseBaseExpr LIKE 'ph%' THEN SUBSTRING($phaseBaseExpr, 3)
                ELSE $phaseBaseExpr
            END";
            $subdExpr = "REPLACE(
                REPLACE(
                    REPLACE(
                        REPLACE(
                            REPLACE(
                                REPLACE(
                                    REPLACE(LOWER(IFNULL(a.subdivision,'')),' subdivision',''),
                                'subdivision',''),
                            ' subd.',''),
                        ' subd',''),
                    '.',''),
                ' ',''),
            '/','')";
            $subdExpr = "REPLACE(REPLACE(REPLACE($subdExpr,',',''),'_',''),'#','')";

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
                  AND $houseExpr = ?
                  AND $streetExpr = ?
            ";

            $types = "ss";
            $params = [$normHouse, $normStreet];
            if ($normPhase !== '') {
                $memberSql .= " AND $phaseExpr = ?";
                $types .= "s";
                $params[] = $normPhase;
            }
            if ($normSubd !== '') {
                $memberSql .= " AND $subdExpr = ?";
                $types .= "s";
                $params[] = $normSubd;
            }

            $memberStmt = $conn->prepare($memberSql);
            $memberStmt->bind_param($types, ...$params);
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

        $otherStmt = $conn->prepare("
            SELECT last_name, first_name, middle_name, suffix, birthdate
            FROM householdmemberinfotbl
            WHERE fam_head_id = ?
        ");
        $otherStmt->bind_param("s", $row['resident_id']);
        $otherStmt->execute();
        $otherRes = $otherStmt->get_result();

        while ($m = $otherRes->fetch_assoc()) {
            $mFullName =
                $m['first_name'] . ' ' .
                ($m['middle_name'] ? $m['middle_name'][0] . '. ' : '') .
                $m['last_name'] .
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
        $otherStmt->close();

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
