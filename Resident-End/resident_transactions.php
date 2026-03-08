<?php
require_once __DIR__ . "/includes/resident_access_guard.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="../Images/favicon_sanjose.png?v=20260211">
  <title>Resident Transactions - Barangay San Jose</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/residentDashboard.css">
  <style>
    :root {
      --txn-hover-bg: #e6e8ec;
    }
    .audit-shell {
      border-color: #f1e1cf !important;
    }
    .btn-icon {
      width: 38px;
      height: 38px;
      padding: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex: 0 0 38px;
      border-radius: 10px;
      line-height: 1;
    }
    .btn-icon i {
      margin: 0 !important;
    }
    .admin-list-toolbar {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: nowrap;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      padding-bottom: 2px;
      margin-top: 8px;
    }
    .admin-list-tabs,
    .admin-list-actions {
      display: flex;
      align-items: center;
      gap: 8px;
      flex: 0 0 auto;
      white-space: nowrap;
    }
    .admin-list-actions {
      margin-left: auto;
      flex-wrap: nowrap;
    }
    .pending-summary-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border: 1px solid #f3d9ad;
      background: #fff7eb;
      color: #8a4b00;
      font-weight: 700;
      border-radius: 999px;
      padding: 6px 10px;
      line-height: 1;
      white-space: nowrap;
    }
    .pending-summary-badge .count {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 24px;
      height: 24px;
      border-radius: 999px;
      background: #de710c;
      color: #fff;
      font-size: 0.8rem;
      padding: 0 7px;
    }
    .admin-search {
      min-width: 260px;
      max-width: 420px;
      flex: 1 1 auto;
    }
    .admin-list-actions .btn-icon {
      flex: 0 0 38px;
    }
    .audit-search .form-control:focus {
      box-shadow: 0 0 0 .2rem rgba(254, 153, 60, 0.18);
      border-color: rgba(254, 153, 60, 0.55);
    }
    .admin-refresh {
      border-color: rgba(254, 153, 60, 0.45);
      color: #b85b00;
      background: linear-gradient(180deg, #fffaf4 0%, #fff3e4 100%);
      font-weight: 700;
      letter-spacing: 0.2px;
      transition: transform 120ms ease, box-shadow 120ms ease, background-color 120ms ease, border-color 120ms ease;
    }
    .admin-refresh:hover {
      border-color: rgba(254, 153, 60, 0.7);
      color: #a04f00;
      background: linear-gradient(180deg, #fff6ec 0%, #ffe9d1 100%);
      box-shadow: 0 10px 18px rgba(222, 113, 12, 0.14);
      transform: translateY(-1px);
    }
    .admin-refresh:active {
      transform: translateY(0);
      box-shadow: 0 6px 12px rgba(222, 113, 12, 0.12);
    }
    .admin-refresh:focus-visible {
      box-shadow: 0 0 0 .2rem rgba(254, 153, 60, 0.25);
    }
    .admin-refresh.is-loading i {
      animation: txnSpin 900ms linear infinite;
    }
    @keyframes txnSpin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }
    .audit-table th {
      white-space: nowrap;
    }
    .audit-table td {
      vertical-align: middle;
      max-width: 260px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .audit-table td:nth-child(2) {
      white-space: normal;
      overflow: visible;
      text-overflow: initial;
      max-width: none;
      word-break: break-word;
    }
    .audit-table th:nth-child(1),
    .audit-table td:nth-child(1) { width: 14%; }
    .audit-table th:nth-child(2),
    .audit-table td:nth-child(2) { width: 44%; }
    .audit-table th:nth-child(3),
    .audit-table td:nth-child(3) { width: 22%; }
    .audit-table th:nth-child(4),
    .audit-table td:nth-child(4) { width: 10%; }
    .audit-table th:nth-child(5),
    .audit-table td:nth-child(5) { width: 10%; }
    .audit-table td:nth-child(5) {
      white-space: nowrap;
      overflow: visible;
      text-overflow: initial;
    }
    .txn-main-row {
      cursor: default;
    }
    .txn-main-row.has-docs {
      cursor: pointer;
    }
    .txn-action-btns {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.35rem;
      flex-wrap: nowrap;
      white-space: nowrap;
    }
    .txn-action-btns .btn {
      white-space: nowrap;
    }
    .txn-main-row.has-docs td {
      overflow: visible !important;
      text-overflow: clip !important;
    }
    .txn-hover-cue {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      color: #0d6efd;
      opacity: 1;
      transform: translateY(0);
      transition: opacity 180ms ease, transform 180ms ease;
      max-width: 100%;
      justify-content: center;
    }
    .txn-cue-row {
      background: #fff;
    }
    .txn-cue-cell {
      border-top: 0 !important;
      border-bottom: 1px solid transparent !important;
      padding: 0 !important;
      background: transparent !important;
      transition: border-color 220ms ease;
    }
    .txn-cue-inner {
      max-height: 0;
      opacity: 0;
      overflow: hidden;
      transform: translateY(-8px);
      transition: max-height 420ms ease, opacity 320ms ease, transform 340ms ease, padding 340ms ease;
      padding: 0;
    }
    .txn-row-cue-wrap {
      display: flex;
      justify-content: center;
      align-items: center;
      width: 100%;
      white-space: nowrap;
      color: #0d6efd;
      font-size: 0.76rem;
      font-weight: 700;
      letter-spacing: 0.01em;
    }
    .txn-hover-cue-label {
      white-space: nowrap;
    }
    .txn-hover-cue .txn-cue-arrow {
      font-size: 0.82rem;
      transition: transform 220ms ease;
    }
    .txn-main-row.has-docs:hover + .txn-expand-row + .txn-cue-row .txn-cue-inner,
    .txn-cue-row.has-docs:hover .txn-cue-inner,
    .txn-cue-row.has-docs.is-expanded .txn-cue-inner,
    .txn-main-row.has-docs.is-expanded + .txn-expand-row + .txn-cue-row .txn-cue-inner {
      max-height: 56px;
      opacity: 1;
      transform: translateY(0);
      padding: 0.25rem 0 0.45rem;
    }
    .txn-main-row.has-docs:hover,
    .txn-main-row.has-docs:hover td {
      background: var(--txn-hover-bg);
      --bs-table-accent-bg: transparent;
      box-shadow: none !important;
    }
    .txn-main-row.has-docs:hover td,
    .txn-main-row.has-docs.is-expanded td {
      border-bottom-color: transparent !important;
    }
    .txn-main-row.has-docs:hover + .txn-expand-row,
    .txn-main-row.has-docs:hover + .txn-expand-row td,
    .txn-main-row.has-docs:hover + .txn-expand-row + .txn-cue-row,
    .txn-main-row.has-docs:hover + .txn-expand-row + .txn-cue-row td,
    .txn-cue-row.has-docs:hover,
    .txn-cue-row.has-docs:hover td {
      background: var(--txn-hover-bg);
      --bs-table-accent-bg: transparent;
      box-shadow: none !important;
    }
    .txn-main-row.has-docs:hover + .txn-expand-row + .txn-cue-row .txn-cue-cell,
    .txn-cue-row.has-docs:hover .txn-cue-cell,
    .txn-main-row.has-docs.is-expanded + .txn-expand-row + .txn-cue-row .txn-cue-cell {
      border-bottom-color: #c7ccd3 !important;
    }
    .txn-expand-row:hover,
    .txn-expand-row:hover td {
      background: var(--txn-hover-bg);
      --bs-table-accent-bg: transparent;
      box-shadow: none !important;
    }
    .txn-hover-group,
    .txn-hover-group td {
      background: var(--txn-hover-bg) !important;
      --bs-table-accent-bg: transparent !important;
      box-shadow: none !important;
    }
    .txn-main-row.has-docs.is-expanded + .txn-expand-row + .txn-cue-row .txn-cue-arrow,
    .txn-cue-row.has-docs.is-expanded .txn-cue-arrow {
      transform: rotate(180deg);
    }
    .txn-expand-row {
      background: #fcfcfd;
    }
    .txn-expand-cell {
      border-top: 0 !important;
      border-bottom: 0 !important;
      padding-top: 0 !important;
    }
    .txn-expand-row td {
      border-top-color: transparent !important;
      border-bottom-color: transparent !important;
    }
    .txn-expand-inner {
      overflow: hidden;
      max-height: 0;
      opacity: 0;
      transform: translateY(-6px);
      transition: max-height 520ms ease, opacity 360ms ease, transform 420ms ease;
      padding-top: 0;
    }
    .txn-expand-row.is-open .txn-expand-inner {
      max-height: 360px;
      opacity: 1;
      transform: translateY(0);
      padding-top: 0.35rem;
    }
    .txn-doc-status-list {
      margin: 0;
      padding-left: 1.1rem;
    }
    .txn-doc-status-list li {
      margin-bottom: 0.3rem;
    }
    .txn-doc-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      font-size: 0.92rem;
    }
    .txn-doc-table th,
    .txn-doc-table td {
      padding: 0.45rem 0.55rem;
      border-bottom: 1px solid #e3e6ea;
      vertical-align: top;
      text-align: left;
    }
    .txn-doc-table thead th {
      background: #f7f8fa;
      font-weight: 700;
      color: #374151;
      white-space: nowrap;
    }
    .txn-doc-table tbody tr:last-child td {
      border-bottom: 0;
    }
    #txnCards {
      display: none;
    }
    .txn-tab.btn {
      border-radius: 999px;
      padding: 0.35rem 0.85rem;
      font-weight: 600;
      border-color: #e3e6ea;
      color: #495057;
      background: #fff;
    }
    .txn-tab.btn.active {
      border-color: rgba(254, 153, 60, 0.7);
      color: #a04f00;
      background: linear-gradient(180deg, #fff6ec 0%, #ffe9d1 100%);
    }
    .txn-page-title {
      font-family: 'Charis SIL Bold', serif;
      color: #DE710C;
      font-size: clamp(2rem, 4.4vw, 3rem);
      line-height: 1.1;
      margin: 0 0 0.65rem 0;
    }
    @media (max-width: 991.98px) {
      .admin-list-toolbar {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
      }
      .admin-list-tabs,
      .admin-list-actions {
        width: 100%;
      }
      .admin-list-tabs {
        overflow-x: auto;
        white-space: nowrap;
        padding-bottom: 4px;
      }
      .admin-list-actions {
        margin-left: 0;
        flex-wrap: nowrap;
        justify-content: flex-start;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 2px;
      }
      .admin-search {
        min-width: 0;
        max-width: none;
        width: 100%;
      }
    }
    @media (max-width: 767.98px) {
      #div-mainDisplay {
        padding: 1rem !important;
      }
      #div-tableContainer {
        padding: 0.9rem !important;
      }
      .table-responsive {
        display: none;
      }
      #txnCards {
        display: block;
      }
      .txn-card {
        border: 1px solid #eceff3;
        border-radius: 12px;
        padding: 0.85rem 0.9rem;
        background: #fff;
        margin-bottom: 0.75rem;
      }
      .txn-card .txn-meta {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.35rem;
      }
      .txn-card .txn-label {
        font-size: 0.78rem;
        color: #495057;
        text-transform: uppercase;
        letter-spacing: .02em;
        font-weight: 800;
      }
      .txn-card .txn-value {
        font-size: 0.96rem;
        color: #212529;
        word-break: break-word;
        white-space: normal;
      }
      .txn-card .fw-semibold {
        font-size: 1rem;
        color: #111827;
      }
      .txn-tab.btn {
        font-size: 0.95rem;
        font-weight: 800;
      }
      .txn-page-title {
        font-size: clamp(1.7rem, 7.5vw, 2.15rem);
        margin-bottom: 0.4rem;
      }
    }
    @media (max-width: 480px) {
      .audit-table {
        min-width: 620px;
      }
      .audit-table td,
      .audit-table th {
        font-size: 0.875rem;
      }
    }
    @media (max-width: 1160px) {
      #mobile-header {
        display: block !important;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        z-index: 1030;
        height: auto !important;
        padding: 0 !important;
        margin: 0 !important;
        overflow: visible !important;
      }
      #mobile-header .d-flex {
        width: 100%;
      }
      #div-mainDisplay {
        margin-left: 0 !important;
        width: 100%;
        padding-top: 1rem !important;
      }
      body {
        padding-top: 64px;
      }
      #div-sidebarWrapper {
        position: fixed !important;
        top: 0;
        left: 0;
        height: 100vh !important;
        width: 280px;
        z-index: 1060;
        transform: translateX(-100%);
        transition: transform 0.28s ease;
        box-shadow: 0 0 0 9999px rgba(0,0,0,0);
      }
      #div-sidebarWrapper.show {
        transform: translateX(0);
        box-shadow: 0 0 0 9999px rgba(0,0,0,0.25);
      }
    }
    @media (min-width: 1161px) {
      body {
        padding-top: 0;
      }
      #mobile-header {
        display: none !important;
      }
      #div-sidebarWrapper {
        transform: none !important;
      }
    }
  </style>
