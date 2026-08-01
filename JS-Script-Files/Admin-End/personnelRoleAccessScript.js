(function () {
  const opts = window.PERSONNEL_ROLE_ACCESS_OPTIONS || {};
  const apiUrl = String(opts.apiUrl || "../PhpFiles/Admin-End/personnelRoleAccess.php");
  const permissionCatalog = Array.isArray(opts.permissionCatalog) ? opts.permissionCatalog : [];
  const defaultPermissionKeysFromOpts = Array.isArray(opts.defaultPermissionKeys) ? opts.defaultPermissionKeys.map(String) : [];

  const state = {
    rowsRaw: [],
    rows: [],
    summary: {
      profiles_total: 0,
      custom_profiles: 0,
      default_profiles: 0,
      assigned_personnel_total: 0,
    },
    editorOptions: {
      departments: [],
      positionsByDepartment: {},
    },
    search: "",
    department: "ALL",
    position: "ALL",
    modal: {
      mode: "edit",
      row: null,
      permissionMap: {},
      search: "",
    },
  };

  const el = (id) => document.getElementById(id);
  const tbody = el("personnelRoleAccessTbody");
  const searchInput = el("personnelRoleAccessSearch");
  const departmentFilter = el("personnelRoleAccessDepartmentFilter");
  const positionFilter = el("personnelRoleAccessPositionFilter");
  const refreshBtn = el("btnPersonnelRoleAccessRefresh");
  const createBtn = el("btnPersonnelRoleAccessCreate");
  const metaEl = el("personnelRoleAccessMeta");

  const modalEl = el("modalPersonnelRoleAccess");
  const modalTitleEl = el("personnelRoleAccessModalTitle");
  const modalNoticeEl = el("personnelRoleAccessModalNotice");
  const saveBtn = el("btnPersonnelRoleAccessSave");
  const resetBtn = el("btnPersonnelRoleAccessReset");
  const permissionSearchInput = el("personnelRoleAccessPermissionSearch");
  const permissionGroupsEl = el("personnelRoleAccessGroups");

  const createModalEl = el("modalPersonnelRoleProfileCreate");
  const createDepartmentSelect = el("personnelRoleAccessCreateDepartment");
  const createPositionSelect = el("personnelRoleAccessCreatePosition");
  const createContinueBtn = el("btnPersonnelRoleAccessCreateContinue");
  const createHintEl = el("personnelRoleAccessCreateHint");

  const statProfiles = el("roleAccessStatProfiles");
  const statCustom = el("roleAccessStatCustom");
  const statDefault = el("roleAccessStatDefault");
  const statPersonnel = el("roleAccessStatPersonnel");

  const modalDepartmentInput = el("personnelRoleAccessDepartment");
  const modalPositionInput = el("personnelRoleAccessPosition");
  const modalScopeEl = el("personnelRoleAccessScope");
  const modalMemberCountEl = el("personnelRoleAccessMemberCount");
  const modalSourceEl = el("personnelRoleAccessSource");
  const modalModulesSummaryEl = el("personnelRoleAccessModulesSummary");

  const escapeHtml = (value) =>
    String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");

  const safe = (value, fallback = "—") => {
    const normalized = String(value ?? "").trim();
    return normalized !== "" ? normalized : fallback;
  };

  const sortLabels = (values) =>
    Array.from(new Set(
      values
        .map((value) => String(value ?? "").trim())
        .filter(Boolean)
    )).sort((a, b) => a.localeCompare(b));

  const normalizeScopeValue = (value) =>
    String(value ?? "")
      .replace(/[\r\n\t]+/g, " ")
      .replace(/\s+/g, " ")
      .trim()
      .toLowerCase();

  const buildProfileId = (department, positionAccess) =>
    `${normalizeScopeValue(department)}||${normalizeScopeValue(positionAccess)}`;

  const formatDateTime = (raw) => {
    const value = String(raw || "").trim();
    if (!value) return "Not customized yet";

    const normalized = value.replace(" ", "T");
    const parsed = new Date(normalized);
    if (Number.isNaN(parsed.getTime())) return value;

    return parsed.toLocaleString("en-PH", {
      year: "numeric",
      month: "short",
      day: "numeric",
      hour: "numeric",
      minute: "2-digit",
    });
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
            };
          });
          return;
        }

        map[item.key] = {
          ...item,
          section: section.section || "",
          parentLabel: "",
          parentKey: "",
        };
      });
    });
    return map;
  };

  const permissionMetaMap = buildPermissionMetaMap();
  const defaultPermissionKeys = sortLabels(
    defaultPermissionKeysFromOpts.length ? defaultPermissionKeysFromOpts : Object.keys(permissionMetaMap)
  );

  const buildDefaultPermissionMap = () => {
    const map = {};
    defaultPermissionKeys.forEach((key) => {
      map[key] = true;
    });
    return map;
  };

  const permissionSummaryFromMap = (permissionMap, maxLabels = 3) => {
    const labels = Object.keys(permissionMap || {}).map((key) => {
      const meta = permissionMetaMap[key] || {};
      const parentLabel = String(meta.parentLabel || "").trim();
      const label = String(meta.label || key).trim();
      return parentLabel ? `${parentLabel} - ${label}` : label;
    });

    const uniqueLabels = sortLabels(labels);
    if (!uniqueLabels.length) {
      return "No modules";
    }

    const visible = uniqueLabels.slice(0, maxLabels);
    return uniqueLabels.length > maxLabels
      ? `${visible.join(", ")} +${uniqueLabels.length - maxLabels}`
      : visible.join(", ");
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

  const populatePromptSelect = (selectEl, values, promptLabel, currentValue = "") => {
    if (!selectEl) return;
    const options = [`<option value="">${escapeHtml(promptLabel)}</option>`];
    values.forEach((value) => {
      options.push(`<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`);
    });
    selectEl.innerHTML = options.join("");
    selectEl.value = values.includes(currentValue) ? currentValue : "";
  };

  const getUniqueValues = (key) => {
    const values = new Set();
    state.rowsRaw.forEach((row) => {
      const value = String(row?.[key] ?? "").trim();
      if (value !== "") values.add(value);
    });
    return Array.from(values).sort((a, b) => a.localeCompare(b));
  };

  const normalizeEditorOptions = (raw) => {
    const departments = sortLabels(Array.isArray(raw?.departments) ? raw.departments : []);
    const positionsByDepartment = {};

    const rawPositions = raw?.positions_by_department || raw?.positionsByDepartment || {};
    Object.entries(rawPositions).forEach(([department, positions]) => {
      const departmentLabel = String(department || "").trim();
      if (!departmentLabel) return;
      positionsByDepartment[departmentLabel] = sortLabels(Array.isArray(positions) ? positions : []);
    });

    state.rowsRaw.forEach((row) => {
      const department = String(row?.department_display || row?.department || "").trim();
      const position = String(row?.position_display || row?.position_access || "").trim();
      if (!department) return;
      if (!departments.includes(department)) departments.push(department);
      if (!positionsByDepartment[department]) positionsByDepartment[department] = [];
      if (position) positionsByDepartment[department].push(position);
    });

    const sortedDepartments = sortLabels(departments);
    sortedDepartments.forEach((department) => {
      positionsByDepartment[department] = sortLabels(positionsByDepartment[department] || []);
    });

    return {
      departments: sortedDepartments,
      positionsByDepartment,
    };
  };

  const renderStats = () => {
    if (statProfiles) statProfiles.textContent = String(state.summary.profiles_total || 0);
    if (statCustom) statCustom.textContent = String(state.summary.custom_profiles || 0);
    if (statDefault) statDefault.textContent = String(state.summary.default_profiles || 0);
    if (statPersonnel) statPersonnel.textContent = String(state.summary.assigned_personnel_total || 0);
  };

  const renderFilterOptions = () => {
    const departments = getUniqueValues("department_display");
    const positions = getUniqueValues("position_display");

    if (!departments.includes(state.department)) state.department = "ALL";
    if (!positions.includes(state.position)) state.position = "ALL";

    populateSelect(departmentFilter, departments, "All departments", state.department);
    populateSelect(positionFilter, positions, "All positions", state.position);
  };

  const applyFilters = () => {
    const q = state.search.toLowerCase();
    state.rows = state.rowsRaw.filter((row) => {
      if (state.department !== "ALL" && String(row.department_display || "") !== state.department) return false;
      if (state.position !== "ALL" && String(row.position_display || "") !== state.position) return false;
      if (!q) return true;

      const searchBag = [
        row.department_display,
        row.position_display,
        row.access_source,
        row.permission_summary,
        ...(Array.isArray(row.permission_keys) ? row.permission_keys.map((key) => {
          const meta = permissionMetaMap[key] || {};
          return [meta.parentLabel, meta.label, meta.section].join(" ");
        }) : []),
      ].join(" ").toLowerCase();

      return searchBag.includes(q);
    });
  };

  const sourceBadgeHtml = (row) => {
    const isCustom = Boolean(row.has_saved_profile);
    return `<span class="role-access-source-badge ${isCustom ? "is-custom" : "is-default"}">${escapeHtml(safe(row.access_source))}</span>`;
  };

  const renderTable = () => {
    if (!tbody) return;

    applyFilters();
    if (metaEl) {
      const shown = state.rows.length;
      const total = state.rowsRaw.length;
      metaEl.textContent = `${shown} of ${total} position permission profiles shown`;
    }

    if (!state.rows.length) {
      const emptyMessage = state.rowsRaw.length === 0
        ? "No role profiles yet. Use Add Role Profile to create one."
        : "No position permission profiles match the current filters.";
      tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">${escapeHtml(emptyMessage)}</td></tr>`;
      return;
    }

    tbody.innerHTML = state.rows.map((row) => `
      <tr>
        <td>${escapeHtml(safe(row.department_display))}</td>
        <td>${escapeHtml(safe(row.position_display))}</td>
        <td>${escapeHtml(String(row.personnel_count || 0))}</td>
        <td>${sourceBadgeHtml(row)}</td>
        <td class="role-access-modules">${escapeHtml(safe(row.permission_summary))}</td>
        <td>${escapeHtml(formatDateTime(row.updated_at))}</td>
        <td>
          <button type="button"
                  class="btn btn-sm btn-outline-primary personnel-role-access-manage"
                  data-profile-id="${escapeHtml(String(row.profile_id || ""))}">
            Manage Permissions
          </button>
        </td>
      </tr>
    `).join("");

    tbody.querySelectorAll(".personnel-role-access-manage").forEach((button) => {
      button.addEventListener("click", () => {
        const profileId = String(button.getAttribute("data-profile-id") || "");
        const row = state.rowsRaw.find((entry) => String(entry.profile_id) === profileId);
        if (row) openAccessModalFromRow(row, "edit");
      });
    });
  };

  const getChildKeys = (item) =>
    Array.isArray(item.children) && item.children.length
      ? item.children.map((child) => String(child.key || "").trim()).filter(Boolean)
      : [];

  const sectionHasVisibleItems = (section, term) => {
    const normalizedTerm = term.toLowerCase();
    if (!normalizedTerm) return true;

    return (section.items || []).some((item) => {
      const itemLabel = String(item.label || "").toLowerCase();
      if (itemLabel.includes(normalizedTerm)) return true;
      return (item.children || []).some((child) => String(child.label || "").toLowerCase().includes(normalizedTerm));
    });
  };

  const updateModalPermissionSummary = () => {
    if (!modalModulesSummaryEl) return;
    const selectedMap = state.modal.permissionMap || {};
    const count = Object.keys(selectedMap).length;
    modalModulesSummaryEl.textContent = `${count} granted - ${permissionSummaryFromMap(selectedMap)}`;
  };

  const bindPermissionEvents = () => {
    if (!permissionGroupsEl) return;

    permissionGroupsEl.querySelectorAll(".role-access-child").forEach((checkbox) => {
      checkbox.addEventListener("change", () => {
        const key = String(checkbox.getAttribute("data-key") || "").trim();
        if (!key) return;
        if (checkbox.checked) state.modal.permissionMap[key] = true;
        else delete state.modal.permissionMap[key];
        updateModalPermissionSummary();
        renderPermissionGroups();
      });
    });

    permissionGroupsEl.querySelectorAll(".role-access-parent").forEach((checkbox) => {
      checkbox.addEventListener("change", () => {
        const childKeys = String(checkbox.getAttribute("data-child-keys") || "")
          .split(",")
          .map((value) => value.trim())
          .filter(Boolean);

        childKeys.forEach((key) => {
          if (checkbox.checked) state.modal.permissionMap[key] = true;
          else delete state.modal.permissionMap[key];
        });

        updateModalPermissionSummary();
        renderPermissionGroups();
      });
    });

    permissionGroupsEl.querySelectorAll(".role-access-section-toggle").forEach((button) => {
      button.addEventListener("click", () => {
        const mode = String(button.getAttribute("data-mode") || "on");
        const keys = String(button.getAttribute("data-section-keys") || "")
          .split(",")
          .map((value) => value.trim())
          .filter(Boolean);

        keys.forEach((key) => {
          if (mode === "on") state.modal.permissionMap[key] = true;
          else delete state.modal.permissionMap[key];
        });

        updateModalPermissionSummary();
        renderPermissionGroups();
      });
    });
  };

  const renderPermissionGroups = () => {
    if (!permissionGroupsEl) return;
    const row = state.modal.row;
    if (!row) {
      permissionGroupsEl.innerHTML = "";
      return;
    }

    const term = String(state.modal.search || "").trim().toLowerCase();

    const html = permissionCatalog
      .filter((section) => sectionHasVisibleItems(section, term))
      .map((section) => {
        const sectionLeafKeys = [];
        const itemsHtml = (section.items || []).map((item) => {
          const children = item.children || [];
          if (children.length) {
            const childKeys = getChildKeys(item);
            childKeys.forEach((key) => sectionLeafKeys.push(key));

            const visibleChildren = children.filter((child) => {
              const childLabel = String(child.label || "").toLowerCase();
              return !term || childLabel.includes(term) || String(item.label || "").toLowerCase().includes(term);
            });
            if (!visibleChildren.length && !String(item.label || "").toLowerCase().includes(term)) {
              return "";
            }

            const selectedChildren = childKeys.filter((key) => Boolean(state.modal.permissionMap[key]));
            const parentChecked = childKeys.length > 0 && selectedChildren.length === childKeys.length;
            const parentPartial = selectedChildren.length > 0 && selectedChildren.length < childKeys.length;

            const childHtml = visibleChildren.map((child) => {
              const key = String(child.key || "").trim();
              const checked = Boolean(state.modal.permissionMap[key]);
              return `
                <div class="role-access-item is-child">
                  <label>
                    <input type="checkbox" class="role-access-child" data-key="${escapeHtml(key)}" ${checked ? "checked" : ""}>
                    <span>
                      <span class="role-access-item-main">${escapeHtml(child.label || key)}</span>
                      <span class="role-access-item-sub">${escapeHtml(section.section || "")}</span>
                    </span>
                  </label>
                </div>
              `;
            }).join("");

            return `
              <div class="role-access-item">
                <label>
                  <input type="checkbox"
                         class="role-access-parent"
                         data-child-keys="${escapeHtml(childKeys.join(","))}"
                         ${parentChecked ? "checked" : ""}
                         ${parentPartial ? 'data-partial="1"' : ""}>
                  <span>
                    <span class="role-access-item-main">${escapeHtml(item.label || item.key)}</span>
                    <span class="role-access-item-sub">Toggle all modules in this group.</span>
                  </span>
                </label>
              </div>
              ${childHtml}
            `;
          }

          const key = String(item.key || "").trim();
          const checked = Boolean(state.modal.permissionMap[key]);
          const itemText = `${item.label || ""} ${section.section || ""}`.toLowerCase();
          if (term && !itemText.includes(term)) {
            return "";
          }

          sectionLeafKeys.push(key);
          return `
            <div class="role-access-item">
              <label>
                <input type="checkbox" class="role-access-child" data-key="${escapeHtml(key)}" ${checked ? "checked" : ""}>
                <span>
                  <span class="role-access-item-main">${escapeHtml(item.label || key)}</span>
                  <span class="role-access-item-sub">${escapeHtml(section.section || "")}</span>
                </span>
              </label>
            </div>
          `;
        }).join("");

        if (!itemsHtml.trim()) {
          return "";
        }

        return `
          <div class="role-access-group">
            <div class="role-access-group-head">
              <div class="role-access-group-title">${escapeHtml(section.section || "Modules")}</div>
              <div class="role-access-group-actions">
                <button type="button" class="btn btn-sm btn-outline-secondary role-access-section-toggle" data-mode="on" data-section-keys="${escapeHtml(sectionLeafKeys.join(","))}">Check all</button>
                <button type="button" class="btn btn-sm btn-outline-secondary role-access-section-toggle" data-mode="off" data-section-keys="${escapeHtml(sectionLeafKeys.join(","))}">Clear</button>
              </div>
            </div>
            <div class="role-access-items">${itemsHtml}</div>
          </div>
        `;
      })
      .join("");

    permissionGroupsEl.innerHTML = html || '<div class="text-muted small px-2 py-3">No module labels match that search.</div>';
    permissionGroupsEl.querySelectorAll(".role-access-parent[data-partial='1']").forEach((input) => {
      input.indeterminate = true;
    });
    bindPermissionEvents();
  };

  const setModalChrome = () => {
    const row = state.modal.row;
    const isCreate = state.modal.mode === "create";
    if (!row) return;

    if (modalTitleEl) {
      modalTitleEl.textContent = isCreate ? "Create Access Control Profile" : "Manage Access Control Profile";
    }
    if (modalNoticeEl) {
      modalNoticeEl.textContent = isCreate
        ? "This new profile will become the default access for the selected department position. Personnel assigned later will inherit these checked permissions."
        : "This profile applies to the selected department position. Saving here updates the default permissions that personnel in this position inherit.";
    }

    if (modalDepartmentInput) modalDepartmentInput.value = row.department || "";
    if (modalPositionInput) modalPositionInput.value = row.position_access || "";
    if (modalScopeEl) modalScopeEl.textContent = `${safe(row.department_display)} - ${safe(row.position_display)}`;
    if (modalMemberCountEl) modalMemberCountEl.textContent = `${row.personnel_count || 0} personnel`;
    if (modalSourceEl) modalSourceEl.textContent = isCreate ? "New Profile" : safe(row.access_source);

    updateModalPermissionSummary();

    if (resetBtn) {
      resetBtn.classList.toggle("d-none", isCreate);
      resetBtn.disabled = isCreate || !row.has_saved_profile;
    }
    if (saveBtn) {
      saveBtn.textContent = isCreate ? "Create Profile" : "Save Permissions";
    }
  };

  const createPendingRow = (department, positionAccess) => ({
    profile_id: buildProfileId(department, positionAccess),
    department,
    department_display: department,
    position_access: positionAccess,
    position_display: positionAccess,
    personnel_count: 0,
    has_saved_profile: false,
    access_source: "Default Permissions",
    permission_keys: defaultPermissionKeys.slice(),
    permission_count: defaultPermissionKeys.length,
    permission_summary: permissionSummaryFromMap(buildDefaultPermissionMap()),
    updated_at: "",
  });

  const openAccessModalFromRow = (row, mode = "edit") => {
    if (!row || !modalEl) return;

    state.modal.mode = mode;
    state.modal.row = {
      ...row,
      department: String(row.department || row.department_display || "").trim(),
      position_access: String(row.position_access || row.position_display || "").trim(),
      department_display: String(row.department_display || row.department || "").trim(),
      position_display: String(row.position_display || row.position_access || "").trim(),
    };
    state.modal.permissionMap = {};
    const selectedKeys = Array.isArray(row.permission_keys) && row.permission_keys.length
      ? row.permission_keys
      : defaultPermissionKeys;
    selectedKeys.forEach((key) => {
      state.modal.permissionMap[String(key)] = true;
    });
    state.modal.search = "";

    if (permissionSearchInput) permissionSearchInput.value = "";
    setModalChrome();
    renderPermissionGroups();
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  };

  const findRowByScope = (department, positionAccess) => {
    const targetId = buildProfileId(department, positionAccess);
    return state.rowsRaw.find((row) => {
      const rowDepartment = row.department || row.department_display || "";
      const rowPosition = row.position_access || row.position_display || "";
      return buildProfileId(rowDepartment, rowPosition) === targetId;
    }) || null;
  };

  const renderCreatePositionOptions = (selectedDepartment, selectedPosition = "") => {
    const positions = selectedDepartment
      ? (state.editorOptions.positionsByDepartment[selectedDepartment] || [])
      : [];
    populatePromptSelect(createPositionSelect, positions, "Select position", selectedPosition);
  };

  const updateCreateHint = () => {
    if (!createHintEl) return;
    const department = String(createDepartmentSelect?.value || "").trim();
    const position = String(createPositionSelect?.value || "").trim();
    if (!department || !position) {
      createHintEl.textContent = "Existing profiles can be reopened here. New profiles start with the current default permissions already checked.";
      return;
    }

    const existingRow = findRowByScope(department, position);
    createHintEl.textContent = existingRow
      ? "This department and position already has a profile. Continue to manage the existing permissions."
      : "This will open a new role profile with the default permissions preselected.";
  };

  const openCreateScopeModal = () => {
    if (!createModalEl) return;
    populatePromptSelect(createDepartmentSelect, state.editorOptions.departments, "Select department");
    renderCreatePositionOptions("");
    updateCreateHint();
    bootstrap.Modal.getOrCreateInstance(createModalEl).show();
  };

  const parseJsonResponse = async (res) => {
    const text = await res.text();
    if (!text) return null;
    try {
      return JSON.parse(text);
    } catch (error) {
      return null;
    }
  };

  const postProfileAction = async (action, extraBody = {}) => {
    const body = new FormData();
    body.append("action", action);

    Object.entries(extraBody).forEach(([key, value]) => {
      if (Array.isArray(value)) {
        value.forEach((entry) => body.append(`${key}[]`, String(entry)));
        return;
      }
      body.append(key, String(value ?? ""));
    });

    const res = await fetch(apiUrl, {
      method: "POST",
      body,
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    });
    const data = await parseJsonResponse(res);
    if (!res.ok || !data?.success) {
      throw new Error(data?.message || "Unable to save access control changes.");
    }

    return data;
  };

  const saveProfile = async () => {
    const row = state.modal.row;
    if (!row) return;

    const department = String(row.department || "").trim();
    const positionAccess = String(row.position_access || "").trim();
    if (!department || !positionAccess) {
      throw new Error("Department and position are required.");
    }

    const permissionKeys = Object.keys(state.modal.permissionMap);
    await postProfileAction("save_profile_permissions", {
      department,
      position_access: positionAccess,
      permission_keys: permissionKeys,
    });
  };

  const resetProfile = async () => {
    const row = state.modal.row;
    if (!row) return;
    if (!(await window.UniversalModal.confirm("Reset this position permission profile back to the default permissions?"))) return;

    await postProfileAction("reset_profile_permissions", {
      department: row.department || "",
      position_access: row.position_access || "",
    });
  };

  const setLoadingState = (loading) => {
    if (refreshBtn) refreshBtn.disabled = loading;
    if (createBtn) createBtn.disabled = loading;
    if (createContinueBtn) createContinueBtn.disabled = loading;
    if (saveBtn) saveBtn.disabled = loading;
    if (resetBtn) resetBtn.disabled = loading || state.modal.mode === "create" || !Boolean(state.modal.row?.has_saved_profile);
  };

  const load = async () => {
    setLoadingState(true);
    try {
      const params = new URLSearchParams();
      params.set("action", "list_profiles");

      const res = await fetch(`${apiUrl}?${params.toString()}`, {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
      });
      const data = await parseJsonResponse(res);
      if (!res.ok || !data?.success) {
        throw new Error(data?.message || "Unable to load position permission profiles.");
      }

      state.rowsRaw = Array.isArray(data.data) ? data.data : [];
      state.summary = data.summary || state.summary;
      state.editorOptions = normalizeEditorOptions(data.editor_options || {});
      renderStats();
      renderFilterOptions();
      renderTable();
    } catch (error) {
      if (tbody) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${escapeHtml(safe(error?.message || "Unable to load position permission profiles."))}</td></tr>`;
      }
      if (metaEl) metaEl.textContent = "Unable to load position permission profiles";
    } finally {
      setLoadingState(false);
    }
  };

  if (searchInput) {
    searchInput.addEventListener("input", () => {
      state.search = String(searchInput.value || "").trim();
      renderTable();
    });
  }

  if (departmentFilter) {
    departmentFilter.addEventListener("change", () => {
      state.department = departmentFilter.value || "ALL";
      renderTable();
    });
  }

  if (positionFilter) {
    positionFilter.addEventListener("change", () => {
      state.position = positionFilter.value || "ALL";
      renderTable();
    });
  }

  if (refreshBtn) {
    refreshBtn.addEventListener("click", () => {
      load();
    });
  }

  if (createBtn) {
    createBtn.addEventListener("click", () => {
      openCreateScopeModal();
    });
  }

  if (createDepartmentSelect) {
    createDepartmentSelect.addEventListener("change", () => {
      renderCreatePositionOptions(String(createDepartmentSelect.value || "").trim());
      updateCreateHint();
    });
  }

  if (createPositionSelect) {
    createPositionSelect.addEventListener("change", () => {
      updateCreateHint();
    });
  }

  if (createContinueBtn) {
    createContinueBtn.addEventListener("click", () => {
      const department = String(createDepartmentSelect?.value || "").trim();
      const positionAccess = String(createPositionSelect?.value || "").trim();
      if (!department || !positionAccess) {
        window.alert("Choose both the department and the position first.");
        return;
      }

      bootstrap.Modal.getOrCreateInstance(createModalEl).hide();
      const existingRow = findRowByScope(department, positionAccess);
      if (existingRow) {
        openAccessModalFromRow(existingRow, "edit");
        return;
      }

      openAccessModalFromRow(createPendingRow(department, positionAccess), "create");
    });
  }

  if (permissionSearchInput) {
    permissionSearchInput.addEventListener("input", () => {
      state.modal.search = String(permissionSearchInput.value || "").trim();
      renderPermissionGroups();
    });
  }

  if (saveBtn) {
    saveBtn.addEventListener("click", async () => {
      try {
        setLoadingState(true);
        await saveProfile();
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        await load();
      } catch (error) {
        window.alert(error?.message || "Unable to save access control changes.");
      } finally {
        setLoadingState(false);
      }
    });
  }

  if (resetBtn) {
    resetBtn.addEventListener("click", async () => {
      try {
        setLoadingState(true);
        await resetProfile();
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        await load();
      } catch (error) {
        window.alert(error?.message || "Unable to reset access control profile.");
      } finally {
        setLoadingState(false);
      }
    });
  }

  load();
})();
