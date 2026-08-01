<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/security.php';
require_once __DIR__ . '/../General/documentRequestWorkflow.php';

requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin']);

header('Content-Type: application/json; charset=utf-8');

function bm_ensure_establishment_status_table(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS businessmonitoringstatustbl (
        request_id VARCHAR(64) NOT NULL,
        establishment_status ENUM('operational', 'closed', 'archived') NOT NULL DEFAULT 'operational',
        updated_by VARCHAR(64) NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (request_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$conn->query($sql)) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to prepare establishment status storage.']);
    }
}

function bm_select_or_null(mysqli $conn, string $table, string $column, string $alias, string $tableAlias = 'd'): string
{
    return dr_column_exists($conn, $table, $column)
        ? $tableAlias . '.' . $column . ' AS ' . $alias
        : 'NULL AS ' . $alias;
}

function bm_non_empty(array $values): string
{
    foreach ($values as $value) {
        $text = trim(preg_replace('/\s+/', ' ', (string)$value) ?? '');
        if ($text !== '' && !bm_is_protected_value($text)) {
            return $text;
        }
    }

    return '';
}

function bm_is_protected_value($value): bool
{
    $text = trim((string)$value);
    if ($text === '') {
        return false;
    }

    if (function_exists('pii_cipher_prefix') && strpos($text, pii_cipher_prefix()) !== false) {
        return true;
    }

    return function_exists('pii_is_encrypted_value') && pii_is_encrypted_value($text);
}

function bm_decode_payload(array $row): array
{
    if (function_exists('dr_decode_request_payload')) {
        return dr_decode_request_payload($row);
    }

    $payload = json_decode((string)($row['request_details'] ?? '{}'), true);
    if (!is_array($payload)) {
        return [];
    }

    return function_exists('dr_decode_request_payload_value')
        ? dr_decode_request_payload_value($payload)
        : $payload;
}

function bm_normalize_public_file_path(string $rawPath): string
{
    $pathText = trim($rawPath);
    if ($pathText === '') {
        return '';
    }

    $match = [];
    if (preg_match('/\/UnifiedFileAttachment\/[^\s"\']+/i', $pathText, $match)) {
        return (string)($match[0] ?? '');
    }

    return str_starts_with($pathText, '/UnifiedFileAttachment/') ? $pathText : '';
}

function bm_extract_public_file_paths($value): array
{
    $paths = [];

    if (is_array($value)) {
        foreach ($value as $item) {
            foreach (bm_extract_public_file_paths($item) as $path) {
                $paths[] = $path;
            }
        }
        return $paths;
    }

    $normalizedPath = bm_normalize_public_file_path((string)$value);
    if ($normalizedPath !== '') {
        $paths[] = $normalizedPath;
    }

    return $paths;
}

function bm_extract_submitted_documents(array $payload): array
{
    $docs = [];
    $seen = [];
    $fieldMap = [
        'business_reg_file_paths' => [
            'label' => bm_business_registration_label((string)($payload['business_reg_type'] ?? '')),
            'fallback_fields' => ['business_reg_file_path'],
        ],
        'proof_address_file_paths' => [
            'label' => bm_proof_of_business_address_label((string)($payload['proof_address_type'] ?? '')),
            'fallback_fields' => ['proof_address_file_path'],
        ],
        'business_photo_file_paths' => [
            'label' => 'Establishment Photo',
            'fallback_fields' => ['business_photo_file_path'],
        ],
        'renewal_business_reg_file_paths' => [
            'label' => bm_business_registration_label((string)($payload['renewal_business_reg_type'] ?? ''), true),
            'fallback_fields' => ['renewal_business_reg_file_path'],
        ],
        'renewal_proof_address_file_paths' => [
            'label' => bm_proof_of_business_address_label((string)($payload['renewal_proof_address_type'] ?? ''), true),
            'fallback_fields' => ['renewal_proof_address_file_path'],
        ],
        'renewal_business_photo_file_paths' => [
            'label' => 'Updated Establishment Photo',
            'fallback_fields' => ['renewal_business_photo_file_path'],
        ],
    ];

    foreach ($fieldMap as $field => $config) {
        $paths = bm_extract_public_file_paths($payload[$field] ?? []);
        foreach (($config['fallback_fields'] ?? []) as $fallbackField) {
            $paths = array_merge($paths, bm_extract_public_file_paths($payload[$fallbackField] ?? ''));
        }
        $paths = array_values(array_unique($paths));

        $pathCount = count($paths);
        foreach ($paths as $index => $normalizedPath) {
            $publicUrl = appUrl($normalizedPath);
            if (isset($seen[$publicUrl])) {
                continue;
            }
            $seen[$publicUrl] = true;

            $label = (string)($config['label'] ?? 'Submitted Document');
            if ($pathCount > 1) {
                $label .= ' Attachment ' . ($index + 1);
            }

            $docs[] = [
                'label' => $label,
                'path' => $normalizedPath,
                'url' => $publicUrl,
                'name' => basename($normalizedPath),
            ];
        }
    }

    return $docs;
}

