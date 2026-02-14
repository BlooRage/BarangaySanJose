<?php
session_start();
include "../General/connection.php";
require_once "../General/uniqueIDGenerate.php";
require_once __DIR__ . "/../../composer-email-handler/vendor/autoload.php";

use setasign\Fpdi\Fpdi;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Access denied. You must be logged in."
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);
    exit;
}

$conn->set_charset("utf8mb4");

// -------- Helpers --------
function getStatusId(mysqli $conn, string $name, string $type): int {
    $q = $conn->prepare("SELECT status_id FROM statuslookuptbl WHERE status_name=? AND status_type=? LIMIT 1");
    if (!$q) throw new Exception("Prepare failed (getStatusId): " . $conn->error);

    $q->bind_param("ss", $name, $type);
    $q->execute();
    $res = $q->get_result()->fetch_assoc();
    $q->close();

    if (!$res || !isset($res['status_id'])) {
        throw new Exception("Status not found: {$name} ({$type})");
    }
    return (int)$res['status_id'];
}

function cleanString($v): string {
    return trim((string)$v);
}

function isValidPersonName(string $value, int $minLetters = 1, int $maxLength = 50): bool {
    $value = trim($value);
    if ($value === '') {
        return false;
    }
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    if ($length > $maxLength) {
        return false;
    }
    if (!preg_match("/^[A-Za-zÀ-ÖØ-öø-ÿÑñ.' -]+$/u", $value)) {
        return false;
    }
    if (!preg_match("/^[A-Za-zÀ-ÖØ-öø-ÿÑñ]/u", $value) || !preg_match("/[A-Za-zÀ-ÖØ-öø-ÿÑñ]$/u", $value)) {
        return false;
    }
    preg_match_all("/[A-Za-zÀ-ÖØ-öø-ÿÑñ]/u", $value, $matches);
    return count($matches[0]) >= $minLetters;
}

function isValidAlphaText(string $value): bool {
    $value = trim($value);
    if ($value === '') {
        return false;
    }
    return (bool)preg_match("/^[A-Za-zÀ-ÖØ-öø-ÿÑñ .,'-]+$/u", $value);
}

function isValidAddressLikeText(string $value): bool {
    $value = trim($value);
    if ($value === '') {
        return false;
    }
    return (bool)preg_match("/^[A-Za-z0-9À-ÖØ-öø-ÿÑñ .,'#()\\/&-]+$/u", $value);
}

function isValidIdNumber(string $value): bool {
    $value = trim($value);
    if ($value === '') {
        return false;
    }
    return (bool)preg_match('/^[A-Za-z0-9-]{3,50}$/', $value);
}

function normalizePhaseNumber(string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/^phase\\s*/i', '', $value);
    return 'Phase ' . trim($value);
}