</head>
<body>
  <div class="d-flex" style="min-height: 100vh;">
    <?php include __DIR__ . '/includes/resident_sidebar.php'; ?>

    <header id="mobile-header">
      <div class="d-flex align-items-center px-3 py-2 shadow-sm bg-white">
        <div class="d-flex align-items-center gap-2">
          <button class="btn" id="btn-burger" type="button">
            <i class="fa-solid fa-bars fa-lg"></i>
          </button>
          <img src="../Images/San_Jose_LOGO.jpg" alt="Logo" style="width:32px;height:32px">
          <span class="logo-name">Barangay San Jose</span>
        </div>
      </div>
    </header>

    <main id="div-mainDisplay" class="flex-grow-1 p-4 p-md-5 bg-light">
      <h2 class="txn-page-title">Transactions</h2>
      <hr class="mt-0 mb-3">

      <div id="div-tableContainer" class="bg-white p-4 rounded-4 shadow-sm border audit-shell">
        <div class="admin-list-toolbar mb-3">
          <div class="admin-list-tabs">
            <button type="button" class="btn txn-tab active" data-tab="all">All</button>
            <button type="button" class="btn txn-tab" data-tab="verified">Verified</button>
            <button type="button" class="btn txn-tab" data-tab="denied">Denied</button>
            <button type="button" class="btn txn-tab" data-tab="pending">Pending</button>
          </div>
          <div class="admin-list-actions">
            <div id="txnPendingSummary" class="pending-summary-badge d-none" aria-live="polite">
              <span>Pending</span>
              <span id="txnPendingCount" class="count">0</span>
            </div>
            <div class="input-group admin-search audit-search">
              <input id="txnSearch" class="form-control" placeholder="Search..." />
              <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
            </div>
            <button id="txnFilterBtn" class="btn btn-outline-secondary btn-icon" type="button" title="Filter" aria-label="Filter" data-bs-toggle="modal" data-bs-target="#txnFilterModal">
              <i class="fas fa-filter"></i>
              <span class="visually-hidden">Filter</span>
            </button>
            <button id="txnRefreshBtn" class="btn admin-refresh btn-icon" type="button" title="Refresh table" aria-label="Refresh table">
              <i class="fa-solid fa-arrows-rotate"></i>
              <span class="visually-hidden">Refresh</span>
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 audit-table">
            <thead class="table-light">
              <tr>
                <th>Transaction</th>
                <th>Description</th>
                <th>Submitted Date</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="txnTbody">
              <tr>
                <td colspan="5" class="text-center text-muted py-4">Loading transactions...</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div id="txnCards" class="mt-2">
          <div class="text-center text-muted py-4">Loading transactions...</div>
        </div>
      </div>
    </main>
  </div>

  <div class="modal fade" id="txnFilterModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Filter Transactions</h5>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label small text-muted mb-1">Transaction Type</label>
              <select class="form-select" id="txnTypeFilter">
                <option value="">All Types</option>
                <option value="RESIDENT_PROFILING">Resident Profiling</option>
                <option value="PROOF_OF_RESIDENCY">Proof of Residency</option>
                <option value="SECTOR_MEMBERSHIP">Sector Membership</option>
                <option value="RESUBMIT_REQUIRED">Resubmit Required</option>
                <option value="EDIT_REQUEST_PROFILE">Profile Edit Request</option>
                <option value="EDIT_REQUEST_ADDRESS">Address Edit Request</option>
                <option value="EDIT_REQUEST_EMERGENCY">Emergency Edit Request</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small text-muted mb-1">Status</label>
              <select class="form-select" id="txnStatusFilter">
                <option value="">All Status</option>
                <option value="PendingRequest">Pending</option>
                <option value="ApprovedRequest">Approved</option>
                <option value="DeniedRequest">Denied</option>
                <option value="PendingReview">Pending Review</option>
                <option value="PendingVerification">Pending Verification</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small text-muted mb-1">Date From</label>
              <input type="date" class="form-control" id="txnDateFrom" />
            </div>
            <div class="col-12">
              <label class="form-label small text-muted mb-1">Date To</label>
              <input type="date" class="form-control" id="txnDateTo" />
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" id="txnFilterReset">Reset</button>
          <button type="button" class="btn btn-primary" id="txnFilterApply" data-bs-dismiss="modal">Apply</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="txnViewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Transaction Details</h5>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <div class="small text-muted">Transaction</div>
            <div class="fw-semibold" id="txnViewTitle">-</div>
            <div class="small text-muted" id="txnViewType">-</div>
          </div>
          <div class="mb-3">
            <div class="small text-muted">Description</div>
            <div id="txnViewDescription">-</div>
          </div>
          <div class="row g-3 mb-2">
            <div class="col-12 col-md-6">
              <div class="small text-muted">Submitted Date</div>
              <div id="txnViewDate">-</div>
            </div>
            <div class="col-12 col-md-6">
              <div class="small text-muted">Status</div>
              <div id="txnViewStatus">-</div>
            </div>
          </div>
          <div id="txnViewReasonWrap" class="alert alert-danger small d-none"></div>

          <div id="txnViewDocsWrap" class="mt-3 d-none">
            <div class="small text-muted mb-2">Uploaded Documents</div>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Document</th>
                    <th>Status</th>
                    <th>Uploaded</th>
                    <th>File</th>
                  </tr>
                </thead>
                <tbody id="txnViewDocsTbody"></tbody>
              </table>
            </div>
          </div>

          <div id="txnViewNoDocsNote" class="small text-muted d-none mt-2">
            Documents are hidden for denied transactions.
          </div>
        </div>
        <div class="modal-footer">
          <a href="#" class="btn btn-outline-primary d-none" id="txnViewResubmitBtn">Resubmit Document</a>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="txnDocViewerModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width: 1100px; width: 92vw;">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="fw-bold mb-0" id="txnDocViewerTitle">Document Preview</h5>
            <div class="small text-muted" id="txnDocViewerSubtitle"></div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="txnDocViewerBody"></div>
        <div class="modal-footer">
          <a href="#" class="btn btn-outline-primary d-none" id="txnDocViewerOpenNewTab" target="_blank" rel="noopener">Open</a>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    let allTransactions = [];
    let activeTab = "all";
    let txnViewModalInstance = null;
    let txnDocViewerModalInstance = null;
    let txnViewDocsCurrent = [];
    const expandedRows = new Set();
    const docStatusCache = new Map();

    function statusBadgeClass(statusName) {
      const raw = String(statusName || "").toLowerCase();
      const key = raw.replace(/[\s_-]+/g, "");
      if (key.includes("pending") || key.includes("review")) return "bg-warning-subtle text-warning-emphasis";
      if (key === "notverified" || key.includes("denied") || key.includes("rejected")) return "bg-danger-subtle text-danger";
      if (key.includes("approved") || key.includes("verified")) return "bg-success-subtle text-success";
      return "bg-warning-subtle text-warning-emphasis";
    }

    function displayStatusName(statusName) {
      const key = String(statusName || "").toLowerCase().trim();
      if (key === "notverified") return "Declined";
      if (key === "pendingverification") return "Pending";
      return String(statusName || "Pending");
    }

    function getStatusDecisionDate(row) {
      const key = String(row?.status_name || "").toLowerCase();
      const isFinal = key.includes("approved") || key.includes("verified") || key.includes("denied") || key.includes("rejected");
      if (!isFinal) return "";

      const raw = String(row?.reviewed_at || row?.updated_at || row?.created_at || "").trim();
      if (!raw) return "";
      return formatDateTime(raw);
    }

    function formatDateTime(value) {
      if (!value) return "-";
      const d = new Date(String(value).replace(" ", "T"));
      if (Number.isNaN(d.getTime())) return String(value);
      return d.toLocaleString();
    }

    function getFileExtFromDoc(doc) {
      const fromType = String(doc?.file_type || "").toLowerCase().trim();
      if (fromType) return fromType;
      const url = String(doc?.file_url || "");
      if (!url) return "";
      const noQuery = url.split("?")[0];
      return (noQuery.split(".").pop() || "").toLowerCase();
    }

    function openTxnDocViewer(doc) {
      const modalEl = document.getElementById("txnDocViewerModal");
      const bodyEl = document.getElementById("txnDocViewerBody");
      const titleEl = document.getElementById("txnDocViewerTitle");
      const subtitleEl = document.getElementById("txnDocViewerSubtitle");
      const openNewTabEl = document.getElementById("txnDocViewerOpenNewTab");
      if (!modalEl || !bodyEl || !window.bootstrap?.Modal) return;

      const url = String(doc?.file_url || "").trim();
      const ext = getFileExtFromDoc(doc);
      const docName = String(doc?.document_type_name || "Document");
      const uploaded = formatDateTime(doc?.uploaded_at || "");

      if (titleEl) titleEl.textContent = docName || "Document Preview";
      if (subtitleEl) subtitleEl.textContent = uploaded && uploaded !== "-" ? `Uploaded: ${uploaded}` : "";

      bodyEl.innerHTML = "";
      if (!url) {
        const div = document.createElement("div");
        div.className = "text-muted";
        div.textContent = "File is unavailable.";
        bodyEl.appendChild(div);
      } else if (["jpg", "jpeg", "png", "webp", "gif"].includes(ext)) {
        const img = document.createElement("img");
        img.src = url;
        img.alt = docName;
        img.className = "img-fluid d-block mx-auto";
        bodyEl.appendChild(img);
      } else if (ext === "pdf") {
        const iframe = document.createElement("iframe");
        iframe.src = url;
        iframe.className = "w-100";
        iframe.style.height = "70vh";
        iframe.title = docName;
        bodyEl.appendChild(iframe);
      } else {
        const div = document.createElement("div");
        div.className = "text-muted";
        div.textContent = "Preview not available for this file type.";
        bodyEl.appendChild(div);
      }

      if (openNewTabEl) {
        if (url) {
          openNewTabEl.href = url;
          openNewTabEl.classList.remove("d-none");
        } else {
          openNewTabEl.href = "#";
          openNewTabEl.classList.add("d-none");
        }
      }

      if (!txnDocViewerModalInstance) {
        txnDocViewerModalInstance = new bootstrap.Modal(modalEl);
      }
      txnDocViewerModalInstance.show();
    }

    function escapeHtml(str) {
      return String(str ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
    }

    function getTabMatches(statusName) {
      const key = String(statusName || "").toLowerCase();
      if (activeTab === "verified") return key.includes("approved") || key.includes("verified");
      if (activeTab === "denied") return key.includes("denied") || key.includes("rejected") || key.includes("notverified");
      if (activeTab === "pending") return key.includes("pending") || key.includes("review");
      return true;
    }

    function isPendingStatus(statusName) {
      const key = String(statusName || "").toLowerCase();
      return (
        key.includes("pending") ||
        key.includes("review") ||
        key.includes("verify") ||
        key.includes("verification") ||
        key.includes("await")
      ) && !(
        key.includes("approved") ||
        key.includes("verified") ||
        key.includes("rejected") ||
        key.includes("denied") ||
        key.includes("notverified")
      );
    }

    function updatePendingSummary() {
      const wrap = document.getElementById("txnPendingSummary");
      const countEl = document.getElementById("txnPendingCount");
      if (!wrap || !countEl) return;
      const count = allTransactions.filter((row) => isPendingStatus(row?.status_name)).length;
      countEl.textContent = String(count);
      wrap.classList.toggle("d-none", count <= 0);
    }

    function isDeniedStatus(statusName) {
      const key = String(statusName || "").toLowerCase();
      return key.includes("denied") || key.includes("rejected") || key.includes("notverified");
    }

    function getDeniedReason(row) {
      if (!isDeniedStatus(row?.status_name)) return "";
      const fromMeta = String(
        row?.metadata?.admin_notes ||
        row?.metadata?.denied_reason ||
        row?.metadata?.reason ||
        ""
      ).trim();
      if (fromMeta) return fromMeta;

      const desc = String(row?.description || "").trim();
      if (!desc) return "";
      const match = desc.match(/reason\s*:\s*(.+)$/i);
      return match ? String(match[1] || "").trim() : "";
    }

    function getResubmitUrl(row) {
      const url = String(row?.metadata?.resubmit_url || "").trim();
      return url || "";
    }

    function getFilteredItems() {
      const searchValue = (document.getElementById("txnSearch").value || "").trim().toLowerCase();
      const type = document.getElementById("txnTypeFilter").value;
      const status = document.getElementById("txnStatusFilter").value;
      const dateFrom = document.getElementById("txnDateFrom").value;
      const dateTo = document.getElementById("txnDateTo").value;

      return allTransactions.filter((row) => {
        if (!getTabMatches(row.status_name)) return false;
        if (type && row.transaction_type !== type) return false;
        if (status && row.status_name !== status) return false;

        const rowDate = String(row.created_at || "").slice(0, 10);
        if (dateFrom && rowDate < dateFrom) return false;
        if (dateTo && rowDate > dateTo) return false;

        if (searchValue) {
          const hay = `${row.title || ""} ${row.transaction_type || ""} ${row.description || ""} ${row.status_name || ""}`.toLowerCase();
          if (!hay.includes(searchValue)) return false;
        }
        return true;
      });
    }

    function buildTransactionDescription(row) {
      const type = String(row?.transaction_type || "").trim().toUpperCase();
      const metadata = (row && typeof row === "object" && row.metadata && typeof row.metadata === "object")
        ? row.metadata
        : {};

      const toTitle = (value) => String(value || "")
        .replace(/([a-z])([A-Z])/g, "$1 $2")
        .replace(/[_-]+/g, " ")
        .toLowerCase()
        .replace(/\b\w/g, (m) => m.toUpperCase())
        .trim();

      const normalizeSector = (value) => {
        const raw = String(value || "").trim();
        if (!raw) return "";
        const lower = raw.toLowerCase();
        const map = {
          pwd: "PWD",
          seniorcitizen: "Senior Citizen",
          singleparent: "Single Parent",
          indigenouspeople: "Indigenous People",
          student: "Student"
        };
        const key = lower.replace(/[\s_-]+/g, "");
        return map[key] || toTitle(raw);
      };

      if (type === "RESIDENT_PROFILING") return "Resident Registration - Profiling";
      if (type === "PROOF_OF_RESIDENCY") return "Resident Registration - Proof of Residency";

      if (type === "SECTOR_MEMBERSHIP" || type === "SECTOR_MEMBERSHIP_VERIFICATION") {
        const sectors = Array.isArray(metadata.sectors)
          ? metadata.sectors
          : (Array.isArray(metadata.added_sectors) ? metadata.added_sectors : []);
        const sectorLabel = sectors.length
          ? sectors.map(normalizeSector).filter(Boolean).join(", ")
          : "General";
        return `Sector Membership - ${sectorLabel}`;
      }

      if (type === "RESUBMIT_REQUIRED") return "Document Review - Resubmission";
      if (type === "EDIT_REQUEST_PROFILE") return "Profile Update - Profile";
      if (type === "EDIT_REQUEST_ADDRESS") return "Profile Update - Address";
      if (type === "EDIT_REQUEST_EMERGENCY") return "Profile Update - Emergency";

      if (type) return toTitle(type);
      return "Transaction - Record";
    }

    function bindViewButtons() {
      document.querySelectorAll(".txn-view-btn").forEach((btn) => {
        btn.addEventListener("click", () => {
          const id = btn.getAttribute("data-txn-id");
          const row = allTransactions.find((item) => String(item.transaction_id) === String(id));
          if (!row) return;
          openTransactionView(row);
        });
      });
    }

    function resolveDocSource(row) {
      const txnType = String(row?.transaction_type || "").toUpperCase();
      const metadata = (row && typeof row === "object" && row.metadata && typeof row.metadata === "object")
        ? row.metadata
        : {};
      const attachmentIds = Array.isArray(metadata.attachment_ids)
        ? metadata.attachment_ids.map((v) => Number(v)).filter((v) => Number.isInteger(v) && v > 0)
        : [];
      const profilingTypes = new Set([
        "RESIDENT_PROFILING",
        "PROOF_OF_RESIDENCY",
        "SECTOR_MEMBERSHIP",
        "SECTOR_MEMBERSHIP_VERIFICATION"
      ]);
      if (profilingTypes.has(txnType) && row?.source_id) {
        let purpose = "all";
        if (txnType === "SECTOR_MEMBERSHIP" || txnType === "SECTOR_MEMBERSHIP_VERIFICATION") {
          purpose = "sector";
        } else if (txnType === "PROOF_OF_RESIDENCY") {
          purpose = "proof";
        }
        return {
          source_type: "ResidentProfiling",
          source_id: String(row.source_id),
          purpose,
          attachment_ids: attachmentIds
        };
      }
      if (!row?.source_type || !row?.source_id) return null;
      return {
        source_type: String(row.source_type),
        source_id: String(row.source_id),
        purpose: "all",
        attachment_ids: attachmentIds
      };
    }

    async function fetchDocStatusesForRow(row) {
      const cacheKey = String(row.transaction_id || "");
      if (!cacheKey) return [];

      const source = resolveDocSource(row);
      if (!source) {
        docStatusCache.set(cacheKey, []);
        return [];
      }

      const params = new URLSearchParams({
        source_type: source.source_type,
        source_id: source.source_id
      });
      if (source.purpose) params.set("purpose", source.purpose);
      if (Array.isArray(source.attachment_ids) && source.attachment_ids.length) {
        params.set("attachment_ids", source.attachment_ids.join(","));
      }
      if (source.cutoff_at) params.set("cutoff_at", source.cutoff_at);
      const res = await fetch(`../PhpFiles/Resident-End/get_resident_transaction_documents.php?${params.toString()}`, {
        credentials: "same-origin",
        headers: { "Accept": "application/json" }
      });
      const data = await res.json().catch(() => null);
      if (!res.ok || !data?.success) throw new Error(data?.message || "Unable to load document statuses.");
      const items = Array.isArray(data.items) ? data.items : [];
      docStatusCache.set(cacheKey, items);
      return items;
    }

    function renderExpandedStatusHtml(row) {
      const cacheKey = String(row.transaction_id || "");
      const docs = docStatusCache.get(cacheKey);
      if (!docs) return `<div class="text-muted small">Loading document statuses...</div>`;
      if (!docs.length) return `<div class="text-muted small">No document statuses available for this transaction.</div>`;
      const parentDeniedReason = String(getDeniedReason(row) || "").trim();

      const parsePurposeFromRemarks = (remarks) => {
        const raw = String(remarks || "").trim();
        const key = raw.toLowerCase();
        if (!key) return "General Verification";
        if (key.startsWith("sector:")) return "Sector Membership Verification";
        if (key === "2x2") return "2x2 Profile Photo Verification";
        if (key === "idsingle" || key === "idmerged" || key === "document") return "Proof of Residency Verification";
        if (key === "edit_request_name_id") return "Profile Edit Request (Name/ID)";
        if (key === "edit_request_civil_status") return "Profile Edit Request (Civil Status)";
        if (key === "edit_request_student_status") return "Profile Edit Request (Student Status)";
        if (key.startsWith("edit_request_supporting:")) {
          const subtype = raw.split(":")[1] || "";
          return `Profile Edit Supporting (${subtype || "General"})`;
        }
        return "General Verification";
      };

      const parseRejectReasonFromRemarks = (remarks) => {
        const raw = String(remarks || "").trim();
        if (!raw) return "";
        const match = raw.match(/(?:^|;)\s*reason\s*=\s*(.+)$/i);
        return match ? String(match[1] || "").trim() : "";
      };

      const rows = docs.map((doc) => {
        const name = escapeHtml(doc.document_type_name || "-");
        const purposeLabel = parsePurposeFromRemarks(doc.remarks);
        const purpose = escapeHtml(purposeLabel);
        const statusNameRaw = String(doc.status_name || "PendingReview");
        const statusClass = statusBadgeClass(statusNameRaw);
        const statusLabel = escapeHtml(displayStatusName(statusNameRaw));
        const statusHtml = `<span class="badge rounded-pill ${statusClass}">${statusLabel}</span>`;
        const statusDate = escapeHtml(formatDateTime(doc.status_changed_at || doc.uploaded_at));
        const isRejected = isDeniedStatus(statusNameRaw);
        const rejectReasonRaw = parseRejectReasonFromRemarks(doc.remarks);
        const sectorReason = purposeLabel === "Sector Membership Verification" ? parentDeniedReason : "";
        const rejectReason = isRejected
          ? escapeHtml(sectorReason || rejectReasonRaw || "No reason provided.")
          : "-";
        return `
          <tr>
            <td>${name}</td>
            <td>${purpose}</td>
            <td>${statusHtml}</td>
            <td>${statusDate}</td>
            <td>${rejectReason}</td>
          </tr>
        `;
      }).join("");

      return `
        <div class="table-responsive">
          <table class="txn-doc-table">
            <thead>
              <tr>
                <th>Document Type</th>
                <th>Purpose</th>
                <th>Status</th>
                <th>Date of Status Change</th>
                <th>Reason for Rejection</th>
              </tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>
        </div>
      `;
    }

    function renderTransactions() {
      const tbody = document.getElementById("txnTbody");
      const cards = document.getElementById("txnCards");
      const rows = getFilteredItems();

      if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">No transactions found.</td></tr>`;
        if (cards) cards.innerHTML = `<div class="text-center text-muted py-4">No transactions found.</div>`;
        return;
      }

      tbody.innerHTML = rows.map((row) => {
        const statusClass = statusBadgeClass(row.status_name);
        const descriptionText = buildTransactionDescription(row);
        const deniedReason = getDeniedReason(row);
        const reasonHtml = deniedReason ? `<div class="small text-danger mt-1"><strong>Reason:</strong> ${escapeHtml(deniedReason)}</div>` : "";
        const resubmitUrl = getResubmitUrl(row);
        const decisionDate = getStatusDecisionDate(row);
        const decisionDateHtml = decisionDate ? `<div class="small text-muted mt-1">${escapeHtml(decisionDate)}</div>` : "";
        const hasDocuments = Boolean(row?.has_documents);
        const actionHtml = `
          <div class="txn-action-btns">
            <button type="button" class="btn btn-sm btn-outline-secondary txn-view-btn" data-txn-id="${escapeHtml(row.transaction_id)}">View</button>
            ${resubmitUrl ? `<a class="btn btn-sm btn-outline-primary" href="${escapeHtml(resubmitUrl)}">Resubmit</a>` : ""}
          </div>
        `;
        const expandId = `txn-expand-${escapeHtml(row.transaction_id)}`;
        const isExpanded = hasDocuments && expandedRows.has(String(row.transaction_id));
        const expandedContent = isExpanded ? renderExpandedStatusHtml(row) : "";
        const rowClass = hasDocuments
          ? `txn-main-row has-docs${isExpanded ? " is-expanded" : ""}`
          : "txn-main-row";
        const hoverCueHtml = hasDocuments
          ? `
              <div class="txn-cue-inner" aria-hidden="true">
              <div class="txn-row-cue-wrap" aria-hidden="true">
              <div class="txn-hover-cue" aria-hidden="true">
                <span class="txn-hover-cue-label">Click to view document statuses</span>
                <i class="bi bi-chevron-down txn-cue-arrow"></i>
              </div>
              </div>
              </div>
            `
          : "";
        return `
          <tr class="${rowClass}" data-txn-id="${escapeHtml(row.transaction_id)}">
            <td>
              <div class="fw-semibold">${escapeHtml(row.transaction_id || "-")}</div>
            </td>
            <td class="small">${escapeHtml(descriptionText)}${reasonHtml}</td>
            <td class="small">${escapeHtml(formatDateTime(row.created_at))}</td>
            <td><span class="badge rounded-pill ${statusClass}">${escapeHtml(displayStatusName(row.status_name))}</span>${decisionDateHtml}</td>
            <td>${actionHtml}</td>
          </tr>
          <tr id="${expandId}" class="txn-expand-row" data-open="${isExpanded ? "1" : "0"}" ${hasDocuments ? `data-txn-id="${escapeHtml(row.transaction_id)}"` : ""}>
            <td colspan="5" class="txn-expand-cell">
              <div class="txn-expand-inner">${expandedContent}</div>
            </td>
          </tr>
          ${hasDocuments ? `
          <tr class="txn-cue-row has-docs${isExpanded ? " is-expanded" : ""}" data-txn-id="${escapeHtml(row.transaction_id)}">
            <td colspan="5" class="txn-cue-cell">${hoverCueHtml}</td>
          </tr>
          ` : ""}
        `;
      }).join("");

      if (cards) {
        cards.innerHTML = rows.map((row) => {
          const statusClass = statusBadgeClass(row.status_name);
          const descriptionText = buildTransactionDescription(row);
          const deniedReason = getDeniedReason(row);
          const reasonHtml = deniedReason ? `<div class="text-danger small mt-1"><strong>Reason:</strong> ${escapeHtml(deniedReason)}</div>` : "";
          const resubmitUrl = getResubmitUrl(row);
          const decisionDate = getStatusDecisionDate(row);
          const decisionDateHtml = decisionDate ? `<div class="small text-muted mt-1">${escapeHtml(decisionDate)}</div>` : "";
          return `
            <article class="txn-card">
              <div class="txn-meta">
                <div>
                  <div class="txn-label">Transaction</div>
                  <div class="txn-value fw-semibold">${escapeHtml(row.transaction_id || "-")}</div>
                </div>
                <div>
                  <div class="txn-label">Description</div>
                  <div class="txn-value">${escapeHtml(descriptionText)}${reasonHtml}</div>
                </div>
                <div>
                  <div class="txn-label">Submitted Date</div>
                  <div class="txn-value">${escapeHtml(formatDateTime(row.created_at))}</div>
                </div>
                <div>
                  <div class="txn-label">Status</div>
                  <div class="txn-value"><span class="badge rounded-pill ${statusClass}">${escapeHtml(displayStatusName(row.status_name))}</span>${decisionDateHtml}</div>
                  <button type="button" class="btn btn-sm btn-outline-secondary mt-2 txn-view-btn" data-txn-id="${escapeHtml(row.transaction_id)}">View</button>
                  ${resubmitUrl ? `<a class="btn btn-sm btn-outline-primary mt-2" href="${escapeHtml(resubmitUrl)}">Resubmit</a>` : ""}
                </div>
              </div>
            </article>
          `;
        }).join("");
      }

      bindViewButtons();
      document.querySelectorAll(".txn-expand-row").forEach((rowEl) => {
        if (rowEl.getAttribute("data-open") === "1") {
          requestAnimationFrame(() => rowEl.classList.add("is-open"));
        } else {
          rowEl.classList.remove("is-open");
        }
      });
    }

    async function loadTransactionDocuments(row) {
      const source = resolveDocSource(row);
      if (!source) return [];
      const params = new URLSearchParams({
        source_type: source.source_type,
        source_id: source.source_id
      });
      if (source.purpose) params.set("purpose", source.purpose);
      if (Array.isArray(source.attachment_ids) && source.attachment_ids.length) {
        params.set("attachment_ids", source.attachment_ids.join(","));
      }
      if (source.cutoff_at) params.set("cutoff_at", source.cutoff_at);
      const res = await fetch(`../PhpFiles/Resident-End/get_resident_transaction_documents.php?${params.toString()}`, {
        credentials: "same-origin",
        headers: { "Accept": "application/json" }
      });
      const data = await res.json().catch(() => null);
      if (!res.ok || !data?.success) {
        throw new Error(data?.message || "Unable to load uploaded documents.");
      }
      return Array.isArray(data.items) ? data.items : [];
    }

    async function openTransactionView(row) {
      const titleEl = document.getElementById("txnViewTitle");
      const typeEl = document.getElementById("txnViewType");
      const descEl = document.getElementById("txnViewDescription");
      const dateEl = document.getElementById("txnViewDate");
      const statusEl = document.getElementById("txnViewStatus");
      const reasonWrap = document.getElementById("txnViewReasonWrap");
      const docsWrap = document.getElementById("txnViewDocsWrap");
      const docsBody = document.getElementById("txnViewDocsTbody");
      const noDocsNote = document.getElementById("txnViewNoDocsNote");
      const resubmitBtn = document.getElementById("txnViewResubmitBtn");
      txnViewDocsCurrent = [];

      titleEl.textContent = row.transaction_id || "-";
      typeEl.textContent = row.transaction_type || "-";
      descEl.textContent = buildTransactionDescription(row);
      dateEl.textContent = formatDateTime(row.created_at);
      statusEl.innerHTML = `<span class="badge rounded-pill ${statusBadgeClass(row.status_name)}">${escapeHtml(displayStatusName(row.status_name))}</span>`;

      const deniedReason = getDeniedReason(row);
      reasonWrap.classList.toggle("d-none", !deniedReason);
      reasonWrap.textContent = deniedReason ? `Reason: ${deniedReason}` : "";

      const resubmitUrl = getResubmitUrl(row);
      if (resubmitUrl) {
        resubmitBtn.classList.remove("d-none");
        resubmitBtn.href = resubmitUrl;
      } else {
        resubmitBtn.classList.add("d-none");
        resubmitBtn.href = "#";
      }

      if (isDeniedStatus(row.status_name)) {
        docsWrap.classList.add("d-none");
        noDocsNote.classList.remove("d-none");
      } else {
        noDocsNote.classList.add("d-none");
        docsWrap.classList.remove("d-none");
        docsBody.innerHTML = `<tr><td colspan="4" class="text-muted text-center py-3">Loading documents...</td></tr>`;

        try {
          const docs = await loadTransactionDocuments(row);
          txnViewDocsCurrent = docs;
          if (!docs.length) {
            docsBody.innerHTML = `<tr><td colspan="4" class="text-muted text-center py-3">No uploaded documents found.</td></tr>`;
          } else {
            docsBody.innerHTML = docs.map((doc, idx) => {
              const fileLink = doc.file_url
                ? `<button type="button" class="btn btn-sm btn-outline-primary txn-doc-view-btn" data-doc-idx="${escapeHtml(String(idx))}">View</button>`
                : `<span class="text-muted">-</span>`;
              return `
                <tr>
                  <td>${escapeHtml(doc.document_type_name || "-")}</td>
                  <td><span class="badge rounded-pill ${statusBadgeClass(doc.status_name || "-")}">${escapeHtml(displayStatusName(doc.status_name || "-"))}</span></td>
                  <td>${escapeHtml(formatDateTime(doc.uploaded_at))}</td>
                  <td>${fileLink}</td>
                </tr>
              `;
            }).join("");
          }
        } catch (err) {
          docsBody.innerHTML = `<tr><td colspan="4" class="text-danger text-center py-3">${escapeHtml(err?.message || "Unable to load documents.")}</td></tr>`;
          txnViewDocsCurrent = [];
        }
      }

      if (!txnViewModalInstance && window.bootstrap?.Modal) {
        txnViewModalInstance = new bootstrap.Modal(document.getElementById("txnViewModal"));
      }
      txnViewModalInstance?.show();
    }

    async function loadTransactions() {
      const tbody = document.getElementById("txnTbody");
      const cards = document.getElementById("txnCards");
      const refreshBtn = document.getElementById("txnRefreshBtn");
      if (refreshBtn) refreshBtn.classList.add("is-loading");
      tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">Loading transactions...</td></tr>`;
      if (cards) cards.innerHTML = `<div class="text-center text-muted py-4">Loading transactions...</div>`;

      try {
        const res = await fetch(`../PhpFiles/Resident-End/get_resident_transactions.php?limit=500&offset=0`, {
          credentials: "same-origin",
          headers: { "Accept": "application/json" }
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data?.success) {
          throw new Error(data?.message || "Failed to load transactions.");
        }
        allTransactions = Array.isArray(data.items) ? data.items : [];
        updatePendingSummary();
        renderTransactions();
      } catch (err) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">${escapeHtml(err?.message || "Unable to load transactions.")}</td></tr>`;
        if (cards) cards.innerHTML = `<div class="text-center text-danger py-4">${escapeHtml(err?.message || "Unable to load transactions.")}</div>`;
        const wrap = document.getElementById("txnPendingSummary");
        if (wrap) wrap.classList.add("d-none");
      } finally {
        if (refreshBtn) refreshBtn.classList.remove("is-loading");
      }
    }

    document.addEventListener("DOMContentLoaded", () => {
      const burgerBtn = document.getElementById("btn-burger");
      const sidebar = document.getElementById("div-sidebarWrapper");
      if (burgerBtn && sidebar) {
        burgerBtn.addEventListener("click", () => {
          sidebar.classList.toggle("show");
        });
      }

      document.querySelectorAll(".txn-tab").forEach((btn) => {
        btn.addEventListener("click", () => {
          document.querySelectorAll(".txn-tab").forEach((b) => b.classList.remove("active"));
          btn.classList.add("active");
          activeTab = btn.getAttribute("data-tab") || "all";
          renderTransactions();
        });
      });

      document.getElementById("txnSearch").addEventListener("input", renderTransactions);
      document.getElementById("txnTypeFilter").addEventListener("change", renderTransactions);
      document.getElementById("txnStatusFilter").addEventListener("change", renderTransactions);
      document.getElementById("txnDateFrom").addEventListener("change", renderTransactions);
      document.getElementById("txnDateTo").addEventListener("change", renderTransactions);

      document.getElementById("txnFilterApply").addEventListener("click", renderTransactions);
      document.getElementById("txnFilterReset").addEventListener("click", () => {
        document.getElementById("txnTypeFilter").value = "";
        document.getElementById("txnStatusFilter").value = "";
        document.getElementById("txnDateFrom").value = "";
        document.getElementById("txnDateTo").value = "";
        renderTransactions();
      });

      document.getElementById("txnRefreshBtn").addEventListener("click", loadTransactions);

      const txnTbody = document.getElementById("txnTbody");
      if (txnTbody) {
        const closeTimers = new Map();
        const DOCS_CLOSE_PHASE_MS = 320;

        const getTxnGroupRows = (txnId) => {
          const rows = Array.from(txnTbody.querySelectorAll("tr[data-txn-id]"));
          const mainRow = rows.find((el) => el.classList.contains("txn-main-row") && String(el.getAttribute("data-txn-id") || "") === txnId) || null;
          const cueRow = rows.find((el) => el.classList.contains("txn-cue-row") && String(el.getAttribute("data-txn-id") || "") === txnId) || null;
          const expandRow = rows.find((el) => el.classList.contains("txn-expand-row") && String(el.getAttribute("data-txn-id") || "") === txnId) || null;
          return { mainRow, cueRow, expandRow };
        };

        const clearCloseTimer = (txnId) => {
          const timer = closeTimers.get(txnId);
          if (timer) {
            clearTimeout(timer);
            closeTimers.delete(txnId);
          }
        };

        const setTxnGroupHover = (txnId, enabled) => {
          if (!txnId) return;
          txnTbody.querySelectorAll(`tr[data-txn-id="${txnId}"]`).forEach((el) => {
            el.classList.toggle("txn-hover-group", enabled);
          });
        };

        const collapseTxnGroupWithAnimation = (txnId) => {
          if (!txnId) return;
          clearCloseTimer(txnId);
          expandedRows.delete(txnId);
          const { mainRow, cueRow, expandRow } = getTxnGroupRows(txnId);

          // Phase 1: close document statuses first.
          if (expandRow) {
            expandRow.classList.remove("is-open");
            expandRow.setAttribute("data-open", "0");
          }

          // Phase 2: then collapse the "Click to view" row.
          const timer = setTimeout(() => {
            if (expandedRows.has(txnId)) return;
            mainRow?.classList.remove("is-expanded");
            cueRow?.classList.remove("is-expanded");
            closeTimers.delete(txnId);
          }, DOCS_CLOSE_PHASE_MS);
          closeTimers.set(txnId, timer);
        };

        txnTbody.addEventListener("mouseover", (e) => {
          const rowEl = e.target.closest("tr[data-txn-id]");
          if (!rowEl) return;
          const txnId = String(rowEl.getAttribute("data-txn-id") || "");
          if (!txnId) return;
          setTxnGroupHover(txnId, true);
        });

        txnTbody.addEventListener("mouseout", (e) => {
          const rowEl = e.target.closest("tr[data-txn-id]");
          if (!rowEl) return;
          const txnId = String(rowEl.getAttribute("data-txn-id") || "");
          if (!txnId) return;

          const toEl = e.relatedTarget instanceof Element ? e.relatedTarget.closest("tr[data-txn-id]") : null;
          const toId = toEl ? String(toEl.getAttribute("data-txn-id") || "") : "";
          if (toId === txnId) return;

          setTxnGroupHover(txnId, false);
          if (expandedRows.has(txnId)) {
            collapseTxnGroupWithAnimation(txnId);
          }
        });

        txnTbody.addEventListener("click", async (e) => {
          const ignoreClick = e.target.closest("a,button");
          if (ignoreClick) return;

          const rowEl = e.target.closest(".txn-main-row, .txn-cue-row");
          if (!rowEl) return;
          if (!rowEl.classList.contains("has-docs")) return;
          const txnId = String(rowEl.getAttribute("data-txn-id") || "");
          if (!txnId) return;

          if (expandedRows.has(txnId)) {
            collapseTxnGroupWithAnimation(txnId);
            return;
          }

          clearCloseTimer(txnId);
          expandedRows.clear();
          expandedRows.add(txnId);
          renderTransactions();

          const row = allTransactions.find((item) => String(item.transaction_id) === txnId);
          if (!row) return;
          try {
            await fetchDocStatusesForRow(row);
          } catch (err) {
            const key = String(row.transaction_id || "");
            docStatusCache.set(key, []);
          }
          renderTransactions();
        });
      }

      const txnViewDocsTbody = document.getElementById("txnViewDocsTbody");
      txnViewDocsTbody?.addEventListener("click", (event) => {
        const btn = event.target?.closest?.(".txn-doc-view-btn");
        if (!btn) return;
        const idx = Number(btn.getAttribute("data-doc-idx"));
        if (!Number.isInteger(idx) || idx < 0 || idx >= txnViewDocsCurrent.length) return;
        openTxnDocViewer(txnViewDocsCurrent[idx]);
      });

      loadTransactions();
    });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../JS-Script-Files/Resident-End/dateFieldModal.js"></script>
</body>
</html>
