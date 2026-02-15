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
require_once __DIR__ . '/../General/uniqueIDGenerate.php';

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

function isValidAddressLikeText(string $value): bool {
    $value = trim($value);
    if ($value === '') {
        return false;
    }
    return (bool)preg_match("/^[A-Za-z0-9 .,'#()\\/&-]+$/", $value);
}

function assertMaxLength(string $label, string $value, int $max): void {
    if (strlen($value) > $max) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "{$label} must be {$max} characters or less."]);
        exit;
    }
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
$residentId = getResidentId($conn, $userId);
if (!$residentId) {
    echo json_encode(['success' => false, 'message' => 'Resident profile not found.']);
    exit;
}

$unitNumber = cleanString($payload['unit_number'] ?? '');
$streetNumber = cleanString($payload['street_number'] ?? '');
$streetName = cleanString($payload['street_name'] ?? '');
$phaseNumber = cleanString($payload['phase_number'] ?? '');
$subdivision = cleanString($payload['subdivision'] ?? '');
$areaNumber = cleanString($payload['area_number'] ?? '');

if ($streetNumber === '' || $streetName === '' || $areaNumber === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Street number, street name, and area number are required.']);
    exit;
}

assertMaxLength('Unit number', $unitNumber, 50);
assertMaxLength('Street number', $streetNumber, 50);
assertMaxLength('Street name', $streetName, 150);
assertMaxLength('Phase number', $phaseNumber, 50);
assertMaxLength('Subdivision', $subdivision, 150);
assertMaxLength('Area number', $areaNumber, 50);

$addressLikeChecks = [
    'Unit number' => $unitNumber,
    'Street number' => $streetNumber,
    'Street name' => $streetName,
    'Phase number' => $phaseNumber,
    'Subdivision' => $subdivision,
    'Area number' => $areaNumber,
];
foreach ($addressLikeChecks as $label => $value) {
    if ($value !== '' && !isValidAddressLikeText($value)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "{$label} contains invalid characters."]);
        exit;
    }
}

// Fetch latest address
$latestAddress = null;
$stmt = $conn->prepare("
    SELECT address_id, unit_number, street_number, street_name, phase_number, subdivision, area_number,
           house_type, house_ownership, residency_duration, status_id_residency
    FROM residentaddresstbl
    WHERE resident_id = ?
    ORDER BY address_id DESC
    LIMIT 1
");
$stmt->bind_param("s", $residentId);
$stmt->execute();
$res = $stmt->get_result();
$latestAddress = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$latestAddress) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No address record found.']);
    exit;
}

$newAddress = [
    'unit_number' => $unitNumber !== '' ? $unitNumber : (string)($latestAddress['unit_number'] ?? ''),
    'street_number' => $streetNumber,
    'street_name' => $streetName,
    'phase_number' => $phaseNumber !== '' ? $phaseNumber : (string)($latestAddress['phase_number'] ?? ''),
    'subdivision' => $subdivision !== '' ? $subdivision : (string)($latestAddress['subdivision'] ?? ''),
    'area_number' => $areaNumber,
    'house_type' => (string)($latestAddress['house_type'] ?? ''),
    'house_ownership' => (string)($latestAddress['house_ownership'] ?? ''),
    'residency_duration' => (string)($latestAddress['residency_duration'] ?? ''),
];

$changed = false;
foreach (['unit_number', 'street_number', 'street_name', 'phase_number', 'subdivision', 'area_number'] as $field) {
    $oldVal = trim((string)($latestAddress[$field] ?? ''));
    $newVal = trim((string)($newAddress[$field] ?? ''));
    if ($oldVal !== $newVal) {
        $changed = true;
        break;
    }
}

if (!$changed) {
    echo json_encode(['success' => true, 'message' => 'No changes detected.']);
    exit;
}

$pendingStatusId = getStatusId($conn, 'PendingRequest', 'EditRequest');
if ($pendingStatusId === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Edit request status missing.']);
    exit;
}

// Block duplicate pending address requests
$dup = $conn->prepare("
    SELECT 1
    FROM resident_edit_requesttbl
    WHERE resident_id = ? AND request_type = 'address' AND status_id = ?
    LIMIT 1
");
$dup->bind_param("si", $residentId, $pendingStatusId);
$dup->execute();
$dupExists = $dup->get_result()->num_rows > 0;
$dup->close();
if ($dupExists) {
    echo json_encode(['success' => true, 'message' => 'You already have a pending address edit request.']);
    exit;
}

$changes = [
    'unit_number' => $newAddress['unit_number'],
    'street_number' => $newAddress['street_number'],
    'street_name' => $newAddress['street_name'],
    'phase_number' => $newAddress['phase_number'],
    'subdivision' => $newAddress['subdivision'],
    'area_number' => $newAddress['area_number'],
];
$newHeadResidentId = cleanString($payload['new_head_resident_id'] ?? '');
if ($newHeadResidentId !== '') {
    $changes['new_head_resident_id'] = $newHeadResidentId;
}

$stmt = $conn->prepare("
    INSERT INTO resident_edit_requesttbl
        (resident_id, user_id, request_type, status_id, requested_changes)
    VALUES (?, ?, 'address', ?, ?)
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

echo json_encode(['success' => true, 'message' => 'Address edit request submitted.']);
