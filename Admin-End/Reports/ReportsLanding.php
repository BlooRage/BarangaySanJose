<?php
$reportsBaseUrl = appUrl('Admin-End/Reports/Reports.php');
$reportCards = [
    [
        'permission' => 'reports_certificate_issuance',
        'module' => 'certificate_issuance',
        'title' => 'Certificate Issuance',
        'description' => 'Review certificate requests, issuance outcomes, service channels, and monthly trends.',
        'icon' => 'fa-file-circle-check',
        'accent' => '#2563eb',
        'soft' => '#eff6ff',
    ],
    [
        'permission' => 'reports_clearance_issuance',
        'module' => 'clearance_issuance',
        'title' => 'Clearance Issuance',
        'description' => 'Analyze clearance applications, status results, document types, and processing activity.',
        'icon' => 'fa-stamp',
        'accent' => '#de710c',
        'soft' => '#fff7ed',
    ],
    [
        'permission' => 'reports_financial',
        'module' => 'financial',
        'title' => 'Financial & Collections',
        'description' => 'Track collections, payment channels, revenue sources, and transaction trends.',
        'icon' => 'fa-peso-sign',
        'accent' => '#059669',
        'soft' => '#ecfdf5',
    ],
    [
        'permission' => 'reports_residents',
        'module' => 'residents',
        'title' => 'Residents',
        'description' => 'Explore resident demographics, households, sectors, employment, gender, and age groups.',
        'icon' => 'fa-users',
        'accent' => '#7c3aed',
        'soft' => '#f5f3ff',
    ],
    [
        'permission' => 'reports_blotter',
        'module' => 'blotter',
        'title' => 'Blotter & Cases',
        'description' => 'Summarize incident types, case status, affected areas, sectors, and monthly activity.',
        'icon' => 'fa-gavel',
        'accent' => '#dc2626',
        'soft' => '#fef2f2',
    ],
    [
        'permission' => 'reports_complaints',
        'module' => 'complaints',
        'title' => 'Complaints & Grievances',
        'description' => 'Monitor complaint types, origins, subjects, escalation results, and reporting trends.',
        'icon' => 'fa-comments',
        'accent' => '#0891b2',
        'soft' => '#ecfeff',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="../../Images/favicon_sanjose.png?v=20260211">
  <title>Reports — Barangay San Jose</title>
  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <style>
    :root { --reports-accent: #de710c; }
    .reports-main { min-width: 0; overflow-x: hidden; }
    .reports-landing { width: 100%; max-width: none; margin: 0; }
    .reports-page-title {
      margin: 0 0 .3rem;
      color: var(--reports-accent);
      font-family: "Charis SIL Bold", Georgia, serif;
    }
    .reports-panel {
      padding: 1.25rem;
      border: 1px solid #dee2e6;
      border-radius: 1rem;
      background: #fff;
      box-shadow: 0 .125rem .25rem rgba(0, 0, 0, .075);
    }
    .reports-panel-head {
      padding-bottom: .75rem;
      margin-bottom: .75rem;
      border-bottom: 1px solid #edf0f3;
    }
    .reports-panel-title { margin: 0; color: #212529; font-size: 1.05rem; font-weight: 700; }
    .reports-panel-copy { max-width: 760px; margin: .2rem 0 0; color: #6c757d; font-size: .9rem; }
    .reports-card-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: .6rem;
    }
    .reports-card {
      display: grid;
      grid-template-columns: 2.6rem minmax(0, 1fr) auto;
      align-items: center;
      gap: .9rem;
      min-width: 0;
      padding: .78rem 1rem;
      color: inherit;
      text-decoration: none;
      border: 1px solid #e4e8ed;
      border-radius: .75rem;
      background: #fff;
      transition: border-color .15s, box-shadow .15s, background-color .15s;
    }
    .reports-card:hover, .reports-card:focus-visible {
      color: inherit;
      border-color: #f1b56d;
      background: #fffaf4;
      box-shadow: 0 .25rem .75rem rgba(222, 113, 12, .08);
    }
    .reports-card-icon {
      display: inline-grid;
      width: 2.6rem;
      height: 2.6rem;
      place-items: center;
      align-items: center;
      justify-content: center;
      border-radius: .7rem;
      color: var(--reports-accent);
      background: #fff4e8;
      font-size: 1rem;
    }
    .reports-card-copy { min-width: 0; }
    .reports-card h2 { margin: 0; color: #212529; font-size: .95rem; font-weight: 700; }
    .reports-card p { margin: .12rem 0 0; color: #6c757d; font-size: .9rem; line-height: 1.3; }
    .reports-card-action {
      color: var(--reports-accent);
      font-size: .9rem;
      white-space: nowrap;
    }
    .reports-empty {
      grid-column: 1 / -1;
      padding: 2.5rem;
      text-align: center;
      color: #64748b;
      border: 1px dashed #cbd5e1;
      border-radius: 1rem;
      background: #fff;
    }
    @media (max-width: 767.98px) {
      .reports-card-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 575.98px) {
      .reports-panel { padding: .85rem; }
      .reports-card { grid-template-columns: 2.4rem minmax(0, 1fr) auto; padding: .85rem; gap: .7rem; }
      .reports-card-icon { width: 2.4rem; height: 2.4rem; }
      .reports-card-action span { display: none; }
    }
  </style>
</head>
<body>
<div class="d-flex flex-column flex-md-row" style="min-height:100vh;">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main id="main-display" class="reports-main flex-grow-1 p-3 p-md-4 bg-light">
    <div class="reports-landing">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
          <h1 class="reports-page-title h2">Reports</h1>
          <p class="mb-0 text-muted">Review barangay activity, customize report content, and generate formal PDF reports.</p>
        </div>
      </div>
      <hr class="mt-0 mb-4">

      <section class="reports-panel" aria-labelledby="reportsCategoryHeading">
        <div class="reports-panel-head">
          <h2 class="reports-panel-title" id="reportsCategoryHeading">Report Categories</h2>
          <p class="reports-panel-copy">Choose the operational area you want to review. Each category opens its available report types.</p>
        </div>
        <div class="reports-card-grid">
        <?php $visibleCardCount = 0; ?>
        <?php foreach ($reportCards as $card): ?>
          <?php if (!$sbCan($card['permission'])) continue; ?>
          <?php $visibleCardCount++; ?>
          <a
            class="reports-card"
            href="<?= htmlspecialchars($reportsBaseUrl . '?module=' . rawurlencode($card['module']), ENT_QUOTES, 'UTF-8') ?>"
          >
            <span class="reports-card-icon"><i class="fas <?= htmlspecialchars($card['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
            <span class="reports-card-copy">
              <h2><?= htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8') ?></h2>
              <p><?= htmlspecialchars($card['description'], ENT_QUOTES, 'UTF-8') ?></p>
            </span>
            <span class="reports-card-action"><span class="me-2">Open</span><i class="fas fa-arrow-right"></i></span>
          </a>
        <?php endforeach; ?>
        <?php if ($visibleCardCount === 0): ?>
          <div class="reports-empty">No report categories are available for your current role.</div>
        <?php endif; ?>
        </div>
      </section>
    </div>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
