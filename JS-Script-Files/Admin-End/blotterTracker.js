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
  const filterButtons = Array.from(document.querySelectorAll('.status-filter-btn'));
  const activeBlotterBadge = document.getElementById('activeBlotterBadge');
  const filterModalEl = document.getElementById('modalBlotterFilter');
  const filterDateFromEl = document.getElementById('blotterFilterDateFrom');
  const filterDateToEl = document.getElementById('blotterFilterDateTo');
  const filterTypeListEl = document.getElementById('blotterFilterTypeList');
  const filterAreaListEl = document.getElementById('blotterFilterAreaList');
  const filterSectorListEl = document.getElementById('blotterFilterSectorList');
  const btnBlotterFilterApply = document.getElementById('btnBlotterFilterApply');
  const btnBlotterFilterReset = document.getElementById('btnBlotterFilterReset');

  const viewModalEl = document.getElementById('viewModal');
  const viewModal = viewModalEl ? new bootstrap.Modal(viewModalEl) : null;
  const viewModalTitle = document.getElementById('viewModalTitle');
  const viewDetailsBody = document.getElementById('viewDetailsBody');
  const viewModalActionButtons = document.getElementById('viewModalActionButtons');
  const caseLogsModalEl = document.getElementById('caseLogsModal');
  const caseLogsModal = caseLogsModalEl ? new bootstrap.Modal(caseLogsModalEl) : null;
  const caseLogsModalTitle = document.getElementById('caseLogsModalTitle');
  const caseLogsBody = document.getElementById('caseLogsBody');
  const unsupportedFileModalEl = document.getElementById('unsupportedFileModal');
  const unsupportedFileModal = unsupportedFileModalEl ? new bootstrap.Modal(unsupportedFileModalEl) : null;
  const btnUnsupportedFileReturn = document.getElementById('btnUnsupportedFileReturn');
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

  let currentRows = [];
  let currentPage = 1;
  let totalPages = 1;
  let totalItems = 0;
  let activeFilter = '';
  let modalFilters = {
    dateFrom: '',
    dateTo: '',
    complaint_type: [],
    area_number: [],
    sector_membership: [],
  };
  let currentViewCaseId = null;
  let currentDetail = null;
  let pendingCaseAction = null;
  let caseActionHandlersBound = false;
  let unsupportedFileReturnToView = false;
  const OFFICIAL_AREA_OPTIONS = ['Area 01', 'Area 1A', 'Area 02', 'Area 03', 'Area 04', 'Area 05', 'Area 06'];
  const OFFICIAL_SECTOR_OPTIONS = ['PWD', 'Senior Citizen', 'Student', 'Indigenous People', 'Single Parent'];

  function setRefreshLoading(on) {
    if (!refreshBtn) return;
    refreshBtn.classList.toggle('is-loading', !!on);
    refreshBtn.disabled = !!on;
  }

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

  function formatNameWithMiddleInitial(parts) {
    const first = String(parts?.firstname ?? '').trim().replace(/\s+/g, ' ');
    const middle = String(parts?.middlename ?? '').trim().replace(/\s+/g, ' ');
    const last = String(parts?.lastname ?? '').trim().replace(/\s+/g, ' ');
    const suffix = String(parts?.suffix ?? '').trim().replace(/\s+/g, ' ');
    const fallback = String(parts?.fullName ?? '').trim().replace(/\s+/g, ' ');

    const middleInitial = middle ? `${middle.charAt(0).toUpperCase()}.` : '';
    const formatted = [first, middleInitial, last, suffix].filter(Boolean).join(' ');
    return formatted || fallback || '-';
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

  function parseCsvValues(value) {
    return Array.from(new Set(
      String(value ?? '')
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean)
    ));
  }

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
    const raw = String(value || '').trim();
    if (!raw) return '';
    const isoMatch = raw.match(/^(\d{4}-\d{2}-\d{2})/);
    if (isoMatch) return isoMatch[1];
    const parsed = new Date(raw);
    if (Number.isNaN(parsed.getTime())) return '';
    const year = parsed.getFullYear();
    const month = String(parsed.getMonth() + 1).padStart(2, '0');
    const day = String(parsed.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  function renderFilterChecklist(container, field, values) {
    if (!container) return;
    const list = Array.isArray(values) ? values : [];
    if (!list.length) {
      container.innerHTML = `<div class="text-muted small">No options available.</div>`;
      return;
    }
    const active = new Set(Array.isArray(modalFilters[field]) ? modalFilters[field] : []);
    container.innerHTML = list.map((value, index) => `
      <label class="d-flex align-items-center gap-2">
        <input class="form-check-input m-0 blotter-filter-checkbox" type="checkbox" value="${esc(value)}" data-field="${esc(field)}" id="${esc(`blotterFilter_${field}_${index}`)}" ${active.has(value) ? 'checked' : ''}>
        <span>${esc(value)}</span>
      </label>
    `).join('');
  }

  function syncFilterOptions(complaintTypes = []) {
    const normalizedComplaintTypes = Array.from(new Set(
      (Array.isArray(complaintTypes) ? complaintTypes : [])
        .map((value) => String(value || '').trim())
        .filter(Boolean)
    )).sort((a, b) => a.localeCompare(b));
    const areaNumbers = OFFICIAL_AREA_OPTIONS.slice();
    const sectors = OFFICIAL_SECTOR_OPTIONS.slice();

    modalFilters.area_number = modalFilters.area_number.filter((value) => areaNumbers.includes(String(value || '').trim()));
    modalFilters.sector_membership = modalFilters.sector_membership
      .map((value) => normalizeSectorLabel(value))
      .filter((value) => sectors.includes(value));

    renderFilterChecklist(filterTypeListEl, 'complaint_type', normalizedComplaintTypes);
    renderFilterChecklist(filterAreaListEl, 'area_number', areaNumbers);
    renderFilterChecklist(filterSectorListEl, 'sector_membership', sectors);
    if (filterDateFromEl) filterDateFromEl.value = modalFilters.dateFrom || '';
    if (filterDateToEl) filterDateToEl.value = modalFilters.dateTo || '';
  }

  function collectModalFilters() {
    const next = {
      dateFrom: String(filterDateFromEl?.value || '').trim(),
      dateTo: String(filterDateToEl?.value || '').trim(),
      complaint_type: [],
      area_number: [],
      sector_membership: [],
    };
    document.querySelectorAll('.blotter-filter-checkbox:checked').forEach((checkbox) => {
      const field = String(checkbox.getAttribute('data-field') || '').trim();
      if (!field || !Array.isArray(next[field])) return;
      next[field].push(String(checkbox.value || '').trim());
    });
    return next;
  }

  function matchesModalFilters(row) {
    const filedDate = normalizeDateValue(row?.date_filed_raw);
    if (modalFilters.dateFrom && (!filedDate || filedDate < modalFilters.dateFrom)) return false;
    if (modalFilters.dateTo && (!filedDate || filedDate > modalFilters.dateTo)) return false;
    if (modalFilters.complaint_type.length && !modalFilters.complaint_type.includes(String(row?.complaint_type || '').trim())) return false;
    if (modalFilters.area_number.length && !modalFilters.area_number.includes(String(row?.area_number || '').trim())) return false;
    if (modalFilters.sector_membership.length) {
      const memberships = parseSectorValues(row?.sector_membership);
      const hasSector = modalFilters.sector_membership
        .map((value) => normalizeSectorLabel(value))
        .some((value) => memberships.includes(value));
      if (!hasSector) return false;
    }
    return true;
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
    const complainant = formatNameWithMiddleInitial({
      firstname: row.complainant_firstname,
      middlename: row.complainant_middlename,
      lastname: row.complainant_lastname,
      suffix: row.complainant_suffix,
      fullName: row.complainant_name
    });
    const respondent = formatNameWithMiddleInitial({
      firstname: row.respondent_firstname,
      middlename: row.respondent_middlename,
      lastname: row.respondent_lastname,
      suffix: row.respondent_suffix,
      fullName: row.respondent_name
    });
    const status = row.status_name || '-';
    const level = row.level_name || '-';
    const statusBadge = badge(status, toneForStatus(status));
    const levelBadge = badge(level, toneForCaseLevel(level));
    const viewBtn = `<button class="btn btn-sm btn-outline-secondary compact-table-btn" data-view-id="${esc(row.case_id)}">View</button>`;
    const logsBtn = `<button class="btn btn-sm btn-warning compact-table-btn" data-logs-id="${esc(row.case_id)}">Case Logs</button>`;
    return `
      <tr>
        <td>${esc(blotterIdDisplay)}</td>
        <td>${esc(blotterNumber)}</td>
        <td>${esc(dateFiled)}</td>
        <td>${esc(complainant)}</td>
        <td>${esc(respondent)}</td>
        <td>${statusBadge}</td>
        <td>${levelBadge}</td>
        <td><div class="compact-table-actions">${viewBtn}${logsBtn}</div></td>
      </tr>
    `;
  }

  function renderPagination() {
    if (!paginationEl) return;
    currentPage = Math.min(currentPage, totalPages);
    const items = [];
    const makeBtn = (label, page, disabled = false, active = false) => `
      <li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
        <button class="page-link" data-page="${page}" ${disabled ? 'disabled' : ''}>${label}</button>
      </li>
    `;
    items.push(makeBtn('Prev', currentPage - 1, currentPage <= 1));
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    startPage = Math.max(1, endPage - 4);

    if (startPage > 1) {
      items.push(makeBtn('1', 1, false, currentPage === 1));
      if (startPage > 2) {
        items.push(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
      }
    }

    for (let i = startPage; i <= endPage; i += 1) {
      items.push(makeBtn(String(i), i, false, i === currentPage));
    }

    if (endPage < totalPages) {
      if (endPage < totalPages - 1) {
        items.push(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
      }
      items.push(makeBtn(String(totalPages), totalPages, false, currentPage === totalPages));
    }

    items.push(makeBtn('Next', currentPage + 1, currentPage >= totalPages));
    paginationEl.innerHTML = items.join('');
    paginationEl.querySelectorAll('button[data-page]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const page = Number(btn.getAttribute('data-page') || 1);
        if (!Number.isFinite(page)) return;
        currentPage = page;
        loadList();
      });
    });
  }

  function renderTable() {
    if (!tableBody) return;
    if (!currentRows.length) {
      tableBody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">No blotter records found.</td></tr>`;
    } else {
      tableBody.innerHTML = currentRows.map(buildTableRow).join('');
    }
    renderPagination();

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

  function buildListUrl() {
    const params = new URLSearchParams();
    params.set('action', 'list');
    params.set('page', String(currentPage));
    params.set('per_page', String(Math.max(1, Number(entriesPerPageInput?.value || 20))));
    const searchTerm = String(searchInput?.value || '').trim();
    if (searchTerm) params.set('search', searchTerm);
    if (activeFilter) params.set('status', activeFilter);
    if (modalFilters.dateFrom) params.set('date_from', modalFilters.dateFrom);
    if (modalFilters.dateTo) params.set('date_to', modalFilters.dateTo);
    if (modalFilters.complaint_type.length) params.set('complaint_type', modalFilters.complaint_type.join(','));
    if (modalFilters.area_number.length) params.set('area_number', modalFilters.area_number.join(','));
    if (modalFilters.sector_membership.length) params.set('sector_membership', modalFilters.sector_membership.join(','));
    return `${endpoint}?${params.toString()}`;
  }

  function updateActiveBadge(count) {
    if (!activeBlotterBadge) return;
    activeBlotterBadge.textContent = String(count);
    activeBlotterBadge.classList.toggle('d-none', count <= 0);
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

  function applyListResponse(data) {
    const meta = data?.meta || {};
    const pagination = meta.pagination || {};
    currentRows = Array.isArray(data?.items) ? data.items : [];
    currentPage = Math.max(1, Number(pagination.page || currentPage || 1));
    totalPages = Math.max(1, Number(pagination.total_pages || 1));
    totalItems = Math.max(0, Number(pagination.total_items || currentRows.length || 0));
    updateActiveBadge(Number(meta.badges?.active_count || 0));
    syncFilterOptions(meta.filters?.complaint_types || []);
    renderTable();
  }

  async function loadList() {
    if (!tableBody) return;
    const url = buildListUrl();
    tableBody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">Loading blotter records...</td></tr>`;
    setRefreshLoading(true);
    try {
      const data = await fetchJson(url);
      applyListResponse(data);
    } catch (err) {
      tableBody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${esc(err.message || err)}</td></tr>`;
    } finally {
      setRefreshLoading(false);
    }
  }

  function renderNarrativeReportsSection(detail) {
    const initialStamp = String(detail?.report_timestamp || detail?.date_filed || '-');
    let initialValueHtml = esc(detail?.narrative_value || '-');
    if (detail?.narrative_type === 'file' && detail?.narrative_value) {
      const fileUrl = String(detail?.narrative_url || '').trim()
        || `${appBase}/${String(detail.narrative_value).replace(/^\/+/, '')}`;
      const fileName = String(detail?.narrative_value || 'Narrative File').split('/').pop() || 'Narrative File';
      const mimeType = String(detail?.narrative_mime_type || '').trim();
      const ext = getAttachmentExtension(fileUrl);
      const isPdf = mimeType.toLowerCase() === 'application/pdf' || ext === 'pdf';
      const isImage = mimeType.toLowerCase().startsWith('image/') || ['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp', 'svg'].includes(ext);
      let previewHtml = `
        <div class="small text-muted">
          Preview is unavailable for this file type. Use the buttons above to open or download the attachment.
        </div>
      `;
      if (isImage) {
        previewHtml = `
          <a
            class="d-block border rounded-3 overflow-hidden bg-white js-blotter-attachment"
            href="${esc(fileUrl)}"
            data-file-url="${esc(fileUrl)}"
            data-file-name="${esc(fileName)}"
            data-file-mime="${esc(mimeType)}"
            aria-label="Open ${esc(fileName)}"
          >
            <img src="${esc(fileUrl)}" alt="${esc(fileName)}" class="img-fluid d-block mx-auto" style="max-height: 420px; object-fit: contain;">
          </a>
        `;
      } else if (isPdf) {
        previewHtml = `
          <iframe
            src="${esc(fileUrl)}"
            title="${esc(fileName)}"
            class="w-100 border rounded-3 bg-white"
            style="min-height: 480px;"
          ></iframe>
        `;
      }
      initialValueHtml = `
        <div class="tracker-attachment-actions">
          <a
            class="btn btn-sm btn-outline-primary js-blotter-attachment"
            href="${esc(fileUrl)}"
            data-file-url="${esc(fileUrl)}"
            data-file-name="${esc(fileName)}"
            data-file-mime="${esc(mimeType)}"
          >Open Narrative File</a>
          <a
            class="btn btn-sm btn-outline-secondary"
            href="${esc(fileUrl)}"
            download
          >Download</a>
        </div>
        <div class="tracker-attachment-preview mt-2">${previewHtml}</div>
      `;
    } else if (detail?.narrative_type === 'text' && detail?.narrative_value) {
      initialValueHtml = `<div class="tracker-form-value-text">${esc(detail.narrative_value || '-')}</div>`;
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
            <a
              class="btn btn-sm btn-outline-primary js-blotter-attachment"
              href="${esc(fileUrl)}"
              data-file-url="${esc(fileUrl)}"
              data-file-name="${esc(label)} Signature"
              data-file-mime="${esc(item?.mime_type || '')}"
            >Open</a>
          </div>
          <a
            class="tracker-signature-card__preview js-blotter-attachment"
            href="${esc(fileUrl)}"
            data-file-url="${esc(fileUrl)}"
            data-file-name="${esc(label)} Signature"
            data-file-mime="${esc(item?.mime_type || '')}"
            aria-label="Open ${esc(label)} signature"
          >
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
      </section>
    `;
  }

  function syncViewModalFooterActions(detail) {
    const isFinalized = String(detail?.status_name || '').trim().toLowerCase() !== 'active';
    const markResolvedBtn = document.getElementById('btnMarkResolved');
    const subjectEndorsementBtn = document.getElementById('btnSubjectEndorsement');
    const markDroppedBtn = document.getElementById('btnMarkDropped');

    markResolvedBtn?.classList.toggle('d-none', isFinalized);
    subjectEndorsementBtn?.classList.toggle('d-none', isFinalized);
    markDroppedBtn?.classList.toggle('d-none', isFinalized);
    viewModalActionButtons?.classList.toggle('d-none', isFinalized);
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

  function getAttachmentExtension(url) {
    const cleanUrl = String(url || '').split('?')[0].split('#')[0].trim().toLowerCase();
    const match = cleanUrl.match(/\.([a-z0-9]+)$/i);
    return match ? match[1] : '';
  }

  function isSupportedAttachment(fileUrl, mimeType) {
    const mime = String(mimeType || '').trim().toLowerCase();
    if (mime === 'application/pdf' || mime.startsWith('image/')) {
      return true;
    }

    const ext = getAttachmentExtension(fileUrl);
    return ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp', 'svg'].includes(ext);
  }

  function bindAttachmentActions() {
    viewDetailsBody?.querySelectorAll('.js-blotter-attachment').forEach((link) => {
      link.addEventListener('click', (event) => {
        event.preventDefault();
        const fileUrl = String(link.getAttribute('data-file-url') || link.getAttribute('href') || '').trim();
        const mimeType = String(link.getAttribute('data-file-mime') || '').trim();
        if (!fileUrl) {
          return;
        }

        if (isSupportedAttachment(fileUrl, mimeType)) {
          window.open(fileUrl, '_blank', 'noopener');
          return;
        }

        unsupportedFileReturnToView = true;
        transitionModal(viewModalEl, viewModal, unsupportedFileModal);
      });
    });
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
        const finishCaseUpdate = async () => {
          currentViewCaseId = null;
          currentDetail = null;
          await loadList();
          alert('Case updated successfully.');
        };
        if (caseActionConfirmModalEl && caseActionConfirmModal) {
          caseActionConfirmModalEl.addEventListener('hidden.bs.modal', async () => {
            await finishCaseUpdate();
          }, { once: true });
          caseActionConfirmModal.hide();
        } else {
          await finishCaseUpdate();
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
    const isFinalized = String(currentDetail?.status_name || '').trim().toLowerCase() !== 'active';

    if (!narrativeAddInput || !addNarrativeBtn || !addUpdateBtn) return;

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
    viewModalActionButtons?.classList.add('d-none');
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
          { label: 'Area Number', value: d.incident_area_number || '-' },
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
      syncViewModalFooterActions(d);
      bindAttachmentActions();
      bindViewActions();
      loadNarrativeUpdates(caseId);
    } catch (err) {
      viewDetailsBody.innerHTML = `<div class="text-danger">${esc(err.message || err)}</div>`;
      viewModalActionButtons?.classList.add('d-none');
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

  btnUnsupportedFileReturn?.addEventListener('click', () => {
    unsupportedFileReturnToView = false;
    transitionModal(unsupportedFileModalEl, unsupportedFileModal, viewModal);
  });

  unsupportedFileModalEl?.addEventListener('hidden.bs.modal', () => {
    if (!unsupportedFileReturnToView) {
      return;
    }
    unsupportedFileReturnToView = false;
    viewModal?.show();
  });

  let searchTimer = null;
  filterButtons.forEach((button) => {
    button.addEventListener('click', () => {
      activeFilter = String(button.getAttribute('data-filter') || '').trim().toLowerCase();
      filterButtons.forEach((btn) => {
        const isActive = btn === button;
        btn.classList.toggle('active', isActive);
        btn.classList.toggle('btn-outline-primary', isActive);
        btn.classList.toggle('btn-outline-secondary', !isActive);
      });
      currentPage = 1;
      loadList();
    });
  });

  searchInput?.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      currentPage = 1;
      loadList();
    }, 200);
  });

  entriesPerPageInput?.addEventListener('change', () => {
    currentPage = 1;
    loadList();
  });

  refreshBtn?.addEventListener('click', () => {
    loadList();
  });

  btnBlotterFilterApply?.addEventListener('click', () => {
    modalFilters = collectModalFilters();
    currentPage = 1;
    loadList();
    if (filterModalEl) bootstrap.Modal.getInstance(filterModalEl)?.hide();
  });

  btnBlotterFilterReset?.addEventListener('click', () => {
    modalFilters = {
      dateFrom: '',
      dateTo: '',
      complaint_type: [],
      area_number: [],
      sector_membership: [],
    };
    if (filterDateFromEl) filterDateFromEl.value = '';
    if (filterDateToEl) filterDateToEl.value = '';
    document.querySelectorAll('.blotter-filter-checkbox').forEach((checkbox) => {
      checkbox.checked = false;
    });
    currentPage = 1;
    loadList();
  });

  initCaseActionFlow();
  loadList();
})();
