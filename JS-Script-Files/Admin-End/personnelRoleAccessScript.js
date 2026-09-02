(function () {
  const opts = window.PERSONNEL_ROLE_ACCESS_OPTIONS || {};
  const apiUrl = String(opts.apiUrl || "../PhpFiles/Admin-End/personnelRoleAccess.php");
  const appBaseUrl = String(opts.appBaseUrl || "").replace(/\/+$/, "");
  const previewToken = String(opts.previewToken || "");
  const csrfToken = String(opts.csrfToken || "");
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
    preview: {
      row: null,
      items: [],
      activeKey: "",
      search: "",
      openGroups: {},
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
  const createPositionCustomInput = el("personnelRoleAccessCreatePositionCustom");
  const createContinueBtn = el("btnPersonnelRoleAccessCreateContinue");
  const createHintEl = el("personnelRoleAccessCreateHint");

  const previewModalEl = el("modalPersonnelRoleAccessPreview");
  const previewTitleEl = el("personnelRoleAccessPreviewTitle");
  const previewScopeEl = el("personnelRoleAccessPreviewScope");
  const previewMetaEl = el("personnelRoleAccessPreviewMeta");
  const previewSearchInput = el("personnelRoleAccessPreviewSearch");
  const previewNavEl = el("personnelRoleAccessPreviewNav");
  const previewCurrentTitleEl = el("personnelRoleAccessPreviewCurrentTitle");
  const previewCurrentPathEl = el("personnelRoleAccessPreviewCurrentPath");
  const previewFrameEl = el("personnelRoleAccessPreviewFrame");
  const previewEmptyEl = el("personnelRoleAccessPreviewEmpty");
  const previewReloadBtn = el("btnPersonnelRoleAccessPreviewReload");

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

  const notify = (message, tone = "info", title = "") => {
    const normalizedMessage = String(message || "").trim();
    if (window.UniversalModal?.open) {
      window.UniversalModal.open({
        message: normalizedMessage,
        tone,
        title,
      });
      return;
    }
    window.alert(normalizedMessage);
  };

  const confirmAction = (message, options = {}) => {
    if (window.UniversalModal?.confirm) {
      return window.UniversalModal.confirm(message, options);
    }
    return Promise.resolve(window.confirm(message));
  };

  const validAccessLabel = (value) =>
    /^[A-Za-z0-9][A-Za-z0-9 .,'()&\/-]{0,99}$/.test(String(value || "").trim());

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

  const buildAppUrl = (path) => {
    const rawPath = String(path || "").trim();
    if (!rawPath) return "about:blank";
    if (/^(?:https?:)?\/\//i.test(rawPath) || rawPath.startsWith("about:")) return rawPath;
    const cleanPath = rawPath.replace(/^\/+/, "");
    return appBaseUrl ? `${appBaseUrl}/${cleanPath}` : `/${cleanPath}`;
  };

  const appendUrlParams = (url, params) => {
    const [baseAndQuery, hash = ""] = String(url || "").split("#", 2);
    const separator = baseAndQuery.includes("?") ? "&" : "?";
    const query = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
      const normalized = String(value ?? "").trim();
      if (normalized !== "") query.set(key, normalized);
    });

    const suffix = query.toString();
    return suffix
      ? `${baseAndQuery}${separator}${suffix}${hash ? `#${hash}` : ""}`
      : url;
  };

  const getPreviewParams = (row) => ({
    access_preview: "personnel_role",
    preview_department: row?.department || row?.department_display || "",
    preview_position: row?.position_access || row?.position_display || "",
    preview_token: previewToken,
  });

  const buildPreviewUrl = (path, row) => appendUrlParams(buildAppUrl(path), getPreviewParams(row));

  const withPreviewParams = (url) => {
    const row = state.preview.row;
    if (!row) return url;
    return appendUrlParams(url, getPreviewParams(row));
  };

  const previewIconClass = (key, parentKey = "") => {
    const iconMap = {
      dashboard: "fa-house",
      appointments: "fa-calendar-check",
      resident_profiling: "fa-user-group",
      household_profiling: "fa-house",
      area_statistics: "fa-map-location-dot",
      certificate_issuance: "fa-file-circle-check",
      id_issuance: "fa-id-card",
      clearance_issuance: "fa-stamp",
      business_monitoring: "fa-building",
      finance_transactions: "fa-money-check-alt",
      blotter_log_new_incident: "fa-scale-balanced",
      blotter_tools: "fa-toolbox",
      complaint_log_new_incident: "fa-comments",
      complaint_tools: "fa-comments",
      news_management: "fa-newspaper",
      announcements: "fa-bullhorn",
      reports: "fa-chart-bar",
      user_management: "fa-users-cog",
      admin_management: "fa-user-gear",
      personnel_management: "fa-user-tie",
      official_records_management: "fa-user-shield",
      official_transition: "fa-user-shield",
      audit_logs: "fa-clipboard-list",
      website_settings: "fa-screwdriver-wrench",
    };

    return iconMap[parentKey] || iconMap[key] || "fa-circle-dot";
  };

  const getPreviewItemsFromPermissionMap = (permissionMap, row) => {
    const allowed = permissionMap || {};
    const items = [];

    permissionCatalog.forEach((section) => {
      (section.items || []).forEach((item) => {
        const children = Array.isArray(item.children) ? item.children : [];
        if (children.length) {
          children.forEach((child) => {
            const key = String(child.key || "").trim();
            const path = String(child.path || "").trim();
            if (!key || !path || !allowed[key]) return;
            items.push({
              key,
              label: String(child.label || key),
              parentLabel: String(item.label || ""),
              parentKey: String(item.key || ""),
              section: String(section.section || "Modules"),
              path,
              url: buildPreviewUrl(path, row),
            });
          });
          return;
        }

        const key = String(item.key || "").trim();
        const path = String(item.path || "").trim();
        if (!key || !path || !allowed[key]) return;
        items.push({
          key,
          label: String(item.label || key),
          parentLabel: "",
          parentKey: "",
          section: String(section.section || "Modules"),
          path,
          url: buildPreviewUrl(path, row),
        });
      });
    });

    return items;
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
          <div class="role-access-action-stack">
            <button type="button"
                    class="btn btn-sm btn-outline-primary personnel-role-access-preview"
                    data-profile-id="${escapeHtml(String(row.profile_id || ""))}">
              <i class="fas fa-eye me-1"></i> Preview Access
            </button>
            <button type="button"
                    class="btn btn-sm btn-outline-primary personnel-role-access-manage"
                    data-profile-id="${escapeHtml(String(row.profile_id || ""))}">
              Manage Permissions
            </button>
          </div>
        </td>
      </tr>
    `).join("");

    tbody.querySelectorAll(".personnel-role-access-preview").forEach((button) => {
      button.addEventListener("click", () => {
        const profileId = String(button.getAttribute("data-profile-id") || "");
        const row = state.rowsRaw.find((entry) => String(entry.profile_id) === profileId);
        if (row) openPreviewModalFromRow(row);
      });
    });

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

  const buildPermissionMapFromRow = (row) => {
    const map = {};
    const fallbackKeys = row && !row.has_saved_profile ? defaultPermissionKeys : [];
    const sourceKeys = Array.isArray(row?.permission_keys) ? row.permission_keys : fallbackKeys;
    sourceKeys.forEach((key) => {
      const normalizedKey = String(key || "").trim();
      if (normalizedKey) map[normalizedKey] = true;
    });
    return map;
  };

  const updatePreviewFrame = (item) => {
    if (previewEmptyEl) {
      previewEmptyEl.classList.toggle("d-none", Boolean(item));
    }
    if (previewFrameEl) {
      previewFrameEl.classList.toggle("d-none", !item);
      const nextUrl = item ? item.url : "about:blank";
      if (previewFrameEl.getAttribute("src") !== nextUrl) {
        previewFrameEl.setAttribute("src", nextUrl);
      }
    }
    if (previewCurrentTitleEl) {
      previewCurrentTitleEl.textContent = item ? item.label : "No Modules Granted";
    }
    if (previewCurrentPathEl) {
      previewCurrentPathEl.textContent = item ? item.path : "-";
    }
    if (previewReloadBtn) {
      previewReloadBtn.disabled = !item;
    }
  };

  const preparePreviewFrameDocument = () => {
    if (!previewFrameEl || previewFrameEl.classList.contains("d-none")) return;

    try {
      const doc = previewFrameEl.contentDocument;
      if (!doc || !doc.head) {
        return;
      }

      if (!doc.querySelector("style[data-role-access-preview-frame='1']")) {
        const style = doc.createElement("style");
        style.setAttribute("data-role-access-preview-frame", "1");
        style.textContent = `
          :root {
            --role-access-preview-readonly: #9a3412;
          }
          #dashboard-sidebar,
          #admin-mobile-header {
            display: none !important;
          }
          html {
            width: 100% !important;
            height: auto !important;
            min-height: 100% !important;
            scroll-behavior: auto !important;
            overflow-x: hidden !important;
            overflow-y: auto !important;
          }
          body {
            width: 100% !important;
            margin: 0 !important;
            padding-top: 0 !important;
            height: auto !important;
            min-height: 100% !important;
            overflow-x: hidden !important;
            overflow-y: auto !important;
          }
          #main-display {
            width: 100% !important;
            max-width: 100% !important;
            min-height: auto !important;
            padding: 1rem !important;
            overflow: visible !important;
          }
          main,
          .main-content,
          .content-wrapper,
          .container,
          .container-fluid {
            max-width: 100% !important;
          }
          .d-flex.flex-column.flex-md-row {
            height: auto !important;
            min-height: 100% !important;
            overflow: visible !important;
          }
          .dashboard-page-header {
            margin-bottom: 1rem !important;
          }
          .dashboard-page-title {
            font-size: clamp(2rem, 3vw, 2.45rem) !important;
          }
          .dashboard-attention-panel,
          .chart-panel {
            border-radius: 18px !important;
          }
          .dashboard-attention-panel {
            padding: 1rem !important;
          }
          .dashboard-section-head,
          .chart-panel-head {
            gap: 0.9rem !important;
          }
          .attention-tile {
            min-height: 78px !important;
            border-radius: 14px !important;
            padding: 0.85rem 0.9rem !important;
          }
          .attention-tile__icon {
            width: 42px !important;
            height: 42px !important;
            border-radius: 14px !important;
          }
          .service-grid {
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 190px), 1fr)) !important;
            gap: 1rem !important;
            justify-content: start !important;
          }
          .service-grid > [class*="col-"] {
            display: flex !important;
            width: auto !important;
            max-width: none !important;
            min-width: 0 !important;
            padding: 0 !important;
            flex: none !important;
          }
          .card-action {
            aspect-ratio: auto !important;
            min-height: 190px !important;
            border-radius: 20px !important;
            padding: 1rem !important;
            grid-template-rows: 62px minmax(2.1rem, auto) minmax(2.7rem, auto) !important;
            gap: 0.65rem !important;
          }
          .card-action__badge {
            top: 0.65rem !important;
            right: 0.65rem !important;
            min-width: 1.65rem !important;
            height: 1.65rem !important;
            font-size: 0.72rem !important;
          }
          .card-action__icon {
            width: 62px !important;
            height: 62px !important;
            border-radius: 18px !important;
          }
          .card-action__icon i {
            font-size: 1.65rem !important;
          }
          .card-action__title {
            max-width: 15ch !important;
            font-size: 1rem !important;
            line-height: 1.18 !important;
          }
          .card-action__subtext {
            max-width: 15rem !important;
            font-size: 0.82rem !important;
            line-height: 1.35 !important;
          }
          body::before {
            content: "Preview only - actions are disabled";
            position: fixed;
            right: 1rem;
            bottom: 1rem;
            z-index: 2147483647;
            padding: 0.45rem 0.75rem;
            border: 1px solid #fed7aa;
            border-radius: 999px;
            background: #fff7ed;
            color: var(--role-access-preview-readonly);
            font: 700 0.78rem/1.2 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
            pointer-events: none;
          }
          button:not([data-bs-toggle]),
          input,
          select,
          textarea,
          [role="button"]:not([data-bs-toggle]),
          .btn:not(a[href]):not([data-bs-toggle]) {
            cursor: not-allowed !important;
          }
          form {
            position: relative;
          }
        `;
        doc.head.appendChild(style);
      }

      const applyPreviewHref = (anchor) => {
        const rawHref = String(anchor.getAttribute("href") || "").trim();
        if (
          !rawHref
          || rawHref.startsWith("#")
          || /^javascript:/i.test(rawHref)
          || /^mailto:/i.test(rawHref)
          || /^tel:/i.test(rawHref)
        ) {
          return;
        }

        const parsed = new URL(rawHref, doc.location.href);
        if (parsed.origin !== doc.location.origin || !parsed.pathname.includes("/Admin-End/")) {
          return;
        }

        anchor.setAttribute("href", withPreviewParams(parsed.href));
        anchor.setAttribute("target", "_self");
      };

      doc.querySelectorAll("a[href]").forEach(applyPreviewHref);
      if (!doc.body || doc.body.getAttribute("data-role-access-preview-links") === "1") {
        return;
      }

      doc.querySelectorAll("form").forEach((form) => {
        form.setAttribute("data-preview-readonly", "1");
      });

      doc.querySelectorAll("button, input, select, textarea").forEach((control) => {
        const type = String(control.getAttribute("type") || "").toLowerCase();
        if (type === "hidden") return;
        control.setAttribute("data-preview-readonly", "1");
        if (control.matches("button[data-bs-toggle], input[type='button'][data-bs-toggle]")) {
          return;
        }
        if (control.matches("button, input[type='button'], input[type='submit'], input[type='reset']")) {
          control.setAttribute("disabled", "disabled");
        } else if (!control.hasAttribute("readonly")) {
          control.setAttribute("readonly", "readonly");
        }
      });

      const isActionAnchor = (anchor) => {
        const rawHref = String(anchor.getAttribute("href") || "").trim();
        const actionHint = [
          anchor.getAttribute("data-action"),
          anchor.getAttribute("data-delete-url"),
          anchor.getAttribute("data-archive-url"),
          anchor.getAttribute("data-restore-url"),
          anchor.getAttribute("data-status-url"),
          anchor.getAttribute("data-method"),
          anchor.getAttribute("download"),
          anchor.className,
          anchor.id,
        ].join(" ").toLowerCase();

        if (anchor.closest("form")) {
          return true;
        }

        if (
          /\b(delete|remove|archive|restore|approve|reject|deny|cancel|save|submit|update|issue|release|void|complete|confirm|reschedule|send|publish|unpublish)\b/.test(actionHint)
        ) {
          return true;
        }

        if (/\/PhpFiles\//i.test(rawHref)) {
          return true;
        }

        return false;
      };

      const isNavigationAnchor = (anchor) => {
        const rawHref = String(anchor.getAttribute("href") || "").trim();
        if (!rawHref || rawHref.startsWith("#") || /^javascript:/i.test(rawHref)) {
          return false;
        }
        if (isActionAnchor(anchor)) {
          return false;
        }

        const parsed = new URL(rawHref, doc.location.href);
        return parsed.origin === doc.location.origin && parsed.pathname.includes("/Admin-End/");
      };

      const blockReadonlyEvent = (event) => {
        const target = event.target;
        if (event.type === "keydown") {
          const scrollKeys = new Set(["ArrowUp", "ArrowDown", "PageUp", "PageDown", "Home", "End", " "]);
          if (scrollKeys.has(event.key) && !target?.closest?.("input, select, textarea, button, [role='button']")) {
            return;
          }
        }

        const anchor = target?.closest?.("a[href]");
        if (anchor) {
          if (isNavigationAnchor(anchor)) {
            return;
          }
          event.preventDefault();
          event.stopPropagation();
          return;
        }

        const bootstrapPreviewControl = target?.closest?.(
          "[data-bs-toggle='modal'], [data-bs-toggle='collapse'], [data-bs-toggle='tab'], [data-bs-toggle='pill'], [data-bs-toggle='dropdown']"
        );
        if (bootstrapPreviewControl) {
          return;
        }

        const blockedControl = target?.closest?.("form, button, input, select, textarea, [role='button'], [data-bs-toggle], [data-action], .btn");
        if (!blockedControl) return;
        event.preventDefault();
        event.stopPropagation();
      };

      doc.body.setAttribute("data-role-access-preview-links", "1");
      doc.body.addEventListener("submit", blockReadonlyEvent, true);
      doc.body.addEventListener("click", (event) => {
        const anchor = event.target?.closest?.("a[href]");
        if (!anchor) {
          blockReadonlyEvent(event);
          return;
        }
        const beforeHref = anchor.getAttribute("href") || "";
        applyPreviewHref(anchor);
        if (anchor.getAttribute("href") !== beforeHref) {
          return;
        }
        blockReadonlyEvent(event);
      }, true);
      ["change", "input", "keydown"].forEach((eventName) => {
        doc.body.addEventListener(eventName, blockReadonlyEvent, true);
      });
    } catch (error) {
      // Some browser policies can block iframe document access. The preview
      // remains usable through its outer granted-module navigator.
    }
  };

  const renderPreviewNav = () => {
    if (!previewNavEl) return;

    const term = String(state.preview.search || "").trim().toLowerCase();
    const filteredItems = state.preview.items.filter((item) => {
      if (!term) return true;
      return [
        item.label,
        item.parentLabel,
        item.section,
        item.path,
      ].join(" ").toLowerCase().includes(term);
    });

    if (!filteredItems.length) {
      previewNavEl.innerHTML = `<div class="text-muted small px-2 py-3">${escapeHtml(state.preview.items.length ? "No granted modules match that search." : "No modules granted.")}</div>`;
      return;
    }

    const grouped = new Map();
    filteredItems.forEach((item) => {
      const section = item.section || "Modules";
      if (!grouped.has(section)) grouped.set(section, []);
      grouped.get(section).push(item);
    });

    const html = `<ul class="role-access-preview-sidebar-list">${
      Array.from(grouped.entries()).map(([section, items]) => {
        const navGroups = new Map();
        items.forEach((item) => {
          const groupKey = item.parentKey || item.key;
          if (!navGroups.has(groupKey)) {
            navGroups.set(groupKey, {
              key: groupKey,
              label: item.parentLabel || item.label,
              parentKey: item.parentKey || "",
              directItem: item.parentKey ? null : item,
              children: [],
            });
          }

          const group = navGroups.get(groupKey);
          if (item.parentKey) {
            group.children.push(item);
          } else {
            group.directItem = item;
          }
        });

        const groupsHtml = Array.from(navGroups.values()).map((group) => {
          const childItems = group.children;
          const firstItem = group.directItem || childItems[0] || null;
          if (!firstItem) return "";

          const active = firstItem.key === state.preview.activeKey
            || childItems.some((item) => item.key === state.preview.activeKey);
          const iconClass = previewIconClass(firstItem.key, group.key);
          const groupOpen = Boolean(term) || active || state.preview.openGroups[group.key] === true;

          if (!childItems.length) {
            return `
              <li class="mb-2">
                <button type="button"
                        class="role-access-preview-main ${active ? "is-active" : ""}"
                        data-preview-key="${escapeHtml(firstItem.key)}">
                  <span class="role-access-preview-main-left">
                    <span class="role-access-preview-icon-wrap"><i class="fas ${escapeHtml(iconClass)}"></i></span>
                    <span class="role-access-preview-label">${escapeHtml(group.label)}</span>
                  </span>
                </button>
              </li>
            `;
          }

          return `
            <li class="mb-1">
              <button type="button"
                      class="role-access-preview-main role-access-preview-toggle ${active ? "is-active" : ""} ${groupOpen ? "" : "is-collapsed"}"
                      data-preview-group="${escapeHtml(group.key)}"
                      aria-expanded="${groupOpen ? "true" : "false"}">
                <span class="role-access-preview-main-left">
                  <span class="role-access-preview-icon-wrap"><i class="fas ${escapeHtml(iconClass)}"></i></span>
                  <span class="role-access-preview-label">${escapeHtml(group.label)}</span>
                </span>
                <i class="fas fa-chevron-down role-access-preview-chevron" aria-hidden="true"></i>
              </button>
              <ul class="role-access-preview-subnav ${groupOpen ? "" : "is-collapsed"}">
                ${childItems.map((item) => `
                  <li>
                    <button type="button"
                            class="${item.key === state.preview.activeKey ? "is-active" : ""}"
                            data-preview-key="${escapeHtml(item.key)}">
                      ${escapeHtml(item.label)}
                    </button>
                  </li>
                `).join("")}
              </ul>
            </li>
          `;
        }).join("");

        return `
          <li class="role-access-preview-section-title">${escapeHtml(section)}</li>
          ${groupsHtml}
        `;
      }).join("")
    }</ul>`;

    previewNavEl.innerHTML = html;
    previewNavEl.querySelectorAll(".role-access-preview-toggle").forEach((button) => {
      button.addEventListener("click", () => {
        const groupKey = String(button.getAttribute("data-preview-group") || "").trim();
        if (!groupKey) return;
        state.preview.openGroups[groupKey] = !state.preview.openGroups[groupKey];
        renderPreviewNav();
      });
    });
    previewNavEl.querySelectorAll("[data-preview-key]").forEach((button) => {
      button.addEventListener("click", () => {
        const key = String(button.getAttribute("data-preview-key") || "").trim();
        setPreviewActiveItem(key);
      });
    });
  };

  const setPreviewActiveItem = (key) => {
    const item = state.preview.items.find((entry) => entry.key === key) || null;
    state.preview.activeKey = item ? item.key : "";
    updatePreviewFrame(item);
    renderPreviewNav();
  };

  const openPreviewModalFromRow = (row) => {
    if (!row || !previewModalEl) return;

    const normalizedRow = {
      ...row,
      department_display: String(row.department_display || row.department || "").trim(),
      position_display: String(row.position_display || row.position_access || "").trim(),
    };
    const permissionMap = buildPermissionMapFromRow(normalizedRow);
    const previewItems = getPreviewItemsFromPermissionMap(permissionMap, normalizedRow);
    const initialItem = previewItems.find((item) => item.key === "dashboard") || previewItems[0] || null;

    state.preview.row = normalizedRow;
    state.preview.items = previewItems;
    state.preview.activeKey = initialItem ? initialItem.key : "";
    state.preview.search = "";
    state.preview.openGroups = {};

    if (previewSearchInput) previewSearchInput.value = "";
    if (previewTitleEl) previewTitleEl.textContent = `Preview Access - ${safe(normalizedRow.position_display)}`;
    if (previewScopeEl) previewScopeEl.textContent = `${safe(normalizedRow.department_display)} - ${safe(normalizedRow.position_display)}`;
    if (previewMetaEl) {
      const moduleCount = previewItems.length;
      const personnelCount = Number(normalizedRow.personnel_count || 0);
      previewMetaEl.textContent = `${moduleCount} granted module${moduleCount === 1 ? "" : "s"} - ${personnelCount} personnel covered`;
    }

    updatePreviewFrame(initialItem);
    renderPreviewNav();
    bootstrap.Modal.getOrCreateInstance(previewModalEl).show();
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
    const selectedKeys = Array.isArray(row.permission_keys)
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
    const customPosition = String(createPositionCustomInput?.value || "").replace(/\s+/g, " ").trim();
    const selectedPosition = String(createPositionSelect?.value || "").trim();
    const position = customPosition || selectedPosition;
    if (!department || !position) {
      createHintEl.textContent = "Choose an existing position or type a new title. New profiles start with the current default permissions already checked.";
      return;
    }

    const existingRow = findRowByScope(department, position);
    createHintEl.textContent = existingRow
      ? "This department and position already has a profile. Continue to manage the existing permissions."
      : "This will open a new role profile with the default permissions preselected. Review the module checklist before saving.";
  };

  const openCreateScopeModal = () => {
    if (!createModalEl) return;
    populatePromptSelect(createDepartmentSelect, state.editorOptions.departments, "Select department");
    renderCreatePositionOptions("");
    if (createPositionCustomInput) createPositionCustomInput.value = "";
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
    body.append("csrf_token", csrfToken);

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
    if (state.modal.mode === "create" && permissionKeys.length === 0) {
      throw new Error("Select at least one module or tracker before creating this access profile.");
    }
    await postProfileAction("save_profile_permissions", {
      department,
      position_access: positionAccess,
      permission_keys: permissionKeys,
    });
  };

  const resetProfile = async () => {
    const row = state.modal.row;
    if (!row) return false;
    if (!(await confirmAction("Reset this position permission profile back to the default permissions?", {
      title: "Confirm Permission Reset",
      confirmLabel: "Reset",
      confirmClass: "btn btn-danger",
    }))) return false;

    await postProfileAction("reset_profile_permissions", {
      department: row.department || "",
      position_access: row.position_access || "",
    });
    return true;
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
      if (createPositionCustomInput) createPositionCustomInput.value = "";
      updateCreateHint();
    });
  }

  if (createPositionCustomInput) {
    createPositionCustomInput.addEventListener("input", () => {
      if (String(createPositionCustomInput.value || "").trim() !== "" && createPositionSelect) {
        createPositionSelect.value = "";
      }
      updateCreateHint();
    });
  }

  if (createContinueBtn) {
    createContinueBtn.addEventListener("click", () => {
      const department = String(createDepartmentSelect?.value || "").trim();
      const customPosition = String(createPositionCustomInput?.value || "").replace(/\s+/g, " ").trim();
      const positionAccess = customPosition || String(createPositionSelect?.value || "").trim();
      if (!department || !positionAccess) {
        notify("Choose the department and either an existing position or a new title first.", "warning", "Missing Profile Scope");
        return;
      }
      if (!validAccessLabel(positionAccess)) {
        notify("Enter a valid title. Use letters, numbers, spaces, and common punctuation only.", "warning", "Invalid Title");
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

  if (previewSearchInput) {
    previewSearchInput.addEventListener("input", () => {
      state.preview.search = String(previewSearchInput.value || "").trim();
      renderPreviewNav();
    });
  }

  if (previewReloadBtn) {
    previewReloadBtn.addEventListener("click", () => {
      const item = state.preview.items.find((entry) => entry.key === state.preview.activeKey) || null;
      if (!item || !previewFrameEl) return;
      previewFrameEl.setAttribute("src", item.url);
    });
  }

  if (previewFrameEl) {
    previewFrameEl.addEventListener("load", () => {
      preparePreviewFrameDocument();
    });
  }

  if (previewModalEl) {
    previewModalEl.addEventListener("hidden.bs.modal", () => {
      state.preview.row = null;
      state.preview.items = [];
      state.preview.activeKey = "";
      state.preview.search = "";
      state.preview.openGroups = {};
      if (previewSearchInput) previewSearchInput.value = "";
      if (previewFrameEl) previewFrameEl.setAttribute("src", "about:blank");
    });
  }

  if (saveBtn) {
    saveBtn.addEventListener("click", async () => {
      try {
        const row = state.modal.row;
        const permissionKeys = Object.keys(state.modal.permissionMap || {});
        if (!row) return;
        if (state.modal.mode === "create" && permissionKeys.length === 0) {
          notify("Select at least one module or tracker before creating this access profile.", "warning", "No Modules Selected");
          return;
        }
        const actionLabel = state.modal.mode === "create" ? "Create" : "Save";
        const confirmed = await confirmAction(
          `${actionLabel} permissions for ${safe(row.position_display || row.position_access)} under ${safe(row.department_display || row.department)} with ${permissionKeys.length} granted module${permissionKeys.length === 1 ? "" : "s"}?`,
          {
            title: `Confirm ${actionLabel}`,
            confirmLabel: actionLabel,
          }
        );
        if (!confirmed) return;

        setLoadingState(true);
        await saveProfile();
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        await load();
        notify("Access control profile saved successfully.", "success", "Permissions Saved");
      } catch (error) {
        notify(error?.message || "Unable to save access control changes.", "danger", "Save Failed");
      } finally {
        setLoadingState(false);
      }
    });
  }

  if (resetBtn) {
    resetBtn.addEventListener("click", async () => {
      try {
        setLoadingState(true);
        const didReset = await resetProfile();
        if (!didReset) return;
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        await load();
        notify("Access control profile reset to default permissions.", "success", "Permissions Reset");
      } catch (error) {
        notify(error?.message || "Unable to reset access control profile.", "danger", "Reset Failed");
      } finally {
        setLoadingState(false);
      }
    });
  }

  load();
})();
