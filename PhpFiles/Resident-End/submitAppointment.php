<?php
require_once __DIR__ . "/../General/security.php";
require_once __DIR__ . "/../General/connection.php";
require_once __DIR__ . "/../General/appointmentCouncilMembers.php";
require_once __DIR__ . "/../General/appointmentSettings.php";
require_once __DIR__ . "/../General/appointmentOfficialSchedules.php";
require_once __DIR__ . "/../General/appointmentTimeSlots.php";
require_once __DIR__ . "/../General/uniqueIDGenerate.php";
require_once __DIR__ . "/../General/sendSMS.php";
require_once __DIR__ . "/../General/mailConfigurations.php";
require_once __DIR__ . "/../General/piiCrypto.php";
require_once __DIR__ . "/../EmailHandlers/emailSender.php";
require_once __DIR__ . "/../GET/getResidentProfile.php";

function appointmentRedirectWithMessage(string $type, string $message, array $extra = []): void
{
    $query = array_merge([$type => $message], $extra);
    header('Location: ' . appUrl('/Resident-End/Appointments/AppointmentForm.php') . '?' . http_build_query($query));
    exit;
}

function appointmentTableExists(mysqli $conn, string $tableName): bool
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

function appointmentGetTableColumns(mysqli $conn, string $tableName): array
{
    $columns = [];
    $stmt = $conn->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    if (!$stmt) {
        return $columns;
    }

    $stmt->bind_param("s", $tableName);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $column = strtolower(trim((string)($row['COLUMN_NAME'] ?? '')));
        if ($column !== '') {
            $columns[$column] = true;
        }
    }
    $stmt->close();

    return $columns;
}

function appointmentGetStatusId(mysqli $conn, string $name, string $type): ?int
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

function appointmentEnsureStatusId(mysqli $conn, string $name, string $type): int
{
    $existing = appointmentGetStatusId($conn, $name, $type);
    if ($existing !== null) {
        return $existing;
    }

    $stmt = $conn->prepare("INSERT INTO statuslookuptbl (status_name, status_type) VALUES (?, ?)");
    if (!$stmt) {
        throw new Exception("Failed to create appointment status lookup.");
    }

    $stmt->bind_param("ss", $name, $type);
    $stmt->execute();
    $statusId = (int)$conn->insert_id;
    $stmt->close();

    return $statusId;
}

function appointmentNormalizePhone(string $value): ?string
{
    $digits = preg_replace('/\D+/', '', trim($value));
    if ($digits === '') {
        return null;
    }
    if (preg_match('/^9\d{9}$/', $digits)) {
        $digits = '0' . $digits;
    }
    return $digits;
}

function appointmentNormalizeSubjectLabel(string $value): string
{
    $normalized = strtolower(trim($value));
    $map = [
        'follow_up' => 'Follow-up Concern',
        'consultation' => 'Consultation',
        'event_coordination' => 'Event Coordination',
        'other' => 'Other',
    ];
    return $map[$normalized] ?? $value;
}

function appointmentDisplayName(array $row, string $fallback = ''): string
{
    $parts = array_filter([
        trim((string)($row['firstname'] ?? '')),
        trim((string)($row['middlename'] ?? '')),
        trim((string)($row['lastname'] ?? '')),
        trim((string)($row['suffix'] ?? '')),
    ], static fn($value) => $value !== '');

    $fullName = preg_replace('/\s+/', ' ', trim(implode(' ', $parts)));
    if ($fullName !== '') {
        return $fullName;
    }

    return trim($fallback);
}

function appointmentFormatTimestampLabel(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'the selected schedule';
    }

    try {
        $timestamp = new DateTimeImmutable($value);
        return $timestamp->format('F j, Y g:i A');
    } catch (Throwable $e) {
        return $value;
    }
}

