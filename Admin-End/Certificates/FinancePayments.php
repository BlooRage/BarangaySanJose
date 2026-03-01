<?php
require_once __DIR__ . '/../includes/admin_guard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Finance Payments</title>
  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css">
  <style>
    .certificate-tracker-shell {
      max-width: 1340px;
      margin: 0 auto;
    }
    .certificate-tracker-shell .admin-list-toolbar {
      overflow-x: visible;
      overflow-y: visible;
      flex-wrap: wrap;
      row-gap: 12px;
    }
    .certificate-tracker-shell .admin-list-tabs {
      gap: 12px;
      overflow: visible;
    }
    .certificate-tracker-shell .stage-filter-btn {
      border-radius: 10px;
      border-width: 1px;
      min-width: 140px;
      position: relative;
    }
    .certificate-tracker-shell .stage-filter-btn .tab-count {
      position: absolute;
      top: -7px;
      right: -7px;
      min-width: 20px;
      height: 20px;
      padding: 0 6px;
      border-radius: 999px;
      background: #dc3545;
      color: #fff;
      font-size: .72rem;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      line-height: 1;
      box-shadow: none;
    }
    .certificate-tracker-shell .status-filter-btn[data-status-filter="all"] {
      border-color: #0d6efd;
      color: #0d6efd;
    }
    .certificate-tracker-shell .status-filter-btn[data-status-filter="all"].active {
      background: #0d6efd;
      border-color: #0d6efd;
      color: #fff;
    }
    .certificate-tracker-shell .status-filter-btn:not([data-status-filter="all"]).active {
      background: #fff3e4;
      border-color: #fe993c;
      color: #a04f00;
    }
    .certificate-tracker-shell .admin-list-actions .input-group-text,
    .certificate-tracker-shell .admin-list-actions .form-control {
      height: 38px;
    }
    .certificate-tracker-shell .admin-search {
      min-width: 300px;
      max-width: 360px;
    }
    .certificate-tracker-shell .table-responsive {
      overflow-x: auto;
      overflow-y: visible;
      -webkit-overflow-scrolling: touch;
    }
    #table-certificateTracker {
      table-layout: auto;
      width: 100%;
      min-width: 1100px;
    }
    #table-certificateTracker th,
    #table-certificateTracker td {
      vertical-align: middle;
    }
    #table-certificateTracker .col-request-id { width: 11%; }
    #table-certificateTracker .col-resident-id { width: 11%; }
    #table-certificateTracker .col-full-name { width: 18%; }
    #table-certificateTracker .col-document { width: 15%; }
    #table-certificateTracker .col-purpose { width: 17%; }
    #table-certificateTracker .col-status { width: 13%; }
    #table-certificateTracker .col-submitted { width: 10%; }
    #table-certificateTracker .col-action { width: 15%; }

    #viewModal .modal-body { background: #f8fafc; }
    #viewModal .tracker-doc-highlight {
      background: #dbeafe;
      color: #1e40af;
      border-radius: 8px;
      padding: 10px 12px;
      font-weight: 700;
      margin-bottom: 12px;
    }
    #viewModal .tracker-form-section {
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      background: #fff;
      padding: 12px;
      margin-bottom: 12px;
    }
    #viewModal .tracker-form-section-title {
      font-size: 1rem;
      font-weight: 700;
      color: #111827;
      margin-bottom: 10px;
    }
    #viewModal .tracker-form-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
    }
    #viewModal .tracker-form-grid.cols-1 { grid-template-columns: 1fr; }
    #viewModal .tracker-form-field {
      background: #f8fafc;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      padding: 8px 10px;
    }
    #viewModal .tracker-form-label {
      margin: 0 0 3px 0;
      font-size: .78rem;
      color: #6b7280;
      font-weight: 600;
      text-transform: capitalize;
    }
    #viewModal .tracker-form-value {
      margin: 0;
      color: #111827;
      font-weight: 600;
      word-break: break-word;
    }
    #viewModal .tracker-status-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 10px;
    }
    @media (max-width: 767px) {
      #viewModal .tracker-form-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 767px) {
      .certificate-tracker-shell .stage-filter-btn {
        min-width: 118px;
      }
    }
  </style>
