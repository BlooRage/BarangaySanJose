(() => {
    const appBase = (() => {
        const marker = "/Admin-End/";
        const idx = window.location.pathname.indexOf(marker);
        if (idx === -1) return "";
        return window.location.pathname.slice(0, idx);
    })();

    const endpoint = `${appBase}/PhpFiles/Admin-End/complaintTrackerData.php`;
    const tableBody = document.getElementById("tableBody");
    const searchInput = document.getElementById("searchInput");
    const entriesPerPageInput = document.getElementById("entriesPerPageInput");
    const paginationEl = document.getElementById("complaintPagination");
    const refreshBtn = document.getElementById("btnComplaintTableRefresh");
    const filterButtons = Array.from(document.querySelectorAll(".status-filter-btn"));
    const viewModalEl = document.getElementById("viewModal");
    const viewModal = viewModalEl ? new bootstrap.Modal(viewModalEl) : null;
    const viewModalTitle = document.getElementById("viewModalTitle");
    const viewDetailsBody = document.getElementById("viewDetailsBody");

    let allRows = [];
    let filteredRows = [];
    let currentPage = 1;
    let activeFilter = "";

    function esc(v) {
        return String(v ?? "").replace(/[&<>\"']/g, (m) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;" }[m]));
    }

    function badge(text, toneClass) {
        return `<span class="status-pill ${esc(toneClass || "archived")}">${esc(text || "-")}</span>`;
    }

    function toneForStatus(row) {
        if (Number(row?.escalated_to_blotter || 0) === 1) return "info";
        const status = String(row?.status_name || "").trim().toLowerCase();
        if (status.includes("review")) return "pending";
        if (status.includes("resolved") || status.includes("completed")) return "approved";
        return "archived";
    }

    function emptyState(message = "No complaint records found.") {
        return `
            <tr>
                <td colspan="7" class="text-start text-muted py-4">${esc(message)}</td>
            </tr>
        `;
    }

    function buildTableRow(row) {
        const actionBtn = `<button class="btn btn-sm btn-outline-secondary" data-view-id="${esc(row.case_id)}">View</button>`;
        return `
            <tr>
                <td>${esc(row.case_id)}</td>
                <td>${esc(row.submitted_date || "-")}</td>
                <td>${esc(row.complainant_name || "-")}</td>
                <td>${esc(row.subject_display_name || "-")}</td>
                <td>${esc(row.complaint_type || "-")}</td>
                <td>${badge(row.status_name || "Pending", toneForStatus(row))}</td>
                <td>${actionBtn}</td>
            </tr>
        `;
    }

    function renderPagination(total) {
        if (!paginationEl) return;
        const perPage = Math.max(1, Number(entriesPerPageInput?.value || 20));
        const totalPages = Math.max(1, Math.ceil(total / perPage));
        currentPage = Math.min(currentPage, totalPages);

        const makeBtn = (label, page, disabled = false, active = false) => `
            <li class="page-item ${disabled ? "disabled" : ""} ${active ? "active" : ""}">
                <button class="page-link" data-page="${page}" ${disabled ? "disabled" : ""}>${label}</button>
            </li>
        `;

        const html = [];
        html.push(makeBtn("Prev", currentPage - 1, currentPage <= 1));
        for (let page = 1; page <= totalPages; page += 1) {
            html.push(makeBtn(String(page), page, false, page === currentPage));
        }
        html.push(makeBtn("Next", currentPage + 1, currentPage >= totalPages));
        paginationEl.innerHTML = html.join("");

        paginationEl.querySelectorAll("button[data-page]").forEach((button) => {
            button.addEventListener("click", () => {
                const page = Number(button.getAttribute("data-page") || 1);
                if (!Number.isFinite(page)) return;
                currentPage = page;
                renderTable();
            });
        });
    }

    function renderTable() {
        if (!tableBody) return;
        const perPage = Math.max(1, Number(entriesPerPageInput?.value || 20));
        const start = (currentPage - 1) * perPage;
        const pageRows = filteredRows.slice(start, start + perPage);

        if (!pageRows.length) {
            tableBody.innerHTML = emptyState();
        } else {
            tableBody.innerHTML = pageRows.map(buildTableRow).join("");
        }

        renderPagination(filteredRows.length);

        tableBody.querySelectorAll("button[data-view-id]").forEach((button) => {
            button.addEventListener("click", () => {
                const caseId = String(button.getAttribute("data-view-id") || "").trim();
                if (!caseId) return;
                openViewModal(caseId);
            });
        });
    }

    function applyFilters() {
        const term = String(searchInput?.value || "").trim().toLowerCase();
        filteredRows = allRows.filter((row) => {
            const matchesFilter = !activeFilter || String(row.status_key || "").trim().toLowerCase() === activeFilter;
            if (!matchesFilter) return false;

            if (!term) return true;
            const haystack = [
                row.case_id,
                row.complainant_name,
                row.subject_display_name,
                row.complaint_type,
                row.status_name,
            ].map((value) => String(value || "").toLowerCase());
            return haystack.some((value) => value.includes(term));
        });

        currentPage = 1;
        renderTable();
    }

    async function fetchJson(url) {
        const res = await fetch(url);
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.success === false) {
            throw new Error(data.message || "Request failed.");
        }
        return data;
    }

    function field(label, value) {
        return `
            <div class="tracker-form-field">
                <p class="tracker-form-label">${esc(label)}</p>
                <div class="tracker-form-value">${esc(String(value || "-"))}</div>
            </div>
        `;
    }

    function section(title, content) {
        return `
            <section class="tracker-form-section">
                <h6 class="tracker-form-section-title">${esc(title)}</h6>
                <div class="tracker-form-grid">${content}</div>
            </section>
        `;
    }

    async function openViewModal(caseId) {
        if (!viewModal || !viewDetailsBody) return;

        if (viewModalTitle) {
            viewModalTitle.textContent = `Complaint Details (#${caseId})`;
        }
        viewDetailsBody.innerHTML = '<div class="text-muted">Loading complaint details...</div>';
        viewModal.show();

        try {
            const data = await fetchJson(`${endpoint}?action=detail&case_id=${encodeURIComponent(caseId)}`);
            const d = data.detail || {};

            const summarySection = section("Complaint Summary", [
                field("Case ID", d.case_id),
                field("Submitted At", d.submitted_at),
                field("Origin", d.complaint_origin || "ResidentPortal"),
                field("Status", d.status_name || "Pending"),
                field("Complaint Type", d.complaint_type),
                field("Incident Date", d.incident_date),
                field("Incident Time", d.incident_time),
                field("Incident Place", d.incident_place),
            ].join(""));

            const complainantSection = section("Complainant Information", [
                field("Full Name", d.complainant?.full_name),
                field("Contact Number", d.complainant?.contact_number),
                field("Age", d.complainant?.age),
                field("Sex", d.complainant?.sex),
                field("Address", d.complainant?.address),
            ].join(""));

            const subjectSection = section("Subject Information", [
                field("Subject", d.subject_display_name),
                field("Subject Kind", d.subject_kind),
                field("Contact Number", d.subject_contact_number),
                field("Address", d.subject_address),
                field("Respondent Record", d.respondent?.full_name || "-"),
            ].join(""));

            const witnessSection = section("Witness Information", [
                field("Witness Record", d.witness?.full_name || "-"),
                field("Witness Contact", d.witness?.contact_number || "-"),
                field("Witness Address", d.witness?.address || "-"),
                field("Witness Summary", d.witness_summary || "-"),
            ].join(""));

            const notesSection = section("Narration and Notes", [
                field("Resident Narration", d.case_details),
                field("Case Remarks", d.case_remarks),
                field("Intake Notes", d.intake_notes),
                field("Screening Notes", d.screening_notes),
                field("Escalated to Blotter", Number(d.escalated_to_blotter || 0) === 1 ? `Yes${d.blotter_id ? ` (Blotter #${d.blotter_id})` : ""}` : "No"),
            ].join(""));

            viewDetailsBody.innerHTML = [summarySection, complainantSection, subjectSection, witnessSection, notesSection].join("");
        } catch (error) {
            viewDetailsBody.innerHTML = `<div class="text-danger">${esc(error.message || error)}</div>`;
        }
    }

    async function loadList() {
        if (!tableBody) return;
        tableBody.innerHTML = emptyState("Loading complaint records...");

        try {
            const data = await fetchJson(`${endpoint}?action=list`);
            allRows = Array.isArray(data.items) ? data.items : [];
            applyFilters();
        } catch (error) {
            tableBody.innerHTML = emptyState(error.message || "Failed to load complaint records.");
        }
    }

    let searchTimer = null;
    searchInput?.addEventListener("input", () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(applyFilters, 180);
    });

    entriesPerPageInput?.addEventListener("change", () => {
        currentPage = 1;
        renderTable();
    });

    refreshBtn?.addEventListener("click", loadList);

    filterButtons.forEach((button) => {
        button.addEventListener("click", () => {
            filterButtons.forEach((btn) => {
                btn.classList.remove("btn-outline-primary", "active");
                btn.classList.add("btn-outline-secondary");
            });
            button.classList.remove("btn-outline-secondary");
            button.classList.add("btn-outline-primary", "active");
            activeFilter = String(button.dataset.filter || "").trim().toLowerCase();
            applyFilters();
        });
    });

    loadList();
})();
