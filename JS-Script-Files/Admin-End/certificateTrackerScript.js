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
  const actionPrompt = document.getElementById('actionPrompt');
  const actionCancelBtn = document.getElementById('actionCancelBtn');
  const actionSubmitBtn = document.getElementById('actionSubmitBtn');
  const modalTitle = document.getElementById('actionModalTitle');
  const modalError = document.getElementById('actionModalError');
  const viewModalEl = document.getElementById('viewModal');
  const viewModal = viewModalEl ? new bootstrap.Modal(viewModalEl) : null;
  const viewModalTitle = document.getElementById('viewModalTitle');
  const viewDetailsBody = document.getElementById('viewDetailsBody');
  const viewModalActions = document.getElementById('viewModalActions');
  const viewModalBackBtn = document.getElementById('viewModalBackBtn');
  const viewModalNextBtn = document.getElementById('viewModalNextBtn');
  const paymentProofModalEl = document.getElementById('paymentProofModal');
  const paymentProofModal = paymentProofModalEl ? new bootstrap.Modal(paymentProofModalEl) : null;
  const paymentProofWrap = document.getElementById('paymentProofWrap');
  const paymentProofOpenNew = document.getElementById('paymentProofOpenNew');
  const paymentProofTitle = document.getElementById('paymentProofTitle');
  const paymentProofReturnBtn = document.getElementById('paymentProofReturnBtn');
  const paymentProofCloseBtn = document.getElementById('paymentProofCloseBtn');
  const residentProfileModalEl = document.getElementById('residentProfileModal');
  const residentProfileModal = residentProfileModalEl ? new bootstrap.Modal(residentProfileModalEl) : null;
  const residentProfileReturnBtn = document.getElementById('residentProfileReturnBtn');
  const residentProfileEndpoint = `${appBase}/PhpFiles/Admin-End/residentMasterlist.php`;

  let currentStage = String(window.CERT_TRACKER_DEFAULT_STAGE || '');
  let currentStatusFilter = 'all';
  let currentDocumentTypeFilter = '';
  let itemById = new Map();
  let viewMode = 'details';
  let viewDetailsHtml = '';
  let viewPreviewState = null;
  let currentViewRequestId = '';
  let actionReturnTarget = '';
  let suppressActionReturn = false;
  let openPreviewAfterActionModal = false;
  let paymentProofReturnTarget = '';
  let preserveViewStateOnNextHide = false;
  const financeStages = new Set(['for_payment', 'payment_submitted']);

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
    const proofBtn = row.payment_proof_path
      ? `<button class="btn btn-sm btn-outline-dark" data-proof-id="${id}">View Payment</button>`
      : '';

    if (stage === 'submitted') {
      return `
        <button class="btn btn-sm btn-danger" data-view-action="personnel_reject" data-id="${id}">Reject</button>
        <button class="btn btn-sm btn-success" data-view-action="personnel_approve" data-id="${id}">Approve</button>
      `;
    }
    if (stage === 'payment_submitted') {
      return `
        ${proofBtn}
        <button class="btn btn-sm btn-success" data-view-action="finance_verify" data-id="${id}">Verify Payment</button>
        <button class="btn btn-sm btn-danger" data-view-action="finance_reject" data-id="${id}">Reject Payment</button>
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
        <button class="btn btn-sm btn-dark" data-view-action="mark_completed" data-id="${id}">Mark Completed</button>
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

  function fullNameFromRow(row) {
    const payload = row && row.payload && typeof row.payload === 'object' ? row.payload : {};
    const first = firstNonEmpty([payload.first_name, payload.firstname]);
    const middle = firstNonEmpty([payload.middle_name, payload.middlename]);
    const last = firstNonEmpty([payload.last_name, payload.lastname]);
    const suffix = firstNonEmpty([payload.suffix, payload.suffix_name]);
    const middleInitial = middle ? `${middle.charAt(0).toUpperCase()}.` : '';

    const ordered = [first, middleInitial, last, suffix].filter(Boolean);
    if (ordered.length) return ordered.join(' ');
    const fallbackName = firstNonEmpty([row.full_name, row.resident_full_name, row.resident_name, '']);
    if (!fallbackName) return '-';

    const parts = fallbackName.split(/\s+/).filter(Boolean);
    if (parts.length >= 3) {
      const f = parts[0];
      const l = parts[parts.length - 1];
      const m = parts.slice(1, parts.length - 1).join(' ');
      const mi = m ? `${m.charAt(0).toUpperCase()}.` : '';
      return [f, mi, l].filter(Boolean).join(' ');
    }
    return fallbackName;
  }

  function stripAreaFromAddress(address) {
    let value = String(address || '').trim();
    if (!value) return '';
    value = value.replace(/\s*,\s*Area\s+[A-Za-z0-9-]+\s*(?=,|$)/gi, '');
    value = value.replace(/(^|,\s*)Area\s+[A-Za-z0-9-]+\s*,\s*/gi, '$1');
    value = value.replace(/\s{2,}/g, ' ').trim();
    value = value.replace(/^[,\s]+|[,\s]+$/g, '');
    return value;
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
    if (key === 'indigency' || key === 'certificateofindigency') {
      return 'CertificateOfIndigency';
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
    return `<div class="tracker-form-grid ${cls}">${clean.map((f) => formField(f.label, f.value, !!f.raw)).join('')}</div>`;
  }

  function extractSubmittedDocuments(row, payload) {
    const docs = [];
    const seen = new Set();

    const addDoc = (label, rawPath) => {
      const pathText = String(rawPath || '').trim();
      if (!pathText) return;
      const match = pathText.match(/\/UnifiedFileAttachment\/[^\s"'<>]+/i);
      const normalized = match ? match[0] : (pathText.startsWith('/UnifiedFileAttachment/') ? pathText : '');
      if (!normalized) return;
      const url = `${appBase}${normalized}`;
      if (seen.has(url)) return;
      seen.add(url);
      docs.push({ label: String(label || 'Document'), url, path: normalized });
    };

    addDoc('Payment Proof', row?.payment_proof_path);

    if (payload && typeof payload === 'object') {
      Object.keys(payload).forEach((key) => {
        const value = payload[key];
        if (Array.isArray(value)) {
          value.forEach((entry, idx) => addDoc(`${friendlyLabel(key)} ${idx + 1}`, entry));
          return;
        }
        if (typeof value === 'string') {
          addDoc(friendlyLabel(key), value);
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

  function dr_now_text() {
    return new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
  }

  function previewIndigencyIssuedText(value) {
    const raw = String(value || '').trim();
    const d = raw ? new Date(raw) : new Date();
    if (Number.isNaN(d.getTime())) return raw || dr_now_text();
    const day = d.getDate();
    const month = d.toLocaleDateString('en-US', { month: 'long' }).toUpperCase();
    const year = d.getFullYear();
    const v = day % 100;
    const suffix = (v >= 11 && v <= 13) ? 'th' : ({ 1: 'st', 2: 'nd', 3: 'rd' }[day % 10] || 'th');
    return `${day}${suffix} day of ${month} ${year}`;
  }

  function previewEditable(key, value, fallback = 'Type here', extraClass = '') {
    const text = String(value || '').trim() || fallback;
    const cls = ['doc-editable', extraClass].filter(Boolean).join(' ');
    return `<span class="${cls}" contenteditable="true" data-edit-key="${esc(key)}">${esc(text)}</span>`;
  }

  function normalizePreviewDocKey(docType) {
    const text = String(docType || '').toLowerCase();
    if (text.includes('cohabitation')) return 'cohabitation';
    if (text.includes('indigency')) return 'indigency';
    if (text.includes('first time') || text.includes('job seeker')) return 'firsttimejobseeker';
    if (text.includes('identity')) return 'identity';
    if (text.includes('residency')) return 'residency';
    if (text.includes('good moral')) return 'goodmoral';
    return 'generic';
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

  function renderDocumentPreview(state) {
    if (!state || typeof state !== 'object') {
      return '<div class="text-muted">No document preview available.</div>';
    }
    const docType = String(state.docType || 'Certificate').trim() || 'Certificate';
    const docKey = normalizePreviewDocKey(docType);
    const fullName = String(state.fullName || '-').trim() || '-';
    const fullAddress = String(state.fullAddress || '-').trim() || '-';
    const purpose = String(state.purpose || '-').trim() || '-';
    const issuedDate = previewDateText(state.issuedDate || '');
    const requestOfficer = String(state.requestOfficer || '').trim();
    const requestFor = String(state.requestFor || '').trim();
    const yearsResidency = String(state.yearsResidency || '').trim();
    const monthsResidency = String(state.monthsResidency || '').trim();
    const childBirthplace = String(state.childBirthplace || '').trim();
    const childBirthdate = String(state.childBirthdate || '').trim();
    const childNationality = String(state.childNationality || '').trim();
    const fatherName = String(state.fatherName || '').trim();
    const motherName = String(state.motherName || '').trim();
    const cohabitantName = String(state.cohabitantName || '').trim();
    const cohabitantRelationship = String(state.cohabitantRelationship || '').trim();
    const cohabitationDuration = String(state.cohabitationDuration || '').trim();
    const cohabitationStartDate = String(state.cohabitationStartDate || '').trim();
    const education = String(state.educationalAttainment || '').trim();
    const jobStart = String(state.jobstartBeneficiary || '').trim();
    const businessName = String(state.businessName || '').trim();
    const additionalHtml = additionalDetailRows(state.additionalDetails);
    const leftLogoUrl = `${appBase}/Images/San_Jose_LOGO.jpg`;
    const rightLogoUrl = `${appBase}/Images/Montalban_Logo.png`;
    const fallbackRightLogoUrl = `${appBase}/Images/San_Jose_LOGO.jpg`;
    const qrUrl = String(state.qrUrl || '').trim();

    let contentHtml = '';
    if (docKey === 'residency') {
      const residencyText = [yearsResidency ? `${yearsResidency} year(s)` : '', monthsResidency ? `${monthsResidency} month(s)` : '']
        .filter(Boolean).join(' and ') || 'a stated period';
      contentHtml = `
        <p>
          This is to certify that <strong>${esc(fullName)}</strong> is a bona fide resident of
          <strong>${esc(fullAddress)}</strong> and has been residing in this barangay for <strong>${esc(residencyText)}</strong>.
        </p>
        <p>
          This certificate is issued upon request for <strong>${previewEditable('purpose', purpose, 'State purpose')}</strong>.
        </p>
        <p>
          This certification is valid for legal and administrative use, subject to verification by concerned offices and institutions.
        </p>
      `;
    } else if (docKey === 'indigency') {
      const requestOffice = requestOfficer || '';
      const indigencyPurpose = purpose || requestFor || 'PURPOSE';
      const toBlock = requestOffice.trim() !== ''
        ? `<div class="doc-to-block"><strong>TO</strong><strong>:</strong><strong>${previewEditable('requestOfficer', requestOffice, 'Receiving office', 'doc-editable-multiline')}</strong></div>`
        : `<div class="doc-to-block"><strong>TO</strong><strong>:</strong><div class="doc-to-lines"><span class="line"></span><span class="line"></span><span class="line"></span></div></div>`;
      contentHtml = `
        ${toBlock}
        <p>
          This is to certify that <strong>${esc(fullName)}</strong>, resident of
          <strong>${esc(fullAddress)}</strong> belongs to the one of the indigent families of this Barangay.
          The Income of this family is barely enough to meet their day-to-day needs.
        </p>
        <p>
          This certification is being issued upon the request of the above subject in person in connection
          with his/her application for <strong>${previewEditable('purpose', indigencyPurpose, 'PURPOSE')}</strong> purposes only.
        </p>
      `;
    } else if (docKey === 'goodmoral') {
      contentHtml = `
        <p>
          This is to certify that <strong>${esc(fullName)}</strong> of
          <strong>${esc(fullAddress)}</strong> is known in this community as a person of good moral character.
        </p>
        <p>
          This certification is issued upon request for <strong>${previewEditable('purpose', purpose, 'State purpose')}</strong>.
        </p>
        <p>
          Issued for whatever legal purpose it may serve, without prejudice to existing laws, ordinances, and regulations.
        </p>
      `;
    } else if (docKey === 'identity') {
      contentHtml = `
        <p>
          This is to certify that <strong>${esc(fullName)}</strong> is personally known to this barangay and is a resident of
          <strong>${esc(fullAddress)}</strong>.
        </p>
        <p>
          The following information is presented for identity reference: Birthdate <strong>${esc(childBirthdate || '-')}</strong>,
          Place of Birth <strong>${esc(childBirthplace || '-')}</strong>, Nationality <strong>${esc(childNationality || '-')}</strong>,
          Father <strong>${esc(fatherName || '-')}</strong>, Mother <strong>${esc(motherName || '-')}</strong>.
        </p>
        <p>
          This certification is issued upon request for <strong>${previewEditable('purpose', purpose, 'State purpose')}</strong>.
        </p>
        <p>
          This document may be used as reference for identity confirmation subject to the acceptance of the receiving office.
        </p>
      `;
    } else if (docKey === 'firsttimejobseeker') {
      contentHtml = `
        <p>
          This is to certify that <strong>${esc(fullName)}</strong> is a bona fide resident of
          <strong>${esc(fullAddress)}</strong> and is applying as a first time job seeker.
        </p>
        <p>
          Educational Attainment: <strong>${esc(education || '-')}</strong>.
          Beneficiary of JobStart Program under RA 10869: <strong>${esc(jobStart || '-')}</strong>.
        </p>
        <p>
          This certificate is issued upon request for <strong>${previewEditable('purpose', purpose, 'State purpose')}</strong>.
        </p>
        <p>
          Issued in accordance with applicable barangay procedures for first time job seeker documentary assistance.
        </p>
      `;
    } else if (docKey === 'cohabitation') {
      contentHtml = `
        <p>
          This is to certify that <strong>${esc(fullName)}</strong> and
          <strong>${esc(cohabitantName || 'cohabitant/partner')}</strong> are known residents of this barangay.
        </p>
        <p>
          Based on records and sworn declaration, they have been living together as partners
          ${cohabitationDuration !== '' ? `for <strong>${esc(cohabitationDuration)}</strong>` : ''}
          ${cohabitationStartDate !== '' ? ` since <strong>${esc(previewDateText(cohabitationStartDate))}</strong>` : ''}
          at <strong>${esc(fullAddress)}</strong>
          ${cohabitantRelationship !== '' ? `, with relationship stated as <strong>${esc(cohabitantRelationship)}</strong>` : ''}.
        </p>
        <p>
          This certification is issued upon request for <strong>${previewEditable('purpose', purpose, 'State purpose')}</strong>.
        </p>
        <p>
          This is issued for proper documentation and lawful use where proof of cohabitation is required.
        </p>
      `;
    } else {
      contentHtml = `
        <p>
          This is to certify that <strong>${esc(fullName)}</strong> is a bona fide resident of
          <strong>${esc(fullAddress)}</strong>.
        </p>
        <p>
          ${businessName !== '' ? `Business Name: <strong>${previewEditable('businessName', businessName, 'Business name')}</strong>.<br>` : ''}
          This certification is issued upon request for <strong>${previewEditable('purpose', purpose, 'State purpose')}</strong>.
        </p>
        <p>
          This document is issued for lawful purposes and may be subject to validation by receiving agencies.
        </p>
      `;
    }
    if (additionalHtml !== '') {
      contentHtml += additionalHtml;
    }
    if (businessName !== '' && docKey !== 'generic') {
      contentHtml += `
        <p>
          Business Name: <strong>${previewEditable('businessName', businessName, 'Business name')}</strong>.
        </p>
      `;
    }

    const isIndigency = docKey === 'indigency';
    const titleText = isIndigency ? 'CERTIFICATE OF INDIGENCY' : docType;
    const issuedLine = isIndigency
      ? `Issued this <strong>${esc(previewIndigencyIssuedText(state.issuedDate || ''))}</strong>, at the office of the punong Barangay, Barangay San Jose, Rodriguez (Montalban), Rizal.`
      : `Issued this <strong>${esc(issuedDate)}</strong> at Barangay San Jose, Rodriguez, Rizal.`;
    const paperClass = isIndigency ? 'doc-preview-paper doc-preview-paper--indigency' : 'doc-preview-paper';
    return `
      <div class="doc-preview-stage">
        <span class="doc-preview-label">Document Display</span>
        <div class="doc-preview-shell">
          <div class="${paperClass}">
            ${isIndigency ? '' : '<p class="doc-preview-hint">Highlighted fields are editable in this preview.</p>'}
            <div class="doc-preview-head">
              <img class="doc-preview-logo" src="${leftLogoUrl}" alt="Barangay San Jose Logo">
              <div class="doc-preview-head-center">
                <p class="rep">REPUBLIKA NG PILIPINAS</p>
                <p>LALAWIGAN NG RIZAL</p>
                <p>BAYAN NG RODRIGUEZ</p>
                <p class="barangay">BARANGAY SAN JOSE</p>
                ${isIndigency ? '' : '<p class="doc-head-office">TANGGAPAN NG PUNONG BARANGAY</p>'}
                <div class="doc-preview-head-line"></div>
              </div>
              <img class="doc-preview-logo" src="${rightLogoUrl}" alt="Montalban Logo" onerror="this.onerror=null;this.src='${fallbackRightLogoUrl}'">
            </div>
            ${isIndigency
              ? '<div class="doc-preview-title doc-preview-title--indigency"><div class="office">TANGGAPAN NG PUNONG BARANGAY</div><div class="certificate">CERTIFICATE OF INDIGENCY</div></div>'
              : `<div class="doc-preview-title">${esc(titleText)}</div>`}
            <div class="doc-preview-body">
              ${isIndigency ? '' : '<p><strong>TO WHOM IT MAY CONCERN:</strong></p>'}
              ${contentHtml}
              <p>${issuedLine}</p>
            </div>
            ${isIndigency ? '<div class="doc-preview-issuedby">Issued by: <strong>MINERVA D. QUITA</strong><br><em>Barangay Secretary</em></div>' : ''}
            <div class="doc-preview-signature">
              <div class="name">HON. GLENN S. EVANGELISTA</div>
              <div>Punong Barangay</div>
            </div>
            <div class="doc-preview-qr">
              <div class="doc-preview-qr-box">
                ${qrUrl !== '' ? `<img src="${esc(qrUrl)}" alt="QR Code">` : '<span>QR</span>'}
              </div>
              ${isIndigency ? 'QR' : 'QR PLACEMENT'}
            </div>
            ${isIndigency ? '<div class="doc-preview-footer">This certificate is valid for Forty-five (45) days from the date of issue, check the<br>QR code to verify the authenticity of this document.</div>' : ''}
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

  function switchViewMode(mode) {
    viewMode = mode === 'preview' ? 'preview' : 'details';
    if (!viewDetailsBody) return;

    if (viewMode === 'preview') {
      viewDetailsBody.innerHTML = renderDocumentPreview(viewPreviewState);
      bindPreviewEditHandlers();
      viewModalBackBtn?.classList.remove('d-none');
      if (viewModalBackBtn) {
        viewModalBackBtn.textContent = 'Cancel';
      }
      if (viewModalNextBtn) {
        viewModalNextBtn.textContent = 'Save and Approve';
        viewModalNextBtn.classList.remove('d-none', 'btn-primary');
        viewModalNextBtn.classList.add('btn-success');
        viewModalNextBtn.disabled = false;
      }
      return;
    }

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

  paymentProofModalEl?.addEventListener('hidden.bs.modal', () => {
    paymentProofReturnTarget = '';
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
  });

  function rowHtml(row) {
    const reasonValue = firstNonEmpty([row.status_remarks, row.status_reason]);
    const reason = reasonValue ? `<div class="text-danger small mt-1">Reason: ${esc(reasonValue)}</div>` : '';
    const fullName = fullNameFromRow(row);
    const purpose = firstNonEmpty([row.purpose, '-']);
    return `
      <tr>
        <td class="fw-semibold">${esc(row.request_id)}</td>
        <td>${esc(row.resident_id || '-')}</td>
        <td>${esc(fullName)}</td>
        <td>${documentTypeBadgeBlue(row)}</td>
        <td>
          <div class="cell-purpose">${esc(purpose)}</div>
        </td>
        <td>${badge(row.stage, esc(row.stage_label || row.stage || ''))}${reason}</td>
        <td>${esc(row.submitted_at || '-')}</td>
        <td>${actionButtons(row)}</td>
      </tr>
    `;
  }

  function openDocumentModal(docUrl, title = 'Document Viewer', returnTarget = '') {
    if (!docUrl || !paymentProofModal || !paymentProofWrap || !paymentProofOpenNew) return;
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
    paymentProofOpenNew.href = docUrl;
    const lower = String(docUrl).toLowerCase();
    if (lower.endsWith('.pdf')) {
      paymentProofWrap.innerHTML = `<iframe src="${docUrl}" style="width:100%;height:70vh;border:1px solid #ddd;border-radius:8px;"></iframe>`;
    } else {
      paymentProofWrap.innerHTML = `<img src="${docUrl}" alt="Document Preview" style="max-width:100%;max-height:70vh;border:1px solid #ddd;border-radius:8px;">`;
    }
    paymentProofModal.show();
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
      const q = (searchInput.value || '').trim();
      if (q) params.set('q', q);

      const data = await fetchJson(`${endpoint}?${params.toString()}`);
      if (!data.success) throw new Error(data.message || 'Failed to load requests.');

      const allItems = Array.isArray(data.items) ? data.items : [];
      updateStageTabBadges(allItems);
      const stageItems = currentStage === 'finance'
        ? allItems.filter((it) => financeStages.has(String(it.stage || '').toLowerCase()))
        : allItems.filter((it) => matchesStageTabFilter(it, currentStage));
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
    if (actionPrompt) {
      actionPrompt.classList.add('d-none');
      actionPrompt.textContent = '';
    }
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
    if (actionCancelBtn) {
      actionCancelBtn.textContent = 'Return';
    }
    if (actionSubmitBtn) {
      actionSubmitBtn.textContent = 'Submit';
      actionSubmitBtn.classList.remove('btn-danger', 'btn-success');
      actionSubmitBtn.classList.add('btn-primary');
    }
    actionForm?.querySelector('.modal-footer')?.classList.remove('action-split');
  }

  function clearModalError() {
    modalError.classList.add('d-none');
    modalError.textContent = '';
  }

  function openActionModal(type, requestId) {
    if (!actionModal) return;

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

    const labels = {
      personnel_approve: 'Before You Approve',
      personnel_reject: 'Reject Request',
      finance_verify: 'Verify Payment',
      finance_reject: 'Reject Payment',
      mark_ready: 'Mark Ready for Claim',
      mark_completed: 'Mark Completed'
    };
    modalTitle.textContent = labels[type] || 'Update Request';
    const docName = normalizeDocumentTypeDisplay(String(row?.document_type || 'document'));

    if (type === 'personnel_approve' && actionPrompt) {
      actionPrompt.textContent = 'Click the view the document to check the document that will be issued and edit it if there are necessary changes in the details. Once everything is correct, proceed to verify the Certificate Of Indigency.';
      actionPrompt.classList.remove('d-none');
    }
    if ((type === 'personnel_reject' || type === 'finance_reject') && actionPrompt) {
      actionPrompt.textContent = 'Please provide the reason for rejection.';
      actionPrompt.classList.remove('d-none');
    }
    if (actionSubmitBtn) {
      if (type === 'personnel_approve') {
        actionSubmitBtn.textContent = 'View Document (Next)';
        actionSubmitBtn.classList.remove('btn-danger', 'btn-success');
        actionSubmitBtn.classList.add('btn-primary');
      } else if (type === 'personnel_reject') {
        actionSubmitBtn.textContent = 'Reject';
        actionSubmitBtn.classList.remove('btn-primary', 'btn-success');
        actionSubmitBtn.classList.add('btn-danger');
      }
    }
    if (type === 'personnel_approve' || type === 'personnel_reject') {
      actionForm?.querySelector('.modal-footer')?.classList.add('action-split');
    }

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
        const residentProfile = row.resident_profile && typeof row.resident_profile === 'object'
          ? row.resident_profile
          : {};
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
          'cohabitant_full_subdivision', 'cohabitant_full_barangay', 'cohabitant_full_area_number'
        ]);

        const requestFields = [];
        const purposeText = firstNonEmpty([row.purpose, payload.purpose, payload.request_purpose]);
        if (purposeText) {
          consumedKeys.add('purpose');
          consumedKeys.add('request_purpose');
          requestFields.push({ label: 'Purpose', value: purposeText });
        }
        const officerText = firstNonEmpty([payload.request_officer]);
        if (officerText) {
          consumedKeys.add('request_officer');
          requestFields.push({ label: 'To Be Submitted To', value: officerText });
        }

        Object.keys(payload).forEach((key) => {
          const normalized = String(key);
          if (consumedKeys.has(normalized) || technicalKeys.has(normalized)) return;
          const value = payload[key];
          if (value === null || value === undefined) return;
          const text = String(value).trim();
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
                 <div class="tracker-form-value d-flex justify-content-between align-items-center gap-2">
                   <span class="text-truncate">${esc(proofResidencyName || proofResidencyPath)}</span>
                   <button type="button" class="btn btn-sm btn-primary" data-view-doc-url="${esc(proofResidencyUrl)}" data-view-doc-title="${esc(proofResidencyTitle)}">View</button>
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
            <div class="tracker-form-field">
              <p class="tracker-form-label">${esc(doc.label || `Document ${idx + 1}`)}</p>
              <div class="tracker-form-value d-flex justify-content-between align-items-center gap-2">
                <span class="text-truncate">${esc(doc.path || '')}</span>
                <button type="button" class="btn btn-sm btn-primary" data-view-doc-url="${esc(doc.url)}" data-view-doc-title="${esc(doc.label || 'Submitted Document')}">View</button>
              </div>
            </div>
          `).join('');
          html += formSection('Submitted Documents', `<div class="tracker-form-grid cols-1">${docsHtml}</div>`);
        }

        const stageKeyForStatus = String(row.stage || '').toLowerCase();
        const isRejectedStatus = stageKeyForStatus.includes('rejected') || stageKeyForStatus === 'cancelled';
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
          { label: 'Submitted At', value: row.submitted_at || '-' }
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
        const businessName = firstNonEmpty([
          payload.business_name,
          payload.businessName,
          payload.business_trade_name,
          payload.trade_name,
          payload.establishment_name,
          payload.business_establishment
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
        const cohabitationDuration = [durationValue, durationUnit].filter(Boolean).join(' ').trim();
        const knownPayloadKeys = new Set([
          'action', 'csrf_token', 'redirect', 'document_type',
          'last_name', 'lastname', 'first_name', 'firstname', 'middle_name', 'middlename', 'suffix', 'suffix_name',
          'contact_number', 'phone_number', 'full_address', 'full_address_display', 'address', 'complete_address',
          'birthdate', 'date_of_birth', 'child_dob', 'age', 'sex', 'gender', 'child_sex',
          'civil_status', 'religion', 'occupation',
          'purpose', 'request_purpose', 'request_officer',
          'business_name', 'businessName', 'business_trade_name', 'trade_name', 'establishment_name', 'business_establishment',
          'years_of_residency', 'months_of_residency',
          'child_birthplace', 'child_nationality',
          'father_first_name', 'father_middle_name', 'father_last_name', 'father_suffix',
          'mother_first_name', 'mother_middle_name', 'mother_last_name', 'mother_suffix',
          'cohabitant_first', 'cohabitant_middle', 'cohabitant_last', 'cohabitant_suffix',
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
        viewPreviewState = {
          docType: row.document_type || 'Certificate',
          fullName: [
            personalMap.get('First Name') || '',
            personalMap.get('Middle Name') || '',
            personalMap.get('Last Name') || '',
            personalMap.get('Suffix') || ''
          ].join(' ').replace(/\s+/g, ' ').trim() || fullNameFromRow(row),
          fullAddress: stripAreaFromAddress(personalMap.get('Full Address') || '-') || '-',
          purpose: firstNonEmpty([row.purpose, payload.purpose, payload.request_purpose, '-']) || '-',
          businessName: businessName || '',
          issuedDate: row.submitted_at || dr_now_text(),
          requestOfficer: firstNonEmpty([payload.request_officer]),
          requestFor: firstNonEmpty([payload.request_purpose]),
          yearsResidency: firstNonEmpty([payload.years_of_residency]),
          monthsResidency: firstNonEmpty([payload.months_of_residency]),
          childBirthplace: firstNonEmpty([payload.child_birthplace]),
          childBirthdate: firstNonEmpty([payload.child_dob, payload.birthdate, payload.date_of_birth]),
          childNationality: firstNonEmpty([payload.child_nationality]),
          fatherName: fatherName,
          motherName: motherName,
          cohabitantName: cohabitantName,
          cohabitantRelationship: firstNonEmpty([payload.cohabitant_relationship]),
          cohabitationDuration: cohabitationDuration,
          cohabitationStartDate: firstNonEmpty([payload.cohabitation_start_date]),
          educationalAttainment: firstNonEmpty([payload.educational_attainment]),
          jobstartBeneficiary: firstNonEmpty([payload.jobstart_beneficiary]),
          additionalDetails: additionalDetails,
          qrUrl: resolvePublicUrl(firstNonEmpty([row.qr_code_path]))
        };
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
          viewModalTitle.textContent = requestId ? `Certificate Request (#${requestId})` : 'Certificate Request';
        }
        if (viewModalActions) {
          viewModalActions.innerHTML = '';
        }
        viewDetailsBody.querySelectorAll('.tracker-status-actions button[data-view-action][data-id]').forEach((actionBtn) => {
          actionBtn.addEventListener('click', () => {
            openActionModal(actionBtn.getAttribute('data-view-action') || '', actionBtn.getAttribute('data-id') || '');
          });
        });
        viewDetailsBody.querySelectorAll('.tracker-status-actions button[data-proof-id]').forEach((proofBtn) => {
          proofBtn.addEventListener('click', () => {
            const proofId = String(proofBtn.getAttribute('data-proof-id') || '');
            const proofRow = itemById.get(proofId);
            if (!proofRow || !proofRow.payment_proof_path || !paymentProofModal || !paymentProofWrap || !paymentProofOpenNew) return;
            const proofUrl = `${appBase}/PhpFiles/Admin-End/documentRequestWorkflow.php?action=view_payment_proof&request_id=` + encodeURIComponent(proofId);
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
        viewDetailsBody.querySelectorAll('button[data-inline-profile-id]').forEach((profileBtn) => {
          profileBtn.addEventListener('click', () => {
            openResidentProfileModal(
              String(profileBtn.getAttribute('data-inline-profile-id') || ''),
              String(profileBtn.getAttribute('data-inline-profile-user-id') || ''),
              row?.resident_profile && typeof row.resident_profile === 'object' ? row.resident_profile : null
            );
          });
        });
        viewModal.show();
      });
    });

    tableBody.querySelectorAll('button[data-proof-id]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = String(btn.getAttribute('data-proof-id') || '');
        const row = itemById.get(id);
        if (!row || !row.payment_proof_path || !paymentProofModal || !paymentProofWrap || !paymentProofOpenNew) return;

        const proofUrl = `${appBase}/PhpFiles/Admin-End/documentRequestWorkflow.php?action=view_payment_proof&request_id=` + encodeURIComponent(id);
        openDocumentModal(proofUrl, 'Payment Proof', '');
      });
    });

  }

  actionForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearModalError();

    if ((actionType.value || '') === 'personnel_approve') {
      // "View Document (Next)" only opens preview; it does not approve yet.
      suppressActionReturn = true;
      openPreviewAfterActionModal = true;
      actionModal.hide();
      return;
    }

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

      suppressActionReturn = true;
      actionModal.hide();
      await load();
    } catch (err) {
      modalError.textContent = err.message || String(err);
      modalError.classList.remove('d-none');
    }
  });

  actionCancelBtn?.addEventListener('click', () => {
    suppressActionReturn = false;
  });

  actionModalEl?.addEventListener('hidden.bs.modal', () => {
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

  btnRefreshList?.addEventListener('click', () => {
    load();
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
      const fd = new FormData();
      fd.append('action', 'personnel_approve');
      fd.append('request_id', rid);
      viewModalNextBtn.disabled = true;
      fetchJson(endpoint, { method: 'POST', body: fd })
        .then((data) => {
          if (!data.success) throw new Error(data.message || 'Unable to approve request.');
          viewModal.hide();
          return load();
        })
        .catch((err) => {
          alert(err.message || String(err));
        })
        .finally(() => {
          if (viewModalNextBtn) viewModalNextBtn.disabled = false;
        });
      return;
    }
    switchViewMode('preview');
  });

  viewModalBackBtn?.addEventListener('click', () => {
    switchViewMode('details');
  });

  viewModalEl?.addEventListener('hidden.bs.modal', () => {
    if (preserveViewStateOnNextHide) {
      preserveViewStateOnNextHide = false;
      return;
    }
    viewDetailsHtml = '';
    viewPreviewState = null;
    currentViewRequestId = '';
    switchViewMode('details');
  });

  load();
})();
