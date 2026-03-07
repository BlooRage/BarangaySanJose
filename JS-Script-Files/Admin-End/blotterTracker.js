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
  const caseActionModalEl = document.getElementById('caseActionModal');
  const caseActionModal = caseActionModalEl ? new bootstrap.Modal(caseActionModalEl) : null;
  const caseActionModalTitle = document.getElementById('caseActionModalTitle');
  const endorsementTargetGroup = document.getElementById('endorsementTargetGroup');
  const endorsementTargetSelect = document.getElementById('endorsementTargetSelect');
  const caseActionRemarks = document.getElementById('caseActionRemarks');
  const btnCaseActionProceed = document.getElementById('btnCaseActionProceed');
  const btnCaseActionReturn = document.getElementById('btnCaseActionReturn');
  const caseActionConfirmModalEl = document.getElementById('caseActionConfirmModal');
  const caseActionConfirmModal = caseActionConfirmModalEl ? new bootstrap.Modal(caseActionConfirmModalEl) : null;
  const caseActionConfirmText = document.getElementById('caseActionConfirmText');
  const btnCaseActionConfirmReturn = document.getElementById('btnCaseActionConfirmReturn');
  const btnCaseActionConfirm = document.getElementById('btnCaseActionConfirm');

  let allRows = [];
  let filteredRows = [];
  let currentPage = 1;
  let currentViewCaseId = null;
  let currentDetail = null;
  let pendingCaseAction = null;
  let caseActionHandlersBound = false;

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

  function renderNarrativeReportsSection(detail) {
    const initialStamp = String(detail?.report_timestamp || detail?.date_filed || '-');
    let initialValueHtml = esc(detail?.narrative_value || '-');
    if (detail?.narrative_type === 'file' && detail?.narrative_value) {
      const fileUrl = `${appBase}/${String(detail.narrative_value).replace(/^\/+/, '')}`;
      initialValueHtml = `<a class="btn btn-sm btn-outline-primary" href="${esc(fileUrl)}" target="_blank" rel="noopener">Open Narrative File</a>`;
    }

    return `
      <section class="tracker-form-section">
        <h6 class="tracker-form-section-title">Narrative Reports</h6>
        ${formField(`Narrative Report (${initialStamp})`, initialValueHtml, true)}
        <div id="narrativeUpdatesList" class="mt-2">
          <div class="text-muted small">Loading narrative updates...</div>
        </div>
      </section>
    `;
  }

  async function loadNarrativeUpdates(caseId) {
    const host = document.getElementById('narrativeUpdatesList');
    if (!host) return;
    try {
      const data = await fetchJson(`${endpoint}?action=case_logs&case_id=${encodeURIComponent(caseId)}`);
      const logs = Array.isArray(data.items) ? data.items : [];
      const narrativeLogs = logs.filter((item) => String(item?.log_entry || '').toLowerCase().startsWith('narrative report added:'));
      if (!narrativeLogs.length) {
        host.innerHTML = '<div class="text-muted small">No additional narrative reports yet.</div>';
        return;
      }

      host.innerHTML = narrativeLogs.map((item) => {
        const raw = String(item.log_entry || '');
        const text = raw.replace(/^Narrative report added:\s*/i, '').trim() || raw;
        const stamp = String(item.logged_at || '-');
        return formField(`Narrative Report (${stamp})`, text);
      }).join('');
    } catch (err) {
      host.innerHTML = `<div class="text-danger small">${esc(err.message || err)}</div>`;
    }
  }

  function renderCaseManagementSection(detail) {
    return `
      <section class="tracker-form-section">
        <h6 class="tracker-form-section-title">Case Management</h6>
        <div class="mb-3">
          <label class="form-label fw-semibold mb-1" for="narrativeAddInput">Additional Narrative Report</label>
          <textarea id="narrativeAddInput" class="form-control" rows="4" placeholder="Add a new narrative entry..."></textarea>
          <div class="d-flex justify-content-end mt-2">
            <button type="button" class="btn btn-sm btn-primary" id="btnAddNarrative">Add Narrative</button>
          </div>
        </div>

        <div>
          <label class="form-label fw-semibold mb-1" for="caseUpdateInput">Case Updates and Logs</label>
          <textarea id="caseUpdateInput" class="form-control" rows="4" placeholder="Add case event update..."></textarea>
          <div class="d-flex justify-content-end mt-2">
            <button type="button" class="btn btn-sm btn-success" id="btnAddCaseUpdate">Add Update</button>
          </div>
        </div>

        <hr class="my-3">
        <div class="d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-sm btn-danger" id="btnMarkDropped">Mark as Dropped</button>
          <button type="button" class="btn btn-sm btn-warning" id="btnSubjectEndorsement">Subject to Endorsement</button>
          <button type="button" class="btn btn-sm btn-success" id="btnMarkResolved">Mark as Resolved</button>
        </div>
      </section>
    `;
  }

  function actionLabel(type) {
    if (type === 'resolved') return 'Mark as Resolved';
    if (type === 'endorsement') return 'Subject to Endorsement';
    if (type === 'dropped') return 'Mark as Dropped';
    return 'Update Case';
  }

  function transitionModal(fromEl, fromModal, toModal) {
    if (fromEl && fromEl.classList.contains('show') && fromModal) {
      fromEl.addEventListener('hidden.bs.modal', () => {
        toModal?.show();
      }, { once: true });
      fromModal.hide();
      return;
    }
    toModal?.show();
  }

  function openCaseActionModal(type) {
    if (!caseActionModal) return;
    pendingCaseAction = null;
    if (caseActionModalTitle) caseActionModalTitle.textContent = actionLabel(type);
    if (caseActionRemarks) caseActionRemarks.value = '';
    if (endorsementTargetSelect) endorsementTargetSelect.value = '';
    if (endorsementTargetGroup) endorsementTargetGroup.classList.toggle('d-none', type !== 'endorsement');
    if (btnCaseActionProceed) btnCaseActionProceed.setAttribute('data-action-type', type);
    transitionModal(viewModalEl, viewModal, caseActionModal);
  }

  function initCaseActionFlow() {
    if (caseActionHandlersBound) return;
    caseActionHandlersBound = true;
    if (!btnCaseActionProceed || !btnCaseActionConfirm) return;

    btnCaseActionProceed.addEventListener('click', () => {
      const type = String(btnCaseActionProceed.getAttribute('data-action-type') || '').trim();
      const remarks = String(caseActionRemarks?.value || '').trim();
      const endorsementTarget = String(endorsementTargetSelect?.value || '').trim();
      if (!type || !currentViewCaseId) return;
      if (!remarks) {
        alert('Remarks are required.');
        caseActionRemarks?.focus();
        return;
      }
      if (type === 'endorsement' && !endorsementTarget) {
        alert('Please select endorsement target.');
        endorsementTargetSelect?.focus();
        return;
      }

      pendingCaseAction = {
        case_id: currentViewCaseId,
        action_type: type,
        endorsement_target: endorsementTarget,
        remarks
      };

      const targetText = type === 'endorsement'
        ? ` to ${endorsementTarget === 'lupon' ? 'Lupon' : 'PNP'}`
        : '';
      if (caseActionConfirmText) {
        caseActionConfirmText.textContent = `Are you sure you want to ${actionLabel(type).toLowerCase()}${targetText}?`;
      }
      transitionModal(caseActionModalEl, caseActionModal, caseActionConfirmModal);
    });

    btnCaseActionReturn?.addEventListener('click', () => {
      transitionModal(caseActionModalEl, caseActionModal, viewModal);
    });

    btnCaseActionConfirmReturn?.addEventListener('click', () => {
      transitionModal(caseActionConfirmModalEl, caseActionConfirmModal, caseActionModal);
    });

    btnCaseActionConfirm.addEventListener('click', async () => {
      if (!pendingCaseAction) return;
      try {
        btnCaseActionConfirm.disabled = true;
        await fetchJson(endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'update_case_outcome',
            ...pendingCaseAction
          })
        });
        const targetCaseId = currentViewCaseId;
        if (caseActionConfirmModalEl && caseActionConfirmModal) {
          caseActionConfirmModalEl.addEventListener('hidden.bs.modal', async () => {
            alert('Case updated successfully.');
            await loadList();
            if (targetCaseId) openViewModal(targetCaseId);
          }, { once: true });
          caseActionConfirmModal.hide();
        } else {
          alert('Case updated successfully.');
          await loadList();
          if (targetCaseId) openViewModal(targetCaseId);
        }
      } catch (err) {
        alert(String(err?.message || err || 'Failed to update case.'));
      } finally {
        btnCaseActionConfirm.disabled = false;
        pendingCaseAction = null;
      }
    });
  }

  function bindViewActions() {
    const narrativeAddInput = document.getElementById('narrativeAddInput');
    const caseUpdateInput = document.getElementById('caseUpdateInput');
    const addNarrativeBtn = document.getElementById('btnAddNarrative');
    const addUpdateBtn = document.getElementById('btnAddCaseUpdate');
    const markResolvedBtn = document.getElementById('btnMarkResolved');
    const subjectEndorsementBtn = document.getElementById('btnSubjectEndorsement');
    const markDroppedBtn = document.getElementById('btnMarkDropped');

    if (!narrativeAddInput || !addNarrativeBtn || !addUpdateBtn) return;

    addNarrativeBtn.addEventListener('click', async () => {
      const value = String(narrativeAddInput.value || '').trim();
      if (!value) {
        alert('Please enter a narrative report update first.');
        narrativeAddInput.focus();
        return;
      }
      if (!currentViewCaseId) return;

      try {
        addNarrativeBtn.disabled = true;
        await fetchJson(endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'add_narrative_entry',
            case_id: currentViewCaseId,
            narrative_report: value
          })
        });
        narrativeAddInput.value = '';
        alert('Narrative entry added to case logs.');
      } catch (err) {
        alert(String(err?.message || err || 'Failed to add narrative entry.'));
      } finally {
        addNarrativeBtn.disabled = false;
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

    markResolvedBtn?.addEventListener('click', () => openCaseActionModal('resolved'));
    subjectEndorsementBtn?.addEventListener('click', () => openCaseActionModal('endorsement'));
    markDroppedBtn?.addEventListener('click', () => openCaseActionModal('dropped'));
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

      const html = [
        formSection('Blotter Information', blotterGrid),
        formSection('Complainant Information', complainantGrid),
        formSection('Respondent Information', respondentGrid),
        formSection('Incident Details', incidentGrid),
        renderNarrativeReportsSection(d),
        renderCaseManagementSection(d)
      ].join('');

      viewDetailsBody.innerHTML = html || '<div class="text-muted">No details available.</div>';
      currentDetail = d;
      bindViewActions();
      loadNarrativeUpdates(caseId);
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
      const html = logs.map((item) => {
        const text = String(item.log_entry || '');
        const isNarrative = text.toLowerCase().startsWith('narrative report added:');
        const badge = isNarrative
          ? '<span class="badge text-bg-primary me-2">Narrative</span>'
          : '<span class="badge text-bg-secondary me-2">Case Update</span>';
        return `
          <div class="border rounded-3 p-3 mb-2">
            <div class="small text-muted mb-1">${esc(item.logged_at || '-')} | ${esc(item.logged_by_display || item.logged_by_name || item.logged_by_user_id || 'Unknown User')}</div>
            <div class="mb-1">${badge}</div>
            <div>${esc(text)}</div>
          </div>
        `;
      }).join('');
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

  initCaseActionFlow();
  loadList();
})();
