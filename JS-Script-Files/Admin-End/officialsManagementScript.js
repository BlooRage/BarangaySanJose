(() => {
  const el = (id) => document.getElementById(id);
  const opts = window.OFFICIALS_MGMT_OPTIONS || {};
  const permissionCatalog = Array.isArray(opts.permissionCatalog) ? opts.permissionCatalog : [];
  const apiUrl = String(opts.apiUrl || "../PhpFiles/Admin-End/officialsManagement.php");
  const managementMode = String(opts.managementMode || "official");
  const showLifecycleTabs = Boolean(opts.showLifecycleTabs ?? (managementMode !== "personnel"));
  const entitySingular = String(opts.entitySingular || "Official");
  const entityPluralLower = String(opts.entityPluralLower || "officials");
  const emptyCurrentMessage = String(opts.emptyCurrentMessage || `No current ${entityPluralLower} found.`);
  const emptyPastMessage = String(opts.emptyPastMessage || `No past ${entityPluralLower} found.`);
  const loadFailureMessage = String(opts.loadFailureMessage || `Failed to load ${entityPluralLower}.`);
  const loadUnavailableMessage = String(opts.loadUnavailableMessage || `Unable to load ${entityPluralLower}.`);

  const state = {
    rowsRaw: [],
    rows: [],
    search: "",
    lifecycle: "current",
    role: "ALL",
    permission: "ALL",
    department: "ALL",
    employmentStatus: "ALL",
    accountStatus: "ALL",
    profileApproval: "ALL",
    canManageActions: false,
    pagination: { currentPage: 1, entriesPerPage: 20 },
    auto: { interval: null, inFlight: false },
    accessModal: {
      row: null,
      permissionMap: {},
      lockedKeys: new Set(),
      search: "",
    },
  };

  const tbody = el("officialsMgmtTbody");
  const paginationEl = el("officialsMgmtPagination");
  const entriesInput = el("officialsMgmtEntriesInput");
  const refreshBtn = el("btnOfficialsMgmtRefresh");
  const AUTO_REFRESH_MS = 30000;
  const revokedBadge = el("revokedOfficialsBadge");
  const searchInput = el("officialsMgmtSearch");
  const lifecycleButtons = Array.from(document.querySelectorAll(".status-filter-btn[data-lifecycle-filter]"));
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

  const accessModalEl = el("modalOfficialsMgmtAccess");
  const accessOfficialIdInput = el("officialsMgmtAccessOfficialId");
  const accessSummaryEl = el("officialsMgmtAccessSummary");
  const accessRoleSelect = el("officialsMgmtAccessRole");
  const accessExpiryInput = el("officialsMgmtAccessExpiry");
  const accessModulesSummaryEl = el("officialsMgmtAccessModulesSummary");
  const accessProtectedNoticeEl = el("officialsMgmtAccessProtectedNotice");
  const accessPermissionSearch = el("officialsMgmtPermissionSearch");
  const accessPermissionGroups = el("officialsMgmtPermissionGroups");
  const accessSubmitBtn = el("btnOfficialsMgmtAccessSubmit");

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

  const statusPillClass = (value, type = "generic") => {
    const normalized = String(value ?? "").trim().toLowerCase();

    if (type === "permission") {
      return normalized === "revoked" ? "denied" : "approved";
    }

    if (type === "profileApproval") {
      if (normalized === "approved") return "approved";
      if (normalized === "rejected") return "denied";
      return "pending";
    }

    if (/active|verified|enabled|approved|completed/.test(normalized)) return "approved";
    if (/revoked|rejected|denied|inactive|disabled|suspended/.test(normalized)) return "denied";
    if (/pending|onboarding|review/.test(normalized)) return "pending";

    return "archived";
  };

  const statusPillHtml = (text, type) =>
    `<span class="status-pill ${statusPillClass(text, type)}">${escapeHtml(safe(text))}</span>`;

  const formatDate = (raw) => {
    const value = String(raw || "").trim();
    if (!value) return "No expiry";
    const parsed = new Date(`${value}T00:00:00`);
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleDateString("en-PH", { year: "numeric", month: "short", day: "numeric" });
  };

  const buildPermissionMetaMap = () => {
    const map = {};
    permissionCatalog.forEach((section) => {
      (section.items || []).forEach((item) => {
        if (Array.isArray(item.children) && item.children.length) {
          item.children.forEach((child) => {
            map[child.key] = {
              ...child,
              section: section.section || "",
              parentLabel: item.label || "",
              parentKey: item.key || "",
              adminOnly: Boolean(child.admin_only || item.admin_only),
            };
          });
          return;
        }
        map[item.key] = {
          ...item,
          section: section.section || "",
          parentLabel: "",
          parentKey: "",
          adminOnly: Boolean(item.admin_only),
        };
      });
    });
    return map;
  };

  const permissionMetaMap = buildPermissionMetaMap();

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
    const options = [`<option value="ALL">${escapeHtml(allLabel)}</option>`];
    values.forEach((value) => {
      options.push(`<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`);
    });
    selectEl.innerHTML = options.join("");
    selectEl.value = values.includes(currentValue) ? currentValue : "ALL";
  };

  const isPastOfficial = (row) => {
    const permissionState = String(row?.permission_state ?? "").trim().toLowerCase();
    const accountStatus = String(row?.account_status ?? "").trim().toLowerCase();
    const employmentStatus = String(row?.employment_status ?? "").trim().toLowerCase();

    return permissionState === "revoked"
      || /inactive|revoked|suspended|disabled/.test(accountStatus)
      || /term ended|resigned|removed|retired/.test(employmentStatus);
  };

  const syncQuickFilterButtons = () => {
    lifecycleButtons.forEach((btn) => {
      btn.classList.toggle("active", String(btn.dataset.lifecycleFilter || "current") === state.lifecycle);
    });
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
      if (showLifecycleTabs) {
        const isPast = isPastOfficial(row);
        if (state.lifecycle === "current" && isPast) return false;
        if (state.lifecycle === "past" && !isPast) return false;
      }
      if (state.role !== "ALL" && String(row.display_role || "") !== state.role) return false;
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
        row.display_role,
        row.position_access,
        row.department,
        row.employment_status,
        row.account_status,
        row.permission_state,
        row.profile_approval_state,
        row.email,
        row.phone_number,
        row.access_expires_on,
        row.module_summary,
        row.protected_label,
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

    if (!row.can_edit_access) {
      return '<span class="text-muted small">Protected Account</span>';
    }

    const id = escapeHtml(safe(row.official_id));
    const approvalState = String(row.profile_approval_state || "");
    const isRevoked = String(row.permission_state || "") === "Revoked";
    let items = "";

    items += `<li><button class="dropdown-item officials-action-btn" data-action="manage_access" data-official-id="${id}"><i class="fas fa-list-check me-2"></i>Manage Access</button></li>`;
    items += `<li><hr class="dropdown-divider"></li>`;

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
      const emptyMessage = showLifecycleTabs
        ? (state.lifecycle === "past" ? emptyPastMessage : emptyCurrentMessage)
        : emptyCurrentMessage;
      tbody.innerHTML = `<tr><td colspan="11" class="text-center text-muted py-4">${emptyMessage}</td></tr>`;
      renderPagination();
      return;
    }

    tbody.innerHTML = pageRows.map((row) => {
      const approvalState = String(row.profile_approval_state || "PendingApproval");
      return `
        <tr>
          <td>${escapeHtml(safe(row.official_id))}</td>
          <td>${escapeHtml(safe(row.user_id))}</td>
          <td>${escapeHtml(safe(row.full_name))}</td>
          <td>${escapeHtml(safe(row.display_role))}</td>
          <td>${escapeHtml(safe(row.position_access))}</td>
          <td>${escapeHtml(safe(row.department))}</td>
          <td>${escapeHtml(formatDate(row.access_expires_on))}</td>
          <td>${statusPillHtml(row.account_status, "accountStatus")}</td>
          <td><div class="officials-module-summary">${escapeHtml(safe(row.module_summary))}</div></td>
          <td>${statusPillHtml(approvalState, "profileApproval")}</td>
          <td>${actionButtonHtml(row)}</td>
        </tr>
      `;
    }).join("");

    renderPagination();
    wireActionButtons();

    document.querySelectorAll("#table-officialsMgmt .dropdown-toggle").forEach((btn) => {
      new bootstrap.Dropdown(btn, { popperConfig: { strategy: "fixed" } });
    });
  };

  const scheduleAutoRefresh = () => {
    if (state.auto.interval) clearTimeout(state.auto.interval);
    state.auto.interval = window.setTimeout(() => {
      if (state.auto.inFlight) {
        scheduleAutoRefresh();
        return;
      }
      scheduleAutoRefresh();
      load().catch(() => {});
    }, AUTO_REFRESH_MS);
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
    body.append("mode", managementMode);

    const res = await fetch(apiUrl, {
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
      const deptPositions = (positionsByDepartment[row.department] || []).filter((pos) => pos !== row.position_access);
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
          pathEl.textContent = selectedPos ? `${row.position_access} → ${selectedPos}` : "Select a position to see promotion path";
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

    const syncArea = () => {
      const selectedPos = newPositionSelect?.value || "";
      if (areaWrap) areaWrap.classList.toggle("d-none", !areaRequiredPositions.includes(selectedPos));
      if (areaSelect && !areaRequiredPositions.includes(selectedPos)) areaSelect.value = "";
    };

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
    if (deptSelect) deptSelect.onchange = () => populatePositions(deptSelect.value, "");

    if (deptSelect) deptSelect.value = row.department || "";
    populatePositions(row.department || "", row.position_access || "");
    if (areaSelect) areaSelect.value = row.area_number || "";

    const previewEl = el("officialsMgmtDepartmentPreview");
    if (previewEl) previewEl.textContent = `Changing department for: ${row.full_name}`;

    bootstrap.Modal.getOrCreateInstance(document.getElementById("modalOfficialsMgmtDepartment")).show();
  };

  const syncAccessPermissionConstraints = () => {
    const row = state.accessModal.row;
    if (!row) return;

    const displayRole = String(accessRoleSelect?.value || "Admin");
    if (displayRole !== "SuperAdmin") {
      Object.values(permissionMetaMap).forEach((meta) => {
        if (meta.adminOnly && !state.accessModal.lockedKeys.has(meta.key)) {
          delete state.accessModal.permissionMap[meta.key];
        }
      });
    }

    state.accessModal.lockedKeys.forEach((key) => {
      state.accessModal.permissionMap[key] = true;
    });
  };

  const getChildKeys = (item) =>
    Array.isArray(item.children) && item.children.length ? item.children.map((child) => String(child.key || "").trim()).filter(Boolean) : [];

  const sectionHasVisibleItems = (section, term) => {
    const normalizedTerm = term.toLowerCase();
    if (!normalizedTerm) return true;

    return (section.items || []).some((item) => {
      const itemLabel = String(item.label || "").toLowerCase();
      if (itemLabel.includes(normalizedTerm)) return true;
      const children = item.children || [];
      return children.some((child) => String(child.label || "").toLowerCase().includes(normalizedTerm));
    });
  };

  const renderAccessPermissionGroups = () => {
    if (!accessPermissionGroups) return;
    const row = state.accessModal.row;
    if (!row) {
      accessPermissionGroups.innerHTML = "";
      return;
    }

    syncAccessPermissionConstraints();
    const term = String(state.accessModal.search || "").trim().toLowerCase();

    const html = permissionCatalog
      .filter((section) => sectionHasVisibleItems(section, term))
      .map((section, sectionIndex) => {
        const sectionLeafKeys = [];

        const itemsHtml = (section.items || []).map((item, itemIndex) => {
          const children = item.children || [];
          if (children.length) {
            const childKeys = getChildKeys(item);
            childKeys.forEach((key) => sectionLeafKeys.push(key));

            const visibleChildren = children.filter((child) => {
              const childLabel = String(child.label || "").toLowerCase();
              return !term || childLabel.includes(term) || String(item.label || "").toLowerCase().includes(term);
            });
            if (!visibleChildren.length && !(String(item.label || "").toLowerCase().includes(term))) {
              return "";
            }

            const selectedChildren = childKeys.filter((key) => Boolean(state.accessModal.permissionMap[key]));
            const parentChecked = childKeys.length > 0 && selectedChildren.length === childKeys.length;
            const parentPartial = selectedChildren.length > 0 && selectedChildren.length < childKeys.length;

            const childHtml = visibleChildren.map((child, childIndex) => {
              const key = String(child.key || "").trim();
              const checked = Boolean(state.accessModal.permissionMap[key]);
              const isLocked = state.accessModal.lockedKeys.has(key);
              const isAdminOnly = Boolean(child.admin_only || item.admin_only);
              const disabled = isLocked || (!row.can_edit_access) || (isAdminOnly && String(accessRoleSelect?.value || "Admin") !== "SuperAdmin");
              return `
                <div class="officials-access-item is-child ${disabled ? "is-disabled" : ""}" data-item-text="${escapeHtml(`${item.label} ${child.label}`.toLowerCase())}">
                  <label>
                    <input type="checkbox"
                           class="officials-access-child"
                           data-key="${escapeHtml(key)}"
                           data-parent-key="${escapeHtml(String(item.key || ""))}"
                           data-admin-only="${isAdminOnly ? "1" : "0"}"
                           ${checked ? "checked" : ""}
                           ${disabled ? "disabled" : ""}>
                    <span>
                      <span class="officials-access-item-main">${escapeHtml(child.label || key)}</span>
                      <span class="officials-access-item-sub">${escapeHtml(section.section || "")}</span>
                    </span>
                  </label>
                </div>
              `;
            }).join("");

            return `
              <div class="officials-access-item ${!row.can_edit_access ? "is-disabled" : ""}">
                <label>
                  <input type="checkbox"
                         class="officials-access-parent"
                         data-parent-key="${escapeHtml(String(item.key || ""))}"
                         data-child-keys="${escapeHtml(childKeys.join(","))}"
                         ${parentChecked ? "checked" : ""}
                         ${parentPartial ? 'data-partial="1"' : ""}
                         ${!row.can_edit_access ? "disabled" : ""}>
                  <span>
                    <span class="officials-access-item-main">${escapeHtml(item.label || item.key)}</span>
                    <span class="officials-access-item-sub">Toggle all child modules in this group.</span>
                  </span>
                </label>
              </div>
              ${childHtml}
            `;
          }

          const key = String(item.key || "").trim();
          const meta = permissionMetaMap[key] || {};
          const isLocked = state.accessModal.lockedKeys.has(key);
          const isAdminOnly = Boolean(meta.adminOnly || item.admin_only);
          const disabled = isLocked || (!row.can_edit_access) || (isAdminOnly && String(accessRoleSelect?.value || "Admin") !== "SuperAdmin");
          const checked = Boolean(state.accessModal.permissionMap[key]);
          const itemText = `${item.label || ""} ${section.section || ""}`.toLowerCase();
          if (term && !itemText.includes(term)) {
            return "";
          }
          sectionLeafKeys.push(key);
          return `
            <div class="officials-access-item ${disabled ? "is-disabled" : ""}" data-item-text="${escapeHtml(itemText)}">
              <label>
                <input type="checkbox"
                       class="officials-access-child"
                       data-key="${escapeHtml(key)}"
                       data-admin-only="${isAdminOnly ? "1" : "0"}"
                       ${checked ? "checked" : ""}
                       ${disabled ? "disabled" : ""}>
                <span>
                  <span class="officials-access-item-main">${escapeHtml(item.label || key)}</span>
                  <span class="officials-access-item-sub">${escapeHtml(section.section || "")}</span>
                </span>
              </label>
            </div>
          `;
        }).join("");

        if (!itemsHtml.trim()) {
          return "";
        }

        return `
          <div class="officials-access-group" data-section-index="${sectionIndex}">
            <div class="officials-access-group-head">
              <div class="officials-access-group-title">${escapeHtml(section.section || "Modules")}</div>
              <div class="officials-access-group-actions">
                <button type="button" class="btn btn-sm btn-outline-secondary officials-access-section-toggle" data-mode="on" data-section-keys="${escapeHtml(sectionLeafKeys.join(","))}" ${!row.can_edit_access ? "disabled" : ""}>Check all</button>
                <button type="button" class="btn btn-sm btn-outline-secondary officials-access-section-toggle" data-mode="off" data-section-keys="${escapeHtml(sectionLeafKeys.join(","))}" ${!row.can_edit_access ? "disabled" : ""}>Clear</button>
              </div>
            </div>
            <div class="officials-access-items">${itemsHtml}</div>
          </div>
        `;
      })
      .join("");

    accessPermissionGroups.innerHTML = html || '<div class="text-muted small px-2 py-3">No module labels match that search.</div>';

    accessPermissionGroups.querySelectorAll(".officials-access-parent[data-partial='1']").forEach((input) => {
      input.indeterminate = true;
    });

    bindAccessPermissionEvents();
  };

  const bindAccessPermissionEvents = () => {
    if (!accessPermissionGroups) return;

    accessPermissionGroups.querySelectorAll(".officials-access-child").forEach((checkbox) => {
      checkbox.addEventListener("change", () => {
        const key = String(checkbox.getAttribute("data-key") || "").trim();
        if (!key) return;
        if (checkbox.checked) {
          state.accessModal.permissionMap[key] = true;
        } else {
          delete state.accessModal.permissionMap[key];
        }
        renderAccessPermissionGroups();
      });
    });

    accessPermissionGroups.querySelectorAll(".officials-access-parent").forEach((checkbox) => {
      checkbox.addEventListener("change", () => {
        const childKeys = String(checkbox.getAttribute("data-child-keys") || "")
          .split(",")
          .map((value) => value.trim())
          .filter(Boolean);

        childKeys.forEach((key) => {
          if (state.accessModal.lockedKeys.has(key)) {
            state.accessModal.permissionMap[key] = true;
            return;
          }
          if (checkbox.checked) {
            state.accessModal.permissionMap[key] = true;
          } else {
            delete state.accessModal.permissionMap[key];
          }
        });

        renderAccessPermissionGroups();
      });
    });

    accessPermissionGroups.querySelectorAll(".officials-access-section-toggle").forEach((button) => {
      button.addEventListener("click", () => {
        const mode = String(button.getAttribute("data-mode") || "on");
        const keys = String(button.getAttribute("data-section-keys") || "")
          .split(",")
          .map((value) => value.trim())
          .filter(Boolean);

        keys.forEach((key) => {
          if (state.accessModal.lockedKeys.has(key)) {
            state.accessModal.permissionMap[key] = true;
            return;
          }

          const meta = permissionMetaMap[key] || {};
          if (mode === "on") {
            if (meta.adminOnly && String(accessRoleSelect?.value || "Admin") !== "SuperAdmin") {
              return;
            }
            state.accessModal.permissionMap[key] = true;
          } else {
            delete state.accessModal.permissionMap[key];
          }
        });

        renderAccessPermissionGroups();
      });
    });
  };

  const openAccessModal = (officialId) => {
    const row = state.rowsRaw.find((entry) => String(entry.official_id) === String(officialId));
    if (!row) return;

    state.accessModal.row = row;
    state.accessModal.permissionMap = {};
    (Array.isArray(row.permission_keys) ? row.permission_keys : []).forEach((key) => {
      state.accessModal.permissionMap[String(key)] = true;
    });
    state.accessModal.lockedKeys = new Set(Array.isArray(row.locked_permission_keys) ? row.locked_permission_keys : []);
    state.accessModal.search = "";

    if (accessOfficialIdInput) accessOfficialIdInput.value = row.official_id || "";
    if (accessSummaryEl) accessSummaryEl.textContent = `${row.full_name} — ${row.position_access} (${row.department})`;
    if (accessRoleSelect) {
      accessRoleSelect.value = row.display_role || "Admin";
      accessRoleSelect.disabled = row.protected_code !== "";
    }
    if (accessExpiryInput) {
      accessExpiryInput.value = String(row.access_expires_on || "").trim();
      accessExpiryInput.disabled = !row.can_edit_access;
    }
    if (accessModulesSummaryEl) accessModulesSummaryEl.textContent = `${row.module_count || 0} enabled — ${row.module_summary || "No modules"}`;
    if (accessPermissionSearch) accessPermissionSearch.value = "";

    if (accessProtectedNoticeEl) {
      const notices = [];
      if (row.protected_label) {
        notices.push(`${row.protected_label} account`);
      }
      if (!row.can_edit_access) {
        notices.push("This account is locked from access changes by the current SuperAdmin.");
      } else if (row.protected_code === "IT_SUPERADMIN") {
        notices.push("Protected core admin modules stay enabled for this IT SuperAdmin account.");
      } else if (row.protected_code === "BARANGAY_CAPTAIN") {
        notices.push("The Barangay Captain must remain a SuperAdmin while assigned to that position.");
      }

      if (notices.length) {
        accessProtectedNoticeEl.classList.remove("d-none");
        accessProtectedNoticeEl.textContent = notices.join(" ");
      } else {
        accessProtectedNoticeEl.classList.add("d-none");
        accessProtectedNoticeEl.textContent = "";
      }
    }

    if (accessSubmitBtn) accessSubmitBtn.disabled = !row.can_edit_access;

    renderAccessPermissionGroups();
    bootstrap.Modal.getOrCreateInstance(accessModalEl).show();
  };

  const wireActionButtons = () => {
    document.querySelectorAll(".officials-action-btn").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const action = String(btn.getAttribute("data-action") || "");
        const officialId = String(btn.getAttribute("data-official-id") || "");
        if (!action || !officialId) return;

        if (action === "manage_access") {
          openAccessModal(officialId);
          return;
        }
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

  const saveAccessProfile = async () => {
    const row = state.accessModal.row;
    if (!row) return;

    const officialId = String(accessOfficialIdInput?.value || "");
    const displayRole = String(accessRoleSelect?.value || "Admin");
    const accessExpiresOn = String(accessExpiryInput?.value || "").trim();
    if (!officialId) {
      window.alert(`Missing ${entitySingular.toLowerCase()} record.`);
      return;
    }

    syncAccessPermissionConstraints();
    const permissionKeys = Object.keys(state.accessModal.permissionMap);

    const body = new FormData();
    body.append("action", "update_access_profile");
    body.append("official_id", officialId);
    body.append("mode", managementMode);
    body.append("display_role", displayRole);
    body.append("access_expires_on", accessExpiresOn);
    permissionKeys.forEach((key) => body.append("permission_keys[]", key));

    const res = await fetch(apiUrl, {
      method: "POST",
      body,
      headers: { Accept: "application/json" },
    });
    const data = await res.json().catch(() => null);
    if (!res.ok || !data?.success) {
      throw new Error(data?.message || "Unable to update access profile.");
    }
    return data;
  };

  const load = async () => {
    if (state.auto.inFlight) return;
    state.auto.inFlight = true;
    setRefreshState(true);

    try {
      const params = new URLSearchParams();
      params.set("fetch_officials_management", "1");
      params.set("mode", managementMode);
      params.set("limit", "1000");

      const res = await fetch(`${apiUrl}?${params.toString()}`, {
        headers: { Accept: "application/json" },
      });
      const data = await res.json().catch(() => null);
      if (!res.ok || !data?.success) {
        throw new Error(data?.message || loadFailureMessage);
      }

      state.rowsRaw = Array.isArray(data.data) ? data.data : [];
      state.canManageActions = Boolean(data.can_manage_actions);
      updateRevokedBadge();
      renderFilterOptions();
      renderTable();
    } catch (error) {
      if (tbody) {
        tbody.innerHTML = `<tr><td colspan="11" class="text-center text-danger py-4">${escapeHtml(safe(error?.message || loadUnavailableMessage))}</td></tr>`;
      }
    } finally {
      state.auto.inFlight = false;
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

    if (showLifecycleTabs) {
      lifecycleButtons.forEach((btn) => {
        btn.addEventListener("click", () => {
          state.lifecycle = String(btn.dataset.lifecycleFilter || "current");
          state.pagination.currentPage = 1;
          syncQuickFilterButtons();
          renderTable();
        });
      });
    }

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
      scheduleAutoRefresh();
      load().catch(() => {});
    });

    btnFilterApply?.addEventListener("click", applyModalFilters);
    btnFilterReset?.addEventListener("click", resetModalFilters);

    accessRoleSelect?.addEventListener("change", () => {
      renderAccessPermissionGroups();
    });

    accessPermissionSearch?.addEventListener("input", () => {
      state.accessModal.search = accessPermissionSearch.value || "";
      renderAccessPermissionGroups();
    });

    accessSubmitBtn?.addEventListener("click", async () => {
      try {
        accessSubmitBtn.disabled = true;
        await saveAccessProfile();
        bootstrap.Modal.getInstance(accessModalEl)?.hide();
        await load();
      } catch (error) {
        window.alert(error?.message || "Unable to update access profile.");
      } finally {
        accessSubmitBtn.disabled = false;
      }
    });

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
          body.append("mode", managementMode);
          body.append("new_position", newPosition);
          body.append("area_number", areaNumber);

          const res = await fetch(apiUrl, {
            method: "POST",
            body,
            headers: { Accept: "application/json" },
          });
          const data = await res.json().catch(() => null);
          if (!res.ok || !data?.success) throw new Error(data?.message || "Promotion failed.");

          bootstrap.Modal.getInstance(document.getElementById("modalOfficialsMgmtPromote"))?.hide();
          await load();
        } catch (error) {
          window.alert(error?.message || `Unable to update ${entitySingular.toLowerCase()} position.`);
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
          body.append("mode", managementMode);
          body.append("new_department", newDepartment);
          body.append("new_position", newPosition);
          body.append("area_number", areaNumber);

          const res = await fetch(apiUrl, {
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

    scheduleAutoRefresh();
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
