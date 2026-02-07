<?php
session_start();
include "../General/connection.php";
require_once "../General/uniqueIDGenerate.php";

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
    if (!$res || !isset($res['document_type_id'])) {
        throw new Exception("Document type not found: {$name}");
    }
    return (int)$res['document_type_id'];
}

function isHeicExt(string $ext): bool {
    return in_array($ext, ['heic', 'heif'], true);
}

function convertHeicToJpg(string $tmpPath, string $targetPath): void {
    if (!class_exists('Imagick')) {
        throw new Exception("HEIC conversion requires Imagick.");
    }
    $img = new Imagick($tmpPath);
    $img->setImageFormat('jpeg');
    $img->setImageCompressionQuality(85);
    $img->writeImage($targetPath);
    $img->clear();
    $img->destroy();
}

try {
    // -------- Collect Inputs --------
    $lastName   = cleanString($_POST['lastName'] ?? '');
    $firstName  = cleanString($_POST['firstName'] ?? '');
    $middleName = cleanString($_POST['middleName'] ?? '');

    $suffix = cleanString($_POST['suffix'] ?? '');
    if ($suffix === "Other") {
        $suffix = cleanString($_POST['suffixOther'] ?? '');
    }

    $dob   = cleanString($_POST['dateOfBirth'] ?? '');
    $sex   = cleanString($_POST['sex'] ?? '');
    $civil = cleanString($_POST['civilStatus'] ?? '');

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

    $sector = '';
    if (isset($_POST['sectorMembership']) && is_array($_POST['sectorMembership'])) {
        $sector = implode(",", array_map('trim', $_POST['sectorMembership']));
    }

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
    $requireFiles = false;

    $idFiles = ['idFront', 'idBack'];
    $allProvided = true;

    foreach ($idFiles as $fileKey) {
        if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
            $allProvided = false;
            break;
        }
    }

    if ($requireFiles && !$allProvided) {
        throw new Exception("Missing required files.");
    }

    $hasIdProof = $allProvided;
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

    if ($hasIdProof) {
        $uploadDir = __DIR__ . "/../../UnifiedFileAttachment/Documents/$resident_id/";
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception("Failed to create upload directory.");
            }
        }

        foreach ($idFiles as $fileKey) {
            $tmpName = $_FILES[$fileKey]['tmp_name'];
            $name = basename($_FILES[$fileKey]['name']);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            $allowed = ['jpg','jpeg','png','webp','pdf'];
            if (isHeicExt($ext)) {
                throw new Exception("HEIC is not supported on the server. Please upload JPG or PNG.");
            }
            if (!in_array($ext, $allowed, true)) {
                throw new Exception("Invalid file type for {$fileKey}.");
            }

            $newExt = $ext;
            $newFileName = $fileKey . "." . $newExt;
            $target = $uploadDir . $newFileName;

            if (!move_uploaded_file($tmpName, $target)) {
                throw new Exception("Failed to upload file: {$fileKey}");
            }

            $docTypeId = getDocumentTypeId($conn, cleanString($_POST['idType'] ?? ''));
            $statusVerifyId = getStatusId($conn, "PendingReview", "ResidentDocumentProfiling");
            $ins = $conn->prepare("
                INSERT INTO unifiedfileattachmenttbl
                (source_type, source_id, document_type_id, file_name, file_path, file_type, user_id_uploaded_by, status_id_verify, remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if (!$ins) throw new Exception("Prepare failed (insert attachment): " . $conn->error);
            $sourceType = "ResidentProfiling";
            $remarks = $fileKey;
            $fileName = $newFileName;
            $filePath = $target;
            $fileType = $newExt;
            $ins->bind_param(
                "ssissssis",
                $sourceType,
                $resident_id,
                $docTypeId,
                $fileName,
                $filePath,
                $fileType,
                $user_id,
                $statusVerifyId,
                $remarks
            );
            if (!$ins->execute()) throw new Exception("Attachment insert failed: " . $ins->error);
            $ins->close();
        }

    }

    if ($hasPicture) {
        $uploadDirPic = __DIR__ . "/../../UnifiedFileAttachment/IDPictures/$resident_id/";
        if (!is_dir($uploadDirPic)) {
            if (!mkdir($uploadDirPic, 0755, true)) {
                throw new Exception("Failed to create ID picture upload directory.");
            }
        }

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

        $newExt = $ext;
        $target = $uploadDirPic . "2x2." . $newExt;

        if (!move_uploaded_file($tmpName, $target)) {
            throw new Exception("Failed to upload 2x2 picture.");
        }

        $docTypeId = getDocumentTypeId($conn, "2x2 Picture");
        $statusVerifyId = getStatusId($conn, "PendingReview", "ResidentDocumentProfiling");
        $ins = $conn->prepare("
            INSERT INTO unifiedfileattachmenttbl
            (source_type, source_id, document_type_id, file_name, file_path, file_type, user_id_uploaded_by, status_id_verify, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$ins) throw new Exception("Prepare failed (insert attachment): " . $conn->error);
        $sourceType = "ResidentProfiling";
        $remarks = "2x2";
        $fileName = basename($target);
        $filePath = $target;
        $fileType = $newExt;
        $ins->bind_param(
            "ssissssis",
            $sourceType,
            $resident_id,
            $docTypeId,
            $fileName,
            $filePath,
            $fileType,
            $user_id,
            $statusVerifyId,
            $remarks
        );
        if (!$ins->execute()) throw new Exception("Attachment insert failed: " . $ins->error);
        $ins->close();
    }

    if ($hasDocumentProof) {
        $uploadDirDocs = __DIR__ . "/../../UnifiedFileAttachment/Documents/$resident_id/";
        if (!is_dir($uploadDirDocs)) {
            if (!mkdir($uploadDirDocs, 0755, true)) {
                throw new Exception("Failed to create document upload directory.");
            }
        }

        $docAllowed = ['jpg','jpeg','png','webp','pdf'];
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

            $safeBase = pathinfo($docName, PATHINFO_FILENAME);
            $safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $safeBase);
            $newExt = $ext;
            $target = $uploadDirDocs . $safeBase . "_" . time() . "_" . $i . "." . $newExt;

            if (!move_uploaded_file($tmpName, $target)) {
                throw new Exception("Failed to upload document attachment.");
            }

            $docTypeId = getDocumentTypeId($conn, cleanString($_POST['documentType'] ?? ''));
            $statusVerifyId = getStatusId($conn, "PendingReview", "ResidentDocumentProfiling");
            $ins = $conn->prepare("
                INSERT INTO unifiedfileattachmenttbl
                (source_type, source_id, document_type_id, file_name, file_path, file_type, user_id_uploaded_by, status_id_verify, remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if (!$ins) throw new Exception("Prepare failed (insert attachment): " . $conn->error);
            $sourceType = "ResidentProfiling";
            $remarks = "document";
            $fileName = basename($target);
            $filePath = $target;
            $fileType = $newExt;
            $ins->bind_param(
                "ssissssis",
                $sourceType,
                $resident_id,
                $docTypeId,
                $fileName,
                $filePath,
                $fileType,
                $user_id,
                $statusVerifyId,
                $remarks
            );
            if (!$ins->execute()) throw new Exception("Attachment insert failed: " . $ins->error);
            $ins->close();
        }
    }

    if ($hasIdProof || $hasDocumentProof || $hasPicture) {
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
    }

    $ePhone = cleanString($_POST['emergencyPhoneNumber'] ?? '');
    $eRel   = cleanString($_POST['emergencyRelationship'] ?? '');
    $eAddr  = cleanString($_POST['emergencyAddress'] ?? '');

    if ($eLast === '' || $eFirst === '' || $ePhone === '' || $eRel === '' || $eAddr === '') {
        throw new Exception("Missing required emergency contact fields.");
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

    $message = $allProvided
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
