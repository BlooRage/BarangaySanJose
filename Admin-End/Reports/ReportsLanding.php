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
    .reports-landing {
      width: min(1180px, 100%);
      margin: 0 auto;
    }
    .reports-landing-hero {
      position: relative;
      overflow: hidden;
      padding: clamp(1.5rem, 3vw, 2.5rem);
      border: 1px solid #e5e7eb;
      border-radius: 1.25rem;
      background: linear-gradient(135deg, #fff 0%, #fffaf5 100%);
      box-shadow: 0 .35rem 1.2rem rgba(15, 23, 42, .06);
    }
    .reports-landing-hero::after {
      content: "";
      position: absolute;
      width: 210px;
      height: 210px;
      right: -60px;
      top: -85px;
      border-radius: 50%;
      background: rgba(222, 113, 12, .09);
    }
    .reports-landing-eyebrow {
      color: #de710c;
      font-size: .78rem;
      font-weight: 800;
      letter-spacing: .09em;
      text-transform: uppercase;
    }
    .reports-landing-title {
      position: relative;
      z-index: 1;
      margin: .35rem 0 .6rem;
      color: #1f2937;
      font-family: "Charis SIL Bold", Georgia, serif;
      font-size: clamp(1.85rem, 4vw, 2.55rem);
    }
    .reports-landing-copy {
      position: relative;
      z-index: 1;
      max-width: 720px;
      margin: 0;
      color: #64748b;
      line-height: 1.65;
    }
    .reports-card-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 1rem;
      margin-top: 1.25rem;
    }
    .reports-card {
      display: flex;
      min-height: 230px;
      flex-direction: column;
      padding: 1.35rem;
      color: inherit;
      text-decoration: none;
      border: 1px solid #e5e7eb;
      border-radius: 1rem;
      background: #fff;
      box-shadow: 0 .2rem .8rem rgba(15, 23, 42, .045);
      transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .reports-card:hover {
      color: inherit;
      transform: translateY(-3px);
      border-color: color-mix(in srgb, var(--report-accent) 35%, #e5e7eb);
      box-shadow: 0 .75rem 1.5rem rgba(15, 23, 42, .09);
    }
    .reports-card-icon {
      display: inline-flex;
      width: 48px;
      height: 48px;
      align-items: center;
      justify-content: center;
      margin-bottom: 1.15rem;
      border-radius: .9rem;
      color: var(--report-accent);
      background: var(--report-soft);
      font-size: 1.2rem;
    }
    .reports-card h2 {
      margin: 0 0 .55rem;
      color: #1f2937;
      font-size: 1.08rem;
      font-weight: 750;
    }
    .reports-card p {
      margin: 0 0 1.25rem;
      color: #64748b;
      font-size: .9rem;
      line-height: 1.55;
    }
    .reports-card-action {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-top: auto;
      color: var(--report-accent);
      font-size: .85rem;
      font-weight: 750;
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
    @media (max-width: 991.98px) {
      .reports-card-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 575.98px) {
      .reports-card-grid { grid-template-columns: 1fr; }
      .reports-card { min-height: 205px; }
    }
  </style>
</head>
<body>
<div class="d-flex flex-column flex-md-row" style="min-height:100vh;">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
    <div class="reports-landing">
      <section class="reports-landing-hero">
        <div class="reports-landing-eyebrow">Barangay intelligence</div>
        <h1 class="reports-landing-title">Reports</h1>
        <p class="reports-landing-copy">Choose a report category to review operational activity, apply filters, customize the report layout, and download a formal PDF.</p>
      </section>

      <section class="reports-card-grid" aria-label="Report categories">
        <?php $visibleCardCount = 0; ?>
        <?php foreach ($reportCards as $card): ?>
          <?php if (!$sbCan($card['permission'])) continue; ?>
          <?php $visibleCardCount++; ?>
          <a
            class="reports-card"
            href="<?= htmlspecialchars($reportsBaseUrl . '?module=' . rawurlencode($card['module']), ENT_QUOTES, 'UTF-8') ?>"
            style="--report-accent:<?= htmlspecialchars($card['accent'], ENT_QUOTES, 'UTF-8') ?>;--report-soft:<?= htmlspecialchars($card['soft'], ENT_QUOTES, 'UTF-8') ?>"
          >
            <span class="reports-card-icon"><i class="fas <?= htmlspecialchars($card['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
            <h2><?= htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p><?= htmlspecialchars($card['description'], ENT_QUOTES, 'UTF-8') ?></p>
            <span class="reports-card-action">Open report <i class="fas fa-arrow-right"></i></span>
          </a>
        <?php endforeach; ?>
        <?php if ($visibleCardCount === 0): ?>
          <div class="reports-empty">No report categories are available for your current role.</div>
        <?php endif; ?>
      </section>
    </div>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
