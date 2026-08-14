<?php
require_once __DIR__ . "/../General/security.php";
require_once __DIR__ . "/../General/connection.php";
require_once __DIR__ . "/../General/adminModulePermissions.php";
require_once __DIR__ . "/../General/appointmentCouncilMembers.php";
require_once __DIR__ . "/../General/appointmentSettings.php";
require_once __DIR__ . "/../General/appointmentOfficialSchedules.php";
require_once __DIR__ . "/../General/appointmentTimeSlots.php";
require_once __DIR__ . "/../General/sendSMS.php";
require_once __DIR__ . "/../General/mailConfigurations.php";
require_once __DIR__ . "/../General/piiCrypto.php";
require_once __DIR__ . "/../EmailHandlers/emailSender.php";

requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin'], false);
verifyCsrfToken(false);
if (!amp_current_user_has_module_permission($conn, 'appointments')) {
    header('Location: ' . appUrl('/Admin-End/Appointments/AppointmentTracker.php?error=' . rawurlencode('You do not have permission to manage appointments.')));
    exit;
}

function am_redirect_with_message(string $type, string $message, array $extra = []): void
{
    header('Location: ' . am_redirect_url($type, $message, $extra));
    exit;
}

function am_redirect_url(string $type, string $message, array $extra = []): string
{
    $query = array_merge([$type => $message], $extra);
    return appUrl('/Admin-End/Appointments/AppointmentTracker.php') . '?' . http_build_query($query);
}

function am_finish_redirect_response(string $location): void
{
    if (headers_sent()) {
        return;
    }

    ignore_user_abort(true);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    header('Location: ' . $location, true, 302);
    header('Content-Length: 0');
    header('Connection: close');

    flush();

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
}

function am_is_local_request(): bool
{
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '')));
    $host = preg_replace('/:\d+$/', '', $host);
    $remote = strtolower(trim((string)($_SERVER['REMOTE_ADDR'] ?? '')));
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
        || in_array($remote, ['127.0.0.1', '::1'], true);
}

function am_table_exists(mysqli $conn, string $tableName): bool
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

function am_table_columns(mysqli $conn, string $tableName): array
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

function am_official_profile_exists(mysqli $conn, string $userId): bool
{
    $userId = trim($userId);
    if ($userId === '') {
        return false;
    }

    if (!am_table_exists($conn, 'officialinformationtbl')) {
        return false;
    }

    $stmt = $conn->prepare("
        SELECT 1
        FROM officialinformationtbl
        WHERE user_id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();

    return !empty($row);
}

function am_account_exists(mysqli $conn, string $userId): bool
{
    $userId = trim($userId);
    if ($userId === '') {
        return false;
    }

    if (!am_table_exists($conn, 'useraccountstbl')) {
        return false;
    }

    $stmt = $conn->prepare("
        SELECT 1
        FROM useraccountstbl
        WHERE user_id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();

    return !empty($row);
}

function am_get_status_id(mysqli $conn, string $name, string $type): ?int
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

function am_ensure_status_id(mysqli $conn, string $name, string $type): int
{
    $existing = am_get_status_id($conn, $name, $type);
    if ($existing !== null) {
        return $existing;
    }

    $stmt = $conn->prepare("INSERT INTO statuslookuptbl (status_name, status_type) VALUES (?, ?)");
    if (!$stmt) {
        throw new Exception('Failed to create appointment status lookup.');
    }

    $stmt->bind_param("ss", $name, $type);
    $stmt->execute();
    $statusId = (int)$conn->insert_id;
    $stmt->close();

    return $statusId;
}

function am_normalize_phone(string $value): ?string
{
    $digits = preg_replace('/\D+/', '', trim($value));
    if ($digits === '') {
        return null;
    }
    if (preg_match('/^9\d{9}$/', $digits)) {
        return '0' . $digits;
    }
    if (preg_match('/^0\d{10}$/', $digits)) {
        return $digits;
    }
    if (preg_match('/^63\d{10}$/', $digits)) {
        return '0' . substr($digits, 2);
    }

    return null;
}

function am_display_name(array $row, string $fallback = ''): string
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

function am_official_title_from_position(string $positionAccess, string $seatName = ''): string
{
    $label = strtolower(trim($positionAccess . ' ' . $seatName));
    if ($label === '') {
        return '';
    }
    if (strpos($label, 'punong barangay') !== false || strpos($label, 'barangay captain') !== false || strpos($label, 'kapitan') !== false) {
        return 'Kapitan';
    }
    if (strpos($label, 'kagawad') !== false || strpos($label, 'councilor') !== false) {
        return 'Kagawad';
    }
    if (strpos($label, 'barangay secretary') !== false || preg_match('/\bsecretary\b/', $label)) {
        return 'Barangay Secretary';
    }
    if (strpos($label, 'barangay treasurer') !== false || preg_match('/\btreasurer\b/', $label)) {
        return 'Barangay Treasurer';
    }

    return '';
}

function am_official_message_label(array $official): string
{
    $fullName = trim((string)($official['full_name'] ?? ''));
    $positionAccess = trim((string)($official['position_access'] ?? ''));
    $seatName = trim((string)($official['seat_name'] ?? ''));
    $title = am_official_title_from_position($positionAccess, $seatName);
    if ($title !== '' && $fullName !== '') {
        return $title . ' ' . $fullName;
    }
    if ($fullName !== '') {
        return $fullName;
    }
    return 'the assigned barangay official';
}

function am_format_schedule_label(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'the updated schedule';
    }

    try {
        $timestamp = new DateTimeImmutable($value);
        return $timestamp->format('F j, Y g:i A');
    } catch (Throwable $e) {
        return $value;
    }
}

function am_format_sms_schedule_label(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'the updated schedule';
    }

    try {
        $timestamp = new DateTimeImmutable($value);
        return $timestamp->format('M j, Y g:i A');
    } catch (Throwable $e) {
        return $value;
    }
}

