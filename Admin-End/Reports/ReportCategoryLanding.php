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
$settingsUrl = $reportsBaseUrl . '?' . http_build_query([
    'module' => $module,
    'report' => 'signatory_settings',
]);

$issuanceConfig = rp_issuance_module_config($module);
if ($issuanceConfig !== null) {
    $choices[] = [
        'title' => 'All Requesters Masterlist',
        'description' => 'Generate one masterlist of everyone who requested any document in this category.',
        'icon' => 'fa-users',
        'query' => [
            'module' => $module,
            'report' => 'requester_masterlist',
            'show_section' => ['requesters'],
        ],
    ];
    foreach ((array)$issuanceConfig['request_types'] as $typeKey => $typeLabel) {
        $choices[] = [
            'title' => $typeLabel . ' Summary',
            'description' => 'Generate statistics, breakdowns, charts, revenue, and trends for ' . $typeLabel . '.',
            'icon' => 'fa-chart-column',
            'query' => [
                'module' => $module,
                'report' => 'document_summary',
                'filter_type' => [$typeKey],
                'show_section' => ['summary', 'breakdown', 'charts', 'tables', 'channel', 'revenue', 'trend'],
            ],
        ];
        $choices[] = [
            'title' => $typeLabel . ' Requester Masterlist',
            'description' => 'List every person who requested ' . $typeLabel . ', with request and status details.',
            'icon' => 'fa-users-rectangle',
            'query' => [
                'module' => $module,
                'report' => 'requester_masterlist',
                'filter_type' => [$typeKey],
                'show_section' => ['requesters'],
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
    .report-choice-main { min-width: 0; overflow-x: hidden; }
    .report-choice-page { width: 100%; max-width: none; margin: 0; }
    .report-choice-back {
      display: inline-flex; align-items: center; gap: .45rem;
      color: #6c757d; font-size: .9rem; font-weight: 600; text-decoration: none;
    }
    .report-choice-back:hover { color: #de710c; }
    .report-choice-panel { padding: 1.25rem; border: 1px solid #dee2e6; border-radius: 1rem; background: #fff; box-shadow: 0 .125rem .25rem rgba(0,0,0,.075); }
    .report-choice-panel-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding-bottom: .75rem; margin-bottom: .75rem; border-bottom: 1px solid #edf0f3; }
    .report-choice-heading { margin: 0; color: #212529; font-size: 1.05rem; font-weight: 700; }
    .report-choice-panel-copy { max-width: 760px; margin: .2rem 0 0; color: #6c757d; font-size: .9rem; }
    .report-choice-settings {
      display: inline-flex; flex: 0 0 auto; align-items: center; gap: .45rem; padding: .55rem .9rem;
      color: var(--report-accent); font-size: .875rem; font-weight: 700; text-decoration: none;
      border: 1px solid var(--report-accent); border-radius: .65rem; background: #fff;
      transition: color .18s ease, background-color .18s ease, box-shadow .18s ease;
    }
    .report-choice-settings:hover, .report-choice-settings:focus-visible {
      color: #fff; background: var(--report-accent); box-shadow: 0 .35rem .8rem rgba(15,23,42,.12);
    }
    .report-choice-grid {
      display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem;
    }
    .report-choice-card {
      display: flex; min-height: 230px; flex-direction: column; align-items: center;
      justify-content: center; min-width: 0; padding: 1.35rem; color: inherit;
      text-align: center; text-decoration: none; border: 1px solid #e5e7eb;
      border-radius: 1rem; background: #fff; box-shadow: 0 .2rem .8rem rgba(15,23,42,.045);
      transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .report-choice-card:hover, .report-choice-card:focus-visible {
      color: inherit; transform: translateY(-3px); border-color: var(--report-accent);
      box-shadow: 0 .75rem 1.5rem rgba(15,23,42,.09);
    }
    .report-choice-card-icon { display: inline-flex; width: 48px; height: 48px; flex: 0 0 auto; align-items: center; justify-content: center; margin-bottom: 1.15rem; border-radius: .9rem; color: var(--report-accent); background: var(--report-soft); font-size: 1.2rem; }
    .report-choice-card-copy { min-width: 0; width: 100%; }
    .report-choice-card h2 { margin: 0 0 .55rem; color: #1f2937; font-size: 1.08rem; font-weight: 750; }
    .report-choice-card p { margin: 0; color: #64748b; font-size: .9rem; line-height: 1.55; }
    @media (max-width: 991.98px) { .report-choice-grid { grid-template-columns: repeat(2, minmax(0,1fr)); } }
    @media (max-width: 575.98px) {
      .report-choice-panel { padding: .85rem; }
      .report-choice-panel-head { align-items: center; }
      .report-choice-grid { grid-template-columns: 1fr; }
      .report-choice-card { min-height: 205px; }
    }
  </style>
</head>
<body>
<div class="d-flex flex-column flex-md-row" style="min-height:100vh;">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main id="main-display" class="report-choice-main flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
    <div class="report-choice-page" style="--report-accent:<?= htmlspecialchars($meta['accent'], ENT_QUOTES, 'UTF-8') ?>;--report-soft:<?= htmlspecialchars($meta['soft'], ENT_QUOTES, 'UTF-8') ?>">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C;"><?= htmlspecialchars($meta['title']) ?> Reports</h2>
        <a class="report-choice-back" href="<?= htmlspecialchars($reportsBaseUrl, ENT_QUOTES, 'UTF-8') ?>">
          <i class="fas fa-arrow-left" aria-hidden="true"></i>All report categories
        </a>
      </div>
      <hr><br>
      <section class="report-choice-panel" aria-labelledby="availableReportsHeading">
        <div class="report-choice-panel-head">
          <div>
            <h2 class="report-choice-heading" id="availableReportsHeading">Available Reports</h2>
            <p class="report-choice-panel-copy">Choose the report you want to review, customize, print, or download.</p>
          </div>
          <a class="report-choice-settings" href="<?= htmlspecialchars($settingsUrl, ENT_QUOTES, 'UTF-8') ?>">
            <i class="fas fa-gear" aria-hidden="true"></i><span>Settings</span>
          </a>
        </div>
        <div class="report-choice-grid" aria-label="<?= htmlspecialchars($meta['title']) ?> report types">
        <?php foreach ($choices as $choice): ?>
          <a class="report-choice-card" href="<?= htmlspecialchars($reportsBaseUrl . '?' . http_build_query($choice['query']), ENT_QUOTES, 'UTF-8') ?>">
            <span class="report-choice-card-icon"><i class="fas <?= htmlspecialchars($choice['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i></span>
            <span class="report-choice-card-copy">
              <h2><?= htmlspecialchars($choice['title']) ?></h2>
              <p><?= htmlspecialchars($choice['description']) ?></p>
            </span>
          </a>
        <?php endforeach; ?>
        </div>
      </section>
    </div>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
