<?php
require_once __DIR__ . '/../../PhpFiles/General/connection.php';
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../../PhpFiles/General/documentRequestWorkflow.php';
require_once __DIR__ . '/../../PhpFiles/General/piiCrypto.php';
require_once __DIR__ . '/../../PhpFiles/General/documentModuleSettings.php';

// ── Helpers ──────────────────────────────────────────────────────────────────
function rp_table_exists(mysqli $conn, string $t): bool {
    $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
}

function rp_column_exists(mysqli $conn, string $table, string $column): bool {
    static $cache = [];
    $key = strtolower($table . '.' . $column);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return $cache[$key] = false;
    }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $exists = (bool)($stmt->get_result()->fetch_row()[0] ?? false);
    $stmt->close();
    return $cache[$key] = $exists;
}

function rp_safe_query(mysqli $conn, string $sql): array {
    $r = $conn->query($sql);
    if (!$r) return [];
    $rows = [];
    while ($row = $r->fetch_assoc()) $rows[] = $row;
    $r->free();
    return $rows;
}

function rp_date_label(string $d): string {
    $ts = strtotime($d);
    return $ts ? date('F j, Y', $ts) : $d;
}

function rp_pct(float $part, float $total): string {
    if ($total <= 0) return '0.0%';
    return number_format(($part / $total) * 100, 1) . '%';
}

function rp_roman_numeral(int $value): string {
    if ($value <= 0) {
        return '0';
    }

    $map = [
        1000 => 'M',
        900 => 'CM',
        500 => 'D',
        400 => 'CD',
        100 => 'C',
        90 => 'XC',
        50 => 'L',
        40 => 'XL',
        10 => 'X',
        9 => 'IX',
        5 => 'V',
        4 => 'IV',
        1 => 'I',
    ];

    $result = '';
    foreach ($map as $arabic => $roman) {
        while ($value >= $arabic) {
            $result .= $roman;
            $value -= $arabic;
        }
    }

    return $result;
}

function rp_section_heading(array $orderedSections, array $visibleSections, string $key): string {
    $position = 0;
    foreach ($orderedSections as $sectionKey => $label) {
        if (!in_array($sectionKey, $visibleSections, true)) {
            continue;
        }
        $position++;
        if ($sectionKey === $key) {
            return rp_roman_numeral($position) . '. ' . $label;
        }
    }

    return $orderedSections[$key] ?? ucwords(str_replace('_', ' ', $key));
}

function rp_stage_label(string $stage): string {
    $normalized = strtolower(trim($stage));
    $map = [
        'submitted' => 'Pending Verification',
        'fee_tagging' => 'Fee Tagging',
        'for_payment' => 'For Payment',
        'payment_submitted' => 'Payment Submitted',
        'payment_verified' => 'Payment Verified',
        'payment_rejected' => 'Payment Rejected',
        'for_interview' => 'For Interview',
        'interview_failed' => 'Interview Failed',
        'for_inspection' => 'For Inspection',
        'inspection_failed' => 'Inspection Failed',
        'ready_for_claim' => 'For Release',
        'completed' => 'Completed',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
    ];
    if (isset($map[$normalized])) {
        return $map[$normalized];
    }
    return ucwords(str_replace('_', ' ', $normalized !== '' ? $normalized : 'Unknown'));
}

function rp_payment_method_label(string $method): string {
    $normalized = strtolower(trim($method));
    $map = [
        'gcash' => 'GCash',
        'barangay' => 'Walk-in / Barangay Hall',
        'walk_in' => 'Walk-in / Barangay Hall',
        'walkin' => 'Walk-in / Barangay Hall',
        'cash' => 'Walk-in / Barangay Hall',
        'unspecified' => 'Unspecified',
    ];
    if (isset($map[$normalized])) {
        return $map[$normalized];
    }
    return $normalized !== '' ? ucwords(str_replace('_', ' ', $normalized)) : 'Unspecified';
}

function rp_document_type_label(string $raw): string {
    $value = trim($raw);
    if ($value === '') {
        return 'Unspecified';
    }

    $key = preg_replace('/[^a-z0-9]+/', '', strtolower($value));
    $map = [
        'certificateofindigency' => 'Certificate of Indigency',
        'goodmoral' => 'Certificate of Good Moral',
        'certificateofgoodmoral' => 'Certificate of Good Moral',
        'residency' => 'Certificate of Residency',
        'certificateofresidency' => 'Certificate of Residency',
        'certificateofresidence' => 'Certificate of Residency',
        'cohabitation' => 'Certificate of Cohabitation',
        'certificateofcohabitation' => 'Certificate of Cohabitation',
        'identity' => 'Certificate of Identity',
        'certificateofidentity' => 'Certificate of Identity',
        'generalcertification' => 'General Certificate',
        'firsttimejobseeker' => 'First Time Job Seeker Certificate',
        'firsttimejobseekers' => 'First Time Job Seeker Certificate',
        'firsttimejobseekercertificate' => 'First Time Job Seeker Certificate',
        'barangayid' => 'Barangay ID',
    ];
    if (isset($map[$key])) {
        return $map[$key];
    }
    if (str_contains($key, 'tricycle')) {
        return 'Barangay Clearance for Tricycle Permit';
    }
    if (str_contains($key, 'waterpermit')) {
        return 'Barangay Clearance for Water Permit';
    }
    if (str_contains($key, 'electricalpermit')) {
        return 'Barangay Clearance for Electrical Permit';
    }
    if (str_contains($key, 'residentialpermit')) {
        return 'Barangay Clearance for Residential Permit';
    }
    if (str_contains($key, 'commercialpermit')) {
        return 'Barangay Clearance for Commercial Permit';
    }
    if (str_contains($key, 'businesspermit') || str_contains($key, 'businessclearance')) {
        return 'Barangay Clearance for Business Permit';
    }
    if (str_contains($key, 'barangayclearance') || str_contains($key, 'barangaycertification') || $key === 'clearance') {
        return 'Barangay Certification';
    }

    return $value;
}

function rp_parse_query_list($value, ?callable $normalizer = null): array {
    $items = is_array($value) ? $value : [$value];
    $resolved = [];
    foreach ($items as $item) {
        $text = trim((string)$item);
        if ($text === '') {
            continue;
        }
        if ($normalizer !== null) {
            $text = trim((string)$normalizer($text));
            if ($text === '') {
                continue;
            }
        }
        $resolved[] = $text;
    }
    return array_values(array_unique($resolved));
}

function rp_document_request_key(string $raw): string {
    $value = trim($raw);
    if ($value === '') {
        return '';
    }

    $key = preg_replace('/[^a-z0-9]+/', '', strtolower($value));
    $map = [
        'barangayid' => 'barangay_id',
        'applicationforbarangayid' => 'barangay_id',
        'cohabitation' => 'cert_cohabitation',
        'certificateofcohabitation' => 'cert_cohabitation',
        'goodmoral' => 'cert_good_moral',
        'certificateofgoodmoral' => 'cert_good_moral',
        'certificateforjailvisitation' => 'cert_jail_visitation',
        'certificateofrelationshipforjailvisitation' => 'cert_jail_visitation',
        'certificateofrelationshipforjailvisit' => 'cert_jail_visitation',
        'jailvisitation' => 'cert_jail_visitation',
        'firsttimejobseeker' => 'cert_first_time_job_seeker',
        'firsttimejobseekercertificate' => 'cert_first_time_job_seeker',
        'certificateforfirsttimejobseeker' => 'cert_first_time_job_seeker',
        'residency' => 'cert_residency',
        'certificateofresidency' => 'cert_residency',
        'certificateofresidence' => 'cert_residency',
        'indigency' => 'cert_indigency',
        'certificateofindigency' => 'cert_indigency',
        'generalcertification' => 'cert_general',
    ];
    if (isset($map[$key])) {
        return $map[$key];
    }
    if (str_starts_with($key, 'generalcertificate')) {
        return 'cert_general';
    }
    if (str_contains($key, 'businesspermit') || str_contains($key, 'businessclearance')) {
        return 'clr_business_permit';
    }
    if (str_contains($key, 'tricyclepermit') || str_contains($key, 'tricycleclearance')) {
        return 'clr_tricycle_permit';
    }
    if (str_contains($key, 'electricalpermit') || str_contains($key, 'electricpermit')) {
        return 'clr_electric_permit';
    }
    if (str_contains($key, 'waterpermit')) {
        return 'clr_water_permit';
    }
    if (str_contains($key, 'residentialpermit') || str_contains($key, 'residentialbuildingpermit')) {
        return 'clr_residential_permit';
    }
    if (str_contains($key, 'commercialpermit') || str_contains($key, 'commercialbuildingpermit')) {
        return 'clr_commercial_permit';
    }

    return '';
}

function rp_issuance_module_config(string $module): ?array {
    $configs = [
        'certificate_issuance' => [
            'label' => 'Certificate Issuance Report',
            'summary_label' => 'Certificate Issuance Requests',
            'show_breakdown_sectors' => false,
            'request_types' => [
                'cert_cohabitation' => 'Certificate of Cohabitation',
                'cert_good_moral' => 'Certificate of Good Moral',
                'cert_jail_visitation' => 'Certificate of Relationship for Jail Visitation',
                'cert_first_time_job_seeker' => 'First Time Job Seeker Certificate',
                'cert_residency' => 'Certificate of Residency',
                'cert_indigency' => 'Certificate of Indigency',
                'cert_general' => 'General Certificate',
                'barangay_id' => 'Barangay ID',
            ],
        ],
        'clearance_issuance' => [
            'label' => 'Clearance Issuance Report',
            'summary_label' => 'Clearance Issuance Requests',
            'show_breakdown_sectors' => false,
            'request_types' => [
                'clr_business_permit' => 'Clearance for Business Permit',
                'clr_tricycle_permit' => 'Clearance for Tricycle Permit',
                'clr_electric_permit' => 'Clearance for Electric Permit',
                'clr_water_permit' => 'Clearance for Water Permit',
                'clr_residential_permit' => 'Clearance for Residential Permit',
                'clr_commercial_permit' => 'Clearance for Commercial Permit',
            ],
        ],
    ];

    return $configs[$module] ?? null;
}

function rp_request_status_options(): array {
    return [
        'completed' => 'Completed',
        'pending' => 'Pending',
        'rejected' => 'Rejected',
    ];
}

function rp_request_status_key(string $stage): string {
    $normalized = strtolower(trim($stage));
    if ($normalized === 'completed') {
        return 'completed';
    }
    if (in_array($normalized, ['rejected', 'cancelled', 'interview_failed', 'inspection_failed', 'payment_rejected'], true)) {
        return 'rejected';
    }
    return 'pending';
}

function rp_document_request_effective_fee(mysqli $conn, array $row): ?float {
    $docType = trim((string)($row['document_type'] ?? ''));
    if ($docType === '') {
        return null;
    }
    $baseFee = null;
    if (isset($row['fee_amount']) && $row['fee_amount'] !== null && $row['fee_amount'] !== '' && is_numeric((string)$row['fee_amount'])) {
        $baseFee = (float)$row['fee_amount'];
    }
    return dr_get_effective_document_fee_amount($conn, $docType, $row, $baseFee);
}

function rp_document_request_effective_stage(mysqli $conn, array $row): string {
    $stage = strtolower(trim((string)($row['stage'] ?? 'submitted')));
    $effectiveFee = rp_document_request_effective_fee($conn, $row);
    if ($effectiveFee !== null && $effectiveFee <= 0.0) {
        if (in_array($stage, ['for_payment', 'payment_submitted', 'payment_verified', 'payment_rejected'], true)) {
            return 'ready_for_claim';
        }
    }
    return $stage !== '' ? $stage : 'submitted';
}

function rp_request_channel_label(array $payload): string {
    $channel = strtolower(trim((string)($payload['_submission_channel'] ?? '')));
    if ($channel !== '' && str_contains($channel, 'walkin')) {
        return 'Walk-in';
    }
    return 'Online';
}

function rp_decode_json_assoc($raw): array {
    if (is_array($raw)) {
        return $raw;
    }
    $text = trim((string)$raw);
    if ($text === '') {
        return [];
    }
    $decoded = json_decode($text, true);
    return is_array($decoded) ? $decoded : [];
}

function rp_first_existing_datetime_expr(mysqli $conn, string $table, string $alias, array $columns): string {
    $parts = [];
    foreach ($columns as $column) {
        if (!rp_column_exists($conn, $table, $column)) {
            continue;
        }
        $parts[] = "NULLIF({$alias}.{$column}, '0000-00-00 00:00:00')";
    }
    if (!$parts) {
        return "NULL";
    }
    return 'COALESCE(' . implode(', ', $parts) . ')';
}

function rp_finance_rollup_subquery(mysqli $conn, string $sourceAlias = 'ft'): string {
    $financeDateExpr = rp_first_existing_datetime_expr($conn, 'financetransactiontbl', $sourceAlias, ['finance_decision_at', 'payment_timestamp', 'created_at']);
    $dateSelect = $financeDateExpr !== 'NULL'
        ? "MAX({$financeDateExpr}) AS finance_event_at"
        : "NULL AS finance_event_at";

    return "
        (
            SELECT
                {$sourceAlias}.request_id,
                MAX(COALESCE({$sourceAlias}.transaction_amount, 0)) AS transaction_amount,
                MAX(COALESCE(NULLIF(TRIM({$sourceAlias}.payment_method), ''), 'Unspecified')) AS payment_method,
                MAX(COALESCE(NULLIF({$sourceAlias}.or_number, ''), '')) AS or_number,
                {$dateSelect}
            FROM financetransactiontbl {$sourceAlias}
            GROUP BY {$sourceAlias}.request_id
        )
    ";
}

function rp_join_latest_address_sql(string $residentIdExpr, string $alias): string {
    return "
        LEFT JOIN residentaddresstbl {$alias}
            ON {$alias}.address_id = (
                SELECT a2.address_id
                FROM residentaddresstbl a2
                WHERE a2.resident_id = {$residentIdExpr}
                ORDER BY a2.address_id DESC
                LIMIT 1
            )
    ";
}

function rp_document_request_resident_parts(mysqli $conn, string $requestAlias = 'd', string $prefix = 'drf'): array {
    $joins = [];
    $sectorCandidates = [];
    $areaCandidates = [];
    $nameCandidates = array_fill_keys(['firstname', 'middlename', 'lastname', 'suffix'], []);

    if (!rp_table_exists($conn, 'residentinformationtbl')) {
        return ['joins' => '', 'sector_expr' => 'NULL', 'area_expr' => 'NULL', 'name_exprs' => []];
    }

    if (rp_column_exists($conn, 'documentrequesttbl', 'resident_user_id')) {
        $infoAlias = $prefix . 'iu';
        $joins[] = "LEFT JOIN residentinformationtbl {$infoAlias} ON {$infoAlias}.user_id = {$requestAlias}.resident_user_id";
        $sectorCandidates[] = "NULLIF(TRIM({$infoAlias}.sector_membership), '')";
        foreach (array_keys($nameCandidates) as $column) {
            if (rp_column_exists($conn, 'residentinformationtbl', $column)) {
                $nameCandidates[$column][] = "NULLIF(TRIM({$infoAlias}.{$column}), '')";
            }
        }
        if (rp_table_exists($conn, 'residentaddresstbl')) {
            $addrAlias = $prefix . 'au';
            $joins[] = trim(rp_join_latest_address_sql("{$infoAlias}.resident_id", $addrAlias));
            $areaCandidates[] = "NULLIF(TRIM({$addrAlias}.area_number), '')";
        }
    }

    if (rp_column_exists($conn, 'documentrequesttbl', 'resident_id')) {
        $infoAlias = $prefix . 'ir';
        $joins[] = "LEFT JOIN residentinformationtbl {$infoAlias} ON {$infoAlias}.resident_id = {$requestAlias}.resident_id";
        $sectorCandidates[] = "NULLIF(TRIM({$infoAlias}.sector_membership), '')";
        foreach (array_keys($nameCandidates) as $column) {
            if (rp_column_exists($conn, 'residentinformationtbl', $column)) {
                $nameCandidates[$column][] = "NULLIF(TRIM({$infoAlias}.{$column}), '')";
            }
        }
        if (rp_table_exists($conn, 'residentaddresstbl')) {
            $addrAlias = $prefix . 'ar';
            $joins[] = trim(rp_join_latest_address_sql("{$requestAlias}.resident_id", $addrAlias));
            $areaCandidates[] = "NULLIF(TRIM({$addrAlias}.area_number), '')";
        }
    }

    return [
        'joins' => implode("\n        ", array_filter($joins)),
        'sector_expr' => $sectorCandidates ? 'COALESCE(' . implode(', ', $sectorCandidates) . ')' : 'NULL',
        'area_expr' => $areaCandidates ? 'COALESCE(' . implode(', ', $areaCandidates) . ')' : 'NULL',
        'name_exprs' => array_map(
            static fn(array $values): string => $values ? 'COALESCE(' . implode(', ', $values) . ", '')" : "''",
            $nameCandidates
        ),
    ];
}

function rp_user_resident_parts(mysqli $conn, string $userIdExpr, string $prefix = 'rf'): array {
    if (!rp_table_exists($conn, 'residentinformationtbl')) {
        return ['joins' => '', 'sector_expr' => 'NULL', 'area_expr' => 'NULL'];
    }

    $infoAlias = $prefix . 'i';
    $joins = ["LEFT JOIN residentinformationtbl {$infoAlias} ON {$infoAlias}.user_id = {$userIdExpr}"];
    $sectorExpr = "NULLIF(TRIM({$infoAlias}.sector_membership), '')";
    $areaExpr = 'NULL';

    if (rp_table_exists($conn, 'residentaddresstbl')) {
        $addrAlias = $prefix . 'a';
        $joins[] = trim(rp_join_latest_address_sql("{$infoAlias}.resident_id", $addrAlias));
        $areaExpr = "NULLIF(TRIM({$addrAlias}.area_number), '')";
    }

    return [
        'joins' => implode("\n        ", $joins),
        'sector_expr' => $sectorExpr,
        'area_expr' => $areaExpr,
    ];
}

