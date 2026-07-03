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
    const filterModalEl = document.getElementById("modalComplaintFilter");
    const filterDateFromEl = document.getElementById("complaintFilterDateFrom");
    const filterDateToEl = document.getElementById("complaintFilterDateTo");
    const filterTypeListEl = document.getElementById("complaintFilterTypeList");
    const filterAreaListEl = document.getElementById("complaintFilterAreaList");
    const filterSectorListEl = document.getElementById("complaintFilterSectorList");
    const btnComplaintFilterApply = document.getElementById("btnComplaintFilterApply");
    const btnComplaintFilterReset = document.getElementById("btnComplaintFilterReset");
    const viewModalEl = document.getElementById("viewModal");
    const viewModal = viewModalEl ? new bootstrap.Modal(viewModalEl) : null;
    const viewModalTitle = document.getElementById("viewModalTitle");
    const viewDetailsBody = document.getElementById("viewDetailsBody");
    const attachmentViewerModalEl = document.getElementById("attachmentViewerModal");
    const attachmentViewerModal = attachmentViewerModalEl ? new bootstrap.Modal(attachmentViewerModalEl) : null;
    const attachmentViewerTitle = document.getElementById("attachmentViewerTitle");
    const attachmentViewerBody = document.getElementById("attachmentViewerBody");
    const btnAttachmentViewerReturn = document.getElementById("btnAttachmentViewerReturn");
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
    const attachmentViewerEmptyState = '<div class="attachment-viewer-empty">Select an attachment to preview.</div>';

    let currentRows = [];
    let currentPage = 1;
    let totalPages = 1;
    let totalItems = 0;
    let activeFilter = "";
    let modalFilters = {
        dateFrom: "",
        dateTo: "",
        complaint_type: [],
        area_number: [],
        sector_membership: [],
    };
    let currentViewCaseId = null;
    let currentDetail = null;
    let pendingComplaintAction = null;
    const OFFICIAL_AREA_OPTIONS = ["Area 01", "Area 1A", "Area 02", "Area 03", "Area 04", "Area 05", "Area 06"];
    const OFFICIAL_SECTOR_OPTIONS = ["PWD", "Senior Citizen", "Student", "Indigenous People", "Single Parent"];

    function setRefreshLoading(on) {
        if (!refreshBtn) return;
        refreshBtn.classList.toggle("is-loading", !!on);
        refreshBtn.disabled = !!on;
    }

    if (endorseBtn) {
        endorseBtn.textContent = "Send for Blotter Review";
    }

    function esc(v) {
        return String(v ?? "").replace(/[&<>\"']/g, (m) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;" }[m]));
    }

    function badge(text, toneClass) {
        return `<span class="status-pill ${esc(toneClass || "archived")}">${esc(text || "-")}</span>`;
    }

    function parseCsvValues(value) {
        return Array.from(new Set(
            String(value ?? "")
                .split(",")
                .map((item) => item.trim())
                .filter(Boolean)
        ));
    }

    function normalizeSectorLabel(value) {
        const raw = String(value ?? "").trim();
        if (!raw) return "";
        const normalized = raw.toLowerCase().replace(/[^a-z]/g, "");
        const map = {
            pwd: "PWD",
            seniorcitizen: "Senior Citizen",
            student: "Student",
            indigenouspeople: "Indigenous People",
            indigenousperson: "Indigenous People",
            singleparent: "Single Parent",
            soloparent: "Single Parent",
        };
        return map[normalized] || raw;
    }

    function parseSectorValues(value) {
        return Array.from(new Set(
            parseCsvValues(value)
                .map((item) => normalizeSectorLabel(item))
                .filter(Boolean)
        ));
    }

    function normalizeDateValue(value) {
        const raw = String(value || "").trim();
        if (!raw) return "";
        const isoMatch = raw.match(/^(\d{4}-\d{2}-\d{2})/);
        if (isoMatch) return isoMatch[1];
        const parsed = new Date(raw);
        if (Number.isNaN(parsed.getTime())) return "";
        const year = parsed.getFullYear();
        const month = String(parsed.getMonth() + 1).padStart(2, "0");
        const day = String(parsed.getDate()).padStart(2, "0");
        return `${year}-${month}-${day}`;
    }

    function renderFilterChecklist(container, field, values) {
        if (!container) return;
        const list = Array.isArray(values) ? values : [];
        if (!list.length) {
            container.innerHTML = `<div class="text-muted small">No options available.</div>`;
            return;
        }
        const active = new Set(Array.isArray(modalFilters[field]) ? modalFilters[field] : []);
        container.innerHTML = list.map((value, index) => `
            <label class="d-flex align-items-center gap-2">
                <input class="form-check-input m-0 complaint-filter-checkbox" type="checkbox" value="${esc(value)}" data-field="${esc(field)}" id="${esc(`complaintFilter_${field}_${index}`)}" ${active.has(value) ? "checked" : ""}>
                <span>${esc(value)}</span>
            </label>
        `).join("");
    }

    function syncFilterOptions(complaintTypes = []) {
        const normalizedComplaintTypes = Array.from(new Set(
            (Array.isArray(complaintTypes) ? complaintTypes : [])
                .map((value) => String(value || "").trim())
                .filter(Boolean)
        )).sort((a, b) => a.localeCompare(b));
        const areaNumbers = OFFICIAL_AREA_OPTIONS.slice();
        const sectors = OFFICIAL_SECTOR_OPTIONS.slice();

        modalFilters.area_number = modalFilters.area_number.filter((value) => areaNumbers.includes(String(value || "").trim()));
        modalFilters.sector_membership = modalFilters.sector_membership
            .map((value) => normalizeSectorLabel(value))
            .filter((value) => sectors.includes(value));

        renderFilterChecklist(filterTypeListEl, "complaint_type", normalizedComplaintTypes);
        renderFilterChecklist(filterAreaListEl, "area_number", areaNumbers);
        renderFilterChecklist(filterSectorListEl, "sector_membership", sectors);
        if (filterDateFromEl) filterDateFromEl.value = modalFilters.dateFrom || "";
        if (filterDateToEl) filterDateToEl.value = modalFilters.dateTo || "";
    }

    function collectModalFilters() {
        const next = {
            dateFrom: String(filterDateFromEl?.value || "").trim(),
            dateTo: String(filterDateToEl?.value || "").trim(),
            complaint_type: [],
            area_number: [],
            sector_membership: [],
        };
        document.querySelectorAll(".complaint-filter-checkbox:checked").forEach((checkbox) => {
            const field = String(checkbox.getAttribute("data-field") || "").trim();
            if (!field || !Array.isArray(next[field])) return;
            next[field].push(String(checkbox.value || "").trim());
        });
        return next;
    }

    function matchesModalFilters(row) {
        const submittedDate = normalizeDateValue(row?.submitted_at_raw);
        if (modalFilters.dateFrom && (!submittedDate || submittedDate < modalFilters.dateFrom)) return false;
        if (modalFilters.dateTo && (!submittedDate || submittedDate > modalFilters.dateTo)) return false;
        if (modalFilters.complaint_type.length && !modalFilters.complaint_type.includes(String(row?.complaint_type || "").trim())) return false;
        if (modalFilters.area_number.length && !modalFilters.area_number.includes(String(row?.area_number || "").trim())) return false;
        if (modalFilters.sector_membership.length) {
            const memberships = parseSectorValues(row?.sector_membership);
            const hasSector = modalFilters.sector_membership
                .map((value) => normalizeSectorLabel(value))
                .some((value) => memberships.includes(value));
            if (!hasSector) return false;
        }
        return true;
    }

    function setActionButtonsState(detail) {
        if (!complaintActionButtons) return;
        if (!detail) {
            complaintActionButtons.classList.add("d-none");
            return;
        }
        const status = String(detail?.status_name || "").trim().toLowerCase();
        const hasLinkedBlotter = String(detail?.blotter_id || "").trim() !== "";
        const requestStatus = String(detail?.blotter_request_status || "").trim().toLowerCase();
        const hasOpenRequest = ["pending", "approved"].includes(requestStatus);
        const isFinal = ["resolved", "dropped"].includes(status) || (status === "endorsed" && hasLinkedBlotter) || hasOpenRequest;
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

    function emptyState(message = "No complaint records found.", toneClass = "text-center text-muted") {
        return `
            <tr>
                <td colspan="8" class="complaint-table-empty ${toneClass} py-4">${esc(message)}</td>
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

    function renderPagination() {
        if (!paginationEl) return;
        currentPage = Math.min(currentPage, totalPages);

        const makeBtn = (label, page, disabled = false, active = false) => `
            <li class="page-item ${disabled ? "disabled" : ""} ${active ? "active" : ""}">
                <button class="page-link" data-page="${page}" ${disabled ? "disabled" : ""}>${label}</button>
            </li>
        `;

        const html = [];
        html.push(makeBtn("Prev", currentPage - 1, currentPage <= 1));

        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, startPage + 4);
        startPage = Math.max(1, endPage - 4);

        if (startPage > 1) {
            html.push(makeBtn("1", 1, false, currentPage === 1));
            if (startPage > 2) {
                html.push(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
            }
        }

        for (let page = startPage; page <= endPage; page += 1) {
            html.push(makeBtn(String(page), page, false, page === currentPage));
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                html.push(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
            }
            html.push(makeBtn(String(totalPages), totalPages, false, currentPage === totalPages));
        }

        html.push(makeBtn("Next", currentPage + 1, currentPage >= totalPages));
        paginationEl.innerHTML = html.join("");

        paginationEl.querySelectorAll("button[data-page]").forEach((button) => {
            button.addEventListener("click", () => {
                const page = Number(button.getAttribute("data-page") || 1);
                if (!Number.isFinite(page)) return;
                currentPage = page;
                loadList();
            });
        });
    }

    function renderTable() {
        if (!tableBody) return;
        if (!currentRows.length) {
            tableBody.innerHTML = emptyState();
        } else {
            tableBody.innerHTML = currentRows.map(buildTableRow).join("");
        }

        renderPagination();

        tableBody.querySelectorAll("button[data-view-id]").forEach((button) => {
            button.addEventListener("click", () => {
                const caseId = String(button.getAttribute("data-view-id") || "").trim();
                if (!caseId) return;
                openViewModal(caseId);
            });
        });
    }

    function buildListUrl() {
        const params = new URLSearchParams();
        params.set("action", "list");
        params.set("page", String(currentPage));
        params.set("per_page", String(Math.max(1, Number(entriesPerPageInput?.value || 20))));
        const searchTerm = String(searchInput?.value || "").trim();
        if (searchTerm) params.set("search", searchTerm);
        if (activeFilter) params.set("status", activeFilter);
        if (modalFilters.dateFrom) params.set("date_from", modalFilters.dateFrom);
        if (modalFilters.dateTo) params.set("date_to", modalFilters.dateTo);
        if (modalFilters.complaint_type.length) params.set("complaint_type", modalFilters.complaint_type.join(","));
        if (modalFilters.area_number.length) params.set("area_number", modalFilters.area_number.join(","));
        if (modalFilters.sector_membership.length) params.set("sector_membership", modalFilters.sector_membership.join(","));
        return `${endpoint}?${params.toString()}`;
    }

    function updatePendingBadge(count) {
        if (!pendingComplaintBadge) return;
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

    function applyListResponse(data) {
        const meta = data?.meta || {};
        const pagination = meta.pagination || {};
        currentRows = Array.isArray(data?.items) ? data.items : [];
        currentPage = Math.max(1, Number(pagination.page || currentPage || 1));
        totalPages = Math.max(1, Number(pagination.total_pages || 1));
        totalItems = Math.max(0, Number(pagination.total_items || currentRows.length || 0));
        updatePendingBadge(Number(meta.badges?.pending_count || 0));
        syncFilterOptions(meta.filters?.complaint_types || []);
        renderTable();
    }

    function formField(label, value, raw = false, fullWidth = false) {
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
        return `<div class="tracker-form-grid ${cls}">${clean.map((f) => formField(f.label, f.value, !!f.raw, !!f.fullWidth)).join("")}</div>`;
    }

    function renderAddressFieldGrid(fields) {
        const clean = (Array.isArray(fields) ? fields : []).filter((f) => f && String(f.value ?? "").trim() !== "");
        if (!clean.length) return "";
        return `<div class="tracker-form-grid cols-1">${clean.map((f) => formField(f.label, f.value, !!f.raw, true)).join("")}</div>`;
    }

    function renderComplaintSpecificFieldGrid(fields) {
        const clean = (Array.isArray(fields) ? fields : []).filter((field) => field && String(field.value ?? "").trim() !== "");
        if (!clean.length) return "";
        return renderFieldGrid(clean.map((field) => ({
            label: field.label || field.name || "Detail",
            value: field.value || "-",
        })), 2);
    }

    function resolveAttachmentUrl(path) {
        const rawPath = String(path || "").trim();
        if (!rawPath) return "";
        if (/^(?:https?:)?\/\//i.test(rawPath) || rawPath.startsWith("data:")) {
            return rawPath;
        }
        if (appBase && (rawPath === appBase || rawPath.startsWith(`${appBase}/`))) {
            return rawPath;
        }
        if (rawPath.startsWith("/")) {
            return `${appBase}${rawPath}`;
        }
        return `${appBase}/${rawPath}`.replace(/\/{2,}/g, "/");
    }

    function isImageAttachment(attachment) {
        const source = `${attachment?.name || ""} ${attachment?.path || ""}`.toLowerCase();
        return /\.(png|jpe?g|webp|gif|bmp|svg)(?:$|\?)/i.test(source);
    }

    function openAttachmentViewer(name, url, isImage = true) {
        if (!attachmentViewerModal || !attachmentViewerBody) {
            return;
        }

        const safeName = String(name || "Attachment").trim() || "Attachment";
        const safeUrl = String(url || "").trim();
        if (attachmentViewerTitle) {
            attachmentViewerTitle.textContent = safeName;
        }

        if (!safeUrl) {
            attachmentViewerBody.innerHTML = '<div class="attachment-viewer-empty">Attachment preview is unavailable.</div>';
            transitionModal(viewModalEl, viewModal, attachmentViewerModal);
            return;
        }

        attachmentViewerBody.innerHTML = isImage
            ? `<img src="${esc(safeUrl)}" alt="${esc(safeName)}">`
            : `<iframe src="${esc(safeUrl)}" title="${esc(safeName)}"></iframe>`;
        transitionModal(viewModalEl, viewModal, attachmentViewerModal);
    }

    function renderAttachmentList(attachments) {
        const clean = (Array.isArray(attachments) ? attachments : []).filter((attachment) => attachment && String(attachment.path ?? "").trim() !== "");
        if (!clean.length) return "";
        return `
            <div class="complaint-attachment-grid">
                ${clean.map((attachment, index) => {
            const name = String(attachment.name || `Attachment ${index + 1}`).trim() || `Attachment ${index + 1}`;
            const href = resolveAttachmentUrl(attachment.path || "");
            const previewable = isImageAttachment(attachment);
            const previewShell = previewable
                ? `<button type="button" class="complaint-attachment-preview" data-attachment-view="true" data-attachment-src="${esc(href)}" data-attachment-name="${esc(name)}" data-attachment-image="true">
                        <img src="${esc(href)}" alt="${esc(name)}" loading="lazy">
                   </button>`
                : `<button type="button" class="complaint-attachment-preview" data-attachment-view="true" data-attachment-src="${esc(href)}" data-attachment-name="${esc(name)}" data-attachment-image="false">
                        <div class="complaint-attachment-preview-placeholder">
                            <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
                            <span>Preview attachment</span>
                        </div>
                   </button>`;
            return `
                <article class="complaint-attachment-card">
                    ${previewShell}
                    <div class="complaint-attachment-body">
                        <p class="complaint-attachment-name">${esc(name)}</p>
                        <div class="complaint-attachment-actions">
                            <button type="button" class="btn btn-sm btn-primary" data-attachment-view="true" data-attachment-src="${esc(href)}" data-attachment-name="${esc(name)}" data-attachment-image="${previewable ? "true" : "false"}">View Attachment</button>
                        </div>
                    </div>
                </article>
            `;
        }).join("")}
            </div>
        `;
    }

    function renderWitnessSection(witnesses, witnessSummary) {
        const clean = (Array.isArray(witnesses) ? witnesses : []).filter((witness) => {
            return witness && (
                String(witness.full_name ?? "").trim() !== ""
                || String(witness.contact_number ?? "").trim() !== ""
                || String(witness.address ?? "").trim() !== ""
            );
        });

        if (!clean.length) {
            return renderFieldGrid([
                { label: "Witness Summary", value: witnessSummary || "-" },
            ], 1);
        }

        return clean.map((witness, index) => {
            return [
                `<div class="small fw-semibold text-uppercase text-muted mb-2">Witness ${index + 1}</div>`,
                renderFieldGrid([
                    { label: "Full Name", value: witness.full_name || "-" },
                    { label: "Contact Number", value: witness.contact_number || "-" },
                ], 2),
                renderFieldGrid([
                    { label: "Address", value: witness.address || "-" },
                ], 1),
            ].join("");
        }).join('<div class="mt-3"></div>');
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

    function displayIncidentPlace(place, areaNumber) {
        const rawPlace = String(place || "").trim();
        const rawArea = String(areaNumber || "").trim();
        if (!rawPlace) return "";
        if (!rawArea) return rawPlace;

        const escapedArea = rawArea.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
        return rawPlace.replace(new RegExp(`^${escapedArea}\\s*-\\s*`, "i"), "").trim() || rawPlace;
    }

    function renderIntakeNotesEditor(value) {
        const normalizedValue = String(value ?? "").trim();
        return `
            <div class="tracker-form-field">
                <p class="tracker-form-label">Intake Notes</p>
                <div class="tracker-form-value complaint-intake-editor">
                    <textarea id="complaintIntakeNotes" class="form-control" rows="4" placeholder="Add intake notes...">${esc(normalizedValue)}</textarea>
                    <div class="complaint-intake-actions mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnSaveComplaintIntakeNotes">Save Intake Notes</button>
                    </div>
                </div>
            </div>
        `;
    }

    function buildBlotterRequestNotice(detail) {
        const status = String(detail?.blotter_request_status || "").trim().toLowerCase();
        const notes = String(detail?.blotter_request_notes || "").trim();

        if (status === "pending" || status === "approved") {
            return "Blotter request is still under review.";
        }

        if (status === "rejected") {
            return notes !== ""
                ? `Previous blotter request was rejected. Review remarks: ${notes}`
                : "Previous blotter request was rejected.";
        }

        return "";
    }

    function hasVisibleBlotterRequestDetails(detail) {
        const status = String(detail?.blotter_request_status || "").trim().toLowerCase();
        return status === "pending" || status === "approved";
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
        if (actionType === "endorsement") title = "Send Complaint for Blotter Review";
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
            if (pendingComplaintAction === "endorsement") actionText = "send this complaint to blotter review";
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

    attachmentViewerModalEl?.addEventListener("hidden.bs.modal", () => {
        if (attachmentViewerBody) {
            attachmentViewerBody.innerHTML = attachmentViewerEmptyState;
        }
    });

    btnAttachmentViewerReturn?.addEventListener("click", () => {
        transitionModal(attachmentViewerModalEl, attachmentViewerModal, viewModal);
    });

    viewDetailsBody?.addEventListener("click", (event) => {
        const trigger = event.target.closest("[data-attachment-view]");
        if (!trigger) {
            return;
        }

        event.preventDefault();
        const src = String(trigger.getAttribute("data-attachment-src") || "").trim();
        const name = String(trigger.getAttribute("data-attachment-name") || "Attachment").trim() || "Attachment";
        const imageFlag = String(trigger.getAttribute("data-attachment-image") || "").trim() === "true";
        openAttachmentViewer(name, src, imageFlag);
    });

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
                { label: "Incident Place", value: displayIncidentPlace(d.incident_place || "", d.incident_area_number || "") || "-" },
                { label: "Area Number", value: d.incident_area_number || "-" },
            ], 2);

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

            const witnessGrid = renderWitnessSection(d.witnesses || [], d.witness_summary || "");
            const complaintSpecificGrid = renderComplaintSpecificFieldGrid(d.complaint_detail_fields || []);
            const attachmentGrid = renderAttachmentList(d.attachments || []);

            const intakeNotesSection = formSection("Intake Notes", renderIntakeNotesEditor(d.intake_notes || ""));
            const blotterRequestNotice = buildBlotterRequestNotice(d);
            const showBlotterRequestDetails = hasVisibleBlotterRequestDetails(d);

            const notesGrid = [
                renderFieldGrid([
                    { label: "Case Remarks", value: d.case_remarks || "-" },
                    { label: "Escalated to Blotter", value: Number(d.escalated_to_blotter || 0) === 1 ? `Yes${d.blotter_id ? ` (${d.blotter_id})` : ""}` : "No" },
                ], 2),
                showBlotterRequestDetails ? renderFieldGrid([
                    { label: "Blotter Request ID", value: d.blotter_request_id || "-" },
                    { label: "Blotter Request Status", value: d.blotter_request_status || "-" },
                    { label: "Request Submitted", value: d.blotter_request_requested_at || "-" },
                    { label: "Request Reviewed", value: d.blotter_request_reviewed_at || "-" },
                ], 2) : "",
                renderFieldGrid([
                    { label: "Resident Narration", value: d.complaint_narration || d.case_details || "-" },
                ], 1),
                renderFieldGrid([
                    { label: "Screening Notes", value: d.screening_notes || "-" },
                    ...(showBlotterRequestDetails ? [{ label: "Request Review Notes", value: d.blotter_request_notes || "-" }] : []),
                    ...(blotterRequestNotice ? [{ label: "Blotter Request Note", value: blotterRequestNotice }] : []),
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
                complaintSpecificGrid ? formSection("Complaint-Specific Information", complaintSpecificGrid) : "",
                formSection("Witness Information", witnessGrid),
                attachmentGrid ? formSection("Attachments", attachmentGrid) : "",
                intakeNotesSection,
                formSection("Narration and Notes", notesGrid),
            ].join("");

            viewDetailsBody.innerHTML = html || '<div class="text-muted">No details available.</div>';
            currentDetail = d;
            setActionButtonsState(d);

            const intakeNotesField = document.getElementById("complaintIntakeNotes");
            const saveIntakeNotesBtn = document.getElementById("btnSaveComplaintIntakeNotes");
            saveIntakeNotesBtn?.addEventListener("click", async () => {
                const intakeNotes = String(intakeNotesField?.value || "").trim();
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
        const url = buildListUrl();
        tableBody.innerHTML = emptyState("Loading complaint records...");
        setRefreshLoading(true);

        try {
            const data = await fetchJson(url);
            applyListResponse(data);
        } catch (error) {
            tableBody.innerHTML = emptyState(error.message || "Failed to load complaint records.", "text-center text-danger");
        } finally {
            setRefreshLoading(false);
        }
    }

    let searchTimer = null;
    searchInput?.addEventListener("input", () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            currentPage = 1;
            loadList();
        }, 180);
    });

    entriesPerPageInput?.addEventListener("change", () => {
        currentPage = 1;
        loadList();
    });

    refreshBtn?.addEventListener("click", () => {
        loadList();
    });

    btnComplaintFilterApply?.addEventListener("click", () => {
        modalFilters = collectModalFilters();
        currentPage = 1;
        loadList();
        if (filterModalEl) bootstrap.Modal.getInstance(filterModalEl)?.hide();
    });

    btnComplaintFilterReset?.addEventListener("click", () => {
        modalFilters = {
            dateFrom: "",
            dateTo: "",
            complaint_type: [],
            area_number: [],
            sector_membership: [],
        };
        if (filterDateFromEl) filterDateFromEl.value = "";
        if (filterDateToEl) filterDateToEl.value = "";
        document.querySelectorAll(".complaint-filter-checkbox").forEach((checkbox) => {
            checkbox.checked = false;
        });
        currentPage = 1;
        loadList();
    });

    filterButtons.forEach((button) => {
        button.addEventListener("click", () => {
            filterButtons.forEach((btn) => {
                btn.classList.remove("btn-outline-primary", "active");
                btn.classList.add("btn-outline-secondary");
            });
            button.classList.remove("btn-outline-secondary");
            button.classList.add("btn-outline-primary", "active");
            activeFilter = String(button.dataset.filter || "").trim().toLowerCase();
            currentPage = 1;
            loadList();
        });
    });

    initComplaintActionFlow();
    loadList();
})();
