<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/appointmentOfficialSchedules.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
    exit;
}

apos_schedule_ensure_storage($conn);

function rat_table_exists(mysqli $conn, string $tableName): bool
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

    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();

    return !empty($row);
}

function rat_table_columns(mysqli $conn, string $tableName): array
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

    $stmt->bind_param('s', $tableName);
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

function rat_status_bucket(string $statusName): string
{
    $normalized = strtolower(trim($statusName));
    if ($normalized === '') {
        return 'pending';
    }
    if (str_contains($normalized, 'approve') || str_contains($normalized, 'complete') || str_contains($normalized, 'done')) {
        return 'approved';
    }
    if (str_contains($normalized, 'resched')) {
        return 'info';
    }
    if (str_contains($normalized, 'deny') || str_contains($normalized, 'reject')) {
        return 'archived';
    }
    return 'pending';
}

if (!rat_table_exists($conn, 'appointmentstbl')) {
    echo json_encode(['success' => true, 'items' => []]);
    exit;
}

$residentUserId = (string)$_SESSION['user_id'];
$appointmentColumns = rat_table_columns($conn, 'appointmentstbl');
$residentUserColumn = isset($appointmentColumns['user_id_resident']) ? 'a.user_id_resident' : 'NULL';
$nameSelect = isset($appointmentColumns['name']) ? 'a.name' : "''";
$contactNumberSelect = isset($appointmentColumns['contact_number']) ? 'a.contact_number' : "''";
$subjectSelect = isset($appointmentColumns['subject']) ? 'a.subject' : "'Appointment'";
$subjectOtherSelect = isset($appointmentColumns['subject_other']) ? 'a.subject_other' : "''";
$purposeSelect = isset($appointmentColumns['purpose']) ? 'a.purpose' : "''";
$preferredScheduleSelect = isset($appointmentColumns['preferred_schedule_timestamp'])
    ? 'a.preferred_schedule_timestamp'
    : (isset($appointmentColumns['schedule_timestamp']) ? 'a.schedule_timestamp' : 'NULL');
$confirmedScheduleSelect = isset($appointmentColumns['confirmed_schedule_timestamp'])
    ? 'a.confirmed_schedule_timestamp'
    : 'NULL';
$reviewTimestampSelect = isset($appointmentColumns['review_timestamp'])
    ? 'a.review_timestamp'
    : 'NULL';
$remarksSelect = isset($appointmentColumns['appointment_remarks'])
    ? 'a.appointment_remarks'
    : 'NULL';
$meetingLocationSelect = isset($appointmentColumns['meeting_location'])
    ? 'a.meeting_location'
    : 'NULL';
$hasAssignedOfficial = isset($appointmentColumns['user_id_official_assigned']);
$residentNotesSelect = isset($appointmentColumns['resident_notes'])
    ? 'a.resident_notes'
    : 'NULL';
$statusJoin = rat_table_exists($conn, 'statuslookuptbl')
    ? "LEFT JOIN statuslookuptbl s ON a.appointment_status_id = s.status_id"
    : '';
$statusSelect = rat_table_exists($conn, 'statuslookuptbl')
    ? "COALESCE(s.status_name, 'Pending') AS status_name"
    : "'Pending' AS status_name";
$officialJoin = '';
$officialNameSelect = "'-' AS official_name";
if ($hasAssignedOfficial && rat_table_exists($conn, 'officialinformationtbl')) {
    $officialJoin = "
        LEFT JOIN officialinformationtbl oi
            ON a.user_id_official_assigned COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
    ";
    $officialNameSelect = "TRIM(CONCAT_WS(' ', oi.firstname, oi.middlename, oi.lastname, oi.suffix)) AS official_name";

    if (rat_table_exists($conn, 'barangaycounciltbl')) {
        $officialJoin .= "
            LEFT JOIN barangaycounciltbl bc
                ON bc.current_official_id = oi.official_id
               AND bc.is_active = 1
        ";
        $officialNameSelect = "TRIM(CONCAT_WS(' - ', CONCAT_WS(' ', oi.firstname, oi.middlename, oi.lastname, oi.suffix), NULLIF(bc.seat_name, ''))) AS official_name";
    }
}
$requestTimestampSelect = isset($appointmentColumns['request_timestamp']) ? 'a.request_timestamp' : 'NULL';
$orderByTimestamp = isset($appointmentColumns['request_timestamp']) ? 'a.request_timestamp DESC, ' : '';

if (!isset($appointmentColumns['user_id_resident'])) {
    echo json_encode(['success' => true, 'items' => []]);
    exit;
}

$sql = "
    SELECT
        a.appointment_id,
        {$nameSelect} AS name,
        {$contactNumberSelect} AS contact_number,
        {$subjectSelect} AS subject,
        {$subjectOtherSelect} AS subject_other,
        {$purposeSelect} AS purpose,
        {$preferredScheduleSelect} AS preferred_schedule_timestamp,
        {$confirmedScheduleSelect} AS confirmed_schedule_timestamp,
        {$reviewTimestampSelect} AS review_timestamp,
        {$requestTimestampSelect} AS request_timestamp,
        {$remarksSelect} AS appointment_remarks,
        {$meetingLocationSelect} AS meeting_location,
        {$residentNotesSelect} AS resident_notes,
        {$statusSelect},
        {$officialNameSelect}
    FROM appointmentstbl a
    {$statusJoin}
    {$officialJoin}
    WHERE {$residentUserColumn} = ?
    ORDER BY {$orderByTimestamp} a.appointment_id DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load appointments.']);
    exit;
}

$stmt->bind_param('s', $residentUserId);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $subject = trim((string)($row['subject'] ?? 'Appointment'));
    $subjectOther = trim((string)($row['subject_other'] ?? ''));
    if (strcasecmp($subject, 'Other') === 0 && $subjectOther !== '') {
        $subject = 'Other: ' . $subjectOther;
    }

    $items[] = [
        'appointment_id' => (string)($row['appointment_id'] ?? ''),
        'resident_name' => (string)($row['name'] ?? ''),
        'contact_number' => (string)($row['contact_number'] ?? ''),
        'subject' => $subject !== '' ? $subject : 'Appointment',
        'purpose' => trim((string)($row['purpose'] ?? '')),
        'preferred_schedule_timestamp' => (string)($row['preferred_schedule_timestamp'] ?? ''),
        'confirmed_schedule_timestamp' => (string)($row['confirmed_schedule_timestamp'] ?? ''),
        'meeting_location' => trim((string)($row['meeting_location'] ?? '')),
        'review_timestamp' => (string)($row['review_timestamp'] ?? ''),
        'request_timestamp' => (string)($row['request_timestamp'] ?? ''),
        'appointment_remarks' => trim((string)($row['appointment_remarks'] ?? '')),
        'resident_notes' => trim((string)($row['resident_notes'] ?? '')),
        'status_name' => (string)($row['status_name'] ?? 'Pending'),
        'status_bucket' => rat_status_bucket((string)($row['status_name'] ?? 'Pending')),
        'official_name' => trim((string)($row['official_name'] ?? '')) !== '' ? (string)$row['official_name'] : '-',
    ];
}
$stmt->close();

echo json_encode([
    'success' => true,
    'items' => $items,
]);