function rp_fetch_document_financial_rows(mysqli $conn, string $dateFrom, string $dateTo): array {
    if (!rp_table_exists($conn, 'documentrequesttbl') || !rp_table_exists($conn, 'financetransactiontbl')) {
        return [];
    }

    $financeDateExpr = rp_first_existing_datetime_expr($conn, 'financetransactiontbl', 'ft', ['finance_decision_at', 'payment_timestamp', 'created_at']);
    if ($financeDateExpr === 'NULL') {
        return [];
    }

    $residentParts = rp_document_request_resident_parts($conn, 'd', 'fin');
    $residentJoin = $residentParts['joins'] !== '' ? "\n        {$residentParts['joins']}" : '';
    $areaExpr = $residentParts['area_expr'] !== 'NULL' ? $residentParts['area_expr'] : "'Unspecified'";
    $sectorExpr = $residentParts['sector_expr'] !== 'NULL' ? $residentParts['sector_expr'] : "''";
    $df = $conn->real_escape_string($dateFrom);
    $dt = $conn->real_escape_string($dateTo);

    $rows = rp_safe_query($conn, "
        SELECT
            'document' AS record_source,
            d.request_id AS source_id,
            COALESCE(NULLIF(TRIM(d.document_type), ''), 'Unspecified') AS document_type,
            COALESCE(NULLIF(TRIM(d.stage), ''), 'submitted') AS stage,
            COALESCE(d.resident_id, '') AS resident_id,
            COALESCE(d.resident_user_id, '') AS resident_user_id,
            COALESCE(d.fee_amount, NULL) AS fee_amount,
            COALESCE(d.request_details, '') AS request_details,
            COALESCE({$areaExpr}, 'Unspecified') AS area_number,
            COALESCE({$sectorExpr}, '') AS sector_membership,
            TRIM(CONCAT_WS(
                ' ',
                NULLIF(TRIM(COALESCE(ft.applicant_firstname, '')), ''),
                NULLIF(TRIM(COALESCE(ft.applicant_middleInitial, '')), ''),
                NULLIF(TRIM(COALESCE(ft.applicant_lastname, '')), '')
            )) AS resident_name,
            COALESCE(NULLIF(TRIM(d.certificate_number), ''), '—') AS certificate_number,
            COALESCE(ft.transaction_amount, 0) AS transaction_amount,
            COALESCE(NULLIF(TRIM(ft.payment_method), ''), 'unspecified') AS payment_method,
            COALESCE(NULLIF(TRIM(ft.or_number), ''), '') AS or_number,
            {$financeDateExpr} AS finance_event_at,
            '' AS department_handle
        FROM documentrequesttbl d
        INNER JOIN financetransactiontbl ft ON ft.request_id = d.request_id" . $residentJoin . "
        WHERE LOWER(COALESCE(d.stage, '')) NOT IN ('rejected', 'cancelled', 'interview_failed', 'inspection_failed', 'payment_rejected')
          AND COALESCE(ft.transaction_amount, 0) > 0
          AND DATE({$financeDateExpr}) BETWEEN '{$df}' AND '{$dt}'
        ORDER BY {$financeDateExpr} ASC, d.request_id ASC
    ");

    return array_values(array_filter($rows, static function (array $row) use ($conn): bool {
        $effectiveFee = rp_document_request_effective_fee($conn, $row);
        return !($effectiveFee !== null && $effectiveFee <= 0.0);
    }));
}

function rp_fetch_manual_financial_rows(mysqli $conn, string $dateFrom, string $dateTo): array {
    if (!rp_table_exists($conn, 'manualfinancetransactiontbl')) {
        return [];
    }

    $requiredColumns = [
        'transaction_id',
        'transaction_name',
        'transaction_description',
        'department_handle',
        'transaction_amount',
        'or_number_receipt',
    ];
    foreach ($requiredColumns as $column) {
        if (!rp_column_exists($conn, 'manualfinancetransactiontbl', $column)) {
            return [];
        }
    }

    $manualDateExpr = rp_first_existing_datetime_expr($conn, 'manualfinancetransactiontbl', 'mt', ['created_at', 'updated_at']);
    if ($manualDateExpr === 'NULL') {
        return [];
    }

    $df = $conn->real_escape_string($dateFrom);
    $dt = $conn->real_escape_string($dateTo);

    return rp_safe_query($conn, "
        SELECT
            'manual' AS record_source,
            mt.transaction_id AS source_id,
            COALESCE(NULLIF(TRIM(mt.transaction_description), ''), 'Manual Finance Transaction') AS document_type,
            'Unspecified' AS area_number,
            '' AS sector_membership,
            COALESCE(NULLIF(TRIM(mt.transaction_name), ''), '—') AS resident_name,
            'Manual' AS certificate_number,
            COALESCE(mt.transaction_amount, 0) AS transaction_amount,
            'unspecified' AS payment_method,
            COALESCE(NULLIF(TRIM(mt.or_number_receipt), ''), '') AS or_number,
            {$manualDateExpr} AS finance_event_at,
            COALESCE(NULLIF(TRIM(mt.department_handle), ''), 'Unspecified') AS department_handle
        FROM manualfinancetransactiontbl mt
        WHERE COALESCE(mt.transaction_amount, 0) > 0
          AND DATE({$manualDateExpr}) BETWEEN '{$df}' AND '{$dt}'
        ORDER BY {$manualDateExpr} ASC, mt.transaction_id ASC
    ");
}

function rp_fetch_financial_collection_rows(mysqli $conn, string $dateFrom, string $dateTo): array {
    $rows = array_merge(
        rp_fetch_document_financial_rows($conn, $dateFrom, $dateTo),
        rp_fetch_manual_financial_rows($conn, $dateFrom, $dateTo)
    );

    usort($rows, static function (array $left, array $right): int {
        $leftDate = (string)($left['finance_event_at'] ?? '');
        $rightDate = (string)($right['finance_event_at'] ?? '');
        if ($leftDate !== $rightDate) {
            return strcmp($leftDate, $rightDate);
        }
        return strcmp((string)($left['source_id'] ?? ''), (string)($right['source_id'] ?? ''));
    });

    return $rows;
}

function rp_financial_payment_method_key(string $method): string {
    $normalized = strtolower(trim($method));
    return $normalized !== '' ? $normalized : 'unspecified';
}

function rp_financial_department_value(array $row): string {
    $department = trim((string)($row['department_handle'] ?? ''));
    if ($department !== '') {
        return $department;
    }
    return rp_financial_department_label((string)($row['document_type'] ?? ''));
}

function rp_financial_matches_filters(array $row, array $typeFilters, array $areaFilters, array $sectorFilters): bool {
    $documentType = trim((string)($row['document_type'] ?? ''));
    if ($typeFilters !== [] && !in_array($documentType, $typeFilters, true)) {
        return false;
    }

    $areaValue = trim((string)($row['area_number'] ?? ''));
    $areaValue = $areaValue !== '' ? $areaValue : 'Unspecified';
    if ($areaFilters !== [] && !in_array($areaValue, $areaFilters, true)) {
        return false;
    }

    $sectorLabels = array_values(array_intersect(
        array_unique(array_map('rp_normalize_sector_label', rp_parse_csv_values((string)($row['sector_membership'] ?? '')))),
        array_keys(rp_official_sector_options())
    ));
    if ($sectorFilters !== [] && array_intersect($sectorLabels, $sectorFilters) === []) {
        return false;
    }

    return true;
}

function rp_parse_csv_values(string $value): array {
    return array_values(array_filter(array_map(
        static fn(string $item): string => trim($item),
        explode(',', $value)
    ), static fn(string $item): bool => $item !== ''));
}

function rp_official_area_options(): array {
    $areas = ['Area 01', 'Area 1A', 'Area 02', 'Area 03', 'Area 04', 'Area 05', 'Area 06'];
    return array_combine($areas, $areas) ?: [];
}

function rp_normalize_sector_label(string $value): string {
    $raw = trim($value);
    if ($raw === '') {
        return '';
    }

    $normalized = preg_replace('/[^a-z]/', '', strtolower($raw));
    $map = [
        'pwd' => 'PWD',
        'seniorcitizen' => 'Senior Citizen',
        'student' => 'Student',
        'indigenouspeople' => 'Indigenous People',
        'indigenousperson' => 'Indigenous People',
        'singleparent' => 'Single Parent',
        'soloparent' => 'Single Parent',
    ];

    return $map[$normalized] ?? $raw;
}

function rp_official_sector_options(): array {
    $sectors = ['PWD', 'Senior Citizen', 'Student', 'Indigenous People', 'Single Parent'];
    return array_combine($sectors, $sectors) ?: [];
}

function rp_complete_area_rollup_rows(array $rows, string $areaKey = 'area', array $defaultValues = []): array {
    $officialAreas = array_keys(rp_official_area_options());
    $officialAreaSet = array_fill_keys($officialAreas, true);
    $indexedRows = [];
    $extraRows = [];

    foreach ($rows as $row) {
        $areaLabel = trim((string)($row[$areaKey] ?? ''));
        $areaLabel = $areaLabel !== '' ? $areaLabel : 'Unspecified';
        $row[$areaKey] = $areaLabel;

        if (isset($officialAreaSet[$areaLabel])) {
            $indexedRows[$areaLabel] = $row;
            continue;
        }

        $extraRows[] = $row;
    }

    $completedRows = [];
    foreach ($officialAreas as $areaLabel) {
        if (isset($indexedRows[$areaLabel])) {
            $completedRows[] = $indexedRows[$areaLabel];
            continue;
        }

        $completedRows[] = array_merge([$areaKey => $areaLabel], $defaultValues);
    }

    return array_merge($completedRows, $extraRows);
}

function rp_breakdown_sector_header_label(string $sector): string {
    $map = [
        'PWD' => 'PWD',
        'Senior Citizen' => 'Senior',
        'Student' => 'Student',
        'Indigenous People' => 'Indigenous',
        'Single Parent' => 'Single Parent',
    ];

    return $map[$sector] ?? $sector;
}

function rp_options_from_rows(array $rows, string $valueKey, ?callable $labelFormatter = null): array {
    $options = [];
    foreach ($rows as $row) {
        $value = trim((string)($row[$valueKey] ?? ''));
        if ($value === '') {
            continue;
        }
        $options[$value] = $labelFormatter ? (string)$labelFormatter($value) : $value;
    }
    asort($options, SORT_NATURAL | SORT_FLAG_CASE);
    return $options;
}

function rp_sector_options_from_rows(array $rows, string $valueKey = 'sector_membership'): array {
    $options = [];
    foreach ($rows as $row) {
        foreach (rp_parse_csv_values((string)($row[$valueKey] ?? '')) as $value) {
            $value = rp_normalize_sector_label($value);
            if ($value === '') {
                continue;
            }
            $options[$value] = $value;
        }
    }
    asort($options, SORT_NATURAL | SORT_FLAG_CASE);
    return $options;
}

function rp_sql_quote(mysqli $conn, string $value): string {
    return "'" . $conn->real_escape_string($value) . "'";
}

function rp_sql_in_list(mysqli $conn, array $values): string {
    return implode(', ', array_map(
        static fn(string $value): string => rp_sql_quote($conn, $value),
        array_values(array_filter(array_map(static fn($value): string => trim((string)$value), $values), static fn(string $value): bool => $value !== ''))
    ));
}

function rp_csv_contains_expr(mysqli $conn, string $columnExpr, string $value): string {
    $normalizedValue = strtolower(str_replace(' ', '', rp_normalize_sector_label($value)));
    return "FIND_IN_SET(" . rp_sql_quote($conn, $normalizedValue) . ", REPLACE(REPLACE(LOWER(COALESCE({$columnExpr}, '')), ', ', ','), ' ', '')) > 0";
}

function rp_csv_contains_any_expr(mysqli $conn, string $columnExpr, array $values): string {
    $clauses = [];
    foreach ($values as $value) {
        $normalized = trim((string)$value);
        if ($normalized === '') {
            continue;
        }
        $clauses[] = rp_csv_contains_expr($conn, $columnExpr, $normalized);
    }
    if ($clauses === []) {
        return '1 = 1';
    }
    return '(' . implode(' OR ', $clauses) . ')';
}

function rp_sector_rollup_rows(array $rows, string $sectorKey = 'sector_membership', ?string $amountKey = null, string $labelKey = 'sector'): array {
    $rollup = [];
    foreach (array_keys(rp_official_sector_options()) as $sectorLabel) {
        $rollup[$sectorLabel] = [
            $labelKey => $sectorLabel,
            'total' => 0,
        ];
        if ($amountKey !== null) {
            $rollup[$sectorLabel]['amount'] = 0.0;
        }
    }

    foreach ($rows as $row) {
        $amount = $amountKey !== null ? (float)($row[$amountKey] ?? 0) : 0.0;
        $labels = array_values(array_intersect(
            array_unique(array_map('rp_normalize_sector_label', rp_parse_csv_values((string)($row[$sectorKey] ?? '')))),
            array_keys($rollup)
        ));
        foreach ($labels as $label) {
            $rollup[$label]['total']++;
            if ($amountKey !== null) {
                $rollup[$label]['amount'] += $amount;
            }
        }
    }

    return array_values(array_filter($rollup, static function (array $row) use ($amountKey): bool {
        if ((int)($row['total'] ?? 0) > 0) {
            return true;
        }
        return $amountKey !== null && (float)($row['amount'] ?? 0) > 0;
    }));
}

function rp_financial_department_label(string $documentType): string {
    $requestKey = rp_document_request_key($documentType);
    if (str_starts_with($requestKey, 'clr_')) {
        return 'Barangay Monitoring';
    }
    if ($requestKey !== '') {
        return 'Barangay Issuance';
    }

    $normalized = preg_replace('/[^a-z0-9]+/', '', strtolower(trim($documentType)));
    if ($normalized !== '' && (str_contains($normalized, 'clearance') || str_contains($normalized, 'permit'))) {
        return 'Barangay Monitoring';
    }

    return 'Barangay Issuance';
}

function rp_financial_department_rollup_rows(array $rows, string $documentTypeKey = 'document_type', string $countKey = 'count', string $amountKey = 'total'): array {
    $rollup = [
        'Barangay Issuance' => [
            'department' => 'Barangay Issuance',
            'total' => 0,
            'amount' => 0.0,
        ],
        'Barangay Monitoring' => [
            'department' => 'Barangay Monitoring',
            'total' => 0,
            'amount' => 0.0,
        ],
    ];

    foreach ($rows as $row) {
        $department = rp_financial_department_label((string)($row[$documentTypeKey] ?? ''));
        if (!isset($rollup[$department])) {
            continue;
        }

        $rollup[$department]['total'] += (int)($row[$countKey] ?? 0);
        $rollup[$department]['amount'] += (float)($row[$amountKey] ?? 0);
    }

    return array_values($rollup);
}

function rp_render_hidden_input(string $name, string $value): void {
    echo '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">' . "\n";
}

function rp_render_hidden_inputs(string $name, array $values): void {
    foreach ($values as $value) {
        $text = trim((string)$value);
        if ($text === '') {
            continue;
        }
        rp_render_hidden_input($name . '[]', $text);
    }
}

function rp_normalize_full_filter_selection(array $selectedValues, array $availableOptions, array $defaultValues = []): array {
    $selected = array_values(array_unique(array_map(static fn($value): string => trim((string)$value), $selectedValues)));
    $selected = array_values(array_filter($selected, static fn(string $value): bool => $value !== ''));
    $baseline = $defaultValues !== []
        ? array_values(array_map(static fn($value): string => trim((string)$value), $defaultValues))
        : array_values(array_map(static fn($value): string => trim((string)$value), array_keys($availableOptions)));
    $baseline = array_values(array_filter($baseline, static fn(string $value): bool => $value !== ''));

    sort($selected);
    sort($baseline);

    return $selected === $baseline ? [] : $selected;
}

function rp_filter_modal_checked_values(array $selectedValues, array $availableOptions, array $defaultValues = []): array {
    if ($selectedValues !== []) {
        return array_values(array_unique(array_map(static fn($value): string => trim((string)$value), $selectedValues)));
    }

    $fallbackValues = $defaultValues !== [] ? $defaultValues : array_keys($availableOptions);

    return array_values(array_map(static fn($value): string => trim((string)$value), $fallbackValues));
}

function rp_default_visible_report_columns(array $availableColumns): array {
    $disabledByDefault = ['percentage', 'revenue', 'breakdown_revenue'];
    $visibleColumns = array_values(array_filter(
        $availableColumns,
        static fn(string $column): bool => !in_array($column, $disabledByDefault, true)
    ));

    return $visibleColumns === [] ? array_values($availableColumns) : $visibleColumns;
}

function rp_selection_difference_count(array $defaultValues, array $currentValues): int {
    return count(array_diff($defaultValues, $currentValues)) + count(array_diff($currentValues, $defaultValues));
}

function rp_breakdown_area_column_key(string $area): string {
    return 'breakdown_area_' . strtolower(preg_replace('/[^a-z0-9]+/', '_', trim($area)));
}

function rp_breakdown_sector_column_key(string $sector): string {
    return 'breakdown_sector_' . strtolower(preg_replace('/[^a-z0-9]+/', '_', trim($sector)));
}

function rp_issuance_customize_columns(bool $includeBreakdownSectors = true): array {
    $columns = [
        'identifier' => 'Masterlist: Request ID',
        'resident' => 'Masterlist: Requester Name',
        'date' => 'Monthly Trend: Month',
        'type' => 'Request / Document Type',
        'area' => 'Tables: Area Number',
        'status' => 'Tables: Status',
        'channel' => 'Channel / Walk-in / Online',
        'count' => 'Totals / Counts',
        'percentage' => 'Percentages / Rates',
        'revenue' => 'Revenue / Amount',
        'breakdown_document_type' => 'Breakdown: Document Type',
    ];

    foreach (array_keys(rp_official_area_options()) as $areaLabel) {
        $columns[rp_breakdown_area_column_key($areaLabel)] = 'Breakdown: ' . $areaLabel;
    }
    if ($includeBreakdownSectors) {
        foreach (array_keys(rp_official_sector_options()) as $sectorLabel) {
            $columns[rp_breakdown_sector_column_key($sectorLabel)] = 'Breakdown: ' . $sectorLabel;
        }
    }
    $columns['breakdown_revenue'] = 'Breakdown: Revenue';
    $columns['breakdown_total'] = 'Breakdown: Total';

    return $columns;
}

function rp_issuance_customize_column_groups(bool $includeBreakdownSectors = true): array {
    $breakdownColumns = ['breakdown_document_type'];
    foreach (array_keys(rp_official_area_options()) as $areaLabel) {
        $breakdownColumns[] = rp_breakdown_area_column_key($areaLabel);
    }
    if ($includeBreakdownSectors) {
        foreach (array_keys(rp_official_sector_options()) as $sectorLabel) {
            $breakdownColumns[] = rp_breakdown_sector_column_key($sectorLabel);
        }
    }
    $breakdownColumns[] = 'breakdown_revenue';
    $breakdownColumns[] = 'breakdown_total';

    return [
        [
            'label' => 'Breakdown',
            'sections' => ['breakdown'],
            'columns' => $breakdownColumns,
        ],
        [
            'label' => 'Tables',
            'sections' => ['tables'],
            'columns' => ['area', 'type', 'status', 'channel', 'count', 'percentage', 'revenue'],
        ],
        [
            'label' => 'Request Type',
            'sections' => ['channel'],
            'columns' => ['type', 'channel', 'count'],
        ],
        [
            'label' => 'Revenue',
            'sections' => ['revenue'],
            'columns' => ['type', 'count', 'revenue'],
        ],
        [
            'label' => 'Monthly Trend',
            'sections' => ['trend'],
            'columns' => ['date', 'count'],
        ],
        [
            'label' => 'Requester Masterlist',
            'sections' => ['requesters'],
            'columns' => ['identifier', 'resident', 'date', 'type', 'status', 'area', 'channel', 'revenue'],
        ],
    ];
}

function rp_resolve_customize_column_groups(array $allColumns, array $groupDefinitions): array {
    if ($allColumns === []) {
        return [];
    }

    if ($groupDefinitions === []) {
        return [[
            'label' => 'Column Groups',
            'sections' => [],
            'columns' => $allColumns,
        ]];
    }

    $resolvedGroups = [];
    $usedColumns = [];

    foreach ($groupDefinitions as $groupDefinition) {
        $groupColumns = [];
        foreach ((array)($groupDefinition['columns'] ?? []) as $columnKey) {
            $columnKey = trim((string)$columnKey);
            if ($columnKey === '' || !array_key_exists($columnKey, $allColumns)) {
                continue;
            }
            $groupColumns[$columnKey] = $allColumns[$columnKey];
            $usedColumns[$columnKey] = true;
        }

        if ($groupColumns === []) {
            continue;
        }

        $resolvedGroups[] = [
            'label' => trim((string)($groupDefinition['label'] ?? 'Column Group')) ?: 'Column Group',
            'sections' => array_values(array_filter(array_map(
                static fn($section): string => trim((string)$section),
                (array)($groupDefinition['sections'] ?? [])
            ), static fn(string $section): bool => $section !== '')),
            'columns' => $groupColumns,
        ];
    }

    $remainingColumns = array_diff_key($allColumns, $usedColumns);
    if ($remainingColumns !== []) {
        $resolvedGroups[] = [
            'label' => 'Shared Columns',
            'sections' => [],
            'columns' => $remainingColumns,
        ];
    }

    return $resolvedGroups;
}

function rp_report_customize_config(string $module): array {
    $sharedColumns = [
        'date' => 'Date / Month',
        'type' => 'Request / Document Type',
        'status' => 'Status',
        'area' => 'Area Number',
        'sector' => 'Sector Membership',
        'count' => 'Counts / Totals',
        'percentage' => 'Percentages / Rates',
        'revenue' => 'Revenue / Amount',
        'channel' => 'Channel / Origin',
        'identifier' => 'Identifiers',
        'resident' => 'Resident / Person',
        'payment' => 'Payment Details',
        'result' => 'Completed / Escalated / Resolved',
    ];

    $configs = [
        'certificate_issuance' => [
            'sections' => [
                'summary' => 'Summary',
                'breakdown' => 'Breakdown',
                'charts' => 'Charts',
                'tables' => 'Tables',
                'channel' => 'Request Type',
                'revenue' => 'Revenue',
                'trend' => 'Monthly Trend',
                'requesters' => 'Requester Masterlist',
            ],
            'columns' => rp_issuance_customize_columns(false),
            'column_groups' => rp_issuance_customize_column_groups(false),
        ],
        'clearance_issuance' => [
            'sections' => [
                'summary' => 'Summary',
                'breakdown' => 'Breakdown',
                'charts' => 'Charts',
                'tables' => 'Tables',
                'channel' => 'Request Type',
                'revenue' => 'Revenue',
                'trend' => 'Monthly Trend',
                'requesters' => 'Requester Masterlist',
            ],
            'columns' => rp_issuance_customize_columns(false),
            'column_groups' => rp_issuance_customize_column_groups(false),
        ],
        'financial' => [
            'sections' => [
                'summary' => 'Overall Summary',
                'charts' => 'Revenue Stream Graphs',
                'type' => 'Revenue by Payment Type',
                'payment_method' => 'Payment Type Breakdown',
                'area' => 'Revenue by Area',
                'sector' => 'Revenue by Department',
                'daily' => 'Daily Collection Log',
                'or_log' => 'Official Receipt (OR) Log',
            ],
            'columns' => [
                'identifier' => $sharedColumns['identifier'],
                'date' => $sharedColumns['date'],
                'type' => $sharedColumns['type'],
                'area' => $sharedColumns['area'],
                'sector' => 'Department',
                'count' => $sharedColumns['count'],
                'percentage' => $sharedColumns['percentage'],
                'revenue' => $sharedColumns['revenue'],
                'payment' => 'Payment Type',
                'resident' => $sharedColumns['resident'],
                'channel' => 'GCash / Walk-in',
            ],
            'column_groups' => [
                [
                    'label' => 'Revenue by Payment Type',
                    'sections' => ['type'],
                    'columns' => ['payment', 'count'],
                ],
                [
                    'label' => 'Payment Type Breakdown',
                    'sections' => ['payment_method'],
                    'columns' => ['payment', 'count', 'percentage', 'revenue'],
                ],
                [
                    'label' => 'Revenue by Area',
                    'sections' => ['area'],
                    'columns' => ['area', 'count', 'percentage', 'revenue'],
                ],
                [
                    'label' => 'Revenue by Department',
                    'sections' => ['sector'],
                    'columns' => ['sector', 'count', 'percentage', 'revenue'],
                ],
                [
                    'label' => 'Daily Collection Log',
                    'sections' => ['daily'],
                    'columns' => ['date', 'count', 'channel'],
                ],
                [
                    'label' => 'Official Receipt Log',
                    'sections' => ['or_log'],
                    'columns' => ['identifier', 'resident', 'type', 'payment', 'date', 'revenue'],
                ],
            ],
        ],
        'residents' => [
            'sections' => [
                'summary' => 'Overall Summary',
                'breakdown' => 'Residents Breakdown',
                'household' => 'Household Data',
                'charts' => 'Graphs',
                'tables' => 'Tables (Supporting the Graphs)',
                'sector' => 'Sector Membership',
                'employment' => 'Employed and Unemployed',
                'gender' => 'Gender',
                'age' => 'Age Distribution',
                'monthly' => 'Monthly Registration Count',
            ],
            'columns' => [
                'date' => $sharedColumns['date'],
                'area' => $sharedColumns['area'],
                'sector' => $sharedColumns['sector'],
                'group' => 'Leading Group / Label',
                'household' => 'Household Metric',
                'count' => $sharedColumns['count'],
                'percentage' => $sharedColumns['percentage'],
                'type' => 'Dataset / Category',
            ],
            'column_groups' => [
                [
                    'label' => 'Residents Breakdown',
                    'sections' => ['breakdown'],
                    'columns' => ['area', 'count', 'percentage'],
                ],
                [
                    'label' => 'Household Data',
                    'sections' => ['household'],
                    'columns' => ['area', 'household', 'count', 'percentage'],
                ],
                [
                    'label' => 'Tables (Supporting the Graphs)',
                    'sections' => ['tables'],
                    'columns' => ['type', 'group', 'count', 'percentage'],
                ],
                [
                    'label' => 'Sector Membership',
                    'sections' => ['sector'],
                    'columns' => ['sector', 'count', 'percentage'],
                ],
                [
                    'label' => 'Employed and Unemployed',
                    'sections' => ['employment'],
                    'columns' => ['type', 'count', 'percentage'],
                ],
                [
                    'label' => 'Gender',
                    'sections' => ['gender'],
                    'columns' => ['type', 'count', 'percentage'],
                ],
                [
                    'label' => 'Age Distribution',
                    'sections' => ['age'],
                    'columns' => ['type', 'count', 'percentage'],
                ],
                [
                    'label' => 'Monthly Registration Count',
                    'sections' => ['monthly'],
                    'columns' => ['date', 'count'],
                ],
            ],
        ],
        'blotter' => [
            'sections' => [
                'summary' => 'Overall Summary',
                'charts' => 'Graphs',
                'type' => 'Complaint Type Breakdown',
                'status' => 'Status Breakdown',
                'area' => 'Cases by Area',
                'sector' => 'Cases by Sector Membership',
                'trend' => 'Monthly Trend',
            ],
            'columns' => [
                'date' => $sharedColumns['date'],
                'type' => 'Complaint Type',
                'status' => $sharedColumns['status'],
                'area' => $sharedColumns['area'],
                'sector' => $sharedColumns['sector'],
                'count' => $sharedColumns['count'],
                'percentage' => $sharedColumns['percentage'],
                'result' => 'Resolved / Resolution Rate',
            ],
            'column_groups' => [
                [
                    'label' => 'Complaint Type Breakdown',
                    'sections' => ['type'],
                    'columns' => ['type', 'count', 'result', 'percentage'],
                ],
                [
                    'label' => 'Status Breakdown',
                    'sections' => ['status'],
                    'columns' => ['status', 'count', 'percentage'],
                ],
                [
                    'label' => 'Cases by Area',
                    'sections' => ['area'],
                    'columns' => ['area', 'count', 'percentage'],
                ],
                [
                    'label' => 'Cases by Sector Membership',
                    'sections' => ['sector'],
                    'columns' => ['sector', 'count', 'percentage'],
                ],
                [
                    'label' => 'Monthly Trend',
                    'sections' => ['trend'],
                    'columns' => ['date', 'count'],
                ],
            ],
        ],
        'complaints' => [
            'sections' => [
                'summary' => 'Overall Summary',
                'charts' => 'Graphs',
                'type' => 'Complaint Type Breakdown',
                'origin' => 'Origin',
                'kind' => 'Subject Kind',
                'area' => 'Complaints by Area',
                'sector' => 'Complaints by Sector Membership',
                'trend' => 'Monthly Trend',
            ],
            'columns' => [
                'date' => $sharedColumns['date'],
                'type' => 'Complaint Type',
                'channel' => 'Origin',
                'status' => 'Subject Kind',
                'area' => $sharedColumns['area'],
                'sector' => $sharedColumns['sector'],
                'count' => $sharedColumns['count'],
                'percentage' => $sharedColumns['percentage'],
                'result' => 'Escalated / Escalation Rate',
            ],
            'column_groups' => [
                [
                    'label' => 'Complaint Type Breakdown',
                    'sections' => ['type'],
                    'columns' => ['type', 'count', 'result', 'percentage'],
                ],
                [
                    'label' => 'Origin',
                    'sections' => ['origin'],
                    'columns' => ['channel', 'count', 'percentage'],
                ],
                [
                    'label' => 'Subject Kind',
                    'sections' => ['kind'],
                    'columns' => ['status', 'count', 'percentage'],
                ],
                [
                    'label' => 'Complaints by Area',
                    'sections' => ['area'],
                    'columns' => ['area', 'count', 'percentage'],
                ],
                [
                    'label' => 'Complaints by Sector Membership',
                    'sections' => ['sector'],
                    'columns' => ['sector', 'count', 'percentage'],
                ],
                [
                    'label' => 'Monthly Trend',
                    'sections' => ['trend'],
                    'columns' => ['date', 'count', 'result', 'percentage'],
                ],
            ],
        ],
    ];

    return $configs[$module] ?? ['sections' => [], 'columns' => [], 'column_groups' => []];
}

// ── Module routing ────────────────────────────────────────────────────────────
$allowedModules = ['certificate_issuance', 'clearance_issuance', 'financial', 'residents', 'blotter', 'complaints'];
$module = strtolower(trim((string)($_GET['module'] ?? 'certificate_issuance')));
if ($module === 'document_requests') {
    $module = 'certificate_issuance';
}
if (!in_array($module, $allowedModules, true)) $module = 'certificate_issuance';

// ── Date range (shared) ───────────────────────────────────────────────────────
$today      = date('Y-m-d');
$yearStart  = date('Y-01-01');
$dateFrom   = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_from'] ?? '')) ? $_GET['date_from'] : $yearStart;
$dateTo     = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_to']   ?? '')) ? $_GET['date_to']   : $today;
if ($dateTo < $dateFrom) $dateTo = $dateFrom;

$baseUrl = appUrl('Admin-End/Reports/Reports.php');
if (!array_key_exists('module', $_GET) && (($_GET['format'] ?? '') !== 'print')) {
    require __DIR__ . '/ReportsLanding.php';
    exit;
}
if (
    array_key_exists('module', $_GET)
    && count($_GET) === 1
    && !array_key_exists('report', $_GET)
    && (($_GET['format'] ?? '') !== 'print')
) {
    require __DIR__ . '/ReportCategoryLanding.php';
    exit;
}
if ((string)($_GET['report'] ?? '') === 'signatory_settings' && (($_GET['format'] ?? '') !== 'print')) {
    require __DIR__ . '/ReportSignatorySettings.php';
    exit;
}
$rawFilterTypeParam = $_GET['filter_type'] ?? '';
$rawFilterAreaParam = $_GET['filter_area'] ?? '';
$rawFilterSectorParam = $_GET['filter_sector'] ?? '';
$rawFilterStatusParam = $_GET['filter_status'] ?? '';
$reportFilterType = is_array($rawFilterTypeParam) ? '' : trim((string)$rawFilterTypeParam);
$reportFilterArea = is_array($rawFilterAreaParam) ? '' : trim((string)$rawFilterAreaParam);
$reportFilterSector = is_array($rawFilterSectorParam) ? '' : rp_normalize_sector_label(trim((string)$rawFilterSectorParam));
$reportFilterTypes = rp_parse_query_list($rawFilterTypeParam);
$reportFilterAreas = rp_parse_query_list($rawFilterAreaParam);
$reportFilterSectors = rp_parse_query_list($rawFilterSectorParam, 'rp_normalize_sector_label');
$reportFilterStatuses = rp_parse_query_list($rawFilterStatusParam, static fn(string $value): string => strtolower(trim($value)));
$issuanceModuleConfig = rp_issuance_module_config($module);
$defaultReportStatusSelection = $module === 'certificate_issuance' ? ['completed'] : [];
$officialReportAreaOptions = rp_official_area_options();
$officialReportSectorOptions = rp_official_sector_options();
if ($reportFilterArea !== '' && !array_key_exists($reportFilterArea, $officialReportAreaOptions)) {
    $reportFilterArea = '';
}
if ($reportFilterSector !== '' && !array_key_exists($reportFilterSector, $officialReportSectorOptions)) {
    $reportFilterSector = '';
}
if ($reportFilterType !== '' && $reportFilterTypes === []) {
    $reportFilterTypes[] = $reportFilterType;
}
if ($reportFilterArea !== '' && $reportFilterAreas === []) {
    $reportFilterAreas[] = $reportFilterArea;
}
if ($reportFilterSector !== '' && $reportFilterSectors === []) {
    $reportFilterSectors[] = $reportFilterSector;
}
if ($issuanceModuleConfig !== null) {
    $reportFilterSector = '';
    $reportFilterSectors = [];
}
$reportFilterOptions = [
    'type' => [],
    'area' => $officialReportAreaOptions,
    'sector' => $officialReportSectorOptions,
];
$reportFilterStatusOptions = $issuanceModuleConfig !== null ? rp_request_status_options() : [];
$reportFilterTypes = array_values(array_unique($reportFilterTypes));
$reportFilterAreas = array_values(array_intersect($reportFilterAreas, array_keys($officialReportAreaOptions)));
$reportFilterSectors = array_values(array_intersect($reportFilterSectors, array_keys($officialReportSectorOptions)));
if ($issuanceModuleConfig !== null) {
    $reportFilterTypes = array_values(array_intersect($reportFilterTypes, array_keys($issuanceModuleConfig['request_types'])));
    $reportFilterStatuses = array_values(array_intersect($reportFilterStatuses, array_keys($reportFilterStatusOptions)));
    if (!array_key_exists('filter_status', $_GET) && $reportFilterStatuses === []) {
        $reportFilterStatuses = $defaultReportStatusSelection;
    }
}
$reportFilterLabels = [
    'type' => in_array($module, ['blotter', 'complaints'], true) ? 'Type of Complaint' : ($issuanceModuleConfig !== null ? 'Request Type' : 'Type of Request'),
    'area' => 'Area Number',
    'sector' => 'Sector Membership',
    'status' => 'Statuses',
];

// ── Prepared-by user ──────────────────────────────────────────────────────────
$preparedByName = 'System User';
if (!empty($_SESSION['user_id']) && isset($conn) && $conn instanceof mysqli) {
    $pStmt = $conn->prepare("SELECT firstname, lastname FROM officialinformationtbl WHERE user_id=? LIMIT 1");
    if ($pStmt) {
        $pStmt->bind_param('s', $_SESSION['user_id']);
        $pStmt->execute();
        $pRow = $pStmt->get_result()->fetch_assoc();
        if ($pRow) {
            $pRow = pii_decrypt_official_row($pRow) ?? $pRow;
            $preparedByName = trim($pRow['firstname'] . ' ' . $pRow['lastname']);
        }
        $pStmt->close();
    }
}
$currentBarangaySignatories = dms_current_barangay_signatories($conn);
$defaultReportSignatoryName = trim((string)($currentBarangaySignatories['punong']['name'] ?? ''));
$defaultReportSignatoryRole = trim((string)($currentBarangaySignatories['punong']['title'] ?? ''));
$reportSignatorySettingsReady = $conn->query("CREATE TABLE IF NOT EXISTS report_signatory_settings (
    report_module VARCHAR(64) NOT NULL PRIMARY KEY,
    signatory_one_name VARCHAR(180) NULL,
    signatory_one_position VARCHAR(180) NULL,
    signatory_two_name VARCHAR(180) NULL,
    signatory_two_position VARCHAR(180) NULL,
    updated_by VARCHAR(64) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$requiredSignatoryColumns = [
    'signatory_one_name' => 'VARCHAR(180) NULL',
    'signatory_one_position' => 'VARCHAR(180) NULL',
    'signatory_two_name' => 'VARCHAR(180) NULL',
    'signatory_two_position' => 'VARCHAR(180) NULL',
];
foreach ($requiredSignatoryColumns as $column => $definition) {
    if (!rp_column_exists($conn, 'report_signatory_settings', $column)) {
        $conn->query("ALTER TABLE report_signatory_settings ADD COLUMN {$column} {$definition}");
    }
}

$savedSignatoryRow = [];
if ($reportSignatorySettingsReady) {
    $loadSignatory = $conn->prepare('SELECT signatory_one_name, signatory_one_position, signatory_two_name, signatory_two_position FROM report_signatory_settings WHERE report_module=? LIMIT 1');
    if ($loadSignatory) {
        $loadSignatory->bind_param('s', $module);
        $loadSignatory->execute();
        $savedSignatoryRow = $loadSignatory->get_result()->fetch_assoc() ?: [];
        $loadSignatory->close();
    }
}

$preparedByName = trim((string)($savedSignatoryRow['signatory_one_name'] ?? '')) ?: $preparedByName;
$preparedByRole = trim((string)($savedSignatoryRow['signatory_one_position'] ?? '')) ?: 'Prepared by';
$reportNotedByName = trim((string)($savedSignatoryRow['signatory_two_name'] ?? '')) ?: $defaultReportSignatoryName;
$reportNotedByRole = trim((string)($savedSignatoryRow['signatory_two_position'] ?? '')) ?: $defaultReportSignatoryRole;
if ($reportNotedByName === '') {
    $reportNotedByName = 'HON. GLENN S. EVANGELISTA';
}
if ($reportNotedByRole === '') {
    $reportNotedByRole = 'Punong Barangay';
}
$barangaySealUrl = appUrl('Images/San_Jose_LOGO.jpg');
$municipalSealUrl = appUrl('Images/Montalban_Logo.png');

// ═══════════════════════════════════════════════════════════════════════════════
// MODULE: ISSUANCE REPORTS
// ═══════════════════════════════════════════════════════════════════════════════
$issuanceReport = [
    'rows' => [],
    'summary' => [
        'total' => 0,
        'completed' => 0,
        'pending' => 0,
        'rejected' => 0,
        'revenue' => 0.0,
        'walkin' => 0,
        'online' => 0,
    ],
    'breakdown' => [],
    'revenue' => [],
    'channels' => [],
    'trend' => [],
];
if ($issuanceModuleConfig !== null && rp_table_exists($conn, 'documentrequesttbl')) {
    $reportFilterOptions['type'] = $issuanceModuleConfig['request_types'];
    $reportFilterStatusOptions = rp_request_status_options();

    $df = $conn->real_escape_string($dateFrom);
    $dt = $conn->real_escape_string($dateTo);
    $requestDateExpr = rp_first_existing_datetime_expr($conn, 'documentrequesttbl', 'd', ['submitted_at', 'request_timestamp', 'created_at']);
    $hasFinanceTable = rp_table_exists($conn, 'financetransactiontbl');
    $residentParts = rp_document_request_resident_parts($conn, 'd', 'dr');
    $financeRollup = $hasFinanceTable ? rp_finance_rollup_subquery($conn, 'ft') : '';
    $financeJoin = $hasFinanceTable ? "LEFT JOIN {$financeRollup} f ON f.request_id = d.request_id" : '';
    $residentJoin = $residentParts['joins'] !== '' ? $residentParts['joins'] : '';
    $areaExpr = $residentParts['area_expr'] !== 'NULL' ? $residentParts['area_expr'] : "''";
    $sectorExpr = $residentParts['sector_expr'] !== 'NULL' ? $residentParts['sector_expr'] : "''";
    $residentNameExprs = (array)($residentParts['name_exprs'] ?? []);
    $residentFirstNameExpr = $residentNameExprs['firstname'] ?? "''";
    $residentMiddleNameExpr = $residentNameExprs['middlename'] ?? "''";
    $residentLastNameExpr = $residentNameExprs['lastname'] ?? "''";
    $residentSuffixExpr = $residentNameExprs['suffix'] ?? "''";
    $amountExpr = $hasFinanceTable && rp_column_exists($conn, 'financetransactiontbl', 'transaction_amount')
        ? "COALESCE(f.transaction_amount, 0)"
        : "0";
    $residentIdExpr = rp_column_exists($conn, 'documentrequesttbl', 'resident_id') ? 'd.resident_id' : "''";
    $residentUserIdExpr = rp_column_exists($conn, 'documentrequesttbl', 'resident_user_id')
        ? 'd.resident_user_id'
        : (rp_column_exists($conn, 'documentrequesttbl', 'user_id') ? 'd.user_id' : "''");
    $requestFeeExpr = rp_column_exists($conn, 'documentrequesttbl', 'fee_amount') ? 'd.fee_amount' : 'NULL';
    $hasRequestData = $requestDateExpr !== 'NULL';
    $requestDateFilter = $hasRequestData
        ? "DATE({$requestDateExpr}) BETWEEN '{$df}' AND '{$dt}'"
        : '1 = 0';

    $rows = rp_safe_query($conn, "
        SELECT
          d.request_id,
          {$residentIdExpr} AS resident_id,
          {$residentUserIdExpr} AS resident_user_id,
          {$requestDateExpr} AS request_date,
          COALESCE(NULLIF(TRIM(d.document_type), ''), '') AS document_type,
          COALESCE(NULLIF(TRIM(d.stage), ''), 'submitted') AS stage,
          COALESCE(d.request_details, '') AS request_details,
          {$residentFirstNameExpr} AS resident_firstname,
          {$residentMiddleNameExpr} AS resident_middlename,
          {$residentLastNameExpr} AS resident_lastname,
          {$residentSuffixExpr} AS resident_suffix,
          {$areaExpr} AS area_number,
          {$sectorExpr} AS sector_membership,
          {$requestFeeExpr} AS fee_amount,
          {$amountExpr} AS revenue_amount
        FROM documentrequesttbl d
        {$financeJoin}
        " . ($residentJoin !== '' ? "\n        {$residentJoin}" : '') . "
        WHERE {$requestDateFilter}
        ORDER BY {$requestDateExpr} DESC, d.request_id DESC
    ");

    $officialAreas = array_keys($officialReportAreaOptions);
    $officialSectors = array_keys($officialReportSectorOptions);
    $requestTypesForOutput = $reportFilterTypes !== [] ? $reportFilterTypes : array_keys($issuanceModuleConfig['request_types']);
    $breakdown = [];
    $channels = [];
    $revenueRows = [];
    foreach ($requestTypesForOutput as $typeKey) {
        $label = $issuanceModuleConfig['request_types'][$typeKey] ?? $typeKey;
        $breakdown[$typeKey] = [
            'request_type_key' => $typeKey,
            'request_type_label' => $label,
            'areas' => array_fill_keys($officialAreas, 0),
            'sectors' => array_fill_keys($officialSectors, 0),
            'revenue' => 0.0,
            'total' => 0,
        ];
        $channels[$typeKey] = [
            'request_type_key' => $typeKey,
            'request_type_label' => $label,
            'walkin' => 0,
            'online' => 0,
            'total' => 0,
        ];
        $revenueRows[$typeKey] = [
            'request_type_key' => $typeKey,
            'request_type_label' => $label,
            'completed' => 0,
            'pending' => 0,
            'rejected' => 0,
            'total' => 0,
            'paid' => 0,
            'free_exempt' => 0,
            'revenue' => 0.0,
        ];
    }

    foreach ($rows as $row) {
        $payload = rp_decode_json_assoc((string)($row['request_details'] ?? ''));
        $residentName = trim(implode(' ', array_filter([
            trim(pii_decrypt_string((string)($row['resident_firstname'] ?? ''))),
            trim(pii_decrypt_string((string)($row['resident_middlename'] ?? ''))),
            trim(pii_decrypt_string((string)($row['resident_lastname'] ?? ''))),
            trim(pii_decrypt_string((string)($row['resident_suffix'] ?? ''))),
        ], static fn(string $part): bool => $part !== '')));
        if ($residentName === '') {
            $residentName = trim((string)($payload['resident_name'] ?? $payload['full_name'] ?? $payload['applicant_name'] ?? ''));
        }
        $rawDocumentType = trim((string)($row['document_type'] ?? ''));
        if ($rawDocumentType === '') {
            $rawDocumentType = trim((string)($payload['document_type'] ?? ''));
        }
        $requestTypeKey = rp_document_request_key($rawDocumentType);
        if ($requestTypeKey === '' || !isset($issuanceModuleConfig['request_types'][$requestTypeKey])) {
            continue;
        }

        $effectiveStage = rp_document_request_effective_stage($conn, $row);
        $statusKey = rp_request_status_key($effectiveStage);
        $areaNumber = trim((string)($row['area_number'] ?? ''));
        $areaNumber = isset($officialReportAreaOptions[$areaNumber]) ? $areaNumber : '';
        $sectorLabels = array_values(array_intersect(
            array_unique(array_map('rp_normalize_sector_label', rp_parse_csv_values((string)($row['sector_membership'] ?? '')))),
            $officialSectors
        ));
        $payloadSectorKeys = dr_parse_sector_membership_csv((string)($payload['sector_membership'] ?? ''));
        $hasPayloadExemption = in_array('pwd', $payloadSectorKeys, true) || in_array('seniorcitizen', $payloadSectorKeys, true);
        $requestSource = rp_request_channel_label($payload);
        $revenueAmount = (float)($row['revenue_amount'] ?? 0);
        $effectiveFee = rp_document_request_effective_fee($conn, $row);
        $isFreeOrExempt = $hasPayloadExemption || ($effectiveFee !== null && $effectiveFee <= 0.0);

        if ($reportFilterTypes !== [] && !in_array($requestTypeKey, $reportFilterTypes, true)) {
            continue;
        }
        if ($reportFilterAreas !== [] && !in_array($areaNumber, $reportFilterAreas, true)) {
            continue;
        }
        if ($reportFilterSectors !== [] && array_intersect($sectorLabels, $reportFilterSectors) === []) {
            continue;
        }
        if ($reportFilterStatuses !== [] && !in_array($statusKey, $reportFilterStatuses, true)) {
            continue;
        }

        $requestDateRaw = trim((string)($row['request_date'] ?? ''));
        $requestDateLabel = $requestDateRaw !== '' ? rp_date_label(substr($requestDateRaw, 0, 10)) : 'N/A';
        $issuanceRow = [
            'request_id' => trim((string)($row['request_id'] ?? '')),
            'request_date_raw' => $requestDateRaw,
            'request_date_label' => $requestDateLabel,
            'request_type_key' => $requestTypeKey,
            'request_type_label' => $issuanceModuleConfig['request_types'][$requestTypeKey],
            'resident_name' => $residentName !== '' ? $residentName : 'Unavailable',
            'status_key' => $statusKey,
            'status_label' => $reportFilterStatusOptions[$statusKey] ?? rp_stage_label($effectiveStage),
            'area_number' => $areaNumber,
            'sector_membership' => $sectorLabels,
            'sector_membership_label' => $sectorLabels !== [] ? implode(', ', $sectorLabels) : 'None',
            'request_source' => $requestSource,
            'revenue' => $revenueAmount,
        ];
        $issuanceReport['rows'][] = $issuanceRow;

        $issuanceReport['summary']['total']++;
        $issuanceReport['summary'][$statusKey]++;
        $issuanceReport['summary']['revenue'] += $revenueAmount;
        if ($requestSource === 'Walk-in') {
            $issuanceReport['summary']['walkin']++;
        } else {
            $issuanceReport['summary']['online']++;
        }

        if (isset($breakdown[$requestTypeKey])) {
            $breakdown[$requestTypeKey]['total']++;
            if ($areaNumber !== '' && isset($breakdown[$requestTypeKey]['areas'][$areaNumber])) {
                $breakdown[$requestTypeKey]['areas'][$areaNumber]++;
            }
            foreach ($sectorLabels as $sectorLabel) {
                if (isset($breakdown[$requestTypeKey]['sectors'][$sectorLabel])) {
                    $breakdown[$requestTypeKey]['sectors'][$sectorLabel]++;
                }
            }
            $breakdown[$requestTypeKey]['revenue'] += $revenueAmount;
        }

        if (isset($revenueRows[$requestTypeKey])) {
            $revenueRows[$requestTypeKey][$statusKey]++;
            $revenueRows[$requestTypeKey]['total']++;
            if ($revenueAmount > 0.0) {
                $revenueRows[$requestTypeKey]['paid']++;
            }
            if ($isFreeOrExempt) {
                $revenueRows[$requestTypeKey]['free_exempt']++;
            }
            $revenueRows[$requestTypeKey]['revenue'] += $revenueAmount;
        }

        if (isset($channels[$requestTypeKey])) {
            if ($requestSource === 'Walk-in') {
                $channels[$requestTypeKey]['walkin']++;
            } else {
                $channels[$requestTypeKey]['online']++;
            }
            $channels[$requestTypeKey]['total']++;
        }

        if ($requestDateRaw !== '') {
            $monthKey = date('Y-m', strtotime(substr($requestDateRaw, 0, 10)));
            if (!isset($issuanceReport['trend'][$monthKey])) {
                $issuanceReport['trend'][$monthKey] = [
                    'month' => $monthKey,
                    'total' => 0,
                    'completed' => 0,
                    'pending' => 0,
                    'rejected' => 0,
                    'revenue' => 0.0,
                ];
            }
            $issuanceReport['trend'][$monthKey]['total']++;
            $issuanceReport['trend'][$monthKey][$statusKey]++;
            $issuanceReport['trend'][$monthKey]['revenue'] += $revenueAmount;
        }
    }

    usort($issuanceReport['rows'], static function (array $a, array $b): int {
        return strcmp((string)($b['request_date_raw'] ?? ''), (string)($a['request_date_raw'] ?? ''));
    });
    ksort($issuanceReport['trend']);
    $issuanceReport['breakdown'] = array_values($breakdown);
    $issuanceReport['channels'] = array_values($channels);
    $issuanceReport['revenue'] = array_values($revenueRows);
    $issuanceReport['trend'] = array_values($issuanceReport['trend']);
}

// ═══════════════════════════════════════════════════════════════════════════════
// MODULE: FINANCIAL
// ═══════════════════════════════════════════════════════════════════════════════
$fin = [];
if ($module === 'financial') {
    $financialSourceRows = rp_fetch_financial_collection_rows($conn, $dateFrom, $dateTo);
    $reportFilterOptions['type'] = rp_options_from_rows($financialSourceRows, 'document_type', 'rp_document_type_label');

    $filteredFinancialRows = array_values(array_filter(
        $financialSourceRows,
        static function (array $row) use ($reportFilterTypes, $reportFilterAreas, $reportFilterSectors): bool {
            return rp_financial_matches_filters($row, $reportFilterTypes, $reportFilterAreas, $reportFilterSectors);
        }
    ));

    $kpi = [
        'total_issued' => 0,
        'total_collections' => 0.0,
        'gcash_total' => 0.0,
        'walkin_total' => 0.0,
        'unspecified_total' => 0.0,
        'or_count' => 0,
    ];
    $dailyLog = [];
    $typeRollup = [];
    $methodRollup = [];
    $areaRollup = [];
    $departmentRollup = [];
    $orLog = [];

    foreach ($filteredFinancialRows as $row) {
        $amount = (float)($row['transaction_amount'] ?? 0);
        $methodKey = rp_financial_payment_method_key((string)($row['payment_method'] ?? ''));
        $orNumber = trim((string)($row['or_number'] ?? ''));
        $documentType = trim((string)($row['document_type'] ?? ''));
        $documentType = $documentType !== '' ? $documentType : 'Unspecified';
        $areaLabel = trim((string)($row['area_number'] ?? ''));
        $areaLabel = $areaLabel !== '' ? $areaLabel : 'Unspecified';
        $departmentLabel = rp_financial_department_value($row);
        $departmentLabel = $departmentLabel !== '' ? $departmentLabel : 'Unspecified';
        $eventAt = trim((string)($row['finance_event_at'] ?? ''));
        $collectionDate = $eventAt !== '' ? substr($eventAt, 0, 10) : '';

        $kpi['total_issued']++;
        $kpi['total_collections'] += $amount;
        if ($methodKey === 'gcash') {
            $kpi['gcash_total'] += $amount;
        } elseif (in_array($methodKey, ['barangay', 'walk_in', 'walkin', 'cash'], true)) {
            $kpi['walkin_total'] += $amount;
        } else {
            $kpi['unspecified_total'] += $amount;
        }
        if ($orNumber !== '') {
            $kpi['or_count']++;
        }

        if ($collectionDate !== '') {
            if (!isset($dailyLog[$collectionDate])) {
                $dailyLog[$collectionDate] = [
                    'collection_date' => $collectionDate,
                    'count' => 0,
                    'total' => 0.0,
                    'gcash' => 0.0,
                    'walkin' => 0.0,
                    'unspecified' => 0.0,
                ];
            }
            $dailyLog[$collectionDate]['count']++;
            $dailyLog[$collectionDate]['total'] += $amount;
            if ($methodKey === 'gcash') {
                $dailyLog[$collectionDate]['gcash'] += $amount;
            } elseif (in_array($methodKey, ['barangay', 'walk_in', 'walkin', 'cash'], true)) {
                $dailyLog[$collectionDate]['walkin'] += $amount;
            } else {
                $dailyLog[$collectionDate]['unspecified'] += $amount;
            }
        }

        if (!isset($typeRollup[$documentType])) {
            $typeRollup[$documentType] = [
                'document_type' => $documentType,
                'count' => 0,
                'total' => 0.0,
            ];
        }
        $typeRollup[$documentType]['count']++;
        $typeRollup[$documentType]['total'] += $amount;

        if (!isset($methodRollup[$methodKey])) {
            $methodRollup[$methodKey] = [
                'method' => $methodKey,
                'total' => 0,
                'amount' => 0.0,
            ];
        }
        $methodRollup[$methodKey]['total']++;
        $methodRollup[$methodKey]['amount'] += $amount;

        if (!isset($areaRollup[$areaLabel])) {
            $areaRollup[$areaLabel] = [
                'area' => $areaLabel,
                'total' => 0,
                'amount' => 0.0,
            ];
        }
        $areaRollup[$areaLabel]['total']++;
        $areaRollup[$areaLabel]['amount'] += $amount;

        if (!isset($departmentRollup[$departmentLabel])) {
            $departmentRollup[$departmentLabel] = [
                'department' => $departmentLabel,
                'total' => 0,
                'amount' => 0.0,
            ];
        }
        $departmentRollup[$departmentLabel]['total']++;
        $departmentRollup[$departmentLabel]['amount'] += $amount;

        if ($orNumber !== '') {
            $orLog[] = [
                'or_number' => $orNumber,
                'certificate_number' => trim((string)($row['certificate_number'] ?? '')) ?: '—',
                'resident_name' => trim((string)($row['resident_name'] ?? '')),
                'document_type' => $documentType,
                'fee_amount' => $amount,
                'payment_method' => rp_payment_method_label($methodKey),
                'finance_decision_at' => $eventAt,
            ];
        }
    }

    ksort($dailyLog);
    $fin['kpi'] = $kpi;
    $fin['daily_log'] = array_values($dailyLog);

    $fin['by_type'] = array_values($typeRollup);
    usort($fin['by_type'], static function (array $left, array $right): int {
        $amountCompare = (float)($right['total'] ?? 0) <=> (float)($left['total'] ?? 0);
        if ($amountCompare !== 0) {
            return $amountCompare;
        }
        return strcmp((string)($left['document_type'] ?? ''), (string)($right['document_type'] ?? ''));
    });

    $fin['by_method'] = array_values($methodRollup);
    usort($fin['by_method'], static function (array $left, array $right): int {
        $amountCompare = (float)($right['amount'] ?? 0) <=> (float)($left['amount'] ?? 0);
        if ($amountCompare !== 0) {
            return $amountCompare;
        }
        return (int)($right['total'] ?? 0) <=> (int)($left['total'] ?? 0);
    });

    $finAreaRows = array_values($areaRollup);
    usort($finAreaRows, static function (array $left, array $right): int {
        $countCompare = (int)($right['total'] ?? 0) <=> (int)($left['total'] ?? 0);
        if ($countCompare !== 0) {
            return $countCompare;
        }
        return (float)($right['amount'] ?? 0) <=> (float)($left['amount'] ?? 0);
    });
    $fin['by_area'] = $finAreaRows !== []
        ? rp_complete_area_rollup_rows($finAreaRows, 'area', ['total' => 0, 'amount' => 0.0])
        : [];

    $fin['by_department'] = array_values($departmentRollup);
    usort($fin['by_department'], static function (array $left, array $right): int {
        $amountCompare = (float)($right['amount'] ?? 0) <=> (float)($left['amount'] ?? 0);
        if ($amountCompare !== 0) {
            return $amountCompare;
        }
        return strcmp((string)($left['department'] ?? ''), (string)($right['department'] ?? ''));
    });

    usort($orLog, static function (array $left, array $right): int {
        $dateCompare = strcmp((string)($left['finance_decision_at'] ?? ''), (string)($right['finance_decision_at'] ?? ''));
        if ($dateCompare !== 0) {
            return $dateCompare;
        }
        return strcmp((string)($left['or_number'] ?? ''), (string)($right['or_number'] ?? ''));
    });
    $fin['or_log'] = array_slice($orLog, 0, 500);
    $fin['or_log_total'] = array_sum(array_map(static fn(array $row): float => (float)($row['fee_amount'] ?? 0), $fin['or_log']));
}

// ═══════════════════════════════════════════════════════════════════════════════
// MODULE: RESIDENTS
// ═══════════════════════════════════════════════════════════════════════════════
$res = [];
if ($module === 'residents' && rp_table_exists($conn, 'residentinformationtbl')) {
    $residentAddressJoin = rp_table_exists($conn, 'residentaddresstbl')
        ? trim(rp_join_latest_address_sql('ri.resident_id', 'ra'))
        : '';
    $residentAreaExpr = $residentAddressJoin !== '' ? "NULLIF(TRIM(ra.area_number), '')" : 'NULL';
    $residentSectorExpr = "NULLIF(TRIM(ri.sector_membership), '')";
    $residentVerifiedWhere = "s.status_name = 'VerifiedResident'";
    $residentFilterClauses = [];
    if ($reportFilterAreas !== [] && $residentAreaExpr !== 'NULL') {
        $residentFilterClauses[] = "{$residentAreaExpr} IN (" . rp_sql_in_list($conn, $reportFilterAreas) . ")";
    }
    if ($reportFilterSectors !== []) {
        $residentFilterClauses[] = rp_csv_contains_any_expr($conn, $residentSectorExpr, $reportFilterSectors);
    }
    $residentFilterSql = $residentFilterClauses ? ' AND ' . implode(' AND ', $residentFilterClauses) : '';
    if ($residentAreaExpr !== 'NULL') {
        $reportFilterOptions['area'] = rp_options_from_rows(rp_safe_query($conn, "
            SELECT {$residentAreaExpr} AS value
            FROM residentinformationtbl ri
            JOIN statuslookuptbl s ON s.status_id = ri.status_id_resident
            {$residentAddressJoin}
            WHERE {$residentVerifiedWhere}
              AND {$residentAreaExpr} IS NOT NULL
              AND {$residentAreaExpr} <> ''
            GROUP BY {$residentAreaExpr}
            ORDER BY {$residentAreaExpr} ASC
        "), 'value');
    }
    $reportFilterOptions['sector'] = rp_sector_options_from_rows(rp_safe_query($conn, "
        SELECT {$residentSectorExpr} AS sector_membership
        FROM residentinformationtbl ri
        JOIN statuslookuptbl s ON s.status_id = ri.status_id_resident
        WHERE {$residentVerifiedWhere}
          AND {$residentSectorExpr} IS NOT NULL
          AND {$residentSectorExpr} <> ''
    "));

    $res['kpi'] = rp_safe_query($conn, "
        SELECT
          COUNT(*) AS total,
          COUNT(*) AS verified,
          0 AS pending,
          0 AS not_verified,
          0 AS archived
        FROM residentinformationtbl ri
        JOIN statuslookuptbl s ON s.status_id = ri.status_id_resident
        " . ($residentAddressJoin !== '' ? "\n        {$residentAddressJoin}" : '') . "
        WHERE {$residentVerifiedWhere} {$residentFilterSql}
    ");
    $res['kpi'] = $res['kpi'][0] ?? [];

    $res['by_gender'] = rp_safe_query($conn, "
        SELECT COALESCE(LOWER(ri.sex),'unspecified') AS gender, COUNT(*) AS total
        FROM residentinformationtbl ri
        JOIN statuslookuptbl s ON s.status_id = ri.status_id_resident
        WHERE {$residentVerifiedWhere}
        {$residentFilterSql}
        GROUP BY gender ORDER BY total DESC
    ");

    $allBirthdays = rp_safe_query($conn, "
        SELECT ri.birthdate AS birthdate FROM residentinformationtbl ri
        JOIN statuslookuptbl s ON s.status_id = ri.status_id_resident
        " . ($residentAddressJoin !== '' ? "\n        {$residentAddressJoin}" : '') . "
        WHERE {$residentVerifiedWhere} AND ri.birthdate IS NOT NULL AND ri.birthdate != ''
        {$residentFilterSql}
    ");
    $ageBuckets = ['0-17' => 0, '18-30' => 0, '31-59' => 0, '60+' => 0];
    $now = new DateTimeImmutable();
    foreach ($allBirthdays as $b) {
        try {
            $age = (int)$now->diff(new DateTimeImmutable($b['birthdate']))->y;
            if ($age <= 17) $ageBuckets['0-17']++;
            elseif ($age <= 30) $ageBuckets['18-30']++;
            elseif ($age <= 59) $ageBuckets['31-59']++;
            else $ageBuckets['60+']++;
        } catch (Exception $e) {}
    }
    $res['age_buckets'] = $ageBuckets;

    if ($residentAddressJoin !== '') {
        $res['by_area'] = rp_safe_query($conn, "
            SELECT COALESCE({$residentAreaExpr},'Unspecified') AS area, COUNT(DISTINCT ri.resident_id) AS total
            FROM residentinformationtbl ri
            JOIN statuslookuptbl s ON s.status_id = ri.status_id_resident
            {$residentAddressJoin}
            WHERE {$residentVerifiedWhere}
            {$residentFilterSql}
            GROUP BY area ORDER BY total DESC
        ");
    }
    $res['by_area_complete'] = rp_complete_area_rollup_rows($res['by_area'] ?? [], 'area', ['total' => 0]);

    $sectorRows = rp_safe_query($conn, "
        SELECT {$residentSectorExpr} AS sector_membership FROM residentinformationtbl ri
        JOIN statuslookuptbl s ON s.status_id = ri.status_id_resident
        " . ($residentAddressJoin !== '' ? "\n        {$residentAddressJoin}" : '') . "
        WHERE {$residentVerifiedWhere} AND {$residentSectorExpr} IS NOT NULL AND {$residentSectorExpr} <> ''
        {$residentFilterSql}
    ");
    $officialSectorCounts = array_fill_keys(array_keys(rp_official_sector_options()), 0);
    $extraSectorCounts = [];
    foreach ($sectorRows as $r) {
        foreach (array_map('trim', explode(',', $r['sector_membership'])) as $sk) {
            $label = rp_normalize_sector_label($sk);
            if ($label === '') {
                continue;
            }
            if (array_key_exists($label, $officialSectorCounts)) {
                $officialSectorCounts[$label]++;
            } else {
                $extraSectorCounts[$label] = ($extraSectorCounts[$label] ?? 0) + 1;
            }
        }
    }
    arsort($extraSectorCounts);
    $res['by_sector'] = array_filter(
        array_merge($officialSectorCounts, $extraSectorCounts),
        static fn(int $count): bool => $count > 0
    );
    $res['by_sector_rows'] = [];
    foreach ($officialSectorCounts as $sectorLabel => $count) {
        $res['by_sector_rows'][] = [
            'sector' => $sectorLabel,
            'total' => $count,
        ];
    }
    foreach ($extraSectorCounts as $sectorLabel => $count) {
        $res['by_sector_rows'][] = [
            'sector' => $sectorLabel,
            'total' => $count,
        ];
    }

    $employmentRows = [];
    if (rp_column_exists($conn, 'residentinformationtbl', 'occupation')) {
        $employmentRows = rp_safe_query($conn, "
            SELECT
              CASE WHEN COALESCE(ri.occupation, 0) = 1 THEN 'Employed' ELSE 'Unemployed' END AS employment_label,
              COUNT(*) AS total
            FROM residentinformationtbl ri
            JOIN statuslookuptbl s ON s.status_id = ri.status_id_resident
            " . ($residentAddressJoin !== '' ? "\n        {$residentAddressJoin}" : '') . "
            WHERE {$residentVerifiedWhere}
            {$residentFilterSql}
            GROUP BY employment_label
            ORDER BY FIELD(employment_label, 'Employed', 'Unemployed')
        ");
    }
    $employmentIndex = ['Employed' => 0, 'Unemployed' => 0];
    foreach ($employmentRows as $row) {
        $label = (string)($row['employment_label'] ?? '');
        if (array_key_exists($label, $employmentIndex)) {
            $employmentIndex[$label] = (int)($row['total'] ?? 0);
        }
    }
    $res['by_employment'] = [
        ['employment' => 'Employed', 'total' => $employmentIndex['Employed']],
        ['employment' => 'Unemployed', 'total' => $employmentIndex['Unemployed']],
    ];

    $res['monthly_reg'] = rp_safe_query($conn, "
        SELECT DATE_FORMAT(ri.created_at,'%Y-%m') AS month, COUNT(*) AS total
        FROM residentinformationtbl ri
        JOIN statuslookuptbl s ON s.status_id = ri.status_id_resident
        " . ($residentAddressJoin !== '' ? "\n        {$residentAddressJoin}" : '') . "
        WHERE ri.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
          AND {$residentVerifiedWhere}
        {$residentFilterSql}
        GROUP BY month ORDER BY month ASC
    ");

    $res['household_kpi'] = rp_safe_query($conn, "
        SELECT COUNT(DISTINCT ri.resident_id) AS total
        FROM residentinformationtbl ri
        JOIN statuslookuptbl s ON s.status_id = ri.status_id_resident
        " . ($residentAddressJoin !== '' ? "\n        {$residentAddressJoin}" : '') . "
        WHERE {$residentVerifiedWhere}
          AND COALESCE(ri.head_of_family, 0) = 1
        {$residentFilterSql}
    ");
    $res['household_kpi'] = $res['household_kpi'][0] ?? [];

    if ($residentAddressJoin !== '') {
        $res['household_by_area'] = rp_safe_query($conn, "
            SELECT COALESCE({$residentAreaExpr},'Unspecified') AS area, COUNT(DISTINCT ri.resident_id) AS total
            FROM residentinformationtbl ri
            JOIN statuslookuptbl s ON s.status_id = ri.status_id_resident
            {$residentAddressJoin}
            WHERE {$residentVerifiedWhere}
              AND COALESCE(ri.head_of_family, 0) = 1
            {$residentFilterSql}
            GROUP BY area ORDER BY total DESC
        ");
    }
    $res['household_by_area_complete'] = rp_complete_area_rollup_rows($res['household_by_area'] ?? [], 'area', ['total' => 0]);
}

// ═══════════════════════════════════════════════════════════════════════════════
// MODULE: APPOINTMENTS
// ═══════════════════════════════════════════════════════════════════════════════
$appt = [];
if ($module === 'appointments' && rp_table_exists($conn, 'appointmentstbl')) {
    $df = $conn->real_escape_string($dateFrom);
    $dt = $conn->real_escape_string($dateTo);
    $appointmentResidentParts = rp_column_exists($conn, 'appointmentstbl', 'user_id_resident')
        ? rp_user_resident_parts($conn, 'a.user_id_resident', 'apt')
        : ['joins' => '', 'sector_expr' => 'NULL', 'area_expr' => 'NULL'];
    $appointmentJoin = $appointmentResidentParts['joins'] !== '' ? $appointmentResidentParts['joins'] : '';
    $appointmentAreaExpr = $appointmentResidentParts['area_expr'];
    $appointmentSectorExpr = $appointmentResidentParts['sector_expr'];
    $appointmentTypeExpr = "COALESCE(NULLIF(TRIM(a.subject), ''), NULLIF(TRIM(a.purpose), ''), 'Not specified')";
    $appointmentFilterClauses = ["DATE(a.request_timestamp) BETWEEN '{$df}' AND '{$dt}'"];
    if ($reportFilterTypes !== []) {
        $appointmentFilterClauses[] = "{$appointmentTypeExpr} IN (" . rp_sql_in_list($conn, $reportFilterTypes) . ")";
    }
    if ($reportFilterAreas !== [] && $appointmentAreaExpr !== 'NULL') {
        $appointmentFilterClauses[] = "{$appointmentAreaExpr} IN (" . rp_sql_in_list($conn, $reportFilterAreas) . ")";
    }
    if ($reportFilterSectors !== [] && $appointmentSectorExpr !== 'NULL') {
        $appointmentFilterClauses[] = rp_csv_contains_any_expr($conn, $appointmentSectorExpr, $reportFilterSectors);
    }
    $appointmentWhere = implode(' AND ', $appointmentFilterClauses);
    $reportFilterOptions['type'] = rp_options_from_rows(rp_safe_query($conn, "
        SELECT {$appointmentTypeExpr} AS value
        FROM appointmentstbl a
        " . ($appointmentJoin !== '' ? "\n        {$appointmentJoin}" : '') . "
        WHERE DATE(a.request_timestamp) BETWEEN '{$df}' AND '{$dt}'
        GROUP BY {$appointmentTypeExpr}
        ORDER BY {$appointmentTypeExpr} ASC
    "), 'value');
    if ($appointmentAreaExpr !== 'NULL') {
        $reportFilterOptions['area'] = rp_options_from_rows(rp_safe_query($conn, "
            SELECT {$appointmentAreaExpr} AS value
            FROM appointmentstbl a
            {$appointmentJoin}
            WHERE DATE(a.request_timestamp) BETWEEN '{$df}' AND '{$dt}'
              AND {$appointmentAreaExpr} IS NOT NULL
              AND {$appointmentAreaExpr} <> ''
            GROUP BY {$appointmentAreaExpr}
            ORDER BY {$appointmentAreaExpr} ASC
        "), 'value');
    }
    if ($appointmentSectorExpr !== 'NULL') {
        $reportFilterOptions['sector'] = rp_sector_options_from_rows(rp_safe_query($conn, "
            SELECT {$appointmentSectorExpr} AS sector_membership
            FROM appointmentstbl a
            {$appointmentJoin}
            WHERE DATE(a.request_timestamp) BETWEEN '{$df}' AND '{$dt}'
              AND {$appointmentSectorExpr} IS NOT NULL
              AND {$appointmentSectorExpr} <> ''
        "));
    }

    $appt['kpi'] = rp_safe_query($conn, "
        SELECT COUNT(*) AS total,
          SUM(CASE WHEN s.status_name IN ('Scheduled','Confirmed') THEN 1 ELSE 0 END) AS scheduled,
          SUM(CASE WHEN s.status_name = 'Completed' THEN 1 ELSE 0 END) AS completed,
          SUM(CASE WHEN s.status_name IN ('Cancelled','CancelledByResident','CancelledByAdmin') THEN 1 ELSE 0 END) AS cancelled,
          SUM(CASE WHEN s.status_name = 'Pending' THEN 1 ELSE 0 END) AS pending
        FROM appointmentstbl a
        JOIN statuslookuptbl s ON s.status_id = a.appointment_status_id
        " . ($appointmentJoin !== '' ? "\n        {$appointmentJoin}" : '') . "
        WHERE {$appointmentWhere}
    ");
    $appt['kpi'] = $appt['kpi'][0] ?? [];

    $appt['by_status'] = rp_safe_query($conn, "
        SELECT s.status_name AS status, COUNT(*) AS total
        FROM appointmentstbl a
        JOIN statuslookuptbl s ON s.status_id = a.appointment_status_id
        " . ($appointmentJoin !== '' ? "\n        {$appointmentJoin}" : '') . "
        WHERE {$appointmentWhere}
        GROUP BY s.status_name ORDER BY total DESC
    ");

    $appt['by_purpose'] = rp_safe_query($conn, "
        SELECT COALESCE(NULLIF(TRIM(purpose),''),'Not specified') AS purpose, COUNT(*) AS total
        FROM appointmentstbl a
        " . ($appointmentJoin !== '' ? "\n        {$appointmentJoin}" : '') . "
        WHERE {$appointmentWhere}
        GROUP BY purpose ORDER BY total DESC LIMIT 20
    ");

    if ($appointmentAreaExpr !== 'NULL') {
        $appt['by_area'] = rp_safe_query($conn, "
            SELECT COALESCE({$appointmentAreaExpr}, 'Unspecified') AS area, COUNT(*) AS total
            FROM appointmentstbl a
            {$appointmentJoin}
            WHERE {$appointmentWhere}
            GROUP BY area
            ORDER BY total DESC
        ");
    }

    if ($appointmentSectorExpr !== 'NULL') {
        $appointmentSectorRows = rp_safe_query($conn, "
            SELECT {$appointmentSectorExpr} AS sector_membership
            FROM appointmentstbl a
            {$appointmentJoin}
            WHERE {$appointmentWhere}
              AND {$appointmentSectorExpr} IS NOT NULL
              AND {$appointmentSectorExpr} <> ''
        ");
        $appt['by_sector'] = rp_sector_rollup_rows($appointmentSectorRows);
    }

    $appt['trend'] = rp_safe_query($conn, "
        SELECT DATE_FORMAT(request_timestamp,'%Y-%m') AS month, COUNT(*) AS total,
          SUM(CASE WHEN s.status_name='Completed' THEN 1 ELSE 0 END) AS completed
        FROM appointmentstbl a
        JOIN statuslookuptbl s ON s.status_id = a.appointment_status_id
        " . ($appointmentJoin !== '' ? "\n        {$appointmentJoin}" : '') . "
        WHERE a.request_timestamp >= DATE_SUB(NOW(), INTERVAL 12 MONTH)" . ($reportFilterTypes !== [] ? " AND {$appointmentTypeExpr} IN (" . rp_sql_in_list($conn, $reportFilterTypes) . ")" : '') . ($reportFilterAreas !== [] && $appointmentAreaExpr !== 'NULL' ? " AND {$appointmentAreaExpr} IN (" . rp_sql_in_list($conn, $reportFilterAreas) . ")" : '') . ($reportFilterSectors !== [] && $appointmentSectorExpr !== 'NULL' ? " AND " . rp_csv_contains_any_expr($conn, $appointmentSectorExpr, $reportFilterSectors) : '') . "
        GROUP BY month ORDER BY month ASC
    ");
}

// ═══════════════════════════════════════════════════════════════════════════════
// MODULE: BLOTTER
// ═══════════════════════════════════════════════════════════════════════════════
$blot = [];
if ($module === 'blotter' && rp_table_exists($conn, 'casereportstbl')) {
    $df = $conn->real_escape_string($dateFrom);
    $dt = $conn->real_escape_string($dateTo);
    $caseDateExpr = rp_first_existing_datetime_expr($conn, 'casereportstbl', 'c', ['report_timestamp', 'created_at']);
    $caseResidentParts = rp_column_exists($conn, 'casereportstbl', 'resident_user_id')
        ? rp_user_resident_parts($conn, 'c.resident_user_id', 'blt')
        : ['joins' => '', 'sector_expr' => 'NULL', 'area_expr' => 'NULL'];
    $caseJoin = $caseResidentParts['joins'] !== '' ? $caseResidentParts['joins'] : '';
    $caseAreaExpr = $caseResidentParts['area_expr'];
    $caseSectorExpr = $caseResidentParts['sector_expr'];
    $complaintTypeExpr = "COALESCE(NULLIF(TRIM(c.complaint_type), ''), 'Not specified')";
    $blotterFilterClauses = ["DATE({$caseDateExpr}) BETWEEN '{$df}' AND '{$dt}'"];
    if ($reportFilterTypes !== []) {
        $blotterFilterClauses[] = "{$complaintTypeExpr} IN (" . rp_sql_in_list($conn, $reportFilterTypes) . ")";
    }
    if ($reportFilterAreas !== [] && $caseAreaExpr !== 'NULL') {
        $blotterFilterClauses[] = "{$caseAreaExpr} IN (" . rp_sql_in_list($conn, $reportFilterAreas) . ")";
    }
    if ($reportFilterSectors !== [] && $caseSectorExpr !== 'NULL') {
        $blotterFilterClauses[] = rp_csv_contains_any_expr($conn, $caseSectorExpr, $reportFilterSectors);
    }
    $blotterWhere = implode(' AND ', $blotterFilterClauses);
    $reportFilterOptions['type'] = rp_options_from_rows(rp_safe_query($conn, "
        SELECT {$complaintTypeExpr} AS value
        FROM casereportstbl c
        " . ($caseJoin !== '' ? "\n        {$caseJoin}" : '') . "
        WHERE DATE({$caseDateExpr}) BETWEEN '{$df}' AND '{$dt}'
        GROUP BY {$complaintTypeExpr}
        ORDER BY {$complaintTypeExpr} ASC
    "), 'value');
    if ($caseAreaExpr !== 'NULL') {
        $reportFilterOptions['area'] = rp_options_from_rows(rp_safe_query($conn, "
            SELECT {$caseAreaExpr} AS value
            FROM casereportstbl c
            {$caseJoin}
            WHERE DATE({$caseDateExpr}) BETWEEN '{$df}' AND '{$dt}'
              AND {$caseAreaExpr} IS NOT NULL
              AND {$caseAreaExpr} <> ''
            GROUP BY {$caseAreaExpr}
            ORDER BY {$caseAreaExpr} ASC
        "), 'value');
    }
    if ($caseSectorExpr !== 'NULL') {
        $reportFilterOptions['sector'] = rp_sector_options_from_rows(rp_safe_query($conn, "
            SELECT {$caseSectorExpr} AS sector_membership
            FROM casereportstbl c
            {$caseJoin}
            WHERE DATE({$caseDateExpr}) BETWEEN '{$df}' AND '{$dt}'
              AND {$caseSectorExpr} IS NOT NULL
              AND {$caseSectorExpr} <> ''
        "));
    }

    $blot['kpi'] = rp_safe_query($conn, "
        SELECT COUNT(*) AS total,
          SUM(CASE WHEN LOWER(s.status_name) IN ('active','open','ongoing') THEN 1 ELSE 0 END) AS active,
          SUM(CASE WHEN LOWER(s.status_name) IN ('resolved','closed','settled') THEN 1 ELSE 0 END) AS resolved,
          SUM(CASE WHEN report_type='Blotter' THEN 1 ELSE 0 END) AS blotter_count,
          SUM(CASE WHEN report_type='Complaint' THEN 1 ELSE 0 END) AS complaint_count
        FROM casereportstbl c
        JOIN statuslookuptbl s ON s.status_id = c.case_status_id
        " . ($caseJoin !== '' ? "\n        {$caseJoin}" : '') . "
        WHERE {$blotterWhere}
    ");
    $blot['kpi'] = $blot['kpi'][0] ?? [];

    $blot['by_type'] = rp_safe_query($conn, "
        SELECT {$complaintTypeExpr} AS complaint_type,
          COUNT(*) AS total,
          SUM(CASE WHEN LOWER(s.status_name) IN ('resolved','closed','settled') THEN 1 ELSE 0 END) AS resolved
        FROM casereportstbl c
        JOIN statuslookuptbl s ON s.status_id = c.case_status_id
        " . ($caseJoin !== '' ? "\n        {$caseJoin}" : '') . "
        WHERE {$blotterWhere}
        GROUP BY complaint_type ORDER BY total DESC LIMIT 20
    ");

    $blot['by_status'] = rp_safe_query($conn, "
        SELECT s.status_name AS status, COUNT(*) AS total
        FROM casereportstbl c
        JOIN statuslookuptbl s ON s.status_id = c.case_status_id
        " . ($caseJoin !== '' ? "\n        {$caseJoin}" : '') . "
        WHERE {$blotterWhere}
        GROUP BY s.status_name ORDER BY total DESC
    ");

    if ($caseAreaExpr !== 'NULL') {
        $blot['by_area'] = rp_safe_query($conn, "
            SELECT COALESCE({$caseAreaExpr}, 'Unspecified') AS area, COUNT(*) AS total
            FROM casereportstbl c
            {$caseJoin}
            WHERE {$blotterWhere}
            GROUP BY area
            ORDER BY total DESC
        ");
    }

    if ($caseSectorExpr !== 'NULL') {
        $blotterSectorRows = rp_safe_query($conn, "
            SELECT {$caseSectorExpr} AS sector_membership
            FROM casereportstbl c
            {$caseJoin}
            WHERE {$blotterWhere}
              AND {$caseSectorExpr} IS NOT NULL
              AND {$caseSectorExpr} <> ''
        ");
        $blot['by_sector'] = rp_sector_rollup_rows($blotterSectorRows);
    }

    $blot['trend'] = rp_safe_query($conn, "
        SELECT DATE_FORMAT({$caseDateExpr},'%Y-%m') AS month, COUNT(*) AS total
        FROM casereportstbl c
        " . ($caseJoin !== '' ? "\n        {$caseJoin}" : '') . "
        WHERE {$caseDateExpr} >= DATE_SUB(NOW(), INTERVAL 12 MONTH)" . ($reportFilterTypes !== [] ? " AND {$complaintTypeExpr} IN (" . rp_sql_in_list($conn, $reportFilterTypes) . ")" : '') . ($reportFilterAreas !== [] && $caseAreaExpr !== 'NULL' ? " AND {$caseAreaExpr} IN (" . rp_sql_in_list($conn, $reportFilterAreas) . ")" : '') . ($reportFilterSectors !== [] && $caseSectorExpr !== 'NULL' ? " AND " . rp_csv_contains_any_expr($conn, $caseSectorExpr, $reportFilterSectors) : '') . "
        GROUP BY month ORDER BY month ASC
    ");
}

// ═══════════════════════════════════════════════════════════════════════════════
// MODULE: COMPLAINTS
// ═══════════════════════════════════════════════════════════════════════════════
$comp = [];
if ($module === 'complaints' && rp_table_exists($conn, 'complaintstbl')) {
    $df = $conn->real_escape_string($dateFrom);
    $dt = $conn->real_escape_string($dateTo);
    $hasCaseTable = rp_table_exists($conn, 'casereportstbl');
    $complaintCaseJoin = $hasCaseTable ? "LEFT JOIN casereportstbl c ON c.case_id = ct.case_id" : '';
    $complaintResidentParts = ($hasCaseTable && rp_column_exists($conn, 'casereportstbl', 'resident_user_id'))
        ? rp_user_resident_parts($conn, 'c.resident_user_id', 'cmp')
        : ['joins' => '', 'sector_expr' => 'NULL', 'area_expr' => 'NULL'];
    $complaintResidentJoin = $complaintResidentParts['joins'] !== '' ? $complaintResidentParts['joins'] : '';
    $complaintAreaExpr = $complaintResidentParts['area_expr'];
    $complaintSectorExpr = $complaintResidentParts['sector_expr'];
    $complaintTypeExpr = $hasCaseTable
        ? "COALESCE(NULLIF(TRIM(c.complaint_type), ''), 'Not specified')"
        : "'Not specified'";
    $complaintFilterClauses = ["DATE(ct.created_at) BETWEEN '{$df}' AND '{$dt}'"];
    if ($reportFilterTypes !== [] && $hasCaseTable) {
        $complaintFilterClauses[] = "{$complaintTypeExpr} IN (" . rp_sql_in_list($conn, $reportFilterTypes) . ")";
    }
    if ($reportFilterAreas !== [] && $complaintAreaExpr !== 'NULL') {
        $complaintFilterClauses[] = "{$complaintAreaExpr} IN (" . rp_sql_in_list($conn, $reportFilterAreas) . ")";
    }
    if ($reportFilterSectors !== [] && $complaintSectorExpr !== 'NULL') {
        $complaintFilterClauses[] = rp_csv_contains_any_expr($conn, $complaintSectorExpr, $reportFilterSectors);
    }
    $complaintWhere = implode(' AND ', $complaintFilterClauses);
    if ($hasCaseTable) {
        $reportFilterOptions['type'] = rp_options_from_rows(rp_safe_query($conn, "
            SELECT {$complaintTypeExpr} AS value
            FROM complaintstbl ct
            {$complaintCaseJoin}
            " . ($complaintResidentJoin !== '' ? "\n        {$complaintResidentJoin}" : '') . "
            WHERE DATE(ct.created_at) BETWEEN '{$df}' AND '{$dt}'
            GROUP BY {$complaintTypeExpr}
            ORDER BY {$complaintTypeExpr} ASC
        "), 'value');
    }
    if ($complaintAreaExpr !== 'NULL') {
        $reportFilterOptions['area'] = rp_options_from_rows(rp_safe_query($conn, "
            SELECT {$complaintAreaExpr} AS value
            FROM complaintstbl ct
            {$complaintCaseJoin}
            {$complaintResidentJoin}
            WHERE DATE(ct.created_at) BETWEEN '{$df}' AND '{$dt}'
              AND {$complaintAreaExpr} IS NOT NULL
              AND {$complaintAreaExpr} <> ''
            GROUP BY {$complaintAreaExpr}
            ORDER BY {$complaintAreaExpr} ASC
        "), 'value');
    }
    if ($complaintSectorExpr !== 'NULL') {
        $reportFilterOptions['sector'] = rp_sector_options_from_rows(rp_safe_query($conn, "
            SELECT {$complaintSectorExpr} AS sector_membership
            FROM complaintstbl ct
            {$complaintCaseJoin}
            {$complaintResidentJoin}
            WHERE DATE(ct.created_at) BETWEEN '{$df}' AND '{$dt}'
              AND {$complaintSectorExpr} IS NOT NULL
              AND {$complaintSectorExpr} <> ''
        "));
    }

    $comp['kpi'] = rp_safe_query($conn, "
        SELECT COUNT(*) AS total,
          SUM(CASE WHEN escalated_to_blotter=1 THEN 1 ELSE 0 END) AS escalated,
          SUM(CASE WHEN escalated_to_blotter=0 OR escalated_to_blotter IS NULL THEN 1 ELSE 0 END) AS unescalated,
          SUM(CASE WHEN complaint_origin='walk_in' OR complaint_origin='Walk-in' THEN 1 ELSE 0 END) AS walkin,
          SUM(CASE WHEN complaint_origin='online' OR complaint_origin='Online' THEN 1 ELSE 0 END) AS online_count
        FROM complaintstbl ct
        {$complaintCaseJoin}
        " . ($complaintResidentJoin !== '' ? "\n        {$complaintResidentJoin}" : '') . "
        WHERE {$complaintWhere}
    ");
    $comp['kpi'] = $comp['kpi'][0] ?? [];

    $comp['by_origin'] = rp_safe_query($conn, "
        SELECT COALESCE(complaint_origin,'Unknown') AS origin, COUNT(*) AS total
        FROM complaintstbl ct
        {$complaintCaseJoin}
        " . ($complaintResidentJoin !== '' ? "\n        {$complaintResidentJoin}" : '') . "
        WHERE {$complaintWhere}
        GROUP BY complaint_origin ORDER BY total DESC
    ");

    if ($hasCaseTable) {
        $comp['by_type'] = rp_safe_query($conn, "
            SELECT {$complaintTypeExpr} AS complaint_type,
              COUNT(*) AS total,
              SUM(CASE WHEN escalated_to_blotter=1 THEN 1 ELSE 0 END) AS escalated
            FROM complaintstbl ct
            {$complaintCaseJoin}
            " . ($complaintResidentJoin !== '' ? "\n        {$complaintResidentJoin}" : '') . "
            WHERE {$complaintWhere}
            GROUP BY complaint_type
            ORDER BY total DESC
            LIMIT 20
        ");
    }

    $comp['by_kind'] = rp_safe_query($conn, "
        SELECT COALESCE(subject_kind,'Unknown') AS kind, COUNT(*) AS total
        FROM complaintstbl ct
        {$complaintCaseJoin}
        " . ($complaintResidentJoin !== '' ? "\n        {$complaintResidentJoin}" : '') . "
        WHERE {$complaintWhere}
        GROUP BY subject_kind ORDER BY total DESC
    ");

    if ($complaintAreaExpr !== 'NULL') {
        $comp['by_area'] = rp_safe_query($conn, "
            SELECT COALESCE({$complaintAreaExpr}, 'Unspecified') AS area, COUNT(*) AS total
            FROM complaintstbl ct
            {$complaintCaseJoin}
            {$complaintResidentJoin}
            WHERE {$complaintWhere}
            GROUP BY area
            ORDER BY total DESC
        ");
    }

    if ($complaintSectorExpr !== 'NULL') {
        $complaintSectorRows = rp_safe_query($conn, "
            SELECT {$complaintSectorExpr} AS sector_membership
            FROM complaintstbl ct
            {$complaintCaseJoin}
            {$complaintResidentJoin}
            WHERE {$complaintWhere}
              AND {$complaintSectorExpr} IS NOT NULL
              AND {$complaintSectorExpr} <> ''
        ");
        $comp['by_sector'] = rp_sector_rollup_rows($complaintSectorRows);
    }

    $comp['trend'] = rp_safe_query($conn, "
        SELECT DATE_FORMAT(ct.created_at,'%Y-%m') AS month, COUNT(*) AS total,
          SUM(CASE WHEN escalated_to_blotter=1 THEN 1 ELSE 0 END) AS escalated
        FROM complaintstbl ct
        {$complaintCaseJoin}
        " . ($complaintResidentJoin !== '' ? "\n        {$complaintResidentJoin}" : '') . "
        WHERE ct.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)" . ($reportFilterTypes !== [] && $hasCaseTable ? " AND {$complaintTypeExpr} IN (" . rp_sql_in_list($conn, $reportFilterTypes) . ")" : '') . ($reportFilterAreas !== [] && $complaintAreaExpr !== 'NULL' ? " AND {$complaintAreaExpr} IN (" . rp_sql_in_list($conn, $reportFilterAreas) . ")" : '') . ($reportFilterSectors !== [] && $complaintSectorExpr !== 'NULL' ? " AND " . rp_csv_contains_any_expr($conn, $complaintSectorExpr, $reportFilterSectors) : '') . "
        GROUP BY month ORDER BY month ASC
    ");
}

// ── Module labels ─────────────────────────────────────────────────────────────
$moduleLabels = [
    'certificate_issuance' => ['label' => 'Certificate Issuance Report', 'icon' => 'fa-file-circle-check'],
    'clearance_issuance' => ['label' => 'Clearance Issuance Report', 'icon' => 'fa-stamp'],
    'financial'         => ['label' => 'Financial / Collections', 'icon' => 'fa-peso-sign'],
    'residents'         => ['label' => 'Residents', 'icon' => 'fa-users'],
    'blotter'           => ['label' => 'Blotter & Cases', 'icon' => 'fa-gavel'],
    'complaints'        => ['label' => 'Complaints & Grievances', 'icon' => 'fa-comments'],
];
$currentLabel = $moduleLabels[$module]['label'];
$isPrintView  = ($_GET['format'] ?? '') === 'print';
$isPdfDownloadView = $isPrintView && strtolower(trim((string)($_GET['download'] ?? ''))) === 'pdf';
$shouldAutoPrint = $isPrintView && !$isPdfDownloadView && (($_GET['autoprint'] ?? '1') !== '0');
$reportLeftLogo = '../../Images/San_Jose_LOGO.jpg';
$reportRightLogo = '../../Images/Montalban_Logo.png';
$reportChartTypeOptions = [
    'bar' => 'Bar Chart',
    'pie' => 'Pie Chart',
];
$reportChartType = strtolower(trim((string)($_GET['chart_type'] ?? 'bar')));
if (!isset($reportChartTypeOptions[$reportChartType])) {
    $reportChartType = 'bar';
}
$reportCustomizeConfig = rp_report_customize_config($module);
$reportCustomizeColumnGroups = rp_resolve_customize_column_groups(
    $reportCustomizeConfig['columns'] ?? [],
    $reportCustomizeConfig['column_groups'] ?? []
);
$availableReportSections = array_keys($reportCustomizeConfig['sections']);
$availableReportColumns = array_keys($reportCustomizeConfig['columns']);
$defaultVisibleReportSections = $availableReportSections;
$defaultVisibleReportColumns = rp_default_visible_report_columns($availableReportColumns);
$visibleReportSections = rp_parse_query_list($_GET['show_section'] ?? '');
$visibleReportColumns = rp_parse_query_list($_GET['show_column'] ?? '');
$visibleReportSections = $visibleReportSections === []
    ? $defaultVisibleReportSections
    : array_values(array_intersect($visibleReportSections, $availableReportSections));
$visibleReportColumns = $visibleReportColumns === []
    ? $defaultVisibleReportColumns
    : array_values(array_intersect($visibleReportColumns, $availableReportColumns));
if ($visibleReportSections === []) {
    $visibleReportSections = $defaultVisibleReportSections;
}
if ($visibleReportColumns === []) {
    $visibleReportColumns = $defaultVisibleReportColumns;
}
if ($issuanceModuleConfig !== null && in_array('breakdown_total', $availableReportColumns, true) && !in_array('breakdown_total', $visibleReportColumns, true)) {
    $visibleReportColumns[] = 'breakdown_total';
}
$showReportSection = static fn(string $key): bool => in_array($key, $visibleReportSections, true);
$showReportColumn = static fn(string $key): bool => in_array($key, $visibleReportColumns, true);
$reportColumnClass = static fn(string $key): string => $showReportColumn($key) ? '' : ' rp-col-hidden';
$reportBreakdownAreaClass = static fn(string $area): string => $showReportColumn(rp_breakdown_area_column_key($area)) ? '' : ' rp-col-hidden';
$reportBreakdownSectorClass = static fn(string $sector): string => $showReportColumn(rp_breakdown_sector_column_key($sector)) ? '' : ' rp-col-hidden';
$layoutSectionDiffCount = rp_selection_difference_count($defaultVisibleReportSections, $visibleReportSections);
$layoutColumnDiffCount = rp_selection_difference_count($defaultVisibleReportColumns, $visibleReportColumns);
$isCustomLayoutActive = $layoutSectionDiffCount > 0
    || $layoutColumnDiffCount > 0
    || $reportChartType !== 'bar';
$hiddenLayoutCount = $layoutSectionDiffCount
    + $layoutColumnDiffCount
    + ($reportChartType !== 'bar' ? 1 : 0);
$reportFilterOptions['area'] = $officialReportAreaOptions;
$reportFilterOptions['sector'] = $issuanceModuleConfig !== null ? [] : $officialReportSectorOptions;
$reportFilterTypes = array_values(array_intersect($reportFilterTypes, array_keys($reportFilterOptions['type'])));
$reportFilterTypes = rp_normalize_full_filter_selection($reportFilterTypes, $reportFilterOptions['type']);
$reportFilterAreas = rp_normalize_full_filter_selection($reportFilterAreas, $reportFilterOptions['area']);
$reportFilterSectors = rp_normalize_full_filter_selection($reportFilterSectors, $reportFilterOptions['sector']);
if ($issuanceModuleConfig !== null) {
    $reportFilterStatuses = rp_normalize_full_filter_selection($reportFilterStatuses, $reportFilterStatusOptions, $defaultReportStatusSelection);
}
$filterModalTypes = rp_filter_modal_checked_values($reportFilterTypes, $reportFilterOptions['type']);
$filterModalAreas = rp_filter_modal_checked_values($reportFilterAreas, $reportFilterOptions['area']);
$filterModalSectors = rp_filter_modal_checked_values($reportFilterSectors, $reportFilterOptions['sector']);
$filterModalStatuses = rp_filter_modal_checked_values($reportFilterStatuses, $reportFilterStatusOptions, $defaultReportStatusSelection);
$activeReportFilters = [];
if ($reportFilterTypes !== []) {
    $labels = array_values(array_map(
        static fn(string $key): string => $reportFilterOptions['type'][$key] ?? $key,
        $reportFilterTypes
    ));
    $activeReportFilters[] = $reportFilterLabels['type'] . ': ' . implode(', ', $labels);
}
if ($reportFilterAreas !== []) {
    $activeReportFilters[] = $reportFilterLabels['area'] . ': ' . implode(', ', $reportFilterAreas);
}
if ($reportFilterSectors !== []) {
    $activeReportFilters[] = $reportFilterLabels['sector'] . ': ' . implode(', ', $reportFilterSectors);
}
if ($issuanceModuleConfig !== null && $reportFilterStatuses !== []) {
    $labels = array_values(array_map(
        static fn(string $key): string => $reportFilterStatusOptions[$key] ?? ucfirst($key),
        $reportFilterStatuses
    ));
    $activeReportFilters[] = $reportFilterLabels['status'] . ': ' . implode(', ', $labels);
}
$reportFilterStateQuery = ['module' => $module];
if ($module !== 'residents') {
    $reportFilterStateQuery['date_from'] = $dateFrom;
    $reportFilterStateQuery['date_to'] = $dateTo;
}
foreach ($reportFilterTypes as $value) {
    $reportFilterStateQuery['filter_type'][] = $value;
}
foreach ($reportFilterAreas as $value) {
    $reportFilterStateQuery['filter_area'][] = $value;
}
foreach ($reportFilterSectors as $value) {
    $reportFilterStateQuery['filter_sector'][] = $value;
}
foreach ($reportFilterStatuses as $value) {
    $reportFilterStateQuery['filter_status'][] = $value;
}
$reportLayoutStateQuery = $reportFilterStateQuery;
foreach ($visibleReportSections as $value) {
    $reportLayoutStateQuery['show_section'][] = $value;
}
foreach ($visibleReportColumns as $value) {
    $reportLayoutStateQuery['show_column'][] = $value;
}
if ($reportChartType !== 'bar') {
    $reportLayoutStateQuery['chart_type'] = $reportChartType;
}
$reportFilterStateUrl = $baseUrl . '?' . http_build_query($reportFilterStateQuery);
$reportLayoutStateUrl = $baseUrl . '?' . http_build_query($reportLayoutStateQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="../../Images/favicon_sanjose.png?v=20260211">
  <title><?= $isPrintView ? htmlspecialchars($currentLabel).' — Barangay San Jose' : 'Reports' ?></title>
  <?php if (!$isPrintView): ?>
  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css">
  <?php endif; ?>
  <style>
    :root {
      --rp-letter-width: 8.5in;
      --rp-letter-height: 11in;
      --rp-page-margin: 0.5in;
      --rp-screen-page-gap: 18px;
    }
    @page { size: letter portrait; margin: 0; }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    /* ── Page shell ─────────────────────────────────────────────────────────── */
    .reports-shell { max-width: 1200px; margin: 0 auto; }
    .module-nav .nav-link { font-size: .875rem; white-space: nowrap; }
    .module-nav .nav-link.active { background: #DE710C; color: #fff !important; border-color: #DE710C; }
    .rp-filter-form {
      display: grid;
      gap: 14px;
    }
    .rp-controls { margin-bottom: 24px; }
    .rp-controls-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
      flex-wrap: wrap;
      margin-bottom: 12px;
    }
    .rp-controls-summary {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
      color: #475569;
      font-size: .9rem;
    }
    .rp-filter-chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 10px;
      border-radius: 999px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      color: #334155;
      font-size: .82rem;
      line-height: 1.2;
    }
    .rp-controls-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center;
    }
    .rp-filter-count {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 1.35rem;
      height: 1.35rem;
      padding: 0 .35rem;
      border-radius: 999px;
      background: #2563eb;
      color: #fff;
      font-size: .75rem;
      font-weight: 700;
    }
    .rp-filter-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 14px;
      align-items: end;
    }
    .rp-filter-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center;
    }
    .rp-filter-modal .modal-content {
      border: none;
      border-radius: 18px;
      box-shadow: 0 18px 40px rgba(15, 23, 42, 0.14);
      overflow: hidden;
    }
    .rp-filter-modal .rp-filter-form {
      display: flex;
      flex-direction: column;
      gap: 0;
      min-height: 0;
      max-height: calc(100vh - 1rem);
    }
    .rp-filter-modal .modal-header {
      border-bottom: 1px solid #e5e7eb;
      padding: 1rem 1.25rem;
    }
    .rp-filter-modal .modal-body {
      padding: 1.25rem;
      overflow-y: auto;
      overscroll-behavior: contain;
    }
    .rp-filter-modal .modal-footer {
      border-top: 1px solid #e5e7eb;
      padding: 1rem 1.25rem;
    }
    .rp-checklist-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 14px 18px;
    }
    .rp-checklist-group {
      border: 1px solid #e5e7eb;
      border-radius: 14px;
      padding: 12px 14px;
      background: #fff;
    }
    .rp-checklist-label {
      display: block;
      font-size: .86rem;
      font-weight: 700;
      color: #334155;
      margin-bottom: 8px;
    }
    .rp-checklist-list {
      display: grid;
      gap: 8px;
      max-height: 220px;
      overflow-y: auto;
      padding-right: 4px;
    }
    .rp-checklist-subgroups {
      display: grid;
      gap: 12px;
    }
    .rp-checklist-subgroup {
      border-top: 1px dashed #e2e8f0;
      padding-top: 10px;
    }
    .rp-checklist-subgroup:first-child {
      border-top: none;
      padding-top: 0;
    }
    .rp-checklist-sublabel {
      display: block;
      font-size: .76rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: #64748b;
      margin-bottom: 8px;
    }
    .rp-subsection-title {
      font-size: .82rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: #475569;
      margin: 4px 0 10px;
    }

    /* ── Formal report document ─────────────────────────────────────────────── */
    .rp-doc {
      background: #fff;
      border: 1.5px solid #2f2f2f;
      border-radius: 0;
      box-sizing: border-box;
      width: min(100%, var(--rp-letter-width));
      max-width: var(--rp-letter-width);
      min-height: var(--rp-letter-height);
      margin: 0 auto;
      padding: 34px 36px 40px;
      box-shadow: 0 14px 28px rgba(15, 23, 42, 0.08);
      font-size: 13px;
      line-height: 1.45;
      color: #1a1a1a;
    }
    .rp-doc--source { display: none !important; }
    .rp-print-pages {
      display: grid;
      gap: var(--rp-screen-page-gap);
      justify-content: center;
    }
    .rp-print-page {
      width: var(--rp-letter-width);
      min-height: var(--rp-letter-height);
      box-sizing: border-box;
      padding: var(--rp-page-margin);
      background: #fff;
      border: 1.5px solid #2f2f2f;
      box-shadow: 0 14px 28px rgba(15, 23, 42, 0.08);
      overflow: hidden;
    }
    .rp-print-page-content {
      display: flex;
      flex-direction: column;
      gap: 14pt;
    }
    .rp-pagination-stage {
      position: fixed;
      left: -200vw;
      top: 0;
      visibility: hidden;
      pointer-events: none;
      width: var(--rp-letter-width);
      z-index: -1;
    }
    .rp-page-block {
      width: 100%;
      box-sizing: border-box;
      min-width: 0;
    }
    .rp-page-block--scaled {
      overflow: hidden;
    }
    .rp-page-block-scale {
      transform-origin: top left;
    }

    /* Header */
    .rp-doc-header {
      text-align: center;
      padding-bottom: 0;
      margin-bottom: 24px;
    }
    .rp-letterhead {
      display: grid;
      grid-template-columns: 120px 1fr 120px;
      align-items: center;
      gap: 18px;
      margin-bottom: 0;
    }
    .rp-letterhead-logo {
      width: 108px;
      height: 108px;
      object-fit: contain;
      justify-self: center;
      display: block;
    }
    .rp-letterhead-center {
      text-align: center;
      color: #000;
      line-height: 1.22;
      font-family: Arial, Helvetica, sans-serif;
    }
    .rp-letterhead-center p {
      margin: 0;
      font-size: 19px;
      font-weight: 400;
      text-transform: uppercase;
    }
    .rp-letterhead-rep {
      font-size: 23px !important;
      font-weight: 900 !important;
    }
    .rp-letterhead-barangay {
      font-size: 34px !important;
      font-weight: 900 !important;
      margin-top: 12px !important;
      letter-spacing: .02em;
    }
    .rp-letterhead-line {
      border-bottom: 2px solid #4b5563;
      margin-top: 12px;
    }
    .rp-doc-header .rp-report-title {
      font-size: 17px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: #000;
      margin-top: 18px;
    }
    .rp-doc-header .rp-period { font-size: 12px; font-weight: 400; color: #4b5563; margin-top: 6px; }
    .rp-filter-summary {
      margin-top: 6px;
      font-size: 11px;
      color: #4b5563;
    }
    .rp-report-meta {
      margin-top: 8px;
      font-size: 11px;
      color: #6b7280;
    }

    /* Section label */
    .rp-section {
      margin-top: 22px;
      margin-bottom: 8px;
    }
    .rp-section-label {
      font-size: 11.5px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: #fff;
      background: #DE710C;
      padding: 4px 10px;
      border-radius: 3px;
      display: inline-block;
      margin-bottom: 8px;
    }

    /* Summary 2-col table */
    .rp-summary {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 4px;
      font-size: 13px;
    }
    .rp-summary td {
      padding: 6px 10px;
      border: 1px solid #dde1e7;
    }
    .rp-summary tr:nth-child(odd) td { background: #fafbfc; }
    .rp-summary td:first-child { font-weight: 600; width: 55%; color: #374151; }
    .rp-summary td:last-child { font-weight: 700; color: #111; text-align: right; }
    .rp-summary tfoot td { background: #f0f3f7 !important; font-weight: 700; border-top: 2px solid #adb5bd; }

    /* Data tables */
    .rp-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 12.5px;
      margin-bottom: 4px;
    }
    .rp-table th {
      background: #f0f3f7;
      padding: 7px 10px;
      border: 1px solid #adb5bd;
      font-weight: 700;
      text-align: left;
      white-space: normal;
      font-size: 11.5px;
      text-transform: uppercase;
      letter-spacing: .02em;
      overflow-wrap: anywhere;
    }
    .rp-table td {
      padding: 6px 10px;
      border: 1px solid #d0d7de;
      vertical-align: middle;
      overflow-wrap: anywhere;
    }
    .rp-table tr:nth-child(even) td { background: #fafbfc; }
    .rp-table tfoot td {
      background: #f0f3f7;
      font-weight: 700;
      border-top: 2px solid #adb5bd;
    }
    .rp-table--issuance-breakdown {
      table-layout: fixed;
    }
    .rp-table--issuance-breakdown .rp-breakdown-document-type {
      width: 18%;
      max-width: 18%;
      white-space: normal;
      word-break: normal;
      overflow-wrap: anywhere;
    }
    .rp-table--issuance-breakdown th,
    .rp-table--issuance-breakdown td {
      word-break: normal;
      overflow-wrap: normal;
      white-space: normal;
      hyphens: none;
    }
    .rp-table--issuance-breakdown th:not(.rp-breakdown-document-type),
    .rp-table--issuance-breakdown td:not(.rp-breakdown-document-type) {
      font-size: 11px;
      padding-left: 6px;
      padding-right: 6px;
    }
    .rp-table--issuance-breakdown th {
      line-height: 1.25;
      text-align: center;
    }
    .rp-table--issuance-breakdown th.rp-breakdown-document-type {
      text-align: left;
    }
    .rp-table .text-end { text-align: right; }
    .rp-table .text-center { text-align: center; }
    .rp-table .pct { color: #6b7280; font-size: 11px; }
    .rp-col-hidden { display: none !important; }

    /* Two-column layout for side-by-side tables */
    .rp-two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: start; }
    .rp-three-col { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; align-items: start; }
    .rp-chart-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: start; }
    .rp-chart-card {
      border: 1px solid #dbe4ee;
      border-radius: 14px;
      padding: 14px 16px 16px;
      background: #fff;
      min-width: 0;
      overflow: hidden;
    }
    .rp-chart-wrap {
      position: relative;
      width: 100%;
      max-width: 100%;
      min-height: 280px;
    }
    .rp-chart-wrap canvas { max-width: 100% !important; }
    .rp-chart-note {
      font-size: 11px;
      color: #64748b;
      margin-top: 8px;
    }
    @media (max-width: 980px) {
      .rp-two-col, .rp-three-col, .rp-chart-grid { grid-template-columns: 1fr; }
    }

    /* Signature block */
    .rp-footer {
      margin-top: 36px;
      padding-top: 18px;
    }
    .rp-footer-meta {
      margin-top: 24px; padding-top: 8px; border-top: 1px solid #d1d5db;
      color: #6b7280; font-size: 9px; line-height: 1.35; text-align: center;
    }
    .rp-sig-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; }
    .rp-sig-block { text-align: center; }
    .rp-sig-block .rp-sig-line { border-top: 1px solid #333; padding-top: 4px; margin-top: 36px; }
    .rp-sig-block .rp-sig-name { font-weight: 700; font-size: 13px; text-transform: uppercase; }
    .rp-sig-block .rp-sig-role { font-size: 11px; color: #555; }

    /* Empty state */
    .rp-empty { color: #9ca3af; font-style: italic; padding: 12px 0; font-size: 12px; }

    <?php if (!$isPrintView): ?>
    /* Screen preview: show the report content directly without formal paper framing. */
    .rp-doc {
      border: none;
      box-shadow: none;
      width: 100%;
      max-width: 100%;
      min-height: auto;
      padding: 0;
      margin: 0;
    }
    .rp-doc-header,
    .rp-footer { display: none; }
    .rp-doc > .rp-section:first-of-type { margin-top: 0; }
    <?php endif; ?>

    /* Print from admin view — strip layout chrome */
    @media print {
      #dashboard-sidebar, #admin-mobile-header, .module-nav,
      .rp-controls, .d-print-none { display: none !important; }
      body, html { margin: 0 !important; padding: 0 !important; background: #fff !important; }
      main { padding: 0 !important; margin: 0 !important; background: #fff !important; }
      .bg-white.reports-shell,
      .bg-white { padding: 0 !important; border: none !important; box-shadow: none !important; background: #fff !important; }
      .reports-shell { max-width: 100% !important; }
      .rp-doc {
        border: none !important;
        border-radius: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        max-width: 100% !important;
        font-size: 10pt !important;
        min-height: auto !important;
      }
      .rp-doc-header,
      .rp-footer { display: block !important; }
      .rp-doc-header { padding: 14pt 0 0 !important; margin-bottom: 14pt !important; }
      .rp-letterhead { grid-template-columns: 84pt 1fr 84pt !important; gap: 10pt !important; margin-left: 3% !important; margin-right: 3% !important; }
      .rp-letterhead-logo { width: 76pt !important; height: 76pt !important; }
      .rp-letterhead-center p { font-size: 13pt !important; font-weight: 400 !important; }
      .rp-letterhead-center .rp-letterhead-rep { font-size: 15pt !important; font-weight: 800 !important; }
      .rp-letterhead-center .rp-letterhead-barangay { font-size: 21pt !important; font-weight: 800 !important; margin-top: 8pt !important; }
      .rp-letterhead-line { margin: 8pt 3% 0 !important; border-color: #4b5563 !important; }
      .rp-doc-header .rp-report-title { font-size: 17px !important; color: #000 !important; font-weight: 800 !important; }
      .rp-doc-header .rp-period { font-size: 12px !important; font-weight: 400 !important; margin-top: 6px !important; }
      .rp-section {
        margin-top: 14pt !important;
        page-break-inside: auto !important;
        break-inside: auto !important;
      }
      .rp-section-label {
        font-size: 9pt !important;
        padding: 3pt 8pt !important;
        page-break-after: avoid !important;
        break-after: avoid-page !important;
      }
      .rp-summary td, .rp-table th, .rp-table td { font-size: 9pt !important; padding: 4pt 6pt !important; }
      .rp-table th { white-space: normal !important; word-break: break-word !important; vertical-align: bottom !important; }
      .rp-table--issuance-breakdown { table-layout: fixed !important; width: 100% !important; }
      .rp-table--issuance-breakdown th,
      .rp-table--issuance-breakdown td { font-size: 6.5pt !important; padding: 2pt 2pt !important; white-space: normal !important; word-break: break-word !important; overflow-wrap: break-word !important; vertical-align: top !important; }
      .rp-breakdown-document-type { width: 20% !important; }
      .rp-table tr { page-break-inside: avoid; }
      .rp-two-col, .rp-three-col, .rp-chart-grid {
        display: block !important;
      }
      .rp-two-col > *,
      .rp-three-col > *,
      .rp-chart-grid > * {
        margin-top: 14pt !important;
      }
      .rp-two-col > *:first-child,
      .rp-three-col > *:first-child,
      .rp-chart-grid > *:first-child {
        margin-top: 0 !important;
      }
      .rp-two-col > div,
      .rp-three-col > div,
      .rp-chart-card {
        page-break-inside: avoid !important;
        break-inside: avoid-page !important;
      }
      .rp-chart-card { page-break-inside: avoid; break-inside: avoid; }
      .rp-chart-wrap { min-height: 180pt; }
      .rp-footer { margin-top: 24pt !important; }
      .rp-sig-grid { gap: 32pt !important; }
      .rp-print-pages {
        display: block !important;
        gap: 0 !important;
      }
      .rp-print-page {
        width: auto !important;
        min-height: auto !important;
        padding: var(--rp-page-margin) !important;
        border: none !important;
        box-shadow: none !important;
        page-break-after: always !important;
        break-after: page !important;
      }
      .rp-print-page:last-child {
        page-break-after: auto !important;
        break-after: auto !important;
      }
      .rp-pagination-stage,
      #reportPdfExportStatus {
        display: none !important;
      }
    }

    /* Standalone print view — applied directly (no @media wrapper needed) */
    <?php if ($isPrintView): ?>
    html, body { margin: 0; padding: 0; background: #fff; font-family: Arial, Helvetica, sans-serif; }
    @media screen {
      html, body { background: #dfe4ea; }
      body { padding: 18px 0 28px; }
      .rp-print-page {
        border-color: #b8c2cc !important;
        box-shadow: 0 18px 34px rgba(15, 23, 42, 0.14) !important;
      }
    }
    .rp-doc-header { padding: 14pt 0 0 !important; margin-bottom: 14pt !important; }
    .rp-letterhead { grid-template-columns: 84pt 1fr 84pt !important; gap: 10pt !important; margin-left: 3% !important; margin-right: 3% !important; }
    .rp-letterhead-logo { width: 76pt !important; height: 76pt !important; }
    .rp-letterhead-center p { font-size: 13pt !important; font-weight: 400 !important; }
    .rp-letterhead-center .rp-letterhead-rep { font-size: 15pt !important; font-weight: 800 !important; }
    .rp-letterhead-center .rp-letterhead-barangay { font-size: 21pt !important; font-weight: 800 !important; margin-top: 8pt !important; }
    .rp-letterhead-line { margin: 8pt 3% 0 !important; border-color: #4b5563 !important; }
    .rp-doc-header .rp-report-title { font-size: 17px !important; color: #000 !important; font-weight: 800 !important; }
    .rp-doc-header .rp-period { font-size: 12px !important; font-weight: 400 !important; margin-top: 6px !important; }
    .rp-section {
      margin-top: 14pt !important;
      page-break-inside: auto !important;
      break-inside: auto !important;
    }
    .rp-section-label {
      font-size: 9pt !important;
      padding: 3pt 8pt !important;
      page-break-after: avoid !important;
      break-after: avoid-page !important;
    }
    .rp-summary td, .rp-table th, .rp-table td { font-size: 9pt !important; padding: 4pt 6pt !important; }
    .rp-table th { white-space: normal !important; word-break: break-word !important; vertical-align: bottom !important; }
    .rp-table--issuance-breakdown { table-layout: fixed !important; width: 100% !important; }
    .rp-table--issuance-breakdown th,
    .rp-table--issuance-breakdown td { font-size: 6.5pt !important; padding: 2pt 2pt !important; white-space: normal !important; word-break: break-word !important; overflow-wrap: break-word !important; vertical-align: top !important; }
    .rp-breakdown-document-type { width: 20% !important; }
    .rp-table tr { page-break-inside: avoid; }
    .rp-two-col, .rp-three-col, .rp-chart-grid {
      display: block !important;
    }
    .rp-two-col > *,
    .rp-three-col > *,
    .rp-chart-grid > * {
      margin-top: 14pt !important;
    }
    .rp-two-col > *:first-child,
    .rp-three-col > *:first-child,
    .rp-chart-grid > *:first-child {
      margin-top: 0 !important;
    }
    .rp-two-col > div,
    .rp-three-col > div,
    .rp-chart-card {
      page-break-inside: avoid !important;
      break-inside: avoid-page !important;
    }
    .rp-chart-card { page-break-inside: avoid; break-inside: avoid; }
    .rp-chart-wrap { min-height: 180pt; }
    .rp-footer { margin-top: 24pt !important; }
    .rp-sig-grid { gap: 32pt !important; }
    <?php endif; ?>
  </style>
</head>
<?php if ($isPrintView): ?>
<body>
<?php else: /* ── Full admin layout ── */ ?>
<body>
<div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
      <h2 class="mb-0" style="font-family: 'Charis SIL Bold'; color: #DE710C;">Reports</h2>
      <div class="d-flex gap-2 d-print-none">
        <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($baseUrl . '?module=' . rawurlencode($module), ENT_QUOTES, 'UTF-8') ?>">
          <i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Available Reports
        </a>
        <button class="btn btn-danger btn-sm" id="btnDownloadPdf" onclick="downloadPdf()">
          <i class="fas fa-file-pdf me-1"></i>Download PDF
        </button>
      </div>
    </div>
    <hr class="mb-4">

    <div class="bg-white p-4 rounded-4 shadow-sm border reports-shell">

      <!-- ── Controls: date filter ──────────────────────────────────────── -->
      <div class="rp-controls">
        <?php
          $hasTypeFilter = !empty($reportFilterOptions['type']);
          $hasAreaFilter = !empty($reportFilterOptions['area']);
          $hasSectorFilter = !empty($reportFilterOptions['sector']);
          $hasStatusFilter = !empty($reportFilterStatusOptions);
          $reportResetUrl = $baseUrl . '?module=' . rawurlencode($module);
          $screenReportFilters = [];
          if ($module !== 'residents') {
            $screenReportFilters[] = 'From: ' . rp_date_label($dateFrom);
            $screenReportFilters[] = 'To: ' . rp_date_label($dateTo);
          } else {
            $screenReportFilters[] = 'As of: ' . date('F j, Y');
          }
          if ($reportFilterTypes !== []) {
            $labels = array_values(array_map(
              static fn(string $key): string => $reportFilterOptions['type'][$key] ?? $key,
              $reportFilterTypes
            ));
            $screenReportFilters[] = $reportFilterLabels['type'] . ': ' . implode(', ', $labels);
          }
          if ($reportFilterAreas !== []) {
            $screenReportFilters[] = $reportFilterLabels['area'] . ': ' . implode(', ', $reportFilterAreas);
          }
          if ($reportFilterSectors !== []) {
            $screenReportFilters[] = $reportFilterLabels['sector'] . ': ' . implode(', ', $reportFilterSectors);
          }
          if ($issuanceModuleConfig !== null && $reportFilterStatuses !== []) {
            $labels = array_values(array_map(
              static fn(string $key): string => $reportFilterStatusOptions[$key] ?? ucfirst($key),
              $reportFilterStatuses
            ));
            $screenReportFilters[] = $reportFilterLabels['status'] . ': ' . implode(', ', $labels);
          }
          if ($reportChartType !== 'bar') {
            $screenReportFilters[] = 'Chart: ' . $reportChartTypeOptions[$reportChartType];
          }
          if ($isCustomLayoutActive) {
            $screenReportFilters[] = 'Layout: Custom';
          }
          $selectedFilterCount = 0;
          $selectedFilterCount += count($reportFilterTypes);
          $selectedFilterCount += count($reportFilterAreas);
          $selectedFilterCount += count($reportFilterSectors);
          if ($issuanceModuleConfig !== null) {
            $selectedFilterCount += count($reportFilterStatuses);
          }
          $selectedCustomizationCount = $hiddenLayoutCount;
        ?>
        <div class="rp-controls-bar">
          <div class="rp-controls-summary">
            <?php foreach ($screenReportFilters as $summaryItem): ?>
            <span class="rp-filter-chip"><?= htmlspecialchars($summaryItem) ?></span>
            <?php endforeach; ?>
          </div>
          <div class="rp-controls-actions d-print-none">
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#reportFilterModal">
              <i class="fas fa-filter me-1"></i>Filter
              <?php if ($selectedFilterCount > 0): ?>
              <span class="rp-filter-count ms-1"><?= $selectedFilterCount ?></span>
              <?php endif; ?>
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#reportCustomizeModal">
              <i class="fas fa-sliders-h me-1"></i>Customize
              <?php if ($selectedCustomizationCount > 0): ?>
              <span class="rp-filter-count ms-1"><?= $selectedCustomizationCount ?></span>
              <?php endif; ?>
            </button>
            <a href="<?= htmlspecialchars($reportResetUrl) ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
          </div>
        </div>
      </div><!-- /.rp-controls -->

      <div class="modal fade rp-filter-modal" id="reportFilterModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
          <div class="modal-content">
            <form method="GET" class="rp-filter-form">
              <div class="modal-header">
                <div>
                  <h5 class="modal-title mb-0">Report Filters</h5>
                  <p class="text-muted small mb-0 mt-1">Adjust the report date range and filter values for <?= htmlspecialchars($currentLabel) ?>.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="module" value="<?= htmlspecialchars($module) ?>">
                <?php rp_render_hidden_input('chart_type', $reportChartType); ?>
                <?php rp_render_hidden_inputs('show_section', $visibleReportSections); ?>
                <?php rp_render_hidden_inputs('show_column', $visibleReportColumns); ?>
                <div class="rp-filter-grid mb-3">
                  <?php if ($module !== 'residents'): ?>
                  <div>
                    <label class="form-label small fw-semibold mb-1">From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
                  </div>
                  <div>
                    <label class="form-label small fw-semibold mb-1">To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
                  </div>
                  <?php endif; ?>
                </div>
                <div class="rp-checklist-grid">
                  <?php if ($hasTypeFilter): ?>
                  <div class="rp-checklist-group">
                    <label class="rp-checklist-label"><?= htmlspecialchars($reportFilterLabels['type']) ?></label>
                    <div class="rp-checklist-list">
                      <?php foreach ($reportFilterOptions['type'] as $value => $label): ?>
                      <label class="d-flex align-items-center gap-2">
                        <input class="form-check-input m-0" type="checkbox" name="filter_type[]" value="<?= htmlspecialchars($value) ?>" <?= in_array((string)$value, $filterModalTypes, true) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($label) ?></span>
                      </label>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <?php endif; ?>
                  <?php if ($hasAreaFilter): ?>
                  <div class="rp-checklist-group">
                    <label class="rp-checklist-label"><?= htmlspecialchars($reportFilterLabels['area']) ?></label>
                    <div class="rp-checklist-list">
                      <?php foreach ($reportFilterOptions['area'] as $value => $label): ?>
                      <label class="d-flex align-items-center gap-2">
                        <input class="form-check-input m-0" type="checkbox" name="filter_area[]" value="<?= htmlspecialchars($value) ?>" <?= in_array((string)$value, $filterModalAreas, true) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($label) ?></span>
                      </label>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <?php endif; ?>
                  <?php if ($hasSectorFilter): ?>
                  <div class="rp-checklist-group">
                    <label class="rp-checklist-label"><?= htmlspecialchars($reportFilterLabels['sector']) ?></label>
                    <div class="rp-checklist-list">
                      <?php foreach ($reportFilterOptions['sector'] as $value => $label): ?>
                      <label class="d-flex align-items-center gap-2">
                        <input class="form-check-input m-0" type="checkbox" name="filter_sector[]" value="<?= htmlspecialchars($value) ?>" <?= in_array((string)$value, $filterModalSectors, true) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($label) ?></span>
                      </label>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <?php endif; ?>
                  <?php if ($hasStatusFilter): ?>
                  <div class="rp-checklist-group">
                    <label class="rp-checklist-label"><?= htmlspecialchars($reportFilterLabels['status']) ?></label>
                    <div class="rp-checklist-list">
                      <?php foreach ($reportFilterStatusOptions as $value => $label): ?>
                      <label class="d-flex align-items-center gap-2">
                        <input class="form-check-input m-0" type="checkbox" name="filter_status[]" value="<?= htmlspecialchars($value) ?>" <?= in_array((string)$value, $filterModalStatuses, true) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($label) ?></span>
                      </label>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
              <div class="modal-footer">
                <a href="<?= htmlspecialchars($reportResetUrl) ?>" class="btn btn-outline-secondary me-auto">Reset</a>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i>Apply Filters</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="modal fade rp-filter-modal" id="reportCustomizeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
          <div class="modal-content">
            <form method="GET" class="rp-filter-form">
              <div class="modal-header">
                <div>
                  <h5 class="modal-title mb-0">Customize Report</h5>
                  <p class="text-muted small mb-0 mt-1">Choose which sections and column groups should appear in <?= htmlspecialchars($currentLabel) ?>.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="module" value="<?= htmlspecialchars($module) ?>">
                <?php if ($module !== 'residents'): ?>
                <?php rp_render_hidden_input('date_from', $dateFrom); ?>
                <?php rp_render_hidden_input('date_to', $dateTo); ?>
                <?php endif; ?>
                <?php rp_render_hidden_inputs('filter_type', $reportFilterTypes); ?>
                <?php rp_render_hidden_inputs('filter_area', $reportFilterAreas); ?>
                <?php rp_render_hidden_inputs('filter_sector', $reportFilterSectors); ?>
                <?php rp_render_hidden_inputs('filter_status', $reportFilterStatuses); ?>

                <div class="rp-checklist-grid">
                  <div class="rp-checklist-group">
                    <label class="rp-checklist-label">Sections</label>
                    <div class="rp-checklist-list">
                      <?php foreach ($reportCustomizeConfig['sections'] as $value => $label): ?>
                      <label class="d-flex align-items-center gap-2">
                        <input class="form-check-input m-0" type="checkbox" name="show_section[]" value="<?= htmlspecialchars($value) ?>" data-customize-section-toggle="<?= htmlspecialchars($value) ?>" <?= in_array((string)$value, $visibleReportSections, true) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($label) ?></span>
                      </label>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <div class="rp-checklist-group" data-customize-column-panel>
                    <label class="rp-checklist-label">Column Groups</label>
                    <div class="rp-checklist-subgroups">
                      <?php foreach ($reportCustomizeColumnGroups as $group): ?>
                      <?php
                        $groupSections = array_values(array_filter(array_map(
                            static fn($section): string => trim((string)$section),
                            (array)($group['sections'] ?? [])
                        ), static fn(string $section): bool => $section !== ''));
                        $groupIsVisible = $groupSections === [] || array_intersect($groupSections, $visibleReportSections) !== [];
                      ?>
                      <div class="rp-checklist-subgroup" data-customize-column-group data-section-keys="<?= htmlspecialchars(implode(' ', $groupSections)) ?>" <?= $groupIsVisible ? '' : 'hidden' ?>>
                        <span class="rp-checklist-sublabel"><?= htmlspecialchars((string)($group['label'] ?? 'Column Group')) ?></span>
                        <div class="rp-checklist-list">
                          <?php foreach ((array)($group['columns'] ?? []) as $value => $label): ?>
                          <label class="d-flex align-items-center gap-2">
                            <input class="form-check-input m-0" type="checkbox" name="show_column[]" value="<?= htmlspecialchars((string)$value) ?>" data-customize-column-value="<?= htmlspecialchars((string)$value) ?>" <?= in_array((string)$value, $visibleReportColumns, true) ? 'checked' : '' ?> <?= $groupIsVisible ? '' : 'disabled' ?>>
                            <span><?= htmlspecialchars((string)$label) ?></span>
                          </label>
                          <?php endforeach; ?>
                        </div>
                      </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <?php if ($issuanceModuleConfig !== null): ?>
                  <div class="rp-checklist-group">
                    <label class="rp-checklist-label">Chart Type</label>
                    <div class="d-flex flex-wrap gap-3">
                      <?php foreach ($reportChartTypeOptions as $value => $label): ?>
                      <label class="d-flex align-items-center gap-2">
                        <input class="form-check-input m-0" type="radio" name="chart_type" value="<?= htmlspecialchars($value) ?>" <?= $reportChartType === $value ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($label) ?></span>
                      </label>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
              <div class="modal-footer">
                <a href="<?= htmlspecialchars($reportFilterStateUrl) ?>" class="btn btn-outline-secondary me-auto">Default Layout</a>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-sliders-h me-1"></i>Apply Layout</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════ REPORT DOCUMENT -->
      <div id="reportPrintArea">
<?php endif; /* end admin/print split — rp-doc content is shared below */ ?>
<div class="rp-doc">

        <!-- Document header -->
        <div class="rp-doc-header">
          <div class="rp-letterhead">
            <img class="rp-letterhead-logo" src="<?= htmlspecialchars($reportLeftLogo) ?>" alt="Barangay San Jose Logo">
            <div class="rp-letterhead-center">
              <p class="rp-letterhead-rep">REPUBLIKA NG PILIPINAS</p>
              <p>LALAWIGAN NG RIZAL</p>
              <p>BAYAN NG RODRIGUEZ</p>
              <p class="rp-letterhead-barangay">BARANGAY SAN JOSE</p>
            </div>
            <img class="rp-letterhead-logo" src="<?= htmlspecialchars($reportRightLogo) ?>" alt="Montalban Logo" onerror="this.onerror=null;this.src='<?= htmlspecialchars($reportLeftLogo) ?>';">
          </div>
          <div class="rp-letterhead-line"></div>
          <div class="rp-report-title"><?= htmlspecialchars(strtoupper((string)preg_replace('/\s+Report$/i', '', $currentLabel))) ?> STATISTICAL REPORT</div>
          <?php if ($module !== 'residents'): ?>
          <div class="rp-period">
            For the period: <strong><?= rp_date_label($dateFrom) ?></strong>
            &nbsp;to&nbsp;
            <strong><?= rp_date_label($dateTo) ?></strong>
          </div>
          <?php else: ?>
          <div class="rp-period">As of <strong><?= date('F j, Y') ?></strong></div>
          <?php endif; ?>
          <?php if (!empty($activeReportFilters)): ?>
          <div class="rp-filter-summary">
            Filters: <strong><?= htmlspecialchars(implode(' | ', $activeReportFilters)) ?></strong>
          </div>
          <?php endif; ?>
        </div>

<?php
// ══════════════════════════════════════════════════════════════════════════════
// RENDER: ISSUANCE REPORTS
// ══════════════════════════════════════════════════════════════════════════════
$shouldLoadIssuanceCharts = false;
$shouldLoadFinancialCharts = false;
$shouldLoadResidentCharts = false;
$shouldLoadAppointmentCharts = false;
$shouldLoadBlotterCharts = false;
$shouldLoadComplaintCharts = false;
$financialTypeRevenueChartData = [];
$financialAreaRevenueChartData = [];
$residentGenderChartData = [];
$residentAgeChartData = [];
$residentAreaChartData = [];
$residentHouseholdChartData = [];
$residentSectorChartData = [];
$residentMonthlyChartData = [];
$appointmentStatusChartData = [];
$appointmentPurposeChartData = [];
$appointmentAreaChartData = [];
$appointmentSectorChartData = [];
$appointmentTrendChartData = [];
$blotterTypeChartData = [];
$blotterStatusChartData = [];
$blotterAreaChartData = [];
$blotterSectorChartData = [];
$blotterTrendChartData = [];
$complaintTypeChartData = [];
$complaintOriginChartData = [];
$complaintKindChartData = [];
$complaintAreaChartData = [];
$complaintSectorChartData = [];
$complaintTrendChartData = [];
if ($issuanceModuleConfig !== null):
  $issuanceSummary = $issuanceReport['summary'] ?? [];
  $issuanceRows = $issuanceReport['rows'] ?? [];
  $issuanceBreakdown = $issuanceReport['breakdown'] ?? [];
  $issuanceRevenue = $issuanceReport['revenue'] ?? [];
  $issuanceChannels = $issuanceReport['channels'] ?? [];
  $issuanceTrend = $issuanceReport['trend'] ?? [];
  $issuanceTotal = (int)($issuanceSummary['total'] ?? 0);
  $officialAreas = array_keys($officialReportAreaOptions);
  $officialSectors = array_keys($officialReportSectorOptions);
  $showIssuanceBreakdownSectors = (bool)($issuanceModuleConfig['show_breakdown_sectors'] ?? true);
  $breakdownSectors = $showIssuanceBreakdownSectors ? $officialSectors : [];
  $breakdownTotals = [
    'areas' => array_fill_keys($officialAreas, 0),
    'sectors' => array_fill_keys($breakdownSectors, 0),
    'revenue' => 0.0,
    'total' => 0,
  ];
  foreach ($issuanceBreakdown as $row) {
    foreach ($officialAreas as $areaKey) {
      $breakdownTotals['areas'][$areaKey] += (int)($row['areas'][$areaKey] ?? 0);
    }
    foreach ($breakdownSectors as $sectorKey) {
      $breakdownTotals['sectors'][$sectorKey] += (int)($row['sectors'][$sectorKey] ?? 0);
    }
    $breakdownTotals['revenue'] += (float)($row['revenue'] ?? 0);
    $breakdownTotals['total'] += (int)($row['total'] ?? 0);
  }
  $channelTotals = ['walkin' => 0, 'online' => 0, 'total' => 0];
  foreach ($issuanceChannels as $row) {
    $channelTotals['walkin'] += (int)($row['walkin'] ?? 0);
    $channelTotals['online'] += (int)($row['online'] ?? 0);
    $channelTotals['total'] += (int)($row['total'] ?? 0);
  }
  $trendTotals = ['total' => 0, 'completed' => 0, 'pending' => 0, 'rejected' => 0, 'revenue' => 0.0];
  foreach ($issuanceTrend as $row) {
    $trendTotals['total'] += (int)($row['total'] ?? 0);
    $trendTotals['completed'] += (int)($row['completed'] ?? 0);
    $trendTotals['pending'] += (int)($row['pending'] ?? 0);
    $trendTotals['rejected'] += (int)($row['rejected'] ?? 0);
    $trendTotals['revenue'] += (float)($row['revenue'] ?? 0);
  }
  $revenueAccountingTotals = ['total' => 0, 'paid' => 0, 'free_exempt' => 0, 'revenue' => 0.0];
  foreach ($issuanceRevenue as $row) {
    $revenueAccountingTotals['total'] += (int)($row['total'] ?? 0);
    $revenueAccountingTotals['paid'] += (int)($row['paid'] ?? 0);
    $revenueAccountingTotals['free_exempt'] += (int)($row['free_exempt'] ?? 0);
    $revenueAccountingTotals['revenue'] += (float)($row['revenue'] ?? 0);
  }
  $issuanceAreaTotals = [];
  foreach ($officialAreas as $areaKey) {
    $issuanceAreaTotals[$areaKey] = [
      'area_label' => $areaKey,
      'total' => 0,
      'revenue' => 0.0,
    ];
  }
  $issuanceAreaTotals['Unspecified'] = [
    'area_label' => 'Unspecified',
    'total' => 0,
    'revenue' => 0.0,
  ];
  foreach ($issuanceRows as $row) {
    $areaLabel = trim((string)($row['area_number'] ?? ''));
    $areaLabel = $areaLabel !== '' ? $areaLabel : 'Unspecified';
    if (!isset($issuanceAreaTotals[$areaLabel])) {
      $issuanceAreaTotals[$areaLabel] = [
        'area_label' => $areaLabel,
        'total' => 0,
        'revenue' => 0.0,
      ];
    }
    $issuanceAreaTotals[$areaLabel]['total']++;
    $issuanceAreaTotals[$areaLabel]['revenue'] += (float)($row['revenue'] ?? 0);
  }
  $issuanceAreaTotals = array_values(array_filter($issuanceAreaTotals, static function (array $row) use ($officialAreas): bool {
    $areaLabel = (string)($row['area_label'] ?? '');
    return in_array($areaLabel, $officialAreas, true) || (int)($row['total'] ?? 0) > 0;
  }));

  $issuanceTypeTotals = [];
  foreach ($issuanceRevenue as $row) {
    $typeTotal = (int)($row['completed'] ?? 0) + (int)($row['pending'] ?? 0) + (int)($row['rejected'] ?? 0);
    $issuanceTypeTotals[] = [
      'request_type_label' => (string)($row['request_type_label'] ?? ''),
      'total' => $typeTotal,
      'revenue' => (float)($row['revenue'] ?? 0),
    ];
  }
  $issuanceStatusTotalsByKey = [
    'completed' => [
      'status_label' => 'Completed',
      'total' => (int)($issuanceSummary['completed'] ?? 0),
    ],
    'pending' => [
      'status_label' => 'Pending',
      'total' => (int)($issuanceSummary['pending'] ?? 0),
    ],
    'rejected' => [
      'status_label' => 'Rejected',
      'total' => (int)($issuanceSummary['rejected'] ?? 0),
    ],
  ];
  $issuanceStatusTotals = [];
  foreach ($filterModalStatuses as $statusKey) {
    if (isset($issuanceStatusTotalsByKey[$statusKey])) {
      $issuanceStatusTotals[] = $issuanceStatusTotalsByKey[$statusKey];
    }
  }
  $showIssuanceStatusAnalysis = array_intersect(['pending', 'rejected'], $filterModalStatuses) !== [];
  $issuanceChannelSummary = [
    [
      'channel_label' => 'Walk-in',
      'total' => (int)($issuanceSummary['walkin'] ?? 0),
    ],
    [
      'channel_label' => 'Online',
      'total' => (int)($issuanceSummary['online'] ?? 0),
    ],
  ];
  $issuanceStatusChartData = $showIssuanceStatusAnalysis ? $issuanceStatusTotals : [];
  $issuanceChannelChartData = array_values(array_filter($issuanceChannelSummary, static function (array $row): bool {
    return (int)($row['total'] ?? 0) > 0;
  }));
  $issuanceAreaChartData = array_values(array_filter($issuanceAreaTotals, static function (array $row): bool {
    return (int)($row['total'] ?? 0) > 0;
  }));
  $issuanceTypeChartData = array_values(array_filter($issuanceTypeTotals, static function (array $row): bool {
    return (int)($row['total'] ?? 0) > 0;
  }));
  $shouldLoadIssuanceCharts = $showReportSection('charts') && (
    $issuanceAreaChartData !== []
    || $issuanceTypeChartData !== []
    || $issuanceStatusChartData !== []
    || $issuanceChannelChartData !== []
  );
  $issuanceSectionLabel = static fn(string $key): string => rp_section_heading($reportCustomizeConfig['sections'] ?? [], $visibleReportSections, $key);
  $showIssuanceRevenue = $showReportSection('revenue');
?>
        <?php if ($showReportSection('summary')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($issuanceSectionLabel('summary')) ?></div>
          <table class="rp-summary">
            <tbody>
              <tr>
                <td><?= htmlspecialchars('Total ' . ($issuanceModuleConfig['summary_label'] ?? 'Requests')) ?></td>
                <td><?= number_format($issuanceTotal) ?></td>
              </tr>
              <tr>
                <td>Walk-in Requests</td>
                <td><?= number_format((int)($issuanceSummary['walkin'] ?? 0)) ?> &nbsp;<span class="pct">(<?= rp_pct((int)($issuanceSummary['walkin'] ?? 0), $issuanceTotal) ?>)</span></td>
              </tr>
              <tr>
                <td>Online Requests</td>
                <td><?= number_format((int)($issuanceSummary['online'] ?? 0)) ?> &nbsp;<span class="pct">(<?= rp_pct((int)($issuanceSummary['online'] ?? 0), $issuanceTotal) ?>)</span></td>
              </tr>
              <?php if ($showIssuanceRevenue): ?>
              <tr>
                <td>Total Revenue Collected</td>
                <td>&#8369;<?= number_format((float)($issuanceSummary['revenue'] ?? 0), 2) ?></td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('breakdown')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($issuanceSectionLabel('breakdown')) ?></div>
          <?php if ($issuanceBreakdown === []): ?>
            <p class="rp-empty">No breakdown data is available for the selected filters.</p>
          <?php else: ?>
          <table class="rp-table rp-table--issuance-breakdown">
            <thead>
              <tr>
                <th class="rp-breakdown-document-type <?= htmlspecialchars(trim($reportColumnClass('breakdown_document_type'))) ?>">Document Type</th>
                <?php foreach ($officialAreas as $areaKey): ?>
                <th class="text-center<?= htmlspecialchars($reportBreakdownAreaClass($areaKey)) ?>"><?= htmlspecialchars($areaKey) ?></th>
                <?php endforeach; ?>
                <?php foreach ($breakdownSectors as $sectorKey): ?>
                <th class="text-center<?= htmlspecialchars($reportBreakdownSectorClass($sectorKey)) ?>" title="<?= htmlspecialchars($sectorKey) ?>"><?= htmlspecialchars(rp_breakdown_sector_header_label($sectorKey)) ?></th>
                <?php endforeach; ?>
                <?php if ($showIssuanceRevenue): ?>
                <th class="text-end<?= htmlspecialchars($reportColumnClass('breakdown_revenue')) ?>">Revenue</th>
                <?php endif; ?>
                <th class="text-center<?= htmlspecialchars($reportColumnClass('breakdown_total')) ?>">Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($issuanceBreakdown as $row): ?>
              <tr>
                <td class="rp-breakdown-document-type <?= htmlspecialchars(trim($reportColumnClass('breakdown_document_type'))) ?>"><?= htmlspecialchars((string)($row['request_type_label'] ?? '')) ?></td>
                <?php foreach ($officialAreas as $areaKey): ?>
                <td class="text-center<?= htmlspecialchars($reportBreakdownAreaClass($areaKey)) ?>"><?= number_format((int)($row['areas'][$areaKey] ?? 0)) ?></td>
                <?php endforeach; ?>
                <?php foreach ($breakdownSectors as $sectorKey): ?>
                <td class="text-center<?= htmlspecialchars($reportBreakdownSectorClass($sectorKey)) ?>"><?= number_format((int)($row['sectors'][$sectorKey] ?? 0)) ?></td>
                <?php endforeach; ?>
                <?php if ($showIssuanceRevenue): ?>
                <td class="text-end<?= htmlspecialchars($reportColumnClass('breakdown_revenue')) ?>">&#8369;<?= number_format((float)($row['revenue'] ?? 0), 2) ?></td>
                <?php endif; ?>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('breakdown_total')) ?>"><?= number_format((int)($row['total'] ?? 0)) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td class="rp-breakdown-document-type <?= htmlspecialchars(trim($reportColumnClass('breakdown_document_type'))) ?>"><strong>TOTAL</strong></td>
                <?php foreach ($officialAreas as $areaKey): ?>
                <td class="text-center<?= htmlspecialchars($reportBreakdownAreaClass($areaKey)) ?>"><?= number_format((int)($breakdownTotals['areas'][$areaKey] ?? 0)) ?></td>
                <?php endforeach; ?>
                <?php foreach ($breakdownSectors as $sectorKey): ?>
                <td class="text-center<?= htmlspecialchars($reportBreakdownSectorClass($sectorKey)) ?>"><?= number_format((int)($breakdownTotals['sectors'][$sectorKey] ?? 0)) ?></td>
                <?php endforeach; ?>
                <?php if ($showIssuanceRevenue): ?>
                <td class="text-end<?= htmlspecialchars($reportColumnClass('breakdown_revenue')) ?>">&#8369;<?= number_format((float)($breakdownTotals['revenue'] ?? 0), 2) ?></td>
                <?php endif; ?>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('breakdown_total')) ?>"><?= number_format((int)($breakdownTotals['total'] ?? 0)) ?></td>
              </tr>
            </tfoot>
          </table>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('charts')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($issuanceSectionLabel('charts')) ?></div>
          <?php if ($issuanceAreaChartData === [] && $issuanceTypeChartData === [] && $issuanceStatusChartData === [] && $issuanceChannelChartData === []): ?>
            <p class="rp-empty">No chart data is available for the selected filters.</p>
          <?php else: ?>
          <div class="rp-chart-grid">
            <div class="rp-chart-card">
              <div class="rp-subsection-title">Total Requests by Area Number</div>
              <div class="rp-chart-wrap">
                <canvas id="issuanceAreaTotalsChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of request totals by area number.</div>
            </div>
            <div class="rp-chart-card">
              <div class="rp-subsection-title">Total Requests by Document Type</div>
              <div class="rp-chart-wrap">
                <canvas id="issuanceTypeTotalsChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of request totals by document type.</div>
            </div>
            <?php if ($showIssuanceStatusAnalysis): ?>
            <div class="rp-chart-card">
              <div class="rp-subsection-title">Total Requests by Status</div>
              <div class="rp-chart-wrap">
                <canvas id="issuanceStatusTotalsChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of request totals by status.</div>
            </div>
            <?php endif; ?>
            <div class="rp-chart-card"<?= $showIssuanceStatusAnalysis ? '' : ' style="grid-column:1 / -1;"' ?>>
              <div class="rp-subsection-title">Total Requests by Channel</div>
              <div class="rp-chart-wrap">
                <canvas id="issuanceChannelTotalsChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of request totals by request channel.</div>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('tables')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($issuanceSectionLabel('tables')) ?></div>
          <?php if ($issuanceTotal <= 0): ?>
            <p class="rp-empty">No requests matched the selected filters.</p>
          <?php else: ?>
          <div class="rp-two-col">
            <div>
              <div class="rp-subsection-title">Total Requests by Area Number</div>
              <table class="rp-table">
                <thead>
                  <tr>
                    <th class="<?= htmlspecialchars(trim($reportColumnClass('area'))) ?>">Area Number</th>
                    <th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Total Requests</th>
                    <th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">%</th>
                    <?php if ($showIssuanceRevenue): ?>
                    <th class="text-end<?= htmlspecialchars($reportColumnClass('revenue')) ?>">Revenue</th>
                    <?php endif; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($issuanceAreaTotals as $areaRow): ?>
                  <tr>
                    <td class="<?= htmlspecialchars(trim($reportColumnClass('area'))) ?>"><?= htmlspecialchars((string)($areaRow['area_label'] ?? 'Unspecified')) ?></td>
                    <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)($areaRow['total'] ?? 0)) ?></td>
                    <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)($areaRow['total'] ?? 0), $issuanceTotal) ?></td>
                    <?php if ($showIssuanceRevenue): ?>
                    <td class="text-end<?= htmlspecialchars($reportColumnClass('revenue')) ?>">&#8369;<?= number_format((float)($areaRow['revenue'] ?? 0), 2) ?></td>
                    <?php endif; ?>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr>
                    <td class="<?= htmlspecialchars(trim($reportColumnClass('area'))) ?>"><strong>TOTAL</strong></td>
                    <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($issuanceTotal) ?></td>
                    <td class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">100%</td>
                    <?php if ($showIssuanceRevenue): ?>
                    <td class="text-end<?= htmlspecialchars($reportColumnClass('revenue')) ?>">&#8369;<?= number_format((float)($issuanceSummary['revenue'] ?? 0), 2) ?></td>
                    <?php endif; ?>
                  </tr>
                </tfoot>
              </table>
            </div>
            <div>
              <div class="rp-subsection-title">Total Requests by Document Type</div>
              <table class="rp-table">
                <thead>
                  <tr>
                    <th class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>">Document Type</th>
                    <th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Total Requests</th>
                    <th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">%</th>
                    <?php if ($showIssuanceRevenue): ?>
                    <th class="text-end<?= htmlspecialchars($reportColumnClass('revenue')) ?>">Revenue</th>
                    <?php endif; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($issuanceTypeTotals as $typeRow): ?>
                  <tr>
                    <td class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>"><?= htmlspecialchars((string)($typeRow['request_type_label'] ?? '')) ?></td>
                    <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)($typeRow['total'] ?? 0)) ?></td>
                    <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)($typeRow['total'] ?? 0), $issuanceTotal) ?></td>
                    <?php if ($showIssuanceRevenue): ?>
                    <td class="text-end<?= htmlspecialchars($reportColumnClass('revenue')) ?>">&#8369;<?= number_format((float)($typeRow['revenue'] ?? 0), 2) ?></td>
                    <?php endif; ?>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr>
                    <td class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>"><strong>TOTAL</strong></td>
                    <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($issuanceTotal) ?></td>
                    <td class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">100%</td>
                    <?php if ($showIssuanceRevenue): ?>
                    <td class="text-end<?= htmlspecialchars($reportColumnClass('revenue')) ?>">&#8369;<?= number_format((float)($issuanceSummary['revenue'] ?? 0), 2) ?></td>
                    <?php endif; ?>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <div class="rp-two-col" style="margin-top:18px;<?= $showIssuanceStatusAnalysis ? '' : 'grid-template-columns:1fr;' ?>">
            <?php if ($showIssuanceStatusAnalysis): ?>
            <div>
              <div class="rp-subsection-title">Total Requests by Status</div>
              <table class="rp-table">
                <thead>
                  <tr>
                    <th class="<?= htmlspecialchars(trim($reportColumnClass('status'))) ?>">Status</th>
                    <th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Total Requests</th>
                    <th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">%</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($issuanceStatusTotals as $statusRow): ?>
                  <tr>
                    <td class="<?= htmlspecialchars(trim($reportColumnClass('status'))) ?>"><?= htmlspecialchars((string)($statusRow['status_label'] ?? '')) ?></td>
                    <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)($statusRow['total'] ?? 0)) ?></td>
                    <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)($statusRow['total'] ?? 0), $issuanceTotal) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr>
                    <td class="<?= htmlspecialchars(trim($reportColumnClass('status'))) ?>"><strong>TOTAL</strong></td>
                    <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($issuanceTotal) ?></td>
                    <td class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">100%</td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <?php endif; ?>
            <div>
              <div class="rp-subsection-title">Total Requests by Channel</div>
              <table class="rp-table">
                <thead>
                  <tr>
                    <th class="<?= htmlspecialchars(trim($reportColumnClass('channel'))) ?>">Channel</th>
                    <th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Total Requests</th>
                    <th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">%</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($issuanceChannelSummary as $channelRow): ?>
                  <tr>
                    <td class="<?= htmlspecialchars(trim($reportColumnClass('channel'))) ?>"><?= htmlspecialchars((string)($channelRow['channel_label'] ?? '')) ?></td>
                    <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)($channelRow['total'] ?? 0)) ?></td>
                    <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)($channelRow['total'] ?? 0), $issuanceTotal) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr>
                    <td class="<?= htmlspecialchars(trim($reportColumnClass('channel'))) ?>"><strong>TOTAL</strong></td>
                    <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($issuanceTotal) ?></td>
                    <td class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">100%</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('channel')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($issuanceSectionLabel('channel')) ?></div>
          <table class="rp-table">
            <thead>
              <tr>
                <th class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>">Request Type</th>
                <th class="text-center<?= htmlspecialchars($reportColumnClass('channel')) ?>">Walk-in</th>
                <th class="text-center<?= htmlspecialchars($reportColumnClass('channel')) ?>">Online</th>
                <th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($issuanceChannels as $row): ?>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>"><?= htmlspecialchars((string)($row['request_type_label'] ?? '')) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('channel')) ?>"><?= number_format((int)($row['walkin'] ?? 0)) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('channel')) ?>"><?= number_format((int)($row['online'] ?? 0)) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)($row['total'] ?? 0)) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>"><strong>TOTAL</strong></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('channel')) ?>"><?= number_format((int)$channelTotals['walkin']) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('channel')) ?>"><?= number_format((int)$channelTotals['online']) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)$channelTotals['total']) ?></td>
              </tr>
            </tfoot>
          </table>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('revenue')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($issuanceSectionLabel('revenue')) ?></div>
          <table class="rp-table">
            <colgroup>
              <col style="width:38%">
              <col style="width:18%">
              <col style="width:12%">
              <col style="width:12%">
              <col style="width:20%">
            </colgroup>
            <thead>
              <tr>
                <th class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>">Request Type</th>
                <th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Total Requests</th>
                <th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Paid</th>
                <th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Free / Exempt</th>
                <th class="text-end">Amount Collected</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($issuanceRevenue as $row): ?>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>"><?= htmlspecialchars((string)($row['request_type_label'] ?? '')) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)($row['total'] ?? 0)) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)($row['paid'] ?? 0)) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)($row['free_exempt'] ?? 0)) ?></td>
                <td class="text-end">&#8369;<?= number_format((float)($row['revenue'] ?? 0), 2) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>"><strong>TOTAL</strong></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)$revenueAccountingTotals['total']) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)$revenueAccountingTotals['paid']) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)$revenueAccountingTotals['free_exempt']) ?></td>
                <td class="text-end">&#8369;<?= number_format((float)$revenueAccountingTotals['revenue'], 2) ?></td>
              </tr>
            </tfoot>
          </table>
          <p class="rp-chart-note">Free / Exempt includes requests whose effective fee is zero, such as qualified PWD, senior citizen, First Time Job Seeker, and inherently free document requests.</p>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('trend')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($issuanceSectionLabel('trend')) ?></div>
          <?php if ($issuanceTrend === []): ?>
            <p class="rp-empty">No trend data available for the selected filters.</p>
          <?php else: ?>
          <table class="rp-table">
            <thead>
              <tr>
                <th class="<?= htmlspecialchars(trim($reportColumnClass('date'))) ?>">Month</th>
                <th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($issuanceTrend as $row): ?>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('date'))) ?>"><?= htmlspecialchars(date('F Y', strtotime((string)$row['month'] . '-01'))) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)($row['total'] ?? 0)) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('date'))) ?>"><strong>TOTAL</strong></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)$trendTotals['total']) ?></td>
              </tr>
            </tfoot>
          </table>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('requesters')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($issuanceSectionLabel('requesters')) ?></div>
          <?php if ($issuanceRows === []): ?>
            <p class="rp-empty">No requester records matched the selected document and filters.</p>
          <?php else: ?>
          <table class="rp-table">
            <thead>
              <tr>
                <th class="<?= htmlspecialchars(trim($reportColumnClass('identifier'))) ?>">Request ID</th>
                <th class="<?= htmlspecialchars(trim($reportColumnClass('resident'))) ?>">Requester</th>
                <th class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>">Document</th>
                <th class="<?= htmlspecialchars(trim($reportColumnClass('date'))) ?>">Requested</th>
                <th class="<?= htmlspecialchars(trim($reportColumnClass('status'))) ?>">Status</th>
                <th class="text-center<?= htmlspecialchars($reportColumnClass('area')) ?>">Area</th>
                <th class="<?= htmlspecialchars(trim($reportColumnClass('channel'))) ?>">Channel</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($issuanceRows as $requesterRow): ?>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('identifier'))) ?>"><?= htmlspecialchars((string)($requesterRow['request_id'] ?? '')) ?></td>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('resident'))) ?>"><?= htmlspecialchars((string)($requesterRow['resident_name'] ?? 'Unavailable')) ?></td>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>"><?= htmlspecialchars((string)($requesterRow['request_type_label'] ?? '')) ?></td>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('date'))) ?>"><?= htmlspecialchars((string)($requesterRow['request_date_label'] ?? 'N/A')) ?></td>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('status'))) ?>"><?= htmlspecialchars((string)($requesterRow['status_label'] ?? '')) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('area')) ?>"><?= htmlspecialchars((string)(($requesterRow['area_number'] ?? '') ?: 'Unspecified')) ?></td>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('channel'))) ?>"><?= htmlspecialchars((string)($requesterRow['request_source'] ?? '')) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
        <?php endif; ?>

<?php
// ══════════════════════════════════════════════════════════════════════════════
// RENDER: FINANCIAL
// ══════════════════════════════════════════════════════════════════════════════
elseif ($module === 'financial'):
  $kpi = $fin['kpi'] ?? [];
  $financialVisibleSections = array_values(array_filter(
    $visibleReportSections,
    static function (string $sectionKey) use ($fin): bool {
      return !($sectionKey === 'or_log' && empty($fin['or_log']));
    }
  ));
  $showFinancialSection = static fn(string $key): bool => in_array($key, $financialVisibleSections, true);
  $financialTypeRevenueChartData = array_values(array_filter($fin['by_method'] ?? [], static function (array $row): bool {
    return (float)($row['amount'] ?? 0) > 0;
  }));
  $financialAreaRevenueChartData = array_values($fin['by_area'] ?? []);
  $shouldLoadFinancialCharts = $showFinancialSection('charts') && (
    $financialTypeRevenueChartData !== []
    || $financialAreaRevenueChartData !== []
  );
  $financialSectionLabel = static fn(string $key): string => rp_section_heading($reportCustomizeConfig['sections'] ?? [], $financialVisibleSections, $key);
?>
        <!-- I. Summary -->
        <?php if ($showFinancialSection('summary')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($financialSectionLabel('summary')) ?></div>
          <table class="rp-summary">
            <tbody>
              <tr><td>Total Collected Transactions</td><td><?= number_format((int)($kpi['total_issued']??0)) ?></td></tr>
              <tr><td>Total Collections</td><td>&#8369;<?= number_format((float)($kpi['total_collections']??0),2) ?></td></tr>
              <tr><td>&nbsp;&nbsp;&nbsp; ↳ GCash Collections</td><td>&#8369;<?= number_format((float)($kpi['gcash_total']??0),2) ?> &nbsp;<span class="pct">(<?= rp_pct((float)($kpi['gcash_total']??0),(float)($kpi['total_collections']??0)) ?>)</span></td></tr>
              <tr><td>&nbsp;&nbsp;&nbsp; ↳ Walk-in / Barangay Collections</td><td>&#8369;<?= number_format((float)($kpi['walkin_total']??0),2) ?> &nbsp;<span class="pct">(<?= rp_pct((float)($kpi['walkin_total']??0),(float)($kpi['total_collections']??0)) ?>)</span></td></tr>
              <tr><td>&nbsp;&nbsp;&nbsp; ↳ Unspecified / Manual Entry</td><td>&#8369;<?= number_format((float)($kpi['unspecified_total']??0),2) ?> &nbsp;<span class="pct">(<?= rp_pct((float)($kpi['unspecified_total']??0),(float)($kpi['total_collections']??0)) ?>)</span></td></tr>
              <tr><td>Official Receipts (OR) Issued</td><td><?= number_format((int)($kpi['or_count']??0)) ?></td></tr>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <?php if ($showFinancialSection('charts')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($financialSectionLabel('charts')) ?></div>
          <?php if ($financialTypeRevenueChartData === [] && $financialAreaRevenueChartData === []): ?>
            <p class="rp-empty">No revenue stream chart data for the selected period.</p>
          <?php else: ?>
          <div class="rp-chart-grid">
            <div class="rp-chart-card">
              <div class="rp-subsection-title">By Payment Type Revenue Stream</div>
              <div class="rp-chart-wrap">
                <canvas id="financialTypeRevenueChart"></canvas>
              </div>
              <div class="rp-chart-note">Bar graph view of revenue by payment type.</div>
            </div>
            <div class="rp-chart-card">
              <div class="rp-subsection-title">By Area Revenue Stream</div>
              <div class="rp-chart-wrap">
                <canvas id="financialAreaRevenueChart"></canvas>
              </div>
              <div class="rp-chart-note">Bar graph view of revenue by area.</div>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- II. By Payment Type -->
        <?php if ($showFinancialSection('type')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($financialSectionLabel('type')) ?></div>
          <?php if (empty($fin['by_method'])): ?>
            <p class="rp-empty">No data for the selected period.</p>
          <?php else: ?>
          <table class="rp-table">
            <thead><tr><th class="<?= htmlspecialchars(trim($reportColumnClass('payment'))) ?>">Payment Type</th><th class="text-end">Average Amount</th><th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">No. of Transactions</th><th class="text-end">Total</th></tr></thead>
            <tbody>
              <?php
              foreach ($fin['by_method'] as $r):
                $cnt = (int)$r['total'];
                $amt = (float)$r['amount'];
                $unitPrice = $cnt > 0 ? $amt / $cnt : 0.0;
              ?>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('payment'))) ?>"><?= htmlspecialchars(rp_payment_method_label((string)$r['method'])) ?></td>
                <td class="text-end">&#8369;<?= number_format($unitPrice, 2) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($cnt) ?></td>
                <td class="text-end">&#8369;<?= number_format($amt,2) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('payment'))) ?>"><strong>TOTAL</strong></td>
                <td class="text-end"></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)($kpi['total_issued']??0)) ?></td>
                <td class="text-end">&#8369;<?= number_format((float)($kpi['total_collections']??0),2) ?></td>
              </tr>
            </tfoot>
          </table>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- III. Payment Type Breakdown -->
        <?php if ($showFinancialSection('payment_method')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($financialSectionLabel('payment_method')) ?></div>
          <?php if (empty($fin['by_method'])): ?>
            <p class="rp-empty">No payment type data for this period.</p>
          <?php else: $methodCountTotal = array_sum(array_column($fin['by_method'], 'total')); ?>
          <table class="rp-table">
            <thead>
              <tr><th class="<?= htmlspecialchars(trim($reportColumnClass('payment'))) ?>">Payment Type</th><th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Transactions</th><th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">%</th><th class="text-end<?= htmlspecialchars($reportColumnClass('revenue')) ?>">Amount Collected</th></tr>
            </thead>
            <tbody>
              <?php foreach ($fin['by_method'] as $r): ?>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('payment'))) ?>"><?= htmlspecialchars(rp_payment_method_label((string)$r['method'])) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)$r['total']) ?></td>
                <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)$r['total'], $methodCountTotal) ?></td>
                <td class="text-end<?= htmlspecialchars($reportColumnClass('revenue')) ?>">&#8369;<?= number_format((float)$r['amount'], 2) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('payment'))) ?>"><strong>TOTAL</strong></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)($kpi['total_issued'] ?? 0)) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">100%</td>
                <td class="text-end<?= htmlspecialchars($reportColumnClass('revenue')) ?>">&#8369;<?= number_format((float)($kpi['total_collections'] ?? 0), 2) ?></td>
              </tr>
            </tfoot>
          </table>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- IV. Area + Department Breakdown -->
        <?php if ($showFinancialSection('area') || $showFinancialSection('sector')): ?>
        <div class="rp-two-col" style="margin-top:22px;">
          <?php if ($showFinancialSection('area')): ?>
          <div>
            <div class="rp-section-label"><?= htmlspecialchars($financialSectionLabel('area')) ?></div>
            <?php if (empty($fin['by_area'])): ?>
              <p class="rp-empty">No area-linked collection data.</p>
            <?php else: $financialAreaTotal = array_sum(array_column($fin['by_area'], 'total')); ?>
            <table class="rp-table">
              <thead><tr><th>Area</th><th class="text-center">Transactions</th><th class="text-center">%</th><th class="text-end">Revenue</th></tr></thead>
              <tbody>
                <?php foreach ($fin['by_area'] as $r): ?>
                <tr>
                  <td><?= htmlspecialchars((string)$r['area']) ?></td>
                  <td class="text-center"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center pct"><?= rp_pct((int)$r['total'], $financialAreaTotal) ?></td>
                  <td class="text-end">&#8369;<?= number_format((float)$r['amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr><td><strong>TOTAL</strong></td><td class="text-center"><?= number_format($financialAreaTotal) ?></td><td class="text-center">100%</td><td class="text-end">&#8369;<?= number_format((float)($kpi['total_collections'] ?? 0), 2) ?></td></tr></tfoot>
            </table>
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <?php if ($showFinancialSection('sector')): ?>
          <div>
            <div class="rp-section-label"><?= htmlspecialchars($financialSectionLabel('sector')) ?></div>
            <?php if (empty($fin['by_department'])): ?>
              <p class="rp-empty">No department-linked collection data.</p>
            <?php else: $financialDepartmentTotal = array_sum(array_column($fin['by_department'], 'total')); ?>
            <table class="rp-table">
              <thead><tr><th>Department</th><th class="text-center">Transactions</th><th class="text-center">%</th><th class="text-end">Revenue</th></tr></thead>
              <tbody>
                <?php foreach ($fin['by_department'] as $r): ?>
                <tr>
                  <td><?= htmlspecialchars((string)$r['department']) ?></td>
                  <td class="text-center"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center pct"><?= rp_pct((int)$r['total'], $financialDepartmentTotal) ?></td>
                  <td class="text-end">&#8369;<?= number_format((float)$r['amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr><td><strong>TOTAL</strong></td><td class="text-center"><?= number_format($financialDepartmentTotal) ?></td><td class="text-center">100%</td><td class="text-end">&#8369;<?= number_format(array_sum(array_column($fin['by_department'], 'amount')), 2) ?></td></tr></tfoot>
            </table>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- VI. Daily Collection Log -->
        <?php if ($showFinancialSection('daily')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($financialSectionLabel('daily')) ?></div>
          <?php if (empty($fin['daily_log'])): ?>
            <p class="rp-empty">No collections in this period.</p>
          <?php else: ?>
          <table class="rp-table">
            <thead>
              <tr><th class="<?= htmlspecialchars(trim($reportColumnClass('date'))) ?>">Date</th><th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Transactions</th><th class="text-end<?= htmlspecialchars($reportColumnClass('channel')) ?>">GCash</th><th class="text-end<?= htmlspecialchars($reportColumnClass('channel')) ?>">Walk-in</th><th class="text-end<?= htmlspecialchars($reportColumnClass('channel')) ?>">Unspecified</th><th class="text-end">Total</th></tr>
            </thead>
            <tbody>
              <?php foreach ($fin['daily_log'] as $r): ?>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('date'))) ?>"><?= htmlspecialchars(rp_date_label($r['collection_date'])) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)$r['count']) ?></td>
                <td class="text-end<?= htmlspecialchars($reportColumnClass('channel')) ?>">&#8369;<?= number_format((float)$r['gcash'],2) ?></td>
                <td class="text-end<?= htmlspecialchars($reportColumnClass('channel')) ?>">&#8369;<?= number_format((float)$r['walkin'],2) ?></td>
                <td class="text-end<?= htmlspecialchars($reportColumnClass('channel')) ?>">&#8369;<?= number_format((float)($r['unspecified'] ?? 0),2) ?></td>
                <td class="text-end"><strong>&#8369;<?= number_format((float)$r['total'],2) ?></strong></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('date'))) ?>"><strong>TOTAL</strong></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)($kpi['total_issued']??0)) ?></td>
                <td class="text-end<?= htmlspecialchars($reportColumnClass('channel')) ?>">&#8369;<?= number_format((float)($kpi['gcash_total']??0),2) ?></td>
                <td class="text-end<?= htmlspecialchars($reportColumnClass('channel')) ?>">&#8369;<?= number_format((float)($kpi['walkin_total']??0),2) ?></td>
                <td class="text-end<?= htmlspecialchars($reportColumnClass('channel')) ?>">&#8369;<?= number_format((float)($kpi['unspecified_total']??0),2) ?></td>
                <td class="text-end">&#8369;<?= number_format((float)($kpi['total_collections']??0),2) ?></td>
              </tr>
            </tfoot>
          </table>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- VII. OR Number Log -->
        <?php if ($showFinancialSection('or_log')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($financialSectionLabel('or_log')) ?></div>
          <table class="rp-table">
            <thead>
              <tr><th class="<?= htmlspecialchars(trim($reportColumnClass('identifier'))) ?>">#</th><th class="<?= htmlspecialchars(trim($reportColumnClass('identifier'))) ?>">OR Number</th><th class="<?= htmlspecialchars(trim($reportColumnClass('identifier'))) ?>">Cert./Ref.</th><th class="<?= htmlspecialchars(trim($reportColumnClass('resident'))) ?>">Resident</th><th class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>">Document Type</th><th class="<?= htmlspecialchars(trim($reportColumnClass('payment'))) ?>">Payment Type</th><th class="text-end<?= htmlspecialchars($reportColumnClass('revenue')) ?>">Amount</th><th class="<?= htmlspecialchars(trim($reportColumnClass('date'))) ?>">Transaction Date</th></tr>
            </thead>
            <tbody>
              <?php $i = 1; foreach ($fin['or_log'] as $r): ?>
              <tr>
                <td class="pct<?= htmlspecialchars($reportColumnClass('identifier')) ?>"><?= $i++ ?></td>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('identifier'))) ?>"><strong><?= htmlspecialchars($r['or_number']) ?></strong></td>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('identifier'))) ?>"><?= htmlspecialchars($r['certificate_number'] ?? '—') ?></td>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('resident'))) ?>"><?= htmlspecialchars($r['resident_name'] ?: '—') ?></td>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>"><?= htmlspecialchars(rp_document_type_label((string)$r['document_type'])) ?></td>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('payment'))) ?>"><?= htmlspecialchars($r['payment_method']) ?></td>
                <td class="text-end<?= htmlspecialchars($reportColumnClass('revenue')) ?>">&#8369;<?= number_format((float)$r['fee_amount'],2) ?></td>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('date'))) ?>"><?= htmlspecialchars($r['finance_decision_at'] ?? '') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('identifier'))) ?>"><strong>TOTAL</strong></td>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('identifier'))) ?>"></td>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('identifier'))) ?>"></td>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('resident'))) ?>"></td>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>"></td>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('payment'))) ?>"></td>
                <td class="text-end<?= htmlspecialchars($reportColumnClass('revenue')) ?>">&#8369;<?= number_format((float)($fin['or_log_total'] ?? 0),2) ?></td>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('date'))) ?>"></td>
              </tr>
            </tfoot>
          </table>
        </div>
        <?php endif; ?>

<?php
// ══════════════════════════════════════════════════════════════════════════════
// RENDER: RESIDENTS
// ══════════════════════════════════════════════════════════════════════════════
elseif ($module === 'residents'):
  $kpi   = $res['kpi'] ?? [];
  $total = (int)($kpi['total'] ?? 0);
  $ver   = (int)($kpi['verified'] ?? 0);
  $residentSectionLabel = static fn(string $key): string => rp_section_heading($reportCustomizeConfig['sections'] ?? [], $visibleReportSections, $key);
  $residentAreaRows = $res['by_area_complete'] ?? [];
  $residentAreaTotal = array_sum(array_column($residentAreaRows, 'total'));
  $residentSectorRows = $res['by_sector_rows'] ?? [];
  $residentSectorTotal = array_sum(array_column($residentSectorRows, 'total'));
  $residentEmploymentRows = $res['by_employment'] ?? [];
  $residentEmploymentTotal = array_sum(array_column($residentEmploymentRows, 'total'));
  $residentGenderRows = array_values(array_map(static function (array $row): array {
    return [
      'label' => ucfirst((string)($row['gender'] ?? 'Unspecified')),
      'total' => (int)($row['total'] ?? 0),
    ];
  }, $res['by_gender'] ?? []));
  $residentGenderTotal = array_sum(array_column($residentGenderRows, 'total'));
  $residentAgeRows = [];
  foreach (($res['age_buckets'] ?? []) as $label => $count) {
    $residentAgeRows[] = [
      'label' => $label . ' years',
      'total' => (int)$count,
    ];
  }
  $residentAgeTotal = array_sum(array_column($residentAgeRows, 'total'));
  $residentMonthlyRows = array_values(array_map(static function (array $row): array {
    $monthValue = (string)($row['month'] ?? '');
    return [
      'month' => $monthValue,
      'label' => $monthValue !== '' ? date('F Y', strtotime($monthValue . '-01')) : 'Unspecified',
      'total' => (int)($row['total'] ?? 0),
    ];
  }, $res['monthly_reg'] ?? []));
  $residentMonthlyTotal = array_sum(array_column($residentMonthlyRows, 'total'));
  $residentHouseholdTotal = (int)($res['household_kpi']['total'] ?? 0);
  $residentHouseholdRows = $res['household_by_area_complete'] ?? [];
  $residentHouseholdAreaTotal = array_sum(array_column($residentHouseholdRows, 'total'));
  $residentAreaChartData = $residentAreaTotal > 0 ? $residentAreaRows : [];
  $residentHouseholdChartData = $residentHouseholdAreaTotal > 0 ? array_values(array_filter($residentHouseholdRows, static function (array $row): bool {
    return (int)($row['total'] ?? 0) > 0;
  })) : [];
  $residentSectorChartData = array_values(array_filter($residentSectorRows, static function (array $row): bool {
    return (int)($row['total'] ?? 0) > 0;
  }));
  $residentEmploymentChartData = array_values(array_filter($residentEmploymentRows, static function (array $row): bool {
    return (int)($row['total'] ?? 0) > 0;
  }));
  $residentGenderChartData = array_values(array_filter($residentGenderRows, static function (array $row): bool {
    return (int)($row['total'] ?? 0) > 0;
  }));
  $residentAgeChartData = $residentAgeTotal > 0 ? array_values(array_filter($residentAgeRows, static function (array $row): bool {
    return (int)($row['total'] ?? 0) > 0;
  })) : [];
  $residentMonthlyChartData = array_values(array_filter($residentMonthlyRows, static function (array $row): bool {
    return (int)($row['total'] ?? 0) > 0;
  }));
  $residentSupportRow = static function (string $dataset, array $rows, string $labelKey, int $totalCount): array {
    $topLabel = 'No data';
    $topCount = 0;
    foreach ($rows as $row) {
      $count = (int)($row['total'] ?? 0);
      if ($count <= $topCount) {
        continue;
      }
      $topCount = $count;
      $topLabel = trim((string)($row[$labelKey] ?? '')) !== '' ? (string)$row[$labelKey] : 'No data';
    }

    return [
      'dataset' => $dataset,
      'group' => $topLabel,
      'count' => $topCount,
      'percentage' => rp_pct($topCount, $totalCount),
    ];
  };
  $residentSupportRows = [
    $residentSupportRow('Residents Breakdown', $residentAreaRows, 'area', $residentAreaTotal),
    $residentSupportRow('Household Data', $residentHouseholdRows, 'area', $residentHouseholdAreaTotal),
    $residentSupportRow('Sector Membership', $residentSectorRows, 'sector', $residentSectorTotal),
    $residentSupportRow('Employed and Unemployed', $residentEmploymentRows, 'employment', $residentEmploymentTotal),
    $residentSupportRow('Gender', $residentGenderRows, 'label', $residentGenderTotal),
    $residentSupportRow('Age Distribution', $residentAgeRows, 'label', $residentAgeTotal),
    $residentSupportRow('Monthly Registration Count', $residentMonthlyRows, 'label', $residentMonthlyTotal),
  ];
  $shouldLoadResidentCharts = $showReportSection('charts') && (
    $residentEmploymentChartData !== []
    || $residentAreaChartData !== []
    || $residentHouseholdChartData !== []
    || $residentGenderChartData !== []
    || $residentAgeChartData !== []
    || $residentSectorChartData !== []
    || $residentMonthlyChartData !== []
  );
?>
        <?php if ($showReportSection('summary')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($residentSectionLabel('summary')) ?></div>
          <table class="rp-summary">
            <tbody>
              <tr><td>Total Verified Residents</td><td><?= number_format($total) ?></td></tr>
              <tr><td>Total Verified Households (HOF)</td><td><?= number_format($residentHouseholdTotal) ?></td></tr>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('breakdown')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($residentSectionLabel('breakdown')) ?></div>
          <table class="rp-table">
            <thead>
              <tr>
                <th class="<?= htmlspecialchars(trim($reportColumnClass('area'))) ?>">Area</th>
                <th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Count</th>
                <th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">%</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($residentAreaRows as $row): ?>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('area'))) ?>"><?= htmlspecialchars((string)($row['area'] ?? 'Unspecified')) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)($row['total'] ?? 0)) ?></td>
                <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)($row['total'] ?? 0), $residentAreaTotal) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('area'))) ?>"><strong>TOTAL</strong></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($residentAreaTotal) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">100%</td>
              </tr>
            </tfoot>
          </table>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('household')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($residentSectionLabel('household')) ?></div>
          <table class="rp-summary" style="margin-bottom:18px;">
            <tbody>
              <tr><td>Total Verified Households (HOF)</td><td><?= number_format($residentHouseholdTotal) ?></td></tr>
            </tbody>
          </table>
          <table class="rp-table">
            <thead>
              <tr>
                <th class="<?= htmlspecialchars(trim($reportColumnClass('area'))) ?>">Area</th>
                <th class="<?= htmlspecialchars(trim($reportColumnClass('household'))) ?>">Household Metric</th>
                <th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Count</th>
                <th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">%</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($residentHouseholdRows as $row): ?>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('area'))) ?>"><?= htmlspecialchars((string)($row['area'] ?? 'Unspecified')) ?></td>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('household'))) ?>">Heads of Family</td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)($row['total'] ?? 0)) ?></td>
                <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)($row['total'] ?? 0), $residentHouseholdAreaTotal) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('area'))) ?>"><strong>TOTAL</strong></td>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('household'))) ?>">Heads of Family</td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($residentHouseholdAreaTotal) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">100%</td>
              </tr>
            </tfoot>
          </table>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('charts')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($residentSectionLabel('charts')) ?></div>
          <?php if ($residentGenderChartData === [] && $residentAgeChartData === [] && $residentAreaChartData === [] && $residentHouseholdChartData === [] && $residentSectorChartData === [] && $residentEmploymentChartData === [] && $residentMonthlyChartData === []): ?>
            <p class="rp-empty">No chart data is available for the selected filters.</p>
          <?php else: ?>
          <div class="rp-chart-grid">
            <div class="rp-chart-card">
              <h3>Residents Breakdown by Area</h3>
              <div class="rp-chart-wrap">
                <canvas id="residentAreaChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of verified residents by area.</div>
            </div>
            <?php if ($residentHouseholdChartData !== []): ?>
            <div class="rp-chart-card">
              <h3>Households by Area</h3>
              <div class="rp-chart-wrap">
                <canvas id="residentHouseholdChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of verified heads of family by area.</div>
            </div>
            <?php endif; ?>
            <?php if ($residentSectorChartData !== []): ?>
            <div class="rp-chart-card">
              <h3>Sector Membership</h3>
              <div class="rp-chart-wrap">
                <canvas id="residentSectorChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of verified residents by sector membership.</div>
            </div>
            <?php endif; ?>
            <?php if ($residentEmploymentChartData !== []): ?>
            <div class="rp-chart-card">
              <h3>Employed and Unemployed</h3>
              <div class="rp-chart-wrap">
                <canvas id="residentEmploymentChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of verified resident employment status.</div>
            </div>
            <?php endif; ?>
            <?php if ($residentGenderChartData !== []): ?>
            <div class="rp-chart-card">
              <h3>Gender</h3>
              <div class="rp-chart-wrap">
                <canvas id="residentGenderChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of verified residents by gender.</div>
            </div>
            <?php endif; ?>
            <?php if ($residentAgeChartData !== []): ?>
            <div class="rp-chart-card">
              <h3>Age Distribution</h3>
              <div class="rp-chart-wrap">
                <canvas id="residentAgeChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of verified residents by age group.</div>
            </div>
            <?php endif; ?>
            <?php if ($residentMonthlyChartData !== []): ?>
            <div class="rp-chart-card">
              <h3>Monthly Registration Count</h3>
              <div class="rp-chart-wrap">
                <canvas id="residentMonthlyChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of monthly verified resident registrations.</div>
            </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('tables')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($residentSectionLabel('tables')) ?></div>
          <table class="rp-table">
            <thead>
              <tr>
                <th class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>">Dataset</th>
                <th class="<?= htmlspecialchars(trim($reportColumnClass('group'))) ?>">Leading Group</th>
                <th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Count</th>
                <th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">%</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($residentSupportRows as $row): ?>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>"><?= htmlspecialchars((string)($row['dataset'] ?? '')) ?></td>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('group'))) ?>"><?= htmlspecialchars((string)($row['group'] ?? 'No data')) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)($row['count'] ?? 0)) ?></td>
                <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= htmlspecialchars((string)($row['percentage'] ?? '0.0%')) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('sector')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($residentSectionLabel('sector')) ?> (Verified)</div>
          <table class="rp-table">
            <thead>
              <tr>
                <th class="<?= htmlspecialchars(trim($reportColumnClass('sector'))) ?>">Sector</th>
                <th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Count</th>
                <th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">%</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($residentSectorRows as $row): ?>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('sector'))) ?>"><?= htmlspecialchars((string)($row['sector'] ?? 'Unspecified')) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)($row['total'] ?? 0)) ?></td>
                <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)($row['total'] ?? 0), $residentSectorTotal) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('sector'))) ?>"><strong>TOTAL</strong></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($residentSectorTotal) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">100%</td>
              </tr>
            </tfoot>
          </table>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('employment')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($residentSectionLabel('employment')) ?> (Verified)</div>
          <table class="rp-table">
            <thead>
              <tr>
                <th class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>">Employment Status</th>
                <th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Count</th>
                <th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">%</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($residentEmploymentRows as $row): ?>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>"><?= htmlspecialchars((string)($row['employment'] ?? 'Unspecified')) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)($row['total'] ?? 0)) ?></td>
                <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)($row['total'] ?? 0), $residentEmploymentTotal) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>"><strong>TOTAL</strong></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($residentEmploymentTotal) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">100%</td>
              </tr>
            </tfoot>
          </table>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('gender')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($residentSectionLabel('gender')) ?> (Verified)</div>
          <?php if ($residentGenderRows === []): ?>
            <p class="rp-empty">No data.</p>
          <?php else: ?>
          <table class="rp-table">
            <thead>
              <tr>
                <th class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>">Gender</th>
                <th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Count</th>
                <th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">%</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($residentGenderRows as $row): ?>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>"><?= htmlspecialchars((string)($row['label'] ?? 'Unspecified')) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)($row['total'] ?? 0)) ?></td>
                <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)($row['total'] ?? 0), $residentGenderTotal) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>"><strong>TOTAL</strong></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($residentGenderTotal) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">100%</td>
              </tr>
            </tfoot>
          </table>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('age')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($residentSectionLabel('age')) ?> (Verified)</div>
          <table class="rp-table">
            <thead>
              <tr>
                <th class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>">Age Group</th>
                <th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Count</th>
                <th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">%</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($residentAgeRows as $row): ?>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>"><?= htmlspecialchars((string)($row['label'] ?? '')) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)($row['total'] ?? 0)) ?></td>
                <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)($row['total'] ?? 0), $residentAgeTotal) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>"><strong>TOTAL</strong></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($residentAgeTotal) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">100%</td>
              </tr>
            </tfoot>
          </table>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('monthly')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($residentSectionLabel('monthly')) ?> (Verified, Last 12 Months)</div>
          <?php if ($residentMonthlyRows === []): ?>
            <p class="rp-empty">No data.</p>
          <?php else: ?>
          <table class="rp-table">
            <thead><tr><th class="<?= htmlspecialchars(trim($reportColumnClass('date'))) ?>">Month</th><th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">New Verified Residents</th></tr></thead>
            <tbody>
              <?php foreach ($residentMonthlyRows as $row): ?>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('date'))) ?>"><?= htmlspecialchars((string)($row['label'] ?? 'Unspecified')) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)($row['total'] ?? 0)) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td class="<?= htmlspecialchars(trim($reportColumnClass('date'))) ?>"><strong>TOTAL</strong></td><td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($residentMonthlyTotal) ?></td></tr></tfoot>
          </table>
          <?php endif; ?>
        </div>
        <?php endif; ?>