function bm_business_registration_label(string $type, bool $updated = false): string
{
    $label = match (strtolower(trim($type))) {
        'dti' => 'DTI Certificate',
        'sec' => 'SEC Certificate',
        default => 'Business Registration',
    };

    return $updated ? 'Updated ' . $label : $label;
}

function bm_proof_of_business_address_label(string $type, bool $updated = false): string
{
    $label = match (strtolower(trim($type))) {
        'lease' => 'Contract of Lease',
        'tct' => 'Transfer Certificate of Title',
        'tax_declaration' => 'Tax Declaration',
        default => 'Proof of Business Address',
    };

    return $updated ? 'Updated ' . $label : $label;
}

function bm_person_name(array $payload, string $prefix): string
{
    return bm_join_person_name(
        $payload[$prefix . 'fn'] ?? '',
        $payload[$prefix . 'mn'] ?? '',
        $payload[$prefix . 'ln'] ?? '',
        $payload[$prefix . 'suffix'] ?? ''
    );
}

function bm_join_person_name($first, $middle, $last, $suffix): string
{
    $parts = [
        trim((string)$first),
        trim((string)$middle),
        trim((string)$last),
        trim((string)$suffix),
    ];

    return trim(implode(' ', array_filter($parts, static fn($value) => $value !== '' && !bm_is_protected_value($value))));
}

function bm_format_timestamp(string $value): string
{
    $text = trim($value);
    if ($text === '') {
        return '';
    }

    try {
        $date = new DateTime($text);
        return $date->format('M d, Y h:i A');
    } catch (Throwable $e) {
        return $text;
    }
}

function bm_status_bucket(string $stage): string
{
    $value = strtolower(trim($stage));
    if ($value === '') {
        return 'pending';
    }
    if (strpos($value, 'rejected') !== false || strpos($value, 'failed') !== false || $value === DR_STAGE_CANCELLED) {
        return 'denied';
    }
    if (in_array($value, [DR_STAGE_PAYMENT_VERIFIED, DR_STAGE_READY_FOR_CLAIM, DR_STAGE_COMPLETED], true)) {
        return 'verified';
    }

    return 'pending';
}

