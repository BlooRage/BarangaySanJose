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
  <style>
    .tracker-shell { border-color: #f1e1cf !important; }
    .tracker-title {
      font-family: 'Charis SIL Bold', serif;
      color: #DE710C;
      font-size: clamp(2rem, 4.4vw, 3rem);
      line-height: 1.1;
      margin: 0 0 0.65rem 0;
    }
    .toolbar {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: nowrap;
      overflow-x: auto;
      margin-bottom: 1rem;
    }
    .toolbar .tabs,
    .toolbar .actions {
      display: flex;
      align-items: center;
      gap: 8px;
      flex: 0 0 auto;
      white-space: nowrap;
    }
    .toolbar .actions {
      margin-left: auto;
      flex-wrap: nowrap;
    }
    .toolbar .actions .input-group {
      min-width: 260px;
      max-width: 420px;
      flex: 1 1 auto;
    }
    .tracker-tab.btn {
      border-radius: 999px;
      padding: 0.35rem 0.85rem;
      font-weight: 600;
      border-color: #e3e6ea;
      color: #495057;
      background: #fff;
    }
    .tracker-tab.btn.active {
      border-color: rgba(254, 153, 60, 0.7);
      color: #a04f00;
      background: linear-gradient(180deg, #fff6ec 0%, #ffe9d1 100%);
    }
    .btn-icon {
      width: 38px;
      height: 38px;
      padding: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 10px;
    }
    .refresh-btn {
      border-color: rgba(254, 153, 60, 0.45);
      color: #b85b00;
      background: linear-gradient(180deg, #fffaf4 0%, #fff3e4 100%);
    }
    .refresh-btn.is-loading i { animation: spin 900ms linear infinite; }
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      border-radius: 999px;
      padding: 0.34rem 0.78rem;
      font-size: 0.82rem;
      font-weight: 700;
      white-space: nowrap;
      border: 1px solid transparent;
    }
    .status-pill.pending { color: #9a3412; background: #ffedd5; border-color: #fdba74; }
    .status-pill.approved { color: #166534; background: #dcfce7; border-color: #86efac; }
    .status-pill.archived { color: #991b1b; background: #fee2e2; border-color: #fca5a5; }
    .status-pill.info { color: #1d4ed8; background: #dbeafe; border-color: #93c5fd; }
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
      .toolbar {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
      }
      .toolbar .tabs,
      .toolbar .actions {
        width: 100%;
      }
      .toolbar .tabs {
        overflow-x: auto;
        padding-bottom: 4px;
      }
      .toolbar .actions {
        margin-left: 0;
        justify-content: flex-start;
        overflow-x: auto;
        padding-bottom: 2px;
      }
      .toolbar .actions .input-group {
        min-width: 0;
        max-width: none;
        width: 100%;
      }
    }
    @media (max-width: 767.98px) {
      .table-responsive { display: none; }
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

      <div class="bg-white p-4 rounded-4 shadow-sm border tracker-shell">
        <div class="toolbar">
          <div class="tabs">
            <button type="button" class="btn tracker-tab active" data-tab="all">All</button>
            <button type="button" class="btn tracker-tab" data-tab="pending">Pending</button>
            <button type="button" class="btn tracker-tab" data-tab="approved">Approved</button>
            <button type="button" class="btn tracker-tab" data-tab="archived">Denied</button>
          </div>
          <div class="actions">
            <div class="input-group">
              <input id="appointmentSearch" class="form-control" placeholder="Search appointments..." />
              <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
            </div>
            <button id="appointmentFilterBtn" class="btn btn-outline-secondary btn-icon" type="button" title="Filter" aria-label="Filter" data-bs-toggle="modal" data-bs-target="#appointmentFilterModal">
              <i class="fas fa-filter"></i>
            </button>
            <button id="appointmentRefreshBtn" class="btn refresh-btn btn-icon" type="button" title="Refresh appointment tracker" aria-label="Refresh appointment tracker">
              <i class="fa-solid fa-arrows-rotate"></i>
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
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
                <option value="Pending">Pending</option>
                <option value="Approved">Approved</option>
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
            <div class="col-md-6"><strong>Assigned Official:</strong><div id="appointmentViewOfficial" class="text-muted"></div></div>
            <div class="col-md-6"><strong>Preferred Schedule:</strong><div id="appointmentViewPreferred" class="text-muted"></div></div>
            <div class="col-md-6"><strong>Confirmed Schedule:</strong><div id="appointmentViewConfirmed" class="text-muted"></div></div>
            <div class="col-md-6"><strong>Requested At:</strong><div id="appointmentViewRequested" class="text-muted"></div></div>
            <div class="col-md-6"><strong>Reviewed At:</strong><div id="appointmentViewReviewed" class="text-muted"></div></div>
            <div class="col-12"><strong>Purpose:</strong><div id="appointmentViewPurpose" class="text-muted"></div></div>
            <div class="col-12"><strong>Resident Notes:</strong><div id="appointmentViewResidentNotes" class="text-muted"></div></div>
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
      if (normalized.includes("approve") || normalized.includes("complete")) return "approved";
      if (normalized.includes("resched")) return "info";
      if (normalized.includes("deny") || normalized.includes("reject")) return "archived";
      return "pending";
    }

    function getFilteredAppointments() {
      const search = String(document.getElementById("appointmentSearch").value || "").toLowerCase().trim();
      const status = String(document.getElementById("appointmentStatusFilter").value || "").trim().toLowerCase();
      const dateFrom = String(document.getElementById("appointmentDateFrom").value || "").trim();
      const dateTo = String(document.getElementById("appointmentDateTo").value || "").trim();

      return allAppointments.filter((item) => {
        if (activeTab !== "all" && String(item.status_bucket || "") !== activeTab) return false;
        if (status && String(item.status_name || "").toLowerCase() !== status) return false;

        const haystack = [
          item.appointment_id,
          item.subject,
          item.purpose,
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
      document.getElementById("appointmentViewStatus").innerHTML = `<span class="status-pill ${escapeHtml(statusBadgeClass(item.status_name))}">${escapeHtml(item.status_name || "Pending")}</span>`;
      document.getElementById("appointmentViewSubject").textContent = item.subject || "-";
      document.getElementById("appointmentViewOfficial").textContent = item.official_name || "-";
      document.getElementById("appointmentViewPreferred").textContent = formatDateTime(item.preferred_schedule_timestamp, "To be scheduled");
      document.getElementById("appointmentViewConfirmed").textContent = formatDateTime(item.confirmed_schedule_timestamp, "Awaiting confirmation");
      document.getElementById("appointmentViewRequested").textContent = formatDateTime(item.request_timestamp);
      document.getElementById("appointmentViewReviewed").textContent = formatDateTime(item.review_timestamp, "Not reviewed yet");
      document.getElementById("appointmentViewPurpose").textContent = item.purpose || "-";
      document.getElementById("appointmentViewResidentNotes").textContent = item.resident_notes || "-";
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

      if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">No appointments found.</td></tr>`;
        cards.innerHTML = `<div class="text-center text-muted py-4">No appointments found.</div>`;
        return;
      }

      tbody.innerHTML = rows.map((item) => `
        <tr>
          <td><strong>${escapeHtml(item.appointment_id || "-")}</strong></td>
          <td>${escapeHtml(item.subject || "-")}<div class="small text-muted mt-1">${escapeHtml(item.purpose || "No purpose noted")}</div></td>
          <td>${escapeHtml(formatDateTime(item.preferred_schedule_timestamp, "To be scheduled"))}</td>
          <td>${escapeHtml(formatDateTime(item.confirmed_schedule_timestamp, "Awaiting confirmation"))}</td>
          <td><span class="status-pill ${escapeHtml(statusBadgeClass(item.status_name))}">${escapeHtml(item.status_name || "Pending")}</span></td>
          <td><button type="button" class="btn btn-sm btn-outline-secondary appointment-view-btn" data-id="${escapeHtml(item.appointment_id)}">View</button></td>
        </tr>
      `).join("");

      cards.innerHTML = rows.map((item) => `
        <article class="tracker-card">
          <div class="tracker-label">Appointment</div>
          <div class="tracker-value fw-semibold">${escapeHtml(item.appointment_id || "-")}</div>
          <div class="tracker-label mt-2">Subject</div>
          <div class="tracker-value">${escapeHtml(item.subject || "-")}</div>
          <div class="tracker-label mt-2">Preferred Schedule</div>
          <div class="tracker-value">${escapeHtml(formatDateTime(item.preferred_schedule_timestamp, "To be scheduled"))}</div>
          <div class="tracker-label mt-2">Status</div>
          <div class="tracker-value"><span class="status-pill ${escapeHtml(statusBadgeClass(item.status_name))}">${escapeHtml(item.status_name || "Pending")}</span></div>
          <button type="button" class="btn btn-sm btn-outline-secondary mt-3 appointment-view-btn" data-id="${escapeHtml(item.appointment_id)}">View</button>
        </article>
      `).join("");

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
      document.getElementById("appointmentStatusFilter").addEventListener("change", renderAppointments);
      document.getElementById("appointmentDateFrom").addEventListener("change", renderAppointments);
      document.getElementById("appointmentDateTo").addEventListener("change", renderAppointments);
      document.getElementById("appointmentFilterApply").addEventListener("click", renderAppointments);
      document.getElementById("appointmentFilterReset").addEventListener("click", () => {
        document.getElementById("appointmentStatusFilter").value = "";
        document.getElementById("appointmentDateFrom").value = "";
        document.getElementById("appointmentDateTo").value = "";
        renderAppointments();
      });
      document.getElementById("appointmentRefreshBtn").addEventListener("click", loadAppointments);

      loadAppointments();
    });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
