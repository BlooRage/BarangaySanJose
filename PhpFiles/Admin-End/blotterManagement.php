<?php
session_start();

require_once "../General/connection.php";
require_once "../General/security.php";
require_once "../General/uniqueIDGenerate.php";

requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin', 'Employee']);

function str_field($value): ?string {
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function buildAddress(array $parts): ?string {
    $clean = [];
    foreach ($parts as $label => $value) {
        $value = trim((string)$value);
        if ($value === '') continue;
        $clean[] = $label !== '' ? "{$label}: {$value}" : $value;
    }
    if (!$clean) return null;
    return implode(', ', $clean);
}

function getStatusId(mysqli $conn, string $name, string $type): int {
    $q = $conn->prepare("SELECT status_id FROM statuslookuptbl WHERE status_name=? AND status_type=? LIMIT 1");
    if (!$q) {
        throw new Exception("Prepare failed (status lookup): " . $conn->error);
    }
    $q->bind_param("ss", $name, $type);
    $q->execute();
    $res = $q->get_result()->fetch_assoc();
    $q->close();
    if (!$res || !isset($res['status_id'])) {
        throw new Exception("Status not found: {$name} ({$type})");
    }
    return (int)$res['status_id'];
}

function validateIncidentDateTime(string $incidentDate, string $incidentTime): void {
    $timezone = new DateTimeZone(date_default_timezone_get() ?: 'Asia/Manila');
    $now = new DateTimeImmutable('now', $timezone);
    $incidentDateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i', $incidentDate . ' ' . $incidentTime, $timezone);
    if (!$incidentDateTime) {
        http_response_code(400);
        exit("Incident date or time is invalid.");
    }
    if ($incidentDateTime > $now) {
        http_response_code(400);
        exit("Incident date and time cannot be in the future.");
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit("Method not allowed.");
}

$blotterNumber = str_field($_POST['blotter_number'] ?? '');
$dateFiled = str_field($_POST['date_filed'] ?? '');
$timeFiled = str_field($_POST['time_filed'] ?? '');

$complainantLast = str_field($_POST['complainant_last_name'] ?? '');
$complainantFirst = str_field($_POST['complainant_first_name'] ?? '');
$complainantMiddle = str_field($_POST['complainant_middle_name'] ?? '');
$complainantSuffix = str_field($_POST['complainant_suffix'] ?? '');
$complainantContact = str_field($_POST['complainant_contact_number'] ?? '');
$complainantAge = str_field($_POST['complainant_age'] ?? '');
$complainantSex = str_field($_POST['complainant_sex'] ?? '');

$respondentLast = str_field($_POST['respondent_last_name'] ?? '');
$respondentFirst = str_field($_POST['respondent_first_name'] ?? '');
$respondentMiddle = str_field($_POST['respondent_middle_name'] ?? '');
$respondentSuffix = str_field($_POST['respondent_suffix'] ?? '');
$respondentContact = str_field($_POST['respondent_contact_number'] ?? '');
$respondentAge = str_field($_POST['respondent_age'] ?? '');
$respondentSex = str_field($_POST['respondent_sex'] ?? '');

$incidentDate = str_field($_POST['incident_date'] ?? '');
$incidentTime = str_field($_POST['incident_time'] ?? '');
$incidentPlace = str_field($_POST['incident_place'] ?? '');
$complaintType = str_field($_POST['complaint_type'] ?? '');
$narrativeMethod = str_field($_POST['narrative_input_method'] ?? 'text');
$narrativeText = str_field($_POST['narrative_report'] ?? '');

if (!$blotterNumber || !$dateFiled || !$timeFiled || !$complainantLast || !$complainantFirst || !$respondentLast || !$respondentFirst || !$incidentDate || !$incidentTime || !$incidentPlace || !$complaintType) {
    http_response_code(400);
    exit("Missing required fields.");
}

validateIncidentDateTime($incidentDate, $incidentTime);

if ($narrativeMethod === 'text' && !$narrativeText) {
    http_response_code(400);
    exit("Narrative report is required.");
}

$complainantAddress = buildAddress([
    'Address System' => $_POST['complainant_address_system'] ?? '',
    'Unit' => $_POST['complainant_unit_number'] ?? '',
    'Subdivision' => $_POST['complainant_subdivision'] ?? '',
    'House No.' => $_POST['complainant_house_number'] ?? '',
    'Street' => $_POST['complainant_street_name'] ?? '',
    'Area' => $_POST['complainant_area_number'] ?? '',
    'Lot' => $_POST['complainant_lot_number'] ?? '',
    'Block' => $_POST['complainant_block_number'] ?? '',
    'Phase' => $_POST['complainant_phase_number'] ?? '',
    'Barangay' => $_POST['complainant_barangay'] ?? '',
    'Municipality' => $_POST['complainant_municipality'] ?? '',
    'Province' => $_POST['complainant_province'] ?? '',
]);

$respondentAddress = buildAddress([
    'Address System' => $_POST['respondent_address_system'] ?? '',
    'Unit' => $_POST['respondent_unit_number'] ?? '',
    'Subdivision' => $_POST['respondent_subdivision'] ?? '',
    'House No.' => $_POST['respondent_house_number'] ?? '',
    'Street' => $_POST['respondent_street_name'] ?? '',
    'Area' => $_POST['respondent_area_number'] ?? '',
    'Lot' => $_POST['respondent_lot_number'] ?? '',
    'Block' => $_POST['respondent_block_number'] ?? '',
    'Phase' => $_POST['respondent_phase_number'] ?? '',
    'Barangay' => $_POST['respondent_barangay'] ?? '',
    'Municipality' => $_POST['respondent_municipality'] ?? '',
    'Province' => $_POST['respondent_province'] ?? '',
]);

$complainantRemarks = null;
$respondentRemarks = null;

$caseDetails = $narrativeText;
$caseRemarks = null;
$uploadPathForDb = null;

if ($narrativeMethod === 'file') {
    if (!isset($_FILES['narrative_file']) || $_FILES['narrative_file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        exit("Narrative file is required.");
    }

    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    $fileName = $_FILES['narrative_file']['name'] ?? '';
    $tmpName = $_FILES['narrative_file']['tmp_name'] ?? '';
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions, true)) {
        http_response_code(400);
        exit("Invalid narrative file type.");
    }

    $uploadRoot = realpath(__DIR__ . "/../..");
    if ($uploadRoot === false) {
        $uploadRoot = __DIR__ . "/../..";
    }
    $uploadDir = rtrim($uploadRoot, "/\\") . "/UnifiedFileAttachment/BlotterNarratives";
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($fileName, PATHINFO_FILENAME));
    $safeBase = $safeBase !== '' ? $safeBase : 'narrative';
    $uniqueName = $safeBase . "_" . date('Ymd_His') . "_" . bin2hex(random_bytes(4)) . "." . $ext;
    $destPath = $uploadDir . "/" . $uniqueName;
    if (!move_uploaded_file($tmpName, $destPath)) {
        http_response_code(500);
        exit("Failed to save narrative file.");
    }

    $uploadPathForDb = "UnifiedFileAttachment/BlotterNarratives/" . $uniqueName;
    $caseDetails = $uploadPathForDb;
    $caseRemarks = "Narrative file uploaded";
}

