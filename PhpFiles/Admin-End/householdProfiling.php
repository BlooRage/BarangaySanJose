<?php
session_start();
require_once "../General/connection.php";
require_once "../General/security.php";

requireRoleSession(['Admin', 'Employee']);

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
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Household profiling is view-only. Updates are disabled.'
    ]);
    exit;
}

if (isset($_GET['fetch'])) {
    header('Content-Type: application/json; charset=utf-8');

    $search = trim($_GET['search'] ?? '');
    $mode = strtolower(trim($_GET['mode'] ?? 'addresses'));
    if ($mode !== 'heads') {
        $mode = 'addresses';
    }

    $getAge = function ($birthdate) {
        if (empty($birthdate)) {
            return null;
        }
        $dob = new DateTime($birthdate);
        return (new DateTime())->diff($dob)->y;
    };

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

    if ($mode === 'heads') {
        $rows = [];
        while ($row = $result->fetch_assoc()) {
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

            $addressDisplay = $addressParts ? implode(', ', $addressParts) : 'ï¿½';

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

            $rows[] = [
                'resident_id' => $row['resident_id'],
                'head_full_name' => $headFullName,
                'address_display' => $addressDisplay,
                'house_number' => $row['house_number'],
                'street_name' => $row['street_name'],
                'phase_number' => $row['phase_number'],
                'subdivision' => $row['subdivision'],
                'area_number' => $row['area_number'],
                'address_id' => $row['address_id'],
                'households' => [[
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
                ]]
            ];
        }

        if ($search !== '') {
            $searchLower = strtolower($search);
            $rows = array_values(array_filter($rows, function ($row) use ($searchLower) {
                $haystack = strtolower(implode(' ', [
                    $row['resident_id'] ?? '',
                    $row['head_full_name'] ?? '',
                    $row['address_display'] ?? '',
                    $row['house_number'] ?? '',
                    $row['street_name'] ?? '',
                    $row['phase_number'] ?? '',
                    $row['subdivision'] ?? '',
                    $row['area_number'] ?? '',
                    $row['address_id'] ?? ''
                ]));
                return strpos($haystack, $searchLower) !== false;
            }));
        }

        echo json_encode($rows);
        exit;
    }

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
