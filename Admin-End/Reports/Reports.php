<?php
require_once __DIR__ . '/../../PhpFiles/General/connection.php';
require_once __DIR__ . '/../includes/admin_guard.php';

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

    $key = strtolower(preg_replace('/[^a-z0-9]+/', '', $value));
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

    if (!rp_table_exists($conn, 'residentinformationtbl')) {
        return ['joins' => '', 'sector_expr' => 'NULL', 'area_expr' => 'NULL'];
    }

    if (rp_column_exists($conn, 'documentrequesttbl', 'resident_user_id')) {
        $infoAlias = $prefix . 'iu';
        $joins[] = "LEFT JOIN residentinformationtbl {$infoAlias} ON {$infoAlias}.user_id = {$requestAlias}.resident_user_id";
        $sectorCandidates[] = "NULLIF(TRIM({$infoAlias}.sector_membership), '')";
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

    $normalized = strtolower(preg_replace('/[^a-z]/', '', $raw));
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

function rp_csv_contains_expr(mysqli $conn, string $columnExpr, string $value): string {
    $normalizedValue = strtolower(str_replace(' ', '', rp_normalize_sector_label($value)));
    return "FIND_IN_SET(" . rp_sql_quote($conn, $normalizedValue) . ", REPLACE(REPLACE(LOWER(COALESCE({$columnExpr}, '')), ', ', ','), ' ', '')) > 0";
}

// ── Module routing ────────────────────────────────────────────────────────────
$allowedModules = ['document_requests', 'financial', 'residents', 'appointments', 'blotter', 'complaints'];
$module = strtolower(trim((string)($_GET['module'] ?? 'document_requests')));
if (!in_array($module, $allowedModules, true)) $module = 'document_requests';

// ── Date range (shared) ───────────────────────────────────────────────────────
$today      = date('Y-m-d');
$monthStart = date('Y-m-01');
$dateFrom   = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_from'] ?? '')) ? $_GET['date_from'] : $monthStart;
$dateTo     = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_to']   ?? '')) ? $_GET['date_to']   : $today;
if ($dateTo < $dateFrom) $dateTo = $dateFrom;

$baseUrl = appUrl('Admin-End/Reports/Reports.php');
$reportFilterType = trim((string)($_GET['filter_type'] ?? ''));
$reportFilterArea = trim((string)($_GET['filter_area'] ?? ''));
$reportFilterSector = rp_normalize_sector_label(trim((string)($_GET['filter_sector'] ?? '')));
$officialReportAreaOptions = rp_official_area_options();
$officialReportSectorOptions = rp_official_sector_options();
if ($reportFilterArea !== '' && !array_key_exists($reportFilterArea, $officialReportAreaOptions)) {
    $reportFilterArea = '';
}
if ($reportFilterSector !== '' && !array_key_exists($reportFilterSector, $officialReportSectorOptions)) {
    $reportFilterSector = '';
}
$reportFilterOptions = [
    'type' => [],
    'area' => $officialReportAreaOptions,
    'sector' => $officialReportSectorOptions,
];
$reportFilterLabels = [
    'type' => in_array($module, ['blotter', 'complaints'], true) ? 'Type of Complaint' : 'Type of Request',
    'area' => 'Area Number',
    'sector' => 'Sector Membership',
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
            $preparedByName = trim($pRow['firstname'] . ' ' . $pRow['lastname']);
        }
        $pStmt->close();
    }
}
$reportNotedByName = 'HON. GLENN S. EVANGELISTA';
$reportNotedByRole = 'Punong Barangay';
$barangaySealUrl = appUrl('Images/San_Jose_LOGO.jpg');
$municipalSealUrl = appUrl('Images/Montalban_Logo.png');

