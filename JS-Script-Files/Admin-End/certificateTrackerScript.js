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
  const paymentProofModalEl = document.getElementById('paymentProofModal');
  const paymentProofModal = paymentProofModalEl ? new bootstrap.Modal(paymentProofModalEl) : null;
  const paymentProofWrap = document.getElementById('paymentProofWrap');
  const paymentProofOpenNew = document.getElementById('paymentProofOpenNew');

  let currentStage = String(window.CERT_TRACKER_DEFAULT_STAGE || '');
  let itemById = new Map();

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
    const stage = String(row.stage || '');
    if (stage === 'submitted') {
      return `
        ${viewBtn}${proofBtn}
        <button class="btn btn-sm btn-success me-1" data-action="personnel_approve" data-id="${esc(row.request_id)}">Approve</button>
        <button class="btn btn-sm btn-danger" data-action="personnel_reject" data-id="${esc(row.request_id)}">Reject</button>
      `;
    }
    if (stage === 'payment_submitted') {
      return `
        ${viewBtn}${proofBtn}
        <button class="btn btn-sm btn-success me-1" data-action="finance_verify" data-id="${esc(row.request_id)}">Verify Payment</button>
        <button class="btn btn-sm btn-danger" data-action="finance_reject" data-id="${esc(row.request_id)}">Reject Payment</button>
      `;
    }
    if (stage === 'payment_verified') {
      return `${viewBtn}${proofBtn}<button class="btn btn-sm btn-primary" data-action="mark_ready" data-id="${esc(row.request_id)}">Ready for Claim</button>`;
    }
    if (stage === 'ready_for_claim') {
      return `${viewBtn}${proofBtn}<button class="btn btn-sm btn-dark" data-action="mark_completed" data-id="${esc(row.request_id)}">Mark Completed</button>`;
    }
    return `${viewBtn}${proofBtn}<span class="text-muted small">-</span>`;
  }

  function rowHtml(row) {
    const reason = row.status_reason ? `<div class="text-danger small mt-1">Reason: ${esc(row.status_reason)}</div>` : '';
    return `
      <tr>
        <td class="fw-semibold">${esc(row.request_id)}</td>
        <td>${esc(row.resident_id || '-')}</td>
        <td>${esc(row.document_type)}</td>
        <td>${esc(row.purpose || '-')}</td>
        <td>${badge(row.stage, esc(row.stage_label || row.stage || ''))}${reason}</td>
        <td>${esc(row.submitted_at || '-')}</td>
        <td>${actionButtons(row)}</td>
      </tr>
    `;
  }

  async function load() {
    tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>';
    try {
      const params = new URLSearchParams({ action: 'list' });
      if (currentStage) params.set('stage', currentStage);
      const q = (searchInput.value || '').trim();
      if (q) params.set('q', q);

      const res = await fetch(`${endpoint}?${params.toString()}`, { credentials: 'same-origin' });
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'Failed to load requests.');

      const items = Array.isArray(data.items) ? data.items : [];
      itemById = new Map(items.map((it) => [String(it.request_id), it]));
      if (!items.length) {
        tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No requests found.</td></tr>';
        return;
      }

      tableBody.innerHTML = items.map(rowHtml).join('');
      bindActionButtons();
    } catch (err) {
      tableBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${esc(err.message || err)}</td></tr>`;
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
        const fixedRows = [
          ['Request ID', row.request_id],
          ['Resident ID', row.resident_id],
          ['Document Type', row.document_type],
          ['Purpose', row.purpose || '-'],
          ['Stage', row.stage_label || row.stage || '-'],
          ['Status Reason', row.status_reason || '-'],
          ['Submitted At', row.submitted_at || '-'],
          ['Payment Method', row.payment_method || '-'],
          ['Amount', row.amount || '-'],
          ['OR Number', row.or_number || '-'],
          ['Certificate Number', row.certificate_number || '-'],
        ];

        let html = fixedRows.map(([k, v]) => `<tr><th style="width:220px;">${esc(k)}</th><td>${esc(v ?? '-')}</td></tr>`).join('');
        Object.keys(payload).forEach((key) => {
          html += `<tr><th>${esc(key)}</th><td>${esc(payload[key])}</td></tr>`;
        });
        viewDetailsBody.innerHTML = html || '<tr><td class="text-muted">No details.</td></tr>';
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

    tableBody.querySelectorAll('button[data-action][data-id]').forEach((btn) => {
      btn.addEventListener('click', () => {
        openActionModal(btn.getAttribute('data-action') || '', btn.getAttribute('data-id') || '');
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
      const res = await fetch(endpoint, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      });
      const data = await res.json();
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

  let searchTimer = null;
  searchInput?.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(load, 250);
  });

  load();
})();
