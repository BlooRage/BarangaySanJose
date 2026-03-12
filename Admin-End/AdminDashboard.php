<?php
require_once __DIR__ . "/includes/admin_guard.php";
require_once "../PhpFiles/General/connection.php";

function tableExists(mysqli $conn, string $tableName): bool {
  $escaped = $conn->real_escape_string($tableName);
  $result = $conn->query("SHOW TABLES LIKE '{$escaped}'");
  if (!$result) {
    return false;
  }

  $exists = $result->num_rows > 0;
  $result->free();
  return $exists;
}

$stats = [
  'total' => 0,
  'verified' => 0,
  'pending' => 0,
  'denied' => 0
];

$verifiedResidentCondition = "s.status_name = 'VerifiedResident'";

$statsQuery = "
  SELECT
    SUM(CASE WHEN s.status_name = 'PendingVerification' THEN 1 ELSE 0 END) AS pending_count,
    SUM(CASE WHEN s.status_name = 'VerifiedResident' THEN 1 ELSE 0 END) AS verified_count,
    SUM(CASE WHEN s.status_name = 'NotVerified' THEN 1 ELSE 0 END) AS denied_count,
    SUM(CASE WHEN s.status_name <> 'Archived' OR s.status_name IS NULL THEN 1 ELSE 0 END) AS total_count
  FROM residentinformationtbl r
  LEFT JOIN statuslookuptbl s ON r.status_id_resident = s.status_id
";

if ($result = $conn->query($statsQuery)) {
  if ($row = $result->fetch_assoc()) {
    $stats['pending'] = (int)($row['pending_count'] ?? 0);
    $stats['verified'] = (int)($row['verified_count'] ?? 0);
    $stats['denied'] = (int)($row['denied_count'] ?? 0);
    $stats['total'] = (int)($row['total_count'] ?? 0);
  }
  $result->free();
}

$sectorCounts = [
  'PWD' => 0,
  'Senior Citizen' => 0,
  'Student' => 0,
  'Indigenous People' => 0,
  'Single Parent' => 0
];

$sectorQuery = "
  SELECT r.sector_membership
  FROM residentinformationtbl r
  LEFT JOIN statuslookuptbl s ON r.status_id_resident = s.status_id
  WHERE {$verifiedResidentCondition}
";

if ($result = $conn->query($sectorQuery)) {
  while ($row = $result->fetch_assoc()) {
    $memberships = array_filter(array_map('trim', explode(',', (string)($row['sector_membership'] ?? ''))));
    foreach ($memberships as $membership) {
      if (array_key_exists($membership, $sectorCounts)) {
        $sectorCounts[$membership]++;
      }
    }
  }
  $result->free();
}

$genderCounts = [
  'Male' => 0,
  'Female' => 0,
  'Unspecified' => 0
];

$genderQuery = "
  SELECT
    SUM(CASE WHEN r.sex = 'Male' THEN 1 ELSE 0 END) AS male_count,
    SUM(CASE WHEN r.sex = 'Female' THEN 1 ELSE 0 END) AS female_count,
    SUM(CASE WHEN r.sex IS NULL OR TRIM(r.sex) = '' OR r.sex NOT IN ('Male', 'Female') THEN 1 ELSE 0 END) AS unspecified_count
  FROM residentinformationtbl r
  LEFT JOIN statuslookuptbl s ON r.status_id_resident = s.status_id
  WHERE {$verifiedResidentCondition}
";

if ($result = $conn->query($genderQuery)) {
  if ($row = $result->fetch_assoc()) {
    $genderCounts['Male'] = (int)($row['male_count'] ?? 0);
    $genderCounts['Female'] = (int)($row['female_count'] ?? 0);
    $genderCounts['Unspecified'] = (int)($row['unspecified_count'] ?? 0);
  }
  $result->free();
}

$householdAreaLabels = [];
$householdAreaValues = [];

