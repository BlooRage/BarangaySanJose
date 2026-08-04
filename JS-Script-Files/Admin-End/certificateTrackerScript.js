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
  const launchParams = new URLSearchParams(window.location.search);
  const launchTab = String(launchParams.get('tab') || '').toLowerCase();
  const launchManualDocument = String(launchParams.get('document') || '').toLowerCase();
  const launchEntry = String(launchParams.get('entry') || '').toLowerCase();
  const rawLaunchStage = String(launchParams.get('stage') || '').toLowerCase();
  const rawLaunchFilterDocument = String(launchParams.get('filter_document') || '').trim();
  const isBarangayIdManualLaunch = launchTab === 'manual' && launchManualDocument === 'barangay_id';
  const isIdIssuanceTrackerView = launchEntry === 'id_issuance' || isBarangayIdManualLaunch;
  const isLegacyBarangayIdTrackerLaunch =
    rawLaunchStage === 'barangay_id' &&
    rawLaunchFilterDocument.toLowerCase() === 'barangay id';
  const launchStage = isLegacyBarangayIdTrackerLaunch ? '' : rawLaunchStage;
  const launchFilterDocument = isLegacyBarangayIdTrackerLaunch ? '' : rawLaunchFilterDocument;
  const barangayIdTabCount = document.getElementById('barangayIdTabCount');
  const pendingTabCount = document.getElementById('pendingTabCount');
  const releaseTabCount = document.getElementById('releaseTabCount');
  const unpaidTabCount = document.getElementById('unpaidTabCount');
  const pendingVerificationTabCount = document.getElementById('pendingVerificationTabCount');
  const statusTabs = Array.from(document.querySelectorAll('[data-status-filter]'));
  const btnRefreshList = document.getElementById('btnRefreshList');
  const filterButton = document.getElementById('filterButton');
  const filterModalEl = document.getElementById('modalFilter');
  const filterDateFrom = document.getElementById('filterDateFrom');
  const filterDateTo = document.getElementById('filterDateTo');
  const filterDocumentTypeList = document.getElementById('filterDocumentTypeList');
  const filterAreaList = document.getElementById('filterAreaList');
  const filterSectorList = document.getElementById('filterSectorList');
  const btnApplyFilter = document.getElementById('btnApplyFilter');
  const btnResetModalFilters = document.getElementById('btnResetModalFilters');
  const documentTypeFilter = document.getElementById('documentTypeFilter');
  const financeFilterDateFrom = document.getElementById('financeFilterDateFrom');
  const financeFilterDateTo = document.getElementById('financeFilterDateTo');
  const financeFilterTypeList = document.getElementById('financeFilterDocumentTypeList');
  const financeFilterAreaList = document.getElementById('financeFilterAreaList');
  const financeFilterSectorList = document.getElementById('financeFilterSectorList');
  const financeFilterPaymentMethod = document.getElementById('financeFilterPaymentMethod');
  const btnFinanceFilterApply = document.getElementById('btnFinanceFilterApply');
  const btnFinanceFilterReset = document.getElementById('btnFinanceFilterReset');
  const financeColChecks = Array.from(document.querySelectorAll('[data-finance-col-index]'));
  const btnFinanceColumnsReset = document.getElementById('btnFinanceColumnsReset');

  function setRefreshLoading(on) {
    if (!btnRefreshList) return;
    btnRefreshList.classList.toggle('is-loading', !!on);
    btnRefreshList.disabled = !!on;
  }

  function getOrCreateModalInstance(modalEl, options = {}) {
    if (!modalEl || !window.bootstrap?.Modal) return null;
    if (typeof bootstrap.Modal.getOrCreateInstance === 'function') {
      return bootstrap.Modal.getOrCreateInstance(modalEl, options);
    }
    const existing = typeof bootstrap.Modal.getInstance === 'function'
      ? bootstrap.Modal.getInstance(modalEl)
      : null;
    return existing || new bootstrap.Modal(modalEl, options);
  }

  function runAfterModalHidden(modalEl, callback) {
    if (typeof callback !== 'function') return;
    if (!modalEl || !modalEl.classList.contains('show')) {
      try {
        callback();
      } catch (err) {
        console.error('Modal follow-up callback failed:', err);
      }
      return;
    }

    let done = false;
    let fallbackTimer = 0;
    const finish = () => {
      if (done) return;
      done = true;
      if (fallbackTimer) {
        window.clearTimeout(fallbackTimer);
      }
      modalEl.removeEventListener('hidden.bs.modal', finish);
      try {
        callback();
      } catch (err) {
        console.error('Modal follow-up callback failed:', err);
      }
    };

    modalEl.addEventListener('hidden.bs.modal', finish, { once: true });
    fallbackTimer = window.setTimeout(finish, 700);
  }

  function handoffToFeeTagging(requestId, options = {}, sourceModalEl = null, sourceModal = null) {
    if (!requestId) return;
    const openNow = () => {
      try {
        openFeeTaggingModal(requestId, options);
      } catch (err) {
        console.error('Failed to open fee tagging modal:', err);
        alert('Unable to open the Tag Fees modal. Please try again.');
      }
    };

    if (sourceModalEl && sourceModalEl.classList.contains('show') && sourceModal) {
      runAfterModalHidden(sourceModalEl, openNow);
      sourceModal.hide();
      return;
    }

    openNow();
  }

  let feeTypeCatalogCache = null;
  let feeTypeCatalogPromise = null;
  let feeTaggingLoadToken = 0;
  let feeCatalogModalBound = false;
  let feeTaggingReturnState = null;
  let barangayIdTemplateConfigCache = null;
  let barangayIdTemplateConfigPromise = null;

  async function fetchBarangayIdTemplateConfig(options = {}) {
    const force = !!options.force;
    if (!force && barangayIdTemplateConfigCache && typeof barangayIdTemplateConfigCache === 'object') {
      return barangayIdTemplateConfigCache;
    }
    if (!force && barangayIdTemplateConfigPromise) {
      return barangayIdTemplateConfigPromise;
    }

    const runner = (async () => {
      const data = await fetchJson(`${endpoint}?action=barangay_id_template_config`);
      barangayIdTemplateConfigCache = {
        frontTemplateUrl: String(data?.front_template_url || '').trim(),
        backTemplateUrl: String(data?.back_template_url || '').trim(),
        templateVariant: String(data?.template_variant || 'empty').trim() || 'empty',
        layoutConfig: data?.layout && typeof data.layout === 'object' ? data.layout : null,
        sampleData: data?.sample_data && typeof data.sample_data === 'object' ? data.sample_data : null,
        punongSignatoryName: String(data?.punong_signatory_name || '').trim(),
        punongSignatoryTitle: String(data?.punong_signatory_title || '').trim(),
        punongSignatorySignatureUrl: String(data?.punong_signatory_signature_url || '').trim(),
        secretarySignatoryName: String(data?.secretary_signatory_name || '').trim(),
        secretarySignatoryTitle: String(data?.secretary_signatory_title || '').trim()
      };
      return barangayIdTemplateConfigCache;
    })();

    barangayIdTemplateConfigPromise = runner;
    try {
      return await runner;
    } finally {
      barangayIdTemplateConfigPromise = null;
    }
  }

  fetchBarangayIdTemplateConfig().catch(() => null);

  async function fetchFeeTypeCatalog(options = {}) {
    const force = !!options.force;
    if (!force && Array.isArray(feeTypeCatalogCache)) {
      return feeTypeCatalogCache;
    }
    if (!force && feeTypeCatalogPromise) {
      return feeTypeCatalogPromise;
    }

    const runner = (async () => {
      const data = await fetchJson(`${endpoint}?action=list_fee_types`);
      const rows = Array.isArray(data?.fee_types) ? data.fee_types : [];
      feeTypeCatalogCache = rows;
      return rows;
    })();

    if (!force) {
      feeTypeCatalogPromise = runner;
    }

    try {
      return await runner;
    } finally {
      if (!force) {
        feeTypeCatalogPromise = null;
      }
    }
  }

  function warmFeeTypeCatalogCache() {
    fetchFeeTypeCatalog().catch(() => {});
  }

  async function fetchTaggedClearanceFees(requestId) {
    const q = new URLSearchParams({
      action: 'get_clearance_fees',
      request_id: String(requestId || '').trim()
    });
    const data = await fetchJson(`${endpoint}?${q.toString()}`);
    return Array.isArray(data?.fees) ? data.fees : [];
  }

  function resolveSystemAmount(row, fallbackValue = null) {
    const candidates = [fallbackValue, row?.fee_amount, row?.amount];
    for (const candidate of candidates) {
      if (candidate === null || candidate === undefined || String(candidate).trim() === '') {
        continue;
      }
      const numeric = Number(candidate);
      if (Number.isFinite(numeric)) {
        return numeric;
      }
    }
    return null;
  }

  function formatPhpAmount(value, fallback = '-') {
    return Number.isFinite(value) ? `PHP ${value.toFixed(2)}` : fallback;
  }

  function renderFinanceVerifyPrompt({
    row,
    isPendingVerification = false,
    isWalkInStage = false,
    feeRows = null,
    loadingBreakdown = false,
    breakdownError = '',
    fallbackAmount = null
  } = {}) {
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
    const method = firstNonEmpty([row?.payment_method, isWalkInStage ? 'Barangay Walk-in' : 'GCash']);
    const docNameForPrompt = normalizeDocumentTypeDisplay(firstNonEmpty([row?.document_type, '-']));
    const taggedFees = Array.isArray(feeRows) ? feeRows : [];
    const taggedTotal = taggedFees.length
      ? taggedFees.reduce((sum, fee) => sum + (Number(fee?.amount) || 0), 0)
      : null;
    const resolvedAmount = resolveSystemAmount(row, fallbackAmount ?? taggedTotal);
    const showBreakdown = taggedFees.length > 0;
    const showAmountOnly = !showBreakdown && Number.isFinite(resolvedAmount);

    let intro = 'Review the payment details below and enter the OR number to continue.';
    if (isWalkInStage) {
      intro = showBreakdown
        ? 'The tagged fee breakdown below will be used for this walk-in payment. Enter the OR number to continue.'
        : (showAmountOnly
            ? 'The system amount below will be used for this walk-in payment. Enter the OR number to continue.'
            : 'Enter the paid amount and OR number to record this walk-in payment.');
    } else if (isPendingVerification) {
      intro = showBreakdown
        ? 'Review the tagged fee breakdown below and enter the OR number to continue.'
        : 'Review the transaction details below and enter the OR number to continue.';
    }

    const breakdownLines = showBreakdown
      ? taggedFees.map((fee) => {
          const feeName = firstNonEmpty([fee?.fee_type, fee?.fee_name, 'Fee']);
          const feeAmount = Number(fee?.amount);
          return `
            <div class="d-flex justify-content-between align-items-center py-1 border-top">
              <span>${esc(feeName)}</span>
              <span class="fw-semibold">${esc(formatPhpAmount(feeAmount))}</span>
            </div>
          `;
        }).join('')
      : '';

    return `
      <div class="mb-2">${esc(intro)}</div>
      <div class="border rounded p-3 bg-light mt-2">
        <div class="small text-muted mb-2">Payment Summary</div>
        <div><strong>Full Name:</strong> ${esc(customerName)}</div>
        <div><strong>Full Address:</strong> ${esc(customerAddress)}</div>
        <div><strong>Payment Method:</strong> ${esc(method)}</div>
        <div><strong>Requested Document:</strong> ${esc(docNameForPrompt)}</div>
        ${showBreakdown ? `
          <div class="mt-3">
            <div class="fw-semibold mb-1">Tagged Fee Breakdown</div>
            <div class="small">
              ${breakdownLines}
              <div class="d-flex justify-content-between align-items-center pt-2 mt-1 border-top fw-bold text-primary">
                <span>Total</span>
                <span>${esc(formatPhpAmount(resolveSystemAmount(row, taggedTotal)))}</span>
              </div>
            </div>
          </div>
        ` : ''}
        ${!showBreakdown && showAmountOnly ? `
          <div class="d-flex justify-content-between align-items-center pt-2 mt-2 border-top">
            <span class="fw-semibold">Amount Due</span>
            <span class="fw-bold text-primary">${esc(formatPhpAmount(resolvedAmount))}</span>
          </div>
        ` : ''}
        ${loadingBreakdown ? `
          <div class="small text-muted mt-2">
            <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
            Loading tagged fee breakdown...
          </div>
        ` : ''}
        ${breakdownError ? `<div class="small text-danger mt-2">${esc(breakdownError)}</div>` : ''}
      </div>
    `;
  }

  async function populateFinanceVerifyPrompt(row, options = {}) {
    if (!actionPrompt) return;
    const requestId = String(row?.request_id || '').trim();
    const token = ++financeVerifySummaryToken;
    const isPendingVerification = !!options.isPendingVerification;
    const isWalkInStage = !!options.isWalkInStage;
    const needsTaggedBreakdown = requestNeedsFeeTagging(row);

    actionPrompt.innerHTML = renderFinanceVerifyPrompt({
      row,
      isPendingVerification,
      isWalkInStage,
      loadingBreakdown: needsTaggedBreakdown
    });
    actionPrompt.classList.remove('d-none');

    if (!needsTaggedBreakdown || !requestId) {
      return;
    }

    try {
      const feeRows = await fetchTaggedClearanceFees(requestId);
      if (
        token !== financeVerifySummaryToken ||
        String(actionType?.value || '') !== 'finance_verify' ||
        String(actionRequestId?.value || '').trim() !== requestId
      ) {
        return;
      }

      const taggedTotal = feeRows.reduce((sum, fee) => sum + (Number(fee?.amount) || 0), 0);
      const hasTaggedBreakdown = feeRows.length > 0 && Number.isFinite(taggedTotal);
      const resolvedTaggedAmount = resolveSystemAmount(row, hasTaggedBreakdown ? taggedTotal : null);
      if (hasTaggedBreakdown && actionAmount) {
        actionAmount.value = taggedTotal.toFixed(2);
      }
      if (hasTaggedBreakdown) {
        actionAmountWrap.classList.add('d-none');
        actionAmount.required = false;
      } else if (!isPendingVerification && !Number.isFinite(resolvedTaggedAmount)) {
        actionAmountWrap.classList.remove('d-none');
        actionAmount.required = true;
        if (actionAmount) {
          actionAmount.readOnly = false;
          actionAmount.classList.remove('bg-light');
        }
      }

      actionPrompt.innerHTML = renderFinanceVerifyPrompt({
        row,
        isPendingVerification,
        isWalkInStage,
        feeRows,
        fallbackAmount: taggedTotal
      });
    } catch (err) {
      if (
        token !== financeVerifySummaryToken ||
        String(actionType?.value || '') !== 'finance_verify' ||
        String(actionRequestId?.value || '').trim() !== requestId
      ) {
        return;
      }
      actionPrompt.innerHTML = renderFinanceVerifyPrompt({
        row,
        isPendingVerification,
        isWalkInStage,
        breakdownError: err?.message || 'Unable to load the tagged fee breakdown.'
      });
      if (!isPendingVerification && !Number.isFinite(resolveSystemAmount(row))) {
        actionAmountWrap.classList.remove('d-none');
        actionAmount.required = true;
        if (actionAmount) {
          actionAmount.readOnly = false;
          actionAmount.classList.remove('bg-light');
        }
      }
    }
  }

  function renderFeeTaggingLoadingState(row) {
    const docName = esc(row?.document_type || 'Document');
    const residentName = esc(row?.full_name || row?.resident_name || '');
    return `
      <div class="small text-muted mb-3">Preparing fee tagging for <strong>${docName}</strong>${residentName ? ` - ${residentName}` : ''}</div>
      <div class="border rounded text-center py-4 bg-light">
        <div class="spinner-border spinner-border-sm text-primary mb-2" role="status" aria-hidden="true"></div>
        <div class="text-muted">Loading fee options...</div>
      </div>
    `;
  }

  function renderFeeTaggingErrorState(message) {
    return `<div class="alert alert-danger mb-0">${esc(message || 'Failed to load fee tagging data.')}</div>`;
  }

  function renderFeeTaggingForm(row, feeTypes, taggedFees) {
    const taggedMap = {};
    taggedFees.forEach((fee) => {
      const feeName = String(fee?.fee_type || '').trim();
      if (!feeName) return;
      taggedMap[feeName] = fee.amount;
    });

    const docName = esc(row?.document_type || 'Document');
    const residentName = esc(row?.full_name || row?.resident_name || '');
    const renderedFeeNames = new Set();

    let html = `<div class="small text-muted mb-3">Tagging fees for <strong>${docName}</strong>${residentName ? ` - ${residentName}` : ''}</div>`;
    html += `<div class="table-responsive"><table class="table table-sm align-middle" id="feeTaggingTable">`;
    html += `<thead class="table-light"><tr><th style="width:30px"><input type="checkbox" id="feeTagSelectAll" title="Select/deselect all"></th><th>Fee Name</th><th style="width:130px">Amount (₱)</th><th style="width:40px"></th></tr></thead><tbody id="feeTaggingRows">`;

    const activeFeeTypes = Array.isArray(feeTypes)
      ? feeTypes.filter((ft) => {
          const feeName = String(ft?.fee_name || '').trim();
          if (!feeName) return false;
          const status = String(ft?.status || '').trim().toLowerCase();
          return status === '' || status === 'approved';
        })
      : [];

    activeFeeTypes.forEach((ft) => {
      const feeName = String(ft?.fee_name || '').trim();
      if (!feeName) return;
      renderedFeeNames.add(feeName);
      const isChecked = Object.prototype.hasOwnProperty.call(taggedMap, feeName);
      const amt = isChecked ? taggedMap[feeName] : ft.default_amount;
      html += `<tr data-fee-row class="fee-tag-option-row">
        <td data-fee-toggle><input type="checkbox" class="fee-tag-check" ${isChecked ? 'checked' : ''}></td>
        <td data-fee-toggle><span class="fee-tag-name">${esc(feeName)}</span></td>
        <td><input type="number" class="form-control form-control-sm fee-tag-amount" value="${Number(amt).toFixed(2)}" min="0" step="0.01"></td>
        <td></td>
      </tr>`;
    });

    taggedFees.forEach((fee) => {
      const feeName = String(fee?.fee_type || '').trim();
      if (!feeName || renderedFeeNames.has(feeName)) return;
      html += `<tr data-fee-row class="fee-tag-option-row">
        <td data-fee-toggle><input type="checkbox" class="fee-tag-check" checked></td>
        <td data-fee-toggle><span class="fee-tag-name">${esc(feeName)}</span></td>
        <td><input type="number" class="form-control form-control-sm fee-tag-amount" value="${Number(fee.amount || 0).toFixed(2)}" min="0" step="0.01"></td>
        <td></td>
      </tr>`;
    });

    if (!activeFeeTypes.length && !taggedFees.length) {
      html += `<tr><td colspan="4" class="text-center text-muted py-3">No saved fee types yet.</td></tr>`;
    }

    html += `</tbody></table></div>`;
    html += `<div class="d-flex justify-content-between align-items-center fw-bold border-top pt-2"><span>Total</span><span id="feeTaggingTotal" class="text-primary">₱0.00</span></div>`;

    return html;
  }

  function syncFeeTagSelectAllState() {
    const selectAll = document.getElementById('feeTagSelectAll');
    if (!selectAll) return;
    const checks = Array.from(document.querySelectorAll('#feeTaggingRows .fee-tag-check'));
    if (!checks.length) {
      selectAll.checked = false;
      selectAll.indeterminate = false;
      return;
    }
    const checkedCount = checks.filter((cb) => cb.checked).length;
    selectAll.checked = checkedCount === checks.length;
    selectAll.indeterminate = checkedCount > 0 && checkedCount < checks.length;
  }

  function bindFeeTaggingTable() {
    const selectAll = document.getElementById('feeTagSelectAll');
    if (selectAll) {
      selectAll.addEventListener('change', () => {
        document.querySelectorAll('#feeTaggingRows .fee-tag-check').forEach((cb) => { cb.checked = selectAll.checked; });
        updateFeeTagTotal();
      });
    }

    const tbody = document.getElementById('feeTaggingRows');
    if (tbody) {
      tbody.addEventListener('click', (event) => {
        const row = event.target.closest('tr[data-fee-row]');
        if (!row || !tbody.contains(row)) return;
        if (!event.target.closest('td[data-fee-toggle]')) return;
        if (event.target.closest('input, button, a, label, select, textarea')) return;
        const checkbox = row.querySelector('.fee-tag-check');
        if (!checkbox || checkbox.disabled) return;
        checkbox.checked = !checkbox.checked;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
      });
    }

    document.querySelectorAll('#feeTaggingRows .fee-tag-amount, #feeTaggingRows .fee-tag-check').forEach((el) => {
      el.addEventListener('input', updateFeeTagTotal);
      el.addEventListener('change', updateFeeTagTotal);
    });

    syncFeeTagSelectAllState();
    updateFeeTagTotal();
  }

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
  const actionValidityWrap = document.getElementById('actionValidityWrap');
  const actionValidityLabel = document.getElementById('actionValidityLabel');
  const actionValidity = document.getElementById('actionValidity');
  const actionValidityHelp = document.getElementById('actionValidityHelp');
  const actionIssuedWrap = document.getElementById('actionIssuedWrap');
  const actionIssued = document.getElementById('actionIssued');
  const actionBusinessApprovalWrap = document.getElementById('actionBusinessApprovalWrap');
  const actionBusinessApproval = document.getElementById('actionBusinessApproval');
  const actionBusinessApprovalOptionsWrap = document.getElementById('actionBusinessApprovalOptions');
  const actionBusinessApprovalOptions = Array.from(document.querySelectorAll('.action-business-approval-option'));
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
  const paymentProofRegenerateBtn = document.getElementById('paymentProofRegenerateBtn');
  const paymentProofReleaseBtn = document.getElementById('paymentProofReleaseBtn');
  const paymentProofCloseBtn = document.getElementById('paymentProofCloseBtn');
  const regenerateIssuedConfirmModalEl = document.getElementById('regenerateIssuedConfirmModal');
  const regenerateIssuedConfirmModal = regenerateIssuedConfirmModalEl ? new bootstrap.Modal(regenerateIssuedConfirmModalEl) : null;
  const regenerateIssuedConfirmBtn = document.getElementById('regenerateIssuedConfirmBtn');
  const idPrintProcessModalEl = document.getElementById('idPrintProcessModal');
  const idPrintProcessModal = idPrintProcessModalEl ? new bootstrap.Modal(idPrintProcessModalEl) : null;
  const idPrintProcessPreview = document.getElementById('idPrintProcessPreview');
  const idPrintProcessStep = document.getElementById('idPrintProcessStep');
  const idPrintProcessCopy = document.getElementById('idPrintProcessCopy');
  const idPrintProcessReturnBtn = document.getElementById('idPrintProcessReturnBtn');
  const idPrintProcessReprintBtn = document.getElementById('idPrintProcessReprintBtn');
  const idPrintProcessPrimaryBtn = document.getElementById('idPrintProcessPrimaryBtn');
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
  let financeFilterMethod = '';
  let requestModalFilters = {
    dateFrom: '',
    dateTo: '',
    document_type: [],
    area_number: [],
    sector_membership: []
  };
  let cachedAllItems = [];
  let itemById = new Map();
  const detailById = new Map();
  const residentProfileSnapshotByLookup = new Map();
  let viewMode = 'details';
  let viewDetailsHtml = '';
  let viewPreviewState = null;
  let currentViewRequestId = '';
  let currentViewStage = '';
  let actionReturnTarget = '';
  let actionReturnState = null;
  let suppressActionReturn = false;
  let openPreviewAfterActionModal = false;
  let openViewDirectPreview = false;
  let pendingPreviewStateOverride = null;
  let paymentProofReturnTarget = '';
  let paymentProofPrintUrl = '';
  let paymentProofReleaseRequestId = '';
  let paymentProofModalState = null;
  let idPrintProcessPhase = 'front';
  let idPrintProcessPendingOpen = false;
  let idPrintProcessContext = null;
  let idPrintProcessReopenViewer = false;
  let paymentProofBarangayIdReopen = null;
  let pendingPaymentProofAction = null;
  let submittedFileReturnTarget = '';
  let preserveViewStateOnNextHide = false;
  let financeViewIntent = 'view';
  let templatePreviewRequestSeq = 0;
  let financeVerifySummaryToken = 0;
  let previewScrollCleanup = null;
  const financeStages = new Set([
    'for_payment',
    'payment_submitted',
    'payment_rejected',
    'payment_verified',
    'ready_for_claim',
    'completed'
  ]);

  function formatDateInputValue(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) return '';
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  function parseDateInputValue(value) {
    const raw = String(value || '').trim();
    if (!raw) return null;
    const dateMatch = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (dateMatch) {
      const parsed = new Date(
        Number.parseInt(dateMatch[1], 10),
        Number.parseInt(dateMatch[2], 10) - 1,
        Number.parseInt(dateMatch[3], 10),
        12,
        0,
        0,
        0
      );
      return Number.isNaN(parsed.getTime()) ? null : parsed;
    }
    const parsed = new Date(raw);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
  }

  function addDaysDateInputValue(days, baseDate = new Date()) {
    const date = baseDate instanceof Date && !Number.isNaN(baseDate.getTime())
      ? new Date(baseDate.getTime())
      : new Date();
    date.setHours(12, 0, 0, 0);
    date.setDate(date.getDate() + Number(days || 0));
    return formatDateInputValue(date);
  }

  function addYearsDateInputValue(years, baseDate = new Date()) {
    const date = baseDate instanceof Date && !Number.isNaN(baseDate.getTime())
      ? new Date(baseDate.getTime())
      : new Date();
    date.setHours(12, 0, 0, 0);
    date.setFullYear(date.getFullYear() + Number(years || 0));
    return formatDateInputValue(date);
  }

  function certificateDefaultValidityDate(baseDate = new Date()) {
    const configuredDays = Number.parseInt(String(window.ISSUANCE_SETTINGS?.default_validity_days || '45'), 10);
    return addDaysDateInputValue(Math.max(1, Math.min(365, configuredDays || 45)), baseDate);
  }

  function barangayIdDefaultValidityDate(baseDate = new Date()) {
    const configuredYears = Number.parseInt(String(window.BARANGAY_ID_SETTINGS?.default_validity_years || '2'), 10);
    return addYearsDateInputValue(Math.max(1, Math.min(5, configuredYears || 2)), baseDate);
  }

  function certificateValidityPresets(baseDate = new Date()) {
    const configured = Array.isArray(window.ISSUANCE_SETTINGS?.allowed_validity_days)
      ? window.ISSUANCE_SETTINGS.allowed_validity_days.map(Number).filter((days) => Number.isInteger(days) && days > 0 && days <= 365)
      : [];
    const daysList = configured.length ? [...new Set(configured)] : [3, 15, 30, 45, 60];
    return daysList.map((days) => ({
      value: addDaysDateInputValue(days, baseDate),
      label: `${days} days`,
      amount: days,
      unit: 'days'
    }));
  }

  function barangayIdValidityPresets(baseDate = new Date()) {
    return [1, 2, 3, 4, 5].map((years) => ({
      value: addYearsDateInputValue(years, baseDate),
      label: `${years} ${years === 1 ? 'year' : 'years'}`,
      amount: years,
      unit: 'years'
    }));
  }

  function validityPresetOptions(kind, baseDate = new Date()) {
    return kind === 'barangay_id'
      ? barangayIdValidityPresets(baseDate)
      : certificateValidityPresets(baseDate);
  }

  function defaultValidityDateForKind(kind, baseDate = new Date()) {
    return kind === 'barangay_id'
      ? barangayIdDefaultValidityDate(baseDate)
      : certificateDefaultValidityDate(baseDate);
  }

  function normalizeDateInputValue(value, fallback = '') {
    const parsed = parseDateInputValue(value);
    if (parsed) return formatDateInputValue(parsed);
    return String(fallback || '').trim();
  }

  function resolveCertificateValidityDate(value, fallback = '', baseDate = new Date()) {
    const options = validityPresetOptions('certificate', baseDate);
    const normalizedValue = normalizeDateInputValue(value, '');
    if (normalizedValue && options.some((option) => option.value === normalizedValue)) {
      return normalizedValue;
    }
    const normalizedFallback = normalizeDateInputValue(fallback, '');
    if (normalizedFallback && options.some((option) => option.value === normalizedFallback)) {
      return normalizedFallback;
    }
    return certificateDefaultValidityDate(baseDate);
  }

  function resolveBarangayIdValidityDate(value, fallback = '', baseDate = new Date()) {
    const options = validityPresetOptions('barangay_id', baseDate);
    const normalizedValue = normalizeDateInputValue(value, '');
    if (normalizedValue && options.some((option) => option.value === normalizedValue)) {
      return normalizedValue;
    }
    const normalizedFallback = normalizeDateInputValue(fallback, '');
    if (normalizedFallback && options.some((option) => option.value === normalizedFallback)) {
      return normalizedFallback;
    }
    return barangayIdDefaultValidityDate(baseDate);
  }

  function resolveValidityDateByKind(kind, value, fallback = '', baseDate = new Date()) {
    return kind === 'barangay_id'
      ? resolveBarangayIdValidityDate(value, fallback, baseDate)
      : resolveCertificateValidityDate(value, fallback, baseDate);
  }

  function diffCalendarDays(startValue, endValue) {
    const start = parseDateInputValue(startValue);
    const end = parseDateInputValue(endValue);
    if (!(start instanceof Date) || Number.isNaN(start.getTime()) || !(end instanceof Date) || Number.isNaN(end.getTime())) {
      return null;
    }
    const startMidday = new Date(start.getFullYear(), start.getMonth(), start.getDate(), 12, 0, 0, 0);
    const endMidday = new Date(end.getFullYear(), end.getMonth(), end.getDate(), 12, 0, 0, 0);
    return Math.max(0, Math.round((endMidday.getTime() - startMidday.getTime()) / 86400000));
  }

  function numberToWords(value) {
    const number = Math.max(0, Number.parseInt(String(value || '0'), 10) || 0);
    const ones = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'];
    const teens = ['ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
    const tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

    if (number < 10) return ones[number];
    if (number < 20) return teens[number - 10];
    if (number < 100) {
      const remainder = number % 10;
      return remainder ? `${tens[Math.floor(number / 10)]}-${ones[remainder]}` : tens[Math.floor(number / 10)];
    }
    if (number < 1000) {
      const remainder = number % 100;
      const prefix = `${ones[Math.floor(number / 100)]} hundred`;
      return remainder ? `${prefix} ${numberToWords(remainder)}` : prefix;
    }
    const thousands = Math.floor(number / 1000);
    const remainder = number % 1000;
    const prefix = `${numberToWords(thousands)} thousand`;
    return remainder ? `${prefix} ${numberToWords(remainder)}` : prefix;
  }

  function sentenceCaseWords(value) {
    const text = String(value || '').trim();
    return text ? text.charAt(0).toUpperCase() + text.slice(1) : '';
  }

  function formatDateForValidityCard(value) {
    const raw = String(value || '').trim();
    if (!raw) return '';
    const parsed = parseDateInputValue(raw);
    if (!(parsed instanceof Date) || Number.isNaN(parsed.getTime())) return raw;
    return parsed.toLocaleDateString('en-PH', {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  }

  function formatDateForBarangayIdCard(value) {
    const parsed = parseDateInputValue(value);
    if (!(parsed instanceof Date) || Number.isNaN(parsed.getTime())) return String(value || '').trim();
    return [
      String(parsed.getMonth() + 1).padStart(2, '0'),
      String(parsed.getDate()).padStart(2, '0'),
      parsed.getFullYear()
    ].join('/');
  }

  function describeValiditySelection(kind, value) {
    const normalized = normalizeDateInputValue(value, '');
    if (!normalized) {
      return {
        label: kind === 'barangay_id' ? 'Default: 2 years after approval' : 'Default: 45 days after approval',
        dateText: '',
      };
    }

    const preset = validityPresetOptions(kind).find((option) => option.value === normalized);
    return {
      label: preset ? preset.label : '',
      dateText: formatDateForValidityCard(normalized),
    };
  }

  function validityPresetByValue(kind, value) {
    const normalized = normalizeDateInputValue(value, '');
    if (!normalized) return null;
    return validityPresetOptions(kind).find((option) => option.value === normalized) || null;
  }

  function formatValiditySummary(value, kind = 'certificate') {
    const summary = describeValiditySelection(kind, value);
    if (!summary.dateText) {
      return summary.label;
    }
    if (!summary.label) {
      return summary.dateText;
    }
    return `${summary.label} (until ${summary.dateText})`;
  }

  function populateValiditySelect(select, kind, selectedValue = '') {
    if (!select) return '';
    const options = validityPresetOptions(kind);
    const fallbackValue = defaultValidityDateForKind(kind);
    const resolvedValue = resolveValidityDateByKind(kind, selectedValue, fallbackValue);
    const matchedValue = options.some((option) => option.value === resolvedValue)
      ? resolvedValue
      : fallbackValue;

    select.dataset.validityKind = kind;
    select.innerHTML = options.map((option) => (
      `<option value="${option.value}">${option.label}</option>`
    )).join('');
    select.value = matchedValue;
    return matchedValue;
  }

  function configureValidityField(labelEl, helpEl, selectEl, kind, selectedValue = '') {
    if (labelEl) {
      labelEl.textContent = kind === 'barangay_id' ? 'Barangay ID Validity' : 'Certificate Validity';
    }
    if (helpEl) {
      helpEl.textContent = kind === 'barangay_id'
        ? 'Choose the Barangay ID validity period: 1 to 5 years.'
        : `Choose the certificate validity period: ${certificateValidityPresets().map((item) => item.amount).join(', ')} days.`;
    }
    return populateValiditySelect(selectEl, kind, selectedValue);
  }

  function isBarangayIdPreviewDocKey(docKey) {
    return normalizePreviewDocKey(docKey || '') === 'barangayid';
  }

  function isCertificateRequestRow(row) {
    return normalizePreviewDocKey(row?.document_type || '') !== 'barangayid' && !requestNeedsFeeTagging(row);
  }

  function isCertificateManualConfig(config) {
    return !!config && !config.clearance && config.kind !== 'barangay_id';
  }

  function manualValidityKind(config) {
    if (config?.kind === 'barangay_id') return 'barangay_id';
    return isCertificateManualConfig(config) ? 'certificate' : '';
  }

  function isCertificatePreviewDocKey(docKey) {
    const key = normalizePreviewDocKey(docKey || '');
    return !!key && !['barangayid', 'generalpermitclearance', 'businessclearance', 'tricycleclearance'].includes(key);
  }

  function resolveActionValidityKind(docKey, isFirstTimeJobSeeker = false) {
    if (isBarangayIdPreviewDocKey(docKey)) {
      return 'barangay_id';
    }
    if (isFirstTimeJobSeeker || isCertificatePreviewDocKey(docKey)) {
      return 'certificate';
    }
    return '';
  }

  function applyBarangayIdValidityPreviewState(state, validityDate) {
    if (!state || typeof state !== 'object') return;
    const normalized = resolveBarangayIdValidityDate(validityDate, '');
    if (!normalized) return;
    const validUntil = formatDateForBarangayIdCard(normalized);
    state.documentValidity = normalized;
    state.validUntil = validUntil;
    state.validityNotice = `This ID is valid until ${validUntil || '____'} except when the holder requests for a new one.`;
  }
  const isFinancePaymentsPage = /\/admin-end\/certificates\/financepayments(?:\.php)?\/?$/i.test(window.location.pathname);
  const requestFilterModalEl = isFinancePaymentsPage ? document.getElementById('modalFinanceFilter') : filterModalEl;
  const requestFilterDateFromEl = isFinancePaymentsPage ? financeFilterDateFrom : filterDateFrom;
  const requestFilterDateToEl = isFinancePaymentsPage ? financeFilterDateTo : filterDateTo;
  const requestFilterTypeListEl = isFinancePaymentsPage ? financeFilterTypeList : filterDocumentTypeList;
  const requestFilterAreaListEl = isFinancePaymentsPage ? financeFilterAreaList : filterAreaList;
  const requestFilterSectorListEl = isFinancePaymentsPage ? financeFilterSectorList : filterSectorList;
  const financeColumnsStorageKey = 'financePaymentsVisibleColumns';
  const defaultFinanceVisibleColumns = [1, 3, 4, 6, 7, 8];

  function setActiveStageTab(stageFilter) {
    const target = String(stageFilter || '').toLowerCase();
    const hasMatch = stageTabs.some((tab) => String(tab.getAttribute('data-stage-filter') || '').toLowerCase() === target);

    stageTabs.forEach((tab, index) => {
      const tabFilter = String(tab.getAttribute('data-stage-filter') || '').toLowerCase();
      const isActive = hasMatch ? tabFilter === target : index === 0;
      tab.classList.toggle('active', isActive);
    });

    currentStage = hasMatch ? target : '';
  }

  if (launchStage) {
    setActiveStageTab(launchStage);
  }

  function esc(v) {
    return String(v ?? '').replace(/[&<>\"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '\"': '&quot;', "'": '&#39;' }[m]));
  }

  function parseCsvValues(value) {
    return Array.from(new Set(
      String(value ?? '')
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean)
    ));
  }

  const OFFICIAL_AREA_OPTIONS = ['Area 01', 'Area 1A', 'Area 02', 'Area 03', 'Area 04', 'Area 05', 'Area 06'];
  const OFFICIAL_SECTOR_OPTIONS = ['PWD', 'Senior Citizen', 'Student', 'Indigenous People', 'Single Parent'];

  function normalizeSectorLabel(value) {
    const raw = String(value ?? '').trim();
    if (!raw) return '';
    const normalized = raw.toLowerCase().replace(/[^a-z]/g, '');
    const map = {
      pwd: 'PWD',
      seniorcitizen: 'Senior Citizen',
      student: 'Student',
      indigenouspeople: 'Indigenous People',
      indigenousperson: 'Indigenous People',
      singleparent: 'Single Parent',
      soloparent: 'Single Parent',
    };
    return map[normalized] || raw;
  }

  function parseSectorValues(value) {
    return Array.from(new Set(
      parseCsvValues(value)
        .map((item) => normalizeSectorLabel(item))
        .filter(Boolean)
    ));
  }

  function normalizeDateValue(value) {
    const parsed = parseFlexibleDate(value);
    if (!parsed || Number.isNaN(parsed.getTime())) return '';
    const year = parsed.getFullYear();
    const month = String(parsed.getMonth() + 1).padStart(2, '0');
    const day = String(parsed.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  function requestTypeLabel(row) {
    const payload = row && row.payload && typeof row.payload === 'object' ? row.payload : {};
    if (String(payload.manual_document_variant || '').trim().toLowerCase() === 'general certificate - other') {
      return 'General Certificate - Other';
    }
    const previewKey = normalizePreviewDocKey(row?.document_type || '');
    if (previewKey === 'cohabitation') {
      const variant = String(payload?.cohabitation_variant || '').trim().toLowerCase();
      if (['relationship_jail_visit', 'conjugal_visit'].includes(variant)) {
        return 'Certificate for Jail Visitation';
      }
      return 'Certificate of Cohabitation';
    }

    const normalized = normalizeDocumentTypeDisplay(String(row?.document_type || ''));
    if (normalized === 'First Time Job Seeker Certificate') {
      return 'Certificate for First Time Job Seeker';
    }
    return normalized;
  }

  function renderRequestFilterChecklist(container, field, values) {
    if (!container) return;
    const list = Array.isArray(values) ? values : [];
    if (!list.length) {
      container.innerHTML = '<div class="text-muted small">No options available.</div>';
      return;
    }

    const active = new Set(Array.isArray(requestModalFilters[field]) ? requestModalFilters[field] : []);
    container.innerHTML = list.map((value, index) => `
      <label class="d-flex align-items-center gap-2">
        <input class="form-check-input m-0 request-filter-checkbox" type="checkbox" value="${esc(value)}" data-field="${esc(field)}" id="${esc(`requestFilter_${field}_${index}`)}" ${active.has(value) ? 'checked' : ''}>
        <span>${esc(value)}</span>
      </label>
    `).join('');
  }

  function syncRequestFilterOptions(items) {
    const rows = Array.isArray(items) ? items : [];
    const requestTypes = Array.from(new Set(
      rows
        .map((row) => requestTypeLabel(row))
        .map((value) => String(value || '').trim())
        .filter((value) => value && value !== '-')
    )).sort((a, b) => a.localeCompare(b));
    const areaNumbers = OFFICIAL_AREA_OPTIONS.slice();
    const sectors = OFFICIAL_SECTOR_OPTIONS.slice();

    requestModalFilters.document_type = requestModalFilters.document_type.filter((value) => requestTypes.includes(value));
    requestModalFilters.area_number = requestModalFilters.area_number.filter((value) => areaNumbers.includes(value));
    requestModalFilters.sector_membership = requestModalFilters.sector_membership
      .map((value) => normalizeSectorLabel(value))
      .filter((value) => sectors.includes(value));

    renderRequestFilterChecklist(requestFilterTypeListEl, 'document_type', requestTypes);
    renderRequestFilterChecklist(requestFilterAreaListEl, 'area_number', areaNumbers);
    renderRequestFilterChecklist(requestFilterSectorListEl, 'sector_membership', sectors);

    if (requestFilterDateFromEl) requestFilterDateFromEl.value = requestModalFilters.dateFrom || '';
    if (requestFilterDateToEl) requestFilterDateToEl.value = requestModalFilters.dateTo || '';
  }

  function collectRequestModalFilters() {
    const next = {
      dateFrom: String(requestFilterDateFromEl?.value || '').trim(),
      dateTo: String(requestFilterDateToEl?.value || '').trim(),
      document_type: [],
      area_number: [],
      sector_membership: []
    };

    document.querySelectorAll('.request-filter-checkbox:checked').forEach((checkbox) => {
      const field = String(checkbox.getAttribute('data-field') || '').trim();
      if (!field || !Array.isArray(next[field])) return;
      next[field].push(String(checkbox.value || '').trim());
    });

    return next;
  }

  function matchesRequestModalFilters(row) {
    const submittedDate = normalizeDateValue(firstNonEmpty([row?.submitted_at, row?.request_timestamp]));
    if (requestModalFilters.dateFrom && (!submittedDate || submittedDate < requestModalFilters.dateFrom)) return false;
    if (requestModalFilters.dateTo && (!submittedDate || submittedDate > requestModalFilters.dateTo)) return false;
    if (requestModalFilters.document_type.length && !requestModalFilters.document_type.includes(requestTypeLabel(row))) return false;
    if (requestModalFilters.area_number.length && !requestModalFilters.area_number.includes(String(row?.area_number || '').trim())) return false;
    if (requestModalFilters.sector_membership.length) {
      const memberships = parseSectorValues(row?.sector_membership);
      const hasSector = requestModalFilters.sector_membership
        .map((value) => normalizeSectorLabel(value))
        .some((value) => memberships.includes(value));
      if (!hasSector) return false;
    }
    return true;
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
    const payload = row?.payload && typeof row.payload === 'object' ? row.payload : {};
    const raw = String(
      row?.payment_proof_path
      || payload?.payment_proof_path
      || payload?.payment_proof
      || payload?.payment_proof_url
      || ''
    ).trim();
    const id = String(requestId || row?.request_id || '').trim();
    const isGcash = String(row?.payment_method || '').trim().toLowerCase() === 'gcash';
    if (id && (raw || isGcash)) {
      return `${appBase}/PhpFiles/Admin-End/documentRequestWorkflow.php?action=view_payment_proof&request_id=${encodeURIComponent(id)}&_ts=${Date.now()}`;
    }
    if (!raw) return '';

    const unifiedMatch = raw.replace(/\\/g, '/').match(/\/UnifiedFileAttachment\/[^\s"'<>]+/i);
    if (unifiedMatch && unifiedMatch[0]) {
      return `${appBase}${unifiedMatch[0]}`;
    }

    return resolvePublicUrl(raw);
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
        { key: 'fullName', label: 'Name', wide: true },
        { key: 'fullAddress', label: 'Address', multiline: true, wide: true },
        { key: 'requestOfficerLine1', label: 'Official Name' },
        { key: 'requestOfficerLine2', label: 'Position' },
        { key: 'requestOfficerLine3', label: 'Jurisdiction', wide: true },
        { key: 'purpose', label: 'Purpose', multiline: true, wide: true }
      ];
    }
    if (key === 'goodmoral') {
      return [
        { key: 'fullName', label: 'Name', wide: true },
        { key: 'fullAddress', label: 'Address', multiline: true, wide: true },
        { key: 'purpose', label: 'Purpose', multiline: true, wide: true }
      ];
    }
    if (key === 'residency') {
      return [
        { key: 'fullName', label: 'Name', wide: true },
        { key: 'fullAddress', label: 'Address', multiline: true, wide: true },
        { key: 'birthdate', label: 'Birthdate' },
        { key: 'birthplace', label: 'Birthplace', wide: true },
        { key: 'remarks', label: 'Remarks', multiline: true, wide: true },
        { key: 'purpose', label: 'Purpose', multiline: true, wide: true }
      ];
    }
    if (key === 'cohabitation') {
      return [
        { key: 'fullName', label: 'Applicant Name', wide: true },
        { key: 'fullAddress', label: 'Address', multiline: true, wide: true },
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
      { key: 'fullName', label: 'Name', wide: true },
      { key: 'fullAddress', label: 'Address', multiline: true, wide: true },
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
    if (window.BarangayIdDigital && typeof window.BarangayIdDigital.hydrate === 'function') {
      const hydrateBarangayId = () => window.BarangayIdDigital.hydrate(viewDetailsBody);
      hydrateBarangayId();
      window.requestAnimationFrame(() => {
        hydrateBarangayId();
        window.requestAnimationFrame(hydrateBarangayId);
      });
      window.setTimeout(hydrateBarangayId, 120);
      window.setTimeout(hydrateBarangayId, 400);
      window.setTimeout(hydrateBarangayId, 700);
      viewModalEl?.addEventListener('shown.bs.modal', hydrateBarangayId, { once: true });
    }
    bindPreviewEditHandlers();
  }

  function badge(stage, label) {
    const statusPill = (tone) => `<span class="status-pill ${tone}">${label}</span>`;
    if (isFinancePaymentsPage) {
      const bucket = String(stage || '').toLowerCase();
      if (bucket === 'verified') return statusPill('approved');
      if (bucket === 'pending_verification') return statusPill('pending');
      if (bucket === 'unpaid') return statusPill('archived');
      if (bucket === 'rejected') return statusPill('denied');
      if (bucket === 'cancelled') return statusPill('archived');
      return statusPill('archived');
    }
    const k = String(stage || '').toLowerCase();
    if (k.includes('rejected')) return statusPill('denied');
    if (k === 'completed') return statusPill('approved');
    if (k === 'fee_tagging') return statusPill('approved');
    if (k === 'for_printing') return statusPill('info');
    if (k === 'ready_for_claim' || k === 'payment_verified') return statusPill('info');
    if (k === 'submitted' || k === 'pending_verification' || k === 'for_payment' || k === 'payment_submitted') return statusPill('pending');
    return statusPill('archived');
  }

  function requestDeliveryNote(row) {
    return String(row?.hard_copy_notice || '').trim();
  }

  function requestHardCopyStatusLabel(row) {
    return String(row?.hard_copy_status_label || '').trim();
  }

  function resolveWorkflowStage(row) {
    if (!row) return '';
    const normalizeStageToken = (value) => String(value || '')
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '');
    const normalizeStageAlias = (value) => {
      const token = normalizeStageToken(value);
      if (token === 'for_release' || token === 'forrelease') return 'ready_for_claim';
      if (token === 'ready_for_release' || token === 'readyforrelease') return 'ready_for_claim';
      return token;
    };

    const stageRaw = normalizeStageAlias(row.stage);
    const stageLabel = normalizeStageAlias(row.stage_label);
    const paymentToken = normalizeStageAlias(firstNonEmpty([row.payment_status_name, row.payment_status_label]));
    const financeLikeStages = new Set([
      'for_payment',
      'payment_submitted',
      'payment_rejected',
      'payment_verified',
      'ready_for_claim',
      'completed'
    ]);
    const paymentFlowStages = new Set([
      'for_payment',
      'payment_submitted',
      'payment_rejected',
      'payment_verified'
    ]);
    const isBarangayId = normalizePreviewDocKey(row?.document_type || '') === 'barangayid';

    let stage = stageRaw || stageLabel;
    if (financeLikeStages.has(stageLabel)) {
      stage = stageLabel;
    }

    // Only let payment-status labels refine active payment stages.
    // Once a request is already for release/completed, don't downgrade it.
    const canDeriveFromPaymentToken = !stageRaw || paymentFlowStages.has(stageRaw) || paymentFlowStages.has(stageLabel);
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

    if (isBarangayId && paymentFlowStages.has(stage)) {
      return 'ready_for_claim';
    }

    return stage;
  }

  function actionButtons(row) {
    const viewBtn = `<button class="btn btn-sm btn-outline-secondary me-1" data-view-id="${esc(row.request_id)}">View</button>`;
    const stageKey = resolveWorkflowStage(row);
    const issuedLabel = normalizePreviewDocKey(row?.document_type || '') === 'barangayid'
      ? 'View ID'
      : 'View Document';
    const viewIssuedBtn = (!isFinancePaymentsPage && stageKey !== 'completed' && canOpenIssuedDocument(row))
      ? `<button class="btn btn-sm btn-outline-success me-1" data-issued-id="${esc(row.request_id)}">${issuedLabel}</button>`
      : '';
    if (isFinancePaymentsPage) {
      const financeKey = statusBucket(row);
      if (financeKey === 'pending_verification') {
        return `${viewBtn}<button class="btn btn-sm btn-success" data-inline-action="finance_verify_gcash" data-id="${esc(row.request_id)}">Verify Payment</button>`;
      }
      return viewBtn;
    }
    const buttons = `${viewBtn}${viewIssuedBtn}`;
    return isIdIssuanceTrackerView
      ? `<div class="id-issuance-table-actions">${buttons}</div>`
      : buttons;
  }

  function viewModalActionButtons(row) {
    if (!row) return '';
    const id = esc(row.request_id || '');
    const isFirstTimeJobSeeker = isFirstTimeJobSeekerRow(row);
    const stage = resolveWorkflowStage(row);
    const proofBtn = (isFinancePaymentsPage && row.payment_proof_path)
      ? `<button class="btn btn-sm btn-outline-dark" data-proof-id="${id}">View Payment</button>`
      : '';

    if (stage === 'submitted') {
      return `
        <button class="btn btn-sm btn-danger" data-view-action="personnel_reject" data-id="${id}">Reject</button>
        <button class="btn btn-sm btn-success" data-view-action="personnel_approve" data-id="${id}">${isFirstTimeJobSeeker ? 'Approve for Interview' : 'Approve'}</button>
      `;
    }
    if (stage === 'fee_tagging') {
      if (isFinancePaymentsPage) return '<span class="text-muted small">No actions</span>';
      return `<button class="btn btn-sm btn-warning" data-view-action="open_fee_tagging" data-id="${id}">Continue Approval</button>`;
    }
    if (stage === 'for_interview' && isFirstTimeJobSeeker) {
      return `
        <button class="btn btn-sm btn-danger" data-view-action="interview_fail" data-id="${id}">Fail Interview</button>
        <button class="btn btn-sm btn-success" data-view-action="interview_pass" data-id="${id}">Pass Interview</button>
      `;
    }
    if (stage === 'for_inspection' && requestRequiresInspection(row)) {
      return `
        <button class="btn btn-sm btn-danger" data-view-action="inspection_fail" data-id="${id}">Fail Inspection</button>
        <button class="btn btn-sm btn-success" data-view-action="inspection_pass" data-id="${id}">Pass Inspection</button>
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
      if (isFinancePaymentsPage) return proofBtn || '<span class="text-muted small">No actions</span>';
      return `
        ${proofBtn}
        <button class="btn btn-sm btn-primary" data-view-action="mark_ready" data-id="${id}">Ready for Claim</button>
      `;
    }
    if (stage === 'for_printing') {
      if (isFinancePaymentsPage) return proofBtn || '<span class="text-muted small">No actions</span>';
      return `
        ${proofBtn}
        <button class="btn btn-sm btn-primary" data-view-action="mark_ready" data-id="${id}">Mark Printed / For Claim</button>
      `;
    }
    if (stage === 'ready_for_claim') {
      if (isFinancePaymentsPage) return proofBtn || '<span class="text-muted small">No actions</span>';
      return `
        ${proofBtn}
        <button class="btn btn-sm btn-success" data-view-action="mark_completed" data-id="${id}">Mark as Claimed</button>
      `;
    }
    return proofBtn || '<span class="text-muted small">No actions</span>';
  }

  function firstNonEmpty(values) {
    for (const value of values) {
      if (value === null || value === undefined) continue;
      const s = String(value).trim();
      if (looksLikeProtectedValue(s)) continue;
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

  function looksLikeProtectedValue(value) {
    const s = String(value || '').trim();
    if (!s) return false;
    return /^pii:v\d+:/i.test(s);
  }

  function firstNonEmptyName(values, fallback = '') {
    for (const value of values) {
      if (value === null || value === undefined) continue;
      const s = String(value).trim();
      if (!s) continue;
      if (looksLikeProtectedValue(s)) continue;
      if (looksLikeOfficialId(s)) continue;
      return s;
    }
    return String(fallback || '').trim();
  }

  function formatPersonNameFnMiLn(first, middle, last, suffix = '') {
    const f = String(first || '').trim();
    const m = String(middle || '').trim();
    const l = String(last || '').trim();
    const s = String(suffix || '').trim();
    const mi = m ? `${m.charAt(0).toUpperCase()}.` : '';
    return [f, mi, l, s].filter(Boolean).join(' ').trim();
  }

  function fullNameFromRow(row) {
    const payload = row && row.payload && typeof row.payload === 'object' ? row.payload : {};
    const residentProfile = row && row.resident_profile && typeof row.resident_profile === 'object'
      ? row.resident_profile
      : {};
    const fallbackName = firstNonEmptyName([
      payload._preview_full_name,
      row.full_name,
      row.resident_full_name,
      row.resident_name,
      payload.resident_name,
      ''
    ]);
    if (fallbackName) {
      return fallbackName;
    }

    const first = firstNonEmptyName([
      payload.first_name,
      payload.firstname,
      residentProfile.first_name,
      residentProfile.firstname
    ]);
    const middle = firstNonEmptyName([
      payload.middle_name,
      payload.middlename,
      residentProfile.middle_name,
      residentProfile.middlename
    ]);
    const last = firstNonEmptyName([
      payload.last_name,
      payload.lastname,
      residentProfile.last_name,
      residentProfile.lastname
    ]);
    const suffix = firstNonEmptyName([
      payload.suffix,
      payload.suffix_name,
      residentProfile.suffix,
      residentProfile.suffix_name
    ]);
    const ordered = formatPersonNameFnMiLn(first, middle, last, suffix);
    if (ordered.length) return ordered;
    return '-';
  }

  function stripAreaFromAddress(address) {
    let value = String(address || '').trim();
    if (!value) return '';
    value = value.replace(/\s*,\s*Area(?:\s+Area)*\s+[A-Za-z0-9-]+\s*(?=,|$)/gi, '');
    value = value.replace(/(^|,\s*)Area(?:\s+Area)*\s+[A-Za-z0-9-]+\s*,\s*/gi, '$1');
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
    if (!cleaned) {
      return suffix || '-';
    }
    if (!suffix) {
      return cleaned;
    }
    if (cleaned.toLowerCase().endsWith(suffix.toLowerCase())) {
      return cleaned;
    }
    return `${cleaned}, ${suffix}`;
  }

  function composeLocalityAddress(address, locality = 'SAN JOSE, RODRIGUEZ, RIZAL') {
    const suffix = String(locality || '').trim();
    const cleaned = stripAreaFromAddress(String(address || '').trim()).replace(/^[,\s]+|[,\s]+$/g, '');
    if (!cleaned) {
      return suffix || '-';
    }
    if (!suffix) {
      return cleaned;
    }
    if (cleaned.toLowerCase().endsWith(suffix.toLowerCase())) {
      return cleaned;
    }
    return `${cleaned}, ${suffix}`;
  }

  function pickMostSpecificAddress(...candidates) {
    let fallback = '';
    let best = '';
    let bestScore = -1;

    candidates.flat().forEach((candidate) => {
      const text = String(candidate || '').trim();
      if (!text) return;
      if (!fallback) fallback = text;

      const stripped = stripAreaFromAddress(text);
      let score = stripped.length;
      if (/\d/.test(stripped)) score += 20;
      if (/\b(unit|lot|blk|block|phase|street|st\\.?|subdivision)\b/i.test(stripped)) score += 15;
      if (stripped.includes(',')) score += 8;

      if (score > bestScore) {
        best = text;
        bestScore = score;
      }
    });

    return best || fallback;
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
    const doc = trackerDocumentTypeDisplay(row);
    return `<span style="display:inline-block;padding:4px 10px;border-radius:8px;background:#dbeafe;color:#1e40af;font-weight:700;">${esc(doc)}</span>`;
  }

  function trackerDocumentTypeDisplay(row) {
    const payload = row && row.payload && typeof row.payload === 'object' ? row.payload : {};
    if (String(payload.manual_document_variant || '').trim().toLowerCase() === 'general certificate - other') {
      return 'General Certificate - Other';
    }
    return normalizeDocumentTypeDisplay(firstNonEmpty([row?.document_type, '-']));
  }

  function normalizeDocumentTypeDisplay(value) {
    const raw = String(value || '').trim();
    if (!raw) return '-';
    const key = raw.toLowerCase().replace(/[^a-z0-9]+/g, '');
    if (key.includes('barangayid')) {
      return 'Barangay ID';
    }
    if (key.includes('residentialbuildingpermit')) {
      return 'Barangay Clearance for Residential Building Permit';
    }
    if (key.includes('commercialbuildingpermit')) {
      return 'Barangay Clearance for Commercial Building Permit';
    }
    if (key.includes('electricalpermit')) {
      return 'Barangay Clearance for Electrical Permit';
    }
    if (key.includes('waterpermit')) {
      return 'Barangay Clearance for Water Permit';
    }
    if (key.includes('residentialpermit')) {
      return 'Barangay Clearance for Residential Permit';
    }
    if (key.includes('commercialpermit')) {
      return 'Barangay Clearance for Commercial Permit';
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
    if (key.includes('tricycle')) {
      return 'Barangay Clearance for Tricycle Permit';
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
    const subsection = (title, rows) => {
      const content = (Array.isArray(rows) ? rows : []).filter(Boolean).join('');
      if (!content) return '';
      return `
        <div class="tracker-form-subsection">
          <h6 class="tracker-form-subsection-title">${esc(title)}</h6>
          ${content}
        </div>
      `;
    };
    const takeFirst = (arr, predicate) => {
      const idx = arr.findIndex((f) => f && predicate(normalizeLabel(f.label)));
      if (idx === -1) return null;
      return arr.splice(idx, 1)[0];
    };
    const takeAll = (arr, predicate) => {
      const picked = [];
      for (let i = arr.length - 1; i >= 0; i -= 1) {
        const field = arr[i];
        if (!field || !predicate(normalizeLabel(field.label))) continue;
        picked.unshift(arr[i]);
        arr.splice(i, 1);
      }
      return picked;
    };

    const remaining = clean.slice();

    const applicationType = takeFirst(remaining, (l) => l === 'application type');
    const paymentMethod = takeFirst(remaining, (l) => l === 'payment method');
    const gcashTransactionNumber = takeFirst(remaining, (l) => l === 'gcash transaction number');
    const purpose = takeFirst(remaining, (l) => l === 'purpose' || l.includes('purpose'));
    const applicantAge = takeFirst(remaining, (l) => l === 'applicant age');

    const ownerLastName = takeFirst(remaining, (l) =>
      l === 'owner last name' || l.includes('owner last name') || l === 'applicant last name' || l.includes('applicant last name')
    ) || takeFirst(remaining, (l) => l.includes('last name'));
    const ownerFirstName = takeFirst(remaining, (l) =>
      l === 'owner first name' || l.includes('owner first name') || l === 'applicant first name' || l.includes('applicant first name')
    ) || takeFirst(remaining, (l) => l.includes('first name'));
    const ownerMiddleName = takeFirst(remaining, (l) =>
      l === 'owner middle name' || l.includes('owner middle name') || l === 'applicant middle name' || l.includes('applicant middle name')
    ) || takeFirst(remaining, (l) => l.includes('middle name'));
    const ownerPhone = takeFirst(
      remaining,
      (l) =>
        l === 'owner phone'
        || l.includes('owner phone')
        || l.includes('owner contact number')
        || l === 'applicant phone'
        || l.includes('applicant phone')
        || l.includes('applicant contact number')
    ) || takeFirst(
      remaining,
      (l) => l.includes('contact number') || l.includes('mobile number') || l.includes('phone number')
    );
    const ownerFullAddress = takeFirst(
      remaining,
      (l) =>
        l === 'owner full address'
        || l.includes('owner full address')
        || l === 'applicant full address'
        || l.includes('applicant full address')
    ) || takeFirst(remaining, (l) => l === 'address' || l.includes('full address'));

    const proofAddressType = takeFirst(remaining, (l) => l.includes('proof') && l.includes('address') && l.includes('type'));
    const proofAddressNumber = takeFirst(
      remaining,
      (l) => l.includes('proof') && l.includes('address') && (l.includes('number') || l.includes('no') || l.includes('#'))
    );

    const businessName = takeFirst(remaining, (l) => l === 'business name');
    const businessContactNumber = takeFirst(
      remaining,
      (l) => l === 'business contact number' || l === 'business contact num' || l === 'business phone'
    );
    const businessAddressSystem = takeFirst(remaining, (l) => l === 'business address system' || l === 'business add system');
    const businessStreetNumber = takeFirst(remaining, (l) => l === 'business street number' || l === 'business street num');
    const businessStreetName = takeFirst(remaining, (l) => l === 'business street name');
    const businessSubdivision = takeFirst(remaining, (l) => l === 'business subdivision' || l === 'subdivision');
    const businessBarangay = takeFirst(remaining, (l) => l === 'business barangay');
    const businessCity = takeFirst(remaining, (l) => l === 'business city');
    const businessProvince = takeFirst(remaining, (l) => l === 'business province');
    const businessFullAddress = takeFirst(remaining, (l) => l === 'business full address');
    const initialOperationDate = takeFirst(remaining, (l) => l === 'initial operation date');
    const owner = takeFirst(remaining, (l) => l === 'owner');
    const businessRegistrationType = takeFirst(remaining, (l) => l === 'business registration type');
    const birthplace = takeFirst(remaining, (l) => l === 'birthplace' || l === 'place of birth');
    const prevLastName = takeFirst(remaining, (l) => l === 'prev last name' || l === 'previous last name');
    const prevFirstName = takeFirst(remaining, (l) => l === 'prev first name' || l === 'previous first name');
    const prevMiddleName = takeFirst(remaining, (l) => l === 'prev middle name' || l === 'previous middle name');
    const prevSuffix = takeFirst(remaining, (l) => l === 'prev suffix' || l === 'previous suffix');
    const prevFullName = takeFirst(remaining, (l) => l === 'prev full name' || l === 'previous full name');
    const prevBusinessApprovalType = takeFirst(
      remaining,
      (l) => l === 'prev business approval type' || l === 'previous business approval type'
    );
    const prevPlateNum = takeFirst(remaining, (l) => l === 'prev plate num' || l === 'previous plate num');

    const lotAddressSystem = takeFirst(remaining, (l) => l === 'lot address system');
    const lotStreetNumber = takeFirst(remaining, (l) => l === 'lot street number');
    const lotStreetName = takeFirst(remaining, (l) => l === 'lot street name');
    const lotSubdivision = takeFirst(remaining, (l) => l === 'lot subdivision' || l === 'subdivision');
    const lotBarangay = takeFirst(remaining, (l) => l === 'lot barangay');
    const lotCity = takeFirst(remaining, (l) => l === 'lot city');
    const lotProvince = takeFirst(remaining, (l) => l === 'lot province');
    const location = takeFirst(remaining, (l) => l === 'location');
    const projectLocation = takeFirst(remaining, (l) => l === 'project location');
    const lotFullAddress = takeFirst(remaining, (l) => l === 'lot full address');

    const franchisee = takeFirst(remaining, (l) => l === 'franchisee');
    const plateNumber = takeFirst(remaining, (l) => l === 'plate number' || l === 'plate no.' || l === 'plate no');
    const bodyNumber = takeFirst(remaining, (l) => l === 'body number' || l === 'body no.' || l === 'body no');
    const chassisNumber = takeFirst(remaining, (l) => l === 'chassis number' || l === 'chassis no.' || l === 'chassis no');
    const motorNumber = takeFirst(remaining, (l) => l === 'motor number' || l === 'motor no.' || l === 'motor no');
    const orNumber = takeFirst(remaining, (l) => l === 'or number' || l === 'or no.' || l === 'or no');
    const crNumber = takeFirst(remaining, (l) => l === 'cr number' || l === 'cr no.' || l === 'cr no');
    const vehicleMake = takeFirst(remaining, (l) => l === 'vehicle make');
    const vehicleNamedToOwner = takeFirst(remaining, (l) => l === 'vehicle named to owner');
    const vehicleFranchise = takeFirst(remaining, (l) => l === 'vehicle franchise');
    const cohabitantFirstName = takeFirst(remaining, (l) => /(partner|cohabitant|detainee) first( name)?$/.test(l));
    const cohabitantMiddleName = takeFirst(remaining, (l) => /(partner|cohabitant|detainee) middle( name)?$/.test(l));
    const cohabitantLastName = takeFirst(remaining, (l) => /(partner|cohabitant|detainee) last( name)?$/.test(l));
    const cohabitantSuffix = takeFirst(remaining, (l) => /(partner|cohabitant|detainee) suffix/.test(l));
    const cohabitantBirthdate = takeFirst(remaining, (l) => /(partner|cohabitant|detainee) birthdate/.test(l) || /(partner|cohabitant|detainee) dob/.test(l));
    const cohabitantAge = takeFirst(remaining, (l) => /(partner|cohabitant|detainee) age/.test(l));
    const cohabitantCivilStatus = takeFirst(remaining, (l) => /(partner|cohabitant|detainee) civil status/.test(l));
    const cohabitantNationality = takeFirst(remaining, (l) => /(partner|cohabitant|detainee) nationality/.test(l));
    const cohabitantRelationship = takeFirst(remaining, (l) => l.includes('relationship'));
    const cohabitantIdType = takeFirst(remaining, (l) => /(partner|cohabitant|detainee) id type/.test(l));
    const cohabitantIdNumber = takeFirst(remaining, (l) => /(partner|cohabitant|detainee) id number/.test(l));
    const cohabitantAddress = takeFirst(remaining, (l) => /(partner|cohabitant|detainee).*(address|residence)/.test(l));
    const cohabitantUnitNumber = takeFirst(remaining, (l) => /cohabitant unit number$/.test(l));
    const cohabitantHouseNumber = takeFirst(remaining, (l) => /cohabitant house number$/.test(l));
    const cohabitantStreetName = takeFirst(remaining, (l) => /cohabitant street name$/.test(l));
    const cohabitantSubdivision = takeFirst(remaining, (l) => /cohabitant subdivision$/.test(l) || /cohabitant subdivision lot$/.test(l));
    const cohabitantLotNumber = takeFirst(remaining, (l) => /cohabitant lot number$/.test(l));
    const cohabitantBlockNumber = takeFirst(remaining, (l) => /cohabitant block number$/.test(l));
    const cohabitantPhaseNumber = takeFirst(remaining, (l) => /cohabitant phase number$/.test(l));
    const cohabitantBarangay = takeFirst(remaining, (l) => /cohabitant barangay$/.test(l));
    const cohabitantCity = takeFirst(remaining, (l) => /cohabitant city$/.test(l));
    const cohabitantProvince = takeFirst(remaining, (l) => /cohabitant province$/.test(l));
    const cohabitantPostalCode = takeFirst(remaining, (l) => /cohabitant postal code$/.test(l));
    const cohabitationStart = takeFirst(remaining, (l) => l.includes('living together since') || l.includes('cohabitation start date'));
    const cohabitationDuration = takeFirst(remaining, (l) => l === 'cohabitation duration');
    const cohabitationDurationValue = takeFirst(remaining, (l) => l === 'cohabitation duration value');
    const cohabitationDurationUnit = takeFirst(remaining, (l) => l === 'cohabitation duration unit');
    const cohabitationSameAddress = takeFirst(remaining, (l) => /cohabitation.*same.*address/.test(l));
    const cohabitationAddress = takeFirst(remaining, (l) => l.includes('cohabitation') && (l.includes('address') || l.includes('residence')));
    const cohabitationHouseNumber = takeFirst(remaining, (l) => /cohabitation house number$/.test(l));
    const cohabitationStreetName = takeFirst(remaining, (l) => /cohabitation street name$/.test(l));
    const cohabitationSubdivision = takeFirst(remaining, (l) => /cohabitation subdivision$/.test(l) || /cohabitation subdivision lot$/.test(l));
    const cohabitationBarangay = takeFirst(remaining, (l) => /cohabitation barangay$/.test(l));
    const cohabitationMunicipality = takeFirst(remaining, (l) => /cohabitation municipality$/.test(l) || /cohabitation city$/.test(l));
    const cohabitationProvince = takeFirst(remaining, (l) => /cohabitation province$/.test(l));
    const cohabitationAreaNumber = takeFirst(remaining, (l) => /cohabitation area number$/.test(l));
    const childFields = takeAll(remaining, (l) => l.includes('child') || l.includes('children'));

    const blocks = [];
    const hasBusinessSpecificLayout = !!(
      businessName
      || businessContactNumber
      || businessAddressSystem
      || businessStreetNumber
      || businessStreetName
      || businessBarangay
      || businessCity
      || businessProvince
      || businessFullAddress
      || initialOperationDate
      || businessRegistrationType
      || prevBusinessApprovalType
      || prevPlateNum
    );
    const hasTricycleSpecificLayout = !!(
      franchisee
      || plateNumber
      || bodyNumber
      || chassisNumber
      || motorNumber
      || orNumber
      || crNumber
      || vehicleMake
      || vehicleNamedToOwner
      || vehicleFranchise
    );
    const hasGeneralPermitLayout = !!(
      lotAddressSystem
      || lotStreetNumber
      || lotStreetName
      || lotBarangay
      || lotCity
      || lotProvince
      || location
      || projectLocation
      || lotFullAddress
      || proofAddressType
      || proofAddressNumber
    );
    const hasCohabitationLayout = !!(
      cohabitantFirstName
      || cohabitantMiddleName
      || cohabitantLastName
      || cohabitantSuffix
      || cohabitantBirthdate
      || cohabitantAge
      || cohabitantCivilStatus
      || cohabitantNationality
      || cohabitantRelationship
      || cohabitantIdType
      || cohabitantIdNumber
      || cohabitantAddress
      || cohabitationStart
      || cohabitationDuration
      || cohabitationDurationValue
      || cohabitationDurationUnit
      || cohabitationSameAddress
      || cohabitationAddress
      || cohabitationHouseNumber
      || cohabitationStreetName
      || cohabitationSubdivision
      || cohabitationBarangay
      || cohabitationMunicipality
      || cohabitationProvince
      || cohabitationAreaNumber
    );

    if (hasCohabitationLayout) {
      const cohabitationSameAddressValue = String(cohabitationSameAddress?.value || '').trim().toLowerCase();
      const isCohabitationSameAddress = ['on', 'yes', 'true', '1'].includes(cohabitationSameAddressValue);
      blocks.push(
        subsection('Request Details', [
          renderFieldGrid([{ ...purpose, wide: true }].filter(Boolean), 1),
          renderFieldGrid([applicationType, paymentMethod].filter(Boolean), 2),
          renderFieldGrid([gcashTransactionNumber].filter(Boolean), 1)
        ]),
        subsection('Cohabitant Information', [
          renderFieldGrid([cohabitantFirstName, cohabitantMiddleName, cohabitantLastName, cohabitantSuffix].filter(Boolean), 4),
          renderFieldGrid([cohabitantBirthdate, cohabitantAge, cohabitantCivilStatus, cohabitantNationality].filter(Boolean), 4),
          renderFieldGrid([cohabitantRelationship].filter(Boolean), 1),
          renderFieldGrid([cohabitantIdType, cohabitantIdNumber].filter(Boolean), 2),
          renderFieldGrid([cohabitantAddress].filter(Boolean), 1),
          renderFieldGrid([cohabitantUnitNumber, cohabitantHouseNumber, cohabitantStreetName].filter(Boolean), 3),
          renderFieldGrid([cohabitantLotNumber, cohabitantBlockNumber, cohabitantPhaseNumber].filter(Boolean), 3),
          renderFieldGrid([cohabitantSubdivision, cohabitantBarangay, cohabitantCity, cohabitantProvince].filter(Boolean), 4),
          renderFieldGrid([cohabitantPostalCode].filter(Boolean), 1)
        ]),
        subsection('Cohabitation Information', [
          renderFieldGrid([cohabitationStart, cohabitationDuration, cohabitationDurationValue, cohabitationDurationUnit].filter(Boolean), 2),
          renderFieldGrid([cohabitationSameAddress].filter(Boolean), 1),
          ...(
            isCohabitationSameAddress
              ? []
              : [
                  renderFieldGrid([cohabitationAddress].filter(Boolean), 1),
                  renderFieldGrid([cohabitationHouseNumber, cohabitationStreetName, cohabitationSubdivision].filter(Boolean), 3),
                  renderFieldGrid([cohabitationBarangay, cohabitationMunicipality, cohabitationProvince, cohabitationAreaNumber].filter(Boolean), 4)
                ]
          )
        ])
      );
      if (childFields.length) {
        const childGroups = new Map();
        childFields.forEach((field) => {
          const label = String(field?.label || '').trim();
          const match = label.match(/child\s+(\d+)\s+(name|age)/i);
          if (!match) return;
          const childNumber = match[1];
          const childPart = match[2].toLowerCase();
          if (!childGroups.has(childNumber)) {
            childGroups.set(childNumber, {});
          }
          childGroups.get(childNumber)[childPart] = field;
        });

        const childRows = Array.from(childGroups.entries())
          .sort((a, b) => Number.parseInt(a[0], 10) - Number.parseInt(b[0], 10))
          .map(([, parts]) => renderFieldGrid([parts.name, parts.age].filter(Boolean), 2))
          .filter(Boolean);

        const unmatchedChildFields = childFields.filter((field) => {
          const label = String(field?.label || '').trim();
          return !/child\s+\d+\s+(name|age)/i.test(label);
        });
        if (unmatchedChildFields.length) {
          childRows.push(renderFieldGrid(unmatchedChildFields.map((field) => ({ ...field, wide: true })), 1));
        }

        blocks.push(subsection('Children Information', childRows));
      }
    } else if (hasTricycleSpecificLayout) {
      blocks.push(
        subsection('Request Details', [
          renderFieldGrid([{ ...applicationType, wide: true }, { ...purpose, wide: true }].filter((f) => f?.value), 1),
          renderFieldGrid([{ ...franchisee, wide: true }].filter(Boolean), 1)
        ]),
        subsection('Vehicle Information', [
          renderFieldGrid([plateNumber, bodyNumber].filter(Boolean), 2),
          renderFieldGrid([chassisNumber, motorNumber].filter(Boolean), 2),
          renderFieldGrid([orNumber, crNumber].filter(Boolean), 2),
          renderFieldGrid([vehicleMake, vehicleNamedToOwner].filter(Boolean), 2),
          renderFieldGrid([{ ...vehicleFranchise, wide: true }].filter(Boolean), 1),
          renderFieldGrid([prevPlateNum].filter(Boolean), 1)
        ])
      );
    } else if (hasBusinessSpecificLayout) {
      const assembledPrevFullName = firstNonEmpty([
        prevFullName?.value,
        [prevLastName?.value, prevFirstName?.value, prevMiddleName?.value, prevSuffix?.value].filter(Boolean).join(' ').trim()
      ]);
      blocks.push(
        subsection('Request Details', [
          renderFieldGrid([
            { label: 'Application Type', value: applicationType?.value || '' },
            { label: 'Purpose', value: purpose?.value || '' }
          ].filter((f) => f.value), 2)
        ]),
        subsection('Applicant Information', [
          renderFieldGrid([ownerLastName, ownerFirstName, ownerMiddleName].filter(Boolean), 3)
        ]),
        subsection('Contact Details', [
          renderFieldGrid([ownerPhone].filter(Boolean), 1),
          renderFieldGrid([businessContactNumber].filter(Boolean), 1)
        ]),
        subsection('Business Information', [
          renderFieldGrid([businessName, owner, businessRegistrationType].filter(Boolean), 3),
          renderFieldGrid([initialOperationDate].filter(Boolean), 1),
          renderFieldGrid([
            assembledPrevFullName ? { label: 'Prev Full Name', value: assembledPrevFullName } : null,
            prevBusinessApprovalType,
            prevPlateNum
          ].filter(Boolean), 3)
        ]),
        subsection('Business Address', [
          renderFieldGrid([{ ...businessAddressSystem, wide: true }].filter(Boolean), 1),
          renderFieldGrid([businessStreetNumber, businessStreetName, businessSubdivision].filter(Boolean), 3),
          renderFieldGrid([businessBarangay, businessCity, businessProvince].filter(Boolean), 3),
          renderFieldGrid([{ ...businessFullAddress, wide: true }].filter(Boolean), 1)
        ])
      );
    } else if (hasGeneralPermitLayout) {
      blocks.push(
        subsection('Request Details', [
          renderFieldGrid([{ ...purpose, wide: true }].filter(Boolean), 1)
        ]),
        subsection('Applicant Information', [
          renderFieldGrid([ownerLastName, ownerFirstName, ownerMiddleName].filter(Boolean), 3)
        ]),
        subsection('Contact Details', [
          renderFieldGrid([ownerPhone].filter(Boolean), 1)
        ]),
        subsection('Applicant Address', [
          renderFieldGrid([ownerFullAddress].filter(Boolean), 1)
        ]),
        subsection('Property / Location Details', [
          renderFieldGrid([proofAddressType, proofAddressNumber].filter(Boolean), 2),
          renderFieldGrid(lotAddressSystem ? [{ ...lotAddressSystem, wide: true }] : [], 1),
          renderFieldGrid([lotStreetNumber, lotStreetName].filter(Boolean), 2),
          renderFieldGrid([lotBarangay, lotCity, lotProvince].filter(Boolean), 3),
          renderFieldGrid(location ? [{ ...location, wide: true }] : [], 1),
          renderFieldGrid(projectLocation ? [{ ...projectLocation, wide: true }] : [], 1),
          renderFieldGrid(lotFullAddress ? [{ ...lotFullAddress, wide: true }] : [], 1)
        ])
      );
    } else {
      blocks.push(
        subsection('Request Details', [
          purpose ? renderFieldGrid([{ ...purpose, wide: true }], 1) : '',
          renderFieldGrid([applicationType, paymentMethod].filter(Boolean), 2)
        ]),
        subsection('Applicant Information', [
          renderFieldGrid([ownerLastName, ownerFirstName, ownerMiddleName].filter(Boolean), 3)
        ]),
        subsection('Contact Details', [
          renderFieldGrid([ownerPhone].filter(Boolean), 1)
        ]),
        subsection('Applicant Address', [
          renderFieldGrid([ownerFullAddress].filter(Boolean), 1)
        ]),
        subsection('Other Details', [
          renderFieldGrid([proofAddressType, proofAddressNumber].filter(Boolean), 2)
        ])
      );
    }

    if (remaining.length) {
      blocks.push(subsection('Other Details', [
        renderFieldGrid(remaining.map((f) => ({ ...f, wide: true })), 1)
      ]));
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
    const skipDocKeys = new Set([
      'id_picture_url',
      'id_picture_path',
      'profile_image_url',
      'profile_image_path',
      'barangay_id_photo_url',
      'barangay_id_photo_path',
      'barangay_id_photo_capture',
      'id_picture_data_url',
      'payment_proof_path',
      'payment_proof_url'
    ]);
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

    if (payload && typeof payload === 'object') {
      Object.keys(payload).forEach((key) => {
        const normalizedKey = String(key || '').trim().toLowerCase();
        if (
          skipDocKeys.has(normalizedKey)
          || normalizedKey.includes('payment_proof')
          || normalizedKey.includes('paymentproof')
          || normalizedKey.includes('id_picture')
          || normalizedKey.includes('profile_image')
          || normalizedKey.includes('barangay_id_photo')
        ) {
          return;
        }
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

  function buildResidencyPurposeText(basePurpose, startRaw, yearsRaw, monthsRaw, fallbackDurationRaw = '') {
    const cleanedBase = String(basePurpose || '').replace(/\s*\(\s*since\b[^)]*\)\s*$/i, '').trim();
    const purposeBase = cleanedBase && cleanedBase.toUpperCase() !== 'PURPOSE'
      ? cleanedBase
      : 'RESIDENCY VERIFICATION';

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
    if (!startDisplay && years !== null && months !== null) {
      const now = new Date();
      const inferred = new Date(now.getFullYear(), now.getMonth(), 1);
      inferred.setMonth(inferred.getMonth() - months);
      inferred.setFullYear(inferred.getFullYear() - years);
      if (!Number.isNaN(inferred.getTime())) {
        startDisplay = inferred.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
      }
    }

    return startDisplay ? `${purposeBase} (SINCE ${startDisplay})` : purposeBase;
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

  function isTemplatePlaceholderToken(value) {
    return /^\$\{[^}]+\}$/.test(String(value || '').trim());
  }

  function previewEditable(key, value, fallback = 'Type here', extraClass = '') {
    const rawValue = String(value || '').trim();
    const rawFallback = String(fallback || '').trim();
    const resolvedText = rawValue || (isTemplatePlaceholderToken(rawFallback) ? '' : rawFallback);
    const text = resolvedText.toUpperCase();
    const cls = ['doc-editable', !text ? 'doc-editable--empty' : '', extraClass].filter(Boolean).join(' ');
    return `<span class="${cls}" contenteditable="true" data-edit-key="${esc(key)}">${text ? esc(text) : ''}</span>`;
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
    if (text.includes('barangayid')) return 'barangayid';
    if (text.includes('cohabitation')) return 'cohabitation';
    if (text.includes('indigency')) return 'indigency';
    if (text.includes('firsttime') || text.includes('jobseeker')) return 'firsttimejobseeker';
    if (text.includes('identity')) return 'identity';
    if (text.includes('generalcertificate') || text.includes('generalcertification')) return 'generalcertificate';
    if (text.includes('residency') || text.includes('residence')) return 'residency';
    if (text.includes('goodmoral')) return 'goodmoral';
    if (text.includes('electricalpermit') || text.includes('waterpermit') || text.includes('residentialpermit') || text.includes('residentialbuildingpermit') || text.includes('commercialpermit') || text.includes('commercialbuildingpermit')) return 'generalpermitclearance';
    if (text.includes('tricycle')) return 'tricycleclearance';
    if (text.includes('businesspermit') || text.includes('businessclearance') || text.includes('clearanceforbusinesspermit')) return 'businessclearance';
    if (text.includes('barangayclearance') || text.includes('barangaycertification') || text === 'clearance') return 'generic';
    return 'generic';
  }

  function canonicalDocumentFilterValue(value) {
    const raw = String(value || '').trim();
    if (!raw) {
      return '';
    }
    const lowered = raw.toLowerCase();
    if (
      lowered === '__clearances__'
      || lowered === '__clearance__'
      || lowered === '__clearance_issuance__'
      || lowered === 'clearance'
      || lowered === 'clearances'
    ) {
      return '__clearances__';
    }
    if (lowered === '__business__' || lowered === 'business' || lowered === 'business_monitoring' || lowered === 'businessclearance') {
      return '__business__';
    }
    if (lowered === '__certificates__' || lowered === '__certificate_issuance__' || lowered === 'certificate_issuance' || lowered === 'certificates') {
      return '__certificates__';
    }
    const key = raw.toLowerCase().replace(/[^a-z0-9]+/g, '');
    if (lowered === '__clr_business_permit__' || key === 'barangayclearanceforbusinesspermit' || key === 'clearanceforbusinesspermit') {
      return '__clr_business_permit__';
    }
    if (lowered === '__clr_tricycle_permit__' || key === 'barangayclearancefortricyclepermit' || key === 'clearancefortricyclepermit') {
      return '__clr_tricycle_permit__';
    }
    if (
      lowered === '__clr_electric_permit__'
      || key === 'barangayclearanceforelectricalpermit'
      || key === 'clearanceforelectricalpermit'
      || key === 'clearanceforelectricpermit'
    ) {
      return '__clr_electric_permit__';
    }
    if (lowered === '__clr_water_permit__' || key === 'barangayclearanceforwaterpermit' || key === 'clearanceforwaterpermit') {
      return '__clr_water_permit__';
    }
    if (
      lowered === '__clr_residential_permit__'
      || key === 'barangayclearanceforresidentialpermit'
      || key === 'clearanceforresidentialpermit'
    ) {
      return '__clr_residential_permit__';
    }
    if (
      lowered === '__clr_commercial_permit__'
      || key === 'barangayclearanceforcommercialpermit'
      || key === 'clearanceforcommercialpermit'
    ) {
      return '__clr_commercial_permit__';
    }
    if (lowered === '__cert_cohabitation__' || key === 'certificateofcohabitation') {
      return '__cert_cohabitation__';
    }
    if (lowered === '__cert_good_moral__' || key === 'certificateofgoodmoral' || key === 'goodmoral') {
      return '__cert_good_moral__';
    }
    if (
      lowered === '__cert_jail_visit__'
      || key === 'certificateofrelationshipforjailvisitation'
      || key === 'certificateforjailvisitation'
      || key === 'jailvisitation'
    ) {
      return '__cert_jail_visit__';
    }
    if (
      lowered === '__cert_first_time_job_seeker__'
      || key === 'firsttimejobseekercertificate'
      || key === 'certificateforfirsttimejobseeker'
      || key === 'firsttimejobseeker'
    ) {
      return '__cert_first_time_job_seeker__';
    }
    if (lowered === '__cert_residency__' || key === 'certificateofresidency' || key === 'certificateofresidence' || key === 'residency') {
      return '__cert_residency__';
    }
    if (lowered === '__cert_indigency__' || key === 'certificateofindigency' || key === 'indigency') {
      return '__cert_indigency__';
    }
    return normalizePreviewDocKey(raw) === 'barangayid' ? 'Barangay ID' : raw;
  }

  function normalizeBusinessApprovalType(value) {
    return normalizeBusinessApprovalTypes(value)[0] || '';
  }

  function normalizeBusinessApprovalTypes(value) {
    const rawValues = Array.isArray(value)
      ? value
      : String(value || '').split(',');
    const normalized = [];
    for (const entry of rawValues) {
      const token = String(entry || '')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');
      if (!token) continue;
      if ((token === 'not_banned' || token.includes('not_among_those_business')) && !normalized.includes('not_banned')) {
        normalized.push('not_banned');
        continue;
      }
      if ((token === 'no_objection' || token.includes('interposes_no_objection')) && !normalized.includes('no_objection')) {
        normalized.push('no_objection');
        continue;
      }
      if ((token === 'temporary_clearance' || token.includes('temporary_barangay_clearance')) && !normalized.includes('temporary_clearance')) {
        normalized.push('temporary_clearance');
      }
    }
    return normalized;
  }

  function encodeBusinessApprovalTypes(value) {
    return normalizeBusinessApprovalTypes(value).join(',');
  }

  function syncBusinessApprovalSelection(rawValue = '') {
    const selectedValues = new Set(normalizeBusinessApprovalTypes(rawValue));
    const hasTemporaryClearance = selectedValues.has('temporary_clearance');
    const hasPrimaryApproval = selectedValues.has('not_banned') || selectedValues.has('no_objection');

    actionBusinessApprovalOptions.forEach((option) => {
      const value = String(option.value || '').trim();
      option.checked = selectedValues.has(value);
      if (value === 'temporary_clearance') {
        option.disabled = hasPrimaryApproval;
        if (hasPrimaryApproval) option.checked = false;
        return;
      }
      option.disabled = hasTemporaryClearance;
      if (hasTemporaryClearance) option.checked = false;
    });

    const normalized = actionBusinessApprovalOptions
      .filter((option) => option.checked)
      .map((option) => option.value);

    if (actionBusinessApproval) {
      actionBusinessApproval.value = encodeBusinessApprovalTypes(normalized);
    }
  }

  function readBusinessApprovalSelection() {
    return encodeBusinessApprovalTypes(
      actionBusinessApprovalOptions
        .filter((option) => option.checked)
        .map((option) => option.value)
    );
  }

  function handleBusinessApprovalOptionChange(changedOption) {
    if (!changedOption) return;
    const currentValue = String(changedOption.value || '').trim();
    if (!currentValue) return;
    if (changedOption.checked && currentValue === 'temporary_clearance') {
      actionBusinessApprovalOptions
        .filter((option) => option.value === 'not_banned' || option.value === 'no_objection')
        .forEach((option) => { option.checked = false; });
    }
    if (changedOption.checked && (currentValue === 'not_banned' || currentValue === 'no_objection')) {
      actionBusinessApprovalOptions
        .filter((option) => option.value === 'temporary_clearance')
        .forEach((option) => { option.checked = false; });
    }
    syncBusinessApprovalSelection(readBusinessApprovalSelection());
  }

  actionBusinessApprovalOptions.forEach((option) => {
    option.addEventListener('change', () => {
      handleBusinessApprovalOptionChange(option);
    });
  });

  function isFirstTimeJobSeekerRow(row) {
    return normalizePreviewDocKey(row?.document_type || '') === 'firsttimejobseeker';
  }

  function requestRequiresInspection(row) {
    if (!row || isFirstTimeJobSeekerRow(row)) {
      return false;
    }
    const docToken = String(row?.document_type || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
    if (!docToken) {
      return false;
    }
    return [
      'barangayclearanceforbusinesspermit',
      'clearanceforbusinesspermit',
      'businesspermit',
      'barangaybusinessclearance',
      'businessclearance',
      'barangayclearanceforelectricalpermit',
      'clearanceforelectricalpermit',
      'electricalpermit',
      'barangayclearanceforwaterpermit',
      'clearanceforwaterpermit',
      'waterpermit',
      'barangayclearanceforresidentialpermit',
      'clearanceforresidentialpermit',
      'residentialpermit',
      'barangayclearanceforresidentialbuildingpermit',
      'clearanceforresidentialbuildingpermit',
      'residentialbuildingpermit',
      'barangayclearanceforcommercialpermit',
      'clearanceforcommercialpermit',
      'commercialpermit',
      'barangayclearanceforcommercialbuildingpermit',
      'clearanceforcommercialbuildingpermit',
      'commercialbuildingpermit'
    ].includes(docToken);
  }

  function requestRequiresFeeTagging(row) {
    if (!row || isFirstTimeJobSeekerRow(row)) {
      return false;
    }
    const docType = String(row?.document_type || '').toLowerCase().trim();
    if (!docType) {
      return false;
    }
    if (docType.includes('clearance')) {
      return true;
    }

    const docToken = docType.replace(/[^a-z0-9]+/g, '');
    return [
      'businesspermit',
      'electricalpermit',
      'waterpermit',
      'residentialpermit',
      'residentialbuildingpermit',
      'commercialpermit',
      'commercialbuildingpermit',
      'tricyclepermit'
    ].some((token) => docToken.includes(token));
  }

  function requestNeedsFeeTagging(row) {
    if (!requestRequiresFeeTagging(row)) {
      return false;
    }
    if (Array.isArray(row?.clearance_fees)) {
      return row.clearance_fees.length === 0;
    }
    const rawCount = String(row?.clearance_fee_count ?? '').trim();
    if (rawCount !== '') {
      const parsedCount = Number.parseInt(rawCount, 10);
      if (Number.isFinite(parsedCount)) {
        return parsedCount <= 0;
      }
    }
    return true;
  }

  function requestNeedsManualIssuedUpload(row) {
    return false;
  }

  function canOpenIssuedDocument(row) {
    if (!row) {
      return false;
    }
    const stageKey = String(row?.stage || '').toLowerCase();
    if (requestNeedsManualIssuedUpload(row)) {
      return ['ready_for_claim', 'completed'].includes(stageKey) && String(row?.issued_file_path || '').trim() !== '';
    }
    return ['payment_verified', 'for_printing', 'ready_for_claim', 'completed'].includes(stageKey);
  }

  function issuedDocumentFileUrl(requestId) {
    const id = String(requestId || '').trim();
    if (!id) return '';
    return `${appBase}/PhpFiles/Admin-End/documentRequestWorkflow.php?action=view_issued&request_id=${encodeURIComponent(id)}&_ts=${Date.now()}`;
  }

  function issuedDocumentUrl(requestId, row = null) {
    const id = String(requestId || '').trim();
    if (!id) return '';
    const action = normalizePreviewDocKey(row?.document_type || '') === 'barangayid'
      ? 'view_issued_card'
      : 'view_issued';
    return `${appBase}/PhpFiles/Admin-End/documentRequestWorkflow.php?action=${action}&request_id=${encodeURIComponent(id)}&_ts=${Date.now()}`;
  }

  function issuedDocumentTitle(row) {
    return normalizePreviewDocKey(row?.document_type || '') === 'barangayid'
      ? 'Digital Barangay ID'
      : 'Issued Document';
  }

  function barangayIdResidentSex(row, payload = {}, residentProfile = {}) {
    return firstNonEmpty([
      residentProfile.sex,
      row?.resident_profile?.sex,
      payload.card_sex,
      payload.sex,
      payload.gender,
      payload.child_sex,
      row?.sex
    ]);
  }

  function barangayIdVerificationUrl(row, payload = {}) {
    const requestId = String(firstNonEmpty([row?.request_id, payload.request_id]) || '').trim();
    if (!requestId) return '';
    const verificationCode = String(firstNonEmpty([
      row?.verification_code,
      payload.verification_code,
      requestId
    ]) || '').trim();
    const appOrigin = `${window.location.origin}${appBase}`;
    return `${appOrigin}/transactions?request_id=${encodeURIComponent(requestId)}&vc=${encodeURIComponent(verificationCode || requestId)}`;
  }

  function barangayIdQrPreviewUrl(row, payload = {}) {
    const existing = firstNonEmpty([row?.qr_code_path, payload.qr_code_path]);
    if (existing) {
      return resolvePublicUrl(existing);
    }
    const verifyUrl = barangayIdVerificationUrl(row, payload);
    if (!verifyUrl) return '';
    return `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(verifyUrl)}`;
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
    const residentialAddressRaw = pickMostSpecificAddress(
      getPersonal('Full Address', ''),
      payload.owner_full_address,
      payload.applicant_full_address,
      payload.full_address,
      payload.full_address_display,
      payload.address,
      payload.complete_address,
      residentProfile.full_address
    );
    const previewAmount = (() => {
      const raw = String(firstNonEmpty([
        row.amount,
        row.fee_amount,
        row.transaction_amount,
        payload.fee_amount,
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
    const businessApprovalType = encodeBusinessApprovalTypes(firstNonEmpty([
      payload._preview_business_approval_type,
      payload.business_approval_type,
      payload.businessApprovalType
    ]));
    const franchisee = firstNonEmpty([
      payload._preview_franchisee,
      payload.franchisee,
      payload.vehicle_franchise
    ]);
    const tricycleLocation = firstNonEmpty([
      payload._preview_toda_poda_location,
      payload.location_of_toda_poda,
      payload.location,
      franchisee
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
    const cohabitantName = firstNonEmpty([
      payload.cohabitant_full_name,
      [
        firstNonEmpty([payload.cohabitant_first]),
        firstNonEmpty([payload.cohabitant_middle]),
        firstNonEmpty([payload.cohabitant_last]),
        firstNonEmpty([payload.cohabitant_suffix])
      ].filter(Boolean).join(' ').replace(/\s+/g, ' ').trim()
    ]);
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
    const sexValue = barangayIdResidentSex(row, payload, residentProfile) || firstNonEmpty([payload.sex, payload.gender, residentProfile.sex]);
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
    const applicantAgeRaw = firstNonEmpty([
      parseAgeText(payload.age),
      parseAgeText(residentProfile.age),
      deriveAgeFromDate(applicantBirthdateRaw)
    ]);
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
    const requestedTemplateDocType = firstNonEmpty([
      payload.other_document_template_document_type,
      payload.template_document_type,
      requestedDocType
    ]);
    const requestedDocKey = normalizePreviewDocKey(requestedTemplateDocType);
    const isBarangayIdDocument = requestedDocKey === 'barangayid';
    const documentValidityDate = isBarangayIdDocument
      ? resolveBarangayIdValidityDate(
          firstNonEmpty([
            payload.barangay_id_valid_until,
            payload.valid_until,
            payload.document_validity,
            row.document_validity
          ]),
          barangayIdDefaultValidityDate()
        )
      : (isCertificatePreviewDocKey(requestedDocKey)
          ? resolveCertificateValidityDate(
              firstNonEmpty([
                payload.document_validity,
                row.document_validity
              ]),
              certificateDefaultValidityDate()
            )
          : '');
    const generalPermitPurpose = generalClearancePurposeFromDocType(requestedTemplateDocType);
    const generalPermitLocation = buildGeneralPermitLocation(
      payload,
      firstNonEmpty([
        payload.applicant_full_address,
        payload.full_address,
        payload.full_address_display,
        residentProfile.full_address
      ])
    );
    const generalPermitRemarks = firstNonEmpty([payload.remarks, payload.remark]);
    const knownPayloadKeys = new Set([
      'action', 'csrf_token', 'redirect', 'document_type',
      'custom_document_title', 'other_document_fee', 'other_document_template_id', 'other_document_template_document_type',
      'other_document_template_kind', 'other_document_template_label', 'template_document_type',
      'last_name', 'lastname', 'first_name', 'firstname', 'middle_name', 'middlename', 'suffix', 'suffix_name',
      'contact_number', 'phone_number', 'full_address', 'full_address_display', 'address', 'complete_address',
      'birthdate', 'date_of_birth', 'child_dob', 'age', 'sex', 'gender', 'child_sex',
      'civil_status', 'religion', 'occupation',
      'purpose', 'request_purpose', 'request_officer',
      'submission_target_type', 'government_official_id', 'government_position_group', 'government_position_other',
      'government_position_detail', 'government_official_other', 'government_office', 'government_position', 'government_official',
      'institution_name', 'institution_person', 'institution_position',
      'request_officer_line1', 'request_officer_line2', 'request_officer_line3',
      'document_validity', 'barangay_id_valid_until', 'valid_until', 'barangay_id_validity_years',
      'business_name', 'businessName', 'business_trade_name', 'trade_name', 'establishment_name', 'business_establishment',
      '_preview_business_approval_type', 'business_approval_type', 'businessApprovalType',
      '_preview_plate_number', 'plate_number', 'business_plate_number', 'vehicle_plate_number',
      '_preview_franchisee', 'franchisee', 'vehicle_franchise', '_preview_toda_poda_location', 'location_of_toda_poda', '_preview_vehicle_type', 'vehicle_make', 'type_of_vehicle',
      '_preview_registration_number', 'registration_number', 'cr_number', 'or_number', '_preview_body_number', 'body_number',
      'vehicle_named_to_owner', 'applicant_last_name', 'applicant_first_name', 'applicant_middle_name', 'applicant_suffix',
      'applicant_contact_number', 'applicant_full_address',
      'lot_same_address', 'lot_address_system', 'lot_unit_number', 'lot_street_number', 'lot_street_name',
      'lot_subdivision', 'lot_number', 'block_number', 'lot_phase_number', 'lot_barangay', 'lot_city', 'lot_province',
      'project_location', 'ownership_type', 'application_type', 'applicationType', 'business_type', 'businessType',
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

    const inferredBusinessClearance = requestedDocKey === 'generic' && (
      !!businessName
      || /business\s+permit/i.test(firstNonEmpty([row.purpose, payload.request_purpose, payload.purpose]))
    );
    const barangayIdDigitalState = (() => {
      if (!isBarangayIdDocument || !window.BarangayIdDigital || typeof window.BarangayIdDigital.createState !== 'function') {
        return {};
      }
      const templateConfig = barangayIdTemplateConfigCache && typeof barangayIdTemplateConfigCache === 'object'
        ? barangayIdTemplateConfigCache
        : {};
      const fallbackFrontTemplateUrl = `${appBase}/Resident-End/Certificates/BarangayID/FRONT_EMPTY.png?v=${Date.now()}`;
      const fallbackBackTemplateUrl = `${appBase}/Resident-End/Certificates/BarangayID/BACK_EMPTY.png?v=${Date.now()}`;
      return window.BarangayIdDigital.createState({
        appBase,
        row: {
          ...row,
          qr_code_path: barangayIdQrPreviewUrl(row, payload),
          sex: sexValue || row?.sex || '',
          punong_signatory_name: templateConfig.punongSignatoryName || row?.punong_signatory_name || '',
          punong_signatory_title: templateConfig.punongSignatoryTitle || row?.punong_signatory_title || '',
          punong_signatory_signature_path: templateConfig.punongSignatorySignatureUrl || row?.punong_signatory_signature_path || ''
        },
        payload: {
          ...payload,
          qr_code_path: barangayIdQrPreviewUrl(row, payload),
          sex: sexValue || payload.sex || payload.gender || '',
          gender: sexValue || payload.gender || payload.sex || '',
          card_sex: sexValue || payload.card_sex || ''
        },
        residentProfile: {
          ...residentProfile,
          sex: sexValue || residentProfile.sex || ''
        },
        frontTemplateUrl: templateConfig.frontTemplateUrl || fallbackFrontTemplateUrl,
        backTemplateUrl: templateConfig.backTemplateUrl || fallbackBackTemplateUrl,
        templateVariant: templateConfig.templateVariant || 'empty',
        layoutConfig: templateConfig.layoutConfig || null,
        fallbackProfileImageUrl: `${appBase}/Images/Profile-Placeholder.png`,
      });
    })();

    const basePurposeText = generalPermitPurpose || firstNonEmpty([row.purpose, payload.purpose, payload.request_purpose, '']);
    const generalCertificationType = String(firstNonEmpty([
      payload.manual_document_variant,
      requestedDocType
    ]) || '').trim();
    const isGeneralCertificationVariant = /^general\s+certificat(?:e|ion)\b/i.test(generalCertificationType);
    const residencyPurposeText = requestedDocKey === 'residency' && !isGeneralCertificationVariant
      ? buildResidencyPurposeText(
          basePurposeText,
          firstNonEmpty([payload.barangay_residency, residentProfile.barangay_residency]),
          firstNonEmpty([payload.years_of_residency]),
          firstNonEmpty([payload.months_of_residency]),
          firstNonEmpty([payload.residency_duration, residentProfile.residency_duration])
        )
      : (isGeneralCertificationVariant
          ? String(basePurposeText || '').replace(/\s*\(\s*since\b[^)]*\)\s*$/i, '').trim()
          : basePurposeText);
    const submissionTargetType = String(firstNonEmpty([payload.submission_target_type]) || '').trim().toLowerCase();
    const explicitRequestOfficerLines = [
      upperText(firstNonEmpty([payload.request_officer_line1]), ''),
      upperText(firstNonEmpty([payload.request_officer_line2]), ''),
      upperText(firstNonEmpty([payload.request_officer_line3]), '')
    ];
    const institutionRequestOfficerLines = [
      upperText(firstNonEmpty([payload.institution_person]), ''),
      upperText(firstNonEmpty([payload.institution_position]), ''),
      upperText(firstNonEmpty([payload.institution_name]), '')
    ].filter(Boolean);
    const governmentRequestOfficerLines = [
      upperText(firstNonEmpty([payload.government_official, payload.government_official_other]), ''),
      upperText(firstNonEmpty([payload.government_position, payload.government_position_detail]), ''),
      upperText(firstNonEmpty([payload.government_office, payload.government_position_group]), '')
    ].filter(Boolean);
    const derivedRequestOfficerLines = (() => {
      if (submissionTargetType === 'institution' && institutionRequestOfficerLines.length) {
        return institutionRequestOfficerLines;
      }
      if (submissionTargetType === 'government_official' && governmentRequestOfficerLines.length) {
        return governmentRequestOfficerLines;
      }
      const explicitLines = explicitRequestOfficerLines.filter(Boolean);
      if (explicitLines.length) {
        return explicitLines;
      }
      return [];
    })();
    const requestOfficerLine1Text = derivedRequestOfficerLines[0] || '';
    const requestOfficerLine2Text = derivedRequestOfficerLines[1] || '';
    const requestOfficerLine3Text = derivedRequestOfficerLines[2] || '';
    const requestOfficerText = derivedRequestOfficerLines.length
      ? derivedRequestOfficerLines.join(' - ')
      : upperText(firstNonEmpty([
          payload.request_officer,
          [
            payload.government_official,
            payload.government_position || payload.government_position_detail,
            payload.government_office || payload.government_position_group
          ].filter(Boolean).join(' - ')
        ]), '');

    return {
      ...barangayIdDigitalState,
      templateDocType: normalizeDocumentTypeDisplay(requestedTemplateDocType),
      documentTitleOverride: upperText(firstNonEmpty([payload.custom_document_title]), ''),
      docType: inferredBusinessClearance
        ? 'Barangay Clearance for Business Permit'
        : (firstNonEmpty([payload.custom_document_title])
          ? String(payload.custom_document_title).trim()
          : normalizeDocumentTypeDisplay(requestedDocType)),
      fullName: upperText(
        formatPersonNameFnMiLn(
          getPersonal('First Name', ''),
          getPersonal('Middle Name', ''),
          getPersonal('Last Name', '')
        ),
        fullNameFromRow(row)
      ),
      contactNumber: upperText(firstNonEmpty([
        payload.contact_number,
        payload.phone_number,
        row.contact_number,
        residentProfile.phone_number
      ]), ''),
      fullAddress: upperText(stripAreaFromAddress(residentialAddressRaw || '-'), '-'),
      purpose: upperText(residencyPurposeText, '-'),
      businessName: upperText(stripTemplateTokens(businessName || ''), ''),
      businessType: upperText(stripTemplateTokens(firstNonEmpty([payload.business_type, payload.businessType])), ''),
      businessAddress: upperText(stripTemplateTokens(businessAddress || ''), ''),
      businessApprovalType,
      franchisee: upperText(franchisee || '', ''),
      tricycleLocation: upperText(tricycleLocation || '', ''),
      vehicleType: upperText(vehicleType || '', ''),
      registrationNumber: upperText(registrationNumber || '', ''),
      plateNumber: upperText(plateNumber || '', ''),
      bodyNumber: upperText(bodyNumber || '', ''),
      tricycleApplicationType: upperText(firstNonEmpty([payload.application_type, payload.applicationType]), ''),
      operatorName: upperText(stripTemplateTokens(firstNonEmpty([
        payload.operator_name,
        payload.business_operator_name,
        fullNameFromRow(row)
      ])), ''),
      operatorAddress: upperText(stripTemplateTokens(composeLocalityAddress(pickMostSpecificAddress(
        payload.owner_full_address,
        payload.applicant_full_address,
        payload.operator_address,
        residentialAddressRaw,
        residentProfile.full_address,
        payload.full_address,
        payload.full_address_display,
        payload.address,
        payload.complete_address
      ))), ''),
      amount: upperText(previewAmount, ''),
      documentFieldVisibility: row.document_field_visibility && typeof row.document_field_visibility === 'object'
        ? row.document_field_visibility
        : {},
      issuedAt: upperText(stripTemplateTokens(firstNonEmpty([
        payload.issued_at,
        payload.issuedAt,
        'BARANGAY SAN JOSE'
      ])), 'BARANGAY SAN JOSE'),
      issuedOn: upperText(previewDateText(firstNonEmpty([
        row.finance_decision_at,
        row.release_timestamp,
        row.completed_at,
        row.ready_at,
        row.submitted_at
      ])), ''),
      issuedDate: firstNonEmpty([
        row.finance_decision_at,
        row.release_timestamp,
        row.completed_at,
        row.ready_at,
        row.submitted_at,
        dr_now_text()
      ]),
      documentValidity: documentValidityDate,
      approvedByName: upperText(firstNonEmptyName([
        row.punong_signatory_name
      ]), 'HON. GLENN S. EVANGELISTA'),
      punongSignatoryName: upperText(firstNonEmptyName([
        row.punong_signatory_name
      ]), 'HON. GLENN S. EVANGELISTA'),
      punongSignatoryTitle: firstNonEmpty([
        row.punong_signatory_title,
        'Punong Barangay'
      ]) || 'Punong Barangay',
      punongSignatorySignatureUrl: resolvePublicUrl(firstNonEmpty([
        row.punong_signatory_signature_path
      ])),
      secretarySignatoryName: upperText(firstNonEmptyName([
        row.secretary_signatory_name
      ]), ''),
      secretarySignatoryTitle: firstNonEmpty([
        row.secretary_signatory_title,
        'Barangay Secretary'
      ]) || 'Barangay Secretary',
      monitoringSignatoryName: upperText(firstNonEmptyName([
        row.monitoring_signatory_name
      ]), 'MR. JOSEPH C. PATRICIO'),
      monitoringSignatoryTitle: firstNonEmpty([
        row.monitoring_signatory_title,
        'Head, Monitoring & Collection Dept.'
      ]) || 'Head, Monitoring & Collection Dept.',
      monitoringSignatorySignatureUrl: resolvePublicUrl(firstNonEmpty([
        row.monitoring_signatory_signature_path
      ])),
      requestOfficer: requestOfficerText,
      requestOfficerLine1: requestOfficerLine1Text,
      requestOfficerLine2: requestOfficerLine2Text,
      requestOfficerLine3: requestOfficerLine3Text,
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
      applicantResidenceAddress: upperText(residentialAddressRaw, ''),
      cohabitantResidenceAddress: upperText(buildCohabitantAddress(payload, firstNonEmpty([payload.full_address, payload.full_address_display, residentProfile.full_address])), ''),
      cohabitationResidenceAddress: upperText(buildCohabitationAddress(payload, firstNonEmpty([payload.full_address, payload.full_address_display, residentProfile.full_address])), ''),
      remarks: upperText(generalPermitRemarks, ''),
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
    const templateDocType = String(state.templateDocType || docType).trim() || docType;
    const customDocumentTitle = String(state.documentTitleOverride || '').trim();
    const docKey = normalizePreviewDocKey(templateDocType);
    const isIndigency = docKey === 'indigency';
    const isBarangayId = docKey === 'barangayid';
    const isGeneralPermitClearance = docKey === 'generalpermitclearance';
    const isBusinessPermitClearance = docKey === 'businessclearance';
    const isTricyclePermitClearance = docKey === 'tricycleclearance';
    const isGoodMoral = docKey === 'goodmoral';
    const isResidency = docKey === 'residency';
    const isGeneralCertification = /^general\s+certificat(?:e|ion)\b/i.test(templateDocType);
    const usesResidencyTemplate = isResidency || isGeneralCertification;
    const isCohabitation = docKey === 'cohabitation';
    const isFirstTimeJobSeeker = docKey === 'firsttimejobseeker';
    const isRelationshipJailVisit = isCohabitation
      && ['relationship_jail_visit', 'conjugal_visit'].includes(String(state.cohabitationVariant || '').trim().toLowerCase());
    const fullName = String(state.fullName || '-').trim() || '-';
    const fullAddress = String(state.fullAddress || '-').trim() || '-';
    const purpose = String(state.purpose || '-').trim() || '-';
    const location = String(state.location || '').trim();
    const franchisee = String(state.franchisee || '').trim();
    const tricycleLocation = String(state.tricycleLocation || franchisee || '').trim();
    const vehicleType = String(state.vehicleType || '').trim();
    const registrationNumber = String(state.registrationNumber || '').trim();
    const issuedDateWord = previewIndigencyIssuedText(state.issuedDate || '');
    const certificateNumber = String(state.certificateNumber || '').trim();
    const contactNumber = String(state.contactNumber || '').trim();
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
    const businessApprovalTypes = normalizeBusinessApprovalTypes(state.businessApprovalType || '');
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
    const ftjsSignedDateText = signedDateText && signedDateText !== 'DATE' ? signedDateText : 'MM/DD/YYYY';
    const approvedByNameText = String(state.approvedByName || 'HON. GLENN S. EVANGELISTA').trim() || 'HON. GLENN S. EVANGELISTA';
    const punongSignatoryNameText = String(state.punongSignatoryName || approvedByNameText).trim() || approvedByNameText;
    const punongSignatoryTitleText = String(state.punongSignatoryTitle || 'Punong Barangay').trim() || 'Punong Barangay';
    const punongSignatorySignatureUrl = String(state.punongSignatorySignatureUrl || '').trim();
    const secretarySignatoryNameText = String(state.secretarySignatoryName || '').trim() || '-';
    const secretarySignatoryTitleText = String(state.secretarySignatoryTitle || 'Barangay Secretary').trim() || 'Barangay Secretary';
    const monitoringSignatoryNameText = String(state.monitoringSignatoryName || 'MR. JOSEPH C. PATRICIO').trim() || 'MR. JOSEPH C. PATRICIO';
    const monitoringSignatoryTitleText = String(state.monitoringSignatoryTitle || 'Head, Monitoring & Collection Dept.').trim() || 'Head, Monitoring & Collection Dept.';
    const monitoringSignatorySignatureUrl = String(state.monitoringSignatorySignatureUrl || '').trim();
    const issuedBase = firstNonEmpty([state.issuedOn, state.issuedDate, formatDateInputValue(new Date())]);
    const documentValidityText = resolveCertificateValidityDate(
      state.documentValidity || '',
      certificateDefaultValidityDate(issuedBase),
      issuedBase
    );
    const certificateValidityDays = (() => {
      const resolvedDays = diffCalendarDays(issuedBase, documentValidityText);
      return resolvedDays === null ? 45 : Math.max(0, resolvedDays);
    })();
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
      if (text && text !== '-') return text;
      const fallbackText = String(fallback || '').trim();
      return isTemplatePlaceholderToken(fallbackText) ? '' : fallbackText;
    };
    const generalClearanceIssuedAt = upperText(firstNonEmpty([state.issuedAt, 'BARANGAY SAN JOSE']), '');
    const generalClearanceIssuedOn = upperText(previewDateText(firstNonEmpty([state.issuedOn, state.issuedDate])), '');
    const generalClearanceMetaValue = (value, fallback = '') => {
      const text = templateSafe(value, fallback);
      return `
        <span class="doc-preview-generalclearance-meta-line${text ? ' doc-preview-generalclearance-meta-line--filled' : ''}">
          ${text ? `<span class="doc-preview-generalclearance-meta-line-text">${esc(text)}</span>` : ''}
        </span>
      `;
    };
    const businessMetaValue = (key, value) => {
      const text = String(value || '').trim();
      if (!text) return '<span class="doc-preview-business-meta-line"></span>';
      return previewEditable(key, text, '_____');
    };
    const documentFieldVisible = (key) => {
      const visibility = state.documentFieldVisibility && typeof state.documentFieldVisibility === 'object'
        ? state.documentFieldVisibility
        : {};
      return !Object.prototype.hasOwnProperty.call(visibility, key) || Boolean(visibility[key]);
    };
    const businessMetaRow = (settingKey, label, valueHtml) => {
      const renderedValue = settingKey && !documentFieldVisible(settingKey)
        ? '<span class="doc-preview-business-meta-line"></span>'
        : valueHtml;
      return `
        <div class="doc-preview-business-meta-row">
          <div class="doc-preview-business-meta-label"><strong>${esc(label)}</strong></div>
          <div class="doc-preview-business-meta-colon">:</div>
          <div class="doc-preview-business-meta-value">${renderedValue}</div>
        </div>
      `;
    };
    const businessCheckMark = (type) => {
      const selected = businessApprovalTypes.includes(type);
      return `
        <span class="doc-preview-business-check-mark${selected ? ' doc-preview-business-check-mark--selected' : ''}">
          <span class="doc-preview-business-check-line"></span>
          <span class="doc-preview-business-check-tick">✓</span>
          <span class="doc-preview-business-check-line"></span>
        </span>
      `;
    };
    const renderSignatureInk = (url, alt, extraClass = '') => {
      const classes = ['doc-preview-signature-ink'];
      if (extraClass) classes.push(extraClass);
      if (!url) {
        return `<div class="${classes.join(' ')}"></div>`;
      }
      return `<div class="${classes.join(' ')}"><img src="${esc(url)}" alt="${esc(alt)}"></div>`;
    };
    const fullAddressWithBarangay = composeBarangayAddress(fullAddress);
    const applicantAddressWithBarangay = composeBarangayAddress(applicantResidenceAddress || fullAddress);
    const cohabitationHasChildren = isCohabitation && cohabitationChildrenCount > 0;
    const barangayIdExtraDetails = additionalDetailRows(
      Array.isArray(state.additionalDetails)
        ? state.additionalDetails.filter((entry) => String(entry?.label || '').toLowerCase().startsWith('emergency '))
        : []
    );

    const residencyRows = `
      <div class="doc-to-block"><strong>Name</strong><strong>:</strong><strong>${previewEditable('fullName', safe(fullName), '${FULL_NAME}')}</strong></div>
      <div class="doc-to-block"><strong>Address</strong><strong>:</strong><div><strong>${previewEditable('fullAddress', safe(fullAddress), '${ADDRESS}', 'doc-editable-multiline')}</strong><br><strong>BARANGAY SAN JOSE, MONTALBAN, RIZAL</strong></div></div>
      <div class="doc-to-block"><strong>Birthday</strong><strong>:</strong><strong>${previewEditable('birthdate', safe(birthdate, '${Birthdate}'), '${Birthdate}')}</strong></div>
      <div class="doc-to-block"><strong>Birthplace</strong><strong>:</strong><strong>${previewEditable('birthplace', safe(birthplace, '${Birthplace}'), '${Birthplace}')}</strong></div>
      <div class="doc-to-block"><strong>Remarks</strong><strong>:</strong><strong>${previewEditable('remarks', templateSafe(remarks, '${REMARKS}'), '${REMARKS}')}</strong></div>
      <div class="doc-to-block"><strong>Purpose</strong><strong>:</strong><strong>${previewEditable('purpose', safe(purpose, '${PURPOSE}'), '${PURPOSE}')}</strong></div>
    `;

    const buildIssuedLine = (wrapBeforeLocality = false) => {
      const officeText = wrapBeforeLocality
        ? 'at the office of the Punong Barangay, Barangay<br>San Jose, Montalban, Rizal'
        : 'at the office of the Punong Barangay, Barangay San Jose, Montalban, Rizal';
      return `Issued this <strong>${esc(issuedDateWord)}</strong> ${officeText}`;
    };
    const buildSharedIssuedMetaRows = () => ([
      { label: 'CTC No.:', value: '_____' },
      { label: 'Issued at:', value: '_____' },
      { label: 'Issued On:', value: '_____' },
      { label: 'OR No.:', value: '_____' },
    ]);
    const buildCertificateFooterNote = () => {
      const dayWord = sentenceCaseWords(numberToWords(certificateValidityDays || 0)) || 'Forty-five';
      const dayLabel = Number(certificateValidityDays || 0) === 1 ? 'day' : 'days';
      return `This is valid ${esc(dayWord)} (${esc(String(certificateValidityDays || 0))}) ${dayLabel} from the date of issue, check the<br>QR Code to verify the authenticity of the document`;
    };

    let contentHtml = '';
    let titleHtml = '<div class="doc-preview-goodmoral-office"><div>TANGGAPAN NG PUNONG BARANGAY</div><div>BARANGAY CERTIFICATION</div></div>';
    let issuedLine = buildIssuedLine();
    let metaHtml = renderPreviewMetaRows(buildSharedIssuedMetaRows());

    if (isIndigency) {
      const indigencyPurpose = safe(purpose || requestFor, 'PURPOSE');
      const indigencyOfficerLines = [requestOfficerLine1, requestOfficerLine2, requestOfficerLine3].filter((line) => safe(line, '') !== '');
      const indigencyOfficerLineHtml = indigencyOfficerLines.length
        ? indigencyOfficerLines.map((line, index) => `
            <div><strong>${previewEditable(`requestOfficerLine${index + 1}`, safe(line, ''), index === 1 ? 'Position / Department' : `Line ${index + 1}`)}</strong></div>
          `).join('')
        : `
            <div><strong>${previewEditable('requestOfficerLine1', '${REQUEST_OFFICER_LINE1}', 'Line 1')}</strong></div>
            <div><strong>${previewEditable('requestOfficerLine2', '${REQUEST_OFFICER_LINE2}', 'Position / Department')}</strong></div>
            <div><strong>${previewEditable('requestOfficerLine3', '${REQUEST_OFFICER_LINE3}', 'Line 3')}</strong></div>
          `;
      const toBlock = `
        <div class="doc-to-block">
          <strong>TO</strong><strong>:</strong>
          <div class="doc-to-lines">
            ${indigencyOfficerLineHtml}
          </div>
        </div>
      `;
      titleHtml = '<div class="doc-preview-title doc-preview-title--indigency"><div class="office">TANGGAPAN NG PUNONG BARANGAY</div><div class="certificate">CERTIFICATE OF INDIGENCY</div></div>';
      contentHtml = `
        ${toBlock}
        <p>
          This is to certify that <strong>${previewEditable('fullName', safe(fullName, '${FULL_NAME}'), '${FULL_NAME}')}</strong>, resident of <strong>${previewEditable('fullAddress', safe(fullAddress, '${ADDRESS}'), '${ADDRESS}', 'doc-editable-multiline')}</strong>, Barangay San Jose, Montalban, Rizal belongs to one of the indigent families of this Barangay. The income of this family is barely enough to meet their day-to-day needs.
        </p>
        <p>
          This certification is being issued upon the request of the above subject in person in connection with his/her application for
          <strong>${previewEditable('purpose', indigencyPurpose, 'PURPOSE')}</strong> purposes only.
        </p>
      `;
      metaHtml = '';
      issuedLine = buildIssuedLine();
    } else if (isBarangayId) {
      if (window.BarangayIdDigital && typeof window.BarangayIdDigital.render === 'function') {
        return window.BarangayIdDigital.render(state, {
          eyebrow: 'Initial Barangay ID Preview',
          helper: 'Review the resident information on the actual front and back ID layout before creating the record.',
          frontLabel: 'ID Front',
          backLabel: 'ID Back',
        });
      }
      titleHtml = '<div class="doc-preview-goodmoral-office"><div>TANGGAPAN NG PUNONG BARANGAY</div><div>BARANGAY ID APPLICATION</div></div>';
      contentHtml = `
        <p><strong>APPLICATION REVIEW</strong></p>
        <p>
          This preview summarizes the submitted Barangay ID application details for staff review before release preparation.
        </p>
        <div class="doc-to-block"><strong>Name</strong><strong>:</strong><strong>${previewEditable('fullName', safe(fullName, '-'), '-')}</strong></div>
        <div class="doc-to-block"><strong>Address</strong><strong>:</strong><div><strong>${previewEditable('fullAddress', safe(fullAddress, '-'), '-', 'doc-editable-multiline')}</strong><br><strong>BARANGAY SAN JOSE, MONTALBAN, RIZAL</strong></div></div>
        <div class="doc-to-block"><strong>Birthdate</strong><strong>:</strong><strong>${previewEditable('birthdate', safe(birthdate, '-'), '-')}</strong></div>
        <div class="doc-to-block"><strong>Birthplace</strong><strong>:</strong><strong>${previewEditable('birthplace', safe(birthplace, '-'), '-')}</strong></div>
        <div class="doc-to-block"><strong>Contact Number</strong><strong>:</strong><strong>${esc(safe(contactNumber, '-'))}</strong></div>
        ${barangayIdExtraDetails}
      `;
      issuedLine = 'After approval, the Barangay ID will be generated from the approved front and back template with QR verification.';
      metaHtml = '';
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
            <div class="doc-preview-generalclearance-field-value"><strong>${previewEditable('fullName', templateSafe(fullName, '${FULL_NAME}'), '${FULL_NAME}')}</strong></div>
          </div>
          <div class="doc-preview-generalclearance-field doc-preview-generalclearance-field--address">
            <strong class="doc-preview-generalclearance-field-label">Residential Address</strong>
            <strong class="doc-preview-generalclearance-field-colon">:</strong>
            <div class="doc-preview-generalclearance-field-value"><strong>${previewEditable('fullAddress', templateSafe(fullAddress, '${ADDRESS}'), '${ADDRESS}', 'doc-editable-multiline')}</strong><br><strong>Barangay San Jose, Montalban, Rizal</strong></div>
          </div>
          <div class="doc-preview-generalclearance-field">
            <strong class="doc-preview-generalclearance-field-label">Location</strong>
            <strong class="doc-preview-generalclearance-field-colon">:</strong>
            <div class="doc-preview-generalclearance-field-value"><strong>${previewEditable('location', templateSafe(location, '${LOCATION}'), '${LOCATION}', 'doc-editable-multiline')}</strong></div>
          </div>
          <div class="doc-preview-generalclearance-field">
            <strong class="doc-preview-generalclearance-field-label">Remarks</strong>
            <strong class="doc-preview-generalclearance-field-colon">:</strong>
            <div class="doc-preview-generalclearance-field-value"><strong>${previewEditable('remarks', templateSafe(remarks, '${REMARKS}'), '${REMARKS}')}</strong></div>
          </div>
          <div class="doc-preview-generalclearance-field">
            <strong class="doc-preview-generalclearance-field-label">Purpose</strong>
            <strong class="doc-preview-generalclearance-field-colon">:</strong>
            <div class="doc-preview-generalclearance-field-value"><strong>${previewEditable('purpose', templateSafe(purpose, '${PURPOSE}'), '${PURPOSE}')}</strong></div>
          </div>
        </div>
        <p class="doc-preview-generalclearance-note">
          This clearance is being issued pursuant to Barangay Revenue Code ORDINANCE
          <span class="doc-preview-generalclearance-note-nowrap">NO. 11 - 2019</span>
        </p>
      `;
      issuedLine = `Issued this <strong>${esc(issuedDateWord)}</strong> at the office of the Punong Barangay, Barangay San Jose, Montalban, Rizal`;
      metaHtml = `
        <div class="doc-preview-generalclearance-meta">
          <div class="doc-preview-generalclearance-meta-row">
            <div class="doc-preview-generalclearance-meta-label"><strong>CTC No.</strong></div>
            <div class="doc-preview-generalclearance-meta-colon">:</div>
            <div class="doc-preview-generalclearance-meta-value">${generalClearanceMetaValue(certificateNumber, '${CERTIFICATE_NUMBER}')}</div>
          </div>
          <div class="doc-preview-generalclearance-meta-row">
            <div class="doc-preview-generalclearance-meta-label"><strong>Issued at</strong></div>
            <div class="doc-preview-generalclearance-meta-colon">:</div>
            <div class="doc-preview-generalclearance-meta-value">${generalClearanceMetaValue(generalClearanceIssuedAt)}</div>
          </div>
          <div class="doc-preview-generalclearance-meta-row">
            <div class="doc-preview-generalclearance-meta-label"><strong>Issued On</strong></div>
            <div class="doc-preview-generalclearance-meta-colon">:</div>
            <div class="doc-preview-generalclearance-meta-value">${generalClearanceMetaValue(generalClearanceIssuedOn)}</div>
          </div>
          <div class="doc-preview-generalclearance-meta-row">
            <div class="doc-preview-generalclearance-meta-label"><strong>Amount</strong></div>
            <div class="doc-preview-generalclearance-meta-colon">:</div>
            <div class="doc-preview-generalclearance-meta-value">${generalClearanceMetaValue(amount, '${AMOUNT}')}</div>
          </div>
          <div class="doc-preview-generalclearance-meta-row">
            <div class="doc-preview-generalclearance-meta-label"><strong>OR No.</strong></div>
            <div class="doc-preview-generalclearance-meta-colon">:</div>
            <div class="doc-preview-generalclearance-meta-value">${generalClearanceMetaValue(state.orNumber, '${OR_NUMBER}')}</div>
          </div>
        </div>
      `;
    } else if (isTricyclePermitClearance) {
      titleHtml = '<div class="doc-preview-tricycle-office"><div>TANGGAPAN NG PUNONG BARANGAY</div><div>BARANGAY CLEARANCE</div></div>';
      contentHtml = `
        <p class="doc-preview-tricycle-lead"><strong>TO WHOM IT MAY CONCERN::</strong></p>
        <p class="doc-preview-tricycle-intro">
          This is to certify that the person whose name and thumb mark appears here on has
          requested a Barangay Clearance from this office and the information are listed below:
        </p>
        <div class="doc-preview-tricycle-fields">
          <div class="doc-preview-tricycle-field">
            <strong class="doc-preview-tricycle-field-label">Name</strong>
            <strong class="doc-preview-tricycle-field-colon">:</strong>
            <div class="doc-preview-tricycle-field-value"><strong>${previewEditable('fullName', templateSafe(fullName, '${FULL_NAME}'), '${FULL_NAME}')}</strong></div>
          </div>
          <div class="doc-preview-tricycle-field doc-preview-tricycle-field--address">
            <strong class="doc-preview-tricycle-field-label">Address</strong>
            <strong class="doc-preview-tricycle-field-colon">:</strong>
            <div class="doc-preview-tricycle-field-value"><strong>${previewEditable('fullAddress', templateSafe(fullAddress, '${ADDRESS}'), '${ADDRESS}', 'doc-editable-multiline')}</strong><br><strong>Barangay San Jose, Montalban, Rizal</strong></div>
          </div>
          <div class="doc-preview-tricycle-field">
            <strong class="doc-preview-tricycle-field-label">Location</strong>
            <strong class="doc-preview-tricycle-field-colon">:</strong>
            <div class="doc-preview-tricycle-field-value"><strong>${esc(tricycleLocation)}</strong></div>
          </div>
          <div class="doc-preview-tricycle-field">
            <strong class="doc-preview-tricycle-field-label">Type of Vehicle</strong>
            <strong class="doc-preview-tricycle-field-colon">:</strong>
            <div class="doc-preview-tricycle-field-value"><strong>${esc(templateSafe(franchisee || vehicleType, '${TYPE_OF_VEHICLE}'))}</strong></div>
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
      issuedLine = `Issued this <strong>${esc(issuedDateWord)}</strong> at the office of the Punong Barangay, Barangay San Jose, Montalban, Rizal.`;
      const tricycleMetaValues = [certificateNumber, state.orNumber, amount].map((value) => String(value || '').trim());
      const tricycleMetaLongest = tricycleMetaValues.reduce((max, value) => Math.max(max, value.length), 0);
      const tricycleMetaWidthCh = Math.max(8, tricycleMetaLongest + 1.2);
      const tricycleMetaLine = (value) => {
        const text = String(value || '').trim();
        return `<span class="doc-preview-tricycle-meta-line" style="width:${tricycleMetaWidthCh}ch">${text ? `<span class="doc-preview-tricycle-meta-line-text">${esc(text)}</span>` : ''}</span>`;
      };
      metaHtml = `
        <div class="doc-preview-tricycle-meta">
          <div class="doc-preview-tricycle-meta-row">
            <div class="doc-preview-tricycle-meta-label"><strong>Clearance No.</strong></div>
            <div class="doc-preview-tricycle-meta-colon">:</div>
            <div class="doc-preview-tricycle-meta-value">${tricycleMetaLine(certificateNumber)}</div>
          </div>
          <div class="doc-preview-tricycle-meta-row">
            <div class="doc-preview-tricycle-meta-label"><strong>Receipt No.</strong></div>
            <div class="doc-preview-tricycle-meta-colon">:</div>
            <div class="doc-preview-tricycle-meta-value">${tricycleMetaLine(state.orNumber)}</div>
          </div>
          <div class="doc-preview-tricycle-meta-row">
            <div class="doc-preview-tricycle-meta-label"><strong>Amount</strong></div>
            <div class="doc-preview-tricycle-meta-colon">:</div>
            <div class="doc-preview-tricycle-meta-value">${tricycleMetaLine(amount)}</div>
          </div>
        </div>
      `;
    } else if (isBusinessPermitClearance) {
      titleHtml = '<div class="doc-preview-goodmoral-office doc-preview-business-office"><div>TANGGAPAN NG PUNONG BARANGAY</div><div>BARANGAY CLEARANCE FOR BUSINESS PERMIT</div></div>';
      contentHtml = `
        <p class="doc-preview-business-lead"><strong>TO WHOM IT MAY CONCERN::</strong></p>
        <p class="doc-preview-business-intro">This is to certify that the business or trade activity below</p>
        <div class="doc-preview-business-fields">
          <div class="doc-preview-business-field"><strong>${previewEditable('businessName', businessName, '${BUSINESS_NAME}')}</strong></div>
          <div class="doc-preview-business-field"><strong>${previewEditable('businessAddress', businessAddress, '${BUSINESS_ADDRESS}', 'doc-editable-multiline')}</strong></div>
          <div class="doc-preview-business-field"><strong>${previewEditable('operatorName', operatorName, '${OPERATOR_NAME}')}</strong></div>
          <div class="doc-preview-business-field"><strong>${previewEditable('operatorAddress', operatorAddress, '${OPERATOR_ADDRESS}', 'doc-editable-multiline')}</strong></div>
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
      issuedLine = buildIssuedLine();
      metaHtml = '';
    } else if (isGoodMoral) {
      contentHtml = `
        <p><strong>TO WHOM IT MAY CONCERN:</strong></p>
        <p>
          This is to certify <strong>${previewEditable('fullName', safe(fullName, '${FULL_NAME}'), '${FULL_NAME}')}</strong>, resident of
          <strong>${previewEditable('fullAddress', safe(fullAddress, '${ADDRESS}'), '${ADDRESS}', 'doc-editable-multiline')}</strong>, Barangay San Jose, Montalban, Rizal
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
          This is to certify <strong>${previewEditable('fullName', safe(fullName, '${NAME}'), '${NAME}')}</strong>, resident of
          <strong>${previewEditable('fullAddress', safe(fullAddress, '${ADDRESS}'), '${ADDRESS}', 'doc-editable-multiline')}</strong>, Barangay San Jose, Montalban, Rizal
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
      issuedLine = buildIssuedLine();
      metaHtml = renderPreviewMetaRows(buildSharedIssuedMetaRows());
    } else if (isCohabitation && !cohabitationHasChildren) {
      titleHtml = '<div class="doc-preview-goodmoral-office"><div>TANGGAPAN NG PUNONG BARANGAY</div><div>CERTIFICATE OF COHABITATION</div></div>';
      contentHtml = `
        <p><strong>TO WHOM IT MAY CONCERN:</strong></p>
        <p>
          This is to certify that <strong>${previewEditable('fullName', safe(fullName, '${NAME}'), '${NAME}')}</strong>,
          <strong>${esc(safe(age, '${AGE}'))} y/o</strong> a resident of <strong>${previewEditable('fullAddress', safe(fullAddress, '${ADDRESS}'), '${ADDRESS}', 'doc-editable-multiline')}</strong>, Barangay San Jose, Montalban, Rizal
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
      metaHtml = renderPreviewMetaRows(buildSharedIssuedMetaRows());
    } else if (isCohabitation && cohabitationHasChildren) {
      titleHtml = '<div class="doc-preview-goodmoral-office"><div>TANGGAPAN NG PUNONG BARANGAY</div><div>CERTIFICATE OF COHABITATION</div></div>';
      contentHtml = `
        <p><strong>TO WHOM IT MAY CONCERN:</strong></p>
        <p>
          This is to certify that the person whose name appears here on has requested a Barangay Certification from this office and the information are listed below:
        </p>
        <div class="doc-to-block"><strong>Name</strong><strong>:</strong><div><div><strong>${previewEditable('fullName', safe(fullName, '-'), '-')}</strong>, ${esc(safe(age, '-'))} y/o</div><div><strong>${previewEditable('cohabitantName', safe(cohabitantName, '-'), '-')}</strong>, ${esc(safe(cohabitantAge, '-'))} y/o</div></div></div>
        <div class="doc-to-block"><strong>Address</strong><strong>:</strong><div><strong>${previewEditable('fullAddress', safe(fullAddress, '-'), '-', 'doc-editable-multiline')}</strong><br><strong>BARANGAY SAN JOSE, MONTALBAN, RIZAL</strong></div></div>
        <div class="doc-to-block"><strong>Remarks</strong><strong>:</strong><strong>${previewEditable('remarks', templateSafe(remarks, '${REMARKS}'), '${REMARKS}')}</strong></div>
        <div class="doc-to-block"><strong>Purpose</strong><strong>:</strong><strong>${esc(`COHABITATION SINCE ${safe(cohabitationStartDate || cohabitationDuration, '-')}`)}</strong></div>
        <div class="doc-to-block"><strong>Name of Children</strong><strong>:</strong><span>${esc(safe(cohabitationChildrenList, '-'))}</span></div>
        <p>
          This clearance is being issued pursuant to Barangay Revenue Code ORDINANCE NO. 11 – 2019
        </p>
      `;
      metaHtml = renderPreviewMetaRows(buildSharedIssuedMetaRows());
    } else if (usesResidencyTemplate) {
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
      metaHtml = renderPreviewMetaRows(buildSharedIssuedMetaRows());
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
          This is to certify <strong>${esc(safe(applicantHonorificText, 'MR./MS.'))} ${previewEditable('fullName', safe(fullName, '${NAME}'), '${NAME}')}</strong>,
          resident of <strong>${previewEditable('fullAddress', safe(fullAddress, '${ADDRESS}'), '${ADDRESS}', 'doc-editable-multiline')}</strong>, Barangay San Jose, Montalban, Rizal
          since <strong>${esc(safe(residencySinceText, '${RESIDENCY_SINCE}'))}</strong> is a qualified availlee of RA 11261
          or the First Time Jobseekers Act 2019.
        </p>
        <p>
          I further certify that the holder/bearer was informed of his/her rights, including the duties and responsibilities accorded by RA 11261 through the Oath of Undertaking he/she has signed and executed in the presence of our Barangay Official.
        </p>
      `;
      metaHtml = '';
    } else if (usesResidencyTemplate) {
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

    if (
      customDocumentTitle
      && !isBarangayId
      && !isGeneralPermitClearance
      && !isBusinessPermitClearance
      && !isTricyclePermitClearance
    ) {
      if (isIndigency) {
        titleHtml = `<div class="doc-preview-title doc-preview-title--indigency"><div class="office">TANGGAPAN NG PUNONG BARANGAY</div><div class="certificate">${esc(customDocumentTitle)}</div></div>`;
      } else if (isFirstTimeJobSeeker) {
        titleHtml = `
          <div class="doc-preview-goodmoral-office doc-preview-ftjs-office">
            <div>TANGGAPAN NG PUNONG BARANGAY</div>
            <div>${esc(customDocumentTitle)}</div>
            <div class="doc-preview-ftjs-subtitle">(First Time Jobseekers Act-RA 11261)</div>
          </div>
        `;
      } else {
        titleHtml = `<div class="doc-preview-goodmoral-office"><div>TANGGAPAN NG PUNONG BARANGAY</div><div>${esc(customDocumentTitle)}</div></div>`;
      }
    }

    const paperClass = isIndigency
      ? 'doc-preview-paper doc-preview-paper--indigency'
      : isBusinessPermitClearance
        ? 'doc-preview-paper doc-preview-paper--business'
        : isGeneralPermitClearance
          ? 'doc-preview-paper doc-preview-paper--generalclearance'
          : isTricyclePermitClearance
            ? 'doc-preview-paper doc-preview-paper--tricycle'
            : (isBarangayId || isGoodMoral || usesResidencyTemplate || isCohabitation || isFirstTimeJobSeeker)
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
            <div class="doc-preview-business-left-column">
              <div class="doc-preview-business-issuedby">Issued by: <strong>${esc(secretarySignatoryNameText)}</strong><br><em>${esc(secretarySignatoryTitleText)}</em></div>
              <div class="doc-preview-business-meta">
                ${businessMetaRow('clearance_no', 'Clearance No.', businessMetaValue('certificateNumber', certificateNumber))}
                ${businessMetaRow('or_number', 'O.R No.', businessMetaValue('orNumber', state.orNumber))}
                ${businessMetaRow('amount', 'Amount', businessMetaValue('amount', amount))}
                ${businessMetaRow('', 'Plate No.', businessMetaValue('plateNumber', plateNumber))}
                ${businessMetaRow('issued_on', 'Date Issued', businessMetaValue('issuedOn', previewDateText(state.issuedOn || state.issuedDate)))}
                ${businessMetaRow('issued_at', 'Place Issued', businessMetaValue('issuedAt', state.issuedAt || 'Barangay San Jose'))}
              </div>
            </div>
            <div class="doc-preview-business-signing">
              <div class="doc-preview-signature doc-preview-business-signature">
                ${renderSignatureInk(punongSignatorySignatureUrl, `${punongSignatoryNameText} signature`)}
                <div class="name">${esc(punongSignatoryNameText)}</div>
                <div>${esc(punongSignatoryTitleText)}</div>
              </div>
              <div class="doc-preview-signature doc-preview-business-signature">
                ${renderSignatureInk(monitoringSignatorySignatureUrl, `${monitoringSignatoryNameText} signature`)}
                <div class="name">${esc(monitoringSignatoryNameText)}</div>
                <div>${esc(monitoringSignatoryTitleText)}</div>
              </div>
            </div>
          </div>
          ${qrBlockHtml}
        </div>
      `;
      footerNoteHtml = `This document is valid until December 31, ${new Date().getFullYear()},<br>Check the qr code to verify the authenticity of this document.`;
    } else if (isGeneralPermitClearance) {
      footerAreaHtml = `
        <div class="doc-preview-generalclearance-footer-area${qrBlockHtml ? '' : ' doc-preview-generalclearance-footer-area--noqr'}">
          <div class="doc-preview-generalclearance-issuedby">Issued by: <strong>${esc(secretarySignatoryNameText)}</strong><br><em>${esc(secretarySignatoryTitleText)}</em></div>
          <div class="doc-preview-generalclearance-signing">
            <div class="doc-preview-signature doc-preview-generalclearance-signature">
              ${renderSignatureInk(punongSignatorySignatureUrl, `${punongSignatoryNameText} signature`)}
              <div class="name">${esc(punongSignatoryNameText)}</div>
              <div>${esc(punongSignatoryTitleText)}</div>
            </div>
            <div class="doc-preview-signature doc-preview-generalclearance-signature">
              ${renderSignatureInk(monitoringSignatorySignatureUrl, `${monitoringSignatoryNameText} signature`)}
              <div class="name">${esc(monitoringSignatoryNameText)}</div>
              <div>${esc(monitoringSignatoryTitleText)}</div>
            </div>
          </div>
          ${qrBlockHtml}
        </div>
      `;
      footerNoteHtml = 'This clearance is valid for Forty-five (45) days from the date issued and not valid without official seal. Check the qr code to verify the authenticity of this document.';
    } else if (isTricyclePermitClearance) {
      footerAreaHtml = `
        <div class="doc-preview-tricycle-footer-area${qrBlockHtml ? '' : ' doc-preview-tricycle-footer-area--noqr'}">
          <div class="doc-preview-tricycle-issuedby">Issued by: <strong>${esc(secretarySignatoryNameText)}</strong><br><em>${esc(secretarySignatoryTitleText)}</em></div>
          <div class="doc-preview-tricycle-signing">
            <div class="doc-preview-signature doc-preview-tricycle-signature">
              ${renderSignatureInk(punongSignatorySignatureUrl, `${punongSignatoryNameText} signature`)}
              <div class="name">${esc(punongSignatoryNameText)}</div>
              <div>${esc(punongSignatoryTitleText)}</div>
            </div>
            <div class="doc-preview-signature doc-preview-tricycle-signature">
              ${renderSignatureInk(monitoringSignatorySignatureUrl, `${monitoringSignatoryNameText} signature`)}
              <div class="name">${esc(monitoringSignatoryNameText)}</div>
              <div>${esc(monitoringSignatoryTitleText)}</div>
            </div>
          </div>
          ${qrBlockHtml}
        </div>
      `;
      footerNoteHtml = 'This clearance is valid for Forty-five (45) days from the date issue and not valid<br>without official seal. Check the qr code to verify the authenticity of this document.';
    } else {
      const footerAreaClass = `doc-preview-footer-area${isFirstTimeJobSeeker ? ' doc-preview-footer-area--ftjs' : ''}${qrBlockHtml ? '' : ' doc-preview-footer-area--noqr'}`;
      footerAreaHtml = isFirstTimeJobSeeker
        ? `
            <div class="${footerAreaClass}">
              <div></div>
              <div class="doc-preview-ftjs-signing">
                <div class="doc-preview-ftjs-block">
                  ${renderSignatureInk(punongSignatorySignatureUrl, `${punongSignatoryNameText} signature`, 'doc-preview-signature-ink--ftjs')}
                  <div class="doc-preview-ftjs-name">${esc(punongSignatoryNameText)}</div>
                  <div class="doc-preview-ftjs-role">${esc(punongSignatoryTitleText)}</div>
                </div>
                <div class="doc-preview-ftjs-date-line"><span>${esc(ftjsSignedDateText)}</span></div>
                <div class="doc-preview-ftjs-date-label">Date</div>
                <div class="doc-preview-ftjs-witness-label">Witnesses by:</div>
                <div class="doc-preview-ftjs-block doc-preview-ftjs-witness">
                  <div class="doc-preview-ftjs-name">${esc(secretarySignatoryNameText)}</div>
                  <div class="doc-preview-ftjs-role">${esc(secretarySignatoryTitleText)}</div>
                </div>
                <div class="doc-preview-ftjs-date-line"><span>${esc(ftjsSignedDateText)}</span></div>
                <div class="doc-preview-ftjs-date-label">Date</div>
              </div>
              ${qrBlockHtml}
            </div>
          `
        : `
            <div class="${footerAreaClass}">
              <div class="doc-preview-issuedby">Issued by: <strong>${esc(secretarySignatoryNameText)}</strong><br><em>${esc(secretarySignatoryTitleText)}</em></div>
              <div class="doc-preview-signature">
                ${renderSignatureInk(punongSignatorySignatureUrl, `${punongSignatoryNameText} signature`)}
                <div class="name">${esc(punongSignatoryNameText)}</div>
                <div>${esc(punongSignatoryTitleText)}</div>
              </div>
              ${qrBlockHtml}
            </div>
          `;
      footerNoteHtml = buildCertificateFooterNote();
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
    const stageKey = String(currentViewStage || '').toLowerCase();
    if (!viewModalNextBtn || (stageKey !== 'submitted' && stageKey !== 'fee_tagging' && stageKey !== 'for_interview')) return;
    const currentRow = itemById.get(String(currentViewRequestId || '').trim());
    if (
      isFirstTimeJobSeekerRow(currentRow)
      || normalizePreviewDocKey(currentRow?.document_type || '') === 'barangayid'
    ) {
      viewModalNextBtn.disabled = false;
      viewModalNextBtn.title = '';
      return;
    }
    const scrollHost = viewDetailsBody.closest('.modal-body') || viewDetailsBody;
    if (!scrollHost) return;

    const threshold = 24;
    const update = () => {
      const activeStageKey = String(currentViewStage || '').toLowerCase();
      if (viewMode !== 'preview' || (activeStageKey !== 'submitted' && activeStageKey !== 'fee_tagging' && activeStageKey !== 'for_interview')) return;
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
      const currentRow = itemById.get(rid);
      const submittedFlow = stageKey === 'submitted' || stageKey === 'fee_tagging';
      const interviewFlow = stageKey === 'for_interview' && isFirstTimeJobSeekerRow(currentRow);
      const releaseFlow = stageKey === 'ready_for_claim';
      viewModalBackBtn?.classList.remove('d-none');
      if (viewModalBackBtn) {
        viewModalBackBtn.textContent = (submittedFlow || interviewFlow) ? 'Cancel' : 'Back';
      }
      if (viewModalNextBtn) {
        if (submittedFlow) {
          viewModalNextBtn.textContent = isFirstTimeJobSeekerRow(currentRow)
            ? 'Approve for Interview'
            : 'Save and Approve';
          viewModalNextBtn.classList.remove('d-none', 'btn-primary');
          viewModalNextBtn.classList.add('btn-success');
          viewModalNextBtn.disabled = !isFirstTimeJobSeekerRow(currentRow);
        } else if (interviewFlow) {
          viewModalNextBtn.textContent = 'Save and Process';
          viewModalNextBtn.classList.remove('d-none', 'btn-primary');
          viewModalNextBtn.classList.add('btn-success');
          viewModalNextBtn.disabled = false;
        } else if (releaseFlow) {
          viewModalNextBtn.textContent = 'For Release';
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
      if (submittedFlow || interviewFlow) {
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

  function residentProfileDisplayName(record) {
    if (!record || typeof record !== 'object') return '';
    return String(firstNonEmpty([
      record.full_name,
      [
        record.firstname || record.first_name,
        record.middlename || record.middle_name,
        record.lastname || record.last_name,
        record.suffix
      ].filter(Boolean).join(' ')
    ]) || '').trim();
  }

  function normalizeResidentProfileSnapshot(record) {
    if (!record || typeof record !== 'object') return null;
    const emergencyFirstName = String(firstNonEmpty([record.emergency_first_name, record.emergency_first])).trim();
    const emergencyMiddleName = String(firstNonEmpty([record.emergency_middle_name, record.emergency_middle])).trim();
    const emergencyLastName = String(firstNonEmpty([record.emergency_last_name, record.emergency_last])).trim();
    const emergencySuffix = String(firstNonEmpty([record.emergency_suffix])).trim();
    const normalized = {
      ...record,
      resident_id: String(firstNonEmpty([record.resident_id])).trim(),
      resident_user_id: String(firstNonEmpty([record.resident_user_id, record.user_id])).trim(),
      first_name: String(firstNonEmpty([record.firstname, record.first_name])).trim(),
      middle_name: String(firstNonEmpty([record.middlename, record.middle_name])).trim(),
      last_name: String(firstNonEmpty([record.lastname, record.last_name])).trim(),
      occupation_display: String(firstNonEmpty([record.occupation_display, record.occupation, record.occupation_detail])).trim(),
      house_number: String(firstNonEmpty([record.house_number, record.street_number])).trim(),
      emergency_first_name: emergencyFirstName,
      emergency_middle_name: emergencyMiddleName,
      emergency_last_name: emergencyLastName,
      emergency_suffix: emergencySuffix,
      emergency_contact_number: String(firstNonEmpty([
        record.emergency_contact_number,
        record.emergency_contact,
        record.emergency_phone_number
      ])).trim(),
      id_picture_url: String(firstNonEmpty([record.id_picture_url])).trim(),
      id_picture_path: String(firstNonEmpty([record.id_picture_path])).trim()
    };
    normalized.full_name = residentProfileDisplayName(record) || residentProfileDisplayName(normalized);
    normalized.emergency_full_name = String(firstNonEmpty([
      record.emergency_full_name,
      [emergencyFirstName, emergencyMiddleName, emergencyLastName, emergencySuffix].filter(Boolean).join(' ')
    ])).trim();
    return normalized;
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
    const displayName = firstNonEmpty([data?.full_name, residentProfileDisplayName(data)]);
    const emergencyDisplayName = firstNonEmpty([
      data?.emergency_full_name,
      residentProfileDisplayName({
        first_name: data?.emergency_first_name,
        middle_name: data?.emergency_middle_name,
        last_name: data?.emergency_last_name,
        suffix: data?.emergency_suffix
      })
    ]);
    const occupationDisplay = firstNonEmpty([data?.occupation_display, data?.occupation, 'Unemployed']);
    const emergencyContactDisplay = firstNonEmpty([data?.emergency_contact_number, data?.emergency_contact]);
    const emergencyRelationshipDisplay = firstNonEmpty([data?.emergency_relationship, data?.relationship]);
    const ageDisplay = firstNonEmpty([data?.age, computeAgeFromBirthdate(data?.birthdate)]);
    const headOfFamilyRaw = String(firstNonEmpty([data?.head_of_family]) || '').trim().toLowerCase();
    const voterStatusRaw = String(firstNonEmpty([data?.voter_status]) || '').trim().toLowerCase();
    const isHeadOfFamily = headOfFamilyRaw === '1' || headOfFamilyRaw === 'yes' || headOfFamilyRaw === 'true';
    const isRegisteredVoter = voterStatusRaw === '1' || voterStatusRaw === 'registered' || voterStatusRaw === 'true';
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

    setById('txt-modalName', displayName);
    setById('txt-modalDob', data?.birthdate);
    setById('txt-modalAge', ageDisplay);
    setById('txt-modalSex', data?.sex);
    setById('txt-modalCivilStatus', data?.civil_status);
    setById('txt-modalHeadOfFam', isHeadOfFamily ? 'Yes' : 'No');
    setById('txt-modalVoterStatus', isRegisteredVoter ? 'Registered' : 'Not Registered');
    setById('txt-modalOccupation', occupationDisplay);
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

    setById('txt-modalEmergencyFullName', emergencyDisplayName);
    setById('txt-modalEmergencyContactNumber', emergencyContactDisplay);
    setById('txt-modalEmergencyRelationship', emergencyRelationshipDisplay);
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

    // Older templates contained a mojibake version of the em dash. Normalize
    // any remaining empty-value placeholders after the profile is hydrated.
    residentProfileModalEl?.querySelectorAll('p, span').forEach((element) => {
      const text = String(element.textContent || '').trim();
      if (text === 'â€”' || text === 'â€' || text === 'â€˜') {
        element.textContent = '—';
      }
    });
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

  async function fetchResidentProfileSnapshot(residentId, residentUserId = '', fallbackProfile = null) {
    const rid = String(residentId || '').trim();
    const uid = String(residentUserId || '').trim();
    const fallbackRid = String(fallbackProfile?.resident_id || '').trim();
    const fallbackUid = String(fallbackProfile?.resident_user_id || fallbackProfile?.user_id || '').trim();
    const cacheKey = `${rid || fallbackRid}|${uid || fallbackUid}`;
    if (cacheKey !== '|' && residentProfileSnapshotByLookup.has(cacheKey)) {
      return residentProfileSnapshotByLookup.get(cacheKey);
    }

    const searchToken = rid || uid || fallbackRid || fallbackUid;
    const fallbackNormalized = normalizeResidentProfileSnapshot(fallbackProfile);
    if (!searchToken) {
      return fallbackNormalized;
    }

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

      const normalized = normalizeResidentProfileSnapshot(match || fallbackProfile);
      if (cacheKey !== '|' && normalized) {
        residentProfileSnapshotByLookup.set(cacheKey, normalized);
      }
      return normalized;
    } catch (_) {
      if (cacheKey !== '|' && fallbackNormalized) {
        residentProfileSnapshotByLookup.set(cacheKey, fallbackNormalized);
      }
      return fallbackNormalized;
    }
  }

  async function ensureBarangayIdPhotoData(row) {
    if (!row || typeof row !== 'object') return row;
    if (normalizePreviewDocKey(row?.document_type || '') !== 'barangayid') {
      return row;
    }
    if (barangayIdRequestHasPhoto(row)) {
      return row;
    }

    const fallbackProfile = row.resident_profile && typeof row.resident_profile === 'object'
      ? row.resident_profile
      : null;
    const residentProfile = await fetchResidentProfileSnapshot(
      row?.resident_id,
      row?.resident_user_id,
      fallbackProfile
    );
    if (!residentProfile || (!residentProfile.id_picture_url && !residentProfile.id_picture_path)) {
      return row;
    }

    const payload = row.payload && typeof row.payload === 'object' ? { ...row.payload } : {};
    if (!String(payload.id_picture_url || '').trim()) {
      payload.id_picture_url = String(residentProfile.id_picture_url || '').trim();
    }
    if (!String(payload.id_picture_path || '').trim()) {
      payload.id_picture_path = String(residentProfile.id_picture_path || '').trim();
    }

    return {
      ...row,
      payload,
      resident_profile: {
        ...(fallbackProfile || {}),
        ...residentProfile
      }
    };
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
      const match = await fetchResidentProfileSnapshot(rid, uid, fallbackProfile);
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

  function confirmIssuedDocumentRegeneration() {
    if (!regenerateIssuedConfirmModal || !regenerateIssuedConfirmModalEl || !regenerateIssuedConfirmBtn) {
      return Promise.resolve(false);
    }
    return new Promise((resolve) => {
      let settled = false;
      const finish = (confirmed) => {
        if (settled) return;
        settled = true;
        resolve(confirmed);
      };
      const handleConfirm = () => {
        finish(true);
        regenerateIssuedConfirmModal.hide();
      };
      const handleHidden = () => {
        regenerateIssuedConfirmBtn.removeEventListener('click', handleConfirm);
        regenerateIssuedConfirmModalEl.removeEventListener('hidden.bs.modal', handleHidden);
        if (paymentProofModalEl?.classList.contains('show')) {
          document.body.classList.add('modal-open');
        }
        finish(false);
      };
      regenerateIssuedConfirmBtn.addEventListener('click', handleConfirm, { once: true });
      regenerateIssuedConfirmModalEl.addEventListener('hidden.bs.modal', handleHidden, { once: true });
      regenerateIssuedConfirmModal.show();
    });
  }

  paymentProofRegenerateBtn?.addEventListener('click', async () => {
    const requestId = String(paymentProofReleaseRequestId || '').trim();
    if (!requestId || !(await confirmIssuedDocumentRegeneration())) {
      return;
    }
    const modalState = paymentProofModalState ? {
      docUrl: paymentProofModalState.docUrl,
      title: paymentProofModalState.title,
      returnTarget: paymentProofModalState.returnTarget,
      options: { ...(paymentProofModalState.options || {}) }
    } : null;
    paymentProofRegenerateBtn.disabled = true;
    paymentProofRegenerateBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Regenerating...';
    try {
      const body = new FormData();
      body.append('action', 'regenerate_issued_document');
      body.append('request_id', requestId);
      const data = await fetchJson(endpoint, { method: 'POST', body });
      cachedAllItems = null;
      await load({ force: true });
      if (modalState) {
        openDocumentModal(modalState.docUrl, modalState.title, modalState.returnTarget, modalState.options);
      }
      alert(data?.message || 'Issued document regenerated successfully.');
    } catch (error) {
      alert(error?.message || 'Unable to regenerate the issued document.');
    } finally {
      paymentProofRegenerateBtn.disabled = false;
      paymentProofRegenerateBtn.innerHTML = '<i class="fas fa-rotate me-1"></i>Regenerate Document';
    }
  });

  paymentProofReleaseBtn?.addEventListener('click', async () => {
    const requestId = String(paymentProofReleaseRequestId || '').trim();
    if (!requestId) return;

    const currentRow = itemById.get(requestId);
    const stageKey = resolveWorkflowStage(currentRow);
    if (stageKey !== 'ready_for_claim') {
      alert('This request is no longer ready for release.');
      await load({ force: true });
      return;
    }

    pendingPaymentProofAction = {
      type: 'mark_completed_confirm',
      requestId,
      returnTarget: 'paymentProof'
    };
    if (paymentProofModal) {
      paymentProofModal.hide();
    }
  });

  async function printBarangayIdCards(which = 'both', sourceRoot = paymentProofWrap) {
    const cards = Array.from(sourceRoot?.querySelectorAll('.barangay-id-card') || []);
    if (!cards.length) return false;
    const renderer = window.html2canvas;
    if (typeof renderer !== 'function') {
      alert('Image print support is not available right now. Please reload the page and try again.');
      return true;
    }

    const hasSinglePreviewCard = cards.length === 1;
    const selectedCards = which === 'front'
      ? cards.slice(0, 1)
      : which === 'back'
        ? (hasSinglePreviewCard ? cards.slice(0, 1) : cards.slice(1, 2))
        : cards;
    if (!selectedCards.length) return true;

    const printWindow = window.open('', '_blank', 'width=1200,height=900');
    if (!printWindow) return true;

    const buttons = [
      paymentProofPrintBtn,
      idPrintProcessReturnBtn,
      idPrintProcessReprintBtn,
      idPrintProcessPrimaryBtn
    ].filter(Boolean);
    try {
      buttons.forEach((btn) => { btn.disabled = true; });
      printWindow.document.open();
      printWindow.document.write(`
        <!doctype html>
        <html lang="en">
          <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Preparing ID Print</title>
            <style>
              body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                font-family: Arial, Helvetica, sans-serif;
                background: #fff;
                color: #334155;
              }
            </style>
          </head>
          <body>Preparing ID print...</body>
        </html>
      `);
      printWindow.document.close();

      const imageUrls = [];
      for (const card of selectedCards) {
        const canvas = await renderer(card, {
          backgroundColor: '#ffffff',
          scale: Math.max(2, Math.min(4, window.devicePixelRatio || 2)),
          useCORS: true,
          logging: false
        });
        imageUrls.push(canvas.toDataURL('image/png'));
      }

      const imageMarkup = imageUrls.map((src, index) => `
        <figure class="id-print-sheet">
          <img src="${src}" alt="Barangay ID ${which === 'back' ? 'back' : which === 'front' ? 'front' : index === 0 ? 'front' : 'back'}">
        </figure>
      `).join('');

      printWindow.document.open();
      printWindow.document.write(`
        <!doctype html>
        <html lang="en">
          <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Print Digital Barangay ID</title>
            <style>
              @page {
                size: 85.6mm 54mm;
                margin: 0;
              }
              html,
              body {
                margin: 0;
                padding: 0;
                width: 85.6mm;
                min-width: 85.6mm;
                background: #fff;
                font-family: Arial, Helvetica, sans-serif;
              }
              .print-grid {
                display: grid;
                gap: 0;
                justify-content: start;
              }
              .id-print-sheet {
                margin: 0;
                width: 85.6mm;
                height: 54mm;
                break-inside: avoid;
                break-after: page;
                page-break-after: always;
              }
              .id-print-sheet img {
                display: block;
                width: 85.6mm;
                height: 54mm;
                image-rendering: high-quality;
              }
              @media print {
                html,
                body {
                  width: 85.6mm;
                  min-width: 85.6mm;
                }
                .id-print-sheet:last-child {
                  break-after: auto;
                  page-break-after: auto;
                }
              }
            </style>
          </head>
          <body>
            <main class="print-grid">${imageMarkup}</main>
          </body>
        </html>
      `);
      printWindow.document.close();
      const runPrint = () => {
        try {
          printWindow.focus();
          printWindow.print();
        } catch (_) {}
      };
      printWindow.addEventListener('load', () => setTimeout(runPrint, 350), { once: true });
      setTimeout(runPrint, 1400);
    } catch (error) {
      console.error('Failed to rasterize Barangay ID for printing:', error);
      alert('Unable to prepare the ID as an image for printing.');
    } finally {
      buttons.forEach((btn) => { btn.disabled = false; });
    }
    return true;
  }

  function queueReleaseFromPaymentProof(requestId) {
    const normalizedRequestId = String(requestId || '').trim();
    if (!normalizedRequestId) {
      alert('Unable to release this Barangay ID because the request ID is missing.');
      return;
    }
    const requestRow = itemById.get(normalizedRequestId);
    const printingStage = resolveWorkflowStage(requestRow) === 'for_printing';
    pendingPaymentProofAction = {
      type: printingStage ? 'mark_ready' : 'mark_completed_confirm',
      requestId: normalizedRequestId,
      returnTarget: 'paymentProof'
    };
    if (idPrintProcessModal) {
      idPrintProcessModal.hide();
    }
    if (paymentProofModal) {
      paymentProofModal.hide();
    }
  }

  function idPrintProcessPhaseLabel() {
    return idPrintProcessPhase === 'back' ? 'back' : 'front';
  }

  function renderIdPrintProcessPreview() {
    if (!idPrintProcessPreview) return;
    const cardHtml = idPrintProcessPhase === 'back'
      ? String(idPrintProcessContext?.backHtml || '').trim()
      : String(idPrintProcessContext?.frontHtml || '').trim();
    idPrintProcessPreview.innerHTML = cardHtml || '';
  }

  function showIdPrintProcessModal({ autoPrint = false } = {}) {
    renderIdPrintProcessPhase();
    renderIdPrintProcessPreview();
    idPrintProcessModal?.show();
    if (autoPrint) {
      window.setTimeout(() => {
        printBarangayIdCards(idPrintProcessPhaseLabel(), idPrintProcessPreview);
      }, 180);
    }
  }

  function renderIdPrintProcessPhase() {
    if (!idPrintProcessStep || !idPrintProcessCopy || !idPrintProcessPrimaryBtn || !idPrintProcessReturnBtn || !idPrintProcessReprintBtn) return;
    idPrintProcessPrimaryBtn.classList.remove('btn-success');
    idPrintProcessPrimaryBtn.classList.add('btn-primary');
    switch (idPrintProcessPhase) {
      case 'front':
        idPrintProcessStep.textContent = 'Step 1 of 3';
        idPrintProcessCopy.textContent = 'This is the front side of the Barangay ID. Print it first, then continue to the back side.';
        idPrintProcessReturnBtn.textContent = 'Return';
        idPrintProcessReprintBtn.textContent = 'Reprint';
        idPrintProcessPrimaryBtn.textContent = 'Next';
        break;
      case 'back':
        idPrintProcessStep.textContent = 'Step 2 of 3';
        idPrintProcessCopy.textContent = 'This is the back side of the Barangay ID. Print it, then mark the ID ready for claim when both sides are finished.';
        idPrintProcessReturnBtn.textContent = 'Return';
        idPrintProcessReprintBtn.textContent = 'Reprint';
        idPrintProcessPrimaryBtn.textContent = 'Mark Printed / For Claim';
        idPrintProcessPrimaryBtn.classList.remove('btn-primary');
        idPrintProcessPrimaryBtn.classList.add('btn-success');
        break;
      default:
        idPrintProcessPhase = 'front';
        renderIdPrintProcessPhase();
        break;
    }
  }

  paymentProofPrintBtn?.addEventListener('click', async () => {
    if (paymentProofWrap?.querySelector('.barangay-id-digital')) {
      const cards = Array.from(paymentProofWrap.querySelectorAll('.barangay-id-card'));
      if (!cards.length) {
        alert('Unable to prepare the Barangay ID preview for printing.');
        return;
      }
      const releaseRequestId = String(
        paymentProofReleaseRequestId
        || paymentProofModalState?.options?.releaseRequestId
        || ''
      ).trim();
      idPrintProcessContext = {
        requestId: releaseRequestId,
        frontHtml: cards[0]?.outerHTML || '',
        backHtml: cards[1]?.outerHTML || '',
        docUrl: String(paymentProofModalState?.docUrl || '').trim(),
        title: String(paymentProofModalState?.title || 'Digital Barangay ID').trim() || 'Digital Barangay ID',
        returnTarget: String(paymentProofModalState?.returnTarget || '').trim(),
        options: { ...(paymentProofModalState?.options || {}) }
      };
      idPrintProcessPhase = 'front';
      idPrintProcessPendingOpen = true;
      paymentProofModal?.hide();
      return;
    }
    if (await printBarangayIdCards('both')) {
      return;
    }

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

  idPrintProcessReturnBtn?.addEventListener('click', () => {
    if (idPrintProcessPhase === 'back') {
      idPrintProcessPhase = 'front';
      showIdPrintProcessModal();
      return;
    }
    idPrintProcessReopenViewer = true;
    idPrintProcessModal?.hide();
  });

  idPrintProcessReprintBtn?.addEventListener('click', async () => {
    await printBarangayIdCards(idPrintProcessPhaseLabel(), idPrintProcessPreview);
  });

  idPrintProcessPrimaryBtn?.addEventListener('click', async () => {
    switch (idPrintProcessPhase) {
      case 'front':
        idPrintProcessPhase = 'back';
        renderIdPrintProcessPhase();
        renderIdPrintProcessPreview();
        await printBarangayIdCards('back', idPrintProcessPreview);
        return;
      case 'back':
        queueReleaseFromPaymentProof(idPrintProcessContext?.requestId || paymentProofReleaseRequestId);
        return;
      default:
        idPrintProcessPhase = 'front';
        showIdPrintProcessModal();
    }
  });

  idPrintProcessModalEl?.addEventListener('hidden.bs.modal', () => {
    const reopenViewer = idPrintProcessReopenViewer;
    const reopenHandler = reopenViewer && typeof paymentProofBarangayIdReopen === 'function'
      ? paymentProofBarangayIdReopen
      : null;
    const queuedAction = pendingPaymentProofAction ? { ...pendingPaymentProofAction } : null;
    const queuedReturnState = queuedAction && idPrintProcessContext ? {
      docUrl: String(idPrintProcessContext.docUrl || '').trim(),
      title: String(idPrintProcessContext.title || 'Digital Barangay ID').trim() || 'Digital Barangay ID',
      returnTarget: String(idPrintProcessContext.returnTarget || '').trim(),
      options: { ...(idPrintProcessContext.options || {}) }
    } : null;
    idPrintProcessPhase = 'front';
    idPrintProcessPendingOpen = false;
    idPrintProcessContext = null;
    idPrintProcessReopenViewer = false;
    if (idPrintProcessPreview) {
      idPrintProcessPreview.innerHTML = '';
    }
    renderIdPrintProcessPhase();
    if (idPrintProcessPrimaryBtn) {
      idPrintProcessPrimaryBtn.disabled = false;
    }
    if (idPrintProcessReturnBtn) {
      idPrintProcessReturnBtn.disabled = false;
    }
    if (idPrintProcessReprintBtn) {
      idPrintProcessReprintBtn.disabled = false;
    }
    if (reopenHandler) {
      window.setTimeout(() => {
        reopenHandler();
      }, 0);
      return;
    }
    if (queuedAction) {
      pendingPaymentProofAction = null;
      window.setTimeout(() => {
        openActionModal(queuedAction.type, queuedAction.requestId, {
          returnTarget: queuedAction.returnTarget,
          reopenState: queuedReturnState
        });
      }, 0);
    }
  });

  paymentProofModalEl?.addEventListener('hidden.bs.modal', () => {
    const queuedAction = pendingPaymentProofAction ? { ...pendingPaymentProofAction } : null;
    const queuedReturnState = queuedAction && paymentProofModalState ? {
      docUrl: paymentProofModalState.docUrl,
      title: paymentProofModalState.title,
      returnTarget: paymentProofModalState.returnTarget,
      options: { ...(paymentProofModalState.options || {}) }
    } : null;

    paymentProofReturnTarget = '';
    paymentProofPrintUrl = '';
    paymentProofReleaseRequestId = '';
    paymentProofModalState = null;
    pendingPaymentProofAction = null;
    if (idPrintProcessModal && !idPrintProcessPendingOpen) {
      idPrintProcessModal.hide();
    }
    if (paymentProofReturnBtn) {
      paymentProofReturnBtn.classList.add('d-none');
      paymentProofReturnBtn.disabled = false;
    }
    if (paymentProofTitle) {
      paymentProofTitle.textContent = 'Document Viewer';
    }
    if (paymentProofOpenNew) {
      paymentProofOpenNew.classList.remove('d-none');
      paymentProofOpenNew.removeAttribute('href');
    }
    if (paymentProofCloseBtn) {
      paymentProofCloseBtn.classList.remove('d-none');
    }
    if (paymentProofPrintBtn) {
      paymentProofPrintBtn.classList.add('d-none');
      paymentProofPrintBtn.textContent = 'Print';
    }
    if (paymentProofRegenerateBtn) {
      paymentProofRegenerateBtn.classList.add('d-none');
      paymentProofRegenerateBtn.disabled = false;
      paymentProofRegenerateBtn.innerHTML = '<i class="fas fa-rotate me-1"></i>Regenerate Document';
    }
    if (paymentProofReleaseBtn) {
      paymentProofReleaseBtn.classList.add('d-none');
      paymentProofReleaseBtn.disabled = false;
      paymentProofReleaseBtn.textContent = 'Release';
    }
    if (paymentProofWrap) {
      paymentProofWrap.innerHTML = '';
    }
    if (idPrintProcessPendingOpen) {
      idPrintProcessPendingOpen = false;
      window.setTimeout(() => {
        showIdPrintProcessModal({ autoPrint: true });
      }, 0);
      return;
    }
    paymentProofBarangayIdReopen = null;
    if (queuedAction) {
      openActionModal(queuedAction.type, queuedAction.requestId, {
        returnTarget: queuedAction.returnTarget,
        reopenState: queuedReturnState
      });
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
    const deliveryNote = requestDeliveryNote(row)
      ? `<div class="text-muted small mt-1">${esc(requestDeliveryNote(row))}</div>`
      : '';
    const fullName = fullNameFromRow(row);
    const purpose = normalizeDisplayCasing(firstNonEmpty([row.purpose, '-']));
    const statusKey = statusBucket(row);
    const workflowStage = resolveWorkflowStage(row);
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
        <td class="col-purpose-cell">
          <div class="cell-purpose">${esc(purpose)}</div>
        </td>
        <td>${badge(isFinancePaymentsPage ? statusKey : workflowStage, esc(isFinancePaymentsPage ? financeStatusLabel : stageLabel))}${reason}${deliveryNote}</td>
        <td>${esc(row.submitted_at || '-')}</td>
        <td>${actionButtons(row)}</td>
      </tr>
    `;
  }

  function openDocumentModal(docUrl, title = 'Document Viewer', returnTarget = '', options = {}) {
    if (!docUrl || !paymentProofModal || !paymentProofWrap || !paymentProofOpenNew) return;
    paymentProofModalState = {
      docUrl: String(docUrl || '').trim(),
      title: String(title || 'Document Viewer').trim() || 'Document Viewer',
      returnTarget: String(returnTarget || '').trim(),
      options: { ...(options || {}) }
    };
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
    const normalizedTitle = String(title || '').trim().toLowerCase();
    const proofOnly = String(title || '').toLowerCase().startsWith('proof of residency');
    const isIssuedDocument = normalizedTitle === 'issued document';
    const releaseRequestId = String(options?.releaseRequestId || '').trim();
    const showReturnButton = proofOnly || isIssuedDocument || paymentProofReturnTarget !== '';
    if (paymentProofReturnBtn) {
      paymentProofReturnBtn.classList.toggle('d-none', !showReturnButton);
      if (proofOnly) paymentProofReturnBtn.classList.remove('d-none');
    }
    if (paymentProofOpenNew) {
      paymentProofOpenNew.classList.toggle('d-none', proofOnly || isIssuedDocument);
    }
    if (paymentProofCloseBtn) {
      paymentProofCloseBtn.classList.toggle('d-none', proofOnly || isIssuedDocument);
    }
    paymentProofPrintUrl = '';
    paymentProofReleaseRequestId = releaseRequestId;
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
    if (isIssuedDocument) {
      isLikelyPdf = true;
    }

    const renderModalContent = () => {
      if (!paymentProofWrap) return;
      if (!isImageAsset) {
        paymentProofWrap.innerHTML = `<iframe src="${bustedUrl}" loading="lazy" title="Document Preview"></iframe>`;
      } else {
        paymentProofWrap.innerHTML = `<img src="${bustedUrl}" alt="Document Preview" loading="lazy">`;
      }
    };
    paymentProofWrap.innerHTML = `
      <div class="doc-viewer-loading">
        <div class="doc-viewer-loading__inner">
          <div class="doc-viewer-loading__spinner" aria-hidden="true"></div>
          <div class="doc-viewer-loading__label">Loading document preview...</div>
        </div>
      </div>
    `;
    if (paymentProofPrintBtn) {
      const allowPrint = isIssuedDocument || !!(options && options.allowPrint);
      paymentProofPrintBtn.classList.toggle('d-none', !(allowPrint && isLikelyPdf && !proofOnly));
      if (allowPrint && isLikelyPdf && !proofOnly) {
        paymentProofPrintUrl = bustedUrl;
      }
    }
    if (paymentProofReleaseBtn) {
      paymentProofReleaseBtn.classList.toggle('d-none', !(isIssuedDocument && releaseRequestId));
      paymentProofReleaseBtn.disabled = false;
      paymentProofReleaseBtn.textContent = 'Release';
    }
    if (paymentProofRegenerateBtn) {
      paymentProofRegenerateBtn.classList.toggle('d-none', !(isIssuedDocument && releaseRequestId));
      paymentProofRegenerateBtn.disabled = false;
    }
    paymentProofModal.show();
    window.requestAnimationFrame(() => {
      window.setTimeout(renderModalContent, 40);
    });
  }

  function openBarangayIdCardModal(row, docUrl, title = 'Digital Barangay ID', returnTarget = '', options = {}) {
    if (!row || !paymentProofModal || !paymentProofWrap) return;
    const requestId = String(row.request_id || '').trim();
    if (!requestId || !window.BarangayIdDigital || typeof window.BarangayIdDigital.render !== 'function') {
      openDocumentModal(docUrl, title, returnTarget, options);
      return;
    }
    paymentProofReleaseRequestId = String(options?.releaseRequestId || requestId).trim();

    paymentProofModalState = {
      docUrl: String(docUrl || '').trim(),
      title: String(title || 'Digital Barangay ID').trim() || 'Digital Barangay ID',
      returnTarget: String(returnTarget || '').trim(),
      options: { ...(options || {}) }
    };
    paymentProofReturnTarget = String(returnTarget || '').trim();
    if (paymentProofTitle) {
      paymentProofTitle.textContent = 'Digital Barangay ID';
    }
    if (paymentProofReturnBtn) {
      paymentProofReturnBtn.classList.toggle('d-none', paymentProofReturnTarget === '');
      paymentProofReturnBtn.disabled = false;
    }
    if (paymentProofOpenNew) {
      paymentProofOpenNew.classList.add('d-none');
      paymentProofOpenNew.removeAttribute('href');
    }
    if (paymentProofCloseBtn) {
      paymentProofCloseBtn.classList.remove('d-none');
    }
    if (paymentProofPrintBtn) {
      paymentProofPrintBtn.classList.add('d-none');
    }
    if (paymentProofReleaseBtn) {
      paymentProofReleaseBtn.classList.add('d-none');
    }
    if (paymentProofPrintBtn) {
      paymentProofPrintBtn.classList.toggle('d-none', !(options && options.allowPrint));
      paymentProofPrintBtn.textContent = 'Print ID';
    }
    paymentProofBarangayIdReopen = () => {
      openBarangayIdCardModal(row, docUrl, title, returnTarget, options);
    };

    paymentProofWrap.innerHTML = `
      <div class="d-flex flex-column align-items-center justify-content-center py-5 gap-3 text-muted">
        <div class="spinner-border" role="status" aria-hidden="true"></div>
        <div>Loading Barangay ID preview...</div>
      </div>
    `;
    paymentProofModal.show();

    Promise.all([
      fetchBarangayIdTemplateConfig({ force: true }).catch(() => null),
      ensureBarangayIdPhotoData(row).catch(() => row)
    ])
      .then(([templateConfig, hydratedRow]) => {
        const state = buildBarangayIdCardModalState(hydratedRow || row, options, templateConfig);
        if (typeof window.BarangayIdDigital.renderInto === 'function') {
          window.BarangayIdDigital.renderInto(paymentProofWrap, state, {
            showIntro: false,
            frontLabel: 'Front Template',
            backLabel: 'Back Template',
          });
          return;
        }
        paymentProofWrap.innerHTML = window.BarangayIdDigital.render(state, {
          showIntro: false,
          frontLabel: 'Front Template',
          backLabel: 'Back Template',
        });
        if (typeof window.BarangayIdDigital.hydrate === 'function') {
          window.BarangayIdDigital.hydrate(paymentProofWrap);
        }
      });
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
    if (stage.includes('rejected') || stage === 'interview_failed' || stage === 'inspection_failed') return 'denied';
    if (stage === 'completed' || stage === 'ready_for_claim' || stage === 'payment_verified') return 'verified';
    return 'pending';
  }

  function isFinanceWalkInEligible(row) {
    const stage = String(row?.stage || '').toLowerCase();
    return stage === 'for_payment' || stage === 'payment_rejected';
  }

  function isFinanceOnlineVerificationEligible(row) {
    const stage = String(row?.stage || '').toLowerCase();
    return stage === 'payment_submitted';
  }

  function matchesStatusFilter(row) {
    if (currentStatusFilter === 'all') return true;
    return statusBucket(row) === currentStatusFilter;
  }

  function matchesDocumentTypeFilter(row) {
    const activeDocumentFilter = isIdIssuanceTrackerView && !isFinancePaymentsPage
      ? 'Barangay ID'
      : currentDocumentTypeFilter;
    if (!activeDocumentFilter) return true;
    const normalizedDocumentType = normalizeDocumentTypeDisplay(String(row?.document_type || ''));
    if (activeDocumentFilter === '__clearances__') {
      return [
        'Barangay Clearance for Business Permit',
        'Barangay Clearance for Tricycle Permit',
        'Barangay Clearance for Electrical Permit',
        'Barangay Clearance for Water Permit',
        'Barangay Clearance for Residential Permit',
        'Barangay Clearance for Residential Building Permit',
        'Barangay Clearance for Commercial Permit',
        'Barangay Clearance for Commercial Building Permit',
      ].includes(normalizedDocumentType);
    }
    if (activeDocumentFilter === '__business__') {
      return normalizePreviewDocKey(row?.document_type || '') === 'businessclearance';
    }
    if (activeDocumentFilter === '__certificates__') {
      const rawDocumentType = String(row?.document_type || '').trim().toLowerCase();
      if (
        rawDocumentType.includes('certificate')
        && !rawDocumentType.includes('barangay id')
        && !rawDocumentType.includes('clearance')
      ) {
        return true;
      }
      const previewKey = normalizePreviewDocKey(row?.document_type || '');
      if (previewKey === 'cohabitation') {
        return true;
      }
      return ['goodmoral', 'firsttimejobseeker', 'residency', 'indigency', 'identity', 'generalcertificate'].includes(previewKey);
    }
    if (activeDocumentFilter === '__clr_business_permit__') {
      return normalizedDocumentType === 'Barangay Clearance for Business Permit';
    }
    if (activeDocumentFilter === '__clr_tricycle_permit__') {
      return normalizedDocumentType === 'Barangay Clearance for Tricycle Permit';
    }
    if (activeDocumentFilter === '__clr_electric_permit__') {
      return normalizedDocumentType === 'Barangay Clearance for Electrical Permit';
    }
    if (activeDocumentFilter === '__clr_water_permit__') {
      return normalizedDocumentType === 'Barangay Clearance for Water Permit';
    }
    if (activeDocumentFilter === '__clr_residential_permit__') {
      return normalizedDocumentType === 'Barangay Clearance for Residential Permit';
    }
    if (activeDocumentFilter === '__clr_commercial_permit__') {
      return normalizedDocumentType === 'Barangay Clearance for Commercial Permit';
    }
    if (activeDocumentFilter === '__cert_cohabitation__') {
      const payload = row && row.payload && typeof row.payload === 'object' ? row.payload : {};
      const variant = String(payload?.cohabitation_variant || '').trim().toLowerCase();
      return normalizePreviewDocKey(row?.document_type || '') === 'cohabitation'
        && !['relationship_jail_visit', 'conjugal_visit'].includes(variant);
    }
    if (activeDocumentFilter === '__cert_good_moral__') {
      return normalizePreviewDocKey(row?.document_type || '') === 'goodmoral';
    }
    if (activeDocumentFilter === '__cert_jail_visit__') {
      const payload = row && row.payload && typeof row.payload === 'object' ? row.payload : {};
      const variant = String(payload?.cohabitation_variant || '').trim().toLowerCase();
      return normalizePreviewDocKey(row?.document_type || '') === 'cohabitation'
        && ['relationship_jail_visit', 'conjugal_visit'].includes(variant);
    }
    if (activeDocumentFilter === '__cert_first_time_job_seeker__') {
      return normalizePreviewDocKey(row?.document_type || '') === 'firsttimejobseeker';
    }
    if (activeDocumentFilter === '__cert_residency__') {
      return normalizePreviewDocKey(row?.document_type || '') === 'residency';
    }
    if (activeDocumentFilter === '__cert_indigency__') {
      return normalizePreviewDocKey(row?.document_type || '') === 'indigency';
    }
    return String(row?.document_type || '') === activeDocumentFilter;
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
      requestTypeLabel(row),
      row?.area_number,
      row?.sector_membership,
      payload?.last_name,
      payload?.first_name,
      payload?.middle_name,
      payload?.full_address
    ].map((v) => String(v || '').toLowerCase()).join(' ');
    return haystack.includes(q);
  }

  function matchesFinanceAdvancedFilters(row) {
    if (!isFinancePaymentsPage) return true;
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
    if (key === 'barangay_id') {
      return normalizePreviewDocKey(row?.document_type || '') === 'barangayid';
    }
    if (key === 'pending') {
      return (
        stage === 'submitted' ||
        stage === 'for_interview' ||
        stage === 'for_inspection' ||
        stage === 'fee_tagging' ||
        stage === 'for_payment' ||
        stage === 'payment_submitted' ||
        stage.includes('pending')
      );
    }
    if (key === 'release') {
      return stage === 'for_printing' || stage === 'ready_for_claim' || stage.includes('release');
    }
    if (key === 'completed') {
      return stage === 'completed';
    }
    return stage === key;
  }

  function updateStageTabBadges(items) {
    const rows = Array.isArray(items) ? items : [];
    if (barangayIdTabCount) {
      barangayIdTabCount.textContent = String(rows.filter((it) => matchesStageTabFilter(it, 'barangay_id')).length);
    }
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
    if (isIdIssuanceTrackerView && !isFinancePaymentsPage) {
      currentDocumentTypeFilter = 'Barangay ID';
      documentTypeFilter.innerHTML = '<option value="Barangay ID">Filter: Barangay ID</option>';
      documentTypeFilter.value = 'Barangay ID';
      documentTypeFilter.disabled = true;
      return;
    }
    documentTypeFilter.disabled = false;
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
    if (!financeFilterPaymentMethod) return;

    const methods = Array.from(new Set(
      (items || [])
        .map((it) => String(it?.payment_method || '').trim().toLowerCase())
        .filter((v) => v !== '')
    ));

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

    const methodAllowed = ['gcash', 'barangay'].includes(financeFilterMethod) && methods.includes(financeFilterMethod);
    financeFilterPaymentMethod.value = methodAllowed ? financeFilterMethod : '';
    financeFilterMethod = financeFilterPaymentMethod.value;
  }

  function getRequestFilterSourceItems(allItems) {
    const rows = Array.isArray(allItems) ? allItems : [];
    const stageItems = currentStage === 'finance'
      ? rows.filter((it) => financeStages.has(String(it.stage || '').toLowerCase()) && hasFinanceTransaction(it))
      : rows.filter((it) => matchesStageTabFilter(it, currentStage));

    return stageItems
      .filter(matchesStatusFilter)
      .filter(matchesDocumentTypeFilter);
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
    const bodyText = String(text || '').trim().replace(/^\uFEFF/, '');
    const looksHtml = bodyText.startsWith('<!DOCTYPE') || bodyText.startsWith('<html');
    const looksJson = bodyText.startsWith('{') || bodyText.startsWith('[');

    if (!contentType.includes('application/json') && !looksJson) {
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
      data = JSON.parse(bodyText);
    } catch (_) {
      throw new Error('Invalid JSON response from server.');
    }

    if (!res.ok) {
      throw new Error(data?.message || `Request failed (${res.status}).`);
    }
    return data;
  }

  function rememberPreviewStateOverride(requestId, patch = {}) {
    const rid = String(requestId || '').trim();
    if (!rid || !patch || typeof patch !== 'object') return;
    pendingPreviewStateOverride = {
      requestId: rid,
      patch: { ...patch }
    };
  }

  function applyPendingPreviewStateOverride(requestId) {
    const rid = String(requestId || '').trim();
    if (!rid || !pendingPreviewStateOverride || pendingPreviewStateOverride.requestId !== rid) {
      return;
    }
    const currentState = viewPreviewState && typeof viewPreviewState === 'object'
      ? viewPreviewState
      : {};
    viewPreviewState = { ...currentState, ...pendingPreviewStateOverride.patch };
    pendingPreviewStateOverride = null;
  }

  function updateCachedRequestRecord(requestId, patch = {}) {
    const rid = String(requestId || '').trim();
    if (!rid || !patch || typeof patch !== 'object') return;

    cachedAllItems = cachedAllItems.map((item) => (
      String(item?.request_id || '') === rid ? { ...item, ...patch } : item
    ));

    const currentItem = itemById.get(rid);
    if (currentItem) {
      itemById.set(rid, { ...currentItem, ...patch });
    }

    if (detailById.has(rid)) {
      const detailItem = detailById.get(rid) || {};
      detailById.set(rid, { ...detailItem, ...patch });
    }
  }

  async function openRequestPreviewFromList(requestId) {
    const rid = String(requestId || '').trim();
    if (!rid || !tableBody) return false;

    let viewBtn = Array.from(tableBody.querySelectorAll('button[data-view-id]'))
      .find((candidate) => String(candidate.getAttribute('data-view-id') || '') === rid);

    if (!viewBtn) {
      await load({ force: true });
      viewBtn = Array.from(tableBody.querySelectorAll('button[data-view-id]'))
        .find((candidate) => String(candidate.getAttribute('data-view-id') || '') === rid);
    }

    if (!viewBtn) return false;
    openViewDirectPreview = true;
    viewBtn.click();
    return true;
  }

  function barangayIdRequestHasPhoto(row) {
    if (!row || typeof row !== 'object') return false;
    const payload = row.payload && typeof row.payload === 'object' ? row.payload : {};
    const residentProfile = row.resident_profile && typeof row.resident_profile === 'object'
      ? row.resident_profile
      : {};
    return [
      payload.id_picture_url,
      payload.id_picture_path,
      residentProfile.id_picture_url,
      residentProfile.id_picture_path
    ].some((value) => String(value || '').trim() !== '');
  }

  async function fetchRequestDetails(requestId, options = {}) {
    const id = String(requestId || '').trim();
    if (!id) return null;
    const force = !!(options && options.force);
    if (!force && detailById.has(id)) {
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
    const hasResidentLink = String(row.resident_user_id || '').trim() !== '' || String(row.resident_id || '').trim() !== '';
    const payloadResidentLink = payloadReady
      && (
        String(row.payload.resident_user_id || row.payload.user_id || '').trim() !== ''
        || String(row.payload.resident_id || '').trim() !== ''
      );
    if (!payloadReady) return false;
    if ((hasResidentLink || payloadResidentLink) && !profileReady) return false;
    if (normalizePreviewDocKey(row?.document_type || '') === 'barangayid' && !barangayIdRequestHasPhoto(row)) {
      return false;
    }
    return true;
  }

  async function ensureRowDetails(row) {
    if (!row || typeof row !== 'object') return row;
    const id = String(row.request_id || '').trim();
    if (!id) return row;
    if (rowHasModalDetails(row)) {
      return row;
    }
    try {
      const full = await ensureBarangayIdPhotoData(await fetchRequestDetails(id, { force: true }));
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

  function buildBarangayIdCardModalState(row, options = {}, templateConfig = null) {
    const payload = row && row.payload && typeof row.payload === 'object' ? row.payload : {};
    const residentProfile = row && row.resident_profile && typeof row.resident_profile === 'object'
      ? row.resident_profile
      : {};
    const previewState = options && options.previewState && typeof options.previewState === 'object'
      ? options.previewState
      : buildPreviewState(row, payload, residentProfile, null);
    const qrPreviewUrl = barangayIdQrPreviewUrl(row, payload);
    const resolvedConfig = templateConfig && typeof templateConfig === 'object' ? templateConfig : {};
    const fallbackFrontTemplateUrl = `${appBase}/Resident-End/Certificates/BarangayID/FRONT_EMPTY.png?v=20260324-01`;
    const fallbackBackTemplateUrl = `${appBase}/Resident-End/Certificates/BarangayID/BACK_EMPTY.png?v=20260324-01`;
    const frontTemplateUrl = String(resolvedConfig.frontTemplateUrl || fallbackFrontTemplateUrl).trim() || fallbackFrontTemplateUrl;
    const backTemplateUrl = String(resolvedConfig.backTemplateUrl || fallbackBackTemplateUrl).trim() || fallbackBackTemplateUrl;

    return {
      ...(previewState && typeof previewState === 'object' ? previewState : {}),
      appBase,
      templateVariant: String(resolvedConfig.templateVariant || 'empty').trim() || 'empty',
      frontTemplateUrl,
      frontTemplateFallbackUrl: fallbackFrontTemplateUrl,
      backTemplateUrl,
      backTemplateFallbackUrl: fallbackBackTemplateUrl,
      layoutConfig: resolvedConfig.layoutConfig || null,
      punongSignatoryName: firstNonEmpty([resolvedConfig.punongSignatoryName, previewState?.punongSignatoryName]),
      punongSignatoryTitle: firstNonEmpty([resolvedConfig.punongSignatoryTitle, previewState?.punongSignatoryTitle]),
      punongSignatorySignatureUrl: resolvePublicUrl(firstNonEmpty([
        resolvedConfig.punongSignatorySignatureUrl,
        previewState?.punongSignatorySignatureUrl
      ])),
      qrUrl: resolvePublicUrl(firstNonEmpty([qrPreviewUrl, previewState?.qrUrl])),
      photoUrl: firstNonEmpty([
        resolvePublicUrl(firstNonEmpty([
          payload.id_picture_url,
          payload.id_picture_path,
          residentProfile.id_picture_url,
          residentProfile.id_picture_path
        ])),
        previewState?.photoUrl,
        `${appBase}/Images/Profile-Placeholder.png`
      ])
    };
  }

  function renderQuickRequestSummary(row) {
    const docName = trackerDocumentTypeDisplay(row);
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
    const badgeItems = allItems.filter(matchesDocumentTypeFilter);
    updateStageTabBadges(badgeItems);
    const stageItems = currentStage === 'finance'
      ? allItems.filter((it) => financeStages.has(String(it.stage || '').toLowerCase()) && hasFinanceTransaction(it))
      : allItems.filter((it) => matchesStageTabFilter(it, currentStage));
    updateFinanceStatusTabBadges(stageItems);
    syncDocumentTypeFilterOptions(stageItems);
    const requestFilterSourceItems = getRequestFilterSourceItems(allItems);
    syncFinanceFilterOptions(requestFilterSourceItems);
    syncRequestFilterOptions(requestFilterSourceItems);

    const items = stageItems
      .filter(matchesStatusFilter)
      .filter(matchesFinanceAdvancedFilters)
      .filter(matchesDocumentTypeFilter)
      .filter(matchesRequestModalFilters)
      .filter(matchesSearchFilter);

    itemById = new Map(items.map((it) => [String(it.request_id), it]));
    if (!items.length) {
      tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No requests found.</td></tr>';
      return;
    }

    tableBody.innerHTML = items.map(rowHtml).join('');
    applyFinanceColumnVisibility();
    bindActionButtons();
    bindFeeCatalogModal();
  }

  async function load(options = {}) {
    const force = !!options.force;
    const showRefreshLoading = !!options.showRefreshLoading;
    if (!force && Array.isArray(cachedAllItems) && cachedAllItems.length > 0) {
      renderFromCache();
      return;
    }

    if (showRefreshLoading) {
      setRefreshLoading(true);
    }
    tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr>';
    try {
      const params = new URLSearchParams({ action: 'list' });
      params.set('lite', '1');
      if (isFinancePaymentsPage) {
        params.set('list_context', 'finance');
        params.set('limit', '250');
      } else {
        // The endpoint returns all issuance modules together and document-type
        // filtering happens below on the client. Fetch the supported maximum so
        // newer ID/clearance rows cannot crowd certificates out of the result.
        params.set('limit', '250');
      }
      const data = await fetchJson(`${endpoint}?${params.toString()}`);
      if (!data.success) throw new Error(data.message || 'Failed to load requests.');
      cachedAllItems = Array.isArray(data.items) ? data.items : [];
      renderFromCache();
    } catch (err) {
      tableBody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${esc(err.message || err)}</td></tr>`;
    } finally {
      if (showRefreshLoading) {
        setRefreshLoading(false);
      }
    }
  }

  function resetModalFields() {
    financeVerifySummaryToken += 1;
    modalError.classList.add('d-none');
    modalError.textContent = '';
    if (actionPrompt) {
      actionPrompt.classList.add('d-none');
      actionPrompt.textContent = '';
    }
    actionReasonWrap.classList.add('d-none');
    actionAmountWrap.classList.add('d-none');
    actionOrWrap.classList.add('d-none');
    actionValidityWrap?.classList.add('d-none');
    actionIssuedWrap.classList.add('d-none');
    actionBusinessApprovalWrap?.classList.add('d-none');
    actionPlateWrap?.classList.add('d-none');
    actionReason.required = false;
    actionAmount.required = false;
    actionOr.required = false;
    if (actionValidity) {
      actionValidity.required = false;
      actionValidity.value = '';
      delete actionValidity.dataset.validityKind;
    }
    actionIssued.required = false;
    if (actionBusinessApproval) {
      actionBusinessApproval.required = false;
      actionBusinessApproval.value = '';
    }
    actionBusinessApprovalOptions.forEach((option) => {
      option.checked = false;
      option.disabled = false;
    });
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
      modalTitle.textContent = 'Approved | Tick Selection';
    }
    if (actionPrompt) {
      actionPrompt.textContent = 'Choose the approval type for this Barangay Clearance for Business Permit. After this, you will tag the fees before reviewing the initial document preview for inspection approval.';
      actionPrompt.classList.remove('d-none');
    }
    if (actionBusinessApprovalWrap) {
      actionBusinessApprovalWrap.classList.remove('d-none');
    }
    if (actionBusinessApproval) {
      actionBusinessApproval.required = false;
      actionBusinessApproval.value = encodeBusinessApprovalTypes(selectedValue || '');
    }
    syncBusinessApprovalSelection(selectedValue || '');
    if (actionPlateWrap) {
      actionPlateWrap.classList.remove('d-none');
    }
    if (actionPlate) {
      actionPlate.required = true;
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

  function openActionModal(type, requestId, options = {}) {
    if (!actionModal) return;
    if (actionForm?.dataset?.verifyMode) {
      delete actionForm.dataset.verifyMode;
    }

    const explicitReturnTarget = String(options?.returnTarget || '').trim();
    const isReturningFromView = !!(
      viewModalEl &&
      viewModalEl.classList.contains('show') &&
      viewModal
    );
    const showPreparedActionModal = () => {
      if (isReturningFromView) {
        runAfterModalHidden(viewModalEl, () => actionModal.show());
        viewModal.hide();
        return;
      }
      actionModal.show();
    };
    actionReturnState = options && typeof options === 'object' && options.reopenState
      ? { ...options.reopenState }
      : null;
    if (explicitReturnTarget === 'paymentProof') {
      actionReturnTarget = 'paymentProof';
    } else if (isReturningFromView) {
      preserveViewStateOnNextHide = true;
      actionReturnTarget = 'view';
    } else {
      actionReturnTarget = '';
      actionReturnState = null;
    }
    suppressActionReturn = false;

    resetModalFields();
    actionType.value = type;
    actionRequestId.value = requestId;
    const row = itemById.get(String(requestId));
    const docKey = normalizePreviewDocKey(row?.document_type || '');
    const isFirstTimeJobSeeker = isFirstTimeJobSeekerRow(row);
    const needsFeeTagging = requestNeedsFeeTagging(row);
    const needsInspection = requestRequiresInspection(row);
    const needsManualIssuedUpload = requestNeedsManualIssuedUpload(row);
    const isBarangayIdRequest = docKey === 'barangayid';
    const isCertificateRequest = isCertificateRequestRow(row);
    const actionValidityKind = resolveActionValidityKind(docKey, isFirstTimeJobSeeker);
    if (actionForm) {
      actionForm.dataset.docKey = docKey;
    }
    const actionDocKey = String(actionForm?.dataset?.docKey || '');
    const isBusinessClearanceApproval = type === 'personnel_approve' && !isFirstTimeJobSeeker && actionDocKey === 'businessclearance';
    const existingBusinessApprovalType = encodeBusinessApprovalTypes(firstNonEmpty([
      options?.businessApprovalType,
      viewPreviewState?.businessApprovalType,
      row?.payload?._preview_business_approval_type,
      row?.payload?.business_approval_type
    ]));
    const existingPlateNumber = firstNonEmpty([
      options?.plateNumber,
      viewPreviewState?.plateNumber,
      row?.payload?._preview_plate_number,
      row?.payload?.plate_number,
      row?.payload?.business_plate_number,
      row?.payload?.vehicle_plate_number
    ]).toUpperCase();
    const existingDocumentValidity = actionValidityKind
      ? resolveValidityDateByKind(actionValidityKind, firstNonEmpty([
          options?.documentValidity,
          viewPreviewState?.documentValidity,
          row?.document_validity,
          row?.payload?.document_validity,
          ...(actionValidityKind === 'barangay_id' ? [row?.payload?.barangay_id_valid_until] : [])
        ]), defaultValidityDateForKind(actionValidityKind))
      : '';

    const rowStage = String(row?.stage || '').toLowerCase();
    const isWalkInFlow = rowStage === 'for_payment' || rowStage === 'payment_rejected';
    const labels = {
      personnel_approve: isFirstTimeJobSeeker ? 'Approve for Interview' : (needsFeeTagging ? 'Before You Tag Fees' : 'Before You Approve'),
      personnel_approve_confirm: 'Confirm Approval',
      personnel_reject: 'Reject Request',
      interview_pass: 'Review Interview Result',
      interview_pass_confirm: 'Confirm Interview Approval',
      interview_fail: 'Fail Interview',
      inspection_pass: 'Pass Inspection',
      inspection_fail: 'Fail Inspection',
      finance_verify: isWalkInFlow ? 'Record Walk-in Payment' : 'Verify Payment / Walk-in Payment',
      finance_reject: 'Reject Payment',
      mark_ready: isBarangayIdRequest ? 'Mark ID as Printed' : 'Mark Ready for Claim',
      mark_completed_confirm: isBarangayIdRequest ? 'Confirm ID Claim' : 'For Release',
      mark_completed: isBarangayIdRequest ? 'Mark as Claimed' : 'Release Document'
    };
    modalTitle.textContent = labels[type] || 'Update Request';
    const docName = normalizeDocumentTypeDisplay(String(row?.document_type || 'document'));

    if (isBusinessClearanceApproval) {
      configureBusinessApprovalSelectionStep(existingBusinessApprovalType, existingPlateNumber);
      showPreparedActionModal();
      return;
    }

    if (type === 'personnel_approve' && actionPrompt) {
      actionPrompt.textContent = isFirstTimeJobSeeker
        ? 'This request will be moved to the interview stage. The resident will be notified to report to the barangay within 5 working days for the oath of undertaking and interview.'
        : (isBarangayIdRequest
            ? 'Choose the Barangay ID validity above, then click Review Application to inspect the submitted details. Once everything is correct, proceed to approve the request for release.'
        : (needsFeeTagging
            ? `Tag the applicable fees for the ${docName} first. After confirming the fees, the initial document preview will open so you can save and approve it for ${needsInspection ? 'inspection' : 'payment'}.`
            : (isCertificateRequest
                ? `Set the certificate validity above first, then click View Document to check the document that will be issued. Once everything is correct, proceed to ${needsInspection ? `approve the ${docName} for inspection` : `verify the ${docName}`}.`
                : `Click View Document to check the document that will be issued and edit it if there are necessary changes in the details. Once everything is correct, proceed to ${needsInspection ? `approve the ${docName} for inspection` : `verify the ${docName}`}.`)));
      actionPrompt.classList.remove('d-none');
    }
    if (type === 'personnel_approve_confirm' && actionPrompt) {
      actionPrompt.textContent = isBarangayIdRequest
        ? 'Please confirm that you thoroughly checked the submitted Barangay ID application. This will approve the request and tag the ID for printing.'
        : (needsFeeTagging
            ? `Please confirm that you thoroughly checked the resident's data. This will save the ${docName} and approve it for ${needsInspection ? 'inspection' : 'payment'}.`
            : `Please confirm that you thoroughly checked the resident's data to ${needsInspection ? `approve the ${docName} for inspection` : `issue a ${docName}`}.`);
      actionPrompt.classList.remove('d-none');
    }
    const usesPreviewValiditySelection = (
      type === 'interview_pass'
      || (type === 'personnel_approve' && !isFirstTimeJobSeeker)
    );
    const usesPreviewValidityConfirm = [
      'personnel_approve_confirm',
      'interview_pass_confirm',
    ].includes(type);
    if (usesPreviewValiditySelection && actionValidityKind && actionValidityWrap && actionValidity) {
      actionValidityWrap.classList.remove('d-none');
      actionValidity.required = true;
      configureValidityField(actionValidityLabel, actionValidityHelp, actionValidity, actionValidityKind, existingDocumentValidity);
    } else if (usesPreviewValidityConfirm && actionValidityWrap && actionValidity) {
      // Keep the selected validity from the preview step and prevent a second picker from showing here.
      actionValidityWrap.classList.add('d-none');
      actionValidity.required = false;
      actionValidity.value = existingDocumentValidity;
    }
    if (type === 'mark_completed_confirm' && actionPrompt) {
      actionPrompt.textContent = isBarangayIdRequest
        ? 'Confirm that the resident has claimed the printed Barangay ID. This will mark the request as completed.'
        : 'Are you sure you want to release this document now? This will mark the request as completed.';
      actionPrompt.classList.remove('d-none');
    }
    if ((type === 'personnel_reject' || type === 'finance_reject') && actionPrompt) {
      actionPrompt.textContent = 'Please provide the reason for rejection.';
      actionPrompt.classList.remove('d-none');
    }
    if (type === 'interview_pass' && actionPrompt) {
      actionPrompt.textContent = 'Set the certificate validity above first, then click View Document to review the First Time Job Seeker certificate. Once everything is correct, save and process it for release.';
      actionPrompt.classList.remove('d-none');
    }
    if (type === 'interview_pass_confirm' && actionPrompt) {
      actionPrompt.textContent = 'Please confirm the interview result and the document details. This will save the preview and move the request to release.';
      actionPrompt.classList.remove('d-none');
    }
    if (type === 'interview_fail' && actionPrompt) {
      actionPrompt.textContent = 'Please provide the reason why the resident did not pass the interview.';
      actionPrompt.classList.remove('d-none');
    }
    if (type === 'inspection_pass' && actionPrompt) {
      actionPrompt.textContent = 'Please confirm that the request passed inspection. Requests with tagged fees will move to payment, while zero-fee requests will move directly to release.';
      actionPrompt.classList.remove('d-none');
    }
    if (type === 'inspection_fail' && actionPrompt) {
      actionPrompt.textContent = 'Please provide the reason why the request did not pass inspection.';
      actionPrompt.classList.remove('d-none');
    }
    if (actionSubmitBtn) {
      if (type === 'personnel_approve') {
        actionSubmitBtn.textContent = isFirstTimeJobSeeker
          ? 'Approve for Interview'
          : (isBarangayIdRequest ? 'Review Application' : (needsFeeTagging ? 'Tag Fees' : 'View Document'));
        actionSubmitBtn.classList.remove('btn-danger', 'btn-success', 'btn-primary');
        actionSubmitBtn.classList.add(isFirstTimeJobSeeker ? 'btn-success' : 'btn-primary');
      } else if (type === 'personnel_approve_confirm') {
        actionSubmitBtn.textContent = 'Save and Approve';
        actionSubmitBtn.classList.remove('btn-danger', 'btn-primary');
        actionSubmitBtn.classList.add('btn-success');
      } else if (type === 'interview_pass') {
        actionSubmitBtn.textContent = 'View Document';
        actionSubmitBtn.classList.remove('btn-danger', 'btn-primary');
        actionSubmitBtn.classList.add('btn-success');
      } else if (type === 'interview_pass_confirm') {
        actionSubmitBtn.textContent = 'Save and Process';
        actionSubmitBtn.classList.remove('btn-danger', 'btn-primary');
        actionSubmitBtn.classList.add('btn-success');
      } else if (type === 'interview_fail') {
        actionSubmitBtn.textContent = 'Fail Interview';
        actionSubmitBtn.classList.remove('btn-primary', 'btn-success');
        actionSubmitBtn.classList.add('btn-danger');
      } else if (type === 'inspection_pass') {
        actionSubmitBtn.textContent = 'Pass Inspection';
        actionSubmitBtn.classList.remove('btn-danger', 'btn-primary');
        actionSubmitBtn.classList.add('btn-success');
      } else if (type === 'inspection_fail') {
        actionSubmitBtn.textContent = 'Fail Inspection';
        actionSubmitBtn.classList.remove('btn-primary', 'btn-success');
        actionSubmitBtn.classList.add('btn-danger');
      } else if (type === 'mark_completed_confirm') {
        actionSubmitBtn.textContent = isBarangayIdRequest ? 'Confirm Claimed' : 'Release Now';
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
      } else if (type === 'mark_ready') {
        actionSubmitBtn.textContent = isBarangayIdRequest ? 'Mark Printed / For Claim' : (needsManualIssuedUpload ? 'Upload & Mark Ready' : 'Mark Ready');
        actionSubmitBtn.classList.remove('btn-danger', 'btn-primary');
        actionSubmitBtn.classList.add('btn-success');
      }
    }
    if (
      type === 'personnel_approve' ||
      type === 'personnel_approve_confirm' ||
      type === 'personnel_reject' ||
      type === 'interview_pass' ||
      type === 'interview_pass_confirm' ||
      type === 'interview_fail' ||
      type === 'inspection_pass' ||
      type === 'inspection_fail' ||
      type === 'mark_completed_confirm'
    ) {
      actionForm?.querySelector('.modal-footer')?.classList.add('action-split');
    }

    if (type === 'personnel_reject' || type === 'finance_reject' || type === 'interview_fail' || type === 'inspection_fail') {
      actionReasonWrap.classList.remove('d-none');
      actionReason.required = true;
    }
    if (type === 'finance_verify') {
      actionOrWrap.classList.remove('d-none');
      actionOr.required = true;
      const stage = String(row?.stage || '').toLowerCase();
      const isPendingVerification = isFinancePaymentsPage
        ? isFinanceOnlineVerificationEligible(row)
        : stage === 'payment_submitted';
      const isWalkInStage = isFinancePaymentsPage
        ? isFinanceWalkInEligible(row)
        : stage === 'for_payment' || stage === 'payment_rejected';
      const fixedAmount = resolveSystemAmount(row);

      if (Number.isFinite(fixedAmount)) {
        actionAmountWrap.classList.add('d-none');
        actionAmount.required = false;
        if (actionAmount) {
          actionAmount.value = fixedAmount.toFixed(2);
          actionAmount.readOnly = true;
          actionAmount.classList.add('bg-light');
        }
      } else if (needsFeeTagging) {
        actionAmountWrap.classList.add('d-none');
        actionAmount.required = false;
        if (actionAmount) {
          actionAmount.value = '';
          actionAmount.readOnly = true;
          actionAmount.classList.add('bg-light');
        }
      } else if (isPendingVerification) {
        actionAmountWrap.classList.add('d-none');
        actionAmount.required = false;
      } else {
        actionAmountWrap.classList.remove('d-none');
        actionAmount.required = true;
        if (actionAmount) {
          actionAmount.readOnly = false;
          actionAmount.classList.remove('bg-light');
        }
      }

      populateFinanceVerifyPrompt(row, {
        isPendingVerification,
        isWalkInStage
      }).catch(() => {});
    }
    if (type === 'mark_ready') {
      if (actionIssued) {
        actionIssued.value = '';
      }
      if (isBarangayIdRequest) {
        actionIssuedWrap.classList.add('d-none');
        actionIssued.required = false;
        actionPrompt.textContent = 'Confirm that the Barangay ID has been printed. The resident will be notified that it is ready for claim.';
      } else if (needsManualIssuedUpload) {
        actionIssuedWrap.classList.remove('d-none');
        actionIssued.required = true;
        actionPrompt.textContent = 'Upload the prepared issued file to mark this request as ready for claim.';
      } else {
        actionIssuedWrap.classList.add('d-none');
        actionPrompt.textContent = 'This will generate the issued document and mark the request as ready for claim.';
      }
      actionPrompt.classList.remove('d-none');
    }

    showPreparedActionModal();
  }

  // ── Fee Tagging Modal ────────────────────────────────────────────────────────

  async function openFeeTaggingModal(requestId, options = {}) {
    if (!requestId) return;
    const row = itemById.get(requestId);
    const feeTagModal = document.getElementById('feeTaggingModal');
    if (!feeTagModal) return;

    const openPreviewOnSave = options.openPreviewOnSave !== false;
    const modeInput = document.getElementById('feeTaggingMode');
    if (modeInput) modeInput.value = openPreviewOnSave ? 'preview' : '';

    const modalTitle = feeTagModal.querySelector('.modal-title');
    if (modalTitle) {
      modalTitle.innerHTML = '<i class="fas fa-tags me-2 text-warning"></i>Tag Clearance Fees';
    }
    const submitBtn = document.getElementById('feeTaggingSubmitBtn');
    if (submitBtn) submitBtn.textContent = openPreviewOnSave ? 'Confirm Fees & Continue' : 'Save Fees';
    const returnBtn = document.getElementById('feeTaggingReturnBtn');
    feeTaggingReturnState = options && typeof options.returnState === 'object' && String(options.returnState.kind || '').trim() === 'action'
      ? { ...options.returnState }
      : null;
    if (returnBtn) {
      returnBtn.classList.toggle('d-none', !feeTaggingReturnState);
      returnBtn.disabled = false;
    }

    const feeTagBody = document.getElementById('feeTaggingBody');
    const feeTagRequestId = document.getElementById('feeTaggingRequestId');
    if (feeTagRequestId) feeTagRequestId.value = requestId;

    const loadToken = ++feeTaggingLoadToken;
    if (feeTagBody) feeTagBody.innerHTML = renderFeeTaggingLoadingState(row);
    if (submitBtn) submitBtn.disabled = true;
    getOrCreateModalInstance(feeTagModal)?.show();

    try {
      const [feeTypes, taggedFees] = await Promise.all([
        fetchFeeTypeCatalog(),
        fetchTaggedClearanceFees(requestId)
      ]);

      const latestRequestId = document.getElementById('feeTaggingRequestId')?.value?.trim();
      if (loadToken !== feeTaggingLoadToken || latestRequestId !== requestId) {
        return;
      }

      updateCachedRequestRecord(requestId, {
        clearance_fees: taggedFees,
        clearance_fee_count: taggedFees.length
      });
      if (feeTagBody) feeTagBody.innerHTML = renderFeeTaggingForm(row, feeTypes, taggedFees);
      bindFeeTaggingTable();
      if (submitBtn) submitBtn.disabled = false;
    } catch (e) {
      if (loadToken !== feeTaggingLoadToken) {
        return;
      }
      if (feeTagBody) feeTagBody.innerHTML = renderFeeTaggingErrorState(e?.message || 'Failed to load fee tagging data.');
      if (submitBtn) submitBtn.disabled = true;
    }
  }

  function reopenFeeTaggingReturnState() {
    const state = feeTaggingReturnState && typeof feeTaggingReturnState === 'object'
      ? { ...feeTaggingReturnState }
      : null;
    feeTaggingReturnState = null;
    if (!state) return;

    if (state.kind === 'action') {
      openActionModal(String(state.actionType || 'personnel_approve'), String(state.requestId || ''), {
        businessApprovalType: String(state.businessApprovalType || '').trim(),
        plateNumber: String(state.plateNumber || '').trim(),
        documentValidity: String(state.documentValidity || '').trim()
      });
      return;
    }

  }

  function updateFeeTagTotal() {
    let total = 0;
    document.querySelectorAll('#feeTaggingRows tr[data-fee-row]').forEach((row) => {
      const check = row.querySelector('.fee-tag-check');
      if (!check || !check.checked) return;
      const amt = parseFloat(row.querySelector('.fee-tag-amount')?.value || '0') || 0;
      total += amt;
    });
    const el = document.getElementById('feeTaggingTotal');
    if (el) el.textContent = `₱${total.toFixed(2)}`;
    syncFeeTagSelectAllState();
  }

  async function submitFeeTagging() {
    const requestId = document.getElementById('feeTaggingRequestId')?.value?.trim();
    if (!requestId) return;

    const openPreviewOnSave = document.getElementById('feeTaggingMode')?.value === 'preview';

    const fees = [];
    document.querySelectorAll('#feeTaggingRows tr[data-fee-row]').forEach((row) => {
      const check = row.querySelector('.fee-tag-check');
      if (!check || !check.checked) return;
      // Name could be a static span or an input (custom row)
      const nameEl = row.querySelector('.fee-tag-name') || row.querySelector('.fee-tag-name-input');
      const name = nameEl ? (nameEl.textContent || nameEl.value || '').trim() : '';
      const amt = parseFloat(row.querySelector('.fee-tag-amount')?.value || '0') || 0;
      if (name) fees.push({ fee_name: name, amount: amt });
    });

    const btn = document.getElementById('feeTaggingSubmitBtn');
    if (btn) { btn.disabled = true; btn.textContent = 'Processing...'; }

    try {
      const body = new FormData();
      body.append('action', 'tag_clearance_fees');
      body.append('request_id', requestId);
      body.append('fees', JSON.stringify(fees));
      body.append('transition_to_payment', '0');
      const data = await fetchJson(endpoint, { method: 'POST', body });
      if (!data.success) {
        alert(data.message || 'Failed to tag fees.');
        return;
      }
      updateCachedRequestRecord(requestId, {
        ...(data?.request && typeof data.request === 'object' ? data.request : {}),
        fee_amount: Number.isFinite(Number(data?.total)) ? Number(data.total) : null,
        clearance_fees: fees,
        clearance_fee_count: fees.length
      });
      const feeTagModal = document.getElementById('feeTaggingModal');
      if (feeTagModal) bootstrap.Modal.getInstance(feeTagModal)?.hide();
      feeTaggingReturnState = null;
      if (openPreviewOnSave) {
        await new Promise((resolve) => window.setTimeout(resolve, 180));
        const opened = await openRequestPreviewFromList(requestId);
        if (!opened) {
          await load({ force: true });
        }
      } else {
        await load({ force: true });
      }
    } catch (e) {
      alert(e?.message || 'Failed to tag fees. Please try again.');
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.textContent = document.getElementById('feeTaggingMode')?.value === 'preview'
          ? 'Confirm Fees & Continue'
          : 'Save Fees';
      }
    }
  }

  document.getElementById('feeTaggingReturnBtn')?.addEventListener('click', () => {
    const feeTagModal = document.getElementById('feeTaggingModal');
    if (!feeTagModal) return;
    bootstrap.Modal.getInstance(feeTagModal)?.hide();
    window.setTimeout(() => {
      reopenFeeTaggingReturnState();
    }, 160);
  });

  document.getElementById('feeTaggingModal')?.addEventListener('hidden.bs.modal', () => {
    const returnBtn = document.getElementById('feeTaggingReturnBtn');
    if (returnBtn) {
      returnBtn.classList.add('d-none');
      returnBtn.disabled = false;
    }
  });

  // ── Finance Fee Catalog CRUD ─────────────────────────────────────────────────

  async function openFeeCatalogModal() {
    const modal = document.getElementById('feeCatalogModal');
    if (!modal) return;
    getOrCreateModalInstance(modal)?.show();
    await refreshFeeCatalogTable({ force: true });
  }

  async function refreshFeeCatalogTable(options = {}) {
    const force = !!options.force;
    const tbody = document.getElementById('feeCatalogTableBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="4" class="text-muted text-center py-3">Loading…</td></tr>';
    try {
      const rows = await fetchFeeTypeCatalog({ force });
      if (!rows.length) { tbody.innerHTML = '<tr><td colspan="4" class="text-muted text-center">No fee types yet.</td></tr>'; return; }
      tbody.innerHTML = rows.map((ft) => `
        <tr>
          <td>${esc(ft.fee_name)}</td>
          <td>₱${Number(ft.default_amount).toFixed(2)}</td>
          <td><span class="badge ${ft.status === 'approved' ? 'bg-success' : 'bg-secondary'}">${ft.status === 'approved' ? 'Active' : esc(ft.status)}</span></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-primary me-1" onclick="editFeeType(${ft.fee_type_id},'${esc(ft.fee_name)}',${ft.default_amount},${ft.is_active})">Edit</button>
            <button class="btn btn-sm btn-outline-danger" onclick="deleteFeeType(${ft.fee_type_id},'${esc(ft.fee_name)}')">Delete</button>
          </td>
        </tr>`).join('');
    } catch (_) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-danger text-center">Error loading data.</td></tr>';
    }
  }

  window.editFeeType = function(id, name, amount, isActive) {
    document.getElementById('feeCatalogFeeTypeId').value = id || '';
    document.getElementById('feeCatalogFeeName').value = name || '';
    document.getElementById('feeCatalogDefaultAmount').value = Number(amount).toFixed(2);
    document.getElementById('feeCatalogIsActive').checked = isActive == 1;
    document.getElementById('feeCatalogFormTitle').textContent = id ? 'Edit Fee Type' : 'Add Fee Type';
  };

  window.deleteFeeType = async function(id, name) {
    if (!(await UniversalModal.confirm(`Delete fee type "${name}"?`, { confirmLabel: 'Delete', confirmClass: 'btn btn-danger' }))) return;
    const body = new FormData();
    body.append('action', 'delete_fee_type');
    body.append('fee_type_id', id);
    const data = await fetchJson(endpoint, { method: 'POST', body });
    if (data.success) { await refreshFeeCatalogTable({ force: true }); }
    else { alert(data.message || 'Delete failed.'); }
  };

  async function saveFeeCatalogForm() {
    const id = (document.getElementById('feeCatalogFeeTypeId')?.value || '').trim();
    const name = (document.getElementById('feeCatalogFeeName')?.value || '').trim();
    const amount = parseFloat(document.getElementById('feeCatalogDefaultAmount')?.value || '0') || 0;
    const isActive = document.getElementById('feeCatalogIsActive')?.checked ? 1 : 0;
    if (!name) { alert('Fee name is required.'); return; }
    const body = new FormData();
    body.append('action', 'save_fee_type');
    if (id) body.append('fee_type_id', id);
    body.append('fee_name', name);
    body.append('default_amount', String(amount));
    body.append('is_active', String(isActive));
    const data = await fetchJson(endpoint, { method: 'POST', body });
    if (data.success) {
      document.getElementById('feeCatalogFeeTypeId').value = '';
      document.getElementById('feeCatalogFeeName').value = '';
      document.getElementById('feeCatalogDefaultAmount').value = '0.00';
      document.getElementById('feeCatalogIsActive').checked = true;
      document.getElementById('feeCatalogFormTitle').textContent = 'Add Fee Type';
      await refreshFeeCatalogTable({ force: true });
    } else { alert(data.message || 'Save failed.'); }
  }

  function bindFeeCatalogModal() {
    if (feeCatalogModalBound) return;
    feeCatalogModalBound = true;
    const saveBtn = document.getElementById('feeCatalogSaveBtn');
    if (saveBtn) saveBtn.addEventListener('click', saveFeeCatalogForm);
    const submitBtn = document.getElementById('feeTaggingSubmitBtn');
    if (submitBtn) submitBtn.addEventListener('click', submitFeeTagging);
    const manageFeeBtn = document.getElementById('btnManageFees');
    if (manageFeeBtn) manageFeeBtn.addEventListener('click', openFeeCatalogModal);
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
            viewModalDocBtn.textContent = 'View Document';
          }
          const openImmediately = isFinancePaymentsPage || !rowHasModalDetails(row);
          if (openImmediately) {
            viewDetailsBody.innerHTML = renderQuickRequestSummary(row);
            viewModal.show();
          }

          row = await ensureRowDetails(row);
          if (!row) return;
          if (normalizePreviewDocKey(row?.document_type || '') === 'barangayid') {
            await fetchBarangayIdTemplateConfig({ force: true }).catch(() => null);
            row = await ensureBarangayIdPhotoData(row).catch(() => row);
          }

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
              + (paymentProofHtml ? formSection('Submitted Proof of Payment', paymentProofHtml) : '')
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
          currentViewStage = resolveWorkflowStage(row);
          if (viewModalActions) {
            viewModalActions.innerHTML = '';
          }
          if (viewModalDocBtn) {
            const issuedDocReady = canOpenIssuedDocument(row);
            const issuedDocUrl = issuedDocumentUrl(String(row.request_id || ''), row);
            const issuedStageKey = resolveWorkflowStage(row);
            const isBarangayIdIssuedDoc = normalizePreviewDocKey(row?.document_type || '') === 'barangayid';
            viewModalDocBtn.classList.remove('d-none');
            viewModalDocBtn.textContent = issuedDocReady
              ? (isBarangayIdIssuedDoc ? 'View ID' : 'View Issued Document')
              : 'View Document';
            viewModalDocBtn.onclick = () => {
              if (issuedDocReady && issuedDocUrl) {
                if (viewModalEl && viewModalEl.classList.contains('show') && viewModal) {
                  preserveViewStateOnNextHide = true;
                  viewModal.hide();
                }
                if (isBarangayIdIssuedDoc) {
                  openBarangayIdCardModal(row, issuedDocUrl, issuedDocumentTitle(row), 'view', {
                    allowPrint: ['for_printing', 'ready_for_claim', 'completed'].includes(issuedStageKey),
                    completed: issuedStageKey === 'completed',
                    releaseRequestId: ['for_printing', 'ready_for_claim'].includes(issuedStageKey) ? String(row.request_id || '') : '',
                    previewState: viewPreviewState
                  });
                  return;
                }
                openDocumentModal(issuedDocUrl, issuedDocumentTitle(row), 'view', {
                  allowPrint: ['for_printing', 'ready_for_claim', 'completed'].includes(issuedStageKey),
                  completed: issuedStageKey === 'completed',
                  releaseRequestId: ['for_printing', 'ready_for_claim'].includes(issuedStageKey) ? String(row.request_id || '') : ''
                });
                return;
              }
              viewPreviewState = buildPreviewState(row, payload, residentProfile, null);
              applyPendingPreviewStateOverride(String(row.request_id || ''));
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
            if (isFinanceWalkInEligible(row)) {
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
          const isBusinessPermitRequest = String(row?.document_type || payload?.document_type || '')
            .toLowerCase()
            .includes('business permit');
          const isRelationshipJailVisit = String(payload?.cohabitation_variant || '').trim() === 'relationship_jail_visit'
            || String(payload?.cohabitation_variant || '').trim() === 'conjugal_visit';
          const consumedKeys = new Set();
          const collectFirst = (...keys) => {
            for (const key of keys) {
              if (!key) continue;
              const value = payload[key];
              if (value === null || value === undefined) continue;
              const text = String(value).trim();
              if (text === '' || looksLikeProtectedValue(text)) continue;
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
              if (text === '' || looksLikeProtectedValue(text)) continue;
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
          { label: 'Age', value: firstNonEmpty([collectFirst('age'), collectResidentFirst('age')]) },
          { label: 'Sex', value: firstNonEmpty([collectFirst('sex', 'gender', 'child_sex'), collectResidentFirst('sex')]) },
          { label: 'Civil Status', value: firstNonEmpty([collectFirst('civil_status'), collectResidentFirst('civil_status')]) },
          { label: 'Religion', value: firstNonEmpty([collectFirst('religion'), collectResidentFirst('religion')]) },
          { label: 'Occupation', value: firstNonEmpty([collectFirst('occupation'), collectResidentFirst('occupation')]) }
        ];
        if (!isBusinessPermitRequest) {
          personalFields.splice(6, 0, {
            label: 'Birthdate',
            value: firstNonEmpty([collectFirst('birthdate', 'date_of_birth', 'child_dob'), collectResidentFirst('birthdate')])
          });
        }

        const technicalKeys = new Set([
          'action', 'csrf_token', 'redirect', 'document_type', 'suffix_name_display', 'suffix_display',
          'child_sex_display', 'cohabitant_region_select', 'cohabitant_province_select',
          'cohabitant_city_select', 'cohabitant_barangay_select', 'cohabitantSameAddress',
          'cohabitationAgree', 'cohabitation_agree',
          'birthdate', 'date_of_birth', 'child_dob',
          'cohabitant_dob', 'cohabitant_birthdate', 'partner_birthdate',
          'preview_full_name', 'cohabitant_full_name',
          'cohabitant_full_address', 'cohabitant_full_address_display', 'cohabitant_address_system',
          'cohabitation_full_address', 'cohabitation_full_address_display', 'cohabitation_address_system',
          'cohabitation_full_unit_number', 'cohabitation_full_house_lot_number',
          'cohabitation_full_street_block_name', 'cohabitation_full_subdivision',
          'cohabitation_full_barangay', 'cohabitation_full_area_number',
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
        if (isBusinessPermitRequest) {
          technicalKeys.add('birthplace');
          technicalKeys.add('place_of_birth');
          technicalKeys.add('birthdate');
          technicalKeys.add('date_of_birth');
        }
        if (String(payload?.cohabitation_variant || '').trim() !== '') {
          technicalKeys.add('birthdate');
          technicalKeys.add('date_of_birth');
          technicalKeys.add('child_dob');
        }

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

        const submissionTargetTypeText = String(firstNonEmpty([payload.submission_target_type]) || '').trim().toLowerCase();
        const institutionNameText = firstNonEmpty([payload.institution_name]);
        const institutionPersonText = firstNonEmpty([payload.institution_person]);
        const institutionPositionText = firstNonEmpty([payload.institution_position]);
        if (submissionTargetTypeText === 'institution' && (institutionNameText || institutionPersonText || institutionPositionText)) {
          consumedKeys.add('submission_target_type');
          consumedKeys.add('institution_name');
          consumedKeys.add('institution_person');
          consumedKeys.add('institution_position');
          consumedKeys.add('request_officer_line1');
          consumedKeys.add('request_officer_line2');
          consumedKeys.add('request_officer_line3');
          consumedKeys.add('request_officer');

          if (institutionPersonText) {
            requestFields.push({ label: 'Person to Address', value: institutionPersonText });
          }
          if (institutionPositionText) {
            requestFields.push({ label: 'Position / Department', value: institutionPositionText });
          }
          if (institutionNameText) {
            requestFields.push({ label: 'Institution Name', value: institutionNameText });
          }
        } else {
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
              requestFields.push({ label: 'Position / Department', value: positionText });
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
          if (normalized.startsWith('preview_') || normalized.startsWith('_preview_')) return;
          if (normalized.endsWith('_display') || normalized.startsWith('display_')) return;
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
        const contactFields = personalFields.filter((f) => ['Contact Number', 'Full Address'].includes(f.label));
        const contactGrid = contactFields.length
          ? `<div class="tracker-form-grid contact-address-grid">${contactFields.map((f) => formField(f.label, f.value, !!f.raw, !!f.wide)).join('')}</div>`
          : '';
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
                   <button
                     type="button"
                     class="btn btn-sm btn-primary"
                     data-support-doc-url="${esc(proofResidencyUrl)}"
                     data-support-doc-title="${esc(proofResidencyTitle)}"
                     data-support-doc-name="${esc(proofResidencyName || 'Proof of Residency')}"
                     data-support-doc-inline="0"
                   >View</button>
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
        const reqGrid = renderRequestDetailsGrid(requestFields);
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
          const completedIssuedUrl = issuedDocumentUrl(String(row.request_id || ''), row);
          const completedIssuedFileUrl = issuedDocumentFileUrl(String(row.request_id || ''));
          const completedIssuedTitle = issuedDocumentTitle(row);
          const completedIssuedLabel = normalizePreviewDocKey(row?.document_type || '') === 'barangayid'
            ? 'Digital Barangay ID'
            : 'Issued Document';
          const deliveryNote = requestDeliveryNote(row);
          const hardCopyStatusLabel = requestHardCopyStatusLabel(row);
          const issuedViewerHtml = `
            <div class="tracker-form-grid cols-1">
              <div class="tracker-form-field">
                <p class="tracker-form-label">${esc(completedIssuedLabel)}</p>
                <div class="tracker-form-value d-flex justify-content-between align-items-center gap-2">
                  <span>Open the issued document only when needed.</span>
                  <button type="button" class="btn btn-sm btn-primary" data-view-doc-url="${esc(completedIssuedUrl)}" data-view-doc-title="${esc(completedIssuedTitle)}">View</button>
                </div>
              </div>
            </div>
            ${deliveryNote || hardCopyStatusLabel ? `
              <div class="tracker-form-grid cols-2">
                <div class="tracker-form-field">
                  <p class="tracker-form-label">Soft Copy</p>
                  <div class="tracker-form-value">${esc(row.soft_copy_available ? 'Available Online' : 'Not Yet Available')}</div>
                </div>
                <div class="tracker-form-field">
                  <p class="tracker-form-label">Hard Copy Status</p>
                  <div class="tracker-form-value">${esc(hardCopyStatusLabel || '-')}</div>
                </div>
              </div>
              ${deliveryNote ? `
                <div class="tracker-form-grid cols-1">
                  <div class="tracker-form-field">
                    <p class="tracker-form-label">Claim Note</p>
                    <div class="tracker-form-value">${esc(deliveryNote)}</div>
                  </div>
                </div>
              ` : ''}
            ` : ''}
          `;
          const issuedActionHtml = `<a class="btn btn-sm btn-outline-primary" href="${completedIssuedFileUrl}" target="_blank" rel="noopener">Open in New Tab</a>`;
          html += formSection(completedIssuedLabel, issuedViewerHtml, issuedActionHtml);
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
        const deliveryNoteText = requestDeliveryNote(row);
        const hardCopyStatusLabel = requestHardCopyStatusLabel(row);

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
          },
          ...(deliveryNoteText || hardCopyStatusLabel ? [
            { label: 'Soft Copy', value: row.soft_copy_available ? 'Available Online' : 'Not Yet Available' },
            { label: 'Hard Copy Status', value: hardCopyStatusLabel || '-' },
            { label: 'Claim Note', value: deliveryNoteText || '-' }
          ] : [])
        ], 3);
        if (statusGrid) {
          const modalActionsHtml = viewModalActionButtons(row);
          const stageValue = String(row.stage || '').toLowerCase();
          const isPendingStage = stageValue === 'submitted' || stageValue === 'fee_tagging';
          const statusActions = (modalActionsHtml && !modalActionsHtml.includes('No actions'))
            ? `<div class="tracker-status-actions${isPendingStage ? ' tracker-status-actions--split' : ''}">${modalActionsHtml}</div>`
            : '';
          html += formSection('Request Status', `${statusGrid}${statusActions}`);
        }

        viewDetailsHtml = html || '<div class="text-muted">No details.</div>';
        viewPreviewState = buildPreviewState(row, payload, residentProfile, personalMap);
        applyPendingPreviewStateOverride(String(row.request_id || ''));
        switchViewMode('details');
        const stageKey = resolveWorkflowStage(row);
        const nextEnabled = !(
          stageKey === 'submitted' ||
          stageKey === 'fee_tagging' ||
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
          currentViewStage = resolveWorkflowStage(row);
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
            if (action === 'open_fee_tagging') {
              if (viewModalEl && viewModalEl.classList.contains('show') && viewModal) {
                preserveViewStateOnNextHide = false;
                handoffToFeeTagging(actionId, { openPreviewOnSave: true }, viewModalEl, viewModal);
                return;
              }
              handoffToFeeTagging(actionId, { openPreviewOnSave: true });
              return;
            }
            if (action === 'approve_clearance_with_fees') {
              openActionModal('personnel_approve', actionId);
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
            if (normalizePreviewDocKey(row?.document_type || '') === 'barangayid') {
              openBarangayIdCardModal(row, docUrl, docTitle, 'view', {
                previewState: viewPreviewState
              });
              return;
            }
            const completedDocument = resolveWorkflowStage(row) === 'completed' && docTitle.toLowerCase() === 'issued document';
            openDocumentModal(docUrl, docTitle, 'view', {
              allowPrint: completedDocument,
              completed: completedDocument
            });
          });
        });
        viewDetailsBody.querySelectorAll('button[data-support-doc-url]').forEach((docBtn) => {
          docBtn.addEventListener('click', () => {
            const docUrl = String(docBtn.getAttribute('data-support-doc-url') || '').trim();
            const docTitle = String(docBtn.getAttribute('data-support-doc-title') || 'Submitted Attachment Viewer').trim();
            const docName = String(docBtn.getAttribute('data-support-doc-name') || '').trim();
            const allowInline = String(docBtn.getAttribute('data-support-doc-inline') || '1').trim() !== '0';
            if (!docUrl) return;
            if (allowInline && openInlineSubmittedPreview(docUrl, docName || docTitle)) {
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
      btn.addEventListener('click', async () => {
        const id = String(btn.getAttribute('data-issued-id') || '');
        if (!id) return;
        let row = itemById.get(id);
        row = await ensureRowDetails(row);
        if (!row) return;
        const stageKey = resolveWorkflowStage(row);
        const allowPrint = stageKey === 'for_printing' || stageKey === 'ready_for_claim' || stageKey === 'completed';
        const issuedUrl = issuedDocumentUrl(id, row);
        if (normalizePreviewDocKey(row?.document_type || '') === 'barangayid') {
          openBarangayIdCardModal(row, issuedUrl, issuedDocumentTitle(row), '', {
            allowPrint,
            completed: stageKey === 'completed',
            releaseRequestId: ['for_printing', 'ready_for_claim'].includes(stageKey) ? id : '',
            previewState: buildPreviewState(
              row,
              row?.payload && typeof row.payload === 'object' ? row.payload : {},
              row?.resident_profile && typeof row.resident_profile === 'object' ? row.resident_profile : {},
              null
            )
          });
          return;
        }
        openDocumentModal(issuedUrl, issuedDocumentTitle(row), '', {
          allowPrint,
          completed: stageKey === 'completed',
          releaseRequestId: ['for_printing', 'ready_for_claim'].includes(stageKey) ? id : ''
        });
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
          return;
        }
        if (action === 'mark_completed') {
          openActionModal('mark_completed_confirm', id);
          return;
        }
        if (action === 'mark_ready') {
          openActionModal('mark_ready', id);
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
    const currentRequestId = String(actionRequestId.value || '').trim();
    const currentRow = itemById.get(currentRequestId);
    const currentNeedsFeeTagging = requestNeedsFeeTagging(currentRow);
    if (currentActionValue === 'personnel_approve' && currentDocKey === 'businessclearance' && businessApprovalStep === 'select') {
      const selectedApprovalType = readBusinessApprovalSelection();
      const selectedPlateNumber = String(actionPlate?.value || '').trim().toUpperCase();
      if (!selectedApprovalType) {
        modalError.textContent = 'Please select at least one approval type first.';
        modalError.classList.remove('d-none');
        return;
      }
      if (!selectedPlateNumber) {
        modalError.textContent = 'Please enter the plate number before continuing.';
        modalError.classList.remove('d-none');
        actionPlate?.focus();
        return;
      }
      if (actionForm) {
        actionForm.dataset.businessApprovalType = selectedApprovalType;
        actionForm.dataset.businessPlateNumber = selectedPlateNumber;
      }
      rememberPreviewStateOverride(currentRequestId, {
        businessApprovalType: selectedApprovalType,
        plateNumber: selectedPlateNumber
      });
      if (actionSubmitBtn) {
        actionSubmitBtn.disabled = true;
        actionSubmitBtn.textContent = 'Opening Fee Tagging...';
      }
      if (actionCancelBtn) {
        actionCancelBtn.disabled = true;
      }
      suppressActionReturn = true;
      handoffToFeeTagging(currentRequestId, {
        openPreviewOnSave: true,
        returnState: {
          kind: 'action',
          actionType: 'personnel_approve',
          requestId: currentRequestId,
          businessApprovalType: selectedApprovalType,
          plateNumber: selectedPlateNumber
        }
      }, actionModalEl, actionModal);
      return;
    }

    if ((actionType.value || '') === 'personnel_approve' && String(actionForm?.dataset?.docKey || '') !== 'firsttimejobseeker') {
      const rid = currentRequestId;
      let selectedApprovalType = '';
      let selectedPlateNumber = '';
      let selectedDocumentValidity = '';
      const previewValidityKind = resolveActionValidityKind(currentDocKey, false);
      if (previewValidityKind) {
        selectedDocumentValidity = resolveValidityDateByKind(
          previewValidityKind,
          actionValidity?.value || '',
          firstNonEmpty([
            currentRow?.document_validity,
            ...(previewValidityKind === 'barangay_id' ? [currentRow?.payload?.barangay_id_valid_until] : []),
            viewPreviewState?.documentValidity
          ])
        );
        if (actionValidity) {
          actionValidity.value = selectedDocumentValidity;
        }
      }
      if (String(actionForm?.dataset?.docKey || '') === 'businessclearance') {
        selectedApprovalType = encodeBusinessApprovalTypes(
          actionForm?.dataset?.businessApprovalType || actionBusinessApproval?.value || ''
        );
        selectedPlateNumber = String(
          actionForm?.dataset?.businessPlateNumber || actionPlate?.value || ''
        ).trim().toUpperCase();
        if (!selectedApprovalType) {
          modalError.textContent = 'Please select at least one approval type first.';
          modalError.classList.remove('d-none');
          return;
        }
        if (!selectedPlateNumber) {
          modalError.textContent = 'Please enter the plate number before continuing.';
          modalError.classList.remove('d-none');
          actionPlate?.focus();
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
        rememberPreviewStateOverride(rid, {
          businessApprovalType: selectedApprovalType,
          plateNumber: selectedPlateNumber
        });
      } else {
        if (!viewPreviewState || typeof viewPreviewState !== 'object') {
          const previewRow = itemById.get(rid) || {};
          const previewPayload = previewRow?.payload && typeof previewRow.payload === 'object' ? previewRow.payload : {};
          const previewProfile = previewRow?.resident_profile && typeof previewRow.resident_profile === 'object'
            ? previewRow.resident_profile
            : {};
          viewPreviewState = buildPreviewState(previewRow, previewPayload, previewProfile, null);
        }
      }
      if (selectedDocumentValidity) {
        if (viewPreviewState && typeof viewPreviewState === 'object') {
          viewPreviewState.documentValidity = selectedDocumentValidity;
          if (previewValidityKind === 'barangay_id') {
            applyBarangayIdValidityPreviewState(viewPreviewState, selectedDocumentValidity);
          }
        }
        rememberPreviewStateOverride(rid, {
          ...(pendingPreviewStateOverride?.requestId === rid && pendingPreviewStateOverride?.patch
            ? pendingPreviewStateOverride.patch
            : {}),
          documentValidity: selectedDocumentValidity,
          ...(previewValidityKind === 'barangay_id'
            ? {
                validUntil: formatDateForBarangayIdCard(selectedDocumentValidity),
                validityNotice: `This ID is valid until ${formatDateForBarangayIdCard(selectedDocumentValidity) || '____'} except when the holder requests for a new one.`,
              }
            : {})
        });
      }
      if (currentNeedsFeeTagging) {
        if (actionSubmitBtn) {
          actionSubmitBtn.disabled = true;
          actionSubmitBtn.textContent = 'Opening Fee Tagging...';
        }
        if (actionCancelBtn) {
          actionCancelBtn.disabled = true;
        }
        suppressActionReturn = true;
        handoffToFeeTagging(rid, {
          openPreviewOnSave: true,
          returnState: {
            kind: 'action',
            actionType: 'personnel_approve',
            requestId: rid,
            businessApprovalType: selectedApprovalType,
            plateNumber: selectedPlateNumber,
            documentValidity: selectedDocumentValidity
          }
        }, actionModalEl, actionModal);
        return;
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

    if ((actionType.value || '') === 'interview_pass') {
      const rid = currentRequestId;
      const selectedDocumentValidity = resolveActionValidityKind(currentDocKey, true)
        ? resolveValidityDateByKind(
            'certificate',
            actionValidity?.value || '',
            firstNonEmpty([
              currentRow?.document_validity,
              viewPreviewState?.documentValidity
            ])
          )
        : '';
      if (selectedDocumentValidity) {
        if (actionValidity) {
          actionValidity.value = selectedDocumentValidity;
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
          viewPreviewState.documentValidity = selectedDocumentValidity;
        }
        rememberPreviewStateOverride(rid, {
          documentValidity: selectedDocumentValidity
        });
      }
      if (actionSubmitBtn) {
        actionSubmitBtn.disabled = true;
        actionSubmitBtn.textContent = 'Opening Preview...';
      }
      if (actionCancelBtn) {
        actionCancelBtn.disabled = true;
      }
      suppressActionReturn = true;
      openPreviewAfterActionModal = true;
      actionModal.hide();
      return;
    }

    const currentAction = String(actionType.value || '');
    const apiAction = currentAction === 'personnel_approve_confirm'
      ? 'personnel_approve'
      : (currentAction === 'interview_pass_confirm'
          ? 'interview_pass'
          : (currentAction === 'mark_completed_confirm' ? 'mark_completed' : currentAction));

    const fd = new FormData();
    fd.append('action', apiAction);
    fd.append('request_id', actionRequestId.value || '');
    const financeAmountValue = String(actionAmount?.value || '').trim();

    if (actionReasonWrap && !actionReasonWrap.classList.contains('d-none')) {
      fd.append('reason', actionReason.value || '');
    }
    if (currentAction === 'finance_verify') {
      fd.append('amount', financeAmountValue);
    } else if (actionAmountWrap && !actionAmountWrap.classList.contains('d-none')) {
      fd.append('amount', actionAmount.value || '');
    }
    if (actionOrWrap && !actionOrWrap.classList.contains('d-none')) {
      fd.append('or_number', actionOr.value || '');
    }
    if ((currentAction === 'personnel_approve_confirm' || currentAction === 'interview_pass_confirm')
      && viewPreviewState && typeof viewPreviewState === 'object'
      && actionValidityWrap && !actionValidityWrap.classList.contains('d-none')) {
      const confirmValidityKind = resolveActionValidityKind(currentDocKey, currentAction === 'interview_pass_confirm');
      const selectedConfirmValidity = resolveValidityDateByKind(
        confirmValidityKind || 'certificate',
        actionValidity?.value || '',
        String(viewPreviewState?.documentValidity || '').trim()
      );
      viewPreviewState.documentValidity = selectedConfirmValidity;
      if (confirmValidityKind === 'barangay_id') {
        applyBarangayIdValidityPreviewState(viewPreviewState, selectedConfirmValidity);
      }
    }
    const resolvedPreviewValidity = (currentAction === 'personnel_approve_confirm' || currentAction === 'interview_pass_confirm')
      ? String(viewPreviewState?.documentValidity || '').trim()
      : '';
    if (actionValidityWrap && !actionValidityWrap.classList.contains('d-none')) {
      fd.append('document_validity', actionValidity?.value || '');
    } else if (resolvedPreviewValidity) {
      fd.append('document_validity', resolvedPreviewValidity);
    }
    if (currentAction === 'finance_verify' && actionForm?.dataset?.verifyMode) {
      fd.append('verify_mode', String(actionForm.dataset.verifyMode));
    }
    if (actionIssuedWrap && !actionIssuedWrap.classList.contains('d-none') && actionIssued.files?.[0]) {
      fd.append('issued_file', actionIssued.files[0]);
    }
    if ((currentAction === 'personnel_approve_confirm' || currentAction === 'interview_pass_confirm')
      && viewPreviewState && typeof viewPreviewState === 'object') {
      fd.append('edited_preview', JSON.stringify(viewPreviewState));
    }

    if (apiAction === 'personnel_approve' && requestRequiresFeeTagging(currentRow)) {
      let clearanceFeeSnapshot = Array.isArray(currentRow?.clearance_fees)
        ? currentRow.clearance_fees
        : [];
      if (!clearanceFeeSnapshot.length && currentRequestId) {
        try {
          clearanceFeeSnapshot = await fetchTaggedClearanceFees(currentRequestId);
          updateCachedRequestRecord(currentRequestId, {
            clearance_fees: clearanceFeeSnapshot,
            clearance_fee_count: clearanceFeeSnapshot.length
          });
        } catch (err) {
          console.warn('Failed to refresh tagged clearance fees before approval:', err);
        }
      }
      if (Array.isArray(clearanceFeeSnapshot) && clearanceFeeSnapshot.length) {
        const normalizedSnapshot = clearanceFeeSnapshot
          .map((fee) => ({
            fee_name: String(firstNonEmpty([fee?.fee_name, fee?.fee_type]) || '').trim(),
            amount: Number(fee?.amount) || 0
          }))
          .filter((fee) => fee.fee_name !== '');
        if (normalizedSnapshot.length) {
          fd.append('clearance_fee_snapshot', JSON.stringify(normalizedSnapshot));
        }
      }
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
      await load({ force: true });
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
    if (!suppressActionReturn && actionReturnTarget === 'paymentProof' && actionReturnState) {
      openDocumentModal(
        actionReturnState.docUrl,
        actionReturnState.title,
        actionReturnState.returnTarget,
        actionReturnState.options || {}
      );
    }
    actionReturnTarget = '';
    actionReturnState = null;
    suppressActionReturn = false;
  });

  stageTabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      setActiveStageTab(tab.getAttribute('data-stage-filter') || '');
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

  requestFilterModalEl?.addEventListener('show.bs.modal', () => {
    syncRequestFilterOptions(getRequestFilterSourceItems(cachedAllItems));
  });

  btnApplyFilter?.addEventListener('click', () => {
    requestModalFilters = collectRequestModalFilters();
    const modalInstance = filterModalEl ? bootstrap.Modal.getInstance(filterModalEl) : null;
    modalInstance?.hide();
    load();
  });

  btnResetModalFilters?.addEventListener('click', () => {
    requestModalFilters = {
      dateFrom: '',
      dateTo: '',
      document_type: [],
      area_number: [],
      sector_membership: []
    };
    syncRequestFilterOptions(getRequestFilterSourceItems(cachedAllItems));
    load();
  });

  btnFinanceFilterApply?.addEventListener('click', () => {
    requestModalFilters = collectRequestModalFilters();
    financeFilterMethod = String(financeFilterPaymentMethod?.value || '').toLowerCase();
    const modalEl = document.getElementById('modalFinanceFilter');
    const modalInstance = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;
    modalInstance?.hide();
    load();
  });

  const initialDocumentFilter = isIdIssuanceTrackerView && !isFinancePaymentsPage
    ? 'Barangay ID'
    : canonicalDocumentFilterValue(launchFilterDocument);
  if (initialDocumentFilter) {
    currentDocumentTypeFilter = initialDocumentFilter;
  }

  btnFinanceFilterReset?.addEventListener('click', () => {
    financeFilterMethod = '';
    requestModalFilters = {
      dateFrom: '',
      dateTo: '',
      document_type: [],
      area_number: [],
      sector_membership: []
    };
    if (financeFilterPaymentMethod) financeFilterPaymentMethod.value = '';
    syncRequestFilterOptions(getRequestFilterSourceItems(cachedAllItems));
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
    load({ force: true, showRefreshLoading: true });
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
      const currentRow = itemById.get(rid);
      if (stageKey === 'ready_for_claim') {
        openActionModal('mark_completed_confirm', rid);
        return;
      }
      if (stageKey === 'for_interview' && isFirstTimeJobSeekerRow(currentRow)) {
        openActionModal('interview_pass_confirm', rid);
        return;
      }
      if (stageKey !== 'submitted' && stageKey !== 'fee_tagging') {
        return;
      }
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
    const stageKey = String(row?.stage || '').toLowerCase();
    const isEligible = isFinancePaymentsPage
      ? isFinanceWalkInEligible(row)
      : (stageKey === 'for_payment' || stageKey === 'payment_rejected');
    if (!isEligible) {
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
      viewModalDocBtn.textContent = 'View Document';
      viewModalDocBtn.onclick = null;
    }
    viewDetailsHtml = '';
    viewPreviewState = null;
    currentViewRequestId = '';
    currentViewStage = '';
    openViewDirectPreview = false;
    switchViewMode('details');
  });

  (function initManualIssuancePanel() {
    const manualPanel = document.getElementById('manualIssuancePanel');
    const manualForm = document.getElementById('manualIssuanceForm');
    if (!manualPanel || !manualForm) return;

    const manualResidentModeExisting = document.getElementById('manualResidentModeExisting');
    const manualResidentModeWalkin = document.getElementById('manualResidentModeWalkin');
    const manualResidentModeRenewal = document.getElementById('manualResidentModeRenewal');
    const manualResidentLookupWrap = document.getElementById('manualResidentLookupWrap');
    const manualResidentSearchInput = document.getElementById('manualResidentSearchInput');
    const manualResidentSearchBtn = document.getElementById('manualResidentSearchBtn');
    const manualResidentResultsWrap = document.getElementById('manualResidentResultsWrap');
    const manualResidentResults = document.getElementById('manualResidentResults');
    const manualResidentSearchHint = document.getElementById('manualResidentSearchHint');
    const manualSelectedResidentCard = document.getElementById('manualSelectedResident');
    const manualSelectedResidentName = document.getElementById('manualSelectedResidentName');
    const manualSelectedResidentMeta = document.getElementById('manualSelectedResidentMeta');
    const manualClearSelectedResidentBtn = document.getElementById('manualClearSelectedResidentBtn');
    const manualResidentId = document.getElementById('manualResidentId');
    const manualResidentUserId = document.getElementById('manualResidentUserId');
    const manualDocumentType = document.getElementById('manualDocumentType');
    const manualOtherDocumentWrap = document.getElementById('manualOtherDocumentWrap');
    const manualOtherDocumentTitle = document.getElementById('manualOtherDocumentTitle');
    const manualOtherDocumentFee = document.getElementById('manualOtherDocumentFee');
    const manualOtherDocumentTemplate = document.getElementById('manualOtherDocumentTemplate');
    const manualPurposePresetWrap = document.getElementById('manualPurposePresetWrap');
    const manualPurposePreset = document.getElementById('manualPurposePreset');
    const manualPurpose = document.getElementById('manualPurpose');
    const manualDynamicFields = document.getElementById('manualDynamicFields');
    const manualSpecificFieldsHint = document.getElementById('manualSpecificFieldsHint');
    const manualFeeWrap = document.getElementById('manualFeeWrap');
    const manualFeeList = document.getElementById('manualFeeList');
    const manualFeeTotal = document.getElementById('manualFeeTotal');
    const manualSectorMembershipWrap = document.getElementById('manualSectorMembershipWrap');
    const manualSectorCheckboxes = Array.from(manualPanel.querySelectorAll('[data-manual-sector]'));
    const manualPreviewBtn = document.getElementById('manualPreviewBtn');
    const manualSubmitBtn = document.getElementById('manualSubmitBtn');
    const manualResetBtn = document.getElementById('manualResetBtn');
    const manualFormAlert = document.getElementById('manualFormAlert');
    const manualResidentSummary = document.getElementById('manualResidentSummary');
    const manualDocumentSummary = document.getElementById('manualDocumentSummary');
    const manualNextStageSummary = document.getElementById('manualNextStageSummary');
    const manualValidityWrap = document.getElementById('manualValidityWrap');
    const manualValidityLabel = document.getElementById('manualValidityLabel');
    const manualValidityDate = document.getElementById('manualValidityDate');
    const manualValidityHelp = document.getElementById('manualValidityHelp');
    const manualValiditySummaryWrap = document.getElementById('manualValiditySummaryWrap');
    const manualValiditySummaryLabel = document.getElementById('manualValiditySummaryLabel');
    const manualValiditySummary = document.getElementById('manualValiditySummary');
    const manualValidityProcessMount = document.getElementById('manualValidityProcessMount');
    const manualCertificateValidityMount = document.getElementById('manualCertificateValidityMount');
    const manualBarangayIdPhotoStepMount = document.getElementById('manualBarangayIdPhotoStepMount');
    const manualIdWizardPanels = Array.from(manualForm.querySelectorAll('[data-manual-step-panel], [data-manual-id-step-panel]'));
    const manualIdWizardSteps = Array.from(document.querySelectorAll('.manual-id-process-step'));
    const manualIdWizardBack = document.getElementById('manualIdWizardBack');
    const manualIdWizardNext = document.getElementById('manualIdWizardNext');
    const manualIdWizardPosition = document.getElementById('manualIdWizardPosition');
    const manualIdInlinePreview = document.getElementById('manualIdInlinePreview');
    const manualDocumentInlinePreview = document.getElementById('manualDocumentInlinePreview');
    let manualIdWizardCurrentStep = 1;
    if (isIdIssuanceTrackerView && manualValidityProcessMount && manualValidityWrap) {
      manualValidityWrap.className = 'col-12 d-none';
      manualValidityProcessMount.appendChild(manualValidityWrap);
    } else if (!isIdIssuanceTrackerView && manualCertificateValidityMount && manualValidityWrap) {
      manualValidityWrap.className = 'col-12 d-none';
      manualCertificateValidityMount.appendChild(manualValidityWrap);
    }
    const manualLastName = document.getElementById('manualLastName');
    const manualFirstName = document.getElementById('manualFirstName');
    const manualMiddleName = document.getElementById('manualMiddleName');
    const manualSuffix = document.getElementById('manualSuffix');
    const manualBirthdate = document.getElementById('manualBirthdate');
    const manualBirthdateRequiredMark = document.getElementById('manualBirthdateRequiredMark');
    const manualBirthMonth = document.getElementById('manualBirthMonth');
    const manualBirthDay = document.getElementById('manualBirthDay');
    const manualBirthYear = document.getElementById('manualBirthYear');
    const manualSex = document.getElementById('manualSex');
    const manualCivilStatus = document.getElementById('manualCivilStatus');
    const manualContactNumber = document.getElementById('manualContactNumber');
    const manualBirthplace = document.getElementById('manualBirthplace');
    const manualBirthplaceRequiredMark = document.getElementById('manualBirthplaceRequiredMark');
    const manualOccupation = document.getElementById('manualOccupation');
    const manualReligion = document.getElementById('manualReligion');
    const manualFullAddress = document.getElementById('manualFullAddress');
    const manualAddressLine = document.getElementById('manualAddressLine');
    const manualAreaNumber = document.getElementById('manualAreaNumber');
    const manualBarangay = document.getElementById('manualBarangay');
    const manualCity = document.getElementById('manualCity');
    const manualProvince = document.getElementById('manualProvince');
    const manualAreaNumberModalEl = document.getElementById('manualAreaNumberModal');
    const manualAreaNumberModal = manualAreaNumberModalEl ? getOrCreateModalInstance(manualAreaNumberModalEl) : null;
    const manualAreaOptions = Array.from(document.querySelectorAll('[data-manual-area-option]'));
    const manualBarangayIdPhotoModalEl = document.getElementById('manualBarangayIdPhotoModal');
    const manualBarangayIdPhotoModal = manualBarangayIdPhotoModalEl ? getOrCreateModalInstance(manualBarangayIdPhotoModalEl) : null;
    const manualBarangayIdPhotoStatus = document.getElementById('manualBarangayIdPhotoStatus');
    const manualBarangayIdPhotoFooterCopy = document.getElementById('manualBarangayIdPhotoFooterCopy');
    const manualBarangayIdCameraStage = document.getElementById('manualBarangayIdCameraStage');
    const manualBarangayIdCropStage = document.getElementById('manualBarangayIdCropStage');
    const manualBarangayIdCameraVideo = document.getElementById('manualBarangayIdCameraVideo');
    const manualBarangayIdCameraEmpty = document.getElementById('manualBarangayIdCameraEmpty');
    const manualBarangayIdCameraWorkspace = document.getElementById('manualBarangayIdCameraWorkspace');
    const manualBarangayIdCameraSelect = document.getElementById('manualBarangayIdCameraSelect');
    const manualBarangayIdCropWorkspace = document.getElementById('manualBarangayIdCropWorkspace');
    const manualBarangayIdCropImage = document.getElementById('manualBarangayIdCropImage');
    const manualBarangayIdCropFrame = document.getElementById('manualBarangayIdCropFrame');
    const manualBarangayIdCropEmpty = document.getElementById('manualBarangayIdCropEmpty');
    const manualBarangayIdZoomRange = document.getElementById('manualBarangayIdZoomRange');
    const manualBarangayIdUseLinkedPhotoBtn = document.getElementById('manualBarangayIdUseLinkedPhotoBtn');
    const manualBarangayIdStartCameraBtn = document.getElementById('manualBarangayIdStartCameraBtn');
    const manualBarangayIdRetakePhotoBtn = document.getElementById('manualBarangayIdRetakePhotoBtn');
    const manualBarangayIdCapturePhotoBtn = document.getElementById('manualBarangayIdCapturePhotoBtn');
    const manualBarangayIdSavePhotoBtn = document.getElementById('manualBarangayIdSavePhotoBtn');
    const manualIndigencyGovernmentDirectory = window.MANUAL_INDIGENCY_GOVERNMENT_DIRECTORY || {
      groups: [],
      positions: [],
      officials: [],
    };
    const manualDropdownOtherValue = '__other__';

    const manualDocumentConfigs = [
      { id: 'barangay_id', group: 'ID', label: 'Barangay ID', documentType: 'Barangay ID', kind: 'barangay_id', free: true },
      { id: 'good_moral', group: 'Certificates', label: 'Certificate of Good Moral', documentType: 'Certificate of Good Moral', kind: 'good_moral' },
      { id: 'residency', group: 'Certificates', label: 'Certificate of Residency', documentType: 'Certificate of Residency', kind: 'residency' },
      { id: 'general_certificate_local_employment', group: 'General Certificates', label: 'General Certificate - Local Employment', documentType: 'Certificate of Residency', kind: 'general_certification', purpose: 'Local Employment' },
      { id: 'general_certificate_loan_application', group: 'General Certificates', label: 'General Certificate - Loan Application', documentType: 'Certificate of Residency', kind: 'general_certification', purpose: 'Loan Application' },
      { id: 'general_certificate_bailbond', group: 'General Certificates', label: 'General Certificate - Bailbond', documentType: 'Certificate of Residency', kind: 'general_certification', purpose: 'Bailbond' },
      { id: 'general_certificate_postal_id', group: 'General Certificates', label: 'General Certificate - Postal ID Requirement', documentType: 'Certificate of Residency', kind: 'general_certification', purpose: 'Postal ID Requirement' },
      { id: 'general_certificate_tesda', group: 'General Certificates', label: 'General Certificate - Tesda Requirement', documentType: 'Certificate of Residency', kind: 'general_certification', purpose: 'Tesda Requirement' },
      { id: 'general_certificate_personal_collection', group: 'General Certificates', label: 'General Certificate - Personal Collection', documentType: 'Certificate of Residency', kind: 'general_certification', purpose: 'Personal Collection' },
      { id: 'general_certificate_school', group: 'General Certificates', label: 'General Certificate - School Requirement', documentType: 'Certificate of Residency', kind: 'general_certification', purpose: 'School Requirement' },
      { id: 'general_certificate_bank', group: 'General Certificates', label: 'General Certificate - Bank Requirement (open account)', documentType: 'Certificate of Residency', kind: 'general_certification', purpose: 'Bank Requirement (open account)' },
      { id: 'general_certificate_other', group: 'General Certificates', label: 'General Certificate - Other', documentType: 'Certificate of Residency', kind: 'general_certification', purpose: '__other__' },
      { id: 'identity', group: 'Certificates', label: 'Certificate of Identity', documentType: 'Certificate of Identity', kind: 'identity' },
      { id: 'indigency', group: 'Certificates', label: 'Certificate of Indigency', documentType: 'CertificateOfIndigency', kind: 'indigency', free: true },
      { id: 'cohabitation', group: 'Certificates', label: 'Certificate of Cohabitation', documentType: 'Certificate of Cohabitation', kind: 'cohabitation' },
      { id: 'jail_visit', group: 'Certificates', label: 'Certificate of Relationship for Jail Visitation', documentType: 'Certificate of Cohabitation', kind: 'jail_visit' },
      { id: 'first_time_job_seeker', group: 'Certificates', label: 'First Time Job Seeker Certificate', documentType: 'First Time Job Seeker Certificate', kind: 'first_time_job_seeker' },
      { id: 'electrical_clearance', group: 'Clearances', label: 'Barangay Clearance for Electrical Permit', documentType: 'Barangay Clearance for Electrical Permit', kind: 'general_clearance', clearance: true },
      { id: 'water_clearance', group: 'Clearances', label: 'Barangay Clearance for Water Permit', documentType: 'Barangay Clearance for Water Permit', kind: 'general_clearance', clearance: true },
      { id: 'residential_clearance', group: 'Clearances', label: 'Barangay Clearance for Residential Permit', documentType: 'Barangay Clearance for Residential Permit', kind: 'general_clearance', clearance: true },
      { id: 'residential_building_clearance', group: 'Clearances', label: 'Barangay Clearance for Residential Building Permit', documentType: 'Barangay Clearance for Residential Building Permit', kind: 'general_clearance', clearance: true },
      { id: 'commercial_clearance', group: 'Clearances', label: 'Barangay Clearance for Commercial Permit', documentType: 'Barangay Clearance for Commercial Permit', kind: 'general_clearance', clearance: true },
      { id: 'commercial_building_clearance', group: 'Clearances', label: 'Barangay Clearance for Commercial Building Permit', documentType: 'Barangay Clearance for Commercial Building Permit', kind: 'general_clearance', clearance: true },
      { id: 'business_clearance', group: 'Clearances', label: 'Barangay Clearance for Business Permit', documentType: 'Barangay Clearance for Business Permit', kind: 'business_clearance', clearance: true },
      { id: 'tricycle_clearance', group: 'Clearances', label: 'Barangay Clearance for Tricycle Permit', documentType: 'Barangay Clearance for Tricycle Permit', kind: 'tricycle_clearance', clearance: true }
    ];
    const manualGeneralCertificationPurposes = new Set([
      'Local Employment',
      'Loan Application',
      'Bailbond',
      'Postal ID Requirement',
      'Tesda Requirement',
      'Personal Collection',
      'School Requirement',
      'Bank Requirement (open account)',
      '__other__'
    ]);
    const manualContextFilter = isIdIssuanceTrackerView
      ? '__barangay_id__'
      : canonicalDocumentFilterValue(launchFilterDocument);

    let manualResidentSearchResults = [];
    let manualSelectedResident = null;
    let manualPreviewSignature = '';
    let manualResidentSearchToken = 0;
    let manualBarangayIdPhotoMode = 'none';
    let manualBarangayIdPhotoCustomDataUrl = '';
    let manualBarangayIdPhotoResidentUrl = '';
    let manualBarangayIdPhotoResidentPath = '';
    let manualBarangayIdPhotoStream = null;
    let manualBarangayIdCameraDevices = [];
    let manualBarangayIdSelectedCameraId = '';
    let manualBarangayIdCropSourceUrl = '';
    let manualBarangayIdCropState = {
      x: 0,
      y: 0,
      scale: 1,
      minScale: 1,
      maxScale: 4,
      baseScale: 1,
      imageWidth: 0,
      imageHeight: 0,
      frameLeft: 0,
      frameTop: 0,
      frameSize: 0,
    };
    let manualBarangayIdDragState = {
      active: false,
      startX: 0,
      startY: 0,
      originX: 0,
      originY: 0,
      pointerId: null,
    };

    function manualSetAlert(message = '', tone = 'warning') {
      if (!manualFormAlert) return;
      const tones = ['alert-warning', 'alert-danger', 'alert-success', 'alert-info'];
      manualFormAlert.classList.remove(...tones);
      if (!message) {
        manualFormAlert.classList.add('d-none');
        manualFormAlert.textContent = '';
        return;
      }
      const toneClass = {
        warning: 'alert-warning',
        danger: 'alert-danger',
        success: 'alert-success',
        info: 'alert-info'
      }[tone] || 'alert-warning';
      manualFormAlert.classList.add(toneClass);
      manualFormAlert.textContent = String(message);
      manualFormAlert.classList.remove('d-none');
    }

    function manualSyncStructuredAddress() {
      if (!manualAddressLine || !manualFullAddress) return;
      const parts = [
        manualAddressLine.value,
        manualAreaNumber?.value,
        manualBarangay?.value ? `Barangay ${manualBarangay.value}` : '',
        manualCity?.value,
        manualProvince?.value,
      ].map((part) => String(part || '').trim()).filter(Boolean);
      manualFullAddress.value = parts.join(', ');
    }

    function manualSetAreaNumber(value = '') {
      if (!manualAreaNumber) return;
      manualAreaNumber.value = String(value || '').trim();
      manualAreaOptions.forEach((option) => {
        option.classList.toggle('is-selected', option.dataset.manualAreaOption === manualAreaNumber.value);
      });
      manualSyncStructuredAddress();
    }

    function manualRefreshBirthDays(preferredDay = '') {
      if (!manualBirthDay) return;
      const month = Number(manualBirthMonth?.value || 0);
      const year = Number(manualBirthYear?.value || new Date().getFullYear());
      const daysInMonth = month ? new Date(year, month, 0).getDate() : 31;
      const selectedDay = String(preferredDay || manualBirthDay.value || '').padStart(2, '0');
      manualBirthDay.innerHTML = '<option value="">Day</option>' + Array.from({ length: daysInMonth }, (_, index) => {
        const value = String(index + 1).padStart(2, '0');
        return `<option value="${value}"${value === selectedDay ? ' selected' : ''}>${index + 1}</option>`;
      }).join('');
    }

    function manualSyncBirthdateFromDropdowns() {
      if (!manualBirthdate || !manualBirthMonth || !manualBirthDay || !manualBirthYear) return;
      manualRefreshBirthDays();
      const month = manualBirthMonth.value;
      const day = manualBirthDay.value;
      const year = manualBirthYear.value;
      manualBirthdate.value = month && day && year ? `${year}-${month}-${day}` : '';
      const today = new Date().toISOString().slice(0, 10);
      manualBirthYear.setCustomValidity(manualBirthdate.value && manualBirthdate.value > today ? 'Birthdate cannot be in the future.' : '');
    }

    function manualSyncBirthdateDropdownsFromValue() {
      if (!manualBirthdate || !manualBirthMonth || !manualBirthDay || !manualBirthYear) return;
      const match = String(manualBirthdate.value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
      manualBirthYear.value = match?.[1] || '';
      manualBirthMonth.value = match?.[2] || '';
      manualRefreshBirthDays(match?.[3] || '');
      manualBirthDay.value = match?.[3] || '';
    }

    function manualPanelStep(panel) {
      return Number(panel?.dataset?.manualStepPanel || panel?.dataset?.manualIdStepPanel || 0);
    }

    function manualShowIdWizardStep(step, scroll = false) {
      if (!manualIdWizardPanels.length) return;
      const totalSteps = 5;
      manualIdWizardCurrentStep = Math.max(1, Math.min(totalSteps, Number(step) || 1));
      manualIdWizardPanels.forEach((panel) => {
        const matchesStep = manualPanelStep(panel) === manualIdWizardCurrentStep;
        const optionalClearance = panel.dataset.manualOptionalPanel === 'clearance';
        panel.classList.toggle('d-none', !matchesStep || (optionalClearance && !manualCurrentConfig()?.clearance));
      });
      manualIdWizardSteps.forEach((item, index) => {
        const itemStep = index + 1;
        item.classList.toggle('is-active', itemStep === manualIdWizardCurrentStep);
        item.classList.toggle('is-complete', itemStep < manualIdWizardCurrentStep);
        item.setAttribute('aria-current', itemStep === manualIdWizardCurrentStep ? 'step' : 'false');
      });
      if (manualIdWizardBack) manualIdWizardBack.disabled = manualIdWizardCurrentStep === 1;
      if (manualIdWizardNext) manualIdWizardNext.classList.toggle('d-none', manualIdWizardCurrentStep === totalSteps);
      if (manualSubmitBtn && isIdIssuanceTrackerView) manualSubmitBtn.classList.toggle('d-none', manualIdWizardCurrentStep !== 5);
      if (manualIdWizardPosition) manualIdWizardPosition.textContent = `Step ${manualIdWizardCurrentStep} of ${totalSteps}`;
      manualSetAlert('', 'warning');
      if (isIdIssuanceTrackerView && manualIdWizardCurrentStep === 5) void manualRenderIdInlinePreview();
      if (!isIdIssuanceTrackerView && manualIdWizardCurrentStep === 5) void manualRenderInlineDocumentPreview();
      if (scroll) manualPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    async function manualRenderIdInlinePreview() {
      if (!manualIdInlinePreview) return;
      manualIdInlinePreview.innerHTML = '<div class="manual-id-inline-preview-loading"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Preparing ID preview…</div>';
      try {
        const bundle = manualPreviewStateBundle();
        await fetchBarangayIdTemplateConfig({ force: true }).catch(() => null);
        manualIdInlinePreview.innerHTML = renderDocumentPreview(
          buildPreviewState(bundle.previewRow, bundle.payload, bundle.residentProfile, null)
        );
        manualIdInlinePreview.querySelectorAll('.doc-editable').forEach((editable) => {
          editable.setAttribute('contenteditable', 'false');
          editable.removeAttribute('data-edit-key');
        });
        if (window.BarangayIdDigital && typeof window.BarangayIdDigital.hydrate === 'function') {
          const hydrate = () => window.BarangayIdDigital.hydrate(manualIdInlinePreview);
          hydrate();
          requestAnimationFrame(() => requestAnimationFrame(hydrate));
          window.setTimeout(hydrate, 150);
        }
        manualPreviewSignature = bundle.signature;
        if (manualSubmitBtn) manualSubmitBtn.disabled = false;
      } catch (error) {
        manualPreviewSignature = '';
        if (manualSubmitBtn) manualSubmitBtn.disabled = true;
        manualIdInlinePreview.innerHTML = `<div class="alert alert-danger mb-0">${esc(error?.message || 'Unable to build the Barangay ID preview.')}</div>`;
      }
    }

    function manualNormalizePreviewEditableDate(value) {
      const parsed = parseFlexibleDate(String(value || '').trim());
      return parsed ? formatDateInputValue(parsed) : '';
    }

    function manualApplyPreviewEditableFullName(value) {
      const suffixes = new Set(['JR', 'JR.', 'SR', 'SR.', 'II', 'III', 'IV', 'V']);
      const parts = String(value || '').trim().split(/\s+/).filter(Boolean);
      if (!parts.length) return;
      let suffix = '';
      if (parts.length > 1 && suffixes.has(String(parts[parts.length - 1] || '').toUpperCase())) {
        suffix = parts.pop() || '';
      }
      const first = parts.shift() || '';
      const last = parts.length ? (parts.pop() || '') : '';
      const middle = parts.join(' ');
      if (manualFirstName) manualFirstName.value = first;
      if (manualMiddleName) manualMiddleName.value = middle;
      if (manualLastName) manualLastName.value = last;
      if (manualSuffix) manualSuffix.value = suffix;
    }

    function manualApplyPreviewEditableValue(editKey, value) {
      const target = (fieldName) => manualDynamicFields?.querySelector(`[data-manual-field="${fieldName}"]`);
      switch (editKey) {
        case 'fullName':
          manualApplyPreviewEditableFullName(value);
          return true;
        case 'fullAddress':
        case 'operatorAddress':
          if (manualFullAddress) manualFullAddress.value = value;
          return true;
        case 'birthdate': {
          const normalizedDate = manualNormalizePreviewEditableDate(value);
          if (manualBirthdate && normalizedDate) {
            manualBirthdate.value = normalizedDate;
            return true;
          }
          return false;
        }
        case 'birthplace':
          if (manualBirthplace) manualBirthplace.value = value;
          return true;
        case 'purpose':
          if (manualPurpose) manualPurpose.value = value;
          return true;
        case 'remarks': {
          const remarksField = target('remarks');
          if (remarksField) {
            remarksField.value = value;
            return true;
          }
          return false;
        }
        case 'location': {
          const locationField = target('location') || target('location_of_toda_poda');
          if (locationField) {
            locationField.value = value;
            return true;
          }
          return false;
        }
        case 'businessAddress': {
          const businessAddressField = target('business_full_address');
          if (businessAddressField) {
            businessAddressField.value = value;
            return true;
          }
          return false;
        }
        case 'businessName': {
          const businessNameField = target('business_name');
          if (businessNameField) {
            businessNameField.value = value;
            return true;
          }
          return false;
        }
        default:
          return false;
      }
    }

    function manualBindPreviewEditableFields(container) {
      if (!container) return;
      container.querySelectorAll('.doc-editable').forEach((editable) => {
        const editKey = String(editable.getAttribute('data-edit-key') || '');
        const editableKeys = ['fullName', 'fullAddress', 'birthdate', 'birthplace', 'remarks', 'purpose', 'location', 'businessAddress', 'businessName', 'operatorAddress'];
        if (isIdIssuanceTrackerView || !editableKeys.includes(editKey)) {
          editable.setAttribute('contenteditable', 'false');
          editable.removeAttribute('data-edit-key');
          return;
        }
        editable.setAttribute('title', 'Click to edit this field before approval');
        editable.addEventListener('input', () => {
          const value = String(editable.textContent || '').trim();
          manualApplyPreviewEditableValue(editKey, value);
          try {
            manualPreviewSignature = manualPreviewStateBundle().signature;
            if (manualSubmitBtn) manualSubmitBtn.disabled = false;
            manualUpdateSummary();
          } catch (_) {
            manualPreviewSignature = '';
            if (manualSubmitBtn) manualSubmitBtn.disabled = true;
          }
        });
      });
    }

    async function manualRenderInlineDocumentPreview() {
      if (isIdIssuanceTrackerView || !manualDocumentInlinePreview) return;
      manualDocumentInlinePreview.innerHTML = '<div class="manual-id-inline-preview-loading"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Preparing document preview...</div>';
      try {
        await fetchBarangayIdTemplateConfig({ force: true });
        const bundle = manualPreviewStateBundle();
        if (bundle.config.clearance && !bundle.feeRows.length && !manualHasExemptSector(manualCurrentSectorValues())) {
          manualSetAlert('Tag at least one clearance fee first so the manual request can proceed to finance after submission.', 'warning');
        }
        const previewHtml = renderDocumentPreview(
          buildPreviewState(bundle.previewRow, bundle.payload, bundle.residentProfile, null)
        );
        manualDocumentInlinePreview.innerHTML = `
          <div class="tracker-doc-highlight">
            Preview ready for ${esc(bundle.displayTitle || bundle.config.label)}. Edit any highlighted field below if needed, then approve.
          </div>
          ${previewHtml}
        `;
        if (window.BarangayIdDigital && typeof window.BarangayIdDigital.hydrate === 'function') {
          const hydrate = () => window.BarangayIdDigital.hydrate(manualDocumentInlinePreview);
          hydrate();
          requestAnimationFrame(() => requestAnimationFrame(hydrate));
          window.setTimeout(hydrate, 120);
        }
        manualBindPreviewEditableFields(manualDocumentInlinePreview);
        manualPreviewSignature = bundle.signature;
        if (manualSubmitBtn) manualSubmitBtn.disabled = false;
        manualSetAlert('Preview ready. Edit any highlighted field if needed, then approve the certificate.', 'success');
      } catch (error) {
        manualPreviewSignature = '';
        if (manualSubmitBtn) manualSubmitBtn.disabled = true;
        manualDocumentInlinePreview.innerHTML = `<div class="alert alert-danger mb-0">${esc(error?.message || 'Unable to build the manual document preview.')}</div>`;
      }
    }

    function manualValidateIdWizardStep() {
      if (manualIdWizardCurrentStep === 1 && manualCurrentMode() !== 'walkin' && !manualSelectedResident) {
        manualSetAlert('Select a registered resident before continuing, or choose Walk-in Resident.', 'warning');
        manualResidentSearchInput?.focus();
        return false;
      }
      if (manualIdWizardCurrentStep === 1 && manualCurrentMode() === 'renewal' && !manualSelectedResident?.existing_barangay_id_number) {
        manualSetAlert('This resident has no previously issued Barangay ID number. Choose Registered Resident for a new application.', 'warning');
        return false;
      }
      if (isIdIssuanceTrackerView && manualIdWizardCurrentStep === 3 && !manualCurrentBarangayIdPhoto().previewUrl) {
        manualSetAlert('Take or select the resident ID photo before continuing.', 'warning');
        return false;
      }
      if (manualIdWizardCurrentStep === 2 && manualAreaNumber && !manualAreaNumber.value.trim()) {
        manualSetFieldInvalidState(manualAreaNumber, true);
        manualSetAlert('Select the resident Area Number before continuing.', 'warning');
        manualAreaNumber?.focus();
        return false;
      }
      const currentPanels = manualIdWizardPanels.filter(
        (panel) => manualPanelStep(panel) === manualIdWizardCurrentStep
      );
      const invalidField = currentPanels
        .flatMap((panel) => Array.from(panel.querySelectorAll('input, select, textarea')))
        .find((field) => !field.disabled && !field.checkValidity());
      if (invalidField) {
        manualSetFieldInvalidState(invalidField, true);
        manualSetAlert('Complete the required information in this step before continuing.', 'warning');
        invalidField.focus();
        return false;
      }
      return true;
    }

    function manualCurrentMode() {
      if (manualResidentModeWalkin?.checked) return 'walkin';
      if (manualResidentModeRenewal?.checked) return 'renewal';
      return 'existing';
    }

    function manualSelectedSectorValues() {
      return manualSectorCheckboxes
        .filter((checkbox) => checkbox?.checked)
        .map((checkbox) => normalizeSectorLabel(checkbox?.getAttribute('data-manual-sector') || ''))
        .filter(Boolean);
    }

    function manualSetSelectedSectorValues(values = []) {
      const normalized = new Set(parseSectorValues(Array.isArray(values) ? values.join(',') : values));
      manualSectorCheckboxes.forEach((checkbox) => {
        const label = normalizeSectorLabel(checkbox?.getAttribute('data-manual-sector') || '');
        checkbox.checked = normalized.has(label);
      });
    }

    function manualHasExemptSector(values = manualSelectedSectorValues()) {
      const selected = new Set((Array.isArray(values) ? values : []).map((value) => normalizeSectorLabel(value)));
      return selected.has('PWD') || selected.has('Senior Citizen');
    }

    function manualCurrentSectorValues() {
      if (manualCurrentMode() === 'walkin') {
        return manualSelectedSectorValues();
      }
      return parseSectorValues(manualSelectedResident?.sector_membership || '');
    }

    function manualSyncSectorMembershipUi() {
      const isWalkin = manualCurrentMode() === 'walkin';
      const activeSectors = isWalkin
        ? manualSelectedSectorValues()
        : parseSectorValues(manualSelectedResident?.sector_membership || '');

      if (!isWalkin) {
        manualSetSelectedSectorValues(activeSectors);
      }

      manualSectorCheckboxes.forEach((checkbox) => {
        checkbox.disabled = !isWalkin;
      });
    }

    function manualValidationTargets() {
      const dynamicFields = manualDynamicFields
        ? Array.from(manualDynamicFields.querySelectorAll('[data-manual-field], [data-manual-other-for]'))
        : [];
      return [
        manualDocumentType,
        manualPurposePreset,
        manualOtherDocumentTitle,
        manualOtherDocumentFee,
        manualOtherDocumentTemplate,
        manualPurpose,
        manualLastName,
        manualFirstName,
        manualBirthdate,
        manualSex,
        manualContactNumber,
        manualBirthplace,
        manualFullAddress,
        manualAreaNumber,
        ...dynamicFields
      ].filter(Boolean);
    }

    function manualSetFieldInvalidState(field, invalid) {
      if (!field) return;
      field.classList.toggle('is-invalid', !!invalid);
    }

    function manualClearValidationState() {
      manualValidationTargets().forEach((field) => manualSetFieldInvalidState(field, false));
    }

    function manualValidateRequiredFields() {
      let firstInvalid = null;
      manualValidationTargets().forEach((field) => {
        if (field.disabled || field.type === 'checkbox') return;
        const invalid = !field.checkValidity();
        manualSetFieldInvalidState(field, invalid);
        if (!firstInvalid && invalid && typeof field.focus === 'function') {
          firstInvalid = field;
        }
      });
      manualDynamicFields?.querySelectorAll('[data-manual-checklist-required="1"]').forEach((wrapper) => {
        if (wrapper.classList.contains('d-none')) return;
        const checkboxes = Array.from(wrapper.querySelectorAll('input[type="checkbox"]:not(:disabled)'));
        const invalid = checkboxes.length > 0 && !checkboxes.some((checkbox) => checkbox.checked);
        checkboxes.forEach((checkbox) => manualSetFieldInvalidState(checkbox, invalid));
        wrapper.classList.toggle('is-invalid', invalid);
        if (!firstInvalid && invalid) firstInvalid = checkboxes[0] || wrapper;
      });
      if (firstInvalid) {
        firstInvalid.focus();
        return false;
      }
      return true;
    }

    function manualCurrentRawConfig() {
      const key = String(manualDocumentType?.value || '').trim();
      return manualDocumentConfigs.find((config) => config.id === key) || null;
    }

    function manualUsesPurposePreset(config = manualCurrentConfig()) {
      return !!config && config.kind === 'general_certification' && !config.purpose;
    }

    function manualSyncPurposePreset() {
      const config = manualCurrentConfig();
      const usesPreset = manualUsesPurposePreset(config);
      const fixedPurpose = config?.kind === 'general_certification' ? String(config.purpose || '').trim() : '';
      const presetValue = String(manualPurposePreset?.value || '').trim();

      manualPurposePresetWrap?.classList.toggle('d-none', !usesPreset);
      if (manualPurposePreset) {
        manualPurposePreset.required = usesPreset;
        if (!usesPreset) {
          manualPurposePreset.value = '';
        }
      }
      if (!manualPurpose) return;
      if (fixedPurpose) {
        const isOther = fixedPurpose === '__other__';
        manualPurpose.classList.toggle('d-none', !isOther);
        manualPurpose.required = true;
        manualPurpose.placeholder = 'State the exact purpose shown on the issued document';
        if (!isOther) {
          manualPurpose.value = fixedPurpose;
          manualPurpose.dataset.auto = '1';
        } else if (manualGeneralCertificationPurposes.has(String(manualPurpose.value || '').trim())) {
          manualPurpose.value = '';
        }
        return;
      }
      if (!usesPreset) {
        manualPurpose.classList.remove('d-none');
        manualPurpose.required = true;
        manualPurpose.placeholder = 'State the exact purpose shown on the issued document';
        return;
      }

      const isOther = presetValue === '__other__';
      manualPurpose.classList.toggle('d-none', !isOther);
      manualPurpose.placeholder = 'State the exact purpose shown on the issued document';
      manualPurpose.required = isOther || usesPreset;

      if (!isOther) {
        manualPurpose.value = presetValue;
      } else if (manualGeneralCertificationPurposes.has(String(manualPurpose.value || '').trim())) {
        manualPurpose.value = '';
      }
    }

    function manualIsOtherDocumentSelection(config = manualCurrentRawConfig()) {
      return !!config && config.kind === 'other_document';
    }

    function manualOtherDocumentTemplateConfigs() {
      return manualDocumentConfigs.filter((config) => (
        config.group === 'Certificates'
        && config.kind !== 'barangay_id'
        && config.kind !== 'other_document'
        && !config.clearance
      ));
    }

    function manualSelectedOtherTemplateConfig() {
      const key = String(manualOtherDocumentTemplate?.value || '').trim();
      return manualOtherDocumentTemplateConfigs().find((config) => config.id === key) || null;
    }

    function manualCurrentConfig() {
      return manualCurrentRawConfig();
    }

    function manualResolvedDocumentLabel(rawConfig = manualCurrentRawConfig(), effectiveConfig = manualCurrentConfig()) {
      return rawConfig?.label || effectiveConfig?.label || 'Select a manual issuance form';
    }

    function manualResolvedDocumentFee() {
      const rawConfig = manualCurrentRawConfig();
      if (!manualIsOtherDocumentSelection(rawConfig)) {
        return null;
      }
      const parsed = Number.parseFloat(String(manualOtherDocumentFee?.value || '').trim());
      return Number.isFinite(parsed) && parsed >= 0 ? parsed : null;
    }

    function manualRenderOtherDocumentTemplateOptions() {
      if (!manualOtherDocumentTemplate) return;
      const options = manualOtherDocumentTemplateConfigs();
      const previousValue = String(manualOtherDocumentTemplate.value || '').trim();
      let html = '<option value="">Select a certificate template</option>';
      html += options.map((config) => `<option value="${manualEscapeAttr(config.id)}">${esc(config.label)}</option>`).join('');
      manualOtherDocumentTemplate.innerHTML = html;
      if (previousValue && options.some((config) => config.id === previousValue)) {
        manualOtherDocumentTemplate.value = previousValue;
      }
    }

    function manualSyncOtherDocumentSetup() {
      const rawConfig = manualCurrentRawConfig();
      const showOther = manualIsOtherDocumentSelection(rawConfig);
      manualOtherDocumentWrap?.classList.toggle('d-none', !showOther);
      if (manualOtherDocumentTitle) manualOtherDocumentTitle.required = showOther;
      if (manualOtherDocumentFee) manualOtherDocumentFee.required = showOther;
      if (manualOtherDocumentTemplate) manualOtherDocumentTemplate.required = showOther;
      if (showOther) {
        manualRenderOtherDocumentTemplateOptions();
      }
    }

    function manualConfigMatchesContext(config, contextFilter = manualContextFilter) {
      const activeFilter = String(contextFilter || '').trim().toLowerCase();
      if (!activeFilter) {
        return true;
      }
      if (config.id === 'other_document') {
        if (
          activeFilter === '__barangay_id__'
          || activeFilter === '__clearances__'
          || activeFilter === '__business__'
          || activeFilter.startsWith('__clr_')
        ) {
          return false;
        }
        return true;
      }
      if (activeFilter === '__barangay_id__') {
        return config.kind === 'barangay_id';
      }
      if (activeFilter === '__certificates__') {
        return !config.clearance && config.kind !== 'barangay_id';
      }
      if (activeFilter === '__clearances__') {
        return !!config.clearance;
      }
      if (activeFilter === '__business__' || activeFilter === '__clr_business_permit__') {
        return config.id === 'business_clearance';
      }
      if (activeFilter === '__clr_tricycle_permit__') {
        return config.id === 'tricycle_clearance';
      }
      if (activeFilter === '__clr_electric_permit__') {
        return config.id === 'electrical_clearance';
      }
      if (activeFilter === '__clr_water_permit__') {
        return config.id === 'water_clearance';
      }
      if (activeFilter === '__clr_residential_permit__') {
        return config.id === 'residential_clearance';
      }
      if (activeFilter === '__clr_commercial_permit__') {
        return config.id === 'commercial_clearance';
      }
      if (activeFilter === '__cert_cohabitation__') {
        return config.id === 'cohabitation';
      }
      if (activeFilter === '__cert_good_moral__') {
        return config.id === 'good_moral';
      }
      if (activeFilter === '__cert_jail_visit__') {
        return config.id === 'jail_visit';
      }
      if (activeFilter === '__cert_first_time_job_seeker__') {
        return config.id === 'first_time_job_seeker';
      }
      if (activeFilter === '__cert_residency__') {
        return config.id === 'residency';
      }
      if (activeFilter === '__cert_indigency__') {
        return config.id === 'indigency';
      }
      return true;
    }

    function manualAvailableDocumentConfigs() {
      return manualDocumentConfigs.filter((config) => manualConfigMatchesContext(config));
    }

    function manualPreferredDocumentId(configs = manualAvailableDocumentConfigs()) {
      const preferredLaunchId = String(launchManualDocument || '').trim().toLowerCase();
      if (preferredLaunchId && configs.some((config) => config.id === preferredLaunchId)) {
        return preferredLaunchId;
      }
      if (configs.length === 1) {
        return configs[0].id;
      }
      if (manualContextFilter === '__barangay_id__' && configs.some((config) => config.id === 'barangay_id')) {
        return 'barangay_id';
      }
      return '';
    }

    function manualApplyContextDocumentSelection() {
      if (!manualDocumentType) return;
      const preferredId = manualPreferredDocumentId();
      const hasPreferredOption = preferredId && Array.from(manualDocumentType.options).some(
        (option) => String(option.value || '').trim().toLowerCase() === preferredId
      );
      manualDocumentType.value = hasPreferredOption ? preferredId : '';
      manualRenderDynamicFields();
    }

    function manualEscapeAttr(value) {
      return String(value ?? '').replace(/"/g, '&quot;');
    }

    function manualIndigencyField(name) {
      return manualDynamicFields?.querySelector(`[data-manual-field="${name}"]`) || null;
    }

    function manualSetFieldRequirement(field, required = false, enabled = true, clear = false) {
      if (!field) return;
      field.disabled = !enabled;
      field.required = !!required && !!enabled;
      if (clear) field.value = '';
    }

    function manualIndigencyOfficialById(officialId) {
      const id = String(officialId || '').trim();
      return (Array.isArray(manualIndigencyGovernmentDirectory.officials)
        ? manualIndigencyGovernmentDirectory.officials
        : []
      ).find((item) => String(item?.id || '').trim() === id) || null;
    }

    function manualSyncIndigencyRecipientFields() {
      const config = manualCurrentConfig();
      if (config?.kind !== 'indigency' || !manualDynamicFields) return;

      const targetField = manualIndigencyField('submission_target_type');
      const groupField = manualIndigencyField('government_position_group');
      const groupOtherField = manualIndigencyField('government_position_other');
      const positionField = manualIndigencyField('government_position_detail');
      const officialField = manualIndigencyField('government_official_id');
      const officialOtherField = manualIndigencyField('government_official_other');
      const institutionNameField = manualIndigencyField('institution_name');
      const institutionPersonField = manualIndigencyField('institution_person');
      const institutionPositionField = manualIndigencyField('institution_position');
      const requestOfficerLine1Field = manualIndigencyField('request_officer_line1');
      const requestOfficerLine2Field = manualIndigencyField('request_officer_line2');
      const requestOfficerLine3Field = manualIndigencyField('request_officer_line3');
      const requestOfficerField = manualIndigencyField('request_officer');
      const governmentOfficeField = manualIndigencyField('government_office');
      const governmentPositionField = manualIndigencyField('government_position');
      const governmentOfficialField = manualIndigencyField('government_official');
      const governmentWrap = manualDynamicFields.querySelector('[data-manual-indigency-government]');
      const institutionWrap = manualDynamicFields.querySelector('[data-manual-indigency-institution]');
      const emptyState = manualDynamicFields.querySelector('[data-manual-indigency-empty-state]');

      const targetValue = String(targetField?.value || '').trim();
      const isGovernment = targetValue === 'government_official';
      const isInstitution = targetValue === 'institution';
      const groupValue = String(groupField?.value || '').trim();
      const officialValue = String(officialField?.value || '').trim();
      const isOtherGroup = groupValue === manualDropdownOtherValue;
      const isOtherOfficial = officialValue === manualDropdownOtherValue || isOtherGroup;

      governmentWrap?.classList.toggle('d-none', !isGovernment);
      institutionWrap?.classList.toggle('d-none', !isInstitution);

      manualSetFieldRequirement(groupField, isGovernment, isGovernment, !isGovernment);
      manualSetFieldRequirement(groupOtherField, isGovernment && isOtherGroup, isGovernment && isOtherGroup, !isGovernment || !isOtherGroup);
      groupOtherField?.classList.toggle('d-none', !(isGovernment && isOtherGroup));

      manualSetFieldRequirement(positionField, isGovernment, isGovernment, !isGovernment);

      manualSetFieldRequirement(officialField, isGovernment && !isOtherGroup, isGovernment && groupValue !== '', !isGovernment || groupValue === '');
      manualSetFieldRequirement(officialOtherField, isGovernment && isOtherOfficial, isGovernment && isOtherOfficial, !isGovernment || !isOtherOfficial);
      officialOtherField?.classList.toggle('d-none', !(isGovernment && isOtherOfficial));

      manualSetFieldRequirement(institutionPersonField, isInstitution, isInstitution, !isInstitution);
      manualSetFieldRequirement(institutionPositionField, isInstitution, isInstitution, !isInstitution);
      manualSetFieldRequirement(institutionNameField, isInstitution, isInstitution, !isInstitution);

      if (officialField) {
        const selectedCategory = isOtherGroup ? '' : groupValue;
        Array.from(officialField.options).forEach((option, index) => {
          if (index === 0 || String(option.value || '').trim() === manualDropdownOtherValue) {
            option.hidden = false;
            return;
          }
          const optionCategory = String(option.getAttribute('data-category') || '').trim();
          option.hidden = !!selectedCategory && optionCategory !== selectedCategory;
        });
        const activeOption = officialField.selectedOptions?.[0] || null;
        if (activeOption?.hidden) {
          officialField.value = '';
        }
      }
      if (emptyState) {
        emptyState.textContent = groupValue
          ? (isOtherGroup ? 'Enter the recipient name manually.' : 'Choose the recipient name from the filtered dropdown.')
          : 'Choose a recipient address first.';
      }

      const official = manualIndigencyOfficialById(officialValue);
      if (isGovernment && positionField && official && !isOtherOfficial && !String(positionField.value || '').trim()) {
        positionField.value = String(official.position_name || '').trim();
      }

      let line1 = '';
      let line2 = '';
      let line3 = '';

      if (isGovernment) {
        line1 = isOtherOfficial
          ? String(officialOtherField?.value || '').trim()
          : String(official?.name || '').trim();
        line2 = String(positionField?.value || official?.position_name || '').trim();
        line3 = isOtherGroup
          ? String(groupOtherField?.value || '').trim()
          : String(official?.jurisdiction_location || '').trim();
      } else if (isInstitution) {
        line1 = String(institutionPersonField?.value || '').trim();
        line2 = String(institutionPositionField?.value || '').trim();
        line3 = String(institutionNameField?.value || '').trim();
      }

      if (requestOfficerLine1Field) requestOfficerLine1Field.value = line1;
      if (requestOfficerLine2Field) requestOfficerLine2Field.value = line2;
      if (requestOfficerLine3Field) requestOfficerLine3Field.value = line3;
      if (requestOfficerField) requestOfficerField.value = [line1, line2, line3].filter(Boolean).join(' - ');
      if (governmentOfficeField) governmentOfficeField.value = line3;
      if (governmentPositionField) governmentPositionField.value = line2;
      if (governmentOfficialField) governmentOfficialField.value = line1;
    }

    function manualBarangayIdPhotoButton(label, action, tone = 'outline-primary', icon = 'fa-camera') {
      return `
        <button type="button" class="btn btn-${tone}" data-manual-photo-action="${manualEscapeAttr(action)}">
          <i class="fas ${manualEscapeAttr(icon)} me-1"></i>${esc(label)}
        </button>
      `;
    }

    function manualResolveBarangayIdPhotoPreviewUrl(value) {
      const raw = String(value || '').trim();
      if (!raw) return '';
      if (/^(data|blob):/i.test(raw)) return raw;
      return resolvePublicUrl(raw);
    }

    function manualCurrentBarangayIdPhoto() {
      if (manualBarangayIdPhotoMode === 'custom' && manualBarangayIdPhotoCustomDataUrl) {
        return {
          mode: 'custom',
          previewUrl: manualBarangayIdPhotoCustomDataUrl,
          dataUrl: manualBarangayIdPhotoCustomDataUrl,
          path: '',
          label: 'Captured and cropped photo ready',
        };
      }
      if (manualBarangayIdPhotoMode === 'resident' && (manualBarangayIdPhotoResidentUrl || manualBarangayIdPhotoResidentPath)) {
        return {
          mode: 'resident',
          previewUrl: manualResolveBarangayIdPhotoPreviewUrl(manualBarangayIdPhotoResidentUrl || manualBarangayIdPhotoResidentPath),
          dataUrl: '',
          path: manualBarangayIdPhotoResidentPath,
          label: 'Using linked resident photo',
        };
      }
      return {
        mode: 'none',
        previewUrl: '',
        dataUrl: '',
        path: '',
        label: 'No photo selected yet',
      };
    }

    function manualSetBarangayIdPhotoStatus(message = '', tone = 'info') {
      if (!manualBarangayIdPhotoStatus) return;
      const toneClasses = ['alert-info', 'alert-warning', 'alert-danger', 'alert-success'];
      manualBarangayIdPhotoStatus.classList.remove(...toneClasses);
      if (!message) {
        manualBarangayIdPhotoStatus.classList.add('d-none');
        manualBarangayIdPhotoStatus.textContent = '';
        return;
      }
      const className = {
        info: 'alert-info',
        warning: 'alert-warning',
        danger: 'alert-danger',
        success: 'alert-success',
      }[tone] || 'alert-info';
      manualBarangayIdPhotoStatus.classList.add(className);
      manualBarangayIdPhotoStatus.textContent = String(message);
      manualBarangayIdPhotoStatus.classList.remove('d-none');
    }

    function manualCurrentBarangayIdCameraDeviceId() {
      const track = manualBarangayIdPhotoStream && typeof manualBarangayIdPhotoStream.getVideoTracks === 'function'
        ? manualBarangayIdPhotoStream.getVideoTracks()[0]
        : null;
      const settings = track && typeof track.getSettings === 'function'
        ? track.getSettings()
        : null;
      return String(settings?.deviceId || '').trim();
    }

    function manualRenderBarangayIdCameraOptions(devices = [], selectedDeviceId = '') {
      if (!manualBarangayIdCameraSelect) return;
      const list = Array.isArray(devices) ? devices : [];
      manualBarangayIdCameraSelect.innerHTML = '';

      if (!list.length) {
        manualBarangayIdCameraSelect.innerHTML = '<option value="">No camera detected</option>';
        manualBarangayIdCameraSelect.disabled = true;
        manualBarangayIdSelectedCameraId = '';
        return;
      }

      const selectableDeviceIds = [];
      list.forEach((device, index) => {
        const option = document.createElement('option');
        const deviceId = String(device?.deviceId || '').trim();
        option.value = deviceId;
        option.textContent = String(device?.label || `Camera ${index + 1}`);
        if (!deviceId) {
          option.disabled = true;
        } else {
          selectableDeviceIds.push(deviceId);
        }
        manualBarangayIdCameraSelect.appendChild(option);
      });

      const preferredDeviceId = String(
        selectedDeviceId
        || manualCurrentBarangayIdCameraDeviceId()
        || manualBarangayIdSelectedCameraId
        || ''
      ).trim();
      const fallbackDeviceId = selectableDeviceIds[0] || '';

      if (preferredDeviceId && selectableDeviceIds.includes(preferredDeviceId)) {
        manualBarangayIdCameraSelect.value = preferredDeviceId;
        manualBarangayIdSelectedCameraId = preferredDeviceId;
      } else {
        manualBarangayIdCameraSelect.value = fallbackDeviceId;
        manualBarangayIdSelectedCameraId = fallbackDeviceId;
      }

      manualBarangayIdCameraSelect.disabled = selectableDeviceIds.length < 2;
    }

    async function manualRefreshBarangayIdCameraOptions(selectedDeviceId = '') {
      if (!manualBarangayIdCameraSelect) return;

      if (!navigator.mediaDevices?.enumerateDevices) {
        manualBarangayIdCameraDevices = [];
        manualBarangayIdCameraSelect.innerHTML = '<option value="">Camera list unavailable on this browser</option>';
        manualBarangayIdCameraSelect.disabled = true;
        return;
      }

      try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        manualBarangayIdCameraDevices = devices.filter((device) => device.kind === 'videoinput');
      } catch (_) {
        manualBarangayIdCameraDevices = [];
      }

      manualRenderBarangayIdCameraOptions(manualBarangayIdCameraDevices, selectedDeviceId);
    }

    function manualDescribeBarangayIdCameraError(error) {
      const name = String(error?.name || '').trim();
      if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
        return 'Camera access was denied. Allow the camera in your browser, then try again.';
      }
      if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
        return 'No camera was found on this device. Connect one, then try again.';
      }
      if (name === 'NotReadableError' || name === 'TrackStartError') {
        return 'The selected camera is busy or unavailable. Close other apps using it, then try again.';
      }
      if (name === 'OverconstrainedError') {
        return 'The selected camera could not be started. Choose another camera from the dropdown, then try again.';
      }
      if (name === 'SecurityError') {
        return 'Camera access is blocked by the current browser or page security settings.';
      }
      return error?.message || 'Camera access was blocked. Allow camera access, then try again.';
    }

    function manualStopBarangayIdCamera() {
      if (manualBarangayIdPhotoStream) {
        manualBarangayIdPhotoStream.getTracks().forEach((track) => track.stop());
      }
      manualBarangayIdPhotoStream = null;
      if (manualBarangayIdCameraVideo) {
        manualBarangayIdCameraVideo.srcObject = null;
      }
      if (manualBarangayIdCapturePhotoBtn) {
        manualBarangayIdCapturePhotoBtn.disabled = true;
      }
    }

    function manualBarangayIdHasResidentPhoto() {
      return !!(manualBarangayIdPhotoResidentUrl || manualBarangayIdPhotoResidentPath);
    }

    function manualSyncBarangayIdPhotoResidentSource() {
      manualBarangayIdPhotoResidentUrl = String(manualSelectedResident?.id_picture_url || '').trim();
      manualBarangayIdPhotoResidentPath = String(manualSelectedResident?.id_picture_path || '').trim();
      if (manualBarangayIdPhotoMode === 'resident' && !manualBarangayIdHasResidentPhoto()) {
        manualBarangayIdPhotoMode = 'none';
      }
      if (manualBarangayIdPhotoMode !== 'custom' && manualBarangayIdPhotoMode !== 'resident' && !manualBarangayIdHasResidentPhoto()) {
        manualBarangayIdPhotoMode = 'none';
      }
      manualUpdateBarangayIdPhotoField();
    }

    function manualResetBarangayIdPhotoState() {
      manualStopBarangayIdCamera();
      manualBarangayIdPhotoMode = 'none';
      manualBarangayIdPhotoCustomDataUrl = '';
      manualBarangayIdPhotoResidentUrl = '';
      manualBarangayIdPhotoResidentPath = '';
      manualBarangayIdCropSourceUrl = '';
      manualBarangayIdCropState = {
        x: 0,
        y: 0,
        scale: 1,
        minScale: 1,
        maxScale: 4,
        baseScale: 1,
        imageWidth: 0,
        imageHeight: 0,
        frameLeft: 0,
        frameTop: 0,
        frameSize: 0,
      };
      manualBarangayIdDragState = {
        active: false,
        startX: 0,
        startY: 0,
        originX: 0,
        originY: 0,
        pointerId: null,
      };
      if (manualBarangayIdZoomRange) {
        manualBarangayIdZoomRange.value = '100';
        manualBarangayIdZoomRange.disabled = true;
      }
      if (manualBarangayIdCropImage) {
        manualBarangayIdCropImage.removeAttribute('src');
        manualBarangayIdCropImage.style.transform = '';
      }
      if (manualBarangayIdSavePhotoBtn) {
        manualBarangayIdSavePhotoBtn.disabled = true;
      }
      manualBarangayIdCropWorkspace?.classList.remove('is-dragging');
      manualSetBarangayIdPhotoStatus('', 'info');
      manualUpdateBarangayIdPhotoField();
    }

    function manualUpdateBarangayIdPhotoField() {
      const photoField = manualForm?.querySelector('[data-manual-photo-field="barangay_id"]');
      if (!photoField) return;
      const preview = manualCurrentBarangayIdPhoto();
      const previewBox = photoField.querySelector('[data-manual-photo-preview]');
      const chip = photoField.querySelector('[data-manual-photo-chip]');
      const actions = photoField.querySelector('[data-manual-photo-actions]');
      const note = photoField.querySelector('[data-manual-photo-note]');

      if (previewBox) {
        if (preview.previewUrl) {
          previewBox.innerHTML = `<img src="${manualEscapeAttr(preview.previewUrl)}" alt="Barangay ID photo preview">`;
        } else {
          previewBox.innerHTML = '<div class="manual-photo-preview-placeholder">No Barangay ID photo saved yet. Take a photo, then crop and save it here.</div>';
        }
      }
      if (chip) {
        chip.innerHTML = preview.mode === 'custom'
          ? '<i class="fas fa-circle-check"></i>Captured photo ready'
          : (preview.mode === 'resident'
              ? '<i class="fas fa-id-badge"></i>Linked resident photo ready'
              : '<i class="fas fa-camera"></i>Photo required for Barangay ID');
      }
      if (note) {
        note.textContent = preview.mode === 'custom'
          ? 'This captured photo will be used for the Barangay ID preview and saved with the request.'
          : (preview.mode === 'resident'
              ? 'The linked resident photo is currently active. Taking a new photo will replace it for this request.'
              : 'Capture the resident through the webcam, then crop and save a square photo for the Barangay ID.');
      }
      if (actions) {
        const buttons = [
          manualBarangayIdPhotoButton(
            preview.mode === 'custom' ? 'Retake Photo' : 'Open Camera',
            'take',
            preview.previewUrl ? 'outline-primary' : 'primary',
            'fa-camera'
          ),
        ];
        if (manualBarangayIdHasResidentPhoto() && manualBarangayIdPhotoMode !== 'resident') {
          buttons.push(manualBarangayIdPhotoButton('Use Linked Photo', 'linked', 'outline-secondary', 'fa-id-card'));
        }
        if (preview.previewUrl) {
          buttons.push(manualBarangayIdPhotoButton('Remove Photo', 'remove', 'outline-danger', 'fa-trash'));
        }
        actions.innerHTML = buttons.join('');
      }
    }

    function manualSwitchBarangayIdPhotoStage(stage) {
      const isCrop = stage === 'crop';
      manualBarangayIdCameraStage?.classList.toggle('d-none', isCrop);
      manualBarangayIdCropStage?.classList.toggle('d-none', !isCrop);
      if (manualBarangayIdStartCameraBtn) {
        manualBarangayIdStartCameraBtn.classList.toggle('d-none', isCrop);
      }
      if (manualBarangayIdUseLinkedPhotoBtn) {
        manualBarangayIdUseLinkedPhotoBtn.classList.toggle('d-none', isCrop || !manualBarangayIdHasResidentPhoto());
      }
      if (manualBarangayIdCapturePhotoBtn) {
        manualBarangayIdCapturePhotoBtn.classList.toggle('d-none', isCrop);
      }
      if (manualBarangayIdRetakePhotoBtn) {
        manualBarangayIdRetakePhotoBtn.classList.toggle('d-none', !isCrop);
      }
      if (manualBarangayIdSavePhotoBtn) {
        manualBarangayIdSavePhotoBtn.classList.toggle('d-none', !isCrop);
      }
      if (manualBarangayIdPhotoFooterCopy) {
        manualBarangayIdPhotoFooterCopy.textContent = isCrop
          ? 'Drag the image behind the square guide and use the zoom slider until the face is framed well, then save the crop.'
          : 'Allow camera access when prompted. If more than one webcam is connected, choose the camera you want from the dropdown before capturing.';
      }
    }

    async function manualStartBarangayIdCamera(cameraDeviceId = '') {
      if (!navigator.mediaDevices?.getUserMedia) {
        manualSetBarangayIdPhotoStatus('This browser does not support camera access for manual Barangay ID capture.', 'danger');
        return;
      }
      if (!window.isSecureContext) {
        manualSetBarangayIdPhotoStatus('Camera access requires HTTPS or localhost in this browser.', 'danger');
        return;
      }

      const requestedDeviceId = String(cameraDeviceId || manualBarangayIdSelectedCameraId || '').trim();
      manualStopBarangayIdCamera();
      manualSwitchBarangayIdPhotoStage('camera');

      try {
        const videoConstraints = requestedDeviceId
          ? {
              deviceId: { exact: requestedDeviceId },
              width: { ideal: 1280 },
              height: { ideal: 720 },
            }
          : {
              facingMode: 'user',
              width: { ideal: 1280 },
              height: { ideal: 720 },
            };
        const stream = await navigator.mediaDevices.getUserMedia({
          video: videoConstraints,
          audio: false,
        });
        manualBarangayIdPhotoStream = stream;
        if (manualBarangayIdCameraVideo) {
          manualBarangayIdCameraVideo.srcObject = stream;
          await manualBarangayIdCameraVideo.play();
        }
        manualBarangayIdSelectedCameraId = manualCurrentBarangayIdCameraDeviceId() || requestedDeviceId;
        await manualRefreshBarangayIdCameraOptions(manualBarangayIdSelectedCameraId);
        manualBarangayIdCameraEmpty?.classList.add('d-none');
        if (manualBarangayIdCapturePhotoBtn) {
          manualBarangayIdCapturePhotoBtn.disabled = false;
        }
        manualSetBarangayIdPhotoStatus('Camera ready. Position the resident inside the square guide, then capture the photo.', 'info');
      } catch (error) {
        manualStopBarangayIdCamera();
        await manualRefreshBarangayIdCameraOptions(manualBarangayIdSelectedCameraId);
        manualBarangayIdCameraEmpty?.classList.remove('d-none');
        manualSetBarangayIdPhotoStatus(manualDescribeBarangayIdCameraError(error), 'danger');
      }
    }

    function manualComputeBarangayIdCropFrame() {
      if (!manualBarangayIdCropWorkspace || !manualBarangayIdCropFrame) return null;
      const workspaceRect = manualBarangayIdCropWorkspace.getBoundingClientRect();
      const frameRect = manualBarangayIdCropFrame.getBoundingClientRect();
      const frame = {
        left: frameRect.left - workspaceRect.left,
        top: frameRect.top - workspaceRect.top,
        size: frameRect.width,
      };
      manualBarangayIdCropState.frameLeft = frame.left;
      manualBarangayIdCropState.frameTop = frame.top;
      manualBarangayIdCropState.frameSize = frame.size;
      return frame;
    }

    function manualClampBarangayIdCropPosition() {
      const frame = manualComputeBarangayIdCropFrame();
      if (!frame) return;
      const displayWidth = manualBarangayIdCropState.imageWidth * manualBarangayIdCropState.scale;
      const displayHeight = manualBarangayIdCropState.imageHeight * manualBarangayIdCropState.scale;
      const minX = frame.left + frame.size - displayWidth;
      const maxX = frame.left;
      const minY = frame.top + frame.size - displayHeight;
      const maxY = frame.top;
      manualBarangayIdCropState.x = Math.min(maxX, Math.max(minX, manualBarangayIdCropState.x));
      manualBarangayIdCropState.y = Math.min(maxY, Math.max(minY, manualBarangayIdCropState.y));
    }

    function manualApplyBarangayIdCropTransform() {
      if (!manualBarangayIdCropImage) return;
      manualClampBarangayIdCropPosition();
      manualBarangayIdCropImage.style.transform = `translate(${manualBarangayIdCropState.x}px, ${manualBarangayIdCropState.y}px) scale(${manualBarangayIdCropState.scale})`;
    }

    function manualSeedBarangayIdCropState() {
      if (!manualBarangayIdCropWorkspace || !manualBarangayIdCropImage || !manualBarangayIdCropImage.naturalWidth || !manualBarangayIdCropImage.naturalHeight) {
        return;
      }
      const workspaceRect = manualBarangayIdCropWorkspace.getBoundingClientRect();
      const frame = manualComputeBarangayIdCropFrame();
      if (!frame) return;
      const imageWidth = manualBarangayIdCropImage.naturalWidth;
      const imageHeight = manualBarangayIdCropImage.naturalHeight;
      const baseScale = Math.max(frame.size / imageWidth, frame.size / imageHeight);
      manualBarangayIdCropState = {
        ...manualBarangayIdCropState,
        x: (workspaceRect.width - imageWidth * baseScale) / 2,
        y: (workspaceRect.height - imageHeight * baseScale) / 2,
        scale: baseScale,
        minScale: baseScale,
        maxScale: baseScale * 4,
        baseScale,
        imageWidth,
        imageHeight,
      };
      if (manualBarangayIdZoomRange) {
        manualBarangayIdZoomRange.value = '100';
      }
      manualApplyBarangayIdCropTransform();
    }

    function manualLoadBarangayIdCropSource(sourceUrl) {
      if (!manualBarangayIdCropImage) return;
      manualBarangayIdCropSourceUrl = manualResolveBarangayIdPhotoPreviewUrl(sourceUrl);
      if (manualBarangayIdSavePhotoBtn) {
        manualBarangayIdSavePhotoBtn.disabled = true;
      }
      if (manualBarangayIdZoomRange) {
        manualBarangayIdZoomRange.disabled = true;
      }
      if (!manualBarangayIdCropSourceUrl) {
        manualBarangayIdCropEmpty?.classList.remove('d-none');
        manualBarangayIdCropImage.removeAttribute('src');
        return;
      }
      manualBarangayIdCropEmpty?.classList.add('d-none');
      if (manualBarangayIdCropImage.src === manualBarangayIdCropSourceUrl) {
        manualBarangayIdCropImage.removeAttribute('src');
        requestAnimationFrame(() => {
          manualBarangayIdCropImage.src = manualBarangayIdCropSourceUrl;
        });
        return;
      }
      manualBarangayIdCropImage.src = manualBarangayIdCropSourceUrl;
    }

    function manualStartBarangayIdCropFromSource(sourceUrl, statusMessage = 'Adjust the photo inside the square frame, then save the crop.') {
      const resolvedSource = manualResolveBarangayIdPhotoPreviewUrl(sourceUrl);
      if (!resolvedSource) {
        manualSetBarangayIdPhotoStatus('No photo source is available yet. Capture a photo first or use the linked resident photo.', 'warning');
        return;
      }
      manualBarangayIdPhotoModal?.show();
      manualSwitchBarangayIdPhotoStage('crop');
      manualLoadBarangayIdCropSource(resolvedSource);
      manualStopBarangayIdCamera();
      manualSetBarangayIdPhotoStatus(statusMessage, 'info');
    }

    function manualOpenBarangayIdPhotoModal(mode = 'camera') {
      if (!manualBarangayIdPhotoModal) return;
      if (mode === 'crop') {
        const current = manualCurrentBarangayIdPhoto();
        if (!current.previewUrl) {
          manualSetBarangayIdPhotoStatus('Capture a photo first before opening the crop view.', 'warning');
          return;
        }
        manualStartBarangayIdCropFromSource(current.previewUrl);
        return;
      }
      manualBarangayIdPhotoModal.show();
      manualSwitchBarangayIdPhotoStage('camera');
      void manualRefreshBarangayIdCameraOptions(manualCurrentBarangayIdCameraDeviceId() || manualBarangayIdSelectedCameraId);
      manualStartBarangayIdCamera();
    }

    function manualCaptureBarangayIdPhoto() {
      if (!manualBarangayIdCameraVideo || !manualBarangayIdCameraVideo.videoWidth || !manualBarangayIdCameraVideo.videoHeight) {
        manualSetBarangayIdPhotoStatus('Camera preview is not ready yet. Start the camera first.', 'warning');
        return;
      }
      const canvas = document.createElement('canvas');
      canvas.width = manualBarangayIdCameraVideo.videoWidth;
      canvas.height = manualBarangayIdCameraVideo.videoHeight;
      const context = canvas.getContext('2d');
      if (!context) {
        manualSetBarangayIdPhotoStatus('Unable to capture the camera frame on this device.', 'danger');
        return;
      }
      context.drawImage(manualBarangayIdCameraVideo, 0, 0, canvas.width, canvas.height);
      manualLoadBarangayIdCropSource(canvas.toDataURL('image/png'));
      manualSwitchBarangayIdPhotoStage('crop');
      manualStopBarangayIdCamera();
      manualSetBarangayIdPhotoStatus('Photo captured. Drag and zoom it inside the square frame, then save the crop.', 'success');
    }

    function manualSaveBarangayIdCrop() {
      if (!manualBarangayIdCropImage || !manualBarangayIdCropImage.naturalWidth) {
        manualSetBarangayIdPhotoStatus('Capture or load a photo first before saving the crop.', 'warning');
        return;
      }
      const frame = manualComputeBarangayIdCropFrame();
      if (!frame) {
        manualSetBarangayIdPhotoStatus('Unable to compute the crop frame. Resize the window and try again.', 'danger');
        return;
      }
      const cropCanvas = document.createElement('canvas');
      cropCanvas.width = 512;
      cropCanvas.height = 512;
      const context = cropCanvas.getContext('2d');
      if (!context) {
        manualSetBarangayIdPhotoStatus('Unable to save the cropped photo on this browser.', 'danger');
        return;
      }
      const sourceX = (frame.left - manualBarangayIdCropState.x) / manualBarangayIdCropState.scale;
      const sourceY = (frame.top - manualBarangayIdCropState.y) / manualBarangayIdCropState.scale;
      const sourceSize = frame.size / manualBarangayIdCropState.scale;
      context.drawImage(
        manualBarangayIdCropImage,
        sourceX,
        sourceY,
        sourceSize,
        sourceSize,
        0,
        0,
        cropCanvas.width,
        cropCanvas.height
      );
      manualBarangayIdPhotoCustomDataUrl = cropCanvas.toDataURL('image/png');
      manualBarangayIdPhotoMode = 'custom';
      manualUpdateBarangayIdPhotoField();
      manualMarkPreviewStale(true);
      manualBarangayIdPhotoModal?.hide();
      manualSetAlert('Barangay ID photo saved. Preview the ID again before submitting the manual request.', 'success');
    }

    function manualResidentDisplayName(record) {
      if (!record || typeof record !== 'object') return '';
      return String(firstNonEmpty([
        record.full_name,
        [
          record.firstname || record.first_name,
          record.middlename || record.middle_name,
          record.lastname || record.last_name,
          record.suffix
        ].filter(Boolean).join(' ')
      ]) || '').trim();
    }

    function manualNormalizeResident(record) {
      if (!record || typeof record !== 'object') return null;
      const emergencyFirstName = String(firstNonEmpty([record.emergency_first_name, record.emergency_first])).trim();
      const emergencyMiddleName = String(firstNonEmpty([record.emergency_middle_name, record.emergency_middle])).trim();
      const emergencyLastName = String(firstNonEmpty([record.emergency_last_name, record.emergency_last])).trim();
      const emergencySuffix = String(firstNonEmpty([record.emergency_suffix])).trim();
      const resident = {
        resident_id: String(firstNonEmpty([record.resident_id])).trim(),
        resident_user_id: String(firstNonEmpty([record.resident_user_id, record.user_id])).trim(),
        id_number: String(firstNonEmpty([record.id_number, record.barangay_id_number])).trim(),
        existing_barangay_id_number: String(firstNonEmpty([record.existing_barangay_id_number])).trim(),
        first_name: String(firstNonEmpty([record.firstname, record.first_name])).trim(),
        middle_name: String(firstNonEmpty([record.middlename, record.middle_name])).trim(),
        last_name: String(firstNonEmpty([record.lastname, record.last_name])).trim(),
        suffix: String(firstNonEmpty([record.suffix])).trim(),
        birthdate: String(firstNonEmpty([record.birthdate])).trim(),
        birthplace: String(firstNonEmpty([record.birthplace])).trim(),
        age: String(firstNonEmpty([record.age])).trim(),
        sex: String(firstNonEmpty([record.sex])).trim(),
        civil_status: String(firstNonEmpty([record.civil_status])).trim(),
        religion: String(firstNonEmpty([record.religion])).trim(),
        occupation: String(firstNonEmpty([record.occupation, record.occupation_detail])).trim(),
        occupation_display: String(firstNonEmpty([record.occupation_display, record.occupation, record.occupation_detail])).trim(),
        head_of_family: String(firstNonEmpty([record.head_of_family])).trim(),
        voter_status: String(firstNonEmpty([record.voter_status])).trim(),
        sector_membership: String(firstNonEmpty([record.sector_membership])).trim(),
        status: String(firstNonEmpty([record.status])).trim(),
        contact_number: String(firstNonEmpty([record.contact_number, record.phone_number])).trim(),
        full_address: String(firstNonEmpty([record.full_address])).trim(),
        unit_number: String(firstNonEmpty([record.unit_number])).trim(),
        house_number: String(firstNonEmpty([record.house_number, record.street_number])).trim(),
        street_name: String(firstNonEmpty([record.street_name])).trim(),
        phase_number: String(firstNonEmpty([record.phase_number])).trim(),
        subdivision: String(firstNonEmpty([record.subdivision])).trim(),
        area_number: String(firstNonEmpty([record.area_number])).trim(),
        barangay: String(firstNonEmpty([record.barangay, record.full_barangay])).trim(),
        municipality_city: String(firstNonEmpty([record.municipality_city, record.city, record.municipality])).trim(),
        province: String(firstNonEmpty([record.province])).trim(),
        house_ownership: String(firstNonEmpty([record.house_ownership])).trim(),
        house_type: String(firstNonEmpty([record.house_type])).trim(),
        residency_duration: String(firstNonEmpty([record.residency_duration])).trim(),
        emergency_first_name: emergencyFirstName,
        emergency_middle_name: emergencyMiddleName,
        emergency_last_name: emergencyLastName,
        emergency_suffix: emergencySuffix,
        emergency_contact: String(firstNonEmpty([record.emergency_contact, record.emergency_contact_number, record.emergency_phone_number])).trim(),
        emergency_contact_number: String(firstNonEmpty([record.emergency_contact_number, record.emergency_contact, record.emergency_phone_number])).trim(),
        emergency_relationship: String(firstNonEmpty([record.emergency_relationship, record.relationship])).trim(),
        emergency_address: String(firstNonEmpty([record.emergency_address])).trim(),
        id_picture_url: String(firstNonEmpty([record.id_picture_url])).trim(),
        id_picture_path: String(firstNonEmpty([record.id_picture_path])).trim(),
      };

      resident.full_name = manualResidentDisplayName(record) || manualResidentDisplayName(resident);
      resident.emergency_full_name = String(firstNonEmpty([
        record.emergency_full_name,
        [emergencyFirstName, emergencyMiddleName, emergencyLastName, emergencySuffix].filter(Boolean).join(' ')
      ])).trim();

      return resident;
    }

    function manualSuggestedPurpose(config) {
      if (!config) return '';
      if (config.kind === 'general_certification' && config.purpose) {
        return config.purpose === '__other__' ? '' : config.purpose;
      }
      if (manualUsesPurposePreset(config)) {
        const presetValue = String(manualPurposePreset?.value || '').trim();
        return presetValue && presetValue !== '__other__' ? presetValue : '';
      }
      if (config.kind === 'general_clearance') {
        return generalClearancePurposeFromDocType(config.documentType);
      }
      if (config.kind === 'business_clearance') {
        const applicationTypeField = manualDynamicFields?.querySelector('[data-manual-field="application_type"]');
        const applicationType = String(applicationTypeField?.value || '').trim().toLowerCase();
        return applicationType === 'renewal' ? 'Business Permit - Renewal' : 'Business Permit - New Application';
      }
      if (config.kind === 'tricycle_clearance') {
        const applicationTypeField = manualDynamicFields?.querySelector('[data-manual-field="application_type"]');
        const applicationType = String(applicationTypeField?.value || '').trim().toLowerCase();
        return applicationType === 'renewal' ? 'Tricycle Permit - Renewal' : 'Tricycle Permit - New Application';
      }
      if (config.kind === 'barangay_id') return 'Barangay ID Application';
      if (config.kind === 'jail_visit') return 'Jail Visitation';
      if (config.kind === 'first_time_job_seeker') return 'First Time Job Seeker Application';
      return '';
    }

    function manualApplySuggestedPurpose() {
      if (!manualPurpose) return;
      const config = manualCurrentConfig();
      manualSyncPurposePreset();
      const suggested = manualSuggestedPurpose(config);
      const currentValue = String(manualPurpose?.value || '').trim();
      const wasAuto = String(manualPurpose?.dataset.auto || '0') === '1';
      if (suggested) {
        if (!currentValue || wasAuto) {
          manualPurpose.value = suggested;
          manualPurpose.dataset.auto = '1';
        }
      } else if (!currentValue) {
        manualPurpose.dataset.auto = '1';
      }
    }

    function manualCurrentFeeRows() {
      if (!manualFeeList) return [];
      return Array.from(manualFeeList.querySelectorAll('.manual-fee-item')).map((item) => {
        const checkbox = item.querySelector('[data-manual-fee-check]');
        const amountInput = item.querySelector('[data-manual-fee-amount]');
        const feeName = String(checkbox?.getAttribute('data-fee-name') || '').trim();
        const checked = !!checkbox?.checked;
        const amount = Number(amountInput?.value || 0);
        return checked && feeName
          ? { fee_name: feeName, amount: Number.isFinite(amount) && amount >= 0 ? amount : 0 }
          : null;
      }).filter(Boolean);
    }

    function manualExpectedStage(config = manualCurrentConfig(), feeRows = manualCurrentFeeRows()) {
      const rawConfig = manualCurrentRawConfig();
      if (!config) {
        return {
          key: '',
          label: 'Preview the document first to unlock submission.'
        };
      }
      const hasExemptSector = manualHasExemptSector(manualCurrentSectorValues());
      if (config.kind === 'first_time_job_seeker') {
        return {
          key: 'for_interview',
          label: 'After submit: For Interview'
        };
      }
      if (hasExemptSector) {
        return {
          key: 'ready_for_claim',
          label: 'After submit: Ready for Release'
        };
      }
      if (config.clearance) {
        if (!feeRows.length) {
          return {
            key: 'needs_fee_tagging',
            label: 'Tag at least one clearance fee before submission.'
          };
        }
        const total = feeRows.reduce((sum, row) => sum + (Number(row.amount) || 0), 0);
        return total <= 0
          ? { key: 'ready_for_claim', label: 'After submit: Ready for Release' }
          : { key: 'for_payment', label: 'After submit: For Payment' };
      }
      if (config.free) {
        return {
          key: 'ready_for_claim',
          label: 'After submit: Ready for Release'
        };
      }
      if (manualIsOtherDocumentSelection(rawConfig)) {
        const customFee = manualResolvedDocumentFee();
        if (customFee !== null && customFee <= 0) {
          return {
            key: 'ready_for_claim',
            label: 'After submit: Ready for Release'
          };
        }
      }
      if (config.kind === 'barangay_id') {
        return {
          key: 'ready_for_claim',
          label: 'After submit: Ready for Release'
        };
      }
      return {
        key: 'for_payment',
        label: 'After submit: For Payment'
      };
    }

    function manualUpdateSummary() {
      const config = manualCurrentConfig();
      const rawConfig = manualCurrentRawConfig();
      const mode = manualCurrentMode();
      const typedName = [manualFirstName?.value, manualMiddleName?.value, manualLastName?.value, manualSuffix?.value]
        .map((part) => String(part || '').trim())
        .filter(Boolean)
        .join(' ');

      if (manualResidentSummary) {
        if (manualSelectedResident && mode === 'existing') {
          manualResidentSummary.textContent = `${manualSelectedResident.full_name || typedName || 'Registered resident'} (${manualSelectedResident.resident_id || 'linked'})`;
        } else if (typedName) {
          manualResidentSummary.textContent = mode === 'walkin' ? `Walk-in: ${typedName}` : typedName;
        } else {
          manualResidentSummary.textContent = mode === 'walkin' ? 'Walk-in / not linked yet' : 'Registered resident not selected yet';
        }
      }

      if (manualDocumentSummary) {
        manualDocumentSummary.textContent = config
          ? manualResolvedDocumentLabel(rawConfig, config)
          : 'Select a manual issuance form';
      }
      if (manualNextStageSummary) manualNextStageSummary.textContent = manualExpectedStage(config).label;
      if (manualValidityWrap && manualValidityDate) {
        const validityKind = manualValidityKind(config);
        const showValidity = !!validityKind;
        manualValidityWrap.classList.toggle('d-none', !showValidity);
        manualValiditySummaryWrap?.classList.toggle('d-none', !showValidity);
        if (showValidity) {
          if (!String(manualValidityDate.value || '').trim()) {
            manualValidityDate.value = configureValidityField(
              manualValidityLabel,
              manualValidityHelp,
              manualValidityDate,
              validityKind
            );
          } else {
            configureValidityField(
              manualValidityLabel,
              manualValidityHelp,
              manualValidityDate,
              validityKind,
              manualValidityDate.value
            );
          }
          if (manualValiditySummaryLabel) {
            manualValiditySummaryLabel.textContent = validityKind === 'barangay_id' ? 'Barangay ID Validity' : 'Certificate Validity';
          }
          if (manualValiditySummary) {
            manualValiditySummary.textContent = formatValiditySummary(manualValidityDate.value, validityKind);
          }
        } else {
          manualValidityDate.value = '';
          if (manualValiditySummaryLabel) {
            manualValiditySummaryLabel.textContent = 'Selected Validity';
          }
          if (manualValiditySummary) {
            manualValiditySummary.textContent = 'Default: 45 days after approval';
          }
        }
      }
    }

    function manualApplyCommonFieldRequirements(config) {
      const isBarangayId = config?.kind === 'barangay_id';
      if (manualBirthdate) manualBirthdate.required = true;
      manualBirthdateRequiredMark?.classList.remove('d-none');
      if (manualSex) manualSex.required = true;
      if (manualCivilStatus) manualCivilStatus.required = true;
      if (manualContactNumber) manualContactNumber.required = true;
      if (manualBirthplace) manualBirthplace.required = true;
      manualBirthplaceRequiredMark?.classList.remove('d-none');
      if (manualOccupation) manualOccupation.required = !isBarangayId;
      if (manualReligion) manualReligion.required = !isBarangayId;
    }

    function manualMarkPreviewStale(silent = false) {
      manualPreviewSignature = '';
      if (manualSubmitBtn) {
        manualSubmitBtn.disabled = true;
      }
      if (!isIdIssuanceTrackerView && manualDocumentInlinePreview) {
        if (manualIdWizardCurrentStep === 5) {
          manualDocumentInlinePreview.innerHTML = '<div class="manual-id-inline-preview-loading"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Refreshing document preview...</div>';
          queueMicrotask(() => {
            if (manualIdWizardCurrentStep === 5) void manualRenderInlineDocumentPreview();
          });
        } else {
          manualDocumentInlinePreview.innerHTML = '<div class="manual-id-inline-preview-loading"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Document preview will appear here automatically on Step 5.</div>';
        }
      }
      if (!silent && manualCurrentConfig()) {
        manualSetAlert('Preview the latest changes before submitting this manual issuance request.', 'info');
      }
      manualUpdateSummary();
    }

    function manualFieldDefinitions(config) {
      if (!config) return [];
      switch (config.kind) {
        case 'general_clearance':
          return [
            ...(String(config.id || '').includes('commercial')
              ? [{ name: 'establishment_name', label: 'Establishment / Building Name', type: 'text', required: true, col: 'col-12' }]
              : []),
            { name: 'location', label: 'Project / Permit Location', type: 'text', required: true, col: 'col-md-6' },
            { name: 'remarks', label: 'Remarks', type: 'text', col: 'col-md-6' }
          ];
        case 'business_clearance':
          return [
            { name: 'application_type', label: 'Application Type', type: 'select', required: true, col: 'col-md-4', options: [
              { value: 'New', label: 'New' },
              { value: 'Renewal', label: 'Renewal' }
            ] },
            { name: 'business_name', label: 'Business Name', type: 'text', required: true, col: 'col-md-8' },
            { name: 'plate_number', label: 'Plate Number', type: 'text', required: true, col: 'col-md-4', placeholder: 'Enter the plate number to issue' },
            { name: 'previous_plate_number', label: 'Old Plate Number', type: 'text', required: true, col: 'col-md-4', placeholder: 'Enter the old plate number', showWhen: { field: 'application_type', value: 'Renewal' } },
            { name: 'business_type', label: 'Business Type', type: 'select', required: true, allowOther: true, col: 'col-md-6', options: [
              { value: 'Retail', label: 'Retail' },
              { value: 'Food and Beverage', label: 'Food and Beverage' },
              { value: 'Services', label: 'Services' },
              { value: 'Manufacturing', label: 'Manufacturing' }
            ] },
            { name: 'business_approval_type', label: 'Approval Type', type: 'checklist', required: true, col: 'col-12', options: [
              { value: 'not_banned', label: 'Not among banned business activities' },
              { value: 'no_objection', label: 'Interposes no objection' },
              { value: 'temporary_clearance', label: 'Temporary Barangay Clearance' }
            ] },
            { name: 'business_address_line', label: 'Business Address', type: 'text', required: true, col: 'col-12', placeholder: 'House/unit, street, phase, or subdivision' },
            { name: 'business_area_number', label: 'Area Number', type: 'select', required: true, col: 'col-md-6', options: [
              { value: 'Area 01', label: 'Area 01 - San Jose Proper' },
              { value: 'Area 1A', label: 'Area 1A - Litex Village and nearby communities' },
              { value: 'Area 02', label: 'Area 02 - VFW, Amychelle, Christine Villa and nearby communities' },
              { value: 'Area 03', label: 'Area 03 - Relocation' },
              { value: 'Area 04', label: 'Area 04 - Kasiglahan Phase 1-B, 1-C, 1-D, 1-M, 1-A' },
              { value: 'Area 05', label: 'Area 05 - Kasiglahan Phase 1-K, 1K1, 1K2, 1-E, 1-G' },
              { value: 'Area 06', label: 'Area 06 - Sub-Urban and Metro Manila Hills' }
            ] },
            { name: 'business_barangay', label: 'Barangay', type: 'text', value: 'San Jose', readonly: true, col: 'col-md-6' },
            { name: 'business_city', label: 'City / Municipality', type: 'text', value: 'Rodriguez (Montalban)', readonly: true, col: 'col-md-6' },
            { name: 'business_province', label: 'Province', type: 'text', value: 'Rizal', readonly: true, col: 'col-md-6' }
          ];
        case 'tricycle_clearance':
          return [
            { name: 'application_type', label: 'Application Type', type: 'select', required: true, col: 'col-md-4', options: [
              { value: 'New', label: 'New' },
              { value: 'Renewal', label: 'Renewal' }
            ] },
            { name: 'location_of_toda_poda', label: 'Location', type: 'text', required: true, col: 'col-md-4' },
            { name: 'vehicle_make', label: 'Type of Vehicle', type: 'text', required: true, col: 'col-md-4' },
            { name: 'cr_number', label: 'Registration No.', type: 'text', required: true, col: 'col-md-4' },
            { name: 'plate_number', label: 'Plate No.', type: 'text', required: true, col: 'col-md-4' },
            { name: 'body_number', label: 'Body No.', type: 'text', required: true, col: 'col-md-4' }
          ];
        case 'barangay_id':
          return [
            { name: 'emergency_last', label: 'Emergency Last Name', type: 'text', required: true, col: 'col-md-3' },
            { name: 'emergency_first', label: 'Emergency First Name', type: 'text', required: true, col: 'col-md-3' },
            { name: 'emergency_middle', label: 'Emergency Middle Name', type: 'text', col: 'col-md-3' },
            { name: 'emergency_suffix', label: 'Emergency Suffix', type: 'select', col: 'col-md-3', options: [
              { value: 'Jr.', label: 'Jr.' },
              { value: 'Sr.', label: 'Sr.' },
              { value: 'II', label: 'II' },
              { value: 'III', label: 'III' },
              { value: 'IV', label: 'IV' }
            ] },
            { name: 'emergency_relationship', label: 'Relationship', type: 'select', required: true, col: 'col-md-6', options: [
              { value: 'Parent', label: 'Parent' },
              { value: 'Spouse', label: 'Spouse' },
              { value: 'Sibling', label: 'Sibling' },
              { value: 'Child', label: 'Child' },
              { value: 'Relative', label: 'Other Relative' },
              { value: 'Guardian', label: 'Guardian' }
            ] },
            { name: 'emergency_contact', label: 'Emergency Contact Number', type: 'text', required: true, col: 'col-md-6' },
            { name: 'emergency_address', label: 'Emergency Address', type: 'textarea', required: true, col: 'col-12', rows: 2 },
            { name: 'barangay_id_photo_capture', label: 'Barangay ID Photo', type: 'photo_capture', required: true, col: 'col-12' }
          ];
        case 'residency':
        case 'general_certification':
          return [
            { name: 'remarks', label: 'Remarks', type: 'text', col: 'col-md-6' }
          ];
        case 'identity':
          return [
            { name: 'remarks', label: 'Remarks', type: 'text', col: 'col-md-6' },
            { name: 'years_of_residency', label: 'Years of Residency', type: 'number', min: '0', col: 'col-md-3' },
            { name: 'months_of_residency', label: 'Months of Residency', type: 'number', min: '0', col: 'col-md-3' }
          ];
        case 'indigency':
          return [
            { name: 'indigency_recipient', label: 'Recipient Details', type: 'indigency_recipient', required: true, col: 'col-12' }
          ];
        case 'cohabitation':
          return [
            { name: 'cohabitant_first', label: 'Partner First Name', type: 'text', required: true, col: 'col-md-3' },
            { name: 'cohabitant_middle', label: 'Partner Middle Name', type: 'text', col: 'col-md-3' },
            { name: 'cohabitant_last', label: 'Partner Last Name', type: 'text', required: true, col: 'col-md-3' },
            { name: 'cohabitant_suffix', label: 'Partner Suffix', type: 'text', col: 'col-md-3' },
            { name: 'cohabitant_birthdate', label: 'Partner Birthdate', type: 'date', required: true, col: 'col-md-6' },
            { name: 'cohabitation_start_date', label: 'Living Together Since', type: 'date', required: true, col: 'col-md-6' },
            { name: 'cohabitant_full_address', label: 'Partner Residential Address', type: 'textarea', required: true, col: 'col-12', rows: 2 },
            { name: 'cohabitation_full_address', label: 'Current Cohabitation Address', type: 'textarea', required: true, col: 'col-12', rows: 2 }
          ];
        case 'jail_visit':
          return [
            { name: 'cohabitant_first', label: 'Detainee First Name', type: 'text', required: true, col: 'col-md-3' },
            { name: 'cohabitant_middle', label: 'Detainee Middle Name', type: 'text', col: 'col-md-3' },
            { name: 'cohabitant_last', label: 'Detainee Last Name', type: 'text', required: true, col: 'col-md-3' },
            { name: 'cohabitant_suffix', label: 'Detainee Suffix', type: 'text', col: 'col-md-3' },
            { name: 'cohabitant_relationship', label: 'Relationship to Detainee', type: 'select', required: true, allowOther: true, col: 'col-md-6', options: [
              { value: 'Parent', label: 'Parent' }, { value: 'Spouse', label: 'Spouse' },
              { value: 'Sibling', label: 'Sibling' }, { value: 'Child', label: 'Child' },
              { value: 'Relative', label: 'Relative' }, { value: 'Guardian', label: 'Guardian' }
            ] },
            { name: 'detention_facility', label: 'Detention Facility', type: 'text', required: true, col: 'col-md-6' }
          ];
        case 'first_time_job_seeker':
          return [
            { name: 'educational_attainment', label: 'Educational Attainment', type: 'select', required: true, allowOther: true, col: 'col-md-6', options: [
              { value: 'Elementary', label: 'Elementary' }, { value: 'High School', label: 'High School' },
              { value: 'Senior High School', label: 'Senior High School' }, { value: 'Vocational', label: 'Vocational' },
              { value: 'College', label: 'College' }, { value: 'Postgraduate', label: 'Postgraduate' }
            ] },
            { name: 'jobstart_beneficiary', label: 'Has Availed Before?', type: 'select', required: true, col: 'col-md-6', options: [
              { value: 'No', label: 'No' },
              { value: 'Yes', label: 'Yes' }
            ] },
            { name: 'years_of_residency', label: 'Years of Residency', type: 'number', min: '0', required: true, col: 'col-md-6' },
            { name: 'months_of_residency', label: 'Additional Months of Residency', type: 'number', min: '0', required: true, col: 'col-md-6' }
          ];
        default:
          return [];
      }
    }

    function manualFieldHtml(field) {
      const col = field.col || 'col-md-6';
      const required = field.required ? 'required' : '';
      const readonly = field.readonly ? 'readonly' : '';
      const conditionAttrs = field.showWhen
        ? `data-manual-condition-field="${manualEscapeAttr(field.showWhen.field)}" data-manual-condition-value="${manualEscapeAttr(field.showWhen.value)}"`
        : '';
      const conditionClass = field.showWhen ? ' d-none' : '';
      const min = field.min !== undefined ? `min="${manualEscapeAttr(field.min)}"` : '';
      const placeholder = field.placeholder ? `placeholder="${manualEscapeAttr(field.placeholder)}"` : '';
      const valueAttr = field.value ? `value="${manualEscapeAttr(field.value)}"` : '';
      const label = `${esc(field.label)}${field.required ? ' <span class="text-danger">*</span>' : ''}`;

      if (field.type === 'indigency_recipient') {
        const groupOptions = Array.isArray(manualIndigencyGovernmentDirectory.groups)
          ? manualIndigencyGovernmentDirectory.groups.map((group) => `
              <option value="${manualEscapeAttr(group.id)}">${esc(group.name)}</option>
            `).join('')
          : '';
        const positionOptions = Array.isArray(manualIndigencyGovernmentDirectory.positions)
          ? manualIndigencyGovernmentDirectory.positions.map((position) => `
              <option value="${manualEscapeAttr(position)}">${esc(position)}</option>
            `).join('')
          : '';
        const officialOptions = Array.isArray(manualIndigencyGovernmentDirectory.officials)
          ? manualIndigencyGovernmentDirectory.officials.map((official) => {
              const labelText = [
                String(official?.name || '').trim(),
                String(official?.position_name || '').trim(),
                String(official?.jurisdiction_location || '').trim(),
              ].filter(Boolean).join(' - ');
              return `
                <option
                  value="${manualEscapeAttr(official?.id || '')}"
                  data-category="${manualEscapeAttr(official?.group_key || '')}"
                  data-position="${manualEscapeAttr(official?.position_name || '')}"
                  data-location="${manualEscapeAttr(official?.jurisdiction_location || '')}"
                  data-name="${manualEscapeAttr(official?.name || '')}"
                >
                  ${esc(labelText)}
                </option>
              `;
            }).join('')
          : '';
        return `
          <div class="${col}">
            <label class="form-label fw-semibold small">Recipient Type <span class="text-danger">*</span></label>
            <select class="form-select" data-manual-field="submission_target_type" data-manual-indigency-trigger="target">
              <option value="">Select recipient type</option>
              <option value="government_official">Government Official</option>
              <option value="institution">Institution / Office</option>
            </select>

            <div class="row g-3 mt-1 d-none" data-manual-indigency-government>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Recipient Address <span class="text-danger">*</span></label>
                <select class="form-select" data-manual-field="government_position_group" data-manual-indigency-trigger="group">
                  <option value="">Select office group</option>
                  ${groupOptions}
                  <option value="${manualDropdownOtherValue}">Other</option>
                </select>
                <input type="text" class="form-control mt-2 d-none" data-manual-field="government_position_other" placeholder="Enter recipient address">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Recipient Position <span class="text-danger">*</span></label>
                <select class="form-select" data-manual-field="government_position_detail">
                  <option value="">Select position</option>
                  ${positionOptions}
                </select>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">Recipient Name <span class="text-danger">*</span></label>
                <select class="form-select" data-manual-field="government_official_id" data-manual-indigency-trigger="official" disabled>
                  <option value="">Select official</option>
                  ${officialOptions}
                  <option value="${manualDropdownOtherValue}">Other</option>
                </select>
                <input type="text" class="form-control mt-2 d-none" data-manual-field="government_official_other" placeholder="Enter recipient name">
                <div class="form-text" data-manual-indigency-empty-state>Choose a recipient address first.</div>
              </div>
            </div>

            <div class="row g-3 mt-1 d-none" data-manual-indigency-institution>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Recipient Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" data-manual-field="institution_person" placeholder="Enter recipient name">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Recipient Position <span class="text-danger">*</span></label>
                <input type="text" class="form-control" data-manual-field="institution_position" placeholder="Enter recipient position">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Recipient Address <span class="text-danger">*</span></label>
                <input type="text" class="form-control" data-manual-field="institution_name" placeholder="Enter recipient address">
              </div>
            </div>

            <input type="hidden" data-manual-field="request_officer_line1">
            <input type="hidden" data-manual-field="request_officer_line2">
            <input type="hidden" data-manual-field="request_officer_line3">
            <input type="hidden" data-manual-field="request_officer">
            <input type="hidden" data-manual-field="government_office">
            <input type="hidden" data-manual-field="government_position">
            <input type="hidden" data-manual-field="government_official">
          </div>
        `;
      }

      if (field.type === 'photo_capture') {
        return `
          <div class="${col}">
            <div class="manual-photo-field" data-manual-photo-field="barangay_id">
              <div class="manual-photo-capture-layout">
                <div class="manual-photo-preview-box" data-manual-photo-preview>
                  <div class="manual-photo-preview-placeholder">No Barangay ID photo saved yet. Take a photo, then crop and save it here.</div>
                </div>
                <div class="manual-photo-meta">
                  <span class="manual-photo-chip" data-manual-photo-chip><i class="fas fa-camera"></i>Required ID photo</span>
                  <div class="manual-photo-guidance">
                    <h6>Capture the resident's ID photo</h6>
                    <p>Use a clear, recent photo with the resident facing forward against a plain background.</p>
                    <ul>
                      <li>Keep the full face visible</li>
                      <li>Use even lighting</li>
                      <li>Center the face inside the crop guide</li>
                    </ul>
                  </div>
                  <div class="manual-photo-actions" data-manual-photo-actions>
                    ${manualBarangayIdPhotoButton('Open Camera', 'take', 'primary', 'fa-camera')}
                  </div>
                  <p class="manual-photo-note" data-manual-photo-note>
                    You can review and crop the image before it is saved.
                  </p>
                </div>
              </div>
            </div>
          </div>
        `;
      }

      if (field.type === 'textarea') {
        return `
          <div class="${col}${conditionClass}" ${conditionAttrs}>
            <label class="form-label fw-semibold small">${label}</label>
            <textarea class="form-control" rows="${field.rows || 2}" data-manual-field="${manualEscapeAttr(field.name)}" ${required} ${placeholder}></textarea>
          </div>
        `;
      }

      if (field.type === 'checklist') {
        const options = Array.isArray(field.options) ? field.options.map((option, index) => `
          <label class="form-check border rounded-3 p-3 d-flex align-items-start gap-2 mb-2" for="manual_${manualEscapeAttr(field.name)}_${index}">
            <input class="form-check-input mt-1" type="checkbox" id="manual_${manualEscapeAttr(field.name)}_${index}" data-manual-field="${manualEscapeAttr(field.name)}" value="${manualEscapeAttr(option.value)}">
            <span class="form-check-label">${esc(option.label)}</span>
          </label>
        `).join('') : '';
        return `
          <div class="${col}${conditionClass}" ${conditionAttrs} data-manual-checklist-required="${field.required ? '1' : '0'}">
            <fieldset>
              <legend class="form-label fw-semibold small">${label}</legend>
              ${options}
              <div class="invalid-feedback">Select at least one ${esc(field.label.toLowerCase())}.</div>
            </fieldset>
          </div>
        `;
      }

      if (field.type === 'select') {
        const options = Array.isArray(field.options) ? field.options.map((option) => `
          <option value="${manualEscapeAttr(option.value)}">${esc(option.label)}</option>
        `).join('') : '';
        return `
          <div class="${col}${conditionClass}" ${conditionAttrs}>
            <label class="form-label fw-semibold small">${label}</label>
            <select class="form-select" data-manual-field="${manualEscapeAttr(field.name)}" ${required}>
              <option value="">Select ${esc(field.label.toLowerCase())}</option>
              ${options}
              ${field.allowOther ? '<option value="__other__">Other</option>' : ''}
            </select>
            ${field.allowOther ? `<input type="text" class="form-control mt-2 d-none" data-manual-other-for="${manualEscapeAttr(field.name)}" placeholder="Please specify ${manualEscapeAttr(field.label.toLowerCase())}">` : ''}
          </div>
        `;
      }

      return `
        <div class="${col}${conditionClass}" ${conditionAttrs}>
          <label class="form-label fw-semibold small">${label}</label>
          <input type="${manualEscapeAttr(field.type || 'text')}" class="form-control" data-manual-field="${manualEscapeAttr(field.name)}" ${required} ${readonly} ${placeholder} ${valueAttr} ${min}>
        </div>
      `;
    }

    function manualSyncConditionalFields() {
      manualDynamicFields?.querySelectorAll('[data-manual-condition-field]').forEach((wrapper) => {
        const sourceName = String(wrapper.dataset.manualConditionField || '').trim();
        const expectedValue = String(wrapper.dataset.manualConditionValue || '').trim().toLowerCase();
        const source = manualDynamicFields.querySelector(`[data-manual-field="${sourceName}"]`);
        const show = String(source?.value || '').trim().toLowerCase() === expectedValue;
        wrapper.classList.toggle('d-none', !show);
        wrapper.querySelectorAll('input, select, textarea').forEach((input) => {
          input.disabled = !show;
          if (input.dataset.manualField === 'previous_plate_number') {
            input.required = show;
          }
          if (!show && input.type !== 'hidden') input.value = '';
        });
      });
    }

    function manualRenderDocumentOptions() {
      if (!manualDocumentType) return;
      const scopedConfigs = manualAvailableDocumentConfigs();
      if (!scopedConfigs.length) {
        manualDocumentType.innerHTML = '<option value="">No manual issuance forms available in this section</option>';
        return;
      }
      const grouped = scopedConfigs.reduce((map, config) => {
        if (!map.has(config.group)) map.set(config.group, []);
        map.get(config.group).push(config);
        return map;
      }, new Map());
      let html = '<option value="">Select a manual issuance form</option>';
      grouped.forEach((configs, group) => {
        html += `<optgroup label="${manualEscapeAttr(group)}">`;
        html += configs.map((config) => `<option value="${manualEscapeAttr(config.id)}">${esc(config.label)}</option>`).join('');
        html += '</optgroup>';
      });
      manualDocumentType.innerHTML = html;
    }

    async function manualRenderFeeCatalog() {
      const config = manualCurrentConfig();
      if (!manualFeeWrap || !manualFeeList || !manualFeeTotal) return;
      if (!config?.clearance) {
        manualFeeWrap.classList.add('d-none');
        manualFeeList.innerHTML = '';
        manualFeeTotal.textContent = 'PHP 0.00';
        return;
      }

      const previous = new Map(manualCurrentFeeRows().map((row) => [String(row.fee_name || '').toLowerCase(), row]));
      manualFeeWrap.classList.toggle('d-none', !isIdIssuanceTrackerView && manualIdWizardCurrentStep !== 3);
      manualFeeList.innerHTML = `
        <div class="manual-search-empty">
          <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading fee catalog...
        </div>
      `;
      try {
        const feeTypes = await fetchFeeTypeCatalog();
        if (!Array.isArray(feeTypes) || !feeTypes.length) {
          manualFeeList.innerHTML = '<div class="manual-search-empty">No approved clearance fee types are available yet.</div>';
          manualFeeTotal.textContent = 'PHP 0.00';
          manualUpdateSummary();
          return;
        }
        manualFeeList.innerHTML = feeTypes.map((feeType) => {
          const feeName = String(firstNonEmpty([feeType?.fee_name, 'Fee'])).trim();
          const defaultAmount = Number(feeType?.default_amount || 0);
          const previousRow = previous.get(feeName.toLowerCase());
          const checked = !!previousRow;
          const amount = previousRow ? Number(previousRow.amount || 0) : defaultAmount;
          return `
            <div class="manual-fee-item">
              <div class="row g-3 align-items-center">
                <div class="col-lg-7">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="manualFee_${manualEscapeAttr(String(feeType?.fee_type_id || feeName))}" data-manual-fee-check data-fee-name="${manualEscapeAttr(feeName)}" ${checked ? 'checked' : ''}>
                    <label class="form-check-label" for="manualFee_${manualEscapeAttr(String(feeType?.fee_type_id || feeName))}">
                      ${esc(feeName)}
                    </label>
                  </div>
                </div>
                <div class="col-lg-5">
                  <div class="input-group">
                    <span class="input-group-text">PHP</span>
                    <input type="number" class="form-control" min="0" step="0.01" data-manual-fee-amount value="${Number.isFinite(amount) ? amount.toFixed(2) : '0.00'}">
                  </div>
                </div>
              </div>
            </div>
          `;
        }).join('');
        manualUpdateFeeTotal();
      } catch (error) {
        manualFeeList.innerHTML = `<div class="manual-search-empty text-danger">${esc(error?.message || 'Failed to load fee catalog.')}</div>`;
        manualFeeTotal.textContent = 'PHP 0.00';
      }
    }

    function manualUpdateFeeTotal() {
      const total = manualCurrentFeeRows().reduce((sum, row) => sum + (Number(row.amount) || 0), 0);
      if (manualFeeTotal) {
        manualFeeTotal.textContent = formatPhpAmount(total, 'PHP 0.00');
      }
      manualUpdateSummary();
    }

    function manualRenderDynamicFields() {
      const rawConfig = manualCurrentRawConfig();
      const config = manualCurrentConfig();
      const fields = manualFieldDefinitions(config);
      if (!manualDynamicFields || !manualSpecificFieldsHint) return;
      manualClearValidationState();
      manualSyncOtherDocumentSetup();
      manualApplyCommonFieldRequirements(config);
      manualSyncPurposePreset();
      if (!config) {
        manualDynamicFields.innerHTML = '<div class="col-12"><div class="manual-search-empty">Select a certificate or clearance type to load its matching manual encoding fields.</div></div>';
        manualSpecificFieldsHint.textContent = manualIsOtherDocumentSelection(rawConfig)
          ? 'Select the base certificate template for this Other Document to load the matching fields.'
          : 'Select a certificate or clearance type to load its manual encoding fields.';
        manualRenderFeeCatalog();
        manualUpdateSummary();
        return;
      }
      manualSpecificFieldsHint.textContent = manualIsOtherDocumentSelection(rawConfig)
        ? `This Other Document uses the ${config.label} template for its required fields and preview layout.`
        : (config.kind === 'general_certification'
          ? 'This manual document uses the residency certification layout, while the purpose is selected from the guided list.'
        : (config.kind === 'barangay_id'
          ? 'Record one reachable emergency contact for the resident.'
          : (config.clearance
            ? 'Clearance forms can also tag fees here so finance receives the exact walk-in amount.'
            : 'Complete the extra fields that appear in the chosen handwritten form.')));
      if (!fields.length) {
        manualDynamicFields.innerHTML = '<div class="col-12"><div class="manual-search-empty">No additional fields are required beyond the basic information and purpose for this form.</div></div>';
      } else {
        manualDynamicFields.innerHTML = fields.map(manualFieldHtml).join('');
      }
      manualSyncConditionalFields();
      manualSyncIndigencyRecipientFields();
      if (config.kind === 'barangay_id' && manualBarangayIdPhotoStepMount) {
        const photoColumn = manualDynamicFields.querySelector('[data-manual-photo-field="barangay_id"]')?.closest('[class*="col-"]');
        if (photoColumn) manualBarangayIdPhotoStepMount.replaceChildren(photoColumn);
      } else if (manualBarangayIdPhotoStepMount) {
        manualBarangayIdPhotoStepMount.replaceChildren();
      }
      manualApplyResidentDynamicFields();
      manualUpdateBarangayIdPhotoField();
      manualApplySuggestedPurpose();
      manualRenderFeeCatalog();
      manualUpdateSummary();
    }

    function manualToggleResidentLookup() {
      const isWalkin = manualCurrentMode() === 'walkin';
      manualResidentLookupWrap?.classList.toggle('d-none', isWalkin);
      if (isWalkin) {
        manualResidentSearchInput.value = '';
        manualResidentResultsWrap?.classList.add('d-none');
        manualResidentResults.innerHTML = '';
        manualResidentSearchHint?.classList.remove('d-none');
        manualSelectedResident = null;
        manualResidentId.value = '';
        manualResidentUserId.value = '';
        manualSelectedResidentCard?.classList.add('d-none');
        manualSelectedResidentName.textContent = 'Registered resident';
        manualSelectedResidentMeta.textContent = '';
        manualSyncBarangayIdPhotoResidentSource();
      }
      manualSyncSectorMembershipUi();
      manualMarkPreviewStale(true);
      manualUpdateSummary();
    }

    function manualApplyResidentDynamicFields() {
      if (!manualDynamicFields) return;
      if (!manualSelectedResident) {
        manualSyncBarangayIdPhotoResidentSource();
        manualSyncSectorMembershipUi();
        return;
      }
      const resident = manualSelectedResident;
      const fieldValues = {
        emergency_last: resident.emergency_last_name,
        emergency_first: resident.emergency_first_name,
        emergency_middle: resident.emergency_middle_name,
        emergency_suffix: resident.emergency_suffix,
        emergency_relationship: resident.emergency_relationship,
        emergency_contact: resident.emergency_contact,
        emergency_address: resident.emergency_address,
      };
      Object.entries(fieldValues).forEach(([key, value]) => {
        const input = manualDynamicFields.querySelector(`[data-manual-field="${key}"]`);
        if (input && !String(input.value || '').trim()) {
          input.value = String(value || '').trim();
        }
      });
      manualSyncBarangayIdPhotoResidentSource();
      manualSyncSectorMembershipUi();
    }

    function manualClearLinkedResidentFields() {
      [
        manualLastName,
        manualFirstName,
        manualMiddleName,
        manualSuffix,
        manualBirthdate,
        manualBirthplace,
        manualSex,
        manualCivilStatus,
        manualContactNumber,
        manualOccupation,
        manualReligion,
        manualFullAddress
      ].forEach((field) => {
        if (!field) return;
        field.value = '';
      });
      if (manualAddressLine) manualAddressLine.value = '';
      manualSyncBirthdateDropdownsFromValue();
      manualSetAreaNumber('');
      manualSyncStructuredAddress();

      if (manualDynamicFields) {
        [
          'emergency_last',
          'emergency_first',
          'emergency_middle',
          'emergency_suffix',
          'emergency_contact',
          'emergency_address'
        ].forEach((key) => {
          const input = manualDynamicFields.querySelector(`[data-manual-field="${key}"]`);
          if (input) {
            input.value = '';
          }
        });
      }
    }

    function manualFillFormFromResident(record) {
      const resident = manualNormalizeResident(record);
      if (!resident) return;
      const previousResidentKey = [
        String(manualSelectedResident?.resident_id || '').trim(),
        String(manualSelectedResident?.resident_user_id || '').trim(),
      ].join('|');
      const nextResidentKey = [
        String(resident.resident_id || '').trim(),
        String(resident.resident_user_id || '').trim(),
      ].join('|');
      const residentChanged = nextResidentKey !== '' && nextResidentKey !== previousResidentKey;
      manualSelectedResident = resident;
      if (residentChanged) {
        manualBarangayIdPhotoCustomDataUrl = '';
        manualBarangayIdPhotoMode = 'resident';
      }
      manualResidentId.value = resident.resident_id || '';
      manualResidentUserId.value = resident.resident_user_id || '';
      manualLastName.value = resident.last_name || '';
      manualFirstName.value = resident.first_name || '';
      manualMiddleName.value = resident.middle_name || '';
      manualSuffix.value = resident.suffix || '';
      manualBirthdate.value = resident.birthdate || '';
      manualSyncBirthdateDropdownsFromValue();
      manualBirthdate.dispatchEvent(new Event('input', { bubbles: true }));
      manualBirthdate.dispatchEvent(new Event('change', { bubbles: true }));
      manualBirthplace.value = resident.birthplace || '';
      manualSex.value = resident.sex || '';
      manualCivilStatus.value = resident.civil_status || '';
      manualContactNumber.value = resident.contact_number || '';
      manualOccupation.value = resident.occupation || '';
      manualReligion.value = resident.religion || '';
      if (manualAddressLine) {
        const structuredAddress = [
          resident.unit_number,
          resident.house_number,
          resident.street_name,
          resident.phase_number,
          resident.subdivision,
        ].map((part) => String(part || '').trim()).filter(Boolean).join(', ');
        manualAddressLine.value = structuredAddress || resident.full_address || '';
        manualSetAreaNumber(resident.area_number || '');
        manualSyncStructuredAddress();
      } else {
        manualFullAddress.value = resident.full_address || '';
      }
      manualApplyResidentDynamicFields();

      manualSelectedResidentName.textContent = resident.full_name || 'Linked resident';
      manualSelectedResidentMeta.textContent = [
        resident.resident_id ? `Resident ID: ${resident.resident_id}` : '',
        resident.id_number ? `ID Number: ${resident.id_number}` : '',
        manualCurrentMode() === 'renewal' && resident.existing_barangay_id_number ? `Existing Barangay ID: ${resident.existing_barangay_id_number}` : '',
        resident.full_address || '',
      ].filter(Boolean).join(' • ');
      manualSelectedResidentCard?.classList.remove('d-none');
      manualResidentResultsWrap?.classList.add('d-none');
      manualResidentResults.innerHTML = '';
      manualResidentSearchHint?.classList.add('d-none');
      manualSyncSectorMembershipUi();
      manualMarkPreviewStale(true);
      manualUpdateSummary();
      manualSetAlert('Resident linked. Review the auto-filled details, edit if needed, then preview the document.', 'info');
    }

    function manualRenderResidentResults() {
      if (!manualResidentResults || !manualResidentResultsWrap) return;
      if (!manualResidentSearchResults.length) {
        manualResidentResultsWrap.classList.remove('d-none');
        manualResidentResults.innerHTML = '<div class="manual-search-empty">No registered residents matched that search. You can switch to walk-in mode if the resident is not yet registered.</div>';
        return;
      }

      manualResidentResultsWrap.classList.remove('d-none');
      manualResidentResults.innerHTML = manualResidentSearchResults.map((row, index) => `
        <div class="manual-resident-result" role="button" tabindex="0" data-manual-use-resident="${index}" aria-label="Select ${esc(row.full_name || 'resident')}">
          <div>
            <div class="manual-resident-result-name">${esc(row.full_name || 'Unnamed Resident')}</div>
            <p class="manual-resident-result-meta">
              ${esc([row.resident_id ? `Resident ID: ${row.resident_id}` : '', row.existing_barangay_id_number ? `Barangay ID: ${row.existing_barangay_id_number}` : '', row.id_number ? `ID Number: ${row.id_number}` : '', row.contact_number ? `Contact: ${row.contact_number}` : ''].filter(Boolean).join(' • '))}
            </p>
            <p class="manual-resident-result-meta">${esc(row.full_address || '-')}</p>
          </div>
        </div>
      `).join('');
    }

    async function manualSearchResidents() {
      const query = String(manualResidentSearchInput?.value || '').trim();
      if (!query) {
        manualSetAlert('Enter a Resident ID, resident name, or ID number to search.', 'warning');
        manualResidentSearchInput?.focus();
        return;
      }

      const token = ++manualResidentSearchToken;
      manualResidentSearchBtn.disabled = true;
      const originalLabel = manualResidentSearchBtn.innerHTML;
      manualResidentSearchBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Searching...';
      try {
        const data = await fetchJson(`${endpoint}?action=search_manual_residents&q=${encodeURIComponent(query)}`);
        if (token !== manualResidentSearchToken) {
          return;
        }
        manualResidentSearchResults = Array.isArray(data?.items) ? data.items : [];
        manualRenderResidentResults();
        manualResidentSearchHint?.classList.add('d-none');
      } catch (error) {
        manualResidentResultsWrap?.classList.remove('d-none');
        manualResidentResults.innerHTML = `<div class="manual-search-empty text-danger">${esc(error?.message || 'Resident search failed.')}</div>`;
      } finally {
        manualResidentSearchBtn.disabled = false;
        manualResidentSearchBtn.innerHTML = originalLabel;
      }
    }

    function manualBuildPayload() {
      const rawConfig = manualCurrentRawConfig();
      const config = manualCurrentConfig();
      if (!config) return null;

      const residentFullName = [manualFirstName?.value, manualMiddleName?.value, manualLastName?.value, manualSuffix?.value]
        .map((part) => String(part || '').trim())
        .filter(Boolean)
        .join(' ');
      const fullAddress = String(manualFullAddress?.value || '').trim();
      const resolvedDocumentType = config.kind === 'general_certification'
        ? manualResolvedDocumentLabel(rawConfig, config)
        : config.documentType;
      const payload = {
        document_type: resolvedDocumentType,
        resident_id: String(manualResidentId?.value || '').trim(),
        resident_user_id: String(manualResidentUserId?.value || '').trim(),
        resident_name: residentFullName,
        first_name: String(manualFirstName?.value || '').trim(),
        middle_name: String(manualMiddleName?.value || '').trim(),
        last_name: String(manualLastName?.value || '').trim(),
        suffix: String(manualSuffix?.value || '').trim(),
        birthdate: String(manualBirthdate?.value || '').trim(),
        sex: String(manualSex?.value || '').trim(),
        civil_status: String(manualCivilStatus?.value || '').trim(),
        contact_number: String(manualContactNumber?.value || '').trim(),
        birthplace: String(manualBirthplace?.value || '').trim(),
        occupation: String(manualOccupation?.value || '').trim(),
        religion: String(manualReligion?.value || '').trim(),
        full_address: fullAddress,
        full_address_display: fullAddress,
        address: fullAddress,
        sector_membership: manualCurrentSectorValues().join(', '),
      };
      if (config.kind === 'general_certification') {
        payload.manual_document_variant = config.label;
        payload.template_document_type = String(config.documentType || '').trim();
      }
      if (manualIsOtherDocumentSelection(rawConfig)) {
        payload.custom_document_title = String(manualOtherDocumentTitle?.value || '').trim();
        payload.other_document_fee = String(manualOtherDocumentFee?.value || '').trim();
        payload.other_document_template_id = String(config.id || '').trim();
        payload.other_document_template_document_type = String(config.documentType || '').trim();
        payload.other_document_template_kind = String(config.kind || '').trim();
        payload.other_document_template_label = String(config.label || '').trim();
        payload.template_document_type = String(config.documentType || '').trim();
      }
      if (manualAddressLine) {
        payload.address_line = String(manualAddressLine.value || '').trim();
        payload.area_number = String(manualAreaNumber?.value || '').trim();
        payload.barangay = String(manualBarangay?.value || '').trim();
        payload.municipality_city = String(manualCity?.value || '').trim();
        payload.province = String(manualProvince?.value || '').trim();
      }

      if (config.kind === 'barangay_id') {
        const requestMode = manualCurrentMode();
        payload.barangay_id_request_type = requestMode === 'renewal' ? 'renewal' : 'new';
        payload.request_purpose = requestMode === 'renewal' ? 'Barangay ID Renewal / Re-issue' : 'Barangay ID Application';
        payload.purpose = payload.request_purpose;
        if (requestMode === 'renewal' && manualSelectedResident?.existing_barangay_id_number) {
          payload.barangay_id_number = String(manualSelectedResident.existing_barangay_id_number).trim();
          payload.previous_barangay_id_number = payload.barangay_id_number;
        }
        const photo = manualCurrentBarangayIdPhoto();
        if (photo.previewUrl) {
          payload.id_picture_url = photo.previewUrl;
        }
        if (photo.path) {
          payload.id_picture_path = photo.path;
        }
      }

      const dynamicValues = new Map();
      manualDynamicFields?.querySelectorAll('[data-manual-field]').forEach((field) => {
        if (field.disabled) return;
        const key = String(field.getAttribute('data-manual-field') || '').trim();
        if (!key) return;
        if (field.type === 'checkbox') {
          if (!field.checked) return;
          const values = dynamicValues.get(key) || [];
          values.push(String(field.value || '').trim());
          dynamicValues.set(key, values);
          return;
        }
        const value = String(field.value || '').trim();
        const otherField = manualDynamicFields.querySelector(`[data-manual-other-for="${key}"]`);
        payload[key] = value === '__other__' ? String(otherField?.value || '').trim() : value;
      });
      dynamicValues.forEach((values, key) => {
        payload[key] = values.filter(Boolean).join(',');
      });
      if (config.kind === 'business_clearance') {
        const addressParts = [
          payload.business_address_line,
          payload.business_area_number,
          payload.business_barangay,
          payload.business_city,
          payload.business_province,
        ].map((part) => String(part || '').trim()).filter(Boolean);
        payload.business_full_address = addressParts.join(', ');
      }

      const purpose = config.kind === 'barangay_id' && manualCurrentMode() === 'renewal'
        ? 'Barangay ID Renewal / Re-issue'
        : (String(manualPurpose?.value || '').trim() || manualSuggestedPurpose(config));
      if (purpose) {
        payload.request_purpose = purpose;
        payload.purpose = purpose;
      }

      if (config.kind === 'cohabitation') {
        payload.cohabitation_variant = 'standard';
      } else if (config.kind === 'jail_visit') {
        payload.cohabitation_variant = 'relationship_jail_visit';
      }
      const validityKind = manualValidityKind(config);
      if (validityKind) {
        const validityDate = String(manualValidityDate?.value || '').trim();
        if (validityDate) {
          payload.document_validity = validityDate;
          if (validityKind === 'barangay_id') {
            payload.barangay_id_valid_until = validityDate;
            const matchedPreset = validityPresetByValue(validityKind, validityDate);
            if (matchedPreset?.amount) {
              payload.barangay_id_validity_years = String(matchedPreset.amount);
            }
          }
        }
      }

      return payload;
    }

    function manualPreviewStateBundle() {
      const rawConfig = manualCurrentRawConfig();
      const config = manualCurrentConfig();
      if (!config) {
        manualSetFieldInvalidState(manualDocumentType, true);
        if (manualIsOtherDocumentSelection(rawConfig)) {
          manualSetFieldInvalidState(manualOtherDocumentTemplate, true);
          throw new Error('Select the base certificate template for this Other Document first.');
        }
        throw new Error('Select a certificate or clearance type first.');
      }
      manualClearValidationState();
      if (!manualValidateRequiredFields()) {
        throw new Error('Complete the required form fields first.');
      }

      const photo = manualCurrentBarangayIdPhoto();
      if (config.kind === 'barangay_id' && !photo.previewUrl) {
        throw new Error('Barangay ID photo is required. Take a photo, then Crop and Save before previewing.');
      }
      const payload = manualBuildPayload();
      const photoDataUrl = config.kind === 'barangay_id' && photo.mode === 'custom'
        ? String(photo.dataUrl || '').trim()
        : '';
      const feeRows = manualCurrentFeeRows();
      const expectedStage = manualExpectedStage(config, feeRows);
      const displayTitle = manualResolvedDocumentLabel(rawConfig, config);
      const previewRow = {
        document_type: displayTitle,
        purpose: String(payload?.request_purpose || payload?.purpose || '').trim(),
        resident_name: String(payload?.resident_name || '').trim(),
        resident_id: String(payload?.resident_id || '').trim(),
        resident_user_id: String(payload?.resident_user_id || '').trim(),
        payload: { ...payload },
        stage: expectedStage.key,
        fee_amount: config.clearance && feeRows.length
          ? feeRows.reduce((sum, row) => sum + (Number(row.amount) || 0), 0)
          : (manualIsOtherDocumentSelection(rawConfig) ? manualResolvedDocumentFee() : null),
        submitted_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
        punong_signatory_name: String(barangayIdTemplateConfigCache?.punongSignatoryName || '').trim(),
        punong_signatory_title: String(barangayIdTemplateConfigCache?.punongSignatoryTitle || '').trim(),
        punong_signatory_signature_path: String(barangayIdTemplateConfigCache?.punongSignatorySignatureUrl || '').trim(),
        secretary_signatory_name: String(barangayIdTemplateConfigCache?.secretarySignatoryName || '').trim(),
        secretary_signatory_title: String(barangayIdTemplateConfigCache?.secretarySignatoryTitle || '').trim()
      };
      const residentProfile = manualSelectedResident ? { ...manualSelectedResident } : {};
      const signature = JSON.stringify({
        payload,
        feeRows,
        resident_id: previewRow.resident_id,
        resident_user_id: previewRow.resident_user_id,
        document_type: displayTitle,
        template_document_type: config.documentType,
        photo_key: photo.mode === 'custom'
          ? photoDataUrl
          : `${photo.mode}:${String(photo.path || photo.previewUrl || '').trim()}`
      });

      return {
        rawConfig,
        config,
        displayTitle,
        payload,
        feeRows,
        previewRow,
        residentProfile,
        photoDataUrl,
        signature
      };
    }

    function manualResetForm() {
      manualBarangayIdPhotoModal?.hide();
      manualForm.reset();
      manualRefreshBirthDays();
      manualSyncBirthdateFromDropdowns();
      manualSetAreaNumber('');
      manualSyncStructuredAddress();
      manualResidentSearchResults = [];
      manualSelectedResident = null;
      manualResidentId.value = '';
      manualResidentUserId.value = '';
      manualSelectedResidentName.textContent = 'Registered resident';
      manualSelectedResidentMeta.textContent = '';
      manualResidentResults.innerHTML = '';
      manualResidentResultsWrap?.classList.add('d-none');
      manualResidentSearchHint?.classList.remove('d-none');
      manualSelectedResidentCard?.classList.add('d-none');
      manualPreviewSignature = '';
      if (!isIdIssuanceTrackerView && manualDocumentInlinePreview) {
        manualDocumentInlinePreview.innerHTML = '<div class="manual-id-inline-preview-loading"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Document preview will appear here automatically on this step.</div>';
      }
      manualResetBarangayIdPhotoState();
      manualClearValidationState();
      if (manualSubmitBtn) {
        manualSubmitBtn.disabled = true;
      }
      manualRenderDocumentOptions();
      manualFeeWrap?.classList.add('d-none');
      manualFeeList.innerHTML = '';
      manualFeeTotal.textContent = 'PHP 0.00';
      manualSetSelectedSectorValues([]);
      manualSyncSectorMembershipUi();
      if (manualPurpose) manualPurpose.dataset.auto = '1';
      manualApplyContextDocumentSelection();
      manualSetAlert('', 'warning');
      manualToggleResidentLookup();
      manualUpdateSummary();
    }

    function manualApplyLaunchSelection() {
      if (launchTab !== 'manual' || !launchManualDocument || !manualDocumentType) {
        return;
      }
      if (String(manualDocumentType.value || '').trim().toLowerCase() === launchManualDocument) {
        return;
      }
      const hasOption = Array.from(manualDocumentType.options).some(
        (option) => String(option.value || '').toLowerCase() === launchManualDocument
      );
      if (!hasOption) {
        return;
      }
      manualDocumentType.value = launchManualDocument;
      manualDocumentType.dispatchEvent(new Event('change', { bubbles: true }));
    }

    manualResidentSearchBtn?.addEventListener('click', manualSearchResidents);
    manualResidentSearchInput?.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        manualSearchResidents();
      }
    });

    manualResidentResults?.addEventListener('click', (event) => {
      const trigger = event.target.closest('[data-manual-use-resident]');
      if (!trigger) return;
      const index = Number(trigger.getAttribute('data-manual-use-resident') || '-1');
      if (!Number.isInteger(index) || index < 0 || index >= manualResidentSearchResults.length) return;
      manualFillFormFromResident(manualResidentSearchResults[index]);
    });
    manualResidentResults?.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' && event.key !== ' ') return;
      const trigger = event.target.closest('[data-manual-use-resident]');
      if (!trigger) return;
      event.preventDefault();
      const index = Number(trigger.getAttribute('data-manual-use-resident') || '-1');
      if (!Number.isInteger(index) || index < 0 || index >= manualResidentSearchResults.length) return;
      manualFillFormFromResident(manualResidentSearchResults[index]);
    });

    manualClearSelectedResidentBtn?.addEventListener('click', () => {
      manualSelectedResident = null;
      manualResidentId.value = '';
      manualResidentUserId.value = '';
      manualClearLinkedResidentFields();
      manualSelectedResidentCard?.classList.add('d-none');
      manualSelectedResidentName.textContent = 'Registered resident';
      manualSelectedResidentMeta.textContent = '';
      manualSyncBarangayIdPhotoResidentSource();
      manualSyncSectorMembershipUi();
      manualMarkPreviewStale(true);
      manualUpdateSummary();
      manualSetAlert('Resident link cleared. You can keep encoding this as a walk-in request or search again.', 'info');
    });

    manualResidentModeExisting?.addEventListener('change', manualToggleResidentLookup);
    manualResidentModeWalkin?.addEventListener('change', manualToggleResidentLookup);
    manualResidentModeRenewal?.addEventListener('change', manualToggleResidentLookup);

    manualDocumentType?.addEventListener('change', () => {
      manualRenderDynamicFields();
      manualApplySuggestedPurpose();
      manualSyncPurposePreset();
      manualMarkPreviewStale(true);
      manualSetAlert('Document form updated. Review the fields, then preview the document again before submitting.', 'info');
    });

    manualOtherDocumentTemplate?.addEventListener('change', () => {
      manualRenderDynamicFields();
      manualApplySuggestedPurpose();
      manualMarkPreviewStale(true);
    });
    manualOtherDocumentTitle?.addEventListener('input', () => {
      manualMarkPreviewStale(true);
      manualUpdateSummary();
    });
    manualOtherDocumentFee?.addEventListener('input', () => {
      manualMarkPreviewStale(true);
      manualUpdateSummary();
    });
    manualPurposePreset?.addEventListener('change', () => {
      manualSyncPurposePreset();
      manualApplySuggestedPurpose();
      manualMarkPreviewStale(true);
    });

    manualPurpose?.addEventListener('input', () => {
      manualPurpose.dataset.auto = manualPurpose.value.trim() ? '0' : '1';
      manualMarkPreviewStale(true);
    });

    manualDynamicFields?.addEventListener('input', (event) => {
      const field = event.target.closest('[data-manual-field], [data-manual-other-for]');
      if (!field) return;
      manualSyncIndigencyRecipientFields();
      manualMarkPreviewStale(true);
    });

    manualDynamicFields?.addEventListener('change', (event) => {
      const field = event.target.closest('[data-manual-field]');
      if (!field) return;
      const key = String(field.getAttribute('data-manual-field') || '');
      const otherField = manualDynamicFields.querySelector(`[data-manual-other-for="${key}"]`);
      if (otherField) {
        const showOther = String(field.value || '') === '__other__';
        otherField.classList.toggle('d-none', !showOther);
        otherField.required = showOther;
        if (!showOther) otherField.value = '';
        if (showOther) otherField.focus();
      }
      if (key === 'application_type') {
        manualApplySuggestedPurpose();
        manualSyncConditionalFields();
      }
      manualSyncIndigencyRecipientFields();
      manualMarkPreviewStale(true);
    });

    manualForm?.addEventListener('click', (event) => {
      const trigger = event.target.closest('[data-manual-photo-action]');
      if (!trigger) return;
      const action = String(trigger.getAttribute('data-manual-photo-action') || '').trim();
      if (!action) return;
      event.preventDefault();

      if (action === 'take') {
        manualOpenBarangayIdPhotoModal('camera');
        return;
      }
      if (action === 'adjust') {
        manualOpenBarangayIdPhotoModal('crop');
        return;
      }
      if (action === 'linked') {
        if (!manualBarangayIdHasResidentPhoto()) {
          manualSetAlert('No linked resident photo is available yet for this record.', 'warning');
          return;
        }
        manualStartBarangayIdCropFromSource(
          manualBarangayIdPhotoResidentUrl || manualBarangayIdPhotoResidentPath,
          'Adjust the linked resident photo inside the square frame, then save the crop.'
        );
        return;
      }
      if (action === 'remove') {
        const current = manualCurrentBarangayIdPhoto();
        if (current.mode === 'custom') {
          manualBarangayIdPhotoCustomDataUrl = '';
          manualBarangayIdPhotoMode = manualBarangayIdHasResidentPhoto() ? 'resident' : 'none';
          manualUpdateBarangayIdPhotoField();
          manualMarkPreviewStale(true);
          manualSetAlert(
            manualBarangayIdPhotoMode === 'resident'
              ? 'Captured photo removed. The linked resident photo is active again.'
              : 'Captured photo removed. Take a new photo before previewing the Barangay ID again.',
            'info'
          );
          return;
        }
        manualBarangayIdPhotoMode = 'none';
        manualUpdateBarangayIdPhotoField();
        manualMarkPreviewStale(true);
        manualSetAlert('Linked resident photo removed. Capture a new photo or use the linked photo again before previewing the Barangay ID.', 'info');
      }
    });

    manualFeeList?.addEventListener('input', () => {
      manualUpdateFeeTotal();
      manualMarkPreviewStale(true);
    });
    manualFeeList?.addEventListener('change', () => {
      manualUpdateFeeTotal();
      manualMarkPreviewStale(true);
    });
    manualSectorMembershipWrap?.addEventListener('change', () => {
      manualSyncSectorMembershipUi();
      manualMarkPreviewStale(true);
    });
    manualAddressLine?.addEventListener('input', () => {
      manualSyncStructuredAddress();
      manualSetFieldInvalidState(manualAddressLine, !manualAddressLine.checkValidity());
      manualMarkPreviewStale(true);
    });
    [manualBirthMonth, manualBirthDay, manualBirthYear].forEach((field) => {
      field?.addEventListener('change', () => {
        manualSyncBirthdateFromDropdowns();
        manualSetFieldInvalidState(field, !field.checkValidity());
        manualMarkPreviewStale(true);
      });
    });
    const manualOpenAreaGuide = () => {
      manualAreaNumber?.blur();
      manualAreaNumberModal?.show();
    };
    manualAreaNumber?.addEventListener('click', manualOpenAreaGuide);
    manualAreaNumber?.addEventListener('focus', manualOpenAreaGuide);
    manualAreaOptions.forEach((option) => {
      option.addEventListener('click', () => {
        manualSetAreaNumber(option.dataset.manualAreaOption || '');
        manualSetFieldInvalidState(manualAreaNumber, !manualAreaNumber.checkValidity());
        manualMarkPreviewStale(true);
        manualAreaNumberModal?.hide();
      });
    });
    manualValidityDate?.addEventListener('change', () => {
      manualMarkPreviewStale(true);
      manualUpdateSummary();
      const validityKind = manualValidityKind(manualCurrentConfig());
      if (validityKind === 'barangay_id') {
        manualSetAlert('Barangay ID validity updated. Preview the ID again before submitting.', 'info');
      } else if (validityKind === 'certificate') {
        manualSetAlert('Certificate validity updated. Preview the document again before submitting.', 'info');
      }
    });
    manualDocumentType?.addEventListener('change', () => {
      manualSetFieldInvalidState(manualDocumentType, !manualDocumentType.checkValidity());
    });
    manualDynamicFields?.addEventListener('input', (event) => {
      const field = event.target?.closest?.('[data-manual-field]');
      if (!field) return;
      manualSetFieldInvalidState(field, !field.checkValidity());
      manualMarkPreviewStale(true);
    });
    manualDynamicFields?.addEventListener('change', (event) => {
      const field = event.target?.closest?.('[data-manual-field]');
      if (!field) return;
      manualSetFieldInvalidState(field, !field.checkValidity());
      manualMarkPreviewStale(true);
    });

    [
      manualLastName,
      manualFirstName,
      manualMiddleName,
      manualSuffix,
      manualBirthdate,
      manualSex,
      manualCivilStatus,
      manualContactNumber,
      manualBirthplace,
      manualOccupation,
      manualReligion,
      manualFullAddress
    ].forEach((field) => {
      field?.addEventListener('input', () => {
        manualSetFieldInvalidState(field, !field.checkValidity());
        manualMarkPreviewStale(true);
      });
      field?.addEventListener('change', () => {
        manualSetFieldInvalidState(field, !field.checkValidity());
        manualMarkPreviewStale(true);
      });
    });

    manualBarangayIdStartCameraBtn?.addEventListener('click', () => {
      manualStartBarangayIdCamera();
    });

    manualBarangayIdCameraSelect?.addEventListener('change', () => {
      const nextCameraId = String(manualBarangayIdCameraSelect.value || '').trim();
      manualBarangayIdSelectedCameraId = nextCameraId;
      if (!nextCameraId) {
        return;
      }
      manualStartBarangayIdCamera(nextCameraId);
    });

    manualBarangayIdCapturePhotoBtn?.addEventListener('click', () => {
      manualCaptureBarangayIdPhoto();
    });

    manualBarangayIdRetakePhotoBtn?.addEventListener('click', () => {
      manualOpenBarangayIdPhotoModal('camera');
    });

    manualBarangayIdUseLinkedPhotoBtn?.addEventListener('click', () => {
      if (!manualBarangayIdHasResidentPhoto()) {
        manualSetBarangayIdPhotoStatus('No linked resident photo is available yet for this record.', 'warning');
        return;
      }
      manualStartBarangayIdCropFromSource(
        manualBarangayIdPhotoResidentUrl || manualBarangayIdPhotoResidentPath,
        'Adjust the linked resident photo inside the square frame, then save the crop.'
      );
    });

    manualBarangayIdSavePhotoBtn?.addEventListener('click', () => {
      manualSaveBarangayIdCrop();
    });

    manualBarangayIdCropImage?.addEventListener('load', () => {
      manualBarangayIdCropEmpty?.classList.add('d-none');
      if (manualBarangayIdZoomRange) {
        manualBarangayIdZoomRange.disabled = false;
      }
      if (manualBarangayIdSavePhotoBtn) {
        manualBarangayIdSavePhotoBtn.disabled = false;
      }
      requestAnimationFrame(() => {
        manualSeedBarangayIdCropState();
      });
    });

    manualBarangayIdCropImage?.addEventListener('error', () => {
      manualBarangayIdCropEmpty?.classList.remove('d-none');
      if (manualBarangayIdZoomRange) {
        manualBarangayIdZoomRange.disabled = true;
      }
      if (manualBarangayIdSavePhotoBtn) {
        manualBarangayIdSavePhotoBtn.disabled = true;
      }
      manualSetBarangayIdPhotoStatus('The selected photo could not be loaded for cropping. Try again.', 'danger');
    });

    manualBarangayIdZoomRange?.addEventListener('input', (event) => {
      const frame = manualComputeBarangayIdCropFrame();
      if (!frame || !manualBarangayIdCropState.baseScale) return;
      const multiplier = Math.max(1, Number(event.target?.value || 100) / 100);
      const nextScale = Math.min(
        manualBarangayIdCropState.maxScale,
        Math.max(manualBarangayIdCropState.minScale, manualBarangayIdCropState.baseScale * multiplier)
      );
      const focusX = frame.left + frame.size / 2;
      const focusY = frame.top + frame.size / 2;
      const imageFocusX = (focusX - manualBarangayIdCropState.x) / manualBarangayIdCropState.scale;
      const imageFocusY = (focusY - manualBarangayIdCropState.y) / manualBarangayIdCropState.scale;
      manualBarangayIdCropState.scale = nextScale;
      manualBarangayIdCropState.x = focusX - imageFocusX * nextScale;
      manualBarangayIdCropState.y = focusY - imageFocusY * nextScale;
      manualApplyBarangayIdCropTransform();
    });

    manualBarangayIdCropWorkspace?.addEventListener('pointerdown', (event) => {
      if (!manualBarangayIdCropSourceUrl || !manualBarangayIdCropImage?.naturalWidth) return;
      event.preventDefault();
      manualBarangayIdDragState = {
        active: true,
        startX: event.clientX,
        startY: event.clientY,
        originX: manualBarangayIdCropState.x,
        originY: manualBarangayIdCropState.y,
        pointerId: event.pointerId,
      };
      manualBarangayIdCropWorkspace.classList.add('is-dragging');
      manualBarangayIdCropWorkspace.setPointerCapture?.(event.pointerId);
    });

    manualBarangayIdCropWorkspace?.addEventListener('pointermove', (event) => {
      if (!manualBarangayIdDragState.active) return;
      if (manualBarangayIdDragState.pointerId !== null && event.pointerId !== manualBarangayIdDragState.pointerId) return;
      event.preventDefault();
      manualBarangayIdCropState.x = manualBarangayIdDragState.originX + (event.clientX - manualBarangayIdDragState.startX);
      manualBarangayIdCropState.y = manualBarangayIdDragState.originY + (event.clientY - manualBarangayIdDragState.startY);
      manualApplyBarangayIdCropTransform();
    });

    const manualEndBarangayIdCropDrag = (event) => {
      if (!manualBarangayIdDragState.active) return;
      if (manualBarangayIdDragState.pointerId !== null && event?.pointerId !== undefined && event.pointerId !== manualBarangayIdDragState.pointerId) {
        return;
      }
      manualBarangayIdDragState = {
        active: false,
        startX: 0,
        startY: 0,
        originX: manualBarangayIdCropState.x,
        originY: manualBarangayIdCropState.y,
        pointerId: null,
      };
      manualBarangayIdCropWorkspace?.classList.remove('is-dragging');
      if (event?.pointerId !== undefined) {
        try {
          manualBarangayIdCropWorkspace?.releasePointerCapture?.(event.pointerId);
        } catch (_) {
          // Pointer capture may already be released.
        }
      }
    };

    manualBarangayIdCropWorkspace?.addEventListener('pointerup', manualEndBarangayIdCropDrag);
    manualBarangayIdCropWorkspace?.addEventListener('pointercancel', manualEndBarangayIdCropDrag);
    manualBarangayIdCropWorkspace?.addEventListener('lostpointercapture', manualEndBarangayIdCropDrag);

    manualBarangayIdPhotoModalEl?.addEventListener('hidden.bs.modal', () => {
      manualStopBarangayIdCamera();
      manualSetBarangayIdPhotoStatus('', 'info');
      manualEndBarangayIdCropDrag();
    });

    navigator.mediaDevices?.addEventListener?.('devicechange', () => {
      void manualRefreshBarangayIdCameraOptions(manualCurrentBarangayIdCameraDeviceId() || manualBarangayIdSelectedCameraId);
    });

    manualPreviewBtn?.addEventListener('click', async () => {
      if (!isIdIssuanceTrackerView && manualDocumentInlinePreview) {
        await manualRenderInlineDocumentPreview();
        return;
      }
    });

    manualForm?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const originalSubmitLabel = manualSubmitBtn?.innerHTML || 'Submit Manual Issuance';
      try {
        const bundle = manualPreviewStateBundle();
        if (manualCurrentMode() !== 'walkin' && !String(bundle.payload.resident_id || bundle.payload.resident_user_id || '').trim()) {
          throw new Error('Search and link a registered resident first, or switch the request to walk-in mode.');
        }
        if (bundle.config.clearance && !bundle.feeRows.length && !manualHasExemptSector(manualCurrentSectorValues())) {
          throw new Error('Tag at least one clearance fee before submitting this manual issuance request.');
        }
        if (bundle.signature !== manualPreviewSignature) {
          throw new Error('Preview the latest changes before submitting this manual issuance request.');
        }

        manualSubmitBtn.disabled = true;
        if (manualPreviewBtn) manualPreviewBtn.disabled = true;
        if (manualResetBtn) manualResetBtn.disabled = true;
        manualSubmitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Submitting...';

        const body = new FormData();
        body.append('action', 'create_manual_request');
        const submitPayload = { ...bundle.payload };
        if (/^data:/i.test(String(submitPayload.id_picture_url || '').trim())) {
          delete submitPayload.id_picture_url;
        }
        body.append('payload', JSON.stringify(submitPayload));
        body.append('fees', JSON.stringify(bundle.feeRows));
        if (bundle.photoDataUrl) {
          body.append('id_picture_data_url', bundle.photoDataUrl);
        }
        if (bundle.payload.resident_id) {
          body.append('resident_id', String(bundle.payload.resident_id));
        }
        if (bundle.payload.resident_user_id) {
          body.append('resident_user_id', String(bundle.payload.resident_user_id));
        }

        const data = await fetchJson(endpoint, { method: 'POST', body });
        viewModal?.hide();
        manualResetForm();
        manualSetAlert('', 'warning');
        document.getElementById('tabDocRequests')?.click();
        document.querySelector('[data-stage-filter=""]')?.click();
        if (searchInput) {
          searchInput.value = String(data?.request_id || '').trim();
        }
        await load({ force: true });
        searchInput?.dispatchEvent(new Event('input', { bubbles: true }));
        alert(`Manual issuance request ${data?.request_id || ''} created. Next step: ${data?.stage_label || 'Review the tracker list.'}`);
      } catch (error) {
        manualSetAlert(error?.message || 'Failed to submit the manual issuance request.', 'danger');
      } finally {
        if (manualSubmitBtn) {
          manualSubmitBtn.innerHTML = originalSubmitLabel;
        }
        if (manualPreviewBtn) manualPreviewBtn.disabled = false;
        if (manualResetBtn) manualResetBtn.disabled = false;
        if (manualSubmitBtn) manualSubmitBtn.disabled = !manualPreviewSignature;
      }
    });

    manualResetBtn?.addEventListener('click', () => {
      manualResetForm();
      manualShowIdWizardStep(1, true);
      manualSetAlert('Manual issuance form cleared.', 'info');
    });

    manualIdWizardBack?.addEventListener('click', () => {
      manualShowIdWizardStep(manualIdWizardCurrentStep - 1, true);
    });
    manualIdWizardNext?.addEventListener('click', () => {
      if (!manualValidateIdWizardStep()) return;
      manualShowIdWizardStep(manualIdWizardCurrentStep + 1, true);
    });

    manualRenderDocumentOptions();
    manualResetForm();
    manualApplyLaunchSelection();
    manualShowIdWizardStep(1);
  })();

  load();
  warmFeeTypeCatalogCache();

  // ── Fee Change Requests sub-navbar ────────────────────────────────────────
  (function initFeeChangePanel() {
    const tabDocRequests = document.getElementById('tabDocRequests');
    const tabManualIssuance = document.getElementById('tabManualIssuance');
    const tabFeeRequests = document.getElementById('tabFeeRequests');
    const docRequestsPanel = document.getElementById('docRequestsPanel');
    const manualIssuancePanel = document.getElementById('manualIssuancePanel');
    const feeChangePanel   = document.getElementById('feeChangePanel');
    if (!tabDocRequests || !feeChangePanel) return; // not on the certificate tracker page

    const API = (function () {
      const base = window.location.pathname.replace(/\/[^/]*$/, '');
      return base.replace(/\/Admin-End\/Certificates$/, '') + '/PhpFiles/Admin-End/documentRequestWorkflow.php';
    })();

    function esc(v) {
      return String(v ?? '').replace(/[&<>"']/g, m =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    }

    // ── Page tab switching ──────────────────────────────────────────────────
    function showDocTab() {
      tabDocRequests.classList.add('active');
      tabManualIssuance?.classList.remove('active');
      tabFeeRequests?.classList.remove('active');
      docRequestsPanel.classList.remove('d-none');
      manualIssuancePanel?.classList.add('d-none');
      feeChangePanel.classList.add('d-none');
    }
    function showManualTab() {
      tabManualIssuance?.classList.add('active');
      tabDocRequests.classList.remove('active');
      tabFeeRequests?.classList.remove('active');
      manualIssuancePanel?.classList.remove('d-none');
      docRequestsPanel.classList.add('d-none');
      feeChangePanel.classList.add('d-none');
    }
    function showFeeTab() {
      tabFeeRequests?.classList.add('active');
      tabManualIssuance?.classList.remove('active');
      tabDocRequests.classList.remove('active');
      feeChangePanel.classList.remove('d-none');
      manualIssuancePanel?.classList.add('d-none');
      docRequestsPanel.classList.add('d-none');
      loadActiveFeeSubPanel();
    }
    tabDocRequests.addEventListener('click', showDocTab);
    tabManualIssuance?.addEventListener('click', showManualTab);
    tabFeeRequests?.addEventListener('click', showFeeTab);

    // ── Sub-tab switching ───────────────────────────────────────────────────
    const subTabAddFeeType  = document.getElementById('subTabAddFeeType');
    const subTabEditPrice   = document.getElementById('subTabEditPrice');
    const subTabMyRequests  = document.getElementById('subTabMyRequests');
    const fcrAddPanel       = document.getElementById('fcrAddPanel');
    const fcrEditPanel      = document.getElementById('fcrEditPanel');
    const fcrListPanel      = document.getElementById('fcrListPanel');
    let activeSubTab = 'add';
    let editCatalogLoaded = false;

    function setSubTab(tab) {
      activeSubTab = tab;
      subTabAddFeeType.classList.toggle('active', tab === 'add');
      subTabEditPrice.classList.toggle('active', tab === 'edit');
      subTabMyRequests.classList.toggle('active', tab === 'list');
      fcrAddPanel.classList.toggle('d-none', tab !== 'add');
      fcrEditPanel.classList.toggle('d-none', tab !== 'edit');
      fcrListPanel.classList.toggle('d-none', tab !== 'list');
    }
    subTabAddFeeType.addEventListener('click', () => setSubTab('add'));
    subTabEditPrice.addEventListener('click', () => {
      setSubTab('edit');
      if (!editCatalogLoaded) { editCatalogLoaded = true; loadEditCatalog(); }
    });
    subTabMyRequests.addEventListener('click', () => {
      setSubTab('list');
      loadFcrList();
    });

    function loadActiveFeeSubPanel() {
      if (activeSubTab === 'edit' && !editCatalogLoaded) { editCatalogLoaded = true; loadEditCatalog(); }
      if (activeSubTab === 'list') loadFcrList();
    }

    const feeSettingsScope = String(launchParams.get('fee_scope') || '').toLowerCase();
    if (feeSettingsScope === 'issuance') {
      subTabAddFeeType.classList.add('d-none');
      setSubTab('edit');
    }

    if (launchTab === 'manual') {
      showManualTab();
    } else if (launchTab === 'fees') {
      showFeeTab();
    } else {
      showDocTab();
    }

    function getFeeCatalogSource() {
      if (feeSettingsScope === 'issuance') return 'general';
      if (feeSettingsScope === 'monitoring') return 'clearance';
      const activeDocumentFilter = isIdIssuanceTrackerView && !isFinancePaymentsPage
        ? 'Barangay ID'
        : currentDocumentTypeFilter;
      return activeDocumentFilter === '__certificates__' ? 'general' : 'clearance';
    }

    // ── Add New Fee Type ────────────────────────────────────────────────────
    function setFeeRequestAlert(elementId, message = '', tone = 'danger') {
      const el = document.getElementById(elementId);
      if (!el) return;
      el.classList.remove('alert-danger', 'alert-success', 'alert-warning', 'alert-info');
      if (!message) {
        el.textContent = '';
        el.classList.add('d-none');
        return;
      }

      const toneClass = {
        danger: 'alert-danger',
        success: 'alert-success',
        warning: 'alert-warning',
        info: 'alert-info'
      }[tone] || 'alert-danger';

      el.textContent = message;
      el.classList.add(toneClass);
      el.classList.remove('d-none');
    }

    function showAddError(msg) {
      setFeeRequestAlert('fcrAddError', msg, 'danger');
      if (msg) {
        setFeeRequestAlert('fcrAddSuccess', '');
      }
    }

    function showAddSuccess(msg) {
      setFeeRequestAlert('fcrAddSuccess', msg, 'success');
      if (msg) {
        setFeeRequestAlert('fcrAddError', '');
      }
    }

    document.getElementById('fcrAddSubmitBtn').addEventListener('click', async () => {
      const name   = document.getElementById('fcrAddName').value.trim();
      const amount = parseFloat(document.getElementById('fcrAddAmount').value) || 0;
      const notes  = document.getElementById('fcrAddNotes').value.trim();
      showAddError('');
      showAddSuccess('');
      if (!name) { showAddError('Fee name is required.'); document.getElementById('fcrAddName').focus(); return; }

      const btn = document.getElementById('fcrAddSubmitBtn');
      btn.disabled = true;
      const orig = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Submitting…';
      try {
        const fd = new FormData();
        fd.append('action', 'submit_fee_change_request');
        fd.append('request_type', 'add_type');
        fd.append('proposed_fee_name', name);
        fd.append('proposed_amount', String(amount));
        fd.append('notes', notes);
        const res  = await fetch(API, { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Submit failed.');
        document.getElementById('fcrAddName').value   = '';
        document.getElementById('fcrAddAmount').value = '0.00';
        document.getElementById('fcrAddNotes').value  = '';
        showAddError('');
        showAddSuccess('Request submitted successfully. Finance will review it shortly.');
      } catch (e) { showAddError(e.message); }
      finally { btn.disabled = false; btn.innerHTML = orig; }
    });

    // ── Edit Price Catalog ──────────────────────────────────────────────────
    async function loadEditCatalog() {
      const tbody = document.getElementById('fcrEditCatalogBody');
      if (!tbody) return;
      tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-1"></i>Loading…</td></tr>';
      try {
        const action = getFeeCatalogSource() === 'general' ? 'list_general_fee_catalog' : 'list_fee_types';
        const res  = await fetch(`${API}?action=${encodeURIComponent(action)}`);
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Failed to load.');
        renderEditCatalog(data.fee_types || []);
      } catch (e) {
        tbody.innerHTML = `<tr><td colspan="4" class="text-danger text-center py-3">${esc(e.message)}</td></tr>`;
      }
    }

    function renderEditCatalog(rows) {
      const tbody = document.getElementById('fcrEditCatalogBody');
      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-muted text-center py-3">No fee types in catalog yet.</td></tr>';
        return;
      }
      tbody.innerHTML = rows.map(ft => `
        <tr>
          <td class="fw-semibold">${esc(ft.fee_name)}</td>
          <td>₱${Number(ft.default_amount).toFixed(2)}</td>
          <td><span class="badge ${ft.status === 'approved' ? 'bg-success' : 'bg-secondary'}">${ft.status === 'approved' ? 'Active' : esc(ft.status)}</span></td>
          <td class="text-end">
            <button
              type="button"
              class="btn btn-sm btn-warning py-0 px-2 fcr-edit-select-btn"
              data-fee-type-id="${esc(ft.fee_type_id)}"
              data-fee-name="${esc(ft.fee_name)}"
              data-fee-amount="${esc(Number(ft.default_amount).toFixed(2))}">
              Request Edit
            </button>
          </td>
        </tr>`).join('');
    }

    window.fcrSelectEditFee = function(id, name, amount) {
      document.getElementById('fcrEditFeeTypeId').value    = id;
      document.getElementById('fcrEditFeeName').value      = name;
      document.getElementById('fcrEditCurrentAmount').value = Number(amount).toFixed(2);
      document.getElementById('fcrEditProposedAmount').value = Number(amount).toFixed(2);
      document.getElementById('fcrEditNotes').value         = '';
      showEditError('');
      showEditSuccess('');
      const modalElement = document.getElementById('fcrEditModal');
      const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
      modalElement.addEventListener('shown.bs.modal', () => {
        document.getElementById('fcrEditProposedAmount').focus();
      }, { once: true });
      modal.show();
    };

    document.getElementById('fcrEditCatalogBody')?.addEventListener('click', (event) => {
      const trigger = event.target.closest('.fcr-edit-select-btn');
      if (!trigger) return;
      window.fcrSelectEditFee(
        trigger.getAttribute('data-fee-type-id') || '',
        trigger.getAttribute('data-fee-name') || '',
        trigger.getAttribute('data-fee-amount') || '0'
      );
    });

    document.getElementById('fcrEditCancelBtn').addEventListener('click', () => {
      showEditError('');
    });

    document.getElementById('fcrEditRefreshBtn').addEventListener('click', () => {
      editCatalogLoaded = true;
      loadEditCatalog();
    });

    function showEditError(msg) {
      setFeeRequestAlert('fcrEditError', msg, 'danger');
      if (msg) {
        setFeeRequestAlert('fcrEditSuccess', '');
      }
    }

    function showEditSuccess(msg) {
      setFeeRequestAlert('fcrEditSuccess', msg, 'success');
      if (msg) {
        setFeeRequestAlert('fcrEditError', '');
      }
    }

    document.getElementById('fcrEditSubmitBtn').addEventListener('click', async () => {
      const id       = document.getElementById('fcrEditFeeTypeId').value;
      const name     = document.getElementById('fcrEditFeeName').value;
      const current  = document.getElementById('fcrEditCurrentAmount').value;
      const proposed = parseFloat(document.getElementById('fcrEditProposedAmount').value);
      const notes    = document.getElementById('fcrEditNotes').value.trim();
      showEditError('');
      showEditSuccess('');
      if (!id) { showEditError('No fee type selected.'); return; }
      if (isNaN(proposed) || proposed < 0) { showEditError('Please enter a valid proposed amount.'); return; }

      const btn = document.getElementById('fcrEditSubmitBtn');
      btn.disabled = true;
      const orig = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Submitting…';
      try {
        const fd = new FormData();
        fd.append('action', 'submit_fee_change_request');
        fd.append('request_type', 'edit_price');
        fd.append('fee_catalog_source', getFeeCatalogSource());
        fd.append('fee_type_id', id);
        fd.append('proposed_fee_name', name);
        fd.append('current_amount', current);
        fd.append('proposed_amount', String(proposed));
        fd.append('notes', notes);
        const res  = await fetch(API, { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Submit failed.');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('fcrEditModal')).hide();
        showEditError('');
        showEditSuccess('Price edit request submitted. Finance will review it.');
      } catch (e) { showEditError(e.message); }
      finally { btn.disabled = false; btn.innerHTML = orig; }
    });

    // ── Submitted Requests List ─────────────────────────────────────────────
    async function loadFcrList() {
      const tbody = document.getElementById('fcrListBody');
      if (!tbody) return;
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-1"></i>Loading…</td></tr>';
      try {
        const res  = await fetch(`${API}?action=list_fee_change_requests`);
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Failed to load.');
        renderFcrList(data.requests || data.fee_types || []);
      } catch (e) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-danger text-center py-3">${esc(e.message)}</td></tr>`;
      }
    }

    function renderFcrList(rows) {
      const tbody = document.getElementById('fcrListBody');
      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-muted text-center py-3">No requests submitted yet.</td></tr>';
        return;
      }
      const statusBadge = s => {
        const map = { pending: 'bg-warning text-dark', approved: 'bg-success', rejected: 'bg-danger' };
        return `<span class="badge ${map[s] || 'bg-secondary'}">${esc(s)}</span>`;
      };
      tbody.innerHTML = rows.map(r => `
        <tr>
          <td><span class="badge bg-secondary">${r.change_type === 'new_type' ? 'New Type' : 'Price Edit'}</span></td>
          <td class="fw-semibold">${esc(r.fee_name)}</td>
          <td>₱${Number(r.proposed_amount || r.default_amount).toFixed(2)}</td>
          <td class="small text-muted">${esc(r.notes || '—')}</td>
          <td>${statusBadge(r.status)}</td>
          <td class="small text-muted">${esc(r.updated_at || r.created_at || '')}</td>
          <td class="text-end">
            ${r.status === 'pending'
              ? `<button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="fcrCancelRequest(${r.fee_type_id})">Cancel</button>`
              : '—'}
          </td>
        </tr>`).join('');
    }

    function renderFcrList(rows) {
      const tbody = document.getElementById('fcrListBody');
      if (!tbody) return;
      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-muted text-center py-3">No requests submitted yet.</td></tr>';
        return;
      }

      const statusBadge = s => {
        const token = String(s || '').trim().toLowerCase();
        const map = {
          pending: 'pending',
          approved: 'approved',
          rejected: 'denied',
          cancelled: 'archived',
          canceled: 'archived'
        };
        const labelMap = {
          pending: 'Pending',
          approved: 'Approved',
          rejected: 'Rejected',
          cancelled: 'Cancelled',
          canceled: 'Cancelled'
        };
        const pillClass = map[token] || 'archived';
        const label = labelMap[token] || token.replace(/_/g, ' ') || 'Unknown';
        return `<span class="status-pill ${pillClass}">${esc(label)}</span>`;
      };

      tbody.innerHTML = rows.map(r => `
        <tr>
          <td><span class="badge bg-secondary">${r.change_type === 'new_type' ? 'New Type' : 'Price Edit'}</span></td>
          <td class="fw-semibold">${esc(r.fee_name)}</td>
          <td>&#8369;${Number(r.proposed_amount || r.default_amount).toFixed(2)}</td>
          <td class="small text-muted">${esc(r.notes || '-')}</td>
          <td>${statusBadge(r.status)}</td>
          <td class="small text-muted">${esc(r.updated_at || r.created_at || '')}</td>
          <td class="text-end">
            ${String(r.status || '').trim().toLowerCase() === 'pending'
              ? `<div class="compact-table-actions justify-content-end"><button class="btn btn-sm compact-table-btn btn-danger" onclick="fcrOpenCancelModal(${r.fee_type_id})">Cancel</button></div>`
              : '-'}
          </td>
        </tr>`).join('');
    }

    const fcrCancelModalEl = document.getElementById('fcrCancelModal');
    const fcrCancelModalError = document.getElementById('fcrCancelModalError');
    const fcrCancelModalConfirmBtn = document.getElementById('fcrCancelModalConfirmBtn');
    const fcrCancelModalBackBtn = document.getElementById('fcrCancelModalBackBtn');
    const fcrCancelModal = (fcrCancelModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal)
      ? new bootstrap.Modal(fcrCancelModalEl)
      : null;
    let pendingFcrCancelId = null;

    function setFcrCancelModalError(message) {
      if (!fcrCancelModalError) return;
      fcrCancelModalError.textContent = message || '';
      fcrCancelModalError.classList.toggle('d-none', !message);
    }

    function setFcrCancelModalBusy(isBusy) {
      if (fcrCancelModalConfirmBtn) {
        fcrCancelModalConfirmBtn.disabled = isBusy;
        fcrCancelModalConfirmBtn.innerHTML = isBusy
          ? '<i class="fas fa-spinner fa-spin me-1"></i>Cancelling...'
          : 'Cancel Request';
      }
      if (fcrCancelModalBackBtn) {
        fcrCancelModalBackBtn.disabled = isBusy;
      }
    }

    window.fcrOpenCancelModal = function(id) {
      pendingFcrCancelId = id;
      setFcrCancelModalError('');
      setFcrCancelModalBusy(false);
      if (fcrCancelModal) {
        fcrCancelModal.show();
      }
    };

    async function fcrCancelRequest(id) {
      try {
        const fd = new FormData();
        fd.append('action', 'cancel_fee_change_request');
        fd.append('fee_type_id', id);
        const res  = await fetch(API, { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Cancel failed.');
        if (fcrCancelModal) {
          fcrCancelModal.hide();
        }
        pendingFcrCancelId = null;
        loadFcrList();
      } catch (e) {
        setFcrCancelModalError(e.message || 'Cancel failed.');
      } finally {
        setFcrCancelModalBusy(false);
      }
    }

    fcrCancelModalConfirmBtn?.addEventListener('click', () => {
      if (!pendingFcrCancelId) return;
      setFcrCancelModalError('');
      setFcrCancelModalBusy(true);
      fcrCancelRequest(pendingFcrCancelId);
    });

    fcrCancelModalEl?.addEventListener('hidden.bs.modal', () => {
      pendingFcrCancelId = null;
      setFcrCancelModalError('');
      setFcrCancelModalBusy(false);
    });

    document.getElementById('fcrListRefreshBtn').addEventListener('click', loadFcrList);
  })();
})();
