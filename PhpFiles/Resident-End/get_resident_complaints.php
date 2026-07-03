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
require_once __DIR__ . '/../General/complaintTypeDetails.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
    exit;
}

function rct_table_exists(mysqli $conn, string $tableName): bool
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

function rct_display_name(array $row): string
{
    $first = trim((string)($row['firstname'] ?? ''));
    $middle = trim((string)($row['middlename'] ?? ''));
    $last = trim((string)($row['lastname'] ?? ''));
    $suffix = trim((string)($row['suffix'] ?? ''));
    $middleInitial = $middle !== '' ? strtoupper(substr($middle, 0, 1)) . '.' : '';

    return trim(implode(' ', array_filter([$first, $middleInitial, $last, $suffix])));
}

function rct_status_payload(string $statusName, array $row = []): array
{
    $normalized = strtolower(trim($statusName));
    $label = trim($statusName) !== '' ? trim($statusName) : 'Pending';

    if ((int)($row['escalated_to_blotter'] ?? 0) === 1 || str_contains($normalized, 'endorse')) {
        return ['label' => 'Endorsed to Blotter', 'bucket' => 'archived'];
    }
    if (str_contains($normalized, 'resolve')) {
        return ['label' => 'Resolved', 'bucket' => 'approved'];
    }
    if (str_contains($normalized, 'drop')) {
        return ['label' => 'Dropped', 'bucket' => 'archived'];
    }

    return ['label' => $label, 'bucket' => 'pending'];
}

if (
    !rct_table_exists($conn, 'casereportstbl') ||
    !rct_table_exists($conn, 'complaintstbl') ||
    !rct_table_exists($conn, 'caseparticipantstbl')
) {
    echo json_encode(['success' => true, 'items' => []]);
    exit;
}

$residentUserId = (string)$_SESSION['user_id'];
$statusJoin = rct_table_exists($conn, 'statuslookuptbl')
    ? "
        LEFT JOIN statuslookuptbl s ON s.status_id = c.case_status_id
        LEFT JOIN statuslookuptbl l ON l.status_id = c.case_level_id
    "
    : '';
$statusSelect = rct_table_exists($conn, 'statuslookuptbl')
    ? "
        COALESCE(s.status_name, 'Pending') AS status_name,
        COALESCE(l.status_name, 'Complaint Only') AS level_name
    "
    : "
        'Pending' AS status_name,
        'Complaint Only' AS level_name
    ";

$sql = "
    SELECT
        c.case_id,
        ct.complaint_id,
        c.report_timestamp,
        c.incident_date,
        c.incident_time,
        c.incident_place,
        c.complaint_type,
        c.case_details,
        c.case_remarks,
        ct.subject_kind,
        ct.subject_display_name,
        ct.subject_contact_number,
        ct.subject_address,
        ct.witness_summary,
        ct.intake_notes,
        ct.screening_notes,
        ct.escalated_to_blotter,
        ct.blotter_id,
        {$statusSelect},
        complainant.firstname,
        complainant.middlename,
        complainant.lastname,
        complainant.suffix,
        complainant.contact_number AS complainant_contact_number,
        complainant.address AS complainant_address,
        complainant.age AS complainant_age,
        complainant.sex AS complainant_sex
    FROM casereportstbl c
    INNER JOIN complaintstbl ct ON ct.case_id = c.case_id
    {$statusJoin}
    LEFT JOIN caseparticipantstbl complainant
        ON complainant.case_id = c.case_id
       AND complainant.participant_role = 'Complainant'
    WHERE c.report_type = 'Complaint'
      AND c.resident_user_id = ?
    ORDER BY c.report_timestamp DESC, c.case_id DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load complaints.']);
    exit;
}

$stmt->bind_param('s', $residentUserId);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $status = rct_status_payload((string)($row['status_name'] ?? 'Pending'), $row);
    $parsedCaseDetails = complaintTypeParseCaseDetails($row['case_details'] ?? '');
    $items[] = [
        'complaint_id' => (string)($row['complaint_id'] ?? ''),
        'case_id' => (string)($row['case_id'] ?? ''),
        'report_timestamp' => (string)($row['report_timestamp'] ?? ''),
        'incident_date' => (string)($row['incident_date'] ?? ''),
        'incident_time' => (string)($row['incident_time'] ?? ''),
        'incident_place' => (string)($row['incident_place'] ?? ''),
        'incident_area_number' => (string)($parsedCaseDetails['incident_area_number'] ?? ''),
        'complaint_type' => (string)($row['complaint_type'] ?? ''),
        'case_details' => trim((string)($row['case_details'] ?? '')),
        'complaint_narration' => $parsedCaseDetails['narration'] ?? '',
        'complaint_detail_fields' => $parsedCaseDetails['fields'] ?? [],
        'attachments' => $parsedCaseDetails['attachments'] ?? [],
        'case_remarks' => trim((string)($row['case_remarks'] ?? '')),
        'subject_kind' => (string)($row['subject_kind'] ?? ''),
        'subject_display_name' => (string)($row['subject_display_name'] ?? ''),
        'subject_contact_number' => (string)($row['subject_contact_number'] ?? ''),
        'subject_address' => (string)($row['subject_address'] ?? ''),
        'witness_summary' => trim((string)($row['witness_summary'] ?? '')),
        'intake_notes' => trim((string)($row['intake_notes'] ?? '')),
        'screening_notes' => trim((string)($row['screening_notes'] ?? '')),
        'escalated_to_blotter' => (int)($row['escalated_to_blotter'] ?? 0),
        'blotter_id' => (string)($row['blotter_id'] ?? ''),
        'status_name' => $status['label'],
        'status_bucket' => $status['bucket'],
        'level_name' => (string)($row['level_name'] ?? 'Complaint Only'),
        'complainant_name' => rct_display_name($row),
        'complainant_contact_number' => (string)($row['complainant_contact_number'] ?? ''),
        'complainant_address' => (string)($row['complainant_address'] ?? ''),
        'complainant_age' => (string)($row['complainant_age'] ?? ''),
        'complainant_sex' => (string)($row['complainant_sex'] ?? ''),
    ];
}
$stmt->close();

echo json_encode([
    'success' => true,
    'items' => $items,
]);
