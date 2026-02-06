document.addEventListener("DOMContentLoaded", () => {
    const searchInput = document.getElementById("searchInput");
    const btnApplyFilter = document.getElementById("btnApplyFilter");
    const btnResetModal = document.getElementById("btnResetModalFilters");

    let allArchivedResidents = [];
    let activeFilters = {};
    let searchValue = "";

    fetchArchivedResidents();

    if (searchInput) {
        searchInput.addEventListener("input", () => {
            searchValue = searchInput.value.trim().toLowerCase();
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
            applyFiltersAndRender();
        });
    }

    function fetchArchivedResidents() {
        fetch("../PhpFiles/Admin-End/archiveResident.php")
            .then(response => response.json())
            .then(data => {
                allArchivedResidents = Array.isArray(data) ? data : [];
                applyFiltersAndRender();
            })
            .catch(error => console.error("Error:", error));
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

        if (!data.length) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center text-muted">No archived residents found</td>
                </tr>
            `;
            return;
        }

        data.forEach(resident => {
            const archivedDate = resident.archived_at ?? "N/A";
            tableBody.innerHTML += `
                <tr>
                    <td>${resident.resident_id}</td>
                    <td>${resident.full_name}</td>
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

    window.fetchArchivedResidents = fetchArchivedResidents;
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
