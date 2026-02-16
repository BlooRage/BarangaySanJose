<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    
  <link rel="icon" href="/Images/favicon_sanjose.png?v=20260211">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sector Membership Verification</title>

    <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260216-2">
</head>

<body>
<div class="d-flex" style="min-height: 100vh;">

    <!-- SIDEBAR INCLUDE -->
<?php
require_once "../PhpFiles/General/connection.php";
require_once 'includes/admin_guard.php';
include 'includes/sidebar.php';
?>

    <!-- MAIN CONTENT -->
    <main id="main-display" class="flex-grow-1 p-4 p-md-5 bg-light">
        <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C; font-size: 48px;">
            Sector Membership Verification
        </h2>
        <hr><br>

        <div class="bg-white p-4 rounded-4 shadow-sm border">

	            <div class="admin-list-toolbar mb-3">
	                <div class="admin-list-tabs">
	                    <button class="btn btn-outline-primary btn-sm filter-btn active" data-filter="ALL">All</button>
	                    <button class="btn btn-outline-warning text-dark btn-sm filter-btn" data-filter="PendingReview">Pending</button>
	                    <button class="btn btn-outline-success btn-sm filter-btn" data-filter="Verified">Verified</button>
	                    <button class="btn btn-outline-danger btn-sm filter-btn" data-filter="Rejected">Rejected</button>
	                </div>
	
	                <div class="admin-list-actions">
	                    <div class="input-group admin-search">
	                        <input type="text" id="searchInput" class="form-control" placeholder="Search Resident ID / Name / Sector">
	                        <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
	                    </div>
	                    <button class="btn btn-outline-secondary btn-icon" type="button" data-bs-toggle="modal" data-bs-target="#modalFilter" id="filterButton" title="Filter" aria-label="Filter">
	                        <i class="fas fa-filter"></i>
	                        <span class="visually-hidden">Filter</span>
	                    </button>
	                    <button class="btn admin-columns btn-icon" type="button" data-bs-toggle="modal" data-bs-target="#modalTableColumns" id="btnSectorAppsColumns" title="Columns" aria-label="Columns">
	                        <i class="fa-solid fa-sliders"></i>
	                        <span class="visually-hidden">Columns</span>
	                    </button>
	                    <button class="btn admin-refresh btn-icon" type="button" id="btnSectorAppsRefresh" title="Refresh table" aria-label="Refresh table">
	                        <i class="fa-solid fa-arrows-rotate"></i>
	                        <span class="visually-hidden">Refresh</span>
	                    </button>
	                    <span id="sectorAppsAutoRefreshCountdown" class="small text-muted d-none"></span>
	                </div>
	            </div>

            <div id="sectorAppsLoading" class="text-muted small mb-2">Loading applications...</div>
            <div id="sectorAppsEmpty" class="text-muted small d-none">No sector membership applications found.</div>

            <div class="table-responsive">
                <table class="table align-middle" id="table-sectorApps">
                    <thead>
                        <tr class="table-light">
                            <th>Resident ID</th>
                            <th>Name</th>
                            <th>Sector Membership</th>
                            <th>Status</th>
                            <th style="width: 120px;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="sectorTableBody">
                        <!-- Filled by JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- DOCUMENT VIEWER MODAL (single document + actions) -->
<div class="modal fade doc-viewer-modal" id="modal-sectorDocViewer" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content p-3">
      <div class="modal-header border-0">
        <div class="w-100">
          <h5 class="fw-bold mb-0" id="sector-docViewer-title">Document Preview</h5>
          <div class="small text-muted" id="sector-docViewer-subtitle"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3" id="sector-docViewer-info"></div>

        <div id="sector-docViewer-body" class="w-100 mb-3"></div>
        <div id="sector-docViewer-actions" class="d-flex flex-nowrap w-100 gap-2"></div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- APPROVE CONFIRM MODAL -->
<div class="modal fade" id="modal-sectorApproveConfirm" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-3">
      <div class="modal-header justify-content-center border-0 pb-0">
        <h5 class="modal-title fw-bold text-center w-100">Verify Document</h5>
      </div>
      <hr>
      <div class="modal-body text-center">
        <p class="mb-0">Are you sure you want to verify this sector membership document?</p>
      </div>
      <div class="modal-footer border-0 pt-0 d-flex gap-2 w-100">
        <button type="button" class="btn btn-outline-secondary flex-fill" id="btn-sectorApproveCancel">Return</button>
        <button type="button" class="btn btn-success flex-fill" id="btn-sectorApproveConfirm">Verify</button>
      </div>
    </div>
  </div>
</div>

<!-- DENY CONFIRM MODAL -->
<div class="modal fade" id="modal-sectorDenyConfirm" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-3">
      <div class="modal-header justify-content-center border-0 pb-0">
        <h5 class="modal-title fw-bold text-center w-100">Decline Document</h5>
      </div>
      <hr>
      <div class="modal-body">
        <label for="txt-sectorDenyReason" class="form-label fw-bold mb-1">Reason for denial</label>
        <textarea id="txt-sectorDenyReason" class="form-control" rows="4" placeholder="State why this document is denied."></textarea>
        <div class="invalid-feedback d-none" id="txt-sectorDenyReasonError">Reason is required.</div>
      </div>
      <div class="modal-footer border-0 pt-0 d-flex gap-2 w-100">
        <button type="button" class="btn btn-outline-secondary flex-fill" id="btn-sectorDenyCancel">Return</button>
        <button type="button" class="btn btn-danger flex-fill" id="btn-sectorDenyConfirm">Decline</button>
      </div>
    </div>
  </div>
</div>

<!-- FILTER MODAL -->
<div class="modal fade" id="modalFilter" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold">Filter Applications</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <hr>
      <div class="modal-body">
        <div class="d-flex flex-wrap gap-2">
          <button class="btn btn-outline-primary btn-sm filter-btn active" data-filter="ALL" data-bs-dismiss="modal">All</button>
          <button class="btn btn-outline-warning text-dark btn-sm filter-btn" data-filter="PendingReview" data-bs-dismiss="modal">Pending</button>
          <button class="btn btn-outline-success btn-sm filter-btn" data-filter="Verified" data-bs-dismiss="modal">Verified</button>
          <button class="btn btn-outline-danger btn-sm filter-btn" data-filter="Rejected" data-bs-dismiss="modal">Rejected</button>
        </div>
        <div class="small text-muted mt-3">Selecting a filter applies immediately.</div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
      </div>
    </div>
  </div>
</div>

<!-- TABLE COLUMNS MODAL -->
<div class="modal fade" id="modalTableColumns" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Columns</h5>
      </div>
      <div class="modal-body">
        <div class="row g-2" id="tableColumnsList"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" id="btnTableColumnsReset">Reset</button>
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  window.ADMIN_TABLE_COLUMNS_CONFIG = {
    tableSelector: "#table-sectorApps",
    modalId: "modalTableColumns",
    listId: "tableColumnsList",
    resetBtnId: "btnTableColumnsReset",
    storageKey: "admin_cols_sector_membership_v1"
  };
</script>
<script src="../JS-Script-Files/Admin-End/tableColumnsGeneric.js?v=20260215-1"></script>
<script src="../JS-Script-Files/Admin-End/sectorMembershipVerificationScript.js?v=20260214-1"></script>
</body>
</html>

