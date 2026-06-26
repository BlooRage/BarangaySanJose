<?php
require_once __DIR__ . '/../General/security.php';

header('Content-Type: application/json');

require_once __DIR__ . '/../General/connection.php';

requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin'], true);

$userId = (string)($_SESSION['user_id'] ?? '');
if ($userId === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$conn->query("ALTER TABLE officialinformationtbl ADD COLUMN IF NOT EXISTS position_access VARCHAR(100) NULL AFTER role_access");
$conn->query("ALTER TABLE officialinformationtbl ADD COLUMN IF NOT EXISTS emergency_contact_name VARCHAR(150) NULL");
$conn->query("ALTER TABLE officialinformationtbl ADD COLUMN IF NOT EXISTS emergency_contact_relationship VARCHAR(80) NULL");
$conn->query("ALTER TABLE officialinformationtbl ADD COLUMN IF NOT EXISTS emergency_contact_phone VARCHAR(15) NULL");
$conn->query("ALTER TABLE officialinformationtbl ADD COLUMN IF NOT EXISTS emergency_contact_address VARCHAR(255) NULL");
$conn->query("ALTER TABLE officialinformationtbl ADD COLUMN IF NOT EXISTS house_number VARCHAR(50) NULL");
$conn->query("ALTER TABLE officialinformationtbl ADD COLUMN IF NOT EXISTS street_name VARCHAR(150) NULL");
$conn->query("ALTER TABLE officialinformationtbl ADD COLUMN IF NOT EXISTS subdivision VARCHAR(150) NULL");
$conn->query("ALTER TABLE officialinformationtbl ADD COLUMN IF NOT EXISTS address_mode VARCHAR(20) NULL");
$conn->query("ALTER TABLE officialinformationtbl ADD COLUMN IF NOT EXISTS block_number VARCHAR(50) NULL");
$conn->query("ALTER TABLE officialinformationtbl ADD COLUMN IF NOT EXISTS lot_number VARCHAR(50) NULL");
$conn->query("ALTER TABLE officialinformationtbl ADD COLUMN IF NOT EXISTS barangay VARCHAR(150) NULL");
$conn->query("ALTER TABLE officialinformationtbl ADD COLUMN IF NOT EXISTS municipality_city VARCHAR(150) NULL");
$conn->query("ALTER TABLE officialinformationtbl ADD COLUMN IF NOT EXISTS province VARCHAR(150) NULL");

$raw = file_get_contents('php://input');
$data = json_decode((string)$raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$section = trim((string)($data['section'] ?? ''));

$existsStmt = $conn->prepare("SELECT official_id FROM officialinformationtbl WHERE user_id = ? LIMIT 1");
if (!$existsStmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error.']);
    exit;
}
$existsStmt->bind_param('s', $userId);
$existsStmt->execute();
$row = $existsStmt->get_result()->fetch_assoc();
$existsStmt->close();
if (!$row) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Official profile not found.']);
    exit;
}

$normalizePhone10 = static function (string $rawPhone): string {
    $digits = preg_replace('/\D+/', '', $rawPhone);
    if (strlen($digits) === 11 && str_starts_with($digits, '0')) $digits = substr($digits, 1);
    if (strlen($digits) === 12 && str_starts_with($digits, '63')) $digits = substr($digits, 2);
    return substr($digits, 0, 10);
};

if ($section === 'personal') {
    $lastName = trim((string)($data['lastname'] ?? ''));
    $firstName = trim((string)($data['firstname'] ?? ''));
    $middleName = trim((string)($data['middlename'] ?? ''));
    $suffix = trim((string)($data['suffix'] ?? ''));
    $birthdate = trim((string)($data['birthdate'] ?? ''));
    $sex = trim((string)($data['sex'] ?? ''));
    $civilStatus = trim((string)($data['civil_status'] ?? ''));
    $department = trim((string)($data['department'] ?? ''));
    $positionAccess = trim((string)($data['position_access'] ?? ''));

    if ($lastName === '' || $firstName === '' || $birthdate === '' || $sex === '' || $civilStatus === '' || $department === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Missing required personal fields.']);
        exit;
    }
    if (!preg_match('/^[A-Za-z][A-Za-z .\'-]{0,99}$/', $lastName) || !preg_match('/^[A-Za-z][A-Za-z .\'-]{0,99}$/', $firstName)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid name format.']);
        exit;
    }
    if ($middleName !== '' && !preg_match('/^[A-Za-z][A-Za-z .\'-]{0,99}$/', $middleName)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid middle name format.']);
        exit;
    }
    if ($suffix !== '' && !preg_match('/^[A-Za-z0-9 .\'-]{1,20}$/', $suffix)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid suffix format.']);
        exit;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Birthdate is invalid.']);
        exit;
    }
    $personalEncrypted = pii_encrypt_field_map([
        'lastname' => $lastName,
        'firstname' => $firstName,
        'middlename' => $middleName,
        'suffix' => $suffix,
        'birthdate' => $birthdate,
        'sex' => $sex,
        'civil_status' => $civilStatus,
    ]);

    $stmt = $conn->prepare("UPDATE officialinformationtbl SET lastname=?, firstname=?, middlename=?, suffix=?, birthdate=?, sex=?, civil_status=?, department=?, position_access=?, last_updated=CURRENT_TIMESTAMP WHERE user_id=? LIMIT 1");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to prepare update.']);
        exit;
    }
    $stmt->bind_param(
        'ssssssssss',
        $personalEncrypted['lastname'],
        $personalEncrypted['firstname'],
        $personalEncrypted['middlename'],
        $personalEncrypted['suffix'],
        $personalEncrypted['birthdate'],
        $personalEncrypted['sex'],
        $personalEncrypted['civil_status'],
        $department,
        $positionAccess,
        $userId
    );
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save personal information.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Personal information updated.']);
    exit;
}

