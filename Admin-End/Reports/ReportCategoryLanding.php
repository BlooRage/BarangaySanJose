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
$choices[] = [
    'title' => 'Report Signatory Settings',
    'description' => 'Set the names and positions of the two signatories shown on generated reports for this category.',
    'icon' => 'fa-signature',
    'query' => ['module' => $module, 'report' => 'signatory_settings'],
];

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
    .report-choice-panel-head { padding-bottom: .75rem; margin-bottom: .75rem; border-bottom: 1px solid #edf0f3; }
    .report-choice-heading { margin: 0; color: #212529; font-size: 1.05rem; font-weight: 700; }
    .report-choice-panel-copy { max-width: 760px; margin: .2rem 0 0; color: #6c757d; font-size: .9rem; }
    .report-choice-grid {
      display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .6rem;
    }
    .report-choice-card {
      display: grid; grid-template-columns: 2.6rem minmax(0, 1fr) auto; align-items: center;
      gap: .9rem; min-width: 0; padding: .78rem 1rem; color: inherit; text-decoration: none;
      border: 1px solid #e4e8ed; border-radius: .75rem; background: #fff;
      transition: border-color .15s, box-shadow .15s, background-color .15s;
    }
    .report-choice-card:hover, .report-choice-card:focus-visible {
      color: inherit; border-color: #f1b56d; background: #fffaf4;
      box-shadow: 0 .25rem .75rem rgba(222,113,12,.08);
    }
    .report-choice-card-icon { display: inline-grid; width: 2.6rem; height: 2.6rem; place-items: center; border-radius: .7rem; color: #de710c; background: #fff4e8; }
    .report-choice-card-copy { min-width: 0; }
    .report-choice-card h2 { margin: 0; color: #212529; font-size: .95rem; font-weight: 700; }
    .report-choice-card p { margin: .12rem 0 0; color: #6c757d; font-size: .9rem; line-height: 1.3; }
    .report-choice-arrow { color: #de710c; }
    @media (max-width: 767.98px) { .report-choice-grid { grid-template-columns: 1fr; } }
    @media (max-width: 575.98px) {
      .report-choice-panel { padding: .85rem; }
      .report-choice-card { grid-template-columns: 2.4rem minmax(0,1fr) auto; padding: .85rem; gap: .7rem; }
      .report-choice-card-icon { width: 2.4rem; height: 2.4rem; }
    }
  </style>
</head>
<body>
<div class="d-flex flex-column flex-md-row" style="min-height:100vh;">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <main id="main-display" class="report-choice-main flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
    <div class="report-choice-page">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C;"><?= htmlspecialchars($meta['title']) ?> Reports</h2>
        <a class="report-choice-back" href="<?= htmlspecialchars($reportsBaseUrl, ENT_QUOTES, 'UTF-8') ?>">
          <i class="fas fa-arrow-left" aria-hidden="true"></i>All report categories
        </a>
      </div>
      <hr><br>
      <section class="report-choice-panel" aria-labelledby="availableReportsHeading">
        <div class="report-choice-panel-head">
          <h2 class="report-choice-heading" id="availableReportsHeading">Available Reports</h2>
          <p class="report-choice-panel-copy">Choose the report you want to review, customize, print, or download.</p>
        </div>
        <div class="report-choice-grid" aria-label="<?= htmlspecialchars($meta['title']) ?> report types">
        <?php foreach ($choices as $choice): ?>
          <a class="report-choice-card" href="<?= htmlspecialchars($reportsBaseUrl . '?' . http_build_query($choice['query']), ENT_QUOTES, 'UTF-8') ?>">
            <span class="report-choice-card-icon"><i class="fas <?= htmlspecialchars($choice['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i></span>
            <span class="report-choice-card-copy">
              <h2><?= htmlspecialchars($choice['title']) ?></h2>
              <p><?= htmlspecialchars($choice['description']) ?></p>
            </span>
            <i class="fas fa-arrow-right report-choice-arrow" aria-hidden="true"></i>
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
