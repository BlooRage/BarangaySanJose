(() => {
  const appBase = (() => {
    const marker = "/Admin-End/";
    const idx = window.location.pathname.indexOf(marker);
    if (idx === -1) return "";
    return window.location.pathname.slice(0, idx);
  })();

  const endpoint = `${appBase}/PhpFiles/Admin-End/businessMonitoringData.php`;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
  const tableBody = document.getElementById("businessMonitoringTbody");
  const searchInput = document.getElementById("searchInput");
  const btnApplyFilter = document.getElementById("btnBusinessFilterApply");
  const btnResetFilter = document.getElementById("btnBusinessFilterReset");
  const btnRefresh = document.getElementById("btnBusinessRefresh");
  const entriesPerPageInput = document.getElementById("businessEntriesPerPageInput");
  const paginationEl = document.getElementById("businessPagination");
  const filterModalEl = document.getElementById("modalFilter");
  const filterDateFrom = document.getElementById("businessFilterDateFrom");
  const filterDateTo = document.getElementById("businessFilterDateTo");
  const filterTypeList = document.getElementById("businessFilterTypeList");
  const filterAreaList = document.getElementById("businessFilterAreaList");
  const filterSectorList = document.getElementById("businessFilterSectorList");
  const viewModalEl = document.getElementById("businessViewModal");
  const viewModalTitle = document.getElementById("businessViewModalTitle");
  const viewModalBody = document.getElementById("businessViewModalBody");
  const documentModalEl = document.getElementById("businessDocumentModal");
  const documentModalTitle = document.getElementById("businessDocumentModalTitle");
  const documentModalBody = document.getElementById("businessDocumentModalBody");
  const viewModal = viewModalEl && window.bootstrap?.Modal
    ? new bootstrap.Modal(viewModalEl)
    : null;
  const documentModal = documentModalEl && window.bootstrap?.Modal
    ? new bootstrap.Modal(documentModalEl)
    : null;

  const state = {
    rowsRaw: [],
    rows: [],
    search: "",
    filters: {},
    dateRange: {
      from: "",
      to: "",
    },
    documentViewer: {
      returnToView: false,
    },
    pagination: {
      currentPage: 1,
      entriesPerPage: Math.max(1, Number.parseInt(entriesPerPageInput?.value || "20", 10) || 20),
    },
    auto: {
      interval: null,
      inFlight: false,
    },
  };

  const AUTO_REFRESH_MS = 30000;
  const COLUMN_COUNT = 5;
  const OFFICIAL_AREA_OPTIONS = ["Area 01", "Area 1A", "Area 02", "Area 03", "Area 04", "Area 05", "Area 06"];
  const OFFICIAL_SECTOR_OPTIONS = ["PWD", "Senior Citizen", "Student", "Indigenous People", "Single Parent"];

  function esc(value) {
    return String(value ?? "").replace(/[&<>\"']/g, (match) => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      "\"": "&quot;",
      "'": "&#39;",
    }[match]));
  }

  function text(value, fallback = "—") {
    const resolved = String(value ?? "").trim();
    return resolved !== "" ? resolved : fallback;
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
    const active = new Set(Array.isArray(state.filters?.[field]) ? state.filters[field] : []);
    container.innerHTML = list.map((value, index) => `
      <label class="d-flex align-items-center gap-2">
        <input class="form-check-input m-0 business-filter-checkbox" type="checkbox" value="${esc(value)}" data-field="${esc(field)}" id="${esc(`businessFilter_${field}_${index}`)}" ${active.has(value) ? "checked" : ""}>
        <span>${esc(value)}</span>
      </label>
    `).join("");
  }

  function syncFilterOptions(rows) {
    const list = Array.isArray(rows) ? rows : [];
    const requestTypes = Array.from(new Set(
      list.map((row) => String(row?.application_type || "").trim()).filter(Boolean)
    )).sort((a, b) => a.localeCompare(b));
    const areaNumbers = OFFICIAL_AREA_OPTIONS.slice();
    const sectors = OFFICIAL_SECTOR_OPTIONS.slice();

    if (Array.isArray(state.filters?.area_number)) {
      state.filters.area_number = state.filters.area_number.filter((value) => areaNumbers.includes(String(value || "").trim()));
    }
    if (Array.isArray(state.filters?.sector_membership)) {
      state.filters.sector_membership = state.filters.sector_membership
        .map((value) => normalizeSectorLabel(value))
        .filter((value) => sectors.includes(value));
    }

    renderFilterChecklist(filterTypeList, "application_type", requestTypes);
    renderFilterChecklist(filterAreaList, "area_number", areaNumbers);
    renderFilterChecklist(filterSectorList, "sector_membership", sectors);
  }

  function statusBadge(row) {
    const bucket = String(row?.status_bucket || "pending").trim().toLowerCase() || "pending";
    const label = text(row?.stage_label, "Pending Verification");
    return `<span class="business-status-badge ${esc(bucket)}">${esc(label)}</span>`;
  }

  function establishmentStatusBadge(row) {
    const status = ["operational", "closed", "archived"].includes(String(row?.establishment_status || "").toLowerCase())
      ? String(row.establishment_status).toLowerCase()
      : "operational";
    const label = status.charAt(0).toUpperCase() + status.slice(1);
    return `<span class="business-status-badge ${esc(status)}">${esc(label)}</span>`;
  }

  function detailField(label, value, options = {}) {
    const { raw = false, fullWidth = false } = options;
    return `
      <div class="business-view-field${fullWidth ? " full-width" : ""}">
        <p class="business-view-label">${esc(label)}</p>
        <div class="business-view-value">${raw ? String(value || "—") : esc(text(value))}</div>
      </div>
    `;
  }

  function renderSubmittedDocumentsSection(row) {
    const docs = Array.isArray(row?.submitted_documents) ? row.submitted_documents : [];
    if (!docs.length) {
      return `
        <div class="submitted-docs-section">
          <p class="business-view-label">Submitted Documents</p>
          <div class="business-view-value mt-2">No submitted business clearance documents found.</div>
        </div>
      `;
    }

    const listHtml = docs.map((doc, index) => `
      <div class="submitted-docs-item">
        <div class="submitted-docs-item__meta">
          <div class="submitted-docs-item__label">${esc(doc?.label || `Document ${index + 1}`)}</div>
          <div class="submitted-docs-item__name">${esc(doc?.name || doc?.label || `Document ${index + 1}`)}</div>
        </div>
        <button
          type="button"
          class="btn btn-sm btn-primary"
          data-business-doc-url="${esc(doc?.url || "")}"
          data-business-doc-name="${esc(doc?.name || doc?.label || `Document ${index + 1}`)}"
          data-business-doc-label="${esc(doc?.label || `Document ${index + 1}`)}"
          data-business-name="${esc(row?.business_name || "")}"
        >
          View
        </button>
      </div>
    `).join("");

    return `
      <div class="submitted-docs-section">
        <p class="business-view-label">Submitted Documents</p>
        <div class="submitted-docs-list mt-2">${listHtml}</div>
      </div>
    `;
  }

  function renderDocumentModal(url, title = "") {
    const cleanUrl = String(url || "").trim();
    const cleanTitle = text(title, "Submitted Document");
    if (documentModalTitle) documentModalTitle.textContent = cleanTitle;

    if (!cleanUrl) {
      if (documentModalBody) {
        documentModalBody.innerHTML = `
          <div class="business-document-viewer">
            <div class="business-document-viewer__placeholder">Preview unavailable.</div>
          </div>
        `;
      }
      return;
    }

    const isImage = /\.(png|jpe?g|webp|gif)(?:[?#].*)?$/i.test(cleanUrl);
    if (documentModalBody) {
      documentModalBody.innerHTML = `
        <div class="business-document-viewer">
          ${isImage
            ? `<img src="${esc(cleanUrl)}" alt="${esc(cleanTitle)}">`
            : `<iframe src="${esc(cleanUrl)}" title="${esc(cleanTitle)}" loading="lazy"></iframe>`}
        </div>
      `;
    }
  }

  function buildDocumentTitle(submitType, businessName, fallbackName = "") {
    const resolvedType = String(submitType || "").trim();
    const resolvedBusinessName = String(businessName || "").trim();
    if (resolvedType && resolvedBusinessName) {
      return `${resolvedType} - ${resolvedBusinessName}`;
    }
    if (resolvedType) {
      return resolvedType;
    }
    if (resolvedBusinessName) {
      return resolvedBusinessName;
    }
    return text(fallbackName, "Submitted Document");
  }

  function showDocumentModal(url, title = "") {
    renderDocumentModal(url, title);

    const showViewer = () => {
      if (documentModal) {
        documentModal.show();
        return;
      }

      const cleanUrl = String(url || "").trim();
      if (cleanUrl) {
        window.open(cleanUrl, "_blank", "noopener");
      }
    };

    if (viewModalEl?.classList.contains("show") && viewModal && documentModal) {
      state.documentViewer.returnToView = true;
      const handleViewModalHidden = () => {
        viewModalEl.removeEventListener("hidden.bs.modal", handleViewModalHidden);
        showViewer();
      };
      viewModalEl.addEventListener("hidden.bs.modal", handleViewModalHidden);
      viewModal.hide();
      return;
    }

    state.documentViewer.returnToView = false;
    showViewer();
  }

  function bindSubmittedDocumentButtons() {
    const docButtons = Array.from(document.querySelectorAll("button[data-business-doc-url]"));
    if (!docButtons.length) return;

    docButtons.forEach((button) => {
      button.addEventListener("click", () => {
        const fileName = String(button.getAttribute("data-business-doc-name") || "");
        const submitType = String(button.getAttribute("data-business-doc-label") || "");
        const businessName = String(button.getAttribute("data-business-name") || "");
        showDocumentModal(
          String(button.getAttribute("data-business-doc-url") || ""),
          buildDocumentTitle(submitType, businessName, fileName)
        );
      });
    });
  }

  function emptyState(message = "No business permit clearance requests found.") {
    return `
      <tr>
        <td colspan="${COLUMN_COUNT}" class="text-center text-muted py-4">${esc(message)}</td>
      </tr>
    `;
  }

  function buildRow(row) {
    const requestId = esc(text(row.request_id, ""));
    return `
      <tr>
        <td>${esc(text(row.plate_number))}</td>
        <td>${esc(text(row.business_name))}</td>
        <td>${esc(text(row.business_type))}</td>
        <td>${esc(text(row.business_address))}</td>
        <td>
          <div class="d-flex align-items-center justify-content-between gap-2">
            ${establishmentStatusBadge(row)}
            <div class="dropdown">
              <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Actions</button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><button class="dropdown-item" type="button" data-view-id="${requestId}"><i class="fa-regular fa-eye me-2"></i>View</button></li>
                <li><hr class="dropdown-divider"></li>
                <li><button class="dropdown-item" type="button" data-establishment-status="operational" data-request-id="${requestId}"><i class="fa-solid fa-store me-2 text-success"></i>Operational</button></li>
                <li><button class="dropdown-item" type="button" data-establishment-status="closed" data-request-id="${requestId}"><i class="fa-solid fa-store-slash me-2 text-danger"></i>Closed</button></li>
                <li><button class="dropdown-item" type="button" data-establishment-status="archived" data-request-id="${requestId}"><i class="fa-solid fa-box-archive me-2 text-secondary"></i>Archived</button></li>
              </ul>
            </div>
          </div>
        </td>
      </tr>
    `;
  }

  function renderPagination(totalPages, totalRows) {
    if (!paginationEl) return;
    paginationEl.innerHTML = "";

    const addBtn = (label, page, disabled = false, active = false) => {
      const li = document.createElement("li");
      li.className = `page-item${disabled ? " disabled" : ""}${active ? " active" : ""}`;
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = `page-link${active ? " fw-bold" : ""}`;
      btn.textContent = label;
      btn.disabled = disabled;
      btn.addEventListener("click", () => {
        if (disabled || page === state.pagination.currentPage) return;
        state.pagination.currentPage = page;
        renderTable();
      });
      li.appendChild(btn);
      paginationEl.appendChild(li);
    };

    if (totalRows <= 0) {
      addBtn("<", 1, true, false);
      addBtn("1", 1, false, true);
      addBtn(">", 1, true, false);
      return;
    }

    addBtn("<", Math.max(1, state.pagination.currentPage - 1), state.pagination.currentPage <= 1, false);
    let startPage = Math.max(1, state.pagination.currentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);

    for (let page = startPage; page <= endPage; page += 1) {
      addBtn(String(page), page, false, page === state.pagination.currentPage);
    }

    addBtn(">", Math.min(totalPages, state.pagination.currentPage + 1), state.pagination.currentPage >= totalPages, false);
  }

  function renderTable() {
    if (!tableBody) return;

    const rows = Array.isArray(state.rows) ? state.rows : [];
    const totalPages = Math.max(1, Math.ceil(rows.length / state.pagination.entriesPerPage));
    if (state.pagination.currentPage > totalPages) state.pagination.currentPage = totalPages;
    if (state.pagination.currentPage < 1) state.pagination.currentPage = 1;

    const start = (state.pagination.currentPage - 1) * state.pagination.entriesPerPage;
    const pageRows = rows.slice(start, start + state.pagination.entriesPerPage);
    renderPagination(totalPages, rows.length);

    if (!pageRows.length) {
      tableBody.innerHTML = emptyState();
      return;
    }

    tableBody.innerHTML = pageRows.map(buildRow).join("");
    bindViewButtons();
    bindStatusButtons();
  }

  function renderViewModal(row) {
    if (!viewModalBody) return;

    viewModalTitle.textContent = `Business Request ${text(row?.request_id)}`;
    viewModalBody.innerHTML = `
      <div class="business-view-grid">
        ${detailField("Plate Number", row?.plate_number)}
        ${detailField("Business Name", row?.business_name)}
        ${detailField("Business Type", row?.business_type)}
        ${detailField("Application Type", row?.application_type)}
        ${detailField("Applicant", row?.applicant_name)}
        ${detailField("Owner Type", row?.owner_type)}
        ${detailField("Owner Name", row?.owner_name)}
        ${detailField("Status", statusBadge(row), { raw: true })}
        ${detailField("Establishment Status", establishmentStatusBadge(row), { raw: true })}
        ${detailField("Submitted At", row?.submitted_at_display || row?.submitted_at)}
        ${detailField("Business Address", row?.business_address, { fullWidth: true })}
      </div>
      ${renderSubmittedDocumentsSection(row)}
    `;
    bindSubmittedDocumentButtons();
  }

  function openViewModal(requestId) {
    const row = state.rowsRaw.find((item) => String(item?.request_id || "").trim() === String(requestId || "").trim());
    if (!row) return;
    renderViewModal(row);
    viewModal?.show();
  }

  function bindViewButtons() {
    if (!tableBody) return;
    tableBody.querySelectorAll("button[data-view-id]").forEach((button) => {
      button.addEventListener("click", () => {
        openViewModal(String(button.getAttribute("data-view-id") || ""));
      });
    });
  }

  async function updateEstablishmentStatus(requestId, status, button) {
    const label = status.charAt(0).toUpperCase() + status.slice(1);
    if ((status === "closed" || status === "archived") && !window.confirm(`Mark this establishment as ${label}?`)) return;

    const body = new FormData();
    body.append("action", "set_establishment_status");
    body.append("request_id", requestId);
    body.append("status", status);
    body.append("csrf_token", csrfToken);
    if (button) button.disabled = true;
    try {
      const response = await fetch(endpoint, { method: "POST", body, headers: { Accept: "application/json" } });
      const data = await response.json().catch(() => ({}));
      if (!response.ok || data.success === false) throw new Error(data.message || "Unable to update establishment status.");
      const row = state.rowsRaw.find((item) => String(item?.request_id || "") === requestId);
      if (row) row.establishment_status = status;
      renderTableAfterFilter();
    } catch (error) {
      window.alert(error?.message || "Unable to update establishment status.");
    } finally {
      if (button) button.disabled = false;
    }
  }

  function bindStatusButtons() {
    if (!tableBody) return;
    tableBody.querySelectorAll("button[data-establishment-status]").forEach((button) => {
      button.addEventListener("click", () => {
        updateEstablishmentStatus(
          String(button.getAttribute("data-request-id") || ""),
          String(button.getAttribute("data-establishment-status") || ""),
          button
        );
      });
    });
  }

  function matchesSearch(row) {
    if (!state.search) return true;

    const haystack = [
      row.plate_number,
      row.request_id,
      row.document_type,
      row.purpose,
      row.business_name,
      row.business_type,
      row.application_type,
      row.applicant_name,
      row.owner_type,
      row.owner_name,
      row.business_address,
      row.stage,
      row.stage_label,
      row.resident_id,
      row.resident_user_id,
    ].map((value) => String(value || "").toLowerCase());

    return haystack.some((value) => value.includes(state.search));
  }

  function matchesCheckboxFilters(row) {
    const filters = state.filters || {};
    return Object.entries(filters).every(([field, allowed]) => {
      if (!Array.isArray(allowed) || !allowed.length) return true;
      if (field === "sector_membership") {
        const memberships = parseSectorValues(row?.sector_membership);
        return allowed
          .map((value) => normalizeSectorLabel(value))
          .some((value) => memberships.includes(value));
      }
      return allowed.includes(String(row?.[field] ?? "").trim());
    });
  }

  function matchesDateRange(row) {
    const from = String(state.dateRange?.from || "").trim();
    const to = String(state.dateRange?.to || "").trim();
    if (!from && !to) return true;
    const rowDate = normalizeDateValue(row?.submitted_at);
    if (!rowDate) return false;
    if (from && rowDate < from) return false;
    if (to && rowDate > to) return false;
    return true;
  }

  function collectFiltersFromModal() {
    const next = {};
    document.querySelectorAll(".business-filter-checkbox:checked").forEach((checkbox) => {
      const field = String(checkbox.getAttribute("data-field") || "").trim();
      if (!field) return;
      if (!Array.isArray(next[field])) next[field] = [];
      next[field].push(String(checkbox.value || ""));
    });
    return next;
  }

  function setRefreshLoading(on) {
    if (!btnRefresh) return;
    btnRefresh.classList.toggle("is-loading", !!on);
    btnRefresh.disabled = !!on;
  }

  function scheduleAutoRefresh() {
    if (state.auto.interval) clearTimeout(state.auto.interval);
    state.auto.interval = setTimeout(() => {
      if (state.auto.inFlight) {
        scheduleAutoRefresh();
        return;
      }
      triggerRefresh();
    }, AUTO_REFRESH_MS);
  }

  async function fetchJson(url) {
    const response = await fetch(url, {
      headers: {
        Accept: "application/json",
      },
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.success === false) {
      throw new Error(data.message || "Failed to load business requests.");
    }
    return data;
  }

  async function load() {
    if (state.auto.inFlight) return;

    state.auto.inFlight = true;
    setRefreshLoading(true);
    if (tableBody) {
      tableBody.innerHTML = emptyState("Loading business requests...");
    }

    try {
      const data = await fetchJson(`${endpoint}?_ts=${Date.now()}`);
      state.rowsRaw = Array.isArray(data.items) ? data.items : [];
      syncFilterOptions(state.rowsRaw);
      renderTableAfterFilter();
    } catch (error) {
      if (tableBody) {
        tableBody.innerHTML = emptyState(error?.message || "Failed to load business requests.");
      }
      renderPagination(1, 0);
    } finally {
      state.auto.inFlight = false;
      setRefreshLoading(false);
    }
  }

  function triggerRefresh() {
    scheduleAutoRefresh();
    load().catch(() => {});
  }

  searchInput?.addEventListener("input", () => {
    state.search = String(searchInput.value || "").trim().toLowerCase();
    state.pagination.currentPage = 1;
    renderTableAfterFilter();
  });

  function renderTableAfterFilter() {
    state.rows = state.rowsRaw.filter((row) => (
      matchesSearch(row)
      && matchesCheckboxFilters(row)
      && matchesDateRange(row)
    ));
    state.pagination.currentPage = 1;
    renderTable();
  }

  btnApplyFilter?.addEventListener("click", () => {
    state.filters = collectFiltersFromModal();
    state.dateRange = {
      from: String(filterDateFrom?.value || "").trim(),
      to: String(filterDateTo?.value || "").trim(),
    };
    renderTableAfterFilter();

    if (filterModalEl && window.bootstrap?.Modal) {
      const modalInstance = bootstrap.Modal.getInstance(filterModalEl);
      modalInstance?.hide();
    }
  });

  btnResetFilter?.addEventListener("click", () => {
    document.querySelectorAll(".business-filter-checkbox").forEach((checkbox) => {
      checkbox.checked = false;
    });
    state.filters = {};
    state.dateRange = { from: "", to: "" };
    if (filterDateFrom) filterDateFrom.value = "";
    if (filterDateTo) filterDateTo.value = "";
    renderTableAfterFilter();
  });

  btnRefresh?.addEventListener("click", triggerRefresh);

  entriesPerPageInput?.addEventListener("change", () => {
    const next = Math.max(1, Number.parseInt(entriesPerPageInput.value || "20", 10) || 20);
    state.pagination.entriesPerPage = next;
    entriesPerPageInput.value = String(next);
    state.pagination.currentPage = 1;
    renderTable();
  });

  documentModalEl?.addEventListener("hidden.bs.modal", () => {
    if (!state.documentViewer.returnToView) return;
    state.documentViewer.returnToView = false;
    viewModal?.show();
  });

  scheduleAutoRefresh();
  load().catch(() => {});
})();
