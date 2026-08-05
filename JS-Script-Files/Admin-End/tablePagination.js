(function () {
    "use strict";

    function initTable(table) {
        if (table.dataset.paginationReady === "true") return;
        if (table.dataset.tablePagination === "off") return;

        const shell = table.closest(".compact-admin-table-shell");
        const tbody = table.tBodies[0];
        if (!shell || !tbody) return;

        // Trackers such as blotter/complaints paginate on the server and already
        // render their own footer. Never place a second client paginator there.
        const region = shell.parentElement;
        const hasExplicitOptIn = table.hasAttribute("data-table-pagination");
        const hasExistingFooter = region && (
            region.querySelector(".resident-table-footer")
            || region.querySelector(".table-pagination-controls")
            || region.querySelector("ul.pagination[id]")
        );
        if (!hasExplicitOptIn && hasExistingFooter) return;

        table.dataset.paginationReady = "true";

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
            const items = [{ label: "Prev", page: currentPage - 1, disabled: currentPage === 1 }];
            let startPage = Math.max(1, currentPage - 2);
            let endPage = Math.min(totalPages, startPage + 4);
            startPage = Math.max(1, endPage - 4);

            if (startPage > 1) {
                items.push({ label: "1", page: 1, active: currentPage === 1 });
                if (startPage > 2) items.push({ label: "…", disabled: true, ellipsis: true });
            }
            for (let page = startPage; page <= endPage; page += 1) {
                items.push({ label: String(page), page: page, active: page === currentPage });
            }
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) items.push({ label: "…", disabled: true, ellipsis: true });
                items.push({ label: String(totalPages), page: totalPages, active: currentPage === totalPages });
            }
            items.push({ label: "Next", page: currentPage + 1, disabled: currentPage === totalPages });

            items.forEach(function (item) {
                const li = document.createElement("li");
                li.className = "page-item" + (item.active ? " active" : "") + (item.disabled ? " disabled" : "");
                if (item.ellipsis) {
                    const span = document.createElement("span");
                    span.className = "page-link";
                    span.textContent = item.label;
                    li.appendChild(span);
                } else {
                    const button = document.createElement("button");
                    button.type = "button";
                    button.className = "page-link";
                    button.textContent = item.label;
                    button.disabled = Boolean(item.disabled);
                    button.setAttribute("aria-label", item.label === "Prev" ? "Previous page" : item.label === "Next" ? "Next page" : `Page ${item.label}`);
                    button.addEventListener("click", function () {
                        if (item.disabled || item.active) return;
                        currentPage = item.page;
                        render();
                    });
                    li.appendChild(button);
                }
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
        document.querySelectorAll("#main-display .table-responsive > table.table, main .table-responsive > table.table").forEach(function (table) {
            if (table.closest(".modal, .rp-page, .rp-report, [data-admin-table-style='off']")) return;
            table.classList.add("compact-admin-table");
            const wrapper = table.parentElement;
            if (wrapper) wrapper.classList.add("compact-admin-table-shell");
        });
        document.querySelectorAll("#main-display table.compact-admin-table, main table.compact-admin-table, table[data-table-pagination]").forEach(initTable);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
