<?php
$reportsBaseUrl = appUrl('Admin-End/Reports/Reports.php');
$categoryMeta = [
    'certificate_issuance' => [
        'title' => 'Certificate Issuance',
        'description' => 'Choose a certificate report to review requests, outcomes, channels, revenue, and trends.',
        'icon' => 'fa-file-circle-check',
        'accent' => '#2563eb',
        'soft' => '#eff6ff',
    ],
    'clearance_issuance' => [
        'title' => 'Clearance Issuance',
        'description' => 'Choose a clearance report to analyze applications, status results, and processing activity.',
        'icon' => 'fa-stamp',
        'accent' => '#de710c',
        'soft' => '#fff7ed',
    ],
    'financial' => [
        'title' => 'Financial & Collections',
        'description' => 'Choose the collection, revenue, or receipt report you want to generate.',
        'icon' => 'fa-peso-sign',
        'accent' => '#059669',
        'soft' => '#ecfdf5',
    ],
    'residents' => [
        'title' => 'Residents',
        'description' => 'Choose a demographic, household, sector, or registration report.',
        'icon' => 'fa-users',
        'accent' => '#7c3aed',
        'soft' => '#f5f3ff',
    ],
    'blotter' => [
        'title' => 'Blotter & Cases',
        'description' => 'Choose a case breakdown, status, location, sector, or trend report.',
        'icon' => 'fa-gavel',
        'accent' => '#dc2626',
        'soft' => '#fef2f2',
    ],
    'complaints' => [
        'title' => 'Complaints & Grievances',
        'description' => 'Choose a complaint breakdown, origin, subject, location, sector, or trend report.',
        'icon' => 'fa-comments',
        'accent' => '#0891b2',
        'soft' => '#ecfeff',
    ],
];

$meta = $categoryMeta[$module];
$choices = [[
    'title' => 'Complete ' . $meta['title'] . ' Report',
    'description' => 'Include every available summary, breakdown, table, and chart for this category.',
    'icon' => 'fa-chart-column',
    'query' => ['module' => $module, 'report' => 'complete'],
]];