if ($section === 'emergency') {
    $name = trim((string)($data['emergency_contact_name'] ?? ''));
    $relationship = trim((string)($data['emergency_contact_relationship'] ?? ''));
    $phone10 = $normalizePhone10((string)($data['emergency_contact_phone'] ?? ''));
    $address = trim((string)($data['emergency_contact_address'] ?? ''));

    if ($name === '' || $relationship === '' || $phone10 === '' || $address === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Missing required emergency contact fields.']);
        exit;
    }
    if (!preg_match('/^9\d{9}$/', $phone10)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Emergency contact number must be 9XXXXXXXXX.']);
        exit;
    }
    $emergencyEncrypted = pii_encrypt_field_map([
        'emergency_contact_name' => $name,
        'emergency_contact_relationship' => $relationship,
        'emergency_contact_phone' => $phone10,
        'emergency_contact_address' => $address,
    ]);

    $stmt = $conn->prepare("UPDATE officialinformationtbl SET emergency_contact_name=?, emergency_contact_relationship=?, emergency_contact_phone=?, emergency_contact_address=?, last_updated=CURRENT_TIMESTAMP WHERE user_id=? LIMIT 1");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to prepare emergency update.']);
        exit;
    }
    $stmt->bind_param(
        'sssss',
        $emergencyEncrypted['emergency_contact_name'],
        $emergencyEncrypted['emergency_contact_relationship'],
        $emergencyEncrypted['emergency_contact_phone'],
        $emergencyEncrypted['emergency_contact_address'],
        $userId
    );
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save emergency contact.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Emergency contact updated.']);
    exit;
}

if ($section === 'address') {
    $addressMode = strtolower(trim((string)($data['address_mode'] ?? 'street')));
    if (!in_array($addressMode, ['street', 'block_lot'], true)) {
        $addressMode = 'street';
    }
    $house = trim((string)($data['house_number'] ?? ''));
    $street = trim((string)($data['street_name'] ?? ''));
    $subdivision = trim((string)($data['subdivision'] ?? ''));
    $block = trim((string)($data['block_number'] ?? ''));
    $lot = trim((string)($data['lot_number'] ?? ''));
    $barangay = trim((string)($data['barangay'] ?? ''));
    $city = trim((string)($data['municipality_city'] ?? ''));
    $province = trim((string)($data['province'] ?? ''));

    $missingCore = ($barangay === '' || $city === '' || $province === '');
    $missingStreet = ($addressMode === 'street' && ($house === '' || $street === ''));
    $missingBlockLot = ($addressMode === 'block_lot' && ($block === '' || $lot === ''));
    if ($missingCore || $missingStreet || $missingBlockLot) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Complete the required address fields.']);
        exit;
    }
    if ($addressMode === 'street') {
        $block = '';
        $lot = '';
    } else {
        $house = '';
        $street = '';
    }
    $addressEncrypted = pii_encrypt_field_map([
        'address_mode' => $addressMode,
        'house_number' => $house,
        'street_name' => $street,
        'block_number' => $block,
        'lot_number' => $lot,
        'barangay' => $barangay,
        'municipality_city' => $city,
        'province' => $province,
    ]);

    $stmt = $conn->prepare("UPDATE officialinformationtbl SET address_mode=?, house_number=?, street_name=?, subdivision=?, block_number=?, lot_number=?, barangay=?, municipality_city=?, province=?, last_updated=CURRENT_TIMESTAMP WHERE user_id=? LIMIT 1");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to prepare address update.']);
        exit;
    }
    $stmt->bind_param(
        'ssssssssss',
        $addressEncrypted['address_mode'],
        $addressEncrypted['house_number'],
        $addressEncrypted['street_name'],
        $subdivision,
        $addressEncrypted['block_number'],
        $addressEncrypted['lot_number'],
        $addressEncrypted['barangay'],
        $addressEncrypted['municipality_city'],
        $addressEncrypted['province'],
        $userId
    );
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save address.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Address updated.']);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid update section.']);
