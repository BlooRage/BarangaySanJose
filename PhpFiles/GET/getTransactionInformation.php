<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../General/connection.php';

function ti_json(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ti_stage_label(string $stage): string {
    $map = [
        'submitted' => 'Pending Verification',
        'for_interview' => 'For Interview',
        'for_inspection' => 'For Inspection',
        'inspection_failed' => 'Inspection Failed',
        'rejected' => 'Rejected',
        'for_payment' => 'For Payment',
        'payment_submitted' => 'Pending Payment Verification',
        'payment_rejected' => 'Payment Rejected',
        'payment_verified' => 'Payment Verified',
        'ready_for_claim' => 'Ready for Claim',
        'completed' => 'Completed',
    ];
    return $map[$stage] ?? ucfirst(str_replace('_', ' ', $stage));
}

function ti_stage_from_status_name(string $statusName): ?string {
    $k = strtolower(trim($statusName));
    $map = [
        'pendingverification' => 'submitted',
        'pendingreview' => 'submitted',
        'forinterview' => 'for_interview',
        'forinspection' => 'for_inspection',
        'inspectionfailed' => 'inspection_failed',
        'forpayment' => 'for_payment',
        'paymentsubmitted' => 'payment_submitted',
        'paymentrejected' => 'payment_rejected',
        'paymentverified' => 'payment_verified',
        'forrelease' => 'ready_for_claim',
        'readyforclaim' => 'ready_for_claim',
        'completed' => 'completed',
        'rejected' => 'rejected',
    ];
    return $map[$k] ?? null;
}

function ti_value(array $arr, array $keys, string $default = ''): string {
    foreach ($keys as $k) {
        if (isset($arr[$k]) && trim((string)$arr[$k]) !== '') {
            return trim((string)$arr[$k]);
        }
    }
    return $default;
}

$requestId = trim((string)($_GET['request_id'] ?? ''));
$verificationCode = trim((string)($_GET['vc'] ?? ''));

if ($requestId === '') {
    ti_json(422, ['success' => false, 'message' => 'Missing request_id.']);
}

$stmt = $conn->prepare("
    SELECT
        request_id,
        resident_user_id,
        resident_id,
        document_type,
        purpose,
        request_details,
        status_id,
        (SELECT sl.status_name FROM statuslookuptbl sl WHERE sl.status_id = documentrequesttbl.status_id LIMIT 1) AS status_lookup_name,
        status_remarks,
        amount,
        or_number,
        certificate_number,
        verification_code,
        submitted_at,
        ready_at,
        completed_at,
        document_validity,
        release_timestamp
    FROM documentrequesttbl
    WHERE request_id = ?
    LIMIT 1
");

if (!$stmt) {
    ti_json(500, ['success' => false, 'message' => 'Failed to prepare verification query.']);
}

$stmt->bind_param('s', $requestId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    ti_json(404, ['success' => false, 'message' => 'Transaction not found or invalid verification link.']);
}

$statusMappedStage = ti_stage_from_status_name((string)($row['status_lookup_name'] ?? ''));
if ($statusMappedStage !== null) {
    $row['stage'] = $statusMappedStage;
}

$expectedCode = trim((string)($row['verification_code'] ?? ''));
if ($expectedCode === '') {
    $expectedCode = (string)$row['request_id'];
}

if ($verificationCode === '' || strcasecmp($verificationCode, $expectedCode) !== 0) {
    ti_json(404, ['success' => false, 'message' => 'Transaction not found or invalid verification link.']);
}

$payload = json_decode((string)($row['request_details'] ?? $row['payload_json'] ?? '{}'), true);
if (!is_array($payload)) {
    $payload = [];
}

$lastName = ti_value($payload, ['last_name', 'lastname']);
$firstName = ti_value($payload, ['first_name', 'firstname']);
$middleName = ti_value($payload, ['middle_name', 'middlename']);
$suffix = ti_value($payload, ['suffix']);
$fullName = trim(implode(' ', array_filter([
    $lastName !== '' ? $lastName . ',' : '',
    $firstName,
    $middleName !== '' ? strtoupper(substr($middleName, 0, 1)) . '.' : '',
    $suffix,
])));
if ($fullName === '') {
    $fullName = 'Resident';
}

$address = ti_value($payload, ['full_address'], 'Barangay San Jose, Rodriguez, Rizal');
$purpose = trim((string)($row['purpose'] ?? ''));
if ($purpose === '') {
    $purpose = ti_value($payload, ['request_purpose', 'purpose'], '-');
}

$validity = trim((string)($row['document_validity'] ?? ''));
if ($validity === '') {
    $validity = trim((string)($row['completed_at'] ?? $row['ready_at'] ?? $row['release_timestamp'] ?? ''));
}

ti_json(200, [
    'success' => true,
    'verified' => true,
    'data' => [
        'request_id' => (string)$row['request_id'],
        'or_number' => (string)($row['or_number'] ?? ''),
        'certificate_number' => (string)($row['certificate_number'] ?? ''),
        'status' => ti_stage_label((string)($row['stage'] ?? 'submitted')),
        'status_raw' => (string)($row['stage'] ?? 'submitted'),
        'reason' => (string)($row['status_remarks'] ?? ''),
        'document_type' => (string)($row['document_type'] ?? 'Certificate Request'),
        'full_name' => $fullName,
        'purpose' => $purpose,
        'address' => $address,
        'amount' => $row['amount'] !== null ? (float)$row['amount'] : null,
        'validity' => $validity,
        'submitted_at' => (string)($row['submitted_at'] ?? ''),
    ],
]);
