(() => {
  const el = (id) => document.getElementById(id);

  const state = {
    rowsRaw: [],
    rows: [],
    search: "",
    role: "ALL",
    verification: "ALL",
    pagination: { currentPage: 1, entriesPerPage: 20 },
    auto: { secondsLeft: 15, interval: null, inFlight: false },
  };

  const tbody = el("userMasterTbody");
  const paginationEl = el("userMasterPagination");
  const pendingBadge = el("pendingUserBadge");
  const entriesInput = el("userMasterEntriesInput");
  const countdownEl = el("userMasterAutoRefreshCountdown");
  const refreshBtn = el("btnUserMasterRefresh");

  const safe = (v) => {
    const s = String(v ?? "").trim();
    return s !== "" ? s : "—";
  };

  const statusPillHtml = (txt, tone) => {
    const cls = tone === "ok" ? "approved" : tone === "denied" ? "denied" : "pending";
    return `<span class="status-pill ${cls}">${safe(txt)}</span>`;
  };

  const updatePendingBadge = () => {
    if (!pendingBadge) return;
    const count = state.rowsRaw.filter((r) => String(r.verification_status || "") === "Pending").length;
    pendingBadge.textContent = String(count);
    pendingBadge.classList.toggle("d-none", count <= 0);
  };

  const applyFilters = () => {
    const q = state.search.toLowerCase();
    state.rows = state.rowsRaw.filter((r) => {
      if (state.role !== "ALL" && String(r.role_access || "") !== state.role) return false;
      if (state.verification !== "ALL" && String(r.verification_status || "") !== state.verification) return false;
      if (!q) return true;
      const bag = [
        r.user_id,
        r.display_name,
        r.email,
        r.phone_number,
        r.role_access,
        r.account_status,
        r.verification_status,
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

  const renderTable = () => {
    if (!tbody) return;
    applyFilters();

    const totalPages = Math.max(1, Math.ceil(state.rows.length / state.pagination.entriesPerPage));
    if (state.pagination.currentPage > totalPages) state.pagination.currentPage = totalPages;
    if (state.pagination.currentPage < 1) state.pagination.currentPage = 1;

    const start = (state.pagination.currentPage - 1) * state.pagination.entriesPerPage;
    const pageRows = state.rows.slice(start, start + state.pagination.entriesPerPage);

    if (!pageRows.length) {
      tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted py-4">No users found.</td></tr>`;
      renderPagination();
      return;
    }

    tbody.innerHTML = pageRows.map((r) => {
      const verificationTone = String(r.verification_status || "") === "Verified" ? "ok" : "pending";
      const accountStatusKey = String(r.account_status || "").toLowerCase();
      const accountTone = (
        accountStatusKey.includes("active") && !accountStatusKey.includes("inactive")
      ) ? "ok" : (
        accountStatusKey.includes("inactive") ||
        accountStatusKey.includes("revoked") ||
        accountStatusKey.includes("suspend") ||
        accountStatusKey.includes("disabled")
      ) ? "denied" : "pending";
      return `
        <tr>
          <td>${safe(r.user_id)}</td>
          <td>${safe(r.display_name)}</td>
          <td>${safe(r.role_access)}</td>
          <td>${safe(r.email)}</td>
          <td>+63${safe(r.phone_number)}</td>
          <td>${statusPillHtml(r.account_status, accountTone)}</td>
          <td>${statusPillHtml(r.verification_status, verificationTone)}</td>
          <td>${safe(r.account_created)}</td>
          <td>${safe(r.last_login)}</td>
        </tr>
      `;
    }).join("");

    renderPagination();
  };

  const renderCountdown = () => {
    if (!countdownEl) return;
    countdownEl.textContent = state.auto.secondsLeft > 0 ? `Auto refresh in ${state.auto.secondsLeft}s` : "";
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

      const res = await fetch(`../PhpFiles/Admin-End/userMasterlist.php?${params.toString()}`, { headers: { Accept: "application/json" } });
      const data = await res.json().catch(() => null);
      if (!res.ok || !data?.success) throw new Error(data?.message || "Failed to load users.");

      state.rowsRaw = Array.isArray(data.data) ? data.data : [];
      updatePendingBadge();
      renderTable();
    } catch (err) {
      if (tbody) tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">${safe(err?.message || "Unable to load users.")}</td></tr>`;
    } finally {
      state.auto.inFlight = false;
      state.auto.secondsLeft = 15;
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
        document.querySelectorAll(".status-filter-btn").forEach((x) => x.classList.remove("active"));
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
