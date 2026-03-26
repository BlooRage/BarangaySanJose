<?php
require_once __DIR__ . "/../General/security.php";
require_once __DIR__ . "/../General/connection.php";
require_once __DIR__ . "/../General/appointmentCouncilMembers.php";
require_once __DIR__ . "/../General/appointmentSettings.php";
require_once __DIR__ . "/../General/appointmentTimeSlots.php";

requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin', 'Employee'], false);
verifyCsrfToken(false);

function am_redirect_with_message(string $type, string $message, array $extra = []): void
{
    $query = array_merge([$type => $message], $extra);
    header('Location: ' . appUrl('/Admin-End/Appointments/AppointmentTracker.php') . '?' . http_build_query($query));
    exit;
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

function am_validate_schedule(string $date, string $time, array $appointmentSettings): string
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

    if (empty($bookingLimits['has_window']) || aps_first_available_booking_date($appointmentSettings, $now) === null) {
        throw new Exception('No appointment dates are currently available based on the saved appointment settings.');
    }

    if ($date < $minDate || $date > $maxDate) {
        throw new Exception('Confirmed appointment date is outside the current booking window.');
    }

    if (!aps_is_date_available($appointmentSettings, $date)) {
        throw new Exception('The selected appointment date is unavailable for official appointments.');
    }

    if (!ats_is_valid_time($time, $appointmentSettings)) {
        throw new Exception('Please select one of the allotted appointment times.');
    }

    return $schedule->format('Y-m-d H:i:s');
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

if (!in_array($action, ['approve_appointment', 'reschedule_appointment', 'deny_appointment'], true)) {
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

$needsSchedule = in_array($action, ['approve_appointment', 'reschedule_appointment'], true);
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
if ($needsSchedule) {
    try {
        $confirmedScheduleTimestamp = am_validate_schedule($confirmedDate, $confirmedTime, $appointmentSettings);
    } catch (Throwable $e) {
        am_redirect_with_message('error', $e->getMessage(), ['appointment_id' => $appointmentId]);
    }
}

$statusName = 'Pending';
if ($action === 'approve_appointment') {
    $statusName = 'Approved';
} elseif ($action === 'reschedule_appointment') {
    $statusName = 'Rescheduled';
} elseif ($action === 'deny_appointment') {
    $statusName = 'Denied';
}

try {
    $statusId = am_ensure_status_id($conn, $statusName, 'Appointment');
} catch (Throwable $e) {
    am_redirect_with_message('error', 'Unable to prepare appointment statuses right now.');
}

$conn->begin_transaction();
try {
    $existsStmt = $conn->prepare("
        SELECT appointment_id
             , COALESCE(s.status_name, 'Pending') AS status_name
        FROM appointmentstbl
        LEFT JOIN statuslookuptbl s
            ON s.status_id = appointmentstbl.appointment_status_id
        WHERE appointment_id = ?
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

    $currentStatusName = strtolower(trim((string)($existingRow['status_name'] ?? 'pending')));
    if ($currentStatusName !== '' && !str_contains($currentStatusName, 'pending')) {
        throw new Exception('This appointment has already been reviewed and can no longer be changed.');
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

    if (isset($appointmentColumns['review_timestamp'])) {
        $setClauses[] = 'review_timestamp = NOW()';
    }

    if (isset($appointmentColumns['confirmed_schedule_timestamp'])) {
        $setClauses[] = 'confirmed_schedule_timestamp = ?';
        $bindTypes .= 's';
        $bindValues[] = $needsSchedule ? $confirmedScheduleTimestamp : null;
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

    $successMessage = 'Appointment review saved successfully.';
    if ($action === 'approve_appointment') {
        $successMessage = 'Appointment approved successfully.';
    } elseif ($action === 'reschedule_appointment') {
        $successMessage = 'Appointment rescheduled successfully.';
    } elseif ($action === 'deny_appointment') {
        $successMessage = 'Appointment denied successfully.';
    }

    am_redirect_with_message('success', $successMessage, ['appointment_id' => $appointmentId]);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('appointmentManagement failed: ' . $e->getMessage());
    $message = 'Unable to update the appointment right now.';
    if (am_is_local_request()) {
        $message = $e->getMessage() !== '' ? $e->getMessage() : $message;
    }
    am_redirect_with_message('error', $message, ['appointment_id' => $appointmentId]);
}
