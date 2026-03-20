<?php
require_once __DIR__ . '/../../PhpFiles/General/connection.php';
require_once __DIR__ . '/../includes/admin_guard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="icon" href="../../Images/favicon_sanjose.png?v=20260211">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Area Statistics Summary</title>

  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AreaManagementStyle.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <main class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light" id="main-display" data-endpoint="../../PhpFiles/Admin-End/areaStatisticsData.php" data-page-scope="barangay">
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
      Area Statistics Summary
    </h2>
    <hr><br>

    <section class="area-filter-panel">
      <div class="area-filter-head">
        <div>
          <h2 class="stats-title m-0">Filters</h2>
          <p class="chart-copy mb-0">Set the module, time window, and status scope for the barangay summary, then apply the filters.</p>
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
        <div class="col-12 col-md-6 col-xl-2">
          <label class="form-label area-filter-label" for="dateFrom">Date From</label>
          <input type="date" class="form-control area-filter-input" id="dateFrom">
        </div>
        <div class="col-12 col-md-6 col-xl-2">
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
        <div class="col-12 col-md-6 col-xl-2">
          <label class="form-label area-filter-label" for="summaryScope">Scope</label>
          <input type="text" class="form-control area-filter-input" id="summaryScope" value="Barangay-wide" readonly>
        </div>
      </div>
    </section>

    <section class="area-section-header">
      <div>
        <h2 class="analytics-title mb-1">Summary</h2>
        <p class="analytics-copy mb-0">Barangay-wide overview across all tracked modules.</p>
      </div>
      <div class="area-section-tools">
        <span class="chart-total area-scope-badge">All Areas</span>
      </div>
    </section>

    <section class="area-dashboard-grid area-dashboard-grid--cards mb-4">
      <div class="area-grid-item area-grid-item--card" data-widget="summary-population-card" data-widget-label="Population Card">
        <div class="stats-card area-stat-card">
          <div class="stats-icon bg-sky"><i class="fa-solid fa-users"></i></div>
          <div>
            <div class="stats-label">Population</div>
            <div class="stats-value" data-stat="population">0</div>
            <div class="area-stat-meta">Residents in scope</div>
          </div>
        </div>
      </div>
      <div class="area-grid-item area-grid-item--card" data-widget="summary-households-card" data-widget-label="Households Card">
        <div class="stats-card area-stat-card">
          <div class="stats-icon bg-emerald"><i class="fa-solid fa-house-user"></i></div>
          <div>
            <div class="stats-label">Households</div>
            <div class="stats-value" data-stat="households">0</div>
            <div class="area-stat-meta">Head-of-family records</div>
          </div>
        </div>
      </div>
      <div class="area-grid-item area-grid-item--card" data-widget="summary-documents-card" data-widget-label="Issued Documents Card">
        <div class="stats-card area-stat-card">
          <div class="stats-icon bg-amber"><i class="fa-solid fa-file-circle-check"></i></div>
          <div>
            <div class="stats-label">Issued Documents</div>
            <div class="stats-value" data-stat="documents">0</div>
            <div class="area-stat-meta">Within selected date range</div>
          </div>
        </div>
      </div>
      <div class="area-grid-item area-grid-item--card" data-widget="summary-cases-card" data-widget-label="Cases Logged Card">
        <div class="stats-card area-stat-card">
          <div class="stats-icon bg-rose"><i class="fa-solid fa-scale-balanced"></i></div>
          <div>
            <div class="stats-label">Cases Logged</div>
            <div class="stats-value" data-stat="cases">0</div>
            <div class="area-stat-meta">Blotter and complaints</div>
          </div>
        </div>
      </div>
    </section>

    <section class="area-dashboard-grid area-dashboard-grid--content">
      <div class="area-grid-item area-grid-item--wide" data-widget="summary-module-chart" data-widget-label="Module Activity Snapshot">
        <article class="chart-panel area-chart-panel h-100">
          <div class="chart-panel-head">
            <div>
              <h3 class="chart-title">Module Activity Snapshot</h3>
              <p class="chart-copy mb-0">Quick comparison of activity across the major modules inside the selected scope.</p>
            </div>
            <span class="chart-total">Live module split</span>
          </div>
          <div class="chart-canvas-wrap chart-canvas-wrap-wide">
            <canvas class="area-module-chart" aria-label="Area module activity chart"></canvas>
          </div>
        </article>
      </div>

      <div class="area-grid-item" data-widget="summary-demographic-chart" data-widget-label="Population Breakdown">
        <article class="chart-panel area-chart-panel h-100">
          <div class="chart-panel-head">
            <div>
              <h3 class="chart-title">Population Breakdown</h3>
              <p class="chart-copy mb-0">Visual demographic split for the current area selection.</p>
            </div>
            <span class="chart-total">Demographics</span>
          </div>
          <div class="chart-canvas-wrap area-chart-canvas-wrap--donut">
            <canvas class="area-demographic-chart" aria-label="Area demographic chart"></canvas>
          </div>
        </article>
      </div>

      <div class="area-grid-item area-grid-item--wide" data-widget="summary-trend-chart" data-widget-label="Monthly Trend">
        <article class="chart-panel area-chart-panel h-100">
          <div class="chart-panel-head">
            <div>
              <h3 class="chart-title">Monthly Trend</h3>
              <p class="chart-copy mb-0">Monthly activity across all modules inside the selected date range.</p>
            </div>
            <span class="chart-total">Trend view</span>
          </div>
          <div class="chart-canvas-wrap chart-canvas-wrap-wide">
            <canvas class="area-trend-chart" aria-label="Area trend chart"></canvas>
          </div>
        </article>
      </div>

      <div class="area-grid-item" data-widget="summary-highlights" data-widget-label="Top Stats">
        <article class="chart-panel area-chart-panel h-100">
          <div class="chart-panel-head">
            <div>
              <h3 class="chart-title">Top Stats</h3>
              <p class="chart-copy mb-0">Fast-read indicators for admins who want to inspect current pressure points.</p>
            </div>
            <span class="chart-total">Highlights</span>
          </div>
          <div class="area-highlight-list"></div>
        </article>
      </div>

      <div class="area-grid-item area-grid-item--full" data-widget="summary-table" data-widget-label="Module Summary Table">
        <article class="chart-panel area-chart-panel">
          <div class="chart-panel-head">
            <div>
              <h3 class="chart-title">Module Summary Table</h3>
              <p class="chart-copy mb-0">Barangay-wide filtered module breakdown.</p>
            </div>
            <span class="chart-total">Live data</span>
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
<script src="../../JS-Script-Files/Admin-End/areaStatistics.js"></script>
</body>
</html>
