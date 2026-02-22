<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" href="/Images/favicon_sanjose.png?v=20260211">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Officials Management</title>

  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260222-1">
</head>
<body>
  <div class="d-flex" style="min-height: 100vh;">
    <?php
      require_once "../PhpFiles/General/connection.php";
      require_once "includes/admin_guard.php";
      requireRoleSession(['SuperAdmin'], false);
      include "includes/sidebar.php";
    ?>

    <main class="flex-grow-1 p-4 p-md-5 bg-light" id="main-display">
      <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C; font-size: 48px;">
        Officials Management
      </h2>
      <hr><br>

      <div class="bg-white p-4 rounded-4 shadow-sm border resident-masterlist-shell">
        <div class="admin-list-toolbar mb-3">
          <div class="admin-list-tabs">
            <button class="btn btn-outline-primary btn-sm status-filter-btn active" data-filter="ALL">All</button>
            <button class="btn btn-outline-secondary btn-sm status-filter-btn" data-filter="SuperAdmin">SuperAdmin</button>
            <button class="btn btn-outline-secondary btn-sm status-filter-btn" data-filter="Official">Official</button>
            <button class="btn btn-outline-secondary btn-sm status-filter-btn" data-filter="Personnel">Personnel</button>
            <button class="btn btn-outline-secondary btn-sm status-filter-btn" data-permission-filter="Active">Active</button>
            <button class="btn btn-outline-secondary btn-sm status-filter-btn has-notif" data-permission-filter="Revoked">
              Revoked
              <span id="revokedOfficialsBadge" class="pending-count-badge d-none">0</span>
            </button>
          </div>
          <div class="admin-list-actions d-flex flex-row flex-nowrap align-items-center gap-2 ms-auto">
            <div class="input-group admin-search">
              <input id="officialsMgmtSearch" class="form-control" placeholder="Search official ID / user ID / name / department" />
              <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
            </div>
            <button id="btnOfficialsMgmtRefresh" class="btn btn-outline-secondary btn-icon" type="button" title="Refresh table" aria-label="Refresh table">
              <i class="fa-solid fa-arrows-rotate"></i>
              <span class="visually-hidden">Refresh</span>
            </button>
            <span id="officialsMgmtAutoRefreshCountdown" class="small text-muted d-none"></span>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Official ID</th>
                <th>User ID</th>
                <th>Name</th>
                <th>Role</th>
                <th>Position Access</th>
                <th>Department</th>
                <th>Employment Status</th>
                <th>Date Hired</th>
                <th>Account Status</th>
                <th>Permissions</th>
                <th>Profile Approval</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="officialsMgmtTbody">
              <tr><td colspan="12" class="text-center text-muted py-4">Loading...</td></tr>
            </tbody>
          </table>
        </div>

        <div class="resident-table-footer mt-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
          <div class="d-flex align-items-center gap-2">
            <label for="officialsMgmtEntriesInput" class="small text-muted mb-0">Entries</label>
            <input id="officialsMgmtEntriesInput" type="number" min="1" step="1" value="20" class="form-control form-control-sm resident-entries-input" />
          </div>
          <nav aria-label="Officials management pagination">
            <ul class="pagination pagination-sm mb-0" id="officialsMgmtPagination"></ul>
          </nav>
        </div>
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../JS-Script-Files/Admin-End/officialsManagementScript.js?v=20260222-2"></script>
</body>
</html>
