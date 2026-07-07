<?php
$allowUnregistered = false;
require_once __DIR__ . '/includes/resident_access_guard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="../Images/favicon_sanjose.png?v=20260211">
  <title>Appointment Tracker - Barangay San Jose</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/residentDashboard.css">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260321-2">
  <style>
    #div-sidebarWrapper {
      width: 280px;
      min-width: 0;
      min-height: 100vh;
      height: 100vh;
      overflow: hidden;
    }
    #img-sidebarAvatar {
      width: 90px;
      height: 90px;
    }
    @media (min-width: 769px) {
      #div-sidebarWrapper {
        width: 280px;
        min-height: 100vh;
      }
    }
    .tracker-shell { border-color: #f1e1cf !important; }
    .tracker-title {
      font-family: 'Charis SIL Bold', serif;
      color: #DE710C;
      font-size: clamp(2rem, 4.4vw, 3rem);
      line-height: 1.1;
      margin: 0 0 0.65rem 0;
    }
    .tracker-shell .admin-list-tabs {
      gap: 12px;
      overflow: visible;
    }
    .tracker-shell .status-filter-btn {
      border-radius: 10px;
      border-width: 1px;
      overflow: visible;
    }
    .tracker-shell .btn-icon.admin-filter,
    .tracker-shell .btn-icon.admin-columns,
    .tracker-shell .btn-icon.admin-refresh {
      outline: none;
      box-shadow: none;
    }
    .tracker-shell .btn-icon.admin-filter {
      border-color: rgba(108, 117, 125, 0.28);
      color: #495057;
      background: linear-gradient(180deg, #fbfcfd 0%, #f3f6f8 100%);
      font-weight: 700;
      letter-spacing: 0.2px;
      transition: transform 120ms ease, box-shadow 120ms ease, background-color 120ms ease, border-color 120ms ease;
    }
    .tracker-shell .btn-icon.admin-filter:hover {
      border-color: rgba(73, 80, 87, 0.45);
      color: #343a40;
      background: linear-gradient(180deg, #f8fafb 0%, #edf1f4 100%);
      box-shadow: 0 10px 18px rgba(73, 80, 87, 0.10);
      transform: translateY(-1px);
    }
    .tracker-shell .btn-icon.admin-filter:active {
      transform: translateY(0);
      box-shadow: 0 6px 12px rgba(73, 80, 87, 0.10);
    }
    .tracker-shell .btn-icon.admin-filter:focus,
    .tracker-shell .btn-icon.admin-filter:focus-visible {
      outline: none;
      border-color: rgba(108, 117, 125, 0.28);
      box-shadow: 0 0 0 .2rem rgba(108, 117, 125, 0.18);
    }
    .tracker-shell .btn-icon.admin-columns {
      border-color: rgba(13, 110, 253, 0.25);
      color: #0d47a1;
      background: linear-gradient(180deg, #f4f8ff 0%, #eef5ff 100%);
      font-weight: 700;
      letter-spacing: 0.2px;
      transition: transform 120ms ease, box-shadow 120ms ease, background-color 120ms ease, border-color 120ms ease;
    }
    .tracker-shell .btn-icon.admin-columns:hover {
      border-color: rgba(13, 110, 253, 0.45);
      color: #0a3a84;
      background: linear-gradient(180deg, #eff6ff 0%, #e7f0ff 100%);
      box-shadow: 0 10px 18px rgba(13, 110, 253, 0.10);
      transform: translateY(-1px);
    }
    .tracker-shell .btn-icon.admin-columns:active {
      transform: translateY(0);
      box-shadow: 0 6px 12px rgba(13, 110, 253, 0.10);
    }
    .tracker-shell .btn-icon.admin-columns:focus,
    .tracker-shell .btn-icon.admin-columns:focus-visible {
      outline: none;
      border-color: rgba(13, 110, 253, 0.25);
      box-shadow: 0 0 0 .2rem rgba(13, 110, 253, 0.18);
    }
    .tracker-shell .btn-icon.admin-refresh {
      border-color: rgba(254, 153, 60, 0.45);
      color: #b85b00;
      background: linear-gradient(180deg, #fffaf4 0%, #fff3e4 100%);
      font-weight: 700;
      letter-spacing: 0.2px;
      transition: transform 120ms ease, box-shadow 120ms ease, background-color 120ms ease, border-color 120ms ease;
    }
    .tracker-shell .btn-icon.admin-refresh:hover {
      border-color: rgba(254, 153, 60, 0.7);
      color: #a04f00;
      background: linear-gradient(180deg, #fff6ec 0%, #ffe9d1 100%);
      box-shadow: 0 10px 18px rgba(222, 113, 12, 0.14);
      transform: translateY(-1px);
    }
    .tracker-shell .btn-icon.admin-refresh:active {
      transform: translateY(0);
      box-shadow: 0 6px 12px rgba(222, 113, 12, 0.12);
    }
    .tracker-shell .btn-icon.admin-refresh:focus,
    .tracker-shell .btn-icon.admin-refresh:focus-visible {
      outline: none;
      border-color: rgba(254, 153, 60, 0.45);
      box-shadow: 0 0 0 .2rem rgba(254, 153, 60, 0.25);
    }
    .tracker-shell .btn-icon.admin-refresh.is-loading i {
      animation: adminSpin 900ms linear infinite;
    }
    .tracker-shell.resident-masterlist-shell .status-filter-btn[data-filter="ALL"],
    .tracker-shell.resident-masterlist-shell .status-filter-btn[data-filter=""] {
      color: #0d6efd;
      border-color: #0d6efd;
      background: #fff;
    }
    .tracker-shell.resident-masterlist-shell .status-filter-btn:not([data-filter="ALL"]):not([data-filter=""]) {
      color: #495057;
      border-color: #495057;
      background: #fff;
    }
    .tracker-shell.resident-masterlist-shell .status-filter-btn:not([data-filter="ALL"]):not([data-filter=""]):hover,
    .tracker-shell.resident-masterlist-shell .status-filter-btn:not([data-filter="ALL"]):not([data-filter=""]):focus-visible {
      color: #343a40;
      border-color: #343a40;
      background: #f8f9fa;
    }
    .tracker-shell.resident-masterlist-shell .status-filter-btn[data-filter="ALL"].active,
    .tracker-shell.resident-masterlist-shell .status-filter-btn[data-filter=""].active {
      color: #fff !important;
      background-color: #0d6efd !important;
      border-color: #0d6efd !important;
      font-weight: 700;
    }
    .tracker-shell.resident-masterlist-shell .status-filter-btn:not([data-filter="ALL"]):not([data-filter=""]).active {
      color: #fff !important;
      background-color: #495057 !important;
      border-color: #495057 !important;
      font-weight: 700;
    }
    .tracker-shell .status-pill.info,
    .tracker-shell .status-pill.rescheduled,
    .tracker-shell .status-pill.completed,
    #appointmentViewModal .status-pill.info,
    #appointmentViewModal .status-pill.completed,
    #appointmentViewModal .status-pill.rescheduled {
      color: #1d4ed8;
      background: #dbeafe;
      border: 2px solid #bfdbfe;
    }
    .tracker-shell .status-pill.completed,
    #appointmentViewModal .status-pill.completed {
      color: #0f5132;
      background: #d1e7dd;
      border-color: #badbcc;
    }
    .tracker-shell .status-pill.denied,
    .tracker-shell .status-pill.archived,
    #appointmentViewModal .status-pill.denied,
    #appointmentViewModal .status-pill.archived {
      color: #8f2932;
      background: #e8cfd3;
      border: 2px solid #e0bcc2;
    }
    .tracker-table-responsive {
      display: block;
    }
    #appointmentTable {
      table-layout: fixed;
      min-width: 980px;
    }
    #appointmentTable th:first-child,
    #appointmentTable td:first-child {
      width: 15%;
      white-space: nowrap;
    }
    #appointmentTable th:nth-child(2),
    #appointmentTable td:nth-child(2) {
      width: 18%;
      white-space: nowrap;
    }
    #appointmentTable th:nth-child(3),
    #appointmentTable td:nth-child(3),
    #appointmentTable th:nth-child(4),
    #appointmentTable td:nth-child(4) {
      width: 21%;
      white-space: nowrap;
    }
    #appointmentTable th:nth-child(5),
    #appointmentTable td:nth-child(5) {
      width: 14%;
      white-space: nowrap;
    }
    #appointmentTable th:nth-child(6),
    #appointmentTable td:nth-child(6) {
      width: 11%;
      white-space: nowrap;
      text-align: left;
    }
    #appointmentTable td:nth-child(6) {
      padding-left: 1rem;
    }
    .tracker-shell #appointmentPagination .page-link {
      color: #495057;
    }
    .tracker-shell #appointmentPagination .page-item.active .page-link {
      background-color: #0d6efd;
      border-color: #0d6efd;
      color: #fff;
    }
    .tracker-card {
      border: 1px solid #eceff3;
      border-radius: 12px;
      padding: 0.85rem 0.9rem;
      background: #fff;
      margin-bottom: 0.75rem;
    }
    .tracker-label {
      font-size: 0.78rem;
      color: #495057;
      text-transform: uppercase;
      letter-spacing: .02em;
      font-weight: 800;
    }
    .tracker-value {
      font-size: 0.96rem;
      color: #212529;
      word-break: break-word;
      white-space: normal;
    }
    #appointmentCards { display: none; }
    @media (max-width: 991.98px) {
      .tracker-shell .admin-list-toolbar {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
      }
      .tracker-shell .admin-list-tabs,
      .tracker-shell .admin-list-actions {
        width: 100%;
      }
      .tracker-shell .admin-list-tabs {
        overflow-x: auto;
        padding-bottom: 4px;
      }
      .tracker-shell .admin-list-actions {
        margin-left: 0;
        justify-content: flex-start;
        overflow-x: auto;
        padding-bottom: 2px;
      }
      .tracker-shell .admin-search {
        min-width: 0;
        max-width: none;
        width: 100%;
      }
    }
    @media (max-width: 767.98px) {
      .tracker-table-responsive { display: none; }
      #appointmentCards { display: block; }
    }
  </style>
