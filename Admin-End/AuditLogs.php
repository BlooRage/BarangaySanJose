<?php
require_once "../PhpFiles/General/security.php";
requireRoleSession(['Admin', 'Employee'], false);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" href="/Images/favicon_sanjose.png?v=20260211">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Audit Logs</title>

  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <div class="d-flex" style="min-height: 100vh;">
    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-grow-1 p-4 p-md-5 bg-light" id="main-display">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div>
          <h1 class="m-0" style="font-family: 'Charis SIL Bold'; color: #DE710C; font-size: 44px;">Audit Logs</h1>
          <div class="text-muted small">System activity trail (latest first)</div>
        </div>
        <div class="d-flex gap-2">
          <input id="auditSearch" class="form-control" style="min-width: 280px;" placeholder="Search user/module/target/action..." />
          <button id="btnAuditRefresh" class="btn btn-outline-secondary">Refresh</button>
        </div>
      </div>

      <hr class="mt-0" />

      <div class="card shadow-sm">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="white-space:nowrap;">Timestamp</th>
                  <th>User</th>
                  <th>Role</th>
                  <th>Module</th>
                  <th>Target</th>
                  <th>Action</th>
                  <th>Field</th>
                  <th>Old</th>
                  <th>New</th>
                  <th>Remarks</th>
                </tr>
              </thead>
              <tbody id="auditTbody">
                <tr><td colspan="10" class="text-center text-muted py-4">Loading...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../JS-Script-Files/Admin-End/auditLogsScript.js?v=20260215"></script>
</body>
</html>

