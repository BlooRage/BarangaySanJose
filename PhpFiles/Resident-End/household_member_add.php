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
require_once __DIR__ . '/../General/uploadLimits.php';
require_once __DIR__ . '/../General/householdMemberVerification.php';
require_once __DIR__ . '/householdHeadVerification.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$payload = $_POST;
if ($payload === [] && isset($_SERVER['CONTENT_TYPE']) && stripos((string)$_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $decoded = json_decode(file_get_contents('php://input'), true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

$lastName = trim((string)($payload['last_name'] ?? ''));
$firstName = trim((string)($payload['first_name'] ?? ''));
$middleName = trim((string)($payload['middle_name'] ?? ''));
$suffix = trim((string)($payload['suffix'] ?? ''));
$birthdate = trim((string)($payload['birthdate'] ?? ''));

if ($lastName === '' || $firstName === '' || $birthdate === '') {
    echo json_encode(['success' => false, 'message' => 'Last name, first name, and birthdate are required.']);
    exit;
}

$dob = DateTime::createFromFormat('Y-m-d', $birthdate);
if (!$dob || $dob->format('Y-m-d') !== $birthdate) {
    echo json_encode(['success' => false, 'message' => 'Invalid birthdate format.']);
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
    exit;
}

// Resolve resident id + ensure head of family
$residentId = '';
$isHead = false;
$stmt = $conn->prepare("
    SELECT resident_id, head_of_family
    FROM residentinformationtbl
    WHERE user_id = ?
    LIMIT 1
");
$stmt->bind_param("s", $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($residentId, $headFlag);
if ($stmt->fetch()) {
    $isHead = ((int)$headFlag) === 1;
}
$stmt->close();

if ($residentId === '' || !$isHead) {
    echo json_encode(['success' => false, 'message' => 'Only the head can add members.']);
    exit;
}

$headVerification = hhv_get_resident_head_verification($conn, $residentId);
if (!$headVerification['can_manage_members']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => $headVerification['message'] ?: 'Head of family verification is required before adding members.']);
    exit;
}

$middleName = $middleName !== '' ? $middleName : null;
$suffix = $suffix !== '' ? $suffix : null;

$birthCertificate = $_FILES['birth_certificate'] ?? null;
if (!is_array($birthCertificate)) {
    echo json_encode(['success' => false, 'message' => 'Birth certificate upload is required.']);
    exit;
}

$uploadError = app_upload_validate_file($birthCertificate, 'resident', 'Birth certificate', true);
if ($uploadError !== null) {
    echo json_encode(['success' => false, 'message' => $uploadError]);
    exit;
}

$tmpName = trim((string)($birthCertificate['tmp_name'] ?? ''));
if ($tmpName === '' || !is_uploaded_file($tmpName)) {
    echo json_encode(['success' => false, 'message' => 'Invalid birth certificate upload.']);
    exit;
}

$originalName = trim((string)($birthCertificate['name'] ?? ''));
$extension = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
$allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
if (!in_array($extension, $allowedExtensions, true)) {
    echo json_encode(['success' => false, 'message' => 'Birth certificate must be a PDF, JPG, JPEG, PNG, or WEBP file.']);
    exit;
}

// Prevent duplicates under the same head
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
$dup->bind_param("ssssss", $residentId, $lastName, $firstName, $middleName, $suffix, $birthdate);
$dup->execute();
$dup->store_result();
if ($dup->num_rows > 0) {
    $dup->close();
    echo json_encode(['success' => false, 'message' => 'Member already exists in your household.']);
    exit;
}
$dup->close();

hmv_ensure_request_table($conn);

$pendingDup = $conn->prepare("
    SELECT request_id
    FROM householdmemberverificationtbl
    WHERE fam_head_id = ?
      AND last_name = ?
      AND first_name = ?
      AND (middle_name <=> ?)
      AND (suffix <=> ?)
      AND birthdate = ?
      AND status = 'PendingReview'
    LIMIT 1
");
if ($pendingDup) {
    $pendingDup->bind_param("ssssss", $residentId, $lastName, $firstName, $middleName, $suffix, $birthdate);
    $pendingDup->execute();
    $pendingDup->store_result();
    if ($pendingDup->num_rows > 0) {
        $pendingDup->close();
        echo json_encode(['success' => false, 'message' => 'This household member already has a pending verification request.']);
        exit;
    }
    $pendingDup->close();
}

$transactionStarted = false;
try {
    $pendingReviewStatusId = hmv_get_status_id($conn, 'PendingReview', 'ResidentDocumentProfiling');
    if ($pendingReviewStatusId === null) {
        throw new RuntimeException('Document verification status is missing.');
    }
    $documentTypeId = hmv_get_document_type_id($conn, 'Birth Certificate', 'HouseholdMemberVerification');
    $requestId = hmv_generate_request_id($conn);
    if ($requestId <= 0) {
        throw new RuntimeException('Failed to generate household member verification request.');
    }

    $uploadDir = __DIR__ . "/../../UnifiedFileAttachment/Documents/" . (string)$_SESSION['user_id'] . "/HouseholdMemberVerification/";
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
        throw new RuntimeException('Failed to prepare birth certificate upload directory.');
    }

    $safeResidentId = preg_replace('/[^A-Za-z0-9_-]/', '', $residentId);
    $targetName = 'BirthCertificate_' . $safeResidentId . '_' . $requestId . '.' . $extension;
    $targetPath = rtrim($uploadDir, "/\\") . DIRECTORY_SEPARATOR . $targetName;
    if (!move_uploaded_file($tmpName, $targetPath)) {
        throw new RuntimeException('Failed to upload birth certificate.');
    }

    $conn->begin_transaction();
    $transactionStarted = true;

    $insertRequest = $conn->prepare("
        INSERT INTO householdmemberverificationtbl
            (request_id, fam_head_id, submitted_by_user_id, last_name, first_name, middle_name, suffix, birthdate, status)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, 'PendingReview')
    ");
    if (!$insertRequest) {
        throw new RuntimeException('Failed to prepare household member verification request.');
    }
    $currentUserId = (string)$_SESSION['user_id'];
    $insertRequest->bind_param("isssssss", $requestId, $residentId, $currentUserId, $lastName, $firstName, $middleName, $suffix, $birthdate);
    if (!$insertRequest->execute()) {
        $error = $insertRequest->error;
        $insertRequest->close();
        throw new RuntimeException('Failed to save household member verification request. ' . $error);
    }
    $insertRequest->close();

    $attachmentId = insertUnifiedFileAttachment($conn, [
        'source_type' => 'HouseholdMemberVerification',
        'source_id' => (string)$requestId,
        'document_type_id' => $documentTypeId,
        'file_name' => $targetName,
        'file_path' => hmv_to_db_web_path($targetPath),
        'file_type' => $extension,
        'user_id_uploaded_by' => $currentUserId,
        'status_id_verify' => $pendingReviewStatusId,
        'remarks' => 'household_member_birth_certificate',
        'id_number' => null,
    ], 'household member birth certificate');

    $updateRequest = $conn->prepare("
        UPDATE householdmemberverificationtbl
        SET attachment_id = ?
        WHERE request_id = ?
        LIMIT 1
    ");
    if (!$updateRequest) {
        throw new RuntimeException('Failed to finalize household member verification request.');
    }
    $updateRequest->bind_param("ii", $attachmentId, $requestId);
    if (!$updateRequest->execute()) {
        $error = $updateRequest->error;
        $updateRequest->close();
        throw new RuntimeException('Failed to link household member birth certificate. ' . $error);
    }
    $updateRequest->close();

    $conn->commit();
    $transactionStarted = false;
} catch (RuntimeException $e) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Failed to add member.']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Household member verification request submitted. Please wait for admin review.',
]);
?>