</head>
<body>
  <div class="d-flex" style="min-height: 100vh;">
    <?php include __DIR__ . '/includes/resident_sidebar.php'; ?>

    <main id="div-mainDisplay" class="flex-grow-1 p-4 p-md-5 bg-light">
      <h2 class="tracker-title">Appointment Tracker</h2>
      <hr class="mt-0 mb-3">

      <div class="bg-white p-4 rounded-4 shadow-sm border tracker-shell resident-masterlist-shell">
        <div class="admin-list-toolbar mb-3 pt-2 flex-wrap">
          <div class="admin-list-tabs">
            <button type="button" class="btn btn-outline-primary btn-sm status-filter-btn tracker-tab active" data-tab="all" data-filter="ALL">All</button>
            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn tracker-tab fw-semibold" data-tab="approved" data-filter="Approved">Confirmed</button>
            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn tracker-tab fw-semibold" data-tab="completed" data-filter="Completed">Completed</button>
            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn tracker-tab fw-semibold" data-tab="rescheduled" data-filter="Rescheduled">Rescheduled</button>
            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn tracker-tab fw-semibold" data-tab="archived" data-filter="Denied">Denied</button>
          </div>
          <div class="admin-list-actions">
            <div class="input-group admin-search">
              <input id="appointmentSearch" class="form-control" placeholder="Search appointments..." />
              <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
            </div>
            <button id="appointmentFilterBtn" class="btn btn-outline-secondary btn-icon admin-filter" type="button" title="Filter" aria-label="Filter" data-bs-toggle="modal" data-bs-target="#appointmentFilterModal">
              <i class="fas fa-filter"></i>
            </button>
            <button id="appointmentColumnsBtn" class="btn btn-outline-secondary btn-icon admin-columns" type="button" title="Columns" aria-label="Columns" data-bs-toggle="modal" data-bs-target="#appointmentColumnsModal">
              <i class="fa-solid fa-sliders"></i>
            </button>
            <button id="appointmentRefreshBtn" class="btn btn-outline-secondary btn-icon admin-refresh" type="button" title="Refresh appointment tracker" aria-label="Refresh appointment tracker">
              <i class="fa-solid fa-arrows-rotate"></i>
            </button>
          </div>
        </div>

        <div class="table-responsive compact-admin-table-shell tracker-table-responsive">
          <table id="appointmentTable" class="table align-middle compact-admin-table mb-0">
            <thead>
              <tr>
                <th>Appointment ID</th>
                <th>Subject</th>
                <th>Preferred Schedule</th>
                <th>Confirmed Schedule</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="appointmentTbody">
              <tr><td colspan="6" class="text-center text-muted py-4">Loading appointments...</td></tr>
            </tbody>
          </table>
        </div>

        <div class="resident-table-footer mt-3 d-flex flex-wrap justify-content-between align-items-center gap-3 tracker-table-responsive">
          <div class="d-flex align-items-center gap-2">
            <label for="appointmentEntriesPerPageInput" class="small text-muted mb-0">Entries</label>
            <input id="appointmentEntriesPerPageInput" type="number" min="1" step="1" value="20" class="form-control form-control-sm resident-entries-input" />
          </div>
          <nav aria-label="Appointment pagination">
            <ul class="pagination pagination-sm mb-0" id="appointmentPagination"></ul>
          </nav>
        </div>

        <div id="appointmentCards" class="mt-2">
          <div class="text-center text-muted py-4">Loading appointments...</div>
        </div>
      </div>
    </main>
  </div>

  <div class="modal fade" id="appointmentFilterModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Filter Appointments</h5>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label small text-muted mb-1">Status</label>
              <select class="form-select" id="appointmentStatusFilter">
                <option value="">All Status</option>
                <option value="Approved">Confirmed</option>
                <option value="Completed">Completed</option>
                <option value="Rescheduled">Rescheduled</option>
                <option value="Denied">Denied</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small text-muted mb-1">Date From</label>
              <input type="date" class="form-control" id="appointmentDateFrom" />
            </div>
            <div class="col-12">
              <label class="form-label small text-muted mb-1">Date To</label>
              <input type="date" class="form-control" id="appointmentDateTo" />
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" id="appointmentFilterReset">Reset</button>
          <button type="button" class="btn btn-primary" id="appointmentFilterApply" data-bs-dismiss="modal">Apply</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="appointmentColumnsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Columns</h5>
        </div>
        <div class="modal-body">
          <div class="row g-2" id="appointmentColumnsList"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" id="appointmentColumnsReset">Reset</button>
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="appointmentViewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="appointmentViewTitle">Appointment Details</h5>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><strong>Appointment ID:</strong><div id="appointmentViewId" class="text-muted"></div></div>
            <div class="col-md-6"><strong>Status:</strong><div id="appointmentViewStatus"></div></div>
            <div class="col-md-6"><strong>Subject:</strong><div id="appointmentViewSubject" class="text-muted"></div></div>
            <div class="col-md-6"><strong>Council Member:</strong><div id="appointmentViewOfficial" class="text-muted"></div></div>
            <div class="col-md-6"><strong>Preferred Schedule:</strong><div id="appointmentViewPreferred" class="text-muted"></div></div>
            <div class="col-md-6"><strong>Confirmed Schedule:</strong><div id="appointmentViewConfirmed" class="text-muted"></div></div>
            <div class="col-md-6"><strong>Meeting Location:</strong><div id="appointmentViewMeetingLocation" class="text-muted"></div></div>
            <div class="col-md-6"><strong>Requested At:</strong><div id="appointmentViewRequested" class="text-muted"></div></div>
            <div class="col-md-6"><strong>Reviewed At:</strong><div id="appointmentViewReviewed" class="text-muted"></div></div>
            <div class="col-12"><strong>Purpose:</strong><div id="appointmentViewPurpose" class="text-muted"></div></div>
            <div class="col-12"><strong>Office Remarks:</strong><div id="appointmentViewRemarks" class="text-muted"></div></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    let allAppointments = [];
    let activeTab = "all";
    let appointmentViewModal = null;
    let appointmentCurrentPage = 1;
    let appointmentEntriesPerPage = 20;

    function escapeHtml(value) {
      return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
    }

    function formatDateTime(value, fallback = "-") {
      const text = String(value || "").trim();
      if (!text) return fallback;
      const date = new Date(text.replace(" ", "T"));
      if (Number.isNaN(date.getTime())) return text;
      return date.toLocaleString([], {
        year: "numeric",
        month: "short",
        day: "2-digit",
        hour: "numeric",
        minute: "2-digit"
      });
    }

    function statusBadgeClass(value) {
      const normalized = String(value || "").toLowerCase();
      if (normalized.includes("complete") || normalized.includes("done")) return "completed";
      if (normalized.includes("confirm") || normalized.includes("approve")) return "approved";
      if (normalized.includes("resched")) return "rescheduled";
      if (normalized.includes("deny") || normalized.includes("denied") || normalized.includes("reject")) return "denied";
      return "pending";
    }

    function appointmentStatusLabel(value) {
      const normalized = String(value || "").toLowerCase();
      if (normalized.includes("complete") || normalized.includes("done")) return "Completed";
      if (normalized.includes("resched")) return "Rescheduled";
      if (normalized.includes("confirm") || normalized.includes("approve")) return "Confirmed";
      if (normalized.includes("deny") || normalized.includes("denied") || normalized.includes("reject")) return "Denied";
      return "Pending";
    }

    function confirmedScheduleDisplay(item) {
      const confirmedValue = String(item?.confirmed_schedule_timestamp || "").trim();
      if (confirmedValue) {
        return formatDateTime(confirmedValue);
      }

      const preferredValue = String(item?.preferred_schedule_timestamp || "").trim();
      if (preferredValue) {
        return formatDateTime(preferredValue, "Same as requested schedule");
      }

      return appointmentStatusLabel(item?.status_name) === "Confirmed"
        ? "Confirmed upon submission"
        : "To be scheduled";
    }

    function reviewedTimestampDisplay(item) {
      const reviewValue = String(item?.review_timestamp || "").trim();
      if (reviewValue) {
        return formatDateTime(reviewValue);
      }

      return appointmentStatusLabel(item?.status_name) === "Confirmed"
        ? "Confirmed upon submission"
        : "Not reviewed yet";
    }

    function paginateRows(rows, currentPage, perPage) {
      const safePerPage = Math.max(1, Number.parseInt(perPage, 10) || 20);
      const totalPages = Math.max(1, Math.ceil(rows.length / safePerPage));
      const page = Math.min(Math.max(1, currentPage), totalPages);
      const start = (page - 1) * safePerPage;
      return {
        page,
        totalPages,
        items: rows.slice(start, start + safePerPage)
      };
    }

    function renderAppointmentPagination(totalPages) {
      const pagination = document.getElementById("appointmentPagination");
      if (!pagination) return;
      if (totalPages <= 1) {
        pagination.innerHTML = "";
        return;
      }

      const items = [];
      items.push(`
        <li class="page-item ${appointmentCurrentPage <= 1 ? "disabled" : ""}">
          <button type="button" class="page-link" data-page="${appointmentCurrentPage - 1}">Prev</button>
        </li>
      `);
      for (let page = 1; page <= totalPages; page += 1) {
        items.push(`
          <li class="page-item ${page === appointmentCurrentPage ? "active" : ""}">
            <button type="button" class="page-link" data-page="${page}">${page}</button>
          </li>
        `);
      }
      items.push(`
        <li class="page-item ${appointmentCurrentPage >= totalPages ? "disabled" : ""}">
          <button type="button" class="page-link" data-page="${appointmentCurrentPage + 1}">Next</button>
        </li>
      `);
      pagination.innerHTML = items.join("");
      pagination.querySelectorAll("button[data-page]").forEach((button) => {
        button.addEventListener("click", () => {
          if (button.closest(".page-item")?.classList.contains("disabled")) return;
          appointmentCurrentPage = Number.parseInt(button.getAttribute("data-page") || "1", 10) || 1;
          renderAppointments();
        });
      });
    }

    function getFilteredAppointments() {
      const search = String(document.getElementById("appointmentSearch").value || "").toLowerCase().trim();
      const status = String(document.getElementById("appointmentStatusFilter").value || "").trim().toLowerCase();
      const dateFrom = String(document.getElementById("appointmentDateFrom").value || "").trim();
      const dateTo = String(document.getElementById("appointmentDateTo").value || "").trim();

      return allAppointments.filter((item) => {
        if (activeTab !== "all" && String(item.status_bucket || "") !== activeTab) return false;
        if (status && appointmentStatusLabel(item.status_name).toLowerCase() !== status.toLowerCase()) return false;

        const haystack = [
          item.appointment_id,
          item.subject,
          item.purpose,
          item.meeting_location,
          item.status_name,
          item.official_name
        ].join(" ").toLowerCase();
        if (search && !haystack.includes(search)) return false;

        const sourceDate = String(item.request_timestamp || "").slice(0, 10);
        if (dateFrom && sourceDate && sourceDate < dateFrom) return false;
        if (dateTo && sourceDate && sourceDate > dateTo) return false;
        return true;
      });
    }

    function openAppointmentView(item) {
      document.getElementById("appointmentViewTitle").textContent = `Appointment ${item.appointment_id || ""}`;
      document.getElementById("appointmentViewId").textContent = item.appointment_id || "-";
      document.getElementById("appointmentViewStatus").innerHTML = `<span class="status-pill ${escapeHtml(statusBadgeClass(item.status_name))}">${escapeHtml(appointmentStatusLabel(item.status_name))}</span>`;
      document.getElementById("appointmentViewSubject").textContent = item.subject || "-";
      document.getElementById("appointmentViewOfficial").textContent = item.official_name || "-";
      document.getElementById("appointmentViewPreferred").textContent = formatDateTime(item.preferred_schedule_timestamp, "To be scheduled");
      document.getElementById("appointmentViewConfirmed").textContent = confirmedScheduleDisplay(item);
      document.getElementById("appointmentViewMeetingLocation").textContent = item.meeting_location || "To be shared by the office";
      document.getElementById("appointmentViewRequested").textContent = formatDateTime(item.request_timestamp);
      document.getElementById("appointmentViewReviewed").textContent = reviewedTimestampDisplay(item);
      document.getElementById("appointmentViewPurpose").textContent = item.purpose || "-";
      document.getElementById("appointmentViewRemarks").textContent = item.appointment_remarks || "-";

      if (!appointmentViewModal && window.bootstrap?.Modal) {
        appointmentViewModal = new bootstrap.Modal(document.getElementById("appointmentViewModal"));
      }
      appointmentViewModal?.show();
    }

    function bindViewButtons() {
      document.querySelectorAll(".appointment-view-btn").forEach((btn) => {
        btn.addEventListener("click", () => {
          const id = String(btn.getAttribute("data-id") || "");
          const item = allAppointments.find((row) => String(row.appointment_id) === id);
          if (item) openAppointmentView(item);
        });
      });
    }

    function renderAppointments() {
      const rows = getFilteredAppointments();
      const tbody = document.getElementById("appointmentTbody");
      const cards = document.getElementById("appointmentCards");
      const paged = paginateRows(rows, appointmentCurrentPage, appointmentEntriesPerPage);
      appointmentCurrentPage = paged.page;

      if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">No appointments found.</td></tr>`;
        cards.innerHTML = `<div class="text-center text-muted py-4">No appointments found.</div>`;
        renderAppointmentPagination(1);
        return;
      }

      tbody.innerHTML = paged.items.map((item) => `
        <tr>
          <td><strong>${escapeHtml(item.appointment_id || "-")}</strong></td>
          <td>${escapeHtml(item.subject || "-")}</td>
          <td>${escapeHtml(formatDateTime(item.preferred_schedule_timestamp, "To be scheduled"))}</td>
          <td>${escapeHtml(confirmedScheduleDisplay(item))}</td>
          <td><span class="status-pill ${escapeHtml(statusBadgeClass(item.status_name))}">${escapeHtml(appointmentStatusLabel(item.status_name))}</span></td>
          <td><button type="button" class="btn btn-sm btn-outline-secondary appointment-view-btn" data-id="${escapeHtml(item.appointment_id)}" data-view-id="${escapeHtml(item.appointment_id)}">View</button></td>
        </tr>
      `).join("");

      cards.innerHTML = paged.items.map((item) => `
        <article class="tracker-card">
          <div class="tracker-label">Appointment</div>
          <div class="tracker-value fw-semibold">${escapeHtml(item.appointment_id || "-")}</div>
          <div class="tracker-label mt-2">Subject</div>
          <div class="tracker-value">${escapeHtml(item.subject || "-")}</div>
          <div class="tracker-label mt-2">Purpose</div>
          <div class="tracker-value">${escapeHtml(item.purpose || "No purpose noted")}</div>
          <div class="tracker-label mt-2">Preferred Schedule</div>
          <div class="tracker-value">${escapeHtml(formatDateTime(item.preferred_schedule_timestamp, "To be scheduled"))}</div>
          <div class="tracker-label mt-2">Status</div>
          <div class="tracker-value"><span class="status-pill ${escapeHtml(statusBadgeClass(item.status_name))}">${escapeHtml(appointmentStatusLabel(item.status_name))}</span></div>
          <button type="button" class="btn btn-sm btn-outline-secondary mt-3 appointment-view-btn" data-id="${escapeHtml(item.appointment_id)}" data-view-id="${escapeHtml(item.appointment_id)}">View</button>
        </article>
      `).join("");

      renderAppointmentPagination(paged.totalPages);
      bindViewButtons();
    }

    async function loadAppointments() {
      const refreshBtn = document.getElementById("appointmentRefreshBtn");
      const tbody = document.getElementById("appointmentTbody");
      const cards = document.getElementById("appointmentCards");
      refreshBtn?.classList.add("is-loading");
      tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">Loading appointments...</td></tr>`;
      cards.innerHTML = `<div class="text-center text-muted py-4">Loading appointments...</div>`;

      try {
        const res = await fetch(`../PhpFiles/Resident-End/get_resident_appointments.php`, {
          credentials: "same-origin",
          headers: { "Accept": "application/json" }
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data?.success) {
          throw new Error(data?.message || "Unable to load appointments.");
        }
        allAppointments = Array.isArray(data.items) ? data.items : [];
        appointmentCurrentPage = 1;
        renderAppointments();
      } catch (err) {
        const message = escapeHtml(err?.message || "Unable to load appointments.");
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${message}</td></tr>`;
        cards.innerHTML = `<div class="text-center text-danger py-4">${message}</div>`;
      } finally {
        refreshBtn?.classList.remove("is-loading");
      }
    }

    document.addEventListener("DOMContentLoaded", () => {
      document.querySelectorAll(".tracker-tab").forEach((btn) => {
        btn.addEventListener("click", () => {
          document.querySelectorAll(".tracker-tab").forEach((node) => node.classList.remove("active"));
          btn.classList.add("active");
          activeTab = String(btn.getAttribute("data-tab") || "all");
          renderAppointments();
        });
      });

      document.getElementById("appointmentSearch").addEventListener("input", renderAppointments);
      document.getElementById("appointmentSearch").addEventListener("input", () => {
        appointmentCurrentPage = 1;
        renderAppointments();
      });
      document.getElementById("appointmentStatusFilter").addEventListener("change", () => {
        appointmentCurrentPage = 1;
        renderAppointments();
      });
      document.getElementById("appointmentDateFrom").addEventListener("change", () => {
        appointmentCurrentPage = 1;
        renderAppointments();
      });
      document.getElementById("appointmentDateTo").addEventListener("change", () => {
        appointmentCurrentPage = 1;
        renderAppointments();
      });
      document.getElementById("appointmentFilterApply").addEventListener("click", () => {
        appointmentCurrentPage = 1;
        renderAppointments();
      });
      document.getElementById("appointmentFilterReset").addEventListener("click", () => {
        document.getElementById("appointmentStatusFilter").value = "";
        document.getElementById("appointmentDateFrom").value = "";
        document.getElementById("appointmentDateTo").value = "";
        appointmentCurrentPage = 1;
        renderAppointments();
      });
      document.getElementById("appointmentEntriesPerPageInput").addEventListener("change", (event) => {
        appointmentEntriesPerPage = Math.max(1, Number.parseInt(event.target.value || "20", 10) || 20);
        event.target.value = String(appointmentEntriesPerPage);
        appointmentCurrentPage = 1;
        renderAppointments();
      });
      document.getElementById("appointmentRefreshBtn").addEventListener("click", loadAppointments);

      loadAppointments();
    });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    window.ADMIN_TABLE_COLUMNS_CONFIG = {
      tableSelector: "#appointmentTable",
      modalId: "appointmentColumnsModal",
      listId: "appointmentColumnsList",
      resetBtnId: "appointmentColumnsReset",
      storageKey: "resident_cols_appointment_tracker_v1",
      defaultHiddenIdxs: []
    };
  </script>
  <script src="../JS-Script-Files/Resident-End/dateFieldModal.js?v=20260707-date-proxy-white"></script>
  <script src="../JS-Script-Files/Admin-End/tableColumnsGeneric.js"></script>
</body>
</html>