// ═══════════════════════════════════════════════════════════════════════════════
// MODULE: DOCUMENT REQUESTS
// ═══════════════════════════════════════════════════════════════════════════════
$dr = [];
if ($module === 'document_requests' && rp_table_exists($conn, 'documentrequesttbl')) {
    $df = $conn->real_escape_string($dateFrom);
    $dt = $conn->real_escape_string($dateTo);
    $requestDateExpr = rp_first_existing_datetime_expr($conn, 'documentrequesttbl', 'd', ['submitted_at', 'request_timestamp', 'created_at']);
    $hasFinanceTable = rp_table_exists($conn, 'financetransactiontbl');
    $residentParts = rp_document_request_resident_parts($conn, 'd', 'dr');
    $financeRollup = $hasFinanceTable ? rp_finance_rollup_subquery($conn, 'ft') : '';
    $financeJoin = $hasFinanceTable ? "LEFT JOIN {$financeRollup} f ON f.request_id = d.request_id" : '';
    $residentJoin = $residentParts['joins'] !== '' ? $residentParts['joins'] : '';
    $areaExpr = $residentParts['area_expr'];
    $sectorExpr = $residentParts['sector_expr'];
    $amountExpr = $hasFinanceTable && rp_column_exists($conn, 'financetransactiontbl', 'transaction_amount')
        ? "COALESCE(f.transaction_amount, 0)"
        : "0";
    $paymentMethodExpr = $hasFinanceTable && rp_column_exists($conn, 'financetransactiontbl', 'payment_method')
        ? "COALESCE(NULLIF(TRIM(f.payment_method), ''), 'Unspecified')"
        : "'Unspecified'";
    $hasRequestData = $requestDateExpr !== 'NULL';
    $requestDateFilter = $hasRequestData
        ? "DATE({$requestDateExpr}) BETWEEN '{$df}' AND '{$dt}'"
        : '1 = 0';
    $trendFilter = $hasRequestData
        ? "{$requestDateExpr} >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)"
        : '1 = 0';
    $rejectedStagesExpr = "('rejected','cancelled','interview_failed','inspection_failed')";
    $collectedStagesExpr = "('payment_verified','ready_for_claim','completed')";
    $docTypeExpr = "COALESCE(NULLIF(TRIM(d.document_type), ''), 'Unspecified')";
    $requestFilterClauses = [$requestDateFilter];
    if ($reportFilterType !== '') {
        $requestFilterClauses[] = "{$docTypeExpr} = " . rp_sql_quote($conn, $reportFilterType);
    }
    if ($reportFilterArea !== '' && $areaExpr !== 'NULL') {
        $requestFilterClauses[] = "{$areaExpr} = " . rp_sql_quote($conn, $reportFilterArea);
    }
    if ($reportFilterSector !== '' && $sectorExpr !== 'NULL') {
        $requestFilterClauses[] = rp_csv_contains_expr($conn, $sectorExpr, $reportFilterSector);
    }
    $requestFilterWhere = implode(' AND ', $requestFilterClauses);
    $reportFilterOptions['type'] = rp_options_from_rows(rp_safe_query($conn, "
        SELECT {$docTypeExpr} AS value
        FROM documentrequesttbl d
        " . ($residentJoin !== '' ? "\n        {$residentJoin}" : '') . "
        WHERE {$requestDateFilter}
        GROUP BY {$docTypeExpr}
        ORDER BY {$docTypeExpr} ASC
    "), 'value', 'rp_document_type_label');
    if ($areaExpr !== 'NULL') {
        $reportFilterOptions['area'] = rp_options_from_rows(rp_safe_query($conn, "
            SELECT {$areaExpr} AS value
            FROM documentrequesttbl d
            {$residentJoin}
            WHERE {$requestDateFilter}
              AND {$areaExpr} IS NOT NULL
              AND {$areaExpr} <> ''
            GROUP BY {$areaExpr}
            ORDER BY {$areaExpr} ASC
        "), 'value');
    }
    if ($sectorExpr !== 'NULL') {
        $reportFilterOptions['sector'] = rp_sector_options_from_rows(rp_safe_query($conn, "
            SELECT {$sectorExpr} AS sector_membership
            FROM documentrequesttbl d
            {$residentJoin}
            WHERE {$requestDateFilter}
              AND {$sectorExpr} IS NOT NULL
              AND {$sectorExpr} <> ''
        "));
    }

    $kpi = rp_safe_query($conn, "
        SELECT
          COUNT(*) AS total,
          SUM(CASE WHEN LOWER(stage) = 'completed' THEN 1 ELSE 0 END) AS completed,
          SUM(CASE WHEN LOWER(stage) IN {$rejectedStagesExpr} THEN 1 ELSE 0 END) AS rejected,
          SUM(CASE WHEN LOWER(stage) <> 'completed' AND LOWER(stage) NOT IN {$rejectedStagesExpr} THEN 1 ELSE 0 END) AS active,
          SUM(CASE WHEN LOWER(stage) IN {$collectedStagesExpr} AND {$amountExpr} > 0 THEN {$amountExpr} ELSE 0 END) AS revenue
        FROM documentrequesttbl
        d
        {$financeJoin}
        " . ($residentJoin !== '' ? "\n        {$residentJoin}" : '') . "
        WHERE {$requestFilterWhere}
    ");
    $dr['kpi'] = $kpi[0] ?? [];

    $dr['by_type'] = rp_safe_query($conn, "
        SELECT
          {$docTypeExpr} AS document_type,
          COUNT(*) AS total,
          SUM(CASE WHEN LOWER(stage)='completed' THEN 1 ELSE 0 END) AS completed,
          SUM(CASE WHEN LOWER(stage) IN {$rejectedStagesExpr} THEN 1 ELSE 0 END) AS rejected,
          SUM(CASE WHEN LOWER(stage) <> 'completed' AND LOWER(stage) NOT IN {$rejectedStagesExpr} THEN 1 ELSE 0 END) AS active,
          SUM(CASE WHEN LOWER(stage) IN {$collectedStagesExpr} AND {$amountExpr}>0 THEN {$amountExpr} ELSE 0 END) AS revenue
        FROM documentrequesttbl d
        {$financeJoin}
        " . ($residentJoin !== '' ? "\n        {$residentJoin}" : '') . "
        WHERE {$requestFilterWhere}
        GROUP BY {$docTypeExpr}
        ORDER BY total DESC
    ");

    $dr['by_stage'] = rp_safe_query($conn, "
        SELECT COALESCE(stage,'Unknown') AS stage, COUNT(*) AS total
        FROM documentrequesttbl d
        " . ($residentJoin !== '' ? "\n        {$residentJoin}" : '') . "
        WHERE {$requestFilterWhere}
        GROUP BY stage ORDER BY total DESC
    ");

    $dr['trend'] = rp_safe_query($conn, "
        SELECT DATE_FORMAT({$requestDateExpr},'%Y-%m') AS month, COUNT(*) AS total,
          SUM(CASE WHEN LOWER(stage)='completed' THEN 1 ELSE 0 END) AS completed
        FROM documentrequesttbl d
        " . ($residentJoin !== '' ? "\n        {$residentJoin}" : '') . "
        WHERE {$trendFilter}" . ($reportFilterType !== '' ? " AND {$docTypeExpr} = " . rp_sql_quote($conn, $reportFilterType) : '') . ($reportFilterArea !== '' && $areaExpr !== 'NULL' ? " AND {$areaExpr} = " . rp_sql_quote($conn, $reportFilterArea) : '') . ($reportFilterSector !== '' && $sectorExpr !== 'NULL' ? " AND " . rp_csv_contains_expr($conn, $sectorExpr, $reportFilterSector) : '') . "
        GROUP BY month ORDER BY month ASC
    ");

    $dr['by_payment'] = $hasFinanceTable ? rp_safe_query($conn, "
        SELECT {$paymentMethodExpr} AS method,
          COUNT(*) AS total,
          SUM({$amountExpr}) AS revenue
        FROM documentrequesttbl d
        LEFT JOIN {$financeRollup} f ON f.request_id = d.request_id
        " . ($residentJoin !== '' ? "\n        {$residentJoin}" : '') . "
        WHERE {$requestFilterWhere}
          AND LOWER(d.stage) IN {$collectedStagesExpr}
          AND {$amountExpr} > 0
        GROUP BY {$paymentMethodExpr}
        ORDER BY total DESC
    ") : [];
}

// ═══════════════════════════════════════════════════════════════════════════════
// MODULE: FINANCIAL
// ═══════════════════════════════════════════════════════════════════════════════
$fin = [];
if ($module === 'financial' && rp_table_exists($conn, 'documentrequesttbl') && rp_table_exists($conn, 'financetransactiontbl')) {
    $df = $conn->real_escape_string($dateFrom);
    $dt = $conn->real_escape_string($dateTo);
    $residentParts = rp_document_request_resident_parts($conn, 'd', 'fin');
    $financeRollup = rp_finance_rollup_subquery($conn, 'ft');
    $residentJoin = $residentParts['joins'] !== '' ? $residentParts['joins'] : '';
    $areaExpr = $residentParts['area_expr'];
    $sectorExpr = $residentParts['sector_expr'];
    $docTypeExpr = "COALESCE(NULLIF(TRIM(d.document_type), ''), 'Unspecified')";
    $financeDateExpr = "NULLIF(f.finance_event_at, '0000-00-00 00:00:00')";
    $financialDateFilter = $financeDateExpr !== 'NULL'
        ? "DATE({$financeDateExpr}) BETWEEN '{$df}' AND '{$dt}'"
        : '1 = 0';
    $financialCollectedFilter = "LOWER(d.stage) IN ('payment_verified', 'ready_for_claim', 'completed') AND COALESCE(f.transaction_amount, 0) > 0";
    $financialFilterClauses = [$financialCollectedFilter, $financialDateFilter];
    if ($reportFilterType !== '') {
        $financialFilterClauses[] = "{$docTypeExpr} = " . rp_sql_quote($conn, $reportFilterType);
    }
    if ($reportFilterArea !== '' && $areaExpr !== 'NULL') {
        $financialFilterClauses[] = "{$areaExpr} = " . rp_sql_quote($conn, $reportFilterArea);
    }
    if ($reportFilterSector !== '' && $sectorExpr !== 'NULL') {
        $financialFilterClauses[] = rp_csv_contains_expr($conn, $sectorExpr, $reportFilterSector);
    }
    $financialWhere = implode(' AND ', $financialFilterClauses);
    $reportFilterOptions['type'] = rp_options_from_rows(rp_safe_query($conn, "
        SELECT {$docTypeExpr} AS value
        FROM documentrequesttbl d
        INNER JOIN {$financeRollup} f ON f.request_id = d.request_id
        " . ($residentJoin !== '' ? "\n        {$residentJoin}" : '') . "
        WHERE {$financialCollectedFilter}
          AND {$financialDateFilter}
        GROUP BY {$docTypeExpr}
        ORDER BY {$docTypeExpr} ASC
    "), 'value', 'rp_document_type_label');
    if ($areaExpr !== 'NULL') {
        $reportFilterOptions['area'] = rp_options_from_rows(rp_safe_query($conn, "
            SELECT {$areaExpr} AS value
            FROM documentrequesttbl d
            INNER JOIN {$financeRollup} f ON f.request_id = d.request_id
            {$residentJoin}
            WHERE {$financialCollectedFilter}
              AND {$financialDateFilter}
              AND {$areaExpr} IS NOT NULL
              AND {$areaExpr} <> ''
            GROUP BY {$areaExpr}
            ORDER BY {$areaExpr} ASC
        "), 'value');
    }
    if ($sectorExpr !== 'NULL') {
        $reportFilterOptions['sector'] = rp_sector_options_from_rows(rp_safe_query($conn, "
            SELECT {$sectorExpr} AS sector_membership
            FROM documentrequesttbl d
            INNER JOIN {$financeRollup} f ON f.request_id = d.request_id
            {$residentJoin}
            WHERE {$financialCollectedFilter}
              AND {$financialDateFilter}
              AND {$sectorExpr} IS NOT NULL
              AND {$sectorExpr} <> ''
        "));
    }

    $fin['kpi'] = rp_safe_query($conn, "
        SELECT
          COUNT(*) AS total_issued,
          SUM(COALESCE(f.transaction_amount, 0)) AS total_collections,
          SUM(CASE WHEN LOWER(COALESCE(f.payment_method, ''))='gcash' THEN COALESCE(f.transaction_amount, 0) ELSE 0 END) AS gcash_total,
          SUM(CASE WHEN LOWER(COALESCE(f.payment_method, '')) IN ('barangay', 'walk_in', 'walkin', 'cash') THEN COALESCE(f.transaction_amount, 0) ELSE 0 END) AS walkin_total,
          SUM(CASE WHEN COALESCE(f.or_number, '') <> '' THEN 1 ELSE 0 END) AS or_count
        FROM documentrequesttbl d
        INNER JOIN {$financeRollup} f ON f.request_id = d.request_id
        " . ($residentJoin !== '' ? "\n        {$residentJoin}" : '') . "
        WHERE {$financialWhere}
    ");
    $fin['kpi'] = $fin['kpi'][0] ?? [];

    $fin['daily_log'] = rp_safe_query($conn, "
        SELECT
          DATE({$financeDateExpr}) AS collection_date,
          COUNT(*) AS count,
          SUM(COALESCE(f.transaction_amount, 0)) AS total,
          SUM(CASE WHEN LOWER(COALESCE(f.payment_method, ''))='gcash' THEN COALESCE(f.transaction_amount, 0) ELSE 0 END) AS gcash,
          SUM(CASE WHEN LOWER(COALESCE(f.payment_method, '')) IN ('barangay', 'walk_in', 'walkin', 'cash') THEN COALESCE(f.transaction_amount, 0) ELSE 0 END) AS walkin
        FROM documentrequesttbl d
        INNER JOIN {$financeRollup} f ON f.request_id = d.request_id
        " . ($residentJoin !== '' ? "\n        {$residentJoin}" : '') . "
        WHERE {$financialWhere}
        GROUP BY collection_date ORDER BY collection_date ASC
    ");

    $fin['by_type'] = rp_safe_query($conn, "
        SELECT
          {$docTypeExpr} AS document_type,
          COUNT(*) AS count,
          SUM(COALESCE(f.transaction_amount, 0)) AS total
        FROM documentrequesttbl d
        INNER JOIN {$financeRollup} f ON f.request_id = d.request_id
        " . ($residentJoin !== '' ? "\n        {$residentJoin}" : '') . "
        WHERE {$financialWhere}
        GROUP BY {$docTypeExpr}
        ORDER BY total DESC
    ");

    $fin['or_log'] = rp_safe_query($conn, "
        SELECT f.or_number, d.certificate_number,
          TRIM(CONCAT_WS(' ',
            NULLIF(TRIM(COALESCE(f.applicant_firstname,'')), ''),
            NULLIF(TRIM(COALESCE(f.applicant_middleInitial,'')), ''),
            NULLIF(TRIM(COALESCE(f.applicant_lastname,'')), '')
          )) AS resident_name,
          COALESCE(NULLIF(TRIM(d.document_type), ''),'—') AS document_type,
          COALESCE(f.transaction_amount, 0) AS fee_amount,
          UPPER(COALESCE(f.payment_method,'—')) AS payment_method,
          {$financeDateExpr} AS finance_decision_at
        FROM documentrequesttbl d
        INNER JOIN {$financeRollup} f ON f.request_id = d.request_id
        " . ($residentJoin !== '' ? "\n        {$residentJoin}" : '') . "
        WHERE {$financialWhere}
          AND COALESCE(f.or_number, '') <> ''
        ORDER BY {$financeDateExpr} ASC
        LIMIT 500
    ");
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
    $residentFilterClauses = [];
    if ($reportFilterArea !== '' && $residentAreaExpr !== 'NULL') {
        $residentFilterClauses[] = "{$residentAreaExpr} = " . rp_sql_quote($conn, $reportFilterArea);
    }
    if ($reportFilterSector !== '') {
        $residentFilterClauses[] = rp_csv_contains_expr($conn, $residentSectorExpr, $reportFilterSector);
    }
    $residentFilterSql = $residentFilterClauses ? ' AND ' . implode(' AND ', $residentFilterClauses) : '';
    if ($residentAreaExpr !== 'NULL') {
        $reportFilterOptions['area'] = rp_options_from_rows(rp_safe_query($conn, "
            SELECT {$residentAreaExpr} AS value
            FROM residentinformationtbl ri
            {$residentAddressJoin}
            WHERE {$residentAreaExpr} IS NOT NULL
              AND {$residentAreaExpr} <> ''
            GROUP BY {$residentAreaExpr}
            ORDER BY {$residentAreaExpr} ASC
        "), 'value');
    }
    $reportFilterOptions['sector'] = rp_sector_options_from_rows(rp_safe_query($conn, "
        SELECT {$residentSectorExpr} AS sector_membership
        FROM residentinformationtbl ri
        WHERE {$residentSectorExpr} IS NOT NULL
          AND {$residentSectorExpr} <> ''
    "));

    $res['kpi'] = rp_safe_query($conn, "
        SELECT
          COUNT(*) AS total,
          SUM(CASE WHEN s.status_name='VerifiedResident'   THEN 1 ELSE 0 END) AS verified,
          SUM(CASE WHEN s.status_name='PendingVerification' THEN 1 ELSE 0 END) AS pending,
          SUM(CASE WHEN s.status_name='NotVerified'        THEN 1 ELSE 0 END) AS not_verified,
          SUM(CASE WHEN s.status_name='ArchivedResident'   THEN 1 ELSE 0 END) AS archived
        FROM residentinformationtbl ri
        JOIN statuslookuptbl s ON s.status_id = ri.status_id_resident
        " . ($residentAddressJoin !== '' ? "\n        {$residentAddressJoin}" : '') . "
        WHERE 1=1 {$residentFilterSql}
    ");
    $res['kpi'] = $res['kpi'][0] ?? [];

    $res['by_gender'] = rp_safe_query($conn, "
        SELECT COALESCE(LOWER(ri.sex),'unspecified') AS gender, COUNT(*) AS total
        FROM residentinformationtbl ri
        JOIN statuslookuptbl s ON s.status_id = ri.status_id_resident
        WHERE s.status_name = 'VerifiedResident'
        {$residentFilterSql}
        GROUP BY gender ORDER BY total DESC
    ");

    $allBirthdays = rp_safe_query($conn, "
        SELECT ri.birthdate AS birthdate FROM residentinformationtbl ri
        JOIN statuslookuptbl s ON s.status_id = ri.status_id_resident
        " . ($residentAddressJoin !== '' ? "\n        {$residentAddressJoin}" : '') . "
        WHERE s.status_name='VerifiedResident' AND ri.birthdate IS NOT NULL AND ri.birthdate != ''
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
            WHERE s.status_name = 'VerifiedResident'
            {$residentFilterSql}
            GROUP BY area ORDER BY total DESC
        ");
    }

    $sectorRows = rp_safe_query($conn, "
        SELECT {$residentSectorExpr} AS sector_membership FROM residentinformationtbl ri
        JOIN statuslookuptbl s ON s.status_id = ri.status_id_resident
        " . ($residentAddressJoin !== '' ? "\n        {$residentAddressJoin}" : '') . "
        WHERE s.status_name='VerifiedResident' AND {$residentSectorExpr} IS NOT NULL AND {$residentSectorExpr} <> ''
        {$residentFilterSql}
    ");
    $sectors = [];
    foreach ($sectorRows as $r) {
        foreach (array_map('trim', explode(',', $r['sector_membership'])) as $sk) {
            if ($sk !== '') $sectors[$sk] = ($sectors[$sk] ?? 0) + 1;
        }
    }
    arsort($sectors);
    $res['by_sector'] = $sectors;

    $res['monthly_reg'] = rp_safe_query($conn, "
        SELECT DATE_FORMAT(ri.created_at,'%Y-%m') AS month, COUNT(*) AS total
        FROM residentinformationtbl ri
        " . ($residentAddressJoin !== '' ? "\n        {$residentAddressJoin}" : '') . "
        WHERE ri.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        {$residentFilterSql}
        GROUP BY month ORDER BY month ASC
    ");
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
    if ($reportFilterType !== '') {
        $appointmentFilterClauses[] = "{$appointmentTypeExpr} = " . rp_sql_quote($conn, $reportFilterType);
    }
    if ($reportFilterArea !== '' && $appointmentAreaExpr !== 'NULL') {
        $appointmentFilterClauses[] = "{$appointmentAreaExpr} = " . rp_sql_quote($conn, $reportFilterArea);
    }
    if ($reportFilterSector !== '' && $appointmentSectorExpr !== 'NULL') {
        $appointmentFilterClauses[] = rp_csv_contains_expr($conn, $appointmentSectorExpr, $reportFilterSector);
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

    $appt['trend'] = rp_safe_query($conn, "
        SELECT DATE_FORMAT(request_timestamp,'%Y-%m') AS month, COUNT(*) AS total,
          SUM(CASE WHEN s.status_name='Completed' THEN 1 ELSE 0 END) AS completed
        FROM appointmentstbl a
        JOIN statuslookuptbl s ON s.status_id = a.appointment_status_id
        " . ($appointmentJoin !== '' ? "\n        {$appointmentJoin}" : '') . "
        WHERE a.request_timestamp >= DATE_SUB(NOW(), INTERVAL 12 MONTH)" . ($reportFilterType !== '' ? " AND {$appointmentTypeExpr} = " . rp_sql_quote($conn, $reportFilterType) : '') . ($reportFilterArea !== '' && $appointmentAreaExpr !== 'NULL' ? " AND {$appointmentAreaExpr} = " . rp_sql_quote($conn, $reportFilterArea) : '') . ($reportFilterSector !== '' && $appointmentSectorExpr !== 'NULL' ? " AND " . rp_csv_contains_expr($conn, $appointmentSectorExpr, $reportFilterSector) : '') . "
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
    $caseResidentParts = rp_column_exists($conn, 'casereportstbl', 'resident_user_id')
        ? rp_user_resident_parts($conn, 'c.resident_user_id', 'blt')
        : ['joins' => '', 'sector_expr' => 'NULL', 'area_expr' => 'NULL'];
    $caseJoin = $caseResidentParts['joins'] !== '' ? $caseResidentParts['joins'] : '';
    $caseAreaExpr = $caseResidentParts['area_expr'];
    $caseSectorExpr = $caseResidentParts['sector_expr'];
    $complaintTypeExpr = "COALESCE(NULLIF(TRIM(c.complaint_type), ''), 'Not specified')";
    $blotterFilterClauses = ["DATE(c.created_at) BETWEEN '{$df}' AND '{$dt}'"];
    if ($reportFilterType !== '') {
        $blotterFilterClauses[] = "{$complaintTypeExpr} = " . rp_sql_quote($conn, $reportFilterType);
    }
    if ($reportFilterArea !== '' && $caseAreaExpr !== 'NULL') {
        $blotterFilterClauses[] = "{$caseAreaExpr} = " . rp_sql_quote($conn, $reportFilterArea);
    }
    if ($reportFilterSector !== '' && $caseSectorExpr !== 'NULL') {
        $blotterFilterClauses[] = rp_csv_contains_expr($conn, $caseSectorExpr, $reportFilterSector);
    }
    $blotterWhere = implode(' AND ', $blotterFilterClauses);
    $reportFilterOptions['type'] = rp_options_from_rows(rp_safe_query($conn, "
        SELECT {$complaintTypeExpr} AS value
        FROM casereportstbl c
        " . ($caseJoin !== '' ? "\n        {$caseJoin}" : '') . "
        WHERE DATE(c.created_at) BETWEEN '{$df}' AND '{$dt}'
        GROUP BY {$complaintTypeExpr}
        ORDER BY {$complaintTypeExpr} ASC
    "), 'value');
    if ($caseAreaExpr !== 'NULL') {
        $reportFilterOptions['area'] = rp_options_from_rows(rp_safe_query($conn, "
            SELECT {$caseAreaExpr} AS value
            FROM casereportstbl c
            {$caseJoin}
            WHERE DATE(c.created_at) BETWEEN '{$df}' AND '{$dt}'
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
            WHERE DATE(c.created_at) BETWEEN '{$df}' AND '{$dt}'
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

    $blot['trend'] = rp_safe_query($conn, "
        SELECT DATE_FORMAT(c.created_at,'%Y-%m') AS month, COUNT(*) AS total
        FROM casereportstbl c
        " . ($caseJoin !== '' ? "\n        {$caseJoin}" : '') . "
        WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)" . ($reportFilterType !== '' ? " AND {$complaintTypeExpr} = " . rp_sql_quote($conn, $reportFilterType) : '') . ($reportFilterArea !== '' && $caseAreaExpr !== 'NULL' ? " AND {$caseAreaExpr} = " . rp_sql_quote($conn, $reportFilterArea) : '') . ($reportFilterSector !== '' && $caseSectorExpr !== 'NULL' ? " AND " . rp_csv_contains_expr($conn, $caseSectorExpr, $reportFilterSector) : '') . "
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
    if ($reportFilterType !== '' && $hasCaseTable) {
        $complaintFilterClauses[] = "{$complaintTypeExpr} = " . rp_sql_quote($conn, $reportFilterType);
    }
    if ($reportFilterArea !== '' && $complaintAreaExpr !== 'NULL') {
        $complaintFilterClauses[] = "{$complaintAreaExpr} = " . rp_sql_quote($conn, $reportFilterArea);
    }
    if ($reportFilterSector !== '' && $complaintSectorExpr !== 'NULL') {
        $complaintFilterClauses[] = rp_csv_contains_expr($conn, $complaintSectorExpr, $reportFilterSector);
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

    $comp['by_kind'] = rp_safe_query($conn, "
        SELECT COALESCE(subject_kind,'Unknown') AS kind, COUNT(*) AS total
        FROM complaintstbl ct
        {$complaintCaseJoin}
        " . ($complaintResidentJoin !== '' ? "\n        {$complaintResidentJoin}" : '') . "
        WHERE {$complaintWhere}
        GROUP BY subject_kind ORDER BY total DESC
    ");

    $comp['trend'] = rp_safe_query($conn, "
        SELECT DATE_FORMAT(ct.created_at,'%Y-%m') AS month, COUNT(*) AS total,
          SUM(CASE WHEN escalated_to_blotter=1 THEN 1 ELSE 0 END) AS escalated
        FROM complaintstbl ct
        {$complaintCaseJoin}
        " . ($complaintResidentJoin !== '' ? "\n        {$complaintResidentJoin}" : '') . "
        WHERE ct.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)" . ($reportFilterType !== '' && $hasCaseTable ? " AND {$complaintTypeExpr} = " . rp_sql_quote($conn, $reportFilterType) : '') . ($reportFilterArea !== '' && $complaintAreaExpr !== 'NULL' ? " AND {$complaintAreaExpr} = " . rp_sql_quote($conn, $reportFilterArea) : '') . ($reportFilterSector !== '' && $complaintSectorExpr !== 'NULL' ? " AND " . rp_csv_contains_expr($conn, $complaintSectorExpr, $reportFilterSector) : '') . "
        GROUP BY month ORDER BY month ASC
    ");
}

// ── Module labels ─────────────────────────────────────────────────────────────
$moduleLabels = [
    'document_requests' => ['label' => 'Document Requests', 'icon' => 'fa-file-circle-check'],
    'financial'         => ['label' => 'Financial / Collections', 'icon' => 'fa-peso-sign'],
    'residents'         => ['label' => 'Residents', 'icon' => 'fa-users'],
    'appointments'      => ['label' => 'Appointments', 'icon' => 'fa-calendar-check'],
    'blotter'           => ['label' => 'Blotter & Cases', 'icon' => 'fa-gavel'],
    'complaints'        => ['label' => 'Complaints & Grievances', 'icon' => 'fa-comments'],
];
$currentLabel = $moduleLabels[$module]['label'];
$isPrintView  = ($_GET['format'] ?? '') === 'print';
$reportLeftLogo = '../../Images/San_Jose_LOGO.jpg';
$reportRightLogo = '../../Images/Montalban_Logo.png';
$reportFilterOptions['area'] = $officialReportAreaOptions;
$reportFilterOptions['sector'] = $officialReportSectorOptions;
$activeReportFilters = [];
if ($reportFilterType !== '') {
    $activeReportFilters[] = $reportFilterLabels['type'] . ': ' . ($reportFilterOptions['type'][$reportFilterType] ?? $reportFilterType);
}
if ($reportFilterArea !== '') {
    $activeReportFilters[] = $reportFilterLabels['area'] . ': ' . ($reportFilterOptions['area'][$reportFilterArea] ?? $reportFilterArea);
}
if ($reportFilterSector !== '') {
    $activeReportFilters[] = $reportFilterLabels['sector'] . ': ' . ($reportFilterOptions['sector'][$reportFilterSector] ?? $reportFilterSector);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $isPrintView ? htmlspecialchars($currentLabel).' — Barangay San Jose' : 'Reports' ?></title>
  <?php if (!$isPrintView): ?>
  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css">
  <?php endif; ?>
  <style>
    @page { size: letter portrait; margin: 1in; }
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
    }
    .rp-filter-modal .modal-header {
      border-bottom: 1px solid #e5e7eb;
      padding: 1rem 1.25rem;
    }
    .rp-filter-modal .modal-body {
      padding: 1.25rem;
    }
    .rp-filter-modal .modal-footer {
      border-top: 1px solid #e5e7eb;
      padding: 1rem 1.25rem;
    }

    /* ── Formal report document ─────────────────────────────────────────────── */
    .rp-doc {
      background: #fff;
      border: 1.5px solid #2f2f2f;
      border-radius: 0;
      box-sizing: border-box;
      max-width: 8.27in;
      min-height: 10.75in;
      margin: 0 auto;
      padding: 34px 36px 40px;
      box-shadow: 0 14px 28px rgba(15, 23, 42, 0.08);
      font-size: 13px;
      line-height: 1.45;
      color: #1a1a1a;
    }

    /* Header */
    .rp-doc-header {
      text-align: center;
      padding-bottom: 0;
      margin-bottom: 24px;
    }
    .rp-letterhead {
      display: grid;
      grid-template-columns: 110px 1fr 110px;
      align-items: center;
      gap: 14px;
      margin-bottom: 0;
    }
    .rp-letterhead-logo {
      width: 98px;
      height: 98px;
      object-fit: contain;
      justify-self: center;
      display: block;
    }
    .rp-letterhead-center {
      text-align: center;
      color: #000;
      line-height: 1.18;
      font-family: "Times New Roman", Times, serif;
    }
    .rp-letterhead-center p {
      margin: 0;
      font-size: 15px;
      font-weight: 700;
      text-transform: uppercase;
    }
    .rp-letterhead-rep {
      font-size: 18px !important;
    }
    .rp-letterhead-barangay {
      font-size: 28px !important;
      margin-top: 8px !important;
      letter-spacing: .03em;
    }
    .rp-letterhead-line {
      border-bottom: 2px solid #9ca3af;
      margin-top: 10px;
    }
    .rp-doc-header .rp-report-title {
      font-size: 15px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: #9a3412;
      margin-top: 16px;
    }
    .rp-doc-header .rp-period { font-size: 12px; color: #4b5563; margin-top: 4px; }
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
      white-space: nowrap;
      font-size: 11.5px;
      text-transform: uppercase;
      letter-spacing: .02em;
    }
    .rp-table td {
      padding: 6px 10px;
      border: 1px solid #d0d7de;
      vertical-align: middle;
    }
    .rp-table tr:nth-child(even) td { background: #fafbfc; }
    .rp-table tfoot td {
      background: #f0f3f7;
      font-weight: 700;
      border-top: 2px solid #adb5bd;
    }
    .rp-table .text-end { text-align: right; }
    .rp-table .text-center { text-align: center; }
    .rp-table .pct { color: #6b7280; font-size: 11px; }

    /* Two-column layout for side-by-side tables */
    .rp-two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
    .rp-three-col { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
    @media (max-width: 768px) {
      .rp-two-col, .rp-three-col { grid-template-columns: 1fr; }
    }

    /* Signature block */
    .rp-footer {
      margin-top: 36px;
      padding-top: 18px;
      border-top: 1.5px solid #4b5563;
    }
    .rp-footer-meta { font-size: 11.5px; color: #555; margin-bottom: 28px; }
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
      max-width: 100%;
      min-height: auto;
      padding: 0;
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
        border: 1.25pt solid #2f2f2f !important;
        border-radius: 0 !important;
        padding: 18pt 18pt 22pt !important;
        box-shadow: none !important;
        max-width: 100% !important;
        font-size: 10pt !important;
        min-height: auto !important;
      }
      .rp-doc-header,
      .rp-footer { display: block !important; }
      .rp-doc-header { padding-bottom: 0 !important; margin-bottom: 14pt !important; }
      .rp-letterhead { grid-template-columns: 84pt 1fr 84pt !important; gap: 10pt !important; }
      .rp-letterhead-logo { width: 74pt !important; height: 74pt !important; }
      .rp-letterhead-center p { font-size: 11pt !important; }
      .rp-letterhead-rep { font-size: 13pt !important; }
      .rp-letterhead-barangay { font-size: 22pt !important; margin-top: 5pt !important; }
      .rp-letterhead-line { margin-top: 8pt !important; }
      .rp-doc-header .rp-report-title { font-size: 11pt !important; }
      .rp-section { margin-top: 14pt !important; page-break-inside: avoid; }
      .rp-section-label { font-size: 9pt !important; padding: 3pt 8pt !important; }
      .rp-summary td, .rp-table th, .rp-table td { font-size: 9pt !important; padding: 4pt 7pt !important; }
      .rp-table tr { page-break-inside: avoid; }
      .rp-two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16pt; }
      .rp-footer { margin-top: 24pt !important; }
      .rp-sig-grid { gap: 32pt !important; }
    }

    /* Standalone print view — applied directly (no @media wrapper needed) */
    <?php if ($isPrintView): ?>
    html, body { margin: 0; padding: 0; background: #fff; font-family: Arial, Helvetica, sans-serif; }
    .rp-doc { border: 1.25pt solid #2f2f2f !important; border-radius: 0 !important; padding: 18pt 18pt 22pt !important;
              box-shadow: none !important; max-width: 100% !important; font-size: 10pt !important; min-height: auto !important; }
    .rp-doc-header { padding-bottom: 0 !important; margin-bottom: 14pt !important; }
    .rp-letterhead { grid-template-columns: 84pt 1fr 84pt !important; gap: 10pt !important; }
    .rp-letterhead-logo { width: 74pt !important; height: 74pt !important; }
    .rp-letterhead-center p { font-size: 11pt !important; }
    .rp-letterhead-rep { font-size: 13pt !important; }
    .rp-letterhead-barangay { font-size: 22pt !important; margin-top: 5pt !important; }
    .rp-letterhead-line { margin-top: 8pt !important; }
    .rp-doc-header .rp-report-title { font-size: 11pt !important; }
    .rp-section { margin-top: 14pt !important; page-break-inside: avoid; }
    .rp-section-label { font-size: 9pt !important; padding: 3pt 8pt !important; }
    .rp-summary td, .rp-table th, .rp-table td { font-size: 9pt !important; padding: 4pt 7pt !important; }
    .rp-table tr { page-break-inside: avoid; }
    .rp-two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16pt; }
    .rp-footer { margin-top: 24pt !important; }
    .rp-sig-grid { gap: 32pt !important; }
    <?php endif; ?>
  </style>
</head>
<?php if ($isPrintView): ?>
<body onload="window.print();">
<?php else: /* ── Full admin layout ── */ ?>
<body>
<div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
      <h2 class="mb-0" style="font-family: 'Charis SIL Bold'; color: #DE710C;">Reports</h2>
      <div class="d-flex gap-2 d-print-none">
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
          <i class="fas fa-print me-1"></i>Print
        </button>
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
          $reportResetUrl = $baseUrl . '?module=' . rawurlencode($module);
          $screenReportFilters = [];
          if ($module !== 'residents') {
            $screenReportFilters[] = 'From: ' . rp_date_label($dateFrom);
            $screenReportFilters[] = 'To: ' . rp_date_label($dateTo);
          } else {
            $screenReportFilters[] = 'As of: ' . date('F j, Y');
          }
          if ($reportFilterType !== '') {
            $screenReportFilters[] = $reportFilterLabels['type'] . ': ' . ($reportFilterOptions['type'][$reportFilterType] ?? $reportFilterType);
          }
          if ($reportFilterArea !== '') {
            $screenReportFilters[] = $reportFilterLabels['area'] . ': ' . ($reportFilterOptions['area'][$reportFilterArea] ?? $reportFilterArea);
          }
          if ($reportFilterSector !== '') {
            $screenReportFilters[] = $reportFilterLabels['sector'] . ': ' . ($reportFilterOptions['sector'][$reportFilterSector] ?? $reportFilterSector);
          }
          $selectedFilterCount = 0;
          if ($reportFilterType !== '') $selectedFilterCount++;
          if ($reportFilterArea !== '') $selectedFilterCount++;
          if ($reportFilterSector !== '') $selectedFilterCount++;
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
            <a href="<?= htmlspecialchars($reportResetUrl) ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
          </div>
        </div>
      </div><!-- /.rp-controls -->

      <div class="modal fade rp-filter-modal" id="reportFilterModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
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
                <div class="rp-filter-grid">
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
                  <?php if ($hasTypeFilter): ?>
                  <div>
                    <label class="form-label small fw-semibold mb-1"><?= htmlspecialchars($reportFilterLabels['type']) ?></label>
                    <select name="filter_type" class="form-select form-select-sm">
                      <option value="">All</option>
                      <?php foreach ($reportFilterOptions['type'] as $value => $label): ?>
                      <option value="<?= htmlspecialchars($value) ?>" <?= $reportFilterType === (string)$value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <?php endif; ?>
                  <?php if ($hasAreaFilter): ?>
                  <div>
                    <label class="form-label small fw-semibold mb-1"><?= htmlspecialchars($reportFilterLabels['area']) ?></label>
                    <select name="filter_area" class="form-select form-select-sm">
                      <option value="">All</option>
                      <?php foreach ($reportFilterOptions['area'] as $value => $label): ?>
                      <option value="<?= htmlspecialchars($value) ?>" <?= $reportFilterArea === (string)$value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <?php endif; ?>
                  <?php if ($hasSectorFilter): ?>
                  <div>
                    <label class="form-label small fw-semibold mb-1"><?= htmlspecialchars($reportFilterLabels['sector']) ?></label>
                    <select name="filter_sector" class="form-select form-select-sm">
                      <option value="">All</option>
                      <?php foreach ($reportFilterOptions['sector'] as $value => $label): ?>
                      <option value="<?= htmlspecialchars($value) ?>" <?= $reportFilterSector === (string)$value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                      <?php endforeach; ?>
                    </select>
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
          <div class="rp-report-title"><?= htmlspecialchars(strtoupper($currentLabel)) ?> Statistical Report</div>
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
          <div class="rp-report-meta">
            Generated: <?= date('F j, Y \a\t g:i A') ?> &nbsp;·&nbsp; Prepared by: <?= htmlspecialchars($preparedByName) ?>
          </div>
        </div>

<?php
// ══════════════════════════════════════════════════════════════════════════════
// RENDER: DOCUMENT REQUESTS
// ══════════════════════════════════════════════════════════════════════════════
if ($module === 'document_requests'):
  $kpi   = $dr['kpi']        ?? [];
  $total = (int)($kpi['total'] ?? 0);
  if ($total <= 0 && !empty($dr['by_stage'])) {
    $total = (int)array_sum(array_map(static fn($row): int => (int)($row['total'] ?? 0), $dr['by_stage']));
  }
?>
        <!-- I. Summary -->
        <div class="rp-section">
          <div class="rp-section-label">I. Overall Summary</div>
          <table class="rp-summary">
            <tbody>
              <tr><td>Total Document Requests Filed</td><td><?= number_format($total) ?></td></tr>
              <tr><td>Completed</td><td><?= number_format((int)($kpi['completed'] ?? 0)) ?> &nbsp;<span class="pct">(<?= rp_pct((int)($kpi['completed']??0),$total) ?>)</span></td></tr>
              <tr><td>Active / In-Progress</td><td><?= number_format((int)($kpi['active'] ?? 0)) ?> &nbsp;<span class="pct">(<?= rp_pct((int)($kpi['active']??0),$total) ?>)</span></td></tr>
              <tr><td>Rejected</td><td><?= number_format((int)($kpi['rejected'] ?? 0)) ?> &nbsp;<span class="pct">(<?= rp_pct((int)($kpi['rejected']??0),$total) ?>)</span></td></tr>
              <tr><td>Total Revenue Collected</td><td>&#8369;<?= number_format((float)($kpi['revenue'] ?? 0), 2) ?></td></tr>
            </tbody>
          </table>
        </div>

        <!-- II. By Document Type -->
        <div class="rp-section">
          <div class="rp-section-label">II. Breakdown by Document Type</div>
          <?php if (empty($dr['by_type'])): ?>
            <p class="rp-empty">No data for the selected period.</p>
          <?php else: ?>
          <table class="rp-table">
            <thead>
              <tr>
                <th>Document Type</th>
                <th class="text-center">Total</th>
                <th class="text-center">%</th>
                <th class="text-center">Completed</th>
                <th class="text-center">Active</th>
                <th class="text-center">Rejected</th>
                <th class="text-end">Revenue</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($dr['by_type'] as $r): ?>
              <tr>
                <td><?= htmlspecialchars(rp_document_type_label((string)$r['document_type'])) ?></td>
                <td class="text-center"><?= number_format((int)$r['total']) ?></td>
                <td class="text-center pct"><?= rp_pct((int)$r['total'], $total) ?></td>
                <td class="text-center"><?= number_format((int)$r['completed']) ?></td>
                <td class="text-center"><?= number_format((int)$r['active']) ?></td>
                <td class="text-center"><?= number_format((int)$r['rejected']) ?></td>
                <td class="text-end">&#8369;<?= number_format((float)$r['revenue'], 2) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td><strong>TOTAL</strong></td>
                <td class="text-center"><?= number_format($total) ?></td>
                <td class="text-center">100%</td>
                <td class="text-center"><?= number_format((int)($kpi['completed']??0)) ?></td>
                <td class="text-center"><?= number_format((int)($kpi['active']??0)) ?></td>
                <td class="text-center"><?= number_format((int)($kpi['rejected']??0)) ?></td>
                <td class="text-end">&#8369;<?= number_format((float)($kpi['revenue']??0),2) ?></td>
              </tr>
            </tfoot>
          </table>
          <?php endif; ?>
        </div>

        <!-- III. Stage Distribution + IV. Payment Methods — side by side -->
        <div class="rp-two-col" style="margin-top:22px;">
          <div>
            <div class="rp-section-label">III. Stage Distribution</div>
            <?php if (empty($dr['by_stage'])): ?>
              <p class="rp-empty">No data.</p>
            <?php else: ?>
            <table class="rp-table">
              <thead><tr><th>Stage</th><th class="text-center">Count</th><th class="text-center">%</th></tr></thead>
              <tbody>
                <?php foreach ($dr['by_stage'] as $r): ?>
                <tr>
                  <td><?= htmlspecialchars(rp_stage_label((string)$r['stage'])) ?></td>
                  <td class="text-center"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center pct"><?= rp_pct((int)$r['total'], $total) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr><td><strong>TOTAL</strong></td><td class="text-center"><?= number_format($total) ?></td><td class="text-center">100%</td></tr></tfoot>
            </table>
            <?php endif; ?>
          </div>
          <div>
            <div class="rp-section-label">IV. Payment Method (Collected)</div>
            <?php if (empty($dr['by_payment'])): ?>
              <p class="rp-empty">No collected payments in the selected period.</p>
            <?php else:
              $payTotal = array_sum(array_column($dr['by_payment'], 'total'));
              $payRev   = array_sum(array_column($dr['by_payment'], 'revenue'));
            ?>
            <table class="rp-table">
              <thead><tr><th>Method</th><th class="text-center">Count</th><th class="text-center">%</th><th class="text-end">Revenue</th></tr></thead>
              <tbody>
                <?php foreach ($dr['by_payment'] as $r): ?>
                <tr>
                  <td><?= htmlspecialchars(rp_payment_method_label((string)$r['method'])) ?></td>
                  <td class="text-center"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center pct"><?= rp_pct((int)$r['total'], (int)$payTotal) ?></td>
                  <td class="text-end">&#8369;<?= number_format((float)$r['revenue'],2) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr><td><strong>TOTAL</strong></td><td class="text-center"><?= number_format($payTotal) ?></td><td class="text-center">100%</td><td class="text-end">&#8369;<?= number_format($payRev,2) ?></td></tr></tfoot>
            </table>
            <?php endif; ?>
          </div>
        </div>

        <!-- V. Monthly Trend -->
        <div class="rp-section">
          <div class="rp-section-label">V. Monthly Trend (Last 12 Months)</div>
          <?php if (empty($dr['trend'])): ?>
            <p class="rp-empty">No trend data available.</p>
          <?php else:
            $tTotal = array_sum(array_column($dr['trend'], 'total'));
            $tComp  = array_sum(array_column($dr['trend'], 'completed'));
          ?>
          <table class="rp-table">
            <thead><tr><th>Month</th><th class="text-center">Total Filed</th><th class="text-center">Completed</th><th class="text-center">%</th></tr></thead>
            <tbody>
              <?php foreach ($dr['trend'] as $r): ?>
              <tr>
                <td><?= htmlspecialchars(date('F Y', strtotime($r['month'].'-01'))) ?></td>
                <td class="text-center"><?= number_format((int)$r['total']) ?></td>
                <td class="text-center"><?= number_format((int)$r['completed']) ?></td>
                <td class="text-center pct"><?= rp_pct((int)$r['completed'], (int)$r['total']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td><strong>TOTAL</strong></td><td class="text-center"><?= number_format($tTotal) ?></td><td class="text-center"><?= number_format($tComp) ?></td><td class="text-center pct"><?= rp_pct($tComp, $tTotal) ?></td></tr></tfoot>
          </table>
          <?php endif; ?>
        </div>

<?php
// ══════════════════════════════════════════════════════════════════════════════
// RENDER: FINANCIAL
// ══════════════════════════════════════════════════════════════════════════════
elseif ($module === 'financial'):
  $kpi = $fin['kpi'] ?? [];
?>
        <!-- I. Summary -->
        <div class="rp-section">
          <div class="rp-section-label">I. Collection Summary</div>
          <table class="rp-summary">
            <tbody>
              <tr><td>Total Collected Transactions</td><td><?= number_format((int)($kpi['total_issued']??0)) ?></td></tr>
              <tr><td>Total Collections</td><td>&#8369;<?= number_format((float)($kpi['total_collections']??0),2) ?></td></tr>
              <tr><td>&nbsp;&nbsp;&nbsp; ↳ GCash Collections</td><td>&#8369;<?= number_format((float)($kpi['gcash_total']??0),2) ?> &nbsp;<span class="pct">(<?= rp_pct((float)($kpi['gcash_total']??0),(float)($kpi['total_collections']??0)) ?>)</span></td></tr>
              <tr><td>&nbsp;&nbsp;&nbsp; ↳ Walk-in / Barangay Collections</td><td>&#8369;<?= number_format((float)($kpi['walkin_total']??0),2) ?> &nbsp;<span class="pct">(<?= rp_pct((float)($kpi['walkin_total']??0),(float)($kpi['total_collections']??0)) ?>)</span></td></tr>
              <tr><td>Official Receipts (OR) Issued</td><td><?= number_format((int)($kpi['or_count']??0)) ?></td></tr>
            </tbody>
          </table>
        </div>

        <!-- II. By Document Type -->
        <div class="rp-section">
          <div class="rp-section-label">II. Collections by Document Type</div>
          <?php if (empty($fin['by_type'])): ?>
            <p class="rp-empty">No data for the selected period.</p>
          <?php else:
            $typeTotal = array_sum(array_column($fin['by_type'], 'total'));
            $typeRev   = array_sum(array_column($fin['by_type'], 'total_amount') ?: array_column($fin['by_type'], 'total'));
          ?>
          <table class="rp-table">
            <thead><tr><th>Document Type</th><th class="text-center">Transactions</th><th class="text-center">%</th><th class="text-end">Amount Collected</th><th class="text-end">%</th></tr></thead>
            <tbody>
              <?php
              $allTotal = (float)($kpi['total_collections'] ?? 0);
              foreach ($fin['by_type'] as $r):
                $cnt = (int)$r['count'];
                $amt = (float)$r['total'];
              ?>
              <tr>
                <td><?= htmlspecialchars(rp_document_type_label((string)$r['document_type'])) ?></td>
                <td class="text-center"><?= number_format($cnt) ?></td>
                <td class="text-center pct"><?= rp_pct($cnt, (int)($kpi['total_issued']??0)) ?></td>
                <td class="text-end">&#8369;<?= number_format($amt,2) ?></td>
                <td class="text-end pct"><?= $allTotal > 0 ? number_format($amt/$allTotal*100,1).'%' : '—' ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td><strong>TOTAL</strong></td>
                <td class="text-center"><?= number_format((int)($kpi['total_issued']??0)) ?></td>
                <td class="text-center">100%</td>
                <td class="text-end">&#8369;<?= number_format((float)($kpi['total_collections']??0),2) ?></td>
                <td class="text-end">100%</td>
              </tr>
            </tfoot>
          </table>
          <?php endif; ?>
        </div>

        <!-- III. Daily Collection Log -->
        <div class="rp-section">
          <div class="rp-section-label">III. Daily Collection Log</div>
          <?php if (empty($fin['daily_log'])): ?>
            <p class="rp-empty">No collections in this period.</p>
          <?php else: ?>
          <table class="rp-table">
            <thead>
              <tr><th>Date</th><th class="text-center">Transactions</th><th class="text-end">GCash</th><th class="text-end">Walk-in</th><th class="text-end">Daily Total</th></tr>
            </thead>
            <tbody>
              <?php foreach ($fin['daily_log'] as $r): ?>
              <tr>
                <td><?= htmlspecialchars(rp_date_label($r['collection_date'])) ?></td>
                <td class="text-center"><?= number_format((int)$r['count']) ?></td>
                <td class="text-end">&#8369;<?= number_format((float)$r['gcash'],2) ?></td>
                <td class="text-end">&#8369;<?= number_format((float)$r['walkin'],2) ?></td>
                <td class="text-end"><strong>&#8369;<?= number_format((float)$r['total'],2) ?></strong></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td><strong>TOTAL</strong></td>
                <td class="text-center"><?= number_format((int)($kpi['total_issued']??0)) ?></td>
                <td class="text-end">&#8369;<?= number_format((float)($kpi['gcash_total']??0),2) ?></td>
                <td class="text-end">&#8369;<?= number_format((float)($kpi['walkin_total']??0),2) ?></td>
                <td class="text-end">&#8369;<?= number_format((float)($kpi['total_collections']??0),2) ?></td>
              </tr>
            </tfoot>
          </table>
          <?php endif; ?>
        </div>

        <!-- IV. OR Number Log -->
        <div class="rp-section">
          <div class="rp-section-label">IV. Official Receipt (OR) Log</div>
          <?php if (empty($fin['or_log'])): ?>
            <p class="rp-empty">No OR records in this period.</p>
          <?php else: ?>
          <table class="rp-table">
            <thead>
              <tr><th>#</th><th>OR Number</th><th>Cert. No.</th><th>Resident</th><th>Document Type</th><th>Method</th><th class="text-end">Amount</th><th>Date Verified</th></tr>
            </thead>
            <tbody>
              <?php $i = 1; foreach ($fin['or_log'] as $r): ?>
              <tr>
                <td class="pct"><?= $i++ ?></td>
                <td><strong><?= htmlspecialchars($r['or_number']) ?></strong></td>
                <td><?= htmlspecialchars($r['certificate_number'] ?? '—') ?></td>
                <td><?= htmlspecialchars($r['resident_name'] ?: '—') ?></td>
                <td><?= htmlspecialchars(rp_document_type_label((string)$r['document_type'])) ?></td>
                <td><?= htmlspecialchars($r['payment_method']) ?></td>
                <td class="text-end">&#8369;<?= number_format((float)$r['fee_amount'],2) ?></td>
                <td><?= htmlspecialchars($r['finance_decision_at'] ?? '') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="6"><strong>TOTAL</strong></td>
                <td class="text-end">&#8369;<?= number_format((float)($kpi['total_collections']??0),2) ?></td>
                <td></td>
              </tr>
            </tfoot>
          </table>
          <?php endif; ?>
        </div>

<?php
// ══════════════════════════════════════════════════════════════════════════════
// RENDER: RESIDENTS
// ══════════════════════════════════════════════════════════════════════════════
elseif ($module === 'residents'):
  $kpi   = $res['kpi'] ?? [];
  $total = (int)($kpi['total'] ?? 0);
  $ver   = (int)($kpi['verified'] ?? 0);
?>
        <!-- I. Summary -->
        <div class="rp-section">
          <div class="rp-section-label">I. Population Summary</div>
          <table class="rp-summary">
            <tbody>
              <tr><td>Total Registered Residents</td><td><?= number_format($total) ?></td></tr>
              <tr><td>Verified Residents</td><td><?= number_format($ver) ?> &nbsp;<span class="pct">(<?= rp_pct($ver,$total) ?>)</span></td></tr>
              <tr><td>Pending Verification</td><td><?= number_format((int)($kpi['pending']??0)) ?> &nbsp;<span class="pct">(<?= rp_pct((int)($kpi['pending']??0),$total) ?>)</span></td></tr>
              <tr><td>Not Verified</td><td><?= number_format((int)($kpi['not_verified']??0)) ?> &nbsp;<span class="pct">(<?= rp_pct((int)($kpi['not_verified']??0),$total) ?>)</span></td></tr>
              <tr><td>Archived</td><td><?= number_format((int)($kpi['archived']??0)) ?> &nbsp;<span class="pct">(<?= rp_pct((int)($kpi['archived']??0),$total) ?>)</span></td></tr>
            </tbody>
          </table>
        </div>

        <!-- II. Gender + III. Age — side by side -->
        <div class="rp-two-col" style="margin-top:22px;">
          <div>
            <div class="rp-section-label">II. Gender Distribution (Verified)</div>
            <?php if (empty($res['by_gender'])): ?>
              <p class="rp-empty">No data.</p>
            <?php else: ?>
            <table class="rp-table">
              <thead><tr><th>Gender</th><th class="text-center">Count</th><th class="text-center">%</th></tr></thead>
              <tbody>
                <?php foreach ($res['by_gender'] as $r): ?>
                <tr>
                  <td><?= htmlspecialchars(ucfirst($r['gender'])) ?></td>
                  <td class="text-center"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center pct"><?= rp_pct((int)$r['total'], $ver) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr><td><strong>TOTAL</strong></td><td class="text-center"><?= number_format($ver) ?></td><td class="text-center">100%</td></tr></tfoot>
            </table>
            <?php endif; ?>
          </div>
          <div>
            <div class="rp-section-label">III. Age Distribution (Verified)</div>
            <?php $ageTotal = array_sum($res['age_buckets']); ?>
            <table class="rp-table">
              <thead><tr><th>Age Group</th><th class="text-center">Count</th><th class="text-center">%</th></tr></thead>
              <tbody>
                <?php foreach ($res['age_buckets'] as $label => $cnt): ?>
                <tr>
                  <td><?= htmlspecialchars($label) ?> years</td>
                  <td class="text-center"><?= number_format($cnt) ?></td>
                  <td class="text-center pct"><?= rp_pct($cnt, $ageTotal) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr><td><strong>TOTAL</strong></td><td class="text-center"><?= number_format($ageTotal) ?></td><td class="text-center">100%</td></tr></tfoot>
            </table>
          </div>
        </div>

        <!-- IV. By Area + V. Sector — side by side -->
        <div class="rp-two-col" style="margin-top:22px;">
          <?php if (!empty($res['by_area'])): ?>
          <div>
            <div class="rp-section-label">IV. Residents by Area (Verified)</div>
            <?php $areaTotal = array_sum(array_column($res['by_area'],'total')); ?>
            <table class="rp-table">
              <thead><tr><th>Area</th><th class="text-center">Count</th><th class="text-center">%</th></tr></thead>
              <tbody>
                <?php foreach ($res['by_area'] as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['area']) ?></td>
                  <td class="text-center"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center pct"><?= rp_pct((int)$r['total'], $areaTotal) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr><td><strong>TOTAL</strong></td><td class="text-center"><?= number_format($areaTotal) ?></td><td class="text-center">100%</td></tr></tfoot>
            </table>
          </div>
          <?php endif; ?>
          <div>
            <div class="rp-section-label"><?= !empty($res['by_area']) ? 'V.' : 'IV.' ?> Sector Membership (Verified)</div>
            <?php if (empty($res['by_sector'])): ?>
              <p class="rp-empty">No sector data.</p>
            <?php else: $secTotal = array_sum($res['by_sector']); ?>
            <table class="rp-table">
              <thead><tr><th>Sector</th><th class="text-center">Count</th><th class="text-center">%</th></tr></thead>
              <tbody>
                <?php foreach ($res['by_sector'] as $sector => $cnt): ?>
                <tr>
                  <td><?= htmlspecialchars($sector) ?></td>
                  <td class="text-center"><?= number_format($cnt) ?></td>
                  <td class="text-center pct"><?= rp_pct($cnt, $secTotal) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>
        </div>

        <!-- Monthly Registrations -->
        <div class="rp-section">
          <div class="rp-section-label"><?= !empty($res['by_area']) ? 'VI.' : 'V.' ?> Monthly Registrations (Last 12 Months)</div>
          <?php if (empty($res['monthly_reg'])): ?>
            <p class="rp-empty">No data.</p>
          <?php else: $regTotal = array_sum(array_column($res['monthly_reg'],'total')); ?>
          <table class="rp-table">
            <thead><tr><th>Month</th><th class="text-center">New Registrations</th></tr></thead>
            <tbody>
              <?php foreach ($res['monthly_reg'] as $r): ?>
              <tr>
                <td><?= htmlspecialchars(date('F Y', strtotime($r['month'].'-01'))) ?></td>
                <td class="text-center"><?= number_format((int)$r['total']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td><strong>TOTAL</strong></td><td class="text-center"><?= number_format($regTotal) ?></td></tr></tfoot>
          </table>
          <?php endif; ?>
        </div>

<?php
// ══════════════════════════════════════════════════════════════════════════════
// RENDER: APPOINTMENTS
// ══════════════════════════════════════════════════════════════════════════════
elseif ($module === 'appointments'):
  $kpi   = $appt['kpi'] ?? [];
  $total = (int)($kpi['total'] ?? 0);
?>
        <!-- I. Summary -->
        <div class="rp-section">
          <div class="rp-section-label">I. Appointments Summary</div>
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

        <!-- II. By Status + III. By Purpose — side by side -->
        <div class="rp-two-col" style="margin-top:22px;">
          <div>
            <div class="rp-section-label">II. By Status</div>
            <?php if (empty($appt['by_status'])): ?>
              <p class="rp-empty">No data.</p>
            <?php else: ?>
            <table class="rp-table">
              <thead><tr><th>Status</th><th class="text-center">Count</th><th class="text-center">%</th></tr></thead>
              <tbody>
                <?php foreach ($appt['by_status'] as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['status']) ?></td>
                  <td class="text-center"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center pct"><?= rp_pct((int)$r['total'],$total) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr><td><strong>TOTAL</strong></td><td class="text-center"><?= number_format($total) ?></td><td class="text-center">100%</td></tr></tfoot>
            </table>
            <?php endif; ?>
          </div>
          <div>
            <div class="rp-section-label">III. By Purpose (Top 20)</div>
            <?php if (empty($appt['by_purpose'])): ?>
              <p class="rp-empty">No data.</p>
            <?php else: $purpTotal = array_sum(array_column($appt['by_purpose'],'total')); ?>
            <table class="rp-table">
              <thead><tr><th>Purpose</th><th class="text-center">Count</th><th class="text-center">%</th></tr></thead>
              <tbody>
                <?php foreach ($appt['by_purpose'] as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['purpose']) ?></td>
                  <td class="text-center"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center pct"><?= rp_pct((int)$r['total'],$total) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr><td><strong>TOTAL (shown)</strong></td><td class="text-center"><?= number_format($purpTotal) ?></td><td></td></tr></tfoot>
            </table>
            <?php endif; ?>
          </div>
        </div>

        <!-- IV. Monthly Trend -->
        <div class="rp-section">
          <div class="rp-section-label">IV. Monthly Trend (Last 12 Months)</div>
          <?php if (empty($appt['trend'])): ?>
            <p class="rp-empty">No trend data.</p>
          <?php else:
            $tTotal = array_sum(array_column($appt['trend'],'total'));
            $tComp  = array_sum(array_column($appt['trend'],'completed'));
          ?>
          <table class="rp-table">
            <thead><tr><th>Month</th><th class="text-center">Total</th><th class="text-center">Completed</th><th class="text-center">Completion Rate</th></tr></thead>
            <tbody>
              <?php foreach ($appt['trend'] as $r): ?>
              <tr>
                <td><?= htmlspecialchars(date('F Y', strtotime($r['month'].'-01'))) ?></td>
                <td class="text-center"><?= number_format((int)$r['total']) ?></td>
                <td class="text-center"><?= number_format((int)$r['completed']) ?></td>
                <td class="text-center pct"><?= rp_pct((int)$r['completed'],(int)$r['total']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td><strong>TOTAL</strong></td><td class="text-center"><?= number_format($tTotal) ?></td><td class="text-center"><?= number_format($tComp) ?></td><td class="text-center pct"><?= rp_pct($tComp,$tTotal) ?></td></tr></tfoot>
          </table>
          <?php endif; ?>
        </div>

<?php
// ══════════════════════════════════════════════════════════════════════════════
// RENDER: BLOTTER
// ══════════════════════════════════════════════════════════════════════════════
elseif ($module === 'blotter'):
  $kpi   = $blot['kpi'] ?? [];
  $total = (int)($kpi['total'] ?? 0);
?>
        <!-- I. Summary -->
        <div class="rp-section">
          <div class="rp-section-label">I. Blotter & Cases Summary</div>
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

        <!-- II. By Complaint Type + III. By Status — side by side -->
        <div class="rp-two-col" style="margin-top:22px;">
          <div>
            <div class="rp-section-label">II. By Complaint Type (Top 20)</div>
            <?php if (empty($blot['by_type'])): ?>
              <p class="rp-empty">No data.</p>
            <?php else: ?>
            <table class="rp-table">
              <thead><tr><th>Complaint Type</th><th class="text-center">Total</th><th class="text-center">Resolved</th><th class="text-center">Res. Rate</th></tr></thead>
              <tbody>
                <?php foreach ($blot['by_type'] as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['complaint_type']) ?></td>
                  <td class="text-center"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center"><?= number_format((int)$r['resolved']) ?></td>
                  <td class="text-center pct"><?= rp_pct((int)$r['resolved'],(int)$r['total']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>
          <div>
            <div class="rp-section-label">III. By Status</div>
            <?php if (empty($blot['by_status'])): ?>
              <p class="rp-empty">No data.</p>
            <?php else: ?>
            <table class="rp-table">
              <thead><tr><th>Status</th><th class="text-center">Count</th><th class="text-center">%</th></tr></thead>
              <tbody>
                <?php foreach ($blot['by_status'] as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['status']) ?></td>
                  <td class="text-center"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center pct"><?= rp_pct((int)$r['total'],$total) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr><td><strong>TOTAL</strong></td><td class="text-center"><?= number_format($total) ?></td><td class="text-center">100%</td></tr></tfoot>
            </table>
            <?php endif; ?>
          </div>
        </div>

        <!-- IV. Monthly Trend -->
        <div class="rp-section">
          <div class="rp-section-label">IV. Monthly Trend (Last 12 Months)</div>
          <?php if (empty($blot['trend'])): ?>
            <p class="rp-empty">No trend data.</p>
          <?php else: $tTotal = array_sum(array_column($blot['trend'],'total')); ?>
          <table class="rp-table">
            <thead><tr><th>Month</th><th class="text-center">Cases Filed</th></tr></thead>
            <tbody>
              <?php foreach ($blot['trend'] as $r): ?>
              <tr>
                <td><?= htmlspecialchars(date('F Y', strtotime($r['month'].'-01'))) ?></td>
                <td class="text-center"><?= number_format((int)$r['total']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td><strong>TOTAL</strong></td><td class="text-center"><?= number_format($tTotal) ?></td></tr></tfoot>
          </table>
          <?php endif; ?>
        </div>

<?php
// ══════════════════════════════════════════════════════════════════════════════
// RENDER: COMPLAINTS
// ══════════════════════════════════════════════════════════════════════════════
elseif ($module === 'complaints'):
  $kpi   = $comp['kpi'] ?? [];
  $total = (int)($kpi['total'] ?? 0);
?>
        <!-- I. Summary -->
        <div class="rp-section">
          <div class="rp-section-label">I. Complaints Summary</div>
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

        <!-- II. By Origin + III. By Subject Kind — side by side -->
        <div class="rp-two-col" style="margin-top:22px;">
          <div>
            <div class="rp-section-label">II. By Origin</div>
            <?php if (empty($comp['by_origin'])): ?>
              <p class="rp-empty">No data.</p>
            <?php else: ?>
            <table class="rp-table">
              <thead><tr><th>Origin</th><th class="text-center">Count</th><th class="text-center">%</th></tr></thead>
              <tbody>
                <?php foreach ($comp['by_origin'] as $r): ?>
                <tr>
                  <td><?= htmlspecialchars(ucwords(str_replace('_',' ',$r['origin']))) ?></td>
                  <td class="text-center"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center pct"><?= rp_pct((int)$r['total'],$total) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr><td><strong>TOTAL</strong></td><td class="text-center"><?= number_format($total) ?></td><td class="text-center">100%</td></tr></tfoot>
            </table>
            <?php endif; ?>
          </div>
          <div>
            <div class="rp-section-label">III. By Subject Kind</div>
            <?php if (empty($comp['by_kind'])): ?>
              <p class="rp-empty">No data.</p>
            <?php else: $kindTotal = array_sum(array_column($comp['by_kind'],'total')); ?>
            <table class="rp-table">
              <thead><tr><th>Kind</th><th class="text-center">Count</th><th class="text-center">%</th></tr></thead>
              <tbody>
                <?php foreach ($comp['by_kind'] as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['kind']) ?></td>
                  <td class="text-center"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center pct"><?= rp_pct((int)$r['total'],$kindTotal) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr><td><strong>TOTAL</strong></td><td class="text-center"><?= number_format($kindTotal) ?></td><td class="text-center">100%</td></tr></tfoot>
            </table>
            <?php endif; ?>
          </div>
        </div>

        <!-- IV. Monthly Trend -->
        <div class="rp-section">
          <div class="rp-section-label">IV. Monthly Trend (Last 12 Months)</div>
          <?php if (empty($comp['trend'])): ?>
            <p class="rp-empty">No trend data.</p>
          <?php else:
            $tTotal = array_sum(array_column($comp['trend'],'total'));
            $tEsc   = array_sum(array_column($comp['trend'],'escalated'));
          ?>
          <table class="rp-table">
            <thead><tr><th>Month</th><th class="text-center">Total Filed</th><th class="text-center">Escalated</th><th class="text-center">Escalation Rate</th></tr></thead>
            <tbody>
              <?php foreach ($comp['trend'] as $r): ?>
              <tr>
                <td><?= htmlspecialchars(date('F Y', strtotime($r['month'].'-01'))) ?></td>
                <td class="text-center"><?= number_format((int)$r['total']) ?></td>
                <td class="text-center"><?= number_format((int)$r['escalated']) ?></td>
                <td class="text-center pct"><?= rp_pct((int)$r['escalated'],(int)$r['total']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td><strong>TOTAL</strong></td><td class="text-center"><?= number_format($tTotal) ?></td><td class="text-center"><?= number_format($tEsc) ?></td><td class="text-center pct"><?= rp_pct($tEsc,$tTotal) ?></td></tr></tfoot>
          </table>
          <?php endif; ?>
        </div>

<?php endif; ?>

        <!-- ── Certification / Signature block ─────────────────────────── -->
        <div class="rp-footer">
          <div class="rp-footer-meta">
            Report generated on <strong><?= date('F j, Y \a\t g:i A') ?></strong>
            <?php if ($module !== 'residents'): ?>
            &nbsp;|&nbsp; Period covered: <strong><?= rp_date_label($dateFrom) ?></strong> to <strong><?= rp_date_label($dateTo) ?></strong>
            <?php endif; ?>
            &nbsp;|&nbsp; System: Barangay San Jose Information Management System
          </div>
          <div class="rp-sig-grid">
            <div class="rp-sig-block">
              <div style="height:40px;"></div>
              <div class="rp-sig-line">
                <div class="rp-sig-name"><?= htmlspecialchars($preparedByName) ?></div>
                <div class="rp-sig-role">Prepared by</div>
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
        </div>

</div><!-- /.rp-doc -->

<?php if ($isPrintView): ?>
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
  const popup = window.open(url.toString(), '_blank', 'width=900,height=720,scrollbars=yes');
  if (!popup) {
    alert('Please allow popups for this site, then click Download PDF again.');
  }
}
</script>
</body>
</html>
<?php endif; ?>