<?php
// ══════════════════════════════════════════════════════════════════════════════
// RENDER: APPOINTMENTS
// ══════════════════════════════════════════════════════════════════════════════
elseif ($module === 'appointments'):
  $kpi   = $appt['kpi'] ?? [];
  $total = (int)($kpi['total'] ?? 0);
  $appointmentSectionLabel = static fn(string $key): string => rp_section_heading($reportCustomizeConfig['sections'] ?? [], $visibleReportSections, $key);
  $appointmentStatusChartData = array_values(array_filter($appt['by_status'] ?? [], static function (array $row): bool {
    return (int)($row['total'] ?? 0) > 0;
  }));
  $appointmentPurposeChartData = array_values(array_filter($appt['by_purpose'] ?? [], static function (array $row): bool {
    return (int)($row['total'] ?? 0) > 0;
  }));
  $appointmentAreaChartData = array_values(array_filter($appt['by_area'] ?? [], static function (array $row): bool {
    return (int)($row['total'] ?? 0) > 0;
  }));
  $appointmentSectorChartData = array_values(array_filter($appt['by_sector'] ?? [], static function (array $row): bool {
    return (int)($row['total'] ?? 0) > 0;
  }));
  $appointmentTrendChartData = array_values(array_filter(array_map(static function (array $row): array {
    $monthValue = (string)($row['month'] ?? '');
    return [
      'label' => $monthValue !== '' ? date('F Y', strtotime($monthValue . '-01')) : 'Unspecified',
      'total' => (int)($row['total'] ?? 0),
    ];
  }, $appt['trend'] ?? []), static function (array $row): bool {
    return (int)($row['total'] ?? 0) > 0;
  }));
  $shouldLoadAppointmentCharts = $showReportSection('charts') && (
    $appointmentStatusChartData !== []
    || $appointmentPurposeChartData !== []
    || $appointmentAreaChartData !== []
    || $appointmentSectorChartData !== []
    || $appointmentTrendChartData !== []
  );
