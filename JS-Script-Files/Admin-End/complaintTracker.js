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
    const pendingComplaintBadge = document.getElementById("pendingComplaintBadge");
    const viewModalEl = document.getElementById("viewModal");
    const viewModal = viewModalEl ? new bootstrap.Modal(viewModalEl) : null;
    const viewModalTitle = document.getElementById("viewModalTitle");
    const viewDetailsBody = document.getElementById("viewDetailsBody");
    const complaintActionButtons = document.getElementById("complaintActionButtons");
    const resolveBtn = document.getElementById("btnComplaintResolve");
    const endorseBtn = document.getElementById("btnComplaintEndorse");
    const dropBtn = document.getElementById("btnComplaintDrop");
    const complaintActionModalEl = document.getElementById("complaintActionModal");
    const complaintActionModal = complaintActionModalEl ? new bootstrap.Modal(complaintActionModalEl) : null;
    const complaintActionModalTitle = document.getElementById("complaintActionModalTitle");
    const complaintActionRemarks = document.getElementById("complaintActionRemarks");
    const btnComplaintActionReturn = document.getElementById("btnComplaintActionReturn");
    const btnComplaintActionProceed = document.getElementById("btnComplaintActionProceed");
    const complaintActionConfirmModalEl = document.getElementById("complaintActionConfirmModal");
    const complaintActionConfirmModal = complaintActionConfirmModalEl ? new bootstrap.Modal(complaintActionConfirmModalEl) : null;
    const complaintActionConfirmText = document.getElementById("complaintActionConfirmText");
    const btnComplaintActionConfirmReturn = document.getElementById("btnComplaintActionConfirmReturn");
    const btnComplaintActionConfirm = document.getElementById("btnComplaintActionConfirm");

    let allRows = [];
    let filteredRows = [];
    let currentPage = 1;
    let activeFilter = "";
    let currentViewCaseId = null;
    let currentDetail = null;
    let pendingComplaintAction = null;

    function esc(v) {
        return String(v ?? "").replace(/[&<>\"']/g, (m) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;" }[m]));
    }

    function badge(text, toneClass) {
        return `<span class="status-pill ${esc(toneClass || "archived")}">${esc(text || "-")}</span>`;
    }

    function setActionButtonsState(detail) {
        if (!complaintActionButtons) return;
        if (!detail) {
            complaintActionButtons.classList.add("d-none");
            return;
        }
        const status = String(detail?.status_name || "").trim().toLowerCase();
        const hasLinkedBlotter = String(detail?.blotter_id || "").trim() !== "";
        const isFinal = ["resolved", "dropped"].includes(status) || (status === "endorsed" && hasLinkedBlotter);
        complaintActionButtons.classList.toggle("d-none", isFinal);
    }

    function toneForStatus(row) {
        if (Number(row?.escalated_to_blotter || 0) === 1) return "info";
        const status = String(row?.status_name || "").trim().toLowerCase();
        if (status === "pending" || status === "active") return "pending";
        if (status.includes("review")) return "pending";
        if (status.includes("resolved") || status.includes("completed")) return "approved";
        if (status.includes("endorse")) return "info";
        if (status.includes("drop")) return "archived";
        return "archived";
    }

    function toneForLevel(levelName) {
        const level = String(levelName || "").trim().toLowerCase();
        if (level === "complaint only") return "pending";
        if (level.includes("endorse")) return "info";
        return "archived";
    }

    function emptyState(message = "No complaint records found.") {
        return `
            <tr>
                <td colspan="8" class="text-start text-muted py-4">${esc(message)}</td>
            </tr>
        `;
    }

    function buildTableRow(row) {
        const actionBtn = `<button class="btn btn-sm btn-outline-secondary" data-view-id="${esc(row.case_id)}">View</button>`;
        return `
            <tr>
                <td>${esc(row.complaint_id || "-")}</td>
                <td>${esc(row.submitted_at || "-")}</td>
                <td>${esc(row.complainant_name || "-")}</td>
                <td>${esc(row.subject_display_name || "-")}</td>
                <td>${esc(row.complaint_type || "-")}</td>
                <td>${badge(row.status_name || "Pending", toneForStatus(row))}</td>
                <td>${badge(row.level_name || "Complaint Only", toneForLevel(row.level_name || "Complaint Only"))}</td>
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
                row.complaint_id,
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

    function updatePendingBadge() {
        if (!pendingComplaintBadge) return;
        const count = allRows.filter((row) => String(row?.status_key || "").trim().toLowerCase() === "pending").length;
        pendingComplaintBadge.textContent = String(count);
        pendingComplaintBadge.classList.toggle("d-none", count <= 0);
    }

    async function fetchJson(url) {
        const res = await fetch(url);
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.success === false) {
            throw new Error(data.message || "Request failed.");
        }
        return data;
    }

    function formField(label, value, raw = false) {
        const text = String(value ?? "").trim();
        const rendered = raw ? (text || "-") : esc(text || "-");
        return `
            <div class="tracker-form-field">
                <p class="tracker-form-label">${esc(label)}</p>
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
        const clean = (Array.isArray(fields) ? fields : []).filter((f) => f && String(f.value ?? "").trim() !== "");
        if (!clean.length) return "";
        const cls = gridClassByCount(clean.length, maxCols);
        return `<div class="tracker-form-grid ${cls}">${clean.map((f) => formField(f.label, f.value, !!f.raw)).join("")}</div>`;
    }

    function renderAddressFieldGrid(fields) {
        const clean = (Array.isArray(fields) ? fields : []).filter((f) => f && String(f.value ?? "").trim() !== "");
        if (!clean.length) return "";
        const gridClass = clean.length === 1 ? "cols-1" : "cols-3";
        return `<div class="tracker-form-grid ${gridClass}">${clean.map((f) => formField(f.label, f.value, !!f.raw)).join("")}</div>`;
    }

    function formSection(title, content) {
        return `
            <section class="tracker-form-section">
                <h6 class="tracker-form-section-title">${esc(title)}</h6>
                ${content}
            </section>
        `;
    }

    function renderParticipantGrid(participant) {
        return renderFieldGrid([
            { label: "Full Name", value: participant?.full_name || "-" },
            { label: "Contact Number", value: participant?.contact_number || "-" },
            { label: "Age", value: participant?.age || "-" },
            { label: "Sex", value: participant?.sex || "-" },
        ], 2);
    }

    function parseStructuredAddress(address) {
        const text = String(address || "").trim();
        if (!text) return [];

        const parts = text
            .split(",")
            .map((part) => part.trim())
            .filter(Boolean);

        const parsed = new Map();
        parts.forEach((part) => {
            const idx = part.indexOf(":");
            if (idx === -1) return;
            const label = part.slice(0, idx).trim();
            const value = part.slice(idx + 1).trim();
            if (label && value) {
                parsed.set(label, value);
            }
        });

        if (!parsed.size) {
            return [{ label: "Address", value: text }];
        }

        const orderedLabels = [
            "Unit",
            "House No.",
            "Street",
            "Lot",
            "Block",
            "Phase",
            "Subdivision",
            "Area",
            "Barangay",
            "Municipality",
            "Province",
        ];

        return orderedLabels
            .filter((label) => parsed.has(label))
            .map((label) => ({ label, value: parsed.get(label) || "" }));
    }

    function renderIntakeNotesEditor(value) {
        return `
            <div class="tracker-form-field">
                <p class="tracker-form-label">Intake Notes</p>
                <div class="tracker-form-value">
                    <textarea id="complaintIntakeNotes" class="form-control" rows="4" placeholder="Add intake notes...">${esc(value || "")}</textarea>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnSaveComplaintIntakeNotes">Save Intake Notes</button>
                    </div>
                </div>
            </div>
        `;
    }

    async function postJson(payload) {
        const res = await fetch(endpoint, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(payload),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.success === false) {
            throw new Error(data.message || "Request failed.");
        }
        return data;
    }

    function transitionModal(fromEl, fromModal, toModal) {
        if (fromEl && fromEl.classList.contains("show") && fromModal) {
            fromEl.addEventListener("hidden.bs.modal", () => {
                toModal?.show();
            }, { once: true });
            fromModal.hide();
            return;
        }
        toModal?.show();
    }

    function openComplaintActionModal(actionType) {
        if (!complaintActionModal || !currentViewCaseId) return;
        pendingComplaintAction = actionType;
        if (complaintActionRemarks) complaintActionRemarks.value = "";

        let title = "Update Complaint";
        if (actionType === "resolved") title = "Mark Complaint Resolved";
        if (actionType === "endorsement") title = "Endorse Complaint to Blotter";
        if (actionType === "dropped") title = "Drop Complaint";
        if (complaintActionModalTitle) complaintActionModalTitle.textContent = title;

        transitionModal(viewModalEl, viewModal, complaintActionModal);
    }

    async function submitComplaintAction() {
        if (!pendingComplaintAction || !currentViewCaseId) return;
        const remarks = String(complaintActionRemarks?.value || "").trim();
        if (!remarks) {
            window.alert("Screening notes are required.");
            return;
        }

        try {
            if (btnComplaintActionConfirm) btnComplaintActionConfirm.disabled = true;
            await postJson({
                action: "update_case_outcome",
                case_id: currentViewCaseId,
                action_type: pendingComplaintAction,
                remarks,
            });
            const targetCaseId = currentViewCaseId;
            if (complaintActionConfirmModalEl && complaintActionConfirmModal) {
                complaintActionConfirmModalEl.addEventListener("hidden.bs.modal", async () => {
                    await loadList();
                    if (targetCaseId) openViewModal(targetCaseId);
                }, { once: true });
                complaintActionConfirmModal.hide();
            } else {
                await loadList();
                if (targetCaseId) openViewModal(targetCaseId);
            }
        } catch (error) {
            window.alert(error.message || error);
        } finally {
            if (btnComplaintActionConfirm) btnComplaintActionConfirm.disabled = false;
        }
    }

    function initComplaintActionFlow() {
        resolveBtn?.addEventListener("click", () => openComplaintActionModal("resolved"));
        endorseBtn?.addEventListener("click", () => openComplaintActionModal("endorsement"));
        dropBtn?.addEventListener("click", () => openComplaintActionModal("dropped"));

        btnComplaintActionReturn?.addEventListener("click", () => {
            transitionModal(complaintActionModalEl, complaintActionModal, viewModal);
        });

        btnComplaintActionProceed?.addEventListener("click", () => {
            const remarks = String(complaintActionRemarks?.value || "").trim();
            if (!remarks) {
                window.alert("Screening notes are required.");
                return;
            }

            let actionText = "update this complaint";
            if (pendingComplaintAction === "resolved") actionText = "mark this complaint as resolved";
            if (pendingComplaintAction === "endorsement") actionText = "endorse this complaint to blotter";
            if (pendingComplaintAction === "dropped") actionText = "drop this complaint";
            if (complaintActionConfirmText) {
                complaintActionConfirmText.textContent = `Are you sure you want to ${actionText}?`;
            }
            transitionModal(complaintActionModalEl, complaintActionModal, complaintActionConfirmModal);
        });

        btnComplaintActionConfirmReturn?.addEventListener("click", () => {
            transitionModal(complaintActionConfirmModalEl, complaintActionConfirmModal, complaintActionModal);
        });

        btnComplaintActionConfirm?.addEventListener("click", submitComplaintAction);
    }

    async function openViewModal(caseId) {
        if (!viewModal || !viewDetailsBody) return;
        currentViewCaseId = String(caseId);
        currentDetail = null;

        if (viewModalTitle) {
            viewModalTitle.textContent = "Complaint Details";
        }
        viewDetailsBody.innerHTML = '<div class="text-muted">Loading complaint details...</div>';
        setActionButtonsState(null);
        viewModal.show();

        try {
            const data = await fetchJson(`${endpoint}?action=detail&case_id=${encodeURIComponent(caseId)}`);
            const d = data.detail || {};

            const summaryGrid = renderFieldGrid([
                { label: "Complaint ID", value: d.complaint_id || "-" },
                { label: "Submitted At", value: d.submitted_at || "-" },
                { label: "Origin", value: d.complaint_origin || "ResidentPortal" },
                { label: "Status", value: d.status_name || "Pending" },
            ], 4);
            const summaryMetaGrid = renderFieldGrid([
                { label: "Complaint Level", value: d.level_name || "Complaint Only" },
                { label: "Complaint Type", value: d.complaint_type || "-" },
                { label: "Incident Date", value: d.incident_date || "-" },
                { label: "Incident Time", value: d.incident_time || "-" },
            ], 4);
            const summaryPlaceGrid = renderFieldGrid([
                { label: "Incident Place", value: d.incident_place || "-" },
            ], 1);

            const complainantGrid = [
                renderParticipantGrid(d.complainant || {}),
                renderAddressFieldGrid(parseStructuredAddress(d.complainant?.address || "")),
            ].join("");

            const subjectGrid = [
                renderFieldGrid([
                    { label: "Subject", value: d.subject_display_name || "-" },
                    { label: "Subject Kind", value: d.subject_kind || "-" },
                    { label: "Contact Number", value: d.subject_contact_number || "-" },
                    { label: "Complaint Type", value: d.complaint_type || "-" },
                ], 2),
                renderFieldGrid([
                    { label: "Known Address / Location", value: d.subject_address || "-" },
                ], 1),
            ].join("");

            const witnessGrid = [
                renderFieldGrid([
                    { label: "Witness Name", value: d.witness?.full_name || "-" },
                    { label: "Witness Contact", value: d.witness?.contact_number || "-" },
                ], 2),
                renderFieldGrid([
                    { label: "Witness Address", value: d.witness?.address || "-" },
                ], 1),
            ].join("");

            const intakeNotesSection = formSection("Intake Notes", renderIntakeNotesEditor(d.intake_notes || ""));

            const notesGrid = [
                renderFieldGrid([
                    { label: "Case Remarks", value: d.case_remarks || "-" },
                    { label: "Escalated to Blotter", value: Number(d.escalated_to_blotter || 0) === 1 ? `Yes${d.blotter_id ? ` (${d.blotter_id})` : ""}` : "No" },
                ], 2),
                renderFieldGrid([
                    { label: "Resident Narration", value: d.case_details || "-" },
                ], 1),
                renderFieldGrid([
                    { label: "Screening Notes", value: d.screening_notes || "-" },
                ], 1),
            ].join("");

            const html = [
                formSection("Complaint Summary", [
                    summaryGrid,
                    summaryMetaGrid,
                    summaryPlaceGrid,
                ].join("")),
                formSection("Complainant Information", complainantGrid),
                formSection("Subject Information", subjectGrid),
                formSection("Witness Information", witnessGrid),
                intakeNotesSection,
                formSection("Narration and Notes", notesGrid),
            ].join("");

            viewDetailsBody.innerHTML = html || '<div class="text-muted">No details available.</div>';
            currentDetail = d;
            setActionButtonsState(d);

            const intakeNotesField = document.getElementById("complaintIntakeNotes");
            const saveIntakeNotesBtn = document.getElementById("btnSaveComplaintIntakeNotes");
            saveIntakeNotesBtn?.addEventListener("click", async () => {
                const intakeNotes = String(intakeNotesField?.value || "");
                try {
                    if (saveIntakeNotesBtn) saveIntakeNotesBtn.disabled = true;
                    await postJson({
                        action: "update_intake_notes",
                        case_id: currentViewCaseId,
                        intake_notes: intakeNotes,
                    });
                    await loadList();
                    await openViewModal(currentViewCaseId);
                } catch (error) {
                    window.alert(error.message || error);
                } finally {
                    if (saveIntakeNotesBtn) saveIntakeNotesBtn.disabled = false;
                }
            });
        } catch (error) {
            viewDetailsBody.innerHTML = `<div class="text-danger">${esc(error.message || error)}</div>`;
            setActionButtonsState(null);
        }
    }

    async function loadList() {
        if (!tableBody) return;
        tableBody.innerHTML = emptyState("Loading complaint records...");

        try {
            const data = await fetchJson(`${endpoint}?action=list`);
            allRows = Array.isArray(data.items) ? data.items : [];
            updatePendingBadge();
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

    initComplaintActionFlow();
    loadList();
})();

