document.addEventListener("DOMContentLoaded", () => {
    const searchInput = document.getElementById("searchInput");
    const btnApplyFilter = document.getElementById("btnApplyFilter");
    const btnResetModal = document.getElementById("btnResetModalFilters");
	    const btnRefreshTable = document.getElementById("btnArchiveRefresh");
    const entriesPerPageInput = document.getElementById("archiveEntriesPerPageInput");
    const paginationEl = document.getElementById("archivePagination");
    const csrfToken = String(window.ADMIN_RESIDENT_ARCHIVE_CSRF_TOKEN || "").trim();

    const escapeHtml = (value) => String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");

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

        tableBody.innerHTML = pageRows.map((resident) => {
            const residentId = String(resident.resident_id ?? "");
            const archivedDate = resident.archived_at ?? "N/A";
            return `
                <tr>
                    <td>${escapeHtml(residentId)}</td>
                    <td>${escapeHtml(resident.full_name)}</td>
                    <td><span class="badge bg-secondary">Archived</span></td>
                    <td>${escapeHtml(archivedDate)}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-primary"
                            data-archive-action="restore" data-resident-id="${escapeHtml(residentId)}">
                            Restore
                        </button>
                        <button type="button" class="btn btn-sm btn-danger ms-1"
                            data-archive-action="delete" data-resident-id="${escapeHtml(residentId)}">
                            Delete
                        </button>
                    </td>
                </tr>
            `;
        }).join("");
    }

    document.getElementById("tableBody")?.addEventListener("click", (event) => {
        if (!(event.target instanceof Element)) return;
        const button = event.target.closest("[data-archive-action][data-resident-id]");
        if (!button) return;
        const residentId = String(button.dataset.residentId || "").trim();
        if (!residentId) return;
        if (button.dataset.archiveAction === "restore") {
            window.restoreResident(residentId);
        } else if (button.dataset.archiveAction === "delete") {
            window.deleteResident(residentId);
        }
    });

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
    window.ADMIN_RESIDENT_ARCHIVE_CSRF_TOKEN = csrfToken;
    scheduleAutoRefresh();
});

async function restoreResident(residentId) {
    if (!(await UniversalModal.confirm("Restore this resident and the linked user account?", { confirmLabel: "Restore", confirmClass: "btn btn-success" }))) return;

    try {
        const res = await fetch("../PhpFiles/Admin-End/archiveResidentActions.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": String(window.ADMIN_RESIDENT_ARCHIVE_CSRF_TOKEN || ""),
            },
            body: JSON.stringify({ action: "restore", resident_id: residentId })
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) throw new Error(data.message || "Unable to restore the resident.");
        alert(data.message || "Resident restored.");
        if (window.fetchArchivedResidents) window.fetchArchivedResidents();
    } catch (error) {
        alert(error?.message || "Unable to restore the resident.");
    }
}

async function deleteResident(residentId) {
    if (!(await UniversalModal.confirm("Permanently delete this resident? This cannot be undone.", { confirmLabel: "Delete", confirmClass: "btn btn-danger" }))) return;

    try {
        const res = await fetch("../PhpFiles/Admin-End/archiveResidentActions.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": String(window.ADMIN_RESIDENT_ARCHIVE_CSRF_TOKEN || ""),
            },
            body: JSON.stringify({ action: "delete", resident_id: residentId })
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) throw new Error(data.message || "Unable to delete the resident.");
        alert(data.message || "Resident deleted.");
        if (window.fetchArchivedResidents) window.fetchArchivedResidents();
    } catch (error) {
        alert(error?.message || "Unable to delete the resident.");
    }
}
