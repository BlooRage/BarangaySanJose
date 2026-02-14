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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

function cleanString($value): string {
    return trim((string)$value);
}

function isValidPersonName(string $value, int $minLetters = 1, int $maxLength = 50): bool {
    $value = trim($value);
    if ($value === '') {
        return false;
    }
    if (strlen($value) > $maxLength) {
        return false;
    }
    if (!preg_match("/^[A-Za-z.' -]+$/", $value)) {
        return false;
    }
    preg_match_all("/[A-Za-z]/", $value, $matches);
    return count($matches[0]) >= $minLetters;
}

function isValidAlphaText(string $value): bool {
    $value = trim($value);
    if ($value === '') {
        return false;
    }
    return (bool)preg_match("/^[A-Za-z .,'-]+$/", $value);
}

function isValidAddressLikeText(string $value): bool {
    $value = trim($value);
    if ($value === '') {
        return false;
    }
    return (bool)preg_match("/^[A-Za-z0-9 .,'#()\\/&-]+$/", $value);
}

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

function getResidentId(mysqli $conn, string $userId): ?string {
    $stmt = $conn->prepare("
        SELECT resident_id
        FROM residentinformationtbl
        WHERE user_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $stmt->bind_result($residentId);
    $stmt->fetch();
    $stmt->close();
    return $residentId ?: null;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
    exit;
}

$userId = $_SESSION['user_id'];

$lastName = cleanString($payload['last_name'] ?? '');
$firstName = cleanString($payload['first_name'] ?? '');
$middleName = cleanString($payload['middle_name'] ?? '');
$suffix = cleanString($payload['suffix'] ?? '');
$phone = cleanString($payload['phone_number'] ?? '');
$relationship = cleanString($payload['relationship'] ?? '');
$address = cleanString($payload['address'] ?? '');

if ($lastName === '' || $firstName === '' || $phone === '' || $relationship === '' || $address === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required emergency contact fields.']);
    exit;
}

if (strlen($firstName) > 30 || strlen($lastName) > 20 || strlen($middleName) > 20) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Name fields are too long.']);
    exit;
}
if (strlen($relationship) > 50) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Relationship must be 50 characters or less.']);
    exit;
}
if (strlen($address) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Address must be 255 characters or less.']);
    exit;
}

if (!isValidPersonName($firstName, 2, 30)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'First name contains invalid characters.']);
    exit;
}
if (!isValidPersonName($lastName, 2, 20)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Last name contains invalid characters.']);
    exit;
}
if ($middleName !== '' && !isValidPersonName($middleName, 1, 20)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Middle name contains invalid characters.']);
    exit;
}
if (!preg_match('/^9\\d{9}$/', $phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Contact number must be 10 digits and start with 9.']);
    exit;
}
if (!isValidAlphaText($relationship)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Relationship contains invalid characters.']);
    exit;
}
if (!isValidAddressLikeText($address)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Address contains invalid characters.']);
    exit;
}

// Check for changes
$stmt = $conn->prepare("
    SELECT last_name, first_name, middle_name, suffix, phone_number, relationship, address
    FROM emergencycontacttbl
    WHERE user_id = ?
    LIMIT 1
");
$stmt->bind_param("s", $userId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    $unchanged =
        trim((string)$existing['last_name']) === $lastName &&
        trim((string)$existing['first_name']) === $firstName &&
        trim((string)$existing['middle_name']) === $middleName &&
        trim((string)$existing['suffix']) === $suffix &&
        trim((string)$existing['phone_number']) === $phone &&
        trim((string)$existing['relationship']) === $relationship &&
        trim((string)$existing['address']) === $address;
    if ($unchanged) {
        echo json_encode(['success' => true, 'message' => 'No changes detected.']);
        exit;
    }
}

$residentId = getResidentId($conn, $userId);
if (!$residentId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Resident profile not found.']);
    exit;
}

$pendingStatusId = getStatusId($conn, 'PendingRequest', 'EditRequest');
if ($pendingStatusId === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Edit request status missing.']);
    exit;
}

// Block duplicate pending emergency requests
$dup = $conn->prepare("
    SELECT 1
    FROM resident_edit_requesttbl
    WHERE resident_id = ? AND request_type = 'emergency' AND status_id = ?
    LIMIT 1
");
$dup->bind_param("si", $residentId, $pendingStatusId);
$dup->execute();
$dupExists = $dup->get_result()->num_rows > 0;
$dup->close();
if ($dupExists) {
    echo json_encode(['success' => true, 'message' => 'You already have a pending emergency edit request.']);
    exit;
}

$changes = [
    'last_name' => $lastName,
    'first_name' => $firstName,
    'middle_name' => $middleName,
    'suffix' => $suffix,
    'phone_number' => $phone,
    'relationship' => $relationship,
    'address' => $address,
];

$stmt = $conn->prepare("
    INSERT INTO resident_edit_requesttbl
        (resident_id, user_id, request_type, status_id, requested_changes)
    VALUES (?, ?, 'emergency', ?, ?)
");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare edit request.']);
    exit;
}
$changesJson = json_encode($changes, JSON_UNESCAPED_SLASHES);
$stmt->bind_param("ssis", $residentId, $userId, $pendingStatusId, $changesJson);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to submit edit request.']);
    exit;
}
$stmt->close();

$response = [
    'success' => true,
    'message' => 'Emergency edit request submitted.'
];
echo json_encode($response);
