<?php
require_once __DIR__ . "/../../PhpFiles/General/connection.php";
require_once __DIR__ . "/../../PhpFiles/General/appointmentCouncilMembers.php";
require_once __DIR__ . "/../../PhpFiles/General/appointmentSettings.php";
require_once __DIR__ . "/../../PhpFiles/General/appointmentOfficialSchedules.php";
require_once __DIR__ . "/../../PhpFiles/General/appointmentTimeSlots.php";
require_once __DIR__ . "/../../PhpFiles/General/audit.php";
require_once __DIR__ . "/../includes/admin_guard.php";

$appointmentCurrentUserId = trim((string)($_SESSION['user_id'] ?? ''));
$appointmentCurrentRole = trim((string)($_SESSION['role'] ?? ''));
$appointmentAccess = apcm_get_appointment_admin_scope($conn, $appointmentCurrentUserId, $appointmentCurrentRole);
if (empty($appointmentAccess['can_access_tracker'])) {
    http_response_code(403);
    exit('Access denied.');
}

apos_ensure_appointment_storage($conn);

$appointmentTool = strtolower(trim((string)($_GET['tool'] ?? 'tracker')));
if (!in_array($appointmentTool, ['tracker', 'settings', 'schedule'], true)) {
    $appointmentTool = 'tracker';
}
$appointmentDateScope = strtolower(trim((string)($_GET['date_scope'] ?? '')));
if (!in_array($appointmentDateScope, ['today', 'tomorrow'], true)) {
    $appointmentDateScope = '';
}
$isAppointmentSettingsView = $appointmentTool === 'settings';
$isAppointmentScheduleView = $appointmentTool === 'schedule';
$isAppointmentTrackerView = !$isAppointmentSettingsView && !$isAppointmentScheduleView;
if ($isAppointmentSettingsView && empty($appointmentAccess['can_access_settings'])) {
    header('Location: ' . appUrl('/Admin-End/Appointments/AppointmentTracker.php?tool=tracker&error=' . rawurlencode('Only SuperAdmin or the Barangay Secretary can manage appointment settings.')));
    exit;
}
if ($isAppointmentScheduleView && empty($appointmentAccess['can_access_schedule'])) {
    header('Location: ' . appUrl('/Admin-End/Appointments/AppointmentTracker.php?tool=tracker&error=' . rawurlencode('Appointment schedule access is not available for your account.')));
    exit;
}

$appointmentSettingDefinitions = aps_settings_definitions();
$appointmentTimezone = new DateTimeZone(date_default_timezone_get() ?: 'Asia/Manila');
$appointmentToday = new DateTimeImmutable('today', $appointmentTimezone);
$appointmentYearEndDate = (new DateTimeImmutable($appointmentToday->format('Y-12-31'), $appointmentTimezone))->format('Y-m-d');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string)($_POST['action'] ?? '') === 'save_appointment_settings') {
    verifyCsrfToken(false);

    $actorUserId = (string)($_SESSION['user_id'] ?? '');
    $actorRole = (string)($_SESSION['role'] ?? 'Official');
    $existingSettings = aps_settings_load($conn);

    try {
        $slotInterval = trim((string)($_POST['slot_interval_minutes'] ?? ''));
        $bookingWindowDays = trim((string)($_POST['booking_window_days'] ?? ''));
        $closedWeekdays = $_POST['closed_weekdays'] ?? [];
        $lunchBreakEnabled = isset($_POST['lunch_break_enabled']) ? '1' : '0';
        $lunchStartTime = trim((string)($_POST['lunch_start_time'] ?? ''));
        $lunchEndTime = trim((string)($_POST['lunch_end_time'] ?? ''));
        $unavailableDatesRaw = trim((string)($_POST['unavailable_dates'] ?? ''));
        $meetingLocationsRaw = (string)($_POST['meeting_locations'] ?? '');

        if ($slotInterval === '' || filter_var($slotInterval, FILTER_VALIDATE_INT) === false) {
            throw new RuntimeException('Slot interval must be a whole number.');
        }
        if ($bookingWindowDays === '' || filter_var($bookingWindowDays, FILTER_VALIDATE_INT) === false) {
            throw new RuntimeException('Booking window must be a whole number.');
        }
        if (!is_array($closedWeekdays)) {
            throw new RuntimeException('Closed appointment days are invalid.');
        }

        $closedWeekdayValues = aps_normalize_closed_weekdays($closedWeekdays);
        $availableWeekdays = aps_available_weekdays_from_closed($closedWeekdayValues);
        if ($availableWeekdays === []) {
            throw new RuntimeException('At least one weekday must remain open for appointments.');
        }

        $slotIntervalValue = (int)$slotInterval;
        $slotIntervalMin = (int)($appointmentSettingDefinitions['slot_interval_minutes']['min'] ?? 10);
        $slotIntervalMax = (int)($appointmentSettingDefinitions['slot_interval_minutes']['max'] ?? 180);
        $slotIntervalStep = max(1, (int)($appointmentSettingDefinitions['slot_interval_minutes']['step'] ?? 5));
        if ($slotIntervalValue < $slotIntervalMin || $slotIntervalValue > $slotIntervalMax) {
            throw new RuntimeException("Slot interval must be between {$slotIntervalMin} and {$slotIntervalMax} minutes.");
        }
        if ($slotIntervalValue % $slotIntervalStep !== 0) {
            throw new RuntimeException("Slot interval must be in {$slotIntervalStep}-minute increments.");
        }

        $bookingWindowValue = (int)$bookingWindowDays;
        $bookingWindowMin = (int)($appointmentSettingDefinitions['booking_window_days']['min'] ?? 1);
        $bookingWindowMax = (int)($appointmentSettingDefinitions['booking_window_days']['max'] ?? 365);
        if ($bookingWindowValue < $bookingWindowMin || $bookingWindowValue > $bookingWindowMax) {
            throw new RuntimeException("Booking window must be between {$bookingWindowMin} and {$bookingWindowMax} days.");
        }

        $unavailableDateTokens = array_values(array_filter(
            array_map('trim', explode(',', $unavailableDatesRaw)),
            static fn(string $value): bool => $value !== ''
        ));
        foreach ($unavailableDateTokens as $unavailableDate) {
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $unavailableDate, $matches)) {
                throw new RuntimeException('Unavailable dates must use the YYYY-MM-DD format.');
            }
            $year = (int)($matches[1] ?? 0);
            $month = (int)($matches[2] ?? 0);
            $day = (int)($matches[3] ?? 0);
            if (!checkdate($month, $day, $year)) {
                throw new RuntimeException('One or more unavailable dates are invalid.');
            }
        }

        $meetingLocationTokens = aps_normalize_location_options($meetingLocationsRaw);

        $normalizedLunchStartTime = aps_normalize_time_value($lunchStartTime);
        $normalizedLunchEndTime = aps_normalize_time_value($lunchEndTime);
        if ($lunchBreakEnabled === '1') {
            if ($normalizedLunchStartTime === '' || $normalizedLunchEndTime === '') {
                throw new RuntimeException('Lunch break start and end times are required when lunch break is enabled.');
            }
            if ($normalizedLunchStartTime >= $normalizedLunchEndTime) {
                throw new RuntimeException('Lunch break end time must be after the lunch break start time.');
            }
            if ($normalizedLunchStartTime < aps_schedule_start_time() || $normalizedLunchEndTime > aps_schedule_end_time()) {
                throw new RuntimeException('Lunch break must stay within the appointment schedule coverage.');
            }
        }

        $settingsToSave = [
            'slot_interval_minutes' => $slotIntervalValue,
            'booking_window_days' => $bookingWindowValue,
            'available_weekdays' => $availableWeekdays,
            'lunch_break_enabled' => $lunchBreakEnabled,
            'lunch_start_time' => $normalizedLunchStartTime !== '' ? $normalizedLunchStartTime : ($existingSettings['lunch_start_time'] ?? '12:00'),
            'lunch_end_time' => $normalizedLunchEndTime !== '' ? $normalizedLunchEndTime : ($existingSettings['lunch_end_time'] ?? '13:00'),
            'unavailable_dates' => $unavailableDateTokens,
            'meeting_locations' => $meetingLocationTokens,
        ];
        $normalizedSettings = aps_normalize_settings($settingsToSave);
        if (ats_allotted_times($normalizedSettings) === []) {
            throw new RuntimeException('The current schedule and lunch break leave no available appointment times.');
        }
        aps_settings_upsert($conn, $normalizedSettings, $actorUserId);

        insertUnifiedAuditLog(
            $conn,
            $actorUserId,
            $actorRole,
            'Appointments',
            'settings',
            'appointment_settings',
            'save_appointment_settings',
            'values',
            json_encode($existingSettings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($normalizedSettings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'Updated appointment schedule settings.'
        );

        header('Location: ' . appUrl('/Admin-End/Appointments/AppointmentTracker.php?tool=settings&success=' . rawurlencode('Appointment settings saved successfully.')));
        exit;
    } catch (Throwable $e) {
        header('Location: ' . appUrl('/Admin-End/Appointments/AppointmentTracker.php?tool=settings&error=' . rawurlencode($e->getMessage())));
        exit;
    }
}

$appointmentSettings = aps_settings_load($conn);
$appointmentCouncilMembers = apcm_fetch_council_members($conn);
$appointmentCouncilMembersByUserId = [];
foreach ($appointmentCouncilMembers as $member) {
    $memberUserId = trim((string)($member['user_id'] ?? ''));
    if ($memberUserId !== '') {
        $appointmentCouncilMembersByUserId[$memberUserId] = $member;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string)($_POST['action'] ?? '') === 'save_appointment_official_schedule') {
    verifyCsrfToken(false);

    $actorUserId = (string)($_SESSION['user_id'] ?? '');
    $selectedOfficialUserId = trim((string)($_POST['official_user_id'] ?? ''));
    if (empty($appointmentAccess['can_manage_all_schedule'])) {
        $selectedOfficialUserId = trim((string)($appointmentAccess['scoped_official_user_id'] ?? $actorUserId));
    }

    try {
        if ($selectedOfficialUserId === '' || !isset($appointmentCouncilMembersByUserId[$selectedOfficialUserId])) {
            throw new RuntimeException('Please select a valid barangay council member for the appointment schedule.');
        }

        $rawWeeklySchedule = $_POST['weekly_schedule'] ?? [];
        if (!is_array($rawWeeklySchedule)) {
            throw new RuntimeException('Weekly appointment schedule data is invalid.');
        }

        apos_save_weekly_schedule($conn, $selectedOfficialUserId, $rawWeeklySchedule, $actorUserId, $appointmentSettings);

        $redirectParams = [
            'tool' => 'schedule',
            'success' => 'Appointment schedule saved successfully.',
        ];
        if (!empty($appointmentAccess['can_manage_all_schedule'])) {
            $redirectParams['official_user_id'] = $selectedOfficialUserId;
        }

        header('Location: ' . appUrl('/Admin-End/Appointments/AppointmentTracker.php') . '?' . http_build_query($redirectParams));
        exit;
    } catch (Throwable $e) {
        $redirectParams = [
            'tool' => 'schedule',
            'error' => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to save the appointment schedule right now.',
        ];
        if (!empty($appointmentAccess['can_manage_all_schedule']) && $selectedOfficialUserId !== '') {
            $redirectParams['official_user_id'] = $selectedOfficialUserId;
        }
        header('Location: ' . appUrl('/Admin-End/Appointments/AppointmentTracker.php') . '?' . http_build_query($redirectParams));
        exit;
    }
}

$bookingLimits = aps_booking_date_limits($appointmentSettings);
$minConfirmedDate = (string)($bookingLimits['min_date'] ?? '');
$maxConfirmedDate = (string)($bookingLimits['max_date'] ?? '');
$appointmentTimeSlots = ats_allotted_times($appointmentSettings);
$appointmentSlotLabels = array_values($appointmentTimeSlots);
$appointmentSlotsSummary = $appointmentSlotLabels !== []
    ? ($appointmentSlotLabels[0] . ' to ' . $appointmentSlotLabels[count($appointmentSlotLabels) - 1])
    : '-';
$appointmentAvailableWeekdayOptions = aps_weekday_options();
$appointmentAvailableWeekdayShortOptions = aps_weekday_short_options();
$appointmentAvailableWeekdayLabels = aps_weekdays_label($appointmentSettings['available_weekdays'] ?? []);
$appointmentClosedWeekdays = aps_closed_weekdays($appointmentSettings);
$appointmentClosedWeekdayLabels = aps_closed_weekdays_label($appointmentSettings);
$appointmentDisabledWeekdays = aps_disabled_weekdays($appointmentSettings);
$appointmentUnavailableDates = aps_normalize_unavailable_dates($appointmentSettings['unavailable_dates'] ?? []);
$appointmentUnavailableDatesCsv = implode(',', $appointmentUnavailableDates);
$appointmentUnavailableDatesSummary = aps_unavailable_dates_label($appointmentUnavailableDates);
$appointmentMeetingLocations = aps_normalize_location_options($appointmentSettings['meeting_locations'] ?? []);
$appointmentMeetingLocationsSummary = $appointmentMeetingLocations !== [] ? implode(', ', $appointmentMeetingLocations) : 'No meeting locations configured yet';
$appointmentLunchBreakEnabled = aps_has_lunch_break($appointmentSettings);
$appointmentLunchBreakLabel = aps_lunch_break_label($appointmentSettings);
$appointmentLunchStartTime = (string)($appointmentSettings['lunch_start_time'] ?? '12:00');
$appointmentLunchEndTime = (string)($appointmentSettings['lunch_end_time'] ?? '13:00');
$appointmentScheduleCoverageLabel = date('h:i A', strtotime(aps_schedule_start_time())) . ' to ' . date('h:i A', strtotime(aps_schedule_end_time()));
$appointmentFirstAvailableDate = aps_first_available_booking_date($appointmentSettings);
$appointmentFirstAvailableDateLabel = $appointmentFirstAvailableDate !== null ? date('M d, Y', strtotime($appointmentFirstAvailableDate)) : 'No available dates';
$appointmentScheduleScopedOfficialUserId = trim((string)($appointmentAccess['scoped_official_user_id'] ?? ''));
$appointmentScheduleSelectedUserId = trim((string)($_GET['official_user_id'] ?? ''));
if (empty($appointmentAccess['can_manage_all_schedule'])) {
    $appointmentScheduleSelectedUserId = $appointmentScheduleScopedOfficialUserId !== '' ? $appointmentScheduleScopedOfficialUserId : $appointmentCurrentUserId;
}
if ($appointmentScheduleSelectedUserId === '' || !isset($appointmentCouncilMembersByUserId[$appointmentScheduleSelectedUserId])) {
    $appointmentScheduleSelectedUserId = array_key_first($appointmentCouncilMembersByUserId) ?? '';
}
$appointmentScheduleRows = $appointmentScheduleSelectedUserId !== ''
    ? apos_weekly_schedule_for_user($conn, $appointmentScheduleSelectedUserId, $appointmentSettings)
    : [];
$appointmentScheduleMap = apos_fetch_schedule_map($conn, array_keys($appointmentCouncilMembersByUserId), $appointmentSettings);
$appointmentBookedSlotMap = apos_fetch_booked_slots_map($conn, array_keys($appointmentCouncilMembersByUserId));
$appointmentScheduleWeekdayOptions = aps_weekday_options();
$appointmentScheduleTimeOptions = [];
$appointmentScheduleTimeCursor = DateTimeImmutable::createFromFormat('H:i', aps_schedule_start_time());
$appointmentScheduleTimeLast = DateTimeImmutable::createFromFormat('H:i', aps_schedule_end_time());
while ($appointmentScheduleTimeCursor && $appointmentScheduleTimeLast && $appointmentScheduleTimeCursor <= $appointmentScheduleTimeLast) {
    $appointmentScheduleTimeValue = $appointmentScheduleTimeCursor->format('H:i');
    $appointmentScheduleTimeOptions[$appointmentScheduleTimeValue] = $appointmentScheduleTimeCursor->format('h:i A');
    $appointmentScheduleTimeCursor = $appointmentScheduleTimeCursor->modify('+5 minutes');
}
$appointmentSelectedScheduleMember = $appointmentScheduleSelectedUserId !== '' && isset($appointmentCouncilMembersByUserId[$appointmentScheduleSelectedUserId])
    ? $appointmentCouncilMembersByUserId[$appointmentScheduleSelectedUserId]
    : null;
$appointmentScheduleLocationPlaceholder = 'Select meeting location';

function at_table_columns(mysqli $conn, string $tableName): array
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

function at_value(array $row, string $key, string $default = ''): string
{
    if (!array_key_exists($key, $row) || $row[$key] === null) {
        return $default;
    }
    $value = trim((string)$row[$key]);
    return $value === '' ? $default : $value;
}

function at_status_key(string $value): string
{
    $normalized = strtolower(trim($value));
    if ($normalized === '') {
        return 'pending';
    }
    if (str_contains($normalized, 'complete') || str_contains($normalized, 'done')) {
        return 'completed';
    }
    if (str_contains($normalized, 'resched')) {
        return 'rescheduled';
    }
    if (str_contains($normalized, 'confirm')) {
        return 'approved';
    }
    if (str_contains($normalized, 'approve')) {
        return 'approved';
    }
    if (str_contains($normalized, 'deny') || str_contains($normalized, 'denied') || str_contains($normalized, 'reject')) {
        return 'denied';
    }
    return 'pending';
}

function at_status_label(string $value): string
{
    if (at_is_rescheduled($value)) {
        return 'Rescheduled';
    }
    $key = at_status_key($value);
    if ($key === 'completed') {
        return 'Completed';
    }
    if ($key === 'approved') {
        return 'Confirmed';
    }
    if ($key === 'denied') {
        return 'Denied';
    }
    return 'Pending';
}