function am_sms_clip(string $value, int $maxLength): string
{
    $value = preg_replace('/\s+/', ' ', trim($value));
    if ($value === '') {
        return '';
    }
    if ($maxLength < 4 || strlen($value) <= $maxLength) {
        return $value;
    }

    return rtrim(substr($value, 0, $maxLength - 3)) . '...';
}

function am_denial_reason_limit(): int
{
    return 140;
}

function am_status_key(string $statusName): string
{
    $normalized = strtolower(trim($statusName));
    if ($normalized === '') {
        return 'pending';
    }
    if (str_contains($normalized, 'complete') || str_contains($normalized, 'done')) {
        return 'completed';
    }
    if (str_contains($normalized, 'resched')) {
        return 'rescheduled';
    }
    if (str_contains($normalized, 'confirm') || str_contains($normalized, 'approve')) {
        return 'approved';
    }
    if (str_contains($normalized, 'deny') || str_contains($normalized, 'reject') || str_contains($normalized, 'cancel')) {
        return 'denied';
    }

    return 'pending';
}

function am_load_official_contact(mysqli $conn, string $officialUserId, string $fallbackName = 'Barangay Official'): array
{
    $officialUserId = trim($officialUserId);
    if ($officialUserId === '') {
        return [
            'user_id' => '',
            'full_name' => trim($fallbackName) !== '' ? trim($fallbackName) : 'Barangay Official',
            'phone_number' => '',
            'email' => '',
            'position_access' => '',
            'seat_name' => '',
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

    $fullName = am_display_name($row, $fallbackName);
    $officialEmail = pii_decrypt_string((string)($row['official_email'] ?? ''));
    $officialContactNumber = pii_decrypt_string((string)($row['official_contact_number'] ?? ''));
    $email = strtolower(trim($officialEmail));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = strtolower(trim((string)($row['email'] ?? '')));
    }

    $phoneNumber = am_normalize_phone($officialContactNumber);
    if ($phoneNumber === null) {
        $phoneNumber = am_normalize_phone((string)($row['phone_number'] ?? ''));
    }
    $positionAccess = trim((string)apcm_current_official_position($conn, $officialUserId));

    return [
        'user_id' => $officialUserId,
        'full_name' => $fullName !== '' ? $fullName : 'Barangay Official',
        'phone_number' => $phoneNumber ?? '',
        'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '',
        'position_access' => $positionAccess,
        'seat_name' => '',
    ];
}

function am_load_resident_contact(mysqli $conn, array $appointmentRow): array
{
    $residentUserId = trim((string)($appointmentRow['resident_user_id'] ?? ''));
    $fallbackName = trim((string)($appointmentRow['resident_name'] ?? 'Resident'));
    $fallbackPhone = am_normalize_phone((string)($appointmentRow['resident_contact_number'] ?? '')) ?? '';

    if ($residentUserId === '' || !am_table_exists($conn, 'useraccountstbl') || !am_table_exists($conn, 'residentinformationtbl')) {
        return [
            'user_id' => $residentUserId,
            'full_name' => $fallbackName !== '' ? $fallbackName : 'Resident',
            'phone_number' => $fallbackPhone,
        ];
    }

    $stmt = $conn->prepare("
        SELECT ua.user_id,
               ua.phone_number,
               r.firstname,
               r.middlename,
               r.lastname,
               r.suffix
        FROM useraccountstbl ua
        LEFT JOIN residentinformationtbl r
            ON r.user_id COLLATE utf8mb4_general_ci = ua.user_id COLLATE utf8mb4_general_ci
        WHERE ua.user_id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return [
            'user_id' => $residentUserId,
            'full_name' => $fallbackName !== '' ? $fallbackName : 'Resident',
            'phone_number' => $fallbackPhone,
        ];
    }

    $stmt->bind_param('s', $residentUserId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return [
            'user_id' => $residentUserId,
            'full_name' => $fallbackName !== '' ? $fallbackName : 'Resident',
            'phone_number' => $fallbackPhone,
        ];
    }

    $row = pii_decrypt_resident_row($row) ?? $row;
    $row = pii_decrypt_useraccount_row($row) ?? $row;

    $fullName = am_display_name($row, $fallbackName);
    $phoneNumber = am_normalize_phone((string)($row['phone_number'] ?? ''));
    if ($phoneNumber === null) {
        $phoneNumber = $fallbackPhone !== '' ? $fallbackPhone : null;
    }

    return [
        'user_id' => $residentUserId,
        'full_name' => $fullName !== '' ? $fullName : ($fallbackName !== '' ? $fallbackName : 'Resident'),
        'phone_number' => $phoneNumber ?? '',
    ];
}

function am_send_action_notifications(array $resident, array $official, array $appointment, string $actionLabel): array
{
    $errors = [];
    $actionKey = am_status_key($actionLabel);
    $appointmentId = trim((string)($appointment['appointment_id'] ?? ''));
    $subject = trim((string)($appointment['subject'] ?? ''));
    $purpose = trim((string)($appointment['purpose'] ?? ''));
    $location = trim((string)($appointment['meeting_location'] ?? ''));
    $remarks = trim((string)($appointment['appointment_remarks'] ?? ''));
    $scheduleLabel = am_format_schedule_label((string)($appointment['confirmed_schedule_timestamp'] ?? ''));
    $smsScheduleLabel = am_format_sms_schedule_label((string)($appointment['confirmed_schedule_timestamp'] ?? ''));
    $locationLabel = $location !== '' ? $location : 'the assigned meeting location';
    $officialName = trim((string)($official['full_name'] ?? 'Barangay Official'));
    $officialLabel = am_official_message_label($official);
    $residentName = trim((string)($resident['full_name'] ?? 'Resident'));
    $residentPhone = trim((string)($resident['phone_number'] ?? ''));
    $officialPhone = trim((string)($official['phone_number'] ?? ''));
    $officialEmail = trim((string)($official['email'] ?? ''));
    $smsLocationLabel = am_sms_clip($locationLabel, 30);
    $smsOfficialLabel = am_sms_clip($officialLabel, 36);
    $smsResidentName = am_sms_clip($residentName, 28);

    if ($actionKey === 'completed') {
        $residentSms = "Appointment Completed: Your appointment with {$smsOfficialLabel} was marked as completed.";
        $officialSms = "Appointment Completed: {$smsResidentName}'s appointment with you was marked as completed.";
        $emailSubject = 'Appointment Completed: ' . ($appointmentId !== '' ? $appointmentId : 'Barangay San Jose');
        $emailIntro = 'An appointment was marked as completed through the appointment tracker.';
    } elseif ($actionKey === 'rescheduled') {
        $residentSms = "Your appointment with {$smsOfficialLabel} was moved to {$smsScheduleLabel} at {$smsLocationLabel}.";
        $officialSms = "{$smsResidentName}'s appointment with you was moved to {$smsScheduleLabel} at {$smsLocationLabel}.";
        $emailSubject = 'Appointment Rescheduled: ' . ($appointmentId !== '' ? $appointmentId : 'Barangay San Jose');
        $emailIntro = 'An appointment schedule was updated through the appointment tracker.';
    } else {
        $denialReason = $remarks !== '' ? $remarks : 'Please contact the barangay office.';
        $denialReason = am_sms_clip($denialReason, am_denial_reason_limit());
        $residentSms = "Appointment Denied: {$denialReason}";
        $officialSms = "Appointment Denied: {$denialReason}";
        $emailSubject = 'Appointment Denied: ' . ($appointmentId !== '' ? $appointmentId : 'Barangay San Jose');
        $emailIntro = 'An appointment was denied through the appointment tracker.';
    }

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
        $bodyHtml = '
            <p>Hello ' . htmlspecialchars($officialName, ENT_QUOTES, 'UTF-8') . ',</p>
            <p>' . htmlspecialchars($emailIntro, ENT_QUOTES, 'UTF-8') . '</p>
            <ul>
                <li><strong>Appointment ID:</strong> ' . htmlspecialchars($appointmentId, ENT_QUOTES, 'UTF-8') . '</li>
                <li><strong>Resident:</strong> ' . htmlspecialchars($residentName, ENT_QUOTES, 'UTF-8') . '</li>
                <li><strong>Schedule:</strong> ' . htmlspecialchars($scheduleLabel, ENT_QUOTES, 'UTF-8') . '</li>
                <li><strong>Meeting location:</strong> ' . htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8') . '</li>
                <li><strong>Subject:</strong> ' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</li>
                <li><strong>Purpose:</strong> ' . htmlspecialchars($purpose, ENT_QUOTES, 'UTF-8') . '</li>
                <li><strong>Resident contact:</strong> ' . htmlspecialchars($residentPhone !== '' ? $residentPhone : 'Not available', ENT_QUOTES, 'UTF-8') . '</li>
            </ul>
            <p>Please open the appointment tracker if any follow-up is needed.</p>
        ';
        $bodyText = implode("\n", [
            "Hello {$officialName},",
            '',
            $emailIntro,
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
            'bodyHtml' => $bodyHtml,
            'bodyText' => $bodyText,
        ])) {
            $errors[] = 'Official email failed' . ($emailSender->getLastError() !== '' ? ': ' . $emailSender->getLastError() : '.');
        }
    } else {
        $errors[] = 'Official email skipped because no email address is on file.';
    }

    return $errors;
}

