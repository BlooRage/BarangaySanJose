(function () {
    "use strict";

    function initTable(table) {
        if (table.dataset.paginationReady === "true") return;
        table.dataset.paginationReady = "true";

        const shell = table.closest(".compact-admin-table-shell");
        const tbody = table.tBodies[0];
        if (!shell || !tbody) return;

        let currentPage = 1;
        let perPage = 20;
        let rendering = false;
        const controls = document.createElement("div");
        controls.className = "table-pagination-controls";
        controls.innerHTML = '<label class="table-pagination-entries"><span>Entries</span><input type="number" min="1" step="1" value="20" class="form-control form-control-sm resident-entries-input" aria-label="Entries per page"></label><nav aria-label="Table pagination"><ul class="pagination pagination-sm"></ul></nav>';
        shell.insertAdjacentElement("afterend", controls);

        const input = controls.querySelector("input");
        const pagination = controls.querySelector(".pagination");

        function rows() {
            return Array.from(tbody.rows).filter(function (row) {
                return !row.querySelector('td[colspan][class*="py-4"]');
            });
        }

        function render() {
            if (rendering) return;
            rendering = true;
            const tableRows = rows();
            const totalPages = Math.max(1, Math.ceil(tableRows.length / perPage));
            currentPage = Math.min(Math.max(1, currentPage), totalPages);
            const first = (currentPage - 1) * perPage;
            const last = first + perPage;

            tableRows.forEach(function (row, index) {
                row.hidden = index < first || index >= last;
            });

            pagination.innerHTML = "";
            [
                { label: "<", page: currentPage - 1, disabled: currentPage === 1 },
                { label: String(currentPage), page: currentPage, active: true },
                { label: ">", page: currentPage + 1, disabled: currentPage === totalPages }
            ].forEach(function (item) {
                const li = document.createElement("li");
                li.className = "page-item" + (item.active ? " active" : "") + (item.disabled ? " disabled" : "");
                const button = document.createElement("button");
                button.type = "button";
                button.className = "page-link";
                button.textContent = item.label;
                button.disabled = Boolean(item.disabled);
                button.setAttribute("aria-label", item.label === "<" ? "Previous page" : item.label === ">" ? "Next page" : "Current page");
                button.addEventListener("click", function () {
                    if (item.disabled || item.active) return;
                    currentPage = item.page;
                    render();
                });
                li.appendChild(button);
                pagination.appendChild(li);
            });

            controls.hidden = tableRows.length === 0;
            rendering = false;
        }

        input.addEventListener("change", function () {
            perPage = Math.max(1, parseInt(input.value || "20", 10) || 20);
            input.value = String(perPage);
            currentPage = 1;
            render();
        });

        new MutationObserver(function (mutations) {
            if (mutations.some(function (mutation) { return mutation.type === "childList"; })) {
                currentPage = 1;
                render();
            }
        }).observe(tbody, { childList: true, subtree: true });

        render();
    }

    function init() {
        document.querySelectorAll("table[data-table-pagination]").forEach(initTable);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
