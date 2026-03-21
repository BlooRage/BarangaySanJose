<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" href="../Images/favicon_sanjose.png?v=20260211">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Head Of Family Verification</title>

  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260227-2">
  <style>
    #main-display {
      min-width: 0;
    }

    #div-tableContainer {
      overflow: hidden;
    }

    .hof-shell {
      border-color: #f1e1cf !important;
      width: 100%;
      overflow: hidden;
      max-width: 100%;
      overflow-y: visible;
      -webkit-overflow-scrolling: touch;
    }

    .hof-shell .table-responsive {
      overflow-x: auto;
      overflow-y: visible;
      -webkit-overflow-scrolling: touch;
      max-width: 100%;
    }

    .hof-shell #table-hofQueue {
      min-width: 100%;
    }

    .hof-shell #table-hofQueue th,
    .hof-shell #table-hofQueue td {
      white-space: nowrap;
      vertical-align: middle;
    }

    .status-pill {
      min-width: 0;
    }

    .status-pill.pending {
      color: #6c5a06;
      background: #f4e8b7;
      border-color: #e9db9f;
    }

    .status-pill.approved {
      color: #18613f;
      background: #d4e8db;
      border-color: #c0dac9;
    }

    .status-pill.declined {
      color: #8f2932;
      background: #e8cfd3;
      border-color: #e0bcc2;
    }

    .hof-view-card {
      border: 1px solid #f1e1cf;
      border-radius: 12px;
      background: #fffaf4;
      padding: 14px;
    }

    .hof-view-label {
      font-size: 0.78rem;
      color: #6c757d;
      font-weight: 600;
    }

    .hof-view-value {
      font-weight: 600;
    }
  </style>
</head>
<body>
  <div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php
      require_once "../PhpFiles/General/connection.php";
      require_once "includes/admin_guard.php";
      include "includes/sidebar.php";
    ?>

    <main class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light" id="main-display">
      <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C; ">
        Head Of Family Verification
      </h2>
      <hr><br>

      <div id="div-tableContainer" class="bg-white p-4 rounded-4 shadow-sm border resident-masterlist-shell hof-shell">
        <div class="admin-list-toolbar mb-3 pt-2 flex-wrap">
          <div class="admin-list-tabs">
            <button type="button" class="btn btn-outline-primary btn-sm status-filter-btn active" data-filter="ALL">&nbsp;&nbsp;All&nbsp;&nbsp;</button>
            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold" data-filter="Approved">&nbsp;&nbsp;Approved&nbsp;&nbsp;</button>
            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold" data-filter="Declined">&nbsp;&nbsp;Declined&nbsp;&nbsp;</button>
            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn has-notif fw-semibold" data-filter="Pending">
              &nbsp;&nbsp;Pending
              <span id="pendingHofBadge" class="pending-count-badge d-none">0</span>
            </button>
          </div>
          <div class="admin-list-actions">
            <div class="input-group admin-search">
              <input id="hofSearch" class="form-control" placeholder="Search address/group/status..." />
              <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
            </div>
            <button id="btnHofFilter" class="btn btn-outline-secondary btn-icon admin-filter" type="button" title="Filter" aria-label="Filter" data-bs-toggle="modal" data-bs-target="#modalHofFilter">
              <i class="fas fa-filter"></i>
              <span class="visually-hidden">Filter</span>
            </button>
            <button id="btnHofColumns" class="btn btn-outline-secondary btn-icon admin-columns" type="button" title="Columns" aria-label="Columns" data-bs-toggle="modal" data-bs-target="#modalHofColumns">
              <i class="fa-solid fa-sliders"></i>
              <span class="visually-hidden">Columns</span>
            </button>
            <button id="btnHofRefresh" class="btn btn-outline-secondary btn-icon admin-refresh" type="button" title="Refresh table" aria-label="Refresh table">
              <i class="fa-solid fa-arrows-rotate"></i>
              <span class="visually-hidden">Refresh</span>
            </button>
          </div>
        </div>

        <div class="table-responsive compact-admin-table-shell">
          <table class="table table-hover align-middle mb-0 compact-admin-table" id="table-hofQueue">
            <thead class="table-light">
              <tr>
                <th>Address</th>
                <th>Area Number</th>
                <th>Applicants</th>
                <th>Status</th>
                <th>Decided By</th>
                <th>Decided At</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="hofTbody">
              <tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>
            </tbody>
          </table>
        </div>

        <div class="resident-table-footer mt-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
          <div class="d-flex align-items-center gap-2">
            <label for="hofEntriesPerPageInput" class="small text-muted mb-0">Entries</label>
            <input id="hofEntriesPerPageInput" type="number" min="1" step="1" value="20" class="form-control form-control-sm resident-entries-input" />
          </div>
          <nav aria-label="Head of family queue pagination">
            <ul class="pagination pagination-sm mb-0" id="hofPagination"></ul>
          </nav>
        </div>
      </div>
    </main>
  </div>

  <div class="modal fade" id="modalApproveHead" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Approve Head Application</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="small mb-2"><span class="fw-semibold">Address:</span> <span id="approveAddressDisplay">-</span></p>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width:72px;">Select</th>
                  <th>Resident ID</th>
                  <th>Name</th>
                  <th>Members</th>
                </tr>
              </thead>
              <tbody id="approveApplicantsBody"></tbody>
            </table>
          </div>
          <input type="hidden" id="approveGroupKey" value="">
          <div class="small text-danger mt-2 d-none" id="approveHeadError">Please select one resident.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-success" id="btnConfirmApproveHead">Approve</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalHofColumns" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Columns</h5>
        </div>
        <div class="modal-body">
          <div class="row g-2" id="hofColumnsList"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" id="btnHofColumnsReset">Reset</button>
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
        </div>
      </div>
    </div>
  </div>
  <div class="modal fade" id="modalHofFilter" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content p-3">
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold">Filter Verification Status</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <label class="form-label small fw-bold mb-1" for="hofStatusFilterSelect">Status</label>
          <select id="hofStatusFilterSelect" class="form-select">
            <option value="ALL">All</option>
            <option value="Pending">Pending</option>
            <option value="Approved">Approved</option>
            <option value="Declined">Declined</option>
          </select>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-outline-secondary" id="btnHofFilterReset">Reset</button>
          <button type="button" class="btn btn-primary" id="btnHofFilterApply" data-bs-dismiss="modal">Apply</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalHofView" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content p-4">
        <div class="modal-header border-0">
          <div>
            <h4 class="fw-bold mb-1">Household Profiling</h4>
            <div class="text-muted small">Resident Registration Details</div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <div class="hof-view-label">Household Address</div>
            <div id="hofViewAddress" class="hof-view-value">-</div>
            <div id="hofViewAddressMeta" class="text-muted small"></div>
          </div>
          <div class="row g-3" id="hofViewApplicants"></div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    window.ADMIN_TABLE_COLUMNS_CONFIG = {
      tableSelector: "#table-hofQueue",
      modalId: "modalHofColumns",
      listId: "hofColumnsList",
      resetBtnId: "btnHofColumnsReset",
      storageKey: "admin_cols_hof_queue_v1"
    };
  </script>
  <script src="../JS-Script-Files/Admin-End/tableColumnsGeneric.js?v=20260215-1"></script>
  <script src="../JS-Script-Files/Admin-End/headOfFamilyVerificationScript.js?v=20260311-1"></script>
</body>
</html>
