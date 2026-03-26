<?php
require_once __DIR__ . "/../../PhpFiles/General/connection.php";
require_once __DIR__ . "/../../PhpFiles/General/appointmentCouncilMembers.php";
require_once __DIR__ . "/../../PhpFiles/General/appointmentSettings.php";
require_once __DIR__ . "/../../PhpFiles/General/appointmentTimeSlots.php";
require_once __DIR__ . "/../../PhpFiles/General/audit.php";
require_once __DIR__ . "/../includes/admin_guard.php";

$appointmentTool = strtolower(trim((string)($_GET['tool'] ?? 'tracker')));
if (!in_array($appointmentTool, ['tracker', 'settings'], true)) {
    $appointmentTool = 'tracker';
}
$isAppointmentSettingsView = $appointmentTool === 'settings';
$isAppointmentTrackerView = !$isAppointmentSettingsView;

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
$appointmentLunchBreakEnabled = aps_has_lunch_break($appointmentSettings);
$appointmentLunchBreakLabel = aps_lunch_break_label($appointmentSettings);
$appointmentLunchStartTime = (string)($appointmentSettings['lunch_start_time'] ?? '12:00');
$appointmentLunchEndTime = (string)($appointmentSettings['lunch_end_time'] ?? '13:00');
$appointmentScheduleCoverageLabel = date('h:i A', strtotime(aps_schedule_start_time())) . ' to ' . date('h:i A', strtotime(aps_schedule_end_time()));
$appointmentFirstAvailableDate = aps_first_available_booking_date($appointmentSettings);
$appointmentFirstAvailableDateLabel = $appointmentFirstAvailableDate !== null ? date('M d, Y', strtotime($appointmentFirstAvailableDate)) : 'No available dates';

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
    if (str_contains($normalized, 'approve')) {
        return 'approved';
    }
    if (str_contains($normalized, 'deny') || str_contains($normalized, 'denied') || str_contains($normalized, 'reject')) {
        return 'denied';
    }
    if (str_contains($normalized, 'resched')) {
        return 'approved';
    }
    if (str_contains($normalized, 'complete') || str_contains($normalized, 'done')) {
        return 'approved';
    }
    return 'pending';
}

function at_status_label(string $value): string
{
    $key = at_status_key($value);
    if ($key === 'approved') {
        return 'Approved';
    }
    if ($key === 'denied') {
        return 'Denied';
    }
    return 'Pending';
}

function at_status_pill(string $value): string
{
    $key = at_status_key($value);
    if ($key === 'approved') {
        return 'approved';
    }
    if ($key === 'denied') {
        return 'denied';
    }
    return 'pending';
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

function at_flash_message(string $key): string
{
    return trim((string)($_GET[$key] ?? ''));
}

$appointmentRows = [];
$loadError = '';
$appointmentSuccessMessage = at_flash_message('success');
$appointmentErrorMessage = at_flash_message('error');
$highlightAppointmentId = at_flash_message('appointment_id');
$officialOptions = [];

if (!$conn->query("SHOW TABLES LIKE 'appointmentstbl'")->num_rows) {
    $loadError = 'Appointment table is not available. Run the appointment migration first.';
} else {
    $appointmentColumns = at_table_columns($conn, 'appointmentstbl');
    $hasPreferredSchedule = isset($appointmentColumns['preferred_schedule_timestamp']);
    $hasConfirmedSchedule = isset($appointmentColumns['confirmed_schedule_timestamp']);
    $hasReviewTimestamp = isset($appointmentColumns['review_timestamp']);
    $hasAssignedOfficial = isset($appointmentColumns['user_id_official_assigned']);
    $hasCouncilTable = $conn->query("SHOW TABLES LIKE 'barangaycounciltbl'")->num_rows > 0;

    $preferredScheduleSelect = $hasPreferredSchedule
        ? 'a.preferred_schedule_timestamp'
        : (isset($appointmentColumns['schedule_timestamp']) ? 'a.schedule_timestamp' : 'NULL');
    $confirmedScheduleSelect = $hasConfirmedSchedule ? 'a.confirmed_schedule_timestamp' : 'NULL';
    $reviewTimestampSelect = $hasReviewTimestamp ? 'a.review_timestamp' : 'NULL';
    $assignedOfficialSelect = $hasAssignedOfficial ? 'a.user_id_official_assigned' : 'NULL';
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

    $sql = "
        SELECT
            a.appointment_id,
            a.name,
            a.contact_number,
            a.subject,
            a.subject_other,
            a.purpose,
            {$preferredScheduleSelect} AS preferred_schedule_timestamp,
            {$confirmedScheduleSelect} AS confirmed_schedule_timestamp,
            a.request_timestamp,
            a.resident_notes,
            a.appointment_remarks,
            {$reviewTimestampSelect} AS review_timestamp,
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
        {$assignedOfficialJoin}
        ORDER BY a.request_timestamp DESC, a.appointment_id DESC
    ";

    $result = $conn->query($sql);
    if ($result instanceof mysqli_result) {
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
                'status_subtitle' => $isRescheduled ? 'Rescheduled' : '',
                'staff_name' => at_value($row, 'staff_name', '-'),
                'official_user_id' => at_value($row, 'official_user_id', ''),
                'official_name' => at_value($row, 'official_name', '-'),
                'resident_notes' => at_value($row, 'resident_notes', '-'),
                'appointment_remarks' => at_value($row, 'appointment_remarks', '-'),
                'review_timestamp_display' => at_format_datetime($row['review_timestamp'] ?? null),
            ];
        }
        $result->free();
    } else {
        $loadError = 'Unable to load appointment records.';
    }
}

