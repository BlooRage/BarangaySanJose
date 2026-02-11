<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../General/connection.php';

function getStatusId(mysqli $conn, string $name, string $type): ?int {
    $stmt = $conn->prepare("
        SELECT status_id
        FROM statuslookuptbl
        WHERE status_name = ? AND status_type = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("ss", $name, $type);
    $stmt->execute();
    $stmt->bind_result($statusId);
    $statusId = $stmt->fetch() ? (int)$statusId : null;
    $stmt->close();
    return $statusId;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
    exit;
}

// Resolve resident id for current user
$residentId = '';
$stmt = $conn->prepare("
    SELECT resident_id
    FROM residentinformationtbl
    WHERE user_id = ?
    LIMIT 1
");
$stmt->bind_param("s", $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($residentId);
$stmt->fetch();
$stmt->close();

if ($residentId === '') {
    echo json_encode(['success' => false, 'message' => 'Resident profile not found.']);
    exit;
}

$memberActiveStatusId = getStatusId($conn, 'Active', 'HouseholdMember');
if ($memberActiveStatusId === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Household member status not found.']);
    exit;
}

// Find household id for this resident (as member or head)
$householdId = null;
$stmt = $conn->prepare("
    SELECT hm.household_id
    FROM householdmemberresidenttbl hm
    WHERE hm.resident_id = ? AND hm.status_id = ?
    ORDER BY hm.household_id DESC
    LIMIT 1
");
$stmt->bind_param("si", $residentId, $memberActiveStatusId);
$stmt->execute();
$stmt->bind_result($householdId);
$stmt->fetch();
$stmt->close();

if (!$householdId) {
    echo json_encode([
        'success' => true,
        'members' => [],
        'has_household' => false,
        'is_head' => false,
        'resident_id' => $residentId,
        'address' => null,
        'minor_count' => 0,
        'adult_count' => 0,
    ]);
    exit;
}

$headResidentId = null;
$stmt = $conn->prepare("
    SELECT head_resident_id
    FROM householdtbl
    WHERE household_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $householdId);
$stmt->execute();
$stmt->bind_result($headResidentId);
$stmt->fetch();
$stmt->close();

$addressDisplay = null;
if ($headResidentId) {
    $stmt = $conn->prepare("
        SELECT unit_number, street_number, street_name, phase_number, subdivision, area_number
        FROM residentaddresstbl
        WHERE resident_id = ?
        ORDER BY address_id DESC
        LIMIT 1
    ");
    $stmt->bind_param("s", $headResidentId);
    $stmt->execute();
    $resAddr = $stmt->get_result();
    if ($addrRow = $resAddr->fetch_assoc()) {
        $unitNumber = trim((string)($addrRow['unit_number'] ?? ''));
        $houseNo = trim((string)($addrRow['street_number'] ?? ''));
        $streetName = trim((string)($addrRow['street_name'] ?? ''));
        $phase = trim((string)($addrRow['phase_number'] ?? ''));
        $subdivision = trim((string)($addrRow['subdivision'] ?? ''));
        $area = trim((string)($addrRow['area_number'] ?? ''));

        $streetDisplay = $streetName;
        if ($streetName !== '' && stripos($streetName, 'block') === false) {
            $streetDisplay = $streetName . ' Street';
        }
        $parts = [];
        if ($unitNumber !== '') $parts[] = 'Unit ' . $unitNumber;
        if ($houseNo !== '') $parts[] = $houseNo;
        if ($streetDisplay !== '') $parts[] = $streetDisplay;
        if ($phase !== '') $parts[] = $phase;
        if ($subdivision !== '') $parts[] = $subdivision;
        $parts[] = 'San Jose';
        if ($area !== '') $parts[] = $area;
        $parts[] = 'Rodriguez';
        $parts[] = 'Rizal';
        $parts[] = '1860';
        $addressDisplay = implode(', ', array_filter($parts, fn($v) => $v !== ''));
    }
    $stmt->close();
}

$stmt = $conn->prepare("
    SELECT
        r.resident_id,
        r.firstname,
        r.middlename,
        r.lastname,
        r.suffix,
        r.birthdate,
        r.sex,
        r.civil_status,
        hm.role
    FROM householdmemberresidenttbl hm
    INNER JOIN residentinformationtbl r
        ON r.resident_id = hm.resident_id
    WHERE hm.household_id = ?
      AND hm.status_id = ?
    ORDER BY
        CASE WHEN hm.role = 'Head' THEN 0 ELSE 1 END,
        r.lastname, r.firstname
");
$stmt->bind_param("ii", $householdId, $memberActiveStatusId);
$stmt->execute();
$res = $stmt->get_result();

$members = [];
$isHead = false;
$minorCount = 0;
$adultCount = 0;
while ($row = $res->fetch_assoc()) {
    if ($row['role'] === 'Head' && $row['resident_id'] === $residentId) {
        $isHead = true;
    }
    $age = null;
    if (!empty($row['birthdate'])) {
        try {
            $dob = new DateTime($row['birthdate']);
            $age = (new DateTime())->diff($dob)->y;
        } catch (Exception $e) {
            $age = null;
        }
    }
    if ($age !== null) {
        if ($age < 18) {
            $minorCount++;
        } else {
            $adultCount++;
        }
    }
    $middle = trim((string)$row['middlename']);
    $name = trim($row['firstname'] . ' ' . ($middle !== '' ? $middle[0] . '. ' : '') . $row['lastname'] . ($row['suffix'] ? ' ' . $row['suffix'] : ''));
    $members[] = [
        'resident_id' => $row['resident_id'],
        'name' => $name,
        'role' => $row['role'],
        'age' => $age,
        'sex' => $row['sex'],
        'civil_status' => $row['civil_status'],
    ];
}
$stmt->close();

if ($headResidentId) {
    $stmt = $conn->prepare("
        SELECT
            household_member_id,
            first_name,
            middle_name,
            last_name,
            suffix,
            birthdate
        FROM householdmemberinfotbl
        WHERE fam_head_id = ?
        ORDER BY last_name, first_name
    ");
    $stmt->bind_param("s", $headResidentId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $age = null;
        if (!empty($row['birthdate'])) {
            try {
                $dob = new DateTime($row['birthdate']);
                $age = (new DateTime())->diff($dob)->y;
            } catch (Exception $e) {
                $age = null;
            }
        }
        if ($age !== null) {
            if ($age < 18) {
                $minorCount++;
            } else {
                $adultCount++;
            }
        }
        $middle = trim((string)$row['middle_name']);
        $name = trim($row['first_name'] . ' ' . ($middle !== '' ? $middle[0] . '. ' : '') . $row['last_name'] . ($row['suffix'] ? ' ' . $row['suffix'] : ''));
        $members[] = [
            'resident_id' => null,
            'info_member_id' => $row['household_member_id'],
            'name' => $name,
            'role' => 'Member',
            'age' => $age,
            'sex' => null,
            'civil_status' => null,
        ];
    }
    $stmt->close();
}

echo json_encode([
    'success' => true,
    'members' => $members,
    'has_household' => true,
    'is_head' => $isHead,
    'resident_id' => $residentId,
    'address' => $addressDisplay,
    'minor_count' => $minorCount,
    'adult_count' => $adultCount,
]);
?>
