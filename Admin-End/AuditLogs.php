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
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AuditLogsStyle.css?v=20260215-1">
</head>
<body>
  <div class="d-flex" style="min-height: 100vh;">
    <?php
      require_once "../PhpFiles/General/connection.php";
      require_once "includes/admin_guard.php";
      include "includes/sidebar.php";
    ?>

    <main class="flex-grow-1 p-4 p-md-5 bg-light" id="main-display">
      <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C; font-size: 48px;">
        Audit Logs
      </h2>
      <hr><br>

	      <div id="div-tableContainer" class="bg-white p-4 rounded-4 shadow-sm border audit-shell">
	        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
	          <div class="text-muted small">
	            System activity trail (latest first)
	          </div>
	          <div class="d-flex align-items-center gap-2">
	            <div class="input-group audit-search" style="max-width: 380px;">
	              <input id="auditSearch" class="form-control" placeholder="Search user/module/target/action..." />
	              <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
	            </div>
	            <button id="btnAuditColumns" class="btn audit-columns" type="button" data-bs-toggle="modal" data-bs-target="#modalAuditColumns">
	              <i class="fa-solid fa-sliders"></i>
	              <span class="d-none d-sm-inline">Columns</span>
	            </button>
	            <button id="btnAuditRefresh" class="btn audit-refresh">
	              <i class="fa-solid fa-arrows-rotate"></i>
	              <span class="d-none d-sm-inline">Refresh</span>
	            </button>
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
	            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
	            <button type="button" class="btn btn-primary" id="btnAuditColumnsApply">Apply</button>
	          </div>
	        </div>
	      </div>
	    </div>
	  </div>

	  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
	  <script src="../JS-Script-Files/Admin-End/auditLogsScript.js?v=20260215"></script>
	</body>
	</html>
