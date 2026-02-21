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
require_once __DIR__ . '/../General/residentTransaction.php';

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

function isValidNameToken(string $value, int $minLetters = 2, int $maxLen = 30): bool {
    if ($value === '' || strlen($value) > $maxLen) return false;
    if (!preg_match('/^[A-Za-z ]+$/', $value)) return false;
    $letters = preg_replace('/[^A-Za-z]/', '', $value);
    return strlen((string)$letters) >= $minLetters;
}

function parseSectorCsv(string $csv): array {
    if ($csv === '') {
        return [];
    }
    $parts = explode(',', $csv);
    $items = [];
    foreach ($parts as $part) {
        $value = trim($part);
        if ($value !== '') {
            $items[] = $value;
        }
    }
    return array_values(array_unique($items));
}

function normalizeSector(string $sector): string {
    return strtolower(preg_replace('/\s+/', '', trim($sector)));
}

function sectorKeyFromLabel(string $label): string {
    $normalized = strtolower(trim($label));
    $normalized = preg_replace('/[^a-z]/', '', $normalized);
    $map = [
        'pwd' => 'PWD',
        'seniorcitizen' => 'SeniorCitizen',
        'student' => 'Student',
        'indigenouspeople' => 'IndigenousPeople',
        'indigenousperson' => 'IndigenousPeople',
        'singleparent' => 'SingleParent'
    ];
    return $map[$normalized] ?? '';
}

function sectorLabelFromKey(string $sectorKey): string {
    $normalized = strtolower(trim($sectorKey));
    $normalized = preg_replace('/[^a-z]/', '', $normalized);
    $map = [
        'pwd' => 'PWD',
        'seniorcitizen' => 'Senior Citizen',
        'student' => 'Student',
        'indigenouspeople' => 'Indigenous People',
        'singleparent' => 'Single Parent'
    ];
    return $map[$normalized] ?? trim($sectorKey);
}

