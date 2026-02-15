(() => {
  const el = (id) => document.getElementById(id);

  const state = {
    q: "",
    timer: null,
    rows: [],
    visibleCols: null,
  };

  const safeText = (v) => {
    const s = String(v ?? "").trim();
    return s !== "" ? s : "—";
  };

  const truncate = (s, n = 60) => {
    const t = String(s ?? "");
    if (t.length <= n) return t;
    return t.slice(0, n - 1) + "…";
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
      // ignore
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
      th.innerText = c.label;
      if (c.nowrap) th.style.whiteSpace = "nowrap";
      theadRow.appendChild(th);
    });
  };

  const renderTable = () => {
    const tbody = el("auditTbody");
    const activeCols = getActiveColumns();

    renderHeader();

    if (!tbody) return;
    const rows = state.rows || [];
    if (!rows.length) {
      tbody.innerHTML = `<tr><td colspan="${activeCols.length}" class="text-center text-muted py-4">No records found.</td></tr>`;
      return;
    }

    tbody.innerHTML = "";
    rows.forEach((r) => {
      const tr = document.createElement("tr");
      activeCols.forEach((c) => {
        const td = document.createElement("td");
        const raw = c.get(r);
        const text = c.truncate ? truncate(raw, c.truncate) : String(raw ?? "");
        td.innerText = text;
        if (c.truncate) td.title = String(raw ?? "");
        if (c.nowrap) td.style.whiteSpace = "nowrap";
        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });
  };

  const renderColumnsModal = () => {
    const list = el("auditColumnsList");
    if (!list) return;
    const selected = new Set(state.visibleCols || defaultVisibleCols());
    list.innerHTML = "";

    columns.forEach((c) => {
      const col = document.createElement("div");
      col.className = "col-12 col-md-6 col-lg-4";

      const wrap = document.createElement("label");
      wrap.className = "audit-columns-check d-flex align-items-center gap-2 w-100";

      const cb = document.createElement("input");
      cb.type = "checkbox";
      cb.className = "form-check-input m-0";
      cb.dataset.colKey = c.key;
      cb.checked = selected.has(c.key);

      const text = document.createElement("div");
      text.className = "fw-semibold";
      text.innerText = c.label;

      wrap.appendChild(cb);
      wrap.appendChild(text);
      col.appendChild(wrap);
      list.appendChild(col);
    });
  };

  const load = async () => {
    const tbody = el("auditTbody");
    const refreshBtn = el("btnAuditRefresh");
    const activeCols = getActiveColumns();
    const colCount = activeCols.length || 1;
    renderHeader();
    if (refreshBtn) {
      refreshBtn.classList.add("is-loading");
      refreshBtn.disabled = true;
    }
    if (tbody) {
      tbody.innerHTML = `<tr><td colspan="${colCount}" class="text-center text-muted py-4">Loading...</td></tr>`;
    }

    const params = new URLSearchParams();
    params.set("fetch_audit_logs", "1");
    if (state.q.trim()) params.set("q", state.q.trim());
    params.set("limit", "200");

    try {
      const res = await fetch(`../PhpFiles/Admin-End/auditLogs.php?${params.toString()}`, {
        headers: { Accept: "application/json" },
      });

      let data = null;
      let rawText = "";
      try {
        rawText = await res.text();
        data = rawText ? JSON.parse(rawText) : null;
      } catch {
        data = null;
      }

      if (!res.ok || !data || !data.success) {
        const msg =
          (data && (data.message || data.error)) ||
          (rawText && rawText.trim() ? rawText.trim().slice(0, 300) : "") ||
          "Failed to load audit logs.";
        if (tbody) {
          tbody.innerHTML = `<tr><td colspan="${colCount}" class="text-center text-danger py-4">${msg}</td></tr>`;
        }
        return;
      }

      state.rows = Array.isArray(data.data) ? data.data : [];
      renderTable();
    } finally {
      if (refreshBtn) {
        refreshBtn.classList.remove("is-loading");
        refreshBtn.disabled = false;
      }
    }
  };

  const wire = () => {
    const search = el("auditSearch");
    const refresh = el("btnAuditRefresh");
    const applyCols = el("btnAuditColumnsApply");
    const resetCols = el("btnAuditColumnsReset");
    const columnsModalEl = el("modalAuditColumns");

    if (search) {
      search.addEventListener("input", () => {
        state.q = search.value || "";
        if (state.timer) window.clearTimeout(state.timer);
        state.timer = window.setTimeout(load, 250);
      });
    }
    if (refresh) refresh.addEventListener("click", load);

    if (columnsModalEl) {
      columnsModalEl.addEventListener("show.bs.modal", () => {
        renderColumnsModal();
      });
    }

    if (resetCols) {
      resetCols.addEventListener("click", () => {
        state.visibleCols = defaultVisibleCols();
        saveVisibleCols(state.visibleCols);
        renderColumnsModal();
        renderTable();
      });
    }

    if (applyCols) {
      applyCols.addEventListener("click", () => {
        const list = el("auditColumnsList");
        if (!list) return;
        const checked = Array.from(list.querySelectorAll('input[type="checkbox"][data-col-key]'))
          .filter((x) => x.checked)
          .map((x) => String(x.dataset.colKey || "").trim())
          .filter(Boolean);
        state.visibleCols = checked.length ? checked : defaultVisibleCols();
        saveVisibleCols(state.visibleCols);
        renderTable();
        if (columnsModalEl) {
          bootstrap.Modal.getOrCreateInstance(columnsModalEl).hide();
        }
      });
    }
  };

  document.addEventListener("DOMContentLoaded", () => {
    state.visibleCols = loadVisibleCols();
    wire();
    renderHeader();
    load();
  });
})();
