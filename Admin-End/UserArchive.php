<?php
require_once __DIR__ . "/includes/admin_guard.php";
requireRoleSession(['SuperAdmin'], false);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="icon" href="../Images/favicon_sanjose.png?v=20260211">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Archive</title>

  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260227-2">
  <style>
    #main-display {
      min-width: 0;
    }
    .user-archive-shell {
      width: 100%;
      max-width: 100%;
      overflow-x: hidden;
      overflow-y: visible;
      -webkit-overflow-scrolling: touch;
    }
    .user-archive-shell .table-responsive {
      overflow-x: auto;
      overflow-y: visible;
      -webkit-overflow-scrolling: touch;
    }
    .user-archive-table {
      min-width: 1080px;
    }
    .user-archive-table th:last-child,
    .user-archive-table td:last-child {
      min-width: 220px;
    }
    .user-archive-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
    }
  </style>
</head>
<body>
  <div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php
      include "includes/sidebar.php";
    ?>

    <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
      <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C; ">
        User Archive
      </h2>

      <hr><br>

      <div class="bg-white p-4 rounded-4 shadow-sm border user-archive-shell">
        <div class="admin-list-toolbar mb-3 pt-2 flex-wrap">
          <div class="admin-list-tabs"></div>

          <div class="admin-list-actions d-flex flex-row flex-nowrap align-items-center gap-2 ms-auto">
            <div class="input-group admin-search">
              <input type="text" id="userArchiveSearch" class="form-control" placeholder="Search user ID / name / email">
              <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
            </div>
            <select id="userArchiveRoleFilter" class="form-select" style="max-width: 190px;">
              <option value="ALL">All Roles</option>
              <option value="Resident">Resident</option>
              <option value="Official">Official</option>
              <option value="Personnel">Personnel</option>
              <option value="SuperAdmin">SuperAdmin</option>
            </select>
            <button class="btn btn-outline-secondary btn-icon admin-refresh" type="button" id="btnUserArchiveRefresh" title="Refresh table" aria-label="Refresh table">
              <i class="fa-solid fa-arrows-rotate"></i>
              <span class="visually-hidden">Refresh</span>
            </button>
            <a href="<?= htmlspecialchars(appUrl('Admin-End/UserMasterlist.php')) ?>" class="btn btn-outline-dark">
              Back to User Management
            </a>
          </div>
        </div>

        <div class="table-responsive compact-admin-table-shell">
          <table class="table table-hover align-middle mb-0 compact-admin-table compact-admin-table--wide user-archive-table">
            <thead class="table-light">
              <tr>
                <th>User ID</th>
                <th>Name</th>
                <th>Role</th>
                <th>Previous Status</th>
                <th>Archived Date</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="userArchiveTbody">
              <tr><td colspan="6" class="text-center text-muted py-4">Loading...</td></tr>
            </tbody>
          </table>
        </div>

        <div class="resident-table-footer mt-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
          <div class="d-flex align-items-center gap-2">
            <label for="userArchiveEntriesInput" class="small text-muted mb-0">Entries</label>
            <input id="userArchiveEntriesInput" type="number" min="1" step="1" value="20" class="form-control form-control-sm resident-entries-input">
          </div>
          <nav aria-label="User archive pagination">
            <ul class="pagination pagination-sm mb-0" id="userArchivePagination"></ul>
          </nav>
        </div>
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    window.ADMIN_USER_ARCHIVE_CSRF_TOKEN = <?= json_encode(ensureCsrfToken(), JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <script src="../JS-Script-Files/Admin-End/archiveUserScript.js?v=20260904-portal-actions"></script>
</body>
</html>