function upsertSectorMembershipStatusFromUpload(
    mysqli $conn,
    string $residentId,
    string $sectorKey,
    int $sectorStatusId,
    int $latestAttachmentId
): void {
    $stmt = $conn->prepare("
        INSERT INTO residentsectormembershiptbl
            (resident_id, sector_key, sector_status_id, latest_attachment_id, remarks, upload_timestamp, last_update_user_id)
        VALUES
            (?, ?, ?, ?, NULL, NOW(), NULL)
        ON DUPLICATE KEY UPDATE
            sector_status_id = VALUES(sector_status_id),
            latest_attachment_id = VALUES(latest_attachment_id),
            remarks = NULL,
            upload_timestamp = VALUES(upload_timestamp),
            last_update_user_id = NULL,
            updated_at = CURRENT_TIMESTAMP
    ");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param("ssii", $residentId, $sectorKey, $sectorStatusId, $latestAttachmentId);
    $stmt->execute();
    $stmt->close();
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
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new Exception("Invalid upload source.");
    }
    $tmpSize = @filesize($tmpName);
    if ($tmpSize === false || (int)$tmpSize <= 0) {
        throw new Exception("Uploaded file is empty.");
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
        throw new Exception("Failed to upload file.");
    }
    $movedSize = @filesize($target);
    if ($movedSize === false || (int)$movedSize <= 0) {
        @unlink($target);
        throw new Exception("Uploaded file is empty.");
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

    $firstName = cleanString($_POST['first_name'] ?? '');
    $middleName = cleanString($_POST['middle_name'] ?? '');
    $lastName = cleanString($_POST['last_name'] ?? '');
    $suffix = cleanString($_POST['suffix'] ?? '');
    $civilStatus = cleanString($_POST['civil_status'] ?? '');
    $newSurname = cleanString($_POST['new_surname'] ?? '');
    $religion = cleanString($_POST['religion'] ?? '');
    $voterStatusRaw = cleanString($_POST['voter_status'] ?? '');
    $employmentStatus = cleanString($_POST['employment_status'] ?? '');
    $occupation = cleanString($_POST['occupation'] ?? '');
    $sectorMembership = cleanString($_POST['sector_membership'] ?? '');
    $studentStopped = cleanString($_POST['student_stopped'] ?? '0') === '1';

    $stmt = $conn->prepare("
        SELECT firstname, middlename, lastname, suffix, sex, civil_status, religion, occupation, occupation_detail, sector_membership
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
    $civilDrivenSurnameChange = false;
    if ($firstName !== '' && $firstName !== (string)$current['firstname']) $changes['firstname'] = $firstName;
    if ($middleName !== (string)$current['middlename']) $changes['middlename'] = $middleName;
    if ($lastName !== '' && $lastName !== (string)$current['lastname']) $changes['lastname'] = $lastName;
    if ($suffix !== (string)$current['suffix']) $changes['suffix'] = $suffix;
    if ($civilStatus !== '' && $civilStatus !== (string)$current['civil_status']) $changes['civil_status'] = $civilStatus;
    $isFemaleResident = strcasecmp((string)($current['sex'] ?? ''), 'Female') === 0;
    if ($isFemaleResident && isset($changes['civil_status']) && in_array($civilStatus, ['Married', 'Divorced'], true)) {
        if (!isValidNameToken($newSurname, 2, 30)) {
            throw new Exception('New surname is required and must be valid for this civil status change.');
        }
        if ($newSurname !== (string)$current['lastname']) {
            $changes['lastname'] = $newSurname;
            $civilDrivenSurnameChange = true;
        }
    }
    if ($religion !== '' && $religion !== (string)$current['religion']) $changes['religion'] = $religion;
    if ($voterStatusRaw !== '') {
        $voterNorm = strtolower($voterStatusRaw);
        $voterValue = null;
        if (in_array($voterNorm, ['registered', 'registered voter', 'yes', '1'], true)) {
            $voterValue = 1;
        } elseif (in_array($voterNorm, ['not registered', 'no', '0'], true)) {
            $voterValue = 0;
        }
        if ($voterValue !== null && (int)($current['voter_status'] ?? 0) !== $voterValue) {
            $changes['voter_status'] = $voterValue;
        }
    }
    if ($employmentStatus !== '') {
        $currentEmployment = ((int)($current['occupation'] ?? 0)) === 1 ? 'Employed' : 'Unemployed';
        $employmentChanged = $employmentStatus !== $currentEmployment;
        if ($employmentChanged) {
            $changes['occupation'] = $employmentStatus === 'Employed' ? 1 : 0;
        }
        if ($employmentStatus === 'Employed' && $occupation !== '' && $occupation !== (string)($current['occupation_detail'] ?? '')) {
            $changes['occupation_detail'] = $occupation;
        }
    }
    $currentSectors = parseSectorCsv((string)($current['sector_membership'] ?? ''));
    $requestedSectors = parseSectorCsv($sectorMembership);
    $currentByNorm = [];
    foreach ($currentSectors as $sector) {
        $currentByNorm[normalizeSector($sector)] = $sector;
    }
    $requestedByNorm = [];
    foreach ($requestedSectors as $sector) {
        $requestedByNorm[normalizeSector($sector)] = $sector;
    }
    $removedSectorLabels = [];
    foreach ($currentByNorm as $norm => $label) {
        if (!isset($requestedByNorm[$norm])) {
            $removedSectorLabels[] = $label;
        }
    }
    if (!empty($removedSectorLabels)) {
        $removedNonStudent = [];
        $removedStudent = false;
        foreach ($removedSectorLabels as $removed) {
            if (normalizeSector($removed) === 'student') {
                $removedStudent = true;
                continue;
            }
            $removedNonStudent[] = $removed;
        }
        if (!empty($removedNonStudent)) {
            throw new Exception('Only Student sector membership can be removed. Other sector memberships cannot be unticked.');
        }
    }

    $sectorChanged = !empty($removedSectorLabels);
    $addedSectorKeys = [];
    foreach ($requestedByNorm as $norm => $label) {
        if (!isset($currentByNorm[$norm])) {
            $key = sectorKeyFromLabel($label);
            if ($key !== '') {
                $addedSectorKeys[] = $key;
            }
        }
    }
    $addedSectorKeys = array_values(array_unique($addedSectorKeys));
    if (!empty($addedSectorKeys)) {
        $sectorChanged = true;
    }

    $removedStudent = false;
    foreach ($removedSectorLabels as $removed) {
        if (normalizeSector($removed) === 'student') {
            $removedStudent = true;
            break;
        }
    }

    $profileChanges = $changes;
    unset($profileChanges['sector_membership']);

    if (!$profileChanges && !$sectorChanged) {
        echo json_encode(['success' => true, 'message' => 'No changes detected.']);
        exit;
    }

    // Block duplicate pending profile requests only when profile fields are being changed.
    if ($profileChanges) {
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
    }

    $nameChanged = isset($profileChanges['firstname'])
        || isset($profileChanges['middlename'])
        || isset($profileChanges['suffix'])
        || (isset($profileChanges['lastname']) && !$civilDrivenSurnameChange);
    $civilChanged = isset($profileChanges['civil_status']);

    $nameIdType = cleanString($_POST['name_id_type'] ?? '');
    $religionDocType = cleanString($_POST['supporting_religion_type'] ?? '');
    $voterDocType = cleanString($_POST['supporting_voter_type'] ?? '');
    $employmentDocType = cleanString($_POST['supporting_employment_type'] ?? '');
    $sectorDocType = cleanString($_POST['supporting_sector_type'] ?? '');
    $nameIdFiles = normalizeFilesArray($_FILES['name_id_file'] ?? null);
    $religionFiles = normalizeFilesArray($_FILES['supporting_religion_file'] ?? null);
    $voterFiles = normalizeFilesArray($_FILES['supporting_voter_file'] ?? null);
    $employmentFiles = normalizeFilesArray($_FILES['supporting_employment_file'] ?? null);
    $sectorSupportFiles = normalizeFilesArray($_FILES['supporting_sector_file'] ?? null);
    $civilFiles = normalizeFilesArray($_FILES['civil_status_file'] ?? null);
    $studentStatusFiles = normalizeFilesArray($_FILES['student_status_file'] ?? null);

    $allowedIdTypes = [
        "Passport",
        "Driver's License",
        "PhilHealth ID",
        "Voter's ID",
        "National ID",
        "Barangay ID",
        "PRC ID"
    ];
    $allowedSupportDocTypes = [
        "Certificate of Employment",
        "Proof of Income",
        "Voter Certification",
        "Proof of Residency",
        "Barangay Clearance",
        "Affidavit",
        "Other Supporting Document"
    ];

    if ($nameChanged) {
        if ($nameIdType === '' || !in_array($nameIdType, $allowedIdTypes, true)) {
            throw new Exception('Please select a valid ID type for name change.');
        }
        if (!hasValidUpload($nameIdFiles)) {
            throw new Exception('Valid ID photo is required for name change.');
        }
    }

    if (!$nameChanged && $civilChanged && in_array($civilStatus, ['Married', 'Widowed'], true)) {
        if (!hasValidUpload($civilFiles)) {
            throw new Exception('Supporting document is required for civil status change.');
        }
    }

    $changeKeys = array_keys($profileChanges);
    $requiresStudentUntickProof = !$nameChanged
        && !($civilChanged && in_array($civilStatus, ['Married', 'Widowed'], true))
        && $removedStudent;
    if ($requiresStudentUntickProof) {
        $hasStudentFile = hasValidUpload($studentStatusFiles);
        if (!$hasStudentFile && !$studentStopped) {
            throw new Exception('Please upload diploma/proof or confirm that you stopped studying.');
        }
    }
    $requiresReligionDoc = isset($profileChanges['religion']);
    $requiresVoterDoc = isset($profileChanges['voter_status']);
    $requiresEmploymentDoc = isset($profileChanges['occupation']) || isset($profileChanges['occupation_detail']);
    if (isset($profileChanges['occupation'])) {
        $employmentToUnemployed = (int)$profileChanges['occupation'] === 0;
        if ($employmentToUnemployed && !isset($profileChanges['occupation_detail'])) {
            $requiresEmploymentDoc = false;
        }
    }
    if ($requiresReligionDoc) {
        if (!hasValidUpload($religionFiles)) {
            throw new Exception('Supporting document is required for religion change.');
        }
        if ($religionDocType === '' || !in_array($religionDocType, $allowedSupportDocTypes, true)) {
            throw new Exception('Please select a valid supporting document type for religion change.');
        }
    }
    if ($requiresVoterDoc) {
        if (!hasValidUpload($voterFiles)) {
            throw new Exception('Supporting document is required for voter status change.');
        }
        if ($voterDocType === '' || !in_array($voterDocType, $allowedSupportDocTypes, true)) {
            throw new Exception('Please select a valid supporting document type for voter status change.');
        }
    }
    if ($requiresEmploymentDoc) {
        if (!hasValidUpload($employmentFiles)) {
            throw new Exception('Supporting document is required for employment status change.');
        }
        if ($employmentDocType === '' || !in_array($employmentDocType, $allowedSupportDocTypes, true)) {
            throw new Exception('Please select a valid supporting document type for employment status change.');
        }
    }
    if (hasValidUpload($sectorSupportFiles)) {
        if ($sectorDocType === '' || !in_array($sectorDocType, $allowedSupportDocTypes, true)) {
            throw new Exception('Please select a valid supporting document type for sector membership change.');
        }
    }

    $statusVerifyId = getStatusId($conn, "PendingReview", "ResidentDocumentProfiling");
    if ($statusVerifyId === null) {
        throw new Exception('Document status missing.');
    }

    $conn->begin_transaction();

    $requestId = 0;
    if ($profileChanges) {
        $stmt = $conn->prepare("
            INSERT INTO resident_edit_requesttbl
                (resident_id, user_id, request_type, status_id, requested_changes)
            VALUES (?, ?, 'profile', ?, ?)
        ");
        $changesJson = json_encode($profileChanges, JSON_UNESCAPED_SLASHES);
        $stmt->bind_param("ssis", $residentId, $userId, $pendingStatusId, $changesJson);
        if (!$stmt->execute()) {
            throw new Exception('Failed to submit profile edit request.');
        }
        $requestId = (int)$stmt->insert_id;
        $stmt->close();
    }

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

    if ($requestId > 0 && $nameChanged && $nameIdFiles) {
        $docTypeId = getDocumentTypeId($conn, $nameIdType);
        $remarks = "edit_request_name_id";
        foreach (filterValidUploads($nameIdFiles) as $nameIdFile) {
            $ext = strtolower(pathinfo($nameIdFile['name'] ?? '', PATHINFO_EXTENSION));
            if (isHeicExt($ext)) {
                throw new Exception('HEIC is not supported. Please upload JPG or PNG.');
            }
            if (!in_array($ext, $allowedExt, true)) {
                throw new Exception('Invalid file type for ID photo.');
            }
            $moved = moveUploadedFileWithDocName($nameIdFile['tmp_name'], $uploadDir, $nameIdType, $userId, $ext);
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
    }

    if ($requestId > 0 && $civilChanged && $civilFiles && in_array($civilStatus, ['Married', 'Widowed'], true)) {
        $docTypeName = $civilStatus === 'Married' ? 'Marriage Certificate' : 'Death Certificate of Spouse';
        $docTypeId = getDocumentTypeId($conn, $docTypeName);
        $remarks = "edit_request_civil_status";
        foreach (filterValidUploads($civilFiles) as $civilFile) {
            $ext = strtolower(pathinfo($civilFile['name'] ?? '', PATHINFO_EXTENSION));
            if (isHeicExt($ext)) {
                throw new Exception('HEIC is not supported. Please upload JPG or PNG.');
            }
            if (!in_array($ext, $allowedExt, true)) {
                throw new Exception('Invalid file type for civil status document.');
            }
            $moved = moveUploadedFileWithDocName($civilFile['tmp_name'], $uploadDir, $docTypeName, $userId, $ext);
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
    }

    if ($requestId > 0 && hasValidUpload($religionFiles)) {
        $docTypeName = $religionDocType;
        $docTypeId = getDocumentTypeId($conn, $docTypeName);
        $remarks = "edit_request_supporting:religion";
        foreach (filterValidUploads($religionFiles) as $supportingFile) {
            $ext = strtolower(pathinfo($supportingFile['name'] ?? '', PATHINFO_EXTENSION));
            if (isHeicExt($ext)) {
                throw new Exception('HEIC is not supported. Please upload JPG or PNG.');
            }
            if (!in_array($ext, $allowedExt, true)) {
                throw new Exception('Invalid file type for supporting document.');
            }
            $moved = moveUploadedFileWithDocName($supportingFile['tmp_name'], $uploadDir, $docTypeName, $userId, $ext);
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
                throw new Exception('Failed to save supporting document attachment.');
            }
            $ins->close();
        }
    }

    if ($requestId > 0 && hasValidUpload($voterFiles)) {
        $docTypeName = $voterDocType;
        $docTypeId = getDocumentTypeId($conn, $docTypeName);
        $remarks = "edit_request_supporting:voter_status";
        foreach (filterValidUploads($voterFiles) as $supportingFile) {
            $ext = strtolower(pathinfo($supportingFile['name'] ?? '', PATHINFO_EXTENSION));
            if (isHeicExt($ext)) {
                throw new Exception('HEIC is not supported. Please upload JPG or PNG.');
            }
            if (!in_array($ext, $allowedExt, true)) {
                throw new Exception('Invalid file type for supporting document.');
            }
            $moved = moveUploadedFileWithDocName($supportingFile['tmp_name'], $uploadDir, $docTypeName, $userId, $ext);
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
                throw new Exception('Failed to save supporting document attachment.');
            }
            $ins->close();
        }
    }

    if ($requestId > 0 && hasValidUpload($employmentFiles)) {
        $docTypeName = $employmentDocType;
        $docTypeId = getDocumentTypeId($conn, $docTypeName);
        $remarks = "edit_request_supporting:employment_status";
        foreach (filterValidUploads($employmentFiles) as $supportingFile) {
            $ext = strtolower(pathinfo($supportingFile['name'] ?? '', PATHINFO_EXTENSION));
            if (isHeicExt($ext)) {
                throw new Exception('HEIC is not supported. Please upload JPG or PNG.');
            }
            if (!in_array($ext, $allowedExt, true)) {
                throw new Exception('Invalid file type for supporting document.');
            }
            $moved = moveUploadedFileWithDocName($supportingFile['tmp_name'], $uploadDir, $docTypeName, $userId, $ext);
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
                throw new Exception('Failed to save supporting document attachment.');
            }
            $ins->close();
        }
    }


    if ($requestId > 0 && !$sectorChanged && hasValidUpload($studentStatusFiles)) {
        $docTypeName = 'Diploma';
        $docTypeId = getDocumentTypeId($conn, $docTypeName);
        $remarks = "edit_request_student_status";
        foreach (filterValidUploads($studentStatusFiles) as $studentStatusFile) {
            $ext = strtolower(pathinfo($studentStatusFile['name'] ?? '', PATHINFO_EXTENSION));
            if (isHeicExt($ext)) {
                throw new Exception('HEIC is not supported. Please upload JPG or PNG.');
            }
            if (!in_array($ext, $allowedExt, true)) {
                throw new Exception('Invalid file type for student proof document.');
            }
            $moved = moveUploadedFileWithDocName($studentStatusFile['tmp_name'], $uploadDir, $docTypeName, $userId, $ext);
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
                throw new Exception('Failed to save student proof attachment.');
            }
            $ins->close();
        }
    }

    if ($sectorChanged) {
        $sectorUploadFiles = [];
        $sectorDocType = '';
        if (hasValidUpload($studentStatusFiles)) {
            $sectorUploadFiles = filterValidUploads($studentStatusFiles);
            $sectorDocType = 'Diploma';
        } elseif (hasValidUpload($sectorSupportFiles)) {
            $sectorUploadFiles = filterValidUploads($sectorSupportFiles);
            $sectorDocType = $sectorDocType !== '' ? $sectorDocType : 'Other Supporting Document';
        }

        if (!$sectorUploadFiles) {
            throw new Exception('Sector membership request must include a supporting document for verification.');
        }

        $attachmentIds = [];
        $attachmentIdsBySectorKey = [];
        $markers = [];
        foreach ($addedSectorKeys as $sectorKey) {
            $markers[] = 'sector:' . $sectorKey;
        }
        if ($removedStudent) {
            $markers[] = 'sector:Student; action=remove';
        }
        $markers = array_values(array_unique($markers));
        if (!$markers) {
            throw new Exception('No sector membership changes detected.');
        }

        $docTypeId = getDocumentTypeId($conn, $sectorDocType);
        foreach ($sectorUploadFiles as $sectorUploadFile) {
            $ext = strtolower(pathinfo($sectorUploadFile['name'] ?? '', PATHINFO_EXTENSION));
            if (isHeicExt($ext)) {
                throw new Exception('HEIC is not supported. Please upload JPG or PNG.');
            }
            if (!in_array($ext, $allowedExt, true)) {
                throw new Exception('Invalid file type for sector membership proof.');
            }

            $moved = moveUploadedFileWithDocName($sectorUploadFile['tmp_name'], $uploadDir, $sectorDocType, $userId, $ext);
            foreach ($markers as $marker) {
                $ins = $conn->prepare("
                    INSERT INTO unifiedfileattachmenttbl
                        (source_type, source_id, document_type_id, file_name, file_path, file_type, user_id_uploaded_by, status_id_verify, remarks, id_number)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $sourceType = "ResidentProfiling";
                $sourceId = (string)$residentId;
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
                    $marker,
                    $idNumber
                );
                if (!$ins->execute()) {
                    throw new Exception('Failed to save sector membership attachment.');
                }
                $newAttachmentId = (int)$ins->insert_id;
                $ins->close();
                if ($newAttachmentId > 0) {
                    $attachmentIds[] = $newAttachmentId;
                    $markerBase = strtolower(trim((string)explode(';', $marker, 2)[0]));
                    if (strpos($markerBase, 'sector:') === 0) {
                        $sectorKey = trim((string)substr($markerBase, strlen('sector:')));
                        if ($sectorKey !== '') {
                            if (!isset($attachmentIdsBySectorKey[$sectorKey])) {
                                $attachmentIdsBySectorKey[$sectorKey] = [];
                            }
                            $attachmentIdsBySectorKey[$sectorKey][] = $newAttachmentId;
                            upsertSectorMembershipStatusFromUpload(
                                $conn,
                                (string)$residentId,
                                (string)$sectorKey,
                                (int)$statusVerifyId,
                                $newAttachmentId
                            );
                        }
                    }
                }
            }
        }

        $sectorSourceId = !empty($attachmentIds) ? (string)$attachmentIds[0] : (string)$residentId;
        $changedSectorKeys = array_values(array_unique(array_merge(
            array_values($addedSectorKeys),
            $removedStudent ? ['Student'] : []
        )));
        foreach ($changedSectorKeys as $changedSectorKey) {
            $sectorIds = isset($attachmentIdsBySectorKey[$changedSectorKey])
                ? $attachmentIdsBySectorKey[$changedSectorKey]
                : $attachmentIds;
            $sectorIds = array_values(array_unique(array_map('intval', (array)$sectorIds)));
            $sectorSourceIdCurrent = !empty($sectorIds) ? (string)$sectorIds[0] : $sectorSourceId;
            $isRemoval = ($changedSectorKey === 'Student' && $removedStudent);
            $sectorLabel = sectorLabelFromKey($changedSectorKey);

            createResidentTransaction(
                $conn,
                (string)$userId,
                (string)$userId,
                'SECTOR_MEMBERSHIP',
                $sectorSourceIdCurrent,
                'SECTOR_MEMBERSHIP_VERIFICATION',
                'Sector Membership Verification',
                (int)$statusVerifyId,
                'Resident submitted a sector membership request for verification.',
                [
                    'sectors' => [$sectorLabel],
                    'sector_key' => $changedSectorKey,
                    'action' => $isRemoval ? 'remove' : 'add',
                    'added_sectors' => $isRemoval ? [] : [$changedSectorKey],
                    'removed_student' => $isRemoval ? 1 : 0,
                    'attachment_ids' => $sectorIds
                ]
            );
        }
    }

    if ($requestId > 0) {
        createResidentTransaction(
            $conn,
            (string)$userId,
            (string)$userId,
            'EDIT_REQUEST',
            (string)$requestId,
            mapEditRequestTransactionType('profile'),
            mapEditRequestTitle('profile'),
            (int)$pendingStatusId,
            mapEditRequestDescription('profile'),
            ['request_type' => 'profile']
        );
    }

    $conn->commit();
    $message = ($requestId > 0 && $sectorChanged)
        ? 'Profile update submitted. Sector membership request is now pending sector verification.'
        : (($requestId > 0)
            ? 'Profile edit request submitted.'
            : 'Sector membership request submitted for verification.');
    echo json_encode(['success' => true, 'message' => $message]);
} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
