<?php
require_once __DIR__ . '/../../PhpFiles/General/connection.php';
require_once __DIR__ . '/../includes/admin_guard.php';

// ── Helpers ──────────────────────────────────────────────────────────────────
function rp_table_exists(mysqli $conn, string $t): bool {
    $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
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
    return $ts ? date('M j, Y', $ts) : $d;
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

// ═══════════════════════════════════════════════════════════════════════════════
// MODULE: DOCUMENT REQUESTS
// ═══════════════════════════════════════════════════════════════════════════════
$dr = [];
if ($module === 'document_requests' && rp_table_exists($conn, 'documentrequesttbl')) {
    $df = $conn->real_escape_string($dateFrom);
    $dt = $conn->real_escape_string($dateTo);

    // KPIs
    $kpi = rp_safe_query($conn, "
        SELECT
          COUNT(*) AS total,
          SUM(CASE WHEN LOWER(stage) = 'completed' THEN 1 ELSE 0 END) AS completed,
          SUM(CASE WHEN LOWER(stage) = 'rejected'  THEN 1 ELSE 0 END) AS rejected,
          SUM(CASE WHEN LOWER(stage) NOT IN ('completed','rejected') THEN 1 ELSE 0 END) AS active,
          SUM(CASE WHEN LOWER(stage) = 'completed' AND fee_amount > 0 THEN fee_amount ELSE 0 END) AS revenue
        FROM documentrequesttbl
        WHERE DATE(submitted_at) BETWEEN '{$df}' AND '{$dt}'
    ");
    $dr['kpi'] = $kpi[0] ?? [];

    // By document type
    $dr['by_type'] = rp_safe_query($conn, "
        SELECT
          COALESCE(document_type, 'Unknown') AS document_type,
          COUNT(*) AS total,
          SUM(CASE WHEN LOWER(stage)='completed' THEN 1 ELSE 0 END) AS completed,
          SUM(CASE WHEN LOWER(stage)='rejected'  THEN 1 ELSE 0 END) AS rejected,
          SUM(CASE WHEN LOWER(stage) NOT IN ('completed','rejected') THEN 1 ELSE 0 END) AS active,
          SUM(CASE WHEN LOWER(stage)='completed' AND fee_amount>0 THEN fee_amount ELSE 0 END) AS revenue
        FROM documentrequesttbl
        WHERE DATE(submitted_at) BETWEEN '{$df}' AND '{$dt}'
        GROUP BY document_type
        ORDER BY total DESC
    ");

    // By stage
    $dr['by_stage'] = rp_safe_query($conn, "
        SELECT COALESCE(stage,'Unknown') AS stage, COUNT(*) AS total
        FROM documentrequesttbl
        WHERE DATE(submitted_at) BETWEEN '{$df}' AND '{$dt}'
        GROUP BY stage ORDER BY total DESC
    ");

    // Monthly trend (last 12 months)
    $dr['trend'] = rp_safe_query($conn, "
        SELECT DATE_FORMAT(submitted_at,'%Y-%m') AS month, COUNT(*) AS total,
          SUM(CASE WHEN LOWER(stage)='completed' THEN 1 ELSE 0 END) AS completed
        FROM documentrequesttbl
        WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY month ORDER BY month ASC
    ");

    // Payment method breakdown
    $dr['by_payment'] = rp_safe_query($conn, "
        SELECT COALESCE(payment_method,'Not paid') AS method,
          COUNT(*) AS total,
          SUM(COALESCE(fee_amount,0)) AS revenue
        FROM documentrequesttbl
        WHERE DATE(submitted_at) BETWEEN '{$df}' AND '{$dt}'
          AND LOWER(stage) = 'completed'
        GROUP BY payment_method ORDER BY total DESC
    ");
}

// ═══════════════════════════════════════════════════════════════════════════════
// MODULE: FINANCIAL
// ═══════════════════════════════════════════════════════════════════════════════
$fin = [];
if ($module === 'financial' && rp_table_exists($conn, 'documentrequesttbl')) {
    $df = $conn->real_escape_string($dateFrom);
    $dt = $conn->real_escape_string($dateTo);

    $fin['kpi'] = rp_safe_query($conn, "
        SELECT
          COUNT(*) AS total_issued,
          SUM(fee_amount) AS total_collections,
          SUM(CASE WHEN LOWER(payment_method)='gcash'    THEN fee_amount ELSE 0 END) AS gcash_total,
          SUM(CASE WHEN LOWER(payment_method)='barangay' THEN fee_amount ELSE 0 END) AS walkin_total,
          COUNT(CASE WHEN or_number IS NOT NULL AND or_number != '' THEN 1 END) AS or_count
        FROM documentrequesttbl
        WHERE LOWER(stage)='completed' AND fee_amount > 0
          AND DATE(finance_decision_at) BETWEEN '{$df}' AND '{$dt}'
    ");
    $fin['kpi'] = $fin['kpi'][0] ?? [];

    // Daily log
    $fin['daily_log'] = rp_safe_query($conn, "
        SELECT
          DATE(finance_decision_at) AS collection_date,
          COUNT(*) AS count,
          SUM(fee_amount) AS total,
          SUM(CASE WHEN LOWER(payment_method)='gcash' THEN fee_amount ELSE 0 END) AS gcash,
          SUM(CASE WHEN LOWER(payment_method)='barangay' THEN fee_amount ELSE 0 END) AS walkin
        FROM documentrequesttbl
        WHERE LOWER(stage)='completed' AND fee_amount > 0
          AND DATE(finance_decision_at) BETWEEN '{$df}' AND '{$dt}'
        GROUP BY collection_date ORDER BY collection_date DESC
    ");

    // By document type
    $fin['by_type'] = rp_safe_query($conn, "
        SELECT
          COALESCE(document_type,'Unknown') AS document_type,
          COUNT(*) AS count,
          SUM(fee_amount) AS total
        FROM documentrequesttbl
        WHERE LOWER(stage)='completed' AND fee_amount > 0
          AND DATE(finance_decision_at) BETWEEN '{$df}' AND '{$dt}'
        GROUP BY document_type ORDER BY total DESC
    ");

    // OR log
    $fin['or_log'] = rp_safe_query($conn, "
        SELECT or_number, certificate_number,
          COALESCE(resident_name, resident_first_name) AS resident_name,
          COALESCE(document_type,'—') AS document_type,
          fee_amount, UPPER(payment_method) AS payment_method,
          finance_decision_at
        FROM documentrequesttbl
        WHERE LOWER(stage)='completed' AND or_number IS NOT NULL AND or_number != ''
          AND DATE(finance_decision_at) BETWEEN '{$df}' AND '{$dt}'
        ORDER BY finance_decision_at DESC
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

    // Age distribution
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

    // By area
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

    // By sector
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

    // Monthly registrations
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports – <?= htmlspecialchars($currentLabel) ?> | Barangay San Jose</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body { background: #f8f9fa; }
    .report-kpi { border-left: 4px solid; }
    .report-kpi.blue   { border-color: #0d6efd; }
    .report-kpi.green  { border-color: #198754; }
    .report-kpi.red    { border-color: #dc3545; }
    .report-kpi.orange { border-color: #fd7e14; }
    .report-kpi.purple { border-color: #6f42c1; }
    .report-kpi.teal   { border-color: #0dcaf0; }
    .module-nav .nav-link { font-size: .875rem; white-space: nowrap; }
    .module-nav .nav-link.active { background: #DE710C; color: #fff !important; border-color: #DE710C; }
    .table th { white-space: nowrap; }
    .section-title { color: #DE710C; font-weight: 700; font-size: 1rem; border-bottom: 2px solid #DE710C; padding-bottom: .25rem; margin-bottom: 1rem; }
    canvas { max-height: 280px; }
    @media print {
      #dashboard-sidebar, #admin-mobile-header, .module-nav, .d-print-none { display: none !important; }
      main { padding: 0 !important; }
      .card { box-shadow: none !important; border: 1px solid #dee2e6 !important; break-inside: avoid; }
    }
  </style>
</head>
<body>
<div class="d-flex flex-column flex-md-row" style="min-height:100vh">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <main id="main-display" class="flex-grow-1 p-3 p-md-4 bg-light">

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2 d-print-none">
      <div>
        <h4 class="mb-0 fw-bold" style="color:#DE710C">
          <i class="fas fa-chart-bar me-2"></i>Reports
        </h4>
        <small class="text-muted"><?= htmlspecialchars($currentLabel) ?></small>
      </div>
      <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
        <i class="fas fa-print me-1"></i>Print
      </button>
    </div>

    <!-- Module nav -->
    <div class="module-nav mb-3 d-print-none overflow-auto">
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

    <!-- Date filter (not for residents) -->
    <?php if ($module !== 'residents'): ?>
    <form method="GET" class="d-flex align-items-end gap-2 flex-wrap mb-4 d-print-none">
      <input type="hidden" name="module" value="<?= htmlspecialchars($module) ?>">
      <div>
        <label class="form-label small fw-semibold mb-1">From</label>
        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
      </div>
      <div>
        <label class="form-label small fw-semibold mb-1">To</label>
        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
      </div>
      <button type="submit" class="btn btn-sm btn-primary">
        <i class="fas fa-filter me-1"></i>Apply
      </button>
      <a href="<?= $baseUrl ?>?module=<?= htmlspecialchars($module) ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
      <small class="text-muted align-self-center">
        <?= rp_date_label($dateFrom) ?> – <?= rp_date_label($dateTo) ?>
      </small>
    </form>
    <?php endif; ?>

    <!-- Print header (visible only when printing) -->
    <div class="d-none d-print-block mb-4">
      <h5 class="fw-bold">Barangay San Jose — <?= htmlspecialchars($currentLabel) ?> Report</h5>
      <?php if ($module !== 'residents'): ?>
      <small>Period: <?= rp_date_label($dateFrom) ?> to <?= rp_date_label($dateTo) ?></small>
      <?php else: ?>
      <small>As of <?= date('F j, Y') ?></small>
      <?php endif; ?>
    </div>

<?php
// ═════════════════════════════════════════════════════════════════════════════
// RENDER: DOCUMENT REQUESTS
// ═════════════════════════════════════════════════════════════════════════════
if ($module === 'document_requests'):
  $kpi = $dr['kpi'];
?>
    <!-- KPIs -->
    <div class="row g-3 mb-4">
      <?php
      $cards = [
        ['label'=>'Total Requests',  'value'=>number_format((int)($kpi['total']     ?? 0)), 'icon'=>'fa-file-alt',      'color'=>'blue'],
        ['label'=>'Completed',       'value'=>number_format((int)($kpi['completed'] ?? 0)), 'icon'=>'fa-check-circle',  'color'=>'green'],
        ['label'=>'Active',          'value'=>number_format((int)($kpi['active']    ?? 0)), 'icon'=>'fa-clock',         'color'=>'orange'],
        ['label'=>'Rejected',        'value'=>number_format((int)($kpi['rejected']  ?? 0)), 'icon'=>'fa-times-circle',  'color'=>'red'],
        ['label'=>'Revenue Collected','value'=>'₱' . number_format((float)($kpi['revenue'] ?? 0), 2), 'icon'=>'fa-peso-sign','color'=>'green'],
      ];
      foreach ($cards as $c): ?>
      <div class="col-6 col-md-4 col-xl-2">
        <div class="card report-kpi <?= $c['color'] ?> h-100 border-0 shadow-sm p-3">
          <div class="text-muted small mb-1"><i class="fas <?= $c['icon'] ?> me-1"></i><?= $c['label'] ?></div>
          <div class="fw-bold fs-5"><?= $c['value'] ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="row g-4">
      <!-- By document type -->
      <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm p-3">
          <div class="section-title">By Document Type</div>
          <?php if (empty($dr['by_type'])): ?>
            <p class="text-muted small">No data for selected period.</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Document Type</th>
                  <th class="text-center">Total</th>
                  <th class="text-center">Completed</th>
                  <th class="text-center">Active</th>
                  <th class="text-center">Rejected</th>
                  <th class="text-end">Revenue</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($dr['by_type'] as $r): ?>
                <tr>
                  <td class="fw-semibold"><?= htmlspecialchars($r['document_type']) ?></td>
                  <td class="text-center"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center text-success"><?= number_format((int)$r['completed']) ?></td>
                  <td class="text-center text-warning"><?= number_format((int)$r['active']) ?></td>
                  <td class="text-center text-danger"><?= number_format((int)$r['rejected']) ?></td>
                  <td class="text-end">₱<?= number_format((float)$r['revenue'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot class="table-light fw-bold">
                <tr>
                  <td>Total</td>
                  <td class="text-center"><?= number_format((int)($kpi['total'] ?? 0)) ?></td>
                  <td class="text-center text-success"><?= number_format((int)($kpi['completed'] ?? 0)) ?></td>
                  <td class="text-center text-warning"><?= number_format((int)($kpi['active'] ?? 0)) ?></td>
                  <td class="text-center text-danger"><?= number_format((int)($kpi['rejected'] ?? 0)) ?></td>
                  <td class="text-end">₱<?= number_format((float)($kpi['revenue'] ?? 0), 2) ?></td>
                </tr>
              </tfoot>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- By stage + payment method -->
      <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm p-3 mb-3">
          <div class="section-title">By Stage</div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light"><tr><th>Stage</th><th class="text-end">Count</th></tr></thead>
              <tbody>
                <?php foreach ($dr['by_stage'] as $r): ?>
                <tr><td><?= htmlspecialchars(ucwords(str_replace('_',' ',$r['stage']))) ?></td><td class="text-end"><?= number_format((int)$r['total']) ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="card border-0 shadow-sm p-3">
          <div class="section-title">Payment Method (Completed)</div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light"><tr><th>Method</th><th class="text-center">Count</th><th class="text-end">Revenue</th></tr></thead>
              <tbody>
                <?php foreach ($dr['by_payment'] as $r): ?>
                <tr>
                  <td><?= htmlspecialchars(ucfirst($r['method'])) ?></td>
                  <td class="text-center"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-end">₱<?= number_format((float)$r['revenue'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Monthly trend chart -->
      <div class="col-12">
        <div class="card border-0 shadow-sm p-3">
          <div class="section-title">Monthly Trend (Last 12 Months)</div>
          <canvas id="drTrendChart"></canvas>
        </div>
      </div>
    </div>

<?php
// ═════════════════════════════════════════════════════════════════════════════
// RENDER: FINANCIAL
// ═════════════════════════════════════════════════════════════════════════════
elseif ($module === 'financial'):
  $kpi = $fin['kpi'];
?>
    <div class="row g-3 mb-4">
      <?php
      $cards = [
        ['label'=>'Total Collections','value'=>'₱'.number_format((float)($kpi['total_collections']??0),2),'icon'=>'fa-peso-sign','color'=>'green'],
        ['label'=>'GCash','value'=>'₱'.number_format((float)($kpi['gcash_total']??0),2),'icon'=>'fa-mobile-alt','color'=>'blue'],
        ['label'=>'Walk-in','value'=>'₱'.number_format((float)($kpi['walkin_total']??0),2),'icon'=>'fa-walking','color'=>'orange'],
        ['label'=>'OR Numbers Issued','value'=>number_format((int)($kpi['or_count']??0)),'icon'=>'fa-receipt','color'=>'purple'],
        ['label'=>'Transactions','value'=>number_format((int)($kpi['total_issued']??0)),'icon'=>'fa-list','color'=>'teal'],
      ];
      foreach ($cards as $c): ?>
      <div class="col-6 col-md-4 col-xl-2">
        <div class="card report-kpi <?= $c['color'] ?> h-100 border-0 shadow-sm p-3">
          <div class="text-muted small mb-1"><i class="fas <?= $c['icon'] ?> me-1"></i><?= $c['label'] ?></div>
          <div class="fw-bold fs-5"><?= $c['value'] ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="row g-4">
      <!-- Daily log -->
      <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm p-3">
          <div class="section-title">Daily Collection Summary</div>
          <?php if (empty($fin['daily_log'])): ?>
            <p class="text-muted small">No collections for selected period.</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr><th>Date</th><th class="text-center">Count</th><th class="text-end">GCash</th><th class="text-end">Walk-in</th><th class="text-end">Total</th></tr>
              </thead>
              <tbody>
                <?php foreach ($fin['daily_log'] as $r): ?>
                <tr>
                  <td><?= htmlspecialchars(rp_date_label($r['collection_date'])) ?></td>
                  <td class="text-center"><?= number_format((int)$r['count']) ?></td>
                  <td class="text-end">₱<?= number_format((float)$r['gcash'], 2) ?></td>
                  <td class="text-end">₱<?= number_format((float)$r['walkin'], 2) ?></td>
                  <td class="text-end fw-semibold">₱<?= number_format((float)$r['total'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot class="table-light fw-bold">
                <tr>
                  <td>Total</td>
                  <td class="text-center"><?= number_format((int)($kpi['total_issued']??0)) ?></td>
                  <td class="text-end">₱<?= number_format((float)($kpi['gcash_total']??0),2) ?></td>
                  <td class="text-end">₱<?= number_format((float)($kpi['walkin_total']??0),2) ?></td>
                  <td class="text-end">₱<?= number_format((float)($kpi['total_collections']??0),2) ?></td>
                </tr>
              </tfoot>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- By document type -->
      <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm p-3 mb-3">
          <div class="section-title">Collections by Document Type</div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light"><tr><th>Type</th><th class="text-center">Count</th><th class="text-end">Amount</th></tr></thead>
              <tbody>
                <?php foreach ($fin['by_type'] as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['document_type']) ?></td>
                  <td class="text-center"><?= number_format((int)$r['count']) ?></td>
                  <td class="text-end">₱<?= number_format((float)$r['total'],2) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- OR log -->
      <div class="col-12">
        <div class="card border-0 shadow-sm p-3">
          <div class="section-title">OR Number Log</div>
          <?php if (empty($fin['or_log'])): ?>
            <p class="text-muted small">No OR records for selected period.</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr><th>OR No.</th><th>Cert. No.</th><th>Resident</th><th>Document Type</th><th>Method</th><th class="text-end">Amount</th><th>Date Verified</th></tr>
              </thead>
              <tbody>
                <?php foreach ($fin['or_log'] as $r): ?>
                <tr>
                  <td class="fw-semibold"><?= htmlspecialchars($r['or_number']) ?></td>
                  <td class="small"><?= htmlspecialchars($r['certificate_number'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($r['resident_name'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($r['document_type']) ?></td>
                  <td>
                    <span class="badge <?= strtolower($r['payment_method']) === 'GCASH' ? 'bg-primary' : 'bg-secondary' ?>">
                      <?= htmlspecialchars($r['payment_method']) ?>
                    </span>
                  </td>
                  <td class="text-end">₱<?= number_format((float)$r['fee_amount'],2) ?></td>
                  <td class="small text-muted"><?= htmlspecialchars($r['finance_decision_at'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

<?php
// ═════════════════════════════════════════════════════════════════════════════
// RENDER: RESIDENTS
// ═════════════════════════════════════════════════════════════════════════════
elseif ($module === 'residents'):
  $kpi = $res['kpi'];
?>
    <div class="row g-3 mb-4">
      <?php
      $cards = [
        ['label'=>'Total Registered','value'=>number_format((int)($kpi['total']       ??0)),'icon'=>'fa-users',       'color'=>'blue'],
        ['label'=>'Verified',        'value'=>number_format((int)($kpi['verified']    ??0)),'icon'=>'fa-user-check',  'color'=>'green'],
        ['label'=>'Pending',         'value'=>number_format((int)($kpi['pending']     ??0)),'icon'=>'fa-user-clock',  'color'=>'orange'],
        ['label'=>'Not Verified',    'value'=>number_format((int)($kpi['not_verified']??0)),'icon'=>'fa-user-times',  'color'=>'red'],
        ['label'=>'Archived',        'value'=>number_format((int)($kpi['archived']    ??0)),'icon'=>'fa-archive',     'color'=>'purple'],
      ];
      foreach ($cards as $c): ?>
      <div class="col-6 col-md-4 col-xl-2">
        <div class="card report-kpi <?= $c['color'] ?> h-100 border-0 shadow-sm p-3">
          <div class="text-muted small mb-1"><i class="fas <?= $c['icon'] ?> me-1"></i><?= $c['label'] ?></div>
          <div class="fw-bold fs-5"><?= $c['value'] ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="row g-4">
      <!-- Gender -->
      <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm p-3 h-100">
          <div class="section-title">Gender Distribution</div>
          <canvas id="genderChart"></canvas>
          <div class="table-responsive mt-3">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light"><tr><th>Gender</th><th class="text-end">Count</th></tr></thead>
              <tbody>
                <?php foreach ($res['by_gender'] as $r): ?>
                <tr><td><?= htmlspecialchars(ucfirst($r['gender'])) ?></td><td class="text-end"><?= number_format((int)$r['total']) ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Age distribution -->
      <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm p-3 h-100">
          <div class="section-title">Age Distribution (Verified)</div>
          <canvas id="ageChart"></canvas>
          <div class="table-responsive mt-3">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light"><tr><th>Age Group</th><th class="text-end">Count</th></tr></thead>
              <tbody>
                <?php foreach ($res['age_buckets'] as $label => $count): ?>
                <tr><td><?= htmlspecialchars($label) ?></td><td class="text-end"><?= number_format($count) ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Sector membership -->
      <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm p-3 h-100">
          <div class="section-title">Sector Membership (Verified)</div>
          <?php if (empty($res['by_sector'])): ?>
            <p class="text-muted small">No sector data.</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light"><tr><th>Sector</th><th class="text-end">Count</th></tr></thead>
              <tbody>
                <?php foreach ($res['by_sector'] as $sector => $count): ?>
                <tr><td><?= htmlspecialchars($sector) ?></td><td class="text-end"><?= number_format($count) ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- By area -->
      <?php if (!empty($res['by_area'])): ?>
      <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm p-3">
          <div class="section-title">Residents by Area</div>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light"><tr><th>Area</th><th class="text-end">Count</th></tr></thead>
              <tbody>
                <?php foreach ($res['by_area'] as $r): ?>
                <tr><td><?= htmlspecialchars($r['area']) ?></td><td class="text-end"><?= number_format((int)$r['total']) ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Monthly registrations -->
      <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm p-3">
          <div class="section-title">Monthly Registrations (Last 12 Months)</div>
          <canvas id="resRegChart"></canvas>
        </div>
      </div>
    </div>

<?php
// ═════════════════════════════════════════════════════════════════════════════
// RENDER: APPOINTMENTS
// ═════════════════════════════════════════════════════════════════════════════
elseif ($module === 'appointments'):
  $kpi = $appt['kpi'];
?>
    <div class="row g-3 mb-4">
      <?php
      $cards = [
        ['label'=>'Total','value'=>number_format((int)($kpi['total']    ??0)),'icon'=>'fa-calendar','color'=>'blue'],
        ['label'=>'Scheduled','value'=>number_format((int)($kpi['scheduled']??0)),'icon'=>'fa-calendar-check','color'=>'orange'],
        ['label'=>'Completed','value'=>number_format((int)($kpi['completed']??0)),'icon'=>'fa-check-circle','color'=>'green'],
        ['label'=>'Cancelled','value'=>number_format((int)($kpi['cancelled']??0)),'icon'=>'fa-times-circle','color'=>'red'],
        ['label'=>'Pending','value'=>number_format((int)($kpi['pending'] ??0)),'icon'=>'fa-clock','color'=>'purple'],
      ];
      foreach ($cards as $c): ?>
      <div class="col-6 col-md-4 col-xl-2">
        <div class="card report-kpi <?= $c['color'] ?> h-100 border-0 shadow-sm p-3">
          <div class="text-muted small mb-1"><i class="fas <?= $c['icon'] ?> me-1"></i><?= $c['label'] ?></div>
          <div class="fw-bold fs-5"><?= $c['value'] ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="row g-4">
      <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm p-3 mb-3">
          <div class="section-title">By Status</div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light"><tr><th>Status</th><th class="text-end">Count</th></tr></thead>
              <tbody>
                <?php foreach ($appt['by_status'] as $r): ?>
                <tr><td><?= htmlspecialchars($r['status']) ?></td><td class="text-end"><?= number_format((int)$r['total']) ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="card border-0 shadow-sm p-3">
          <div class="section-title">By Purpose (Top 20)</div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light"><tr><th>Purpose</th><th class="text-end">Count</th></tr></thead>
              <tbody>
                <?php foreach ($appt['by_purpose'] as $r): ?>
                <tr><td class="small"><?= htmlspecialchars($r['purpose']) ?></td><td class="text-end"><?= number_format((int)$r['total']) ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm p-3">
          <div class="section-title">Monthly Trend (Last 12 Months)</div>
          <canvas id="apptTrendChart"></canvas>
        </div>
      </div>
    </div>

<?php
// ═════════════════════════════════════════════════════════════════════════════
// RENDER: BLOTTER
// ═════════════════════════════════════════════════════════════════════════════
elseif ($module === 'blotter'):
  $kpi = $blot['kpi'];
?>
    <div class="row g-3 mb-4">
      <?php
      $cards = [
        ['label'=>'Total Cases',    'value'=>number_format((int)($kpi['total']          ??0)),'icon'=>'fa-gavel',        'color'=>'blue'],
        ['label'=>'Active',         'value'=>number_format((int)($kpi['active']         ??0)),'icon'=>'fa-exclamation',  'color'=>'orange'],
        ['label'=>'Resolved',       'value'=>number_format((int)($kpi['resolved']       ??0)),'icon'=>'fa-check',        'color'=>'green'],
        ['label'=>'Blotter Cases',  'value'=>number_format((int)($kpi['blotter_count']  ??0)),'icon'=>'fa-book',         'color'=>'purple'],
        ['label'=>'Complaint Cases','value'=>number_format((int)($kpi['complaint_count']??0)),'icon'=>'fa-comment-dots', 'color'=>'teal'],
      ];
      foreach ($cards as $c): ?>
      <div class="col-6 col-md-4 col-xl-2">
        <div class="card report-kpi <?= $c['color'] ?> h-100 border-0 shadow-sm p-3">
          <div class="text-muted small mb-1"><i class="fas <?= $c['icon'] ?> me-1"></i><?= $c['label'] ?></div>
          <div class="fw-bold fs-5"><?= $c['value'] ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="row g-4">
      <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm p-3 mb-3">
          <div class="section-title">By Complaint Type (Top 20)</div>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light"><tr><th>Type</th><th class="text-center">Total</th><th class="text-center">Resolved</th></tr></thead>
              <tbody>
                <?php foreach ($blot['by_type'] as $r): ?>
                <tr>
                  <td class="small"><?= htmlspecialchars($r['complaint_type']) ?></td>
                  <td class="text-center"><?= number_format((int)$r['total']) ?></td>
                  <td class="text-center text-success"><?= number_format((int)$r['resolved']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="card border-0 shadow-sm p-3">
          <div class="section-title">By Status</div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light"><tr><th>Status</th><th class="text-end">Count</th></tr></thead>
              <tbody>
                <?php foreach ($blot['by_status'] as $r): ?>
                <tr><td><?= htmlspecialchars($r['status']) ?></td><td class="text-end"><?= number_format((int)$r['total']) ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm p-3">
          <div class="section-title">Monthly Trend (Last 12 Months)</div>
          <canvas id="blotterTrendChart"></canvas>
        </div>
      </div>
    </div>

<?php
// ═════════════════════════════════════════════════════════════════════════════
// RENDER: COMPLAINTS
// ═════════════════════════════════════════════════════════════════════════════
elseif ($module === 'complaints'):
  $kpi = $comp['kpi'];
?>
    <div class="row g-3 mb-4">
      <?php
      $cards = [
        ['label'=>'Total Complaints','value'=>number_format((int)($kpi['total']       ??0)),'icon'=>'fa-comments',    'color'=>'blue'],
        ['label'=>'Escalated',       'value'=>number_format((int)($kpi['escalated']   ??0)),'icon'=>'fa-arrow-up',    'color'=>'red'],
        ['label'=>'Not Escalated',   'value'=>number_format((int)($kpi['unescalated'] ??0)),'icon'=>'fa-minus-circle','color'=>'orange'],
        ['label'=>'Walk-in',         'value'=>number_format((int)($kpi['walkin']      ??0)),'icon'=>'fa-walking',     'color'=>'purple'],
        ['label'=>'Online',          'value'=>number_format((int)($kpi['online_count']??0)),'icon'=>'fa-globe',       'color'=>'teal'],
      ];
      foreach ($cards as $c): ?>
      <div class="col-6 col-md-4 col-xl-2">
        <div class="card report-kpi <?= $c['color'] ?> h-100 border-0 shadow-sm p-3">
          <div class="text-muted small mb-1"><i class="fas <?= $c['icon'] ?> me-1"></i><?= $c['label'] ?></div>
          <div class="fw-bold fs-5"><?= $c['value'] ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="row g-4">
      <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm p-3 mb-3">
          <div class="section-title">By Origin</div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light"><tr><th>Origin</th><th class="text-end">Count</th></tr></thead>
              <tbody>
                <?php foreach ($comp['by_origin'] as $r): ?>
                <tr><td><?= htmlspecialchars(ucwords(str_replace('_',' ',$r['origin']))) ?></td><td class="text-end"><?= number_format((int)$r['total']) ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="card border-0 shadow-sm p-3">
          <div class="section-title">By Subject Kind</div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light"><tr><th>Kind</th><th class="text-end">Count</th></tr></thead>
              <tbody>
                <?php foreach ($comp['by_kind'] as $r): ?>
                <tr><td><?= htmlspecialchars($r['kind']) ?></td><td class="text-end"><?= number_format((int)$r['total']) ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm p-3">
          <div class="section-title">Monthly Trend (Last 12 Months)</div>
          <canvas id="compTrendChart"></canvas>
        </div>
      </div>
    </div>

<?php endif; ?>

  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.font.size = 12;
Chart.defaults.color = '#6c757d';

function mkChart(id, type, labels, datasets, opts = {}) {
  const el = document.getElementById(id);
  if (!el) return;
  new Chart(el, { type, data: { labels, datasets }, options: { responsive: true, plugins: { legend: { position: 'bottom' } }, ...opts } });
}

<?php if ($module === 'document_requests' && !empty($dr['trend'])): ?>
(function(){
  const trend = <?= json_encode($dr['trend']) ?>;
  mkChart('drTrendChart', 'bar',
    trend.map(r => r.month),
    [
      { label: 'Total',     data: trend.map(r => +r.total),     backgroundColor: 'rgba(13,110,253,0.6)' },
      { label: 'Completed', data: trend.map(r => +r.completed), backgroundColor: 'rgba(25,135,84,0.7)'  },
    ]
  );
})();
<?php endif; ?>

<?php if ($module === 'residents'): ?>
(function(){
  <?php if (!empty($res['by_gender'])): ?>
  mkChart('genderChart', 'doughnut',
    <?= json_encode(array_map(fn($r) => ucfirst($r['gender']), $res['by_gender'])) ?>,
    [{ data: <?= json_encode(array_map(fn($r) => (int)$r['total'], $res['by_gender'])) ?>, backgroundColor: ['#0d6efd','#dc3545','#adb5bd'] }]
  );
  <?php endif; ?>
  <?php if (!empty($res['age_buckets'])): ?>
  mkChart('ageChart', 'bar',
    <?= json_encode(array_keys($res['age_buckets'])) ?>,
    [{ label: 'Count', data: <?= json_encode(array_values($res['age_buckets'])) ?>, backgroundColor: 'rgba(111,66,193,0.65)' }],
    { indexAxis: 'y' }
  );
  <?php endif; ?>
  <?php if (!empty($res['monthly_reg'])): ?>
  mkChart('resRegChart', 'line',
    <?= json_encode(array_map(fn($r) => $r['month'], $res['monthly_reg'])) ?>,
    [{ label: 'Registrations', data: <?= json_encode(array_map(fn($r) => (int)$r['total'], $res['monthly_reg'])) ?>,
      borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,0.1)', fill: true, tension: 0.3 }]
  );
  <?php endif; ?>
})();
<?php endif; ?>

<?php if ($module === 'appointments' && !empty($appt['trend'])): ?>
(function(){
  const t = <?= json_encode($appt['trend']) ?>;
  mkChart('apptTrendChart', 'bar', t.map(r => r.month),
    [
      { label: 'Total',     data: t.map(r => +r.total),     backgroundColor: 'rgba(13,110,253,0.6)' },
      { label: 'Completed', data: t.map(r => +r.completed), backgroundColor: 'rgba(25,135,84,0.7)'  },
    ]
  );
})();
<?php endif; ?>

<?php if ($module === 'blotter' && !empty($blot['trend'])): ?>
(function(){
  const t = <?= json_encode($blot['trend']) ?>;
  mkChart('blotterTrendChart', 'line', t.map(r => r.month),
    [{ label: 'Cases', data: t.map(r => +r.total), borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,0.1)', fill: true, tension: 0.3 }]
  );
})();
<?php endif; ?>

<?php if ($module === 'complaints' && !empty($comp['trend'])): ?>
(function(){
  const t = <?= json_encode($comp['trend']) ?>;
  mkChart('compTrendChart', 'bar', t.map(r => r.month),
    [
      { label: 'Total',     data: t.map(r => +r.total),     backgroundColor: 'rgba(13,110,253,0.6)' },
      { label: 'Escalated', data: t.map(r => +r.escalated), backgroundColor: 'rgba(220,53,69,0.7)'  },
    ]
  );
})();
<?php endif; ?>
</script>
</body>
</html>
