document.addEventListener("DOMContentLoaded", () => {
  const tbody = document.getElementById("tableBody");
  const searchInput = document.getElementById("searchInput");
  const btnApplyFilter = document.getElementById("btnApplyFilter");
  const btnResetModal = document.getElementById("btnResetModalFilters");
  const filterHouseholdCountInput = document.getElementById("filterHouseholdCountInput");
  const displayModeAddresses = document.getElementById("displayModeAddresses");
  const displayModeHeads = document.getElementById("displayModeHeads");
  const btnRefreshTable = document.getElementById("btnHouseholdRefresh");
  const countdownEl = document.getElementById("householdAutoRefreshCountdown");

  let allAddresses = [];
  let activeAreaFilters = [];
  let activeHouseholdCountFilter = "";
  let activeDisplayMode = "addresses";

  const AUTO_REFRESH_SECONDS = 60;
  let autoRefreshSecondsLeft = AUTO_REFRESH_SECONDS;
  let autoRefreshInterval = null;
  let autoRefreshInFlight = false;

  const setRefreshLoading = (on) => {
    if (!btnRefreshTable) return;
    btnRefreshTable.classList.toggle("is-loading", !!on);
    btnRefreshTable.disabled = !!on;
  };

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

    if (activeDisplayMode === "addresses" && activeHouseholdCountFilter !== "") {
      const selectedCount = Number(activeHouseholdCountFilter);
      filtered = filtered.filter(item => Number(item.household_count ?? 0) === selectedCount);
    }

    return filtered;
  }

  // ========================
  // FETCH HEADS OF FAMILY
  // ========================
  function fetchHeads(search = "") {
    const url = `../PhpFiles/Admin-End/householdProfiling.php?fetch=true&mode=${encodeURIComponent(activeDisplayMode)}&search=${encodeURIComponent(search)}`;
    if (autoRefreshInFlight) return;
    autoRefreshInFlight = true;
    setRefreshLoading(true);
    fetch(url)
      .then(res => res.json())
      .then(data => {
        allAddresses = Array.isArray(data) ? data : [];
        renderTable(getFilteredAddresses());
      })
      .catch(err => console.error("Fetch error:", err))
      .finally(() => {
        autoRefreshInFlight = false;
        setRefreshLoading(false);
      });
  }
  fetchHeads();

  const renderCountdown = () => {
    if (!countdownEl) return;
    countdownEl.textContent = autoRefreshSecondsLeft > 0 ? `Auto refresh in ${autoRefreshSecondsLeft}s` : "";
  };

  const resetCountdown = () => {
    autoRefreshSecondsLeft = AUTO_REFRESH_SECONDS;
    renderCountdown();
  };

  const triggerRefresh = () => {
    resetCountdown();
    fetchHeads(searchInput ? searchInput.value.trim() : "");
  };

  if (btnRefreshTable) {
    btnRefreshTable.addEventListener("click", triggerRefresh);
  }

  const startAutoRefresh = () => {
    renderCountdown();
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
    autoRefreshInterval = setInterval(() => {
      if (autoRefreshInFlight) return;
      autoRefreshSecondsLeft -= 1;
      if (autoRefreshSecondsLeft <= 0) {
        triggerRefresh();
        return;
      }
      renderCountdown();
    }, 1000);
  };

  startAutoRefresh();

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

    const col1 = document.getElementById("th-col-1");
    const col2 = document.getElementById("th-col-2");
    const col3 = document.getElementById("th-col-3");
    if (activeDisplayMode === "heads") {
      if (col1) col1.textContent = "Resident ID";
      if (col2) col2.textContent = "Household Head";
      if (col3) col3.textContent = "Address";
    } else {
      if (col1) col1.textContent = "Address ID";
      if (col2) col2.textContent = "Address";
      if (col3) col3.textContent = "Households";
    }

    data.forEach(row => {
      const tr = document.createElement("tr");
      const col1Value = activeDisplayMode === "heads" ? (row.resident_id ?? "—") : (row.address_id ?? "—");
      const col2Value = activeDisplayMode === "heads" ? (row.head_full_name ?? "—") : (row.address_display ?? "—");
      const col3Value = activeDisplayMode === "heads" ? (row.address_display ?? "—") : (row.household_count ?? 0);
      tr.innerHTML = `
        <td class="fw-bold">${col1Value}</td>
        <td>${col2Value}</td>
        <td>${col3Value}</td>
        <td>
          <button type="button" class="btn btn-primary btn-sm text-white viewEntryBtn">View</button>
        </td>
      `;

      const viewBtn = tr.querySelector(".viewEntryBtn");
      if (viewBtn) {
        viewBtn.addEventListener("click", () => openViewEntry(row));
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
      activeDisplayMode = displayModeHeads && displayModeHeads.checked ? "heads" : "addresses";
      const checkedBoxes = document.querySelectorAll(".filter-checkbox:checked");
      activeAreaFilters = Array.from(checkedBoxes).map(cb => cb.value);
      activeHouseholdCountFilter = filterHouseholdCountInput ? filterHouseholdCountInput.value.trim() : "";
      if (activeDisplayMode === "heads") {
        activeHouseholdCountFilter = "";
        if (filterHouseholdCountInput) {
          filterHouseholdCountInput.value = "";
          filterHouseholdCountInput.disabled = true;
        }
      } else if (filterHouseholdCountInput) {
        filterHouseholdCountInput.disabled = false;
      }
      if (searchInput) {
        searchInput.placeholder = activeDisplayMode === "heads"
          ? "Resident ID, Name, or Address"
          : "Address ID or Address Name";
      }
      fetchHeads(searchInput ? searchInput.value.trim() : "");
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
      if (displayModeAddresses) displayModeAddresses.checked = true;
      if (displayModeHeads) displayModeHeads.checked = false;
      activeDisplayMode = "addresses";
      if (filterHouseholdCountInput) filterHouseholdCountInput.disabled = false;
      if (searchInput) searchInput.placeholder = "Address ID or Address Name";
      fetchHeads(searchInput ? searchInput.value.trim() : "");
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
          wrapper.className = "border rounded-3 p-3 mb-4 bg-light";
          wrapper.innerHTML = `
            <p class="text-muted small mb-1">Head of the Family:</p>
            <h6 class="fw-bold mb-3">${hh.head_full_name ?? "—"}</h6>
            <div class="row g-2">
              <div class="col-md-6">
                <p class="text-muted small mb-0">Adults:</p>
                <p class="fw-bold mb-0">${hh.adult_count ?? 0}</p>
                <ul class="small mb-0 ps-3">${adultItems}</ul>
              </div>
              <div class="col-md-6">
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

    new bootstrap.Modal(document.getElementById("modal-viewHousehold"), {
      backdrop: "static",
      keyboard: true
    }).show();
  }
});