function at_status_pill(string $value): string
{
    $key = at_status_key($value);
    if ($key === 'completed') {
        return 'completed';
    }
    if ($key === 'rescheduled') {
        return 'rescheduled';
    }
    if ($key === 'approved') {
        return 'approved';
    }
    if ($key === 'denied') {
        return 'denied';
    }
    return 'pending';
}

function at_booking_channel_label(string $value, bool $hasResidentLink = false): string
{
    $normalized = strtolower(trim($value));
    if ($normalized === 'resident_portal') {
        return 'Resident Portal';
    }
    if ($normalized === 'guest_otp') {
        return 'Guest OTP';
    }
    if ($normalized === 'walkin_desk') {
        return 'Walk-in Desk';
    }
    if ($hasResidentLink) {
        return 'Resident Portal';
    }

    return 'Manual / Guest';
}

function at_is_rescheduled(string $value): bool
{
    return str_contains(strtolower(trim($value)), 'resched');
}

function at_format_datetime(?string $value, string $fallback = '-'): string
{
    $text = trim((string)$value);
    if ($text === '') {
        return $fallback;
    }
    $timestamp = strtotime($text);
    if ($timestamp === false) {
        return $text;
    }
    return date('M d, Y h:i A', $timestamp);
}

function at_format_date(?string $value, string $fallback = '-'): string
{
    $text = trim((string)$value);
    if ($text === '') {
        return $fallback;
    }
    $timestamp = strtotime($text);
    if ($timestamp === false) {
        return $text;
    }
    return date('M d, Y', $timestamp);
}

function at_format_time(?string $value, string $fallback = '-'): string
{
    $text = trim((string)$value);
    if ($text === '') {
        return $fallback;
    }
    $timestamp = strtotime($text);
    if ($timestamp === false) {
        return $text;
    }
    return date('h:i A', $timestamp);
}

function at_middle_initial_name(string $value, string $fallback = '-'): string
{
    $name = trim($value);
    if ($name === '') {
        return $fallback;
    }

    $parts = preg_split('/\s+/', $name) ?: [];
    $parts = array_values(array_filter($parts, static fn($part) => trim((string)$part) !== ''));
    if (count($parts) <= 2) {
        return $name;
    }

    $suffixes = ['jr', 'jr.', 'sr', 'sr.', 'ii', 'iii', 'iv', 'v'];
    $suffix = '';
    $lastPart = strtolower((string)end($parts));
    if (in_array($lastPart, $suffixes, true)) {
        $suffix = ' ' . array_pop($parts);
    }

    if (count($parts) <= 2) {
        return implode(' ', $parts) . $suffix;
    }

    $lastName = array_pop($parts);
    $givenNames = [];
    if ($parts !== []) {
        $givenNames[] = array_shift($parts);
    }
    if (count($parts) >= 2) {
        $givenNames[] = array_shift($parts);
    }

    $middleInitials = array_map(static function ($part): string {
        $segment = trim((string)$part);
        if ($segment === '') {
            return '';
        }
        return strtoupper(substr($segment, 0, 1)) . '.';
    }, $parts);
    $middleInitials = array_values(array_filter($middleInitials, static fn($part) => $part !== ''));

    return trim(implode(' ', array_filter([
        implode(' ', $givenNames),
        implode(' ', $middleInitials),
        $lastName,
    ])) . $suffix);
}

function at_contains_encrypted_marker(string $value): bool
{
    $value = strtolower(trim($value));
    if ($value === '') {
        return false;
    }

    return str_contains($value, 'pii:v1:') || str_contains($value, 'pii:v2:');
}

function at_fetch_official_directory(mysqli $conn): array
{
    if (!($conn->query("SHOW TABLES LIKE 'officialinformationtbl'") instanceof mysqli_result)) {
        return [];
    }

    $result = $conn->query("
        SELECT user_id, firstname, middlename, lastname, suffix
        FROM officialinformationtbl
    ");
    if (!($result instanceof mysqli_result)) {
        return [];
    }

    $directory = [];
    while ($row = $result->fetch_assoc()) {
        $row = pii_decrypt_official_row($row) ?? $row;
        $userId = trim((string)($row['user_id'] ?? ''));
        if ($userId === '') {
            continue;
        }

        $name = trim(implode(' ', array_values(array_filter([
            trim((string)($row['firstname'] ?? '')),
            trim((string)($row['middlename'] ?? '')),
            trim((string)($row['lastname'] ?? '')),
            trim((string)($row['suffix'] ?? '')),
        ], static fn(string $part): bool => $part !== ''))));

        if ($name !== '') {
            $directory[$userId] = $name;
        }
    }
    $result->free();

    return $directory;
}

function at_resolve_official_display_name(string $userId, array $councilMap, array $officialDirectory, string $fallback = '-'): string
{
    $userId = trim($userId);
    if ($userId !== '' && isset($councilMap[$userId])) {
        $label = trim((string)($councilMap[$userId]['option_label'] ?? $councilMap[$userId]['full_name'] ?? ''));
        if ($label !== '') {
            return $label;
        }
    }

    if ($userId !== '' && isset($officialDirectory[$userId])) {
        $label = trim((string)$officialDirectory[$userId]);
        if ($label !== '') {
            return $label;
        }
    }

    $fallback = trim($fallback);
    if ($fallback !== '' && !at_contains_encrypted_marker($fallback)) {
        return $fallback;
    }

    return '-';
}

function at_flash_message(string $key): string
{
    return trim((string)($_GET[$key] ?? ''));
}

$appointmentRows = [];
$loadError = '';
$appointmentSuccessMessage = at_flash_message('success');
$appointmentErrorMessage = at_flash_message('error');
$appointmentInfoMessage = at_flash_message('info');
$highlightAppointmentId = at_flash_message('appointment_id');
$officialOptions = [];
$scopedOfficialUserId = trim((string)($appointmentAccess['scoped_official_user_id'] ?? ''));
$appointmentOfficialDirectory = at_fetch_official_directory($conn);

if (!$conn->query("SHOW TABLES LIKE 'appointmentstbl'")->num_rows) {
    $loadError = 'Appointment table is not available. Run the appointment migration first.';
} else {
    $appointmentColumns = at_table_columns($conn, 'appointmentstbl');
    $hasPreferredSchedule = isset($appointmentColumns['preferred_schedule_timestamp']);
    $hasConfirmedSchedule = isset($appointmentColumns['confirmed_schedule_timestamp']);
    $hasReviewTimestamp = isset($appointmentColumns['review_timestamp']);
    $hasAssignedOfficial = isset($appointmentColumns['user_id_official_assigned']);
    $hasMeetingLocation = isset($appointmentColumns['meeting_location']);
    $hasCouncilTable = $conn->query("SHOW TABLES LIKE 'barangaycounciltbl'")->num_rows > 0;

    $preferredScheduleSelect = $hasPreferredSchedule
        ? 'a.preferred_schedule_timestamp'
        : (isset($appointmentColumns['schedule_timestamp']) ? 'a.schedule_timestamp' : 'NULL');
    $confirmedScheduleSelect = $hasConfirmedSchedule ? 'a.confirmed_schedule_timestamp' : 'NULL';
    $reviewTimestampSelect = $hasReviewTimestamp ? 'a.review_timestamp' : 'NULL';
    $assignedOfficialSelect = $hasAssignedOfficial ? 'a.user_id_official_assigned' : 'NULL';
    $meetingLocationSelect = $hasMeetingLocation ? 'a.meeting_location' : 'NULL';
    $assignedOfficialJoin = $hasAssignedOfficial
        ? "LEFT JOIN officialinformationtbl official
            ON a.user_id_official_assigned COLLATE utf8mb4_general_ci = official.user_id COLLATE utf8mb4_general_ci"
        : '';
    if ($hasAssignedOfficial && $hasCouncilTable) {
        $assignedOfficialJoin .= "
            LEFT JOIN barangaycounciltbl official_seat
                ON official_seat.current_official_id = official.official_id
               AND official_seat.is_active = 1
        ";
    }
    $assignedOfficialNameSelect = $hasAssignedOfficial
        ? ($hasCouncilTable
            ? "TRIM(CONCAT_WS(' - ', CONCAT_WS(' ', official.firstname, official.middlename, official.lastname, official.suffix), NULLIF(official_seat.seat_name, ''))) AS official_name"
            : "TRIM(CONCAT_WS(' ', official.firstname, official.middlename, official.lastname, official.suffix)) AS official_name")
        : "NULL AS official_name";

    if (empty($appointmentAccess['can_view_all_tracker'])) {
        if (!$hasAssignedOfficial) {
            $loadError = 'Assigned-official tracking is unavailable for this appointments module.';
        } elseif ($scopedOfficialUserId === '') {
            $loadError = 'Your account is missing the official assignment required to view appointment records.';
        }
    }

    if ($loadError === '') {
        $scopeWhere = '';
        $bindTypes = '';
        $bindValues = [];
        if (empty($appointmentAccess['can_view_all_tracker'])) {
            $scopeWhere = "
        WHERE a.user_id_official_assigned COLLATE utf8mb4_general_ci = ?
            ";
            $bindTypes = 's';
            $bindValues[] = $scopedOfficialUserId;
        }

        $sql = "
            SELECT
                a.appointment_id,
                " . (isset($appointmentColumns['user_id_resident']) ? 'a.user_id_resident' : "NULL") . " AS resident_user_id,
                a.name,
                a.contact_number,
                " . (isset($appointmentColumns['email_address']) ? 'a.email_address' : "NULL") . " AS email_address,
                " . (isset($appointmentColumns['booking_channel']) ? 'a.booking_channel' : "NULL") . " AS booking_channel,
                a.subject,
                a.subject_other,
                a.purpose,
                {$preferredScheduleSelect} AS preferred_schedule_timestamp,
                {$confirmedScheduleSelect} AS confirmed_schedule_timestamp,
                a.request_timestamp,
                a.resident_notes,
                a.appointment_remarks,
                {$reviewTimestampSelect} AS review_timestamp,
                {$meetingLocationSelect} AS meeting_location,
                COALESCE(s.status_name, 'Pending') AS status_name,
                staff.user_id AS staff_user_id,
                CONCAT_WS(' ', staff.firstname, staff.lastname) AS staff_name,
                {$assignedOfficialSelect} AS official_user_id,
                {$assignedOfficialNameSelect}
            FROM appointmentstbl a
            LEFT JOIN statuslookuptbl s
                ON a.appointment_status_id = s.status_id
            LEFT JOIN officialinformationtbl staff
                ON a.user_id_employee_staff = staff.user_id
            {$assignedOfficialJoin}{$scopeWhere}
            ORDER BY a.request_timestamp DESC, a.appointment_id DESC
        ";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if ($bindTypes !== '') {
                $stmt->bind_param($bindTypes, ...$bindValues);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $rawStatusName = (string)($row['status_name'] ?? 'Pending');
                $statusName = at_status_label($rawStatusName);
                $isRescheduled = at_is_rescheduled($rawStatusName);
                $subject = at_value($row, 'subject', '-');
                $subjectOther = at_value($row, 'subject_other', '');
                if (strcasecmp($subject, 'Other') === 0 && $subjectOther !== '') {
                    $subject = 'Other: ' . $subjectOther;
                }

                $preferredScheduleTimestamp = at_value($row, 'preferred_schedule_timestamp', '');
                $confirmedScheduleTimestamp = at_value($row, 'confirmed_schedule_timestamp', '');
                $preferredAppointmentDate = at_format_date($row['preferred_schedule_timestamp'] ?? null);
                $preferredAppointmentTime = at_format_time($row['preferred_schedule_timestamp'] ?? null);
                $confirmedAppointmentDate = at_format_date($row['confirmed_schedule_timestamp'] ?? null);
                $confirmedAppointmentTime = at_format_time($row['confirmed_schedule_timestamp'] ?? null);
                $scheduleDisplay = trim($preferredAppointmentDate . ' ' . $preferredAppointmentTime);
                $scheduleSubtitle = '';
                $staffUserId = at_value($row, 'staff_user_id', '');
                $staffName = at_resolve_official_display_name(
                    $staffUserId,
                    [],
                    $appointmentOfficialDirectory,
                    at_value($row, 'staff_name', '-')
                );
                $officialUserId = at_value($row, 'official_user_id', '');
                $officialName = at_resolve_official_display_name(
                    $officialUserId,
                    $appointmentCouncilMembersByUserId,
                    $appointmentOfficialDirectory,
                    at_value($row, 'official_name', '-')
                );
                $residentUserId = at_value($row, 'resident_user_id', '');
                $bookingChannelLabel = at_booking_channel_label(
                    at_value($row, 'booking_channel', ''),
                    $residentUserId !== ''
                );

                if ($confirmedScheduleTimestamp !== '') {
                    $scheduleDisplay = trim($confirmedAppointmentDate . ' ' . $confirmedAppointmentTime);
                    if ($isRescheduled && $preferredScheduleTimestamp !== '') {
                        $scheduleSubtitle = 'Preferred: ' . trim($preferredAppointmentDate . ' ' . $preferredAppointmentTime);
                    }
                }

                $appointmentRows[] = [
                    'appointment_id' => at_value($row, 'appointment_id', '-'),
                    'request_timestamp' => at_value($row, 'request_timestamp', ''),
                    'request_timestamp_display' => at_format_datetime($row['request_timestamp'] ?? null),
                    'resident_name' => at_middle_initial_name(at_value($row, 'name', '')),
                    'contact_number' => at_value($row, 'contact_number', '-'),
                    'email_address' => at_value($row, 'email_address', '-'),
                    'booking_channel' => $bookingChannelLabel,
                    'subject' => $subject,
                    'purpose' => at_value($row, 'purpose', '-'),
                    'preferred_schedule_timestamp' => $preferredScheduleTimestamp,
                    'preferred_appointment_date' => $preferredAppointmentDate,
                    'preferred_appointment_time' => $preferredAppointmentTime,
                    'confirmed_schedule_timestamp' => $confirmedScheduleTimestamp,
                    'confirmed_appointment_date' => $confirmedAppointmentDate,
                    'confirmed_appointment_time' => $confirmedAppointmentTime,
                    'schedule_display' => $scheduleDisplay !== '' ? $scheduleDisplay : '-',
                    'schedule_subtitle' => $scheduleSubtitle,
                    'status_name' => $statusName,
                    'status_key' => at_status_key($statusName),
                    'status_pill' => at_status_pill($statusName),
                    'status_subtitle' => '',
                    'staff_name' => $staffName,
                    'official_user_id' => $officialUserId,
                    'official_name' => $officialName,
                    'meeting_location' => at_value($row, 'meeting_location', ''),
                    'resident_notes' => at_value($row, 'resident_notes', '-'),
                    'appointment_remarks' => at_value($row, 'appointment_remarks', '-'),
                    'review_timestamp_display' => at_format_datetime(
                        $row['review_timestamp'] ?? null,
                        $statusName === 'Confirmed' ? 'Confirmed upon submission' : '-'
                    ),
                ];
            }
            $result->free();
            $stmt->close();
        } else {
            $loadError = 'Unable to load appointment records.';
        }
    }
}

