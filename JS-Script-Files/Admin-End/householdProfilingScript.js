document.addEventListener("DOMContentLoaded", () => {
  const tbody = document.getElementById("tableBody");
  const searchInput = document.getElementById("searchInput");
  const btnApplyFilter = document.getElementById("btnApplyFilter");
  const btnResetModal = document.getElementById("btnResetModalFilters");

  let allHeads = [];

  // ========================
  // FETCH HEADS OF FAMILY
  // ========================
  function fetchHeads(search = "") {
    const url = `../PhpFiles/Admin-End/householdProfiling.php?fetch=true&search=${encodeURIComponent(search)}`;
    fetch(url)
      .then(res => res.json())
      .then(data => {
        allHeads = Array.isArray(data) ? data : [];
        renderTable(allHeads);
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
        <td class="fw-bold">${row.resident_id}</td>
        <td>${row.full_name ?? "—"}</td>
        <td>${row.address_display ?? "—"}</td>
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
      const filters = {};
      checkedBoxes.forEach(cb => {
        const field = cb.dataset.field;
        if (!filters[field]) filters[field] = [];
        filters[field].push(cb.value);
      });

      const filtered = allHeads.filter(res => {
        for (const field in filters) {
          if (!filters[field].includes(String(res[field]))) return false;
        }
        return true;
      });

      renderTable(filtered);

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
    const setList = (id, items) => {
      const el = document.getElementById(id);
      if (!el) return;
      el.innerHTML = "";
      if (!Array.isArray(items) || items.length === 0) return;
      items.forEach(item => {
        const li = document.createElement("li");
        const ageText = item.age !== null && item.age !== undefined ? `${item.age}` : "—";
        li.innerText = `${item.name ?? "—"} - ${ageText}`;
        el.appendChild(li);
      });
    };

    setText("span-displayID", `#${data.resident_id}`);

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
    setText("txt-householdHeadName", data.head_full_name ?? data.full_name ?? "—");
    setText("txt-householdAdultCount", data.adult_count ?? "0");
    setText("txt-householdMinorCount", data.minor_count ?? "0");
    setList("list-householdAdults", data.adults);
    setList("list-householdMinors", data.minors);

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
    const famHeadInput = document.getElementById("add-famHeadId");
    if (famHeadInput) famHeadInput.value = data.resident_id;

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
});
