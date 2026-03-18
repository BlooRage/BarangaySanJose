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

$baseUrl = htmlspecialchars(appUrl('Admin-End/Reports/Reports.php'));

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
    $financeRollup = $hasFinanceTable ? rp_finance_rollup_subquery($conn, 'ft') : '';
    $financeJoin = $hasFinanceTable ? "LEFT JOIN {$financeRollup} f ON f.request_id = d.request_id" : '';
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
        WHERE {$requestDateFilter}
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
        WHERE {$requestDateFilter}
        GROUP BY {$docTypeExpr}
        ORDER BY total DESC
    ");

    $dr['by_stage'] = rp_safe_query($conn, "
        SELECT COALESCE(stage,'Unknown') AS stage, COUNT(*) AS total
        FROM documentrequesttbl d
        WHERE {$requestDateFilter}
        GROUP BY stage ORDER BY total DESC
    ");

    $dr['trend'] = rp_safe_query($conn, "
        SELECT DATE_FORMAT({$requestDateExpr},'%Y-%m') AS month, COUNT(*) AS total,
          SUM(CASE WHEN LOWER(stage)='completed' THEN 1 ELSE 0 END) AS completed
        FROM documentrequesttbl d
        WHERE {$trendFilter}
        GROUP BY month ORDER BY month ASC
    ");

    $dr['by_payment'] = $hasFinanceTable ? rp_safe_query($conn, "
        SELECT {$paymentMethodExpr} AS method,
          COUNT(*) AS total,
          SUM({$amountExpr}) AS revenue
        FROM documentrequesttbl d
        LEFT JOIN {$financeRollup} f ON f.request_id = d.request_id
        WHERE {$requestDateFilter}
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
    $financeRollup = rp_finance_rollup_subquery($conn, 'ft');
    $financeDateExpr = "NULLIF(f.finance_event_at, '0000-00-00 00:00:00')";
    $financialDateFilter = $financeDateExpr !== 'NULL'
        ? "DATE({$financeDateExpr}) BETWEEN '{$df}' AND '{$dt}'"
        : '1 = 0';
    $financialCollectedFilter = "LOWER(d.stage) IN ('payment_verified', 'ready_for_claim', 'completed') AND COALESCE(f.transaction_amount, 0) > 0";

    $fin['kpi'] = rp_safe_query($conn, "
        SELECT
          COUNT(*) AS total_issued,
          SUM(COALESCE(f.transaction_amount, 0)) AS total_collections,
          SUM(CASE WHEN LOWER(COALESCE(f.payment_method, ''))='gcash' THEN COALESCE(f.transaction_amount, 0) ELSE 0 END) AS gcash_total,
          SUM(CASE WHEN LOWER(COALESCE(f.payment_method, '')) IN ('barangay', 'walk_in', 'walkin', 'cash') THEN COALESCE(f.transaction_amount, 0) ELSE 0 END) AS walkin_total,
          SUM(CASE WHEN COALESCE(f.or_number, '') <> '' THEN 1 ELSE 0 END) AS or_count
        FROM documentrequesttbl d
        INNER JOIN {$financeRollup} f ON f.request_id = d.request_id
        WHERE {$financialCollectedFilter}
          AND {$financialDateFilter}
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
        WHERE {$financialCollectedFilter}
          AND {$financialDateFilter}
        GROUP BY collection_date ORDER BY collection_date ASC
    ");

    $fin['by_type'] = rp_safe_query($conn, "
        SELECT
          COALESCE(NULLIF(TRIM(d.document_type), ''), 'Unspecified') AS document_type,
          COUNT(*) AS count,
          SUM(COALESCE(f.transaction_amount, 0)) AS total
        FROM documentrequesttbl d
        INNER JOIN {$financeRollup} f ON f.request_id = d.request_id
        WHERE {$financialCollectedFilter}
          AND {$financialDateFilter}
        GROUP BY COALESCE(NULLIF(TRIM(d.document_type), ''), 'Unspecified')
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
        WHERE {$financialCollectedFilter}
          AND COALESCE(f.or_number, '') <> ''
          AND {$financialDateFilter}
        ORDER BY {$financeDateExpr} ASC
        LIMIT 500
    ");
}

// ═══════════════════════════════════════════════════════════════════════════════
// MODULE: RESIDENTS
// ═══════════════════════════════════════════════════════════════════════════════
$res = [];
if ($module === 'residents' && rp_table_exists($conn, 'residentinformationtbl')) {
    $res['kpi'] = rp_safe_query($conn, "
        SELECT
          COUNT(*) AS total,
          SUM(CASE WHEN s.status_name='VerifiedResident'   THEN 1 ELSE 0 END) AS verified,
          SUM(CASE WHEN s.status_name='PendingVerification' THEN 1 ELSE 0 END) AS pending,
          SUM(CASE WHEN s.status_name='NotVerified'        THEN 1 ELSE 0 END) AS not_verified,
          SUM(CASE WHEN s.status_name='ArchivedResident'   THEN 1 ELSE 0 END) AS archived
        FROM residentinformationtbl ri
        JOIN statuslookuptbl s ON s.status_id = ri.status_id_resident
    ");
    $res['kpi'] = $res['kpi'][0] ?? [];

    $res['by_gender'] = rp_safe_query($conn, "
        SELECT COALESCE(LOWER(sex),'unspecified') AS gender, COUNT(*) AS total
        FROM residentinformationtbl ri
        JOIN statuslookuptbl s ON s.status_id = ri.status_id_resident
        WHERE s.status_name = 'VerifiedResident'
        GROUP BY gender ORDER BY total DESC
    ");

    $allBirthdays = rp_safe_query($conn, "
        SELECT birthdate FROM residentinformationtbl ri
        JOIN statuslookuptbl s ON s.status_id = ri.status_id_resident
        WHERE s.status_name='VerifiedResident' AND birthdate IS NOT NULL AND birthdate != ''
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

    if (rp_table_exists($conn, 'residentaddresstbl')) {
        $res['by_area'] = rp_safe_query($conn, "
            SELECT COALESCE(NULLIF(TRIM(ra.area_number),''),'Unspecified') AS area, COUNT(DISTINCT ri.resident_id) AS total
            FROM residentinformationtbl ri
            JOIN statuslookuptbl s ON s.status_id = ri.status_id_resident
            LEFT JOIN residentaddresstbl ra ON ra.resident_id = ri.resident_id
            WHERE s.status_name = 'VerifiedResident'
            GROUP BY area ORDER BY total DESC
        ");
    }

    $sectorRows = rp_safe_query($conn, "
        SELECT sector_membership FROM residentinformationtbl ri
        JOIN statuslookuptbl s ON s.status_id = ri.status_id_resident
        WHERE s.status_name='VerifiedResident' AND sector_membership IS NOT NULL AND sector_membership != ''
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
        SELECT DATE_FORMAT(created_at,'%Y-%m') AS month, COUNT(*) AS total
        FROM residentinformationtbl
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
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

    $appt['kpi'] = rp_safe_query($conn, "
        SELECT COUNT(*) AS total,
          SUM(CASE WHEN s.status_name IN ('Scheduled','Confirmed') THEN 1 ELSE 0 END) AS scheduled,
          SUM(CASE WHEN s.status_name = 'Completed' THEN 1 ELSE 0 END) AS completed,
          SUM(CASE WHEN s.status_name IN ('Cancelled','CancelledByResident','CancelledByAdmin') THEN 1 ELSE 0 END) AS cancelled,
          SUM(CASE WHEN s.status_name = 'Pending' THEN 1 ELSE 0 END) AS pending
        FROM appointmentstbl a
        JOIN statuslookuptbl s ON s.status_id = a.appointment_status_id
        WHERE DATE(a.request_timestamp) BETWEEN '{$df}' AND '{$dt}'
    ");
    $appt['kpi'] = $appt['kpi'][0] ?? [];

    $appt['by_status'] = rp_safe_query($conn, "
        SELECT s.status_name AS status, COUNT(*) AS total
        FROM appointmentstbl a
        JOIN statuslookuptbl s ON s.status_id = a.appointment_status_id
        WHERE DATE(a.request_timestamp) BETWEEN '{$df}' AND '{$dt}'
        GROUP BY s.status_name ORDER BY total DESC
    ");

    $appt['by_purpose'] = rp_safe_query($conn, "
        SELECT COALESCE(NULLIF(TRIM(purpose),''),'Not specified') AS purpose, COUNT(*) AS total
        FROM appointmentstbl
        WHERE DATE(request_timestamp) BETWEEN '{$df}' AND '{$dt}'
        GROUP BY purpose ORDER BY total DESC LIMIT 20
    ");

    $appt['trend'] = rp_safe_query($conn, "
        SELECT DATE_FORMAT(request_timestamp,'%Y-%m') AS month, COUNT(*) AS total,
          SUM(CASE WHEN s.status_name='Completed' THEN 1 ELSE 0 END) AS completed
        FROM appointmentstbl a
        JOIN statuslookuptbl s ON s.status_id = a.appointment_status_id
        WHERE a.request_timestamp >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
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

    $blot['kpi'] = rp_safe_query($conn, "
        SELECT COUNT(*) AS total,
          SUM(CASE WHEN LOWER(s.status_name) IN ('active','open','ongoing') THEN 1 ELSE 0 END) AS active,
          SUM(CASE WHEN LOWER(s.status_name) IN ('resolved','closed','settled') THEN 1 ELSE 0 END) AS resolved,
          SUM(CASE WHEN report_type='Blotter' THEN 1 ELSE 0 END) AS blotter_count,
          SUM(CASE WHEN report_type='Complaint' THEN 1 ELSE 0 END) AS complaint_count
        FROM casereportstbl c
        JOIN statuslookuptbl s ON s.status_id = c.case_status_id
        WHERE DATE(c.created_at) BETWEEN '{$df}' AND '{$dt}'
    ");
    $blot['kpi'] = $blot['kpi'][0] ?? [];

    $blot['by_type'] = rp_safe_query($conn, "
        SELECT COALESCE(complaint_type,'Not specified') AS complaint_type,
          COUNT(*) AS total,
          SUM(CASE WHEN LOWER(s.status_name) IN ('resolved','closed','settled') THEN 1 ELSE 0 END) AS resolved
        FROM casereportstbl c
        JOIN statuslookuptbl s ON s.status_id = c.case_status_id
        WHERE DATE(c.created_at) BETWEEN '{$df}' AND '{$dt}'
        GROUP BY complaint_type ORDER BY total DESC LIMIT 20
    ");

    $blot['by_status'] = rp_safe_query($conn, "
        SELECT s.status_name AS status, COUNT(*) AS total
        FROM casereportstbl c
        JOIN statuslookuptbl s ON s.status_id = c.case_status_id
        WHERE DATE(c.created_at) BETWEEN '{$df}' AND '{$dt}'
        GROUP BY s.status_name ORDER BY total DESC
    ");

    $blot['trend'] = rp_safe_query($conn, "
        SELECT DATE_FORMAT(created_at,'%Y-%m') AS month, COUNT(*) AS total
        FROM casereportstbl
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
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

    $comp['kpi'] = rp_safe_query($conn, "
        SELECT COUNT(*) AS total,
          SUM(CASE WHEN escalated_to_blotter=1 THEN 1 ELSE 0 END) AS escalated,
          SUM(CASE WHEN escalated_to_blotter=0 OR escalated_to_blotter IS NULL THEN 1 ELSE 0 END) AS unescalated,
          SUM(CASE WHEN complaint_origin='walk_in' OR complaint_origin='Walk-in' THEN 1 ELSE 0 END) AS walkin,
          SUM(CASE WHEN complaint_origin='online' OR complaint_origin='Online' THEN 1 ELSE 0 END) AS online_count
        FROM complaintstbl
        WHERE DATE(created_at) BETWEEN '{$df}' AND '{$dt}'
    ");
    $comp['kpi'] = $comp['kpi'][0] ?? [];

    $comp['by_origin'] = rp_safe_query($conn, "
        SELECT COALESCE(complaint_origin,'Unknown') AS origin, COUNT(*) AS total
        FROM complaintstbl
        WHERE DATE(created_at) BETWEEN '{$df}' AND '{$dt}'
        GROUP BY complaint_origin ORDER BY total DESC
    ");

    $comp['by_kind'] = rp_safe_query($conn, "
        SELECT COALESCE(subject_kind,'Unknown') AS kind, COUNT(*) AS total
        FROM complaintstbl
        WHERE DATE(created_at) BETWEEN '{$df}' AND '{$dt}'
        GROUP BY subject_kind ORDER BY total DESC
    ");

    $comp['trend'] = rp_safe_query($conn, "
        SELECT DATE_FORMAT(created_at,'%Y-%m') AS month, COUNT(*) AS total,
          SUM(CASE WHEN escalated_to_blotter=1 THEN 1 ELSE 0 END) AS escalated
        FROM complaintstbl
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
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

    /* ── Formal report document ─────────────────────────────────────────────── */
    .rp-doc {
      background: #fff;
      border: 1px solid #d0d7de;
      border-radius: 8px;
      padding: 40px 44px;
      font-size: 13px;
      color: #1a1a1a;
    }

    /* Header */
    .rp-doc-header {
      text-align: center;
      border-bottom: 2px solid #6b7280;
      padding-bottom: 16px;
      margin-bottom: 22px;
    }
    .rp-brand-grid {
      display: grid;
      grid-template-columns: 92px 1fr 92px;
      align-items: center;
      gap: 14px;
    }
    .rp-brand-seal {
      width: 86px;
      height: 86px;
      object-fit: contain;
      margin: 0 auto;
      display: block;
    }
    .rp-brand-copy {
      text-align: center;
    }
    .rp-brand-topline {
      font-size: 11.2px;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: #374151;
      line-height: 1.35;
    }
    .rp-brand-main {
      font-size: 20px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: #111827;
      margin-top: 4px;
    }
    .rp-brand-subline {
      font-size: 12.2px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: #DE710C;
      margin-top: 8px;
    }
    .rp-doc-header .rp-report-title {
      font-size: 15px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: #9a3412;
      margin-top: 14px;
    }
    .rp-doc-header .rp-period { font-size: 12px; color: #4b5563; margin-top: 4px; }
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
      padding-top: 16px;
      border-top: 2px solid #555;
    }
    .rp-footer-meta { font-size: 11.5px; color: #555; margin-bottom: 28px; }
    .rp-sig-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; }
    .rp-sig-block { text-align: center; }
    .rp-sig-block .rp-sig-line { border-top: 1px solid #333; padding-top: 4px; margin-top: 36px; }
    .rp-sig-block .rp-sig-name { font-weight: 700; font-size: 13px; text-transform: uppercase; }
    .rp-sig-block .rp-sig-role { font-size: 11px; color: #555; }

    /* Empty state */
    .rp-empty { color: #9ca3af; font-style: italic; padding: 12px 0; font-size: 12px; }

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
      }
      .rp-doc-header { padding-bottom: 10pt !important; margin-bottom: 14pt !important; }
      .rp-brand-grid { grid-template-columns: 70pt 1fr 70pt !important; gap: 10pt !important; }
      .rp-brand-seal { width: 64pt !important; height: 64pt !important; }
      .rp-brand-main { font-size: 15pt !important; }
      .rp-brand-subline { font-size: 9pt !important; }
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
    .rp-doc { border: none !important; border-radius: 0 !important; padding: 0 !important;
              box-shadow: none !important; max-width: 100% !important; font-size: 10pt !important; }
    .rp-doc-header { padding-bottom: 10pt !important; margin-bottom: 14pt !important; }
    .rp-brand-grid { grid-template-columns: 70pt 1fr 70pt !important; gap: 10pt !important; }
    .rp-brand-seal { width: 64pt !important; height: 64pt !important; }
    .rp-brand-main { font-size: 15pt !important; }
    .rp-brand-subline { font-size: 9pt !important; }
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

      <!-- ── Controls: module nav + date filter ─────────────────────────── -->
      <div class="rp-controls">
        <div class="module-nav mb-3 overflow-auto">
          <ul class="nav nav-pills flex-nowrap gap-1">
            <?php foreach ($moduleLabels as $key => $ml): ?>
            <li class="nav-item">
              <a class="nav-link <?= $module === $key ? 'active' : 'btn-outline-secondary border' ?>"
                 href="<?= $baseUrl ?>?module=<?= $key ?>&date_from=<?= htmlspecialchars($dateFrom) ?>&date_to=<?= htmlspecialchars($dateTo) ?>">
                <i class="fas <?= $ml['icon'] ?> me-1"></i><?= htmlspecialchars($ml['label']) ?>
              </a>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>

        <?php if ($module !== 'residents'): ?>
        <form method="GET" class="d-flex align-items-end gap-2 flex-wrap mb-4">
          <input type="hidden" name="module" value="<?= htmlspecialchars($module) ?>">
          <div>
            <label class="form-label small fw-semibold mb-1">From</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
          </div>
          <div>
            <label class="form-label small fw-semibold mb-1">To</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
          </div>
          <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i>Apply</button>
          <a href="<?= $baseUrl ?>?module=<?= htmlspecialchars($module) ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
        </form>
        <?php endif; ?>
      </div><!-- /.rp-controls -->

      <!-- ═══════════════════════════════════════════ REPORT DOCUMENT -->
      <div id="reportPrintArea">
<?php endif; /* end admin/print split — rp-doc content is shared below */ ?>
<div class="rp-doc">

        <!-- Document header -->
        <div class="rp-doc-header">
          <div class="rp-brand-grid">
            <div>
              <img class="rp-brand-seal" src="<?= htmlspecialchars($barangaySealUrl) ?>" alt="Barangay San Jose Seal">
            </div>
            <div class="rp-brand-copy">
              <div class="rp-brand-topline">Republika ng Pilipinas</div>
              <div class="rp-brand-topline">Lalawigan ng Rizal</div>
              <div class="rp-brand-topline">Bayan ng Rodriguez</div>
              <div class="rp-brand-main">Barangay San Jose</div>
              <div class="rp-brand-subline">Office of the Punong Barangay</div>
            </div>
            <div>
              <img class="rp-brand-seal" src="<?= htmlspecialchars($municipalSealUrl) ?>" alt="Municipality of Rodriguez Seal">
            </div>
          </div>
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