function bm_is_business_monitoring_request(array $row, array $payload): bool
{
    $docType = bm_non_empty([
        $row['document_type'] ?? '',
        $payload['document_type'] ?? '',
    ]);
    $docKey = dr_canonical_document_type_key(dr_normalize_document_type($docType));
    $purpose = strtolower(bm_non_empty([
        $row['purpose'] ?? '',
        $payload['request_purpose'] ?? '',
        $payload['purpose'] ?? '',
    ]));
    $businessName = bm_non_empty([
        $payload['business_name'] ?? '',
        $payload['businessName'] ?? '',
        $payload['business_trade_name'] ?? '',
        $payload['trade_name'] ?? '',
        $payload['establishment_name'] ?? '',
    ]);
    $plateNumber = bm_non_empty([
        $payload['_preview_plate_number'] ?? '',
        $payload['plate_number'] ?? '',
        $payload['business_plate_number'] ?? '',
        $payload['vehicle_plate_number'] ?? '',
    ]);

    if (in_array($docKey, [
        'barangayclearanceforbusinesspermit',
        'clearanceforbusinesspermit',
        'barangaybusinessclearance',
        'businessclearance',
    ], true)) {
        return true;
    }

    if ($businessName !== '' && strpos($purpose, 'business permit') !== false) {
        return true;
    }

    return false;
}

function bm_is_commercial_establishment_request(array $row, array $payload): bool
{
    $docType = bm_non_empty([$row['document_type'] ?? '', $payload['document_type'] ?? '']);
    $docKey = dr_canonical_document_type_key(dr_normalize_document_type($docType));
    return in_array($docKey, [
        'barangayclearanceforcommercialpermit',
        'clearanceforcommercialpermit',
        'commercialpermit',
        'barangayclearanceforcommercialbuildingpermit',
        'clearanceforcommercialbuildingpermit',
        'commercialbuildingpermit',
    ], true);
}

if (!dr_table_exists($conn, 'documentrequesttbl')) {
    dr_respond_json(200, ['success' => true, 'items' => []]);
}

bm_ensure_establishment_status_table($conn);
$monitoringKind = strtolower(trim((string)($_GET['kind'] ?? 'business')));
$isCommercialDirectory = $monitoringKind === 'commercial';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken(true);
    $action = strtolower(trim((string)($_POST['action'] ?? '')));
    if ($action !== 'set_establishment_status') {
        dr_respond_json(422, ['success' => false, 'message' => 'Unsupported business monitoring action.']);
    }

    $requestId = trim((string)($_POST['request_id'] ?? ''));
    $status = strtolower(trim((string)($_POST['status'] ?? '')));
    if ($requestId === '' || !in_array($status, ['operational', 'closed', 'archived'], true)) {
        dr_respond_json(422, ['success' => false, 'message' => 'Select a valid establishment status.']);
    }

    $requestRow = dr_fetch_request($conn, $requestId);
    if (!$requestRow || !bm_is_business_monitoring_request($requestRow, bm_decode_payload($requestRow))) {
        dr_respond_json(404, ['success' => false, 'message' => 'Business establishment record not found.']);
    }

    $updatedBy = trim((string)($_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? ''));
    $stmtStatus = $conn->prepare("INSERT INTO businessmonitoringstatustbl (request_id, establishment_status, updated_by, updated_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE establishment_status=VALUES(establishment_status), updated_by=VALUES(updated_by), updated_at=NOW()");
    if (!$stmtStatus) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to prepare the establishment status update.']);
    }
    $stmtStatus->bind_param('sss', $requestId, $status, $updatedBy);
    $saved = $stmtStatus->execute();
    $stmtStatus->close();
    if (!$saved) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to update the establishment status.']);
    }

    dr_respond_json(200, [
        'success' => true,
        'message' => 'Establishment marked as ' . ucfirst($status) . '.',
        'request_id' => $requestId,
        'establishment_status' => $status,
    ]);
}

$baseSelects = [
    'd.request_id AS request_id',
    bm_select_or_null($conn, 'documentrequesttbl', 'resident_user_id', 'resident_user_id'),
    bm_select_or_null($conn, 'documentrequesttbl', 'resident_id', 'resident_id'),
    bm_select_or_null($conn, 'documentrequesttbl', 'resident_name', 'resident_name'),
    bm_select_or_null($conn, 'documentrequesttbl', 'document_type', 'document_type'),
    bm_select_or_null($conn, 'documentrequesttbl', 'purpose', 'purpose'),
    bm_select_or_null($conn, 'documentrequesttbl', 'stage', 'stage'),
    bm_select_or_null($conn, 'documentrequesttbl', 'submitted_at', 'submitted_at'),
    bm_select_or_null($conn, 'documentrequesttbl', 'request_timestamp', 'request_timestamp'),
    dr_column_exists($conn, 'documentrequesttbl', 'request_details')
        ? 'd.request_details AS request_details'
        : 'NULL AS request_details',
];

