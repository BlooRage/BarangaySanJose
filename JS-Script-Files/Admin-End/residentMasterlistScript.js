document.addEventListener("DOMContentLoaded", () => {
  const tbody = document.getElementById("tableBody");
  const searchInput = document.getElementById("searchInput");

  const statusDisplayMap = {
    "VerifiedResident": "Verified Resident",
    "PendingVerification": "Pending Verification",
    "NotVerified": "Not Verified"
  };

  let allResidents = [];
  let activeFilter = "ALL";

  // ========================
  // FETCH RESIDENTS
  // ========================
  function fetchResidents(search = "") {
    const url = `../PhpFiles/Admin-End/residentMasterlist.php?fetch=true&search=${encodeURIComponent(search)}`;
    fetch(url)
      .then(res => res.json())
      .then(data => {
        allResidents = Array.isArray(data) ? data : [];
        applyFilterAndRender();
      })
      .catch(err => console.error("Fetch error:", err));
  }
  fetchResidents();

  // ========================
  // FILTER BUTTONS
  // ========================
  document.querySelectorAll(".status-filter-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      document.querySelectorAll(".status-filter-btn").forEach(b => b.classList.remove("active"));
      btn.classList.add("active");
      activeFilter = btn.dataset.filter;
      applyFilterAndRender();
    });
  });

  // ========================
  // FILTER MODAL
  // ========================
  const btnApplyFilter = document.getElementById("btnApplyFilter");
  btnApplyFilter.addEventListener("click", () => {
    const checkedBoxes = document.querySelectorAll(".filter-checkbox:checked");
    const filters = {};
    checkedBoxes.forEach(cb => {
      const field = cb.dataset.field;
      if (!filters[field]) filters[field] = [];
      filters[field].push(cb.value);
    });

    const filtered = allResidents.filter(res => {
      for (const field in filters) {
        if (!filters[field].includes(String(res[field]))) return false;
      }
      return true;
    });

    renderTable(filtered);

    const filterModal = bootstrap.Modal.getInstance(document.getElementById("modalFilter"));
    filterModal.hide();
  });

  // ========================
  // TABLE RENDER
  // ========================
  function renderTable(data) {
    tbody.innerHTML = "";
    if (!data.length) {
      tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted">No records found</td></tr>`;
      return;
    }

    data.forEach(row => {
      const badge =
        row.status === "VerifiedResident" ? "success" :
        row.status === "PendingVerification" ? "warning text-dark" :
        row.status === "NotVerified" ? "danger" : "secondary";

      const tr = document.createElement("tr");
      const canEditOrArchive = row.status !== "NotVerified" && row.status !== "PendingVerification";
      tr.innerHTML = `
        <td class="fw-bold">${row.resident_id}</td>
        <td>${row.full_name}</td>
        <td><span class="badge bg-${badge}">${statusDisplayMap[row.status] ?? "UNSET"}</span></td>
        <td class="d-flex gap-1">
          <button type="button" class="btn btn-primary btn-sm text-white viewEntryBtn">View</button>
          ${canEditOrArchive ? `<button type="button" class="btn btn-secondary btn-sm text-white editEntryBtn">Edit</button>` : ""}
          ${canEditOrArchive ? `<button type="button" class="btn btn-warning btn-sm text-dark archiveEntryBtn">Archive</button>` : ""}
        </td>
      `;

      tr.querySelector(".viewEntryBtn").addEventListener("click", () => openViewEntry(row));
      const editBtn = tr.querySelector(".editEntryBtn");
      if (editBtn) editBtn.addEventListener("click", () => openEditEntry(row));
      const archiveBtn = tr.querySelector(".archiveEntryBtn");
      if (archiveBtn) archiveBtn.addEventListener("click", () => archiveEntry(row));

      tbody.appendChild(tr);
    });
  }

  function applyFilterAndRender() {
    if (activeFilter === "ALL") renderTable(allResidents);
    else renderTable(allResidents.filter(r => r.status === activeFilter));
  }

  // ========================
  // SEARCH
  // ========================
  let searchTimeout;
  if (searchInput) {
    searchInput.addEventListener("input", () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => fetchResidents(searchInput.value.trim()), 300);
    });
  }

 // ========================
