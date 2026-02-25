(() => {
  const appBase = (() => {
    const marker = '/Admin-End/';
    const idx = window.location.pathname.indexOf(marker);
    if (idx === -1) return '';
    return window.location.pathname.slice(0, idx);
  })();
  const endpoint = `${appBase}/PhpFiles/Admin-End/documentRequestWorkflow.php`;
  const tableBody = document.getElementById('tableBody');
  const searchInput = document.getElementById('searchInput');
  const stageTabs = Array.from(document.querySelectorAll('[data-stage-filter]'));
  const statusTabs = Array.from(document.querySelectorAll('[data-status-filter]'));
  const btnRefreshList = document.getElementById('btnRefreshList');
  const documentTypeFilter = document.getElementById('documentTypeFilter');

  const actionModalEl = document.getElementById('actionModal');
  const actionModal = actionModalEl ? new bootstrap.Modal(actionModalEl) : null;
  const actionForm = document.getElementById('actionForm');
  const actionType = document.getElementById('actionType');
  const actionRequestId = document.getElementById('actionRequestId');
  const actionReasonWrap = document.getElementById('actionReasonWrap');
  const actionReason = document.getElementById('actionReason');
  const actionAmountWrap = document.getElementById('actionAmountWrap');
  const actionAmount = document.getElementById('actionAmount');
  const actionOrWrap = document.getElementById('actionOrWrap');
  const actionOr = document.getElementById('actionOr');
  const actionIssuedWrap = document.getElementById('actionIssuedWrap');
  const actionIssued = document.getElementById('actionIssued');
  const modalTitle = document.getElementById('actionModalTitle');
  const modalError = document.getElementById('actionModalError');
  const viewModalEl = document.getElementById('viewModal');
  const viewModal = viewModalEl ? new bootstrap.Modal(viewModalEl) : null;
  const viewDetailsBody = document.getElementById('viewDetailsBody');
  const viewModalActions = document.getElementById('viewModalActions');
  const paymentProofModalEl = document.getElementById('paymentProofModal');
  const paymentProofModal = paymentProofModalEl ? new bootstrap.Modal(paymentProofModalEl) : null;
  const paymentProofWrap = document.getElementById('paymentProofWrap');
  const paymentProofOpenNew = document.getElementById('paymentProofOpenNew');
  const residentProfileModalEl = document.getElementById('residentProfileModal');
  const residentProfileModal = residentProfileModalEl ? new bootstrap.Modal(residentProfileModalEl) : null;
  const residentProfileEndpoint = `${appBase}/PhpFiles/Admin-End/residentMasterlist.php`;

  let currentStage = String(window.CERT_TRACKER_DEFAULT_STAGE || '');
  let currentStatusFilter = 'all';
  let currentDocumentTypeFilter = '';
  let itemById = new Map();
  const financeStages = new Set(['for_payment', 'payment_submitted']);

  function esc(v) {
    return String(v ?? '').replace(/[&<>\"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '\"': '&quot;', "'": '&#39;' }[m]));
  }

  function badge(stage, label) {
    const k = String(stage || '').toLowerCase();
    if (k.includes('rejected')) return `<span class="badge bg-danger">${label}</span>`;
    if (k === 'completed') return `<span class="badge bg-success">${label}</span>`;
    if (k === 'ready_for_claim') return `<span class="badge bg-primary">${label}</span>`;
    if (k === 'for_payment' || k === 'payment_submitted') return `<span class="badge bg-warning text-dark">${label}</span>`;
    return `<span class="badge bg-secondary">${label}</span>`;
  }

  function actionButtons(row) {
    const viewBtn = `<button class="btn btn-sm btn-outline-secondary me-1" data-view-id="${esc(row.request_id)}">View</button>`;
    const proofBtn = row.payment_proof_path
      ? `<button class="btn btn-sm btn-outline-dark me-1" data-proof-id="${esc(row.request_id)}">View Payment</button>`
      : '';
    return `${viewBtn}${proofBtn}`;
  }

  function viewModalActionButtons(row) {
    if (!row) return '';
    const id = esc(row.request_id || '');
    const stage = String(row.stage || '');
    const residentId = firstNonEmpty([row.resident_id]);
    const profileBtn = residentId
      ? `<button class="btn btn-sm btn-outline-primary" data-view-profile-id="${esc(residentId)}">View Profile</button>`
      : '';
    const proofBtn = row.payment_proof_path
      ? `<button class="btn btn-sm btn-outline-dark" data-proof-id="${id}">View Payment</button>`
      : '';

    if (stage === 'submitted') {
      return `
        ${profileBtn}
        ${proofBtn}
        <button class="btn btn-sm btn-success" data-view-action="personnel_approve" data-id="${id}">Approve</button>
        <button class="btn btn-sm btn-danger" data-view-action="personnel_reject" data-id="${id}">Reject</button>
      `;
    }
    if (stage === 'payment_submitted') {
      return `
        ${profileBtn}
        ${proofBtn}
        <button class="btn btn-sm btn-success" data-view-action="finance_verify" data-id="${id}">Verify Payment</button>
        <button class="btn btn-sm btn-danger" data-view-action="finance_reject" data-id="${id}">Reject Payment</button>
      `;
    }
    if (stage === 'payment_verified') {
      return `
        ${profileBtn}
        ${proofBtn}
        <button class="btn btn-sm btn-primary" data-view-action="mark_ready" data-id="${id}">Ready for Claim</button>
      `;
    }
    if (stage === 'ready_for_claim') {
      return `
        ${profileBtn}
        ${proofBtn}
        <button class="btn btn-sm btn-dark" data-view-action="mark_completed" data-id="${id}">Mark Completed</button>
      `;
    }
    return profileBtn || proofBtn || '<span class="text-muted small">No actions</span>';
  }

  function firstNonEmpty(values) {
    for (const value of values) {
      if (value === null || value === undefined) continue;
      const s = String(value).trim();
      if (s !== '') return s;
    }
    return '';
  }

  function fullNameFromRow(row) {
    const payload = row && row.payload && typeof row.payload === 'object' ? row.payload : {};
    const first = firstNonEmpty([payload.first_name, payload.firstname]);
    const middle = firstNonEmpty([payload.middle_name, payload.middlename]);
    const last = firstNonEmpty([payload.last_name, payload.lastname]);
    const suffix = firstNonEmpty([payload.suffix, payload.suffix_name]);
    const middleInitial = middle ? `${middle.charAt(0).toUpperCase()}.` : '';

    const ordered = [first, middleInitial, last, suffix].filter(Boolean);
    if (ordered.length) return ordered.join(' ');
    return firstNonEmpty([row.full_name, row.resident_full_name, row.resident_name, '-']) || '-';
  }

  function residentInfoFromRow(row) {
    const payload = row && row.payload && typeof row.payload === 'object' ? row.payload : {};
    const bits = [];
    const sex = firstNonEmpty([payload.sex, payload.gender]);
    const birthdate = firstNonEmpty([payload.birthdate, payload.date_of_birth]);
    const age = firstNonEmpty([payload.age]);
    const address = firstNonEmpty([payload.full_address, payload.address, payload.complete_address]);

    if (sex) bits.push(sex);
    if (birthdate) bits.push(`Birthdate: ${birthdate}`);
    if (age) bits.push(`Age: ${age}`);
    if (address) bits.push(address);

    return bits.length ? bits.join(' | ') : '-';
  }

  function documentTypePill(row) {
    const doc = firstNonEmpty([row.document_type, '-']);
    return `<span class="badge rounded-pill" style="background:#f7d9df;color:#8f2b35;font-weight:700;">${esc(doc)}</span>`;
  }

  function profileItem(label, value) {
    return `
      <div class="tracker-profile-item">
        <p class="tracker-profile-label">${esc(label)}</p>
        <p class="tracker-profile-value">${String(value ?? '-')}</p>
      </div>
    `;
  }

  function profileSection(title, content) {
    return `
      <section class="tracker-profile-section">
        <h6>${esc(title)}</h6>
        <div class="tracker-profile-grid">${content}</div>
      </section>
    `;
  }

  function computeAgeFromBirthdate(birthdate) {
    const raw = String(birthdate || '').trim();
    if (!raw) return '—';
    const dob = new Date(raw);
    if (Number.isNaN(dob.getTime())) return '—';
    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const m = today.getMonth() - dob.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age -= 1;
    return age >= 0 ? String(age) : '—';
  }

  function setById(id, value) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = String(value ?? '—').trim() || '—';
  }

  function setAddressField(containerId, valueId, value) {
    const container = document.getElementById(containerId);
    const valueEl = document.getElementById(valueId);
    if (!container || !valueEl) return;
    const text = String(value ?? '').trim();
    if (text === '') {
      container.classList.add('d-none');
      valueEl.textContent = '';
      return;
    }
    container.classList.remove('d-none');
    valueEl.textContent = text;
  }

  function renderResidentStatusBanner(status) {
    const banner = document.getElementById('div-statusBanner');
    if (!banner) return;
    const statusText = String(status || 'UNSET');
    banner.textContent = statusText;
    banner.className = 'mb-0';
    banner.classList.add(
      statusText === 'VerifiedResident' ? 'bg-statusApproved' :
      statusText === 'PendingVerification' ? 'bg-statusPending' :
      statusText === 'NotVerified' ? 'bg-statusDenied' :
      'bg-statusUnset'
    );
  }

  function fillResidentProfileModal(data) {
    const placeholder = `${appBase}/Images/Profile-Placeholder.png`;
    const imgEl = document.getElementById('img-modalIdPicture');
    if (imgEl) {
      const candidate = String(data?.id_picture_url || '').trim();
      imgEl.src = candidate || placeholder;
    }
    setById('span-displayID', `#${String(data?.resident_id || '—')}`);

    setById('txt-modalName', data?.full_name);
    setById('txt-modalDob', data?.birthdate);
    setById('txt-modalAge', computeAgeFromBirthdate(data?.birthdate));
    setById('txt-modalSex', data?.sex);
    setById('txt-modalCivilStatus', data?.civil_status);
    setById('txt-modalHeadOfFam', String(data?.head_of_family || '0') === '1' ? 'Yes' : 'No');
    setById('txt-modalVoterStatus', String(data?.voter_status || '0') === '1' ? 'Registered' : 'Not Registered');
    setById('txt-modalOccupation', data?.occupation_display || 'Unemployed');
    setById('txt-modalReligion', data?.religion);
    setById('txt-modalSectorMembership', data?.sector_membership);

    const sectorWrap = document.getElementById('div-modalSectorProofStatuses');
    if (sectorWrap) {
      sectorWrap.innerHTML = '';
      const sectorText = String(data?.sector_membership || '').trim();
      const statusText = String(data?.status || '').trim();
      if (sectorText !== '') {
        const proofLabel = statusText === 'VerifiedResident'
          ? `${sectorText}: Verified`
          : (statusText === 'PendingVerification' ? `${sectorText}: Pending` : `${sectorText}: Not Verified`);
        const cls = statusText === 'VerifiedResident'
          ? 'bg-success'
          : (statusText === 'PendingVerification' ? 'bg-warning text-dark' : 'bg-danger');
        sectorWrap.innerHTML = `<span class="badge ${cls}">${esc(proofLabel)}</span>`;
      }
    }

    setById('txt-modalEmergencyFullName', data?.emergency_full_name);
    setById('txt-modalEmergencyContactNumber', data?.emergency_contact_number);
    setById('txt-modalEmergencyRelationship', data?.emergency_relationship);
    setById('txt-modalEmergencyAddress', data?.emergency_address);

    setAddressField('addr-unit-number', 'txt-modalUnitNumber', data?.unit_number);
    setAddressField('addr-house-number', 'txt-modalHouseNum', data?.house_number);
    setAddressField('addr-street-name', 'txt-modalStreetName', data?.street_name);
    setAddressField('addr-phase-number', 'txt-modalPhaseNumber', data?.phase_number);
    setAddressField('addr-subdivision', 'txt-modalSubdivision', data?.subdivision);
    setAddressField('addr-area-number', 'txt-modalAreaNumber', data?.area_number);
    setById('txt-modalHouseOwnership', data?.house_ownership);
    setById('txt-modalHouseType', data?.house_type);
    setById('txt-modalResidencyDuration', data?.residency_duration);

    renderResidentStatusBanner(data?.status);
  }

  async function openResidentProfileModal(residentId) {
    if (!residentId || !residentProfileModal) return;
    if (viewModalEl && viewModalEl.classList.contains('show') && viewModal) {
      viewModal.hide();
    }
    residentProfileModal.show();

    try {
      const q = new URLSearchParams({
        fetch: 'true',
        search: String(residentId)
      });
      const rows = await fetchJson(`${residentProfileEndpoint}?${q.toString()}`);
      const match = Array.isArray(rows)
        ? rows.find((r) => String(r.resident_id || '') === String(residentId))
        : null;
      if (!match) return;
      fillResidentProfileModal(match);
    } catch (_) {
      // Keep modal open with placeholders if fetch fails.
    }
  }

  function rowHtml(row) {
    const reason = row.status_reason ? `<div class="text-danger small mt-1">Reason: ${esc(row.status_reason)}</div>` : '';
    const fullName = fullNameFromRow(row);
    const residentInfo = residentInfoFromRow(row);
    const purpose = firstNonEmpty([row.purpose, '-']);
    return `
      <tr>
        <td class="fw-semibold">${esc(row.request_id)}</td>
        <td>${esc(row.resident_id || '-')}</td>
        <td>${esc(fullName)}</td>
        <td class="small text-muted">${esc(residentInfo)}</td>
        <td>
          <div>${esc(purpose)}</div>
          <div class="mt-1">${documentTypePill(row)}</div>
        </td>
        <td>${badge(row.stage, esc(row.stage_label || row.stage || ''))}${reason}</td>
        <td>${esc(row.submitted_at || '-')}</td>
        <td>${actionButtons(row)}</td>
      </tr>
    `;
  }

  function statusBucket(row) {
    const stage = String(row?.stage || '').toLowerCase();
    if (stage.includes('rejected')) return 'denied';
    if (stage === 'completed' || stage === 'ready_for_claim' || stage === 'payment_verified') return 'verified';
    return 'pending';
  }

  function matchesStatusFilter(row) {
    if (currentStatusFilter === 'all') return true;
    return statusBucket(row) === currentStatusFilter;
  }

  function matchesDocumentTypeFilter(row) {
    if (!currentDocumentTypeFilter) return true;
    return String(row?.document_type || '') === currentDocumentTypeFilter;
  }

  function syncDocumentTypeFilterOptions(items) {
    if (!documentTypeFilter) return;
    const selected = currentDocumentTypeFilter;
    const unique = Array.from(new Set(
      (items || [])
        .map((it) => String(it?.document_type || '').trim())
        .filter((v) => v !== '')
    )).sort((a, b) => a.localeCompare(b));

    documentTypeFilter.innerHTML = '<option value="">Filter: All Documents</option>';
    unique.forEach((docType) => {
      const opt = document.createElement('option');
      opt.value = docType;
      opt.textContent = docType;
      documentTypeFilter.appendChild(opt);
    });
    if (selected && unique.includes(selected)) {
      documentTypeFilter.value = selected;
    } else {
      currentDocumentTypeFilter = '';
      documentTypeFilter.value = '';
    }
  }

  async function fetchJson(url, options = {}) {
    const headers = new Headers(options.headers || {});
    if (!headers.has('Accept')) {
      headers.set('Accept', 'application/json');
    }
    headers.set('X-Requested-With', 'XMLHttpRequest');

    const res = await fetch(url, { ...options, headers, credentials: 'same-origin' });
    const contentType = (res.headers.get('content-type') || '').toLowerCase();
    const text = await res.text();
    const looksHtml = text.trim().startsWith('<!DOCTYPE') || text.trim().startsWith('<html');

    if (!contentType.includes('application/json')) {
      if (looksHtml) {
        throw new Error('Session expired or server returned HTML. Please reload and login again.');
      }
      throw new Error('Unexpected response format from server.');
    }

    let data;
    try {
      data = JSON.parse(text);
    } catch (_) {
      throw new Error('Invalid JSON response from server.');
    }

    if (!res.ok) {
      throw new Error(data?.message || `Request failed (${res.status}).`);
    }
    return data;
  }

  async function load() {
    tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr>';
    try {
      const params = new URLSearchParams({ action: 'list' });
      if (currentStage && currentStage !== 'finance') params.set('stage', currentStage);
      const q = (searchInput.value || '').trim();
      if (q) params.set('q', q);

      const data = await fetchJson(`${endpoint}?${params.toString()}`);
      if (!data.success) throw new Error(data.message || 'Failed to load requests.');

      const allItems = Array.isArray(data.items) ? data.items : [];
      const stageItems = currentStage === 'finance'
        ? allItems.filter((it) => financeStages.has(String(it.stage || '').toLowerCase()))
        : allItems;
      syncDocumentTypeFilterOptions(stageItems);
      const items = stageItems
        .filter(matchesStatusFilter)
        .filter(matchesDocumentTypeFilter);
      itemById = new Map(items.map((it) => [String(it.request_id), it]));
      if (!items.length) {
        tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No requests found.</td></tr>';
        return;
      }

      tableBody.innerHTML = items.map(rowHtml).join('');
      bindActionButtons();
    } catch (err) {
      tableBody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${esc(err.message || err)}</td></tr>`;
    }
  }

  function resetModalFields() {
    modalError.classList.add('d-none');
    modalError.textContent = '';
    actionReasonWrap.classList.add('d-none');
    actionAmountWrap.classList.add('d-none');
    actionOrWrap.classList.add('d-none');
    actionIssuedWrap.classList.add('d-none');
    actionReason.required = false;
    actionAmount.required = false;
    actionOr.required = false;
    actionIssued.required = false;
    actionReason.value = '';
    actionAmount.value = '';
    actionOr.value = '';
    actionIssued.value = '';
  }

  function clearModalError() {
    modalError.classList.add('d-none');
    modalError.textContent = '';
  }

  function openActionModal(type, requestId) {
    if (!actionModal) return;

    if (viewModalEl && viewModalEl.classList.contains('show') && viewModal) {
      viewModal.hide();
    }

    resetModalFields();
    actionType.value = type;
    actionRequestId.value = requestId;
    const row = itemById.get(String(requestId));

    const labels = {
      personnel_approve: 'Approve Request',
      personnel_reject: 'Reject Request',
      finance_verify: 'Verify Payment',
      finance_reject: 'Reject Payment',
      mark_ready: 'Mark Ready for Claim',
      mark_completed: 'Mark Completed'
    };
    modalTitle.textContent = labels[type] || 'Update Request';

    if (type === 'personnel_reject' || type === 'finance_reject') {
      actionReasonWrap.classList.remove('d-none');
      actionReason.required = true;
    }
    if (type === 'finance_verify') {
      actionAmountWrap.classList.remove('d-none');
      actionOrWrap.classList.remove('d-none');
      actionAmount.required = true;
      actionOr.required = true;
      if (row && row.fee_amount !== null && row.fee_amount !== undefined && String(row.fee_amount) !== '') {
        actionAmount.value = String(row.fee_amount);
      }
    }
    if (type === 'mark_ready') {
      actionIssuedWrap.classList.remove('d-none');
    }

    actionModal.show();
  }

  function bindActionButtons() {
    tableBody.querySelectorAll('button[data-view-id]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = String(btn.getAttribute('data-view-id') || '');
        const row = itemById.get(id);
        if (!row || !viewDetailsBody || !viewModal) return;

        const payload = row.payload && typeof row.payload === 'object' ? row.payload : {};
        const fullName = fullNameFromRow(row);
        const residentInfo = residentInfoFromRow(row);
        let html = '';
        html += profileSection('Request Summary', [
          profileItem('Request ID', esc(row.request_id || '-')),
          profileItem('Resident ID', esc(row.resident_id || '-')),
          profileItem('Full Name', esc(fullName || '-')),
          profileItem('Document Requested', documentTypePill(row)),
          profileItem('Purpose', esc(row.purpose || '-')),
          profileItem('Information', esc(residentInfo || '-')),
          profileItem('Stage', esc(row.stage_label || row.stage || '-')),
          profileItem('Status Reason', esc(row.status_reason || '-')),
          profileItem('Submitted At', esc(row.submitted_at || '-'))
        ].join(''));

        html += profileSection('Payment and Release', [
          profileItem('Payment Method', esc(row.payment_method || '-')),
          profileItem('Amount', esc(row.amount || '-')),
          profileItem('OR Number', esc(row.or_number || '-')),
          profileItem('Certificate Number', esc(row.certificate_number || '-'))
        ].join(''));

        const hiddenPayloadKeys = new Set([
          'first_name', 'firstname', 'middle_name', 'middlename', 'last_name', 'lastname', 'suffix', 'suffix_name',
          'sex', 'gender', 'birthdate', 'date_of_birth', 'age', 'full_address', 'address', 'complete_address'
        ]);
        const payloadItems = [];
        Object.keys(payload).forEach((key) => {
          if (hiddenPayloadKeys.has(String(key))) return;
          payloadItems.push(profileItem(key, esc(payload[key])));
        });
        if (payloadItems.length) {
          html += profileSection('Other Submitted Fields', payloadItems.join(''));
        }

        viewDetailsBody.innerHTML = html || '<div class="text-muted">No details.</div>';
        if (viewModalActions) {
          viewModalActions.innerHTML = viewModalActionButtons(row);
          viewModalActions.querySelectorAll('button[data-view-action][data-id]').forEach((actionBtn) => {
            actionBtn.addEventListener('click', () => {
              openActionModal(actionBtn.getAttribute('data-view-action') || '', actionBtn.getAttribute('data-id') || '');
            });
          });
          viewModalActions.querySelectorAll('button[data-view-profile-id]').forEach((profileBtn) => {
            profileBtn.addEventListener('click', () => {
              openResidentProfileModal(String(profileBtn.getAttribute('data-view-profile-id') || ''));
            });
          });
          viewModalActions.querySelectorAll('button[data-proof-id]').forEach((proofBtn) => {
            proofBtn.addEventListener('click', () => {
              const proofId = String(proofBtn.getAttribute('data-proof-id') || '');
              const proofRow = itemById.get(proofId);
              if (!proofRow || !proofRow.payment_proof_path || !paymentProofModal || !paymentProofWrap || !paymentProofOpenNew) return;
              const proofUrl = `${appBase}/PhpFiles/Admin-End/documentRequestWorkflow.php?action=view_payment_proof&request_id=` + encodeURIComponent(proofId);
              paymentProofOpenNew.href = proofUrl;
              const path = String(proofRow.payment_proof_path || '').toLowerCase();
              if (path.endsWith('.pdf')) {
                paymentProofWrap.innerHTML = `<iframe src="${proofUrl}" style="width:100%;height:70vh;border:1px solid #ddd;border-radius:8px;"></iframe>`;
              } else {
                paymentProofWrap.innerHTML = `<img src="${proofUrl}" alt="Payment Proof" style="max-width:100%;max-height:70vh;border:1px solid #ddd;border-radius:8px;">`;
              }
              paymentProofModal.show();
            });
          });
        }
        viewModal.show();
      });
    });

    tableBody.querySelectorAll('button[data-proof-id]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = String(btn.getAttribute('data-proof-id') || '');
        const row = itemById.get(id);
        if (!row || !row.payment_proof_path || !paymentProofModal || !paymentProofWrap || !paymentProofOpenNew) return;

        const proofUrl = `${appBase}/PhpFiles/Admin-End/documentRequestWorkflow.php?action=view_payment_proof&request_id=` + encodeURIComponent(id);
        paymentProofOpenNew.href = proofUrl;

        const path = String(row.payment_proof_path || '').toLowerCase();
        if (path.endsWith('.pdf')) {
          paymentProofWrap.innerHTML = `<iframe src="${proofUrl}" style="width:100%;height:70vh;border:1px solid #ddd;border-radius:8px;"></iframe>`;
        } else {
          paymentProofWrap.innerHTML = `<img src="${proofUrl}" alt="Payment Proof" style="max-width:100%;max-height:70vh;border:1px solid #ddd;border-radius:8px;">`;
        }
        paymentProofModal.show();
      });
    });

  }

  actionForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearModalError();

    const fd = new FormData();
    fd.append('action', actionType.value || '');
    fd.append('request_id', actionRequestId.value || '');

    if (actionReasonWrap && !actionReasonWrap.classList.contains('d-none')) {
      fd.append('reason', actionReason.value || '');
    }
    if (actionAmountWrap && !actionAmountWrap.classList.contains('d-none')) {
      fd.append('amount', actionAmount.value || '');
    }
    if (actionOrWrap && !actionOrWrap.classList.contains('d-none')) {
      fd.append('or_number', actionOr.value || '');
    }
    if (actionIssuedWrap && !actionIssuedWrap.classList.contains('d-none') && actionIssued.files?.[0]) {
      fd.append('issued_file', actionIssued.files[0]);
    }

    try {
      const data = await fetchJson(endpoint, {
        method: 'POST',
        body: fd
      });
      if (!data.success) throw new Error(data.message || 'Action failed');

      actionModal.hide();
      await load();
    } catch (err) {
      modalError.textContent = err.message || String(err);
      modalError.classList.remove('d-none');
    }
  });

  stageTabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      stageTabs.forEach((x) => x.classList.remove('active'));
      tab.classList.add('active');
      currentStage = tab.getAttribute('data-stage-filter') || '';
      load();
    });
  });

  statusTabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      statusTabs.forEach((x) => x.classList.remove('active'));
      tab.classList.add('active');
      currentStatusFilter = String(tab.getAttribute('data-status-filter') || 'all').toLowerCase();
      load();
    });
  });

  documentTypeFilter?.addEventListener('change', () => {
    currentDocumentTypeFilter = String(documentTypeFilter.value || '');
    load();
  });

  btnRefreshList?.addEventListener('click', () => {
    load();
  });

  let searchTimer = null;
  searchInput?.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(load, 250);
  });

  load();
})();