foreach ($appointmentCouncilMembers as $member) {
    $memberUserId = trim((string)($member['user_id'] ?? ''));
    if (
        empty($appointmentAccess['can_manage_all_tracker'])
        && $appointmentCurrentUserId !== ''
        && strcasecmp($memberUserId, $appointmentCurrentUserId) !== 0
    ) {
        continue;
    }
    $officialOptions[] = [
        'user_id' => $memberUserId,
        'full_name' => trim((string)($member['full_name'] ?? '')),
        'position_access' => trim((string)($member['position_access'] ?? '')),
        'department' => trim((string)($member['department'] ?? '')),
        'seat_name' => trim((string)($member['seat_name'] ?? '')),
        'option_label' => trim((string)($member['option_label'] ?? '')),
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../../Images/favicon_sanjose.png?v=20260211">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Tracker</title>

    <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
    <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260227-2">
    <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/BlotterMangementStyle.css?v=20260305-1">
    <style>
        .appointment-tracker-shell {
            max-width: var(--admin-table-shell-max-width);
            margin: 0 auto;
        }

        #appointmentTrackerPageTabs {
            max-width: var(--admin-table-shell-max-width);
            margin: 0 auto -1px;
            padding-left: 0;
            border-bottom: 0;
            position: relative;
            z-index: 2;
            gap: 0.15rem;
        }

        body.admin-sidebar-collapsed .appointment-tracker-shell,
        body.admin-sidebar-collapsed #appointmentTrackerPageTabs {
            max-width: var(--admin-table-shell-max-width-collapsed);
        }

        .appointment-cell-main {
            color: #1f2937;
            font-weight: 600;
            line-height: 1.35;
        }

        .appointment-cell-subtitle {
            margin-top: 0.2rem;
            color: #6b7280;
            font-size: 0.72rem;
            line-height: 1.15;
            white-space: nowrap;
        }

        #appointmentTrackerPageTabs .nav-link {
            color: #d76f12;
            font-weight: 600;
            border: 1px solid transparent;
            border-bottom-color: transparent;
            border-top-left-radius: 0.75rem;
            border-top-right-radius: 0.75rem;
            padding: 0.75rem 1rem;
            background: transparent;
        }

        #appointmentTrackerPageTabs .nav-link:hover,
        #appointmentTrackerPageTabs .nav-link:focus-visible {
            color: #b45309;
            border-color: transparent;
        }

        #appointmentTrackerPageTabs .nav-link.active,
        #appointmentTrackerPageTabs .nav-link.active:hover,
        #appointmentTrackerPageTabs .nav-link.active:focus-visible {
            color: #d76f12;
            background: #ffffff;
            border-color: #dee2e6;
            border-bottom-color: #ffffff;
            box-shadow: none;
        }

        #appointmentTrackerPanel {
            border-top-left-radius: 0 !important;
        }

        .appointment-click-loading {
            position: relative;
            pointer-events: none;
            opacity: 0.88;
        }

        .appointment-click-loading::after {
            content: "";
            display: inline-block;
            width: 0.9rem;
            height: 0.9rem;
            margin-left: 0.55rem;
            border-radius: 50%;
            border: 2px solid currentColor;
            border-right-color: transparent;
            vertical-align: -0.12rem;
            animation: appointment-button-spin 0.75s linear infinite;
        }

        .appointment-click-loading i {
            opacity: 0.78;
        }

        .appointment-tracker-shell .btn,
        #appointmentTrackerPageTabs .nav-link {
            transition: opacity 0.16s ease, transform 0.16s ease;
        }

        .appointment-tracker-shell .btn:disabled,
        #appointmentTrackerPageTabs .nav-link:disabled {
            cursor: wait;
        }

        .appointment-tracker-shell.is-busy {
            cursor: progress;
        }

        @keyframes appointment-button-spin {
            to {
                transform: rotate(360deg);
            }
        }

        .appointment-filter-grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .appointment-filter-grid .full-width {
            grid-column: 1 / -1;
        }

        .appointment-filter-summary {
            color: #6b7280;
            font-size: 0.82rem;
        }

        @media (max-width: 767.98px) {
            #appointmentTrackerPageTabs {
                margin-bottom: 0.75rem;
                gap: 0.5rem;
            }

            #appointmentTrackerPageTabs .nav-item {
                width: 100%;
            }

            #appointmentTrackerPageTabs .nav-link {
                width: 100%;
            }

            .appointment-filter-grid {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        .appointment-status-stack {
            display: inline-flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .status-pill.rescheduled {
            color: #1d4ed8;
            background: #dbeafe;
            border-color: #bfdbfe;
        }

        .status-pill.completed {
            color: #0f5132;
            background: #d1e7dd;
            border-color: #badbcc;
        }

        .appointment-settings-shell {
            display: grid;
            gap: 18px;
        }

        .appointment-settings-lead {
            margin: 0;
            color: #6b7280;
            font-size: 0.96rem;
        }

        .appointment-settings-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .appointment-settings-card {
            border: 1px solid #e78924;
            border-radius: 18px;
            background: #ffffff;
            padding: 1.15rem 1.2rem;
            display: grid;
            gap: 10px;
        }

        .appointment-settings-summary-card {
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            gap: 0.9rem;
        }

        .appointment-settings-summary-section {
            display: grid;
            gap: 0.7rem;
            align-content: start;
        }

        .appointment-settings-summary-meta {
            display: grid;
            gap: 0.45rem;
        }

        .appointment-settings-divider {
            margin: 0;
            border-color: #e9ecef;
            opacity: 1;
        }

        .appointment-settings-card h5 {
            margin: 0;
            color: #1f2937;
            font-size: 1rem;
            font-weight: 700;
        }

        .appointment-settings-card p {
            margin: 0;
            color: #6b7280;
            line-height: 1.5;
        }

        .appointment-settings-form {
            display: grid;
            gap: 18px;
        }

        .appointment-settings-field {
            display: grid;
            gap: 8px;
        }

        .appointment-settings-unit-input {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .appointment-settings-unit-input .form-control {
            flex: 1 1 auto;
            min-width: 0;
        }

        .appointment-settings-unit-label {
            color: #4b5563;
            font-size: 0.95rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .appointment-settings-field label {
            color: #374151;
            font-size: 0.92rem;
            font-weight: 700;
        }

        .appointment-settings-field small {
            color: #6b7280;
        }

        .appointment-settings-list {
            margin: 0;
            padding-left: 1.1rem;
            color: #374151;
            display: grid;
            gap: 0.35rem;
        }

        .appointment-settings-toggle {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            margin: 0;
            color: #374151;
            font-size: 0.92rem;
            font-weight: 700;
        }

        .appointment-settings-toggle input {
            width: 18px;
            height: 18px;
            accent-color: #ea580c;
        }

        .appointment-settings-inline {
            display: grid;
            gap: 0.85rem;
        }

        .appointment-lunch-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .appointment-weekday-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.55rem 1rem;
        }

        .appointment-weekday-option {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 0.6rem;
            min-height: auto;
            padding: 0.2rem 0;
            border: 0;
            border-radius: 0;
            background: transparent;
            color: #374151;
            font-weight: 600;
            text-align: left;
            cursor: pointer;
        }

        .appointment-weekday-option span {
            font-size: 0.96rem;
            line-height: 1.2;
        }

        .appointment-weekday-option input {
            width: 18px;
            height: 18px;
            margin: 0;
            accent-color: #ea580c;
        }

        .appointment-slot-cloud {
            display: flex;
            flex-wrap: wrap;
            gap: 0.55rem;
        }

        .appointment-unavailable-toolbar {
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: end;
        }

        .appointment-unavailable-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.55rem;
        }

        .appointment-unavailable-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.42rem 0.8rem;
            border-radius: 999px;
            background: #f8fafc;
            border: 1px solid #dbe3ef;
            color: #334155;
            font-size: 0.82rem;
            font-weight: 700;
        }

        button.appointment-unavailable-pill {
            cursor: pointer;
        }

        button.appointment-unavailable-pill:hover {
            background: #fff7ed;
            border-color: #fdba74;
            color: #9a3412;
        }

        .appointment-unavailable-empty {
            color: #6b7280;
            font-size: 0.88rem;
        }

        .appointment-slot-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.42rem 0.8rem;
            border-radius: 999px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #b45309;
            font-size: 0.82rem;
            font-weight: 700;
        }

        .appointment-settings-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        .appointment-schedule-editor-shell {
            display: grid;
            grid-template-columns: minmax(280px, 0.95fr) minmax(0, 1.55fr);
            gap: 18px;
        }

        .appointment-schedule-overview {
            display: grid;
            gap: 0.9rem;
            align-content: start;
        }

        .appointment-schedule-member-name {
            font-size: 1.05rem;
            font-weight: 700;
            color: #1f2937;
        }

        .appointment-schedule-hint {
            color: #6b7280;
            font-size: 0.9rem;
            line-height: 1.55;
        }

        .appointment-week-schedule-grid {
            display: grid;
            gap: 0.85rem;
        }

        .appointment-week-schedule-row {
            display: grid;
            grid-template-columns: minmax(170px, 0.78fr) repeat(3, minmax(0, 1fr));
            gap: 0.75rem;
            align-items: stretch;
            padding: 0.9rem 0.95rem;
            border: 1px solid #e7ecf2;
            border-radius: 16px;
            background: #fbfcfe;
        }

        .appointment-week-schedule-row.is-disabled {
            background: #f8fafc;
            opacity: 0.78;
        }

        .appointment-week-schedule-day {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            font-weight: 700;
            color: #1f2937;
        }

        .appointment-week-schedule-day input {
            width: 18px;
            height: 18px;
            margin: 0;
            accent-color: #ea580c;
        }

        .appointment-week-schedule-field {
            display: grid;
            gap: 0.35rem;
            align-content: start;
        }

        .appointment-week-schedule-field-label {
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #6b7280;
        }

        .appointment-week-schedule-row .form-control,
        .appointment-week-schedule-row .form-select {
            min-width: 0;
            min-height: 46px;
        }

        .appointment-schedule-summary-list {
            margin: 0;
            padding-left: 1.1rem;
            display: grid;
            gap: 0.35rem;
            color: #374151;
        }

        #viewModal .modal-content {
            border: 1px solid #e9ecef;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }

        #viewModal .modal-header,
        #viewModal .modal-body,
        #viewModal .modal-footer {
            padding: 1rem 1.25rem;
        }

        #viewModal .tracker-profile-view {
            display: grid;
            gap: 12px;
        }

        #viewModal .modal-body {
            background: #fff;
        }

        #table-appointmentData th,
        #table-appointmentData td {
            text-align: left !important;
            vertical-align: middle;
        }

        #viewModal .tracker-form-section {
            border-color: #e78924;
            margin-top: 0;
            display: grid;
            gap: 12px;
        }

        #viewModal .tracker-form-section-title,
        #viewModal .tracker-form-label {
            margin: 0;
        }

        #viewModal .tracker-form-grid {
            display: grid;
            gap: 12px;
        }

        #viewModal .tracker-form-grid.cols-1 {
            grid-template-columns: minmax(0, 1fr);
        }

        #viewModal .tracker-form-grid.cols-2,
        #viewModal .tracker-form-grid:not(.cols-1):not(.cols-3):not(.cols-4) {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        #viewModal .tracker-form-grid.cols-3 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        #viewModal .tracker-form-grid.cols-4 {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        #viewModal .tracker-form-field {
            display: grid;
            gap: 6px;
            align-content: start;
        }

        #viewModal .tracker-form-label {
            line-height: 1.2;
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 600;
        }

        #viewModal .tracker-form-value {
            min-height: 46px;
            padding: 10px 12px;
            border: 1px solid #d8dee5;
            border-radius: 12px;
            background: #f8f9fa;
            line-height: 1.45;
            word-break: break-word;
        }

        .appointment-action-compare {
            width: 100%;
            margin: 0;
        }

        .appointment-action-compare th,
        .appointment-action-compare td {
            vertical-align: middle;
            text-align: left;
        }


        #viewModal .tracker-form-section > .tracker-form-grid + .tracker-form-grid,
        #viewModal .tracker-form-section > .tracker-form-grid + .tracker-form-field,
        #viewModal .tracker-form-section > .tracker-form-field + .tracker-form-grid,
        #viewModal .tracker-form-section > .tracker-form-field + .tracker-form-field {
            margin-top: 0;
        }

        @media (max-width: 991.98px) {
            .appointment-settings-grid {
                grid-template-columns: minmax(0, 1fr);
            }

            .appointment-schedule-editor-shell {
                grid-template-columns: minmax(0, 1fr);
            }

            .appointment-week-schedule-row {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .appointment-week-schedule-day {
                grid-column: 1 / -1;
            }

            .appointment-weekday-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }

            #viewModal .tracker-form-grid.cols-4,
            #viewModal .tracker-form-grid.cols-3 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .appointment-weekday-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .appointment-week-schedule-row {
                grid-template-columns: minmax(0, 1fr);
            }

            .appointment-lunch-grid {
                grid-template-columns: minmax(0, 1fr);
            }

            .appointment-unavailable-toolbar {
                grid-template-columns: minmax(0, 1fr);
            }

            #viewModal .tracker-form-grid,
            #viewModal .tracker-form-grid.cols-4,
            #viewModal .tracker-form-grid.cols-3,
            #viewModal .tracker-form-grid.cols-2 {
                grid-template-columns: minmax(0, 1fr);
            }
        }
    </style>
