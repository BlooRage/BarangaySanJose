<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" href="/Images/favicon_sanjose.png?v=20260211">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Audit Logs</title>

  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260212-5">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AuditLogsStyle.css?v=20260216-2">
</head>
<body>
  <div class="d-flex" style="min-height: 100vh;">
    

    <main class="flex-grow-1 p-4 p-md-5 bg-light" id="main-display">
      <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C; font-size: 48px;">
        Audit Logs
      </h2>
      <hr><br>

      <div id="div-tableContainer" class="bg-white p-4 rounded-4 shadow-sm border audit-shell">
        <div class="admin-list-toolbar mb-3">
          <div class="admin-list-tabs">
            <div class="text-muted small">System activity trail (latest first)</div>
          </div>
          <div class="admin-list-actions d-flex flex-row flex-nowrap align-items-center gap-2">
            <div class="input-group admin-search audit-search flex-grow-1 me-2">
              <input id="auditSearch" class="form-control" placeholder="Search user/module/target/action..." />
              <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
            </div>
            <button id="btnAuditFilter" class="btn btn-outline-secondary btn-icon" type="button" title="Filter" aria-label="Filter" data-bs-toggle="modal" data-bs-target="#modalAuditFilter">
              <i class="fas fa-filter"></i>
              <span class="visually-hidden">Filter</span>
            </button>
            <button id="btnAuditColumns" class="btn audit-columns admin-columns btn-icon" type="button" title="Columns" aria-label="Columns" data-bs-toggle="modal" data-bs-target="#modalAuditColumns">
              <i class="fa-solid fa-sliders"></i>
              <span class="visually-hidden">Columns</span>
            </button>
            <button id="btnAuditRefresh" class="btn audit-refresh admin-refresh btn-icon" type="button" title="Refresh table" aria-label="Refresh table">
              <i class="fa-solid fa-arrows-rotate"></i>
              <span class="visually-hidden">Refresh</span>
            </button>
            <span id="auditAutoRefreshCountdown" class="small text-muted d-none"></span>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 audit-table">
            <thead class="table-light">
              <tr id="auditTheadRow"></tr>
            </thead>
            <tbody id="auditTbody">
              <tr><td colspan="6" class="text-center text-muted py-4">Loading...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>

  <!-- Columns Modal -->
  <div class="modal fade" id="modalAuditColumns" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Customize Table Columns</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="text-muted small mb-3">
            Choose which columns to show in the Audit Logs table.
          </div>
          <div id="auditColumnsList" class="row g-2"></div>
        </div>
        <div class="modal-footer d-flex justify-content-between">
          <button type="button" class="btn btn-outline-secondary" id="btnAuditColumnsReset">
            Reset Default
          </button>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Return</button>
            <button type="button" class="btn btn-primary" id="btnAuditColumnsApply">Apply</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filter Modal -->
  <div class="modal fade" id="modalAuditFilter" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Filter Audit Logs</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label small text-muted mb-1">Date From</label>
              <input type="date" class="form-control" id="auditFilterFrom" />
            </div>
            <div class="col-12">
              <label class="form-label small text-muted mb-1">Date To</label>
              <input type="date" class="form-control" id="auditFilterTo" />
            </div>
          </div>
          <div class="small text-muted mt-3">
            Filters are applied client-side on the loaded logs.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" id="btnAuditFilterReset">Reset</button>
          <button type="button" class="btn btn-primary" id="btnAuditFilterApply" data-bs-dismiss="modal">Apply</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../JS-Script-Files/Admin-End/auditLogsScript.js?v=20260215"></script>
</body>
</html>
