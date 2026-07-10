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
    const complaintSubtabsEl = document.getElementById("complaintSubtabs");
    const filterButtons = Array.from(document.querySelectorAll(".complaint-status-scope-tab"));
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
    const complaintActionToggle = complaintActionButtons?.querySelector(".dropdown-toggle") || null;
    const investigateBtn = document.getElementById("btnComplaintInvestigate");
    const actionInProgressBtn = document.getElementById("btnComplaintActionInProgress");
    const resolveBtn = document.getElementById("btnComplaintResolve");
    const closeBtn = document.getElementById("btnComplaintClose");
    const endorseBtn = document.getElementById("btnComplaintEndorse");
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
    const MAX_COMPLAINT_ATTACHMENTS_PER_UPLOAD = 3;
    const MAX_COMPLAINT_ATTACHMENTS_TOTAL = 6;
    const MAX_COMPLAINT_WITNESSES = 10;

    let currentRows = [];
    let currentPage = 1;
    let totalPages = 1;
    let totalItems = 0;
    let activeFilter = "";
    let activeSubStatus = "";
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
    const complaintSubtabConfig = {
        "": [
            { value: "", label: "All" },
            { value: "active", label: "Active" },
            { value: "closed", label: "Closed" },
        ],
        active: [
            { value: "", label: "All" },
            { value: "received", label: "Received" },
            { value: "under_investigation", label: "Under Investigation" },
            { value: "action_in_progress", label: "Action In Progress" },
        ],
        resolved: [
            { value: "", label: "Resolved" },
        ],
        finalized: [
            { value: "", label: "All" },
            { value: "dropped", label: "Dropped" },
            { value: "referred", label: "Referred" },
        ],
    };
    const complaintActionButtonsByType = {
        under_investigation: investigateBtn,
        action_in_progress: actionInProgressBtn,
        resolved: resolveBtn,
        dropped: closeBtn,
        referred: endorseBtn,
    };
    const complaintActionItemsByType = Object.fromEntries(
        Object.entries(complaintActionButtonsByType).map(([actionType, button]) => [actionType, button?.closest("li") || null])
    );

    function setRefreshLoading(on) {
        if (!refreshBtn) return;
        refreshBtn.classList.toggle("is-loading", !!on);
        refreshBtn.disabled = !!on;
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

    function normalizeComplaintStatus(value) {
        const status = String(value || "").trim().toLowerCase();
        if (status === "" || status === "active" || status === "pending" || status.includes("receive")) return "received";
        if (status.includes("under investigation")) return "under_investigation";
        if (status.includes("action in progress")) return "action_in_progress";
        if (status.includes("resolved") || status.includes("completed")) return "resolved";
        if (status.includes("drop")) return "dropped";
        if (status.includes("refer") || status.includes("endorse")) return "referred";
        return status.replace(/[^a-z0-9]+/g, "_");
    }

    function getComplaintSubtabOptions() {
        return complaintSubtabConfig[activeFilter] || complaintSubtabConfig[""];
    }

    function renderComplaintSubtabs() {
        if (!complaintSubtabsEl) return;
        const options = getComplaintSubtabOptions();
        const validValues = new Set(options.map((option) => option.value));
        if (!validValues.has(activeSubStatus)) {
            activeSubStatus = "";
        }
        complaintSubtabsEl.innerHTML = options.map((option) => `
            <button
                type="button"
                class="complaint-subtab-btn${option.value === activeSubStatus ? " active" : ""}"
                data-sub-status="${esc(option.value)}"
            >${esc(option.label)}</button>
        `).join("");

        complaintSubtabsEl.querySelectorAll("[data-sub-status]").forEach((button) => {
            button.addEventListener("click", () => {
                const nextValue = String(button.getAttribute("data-sub-status") || "").trim().toLowerCase();
                if (nextValue === activeSubStatus) return;
                activeSubStatus = nextValue;
                currentPage = 1;
                renderComplaintSubtabs();
                loadList();
            });
        });
    }

    function complaintActionConfig(actionType) {
        const map = {
            under_investigation: {
                title: "Start Investigation",
                actionText: "start investigation for this complaint",
                placeholder: "Add investigation notes...",
            },
            action_in_progress: {
                title: "Start Action",
                actionText: "move this complaint to Action in Progress",
                placeholder: "Add action in progress notes...",
            },
            resolved: {
                title: "Mark Complaint Resolved",
                actionText: "mark this complaint as resolved",
                placeholder: "Add resolution notes...",
            },
            dropped: {
                title: "Drop Complaint",
                actionText: "drop this complaint",
                placeholder: "Enter the admin reason for dropping this complaint...",
            },
            referred: {
                title: "Refer Complaint",
                actionText: "refer this complaint to another department",
                placeholder: "Add referral notes for the receiving department...",
            },
        };
        return map[actionType] || {
            title: "Update Complaint",
            actionText: "update this complaint",
        };
    }

    function setActionButtonsState(detail) {
        if (!complaintActionButtons) return;

        Object.entries(complaintActionButtonsByType).forEach(([actionType, button]) => {
            button?.classList.add("d-none");
            complaintActionItemsByType[actionType]?.classList.add("d-none");
        });
        if (complaintActionToggle) {
            complaintActionToggle.disabled = false;
        }

        if (!detail) {
            complaintActionButtons.classList.add("d-none");
            return;
        }

        if (!isComplaintClassificationReady(detail)) {
            complaintActionButtons.classList.add("d-none");
            if (complaintActionToggle) {
                complaintActionToggle.disabled = true;
            }
            return;
        }

        const statusKey = normalizeComplaintStatus(detail?.status_name || "");
        const hasLinkedBlotter = String(detail?.blotter_id || "").trim() !== "";
        const requestStatus = String(detail?.blotter_request_status || "").trim().toLowerCase();
        const hasOpenRequest = ["pending", "approved"].includes(requestStatus);
        const isFinal = ["resolved", "dropped", "referred"].includes(statusKey) || hasOpenRequest || hasLinkedBlotter;
        complaintActionButtons.classList.toggle("d-none", isFinal);
        if (isFinal) {
            return;
        }

        const visibleActions = new Set(["referred", "dropped"]);
        if (statusKey === "received") {
            visibleActions.add("under_investigation");
        } else if (statusKey === "under_investigation") {
            visibleActions.add("action_in_progress");
            visibleActions.add("resolved");
        } else if (statusKey === "action_in_progress") {
            visibleActions.add("resolved");
        } else {
            visibleActions.add("under_investigation");
            visibleActions.add("action_in_progress");
            visibleActions.add("resolved");
        }

        visibleActions.forEach((actionType) => {
            complaintActionButtonsByType[actionType]?.classList.remove("d-none");
            complaintActionItemsByType[actionType]?.classList.remove("d-none");
        });
    }

    function toneForStatus(row) {
        const statusKey = normalizeComplaintStatus(row?.status_name || "");
        if (statusKey === "referred") return "archived";

        const requestStatus = String(row?.blotter_request_status || "").trim().toLowerCase();
        if (Number(row?.escalated_to_blotter || 0) === 1 || ["pending", "approved"].includes(requestStatus)) return "info";

        if (statusKey === "received") return "pending";
        if (statusKey === "under_investigation" || statusKey === "action_in_progress" || statusKey === "referred") return "info";
        if (statusKey === "resolved") return "approved";
        if (statusKey === "dropped") return "denied";
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
                <td>${badge(row.status_name || "Received", toneForStatus(row))}</td>
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
        if (activeSubStatus) params.set("sub_status", activeSubStatus);
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

    function formatAttachmentMetaText(attachment, fallbackSubmittedAt = "") {
        const uploadedAt = String(attachment?.uploaded_at || "").trim();
        const uploadedBy = String(attachment?.uploaded_by || "").trim();
        const source = String(attachment?.source || "").trim();
        const metaParts = [];

        if (uploadedAt) {
            metaParts.push(`Uploaded: ${uploadedAt}`);
        } else if (String(fallbackSubmittedAt || "").trim() !== "") {
            metaParts.push(`Uploaded: ${fallbackSubmittedAt}`);
        }

        if (source) {
            metaParts.push(`Source: ${source}`);
        }
        if (uploadedBy) {
            metaParts.push(`By: ${uploadedBy}`);
        }

        return metaParts.length
            ? metaParts.join(" | ")
            : `Attachment ${isImageAttachment(attachment) ? "image" : "file"} ready for viewing`;
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

    function renderAttachmentList(attachments, submittedAt = "") {
        const clean = (Array.isArray(attachments) ? attachments : []).filter((attachment) => attachment && String(attachment.path ?? "").trim() !== "");
        if (!clean.length) return "";
        return `
            <div class="complaint-attachment-grid">
                ${clean.map((attachment, index) => {
            const name = String(attachment.name || `Attachment ${index + 1}`).trim() || `Attachment ${index + 1}`;
            const href = resolveAttachmentUrl(attachment.path || "");
            const previewable = isImageAttachment(attachment);
            const uploadedText = formatAttachmentMetaText(attachment, submittedAt);
            return `
                <article class="complaint-attachment-card">
                    <div class="complaint-attachment-body">
                        <p class="complaint-attachment-name">${esc(name)}</p>
                        <p class="complaint-attachment-meta">${esc(uploadedText)}</p>
                    </div>
                    <div class="complaint-attachment-actions">
                        <button type="button" class="btn btn-sm btn-primary" data-attachment-view="true" data-attachment-src="${esc(href)}" data-attachment-name="${esc(name)}" data-attachment-image="${previewable ? "true" : "false"}">View</button>
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
        const existingCount = clean.length;
        const remainingSlots = Math.max(0, MAX_COMPLAINT_WITNESSES - existingCount);

        const witnessListHtml = !clean.length
            ? renderFieldGrid([
                { label: "Witness Summary", value: witnessSummary || "-" },
            ], 1)
            : clean.map((witness, index) => {
            return [
                `<div class="small fw-semibold text-uppercase text-muted mb-2">Witness ${index + 1}</div>`,
                renderFieldGrid([
                    { label: "Full Name", value: witness.full_name || "-" },
                    { label: "Contact Number", value: witness.contact_number || "-" },
                ], 2),
                renderFieldGrid([
                    { label: "Address", value: witness.address || "-" },
                ], 1),
                renderFieldGrid([
                    { label: "Remarks", value: witness.remarks || "-" },
                ], 1),
            ].join("");
        }).join('<div class="mt-3"></div>');

        const witnessRowsHtml = Array.from({ length: remainingSlots }, (_, index) => {
            const witnessNumber = existingCount + index + 1;
            return `
                <div class="complaint-witness-entry${index === 0 ? "" : " d-none"}" data-complaint-witness-row="${index}">
                    <div class="complaint-witness-editor complaint-witness-editor--row">
                        <button type="button" class="attachment-close-btn witness-remove-btn" data-complaint-witness-remove aria-label="Remove witness ${witnessNumber}">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <div class="small fw-semibold text-uppercase text-muted">Witness ${witnessNumber}</div>
                        <div class="row g-2">
                            <div class="col-12 col-lg-4">
                                <label class="tracker-form-label" for="complaintWitnessLastName_${index}">Last Name <span class="text-danger">*</span></label>
                                <input type="text" id="complaintWitnessLastName_${index}" class="form-control" data-complaint-witness-last-name placeholder="Enter last name">
                            </div>
                            <div class="col-12 col-lg-4">
                                <label class="tracker-form-label" for="complaintWitnessFirstName_${index}">First Name <span class="text-danger">*</span></label>
                                <input type="text" id="complaintWitnessFirstName_${index}" class="form-control" data-complaint-witness-first-name placeholder="Enter first name">
                            </div>
                            <div class="col-12 col-lg-2">
                                <label class="tracker-form-label" for="complaintWitnessMiddleName_${index}">Middle Initial</label>
                                <input type="text" id="complaintWitnessMiddleName_${index}" class="form-control" data-complaint-witness-middle-name maxlength="10" placeholder="Optional">
                            </div>
                            <div class="col-12 col-lg-2">
                                <label class="tracker-form-label" for="complaintWitnessSuffix_${index}">Suffix</label>
                                <select id="complaintWitnessSuffix_${index}" class="form-select" data-complaint-witness-suffix>
                                    <option value="">None</option>
                                    <option value="Jr.">Jr.</option>
                                    <option value="Sr.">Sr.</option>
                                    <option value="III">III</option>
                                    <option value="IV">IV</option>
                                </select>
                            </div>
                            <div class="col-12 col-lg-4">
                                <label class="tracker-form-label" for="complaintWitnessContactNumber_${index}">Contact Number <span class="text-danger">*</span></label>
                                <input type="text" id="complaintWitnessContactNumber_${index}" class="form-control" data-complaint-witness-contact inputmode="numeric" maxlength="11" placeholder="09XXXXXXXXX">
                            </div>
                            <div class="col-12">
                                <label class="tracker-form-label" for="complaintWitnessAddress_${index}">Address</label>
                                <input type="text" id="complaintWitnessAddress_${index}" class="form-control" data-complaint-witness-address placeholder="Optional">
                            </div>
                            <div class="col-12">
                                <label class="tracker-form-label" for="complaintWitnessRemarks_${index}">Remarks</label>
                                <textarea id="complaintWitnessRemarks_${index}" class="form-control" data-complaint-witness-remarks rows="3" placeholder="Optional witness notes"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join("");

        const addButtonLabel = existingCount > 0 ? "Add More Witnesses" : "Add Witness";

        return `
            <div class="complaint-witness-section">
                ${witnessListHtml}
                <div class="complaint-witness-trigger">
                    ${remainingSlots > 0
                        ? `<button type="button" class="btn btn-outline-primary" id="btnAddComplaintWitness">${addButtonLabel}</button>`
                        : `<div class="complaint-admin-warning">The maximum of ${MAX_COMPLAINT_WITNESSES} witnesses has already been reached for this complaint.</div>`}
                </div>
                ${remainingSlots > 0 ? `
                    <div class="complaint-witness-editor d-none" id="complaintWitnessEditor">
                        <div class="complaint-admin-helper">Admins can add confirmed witness details here. Up to ${MAX_COMPLAINT_WITNESSES} witnesses are allowed per complaint. ${remainingSlots} slot${remainingSlots === 1 ? "" : "s"} remaining.</div>
                        <div class="complaint-witness-entries" id="complaintWitnessEntries">${witnessRowsHtml}</div>
                        <div class="complaint-witness-editor-actions mt-2">
                            ${remainingSlots > 1 ? '<button type="button" class="btn btn-outline-secondary" id="btnAddAnotherComplaintWitness">Add Another Witness</button>' : ""}
                            <button type="button" class="btn btn-outline-secondary" id="btnCancelComplaintWitness">Cancel</button>
                            <button type="button" class="btn btn-primary" id="btnSaveComplaintWitness">Save Witnesses</button>
                        </div>
                    </div>
                ` : ""}
            </div>
        `;
    }

    function renderAttachmentUploadEditor(detail) {
        const attachments = (Array.isArray(detail?.attachments) ? detail.attachments : []).filter((attachment) => attachment && String(attachment.path ?? "").trim() !== "");
        const remainingSlots = Math.max(0, MAX_COMPLAINT_ATTACHMENTS_TOTAL - attachments.length);
        if (!remainingSlots) {
            return `<div class="complaint-admin-warning">This complaint already has the maximum of ${MAX_COMPLAINT_ATTACHMENTS_TOTAL} attachments.</div>`;
        }

        const classificationReady = isComplaintClassificationReady(detail);
        const helperText = !classificationReady
            ? "Set the official complaint classification first before uploading admin attachments."
            : `Admins can upload up to ${Math.min(MAX_COMPLAINT_ATTACHMENTS_PER_UPLOAD, remainingSlots)} image attachment${Math.min(MAX_COMPLAINT_ATTACHMENTS_PER_UPLOAD, remainingSlots) === 1 ? "" : "s"} right now. ${remainingSlots} total slot${remainingSlots === 1 ? "" : "s"} remaining.`;

        return `
            <div class="complaint-admin-editor">
                <div class="${classificationReady ? "complaint-admin-helper" : "complaint-admin-warning"}">${esc(helperText)}</div>
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-lg-8">
                        <label class="tracker-form-label" for="complaintAdminAttachments">Add Admin Attachments</label>
                        <input type="file" id="complaintAdminAttachments" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple ${classificationReady ? "" : "disabled"}>
                        <div class="small text-muted mt-2" id="complaintAdminAttachmentSelection">JPG, JPEG, PNG, or WEBP only. Max 5 MB each.</div>
                    </div>
                    <div class="col-12 col-lg-4 d-grid">
                        <button type="button" class="btn btn-outline-primary" id="btnSaveComplaintAttachments" ${classificationReady ? "" : "disabled"}>Upload Attachments</button>
                    </div>
                </div>
            </div>
        `;
    }

    function normalizeComplaintWitnessContact(value) {
        return String(value || "").replace(/\D+/g, "").slice(0, 11);
    }

    function isValidComplaintWitnessContact(value) {
        return /^09\d{9}$/.test(String(value || "").trim());
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

    function getClassificationOptions(detail) {
        return Array.from(new Set(
            (Array.isArray(detail?.classification_options) ? detail.classification_options : [])
                .map((value) => String(value || "").trim())
                .filter(Boolean)
        ));
    }

    function isComplaintClassificationReady(detail) {
        const current = String(detail?.complaint_type || "").trim();
        return current !== "" && getClassificationOptions(detail).includes(current);
    }

    function renderClassificationEditor(detail) {
        const options = getClassificationOptions(detail);
        const currentValue = String(detail?.complaint_type || "").trim();
        const isStandard = isComplaintClassificationReady(detail);
        const selectedValue = isStandard ? currentValue : "";
        const warningHtml = !isStandard
            ? `<div class="complaint-admin-warning">Select the official complaint classification before any admin action so records and reports stay consistent.${currentValue ? ` Current saved value: ${esc(currentValue)}.` : ""}</div>`
            : `<div class="complaint-admin-helper">This classification is used in complaint filters and generated reports.</div>`;

        return `
            <div class="complaint-admin-editor">
                ${warningHtml}
                <div class="complaint-admin-editor-row">
                    <div class="complaint-admin-editor-field">
                        <label class="tracker-form-label" for="complaintAdminClassification">Admin Classification</label>
                        <select id="complaintAdminClassification" class="form-select">
                            <option value="">Select classification</option>
                            ${options.map((option) => `<option value="${esc(option)}" ${selectedValue === option ? "selected" : ""}>${esc(option)}</option>`).join("")}
                        </select>
                    </div>
                    <div class="complaint-admin-editor-actions">
                        <button type="button" class="btn btn-primary" id="btnSaveComplaintClassification">Save Classification</button>
                    </div>
                </div>
            </div>
        `;
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

    function parseComplaintStageNotes(detail) {
        const caseRemarks = String(detail?.case_remarks || "").trim();
        const screeningNotes = String(detail?.screening_notes || "").trim();
        const statusKey = normalizeComplaintStatus(detail?.status_name || "");

        const result = {
            residentNarration: String(detail?.complaint_narration || detail?.case_details || "").trim(),
            initialInvestigation: "",
            progressedAction: "",
            resolution: "",
            caseSummary: "",
            screeningNotes,
        };

        if (caseRemarks !== "") {
            const summaryParts = [];
            caseRemarks
                .split(/\r?\n+/)
                .map((line) => line.trim())
                .filter(Boolean)
                .forEach((line) => {
                    const match = line.match(/^Screening notes \(([^)]+)\):\s*(.+)$/i);
                    if (!match) {
                        summaryParts.push(line);
                        return;
                    }

                    const stageLabel = normalizeComplaintStatus(match[1] || "");
                    const noteValue = String(match[2] || "").trim();
                    if (!noteValue) {
                        return;
                    }

                    if (stageLabel === "under_investigation") {
                        result.initialInvestigation = noteValue;
                        return;
                    }
                    if (stageLabel === "action_in_progress") {
                        result.progressedAction = noteValue;
                        return;
                    }
                    if (["resolved", "dropped", "referred"].includes(stageLabel)) {
                        result.resolution = noteValue;
                        return;
                    }

                    summaryParts.push(line);
                });

            result.caseSummary = summaryParts.join("\n").trim();
        }

        if (result.initialInvestigation === "" && statusKey === "under_investigation" && screeningNotes !== "") {
            result.initialInvestigation = screeningNotes;
        }
        if (result.progressedAction === "" && statusKey === "action_in_progress" && screeningNotes !== "") {
            result.progressedAction = screeningNotes;
        }
        if (result.resolution === "" && ["resolved", "dropped", "referred"].includes(statusKey) && screeningNotes !== "") {
            result.resolution = screeningNotes;
        }
        if (result.initialInvestigation === "" && screeningNotes !== "" && result.progressedAction === "" && result.resolution === "") {
            result.initialInvestigation = screeningNotes;
        }

        return result;
    }

    function renderComplaintNoteCard(title, subtitle, body, toneClass) {
        const normalizedBody = String(body || "").trim();
        if (normalizedBody === "") {
            return "";
        }
        const subtitleHtml = String(subtitle || "").trim() !== ""
            ? `<p class="complaint-note-card-subtitle">${esc(subtitle)}</p>`
            : "";
        return `
            <article class="complaint-note-card ${esc(toneClass)}">
                <div class="complaint-note-card-header">
                    <h6 class="complaint-note-card-title">${esc(title)}</h6>
                    ${subtitleHtml}
                </div>
                <p class="complaint-note-card-body">${esc(normalizedBody)}</p>
            </article>
        `;
    }

    function renderComplaintMetaCard(label, value) {
        const normalizedValue = String(value || "").trim() || "-";
        return `
            <article class="complaint-note-meta-card">
                <p class="complaint-note-meta-label">${esc(label)}</p>
                <p class="complaint-note-meta-value">${esc(normalizedValue)}</p>
            </article>
        `;
    }

    function renderNarrationNotesSection(detail, blotterRequestNotice, showBlotterRequestDetails) {
        const notes = parseComplaintStageNotes(detail);
        const rows = [
            renderComplaintNoteCard(
                "Resident Narration",
                "Original complaint details from the resident or guest submitter",
                notes.residentNarration,
                "is-resident"
            ),
            renderComplaintNoteCard(
                "Initial Investigation",
                "First documented assessment and validation notes",
                notes.initialInvestigation,
                "is-investigation"
            ),
            renderComplaintNoteCard(
                "Progressed Action",
                "Actions already being carried out by the barangay team",
                notes.progressedAction,
                "is-progress"
            ),
            renderComplaintNoteCard(
                "Resolution",
                "Final findings, closure, or settlement notes",
                notes.resolution,
                "is-resolution"
            ),
        ].filter(Boolean);

        const metaCards = [];

        if (notes.caseSummary !== "") {
            rows.push(renderComplaintMetaCard("Case Summary", notes.caseSummary));
        }

        if (showBlotterRequestDetails) {
            metaCards.push(renderComplaintMetaCard("Blotter Request", [
                detail?.blotter_request_id || "-",
                detail?.blotter_request_status || "-",
                detail?.blotter_request_requested_at ? `Submitted: ${detail.blotter_request_requested_at}` : "",
                detail?.blotter_request_reviewed_at ? `Reviewed: ${detail.blotter_request_reviewed_at}` : "",
            ].filter(Boolean).join("\n")));
        }

        if (detail?.blotter_request_notes) {
            metaCards.push(renderComplaintMetaCard("Request Review Notes", detail.blotter_request_notes));
        } else if (blotterRequestNotice) {
            metaCards.push(renderComplaintMetaCard("Blotter Request Note", blotterRequestNotice));
        }

        if (Number(detail?.escalated_to_blotter || 0) === 1) {
            metaCards.push(renderComplaintMetaCard("Escalated to Blotter", `Yes${detail?.blotter_id ? ` (${detail.blotter_id})` : ""}`));
        }

        return `
            <div class="complaint-notes-layout complaint-note-stack">
                ${rows.join("")}
                ${metaCards.join("")}
            </div>
        `;
    }

    function buildBlotterRequestNotice(detail) {
        const status = String(detail?.blotter_request_status || "").trim().toLowerCase();
        const notes = String(detail?.blotter_request_notes || "").trim();

        if (status === "pending" || status === "approved") {
            return "This complaint has already been referred and is still under review.";
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

    async function postFormData(formData) {
        const res = await fetch(endpoint, {
            method: "POST",
            body: formData,
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
        if (!isComplaintClassificationReady(currentDetail)) {
            window.alert("Set the admin complaint classification first before updating the complaint status.");
            return;
        }
        const config = complaintActionConfig(actionType);
        pendingComplaintAction = actionType;
        if (complaintActionRemarks) {
            complaintActionRemarks.value = "";
            complaintActionRemarks.placeholder = config.placeholder || "Add status update notes or reason...";
        }
        if (complaintActionModalTitle) complaintActionModalTitle.textContent = config.title;

        transitionModal(viewModalEl, viewModal, complaintActionModal);
    }

    async function submitComplaintAction() {
        if (!pendingComplaintAction || !currentViewCaseId) return;
        const remarks = String(complaintActionRemarks?.value || "").trim();
        if (!remarks) {
            window.alert("Update notes are required.");
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
        investigateBtn?.addEventListener("click", () => openComplaintActionModal("under_investigation"));
        actionInProgressBtn?.addEventListener("click", () => openComplaintActionModal("action_in_progress"));
        resolveBtn?.addEventListener("click", () => openComplaintActionModal("resolved"));
        closeBtn?.addEventListener("click", () => openComplaintActionModal("dropped"));
        endorseBtn?.addEventListener("click", () => openComplaintActionModal("referred"));

        btnComplaintActionReturn?.addEventListener("click", () => {
            transitionModal(complaintActionModalEl, complaintActionModal, viewModal);
        });

        btnComplaintActionProceed?.addEventListener("click", () => {
            const remarks = String(complaintActionRemarks?.value || "").trim();
            if (!remarks) {
                window.alert("Update notes are required.");
                return;
            }

            const config = complaintActionConfig(pendingComplaintAction);
            if (complaintActionConfirmText) {
                complaintActionConfirmText.textContent = `Are you sure you want to ${config.actionText}?`;
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
                { label: "Status", value: d.status_name || "Received" },
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

            const witnessGrid = renderWitnessSection(d.witnesses || [], d.witness_summary || "");
            const attachmentGrid = renderAttachmentList(d.attachments || [], d.submitted_at || "");
            const attachmentEditor = renderAttachmentUploadEditor(d);

            const classificationSection = formSection("Administrative Classification", renderClassificationEditor(d));
            const intakeNotesSection = formSection("Intake Notes", renderIntakeNotesEditor(d.intake_notes || ""));
            const blotterRequestNotice = buildBlotterRequestNotice(d);
            const showBlotterRequestDetails = hasVisibleBlotterRequestDetails(d);

            const notesGrid = renderNarrationNotesSection(d, blotterRequestNotice, showBlotterRequestDetails);

            const html = [
                formSection("Complaint Summary", [
                    summaryGrid,
                    summaryMetaGrid,
                    summaryPlaceGrid,
                ].join("")),
                classificationSection,
                formSection("Complainant Information", complainantGrid),
                formSection("Witness Information", witnessGrid),
                formSection("Attachments", [attachmentGrid, attachmentEditor].filter(Boolean).join("")),
                intakeNotesSection,
                formSection("Narration and Notes", notesGrid),
            ].join("");

            viewDetailsBody.innerHTML = html || '<div class="text-muted">No details available.</div>';
            currentDetail = d;
            setActionButtonsState(d);

            const intakeNotesField = document.getElementById("complaintIntakeNotes");
            const saveIntakeNotesBtn = document.getElementById("btnSaveComplaintIntakeNotes");
            const complaintAdminClassification = document.getElementById("complaintAdminClassification");
            const saveComplaintClassificationBtn = document.getElementById("btnSaveComplaintClassification");
            const showWitnessFormBtn = document.getElementById("btnAddComplaintWitness");
            const witnessEditor = document.getElementById("complaintWitnessEditor");
            const witnessEntries = Array.from(document.querySelectorAll("[data-complaint-witness-row]"));
            const addAnotherWitnessBtn = document.getElementById("btnAddAnotherComplaintWitness");
            const cancelWitnessBtn = document.getElementById("btnCancelComplaintWitness");
            const saveWitnessBtn = document.getElementById("btnSaveComplaintWitness");
            const complaintAdminAttachments = document.getElementById("complaintAdminAttachments");
            const complaintAdminAttachmentSelection = document.getElementById("complaintAdminAttachmentSelection");
            const saveComplaintAttachmentsBtn = document.getElementById("btnSaveComplaintAttachments");

            saveComplaintClassificationBtn?.addEventListener("click", async () => {
                const complaintType = String(complaintAdminClassification?.value || "").trim();
                if (!complaintType) {
                    window.alert("Select the official complaint classification first.");
                    return;
                }

                try {
                    saveComplaintClassificationBtn.disabled = true;
                    await postJson({
                        action: "update_case_classification",
                        case_id: currentViewCaseId,
                        complaint_type: complaintType,
                    });
                    await loadList();
                    await openViewModal(currentViewCaseId);
                } catch (error) {
                    window.alert(error.message || error);
                } finally {
                    saveComplaintClassificationBtn.disabled = false;
                }
            });

            const visibleWitnessEntries = () => witnessEntries.filter((row) => !row.classList.contains("d-none"));

            const resetWitnessRow = (row, hideRow = true) => {
                if (!row) return;
                row.querySelectorAll("input, textarea").forEach((field) => {
                    field.value = "";
                    field.classList.remove("is-invalid");
                });
                if (hideRow) {
                    row.classList.add("d-none");
                }
            };

            const syncWitnessEditorState = () => {
                const visibleCount = visibleWitnessEntries().length;
                if (addAnotherWitnessBtn) {
                    const canAddMore = visibleCount < witnessEntries.length;
                    addAnotherWitnessBtn.disabled = !canAddMore;
                    addAnotherWitnessBtn.classList.toggle("d-none", !canAddMore);
                }
            };

            const ensureWitnessEditorHasVisibleRow = () => {
                const visibleCount = visibleWitnessEntries().length;
                if (visibleCount > 0) {
                    syncWitnessEditorState();
                    return;
                }
                const firstRow = witnessEntries[0] || null;
                if (!firstRow) return;
                firstRow.classList.remove("d-none");
                syncWitnessEditorState();
            };

            showWitnessFormBtn?.addEventListener("click", () => {
                if (!isComplaintClassificationReady(currentDetail)) {
                    window.alert("Set the admin complaint classification first before adding witness details.");
                    return;
                }
                witnessEditor?.classList.remove("d-none");
                showWitnessFormBtn.classList.add("d-none");
                ensureWitnessEditorHasVisibleRow();
                visibleWitnessEntries()[0]?.querySelector("[data-complaint-witness-last-name]")?.focus();
            });

            cancelWitnessBtn?.addEventListener("click", () => {
                witnessEntries.forEach((row) => resetWitnessRow(row, true));
                witnessEditor?.classList.add("d-none");
                showWitnessFormBtn?.classList.remove("d-none");
                syncWitnessEditorState();
            });

            witnessEntries.forEach((row) => {
                row.querySelectorAll("[data-complaint-witness-contact]").forEach((field) => {
                    field.addEventListener("input", () => {
                        field.value = normalizeComplaintWitnessContact(field.value);
                    });
                });
                row.querySelector("[data-complaint-witness-remove]")?.addEventListener("click", () => {
                    resetWitnessRow(row, true);
                    if (!visibleWitnessEntries().length) {
                        witnessEditor?.classList.add("d-none");
                        showWitnessFormBtn?.classList.remove("d-none");
                    }
                    syncWitnessEditorState();
                });
            });

            addAnotherWitnessBtn?.addEventListener("click", () => {
                if (!isComplaintClassificationReady(currentDetail)) {
                    window.alert("Set the admin complaint classification first before adding witness details.");
                    return;
                }
                const nextRow = witnessEntries.find((row) => row.classList.contains("d-none"));
                if (!nextRow) return;
                nextRow.classList.remove("d-none");
                syncWitnessEditorState();
                nextRow.querySelector("[data-complaint-witness-last-name]")?.focus();
            });

            complaintAdminAttachments?.addEventListener("change", () => {
                const files = Array.from(complaintAdminAttachments.files || []);
                if (!complaintAdminAttachmentSelection) return;
                if (!files.length) {
                    complaintAdminAttachmentSelection.textContent = "JPG, JPEG, PNG, or WEBP only. Max 5 MB each.";
                    return;
                }
                complaintAdminAttachmentSelection.textContent = files.map((file) => file.name).join(", ");
            });

            saveComplaintAttachmentsBtn?.addEventListener("click", async () => {
                if (!isComplaintClassificationReady(currentDetail)) {
                    window.alert("Set the admin complaint classification first before uploading attachments.");
                    return;
                }

                const files = Array.from(complaintAdminAttachments?.files || []);
                const currentAttachments = (Array.isArray(currentDetail?.attachments) ? currentDetail.attachments : []).filter((attachment) => attachment && String(attachment.path ?? "").trim() !== "");
                const remainingSlots = Math.max(0, MAX_COMPLAINT_ATTACHMENTS_TOTAL - currentAttachments.length);

                if (!files.length) {
                    window.alert("Select at least one image attachment before uploading.");
                    return;
                }
                if (files.length > MAX_COMPLAINT_ATTACHMENTS_PER_UPLOAD) {
                    window.alert(`You can only upload up to ${MAX_COMPLAINT_ATTACHMENTS_PER_UPLOAD} images at a time.`);
                    return;
                }
                if (files.length > remainingSlots) {
                    window.alert(`This complaint only has ${remainingSlots} attachment slot${remainingSlots === 1 ? "" : "s"} remaining.`);
                    return;
                }

                try {
                    saveComplaintAttachmentsBtn.disabled = true;
                    if (complaintAdminAttachments) complaintAdminAttachments.disabled = true;

                    const formData = new FormData();
                    formData.append("action", "add_attachments");
                    formData.append("case_id", currentViewCaseId);
                    files.forEach((file) => formData.append("complaint_images[]", file));

                    await postFormData(formData);
                    await loadList();
                    await openViewModal(currentViewCaseId);
                } catch (error) {
                    window.alert(error.message || error);
                } finally {
                    if (saveComplaintAttachmentsBtn) saveComplaintAttachmentsBtn.disabled = false;
                    if (complaintAdminAttachments) complaintAdminAttachments.disabled = false;
                }
            });

            saveWitnessBtn?.addEventListener("click", async () => {
                if (!isComplaintClassificationReady(currentDetail)) {
                    window.alert("Set the admin complaint classification first before saving witness details.");
                    return;
                }
                const witnessesToSave = [];
                let firstInvalidField = null;

                visibleWitnessEntries().forEach((row) => {
                    const lastNameField = row.querySelector("[data-complaint-witness-last-name]");
                    const firstNameField = row.querySelector("[data-complaint-witness-first-name]");
                    const middleNameField = row.querySelector("[data-complaint-witness-middle-name]");
                    const suffixField = row.querySelector("[data-complaint-witness-suffix]");
                    const contactField = row.querySelector("[data-complaint-witness-contact]");
                    const addressField = row.querySelector("[data-complaint-witness-address]");
                    const remarksField = row.querySelector("[data-complaint-witness-remarks]");
                    const lastName = String(lastNameField?.value || "").trim();
                    const firstName = String(firstNameField?.value || "").trim();
                    const middleName = String(middleNameField?.value || "").trim();
                    const suffix = String(suffixField?.value || "").trim();
                    const contactNumber = normalizeComplaintWitnessContact(contactField?.value || "");
                    const address = String(addressField?.value || "").trim();
                    const remarks = String(remarksField?.value || "").trim();

                    if (contactField) {
                        contactField.value = contactNumber;
                    }

                    lastNameField?.classList.remove("is-invalid");
                    firstNameField?.classList.remove("is-invalid");
                    contactField?.classList.remove("is-invalid");

                    let rowValid = true;
                    if (!lastName) {
                        lastNameField?.classList.add("is-invalid");
                        firstInvalidField = firstInvalidField || lastNameField;
                        rowValid = false;
                    }
                    if (!firstName) {
                        firstNameField?.classList.add("is-invalid");
                        firstInvalidField = firstInvalidField || firstNameField;
                        rowValid = false;
                    }
                    if (!isValidComplaintWitnessContact(contactNumber)) {
                        contactField?.classList.add("is-invalid");
                        firstInvalidField = firstInvalidField || contactField;
                        rowValid = false;
                    }
                    if (!rowValid) {
                        return;
                    }

                    witnessesToSave.push({
                        lastname: lastName,
                        firstname: firstName,
                        middlename: middleName,
                        suffix,
                        contact_number: contactNumber,
                        address,
                        remarks,
                    });
                });

                if (firstInvalidField) {
                    firstInvalidField.focus();
                    window.alert("Each witness needs a last name, first name, and a valid contact number in the format 09XXXXXXXXX.");
                    return;
                }
                if (!witnessesToSave.length) {
                    window.alert("Add at least one witness before saving.");
                    return;
                }

                try {
                    saveWitnessBtn.disabled = true;
                    addAnotherWitnessBtn && (addAnotherWitnessBtn.disabled = true);
                    await postJson({
                        action: "add_witnesses",
                        case_id: currentViewCaseId,
                        witnesses: witnessesToSave,
                    });
                    await loadList();
                    await openViewModal(currentViewCaseId);
                } catch (error) {
                    window.alert(error.message || error);
                } finally {
                    saveWitnessBtn.disabled = false;
                    addAnotherWitnessBtn && (addAnotherWitnessBtn.disabled = false);
                }
            });

            saveIntakeNotesBtn?.addEventListener("click", async () => {
                if (!isComplaintClassificationReady(currentDetail)) {
                    window.alert("Set the admin complaint classification first before saving intake notes.");
                    return;
                }
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
                btn.classList.remove("active");
            });
            button.classList.add("active");
            activeFilter = String(button.dataset.filter || "").trim().toLowerCase();
            activeSubStatus = "";
            currentPage = 1;
            renderComplaintSubtabs();
            loadList();
        });
    });

    initComplaintActionFlow();
    renderComplaintSubtabs();
    loadList();
})();
