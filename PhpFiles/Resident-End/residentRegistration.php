<?php
session_start();
include "../General/connection.php";

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
    $houseNumber = cleanString($_POST['houseNumber'] ?? '');
    $streetName  = cleanString($_POST['streetName'] ?? '');
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

    // Address status (saved address, no proof) = PendingVerification (AddressResidency)
    $addressStatusId  = getStatusId($conn, "PendingVerification", "AddressResidency");

    // -------- Insert Resident Info --------
    $stmt = $conn->prepare("
        INSERT INTO residentinformationtbl 
        (user_id, lastname, firstname, middlename, suffix, sex, birthdate, civil_status,
         head_of_family, voter_status, occupation, occupation_detail,
         religion, sector_membership, privacy_consent, status_id_resident)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) throw new Exception("Prepare failed (resident insert): " . $conn->error);

    $stmt->bind_param(
        "ssssssssiiisssii",
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
    $resident_id = (int)$stmt->insert_id;
    $stmt->close();

    // -------- Insert Address --------
    $stmt2 = $conn->prepare("
        INSERT INTO residentaddresstbl
        (resident_id, street_number, street_name, subdivision, area_number, house_type, house_ownership, residency_duration, status_id_residency)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt2) throw new Exception("Prepare failed (address insert): " . $conn->error);

    $stmt2->bind_param(
        "isssssssi",
        $resident_id,
        $houseNumber,
        $streetName,
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

    $files = ['idFront', 'idBack', 'picture'];
    $allProvided = true;

    foreach ($files as $fileKey) {
        if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
            $allProvided = false;
            break;
        }
    }

    if ($requireFiles && !$allProvided) {
        throw new Exception("Missing required files.");
    }

    if ($allProvided) {
        $uploadDir = "../Uploads/Residents/$resident_id/";
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception("Failed to create upload directory.");
            }
        }

        foreach ($files as $fileKey) {
            $tmpName = $_FILES[$fileKey]['tmp_name'];
            $name = basename($_FILES[$fileKey]['name']);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            $allowed = ['jpg','jpeg','png','webp','pdf'];
            if (!in_array($ext, $allowed, true)) {
                throw new Exception("Invalid file type for {$fileKey}.");
            }

            $newFileName = $fileKey . "." . $ext;
            $target = $uploadDir . $newFileName;

            if (!move_uploaded_file($tmpName, $target)) {
                throw new Exception("Failed to upload file: {$fileKey}");
            }
        }

        $pendingResidentStatusId = getStatusId($conn, "PendingVerification", "Resident");

        $updateStatus = $conn->prepare("UPDATE residentinformationtbl SET status_id_resident=? WHERE resident_id=?");
        if (!$updateStatus) throw new Exception("Prepare failed (update resident status): " . $conn->error);

        $updateStatus->bind_param("ii", $pendingResidentStatusId, $resident_id);
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
