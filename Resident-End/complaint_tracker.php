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
  <title>Complaint Tracker - Barangay San Jose</title>

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
    #complaintCards { display: none; }
    #complaintViewModal .modal-content {
      border: 1px solid #e9ecef;
      border-radius: 16px;
      overflow: hidden;
      background: #fff;
    }
    #complaintViewModal .modal-dialog {
      max-width: 1500px;
      width: 75vw;
    }
    #complaintViewModal .modal-header,
    #complaintViewModal .modal-body,
    #complaintViewModal .modal-footer {
      padding: 1rem 1.25rem;
    }
    #complaintViewModal .modal-body {
      background: #fff;
    }
    #complaintViewDetails {
      display: grid;
      gap: 12px;
    }
    #complaintViewModal .tracker-form-section {
      border: 1px solid #e9ecef;
      border-color: #e78924;
      background: #ffffff;
      border-radius: 12px;
      padding: 12px;
      margin-top: 0;
      display: grid;
      gap: 12px;
    }
    #complaintViewModal .tracker-form-section-title {
      margin: 0;
      font-size: 1rem;
      font-weight: 700;
      color: #212529;
      border-bottom: 1px dashed #e9ecef;
      padding-bottom: 6px;
    }
    #complaintViewModal .tracker-form-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 14px 12px;
    }
    #complaintViewModal .tracker-form-grid.cols-4 {
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    #complaintViewModal .tracker-form-grid.cols-3 {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    #complaintViewModal .tracker-form-grid.cols-1 {
      grid-template-columns: 1fr;
    }
    #complaintViewModal .tracker-form-field {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    #complaintViewModal .tracker-form-label {
      margin: 0;
      line-height: 1.2;
      font-size: 0.76rem;
      color: #6b7280;
      font-weight: 700;
    }
    #complaintViewModal .tracker-form-value {
      min-height: 38px;
      border: 1px solid #dbe0e6;
      border-radius: 8px;
      background: #f8fafc;
      padding: 8px 10px;
      font-size: 0.92rem;
      color: #111827;
      font-weight: 500;
      line-height: 1.45;
      word-break: break-word;
    }
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
      #complaintCards { display: block; }
      #complaintViewModal .modal-dialog {
        width: calc(100vw - 1rem);
      }
      #complaintViewModal .tracker-form-grid,
      #complaintViewModal .tracker-form-grid.cols-4,
      #complaintViewModal .tracker-form-grid.cols-3 {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <div class="d-flex" style="min-height: 100vh;">
    <?php include __DIR__ . '/includes/resident_sidebar.php'; ?>

    <main id="div-mainDisplay" class="flex-grow-1 p-4 p-md-5 bg-light">
      <h2 class="tracker-title">Complaint Tracker</h2>
      <hr class="mt-0 mb-3">

      <div class="bg-white p-4 rounded-4 shadow-sm border tracker-shell">
        <div class="toolbar">
          <div class="tabs">
            <button type="button" class="btn tracker-tab active" data-tab="all">All</button>
            <button type="button" class="btn tracker-tab" data-tab="pending">Pending</button>
            <button type="button" class="btn tracker-tab" data-tab="approved">Resolved</button>
            <button type="button" class="btn tracker-tab" data-tab="archived">Closed</button>
          </div>
          <div class="actions">
            <div class="input-group">
              <input id="complaintSearch" class="form-control" placeholder="Search complaints..." />
              <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
            </div>
            <button id="complaintFilterBtn" class="btn btn-outline-secondary btn-icon" type="button" title="Filter" aria-label="Filter" data-bs-toggle="modal" data-bs-target="#complaintFilterModal">
              <i class="fas fa-filter"></i>
            </button>
            <button id="complaintRefreshBtn" class="btn refresh-btn btn-icon" type="button" title="Refresh complaint tracker" aria-label="Refresh complaint tracker">
              <i class="fa-solid fa-arrows-rotate"></i>
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Complaint ID</th>
                <th>Type</th>
                <th>Subject</th>
                <th>Incident Date</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="complaintTbody">
              <tr><td colspan="6" class="text-center text-muted py-4">Loading complaints...</td></tr>
            </tbody>
          </table>
        </div>

        <div id="complaintCards" class="mt-2">
          <div class="text-center text-muted py-4">Loading complaints...</div>
        </div>
      </div>
    </main>
  </div>

  <div class="modal fade" id="complaintFilterModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Filter Complaints</h5>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label small text-muted mb-1">Status</label>
              <select class="form-select" id="complaintStatusFilter">
                <option value="">All Status</option>
                <option value="Pending">Pending</option>
                <option value="Resolved">Resolved</option>
                <option value="Dropped">Dropped</option>
                <option value="Endorsed to Blotter">Endorsed to Blotter</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small text-muted mb-1">Date From</label>
              <input type="date" class="form-control" id="complaintDateFrom" />
            </div>
            <div class="col-12">
              <label class="form-label small text-muted mb-1">Date To</label>
              <input type="date" class="form-control" id="complaintDateTo" />
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" id="complaintFilterReset">Reset</button>
          <button type="button" class="btn btn-primary" id="complaintFilterApply" data-bs-dismiss="modal">Apply</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="complaintViewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="complaintViewTitle">Complaint Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="complaintViewDetails">
            <div class="text-muted">Select a complaint to view details.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    let allComplaints = [];
    let activeTab = "all";
    let complaintViewModal = null;

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

    function formatDate(value, fallback = "-") {
      const text = String(value || "").trim();
      if (!text) return fallback;
      const date = new Date(`${text}T00:00:00`);
      if (Number.isNaN(date.getTime())) return text;
      return date.toLocaleDateString([], {
        year: "numeric",
        month: "short",
        day: "2-digit"
      });
    }

    function statusBadgeClass(value) {
      const normalized = String(value || "").toLowerCase();
      if (normalized.includes("resolve")) return "approved";
      if (normalized.includes("drop") || normalized.includes("endorse")) return "archived";
      return "pending";
    }

    function formField(label, value, options = {}) {
      const text = String(value ?? "").trim();
      const rendered = options.raw ? (text || "-") : escapeHtml(text || "-");
      return `
        <div class="tracker-form-field">
          <p class="tracker-form-label">${escapeHtml(label)}</p>
          <div class="tracker-form-value">${rendered}</div>
        </div>
      `;
    }

    function gridClassByCount(count, maxCols = 4) {
      const n = Math.max(1, Math.min(maxCols, Number(count) || 1));
      if (n >= 4) return "cols-4";
      if (n === 3) return "cols-3";
      if (n === 2) return "";
      return "cols-1";
    }

    function renderFieldGrid(fields, maxCols = 4) {
      const clean = (Array.isArray(fields) ? fields : []).filter((field) => field);
      if (!clean.length) return "";
      return `<div class="tracker-form-grid ${gridClassByCount(clean.length, maxCols)}">${clean.map((field) => formField(field.label, field.value, field)).join("")}</div>`;
    }

    function formSection(title, content) {
      return `
        <section class="tracker-form-section">
          <h6 class="tracker-form-section-title">${escapeHtml(title)}</h6>
          ${content}
        </section>
      `;
    }

    function getFilteredComplaints() {
      const search = String(document.getElementById("complaintSearch").value || "").toLowerCase().trim();
      const status = String(document.getElementById("complaintStatusFilter").value || "").trim().toLowerCase();
      const dateFrom = String(document.getElementById("complaintDateFrom").value || "").trim();
      const dateTo = String(document.getElementById("complaintDateTo").value || "").trim();

      return allComplaints.filter((item) => {
        if (activeTab !== "all" && String(item.status_bucket || "") !== activeTab) return false;
        if (status && String(item.status_name || "").toLowerCase() !== status) return false;

        const haystack = [
          item.complaint_id,
          item.case_id,
          item.complaint_type,
          item.subject_display_name,
          item.incident_place,
          item.status_name
        ].join(" ").toLowerCase();
        if (search && !haystack.includes(search)) return false;

        const sourceDate = String(item.report_timestamp || "").slice(0, 10);
        if (dateFrom && sourceDate && sourceDate < dateFrom) return false;
        if (dateTo && sourceDate && sourceDate > dateTo) return false;
        return true;
      });
    }

    function openComplaintView(item) {
      const complaintViewDetails = document.getElementById("complaintViewDetails");
      const blotterText = item.blotter_id ? `Yes (${item.blotter_id})` : "No";

      document.getElementById("complaintViewTitle").textContent = "Complaint Details";
      complaintViewDetails.innerHTML = [
        formSection("Complaint Summary", [
          renderFieldGrid([
            { label: "Complaint ID", value: item.complaint_id || "-" },
            { label: "Submitted At", value: formatDateTime(item.report_timestamp) },
            { label: "Origin", value: "ResidentPortal" },
            { label: "Status", value: item.status_name || "Pending" },
          ], 4),
          renderFieldGrid([
            { label: "Complaint Level", value: item.level_name || "-" },
            { label: "Complaint Type", value: item.complaint_type || "-" },
            { label: "Incident Date", value: formatDate(item.incident_date) },
            { label: "Incident Time", value: item.incident_time || "-" },
          ], 4),
          renderFieldGrid([
            { label: "Incident Place", value: item.incident_place || "-" },
          ], 1),
        ].join("")),
        formSection("Complainant Information", [
          renderFieldGrid([
            { label: "Full Name", value: item.complainant_name || "-" },
            { label: "Contact Number", value: item.complainant_contact_number || "-" },
            { label: "Age", value: item.complainant_age || "-" },
            { label: "Sex", value: item.complainant_sex || "-" },
          ], 2),
          renderFieldGrid([
            { label: "Address", value: item.complainant_address || "-" },
          ], 1),
        ].join("")),
        formSection("Subject Information", [
          renderFieldGrid([
            { label: "Subject", value: item.subject_display_name || "-" },
            { label: "Subject Kind", value: item.subject_kind || "-" },
            { label: "Contact Number", value: item.subject_contact_number || "-" },
            { label: "Complaint Type", value: item.complaint_type || "-" },
          ], 2),
          renderFieldGrid([
            { label: "Known Address / Location", value: item.subject_address || "-" },
          ], 1),
        ].join("")),
        formSection("Witness Information", renderFieldGrid([
          { label: "Witness Summary", value: item.witness_summary || "-" },
        ], 1)),
        formSection("Intake Notes", renderFieldGrid([
          { label: "Intake Notes", value: item.intake_notes || "-" },
        ], 1)),
        formSection("Narration and Notes", [
          renderFieldGrid([
            { label: "Case Remarks", value: item.case_remarks || "-" },
            { label: "Escalated to Blotter", value: blotterText },
          ], 2),
          renderFieldGrid([
            { label: "Resident Narration", value: item.case_details || "-" },
          ], 1),
          renderFieldGrid([
            { label: "Screening Notes", value: item.screening_notes || "-" },
          ], 2),
        ].join("")),
      ].join("");

      if (!complaintViewModal && window.bootstrap?.Modal) {
        complaintViewModal = new bootstrap.Modal(document.getElementById("complaintViewModal"));
      }
      complaintViewModal?.show();
    }

    function bindViewButtons() {
      document.querySelectorAll(".complaint-view-btn").forEach((btn) => {
        btn.addEventListener("click", () => {
          const id = String(btn.getAttribute("data-id") || "");
          const item = allComplaints.find((row) => String(row.complaint_id) === id);
          if (item) openComplaintView(item);
        });
      });
    }

    function renderComplaints() {
      const rows = getFilteredComplaints();
      const tbody = document.getElementById("complaintTbody");
      const cards = document.getElementById("complaintCards");

      if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">No complaints found.</td></tr>`;
        cards.innerHTML = `<div class="text-center text-muted py-4">No complaints found.</div>`;
        return;
      }

      tbody.innerHTML = rows.map((item) => `
        <tr>
          <td><strong>${escapeHtml(item.complaint_id || "-")}</strong><div class="small text-muted mt-1">Case ${escapeHtml(item.case_id || "-")}</div></td>
          <td>${escapeHtml(item.complaint_type || "-")}</td>
          <td>${escapeHtml(item.subject_display_name || "-")}<div class="small text-muted mt-1">${escapeHtml(item.incident_place || "No location noted")}</div></td>
          <td>${escapeHtml(formatDate(item.incident_date))}</td>
          <td><span class="status-pill ${escapeHtml(statusBadgeClass(item.status_name))}">${escapeHtml(item.status_name || "Pending")}</span><div class="small text-muted mt-1">${escapeHtml(item.level_name || "Complaint Only")}</div></td>
          <td><button type="button" class="btn btn-sm btn-outline-secondary complaint-view-btn" data-id="${escapeHtml(item.complaint_id)}">View</button></td>
        </tr>
      `).join("");

      cards.innerHTML = rows.map((item) => `
        <article class="tracker-card">
          <div class="tracker-label">Complaint</div>
          <div class="tracker-value fw-semibold">${escapeHtml(item.complaint_id || "-")}</div>
          <div class="tracker-label mt-2">Type</div>
          <div class="tracker-value">${escapeHtml(item.complaint_type || "-")}</div>
          <div class="tracker-label mt-2">Subject</div>
          <div class="tracker-value">${escapeHtml(item.subject_display_name || "-")}</div>
          <div class="tracker-label mt-2">Status</div>
          <div class="tracker-value"><span class="status-pill ${escapeHtml(statusBadgeClass(item.status_name))}">${escapeHtml(item.status_name || "Pending")}</span></div>
          <button type="button" class="btn btn-sm btn-outline-secondary mt-3 complaint-view-btn" data-id="${escapeHtml(item.complaint_id)}">View</button>
        </article>
      `).join("");

      bindViewButtons();
    }

    async function loadComplaints() {
      const refreshBtn = document.getElementById("complaintRefreshBtn");
      const tbody = document.getElementById("complaintTbody");
      const cards = document.getElementById("complaintCards");
      refreshBtn?.classList.add("is-loading");
      tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">Loading complaints...</td></tr>`;
      cards.innerHTML = `<div class="text-center text-muted py-4">Loading complaints...</div>`;

      try {
        const res = await fetch(`../PhpFiles/Resident-End/get_resident_complaints.php`, {
          credentials: "same-origin",
          headers: { "Accept": "application/json" }
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data?.success) {
          throw new Error(data?.message || "Unable to load complaints.");
        }
        allComplaints = Array.isArray(data.items) ? data.items : [];
        renderComplaints();
      } catch (err) {
        const message = escapeHtml(err?.message || "Unable to load complaints.");
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
          renderComplaints();
        });
      });

      document.getElementById("complaintSearch").addEventListener("input", renderComplaints);
      document.getElementById("complaintStatusFilter").addEventListener("change", renderComplaints);
      document.getElementById("complaintDateFrom").addEventListener("change", renderComplaints);
      document.getElementById("complaintDateTo").addEventListener("change", renderComplaints);
      document.getElementById("complaintFilterApply").addEventListener("click", renderComplaints);
      document.getElementById("complaintFilterReset").addEventListener("click", () => {
        document.getElementById("complaintStatusFilter").value = "";
        document.getElementById("complaintDateFrom").value = "";
        document.getElementById("complaintDateTo").value = "";
        renderComplaints();
      });
      document.getElementById("complaintRefreshBtn").addEventListener("click", loadComplaints);

      loadComplaints();
    });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
