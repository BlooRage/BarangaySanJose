document.addEventListener("DOMContentLoaded", () => {
  const normalizeSubdivisionLabel = (value) => {
    const text = String(value || "").trim();
    if (!text) return "";
    const cleaned = text
      .replace(/\bsubdivision\b/gi, "")
      .replace(/\bsubd\.?\b/gi, "")
      .replace(/\s+/g, " ")
      .trim();
    return cleaned ? `${cleaned} Subdivision` : "";
  };

  const normalizePhaseLabel = (value) => {
    const text = String(value || "").trim();
    if (!text) return "";
    const cleaned = text.replace(/^\s*(phase|ph)\.?\s*/i, "").replace(/\s+/g, " ").trim();
    return cleaned ? `Phase ${cleaned}` : "";
  };

  const normalizeStreetLabel = (value) => {
    const text = String(value || "").trim();
    if (!text) return "";
    return /\bst(?:reet)?\.?$/i.test(text) ? text : `${text} Street`;
  };

  const normalizeLotLabel = (value) => {
    const text = String(value || "").trim().replace(/^\s*lot\s*/i, "").replace(/\s+/g, " ").trim();
    return text ? `Lot ${text}` : "";
  };

  const normalizeBlockLabel = (value) => {
    const text = String(value || "")
      .trim()
      .replace(/^\s*block\s*/i, "")
      .replace(/^\s*blk\.?\s*/i, "")
      .replace(/\s+/g, " ")
      .trim();
    return text ? `Block ${text}` : "";
  };

  const isLotBlockAddress = (details) => {
    const houseNumber = String(details?.house_number || "").trim();
    const phaseNumber = String(details?.phase_number || "").trim();
    const streetName = String(details?.street_name || "").trim();
    return /^\s*lot\b/i.test(houseNumber)
      || /^\s*(block|blk\.?)\b/i.test(phaseNumber)
      || /^\s*(block|blk\.?)\b/i.test(streetName)
      || (houseNumber !== "" && phaseNumber !== "" && streetName === "");
  };

  const buildAddressSummary = (details) => {
    if (!details) return "";

    if (isLotBlockAddress(details)) {
      const primary = [
        normalizeLotLabel(details.house_number),
        normalizeBlockLabel(details.phase_number),
      ].filter(Boolean).join(" ");

      const trailing = [];
      const streetName = String(details.street_name || "").trim();
      if (streetName) {
        if (/^\s*(phase|ph)\b/i.test(streetName) || /^\s*\d+[a-z]?\s*$/i.test(streetName)) {
          trailing.push(normalizePhaseLabel(streetName));
        } else {
          trailing.push(streetName);
        }
      }
      const subdivision = normalizeSubdivisionLabel(details.subdivision);
      if (subdivision) trailing.push(subdivision);

      return [primary, trailing.join(", ")].filter(Boolean).join(", ");
    }

    const streetLine = [
      String(details.house_number || "").trim(),
      normalizeStreetLabel(details.street_name),
    ].filter(Boolean).join(" ");

    return [
      details.unit_number ? `Unit ${details.unit_number}` : "",
      streetLine,
      normalizePhaseLabel(details.phase_number),
      normalizeSubdivisionLabel(details.subdivision),
    ].filter(Boolean).join(", ");
  };

  const tbody = document.getElementById("hofTbody");
  const searchInput = document.getElementById("hofSearch");
  const refreshBtn = document.getElementById("btnHofRefresh");
  const statusButtons = document.querySelectorAll(".status-filter-btn");
  const statusFilterSelect = document.getElementById("hofStatusFilterSelect");
  const btnFilterApply = document.getElementById("btnHofFilterApply");
  const btnFilterReset = document.getElementById("btnHofFilterReset");
  const areaFilterList = document.getElementById("hofAreaFilterList");
  const sectorFilterList = document.getElementById("hofSectorFilterList");
  const pendingBadge = document.getElementById("pendingHofBadge");
  const entriesPerPageInput = document.getElementById("hofEntriesPerPageInput");
  const paginationEl = document.getElementById("hofPagination");

  const approveModalAddress = document.getElementById("approveAddressDisplay");
  const approveApplicantsBody = document.getElementById("approveApplicantsBody");
  const approveGroupKey = document.getElementById("approveGroupKey");
  const approveError = document.getElementById("approveHeadError");
  const btnConfirmApprove = document.getElementById("btnConfirmApproveHead");
  const viewModalEl = document.getElementById("modalHofView");
  const viewAddressEl = document.getElementById("hofViewAddress");
  const viewAddressMetaEl = document.getElementById("hofViewAddressMeta");
  const viewApplicantsEl = document.getElementById("hofViewApplicants");
  const declineModalEl = document.getElementById("modalDeclineHead");
  const declineAddressDisplay = document.getElementById("declineAddressDisplay");
  const btnConfirmDecline = document.getElementById("btnConfirmDeclineHead");

  let rowsRaw = [];
  let activeStatus = "ALL";
  let activeAreaFilters = [];
  let activeSectorFilters = [];
  let currentPage = 1;
  let entriesPerPage = Math.max(1, Number.parseInt(entriesPerPageInput?.value || "20", 10) || 20);

  let inFlight = false;
  let pendingDeclineGroupKey = "";
  const AUTO_REFRESH_MS = 30000;
  let autoRefreshTimeout = null;
  const OFFICIAL_AREA_OPTIONS = ["Area 01", "Area 1A", "Area 02", "Area 03", "Area 04", "Area 05", "Area 06"];
  const OFFICIAL_SECTOR_OPTIONS = ["PWD", "Senior Citizen", "Student", "Indigenous People", "Single Parent"];

  const setRefreshLoading = (on) => {
    if (!refreshBtn) return;
    refreshBtn.classList.toggle("is-loading", !!on);
    refreshBtn.disabled = !!on;
  };

  const safe = (v) => {
    const s = String(v ?? "").trim();
    return s !== "" ? s : "-";
  };

  const parseCsvValues = (value) => Array.from(new Set(
    String(value ?? "")
      .split(",")
      .map((item) => item.trim())
      .filter(Boolean)
  ));

  const normalizeSectorLabel = (value) => {
    const raw = String(value ?? "").trim();
    if (!raw) return "";
    const normalized = raw.toLowerCase().replace(/[^a-z]/g, "");
    const map = {
      pwd: "PWD",
      seniorcitizen: "Senior Citizen",
      student: "Student",
      indigenouspeople: "Indigenous People",
      indigenousperson: "Indigenous People",
      singleparent: "Single Parent",
      soloparent: "Single Parent",
    };
    return map[normalized] || raw;
  };

  const parseSectorValues = (value) => Array.from(new Set(
    parseCsvValues(value)
      .map((item) => normalizeSectorLabel(item))
      .filter(Boolean)
  ));

  const renderChecklist = (container, field, values, activeValues) => {
    if (!container) return;
    const list = Array.isArray(values) ? values : [];
    if (!list.length) {
      container.innerHTML = `<div class="text-muted small">No options available.</div>`;
      return;
    }
    const active = new Set(Array.isArray(activeValues) ? activeValues : []);
    container.innerHTML = list.map((value, index) => `
      <label class="d-flex align-items-center gap-2">
        <input class="form-check-input m-0 hof-filter-checkbox" type="checkbox" value="${String(value).replace(/"/g, "&quot;")}" data-field="${field}" id="hofFilter_${field}_${index}" ${active.has(value) ? "checked" : ""}>
        <span>${value}</span>
      </label>
    `).join("");
  };

  const syncFilterOptions = () => {
    const areas = OFFICIAL_AREA_OPTIONS.slice();
    const sectors = OFFICIAL_SECTOR_OPTIONS.slice();

    activeAreaFilters = activeAreaFilters.filter((value) => areas.includes(String(value || "").trim()));
    activeSectorFilters = activeSectorFilters
      .map((value) => normalizeSectorLabel(value))
      .filter((value) => sectors.includes(value));

    renderChecklist(areaFilterList, "area_number", areas, activeAreaFilters);
    renderChecklist(sectorFilterList, "sector_membership", sectors, activeSectorFilters);
  };

  const statusPill = (status) => {
    const key = String(status || "").toLowerCase() === "approved"
      ? "approved"
      : (String(status || "").toLowerCase() === "declined" ? "declined" : "pending");
    const text = key === "approved" ? "Approved" : (key === "declined" ? "Declined" : "Pending");
    return `<span class="status-pill ${key}">${text}</span>`;
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
        if (disabled || page === currentPage) return;
        currentPage = page;
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

    addBtn("<", Math.max(1, currentPage - 1), currentPage <= 1, false);
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);
    for (let p = startPage; p <= endPage; p += 1) addBtn(String(p), p, false, p === currentPage);
    addBtn(">", Math.min(totalPages, currentPage + 1), currentPage >= totalPages, false);
  };

  const filteredRows = () => {
    const q = (searchInput?.value || "").trim().toLowerCase();
    let rows = [...rowsRaw];

    if (activeStatus !== "ALL") {
      rows = rows.filter((r) => String(r.verification_status || "Pending") === activeStatus);
    }

    if (activeAreaFilters.length) {
      rows = rows.filter((r) => activeAreaFilters.includes(String(r.area_number || "").trim()));
    }

    if (activeSectorFilters.length) {
      rows = rows.filter((r) => {
        const households = Array.isArray(r?.households) ? r.households : [];
        const memberships = households.flatMap((household) => parseSectorValues(household?.sector_membership));
        return activeSectorFilters
          .map((value) => normalizeSectorLabel(value))
          .some((value) => memberships.includes(value));
      });
    }

    if (q) {
      rows = rows.filter((r) => {
        const text = `${r.group_key || ""} ${r.address_id || ""} ${r.address_display || ""} ${r.area_number || ""} ${r.verification_status || ""} ${r.decided_by_user_id || ""}`.toLowerCase();
        return text.includes(q);
      });
    }
    return rows;
  };

  const updatePendingBadge = () => {
    if (!pendingBadge) return;
    const count = rowsRaw.filter((r) => String(r.verification_status || "Pending") === "Pending").length;
    pendingBadge.textContent = String(count);
    pendingBadge.classList.toggle("d-none", count <= 0);
  };

  const renderTable = () => {
    if (!tbody) return;
    const rows = filteredRows();
    const totalPages = Math.max(1, Math.ceil(rows.length / entriesPerPage));
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;
    const start = (currentPage - 1) * entriesPerPage;
    const pageRows = rows.slice(start, start + entriesPerPage);
    renderPagination(totalPages, rows.length);

    tbody.innerHTML = "";
    if (!pageRows.length) {
      tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">No records found.</td></tr>`;
      return;
    }

    pageRows.forEach((row) => {
      const status = String(row.verification_status || "Pending");
      const canAct = status === "Pending";
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td title="${safe(row.address_display)}">${safe(row.address_display)}</td>
        <td>${safe(row.area_number)}</td>
        <td>${Number(row.household_count ?? 0)}</td>
        <td>${statusPill(status)}</td>
        <td>${safe(row.decided_by_user_id)}</td>
        <td>${safe(row.decided_at)}</td>
        <td>
          <div class="compact-table-actions">
            <button type="button" class="btn btn-outline-secondary btn-sm compact-table-btn btn-view">View</button>
            <button type="button" class="btn btn-success btn-sm compact-table-btn btn-approve" ${canAct ? "" : "disabled"}>Approve</button>
            <button type="button" class="btn btn-danger btn-sm compact-table-btn btn-decline" ${canAct ? "" : "disabled"}>Decline</button>
          </div>
        </td>
      `;

      tr.querySelector(".btn-view")?.addEventListener("click", () => openViewModal(row));
      tr.querySelector(".btn-approve")?.addEventListener("click", () => openApproveModal(row));
      tr.querySelector(".btn-decline")?.addEventListener("click", () => openDeclineModal(row));
      tbody.appendChild(tr);
    });
  };

  const activateStatusButton = (status) => {
    statusButtons.forEach((b) => {
      const isActive = (b.dataset.filter || "ALL") === status;
      b.classList.toggle("active", isActive);
      if (isActive) {
        b.classList.add("btn-outline-primary");
        b.classList.remove("btn-outline-secondary");
      } else {
        b.classList.remove("btn-outline-primary");
        b.classList.add("btn-outline-secondary");
      }
    });
    if (statusFilterSelect) statusFilterSelect.value = status;
  };

  const fetchRows = async () => {
    if (inFlight) return;
    inFlight = true;
    setRefreshLoading(true);
    try {
      const res = await fetch("../PhpFiles/Admin-End/headOfFamilyApplications.php?fetch=1", {
        headers: { Accept: "application/json" }
      });
      const data = await res.json().catch(() => ([]));
      if (!res.ok || !Array.isArray(data)) throw new Error("Failed to load head applications.");
      rowsRaw = data;
      syncFilterOptions();
      updatePendingBadge();
      renderTable();
    } catch (err) {
      if (tbody) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${err?.message || "Failed to load records."}</td></tr>`;
      }
    } finally {
      inFlight = false;
      setRefreshLoading(false);
    }
  };

  const triggerRefresh = () => {
    scheduleAutoRefresh();
    fetchRows().catch(() => {});
  };

  const scheduleAutoRefresh = () => {
    if (autoRefreshTimeout) clearTimeout(autoRefreshTimeout);
    autoRefreshTimeout = window.setTimeout(() => {
      if (inFlight) {
        scheduleAutoRefresh();
        return;
      }
      triggerRefresh();
    }, AUTO_REFRESH_MS);
  };

  const openApproveModal = (row) => {
    if (!row || !Array.isArray(row.households) || !row.households.length) return;
    approveModalAddress.textContent = row.address_display || row.address_id || "-";
    approveGroupKey.value = row.group_key || "";
    approveError.classList.add("d-none");
    approveApplicantsBody.innerHTML = "";

    row.households.forEach((h, idx) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td><input class="form-check-input" type="radio" name="approvedResident" value="${h.resident_id || ""}" ${idx === 0 ? "checked" : ""}></td>
        <td>${safe(h.resident_id)}</td>
        <td>${safe(h.head_full_name)}</td>
        <td>${Number(h.member_count ?? 1)}</td>
      `;
      approveApplicantsBody.appendChild(tr);
    });

    const modalEl = document.getElementById("modalApproveHead");
    if (!modalEl) return;
    bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: "static", keyboard: false }).show();
  };

  const openViewModal = (row) => {
    if (!row) return;
    if (viewAddressEl) viewAddressEl.textContent = safe(row.address_display);
    if (viewAddressMetaEl) {
      const details = row.address_details || {};
      const parts = [buildAddressSummary(details)].filter(Boolean);
      const meta = [
        details.house_type ? `House Type: ${details.house_type}` : "",
        details.house_ownership ? `Ownership: ${details.house_ownership}` : "",
        details.residency_duration ? `Residency: ${details.residency_duration}` : ""
      ].filter(Boolean);
      viewAddressMetaEl.textContent = [...parts, ...meta].join(" • ");
    }

    if (viewApplicantsEl) {
      viewApplicantsEl.innerHTML = "";
      const households = Array.isArray(row.households) ? row.households : [];
      households.forEach((member) => {
        const card = document.createElement("div");
        card.className = "col-12";
        const voterRaw = String(member.voter_status ?? "").trim();
        const voterLabel = voterRaw === "" ? "-" : (voterRaw === "1" ? "Registered" : "Not Registered");
        const occupation = [member.occupation, member.occupation_detail].filter((v) => String(v || "").trim() !== "").join(" - ");
        card.innerHTML = `
          <div class="hof-view-card">
            <div class="d-flex flex-wrap justify-content-between gap-2">
              <div class="fw-bold">${safe(member.head_full_name)}</div>
              <div class="text-muted small">Resident ID: ${safe(member.resident_id)}</div>
            </div>
            <div class="row g-2 mt-2">
              <div class="col-md-4">
                <div class="hof-view-label">Sex</div>
                <div class="hof-view-value">${safe(member.sex)}</div>
              </div>
              <div class="col-md-4">
                <div class="hof-view-label">Birthdate</div>
                <div class="hof-view-value">${safe(member.birthdate)}</div>
              </div>
              <div class="col-md-4">
                <div class="hof-view-label">Birthplace</div>
                <div class="hof-view-value">${safe(member.birthplace)}</div>
              </div>
              <div class="col-md-4">
                <div class="hof-view-label">Barangay Residency</div>
                <div class="hof-view-value">${safe(member.barangay_residency)}</div>
              </div>
              <div class="col-md-4">
                <div class="hof-view-label">Civil Status</div>
                <div class="hof-view-value">${safe(member.civil_status)}</div>
              </div>
              <div class="col-md-4">
                <div class="hof-view-label">Family Role</div>
                <div class="hof-view-value">${safe(member.family_role)}</div>
              </div>
              <div class="col-md-4">
                <div class="hof-view-label">Voter Status</div>
                <div class="hof-view-value">${safe(voterLabel)}</div>
              </div>
              <div class="col-md-4">
                <div class="hof-view-label">Occupation</div>
                <div class="hof-view-value">${safe(occupation)}</div>
              </div>
              <div class="col-md-4">
                <div class="hof-view-label">Religion</div>
                <div class="hof-view-value">${safe(member.religion)}</div>
              </div>
              <div class="col-md-4">
                <div class="hof-view-label">Sector Membership</div>
                <div class="hof-view-value">${safe(member.sector_membership)}</div>
              </div>
            </div>
          </div>
        `;
        viewApplicantsEl.appendChild(card);
      });
    }

    if (viewModalEl && window.bootstrap?.Modal) {
      bootstrap.Modal.getOrCreateInstance(viewModalEl, { backdrop: "static", keyboard: false }).show();
    }
  };

  const approveGroup = async () => {
    const groupKey = approveGroupKey.value.trim();
    const selected = document.querySelector("input[name='approvedResident']:checked");
    const approvedResidentId = selected ? String(selected.value || "").trim() : "";
    if (!groupKey || !approvedResidentId) {
      approveError.classList.remove("d-none");
      return;
    }
    approveError.classList.add("d-none");
    btnConfirmApprove.disabled = true;
    try {
      const res = await fetch("../PhpFiles/Admin-End/headOfFamilyApplications.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "approve_head_group",
          group_key: groupKey,
          approved_resident_id: approvedResidentId
        })
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.success) throw new Error(data.message || "Failed to approve application.");
      const modalEl = document.getElementById("modalApproveHead");
      const modal = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;
      if (modal) modal.hide();
      triggerRefresh();
    } catch (err) {
      alert(err?.message || "Failed to approve application.");
    } finally {
      btnConfirmApprove.disabled = false;
    }
  };

  const openDeclineModal = (row) => {
    if (!row?.group_key || !declineModalEl || !window.bootstrap?.Modal) return;
    pendingDeclineGroupKey = String(row.group_key || "").trim();
    if (declineAddressDisplay) {
      declineAddressDisplay.textContent = safe(row.address_display || row.address_id);
    }
    if (btnConfirmDecline) {
      btnConfirmDecline.disabled = false;
    }
    bootstrap.Modal.getOrCreateInstance(declineModalEl, { backdrop: "static", keyboard: false }).show();
  };

  const declineGroup = async () => {
    const groupKey = String(pendingDeclineGroupKey || "").trim();
    if (!groupKey) return;
    if (btnConfirmDecline) {
      btnConfirmDecline.disabled = true;
    }
    try {
      const res = await fetch("../PhpFiles/Admin-End/headOfFamilyApplications.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "decline_head_group",
          group_key: groupKey
        })
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.success) throw new Error(data.message || "Failed to decline application.");
      const modal = declineModalEl ? bootstrap.Modal.getInstance(declineModalEl) : null;
      if (modal) modal.hide();
      pendingDeclineGroupKey = "";
      triggerRefresh();
    } catch (err) {
      alert(err?.message || "Failed to decline application.");
    } finally {
      if (btnConfirmDecline) {
        btnConfirmDecline.disabled = false;
      }
    }
  };

  declineModalEl?.addEventListener("hidden.bs.modal", () => {
    pendingDeclineGroupKey = "";
    if (declineAddressDisplay) {
      declineAddressDisplay.textContent = "-";
    }
    if (btnConfirmDecline) {
      btnConfirmDecline.disabled = false;
    }
  });

  refreshBtn?.addEventListener("click", triggerRefresh);
  btnConfirmApprove?.addEventListener("click", approveGroup);
  btnConfirmDecline?.addEventListener("click", declineGroup);
  btnFilterApply?.addEventListener("click", () => {
    activeStatus = statusFilterSelect?.value || "ALL";
    activeAreaFilters = Array.from(document.querySelectorAll('.hof-filter-checkbox[data-field="area_number"]:checked')).map((checkbox) => String(checkbox.value || "").trim());
    activeSectorFilters = Array.from(document.querySelectorAll('.hof-filter-checkbox[data-field="sector_membership"]:checked')).map((checkbox) => normalizeSectorLabel(String(checkbox.value || "").trim()));
    currentPage = 1;
    activateStatusButton(activeStatus);
    renderTable();
  });
  btnFilterReset?.addEventListener("click", () => {
    activeStatus = "ALL";
    activeAreaFilters = [];
    activeSectorFilters = [];
    document.querySelectorAll(".hof-filter-checkbox").forEach((checkbox) => {
      checkbox.checked = false;
    });
    currentPage = 1;
    activateStatusButton(activeStatus);
    renderTable();
  });

  statusButtons.forEach((btn) => {
    btn.addEventListener("click", () => {
      activeStatus = btn.dataset.filter || "ALL";
      currentPage = 1;
      activateStatusButton(activeStatus);
      renderTable();
    });
  });

  if (searchInput) {
    let timer;
    searchInput.addEventListener("input", () => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        currentPage = 1;
        renderTable();
      }, 200);
    });
  }

  if (entriesPerPageInput) {
    const applyEntries = () => {
      const parsed = Number.parseInt(entriesPerPageInput.value || "20", 10);
      entriesPerPage = Math.max(1, Number.isNaN(parsed) ? 20 : parsed);
      entriesPerPageInput.value = String(entriesPerPage);
      currentPage = 1;
      renderTable();
    };
    entriesPerPageInput.addEventListener("input", applyEntries);
    entriesPerPageInput.addEventListener("change", applyEntries);
  }

  triggerRefresh();
  scheduleAutoRefresh();
  activateStatusButton(activeStatus);
});
