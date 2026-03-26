<?php
require_once __DIR__ . "/../General/security.php";
require_once __DIR__ . "/../General/connection.php";
require_once __DIR__ . "/../General/caseUserAccountForeignKeys.php";
require_once __DIR__ . "/../General/uniqueIDGenerate.php";

cuafk_ensure_case_useraccount_foreign_keys($conn);

function str_field($value): ?string
{
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function normalizeComplaintPhone($value): ?string
{
    $digits = preg_replace('/\D+/', '', trim((string)$value));
    if ($digits === '') {
        return null;
    }
    if (preg_match('/^9\d{9}$/', $digits)) {
        $digits = '0' . $digits;
    }
    return $digits;
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

function resolveSubjectKind($value, ?string $subjectName): string
{
    $allowed = ['Resident', 'NonResident', 'Business', 'Organization', 'Unknown', 'GeneralConcern'];
    $value = trim((string)$value);
    if (in_array($value, $allowed, true)) {
        return $value;
    }
    return classifySubjectKind($subjectName);
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

    $parts = preg_split('/\s+/', $baseName) ?: [];
    $parts = array_values(array_filter($parts, static fn ($part) => $part !== ''));
    if (empty($parts)) {
        return [null, null, null, str_field($suffix)];
    }
    if (count($parts) === 1) {
        $single = str_field($parts[0]);
        return [$single, $single, null, str_field($suffix)];
    }

    $lastname = str_field(array_pop($parts));
    $firstname = str_field(array_shift($parts));
    $middlename = !empty($parts) ? str_field(implode(' ', $parts)) : null;

    return [$lastname, $firstname, $middlename, str_field($suffix)];
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
    if (!$stmt->execute()) {
        $error = $stmt->error ?: $conn->error;
        $stmt->close();
        throw new Exception("Failed to insert {$role} participant: " . $error);
    }
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

function validateComplaintPhoneOrRedirect(string $path, $value, bool $required, string $label): ?string
{
    $phone = normalizeComplaintPhone($value);
    if ($phone === null) {
        if ($required) {
            redirectWithMessage($path, 'error', "{$label} is required.");
        }
        return null;
    }
    if (!preg_match('/^09\d{9}$/', $phone)) {
        redirectWithMessage($path, 'error', "{$label} must be in the format 09XXXXXXXXX.");
    }
    return $phone;
}

function parseStrictDate(string $value, DateTimeZone $timezone): ?DateTimeImmutable
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))) {
        return null;
    }

    return $date;
}

function parseStrictTime(string $value, DateTimeZone $timezone): ?DateTimeImmutable
{
    if (!preg_match('/^\d{2}:\d{2}$/', $value)) {
        return null;
    }

    $time = DateTimeImmutable::createFromFormat('!H:i', $value, $timezone);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$time || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))) {
        return null;
    }

    return $time;
}

function validateIncidentDateTimeOrRedirect(string $path, ?string $incidentDate, ?string $incidentTime): void
{
    if (!$incidentDate) {
        return;
    }

    $timezone = new DateTimeZone(date_default_timezone_get() ?: 'Asia/Manila');
    $now = new DateTimeImmutable('now', $timezone);
    $oldestAllowed = $now->sub(new DateInterval('P6M'));

    $dateOnly = parseStrictDate($incidentDate, $timezone);
    if (!$dateOnly) {
        redirectWithMessage($path, 'error', 'Incident date is invalid.');
    }

    if ($dateOnly < $oldestAllowed->setTime(0, 0)) {
        redirectWithMessage($path, 'error', 'Incident date must be within the past 6 months.');
    }

    if ($incidentTime) {
        $timeOnly = parseStrictTime($incidentTime, $timezone);
        if (!$timeOnly) {
            redirectWithMessage($path, 'error', 'Incident time is invalid.');
        }
        $incidentDateTime = $dateOnly->setTime((int)$timeOnly->format('H'), (int)$timeOnly->format('i'));
        if ($incidentDateTime > $now) {
            redirectWithMessage($path, 'error', 'Incident date and time cannot be in the future.');
        }
        if ($incidentDateTime < $oldestAllowed) {
            redirectWithMessage($path, 'error', 'Incident date and time must be within the past 6 months.');
        }
        return;
    }

    if ($dateOnly > $now->setTime(0, 0)) {
        redirectWithMessage($path, 'error', 'Incident date cannot be in the future.');
    }
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
$complainantPath = '/Resident-End/Complaints/ComplaintsForm.php';
$complainantContact = validateComplaintPhoneOrRedirect($complainantPath, $_POST['complainant_contact_number'] ?? '', true, 'Complainant contact number');
$complainantAge = str_field($_POST['complainant_age'] ?? '');
$complainantSex = str_field($_POST['complainant_sex'] ?? '');
$complainantAddress = str_field($_POST['complainant_address'] ?? '');

$subjectName = str_field($_POST['subject_name'] ?? '');
$subjectKind = resolveSubjectKind($_POST['subject_kind'] ?? '', $subjectName);
$subjectContact = validateComplaintPhoneOrRedirect($complainantPath, $_POST['subject_contact_number'] ?? '', false, 'Subject contact number');
$subjectAddress = str_field($_POST['subject_address'] ?? '');

$natureOfComplaint = str_field($_POST['nature_of_complaint'] ?? '');
$natureOther = str_field($_POST['nature_other'] ?? '');
$incidentDate = str_field($_POST['incident_date'] ?? '');
$incidentTime = str_field($_POST['incident_time'] ?? '');
$incidentLocation = str_field($_POST['incident_location'] ?? '');
$incidentNarration = str_field($_POST['incident_narration'] ?? '');

$witnessName = str_field($_POST['witness_name'] ?? '');
$witnessContact = validateComplaintPhoneOrRedirect($complainantPath, $_POST['witness_contact_number'] ?? '', false, 'Witness contact number');
$witnessAddress = str_field($_POST['witness_address'] ?? '');

$complaintType = $natureOfComplaint === 'Other' ? $natureOther : $natureOfComplaint;
$complaintType = str_field($complaintType);

if (!$complainantLast || !$complainantFirst || !$complainantAge || !$complainantSex || !$complainantContact || !$complainantAddress || !$subjectName || !$subjectAddress || !$complaintType || !$incidentDate || !$incidentLocation || !$incidentNarration) {
    redirectWithMessage($complainantPath, 'error', 'Missing required complaint fields.');
}

validateIncidentDateTimeOrRedirect($complainantPath, $incidentDate, $incidentTime);

$witnessSummaryParts = array_filter([
    $witnessName ? 'Name: ' . $witnessName : null,
    $witnessContact ? 'Contact: ' . $witnessContact : null,
    $witnessAddress ? 'Address: ' . $witnessAddress : null,
]);
$witnessSummary = !empty($witnessSummaryParts) ? implode(' | ', $witnessSummaryParts) : null;

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

    redirectWithMessage($complainantPath, 'success', 'Complaint submitted successfully.', [
        'case_id' => $caseId,
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    exit('Failed to submit complaint: ' . $e->getMessage());
}