$householdAreaQuery = "
  SELECT
    COALESCE(NULLIF(TRIM(a.area_number), ''), 'Unspecified Area') AS area_label,
    COUNT(*) AS household_count
  FROM residentinformationtbl r
  LEFT JOIN statuslookuptbl s ON r.status_id_resident = s.status_id
  LEFT JOIN residentaddresstbl a ON a.address_id = (
    SELECT a2.address_id
    FROM residentaddresstbl a2
    WHERE a2.resident_id = r.resident_id
    ORDER BY a2.address_id DESC
    LIMIT 1
  )
  WHERE r.head_of_family = 1
    AND {$verifiedResidentCondition}
  GROUP BY area_label
  ORDER BY area_label ASC
";

if ($result = $conn->query($householdAreaQuery)) {
  while ($row = $result->fetch_assoc()) {
    $householdAreaLabels[] = (string)($row['area_label'] ?? 'Unspecified Area');
    $householdAreaValues[] = (int)($row['household_count'] ?? 0);
  }
  $result->free();
}

$ageDistribution = [
  '0-17' => 0,
  '18-30' => 0,
  '31-59' => 0,
  '60+' => 0,
  'Unknown' => 0
];

$ageQuery = "
  SELECT r.birthdate
  FROM residentinformationtbl r
  LEFT JOIN statuslookuptbl s ON r.status_id_resident = s.status_id
  WHERE {$verifiedResidentCondition}
";

if ($result = $conn->query($ageQuery)) {
  $today = new DateTimeImmutable('today');
  while ($row = $result->fetch_assoc()) {
    $birthdateRaw = trim((string)($row['birthdate'] ?? ''));
    if ($birthdateRaw === '' || $birthdateRaw === '0000-00-00') {
      $ageDistribution['Unknown']++;
      continue;
    }

    try {
      $birthdate = new DateTimeImmutable($birthdateRaw);
      $age = $today->diff($birthdate)->y;
    } catch (Exception $e) {
      $ageDistribution['Unknown']++;
      continue;
    }

    if ($age < 18) {
      $ageDistribution['0-17']++;
    } elseif ($age < 31) {
      $ageDistribution['18-30']++;
    } elseif ($age < 60) {
      $ageDistribution['31-59']++;
    } else {
      $ageDistribution['60+']++;
    }
  }
  $result->free();
}

$residentAreaLabels = [];
$residentAreaValues = [];

$residentAreaQuery = "
  SELECT
    COALESCE(NULLIF(TRIM(a.area_number), ''), 'Unspecified Area') AS area_label,
    COUNT(*) AS resident_count
  FROM residentinformationtbl r
  LEFT JOIN statuslookuptbl s ON r.status_id_resident = s.status_id
  LEFT JOIN residentaddresstbl a ON a.address_id = (
    SELECT a2.address_id
    FROM residentaddresstbl a2
    WHERE a2.resident_id = r.resident_id
    ORDER BY a2.address_id DESC
    LIMIT 1
  )
  WHERE {$verifiedResidentCondition}
  GROUP BY area_label
  ORDER BY area_label ASC
";

if ($result = $conn->query($residentAreaQuery)) {
  while ($row = $result->fetch_assoc()) {
    $residentAreaLabels[] = (string)($row['area_label'] ?? 'Unspecified Area');
    $residentAreaValues[] = (int)($row['resident_count'] ?? 0);
  }
  $result->free();
}

$caseTrendLabels = [];
$complaintTrend = [];
$blotterTrend = [];

for ($i = 5; $i >= 0; $i--) {
  $monthKey = date('Y-m', strtotime("-{$i} month"));
  $caseTrendLabels[] = date('M Y', strtotime($monthKey . '-01'));
  $complaintTrend[$monthKey] = 0;
  $blotterTrend[$monthKey] = 0;
}

if (tableExists($conn, 'complaintstbl')) {
  $complaintTrendQuery = "
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key, COUNT(*) AS total_count
    FROM complaintstbl
    WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month_key ASC
  ";

  if ($result = $conn->query($complaintTrendQuery)) {
    while ($row = $result->fetch_assoc()) {
      $monthKey = (string)($row['month_key'] ?? '');
      if (array_key_exists($monthKey, $complaintTrend)) {
        $complaintTrend[$monthKey] = (int)($row['total_count'] ?? 0);
      }
    }
    $result->free();
  }
}

