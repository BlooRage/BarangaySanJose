<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($requestMethod, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/documentRequestWorkflow.php';
require_once __DIR__ . '/../General/documentModuleSettings.php';

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
        'payment_verified' => 'For Release',
        'ready_for_claim' => 'For Release',
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
        'paymentverified' => 'ready_for_claim',
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

function ti_table_exists(mysqli $conn, string $table): bool {
    $tableEsc = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$tableEsc}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

$requestId = trim((string)($_GET['request_id'] ?? ''));
$verificationCode = trim((string)($_GET['vc'] ?? ''));

if ($requestId === '') {
    ti_json(422, ['success' => false, 'message' => 'Missing request_id.']);
}
if (strlen($requestId) > 64 || strlen($verificationCode) > 128) {
    ti_json(422, ['success' => false, 'message' => 'Invalid verification link.']);
}

$statusColumn = dr_request_status_column($conn);
if ($statusColumn === null) {
    ti_json(500, ['success' => false, 'message' => 'Missing status column in documentrequesttbl.']);
}

$selectResidentUserId = dr_column_exists($conn, 'documentrequesttbl', 'resident_user_id')
    ? 'resident_user_id'
    : "'' AS resident_user_id";
$selectRequestDetails = dr_column_exists($conn, 'documentrequesttbl', 'request_details')
    ? 'request_details'
    : "'{}' AS request_details";
$selectRequestTimestamp = dr_column_exists($conn, 'documentrequesttbl', 'request_timestamp')
    ? 'request_timestamp'
    : (dr_column_exists($conn, 'documentrequesttbl', 'created_at') ? 'created_at AS request_timestamp' : "'' AS request_timestamp");
$selectDocumentValidity = dr_column_exists($conn, 'documentrequesttbl', 'document_validity')
    ? 'document_validity'
    : "'' AS document_validity";
$selectReleaseTimestamp = dr_column_exists($conn, 'documentrequesttbl', 'release_timestamp')
    ? 'release_timestamp'
    : (dr_column_exists($conn, 'documentrequesttbl', 'ready_at') ? 'ready_at AS release_timestamp' : "'' AS release_timestamp");
$selectIssuedFilePath = dr_column_exists($conn, 'documentrequesttbl', 'issued_file_path')
    ? 'issued_file_path'
    : "'' AS issued_file_path";

$stmt = $conn->prepare("
    SELECT
        request_id,
        {$selectResidentUserId},
        {$selectRequestDetails},
        {$statusColumn} AS status_id_request,
        (SELECT sl.status_name FROM statuslookuptbl sl WHERE sl.status_id = documentrequesttbl.{$statusColumn} LIMIT 1) AS status_lookup_name,
        {$selectRequestTimestamp},
        {$selectDocumentValidity},
        {$selectReleaseTimestamp},
        {$selectIssuedFilePath}
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

$payload = json_decode((string)($row['request_details'] ?? '{}'), true);
if (!is_array($payload)) {
    $payload = [];
}

$row['document_type'] = trim((string)($payload['document_type'] ?? ''));
if ($row['document_type'] === '') {
    $issuanceTable = function_exists('dr_preferred_issuance_table') ? dr_preferred_issuance_table($conn) : null;
    if ($issuanceTable !== null) {
        $q = $conn->prepare("SELECT certificate_type FROM {$issuanceTable} WHERE request_id = ? LIMIT 1");
        if ($q) {
            $q->bind_param('s', $requestId);
            $q->execute();
            $q->bind_result($certType);
            if ($q->fetch()) {
                $row['document_type'] = trim((string)$certType);
            }
            $q->close();
        }
    }
}
if ($row['document_type'] === '') {
    $row['document_type'] = 'Certificate Request';
}
$verificationSettings = dr_is_clearance_document_type((string)$row['document_type'])
    ? dms_resolve_clearance_settings($conn)
    : dms_resolve_issuance_settings($conn);
if (empty($verificationSettings['qr_verification_enabled'])) {
    ti_json(403, ['success' => false, 'verified' => false, 'message' => 'QR verification is currently disabled for this department.']);
}
$row['purpose'] = trim((string)($payload['request_purpose'] ?? $payload['purpose'] ?? ''));
$row['status_remarks'] = trim((string)($payload['status_remarks'] ?? ''));
$row['certificate_number'] = '';
$row['verification_code'] = '';
if (function_exists('dr_get_issuance_request_meta')) {
    $issuanceMeta = dr_get_issuance_request_meta($conn, $requestId);
    $row['certificate_number'] = trim((string)($issuanceMeta['certificate_number'] ?? ''));
    $row['verification_code'] = trim((string)($issuanceMeta['verification_code'] ?? ''));
}
$row['submitted_at'] = trim((string)($row['request_timestamp'] ?? ''));
$row['ready_at'] = '';
$row['completed_at'] = '';

$row['amount'] = null;
$row['or_number'] = '';
if (ti_table_exists($conn, 'financetransactiontbl')) {
    $tx = $conn->prepare("SELECT transaction_amount, or_number FROM financetransactiontbl WHERE request_id = ? LIMIT 1");
    if ($tx) {
        $tx->bind_param('s', $requestId);
        $tx->execute();
        $txRow = $tx->get_result()->fetch_assoc();
        $tx->close();
        if (is_array($txRow)) {
            $row['amount'] = isset($txRow['transaction_amount']) ? (float)$txRow['transaction_amount'] : null;
            $row['or_number'] = (string)($txRow['or_number'] ?? '');
        }
    }
}

$statusMappedStage = ti_stage_from_status_name((string)($row['status_lookup_name'] ?? ''));
if ($statusMappedStage !== null) {
    $row['stage'] = $statusMappedStage;
}

$expectedCode = trim((string)($row['verification_code'] ?? ''));
$legacyVerification = false;
if ($expectedCode === '') {
    // Preserve old issued-document QR links without treating every predictable
    // request ID as authorization to retrieve a resident's personal details.
    $legacyStage = strtolower(trim((string)($row['stage'] ?? '')));
    $hasIssuedArtifact = trim((string)($row['certificate_number'] ?? '')) !== ''
        || trim((string)($row['issued_file_path'] ?? '')) !== '';
    $isIssuedStage = in_array($legacyStage, ['ready_for_claim', 'completed'], true);
    $legacyVerification = $verificationCode !== ''
        && hash_equals(strtolower((string)$row['request_id']), strtolower($verificationCode))
        && $hasIssuedArtifact
        && $isIssuedStage;

    if (!$legacyVerification) {
        ti_json(404, ['success' => false, 'message' => 'Transaction not found or invalid verification link.']);
    }
} elseif ($verificationCode === '' || !hash_equals(strtolower($expectedCode), strtolower($verificationCode))) {
    ti_json(404, ['success' => false, 'message' => 'Transaction not found or invalid verification link.']);
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

$address = ti_value($payload, ['applicant_full_address', 'owner_full_address', 'full_address', 'full_address_display', 'address', 'complete_address'], 'Barangay San Jose, Rodriguez, Rizal');
$documentType = (string)($row['document_type'] ?? 'Certificate Request');
$purpose = trim((string)($row['purpose'] ?? ''));
if ($purpose === '') {
    $purpose = ti_value($payload, ['request_purpose', 'purpose'], '-');
}

dr_hydrate_request_delivery_fields($row, $payload);

$validity = trim((string)($row['document_validity'] ?? ''));
if (dr_is_barangay_id_document_type($documentType)) {
    $barangayIdValidUntil = dr_barangay_id_valid_until_datetime($row);
    if ($barangayIdValidUntil instanceof DateTimeImmutable) {
        $validity = $barangayIdValidUntil->format('Y-m-d H:i:s');
    }
}
if ($validity === '') {
    $validity = trim((string)($row['completed_at'] ?? $row['ready_at'] ?? $row['release_timestamp'] ?? ''));
}

$responseData = [
    'request_id' => (string)$row['request_id'],
    'or_number' => (string)($row['or_number'] ?? ''),
    'certificate_number' => (string)($row['certificate_number'] ?? ''),
    'status' => ti_stage_label((string)($row['stage'] ?? 'submitted')),
    'status_raw' => (string)($row['stage'] ?? 'submitted'),
    'reason' => (string)($row['status_remarks'] ?? ''),
    'document_type' => $documentType,
    'full_name' => $fullName,
    'purpose' => $purpose,
    'address' => $address,
    'amount' => $row['amount'] !== null ? (float)$row['amount'] : null,
    'validity' => $validity,
    'submitted_at' => (string)($row['submitted_at'] ?? ''),
    'hard_copy_status' => (string)($row['hard_copy_status'] ?? ''),
    'hard_copy_status_label' => (string)($row['hard_copy_status_label'] ?? ''),
    'hard_copy_notice' => (string)($row['hard_copy_notice'] ?? ''),
    'legacy_verification' => $legacyVerification,
];

if ($legacyVerification) {
    // Keep the existing response shape for old QR pages, but expose only the
    // minimum non-personal facts needed to confirm an issued record.
    $responseData['or_number'] = '';
    $responseData['reason'] = '';
    $responseData['full_name'] = '';
    $responseData['purpose'] = '';
    $responseData['address'] = '';
    $responseData['amount'] = null;
    $responseData['submitted_at'] = '';
    $responseData['hard_copy_status'] = '';
    $responseData['hard_copy_status_label'] = '';
    $responseData['hard_copy_notice'] = '';
    $responseData['verification_notice'] = 'Legacy verification link. Reissue this document to use a secure verification code.';
}

ti_json(200, [
    'success' => true,
    'verified' => true,
    'data' => $responseData,
]);
