(() => {
  const el = (id) => document.getElementById(id);

  const state = {
    rowsRaw: [],
    rows: [],
    search: "",
    role: "ALL",
    permission: "ALL",
    canManageActions: false,
    pagination: { currentPage: 1, entriesPerPage: 20 },
    auto: { secondsLeft: 60, interval: null, inFlight: false },
  };

  const tbody = el("officialsMgmtTbody");
  const paginationEl = el("officialsMgmtPagination");
  const entriesInput = el("officialsMgmtEntriesInput");
  const countdownEl = el("officialsMgmtAutoRefreshCountdown");
  const refreshBtn = el("btnOfficialsMgmtRefresh");
  const revokedBadge = el("revokedOfficialsBadge");

  const safe = (v) => {
    const s = String(v ?? "").trim();
    return s !== "" ? s : "—";
  };

  const badgeHtml = (txt, tone) => {
    const cls = tone === "ok" ? "bg-success" : tone === "danger" ? "bg-danger" : "bg-secondary";
    return `<span class="badge ${cls}">${safe(txt)}</span>`;
  };

  const updateRevokedBadge = () => {
    if (!revokedBadge) return;
    const count = state.rowsRaw.filter((r) => String(r.permission_state || "") === "Revoked").length;
    revokedBadge.textContent = String(count);
    revokedBadge.classList.toggle("d-none", count <= 0);
  };

  const applyFilters = () => {
    const q = state.search.toLowerCase();
    state.rows = state.rowsRaw.filter((r) => {
      if (state.role !== "ALL" && String(r.role_access || "") !== state.role) return false;
      if (state.permission !== "ALL" && String(r.permission_state || "") !== state.permission) return false;
      if (!q) return true;
      const bag = [
        r.official_id,
        r.user_id,
        r.full_name,
        r.role_access,
        r.position_access,
        r.department,
        r.employment_status,
        r.account_status,
        r.email,
        r.phone_number,
      ].join(" ").toLowerCase();
      return bag.includes(q);
    });
  };

  const renderPagination = () => {
    if (!paginationEl) return;
    paginationEl.innerHTML = "";

    const totalRows = state.rows.length;
    const totalPages = Math.max(1, Math.ceil(totalRows / state.pagination.entriesPerPage));
    if (state.pagination.currentPage > totalPages) state.pagination.currentPage = totalPages;

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
    for (let p = startPage; p <= endPage; p += 1) {
      addBtn(String(p), p, false, p === state.pagination.currentPage);
    }
    addBtn(">", Math.min(totalPages, state.pagination.currentPage + 1), state.pagination.currentPage >= totalPages, false);
  };

  const actionButtonHtml = (row) => {
    if (!state.canManageActions) {
      return `<span class="text-muted small">SuperAdmin only</span>`;
    }
    const approvalState = String(row.profile_approval_state || "");
    if (approvalState === "PendingApproval") {
      return `
        <div class="d-flex gap-1 flex-wrap">
          <button class="btn btn-sm btn-success officials-action-btn" data-action="approve_profile" data-official-id="${safe(row.official_id)}">Approve</button>
          <button class="btn btn-sm btn-outline-danger officials-action-btn" data-action="reject_profile" data-official-id="${safe(row.official_id)}">Reject</button>
        </div>
      `;
    }
    const isRevoked = String(row.permission_state || "") === "Revoked";
    if (isRevoked) {
      return `<button class="btn btn-sm btn-success officials-action-btn" data-action="restore_permission" data-official-id="${safe(row.official_id)}">Restore</button>`;
    }
    return `<button class="btn btn-sm btn-outline-danger officials-action-btn" data-action="revoke_permission" data-official-id="${safe(row.official_id)}">Revoke</button>`;
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
      tbody.innerHTML = `<tr><td colspan="11" class="text-center text-muted py-4">No officials found.</td></tr>`;
      renderPagination();
      return;
    }

    tbody.innerHTML = pageRows.map((r) => {
      const permTone = String(r.permission_state || "") === "Revoked" ? "danger" : "ok";
      return `
      <tr>
        <td>${safe(r.official_id)}</td>
        <td>${safe(r.user_id)}</td>
        <td>${safe(r.full_name)}</td>
        <td>${safe(r.role_access)}</td>
        <td>${safe(r.position_access)}</td>
        <td>${safe(r.department)}</td>
        <td>${safe(r.employment_status)}</td>
        <td>${safe(r.date_hired)}</td>
        <td>${safe(r.account_status)}</td>
        <td>${badgeHtml(r.permission_state, permTone)}</td>
        <td>${badgeHtml(r.profile_approval_state || "PendingApproval", (String(r.profile_approval_state || "") === "Approved" ? "ok" : (String(r.profile_approval_state || "") === "Rejected" ? "danger" : "secondary")))}</td>
        <td>${actionButtonHtml(r)}</td>
      </tr>
    `;
    }).join("");

    renderPagination();
    wireActionButtons();
  };

  const renderCountdown = () => {
    if (!countdownEl) return;
    countdownEl.textContent = state.auto.secondsLeft > 0 ? `Auto refresh in ${state.auto.secondsLeft}s` : "";
  };

  const postAction = async (action, officialId) => {
    const body = new FormData();
    body.append("action", action);
    body.append("official_id", officialId);
    const res = await fetch("../PhpFiles/Admin-End/officialsManagement.php", {
      method: "POST",
      body,
      headers: { Accept: "application/json" },
    });
    const data = await res.json().catch(() => null);
    if (!res.ok || !data?.success) {
      throw new Error(data?.message || "Action failed.");
    }
    return data;
  };

  const wireActionButtons = () => {
    document.querySelectorAll(".officials-action-btn").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const action = String(btn.getAttribute("data-action") || "");
        const officialId = String(btn.getAttribute("data-official-id") || "");
        if (!action || !officialId) return;

        let label = "update";
        if (action === "revoke_permission") label = "revoke";
        else if (action === "restore_permission") label = "restore";
        else if (action === "approve_profile") label = "approve";
        else if (action === "reject_profile") label = "reject";
        const target = (action === "approve_profile" || action === "reject_profile")
          ? "this profile approval"
          : "this account permission";
        const ok = window.confirm(`Are you sure you want to ${label} ${target}?`);
        if (!ok) return;

        try {
          btn.disabled = true;
          await postAction(action, officialId);
          await load();
        } catch (err) {
          alert(err?.message || "Unable to update permission state.");
        } finally {
          btn.disabled = false;
        }
      });
    });
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
      params.set("fetch_officials_management", "1");
      params.set("limit", "1000");
      if (state.search.trim()) params.set("q", state.search.trim());

      const res = await fetch(`../PhpFiles/Admin-End/officialsManagement.php?${params.toString()}`, { headers: { Accept: "application/json" } });
      const data = await res.json().catch(() => null);
      if (!res.ok || !data?.success) throw new Error(data?.message || "Failed to load officials.");

      state.rowsRaw = Array.isArray(data.data) ? data.data : [];
      state.canManageActions = Boolean(data.can_manage_actions);
      updateRevokedBadge();
      renderTable();
    } catch (err) {
      if (tbody) tbody.innerHTML = `<tr><td colspan="12" class="text-center text-danger py-4">${safe(err?.message || "Unable to load officials.")}</td></tr>`;
    } finally {
      state.auto.inFlight = false;
      state.auto.secondsLeft = 60;
      renderCountdown();
      if (refreshBtn) {
        refreshBtn.classList.remove("is-loading");
        refreshBtn.disabled = false;
      }
    }
  };

  const wire = () => {
    document.querySelectorAll(".status-filter-btn").forEach((btn) => {
      btn.addEventListener("click", () => {
        if (btn.dataset.filter) {
          document.querySelectorAll(".status-filter-btn[data-filter]").forEach((x) => x.classList.remove("active"));
          btn.classList.add("active");
          state.role = btn.dataset.filter || "ALL";
        }
        if (btn.dataset.permissionFilter) {
          document.querySelectorAll(".status-filter-btn[data-permission-filter]").forEach((x) => x.classList.remove("active"));
          btn.classList.add("active");
          state.permission = btn.dataset.permissionFilter || "ALL";
        }
        state.pagination.currentPage = 1;
        renderTable();
      });
    });

    const q = el("officialsMgmtSearch");
    if (q) {
      q.addEventListener("input", () => {
        state.search = q.value || "";
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
        load().catch(() => {});
      });
    }

    renderCountdown();
    if (state.auto.interval) clearInterval(state.auto.interval);
    state.auto.interval = setInterval(() => {
      if (state.auto.inFlight) return;
      state.auto.secondsLeft -= 1;
      if (state.auto.secondsLeft <= 0) {
        load().catch(() => {});
        return;
      }
      renderCountdown();
    }, 1000);
  };

  document.addEventListener("DOMContentLoaded", () => {
    if (entriesInput) {
      state.pagination.entriesPerPage = Math.max(1, Number.parseInt(entriesInput.value || "20", 10) || 20);
    }
    wire();
    load().catch(() => {});
  });
})();