if (dr_column_exists($conn, 'documentrequesttbl', 'status_id_request')) {
    $baseSelects[] = 'd.status_id_request AS status_id_request';
}
if (dr_column_exists($conn, 'documentrequesttbl', 'status_id')) {
    $baseSelects[] = 'd.status_id AS status_id';
}

$extraSelects = [];
$extraJoins = [];
if (dr_table_exists($conn, 'residentinformationtbl')) {
    if (dr_column_exists($conn, 'documentrequesttbl', 'resident_user_id')) {
        $extraSelects[] = "riu.firstname AS _riu_firstname";
        $extraSelects[] = "riu.middlename AS _riu_middlename";
        $extraSelects[] = "riu.lastname AS _riu_lastname";
        $extraSelects[] = "riu.suffix AS _riu_suffix";
        $extraSelects[] = "NULLIF(riu.sector_membership, '') AS _sector_membership_by_user";
        $extraJoins[] = 'LEFT JOIN residentinformationtbl riu ON riu.user_id = d.resident_user_id';
    }
    if (dr_column_exists($conn, 'documentrequesttbl', 'resident_id')) {
        $extraSelects[] = "rir.firstname AS _rir_firstname";
        $extraSelects[] = "rir.middlename AS _rir_middlename";
        $extraSelects[] = "rir.lastname AS _rir_lastname";
        $extraSelects[] = "rir.suffix AS _rir_suffix";
        $extraSelects[] = "NULLIF(rir.sector_membership, '') AS _sector_membership_by_resident";
        $extraJoins[] = 'LEFT JOIN residentinformationtbl rir ON rir.resident_id = d.resident_id';
    }
}
if (dr_table_exists($conn, 'residentaddresstbl')) {
    if (dr_column_exists($conn, 'documentrequesttbl', 'resident_user_id') && dr_table_exists($conn, 'residentinformationtbl')) {
        $extraSelects[] = "NULLIF(rau.area_number, '') AS _area_number_by_user";
        $extraJoins[] = "LEFT JOIN residentaddresstbl rau ON rau.address_id = (
            SELECT a2.address_id
            FROM residentaddresstbl a2
            WHERE a2.resident_id = riu.resident_id
            ORDER BY a2.address_id DESC
            LIMIT 1
        )";
    }
    if (dr_column_exists($conn, 'documentrequesttbl', 'resident_id')) {
        $extraSelects[] = "NULLIF(rar.area_number, '') AS _area_number_by_resident";
        $extraJoins[] = "LEFT JOIN residentaddresstbl rar ON rar.address_id = (
            SELECT a2.address_id
            FROM residentaddresstbl a2
            WHERE a2.resident_id = d.resident_id
            ORDER BY a2.address_id DESC
            LIMIT 1
        )";
    }
}

$whereParts = [];
if ($isCommercialDirectory) {
    if (dr_column_exists($conn, 'documentrequesttbl', 'document_type')) {
        $whereParts[] = "LOWER(COALESCE(d.document_type, '')) LIKE '%commercial%permit%'";
    }
    if (dr_column_exists($conn, 'documentrequesttbl', 'request_details')) {
        $whereParts[] = "d.request_details LIKE '%establishment_name%'";
    }
} else {
if (dr_column_exists($conn, 'documentrequesttbl', 'document_type')) {
    $whereParts[] = "LOWER(COALESCE(d.document_type, '')) LIKE '%business%'";
}
if (dr_column_exists($conn, 'documentrequesttbl', 'purpose')) {
    $whereParts[] = "LOWER(COALESCE(d.purpose, '')) LIKE 'business permit%'";
}
if (dr_column_exists($conn, 'documentrequesttbl', 'request_details')) {
    $whereParts[] = "d.request_details LIKE '%business_name%'";
}
}