</head>
<body>
<div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
        <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C;"><?= $isAppointmentSettingsView ? 'Appointment Settings' : 'Appointment Tracker' ?></h2>
        <hr><br>

        <?php if ($isAppointmentSettingsView): ?>
        <div id="div-tableContainer" class="bg-white p-4 rounded-4 shadow-sm border appointment-tracker-shell appointment-settings-shell">
            <p class="appointment-settings-lead">Adjust the appointment slot length, weekly closed days, lunch break, blocked dates, and how far ahead residents can book. Changes here apply to the resident form and the admin approval flow.</p>

            <div class="appointment-settings-grid">
                <section class="appointment-settings-card">
                    <h5>Appointment Controls</h5>
                    <form method="post" action="<?= htmlspecialchars(appUrl('Admin-End/Appointments/AppointmentTracker.php?tool=settings'), ENT_QUOTES, 'UTF-8') ?>" class="appointment-settings-form">
                        <?= csrfTokenField() ?>
                        <input type="hidden" name="action" value="save_appointment_settings">

                        <div class="appointment-settings-field">
                            <label for="appointmentSlotInterval">Time allotment per appointment</label>
                            <div class="appointment-settings-unit-input">
                                <input
                                    class="form-control"
                                    type="number"
                                    id="appointmentSlotInterval"
                                    name="slot_interval_minutes"
                                    min="<?= (int)($appointmentSettingDefinitions['slot_interval_minutes']['min'] ?? 10) ?>"
                                    max="<?= (int)($appointmentSettingDefinitions['slot_interval_minutes']['max'] ?? 180) ?>"
                                    step="<?= (int)($appointmentSettingDefinitions['slot_interval_minutes']['step'] ?? 5) ?>"
                                    value="<?= htmlspecialchars((string)($appointmentSettings['slot_interval_minutes'] ?? 30), ENT_QUOTES, 'UTF-8') ?>"
                                    required
                                >
                                <span class="appointment-settings-unit-label">Minutes</span>
                            </div>
                            <small>Examples: 30, 40, or 60 minutes.</small>
                        </div>

                        <div class="appointment-settings-field">
                            <label for="appointmentBookingWindow">How many days ahead residents can book</label>
                            <input
                                class="form-control"
                                type="number"
                                id="appointmentBookingWindow"
                                name="booking_window_days"
                                min="<?= (int)($appointmentSettingDefinitions['booking_window_days']['min'] ?? 1) ?>"
                                max="<?= (int)($appointmentSettingDefinitions['booking_window_days']['max'] ?? 365) ?>"
                                step="<?= (int)($appointmentSettingDefinitions['booking_window_days']['step'] ?? 1) ?>"
                                value="<?= htmlspecialchars((string)($appointmentSettings['booking_window_days'] ?? 365), ENT_QUOTES, 'UTF-8') ?>"
                                required
                            >
                            <small>Residents can book from tomorrow up to this many days ahead, still within the current year.</small>
                        </div>

                        <hr>

                        <div class="appointment-settings-field">
                            <label>Closed for appointments</label>
                            <div class="appointment-weekday-grid">
                                <?php foreach ($appointmentAvailableWeekdayOptions as $weekdayValue => $weekdayLabel): ?>
                                    <label class="appointment-weekday-option" for="appointmentClosedWeekday<?= (int)$weekdayValue ?>">
                                        <input
                                            type="checkbox"
                                            id="appointmentClosedWeekday<?= (int)$weekdayValue ?>"
                                            name="closed_weekdays[]"
                                            value="<?= (int)$weekdayValue ?>"
                                            <?= in_array((int)$weekdayValue, $appointmentClosedWeekdays, true) ? 'checked' : '' ?>
                                        >
                                        <span><?= htmlspecialchars($weekdayLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <small>Checked days stay closed every week. Leave a day unchecked to keep it open for appointments.</small>
                        </div>

                        <hr>

                        <div class="appointment-settings-field">
                            <label class="appointment-settings-toggle" for="appointmentLunchBreakEnabled">
                                <input
                                    type="checkbox"
                                    id="appointmentLunchBreakEnabled"
                                    name="lunch_break_enabled"
                                    value="1"
                                    <?= $appointmentLunchBreakEnabled ? 'checked' : '' ?>
                                >
                                <span>Block lunch time in the appointment schedule</span>
                            </label>
                            <div class="appointment-settings-inline appointment-lunch-grid">
                                <div class="appointment-settings-field">
                                    <label for="appointmentLunchStart">Lunch break start</label>
                                    <input
                                        class="form-control"
                                        type="time"
                                        id="appointmentLunchStart"
                                        name="lunch_start_time"
                                        min="<?= htmlspecialchars(aps_schedule_start_time(), ENT_QUOTES, 'UTF-8') ?>"
                                        max="<?= htmlspecialchars(aps_schedule_end_time(), ENT_QUOTES, 'UTF-8') ?>"
                                        value="<?= htmlspecialchars($appointmentLunchStartTime, ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                </div>
                                <div class="appointment-settings-field">
                                    <label for="appointmentLunchEnd">Lunch break end</label>
                                    <input
                                        class="form-control"
                                        type="time"
                                        id="appointmentLunchEnd"
                                        name="lunch_end_time"
                                        min="<?= htmlspecialchars(aps_schedule_start_time(), ENT_QUOTES, 'UTF-8') ?>"
                                        max="<?= htmlspecialchars(aps_schedule_end_time(), ENT_QUOTES, 'UTF-8') ?>"
                                        value="<?= htmlspecialchars($appointmentLunchEndTime, ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                </div>
                            </div>
                            <small>Any slot that overlaps this time range is removed from the available appointment schedule.</small>
                        </div>

                        <hr>

                        <div class="appointment-settings-field">
                            <label for="appointmentUnavailableDatePicker">Unavailable dates</label>
                            <input type="hidden" name="unavailable_dates" id="appointmentUnavailableDates" value="<?= htmlspecialchars($appointmentUnavailableDatesCsv, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="appointment-settings-inline appointment-unavailable-toolbar">
                                <input
                                    class="form-control"
                                    type="date"
                                    id="appointmentUnavailableDatePicker"
                                    placeholder="Select dates"
                                    min="<?= htmlspecialchars($appointmentToday->format('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>"
                                    max="<?= htmlspecialchars($appointmentYearEndDate, ENT_QUOTES, 'UTF-8') ?>"
                                    data-date-modal-style="calendar"
                                    data-date-modal-selection="multiple"
                                    data-date-multi-target="appointmentUnavailableDates"
                                >
                                <button type="button" class="btn btn-outline-secondary" id="appointmentUnavailableDateAdd">Select dates</button>
                            </div>
                            <div class="appointment-unavailable-list" id="appointmentUnavailableDateList">
                                <?php if ($appointmentUnavailableDates === []): ?>
                                    <div class="appointment-unavailable-empty">No unavailable dates added.</div>
                                <?php else: ?>
                                    <?php foreach ($appointmentUnavailableDates as $isoDate): ?>
                                        <button type="button" class="appointment-unavailable-pill" data-unavailable-date="<?= htmlspecialchars($isoDate, ENT_QUOTES, 'UTF-8') ?>">
                                            <span><?= htmlspecialchars(aps_format_date_label($isoDate), ENT_QUOTES, 'UTF-8') ?></span>
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <small>Use this for holidays, office closures, or one-off days when appointments should stay blocked.</small>
                        </div>

                        <hr>

                        <div class="appointment-settings-field">
                            <label for="appointmentMeetingLocationInput">Meeting location options</label>
                            <input type="hidden" name="meeting_locations" id="appointmentMeetingLocations" value="<?= htmlspecialchars(implode("\n", $appointmentMeetingLocations), ENT_QUOTES, 'UTF-8') ?>">
                            <div class="appointment-settings-inline appointment-unavailable-toolbar">
                                <input
                                    class="form-control"
                                    type="text"
                                    id="appointmentMeetingLocationInput"
                                    maxlength="255"
                                    placeholder="Add a meeting location"
                                >
                                <button type="button" class="btn btn-outline-secondary" id="appointmentMeetingLocationAdd">Add location</button>
                            </div>
                            <div class="appointment-unavailable-list" id="appointmentMeetingLocationList">
                                <?php if ($appointmentMeetingLocations === []): ?>
                                    <div class="appointment-unavailable-empty">No meeting locations added yet.</div>
                                <?php else: ?>
                                    <?php foreach ($appointmentMeetingLocations as $locationLabel): ?>
                                        <button type="button" class="appointment-unavailable-pill" data-meeting-location="<?= htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8') ?>">
                                            <span><?= htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <small>These saved locations will appear in the official weekly schedule dropdown.</small>
                        </div>

                        <div class="appointment-settings-actions">
                            <button type="submit" class="btn btn-primary" disabled>Save Settings</button>
                        </div>
                    </form>
                </section>

                <section class="appointment-settings-card appointment-settings-summary-card">
                    <div class="appointment-settings-summary-section">
                        <h5>Current Appointment Rules</h5>
                        <ul class="appointment-settings-list">
                            <li>Residents can only set appointments with currently serving barangay council members.</li>
                            <li>Open weekdays: <?= htmlspecialchars($appointmentAvailableWeekdayLabels, ENT_QUOTES, 'UTF-8') ?>.</li>
                            <li>Closed weekdays: <?= htmlspecialchars($appointmentClosedWeekdayLabels, ENT_QUOTES, 'UTF-8') ?>.</li>
                            <li>Lunch break: <?= htmlspecialchars($appointmentLunchBreakLabel, ENT_QUOTES, 'UTF-8') ?>.</li>
                            <li>Unavailable dates: <?= htmlspecialchars($appointmentUnavailableDatesSummary, ENT_QUOTES, 'UTF-8') ?>.</li>
                            <li>Meeting locations: <?= htmlspecialchars($appointmentMeetingLocationsSummary, ENT_QUOTES, 'UTF-8') ?>.</li>
                            <li>Residents can book from tomorrow up to <?= htmlspecialchars((string)($appointmentSettings['booking_window_days'] ?? 365), ENT_QUOTES, 'UTF-8') ?> days ahead.</li>
                            <li>Earliest bookable date right now: <?= htmlspecialchars($appointmentFirstAvailableDateLabel, ENT_QUOTES, 'UTF-8') ?>.</li>
                        </ul>
                    </div>
                    <hr class="appointment-settings-divider">
                    <div class="appointment-settings-summary-section">
                        <h5>Current Allotted Schedule</h5>
                        <div class="appointment-settings-summary-meta">
                            <p><strong>Coverage:</strong> <?= htmlspecialchars($appointmentScheduleCoverageLabel, ENT_QUOTES, 'UTF-8') ?></p>
                            <p><strong>Interval:</strong> <?= htmlspecialchars((string)($appointmentSettings['slot_interval_minutes'] ?? 30), ENT_QUOTES, 'UTF-8') ?> minutes per appointment slot</p>
                            <?php if ($appointmentLunchBreakEnabled): ?>
                                <p><strong>Lunch break excluded:</strong> <?= htmlspecialchars($appointmentLunchBreakLabel, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="appointment-slot-cloud">
                            <?php foreach ($appointmentSlotLabels as $slotLabel): ?>
                                <span class="appointment-slot-pill"><?= htmlspecialchars($slotLabel, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if ($appointmentUnavailableDates !== []): ?>
                        <hr class="appointment-settings-divider">
                        <div class="appointment-settings-summary-section">
                            <h5>Unavailable Dates</h5>
                            <div class="appointment-unavailable-list">
                                <?php foreach ($appointmentUnavailableDates as $isoDate): ?>
                                    <span class="appointment-unavailable-pill"><?= htmlspecialchars(aps_format_date_label($isoDate), ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
        <?php elseif ($isAppointmentScheduleView): ?>
        <div id="div-tableContainer" class="bg-white p-4 rounded-4 shadow-sm border appointment-tracker-shell appointment-settings-shell">
            <p class="appointment-settings-lead">Set the recurring weekly appointment schedule and meeting location for each barangay council member. SuperAdmin and the Barangay Secretary can manage everyone, while barangay officials can update their own weekly coverage and venue.</p>

            <div class="appointment-schedule-editor-shell">
                <section class="appointment-settings-card appointment-schedule-overview">
                    <h5><?= !empty($appointmentAccess['can_manage_all_schedule']) ? 'Select Council Member' : 'My Appointment Schedule' ?></h5>

                    <?php if ($appointmentCouncilMembers === []): ?>
                        <p class="text-danger mb-0">No active barangay council members are currently available for appointment scheduling.</p>
                    <?php elseif ($appointmentSelectedScheduleMember === null): ?>
                        <p class="text-danger mb-0">The selected council member is not currently available for appointment scheduling.</p>
                    <?php else: ?>
                        <?php if (!empty($appointmentAccess['can_manage_all_schedule'])): ?>
                            <div class="appointment-settings-field">
                                <label for="appointmentScheduleOfficialSelector">Council member</label>
                                <select class="form-select" id="appointmentScheduleOfficialSelector">
                                    <?php foreach ($appointmentCouncilMembers as $member): ?>
                                        <?php $scheduleMemberUserId = trim((string)($member['user_id'] ?? '')); ?>
                                        <option value="<?= htmlspecialchars($scheduleMemberUserId, ENT_QUOTES, 'UTF-8') ?>" <?= $scheduleMemberUserId === $appointmentScheduleSelectedUserId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars((string)($member['option_label'] ?? $member['full_name'] ?? $scheduleMemberUserId), ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <div class="appointment-schedule-member-name"><?= htmlspecialchars((string)($appointmentSelectedScheduleMember['option_label'] ?? $appointmentSelectedScheduleMember['full_name'] ?? $appointmentScheduleSelectedUserId), ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>

                        <p class="appointment-schedule-hint mb-0">Residents can only book dates that match this weekly schedule, stay inside the global booking window, and land on one of the currently generated appointment slots.</p>

                        <ul class="appointment-schedule-summary-list">
                            <li>Global office coverage: <?= htmlspecialchars($appointmentScheduleCoverageLabel, ENT_QUOTES, 'UTF-8') ?></li>
                            <li>Slot interval: <?= htmlspecialchars((string)($appointmentSettings['slot_interval_minutes'] ?? 30), ENT_QUOTES, 'UTF-8') ?> minutes</li>
                            <li>Open weekdays: <?= htmlspecialchars($appointmentAvailableWeekdayLabels, ENT_QUOTES, 'UTF-8') ?></li>
                            <li>Global lunch break: <?= htmlspecialchars($appointmentLunchBreakLabel, ENT_QUOTES, 'UTF-8') ?></li>
                            <li>Blocked dates: <?= htmlspecialchars($appointmentUnavailableDatesSummary, ENT_QUOTES, 'UTF-8') ?></li>
                            <li>Saved meeting locations: <?= htmlspecialchars($appointmentMeetingLocationsSummary, ENT_QUOTES, 'UTF-8') ?></li>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="appointment-settings-card">
                    <h5>Weekly Meeting Schedule</h5>
                    <?php if ($appointmentSelectedScheduleMember === null): ?>
                        <p class="mb-0 text-muted">Choose a council member to edit their weekly appointment coverage and meeting location.</p>
                    <?php else: ?>
                        <?php if ($appointmentMeetingLocations === []): ?>
                            <p class="text-danger mb-3">No meeting locations are configured yet. Add them first in Appointment Settings before saving weekly schedules.</p>
                        <?php endif; ?>
                        <form method="post" action="<?= htmlspecialchars(appUrl('Admin-End/Appointments/AppointmentTracker.php?tool=schedule'), ENT_QUOTES, 'UTF-8') ?>" class="appointment-settings-form" id="appointmentOfficialScheduleForm">
                            <?= csrfTokenField() ?>
                            <input type="hidden" name="action" value="save_appointment_official_schedule">
                            <input type="hidden" name="official_user_id" id="appointmentScheduleOfficialUserId" value="<?= htmlspecialchars($appointmentScheduleSelectedUserId, ENT_QUOTES, 'UTF-8') ?>">

                            <div class="appointment-week-schedule-grid">
                                <?php foreach ($appointmentScheduleWeekdayOptions as $weekdayValue => $weekdayLabel): ?>
                                    <?php $weekdayValue = (int)$weekdayValue; ?>
                                    <?php $daySchedule = $appointmentScheduleRows[$weekdayValue] ?? apos_default_daily_schedule($appointmentSettings, $weekdayValue); ?>
                                    <?php
                                        $dayStartValue = trim((string)($daySchedule['start_time'] ?? ''));
                                        $dayEndValue = trim((string)($daySchedule['end_time'] ?? ''));
                                        $dayLocationValue = trim((string)($daySchedule['meeting_location'] ?? ''));
                                        $dayStartOptions = $appointmentScheduleTimeOptions;
                                        $dayEndOptions = $appointmentScheduleTimeOptions;
                                        if ($dayStartValue !== '' && !isset($dayStartOptions[$dayStartValue])) {
                                            $dayStartOptions[$dayStartValue] = date('h:i A', strtotime($dayStartValue));
                                            ksort($dayStartOptions, SORT_STRING);
                                        }
                                        if ($dayEndValue !== '' && !isset($dayEndOptions[$dayEndValue])) {
                                            $dayEndOptions[$dayEndValue] = date('h:i A', strtotime($dayEndValue));
                                            ksort($dayEndOptions, SORT_STRING);
                                        }
                                        $dayLocationOptions = $appointmentMeetingLocations;
                                        if ($dayLocationValue !== '' && !in_array($dayLocationValue, $dayLocationOptions, true)) {
                                            array_unshift($dayLocationOptions, $dayLocationValue);
                                        }
                                    ?>
                                    <div class="appointment-week-schedule-row<?= !empty($daySchedule['enabled']) ? '' : ' is-disabled' ?>" data-schedule-row="<?= $weekdayValue ?>">
                                        <label class="appointment-week-schedule-day" for="appointmentWeekdayEnabled<?= $weekdayValue ?>">
                                            <input
                                                type="checkbox"
                                                id="appointmentWeekdayEnabled<?= $weekdayValue ?>"
                                                name="weekly_schedule[<?= $weekdayValue ?>][enabled]"
                                                value="1"
                                                data-schedule-toggle="<?= $weekdayValue ?>"
                                                <?= !empty($daySchedule['enabled']) ? 'checked' : '' ?>
                                            >
                                            <span><?= htmlspecialchars($weekdayLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                        </label>
                                        <div class="appointment-week-schedule-field">
                                            <span class="appointment-week-schedule-field-label">Start Time</span>
                                            <select
                                                class="form-select"
                                                name="weekly_schedule[<?= $weekdayValue ?>][start_time]"
                                                data-schedule-start="<?= $weekdayValue ?>"
                                                <?= !empty($daySchedule['enabled']) ? '' : 'disabled' ?>
                                            >
                                                <option value="">Select start time</option>
                                                <?php foreach ($dayStartOptions as $timeValue => $timeLabel): ?>
                                                    <option value="<?= htmlspecialchars($timeValue, ENT_QUOTES, 'UTF-8') ?>" <?= $dayStartValue === $timeValue ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="appointment-week-schedule-field">
                                            <span class="appointment-week-schedule-field-label">End Time</span>
                                            <select
                                                class="form-select"
                                                name="weekly_schedule[<?= $weekdayValue ?>][end_time]"
                                                data-schedule-end="<?= $weekdayValue ?>"
                                                <?= !empty($daySchedule['enabled']) ? '' : 'disabled' ?>
                                            >
                                                <option value="">Select end time</option>
                                                <?php foreach ($dayEndOptions as $timeValue => $timeLabel): ?>
                                                    <option value="<?= htmlspecialchars($timeValue, ENT_QUOTES, 'UTF-8') ?>" <?= $dayEndValue === $timeValue ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="appointment-week-schedule-field">
                                            <span class="appointment-week-schedule-field-label">Meeting Location</span>
                                            <select
                                                class="form-select"
                                                name="weekly_schedule[<?= $weekdayValue ?>][meeting_location]"
                                                data-schedule-location="<?= $weekdayValue ?>"
                                                <?= !empty($daySchedule['enabled']) ? '' : 'disabled' ?>
                                            >
                                                <option value=""><?= htmlspecialchars($appointmentScheduleLocationPlaceholder, ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php foreach ($dayLocationOptions as $locationOption): ?>
                                                    <option value="<?= htmlspecialchars($locationOption, ENT_QUOTES, 'UTF-8') ?>" <?= $dayLocationValue === $locationOption ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($locationOption, ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="appointment-settings-actions">
                                <button type="submit" class="btn btn-primary" disabled>Save Schedule</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>
            </div>
        </div>
        <?php else: ?>
        <ul class="nav nav-tabs mb-0" id="appointmentTrackerPageTabs" style="border-bottom:0">
            <li class="nav-item">
                <button class="nav-link appointment-date-scope-tab <?= $appointmentDateScope === '' ? 'active' : '' ?> fw-semibold" type="button" data-date-scope="">All</button>
            </li>
            <li class="nav-item">
                <button class="nav-link appointment-date-scope-tab <?= $appointmentDateScope === 'today' ? 'active' : '' ?> fw-semibold" type="button" data-date-scope="today">Appointments Today</button>
            </li>
            <li class="nav-item">
                <button class="nav-link appointment-date-scope-tab <?= $appointmentDateScope === 'tomorrow' ? 'active' : '' ?> fw-semibold" type="button" data-date-scope="tomorrow">Appointments Tomorrow</button>
            </li>
        </ul>

        <div id="appointmentTrackerPanel" class="bg-white p-4 rounded-4 shadow-sm border appointment-tracker-shell resident-masterlist-shell">

            <div class="admin-list-toolbar mb-3 pt-2 flex-wrap">
                <div class="admin-list-tabs">
                    <button class="btn btn-outline-primary btn-sm status-filter-btn active" type="button" data-filter="">&nbsp;&nbsp;All&nbsp;&nbsp;</button>
                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold" type="button" data-filter="approved">&nbsp;&nbsp;Confirmed&nbsp;&nbsp;</button>
                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold" type="button" data-filter="completed">&nbsp;&nbsp;Completed&nbsp;&nbsp;</button>
                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold" type="button" data-filter="rescheduled">&nbsp;&nbsp;Rescheduled&nbsp;&nbsp;</button>
                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold" type="button" data-filter="denied">&nbsp;&nbsp;Denied&nbsp;&nbsp;</button>
                </div>

                <div class="admin-list-actions">
                    <div class="input-group admin-search">
                        <input type="text" id="searchInput" class="form-control" placeholder="Appointment ID, applicant, source, official, subject, purpose">
                        <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                    </div>
                    <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars(appUrl('/Admin-End/Appointments/WalkInAppointmentForm.php'), ENT_QUOTES, 'UTF-8') ?>">
                        <i class="fa-solid fa-plus me-1"></i> Encode Walk-in
                    </a>
                    <button class="btn btn-outline-secondary btn-icon admin-filter" type="button" data-bs-toggle="modal" data-bs-target="#modalAppointmentTrackerFilter" id="btnAppointmentFilter" title="Filter" aria-label="Filter">
                        <i class="fa-solid fa-filter"></i>
                        <span class="visually-hidden">Filter</span>
                    </button>
                    <button class="btn btn-outline-secondary btn-icon admin-columns" type="button" data-bs-toggle="modal" data-bs-target="#modalTableColumns" id="btnAppointmentColumns" title="Columns" aria-label="Columns">
                        <i class="fa-solid fa-sliders"></i>
                        <span class="visually-hidden">Columns</span>
                    </button>
                    <button class="btn btn-outline-secondary btn-icon admin-refresh" type="button" id="btnAppointmentTableRefresh" title="Refresh table" aria-label="Refresh table">
                        <i class="fa-solid fa-arrows-rotate"></i>
                        <span class="visually-hidden">Refresh</span>
                    </button>
                </div>
            </div>

            <div class="table-responsive compact-admin-table-shell">
                <table id="table-appointmentData" class="table align-middle compact-admin-table">
                    <thead>
                        <tr class="table-light">
                            <th>Appointment ID</th>
                                    <th>Date Submitted</th>
                                    <th>Applicant</th>
                                    <th>Subject</th>
                                    <th>Schedule</th>
                                    <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php if ($loadError !== ''): ?>
                            <tr>
                                <td colspan="7" class="text-start text-muted py-4"><?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php elseif ($appointmentRows === []): ?>
                            <tr>
                                <td colspan="7" class="text-start text-muted py-4">No appointment records found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($appointmentRows as $row): ?>
                                <tr
                                    class="<?= $highlightAppointmentId !== '' && $highlightAppointmentId === $row['appointment_id'] ? 'table-warning' : '' ?>"
                                    data-status="<?= htmlspecialchars($row['status_key'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-schedule-date="<?= htmlspecialchars(substr((string)($row['confirmed_schedule_timestamp'] !== '' ? $row['confirmed_schedule_timestamp'] : $row['preferred_schedule_timestamp']), 0, 10), ENT_QUOTES, 'UTF-8') ?>"
                                    data-official-user-id="<?= htmlspecialchars($row['official_user_id'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-official-name="<?= htmlspecialchars($row['official_name'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-search="<?= htmlspecialchars(strtolower(implode(' ', [
                                        $row['appointment_id'],
                                        $row['resident_name'],
                                        $row['booking_channel'],
                                        $row['official_name'],
                                        $row['subject'],
                                        $row['purpose'],
                                        $row['status_name'],
                                    ])), ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    <td><?= htmlspecialchars($row['appointment_id'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($row['request_timestamp_display'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <div class="appointment-cell-main"><?= htmlspecialchars($row['resident_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="appointment-cell-subtitle"><?= htmlspecialchars($row['booking_channel'], ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($row['subject'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <div class="appointment-cell-main"><?= htmlspecialchars($row['schedule_display'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php if ($row['schedule_subtitle'] !== ''): ?>
                                            <div class="appointment-cell-subtitle"><?= htmlspecialchars($row['schedule_subtitle'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="appointment-status-stack">
                                            <span class="status-pill <?= htmlspecialchars($row['status_pill'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($row['status_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if ($row['status_subtitle'] !== ''): ?>
                                                <div class="appointment-cell-subtitle"><?= htmlspecialchars($row['status_subtitle'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <button
                                            class="btn btn-sm btn-primary"
                                            type="button"
                                            data-bs-toggle="modal"
                                            data-bs-target="#viewModal"
                                            data-appointment-id="<?= htmlspecialchars($row['appointment_id'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-request-timestamp="<?= htmlspecialchars($row['request_timestamp_display'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-resident-name="<?= htmlspecialchars($row['resident_name'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-contact-number="<?= htmlspecialchars($row['contact_number'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-email-address="<?= htmlspecialchars($row['email_address'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-booking-channel="<?= htmlspecialchars($row['booking_channel'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-subject="<?= htmlspecialchars($row['subject'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-purpose="<?= htmlspecialchars($row['purpose'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-preferred-appointment-date="<?= htmlspecialchars($row['preferred_appointment_date'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-preferred-appointment-time="<?= htmlspecialchars($row['preferred_appointment_time'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-confirmed-appointment-date="<?= htmlspecialchars($row['confirmed_appointment_date'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-confirmed-appointment-time="<?= htmlspecialchars($row['confirmed_appointment_time'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-preferred-schedule-timestamp="<?= htmlspecialchars($row['preferred_schedule_timestamp'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-confirmed-schedule-timestamp="<?= htmlspecialchars($row['confirmed_schedule_timestamp'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-status-name="<?= htmlspecialchars($row['status_name'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-staff-name="<?= htmlspecialchars($row['staff_name'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-official-user-id="<?= htmlspecialchars($row['official_user_id'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-official-name="<?= htmlspecialchars($row['official_name'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-meeting-location="<?= htmlspecialchars($row['meeting_location'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-resident-notes="<?= htmlspecialchars($row['resident_notes'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-appointment-remarks="<?= htmlspecialchars($row['appointment_remarks'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-review-timestamp="<?= htmlspecialchars($row['review_timestamp_display'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-status-key="<?= htmlspecialchars($row['status_key'], ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                            View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="resident-table-footer mt-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <label for="entriesPerPageInput" class="small text-muted mb-0">Entries</label>
                    <input id="entriesPerPageInput" type="number" min="1" step="1" value="20" class="form-control form-control-sm resident-entries-input">
                </div>
                <nav aria-label="Appointment pagination">
                    <ul class="pagination pagination-sm mb-0" id="appointmentPagination"></ul>
                </nav>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<div class="modal fade" id="modalTableColumns" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Columns</h5>
            </div>
            <div class="modal-body">
                <div class="row g-2" id="tableColumnsList"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="btnTableColumnsReset">Reset</button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAppointmentTrackerFilter" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Filter Appointments</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="appointment-filter-grid">
                    <div>
                        <label class="form-label small fw-bold mb-1" for="appointmentFilterDateFrom">Schedule From</label>
                        <input type="date" class="form-control" id="appointmentFilterDateFrom">
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1" for="appointmentFilterDateTo">Schedule To</label>
                        <input type="date" class="form-control" id="appointmentFilterDateTo">
                    </div>
                    <?php if (!empty($appointmentAccess['can_manage_all_tracker'])): ?>
                    <div class="full-width">
                        <label class="form-label small fw-bold mb-1" for="appointmentFilterOfficial">Council Member</label>
                        <select class="form-select" id="appointmentFilterOfficial">
                            <option value="">All council members</option>
                            <?php foreach ($officialOptions as $official): ?>
                                <option value="<?= htmlspecialchars((string)($official['user_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars((string)($official['option_label'] !== '' ? $official['option_label'] : ($official['full_name'] !== '' ? $official['full_name'] : $official['user_id'])), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="appointment-filter-summary mt-3">
                    Date filters apply to the appointment schedule date shown in the tracker.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="btnAppointmentFilterReset">Reset</button>
                <button type="button" class="btn btn-primary" id="btnAppointmentFilterApply">Apply Filter</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="appointmentFeedbackModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="appointmentFeedbackModalTitle">Appointment Update</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="appointmentFeedbackModalMessage">-</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade tracker-profile-modal" id="viewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width: 1500px; width: 75vw;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="viewDetailsBody" class="tracker-profile-view">
                    <section class="tracker-form-section">
                        <h6 class="tracker-form-section-title">Appointment Summary</h6>
                        <div class="tracker-form-grid cols-4">
                            <div class="tracker-form-field"><p class="tracker-form-label">Appointment ID</p><div class="tracker-form-value" id="viewAppointmentId">-</div></div>
                            <div class="tracker-form-field"><p class="tracker-form-label">Date Submitted</p><div class="tracker-form-value" id="viewRequestTimestamp">-</div></div>
                            <div class="tracker-form-field"><p class="tracker-form-label">Status</p><div class="tracker-form-value" id="viewStatusName">-</div></div>
                            <div class="tracker-form-field"><p class="tracker-form-label">Review Timestamp</p><div class="tracker-form-value" id="viewReviewTimestamp">-</div></div>
                        </div>
                    </section>

                    <section class="tracker-form-section">
                        <h6 class="tracker-form-section-title">Applicant and Assignment</h6>
                        <div class="tracker-form-grid cols-3">
                            <div class="tracker-form-field"><p class="tracker-form-label">Applicant Name</p><div class="tracker-form-value" id="viewResidentName">-</div></div>
                            <div class="tracker-form-field"><p class="tracker-form-label">Contact Number</p><div class="tracker-form-value" id="viewContactNumber">-</div></div>
                            <div class="tracker-form-field"><p class="tracker-form-label">Email Address</p><div class="tracker-form-value" id="viewEmailAddress">-</div></div>
                            <div class="tracker-form-field"><p class="tracker-form-label">Booking Source</p><div class="tracker-form-value" id="viewBookingChannel">-</div></div>
                            <div class="tracker-form-field"><p class="tracker-form-label">Reviewed By Staff</p><div class="tracker-form-value" id="viewStaffName">-</div></div>
                            <div class="tracker-form-field"><p class="tracker-form-label">Council Member</p><div class="tracker-form-value" id="viewOfficialName">-</div></div>
                        </div>
                    </section>

                    <section class="tracker-form-section">
                        <h6 class="tracker-form-section-title">Appointment Details</h6>
                        <div class="tracker-form-grid cols-1">
                            <div class="tracker-form-field"><p class="tracker-form-label">Subject</p><div class="tracker-form-value" id="viewSubject">-</div></div>
                        </div>
                        <div class="tracker-form-grid cols-2">
                            <div class="tracker-form-field"><p class="tracker-form-label">Preferred Date</p><div class="tracker-form-value" id="viewPreferredAppointmentDate">-</div></div>
                            <div class="tracker-form-field"><p class="tracker-form-label">Preferred Time</p><div class="tracker-form-value" id="viewPreferredAppointmentTime">-</div></div>
                        </div>
                        <div class="tracker-form-grid cols-2">
                            <div class="tracker-form-field"><p class="tracker-form-label">Confirmed Date</p><div class="tracker-form-value" id="viewConfirmedAppointmentDate">-</div></div>
                            <div class="tracker-form-field"><p class="tracker-form-label">Confirmed Time</p><div class="tracker-form-value" id="viewConfirmedAppointmentTime">-</div></div>
                        </div>
                        <div class="tracker-form-grid cols-1">
                            <div class="tracker-form-field"><p class="tracker-form-label">Meeting Location</p><div class="tracker-form-value" id="viewMeetingLocation">-</div></div>
                        </div>
                        <div class="tracker-form-grid cols-1">
                            <div class="tracker-form-field"><p class="tracker-form-label">Purpose</p><div class="tracker-form-value" id="viewPurpose">-</div></div>
                        </div>
                    </section>

                    <section class="tracker-form-section">
                        <h6 class="tracker-form-section-title">Notes</h6>
                        <div class="tracker-form-grid cols-1">
                            <div class="tracker-form-field"><p class="tracker-form-label">Applicant Notes</p><div class="tracker-form-value" id="viewResidentNotes">-</div></div>
                            <div class="tracker-form-field"><p class="tracker-form-label">Appointment Remarks</p><div class="tracker-form-value" id="viewAppointmentRemarks">-</div></div>
                        </div>
                    </section>

                </div>
            </div>
            <div class="modal-footer justify-content-between flex-wrap gap-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <div class="d-flex flex-wrap gap-2 d-none" id="reviewActionFooter">
                    <button type="button" class="btn btn-danger" data-review-action="deny_appointment">Deny</button>
                    <button type="button" class="btn btn-warning" data-review-action="reschedule_appointment">Reschedule</button>
                    <button type="button" class="btn btn-success" data-review-action="complete_appointment">Complete</button>
                </div>
            </div>
        </div>
    </div>
</div>

<form method="post" action="<?= htmlspecialchars(appUrl('/PhpFiles/Admin-End/appointmentManagement.php'), ENT_QUOTES, 'UTF-8') ?>" id="appointmentReviewForm" class="d-none">
    <?= csrfTokenField() ?>
    <input type="hidden" name="appointment_id" id="reviewAppointmentId" value="">
    <input type="hidden" name="action" id="reviewActionInput" value="">
    <input type="hidden" name="official_user_id" id="reviewOfficialUserId" value="">
</form>

<div class="modal fade" id="appointmentActionConfirmModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="appointmentActionConfirmTitle">Confirm Review Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2" id="appointmentActionConfirmMessage">This action cannot be undone.</p>
                <div class="tracker-form-field d-none" id="appointmentActionScheduleFields">
                    <div class="tracker-form-grid cols-2">
                        <div class="tracker-form-field">
                            <label class="tracker-form-label" for="reviewConfirmedDate">Reschedule Date</label>
                            <input class="form-control" type="date" name="confirmed_date" id="reviewConfirmedDate" form="appointmentReviewForm" min="<?= htmlspecialchars($minConfirmedDate, ENT_QUOTES, 'UTF-8') ?>" max="<?= htmlspecialchars($maxConfirmedDate, ENT_QUOTES, 'UTF-8') ?>" data-disabled-weekdays="<?= htmlspecialchars(implode(',', $appointmentDisabledWeekdays), ENT_QUOTES, 'UTF-8') ?>" data-disabled-dates="<?= htmlspecialchars($appointmentUnavailableDatesCsv, ENT_QUOTES, 'UTF-8') ?>" data-available-weekdays="<?= htmlspecialchars($appointmentAvailableWeekdayLabels, ENT_QUOTES, 'UTF-8') ?>" data-date-modal-style="calendar">
                        </div>
                        <div class="tracker-form-field">
                            <label class="tracker-form-label" for="reviewConfirmedTime">Reschedule Time</label>
                            <select class="form-select" name="confirmed_time" id="reviewConfirmedTime" form="appointmentReviewForm">
                                <option value="">Select allotted time</option>
                                <?php foreach ($appointmentTimeSlots as $slotValue => $slotLabel): ?>
                                    <option value="<?= htmlspecialchars($slotValue, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($slotLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="text-danger small d-none mt-2" id="reviewScheduleError">
                        Rescheduled time must be within office hours, from 9:00 AM to 5:00 PM.
                    </div>
                </div>
                <div class="tracker-form-field d-none" id="appointmentActionRemarksField">
                    <label class="tracker-form-label" for="reviewAppointmentRemarks">Denial Reason</label>
                    <textarea class="form-control" name="appointment_remarks" id="reviewAppointmentRemarks" rows="3" form="appointmentReviewForm" placeholder="Add denial reason" maxlength="140"></textarea>
                    <div class="text-muted small mt-1">Keep the denial reason within 140 characters so the SMS stays within one message.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="appointmentActionReturnBtn">Return</button>
                <button type="button" class="btn btn-primary" id="appointmentActionConfirmBtn">Continue</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    window.ADMIN_TABLE_COLUMNS_CONFIG = {
        tableSelector: "#table-appointmentData",
        modalId: "modalTableColumns",
        listId: "tableColumnsList",
        resetBtnId: "btnTableColumnsReset",
        storageKey: "admin_cols_appointment_tracker_v1",
        defaultHiddenIdxs: []
    };
    window.APPOINTMENT_OFFICIAL_SCHEDULE_MAP = <?= json_encode($appointmentScheduleMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.APPOINTMENT_BOOKED_SLOT_MAP = <?= json_encode($appointmentBookedSlotMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.APPOINTMENT_MEETING_LOCATION_OPTIONS = <?= json_encode(array_values($appointmentMeetingLocations), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.APPOINTMENT_GLOBAL_SCHEDULE_CONFIG = <?= json_encode([
        'startTime' => aps_schedule_start_time(),
        'endTime' => aps_schedule_end_time(),
        'slotIntervalMinutes' => (int)($appointmentSettings['slot_interval_minutes'] ?? 30),
        'disabledWeekdays' => array_values(array_map('intval', $appointmentDisabledWeekdays)),
        'disabledDates' => array_values($appointmentUnavailableDates),
        'minDate' => $minConfirmedDate,
        'maxDate' => $maxConfirmedDate,
        'availableWeekdaysLabel' => $appointmentAvailableWeekdayLabels,
        'lunchBreak' => $appointmentLunchBreakEnabled ? [
            'start' => $appointmentLunchStartTime,
            'end' => $appointmentLunchEndTime,
        ] : null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="../../JS-Script-Files/Admin-End/tableColumnsGeneric.js?v=20260215-1"></script>
<script src="../../JS-Script-Files/Resident-End/dateFieldModal.js?v=20260707-date-proxy-white"></script>
<script>
    (() => {
        const feedbackSuccessMessage = <?= json_encode($appointmentSuccessMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const feedbackErrorMessage = <?= json_encode($appointmentErrorMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const feedbackInfoMessage = <?= json_encode($appointmentInfoMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const initialDateScope = <?= json_encode($appointmentDateScope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const todayIso = <?= json_encode($appointmentToday->format('Y-m-d'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const tomorrowIso = <?= json_encode($appointmentToday->modify('+1 day')->format('Y-m-d'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const trackerPanel = document.getElementById("appointmentTrackerPanel");
        const tableBody = document.getElementById("tableBody");
        const searchInput = document.getElementById("searchInput");
        const entriesPerPageInput = document.getElementById("entriesPerPageInput");
        const paginationEl = document.getElementById("appointmentPagination");
        const refreshBtn = document.getElementById("btnAppointmentTableRefresh");
        const statusFilterButtons = Array.from(document.querySelectorAll(".status-filter-btn"));
        const dateScopeButtons = Array.from(document.querySelectorAll(".appointment-date-scope-tab"));
        const appointmentFilterBtn = document.getElementById("btnAppointmentFilter");
        const appointmentFilterModalEl = document.getElementById("modalAppointmentTrackerFilter");
        const appointmentFilterDateFrom = document.getElementById("appointmentFilterDateFrom");
        const appointmentFilterDateTo = document.getElementById("appointmentFilterDateTo");
        const appointmentFilterOfficial = document.getElementById("appointmentFilterOfficial");
        const appointmentFilterResetBtn = document.getElementById("btnAppointmentFilterReset");
        const appointmentFilterApplyBtn = document.getElementById("btnAppointmentFilterApply");
        const reviewForm = document.getElementById("appointmentReviewForm");
        const reviewActionInput = document.getElementById("reviewActionInput");
        const reviewAppointmentId = document.getElementById("reviewAppointmentId");
        const reviewOfficialUserId = document.getElementById("reviewOfficialUserId");
        const reviewConfirmedDate = document.getElementById("reviewConfirmedDate");
        const reviewConfirmedTime = document.getElementById("reviewConfirmedTime");
        const reviewMeetingLocation = document.getElementById("reviewMeetingLocation");
        const reviewScheduleError = document.getElementById("reviewScheduleError");
        const reviewRemarks = document.getElementById("reviewAppointmentRemarks");
        const reviewActionButtons = Array.from(document.querySelectorAll("[data-review-action]"));
        const reviewActionButtonsByAction = Object.fromEntries(
            reviewActionButtons.map((button) => [String(button.dataset.reviewAction || "").trim(), button])
        );
        const reviewActionFooter = document.getElementById("reviewActionFooter");
        const feedbackModalEl = document.getElementById("appointmentFeedbackModal");
        const feedbackModalTitle = document.getElementById("appointmentFeedbackModalTitle");
        const feedbackModalMessage = document.getElementById("appointmentFeedbackModalMessage");
        const modal = document.getElementById("viewModal");
        const confirmModalEl = document.getElementById("appointmentActionConfirmModal");
        const confirmModalTitle = document.getElementById("appointmentActionConfirmTitle");
        const confirmModalMessage = document.getElementById("appointmentActionConfirmMessage");
        const confirmScheduleFields = document.getElementById("appointmentActionScheduleFields");
        const confirmRemarksField = document.getElementById("appointmentActionRemarksField");
        const confirmModalReturnBtn = document.getElementById("appointmentActionReturnBtn");
        const confirmModalConfirmBtn = document.getElementById("appointmentActionConfirmBtn");
        const viewModalInstance = modal ? bootstrap.Modal.getOrCreateInstance(modal) : null;
        const confirmModalInstance = confirmModalEl ? bootstrap.Modal.getOrCreateInstance(confirmModalEl) : null;
        const appointmentFilterModalInstance = appointmentFilterModalEl ? bootstrap.Modal.getOrCreateInstance(appointmentFilterModalEl) : null;

        let allRows = Array.from(tableBody?.querySelectorAll("tr") || []).filter((row) => row.dataset.status !== undefined);
        let currentPage = 1;
        let activeStatusFilter = "";
        let activeDateScope = ["today", "tomorrow"].includes(initialDateScope) ? initialDateScope : "";
        let activeDateFrom = "";
        let activeDateTo = "";
        let activeOfficialUserId = "";
        let pendingReviewAction = "";
        let renderQueued = false;
        let renderPromise = null;
        let searchInputDebounce = null;
        const AUTO_REFRESH_MS = 30000;
        let autoRefreshTimeout = null;
        let autoRefreshInFlight = false;
        const officialScheduleMap = window.APPOINTMENT_OFFICIAL_SCHEDULE_MAP || {};
        const bookedSlotMap = window.APPOINTMENT_BOOKED_SLOT_MAP || {};
        const globalScheduleConfig = window.APPOINTMENT_GLOBAL_SCHEDULE_CONFIG || {};
        const disabledWeekdays = new Set((Array.isArray(globalScheduleConfig.disabledWeekdays) ? globalScheduleConfig.disabledWeekdays : []).map((value) => String(value)));
        const disabledDates = new Set(Array.isArray(globalScheduleConfig.disabledDates) ? globalScheduleConfig.disabledDates : []);
        const currentBookingMoment = () => {
            const now = new Date();
            return {
                isoDate: `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}-${String(now.getDate()).padStart(2, "0")}`,
                minutes: (now.getHours() * 60) + now.getMinutes(),
            };
        };

        const parseIsoDate = (value) => {
            const text = String(value || "").trim();
            const match = text.match(/^(\d{4})-(\d{2})-(\d{2})$/);
            if (!match) {
                return null;
            }
            return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
        };

        const toMinutes = (value) => {
            const match = String(value || "").trim().match(/^(\d{2}):(\d{2})$/);
            if (!match) {
                return null;
            }
            return (Number(match[1]) * 60) + Number(match[2]);
        };

        const formatTimeLabel = (value) => {
            const totalMinutes = toMinutes(value);
            if (totalMinutes === null) {
                return String(value || "").trim() || "-";
            }
            const hour24 = Math.floor(totalMinutes / 60);
            const minute = totalMinutes % 60;
            const hour12 = hour24 % 12 === 0 ? 12 : hour24 % 12;
            const suffix = hour24 >= 12 ? "PM" : "AM";
            return `${String(hour12).padStart(2, "0")}:${String(minute).padStart(2, "0")} ${suffix}`;
        };

        const formatReviewDate = (value) => {
            const parsed = parseIsoDate(value);
            if (!parsed) {
                return String(value || "").trim() || "-";
            }
            return parsed.toLocaleDateString("en-US", {
                month: "short",
                day: "numeric",
                year: "numeric",
            });
        };

        const formatReviewTime = (value) => formatTimeLabel(value);

        const nextPaint = () => new Promise((resolve) => {
            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(resolve);
            });
        });

        const setButtonLoading = (button, on, loadingLabel = "") => {
            if (!(button instanceof HTMLElement)) {
                return;
            }

            if (on) {
                if (!button.dataset.originalHtml) {
                    button.dataset.originalHtml = button.innerHTML;
                }
                if (!button.dataset.originalWidth) {
                    button.dataset.originalWidth = String(button.getBoundingClientRect().width || 0);
                }
                if (loadingLabel !== "") {
                    button.textContent = loadingLabel;
                }
                button.classList.add("appointment-click-loading");
                button.setAttribute("aria-busy", "true");
                button.setAttribute("disabled", "disabled");
                const lockedWidth = Number(button.dataset.originalWidth || 0);
                if (lockedWidth > 0) {
                    button.style.width = `${lockedWidth}px`;
                }
                return;
            }

            if (button.dataset.originalHtml) {
                button.innerHTML = button.dataset.originalHtml;
            }
            button.classList.remove("appointment-click-loading");
            button.removeAttribute("aria-busy");
            button.removeAttribute("disabled");
            button.style.width = "";
        };

        const withButtonLoading = async (button, work, loadingLabel = "") => {
            setButtonLoading(button, true, loadingLabel);
            trackerPanel?.classList.add("is-busy");
            try {
                await nextPaint();
                return await work();
            } finally {
                trackerPanel?.classList.remove("is-busy");
                setButtonLoading(button, false);
            }
        };

        const submitWithLoading = async (button, loadingLabel = "") => {
            setButtonLoading(button, true, loadingLabel);
            trackerPanel?.classList.add("is-busy");
            await nextPaint();
            reviewForm?.submit();
        };

        const syncAppointmentFilterButtonState = () => {
            const hasActiveFilter = activeDateFrom !== "" || activeDateTo !== "" || activeOfficialUserId !== "";
            appointmentFilterBtn?.classList.toggle("btn-outline-primary", hasActiveFilter);
            appointmentFilterBtn?.classList.toggle("btn-outline-secondary", !hasActiveFilter);
        };

        const syncDateScopeButtons = () => {
            dateScopeButtons.forEach((button) => {
                const scope = String(button.dataset.dateScope || "").trim();
                const isActive = scope === activeDateScope;
                button.classList.toggle("active", isActive);
                button.setAttribute("aria-selected", isActive ? "true" : "false");
            });
        };

        const isWithinOfficeHours = (value) => {
            const minutes = toMinutes(value);
            const minMinutes = toMinutes(globalScheduleConfig.startTime || "");
            const maxMinutes = toMinutes(globalScheduleConfig.endTime || "");
            if (minutes === null || minMinutes === null || maxMinutes === null) {
                return false;
            }
            return minutes >= minMinutes && minutes <= maxMinutes;
        };

        const scheduleDayFor = (officialUserId, isoDate) => {
            const schedule = officialScheduleMap[String(officialUserId || "").trim()] || null;
            const parsedDate = parseIsoDate(isoDate);
            if (!schedule || !parsedDate) {
                return null;
            }

            const weekday = String(parsedDate.getDay());
            if (disabledDates.has(String(isoDate || "").trim()) || disabledWeekdays.has(weekday)) {
                return null;
            }

            const dayEntry = schedule[weekday] || schedule[Number(weekday)] || null;
            if (!dayEntry || dayEntry.enabled !== true) {
                return null;
            }

            return dayEntry;
        };

        const bookedAppointmentIdsFor = (officialUserId, isoDate, timeValue) => {
            const officialKey = String(officialUserId || "").trim();
            const dateKey = String(isoDate || "").trim();
            const timeKey = String(timeValue || "").trim();
            const officialBookings = bookedSlotMap[officialKey] || null;
            if (!officialBookings || !dateKey || !timeKey) {
                return [];
            }
            const dateBookings = officialBookings[dateKey] || null;
            if (!dateBookings) {
                return [];
            }
            return Array.isArray(dateBookings[timeKey]) ? dateBookings[timeKey] : [];
        };

        const buildOfficialSlots = (officialUserId, isoDate, options = {}) => {
            const excludeAppointmentId = String(options.excludeAppointmentId || "").trim();
            const dayEntry = scheduleDayFor(officialUserId, isoDate);
            if (!dayEntry) {
                return { dayEntry: null, slots: [] };
            }

            const startMinutes = Math.max(
                toMinutes(dayEntry.start_time || "") ?? 0,
                toMinutes(globalScheduleConfig.startTime || "") ?? 0
            );
            const endMinutes = Math.min(
                toMinutes(dayEntry.end_time || "") ?? 0,
                toMinutes(globalScheduleConfig.endTime || "") ?? 0
            );
            const interval = Math.max(5, Number(globalScheduleConfig.slotIntervalMinutes || 30));
            if (!Number.isFinite(startMinutes) || !Number.isFinite(endMinutes) || startMinutes >= endMinutes) {
                return { dayEntry: null, slots: [] };
            }

            const lunchBreak = globalScheduleConfig.lunchBreak || null;
            const lunchStart = lunchBreak ? toMinutes(lunchBreak.start || "") : null;
            const lunchEnd = lunchBreak ? toMinutes(lunchBreak.end || "") : null;
            const currentBookingState = currentBookingMoment();
            const slots = [];

            for (let current = startMinutes; current <= endMinutes; current += interval) {
                const slotEnd = current + interval;
                if (
                    lunchStart !== null
                    && lunchEnd !== null
                    && current < lunchEnd
                    && slotEnd > lunchStart
                ) {
                    continue;
                }

                const hours = Math.floor(current / 60);
                const minutes = current % 60;
                const value = `${String(hours).padStart(2, "0")}:${String(minutes).padStart(2, "0")}`;
                if (String(isoDate || "").trim() === currentBookingState.isoDate && current <= currentBookingState.minutes) {
                    continue;
                }
                const bookedIds = bookedAppointmentIdsFor(officialUserId, isoDate, value);
                if (bookedIds.some((appointmentId) => String(appointmentId || "").trim() !== excludeAppointmentId)) {
                    continue;
                }
                slots.push({
                    value,
                    label: formatTimeLabel(value),
                });
            }

            return { dayEntry, slots };
        };

        const populateReviewTimeOptions = (officialUserId, isoDate, selectedValue = "") => {
            if (!reviewConfirmedTime) {
                return [];
            }

            const excludeAppointmentId = String(reviewAppointmentId?.value || "").trim();
            const { slots } = buildOfficialSlots(officialUserId, isoDate, { excludeAppointmentId });
            reviewConfirmedTime.innerHTML = '<option value="">Select allotted time</option>';
            slots.forEach((slot) => {
                const option = document.createElement("option");
                option.value = slot.value;
                option.textContent = slot.label;
                if (selectedValue && selectedValue === slot.value) {
                    option.selected = true;
                }
                reviewConfirmedTime.appendChild(option);
            });

            if (selectedValue && !slots.some((slot) => slot.value === selectedValue)) {
                reviewConfirmedTime.value = "";
            }

            return slots;
        };

        const syncReviewMeetingLocation = () => {
            if (!reviewMeetingLocation) {
                return;
            }
            const officialUserId = String(reviewOfficialUserId?.value || "").trim();
            const isoDate = String(reviewConfirmedDate?.value || "").trim();
            const excludeAppointmentId = String(reviewAppointmentId?.value || "").trim();
            const { dayEntry } = buildOfficialSlots(officialUserId, isoDate, { excludeAppointmentId });
            reviewMeetingLocation.value = dayEntry && String(dayEntry.meeting_location || "").trim()
                ? String(dayEntry.meeting_location || "").trim()
                : "";
        };

        const validateReviewConfirmedDate = () => {
            if (!reviewConfirmedDate) {
                return { ok: true, message: "" };
            }

            const value = String(reviewConfirmedDate.value || "").trim();
            if (value === "") {
                reviewConfirmedDate.setCustomValidity("");
                reviewScheduleError?.classList.add("d-none");
                return { ok: true, message: "" };
            }

            if ((globalScheduleConfig.minDate && value < globalScheduleConfig.minDate) || (globalScheduleConfig.maxDate && value > globalScheduleConfig.maxDate)) {
                const message = "Confirmed appointment date is outside the current booking window.";
                reviewConfirmedDate.setCustomValidity(message);
                return { ok: false, message };
            }

            const parsedDate = parseIsoDate(value);
            const weekday = parsedDate ? String(parsedDate.getDay()) : "";
            if (!parsedDate || disabledDates.has(value) || (weekday !== "" && disabledWeekdays.has(weekday))) {
                const message = "The selected date is unavailable based on the current global appointment settings.";
                reviewConfirmedDate.setCustomValidity(message);
                return { ok: false, message };
            }

            const officialUserId = String(reviewOfficialUserId?.value || "").trim();
            const excludeAppointmentId = String(reviewAppointmentId?.value || "").trim();
            const { dayEntry, slots } = buildOfficialSlots(officialUserId, value, { excludeAppointmentId });
            if (officialUserId !== "" && !dayEntry) {
                const message = "The selected council member is not available on that date.";
                reviewConfirmedDate.setCustomValidity(message);
                return { ok: false, message };
            }
            if (officialUserId !== "" && slots.length === 0) {
                const message = "No remaining appointment times are available for the selected council member on that date.";
                reviewConfirmedDate.setCustomValidity(message);
                return { ok: false, message };
            }

            reviewConfirmedDate.setCustomValidity("");
            reviewScheduleError?.classList.add("d-none");
            return { ok: true, message: "" };
        };

        const syncReviewActionStates = () => {
            const reviewDateValidation = validateReviewConfirmedDate();
            const hasOfficial = String(reviewOfficialUserId?.value || "").trim() !== "";
            const hasDate = String(reviewConfirmedDate?.value || "").trim() !== "";
            const hasTime = String(reviewConfirmedTime?.value || "").trim() !== "";

            reviewActionButtons.forEach((button) => {
                const action = String(button.dataset.reviewAction || "").trim();
                if (action === "reschedule_appointment") {
                    button.disabled = !hasOfficial;
                    return;
                }
                button.disabled = false;
            });
        };

        const syncReviewActionVisibility = (statusKey) => {
            const normalizedStatusKey = String(statusKey || "").trim();
            const isTerminal = normalizedStatusKey === "denied" || normalizedStatusKey === "completed";
            const canModify = !isTerminal;

            reviewActionFooter?.classList.toggle("d-none", !canModify);

            reviewActionButtons.forEach((button) => {
                button.classList.add("d-none");
            });

            if (!canModify) {
                return;
            }

            reviewActionButtonsByAction["deny_appointment"]?.classList.remove("d-none");
            reviewActionButtonsByAction["reschedule_appointment"]?.classList.remove("d-none");

            if (normalizedStatusKey === "approved" || normalizedStatusKey === "rescheduled") {
                reviewActionButtonsByAction["complete_appointment"]?.classList.remove("d-none");
            }
        };

        function setRefreshLoading(on) {
            if (!refreshBtn) return;
            refreshBtn.classList.toggle("is-loading", !!on);
            refreshBtn.disabled = !!on;
        }

        async function refreshTableOnly() {
            if (autoRefreshInFlight || !tableBody) return;
            autoRefreshInFlight = true;
            setRefreshLoading(true);
            try {
                const response = await fetch(window.location.href, {
                    credentials: "same-origin",
                    headers: { "X-Requested-With": "XMLHttpRequest" }
                });
                const html = await response.text();
                if (!response.ok) {
                    throw new Error("Failed to refresh appointments.");
                }
                const doc = new DOMParser().parseFromString(html, "text/html");
                const nextBody = doc.getElementById("tableBody");
                if (!nextBody) {
                    throw new Error("Refreshed appointment table was not found.");
                }
                tableBody.innerHTML = nextBody.innerHTML;
                allRows = Array.from(tableBody.querySelectorAll("tr")).filter((row) => row.dataset.status !== undefined);
                renderTable();
            } catch (error) {
                console.error("Unable to refresh appointment tracker:", error);
            } finally {
                autoRefreshInFlight = false;
                setRefreshLoading(false);
            }
        }

        function triggerRefresh() {
            scheduleAutoRefresh();
            refreshTableOnly().catch(() => {});
        }

        function scheduleAutoRefresh() {
            if (autoRefreshTimeout) window.clearTimeout(autoRefreshTimeout);
            autoRefreshTimeout = window.setTimeout(() => {
                if (autoRefreshInFlight) {
                    scheduleAutoRefresh();
                    return;
                }
                triggerRefresh();
            }, AUTO_REFRESH_MS);
        }

        function renderPagination(total) {
            if (!paginationEl) return;
            const perPage = Math.max(1, Number(entriesPerPageInput?.value || 20));
            const totalPages = Math.max(1, Math.ceil(total / perPage));
            currentPage = Math.min(currentPage, totalPages);

            const makeBtn = (label, page, disabled = false, active = false) => `
                <li class="page-item ${disabled ? "disabled" : ""} ${active ? "active" : ""}">
                    <button class="page-link" data-page="${page}" ${disabled ? "disabled" : ""}>${label}</button>
                </li>
            `;

            const html = [];
            html.push(makeBtn("Prev", currentPage - 1, currentPage <= 1));
            for (let page = 1; page <= totalPages; page += 1) {
                html.push(makeBtn(String(page), page, false, page === currentPage));
            }
            html.push(makeBtn("Next", currentPage + 1, currentPage >= totalPages));
            paginationEl.innerHTML = html.join("");

            paginationEl.querySelectorAll("button[data-page]").forEach((button) => {
                button.addEventListener("click", () => {
                    const page = Number(button.getAttribute("data-page") || 1);
                    if (!Number.isFinite(page)) return;
                    currentPage = page;
                    renderTable();
                });
            });
        }

        function getFilteredRows() {
            const term = String(searchInput?.value || "").trim().toLowerCase();
            return allRows.filter((row) => {
                const scheduleDate = String(row.dataset.scheduleDate || "").trim();
                const statusKey = String(row.dataset.status || "").trim().toLowerCase();
                const officialUserId = String(row.dataset.officialUserId || "").trim();
                const matchesFilter = !activeStatusFilter || statusKey === activeStatusFilter;
                if (!matchesFilter) return false;

                if (activeDateScope === "today") {
                    if (statusKey === "denied") {
                        return false;
                    }
                    if (scheduleDate !== todayIso) {
                        return false;
                    }
                } else if (activeDateScope === "tomorrow") {
                    if (statusKey === "denied") {
                        return false;
                    }
                    if (scheduleDate !== tomorrowIso) {
                        return false;
                    }
                }

                if (activeDateFrom !== "" && (scheduleDate === "" || scheduleDate < activeDateFrom)) {
                    return false;
                }

                if (activeDateTo !== "" && (scheduleDate === "" || scheduleDate > activeDateTo)) {
                    return false;
                }

                if (activeOfficialUserId !== "" && officialUserId !== activeOfficialUserId) {
                    return false;
                }

                if (!term) return true;
                return String(row.dataset.search || "").includes(term);
            });
        }

        function renderTable() {
            renderQueued = false;
            const filteredRows = getFilteredRows();
            const perPage = Math.max(1, Number(entriesPerPageInput?.value || 20));
            const start = (currentPage - 1) * perPage;
            const end = start + perPage;
            const visibleRows = new Set(filteredRows.slice(start, end));

            allRows.forEach((row) => {
                row.style.display = visibleRows.has(row) ? "" : "none";
            });

            renderPagination(filteredRows.length);
        }

        function queueRenderTable() {
            if (renderQueued && renderPromise) {
                return renderPromise;
            }
            renderQueued = true;
            renderPromise = new Promise((resolve) => {
                window.requestAnimationFrame(() => {
                    renderTable();
                    renderPromise = null;
                    resolve();
                });
            });
            return renderPromise;
        }

        function setFilterButtonState(activeValue) {
            statusFilterButtons.forEach((button) => {
                const isActive = String(button.dataset.filter || "") === activeValue;
                button.classList.toggle("active", isActive);
                button.classList.toggle("btn-outline-primary", isActive);
                button.classList.toggle("btn-outline-secondary", !isActive);
            });
        }

        statusFilterButtons.forEach((button) => {
            button.addEventListener("click", async () => {
                await withButtonLoading(button, async () => {
                    activeStatusFilter = String(button.dataset.filter || "").trim().toLowerCase();
                    currentPage = 1;
                    setFilterButtonState(String(button.dataset.filter || ""));
                    await queueRenderTable();
                });
            });
        });

        dateScopeButtons.forEach((button) => {
            button.addEventListener("click", async () => {
                await withButtonLoading(button, async () => {
                    activeDateScope = String(button.dataset.dateScope || "").trim();
                    currentPage = 1;
                    syncDateScopeButtons();
                    await queueRenderTable();
                });
            });
        });

        appointmentFilterApplyBtn?.addEventListener("click", async () => {
            const nextDateFrom = String(appointmentFilterDateFrom?.value || "").trim();
            const nextDateTo = String(appointmentFilterDateTo?.value || "").trim();
            const nextOfficialUserId = String(appointmentFilterOfficial?.value || "").trim();

            if (nextDateFrom !== "" && nextDateTo !== "" && nextDateFrom > nextDateTo) {
                window.alert("Schedule from date cannot be later than the to date.");
                appointmentFilterDateFrom?.focus();
                return;
            }

            await withButtonLoading(appointmentFilterApplyBtn, async () => {
                activeDateFrom = nextDateFrom;
                activeDateTo = nextDateTo;
                activeOfficialUserId = nextOfficialUserId;
                currentPage = 1;
                syncAppointmentFilterButtonState();
                await queueRenderTable();
                appointmentFilterModalInstance?.hide();
            }, "Applying...");
        });

        appointmentFilterResetBtn?.addEventListener("click", async () => {
            await withButtonLoading(appointmentFilterResetBtn, async () => {
                if (appointmentFilterDateFrom) {
                    appointmentFilterDateFrom.value = "";
                }
                if (appointmentFilterDateTo) {
                    appointmentFilterDateTo.value = "";
                }
                if (appointmentFilterOfficial) {
                    appointmentFilterOfficial.value = "";
                }
                activeDateFrom = "";
                activeDateTo = "";
                activeOfficialUserId = "";
                currentPage = 1;
                syncAppointmentFilterButtonState();
                await queueRenderTable();
            }, "Resetting...");
        });

        appointmentFilterModalEl?.addEventListener("show.bs.modal", () => {
            if (appointmentFilterDateFrom) {
                appointmentFilterDateFrom.value = activeDateFrom;
            }
            if (appointmentFilterDateTo) {
                appointmentFilterDateTo.value = activeDateTo;
            }
            if (appointmentFilterOfficial) {
                appointmentFilterOfficial.value = activeOfficialUserId;
            }
        });

        searchInput?.addEventListener("input", () => {
            currentPage = 1;
            if (searchInputDebounce) {
                window.clearTimeout(searchInputDebounce);
            }
            searchInputDebounce = window.setTimeout(() => {
                queueRenderTable();
            }, 120);
        });

        entriesPerPageInput?.addEventListener("change", () => {
            currentPage = 1;
            queueRenderTable();
        });

        refreshBtn?.addEventListener("click", triggerRefresh);
        reviewOfficialUserId?.addEventListener("change", () => {
            populateReviewTimeOptions(reviewOfficialUserId.value || "", reviewConfirmedDate?.value || "", "");
            syncReviewMeetingLocation();
            syncReviewActionStates();
        });
        reviewConfirmedDate?.addEventListener("input", () => {
            populateReviewTimeOptions(reviewOfficialUserId?.value || "", reviewConfirmedDate.value || "", "");
            syncReviewMeetingLocation();
            syncReviewActionStates();
        });
        reviewConfirmedDate?.addEventListener("change", () => {
            populateReviewTimeOptions(reviewOfficialUserId?.value || "", reviewConfirmedDate.value || "", "");
            syncReviewMeetingLocation();
            syncReviewActionStates();
        });
        reviewConfirmedTime?.addEventListener("change", () => {
            reviewScheduleError?.classList.add("d-none");
            syncReviewActionStates();
        });

        modal?.addEventListener("show.bs.modal", (event) => {
            const button = event.relatedTarget;
            if (!(button instanceof HTMLElement)) return;

            const setText = (id, value) => {
                const element = document.getElementById(id);
                if (element) {
                    element.textContent = String(value || "").trim() || "-";
                }
            };

            setText("viewAppointmentId", button.dataset.appointmentId);
            setText("viewRequestTimestamp", button.dataset.requestTimestamp);
            setText("viewStatusName", button.dataset.statusName);
            setText("viewReviewTimestamp", button.dataset.reviewTimestamp);
            setText("viewResidentName", button.dataset.residentName);
            setText("viewContactNumber", button.dataset.contactNumber);
            setText("viewEmailAddress", button.dataset.emailAddress);
            setText("viewBookingChannel", button.dataset.bookingChannel);
            setText("viewStaffName", button.dataset.staffName);
            setText("viewOfficialName", button.dataset.officialName);
            setText("viewSubject", button.dataset.subject);
            setText("viewPreferredAppointmentDate", button.dataset.preferredAppointmentDate);
            setText("viewPreferredAppointmentTime", button.dataset.preferredAppointmentTime);
            setText("viewConfirmedAppointmentDate", button.dataset.confirmedAppointmentDate);
            setText("viewConfirmedAppointmentTime", button.dataset.confirmedAppointmentTime);
            setText("viewMeetingLocation", button.dataset.meetingLocation);
            setText("viewPurpose", button.dataset.purpose);
            setText("viewResidentNotes", button.dataset.residentNotes);
            setText("viewAppointmentRemarks", button.dataset.appointmentRemarks);

            if (reviewAppointmentId) {
                reviewAppointmentId.value = String(button.dataset.appointmentId || "").trim();
            }
            if (modal) {
                modal.dataset.preferredAppointmentDate = String(button.dataset.preferredAppointmentDate || "").trim();
                modal.dataset.preferredAppointmentTime = String(button.dataset.preferredAppointmentTime || "").trim();
                modal.dataset.reviewStatusKey = String(button.dataset.statusKey || "").trim();
            }
            if (reviewOfficialUserId) {
                reviewOfficialUserId.value = String(button.dataset.officialUserId || "").trim();
            }
            if (reviewConfirmedDate) {
                reviewConfirmedDate.value = "";
                const confirmedStamp = String(button.dataset.confirmedScheduleTimestamp || "").trim();
                const preferredStamp = String(button.dataset.preferredScheduleTimestamp || "").trim();
                const stampToUse = confirmedStamp || preferredStamp;
                if (stampToUse) {
                    reviewConfirmedDate.value = stampToUse.slice(0, 10);
                    if (modal) {
                        modal.dataset.originalReviewDate = stampToUse.slice(0, 10);
                    }
                }
            }
            if (reviewConfirmedTime) {
                const confirmedStamp = String(button.dataset.confirmedScheduleTimestamp || "").trim();
                const preferredStamp = String(button.dataset.preferredScheduleTimestamp || "").trim();
                const stampToUse = confirmedStamp || preferredStamp;
                const selectedTime = stampToUse.length >= 16 ? stampToUse.slice(11, 16) : "";
                populateReviewTimeOptions(String(button.dataset.officialUserId || "").trim(), reviewConfirmedDate?.value || "", selectedTime);
                if (selectedTime && modal) {
                    modal.dataset.originalReviewTime = selectedTime;
                }
            }
            if (reviewRemarks) {
                const remarks = String(button.dataset.appointmentRemarks || "").trim();
                reviewRemarks.value = remarks && remarks !== '-' ? remarks : '';
            }
            syncReviewMeetingLocation();
            validateReviewConfirmedDate();

            const statusKey = String(button.dataset.statusKey || "").trim();
            syncReviewActionVisibility(statusKey);
            syncReviewActionStates();
        });

        reviewActionButtons.forEach((button) => {
            button.addEventListener("click", (event) => {
                if (!(event.currentTarget instanceof HTMLButtonElement)) {
                    return;
                }

                const action = String(event.currentTarget.dataset.reviewAction || "").trim();
                if (!action) {
                    return;
                }

                if (action === "reschedule_appointment" && !reviewOfficialUserId?.value) {
                    window.alert("This appointment does not have an assigned council member yet.");
                    return;
                }

                pendingReviewAction = action;
                if (reviewActionInput) {
                    reviewActionInput.value = action;
                }

                confirmScheduleFields?.classList.toggle("d-none", action !== "reschedule_appointment");
                reviewScheduleError?.classList.add("d-none");

                    confirmRemarksField?.classList.toggle("d-none", action !== "deny_appointment");
                if (reviewRemarks && action !== "deny_appointment") {
                    reviewRemarks.value = "";
                }

                if (confirmModalTitle) {
                    confirmModalTitle.textContent = action === "reschedule_appointment"
                        ? "Confirm Reschedule"
                        : (action === "complete_appointment" ? "Confirm Completion" : "Confirm Denial");
                }
                if (confirmModalMessage) {
                    confirmModalMessage.textContent = action === "reschedule_appointment"
                        ? "Review the selected date and time before continuing with this reschedule."
                        : (action === "complete_appointment"
                            ? "Mark this appointment as completed. This will lock further review actions."
                            : "Add a short denial reason before continuing. This action cannot be undone.");
                }
                if (confirmModalConfirmBtn) {
                    confirmModalConfirmBtn.textContent = action === "reschedule_appointment"
                        ? "Confirm Reschedule"
                        : (action === "complete_appointment" ? "Mark Completed" : "Confirm Denial");
                }

                if (viewModalEl?.classList.contains("show") && viewModalInstance) {
                    viewModalEl.addEventListener("hidden.bs.modal", () => confirmModalInstance?.show(), { once: true });
                    viewModalInstance.hide();
                } else {
                    confirmModalInstance?.show();
                }
            });
        });

        confirmModalReturnBtn?.addEventListener("click", () => {
            if (confirmModalConfirmBtn) {
                confirmModalConfirmBtn.textContent = "Continue";
            }
            if (confirmModalEl?.classList.contains("show") && confirmModalInstance) {
                confirmModalEl.addEventListener("hidden.bs.modal", () => viewModalInstance?.show(), { once: true });
                confirmModalInstance.hide();
            } else {
                viewModalInstance?.show();
            }
        });

        confirmModalConfirmBtn?.addEventListener("click", async () => {
            if (!pendingReviewAction) {
                return;
            }
            if (pendingReviewAction === "deny_appointment") {
                const reason = String(reviewRemarks?.value || "").trim();
                if (!reason) {
                    window.alert("Please enter a denial reason.");
                    reviewRemarks?.focus();
                    return;
                }
                if (reason.length > 140) {
                    window.alert("Denial reason must stay within 140 characters.");
                    reviewRemarks?.focus();
                    return;
                }
            }
            if (pendingReviewAction === "reschedule_appointment") {
                if (!reviewOfficialUserId?.value) {
                    window.alert("This appointment does not have an assigned council member yet.");
                    return;
                }
                if (!reviewConfirmedDate?.value || !reviewConfirmedTime?.value) {
                    window.alert("Please choose the new reschedule date and time.");
                    return;
                }

                const reviewDateValidation = validateReviewConfirmedDate();
                if (!reviewDateValidation.ok) {
                    window.alert(reviewDateValidation.message);
                    return;
                }

                if (!isWithinOfficeHours(reviewConfirmedTime?.value || "")) {
                    reviewScheduleError?.classList.remove("d-none");
                    window.alert("Rescheduled time must be within office hours, from 9:00 AM to 5:00 PM.");
                    return;
                }
            }
            if (reviewActionInput) {
                reviewActionInput.value = pendingReviewAction;
            }
            confirmModalReturnBtn?.setAttribute("disabled", "disabled");
            await submitWithLoading(
                confirmModalConfirmBtn,
                pendingReviewAction === "complete_appointment" ? "Processing..." : "Submitting..."
            );
        });

        confirmModalEl?.addEventListener("hidden.bs.modal", () => {
            if (confirmModalConfirmBtn) {
                confirmModalConfirmBtn.textContent = "Continue";
            }
            confirmModalReturnBtn?.removeAttribute("disabled");
        });

        if (feedbackModalEl && (feedbackSuccessMessage || feedbackErrorMessage || feedbackInfoMessage)) {
            if (feedbackModalTitle) {
                feedbackModalTitle.textContent = feedbackSuccessMessage
                    ? "Success"
                    : (feedbackErrorMessage ? "Unable To Update" : "Appointment Info");
            }
            if (feedbackModalMessage) {
                feedbackModalMessage.textContent = feedbackSuccessMessage || feedbackErrorMessage || feedbackInfoMessage || "-";
            }
            const feedbackModal = new bootstrap.Modal(feedbackModalEl);
            feedbackModal.show();
        }

        setFilterButtonState("");
        syncDateScopeButtons();
        syncAppointmentFilterButtonState();
        renderTable();
        scheduleAutoRefresh();
    })();

    (() => {
        const settingsForm = document.querySelector(".appointment-settings-form");
        const slotIntervalInput = document.getElementById("appointmentSlotInterval");
        const bookingWindowInput = document.getElementById("appointmentBookingWindow");
        const lunchBreakToggle = document.getElementById("appointmentLunchBreakEnabled");
        const lunchStartInput = document.getElementById("appointmentLunchStart");
        const lunchEndInput = document.getElementById("appointmentLunchEnd");
        const unavailableDatesHidden = document.getElementById("appointmentUnavailableDates");
        const unavailableDatePicker = document.getElementById("appointmentUnavailableDatePicker");
        const unavailableDateAddBtn = document.getElementById("appointmentUnavailableDateAdd");
        const unavailableDateList = document.getElementById("appointmentUnavailableDateList");
        const meetingLocationsHidden = document.getElementById("appointmentMeetingLocations");
        const meetingLocationInput = document.getElementById("appointmentMeetingLocationInput");
        const meetingLocationAddBtn = document.getElementById("appointmentMeetingLocationAdd");
        const meetingLocationList = document.getElementById("appointmentMeetingLocationList");

        if (!settingsForm || !unavailableDatesHidden || !unavailableDatePicker || !unavailableDateAddBtn || !unavailableDateList || !meetingLocationsHidden || !meetingLocationInput || !meetingLocationAddBtn || !meetingLocationList) {
            return;
        }

        const saveSettingsBtn = settingsForm.querySelector('button[type="submit"]');

        const formatDateLabel = (isoDate) => {
            const match = String(isoDate || "").trim().match(/^(\d{4})-(\d{2})-(\d{2})$/);
            if (!match) {
                return String(isoDate || "").trim();
            }
            return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])).toLocaleDateString("en-US", {
                month: "short",
                day: "numeric",
                year: "numeric",
            });
        };

        const normalizeUnavailableDates = (values) => Array.from(new Set(
            (Array.isArray(values) ? values : String(values || "").split(","))
                .map((value) => value.trim())
                .filter((value) => value !== "")
        )).sort();

        const normalizeMeetingLocationValue = (value) => String(value || "").replace(/\s+/g, " ").trim().slice(0, 255);

        const normalizeMeetingLocations = (values) => Array.from(new Set(
            (Array.isArray(values) ? values : String(values || "").split(/\r\n|\r|\n/))
                .map((value) => normalizeMeetingLocationValue(value))
                .filter((value) => value !== "")
        ));

        const getUnavailableDates = () => normalizeUnavailableDates(unavailableDatesHidden.value);
        const getMeetingLocations = () => normalizeMeetingLocations(meetingLocationsHidden.value);

        const setUnavailableDates = (dates, notify = false) => {
            const normalizedValue = normalizeUnavailableDates(dates).join(",");
            if (unavailableDatesHidden.value === normalizedValue) {
                return;
            }
            unavailableDatesHidden.value = normalizedValue;
            if (notify) {
                unavailableDatesHidden.dispatchEvent(new Event("input", { bubbles: true }));
                unavailableDatesHidden.dispatchEvent(new Event("change", { bubbles: true }));
            }
        };

        const setMeetingLocations = (locations, notify = false) => {
            const normalizedValue = normalizeMeetingLocations(locations).join("\n");
            if (meetingLocationsHidden.value === normalizedValue) {
                return;
            }
            meetingLocationsHidden.value = normalizedValue;
            if (notify) {
                meetingLocationsHidden.dispatchEvent(new Event("input", { bubbles: true }));
                meetingLocationsHidden.dispatchEvent(new Event("change", { bubbles: true }));
            }
        };

        const useMultiDatePicker = String(unavailableDatePicker.dataset.dateModalSelection || "").trim().toLowerCase() === "multiple";

        const renderMeetingLocations = () => {
            const locations = getMeetingLocations();
            if (locations.length === 0) {
                meetingLocationList.innerHTML = '<div class="appointment-unavailable-empty">No meeting locations added yet.</div>';
                return;
            }

            meetingLocationList.innerHTML = locations.map((location) => `
                <button type="button" class="appointment-unavailable-pill" data-meeting-location="${location.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;")}">
                    <span>${location.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;")}</span>
                    <span aria-hidden="true">&times;</span>
                </button>
            `).join("");
        };

        const getSettingsFormState = () => {
            const lunchBreakEnabled = lunchBreakToggle?.checked === true;
            return {
                slot_interval_minutes: String(slotIntervalInput?.value || "").trim(),
                booking_window_days: String(bookingWindowInput?.value || "").trim(),
                closed_weekdays: Array.from(settingsForm.querySelectorAll('input[name="closed_weekdays[]"]:checked'))
                    .map((input) => String(input.value || "").trim())
                    .filter((value) => value !== "")
                    .sort(),
                lunch_break_enabled: lunchBreakEnabled,
                lunch_start_time: lunchBreakEnabled ? String(lunchStartInput?.value || "").trim() : "",
                lunch_end_time: lunchBreakEnabled ? String(lunchEndInput?.value || "").trim() : "",
                unavailable_dates: getUnavailableDates(),
                meeting_locations: getMeetingLocations(),
            };
        };

        const hasSettingsChanges = () => JSON.stringify(getSettingsFormState()) !== originalSettingsState;

        const updateSaveButtonState = () => {
            if (!saveSettingsBtn) {
                return;
            }
            saveSettingsBtn.disabled = !hasSettingsChanges();
        };

        const renderUnavailableDates = () => {
            const dates = getUnavailableDates();
            if (dates.length === 0) {
                unavailableDateList.innerHTML = '<div class="appointment-unavailable-empty">No unavailable dates added.</div>';
                return;
            }

            unavailableDateList.innerHTML = dates.map((isoDate) => `
                <button type="button" class="appointment-unavailable-pill" data-unavailable-date="${isoDate}">
                    <span>${formatDateLabel(isoDate)}</span>
                    <span aria-hidden="true">&times;</span>
                </button>
            `).join("");
        };

        const syncLunchBreakInputs = () => {
            const enabled = lunchBreakToggle?.checked === true;
            [lunchStartInput, lunchEndInput].forEach((input) => {
                if (!input) {
                    return;
                }
                input.disabled = !enabled;
                input.required = enabled;
                if (!enabled) {
                    input.setCustomValidity("");
                }
            });
        };

        unavailableDateAddBtn.addEventListener("click", () => {
            if (useMultiDatePicker) {
                unavailableDatePicker.setCustomValidity("");
                unavailableDatePicker.focus();
                return;
            }

            const isoDate = String(unavailableDatePicker.value || "").trim();
            unavailableDatePicker.setCustomValidity("");

            if (isoDate === "") {
                unavailableDatePicker.setCustomValidity("Please select a date to add.");
                unavailableDatePicker.reportValidity();
                return;
            }

            const minIso = String(unavailableDatePicker.min || "").trim();
            const maxIso = String(unavailableDatePicker.max || "").trim();
            if ((minIso !== "" && isoDate < minIso) || (maxIso !== "" && isoDate > maxIso)) {
                unavailableDatePicker.setCustomValidity("Please choose a date within the current year.");
                unavailableDatePicker.reportValidity();
                return;
            }

            const dates = getUnavailableDates();
            if (!dates.includes(isoDate)) {
                dates.push(isoDate);
                setUnavailableDates(dates, true);
            }

            unavailableDatePicker.value = "";
            unavailableDatePicker.setCustomValidity("");
        });

        unavailableDatePicker.addEventListener("input", () => {
            unavailableDatePicker.setCustomValidity("");
        });

        unavailableDatesHidden.addEventListener("input", () => {
            setUnavailableDates(getUnavailableDates());
            renderUnavailableDates();
        });

        unavailableDatesHidden.addEventListener("change", () => {
            setUnavailableDates(getUnavailableDates());
            renderUnavailableDates();
        });

        meetingLocationAddBtn.addEventListener("click", () => {
            const locationValue = normalizeMeetingLocationValue(meetingLocationInput.value);
            meetingLocationInput.setCustomValidity("");

            if (locationValue === "") {
                meetingLocationInput.setCustomValidity("Please enter a meeting location to add.");
                meetingLocationInput.reportValidity();
                return;
            }

            const locations = getMeetingLocations();
            if (!locations.includes(locationValue)) {
                locations.push(locationValue);
                setMeetingLocations(locations, true);
            }

            meetingLocationInput.value = "";
            meetingLocationInput.setCustomValidity("");
        });

        meetingLocationInput.addEventListener("input", () => {
            meetingLocationInput.setCustomValidity("");
        });

        meetingLocationsHidden.addEventListener("input", () => {
            setMeetingLocations(getMeetingLocations());
            renderMeetingLocations();
        });

        meetingLocationsHidden.addEventListener("change", () => {
            setMeetingLocations(getMeetingLocations());
            renderMeetingLocations();
        });

        unavailableDateList.addEventListener("click", (event) => {
            const removeButton = event.target.closest("[data-unavailable-date]");
            if (!(removeButton instanceof HTMLButtonElement)) {
                return;
            }

            const isoDate = String(removeButton.dataset.unavailableDate || "").trim();
            if (!isoDate) {
                return;
            }

            setUnavailableDates(getUnavailableDates().filter((value) => value !== isoDate), true);
        });

        meetingLocationList.addEventListener("click", (event) => {
            const removeButton = event.target.closest("[data-meeting-location]");
            if (!(removeButton instanceof HTMLButtonElement)) {
                return;
            }

            const locationValue = normalizeMeetingLocationValue(removeButton.dataset.meetingLocation || "");
            if (!locationValue) {
                return;
            }

            setMeetingLocations(getMeetingLocations().filter((value) => value !== locationValue), true);
        });

        lunchBreakToggle?.addEventListener("change", syncLunchBreakInputs);
        settingsForm.addEventListener("input", updateSaveButtonState);
        settingsForm.addEventListener("change", updateSaveButtonState);

        settingsForm.addEventListener("submit", (event) => {
            syncLunchBreakInputs();
            setUnavailableDates(getUnavailableDates());

            if (!hasSettingsChanges()) {
                event.preventDefault();
                updateSaveButtonState();
                return;
            }

            if (lunchBreakToggle?.checked !== true) {
                return;
            }

            const startValue = String(lunchStartInput?.value || "").trim();
            const endValue = String(lunchEndInput?.value || "").trim();
            if (startValue === "" || endValue === "") {
                event.preventDefault();
                const target = lunchStartInput?.value ? lunchEndInput : lunchStartInput;
                target?.setCustomValidity("Lunch break start and end times are required.");
                target?.reportValidity();
                return;
            }

            lunchStartInput?.setCustomValidity("");
            lunchEndInput?.setCustomValidity("");
            if (startValue >= endValue) {
                event.preventDefault();
                lunchEndInput?.setCustomValidity("Lunch break end time must be after the lunch break start time.");
                lunchEndInput?.reportValidity();
                return;
            }
        });

        [lunchStartInput, lunchEndInput].forEach((input) => {
            input?.addEventListener("input", () => {
                input.setCustomValidity("");
            });
        });

        syncLunchBreakInputs();
        setUnavailableDates(getUnavailableDates());
        renderUnavailableDates();
        setMeetingLocations(getMeetingLocations());
        renderMeetingLocations();
        const originalSettingsState = JSON.stringify(getSettingsFormState());
        updateSaveButtonState();
    })();

    (() => {
        const scheduleForm = document.getElementById("appointmentOfficialScheduleForm");
        if (!scheduleForm) {
            return;
        }

        const officialSelector = document.getElementById("appointmentScheduleOfficialSelector");
        const hiddenOfficialInput = document.getElementById("appointmentScheduleOfficialUserId");
        const saveBtn = scheduleForm.querySelector('button[type="submit"]');
        const scheduleMap = window.APPOINTMENT_OFFICIAL_SCHEDULE_MAP || {};
        const baseLocationOptions = Array.isArray(window.APPOINTMENT_MEETING_LOCATION_OPTIONS)
            ? window.APPOINTMENT_MEETING_LOCATION_OPTIONS.map((value) => String(value || "").trim()).filter((value) => value !== "")
            : [];

        const getRow = (weekday) => document.querySelector(`[data-schedule-row="${weekday}"]`);
        const getToggle = (weekday) => document.querySelector(`[data-schedule-toggle="${weekday}"]`);
        const getStartInput = (weekday) => document.querySelector(`[data-schedule-start="${weekday}"]`);
        const getEndInput = (weekday) => document.querySelector(`[data-schedule-end="${weekday}"]`);
        const getLocationInput = (weekday) => document.querySelector(`[data-schedule-location="${weekday}"]`);

        const formatTimeLabel = (value) => {
            const match = String(value || "").trim().match(/^(\d{2}):(\d{2})$/);
            if (!match) {
                return String(value || "").trim();
            }
            const hour24 = Number(match[1]);
            const minute = Number(match[2]);
            const hour12 = hour24 % 12 === 0 ? 12 : hour24 % 12;
            const suffix = hour24 >= 12 ? "PM" : "AM";
            return `${String(hour12).padStart(2, "0")}:${String(minute).padStart(2, "0")} ${suffix}`;
        };

        const ensureTimeOption = (select, value) => {
            if (!(select instanceof HTMLSelectElement)) {
                return;
            }

            const normalizedValue = String(value || "").trim();
            if (normalizedValue === "" || Array.from(select.options).some((option) => option.value === normalizedValue)) {
                return;
            }

            const option = document.createElement("option");
            option.value = normalizedValue;
            option.textContent = formatTimeLabel(normalizedValue);
            select.appendChild(option);
        };

        const ensureLocationOption = (select, value) => {
            if (!(select instanceof HTMLSelectElement)) {
                return;
            }

            const normalizedValue = String(value || "").trim();
            const currentValues = Array.from(select.options).map((option) => String(option.value || "").trim());
            const mergedValues = normalizedValue !== "" && !baseLocationOptions.includes(normalizedValue)
                ? [normalizedValue, ...baseLocationOptions]
                : [...baseLocationOptions];

            const nextValues = ["", ...mergedValues];
            if (currentValues.length === nextValues.length && currentValues.every((item, index) => item === nextValues[index])) {
                return;
            }

            select.innerHTML = "";
            const placeholderOption = document.createElement("option");
            placeholderOption.value = "";
            placeholderOption.textContent = <?= json_encode($appointmentScheduleLocationPlaceholder, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            select.appendChild(placeholderOption);

            mergedValues.forEach((locationLabel) => {
                const option = document.createElement("option");
                option.value = locationLabel;
                option.textContent = locationLabel;
                select.appendChild(option);
            });
        };

        const setRowEnabled = (weekday, enabled) => {
            const row = getRow(weekday);
            const startInput = getStartInput(weekday);
            const endInput = getEndInput(weekday);
            const locationInput = getLocationInput(weekday);
            row?.classList.toggle("is-disabled", !enabled);
            [startInput, endInput, locationInput].forEach((input) => {
                if (!input) {
                    return;
                }
                input.disabled = !enabled;
                input.required = enabled;
                input.setCustomValidity("");
            });
        };

        const applyOfficialSchedule = (officialUserId) => {
            const schedule = scheduleMap[String(officialUserId || "").trim()] || {};
            if (hiddenOfficialInput) {
                hiddenOfficialInput.value = String(officialUserId || "").trim();
            }

            Object.keys(<?= json_encode($appointmentScheduleWeekdayOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>).forEach((weekdayKey) => {
                const dayEntry = schedule[weekdayKey] || schedule[Number(weekdayKey)] || {};
                const toggle = getToggle(weekdayKey);
                const startInput = getStartInput(weekdayKey);
                const endInput = getEndInput(weekdayKey);
                const locationInput = getLocationInput(weekdayKey);
                const enabled = dayEntry.enabled === true;

                if (toggle) {
                    toggle.checked = enabled;
                }
                if (startInput) {
                    ensureTimeOption(startInput, dayEntry.start_time || "");
                    startInput.value = String(dayEntry.start_time || "09:00").trim();
                }
                if (endInput) {
                    ensureTimeOption(endInput, dayEntry.end_time || "");
                    endInput.value = String(dayEntry.end_time || "16:30").trim();
                }
                if (locationInput) {
                    ensureLocationOption(locationInput, dayEntry.meeting_location || "");
                    locationInput.value = String(dayEntry.meeting_location || "").trim();
                }

                setRowEnabled(weekdayKey, enabled);
            });
        };

        const getScheduleFormState = () => {
            const state = {
                official_user_id: String(hiddenOfficialInput?.value || "").trim(),
                days: {},
            };

            Object.keys(<?= json_encode($appointmentScheduleWeekdayOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>).forEach((weekdayKey) => {
                state.days[weekdayKey] = {
                    enabled: getToggle(weekdayKey)?.checked === true,
                    start_time: String(getStartInput(weekdayKey)?.value || "").trim(),
                    end_time: String(getEndInput(weekdayKey)?.value || "").trim(),
                    meeting_location: String(getLocationInput(weekdayKey)?.value || "").trim(),
                };
            });

            return state;
        };

        let originalScheduleState = JSON.stringify(getScheduleFormState());

        const updateSaveButtonState = () => {
            if (!saveBtn) {
                return;
            }
            saveBtn.disabled = JSON.stringify(getScheduleFormState()) === originalScheduleState;
        };

        officialSelector?.addEventListener("change", () => {
            applyOfficialSchedule(officialSelector.value || "");
            originalScheduleState = JSON.stringify(getScheduleFormState());
            updateSaveButtonState();
        });

        scheduleForm.addEventListener("change", (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }

            if (target.matches("[data-schedule-toggle]")) {
                const weekday = String(target.getAttribute("data-schedule-toggle") || "").trim();
                setRowEnabled(weekday, target instanceof HTMLInputElement && target.checked);
            }

            updateSaveButtonState();
        });

        scheduleForm.addEventListener("input", () => {
            updateSaveButtonState();
        });

        scheduleForm.addEventListener("submit", (event) => {
            const officialUserId = String(hiddenOfficialInput?.value || "").trim();
            if (officialUserId === "") {
                event.preventDefault();
                window.alert("Please select a council member before saving the weekly appointment schedule.");
                return;
            }

            let firstInvalidInput = null;
            Object.keys(<?= json_encode($appointmentScheduleWeekdayOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>).forEach((weekdayKey) => {
                const enabled = getToggle(weekdayKey)?.checked === true;
                const startInput = getStartInput(weekdayKey);
                const endInput = getEndInput(weekdayKey);
                const locationInput = getLocationInput(weekdayKey);

                [startInput, endInput, locationInput].forEach((input) => input?.setCustomValidity(""));
                if (!enabled) {
                    return;
                }

                const startValue = String(startInput?.value || "").trim();
                const endValue = String(endInput?.value || "").trim();
                const locationValue = String(locationInput?.value || "").trim();

                if (startValue === "" || endValue === "") {
                    const target = startValue === "" ? startInput : endInput;
                    target?.setCustomValidity("Start and end times are required for available appointment days.");
                    firstInvalidInput = firstInvalidInput || target;
                    return;
                }

                if (startValue >= endValue) {
                    endInput?.setCustomValidity("End time must be later than the start time.");
                    firstInvalidInput = firstInvalidInput || endInput;
                    return;
                }

                if (locationValue === "") {
                    locationInput?.setCustomValidity("Meeting location is required for available appointment days.");
                    firstInvalidInput = firstInvalidInput || locationInput;
                }
            });

            if (firstInvalidInput) {
                event.preventDefault();
                firstInvalidInput.reportValidity();
                return;
            }
        });

        applyOfficialSchedule(hiddenOfficialInput?.value || officialSelector?.value || "");
        originalScheduleState = JSON.stringify(getScheduleFormState());
        updateSaveButtonState();
    })();
</script>
</body>
</html>
