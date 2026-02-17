<?php
require_once __DIR__ . "/includes/resident_access_guard.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="/Images/favicon_sanjose.png?v=20260211">
  <title>Resident Transactions - Barangay San Jose</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/residentDashboard.css">
  <style>
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
    .audit-table td:nth-child(1) { width: 30%; }
    .audit-table th:nth-child(2),
    .audit-table td:nth-child(2) { width: 34%; }
    .audit-table th:nth-child(3),
    .audit-table td:nth-child(3) { width: 22%; }
    .audit-table th:nth-child(4),
    .audit-table td:nth-child(4) { width: 14%; }
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
    <?php include 'includes/resident_sidebar.php'; ?>

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
                <th>Timestamp/Date</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="txnTbody">
              <tr>
                <td colspan="4" class="text-center text-muted py-4">Loading transactions...</td>
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

  <script>
    let allTransactions = [];
    let activeTab = "all";

    function statusBadgeClass(statusName) {
      const key = String(statusName || "").toLowerCase();
      if (key.includes("approved") || key.includes("verified")) return "bg-success-subtle text-success";
      if (key.includes("denied") || key.includes("rejected")) return "bg-danger-subtle text-danger";
      return "bg-warning-subtle text-warning-emphasis";
    }

    function formatDateTime(value) {
      if (!value) return "-";
      const d = new Date(value.replace(" ", "T"));
      if (Number.isNaN(d.getTime())) return value;
      return d.toLocaleString();
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
      if (activeTab === "denied") return key.includes("denied") || key.includes("rejected");
      if (activeTab === "pending") return key.includes("pending") || key.includes("review");
      return true;
    }

    function getDeniedReason(row) {
      const statusKey = String(row?.status_name || "").toLowerCase();
      if (!(statusKey.includes("denied") || statusKey.includes("rejected"))) return "";
      const reason = String(row?.metadata?.admin_notes || "").trim();
      return reason;
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

    function renderTransactions() {
      const tbody = document.getElementById("txnTbody");
      const cards = document.getElementById("txnCards");
      const rows = getFilteredItems();

      if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-4">No transactions found.</td></tr>`;
        if (cards) cards.innerHTML = `<div class="text-center text-muted py-4">No transactions found.</div>`;
        return;
      }

      tbody.innerHTML = rows.map((row) => {
        const statusClass = statusBadgeClass(row.status_name);
        const descriptionText = String(row.description || "").trim() || "No description provided.";
        const deniedReason = getDeniedReason(row);
        const reasonHtml = deniedReason
          ? `<div class="small text-danger mt-1"><strong>Reason:</strong> ${escapeHtml(deniedReason)}</div>`
          : "";
        return `
          <tr>
            <td>
              <div class="fw-semibold">${escapeHtml(row.title || row.transaction_type)}</div>
              <div class="small text-muted">${escapeHtml(row.transaction_type)}</div>
            </td>
            <td class="small">${escapeHtml(descriptionText)}${reasonHtml}</td>
            <td class="small">${escapeHtml(formatDateTime(row.created_at))}</td>
            <td><span class="badge rounded-pill ${statusClass}">${escapeHtml(row.status_name || "Pending")}</span></td>
          </tr>
        `;
      }).join("");

      if (cards) {
        cards.innerHTML = rows.map((row) => {
          const statusClass = statusBadgeClass(row.status_name);
          const descriptionText = String(row.description || "").trim() || "No description provided.";
          const deniedReason = getDeniedReason(row);
          const reasonHtml = deniedReason
            ? `<div class="text-danger small mt-1"><strong>Reason:</strong> ${escapeHtml(deniedReason)}</div>`
            : "";
          return `
            <article class="txn-card">
              <div class="txn-meta">
                <div>
                  <div class="txn-label">Transaction</div>
                  <div class="txn-value fw-semibold">${escapeHtml(row.title || row.transaction_type)}</div>
                  <div class="small text-muted">${escapeHtml(row.transaction_type)}</div>
                </div>
                <div>
                  <div class="txn-label">Description</div>
                  <div class="txn-value">${escapeHtml(descriptionText)}${reasonHtml}</div>
                </div>
                <div>
                  <div class="txn-label">Timestamp/Date</div>
                  <div class="txn-value">${escapeHtml(formatDateTime(row.created_at))}</div>
                </div>
                <div>
                  <div class="txn-label">Status</div>
                  <div class="txn-value"><span class="badge rounded-pill ${statusClass}">${escapeHtml(row.status_name || "Pending")}</span></div>
                </div>
              </div>
            </article>
          `;
        }).join("");
      }
    }

    async function loadTransactions() {
      const tbody = document.getElementById("txnTbody");
      const cards = document.getElementById("txnCards");
      const refreshBtn = document.getElementById("txnRefreshBtn");
      if (refreshBtn) refreshBtn.classList.add("is-loading");
      tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-4">Loading transactions...</td></tr>`;
      if (cards) cards.innerHTML = `<div class="text-center text-muted py-4">Loading transactions...</div>`;

      try {
        const res = await fetch(`../PhpFiles/Resident-End/get_resident_transactions.php?limit=500&offset=0`, {
          credentials: "same-origin",
          headers: { "Accept": "application/json" }
        });
        const data = await res.json();
        if (!res.ok || !data?.success) {
          throw new Error(data?.message || "Failed to load transactions.");
        }
        allTransactions = Array.isArray(data.items) ? data.items : [];
        renderTransactions();
      } catch (err) {
        tbody.innerHTML = `<tr><td colspan="4" class="text-center text-danger py-4">${escapeHtml(err?.message || "Unable to load transactions.")}</td></tr>`;
        if (cards) cards.innerHTML = `<div class="text-center text-danger py-4">${escapeHtml(err?.message || "Unable to load transactions.")}</div>`;
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
      loadTransactions();
    });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
