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
    .reports-card-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 1rem;
    }
    .reports-card {
      position: relative;
      display: flex;
      min-height: 245px;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-width: 0;
      padding: 1.65rem 1.5rem;
      overflow: hidden;
      text-align: center;
      color: inherit;
      text-decoration: none;
      border: 1px solid color-mix(in srgb, var(--report-accent) 15%, #e5e7eb);
      border-radius: 1.35rem;
      background: linear-gradient(145deg, #fff 48%, var(--report-soft) 135%);
      box-shadow: 0 .3rem 1rem rgba(15, 23, 42, .055);
      transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    }
    .reports-card::before {
      content: "";
      position: absolute;
      inset: 0 0 auto;
      height: 4px;
      background: var(--report-accent);
      opacity: .75;
    }
    .reports-card::after {
      content: "";
      position: absolute;
      width: 120px;
      height: 120px;
      right: -54px;
      bottom: -64px;
      border-radius: 50%;
      background: var(--report-soft);
      opacity: .85;
      transition: transform .25s ease;
    }
    .reports-card:hover, .reports-card:focus-visible {
      color: inherit;
      transform: translateY(-5px);
      border-color: var(--report-accent);
      box-shadow: 0 .9rem 1.8rem rgba(15, 23, 42, .11);
    }
    .reports-card:hover::after, .reports-card:focus-visible::after {
      transform: scale(1.15);
    }
    .reports-card-icon {
      position: relative;
      z-index: 1;
      display: inline-flex;
      width: 64px;
      height: 64px;
      align-items: center;
      justify-content: center;
      margin-bottom: 1.25rem;
      border: 1px solid color-mix(in srgb, var(--report-accent) 14%, transparent);
      border-radius: 1.25rem;
      color: var(--report-accent);
      background: var(--report-soft);
      box-shadow: inset 0 1px 0 rgba(255,255,255,.8), 0 .4rem .9rem color-mix(in srgb, var(--report-accent) 12%, transparent);
      font-size: 1.45rem;
      transition: transform .2s ease;
    }
    .reports-card:hover .reports-card-icon, .reports-card:focus-visible .reports-card-icon { transform: translateY(-2px) scale(1.06); }
    .reports-card-copy { position: relative; z-index: 1; min-width: 0; width: 100%; }
    .reports-card h2 { margin: 0 0 .6rem; color: #1f2937; font-size: 1.12rem; font-weight: 750; }
    .reports-card p { max-width: 340px; margin: 0 auto; color: #64748b; font-size: .9rem; line-height: 1.55; }
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
  <main id="main-display" class="reports-main flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
    <div class="reports-landing">
      <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C;">Reports</h2>
      <hr><br>

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
            <span class="reports-card-copy">
              <h2><?= htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8') ?></h2>
              <p><?= htmlspecialchars($card['description'], ENT_QUOTES, 'UTF-8') ?></p>
            </span>
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
