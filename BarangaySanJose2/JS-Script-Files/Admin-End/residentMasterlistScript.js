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

document.querySelectorAll(".status-filter-btn").forEach(btn => {
  btn.addEventListener("click", () => {
    // Remove 'active' class from all buttons
    document.querySelectorAll(".status-filter-btn").forEach(b => b.classList.remove("active"));

    // Add 'active' to clicked button
    btn.classList.add("active");

    // Set activeFilter to the DB status value
    activeFilter = btn.dataset.filter;
    applyFilterAndRender();
  });
});

const btnApplyFilter = document.getElementById("btnApplyFilter");

btnApplyFilter.addEventListener("click", () => {
  const checkedBoxes = document.querySelectorAll(".filter-checkbox:checked");

  // Prepare filter object: { field: [values...] }
  const filters = {};
  checkedBoxes.forEach(cb => {
    const field = cb.dataset.field;
    if (!filters[field]) filters[field] = [];
    filters[field].push(cb.value);
  });

  // Filter function
  const filtered = allResidents.filter(res => {
    for (const field in filters) {
      if (!filters[field].includes(String(res[field]))) return false;
    }
    return true;
  });

  // Render filtered table
  renderTable(filtered);

  // Close modal
  const filterModal = bootstrap.Modal.getInstance(document.getElementById("modalFilter"));
  filterModal.hide();
});


  function renderTable(data) {
    tbody.innerHTML = "";

    if (!data.length) {
      tbody.innerHTML = `
        <tr>
          <td colspan="4" class="text-center text-muted">No records found</td>
        </tr>`;
      return;
    }

    data.forEach(row => {
      const badge =
        row.status === "VerifiedResident" ? "success" :
        row.status === "PendingVerification" ? "warning text-dark" :
        row.status === "NotVerified" ? "danger" :
        "secondary";

      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td class="fw-bold">${row.resident_id}</td>
        <td>${row.full_name}</td>
        <td><span class="badge bg-${badge}">${statusDisplayMap[row.status] ?? "UNSET"}</span></td>
        <td>
          <button type="button" class="btn btn-primary btn-sm text-white viewEntryBtn">
            View Profile
          </button>
        </td>
      `;

      tr.querySelector(".viewEntryBtn")
        .addEventListener("click", () => openViewEntry(row));

      tbody.appendChild(tr);
    });
  }

  function applyFilterAndRender() {
    if (activeFilter === "ALL") {
      renderTable(allResidents);
    } else {
      renderTable(allResidents.filter(r => r.status === activeFilter));
    }
  }

  document.querySelectorAll(".a-sidebarLink[data-filter]").forEach(link => {
    link.addEventListener("click", e => {
      e.preventDefault();

      document.querySelectorAll(".a-sidebarLink")
        .forEach(l => l.classList.remove("active"));

      link.classList.add("active");

      activeFilter = link.dataset.filter;
      applyFilterAndRender();
    });
  });

  let searchTimeout;
  if (searchInput) {
    searchInput.addEventListener("input", () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        fetchResidents(searchInput.value.trim());
      }, 300);
    });
  }

  // ==========================
  // MODAL FUNCTION (FIXED)
  // ==========================
  function openViewEntry(data) {
    document.getElementById("input-appId").value = data.resident_id;
    document.getElementById("span-displayID").innerText = "Resident #" + data.resident_id;

    document.getElementById("txt-modalName").innerText = data.full_name ?? "—";
    document.getElementById("txt-modalDob").innerText = data.birthdate ?? "—";
    document.getElementById("txt-modalSex").innerText = data.sex ?? "—";
    document.getElementById("txt-modalCivilStatus").innerText = data.civil_status ?? "—";
    document.getElementById("txt-modalHeadOfFam").innerText = Number(data.head_of_family) === 1 ? "Yes" : "No";
    document.getElementById("txt-modalVoterStatus").innerText = Number(data.voter_status) === 1 ? "Registered" : "Not Registered";

    // ✅ IMPORTANT FIX:
    // Show JOB TITLE if employed, else Unemployed (comes from PHP occupation_display)
    document.getElementById("txt-modalOccupation").innerText =
      (data.occupation_display && String(data.occupation_display).trim() !== "")
        ? data.occupation_display
        : "Unemployed";

    document.getElementById("txt-modalReligion").innerText = data.religion ?? "—";
    document.getElementById("txt-modalSectorMembership").innerText = data.sector_membership ?? "—";

    document.getElementById("txt-modalEmergencyFullName").innerText = data.emergency_full_name ?? "—";
    document.getElementById("txt-modalEmergencyContactNumber").innerText = data.emergency_contact_number ?? "—";
    document.getElementById("txt-modalEmergencyRelationship").innerText = data.emergency_relationship ?? "—";
    document.getElementById("txt-modalEmergencyAddress").innerText = data.emergency_address ?? "—";

    document.getElementById("txt-modalHouseNum").innerText = data.house_number ?? "—";
    document.getElementById("txt-modalStreetName").innerText = data.street_name ?? "—";
    document.getElementById("txt-modalSubdivision").innerText = data.subdivision ?? "—";
    document.getElementById("txt-modalAreaNumber").innerText = data.area_number ?? "—";

    document.getElementById("txt-modalBarangay").innerText = "Barangay San Jose";
    document.getElementById("txt-modalMunicipalityCity").innerText = "Rodriguez (Montalban)";
    document.getElementById("txt-modalProvince").innerText = "Rizal";

    document.getElementById("txt-modalHouseOwnership").innerText = data.house_ownership ?? "—";
    document.getElementById("txt-modalHouseType").innerText = data.house_type ?? "—";
    document.getElementById("txt-modalResidencyDuration").innerText = data.residency_duration ?? "—";

    const statusToUi = {
      "PendingVerification": "PENDING",
      "VerifiedResident": "APPROVED",
      "NotVerified": "DENIED"
    };
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

    new bootstrap.Modal(document.getElementById("modal-viewEntry")).show();
  }

  const selectStatus = document.getElementById("select-newStatus");
  if (selectStatus) {
    selectStatus.addEventListener("change", () => {
      const denialDiv = document.getElementById("div-denialOptions");
      if (!denialDiv) return;

      if (selectStatus.value === "DENIED") denialDiv.classList.remove("div-hide");
      else denialDiv.classList.add("div-hide");
    });
  }

  const radioOthers = document.getElementById("radio-others");
  if (radioOthers) {
    radioOthers.addEventListener("change", () => {
      const otherBox = document.getElementById("textarea-otherReason");
      if (!otherBox) return;

      if (radioOthers.checked) otherBox.classList.remove("div-hide");
      else otherBox.classList.add("div-hide");
    });
  }

  // confirmation before submitting status update
  const formUpdate = document.getElementById("form-updateStatus");

  if (formUpdate) {
    formUpdate.addEventListener("submit", (e) => {
    const confirmed = confirm("Are you sure you want to save this status?");
    if (!confirmed) {
      e.preventDefault(); // stops the form from submitting
    }
  });
  }

  const btnResetModal = document.getElementById("btnResetModalFilters");

btnResetModal.addEventListener("click", () => {
  // Uncheck all filter checkboxes
  document.querySelectorAll(".filter-checkbox").forEach(cb => cb.checked = false);
});
});