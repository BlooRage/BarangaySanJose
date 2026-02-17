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
require_once __DIR__ . '/../General/residentTransaction.php';

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

function isHeadOfFamily(mysqli $conn, string $residentId): bool {
    $stmt = $conn->prepare("
        SELECT head_of_family
        FROM residentinformationtbl
        WHERE resident_id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("s", $residentId);
    $stmt->execute();
    $stmt->bind_result($headRaw);
    $value = '';
    if ($stmt->fetch()) {
        $value = strtolower(trim((string)$headRaw));
    }
    $stmt->close();
    return in_array($value, ['yes', 'true', '1', 'y'], true);
}

function getActiveHouseholdId(mysqli $conn, string $residentId, int $activeStatusId): ?int {
    $stmt = $conn->prepare("
        SELECT household_id
        FROM householdmemberresidenttbl
        WHERE resident_id = ? AND status_id = ?
        ORDER BY household_id DESC
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("si", $residentId, $activeStatusId);
    $stmt->execute();
    $stmt->bind_result($householdId);
    $value = $stmt->fetch() ? (int)$householdId : null;
    $stmt->close();
    return $value ?: null;
}

function countOtherActiveResidentMembers(mysqli $conn, int $householdId, string $residentId, int $activeStatusId): int {
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM householdmemberresidenttbl
        WHERE household_id = ?
          AND status_id = ?
          AND resident_id IS NOT NULL
          AND resident_id <> ?
    ");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param("iis", $householdId, $activeStatusId, $residentId);
    $stmt->execute();
    $stmt->bind_result($count);
    $value = $stmt->fetch() ? (int)$count : 0;
    $stmt->close();
    return $value;
}

function isResidentEligibleNewHead(mysqli $conn, int $householdId, string $candidateResidentId, int $activeStatusId): bool {
    $stmt = $conn->prepare("
        SELECT 1
        FROM householdmemberresidenttbl
        WHERE household_id = ?
          AND status_id = ?
          AND resident_id = ?
          AND role <> 'Head'
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("iis", $householdId, $activeStatusId, $candidateResidentId);
    $stmt->execute();
    $ok = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $ok;
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
$addressSystem = strtolower(cleanString($payload['address_system'] ?? 'house'));
$houseOwnership = cleanString($payload['house_ownership'] ?? '');
$houseType = cleanString($payload['house_type'] ?? '');
$residencyDuration = 'Less than 6 months';

if (!in_array($addressSystem, ['house', 'lot_block'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid address system selected.']);
    exit;
}

if ($addressSystem === 'house') {
    if ($streetNumber === '' || $streetName === '' || $areaNumber === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'House number, street name, and area are required.']);
        exit;
    }
} else { // lot_block
    if ($streetNumber === '' || $phaseNumber === '' || $areaNumber === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Lot number, block number, and area are required.']);
        exit;
    }
}

if ($houseOwnership === '' || $houseType === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'House ownership and house type are required.']);
    exit;
}

assertMaxLength('Unit number', $unitNumber, 50);
assertMaxLength('Street number', $streetNumber, 50);
assertMaxLength('Street name', $streetName, 150);
assertMaxLength('Phase number', $phaseNumber, 50);
assertMaxLength('Subdivision', $subdivision, 150);
assertMaxLength('Area number', $areaNumber, 50);
assertMaxLength('House ownership', $houseOwnership, 50);
assertMaxLength('House type', $houseType, 100);
assertMaxLength('Residency duration', $residencyDuration, 100);

$addressLikeChecks = [
    'Unit number' => $unitNumber,
    'Street number' => $streetNumber,
    'Street name' => $streetName,
    'Phase number' => $phaseNumber,
    'Subdivision' => $subdivision,
    'Area number' => $areaNumber,
    'House ownership' => $houseOwnership,
    'House type' => $houseType,
    'Residency duration' => $residencyDuration,
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

// Align with profile display baseline: use the latest approved address edit request values.
$approvedAddressStatusId = getStatusId($conn, 'ApprovedRequest', 'EditRequest');
if ($approvedAddressStatusId !== null) {
    $stmtLatestApproved = $conn->prepare("
        SELECT requested_changes
        FROM resident_edit_requesttbl
        WHERE resident_id = ?
          AND request_type = 'address'
          AND status_id = ?
        ORDER BY reviewed_at DESC, request_id DESC
        LIMIT 1
    ");
    if ($stmtLatestApproved) {
        $stmtLatestApproved->bind_param("si", $residentId, $approvedAddressStatusId);
        $stmtLatestApproved->execute();
        $rowApproved = $stmtLatestApproved->get_result()->fetch_assoc();
        $stmtLatestApproved->close();
        if ($rowApproved && isset($rowApproved['requested_changes'])) {
            $approvedChanges = json_decode((string)$rowApproved['requested_changes'], true);
            if (is_array($approvedChanges)) {
                foreach ([
                    'unit_number',
                    'street_number',
                    'street_name',
                    'phase_number',
                    'subdivision',
                    'area_number',
                    'house_type',
                    'house_ownership',
                    'residency_duration'
                ] as $key) {
                    if (array_key_exists($key, $approvedChanges)) {
                        $latestAddress[$key] = (string)$approvedChanges[$key];
                    }
                }
            }
        }
    }
}

$newAddress = [
    'unit_number' => $unitNumber !== '' ? $unitNumber : (string)($latestAddress['unit_number'] ?? ''),
    'street_number' => $streetNumber,
    'street_name' => $streetName,
    'phase_number' => $phaseNumber !== '' ? $phaseNumber : (string)($latestAddress['phase_number'] ?? ''),
    'subdivision' => $subdivision !== '' ? $subdivision : (string)($latestAddress['subdivision'] ?? ''),
    'area_number' => $areaNumber,
    'house_type' => $houseType,
    'house_ownership' => $houseOwnership,
    'residency_duration' => $residencyDuration,
    'address_system' => $addressSystem,
];

$changed = false;
foreach (['unit_number', 'street_number', 'street_name', 'phase_number', 'subdivision', 'area_number', 'house_type', 'house_ownership', 'residency_duration'] as $field) {
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
    echo json_encode(['success' => true, 'message' => 'You already have a pending address change request.']);
    exit;
}

$changes = [
    'unit_number' => $newAddress['unit_number'],
    'street_number' => $newAddress['street_number'],
    'street_name' => $newAddress['street_name'],
    'phase_number' => $newAddress['phase_number'],
    'subdivision' => $newAddress['subdivision'],
    'area_number' => $newAddress['area_number'],
    'house_type' => $newAddress['house_type'],
    'house_ownership' => $newAddress['house_ownership'],
    'residency_duration' => $newAddress['residency_duration'],
    'address_system' => $newAddress['address_system'],
];
$newHeadResidentId = cleanString($payload['new_head_resident_id'] ?? '');
$activeHouseholdMemberStatusId = getStatusId($conn, 'Active', 'HouseholdMember');
$isHead = isHeadOfFamily($conn, $residentId);
if ($activeHouseholdMemberStatusId !== null && $isHead) {
    $householdId = getActiveHouseholdId($conn, $residentId, $activeHouseholdMemberStatusId);
    if ($householdId !== null) {
        $otherActiveResidentCount = countOtherActiveResidentMembers(
            $conn,
            $householdId,
            $residentId,
            $activeHouseholdMemberStatusId
        );
        if ($otherActiveResidentCount > 0 && $newHeadResidentId === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Please assign a new head of household before submitting.'
            ]);
            exit;
        }
        if ($newHeadResidentId !== '' && !isResidentEligibleNewHead($conn, $householdId, $newHeadResidentId, $activeHouseholdMemberStatusId)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Selected new head is not an eligible active household member.'
            ]);
            exit;
        }
    }
}

if ($newHeadResidentId !== '') {
    if (!$isHead) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Only the head of family can assign a new head of household.'
        ]);
        exit;
    }
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
$requestId = (int)$stmt->insert_id;
$stmt->close();

createResidentTransaction(
    $conn,
    (string)$userId,
    (string)$userId,
    'EDIT_REQUEST',
    (string)$requestId,
    mapEditRequestTransactionType('address'),
    mapEditRequestTitle('address'),
    (int)$pendingStatusId,
    mapEditRequestDescription('address'),
    ['request_type' => 'address']
);

echo json_encode(['success' => true, 'message' => 'Address change request submitted.']);
