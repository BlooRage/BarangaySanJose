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
  const pendingTabCount = document.getElementById('pendingTabCount');
  const releaseTabCount = document.getElementById('releaseTabCount');
  const unpaidTabCount = document.getElementById('unpaidTabCount');
  const pendingVerificationTabCount = document.getElementById('pendingVerificationTabCount');
  const statusTabs = Array.from(document.querySelectorAll('[data-status-filter]'));
  const btnRefreshList = document.getElementById('btnRefreshList');
  const documentTypeFilter = document.getElementById('documentTypeFilter');
  const financeFilterDocType = document.getElementById('financeFilterDocumentType');
  const financeFilterPaymentMethod = document.getElementById('financeFilterPaymentMethod');
  const btnFinanceFilterApply = document.getElementById('btnFinanceFilterApply');
  const btnFinanceFilterReset = document.getElementById('btnFinanceFilterReset');
  const financeColChecks = Array.from(document.querySelectorAll('[data-finance-col-index]'));
  const btnFinanceColumnsReset = document.getElementById('btnFinanceColumnsReset');

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
  const actionBusinessApprovalWrap = document.getElementById('actionBusinessApprovalWrap');
  const actionBusinessApproval = document.getElementById('actionBusinessApproval');
  const actionPlateWrap = document.getElementById('actionPlateWrap');
  const actionPlate = document.getElementById('actionPlate');
  const actionPrompt = document.getElementById('actionPrompt');
  const actionCancelBtn = document.getElementById('actionCancelBtn');
  const actionSubmitBtn = document.getElementById('actionSubmitBtn');
  const modalTitle = document.getElementById('actionModalTitle');
  const modalError = document.getElementById('actionModalError');
  const viewModalEl = document.getElementById('viewModal');
  const viewModal = viewModalEl ? new bootstrap.Modal(viewModalEl) : null;
  const viewModalTitle = document.getElementById('viewModalTitle');
  const viewDetailsBody = document.getElementById('viewDetailsBody');
  const viewModalWalkInBtn = document.getElementById('viewModalWalkInBtn');
  const viewModalActions = document.getElementById('viewModalActions');
  const viewModalDocBtn = document.getElementById('viewModalDocBtn');
  const viewModalBackBtn = document.getElementById('viewModalBackBtn');
  const viewModalNextBtn = document.getElementById('viewModalNextBtn');
  const paymentProofModalEl = document.getElementById('paymentProofModal');
  const paymentProofModal = paymentProofModalEl ? new bootstrap.Modal(paymentProofModalEl) : null;
  const paymentProofWrap = document.getElementById('paymentProofWrap');
  const paymentProofOpenNew = document.getElementById('paymentProofOpenNew');
  const paymentProofPrintBtn = document.getElementById('paymentProofPrintBtn');
  const paymentProofTitle = document.getElementById('paymentProofTitle');
  const paymentProofReturnBtn = document.getElementById('paymentProofReturnBtn');
  const paymentProofCloseBtn = document.getElementById('paymentProofCloseBtn');
  const submittedFileModalEl = document.getElementById('submittedFileModal');
  const submittedFileModal = submittedFileModalEl ? new bootstrap.Modal(submittedFileModalEl) : null;
  const submittedFileWrap = document.getElementById('submittedFileWrap');
  const submittedFileOpenNew = document.getElementById('submittedFileOpenNew');
  const submittedFileTitle = document.getElementById('submittedFileTitle');
  const submittedFileReturnBtn = document.getElementById('submittedFileReturnBtn');
  const submittedFileCloseBtn = document.getElementById('submittedFileCloseBtn');
  const residentProfileModalEl = document.getElementById('residentProfileModal');
  const residentProfileModal = residentProfileModalEl ? new bootstrap.Modal(residentProfileModalEl) : null;
  const residentProfileReturnBtn = document.getElementById('residentProfileReturnBtn');
  const residentProfileEndpoint = `${appBase}/PhpFiles/Admin-End/residentMasterlist.php`;

  let currentStage = String(window.CERT_TRACKER_DEFAULT_STAGE || '');
  let currentStatusFilter = 'all';
  let currentDocumentTypeFilter = '';
  let financeFilterDocumentType = '';
  let financeFilterMethod = '';
  let cachedAllItems = [];
  let itemById = new Map();
  const detailById = new Map();
  let viewMode = 'details';
  let viewDetailsHtml = '';
  let viewPreviewState = null;
  let currentViewRequestId = '';
  let currentViewStage = '';
  let actionReturnTarget = '';
  let suppressActionReturn = false;
  let openPreviewAfterActionModal = false;
  let openViewDirectPreview = false;
  let paymentProofReturnTarget = '';
  let paymentProofPrintUrl = '';
  let submittedFileReturnTarget = '';
  let preserveViewStateOnNextHide = false;
  let financeViewIntent = 'view';
  let templatePreviewRequestSeq = 0;
  let previewScrollCleanup = null;
  const financeStages = new Set([
    'for_payment',
    'payment_submitted',
    'payment_rejected',
    'payment_verified',
    'ready_for_claim',
    'completed'
  ]);
  const isFinancePaymentsPage = window.location.pathname.toLowerCase().includes('/admin-end/certificates/financepayments.php');
  const financeColumnsStorageKey = 'financePaymentsVisibleColumns';
  const defaultFinanceVisibleColumns = [1, 3, 4, 6, 7, 8];

  function esc(v) {
    return String(v ?? '').replace(/[&<>\"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '\"': '&quot;', "'": '&#39;' }[m]));
  }

  function resolvePublicUrl(path) {
    const raw = String(path || '').trim();
    if (!raw) return '';
    if (/^https?:\/\//i.test(raw)) return raw;
    if (raw.startsWith('../') || raw.startsWith('./')) {
      const normalized = raw.replace(/^(\.\.\/)+/, '').replace(/^\.\//, '');
      return `${appBase}/${normalized.replace(/^\/+/, '')}`;
    }
    if (appBase && raw.startsWith(`${appBase}/`)) return raw;
    if (raw.startsWith('/')) return `${appBase}${raw}`;
    return `${appBase}/${raw.replace(/^\/+/, '')}`;
  }

  function resolvePaymentProofUrl(row, requestId = '') {
    const raw = String(row?.payment_proof_path || '').trim();
    if (!raw) return '';

    // Prefer direct file URL for reliable preview rendering.
    const unifiedMatch = raw.replace(/\\/g, '/').match(/\/UnifiedFileAttachment\/[^\s"'<>]+/i);
    if (unifiedMatch && unifiedMatch[0]) {
      return `${appBase}${unifiedMatch[0]}`;
    }

    const direct = resolvePublicUrl(raw);
    if (direct) return direct;

    const id = String(requestId || row?.request_id || '').trim();
    if (!id) return '';
    return `${appBase}/PhpFiles/Admin-End/documentRequestWorkflow.php?action=view_payment_proof&request_id=${encodeURIComponent(id)}`;
  }

  function issuedTemplateDocxUrl(requestId) {
    const id = String(requestId || '').trim();
    if (!id) return '';
    return `${appBase}/PhpFiles/Admin-End/documentRequestWorkflow.php?action=view_preview_docx&request_id=${encodeURIComponent(id)}&_ts=${Date.now()}`;
  }

  function issuedTemplateDocxImageUrl(requestId) {
    const id = String(requestId || '').trim();
    if (!id) return '';
    return `${appBase}/PhpFiles/Admin-End/documentRequestWorkflow.php?action=view_preview_docx_image&request_id=${encodeURIComponent(id)}&_ts=${Date.now()}`;
  }

  function renderTemplatePreviewLoading(message = 'Loading certificate preview...') {
    viewDetailsBody.innerHTML = `
      <div class="tracker-form-section">
        <div class="tracker-form-label mb-2">Certificate Preview</div>
        <div class="text-muted">${esc(message)}</div>
      </div>
    `;
  }

  function templateEditFieldConfig(docType) {
    const key = normalizePreviewDocKey(docType);
    if (key === 'indigency') {
      return [
        { key: 'requestOfficerLine1', label: 'Official Name' },
        { key: 'requestOfficerLine2', label: 'Position' },
        { key: 'requestOfficerLine3', label: 'Jurisdiction', wide: true },
        { key: 'purpose', label: 'Purpose', multiline: true, wide: true }
      ];
    }
    if (key === 'goodmoral') {
      return [
        { key: 'purpose', label: 'Purpose', multiline: true, wide: true }
      ];
    }
    if (key === 'residency') {
      return [
        { key: 'remarks', label: 'Remarks', multiline: true, wide: true }
      ];
    }
    if (key === 'cohabitation') {
      return [
        { key: 'remarks', label: 'Remarks', multiline: true, wide: true }
      ];
    }
    if (key === 'businessclearance') {
      return [
        { key: 'businessName', label: 'Business Name', wide: true },
        { key: 'businessAddress', label: 'Business Address', multiline: true, wide: true },
        { key: 'operatorName', label: 'Operator Name', wide: true },
        { key: 'operatorAddress', label: 'Operator Address', multiline: true, wide: true },
        { key: 'orNumber', label: 'OR Number' },
        { key: 'amount', label: 'Amount' },
        { key: 'plateNumber', label: 'Plate Number' }
      ];
    }
    return [
      { key: 'birthdate', label: 'Birthdate' },
      { key: 'birthplace', label: 'Birthplace' },
      { key: 'location', label: 'Location', multiline: true, wide: true },
      { key: 'remarks', label: 'Remarks', multiline: true, wide: true },
      { key: 'purpose', label: 'Purpose', multiline: true, wide: true }
    ];
  }

  function renderTemplateEditFields() {
    if (!viewPreviewState || typeof viewPreviewState !== 'object') return '';
    const config = templateEditFieldConfig(viewPreviewState.docType || '');
    if (!config.length) return '';
    return `
      <div class="tracker-form-grid" aria-label="Editable template fields">
        ${config.map((field) => {
          const value = String(viewPreviewState[field.key] || '').trim();
          return `
            <label class="tracker-form-field${field.wide ? ' tracker-form-field--wide' : ''}">
              <span class="tracker-form-label">${esc(field.label)}</span>
              ${field.multiline
                ? `<textarea class="form-control" data-template-edit-key="${esc(field.key)}" rows="2">${esc(value)}</textarea>`
                : `<input class="form-control" type="text" data-template-edit-key="${esc(field.key)}" value="${esc(value)}">`}
            </label>
          `;
        }).join('')}
      </div>
    `;
  }

  let templateRefreshTimer = null;
  let templatePreviewObjectUrl = '';

  async function fetchTemplatePreviewAsset(requestId, options = {}) {
    const previewUrl = issuedTemplateDocxImageUrl(requestId);
    if (!previewUrl) throw new Error('Preview URL is unavailable.');
    const headers = { 'X-Requested-With': 'XMLHttpRequest' };
    let fetchOptions = {
      credentials: 'same-origin',
      headers
    };
    if (options.editedState && typeof options.editedState === 'object') {
      const fd = new FormData();
      fd.append('edited_preview', JSON.stringify(options.editedState));
      fetchOptions = {
        ...fetchOptions,
        method: 'POST',
        body: fd
      };
    }
    const res = await fetch(previewUrl, fetchOptions);
    if (!res.ok) {
      const failureText = String(await res.text().catch(() => '') || '').trim();
      throw new Error(failureText || `Preview request failed with status ${res.status}`);
    }
    const contentType = String(res.headers.get('content-type') || '').toLowerCase();
    if (!contentType.includes('image/')) {
      const failureText = String(await res.text().catch(() => '') || '').trim();
      throw new Error(failureText || `Unsupported preview type: ${contentType || 'unknown'}`);
    }
    return {
      blob: await res.blob(),
      docxUrl: issuedTemplateDocxUrl(requestId)
    };
  }

  function renderTemplatePreviewShell(requestId, docxHref = '') {
    const docxUrl = issuedTemplateDocxUrl(requestId);
    const frameName = `templateDocxPreviewFrame_${esc(String(requestId || '').trim() || 'preview')}`;
    return `
      <div class="tracker-form-section">
        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-2">
          <div class="tracker-form-label mb-0">Editable Information</div>
          ${docxUrl ? `<a class="btn btn-sm btn-outline-primary" href="${docxUrl}" target="_blank" rel="noopener">Open .docx Template</a>` : ''}
        </div>
        ${renderTemplateEditFields()}
      </div>
      <div class="tracker-form-section">
        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-2">
          <div class="tracker-form-label mb-0">Template Preview (.docx)</div>
          <div class="small text-muted js-template-preview-status">Preparing generated .docx preview...</div>
        </div>
        <div class="template-preview-stack">
          <div class="js-template-preview-placeholder${docxHref ? ' d-none' : ''} text-muted">Preparing generated .docx preview...</div>
          <div class="js-template-preview-docx${docxHref ? '' : ' d-none'}">
            <div class="bg-white border rounded-3 p-3 overflow-auto text-center" style="min-height:72vh;max-height:72vh;">
              <img
                class="js-template-preview-docx-image img-fluid"
                alt="Generated .docx preview"
                style="max-width:100%;height:auto;"
              />
            </div>
          </div>
        </div>
      </div>
    `;
  }

  function setTemplatePreviewStatus(message, isError = false) {
    if (!viewDetailsBody) return;
    const statusEl = viewDetailsBody.querySelector('.js-template-preview-status');
    if (statusEl) {
      statusEl.textContent = String(message || '').trim();
      statusEl.classList.toggle('text-danger', !!isError);
      statusEl.classList.toggle('text-muted', !isError);
    }
  }

  function mountTemplatePreviewImage(objectUrl) {
    if (!viewDetailsBody) return;
    const docxWrap = viewDetailsBody.querySelector('.js-template-preview-docx');
    const docxImage = viewDetailsBody.querySelector('.js-template-preview-docx-image');
    const placeholder = viewDetailsBody.querySelector('.js-template-preview-placeholder');
    if (!docxWrap || !docxImage) return;
    const previousUrl = templatePreviewObjectUrl;
    templatePreviewObjectUrl = objectUrl;
    docxImage.src = objectUrl;
    docxWrap.classList.remove('d-none');
    if (placeholder) placeholder.classList.add('d-none');
    if (previousUrl && String(previousUrl).startsWith('blob:')) {
      setTimeout(() => URL.revokeObjectURL(previousUrl), 1000);
    }
  }

  function bindTemplateFieldEditors(requestId) {
    if (!viewDetailsBody) return;
    viewDetailsBody.querySelectorAll('[data-template-edit-key]').forEach((input) => {
      input.addEventListener('input', () => {
        if (!viewPreviewState) return;
        const key = String(input.getAttribute('data-template-edit-key') || '').trim();
        if (!key) return;
        viewPreviewState[key] = String(input.value || '').trim().toUpperCase();
        if (templateRefreshTimer) clearTimeout(templateRefreshTimer);
        setTemplatePreviewStatus('Updating generated .docx preview...');
        templateRefreshTimer = setTimeout(() => {
          if (String(currentViewRequestId || '').trim() !== String(requestId || '').trim()) return;
          loadTemplatePreview(requestId, { preserveExisting: true, editedState: viewPreviewState });
        }, 180);
      });
    });
  }

  async function loadTemplatePreview(requestId, options = {}) {
    if (!viewDetailsBody) return;
    // DOCX conversion preview removed: render HTML/JS certificate preview directly.
    const previewState = viewPreviewState && typeof viewPreviewState === 'object'
      ? viewPreviewState
      : buildPreviewState({}, {}, {});
    viewDetailsBody.innerHTML = renderDocumentPreview(previewState);
    bindPreviewEditHandlers();
  }

  function badge(stage, label) {
    if (isFinancePaymentsPage) {
      const bucket = String(stage || '').toLowerCase();
      if (bucket === 'verified') return `<span class="badge bg-success">${label}</span>`;
      if (bucket === 'pending_verification') return `<span class="badge bg-warning text-dark">${label}</span>`;
      if (bucket === 'unpaid') return `<span class="badge bg-secondary">${label}</span>`;
      if (bucket === 'rejected') return `<span class="badge bg-danger">${label}</span>`;
      if (bucket === 'cancelled') return `<span class="badge bg-dark">${label}</span>`;
      return `<span class="badge bg-secondary">${label}</span>`;
    }
    const k = String(stage || '').toLowerCase();
    if (k.includes('rejected')) return `<span class="badge bg-danger">${label}</span>`;
    if (k === 'completed') return `<span class="badge bg-success">${label}</span>`;
    if (k === 'ready_for_claim') return `<span class="badge bg-primary">${label}</span>`;
    if (k === 'for_payment' || k === 'payment_submitted') return `<span class="badge bg-warning text-dark">${label}</span>`;
    return `<span class="badge bg-secondary">${label}</span>`;
  }

  function actionButtons(row) {
    const viewBtn = `<button class="btn btn-sm btn-outline-secondary me-1" data-view-id="${esc(row.request_id)}">View</button>`;
    const stageKey = String(row.stage || '').toLowerCase();
    const hasIssuedFile = String(row.issued_file_path || '').trim() !== '';
    const canViewIssuedByStage = stageKey === 'completed' || stageKey === 'ready_for_claim' || stageKey === 'payment_verified';
    const viewIssuedBtn = (!isFinancePaymentsPage && stageKey !== 'completed' && (hasIssuedFile || canViewIssuedByStage))
      ? `<button class="btn btn-sm btn-outline-success me-1" data-issued-id="${esc(row.request_id)}">View Document</button>`
      : '';
    if (isFinancePaymentsPage) {
      const financeKey = statusBucket(row);
      if (financeKey === 'pending_verification') {
        return `${viewBtn}<button class="btn btn-sm btn-success" data-inline-action="finance_verify_gcash" data-id="${esc(row.request_id)}">Verify Payment</button>`;
      }
      if (financeKey === 'unpaid') {
        return viewBtn;
      }
      return viewBtn;
    }
    return `${viewBtn}${viewIssuedBtn}`;
  }

  function viewModalActionButtons(row) {
    if (!row) return '';
    const id = esc(row.request_id || '');
    const isFirstTimeJobSeeker = isFirstTimeJobSeekerRow(row);
    const normalizeStageToken = (value) => String(value || '')
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '');
    const stageRaw = normalizeStageToken(row.stage);
    const stageLabel = normalizeStageToken(row.stage_label);
    const paymentToken = normalizeStageToken(firstNonEmpty([row.payment_status_name, row.payment_status_label]));
    const financeLikeStages = new Set([
      'for_payment',
      'payment_submitted',
      'payment_rejected',
      'payment_verified',
      'ready_for_claim',
      'completed'
    ]);
    let stage = stageRaw || stageLabel;

    // Guard against stale `row.stage` values (e.g. "submitted") by honoring
    // explicit finance/payment labels first when present.
    if (financeLikeStages.has(stageLabel)) {
      stage = stageLabel;
    }
    // Map payment-status tokens conservatively so "Pending Verification"
    // remains an initial review state (submitted), while payment flow uses
    // explicit payment tokens (e.g. Pending Payment Verification).
    // Only let payment token override when stage is absent or already in finance flow.
    const canDeriveFromPaymentToken = !stageRaw || financeLikeStages.has(stageRaw) || financeLikeStages.has(stageLabel);
    if (canDeriveFromPaymentToken) {
      if (['paymentsubmitted', 'pending_payment_verification'].includes(paymentToken)) {
        stage = 'payment_submitted';
      } else if (['unpaid', 'pending'].includes(paymentToken)) {
        stage = 'for_payment';
      } else if (['rejected', 'denied', 'paymentrejected'].includes(paymentToken)) {
        stage = 'payment_rejected';
      } else if (['verified', 'approved'].includes(paymentToken)) {
        stage = 'payment_verified';
      }
    }
    const proofBtn = (isFinancePaymentsPage && row.payment_proof_path)
      ? `<button class="btn btn-sm btn-outline-dark" data-proof-id="${id}">View Payment</button>`
      : '';

    if (stage === 'submitted') {
      return `
        <button class="btn btn-sm btn-danger" data-view-action="personnel_reject" data-id="${id}">Reject</button>
        <button class="btn btn-sm btn-success" data-view-action="personnel_approve" data-id="${id}">${isFirstTimeJobSeeker ? 'Approve for Interview' : 'Approve'}</button>
      `;
    }
    if (stage === 'for_interview' && isFirstTimeJobSeeker) {
      return `
        <button class="btn btn-sm btn-danger" data-view-action="interview_fail" data-id="${id}">Fail Interview</button>
        <button class="btn btn-sm btn-success" data-view-action="interview_pass" data-id="${id}">Pass Interview</button>
      `;
    }
    if (stage === 'payment_submitted') {
      if (!isFinancePaymentsPage) {
        return proofBtn || '<span class="text-muted small">No actions</span>';
      }
      return `
        ${proofBtn}
        <button class="btn btn-sm btn-success" data-view-action="finance_verify" data-id="${id}">Verify Payment</button>
        <button class="btn btn-sm btn-danger" data-view-action="finance_reject" data-id="${id}">Reject Payment</button>
      `;
    }
    if (stage === 'for_payment' || stage === 'payment_rejected') {
      if (!isFinancePaymentsPage) {
        return proofBtn || '<span class="text-muted small">No actions</span>';
      }
      return `
        ${proofBtn}
        <button class="btn btn-sm btn-success" data-view-action="finance_verify" data-id="${id}">Record Walk-in Payment</button>
      `;
    }
    if (stage === 'payment_verified') {
      return `
        ${proofBtn}
        <button class="btn btn-sm btn-primary" data-view-action="mark_ready" data-id="${id}">Ready for Claim</button>
      `;
    }
    if (stage === 'ready_for_claim') {
      return `
        ${proofBtn}
        <button class="btn btn-sm btn-dark w-100" data-view-action="mark_completed" data-id="${id}">Release Document (Mark as Complete)</button>
      `;
    }
    return proofBtn || '<span class="text-muted small">No actions</span>';
  }

  function firstNonEmpty(values) {
    for (const value of values) {
      if (value === null || value === undefined) continue;
      const s = String(value).trim();
      if (s !== '') return s;
    }
    return '';
  }

  function normalizeDisplayCasing(value) {
    const text = String(value ?? '').trim();
    if (!text) return text;
    if (!/[a-z]/i.test(text)) return text;
    if (/@|:\/\//.test(text)) return text;

    const lettersOnly = text.replace(/[^a-z]/gi, '');
    if (!lettersOnly) return text;
    if (lettersOnly !== lettersOnly.toLowerCase()) return text;

    return text.toLowerCase().replace(/\b([a-z])/g, (match) => match.toUpperCase());
  }

  function looksLikeOfficialId(value) {
    const s = String(value || '').trim();
    if (!s) return false;
    return /^[0-9]{6}[A-Z][0-9]{5}$/i.test(s);
  }

  function firstNonEmptyName(values, fallback = '') {
    for (const value of values) {
      if (value === null || value === undefined) continue;
      const s = String(value).trim();
      if (!s) continue;
      if (looksLikeOfficialId(s)) continue;
      return s;
    }
    return String(fallback || '').trim();
  }

  function formatPersonNameFnMiLn(first, middle, last) {
    const f = String(first || '').trim();
    const m = String(middle || '').trim();
    const l = String(last || '').trim();
    const mi = m ? `${m.charAt(0).toUpperCase()}.` : '';
    return [f, mi, l].filter(Boolean).join(' ').trim();
  }

  function fullNameFromRow(row) {
    const payload = row && row.payload && typeof row.payload === 'object' ? row.payload : {};
    const first = firstNonEmpty([payload.first_name, payload.firstname]);
    const middle = firstNonEmpty([payload.middle_name, payload.middlename]);
    const last = firstNonEmpty([payload.last_name, payload.lastname]);
    const ordered = formatPersonNameFnMiLn(first, middle, last);
    if (ordered.length) return ordered;
    const fallbackName = firstNonEmpty([row.full_name, row.resident_full_name, row.resident_name, '']);
    if (!fallbackName) return '-';

    const parts = fallbackName.split(/\s+/).filter(Boolean);
    if (parts.length >= 3) {
      const f = parts[0];
      const l = parts[parts.length - 1];
      const m = parts.slice(1, parts.length - 1).join(' ');
      return formatPersonNameFnMiLn(f, m, l);
    }
    return fallbackName;
  }

  function stripAreaFromAddress(address) {
    let value = String(address || '').trim();
    if (!value) return '';
    value = value.replace(/\s*,\s*Area\s+[A-Za-z0-9-]+\s*(?=,|$)/gi, '');
    value = value.replace(/(^|,\s*)Area\s+[A-Za-z0-9-]+\s*,\s*/gi, '$1');
    value = value.replace(/\s*,\s*San\s+Jose\s*,\s*Rodriguez\s*,\s*Rizal\s*$/i, '');
    value = value.replace(/\s*,\s*Barangay\s+San\s+Jose\s*,\s*Rodriguez(?:\s*\(Montalban\))?\s*,\s*Rizal\s*$/i, '');
    value = value.replace(/\s*,\s*Barangay\s+San\s+Jose\s*,\s*Montalban\s*,\s*Rizal\s*$/i, '');
    value = value.replace(/\s{2,}/g, ' ').trim();
    value = value.replace(/^[,\s]+|[,\s]+$/g, '');
    return value;
  }

  function joinAddressParts(parts) {
    return parts.map((part) => String(part || '').trim()).filter(Boolean).join(', ').replace(/\s+/g, ' ').trim();
  }

  function composeBarangayAddress(address, locality = 'BARANGAY SAN JOSE, RODRIGUEZ, RIZAL') {
    const suffix = String(locality || '').trim();
    const cleaned = stripAreaFromAddress(String(address || '').trim()).replace(/^[,\s]+|[,\s]+$/g, '');
    if (!cleaned || cleaned === '-') {
      return suffix || '-';
    }
    return suffix ? `${cleaned}, ${suffix}` : cleaned;
  }

  function buildCohabitantAddress(payload, applicantAddress = '') {
    const direct = firstNonEmpty([
      payload.cohabitant_full_address,
      payload.cohabitant_full_address_display
    ]);
    if (direct) return direct;
    const system = String(payload.cohabitant_address_system || '').trim().toLowerCase();
    if (system === 'lot_block') {
      return joinAddressParts([
        firstNonEmpty([payload.cohabitant_unit_number_lot]) ? `Unit ${payload.cohabitant_unit_number_lot}` : '',
        firstNonEmpty([payload.cohabitant_lot_number]) ? `Lot ${payload.cohabitant_lot_number}` : '',
        firstNonEmpty([payload.cohabitant_block_number]) ? `Blk ${payload.cohabitant_block_number}` : '',
        firstNonEmpty([payload.cohabitant_phase_number]) ? `Phase ${payload.cohabitant_phase_number}` : '',
        firstNonEmpty([payload.cohabitant_subdivision_lot, payload.cohabitant_subdivision]),
        firstNonEmpty([payload.cohabitant_barangay]),
        firstNonEmpty([payload.cohabitant_city]),
        firstNonEmpty([payload.cohabitant_province])
      ]);
    }
    if (system === 'house') {
      return joinAddressParts([
        firstNonEmpty([payload.cohabitant_unit_number]) ? `Unit ${payload.cohabitant_unit_number}` : '',
        [firstNonEmpty([payload.cohabitant_house_number]), firstNonEmpty([payload.cohabitant_street_name])].filter(Boolean).join(' ').trim(),
        firstNonEmpty([payload.cohabitant_subdivision]),
        firstNonEmpty([payload.cohabitant_barangay]),
        firstNonEmpty([payload.cohabitant_city]),
        firstNonEmpty([payload.cohabitant_province])
      ]);
    }
    return applicantAddress;
  }

  function buildCohabitationAddress(payload, applicantAddress = '') {
    const direct = firstNonEmpty([
      payload.cohabitation_full_address,
      payload.cohabitation_full_address_display
    ]);
    if (direct) return direct;
    const system = String(payload.cohabitation_address_system || '').trim().toLowerCase();
    if (system === 'lot_block') {
      return joinAddressParts([
        firstNonEmpty([payload.cohabitation_unit_number_lot]) ? `Unit ${payload.cohabitation_unit_number_lot}` : '',
        firstNonEmpty([payload.cohabitation_lot_number]) ? `Lot ${payload.cohabitation_lot_number}` : '',
        firstNonEmpty([payload.cohabitation_block_number]) ? `Blk ${payload.cohabitation_block_number}` : '',
        firstNonEmpty([payload.cohabitation_phase_number]) ? `Phase ${payload.cohabitation_phase_number}` : '',
        firstNonEmpty([payload.cohabitation_subdivision_lot, payload.cohabitation_subdivision]),
        firstNonEmpty([payload.cohabitation_barangay]),
        firstNonEmpty([payload.cohabitation_municipality, payload.cohabitation_city]),
        firstNonEmpty([payload.cohabitation_province])
      ]);
    }
    if (system === 'house') {
      return joinAddressParts([
        firstNonEmpty([payload.cohabitation_unit_number]) ? `Unit ${payload.cohabitation_unit_number}` : '',
        [firstNonEmpty([payload.cohabitation_house_number]), firstNonEmpty([payload.cohabitation_street_name])].filter(Boolean).join(' ').trim(),
        firstNonEmpty([payload.cohabitation_subdivision]),
        firstNonEmpty([payload.cohabitation_barangay]),
        firstNonEmpty([payload.cohabitation_municipality, payload.cohabitation_city]),
        firstNonEmpty([payload.cohabitation_province])
      ]);
    }
    return applicantAddress;
  }

  function generalClearancePurposeFromDocType(docType) {
    const token = String(docType || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
    if (token.includes('electricalpermit')) return 'ELECTRICAL PERMIT';
    if (token.includes('waterpermit')) return 'WATER PERMIT';
    if (token.includes('residentialpermit')) return 'RESIDENTIAL PERMIT';
    if (token.includes('residentialbuildingpermit')) return 'RESIDENTIAL BUILDING PERMIT';
    if (token.includes('commercialpermit')) return 'COMMERCIAL PERMIT';
    if (token.includes('commercialbuildingpermit')) return 'COMMERCIAL BUILDING PERMIT';
    return '';
  }

  function buildGeneralPermitLocation(payload, applicantAddress = '') {
    const direct = firstNonEmpty([
      payload.location,
      payload.lot_full_address,
      payload.project_location
    ]);
    if (direct) return direct;

    const sameAddress = String(payload.lot_same_address || '').trim().toLowerCase();
    const applicant = firstNonEmpty([
      payload.applicant_full_address,
      payload.full_address,
      payload.full_address_display,
      payload.address,
      applicantAddress
    ]);
    if (['1', 'true', 'yes', 'on'].includes(sameAddress)) {
      return applicant;
    }

    const system = String(payload.lot_address_system || '').trim().toLowerCase();
    if (system === 'lot_block') {
      return joinAddressParts([
        firstNonEmpty([payload.lot_number]) ? `Lot ${payload.lot_number}` : '',
        firstNonEmpty([payload.block_number]) ? `Blk ${payload.block_number}` : '',
        firstNonEmpty([payload.lot_phase_number]) ? `Phase ${payload.lot_phase_number}` : '',
        firstNonEmpty([payload.lot_subdivision]),
        firstNonEmpty([payload.lot_barangay]),
        firstNonEmpty([payload.lot_city]),
        firstNonEmpty([payload.lot_province])
      ]);
    }
    if (system === 'house') {
      return joinAddressParts([
        firstNonEmpty([payload.lot_unit_number]) ? `Unit ${payload.lot_unit_number}` : '',
        [firstNonEmpty([payload.lot_street_number]), firstNonEmpty([payload.lot_street_name])].filter(Boolean).join(' ').trim(),
        firstNonEmpty([payload.lot_subdivision]),
        firstNonEmpty([payload.lot_barangay]),
        firstNonEmpty([payload.lot_city]),
        firstNonEmpty([payload.lot_province])
      ]);
    }

    return applicant;
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

  function documentTypeBadgeBlue(row) {
    const doc = normalizeDocumentTypeDisplay(firstNonEmpty([row.document_type, '-']));
    return `<span style="display:inline-block;padding:4px 10px;border-radius:8px;background:#dbeafe;color:#1e40af;font-weight:700;">${esc(doc)}</span>`;
  }

  function normalizeDocumentTypeDisplay(value) {
    const raw = String(value || '').trim();
    if (!raw) return '-';
    const key = raw.toLowerCase().replace(/[^a-z0-9]+/g, '');
    if (key.includes('electricalpermit')) {
      return 'Barangay Clearance for Electrical Permit';
    }
    if (key.includes('waterpermit')) {
      return 'Barangay Clearance for Water Permit';
    }
    if (key.includes('residentialpermit')) {
      return 'Barangay Clearance for Residential Permit';
    }
    if (key.includes('residentialbuildingpermit')) {
      return 'Barangay Clearance for Residential Building Permit';
    }
    if (key.includes('commercialpermit')) {
      return 'Barangay Clearance for Commercial Permit';
    }
    if (key.includes('commercialbuildingpermit')) {
      return 'Barangay Clearance for Commercial Building Permit';
    }
    if (key.includes('businesspermit') || key.includes('businessclearance') || key.includes('clearanceforbusinesspermit')) {
      return 'Barangay Clearance for Business Permit';
    }
    if (key === 'indigency' || key === 'certificateofindigency') {
      return 'Certificate of Indigency';
    }
    if (key === 'goodmoral' || key === 'certificateofgoodmoral') {
      return 'Certificate of Good Moral';
    }
    if (key === 'residency' || key === 'certificateofresidency' || key === 'certificateofresidence') {
      return 'Certificate of Residency';
    }
    if (key === 'cohabitation' || key === 'certificateofcohabitation') {
      return 'Certificate of Cohabitation';
    }
    if (key === 'identity' || key === 'certificateofidentity') {
      return 'Certificate of Identity';
    }
    if (key === 'firsttimejobseeker' || key === 'firsttimejobseekers' || key === 'firsttimejobseekercertificate') {
      return 'First Time Job Seeker Certificate';
    }
    if (key.includes('barangayclearance') || key.includes('barangaycertification') || key === 'clearance') {
      return 'Barangay Certification';
    }
    return raw;
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

  function formField(label, value, raw = false, wide = false) {
    const text = String(value ?? '').trim();
    const rendered = raw ? (text || '-') : esc(text || '-');
    return `
      <div class="tracker-form-field">
        <p class="tracker-form-label">${esc(label)}</p>
        <div class="tracker-form-value">${rendered}</div>
      </div>
    `;
  }

  function formSection(title, content, actionHtml = '') {
    const hasAction = String(actionHtml || '').trim() !== '';
    return `
      <section class="tracker-form-section">
        ${hasAction
          ? `<div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
               <h6 class="tracker-form-section-title mb-0">${esc(title)}</h6>
               ${actionHtml}
             </div>`
          : `<h6 class="tracker-form-section-title">${esc(title)}</h6>`}
        ${content}
      </section>
    `;
  }

  function isEmptyFieldValue(value) {
    const text = String(value ?? '').trim();
    if (!text) return true;
    return ['-', '—', 'n/a', 'na', 'null', 'undefined'].includes(text.toLowerCase());
  }

  function visibleFields(fields) {
    return (Array.isArray(fields) ? fields : []).filter((f) => f && !isEmptyFieldValue(f.value));
  }

  function gridClassByCount(count, maxCols = 4) {
    const n = Math.max(1, Math.min(maxCols, Number(count) || 1));
    if (n >= 4) return 'cols-4';
    if (n === 3) return 'cols-3';
    if (n === 2) return '';
    return 'cols-1';
  }

  function renderFieldGrid(fields, maxCols = 4) {
    const clean = visibleFields(fields);
    if (!clean.length) return '';
    const cls = gridClassByCount(clean.length, maxCols);
    return `<div class="tracker-form-grid ${cls}">${clean.map((f) => formField(f.label, f.value, !!f.raw, !!f.wide)).join('')}</div>`;
  }

  function renderRequestDetailsGrid(fields) {
    const clean = visibleFields(fields);
    if (!clean.length) return '';

    const normalizeLabel = (label) => String(label || '').toLowerCase().replace(/\s+/g, ' ').trim();
    const takeFirst = (arr, predicate) => {
      const idx = arr.findIndex((f) => f && predicate(normalizeLabel(f.label)));
      if (idx === -1) return null;
      return arr.splice(idx, 1)[0];
    };

    const remaining = clean.slice();

    const purpose = takeFirst(remaining, (l) => l === 'purpose' || l.includes('purpose'));
    const lastName = takeFirst(remaining, (l) => l.includes('last name'));
    const firstName = takeFirst(remaining, (l) => l.includes('first name'));
    const middleName = takeFirst(remaining, (l) => l.includes('middle name'));

    const contactNumber = takeFirst(
      remaining,
      (l) => l.includes('contact number') || l.includes('mobile number') || l.includes('phone number')
    );
    const fullAddress = takeFirst(remaining, (l) => l === 'address' || l.includes('full address'));

    const ownershipType = takeFirst(remaining, (l) => l.includes('ownership type'));
    const proofAddressType = takeFirst(remaining, (l) => l.includes('proof') && l.includes('address') && l.includes('type'));
    const proofAddressNumber = takeFirst(
      remaining,
      (l) => l.includes('proof') && l.includes('address') && (l.includes('number') || l.includes('no') || l.includes('#'))
    );

    const blocks = [];

    if (purpose) {
      blocks.push(renderFieldGrid([{ ...purpose, wide: true }], 1));
    }

    const nameRow = [lastName, firstName, middleName].filter(Boolean);
    if (nameRow.length) {
      blocks.push(renderFieldGrid(nameRow, 3));
    }

    const contactRow = [contactNumber, fullAddress].filter(Boolean);
    if (contactRow.length) {
      blocks.push(renderFieldGrid(contactRow, 2));
    }

    const ownershipRow = [ownershipType, proofAddressType, proofAddressNumber].filter(Boolean);
    if (ownershipRow.length) {
      blocks.push(renderFieldGrid(ownershipRow, 3));
    }

    if (remaining.length) {
      blocks.push(renderFieldGrid(remaining.map((f) => ({ ...f, wide: true })), 1));
    }

    return `<div class="d-grid gap-3">${blocks.filter(Boolean).join('')}</div>`;
  }

  function looksLikeFilePath(key, value) {
    const k = String(key || '').toLowerCase();
    const v = String(value || '').trim();
    if (!v) return false;
    const vLower = v.toLowerCase();
    if (vLower.includes('/unifiedfileattachment/')) return true;
    if (/\.(pdf|png|jpe?g|webp|gif|bmp)$/i.test(vLower)) return true;
    if (
      k.includes('path') ||
      k.includes('file') ||
      k.includes('attachment') ||
      k.includes('image') ||
      k.includes('photo') ||
      k.includes('proof')
    ) {
      return v.includes('/') || v.includes('\\');
    }
    return false;
  }

  function extractSubmittedDocuments(row, payload) {
    const docs = [];
    const seen = new Set();
    const isRelationshipJailVisit = String(payload?.cohabitation_variant || '').trim() === 'relationship_jail_visit'
      || String(payload?.cohabitation_variant || '').trim() === 'conjugal_visit';
    const customLabels = {
      cohabitant_id_front_path: 'Partner Valid ID - Front',
      cohabitant_id_back_path: 'Partner Valid ID - Back',
      detention_proof_file_path: 'Proof of Detention',
      detention_proof_file_paths: 'Proof of Detention Attachment',
      relationship_proof_file_path: 'Proof of Relationship',
      relationship_proof_file_paths: 'Proof of Relationship Attachment',
      valid_id_file_path: 'Valid ID',
      or_vehicle_file_path: 'O.R. of the Vehicle',
      cr_vehicle_file_path: 'C.R. of the Vehicle',
      toda_poda_cert_file_path: 'TODA / PODA Certification',
      authorization_vehicle_file_path: 'Authorization of Vehicle',
      deed_of_sale_file_path: 'Notarized Deed of Sale',
      last_year_clearance_file_path: 'Barangay Clearance from Previous Year',
      business_reg_file_path: 'Business Registration',
      proof_address_file_path: 'Proof of Business Address',
      business_photo_file_path: 'Establishment Photo',
      renewal_valid_id_file_path: 'Renewal Valid ID',
      renewal_business_reg_file_path: 'Updated Business Registration',
      renewal_proof_address_file_path: 'Updated Proof of Business Address'
    };

    const addDoc = (label, rawPath) => {
      const pathText = String(rawPath || '').trim();
      if (!pathText) return;
      const match = pathText.match(/\/UnifiedFileAttachment\/[^\s"'<>]+/i);
      const normalized = match ? match[0] : (pathText.startsWith('/UnifiedFileAttachment/') ? pathText : '');
      if (!normalized) return;
      const url = `${appBase}${normalized}`;
      if (seen.has(url)) return;
      seen.add(url);
      const fileName = normalized.split('/').pop() || pathText.replace(/\\/g, '/').split('/').pop() || '';
      docs.push({ label: String(label || 'Document'), url, path: normalized, name: fileName });
    };

    addDoc('Payment Proof', row?.payment_proof_path);

    if (payload && typeof payload === 'object') {
      Object.keys(payload).forEach((key) => {
        const value = payload[key];
        if (Array.isArray(value)) {
          value.forEach((entry, idx) => {
            const baseLabel = customLabels[key] || friendlyLabel(key);
            addDoc(`${baseLabel} ${idx + 1}`, entry);
          });
          return;
        }
        if (typeof value === 'string') {
          addDoc(customLabels[key] || friendlyLabel(key), value);
        }
      });
    }

    return docs;
  }

  function previewDateText(value) {
    const raw = String(value || '').trim();
    if (!raw) return dr_now_text();
    const d = new Date(raw);
    if (Number.isNaN(d.getTime())) return raw;
    return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
  }

  function parseFlexibleDate(value) {
    const raw = String(value || '').trim();
    if (!raw) return null;

    const iso = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (iso) {
      const y = Number.parseInt(iso[1], 10);
      const m = Number.parseInt(iso[2], 10);
      const d = Number.parseInt(iso[3], 10);
      const out = new Date(y, m - 1, d);
      return Number.isNaN(out.getTime()) ? null : out;
    }

    const normalized = raw.replace(/\s+/g, ' ').trim();
    const parsed = new Date(normalized);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
  }

  function previewBornOnDate(value) {
    const raw = String(value || '').trim();
    if (!raw) return '';
    const d = parseFlexibleDate(raw);
    if (!d) return raw;
    return d.toLocaleDateString('en-US', { month: 'long', day: '2-digit', year: 'numeric' });
  }

  function previewMonthYear(value) {
    const raw = String(value || '').trim();
    if (!raw) return '';
    const ym = raw.match(/^(\d{4})-(\d{2})$/);
    if (ym) {
      const dt = new Date(Number.parseInt(ym[1], 10), Number.parseInt(ym[2], 10) - 1, 1);
      if (!Number.isNaN(dt.getTime())) {
        return dt.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
      }
    }
    const d = parseFlexibleDate(raw);
    if (!d) return raw;
    return d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
  }

  function parseDurationParts(value) {
    const raw = String(value || '').trim();
    if (!raw) return null;
    const yearsMatch = raw.match(/(\d+)\s*year/i);
    const monthsMatch = raw.match(/(\d+)\s*month/i);
    if (!yearsMatch && !monthsMatch) return null;
    return {
      years: yearsMatch ? Math.max(0, Number.parseInt(yearsMatch[1], 10) || 0) : 0,
      months: monthsMatch ? Math.max(0, Number.parseInt(monthsMatch[1], 10) || 0) : 0
    };
  }

  function formatDurationParts(years, months) {
    const y = Math.max(0, Number.parseInt(String(years || '0'), 10) || 0);
    const m = Math.max(0, Number.parseInt(String(months || '0'), 10) || 0);
    const parts = [];
    if (y > 0) parts.push(`${y} ${y === 1 ? 'year' : 'years'}`);
    if (m > 0 || parts.length === 0) parts.push(`${m} ${m === 1 ? 'month' : 'months'}`);
    return parts.join(' and ');
  }

  function buildResidencySinceText(startRaw, yearsRaw, monthsRaw, fallbackDurationRaw = '') {
    const explicitYears = String(yearsRaw || '').trim();
    const explicitMonths = String(monthsRaw || '').trim();
    let years = explicitYears !== '' ? Math.max(0, Number.parseInt(explicitYears, 10) || 0) : null;
    let months = explicitMonths !== '' ? Math.max(0, Number.parseInt(explicitMonths, 10) || 0) : null;

    if (years === null || months === null) {
      const parsed = parseDurationParts(fallbackDurationRaw);
      if (parsed) {
        if (years === null) years = parsed.years;
        if (months === null) months = parsed.months;
      }
    }

    let startDisplay = previewMonthYear(startRaw);
    const durationDisplay = formatDurationParts(years ?? 0, months ?? 0);
    if (!startDisplay && years !== null && months !== null) {
      const now = new Date();
      const inferred = new Date(now.getFullYear(), now.getMonth(), 1);
      inferred.setMonth(inferred.getMonth() - months);
      inferred.setFullYear(inferred.getFullYear() - years);
      if (!Number.isNaN(inferred.getTime())) {
        startDisplay = inferred.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
      }
    }
    if (startDisplay) {
      return `${startDisplay} (${durationDisplay})`;
    }
    return durationDisplay;
  }

  function applicantHonorific(sexValue) {
    const sex = String(sexValue || '').trim().toLowerCase();
    if (sex.startsWith('m')) return 'MR.';
    if (sex.startsWith('f')) return 'MS.';
    return 'MR./MS.';
  }

  function dr_now_text() {
    return new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
  }

  function previewIndigencyIssuedText(value) {
    const raw = stripTemplateTokens(value);
    const normalized = String(raw || '').trim();
    const formattedTokenMatch = normalized.match(/^(\d{1,2})(st|nd|rd|th)\s+day\s+of\s+([A-Za-z]+)\s+(\d{4})$/i);
    if (formattedTokenMatch) {
      return `${formattedTokenMatch[1]}${formattedTokenMatch[2].toLowerCase()} day of ${formattedTokenMatch[3].toUpperCase()} ${formattedTokenMatch[4]}`;
    }
    const parsed = normalized ? (parseFlexibleDate(normalized) || new Date(normalized)) : new Date();
    const d = parsed instanceof Date ? parsed : new Date();
    if (Number.isNaN(d.getTime())) {
      const fallback = new Date();
      return previewIndigencyIssuedText(fallback.toISOString());
    }
    const day = d.getDate();
    const month = d.toLocaleDateString('en-US', { month: 'long' }).toUpperCase();
    const year = d.getFullYear();
    const v = day % 100;
    const suffix = (v >= 11 && v <= 13) ? 'th' : ({ 1: 'st', 2: 'nd', 3: 'rd' }[day % 10] || 'th');
    return `${day}${suffix} day of ${month} ${year}`;
  }

  function deriveAgeFromDate(value) {
    const raw = String(value || '').trim();
    if (!raw) return '';
    const birth = parseFlexibleDate(raw);
    if (!birth) return '';
    const now = new Date();
    let years = now.getFullYear() - birth.getFullYear();
    const monthDiff = now.getMonth() - birth.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && now.getDate() < birth.getDate())) {
      years -= 1;
    }
    return years >= 0 ? String(years) : '';
  }

  function stripTemplateTokens(value) {
    const raw = String(value ?? '').trim();
    if (!raw) return '';
    const stripped = raw
      .replace(/\$\{[A-Z0-9_]+\}/gi, ' ')
      .replace(/\b(PARTNER_AGE|OR_NUMBER)\b/gi, ' ')
      .replace(/\s+/g, ' ')
      .trim();
    return stripped;
  }

  function stripTrailingParenthetical(value) {
    return String(value || '').replace(/\s*\([^()]*\)\s*$/g, '').trim();
  }

  function parseAgeText(value) {
    const cleaned = stripTemplateTokens(value);
    if (!cleaned) return '';
    const match = cleaned.match(/(\d{1,3})/);
    if (!match) return '';
    const parsed = Number.parseInt(match[1], 10);
    return Number.isFinite(parsed) && parsed >= 0 ? String(parsed) : '';
  }

  function previewEditable(key, value, fallback = 'Type here', extraClass = '') {
    const text = String(value || '').trim().toUpperCase() || String(fallback || '').toUpperCase();
    const cls = ['doc-editable', extraClass].filter(Boolean).join(' ');
    return `<span class="${cls}" contenteditable="true" data-edit-key="${esc(key)}">${esc(text)}</span>`;
  }

  function renderPreviewMetaRows(rows) {
    if (!Array.isArray(rows) || rows.length === 0) return '';
    return `
      <div class="doc-preview-goodmoral-meta">
        ${rows.map((row) => `
          <div class="doc-preview-meta-row">
            <div class="doc-preview-meta-label"><strong>${esc(row.label || '')}</strong></div>
            <div class="doc-preview-meta-value">${/^[\s_]+$/.test(String(row.value || '').trim() || '_____')
              ? '<span class="doc-preview-meta-line"></span>'
              : esc(String(row.value || '').trim() || '_____')}</div>
          </div>
        `).join('')}
      </div>
    `;
  }

  function upperText(value, fallback = '-') {
    const text = String(value ?? '').trim();
    const resolved = text || String(fallback ?? '').trim();
    return resolved ? resolved.toUpperCase() : '';
  }

  function normalizePreviewDocKey(docType) {
    const text = String(docType || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
    if (text.includes('cohabitation')) return 'cohabitation';
    if (text.includes('indigency')) return 'indigency';
    if (text.includes('firsttime') || text.includes('jobseeker')) return 'firsttimejobseeker';
    if (text.includes('identity')) return 'identity';
    if (text.includes('residency') || text.includes('residence')) return 'residency';
    if (text.includes('goodmoral')) return 'goodmoral';
    if (text.includes('electricalpermit') || text.includes('waterpermit') || text.includes('residentialpermit') || text.includes('residentialbuildingpermit') || text.includes('commercialpermit') || text.includes('commercialbuildingpermit')) return 'generalpermitclearance';
    if (text.includes('tricycle')) return 'tricycleclearance';
    if (text.includes('businesspermit') || text.includes('businessclearance') || text.includes('clearanceforbusinesspermit')) return 'businessclearance';
    if (text.includes('barangayclearance') || text.includes('barangaycertification') || text === 'clearance') return 'generic';
    return 'generic';
  }

  function normalizeBusinessApprovalType(value) {
    const token = String(value || '')
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '');
    if (!token) return '';
    if (token === 'not_banned' || token.includes('not_among_those_business')) return 'not_banned';
    if (token === 'no_objection' || token.includes('interposes_no_objection')) return 'no_objection';
    if (token === 'temporary_clearance' || token.includes('temporary_barangay_clearance')) return 'temporary_clearance';
    return '';
  }

  function isFirstTimeJobSeekerRow(row) {
    return normalizePreviewDocKey(row?.document_type || '') === 'firsttimejobseeker';
  }

  function additionalDetailRows(entries) {
    const rows = Array.isArray(entries) ? entries.filter((it) => it && it.label && it.value) : [];
    if (!rows.length) return '';
    const html = rows.map((it) => `
      <p><strong>${esc(it.label)}:</strong> ${esc(it.value)}</p>
    `).join('');
    return `
      <p><strong>Additional Submitted Details:</strong></p>
      ${html}
    `;
  }

  function buildPreviewState(row, payload = {}, residentProfile = {}, personalMap = null) {
    const getPersonal = (label, fallback = '-') => {
      if (personalMap instanceof Map) {
        const value = String(personalMap.get(label) || '').trim();
        if (value) return value;
      }
      return fallback;
    };
    const businessName = firstNonEmpty([
      payload.business_name,
      payload.businessName,
      payload.business_trade_name,
      payload.trade_name,
      payload.establishment_name,
      payload.business_establishment
    ]);
    const businessAddressFromFields = [
      [
        String(payload.business_house_number || '').trim(),
        String(payload.business_street_name || '').trim()
      ].filter(Boolean).join(' '),
      String(payload.business_subdivision || '').trim(),
      String(payload.business_barangay || '').trim(),
      String(payload.business_city || '').trim(),
      String(payload.business_province || '').trim()
    ].filter(Boolean).join(', ');
    const businessAddress = firstNonEmpty([
      payload.business_full_address,
      payload.business_address,
      payload.location,
      businessAddressFromFields
    ]);
    const operatorAddressRaw = firstNonEmpty([
      payload.owner_full_address,
      payload.full_address,
      payload.full_address_display,
      payload.address,
      residentProfile.full_address
    ]);
    const previewAmount = (() => {
      const raw = String(firstNonEmpty([
        row.amount,
        row.transaction_amount,
        payload.amount,
        payload.transaction_amount
      ]) || '').replace(/,/g, '').trim();
      if (!raw) return '';
      const parsed = Number.parseFloat(raw);
      if (!Number.isFinite(parsed)) return '';
      return parsed.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    })();
    const plateNumber = firstNonEmpty([
      payload._preview_plate_number,
      payload.plate_number,
      payload.business_plate_number,
      payload.vehicle_plate_number
    ]);
    const businessApprovalType = normalizeBusinessApprovalType(firstNonEmpty([
      payload._preview_business_approval_type,
      payload.business_approval_type,
      payload.businessApprovalType
    ]));
    const franchisee = firstNonEmpty([
      payload._preview_franchisee,
      payload.franchisee,
      payload.vehicle_franchise
    ]);
    const vehicleType = firstNonEmpty([
      payload._preview_vehicle_type,
      payload.vehicle_make,
      payload.type_of_vehicle
    ]);
    const registrationNumber = firstNonEmpty([
      payload._preview_registration_number,
      payload.cr_number,
      payload.registration_number,
      payload.or_number
    ]);
    const bodyNumber = firstNonEmpty([
      payload._preview_body_number,
      payload.body_number
    ]);
    const fatherName = [
      firstNonEmpty([payload.father_first_name]),
      firstNonEmpty([payload.father_middle_name]),
      firstNonEmpty([payload.father_last_name]),
      firstNonEmpty([payload.father_suffix])
    ].filter(Boolean).join(' ').replace(/\s+/g, ' ').trim();
    const motherName = [
      firstNonEmpty([payload.mother_first_name]),
      firstNonEmpty([payload.mother_middle_name]),
      firstNonEmpty([payload.mother_last_name]),
      firstNonEmpty([payload.mother_suffix])
    ].filter(Boolean).join(' ').replace(/\s+/g, ' ').trim();
    const cohabitantName = [
      firstNonEmpty([payload.cohabitant_first]),
      firstNonEmpty([payload.cohabitant_middle]),
      firstNonEmpty([payload.cohabitant_last]),
      firstNonEmpty([payload.cohabitant_suffix])
    ].filter(Boolean).join(' ').replace(/\s+/g, ' ').trim();
    const durationValue = firstNonEmpty([payload.cohabitation_duration_value]);
    const durationUnit = firstNonEmpty([payload.cohabitation_duration_unit]);
    const cohabitationDurationRaw = firstNonEmpty([
      payload.cohabitation_duration,
      [durationValue, durationUnit].filter(Boolean).join(' ').trim()
    ]);
    const cohabitationDuration = stripTrailingParenthetical(cohabitationDurationRaw);
    const cohabitationStartRaw = firstNonEmpty([payload.cohabitation_start_date]);
    const cohabitationStartDateDisplay = (() => {
      const raw = String(cohabitationStartRaw || '').trim();
      if (!raw) return '';
      const base = previewMonthYear(raw) || raw;
      const dur = stripTrailingParenthetical(cohabitationDuration);
      return dur ? `${base} (${dur.toLowerCase()})` : base;
    })();
    const sexValue = firstNonEmpty([payload.sex, payload.gender, residentProfile.sex]);
    const residencySinceText = buildResidencySinceText(
      firstNonEmpty([payload.barangay_residency, residentProfile.barangay_residency]),
      firstNonEmpty([payload.years_of_residency]),
      firstNonEmpty([payload.months_of_residency]),
      firstNonEmpty([payload.residency_duration, residentProfile.residency_duration])
    );
    const applicantBirthdateRaw = stripTemplateTokens(firstNonEmpty([
      payload.birthdate,
      payload.date_of_birth,
      payload.child_dob,
      payload.birthDate,
      residentProfile.birthdate
    ]));
    const cohabitantBirthdateRaw = stripTemplateTokens(firstNonEmpty([
      payload.cohabitant_birthdate,
      payload.cohabitant_dob,
      payload.partner_birthdate,
      payload.partner_dob,
      payload.cohabitantBirthdate
    ]));
    const applicantAgeRaw = firstNonEmpty([parseAgeText(payload.age), deriveAgeFromDate(applicantBirthdateRaw)]);
    const cohabitantAgeRaw = firstNonEmpty([
      parseAgeText(payload.cohabitant_age),
      parseAgeText(payload.partner_age),
      deriveAgeFromDate(cohabitantBirthdateRaw)
    ]);
    const childLines = [];
    for (let i = 1; i <= 5; i += 1) {
      const childName = String(payload[`cohabitation_child_${i}_name`] || '').trim();
      const childAge = String(payload[`cohabitation_child_${i}_age`] || '').trim();
      if (!childName && !childAge) continue;
      childLines.push(`${childName}${childAge ? `, ${childAge} y/o` : ''}`.trim());
    }
    const requestedDocType = firstNonEmpty([payload.document_type, row.document_type, 'Certificate']);
    const generalPermitPurpose = generalClearancePurposeFromDocType(requestedDocType);
    const generalPermitLocation = buildGeneralPermitLocation(
      payload,
      firstNonEmpty([
        payload.applicant_full_address,
        payload.full_address,
        payload.full_address_display,
        residentProfile.full_address
      ])
    );
    const generalPermitRemarks = firstNonEmpty([payload.remarks, payload.ownership_type]);
    const knownPayloadKeys = new Set([
      'action', 'csrf_token', 'redirect', 'document_type',
      'last_name', 'lastname', 'first_name', 'firstname', 'middle_name', 'middlename', 'suffix', 'suffix_name',
      'contact_number', 'phone_number', 'full_address', 'full_address_display', 'address', 'complete_address',
      'birthdate', 'date_of_birth', 'child_dob', 'age', 'sex', 'gender', 'child_sex',
      'civil_status', 'religion', 'occupation',
      'purpose', 'request_purpose', 'request_officer',
      'business_name', 'businessName', 'business_trade_name', 'trade_name', 'establishment_name', 'business_establishment',
      '_preview_business_approval_type', 'business_approval_type', 'businessApprovalType',
      '_preview_plate_number', 'plate_number', 'business_plate_number', 'vehicle_plate_number',
      '_preview_franchisee', 'franchisee', 'vehicle_franchise', '_preview_vehicle_type', 'vehicle_make', 'type_of_vehicle',
      '_preview_registration_number', 'registration_number', 'cr_number', 'or_number', '_preview_body_number', 'body_number',
      'vehicle_named_to_owner', 'applicant_last_name', 'applicant_first_name', 'applicant_middle_name', 'applicant_suffix',
      'applicant_contact_number', 'applicant_full_address',
      'lot_same_address', 'lot_address_system', 'lot_unit_number', 'lot_street_number', 'lot_street_name',
      'lot_subdivision', 'lot_number', 'block_number', 'lot_phase_number', 'lot_barangay', 'lot_city', 'lot_province',
      'project_location', 'ownership_type',
      'years_of_residency', 'months_of_residency',
      'child_birthplace', 'child_nationality', 'birthplace', 'place_of_birth', 'location', 'remarks',
      'father_first_name', 'father_middle_name', 'father_last_name', 'father_suffix',
      'mother_first_name', 'mother_middle_name', 'mother_last_name', 'mother_suffix',
      'cohabitant_first', 'cohabitant_middle', 'cohabitant_last', 'cohabitant_suffix',
      'cohabitant_dob', 'cohabitant_birthdate', 'cohabitant_age', 'partner_age',
      'cohabitant_relationship', 'cohabitation_duration_value', 'cohabitation_duration_unit', 'cohabitation_start_date',
      'educational_attainment', 'jobstart_beneficiary'
    ]);
    const additionalDetails = [];
    Object.keys(payload).forEach((key) => {
      const k = String(key || '');
      if (!k || knownPayloadKeys.has(k)) return;
      const value = String(payload[key] ?? '').trim();
      if (!value) return;
      additionalDetails.push({ label: friendlyLabel(k), value });
    });

    const inferredBusinessClearance = !!businessName
      || /business\s+permit/i.test(firstNonEmpty([row.purpose, payload.request_purpose, payload.purpose]));

    return {
      docType: inferredBusinessClearance
        ? 'Barangay Clearance for Business Permit'
        : normalizeDocumentTypeDisplay(requestedDocType),
      fullName: upperText(
        formatPersonNameFnMiLn(
          getPersonal('First Name', ''),
          getPersonal('Middle Name', ''),
          getPersonal('Last Name', '')
        ),
        fullNameFromRow(row)
      ),
      fullAddress: upperText(stripAreaFromAddress(getPersonal('Full Address', '') || residentProfile.full_address || payload.full_address || payload.full_address_display || payload.address || payload.complete_address || '-'), '-'),
      purpose: upperText(generalPermitPurpose || firstNonEmpty([row.purpose, payload.purpose, payload.request_purpose, '-']), '-'),
      businessName: upperText(businessName || '', ''),
      businessAddress: upperText(businessAddress || '', ''),
      businessApprovalType,
      franchisee: upperText(franchisee || '', ''),
      vehicleType: upperText(vehicleType || '', ''),
      registrationNumber: upperText(registrationNumber || '', ''),
      plateNumber: upperText(plateNumber || '', ''),
      bodyNumber: upperText(bodyNumber || '', ''),
      operatorName: upperText(firstNonEmpty([
        payload.operator_name,
        payload.business_operator_name,
        fullNameFromRow(row)
      ]), ''),
      operatorAddress: upperText(composeBarangayAddress(operatorAddressRaw || residentProfile.full_address || payload.full_address || ''), ''),
      amount: upperText(previewAmount, ''),
      issuedDate: firstNonEmpty([
        row.release_timestamp,
        row.completed_at,
        row.ready_at,
        row.submitted_at,
        dr_now_text()
      ]),
      approvedByName: upperText(firstNonEmptyName([
        row.reviewed_by,
        row.personnel_name,
        row.released_by,
        row.finance_user_name
      ]), 'HON. GLENN S. EVANGELISTA'),
      requestOfficer: upperText(firstNonEmpty([
        payload.request_officer,
        [
          payload.government_official,
          payload.government_position || payload.government_position_detail,
          payload.government_office || payload.government_position_group
        ].filter(Boolean).join(' - ')
      ]), ''),
      requestOfficerLine1: upperText(firstNonEmpty([
        payload.request_officer_line1,
        payload.government_official,
        payload.government_official_other
      ]), ''),
      requestOfficerLine2: upperText(firstNonEmpty([
        payload.request_officer_line2,
        payload.government_position,
        payload.government_position_detail,
        payload.institution_position
      ]), ''),
      requestOfficerLine3: upperText(firstNonEmpty([
        payload.request_officer_line3,
        payload.government_office,
        payload.government_position_group,
        payload.institution_name
      ]), ''),
      certificateNumber: upperText(firstNonEmpty([row.certificate_number, payload.certificate_number]), ''),
      requestFor: upperText(firstNonEmpty([payload.request_purpose]), ''),
      orNumber: upperText(stripTemplateTokens(firstNonEmpty([row.or_number])), ''),
      yearsResidency: upperText(firstNonEmpty([payload.years_of_residency]), ''),
      monthsResidency: upperText(firstNonEmpty([payload.months_of_residency]), ''),
      childBirthplace: upperText(firstNonEmpty([payload.child_birthplace, payload.birthplace, payload.place_of_birth]), ''),
      childBirthdate: upperText(previewBornOnDate(firstNonEmpty([payload.child_dob, payload.birthdate, payload.date_of_birth, residentProfile.birthdate])), ''),
      childNationality: upperText(firstNonEmpty([payload.child_nationality]), ''),
      birthdate: upperText(previewBornOnDate(applicantBirthdateRaw), ''),
      birthplace: upperText(firstNonEmpty([payload.birthplace, payload.place_of_birth, payload.child_birthplace]), ''),
      location: upperText(generalPermitLocation || firstNonEmpty([payload.location, payload.complete_address, payload.address, payload.full_address, residentProfile.full_address]), ''),
      applicantResidenceAddress: upperText(firstNonEmpty([payload.full_address, payload.full_address_display, payload.address, residentProfile.full_address]), ''),
      cohabitantResidenceAddress: upperText(buildCohabitantAddress(payload, firstNonEmpty([payload.full_address, payload.full_address_display, residentProfile.full_address])), ''),
      cohabitationResidenceAddress: upperText(buildCohabitationAddress(payload, firstNonEmpty([payload.full_address, payload.full_address_display, residentProfile.full_address])), ''),
      remarks: upperText(generalPermitRemarks || firstNonEmpty([payload.remarks, payload.remark, row.status_remarks, row.status_reason]), ''),
      fatherName: upperText(fatherName, ''),
      motherName: upperText(motherName, ''),
      cohabitantName: upperText(cohabitantName, ''),
      age: upperText(applicantAgeRaw, ''),
      cohabitantAge: upperText(cohabitantAgeRaw, ''),
      cohabitantBirthdate: upperText(previewBornOnDate(cohabitantBirthdateRaw), ''),
      cohabitantRelationship: upperText(firstNonEmpty([payload.cohabitant_relationship]), ''),
      cohabitationVariant: String(payload.cohabitation_variant || '').trim().toLowerCase(),
      detentionFacility: upperText(firstNonEmpty([
        payload._preview_detention_facility,
        payload.detention_facility_other,
        payload.detention_facility
      ]), ''),
      cohabitationDuration: upperText(cohabitationDuration, ''),
      cohabitationStartDate: upperText(cohabitationStartDateDisplay, ''),
      cohabitationChildrenCount: Number.parseInt(firstNonEmpty([payload.cohabitation_children_count, '0']), 10) || 0,
      cohabitationChildrenList: upperText(firstNonEmpty([payload.children_list, childLines.join('; ')]), ''),
      educationalAttainment: upperText(firstNonEmpty([payload.educational_attainment]), ''),
      jobstartBeneficiary: upperText(firstNonEmpty([payload.jobstart_beneficiary]), ''),
      applicantHonorific: upperText(applicantHonorific(sexValue), ''),
      residencySinceText: upperText(residencySinceText, ''),
      signedDate: upperText(previewDateText(firstNonEmpty([row.completed_at, row.release_timestamp, row.ready_at])), ''),
      additionalDetails: additionalDetails.map((entry) => ({ ...entry, value: upperText(entry.value, '') })),
      qrUrl: resolvePublicUrl(firstNonEmpty([row.qr_code_path]))
    };
  }

  function renderDocumentPreview(state) {
    if (!state || typeof state !== 'object') {
      return '<div class="text-muted">No document preview available.</div>';
    }
    const docType = String(state.docType || 'Certificate').trim() || 'Certificate';
    const docKey = normalizePreviewDocKey(docType);
    const isIndigency = docKey === 'indigency';
    const isGeneralPermitClearance = docKey === 'generalpermitclearance';
    const isBusinessPermitClearance = docKey === 'businessclearance';
    const isTricyclePermitClearance = docKey === 'tricycleclearance';
    const isGoodMoral = docKey === 'goodmoral';
    const isResidency = docKey === 'residency';
    const isCohabitation = docKey === 'cohabitation';
    const isFirstTimeJobSeeker = docKey === 'firsttimejobseeker';
    const isRelationshipJailVisit = isCohabitation
      && ['relationship_jail_visit', 'conjugal_visit'].includes(String(state.cohabitationVariant || '').trim().toLowerCase());
    const fullName = String(state.fullName || '-').trim() || '-';
    const fullAddress = String(state.fullAddress || '-').trim() || '-';
    const purpose = String(state.purpose || '-').trim() || '-';
    const location = String(state.location || '').trim();
    const franchisee = String(state.franchisee || '').trim();
    const vehicleType = String(state.vehicleType || '').trim();
    const registrationNumber = String(state.registrationNumber || '').trim();
    const issuedDateWord = previewIndigencyIssuedText(state.issuedDate || '');
    const certificateNumber = String(state.certificateNumber || '').trim();
    const birthdate = String(state.birthdate || '').trim();
    const birthplace = String(state.birthplace || '').trim();
    const remarks = String(state.remarks || '').trim();
    const requestFor = String(state.requestFor || '').trim();
    const bodyNumber = String(state.bodyNumber || '').trim();
    const age = String(state.age || '').trim();
    const cohabitantName = String(state.cohabitantName || '').trim();
    const cohabitantAgeRawState = String(state.cohabitantAge || '').trim();
    const cohabitantBirthdate = String(state.cohabitantBirthdate || '').trim();
    const cohabitantAge = cohabitantAgeRawState || deriveAgeFromDate(cohabitantBirthdate);
    const cohabitantRelationship = String(state.cohabitantRelationship || '').trim();
    const detentionFacility = String(state.detentionFacility || '').trim();
    const businessName = String(state.businessName || '').trim();
    const businessAddress = String(state.businessAddress || state.location || '').trim();
    const businessApprovalType = normalizeBusinessApprovalType(state.businessApprovalType || '');
    const plateNumber = String(state.plateNumber || '').trim();
    const operatorName = String(state.operatorName || fullName || '').trim();
    const operatorAddress = String(state.operatorAddress || '').trim();
    const amount = String(state.amount || '').trim();
    const applicantResidenceAddress = String(state.applicantResidenceAddress || state.fullAddress || '').trim();
    const cohabitantResidenceAddress = String(state.cohabitantResidenceAddress || state.fullAddress || '').trim();
    const cohabitationResidenceAddress = String(state.cohabitationResidenceAddress || state.fullAddress || '').trim();
    const cohabitationDuration = String(state.cohabitationDuration || '').trim();
    const cohabitationStartDate = String(state.cohabitationStartDate || '').trim();
    const cohabitationChildrenCount = Math.max(0, Number.parseInt(String(state.cohabitationChildrenCount || '0'), 10) || 0);
    const cohabitationChildrenList = String(state.cohabitationChildrenList || '').trim();
    const applicantHonorificText = String(state.applicantHonorific || 'MR./MS.').trim() || 'MR./MS.';
    const residencySinceText = String(state.residencySinceText || '').trim();
    const signedDateText = String(state.signedDate || 'DATE').trim() || 'DATE';
    const leftLogoUrl = `${appBase}/Images/San_Jose_LOGO.jpg`;
    const rightLogoUrl = `${appBase}/Images/Montalban_Logo.png`;
    const fallbackRightLogoUrl = `${appBase}/Images/San_Jose_LOGO.jpg`;
    const qrUrl = String(state.qrUrl || '').trim();
    const requestOfficerLine1 = String(state.requestOfficerLine1 || '').trim();
    const requestOfficerLine2 = String(state.requestOfficerLine2 || '').trim();
    const requestOfficerLine3 = String(state.requestOfficerLine3 || '').trim();
    const safe = (value, fallback = '-') => (String(value || '').trim() || fallback);
    const templateSafe = (value, fallback = '-') => {
      const text = String(value || '').trim();
      return (text && text !== '-') ? text : fallback;
    };
    const businessMetaValue = (key, value) => {
      const text = String(value || '').trim();
      if (!text) return '<span class="doc-preview-business-meta-line"></span>';
      return previewEditable(key, text, '_____');
    };
    const businessCheckMark = (type) => {
      const selected = businessApprovalType === type;
      return `
        <span class="doc-preview-business-check-mark${selected ? ' doc-preview-business-check-mark--selected' : ''}">
          <span class="doc-preview-business-check-line"></span>
          <span class="doc-preview-business-check-tick">✓</span>
          <span class="doc-preview-business-check-line"></span>
        </span>
      `;
    };
    const fullAddressWithBarangay = composeBarangayAddress(fullAddress);
    const applicantAddressWithBarangay = composeBarangayAddress(applicantResidenceAddress || fullAddress);
    const cohabitationHasChildren = isCohabitation && cohabitationChildrenCount > 0;

    const residencyRows = `
      <div class="doc-to-block"><strong>Name</strong><strong>:</strong><strong>${esc(safe(fullName))}</strong></div>
      <div class="doc-to-block"><strong>Address</strong><strong>:</strong><div><strong>${esc(safe(fullAddress))}</strong><br><strong>BARANGAY SAN JOSE, MONTALBAN, RIZAL</strong></div></div>
      <div class="doc-to-block"><strong>Birthday</strong><strong>:</strong><strong>${esc(safe(birthdate, '${Birthdate}'))}</strong></div>
      <div class="doc-to-block"><strong>Birthplace</strong><strong>:</strong><strong>${esc(safe(birthplace, '${Birthplace}'))}</strong></div>
      <div class="doc-to-block"><strong>Remarks</strong><strong>:</strong><strong>${previewEditable('remarks', safe(remarks, '${REMARKS}'), '${REMARKS}')}</strong></div>
      <div class="doc-to-block"><strong>Purpose</strong><strong>:</strong><strong>${esc(safe(purpose, '${PURPOSE}'))}</strong></div>
    `;

    let contentHtml = '';
    let titleHtml = '<div class="doc-preview-goodmoral-office"><div>TANGGAPAN NG PUNONG BARANGAY</div><div>BARANGAY CERTIFICATION</div></div>';
    let issuedLine = `Issued this <strong>${esc(issuedDateWord)}</strong> at the office of the Punong Barangay, Barangay San Jose, Montalban, Rizal`;
    let metaHtml = renderPreviewMetaRows([
      { label: 'CTC No.:', value: '_____' },
      { label: 'Issued at:', value: '_____' },
      { label: 'Issued On:', value: '_____' },
      { label: 'OR No.:', value: esc(safe(state.orNumber, '_____')) },
    ]);

    if (isIndigency) {
      const indigencyPurpose = safe(purpose || requestFor, 'PURPOSE');
      const toBlock = `
        <div class="doc-to-block">
          <strong>TO</strong><strong>:</strong>
          <div class="doc-to-lines">
            <div><strong>${previewEditable('requestOfficerLine1', safe(requestOfficerLine1, '${REQUEST_OFFICER_LINE1}'), 'Official name')}</strong></div>
            <div><strong>${previewEditable('requestOfficerLine2', safe(requestOfficerLine2, '${REQUEST_OFFICER_LINE2}'), 'Position')}</strong></div>
            <div><strong>${previewEditable('requestOfficerLine3', safe(requestOfficerLine3, '${REQUEST_OFFICER_LINE3}'), 'Jurisdiction')}</strong></div>
          </div>
        </div>
      `;
      titleHtml = '<div class="doc-preview-title doc-preview-title--indigency"><div class="office">TANGGAPAN NG PUNONG BARANGAY</div><div class="certificate">CERTIFICATE OF INDIGENCY</div></div>';
      contentHtml = `
        ${toBlock}
        <p>
          This is to certify that <strong>${esc(safe(fullName, '${FULL_NAME}'))}</strong>, resident of
          <strong>${esc(safe(fullAddress, '${ADDRESS}'))}</strong><br>
          <strong>BARANGAY SAN JOSE, RODRIGUEZ, RIZAL</strong>
          belongs to the one of the indigent families of this Barangay. The Income of this family is barely enough to meet their day-to-day needs.
        </p>
        <p>
          This certification is being issued upon the request of the above subject in person in connection with his/her application for
          <strong>${previewEditable('purpose', indigencyPurpose, 'PURPOSE')}</strong> purposes only.
        </p>
      `;
      metaHtml = '';
      issuedLine = `Issued this <strong>${esc(issuedDateWord)}</strong>, at the office of the punong Barangay, Barangay San Jose, Rodriguez (Montalban), Rizal.`;
    } else if (isGeneralPermitClearance) {
      titleHtml = '<div class="doc-preview-generalclearance-office"><div>TANGGAPAN NG PUNONG BARANGAY</div><div>BARANGAY CLEARANCE</div></div>';
      contentHtml = `
        <p class="doc-preview-generalclearance-lead"><strong>TO WHOM IT MAY CONCERN::</strong></p>
        <p class="doc-preview-generalclearance-intro">
          This is to certify that the person whose name and thumb mark appears here on has
          requested a Barangay Clearance from this office and the information are listed below:
        </p>
        <div class="doc-preview-generalclearance-fields">
          <div class="doc-preview-generalclearance-field">
            <strong class="doc-preview-generalclearance-field-label">Name</strong>
            <strong class="doc-preview-generalclearance-field-colon">:</strong>
            <div class="doc-preview-generalclearance-field-value"><strong>${esc(templateSafe(fullName, '${FULL_NAME}'))}</strong></div>
          </div>
          <div class="doc-preview-generalclearance-field doc-preview-generalclearance-field--address">
            <strong class="doc-preview-generalclearance-field-label">Residential Address</strong>
            <strong class="doc-preview-generalclearance-field-colon">:</strong>
            <div class="doc-preview-generalclearance-field-value"><strong>${esc(templateSafe(fullAddress, '${ADDRESS}'))}</strong><br><strong>Barangay San Jose, Montalban, Rizal</strong></div>
          </div>
          <div class="doc-preview-generalclearance-field">
            <strong class="doc-preview-generalclearance-field-label">Location</strong>
            <strong class="doc-preview-generalclearance-field-colon">:</strong>
            <div class="doc-preview-generalclearance-field-value"><strong>${esc(templateSafe(location, '${LOCATION}'))}</strong></div>
          </div>
          <div class="doc-preview-generalclearance-field">
            <strong class="doc-preview-generalclearance-field-label">Remarks</strong>
            <strong class="doc-preview-generalclearance-field-colon">:</strong>
            <div class="doc-preview-generalclearance-field-value"><strong>${esc(templateSafe(remarks, '${REMARKS}'))}</strong></div>
          </div>
          <div class="doc-preview-generalclearance-field">
            <strong class="doc-preview-generalclearance-field-label">Purpose</strong>
            <strong class="doc-preview-generalclearance-field-colon">:</strong>
            <div class="doc-preview-generalclearance-field-value"><strong>${esc(templateSafe(purpose, '${PURPOSE}'))}</strong></div>
          </div>
        </div>
        <p class="doc-preview-generalclearance-note">
          This clearance is being issued pursuant to Barangay Revenue Code ORDINANCE NO.<br>11 - 2019
        </p>
      `;
      issuedLine = `Issued this <strong>${esc(issuedDateWord)}</strong> at the office of the Punong Barangay, Barangay<br>San Jose, Montalban, Rizal`;
      metaHtml = `
        <div class="doc-preview-generalclearance-meta">
          <div class="doc-preview-generalclearance-meta-row">
            <div class="doc-preview-generalclearance-meta-label"><strong>CTC No.</strong></div>
            <div class="doc-preview-generalclearance-meta-colon">:</div>
            <div class="doc-preview-generalclearance-meta-value"><strong>${esc(templateSafe(certificateNumber, '${CERTIFICATE_NUMBER}'))}</strong></div>
          </div>
          <div class="doc-preview-generalclearance-meta-row">
            <div class="doc-preview-generalclearance-meta-label"><strong>Issued at</strong></div>
            <div class="doc-preview-generalclearance-meta-colon">:</div>
            <div class="doc-preview-generalclearance-meta-value"><span class="doc-preview-generalclearance-meta-line"></span></div>
          </div>
          <div class="doc-preview-generalclearance-meta-row">
            <div class="doc-preview-generalclearance-meta-label"><strong>Issued On</strong></div>
            <div class="doc-preview-generalclearance-meta-colon">:</div>
            <div class="doc-preview-generalclearance-meta-value"><span class="doc-preview-generalclearance-meta-line"></span></div>
          </div>
          <div class="doc-preview-generalclearance-meta-row">
            <div class="doc-preview-generalclearance-meta-label"><strong>Amount</strong></div>
            <div class="doc-preview-generalclearance-meta-colon">:</div>
            <div class="doc-preview-generalclearance-meta-value"><strong>${esc(templateSafe(amount, '${AMOUNT}'))}</strong></div>
          </div>
          <div class="doc-preview-generalclearance-meta-row">
            <div class="doc-preview-generalclearance-meta-label"><strong>OR No.</strong></div>
            <div class="doc-preview-generalclearance-meta-colon">:</div>
            <div class="doc-preview-generalclearance-meta-value"><strong>${esc(templateSafe(state.orNumber, '${OR_NUMBER}'))}</strong></div>
          </div>
        </div>
      `;
    } else if (isTricyclePermitClearance) {
      titleHtml = '<div class="doc-preview-tricycle-office"><div>TANGGAPAN NG PUNONG BARANGAY</div><div>BARANGAY CLEARANCE</div></div>';
      contentHtml = `
        <p class="doc-preview-tricycle-lead"><strong>TO WHOM IT MAY CONCERN:</strong></p>
        <p class="doc-preview-tricycle-intro">
          This is to certify that the person whose name and thumb mark appears here on has
          requested a Barangay Clearance from this office and the information are listed below:
        </p>
        <div class="doc-preview-tricycle-fields">
          <div class="doc-preview-tricycle-field">
            <strong class="doc-preview-tricycle-field-label">Name</strong>
            <strong class="doc-preview-tricycle-field-colon">:</strong>
            <div class="doc-preview-tricycle-field-value"><strong>${esc(templateSafe(fullName, '${NAME}'))}</strong></div>
          </div>
          <div class="doc-preview-tricycle-field doc-preview-tricycle-field--address">
            <strong class="doc-preview-tricycle-field-label">Address</strong>
            <strong class="doc-preview-tricycle-field-colon">:</strong>
            <div class="doc-preview-tricycle-field-value"><strong>${esc(templateSafe(fullAddress, '${ADDRESS}'))}</strong><br><strong>Barangay San Jose, Montalban, Rizal</strong></div>
          </div>
          <div class="doc-preview-tricycle-field">
            <strong class="doc-preview-tricycle-field-label">Location</strong>
            <strong class="doc-preview-tricycle-field-colon">:</strong>
            <div class="doc-preview-tricycle-field-value"><strong>${esc(templateSafe(franchisee, '${LOCATION_OF_TODA/PODA}'))}</strong></div>
          </div>
          <div class="doc-preview-tricycle-field">
            <strong class="doc-preview-tricycle-field-label">Type of Vehicle</strong>
            <strong class="doc-preview-tricycle-field-colon">:</strong>
            <div class="doc-preview-tricycle-field-value"><strong>${esc(templateSafe(vehicleType, '${TYPE_OF_VEHICLE}'))}</strong></div>
          </div>
          <div class="doc-preview-tricycle-field">
            <strong class="doc-preview-tricycle-field-label">Registration No.</strong>
            <strong class="doc-preview-tricycle-field-colon">:</strong>
            <div class="doc-preview-tricycle-field-value"><strong>${esc(templateSafe(registrationNumber, '${REGISTRATION_NUMBER}'))}</strong></div>
          </div>
          <div class="doc-preview-tricycle-field">
            <strong class="doc-preview-tricycle-field-label">Plate No.</strong>
            <strong class="doc-preview-tricycle-field-colon">:</strong>
            <div class="doc-preview-tricycle-field-value"><strong>${esc(templateSafe(plateNumber, '${PLATE_NUMBER}'))}</strong></div>
          </div>
          <div class="doc-preview-tricycle-field">
            <strong class="doc-preview-tricycle-field-label">Body No.</strong>
            <strong class="doc-preview-tricycle-field-colon">:</strong>
            <div class="doc-preview-tricycle-field-value"><strong>${esc(templateSafe(bodyNumber, '${BODY_NUMBER}'))}</strong></div>
          </div>
        </div>
        <p class="doc-preview-tricycle-purpose">
          This certification is being issued upon the request of the above subject person for his/her
          application for necessary permit
        </p>
      `;
      issuedLine = `Issued this <strong>${esc(issuedDateWord)}</strong> at the office of the Punong Barangay, Barangay<br>San Jose, Montalban, Rizal`;
      metaHtml = `
        <div class="doc-preview-tricycle-meta">
          <div class="doc-preview-tricycle-meta-row">
            <div class="doc-preview-tricycle-meta-label"><strong>Clearance No.</strong></div>
            <div class="doc-preview-tricycle-meta-colon">:</div>
            <div class="doc-preview-tricycle-meta-value"><strong>${esc(templateSafe(certificateNumber, '${CLEARANCE_NUMBER}'))}</strong></div>
          </div>
          <div class="doc-preview-tricycle-meta-row">
            <div class="doc-preview-tricycle-meta-label"><strong>Reciept No.</strong></div>
            <div class="doc-preview-tricycle-meta-colon">:</div>
            <div class="doc-preview-tricycle-meta-value"><strong>${esc(templateSafe(state.orNumber, '${RECIEPT_NUMBER}'))}</strong></div>
          </div>
          <div class="doc-preview-tricycle-meta-row">
            <div class="doc-preview-tricycle-meta-label"><strong>Amount</strong></div>
            <div class="doc-preview-tricycle-meta-colon">:</div>
            <div class="doc-preview-tricycle-meta-value"><strong>${esc(templateSafe(amount, '${AMOUNT}'))}</strong></div>
          </div>
        </div>
      `;
    } else if (isBusinessPermitClearance) {
      titleHtml = '<div class="doc-preview-goodmoral-office doc-preview-business-office"><div>TANGGAPAN NG PUNONG BARANGAY</div><div>BARANGAY CLEARANCE FOR BUSINESS PERMIT</div></div>';
      contentHtml = `
        <p class="doc-preview-business-lead"><strong>TO WHOM IT MAY CONCERN::</strong></p>
        <p class="doc-preview-business-intro">This is to certify that the business or trade activity below</p>
        <div class="doc-preview-business-fields">
          <div class="doc-preview-business-field"><strong>${previewEditable('businessName', safe(businessName, '${BUSINESS_NAME}'), '${BUSINESS_NAME}')}</strong></div>
          <div class="doc-preview-business-field"><strong>${previewEditable('businessAddress', safe(businessAddress, '${BUSINESS_ADDRESS}'), '${BUSINESS_ADDRESS}', 'doc-editable-multiline')}</strong></div>
          <div class="doc-preview-business-field"><strong>${previewEditable('operatorName', safe(operatorName, '${OPERATOR_NAME}'), '${OPERATOR_NAME}')}</strong></div>
          <div class="doc-preview-business-field"><strong>${previewEditable('operatorAddress', safe(operatorAddress, '${OPERATOR_ADDRESS}'), '${OPERATOR_ADDRESS}', 'doc-editable-multiline')}</strong></div>
        </div>
        <p class="doc-preview-business-paragraph">
          Proposed to be established or being applied for renewal for a Barangay Clearance to be used in securing corresponding Business Permit has been found to be:
        </p>
        <p class="doc-preview-business-paragraph">
          In conformity with the provisions of existing Barangay Ordinances, Rules and Regulations being enforced in this Barangay.
        </p>
        <div class="doc-preview-business-checks">
          <div class="doc-preview-business-check-row">
            ${businessCheckMark('not_banned')}
            <span>Not among those business or trade activities being banned to be established in this Barangay</span>
          </div>
          <div class="doc-preview-business-check-row">
            ${businessCheckMark('no_objection')}
            <span>Interposes no objection for the issuance of the corresponding Business Permit being applied for.</span>
          </div>
          <div class="doc-preview-business-check-row">
            ${businessCheckMark('temporary_clearance')}
            <span>Recommendations only the issuance of "Temporary Barangay Clearance" subject for revocation anytime provided that the requirements under existing Barangay Ordinance, Rules and Regulations should be complied with, otherwise this Barangay should take the necessary actions within legal bounds to stop its continued operations.</span>
          </div>
        </div>
      `;
      issuedLine = `Issued this <strong>${esc(issuedDateWord)}</strong> at the office of the Punong Barangay, Barangay San Jose, Montalban, Rizal.`;
      metaHtml = '';
    } else if (isGoodMoral) {
      contentHtml = `
        <p><strong>TO WHOM IT MAY CONCERN:</strong></p>
        <p>
          This is to certify <strong>${esc(safe(fullName, '${FULL_NAME}'))}</strong>, resident of
          <strong>${esc(safe(fullAddressWithBarangay, '${ADDRESS}'))}</strong>
          is personally known to be as a person of <strong>GOOD MORAL CHARACTER, PEACEFUL and LAW-ABIDING CITIZEN of THE COMMUNITY.</strong>
        </p>
        <p>
          This further certifies that he/she is not a member, nor has joined a subversive society organization against the government.
        </p>
        <p>
          This certification is being issued upon the request of the above-named person to be used for his/her application for
          <strong>${previewEditable('purpose', safe(purpose, '${PURPOSE}'), 'PURPOSE')}</strong> purposes only.
        </p>
      `;
    } else if (isRelationshipJailVisit) {
      titleHtml = '<div class="doc-preview-goodmoral-office"><div>TANGGAPAN NG PUNONG BARANGAY</div><div>BARANGAY CERTIFICATION</div></div>';
      contentHtml = `
        <p class="doc-preview-jail-lead"><strong>TO WHOM IT MAY CONCERN:</strong></p>
        <p class="doc-preview-jail-center">
          This is to certify <strong>${esc(safe(fullName, '${NAME}'))}</strong>, resident of
          <strong>${esc(safe(applicantAddressWithBarangay, '${ADDRESS}'))}</strong>
          is personally known to be as a person of <strong>GOOD MORAL CHARACTER, PEACEFUL and LAW-ABIDING CITIZEN of THE COMMUNITY.</strong>
        </p>
        <p class="doc-preview-jail-center">
          Moreover, this certifies that the subject person is the <strong>${esc(safe(cohabitantRelationship, '${RELATIONSHIP}'))}</strong>
          of DETAINED <strong>${esc(safe(cohabitantName, '${DETAINEE_NAME}'))}</strong> and presently at the
          <strong>${previewEditable('detentionFacility', safe(detentionFacility, '${PRISON}'), '${PRISON}')}</strong>.
        </p>
        <p class="doc-preview-jail-center doc-preview-jail-ordinance">
          This certification is being issued pursuant to Barangay Revenue Code ORDINANCE NO. 11 – 2019.
        </p>
      `;
      issuedLine = `Issued this <strong>${esc(issuedDateWord)}</strong> at the office of the Punong Barangay, Barangay San Jose, Montalban, Rizal.`;
      metaHtml = renderPreviewMetaRows([
        { label: 'CTC No.:', value: '_____' },
        { label: 'Issued at:', value: '_____' },
        { label: 'Issued On:', value: '_____' },
        { label: 'OR No.:', value: esc(safe(state.orNumber, '_____')) },
      ]);
    } else if (isCohabitation && !cohabitationHasChildren) {
      titleHtml = '<div class="doc-preview-goodmoral-office"><div>TANGGAPAN NG PUNONG BARANGAY</div><div>CERTIFICATE OF COHABITATION</div></div>';
      contentHtml = `
        <p><strong>TO WHOM IT MAY CONCERN:</strong></p>
        <p>
          This is to certify that <strong>${esc(safe(fullName, '${NAME}'))}</strong>,
          <strong>${esc(safe(age, '${AGE}'))} y/o</strong> a resident of <strong>${esc(safe(applicantResidenceAddress, '${ADDRESS}'))}</strong>
          and <strong>${esc(safe(cohabitantName, '${PARTNER_NAME}'))}</strong>,
          <strong>${esc(safe(cohabitantAge, '-'))} y/o</strong> a resident of <strong>${esc(safe(cohabitantResidenceAddress, '${PARTNER_ADDRESS}'))}</strong>.
        </p>
        <p>
          This further certifies that they are both living together since
          <strong>${esc(safe(cohabitationStartDate || cohabitationDuration, '${COHABITATION_DURATION}'))}</strong>
          up to present on <strong>${esc(safe(cohabitationResidenceAddress, '${COHABITATION_ADDRESS}'))}</strong>.
        </p>
        <p>
          This certification is being issued upon the request of both parties for whatever legal purpose it may serve them.
        </p>
      `;
      metaHtml = renderPreviewMetaRows([
        { label: 'CTC No.:', value: '_____' },
        { label: 'Issued at:', value: '_____' },
        { label: 'Issued On:', value: '_____' },
        { label: 'OR No.:', value: esc(safe(state.orNumber, '_____')) },
      ]);
    } else if (isCohabitation && cohabitationHasChildren) {
      titleHtml = '<div class="doc-preview-goodmoral-office"><div>TANGGAPAN NG PUNONG BARANGAY</div><div>CERTIFICATE OF COHABITATION</div></div>';
      contentHtml = `
        <p><strong>TO WHOM IT MAY CONCERN:</strong></p>
        <p>
          This is to certify that the person whose name appears here on has requested a Barangay Certification from this office and the information are listed below:
        </p>
        <div class="doc-to-block"><strong>Name</strong><strong>:</strong><div><div><strong>${esc(safe(fullName, '-'))}</strong>, ${esc(safe(age, '-'))} y/o</div><div><strong>${esc(safe(cohabitantName, '-'))}</strong>, ${esc(safe(cohabitantAge, '-'))} y/o</div></div></div>
        <div class="doc-to-block"><strong>Address</strong><strong>:</strong><div><strong>${esc(safe(fullAddress, '-'))}</strong><br><strong>BARANGAY SAN JOSE, MONTALBAN, RIZAL</strong></div></div>
        <div class="doc-to-block"><strong>Remarks</strong><strong>:</strong><strong>${previewEditable('remarks', safe(remarks, '-'), 'Remarks')}</strong></div>
        <div class="doc-to-block"><strong>Purpose</strong><strong>:</strong><strong>${esc(`COHABITATION SINCE ${safe(cohabitationStartDate || cohabitationDuration, '-')}`)}</strong></div>
        <div class="doc-to-block"><strong>Name of Children</strong><strong>:</strong><span>${esc(safe(cohabitationChildrenList, '-'))}</span></div>
        <p>
          This clearance is being issued pursuant to Barangay Revenue Code ORDINANCE NO. 11 – 2019
        </p>
      `;
      metaHtml = renderPreviewMetaRows([
        { label: 'CTC No.:', value: '_____' },
        { label: 'Issued at:', value: '_____' },
        { label: 'Issued On:', value: '_____' },
        { label: 'OR No.:', value: esc(safe(state.orNumber, '_____')) },
      ]);
    } else if (isResidency) {
      titleHtml = '<div class="doc-preview-goodmoral-office"><div>TANGGAPAN NG PUNONG BARANGAY</div><div>BARANGAY CERTIFICATION</div></div>';
      contentHtml = `
        <p><strong>TO WHOM IT MAY CONCERN:</strong></p>
        <p>
          This is to certify that the person whose name appears here on has requested a Barangay Clearance from this office and the information are listed below:
        </p>
        ${residencyRows}
        <p>
          This clearance is being issued pursuant to Barangay Revenue Code ORDINANCE NO. 11 – 2019
        </p>
      `;
      metaHtml = renderPreviewMetaRows([
        { label: 'CTC No.:', value: '_____' },
        { label: 'Issued at:', value: '_____' },
        { label: 'Issued On:', value: '_____' },
        { label: 'OR No.:', value: esc(safe(state.orNumber, '_____')) },
      ]);
    } else if (isFirstTimeJobSeeker) {
      titleHtml = `
        <div class="doc-preview-goodmoral-office doc-preview-ftjs-office">
          <div>TANGGAPAN NG PUNONG BARANGAY</div>
          <div>BARANGAY CERTIFICATION</div>
          <div class="doc-preview-ftjs-subtitle">(First Time Jobseekers Act-RA 11261)</div>
        </div>
      `;
      contentHtml = `
        <p><strong>TO WHOM IT MAY CONCERN:</strong></p>
        <p>
          This is to certify <strong>${esc(safe(applicantHonorificText, 'MR./MS.'))} ${esc(safe(fullName, '${NAME}'))}</strong>,
          resident of <strong>${esc(safe(fullAddressWithBarangay, '${ADDRESS}'))}</strong>
          since <strong>${esc(safe(residencySinceText, '${RESIDENCY_SINCE}'))}</strong> is a qualified availlee of RA 11261
          or the First Time Jobseekers Act 2019.
        </p>
        <p>
          I further certify that the holder/bearer was informed of his/her rights, including the duties and responsibilities accorded by RA 11261 through the Oath of Undertaking he/she has signed and executed in the presence of our Barangay Official.
        </p>
      `;
      metaHtml = '';
    } else if (isResidency) {
      titleHtml = '<div class="doc-preview-goodmoral-office"><div>TANGGAPAN NG PUNONG BARANGAY</div><div>BARANGAY CERTIFICATION</div></div>';
      contentHtml = `
        <p><strong>TO WHOM IT MAY CONCERN:</strong></p>
        <p>
          This is to certify that the person whose name appears here on has requested a Barangay Clearance from this office and the information are listed below:
        </p>
        ${residencyRows}
      `;
    } else {
      contentHtml = `
        <p><strong>TO WHOM IT MAY CONCERN:</strong></p>
        <p>
          This is to certify that the person whose name appears here on has requested a Barangay Clearance from this office and the information are listed below:
        </p>
        ${residencyRows}
      `;
    }

    const paperClass = isIndigency
      ? 'doc-preview-paper doc-preview-paper--indigency'
      : isBusinessPermitClearance
        ? 'doc-preview-paper doc-preview-paper--business'
        : isGeneralPermitClearance
          ? 'doc-preview-paper doc-preview-paper--generalclearance'
          : isTricyclePermitClearance
            ? 'doc-preview-paper doc-preview-paper--tricycle'
            : (isGoodMoral || isResidency || isCohabitation || isFirstTimeJobSeeker)
              ? `doc-preview-paper doc-preview-paper--goodmoral${(isCohabitation && cohabitationHasChildren) ? ' doc-preview-paper--cohabitation-children' : ''}${isFirstTimeJobSeeker ? ' doc-preview-paper--ftjs' : ''}${isRelationshipJailVisit ? ' doc-preview-paper--jail' : ''}`
              : 'doc-preview-paper';

    const qrBlockHtml = qrUrl !== ''
      ? `
          <div class="doc-preview-qr">
            <div class="doc-preview-qr-box">
              <img src="${esc(qrUrl)}" alt="QR Code">
            </div>
            QR
          </div>
        `
      : '';
    let footerAreaHtml = '';
    let footerNoteHtml = '';
    if (isBusinessPermitClearance) {
      footerAreaHtml = `
        <div class="doc-preview-business-footer-area${qrBlockHtml ? '' : ' doc-preview-business-footer-area--noqr'}">
          <div class="doc-preview-business-footer-main">
            <div class="doc-preview-business-issuedby">Issued by: <strong>MINERVA D. QUITA</strong><br><em>Barangay Secretary</em></div>
            <div class="doc-preview-business-signing">
              <div class="doc-preview-signature doc-preview-business-signature">
                <div class="name">HON. GLENN S. EVANGELISTA</div>
                <div>Punong Barangay</div>
              </div>
              <div class="doc-preview-signature doc-preview-business-signature">
                <div class="name">MR. JOSEPH C. PATRICIO</div>
                <div>Head, Monitoring &amp; Collection Dept.</div>
              </div>
            </div>
          </div>
          ${qrBlockHtml}
        </div>
        <div class="doc-preview-business-meta">
          <div class="doc-preview-business-meta-row">
            <div class="doc-preview-business-meta-label"><strong>O.R No.</strong></div>
            <div class="doc-preview-business-meta-colon">:</div>
            <div class="doc-preview-business-meta-value">${businessMetaValue('orNumber', state.orNumber)}</div>
          </div>
          <div class="doc-preview-business-meta-row">
            <div class="doc-preview-business-meta-label"><strong>Amount</strong></div>
            <div class="doc-preview-business-meta-colon">:</div>
            <div class="doc-preview-business-meta-value">${businessMetaValue('amount', amount)}</div>
          </div>
          <div class="doc-preview-business-meta-row">
            <div class="doc-preview-business-meta-label"><strong>Plate No.</strong></div>
            <div class="doc-preview-business-meta-colon">:</div>
            <div class="doc-preview-business-meta-value">${businessMetaValue('plateNumber', plateNumber)}</div>
          </div>
          <div class="doc-preview-business-meta-row">
            <div class="doc-preview-business-meta-label"><strong>Date Issued</strong></div>
            <div class="doc-preview-business-meta-colon">:</div>
            <div class="doc-preview-business-meta-value"><span class="doc-preview-business-meta-line"></span></div>
          </div>
          <div class="doc-preview-business-meta-row">
            <div class="doc-preview-business-meta-label"><strong>Place Issued</strong></div>
            <div class="doc-preview-business-meta-colon">:</div>
            <div class="doc-preview-business-meta-value"><span class="doc-preview-business-meta-line"></span></div>
          </div>
        </div>
      `;
      footerNoteHtml = 'This document is valid until the end of the year,<br>Check the qr code to verify the authenticity of this document.';
    } else if (isGeneralPermitClearance) {
      footerAreaHtml = `
        <div class="doc-preview-generalclearance-footer-area${qrBlockHtml ? '' : ' doc-preview-generalclearance-footer-area--noqr'}">
          <div class="doc-preview-generalclearance-issuedby">Issued by: <strong>MINERVA D. QUITA</strong><br><em>Barangay Secretary</em></div>
          <div class="doc-preview-generalclearance-signing">
            <div class="doc-preview-signature doc-preview-generalclearance-signature">
              <div class="name">${esc(String(state.approvedByName || 'HON. GLENN S. EVANGELISTA').trim() || 'HON. GLENN S. EVANGELISTA')}</div>
              <div>Punong Barangay</div>
            </div>
            <div class="doc-preview-signature doc-preview-generalclearance-signature">
              <div class="name">MR. JOSEPH C. PATRICIO</div>
              <div>Head, Monitoring &amp; Collection Dept.</div>
            </div>
          </div>
          ${qrBlockHtml}
        </div>
      `;
      footerNoteHtml = 'This clearance is valid for Forty-five (45) days from the date issued and not valid without official seal. Check the qr code to verify the authenticity of this document.';
    } else if (isTricyclePermitClearance) {
      footerAreaHtml = `
        <div class="doc-preview-tricycle-footer-area${qrBlockHtml ? '' : ' doc-preview-tricycle-footer-area--noqr'}">
          <div class="doc-preview-tricycle-issuedby">Issued by: <strong>MINERVA D. QUITA</strong><br><em>Barangay Secretary</em></div>
          <div class="doc-preview-tricycle-signing">
            <div class="doc-preview-signature doc-preview-tricycle-signature">
              <div class="name">${esc(String(state.approvedByName || 'HON. GLENN S. EVANGELISTA').trim() || 'HON. GLENN S. EVANGELISTA')}</div>
              <div>Punong Barangay</div>
            </div>
            <div class="doc-preview-signature doc-preview-tricycle-signature">
              <div class="name">MR. JOSEPH C. PATRICIO</div>
              <div>Head, Monitoring &amp; Collection Dept.</div>
            </div>
          </div>
          ${qrBlockHtml}
        </div>
      `;
      footerNoteHtml = 'Check the qr code to verify the authenticity of this document.';
    } else {
      const footerAreaClass = `doc-preview-footer-area${isFirstTimeJobSeeker ? ' doc-preview-footer-area--ftjs' : ''}${qrBlockHtml ? '' : ' doc-preview-footer-area--noqr'}`;
      footerAreaHtml = isFirstTimeJobSeeker
        ? `
            <div class="${footerAreaClass}">
              <div></div>
              <div class="doc-preview-ftjs-signing">
                <div class="doc-preview-signature doc-preview-signature--ftjs">
                  <div class="name">${esc(String(state.approvedByName || 'HON. GLENN S. EVANGELISTA').trim() || 'HON. GLENN S. EVANGELISTA')}</div>
                  <div>Punong Barangay</div>
                </div>
                <div class="doc-preview-ftjs-date"><span>${esc(signedDateText)}</span></div>
                <div class="doc-preview-ftjs-witness-label">Witnesses by:</div>
                <div class="doc-preview-ftjs-witness">
                  <div class="name">MINERVA D. QUITA</div>
                  <div>Barangay Secretary</div>
                </div>
                <div class="doc-preview-ftjs-date"><span>${esc(signedDateText)}</span></div>
              </div>
              ${qrBlockHtml}
            </div>
          `
        : `
            <div class="${footerAreaClass}">
              <div class="doc-preview-issuedby">Issued by: <strong>MINERVA D. QUITA</strong><br><em>Barangay Secretary</em></div>
              <div class="doc-preview-signature">
                <div class="name">${esc(String(state.approvedByName || 'HON. GLENN S. EVANGELISTA').trim() || 'HON. GLENN S. EVANGELISTA')}</div>
                <div>Punong Barangay</div>
              </div>
              ${qrBlockHtml}
            </div>
          `;
      footerNoteHtml = isFirstTimeJobSeeker
        ? 'This certification is only valid for 1 year from the issuance.<br>Check the qr code to verify the authenticity of this document.'
        : 'This certificate is valid for Forty-five (45) days from the date of issue,<br>check the QR code to verify the authenticity of this document.';
    }

    return `
      <div class="doc-preview-stage">
        <span class="doc-preview-label">Document Display</span>
        <div class="doc-preview-shell">
          <div class="${paperClass}">
            <p class="doc-preview-hint">Highlighted fields are editable in this preview.</p>
            <div class="doc-preview-head">
              <img class="doc-preview-logo" src="${leftLogoUrl}" alt="Barangay San Jose Logo">
              <div class="doc-preview-head-center">
                <p class="rep">REPUBLIKA NG PILIPINAS</p>
                <p>LALAWIGAN NG RIZAL</p>
                <p>BAYAN NG RODRIGUEZ</p>
                <p class="barangay">BARANGAY SAN JOSE</p>
                <div class="doc-preview-head-line"></div>
              </div>
              <img class="doc-preview-logo" src="${rightLogoUrl}" alt="Montalban Logo" onerror="this.onerror=null;this.src='${fallbackRightLogoUrl}'">
            </div>
            ${titleHtml}
            <div class="doc-preview-body">
              ${contentHtml}
              <p class="doc-preview-issued-line">${issuedLine}</p>
              ${metaHtml}
            </div>
            ${footerAreaHtml}
            <div class="doc-preview-footer">${footerNoteHtml}</div>
          </div>
        </div>
      </div>
    `;
  }

  function bindPreviewEditHandlers() {
    if (!viewDetailsBody) return;
    viewDetailsBody.querySelectorAll('.doc-editable[data-edit-key]').forEach((editable) => {
      editable.addEventListener('input', () => {
        if (!viewPreviewState) return;
        const key = String(editable.getAttribute('data-edit-key') || '').trim();
        if (!key) return;
        viewPreviewState[key] = String(editable.textContent || '').trim();
      });
    });
  }

  function resetPreviewScrollGate() {
    if (typeof previewScrollCleanup === 'function') {
      previewScrollCleanup();
    }
    previewScrollCleanup = null;
  }

  function bindApproveScrollGate() {
    resetPreviewScrollGate();
    if (!viewModalNextBtn || String(currentViewStage || '').toLowerCase() !== 'submitted') return;
    const currentRow = itemById.get(String(currentViewRequestId || '').trim());
    if (isFirstTimeJobSeekerRow(currentRow)) {
      viewModalNextBtn.disabled = false;
      viewModalNextBtn.title = '';
      return;
    }
    const scrollHost = viewDetailsBody.closest('.modal-body') || viewDetailsBody;
    if (!scrollHost) return;

    const threshold = 24;
    const update = () => {
      if (viewMode !== 'preview' || String(currentViewStage || '').toLowerCase() !== 'submitted') return;
      const remaining = scrollHost.scrollHeight - scrollHost.scrollTop - scrollHost.clientHeight;
      const reachedBottom = remaining <= threshold;
      viewModalNextBtn.disabled = !reachedBottom;
      viewModalNextBtn.title = reachedBottom ? '' : 'Scroll to the bottom before approving.';
    };

    scrollHost.scrollTop = 0;
    viewModalNextBtn.disabled = true;
    viewModalNextBtn.title = 'Scroll to the bottom before approving.';
    scrollHost.addEventListener('scroll', update, { passive: true });
    requestAnimationFrame(update);
    setTimeout(update, 150);

    previewScrollCleanup = () => {
      scrollHost.removeEventListener('scroll', update);
      if (viewModalNextBtn) {
        viewModalNextBtn.title = '';
      }
    };
  }

  function switchViewMode(mode) {
    viewMode = mode === 'preview' ? 'preview' : 'details';
    if (!viewDetailsBody) return;

    if (viewMode === 'preview') {
      const rid = String(currentViewRequestId || '').trim();
      loadTemplatePreview(rid, { preserveExisting: true });
      const stageKey = String(currentViewStage || '').toLowerCase();
      const submittedFlow = stageKey === 'submitted';
      const releaseFlow = stageKey === 'ready_for_claim';
      viewModalBackBtn?.classList.remove('d-none');
      if (viewModalBackBtn) {
        viewModalBackBtn.textContent = submittedFlow ? 'Cancel' : 'Back';
      }
      if (viewModalNextBtn) {
        if (submittedFlow) {
          const currentRow = itemById.get(rid);
          viewModalNextBtn.textContent = isFirstTimeJobSeekerRow(currentRow) ? 'Approve for Interview' : 'Save and Approve';
          viewModalNextBtn.classList.remove('d-none', 'btn-primary');
          viewModalNextBtn.classList.add('btn-success');
          viewModalNextBtn.disabled = !isFirstTimeJobSeekerRow(currentRow);
        } else if (releaseFlow) {
          viewModalNextBtn.textContent = 'Mark as Complete / Release';
          viewModalNextBtn.classList.remove('d-none', 'btn-primary');
          viewModalNextBtn.classList.add('btn-success');
          viewModalNextBtn.disabled = false;
        } else {
          viewModalNextBtn.textContent = 'Next';
          viewModalNextBtn.classList.remove('btn-success');
          viewModalNextBtn.classList.add('btn-primary', 'd-none');
          viewModalNextBtn.disabled = true;
        }
      }
      if (submittedFlow) {
        bindApproveScrollGate();
      } else {
        resetPreviewScrollGate();
      }
      return;
    }

    resetPreviewScrollGate();
    templatePreviewRequestSeq += 1;
    viewDetailsBody.innerHTML = viewDetailsHtml || '<div class="text-muted">No details.</div>';
    if (viewModalBackBtn) {
      viewModalBackBtn.textContent = 'Back';
      viewModalBackBtn.classList.add('d-none');
    }
    if (viewModalNextBtn) {
      viewModalNextBtn.textContent = 'Next';
      viewModalNextBtn.classList.remove('btn-success');
      viewModalNextBtn.classList.add('btn-primary', 'd-none');
    }
  }

  function friendlyLabel(key) {
    const raw = String(key || '').trim();
    if (!raw) return '';
    const map = {
      last_name: 'Last Name',
      first_name: 'First Name',
      middle_name: 'Middle Name',
      suffix_name: 'Suffix',
      suffix: 'Suffix',
      o_ln: 'Owner Last Name',
      o_fn: 'Owner First Name',
      o_mn: 'Owner Middle Name',
      o_sfx: 'Owner Suffix',
      o_phone: 'Owner Phone',
      owner_full_address: 'Owner Full Address',
      application_type: 'Application Type',
      business_name: 'Business Name',
      b_name: 'Business Name',
      business_contact_number: 'Business Contact Num',
      b_contact_1: 'Business Contact Num',
      business_address_system: 'Business Add System',
      business_street_number: 'Business Street Num',
      business_street_name: 'Business Street Name',
      business_subdivision: 'Subdivision',
      business_subdivision_block: 'Subdivision',
      business_barangay: 'Business Barangay',
      business_city: 'Business City',
      business_province: 'Business Province',
      business_full_address: 'Business Full Address',
      initial_operation_date: 'Initial Operation Date',
      b_date: 'Initial Operation Date',
      owner_type: 'Owner',
      business_reg_type: 'Business Registration Type',
      renewal_business_reg_type: 'Business Registration Type',
      ro_ln: 'Prev Last Name',
      ro_fn: 'Prev First Name',
      ro_mn: 'Prev Middle Name',
      ro_sfx: 'Prev Suffix',
      business_approval_type: 'Prev Business Approval Type',
      _preview_business_approval_type: 'Prev Business Approval Type',
      business_plate_number: 'Prev Plate Num',
      _preview_plate_number: 'Prev Plate Num',
      contact_number: 'Contact Number',
      phone_number: 'Contact Number',
      full_address: 'Full Address',
      full_address_display: 'Full Address',
      birthdate: 'Birthdate',
      child_dob: 'Date of Birth',
      age: 'Age',
      sex: 'Sex',
      gender: 'Gender',
      child_sex: 'Sex',
      civil_status: 'Civil Status',
      religion: 'Religion',
      occupation: 'Occupation',
      years_of_residency: 'Years of Residency',
      months_of_residency: 'Months of Residency',
      request_purpose: 'Request For',
      request_officer: 'To Be Submitted To',
      educational_attainment: 'Educational Attainment',
      jobstart_beneficiary: 'JobStart Beneficiary',
      residency_arrangement: 'Living Arrangement',
      supporting_document_type: 'Supporting Document Type',
      child_birthplace: 'Place of Birth',
      child_nationality: 'Nationality'
    };
    if (map[raw]) return map[raw];
    return raw.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
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
    el.textContent = normalizeDisplayCasing(String(value ?? '—').trim()) || '—';
  }

  function setAddressField(containerId, valueId, value) {
    const container = document.getElementById(containerId);
    const valueEl = document.getElementById(valueId);
    if (!container || !valueEl) return;
    const text = normalizeDisplayCasing(String(value ?? '').trim());
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
      const candidateRaw = String(data?.id_picture_url || '').trim();
      const candidate = resolvePublicUrl(candidateRaw);
      imgEl.onerror = () => {
        imgEl.onerror = null;
        imgEl.src = placeholder;
      };
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

  function isSameToken(a, b) {
    return String(a || '').trim().toLowerCase() === String(b || '').trim().toLowerCase();
  }

  function findResidentRow(rows, residentId, residentUserId) {
    if (!Array.isArray(rows) || !rows.length) return null;
    const rid = String(residentId || '').trim();
    const uid = String(residentUserId || '').trim();
    return rows.find((r) => {
      const rowResidentId = String(r?.resident_id || '').trim();
      const rowUserId = String(r?.user_id || '').trim();
      return (
        (rid !== '' && (isSameToken(rowResidentId, rid) || isSameToken(rowUserId, rid))) ||
        (uid !== '' && (isSameToken(rowResidentId, uid) || isSameToken(rowUserId, uid)))
      );
    }) || null;
  }

  async function openResidentProfileModal(residentId, residentUserId = '', fallbackProfile = null) {
    const rid = String(residentId || '').trim();
    const uid = String(residentUserId || '').trim();
    const fallbackRid = String(fallbackProfile?.resident_id || '').trim();
    const fallbackUid = String(fallbackProfile?.resident_user_id || fallbackProfile?.user_id || '').trim();
    const searchToken = rid || uid || fallbackRid || fallbackUid;
    if (!searchToken || !residentProfileModal) return;
    if (viewModalEl && viewModalEl.classList.contains('show') && viewModal) {
      preserveViewStateOnNextHide = true;
      viewModal.hide();
    }
    residentProfileModal.show();

    try {
      const q = new URLSearchParams({
        fetch: 'true',
        search: searchToken
      });
      const rows = await fetchJson(`${residentProfileEndpoint}?${q.toString()}`);
      let match = findResidentRow(rows, rid, uid);
      if (!match && rid && uid && !isSameToken(rid, uid)) {
        const retryQ = new URLSearchParams({
          fetch: 'true',
          search: uid
        });
        const retryRows = await fetchJson(`${residentProfileEndpoint}?${retryQ.toString()}`);
        match = findResidentRow(retryRows, rid, uid);
      }
      if (!match && fallbackProfile && typeof fallbackProfile === 'object') {
        match = fallbackProfile;
      }
      if (!match) return;
      fillResidentProfileModal(match);
    } catch (_) {
      // Keep modal open using fallback profile snapshot when fetch fails.
      if (fallbackProfile && typeof fallbackProfile === 'object') {
        fillResidentProfileModal(fallbackProfile);
      }
    }
  }

  residentProfileReturnBtn?.addEventListener('click', () => {
    if (residentProfileModal) {
      residentProfileModal.hide();
    }
    if (viewModal) {
      viewModal.show();
    }
  });

  paymentProofReturnBtn?.addEventListener('click', () => {
    if (paymentProofModal) {
      paymentProofModal.hide();
    }
    if (paymentProofReturnTarget === 'view' && viewModal) {
      viewModal.show();
    }
  });

  paymentProofPrintBtn?.addEventListener('click', () => {
    const url = String(paymentProofPrintUrl || '').trim();
    if (!url) return;
    const frame = paymentProofWrap?.querySelector('iframe');
    if (frame && frame.contentWindow) {
      try {
        frame.contentWindow.focus();
        frame.contentWindow.print();
        return;
      } catch (_) {
        // Fallback to new-tab print below.
      }
    }
    const w = window.open(url, '_blank', 'noopener');
    if (!w) return;
    const tryPrint = () => {
      try {
        w.focus();
        w.print();
      } catch (_) {
        // Ignore browser print restrictions.
      }
    };
    w.addEventListener('load', () => setTimeout(tryPrint, 250), { once: true });
    setTimeout(tryPrint, 1200);
  });

  paymentProofModalEl?.addEventListener('hidden.bs.modal', () => {
    paymentProofReturnTarget = '';
    paymentProofPrintUrl = '';
    if (paymentProofReturnBtn) {
      paymentProofReturnBtn.classList.add('d-none');
    }
    if (paymentProofTitle) {
      paymentProofTitle.textContent = 'Document Viewer';
    }
    if (paymentProofOpenNew) {
      paymentProofOpenNew.classList.remove('d-none');
    }
    if (paymentProofCloseBtn) {
      paymentProofCloseBtn.classList.remove('d-none');
    }
    if (paymentProofPrintBtn) {
      paymentProofPrintBtn.classList.add('d-none');
    }
  });

  submittedFileReturnBtn?.addEventListener('click', () => {
    if (submittedFileModal) {
      submittedFileModal.hide();
    }
    if (submittedFileReturnTarget === 'view' && viewModal) {
      viewModal.show();
    }
  });

  submittedFileModalEl?.addEventListener('hidden.bs.modal', () => {
    submittedFileReturnTarget = '';
    if (submittedFileTitle) {
      submittedFileTitle.textContent = 'Submitted Attachment Viewer';
    }
    if (submittedFileReturnBtn) {
      submittedFileReturnBtn.classList.add('d-none');
    }
    if (submittedFileOpenNew) {
      submittedFileOpenNew.classList.remove('d-none');
      submittedFileOpenNew.removeAttribute('href');
    }
    if (submittedFileCloseBtn) {
      submittedFileCloseBtn.classList.remove('d-none');
    }
    if (submittedFileWrap) {
      submittedFileWrap.innerHTML = '';
    }
  });

  function rowHtml(row) {
    const reasonValue = firstNonEmpty([row.status_remarks, row.status_reason]);
    const reason = reasonValue ? `<div class="text-danger small mt-1">Reason: ${esc(normalizeDisplayCasing(reasonValue))}</div>` : '';
    const fullName = fullNameFromRow(row);
    const purpose = normalizeDisplayCasing(firstNonEmpty([row.purpose, '-']));
    const statusKey = statusBucket(row);
    const financeStatusLabel = {
      verified: 'Verified',
      rejected: 'Rejected',
      pending_verification: 'Pending Verification',
      cancelled: 'Cancelled',
      unpaid: 'Unpaid'
    }[statusKey] || normalizeDisplayCasing(firstNonEmpty([row.payment_status_name, row.payment_status_label, row.stage_label, row.stage, '-']));
    const stageLabel = normalizeDisplayCasing(row.stage_label || row.stage || '');
    return `
      <tr>
        <td class="fw-semibold">${esc(row.request_id)}</td>
        <td>${esc(row.resident_id || '-')}</td>
        <td>${esc(fullName)}</td>
        <td>${documentTypeBadgeBlue(row)}</td>
        <td>
          <div class="cell-purpose">${esc(purpose)}</div>
        </td>
        <td>${badge(isFinancePaymentsPage ? statusKey : row.stage, esc(isFinancePaymentsPage ? financeStatusLabel : stageLabel))}${reason}</td>
        <td>${esc(row.submitted_at || '-')}</td>
        <td>${actionButtons(row)}</td>
      </tr>
    `;
  }

  function openDocumentModal(docUrl, title = 'Document Viewer', returnTarget = '', options = {}) {
    if (!docUrl || !paymentProofModal || !paymentProofWrap || !paymentProofOpenNew) return;
    const bustedUrl = (() => {
      const stamp = String(Date.now());
      try {
        const u = new URL(String(docUrl), window.location.origin);
        u.searchParams.set('_ts', stamp);
        return u.toString();
      } catch (_) {
        const raw = String(docUrl);
        return `${raw}${raw.includes('?') ? '&' : '?'}_ts=${stamp}`;
      }
    })();
    paymentProofReturnTarget = String(returnTarget || '').trim();
    if (paymentProofTitle) {
      paymentProofTitle.textContent = String(title || 'Document Viewer').trim() || 'Document Viewer';
    }
    const proofOnly = String(title || '').toLowerCase().startsWith('proof of residency');
    if (paymentProofReturnBtn) {
      paymentProofReturnBtn.classList.toggle('d-none', !proofOnly && paymentProofReturnTarget === '');
      if (proofOnly) paymentProofReturnBtn.classList.remove('d-none');
    }
    if (paymentProofOpenNew) {
      paymentProofOpenNew.classList.toggle('d-none', proofOnly);
    }
    if (paymentProofCloseBtn) {
      paymentProofCloseBtn.classList.toggle('d-none', proofOnly);
    }
    paymentProofPrintUrl = '';
    paymentProofOpenNew.href = bustedUrl;
    const lower = String(bustedUrl).toLowerCase();
    const isImageAsset = /\.(png|jpe?g|gif|webp|bmp|svg)(\?|#|$)/i.test(lower);
    let isLikelyPdf = lower.endsWith('.pdf');
    try {
      const u = new URL(bustedUrl, window.location.origin);
      const explicitFormat = String(u.searchParams.get('format') || '').toLowerCase();
      if (explicitFormat === 'pdf') {
        isLikelyPdf = true;
      }
    } catch (_) {
      // keep extension-based fallback
    }

    if (!isImageAsset) {
      paymentProofWrap.innerHTML = `<iframe src="${bustedUrl}" style="width:100%;height:70vh;border:1px solid #ddd;border-radius:8px;"></iframe>`;
    } else {
      paymentProofWrap.innerHTML = `<img src="${bustedUrl}" alt="Document Preview" style="max-width:100%;max-height:70vh;border:1px solid #ddd;border-radius:8px;">`;
    }
    if (paymentProofPrintBtn) {
      const allowPrint = !!(options && options.allowPrint);
      paymentProofPrintBtn.classList.toggle('d-none', !(allowPrint && isLikelyPdf && !proofOnly));
      if (allowPrint && isLikelyPdf && !proofOnly) {
        paymentProofPrintUrl = bustedUrl;
      }
    }
    paymentProofModal.show();
  }

  function openSubmittedFileModal(docUrl, title = 'Submitted Attachment Viewer', returnTarget = '') {
    if (!docUrl || !submittedFileModal || !submittedFileWrap || !submittedFileOpenNew) return;
    submittedFileReturnTarget = String(returnTarget || '').trim();
    if (submittedFileTitle) {
      submittedFileTitle.textContent = String(title || 'Submitted Attachment Viewer').trim() || 'Submitted Attachment Viewer';
    }
    if (submittedFileReturnBtn) {
      submittedFileReturnBtn.classList.toggle('d-none', submittedFileReturnTarget === '');
    }
    submittedFileOpenNew.href = docUrl;
    const lower = String(docUrl).toLowerCase();
    const isImageAsset = /\.(png|jpe?g|gif|webp|bmp|svg)(\?|#|$)/i.test(lower);
    if (!isImageAsset) {
      submittedFileWrap.innerHTML = `<iframe src="${docUrl}" style="width:100%;height:70vh;border:1px solid #ddd;border-radius:8px;"></iframe>`;
    } else {
      submittedFileWrap.innerHTML = `<img src="${docUrl}" alt="Submitted File Preview" style="max-width:100%;max-height:70vh;border:1px solid #ddd;border-radius:8px;">`;
    }
    submittedFileModal.show();
  }

  function inlineSubmittedPreviewMarkup(docUrl) {
    const clean = String(docUrl || '').split('?')[0].split('#')[0];
    const ext = clean.split('.').pop().toLowerCase();
    const imgExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];
    if (ext === 'pdf') {
      return `<iframe src="${esc(docUrl)}" title="Submitted File Preview"></iframe>`;
    }
    if (imgExts.includes(ext)) {
      return `<img src="${esc(docUrl)}" alt="Submitted File Preview">`;
    }
    return `
      <div class="submitted-docs-preview__placeholder">
        Preview not available.
        <div class="mt-2">
          <a class="btn btn-sm btn-outline-primary" href="${esc(docUrl)}" target="_blank" rel="noopener">Open File</a>
        </div>
      </div>
    `;
  }

  function openInlineSubmittedPreview(docUrl, docName) {
    if (!viewDetailsBody) return false;
    const previewBody = viewDetailsBody.querySelector('#submittedDocsPreviewBody');
    if (!previewBody) return false;
    const previewName = viewDetailsBody.querySelector('#submittedDocsPreviewName');
    const previewOpen = viewDetailsBody.querySelector('#submittedDocsPreviewOpen');
    previewBody.innerHTML = inlineSubmittedPreviewMarkup(docUrl);
    if (previewName) previewName.textContent = String(docName || 'Submitted Document');
    if (previewOpen) {
      previewOpen.href = docUrl;
      previewOpen.classList.remove('d-none');
    }
    return true;
  }

  function statusBucket(row) {
    if (isFinancePaymentsPage) {
      const stage = String(row?.stage || '').toLowerCase();
      const paymentStatusKey = String(
        firstNonEmpty([row?.payment_status_name, row?.payment_status_label, ''])
      ).toLowerCase().replace(/[^a-z0-9]+/g, '');
      const hasSubmittedProof = String(row?.payment_submitted_at || '').trim() !== ''
        && (
          String(row?.payment_proof_path || '').trim() !== ''
          || String(row?.payment_reference || '').trim() !== ''
        );
      if (paymentStatusKey === 'paymentsubmitted' || paymentStatusKey === 'pendingpaymentverification') return 'pending_verification';
      // "PendingVerification" can be a legacy generic status; only treat it as
      // payment verification once the request is already in payment flow.
      if (paymentStatusKey === 'pendingverification') {
        if (stage === 'payment_submitted' || hasSubmittedProof) return 'pending_verification';
        return 'unpaid';
      }
      if (paymentStatusKey === 'unpaid' || paymentStatusKey === 'pending' || paymentStatusKey === 'pendingreview') return 'unpaid';
      if (paymentStatusKey === 'rejected' || paymentStatusKey === 'denied' || paymentStatusKey === 'paymentrejected') return 'rejected';
      if (paymentStatusKey === 'verified' || paymentStatusKey === 'approved') return 'verified';
      if (paymentStatusKey === 'cancelled' || paymentStatusKey === 'autocancelled' || paymentStatusKey === 'expired') return 'cancelled';
      if (hasSubmittedProof) return 'pending_verification';
      if (stage === 'payment_submitted') return 'pending_verification';
      if (stage === 'for_payment') return 'unpaid';
      if (stage === 'payment_rejected') return 'rejected';
      if (stage === 'cancelled') return 'cancelled';
      if (stage === 'payment_verified' || stage === 'ready_for_claim' || stage === 'completed') return 'verified';
      return 'unpaid';
    }
    const stage = String(row?.stage || '').toLowerCase();
    if (stage.includes('rejected') || stage === 'interview_failed') return 'denied';
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

  function matchesSearchFilter(row) {
    const q = String(searchInput?.value || '').trim().toLowerCase();
    if (!q) return true;
    const payload = row && row.payload && typeof row.payload === 'object' ? row.payload : {};
    const haystack = [
      row?.request_id,
      row?.resident_id,
      row?.document_type,
      row?.purpose,
      row?.stage,
      row?.stage_label,
      row?.payment_status_name,
      row?.payment_status_label,
      row?.payment_method,
      row?.payment_reference,
      row?.resident_name,
      row?.full_name,
      payload?.last_name,
      payload?.first_name,
      payload?.middle_name,
      payload?.full_address
    ].map((v) => String(v || '').toLowerCase()).join(' ');
    return haystack.includes(q);
  }

  function matchesFinanceAdvancedFilters(row) {
    if (!isFinancePaymentsPage) return true;
    if (financeFilterDocumentType && String(row?.document_type || '') !== financeFilterDocumentType) {
      return false;
    }
    if (financeFilterMethod) {
      const method = String(row?.payment_method || '').toLowerCase();
      if (method !== financeFilterMethod) {
        return false;
      }
    }
    return true;
  }

  function hasFinanceTransaction(row) {
    const stage = String(row?.stage || '').toLowerCase();
    if (financeStages.has(stage)) return true;
    const statusId = Number(row?.payment_status_id || 0);
    if (Number.isFinite(statusId) && statusId > 0) return true;
    if (String(row?.payment_status_name || '').trim() !== '') return true;
    if (String(row?.payment_method || '').trim() !== '') return true;
    if (String(row?.payment_deadline || '').trim() !== '') return true;
    if (String(row?.payment_submitted_at || '').trim() !== '') return true;
    if (String(row?.or_number || '').trim() !== '') return true;
    if (row?.amount !== null && row?.amount !== undefined && String(row.amount).trim() !== '') return true;
    return false;
  }

  function matchesStageTabFilter(row, stageFilter) {
    const stage = String(row?.stage || '').toLowerCase();
    const key = String(stageFilter || '').toLowerCase();
    if (!key) return true;
    if (key === 'pending') {
      return (
        stage === 'submitted' ||
        stage === 'for_interview' ||
        stage === 'for_inspection' ||
        stage === 'for_payment' ||
        stage === 'payment_submitted' ||
        stage.includes('pending')
      );
    }
    if (key === 'release') {
      return stage === 'ready_for_claim' || stage.includes('release');
    }
    if (key === 'completed') {
      return stage === 'completed';
    }
    return stage === key;
  }

  function updateStageTabBadges(items) {
    const rows = Array.isArray(items) ? items : [];
    if (pendingTabCount) {
      pendingTabCount.textContent = String(rows.filter((it) => matchesStageTabFilter(it, 'pending')).length);
    }
    if (releaseTabCount) {
      releaseTabCount.textContent = String(rows.filter((it) => matchesStageTabFilter(it, 'release')).length);
    }
  }

  function updateFinanceStatusTabBadges(items) {
    if (!isFinancePaymentsPage) return;
    const rows = Array.isArray(items) ? items : [];
    if (unpaidTabCount) {
      unpaidTabCount.textContent = String(rows.filter((it) => statusBucket(it) === 'unpaid').length);
    }
    if (pendingVerificationTabCount) {
      pendingVerificationTabCount.textContent = String(rows.filter((it) => statusBucket(it) === 'pending_verification').length);
    }
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

  function syncFinanceFilterOptions(items) {
    if (!isFinancePaymentsPage) return;
    if (!financeFilterDocType || !financeFilterPaymentMethod) return;

    const docs = Array.from(new Set(
      (items || [])
        .map((it) => String(it?.document_type || '').trim())
        .filter((v) => v !== '')
    )).sort((a, b) => a.localeCompare(b));

    const methods = Array.from(new Set(
      (items || [])
        .map((it) => String(it?.payment_method || '').trim().toLowerCase())
        .filter((v) => v !== '')
    ));

    financeFilterDocType.innerHTML = '<option value="">All documents</option>';
    docs.forEach((doc) => {
      const opt = document.createElement('option');
      opt.value = doc;
      opt.textContent = doc;
      financeFilterDocType.appendChild(opt);
    });

    const defaultMethodOptions = [
      { value: '', label: 'All payment methods' },
      { value: 'gcash', label: 'GCash' },
      { value: 'barangay', label: 'Pay in Barangay' }
    ];
    financeFilterPaymentMethod.innerHTML = '';
    defaultMethodOptions
      .filter((it) => it.value === '' || methods.includes(it.value))
      .forEach((it) => {
        const opt = document.createElement('option');
        opt.value = it.value;
        opt.textContent = it.label;
        financeFilterPaymentMethod.appendChild(opt);
      });

    financeFilterDocType.value = docs.includes(financeFilterDocumentType) ? financeFilterDocumentType : '';
    financeFilterDocumentType = financeFilterDocType.value;

    const methodAllowed = ['gcash', 'barangay'].includes(financeFilterMethod) && methods.includes(financeFilterMethod);
    financeFilterPaymentMethod.value = methodAllowed ? financeFilterMethod : '';
    financeFilterMethod = financeFilterPaymentMethod.value;
  }

  function getFinanceVisibleColumns() {
    if (!isFinancePaymentsPage) return new Set(defaultFinanceVisibleColumns);
    try {
      const raw = localStorage.getItem(financeColumnsStorageKey);
      if (!raw) return new Set(defaultFinanceVisibleColumns);
      const parsed = JSON.parse(raw);
      if (!Array.isArray(parsed) || !parsed.length) return new Set(defaultFinanceVisibleColumns);
      const normalized = parsed.map((v) => Number(v)).filter((v) => Number.isInteger(v) && v >= 1 && v <= 8);
      if (!normalized.length) return new Set(defaultFinanceVisibleColumns);
      return new Set(normalized);
    } catch (_) {
      return new Set(defaultFinanceVisibleColumns);
    }
  }

  function applyFinanceColumnVisibility() {
    if (!isFinancePaymentsPage) return;
    const table = document.getElementById('table-certificateTracker');
    if (!table) return;
    const visible = getFinanceVisibleColumns();
    for (let i = 1; i <= 8; i += 1) {
      const show = visible.has(i);
      table.querySelectorAll(`tr > *:nth-child(${i})`).forEach((cell) => {
        cell.style.display = show ? '' : 'none';
      });
    }
    financeColChecks.forEach((check) => {
      const idx = Number(check.getAttribute('data-finance-col-index') || '0');
      check.checked = visible.has(idx);
    });
  }

  function saveFinanceVisibleColumnsFromChecks() {
    if (!isFinancePaymentsPage) return;
    const checked = financeColChecks
      .filter((check) => check.checked)
      .map((check) => Number(check.getAttribute('data-finance-col-index') || '0'))
      .filter((v) => Number.isInteger(v) && v >= 1 && v <= 8);
    const value = checked.length ? checked : [1, 2, 3, 4, 5, 6, 7, 8];
    localStorage.setItem(financeColumnsStorageKey, JSON.stringify(value));
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
      const bodyText = String(text || '').trim();
      const lowerBody = bodyText.toLowerCase();
      if (lowerBody.includes('service temporarily unavailable')) {
        throw new Error('Database is temporarily unavailable. Please try again in a moment.');
      }
      if (lowerBody.includes('maximum execution time')) {
        throw new Error('Request timed out while loading data. Please refresh and try again.');
      }
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

  async function fetchRequestDetails(requestId) {
    const id = String(requestId || '').trim();
    if (!id) return null;
    if (detailById.has(id)) {
      return detailById.get(id);
    }
    const q = new URLSearchParams({ action: 'get_request', request_id: id });
    const data = await fetchJson(`${endpoint}?${q.toString()}`);
    const item = data && data.item && typeof data.item === 'object' ? data.item : null;
    if (item) {
      detailById.set(id, item);
    }
    return item;
  }

  function rowHasModalDetails(row) {
    if (!row || typeof row !== 'object') return false;
    const payloadReady = row.payload && typeof row.payload === 'object' && Object.keys(row.payload).length > 0;
    const profileReady = row.resident_profile && typeof row.resident_profile === 'object' && Object.keys(row.resident_profile).length > 0;
    return payloadReady || profileReady;
  }

  async function ensureRowDetails(row) {
    if (!row || typeof row !== 'object') return row;
    const id = String(row.request_id || '').trim();
    if (!id) return row;
    if (rowHasModalDetails(row)) {
      return row;
    }
    try {
      const full = await fetchRequestDetails(id);
      if (full) {
        itemById.set(id, full);
        if (Array.isArray(cachedAllItems)) {
          const idx = cachedAllItems.findIndex((it) => String(it?.request_id || '') === id);
          if (idx >= 0) cachedAllItems[idx] = full;
        }
        return full;
      }
    } catch (_) {
      // Keep lightweight row if details fetch fails.
    }
    return row;
  }

  function renderQuickRequestSummary(row) {
    const docName = normalizeDocumentTypeDisplay(String(row?.document_type || '-'));
    const statusLabel = String(row?.stage_label || row?.stage || '-').trim() || '-';
    const residentName = fullNameFromRow(row) || String(row?.resident_name || '-').trim() || '-';
    const submittedAt = firstNonEmpty([row?.submitted_at, row?.request_timestamp, '-']);
    const purpose = firstNonEmpty([row?.purpose, '-']);
    return `
      <div class="tracker-doc-highlight">Document Requested: ${esc(docName)}</div>
      ${formSection('Request Summary', renderFieldGrid([
        { label: 'Full Name', value: residentName },
        { label: 'Status', value: statusLabel },
        { label: 'Submitted At', value: submittedAt },
        { label: 'Purpose', value: purpose }
      ], 2))}
      <div class="tracker-form-section">
        <div class="text-muted">Loading full details...</div>
      </div>
    `;
  }

  function renderFromCache() {
    const allItems = Array.isArray(cachedAllItems) ? cachedAllItems : [];
    updateStageTabBadges(allItems);
    const stageItems = currentStage === 'finance'
      ? allItems.filter((it) => financeStages.has(String(it.stage || '').toLowerCase()) && hasFinanceTransaction(it))
      : allItems.filter((it) => matchesStageTabFilter(it, currentStage));
    updateFinanceStatusTabBadges(stageItems);
    syncDocumentTypeFilterOptions(stageItems);
    syncFinanceFilterOptions(stageItems);

    const items = stageItems
      .filter(matchesStatusFilter)
      .filter(matchesFinanceAdvancedFilters)
      .filter(matchesDocumentTypeFilter)
      .filter(matchesSearchFilter);

    itemById = new Map(items.map((it) => [String(it.request_id), it]));
    if (!items.length) {
      tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No requests found.</td></tr>';
      return;
    }

    tableBody.innerHTML = items.map(rowHtml).join('');
    applyFinanceColumnVisibility();
    bindActionButtons();
  }

  async function load(options = {}) {
    const force = !!options.force;
    if (!force && Array.isArray(cachedAllItems) && cachedAllItems.length > 0) {
      renderFromCache();
      return;
    }

    tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr>';
    try {
      const params = new URLSearchParams({ action: 'list' });
      params.set('lite', '1');
      if (isFinancePaymentsPage) {
        params.set('list_context', 'finance');
        params.set('limit', '60');
      } else {
        params.set('limit', '70');
      }
      const data = await fetchJson(`${endpoint}?${params.toString()}`);
      if (!data.success) throw new Error(data.message || 'Failed to load requests.');
      cachedAllItems = Array.isArray(data.items) ? data.items : [];
      renderFromCache();
    } catch (err) {
      tableBody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${esc(err.message || err)}</td></tr>`;
    }
  }

  function resetModalFields() {
    modalError.classList.add('d-none');
    modalError.textContent = '';
    if (actionPrompt) {
      actionPrompt.classList.add('d-none');
      actionPrompt.textContent = '';
    }
    actionReasonWrap.classList.add('d-none');
    actionAmountWrap.classList.add('d-none');
    actionOrWrap.classList.add('d-none');
    actionIssuedWrap.classList.add('d-none');
    actionBusinessApprovalWrap?.classList.add('d-none');
    actionPlateWrap?.classList.add('d-none');
    actionReason.required = false;
    actionAmount.required = false;
    actionOr.required = false;
    actionIssued.required = false;
    if (actionBusinessApproval) {
      actionBusinessApproval.required = false;
      actionBusinessApproval.value = '';
    }
    if (actionPlate) {
      actionPlate.required = false;
      actionPlate.value = '';
    }
    actionReason.value = '';
    actionAmount.value = '';
    actionOr.value = '';
    actionIssued.value = '';
    if (actionOr) {
      actionOr.readOnly = false;
    }
    if (actionAmount) {
      actionAmount.readOnly = false;
      actionAmount.classList.remove('bg-light');
    }
    if (actionForm?.dataset?.confirmStep) {
      delete actionForm.dataset.confirmStep;
    }
    if (actionForm?.dataset?.businessApprovalStep) {
      delete actionForm.dataset.businessApprovalStep;
    }
    if (actionForm?.dataset?.businessApprovalType) {
      delete actionForm.dataset.businessApprovalType;
    }
    if (actionForm?.dataset?.businessPlateNumber) {
      delete actionForm.dataset.businessPlateNumber;
    }
    if (actionCancelBtn) {
      actionCancelBtn.textContent = 'Return';
      actionCancelBtn.disabled = false;
    }
    if (actionSubmitBtn) {
      actionSubmitBtn.textContent = 'Submit';
      actionSubmitBtn.classList.remove('btn-danger', 'btn-success');
      actionSubmitBtn.classList.add('btn-primary');
      actionSubmitBtn.disabled = false;
    }
    actionForm?.querySelector('.modal-footer')?.classList.remove('action-split');
  }

  function clearModalError() {
    modalError.classList.add('d-none');
    modalError.textContent = '';
  }

  function configureBusinessApprovalSelectionStep(selectedValue = '', plateValue = '') {
    if (actionForm) {
      actionForm.dataset.businessApprovalStep = 'select';
      if (selectedValue) {
        actionForm.dataset.businessApprovalType = selectedValue;
      } else if (actionForm.dataset.businessApprovalType) {
        delete actionForm.dataset.businessApprovalType;
      }
      actionForm.dataset.businessPlateNumber = String(plateValue || '').trim().toUpperCase();
    }
    if (modalTitle) {
      modalTitle.textContent = 'Type of Approval';
    }
    if (actionPrompt) {
      actionPrompt.textContent = 'Choose the approval type for this Barangay Clearance for Business Permit.';
      actionPrompt.classList.remove('d-none');
    }
    if (actionBusinessApprovalWrap) {
      actionBusinessApprovalWrap.classList.remove('d-none');
    }
    if (actionBusinessApproval) {
      actionBusinessApproval.required = true;
      actionBusinessApproval.value = selectedValue || '';
    }
    if (actionPlateWrap) {
      actionPlateWrap.classList.remove('d-none');
    }
    if (actionPlate) {
      actionPlate.required = false;
      actionPlate.value = String(plateValue || '').trim().toUpperCase();
    }
    if (actionSubmitBtn) {
      actionSubmitBtn.textContent = 'Next';
      actionSubmitBtn.classList.remove('btn-danger', 'btn-success');
      actionSubmitBtn.classList.add('btn-primary');
      actionSubmitBtn.disabled = false;
    }
  }

  function configureBusinessApprovalReviewStep() {
    if (actionForm) {
      actionForm.dataset.businessApprovalStep = 'review';
    }
    if (modalTitle) {
      modalTitle.textContent = 'Before You Approve';
    }
    if (actionPrompt) {
      actionPrompt.textContent = 'Click View Document to check the document that will be issued and edit it if there are necessary changes in the details. Once everything is correct, proceed to verify the Barangay Clearance for Business Permit.';
      actionPrompt.classList.remove('d-none');
    }
    if (actionBusinessApprovalWrap) {
      actionBusinessApprovalWrap.classList.add('d-none');
    }
    if (actionBusinessApproval) {
      actionBusinessApproval.required = false;
    }
    if (actionPlateWrap) {
      actionPlateWrap.classList.add('d-none');
    }
    if (actionPlate) {
      actionPlate.required = false;
    }
    if (actionSubmitBtn) {
      actionSubmitBtn.textContent = 'View Document';
      actionSubmitBtn.classList.remove('btn-danger', 'btn-success');
      actionSubmitBtn.classList.add('btn-primary');
      actionSubmitBtn.disabled = false;
    }
  }

  function openActionModal(type, requestId) {
    if (!actionModal) return;
    if (actionForm?.dataset?.verifyMode) {
      delete actionForm.dataset.verifyMode;
    }

    if (viewModalEl && viewModalEl.classList.contains('show') && viewModal) {
      preserveViewStateOnNextHide = true;
      viewModal.hide();
      actionReturnTarget = 'view';
    } else {
      actionReturnTarget = '';
    }
    suppressActionReturn = false;

    resetModalFields();
    actionType.value = type;
    actionRequestId.value = requestId;
    const row = itemById.get(String(requestId));
    const isFirstTimeJobSeeker = isFirstTimeJobSeekerRow(row);
    if (actionForm) {
      actionForm.dataset.docKey = normalizePreviewDocKey(row?.document_type || '');
    }
    const docKey = String(actionForm?.dataset?.docKey || '');
    const isBusinessClearanceApproval = type === 'personnel_approve' && !isFirstTimeJobSeeker && docKey === 'businessclearance';
    const existingBusinessApprovalType = normalizeBusinessApprovalType(firstNonEmpty([
      viewPreviewState?.businessApprovalType,
      row?.payload?._preview_business_approval_type,
      row?.payload?.business_approval_type
    ]));
    const existingPlateNumber = firstNonEmpty([
      viewPreviewState?.plateNumber,
      row?.payload?._preview_plate_number,
      row?.payload?.plate_number,
      row?.payload?.business_plate_number,
      row?.payload?.vehicle_plate_number
    ]).toUpperCase();

    const rowStage = String(row?.stage || '').toLowerCase();
    const isWalkInFlow = rowStage === 'for_payment' || rowStage === 'payment_rejected';
    const labels = {
      personnel_approve: isFirstTimeJobSeeker ? 'Approve for Interview' : 'Before You Approve',
      personnel_approve_confirm: 'Confirm Approval',
      personnel_reject: 'Reject Request',
      interview_pass: 'Approve Interview',
      interview_fail: 'Fail Interview',
      finance_verify: isWalkInFlow ? 'Record Walk-in Payment' : 'Verify Payment / Walk-in Payment',
      finance_reject: 'Reject Payment',
      mark_ready: 'Mark Ready for Claim',
      mark_completed_confirm: 'Confirm Release',
      mark_completed: 'Mark Completed'
    };
    modalTitle.textContent = labels[type] || 'Update Request';
    const docName = normalizeDocumentTypeDisplay(String(row?.document_type || 'document'));

    if (isBusinessClearanceApproval) {
      configureBusinessApprovalSelectionStep(existingBusinessApprovalType, existingPlateNumber);
      actionModal.show();
      return;
    }

    if (type === 'personnel_approve' && actionPrompt) {
      actionPrompt.textContent = isFirstTimeJobSeeker
        ? 'This request will be moved to the interview stage. The resident will be notified to report to the barangay within 5 working days for the oath of undertaking and interview.'
        : `Click View Document to check the document that will be issued and edit it if there are necessary changes in the details. Once everything is correct, proceed to verify the ${docName}.`;
      actionPrompt.classList.remove('d-none');
    }
    if (type === 'personnel_approve_confirm' && actionPrompt) {
      actionPrompt.textContent = `Please confirm that you thoroughly checked the resident's data to issue a ${docName}.`;
      actionPrompt.classList.remove('d-none');
    }
    if (type === 'mark_completed_confirm' && actionPrompt) {
      actionPrompt.textContent = 'Are you sure you want to release this document and mark this request as completed?';
      actionPrompt.classList.remove('d-none');
    }
    if ((type === 'personnel_reject' || type === 'finance_reject') && actionPrompt) {
      actionPrompt.textContent = 'Please provide the reason for rejection.';
      actionPrompt.classList.remove('d-none');
    }
    if (type === 'interview_pass' && actionPrompt) {
      actionPrompt.textContent = 'Approve the interview result and generate the First Time Job Seeker document for release.';
      actionPrompt.classList.remove('d-none');
    }
    if (type === 'interview_fail' && actionPrompt) {
      actionPrompt.textContent = 'Please provide the reason why the resident did not pass the interview.';
      actionPrompt.classList.remove('d-none');
    }
    if (actionSubmitBtn) {
      if (type === 'personnel_approve') {
        actionSubmitBtn.textContent = isFirstTimeJobSeeker ? 'Approve for Interview' : 'View Document';
        actionSubmitBtn.classList.remove('btn-danger', 'btn-success', 'btn-primary');
        actionSubmitBtn.classList.add(isFirstTimeJobSeeker ? 'btn-success' : 'btn-primary');
      } else if (type === 'personnel_approve_confirm') {
        actionSubmitBtn.textContent = 'Approve';
        actionSubmitBtn.classList.remove('btn-danger', 'btn-primary');
        actionSubmitBtn.classList.add('btn-success');
      } else if (type === 'interview_pass') {
        actionSubmitBtn.textContent = 'Pass Interview';
        actionSubmitBtn.classList.remove('btn-danger', 'btn-primary');
        actionSubmitBtn.classList.add('btn-success');
      } else if (type === 'interview_fail') {
        actionSubmitBtn.textContent = 'Fail Interview';
        actionSubmitBtn.classList.remove('btn-primary', 'btn-success');
        actionSubmitBtn.classList.add('btn-danger');
      } else if (type === 'mark_completed_confirm') {
        actionSubmitBtn.textContent = 'Release';
        actionSubmitBtn.classList.remove('btn-danger', 'btn-primary');
        actionSubmitBtn.classList.add('btn-success');
      } else if (type === 'personnel_reject') {
        actionSubmitBtn.textContent = 'Reject';
        actionSubmitBtn.classList.remove('btn-primary', 'btn-success');
        actionSubmitBtn.classList.add('btn-danger');
      } else if (type === 'finance_verify') {
        actionSubmitBtn.textContent = isWalkInFlow ? 'Record Payment' : 'Verify Payment';
        actionSubmitBtn.classList.remove('btn-danger', 'btn-primary');
        actionSubmitBtn.classList.add('btn-success');
      } else if (type === 'finance_reject') {
        actionSubmitBtn.textContent = 'Reject Payment';
        actionSubmitBtn.classList.remove('btn-primary', 'btn-success');
        actionSubmitBtn.classList.add('btn-danger');
      }
    }
    if (
      type === 'personnel_approve' ||
      type === 'personnel_approve_confirm' ||
      type === 'personnel_reject' ||
      type === 'interview_pass' ||
      type === 'interview_fail' ||
      type === 'mark_completed_confirm'
    ) {
      actionForm?.querySelector('.modal-footer')?.classList.add('action-split');
    }

    if (type === 'personnel_reject' || type === 'finance_reject' || type === 'interview_fail') {
      actionReasonWrap.classList.remove('d-none');
      actionReason.required = true;
    }
    if (type === 'finance_verify') {
      actionAmountWrap.classList.remove('d-none');
      actionOrWrap.classList.remove('d-none');
      actionAmount.required = true;
      actionOr.required = true;
      if (actionPrompt) {
        const financeKey = isFinancePaymentsPage ? statusBucket(row) : '';
        const stage = String(row?.stage || '').toLowerCase();
        const isPendingVerification = isFinancePaymentsPage
          ? financeKey === 'pending_verification'
          : stage === 'payment_submitted';
        const isWalkInStage = isFinancePaymentsPage
          ? financeKey === 'unpaid' || financeKey === 'rejected'
          : stage === 'for_payment' || stage === 'payment_rejected';
        const payload = row && row.payload && typeof row.payload === 'object' ? row.payload : {};
        const residentProfile = row && row.resident_profile && typeof row.resident_profile === 'object'
          ? row.resident_profile
          : {};
        const customerName = fullNameFromRow(row) || '-';
        const customerAddress = firstNonEmpty([
          payload.full_address,
          payload.full_address_display,
          payload.address,
          payload.complete_address,
          residentProfile.full_address,
          row?.full_address,
          row?.address,
          '-'
        ]);
        const method = firstNonEmpty([row?.payment_method, 'GCash']);
        const docNameForPrompt = normalizeDocumentTypeDisplay(firstNonEmpty([row?.document_type, '-']));
        const feeRaw = firstNonEmpty([row?.fee_amount, row?.amount]);
        const feeNumber = Number(feeRaw);
        const feeText = Number.isFinite(feeNumber) ? `PHP ${feeNumber.toFixed(2)}` : '-';
        if (isPendingVerification) {
          actionAmountWrap.classList.add('d-none');
          actionAmount.required = false;
          if (actionAmount) {
            actionAmount.readOnly = true;
            actionAmount.classList.add('bg-light');
          }
          actionPrompt.innerHTML = `
            <div class="small text-muted mb-2">Review transaction before final verification.</div>
            <div class="border rounded p-2 bg-light">
              <div><strong>Full Name:</strong> ${esc(customerName)}</div>
              <div><strong>Full Address:</strong> ${esc(customerAddress)}</div>
              <div><strong>Payment Method:</strong> ${esc(method)}</div>
              <div><strong>Requested Document:</strong> ${esc(docNameForPrompt)}</div>
              <div><strong>Price:</strong> ${esc(feeText)}</div>
            </div>
            <div class="mt-2">Enter the OR Number to continue.</div>
          `;
        } else {
          if (actionAmount) {
            actionAmount.readOnly = false;
            actionAmount.classList.remove('bg-light');
          }
          actionPrompt.textContent = isWalkInStage
            ? 'Record barangay walk-in payment by entering the paid amount and OR number.'
            : 'Verify the submitted payment by entering the official OR number.';
        }
        actionPrompt.classList.remove('d-none');
      }
      if (row) {
        const fixedAmount = firstNonEmpty([row.fee_amount, row.amount]);
        if (fixedAmount !== '') {
          actionAmount.value = String(fixedAmount);
        }
      }
    }
    if (type === 'mark_ready') {
      actionIssuedWrap.classList.add('d-none');
      if (actionIssued) {
        actionIssued.value = '';
      }
      actionPrompt.textContent = 'This will generate the issued document and mark the request as ready for claim.';
      actionPrompt.classList.remove('d-none');
    }

    actionModal.show();
  }

  function bindActionButtons() {
    tableBody.querySelectorAll('button[data-view-id]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const id = String(btn.getAttribute('data-view-id') || '');
        let row = itemById.get(id);
        if (!row || !viewDetailsBody || !viewModal) return;
        try {
          // Open modal immediately, then hydrate heavy details asynchronously.
          switchViewMode('details');
          if (viewModalTitle) {
            const requestId = String(row.request_id || '').trim();
            viewModalTitle.textContent = requestId
              ? (isFinancePaymentsPage ? `Payment Transaction (#${requestId})` : `Certificate Request (#${requestId})`)
              : (isFinancePaymentsPage ? 'Payment Transaction' : 'Certificate Request');
          }
          if (viewModalActions) {
            viewModalActions.innerHTML = '';
          }
          if (viewModalNextBtn) {
            viewModalNextBtn.classList.add('d-none');
          }
          if (viewModalBackBtn) {
            viewModalBackBtn.classList.add('d-none');
          }
          if (viewModalWalkInBtn) {
            viewModalWalkInBtn.classList.add('d-none');
            viewModalWalkInBtn.removeAttribute('data-id');
          }
          if (viewModalDocBtn) {
            viewModalDocBtn.classList.add('d-none');
          }
          const openImmediately = isFinancePaymentsPage || !rowHasModalDetails(row);
          if (openImmediately) {
            viewDetailsBody.innerHTML = renderQuickRequestSummary(row);
            viewModal.show();
          }

          row = await ensureRowDetails(row);
          if (!row) return;

          if (isFinancePaymentsPage) {
          const payload = row.payload && typeof row.payload === 'object' ? row.payload : {};
          const residentProfile = row.resident_profile && typeof row.resident_profile === 'object'
            ? row.resident_profile
            : {};
          const compactFullName = fullNameFromRow(row) || '-';
          const compactAddress = firstNonEmpty([
            payload.full_address,
            payload.full_address_display,
            payload.address,
            payload.complete_address,
            residentProfile.full_address
          ]) || '-';
          const compactDocument = normalizeDocumentTypeDisplay(firstNonEmpty([row.document_type, '-']));
          const financeStatusKey = statusBucket(row);
          const financeStatusLabel = {
            verified: 'Verified',
            rejected: 'Rejected',
            pending_verification: 'Pending Verification',
            cancelled: 'Cancelled',
            unpaid: 'Unpaid'
          }[financeStatusKey] || firstNonEmpty([row.payment_status_name, row.payment_status_label, row.stage_label, row.stage, '-']);

          const compactGrid = renderFieldGrid([
            { label: 'Full Name of Customer', value: compactFullName },
            { label: 'Full Address of Customer', value: compactAddress },
            { label: 'Requested Document', value: compactDocument },
            { label: 'Status', value: financeStatusLabel },
            { label: 'Payment Method', value: firstNonEmpty([row.payment_method, '-']) },
            { label: 'Transaction Number', value: firstNonEmpty([row.payment_reference, '-']) },
            { label: 'Submitted Date', value: firstNonEmpty([row.payment_submitted_at, row.submitted_at, '-']) },
          ], 1);
          const feeRaw = firstNonEmpty([row.fee_amount, row.amount]);
          const feeNumber = Number(feeRaw);
          const feeText = Number.isFinite(feeNumber) ? `PHP ${feeNumber.toFixed(2)}` : '-';
          const pendingSummaryGrid = renderFieldGrid([
            { label: 'Requested Document', value: compactDocument },
            { label: 'Price', value: feeText },
            { label: 'Payment Method', value: firstNonEmpty([row.payment_method, 'GCash']) },
            { label: 'Submitted Date', value: firstNonEmpty([row.payment_submitted_at, row.submitted_at, '-']) },
          ], 3);

          const financeStatusKeyForActions = statusBucket(row);
          const openVerifyFlow = financeViewIntent === 'verify' && financeStatusKeyForActions === 'pending_verification';

          const isVerifiedPayment = financeStatusKeyForActions === 'verified';
          const verifiedAmount = (row.amount !== null && row.amount !== undefined && String(row.amount).trim() !== '')
            ? Number(row.amount)
            : Number(row.fee_amount);
          const verifiedAmountText = Number.isFinite(verifiedAmount)
            ? `PHP ${verifiedAmount.toFixed(2)}`
            : '-';
          const verifiedAtText = firstNonEmpty([row.finance_decision_at, row.payment_submitted_at, '-']);
          const processedByText = firstNonEmptyName([
            row.finance_user_name,
            row.personnel_name,
            row.reviewed_by,
            row.released_by,
            '-'
          ]);
          const verifiedDetailsGrid = isVerifiedPayment
            ? renderFieldGrid([
                { label: 'Requested Document', value: compactDocument },
                { label: 'Processed By', value: processedByText },
                { label: 'OR Number', value: firstNonEmpty([row.or_number, '-']) },
                { label: 'Price', value: verifiedAmountText },
                { label: 'Transaction Number', value: firstNonEmpty([row.payment_reference, '-']) },
                { label: 'Submitted Date', value: firstNonEmpty([row.payment_submitted_at, row.submitted_at, '-']) },
                { label: 'Date Verified', value: verifiedAtText },
              ], 3)
            : '';
          const paymentProofUrl = resolvePaymentProofUrl(row, String(row.request_id || ''));
          const paymentProofHtml = paymentProofUrl
            ? `<div class="tracker-form-grid cols-1 mt-2">
                 <div class="tracker-form-field">
                   <p class="tracker-form-label">Proof of Payment</p>
                   <div class="tracker-form-value">
                     <iframe
                       src="${paymentProofUrl}"
                       style="width:100%;height:420px;border:1px solid #ddd;border-radius:8px;background:#fff;"
                       loading="lazy"
                     ></iframe>
                   </div>
                 </div>
               </div>`
            : '';

          const verifyActionButtons = `
            <div class="tracker-status-actions">
              <button class="btn btn-sm btn-success" data-inline-action="finance_verify_gcash" data-id="${esc(row.request_id)}">Verify Payment</button>
              <button class="btn btn-sm btn-danger" data-inline-action="finance_reject" data-id="${esc(row.request_id)}">Reject Payment</button>
            </div>
          `;

          if (openVerifyFlow) {
            viewDetailsHtml = `<div class="tracker-doc-highlight">Transaction Details</div>`
              + (paymentProofHtml ? formSection('Submitted Proof of Payment', paymentProofHtml) : '')
              + formSection('Payment Transaction Information', `${pendingSummaryGrid || ''}${verifyActionButtons}`);
          } else {
            viewDetailsHtml = `<div class="tracker-doc-highlight">Transaction Details</div>`
              + formSection('Payment Transaction Information', `${compactGrid}`)
              + (isVerifiedPayment
                  ? formSection('Payment Transaction Summary', `${verifiedDetailsGrid || ''}`)
                  : '');
          }
          financeViewIntent = 'view';
          switchViewMode('details');
          if (viewModalTitle) {
            const requestId = String(row.request_id || '').trim();
            viewModalTitle.textContent = requestId ? `Payment Transaction (#${requestId})` : 'Payment Transaction';
          }
          currentViewRequestId = String(row.request_id || '').trim();
          currentViewStage = String(row.stage || '').toLowerCase();
          if (viewModalActions) {
            viewModalActions.innerHTML = '';
          }
          if (viewModalDocBtn) {
            viewModalDocBtn.classList.remove('d-none');
            viewModalDocBtn.onclick = () => {
              viewPreviewState = buildPreviewState(row, payload, residentProfile, null);
              switchViewMode('preview');
            };
          }
          if (viewModalNextBtn) {
            viewModalNextBtn.classList.add('d-none');
          }
          if (viewModalBackBtn) {
            viewModalBackBtn.classList.add('d-none');
          }
          if (viewModalWalkInBtn) {
            const isUnpaidStage = financeStatusKeyForActions === 'unpaid' || financeStatusKeyForActions === 'rejected';
            if (isUnpaidStage) {
              viewModalWalkInBtn.classList.remove('d-none');
              viewModalWalkInBtn.setAttribute('data-id', String(row.request_id || ''));
            } else {
              viewModalWalkInBtn.classList.add('d-none');
              viewModalWalkInBtn.removeAttribute('data-id');
            }
          }
          viewDetailsBody.querySelectorAll('button[data-inline-action][data-id]').forEach((actionBtn) => {
            actionBtn.addEventListener('click', () => {
              const action = String(actionBtn.getAttribute('data-inline-action') || '').trim();
              const actionId = String(actionBtn.getAttribute('data-id') || '').trim();
              if (!actionId) return;
              if (action === 'finance_walkin') {
                openActionModal('finance_verify', actionId);
                if (actionForm) actionForm.dataset.verifyMode = 'walkin';
                return;
              }
              if (action === 'finance_verify_gcash') {
                openActionModal('finance_verify', actionId);
                if (actionForm) actionForm.dataset.verifyMode = 'gcash';
                return;
              }
              if (action === 'finance_reject') {
                openActionModal('finance_reject', actionId);
              }
            });
          });
            viewModal.show();
            return;
          }

          const payload = row.payload && typeof row.payload === 'object' ? row.payload : {};
          const residentProfile = row.resident_profile && typeof row.resident_profile === 'object'
            ? row.resident_profile
            : {};
          const isRelationshipJailVisit = String(payload?.cohabitation_variant || '').trim() === 'relationship_jail_visit'
            || String(payload?.cohabitation_variant || '').trim() === 'conjugal_visit';
          const consumedKeys = new Set();
          const collectFirst = (...keys) => {
            for (const key of keys) {
              if (!key) continue;
              const value = payload[key];
              if (value === null || value === undefined) continue;
              const text = String(value).trim();
              if (text === '') continue;
              consumedKeys.add(String(key));
              return text;
            }
            return '';
          };
          const collectResidentFirst = (...keys) => {
            for (const key of keys) {
              if (!key) continue;
              const value = residentProfile[key];
              if (value === null || value === undefined) continue;
              const text = String(value).trim();
              if (text === '') continue;
              return text;
            }
            return '';
          };

        const personalFields = [
          { label: 'Last Name', value: firstNonEmpty([collectFirst('last_name', 'lastname'), collectResidentFirst('last_name')]) },
          { label: 'First Name', value: firstNonEmpty([collectFirst('first_name', 'firstname'), collectResidentFirst('first_name')]) },
          { label: 'Middle Name', value: firstNonEmpty([collectFirst('middle_name', 'middlename'), collectResidentFirst('middle_name')]) },
          { label: 'Suffix', value: firstNonEmpty([collectFirst('suffix_name', 'suffix'), collectResidentFirst('suffix')]) },
          { label: 'Contact Number', value: firstNonEmpty([collectFirst('contact_number', 'phone_number'), collectResidentFirst('contact_number')]) },
          { label: 'Full Address', value: firstNonEmpty([collectFirst('full_address', 'full_address_display', 'address', 'complete_address'), collectResidentFirst('full_address')]) },
          { label: 'Birthdate', value: firstNonEmpty([collectFirst('birthdate', 'date_of_birth', 'child_dob'), collectResidentFirst('birthdate')]) },
          { label: 'Age', value: firstNonEmpty([collectFirst('age'), collectResidentFirst('age')]) },
          { label: 'Sex', value: firstNonEmpty([collectFirst('sex', 'gender', 'child_sex'), collectResidentFirst('sex')]) },
          { label: 'Civil Status', value: firstNonEmpty([collectFirst('civil_status'), collectResidentFirst('civil_status')]) },
          { label: 'Religion', value: firstNonEmpty([collectFirst('religion'), collectResidentFirst('religion')]) },
          { label: 'Occupation', value: firstNonEmpty([collectFirst('occupation'), collectResidentFirst('occupation')]) }
        ];

        const technicalKeys = new Set([
          'action', 'csrf_token', 'redirect', 'document_type', 'suffix_name_display', 'suffix_display',
          'child_sex_display', 'cohabitant_region_select', 'cohabitant_province_select',
          'cohabitant_city_select', 'cohabitant_barangay_select', 'cohabitantSameAddress',
          'full_unit_number', 'full_house_lot_number', 'full_street_block_name', 'full_subdivision',
          'full_barangay', 'full_area_number', 'cohabitant_full_unit_number',
          'cohabitant_full_house_lot_number', 'cohabitant_full_street_block_name',
          'cohabitant_full_subdivision', 'cohabitant_full_barangay', 'cohabitant_full_area_number',
          'request_purpose', 'requestPurpose', 'purpose_choice', 'purposeChoice',
          'submission_target_type', 'government_official_id', 'government_position_group',
          'government_position_detail', 'government_official', 'government_office',
          'government_position', 'request_officer_line1', 'request_officer_line2', 'request_officer_line3',
          'cohabitation_variant', 'cohabitant_id_front_path', 'cohabitant_id_back_path',
          'detention_proof_file_path', 'detention_proof_file_paths',
          'relationship_proof_file_path', 'relationship_proof_file_paths',
          'valid_id_file_path', 'or_vehicle_file_path', 'cr_vehicle_file_path',
          'toda_poda_cert_file_path', 'authorization_vehicle_file_path', 'deed_of_sale_file_path',
          'last_year_clearance_file_path', 'business_reg_file_path', 'proof_address_file_path',
          'business_photo_file_path', 'renewal_valid_id_file_path', 'renewal_business_reg_file_path',
          'renewal_proof_address_file_path'
        ]);

        const requestFields = [];
        const purposeText = firstNonEmpty([row.purpose, payload.purpose, payload.request_purpose]);
        if (purposeText) {
          consumedKeys.add('purpose');
          consumedKeys.add('request_purpose');
          consumedKeys.add('requestPurpose');
          consumedKeys.add('purpose_choice');
          consumedKeys.add('purposeChoice');
          requestFields.push({ label: 'Purpose', value: purposeText });
        }

        const officialNameText = firstNonEmpty([payload.government_official, payload.request_officer_line1]);
        const positionText = firstNonEmpty([payload.government_position, payload.government_position_detail, payload.request_officer_line2]);
        const jurisdictionText = firstNonEmpty([payload.government_office, payload.government_position_group, payload.request_officer_line3]);
        if (officialNameText || positionText || jurisdictionText) {
          consumedKeys.add('government_official');
          consumedKeys.add('government_position');
          consumedKeys.add('government_position_detail');
          consumedKeys.add('government_office');
          consumedKeys.add('government_position_group');
          consumedKeys.add('request_officer_line1');
          consumedKeys.add('request_officer_line2');
          consumedKeys.add('request_officer_line3');
          consumedKeys.add('request_officer');

          if (officialNameText) {
            requestFields.push({ label: 'Official Name', value: officialNameText });
          }
          if (positionText) {
            requestFields.push({ label: 'Position', value: positionText });
          }
          if (jurisdictionText) {
            requestFields.push({ label: 'Jurisdiction', value: jurisdictionText });
          }
        } else {
          const officerText = firstNonEmpty([payload.request_officer]);
          if (officerText) {
            consumedKeys.add('request_officer');
            requestFields.push({ label: 'To Be Submitted To', value: officerText });
          }
        }

        const paymentMethodText = firstNonEmpty([row.payment_method]);
        if (paymentMethodText) {
          requestFields.push({ label: 'Payment Method', value: String(paymentMethodText).toUpperCase() });
        }
        const paymentReferenceText = firstNonEmpty([row.payment_reference]);
        if (paymentReferenceText) {
          requestFields.push({ label: 'GCash Transaction Number', value: paymentReferenceText });
        }
        if (isRelationshipJailVisit) {
          const detentionProofTypeText = firstNonEmpty([payload.detention_proof_type]);
          if (detentionProofTypeText) {
            consumedKeys.add('detention_proof_type');
            requestFields.push({ label: 'Proof of Detention Type', value: detentionProofTypeText });
          }
        } else {
          const cohabitantIdTypeText = firstNonEmpty([payload.cohabitant_id_type]);
          if (cohabitantIdTypeText) {
            consumedKeys.add('cohabitant_id_type');
            requestFields.push({ label: 'Partner ID Type', value: cohabitantIdTypeText });
          }
          const cohabitantIdNumberText = firstNonEmpty([payload.cohabitant_id_number]);
          if (cohabitantIdNumberText) {
            consumedKeys.add('cohabitant_id_number');
            requestFields.push({ label: 'Partner ID Number', value: cohabitantIdNumberText });
          }
        }

        Object.keys(payload).forEach((key) => {
          const normalized = String(key);
          if (consumedKeys.has(normalized) || technicalKeys.has(normalized)) return;
          const value = payload[key];
          if (value === null || value === undefined) return;
          const text = String(value).trim();
          if (looksLikeFilePath(normalized, text)) return;
          if (text === '') return;
          requestFields.push({ label: friendlyLabel(normalized), value: text });
        });

        let html = '';
        html += `<div class="tracker-doc-highlight">Document Requested: ${esc(normalizeDocumentTypeDisplay(row.document_type || '-'))}</div>`;
        const personalMap = new Map(personalFields.map((f) => [f.label, f.value]));
        const nameGrid = renderFieldGrid(personalFields.filter((f) => ['Last Name', 'First Name', 'Middle Name', 'Suffix'].includes(f.label)), 4);
        const profileGrid = renderFieldGrid(personalFields.filter((f) => ['Birthdate', 'Age', 'Sex', 'Civil Status'].includes(f.label)), 4);
        const contactGrid = renderFieldGrid(personalFields.filter((f) => ['Contact Number', 'Full Address'].includes(f.label)), 2);
        const extraGrid = renderFieldGrid(personalFields.filter((f) => ['Religion', 'Occupation'].includes(f.label)), 2);
        const proofResidencyPath = firstNonEmpty([
          residentProfile.proof_residency_path
        ]);
        const proofResidencyName = firstNonEmpty([
          residentProfile.proof_residency_name,
          proofResidencyPath ? String(proofResidencyPath).split('/').pop() : ''
        ]);
        const proofResidencyUrl = resolvePublicUrl(proofResidencyPath);
        const proofResidencyType = firstNonEmpty([residentProfile.proof_residency_type, 'Document']);
        const proofResidencyIdNo = firstNonEmpty([residentProfile.proof_residency_id_number]);
        const proofResidencyTitle = `Proof of Residency - ${proofResidencyType}${proofResidencyIdNo ? ` #${proofResidencyIdNo}` : ''}`;
        const proofResidencyHtml = proofResidencyUrl
          ? `<div class="tracker-form-grid cols-1">
               <div class="tracker-form-field">
                 <p class="tracker-form-label">Proof of Residency File</p>
                 <div class="tracker-form-value d-flex justify-content-end">
                   <button type="button" class="btn btn-sm btn-primary" data-support-doc-url="${esc(proofResidencyUrl)}" data-support-doc-title="${esc(proofResidencyTitle)}">View</button>
                 </div>
               </div>
             </div>`
          : '';
        const personalHtml = [nameGrid, profileGrid, contactGrid, extraGrid, proofResidencyHtml].filter(Boolean).join('');
        if (personalHtml) {
          const residentId = firstNonEmpty([row.resident_id, residentProfile.resident_id]);
          const residentUserId = firstNonEmpty([row.resident_user_id, row.user_id, residentProfile.resident_user_id, residentProfile.user_id]);
          const personalAction = (residentId || residentUserId)
            ? `<button type="button" class="btn btn-sm btn-primary" data-inline-profile-id="${esc(residentId)}" data-inline-profile-user-id="${esc(residentUserId)}">View Profile</button>`
            : '';
          html += formSection('Personal Information', personalHtml, personalAction);
        }
        const reqGrid = renderFieldGrid(requestFields, 3);
        if (reqGrid) {
          html += formSection('Request Details', reqGrid);
        }

        const submittedDocs = extractSubmittedDocuments(row, payload);
        if (submittedDocs.length) {
          const docsHtml = submittedDocs.map((doc, idx) => `
            <div class="submitted-docs-item">
              <div class="submitted-docs-item__label">${esc(doc.label || `Document ${idx + 1}`)}</div>
              <div class="submitted-docs-item__meta justify-content-end">
                <button
                  type="button"
                  class="btn btn-sm btn-primary"
                  data-support-doc-url="${esc(doc.url)}"
                  data-support-doc-title="${esc(doc.label || 'Submitted Document')}"
                  data-support-doc-name="${esc(doc.name || '')}"
                >
                  View
                </button>
              </div>
            </div>
          `).join('');
          const previewHtml = `
            <div class="submitted-docs-preview" id="submittedDocsPreview">
              <div class="submitted-docs-preview__header">
                <span class="submitted-docs-preview__name text-truncate" id="submittedDocsPreviewName">Select a document</span>
                <a class="btn btn-sm btn-outline-primary d-none" id="submittedDocsPreviewOpen" target="_blank" rel="noopener">Open</a>
              </div>
              <div class="submitted-docs-preview__body" id="submittedDocsPreviewBody">
                <div class="submitted-docs-preview__placeholder">Select a file to preview.</div>
              </div>
            </div>
          `;
          html += formSection(
            'Submitted Documents',
            `<div class="submitted-docs-grid">
               <div class="submitted-docs-list">${docsHtml}</div>
               ${previewHtml}
             </div>`
          );
        }

        const stageKeyForStatus = String(row.stage || '').toLowerCase();
        if (!isFinancePaymentsPage && stageKeyForStatus === 'completed') {
          const completedIssuedUrl = `${appBase}/PhpFiles/Admin-End/documentRequestWorkflow.php?action=view_issued&request_id=${encodeURIComponent(String(row.request_id || ''))}`;
          const issuedViewerHtml = `
            <div class="tracker-form-grid cols-1">
              <div class="tracker-form-field">
                <p class="tracker-form-label">Issued Document</p>
                <div class="tracker-form-value d-flex justify-content-between align-items-center gap-2">
                  <span>Open the issued document only when needed.</span>
                  <button type="button" class="btn btn-sm btn-primary" data-view-doc-url="${esc(completedIssuedUrl)}" data-view-doc-title="Issued Document">View</button>
                </div>
              </div>
            </div>
          `;
          const issuedActionHtml = `<a class="btn btn-sm btn-outline-primary" href="${completedIssuedUrl}" target="_blank" rel="noopener">Open in New Tab</a>`;
          html += formSection('Issued Document', issuedViewerHtml, issuedActionHtml);
        }

        const isRejectedStatus = stageKeyForStatus.includes('rejected') || stageKeyForStatus === 'interview_failed' || stageKeyForStatus === 'cancelled';
        const statusLabelText = String(row.stage_label || row.stage || '-');
        const statusReasonText = firstNonEmpty([row.status_remarks, row.status_reason]);
        const statusBadgeHtml = isRejectedStatus
          ? `<span style="display:inline-block;padding:4px 10px;border-radius:6px;background:#fee2e2;color:#b91c1c;font-weight:700;line-height:1.2;">${esc(statusLabelText)}</span>`
          : esc(statusLabelText);
        const statusReasonHtml = statusReasonText
          ? (isRejectedStatus
              ? `<span style="color:#b91c1c;font-weight:700;">${esc(statusReasonText)}</span>`
              : esc(statusReasonText))
          : (isRejectedStatus ? '<span style="color:#b91c1c;">No reason provided.</span>' : '-');

        const statusGrid = renderFieldGrid([
          { label: 'Status', value: statusBadgeHtml, raw: true },
          { label: isRejectedStatus ? 'Rejection Reason' : 'Reason', value: statusReasonHtml, raw: true },
          { label: 'Submitted At', value: row.submitted_at || '-' },
          {
            label: 'Approved By',
            value: firstNonEmptyName([
              row.reviewed_by,
              row.personnel_name,
              row.finance_user_name,
              '-'
            ])
          },
          {
            label: 'Released By',
            value: firstNonEmptyName([
              row.released_by,
              row.personnel_name,
              '-'
            ])
          }
        ], 3);
        if (statusGrid) {
          const modalActionsHtml = viewModalActionButtons(row);
          const isPendingStage = String(row.stage || '').toLowerCase() === 'submitted';
          const statusActions = (modalActionsHtml && !modalActionsHtml.includes('No actions'))
            ? `<div class="tracker-status-actions${isPendingStage ? ' tracker-status-actions--split' : ''}">${modalActionsHtml}</div>`
            : '';
          html += formSection('Request Status', `${statusGrid}${statusActions}`);
        }

        viewDetailsHtml = html || '<div class="text-muted">No details.</div>';
        viewPreviewState = buildPreviewState(row, payload, residentProfile, personalMap);
        switchViewMode('details');
        const stageKey = String(row.stage || '').toLowerCase();
        const nextEnabled = !(
          stageKey === 'submitted' ||
          stageKey.includes('rejected') ||
          stageKey === 'cancelled'
        );
        if (viewModalNextBtn) {
          viewModalNextBtn.disabled = !nextEnabled;
          viewModalNextBtn.classList.add('d-none');
        }
        if (viewModalTitle) {
          const requestId = String(row.request_id || '').trim();
          currentViewRequestId = requestId;
          currentViewStage = String(row.stage || '').toLowerCase();
          viewModalTitle.textContent = requestId ? `Certificate Request (#${requestId})` : 'Certificate Request';
        }
        if (viewModalWalkInBtn) {
          viewModalWalkInBtn.classList.add('d-none');
          viewModalWalkInBtn.removeAttribute('data-id');
        }
        if (viewModalActions) {
          viewModalActions.innerHTML = '';
        }
        viewDetailsBody.querySelectorAll('.tracker-status-actions button[data-view-action][data-id]').forEach((actionBtn) => {
          actionBtn.addEventListener('click', () => {
            const action = String(actionBtn.getAttribute('data-view-action') || '').trim();
            const actionId = String(actionBtn.getAttribute('data-id') || '').trim();
            if (action === 'mark_completed') {
              openActionModal('mark_completed_confirm', actionId);
              return;
            }
            openActionModal(action, actionId);
          });
        });
        viewDetailsBody.querySelectorAll('.tracker-status-actions button[data-proof-id]').forEach((proofBtn) => {
          proofBtn.addEventListener('click', () => {
            const proofId = String(proofBtn.getAttribute('data-proof-id') || '');
            const proofRow = itemById.get(proofId);
            if (!proofRow || !proofRow.payment_proof_path || !paymentProofModal || !paymentProofWrap || !paymentProofOpenNew) return;
            const proofUrl = resolvePaymentProofUrl(proofRow, proofId);
            if (viewModalEl && viewModalEl.classList.contains('show') && viewModal) {
              preserveViewStateOnNextHide = true;
              viewModal.hide();
            }
            openDocumentModal(proofUrl, 'Payment Proof', 'view');
          });
        });
        viewDetailsBody.querySelectorAll('button[data-view-doc-url]').forEach((docBtn) => {
          docBtn.addEventListener('click', () => {
            const docUrl = String(docBtn.getAttribute('data-view-doc-url') || '').trim();
            const docTitle = String(docBtn.getAttribute('data-view-doc-title') || 'Document Viewer').trim();
            if (!docUrl || !paymentProofModal || !paymentProofWrap || !paymentProofOpenNew) return;
            if (viewModalEl && viewModalEl.classList.contains('show') && viewModal) {
              preserveViewStateOnNextHide = true;
              viewModal.hide();
            }
            openDocumentModal(docUrl, docTitle, 'view');
          });
        });
        viewDetailsBody.querySelectorAll('button[data-support-doc-url]').forEach((docBtn) => {
          docBtn.addEventListener('click', () => {
            const docUrl = String(docBtn.getAttribute('data-support-doc-url') || '').trim();
            const docTitle = String(docBtn.getAttribute('data-support-doc-title') || 'Submitted Attachment Viewer').trim();
            const docName = String(docBtn.getAttribute('data-support-doc-name') || '').trim();
            if (!docUrl) return;
            if (openInlineSubmittedPreview(docUrl, docName || docTitle)) {
              return;
            }
            if (!submittedFileModal || !submittedFileWrap || !submittedFileOpenNew) return;
            if (viewModalEl && viewModalEl.classList.contains('show') && viewModal) {
              preserveViewStateOnNextHide = true;
              viewModal.hide();
            }
            openSubmittedFileModal(docUrl, docTitle, 'view');
          });
        });
        viewDetailsBody.querySelectorAll('button[data-inline-profile-id]').forEach((profileBtn) => {
          profileBtn.addEventListener('click', () => {
            openResidentProfileModal(
              String(profileBtn.getAttribute('data-inline-profile-id') || ''),
              String(profileBtn.getAttribute('data-inline-profile-user-id') || ''),
              row?.resident_profile && typeof row.resident_profile === 'object' ? row.resident_profile : null
            );
          });
        });
          if (openViewDirectPreview) {
            switchViewMode('preview');
            openViewDirectPreview = false;
          }
          viewModal.show();
        } catch (err) {
          const message = String(err?.message || err || 'Failed to load request details.');
          viewDetailsHtml = `<div class="tracker-form-section"><div class="text-danger">${esc(message)}</div></div>`;
          switchViewMode('details');
          viewModal.show();
          console.error('Failed to open request view modal:', err);
        }
      });
    });

    tableBody.querySelectorAll('button[data-preview-id]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = String(btn.getAttribute('data-preview-id') || '');
        if (!id) return;
        const viewBtn = Array.from(tableBody.querySelectorAll('button[data-view-id]'))
          .find((candidate) => String(candidate.getAttribute('data-view-id') || '') === id);
        if (!viewBtn) return;
        openViewDirectPreview = true;
        viewBtn.click();
      });
    });

    tableBody.querySelectorAll('button[data-issued-id]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = String(btn.getAttribute('data-issued-id') || '');
        if (!id) return;
        const row = itemById.get(id);
        const stageKey = String(row?.stage || '').toLowerCase();
        const allowPrint = stageKey === 'ready_for_claim';
        const issuedUrl = `${appBase}/PhpFiles/Admin-End/documentRequestWorkflow.php?action=view_issued&request_id=${encodeURIComponent(id)}`;
        openDocumentModal(issuedUrl, 'Issued Document', '', { allowPrint });
      });
    });

    tableBody.querySelectorAll('button[data-proof-id]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = String(btn.getAttribute('data-proof-id') || '');
        const row = itemById.get(id);
        if (!row || !row.payment_proof_path || !paymentProofModal || !paymentProofWrap || !paymentProofOpenNew) return;

        const proofUrl = resolvePaymentProofUrl(row, id);
        openDocumentModal(proofUrl, 'Payment Proof', '');
      });
    });

    tableBody.querySelectorAll('button[data-inline-action][data-id]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = String(btn.getAttribute('data-id') || '').trim();
        const action = String(btn.getAttribute('data-inline-action') || '').trim();
        if (!id) return;
        if (action === 'finance_walkin') {
          openActionModal('finance_verify', id);
          if (actionForm) actionForm.dataset.verifyMode = 'walkin';
          return;
        }
        if (action === 'finance_verify_gcash') {
          const viewBtn = Array.from(tableBody.querySelectorAll('button[data-view-id]'))
            .find((candidate) => String(candidate.getAttribute('data-view-id') || '') === id);
          if (viewBtn) {
            financeViewIntent = 'verify';
            viewBtn.click();
            return;
          }
          openActionModal('finance_verify', id);
          if (actionForm) actionForm.dataset.verifyMode = 'gcash';
          return;
        }
        if (action === 'finance_reject') {
          openActionModal('finance_reject', id);
        }
      });
    });

  }

  actionForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearModalError();

    const currentActionValue = String(actionType.value || '');
    const currentDocKey = String(actionForm?.dataset?.docKey || '');
    const businessApprovalStep = String(actionForm?.dataset?.businessApprovalStep || '');
    if (currentActionValue === 'personnel_approve' && currentDocKey === 'businessclearance' && businessApprovalStep === 'select') {
      const selectedApprovalType = normalizeBusinessApprovalType(actionBusinessApproval?.value || '');
      const selectedPlateNumber = String(actionPlate?.value || '').trim().toUpperCase();
      if (!selectedApprovalType) {
        modalError.textContent = 'Please select the type of approval first.';
        modalError.classList.remove('d-none');
        return;
      }
      if (actionForm) {
        actionForm.dataset.businessApprovalType = selectedApprovalType;
        actionForm.dataset.businessPlateNumber = selectedPlateNumber;
      }
      configureBusinessApprovalReviewStep();
      return;
    }

    if ((actionType.value || '') === 'personnel_approve' && String(actionForm?.dataset?.docKey || '') !== 'firsttimejobseeker') {
      // "View Document" only opens preview; it does not approve yet.
      const rid = String(actionRequestId.value || '').trim();
      if (String(actionForm?.dataset?.docKey || '') === 'businessclearance') {
        const selectedApprovalType = normalizeBusinessApprovalType(
          actionForm?.dataset?.businessApprovalType || actionBusinessApproval?.value || ''
        );
        const selectedPlateNumber = String(
          actionForm?.dataset?.businessPlateNumber || actionPlate?.value || ''
        ).trim().toUpperCase();
        if (!selectedApprovalType) {
          modalError.textContent = 'Please select the type of approval first.';
          modalError.classList.remove('d-none');
          return;
        }
        if (!viewPreviewState || typeof viewPreviewState !== 'object') {
          const previewRow = itemById.get(rid) || {};
          const previewPayload = previewRow?.payload && typeof previewRow.payload === 'object' ? previewRow.payload : {};
          const previewProfile = previewRow?.resident_profile && typeof previewRow.resident_profile === 'object'
            ? previewRow.resident_profile
            : {};
          viewPreviewState = buildPreviewState(previewRow, previewPayload, previewProfile, null);
        }
        if (viewPreviewState && typeof viewPreviewState === 'object') {
          viewPreviewState.businessApprovalType = selectedApprovalType;
          viewPreviewState.plateNumber = selectedPlateNumber;
        }
      }
      if (actionSubmitBtn) {
        actionSubmitBtn.disabled = true;
        actionSubmitBtn.textContent = 'Loading Document...';
      }
      if (actionCancelBtn) {
        actionCancelBtn.disabled = true;
      }
      fetchTemplatePreviewAsset(rid).catch((err) => {
        console.error('initial template preview preload failed', err);
      });
      window.setTimeout(() => {
        suppressActionReturn = true;
        openPreviewAfterActionModal = true;
        actionModal.hide();
      }, 3000);
      return;
    }

    const currentAction = String(actionType.value || '');
    const apiAction = currentAction === 'personnel_approve_confirm'
      ? 'personnel_approve'
      : (currentAction === 'mark_completed_confirm' ? 'mark_completed' : currentAction);

    const fd = new FormData();
    fd.append('action', apiAction);
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
    if (currentAction === 'finance_verify' && actionForm?.dataset?.verifyMode) {
      fd.append('verify_mode', String(actionForm.dataset.verifyMode));
    }
    if (actionIssuedWrap && !actionIssuedWrap.classList.contains('d-none') && actionIssued.files?.[0]) {
      fd.append('issued_file', actionIssued.files[0]);
    }
    if (currentAction === 'personnel_approve_confirm' && viewPreviewState && typeof viewPreviewState === 'object') {
      fd.append('edited_preview', JSON.stringify(viewPreviewState));
    }

    if (currentAction === 'finance_verify' && actionForm?.dataset?.verifyMode === 'gcash') {
      const orValue = String(actionOr?.value || '').trim();
      if (!orValue) {
        modalError.textContent = 'OR Number is required.';
        modalError.classList.remove('d-none');
        return;
      }
    }

    const prevSubmitLabel = actionSubmitBtn ? actionSubmitBtn.textContent : '';
    if (actionSubmitBtn) {
      actionSubmitBtn.disabled = true;
      actionSubmitBtn.textContent = 'Processing...';
    }
    if (actionCancelBtn) {
      actionCancelBtn.disabled = true;
    }

    try {
      const data = await fetchJson(endpoint, {
        method: 'POST',
        body: fd
      });
      if (!data.success) throw new Error(data.message || 'Action failed');

      suppressActionReturn = true;
      actionModal.hide();
      // Keep action submit responsive; refresh table in background.
      load({ force: true });
    } catch (err) {
      if (actionSubmitBtn) {
        actionSubmitBtn.disabled = false;
        actionSubmitBtn.textContent = prevSubmitLabel || 'Submit';
      }
      if (actionCancelBtn) {
        actionCancelBtn.disabled = false;
      }
      modalError.textContent = err.message || String(err);
      modalError.classList.remove('d-none');
    } finally {
      if (actionForm?.dataset?.verifyMode) {
        delete actionForm.dataset.verifyMode;
      }
      if (actionForm?.dataset?.docKey) {
        delete actionForm.dataset.docKey;
      }
    }
  });

  actionCancelBtn?.addEventListener('click', () => {
    suppressActionReturn = false;
  });

  actionModalEl?.addEventListener('hidden.bs.modal', () => {
    if (actionForm?.dataset?.docKey) {
      delete actionForm.dataset.docKey;
    }
    if (openPreviewAfterActionModal) {
      openPreviewAfterActionModal = false;
      if (viewModal) {
        switchViewMode('preview');
        viewModal.show();
      }
      actionReturnTarget = '';
      suppressActionReturn = false;
      return;
    }
    if (!suppressActionReturn && actionReturnTarget === 'view' && viewModal) {
      viewModal.show();
    }
    actionReturnTarget = '';
    suppressActionReturn = false;
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

  btnFinanceFilterApply?.addEventListener('click', () => {
    financeFilterDocumentType = String(financeFilterDocType?.value || '');
    financeFilterMethod = String(financeFilterPaymentMethod?.value || '').toLowerCase();
    const modalEl = document.getElementById('modalFinanceFilter');
    const modalInstance = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;
    modalInstance?.hide();
    load();
  });

  btnFinanceFilterReset?.addEventListener('click', () => {
    financeFilterDocumentType = '';
    financeFilterMethod = '';
    if (financeFilterDocType) financeFilterDocType.value = '';
    if (financeFilterPaymentMethod) financeFilterPaymentMethod.value = '';
    load();
  });

  financeColChecks.forEach((check) => {
    check.addEventListener('change', () => {
      saveFinanceVisibleColumnsFromChecks();
      applyFinanceColumnVisibility();
    });
  });

  btnFinanceColumnsReset?.addEventListener('click', () => {
    localStorage.setItem(financeColumnsStorageKey, JSON.stringify(defaultFinanceVisibleColumns));
    applyFinanceColumnVisibility();
  });

  btnRefreshList?.addEventListener('click', () => {
    load({ force: true });
  });

  let searchTimer = null;
  searchInput?.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(load, 250);
  });

  viewModalNextBtn?.addEventListener('click', () => {
    if (viewMode === 'preview') {
      const rid = String(currentViewRequestId || '').trim();
      if (!rid) return;
      const stageKey = String(currentViewStage || '').toLowerCase();
      if (stageKey === 'ready_for_claim') {
        openActionModal('mark_completed_confirm', rid);
        return;
      }
      if (stageKey !== 'submitted') {
        return;
      }
      const currentRow = itemById.get(rid);
      openActionModal(isFirstTimeJobSeekerRow(currentRow) ? 'personnel_approve' : 'personnel_approve_confirm', rid);
      return;
    }
    switchViewMode('preview');
  });

  viewModalBackBtn?.addEventListener('click', () => {
    switchViewMode('details');
  });

  viewModalWalkInBtn?.addEventListener('click', () => {
    const id = String(viewModalWalkInBtn.getAttribute('data-id') || '').trim();
    if (!id) return;
    const row = itemById.get(id);
    if (!row) return;
    const financeKey = statusBucket(row);
    if (financeKey !== 'unpaid' && financeKey !== 'rejected') {
      return;
    }
    openActionModal('finance_verify', id);
    if (actionForm) actionForm.dataset.verifyMode = 'walkin';
  });

  viewModalEl?.addEventListener('hidden.bs.modal', () => {
    if (preserveViewStateOnNextHide) {
      preserveViewStateOnNextHide = false;
      return;
    }
    resetPreviewScrollGate();
    if (templatePreviewObjectUrl) {
      URL.revokeObjectURL(templatePreviewObjectUrl);
      templatePreviewObjectUrl = '';
    }
    if (viewModalWalkInBtn) {
      viewModalWalkInBtn.classList.add('d-none');
      viewModalWalkInBtn.removeAttribute('data-id');
    }
    if (viewModalDocBtn) {
      viewModalDocBtn.classList.add('d-none');
      viewModalDocBtn.onclick = null;
    }
    viewDetailsHtml = '';
    viewPreviewState = null;
    currentViewRequestId = '';
    currentViewStage = '';
    openViewDirectPreview = false;
    switchViewMode('details');
  });

  load();
})();
