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
  const caseLogsModalEl = document.getElementById('caseLogsModal');
  const caseLogsModal = caseLogsModalEl ? new bootstrap.Modal(caseLogsModalEl) : null;
  const caseLogsModalTitle = document.getElementById('caseLogsModalTitle');
  const caseLogsBody = document.getElementById('caseLogsBody');

  let allRows = [];
  let filteredRows = [];
  let currentPage = 1;
  let currentViewCaseId = null;
  let currentDetail = null;

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
    const logsBtn = `<button class="btn btn-sm btn-outline-primary ms-1" data-logs-id="${esc(row.case_id)}">Case Logs</button>`;
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
        <td>${viewBtn}${logsBtn}</td>
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

    tableBody.querySelectorAll('button[data-logs-id]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = String(btn.getAttribute('data-logs-id') || '').trim();
        if (!id) return;
        openCaseLogsModal(id);
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
      tableBody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">${esc(err.message || err)}</td></tr>`;
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

  function renderCaseManagementSection(detail) {
    const narrativeValue = String(detail?.narrative_value || '');
    const narrativeType = String(detail?.narrative_type || 'text');
    const hint = narrativeType === 'file'
      ? '<div class="form-text">This case currently uses a narrative file. Saving text below will replace it with a narrative report.</div>'
      : '';

    return `
      <section class="tracker-form-section">
        <h6 class="tracker-form-section-title">Case Management</h6>
        <div class="mb-3">
          <label class="form-label fw-semibold mb-1" for="narrativeEditInput">Narrative Report</label>
          <textarea id="narrativeEditInput" class="form-control" rows="6" disabled>${esc(narrativeValue)}</textarea>
          ${hint}
          <div class="d-flex gap-2 mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnEditNarrative">Edit Narrative</button>
            <button type="button" class="btn btn-sm btn-primary d-none" id="btnSaveNarrative">Save Narrative</button>
            <button type="button" class="btn btn-sm btn-light border d-none" id="btnCancelNarrative">Cancel</button>
          </div>
        </div>

        <div>
          <label class="form-label fw-semibold mb-1" for="caseUpdateInput">Case Updates and Logs</label>
          <textarea id="caseUpdateInput" class="form-control" rows="4" placeholder="Add case event update..."></textarea>
          <div class="d-flex justify-content-end mt-2">
            <button type="button" class="btn btn-sm btn-success" id="btnAddCaseUpdate">Add Update</button>
          </div>
        </div>
      </section>
    `;
  }

  function bindViewActions() {
    const narrativeInput = document.getElementById('narrativeEditInput');
    const caseUpdateInput = document.getElementById('caseUpdateInput');
    const editBtn = document.getElementById('btnEditNarrative');
    const saveBtn = document.getElementById('btnSaveNarrative');
    const cancelBtn = document.getElementById('btnCancelNarrative');
    const addUpdateBtn = document.getElementById('btnAddCaseUpdate');

    if (!narrativeInput || !editBtn || !saveBtn || !cancelBtn || !addUpdateBtn) return;

    const initialValue = narrativeInput.value;

    editBtn.addEventListener('click', () => {
      narrativeInput.disabled = false;
      narrativeInput.focus();
      editBtn.classList.add('d-none');
      saveBtn.classList.remove('d-none');
      cancelBtn.classList.remove('d-none');
    });

    cancelBtn.addEventListener('click', () => {
      narrativeInput.value = initialValue;
      narrativeInput.disabled = true;
      editBtn.classList.remove('d-none');
      saveBtn.classList.add('d-none');
      cancelBtn.classList.add('d-none');
    });

    saveBtn.addEventListener('click', async () => {
      const value = String(narrativeInput.value || '').trim();
      if (!value) {
        alert('Narrative report is required.');
        narrativeInput.focus();
        return;
      }
      if (!currentViewCaseId) return;

      try {
        saveBtn.disabled = true;
        await fetchJson(endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'update_narrative',
            case_id: currentViewCaseId,
            narrative_report: value
          })
        });
        currentDetail = { ...(currentDetail || {}), narrative_type: 'text', narrative_value: value };
        narrativeInput.disabled = true;
        editBtn.classList.remove('d-none');
        saveBtn.classList.add('d-none');
        cancelBtn.classList.add('d-none');
        loadList();
        alert('Narrative report updated.');
      } catch (err) {
        alert(String(err?.message || err || 'Failed to update narrative.'));
      } finally {
        saveBtn.disabled = false;
      }
    });

    addUpdateBtn.addEventListener('click', async () => {
      const logText = String(caseUpdateInput?.value || '').trim();
      if (!logText) {
        alert('Please enter a case update first.');
        caseUpdateInput?.focus();
        return;
      }
      if (!currentViewCaseId) return;
      try {
        addUpdateBtn.disabled = true;
        await fetchJson(endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'add_case_log',
            case_id: currentViewCaseId,
            log_entry: logText
          })
        });
        caseUpdateInput.value = '';
        alert('Case update logged.');
      } catch (err) {
        alert(String(err?.message || err || 'Failed to add case update.'));
      } finally {
        addUpdateBtn.disabled = false;
      }
    });
  }

  async function openViewModal(caseId) {
    if (!viewModal || !viewDetailsBody) return;
    currentViewCaseId = String(caseId);
    currentDetail = null;
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
        formSection('Incident Details', incidentGrid + narrativeField),
        renderCaseManagementSection(d)
      ].join('');

      viewDetailsBody.innerHTML = html || '<div class="text-muted">No details available.</div>';
      currentDetail = d;
      bindViewActions();
    } catch (err) {
      viewDetailsBody.innerHTML = `<div class="text-danger">${esc(err.message || err)}</div>`;
    }
  }

  async function openCaseLogsModal(caseId) {
    if (!caseLogsModal || !caseLogsBody) return;
    caseLogsBody.innerHTML = '<div class="text-muted">Loading case logs...</div>';
    if (caseLogsModalTitle) caseLogsModalTitle.textContent = `Case Logs (#${caseId})`;
    caseLogsModal.show();
    try {
      const data = await fetchJson(`${endpoint}?action=case_logs&case_id=${encodeURIComponent(caseId)}`);
      const logs = Array.isArray(data.items) ? data.items : [];
      if (!logs.length) {
        caseLogsBody.innerHTML = '<div class="text-muted">No case logs yet.</div>';
        return;
      }
      const html = logs.map((item) => `
        <div class="border rounded-3 p-3 mb-2">
          <div class="small text-muted mb-1">${esc(item.logged_at || '-')} | ${esc(item.logged_by_display || item.logged_by_name || item.logged_by_user_id || 'Unknown User')}</div>
          <div>${esc(item.log_entry || '')}</div>
        </div>
      `).join('');
      caseLogsBody.innerHTML = html;
    } catch (err) {
      caseLogsBody.innerHTML = `<div class="text-danger">${esc(err.message || err)}</div>`;
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
