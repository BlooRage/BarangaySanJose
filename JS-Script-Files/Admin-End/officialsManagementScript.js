(() => {
  const el = (id) => document.getElementById(id);

  const state = {
    rowsRaw: [],
    rows: [],
    search: "",
    role: "ALL",
    permission: "ALL",
    department: "ALL",
    employmentStatus: "ALL",
    accountStatus: "ALL",
    profileApproval: "ALL",
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
  const searchInput = el("officialsMgmtSearch");
  const roleButtons = Array.from(document.querySelectorAll(".status-filter-btn[data-filter]"));
  const permissionButtons = Array.from(document.querySelectorAll(".status-filter-btn[data-permission-filter]"));
  const roleFilterSelect = el("officialsMgmtRoleFilter");
  const permissionFilterSelect = el("officialsMgmtPermissionFilter");
  const departmentFilterSelect = el("officialsMgmtDepartmentFilter");
  const employmentFilterSelect = el("officialsMgmtEmploymentFilter");
  const accountFilterSelect = el("officialsMgmtAccountFilter");
  const approvalFilterSelect = el("officialsMgmtApprovalFilter");
  const btnFilterApply = el("btnOfficialsMgmtFilterApply");
  const btnFilterReset = el("btnOfficialsMgmtFilterReset");

  const safe = (value) => {
    const normalized = String(value ?? "").trim();
    return normalized !== "" ? normalized : "N/A";
  };

  const escapeHtml = (value) =>
    String(value ?? "").replace(/[&<>"']/g, (match) => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;",
    }[match]));

  const badgeHtml = (text, tone) => {
    const cls = tone === "ok" ? "bg-success" : tone === "danger" ? "bg-danger" : "bg-secondary";
    return `<span class="badge ${cls}">${escapeHtml(safe(text))}</span>`;
  };

  const getUniqueValues = (key) => {
    const values = new Set();
    state.rowsRaw.forEach((row) => {
      const value = String(row?.[key] ?? "").trim();
      if (value !== "") values.add(value);
    });
    return Array.from(values).sort((a, b) => a.localeCompare(b));
  };

  const populateSelect = (selectEl, values, allLabel, currentValue) => {
    if (!selectEl) return;
    const options = ['<option value="ALL">' + escapeHtml(allLabel) + "</option>"];
    values.forEach((value) => {
      options.push(`<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`);
    });
    selectEl.innerHTML = options.join("");
    selectEl.value = values.includes(currentValue) ? currentValue : "ALL";
  };

  const syncQuickFilterButtons = () => {
    roleButtons.forEach((btn) => {
      btn.classList.toggle("active", String(btn.dataset.filter || "ALL") === state.role);
    });
    permissionButtons.forEach((btn) => {
      btn.classList.toggle("active", String(btn.dataset.permissionFilter || "ALL") === state.permission);
    });
  };

  const syncFilterControls = () => {
    if (roleFilterSelect) roleFilterSelect.value = state.role;
    if (permissionFilterSelect) permissionFilterSelect.value = state.permission;
    if (departmentFilterSelect) departmentFilterSelect.value = state.department;
    if (employmentFilterSelect) employmentFilterSelect.value = state.employmentStatus;
    if (accountFilterSelect) accountFilterSelect.value = state.accountStatus;
    if (approvalFilterSelect) approvalFilterSelect.value = state.profileApproval;
    syncQuickFilterButtons();
  };

  const renderFilterOptions = () => {
    const departments = getUniqueValues("department");
    const employmentStatuses = getUniqueValues("employment_status");
    const accountStatuses = getUniqueValues("account_status");

    if (!departments.includes(state.department)) state.department = "ALL";
    if (!employmentStatuses.includes(state.employmentStatus)) state.employmentStatus = "ALL";
    if (!accountStatuses.includes(state.accountStatus)) state.accountStatus = "ALL";

    populateSelect(departmentFilterSelect, departments, "All departments", state.department);
    populateSelect(employmentFilterSelect, employmentStatuses, "All employment statuses", state.employmentStatus);
    populateSelect(accountFilterSelect, accountStatuses, "All account statuses", state.accountStatus);
    syncFilterControls();
  };

  const updateRevokedBadge = () => {
    if (!revokedBadge) return;
    const count = state.rowsRaw.filter((row) => String(row.permission_state || "") === "Revoked").length;
    revokedBadge.textContent = String(count);
    revokedBadge.classList.toggle("d-none", count <= 0);
  };

  const applyFilters = () => {
    const q = state.search.toLowerCase();
    state.rows = state.rowsRaw.filter((row) => {
      if (state.role !== "ALL" && String(row.role_access || "") !== state.role) return false;
      if (state.permission !== "ALL" && String(row.permission_state || "") !== state.permission) return false;
      if (state.department !== "ALL" && String(row.department || "") !== state.department) return false;
      if (state.employmentStatus !== "ALL" && String(row.employment_status || "") !== state.employmentStatus) return false;
      if (state.accountStatus !== "ALL" && String(row.account_status || "") !== state.accountStatus) return false;
      if (state.profileApproval !== "ALL" && String(row.profile_approval_state || "") !== state.profileApproval) return false;
      if (!q) return true;

      const searchBag = [
        row.official_id,
        row.user_id,
        row.full_name,
        row.role_access,
        row.position_access,
        row.department,
        row.employment_status,
        row.date_hired,
        row.account_status,
        row.permission_state,
        row.profile_approval_state,
        row.email,
        row.phone_number,
      ].join(" ").toLowerCase();

      return searchBag.includes(q);
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

  const actionButtonHtml = (row) => {
    if (!state.canManageActions) {
      return '<span class="text-muted small">SuperAdmin only</span>';
    }

    const id = escapeHtml(safe(row.official_id));
    const approvalState = String(row.profile_approval_state || "");
    const isRevoked = String(row.permission_state || "") === "Revoked";
    let items = "";

    if (approvalState === "PendingApproval") {
      items += `<li><button class="dropdown-item text-success officials-action-btn" data-action="approve_profile" data-official-id="${id}"><i class="fas fa-check me-2"></i>Approve Profile</button></li>`;
      items += `<li><button class="dropdown-item text-danger officials-action-btn" data-action="reject_profile" data-official-id="${id}"><i class="fas fa-times me-2"></i>Reject Profile</button></li>`;
      items += `<li><hr class="dropdown-divider"></li>`;
    }

    if (isRevoked) {
      items += `<li><button class="dropdown-item officials-action-btn" data-action="restore_permission" data-official-id="${id}"><i class="fas fa-unlock me-2"></i>Restore Access</button></li>`;
    } else {
      items += `<li><button class="dropdown-item text-danger officials-action-btn" data-action="revoke_permission" data-official-id="${id}"><i class="fas fa-ban me-2"></i>Revoke Access</button></li>`;
    }

    items += `<li><hr class="dropdown-divider"></li>`;
    items += `<li><button class="dropdown-item officials-action-btn" data-action="promote" data-official-id="${id}"><i class="fas fa-arrow-up me-2"></i>Promote</button></li>`;
    items += `<li><button class="dropdown-item officials-action-btn" data-action="change_department" data-official-id="${id}"><i class="fas fa-building me-2"></i>Change Department</button></li>`;

    return `
      <div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
          Actions
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          ${items}
        </ul>
      </div>
    `;
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
      tbody.innerHTML = '<tr><td colspan="12" class="text-center text-muted py-4">No officials found.</td></tr>';
      renderPagination();
      return;
    }

    tbody.innerHTML = pageRows.map((row) => {
      const permissionTone = String(row.permission_state || "") === "Revoked" ? "danger" : "ok";
      const approvalState = String(row.profile_approval_state || "PendingApproval");
      const approvalTone = approvalState === "Approved" ? "ok" : (approvalState === "Rejected" ? "danger" : "secondary");

      return `
        <tr>
          <td>${escapeHtml(safe(row.official_id))}</td>
          <td>${escapeHtml(safe(row.user_id))}</td>
          <td>${escapeHtml(safe(row.full_name))}</td>
          <td>${escapeHtml(safe(row.role_access))}</td>
          <td>${escapeHtml(safe(row.position_access))}</td>
          <td>${escapeHtml(safe(row.department))}</td>
          <td>${escapeHtml(safe(row.employment_status))}</td>
          <td>${escapeHtml(safe(row.date_hired))}</td>
          <td>${escapeHtml(safe(row.account_status))}</td>
          <td>${badgeHtml(row.permission_state, permissionTone)}</td>
          <td>${badgeHtml(approvalState, approvalTone)}</td>
          <td>${actionButtonHtml(row)}</td>
        </tr>
      `;
    }).join("");

    renderPagination();
    wireActionButtons();

    // Re-initialize dropdowns with fixed strategy so they escape overflow:hidden / overflow-x:auto containers
    document.querySelectorAll("#table-officialsMgmt .dropdown-toggle").forEach((btn) => {
      new bootstrap.Dropdown(btn, { popperConfig: { strategy: "fixed" } });
    });
  };

  const renderCountdown = () => {
    if (!countdownEl) return;
    countdownEl.textContent = state.auto.secondsLeft > 0 ? `Auto refresh in ${state.auto.secondsLeft}s` : "";
  };

  const setRefreshState = (isLoading) => {
    if (!refreshBtn) return;
    refreshBtn.classList.toggle("is-loading", !!isLoading);
    refreshBtn.disabled = !!isLoading;
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

  const openPromoteModal = (officialId) => {
    const row = state.rowsRaw.find((r) => String(r.official_id) === String(officialId));
    if (!row) return;

    const opts = window.OFFICIALS_MGMT_OPTIONS || {};
    const positionsByDepartment = opts.positionsByDepartment || {};
    const areaRequiredPositions = opts.areaRequiredPositions || [];

    const idInput = el("officialsMgmtPromoteOfficialId");
    if (idInput) idInput.value = officialId;

    const summaryEl = el("officialsMgmtPromoteSummary");
    if (summaryEl) summaryEl.textContent = `${row.full_name} — ${row.position_access} (${row.department})`;

    const pathEl = el("officialsMgmtPromotePath");
    if (pathEl) pathEl.textContent = "Select a position to see promotion path";

    const positionSelect = el("officialsMgmtPromotePosition");
    const areaWrap = el("officialsMgmtPromoteAreaWrap");
    const areaSelect = el("officialsMgmtPromoteArea");

    if (positionSelect) {
      // Only show positions within the same department, excluding the current one
      const deptPositions = (positionsByDepartment[row.department] || [])
        .filter((pos) => pos !== row.position_access);

      positionSelect.innerHTML = '<option value="">Select new position</option>';
      deptPositions.forEach((pos) => {
        const opt = document.createElement("option");
        opt.value = pos;
        opt.textContent = pos;
        positionSelect.appendChild(opt);
      });

      positionSelect.onchange = () => {
        const selectedPos = positionSelect.value;
        if (pathEl) {
          pathEl.textContent = selectedPos
            ? `${row.position_access} → ${selectedPos}`
            : "Select a position to see promotion path";
        }
        if (areaWrap) areaWrap.classList.toggle("d-none", !areaRequiredPositions.includes(selectedPos));
        if (areaSelect && !areaRequiredPositions.includes(selectedPos)) areaSelect.value = "";
      };

      positionSelect.value = "";
      if (areaWrap) areaWrap.classList.add("d-none");
      if (areaSelect) areaSelect.value = "";
    }

    bootstrap.Modal.getOrCreateInstance(document.getElementById("modalOfficialsMgmtPromote")).show();
  };

  const openDepartmentModal = (officialId) => {
    const row = state.rowsRaw.find((r) => String(r.official_id) === String(officialId));
    if (!row) return;

    const opts = window.OFFICIALS_MGMT_OPTIONS || {};
    const positionsByDepartment = opts.positionsByDepartment || {};
    const areaRequiredPositions = opts.areaRequiredPositions || [];

    const idInput = el("officialsMgmtDepartmentOfficialId");
    if (idInput) idInput.value = officialId;

    const summaryEl = el("officialsMgmtDepartmentSummary");
    if (summaryEl) summaryEl.textContent = row.full_name;

    const positionEl = el("officialsMgmtDepartmentPosition");
    if (positionEl) positionEl.textContent = `${row.position_access} (${row.department})`;

    const deptSelect = el("officialsMgmtDepartmentSelect");
    const newPositionSelect = el("officialsMgmtDepartmentNewPosition");
    const areaWrap = el("officialsMgmtDepartmentAreaWrap");
    const areaSelect = el("officialsMgmtDepartmentArea");

    // Show/hide area based on selected position
    const syncArea = () => {
      const selectedPos = newPositionSelect?.value || "";
      if (areaWrap) areaWrap.classList.toggle("d-none", !areaRequiredPositions.includes(selectedPos));
      if (areaSelect && !areaRequiredPositions.includes(selectedPos)) areaSelect.value = "";
    };

    // Rebuild position options for a given department, optionally pre-selecting one
    const populatePositions = (department, preselectPosition) => {
      if (!newPositionSelect) return;
      const positions = positionsByDepartment[department] || [];
      newPositionSelect.innerHTML = '<option value="">Select position</option>';
      positions.forEach((pos) => {
        const opt = document.createElement("option");
        opt.value = pos;
        opt.textContent = pos;
        newPositionSelect.appendChild(opt);
      });
      newPositionSelect.value = positions.includes(preselectPosition) ? preselectPosition : "";
      syncArea();
    };

    if (newPositionSelect) newPositionSelect.onchange = syncArea;

    // When department changes, refresh positions and clear selection
    if (deptSelect) deptSelect.onchange = () => populatePositions(deptSelect.value, "");

    // Set initial state
    if (deptSelect) deptSelect.value = row.department || "";
    populatePositions(row.department || "", row.position_access || "");

    // Pre-fill area after positions are set
    if (areaSelect) areaSelect.value = row.area_number || "";

    const previewEl = el("officialsMgmtDepartmentPreview");
    if (previewEl) previewEl.textContent = `Changing department for: ${row.full_name}`;

    bootstrap.Modal.getOrCreateInstance(document.getElementById("modalOfficialsMgmtDepartment")).show();
  };

  const wireActionButtons = () => {
    document.querySelectorAll(".officials-action-btn").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const action = String(btn.getAttribute("data-action") || "");
        const officialId = String(btn.getAttribute("data-official-id") || "");
        if (!action || !officialId) return;

        if (action === "promote") {
          openPromoteModal(officialId);
          return;
        }
        if (action === "change_department") {
          openDepartmentModal(officialId);
          return;
        }

        let label = "update";
        if (action === "revoke_permission") label = "revoke";
        else if (action === "restore_permission") label = "restore";
        else if (action === "approve_profile") label = "approve";
        else if (action === "reject_profile") label = "reject";

        const target = action === "approve_profile" || action === "reject_profile"
          ? "this profile approval"
          : "this account permission";

        if (!window.confirm(`Are you sure you want to ${label} ${target}?`)) return;

        try {
          btn.disabled = true;
          await postAction(action, officialId);
          await load();
        } catch (error) {
          window.alert(error?.message || "Unable to update permission state.");
        } finally {
          btn.disabled = false;
        }
      });
    });
  };

  const load = async () => {
    if (state.auto.inFlight) return;
    state.auto.inFlight = true;
    setRefreshState(true);

    try {
      const params = new URLSearchParams();
      params.set("fetch_officials_management", "1");
      params.set("limit", "1000");

      const res = await fetch(`../PhpFiles/Admin-End/officialsManagement.php?${params.toString()}`, {
        headers: { Accept: "application/json" },
      });
      const data = await res.json().catch(() => null);
      if (!res.ok || !data?.success) {
        throw new Error(data?.message || "Failed to load officials.");
      }

      state.rowsRaw = Array.isArray(data.data) ? data.data : [];
      state.canManageActions = Boolean(data.can_manage_actions);
      updateRevokedBadge();
      renderFilterOptions();
      renderTable();
    } catch (error) {
      if (tbody) {
        tbody.innerHTML = `<tr><td colspan="12" class="text-center text-danger py-4">${escapeHtml(safe(error?.message || "Unable to load officials."))}</td></tr>`;
      }
    } finally {
      state.auto.inFlight = false;
      state.auto.secondsLeft = 60;
      renderCountdown();
      setRefreshState(false);
    }
  };

  const applyModalFilters = () => {
    state.role = roleFilterSelect?.value || "ALL";
    state.permission = permissionFilterSelect?.value || "ALL";
    state.department = departmentFilterSelect?.value || "ALL";
    state.employmentStatus = employmentFilterSelect?.value || "ALL";
    state.accountStatus = accountFilterSelect?.value || "ALL";
    state.profileApproval = approvalFilterSelect?.value || "ALL";
    state.pagination.currentPage = 1;
    syncQuickFilterButtons();
    renderTable();
  };

  const resetModalFilters = () => {
    state.role = "ALL";
    state.permission = "ALL";
    state.department = "ALL";
    state.employmentStatus = "ALL";
    state.accountStatus = "ALL";
    state.profileApproval = "ALL";
    state.pagination.currentPage = 1;
    syncFilterControls();
    renderTable();
  };

  const wire = () => {
    roleButtons.forEach((btn) => {
      btn.addEventListener("click", () => {
        state.role = String(btn.dataset.filter || "ALL");
        state.pagination.currentPage = 1;
        syncFilterControls();
        renderTable();
      });
    });

    permissionButtons.forEach((btn) => {
      btn.addEventListener("click", () => {
        state.permission = String(btn.dataset.permissionFilter || "ALL");
        state.pagination.currentPage = 1;
        syncFilterControls();
        renderTable();
      });
    });

    if (searchInput) {
      searchInput.addEventListener("input", () => {
        state.search = searchInput.value || "";
        state.pagination.currentPage = 1;
        renderTable();
      });
    }

    if (entriesInput) {
      entriesInput.addEventListener("change", () => {
        const nextValue = Math.max(1, Number.parseInt(entriesInput.value || "20", 10) || 20);
        state.pagination.entriesPerPage = nextValue;
        entriesInput.value = String(nextValue);
        state.pagination.currentPage = 1;
        renderTable();
      });
    }

    refreshBtn?.addEventListener("click", () => {
      load().catch(() => {});
    });

    btnFilterApply?.addEventListener("click", applyModalFilters);
    btnFilterReset?.addEventListener("click", resetModalFilters);

    const promoteSubmitBtn = el("btnOfficialsMgmtPromoteSubmit");
    if (promoteSubmitBtn) {
      promoteSubmitBtn.addEventListener("click", async () => {
        const officialId = String(el("officialsMgmtPromoteOfficialId")?.value || "");
        const newPosition = String(el("officialsMgmtPromotePosition")?.value || "");

        if (!officialId || !newPosition) {
          window.alert("Please select a new position.");
          return;
        }

        const areaWrap = el("officialsMgmtPromoteAreaWrap");
        const needsArea = !areaWrap?.classList.contains("d-none");
        const areaNumber = needsArea ? String(el("officialsMgmtPromoteArea")?.value || "") : "";

        if (needsArea && !areaNumber) {
          window.alert("Please select an area for this position.");
          return;
        }

        if (!window.confirm(`Confirm promotion to: ${newPosition}?`)) return;

        try {
          promoteSubmitBtn.disabled = true;
          const body = new FormData();
          body.append("action", "promote");
          body.append("official_id", officialId);
          body.append("new_position", newPosition);
          body.append("area_number", areaNumber);

          const res = await fetch("../PhpFiles/Admin-End/officialsManagement.php", {
            method: "POST",
            body,
            headers: { Accept: "application/json" },
          });
          const data = await res.json().catch(() => null);
          if (!res.ok || !data?.success) throw new Error(data?.message || "Promotion failed.");

          bootstrap.Modal.getInstance(document.getElementById("modalOfficialsMgmtPromote"))?.hide();
          await load();
        } catch (error) {
          window.alert(error?.message || "Unable to promote official.");
        } finally {
          promoteSubmitBtn.disabled = false;
        }
      });
    }

    const deptSubmitBtn = el("btnOfficialsMgmtDepartmentSubmit");
    if (deptSubmitBtn) {
      deptSubmitBtn.addEventListener("click", async () => {
        const officialId = String(el("officialsMgmtDepartmentOfficialId")?.value || "");
        const newDepartment = String(el("officialsMgmtDepartmentSelect")?.value || "");
        const newPosition = String(el("officialsMgmtDepartmentNewPosition")?.value || "");

        if (!officialId || !newDepartment) {
          window.alert("Please select a department.");
          return;
        }

        if (!newPosition) {
          window.alert("Please select a position.");
          return;
        }

        const areaWrap = el("officialsMgmtDepartmentAreaWrap");
        const needsArea = !areaWrap?.classList.contains("d-none");
        const areaNumber = needsArea ? String(el("officialsMgmtDepartmentArea")?.value || "") : "";

        if (needsArea && !areaNumber) {
          window.alert("Please select an area for this position.");
          return;
        }

        if (!window.confirm(`Change department to: ${newDepartment} — ${newPosition}?`)) return;

        try {
          deptSubmitBtn.disabled = true;
          const body = new FormData();
          body.append("action", "change_department");
          body.append("official_id", officialId);
          body.append("new_department", newDepartment);
          body.append("new_position", newPosition);
          body.append("area_number", areaNumber);

          const res = await fetch("../PhpFiles/Admin-End/officialsManagement.php", {
            method: "POST",
            body,
            headers: { Accept: "application/json" },
          });
          const data = await res.json().catch(() => null);
          if (!res.ok || !data?.success) throw new Error(data?.message || "Failed to change department.");

          bootstrap.Modal.getInstance(document.getElementById("modalOfficialsMgmtDepartment"))?.hide();
          await load();
        } catch (error) {
          window.alert(error?.message || "Unable to change department.");
        } finally {
          deptSubmitBtn.disabled = false;
        }
      });
    }

    renderCountdown();

    if (state.auto.interval) clearInterval(state.auto.interval);
    state.auto.interval = window.setInterval(() => {
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
    syncFilterControls();
    wire();
    load().catch(() => {});
  });
})();
