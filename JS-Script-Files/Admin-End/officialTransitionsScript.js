/**
 * Official Transition — JS
 */
(function () {
  'use strict';

  const API = '../PhpFiles/Admin-End/officialTransitions.php';

  // ── Bootstrap instances ────────────────────────────────────────────────────
  const modalInstances = {};
  function getModal(id) {
    if (!modalInstances[id]) {
      const el = document.getElementById(id);
      if (el) modalInstances[id] = new bootstrap.Modal(el);
    }
    return modalInstances[id] || null;
  }

  // ── Toast ──────────────────────────────────────────────────────────────────
  function showToast(message, type = 'success') {
    const el = document.getElementById('otToast');
    const msgEl = document.getElementById('otToastMsg');
    if (!el || !msgEl) return;
    msgEl.textContent = message;
    el.className = 'toast align-items-center text-white border-0 ' +
      (type === 'success' ? 'bg-success' : type === 'warning' ? 'bg-warning text-dark' : 'bg-danger');
    const t = bootstrap.Toast.getOrCreateInstance(el, { delay: 4000 });
    t.show();
  }

  // ── Generic fetch ──────────────────────────────────────────────────────────
  async function apiFetch(params = {}, method = 'GET') {
    let url = API;
    let body = null;
    if (method === 'GET') {
      const qs = new URLSearchParams(params).toString();
      if (qs) url += '?' + qs;
    } else {
      body = new URLSearchParams(params);
    }
    const res = await fetch(url, {
      method,
      ...(body ? { body, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } } : {})
    });
    return res.json();
  }

  const pageTool = document.body?.dataset.otTool || 'current_term';
  const autoStart = document.body?.dataset.otAutostart || '';
  const emptyQueueMessage = pageTool === 'create_new_term'
    ? 'No term encoding records yet. Create the term first, then encode the elected winners and appointed officials.'
    : 'No transitions found.';

  // ── Status badge ──────────────────────────────────────────────────────────
  function statusBadge(status) {
    const map = {
      Open:              ['badge-ot-open',      'Open'],
      CandidateEncoding: ['badge-ot-encoding',  'Access Setup'],
      PendingDecision:   ['badge-ot-pending',   'Pending Access'],
      Decided:           ['badge-ot-decided',   'Access Ready'],
      Completed:         ['badge-ot-completed', 'Completed'],
      Cancelled:         ['badge-ot-cancelled', 'Cancelled'],
    };
    const [cls, label] = map[status] || ['bg-secondary text-white', status];
    return `<span class="badge ${cls}">${label}</span>`;
  }

  function typeBadge(type) {
    const labels = {
      BarangayElection: 'Brgy. Election',
      SKElection:       'SK Election',
      Appointment:      'Appointment',
      Reappointment:    'Reappointment',
      Resignation:      'Resignation',
      Removal:          'Removal',
      Retirement:       'Retirement',
      Replacement:      'Replacement',
    };
    return `<span class="badge bg-light text-dark border">${labels[type] || type}</span>`;
  }

  // ══════════════════════════════════════════════════════════════════════════
  // TRANSITIONS TABLE
  // ══════════════════════════════════════════════════════════════════════════
  let currentTab    = 'active';
  let currentPage   = 0;
  const PAGE_SIZE   = 50;

  async function loadTransitions() {
    const tbody = document.getElementById('otTableBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin me-2"></i> Loading…</td></tr>';

    const q    = document.getElementById('otSearch')?.value.trim()     || '';
    const type = document.getElementById('otTypeFilter')?.value.trim() || '';

    const data = await apiFetch({
      action: 'fetch_transitions',
      tab:    currentTab,
      q, type,
      limit:  PAGE_SIZE,
      offset: currentPage * PAGE_SIZE,
    });

    if (!data.success) {
      tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${data.message || 'Failed to load.'}</td></tr>`;
      return;
    }
    if (data.notice) {
      tbody.innerHTML = `<tr><td colspan="8" class="text-center py-5">
        <div class="text-muted mb-2"><i class="fas fa-database fa-2x"></i></div>
        <div class="fw-semibold">Database migration not yet applied.</div>
        <div class="text-muted small mt-1">Run <code>20260323_create_official_transitions_schema.sql</code> on your database to activate this module.</div>
      </td></tr>`;
      return;
    }

    const rows = data.data || [];
    if (!rows.length) {
      tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">${emptyQueueMessage}</td></tr>`;
      renderPagination(0);
      return;
    }

    tbody.innerHTML = rows.map(r => {
      const outgoingParts = [
        r.outgoing_position
          ? `<div class="small text-muted">${esc(r.outgoing_position)}</div>`
          : '',
        r.outgoing_name
          ? `<div>${esc(r.outgoing_name)}</div>`
          : '',
      ].filter(Boolean);
      const outgoing = outgoingParts.join('');
      const batch = r.batch_label
        ? `<div><span class="badge bg-secondary">${esc(r.batch_label)}</span></div>${
            r.proclamation_date || r.next_election_date
              ? `<div class="small text-muted mt-1">${
                  r.proclamation_date ? `Proclaimed: ${fmtDate(r.proclamation_date)}` : ''
                }${r.proclamation_date && r.next_election_date ? '<br>' : ''}${
                  r.next_election_date ? `Next election: ${fmtDate(r.next_election_date)}` : ''
                }</div>`
              : ''
          }`
        : '<span class="text-muted small">—</span>';
      const effDate   = r.effective_date   ? fmtDate(r.effective_date) : '<span class="text-muted">—</span>';
      const actingTag = r.is_acting == 1   ? ' <span class="badge bg-info text-dark ms-1">Acting</span>' : '';

      const actions = buildRowActions(r);

      return `
        <tr>
          <td class="fw-semibold">${esc(r.transition_id)}</td>
          <td>${typeBadge(r.transition_type)}</td>
          <td>${esc(r.position || '—')}${actingTag}</td>
          <td>${outgoing}</td>
          <td>${batch}</td>
          <td>${effDate}</td>
          <td>${statusBadge(r.status)}</td>
          <td>${actions}</td>
        </tr>`;
    }).join('');

    renderPagination(data.total || 0);
  }

  function buildRowActions(r) {
    const tid = r.transition_id;
    const s   = r.status;
    const btns = [];

    if (['Open', 'CandidateEncoding', 'PendingDecision', 'Decided'].includes(s)) {
      btns.push(`<button class="btn btn-xs btn-outline-primary py-0 px-2" onclick="otOpenCandidates('${esc(tid)}')" title="Set access handover"><i class="fas fa-user-plus me-1"></i>Set Access</button>`);
    }
    if (s === 'PendingDecision' || s === 'Decided') {
      btns.push(`<button class="btn btn-xs btn-success py-0 px-2" onclick="otOpenWinner('${esc(tid)}')" title="Finalize access"><i class="fas fa-key"></i></button>`);
    }
    if (s !== 'Completed' && s !== 'Cancelled') {
      btns.push(`<button class="btn btn-xs btn-outline-danger py-0 px-2" onclick="otCancelTransition('${esc(tid)}')" title="Cancel"><i class="fas fa-times"></i></button>`);
    }

    return `<div class="d-flex gap-1">${btns.join('')}</div>`;
  }

  function renderPagination(total) {
    const el = document.getElementById('otTablePagination');
    if (!el) return;
    const totalPages = Math.ceil(total / PAGE_SIZE);
    if (totalPages <= 1) { el.innerHTML = ''; return; }
    let html = '<nav><ul class="pagination pagination-sm mb-0">';
    html += `<li class="page-item ${currentPage === 0 ? 'disabled' : ''}"><a class="page-link" href="#" onclick="otGoPage(${currentPage - 1});return false;">‹</a></li>`;
    for (let i = 0; i < totalPages; i++) {
      html += `<li class="page-item ${i === currentPage ? 'active' : ''}"><a class="page-link" href="#" onclick="otGoPage(${i});return false;">${i + 1}</a></li>`;
    }
    html += `<li class="page-item ${currentPage >= totalPages - 1 ? 'disabled' : ''}"><a class="page-link" href="#" onclick="otGoPage(${currentPage + 1});return false;">›</a></li>`;
    html += '</ul></nav>';
    el.innerHTML = html;
  }

  window.otGoPage = function (page) {
    currentPage = page;
    loadTransitions();
  };

  // Tab switching
  document.querySelectorAll('[data-ot-tab]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-ot-tab]').forEach(b => b.classList.remove('active', 'btn-outline-primary'));
      document.querySelectorAll('[data-ot-tab]').forEach(b => b.classList.add('btn-outline-secondary'));
      btn.classList.add('active', 'btn-outline-primary');
      btn.classList.remove('btn-outline-secondary');
      currentTab  = btn.dataset.otTab;
      currentPage = 0;
      loadTransitions();
    });
  });

  // Search & filter with debounce
  let searchTimer;
  ['otSearch', 'otTypeFilter'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', () => {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => { currentPage = 0; loadTransitions(); }, 350);
    });
  });

  document.getElementById('btnOtRefresh')?.addEventListener('click', loadTransitions);

  // ══════════════════════════════════════════════════════════════════════════
  // NEW TRANSITION MODAL — council seat driven
  // ══════════════════════════════════════════════════════════════════════════
  const ntCouncilId  = document.getElementById('ntCouncilId');
  const ntType       = document.getElementById('ntType');
  const ntBatchWrap  = document.getElementById('ntBatchLabelWrap');
  const ntProclamationWrap = document.getElementById('ntProclamationDateWrap');
  const ntNextElectionWrap = document.getElementById('ntNextElectionDateWrap');
  const ntReasonLbl  = document.getElementById('ntReasonLabel');
  const ntSeatWrap   = document.getElementById('ntSeatInfoWrap');
  const ntProclamationDate = document.getElementById('ntProclamationDate');
  const ntEffectiveDate = document.getElementById('ntEffectiveDate');

  // Transition type options per selection_method
  const ELECTED_TYPES = [
    ['BarangayElection', 'Barangay Election (Term End)'],
    ['SKElection',       'SK Election (Term End)'],
    ['Resignation',      'Resignation'],
    ['Removal',          'Removal'],
    ['Retirement',       'Retirement'],
  ];
  const APPOINTED_TYPES = [
    ['Appointment',   'New Appointment'],
    ['Reappointment', 'Reappointment'],
    ['Resignation',   'Resignation'],
    ['Removal',       'Removal'],
    ['Retirement',    'Retirement'],
  ];

  function populateTransitionTypes(method) {
    if (!ntType) return;
    const types = method === 'Elected' ? ELECTED_TYPES : APPOINTED_TYPES;
    ntType.innerHTML = '<option value="">— Select —</option>' +
      types.map(([v, l]) => `<option value="${v}">${l}</option>`).join('');
  }

  ntCouncilId?.addEventListener('change', () => {
    const opt = ntCouncilId.options[ntCouncilId.selectedIndex];
    if (!opt?.value) {
      ntSeatWrap && (ntSeatWrap.style.display = 'none');
      if (ntType) ntType.innerHTML = '<option value="">— Select a seat first —</option>';
      return;
    }

    const seat       = opt.dataset.seat        || '';
    const method     = opt.dataset.method      || 'Elected';
    const holderName = opt.dataset.officialName || 'Vacant';
    const acctStatus = opt.dataset.accountStatus || '';

    // Show seat info card
    if (ntSeatWrap) {
      ntSeatWrap.style.display = '';
      document.getElementById('ntSeatName').textContent   = seat;
      document.getElementById('ntSeatMethod').textContent = method;
      document.getElementById('ntSeatHolder').textContent = holderName;
      const badge = document.getElementById('ntSeatHolderStatus');
      if (badge) {
        badge.textContent  = acctStatus || '';
        badge.className    = 'badge ms-1 ' + (
          acctStatus.toLowerCase().includes('active')   ? 'bg-success' :
          acctStatus.toLowerCase().includes('inactive') ? 'bg-secondary' :
          acctStatus.toLowerCase().includes('suspend')  ? 'bg-warning text-dark' :
          'bg-light text-dark'
        );
        badge.style.display = acctStatus ? '' : 'none';
      }
    }

    // Populate transition types based on selection method
    populateTransitionTypes(method);

    // Reset dependent fields
    if (ntType) ntType.value = '';
    if (ntBatchWrap) ntBatchWrap.style.display = 'none';
    if (ntProclamationWrap) ntProclamationWrap.style.display = 'none';
    if (ntNextElectionWrap) ntNextElectionWrap.style.display = 'none';
    if (ntReasonLbl) ntReasonLbl.textContent = 'Reason';
  });

  ntType?.addEventListener('change', () => {
    const v          = ntType.value;
    const isElection = ['BarangayElection','SKElection'].includes(v);
    const isRemoval  = v === 'Removal';
    if (ntBatchWrap) ntBatchWrap.style.display = isElection ? '' : 'none';
    if (ntProclamationWrap) ntProclamationWrap.style.display = isElection ? '' : 'none';
    if (ntNextElectionWrap) ntNextElectionWrap.style.display = isElection ? '' : 'none';
    if (ntReasonLbl) ntReasonLbl.textContent   = isRemoval ? 'Reason *' : 'Reason';
  });

  ntProclamationDate?.addEventListener('change', () => {
    if (ntEffectiveDate && ntType && ['BarangayElection', 'SKElection'].includes(ntType.value)) {
      ntEffectiveDate.value = ntProclamationDate.value || '';
    }
  });

  document.getElementById('formNewTransition')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitNewTransition');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Creating…';

    const fd = new FormData(e.target);
    fd.append('action', 'new_transition');
    const params = {};
    fd.forEach((v, k) => params[k] = v);

    const data = await apiFetch(params, 'POST');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-save me-1"></i> Create Transition';

    if (data.success) {
      showToast(data.message || 'Transition created.');
      getModal('modalNewTransition')?.hide();
      e.target.reset();
      loadTransitions();
      updateStats();
    } else {
      showToast(data.message || 'Failed to create.', 'error');
    }
  });

  // ══════════════════════════════════════════════════════════════════════════
  // NEW BATCH MODAL
  // ══════════════════════════════════════════════════════════════════════════
  const batchSeatPreview = Array.isArray(window.OT_BATCH_SEAT_PREVIEW) ? window.OT_BATCH_SEAT_PREVIEW : [];

  function batchSeatStatusBadge(status) {
    const text = String(status || '').trim();
    if (!text) return '<span class="text-muted">—</span>';
    let cls = 'bg-light text-dark';
    if (/active/i.test(text)) cls = 'bg-success';
    else if (/inactive/i.test(text)) cls = 'bg-secondary';
    else if (/suspend/i.test(text)) cls = 'bg-warning text-dark';
    return `<span class="badge ${cls}">${esc(text)}</span>`;
  }

  function renderBatchSeatPreview() {
    const emptyEl = document.getElementById('nbAutoSeatPreviewEmpty');
    const wrapEl = document.getElementById('nbAutoSeatPreviewWrap');
    const bodyEl = document.getElementById('nbAutoSeatPreviewBody');
    if (!emptyEl || !wrapEl || !bodyEl) return;

    const seats = batchSeatPreview;
    if (!seats.length) {
      emptyEl.classList.remove('d-none');
      emptyEl.textContent = 'No elected seats are configured in the council records yet.';
      wrapEl.classList.add('d-none');
      bodyEl.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No elected seats are available for this batch.</td></tr>';
      return;
    }

    emptyEl.classList.add('d-none');
    wrapEl.classList.remove('d-none');
    bodyEl.innerHTML = seats.map((seat) => {
      const holder = String(seat.current_official_name || '').trim();
      return `
        <tr>
          <td class="fw-semibold">${esc(seat.seat_name || '—')}</td>
          <td><span class="badge bg-primary">${esc(seat.selection_method || 'Elected')}</span></td>
          <td>${esc(seat.seat_group || '—')}</td>
          <td>${holder ? esc(holder) : '<span class="text-muted fst-italic">Vacant</span>'}</td>
          <td>${batchSeatStatusBadge(seat.account_status)}</td>
        </tr>
      `;
    }).join('');
  }

  document.getElementById('formNewBatch')?.addEventListener('submit', async (e) => {
    e.preventDefault();

    const btn = document.getElementById('btnSubmitNewBatch');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Creating…';

    const fd = new FormData(e.target);
    fd.append('action', 'new_batch');
    const params = {};
    fd.forEach((v, k) => {
      if (params[k]) {
        if (!Array.isArray(params[k])) params[k] = [params[k]];
        params[k].push(v);
      } else {
        params[k] = v;
      }
    });

    const body = new URLSearchParams();
    body.append('action', 'new_batch');
    body.append('batch_label', fd.get('batch_label') || '');
    body.append('proclamation_date', fd.get('proclamation_date') || '');
    body.append('next_election_date', fd.get('next_election_date') || '');

    const res = await fetch(API, { method: 'POST', body, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
    const data = await res.json();

    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-layer-group me-1"></i> Create Term';

    if (data.success) {
      showToast(data.message || 'Term created.');
      getModal('modalNewBatch')?.hide();
      e.target.reset();
      renderBatchSeatPreview();
      window.location.href = 'OfficialTransitions.php?tool=current_term';
    } else {
      showToast(data.message || 'Failed.', 'error');
    }
  });

  document.getElementById('modalNewBatch')?.addEventListener('shown.bs.modal', renderBatchSeatPreview);

  // ══════════════════════════════════════════════════════════════════════════
  // ADD ELECTION DATE
  // ══════════════════════════════════════════════════════════════════════════
  const editSchedule = window.OT_EDIT_SCHEDULE && typeof window.OT_EDIT_SCHEDULE === 'object'
    ? window.OT_EDIT_SCHEDULE
    : null;
  const formAddElection = document.getElementById('formAddElection');
  const aeBatchLabel = document.getElementById('aeBatchLabel');
  const aeOriginalBatchLabel = document.getElementById('aeOriginalBatchLabel');
  const aeProclamationDate = document.getElementById('aeProclamationDate');
  const aeProclamationDateHidden = document.getElementById('aeProclamationDateHidden');
  const aeNextElectionDate = document.getElementById('aeNextElectionDate');
  const aeNextElectionHelp = document.getElementById('aeNextElectionHelp');
  const aeNoEditableScheduleAlert = document.getElementById('aeNoEditableScheduleAlert');
  const btnSubmitEditTermDetails = document.getElementById('btnSubmitEditTermDetails');

  function configureEditTermForm() {
    if (!formAddElection || !aeBatchLabel || !aeProclamationDate || !aeNextElectionDate) return;

    const batchLabel = String(editSchedule?.batch_label || '').trim();
    const proclamationDate = String(editSchedule?.proclamation_date || '').trim();
    const nextElectionDate = String(editSchedule?.next_election_date || '').trim();

    aeBatchLabel.value = batchLabel;
    aeProclamationDate.value = proclamationDate;
    aeNextElectionDate.value = nextElectionDate;
    if (aeOriginalBatchLabel) aeOriginalBatchLabel.value = batchLabel;
    if (aeProclamationDateHidden) aeProclamationDateHidden.value = proclamationDate;

    if (!batchLabel || !nextElectionDate) {
      aeNextElectionDate.disabled = true;
      aeNextElectionDate.removeAttribute('min');
      aeNextElectionDate.removeAttribute('max');
      delete aeNextElectionDate.dataset.lockedYear;
      delete aeNextElectionDate.dataset.originalValue;
      aeNoEditableScheduleAlert?.classList.remove('d-none');
      if (btnSubmitEditTermDetails) btnSubmitEditTermDetails.disabled = true;
      return;
    }

    aeNextElectionDate.disabled = false;
    aeNoEditableScheduleAlert?.classList.add('d-none');
    if (btnSubmitEditTermDetails) btnSubmitEditTermDetails.disabled = false;

    const lockedYear = nextElectionDate.slice(0, 4);
    aeNextElectionDate.min = `${lockedYear}-01-01`;
    aeNextElectionDate.max = `${lockedYear}-12-31`;
    aeNextElectionDate.dataset.lockedYear = lockedYear;
    aeNextElectionDate.dataset.originalValue = nextElectionDate;
    if (aeNextElectionHelp) {
      aeNextElectionHelp.textContent = `You can adjust the month and day, but the election year stays locked to ${lockedYear}.`;
    }
  }

  function enforceEditTermYearLock() {
    if (!aeNextElectionDate) return true;
    const lockedYear = aeNextElectionDate.dataset.lockedYear || '';
    const value = aeNextElectionDate.value || '';
    if (!lockedYear || !value) return true;
    if (!value.startsWith(`${lockedYear}-`)) {
      showToast(`Next election year is locked to ${lockedYear}.`, 'warning');
      aeNextElectionDate.value = aeNextElectionDate.dataset.originalValue || '';
      return false;
    }
    aeNextElectionDate.dataset.originalValue = value;
    return true;
  }

  document.getElementById('modalAddElection')?.addEventListener('show.bs.modal', configureEditTermForm);
  aeNextElectionDate?.addEventListener('change', enforceEditTermYearLock);

  formAddElection?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!enforceEditTermYearLock()) return;
    const fd = new FormData(e.target);
    fd.append('action', 'update_election_date');
    const params = {};
    fd.forEach((v, k) => params[k] = v);

    const data = await apiFetch(params, 'POST');
    if (data.success) {
      showToast(data.message || 'Saved.');
      getModal('modalAddElection')?.hide();
      e.target.reset();
      location.reload();
    } else {
      showToast(data.message || 'Failed.', 'error');
    }
  });

  // ══════════════════════════════════════════════════════════════════════════
  // CANDIDATES MODAL
  // ══════════════════════════════════════════════════════════════════════════
  window.otOpenCandidates = function (transitionId) {
    document.getElementById('candidatesTransitionId').value = transitionId;
    if (lastNameEl) lastNameEl.value = '';
    if (firstNameEl) firstNameEl.value = '';
    if (middleNameEl) middleNameEl.value = '';
    if (suffixEl) suffixEl.value = '';
    if (emailEl) emailEl.value = '';
    if (mobileEl) mobileEl.value = '';
    if (linkedOfficialCache) linkedOfficialCache.clear();
    setFormerOfficialMode('');
    const notesEl = document.getElementById('newCandidateNotes');
    if (notesEl) notesEl.value = '';
    getModal('modalCandidates')?.show();
    loadCandidates(transitionId);
  };

  async function loadCandidates(transitionId) {
    const listEl = document.getElementById('candidatesList');
    const posLabel = document.getElementById('candidatesModalPositionLabel');
    const outInfo  = document.getElementById('outgoingOfficialInfo');
    listEl.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-2"></i></div>';

    const data = await apiFetch({ action: 'fetch_candidates', transition_id: transitionId });
    if (!data.success) { listEl.innerHTML = '<p class="text-danger">Failed to load official records.</p>'; return; }

    const t   = data.transition || {};
    const pos = t.position || transitionId;
    if (posLabel) posLabel.textContent = pos;
    document.getElementById('candidatesTransitionStatus').value = t.status || '';

    if (t.outgoing_name) {
      outInfo.classList.remove('d-none');
      document.getElementById('outgoingOfficialName').textContent     = t.outgoing_name;
      document.getElementById('outgoingOfficialPosition').textContent = t.outgoing_position || '';
    } else {
      outInfo.classList.add('d-none');
    }

    const currentOfficial = (data.candidates || [])[0] || null;
    if (!currentOfficial) {
      listEl.innerHTML = '<p class="text-muted small text-center py-2 mb-0">No incoming official information is saved yet for this position. Fill out the form below, then click Continue to Access Review.</p>';
      return;
    }

    await applyEncodedOfficialToForm(currentOfficial);

    const typePill = candidateTypePill(currentOfficial.linked_official_id ? 'ReturningOfficial' : 'New');
    const displayName = formatAccessEntryName(currentOfficial);
    const emailLine = String(currentOfficial.candidate_email || '').trim();
    const mobileLine = formatMobileDisplay(currentOfficial.candidate_mobile || currentOfficial.candidate_contact || '');
    listEl.innerHTML = `
      <div class="border rounded p-3 bg-light">
        <div class="fw-semibold text-muted small mb-2">Currently Saved Official</div>
        <div class="fw-semibold">${esc(displayName)} ${typePill}</div>
        ${emailLine ? `<div class="text-muted small">${esc(emailLine)}</div>` : ''}
        ${mobileLine ? `<div class="text-muted small">${esc(mobileLine)}</div>` : ''}
        ${currentOfficial.notes ? `<div class="text-muted small fst-italic mt-1">${esc(currentOfficial.notes)}</div>` : ''}
        <div class="small text-muted mt-2">Editing the form below will replace this saved official when you continue to access review.</div>
      </div>`;
  }

  function candidateTypePill(type) {
    const map = {
      New:             ['bg-primary text-white', 'New Official'],
      ReturningOfficial:['bg-info text-dark',    'Former Official Match'],
      ActiveOfficial:  ['bg-warning text-dark',  'Existing Official'],
      Resident:        ['bg-secondary text-white','Resident Record'],
    };
    const [cls, label] = map[type] || ['bg-light text-dark', type];
    return `<span class="badge ${cls} candidate-type-badge ms-1">${label}</span>`;
  }

  function formatAccessEntryName(entry) {
    const lastName = String(entry?.candidate_last_name || '').trim();
    const firstName = String(entry?.candidate_first_name || '').trim();
    const middleName = String(entry?.candidate_middle_name || '').trim();
    const suffix = String(entry?.candidate_suffix || '').trim();
    if (!lastName && !firstName) {
      return String(entry?.candidate_name || '—').trim() || '—';
    }

    let name = `${lastName}, ${firstName}`.trim();
    if (middleName) name += ` ${middleName}`;
    if (suffix) name += ` ${suffix}`;
    return name.trim();
  }

  function normalizeMobileDigits(value) {
    const digits = String(value || '').replace(/[^0-9]/g, '');
    if (!digits) return '';
    if (digits.length === 10 && digits.startsWith('9')) return digits;
    if (digits.length >= 12 && digits.startsWith('639')) return digits.slice(-10);
    if (digits.length >= 11 && digits.startsWith('09')) return digits.slice(-10);
    return digits.slice(-10);
  }

  function formatMobileDisplay(value) {
    const normalized = normalizeMobileDigits(value);
    if (!normalized) return '';
    return `+63 ${normalized}`;
  }

  function isValidPhilippineMobile(value) {
    const normalized = normalizeMobileDigits(value);
    return normalized.length === 10 && normalized.startsWith('9');
  }

  const linkedIdWrap  = document.getElementById('linkedIdWrap');
  const linkedIdInput = document.getElementById('newCandidateLinkedId');
  const linkedIdLabel = document.getElementById('linkedIdLabel');
  const linkedSearchInput = document.getElementById('linkedOfficialSearch');
  const linkedSearchResults = document.getElementById('linkedOfficialSearchResults');
  const linkedSelectedEl = document.getElementById('linkedOfficialSelected');
  const formerOfficialModeEl = document.getElementById('formerOfficialMode');
  const newOfficialFieldsWrap = document.getElementById('newOfficialFieldsWrap');
  const lastNameEl    = document.getElementById('newCandidateLastName');
  const firstNameEl   = document.getElementById('newCandidateFirstName');
  const middleNameEl  = document.getElementById('newCandidateMiddleName');
  const suffixEl      = document.getElementById('newCandidateSuffix');
  const emailEl       = document.getElementById('newCandidateEmail');
  const mobileEl      = document.getElementById('newCandidateMobile');
  const linkedOfficialCache = new Map();
  let linkedSearchTimer = null;

  function setAccessFormFromOfficial(officialId) {
    const official = linkedOfficialCache.get(String(officialId || ''))
      || (window.OT_DATA?.activeOfficials || []).find((row) => String(row.official_id || '') === String(officialId || ''));
    if (!official) return;

    if (lastNameEl) lastNameEl.value = String(official.lastname || '').trim();
    if (firstNameEl) firstNameEl.value = String(official.firstname || '').trim();
    if (middleNameEl) middleNameEl.value = String(official.middlename || '').trim();
    if (suffixEl) suffixEl.value = String(official.suffix || '').trim();
    if (emailEl) emailEl.value = String(official.email || '').trim();

    const normalizedMobile = normalizeMobileDigits(official.phone_number || official.candidate_mobile || official.candidate_contact || '');
    if (mobileEl && normalizedMobile) mobileEl.value = normalizedMobile.slice(-10);
  }

  function linkedOfficialDisplayName(official) {
    return `${official.lastname || ''}, ${official.firstname || ''}${official.position ? ` — ${official.position}` : ''}`
      .replace(/^,\s*/, '')
      .trim();
  }

  function clearLinkedOfficialUi() {
    if (linkedIdInput) linkedIdInput.value = '';
    if (linkedSearchInput) linkedSearchInput.value = '';
    if (linkedSelectedEl) {
      linkedSelectedEl.textContent = '';
      linkedSelectedEl.classList.add('d-none');
    }
    if (linkedSearchResults) {
      linkedSearchResults.innerHTML = '';
      linkedSearchResults.classList.add('d-none');
    }
  }

  function setFormerOfficialMode(mode = '') {
    if (formerOfficialModeEl) formerOfficialModeEl.value = mode;
    const isFormer = mode === 'former';
    const isNew = mode === 'new';
    if (linkedIdWrap) linkedIdWrap.style.display = isFormer ? '' : 'none';
    if (newOfficialFieldsWrap) newOfficialFieldsWrap.style.display = isNew ? '' : 'none';
    if (!isFormer) {
      clearLinkedOfficialUi();
    }
  }

  function renderLinkedOfficialResults(officials, type, query = '') {
    if (!linkedSearchResults) return;

    if (!officials.length) {
      const emptyLabel = type === 'ReturningOfficial'
        ? (query ? 'No matching former official found.' : 'No returning former officials found.')
        : (query ? 'No matching active official found.' : 'No active official found.');
      linkedSearchResults.innerHTML = `<div class="p-2 small text-muted">${esc(emptyLabel)}</div>`;
      linkedSearchResults.classList.remove('d-none');
      return;
    }

    linkedSearchResults.innerHTML = officials.map((official) => {
      const displayName = linkedOfficialDisplayName(official);
      const detailBits = [official.official_id, official.department, official.account_status].filter(Boolean);
      return `
        <button type="button" class="linked-search-item w-100 text-start border-0 bg-transparent p-2 border-bottom"
                data-official-id="${esc(official.official_id)}">
          <div class="fw-semibold">${esc(displayName)}</div>
          ${detailBits.length ? `<div class="small text-muted">${esc(detailBits.join(' • '))}</div>` : ''}
        </button>
      `;
    }).join('');
    linkedSearchResults.classList.remove('d-none');
  }

  function selectLinkedOfficial(officialId) {
    const official = linkedOfficialCache.get(String(officialId || ''));
    if (!official) return;

    if (linkedIdInput) linkedIdInput.value = String(officialId);
    if (linkedSearchInput) linkedSearchInput.value = linkedOfficialDisplayName(official);
    if (linkedSelectedEl) {
      linkedSelectedEl.textContent = `Former official selected: ${linkedOfficialDisplayName(official)}`;
      linkedSelectedEl.classList.remove('d-none');
    }
    if (linkedSearchResults) linkedSearchResults.classList.add('d-none');
    setAccessFormFromOfficial(officialId);
  }

  async function applyEncodedOfficialToForm(entry) {
    if (lastNameEl) lastNameEl.value = String(entry.candidate_last_name || '').trim();
    if (firstNameEl) firstNameEl.value = String(entry.candidate_first_name || '').trim();
    if (middleNameEl) middleNameEl.value = String(entry.candidate_middle_name || '').trim();
    if (suffixEl) suffixEl.value = String(entry.candidate_suffix || '').trim();
    if (emailEl) emailEl.value = String(entry.candidate_email || '').trim();
    if (mobileEl) mobileEl.value = normalizeMobileDigits(entry.candidate_mobile || entry.candidate_contact || '');
    document.getElementById('newCandidateNotes').value = String(entry.notes || '').trim();

    const linkedOfficialId = String(entry.linked_official_id || '').trim();
    if (linkedOfficialId) {
      setFormerOfficialMode('former');
      await populateLinkedOfficialSelect();
      selectLinkedOfficial(linkedOfficialId);
    } else {
      setFormerOfficialMode('new');
    }
  }

  async function populateLinkedOfficialSelect(query = '') {
    linkedOfficialCache.clear();

    const data = await apiFetch({ action: 'fetch_inactive_officials', q: query });
    const officials = (data.data || []).filter((row) => Number(row.can_return || 0) === 1);

    if (linkedIdLabel) linkedIdLabel.textContent = 'Search Former Officials';
    if (linkedSearchInput) linkedSearchInput.placeholder = 'Search former official by name, ID, or position';

    officials.forEach((o) => {
      const officialId = String(o.official_id || '').trim();
      if (!officialId) return;
      linkedOfficialCache.set(officialId, o);
    });
    renderLinkedOfficialResults(officials, 'ReturningOfficial', query);
    return officials;
  }

  linkedSearchInput?.addEventListener('input', () => {
    if ((formerOfficialModeEl?.value || '') !== 'former') return;
    if (linkedIdInput) linkedIdInput.value = '';
    if (linkedSelectedEl) {
      linkedSelectedEl.textContent = '';
      linkedSelectedEl.classList.add('d-none');
    }
    clearTimeout(linkedSearchTimer);
    linkedSearchTimer = setTimeout(() => {
      populateLinkedOfficialSelect(linkedSearchInput.value.trim());
    }, 250);
  });

  linkedSearchInput?.addEventListener('focus', () => {
    if ((formerOfficialModeEl?.value || '') !== 'former') return;
    populateLinkedOfficialSelect(linkedSearchInput.value.trim());
  });

  formerOfficialModeEl?.addEventListener('change', () => {
    const mode = formerOfficialModeEl.value || '';
    setFormerOfficialMode(mode);
    if (mode === 'former') {
      populateLinkedOfficialSelect();
      linkedSearchInput?.focus();
    }
  });

  linkedSearchResults?.addEventListener('click', (event) => {
    const option = event.target.closest('[data-official-id]');
    if (!option) return;
    selectLinkedOfficial(option.getAttribute('data-official-id') || '');
  });

  async function saveCurrentOfficialInformation({ showSuccessToast = false } = {}) {
    const transitionId = document.getElementById('candidatesTransitionId').value;
    const notes        = document.getElementById('newCandidateNotes').value.trim();
    const linkedId     = linkedIdInput?.value || '';
    const selectedMode = formerOfficialModeEl?.value || '';
    const isFormerOfficial = selectedMode === 'former';
    const type         = linkedId ? 'ReturningOfficial' : 'New';
    const lastName     = lastNameEl?.value.trim() || '';
    const firstName    = firstNameEl?.value.trim() || '';
    const middleName   = middleNameEl?.value.trim() || '';
    const suffix       = suffixEl?.value.trim() || '';
    const email        = emailEl?.value.trim() || '';
    const mobile       = normalizeMobileDigits(mobileEl?.value || '');

    if (!selectedMode) { showToast('Choose first whether this is a former official or a new official.', 'warning'); formerOfficialModeEl?.focus(); return null; }
    if (isFormerOfficial && !linkedId) { showToast('Search and select the former official first, or choose No if this is a new official.', 'warning'); linkedSearchInput?.focus(); return null; }
    if (!lastName || !firstName) { showToast('Last name and first name are required.', 'warning'); return null; }
    if (!emailEl?.checkValidity() || !email) { showToast('Enter a valid email address.', 'warning'); emailEl?.focus(); return null; }
    if (!isValidPhilippineMobile(mobile)) { showToast('Mobile number must be a valid 10-digit Philippine mobile number.', 'warning'); mobileEl?.focus(); return null; }

    const params = {
      action: 'add_candidate',
      transition_id:         transitionId,
      candidate_type:        type,
      candidate_first_name:  firstName,
      candidate_last_name:   lastName,
      candidate_middle_name: middleName,
      candidate_suffix:      suffix,
      candidate_email:       email,
      candidate_mobile:      mobile,
      notes,
    };
    if (linkedId) params.linked_official_id = linkedId;

    const data = await apiFetch(params, 'POST');
    if (data.success) {
      if (showSuccessToast) {
        showToast(data.message || 'Official information saved.');
      }
      return data;
    } else {
      showToast(data.message || 'Failed.', 'error');
      return null;
    }
  }

  window.otRemoveCandidate = async function (upcomingId, transitionId) {
    if (!confirm('Remove this official from the list?')) return;
    const data = await apiFetch({ action: 'remove_candidate', upcoming_id: upcomingId }, 'POST');
    if (data.success) {
      showToast('Official removed.');
      loadCandidates(transitionId);
      loadTransitions();
    } else {
      showToast(data.message || 'Failed.', 'error');
    }
  };

  document.getElementById('btnMarkPendingDecision')?.addEventListener('click', async () => {
    const transitionId = document.getElementById('candidatesTransitionId').value;
    const btn = document.getElementById('btnMarkPendingDecision');
    if (!transitionId || !btn) return;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving…';

    const saveData = await saveCurrentOfficialInformation();
    if (!saveData) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-key me-1"></i> Continue to Access Review';
      return;
    }

    const data = await apiFetch({ action: 'mark_pending_decision', transition_id: transitionId }, 'POST');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-key me-1"></i> Continue to Access Review';

    if (data.success) {
      showToast('Official information saved. Continue with the final access action.');
      getModal('modalCandidates')?.hide();
      loadTransitions();
      updateStats();
      setTimeout(() => {
        if (typeof window.otOpenWinner === 'function') {
          window.otOpenWinner(transitionId);
        }
      }, 180);
    } else {
      loadCandidates(transitionId);
      showToast(data.message || 'Failed.', 'error');
    }
  });

  // ══════════════════════════════════════════════════════════════════════════
  // SELECT WINNER MODAL
  // ══════════════════════════════════════════════════════════════════════════
  function getAutoOutcomeMeta(currentOfficial) {
    const isFormerOfficial = Boolean(String(currentOfficial?.linked_official_id || '').trim());
    if (isFormerOfficial) {
      return {
        outcome: 'Reactivated',
        label: 'Returning Former Official',
        description: 'The matched account will be reactivated, the saved contact details will be updated, and a fresh onboarding access link will be sent.',
      };
    }

    return {
      outcome: 'NewPerson',
      label: 'First-time Official',
      description: 'A new official account shell will be prepared for this person and their onboarding access will be sent immediately.',
    };
  }

  window.otOpenWinner = function (transitionId) {
    document.getElementById('winnerTransitionId').value = transitionId;
    document.getElementById('winnerOutcome').value      = '';
    const summaryEl = document.getElementById('winnerOutcomeSummary');
    if (summaryEl) {
      summaryEl.innerHTML = 'The system will determine the access action after the official information is loaded.';
    }
    getModal('modalSelectWinner')?.show();
    loadWinnerCandidates(transitionId);
  };

  async function loadWinnerCandidates(transitionId) {
    const listEl = document.getElementById('winnerCandidatesList');
    listEl.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-2"></i></div>';
    listEl.dataset.selectedCandidateId = '';

    const data = await apiFetch({ action: 'fetch_candidates', transition_id: transitionId });
    const currentOfficial = (data.candidates || [])[0] || null;

    if (!currentOfficial) {
      listEl.innerHTML = '<p class="text-muted small text-center">No official information is saved yet. Go back and complete the access setup first.</p>';
      return;
    }

    listEl.dataset.selectedCandidateId = String(currentOfficial.upcoming_id || '');

    const outcomeMeta = getAutoOutcomeMeta(currentOfficial);
    const mappedOutcome = outcomeMeta.outcome;
    const winnerOutcomeEl = document.getElementById('winnerOutcome');
    const winnerOutcomeSummaryEl = document.getElementById('winnerOutcomeSummary');
    if (winnerOutcomeEl) {
      winnerOutcomeEl.value = mappedOutcome;
    }
    if (winnerOutcomeSummaryEl) {
      winnerOutcomeSummaryEl.innerHTML = `
        <div class="fw-semibold text-dark">${esc(outcomeMeta.label)}</div>
        <div class="small text-muted mt-1">${esc(outcomeMeta.description)}</div>
      `;
    }

    const emailLine = String(currentOfficial.candidate_email || '').trim();
    const mobileLine = formatMobileDisplay(currentOfficial.candidate_mobile || currentOfficial.candidate_contact || '');
    listEl.innerHTML = `
      <div class="border rounded p-3 bg-light">
        <div class="fw-semibold small text-muted mb-2">Saved Official Record</div>
        <div class="fw-semibold">${esc(formatAccessEntryName(currentOfficial))} ${candidateTypePill(currentOfficial.linked_official_id ? 'ReturningOfficial' : 'New')}</div>
        ${emailLine ? `<small class="text-muted d-block">${esc(emailLine)}</small>` : ''}
        ${mobileLine ? `<small class="text-muted d-block">${esc(mobileLine)}</small>` : ''}
        ${currentOfficial.notes ? `<small class="text-muted fst-italic d-block mt-1">${esc(currentOfficial.notes)}</small>` : ''}
      </div>`;
  }

  document.getElementById('btnCompleteTransition')?.addEventListener('click', async () => {
    const transitionId = document.getElementById('winnerTransitionId').value;
    const outcome      = document.getElementById('winnerOutcome').value;
    const listEl       = document.getElementById('winnerCandidatesList');
    const selectedRadio= document.querySelector('input[name="winnerCandidate"]:checked');
    const selectedCandidateId = listEl?.dataset.selectedCandidateId || selectedRadio?.value || 0;

    if (!outcome) { showToast('The access action could not be determined. Re-open the first modal and complete the official information.', 'warning'); return; }
    if (!selectedCandidateId) { showToast('Complete the official information first.', 'warning'); return; }

    const btn = document.getElementById('btnCompleteTransition');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing…';

    const data = await apiFetch({
      action:               'complete_transition',
      transition_id:        transitionId,
      selected_candidate_id: selectedCandidateId,
      outcome,
    }, 'POST');

    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Complete and Notify';

    if (data.success) {
      showToast(data.message || 'Access setup completed.');
      if (data.invite_link && data.invite_email_sent === false) {
        window.prompt('Invite email was not sent automatically. Copy the onboarding link and send it manually:', String(data.invite_link));
      }
      getModal('modalSelectWinner')?.hide();
      loadTransitions();
      updateStats();
    } else {
      showToast(data.message || 'Failed.', 'error');
    }
  });

  // ══════════════════════════════════════════════════════════════════════════
  // CANCEL TRANSITION
  // ══════════════════════════════════════════════════════════════════════════
  window.otCancelTransition = async function (transitionId) {
    const reason = prompt(`Cancel transition ${transitionId}?\n\nOptional reason:`);
    if (reason === null) return; // user dismissed
    const data = await apiFetch({ action: 'cancel_transition', transition_id: transitionId, reason: reason || '' }, 'POST');
    if (data.success) {
      showToast('Transition cancelled.');
      loadTransitions();
      updateStats();
    } else {
      showToast(data.message || 'Failed.', 'error');
    }
  };

  // ══════════════════════════════════════════════════════════════════════════
  // QUICK ACTIONS
  // ══════════════════════════════════════════════════════════════════════════
  document.getElementById('btnQaRestoreAccess')?.addEventListener('click', () => {
    getModal('modalQuickActions')?.hide();
    getModal('modalRestoreAccess')?.show();
    loadInactiveOfficials();
  });

  document.getElementById('btnQaChangeCredentials')?.addEventListener('click', () => {
    getModal('modalQuickActions')?.hide();
    otCredStep(1);
    getModal('modalChangeCredentials')?.show();
    loadCredOfficials();
  });

  document.getElementById('btnQaEndActing')?.addEventListener('click', () => {
    getModal('modalQuickActions')?.hide();
    endActingFlow();
  });

  document.getElementById('btnQaChangePosition')?.addEventListener('click', () => {
    getModal('modalQuickActions')?.hide();
    showToast('Use New Official Handover → Appointment/Reappointment for a direct position change.', 'warning');
  });

  // ── Restore Access ─────────────────────────────────────────────────────────
  function inactiveOfficialsRows(officials) {
    return officials.map(o => {
      const transitionOut = [o.transition_out_type, o.transition_out_date ? fmtDate(o.transition_out_date) : '']
        .filter(Boolean)
        .join(' • ');
      return `
        <tr>
          <td>${esc(o.full_name)}</td>
          <td>${esc(o.position || '—')}</td>
          <td><span class="badge bg-secondary">${esc(o.account_status || 'Inactive')}</span></td>
          <td>${transitionOut ? esc(transitionOut) : '<span class="text-muted small">—</span>'}</td>
          <td>${o.can_return == 1 ? '<span class="text-success"><i class="fas fa-check"></i></span>' : '<span class="text-danger"><i class="fas fa-times"></i></span>'}</td>
          <td>
            <button class="btn btn-xs btn-success py-0 px-2" onclick="otRestoreOfficial('${esc(o.official_id)}','${esc(o.full_name)}')">
              <i class="fas fa-unlock me-1"></i> Restore
            </button>
          </td>
        </tr>`;
    }).join('');
  }

  async function loadInactiveOfficials(q = '') {
    const listEl = document.getElementById('restoreOfficialsList');
    if (!listEl) return;
    listEl.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-2"></i></div>';
    const data = await apiFetch({ action: 'fetch_inactive_officials', q });
    const officials = data.data || [];

    if (!officials.length) {
      listEl.innerHTML = '<p class="text-muted small text-center py-2">No inactive officials found.</p>';
      return;
    }

    listEl.innerHTML = `<div class="table-responsive"><table class="table table-sm align-middle mb-0">
      <thead class="table-light"><tr><th>Name</th><th>Position</th><th>Status</th><th>Transition Out</th><th>Can Return</th><th></th></tr></thead>
      <tbody>
        ${inactiveOfficialsRows(officials)}
      </tbody></table></div>`;
  }

  async function loadPastOfficials(q = '') {
    const bodyEl = document.getElementById('otPastOfficialsBody');
    if (!bodyEl) return;
    bodyEl.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin me-2"></i> Loading…</td></tr>';
    const data = await apiFetch({ action: 'fetch_inactive_officials', q });
    const officials = data.data || [];

    if (!officials.length) {
      bodyEl.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No past officials found.</td></tr>';
      return;
    }

    bodyEl.innerHTML = inactiveOfficialsRows(officials);
  }

  let restoreTimer;
  document.getElementById('restoreSearch')?.addEventListener('input', function () {
    clearTimeout(restoreTimer);
    restoreTimer = setTimeout(() => loadInactiveOfficials(this.value.trim()), 350);
  });

  let pastOfficialsTimer;
  document.getElementById('otPastOfficialsSearch')?.addEventListener('input', function () {
    clearTimeout(pastOfficialsTimer);
    pastOfficialsTimer = setTimeout(() => loadPastOfficials(this.value.trim()), 350);
  });

  window.otRestoreOfficial = async function (officialId, name) {
    if (!confirm(`Restore access for ${name}?`)) return;
    const data = await apiFetch({ action: 'restore_access', official_id: officialId }, 'POST');
    if (data.success) {
      showToast(data.message || 'Access restored.');
      loadInactiveOfficials();
      loadPastOfficials(document.getElementById('otPastOfficialsSearch')?.value.trim() || '');
      loadTransitions();
    } else {
      showToast(data.message || 'Failed.', 'error');
    }
  };

  // ── Change Credentials ─────────────────────────────────────────────────────
  async function loadCredOfficials(q = '') {
    const listEl = document.getElementById('credOfficialsList');
    listEl.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-2"></i></div>';
    // Fetch all officials (active)
    const data = await apiFetch({ action: 'fetch_inactive_officials', q: '' });
    const active = window.OT_DATA?.activeOfficials || [];
    const officials = active.filter(o => !q || o.firstname?.toLowerCase().includes(q.toLowerCase()) || o.lastname?.toLowerCase().includes(q.toLowerCase()));

    if (!officials.length) {
      listEl.innerHTML = '<p class="text-muted small text-center py-2">No officials found.</p>';
      return;
    }

    listEl.innerHTML = officials.map(o => `
      <div class="d-flex align-items-center justify-content-between border rounded p-2 mb-2">
        <div>
          <span class="fw-semibold">${esc(o.lastname)}, ${esc(o.firstname)}</span>
          <small class="text-muted ms-2">${esc(o.position || '')}</small>
        </div>
        <button class="btn btn-xs btn-outline-primary py-0 px-2" onclick="otSelectCredOfficial('${esc(o.official_id)}','${esc(o.lastname+', '+o.firstname)}')">
          Select
        </button>
      </div>`).join('');
  }

  let credTimer;
  document.getElementById('credSearch')?.addEventListener('input', function () {
    clearTimeout(credTimer);
    credTimer = setTimeout(() => loadCredOfficials(this.value.trim()), 350);
  });

  window.otSelectCredOfficial = function (officialId, name) {
    document.getElementById('credOfficialId').value = officialId;
    document.getElementById('credOfficialNameLabel').textContent = name;
    otCredStep(2);
  };

  window.otCredStep = function (step) {
    document.getElementById('credStep1').classList.toggle('d-none', step !== 1);
    document.getElementById('credStep2').classList.toggle('d-none', step !== 2);
    document.getElementById('btnCredBack').style.display  = step === 2 ? '' : 'none';
    document.getElementById('btnCredSave').style.display  = step === 2 ? '' : 'none';
  };

  document.getElementById('btnCredSave')?.addEventListener('click', async () => {
    const officialId    = document.getElementById('credOfficialId').value;
    const email         = document.getElementById('credEmail').value.trim();
    const phone         = document.getElementById('credPhone').value.trim();
    const forceReset    = document.getElementById('credForcePasswordReset').checked ? 1 : 0;

    const data = await apiFetch({
      action: 'change_credentials',
      official_id: officialId,
      email, phone,
      force_password_reset: forceReset,
    }, 'POST');

    if (data.success) {
      showToast(data.message || 'Credentials updated.');
      getModal('modalChangeCredentials')?.hide();
    } else {
      showToast(data.message || 'Failed.', 'error');
    }
  });

  // ── End Acting ─────────────────────────────────────────────────────────────
  async function endActingFlow() {
    // Find officials with acting_for_id set
    const actingOfficials = (window.OT_DATA?.activeOfficials || []).filter(o => o.acting_for_id);
    if (!actingOfficials.length) {
      showToast('No acting officials found.', 'warning');
      return;
    }
    const names = actingOfficials.map((o, i) => `${i + 1}. ${o.lastname}, ${o.firstname} (${o.position || ''})`).join('\n');
    const choice = prompt(`Acting officials:\n${names}\n\nEnter the number of the acting official to end:`);
    if (!choice) return;
    const idx = parseInt(choice) - 1;
    if (isNaN(idx) || !actingOfficials[idx]) { showToast('Invalid selection.', 'warning'); return; }
    const picked = actingOfficials[idx];
    if (!confirm(`End acting assignment for ${picked.lastname}, ${picked.firstname}?`)) return;
    const data = await apiFetch({ action: 'end_acting', acting_official_id: picked.official_id }, 'POST');
    if (data.success) {
      showToast(data.message || 'Acting assignment ended.');
      loadTransitions();
    } else {
      showToast(data.message || 'Failed.', 'error');
    }
  }

  // ══════════════════════════════════════════════════════════════════════════
  // RESEND NOTIFICATION
  // ══════════════════════════════════════════════════════════════════════════
  window.otResendNotif = async function (batchLabel, electionDate) {
    if (!confirm(`Reset notification flags for batch "${batchLabel}"?\n\nThe cron will re-evaluate and send pending notifications on its next run.`)) return;
    const data = await apiFetch({ action: 'resend_notification', batch_label: batchLabel, election_date: electionDate }, 'POST');
    if (data.success) {
      showToast(data.message || 'Flags reset.');
      location.reload();
    } else {
      showToast(data.message || 'Failed.', 'error');
    }
  };

  window.otDeleteSchedule = async function (batchLabel, electionDate) {
    if (!confirm(`Delete schedule "${batchLabel}" on ${electionDate}?\n\nThis will remove the schedule and all linked transition rows for that batch.`)) return;
    const data = await apiFetch({ action: 'delete_schedule', batch_label: batchLabel, election_date: electionDate }, 'POST');
    if (data.success) {
      showToast(data.message || 'Schedule deleted.');
      location.reload();
    } else {
      showToast(data.message || 'Failed.', 'error');
    }
  };

  // ══════════════════════════════════════════════════════════════════════════
  // STATS UPDATE
  // ══════════════════════════════════════════════════════════════════════════
  async function updateStats() {
    const data = await apiFetch({ action: 'fetch_transitions', tab: 'active', limit: 1 });
    if (!data.success) return;
    // Re-fetch page for updated stat counts
    // Stats are rendered server-side but we can update from total counts
  }

  // ══════════════════════════════════════════════════════════════════════════
  // UTILS
  // ══════════════════════════════════════════════════════════════════════════
  function esc(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function fmtDate(str) {
    if (!str) return '—';
    const d = new Date(str + 'T00:00:00');
    return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
  }

  // ══════════════════════════════════════════════════════════════════════════
  // INIT
  // ══════════════════════════════════════════════════════════════════════════
  loadTransitions();
  loadPastOfficials();
  if (pageTool === 'create_new_term' && autoStart === 'new-batch') {
    window.setTimeout(() => {
      getModal('modalNewBatch')?.show();
    }, 180);
  }

})();
