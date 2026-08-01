(() => {
  const endpoint = "../PhpFiles/Admin-End/businessMonitoringData.php?kind=commercial";
  const tbody = document.getElementById("establishmentMonitoringTbody");
  const search = document.getElementById("establishmentSearch");
  const refresh = document.getElementById("establishmentRefresh");
  const entries = document.getElementById("establishmentEntries");
  const pagination = document.getElementById("establishmentPagination");
  const state = { rows: [], search: "", page: 1, perPage: 20 };
  const esc = (value) => String(value ?? "").replace(/[&<>"']/g, (c) => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
  const value = (row, ...keys) => keys.map((key) => String(row?.[key] ?? "").trim()).find(Boolean) || "—";

  function filteredRows() {
    return state.rows.filter((row) => {
      const area = value(row, "establishment_area", "area_number");
      if (!state.search) return true;
      return [row.establishment_name, row.applicant_name, row.owner_name, area, row.establishment_address, row.request_id].some((item) => String(item || "").toLowerCase().includes(state.search));
    });
  }

  function renderPagination(totalPages) {
    pagination.innerHTML = "";
    [["<", Math.max(1,state.page-1), state.page===1], [String(state.page), state.page, false], [">", Math.min(totalPages,state.page+1), state.page===totalPages]].forEach(([label,page,disabled]) => {
      const li=document.createElement("li"); li.className=`page-item${disabled?" disabled":""}${label===String(state.page)?" active":""}`;
      const btn=document.createElement("button"); btn.className="page-link"; btn.textContent=label; btn.disabled=disabled; btn.onclick=()=>{state.page=page;render();}; li.appendChild(btn); pagination.appendChild(li);
    });
  }

  function render() {
    const rows = filteredRows();
    const pages = Math.max(1, Math.ceil(rows.length / state.perPage));
    state.page = Math.min(state.page, pages);
    renderPagination(pages);
    const shown = rows.slice((state.page-1)*state.perPage, state.page*state.perPage);
    tbody.innerHTML = shown.length ? shown.map((row) => `<tr><td class="establishment-name">${esc(value(row,"establishment_name"))}</td><td>${esc(value(row,"owner_name","applicant_name"))}</td><td>${esc(value(row,"establishment_area","area_number"))}</td><td>${esc(value(row,"establishment_address","business_address"))}</td><td>${esc(value(row,"request_id"))}</td><td>${esc(value(row,"submitted_at_display","submitted_at"))}</td></tr>`).join("") : '<tr><td colspan="6" class="text-center text-muted py-4">No commercial establishments found.</td></tr>';
  }

  async function load() {
    refresh.disabled = true;
    try {
      const response = await fetch(`${endpoint}&_ts=${Date.now()}`, {headers:{Accept:"application/json"}});
      const data = await response.json();
      if (!response.ok || data.success === false) throw new Error(data.message || "Unable to load establishments.");
      const seen = new Set();
      state.rows = (Array.isArray(data.items) ? data.items : []).filter((row) => {
        const key = `${value(row,"establishment_name").toLowerCase()}|${value(row,"establishment_area","area_number").toLowerCase()}`;
        if (seen.has(key)) return false; seen.add(key); return true;
      });
      render();
    } catch (error) { tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${esc(error.message)}</td></tr>`; }
    finally { refresh.disabled = false; }
  }
  search.addEventListener("input",()=>{state.search=search.value.trim().toLowerCase();state.page=1;render();});
  entries.addEventListener("change",()=>{state.perPage=Math.max(1,parseInt(entries.value,10)||20);state.page=1;render();});
  refresh.addEventListener("click",load);
  load();
})();