if (tableExists($conn, 'barangayblottertbl')) {
  $blotterTrendQuery = "
    SELECT DATE_FORMAT(date_filed, '%Y-%m') AS month_key, COUNT(*) AS total_count
    FROM barangayblottertbl
    WHERE date_filed >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
    GROUP BY DATE_FORMAT(date_filed, '%Y-%m')
    ORDER BY month_key ASC
  ";

  if ($result = $conn->query($blotterTrendQuery)) {
    while ($row = $result->fetch_assoc()) {
      $monthKey = (string)($row['month_key'] ?? '');
      if (array_key_exists($monthKey, $blotterTrend)) {
        $blotterTrend[$monthKey] = (int)($row['total_count'] ?? 0);
      }
    }
    $result->free();
  }
}

$dashboardCharts = [
  'sectorMembership' => [
    'labels' => array_keys($sectorCounts),
    'values' => array_values($sectorCounts)
  ],
  'genderPopulation' => [
    'labels' => array_keys($genderCounts),
    'values' => array_values($genderCounts)
  ],
  'householdsPerArea' => [
    'labels' => $householdAreaLabels,
    'values' => $householdAreaValues
  ],
  'ageDistribution' => [
    'labels' => array_keys($ageDistribution),
    'values' => array_values($ageDistribution)
  ],
  'residentsPerArea' => [
    'labels' => $residentAreaLabels,
    'values' => $residentAreaValues
  ],
  'caseActivity' => [
    'labels' => $caseTrendLabels,
    'complaints' => array_values($complaintTrend),
    'blotters' => array_values($blotterTrend)
  ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" href="../Images/favicon_sanjose.png?v=20260211">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard</title>

  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
</head>

<body>
<div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
  <?php include 'includes/sidebar.php'; ?>

  <main class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light" id="main-display">
    <h1 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C; ">Admin Dashboard</h1>
    <hr><br>

    <section id="dashboard-stats" class="mb-4">
      <div class="stats-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
        <h2 class="stats-title m-0">Overview</h2>
        <span class="stats-subtitle">Snapshot as of <?php echo date('F j, Y'); ?></span>
      </div>
      <div class="row g-3 stats-grid">
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="stats-card">
            <div class="stats-icon bg-amber"><i class="fa-solid fa-file-lines"></i></div>
            <div>
              <div class="stats-label">Pending Verification</div>
              <div class="stats-value"><?php echo number_format($stats['pending']); ?></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="stats-card">
            <div class="stats-icon bg-emerald"><i class="fa-solid fa-clipboard-check"></i></div>
            <div>
              <div class="stats-label">Verified Residents</div>
              <div class="stats-value"><?php echo number_format($stats['verified']); ?></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="stats-card">
            <div class="stats-icon bg-sky"><i class="fa-solid fa-user-group"></i></div>
            <div>
              <div class="stats-label">Total Residents</div>
              <div class="stats-value"><?php echo number_format($stats['total']); ?></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="stats-card">
            <div class="stats-icon bg-rose"><i class="fa-solid fa-file-circle-xmark"></i></div>
            <div>
              <div class="stats-label">Not Verified</div>
              <div class="stats-value"><?php echo number_format($stats['denied']); ?></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="analytics-section">
      <div class="analytics-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2 mb-3">
        <div>
          <h2 class="analytics-title mb-1">Barangay Statistics</h2>
          <p class="analytics-copy mb-0">Live visual summary of resident demographics and case activity.</p>
        </div>
        <span class="analytics-range">Current resident and case records</span>
      </div>

      <div class="row g-4">
        <div class="col-12 col-md-6 col-xl-4">
          <article class="chart-panel h-100">
            <div class="chart-panel-head">
              <div>
                <h3 class="chart-title">Sector Membership</h3>
                <p class="chart-copy mb-0">Counts of residents per declared sector membership.</p>
              </div>
              <span class="chart-total"><?php echo number_format(array_sum($sectorCounts)); ?> records</span>
            </div>
            <div class="chart-canvas-wrap">
              <canvas id="sectorMembershipChart" aria-label="Sector membership chart"></canvas>
            </div>
          </article>
        </div>

        <div class="col-12 col-md-6 col-xl-4">
          <article class="chart-panel h-100">
            <div class="chart-panel-head">
              <div>
                <h3 class="chart-title">Gender Population</h3>
                <p class="chart-copy mb-0">Distribution of active resident records by sex.</p>
              </div>
              <span class="chart-total"><?php echo number_format(array_sum($genderCounts)); ?> residents</span>
            </div>
            <div class="chart-canvas-wrap">
              <canvas id="genderPopulationChart" aria-label="Gender population chart"></canvas>
            </div>
          </article>
        </div>

        <div class="col-12 col-md-6 col-xl-4">
          <article class="chart-panel h-100">
            <div class="chart-panel-head">
              <div>
                <h3 class="chart-title">Age Distribution</h3>
                <p class="chart-copy mb-0">Population grouped into broad age ranges.</p>
              </div>
              <span class="chart-total"><?php echo number_format(array_sum($ageDistribution)); ?> residents</span>
            </div>
            <div class="chart-canvas-wrap">
              <canvas id="ageDistributionChart" aria-label="Age distribution chart"></canvas>
            </div>
          </article>
        </div>

        <div class="col-12 col-xl-6">
          <article class="chart-panel h-100">
            <div class="chart-panel-head">
              <div>
                <h3 class="chart-title">Households Per Area</h3>
                <p class="chart-copy mb-0">Head-of-family households grouped by area number.</p>
              </div>
              <span class="chart-total"><?php echo number_format(array_sum($householdAreaValues)); ?> households</span>
            </div>
            <div class="chart-canvas-wrap chart-canvas-wrap-wide">
              <canvas id="householdsPerAreaChart" aria-label="Households per area chart"></canvas>
            </div>
          </article>
        </div>

        <div class="col-12 col-xl-6">
          <article class="chart-panel chart-panel-wide h-100">
            <div class="chart-panel-head">
              <div>
                <h3 class="chart-title">Residents Per Area</h3>
                <p class="chart-copy mb-0">Active residents grouped by their latest recorded area number.</p>
              </div>
              <span class="chart-total"><?php echo number_format(array_sum($residentAreaValues)); ?> residents</span>
            </div>
            <div class="chart-canvas-wrap chart-canvas-wrap-wide">
              <canvas id="residentsPerAreaChart" aria-label="Residents per area chart"></canvas>
            </div>
          </article>
        </div>

        <div class="col-12">
          <article class="chart-panel chart-panel-wide">
            <div class="chart-panel-head">
              <div>
                <h3 class="chart-title">Blotter and Complaints</h3>
                <p class="chart-copy mb-0">Monthly intake volume for the last six months.</p>
              </div>
              <span class="chart-total"><?php echo number_format(array_sum($complaintTrend) + array_sum($blotterTrend)); ?> total cases</span>
            </div>
            <div class="chart-canvas-wrap chart-canvas-wrap-wide">
              <canvas id="caseActivityChart" aria-label="Blotter and complaints chart"></canvas>
            </div>
          </article>
        </div>
      </div>
    </section>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.body.classList.add('dashboard-preload');
  window.addEventListener('load', () => {
    window.requestAnimationFrame(() => {
      document.body.classList.add('dashboard-loaded');
    });
  });

  const dashboardCharts = <?php echo json_encode($dashboardCharts, JSON_UNESCAPED_SLASHES); ?>;
  const chartPalette = {
    orange: '#de710c',
    amber: '#f59f00',
    sky: '#1c7ed6',
    emerald: '#2f9e44',
    rose: '#e03131',
    slate: '#6c757d',
    grid: 'rgba(32, 35, 41, 0.08)'
  };

  Chart.defaults.font.family = "'Geist', sans-serif";
  Chart.defaults.color = '#495057';

  new Chart(document.getElementById('sectorMembershipChart'), {
    type: 'bar',
    data: {
      labels: dashboardCharts.sectorMembership.labels,
      datasets: [{
        label: 'Residents',
        data: dashboardCharts.sectorMembership.values,
        backgroundColor: ['#de710c', '#f59f00', '#f08c00', '#fd7e14', '#ff922b'],
        borderRadius: 10,
        borderSkipped: false
      }]
    },
    options: {
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false }
      },
      scales: {
        x: {
          grid: { display: false }
        },
        y: {
          beginAtZero: true,
          ticks: { precision: 0 },
          grid: { color: chartPalette.grid }
        }
      }
    }
  });

  new Chart(document.getElementById('genderPopulationChart'), {
    type: 'doughnut',
    data: {
      labels: dashboardCharts.genderPopulation.labels,
      datasets: [{
        data: dashboardCharts.genderPopulation.values,
        backgroundColor: [chartPalette.sky, chartPalette.rose, chartPalette.slate],
        borderColor: '#ffffff',
        borderWidth: 4,
        hoverOffset: 6
      }]
    },
    options: {
      maintainAspectRatio: false,
      cutout: '62%',
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            usePointStyle: true,
            padding: 18
          }
        }
      }
    }
  });

  new Chart(document.getElementById('householdsPerAreaChart'), {
    type: 'bar',
    data: {
      labels: dashboardCharts.householdsPerArea.labels,
      datasets: [{
        label: 'Households',
        data: dashboardCharts.householdsPerArea.values,
        backgroundColor: 'rgba(47, 158, 68, 0.82)',
        borderRadius: 10,
        borderSkipped: false
      }]
    },
    options: {
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false }
      },
      scales: {
        x: {
          grid: { display: false }
        },
        y: {
          beginAtZero: true,
          ticks: { precision: 0 },
          grid: { color: chartPalette.grid }
        }
      }
    }
  });

  new Chart(document.getElementById('ageDistributionChart'), {
    type: 'bar',
    data: {
      labels: dashboardCharts.ageDistribution.labels,
      datasets: [{
        label: 'Residents',
        data: dashboardCharts.ageDistribution.values,
        backgroundColor: ['#ffd166', '#f59f00', '#de710c', '#9c6644', '#adb5bd'],
        borderRadius: 10,
        borderSkipped: false
      }]
    },
    options: {
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false }
      },
      scales: {
        x: {
          grid: { display: false }
        },
        y: {
          beginAtZero: true,
          ticks: { precision: 0 },
          grid: { color: chartPalette.grid }
        }
      }
    }
  });

  new Chart(document.getElementById('residentsPerAreaChart'), {
    type: 'bar',
    data: {
      labels: dashboardCharts.residentsPerArea.labels,
      datasets: [{
        label: 'Residents',
        data: dashboardCharts.residentsPerArea.values,
        backgroundColor: 'rgba(28, 126, 214, 0.82)',
        borderRadius: 10,
        borderSkipped: false
      }]
    },
    options: {
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false }
      },
      scales: {
        x: {
          grid: { display: false }
        },
        y: {
          beginAtZero: true,
          ticks: { precision: 0 },
          grid: { color: chartPalette.grid }
        }
      }
    }
  });

  new Chart(document.getElementById('caseActivityChart'), {
    type: 'bar',
    data: {
      labels: dashboardCharts.caseActivity.labels,
      datasets: [
        {
          label: 'Complaints',
          data: dashboardCharts.caseActivity.complaints,
          backgroundColor: 'rgba(28, 126, 214, 0.85)',
          borderRadius: 10,
          borderSkipped: false
        },
        {
          label: 'Blotter',
          data: dashboardCharts.caseActivity.blotters,
          backgroundColor: 'rgba(222, 113, 12, 0.85)',
          borderRadius: 10,
          borderSkipped: false
        }
      ]
    },
    options: {
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            usePointStyle: true,
            padding: 18
          }
        }
      },
      scales: {
        x: {
          grid: { display: false }
        },
        y: {
          beginAtZero: true,
          ticks: { precision: 0 },
          grid: { color: chartPalette.grid }
        }
      }
    }
  });
</script>
</body>
</html>
