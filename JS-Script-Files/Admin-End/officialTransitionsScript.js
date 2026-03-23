/**
 * Official Transition Module — JS
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

  // ── Status badge ──────────────────────────────────────────────────────────
  function statusBadge(status) {
    const map = {
      Open:              ['badge-ot-open',      'Open'],
      CandidateEncoding: ['badge-ot-encoding',  'Encoding'],
      PendingDecision:   ['badge-ot-pending',   'Pending Decision'],
      Decided:           ['badge-ot-decided',   'Decided'],
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
    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin me-2"></i> Loading…</td></tr>';

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
      tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">${data.message || 'Failed to load.'}</td></tr>`;
      return;
    }
    if (data.notice) {
      tbody.innerHTML = `<tr><td colspan="9" class="text-center py-5">
        <div class="text-muted mb-2"><i class="fas fa-database fa-2x"></i></div>
        <div class="fw-semibold">Database migration not yet applied.</div>
        <div class="text-muted small mt-1">Run <code>20260323_create_official_transitions_schema.sql</code> on your database to activate this module.</div>
      </td></tr>`;
      return;
    }

    const rows = data.data || [];
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No transitions found.</td></tr>';
      renderPagination(0);
      return;
    }

    tbody.innerHTML = rows.map(r => {
      const outgoing  = r.outgoing_name    || '<span class="text-muted">—</span>';
      const batch     = r.batch_label      ? `<span class="badge bg-secondary">${esc(r.batch_label)}</span>` : '<span class="text-muted small">—</span>';
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
          <td class="text-center">
            <button class="btn btn-xs btn-outline-secondary py-0 px-2" onclick="otOpenCandidates('${esc(r.transition_id)}')">
              ${r.candidate_count || 0} <i class="fas fa-users fa-xs ms-1"></i>
            </button>
          </td>
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

    if (s === 'PendingDecision' || s === 'Decided') {
      btns.push(`<button class="btn btn-xs btn-success py-0 px-2" onclick="otOpenWinner('${esc(tid)}')" title="Select winner"><i class="fas fa-trophy"></i></button>`);
    }
    if (['Open','CandidateEncoding'].includes(s)) {
      btns.push(`<button class="btn btn-xs btn-outline-primary py-0 px-2" onclick="otOpenCandidates('${esc(tid)}')" title="Manage candidates"><i class="fas fa-users"></i></button>`);
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
  const ntElectWrap  = document.getElementById('ntElectionDateWrap');
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
    if (ntElectWrap) ntElectWrap.style.display = 'none';
    if (ntReasonLbl) ntReasonLbl.textContent = 'Reason';
  });

  ntType?.addEventListener('change', () => {
    const v          = ntType.value;
    const isElection = ['BarangayElection','SKElection'].includes(v);
    const isRemoval  = v === 'Removal';
    if (ntBatchWrap) ntBatchWrap.style.display = isElection ? '' : 'none';
    if (ntElectWrap) ntElectWrap.style.display = isElection ? '' : 'none';
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
  // NEW BATCH MODAL
  // ══════════════════════════════════════════════════════════════════════════
  document.getElementById('nbSelectAll')?.addEventListener('change', function () {
    document.querySelectorAll('.nb-official-check').forEach(cb => cb.checked = this.checked);
  });

  document.getElementById('formNewBatch')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const checked = [...document.querySelectorAll('.nb-official-check:checked')];
    if (!checked.length) { showToast('Select at least one official.', 'warning'); return; }

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
    body.append('batch_type', fd.get('batch_type') || '');
    body.append('election_date', fd.get('election_date') || '');
    checked.forEach(cb => body.append('officials[]', cb.value));

    const res = await fetch(API, { method: 'POST', body, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
    const data = await res.json();

    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-layer-group me-1"></i> Create Batch';

    if (data.success) {
      showToast(data.message || 'Batch created.');
      getModal('modalNewBatch')?.hide();
      e.target.reset();
      document.querySelectorAll('.nb-official-check').forEach(cb => cb.checked = false);
      location.reload(); // reload to refresh election schedule section
    } else {
      showToast(data.message || 'Failed.', 'error');
    }
  });

  // ══════════════════════════════════════════════════════════════════════════
  // ADD ELECTION DATE
  // ══════════════════════════════════════════════════════════════════════════
  document.getElementById('formAddElection')?.addEventListener('submit', async (e) => {
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
  window.otOpenCandidates = function (transitionId) {
    document.getElementById('candidatesTransitionId').value = transitionId;
    getModal('modalCandidates')?.show();
    loadCandidates(transitionId);
  };

  async function loadCandidates(transitionId) {
    const listEl = document.getElementById('candidatesList');
    const posLabel = document.getElementById('candidatesModalPositionLabel');
    const outInfo  = document.getElementById('outgoingOfficialInfo');
    listEl.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-2"></i></div>';

    const data = await apiFetch({ action: 'fetch_candidates', transition_id: transitionId });
    if (!data.success) { listEl.innerHTML = '<p class="text-danger">Failed to load candidates.</p>'; return; }

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

    const candidates = data.candidates || [];
    if (!candidates.length) {
      listEl.innerHTML = '<p class="text-muted small text-center py-2">No candidates encoded yet.</p>';
      return;
    }

    listEl.innerHTML = candidates.map(c => {
      const typePill = candidateTypePill(c.candidate_type);
      const selected = c.is_selected == 1;
      return `
        <div class="candidate-item p-2 mb-2 d-flex align-items-start justify-content-between ${selected ? 'selected' : ''}">
          <div>
            <span class="fw-semibold">${esc(c.candidate_name)}</span>
            ${typePill}
            ${selected ? '<span class="badge bg-success ms-1">Winner</span>' : ''}
            ${c.candidate_contact ? `<div class="text-muted small">${esc(c.candidate_contact)}</div>` : ''}
            ${c.notes ? `<div class="text-muted small fst-italic">${esc(c.notes)}</div>` : ''}
          </div>
          <button class="btn btn-xs btn-outline-danger py-0 px-1 ms-2" onclick="otRemoveCandidate(${c.upcoming_id},'${esc(transitionId)}')" title="Remove">
            <i class="fas fa-times fa-xs"></i>
          </button>
        </div>`;
    }).join('');
  }

  function candidateTypePill(type) {
    const map = {
      New:             ['bg-primary text-white', 'New'],
      ReturningOfficial:['bg-info text-dark',    'Returning'],
      ActiveOfficial:  ['bg-warning text-dark',  'Active Official'],
      Resident:        ['bg-secondary text-white','Resident'],
    };
    const [cls, label] = map[type] || ['bg-light text-dark', type];
    return `<span class="badge ${cls} candidate-type-badge ms-1">${label}</span>`;
  }

  // Candidate type → show linked-id picker
  const newCandTypeEl = document.getElementById('newCandidateType');
  const linkedIdWrap  = document.getElementById('linkedIdWrap');
  const linkedIdSel   = document.getElementById('newCandidateLinkedId');

  newCandTypeEl?.addEventListener('change', () => {
    const v = newCandTypeEl.value;
    const needsLink = ['ReturningOfficial','ActiveOfficial'].includes(v);
    linkedIdWrap.style.display = needsLink ? '' : 'none';
    if (needsLink) populateLinkedOfficialSelect(v);
  });

  function populateLinkedOfficialSelect(type) {
    linkedIdSel.innerHTML = '<option value="">— Select official —</option>';
    const officials = window.OT_DATA?.activeOfficials || [];
    // For ReturningOfficial we'd need inactive officials; for simplicity show all
    officials.forEach(o => {
      const name = `${o.lastname}, ${o.firstname} — ${o.position || ''}`;
      const opt  = document.createElement('option');
      opt.value = o.official_id;
      opt.textContent = name;
      linkedIdSel.appendChild(opt);
    });
  }

  document.getElementById('btnAddCandidate')?.addEventListener('click', async () => {
    const transitionId = document.getElementById('candidatesTransitionId').value;
    const name         = document.getElementById('newCandidateName').value.trim();
    const contact      = document.getElementById('newCandidateContact').value.trim();
    const type         = document.getElementById('newCandidateType').value;
    const notes        = document.getElementById('newCandidateNotes').value.trim();
    const linkedId     = linkedIdSel?.value || '';

    if (!name) { showToast('Candidate name is required.', 'warning'); return; }

    const params = {
      action: 'add_candidate',
      transition_id:     transitionId,
      candidate_name:    name,
      candidate_contact: contact,
      candidate_type:    type,
      notes,
    };
    if (linkedId) params.linked_official_id = linkedId;

    const data = await apiFetch(params, 'POST');
    if (data.success) {
      showToast('Candidate added.');
      document.getElementById('newCandidateName').value    = '';
      document.getElementById('newCandidateContact').value = '';
      document.getElementById('newCandidateNotes').value   = '';
      linkedIdSel && (linkedIdSel.value = '');
      loadCandidates(transitionId);
      loadTransitions();
    } else {
      showToast(data.message || 'Failed.', 'error');
    }
  });

  window.otRemoveCandidate = async function (upcomingId, transitionId) {
    if (!confirm('Remove this candidate?')) return;
    const data = await apiFetch({ action: 'remove_candidate', upcoming_id: upcomingId }, 'POST');
    if (data.success) {
      showToast('Candidate removed.');
      loadCandidates(transitionId);
      loadTransitions();
    } else {
      showToast(data.message || 'Failed.', 'error');
    }
  };

  document.getElementById('btnMarkPendingDecision')?.addEventListener('click', async () => {
    const transitionId = document.getElementById('candidatesTransitionId').value;
    const data = await apiFetch({ action: 'mark_pending_decision', transition_id: transitionId }, 'POST');
    if (data.success) {
      showToast('Marked as ready for decision.');
      getModal('modalCandidates')?.hide();
      loadTransitions();
      updateStats();
    } else {
      showToast(data.message || 'Failed.', 'error');
    }
  });

  // ══════════════════════════════════════════════════════════════════════════
  // SELECT WINNER MODAL
  // ══════════════════════════════════════════════════════════════════════════
  window.otOpenWinner = function (transitionId) {
    document.getElementById('winnerTransitionId').value = transitionId;
    document.getElementById('winnerOutcome').value      = '';
    document.getElementById('actingUntilWrap').classList.add('d-none');
    getModal('modalSelectWinner')?.show();
    loadWinnerCandidates(transitionId);
  };

  async function loadWinnerCandidates(transitionId) {
    const listEl = document.getElementById('winnerCandidatesList');
    listEl.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-2"></i></div>';

    const data = await apiFetch({ action: 'fetch_candidates', transition_id: transitionId });
    const candidates = data.candidates || [];

    if (!candidates.length) {
      listEl.innerHTML = '<p class="text-muted small text-center">No candidates. Add candidates first.</p>';
      return;
    }

    listEl.innerHTML = `
      <p class="fw-semibold small mb-2">Select the winner:</p>
      ${candidates.map(c => `
        <div class="form-check candidate-item p-2 mb-2">
          <input class="form-check-input" type="radio" name="winnerCandidate" id="wc${c.upcoming_id}" value="${c.upcoming_id}">
          <label class="form-check-label w-100" for="wc${c.upcoming_id}">
            <span class="fw-semibold">${esc(c.candidate_name)}</span>
            ${candidateTypePill(c.candidate_type)}
            ${c.candidate_contact ? `<small class="text-muted d-block">${esc(c.candidate_contact)}</small>` : ''}
            ${c.notes ? `<small class="text-muted fst-italic d-block">${esc(c.notes)}</small>` : ''}
          </label>
        </div>`).join('')}`;
  }

  document.getElementById('winnerOutcome')?.addEventListener('change', function () {
    const actingWrap = document.getElementById('actingUntilWrap');
    actingWrap.classList.toggle('d-none', this.value !== 'ActingReplacement');
  });

  document.getElementById('btnCompleteTransition')?.addEventListener('click', async () => {
    const transitionId = document.getElementById('winnerTransitionId').value;
    const outcome      = document.getElementById('winnerOutcome').value;
    const actingUntil  = document.getElementById('actingUntilDate').value;
    const selectedRadio= document.querySelector('input[name="winnerCandidate"]:checked');

    if (!outcome) { showToast('Select an outcome.', 'warning'); return; }
    if (outcome !== 'NoSuccessor' && !selectedRadio) { showToast('Select a winner.', 'warning'); return; }

    const btn = document.getElementById('btnCompleteTransition');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing…';

    const data = await apiFetch({
      action:               'complete_transition',
      transition_id:        transitionId,
      selected_candidate_id: selectedRadio?.value || 0,
      outcome,
      acting_until_date:    actingUntil,
    }, 'POST');

    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Complete Transition';

    if (data.success) {
      showToast(data.message || 'Transition completed.');
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
    showToast('Use New Transition → Appointment/Reappointment for a direct position change.', 'warning');
  });

  // ── Restore Access ─────────────────────────────────────────────────────────
  async function loadInactiveOfficials(q = '') {
    const listEl = document.getElementById('restoreOfficialsList');
    listEl.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-2"></i></div>';
    const data = await apiFetch({ action: 'fetch_inactive_officials', q });
    const officials = data.data || [];

    if (!officials.length) {
      listEl.innerHTML = '<p class="text-muted small text-center py-2">No inactive officials found.</p>';
      return;
    }

    listEl.innerHTML = `<div class="table-responsive"><table class="table table-sm align-middle mb-0">
      <thead class="table-light"><tr><th>Name</th><th>Position</th><th>Status</th><th>Can Return</th><th></th></tr></thead>
      <tbody>
        ${officials.map(o => `
          <tr>
            <td>${esc(o.full_name)}</td>
            <td>${esc(o.position || '—')}</td>
            <td><span class="badge bg-secondary">${esc(o.account_status)}</span></td>
            <td>${o.can_return == 1 ? '<span class="text-success"><i class="fas fa-check"></i></span>' : '<span class="text-danger"><i class="fas fa-times"></i></span>'}</td>
            <td>
              <button class="btn btn-xs btn-success py-0 px-2" onclick="otRestoreOfficial('${esc(o.official_id)}','${esc(o.full_name)}')">
                <i class="fas fa-unlock me-1"></i> Restore
              </button>
            </td>
          </tr>`).join('')}
      </tbody></table></div>`;
  }

  let restoreTimer;
  document.getElementById('restoreSearch')?.addEventListener('input', function () {
    clearTimeout(restoreTimer);
    restoreTimer = setTimeout(() => loadInactiveOfficials(this.value.trim()), 350);
  });

  window.otRestoreOfficial = async function (officialId, name) {
    if (!confirm(`Restore access for ${name}?`)) return;
    const data = await apiFetch({ action: 'restore_access', official_id: officialId }, 'POST');
    if (data.success) {
      showToast(data.message || 'Access restored.');
      loadInactiveOfficials();
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

})();