</head>
<body>
<div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
    <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C; ">Finance Payments</h2>
    <hr class="mb-4">

    <div class="bg-white p-4 rounded-4 shadow-sm border resident-masterlist-shell certificate-tracker-shell">
      <div class="admin-list-toolbar mb-3">
        <div class="admin-list-tabs">
          <button type="button" class="btn btn-outline-primary btn-sm status-filter-btn stage-filter-btn active" data-status-filter="all">All</button>
          <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn stage-filter-btn fw-semibold" data-status-filter="verified">Verified</button>
          <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn stage-filter-btn fw-semibold" data-status-filter="unpaid">Unpaid <span class="tab-count" id="unpaidTabCount">0</span></button>
          <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn stage-filter-btn fw-semibold" data-status-filter="pending_verification">Pending Verification <span class="tab-count" id="pendingVerificationTabCount">0</span></button>
        </div>

        <div class="admin-list-actions">
          <div class="input-group admin-search">
            <input type="text" id="searchInput" class="form-control" placeholder="Request ID, resident ID, name, document">
            <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
          </div>
          <button class="btn btn-outline-secondary btn-icon" type="button" data-bs-toggle="modal" data-bs-target="#modalFinanceFilter" id="btnFinanceFilter" title="Filter" aria-label="Filter">
            <i class="fas fa-filter"></i>
            <span class="visually-hidden">Filter</span>
          </button>
          <button class="btn btn-outline-secondary btn-icon admin-columns" type="button" data-bs-toggle="modal" data-bs-target="#modalFinanceColumns" id="btnFinanceColumns" title="Columns" aria-label="Columns">
            <i class="fa-solid fa-sliders"></i>
            <span class="visually-hidden">Columns</span>
          </button>
          <button class="btn btn-outline-secondary btn-icon admin-refresh" type="button" id="btnRefreshList" title="Refresh table" aria-label="Refresh table">
            <i class="fa-solid fa-arrows-rotate"></i>
            <span class="visually-hidden">Refresh</span>
          </button>
        </div>
      </div>

      <div class="table-responsive">
        <table id="table-certificateTracker" class="table align-middle">
          <thead>
            <tr class="table-light">
              <th class="col-request-id">Request ID</th>
              <th class="col-resident-id">Resident ID</th>
              <th class="col-full-name">Full Name</th>
              <th class="col-document">Document Requested</th>
              <th class="col-purpose">Purpose</th>
              <th class="col-status">Status</th>
              <th class="col-submitted">Submitted Date</th>
              <th class="col-action">Action</th>
            </tr>
          </thead>
          <tbody id="tableBody">
            <tr>
              <td colspan="8" class="text-center text-muted py-4">Loading requests...</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<div class="modal fade" id="modalFinanceFilter" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-3">
      <div class="modal-header border-0 pb-1">
        <h5 class="modal-title fw-bold">Filter Finance Payments</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <hr class="my-2">
      <div class="modal-body">
        <div class="mb-3">
          <label for="financeFilterDocumentType" class="form-label fw-semibold">Document Type</label>
          <select id="financeFilterDocumentType" class="form-select">
            <option value="">All documents</option>
          </select>
        </div>
        <div>
          <label for="financeFilterPaymentMethod" class="form-label fw-semibold">Payment Method</label>
          <select id="financeFilterPaymentMethod" class="form-select">
            <option value="">All payment methods</option>
            <option value="gcash">GCash</option>
            <option value="barangay">Pay in Barangay</option>
          </select>
        </div>
      </div>
      <div class="modal-footer border-0 pt-1">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-warning" id="btnFinanceFilterReset">Reset</button>
        <button type="button" class="btn btn-primary" id="btnFinanceFilterApply">Apply Filter</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalFinanceColumns" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-3">
      <div class="modal-header border-0 pb-1">
        <h5 class="modal-title fw-bold">Choose Columns</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <hr class="my-2">
      <div class="modal-body d-grid gap-2">
        <label class="table-columns-check form-check d-flex align-items-center justify-content-between mb-0">
          <span>Request ID</span><input class="form-check-input" type="checkbox" data-finance-col-index="1" checked>
        </label>
        <label class="table-columns-check form-check d-flex align-items-center justify-content-between mb-0">
          <span>Resident ID</span><input class="form-check-input" type="checkbox" data-finance-col-index="2" checked>
        </label>
        <label class="table-columns-check form-check d-flex align-items-center justify-content-between mb-0">
          <span>Full Name</span><input class="form-check-input" type="checkbox" data-finance-col-index="3" checked>
        </label>
        <label class="table-columns-check form-check d-flex align-items-center justify-content-between mb-0">
          <span>Document Requested</span><input class="form-check-input" type="checkbox" data-finance-col-index="4" checked>
        </label>
        <label class="table-columns-check form-check d-flex align-items-center justify-content-between mb-0">
          <span>Purpose</span><input class="form-check-input" type="checkbox" data-finance-col-index="5" checked>
        </label>
        <label class="table-columns-check form-check d-flex align-items-center justify-content-between mb-0">
          <span>Status</span><input class="form-check-input" type="checkbox" data-finance-col-index="6" checked>
        </label>
        <label class="table-columns-check form-check d-flex align-items-center justify-content-between mb-0">
          <span>Submitted Date</span><input class="form-check-input" type="checkbox" data-finance-col-index="7" checked>
        </label>
        <label class="table-columns-check form-check d-flex align-items-center justify-content-between mb-0">
          <span>Action</span><input class="form-check-input" type="checkbox" data-finance-col-index="8" checked>
        </label>
      </div>
      <div class="modal-footer border-0 pt-1">
        <button type="button" class="btn btn-warning" id="btnFinanceColumnsReset">Reset</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="actionForm" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title" id="actionModalTitle">Update Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="actionType" name="action">
        <input type="hidden" id="actionRequestId" name="request_id">

        <div id="actionReasonWrap" class="d-none mb-3">
          <label class="form-label">Reason</label>
          <textarea id="actionReason" name="reason" class="form-control" rows="3"></textarea>
        </div>

        <div id="actionAmountWrap" class="d-none mb-3">
          <label class="form-label">Amount</label>
          <input id="actionAmount" name="amount" type="number" min="0" step="0.01" class="form-control">
        </div>

        <div id="actionOrWrap" class="d-none mb-3">
          <label class="form-label">OR Number</label>
          <input id="actionOr" name="or_number" type="text" class="form-control">
        </div>

        <div id="actionIssuedWrap" class="d-none mb-3">
          <label class="form-label">Issued File (optional)</label>
          <input id="actionIssued" name="issued_file" type="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
        </div>

        <div id="actionModalError" class="alert alert-danger d-none mb-0"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Submit</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewModalTitle">Certificate Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="viewDetailsBody"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success d-none" id="viewModalWalkInBtn">Record Walk-in Payment</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="paymentProofModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Payment Proof</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="paymentProofWrap" class="w-100 text-center"></div>
      </div>
      <div class="modal-footer">
        <a id="paymentProofOpenNew" class="btn btn-outline-primary" target="_blank" rel="noopener">Open in New Tab</a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
window.CERT_TRACKER_DEFAULT_STAGE = 'finance';
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../JS-Script-Files/Admin-End/certificateTrackerScript.js"></script>
</body>
</html>