$actorUserId = isset($_SESSION['user_id']) ? (string)$_SESSION['user_id'] : null;

$conn->begin_transaction();
try {
    $statusId = getStatusId($conn, "Active", "Blotter");
    $levelId = getStatusId($conn, "Blotter Only", "BlotterLevel");
    $caseId = GenerateCaseID($conn);
    if (!$caseId) {
        throw new Exception("Failed to generate case ID.");
    }
    $blotterId = GenerateBlotterID($conn);
    if (!$blotterId) {
        throw new Exception("Failed to generate blotter ID.");
    }

    $stmtCase = $conn->prepare("
        INSERT INTO casereportstbl
            (case_id, resident_user_id, report_type, incident_date, incident_time, incident_place, complaint_type,
             case_details, case_remarks, case_status_id, case_level_id, user_id_official_update_by, user_id_official_reviewed_by, user_id_official_record_by)
        VALUES
            (?, NULL, 'Blotter', ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?)
    ");
    if (!$stmtCase) {
        throw new Exception("Prepare failed (case insert): " . $conn->error);
    }
    $stmtCase->bind_param(
        "sssssssiiss",
        $caseId,
        $incidentDate,
        $incidentTime,
        $incidentPlace,
        $complaintType,
        $caseDetails,
        $caseRemarks,
        $statusId,
        $levelId,
        $actorUserId,
        $actorUserId
    );
    $stmtCase->execute();
    $stmtCase->close();

    $stmtBlotter = $conn->prepare("
        INSERT INTO barangayblottertbl
            (blotter_id, case_id, blotter_number, logbook_id, date_filed, time_filed)
        VALUES
            (?, ?, ?, NULL, ?, ?)
    ");
    if (!$stmtBlotter) {
        throw new Exception("Prepare failed (blotter insert): " . $conn->error);
    }
    $stmtBlotter->bind_param("sssss", $blotterId, $caseId, $blotterNumber, $dateFiled, $timeFiled);
    $stmtBlotter->execute();
    $stmtBlotter->close();

    $stmtParticipant = $conn->prepare("
        INSERT INTO caseparticipantstbl
            (case_id, participant_role, lastname, firstname, middlename, suffix, contact_number, email, address, age, sex, remarks)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?)
    ");
    if (!$stmtParticipant) {
        throw new Exception("Prepare failed (participant insert): " . $conn->error);
    }

    $role = 'Complainant';
    $stmtParticipant->bind_param(
        "sssssssssss",
        $caseId,
        $role,
        $complainantLast,
        $complainantFirst,
        $complainantMiddle,
        $complainantSuffix,
        $complainantContact,
        $complainantAddress,
        $complainantAge,
        $complainantSex,
        $complainantRemarks
    );
    if (!$stmtParticipant->execute()) {
        throw new Exception("Failed to insert Complainant participant: " . ($stmtParticipant->error ?: $conn->error));
    }

    $role = 'Respondent';
    $stmtParticipant->bind_param(
        "sssssssssss",
        $caseId,
        $role,
        $respondentLast,
        $respondentFirst,
        $respondentMiddle,
        $respondentSuffix,
        $respondentContact,
        $respondentAddress,
        $respondentAge,
        $respondentSex,
        $respondentRemarks
    );
    if (!$stmtParticipant->execute()) {
        throw new Exception("Failed to insert Respondent participant: " . ($stmtParticipant->error ?: $conn->error));
    }
    $stmtParticipant->close();

    $conn->commit();

    $redirectBase = dirname($_SERVER['SCRIPT_NAME']);
    $redirectUrl = $redirectBase . "/../../Admin-End/Blotter/BlotterForm.php?success=1&case_id=" . $caseId;
    header("Location: " . $redirectUrl);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    if (isset($stmtCase) && $stmtCase instanceof mysqli_stmt) $stmtCase->close();
    if (isset($stmtBlotter) && $stmtBlotter instanceof mysqli_stmt) $stmtBlotter->close();
    if (isset($stmtParticipant) && $stmtParticipant instanceof mysqli_stmt) $stmtParticipant->close();

    http_response_code(500);
    exit("Failed to save blotter case: " . $e->getMessage());
}
