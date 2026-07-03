<?php
require_once __DIR__ . '/../General/security.php';
require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/caseUserAccountForeignKeys.php';
require_once __DIR__ . '/../General/complaintTypeDetails.php';
require_once __DIR__ . '/../General/recaptcha.php';
require_once __DIR__ . '/../General/uniqueIDGenerate.php';

cuafk_ensure_case_useraccount_foreign_keys($conn);

function guestComplaintStrField($value): ?string
{
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function guestComplaintNormalizePhone($value): ?string
{
    $digits = preg_replace('/\D+/', '', trim((string)$value));
    if ($digits === '') {
        return null;
    }
    if (preg_match('/^639\d{9}$/', $digits)) {
        $digits = substr($digits, 2);
    }
    if (preg_match('/^9\d{9}$/', $digits)) {
        $digits = '0' . $digits;
    }
    return $digits;
}

function guestComplaintOtpPhoneKey($value): ?string
{
    $normalized = guestComplaintNormalizePhone($value);
    if ($normalized === null || !preg_match('/^09\d{9}$/', $normalized)) {
        return null;
    }

    return substr($normalized, 1);
}

function guestComplaintTableExists(mysqli $conn, string $tableName): bool
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

function guestComplaintEnumOptions(mysqli $conn, string $tableName, string $columnName): array
{
    static $cache = [];
    $cacheKey = strtolower($tableName . '.' . $columnName);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $stmt = $conn->prepare("
        SELECT COLUMN_TYPE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return $cache[$cacheKey] = [];
    }

    $stmt->bind_param("ss", $tableName, $columnName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $columnType = trim((string)($row['COLUMN_TYPE'] ?? ''));
    if (!preg_match('/^enum\((.*)\)$/i', $columnType, $matches)) {
        return $cache[$cacheKey] = [];
    }

    $options = str_getcsv($matches[1], ',', "'", "\\");
    return $cache[$cacheKey] = array_values(array_filter(array_map(
        static fn($value) => trim((string)$value),
        is_array($options) ? $options : []
    ), static fn($value) => $value !== ''));
}

function guestComplaintResolveOrigin(mysqli $conn): string
{
    $options = guestComplaintEnumOptions($conn, 'complaintstbl', 'complaint_origin');
    if (in_array('GuestPortal', $options, true)) {
        return 'GuestPortal';
    }
    if (in_array('ResidentPortal', $options, true)) {
        return 'ResidentPortal';
    }
    if (!empty($options)) {
        return (string)$options[0];
    }

    return 'ResidentPortal';
}

function guestComplaintResolveSubjectKind(mysqli $conn): string
{
    $options = guestComplaintEnumOptions($conn, 'complaintstbl', 'subject_kind');
    foreach (['Unknown', 'GeneralConcern', 'NonResident', 'Resident', 'Business', 'Organization'] as $candidate) {
        if (in_array($candidate, $options, true)) {
            return $candidate;
        }
    }
    if (!empty($options)) {
        return (string)$options[0];
    }

    return 'Unknown';
}

function guestComplaintClearTrackerCache(): void
{
    $pattern = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'barangaysanjose_complaint_tracker_*.cache';
    $matches = glob($pattern);
    if (!is_array($matches)) {
        return;
    }

    foreach ($matches as $path) {
        if (is_string($path) && is_file($path)) {
            @unlink($path);
        }
    }
}

function guestComplaintGetStatusId(mysqli $conn, string $name, string $type): ?int
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

function guestComplaintEnsureStatusId(mysqli $conn, string $name, string $type): int
{
    $existing = guestComplaintGetStatusId($conn, $name, $type);
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

function guestComplaintEnsureLookups(mysqli $conn): array
{
    $statusIds = [];
    foreach (['Pending', 'Resolved', 'Dropped', 'Endorsed'] as $statusName) {
        $statusIds[$statusName] = guestComplaintEnsureStatusId($conn, $statusName, 'Complaint');
    }

    $levelIds = [];
    foreach (['Complaint Only', 'Endorsed to Blotter'] as $levelName) {
        $levelIds[$levelName] = guestComplaintEnsureStatusId($conn, $levelName, 'ComplaintLevel');
    }

    return ['status' => $statusIds, 'level' => $levelIds];
}

function guestComplaintLogCaseUpdate(mysqli $conn, string $caseId, string $entry, ?string $userId): void
{
    if (!guestComplaintTableExists($conn, 'caseupdateslogtbl')) {
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

function guestComplaintRedirectWithMessage(string $path, string $type, string $message, array $extra = []): void
{
    $query = array_merge([$type => $message], $extra);
    header('Location: ' . appUrl($path) . '?' . http_build_query($query));
    exit;
}

function guestComplaintValidatePhoneOrRedirect(string $path, $value, bool $required, string $label): ?string
{
    $phone = guestComplaintNormalizePhone($value);
    if ($phone === null) {
        if ($required) {
            guestComplaintRedirectWithMessage($path, 'error', "{$label} is required.");
        }
        return null;
    }
    if (!preg_match('/^09\d{9}$/', $phone)) {
        guestComplaintRedirectWithMessage($path, 'error', "{$label} must use +63 followed by 10 digits in the format 9XXXXXXXXX.");
    }
    return $phone;
}

function guestComplaintParseStrictDate(string $value, DateTimeZone $timezone): ?DateTimeImmutable
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

function guestComplaintParseStrictTime(string $value, DateTimeZone $timezone): ?DateTimeImmutable
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

function guestComplaintValidateIncidentDateTimeOrRedirect(string $path, ?string $incidentDate, ?string $incidentTime): void
{
    if (!$incidentDate) {
        return;
    }

    $timezone = new DateTimeZone(date_default_timezone_get() ?: 'Asia/Manila');
    $now = new DateTimeImmutable('now', $timezone);
    $oldestAllowed = $now->sub(new DateInterval('P6M'));

    $dateOnly = guestComplaintParseStrictDate($incidentDate, $timezone);
    if (!$dateOnly) {
        guestComplaintRedirectWithMessage($path, 'error', 'Incident date is invalid.');
    }

    if ($dateOnly < $oldestAllowed->setTime(0, 0)) {
        guestComplaintRedirectWithMessage($path, 'error', 'Incident date must be within the past 6 months.');
    }

    if ($incidentTime) {
        $timeOnly = guestComplaintParseStrictTime($incidentTime, $timezone);
        if (!$timeOnly) {
            guestComplaintRedirectWithMessage($path, 'error', 'Incident time is invalid.');
        }
        $incidentDateTime = $dateOnly->setTime((int)$timeOnly->format('H'), (int)$timeOnly->format('i'));
        if ($incidentDateTime > $now) {
            guestComplaintRedirectWithMessage($path, 'error', 'Incident date and time cannot be in the future.');
        }
        if ($incidentDateTime < $oldestAllowed) {
            guestComplaintRedirectWithMessage($path, 'error', 'Incident date and time must be within the past 6 months.');
        }
        return;
    }

    if ($dateOnly > $now->setTime(0, 0)) {
        guestComplaintRedirectWithMessage($path, 'error', 'Incident date cannot be in the future.');
    }
}

function guestComplaintStoreImageUploadsOrRedirect(string $path, array $files, string $caseId): array
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        return [];
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxBytes = 5 * 1024 * 1024;
    $projectRoot = dirname(__DIR__, 2);
    $relativeFolder = '/UnifiedFileAttachment/ComplaintEvidence/' . date('Y/m');
    $absoluteFolder = $projectRoot . $relativeFolder;

    if (!is_dir($absoluteFolder) && !mkdir($absoluteFolder, 0775, true) && !is_dir($absoluteFolder)) {
        guestComplaintRedirectWithMessage($path, 'error', 'Failed to prepare the complaint upload folder.');
    }

    $saved = [];
    $fileCount = min(3, count($files['name']));
    for ($index = 0; $index < $fileCount; $index++) {
        $errorCode = (int)($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($errorCode !== UPLOAD_ERR_OK) {
            guestComplaintRedirectWithMessage($path, 'error', 'One of the complaint image uploads failed. Please try again.');
        }

        $originalName = trim((string)($files['name'][$index] ?? ''));
        $tmpPath = (string)($files['tmp_name'][$index] ?? '');
        $size = (int)($files['size'][$index] ?? 0);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mimeType = strtolower((string)($files['type'][$index] ?? ''));

        if (!in_array($extension, $allowedExtensions, true) || !in_array($mimeType, $allowedMimeTypes, true)) {
            guestComplaintRedirectWithMessage($path, 'error', 'Complaint images must be JPG, JPEG, PNG, or WEBP.');
        }
        if ($size <= 0 || $size > $maxBytes) {
            guestComplaintRedirectWithMessage($path, 'error', 'Each complaint image must be 5 MB or smaller.');
        }
        if (!is_uploaded_file($tmpPath)) {
            guestComplaintRedirectWithMessage($path, 'error', 'Invalid complaint image upload detected.');
        }

        $targetName = sprintf(
            '%s_guest_%d_%s.%s',
            preg_replace('/[^A-Za-z0-9]/', '', $caseId),
            $index + 1,
            bin2hex(random_bytes(4)),
            $extension
        );
        $targetPath = $absoluteFolder . '/' . $targetName;
        if (!move_uploaded_file($tmpPath, $targetPath)) {
            guestComplaintRedirectWithMessage($path, 'error', 'Failed to save one of the complaint images.');
        }
        @chmod($targetPath, 0664);

        $saved[] = [
            'name' => $originalName !== '' ? $originalName : ('complaint-image.' . $extension),
            'path' => $relativeFolder . '/' . $targetName,
            'type' => $mimeType,
        ];
    }

    return $saved;
}

function guestComplaintCollectWitnessesOrRedirect(string $path, array $source): array
{
    $hasWitnesses = trim((string)($source['has_witnesses'] ?? ''));
    if (!in_array($hasWitnesses, ['Yes', 'No'], true)) {
        guestComplaintRedirectWithMessage($path, 'error', 'Please select whether there is a witness.');
    }
    if ($hasWitnesses === 'No') {
        return [];
    }

    $lastNames = is_array($source['witness_last_name'] ?? null) ? $source['witness_last_name'] : [];
    $firstNames = is_array($source['witness_first_name'] ?? null) ? $source['witness_first_name'] : [];
    $middleNames = is_array($source['witness_middle_name'] ?? null) ? $source['witness_middle_name'] : [];
    $suffixes = is_array($source['witness_suffix'] ?? null) ? $source['witness_suffix'] : [];
    $contacts = is_array($source['witness_contact_number'] ?? null) ? $source['witness_contact_number'] : [];
    $addresses = is_array($source['witness_address'] ?? null) ? $source['witness_address'] : [];

    $witnesses = [];
    for ($index = 0; $index < 3; $index++) {
        $last = guestComplaintStrField($lastNames[$index] ?? '');
        $first = guestComplaintStrField($firstNames[$index] ?? '');
        $middle = guestComplaintStrField($middleNames[$index] ?? '');
        $suffix = guestComplaintStrField($suffixes[$index] ?? '');
        $contactRaw = $contacts[$index] ?? '';
        $address = guestComplaintStrField($addresses[$index] ?? '');
        $hasAnyValue = $last || $first || $middle || $suffix || trim((string)$contactRaw) !== '' || $address;

        if ($index === 0 && !$hasAnyValue) {
            guestComplaintRedirectWithMessage($path, 'error', 'Please enter at least one witness.');
        }
        if (!$hasAnyValue) {
            continue;
        }
        if (!$last || !$first) {
            guestComplaintRedirectWithMessage($path, 'error', 'Witness last name and first name are required.');
        }

        $contact = guestComplaintValidatePhoneOrRedirect($path, $contactRaw, true, 'Witness contact number');
        $witnesses[] = [
            'lastname' => $last,
            'firstname' => $first,
            'middlename' => $middle,
            'suffix' => $suffix,
            'contact_number' => $contact,
            'address' => $address,
        ];
    }

    return $witnesses;
}

function guestComplaintBuildWitnessSummary(array $witnesses): ?string
{
    if ($witnesses === []) {
        return null;
    }

    $parts = [];
    foreach ($witnesses as $index => $witness) {
        $fullName = trim(implode(' ', array_filter([
            $witness['firstname'] ?? '',
            $witness['middlename'] ?? '',
            $witness['lastname'] ?? '',
            $witness['suffix'] ?? '',
        ])));
        $line = array_filter([
            'Witness ' . ($index + 1) . ': ' . ($fullName !== '' ? $fullName : 'Unnamed'),
            !empty($witness['contact_number']) ? 'Contact: ' . $witness['contact_number'] : null,
            !empty($witness['address']) ? 'Address: ' . $witness['address'] : null,
        ]);
        $parts[] = implode(' | ', $line);
    }

    return implode("\n", $parts);
}

function guestComplaintInsertParticipant(
    mysqli $conn,
    string $caseId,
    string $role,
    ?string $lastname,
    ?string $firstname,
    ?string $middlename,
    ?string $suffix,
    ?string $contactNumber,
    ?string $email,
    ?string $address,
    ?string $age,
    ?string $sex,
    ?string $remarks
): void {
    $stmt = $conn->prepare("
        INSERT INTO caseparticipantstbl
            (case_id, participant_role, lastname, firstname, middlename, suffix, contact_number, email, address, age, sex, remarks)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        throw new Exception("Prepare failed (participant insert): " . $conn->error);
    }

    $stmt->bind_param(
        "ssssssssssss",
        $caseId,
        $role,
        $lastname,
        $firstname,
        $middlename,
        $suffix,
        $contactNumber,
        $email,
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

function guestComplaintFindActiveByPhone(mysqli $conn, string $phone): ?array
{
    if (
        !guestComplaintTableExists($conn, 'casereportstbl') ||
        !guestComplaintTableExists($conn, 'complaintstbl') ||
        !guestComplaintTableExists($conn, 'caseparticipantstbl')
    ) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT
            ct.complaint_id,
            c.case_id,
            COALESCE(s.status_name, 'Pending') AS status_name
        FROM caseparticipantstbl cp
        INNER JOIN casereportstbl c ON c.case_id = cp.case_id
        INNER JOIN complaintstbl ct ON ct.case_id = c.case_id
        LEFT JOIN statuslookuptbl s ON s.status_id = c.case_status_id
        WHERE c.report_type = 'Complaint'
          AND cp.participant_role = 'Complainant'
          AND cp.contact_number = ?
          AND LOWER(COALESCE(s.status_name, 'pending')) NOT IN ('resolved', 'dropped')
        ORDER BY c.case_id DESC
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return is_array($row) ? $row : null;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$action = trim((string)($_POST['action'] ?? 'submit_complaint'));
if ($action !== 'submit_complaint') {
    http_response_code(400);
    exit('Unknown complaint action.');
}

verifyCsrfToken(false);

$complainantPath = '/Guest-End/complaints.php';

if (!guestComplaintTableExists($conn, 'complaintstbl')) {
    http_response_code(500);
    exit('Complaint table is not available. Run the complaint migration first.');
}

$complainantLast = guestComplaintStrField($_POST['complainant_last_name'] ?? '');
$complainantFirst = guestComplaintStrField($_POST['complainant_first_name'] ?? '');
$complainantMiddle = guestComplaintStrField($_POST['complainant_middle_name'] ?? '');
$complainantSuffix = guestComplaintStrField($_POST['complainant_suffix'] ?? '');
$complainantContact = guestComplaintValidatePhoneOrRedirect($complainantPath, $_POST['complainant_contact_number'] ?? '', true, 'Complainant contact number');
$complainantEmail = guestComplaintStrField($_POST['complainant_email'] ?? '');
$complainantAge = guestComplaintStrField($_POST['complainant_age'] ?? '');
$complainantSex = guestComplaintStrField($_POST['complainant_sex'] ?? '');
$complainantAddress = guestComplaintStrField($_POST['complainant_address'] ?? '');

$natureOfComplaint = guestComplaintStrField($_POST['nature_of_complaint'] ?? '');
$natureOther = guestComplaintStrField($_POST['nature_other'] ?? '');
$incidentDate = guestComplaintStrField($_POST['incident_date'] ?? '');
$incidentTime = guestComplaintStrField($_POST['incident_time'] ?? '');
$incidentLocation = guestComplaintStrField($_POST['incident_location'] ?? '');
$incidentAreaNumber = guestComplaintStrField($_POST['incident_area_number'] ?? '');
$incidentNarration = guestComplaintStrField($_POST['incident_narration'] ?? '');
$witnesses = guestComplaintCollectWitnessesOrRedirect($complainantPath, $_POST);

try {
    $complaintTypeMeta = complaintTypeValidateAndCollect($natureOfComplaint, $natureOther, $_POST);
} catch (InvalidArgumentException $e) {
    guestComplaintRedirectWithMessage($complainantPath, 'error', $e->getMessage());
}
$complaintType = guestComplaintStrField($complaintTypeMeta['complaint_type'] ?? '');

if (!$complainantLast || !$complainantFirst || !$complainantAge || !$complainantSex || !$complainantContact || !$complainantAddress || !$complaintType || !$incidentDate || !$incidentLocation || !$incidentAreaNumber || !$incidentNarration) {
    guestComplaintRedirectWithMessage($complainantPath, 'error', 'Missing required complaint fields.');
}

$activeComplaint = guestComplaintFindActiveByPhone($conn, $complainantContact);
if (is_array($activeComplaint)) {
    $reference = trim((string)($activeComplaint['complaint_id'] ?? $activeComplaint['case_id'] ?? ''));
    $referenceText = $reference !== '' ? " Reference: {$reference}." : '';
    guestComplaintRedirectWithMessage(
        $complainantPath,
        'error',
        'This mobile number already has an active complaint under review. Please wait until it is completed before submitting another one.' . $referenceText
    );
}

$otpSession = $_SESSION['guest_complaint_otp_verified'] ?? null;
$otpPhoneKey = guestComplaintOtpPhoneKey($complainantContact);
if (
    !is_array($otpSession)
    || !isset($otpSession['phone'], $otpSession['verified_at'])
    || !is_string($otpSession['phone'])
    || $otpPhoneKey === null
    || !hash_equals((string)$otpSession['phone'], $otpPhoneKey)
    || (time() - (int)$otpSession['verified_at']) > 900
) {
    unset($_SESSION['guest_complaint_otp_verified']);
    guestComplaintRedirectWithMessage($complainantPath, 'error', 'Please verify your mobile number through OTP before submitting your complaint.');
}

guestComplaintValidateIncidentDateTimeOrRedirect($complainantPath, $incidentDate, $incidentTime);
$incidentPlace = trim($incidentAreaNumber . ' - ' . $incidentLocation);
$witnessSummary = guestComplaintBuildWitnessSummary($witnesses);

$conn->begin_transaction();
try {
    $lookupIds = guestComplaintEnsureLookups($conn);
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

    $imageAttachments = guestComplaintStoreImageUploadsOrRedirect($complainantPath, $_FILES['complaint_images'] ?? [], $caseId);
    $caseDetails = complaintTypeBuildCaseDetails($incidentNarration, $complaintTypeMeta, [
        'incident_area_number' => $incidentAreaNumber,
        'attachments' => $imageAttachments,
    ]);

    $caseRemarks = 'Complaint submitted via guest portal.';
    $recordedByUserId = null;
    $residentUserId = null;
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
        $incidentPlace,
        $complaintType,
        $caseDetails,
        $caseRemarks,
        $statusId,
        $levelId,
        $recordedByUserId
    );
    if (!$stmtCase->execute()) {
        $error = $stmtCase->error ?: $conn->error;
        $stmtCase->close();
        throw new Exception("Failed to insert complaint case: " . $error);
    }
    $stmtCase->close();

    $complaintOrigin = guestComplaintResolveOrigin($conn);
    $subjectName = 'Not specified';
    $subjectKind = guestComplaintResolveSubjectKind($conn);
    $subjectContact = null;
    $subjectAddress = null;

    $stmtComplaint = $conn->prepare("
        INSERT INTO complaintstbl
            (complaint_id, case_id, complaint_origin, subject_kind, subject_display_name, subject_contact_number, subject_address, witness_summary)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmtComplaint) {
        throw new Exception("Prepare failed (complaint insert): " . $conn->error);
    }

    $stmtComplaint->bind_param(
        "ssssssss",
        $complaintId,
        $caseId,
        $complaintOrigin,
        $subjectKind,
        $subjectName,
        $subjectContact,
        $subjectAddress,
        $witnessSummary
    );
    if (!$stmtComplaint->execute()) {
        $error = $stmtComplaint->error ?: $conn->error;
        $stmtComplaint->close();
        throw new Exception("Failed to insert complaint details: " . $error);
    }
    $stmtComplaint->close();

    guestComplaintInsertParticipant(
        $conn,
        $caseId,
        'Complainant',
        $complainantLast,
        $complainantFirst,
        $complainantMiddle,
        $complainantSuffix,
        $complainantContact,
        $complainantEmail,
        $complainantAddress,
        $complainantAge,
        $complainantSex,
        'Complaint submitted as guest.'
    );

    foreach ($witnesses as $index => $witness) {
        guestComplaintInsertParticipant(
            $conn,
            $caseId,
            'Witness',
            $witness['lastname'] ?? null,
            $witness['firstname'] ?? null,
            $witness['middlename'] ?? null,
            $witness['suffix'] ?? null,
            $witness['contact_number'] ?? null,
            null,
            $witness['address'] ?? null,
            null,
            null,
            'Witness ' . ($index + 1) . ' details recorded from guest complaint submission.'
        );
    }

    guestComplaintLogCaseUpdate($conn, $caseId, 'Complaint submitted through guest portal.', null);
    $conn->commit();
    unset($_SESSION['guest_complaint_otp_verified']);
    guestComplaintClearTrackerCache();

    guestComplaintRedirectWithMessage($complainantPath, 'success', 'Complaint submitted successfully.', [
        'case_id' => $caseId,
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    exit('Failed to submit complaint: ' . $e->getMessage());
}
