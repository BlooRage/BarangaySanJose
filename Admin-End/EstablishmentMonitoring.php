<?php
require_once __DIR__ . '/../PhpFiles/General/connection.php';
require_once __DIR__ . '/includes/admin_guard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Establishment Monitoring</title>
  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260227-2">
  <style>
    .establishment-monitoring-shell{max-width:1540px;margin:0 auto}.establishment-name{font-weight:700;color:#1f2937}
  </style>
</head>
<body>
<div class="d-flex flex-column flex-md-row" style="min-height:100vh">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
    <h2 class="mb-4" style="font-family:'Charis SIL Bold';color:#DE710C">Establishment Monitoring</h2>
    <hr><br>
    <div class="bg-white p-4 rounded-4 shadow-sm border establishment-monitoring-shell resident-masterlist-shell">
      <div class="admin-list-toolbar mb-3 pt-2 flex-wrap">
        <div class="fw-semibold">Commercial Establishments</div>
        <div class="admin-list-actions">
          <div class="input-group admin-search"><input type="text" id="establishmentSearch" class="form-control" placeholder="Establishment, owner, address"><span class="input-group-text bg-white"><i class="fas fa-search"></i></span></div>
          <button class="btn btn-outline-secondary btn-icon" type="button" id="establishmentRefresh" title="Refresh"><i class="fa-solid fa-arrows-rotate"></i></button>
        </div>
      </div>
      <div class="table-responsive compact-admin-table-shell">
        <table class="table align-middle compact-admin-table compact-admin-table--wide mb-0">
          <thead><tr class="table-light"><th>Establishment</th><th>Owner / Applicant</th><th>Area</th><th>Commercial Address</th><th>Permit Request</th><th>Completed Date</th></tr></thead>
          <tbody id="establishmentMonitoringTbody"><tr><td colspan="6" class="text-center text-muted py-4">Loading commercial establishments…</td></tr></tbody>
        </table>
      </div>
      <div class="resident-table-footer mt-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-2"><label for="establishmentEntries" class="small text-muted mb-0">Entries</label><input id="establishmentEntries" type="number" min="1" value="20" class="form-control form-control-sm resident-entries-input"></div>
        <nav aria-label="Establishment pagination"><ul class="pagination pagination-sm mb-0" id="establishmentPagination"></ul></nav>
      </div>
    </div>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../JS-Script-Files/Admin-End/establishmentMonitoring.js?v=20260801-2"></script>
</body>
</html>
