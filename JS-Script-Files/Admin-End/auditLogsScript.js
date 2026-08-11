(() => {
  const scriptEl = document.currentScript;
  const endpoint = scriptEl?.dataset.endpoint || "../PhpFiles/Admin-End/auditLogs.php";
  const csrfToken = scriptEl?.dataset.csrfToken || "";
  const el = (id) => document.getElementById(id);

  const state = {
    q: "",
    timer: null,
    statusTimer: null,
    rows: [],
    meta: {},
    visibleCols: null,
    filters: {
      from: "",
      to: "",
      personUserId: "",
      includeDetails: false,
    },
    auto: {
      interval: null,
      inFlight: false,
    },
    loadSequence: 0,
    loadController: null,
    pagination: {
      currentPage: 1,
      entriesPerPage: 20,
    },
  };

  const entriesPerPageInput = el("auditEntriesPerPageInput");
  const paginationEl = el("auditPagination");
  if (entriesPerPageInput) {
    state.pagination.entriesPerPage = Math.max(1, Number.parseInt(entriesPerPageInput.value || "20", 10) || 20);
  }

  const safeText = (v) => {
    const s = String(v ?? "").trim();
    return s !== "" ? s : "—";
  };

  const truncate = (s, n = 60) => {
    const t = String(s ?? "");
    if (t.length <= n) return t;
    return `${t.slice(0, n - 1)}…`;
  };

  const STORAGE_KEY = "audit_cols_v1";

  const columns = [
    { key: "timestamp", label: "Timestamp", default: true, get: (r) => safeText(r.action_timestamp), nowrap: true },
    { key: "user_id", label: "User ID", default: true, get: (r) => safeText(r.user_id) },
    { key: "name", label: "Name", default: true, get: (r) => safeText(r.display_name) },
    { key: "role_access", label: "Role Access", default: true, get: (r) => safeText(r.role_access) },
    { key: "action_type", label: "Action", default: true, get: (r) => safeText(r.action_type) },
    { key: "module_affected", label: "Module", default: false, get: (r) => safeText(r.module_affected) },
    { key: "target", label: "Target", default: false, get: (r) => `${safeText(r.target_type)} #${safeText(r.target_id)}` },
    { key: "field_changed", label: "Field", default: false, get: (r) => safeText(r.field_changed) },
    { key: "old_value", label: "Old", default: false, get: (r) => safeText(r.old_value), truncate: 60 },
    { key: "new_value", label: "New", default: false, get: (r) => safeText(r.new_value), truncate: 60 },
    { key: "remarks", label: "Remarks", default: false, get: (r) => safeText(r.remarks), truncate: 60 },
  ];

  const defaultVisibleCols = () => columns.filter((c) => c.default).map((c) => c.key);

  const loadVisibleCols = () => {
    try {
      const raw = window.localStorage.getItem(STORAGE_KEY);
      if (!raw) return defaultVisibleCols();
      const parsed = JSON.parse(raw);
      if (!Array.isArray(parsed)) return defaultVisibleCols();
      const allowed = new Set(columns.map((c) => c.key));
      const filtered = parsed.map((x) => String(x)).filter((k) => allowed.has(k));
      return filtered.length ? filtered : defaultVisibleCols();
    } catch {
      return defaultVisibleCols();
    }
  };

  const saveVisibleCols = (keys) => {
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(keys));
    } catch {
      // Local storage can be unavailable in private browsing modes.
    }
  };

  const getActiveColumns = () => {
    const set = new Set(state.visibleCols || defaultVisibleCols());
    return columns.filter((c) => set.has(c.key));
  };

  const renderHeader = () => {
    const theadRow = el("auditTheadRow");
    if (!theadRow) return;
    const activeCols = getActiveColumns();
    theadRow.innerHTML = "";
    activeCols.forEach((c) => {
      const th = document.createElement("th");
      th.textContent = c.label;
      if (c.nowrap) th.style.whiteSpace = "nowrap";
      theadRow.appendChild(th);
    });
  };

  const renderPagination = (totalPages, totalRows) => {
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
      addBtn("<", 1, true);
      addBtn("1", 1, false, true);
      addBtn(">", 1, true);
      return;
    }

    addBtn("<", Math.max(1, state.pagination.currentPage - 1), state.pagination.currentPage <= 1);
    let startPage = Math.max(1, state.pagination.currentPage - 2);
    const endPage = Math.min(totalPages, startPage + 4);
    if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);
    for (let page = startPage; page <= endPage; page += 1) {
      addBtn(String(page), page, false, page === state.pagination.currentPage);
    }
    addBtn(">", Math.min(totalPages, state.pagination.currentPage + 1), state.pagination.currentPage >= totalPages);
  };

  const renderTable = () => {
    const tbody = el("auditTbody");
    const activeCols = getActiveColumns();
    renderHeader();
    if (!tbody) return;

    const rows = state.rows || [];
    const totalPages = Math.max(1, Math.ceil(rows.length / state.pagination.entriesPerPage));
    state.pagination.currentPage = Math.min(Math.max(1, state.pagination.currentPage), totalPages);
    const start = (state.pagination.currentPage - 1) * state.pagination.entriesPerPage;
    const pageRows = rows.slice(start, start + state.pagination.entriesPerPage);
    renderPagination(totalPages, rows.length);

    if (!pageRows.length) {
      tbody.innerHTML = `<tr><td colspan="${activeCols.length || 1}" class="text-center text-muted py-4">No records found.</td></tr>`;
      return;
    }

    tbody.innerHTML = "";
    pageRows.forEach((row) => {
      const tr = document.createElement("tr");
      activeCols.forEach((column) => {
        const td = document.createElement("td");
        const raw = column.get(row);
        td.textContent = column.truncate ? truncate(raw, column.truncate) : String(raw ?? "");
        if (column.truncate) td.title = String(raw ?? "");
        if (column.nowrap) td.style.whiteSpace = "nowrap";
        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });
  };

  const renderTableMessage = (message, className = "text-muted") => {
    const tbody = el("auditTbody");
    if (!tbody) return;
    renderHeader();
    const tr = document.createElement("tr");
    const td = document.createElement("td");
    td.colSpan = getActiveColumns().length || 1;
    td.className = `text-center ${className} py-4`;
    td.textContent = message;
    tr.appendChild(td);
    tbody.replaceChildren(tr);
  };

  const selectedPersonLabel = () => {
    const select = el("auditFilterPerson");
    if (!select || !state.filters.personUserId) return "";
    const option = Array.from(select.options).find((candidate) => candidate.value === state.filters.personUserId);
    return option?.textContent?.trim() || state.filters.personUserId;
  };

  const formatDate = (value) => {
    if (!value) return "";
    const parsed = new Date(`${value}T00:00:00`);
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" });
  };

  const renderActiveFilters = () => {
    const target = el("auditActiveFilters");
    if (!target) return;

    const filters = [];
    if (state.filters.from && state.filters.to) {
      filters.push(`${formatDate(state.filters.from)} to ${formatDate(state.filters.to)}`);
    } else if (state.filters.from) {
      filters.push(`From ${formatDate(state.filters.from)}`);
    } else if (state.filters.to) {
      filters.push(`Through ${formatDate(state.filters.to)}`);
    }
    if (state.filters.personUserId) filters.push(`Performed by: ${selectedPersonLabel()}`);
    if (state.q.trim()) filters.push(`Search: “${truncate(state.q.trim(), 42)}”`);

    const count = state.rows.length;
    let resultText = `${count} record${count === 1 ? "" : "s"}`;
    if (state.meta?.scan_truncated) {
      resultText = `Showing ${resultText}; scan limit reached, narrow the filters for a complete result`;
    } else if (state.meta?.truncated) {
      resultText = `Showing latest ${count}+ records`;
    }
    target.textContent = `${filters.length ? filters.join(" • ") : "All audit logs"} — ${resultText}`;
  };

  const renderColumnsModal = () => {
    const list = el("auditColumnsList");
    if (!list) return;
    const selected = new Set(state.visibleCols || defaultVisibleCols());
    list.innerHTML = "";

    columns.forEach((column) => {
      const col = document.createElement("div");
      col.className = "col-12 col-md-6 col-lg-4";

      const wrap = document.createElement("label");
      wrap.className = "audit-columns-check d-flex align-items-center gap-2 w-100";

      const cb = document.createElement("input");
      cb.type = "checkbox";
      cb.className = "form-check-input m-0";
      cb.dataset.colKey = column.key;
      cb.checked = selected.has(column.key);

      const text = document.createElement("div");
      text.className = "fw-semibold";
      text.textContent = column.label;

      wrap.append(cb, text);
      col.appendChild(wrap);
      list.appendChild(col);
    });
  };

  const buildFilterParams = () => {
    const params = new URLSearchParams();
    if (state.q.trim()) params.set("q", state.q.trim());
    if (state.filters.from) params.set("date_from", state.filters.from);
    if (state.filters.to) params.set("date_to", state.filters.to);
    if (state.filters.personUserId) params.set("person_user_id", state.filters.personUserId);
    return params;
  };

  const readJsonResponse = async (response) => {
    const rawText = await response.text();
    try {
      return { data: rawText ? JSON.parse(rawText) : null, rawText };
    } catch {
      return { data: null, rawText };
    }
  };

  const responseErrorMessage = (data, rawText, fallback) => (
    (data && (data.message || data.error))
    || (rawText?.trim() ? rawText.trim().slice(0, 300) : "")
    || fallback
  );

  const load = async () => {
    const sequence = ++state.loadSequence;
    if (state.loadController) state.loadController.abort();
    const controller = new AbortController();
    state.loadController = controller;
    state.auto.inFlight = true;

    const refreshBtn = el("btnAuditRefresh");
    refreshBtn?.classList.add("is-loading");
    if (refreshBtn) refreshBtn.disabled = true;
    renderTableMessage("Loading...");

    const params = buildFilterParams();
    params.set("fetch_audit_logs", "1");
    params.set("limit", "500");

    try {
      const response = await fetch(`${endpoint}?${params.toString()}`, {
        headers: { Accept: "application/json" },
        signal: controller.signal,
      });
      const { data, rawText } = await readJsonResponse(response);

      if (!response.ok || !data?.success) {
        throw new Error(responseErrorMessage(data, rawText, "Failed to load audit logs."));
      }
      if (sequence !== state.loadSequence) return;

      state.rows = Array.isArray(data.data) ? data.data : [];
      state.meta = data.meta && typeof data.meta === "object" ? data.meta : {};
      renderTable();
      renderActiveFilters();
    } catch (error) {
      if (error?.name === "AbortError" || sequence !== state.loadSequence) return;
      state.rows = [];
      state.meta = {};
      renderTableMessage(error?.message || "Failed to load audit logs.", "text-danger");
      renderActiveFilters();
    } finally {
      if (sequence === state.loadSequence) {
        refreshBtn?.classList.remove("is-loading");
        if (refreshBtn) refreshBtn.disabled = false;
        state.auto.inFlight = false;
        state.loadController = null;
      }
    }
  };

  const loadPeople = async () => {
    const select = el("auditFilterPerson");
    if (!select) return;
    const currentValue = state.filters.personUserId;
    select.disabled = true;
    select.replaceChildren(new Option("Loading people...", ""));

    try {
      const params = new URLSearchParams({ fetch_audit_people: "1" });
      const response = await fetch(`${endpoint}?${params.toString()}`, {
        headers: { Accept: "application/json" },
      });
      const { data, rawText } = await readJsonResponse(response);
      if (!response.ok || !data?.success) {
        throw new Error(responseErrorMessage(data, rawText, "Failed to load people."));
      }

      select.replaceChildren(new Option("All people", ""));
      const people = Array.isArray(data.data) ? data.data : [];
      people.forEach((person) => {
        const userId = String(person?.user_id ?? "").trim();
        if (!userId) return;
        const label = String(person?.label || person?.display_name || userId).trim();
        select.appendChild(new Option(label, userId));
      });
      if (currentValue && !Array.from(select.options).some((option) => option.value === currentValue)) {
        select.appendChild(new Option(currentValue, currentValue));
      }
      select.value = currentValue;
    } catch {
      select.replaceChildren(new Option("All people", ""));
      if (currentValue) {
        select.appendChild(new Option(currentValue, currentValue));
        select.value = currentValue;
      }
    } finally {
      select.disabled = false;
      renderActiveFilters();
    }
  };

  const AUTO_REFRESH_MS = 30000;

  const scheduleAutoRefresh = () => {
    if (state.auto.interval) window.clearTimeout(state.auto.interval);
    state.auto.interval = null;
    // Decrypted free-text search can scan historical rows in PHP. Keep those
    // searches manual instead of repeating the expensive scan every 30s.
    if (state.q.trim()) return;
    state.auto.interval = window.setTimeout(() => {
      if (state.auto.inFlight) {
        scheduleAutoRefresh();
        return;
      }
      load().finally(scheduleAutoRefresh);
    }, AUTO_REFRESH_MS);
  };

  const triggerRefresh = async () => {
    scheduleAutoRefresh();
    await load();
  };

  const setExportStatus = (message, kind = "muted") => {
    const status = el("auditExportStatus");
    if (!status) return;
    if (state.statusTimer) window.clearTimeout(state.statusTimer);
    status.className = `small text-${kind}`;
    status.textContent = message;
    if (message && kind !== "danger") {
      state.statusTimer = window.setTimeout(() => {
        status.textContent = "";
      }, 6000);
    }
  };

  const filenameFromDisposition = (header, fallback) => {
    if (!header) return fallback;
    const utf8Match = header.match(/filename\*=UTF-8''([^;]+)/i);
    if (utf8Match) {
      try {
        return decodeURIComponent(utf8Match[1].trim().replace(/^"|"$/g, ""));
      } catch {
        // Fall through to the plain filename.
      }
    }
    const plainMatch = header.match(/filename="?([^";]+)"?/i);
    return plainMatch?.[1]?.trim() || fallback;
  };

  const downloadExport = async (format) => {
    const normalized = format === "pdf" ? "pdf" : "csv";
    const csvBtn = el("btnAuditExportCsv");
    const pdfBtn = el("btnAuditExportPdf");
    const activeBtn = normalized === "pdf" ? pdfBtn : csvBtn;
    const params = buildFilterParams();
    params.set("export", normalized);
    params.set("include_details", state.filters.includeDetails ? "1" : "0");

    [csvBtn, pdfBtn].forEach((button) => {
      if (!button) return;
      button.disabled = true;
      button.setAttribute("aria-busy", "true");
    });
    activeBtn?.classList.add("is-loading");
    setExportStatus(`Preparing ${normalized.toUpperCase()}...`);

    try {
      const response = await fetch(endpoint, {
        method: "POST",
        headers: {
          Accept: "application/json, text/csv, application/pdf",
          "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
          "X-CSRF-TOKEN": csrfToken,
        },
        body: params.toString(),
      });

      const contentType = (response.headers.get("Content-Type") || "").toLowerCase();
      const expectedType = normalized === "pdf" ? "application/pdf" : "text/csv";
      if (!response.ok || contentType.includes("application/json") || !contentType.includes(expectedType)) {
        const { data, rawText } = await readJsonResponse(response);
        throw new Error(responseErrorMessage(data, rawText, `Failed to create the ${normalized.toUpperCase()} file.`));
      }

      const blob = await response.blob();
      if (blob.size === 0) {
        throw new Error(`The ${normalized.toUpperCase()} export was empty. Please try again.`);
      }
      const fallback = `audit_logs.${normalized}`;
      const filename = filenameFromDisposition(response.headers.get("Content-Disposition"), fallback);
      const objectUrl = URL.createObjectURL(blob);
      const anchor = document.createElement("a");
      anchor.href = objectUrl;
      anchor.download = filename;
      document.body.appendChild(anchor);
      anchor.click();
      anchor.remove();
      window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
      setExportStatus(`${normalized.toUpperCase()} download ready.`, "success");
    } catch (error) {
      const message = error?.message || `Failed to create the ${normalized.toUpperCase()} file.`;
      setExportStatus(message, "danger");
      window.alert(message);
    } finally {
      activeBtn?.classList.remove("is-loading");
      [csvBtn, pdfBtn].forEach((button) => {
        if (!button) return;
        button.disabled = false;
        button.removeAttribute("aria-busy");
      });
    }
  };

  const validateDateRange = (fromEl, toEl) => {
    const from = fromEl?.value || "";
    const to = toEl?.value || "";
    fromEl?.setCustomValidity("");
    toEl?.setCustomValidity("");
    if (from && to && from > to) {
      toEl?.setCustomValidity("Date To must be on or after Date From.");
      toEl?.reportValidity();
      return false;
    }
    return true;
  };

  const wire = () => {
    const search = el("auditSearch");
    const refresh = el("btnAuditRefresh");
    const applyCols = el("btnAuditColumnsApply");
    const resetCols = el("btnAuditColumnsReset");
    const columnsModalEl = el("modalAuditColumns");
    const filterModalEl = el("modalAuditFilter");
    const filterFromEl = el("auditFilterFrom");
    const filterToEl = el("auditFilterTo");
    const filterPersonEl = el("auditFilterPerson");
    const includeDetailsEl = el("auditExportIncludeDetails");
    const filterApplyEl = el("btnAuditFilterApply");
    const filterResetEl = el("btnAuditFilterReset");

    search?.addEventListener("input", () => {
      state.q = search.value || "";
      state.pagination.currentPage = 1;
      scheduleAutoRefresh();
      if (state.timer) window.clearTimeout(state.timer);
      state.timer = window.setTimeout(() => {
        load();
        scheduleAutoRefresh();
      }, 300);
    });
    refresh?.addEventListener("click", () => triggerRefresh());
    el("btnAuditExportCsv")?.addEventListener("click", () => downloadExport("csv"));
    el("btnAuditExportPdf")?.addEventListener("click", () => downloadExport("pdf"));

    columnsModalEl?.addEventListener("show.bs.modal", renderColumnsModal);
    filterModalEl?.addEventListener("show.bs.modal", () => {
      if (filterFromEl) filterFromEl.value = state.filters.from;
      if (filterToEl) filterToEl.value = state.filters.to;
      if (filterPersonEl) filterPersonEl.value = state.filters.personUserId;
      if (includeDetailsEl) includeDetailsEl.checked = state.filters.includeDetails;
      validateDateRange(filterFromEl, filterToEl);
    });

    [filterFromEl, filterToEl].forEach((input) => {
      input?.addEventListener("change", () => validateDateRange(filterFromEl, filterToEl));
    });

    filterApplyEl?.addEventListener("click", () => {
      if (!validateDateRange(filterFromEl, filterToEl)) return;
      state.filters.from = filterFromEl?.value || "";
      state.filters.to = filterToEl?.value || "";
      state.filters.personUserId = filterPersonEl?.value || "";
      state.filters.includeDetails = Boolean(includeDetailsEl?.checked);
      state.pagination.currentPage = 1;
      if (filterModalEl) bootstrap.Modal.getOrCreateInstance(filterModalEl).hide();
      triggerRefresh();
    });

    filterResetEl?.addEventListener("click", () => {
      state.filters = { from: "", to: "", personUserId: "", includeDetails: false };
      if (filterFromEl) filterFromEl.value = "";
      if (filterToEl) filterToEl.value = "";
      if (filterPersonEl) filterPersonEl.value = "";
      if (includeDetailsEl) includeDetailsEl.checked = false;
      validateDateRange(filterFromEl, filterToEl);
      state.pagination.currentPage = 1;
      triggerRefresh();
    });

    entriesPerPageInput?.addEventListener("change", () => {
      const next = Math.max(1, Number.parseInt(entriesPerPageInput.value || "20", 10) || 20);
      state.pagination.entriesPerPage = next;
      entriesPerPageInput.value = String(next);
      state.pagination.currentPage = 1;
      renderTable();
    });

    resetCols?.addEventListener("click", () => {
      state.visibleCols = defaultVisibleCols();
      saveVisibleCols(state.visibleCols);
      renderColumnsModal();
      renderTable();
    });

    applyCols?.addEventListener("click", () => {
      const list = el("auditColumnsList");
      if (!list) return;
      const checked = Array.from(list.querySelectorAll('input[type="checkbox"][data-col-key]'))
        .filter((input) => input.checked)
        .map((input) => String(input.dataset.colKey || "").trim())
        .filter(Boolean);
      state.visibleCols = checked.length ? checked : defaultVisibleCols();
      saveVisibleCols(state.visibleCols);
      renderTable();
      if (columnsModalEl) bootstrap.Modal.getOrCreateInstance(columnsModalEl).hide();
    });
  };

  document.addEventListener("DOMContentLoaded", () => {
    state.visibleCols = loadVisibleCols();
    wire();
    renderHeader();
    renderActiveFilters();
    loadPeople();
    load();
    scheduleAutoRefresh();
  });
})();