function appointmentLoadOfficialDeliveryContact(mysqli $conn, string $officialUserId, array $fallback = []): array
{
    $officialUserId = trim($officialUserId);
    if ($officialUserId === '') {
        return [
            'user_id' => '',
            'full_name' => trim((string)($fallback['full_name'] ?? $fallback['option_label'] ?? 'Barangay Official')),
            'phone_number' => '',
            'email' => '',
        ];
    }

    $stmt = $conn->prepare("
        SELECT ua.user_id,
               ua.email,
               ua.phone_number,
               oi.firstname,
               oi.middlename,
               oi.lastname,
               oi.suffix,
               oi.contact_number AS official_contact_number,
               oi.email AS official_email
        FROM useraccountstbl ua
        LEFT JOIN officialinformationtbl oi
            ON oi.user_id COLLATE utf8mb4_general_ci = ua.user_id COLLATE utf8mb4_general_ci
        WHERE ua.user_id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        throw new RuntimeException('Unable to load the assigned official contact details.');
    }

    $stmt->bind_param('s', $officialUserId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        throw new RuntimeException('Assigned official account could not be found.');
    }

    $row = pii_decrypt_official_row($row) ?? $row;
    $row = pii_decrypt_useraccount_row($row) ?? $row;

    $fullName = appointmentDisplayName($row, (string)($fallback['full_name'] ?? $fallback['option_label'] ?? 'Barangay Official'));
    $email = strtolower(trim((string)($row['official_email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = strtolower(trim((string)($row['email'] ?? '')));
    }

    $phoneNumber = appointmentNormalizePhone((string)($row['official_contact_number'] ?? ''));
    if ($phoneNumber === null) {
        $phoneNumber = appointmentNormalizePhone((string)($row['phone_number'] ?? ''));
    }

    return [
        'user_id' => $officialUserId,
        'full_name' => $fullName !== '' ? $fullName : 'Barangay Official',
        'phone_number' => $phoneNumber ?? '',
        'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '',
    ];
}

function appointmentSlotIsAlreadyBooked(mysqli $conn, array $appointmentColumns, string $officialUserId, string $scheduleTimestamp): bool
{
    $officialUserId = trim($officialUserId);
    $scheduleTimestamp = trim($scheduleTimestamp);
    if (
        $officialUserId === ''
        || $scheduleTimestamp === ''
        || !isset($appointmentColumns['user_id_official_assigned'])
    ) {
        return false;
    }

    $timestampColumns = [];
    foreach (['confirmed_schedule_timestamp', 'preferred_schedule_timestamp', 'schedule_timestamp'] as $column) {
        if (isset($appointmentColumns[$column])) {
            $timestampColumns[] = $column;
        }
    }

    if ($timestampColumns === []) {
        return false;
    }

    $timeClauses = [];
    $bindTypes = 's';
    $bindValues = [$officialUserId];
    foreach ($timestampColumns as $column) {
        $timeClauses[] = "a.{$column} = ?";
        $bindTypes .= 's';
        $bindValues[] = $scheduleTimestamp;
    }

    $statusJoin = '';
    $statusFilter = '';
    if (appointmentTableExists($conn, 'statuslookuptbl') && isset($appointmentColumns['appointment_status_id'])) {
        $statusJoin = "
            LEFT JOIN statuslookuptbl s
                ON s.status_id = a.appointment_status_id
        ";
        $statusFilter = "AND LOWER(TRIM(COALESCE(s.status_name, ''))) <> 'denied'";
    }

    $sql = "
        SELECT a.appointment_id
        FROM appointmentstbl a
        {$statusJoin}
        WHERE a.user_id_official_assigned = ?
          AND (" . implode(' OR ', $timeClauses) . ")
          {$statusFilter}
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Unable to validate the selected appointment slot.');
    }

    $stmt->bind_param($bindTypes, ...$bindValues);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (bool)$row;
}

function appointmentSendNotifications(array $resident, array $official, array $appointment): array
{
    $errors = [];
    $appointmentId = trim((string)($appointment['appointment_id'] ?? ''));
    $subject = trim((string)($appointment['subject'] ?? ''));
    $purpose = trim((string)($appointment['purpose'] ?? ''));
    $location = trim((string)($appointment['meeting_location'] ?? ''));
    $scheduleLabel = appointmentFormatTimestampLabel((string)($appointment['confirmed_schedule_timestamp'] ?? ''));
    $locationLabel = $location !== '' ? $location : 'the assigned meeting location';
    $officialName = trim((string)($official['full_name'] ?? 'Barangay Official'));
    $residentName = trim((string)($resident['full_name'] ?? 'Resident'));
    $residentPhone = trim((string)($resident['phone_number'] ?? ''));
    $officialPhone = trim((string)($official['phone_number'] ?? ''));
    $officialEmail = trim((string)($official['email'] ?? ''));
    $residentSms = "Barangay San Jose: Your appointment {$appointmentId} with {$officialName} is confirmed for {$scheduleLabel} at {$locationLabel}. Subject: {$subject}.";
    $officialSms = "Barangay San Jose: New confirmed appointment {$appointmentId} with {$residentName} on {$scheduleLabel} at {$locationLabel}. Subject: {$subject}.";

    if ($residentPhone !== '') {
        if (!sendSMS($residentPhone, $residentSms)) {
            $errors[] = 'Resident SMS failed' . (getLastSmsError() !== '' ? ': ' . getLastSmsError() : '.');
        }
    } else {
        $errors[] = 'Resident SMS skipped because no mobile number is on file.';
    }

    if ($officialPhone !== '') {
        if (!sendSMS($officialPhone, $officialSms)) {
            $errors[] = 'Official SMS failed' . (getLastSmsError() !== '' ? ': ' . getLastSmsError() : '.');
        }
    } else {
        $errors[] = 'Official SMS skipped because no mobile number is on file.';
    }

    if ($officialEmail !== '') {
        $smtpConfig = require __DIR__ . '/../General/mailConfigurations.php';
        $emailSender = new EmailSender($smtpConfig);
        $emailSubject = 'New Appointment Confirmed: ' . ($appointmentId !== '' ? $appointmentId : 'Barangay San Jose');
        $emailBodyHtml = '
            <p>Hello ' . htmlspecialchars($officialName, ENT_QUOTES, 'UTF-8') . ',</p>
            <p>A resident appointment was automatically confirmed after booking.</p>
            <ul>
                <li><strong>Appointment ID:</strong> ' . htmlspecialchars($appointmentId, ENT_QUOTES, 'UTF-8') . '</li>
                <li><strong>Resident:</strong> ' . htmlspecialchars($residentName, ENT_QUOTES, 'UTF-8') . '</li>
                <li><strong>Schedule:</strong> ' . htmlspecialchars($scheduleLabel, ENT_QUOTES, 'UTF-8') . '</li>
                <li><strong>Meeting location:</strong> ' . htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8') . '</li>
                <li><strong>Subject:</strong> ' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</li>
                <li><strong>Purpose:</strong> ' . htmlspecialchars($purpose, ENT_QUOTES, 'UTF-8') . '</li>
                <li><strong>Resident contact:</strong> ' . htmlspecialchars($residentPhone !== '' ? $residentPhone : 'Not available', ENT_QUOTES, 'UTF-8') . '</li>
            </ul>
            <p>Please open the appointment tracker if any follow-up or rescheduling is needed.</p>
        ';
        $emailBodyText = implode("\n", [
            "Hello {$officialName},",
            '',
            'A resident appointment was automatically confirmed after booking.',
            "Appointment ID: {$appointmentId}",
            "Resident: {$residentName}",
            "Schedule: {$scheduleLabel}",
            "Meeting location: {$locationLabel}",
            "Subject: {$subject}",
            "Purpose: {$purpose}",
            'Resident contact: ' . ($residentPhone !== '' ? $residentPhone : 'Not available'),
        ]);

        if (!$emailSender->send([
            'type' => 'transaction',
            'to' => $officialEmail,
            'subject' => $emailSubject,
            'bodyHtml' => $emailBodyHtml,
            'bodyText' => $emailBodyText,
        ])) {
            $errors[] = 'Official email failed' . ($emailSender->getLastError() !== '' ? ': ' . $emailSender->getLastError() : '.');
        }
    } else {
        $errors[] = 'Official email skipped because no email address is on file.';
    }

    return $errors;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

requireRoleSession(['Resident'], false);
verifyCsrfToken(false);

$action = trim((string)($_POST['action'] ?? 'submit_appointment'));
if ($action !== 'submit_appointment') {
    http_response_code(400);
    exit('Unknown appointment action.');
}

if (!appointmentTableExists($conn, 'appointmentstbl')) {
    http_response_code(500);
    exit('Appointment table is not available. Run the appointment migration first.');
}

$userId = (string)($_SESSION['user_id'] ?? '');
$data = getResidentProfileData($conn, $userId);
$residentinformationtbl = $data['residentinformationtbl'] ?? [];
$useraccountstbl = $data['useraccountstbl'] ?? [];

if ($userId === '' || empty($residentinformationtbl)) {
    appointmentRedirectWithMessage('error', 'Resident profile is required before submitting an appointment.');
}

$subject = strtolower(trim((string)($_POST['subject'] ?? '')));
$subjectOther = trim((string)($_POST['subject_other'] ?? ''));
$officialUserId = trim((string)($_POST['official_user_id'] ?? ''));
$appointmentDate = trim((string)($_POST['appointment_date'] ?? ''));
$appointmentTime = trim((string)($_POST['appointment_time'] ?? ''));
$purpose = trim((string)($_POST['purpose'] ?? ''));
$councilMembersByUserId = apcm_fetch_council_members_by_user_id($conn);
$appointmentSettings = aps_settings_load($conn);

if ($councilMembersByUserId === []) {
    appointmentRedirectWithMessage('error', 'No active barangay council members are currently available for appointments.');
}

if ($officialUserId === '' || !isset($councilMembersByUserId[$officialUserId])) {
    appointmentRedirectWithMessage('error', 'Please select a valid barangay council member for your appointment.');
}

$allowedSubjects = ['follow_up', 'consultation', 'event_coordination', 'other'];
if (!in_array($subject, $allowedSubjects, true)) {
    appointmentRedirectWithMessage('error', 'Please select a valid subject of appointment.');
}

if ($subject === 'other' && $subjectOther === '') {
    appointmentRedirectWithMessage('error', 'Please specify the subject when Other is selected.');
}

if ($appointmentDate === '' || $appointmentTime === '' || $purpose === '') {
    appointmentRedirectWithMessage('error', 'Please complete all required appointment fields.');
}

$timezone = new DateTimeZone(date_default_timezone_get() ?: 'Asia/Manila');
$now = new DateTimeImmutable('now', $timezone);
$bookingLimits = aps_booking_date_limits($appointmentSettings, $now);
$minAppointmentDate = (string)($bookingLimits['min_date'] ?? '');
$maxAppointmentDate = (string)($bookingLimits['max_date'] ?? '');

if (empty($bookingLimits['has_window']) || aps_first_available_booking_date($appointmentSettings, $now) === null) {
    appointmentRedirectWithMessage('error', 'No appointment dates are currently available based on the saved appointment settings.');
}

$schedule = DateTimeImmutable::createFromFormat('Y-m-d H:i', $appointmentDate . ' ' . $appointmentTime, $timezone);
if (!$schedule || $schedule->format('Y-m-d') !== $appointmentDate || $schedule->format('H:i') !== $appointmentTime) {
    appointmentRedirectWithMessage('error', 'Appointment date or time is invalid.');
}

if ($appointmentDate < $minAppointmentDate || $appointmentDate > $maxAppointmentDate) {
    appointmentRedirectWithMessage('error', 'Date of appointment is outside the current booking window.');
}

if (!aps_is_date_available($appointmentSettings, $appointmentDate)) {
    appointmentRedirectWithMessage('error', 'The selected appointment date is unavailable for official appointments.');
}

$officialAvailability = apos_effective_schedule_for_user_date($conn, $officialUserId, $appointmentDate, $appointmentSettings);
if ($officialAvailability === null) {
    appointmentRedirectWithMessage('error', 'The selected council member is not available on that appointment date.');
}

if (!array_key_exists($appointmentTime, (array)($officialAvailability['slots'] ?? []))) {
    appointmentRedirectWithMessage('error', 'Please select one of the allotted appointment times for that council member.');
}

$firstName = trim((string)($residentinformationtbl['firstname'] ?? ''));
$middleName = trim((string)($residentinformationtbl['middlename'] ?? ''));
$lastName = trim((string)($residentinformationtbl['lastname'] ?? ''));
$suffix = trim((string)($residentinformationtbl['suffix'] ?? ''));
$contactNumber = appointmentNormalizePhone((string)($useraccountstbl['phone_number'] ?? ''));

if ($firstName === '' || $lastName === '' || $contactNumber === null) {
    appointmentRedirectWithMessage('error', 'Your resident profile is missing required contact details.');
}

$nameParts = array_filter([$firstName, $middleName, $lastName, $suffix], static fn($value) => trim((string)$value) !== '');
$residentName = implode(' ', $nameParts);
$statusId = appointmentEnsureStatusId($conn, 'Approved', 'Appointment');
$appointmentId = GenerateAppointmentID($conn);

if (!$appointmentId) {
    appointmentRedirectWithMessage('error', 'Unable to generate an appointment ID right now.');
}

$subjectLabel = appointmentNormalizeSubjectLabel($subject);
$normalizedSubjectOther = $subject === 'other' ? $subjectOther : null;
$residentNotes = $subject === 'other' ? 'Other subject detail: ' . $subjectOther : null;
$residentUserId = $userId !== '' ? $userId : null;
$preferredScheduleTimestamp = $schedule->format('Y-m-d H:i:s');
$confirmedScheduleTimestamp = $preferredScheduleTimestamp;
$meetingLocation = apos_normalize_location($officialAvailability['meeting_location'] ?? '');
$appointmentColumns = appointmentGetTableColumns($conn, 'appointmentstbl');
try {
    $officialContact = appointmentLoadOfficialDeliveryContact($conn, $officialUserId, $councilMembersByUserId[$officialUserId] ?? []);
} catch (Throwable $e) {
    appointmentRedirectWithMessage('error', 'Unable to prepare the assigned official details for this appointment.');
}

if (!isset($appointmentColumns['user_id_official_assigned'])) {
    appointmentRedirectWithMessage('error', 'The appointment module is missing the council member assignment field. Please run the latest appointment migration first.');
}

$conn->begin_transaction();
try {
    $insertColumns = [
        'appointment_id',
        'user_id_resident',
        'name',
        'contact_number',
        'user_id_official_assigned',
        'subject',
        'subject_other',
        'purpose',
    ];
    $insertValues = [
        $appointmentId,
        $residentUserId,
        $residentName,
        $contactNumber,
        $officialUserId,
        $subjectLabel,
        $normalizedSubjectOther,
        $purpose,
    ];
    $bindTypes = 'ssssssss';

    if (appointmentSlotIsAlreadyBooked($conn, $appointmentColumns, $officialUserId, $confirmedScheduleTimestamp)) {
        throw new Exception('The selected schedule was just taken. Please choose another available appointment slot.');
    }

    if (isset($appointmentColumns['preferred_schedule_timestamp'])) {
        $insertColumns[] = 'preferred_schedule_timestamp';
        $insertValues[] = $preferredScheduleTimestamp;
        $bindTypes .= 's';
    } elseif (isset($appointmentColumns['schedule_timestamp'])) {
        $insertColumns[] = 'schedule_timestamp';
        $insertValues[] = $preferredScheduleTimestamp;
        $bindTypes .= 's';
    } else {
        throw new Exception('Appointment schedule column is missing from appointmentstbl.');
    }

    if (isset($appointmentColumns['confirmed_schedule_timestamp'])) {
        $insertColumns[] = 'confirmed_schedule_timestamp';
        $insertValues[] = $confirmedScheduleTimestamp;
        $bindTypes .= 's';
    }

    if (isset($appointmentColumns['appointment_status_id'])) {
        $insertColumns[] = 'appointment_status_id';
        $insertValues[] = $statusId;
        $bindTypes .= 'i';
    }

    if (isset($appointmentColumns['resident_notes'])) {
        $insertColumns[] = 'resident_notes';
        $insertValues[] = $residentNotes;
        $bindTypes .= 's';
    }

    if (isset($appointmentColumns['meeting_location'])) {
        $insertColumns[] = 'meeting_location';
        $insertValues[] = $meetingLocation !== '' ? $meetingLocation : null;
        $bindTypes .= 's';
    }

    $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
    $columnSql = implode(",\n                ", $insertColumns);

    $stmt = $conn->prepare("
        INSERT INTO appointmentstbl
            (
                {$columnSql}
            )
        VALUES
            ({$placeholders})
    ");
    if (!$stmt) {
        throw new Exception("Prepare failed (appointment insert): " . $conn->error);
    }

    $stmt->bind_param($bindTypes, ...$insertValues);

    if (!$stmt->execute()) {
        $error = $stmt->error ?: $conn->error;
        $stmt->close();
        throw new Exception("Failed to insert appointment: " . $error);
    }
    $stmt->close();

    $conn->commit();
    $notificationErrors = appointmentSendNotifications([
        'full_name' => $residentName,
        'phone_number' => $contactNumber,
    ], $officialContact, [
        'appointment_id' => $appointmentId,
        'subject' => $subjectLabel,
        'purpose' => $purpose,
        'meeting_location' => $meetingLocation,
        'confirmed_schedule_timestamp' => $confirmedScheduleTimestamp,
    ]);
    if ($notificationErrors !== []) {
        error_log('submitAppointment notification warnings: ' . implode(' | ', $notificationErrors));
    }

    appointmentRedirectWithMessage('success', 'Appointment confirmed successfully.', [
        'appointment_id' => $appointmentId,
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('submitAppointment failed: ' . $e->getMessage());
    appointmentRedirectWithMessage('error', $e->getMessage() !== '' ? $e->getMessage() : 'Unable to submit your appointment request right now.');
}
