<?php
require_once __DIR__ . "/../General/security.php";
require_once __DIR__ . "/../General/connection.php";

requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin', 'Employee'], false);
verifyCsrfToken(false);

function str_field($value): ?string
{
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function tableExists(mysqli $conn, string $tableName): bool
{
    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("s", $tableName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    return !empty($row);
}

function getStatusId(mysqli $conn, string $name, string $type): ?int
{
    $stmt = $conn->prepare("
        SELECT status_id
        FROM statuslookuptbl
        WHERE status_name = ? AND status_type = ?
        ORDER BY status_id ASC
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("ss", $name, $type);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return isset($row['status_id']) ? (int)$row['status_id'] : null;
}

function ensureStatusId(mysqli $conn, string $name, string $type): int
{
    $existing = getStatusId($conn, $name, $type);
    if ($existing !== null) {
        return $existing;
    }

    $stmt = $conn->prepare("INSERT INTO statuslookuptbl (status_name, status_type) VALUES (?, ?)");
    if (!$stmt) {
        throw new Exception("Failed to create status lookup entry.");
    }
    $stmt->bind_param("ss", $name, $type);
    $stmt->execute();
    $statusId = (int)$conn->insert_id;
    $stmt->close();
    return $statusId;
}

function ensureComplaintLookups(mysqli $conn): array
{
    $statusIds = [];
    foreach (['Pending', 'Resolved', 'Dropped', 'Endorsed'] as $statusName) {
        $statusIds[$statusName] = ensureStatusId($conn, $statusName, 'Complaint');
    }

    $levelIds = [];
    foreach (['Complaint Only', 'Endorsed to Blotter'] as $levelName) {
        $levelIds[$levelName] = ensureStatusId($conn, $levelName, 'ComplaintLevel');
    }

    return ['status' => $statusIds, 'level' => $levelIds];
}

function classifySubjectKind(?string $subjectName): string
{
    $value = strtolower(trim((string)$subjectName));
    if ($value === '' || str_contains($value, 'unknown') || $value === 'n/a' || $value === 'na') {
        return 'Unknown';
    }
    foreach (['store', 'shop', 'enterprise', 'company', 'corp', 'corporation', 'business', 'inc'] as $keyword) {
        if (str_contains($value, $keyword)) {
            return 'Business';
        }
    }
    foreach (['organization', 'organisation', 'association', 'committee', 'group', 'club'] as $keyword) {
        if (str_contains($value, $keyword)) {
            return 'Organization';
        }
    }
    return 'GeneralConcern';
}

function parseParticipantName(?string $rawName): array
{
    $rawName = trim((string)$rawName);
    if ($rawName === '') {
        return [null, null, null, null];
    }

    $suffix = null;
    $baseName = $rawName;
    if (preg_match('/\s+(Jr\.|Sr\.|III|IV)$/i', $rawName, $matches)) {
        $suffix = $matches[1];
        $baseName = trim((string)preg_replace('/\s+(Jr\.|Sr\.|III|IV)$/i', '', $rawName));
    }

    if (str_contains($baseName, ',')) {
        [$lastname, $givenNames] = array_pad(array_map('trim', explode(',', $baseName, 2)), 2, '');
        $parts = preg_split('/\s+/', trim($givenNames)) ?: [];
        $firstname = array_shift($parts) ?: null;
        $middlename = !empty($parts) ? implode(' ', $parts) : null;
        return [str_field($lastname), str_field($firstname), str_field($middlename), str_field($suffix)];
    }

    return [str_field($baseName), null, null, str_field($suffix)];
}

function insertParticipant(
    mysqli $conn,
    int $caseId,
    string $role,
    ?string $lastname,
    ?string $firstname,
    ?string $middlename,
    ?string $suffix,
    ?string $contactNumber,
    ?string $address,
    ?string $age,
    ?string $sex,
    ?string $remarks
): void {
    $stmt = $conn->prepare("
        INSERT INTO caseparticipantstbl
            (case_id, participant_role, lastname, firstname, middlename, suffix, contact_number, email, address, age, sex, remarks)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        throw new Exception("Prepare failed (participant insert): " . $conn->error);
    }

    $stmt->bind_param("issssssssss", $caseId, $role, $lastname, $firstname, $middlename, $suffix, $contactNumber, $address, $age, $sex, $remarks);
    $stmt->execute();
    $stmt->close();
}

function redirectWithMessage(string $type, string $message, array $extra = []): void
{
    $query = array_merge([$type => $message], $extra);
    header('Location: ' . appUrl('/Admin-End/Complaints/ComplaintForm.php') . '?' . http_build_query($query));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

if (!tableExists($conn, 'complaintstbl')) {
    http_response_code(500);
    exit('Complaint table is not available. Run the complaint migration first.');
}

$complainantLast = str_field($_POST['complainant_last_name'] ?? '');
$complainantFirst = str_field($_POST['complainant_first_name'] ?? '');
$complainantMiddle = str_field($_POST['complainant_middle_name'] ?? '');
$complainantSuffix = str_field($_POST['complainant_suffix'] ?? '');
$complainantContact = str_field($_POST['complainant_contact_number'] ?? '');
$complainantAge = str_field($_POST['complainant_age'] ?? '');
$complainantSex = str_field($_POST['complainant_sex'] ?? '');
$complainantAddress = str_field($_POST['complainant_address'] ?? '');

$subjectName = str_field($_POST['subject_name'] ?? '');
$subjectContact = str_field($_POST['subject_contact_number'] ?? '');
$subjectAddress = str_field($_POST['subject_address'] ?? '');

$natureOfComplaint = str_field($_POST['nature_of_complaint'] ?? '');
$natureOther = str_field($_POST['nature_other'] ?? '');
$incidentDate = str_field($_POST['incident_date'] ?? '');
$incidentTime = str_field($_POST['incident_time'] ?? '');
$incidentLocation = str_field($_POST['incident_location'] ?? '');
$incidentNarration = str_field($_POST['incident_narration'] ?? '');
$initialNotes = str_field($_POST['initial_notes'] ?? '');

$witnessName = str_field($_POST['witness_name'] ?? '');
$witnessContact = str_field($_POST['witness_contact_number'] ?? '');
$witnessAddress = str_field($_POST['witness_address'] ?? '');

$complaintType = $natureOfComplaint === 'Other' ? $natureOther : $natureOfComplaint;
$complaintType = str_field($complaintType);

if (!$complainantLast || !$complainantFirst || !$complainantAge || !$complainantSex || !$complainantContact || !$complainantAddress || !$subjectName || !$subjectAddress || !$complaintType || !$incidentDate || !$incidentLocation || !$incidentNarration) {
    redirectWithMessage('error', 'Missing required complaint fields.');
}

if ($incidentDate > date('Y-m-d')) {
    redirectWithMessage('error', 'Incident date cannot be in the future.');
}

$witnessSummaryParts = array_filter([
    $witnessName ? 'Name: ' . $witnessName : null,
    $witnessContact ? 'Contact: ' . $witnessContact : null,
    $witnessAddress ? 'Address: ' . $witnessAddress : null,
]);
$witnessSummary = !empty($witnessSummaryParts) ? implode(' | ', $witnessSummaryParts) : null;
$subjectKind = classifySubjectKind($subjectName);
[$respondentLast, $respondentFirst, $respondentMiddle, $respondentSuffix] = parseParticipantName($subjectName);
[$witnessLast, $witnessFirst, $witnessMiddle, $witnessSuffix] = parseParticipantName($witnessName);
$actorUserId = (string)($_SESSION['user_id'] ?? '');
$residentUserId = null;

$conn->begin_transaction();
try {
    $lookupIds = ensureComplaintLookups($conn);
    $statusId = (int)$lookupIds['status']['Pending'];
    $levelId = (int)$lookupIds['level']['Complaint Only'];

    $caseRemarks = 'Complaint encoded by admin.';
    $stmtCase = $conn->prepare("
        INSERT INTO casereportstbl
            (resident_user_id, report_type, incident_date, incident_time, incident_place, complaint_type,
             case_details, case_remarks, case_status_id, case_level_id, user_id_official_update_by, user_id_official_reviewed_by, user_id_official_record_by)
        VALUES
            (?, 'Complaint', ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?)
    ");
    if (!$stmtCase) {
        throw new Exception("Prepare failed (case insert): " . $conn->error);
    }
    $stmtCase->bind_param("sssssssiis", $residentUserId, $incidentDate, $incidentTime, $incidentLocation, $complaintType, $incidentNarration, $caseRemarks, $statusId, $levelId, $actorUserId);
    $stmtCase->execute();
    $caseId = (int)$conn->insert_id;
    $stmtCase->close();

    $stmtComplaint = $conn->prepare("
        INSERT INTO complaintstbl
            (case_id, complaint_origin, subject_kind, subject_display_name, subject_contact_number, subject_address, witness_summary, intake_notes)
        VALUES
            (?, 'AdminEncoded', ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmtComplaint) {
        throw new Exception("Prepare failed (complaint insert): " . $conn->error);
    }
    $stmtComplaint->bind_param("issssss", $caseId, $subjectKind, $subjectName, $subjectContact, $subjectAddress, $witnessSummary, $initialNotes);
    $stmtComplaint->execute();
    $stmtComplaint->close();

    insertParticipant($conn, $caseId, 'Complainant', $complainantLast, $complainantFirst, $complainantMiddle, $complainantSuffix, $complainantContact, $complainantAddress, $complainantAge, $complainantSex, 'Complaint encoded by admin.');
    insertParticipant($conn, $caseId, 'Respondent', $respondentLast, $respondentFirst, $respondentMiddle, $respondentSuffix, $subjectContact, $subjectAddress, null, null, 'Complaint subject recorded from admin complaint entry.');

    if ($witnessName || $witnessContact || $witnessAddress) {
        insertParticipant($conn, $caseId, 'Witness', $witnessLast, $witnessFirst, $witnessMiddle, $witnessSuffix, $witnessContact, $witnessAddress, null, null, 'Witness details recorded from admin complaint entry.');
    }

    $conn->commit();
    redirectWithMessage('success', 'Complaint submitted successfully.', ['case_id' => $caseId]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    exit('Failed to submit complaint: ' . $e->getMessage());
}