$orderCol = dr_column_exists($conn, 'documentrequesttbl', 'submitted_at')
    ? 'submitted_at'
    : (dr_column_exists($conn, 'documentrequesttbl', 'request_timestamp') ? 'request_timestamp' : 'request_id');

$sql = "
    SELECT
        " . implode(",\n        ", $baseSelects)
        . ($extraSelects ? ",\n        " . implode(",\n        ", $extraSelects) : '') . "
    FROM documentrequesttbl d
    " . ($extraJoins ? implode("\n    ", $extraJoins) : '') . "
";

if ($whereParts) {
    $sql .= "\nWHERE (" . implode(' OR ', $whereParts) . ")\n";
}

$sql .= "ORDER BY d.{$orderCol} DESC, d.request_id DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    dr_respond_json(500, ['success' => false, 'message' => 'Failed to prepare business monitoring query.']);
}

$stmt->execute();
$result = $stmt->get_result();
$items = [];
$establishmentStatuses = [];
$statusResult = $conn->query("SELECT request_id, establishment_status FROM businessmonitoringstatustbl");
if ($statusResult) {
    while ($statusRow = $statusResult->fetch_assoc()) {
        $statusRequestId = trim((string)($statusRow['request_id'] ?? ''));
        if ($statusRequestId !== '') {
            $establishmentStatuses[$statusRequestId] = strtolower(trim((string)($statusRow['establishment_status'] ?? 'operational')));
        }
    }
    $statusResult->free();
}

