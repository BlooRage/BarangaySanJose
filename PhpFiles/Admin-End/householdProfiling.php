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

    // =======================
    // ASSIGN OTHER RESIDENT TO HOUSEHOLD
    // =======================
    if (isset($_POST['assign_resident_id'], $_POST['assign_fam_head_id'])) {
        $assignResidentId = trim($_POST['assign_resident_id']);
        $assignFamHeadId = trim($_POST['assign_fam_head_id']);

        if ($assignResidentId === '' || $assignFamHeadId === '') {
            echo json_encode(['success' => false, 'message' => 'Missing resident or family head.']);
            exit;
        }

        // Validate family head
        $stmt = $conn->prepare("
            SELECT resident_id
            FROM residentinformationtbl
            WHERE resident_id = ? AND head_of_family = 1
            LIMIT 1
        ");
        $stmt->bind_param("s", $assignFamHeadId);
        $stmt->execute();
        $headRes = $stmt->get_result();
        $stmt->close();
        if ($headRes->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Family head not found.']);
            exit;
        }

        // Fetch resident info to insert
        $stmt = $conn->prepare("
            SELECT lastname, firstname, middlename, suffix, birthdate
            FROM residentinformationtbl
            WHERE resident_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $assignResidentId);
        $stmt->execute();
        $resRes = $stmt->get_result();
        $stmt->close();
        if ($resRes->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Resident not found.']);
            exit;
        }
        $resRow = $resRes->fetch_assoc();

        // Prevent duplicate entry for the same head and resident data
        $dup = $conn->prepare("
            SELECT 1
            FROM householdmemberinfotbl
            WHERE fam_head_id = ?
              AND last_name = ?
              AND first_name = ?
              AND (middle_name <=> ?)
              AND (suffix <=> ?)
              AND (birthdate <=> ?)
            LIMIT 1
        ");
        $dup->bind_param(
            "ssssss",
            $assignFamHeadId,
            $resRow['lastname'],
            $resRow['firstname'],
            $resRow['middlename'],
            $resRow['suffix'],
            $resRow['birthdate']
        );
        $dup->execute();
        $dupRes = $dup->get_result();
        $dup->close();
        if ($dupRes->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Resident already assigned to this household.']);
            exit;
        }

        // Prevent assigning same resident to any other household (global)
        $dupGlobal = $conn->prepare("
            SELECT 1
            FROM householdmemberinfotbl
            WHERE last_name = ?
              AND first_name = ?
              AND (middle_name <=> ?)
              AND (suffix <=> ?)
              AND (birthdate <=> ?)
            LIMIT 1
        ");
        $dupGlobal->bind_param(
            "sssss",
            $resRow['lastname'],
            $resRow['firstname'],
            $resRow['middlename'],
            $resRow['suffix'],
            $resRow['birthdate']
        );
        $dupGlobal->execute();
        $dupGlobRes = $dupGlobal->get_result();
        $dupGlobal->close();
        if ($dupGlobRes->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Resident already assigned to another household.']);
            exit;
        }

        // Insert into householdmemberinfotbl
        $ins = $conn->prepare("
            INSERT INTO householdmemberinfotbl
                (fam_head_id, last_name, first_name, middle_name, suffix, birthdate)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $ins->bind_param(
            "ssssss",
            $assignFamHeadId,
            $resRow['lastname'],
            $resRow['firstname'],
            $resRow['middlename'],
            $resRow['suffix'],
            $resRow['birthdate']
        );
        if ($ins->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to assign resident.']);
        }
        $ins->close();
        exit;
    }

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

    // Preload non-head residents to identify "other residing members" per address key
    $othersByKey = [];
    $nonHeadSql = "
        SELECT
            r.resident_id,
            r.firstname,
            r.middlename,
            r.lastname,
            r.suffix,
            r.birthdate,
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
        WHERE r.head_of_family = 0
          AND (s.status_name <> 'Archived' OR s.status_name IS NULL)
          AND NOT EXISTS (
              SELECT 1
              FROM householdmemberinfotbl hm
              WHERE hm.last_name = r.lastname
                AND hm.first_name = r.firstname
                AND (hm.middle_name <=> r.middlename)
                AND (hm.suffix <=> r.suffix)
                AND (hm.birthdate <=> r.birthdate)
          )
    ";
    $nonHeadResult = $conn->query($nonHeadSql);
    $getAge = function ($birthdate) {
        if (empty($birthdate)) {
            return null;
        }
        $dob = new DateTime($birthdate);
        return (new DateTime())->diff($dob)->y;
    };
    while ($n = $nonHeadResult->fetch_assoc()) {
        $nKey = implode('|', [
            normalize_simple($n['house_number'] ?? ''),
            normalize_street($n['street_name'] ?? ''),
            normalize_phase($n['phase_number'] ?? ''),
            normalize_subdivision($n['subdivision'] ?? ''),
            normalize_simple($n['area_number'] ?? '')
        ]);
        if (trim($nKey, '|') === '') continue;
        $nFullName =
            $n['firstname'] . ' ' .
            ($n['middlename'] ? $n['middlename'][0] . '. ' : '') .
            $n['lastname'] .
            ($n['suffix'] ? ' ' . $n['suffix'] : '');
        $othersByKey[$nKey][] = [
            'resident_id' => $n['resident_id'],
            'name' => trim($nFullName),
            'age' => $getAge($n['birthdate'] ?? null)
        ];
    }
    $nonHeadResult->close();

    /* ===============================
       FETCH HEADS OF FAMILY (GROUP BY ADDRESS)
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
            a.phase_number,
            a.subdivision,
            a.area_number,\r\n            a.address_id\r\n        FROM residentinformationtbl r
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
    $stmt->execute();
    $result = $stmt->get_result();

    $groups = [];

    while ($row = $result->fetch_assoc()) {

        /* ===============================
           FORMAT NAME & ADDRESS
        =============================== */
        $fullName =
            $row['firstname'] . ' ' .
            ($row['middlename'] ? $row['middlename'][0] . '. ' : '') .
            $row['lastname'] .
            ($row['suffix'] ? ' ' . $row['suffix'] : '');

        $headFullName = trim($fullName);

        $addressParts = [];
        if ($row['house_number']) $addressParts[] = $row['house_number'];
        if ($row['street_name']) $addressParts[] = $row['street_name'] . ' Street';
        if ($row['phase_number']) $addressParts[] = $row['phase_number'];
        if ($row['subdivision']) $addressParts[] = $row['subdivision'];
        if ($row['area_number']) $addressParts[] = $row['area_number'];

        $addressDisplay = $addressParts ? implode(', ', $addressParts) : '�';

        $key = implode('|', [
            normalize_simple($row['house_number'] ?? ''),
            normalize_street($row['street_name'] ?? ''),
            normalize_phase($row['phase_number'] ?? ''),
            normalize_subdivision($row['subdivision'] ?? ''),
            normalize_simple($row['area_number'] ?? '')
        ]);
        if (trim($key, '|') === '') {
            $key = 'unknown|' . $row['resident_id'];
        }

        if (!isset($groups[$key])) {
            $groups[$key] = [
                'address_display' => $addressDisplay,
                'house_number' => $row['house_number'],
                'street_name' => $row['street_name'],
                'phase_number' => $row['phase_number'],
                'subdivision' => $row['subdivision'],
                'area_number' => $row['area_number'],
                'address_id' => $row['address_id'],
                'households' => []
            ];
        }

                        /* ===============================
           HOUSEHOLD MEMBERS (HEAD + ADDED)
        =============================== */
        $adultCount = 0;
        $memberCount = 0;
        $members = [];
        $adults = [];
        $minors = [];

        $headAge = $getAge($row['birthdate']);
        $headEntry = [
            'name' => $headFullName,
            'age' => $headAge
        ];
        $members[] = $headEntry;
        if ($headAge !== null && $headAge >= 18) {
            $adultCount++;
            $adults[] = $headEntry;
        } else {
            $minors[] = $headEntry;
        }
        $memberCount++;

        $otherStmt = $conn->prepare(
            "SELECT last_name, first_name, middle_name, suffix, birthdate
             FROM householdmemberinfotbl
             WHERE fam_head_id = ?"
        );
        $otherStmt->bind_param("s", $row['resident_id']);
        $otherStmt->execute();
        $otherRes = $otherStmt->get_result();

        while ($m = $otherRes->fetch_assoc()) {
            $mFullName =
                $m['first_name'] . ' ' .
                ($m['middle_name'] ? $m['middle_name'][0] . '. ' : '') .
                $m['last_name'] .
                ($m['suffix'] ? ' ' . $m['suffix'] : '');

            $age = $getAge($m['birthdate'] ?? null);
            $entry = [
                'name' => trim($mFullName),
                'age' => $age
            ];
            $members[] = $entry;
            if ($age !== null && $age >= 18) {
                $adultCount++;
                $adults[] = $entry;
            }
            else {
                $minors[] = $entry;
            }
            $memberCount++;
        }
        $otherStmt->close();

        $groups[$key]['households'][] = [
            'resident_id' => $row['resident_id'],
            'head_full_name' => $headFullName,
            'head_of_family' => 1,
            'sex' => $row['sex'],
            'civil_status' => $row['civil_status'],
            'voter_status' => $row['voter_status'],
            'occupation_display' => $row['occupation_display'],
            'adult_count' => $adultCount,
            'member_count' => $memberCount,
            'members' => $members,
            'adults' => $adults,
            'minors' => $minors
        ];

        // Attach other residing members (non-head, not in household) based on the same address key
        $groups[$key]['other_residents'] = $othersByKey[$key] ?? [];
    }

    $data = array_values($groups);

    if ($search !== '') {
        $searchLower = strtolower($search);
        $data = array_values(array_filter($data, function ($group) use ($searchLower) {
            $addressSearch = strtolower(implode(' ', [
                $group['address_display'] ?? '',
                $group['house_number'] ?? '',
                $group['street_name'] ?? '',
                $group['phase_number'] ?? '',
                $group['subdivision'] ?? '',
                $group['area_number'] ?? '',
                $group['address_id'] ?? ''
            ]));

            if (strpos($addressSearch, $searchLower) !== false) {
                return true;
            }

            return false;
        }));
    }

    foreach ($data as &$group) {
        $group['household_count'] = count($group['households']);
    }
    unset($group);

    echo json_encode($data);
    exit;
}
http_response_code(404);
exit("Not found");