function getDocumentTypeId(mysqli $conn, string $name): int {
    $q = $conn->prepare("SELECT document_type_id FROM documenttypelookuptbl WHERE LOWER(document_type_name) = LOWER(?) AND document_category = 'ResidentProfiling' LIMIT 1");
    if (!$q) throw new Exception("Prepare failed (getDocumentTypeId): " . $conn->error);
    $q->bind_param("s", $name);
    $q->execute();
    $res = $q->get_result()->fetch_assoc();
    $q->close();
    if ($res && isset($res['document_type_id'])) {
        return (int)$res['document_type_id'];
    }

    $ins = $conn->prepare("INSERT INTO documenttypelookuptbl (document_type_name, document_category) VALUES (?, 'ResidentProfiling')");
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

function isHeicExt(string $ext): bool {
    return in_array($ext, ['heic', 'heif'], true);
}

function appendImagePageToPdf(Fpdi $pdf, string $imagePath): void {
    $imageInfo = @getimagesize($imagePath);
    if ($imageInfo === false || !isset($imageInfo[0], $imageInfo[1])) {
        throw new Exception("Invalid image file for PDF merge.");
    }

    $imgW = (float)$imageInfo[0];
    $imgH = (float)$imageInfo[1];
    $orientation = $imgW > $imgH ? 'L' : 'P';
    $pdf->AddPage($orientation, 'A4');

    $margin = 10.0;
    $pageW = (float)$pdf->GetPageWidth();
    $pageH = (float)$pdf->GetPageHeight();
    $maxW = $pageW - ($margin * 2);
    $maxH = $pageH - ($margin * 2);

    $scale = min($maxW / $imgW, $maxH / $imgH);
    $drawW = $imgW * $scale;
    $drawH = $imgH * $scale;
    $x = ($pageW - $drawW) / 2;
    $y = ($pageH - $drawH) / 2;

    $pdf->Image($imagePath, $x, $y, $drawW, $drawH);
}

function appendPdfFilePages(Fpdi $pdf, string $pdfPath): void {
    $pageCount = $pdf->setSourceFile($pdfPath);
    for ($i = 1; $i <= $pageCount; $i++) {
        $tpl = $pdf->importPage($i);
        $size = $pdf->getTemplateSize($tpl);
        $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
        $pdf->AddPage($orientation, [$size['width'], $size['height']]);
        $pdf->useTemplate($tpl);
    }
}

function buildMergedIdPdf(string $frontPath, string $frontExt, string $backPath, string $backExt, string $outputPath): void {
    $pdf = new Fpdi();

    if ($frontExt === 'pdf') {
        appendPdfFilePages($pdf, $frontPath);
    } else {
        appendImagePageToPdf($pdf, $frontPath);
    }

    if ($backExt === 'pdf') {
        appendPdfFilePages($pdf, $backPath);
    } else {
        appendImagePageToPdf($pdf, $backPath);
    }

    $pdf->Output('F', $outputPath);
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
    // Example stored value: "UnifiedFileAttachment/Documents/<id>/<file>.pdf"
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

try {
    // -------- Collect Inputs --------
    $lastName   = cleanString($_POST['lastName'] ?? '');
    $firstName  = cleanString($_POST['firstName'] ?? '');
    $middleName = cleanString($_POST['middleName'] ?? '');

    $suffix = cleanString($_POST['suffix'] ?? '');
    if ($suffix === "Other") {
        $suffix = cleanString($_POST['suffixOther'] ?? '');
        $suffixLen = function_exists('mb_strlen') ? mb_strlen($suffix, 'UTF-8') : strlen($suffix);
        if ($suffixLen > 3) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Custom suffix must be 3 characters or less."
            ]);
            exit;
        }
    }

    $dob   = cleanString($_POST['dateOfBirth'] ?? '');
    $sex   = cleanString($_POST['sex'] ?? '');
    $civil = cleanString($_POST['civilStatus'] ?? '');
    $familyRole = cleanString($_POST['familyRole'] ?? '');

    $religion = cleanString($_POST['religion'] ?? '');
    if ($religion === "Other") {
        $religion = cleanString($_POST['religionOther'] ?? '');
    }

    $isHead = (($_POST['isHead'] ?? '') === 'yes') ? 1 : 0;
    $voter  = (($_POST['registeredVoter'] ?? '') === 'yes') ? 1 : 0;

    /**
     * ✅ FIX: Your form sends:
     * - occupationStatus = employed/unemployed
     * - occupation = job title text (only when employed)
     *
     * Database expects:
     * - occupation = 0/1
     * - occupation_detail = job title text
     */
    $occupationStatus = strtolower(trim((string)($_POST['occupationStatus'] ?? 'unemployed')));
    $occupation = ($occupationStatus === 'employed') ? 1 : 0;

    // job title input in your HTML is name="occupation"
    $occupationDetail = cleanString($_POST['occupation'] ?? '');

    if ($occupation === 0) {
        $occupationDetail = '';
    }

    $selectedSectors = [];
    if (isset($_POST['sectorMembership']) && is_array($_POST['sectorMembership'])) {
        $selectedSectors = array_values(array_filter(array_map('trim', $_POST['sectorMembership']), static function ($v) {
            return $v !== '';
        }));
    }
    $sector = implode(",", $selectedSectors);

    $privacy = isset($_POST['privacyConsent']) ? 1 : 0;

    // Address
    $addressSystem = cleanString($_POST['addressSystem'] ?? '');
    $houseNumber = cleanString($_POST['houseNumber'] ?? '');
    $streetName  = cleanString($_POST['streetName'] ?? '');
    $phaseNumber = normalizePhaseNumber(cleanString($_POST['phaseNumber'] ?? ''));
    $unitNumber  = cleanString($_POST['unitNumber'] ?? '');
    $lotNumber   = cleanString($_POST['lotNumber'] ?? '');
    $blockNumber = cleanString($_POST['blockNumber'] ?? '');
    $subd        = cleanString($_POST['subdivisionSitio'] ?? '');
    $area        = cleanString($_POST['areaNumber'] ?? '');

    $houseType = cleanString($_POST['houseType'] ?? '');
    if ($houseType === "Other") {
        $houseType = cleanString($_POST['houseTypeOther'] ?? '');
    }

    $ownership = cleanString($_POST['houseOwnership'] ?? '');
    $duration  = cleanString($_POST['residencyDuration'] ?? '');

    // -------- Basic Validation --------
    if ($lastName === '' || $firstName === '' || $dob === '' || $sex === '' || $civil === '' || !$privacy) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Missing required fields."
        ]);
        exit;
    }

    $allowedSex = ['Male', 'Female'];
    $allowedCivil = ['Single', 'Married', 'Widowed'];
    $allowedFamilyRole = ['Spouse', 'Child', 'Parent', 'Sibling', 'Grandparents', 'Extended Family'];
    $allowedAddressSystem = ['house', 'lot_block'];

    if (!in_array($sex, $allowedSex, true)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid sex value."]);
        exit;
    }
    if (!in_array($civil, $allowedCivil, true)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid civil status value."]);
        exit;
    }
    if ($familyRole === '' || !in_array($familyRole, $allowedFamilyRole, true)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid family role value."]);
        exit;
    }
    if (!in_array($addressSystem, $allowedAddressSystem, true)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Please select a valid address system."]);
        exit;
    }

    $nameChecks = [
        'First name' => $firstName,
        'Last name' => $lastName
    ];
    if ($middleName !== '') {
        $nameChecks['Middle name'] = $middleName;
    }
    foreach ($nameChecks as $label => $nameValue) {
        $minLetters = ($label === 'First name' || $label === 'Last name') ? 2 : 1;
        $maxLength = ($label === 'First name') ? 30 : (($label === 'Last name' || $label === 'Middle name') ? 20 : 50);
        if (!isValidPersonName($nameValue, $minLetters, $maxLength)) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "{$label} contains invalid characters."
            ]);
            exit;
        }
    }

    $alphaOptionalChecks = [];
    if ($suffix !== '') $alphaOptionalChecks['Suffix'] = $suffix;
    if ($religion !== '') $alphaOptionalChecks['Religion'] = $religion;
    if ($occupationDetail !== '') $alphaOptionalChecks['Occupation'] = $occupationDetail;
    foreach ($alphaOptionalChecks as $label => $value) {
        if (!isValidAlphaText($value)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "{$label} contains invalid characters."]);
            exit;
        }
    }

    $addressLikeChecks = [];
    if ($unitNumber !== '') $addressLikeChecks['Unit number'] = $unitNumber;
    if ($houseNumber !== '') $addressLikeChecks['House number'] = $houseNumber;
    if ($streetName !== '') $addressLikeChecks['Street name'] = $streetName;
    if ($phaseNumber !== '') $addressLikeChecks['Phase'] = $phaseNumber;
    if ($lotNumber !== '') $addressLikeChecks['Lot number'] = $lotNumber;
    if ($blockNumber !== '') $addressLikeChecks['Block number'] = $blockNumber;
    if ($subd !== '') $addressLikeChecks['Subdivision'] = $subd;
    foreach ($addressLikeChecks as $label => $value) {
        if (!isValidAddressLikeText($value)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "{$label} contains invalid characters."]);
            exit;
        }
    }

    if ($addressSystem === '') {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Please select an address system."
        ]);
        exit;
    }

    if ($addressSystem === 'house') {
        if ($houseNumber === '' || $streetName === '' || $area === '') {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "House number, street name, and area are required."
            ]);
            exit;
        }
    } elseif ($addressSystem === 'lot_block') {
        if ($lotNumber === '' || $blockNumber === '' || $area === '') {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Lot, block, and area are required."
            ]);
            exit;
        }
        $houseNumber = "Lot " . $lotNumber;
        $streetName = "Block " . $blockNumber;
    }

    $addressId = GenerateAddressID($conn, $area);

    // ✅ Must be at least 18 years old
    $dobDate = DateTime::createFromFormat('Y-m-d', $dob);
    if (!$dobDate) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid date of birth."
        ]);
        exit;
    }

    $today = new DateTime('today');
    $age = $dobDate->diff($today)->y;
    if ($age < 18) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "You must be at least 18 years old to register."
        ]);
        exit;
    }

    // ✅ If employed, require job title
    if ($occupation === 1 && $occupationDetail === '') {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Occupation / Job Title is required when Employed."
        ]);
        exit;
    }

    if ($occupationDetail !== '') {
        $occupationLen = function_exists('mb_strlen') ? mb_strlen($occupationDetail, 'UTF-8') : strlen($occupationDetail);
        if ($occupationLen > 20) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Occupation / Job Title must be 20 characters or less."
            ]);
            exit;
        }
    }

    $conn->begin_transaction();

    // Default Resident status = NotVerified (Resident)
    $residentStatusId = getStatusId($conn, "NotVerified", "Resident");

    // Address status: PendingVerification if proof is skipped, otherwise Residing
    $skipProof = isset($_POST['skipProofIdentity']) && $_POST['skipProofIdentity'] === '1';
    $addressStatusId = $skipProof
        ? getStatusId($conn, "PendingVerification", "AddressResidency")
        : getStatusId($conn, "Residing", "AddressResidency");

    // -------- Insert Resident Info --------
    $resident_id = GenerateResidentID($conn);
    if (!$resident_id) {
        throw new Exception("Failed to generate resident ID.");
    }

    $stmt = $conn->prepare("
        INSERT INTO residentinformationtbl 
        (resident_id, user_id, lastname, firstname, middlename, suffix, sex, birthdate, civil_status,
         head_of_family, voter_status, occupation, occupation_detail,
         religion, sector_membership, privacy_consent, status_id_resident)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) throw new Exception("Prepare failed (resident insert): " . $conn->error);

    $stmt->bind_param(
        "sssssssssiiisssii",
        $resident_id,
        $user_id,
        $lastName,
        $firstName,
        $middleName,
        $suffix,
        $sex,
        $dob,
        $civil,
        $isHead,
        $voter,
        $occupation,
        $occupationDetail,
        $religion,
        $sector,
        $privacy,
        $residentStatusId
    );

    if (!$stmt->execute()) throw new Exception("Insert resident failed: " . $stmt->error);
    $stmt->close();

    // -------- Insert Address --------
    $stmt2 = $conn->prepare("
        INSERT INTO residentaddresstbl
        (address_id, resident_id, unit_number, street_number, street_name, phase_number, subdivision, area_number, house_type, house_ownership, residency_duration, status_id_residency)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt2) throw new Exception("Prepare failed (address insert): " . $conn->error);

    $stmt2->bind_param(
        "sssssssssssi",
        $addressId,
        $resident_id,
        $unitNumber,
        $houseNumber,
        $streetName,
        $phaseNumber,
        $subd,
        $area,
        $houseType,
        $ownership,
        $duration,
        $addressStatusId
    );

    if (!$stmt2->execute()) throw new Exception("Insert address failed: " . $stmt2->error);
    $stmt2->close();

    // -------- Optional Upload Files --------
    $skipProof = isset($_POST['skipProofIdentity']) && $_POST['skipProofIdentity'] === '1';
    $proofType = cleanString($_POST['proofType'] ?? '');
    $validProofTypes = ['ID', 'Document'];
    if (!$skipProof && !in_array($proofType, $validProofTypes, true)) {
        throw new Exception("Please select a valid proof type.");
    }

    $idFiles = ['idFront', 'idBack'];
    $hasIdProof = true;
    foreach ($idFiles as $fileKey) {
        if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
            $hasIdProof = false;
            break;
        }
    }

    $hasPicture = isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK;
    $hasDocumentProof = false;
    if (isset($_FILES['documentProof']) && is_array($_FILES['documentProof']['name'])) {
        foreach ($_FILES['documentProof']['name'] as $i => $name) {
            if (isset($_FILES['documentProof']['error'][$i]) && $_FILES['documentProof']['error'][$i] === UPLOAD_ERR_OK) {
                $hasDocumentProof = true;
                break;
            }
        }
    }

    if (!$skipProof) {
        if ($proofType === 'ID' && !$hasIdProof) {
            throw new Exception("ID Front and Back are required.");
        }
        if ($proofType === 'Document' && !$hasDocumentProof) {
            throw new Exception("Supporting document upload is required.");
        }
        if (!$hasPicture) {
            throw new Exception("2x2 picture is required.");
        }
    }

    $sourceType = "ResidentProfiling";
    $statusVerifyId = getStatusId($conn, "PendingReview", "ResidentDocumentProfiling");
    $uploadDirDocs = __DIR__ . "/../../UnifiedFileAttachment/Documents/$resident_id/";
    $uploadDirPic = __DIR__ . "/../../UnifiedFileAttachment/IDPictures/$resident_id/";

    if (!is_dir($uploadDirDocs) && !mkdir($uploadDirDocs, 0755, true)) {
        throw new Exception("Failed to create document upload directory.");
    }
    if (!is_dir($uploadDirPic) && !mkdir($uploadDirPic, 0755, true)) {
        throw new Exception("Failed to create ID picture upload directory.");
    }

    if ($hasIdProof) {
        $uploadedIdNumber = cleanString($_POST['idNumber'] ?? '');
        if (!isValidIdNumber($uploadedIdNumber)) {
            throw new Exception("ID Number must be 3-50 characters (letters, numbers, hyphen only).");
        }

        $idType = cleanString($_POST['idType'] ?? '');
        if ($idType === '') {
            throw new Exception("ID Type is required.");
        }

        $savedIdFiles = [];
        $allowedId = ['jpg','jpeg','png','webp','pdf'];
        foreach ($idFiles as $fileKey) {
            $tmpName = $_FILES[$fileKey]['tmp_name'];
            $name = basename($_FILES[$fileKey]['name']);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (isHeicExt($ext)) {
                throw new Exception("HEIC is not supported on the server. Please upload JPG or PNG.");
            }
            if (!in_array($ext, $allowedId, true)) {
                throw new Exception("Invalid file type for {$fileKey}.");
            }

            $fileMoved = moveUploadedFileWithDocName($tmpName, $uploadDirDocs, $idType . ' ' . $fileKey, $user_id, $ext);
            $savedIdFiles[$fileKey] = [
                'path' => $fileMoved['disk_path'],
                'ext' => $ext
            ];
        }

        if (!isset($savedIdFiles['idFront'], $savedIdFiles['idBack'])) {
            throw new Exception("ID front/back files are incomplete.");
        }

        $mergedFileName = buildAttachmentFileName($idType, $user_id, 'pdf');
        $mergedPath = $uploadDirDocs . $mergedFileName;
        $mergeIndex = 0;
        while (file_exists($mergedPath)) {
            $mergeIndex++;
            $mergedFileName = buildAttachmentFileName($idType, $user_id, 'pdf', $mergeIndex);
            $mergedPath = $uploadDirDocs . $mergedFileName;
        }

        buildMergedIdPdf(
            $savedIdFiles['idFront']['path'],
            $savedIdFiles['idFront']['ext'],
            $savedIdFiles['idBack']['path'],
            $savedIdFiles['idBack']['ext'],
            $mergedPath
        );

        if (!file_exists($mergedPath) || filesize($mergedPath) <= 0) {
            throw new Exception("Failed to generate merged ID PDF.");
        }

        // Keep only the merged PDF; remove temporary front/back files.
        foreach ($savedIdFiles as $savedFile) {
            $tmpDiskPath = (string)($savedFile['path'] ?? '');
            if ($tmpDiskPath !== '' && file_exists($tmpDiskPath)) {
                @unlink($tmpDiskPath);
            }
        }

        $docTypeId = getDocumentTypeId($conn, $idType);
        $remarks = "idMerged";
        $fileType = "pdf";
        $mergedPathDb = toDbWebPath($mergedPath);
        $ins = $conn->prepare("
            INSERT INTO unifiedfileattachmenttbl
            (source_type, source_id, document_type_id, file_name, file_path, file_type, user_id_uploaded_by, status_id_verify, remarks, id_number)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$ins) throw new Exception("Prepare failed (insert merged ID): " . $conn->error);
        $ins->bind_param(
            "ssissssiss",
            $sourceType,
            $resident_id,
            $docTypeId,
            $mergedFileName,
            $mergedPathDb,
            $fileType,
            $user_id,
            $statusVerifyId,
            $remarks,
            $uploadedIdNumber
        );
        if (!$ins->execute()) throw new Exception("Merged ID attachment insert failed: " . $ins->error);
        $ins->close();
    }

    if ($hasPicture) {
        $tmpName = $_FILES['picture']['tmp_name'];
        $name = basename($_FILES['picture']['name']);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowedPic = ['jpg','jpeg','png','webp'];

        if (isHeicExt($ext)) {
            throw new Exception("HEIC is not supported on the server. Please upload JPG or PNG.");
        }
        if (!in_array($ext, $allowedPic, true)) {
            throw new Exception("Invalid file type for 2x2 picture.");
        }

        $moved = moveUploadedFileWithDocName($tmpName, $uploadDirPic, "2x2 Picture", $user_id, $ext);
        $docTypeId = getDocumentTypeId($conn, "2x2 Picture");
        $remarks = "2x2";
        $idNumber = null;
        $ins = $conn->prepare("
            INSERT INTO unifiedfileattachmenttbl
            (source_type, source_id, document_type_id, file_name, file_path, file_type, user_id_uploaded_by, status_id_verify, remarks, id_number)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$ins) throw new Exception("Prepare failed (insert 2x2): " . $conn->error);
        $ins->bind_param(
            "ssissssiss",
            $sourceType,
            $resident_id,
            $docTypeId,
            $moved['file_name'],
            $moved['file_path'],
            $ext,
            $user_id,
            $statusVerifyId,
            $remarks,
            $idNumber
        );
        if (!$ins->execute()) throw new Exception("2x2 attachment insert failed: " . $ins->error);
        $ins->close();
    }

    if ($hasDocumentProof) {
        $documentType = cleanString($_POST['documentType'] ?? '');
        if (!in_array($documentType, ['Billing Statement', 'HOA Signed Certification of Residency'], true)) {
            throw new Exception("Invalid document type.");
        }

        $docAllowed = ['jpg','jpeg','png','webp','pdf'];
        $docTypeId = getDocumentTypeId($conn, $documentType);
        foreach ($_FILES['documentProof']['name'] as $i => $docName) {
            if ($_FILES['documentProof']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            $tmpName = $_FILES['documentProof']['tmp_name'][$i];
            $ext = strtolower(pathinfo($docName, PATHINFO_EXTENSION));
            if (isHeicExt($ext)) {
                throw new Exception("HEIC is not supported on the server. Please upload JPG or PNG.");
            }
            if (!in_array($ext, $docAllowed, true)) {
                throw new Exception("Invalid file type for document attachment.");
            }

            $moved = moveUploadedFileWithDocName($tmpName, $uploadDirDocs, $documentType, $user_id, $ext);
            $remarks = "document";
            $idNumber = null;
            $ins = $conn->prepare("
                INSERT INTO unifiedfileattachmenttbl
                (source_type, source_id, document_type_id, file_name, file_path, file_type, user_id_uploaded_by, status_id_verify, remarks, id_number)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if (!$ins) throw new Exception("Prepare failed (insert document): " . $conn->error);
            $ins->bind_param(
                "ssissssiss",
                $sourceType,
                $resident_id,
                $docTypeId,
                $moved['file_name'],
                $moved['file_path'],
                $ext,
                $user_id,
                $statusVerifyId,
                $remarks,
                $idNumber
            );
            if (!$ins->execute()) throw new Exception("Document attachment insert failed: " . $ins->error);
            $ins->close();
        }
    }

    // Sector specific proof uploads
    $sectorPostToKey = [
        'PWD' => 'PWD',
        'Single Parent' => 'SingleParent',
        'Student' => 'Student',
        'Senior Citizen' => 'SeniorCitizen',
        'Indigenous People' => 'IndigenousPeople'
    ];
    $selectedSectorKeys = [];
    foreach ($selectedSectors as $sectorLabel) {
        if (isset($sectorPostToKey[$sectorLabel])) {
            $selectedSectorKeys[] = $sectorPostToKey[$sectorLabel];
        }
    }

    $isIdProofSelected = ($proofType === 'ID');
    $idTypeSelected = strtolower(cleanString($_POST['idType'] ?? ''));
    $idTypeSelectedNorm = preg_replace('/[^a-z0-9]/', '', (string)$idTypeSelected);
    $isNationalIdSelected = in_array($idTypeSelectedNorm, ['nationalid', 'philsysid', 'philsysidephilid', 'ephilid'], true);

    foreach ($selectedSectorKeys as $sectorKey) {
        $docTypeValue = cleanString($_POST['sectorDocType'][$sectorKey] ?? '');
        $file = $_FILES['sectorDocFile'] ?? null;
        $sectorErrors = ($file && isset($file['error'][$sectorKey]) && is_array($file['error'][$sectorKey]))
            ? $file['error'][$sectorKey]
            : [];
        $hasFile = false;
        foreach ($sectorErrors as $errCode) {
            if ($errCode === UPLOAD_ERR_OK) {
                $hasFile = true;
                break;
            }
        }

        $isRequired = true;
        if ($sectorKey === 'SingleParent') {
            $isRequired = false;
        } elseif ($sectorKey === 'SeniorCitizen' && $isIdProofSelected) {
            $isRequired = false;
        } elseif ($sectorKey === 'IndigenousPeople' && $isIdProofSelected && $isNationalIdSelected) {
            $isRequired = false;
        }

        // Business rule: if Proof Type is ID, prohibit uploading Senior Citizen sector proof.
        if ($sectorKey === 'SeniorCitizen' && $isIdProofSelected) {
            if ($hasFile || $docTypeValue !== '') {
                throw new Exception("Senior Citizen proof upload is not allowed when using ID as proof of identity.");
            }
            continue;
        }

        // Business rule: if Proof Type is ID and (National ID / PhilSys / ePhilID) is used,
        // prohibit uploading Indigenous People sector proof.
        if ($sectorKey === 'IndigenousPeople' && $isIdProofSelected && $isNationalIdSelected) {
            if ($hasFile || $docTypeValue !== '') {
                throw new Exception("Indigenous People proof upload is not allowed when using National ID/PhilSys as proof of identity.");
            }
            continue;
        }

        if ($isRequired && ($docTypeValue === '' || !$hasFile)) {
            throw new Exception("Please upload the required proof for selected sector membership.");
        }

        if (!$hasFile) {
            continue;
        }
        if ($docTypeValue === '') {
            throw new Exception("Please select sector document type.");
        }

        $docTypeId = getDocumentTypeId($conn, $docTypeValue);
        $allowedSector = ['jpg','jpeg','png','webp','pdf'];
        $sectorNames = isset($file['name'][$sectorKey]) && is_array($file['name'][$sectorKey]) ? $file['name'][$sectorKey] : [];
        $sectorTmpNames = isset($file['tmp_name'][$sectorKey]) && is_array($file['tmp_name'][$sectorKey]) ? $file['tmp_name'][$sectorKey] : [];
        $sectorErrorCodes = isset($file['error'][$sectorKey]) && is_array($file['error'][$sectorKey]) ? $file['error'][$sectorKey] : [];

        foreach ($sectorNames as $idx => $originalRawName) {
            $errCode = (int)($sectorErrorCodes[$idx] ?? UPLOAD_ERR_NO_FILE);
            if ($errCode !== UPLOAD_ERR_OK) {
                continue;
            }

            $originalName = basename((string)$originalRawName);
            $tmpName = $sectorTmpNames[$idx] ?? '';
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (isHeicExt($ext)) {
                throw new Exception("HEIC is not supported on the server. Please upload JPG or PNG.");
            }
            if (!in_array($ext, $allowedSector, true)) {
                throw new Exception("Invalid file type for sector proof.");
            }

            $moved = moveUploadedFileWithDocName($tmpName, $uploadDirDocs, $docTypeValue, $user_id, $ext);
            $remarks = "sector:" . $sectorKey;
            $idNumber = null;
            $ins = $conn->prepare("
                INSERT INTO unifiedfileattachmenttbl
                (source_type, source_id, document_type_id, file_name, file_path, file_type, user_id_uploaded_by, status_id_verify, remarks, id_number)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if (!$ins) throw new Exception("Prepare failed (insert sector document): " . $conn->error);
            $ins->bind_param(
                "ssissssiss",
                $sourceType,
                $resident_id,
                $docTypeId,
                $moved['file_name'],
                $moved['file_path'],
                $ext,
                $user_id,
                $statusVerifyId,
                $remarks,
                $idNumber
            );
            if (!$ins->execute()) throw new Exception("Sector document attachment insert failed: " . $ins->error);
            $ins->close();
        }
    }

    $hasAnySectorProof = false;
    if (isset($_FILES['sectorDocFile']['name']) && is_array($_FILES['sectorDocFile']['name'])) {
        foreach ($_FILES['sectorDocFile']['error'] as $sectorKey => $errorsPerSector) {
            if (!is_array($errorsPerSector)) {
                continue;
            }
            foreach ($errorsPerSector as $errCode) {
                if ($errCode === UPLOAD_ERR_OK) {
                    $hasAnySectorProof = true;
                    break 2;
                }
            }
        }
    }

    if ($hasIdProof || $hasDocumentProof || $hasPicture || $hasAnySectorProof) {
        $pendingResidentStatusId = getStatusId($conn, "PendingVerification", "Resident");

        $updateStatus = $conn->prepare("UPDATE residentinformationtbl SET status_id_resident=? WHERE resident_id=?");
        if (!$updateStatus) throw new Exception("Prepare failed (update resident status): " . $conn->error);

        $updateStatus->bind_param("is", $pendingResidentStatusId, $resident_id);
        if (!$updateStatus->execute()) throw new Exception("Update status failed: " . $updateStatus->error);
        $updateStatus->close();
    }

    // -------- Emergency Contact --------
    $eLast  = cleanString($_POST['emergencyLastName'] ?? '');
    $eFirst = cleanString($_POST['emergencyFirstName'] ?? '');
    $eMid   = cleanString($_POST['emergencyMiddleName'] ?? '');

    $eSuffix = cleanString($_POST['emergencySuffix'] ?? '');
    if ($eSuffix === "Other") {
        $eSuffix = cleanString($_POST['emergencySuffixOther'] ?? '');
        $eSuffixLen = function_exists('mb_strlen') ? mb_strlen($eSuffix, 'UTF-8') : strlen($eSuffix);
        if ($eSuffixLen > 3) {
            throw new Exception("Emergency custom suffix must be 3 characters or less.");
        }
    }

    $ePhone = cleanString($_POST['emergencyPhoneNumber'] ?? '');
    $eRel   = cleanString($_POST['emergencyRelationship'] ?? '');
    $eAddr  = cleanString($_POST['emergencyAddress'] ?? '');

    if ($eLast === '' || $eFirst === '' || $ePhone === '' || $eRel === '' || $eAddr === '') {
        throw new Exception("Missing required emergency contact fields.");
    }

    if (!preg_match('/^9\\d{9}$/', $ePhone)) {
        throw new Exception("Emergency contact number must be 10 digits and start with 9.");
    }
    if (!isValidAddressLikeText($eAddr)) {
        throw new Exception("Emergency address contains invalid characters.");
    }
    if (!isValidAlphaText($eRel)) {
        throw new Exception("Emergency relationship contains invalid characters.");
    }

    $emergencyNameChecks = [
        'Emergency first name' => $eFirst,
        'Emergency last name' => $eLast
    ];
    if ($eMid !== '') {
        $emergencyNameChecks['Emergency middle name'] = $eMid;
    }
    foreach ($emergencyNameChecks as $label => $nameValue) {
        $minLetters = ($label === 'Emergency first name' || $label === 'Emergency last name') ? 2 : 1;
        $maxLength = ($label === 'Emergency first name') ? 30 : (($label === 'Emergency last name' || $label === 'Emergency middle name') ? 20 : 50);
        if (!isValidPersonName($nameValue, $minLetters, $maxLength)) {
            throw new Exception("{$label} contains invalid characters.");
        }
    }

    $stmtE = $conn->prepare("
      INSERT INTO emergencycontacttbl
      (user_id, last_name, first_name, middle_name, suffix, phone_number, relationship, address)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        last_name=VALUES(last_name),
        first_name=VALUES(first_name),
        middle_name=VALUES(middle_name),
        suffix=VALUES(suffix),
        phone_number=VALUES(phone_number),
        relationship=VALUES(relationship),
        address=VALUES(address),
        updated_at=CURRENT_TIMESTAMP
    ");
    if (!$stmtE) throw new Exception('Prepare failed (emergency upsert): ' . $conn->error);

    $stmtE->bind_param("ssssssss", $user_id, $eLast, $eFirst, $eMid, $eSuffix, $ePhone, $eRel, $eAddr);

    if (!$stmtE->execute()) throw new Exception("Emergency contact save failed: " . $stmtE->error);
    $stmtE->close();

    $conn->commit();

    $uploadedAnyProof = $hasIdProof || $hasDocumentProof || $hasPicture || $hasAnySectorProof;
    $message = $uploadedAnyProof
        ? "Information saved successfully! Documents uploaded and pending verification."
        : "Information saved successfully! Address saved (Pending Verification). You can upload documents later.";

    echo json_encode([
        "success" => true,
        "message" => $message,
        "redirect" => "resident_dashboard.php"
    ]);
    exit;

} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
    exit;
}
?>
