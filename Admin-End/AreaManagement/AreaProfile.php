<?php
require_once __DIR__ . '/../../PhpFiles/General/connection.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$requestedArea = trim((string)($_GET['area'] ?? 'Area 01'));
$allowedAreas = ['Area 01', 'Area 1A', 'Area 02', 'Area 03', 'Area 04', 'Area 05', 'Area 06'];
if (!in_array($requestedArea, $allowedAreas, true)) {
    $requestedArea = 'Area 01';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="icon" href="../../Images/favicon_sanjose.png?v=20260211">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($requestedArea) ?> Statistics</title>

  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AreaManagementStyle.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <main class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light" id="main-display" data-endpoint="../../PhpFiles/Admin-End/areaStatisticsData.php" data-page-scope="<?= htmlspecialchars($requestedArea) ?>">
    <div class="area-loading-overlay" id="areaLoadingOverlay" aria-hidden="true">
      <div class="area-loading-card" role="status" aria-live="polite">
        <div class="spinner-border area-loading-spinner" aria-hidden="true"></div>
        <div>
          <strong class="area-loading-title">Loading statistics</strong>
          <p class="area-loading-copy mb-0">Applying filters and refreshing the dashboard.</p>
        </div>
      </div>
    </div>
    <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C;">
      <?= htmlspecialchars($requestedArea) ?> Statistics
    </h2>
    <hr><br>

    <section class="area-filter-panel">
      <div class="area-filter-head">
        <div>
          <h2 class="stats-title m-0">Filters</h2>
          <p class="chart-copy mb-0">Set the module, time window, and status scope for <?= htmlspecialchars($requestedArea) ?>, then apply the filters.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button type="button" class="btn btn-primary area-apply-btn" id="btnApplyAreaFilters">Apply Filters</button>
          <button type="button" class="btn btn-outline-primary btn-area-customize" data-bs-toggle="modal" data-bs-target="#widgetCustomizerModal">Customize Widgets</button>
          <button type="button" class="btn btn-outline-secondary area-reset-btn">Reset</button>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12 col-md-6 col-xl-4">
          <label class="form-label area-filter-label" for="moduleSelect">Module</label>
          <select class="form-select area-filter-input" id="moduleSelect">
            <option value="all">All Modules</option>
            <option value="residents">Population and Demographics</option>
            <option value="documents">Document Issuance</option>
            <option value="blotter">Blotter</option>
            <option value="complaints">Complaints</option>
            <option value="appointments">Appointments</option>
          </select>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <label class="form-label area-filter-label" for="dateFrom">Date From</label>
          <input type="date" class="form-control area-filter-input" id="dateFrom">
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <label class="form-label area-filter-label" for="dateTo">Date To</label>
          <input type="date" class="form-control area-filter-input" id="dateTo">
        </div>
        <div class="col-12 col-md-6 col-xl-2">
          <label class="form-label area-filter-label" for="statusSelect">Status</label>
          <select class="form-select area-filter-input" id="statusSelect">
            <option value="all">All Statuses</option>
            <option value="active">Active</option>
            <option value="pending">Pending</option>
            <option value="resolved">Resolved / Completed</option>
          </select>
        </div>
      </div>
    </section>

    <section class="area-spotlight">
      <div class="area-spotlight-copy">
        <p class="area-eyebrow mb-2">Area Profile</p>
        <h2 class="area-spotlight-title mb-2"><?= htmlspecialchars($requestedArea) ?></h2>
        <p class="analytics-copy mb-0">Single-area operational view separated from the barangay summary page.</p>
      </div>
      <div class="area-spotlight-metrics">
        <div class="area-mini-stat">
          <span class="area-mini-label">Population</span>
          <strong class="area-mini-value" data-stat="population">0</strong>
        </div>
        <div class="area-mini-stat">
          <span class="area-mini-label">Households</span>
          <strong class="area-mini-value" data-stat="households">0</strong>
        </div>
        <div class="area-mini-stat">
          <span class="area-mini-label">Documents</span>
          <strong class="area-mini-value" data-stat="documents">0</strong>
        </div>
        <div class="area-mini-stat">
          <span class="area-mini-label">Cases</span>
          <strong class="area-mini-value" data-stat="cases">0</strong>
        </div>
      </div>
    </section>

    <section class="area-dashboard-grid area-dashboard-grid--content">
      <div class="area-grid-item area-grid-item--wide" data-widget="profile-module-chart" data-widget-label="Area Activity Mix">
        <article class="chart-panel area-profile-panel area-profile-panel--hero h-100">
          <div class="chart-panel-head">
            <div>
              <h3 class="chart-title">Area Activity Mix</h3>
              <p class="chart-copy mb-0">Module comparison for this area only.</p>
            </div>
            <span class="chart-total"><?= htmlspecialchars($requestedArea) ?></span>
          </div>
          <div class="chart-canvas-wrap chart-canvas-wrap-wide">
            <canvas class="area-module-chart" aria-label="Area module activity chart"></canvas>
          </div>
        </article>
      </div>

      <div class="area-grid-item" data-widget="profile-highlights" data-widget-label="Area Highlights">
        <article class="chart-panel area-profile-panel h-100">
          <div class="chart-panel-head">
            <div>
              <h3 class="chart-title">Area Highlights</h3>
              <p class="chart-copy mb-0">Compact reading for the current area.</p>
            </div>
            <span class="chart-total">Profile</span>
          </div>
          <div class="area-highlight-list"></div>
        </article>
      </div>

      <div class="area-grid-item" data-widget="profile-demographic-chart" data-widget-label="Demographic Profile">
        <article class="chart-panel area-profile-panel h-100">
          <div class="chart-panel-head">
            <div>
              <h3 class="chart-title">Demographic Profile</h3>
              <p class="chart-copy mb-0">Population composition for this specific area.</p>
            </div>
            <span class="chart-total">Demographics</span>
          </div>
          <div class="chart-canvas-wrap area-chart-canvas-wrap--donut">
            <canvas class="area-demographic-chart" aria-label="Area demographic chart"></canvas>
          </div>
        </article>
      </div>

      <div class="area-grid-item" data-widget="profile-trend-chart" data-widget-label="Trendline">
        <article class="chart-panel area-profile-panel h-100">
          <div class="chart-panel-head">
            <div>
              <h3 class="chart-title">Trendline</h3>
              <p class="chart-copy mb-0">Monthly activity curve for this area only.</p>
            </div>
            <span class="chart-total">Local trend</span>
          </div>
          <div class="chart-canvas-wrap">
            <canvas class="area-trend-chart" aria-label="Area trend chart"></canvas>
          </div>
        </article>
      </div>

      <div class="area-grid-item area-grid-item--full" data-widget="profile-table" data-widget-label="Operational Breakdown">
        <article class="chart-panel area-profile-panel area-profile-panel--table">
          <div class="chart-panel-head">
            <div>
              <h3 class="chart-title">Operational Breakdown</h3>
              <p class="chart-copy mb-0">Module totals and status distribution for <?= htmlspecialchars($requestedArea) ?>.</p>
            </div>
            <span class="chart-total">Area detail</span>
          </div>
          <div class="table-responsive">
            <table class="table align-middle area-summary-table mb-0">
              <thead>
                <tr>
                  <th>Module</th>
                  <th>Total</th>
                  <th>Active / Pending</th>
                  <th>Completed / Resolved</th>
                  <th>Notes</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="5" class="text-center text-muted py-4">Loading statistics...</td>
                </tr>
              </tbody>
            </table>
          </div>
        </article>
      </div>
    </section>
  </main>
</div>

<div class="modal fade" id="widgetCustomizerModal" tabindex="-1" aria-labelledby="widgetCustomizerLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content area-widget-modal">
      <div class="modal-header">
        <h5 class="modal-title" id="widgetCustomizerLabel">Customize Widgets</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="chart-copy mb-3">Choose which widgets should stay visible on this page.</p>
        <div id="widgetCustomizerList" class="area-widget-list"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" id="btnResetWidgetLayout">Reset Layout</button>
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../JS-Script-Files/Resident-End/dateFieldModal.js?v=20260707-date-proxy-white"></script>
<script src="../../JS-Script-Files/Admin-End/areaStatistics.js"></script>
</body>
</html>