while ($row = $result->fetch_assoc()) {
    if (function_exists('pii_decrypt_assoc')) {
        $row = pii_decrypt_assoc($row, [
            '_riu_firstname',
            '_riu_middlename',
            '_riu_lastname',
            '_riu_suffix',
            '_rir_firstname',
            '_rir_middlename',
            '_rir_lastname',
            '_rir_suffix',
            'resident_name',
        ]) ?? $row;
    }

    $payload = bm_decode_payload($row);
    if ($isCommercialDirectory
        ? !bm_is_commercial_establishment_request($row, $payload)
        : !bm_is_business_monitoring_request($row, $payload)) {
        continue;
    }

    if (trim((string)($row['stage'] ?? '')) === '') {
        dr_sync_stage_from_status_lookup($conn, $row);
    }

    $stage = strtolower(trim((string)($row['stage'] ?? '')));
    $rowRequestId = trim((string)($row['request_id'] ?? ''));
    $establishmentStatus = $establishmentStatuses[$rowRequestId] ?? 'operational';
    if ($stage !== DR_STAGE_COMPLETED) {
        continue;
    }

    $documentType = bm_non_empty([
        $row['document_type'] ?? '',
        $payload['document_type'] ?? '',
        'Barangay Clearance for Business Permit',
    ]);
    $submittedAtRaw = bm_non_empty([
        $row['submitted_at'] ?? '',
        $row['request_timestamp'] ?? '',
    ]);
    $residentNameByUser = bm_join_person_name(
        $row['_riu_firstname'] ?? '',
        $row['_riu_middlename'] ?? '',
        $row['_riu_lastname'] ?? '',
        $row['_riu_suffix'] ?? ''
    );
    $residentNameByResident = bm_join_person_name(
        $row['_rir_firstname'] ?? '',
        $row['_rir_middlename'] ?? '',
        $row['_rir_lastname'] ?? '',
        $row['_rir_suffix'] ?? ''
    );
    $applicantName = bm_non_empty([
        $row['resident_name'] ?? '',
        $residentNameByUser,
        $residentNameByResident,
        $payload['resident_name'] ?? '',
        bm_join_person_name(
            $payload['first_name'] ?? $payload['firstname'] ?? '',
            $payload['middle_name'] ?? $payload['middlename'] ?? '',
            $payload['last_name'] ?? $payload['lastname'] ?? '',
            $payload['suffix'] ?? ''
        ),
    ]);
    $ownerType = bm_non_empty([
        $payload['owner_type'] ?? '',
    ]);
    $ownerName = $ownerType === 'Renter'
        ? bm_non_empty([
            bm_person_name($payload, 'ro_'),
            $payload['owner_name'] ?? '',
        ])
        : $applicantName;
    $businessName = bm_non_empty([
        $payload['business_name'] ?? '',
        $payload['businessName'] ?? '',
        $payload['business_trade_name'] ?? '',
        $payload['trade_name'] ?? '',
        $payload['establishment_name'] ?? '',
    ]);
    $plateNumber = bm_non_empty([
        $payload['_preview_plate_number'] ?? '',
        $payload['plate_number'] ?? '',
        $payload['business_plate_number'] ?? '',
        $payload['vehicle_plate_number'] ?? '',
    ]);
    $businessType = bm_non_empty([
        $payload['business_type'] ?? '',
        $payload['businessType'] ?? '',
    ]);
    $businessAddress = bm_non_empty([
        $payload['business_full_address'] ?? '',
        $payload['business_address'] ?? '',
        $payload['location'] ?? '',
    ]);
    $applicationType = bm_non_empty([
        dr_request_application_type($row, $payload),
        $payload['application_type'] ?? '',
    ]);
    $areaNumber = bm_non_empty([
        $payload['full_area_number'] ?? '',
        $payload['area_number'] ?? '',
        $row['_area_number_by_user'] ?? '',
        $row['_area_number_by_resident'] ?? '',
    ]);
    $sectorMembership = bm_non_empty([
        $row['_sector_membership_by_user'] ?? '',
        $row['_sector_membership_by_resident'] ?? '',
    ]);
    $stage = trim((string)($row['stage'] ?? ''));

    $items[] = [
        'request_id' => trim((string)($row['request_id'] ?? '')),
        'resident_id' => trim((string)($row['resident_id'] ?? '')),
        'resident_user_id' => trim((string)($row['resident_user_id'] ?? '')),
        'document_type' => $documentType,
        'purpose' => bm_non_empty([
            $row['purpose'] ?? '',
            $payload['request_purpose'] ?? '',
            $payload['purpose'] ?? '',
        ]),
        'submitted_at' => $submittedAtRaw,
        'submitted_at_display' => bm_format_timestamp($submittedAtRaw),
        'plate_number' => $plateNumber,
        'business_name' => $businessName,
        'business_type' => $businessType,
        'application_type' => $applicationType,
        'applicant_name' => $applicantName,
        'owner_type' => $ownerType,
        'owner_name' => $ownerName,
        'business_address' => $businessAddress,
        'area_number' => $areaNumber,
        'sector_membership' => $sectorMembership,
        'submitted_documents' => bm_extract_submitted_documents($payload),
        'stage' => $stage,
        'stage_label' => dr_stage_label($stage),
        'status_bucket' => bm_status_bucket($stage),
        'establishment_status' => in_array($establishmentStatus, ['operational', 'closed', 'archived'], true)
            ? $establishmentStatus
            : 'operational',
        'establishment_name' => bm_non_empty([$payload['establishment_name'] ?? '', $businessName]),
        'establishment_address' => bm_non_empty([
            $payload['location'] ?? '',
            $payload['lot_full_address'] ?? '',
            $payload['business_full_address'] ?? '',
            $businessAddress,
        ]),
        'establishment_area' => bm_non_empty([
            $payload['area_number'] ?? '',
            $payload['lot_area_number'] ?? '',
            $row['_area_number_by_user'] ?? '',
            $row['_area_number_by_resident'] ?? '',
        ]),
    ];
}

$stmt->close();

dr_respond_json(200, ['success' => true, 'items' => $items]);
