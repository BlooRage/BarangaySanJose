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
require_once __DIR__ . '/../General/uploadLimits.php';

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

function normalizeFilesArray($file): array {
    if (!$file || !is_array($file) || !isset($file['name'])) {
        return [];
    }
    if (!is_array($file['name'])) {
        return [$file];
    }
    $normalized = [];
    $count = count($file['name']);
    for ($i = 0; $i < $count; $i++) {
        $normalized[] = [
            'name' => $file['name'][$i] ?? '',
            'type' => $file['type'][$i] ?? '',
            'tmp_name' => $file['tmp_name'][$i] ?? '',
            'error' => $file['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $file['size'][$i] ?? 0,
        ];
    }
    return $normalized;
}

function hasValidUpload(array $files): bool {
    foreach ($files as $file) {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $tmpName = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        if (
            $error === UPLOAD_ERR_OK &&
            $tmpName !== '' &&
            is_uploaded_file($tmpName) &&
            $size > 0
        ) {
            return true;
        }
    }
    return false;
}

function filterValidUploads(array $files): array {
    return array_values(array_filter($files, function ($file) {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $tmpName = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        return (
            $error === UPLOAD_ERR_OK &&
            $tmpName !== '' &&
            is_uploaded_file($tmpName) &&
            $size > 0
        );
    }));
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

function isHeicExt(string $ext): bool {
    return in_array($ext, ['heic', 'heif'], true);
}

function sanitizeDocTypeToken(string $docType): string {
    $token = preg_replace('/[^A-Za-z0-9]+/', '', $docType);
    return $token !== '' ? $token : 'Document';
}

function buildAttachmentFileName(string $docType, string $userId, string $ext, int $index = 0): string {
    $base = sanitizeDocTypeToken($docType) . $userId;
    if ($index > 0) {
        $base .= '_' . $index;
    }
    return $base . '.' . strtolower($ext);
}

function toDbWebPath(string $absolutePath): string {
    $absolutePath = str_replace("\\", "/", trim($absolutePath));
    $projectRoot = realpath(__DIR__ . "/../..");
    $marker = "/UnifiedFileAttachment/";
    $markerPos = strpos($absolutePath, $marker);
    if ($markerPos !== false) {
        return ltrim(substr($absolutePath, $markerPos), "/");
    }

    if ($projectRoot) {
        $rootNorm = str_replace("\\", "/", $projectRoot);
        if (strpos($absolutePath, $rootNorm) === 0) {
            $rel = ltrim(substr($absolutePath, strlen($rootNorm)), "/");
            return $rel;
        }
    }

    return ltrim($absolutePath, "/");
}

function moveUploadedFileWithDocName(string $tmpName, string $dir, string $docType, string $userId, string $ext): array {
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new Exception('Invalid upload source.');
    }
    $tmpSize = @filesize($tmpName);
    if ($tmpSize === false || (int)$tmpSize <= 0) {
        throw new Exception('Uploaded file is empty.');
    }
    if ((int)$tmpSize > app_upload_limit_bytes('resident')) {
        throw new Exception(app_upload_limit_error('resident', 'Uploaded file'));
    }

    $index = 0;
    $fileName = buildAttachmentFileName($docType, $userId, $ext, $index);
    $target = rtrim($dir, "/") . "/" . $fileName;

    while (file_exists($target)) {
        $index++;
        $fileName = buildAttachmentFileName($docType, $userId, $ext, $index);
        $target = rtrim($dir, "/") . "/" . $fileName;
    }

    if (!move_uploaded_file($tmpName, $target)) {
        throw new Exception('Failed to upload file.');
    }

    return [
        'file_name' => $fileName,
        'file_path' => toDbWebPath($target),
        'disk_path' => $target,
    ];
}

function getDocumentTypeId(mysqli $conn, string $name): int {
    $q = $conn->prepare("SELECT document_type_id FROM documenttypelookuptbl WHERE LOWER(document_type_name) = LOWER(?) AND document_category = 'EditRequest' LIMIT 1");
    if (!$q) {
        throw new Exception('Prepare failed (getDocumentTypeId): ' . $conn->error);
    }
    $q->bind_param("s", $name);
    $q->execute();
    $res = $q->get_result()->fetch_assoc();
    $q->close();
    if ($res && isset($res['document_type_id'])) {
        return (int)$res['document_type_id'];
    }

    $ins = $conn->prepare("INSERT INTO documenttypelookuptbl (document_type_name, document_category) VALUES (?, 'EditRequest')");
    if (!$ins) {
        throw new Exception('Prepare failed (create document type): ' . $conn->error);
    }
    $ins->bind_param("s", $name);
    if (!$ins->execute()) {
        $ins->close();
        throw new Exception("Failed to create document type: {$name}");
    }
    $newId = (int)$ins->insert_id;
    $ins->close();
    if ($newId <= 0) {
        throw new Exception("Unable to resolve document type: {$name}");
    }
    return $newId;
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
$supportingAddressType = cleanString($payload['supporting_address_type'] ?? '');
$supportingAddressFiles = normalizeFilesArray($_FILES['supporting_address_file'] ?? null);
$allowedSupportingDocTypes = [
    'Contract of Lease',
    'Transfer Certificate of Title',
    'Tax Declaration',
];

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

if (!in_array($supportingAddressType, $allowedSupportingDocTypes, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please select a valid supporting document type for address change.']);
    exit;
}

if (!hasValidUpload($supportingAddressFiles)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Supporting document is required for address change.']);
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

$statusVerifyId = getStatusId($conn, 'PendingReview', 'ResidentDocumentProfiling');
if ($statusVerifyId === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Attachment verification status missing.']);
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

$userFolder = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$userId);
if ($userFolder === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user folder name.']);
    exit;
}
$uploadDir = __DIR__ . "/../../UnifiedFileAttachment/Documents/{$userFolder}/";
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create upload directory.']);
    exit;
}

$allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
$conn->begin_transaction();

try {
    $changesJson = json_encode($changes, JSON_UNESCAPED_SLASHES);
    $requestId = insertResidentEditRequest(
        $conn,
        (string)$residentId,
        (string)$userId,
        'address',
        (int)$pendingStatusId,
        $changesJson
    );

    $docTypeId = getDocumentTypeId($conn, $supportingAddressType);
    $remarks = 'edit_request_supporting:address';
    foreach (filterValidUploads($supportingAddressFiles) as $supportingFile) {
        $ext = strtolower(pathinfo($supportingFile['name'] ?? '', PATHINFO_EXTENSION));
        if (isHeicExt($ext)) {
            throw new Exception('HEIC is not supported. Please upload JPG or PNG.');
        }
        if (!in_array($ext, $allowedExt, true)) {
            throw new Exception('Invalid file type for supporting document.');
        }

        $moved = moveUploadedFileWithDocName($supportingFile['tmp_name'], $uploadDir, $supportingAddressType, $userId, $ext);
        $sourceType = 'ResidentEditRequest';
        $sourceId = (string)$requestId;
        $idNumber = null;
        insertUnifiedFileAttachment($conn, [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'document_type_id' => $docTypeId,
            'file_name' => $moved['file_name'],
            'file_path' => $moved['file_path'],
            'file_type' => $ext,
            'user_id_uploaded_by' => $userId,
            'status_id_verify' => $statusVerifyId,
            'remarks' => $remarks,
            'id_number' => $idNumber,
        ], 'supporting document attachment');
    }

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

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Address change request submitted.']);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage() ?: 'Failed to submit address change request.']);
}
