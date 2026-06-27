/**
 * Official Transition — JS
 */
(function () {
  'use strict';

  const API = '../PhpFiles/Admin-End/officialTransitions.php';
  const API_URL = (() => {
    try {
      return new URL(API, window.location.href).toString();
    } catch (_) {
      return API;
    }
  })();

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
  function appendRequestEntries(target, params = {}) {
    Object.entries(params || {}).forEach(([key, value]) => {
      if (Array.isArray(value)) {
        value.forEach((entry) => target.append(key, entry == null ? '' : String(entry)));
        return;
      }
      target.append(key, value == null ? '' : String(value));
    });
    return target;
  }

  function describeRequestFailure(error, fallbackLabel = 'Request failed') {
    const name = String(error?.name || '').trim();
    const message = String(error?.message || '').trim();
    if (name && message) {
      return `${fallbackLabel}: ${name} - ${message}`;
    }
    return `${fallbackLabel}: ${message || 'Unknown error.'}`;
  }

  async function apiFetch(params = {}, method = 'GET') {
    const normalizedMethod = String(method || 'GET').toUpperCase();
    let url = API_URL;
    let body = null;

    if (normalizedMethod === 'GET') {
      const qs = appendRequestEntries(new URLSearchParams(), params).toString();
      if (qs) url += (url.includes('?') ? '&' : '?') + qs;
    } else {
      body = appendRequestEntries(new FormData(), params);
    }

    let res;
    try {
      res = await fetch(url, {
        method: normalizedMethod,
        ...(body ? { body } : {})
      });
    } catch (error) {
      throw new Error(describeRequestFailure(error, `Request to ${params?.action || 'official transitions'} failed before reaching the server`));
    }

    const rawText = await res.text();
    try {
      return rawText ? JSON.parse(rawText) : {};
    } catch (error) {
      const preview = rawText.trim().slice(0, 180);
      throw new Error(
        `Server returned an unreadable response for ${params?.action || 'official transitions'} ` +
        `(HTTP ${res.status}).${preview ? ` Response preview: ${preview}` : ''}`
      );
    }
  }

  async function requestSecureConfirmation(secureAction, payload = {}, actionLabel = 'this action') {
    const actorPassword = window.prompt(`Enter your current password to continue with ${actionLabel}:`);
    if (actorPassword === null) return null;
    if (!String(actorPassword).trim()) {
      throw new Error('Password confirmation is required.');
    }

    const requestPayload = {
      action: 'request_secure_action_otp',
      secure_action: secureAction,
      actor_password: actorPassword,
      ...payload,
    };
    let otpRequest;
    try {
      otpRequest = await apiFetch(requestPayload, 'POST');
    } catch (error) {
      throw new Error(describeRequestFailure(error, 'Secure confirmation could not start'));
    }
    if (!otpRequest?.success) {
      throw new Error(otpRequest?.message || 'Unable to send OTP.');
    }

    const deliveryLabel = String(otpRequest.delivery_label || '').trim();
    const otpCode = window.prompt(
      deliveryLabel
        ? `Enter the 6-digit OTP sent to ${deliveryLabel}:`
        : 'Enter the 6-digit OTP sent to your verified contact:'
    );
    if (otpCode === null) return null;

    return {
      challenge_key: String(otpRequest.challenge_key || ''),
      otp_code: String(otpCode || '').trim(),
    };
  }

  const pageTool = document.body?.dataset.otTool || 'current_term';
  const autoStart = document.body?.dataset.otAutostart || '';
  const emptyQueueMessage = pageTool === 'create_new_term'
    ? 'No governance cycle records yet. Create the cycle first, then encode the elected winners and appointed officials.'
    : 'No transitions found.';

  // ── Status badge ──────────────────────────────────────────────────────────
  function statusBadge(status) {
    const map = {
      PendingSuperAdminApproval: ['badge-ot-pending',   'Pending Review'],
      PendingAccessReview:       ['badge-ot-encoding',  'Pending Access'],
      Completed:                 ['badge-ot-completed', 'Completed'],
      Cancelled:                 ['badge-ot-cancelled', 'Cancelled'],
      Open:                      ['badge-ot-open',      'Open'],
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
        ? `<div><span class="badge bg-secondary">${esc(r.batch_label)}</span></div>`
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

    if (s !== 'Completed' && s !== 'Cancelled') {
      btns.push(`<button class="btn btn-xs btn-outline-primary py-0 px-2" onclick="otOpenCandidates('${esc(tid)}')" title="Prepare incoming seat holder and complete turnover"><i class="fas fa-user-plus me-1"></i>Complete Turnover</button>`);
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
  const ntReasonLbl  = document.getElementById('ntReasonLabel');
  const ntSeatWrap   = document.getElementById('ntSeatInfoWrap');

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
    if (ntReasonLbl) ntReasonLbl.textContent = 'Reason';
  });

  ntType?.addEventListener('change', () => {
    const v          = ntType.value;
    const isElection = ['BarangayElection','SKElection'].includes(v);
    const isRemoval  = v === 'Removal';
    if (ntBatchWrap) ntBatchWrap.style.display = isElection ? '' : 'none';
    if (ntReasonLbl) ntReasonLbl.textContent   = isRemoval ? 'Reason *' : 'Reason';
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
  // NEW GOVERNANCE CYCLE MODAL
  // ══════════════════════════════════════════════════════════════════════════
  const batchSeatPreview = Array.isArray(window.OT_BATCH_SEAT_PREVIEW) ? window.OT_BATCH_SEAT_PREVIEW : [];

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
      bodyEl.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No elected seats are available for this governance cycle.</td></tr>';
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
    body.append('effective_date', fd.get('effective_date') || '');

    const res = await fetch(API, { method: 'POST', body, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
    const data = await res.json();

    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-layer-group me-1"></i> Create Governance Cycle';

    if (data.success) {
      showToast(data.message || 'Governance cycle created.');
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
  // EDIT GOVERNANCE CYCLE LABEL
  // ══════════════════════════════════════════════════════════════════════════
  const editSchedule = window.OT_EDIT_SCHEDULE && typeof window.OT_EDIT_SCHEDULE === 'object'
    ? window.OT_EDIT_SCHEDULE
    : null;
  const formAddElection = document.getElementById('formAddElection');
  const aeBatchLabel = document.getElementById('aeBatchLabel');
  const aeOriginalBatchLabel = document.getElementById('aeOriginalBatchLabel');
  const aeNoEditableScheduleAlert = document.getElementById('aeNoEditableScheduleAlert');
  const btnSubmitEditTermDetails = document.getElementById('btnSubmitEditTermDetails');

  function configureEditTermForm() {
    if (!formAddElection || !aeBatchLabel) return;

    const batchLabel = String(editSchedule?.batch_label || '').trim();

    aeBatchLabel.value = batchLabel;
    if (aeOriginalBatchLabel) aeOriginalBatchLabel.value = batchLabel;

    if (!batchLabel) {
      aeNoEditableScheduleAlert?.classList.remove('d-none');
      if (btnSubmitEditTermDetails) btnSubmitEditTermDetails.disabled = true;
      return;
    }

    aeNoEditableScheduleAlert?.classList.add('d-none');
    if (btnSubmitEditTermDetails) btnSubmitEditTermDetails.disabled = false;
  }

  document.getElementById('modalAddElection')?.addEventListener('show.bs.modal', configureEditTermForm);

  formAddElection?.addEventListener('submit', async (e) => {
    e.preventDefault();
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
  const transitionDrafts = new Map();
  const transitionMetaCache = new Map();

  function clearAccessIdentityFields() {
    if (lastNameEl) lastNameEl.value = '';
    if (firstNameEl) firstNameEl.value = '';
    if (middleNameEl) middleNameEl.value = '';
    if (suffixEl) suffixEl.value = '';
    if (emailEl) emailEl.value = '';
    if (mobileEl) mobileEl.value = '';
  }

  function clearAccessForm() {
    clearAccessIdentityFields();
    if (linkedOfficialCache) linkedOfficialCache.clear();
    setFormerOfficialMode('');
    const notesEl = document.getElementById('newCandidateNotes');
    if (notesEl) notesEl.value = '';
  }

  window.otOpenCandidates = function (transitionId) {
    document.getElementById('candidatesTransitionId').value = transitionId;
    clearAccessForm();
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
    transitionMetaCache.set(String(transitionId || ''), t);
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

    const currentOfficial = transitionDrafts.get(String(transitionId || '')) || null;
    if (!currentOfficial) {
      listEl.innerHTML = '<p class="text-muted small text-center py-2 mb-0">No incoming official is staged yet for this transition. Fill out the form below, review it, then complete the transition.</p>';
      return;
    }

    await applyEncodedOfficialToForm(currentOfficial);

    const typePill = candidateTypePill(currentOfficial.candidate_type || (currentOfficial.linked_official_id ? 'ReturningOfficial' : 'New'));
    const displayName = formatAccessEntryName(currentOfficial);
    const emailLine = String(currentOfficial.candidate_email || '').trim();
    const mobileLine = formatMobileDisplay(currentOfficial.candidate_mobile || currentOfficial.candidate_contact || '');
    listEl.innerHTML = `
      <div class="border rounded p-3 bg-light">
        <div class="fw-semibold text-muted small mb-2">Current Transition Draft</div>
        <div class="fw-semibold">${esc(displayName)} ${typePill}</div>
        ${emailLine ? `<div class="text-muted small">${esc(emailLine)}</div>` : ''}
        ${mobileLine ? `<div class="text-muted small">${esc(mobileLine)}</div>` : ''}
        ${currentOfficial.notes ? `<div class="text-muted small fst-italic mt-1">${esc(currentOfficial.notes)}</div>` : ''}
        <div class="small text-muted mt-2">Editing the form below will replace this in-page draft until you complete the transition.</div>
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

  function usesExistingOfficialMode(mode) {
    return mode === 'former' || mode === 'active';
  }

  const linkedIdWrap  = document.getElementById('linkedIdWrap');
  const linkedIdInput = document.getElementById('newCandidateLinkedId');
  const linkedIdLabel = document.getElementById('linkedIdLabel');
  const linkedIdHelp = document.getElementById('linkedIdHelp');
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
  const winnerActingOptionsEl = document.getElementById('winnerActingOptions');
  const winnerUseActingReplacementEl = document.getElementById('winnerUseActingReplacement');
  const winnerActingUntilDateEl = document.getElementById('winnerActingUntilDate');
  const linkedOfficialCache = new Map();
  let linkedSearchTimer = null;

  function getTransitionMeta(transitionId) {
    return transitionMetaCache.get(String(transitionId || '')) || {};
  }

  function getExistingOfficialModeConfig(mode) {
    if (mode === 'active') {
      return {
        label: 'Search Active Officials',
        help: 'Search an active official record to move or temporarily assign that account to this position.',
        placeholder: 'Search active official by name, ID, or position',
        selectedPrefix: 'Active official selected',
      };
    }

    return {
      label: 'Search Former Officials',
      help: 'Search a former official record to auto-fill the details and reactivate the previous account.',
      placeholder: 'Search former official by name, ID, or position',
      selectedPrefix: 'Former official selected',
    };
  }

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
    if (mobileEl) mobileEl.value = normalizedMobile ? normalizedMobile.slice(-10) : '';
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
    const usesExistingRecord = usesExistingOfficialMode(mode);
    const isNew = mode === 'new';
    if (linkedIdWrap) linkedIdWrap.style.display = usesExistingRecord ? '' : 'none';
    if (newOfficialFieldsWrap) newOfficialFieldsWrap.style.display = isNew ? '' : 'none';
    if (usesExistingRecord) {
      const cfg = getExistingOfficialModeConfig(mode);
      if (linkedIdLabel) linkedIdLabel.textContent = cfg.label;
      if (linkedIdHelp) linkedIdHelp.textContent = cfg.help;
      if (linkedSearchInput) linkedSearchInput.placeholder = cfg.placeholder;
    } else {
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
    const mode = formerOfficialModeEl?.value || 'former';
    const cfg = getExistingOfficialModeConfig(mode);

    if (linkedIdInput) linkedIdInput.value = String(officialId);
    if (linkedSearchInput) linkedSearchInput.value = linkedOfficialDisplayName(official);
    if (linkedSelectedEl) {
      linkedSelectedEl.textContent = `${cfg.selectedPrefix}: ${linkedOfficialDisplayName(official)}`;
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
      setFormerOfficialMode(String(entry.linked_source_mode || 'former'));
      await populateLinkedOfficialSelect();
      selectLinkedOfficial(linkedOfficialId);
    } else {
      setFormerOfficialMode('new');
    }
  }

  async function populateLinkedOfficialSelect(query = '') {
    linkedOfficialCache.clear();
    const mode = formerOfficialModeEl?.value || '';
    const transitionId = document.getElementById('candidatesTransitionId')?.value || '';
    const transitionMeta = getTransitionMeta(transitionId);
    const outgoingOfficialId = String(transitionMeta.outgoing_official_id || '').trim();
    let officials = [];

    if (mode === 'active') {
      const qLower = String(query || '').trim().toLowerCase();
      officials = (window.OT_DATA?.activeOfficials || []).filter((row) => {
        const officialId = String(row.official_id || '').trim();
        if (!officialId || officialId === outgoingOfficialId) return false;
        if (!qLower) return true;
        const haystack = [
          row.official_id,
          row.firstname,
          row.lastname,
          row.position,
          row.department,
        ].join(' ').toLowerCase();
        return haystack.includes(qLower);
      });
    } else {
      const data = await apiFetch({ action: 'fetch_inactive_officials', q: query });
      officials = (data.data || []).filter((row) => Number(row.can_return || 0) === 1);
    }

    officials.forEach((o) => {
      const officialId = String(o.official_id || '').trim();
      if (!officialId) return;
      linkedOfficialCache.set(officialId, o);
    });
    renderLinkedOfficialResults(officials, mode === 'active' ? 'ActiveOfficial' : 'ReturningOfficial', query);
    return officials;
  }

  linkedSearchInput?.addEventListener('input', () => {
    const mode = formerOfficialModeEl?.value || '';
    if (!usesExistingOfficialMode(mode)) return;
    if (linkedIdInput) linkedIdInput.value = '';
    if (linkedSelectedEl) {
      linkedSelectedEl.textContent = '';
      linkedSelectedEl.classList.add('d-none');
    }
    clearAccessIdentityFields();
    clearTimeout(linkedSearchTimer);
    linkedSearchTimer = setTimeout(() => {
      populateLinkedOfficialSelect(linkedSearchInput.value.trim());
    }, 250);
  });

  linkedSearchInput?.addEventListener('focus', () => {
    const mode = formerOfficialModeEl?.value || '';
    if (!usesExistingOfficialMode(mode)) return;
    populateLinkedOfficialSelect(linkedSearchInput.value.trim());
  });

  formerOfficialModeEl?.addEventListener('change', () => {
    const mode = formerOfficialModeEl.value || '';
    if (linkedOfficialCache) linkedOfficialCache.clear();
    clearLinkedOfficialUi();
    clearAccessIdentityFields();
    setFormerOfficialMode(mode);
    if (usesExistingOfficialMode(mode)) {
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
    const usesExistingRecord = selectedMode === 'former' || selectedMode === 'active';
    const type = selectedMode === 'active'
      ? 'ActiveOfficial'
      : linkedId ? 'ReturningOfficial' : 'New';
    const lastName     = lastNameEl?.value.trim() || '';
    const firstName    = firstNameEl?.value.trim() || '';
    const middleName   = middleNameEl?.value.trim() || '';
    const suffix       = suffixEl?.value.trim() || '';
    const email        = emailEl?.value.trim() || '';
    const mobile       = normalizeMobileDigits(mobileEl?.value || '');

    if (!selectedMode) { showToast('Choose first whether to use an existing official record or create a new one.', 'warning'); formerOfficialModeEl?.focus(); return null; }
    if (usesExistingRecord && !linkedId) {
      showToast(selectedMode === 'active'
        ? 'Search and select the active official first, or choose a different option.'
        : 'Search and select the former official first, or choose a different option.', 'warning');
      linkedSearchInput?.focus();
      return null;
    }
    if (!lastName || !firstName) { showToast('Last name and first name are required.', 'warning'); return null; }
    if (!emailEl?.checkValidity() || !email) { showToast('Enter a valid email address.', 'warning'); emailEl?.focus(); return null; }
    if (!isValidPhilippineMobile(mobile)) { showToast('Mobile number must be a valid 10-digit Philippine mobile number.', 'warning'); mobileEl?.focus(); return null; }

    const candidateNameParts = [`${lastName}, ${firstName}`.trim()];
    if (middleName) candidateNameParts.push(middleName);
    if (suffix) candidateNameParts.push(suffix);

    const draft = {
      transition_id: String(transitionId || ''),
      candidate_type: type,
      candidate_name: candidateNameParts.join(' ').trim(),
      candidate_contact: mobile ? `+63${mobile}` : '',
      linked_official_id: linkedId,
      linked_source_mode: selectedMode,
      linked_resident_id: '',
      notes,
      candidate_first_name: firstName,
      candidate_last_name: lastName,
      candidate_middle_name: middleName,
      candidate_suffix: suffix,
      candidate_email: email,
      candidate_mobile: mobile,
    };

    transitionDrafts.set(String(transitionId || ''), draft);
    if (showSuccessToast) {
      showToast('Official information prepared for final review.');
    }
    return draft;
  }

  document.getElementById('btnMarkPendingDecision')?.addEventListener('click', async () => {
    const transitionId = document.getElementById('candidatesTransitionId').value;
    const btn = document.getElementById('btnMarkPendingDecision');
    if (!transitionId || !btn) return;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving…';

    const saveData = await saveCurrentOfficialInformation();
    if (!saveData) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-key me-1"></i> Review and Continue';
      return;
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-key me-1"></i> Review and Continue';

    showToast('Official information prepared. Review it, then complete the transition.');
    loadCandidates(transitionId);
    getModal('modalCandidates')?.hide();
    setTimeout(() => {
      if (typeof window.otOpenWinner === 'function') {
        window.otOpenWinner(transitionId);
      }
    }, 180);
  });

  // ══════════════════════════════════════════════════════════════════════════
  // SELECT WINNER MODAL
  // ══════════════════════════════════════════════════════════════════════════
  function getAutoOutcomeMeta(currentOfficial, transitionMeta = {}) {
    const mode = String(currentOfficial?.linked_source_mode || '').trim();
    const hasLinkedOfficial = Boolean(String(currentOfficial?.linked_official_id || '').trim());

    if (mode === 'active' && hasLinkedOfficial) {
      const actingReplacement = !!winnerUseActingReplacementEl?.checked;
      if (actingReplacement) {
        return {
          outcome: 'ActingReplacement',
          label: 'Temporary Acting Replacement',
          description: 'The selected active official will temporarily cover this position, the outgoing account will be suspended, and the acting assignment will remain in place until you end it or reach the acting-until date.',
        };
      }
      return {
        outcome: 'PositionChange',
        label: 'Direct Position Change',
        description: 'The selected active official will be moved into this position and the system will automatically open a follow-up transition for the vacated seat.',
      };
    }

    if (mode === 'former' && hasLinkedOfficial) {
      return {
        outcome: 'Reactivated',
        label: 'Returning Former Official',
        description: 'The matched account will be reactivated, the encoded contact details will be updated, and a fresh onboarding access link will be sent.',
      };
    }

    return {
      outcome: 'NewPerson',
      label: 'First-time Official',
      description: 'A new official account shell will be prepared for this person and their onboarding access will be sent immediately.',
    };
  }

  function syncWinnerOutcomeState(transitionId) {
    const currentOfficial = transitionDrafts.get(String(transitionId || '')) || null;
    const transitionMeta = getTransitionMeta(transitionId);
    const winnerOutcomeEl = document.getElementById('winnerOutcome');
    const winnerOutcomeSummaryEl = document.getElementById('winnerOutcomeSummary');
    const mode = String(currentOfficial?.linked_source_mode || '').trim();
    const usingActiveOfficial = mode === 'active' && String(currentOfficial?.linked_official_id || '').trim() !== '';

    if (winnerActingOptionsEl) {
      winnerActingOptionsEl.classList.toggle('d-none', !usingActiveOfficial);
    }
    if (winnerActingUntilDateEl) {
      winnerActingUntilDateEl.disabled = !usingActiveOfficial || !winnerUseActingReplacementEl?.checked;
      if (!usingActiveOfficial) {
        winnerActingUntilDateEl.value = '';
      }
    }

    const outcomeMeta = getAutoOutcomeMeta(currentOfficial, transitionMeta);
    if (winnerOutcomeEl) {
      winnerOutcomeEl.value = outcomeMeta.outcome;
    }
    if (winnerOutcomeSummaryEl) {
      winnerOutcomeSummaryEl.innerHTML = `
        <div class="fw-semibold text-dark">${esc(outcomeMeta.label)}</div>
        <div class="small text-muted mt-1">${esc(outcomeMeta.description)}</div>
      `;
    }
  }

  window.otOpenWinner = function (transitionId) {
    document.getElementById('winnerTransitionId').value = transitionId;
    document.getElementById('winnerOutcome').value      = '';
    const summaryEl = document.getElementById('winnerOutcomeSummary');
    if (summaryEl) {
      summaryEl.innerHTML = 'The system will determine the access action after the official information is loaded.';
    }
    if (winnerUseActingReplacementEl) winnerUseActingReplacementEl.checked = false;
    if (winnerActingUntilDateEl) {
      winnerActingUntilDateEl.value = '';
      winnerActingUntilDateEl.disabled = true;
    }
    if (winnerActingOptionsEl) winnerActingOptionsEl.classList.add('d-none');
    getModal('modalSelectWinner')?.show();
    loadWinnerCandidates(transitionId);
  };

  async function loadWinnerCandidates(transitionId) {
    const listEl = document.getElementById('winnerCandidatesList');
    listEl.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-2"></i></div>';
    const currentOfficial = transitionDrafts.get(String(transitionId || '')) || null;

    if (!currentOfficial) {
      listEl.innerHTML = '<p class="text-muted small text-center">No official information is ready yet. Go back and complete the access setup first.</p>';
      return;
    }

    syncWinnerOutcomeState(transitionId);

    const emailLine = String(currentOfficial.candidate_email || '').trim();
    const mobileLine = formatMobileDisplay(currentOfficial.candidate_mobile || currentOfficial.candidate_contact || '');
    listEl.innerHTML = `
      <div class="border rounded p-3 bg-light">
        <div class="fw-semibold small text-muted mb-2">Transition Review</div>
        <div class="fw-semibold">${esc(formatAccessEntryName(currentOfficial))} ${candidateTypePill(currentOfficial.candidate_type || (currentOfficial.linked_official_id ? 'ReturningOfficial' : 'New'))}</div>
        ${emailLine ? `<small class="text-muted d-block">${esc(emailLine)}</small>` : ''}
        ${mobileLine ? `<small class="text-muted d-block">${esc(mobileLine)}</small>` : ''}
        ${currentOfficial.notes ? `<small class="text-muted fst-italic d-block mt-1">${esc(currentOfficial.notes)}</small>` : ''}
      </div>`;
  }

  winnerUseActingReplacementEl?.addEventListener('change', () => {
    const transitionId = document.getElementById('winnerTransitionId')?.value || '';
    if (winnerActingUntilDateEl) {
      winnerActingUntilDateEl.disabled = !winnerUseActingReplacementEl.checked;
      if (!winnerUseActingReplacementEl.checked) {
        winnerActingUntilDateEl.value = '';
      }
    }
    if (transitionId) {
      syncWinnerOutcomeState(transitionId);
    }
  });

  document.getElementById('btnCompleteTransition')?.addEventListener('click', async () => {
    const transitionId = document.getElementById('winnerTransitionId').value;
    const outcome      = document.getElementById('winnerOutcome').value;
    const draft        = transitionDrafts.get(String(transitionId || '')) || null;

    if (!outcome) { showToast('The access action could not be determined. Re-open the first modal and complete the official information.', 'warning'); return; }
    if (!draft) { showToast('Complete the official information first.', 'warning'); return; }

    const btn = document.getElementById('btnCompleteTransition');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing…';

    const params = {
      action:               'complete_transition',
      transition_id:        transitionId,
      outcome,
      acting_until_date:    outcome === 'ActingReplacement' ? (winnerActingUntilDateEl?.value || '') : '',
      linked_official_id:   draft.linked_official_id || '',
      notes:                draft.notes || '',
      candidate_first_name: draft.candidate_first_name || '',
      candidate_last_name:  draft.candidate_last_name || '',
      candidate_middle_name:draft.candidate_middle_name || '',
      candidate_suffix:     draft.candidate_suffix || '',
      candidate_email:      draft.candidate_email || '',
      candidate_mobile:     draft.candidate_mobile || '',
    };
    try {
      const secureConfirmation = await requestSecureConfirmation(
        'complete_transition',
        { transition_id: transitionId },
        'complete this official turnover'
      );
      if (!secureConfirmation) {
        return;
      }
      Object.assign(params, secureConfirmation);

      const data = await apiFetch(params, 'POST');
      if (data.success) {
        const inviteEmailFailed = data.invite_email_sent === false;
        const inviteSmsFailed = data.invite_sms_sent === false;
        const inviteEmailError = String(data.invite_email_error || '').trim();
        const inviteSmsError = String(data.invite_sms_error || '').trim();
        const deliveryHadIssue = inviteEmailFailed || inviteSmsFailed;
        transitionDrafts.delete(String(transitionId || ''));
        showToast(data.message || 'Access setup completed.', deliveryHadIssue ? 'warning' : 'success');
        if (data.invite_link && inviteEmailFailed) {
          if (inviteEmailError) {
            showToast(`Invite email failed: ${inviteEmailError}`, 'warning');
          }
          window.prompt('Invite email was not sent automatically. Copy the onboarding link and send it manually:', String(data.invite_link));
        }
        if (inviteSmsFailed) {
          showToast(
            inviteSmsError ? `Invite SMS failed: ${inviteSmsError}` : 'Invite SMS was not sent automatically.',
            'warning'
          );
        }
        getModal('modalSelectWinner')?.hide();
        loadTransitions();
        updateStats();
      } else {
        showToast(data.message || 'Failed.', 'error');
      }
    } catch (error) {
      showToast(error?.message || 'Failed.', 'error');
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Complete and Notify';
    }
  });

  // ══════════════════════════════════════════════════════════════════════════
  // CANCEL TRANSITION
  // ══════════════════════════════════════════════════════════════════════════
  window.otCancelTransition = async function (transitionId) {
    try {
      const reason = prompt(`Cancel transition ${transitionId}?\n\nOptional reason:`);
      if (reason === null) return;
      const secureConfirmation = await requestSecureConfirmation(
        'cancel_transition',
        { transition_id: transitionId },
        'cancel this transition'
      );
      if (!secureConfirmation) return;
      const data = await apiFetch({
        action: 'cancel_transition',
        transition_id: transitionId,
        reason: reason || '',
        ...secureConfirmation,
      }, 'POST');
      if (data.success) {
        transitionDrafts.delete(String(transitionId || ''));
        showToast('Transition cancelled.');
        loadTransitions();
        updateStats();
      } else {
        showToast(data.message || 'Failed.', 'error');
      }
    } catch (error) {
      showToast(error?.message || 'Failed.', 'error');
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
    try {
      if (!confirm(`Restore access for ${name}?`)) return;
      const secureConfirmation = await requestSecureConfirmation(
        'restore_access',
        { official_id: officialId },
        `restore access for ${name}`
      );
      if (!secureConfirmation) return;
      const data = await apiFetch({ action: 'restore_access', official_id: officialId, ...secureConfirmation }, 'POST');
      if (data.success) {
        showToast(data.message || 'Access restored.');
        loadInactiveOfficials();
        loadPastOfficials(document.getElementById('otPastOfficialsSearch')?.value.trim() || '');
        loadTransitions();
      } else {
        showToast(data.message || 'Failed.', 'error');
      }
    } catch (error) {
      showToast(error?.message || 'Failed.', 'error');
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
    try {
      const secureConfirmation = await requestSecureConfirmation(
        'change_credentials',
        { official_id: officialId },
        'change these official credentials'
      );
      if (!secureConfirmation) return;
      const data = await apiFetch({
        action: 'change_credentials',
        official_id: officialId,
        email, phone,
        force_password_reset: forceReset,
        ...secureConfirmation,
      }, 'POST');

      if (data.success) {
        showToast(data.message || 'Credentials updated.');
        getModal('modalChangeCredentials')?.hide();
      } else {
        showToast(data.message || 'Failed.', 'error');
      }
    } catch (error) {
      showToast(error?.message || 'Failed.', 'error');
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
    try {
      if (!confirm(`End acting assignment for ${picked.lastname}, ${picked.firstname}?`)) return;
      const secureConfirmation = await requestSecureConfirmation(
        'end_acting',
        { acting_official_id: picked.official_id },
        `end acting assignment for ${picked.lastname}, ${picked.firstname}`
      );
      if (!secureConfirmation) return;
      const data = await apiFetch({ action: 'end_acting', acting_official_id: picked.official_id, ...secureConfirmation }, 'POST');
      if (data.success) {
        showToast(data.message || 'Acting assignment ended.');
        loadTransitions();
      } else {
        showToast(data.message || 'Failed.', 'error');
      }
    } catch (error) {
      showToast(error?.message || 'Failed.', 'error');
    }
  }

  // ══════════════════════════════════════════════════════════════════════════
  window.otDeleteSchedule = async function (batchLabel) {
    try {
      if (!confirm(`Delete governance cycle "${batchLabel}"?\n\nThis will remove the schedule and all linked transition rows for that cycle.`)) return;
      const secureConfirmation = await requestSecureConfirmation(
        'delete_schedule',
        { batch_label: batchLabel },
        `delete governance cycle ${batchLabel}`
      );
      if (!secureConfirmation) return;
      const data = await apiFetch({ action: 'delete_schedule', batch_label: batchLabel, ...secureConfirmation }, 'POST');
      if (data.success) {
        showToast(data.message || 'Schedule deleted.');
        location.reload();
      } else {
        showToast(data.message || 'Failed.', 'error');
      }
    } catch (error) {
      showToast(error?.message || 'Failed.', 'error');
    }
  };

  window.otDemoteOfficial = async function (officialId, name) {
    try {
      const reason = prompt(`Demote ${name}?\n\nOptional reason:`);
      if (reason === null) return;
      const secureConfirmation = await requestSecureConfirmation(
        'demote_official',
        { official_id: officialId },
        `demote ${name}`
      );
      if (!secureConfirmation) return;
      const data = await apiFetch({
        action: 'demote_official',
        official_id: officialId,
        reason: reason || '',
        ...secureConfirmation,
      }, 'POST');
      if (data.success) {
        showToast(data.message || 'Official demoted.');
        loadTransitions();
        updateStats();
        location.reload();
      } else {
        showToast(data.message || 'Failed.', 'error');
      }
    } catch (error) {
      showToast(error?.message || 'Failed.', 'error');
    }
  };

  window.otDemoteBatch = async function (batchLabel) {
    try {
      if (!confirm(`Demote all outgoing officials for governance cycle "${batchLabel}"?\n\nThis is intended for governance-cycle turnover.`)) return;
      const reason = prompt(`Optional reason for demoting governance cycle "${batchLabel}":`) ?? '';
      const secureConfirmation = await requestSecureConfirmation(
        'demote_batch',
        { batch_label: batchLabel },
        `demote all outgoing officials in ${batchLabel}`
      );
      if (!secureConfirmation) return;
      const data = await apiFetch({
        action: 'demote_batch',
        batch_label: batchLabel,
        reason,
        ...secureConfirmation,
      }, 'POST');
      if (data.success) {
        showToast(data.message || 'Governance cycle processed.');
        location.reload();
      } else {
        showToast(data.message || 'Failed.', 'error');
      }
    } catch (error) {
      showToast(error?.message || 'Failed.', 'error');
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
