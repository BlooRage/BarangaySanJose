(() => {
  const appBase = (() => {
    const marker = '/Admin-End/';
    const idx = window.location.pathname.indexOf(marker);
    if (idx === -1) return '';
    return window.location.pathname.slice(0, idx);
  })();

  const endpoint = `${appBase}/PhpFiles/Admin-End/blotterReviewQueueData.php`;
  const tableBody = document.getElementById('tableBody');
  const searchInput = document.getElementById('searchInput');
  const refreshBtn = document.getElementById('btnQueueRefresh');
  const filterButtons = Array.from(document.querySelectorAll('.status-filter-btn'));
  const pendingBadge = document.getElementById('pendingReviewBadge');
  const viewModalEl = document.getElementById('viewModal');
  const viewModal = viewModalEl ? new bootstrap.Modal(viewModalEl) : null;
  const viewModalTitle = document.getElementById('viewModalTitle');
  const viewDetailsBody = document.getElementById('viewDetailsBody');
  const requestActionButtons = document.getElementById('requestActionButtons');
  const approveBtn = document.getElementById('btnApproveRequest');
  const rejectBtn = document.getElementById('btnRejectRequest');
  const requestActionModalEl = document.getElementById('requestActionModal');
  const requestActionModal = requestActionModalEl ? new bootstrap.Modal(requestActionModalEl) : null;
  const requestActionModalTitle = document.getElementById('requestActionModalTitle');
  const blotterNumberGroup = document.getElementById('blotterNumberGroup');
  const blotterNumberInput = document.getElementById('blotterNumberInput');
  const requestActionNotes = document.getElementById('requestActionNotes');
  const btnRequestActionReturn = document.getElementById('btnRequestActionReturn');
  const btnRequestActionProceed = document.getElementById('btnRequestActionProceed');
  const requestActionConfirmModalEl = document.getElementById('requestActionConfirmModal');
  const requestActionConfirmModal = requestActionConfirmModalEl ? new bootstrap.Modal(requestActionConfirmModalEl) : null;
  const requestActionConfirmText = document.getElementById('requestActionConfirmText');
  const btnRequestActionConfirmReturn = document.getElementById('btnRequestActionConfirmReturn');
  const btnRequestActionConfirm = document.getElementById('btnRequestActionConfirm');

  let allRows = [];
  let filteredRows = [];
  let activeFilter = '';
  let currentRequestId = null;
  let currentDetail = null;
  let pendingRequestAction = null;

  function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
  }

  function badge(text, toneClass) {
    return `<span class="status-pill ${esc(toneClass || 'archived')}">${esc(text || '-')}</span>`;
  }

  function toneForStatus(statusName) {
    const status = String(statusName || '').trim().toLowerCase();
    if (status === 'pending') return 'pending';
    if (status === 'approved') return 'approved';
    if (status === 'rejected') return 'denied';
    return 'archived';
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

  function formSection(title, content) {
    return `
      <section class="tracker-form-section">
        <h6 class="tracker-form-section-title">${esc(title)}</h6>
        ${content}
      </section>
    `;
  }

  function renderFieldGrid(fields, cols = 2) {
    const clean = fields.filter((f) => f && String(f.value ?? '').trim() !== '');
    if (!clean.length) return '';
    const cls = cols >= 4 ? 'cols-4' : cols === 3 ? 'cols-3' : cols === 1 ? 'cols-1' : '';
    return `<div class="tracker-form-grid ${cls}">${clean.map((f) => formField(f.label, f.value, !!f.raw)).join('')}</div>`;
  }

  function buildTableRow(row) {
    return `
      <tr>
        <td>${esc(row.request_id || '-')}</td>
        <td>${esc(row.complaint_id || '-')}</td>
        <td>${esc(row.requested_at || '-')}</td>
        <td>${esc(row.complainant_name || '-')}</td>
        <td>${esc(row.complaint_type || '-')}</td>
        <td>${badge(row.request_status_name || 'Pending', toneForStatus(row.request_status_name))}</td>
        <td><span class="compact-table-actions"><button class="btn btn-sm btn-outline-secondary compact-table-btn" data-view-id="${esc(row.request_id)}">View</button></span></td>
      </tr>
    `;
  }

  function updatePendingBadge() {
    if (!pendingBadge) return;
    const count = allRows.filter((row) => String(row?.request_status_name || '').trim().toLowerCase() === 'pending').length;
    pendingBadge.textContent = String(count);
    pendingBadge.classList.toggle('d-none', count <= 0);
  }

  function renderTable() {
    if (!tableBody) return;
    if (!filteredRows.length) {
      tableBody.innerHTML = `<tr><td colspan="7" class="text-start text-muted py-4">No blotter requests found.</td></tr>`;
    } else {
      tableBody.innerHTML = filteredRows.map(buildTableRow).join('');
    }

    tableBody.querySelectorAll('button[data-view-id]').forEach((button) => {
      button.addEventListener('click', () => {
        const requestId = String(button.getAttribute('data-view-id') || '').trim();
        if (!requestId) return;
        openViewModal(requestId);
      });
    });
  }

  function applyFilters() {
    const term = String(searchInput?.value || '').trim().toLowerCase();
    filteredRows = allRows.filter((row) => {
      const status = String(row.request_status_name || '').trim().toLowerCase();
      if (activeFilter && status !== activeFilter) return false;
      if (!term) return true;
      return [
        row.request_id,
        row.complaint_id,
        row.complainant_name,
        row.complaint_type,
      ].some((value) => String(value || '').toLowerCase().includes(term));
    });
    renderTable();
  }

  async function fetchJson(url, options) {
    const res = await fetch(url, options);
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.success === false) {
      throw new Error(data.message || 'Request failed.');
    }
    return data;
  }

  async function loadList() {
    if (!tableBody) return;
    tableBody.innerHTML = `<tr><td colspan="7" class="text-start text-muted py-4">Loading blotter requests...</td></tr>`;
    try {
      const data = await fetchJson(`${endpoint}?action=list`);
      allRows = Array.isArray(data.items) ? data.items : [];
      updatePendingBadge();
      applyFilters();
    } catch (error) {
      tableBody.innerHTML = `<tr><td colspan="7" class="text-start text-danger py-4">${esc(error.message || error)}</td></tr>`;
    }
  }

  function setActionButtonsState(detail) {
    if (!requestActionButtons) return;
    const status = String(detail?.request_status_name || '').trim().toLowerCase();
    requestActionButtons.classList.toggle('d-none', status !== 'pending');
  }

  function transitionModal(fromEl, fromModal, toModal) {
    if (fromEl && fromEl.classList.contains('show') && fromModal) {
      fromEl.addEventListener('hidden.bs.modal', () => toModal?.show(), { once: true });
      fromModal.hide();
      return;
    }
    toModal?.show();
  }

  function openRequestActionModal(actionType) {
    if (!requestActionModal || !currentRequestId) return;
    pendingRequestAction = actionType;
    if (requestActionNotes) requestActionNotes.value = '';
    if (blotterNumberInput) blotterNumberInput.value = '';
    if (blotterNumberGroup) blotterNumberGroup.classList.toggle('d-none', actionType !== 'approved');
    if (requestActionModalTitle) {
      requestActionModalTitle.textContent = actionType === 'approved' ? 'Approve Request' : 'Reject Request';
    }
    transitionModal(viewModalEl, viewModal, requestActionModal);
  }

  async function submitRequestAction() {
    if (!pendingRequestAction || !currentRequestId) return;
    const reviewNotes = String(requestActionNotes?.value || '').trim();
    const blotterNumber = String(blotterNumberInput?.value || '').trim();
    if (pendingRequestAction === 'rejected' && !reviewNotes) {
      alert('Review notes are required when rejecting a request.');
      return;
    }
    if (pendingRequestAction === 'approved') {
      if (!blotterNumber) {
        alert('Blotter number is required.');
        blotterNumberInput?.focus();
        return;
      }
      if (!/^[A-Za-z0-9-]+$/.test(blotterNumber)) {
        alert('Blotter number may contain letters, numbers, and hyphens only.');
        blotterNumberInput?.focus();
        return;
      }
    }
    try {
      btnRequestActionConfirm.disabled = true;
      await fetchJson(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'update_request_status',
          request_id: currentRequestId,
          action_type: pendingRequestAction,
          review_notes: reviewNotes,
          blotter_number: blotterNumber,
        }),
      });
      const targetRequestId = currentRequestId;
      requestActionConfirmModalEl?.addEventListener('hidden.bs.modal', async () => {
        await loadList();
        if (targetRequestId) openViewModal(targetRequestId);
      }, { once: true });
      requestActionConfirmModal?.hide();
    } catch (error) {
      alert(error.message || error);
    } finally {
      btnRequestActionConfirm.disabled = false;
    }
  }

  async function openViewModal(requestId) {
    if (!viewModal || !viewDetailsBody) return;
    currentRequestId = String(requestId);
    currentDetail = null;
    if (viewModalTitle) viewModalTitle.textContent = 'Blotter Request Details';
    viewDetailsBody.innerHTML = '<div class="text-muted">Loading request details...</div>';
    setActionButtonsState(null);
    viewModal.show();

    try {
      const data = await fetchJson(`${endpoint}?action=detail&request_id=${encodeURIComponent(requestId)}`);
      const d = data.detail || {};
      currentDetail = d;
      setActionButtonsState(d);

      viewDetailsBody.innerHTML = [
        formSection('Request Summary', [
          renderFieldGrid([
            { label: 'Request ID', value: d.request_id || '-' },
            { label: 'Complaint ID', value: d.complaint_id || '-' },
            { label: 'Status', value: d.request_status_name || 'Pending' },
            { label: 'Requested At', value: d.requested_at || '-' },
            { label: 'Reviewed At', value: d.reviewed_at || '-' },
            { label: 'Approved Blotter ID', value: d.approved_blotter_id || '-' },
          ], 3),
          renderFieldGrid([
            { label: 'Review Notes', value: d.review_notes || '-' },
          ], 1),
        ].join('')),
        formSection('Complaint Details', [
          renderFieldGrid([
            { label: 'Complaint Type', value: d.complaint_type || '-' },
            { label: 'Subject', value: d.subject_display_name || '-' },
            { label: 'Subject Kind', value: d.subject_kind || '-' },
            { label: 'Subject Contact', value: d.subject_contact_number || '-' },
          ], 2),
          renderFieldGrid([
            { label: 'Incident Date', value: d.incident_date || '-' },
            { label: 'Incident Time', value: d.incident_time || '-' },
            { label: 'Incident Place', value: d.incident_place || '-' },
          ], 3),
          renderFieldGrid([
            { label: 'Subject Address', value: d.subject_address || '-' },
            { label: 'Intake Notes', value: d.intake_notes || '-' },
            { label: 'Screening Notes', value: d.screening_notes || '-' },
            { label: 'Case Remarks', value: d.case_remarks || '-' },
          ], 1),
          renderFieldGrid([
            { label: 'Complaint Narration', value: d.case_details || '-' },
          ], 1),
        ].join('')),
        formSection('Participants', [
          renderFieldGrid([
            { label: 'Complainant', value: d.complainant?.full_name || '-' },
            { label: 'Respondent', value: d.respondent?.full_name || '-' },
            { label: 'Witness', value: d.witness?.full_name || '-' },
          ], 3),
        ].join('')),
      ].join('');
    } catch (error) {
      viewDetailsBody.innerHTML = `<div class="text-danger">${esc(error.message || error)}</div>`;
      setActionButtonsState(null);
    }
  }

  approveBtn?.addEventListener('click', () => openRequestActionModal('approved'));
  rejectBtn?.addEventListener('click', () => openRequestActionModal('rejected'));
  btnRequestActionReturn?.addEventListener('click', () => transitionModal(requestActionModalEl, requestActionModal, viewModal));
  btnRequestActionProceed?.addEventListener('click', () => {
    const notes = String(requestActionNotes?.value || '').trim();
    const blotterNumber = String(blotterNumberInput?.value || '').trim();
    if (pendingRequestAction === 'rejected' && !notes) {
      alert('Review notes are required when rejecting a request.');
      return;
    }
    if (pendingRequestAction === 'approved') {
      if (!blotterNumber) {
        alert('Blotter number is required.');
        blotterNumberInput?.focus();
        return;
      }
      if (!/^[A-Za-z0-9-]+$/.test(blotterNumber)) {
        alert('Blotter number may contain letters, numbers, and hyphens only.');
        blotterNumberInput?.focus();
        return;
      }
    }
    requestActionConfirmText.textContent = pendingRequestAction === 'approved'
      ? `Approve this request and create blotter ${blotterNumber}?`
      : 'Reject this blotter request?';
    transitionModal(requestActionModalEl, requestActionModal, requestActionConfirmModal);
  });
  btnRequestActionConfirmReturn?.addEventListener('click', () => transitionModal(requestActionConfirmModalEl, requestActionConfirmModal, requestActionModal));
  btnRequestActionConfirm?.addEventListener('click', submitRequestAction);

  searchInput?.addEventListener('input', applyFilters);
  refreshBtn?.addEventListener('click', loadList);
  filterButtons.forEach((button) => {
    button.addEventListener('click', () => {
      filterButtons.forEach((btn) => {
        btn.classList.remove('btn-outline-primary', 'active');
        btn.classList.add('btn-outline-secondary');
      });
      button.classList.remove('btn-outline-secondary');
      button.classList.add('btn-outline-primary', 'active');
      activeFilter = String(button.dataset.filter || '').trim().toLowerCase();
      applyFilters();
    });
  });

  loadList();
})();