// VIEW ENTRY MODAL
// ========================
function openViewEntry(data) {
  document.getElementById("input-appId").value = data.resident_id;
  document.getElementById("span-displayID").innerText = "Resident #" + data.resident_id;

  // Personal Info
  document.getElementById("txt-modalName").innerText = data.full_name ?? "—";
  document.getElementById("txt-modalDob").innerText = data.birthdate ?? "—";
  document.getElementById("txt-modalSex").innerText = data.sex ?? "—";
  document.getElementById("txt-modalCivilStatus").innerText = data.civil_status ?? "—";
  document.getElementById("txt-modalHeadOfFam").innerText = data.head_of_family === 1 ? "Yes" : "No";
  document.getElementById("txt-modalVoterStatus").innerText = data.voter_status === 1 ? "Registered" : "Not Registered";
  document.getElementById("txt-modalOccupation").innerText = data.occupation_display || "Unemployed";
  document.getElementById("txt-modalReligion").innerText = data.religion ?? "—";
  document.getElementById("txt-modalSectorMembership").innerText = data.sector_membership ?? "—";

  // Emergency Info
  document.getElementById("txt-modalEmergencyFullName").innerText = data.emergency_full_name ?? "—";
  document.getElementById("txt-modalEmergencyContactNumber").innerText = data.emergency_contact_number ?? "—";
  document.getElementById("txt-modalEmergencyRelationship").innerText = data.emergency_relationship ?? "—";
  document.getElementById("txt-modalEmergencyAddress").innerText = data.emergency_address ?? "—";

  // Address Info
  document.getElementById("txt-modalHouseNum").innerText = data.house_number ?? "—";
  document.getElementById("txt-modalStreetName").innerText = data.street_name ?? "—";
  document.getElementById("txt-modalSubdivision").innerText = data.subdivision ?? "—";
  document.getElementById("txt-modalAreaNumber").innerText = data.area_number ?? "—";

  // Read-only
  document.getElementById("txt-modalBarangay").innerText = "Barangay San Jose";
  document.getElementById("txt-modalMunicipalityCity").innerText = "Rodriguez (Montalban)";
  document.getElementById("txt-modalProvince").innerText = "Rizal";

  // House Info
  document.getElementById("txt-modalHouseOwnership").innerText = data.house_ownership ?? "—";
  document.getElementById("txt-modalHouseType").innerText = data.house_type ?? "—";
  document.getElementById("txt-modalResidencyDuration").innerText = data.residency_duration ?? "—";

  // Status Banner
  const statusToUi = { "PendingVerification": "PENDING", "VerifiedResident": "APPROVED", "NotVerified": "DENIED" };
  document.getElementById("select-newStatus").value = statusToUi[data.status] ?? "PENDING";

  const banner = document.getElementById("div-statusBanner");
  banner.innerText = statusDisplayMap[data.status] ?? "UNSET";
  banner.className = "mb-3";
  banner.classList.add(
    data.status === "VerifiedResident" ? "bg-statusApproved" :
    data.status === "PendingVerification" ? "bg-statusPending" :
    data.status === "NotVerified" ? "bg-statusDenied" :
    "bg-statusUnset"
  );

  // STATIC MODAL: cannot close by click outside or Esc
  new bootstrap.Modal(document.getElementById("modal-viewEntry"), {
    backdrop: 'static',
    keyboard: true
  }).show();
}

// ========================
// EDIT ENTRY MODAL
// ========================
function openEditEntry(data) {
  const modalEl = document.getElementById("modal-editEntry");
  const form = modalEl ? modalEl.querySelector("form") : null;

  document.getElementById("edit-residentId").value = data.resident_id;

  // Names
  document.getElementById("edit-lastname").value = data.lastname ?? "";
  document.getElementById("edit-firstname").value = data.firstname ?? "";
  document.getElementById("edit-middlename").value = data.middlename ?? "";
  document.getElementById("edit-suffix").value = data.suffix ?? "";

  // Personal Info
  document.getElementById("edit-birthdate").value = data.birthdate ?? "";
  document.getElementById("edit-sex").value = data.sex ?? "";
  document.getElementById("edit-civil").value = data.civil_status ?? "";
  document.getElementById("edit-religion").value = data.religion ?? "";
  document.getElementById("edit-voterStatus").value = data.voter_status ?? "";
  document.getElementById("edit-occupation").value = data.occupation_display ?? "";

  // Sector Membership
  const sectors = (data.sector_membership ?? "").split(",");
  document.querySelectorAll("#modal-editEntry input[name='sectorMembership[]']").forEach(cb => {
    cb.checked = sectors.includes(cb.value);
  });

  // Emergency Contact
  document.getElementById("edit-userId").value = data.user_id;
  document.getElementById("edit-ec-firstname").value = data.emergency_first_name ?? "";
  document.getElementById("edit-ec-lastname").value = data.emergency_last_name ?? "";
  document.getElementById("edit-ec-middlename").value = data.emergency_middle_name ?? "";
  document.getElementById("edit-ec-suffix").value = data.emergency_suffix ?? "";
  document.getElementById("edit-ec-contact").value = data.emergency_contact_number ?? "";
  document.getElementById("edit-ec-address").value = data.emergency_address ?? "";
  document.getElementById("edit-ec-relationship").value = data.emergency_relationship ?? "";

  // ========================
  // INIT BOOTSTRAP MODAL (STATIC)
  // ========================
  const editModal = new bootstrap.Modal(modalEl, {
    backdrop: 'static',  // cannot close by clicking outside
    keyboard: true      // cannot close by pressing Esc
  });
  editModal.show();

  // ========================
  // CONFIRM CLOSE BUTTON
  // ========================
  const closeBtn = modalEl.querySelector(".btn-close"); // assumes you have <button class="btn-close"></button>
  if (closeBtn) {
    closeBtn.addEventListener("click", e => {
      if (!form || !hasFormChanges(form) || confirm("You have unsaved changes. Are you sure you want to close?")) {
        editModal.hide();
      }
    });
  }

  // ========================
  // CONFIRM SAVE CHANGES
  // ========================
  if (form && !form.dataset.confirmBound) {
    form.addEventListener("submit", e => {
      if (!confirm("Are you sure you want to save these changes?")) {
        e.preventDefault(); // cancel submission if user says no
      }
    });
    form.dataset.confirmBound = "1";
  }

  // ========================
  // SAVE BUTTON ENABLE/DISABLE (ONLY IF CHANGED)
  // ========================
  if (form) {
    const saveBtn = form.querySelector("button[type='submit']");
    const setSaveState = () => {
      if (!saveBtn) return;
      const current = getFormState(form);
      const original = JSON.parse(form.dataset.originalState || "{}");
      saveBtn.disabled = JSON.stringify(current) === JSON.stringify(original);
    };

    form.dataset.originalState = JSON.stringify(getFormState(form));
    setSaveState();

    if (!form.dataset.changeBound) {
      form.addEventListener("input", setSaveState);
      form.addEventListener("change", setSaveState);
      form.dataset.changeBound = "1";
    }
  }
}