?>
        <?php if ($showReportSection('summary')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($appointmentSectionLabel('summary')) ?></div>
          <table class="rp-summary">
            <tbody>
              <tr><td>Total Appointment Requests</td><td><?= number_format($total) ?></td></tr>
              <tr><td>Completed</td><td><?= number_format((int)($kpi['completed']??0)) ?> &nbsp;<span class="pct">(<?= rp_pct((int)($kpi['completed']??0),$total) ?>)</span></td></tr>
              <tr><td>Scheduled / Confirmed</td><td><?= number_format((int)($kpi['scheduled']??0)) ?> &nbsp;<span class="pct">(<?= rp_pct((int)($kpi['scheduled']??0),$total) ?>)</span></td></tr>
              <tr><td>Pending</td><td><?= number_format((int)($kpi['pending']??0)) ?> &nbsp;<span class="pct">(<?= rp_pct((int)($kpi['pending']??0),$total) ?>)</span></td></tr>
              <tr><td>Cancelled</td><td><?= number_format((int)($kpi['cancelled']??0)) ?> &nbsp;<span class="pct">(<?= rp_pct((int)($kpi['cancelled']??0),$total) ?>)</span></td></tr>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('charts')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($appointmentSectionLabel('charts')) ?></div>
          <?php if ($appointmentStatusChartData === [] && $appointmentPurposeChartData === [] && $appointmentAreaChartData === [] && $appointmentSectorChartData === [] && $appointmentTrendChartData === []): ?>
            <p class="rp-empty">No chart data is available for the selected filters.</p>
          <?php else: ?>
          <div class="rp-chart-grid">
            <?php if ($appointmentStatusChartData !== []): ?>
            <div class="rp-chart-card">
              <div class="rp-subsection-title">Status Breakdown</div>
              <div class="rp-chart-wrap">
                <canvas id="appointmentStatusChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of appointment request status totals.</div>
            </div>
            <?php endif; ?>
            <?php if ($appointmentPurposeChartData !== []): ?>
            <div class="rp-chart-card">
              <div class="rp-subsection-title">Purpose Breakdown</div>
              <div class="rp-chart-wrap">
                <canvas id="appointmentPurposeChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of appointment request purposes.</div>
            </div>
            <?php endif; ?>
            <?php if ($appointmentAreaChartData !== []): ?>
            <div class="rp-chart-card">
              <div class="rp-subsection-title">Requests by Area</div>
              <div class="rp-chart-wrap">
                <canvas id="appointmentAreaChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of appointment requests by area.</div>
            </div>
            <?php endif; ?>
            <?php if ($appointmentSectorChartData !== []): ?>
            <div class="rp-chart-card">
              <div class="rp-subsection-title">Requests by Sector Membership</div>
              <div class="rp-chart-wrap">
                <canvas id="appointmentSectorChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of appointment requests by sector membership.</div>
            </div>
            <?php endif; ?>
            <?php if ($appointmentTrendChartData !== []): ?>
            <div class="rp-chart-card">
              <div class="rp-subsection-title">Monthly Trend</div>
              <div class="rp-chart-wrap">
                <canvas id="appointmentTrendChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of monthly appointment requests for the last 12 months.</div>
            </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('status') || $showReportSection('purpose')): ?>
        <div class="rp-two-col" style="margin-top:22px;">
          <?php if ($showReportSection('status')): ?>
          <div>
            <div class="rp-section-label"><?= htmlspecialchars($appointmentSectionLabel('status')) ?></div>
            <?php if (empty($appt['by_status'])): ?>
              <p class="rp-empty">No data.</p>
            <?php else: ?>
            <table class="rp-table">
              <thead><tr><th class="<?= htmlspecialchars(trim($reportColumnClass('status'))) ?>">Status</th><th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Count</th><th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">%</th></tr></thead>
              <tbody>
                <?php foreach ($appt['by_status'] as $r): ?>
                <tr>
                  <td class="<?= htmlspecialchars(trim($reportColumnClass('status'))) ?>"><?= htmlspecialchars($r['status']) ?></td>
                  <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)$r['total'],$total) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr><td class="<?= htmlspecialchars(trim($reportColumnClass('status'))) ?>"><strong>TOTAL</strong></td><td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($total) ?></td><td class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">100%</td></tr></tfoot>
            </table>
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <?php if ($showReportSection('purpose')): ?>
          <div>
            <div class="rp-section-label"><?= htmlspecialchars($appointmentSectionLabel('purpose')) ?> (Top 20)</div>
            <?php if (empty($appt['by_purpose'])): ?>
              <p class="rp-empty">No data.</p>
            <?php else: $purpTotal = array_sum(array_column($appt['by_purpose'],'total')); ?>
            <table class="rp-table">
              <thead><tr><th class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>">Purpose</th><th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Count</th><th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">%</th></tr></thead>
              <tbody>
                <?php foreach ($appt['by_purpose'] as $r): ?>
                <tr>
                  <td class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>"><?= htmlspecialchars($r['purpose']) ?></td>
                  <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)$r['total'],$total) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr><td class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>"><strong>TOTAL (shown)</strong></td><td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($purpTotal) ?></td><td class="<?= htmlspecialchars(trim($reportColumnClass('percentage'))) ?>"></td></tr></tfoot>
            </table>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('area') || $showReportSection('sector')): ?>
        <div class="rp-two-col" style="margin-top:22px;">
          <?php if ($showReportSection('area')): ?>
          <div>
            <div class="rp-section-label"><?= htmlspecialchars($appointmentSectionLabel('area')) ?></div>
            <?php if (empty($appt['by_area'])): ?>
              <p class="rp-empty">No area-linked appointment data.</p>
            <?php else: $appointmentAreaTotal = array_sum(array_column($appt['by_area'], 'total')); ?>
            <table class="rp-table">
              <thead><tr><th class="<?= htmlspecialchars(trim($reportColumnClass('area'))) ?>">Area</th><th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Count</th><th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">%</th></tr></thead>
              <tbody>
                <?php foreach ($appt['by_area'] as $r): ?>
                <tr>
                  <td class="<?= htmlspecialchars(trim($reportColumnClass('area'))) ?>"><?= htmlspecialchars((string)$r['area']) ?></td>
                  <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)$r['total'], $appointmentAreaTotal) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr><td class="<?= htmlspecialchars(trim($reportColumnClass('area'))) ?>"><strong>TOTAL</strong></td><td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($appointmentAreaTotal) ?></td><td class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">100%</td></tr></tfoot>
            </table>
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <?php if ($showReportSection('sector')): ?>
          <div>
            <div class="rp-section-label"><?= htmlspecialchars($appointmentSectionLabel('sector')) ?></div>
            <?php if (empty($appt['by_sector'])): ?>
              <p class="rp-empty">No sector-linked appointment data.</p>
            <?php else: $appointmentSectorTotal = array_sum(array_column($appt['by_sector'], 'total')); ?>
            <table class="rp-table">
              <thead><tr><th class="<?= htmlspecialchars(trim($reportColumnClass('sector'))) ?>">Sector</th><th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Count</th><th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">%</th></tr></thead>
              <tbody>
                <?php foreach ($appt['by_sector'] as $r): ?>
                <tr>
                  <td class="<?= htmlspecialchars(trim($reportColumnClass('sector'))) ?>"><?= htmlspecialchars((string)$r['sector']) ?></td>
                  <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)$r['total'], $appointmentSectorTotal) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr><td class="<?= htmlspecialchars(trim($reportColumnClass('sector'))) ?>"><strong>TOTAL</strong></td><td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($appointmentSectorTotal) ?></td><td class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">100%</td></tr></tfoot>
            </table>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('trend')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($appointmentSectionLabel('trend')) ?> (Last 12 Months)</div>
          <?php if (empty($appt['trend'])): ?>
            <p class="rp-empty">No trend data.</p>
          <?php else:
            $tTotal = array_sum(array_column($appt['trend'],'total'));
            $tComp  = array_sum(array_column($appt['trend'],'completed'));
          ?>
          <table class="rp-table">
            <thead><tr><th class="<?= htmlspecialchars(trim($reportColumnClass('date'))) ?>">Month</th><th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Total</th><th class="text-center<?= htmlspecialchars($reportColumnClass('result')) ?>">Completed</th><th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">Completion Rate</th></tr></thead>
              <tbody>
                <?php foreach ($appt['trend'] as $r): ?>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('date'))) ?>"><?= htmlspecialchars(date('F Y', strtotime($r['month'].'-01'))) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)$r['total']) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('result')) ?>"><?= number_format((int)$r['completed']) ?></td>
                <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)$r['completed'],(int)$r['total']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td class="<?= htmlspecialchars(trim($reportColumnClass('date'))) ?>"><strong>TOTAL</strong></td><td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($tTotal) ?></td><td class="text-center<?= htmlspecialchars($reportColumnClass('result')) ?>"><?= number_format($tComp) ?></td><td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct($tComp,$tTotal) ?></td></tr></tfoot>
          </table>
          <?php endif; ?>
        </div>
        <?php endif; ?>

