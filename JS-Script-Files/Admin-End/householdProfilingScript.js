document.addEventListener("DOMContentLoaded", () => {
  const tbody = document.getElementById("tableBody");
  const searchInput = document.getElementById("searchInput");
  const btnApplyFilter = document.getElementById("btnApplyFilter");
  const btnResetModal = document.getElementById("btnResetModalFilters");
  const filterHouseholdCountInput = document.getElementById("filterHouseholdCountInput");

  let allAddresses = [];
  let activeAreaFilters = [];
  let activeHouseholdCountFilter = "";

  function normalizeText(value) {
    return String(value ?? "").trim().toLowerCase();
  }

  function normalizeArea(value) {
    const raw = normalizeText(value).replace(/^area\s*/, "").replace(/\s+/g, "");
    if (raw === "1a") return "1a";
    const num = parseInt(raw, 10);
    if (!Number.isNaN(num)) return String(num).padStart(2, "0");
    return raw;
  }

  function getFilteredAddresses() {
    let filtered = allAddresses;

    if (activeAreaFilters.length) {
      const selectedAreas = activeAreaFilters.map(normalizeArea);
      filtered = filtered.filter(item => selectedAreas.includes(normalizeArea(item.area_number)));
    }

    if (activeHouseholdCountFilter !== "") {
      const selectedCount = Number(activeHouseholdCountFilter);
      filtered = filtered.filter(item => Number(item.household_count ?? 0) === selectedCount);
    }

    return filtered;
  }

  // ========================
  // FETCH HEADS OF FAMILY
  // ========================
  function fetchHeads(search = "") {
    const url = `../PhpFiles/Admin-End/householdProfiling.php?fetch=true&search=${encodeURIComponent(search)}`;
    fetch(url)
      .then(res => res.json())
      .then(data => {
        allAddresses = Array.isArray(data) ? data : [];
        renderTable(getFilteredAddresses());
      })
      .catch(err => console.error("Fetch error:", err));
  }
  fetchHeads();

  // ========================
  // TABLE RENDER
  // ========================
  function renderTable(data) {
    if (!tbody) return;
    tbody.innerHTML = "";

    if (!data.length) {
      tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted">No records found</td></tr>`;
      return;
    }

    data.forEach(row => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td class="fw-bold">${row.address_id ?? "—"}</td>
        <td>${row.address_display ?? "—"}</td>
        <td>${row.household_count ?? 0}</td>
        <td>
          <button type="button" class="btn btn-primary btn-sm text-white viewEntryBtn">View</button>
          <button type="button" class="btn btn-success btn-sm text-white addMemberBtn">Add Household Member</button>
        </td>
      `;

      const viewBtn = tr.querySelector(".viewEntryBtn");
      if (viewBtn) {
        viewBtn.addEventListener("click", () => openViewEntry(row));
      }
      const addMemberBtn = tr.querySelector(".addMemberBtn");
      if (addMemberBtn) {
        addMemberBtn.addEventListener("click", () => openAddMember(row));
      }

      tbody.appendChild(tr);
    });
  }

  // ========================
  // SEARCH
  // ========================
  let searchTimeout;
  if (searchInput) {
    searchInput.addEventListener("input", () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => fetchHeads(searchInput.value.trim()), 300);
    });
  }

  // ========================
  // FILTER MODAL
  // ========================
  if (btnApplyFilter) {
    btnApplyFilter.addEventListener("click", () => {
      const checkedBoxes = document.querySelectorAll(".filter-checkbox:checked");
      activeAreaFilters = Array.from(checkedBoxes).map(cb => cb.value);
      activeHouseholdCountFilter = filterHouseholdCountInput ? filterHouseholdCountInput.value.trim() : "";
      renderTable(getFilteredAddresses());

      const filterModal = bootstrap.Modal.getInstance(document.getElementById("modalFilter"));
      if (filterModal) filterModal.hide();
    });
  }

  // ========================
  // RESET FILTER MODAL
  // ========================
  if (btnResetModal) {
    btnResetModal.addEventListener("click", () => {
      document.querySelectorAll(".filter-checkbox").forEach(cb => cb.checked = false);
      activeAreaFilters = [];
      activeHouseholdCountFilter = "";
      if (filterHouseholdCountInput) filterHouseholdCountInput.value = "";
      renderTable(getFilteredAddresses());
    });
  }

  // ========================
  // VIEW MODAL
  // ========================
  function openViewEntry(data) {
    const setText = (id, value) => {
      const el = document.getElementById(id);
      if (el) el.innerText = value ?? "—";
    };
    const renderListItems = (arr) => {
      if (!Array.isArray(arr) || !arr.length) return "";
      return arr.map(m => {
        const ageText = (m && m.age !== null && m.age !== undefined) ? m.age : "—";
        const nameText = (m && m.name) ? m.name : "—";
        return `<li>${nameText} - ${ageText}</li>`;
      }).join("");
    };
    setText("span-displayAddress", data.address_id ?? "—");

    // Address Info
    setText("txt-modalHouseNum", data.house_number ?? "—");
    setText("txt-modalStreetName", data.street_name ?? "—");
    setText("txt-modalPhaseNumber", data.phase_number ?? "—");
    setText("txt-modalSubdivision", data.subdivision ?? "—");
    setText("txt-modalAreaNumber", data.area_number ?? "—");
    setText("txt-modalBarangay", "Barangay San Jose");
    setText("txt-modalMunicipalityCity", "Rodriguez (Montalban)");
    setText("txt-modalProvince", "Rizal");

    // Household Info
    const groups = document.getElementById("div-householdGroups");
    if (groups) {
      groups.innerHTML = "";
      const households = Array.isArray(data.households) ? data.households : [];
      if (!households.length) {
        groups.innerHTML = `<p class="text-muted small mb-0">No households found for this address.</p>`;
      } else {
        households.forEach(hh => {
          const adultItems = renderListItems(hh.adults);
          const minorItems = renderListItems(hh.minors);
          const wrapper = document.createElement("div");
          wrapper.className = "mb-3";
          wrapper.innerHTML = `
            <br>
            <h6 class="fw-bold mb-2">${hh.head_full_name ?? "—"}</h6>
            <div class="row g-2">
              <div class="col-md-4">
                <p class="text-muted small mb-0">Adults:</p>
                <p class="fw-bold mb-0">${hh.adult_count ?? 0}</p>
                <ul class="small mb-0 ps-3">${adultItems}</ul>
              </div>
              <div class="col-md-4">
                <p class="text-muted small mb-0">Minors:</p>
                <p class="fw-bold mb-0">${(hh.member_count ?? 0) - (hh.adult_count ?? 0)}</p>
                <ul class="small mb-0 ps-3">${minorItems}</ul>
              </div>
            </div>
          `;
          groups.appendChild(wrapper);
        });
      }
    }

    // Other residing members (non-household)
    const otherList = document.getElementById("list-otherResidents");
    if (otherList) {
      otherList.innerHTML = "";
      const others = Array.isArray(data.other_residents) ? data.other_residents : [];
      if (!others.length) {
        otherList.innerHTML = `<li class="text-muted">None</li>`;
      } else {
        // store households JSON for reuse in assign buttons
        otherList.dataset.householdsParsed = JSON.stringify(data.households || []);

        others.forEach(o => {
          const ageText = (o && o.age !== null && o.age !== undefined) ? o.age : "—";
          const nameText = (o && o.name) ? o.name : "—";
          const li = document.createElement("li");
          li.className = "d-flex justify-content-between align-items-center mb-1";
          li.innerHTML = `
            <span>${nameText} - ${ageText}</span>
            <button class="btn btn-sm btn-outline-primary assignResidentBtn" data-resident="${o.resident_id ?? ""}">Assign</button>
          `;
          otherList.appendChild(li);
        });
      }
    }

    new bootstrap.Modal(document.getElementById("modal-viewHousehold"), {
      backdrop: "static",
      keyboard: true
    }).show();
  }

  // ========================
  // ADD HOUSEHOLD MEMBER MODAL
  // ========================
  function openAddMember(data) {
    const form = document.getElementById("form-addHouseholdMember");
    if (form) form.reset();
    const famHeadSelect = document.getElementById("add-famHeadId");
    if (famHeadSelect) {
      famHeadSelect.innerHTML = "";
      const households = Array.isArray(data.households) ? data.households : [];
      if (!households.length) {
        const opt = document.createElement("option");
        opt.value = "";
        opt.textContent = "No household heads available";
        famHeadSelect.appendChild(opt);
        famHeadSelect.disabled = true;
      } else {
        famHeadSelect.disabled = false;
        households.forEach((hh, index) => {
          const opt = document.createElement("option");
          opt.value = hh.resident_id ?? "";
          opt.textContent = hh.head_full_name ?? "—";
          if (households.length === 1 || index === 0) {
            opt.selected = true;
          }
          famHeadSelect.appendChild(opt);
        });
      }
    }

    new bootstrap.Modal(document.getElementById("modal-addHouseholdMember"), {
      backdrop: "static",
      keyboard: true
    }).show();
  }

  const addMemberForm = document.getElementById("form-addHouseholdMember");
  if (addMemberForm) {
    addMemberForm.addEventListener("submit", e => {
      if (!confirm("Are you sure you want to add this household member?")) {
        e.preventDefault();
        return;
      }
      e.preventDefault();
      const formData = new FormData(addMemberForm);

      fetch("../PhpFiles/Admin-End/householdProfiling.php", {
        method: "POST",
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          const modal = bootstrap.Modal.getInstance(document.getElementById("modal-addHouseholdMember"));
          if (modal) modal.hide();
          fetchHeads(searchInput ? searchInput.value.trim() : "");
        } else {
          alert(data.message || "Failed to add household member.");
        }
      })
      .catch(err => {
        console.error(err);
        alert("Server error.");
      });
    });
  }

  // ========================
  // ASSIGN OTHER RESIDENT TO HOUSEHOLD
  // ========================
  const assignForm = document.getElementById("form-assignResident");
  const assignModalEl = document.getElementById("modal-assignResident");
  let cachedHouseholds = [];

  document.addEventListener("click", e => {
    const btn = e.target.closest(".assignResidentBtn");
    if (!btn) return;
    const residentId = btn.dataset.resident || "";
    const holder = document.getElementById("list-otherResidents");
    cachedHouseholds = holder && holder.dataset.householdsParsed
      ? JSON.parse(holder.dataset.householdsParsed)
      : [];
    openAssignModal(residentId, cachedHouseholds);
  });

  function openAssignModal(residentId, households) {
    const select = document.getElementById("assign-famHeadSelect");
    const inputResident = document.getElementById("assign-residentId");
    if (!select || !inputResident) return;
    inputResident.value = residentId;
    select.innerHTML = "";
    const list = Array.isArray(households) ? households : [];
    list.forEach((hh, idx) => {
      const opt = document.createElement("option");
      opt.value = hh.resident_id ?? "";
      opt.textContent = hh.head_full_name ?? "—";
      if (idx === 0) opt.selected = true;
      select.appendChild(opt);
    });
    new bootstrap.Modal(assignModalEl, { backdrop: "static", keyboard: true }).show();
  }

  if (assignForm) {
    assignForm.addEventListener("submit", e => {
      e.preventDefault();
      const formData = new FormData(assignForm);
      fetch("../PhpFiles/Admin-End/householdProfiling.php", {
        method: "POST",
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          const residentId = assignForm.querySelector("#assign-residentId")?.value;
          if (residentId) {
            const list = document.getElementById("list-otherResidents");
            const btn = list ? list.querySelector(`.assignResidentBtn[data-resident="${residentId}"]`) : null;
            const li = btn ? btn.closest("li") : null;
            if (li) {
              li.remove();
              if (list && !list.querySelector("li")) {
                list.innerHTML = `<li class="text-muted">None</li>`;
              }
            }
          }
          const modal = bootstrap.Modal.getInstance(assignModalEl);
          if (modal) modal.hide();
          const viewModal = bootstrap.Modal.getInstance(document.getElementById("modal-viewHousehold"));
          if (viewModal) viewModal.hide();
          fetchHeads(searchInput ? searchInput.value.trim() : "");
        } else {
          alert(data.message || "Failed to assign resident.");
        }
      })
      .catch(err => {
        console.error(err);
        alert("Server error.");
      });
    });
  }
});