function getFormState(form) {
  const state = {};
  const checkboxGroups = {};

  const elements = Array.from(form.elements).filter(el => {
    if (!el.name && !el.id) return false;
    if (el.type === "button" || el.type === "submit" || el.type === "reset") return false;
    if (el.type === "hidden") return false;
    return true;
  });

  elements.forEach(el => {
    const key = el.name || el.id;
    if (el.type === "checkbox") {
      if (!checkboxGroups[key]) checkboxGroups[key] = [];
      if (el.checked) checkboxGroups[key].push(el.value);
      return;
    }

    const value = typeof el.value === "string" ? el.value.trim() : el.value;
    state[key] = value ?? "";
  });

  Object.keys(checkboxGroups).forEach(key => {
    const values = checkboxGroups[key].slice().sort();
    state[key] = values;
  });

  return state;
}

function hasFormChanges(form) {
  const original = JSON.parse(form.dataset.originalState || "{}");
  const current = getFormState(form);
  return JSON.stringify(current) !== JSON.stringify(original);
}

  // ========================
  // "Other" toggle handlers
  // ========================
  document.querySelectorAll(".toggle-other").forEach(sel => {
    sel.addEventListener("change", () => {
      const targetId = sel.dataset.target;
      const target = document.getElementById(targetId);
      if (!target) return;
      if (sel.value === "Other") target.classList.remove("d-none");
      else target.classList.add("d-none");
    });
  });

  // ========================
  // STATUS DENIAL UI
  // ========================
  const selectStatus = document.getElementById("select-newStatus");
  if (selectStatus) {
    selectStatus.addEventListener("change", () => {
      const denialDiv = document.getElementById("div-denialOptions");
      if (!denialDiv) return;
      selectStatus.value === "DENIED" ? denialDiv.classList.remove("div-hide") : denialDiv.classList.add("div-hide");
    });
  }

  const radioOthers = document.getElementById("radio-others");
  if (radioOthers) {
    radioOthers.addEventListener("change", () => {
      const otherBox = document.getElementById("textarea-otherReason");
      if (!otherBox) return;
      radioOthers.checked ? otherBox.classList.remove("div-hide") : otherBox.classList.add("div-hide");
    });
  }

  // ========================
  // CONFIRMATION BEFORE SAVE
  // ========================
  const formUpdate = document.getElementById("form-updateStatus");
  if (formUpdate) {
    formUpdate.addEventListener("submit", e => {
      if (!confirm("Are you sure you want to save this status?")) e.preventDefault();
    });
  }

  // ========================
  // RESET FILTER MODAL
  // ========================
  const btnResetModal = document.getElementById("btnResetModalFilters");
  if (btnResetModal) {
    btnResetModal.addEventListener("click", () => document.querySelectorAll(".filter-checkbox").forEach(cb => cb.checked = false));
  }

  // ========================
  // ARCHIVE RESIDENT
  // ========================
  function archiveEntry(row) {
    if (!confirm(`Archive Resident #${row.resident_id}? This can be restored later.`)) return;

    fetch("../PhpFiles/Admin-End/residentMasterlist.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({
        archive_resident: 1,
        resident_id: row.resident_id
      })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        alert("Resident archived successfully.");
        fetchResidents(searchInput.value.trim());
      } else {
        alert(data.message || "Failed to archive resident.");
      }
    })
    .catch(err => {
      console.error(err);
      alert("Server error.");
    });
  }

});