function am_status_can_be_managed(string $statusName): bool
{
    return !in_array(am_status_key($statusName), ['denied', 'completed'], true);
}

function am_status_can_be_completed(string $statusName): bool
{
    return in_array(am_status_key($statusName), ['approved', 'rescheduled'], true);
}

function am_validate_schedule(mysqli $conn, string $officialUserId, string $date, string $time, array $appointmentSettings): array
{
    if ($date === '' || $time === '') {
        throw new Exception('Confirmed date and time are required for this action.');
    }

    $timezone = new DateTimeZone(date_default_timezone_get() ?: 'Asia/Manila');
    $now = new DateTimeImmutable('now', $timezone);
    $bookingLimits = aps_booking_date_limits($appointmentSettings, $now);
    $minDate = (string)($bookingLimits['min_date'] ?? '');
    $maxDate = (string)($bookingLimits['max_date'] ?? '');

    $schedule = DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $time, $timezone);
    if (!$schedule || $schedule->format('Y-m-d') !== $date || $schedule->format('H:i') !== $time) {
        throw new Exception('Confirmed appointment date or time is invalid.');
    }

    if ($schedule <= $now) {
        throw new Exception('Please select a remaining appointment time later than the current time.');
    }

    if (empty($bookingLimits['has_window']) || aps_first_available_booking_date($appointmentSettings, $now) === null) {
        throw new Exception('No appointment dates are currently available based on the saved appointment settings.');
    }

    if ($date < $minDate || $date > $maxDate) {
        throw new Exception('Confirmed appointment date is outside the current booking window.');
    }

    if (!aps_is_date_available($appointmentSettings, $date)) {
        throw new Exception('The selected appointment date is unavailable for official appointments.');
    }

    $officialAvailability = apos_effective_schedule_for_user_date($conn, $officialUserId, $date, $appointmentSettings);
    if ($officialAvailability === null) {
        throw new Exception('The selected council member is not available on that appointment date.');
    }

    if (!array_key_exists($time, (array)($officialAvailability['slots'] ?? []))) {
        throw new Exception('Please select one of the allotted appointment times for that council member.');
    }

    return [
        'timestamp' => $schedule->format('Y-m-d H:i:s'),
        'meeting_location' => apos_normalize_location($officialAvailability['meeting_location'] ?? ''),
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

if (!am_table_exists($conn, 'appointmentstbl')) {
    http_response_code(500);
    exit('Appointment table is not available. Run the appointment migration first.');
}

$action = strtolower(trim((string)($_POST['action'] ?? '')));
$appointmentId = trim((string)($_POST['appointment_id'] ?? ''));
$officialUserId = trim((string)($_POST['official_user_id'] ?? ''));
$confirmedDate = trim((string)($_POST['confirmed_date'] ?? ''));
$confirmedTime = trim((string)($_POST['confirmed_time'] ?? ''));
$remarks = trim((string)($_POST['appointment_remarks'] ?? ''));
$reviewedByUserId = trim((string)($_SESSION['user_id'] ?? ''));
$reviewedByOfficialUserId = am_official_profile_exists($conn, $reviewedByUserId) ? $reviewedByUserId : '';
$appointmentAccess = apcm_get_appointment_admin_scope($conn, $reviewedByUserId, (string)($_SESSION['role'] ?? ''));

if (empty($appointmentAccess['can_access_tracker'])) {
    am_redirect_with_message('error', 'Appointment tracker access is not available for your account.', [
        'appointment_id' => $appointmentId,
    ]);
}

if (empty($appointmentAccess['can_manage_all_tracker']) && $reviewedByUserId !== '') {
    $officialUserId = $reviewedByUserId;
}

if (!in_array($action, ['reschedule_appointment', 'deny_appointment', 'complete_appointment'], true)) {
    am_redirect_with_message('error', 'Unknown appointment action.');
}

if ($appointmentId === '') {
    am_redirect_with_message('error', 'Appointment ID is required.');
}

$appointmentColumns = am_table_columns($conn, 'appointmentstbl');
if (!isset($appointmentColumns['appointment_status_id'])) {
    am_redirect_with_message('error', 'Appointment status column is missing from appointmentstbl.');
}

if ($officialUserId !== '' && !isset($appointmentColumns['user_id_official_assigned'])) {
    am_redirect_with_message('error', 'Assigned official cannot be saved because user_id_official_assigned is missing from appointmentstbl.', [
        'appointment_id' => $appointmentId,
    ]);
}

$needsSchedule = $action === 'reschedule_appointment';
$councilMembersByUserId = apcm_fetch_council_members_by_user_id($conn);
$appointmentSettings = aps_settings_load($conn);

if ($needsSchedule && $officialUserId === '') {
    am_redirect_with_message('error', 'Please select the barangay council member for this appointment.', [
        'appointment_id' => $appointmentId,
    ]);
}

if ($officialUserId !== '' && !isset($councilMembersByUserId[$officialUserId])) {
    am_redirect_with_message('error', 'Selected barangay council member could not be found.', [
        'appointment_id' => $appointmentId,
    ]);
}

$confirmedScheduleTimestamp = null;
$meetingLocation = '';
if ($needsSchedule) {
    try {
        $validatedSchedule = am_validate_schedule($conn, $officialUserId, $confirmedDate, $confirmedTime, $appointmentSettings);
        $confirmedScheduleTimestamp = (string)($validatedSchedule['timestamp'] ?? '');
        $meetingLocation = (string)($validatedSchedule['meeting_location'] ?? '');
    } catch (Throwable $e) {
        am_redirect_with_message('error', $e->getMessage(), ['appointment_id' => $appointmentId]);
    }
}

if ($action !== 'deny_appointment') {
    $remarks = '';
} else {
    $remarks = preg_replace('/\s+/', ' ', $remarks);
    if ($remarks === '') {
        am_redirect_with_message('error', 'A denial reason is required.', [
            'appointment_id' => $appointmentId,
        ]);
    }
    if (strlen($remarks) > am_denial_reason_limit()) {
        am_redirect_with_message('error', 'Denial reason must stay within 140 characters.', [
            'appointment_id' => $appointmentId,
        ]);
    }
}

$statusName = match ($action) {
    'reschedule_appointment' => 'Rescheduled',
    'complete_appointment' => 'Completed',
    default => 'Denied',
};

try {
    $statusId = am_ensure_status_id($conn, $statusName, 'Appointment');
} catch (Throwable $e) {
    am_redirect_with_message('error', 'Unable to prepare appointment statuses right now.');
}

$conn->begin_transaction();
try {
    $existsStmt = $conn->prepare("
        SELECT a.appointment_id
             , " . (isset($appointmentColumns['user_id_resident']) ? "a.user_id_resident" : "NULL") . " AS resident_user_id
             , " . (isset($appointmentColumns['name']) ? "a.name" : "''") . " AS resident_name
             , " . (isset($appointmentColumns['contact_number']) ? "a.contact_number" : "''") . " AS resident_contact_number
             , " . (isset($appointmentColumns['subject']) ? "a.subject" : "''") . " AS subject
             , " . (isset($appointmentColumns['subject_other']) ? "a.subject_other" : "''") . " AS subject_other
             , " . (isset($appointmentColumns['purpose']) ? "a.purpose" : "''") . " AS purpose
             , " . (isset($appointmentColumns['user_id_official_assigned']) ? "a.user_id_official_assigned" : "NULL") . " AS official_user_id
             , " . (isset($appointmentColumns['confirmed_schedule_timestamp']) ? "a.confirmed_schedule_timestamp" : "NULL") . " AS current_confirmed_schedule_timestamp
             , " . (isset($appointmentColumns['meeting_location']) ? "a.meeting_location" : "NULL") . " AS current_meeting_location
             , COALESCE(s.status_name, 'Pending') AS status_name
        FROM appointmentstbl a
        LEFT JOIN statuslookuptbl s
            ON s.status_id = a.appointment_status_id
        WHERE a.appointment_id = ?
        LIMIT 1
    ");
    if (!$existsStmt) {
        throw new Exception('Failed to load the appointment record.');
    }
    $existsStmt->bind_param("s", $appointmentId);
    $existsStmt->execute();
    $existingRow = $existsStmt->get_result()->fetch_assoc();
    $existsStmt->close();

    if (!$existingRow) {
        throw new Exception('Appointment record not found.');
    }

    $residentContact = am_load_resident_contact($conn, $existingRow);

    $currentStatusName = trim((string)($existingRow['status_name'] ?? 'Pending'));
    if (!am_status_can_be_managed($currentStatusName)) {
        throw new Exception('This appointment can no longer be changed because it is already in a final status.');
    }
    if ($action === 'complete_appointment' && !am_status_can_be_completed($currentStatusName)) {
        throw new Exception('Only confirmed or rescheduled appointments can be marked as completed.');
    }

    if (empty($appointmentAccess['can_manage_all_tracker'])) {
        $assignedOfficialUserId = trim((string)($existingRow['official_user_id'] ?? ''));
        if ($reviewedByUserId === '' || $assignedOfficialUserId === '' || strcasecmp($assignedOfficialUserId, $reviewedByUserId) !== 0) {
            throw new Exception('You can only review appointments assigned to your account.');
        }
    }

    $setClauses = ['appointment_status_id = ?'];
    $bindTypes = 'i';
    $bindValues = [$statusId];

    if (isset($appointmentColumns['user_id_employee_staff']) && $reviewedByOfficialUserId !== '') {
        $setClauses[] = 'user_id_employee_staff = ?';
        $bindTypes .= 's';
        $bindValues[] = $reviewedByOfficialUserId;
    }

    if (isset($appointmentColumns['user_id_official_assigned']) && $officialUserId !== '') {
        $setClauses[] = 'user_id_official_assigned = ?';
        $bindTypes .= 's';
        $bindValues[] = $officialUserId;
    }

    if (isset($appointmentColumns['appointment_remarks']) && $remarks !== '') {
        $setClauses[] = 'appointment_remarks = ?';
        $bindTypes .= 's';
        $bindValues[] = $remarks;
    }

    $currentMeetingLocation = trim((string)($existingRow['current_meeting_location'] ?? ''));
    $currentConfirmedScheduleTimestamp = trim((string)($existingRow['current_confirmed_schedule_timestamp'] ?? ''));

    if (isset($appointmentColumns['meeting_location'])) {
        $setClauses[] = 'meeting_location = ?';
        $bindTypes .= 's';
        $bindValues[] = $needsSchedule
            ? ($meetingLocation !== '' ? $meetingLocation : null)
            : ($action === 'complete_appointment' ? ($currentMeetingLocation !== '' ? $currentMeetingLocation : null) : null);
    }

    if (isset($appointmentColumns['review_timestamp'])) {
        $setClauses[] = 'review_timestamp = NOW()';
    }

    if (isset($appointmentColumns['confirmed_schedule_timestamp'])) {
        $setClauses[] = 'confirmed_schedule_timestamp = ?';
        $bindTypes .= 's';
        $bindValues[] = $needsSchedule
            ? $confirmedScheduleTimestamp
            : ($action === 'complete_appointment' ? ($currentConfirmedScheduleTimestamp !== '' ? $currentConfirmedScheduleTimestamp : null) : null);
    } elseif ($needsSchedule && isset($appointmentColumns['schedule_timestamp'])) {
        $setClauses[] = 'schedule_timestamp = ?';
        $bindTypes .= 's';
        $bindValues[] = $confirmedScheduleTimestamp;
    }

    $bindTypes .= 's';
    $bindValues[] = $appointmentId;

    $sql = "
        UPDATE appointmentstbl
        SET " . implode(",\n            ", $setClauses) . "
        WHERE appointment_id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare the appointment update.');
    }

    $stmt->bind_param($bindTypes, ...$bindValues);
    if (!$stmt->execute()) {
        $error = $stmt->error ?: $conn->error;
        $stmt->close();
        throw new Exception('Failed to update appointment: ' . $error);
    }
    $stmt->close();

    $conn->commit();

    $successMessage = match ($action) {
        'reschedule_appointment' => 'Appointment rescheduled successfully.',
        'complete_appointment' => 'Appointment marked as completed successfully.',
        default => 'Appointment denied successfully.',
    };
    $successUrl = am_redirect_url('success', $successMessage, ['appointment_id' => $appointmentId]);
    am_finish_redirect_response($successUrl);

    try {
        $finalOfficialUserId = $officialUserId !== '' ? $officialUserId : trim((string)($existingRow['official_user_id'] ?? ''));
        $officialContact = am_load_official_contact($conn, $finalOfficialUserId, 'Barangay Official');
        $finalScheduleTimestamp = $needsSchedule
            ? (string)$confirmedScheduleTimestamp
            : trim((string)($existingRow['current_confirmed_schedule_timestamp'] ?? ''));
        $finalMeetingLocation = $needsSchedule
            ? $meetingLocation
            : trim((string)($existingRow['current_meeting_location'] ?? ''));
        $subject = trim((string)($existingRow['subject'] ?? ''));
        $subjectOther = trim((string)($existingRow['subject_other'] ?? ''));
        if (strcasecmp($subject, 'Other') === 0 && $subjectOther !== '') {
            $subject = 'Other: ' . $subjectOther;
        }
        $notificationErrors = am_send_action_notifications(
            $residentContact,
            $officialContact,
            [
                'appointment_id' => $appointmentId,
                'subject' => $subject,
                'purpose' => trim((string)($existingRow['purpose'] ?? '')),
                'meeting_location' => $finalMeetingLocation,
                'confirmed_schedule_timestamp' => $finalScheduleTimestamp,
                'appointment_remarks' => $remarks,
            ],
            $statusName
        );
        if ($notificationErrors !== []) {
            error_log('appointmentManagement notification warnings: ' . implode(' | ', $notificationErrors));
        }
    } catch (Throwable $notificationError) {
        error_log('appointmentManagement notification setup failed: ' . $notificationError->getMessage());
    }
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    error_log('appointmentManagement failed: ' . $e->getMessage());
    $message = 'Unable to update the appointment right now.';
    if (am_is_local_request()) {
        $message = $e->getMessage() !== '' ? $e->getMessage() : $message;
    }
    am_redirect_with_message('error', $message, ['appointment_id' => $appointmentId]);
}