foreach (apcm_fetch_council_members($conn) as $member) {
    $officialOptions[] = [
        'user_id' => trim((string)($member['user_id'] ?? '')),
        'full_name' => trim((string)($member['full_name'] ?? '')),
        'position_access' => trim((string)($member['position_access'] ?? '')),
        'department' => trim((string)($member['department'] ?? '')),
        'seat_name' => trim((string)($member['seat_name'] ?? '')),
        'option_label' => trim((string)($member['option_label'] ?? '')),
    ];
}

$pendingCount = 0;
$approvedCount = 0;
$deniedCount = 0;
foreach ($appointmentRows as $row) {
    if ($row['status_key'] === 'pending') {
        $pendingCount++;
    } elseif ($row['status_key'] === 'approved') {
        $approvedCount++;
    } elseif ($row['status_key'] === 'denied') {
        $deniedCount++;
    }
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
            max-width: 1340px;
            margin: 0 auto;
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

        .appointment-status-stack {
            display: inline-flex;
            flex-direction: column;
            align-items: flex-start;
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
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            background: #fffdfb;
            padding: 1.15rem 1.2rem;
            display: grid;
            gap: 10px;
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

                        <div class="appointment-settings-actions">
                            <button type="submit" class="btn btn-primary">Save Settings</button>
                        </div>
                    </form>
                </section>

                <section class="appointment-settings-card">
                    <h5>Current Appointment Rules</h5>
                    <ul class="appointment-settings-list">
                        <li>Residents can only set appointments with currently serving barangay council members.</li>
                        <li>Open weekdays: <?= htmlspecialchars($appointmentAvailableWeekdayLabels, ENT_QUOTES, 'UTF-8') ?>.</li>
                        <li>Closed weekdays: <?= htmlspecialchars($appointmentClosedWeekdayLabels, ENT_QUOTES, 'UTF-8') ?>.</li>
                        <li>Lunch break: <?= htmlspecialchars($appointmentLunchBreakLabel, ENT_QUOTES, 'UTF-8') ?>.</li>
                        <li>Unavailable dates: <?= htmlspecialchars($appointmentUnavailableDatesSummary, ENT_QUOTES, 'UTF-8') ?>.</li>
                        <li>Residents can book from tomorrow up to <?= htmlspecialchars((string)($appointmentSettings['booking_window_days'] ?? 365), ENT_QUOTES, 'UTF-8') ?> days ahead.</li>
                        <li>Earliest bookable date right now: <?= htmlspecialchars($appointmentFirstAvailableDateLabel, ENT_QUOTES, 'UTF-8') ?>.</li>
                    </ul>
                    <hr class="my-2">
                    <h5>Current Allotted Schedule</h5>
                    <p><strong>Coverage:</strong> <?= htmlspecialchars($appointmentScheduleCoverageLabel, ENT_QUOTES, 'UTF-8') ?></p>
                    <p><strong>Interval:</strong> <?= htmlspecialchars((string)($appointmentSettings['slot_interval_minutes'] ?? 30), ENT_QUOTES, 'UTF-8') ?> minutes per appointment slot</p>
                    <?php if ($appointmentLunchBreakEnabled): ?>
                        <p><strong>Lunch break excluded:</strong> <?= htmlspecialchars($appointmentLunchBreakLabel, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <div class="appointment-slot-cloud">
                        <?php foreach ($appointmentSlotLabels as $slotLabel): ?>
                            <span class="appointment-slot-pill"><?= htmlspecialchars($slotLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($appointmentUnavailableDates !== []): ?>
                        <hr class="my-2">
                        <h5>Unavailable Dates</h5>
                        <div class="appointment-unavailable-list">
                            <?php foreach ($appointmentUnavailableDates as $isoDate): ?>
                                <span class="appointment-unavailable-pill"><?= htmlspecialchars(aps_format_date_label($isoDate), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
        <?php else: ?>
        <div id="div-tableContainer" class="bg-white p-4 rounded-4 shadow-sm border appointment-tracker-shell resident-masterlist-shell">
            <div class="admin-list-toolbar mb-3 pt-2 flex-wrap">
                <div class="admin-list-tabs">
                    <button class="btn btn-outline-primary btn-sm status-filter-btn active" type="button" data-filter="">&nbsp;&nbsp;All&nbsp;&nbsp;</button>
                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold" type="button" data-filter="approved">&nbsp;&nbsp;Approved&nbsp;&nbsp;</button>
                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold" type="button" data-filter="denied">&nbsp;&nbsp;Denied&nbsp;&nbsp;</button>
                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold has-notif" type="button" data-filter="pending">
                        &nbsp;&nbsp;Pending
                        <span class="pending-count-badge <?= $pendingCount > 0 ? '' : 'd-none' ?>" id="pendingAppointmentBadge"><?= (int)$pendingCount ?></span>
                    </button>
                </div>

                <div class="admin-list-actions">
                    <div class="input-group admin-search">
                        <input type="text" id="searchInput" class="form-control" placeholder="Appointment ID, resident, subject, purpose">
                        <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                    </div>
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
                            <th>Resident</th>
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
                                    data-search="<?= htmlspecialchars(strtolower(implode(' ', [
                                        $row['appointment_id'],
                                        $row['resident_name'],
                                        $row['subject'],
                                        $row['purpose'],
                                        $row['status_name'],
                                    ])), ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    <td><?= htmlspecialchars($row['appointment_id'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($row['request_timestamp_display'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($row['resident_name'], ENT_QUOTES, 'UTF-8') ?></td>
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
                        <h6 class="tracker-form-section-title">Resident and Assignment</h6>
                        <div class="tracker-form-grid cols-4">
                            <div class="tracker-form-field"><p class="tracker-form-label">Resident Name</p><div class="tracker-form-value" id="viewResidentName">-</div></div>
                            <div class="tracker-form-field"><p class="tracker-form-label">Contact Number</p><div class="tracker-form-value" id="viewContactNumber">-</div></div>
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
                            <div class="tracker-form-field"><p class="tracker-form-label">Purpose</p><div class="tracker-form-value" id="viewPurpose">-</div></div>
                        </div>
                    </section>

                    <section class="tracker-form-section">
                        <h6 class="tracker-form-section-title">Notes</h6>
                        <div class="tracker-form-grid cols-1">
                            <div class="tracker-form-field"><p class="tracker-form-label">Resident Notes</p><div class="tracker-form-value" id="viewResidentNotes">-</div></div>
                            <div class="tracker-form-field"><p class="tracker-form-label">Appointment Remarks</p><div class="tracker-form-value" id="viewAppointmentRemarks">-</div></div>
                        </div>
                    </section>

                    <section class="tracker-form-section">
                        <h6 class="tracker-form-section-title">Review Action</h6>
                        <div class="tracker-form-value d-none" id="reviewLockedMessage">
                            This appointment has already been reviewed. Status changes can no longer be modified.
                        </div>
                        <form method="post" action="<?= htmlspecialchars(appUrl('/PhpFiles/Admin-End/appointmentManagement.php'), ENT_QUOTES, 'UTF-8') ?>" id="appointmentReviewForm" class="tracker-profile-view">
                            <?= csrfTokenField() ?>
                            <input type="hidden" name="appointment_id" id="reviewAppointmentId" value="">
                            <input type="hidden" name="action" id="reviewActionInput" value="">

                            <div class="tracker-form-grid cols-3">
                                <div class="tracker-form-field">
                                    <label class="tracker-form-label" for="reviewOfficialUserId">Council Member</label>
                                    <select class="form-select" name="official_user_id" id="reviewOfficialUserId">
                                        <option value="">Select council member</option>
                                        <?php foreach ($officialOptions as $official): ?>
                                            <option value="<?= htmlspecialchars($official['user_id'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars((string)($official['option_label'] !== '' ? $official['option_label'] : ($official['full_name'] !== '' ? $official['full_name'] : $official['user_id'])), ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($officialOptions === []): ?>
                                        <div class="text-danger small mt-1">No active barangay council members are currently available for appointment approval.</div>
                                    <?php endif; ?>
                                </div>
                                <div class="tracker-form-field">
                                    <label class="tracker-form-label" for="reviewConfirmedDate">Confirmed Date</label>
                                    <input class="form-control" type="date" name="confirmed_date" id="reviewConfirmedDate" min="<?= htmlspecialchars($minConfirmedDate, ENT_QUOTES, 'UTF-8') ?>" max="<?= htmlspecialchars($maxConfirmedDate, ENT_QUOTES, 'UTF-8') ?>" data-disabled-weekdays="<?= htmlspecialchars(implode(',', $appointmentDisabledWeekdays), ENT_QUOTES, 'UTF-8') ?>" data-disabled-dates="<?= htmlspecialchars($appointmentUnavailableDatesCsv, ENT_QUOTES, 'UTF-8') ?>" data-available-weekdays="<?= htmlspecialchars($appointmentAvailableWeekdayLabels, ENT_QUOTES, 'UTF-8') ?>" data-date-modal-ignore>
                                </div>
                                <div class="tracker-form-field">
                                    <label class="tracker-form-label" for="reviewConfirmedTime">Confirmed Time</label>
                                    <select class="form-select" name="confirmed_time" id="reviewConfirmedTime">
                                        <option value="">Select allotted time</option>
                                        <?php foreach ($appointmentTimeSlots as $slotValue => $slotLabel): ?>
                                            <option value="<?= htmlspecialchars($slotValue, ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($slotLabel, ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="tracker-form-grid cols-1">
                                <div class="tracker-form-field">
                                    <div class="text-danger small d-none" id="reviewScheduleError">
                                        Rescheduled time must be within office hours, from 9:00 AM to 5:00 PM.
                                    </div>
                                </div>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
            <div class="modal-footer justify-content-between flex-wrap gap-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <div class="d-flex flex-wrap gap-2 d-none" id="reviewActionFooter">
                    <button type="button" class="btn btn-danger" data-review-action="deny_appointment">Deny</button>
                    <button type="button" class="btn btn-warning" data-review-action="reschedule_appointment">Reschedule</button>
                    <button type="button" class="btn btn-success" data-review-action="approve_appointment">Approve</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="appointmentActionConfirmModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="appointmentActionConfirmTitle">Confirm Appointment Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2" id="appointmentActionConfirmMessage">This action cannot be undone.</p>
                <div class="tracker-form-field d-none" id="appointmentActionSchedulePreviewField">
                    <div class="tracker-form-value p-0 overflow-hidden">
                        <div class="table-responsive">
                            <table class="table table-sm appointment-action-compare mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col"></th>
                                        <th scope="col">Preferred</th>
                                        <th scope="col">Confirmed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <th scope="row">Date</th>
                                        <td id="appointmentActionPreferredDate">-</td>
                                        <td id="appointmentActionConfirmedDate">-</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Time</th>
                                        <td id="appointmentActionPreferredTime">-</td>
                                        <td id="appointmentActionConfirmedTime">-</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="tracker-form-field d-none" id="appointmentActionRemarksField">
                    <label class="tracker-form-label" for="reviewAppointmentRemarks">Review Remarks</label>
                    <textarea class="form-control" name="appointment_remarks" id="reviewAppointmentRemarks" rows="3" form="appointmentReviewForm" placeholder="Add denial remarks"></textarea>
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
</script>
<script src="../../JS-Script-Files/Admin-End/tableColumnsGeneric.js?v=20260215-1"></script>
<script src="../../JS-Script-Files/Resident-End/dateFieldModal.js?v=20260326-1"></script>
<script>
    (() => {
        const feedbackSuccessMessage = <?= json_encode($appointmentSuccessMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const feedbackErrorMessage = <?= json_encode($appointmentErrorMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const tableBody = document.getElementById("tableBody");
        const searchInput = document.getElementById("searchInput");
        const entriesPerPageInput = document.getElementById("entriesPerPageInput");
        const paginationEl = document.getElementById("appointmentPagination");
        const refreshBtn = document.getElementById("btnAppointmentTableRefresh");
        const pendingBadge = document.getElementById("pendingAppointmentBadge");
        const filterButtons = Array.from(document.querySelectorAll(".status-filter-btn"));
        const reviewForm = document.getElementById("appointmentReviewForm");
        const reviewActionInput = document.getElementById("reviewActionInput");
        const reviewAppointmentId = document.getElementById("reviewAppointmentId");
        const reviewOfficialUserId = document.getElementById("reviewOfficialUserId");
        const reviewConfirmedDate = document.getElementById("reviewConfirmedDate");
        const reviewConfirmedTime = document.getElementById("reviewConfirmedTime");
        const reviewScheduleError = document.getElementById("reviewScheduleError");
        const reviewRemarks = document.getElementById("reviewAppointmentRemarks");
        const reviewActionButtons = Array.from(document.querySelectorAll("[data-review-action]"));
        const reviewActionFooter = document.getElementById("reviewActionFooter");
        const reviewLockedMessage = document.getElementById("reviewLockedMessage");
        const feedbackModalEl = document.getElementById("appointmentFeedbackModal");
        const feedbackModalTitle = document.getElementById("appointmentFeedbackModalTitle");
        const feedbackModalMessage = document.getElementById("appointmentFeedbackModalMessage");
        const modal = document.getElementById("viewModal");
        const confirmModalEl = document.getElementById("appointmentActionConfirmModal");
        const confirmModalTitle = document.getElementById("appointmentActionConfirmTitle");
        const confirmModalMessage = document.getElementById("appointmentActionConfirmMessage");
        const confirmSchedulePreviewField = document.getElementById("appointmentActionSchedulePreviewField");
        const confirmPreferredDate = document.getElementById("appointmentActionPreferredDate");
        const confirmPreferredTime = document.getElementById("appointmentActionPreferredTime");
        const confirmConfirmedDate = document.getElementById("appointmentActionConfirmedDate");
        const confirmConfirmedTime = document.getElementById("appointmentActionConfirmedTime");
        const confirmRemarksField = document.getElementById("appointmentActionRemarksField");
        const confirmModalReturnBtn = document.getElementById("appointmentActionReturnBtn");
        const confirmModalConfirmBtn = document.getElementById("appointmentActionConfirmBtn");
        const viewModalInstance = modal ? bootstrap.Modal.getOrCreateInstance(modal) : null;
        const confirmModalInstance = confirmModalEl ? bootstrap.Modal.getOrCreateInstance(confirmModalEl) : null;

        let allRows = Array.from(tableBody?.querySelectorAll("tr") || []).filter((row) => row.dataset.status !== undefined);
        let currentPage = 1;
        let activeFilter = "";
        let pendingReviewAction = "";
        const AUTO_REFRESH_MS = 30000;
        let autoRefreshTimeout = null;
        let autoRefreshInFlight = false;

        const parseIsoDate = (value) => {
            const text = String(value || "").trim();
            const match = text.match(/^(\d{4})-(\d{2})-(\d{2})$/);
            if (!match) {
                return null;
            }
            return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
        };

        function setRefreshLoading(on) {
            if (!refreshBtn) return;
            refreshBtn.classList.toggle("is-loading", !!on);
            refreshBtn.disabled = !!on;
        }

        function updatePendingBadge() {
            if (!pendingBadge) return;
            const count = allRows.filter((row) => String(row.dataset.status || "").trim().toLowerCase() === "pending").length;
            pendingBadge.textContent = String(count);
            pendingBadge.classList.toggle("d-none", count <= 0);
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
                updatePendingBadge();
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
                const matchesFilter = !activeFilter || String(row.dataset.status || "").trim().toLowerCase() === activeFilter;
                if (!matchesFilter) return false;

                if (!term) return true;
                return String(row.dataset.search || "").includes(term);
            });
        }

        function renderTable() {
            const filteredRows = getFilteredRows();
            const perPage = Math.max(1, Number(entriesPerPageInput?.value || 20));
            const start = (currentPage - 1) * perPage;
            const end = start + perPage;

            allRows.forEach((row) => {
                row.style.display = "none";
            });

            filteredRows.slice(start, end).forEach((row) => {
                row.style.display = "";
            });

            renderPagination(filteredRows.length);
        }

        function setFilterButtonState(activeValue) {
            filterButtons.forEach((button) => {
                const isActive = String(button.dataset.filter || "") === activeValue;
                button.classList.toggle("active", isActive);
                button.classList.toggle("btn-outline-primary", isActive);
                button.classList.toggle("btn-outline-secondary", !isActive);
            });
        }

        filterButtons.forEach((button) => {
            button.addEventListener("click", () => {
                activeFilter = String(button.dataset.filter || "").trim().toLowerCase();
                currentPage = 1;
                setFilterButtonState(String(button.dataset.filter || ""));
                renderTable();
            });
        });

        searchInput?.addEventListener("input", () => {
            currentPage = 1;
            renderTable();
        });

        entriesPerPageInput?.addEventListener("change", () => {
            currentPage = 1;
            renderTable();
        });

        refreshBtn?.addEventListener("click", triggerRefresh);

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
            setText("viewStaffName", button.dataset.staffName);
            setText("viewOfficialName", button.dataset.officialName);
            setText("viewSubject", button.dataset.subject);
            setText("viewPreferredAppointmentDate", button.dataset.preferredAppointmentDate);
            setText("viewPreferredAppointmentTime", button.dataset.preferredAppointmentTime);
            setText("viewConfirmedAppointmentDate", button.dataset.confirmedAppointmentDate);
            setText("viewConfirmedAppointmentTime", button.dataset.confirmedAppointmentTime);
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
                validateReviewConfirmedDate();
            }
            if (reviewConfirmedTime) {
                reviewConfirmedTime.value = "";
                const confirmedStamp = String(button.dataset.confirmedScheduleTimestamp || "").trim();
                const preferredStamp = String(button.dataset.preferredScheduleTimestamp || "").trim();
                const stampToUse = confirmedStamp || preferredStamp;
                if (stampToUse.length >= 16) {
                    reviewConfirmedTime.value = stampToUse.slice(11, 16);
                    if (modal) {
                        modal.dataset.originalReviewTime = stampToUse.slice(11, 16);
                    }
                }
            }
            if (reviewRemarks) {
                const remarks = String(button.dataset.appointmentRemarks || "").trim();
                reviewRemarks.value = remarks && remarks !== '-' ? remarks : '';
            }

            const isPending = String(button.dataset.statusKey || "").trim() === "pending";
            reviewLockedMessage?.classList.toggle("d-none", isPending);
            reviewActionFooter?.classList.toggle("d-none", !isPending);
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

                const needsSchedule = action === "approve_appointment" || action === "reschedule_appointment";
                if (needsSchedule) {
                    if (!reviewOfficialUserId?.value) {
                        window.alert("Please select the barangay council member for this appointment.");
                        return;
                    }
                    if (!reviewConfirmedDate?.value || !reviewConfirmedTime?.value) {
                        window.alert("Confirmed date and time are required for this action.");
                        return;
                    }

                    const reviewDateValidation = validateReviewConfirmedDate();
                    if (!reviewDateValidation.ok) {
                        window.alert(reviewDateValidation.message);
                        return;
                    }
                }
                if (action === "reschedule_appointment" && !isWithinOfficeHours(reviewConfirmedTime?.value || "")) {
                    reviewScheduleError?.classList.remove("d-none");
                    window.alert("Rescheduled time must be within office hours, from 9:00 AM to 5:00 PM.");
                    return;
                }

                pendingReviewAction = action;
                if (reviewActionInput) {
                    reviewActionInput.value = action;
                }

                confirmSchedulePreviewField?.classList.toggle("d-none", action !== "reschedule_appointment");
                if (action === "reschedule_appointment") {
                    if (confirmPreferredDate) {
                        confirmPreferredDate.textContent = String(modal?.dataset.preferredAppointmentDate || "").trim() || "-";
                    }
                    if (confirmPreferredTime) {
                        confirmPreferredTime.textContent = String(modal?.dataset.preferredAppointmentTime || "").trim() || "-";
                    }
                    if (confirmConfirmedDate) {
                        confirmConfirmedDate.textContent = formatReviewDate(reviewConfirmedDate?.value || "");
                    }
                    if (confirmConfirmedTime) {
                        confirmConfirmedTime.textContent = formatReviewTime(reviewConfirmedTime?.value || "");
                    }
                }

                confirmRemarksField?.classList.toggle("d-none", action !== "deny_appointment");
                if (reviewRemarks && action !== "deny_appointment") {
                    reviewRemarks.value = "";
                }

                if (confirmModalTitle) {
                    confirmModalTitle.textContent = action === "approve_appointment"
                        ? "Confirm Approval"
                        : (action === "reschedule_appointment" ? "Confirm Reschedule" : "Confirm Denial");
                }
                if (confirmModalMessage) {
                    confirmModalMessage.textContent = action === "approve_appointment"
                        ? "You are about to approve this appointment. This action cannot be undone."
                        : (action === "reschedule_appointment"
                            ? "Review the selected date and time before continuing with this reschedule."
                            : "You are about to deny this appointment. This action cannot be undone.");
                }

                viewModalInstance?.hide();
                confirmModalInstance?.show();
            });
        });

        confirmModalReturnBtn?.addEventListener("click", () => {
            confirmModalInstance?.hide();
            window.setTimeout(() => {
                viewModalInstance?.show();
            }, 150);
        });

        confirmModalConfirmBtn?.addEventListener("click", () => {
            if (!pendingReviewAction) {
                return;
            }
            if (reviewActionInput) {
                reviewActionInput.value = pendingReviewAction;
            }
            reviewForm?.submit();
        });

        if (feedbackModalEl && (feedbackSuccessMessage || feedbackErrorMessage)) {
            if (feedbackModalTitle) {
                feedbackModalTitle.textContent = feedbackSuccessMessage ? "Success" : "Unable To Update";
            }
            if (feedbackModalMessage) {
                feedbackModalMessage.textContent = feedbackSuccessMessage || feedbackErrorMessage || "-";
            }
            const feedbackModal = new bootstrap.Modal(feedbackModalEl);
            feedbackModal.show();
        }

        setFilterButtonState("");
        updatePendingBadge();
        renderTable();
        scheduleAutoRefresh();
    })();

    (() => {
        const settingsForm = document.querySelector(".appointment-settings-form");
        const lunchBreakToggle = document.getElementById("appointmentLunchBreakEnabled");
        const lunchStartInput = document.getElementById("appointmentLunchStart");
        const lunchEndInput = document.getElementById("appointmentLunchEnd");
        const unavailableDatesHidden = document.getElementById("appointmentUnavailableDates");
        const unavailableDatePicker = document.getElementById("appointmentUnavailableDatePicker");
        const unavailableDateAddBtn = document.getElementById("appointmentUnavailableDateAdd");
        const unavailableDateList = document.getElementById("appointmentUnavailableDateList");

        if (!settingsForm || !unavailableDatesHidden || !unavailableDatePicker || !unavailableDateAddBtn || !unavailableDateList) {
            return;
        }

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

        const getUnavailableDates = () => Array.from(new Set(
            String(unavailableDatesHidden.value || "")
                .split(",")
                .map((value) => value.trim())
                .filter((value) => value !== "")
        )).sort();

        const setUnavailableDates = (dates) => {
            unavailableDatesHidden.value = dates.join(",");
        };

        const useMultiDatePicker = String(unavailableDatePicker.dataset.dateModalSelection || "").trim().toLowerCase() === "multiple";

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
                dates.sort();
                setUnavailableDates(dates);
                renderUnavailableDates();
            }

            unavailableDatePicker.value = "";
            unavailableDatePicker.setCustomValidity("");
        });

        unavailableDatePicker.addEventListener("input", () => {
            unavailableDatePicker.setCustomValidity("");
        });

        unavailableDatesHidden.addEventListener("input", () => {
            renderUnavailableDates();
        });

        unavailableDatesHidden.addEventListener("change", () => {
            renderUnavailableDates();
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

            setUnavailableDates(getUnavailableDates().filter((value) => value !== isoDate));
            renderUnavailableDates();
        });

        lunchBreakToggle?.addEventListener("change", syncLunchBreakInputs);

        settingsForm.addEventListener("submit", (event) => {
            syncLunchBreakInputs();
            setUnavailableDates(getUnavailableDates());

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
        renderUnavailableDates();
    })();
</script>
</body>
</html>
