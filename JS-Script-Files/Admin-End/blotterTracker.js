(() => {
  const appBase = (() => {
    const marker = '/Admin-End/';
    const idx = window.location.pathname.indexOf(marker);
    if (idx === -1) return '';
    return window.location.pathname.slice(0, idx);
  })();

  const endpoint = `${appBase}/PhpFiles/Admin-End/blotterTrackerData.php`;
  const tableBody = document.getElementById('tableBody');
  const searchInput = document.getElementById('searchInput');
  const entriesPerPageInput = document.getElementById('entriesPerPageInput');
  const paginationEl = document.getElementById('blotterPagination');
  const refreshBtn = document.getElementById('btnBlotterTableRefresh');

  const viewModalEl = document.getElementById('viewModal');
  const viewModal = viewModalEl ? new bootstrap.Modal(viewModalEl) : null;
  const viewModalTitle = document.getElementById('viewModalTitle');
  const viewDetailsBody = document.getElementById('viewDetailsBody');

  let allRows = [];
  let filteredRows = [];
  let currentPage = 1;

  function esc(v) {
    return String(v ?? '').replace(/[&<>\"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '\"': '&quot;', "'": '&#39;' }[m]));
  }

  function formField(label, value, raw = false) {
    const text = String(value ?? '').trim();
    const rendered = raw ? (text || '-') : esc(text || '-');
    return `
      <div class="tracker-form-field">
        <p class="tracker-form-label">${esc(label)}</p>
        <div class="tracker-form-value">${rendered}</div>
      </div>
    `;
  }

  function gridClassByCount(count, maxCols = 4) {
    const n = Math.max(1, Math.min(maxCols, Number(count) || 1));
    if (n >= 4) return 'cols-4';
    if (n === 3) return 'cols-3';
    if (n === 2) return '';
    return 'cols-1';
  }

  function renderFieldGrid(fields, maxCols = 4) {
    const clean = (Array.isArray(fields) ? fields : []).filter((f) => f && String(f.value ?? '').trim() !== '');
    if (!clean.length) return '';
    const cls = gridClassByCount(clean.length, maxCols);
    return `<div class="tracker-form-grid ${cls}">${clean.map((f) => formField(f.label, f.value, !!f.raw)).join('')}</div>`;
  }

  function formSection(title, content) {
    return `
      <section class="tracker-form-section">
        <h6 class="tracker-form-section-title">${esc(title)}</h6>
        ${content}
      </section>
    `;
  }

  function buildTableRow(row) {
    const idDisplay = row.blotter_id || row.case_id || '-';
    const blotterNumber = row.blotter_number || '-';
    const dateFiled = row.date_filed || '-';
    const timeFiled = row.time_filed || '-';
    const complainant = row.complainant_name || '-';
    const respondent = row.respondent_name || '-';
    const status = row.status_name || '-';
    const level = row.level_name || '-';
    const viewBtn = `<button class="btn btn-sm btn-outline-secondary" data-view-id="${esc(row.case_id)}">View</button>`;
    return `
      <tr>
        <td>${esc(idDisplay)}</td>
        <td>${esc(blotterNumber)}</td>
        <td>${esc(dateFiled)}</td>
        <td>${esc(timeFiled)}</td>
        <td>${esc(complainant)}</td>
        <td>${esc(respondent)}</td>
        <td>${esc(status)}</td>
        <td>${esc(level)}</td>
        <td>${viewBtn}</td>
      </tr>
    `;
  }

  function renderPagination(total) {
    if (!paginationEl) return;
    const perPage = Math.max(1, Number(entriesPerPageInput?.value || 20));
    const pages = Math.max(1, Math.ceil(total / perPage));
    currentPage = Math.min(currentPage, pages);
    const items = [];
    const makeBtn = (label, page, disabled = false, active = false) => `
      <li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
        <button class="page-link" data-page="${page}" ${disabled ? 'disabled' : ''}>${label}</button>
      </li>
    `;
    items.push(makeBtn('Prev', currentPage - 1, currentPage <= 1));
    for (let i = 1; i <= pages; i += 1) {
      items.push(makeBtn(String(i), i, false, i === currentPage));
    }
    items.push(makeBtn('Next', currentPage + 1, currentPage >= pages));
    paginationEl.innerHTML = items.join('');
    paginationEl.querySelectorAll('button[data-page]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const page = Number(btn.getAttribute('data-page') || 1);
        if (!Number.isFinite(page)) return;
        currentPage = page;
        renderTable();
      });
    });
  }

  function renderTable() {
    if (!tableBody) return;
    const perPage = Math.max(1, Number(entriesPerPageInput?.value || 20));
    const start = (currentPage - 1) * perPage;
    const pageRows = filteredRows.slice(start, start + perPage);
    if (!pageRows.length) {
      tableBody.innerHTML = `<tr><td colspan="9" class="text-center text-muted py-4">No blotter records found.</td></tr>`;
    } else {
      tableBody.innerHTML = pageRows.map(buildTableRow).join('');
    }
    renderPagination(filteredRows.length);

    tableBody.querySelectorAll('button[data-view-id]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = String(btn.getAttribute('data-view-id') || '').trim();
        if (!id) return;
        openViewModal(id);
      });
    });
  }

  function applyFilters() {
    const term = String(searchInput?.value || '').trim().toLowerCase();
    if (!term) {
      filteredRows = [...allRows];
    } else {
      filteredRows = allRows.filter((row) => {
        const hay = [
          row.blotter_number,
          row.blotter_id,
          row.case_id,
          row.complainant_name,
          row.respondent_name
        ].map((v) => String(v || '').toLowerCase());
        return hay.some((v) => v.includes(term));
      });
    }
    currentPage = 1;
    renderTable();
  }

  async function fetchJson(url, options) {
    const res = await fetch(url, options);
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.success === false) {
      const message = data.message || 'Request failed.';
      throw new Error(message);
    }
    return data;
  }

  async function loadList() {
    if (!tableBody) return;
    tableBody.innerHTML = `<tr><td colspan="9" class="text-center text-muted py-4">Loading blotter records...</td></tr>`;
    try {
      const data = await fetchJson(`${endpoint}?action=list`);
      allRows = Array.isArray(data.items) ? data.items : [];
      applyFilters();
    } catch (err) {
      tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${esc(err.message || err)}</td></tr>`;
    }
  }

  function renderNarrative(detail) {
    if (!detail) return formField('Narrative', '-');
    if (detail.narrative_type === 'file' && detail.narrative_value) {
      const fileUrl = `${appBase}/${String(detail.narrative_value).replace(/^\/+/, '')}`;
      const link = `<a class="btn btn-sm btn-outline-primary" href="${esc(fileUrl)}" target="_blank" rel="noopener">Open Narrative File</a>`;
      return formField('Narrative File', link, true);
    }
    return formField('Narrative Report', detail.narrative_value || '-');
  }

  async function openViewModal(caseId) {
    if (!viewModal || !viewDetailsBody) return;
    viewDetailsBody.innerHTML = '<div class="text-muted">Loading details...</div>';
    if (viewModalTitle) viewModalTitle.textContent = `Blotter Details (#${caseId})`;
    viewModal.show();

    try {
      const data = await fetchJson(`${endpoint}?action=detail&case_id=${encodeURIComponent(caseId)}`);
      const d = data.detail || {};
      const complainant = d.complainant || {};
      const respondent = d.respondent || {};

      const blotterGrid = renderFieldGrid([
        { label: 'Blotter Number', value: d.blotter_number || d.blotter_id || '-' },
        { label: 'Date Filed', value: d.date_filed || '-' },
        { label: 'Time Filed', value: d.time_filed || '-' },
        { label: 'Status', value: d.status_name || '-' },
        { label: 'Case Level', value: d.level_name || '-' }
      ], 4);

      const complainantGrid = renderFieldGrid([
        { label: 'Full Name', value: complainant.full_name || '-' },
        { label: 'Contact Number', value: complainant.contact_number || '-' },
        { label: 'Age', value: complainant.age || '-' },
        { label: 'Sex', value: complainant.sex || '-' },
        { label: 'Address', value: complainant.address || '-' }
      ], 2);

      const respondentGrid = renderFieldGrid([
        { label: 'Full Name', value: respondent.full_name || '-' },
        { label: 'Contact Number', value: respondent.contact_number || '-' },
        { label: 'Age', value: respondent.age || '-' },
        { label: 'Sex', value: respondent.sex || '-' },
        { label: 'Address', value: respondent.address || '-' }
      ], 2);

      const incidentGrid = renderFieldGrid([
        { label: 'Incident Date', value: d.incident_date || '-' },
        { label: 'Incident Time', value: d.incident_time || '-' },
        { label: 'Incident Place', value: d.incident_place || '-' },
        { label: 'Complaint Type', value: d.complaint_type || '-' }
      ], 2);

      const narrativeField = renderNarrative(d);

      const html = [
        formSection('Blotter Information', blotterGrid),
        formSection('Complainant Information', complainantGrid),
        formSection('Respondent Information', respondentGrid),
        formSection('Incident Details', incidentGrid + narrativeField)
      ].join('');

      viewDetailsBody.innerHTML = html || '<div class="text-muted">No details available.</div>';
    } catch (err) {
      viewDetailsBody.innerHTML = `<div class="text-danger">${esc(err.message || err)}</div>`;
    }
  }

  let searchTimer = null;
  searchInput?.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 200);
  });

  entriesPerPageInput?.addEventListener('change', () => {
    currentPage = 1;
    renderTable();
  });

  refreshBtn?.addEventListener('click', () => {
    loadList();
  });

  loadList();
})();
