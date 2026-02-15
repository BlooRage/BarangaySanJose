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

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
    exit;
}

function cleanString($value): string {
    return trim((string)$value);
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

    // Prefer storing a portable, project-relative path (works across local + hosted).
    // Example stored value: "UnifiedFileAttachment/Documents/<user_id>/<file>.pdf"
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
    $index = 0;
    $fileName = buildAttachmentFileName($docType, $userId, $ext, $index);
    $target = rtrim($dir, "/") . "/" . $fileName;

    while (file_exists($target)) {
        $index++;
        $fileName = buildAttachmentFileName($docType, $userId, $ext, $index);
        $target = rtrim($dir, "/") . "/" . $fileName;
    }

    if (!move_uploaded_file($tmpName, $target)) {
        throw new Exception("Failed to upload file.");
    }

    return [
        'file_name' => $fileName,
        'file_path' => toDbWebPath($target),
        'disk_path' => $target
    ];
}

function getDocumentTypeId(mysqli $conn, string $name): int {
    $q = $conn->prepare("SELECT document_type_id FROM documenttypelookuptbl WHERE LOWER(document_type_name) = LOWER(?) AND document_category = 'EditRequest' LIMIT 1");
    if (!$q) throw new Exception("Prepare failed (getDocumentTypeId): " . $conn->error);
    $q->bind_param("s", $name);
    $q->execute();
    $res = $q->get_result()->fetch_assoc();
    $q->close();
    if ($res && isset($res['document_type_id'])) {
        return (int)$res['document_type_id'];
    }

    $ins = $conn->prepare("INSERT INTO documenttypelookuptbl (document_type_name, document_category) VALUES (?, 'EditRequest')");
    if (!$ins) throw new Exception("Prepare failed (create document type): " . $conn->error);
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

try {
    $userId = $_SESSION['user_id'];
    $residentId = getResidentId($conn, $userId);
    if (!$residentId) {
        throw new Exception('Resident profile not found.');
    }

    $pendingStatusId = getStatusId($conn, 'PendingRequest', 'EditRequest');
    if ($pendingStatusId === null) {
        throw new Exception('Edit request status missing.');
    }

    // Block duplicate pending profile requests
    $dup = $conn->prepare("
        SELECT 1
        FROM resident_edit_requesttbl
        WHERE resident_id = ? AND request_type = 'profile' AND status_id = ?
        LIMIT 1
    ");
    $dup->bind_param("si", $residentId, $pendingStatusId);
    $dup->execute();
    $dupExists = $dup->get_result()->num_rows > 0;
    $dup->close();
    if ($dupExists) {
        echo json_encode(['success' => true, 'message' => 'You already have a pending profile edit request.']);
        exit;
    }

    $firstName = cleanString($_POST['first_name'] ?? '');
    $middleName = cleanString($_POST['middle_name'] ?? '');
    $lastName = cleanString($_POST['last_name'] ?? '');
    $suffix = cleanString($_POST['suffix'] ?? '');
    $civilStatus = cleanString($_POST['civil_status'] ?? '');
    $religion = cleanString($_POST['religion'] ?? '');
    $employmentStatus = cleanString($_POST['employment_status'] ?? '');
    $occupation = cleanString($_POST['occupation'] ?? '');
    $sectorMembership = cleanString($_POST['sector_membership'] ?? '');

    $stmt = $conn->prepare("
        SELECT firstname, middlename, lastname, suffix, civil_status, religion, occupation, occupation_detail, sector_membership
        FROM residentinformationtbl
        WHERE resident_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $residentId);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$current) {
        throw new Exception('Resident profile not found.');
    }

    $changes = [];
    if ($firstName !== '' && $firstName !== (string)$current['firstname']) $changes['firstname'] = $firstName;
    if ($middleName !== (string)$current['middlename']) $changes['middlename'] = $middleName;
    if ($lastName !== '' && $lastName !== (string)$current['lastname']) $changes['lastname'] = $lastName;
    if ($suffix !== (string)$current['suffix']) $changes['suffix'] = $suffix;
    if ($civilStatus !== '' && $civilStatus !== (string)$current['civil_status']) $changes['civil_status'] = $civilStatus;
    if ($religion !== '' && $religion !== (string)$current['religion']) $changes['religion'] = $religion;
    if ($employmentStatus !== '') {
        $changes['occupation'] = $employmentStatus === 'Employed' ? 1 : 0;
        if ($employmentStatus === 'Employed' && $occupation !== '') {
            $changes['occupation_detail'] = $occupation;
        }
    }
    if ($sectorMembership !== '' && $sectorMembership !== (string)$current['sector_membership']) {
        $changes['sector_membership'] = $sectorMembership;
    }

    if (!$changes) {
        echo json_encode(['success' => true, 'message' => 'No changes detected.']);
        exit;
    }

    $nameChanged = isset($changes['firstname']) || isset($changes['middlename']) || isset($changes['lastname']) || isset($changes['suffix']);
    $civilChanged = isset($changes['civil_status']);

    $nameIdType = cleanString($_POST['name_id_type'] ?? '');
    $nameIdFile = $_FILES['name_id_file'] ?? null;
    $civilFile = $_FILES['civil_status_file'] ?? null;

    $allowedIdTypes = [
        "Passport",
        "Driver's License",
        "PhilHealth ID",
        "Voter's ID",
        "National ID",
        "Barangay ID",
        "PRC ID"
    ];

    if ($nameChanged) {
        if ($nameIdType === '' || !in_array($nameIdType, $allowedIdTypes, true)) {
            throw new Exception('Please select a valid ID type for name change.');
        }
        if (!$nameIdFile || ($nameIdFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new Exception('Valid ID photo is required for name change.');
        }
    }

    if ($civilChanged && in_array($civilStatus, ['Married', 'Widowed'], true)) {
        if (!$civilFile || ($civilFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new Exception('Supporting document is required for civil status change.');
        }
    }

    $statusVerifyId = getStatusId($conn, "PendingReview", "ResidentDocumentProfiling");
    if ($statusVerifyId === null) {
        throw new Exception('Document status missing.');
    }

    $conn->begin_transaction();

    $stmt = $conn->prepare("
        INSERT INTO resident_edit_requesttbl
            (resident_id, user_id, request_type, status_id, requested_changes)
        VALUES (?, ?, 'profile', ?, ?)
    ");
    $changesJson = json_encode($changes, JSON_UNESCAPED_SLASHES);
    $stmt->bind_param("ssis", $residentId, $userId, $pendingStatusId, $changesJson);
    if (!$stmt->execute()) {
        throw new Exception('Failed to submit profile edit request.');
    }
    $requestId = (int)$stmt->insert_id;
    $stmt->close();

    // Store files under a folder named by user_id (not resident_id).
    $userFolder = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$userId);
    if ($userFolder === '') {
        throw new Exception('Invalid user folder name.');
    }
    $uploadDir = __DIR__ . "/../../UnifiedFileAttachment/Documents/{$userFolder}/";
    // 0777 so the local dev user can manage/delete uploaded folders even if PHP runs as daemon (XAMPP).
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
        throw new Exception('Failed to create upload directory.');
    }

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    if ($nameChanged && $nameIdFile) {
        $ext = strtolower(pathinfo($nameIdFile['name'] ?? '', PATHINFO_EXTENSION));
        if (isHeicExt($ext)) {
            throw new Exception('HEIC is not supported. Please upload JPG or PNG.');
        }
        if (!in_array($ext, $allowedExt, true)) {
            throw new Exception('Invalid file type for ID photo.');
        }
        $moved = moveUploadedFileWithDocName($nameIdFile['tmp_name'], $uploadDir, $nameIdType, $userId, $ext);
        $docTypeId = getDocumentTypeId($conn, $nameIdType);
        $remarks = "edit_request_name_id";
        $ins = $conn->prepare("
            INSERT INTO unifiedfileattachmenttbl
                (source_type, source_id, document_type_id, file_name, file_path, file_type, user_id_uploaded_by, status_id_verify, remarks, id_number)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $sourceType = "ResidentEditRequest";
        $sourceId = (string)$requestId;
        $idNumber = null;
        $ins->bind_param(
            "ssissssiss",
            $sourceType,
            $sourceId,
            $docTypeId,
            $moved['file_name'],
            $moved['file_path'],
            $ext,
            $userId,
            $statusVerifyId,
            $remarks,
            $idNumber
        );
        if (!$ins->execute()) {
            throw new Exception('Failed to save ID attachment.');
        }
        $ins->close();
    }

    if ($civilChanged && $civilFile && in_array($civilStatus, ['Married', 'Widowed'], true)) {
        $ext = strtolower(pathinfo($civilFile['name'] ?? '', PATHINFO_EXTENSION));
        if (isHeicExt($ext)) {
            throw new Exception('HEIC is not supported. Please upload JPG or PNG.');
        }
        if (!in_array($ext, $allowedExt, true)) {
            throw new Exception('Invalid file type for civil status document.');
        }
        $docTypeName = $civilStatus === 'Married' ? 'Marriage Certificate' : 'Death Certificate of Spouse';
        $moved = moveUploadedFileWithDocName($civilFile['tmp_name'], $uploadDir, $docTypeName, $userId, $ext);
        $docTypeId = getDocumentTypeId($conn, $docTypeName);
        $remarks = "edit_request_civil_status";
        $ins = $conn->prepare("
            INSERT INTO unifiedfileattachmenttbl
                (source_type, source_id, document_type_id, file_name, file_path, file_type, user_id_uploaded_by, status_id_verify, remarks, id_number)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $sourceType = "ResidentEditRequest";
        $sourceId = (string)$requestId;
        $idNumber = null;
        $ins->bind_param(
            "ssissssiss",
            $sourceType,
            $sourceId,
            $docTypeId,
            $moved['file_name'],
            $moved['file_path'],
            $ext,
            $userId,
            $statusVerifyId,
            $remarks,
            $idNumber
        );
        if (!$ins->execute()) {
            throw new Exception('Failed to save civil status attachment.');
        }
        $ins->close();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Profile edit request submitted.']);
} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
