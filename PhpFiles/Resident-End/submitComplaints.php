<?php
require_once __DIR__ . "/../General/security.php";
require_once __DIR__ . "/../General/connection.php";
require_once __DIR__ . "/../General/uniqueIDGenerate.php";

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

function appendRemark(?string $existing, string $newNote): string
{
    $existing = trim((string)$existing);
    $newNote = trim($newNote);
    if ($existing === '') {
        return $newNote;
    }
    if ($newNote === '') {
        return $existing;
    }
    return $existing . "\n" . $newNote;
}

function classifySubjectKind(?string $subjectName): string
{
    $value = strtolower(trim((string)$subjectName));
    if ($value === '') {
        return 'Unknown';
    }
    if (str_contains($value, 'unknown') || $value === 'n/a' || $value === 'na') {
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
        return [
            str_field($lastname),
            str_field($firstname),
            str_field($middlename),
            str_field($suffix),
        ];
    }

    return [str_field($baseName), null, null, str_field($suffix)];
}

function insertParticipant(
    mysqli $conn,
    string $caseId,
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

    $stmt->bind_param(
        "sssssssssss",
        $caseId,
        $role,
        $lastname,
        $firstname,
        $middlename,
        $suffix,
        $contactNumber,
        $address,
        $age,
        $sex,
        $remarks
    );
    $stmt->execute();
    $stmt->close();
}

function logCaseUpdate(mysqli $conn, string $caseId, string $entry, ?string $userId): void
{
    if (!tableExists($conn, 'caseupdateslogtbl')) {
        return;
    }

    $stmt = $conn->prepare("
        INSERT INTO caseupdateslogtbl (case_id, log_entry, logged_by_user_id)
        VALUES (?, ?, ?)
    ");
    if (!$stmt) {
        return;
    }

    $stmt->bind_param("sss", $caseId, $entry, $userId);
    $stmt->execute();
    $stmt->close();
}

function redirectWithMessage(string $path, string $type, string $message, array $extra = []): void
{
    $query = array_merge([$type => $message], $extra);
    header('Location: ' . appUrl($path) . '?' . http_build_query($query));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$action = trim((string)($_POST['action'] ?? 'submit_complaint'));
$actorUserId = (string)($_SESSION['user_id'] ?? '');

if ($action !== 'submit_complaint') {
    http_response_code(400);
    exit('Unknown complaint action.');
}

requireRoleSession(['Resident'], false);
verifyCsrfToken(false);

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

$witnessName = str_field($_POST['witness_name'] ?? '');
$witnessContact = str_field($_POST['witness_contact_number'] ?? '');
$witnessAddress = str_field($_POST['witness_address'] ?? '');

$complaintType = $natureOfComplaint === 'Other' ? $natureOther : $natureOfComplaint;
$complaintType = str_field($complaintType);

if (!$complainantLast || !$complainantFirst || !$complainantAge || !$complainantSex || !$complainantContact || !$complainantAddress || !$subjectName || !$subjectAddress || !$complaintType || !$incidentDate || !$incidentLocation || !$incidentNarration) {
    redirectWithMessage('/Resident-End/Complaints/ComplaintsForm.php', 'error', 'Missing required complaint fields.');
}

$todayIso = date('Y-m-d');
if ($incidentDate > $todayIso) {
    redirectWithMessage('/Resident-End/Complaints/ComplaintsForm.php', 'error', 'Incident date cannot be in the future.');
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

$residentUserId = $actorUserId !== '' ? $actorUserId : null;

$conn->begin_transaction();
try {
    $lookupIds = ensureComplaintLookups($conn);
    $statusId = (int)$lookupIds['status']['Pending'];
    $levelId = (int)$lookupIds['level']['Complaint Only'];
    $caseId = GenerateCaseID($conn);
    if (!$caseId) {
        throw new Exception("Failed to generate case ID.");
    }
    $complaintId = GenerateComplaintID($conn);
    if (!$complaintId) {
        throw new Exception("Failed to generate complaint ID.");
    }

    $caseRemarks = 'Complaint submitted via resident portal.';
    $stmtCase = $conn->prepare("
        INSERT INTO casereportstbl
            (case_id, resident_user_id, report_type, incident_date, incident_time, incident_place, complaint_type,
             case_details, case_remarks, case_status_id, case_level_id, user_id_official_update_by, user_id_official_reviewed_by, user_id_official_record_by)
        VALUES
            (?, ?, 'Complaint', ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?)
    ");
    if (!$stmtCase) {
        throw new Exception("Prepare failed (case insert): " . $conn->error);
    }

    $stmtCase->bind_param(
        "ssssssssiis",
        $caseId,
        $residentUserId,
        $incidentDate,
        $incidentTime,
        $incidentLocation,
        $complaintType,
        $incidentNarration,
        $caseRemarks,
        $statusId,
        $levelId,
        $actorUserId
    );
    $stmtCase->execute();
    $stmtCase->close();

    $stmtComplaint = $conn->prepare("
        INSERT INTO complaintstbl
            (complaint_id, case_id, complaint_origin, subject_kind, subject_display_name, subject_contact_number, subject_address, witness_summary)
        VALUES
            (?, ?, 'ResidentPortal', ?, ?, ?, ?, ?)
    ");
    if (!$stmtComplaint) {
        throw new Exception("Prepare failed (complaint insert): " . $conn->error);
    }

    $stmtComplaint->bind_param(
        "sssssss",
        $complaintId,
        $caseId,
        $subjectKind,
        $subjectName,
        $subjectContact,
        $subjectAddress,
        $witnessSummary
    );
    $stmtComplaint->execute();
    $stmtComplaint->close();

    insertParticipant(
        $conn,
        $caseId,
        'Complainant',
        $complainantLast,
        $complainantFirst,
        $complainantMiddle,
        $complainantSuffix,
        $complainantContact,
        $complainantAddress,
        $complainantAge,
        $complainantSex,
        null
    );

    insertParticipant(
        $conn,
        $caseId,
        'Respondent',
        $respondentLast,
        $respondentFirst,
        $respondentMiddle,
        $respondentSuffix,
        $subjectContact,
        $subjectAddress,
        null,
        null,
        'Complaint subject recorded from resident portal submission.'
    );

    if ($witnessName || $witnessContact || $witnessAddress) {
        insertParticipant(
            $conn,
            $caseId,
            'Witness',
            $witnessLast,
            $witnessFirst,
            $witnessMiddle,
            $witnessSuffix,
            $witnessContact,
            $witnessAddress,
            null,
            null,
            'Witness details recorded from complaint submission.'
        );
    }

    logCaseUpdate($conn, $caseId, 'Complaint submitted through resident portal.', $actorUserId ?: null);
    $conn->commit();

    redirectWithMessage('/Resident-End/Complaints/ComplaintsForm.php', 'success', 'Complaint submitted successfully.', [
        'case_id' => $caseId,
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    exit('Failed to submit complaint: ' . $e->getMessage());
}
