(() => {
  const el = (id) => document.getElementById(id);
  const csrfToken = String(window.ADMIN_USER_ARCHIVE_CSRF_TOKEN || "");

  const state = {
    rowsRaw: [],
    rows: [],
    search: "",
    role: "ALL",
    pagination: { currentPage: 1, entriesPerPage: 20 },
    auto: { interval: null, inFlight: false },
  };

  const tbody = el("userArchiveTbody");
  const paginationEl = el("userArchivePagination");
  const entriesInput = el("userArchiveEntriesInput");
  const refreshBtn = el("btnUserArchiveRefresh");
  const AUTO_REFRESH_MS = 30000;

  const dateTimeFormatter = new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });

  const safe = (value) => {
    const text = String(value ?? "").trim();
    return text !== "" ? text : "—";
  };

  const escapeHtml = (value) => String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");

  const statusPillHtml = (text, tone = "pending") => {
    const cls = tone === "ok" ? "approved" : tone === "denied" ? "denied" : "pending";
    return `<span class="status-pill ${cls}">${escapeHtml(safe(text))}</span>`;
  };

  const sqlDateToLocalDate = (value) => {
    const raw = String(value ?? "").trim();
    if (!raw) return null;
    const parsed = new Date(raw.replace(" ", "T"));
    return Number.isNaN(parsed.getTime()) ? null : parsed;
  };

  const formatDateTime = (value) => {
    const parsed = sqlDateToLocalDate(value);
    return parsed ? dateTimeFormatter.format(parsed) : safe(value);
  };

  const findRow = (userId) => state.rowsRaw.find((row) => String(row.user_id) === String(userId)) || null;

  const applyFilters = () => {
    const query = state.search.toLowerCase();
    state.rows = state.rowsRaw.filter((row) => {
      if (state.role !== "ALL" && String(row.role_access || "") !== state.role) return false;
      if (!query) return true;

      const bag = [
        row.user_id,
        row.display_name,
        row.email,
        row.phone_number,
        row.role_access,
        row.previous_status,
      ].join(" ").toLowerCase();

      return bag.includes(query);
    });
  };

  const renderPagination = () => {
    if (!paginationEl) return;
    paginationEl.innerHTML = "";

    const totalRows = state.rows.length;
    const totalPages = Math.max(1, Math.ceil(totalRows / state.pagination.entriesPerPage));
    if (state.pagination.currentPage > totalPages) state.pagination.currentPage = totalPages;

    const addButton = (label, page, disabled = false, active = false) => {
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
      addButton("<", 1, true, false);
      addButton("1", 1, false, true);
      addButton(">", 1, true, false);
      return;
    }

    addButton("<", Math.max(1, state.pagination.currentPage - 1), state.pagination.currentPage <= 1, false);
    let startPage = Math.max(1, state.pagination.currentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);
    for (let page = startPage; page <= endPage; page += 1) {
      addButton(String(page), page, false, page === state.pagination.currentPage);
    }
    addButton(">", Math.min(totalPages, state.pagination.currentPage + 1), state.pagination.currentPage >= totalPages, false);
  };

  const renderTable = () => {
    if (!tbody) return;
    applyFilters();

    const totalPages = Math.max(1, Math.ceil(state.rows.length / state.pagination.entriesPerPage));
    if (state.pagination.currentPage > totalPages) state.pagination.currentPage = totalPages;
    if (state.pagination.currentPage < 1) state.pagination.currentPage = 1;

    const start = (state.pagination.currentPage - 1) * state.pagination.entriesPerPage;
    const pageRows = state.rows.slice(start, start + state.pagination.entriesPerPage);

    if (!pageRows.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No archived users found.</td></tr>';
      renderPagination();
      return;
    }

    tbody.innerHTML = pageRows.map((row) => `
      <tr>
        <td>${escapeHtml(safe(row.user_id))}</td>
        <td>${escapeHtml(safe(row.display_name))}</td>
        <td>${escapeHtml(safe(row.role_access))}</td>
        <td>${statusPillHtml(row.previous_status, "pending")}</td>
        <td>${escapeHtml(formatDateTime(row.archived_at))}</td>
        <td>
          <div class="user-archive-actions">
            <button type="button" class="btn btn-sm btn-primary" data-action="restore" data-user-id="${escapeHtml(row.user_id)}">Restore</button>
            <button type="button" class="btn btn-sm btn-danger" data-action="delete" data-user-id="${escapeHtml(row.user_id)}">Delete</button>
          </div>
        </td>
      </tr>
    `).join("");

    renderPagination();
  };

  const scheduleAutoRefresh = () => {
    if (state.auto.interval) clearTimeout(state.auto.interval);
    state.auto.interval = setTimeout(() => {
      if (state.auto.inFlight) {
        scheduleAutoRefresh();
        return;
      }
      scheduleAutoRefresh();
      load().catch(() => {});
    }, AUTO_REFRESH_MS);
  };

  const submitAction = async (action, userId) => {
    const row = findRow(userId);
    if (!row || state.auto.inFlight) return;

    const message = action === "restore"
      ? `Restore ${safe(row.display_name)} from the archive?`
      : `Delete ${safe(row.display_name)} from the archive? This will hide the account from User Management.`;
    if (!(await window.UniversalModal.confirm(message, { confirmLabel: "Continue", confirmClass: "btn btn-danger" }))) return;

    if (refreshBtn) {
      refreshBtn.classList.add("is-loading");
      refreshBtn.disabled = true;
    }

    try {
      const res = await fetch("../PhpFiles/Admin-End/archiveUserActions.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": csrfToken,
        },
        body: JSON.stringify({ action, user_id: row.user_id }),
      });
      const data = await res.json().catch(() => null);
      if (!res.ok || !data?.success) {
        throw new Error(data?.message || "Unable to update the archived account.");
      }

      await load();
      window.alert(data.message || "Archived account updated successfully.");
    } catch (err) {
      window.alert(err?.message || "Unable to update the archived account.");
    } finally {
      if (refreshBtn) {
        refreshBtn.classList.remove("is-loading");
        refreshBtn.disabled = false;
      }
    }
  };

  const load = async () => {
    if (state.auto.inFlight) return;
    state.auto.inFlight = true;

    if (refreshBtn) {
      refreshBtn.classList.add("is-loading");
      refreshBtn.disabled = true;
    }

    try {
      const res = await fetch("../PhpFiles/Admin-End/archiveUser.php", {
        headers: { Accept: "application/json" },
      });
      const data = await res.json().catch(() => null);
      if (!res.ok || !data?.success) {
        throw new Error(data?.message || "Failed to load archived users.");
      }

      state.rowsRaw = Array.isArray(data.data) ? data.data : [];
      renderTable();
    } catch (err) {
      if (tbody) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${escapeHtml(safe(err?.message || "Unable to load archived users."))}</td></tr>`;
      }
    } finally {
      state.auto.inFlight = false;
      if (refreshBtn) {
        refreshBtn.classList.remove("is-loading");
        refreshBtn.disabled = false;
      }
    }
  };

  const wire = () => {
    const search = el("userArchiveSearch");
    if (search) {
      search.addEventListener("input", () => {
        state.search = search.value || "";
        state.pagination.currentPage = 1;
        renderTable();
      });
    }

    const role = el("userArchiveRoleFilter");
    if (role) {
      role.addEventListener("change", () => {
        state.role = role.value || "ALL";
        state.pagination.currentPage = 1;
        renderTable();
      });
    }

    if (entriesInput) {
      entriesInput.addEventListener("change", () => {
        const next = Math.max(1, Number.parseInt(entriesInput.value || "20", 10) || 20);
        state.pagination.entriesPerPage = next;
        entriesInput.value = String(next);
        state.pagination.currentPage = 1;
        renderTable();
      });
    }

    if (refreshBtn) {
      refreshBtn.addEventListener("click", () => {
        scheduleAutoRefresh();
        load().catch(() => {});
      });
    }

    if (tbody) {
      document.addEventListener("click", (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) return;

        // Action menus are moved to <body> by the shared admin table helper.
        const button = target.closest('[data-action="restore"], [data-action="delete"]');
        if (!button) return;

        const action = String(button.dataset.action || "").trim();
        const userId = String(button.dataset.userId || "").trim();
        if (!action || !userId) return;

        submitAction(action, userId).catch(() => {});
      });
    }

    scheduleAutoRefresh();
  };

  document.addEventListener("DOMContentLoaded", () => {
    if (entriesInput) {
      state.pagination.entriesPerPage = Math.max(1, Number.parseInt(entriesInput.value || "20", 10) || 20);
    }

    wire();
    load().catch(() => {});
  });
})();
