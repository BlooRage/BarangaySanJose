<?php
require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../../Admin-End/includes/admin_guard.php';

header('Content-Type: application/json; charset=utf-8');

function as_table_exists(mysqli $conn, string $table): bool
{
    static $cache = [];
    $key = strtolower($table);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return $cache[$key] = false;
    }

    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = (bool)($stmt->get_result()->fetch_row()[0] ?? false);
    $stmt->close();
    return $cache[$key] = $exists;
}

function as_column_exists(mysqli $conn, string $table, string $column): bool
{
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

function as_safe_date(?string $value, string $fallback): string
{
    $value = trim((string)$value);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : $fallback;
}

function as_month_labels(string $dateFrom, string $dateTo): array
{
    $start = new DateTimeImmutable(substr($dateFrom, 0, 7) . '-01');
    $end = new DateTimeImmutable(substr($dateTo, 0, 7) . '-01');
    $labels = [];

    while ($start <= $end) {
        $labels[$start->format('Y-m')] = $start->format('M Y');
        $start = $start->modify('+1 month');
    }

    return $labels;
}

function as_count_age_bands(array $rows): array
{
    $bands = [
        'Male' => 0,
        'Female' => 0,
    ];

    foreach ($rows as $row) {
        $sex = trim((string)($row['sex'] ?? ''));
        if ($sex === 'Male') {
            $bands['Male']++;
        } elseif ($sex === 'Female') {
            $bands['Female']++;
        }
    }

    return $bands;
}

function as_status_bucket(string $rawStatus, string $module): string
{
    $status = strtolower(trim($rawStatus));
    if ($status === '') {
        return 'active';
    }

    $resolvedKeywords = ['resolved', 'closed', 'settled', 'completed', 'released', 'verified', 'approved', 'confirmed', 'ready for claim', 'ready_for_claim'];
    foreach ($resolvedKeywords as $keyword) {
        if (str_contains($status, $keyword)) {
            return 'resolved';
        }
    }

    $pendingKeywords = ['pending', 'active', 'open', 'ongoing', 'submitted', 'for payment', 'for inspection', 'for interview', 'scheduled'];
    foreach ($pendingKeywords as $keyword) {
        if (str_contains($status, $keyword)) {
            return 'pending';
        }
    }

    if ($module === 'residents' && $status === 'notverified') {
        return 'pending';
    }

    return 'active';
}

function as_matches_status_filter(string $bucket, string $filter): bool
{
    return $filter === 'all' || $bucket === $filter;
}

function as_area_expr(mysqli $conn, string $tableAlias, string $addressAlias = 'cp'): string
{
    if (as_column_exists($conn, 'caseparticipantstbl', 'area_number')) {
        return "COALESCE(NULLIF(TRIM({$tableAlias}.area_number), ''), NULLIF(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX({$addressAlias}.address, 'Area:', -1), ',', 1)), ''))";
    }

    return "NULLIF(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX({$addressAlias}.address, 'Area:', -1), ',', 1)), '')";
}

$allowedScopes = ['barangay', 'Area 01', 'Area 1A', 'Area 02', 'Area 03', 'Area 04', 'Area 05', 'Area 06'];
$allowedModules = ['all', 'residents', 'documents', 'blotter', 'complaints', 'appointments'];
$allowedStatus = ['all', 'active', 'pending', 'resolved'];

$scope = trim((string)($_GET['scope'] ?? 'barangay'));
if (!in_array($scope, $allowedScopes, true)) {
    $scope = 'barangay';
}

$module = strtolower(trim((string)($_GET['module'] ?? 'all')));
if (!in_array($module, $allowedModules, true)) {
    $module = 'all';
}

$statusFilter = strtolower(trim((string)($_GET['status'] ?? 'all')));
if (!in_array($statusFilter, $allowedStatus, true)) {
    $statusFilter = 'all';
}

$today = new DateTimeImmutable('today');
$defaultFrom = $today->modify('first day of -5 months')->format('Y-m-d');
$defaultTo = $today->format('Y-m-d');
$dateFrom = as_safe_date($_GET['date_from'] ?? null, $defaultFrom);
$dateTo = as_safe_date($_GET['date_to'] ?? null, $defaultTo);
if ($dateTo < $dateFrom) {
    $dateTo = $dateFrom;
}

$scopeEsc = $conn->real_escape_string($scope);
$fromEsc = $conn->real_escape_string($dateFrom);
$toEsc = $conn->real_escape_string($dateTo);

$residentRows = [];
$documentRows = [];
$blotterRows = [];
$complaintRows = [];
$appointmentRows = [];

if (as_table_exists($conn, 'residentinformationtbl') && as_table_exists($conn, 'residentaddresstbl')) {
    $residentScopeWhere = $scope === 'barangay'
        ? "1=1"
        : "COALESCE(NULLIF(TRIM(ra.area_number), ''), 'Unspecified Area') = '{$scopeEsc}'";

    $residentSql = "
        SELECT
            r.resident_id,
            r.sex,
            r.birthdate,
            r.head_of_family,
            COALESCE(s.status_name, '') AS status_name,
            COALESCE(NULLIF(TRIM(ra.area_number), ''), 'Unspecified Area') AS area_name
        FROM residentinformationtbl r
        LEFT JOIN statuslookuptbl s ON s.status_id = r.status_id_resident
        LEFT JOIN residentaddresstbl ra
          ON ra.address_id = (
            SELECT ra2.address_id
            FROM residentaddresstbl ra2
            WHERE ra2.resident_id = r.resident_id
            ORDER BY ra2.address_id DESC
            LIMIT 1
          )
        WHERE {$residentScopeWhere}
    ";

    if ($result = $conn->query($residentSql)) {
        while ($row = $result->fetch_assoc()) {
            if (strtolower(trim((string)($row['status_name'] ?? ''))) === 'archivedresident') {
                continue;
            }
            $residentRows[] = $row;
        }
        $result->free();
    }
}

if (as_table_exists($conn, 'documentrequesttbl') && as_table_exists($conn, 'residentinformationtbl') && as_table_exists($conn, 'residentaddresstbl')) {
    $hasStage = as_column_exists($conn, 'documentrequesttbl', 'stage');
    $documentScopeWhere = $scope === 'barangay'
        ? "1=1"
        : "COALESCE(NULLIF(TRIM(ra.area_number), ''), 'Unspecified Area') = '{$scopeEsc}'";
    $stageSelect = $hasStage ? "COALESCE(d.stage, '') AS stage," : "'' AS stage,";

    $documentSql = "
        SELECT
            d.request_id,
            COALESCE(NULLIF(TRIM(d.document_type), ''), 'Unspecified') AS document_type,
            COALESCE(d.request_timestamp, d.submitted_at, d.created_at) AS activity_date,
            {$stageSelect}
            COALESCE(sl.status_name, '') AS status_name
        FROM documentrequesttbl d
        LEFT JOIN residentinformationtbl r ON r.user_id = d.resident_user_id
        LEFT JOIN residentaddresstbl ra
          ON ra.address_id = (
            SELECT ra2.address_id
            FROM residentaddresstbl ra2
            WHERE ra2.resident_id = r.resident_id
            ORDER BY ra2.address_id DESC
            LIMIT 1
          )
        LEFT JOIN statuslookuptbl sl ON sl.status_id = d.status_id
        WHERE {$documentScopeWhere}
          AND DATE(COALESCE(d.request_timestamp, d.submitted_at, d.created_at)) BETWEEN '{$fromEsc}' AND '{$toEsc}'
    ";

    if ($result = $conn->query($documentSql)) {
        while ($row = $result->fetch_assoc()) {
            $bucket = as_status_bucket((string)($row['stage'] ?: $row['status_name']), 'documents');
            if (!as_matches_status_filter($bucket, $statusFilter)) {
                continue;
            }
            $row['bucket'] = $bucket;
            $documentRows[] = $row;
        }
        $result->free();
    }
}

if (as_table_exists($conn, 'casereportstbl') && as_table_exists($conn, 'caseparticipantstbl')) {
    $caseAreaExpr = as_area_expr($conn, 'cp');
    $caseScopeWhere = $scope === 'barangay'
        ? "1=1"
        : "COALESCE({$caseAreaExpr}, 'Unspecified Area') = '{$scopeEsc}'";

    $caseSql = "
        SELECT
            c.case_id,
            c.report_type,
            COALESCE(c.created_at, CONCAT(c.incident_date, ' 00:00:00')) AS activity_date,
            COALESCE(c.complaint_type, 'Not specified') AS complaint_type,
            COALESCE(sl.status_name, '') AS status_name
        FROM casereportstbl c
        INNER JOIN caseparticipantstbl cp
          ON cp.case_id = c.case_id
         AND cp.participant_role = 'Complainant'
        LEFT JOIN statuslookuptbl sl ON sl.status_id = c.case_status_id
        WHERE {$caseScopeWhere}
          AND DATE(COALESCE(c.created_at, c.incident_date)) BETWEEN '{$fromEsc}' AND '{$toEsc}'
    ";

    if ($result = $conn->query($caseSql)) {
        while ($row = $result->fetch_assoc()) {
            $reportType = trim((string)($row['report_type'] ?? ''));
            $bucket = as_status_bucket((string)($row['status_name'] ?? ''), strtolower($reportType));
            if (!as_matches_status_filter($bucket, $statusFilter)) {
                continue;
            }
            $row['bucket'] = $bucket;
            if ($reportType === 'Blotter') {
                $blotterRows[] = $row;
            } elseif ($reportType === 'Complaint') {
                $complaintRows[] = $row;
            }
        }
        $result->free();
    }
}

if (as_table_exists($conn, 'appointmentstbl') && as_table_exists($conn, 'residentinformationtbl') && as_table_exists($conn, 'residentaddresstbl')) {
    $appointmentScopeWhere = $scope === 'barangay'
        ? "1=1"
        : "COALESCE(NULLIF(TRIM(ra.area_number), ''), 'Unspecified Area') = '{$scopeEsc}'";

    $appointmentSql = "
        SELECT
            a.appointment_id,
            a.subject,
            a.request_timestamp AS activity_date,
            COALESCE(sl.status_name, '') AS status_name
        FROM appointmentstbl a
        LEFT JOIN residentinformationtbl r ON r.user_id = a.user_id_resident
        LEFT JOIN residentaddresstbl ra
          ON ra.address_id = (
            SELECT ra2.address_id
            FROM residentaddresstbl ra2
            WHERE ra2.resident_id = r.resident_id
            ORDER BY ra2.address_id DESC
            LIMIT 1
          )
        LEFT JOIN statuslookuptbl sl ON sl.status_id = a.appointment_status_id
        WHERE {$appointmentScopeWhere}
          AND DATE(a.request_timestamp) BETWEEN '{$fromEsc}' AND '{$toEsc}'
    ";

    if ($result = $conn->query($appointmentSql)) {
        while ($row = $result->fetch_assoc()) {
            $bucket = as_status_bucket((string)($row['status_name'] ?? ''), 'appointments');
            if (!as_matches_status_filter($bucket, $statusFilter)) {
                continue;
            }
            $row['bucket'] = $bucket;
            $appointmentRows[] = $row;
        }
        $result->free();
    }
}

$verifiedResidents = 0;
$pendingResidents = 0;
$households = 0;
foreach ($residentRows as $row) {
    $statusName = trim((string)($row['status_name'] ?? ''));
    if ($statusName === 'VerifiedResident') {
        $verifiedResidents++;
    }
    if ($statusName === 'PendingVerification' || $statusName === 'NotVerified') {
        $pendingResidents++;
    }
    if ((int)($row['head_of_family'] ?? 0) === 1) {
        $households++;
    }
}

$moduleRows = [
    [
        'key' => 'residents',
        'module' => 'Population and Demographics',
        'total' => count($residentRows),
        'active_pending' => $pendingResidents,
        'completed_resolved' => $verifiedResidents,
        'notes' => 'Resident profiles within the selected scope.',
    ],
    [
        'key' => 'documents',
        'module' => 'Document Issuance',
        'total' => count($documentRows),
        'active_pending' => count(array_filter($documentRows, fn($row) => ($row['bucket'] ?? '') !== 'resolved')),
        'completed_resolved' => count(array_filter($documentRows, fn($row) => ($row['bucket'] ?? '') === 'resolved')),
        'notes' => 'Mapped by resident address area.',
    ],
    [
        'key' => 'blotter',
        'module' => 'Blotter',
        'total' => count($blotterRows),
        'active_pending' => count(array_filter($blotterRows, fn($row) => ($row['bucket'] ?? '') !== 'resolved')),
        'completed_resolved' => count(array_filter($blotterRows, fn($row) => ($row['bucket'] ?? '') === 'resolved')),
        'notes' => 'Uses complainant area for statistics.',
    ],
    [
        'key' => 'complaints',
        'module' => 'Complaints',
        'total' => count($complaintRows),
        'active_pending' => count(array_filter($complaintRows, fn($row) => ($row['bucket'] ?? '') !== 'resolved')),
        'completed_resolved' => count(array_filter($complaintRows, fn($row) => ($row['bucket'] ?? '') === 'resolved')),
        'notes' => 'Uses complainant area for statistics.',
    ],
    [
        'key' => 'appointments',
        'module' => 'Appointments',
        'total' => count($appointmentRows),
        'active_pending' => count(array_filter($appointmentRows, fn($row) => ($row['bucket'] ?? '') !== 'resolved')),
        'completed_resolved' => count(array_filter($appointmentRows, fn($row) => ($row['bucket'] ?? '') === 'resolved')),
        'notes' => 'Derived from the requesting resident profile area.',
    ],
];

$moduleRowsForTable = $module === 'all'
    ? $moduleRows
    : array_values(array_filter($moduleRows, fn($row) => $row['key'] === $module));

$moduleChartRows = array_values(array_filter($moduleRows, fn($row) => $row['key'] !== 'residents' || $module === 'all' || $module === 'residents'));
if ($module !== 'all') {
    $moduleChartRows = array_values(array_filter($moduleRows, fn($row) => $row['key'] === $module));
}

$monthMap = as_month_labels($dateFrom, $dateTo);
$trendCounts = array_fill_keys(array_keys($monthMap), 0);

$trendSources = [];
if ($module === 'all' || $module === 'documents') {
    $trendSources[] = $documentRows;
}
if ($module === 'all' || $module === 'blotter') {
    $trendSources[] = $blotterRows;
}
if ($module === 'all' || $module === 'complaints') {
    $trendSources[] = $complaintRows;
}
if ($module === 'all' || $module === 'appointments') {
    $trendSources[] = $appointmentRows;
}

foreach ($trendSources as $rows) {
    foreach ($rows as $row) {
        $activityDate = trim((string)($row['activity_date'] ?? ''));
        if ($activityDate === '') {
            continue;
        }
        $monthKey = substr($activityDate, 0, 7);
        if (array_key_exists($monthKey, $trendCounts)) {
            $trendCounts[$monthKey]++;
        }
    }
}

$demographicBands = as_count_age_bands($residentRows);
$topModule = 'No module activity yet';
if ($moduleRowsForTable) {
    usort($moduleRowsForTable, fn($a, $b) => ($b['total'] <=> $a['total']));
    $topModule = $moduleRowsForTable[0]['module'] ?? $topModule;
}

$highestDemographic = 'No demographic data yet';
if ($demographicBands) {
    arsort($demographicBands);
    $highestDemographic = array_key_first($demographicBands) ?? $highestDemographic;
}

$openCases = count(array_filter(array_merge($blotterRows, $complaintRows), fn($row) => ($row['bucket'] ?? '') !== 'resolved'));
$highlights = [
    [
        'label' => 'Most active module',
        'value' => $topModule,
    ],
    [
        'label' => 'Highest demographic group',
        'value' => $highestDemographic,
    ],
    [
        'label' => 'Current monitoring need',
        'value' => $pendingResidents > 0 ? 'Pending resident verification' : 'No pending resident verification',
    ],
    [
        'label' => 'Suggested next drilldown',
        'value' => $openCases > 0 ? 'Open blotter and complaints' : 'Document issuance completion rate',
    ],
];

$response = [
    'scope' => $scope,
    'module' => $module,
    'status' => $statusFilter,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'cards' => [
        'population' => count($residentRows),
        'households' => $households,
        'documents' => count($documentRows),
        'cases' => count($blotterRows) + count($complaintRows),
    ],
    'module_chart' => [
        'labels' => array_map(fn($row) => $row['module'], $moduleChartRows),
        'values' => array_map(fn($row) => (int)$row['total'], $moduleChartRows),
    ],
    'demographics' => [
        'labels' => array_keys($demographicBands),
        'values' => array_values($demographicBands),
    ],
    'trend' => [
        'labels' => array_values($monthMap),
        'values' => array_values($trendCounts),
    ],
    'highlights' => $highlights,
    'table' => $module === 'all'
        ? $moduleRows
        : array_values(array_filter($moduleRows, fn($row) => $row['key'] === $module)),
];

echo json_encode($response, JSON_UNESCAPED_SLASHES);