<?php
// ══════════════════════════════════════════════════════════════════════════════
// RENDER: BLOTTER
// ══════════════════════════════════════════════════════════════════════════════
elseif ($module === 'blotter'):
  $kpi   = $blot['kpi'] ?? [];
  $total = (int)($kpi['total'] ?? 0);
  $blotterSectionLabel = static fn(string $key): string => rp_section_heading($reportCustomizeConfig['sections'] ?? [], $visibleReportSections, $key);
  $blotterTypeChartData = array_values(array_filter($blot['by_type'] ?? [], static function (array $row): bool {
    return (int)($row['total'] ?? 0) > 0;
  }));
  $blotterStatusChartData = array_values(array_filter($blot['by_status'] ?? [], static function (array $row): bool {
    return (int)($row['total'] ?? 0) > 0;
  }));
  $blotterAreaChartData = array_values(array_filter($blot['by_area'] ?? [], static function (array $row): bool {
    return (int)($row['total'] ?? 0) > 0;
  }));
  $blotterSectorChartData = array_values(array_filter($blot['by_sector'] ?? [], static function (array $row): bool {
    return (int)($row['total'] ?? 0) > 0;
  }));
  $blotterTrendChartData = array_values(array_filter(array_map(static function (array $row): array {
    $monthValue = (string)($row['month'] ?? '');
    return [
      'label' => $monthValue !== '' ? date('F Y', strtotime($monthValue . '-01')) : 'Unspecified',
      'total' => (int)($row['total'] ?? 0),
    ];
  }, $blot['trend'] ?? []), static function (array $row): bool {
    return (int)($row['total'] ?? 0) > 0;
  }));
  $shouldLoadBlotterCharts = $showReportSection('charts') && (
    $blotterTypeChartData !== []
    || $blotterStatusChartData !== []
    || $blotterAreaChartData !== []
    || $blotterSectorChartData !== []
    || $blotterTrendChartData !== []
  );
