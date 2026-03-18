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
  const narrativeTextModalEl = document.getElementById('narrativeTextModal');
  const narrativeTextModal = narrativeTextModalEl ? new bootstrap.Modal(narrativeTextModalEl) : null;
  const narrativeTextModalTitle = document.getElementById('narrativeTextModalTitle');
  const narrativeTextModalBody = document.getElementById('narrativeTextModalBody');
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

  function formField(label, value, raw = false, fullWidth = false) {
    const text = String(value ?? '').trim();
    const rendered = raw ? (text || '-') : esc(text || '-');
    return `
      <div class="tracker-form-field${fullWidth ? ' tracker-form-field--full' : ''}">
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
    return `<div class="tracker-form-grid ${cls}">${clean.map((f) => formField(f.label, f.value, !!f.raw, !!f.fullWidth)).join('')}</div>`;
  }

  function formSection(title, content) {
    return `
      <section class="tracker-form-section">
        <h6 class="tracker-form-section-title">${esc(title)}</h6>
        ${content}
      </section>
    `;
  }

  function formatNameWithMiddleInitial(fullName) {
    const raw = String(fullName ?? '').trim().replace(/\s+/g, ' ');
    if (!raw || raw === '-') return '-';
    const suffixSet = new Set(['jr', 'jr.', 'sr', 'sr.', 'ii', 'iii', 'iv', 'v']);
    const tokens = raw.split(' ').filter(Boolean);
    if (tokens.length < 3) return raw;

    let suffix = '';
    const tail = tokens[tokens.length - 1].toLowerCase();
    const core = [...tokens];
    if (suffixSet.has(tail)) {
      suffix = core.pop() || '';
    }

    if (core.length < 3) {
      return [core.join(' '), suffix].filter(Boolean).join(' ');
    }

    const first = core[0];
    const last = core[core.length - 1];
    const middleTokens = core.slice(1, -1);
    const middleInitials = middleTokens
      .map((m) => String(m || '').trim())
      .filter(Boolean)
      .map((m) => `${m.charAt(0).toUpperCase()}.`);

    return [first, ...middleInitials, last, suffix].filter(Boolean).join(' ');
  }

  function toneForStatus(statusName) {
    const s = String(statusName || '').trim().toLowerCase();
    if (s === 'active') return 'pending';
    if (s === 'resolved') return 'approved';
    if (s === 'endorsed') return 'info';
    if (s === 'dropped') return 'archived';
    return 'archived';
  }

  function toneForCaseLevel(levelName) {
    const l = String(levelName || '').trim().toLowerCase();
    if (l === 'blotter only') return 'pending';
    if (l === 'settled') return 'approved';
    if (l.includes('endorsed')) return 'info';
    if (l === 'unsettled') return 'denied';
    return 'archived';
  }

  function badge(text, toneClass) {
    return `<span class="status-pill ${esc(toneClass || 'archived')}">${esc(text || '-')}</span>`;
  }

  function parseAddressParts(addressText) {
    const raw = String(addressText ?? '').trim();
    if (!raw) return {};

    const map = {};
    raw.split(/\s*,\s*/).forEach((part) => {
      const idx = part.indexOf(':');
      if (idx <= 0) return;
      const key = part.slice(0, idx).trim().toLowerCase();
      const value = part.slice(idx + 1).trim();
      if (!key || !value) return;
      map[key] = value;
    });

    const sysRaw = String(map['address system'] || '').toLowerCase();
    let addressSystem = map['address system'] || '';
    if (sysRaw === 'house') addressSystem = 'House Numbering System';
    if (sysRaw === 'lot_block') addressSystem = 'Lot/Block System';

    return {
      address_system: addressSystem,
      unit_number: map.unit || '',
      house_number: map['house no.'] || map['house no'] || '',
      street_name: map.street || '',
      lot_number: map.lot || '',
      block_number: map.block || '',
      phase_number: map.phase || '',
      subdivision: map.subdivision || '',
      area_number: map.area || '',
      barangay: map.barangay || '',
      municipality: map.municipality || map['municipality / city'] || '',
      province: map.province || ''
    };
  }

  function formatCompleteAddress(address, fallbackRaw = '') {
    const rawSystem = String(address?.address_system || '').toLowerCase();
    const isLotBlock = rawSystem.includes('lot/block') || rawSystem === 'lot_block';
    const stripTerm = (value, pattern) => String(value || '').replace(pattern, ' ').replace(/\s+/g, ' ').trim();
    const cleanStreet = stripTerm(address?.street_name || '', /\b(street|st)\b\.?/gi);
    const cleanSubdivision = stripTerm(address?.subdivision || '', /\b(subdivision|subd)\b\.?/gi);
    const cleanPhase = stripTerm(address?.phase_number || '', /\b(phase|ph)\b\.?/gi);

    const mainNumber = isLotBlock
      ? [address?.lot_number ? `Lot ${address.lot_number}` : '', address?.block_number ? `Block ${address.block_number}` : ''].filter(Boolean).join(', ')
      : String(address?.house_number || '').trim();

    const streetWithSuffix = cleanStreet ? `${cleanStreet} Street` : '';
    const subdivisionWithSuffix = cleanSubdivision ? `${cleanSubdivision} Subdivision` : '';
    const phaseWithPrefix = cleanPhase ? `Phase ${cleanPhase}` : '';
    const primaryLine = [mainNumber, streetWithSuffix].filter(Boolean).join(' ').trim();

    const parts = [
      address?.unit_number ? `Unit ${address.unit_number}` : '',
      primaryLine,
      phaseWithPrefix,
      subdivisionWithSuffix,
      address?.barangay || 'Barangay San Jose',
      address?.municipality || 'Rodriguez',
      address?.province || 'Rizal'
    ].filter((v) => String(v || '').trim() !== '');

    const line = parts.join(', ').trim();
    return line || String(fallbackRaw || '-');
  }

  function renderParticipantGrid(participant) {
    const address = parseAddressParts(participant?.address || '');
    const hasStructuredAddress = Object.values(address).some((v) => String(v || '').trim() !== '');
    const fields = [
      { label: 'Full Name', value: participant?.full_name || '-' },
      { label: 'Contact Number', value: participant?.contact_number || '-' },
      { label: 'Age', value: participant?.age || '-' },
      { label: 'Sex', value: participant?.sex || '-' }
    ];

    if (hasStructuredAddress) {
fields.push({ label: 'Address', value: formatCompleteAddress(address, participant?.address || '-'), fullWidth: true });
    } else {
fields.push({ label: 'Address', value: participant?.address || '-', fullWidth: true });
    }

    return renderFieldGrid(fields, 2);
  }


  function buildTableRow(row) {
    const blotterIdDisplay = row.blotter_id || '-';
    const blotterNumber = row.blotter_number || '-';
    const dateFiled = row.date_filed || '-';
    const timeFiled = row.time_filed || '-';
    const complainant = formatNameWithMiddleInitial(row.complainant_name || '-');
    const respondent = formatNameWithMiddleInitial(row.respondent_name || '-');
    const status = row.status_name || '-';
    const level = row.level_name || '-';
    const statusBadge = badge(status, toneForStatus(status));
    const levelBadge = badge(level, toneForCaseLevel(level));
    const viewBtn = `<button class="btn btn-sm btn-outline-secondary" data-view-id="${esc(row.case_id)}">View</button>`;
    const logsBtn = `<button class="btn btn-sm btn-outline-primary ms-1" data-logs-id="${esc(row.case_id)}">Case Logs</button>`;
    return `
      <tr>
        <td>${esc(blotterIdDisplay)}</td>
        <td>${esc(blotterNumber)}</td>
        <td>${esc(dateFiled)}</td>
        <td>${esc(timeFiled)}</td>
        <td>${esc(complainant)}</td>
        <td>${esc(respondent)}</td>
        <td>${statusBadge}</td>
        <td>${levelBadge}</td>
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
          row.blotter_id,
          row.blotter_number,
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
      const fileUrl = String(detail?.narrative_url || '').trim()
        || `${appBase}/${String(detail.narrative_value).replace(/^\/+/, '')}`;
      initialValueHtml = `
        <div class="tracker-attachment-actions">
          <a class="btn btn-sm btn-outline-primary" href="${esc(fileUrl)}" target="_blank" rel="noopener">Open Narrative File</a>
        </div>
      `;
    } else if (detail?.narrative_type === 'text' && detail?.narrative_value) {
      const preview = `${String(detail.narrative_value).trim().slice(0, 280)}${String(detail.narrative_value).trim().length > 280 ? '...' : ''}`;
      initialValueHtml = `
        <div class="tracker-form-value-text">${esc(preview || '-')}</div>
        <div class="tracker-attachment-actions mt-2">
          <button type="button" class="btn btn-sm btn-outline-primary" id="btnOpenTypedNarrative">
            Open Typed Narrative
          </button>
        </div>
      `;
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

  function titleCase(value) {
    return String(value || '')
      .trim()
      .replace(/[_-]+/g, ' ')
      .replace(/\b\w/g, (m) => m.toUpperCase());
  }

  function renderSignatureSection(detail) {
    const signatures = detail?.signatures && typeof detail.signatures === 'object'
      ? Object.entries(detail.signatures)
      : [];

    if (!signatures.length) {
      return '';
    }

    const cards = signatures.map(([role, item]) => {
      const label = titleCase(role || 'Signature');
      const fileUrl = String(item?.file_url || '').trim()
        || (String(item?.file_path || '').trim()
          ? `${appBase}/${String(item.file_path).replace(/^\/+/, '')}`
          : '');

      if (!fileUrl) {
        return `
          <article class="tracker-signature-card">
            <div class="tracker-signature-card__header">
              <span class="tracker-signature-card__title">${esc(label)}</span>
            </div>
            <div class="tracker-signature-card__empty">Signature file unavailable.</div>
          </article>
        `;
      }

      return `
        <article class="tracker-signature-card">
          <div class="tracker-signature-card__header">
            <span class="tracker-signature-card__title">${esc(label)}</span>
            <a class="btn btn-sm btn-outline-primary" href="${esc(fileUrl)}" target="_blank" rel="noopener">Open</a>
          </div>
          <a class="tracker-signature-card__preview" href="${esc(fileUrl)}" target="_blank" rel="noopener" aria-label="Open ${esc(label)} signature">
            <img src="${esc(fileUrl)}" alt="${esc(label)} signature preview" loading="lazy">
          </a>
        </article>
      `;
    }).join('');

    return formSection('Signatures', `<div class="tracker-signature-grid">${cards}</div>`);
  }

  async function loadNarrativeUpdates(caseId) {
    const host = document.getElementById('narrativeUpdatesList');
    if (!host) return;
    try {
      const data = await fetchJson(`${endpoint}?action=case_logs&case_id=${encodeURIComponent(caseId)}`);
      const logs = Array.isArray(data.items) ? data.items : [];
      const narrativeLogs = logs
        .filter((item) => String(item?.log_entry || '').toLowerCase().startsWith('narrative report added:'))
        .reverse();
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
    const isFinalized = String(detail?.status_name || '').trim().toLowerCase() !== 'active';
    const disabledAttr = isFinalized ? 'disabled' : '';
    const statusActions = isFinalized
      ? `
        <div class="small text-muted">
          Status is final (${esc(detail?.status_name || '-')}); no further status changes are allowed.
        </div>
      `
      : `
        <button type="button" class="btn btn-sm btn-danger" id="btnMarkDropped">Mark as Dropped</button>
        <button type="button" class="btn btn-sm btn-warning" id="btnSubjectEndorsement">Subject to Endorsement</button>
        <button type="button" class="btn btn-sm btn-success" id="btnMarkResolved">Mark as Resolved</button>
      `;

    return `
      <section class="tracker-form-section">
        <h6 class="tracker-form-section-title">Case Management</h6>
        ${isFinalized ? '<div class="small text-muted mb-2">Case events remain visible in Case Logs, but adding new updates is disabled.</div>' : ''}
        <div class="mb-3">
          <label class="form-label fw-semibold mb-1" for="narrativeAddInput">Additional Narrative Report</label>
          <textarea id="narrativeAddInput" class="form-control" rows="4" placeholder="Add a new narrative entry..." ${disabledAttr}></textarea>
          <div class="d-flex justify-content-end mt-2">
            <button type="button" class="btn btn-sm btn-primary" id="btnAddNarrative" ${disabledAttr}>Add Narrative</button>
          </div>
        </div>

        <div>
          <label class="form-label fw-semibold mb-1" for="caseUpdateInput">Case Updates and Logs</label>
          <textarea id="caseUpdateInput" class="form-control" rows="4" placeholder="Add case event update..." ${disabledAttr}></textarea>
          <div class="d-flex justify-content-end mt-2">
            <button type="button" class="btn btn-sm btn-success" id="btnAddCaseUpdate" ${disabledAttr}>Add Update</button>
          </div>
        </div>

        <hr class="my-3">
        <div class="d-flex flex-wrap gap-2">
          ${statusActions}
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
    if (String(currentDetail?.status_name || '').trim().toLowerCase() !== 'active') {
      alert('Case status is already finalized and cannot be changed again.');
      return;
    }
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
    const openTypedNarrativeBtn = document.getElementById('btnOpenTypedNarrative');
    const caseUpdateInput = document.getElementById('caseUpdateInput');
    const addNarrativeBtn = document.getElementById('btnAddNarrative');
    const addUpdateBtn = document.getElementById('btnAddCaseUpdate');
    const markResolvedBtn = document.getElementById('btnMarkResolved');
    const subjectEndorsementBtn = document.getElementById('btnSubjectEndorsement');
    const markDroppedBtn = document.getElementById('btnMarkDropped');
    const isFinalized = String(currentDetail?.status_name || '').trim().toLowerCase() !== 'active';

    if (!narrativeAddInput || !addNarrativeBtn || !addUpdateBtn) return;

    openTypedNarrativeBtn?.addEventListener('click', () => {
      if (!narrativeTextModal || !narrativeTextModalBody) return;
      const narrativeText = String(currentDetail?.narrative_value || '').trim();
      const stamp = String(currentDetail?.report_timestamp || currentDetail?.date_filed || '').trim();
      if (narrativeTextModalTitle) {
        narrativeTextModalTitle.textContent = stamp ? `Narrative Report (${stamp})` : 'Narrative Report';
      }
      narrativeTextModalBody.textContent = narrativeText || '-';
      narrativeTextModal.show();
    });

    addNarrativeBtn.addEventListener('click', async () => {
      if (isFinalized) {
        alert('Case is finalized. New narrative updates are not allowed.');
        return;
      }
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
      if (isFinalized) {
        alert('Case is finalized. New case logs are not allowed.');
        return;
      }
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
        { label: 'Blotter ID', value: d.blotter_id || '-' },
        { label: 'Blotter Number', value: d.blotter_number || d.blotter_id || '-' },
        { label: 'Date Filed', value: d.date_filed || '-' },
        { label: 'Time Filed', value: d.time_filed || '-' },
        { label: 'Status', value: d.status_name || '-' },
        { label: 'Case Level', value: d.level_name || '-' }
      ], 4);

      const complainantGrid = renderParticipantGrid(complainant);
      const respondentGrid = renderParticipantGrid(respondent);

      const incidentGrid = d.narrative_type === 'file'
        ? ''
        : renderFieldGrid([
          { label: 'Incident Date', value: d.incident_date || '-' },
          { label: 'Incident Time', value: d.incident_time || '-' },
          { label: 'Incident Place', value: d.incident_place || '-' },
          { label: 'Complaint Type', value: d.complaint_type || '-' }
        ], 2);

      const html = [
        formSection('Blotter Information', blotterGrid),
        formSection('Complainant Information', complainantGrid),
        formSection('Respondent Information', respondentGrid),
        incidentGrid ? formSection('Incident Details', incidentGrid) : '',
        renderNarrativeReportsSection(d),
        renderSignatureSection(d),
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

