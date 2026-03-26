document.addEventListener("DOMContentLoaded", () => {
    const searchInput = document.getElementById("searchInput");
    const btnApplyFilter = document.getElementById("btnApplyFilter");
    const btnResetModal = document.getElementById("btnResetModalFilters");
	    const btnRefreshTable = document.getElementById("btnArchiveRefresh");
    const entriesPerPageInput = document.getElementById("archiveEntriesPerPageInput");
    const paginationEl = document.getElementById("archivePagination");

    let allArchivedResidents = [];
    let activeFilters = {};
    let searchValue = "";
    let currentPage = 1;
    let entriesPerPage = Math.max(1, Number.parseInt(entriesPerPageInput?.value || "20", 10) || 20);

    const AUTO_REFRESH_MS = 30000;
	    let autoRefreshTimeout = null;
	    let autoRefreshInFlight = false;

	    const setRefreshLoading = (on) => {
	        if (!btnRefreshTable) return;
	        btnRefreshTable.classList.toggle("is-loading", !!on);
	        btnRefreshTable.disabled = !!on;
	    };

    fetchArchivedResidents();

    if (searchInput) {
        searchInput.addEventListener("input", () => {
            searchValue = searchInput.value.trim().toLowerCase();
            currentPage = 1;
            applyFiltersAndRender();
        });
    }

    if (btnApplyFilter) {
        btnApplyFilter.addEventListener("click", () => {
            const checkedBoxes = document.querySelectorAll(".filter-checkbox:checked");
            const filters = {};

            checkedBoxes.forEach(cb => {
                const field = cb.dataset.field;
                if (!filters[field]) filters[field] = [];
                filters[field].push(cb.value);
            });

            activeFilters = filters;
            currentPage = 1;
            applyFiltersAndRender();

            const modalEl = document.getElementById("modalFilter");
            if (modalEl) {
                const filterModal = bootstrap.Modal.getInstance(modalEl);
                if (filterModal) filterModal.hide();
            }
        });
    }

    if (btnResetModal) {
        btnResetModal.addEventListener("click", () => {
            document.querySelectorAll(".filter-checkbox").forEach(cb => cb.checked = false);
            activeFilters = {};
            currentPage = 1;
            applyFiltersAndRender();
        });
    }

	    function fetchArchivedResidents() {
	        if (autoRefreshInFlight) return;
	        autoRefreshInFlight = true;
	        setRefreshLoading(true);
	        fetch("../PhpFiles/Admin-End/archiveResident.php")
	            .then(response => response.json())
	            .then(data => {
	                allArchivedResidents = Array.isArray(data) ? data : [];
	                applyFiltersAndRender();
	            })
	            .catch(error => console.error("Error:", error))
	            .finally(() => {
	                autoRefreshInFlight = false;
	                setRefreshLoading(false);
	            });
	    }

    const scheduleAutoRefresh = () => {
        if (autoRefreshTimeout) clearTimeout(autoRefreshTimeout);
        autoRefreshTimeout = setTimeout(() => {
            if (autoRefreshInFlight) {
                scheduleAutoRefresh();
                return;
            }
            triggerRefresh();
        }, AUTO_REFRESH_MS);
    };

    const triggerRefresh = () => {
        scheduleAutoRefresh();
        fetchArchivedResidents();
    };

    if (btnRefreshTable) {
        btnRefreshTable.addEventListener("click", triggerRefresh);
    }

    function applyFiltersAndRender() {
        let filtered = allArchivedResidents;

        if (searchValue) {
            filtered = filtered.filter(r => {
                const idMatch = String(r.resident_id ?? "").toLowerCase().includes(searchValue);
                const nameMatch = String(r.full_name ?? "").toLowerCase().includes(searchValue);
                return idMatch || nameMatch;
            });
        }

        for (const field in activeFilters) {
            const allowed = activeFilters[field];
            filtered = filtered.filter(r => allowed.includes(String(r[field] ?? "")));
        }

        renderTable(filtered);
    }

    function renderTable(data) {
        const tableBody = document.getElementById("tableBody");
        tableBody.innerHTML = "";
        const rows = Array.isArray(data) ? data : [];
        const totalPages = Math.max(1, Math.ceil(rows.length / entriesPerPage));
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;
        const start = (currentPage - 1) * entriesPerPage;
        const pageRows = rows.slice(start, start + entriesPerPage);
        renderPagination(totalPages, rows.length);

        if (!pageRows.length) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-muted">No archived residents found</td>
                </tr>
            `;
            return;
        }

        pageRows.forEach(resident => {
            const archivedDate = resident.archived_at ?? "N/A";
            tableBody.innerHTML += `
                <tr>
                    <td>${resident.resident_id}</td>
                    <td>${resident.full_name}</td>
                    <td><span class="badge bg-secondary">Archived</span></td>
                    <td>${archivedDate}</td>
                    <td>
                        <button class="btn btn-sm btn-primary" 
                            onclick="restoreResident(${resident.resident_id})">
                            Restore
                        </button>
                        <button class="btn btn-sm btn-danger ms-1"
                            onclick="deleteResident(${resident.resident_id})">
                            Delete
                        </button>
                    </td>
                </tr>
            `;
        });
    }

    function renderPagination(totalPages, totalRows) {
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
                applyFiltersAndRender();
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
        for (let p = startPage; p <= endPage; p += 1) {
            addBtn(String(p), p, false, p === currentPage);
        }
        addBtn(">", Math.min(totalPages, currentPage + 1), currentPage >= totalPages, false);
    }

    if (entriesPerPageInput) {
        entriesPerPageInput.addEventListener("change", () => {
            const next = Math.max(1, Number.parseInt(entriesPerPageInput.value || "20", 10) || 20);
            entriesPerPage = next;
            entriesPerPageInput.value = String(next);
            currentPage = 1;
            applyFiltersAndRender();
        });
    }

    window.fetchArchivedResidents = fetchArchivedResidents;
    scheduleAutoRefresh();
});

function restoreResident(residentId) {
    if (!confirm("Restore this resident?")) return;

    fetch("../PhpFiles/Admin-End/archiveResidentActions.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "restore", resident_id: residentId })
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message || "Resident restored.");
        if (window.fetchArchivedResidents) window.fetchArchivedResidents();
    });
}

function deleteResident(residentId) {
    if (!confirm("Permanently delete this resident? This cannot be undone.")) return;

    fetch("../PhpFiles/Admin-End/archiveResidentActions.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "delete", resident_id: residentId })
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message || "Resident deleted.");
        if (window.fetchArchivedResidents) window.fetchArchivedResidents();
    });
}