?>
        <?php if ($showReportSection('summary')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($blotterSectionLabel('summary')) ?></div>
          <table class="rp-summary">
            <tbody>
              <tr><td>Total Cases Filed</td><td><?= number_format($total) ?></td></tr>
              <tr><td>Active / Open Cases</td><td><?= number_format((int)($kpi['active']??0)) ?> &nbsp;<span class="pct">(<?= rp_pct((int)($kpi['active']??0),$total) ?>)</span></td></tr>
              <tr><td>Resolved / Closed / Settled</td><td><?= number_format((int)($kpi['resolved']??0)) ?> &nbsp;<span class="pct">(<?= rp_pct((int)($kpi['resolved']??0),$total) ?>)</span></td></tr>
              <tr><td>Blotter Cases</td><td><?= number_format((int)($kpi['blotter_count']??0)) ?></td></tr>
              <tr><td>Complaint Cases</td><td><?= number_format((int)($kpi['complaint_count']??0)) ?></td></tr>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('charts')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($blotterSectionLabel('charts')) ?></div>
          <?php if ($blotterTypeChartData === [] && $blotterStatusChartData === [] && $blotterAreaChartData === [] && $blotterSectorChartData === [] && $blotterTrendChartData === []): ?>
            <p class="rp-empty">No chart data is available for the selected filters.</p>
          <?php else: ?>
          <div class="rp-chart-grid">
            <?php if ($blotterTypeChartData !== []): ?>
            <div class="rp-chart-card">
              <div class="rp-subsection-title">Complaint Type Breakdown</div>
              <div class="rp-chart-wrap">
                <canvas id="blotterTypeChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of blotter cases by complaint type.</div>
            </div>
            <?php endif; ?>
            <?php if ($blotterStatusChartData !== []): ?>
            <div class="rp-chart-card">
              <div class="rp-subsection-title">Status Breakdown</div>
              <div class="rp-chart-wrap">
                <canvas id="blotterStatusChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of blotter case statuses.</div>
            </div>
            <?php endif; ?>
            <?php if ($blotterAreaChartData !== []): ?>
            <div class="rp-chart-card">
              <div class="rp-subsection-title">Cases by Area</div>
              <div class="rp-chart-wrap">
                <canvas id="blotterAreaChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of blotter cases by area.</div>
            </div>
            <?php endif; ?>
            <?php if ($blotterSectorChartData !== []): ?>
            <div class="rp-chart-card">
              <div class="rp-subsection-title">Cases by Sector Membership</div>
              <div class="rp-chart-wrap">
                <canvas id="blotterSectorChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of blotter cases by sector membership.</div>
            </div>
            <?php endif; ?>
            <?php if ($blotterTrendChartData !== []): ?>
            <div class="rp-chart-card">
              <div class="rp-subsection-title">Monthly Trend</div>
              <div class="rp-chart-wrap">
                <canvas id="blotterTrendChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of monthly blotter case filings for the last 12 months.</div>
            </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('type') || $showReportSection('status')): ?>
        <div class="rp-two-col" style="margin-top:22px;">
          <?php if ($showReportSection('type')): ?>
          <div>
            <div class="rp-section-label"><?= htmlspecialchars($blotterSectionLabel('type')) ?> (Top 20)</div>
            <?php if (empty($blot['by_type'])): ?>
              <p class="rp-empty">No data.</p>
            <?php else: ?>
            <table class="rp-table">
              <thead><tr><th class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>">Complaint Type</th><th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Total</th><th class="text-center<?= htmlspecialchars($reportColumnClass('result')) ?>">Resolved</th><th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">Res. Rate</th></tr></thead>
              <tbody>
                <?php foreach ($blot['by_type'] as $r): ?>
                <tr>
                  <td class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>"><?= htmlspecialchars($r['complaint_type']) ?></td>
                  <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center<?= htmlspecialchars($reportColumnClass('result')) ?>"><?= number_format((int)$r['resolved']) ?></td>
                  <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)$r['resolved'],(int)$r['total']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <?php if ($showReportSection('status')): ?>
          <div>
            <div class="rp-section-label"><?= htmlspecialchars($blotterSectionLabel('status')) ?></div>
            <?php if (empty($blot['by_status'])): ?>
              <p class="rp-empty">No data.</p>
            <?php else: ?>
            <table class="rp-table">
              <thead><tr><th class="<?= htmlspecialchars(trim($reportColumnClass('status'))) ?>">Status</th><th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Count</th><th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">%</th></tr></thead>
              <tbody>
                <?php foreach ($blot['by_status'] as $r): ?>
                <tr>
                  <td class="<?= htmlspecialchars(trim($reportColumnClass('status'))) ?>"><?= htmlspecialchars($r['status']) ?></td>
                  <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)$r['total'],$total) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr><td class="<?= htmlspecialchars(trim($reportColumnClass('status'))) ?>"><strong>TOTAL</strong></td><td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($total) ?></td><td class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">100%</td></tr></tfoot>
            </table>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('area') || $showReportSection('sector')): ?>
        <div class="rp-two-col" style="margin-top:22px;">
          <?php if ($showReportSection('area')): ?>
          <div>
            <div class="rp-section-label"><?= htmlspecialchars($blotterSectionLabel('area')) ?></div>
            <?php if (empty($blot['by_area'])): ?>
              <p class="rp-empty">No area-linked case data.</p>
            <?php else: $blotterAreaTotal = array_sum(array_column($blot['by_area'], 'total')); ?>
            <table class="rp-table">
              <thead><tr><th class="<?= htmlspecialchars(trim($reportColumnClass('area'))) ?>">Area</th><th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Count</th><th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">%</th></tr></thead>
              <tbody>
                <?php foreach ($blot['by_area'] as $r): ?>
                <tr>
                  <td class="<?= htmlspecialchars(trim($reportColumnClass('area'))) ?>"><?= htmlspecialchars((string)$r['area']) ?></td>
                  <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)$r['total'], $blotterAreaTotal) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr><td class="<?= htmlspecialchars(trim($reportColumnClass('area'))) ?>"><strong>TOTAL</strong></td><td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($blotterAreaTotal) ?></td><td class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">100%</td></tr></tfoot>
            </table>
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <?php if ($showReportSection('sector')): ?>
          <div>
            <div class="rp-section-label"><?= htmlspecialchars($blotterSectionLabel('sector')) ?></div>
            <?php if (empty($blot['by_sector'])): ?>
              <p class="rp-empty">No sector-linked case data.</p>
            <?php else: $blotterSectorTotal = array_sum(array_column($blot['by_sector'], 'total')); ?>
            <table class="rp-table">
              <thead><tr><th class="<?= htmlspecialchars(trim($reportColumnClass('sector'))) ?>">Sector</th><th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Count</th><th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">%</th></tr></thead>
              <tbody>
                <?php foreach ($blot['by_sector'] as $r): ?>
                <tr>
                  <td class="<?= htmlspecialchars(trim($reportColumnClass('sector'))) ?>"><?= htmlspecialchars((string)$r['sector']) ?></td>
                  <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)$r['total'], $blotterSectorTotal) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr><td class="<?= htmlspecialchars(trim($reportColumnClass('sector'))) ?>"><strong>TOTAL</strong></td><td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($blotterSectorTotal) ?></td><td class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">100%</td></tr></tfoot>
            </table>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('trend')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($blotterSectionLabel('trend')) ?> (Last 12 Months)</div>
          <?php if (empty($blot['trend'])): ?>
            <p class="rp-empty">No trend data.</p>
          <?php else: $tTotal = array_sum(array_column($blot['trend'],'total')); ?>
          <table class="rp-table">
            <thead><tr><th class="<?= htmlspecialchars(trim($reportColumnClass('date'))) ?>">Month</th><th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Cases Filed</th></tr></thead>
            <tbody>
              <?php foreach ($blot['trend'] as $r): ?>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('date'))) ?>"><?= htmlspecialchars(date('F Y', strtotime($r['month'].'-01'))) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)$r['total']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td class="<?= htmlspecialchars(trim($reportColumnClass('date'))) ?>"><strong>TOTAL</strong></td><td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($tTotal) ?></td></tr></tfoot>
          </table>
          <?php endif; ?>
        </div>
        <?php endif; ?>