$issuanceConfig = rp_issuance_module_config($module);
if ($issuanceConfig !== null) {
    foreach ((array)$issuanceConfig['request_types'] as $typeKey => $typeLabel) {
        $choices[] = [
            'title' => $typeLabel,
            'description' => 'Generate a focused issuance report for ' . $typeLabel . '.',
            'icon' => $module === 'certificate_issuance' ? 'fa-file-lines' : 'fa-stamp',
            'query' => [
                'module' => $module,
                'report' => 'request_type',
                'filter_type' => [$typeKey],
            ],
        ];
    }
} else {
    $customizeConfig = rp_report_customize_config($module);
    foreach ((array)($customizeConfig['column_groups'] ?? []) as $group) {
        $sections = array_values((array)($group['sections'] ?? []));
        if ($sections === []) {
            continue;
        }
        $choices[] = [
            'title' => (string)($group['label'] ?? 'Focused Report'),
            'description' => 'Generate a focused ' . strtolower((string)($group['label'] ?? 'report')) . ' with the available filters and PDF options.',
            'icon' => 'fa-chart-pie',
            'query' => [
                'module' => $module,
                'report' => 'section',
                'show_section' => array_values(array_unique(array_merge(['summary'], $sections))),
            ],
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="../../Images/favicon_sanjose.png?v=20260211">
  <title><?= htmlspecialchars($meta['title']) ?> Reports — Barangay San Jose</title>
  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <style>
    .report-choice-page { width: min(1180px, 100%); margin: 0 auto; }
    .report-choice-back {
      display: inline-flex; align-items: center; gap: .45rem; margin-bottom: 1rem;
      color: #64748b; font-weight: 650; text-decoration: none;
    }
    .report-choice-back:hover { color: var(--report-accent); }
    .report-choice-hero {
      display: flex; align-items: center; gap: 1rem; padding: clamp(1.35rem, 3vw, 2.25rem);
      border: 1px solid #e5e7eb; border-radius: 1.25rem;
      background: linear-gradient(135deg, #fff 0%, var(--report-soft) 140%);
      box-shadow: 0 .35rem 1.2rem rgba(15, 23, 42, .06);
    }
    .report-choice-hero-icon, .report-choice-card-icon {
      display: inline-flex; flex: 0 0 auto; align-items: center; justify-content: center;
      color: var(--report-accent); background: var(--report-soft);
    }
    .report-choice-hero-icon { width: 64px; height: 64px; border-radius: 1rem; font-size: 1.5rem; }
    .report-choice-eyebrow {
      color: var(--report-accent); font-size: .76rem; font-weight: 800;
      letter-spacing: .09em; text-transform: uppercase;
    }
    .report-choice-title {
      margin: .25rem 0 .35rem; color: #1f2937;
      font-family: "Charis SIL Bold", Georgia, serif; font-size: clamp(1.65rem, 3vw, 2.25rem);
    }
    .report-choice-copy { max-width: 760px; margin: 0; color: #64748b; line-height: 1.6; }
    .report-choice-heading { margin: 1.5rem 0 .85rem; color: #1f2937; font-size: 1.05rem; font-weight: 750; }
    .report-choice-grid {
      display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem;
    }
    .report-choice-card {
      display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: start;
      gap: .9rem; min-height: 150px; padding: 1.15rem; color: inherit; text-decoration: none;
      border: 1px solid #e5e7eb; border-radius: 1rem; background: #fff;
      box-shadow: 0 .2rem .8rem rgba(15, 23, 42, .045);
      transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .report-choice-card:hover {
      color: inherit; transform: translateY(-2px); border-color: var(--report-accent);
      box-shadow: 0 .7rem 1.4rem rgba(15, 23, 42, .08);
    }
    .report-choice-card-icon { width: 42px; height: 42px; border-radius: .8rem; }
    .report-choice-card h2 { margin: .15rem 0 .4rem; color: #1f2937; font-size: 1rem; font-weight: 750; }
    .report-choice-card p { margin: 0; color: #64748b; font-size: .84rem; line-height: 1.5; }
    .report-choice-arrow { align-self: center; color: var(--report-accent); }
    @media (max-width: 991.98px) { .report-choice-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 575.98px) {
      .report-choice-grid { grid-template-columns: 1fr; }
      .report-choice-hero { align-items: flex-start; flex-direction: column; }
    }
  </style>
</head>
<body>
<div class="d-flex flex-column flex-md-row" style="min-height:100vh;">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
    <div class="report-choice-page" style="--report-accent:<?= htmlspecialchars($meta['accent'], ENT_QUOTES, 'UTF-8') ?>;--report-soft:<?= htmlspecialchars($meta['soft'], ENT_QUOTES, 'UTF-8') ?>">
      <a class="report-choice-back" href="<?= htmlspecialchars($reportsBaseUrl, ENT_QUOTES, 'UTF-8') ?>">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>All report categories
      </a>
      <section class="report-choice-hero">
        <span class="report-choice-hero-icon"><i class="fas <?= htmlspecialchars($meta['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i></span>
        <div>
          <div class="report-choice-eyebrow">Select a report type</div>
          <h1 class="report-choice-title"><?= htmlspecialchars($meta['title']) ?></h1>
          <p class="report-choice-copy"><?= htmlspecialchars($meta['description']) ?></p>
        </div>
      </section>
      <h2 class="report-choice-heading">Available reports</h2>
      <section class="report-choice-grid" aria-label="<?= htmlspecialchars($meta['title']) ?> report types">
        <?php foreach ($choices as $choice): ?>
          <a class="report-choice-card" href="<?= htmlspecialchars($reportsBaseUrl . '?' . http_build_query($choice['query']), ENT_QUOTES, 'UTF-8') ?>">
            <span class="report-choice-card-icon"><i class="fas <?= htmlspecialchars($choice['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i></span>
            <span>
              <h2><?= htmlspecialchars($choice['title']) ?></h2>
              <p><?= htmlspecialchars($choice['description']) ?></p>
            </span>
            <i class="fas fa-arrow-right report-choice-arrow" aria-hidden="true"></i>
          </a>
        <?php endforeach; ?>
      </section>
    </div>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
