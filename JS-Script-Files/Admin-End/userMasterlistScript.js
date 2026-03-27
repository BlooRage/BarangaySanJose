(() => {
  const el = (id) => document.getElementById(id);

  const state = {
    rowsRaw: [],
    rows: [],
    search: "",
    role: "ALL",
    verification: "ALL",
    pagination: { currentPage: 1, entriesPerPage: 20 },
    auto: { interval: null, inFlight: false },
    lock: { selectedUserId: "", busy: false },
  };

  const tbody = el("userMasterTbody");
  const paginationEl = el("userMasterPagination");
  const pendingBadge = el("pendingUserBadge");
  const entriesInput = el("userMasterEntriesInput");
  const refreshBtn = el("btnUserMasterRefresh");
  const AUTO_REFRESH_MS = 30000;

  const lockModalEl = el("userLockModal");
  const lockSummaryName = el("userLockSummaryName");
  const lockSummaryMeta = el("userLockSummaryMeta");
  const lockCurrentStatus = el("userLockCurrentStatus");
  const lockCurrentReason = el("userLockCurrentReason");
  const lockFeedback = el("userLockFeedback");
  const lockUntilWrapper = el("userLockUntilWrapper");
  const lockUntilInput = el("userLockUntil");
  const lockReasonInput = el("userLockReason");
  const saveLockBtn = el("btnUserSaveLock");
  const unlockBtn = el("btnUserUnlockAccount");
  const lockModeInputs = Array.from(document.querySelectorAll('input[name="userLockMode"]'));

  const dateTimeFormatter = new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });

  let lockModal = null;

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

  const statusPillHtml = (text, tone) => {
    const cls = tone === "ok" ? "approved" : tone === "denied" ? "denied" : "pending";
    return `<span class="status-pill ${cls}">${escapeHtml(safe(text))}</span>`;
  };

  const pad = (value) => String(value).padStart(2, "0");

  const toDateTimeLocalValue = (date) => {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
      return "";
    }
    return [
      date.getFullYear(),
      pad(date.getMonth() + 1),
      pad(date.getDate()),
    ].join("-") + "T" + [pad(date.getHours()), pad(date.getMinutes())].join(":");
  };

  const sqlDateToLocalDate = (value) => {
    const raw = String(value ?? "").trim();
    if (!raw) return null;
    const parsed = new Date(raw.replace(" ", "T"));
    return Number.isNaN(parsed.getTime()) ? null : parsed;
  };

  const defaultLockUntilDate = () => {
    const next = new Date();
    next.setDate(next.getDate() + 1);
    next.setSeconds(0, 0);
    return next;
  };

  const formatDateTime = (value) => {
    const parsed = sqlDateToLocalDate(value);
    return parsed ? dateTimeFormatter.format(parsed) : safe(value);
  };

  const formatPhone = (value) => {
    const digits = String(value ?? "").replace(/\D/g, "");
    if (digits.length === 10) {
      return `+63${digits}`;
    }
    return safe(value);
  };

  const findRow = (userId) => state.rowsRaw.find((row) => String(row.user_id) === String(userId)) || null;

  const getSelectedLockMode = () => {
    const checked = lockModeInputs.find((input) => input.checked);
    return checked ? checked.value : "temporary";
  };

  const setSelectedLockMode = (mode) => {
    lockModeInputs.forEach((input) => {
      input.checked = input.value === mode;
    });
  };

  const clearLockFeedback = () => {
    if (!lockFeedback) return;
    lockFeedback.className = "alert d-none mb-3";
    lockFeedback.textContent = "";
  };

  const setLockFeedback = (tone, message) => {
    if (!lockFeedback) return;
    lockFeedback.className = `alert alert-${tone} mb-3`;
    lockFeedback.textContent = safe(message);
  };

  const setLockBusy = (isBusy) => {
    state.lock.busy = Boolean(isBusy);
    if (saveLockBtn) saveLockBtn.disabled = state.lock.busy;
    if (unlockBtn) unlockBtn.disabled = state.lock.busy;
    if (lockUntilInput) lockUntilInput.disabled = state.lock.busy || getSelectedLockMode() !== "temporary";
    if (lockReasonInput) lockReasonInput.disabled = state.lock.busy;
    lockModeInputs.forEach((input) => {
      input.disabled = state.lock.busy;
    });
  };

  const syncLockModeUI = () => {
    const isTemporary = getSelectedLockMode() === "temporary";
    if (lockUntilWrapper) {
      lockUntilWrapper.classList.toggle("d-none", !isTemporary);
    }
    if (lockUntilInput) {
      lockUntilInput.disabled = state.lock.busy || !isTemporary;
    }

    const row = findRow(state.lock.selectedUserId);
    if (saveLockBtn) {
      saveLockBtn.textContent = row?.is_locked ? "Update Lock" : "Apply Lock";
    }
  };

  const updatePendingBadge = () => {
    if (!pendingBadge) return;
    const count = state.rowsRaw.filter((row) => String(row.verification_status || "") === "Pending").length;
    pendingBadge.textContent = String(count);
    pendingBadge.classList.toggle("d-none", count <= 0);
  };

  const applyFilters = () => {
    const query = state.search.toLowerCase();
    state.rows = state.rowsRaw.filter((row) => {
      if (state.role !== "ALL" && String(row.role_access || "") !== state.role) return false;
      if (state.verification !== "ALL" && String(row.verification_status || "") !== state.verification) return false;
      if (!query) return true;

      const bag = [
        row.user_id,
        row.display_name,
        row.email,
        row.phone_number,
        row.role_access,
        row.account_status_display,
        row.verification_status,
        row.lock_reason,
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
      tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">No users found.</td></tr>';
      renderPagination();
      return;
    }

    tbody.innerHTML = pageRows.map((row) => {
      const verificationTone = String(row.verification_status || "") === "Verified" ? "ok" : "pending";
      const accountStatusKey = String(row.account_status_display || row.account_status || "").toLowerCase();
      const accountTone = (
        accountStatusKey.includes("active")
        && !accountStatusKey.includes("inactive")
        && !accountStatusKey.includes("lock")
      ) ? "ok" : (
        accountStatusKey.includes("lock")
        || accountStatusKey.includes("inactive")
        || accountStatusKey.includes("revoked")
        || accountStatusKey.includes("suspend")
        || accountStatusKey.includes("disabled")
        || accountStatusKey.includes("deactivated")
        || accountStatusKey.includes("deleted")
      ) ? "denied" : "pending";

      const manageButton = row.can_manage_lock
        ? `<button type="button" class="btn btn-sm btn-outline-danger" data-action="manage-lock" data-user-id="${escapeHtml(row.user_id)}">${row.is_locked ? "Manage Lock" : "Lock Account"}</button>`
        : `<button type="button" class="btn btn-sm btn-outline-secondary" disabled title="${escapeHtml(row.manage_lock_disabled_reason || "Unavailable")}">${row.is_locked ? "Locked" : "Unavailable"}</button>`;

      const archiveButton = row.can_archive_account
        ? `<button type="button" class="btn btn-sm btn-outline-dark" data-action="archive-account" data-user-id="${escapeHtml(row.user_id)}">Archive</button>`
        : `<button type="button" class="btn btn-sm btn-outline-secondary" disabled title="${escapeHtml(row.archive_account_disabled_reason || "Archive unavailable")}">Archive</button>`;

      return `
        <tr>
          <td>${escapeHtml(safe(row.user_id))}</td>
          <td>${escapeHtml(safe(row.display_name))}</td>
          <td>${escapeHtml(safe(row.role_access))}</td>
          <td>${escapeHtml(safe(row.email))}</td>
          <td>${escapeHtml(formatPhone(row.phone_number))}</td>
          <td>${statusPillHtml(row.account_status_display || row.account_status, accountTone)}</td>
          <td>${statusPillHtml(row.verification_status, verificationTone)}</td>
          <td>${escapeHtml(formatDateTime(row.account_created))}</td>
          <td>${escapeHtml(formatDateTime(row.last_login))}</td>
          <td>
            <div class="user-masterlist-actions">
              ${manageButton}
              ${archiveButton}
            </div>
          </td>
        </tr>
      `;
    }).join("");

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

  const populateLockModal = (row) => {
    if (!row) return;

    state.lock.selectedUserId = String(row.user_id || "");
    clearLockFeedback();

    if (lockSummaryName) lockSummaryName.textContent = safe(row.display_name);
    if (lockSummaryMeta) {
      lockSummaryMeta.textContent = `${safe(row.user_id)} | ${safe(row.role_access)} | ${safe(row.email)}`;
    }
    if (lockCurrentStatus) {
      lockCurrentStatus.textContent = safe(row.account_status_display || row.account_status);
    }
    if (lockCurrentReason) {
      lockCurrentReason.textContent = String(row.lock_reason || "").trim() || "No lock reason saved.";
    }
    if (lockReasonInput) {
      lockReasonInput.value = String(row.lock_reason || "");
    }

    const mode = row.is_locked && String(row.lock_type || "") === "permanent" ? "permanent" : "temporary";
    setSelectedLockMode(mode);

    if (lockUntilInput) {
      const now = new Date();
      lockUntilInput.min = toDateTimeLocalValue(now);
      const currentLockUntil = sqlDateToLocalDate(row.lock_until);
      const nextLockUntil = currentLockUntil || defaultLockUntilDate();
      lockUntilInput.value = toDateTimeLocalValue(nextLockUntil);
    }

    if (unlockBtn) {
      unlockBtn.classList.toggle("d-none", !row.is_locked);
    }

    syncLockModeUI();
    setLockBusy(false);
  };

  const load = async () => {
    if (state.auto.inFlight) return;
    state.auto.inFlight = true;

    if (refreshBtn) {
      refreshBtn.classList.add("is-loading");
      refreshBtn.disabled = true;
    }

    try {
      const params = new URLSearchParams();
      params.set("fetch_user_masterlist", "1");
      params.set("limit", "1000");
      if (state.search.trim()) params.set("q", state.search.trim());
      if (state.role !== "ALL") params.set("role", state.role);
      if (state.verification !== "ALL") params.set("verification", state.verification.toLowerCase());

      const res = await fetch(`../PhpFiles/Admin-End/userMasterlist.php?${params.toString()}`, {
        headers: { Accept: "application/json" },
      });
      const data = await res.json().catch(() => null);
      if (!res.ok || !data?.success) {
        throw new Error(data?.message || "Failed to load users.");
      }

      state.rowsRaw = Array.isArray(data.data) ? data.data : [];
      updatePendingBadge();
      renderTable();

      if (state.lock.selectedUserId && lockModalEl?.classList.contains("show")) {
        const refreshedRow = findRow(state.lock.selectedUserId);
        if (refreshedRow) {
          populateLockModal(refreshedRow);
        } else if (lockModal) {
          lockModal.hide();
        }
      }
    } catch (err) {
      if (tbody) {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">${escapeHtml(safe(err?.message || "Unable to load users."))}</td></tr>`;
      }
    } finally {
      state.auto.inFlight = false;
      if (refreshBtn) {
        refreshBtn.classList.remove("is-loading");
        refreshBtn.disabled = false;
      }
    }
  };

  const submitLockAction = async (action) => {
    const row = findRow(state.lock.selectedUserId);
    if (!row || state.lock.busy) return;

    clearLockFeedback();

    const formData = new FormData();
    formData.set("action", action);
    formData.set("user_id", row.user_id);

    if (action === "lock_account") {
      const mode = getSelectedLockMode();
      formData.set("lock_mode", mode);
      formData.set("lock_reason", lockReasonInput?.value?.trim() || "");

      if (mode === "temporary") {
        const lockUntil = lockUntilInput?.value?.trim() || "";
        if (!lockUntil) {
          setLockFeedback("danger", "Choose the date and time when the lock should end.");
          return;
        }
        formData.set("lock_until", lockUntil);
      }
    }

    setLockBusy(true);
    try {
      const res = await fetch("../PhpFiles/Admin-End/userMasterlist.php", {
        method: "POST",
        body: formData,
        headers: { Accept: "application/json" },
      });
      const data = await res.json().catch(() => null);
      if (!res.ok || !data?.success) {
        throw new Error(data?.message || "Unable to update the account lock.");
      }

      const successMessage = data.message || "Account updated successfully.";
      await load();
      if (state.lock.selectedUserId) {
        setLockFeedback("success", successMessage);
      }
    } catch (err) {
      setLockFeedback("danger", err?.message || "Unable to update the account lock.");
    } finally {
      setLockBusy(false);
    }
  };

  const submitArchiveAction = async (userId) => {
    const row = findRow(userId);
    if (!row || state.auto.inFlight || state.lock.busy) return;

    const confirmed = window.confirm(`Archive ${safe(row.display_name)}? You can permanently delete the account later from User Archive.`);
    if (!confirmed) return;

    const formData = new FormData();
    formData.set("action", "archive_account");
    formData.set("user_id", row.user_id);

    if (refreshBtn) {
      refreshBtn.classList.add("is-loading");
      refreshBtn.disabled = true;
    }

    try {
      const res = await fetch("../PhpFiles/Admin-End/userMasterlist.php", {
        method: "POST",
        body: formData,
        headers: { Accept: "application/json" },
      });
      const data = await res.json().catch(() => null);
      if (!res.ok || !data?.success) {
        throw new Error(data?.message || "Unable to archive the account.");
      }

      await load();
      window.alert(data.message || "Account archived successfully.");
    } catch (err) {
      window.alert(err?.message || "Unable to archive the account.");
    } finally {
      if (refreshBtn) {
        refreshBtn.classList.remove("is-loading");
        refreshBtn.disabled = false;
      }
    }
  };

  const wire = () => {
    document.querySelectorAll(".status-filter-btn").forEach((btn) => {
      btn.addEventListener("click", () => {
        document.querySelectorAll(".status-filter-btn").forEach((item) => item.classList.remove("active"));
        btn.classList.add("active");
        state.verification = btn.dataset.filter || "ALL";
        state.pagination.currentPage = 1;
        renderTable();
      });
    });

    const q = el("userMasterSearch");
    if (q) {
      q.addEventListener("input", () => {
        state.search = q.value || "";
        state.pagination.currentPage = 1;
        renderTable();
      });
    }

    const role = el("userMasterRoleFilter");
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
      tbody.addEventListener("click", (event) => {
        const button = event.target.closest('[data-action="manage-lock"]');
        if (button) {
          const row = findRow(button.dataset.userId || "");
          if (!row) return;
          populateLockModal(row);
          if (lockModal) lockModal.show();
          return;
        }

        const archiveButton = event.target.closest('[data-action="archive-account"]');
        if (!archiveButton) return;

        submitArchiveAction(archiveButton.dataset.userId || "").catch(() => {});
      });
    }

    lockModeInputs.forEach((input) => {
      input.addEventListener("change", syncLockModeUI);
    });

    if (saveLockBtn) {
      saveLockBtn.addEventListener("click", () => {
        submitLockAction("lock_account").catch(() => {});
      });
    }

    if (unlockBtn) {
      unlockBtn.addEventListener("click", () => {
        submitLockAction("unlock_account").catch(() => {});
      });
    }

    if (lockModalEl) {
      lockModalEl.addEventListener("hidden.bs.modal", () => {
        state.lock.selectedUserId = "";
        clearLockFeedback();
      });
    }

    scheduleAutoRefresh();
  };

  document.addEventListener("DOMContentLoaded", () => {
    if (entriesInput) {
      state.pagination.entriesPerPage = Math.max(1, Number.parseInt(entriesInput.value || "20", 10) || 20);
    }

    if (lockModalEl && window.bootstrap?.Modal) {
      lockModal = window.bootstrap.Modal.getOrCreateInstance(lockModalEl);
    }

    wire();
    load().catch(() => {});
  });
})();