<?php
// ══════════════════════════════════════════════════════════════════════════════
// RENDER: COMPLAINTS
// ══════════════════════════════════════════════════════════════════════════════
elseif ($module === 'complaints'):
  $kpi   = $comp['kpi'] ?? [];
  $total = (int)($kpi['total'] ?? 0);
  $complaintSectionLabel = static fn(string $key): string => rp_section_heading($reportCustomizeConfig['sections'] ?? [], $visibleReportSections, $key);
  $complaintTypeChartData = array_values(array_filter($comp['by_type'] ?? [], static function (array $row): bool {
    return (int)($row['total'] ?? 0) > 0;
  }));
  $complaintOriginChartData = array_values(array_filter($comp['by_origin'] ?? [], static function (array $row): bool {
    return (int)($row['total'] ?? 0) > 0;
  }));
  $complaintKindChartData = array_values(array_filter($comp['by_kind'] ?? [], static function (array $row): bool {
    return (int)($row['total'] ?? 0) > 0;
  }));
  $complaintAreaChartData = array_values(array_filter($comp['by_area'] ?? [], static function (array $row): bool {
    return (int)($row['total'] ?? 0) > 0;
  }));
  $complaintSectorChartData = array_values(array_filter($comp['by_sector'] ?? [], static function (array $row): bool {
    return (int)($row['total'] ?? 0) > 0;
  }));
  $complaintTrendChartData = array_values(array_filter(array_map(static function (array $row): array {
    $monthValue = (string)($row['month'] ?? '');
    return [
      'label' => $monthValue !== '' ? date('F Y', strtotime($monthValue . '-01')) : 'Unspecified',
      'total' => (int)($row['total'] ?? 0),
    ];
  }, $comp['trend'] ?? []), static function (array $row): bool {
    return (int)($row['total'] ?? 0) > 0;
  }));
  $shouldLoadComplaintCharts = $showReportSection('charts') && (
    $complaintTypeChartData !== []
    || $complaintOriginChartData !== []
    || $complaintKindChartData !== []
    || $complaintAreaChartData !== []
    || $complaintSectorChartData !== []
    || $complaintTrendChartData !== []
  );
?>
        <?php if ($showReportSection('summary')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($complaintSectionLabel('summary')) ?></div>
          <table class="rp-summary">
            <tbody>
              <tr><td>Total Complaints Filed</td><td><?= number_format($total) ?></td></tr>
              <tr><td>Escalated to Blotter</td><td><?= number_format((int)($kpi['escalated']??0)) ?> &nbsp;<span class="pct">(<?= rp_pct((int)($kpi['escalated']??0),$total) ?>)</span></td></tr>
              <tr><td>Not Escalated</td><td><?= number_format((int)($kpi['unescalated']??0)) ?> &nbsp;<span class="pct">(<?= rp_pct((int)($kpi['unescalated']??0),$total) ?>)</span></td></tr>
              <tr><td>Walk-in Complaints</td><td><?= number_format((int)($kpi['walkin']??0)) ?> &nbsp;<span class="pct">(<?= rp_pct((int)($kpi['walkin']??0),$total) ?>)</span></td></tr>
              <tr><td>Online Complaints</td><td><?= number_format((int)($kpi['online_count']??0)) ?> &nbsp;<span class="pct">(<?= rp_pct((int)($kpi['online_count']??0),$total) ?>)</span></td></tr>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('charts')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($complaintSectionLabel('charts')) ?></div>
          <?php if ($complaintTypeChartData === [] && $complaintOriginChartData === [] && $complaintKindChartData === [] && $complaintAreaChartData === [] && $complaintSectorChartData === [] && $complaintTrendChartData === []): ?>
            <p class="rp-empty">No chart data is available for the selected filters.</p>
          <?php else: ?>
          <div class="rp-chart-grid">
            <?php if ($complaintTypeChartData !== []): ?>
            <div class="rp-chart-card">
              <div class="rp-subsection-title">Complaint Type Breakdown</div>
              <div class="rp-chart-wrap">
                <canvas id="complaintTypeChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of complaints by complaint type.</div>
            </div>
            <?php endif; ?>
            <?php if ($complaintOriginChartData !== []): ?>
            <div class="rp-chart-card">
              <div class="rp-subsection-title">By Origin</div>
              <div class="rp-chart-wrap">
                <canvas id="complaintOriginChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of complaints by origin.</div>
            </div>
            <?php endif; ?>
            <?php if ($complaintKindChartData !== []): ?>
            <div class="rp-chart-card">
              <div class="rp-subsection-title">By Subject Kind</div>
              <div class="rp-chart-wrap">
                <canvas id="complaintKindChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of complaints by subject kind.</div>
            </div>
            <?php endif; ?>
            <?php if ($complaintAreaChartData !== []): ?>
            <div class="rp-chart-card">
              <div class="rp-subsection-title">Complaints by Area</div>
              <div class="rp-chart-wrap">
                <canvas id="complaintAreaChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of complaints by area.</div>
            </div>
            <?php endif; ?>
            <?php if ($complaintSectorChartData !== []): ?>
            <div class="rp-chart-card">
              <div class="rp-subsection-title">Complaints by Sector Membership</div>
              <div class="rp-chart-wrap">
                <canvas id="complaintSectorChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of complaints by sector membership.</div>
            </div>
            <?php endif; ?>
            <?php if ($complaintTrendChartData !== []): ?>
            <div class="rp-chart-card">
              <div class="rp-subsection-title">Monthly Trend</div>
              <div class="rp-chart-wrap">
                <canvas id="complaintTrendChart"></canvas>
              </div>
              <div class="rp-chart-note"><?= htmlspecialchars($reportChartTypeOptions[$reportChartType] ?? 'Bar Chart') ?> view of monthly complaints filed for the last 12 months.</div>
            </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('type')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($complaintSectionLabel('type')) ?></div>
          <?php if (empty($comp['by_type'])): ?>
            <p class="rp-empty">No complaint type data for the selected filters.</p>
          <?php else: ?>
          <table class="rp-table">
            <thead><tr><th class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>">Complaint Type</th><th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Count</th><th class="text-center<?= htmlspecialchars($reportColumnClass('result')) ?>">Escalated</th><th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">Esc. Rate</th></tr></thead>
            <tbody>
              <?php foreach ($comp['by_type'] as $r): ?>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('type'))) ?>"><?= htmlspecialchars((string)$r['complaint_type']) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)$r['total']) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('result')) ?>"><?= number_format((int)$r['escalated']) ?></td>
                <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)$r['escalated'], (int)$r['total']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('origin') || $showReportSection('kind')): ?>
        <div class="rp-two-col" style="margin-top:22px;">
          <?php if ($showReportSection('origin')): ?>
          <div>
            <div class="rp-section-label"><?= htmlspecialchars($complaintSectionLabel('origin')) ?></div>
            <?php if (empty($comp['by_origin'])): ?>
              <p class="rp-empty">No data.</p>
            <?php else: ?>
            <table class="rp-table">
              <thead><tr><th class="<?= htmlspecialchars(trim($reportColumnClass('channel'))) ?>">Origin</th><th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Count</th><th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">%</th></tr></thead>
              <tbody>
                <?php foreach ($comp['by_origin'] as $r): ?>
                <tr>
                  <td class="<?= htmlspecialchars(trim($reportColumnClass('channel'))) ?>"><?= htmlspecialchars(ucwords(str_replace('_',' ',$r['origin']))) ?></td>
                  <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)$r['total'],$total) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr><td class="<?= htmlspecialchars(trim($reportColumnClass('channel'))) ?>"><strong>TOTAL</strong></td><td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($total) ?></td><td class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">100%</td></tr></tfoot>
            </table>
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <?php if ($showReportSection('kind')): ?>
          <div>
            <div class="rp-section-label"><?= htmlspecialchars($complaintSectionLabel('kind')) ?></div>
            <?php if (empty($comp['by_kind'])): ?>
              <p class="rp-empty">No data.</p>
            <?php else: $kindTotal = array_sum(array_column($comp['by_kind'],'total')); ?>
            <table class="rp-table">
              <thead><tr><th class="<?= htmlspecialchars(trim($reportColumnClass('status'))) ?>">Kind</th><th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Count</th><th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">%</th></tr></thead>
              <tbody>
                <?php foreach ($comp['by_kind'] as $r): ?>
                <tr>
                  <td class="<?= htmlspecialchars(trim($reportColumnClass('status'))) ?>"><?= htmlspecialchars($r['kind']) ?></td>
                  <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)$r['total'],$kindTotal) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr><td class="<?= htmlspecialchars(trim($reportColumnClass('status'))) ?>"><strong>TOTAL</strong></td><td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($kindTotal) ?></td><td class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">100%</td></tr></tfoot>
            </table>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('area') || $showReportSection('sector')): ?>
        <div class="rp-two-col" style="margin-top:22px;">
          <?php if ($showReportSection('area')): ?>
          <div>
            <div class="rp-section-label"><?= htmlspecialchars($complaintSectionLabel('area')) ?></div>
            <?php if (empty($comp['by_area'])): ?>
              <p class="rp-empty">No area-linked complaint data.</p>
            <?php else: $complaintAreaTotal = array_sum(array_column($comp['by_area'], 'total')); ?>
            <table class="rp-table">
              <thead><tr><th class="<?= htmlspecialchars(trim($reportColumnClass('area'))) ?>">Area</th><th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Count</th><th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">%</th></tr></thead>
              <tbody>
                <?php foreach ($comp['by_area'] as $r): ?>
                <tr>
                  <td class="<?= htmlspecialchars(trim($reportColumnClass('area'))) ?>"><?= htmlspecialchars((string)$r['area']) ?></td>
                  <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)$r['total'], $complaintAreaTotal) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr><td class="<?= htmlspecialchars(trim($reportColumnClass('area'))) ?>"><strong>TOTAL</strong></td><td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($complaintAreaTotal) ?></td><td class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">100%</td></tr></tfoot>
            </table>
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <?php if ($showReportSection('sector')): ?>
          <div>
            <div class="rp-section-label"><?= htmlspecialchars($complaintSectionLabel('sector')) ?></div>
            <?php if (empty($comp['by_sector'])): ?>
              <p class="rp-empty">No sector-linked complaint data.</p>
            <?php else: $complaintSectorTotal = array_sum(array_column($comp['by_sector'], 'total')); ?>
            <table class="rp-table">
              <thead><tr><th class="<?= htmlspecialchars(trim($reportColumnClass('sector'))) ?>">Sector</th><th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Count</th><th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">%</th></tr></thead>
              <tbody>
                <?php foreach ($comp['by_sector'] as $r): ?>
                <tr>
                  <td class="<?= htmlspecialchars(trim($reportColumnClass('sector'))) ?>"><?= htmlspecialchars((string)$r['sector']) ?></td>
                  <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)$r['total'], $complaintSectorTotal) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr><td class="<?= htmlspecialchars(trim($reportColumnClass('sector'))) ?>"><strong>TOTAL</strong></td><td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($complaintSectorTotal) ?></td><td class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">100%</td></tr></tfoot>
            </table>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($showReportSection('trend')): ?>
        <div class="rp-section">
          <div class="rp-section-label"><?= htmlspecialchars($complaintSectionLabel('trend')) ?> (Last 12 Months)</div>
          <?php if (empty($comp['trend'])): ?>
            <p class="rp-empty">No trend data.</p>
          <?php else:
            $tTotal = array_sum(array_column($comp['trend'],'total'));
            $tEsc   = array_sum(array_column($comp['trend'],'escalated'));
          ?>
          <table class="rp-table">
            <thead><tr><th class="<?= htmlspecialchars(trim($reportColumnClass('date'))) ?>">Month</th><th class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>">Total Filed</th><th class="text-center<?= htmlspecialchars($reportColumnClass('result')) ?>">Escalated</th><th class="text-center<?= htmlspecialchars($reportColumnClass('percentage')) ?>">Escalation Rate</th></tr></thead>
            <tbody>
              <?php foreach ($comp['trend'] as $r): ?>
              <tr>
                <td class="<?= htmlspecialchars(trim($reportColumnClass('date'))) ?>"><?= htmlspecialchars(date('F Y', strtotime($r['month'].'-01'))) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format((int)$r['total']) ?></td>
                <td class="text-center<?= htmlspecialchars($reportColumnClass('result')) ?>"><?= number_format((int)$r['escalated']) ?></td>
                <td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct((int)$r['escalated'],(int)$r['total']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td class="<?= htmlspecialchars(trim($reportColumnClass('date'))) ?>"><strong>TOTAL</strong></td><td class="text-center<?= htmlspecialchars($reportColumnClass('count')) ?>"><?= number_format($tTotal) ?></td><td class="text-center<?= htmlspecialchars($reportColumnClass('result')) ?>"><?= number_format($tEsc) ?></td><td class="text-center pct<?= htmlspecialchars($reportColumnClass('percentage')) ?>"><?= rp_pct($tEsc,$tTotal) ?></td></tr></tfoot>
          </table>
          <?php endif; ?>
        </div>
        <?php endif; ?>

<?php endif; ?>

        <!-- ── Certification / Signature block ─────────────────────────── -->
        <div class="rp-footer">
          <div class="rp-sig-grid">
            <div class="rp-sig-block">
              <div style="height:40px;"></div>
              <div class="rp-sig-line">
                <div class="rp-sig-name"><?= htmlspecialchars($preparedByName) ?></div>
                <div class="rp-sig-role"><?= htmlspecialchars($preparedByRole) ?></div>
              </div>
            </div>
            <div class="rp-sig-block">
              <div style="height:40px;"></div>
              <div class="rp-sig-line">
                <div class="rp-sig-name"><?= htmlspecialchars($reportNotedByName) ?></div>
                <div class="rp-sig-role"><?= htmlspecialchars($reportNotedByRole) ?></div>
              </div>
            </div>
          </div>
          <div class="rp-footer-meta">
            Report generated on <strong><?= date('F j, Y \a\t g:i A') ?></strong>
            <?php if ($module !== 'residents'): ?>
            &nbsp;|&nbsp; Period covered: <strong><?= rp_date_label($dateFrom) ?></strong> to <strong><?= rp_date_label($dateTo) ?></strong>
            <?php endif; ?>
            &nbsp;|&nbsp; System: Barangay San Jose Information Management System
          </div>
        </div>

</div><!-- /.rp-doc -->

<?php if ($shouldLoadIssuanceCharts || $shouldLoadFinancialCharts || $shouldLoadResidentCharts || $shouldLoadAppointmentCharts || $shouldLoadBlotterCharts || $shouldLoadComplaintCharts): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<?php if ($isPrintView): ?>
<script>
if (window.Chart && window.Chart.defaults) {
  window.Chart.defaults.animation = false;
}
</script>
<?php endif; ?>
<script>
window.__rpChartHelpers = (() => {
  if (typeof Chart === 'undefined') {
    return null;
  }

  const palette = ['#DE710C', '#2563EB', '#16A34A', '#DC2626', '#7C3AED', '#0891B2', '#CA8A04', '#DB2777', '#4F46E5', '#059669'];
  const colorsFor = (count) => Array.from({ length: count }, (_, index) => palette[index % palette.length]);
  const wrapLabel = (label, maxChars = 18) => {
    const text = String(label ?? '').trim();
    if (text === '' || text.length <= maxChars) {
      return text;
    }

    const words = text.split(/\s+/);
    const lines = [];
    let current = '';
    for (const word of words) {
      const next = current === '' ? word : `${current} ${word}`;
      if (next.length > maxChars && current !== '') {
        lines.push(current);
        current = word;
      } else {
        current = next;
      }
    }
    if (current !== '') {
      lines.push(current);
    }
    return lines.length > 1 ? lines : text;
  };

  const renderCategoricalChart = (source, chartType = 'bar') => {
    const canvas = document.getElementById(source.canvasId);
    if (!canvas || !Array.isArray(source.labels) || source.labels.length === 0) {
      return;
    }

    const entries = source.labels.map((label, index) => ({
      label: String(label ?? '').trim(),
      value: Number(source.values[index] || 0),
    })).filter((entry) => entry.label !== '' && Number.isFinite(entry.value) && entry.value > 0);

    if (!entries.length) {
      return;
    }

    const colors = colorsFor(entries.length);
    const labels = chartType === 'pie'
      ? entries.map((entry) => entry.label)
      : entries.map((entry) => wrapLabel(entry.label, source.maxLabelChars ?? 18));
    const values = entries.map((entry) => entry.value);
    const config = chartType === 'pie'
      ? {
          type: 'pie',
          data: {
            labels,
            datasets: [{
              label: source.title,
              data: values,
              backgroundColor: colors,
              borderColor: '#ffffff',
              borderWidth: 2,
            }],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                position: 'bottom',
              },
            },
          },
        }
      : {
          type: 'bar',
          data: {
            labels,
            datasets: [{
              label: source.datasetLabel || source.title || 'Count',
              data: values,
              backgroundColor: colors,
              borderColor: colors,
              borderWidth: 1,
              borderRadius: 8,
            }],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              x: {
                ticks: {
                  autoSkip: false,
                  maxRotation: 0,
                  minRotation: 0,
                  font: {
                    size: 10,
                  },
                },
              },
              y: {
                beginAtZero: true,
                ticks: {
                  precision: 0,
                },
              },
            },
            plugins: {
              legend: {
                display: false,
              },
            },
          },
        };

    new Chart(canvas.getContext('2d'), config);
  };

  const initCategoricalCharts = (sources, chartType = 'bar') => {
    sources.forEach((source) => renderCategoricalChart(source, chartType));
  };

  return {
    initCategoricalCharts,
  };
})();
</script>
<?php endif; ?>
<?php if ($shouldLoadIssuanceCharts): ?>
<script>
(() => {
  const chartType = <?= json_encode($reportChartType, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const palette = ['#DE710C', '#2563EB', '#16A34A', '#DC2626', '#7C3AED', '#0891B2', '#CA8A04', '#DB2777', '#4F46E5', '#059669'];
  const chartSources = [
    {
      canvasId: 'issuanceAreaTotalsChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['area_label'] ?? ''), $issuanceAreaChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $issuanceAreaChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'Total Requests by Area Number'
    },
    {
      canvasId: 'issuanceTypeTotalsChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['request_type_label'] ?? ''), $issuanceTypeChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $issuanceTypeChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'Total Requests by Document Type'
    },
    {
      canvasId: 'issuanceStatusTotalsChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['status_label'] ?? ''), $issuanceStatusChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $issuanceStatusChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'Total Requests by Status'
    },
    {
      canvasId: 'issuanceChannelTotalsChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['channel_label'] ?? ''), $issuanceChannelChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $issuanceChannelChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'Total Requests by Channel'
    }
  ];

  const colorsFor = (count) => Array.from({ length: count }, (_, index) => palette[index % palette.length]);
  const wrapLabel = (label, maxChars = 18) => {
    const text = String(label ?? '').trim();
    if (text === '' || text.length <= maxChars) {
      return text;
    }

    const words = text.split(/\s+/);
    const lines = [];
    let current = '';
    for (const word of words) {
      const next = current === '' ? word : `${current} ${word}`;
      if (next.length > maxChars && current !== '') {
        lines.push(current);
        current = word;
      } else {
        current = next;
      }
    }
    if (current !== '') {
      lines.push(current);
    }
    return lines.length > 1 ? lines : text;
  };

  const renderChart = (source) => {
    const canvas = document.getElementById(source.canvasId);
    if (!canvas || !Array.isArray(source.labels) || source.labels.length === 0 || typeof Chart === 'undefined') {
      return;
    }

    const colors = colorsFor(source.labels.length);
    const chartLabels = chartType === 'bar'
      ? source.labels.map((label) => wrapLabel(label))
      : source.labels;
    const config = chartType === 'pie'
      ? {
          type: 'pie',
          data: {
            labels: chartLabels,
            datasets: [{
              label: source.title,
              data: source.values,
              backgroundColor: colors,
              borderColor: '#ffffff',
              borderWidth: 2,
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                position: 'bottom',
              },
            },
          },
        }
      : {
          type: 'bar',
          data: {
            labels: chartLabels,
            datasets: [{
              label: 'Total Requests',
              data: source.values,
              backgroundColor: colors,
              borderColor: colors,
              borderWidth: 1,
              borderRadius: 8,
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              x: {
                ticks: {
                  autoSkip: false,
                  maxRotation: 0,
                  minRotation: 0,
                  font: {
                    size: 10,
                  },
                },
              },
              y: {
                beginAtZero: true,
                ticks: {
                  precision: 0,
                },
              },
            },
            plugins: {
              legend: {
                display: false,
              },
            },
          },
        };

    new Chart(canvas.getContext('2d'), config);
  };

  const initCharts = () => chartSources.forEach(renderChart);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCharts, { once: true });
  } else {
    initCharts();
  }
})();
</script>
<?php endif; ?>

<?php if ($shouldLoadFinancialCharts): ?>
<script>
(() => {
  if (typeof Chart === 'undefined') {
    return;
  }

  const palette = ['#DE710C', '#2563EB', '#16A34A', '#DC2626', '#7C3AED', '#0891B2', '#CA8A04', '#DB2777', '#4F46E5', '#059669'];
  const sources = [
    {
      canvasId: 'financialTypeRevenueChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => rp_payment_method_label((string)($row['method'] ?? '')), $financialTypeRevenueChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): float => (float)($row['amount'] ?? 0), $financialTypeRevenueChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'By Payment Type Revenue Stream'
    },
    {
      canvasId: 'financialAreaRevenueChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['area'] ?? ''), $financialAreaRevenueChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): float => (float)($row['amount'] ?? 0), $financialAreaRevenueChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'By Area Revenue Stream',
      includeZeroValues: true,
      preserveOrder: true
    }
  ];

  const wrapLabel = (label, maxChars = 18) => {
    const text = String(label ?? '').trim();
    if (text === '' || text.length <= maxChars) {
      return text;
    }

    const words = text.split(/\s+/);
    const lines = [];
    let current = '';
    for (const word of words) {
      const next = current === '' ? word : `${current} ${word}`;
      if (next.length > maxChars && current !== '') {
        lines.push(current);
        current = word;
      } else {
        current = next;
      }
    }
    if (current !== '') {
      lines.push(current);
    }
    return lines.length > 1 ? lines : text;
  };

  const formatCurrency = (value) => {
    const amount = Number(value || 0);
    return `PHP ${amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  };

  const formatCompactCurrency = (value) => {
    const amount = Number(value || 0);
    const absolute = Math.abs(amount);
    if (absolute >= 1000000000) {
      return `PHP ${(amount / 1000000000).toLocaleString('en-PH', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}B`;
    }
    if (absolute >= 1000000) {
      return `PHP ${(amount / 1000000).toLocaleString('en-PH', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}M`;
    }
    if (absolute >= 1000) {
      return `PHP ${(amount / 1000).toLocaleString('en-PH', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}K`;
    }
    return formatCurrency(amount);
  };

  const renderChart = (source) => {
    const canvas = document.getElementById(source.canvasId);
    if (!canvas || !Array.isArray(source.labels) || source.labels.length === 0) {
      return;
    }

    let entries = source.labels.map((label, index) => ({
      label: String(label ?? '').trim(),
      value: Number(source.values[index] || 0),
    })).filter((entry) => (
      entry.label !== ''
      && Number.isFinite(entry.value)
      && (source.includeZeroValues ? entry.value >= 0 : entry.value > 0)
    ));
    if (!source.preserveOrder) {
      entries = entries.sort((left, right) => right.value - left.value);
    }
    if (!entries.length) {
      return;
    }

    const wrap = canvas.closest('.rp-chart-wrap');
    if (wrap) {
      wrap.style.minHeight = `${Math.max(280, entries.length * 56)}px`;
    }

    const labels = entries.map((entry) => wrapLabel(entry.label, 26));
    const values = entries.map((entry) => entry.value);
    const colors = entries.map((_, index) => palette[index % palette.length]);
    new Chart(canvas.getContext('2d'), {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Revenue',
          data: values,
          backgroundColor: colors,
          borderColor: colors,
          borderWidth: 1,
          borderRadius: 8,
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        layout: {
          padding: {
            right: 12,
          },
        },
        scales: {
          x: {
            beginAtZero: true,
            ticks: {
              callback: (value) => formatCompactCurrency(value),
              maxTicksLimit: 6,
            },
          },
          y: {
            ticks: {
              autoSkip: false,
              font: {
                size: 10,
              },
            },
            grid: {
              display: false,
            },
          },
        },
        plugins: {
          legend: {
            display: false,
          },
          tooltip: {
            callbacks: {
              label: (context) => formatCurrency(context.parsed.x),
            },
          },
        },
      },
    });
  };

  const initCharts = () => sources.forEach(renderChart);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCharts, { once: true });
  } else {
    initCharts();
  }
})();
</script>
<?php endif; ?>

<?php if ($shouldLoadResidentCharts): ?>
<script>
(() => {
  if (typeof Chart === 'undefined') {
    return;
  }

  const chartType = <?= json_encode($reportChartType, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const palette = ['#DE710C', '#2563EB', '#16A34A', '#DC2626', '#7C3AED', '#0891B2', '#CA8A04', '#DB2777', '#4F46E5', '#059669'];
  const sources = [
    {
      canvasId: 'residentAreaChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['area'] ?? ''), $residentAreaChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $residentAreaChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'Residents Breakdown by Area',
      datasetLabel: 'Verified Residents',
    },
    {
      canvasId: 'residentSectorChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['sector'] ?? ''), $residentSectorChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $residentSectorChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'Sector Membership',
      datasetLabel: 'Verified Residents',
    },
    {
      canvasId: 'residentHouseholdChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['area'] ?? ''), $residentHouseholdChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $residentHouseholdChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'Households by Area',
      datasetLabel: 'Heads of Family',
    },
    {
      canvasId: 'residentEmploymentChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['employment'] ?? ''), $residentEmploymentChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $residentEmploymentChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'Employed and Unemployed',
      datasetLabel: 'Verified Residents',
    },
    {
      canvasId: 'residentGenderChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['label'] ?? 'Unspecified'), $residentGenderChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $residentGenderChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'Gender',
      datasetLabel: 'Verified Residents',
    },
    {
      canvasId: 'residentAgeChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['label'] ?? ''), $residentAgeChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $residentAgeChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'Age Distribution',
      datasetLabel: 'Verified Residents',
    },
    {
      canvasId: 'residentMonthlyChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['label'] ?? ''), $residentMonthlyChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $residentMonthlyChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'Monthly Registration Count',
      datasetLabel: 'New Verified Residents',
    }
  ];

  const colorsFor = (count) => Array.from({ length: count }, (_, index) => palette[index % palette.length]);
  const wrapLabel = (label, maxChars = 18) => {
    const text = String(label ?? '').trim();
    if (text === '' || text.length <= maxChars) {
      return text;
    }

    const words = text.split(/\s+/);
    const lines = [];
    let current = '';
    for (const word of words) {
      const next = current === '' ? word : `${current} ${word}`;
      if (next.length > maxChars && current !== '') {
        lines.push(current);
        current = word;
      } else {
        current = next;
      }
    }
    if (current !== '') {
      lines.push(current);
    }
    return lines.length > 1 ? lines : text;
  };

  const renderChart = (source) => {
    const canvas = document.getElementById(source.canvasId);
    if (!canvas || !Array.isArray(source.labels) || source.labels.length === 0) {
      return;
    }

    const colors = colorsFor(source.labels.length);
    const chartLabels = chartType === 'bar'
      ? source.labels.map((label) => wrapLabel(label))
      : source.labels;
    const config = chartType === 'pie'
      ? {
          type: 'pie',
          data: {
            labels: chartLabels,
            datasets: [{
              label: source.title,
              data: source.values,
              backgroundColor: colors,
              borderColor: '#ffffff',
              borderWidth: 2,
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                position: 'bottom',
              },
            },
          },
        }
      : {
          type: 'bar',
          data: {
            labels: chartLabels,
            datasets: [{
              label: source.datasetLabel,
              data: source.values,
              backgroundColor: colors,
              borderColor: colors,
              borderWidth: 1,
              borderRadius: 8,
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              x: {
                ticks: {
                  autoSkip: false,
                  maxRotation: 0,
                  minRotation: 0,
                  font: {
                    size: 10,
                  },
                },
              },
              y: {
                beginAtZero: true,
                ticks: {
                  precision: 0,
                },
              },
            },
            plugins: {
              legend: {
                display: false,
              },
            },
          },
        };

    new Chart(canvas.getContext('2d'), config);
  };

  const initCharts = () => sources.forEach(renderChart);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCharts, { once: true });
  } else {
    initCharts();
  }
})();
</script>
<?php endif; ?>

<?php if ($shouldLoadAppointmentCharts): ?>
<script>
(() => {
  const helpers = window.__rpChartHelpers;
  if (!helpers) {
    return;
  }

  const chartType = <?= json_encode($reportChartType, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const sources = [
    {
      canvasId: 'appointmentStatusChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['status'] ?? ''), $appointmentStatusChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $appointmentStatusChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'Status Breakdown',
      datasetLabel: 'Appointment Requests',
    },
    {
      canvasId: 'appointmentPurposeChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['purpose'] ?? ''), $appointmentPurposeChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $appointmentPurposeChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'Purpose Breakdown',
      datasetLabel: 'Appointment Requests',
      maxLabelChars: 22,
    },
    {
      canvasId: 'appointmentAreaChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['area'] ?? ''), $appointmentAreaChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $appointmentAreaChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'Requests by Area',
      datasetLabel: 'Appointment Requests',
    },
    {
      canvasId: 'appointmentSectorChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['sector'] ?? ''), $appointmentSectorChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $appointmentSectorChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'Requests by Sector Membership',
      datasetLabel: 'Appointment Requests',
      maxLabelChars: 22,
    },
    {
      canvasId: 'appointmentTrendChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['label'] ?? ''), $appointmentTrendChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $appointmentTrendChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'Monthly Trend',
      datasetLabel: 'Requests Filed',
      maxLabelChars: 14,
    }
  ];

  const initCharts = () => helpers.initCategoricalCharts(sources, chartType);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCharts, { once: true });
  } else {
    initCharts();
  }
})();
</script>
<?php endif; ?>

<?php if ($shouldLoadBlotterCharts): ?>
<script>
(() => {
  const helpers = window.__rpChartHelpers;
  if (!helpers) {
    return;
  }

  const chartType = <?= json_encode($reportChartType, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const sources = [
    {
      canvasId: 'blotterTypeChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['complaint_type'] ?? ''), $blotterTypeChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $blotterTypeChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'Complaint Type Breakdown',
      datasetLabel: 'Cases Filed',
      maxLabelChars: 22,
    },
    {
      canvasId: 'blotterStatusChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['status'] ?? ''), $blotterStatusChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $blotterStatusChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'Status Breakdown',
      datasetLabel: 'Cases Filed',
    },
    {
      canvasId: 'blotterAreaChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['area'] ?? ''), $blotterAreaChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $blotterAreaChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'Cases by Area',
      datasetLabel: 'Cases Filed',
    },
    {
      canvasId: 'blotterSectorChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['sector'] ?? ''), $blotterSectorChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $blotterSectorChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'Cases by Sector Membership',
      datasetLabel: 'Cases Filed',
      maxLabelChars: 22,
    },
    {
      canvasId: 'blotterTrendChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['label'] ?? ''), $blotterTrendChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $blotterTrendChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'Monthly Trend',
      datasetLabel: 'Cases Filed',
      maxLabelChars: 14,
    }
  ];

  const initCharts = () => helpers.initCategoricalCharts(sources, chartType);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCharts, { once: true });
  } else {
    initCharts();
  }
})();
</script>
<?php endif; ?>

<?php if ($shouldLoadComplaintCharts): ?>
<script>
(() => {
  const helpers = window.__rpChartHelpers;
  if (!helpers) {
    return;
  }

  const chartType = <?= json_encode($reportChartType, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const sources = [
    {
      canvasId: 'complaintTypeChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['complaint_type'] ?? ''), $complaintTypeChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $complaintTypeChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'Complaint Type Breakdown',
      datasetLabel: 'Complaints Filed',
      maxLabelChars: 22,
    },
    {
      canvasId: 'complaintOriginChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => ucwords(str_replace('_', ' ', (string)($row['origin'] ?? ''))), $complaintOriginChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $complaintOriginChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'By Origin',
      datasetLabel: 'Complaints Filed',
    },
    {
      canvasId: 'complaintKindChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['kind'] ?? ''), $complaintKindChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $complaintKindChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'By Subject Kind',
      datasetLabel: 'Complaints Filed',
    },
    {
      canvasId: 'complaintAreaChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['area'] ?? ''), $complaintAreaChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $complaintAreaChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'Complaints by Area',
      datasetLabel: 'Complaints Filed',
    },
    {
      canvasId: 'complaintSectorChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['sector'] ?? ''), $complaintSectorChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $complaintSectorChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'Complaints by Sector Membership',
      datasetLabel: 'Complaints Filed',
      maxLabelChars: 22,
    },
    {
      canvasId: 'complaintTrendChart',
      labels: <?= json_encode(array_map(static fn(array $row): string => (string)($row['label'] ?? ''), $complaintTrendChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      values: <?= json_encode(array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $complaintTrendChartData), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
      title: 'Monthly Trend',
      datasetLabel: 'Complaints Filed',
      maxLabelChars: 14,
    }
  ];

  const initCharts = () => helpers.initCategoricalCharts(sources, chartType);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCharts, { once: true });
  } else {
    initCharts();
  }
})();
</script>
<?php endif; ?>

<?php if ($isPrintView): ?>
<script>
window.__reportPaginationReady = (() => {
  const LETTER_WIDTH_PX = Math.round(8.5 * 96);
  const LETTER_HEIGHT_PX = Math.round(11 * 96);
  const PAGE_MARGIN_PX = Math.round(0.5 * 96);
  const CONTENT_HEIGHT_PX = LETTER_HEIGHT_PX - (PAGE_MARGIN_PX * 2);
  const BLOCK_GAP_PX = Math.round((14 / 72) * 96);

  const nextFrame = () => new Promise((resolve) => window.requestAnimationFrame(() => resolve()));
  const wait = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms));

  const copyCanvasBitmap = (sourceCanvas, targetCanvas) => {
    if (!(sourceCanvas instanceof HTMLCanvasElement) || !(targetCanvas instanceof HTMLCanvasElement)) {
      return;
    }
    targetCanvas.width = sourceCanvas.width;
    targetCanvas.height = sourceCanvas.height;
    if (sourceCanvas.clientWidth > 0) {
      targetCanvas.style.width = `${sourceCanvas.clientWidth}px`;
    }
    if (sourceCanvas.clientHeight > 0) {
      targetCanvas.style.height = `${sourceCanvas.clientHeight}px`;
    }
    const ctx = targetCanvas.getContext('2d');
    if (!ctx) {
      return;
    }
    try {
      ctx.drawImage(sourceCanvas, 0, 0);
    } catch (err) {
      console.warn('Unable to copy report chart canvas:', err);
    }
  };

  const cloneNodeWithCanvases = (sourceNode) => {
    const clone = sourceNode.cloneNode(true);
    if (sourceNode instanceof HTMLCanvasElement && clone instanceof HTMLCanvasElement) {
      copyCanvasBitmap(sourceNode, clone);
      return clone;
    }

    const sourceCanvases = Array.from(sourceNode.querySelectorAll('canvas'));
    const cloneCanvases = Array.from(clone.querySelectorAll('canvas'));
    sourceCanvases.forEach((canvas, index) => {
      copyCanvasBitmap(canvas, cloneCanvases[index]);
    });
    return clone;
  };

  const createBlockWrapper = (nodes, options = {}) => {
    const wrapper = document.createElement('section');
    wrapper.className = 'rp-page-block';
    if (options.kind) {
      wrapper.classList.add(`rp-page-block--${options.kind}`);
    }
    if (options.sectionId) {
      wrapper.dataset.sectionId = options.sectionId;
    }
    if (options.includeLabel && options.labelNode) {
      wrapper.appendChild(cloneNodeWithCanvases(options.labelNode));
    }
    nodes.forEach((node) => {
      wrapper.appendChild(cloneNodeWithCanvases(node));
    });
    return wrapper;
  };

  const createPage = (container) => {
    const pageEl = document.createElement('section');
    pageEl.className = 'rp-print-page';
    const contentEl = document.createElement('div');
    contentEl.className = 'rp-print-page-content';
    pageEl.appendChild(contentEl);
    container.appendChild(pageEl);
    return {
      el: pageEl,
      content: contentEl,
      count: 0,
    };
  };

  const applyScaleToBlock = (blockNode, naturalHeight, scale) => {
    if (!Number.isFinite(scale) || scale <= 0 || scale >= 0.999) {
      return blockNode;
    }

    const inner = document.createElement('div');
    inner.className = 'rp-page-block-scale';
    while (blockNode.firstChild) {
      inner.appendChild(blockNode.firstChild);
    }

    blockNode.appendChild(inner);
    blockNode.classList.add('rp-page-block--scaled');
    blockNode.style.height = `${Math.ceil(naturalHeight * scale)}px`;
    blockNode.style.minHeight = `${Math.ceil(naturalHeight * scale)}px`;
    inner.style.transform = `scale(${scale})`;
    inner.style.width = `${(100 / scale).toFixed(4)}%`;

    return blockNode;
  };

  const buildBlockDefinitions = (sourceDoc) => {
    const defs = [];
    // Tables flow one row at a time so pagination follows the real remaining
    // page space. Adjacent row blocks are merged back into one visual table.
    const TABLE_ROWS_PER_BLOCK = 1;

    const createTableChunkWrapper = (table, startIndex, endIndex, options = {}) => {
      const tableClone = cloneNodeWithCanvases(table);
      const body = tableClone.querySelector(':scope > tbody');
      if (body) {
        Array.from(body.children).forEach((row, rowIndex) => {
          if (rowIndex < startIndex || rowIndex >= endIndex) {
            row.remove();
          }
        });
      }

      if (!options.isLastChunk) {
        tableClone.querySelector(':scope > tfoot')?.remove();
      }

      const wrapper = document.createElement('section');
      wrapper.className = 'rp-page-block rp-page-block--table';
      if (options.sectionId) {
        wrapper.dataset.sectionId = options.sectionId;
      }
      if (options.includeLabel && options.labelNode) {
        wrapper.appendChild(cloneNodeWithCanvases(options.labelNode));
      }
      wrapper.appendChild(tableClone);
      return wrapper;
    };

    const header = sourceDoc.querySelector(':scope > .rp-doc-header');
    if (header) {
      defs.push({
        type: 'header',
        blockType: 'header',
        build: () => createBlockWrapper([header], { kind: 'header' }),
      });
    }

    const sections = Array.from(sourceDoc.querySelectorAll(':scope > .rp-section'));
    sections.forEach((section, sectionIndex) => {
      const label = section.querySelector(':scope > .rp-section-label');
      const children = Array.from(section.children).filter((child) => child !== label);
      const sectionId = `section-${sectionIndex}`;
      let sectionBlockIndex = 0;

      children.forEach((child) => {
        if (child.matches('.rp-two-col, .rp-three-col, .rp-chart-grid')) {
          const blockType = child.classList.contains('rp-chart-grid') ? 'chart' : 'table';
          Array.from(child.children)
            .filter((item) => item.nodeType === 1)
            .forEach((item) => {
              const localIndex = sectionBlockIndex;
              defs.push({
                type: 'section',
                blockType,
                sectionId,
                indexInSection: localIndex,
                build: (includeLabel) => createBlockWrapper([item], {
                  includeLabel,
                  labelNode: label,
                  sectionId,
                  kind: blockType,
                }),
              });
              sectionBlockIndex += 1;
            });
          return;
        }

        if (child.matches('.rp-table')) {
          const rows = Array.from(child.querySelectorAll(':scope > tbody > tr'));
          if (rows.length > TABLE_ROWS_PER_BLOCK) {
            for (let startIndex = 0; startIndex < rows.length; startIndex += TABLE_ROWS_PER_BLOCK) {
              const endIndex = Math.min(startIndex + TABLE_ROWS_PER_BLOCK, rows.length);
              const localIndex = sectionBlockIndex;
              defs.push({
                type: 'section',
                blockType: 'table',
                sectionId,
                indexInSection: localIndex,
                build: (includeLabel) => createTableChunkWrapper(child, startIndex, endIndex, {
                  includeLabel,
                  labelNode: label,
                  sectionId,
                  isLastChunk: endIndex >= rows.length,
                }),
              });
              sectionBlockIndex += 1;
            }
            return;
          }
        }

        const blockType = child.matches('.rp-summary, .rp-table') ? 'table' : 'content';
        const localIndex = sectionBlockIndex;
        defs.push({
          type: 'section',
          blockType,
          sectionId,
          indexInSection: localIndex,
          build: (includeLabel) => createBlockWrapper([child], {
            includeLabel,
            labelNode: label,
            sectionId,
            kind: blockType,
          }),
        });
        sectionBlockIndex += 1;
      });
    });

    const footer = sourceDoc.querySelector(':scope > .rp-footer');
    if (footer) {
      defs.push({
        type: 'footer',
        blockType: 'footer',
        build: () => createBlockWrapper([footer], { kind: 'footer' }),
      });
    }

    return defs;
  };

  const paginateReport = async () => {
    const sourceDoc = document.querySelector('.rp-doc');
    if (!sourceDoc) {
      return null;
    }

    await nextFrame();
    await wait(160);
    await nextFrame();

    const pageContainer = document.createElement('div');
    pageContainer.className = 'rp-print-pages';
    pageContainer.style.visibility = 'hidden';
    sourceDoc.insertAdjacentElement('afterend', pageContainer);

    const stage = document.createElement('div');
    stage.className = 'rp-pagination-stage';
    const measurePage = document.createElement('section');
    measurePage.className = 'rp-print-page';
    const measureContent = document.createElement('div');
    measureContent.className = 'rp-print-page-content';
    measurePage.appendChild(measureContent);
    stage.appendChild(measurePage);
    document.body.appendChild(stage);

    const readHeight = (node) => {
      measureContent.replaceChildren(node);
      return Math.ceil(Math.max(
        node.getBoundingClientRect().height || 0,
        measureContent.scrollHeight || 0,
        measureContent.offsetHeight || 0
      ));
    };

    const tryMergeTableContinuation = (page, node) => {
      if (!node.classList.contains('rp-page-block--table') || !node.dataset.sectionId) {
        return false;
      }

      const previous = page.content.lastElementChild;
      if (!previous?.classList.contains('rp-page-block--table')
          || previous.dataset.sectionId !== node.dataset.sectionId) {
        return false;
      }

      const previousTable = previous.querySelector(':scope > .rp-table');
      const nextTable = node.querySelector(':scope > .rp-table');
      const previousBody = previousTable?.querySelector(':scope > tbody');
      const nextBody = nextTable?.querySelector(':scope > tbody');
      if (!previousTable || !nextTable || !previousBody || !nextBody) {
        return false;
      }

      const candidate = previous.cloneNode(true);
      const candidateTable = candidate.querySelector(':scope > .rp-table');
      const candidateBody = candidateTable?.querySelector(':scope > tbody');
      if (!candidateTable || !candidateBody) {
        return false;
      }

      Array.from(nextBody.children).forEach((row) => candidateBody.appendChild(row.cloneNode(true)));
      candidateTable.querySelector(':scope > tfoot')?.remove();
      const nextFoot = nextTable.querySelector(':scope > tfoot');
      if (nextFoot) {
        candidateTable.appendChild(nextFoot.cloneNode(true));
      }

      previous.replaceWith(candidate);
      if (page.content.scrollHeight <= CONTENT_HEIGHT_PX + 1) {
        return true;
      }
      candidate.replaceWith(previous);
      return false;
    };

    const tryAppend = (page, node) => {
      if (tryMergeTableContinuation(page, node)) {
        return true;
      }
      page.content.appendChild(node);
      if (page.content.scrollHeight <= CONTENT_HEIGHT_PX + 1) {
        page.count += 1;
        return true;
      }
      node.remove();
      return false;
    };

    const continueFitThreshold = (blockType) => {
      if (blockType === 'chart') return 0.84;
      if (blockType === 'table') return Number.POSITIVE_INFINITY;
      return 0.88;
    };

    const blocks = buildBlockDefinitions(sourceDoc);
    const shownSectionLabels = new Set();
    let page = createPage(pageContainer);

    for (const block of blocks) {
      const isSectionBlock = block.type === 'section';
      const markLabelShown = () => {
        if (isSectionBlock && block.sectionId && includeLabel) {
          shownSectionLabels.add(block.sectionId);
        }
      };
      let includeLabel = isSectionBlock && block.sectionId ? !shownSectionLabels.has(block.sectionId) : false;
      let node = block.build(includeLabel);
      let naturalHeight = readHeight(node);

      if (tryAppend(page, node)) {
        markLabelShown();
        continue;
      }

      const remainingHeight = Math.max(0, CONTENT_HEIGHT_PX - page.content.scrollHeight - (page.count > 0 ? BLOCK_GAP_PX : 0));
      const continueScale = remainingHeight > 0 ? (remainingHeight / Math.max(naturalHeight, 1)) : 0;
      if (page.count > 0 && continueScale >= continueFitThreshold(block.blockType) && continueScale < 1) {
        node = block.build(includeLabel);
        naturalHeight = readHeight(node);
        node = applyScaleToBlock(node, naturalHeight, continueScale);
        if (tryAppend(page, node)) {
          markLabelShown();
          continue;
        }
      }

      page = createPage(pageContainer);
      includeLabel = isSectionBlock && block.sectionId ? !shownSectionLabels.has(block.sectionId) : false;
      node = block.build(includeLabel);
      naturalHeight = readHeight(node);

      if (!tryAppend(page, node)) {
        const fullPageScale = CONTENT_HEIGHT_PX / Math.max(naturalHeight, 1);
        node = block.build(includeLabel);
        naturalHeight = readHeight(node);
        node = applyScaleToBlock(node, naturalHeight, Math.min(1, fullPageScale));
        tryAppend(page, node);
      }
      markLabelShown();
    }

    stage.remove();
    sourceDoc.classList.add('rp-doc--source');
    pageContainer.style.visibility = '';
    return pageContainer;
  };

  return (async () => {
    const container = await paginateReport();
    if (<?= $shouldAutoPrint ? 'true' : 'false' ?>) {
      await nextFrame();
      await wait(220);
      window.print();
    }
    return container;
  })();
})();
</script>
<?php if ($isPdfDownloadView): ?>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script>
(() => {
  const statusId = 'reportPdfExportStatus';
  const setStatus = (message, tone = 'info') => {
    let status = document.getElementById(statusId);
    if (!status) {
      status = document.createElement('div');
      status.id = statusId;
      status.style.position = 'fixed';
      status.style.right = '18px';
      status.style.bottom = '18px';
      status.style.zIndex = '9999';
      status.style.maxWidth = '320px';
      status.style.padding = '12px 14px';
      status.style.borderRadius = '12px';
      status.style.boxShadow = '0 14px 28px rgba(15, 23, 42, 0.18)';
      status.style.font = '600 14px/1.4 Arial, Helvetica, sans-serif';
      status.style.background = '#111827';
      status.style.color = '#fff';
      document.body.appendChild(status);
    }

    if (tone === 'error') {
      status.style.background = '#991b1b';
    } else if (tone === 'success') {
      status.style.background = '#166534';
    } else {
      status.style.background = '#111827';
    }

    status.textContent = message;
  };

  const waitForImages = async (root) => {
    const images = Array.from(root.querySelectorAll('img'));
    await Promise.all(images.map((img) => new Promise((resolve) => {
      if (img.complete) {
        resolve();
        return;
      }
      img.addEventListener('load', resolve, { once: true });
      img.addEventListener('error', resolve, { once: true });
    })));
  };

  const waitForFonts = async () => {
    if (document.fonts && typeof document.fonts.ready?.then === 'function') {
      try {
        await document.fonts.ready;
      } catch (err) {
        console.warn('Report PDF font readiness check failed:', err);
      }
    }
  };

  const nextFrame = () => new Promise((resolve) => window.requestAnimationFrame(() => resolve()));

  const downloadReportPdf = async () => {
    const renderer = window.html2canvas;
    const jsPdfNs = window.jspdf;
    if (typeof renderer !== 'function' || !jsPdfNs?.jsPDF) {
      setStatus('PDF export support failed to load. Please reload and try again.', 'error');
      return;
    }

    try {
      setStatus('Preparing report PDF…');
      await window.__reportPaginationReady;
      const pages = Array.from(document.querySelectorAll('.rp-print-page'));
      if (!pages.length) {
        setStatus('Unable to prepare report pages for PDF export.', 'error');
        return;
      }
      await waitForFonts();
      await waitForImages(document.body);
      await nextFrame();
      await new Promise((resolve) => window.setTimeout(resolve, 450));

      const { jsPDF } = jsPdfNs;
      const pdf = new jsPDF({
        orientation: 'portrait',
        unit: 'pt',
        format: 'letter',
        compress: true,
      });

      const pageWidth = pdf.internal.pageSize.getWidth();
      const pageHeight = pdf.internal.pageSize.getHeight();

      for (let index = 0; index < pages.length; index += 1) {
        const page = pages[index];
        page.style.boxShadow = 'none';
        page.style.border = 'none';
        const canvas = await renderer(page, {
          backgroundColor: '#ffffff',
          scale: Math.max(2, Math.min(3, window.devicePixelRatio || 2)),
          useCORS: true,
          logging: false,
          scrollX: 0,
          scrollY: 0,
          width: page.scrollWidth,
          height: page.scrollHeight,
          windowWidth: page.scrollWidth,
          windowHeight: page.scrollHeight
        });

        if (index > 0) {
          pdf.addPage('letter', 'portrait');
        }
        pdf.addImage(canvas.toDataURL('image/jpeg', 0.96), 'JPEG', 0, 0, pageWidth, pageHeight, undefined, 'FAST');
      }

      const filenameBase = <?= json_encode(strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $currentLabel) ?: 'report'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      const periodSuffix = <?= json_encode(($module !== 'residents')
          ? (trim($dateFrom) !== '' && trim($dateTo) !== '' ? ('_' . preg_replace('/[^0-9-]/', '', $dateFrom) . '_to_' . preg_replace('/[^0-9-]/', '', $dateTo)) : '')
          : ('_' . date('Y-m-d')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      const filename = `${filenameBase}${periodSuffix || ''}.pdf`;

      setStatus('Downloading PDF…');
      pdf.save(filename);
      setStatus('PDF download started. You can close this tab.', 'success');
    } catch (err) {
      console.error('Report PDF export failed:', err);
      setStatus('PDF export failed. Please try again.', 'error');
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', downloadReportPdf, { once: true });
  } else {
    downloadReportPdf();
  }
})();
</script>
<?php endif; ?>
</body>
</html>
<?php exit; ?>
<?php else: /* admin view closing */ ?>
      </div><!-- /#reportPrintArea -->

    </div><!-- /.bg-white reports-shell -->
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function downloadPdf() {
  const url = new URL(window.location.href);
  url.searchParams.set('format', 'print');
  url.searchParams.set('autoprint', '0');
  url.searchParams.set('download', 'pdf');
  const popup = window.open(url.toString(), '_blank', 'width=1100,height=900,scrollbars=yes');
  if (!popup) {
    alert('Please allow popups for this site, then click Download PDF again.');
  }
}

function initReportCustomizeModal() {
  const modal = document.getElementById('reportCustomizeModal');
  if (!modal) {
    return;
  }

  const form = modal.querySelector('form');
  if (!form) {
    return;
  }

  const columnPanel = form.querySelector('[data-customize-column-panel]');
  const sectionInputs = Array.from(form.querySelectorAll('input[name="show_section[]"][data-customize-section-toggle]'));
  const columnGroups = Array.from(form.querySelectorAll('[data-customize-column-group]'));
  const columnInputs = Array.from(form.querySelectorAll('input[name="show_column[]"][data-customize-column-value]'));

  const syncColumnInputs = (source) => {
    columnInputs.forEach((input) => {
      if (input === source || input.value !== source.value) {
        return;
      }
      input.checked = source.checked;
    });
  };

  const updateColumnGroupVisibility = () => {
    const activeSections = new Set(
      sectionInputs
        .filter((input) => input.checked)
        .map((input) => input.value)
    );

    columnGroups.forEach((group) => {
      const sectionKeys = String(group.dataset.sectionKeys || '')
        .split(/\s+/)
        .map((value) => value.trim())
        .filter(Boolean);
      const isVisible = sectionKeys.length === 0 || sectionKeys.some((key) => activeSections.has(key));

      group.hidden = !isVisible;
      group.querySelectorAll('input[name="show_column[]"]').forEach((input) => {
        input.disabled = !isVisible;
      });
    });

    if (columnPanel) {
      columnPanel.hidden = columnGroups.every((group) => group.hidden);
    }
  };

  sectionInputs.forEach((input) => {
    input.addEventListener('change', updateColumnGroupVisibility);
  });
  columnInputs.forEach((input) => {
    input.addEventListener('change', () => syncColumnInputs(input));
  });
  modal.addEventListener('shown.bs.modal', updateColumnGroupVisibility);

  updateColumnGroupVisibility();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initReportCustomizeModal, { once: true });
} else {
  initReportCustomizeModal();
}
</script>
<script src="../../JS-Script-Files/Resident-End/dateFieldModal.js?v=20260707-date-proxy-white"></script>
</body>
</html>
<?php endif; ?>
