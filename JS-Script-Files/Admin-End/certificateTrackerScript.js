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
  const filterButton = document.getElementById('filterButton');
  const stageTabs = Array.from(document.querySelectorAll('[data-stage-filter]'));
  const btnRefreshList = document.getElementById('btnRefreshList');
  const modalFilterEl = document.getElementById('modalFilter');
  const modalFilter = modalFilterEl ? new bootstrap.Modal(modalFilterEl) : null;
  const filterStatusList = document.getElementById('filterStatusList');
  const filterAreaList = document.getElementById('filterAreaList');
  const btnApplyFilter = document.getElementById('btnApplyFilter');
  const btnResetModalFilters = document.getElementById('btnResetModalFilters');

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
  const viewModalTitle = document.getElementById('viewModalTitle');
  const viewDetailsBody = document.getElementById('viewDetailsBody');
  const viewModalActions = document.getElementById('viewModalActions');
  const viewModalBackBtn = document.getElementById('viewModalBackBtn');
  const viewModalNextBtn = document.getElementById('viewModalNextBtn');
  const paymentProofModalEl = document.getElementById('paymentProofModal');
  const paymentProofModal = paymentProofModalEl ? new bootstrap.Modal(paymentProofModalEl) : null;
  const paymentProofWrap = document.getElementById('paymentProofWrap');
  const paymentProofOpenNew = document.getElementById('paymentProofOpenNew');
  const residentProfileModalEl = document.getElementById('residentProfileModal');
  const residentProfileModal = residentProfileModalEl ? new bootstrap.Modal(residentProfileModalEl) : null;
  const residentProfileEndpoint = `${appBase}/PhpFiles/Admin-End/residentMasterlist.php`;

  let currentStage = String(window.CERT_TRACKER_DEFAULT_STAGE || '');
  let itemById = new Map();
  let viewMode = 'details';
  let viewDetailsHtml = '';
  let viewPreviewState = null;
  let selectedStatusFilters = new Set();
  let selectedAreaFilters = new Set();

  function esc(v) {
    return String(v ?? '').replace(/[&<>\"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '\"': '&quot;', "'": '&#39;' }[m]));
  }

  function resolvePublicUrl(path) {
    const raw = String(path || '').trim();
    if (!raw) return '';
    if (/^https?:\/\//i.test(raw)) return raw;
    if (appBase && raw.startsWith(`${appBase}/`)) return raw;
    if (raw.startsWith('/')) return `${appBase}${raw}`;
    return `${appBase}/${raw.replace(/^\/+/, '')}`;
  }

  function badge(stage, label) {
    const k = String(stage || '').toLowerCase();
    if (k.includes('rejected')) return `<span class="badge bg-danger">${label}</span>`;
    if (k === 'inspection_failed') return `<span class="badge bg-danger">${label}</span>`;
    if (k === 'completed') return `<span class="badge bg-success">${label}</span>`;
    if (k === 'ready_for_claim') return `<span class="badge bg-primary">${label}</span>`;
    if (k === 'for_interview') return `<span class="badge bg-info text-dark">${label}</span>`;
    if (k === 'for_inspection') return `<span class="badge bg-info-subtle text-dark">${label}</span>`;
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
    const residentUserId = firstNonEmpty([row.resident_user_id, row.user_id]);
    const profileBtn = (residentId || residentUserId)
      ? `<button class="btn btn-sm btn-outline-primary" data-view-profile-id="${esc(residentId)}" data-view-profile-user-id="${esc(residentUserId)}">View Profile</button>`
      : '';
    const proofBtn = row.payment_proof_path
      ? `<button class="btn btn-sm btn-outline-dark" data-proof-id="${id}">View Payment</button>`
      : '';

    if (stage === 'submitted') {
      return `
        ${profileBtn}
        ${proofBtn}
        <button class="btn btn-sm btn-outline-info" data-view-action="personnel_interview" data-id="${id}">For Interview</button>
        <button class="btn btn-sm btn-outline-info" data-view-action="personnel_inspection" data-id="${id}">For Inspection</button>
        <button class="btn btn-sm btn-success" data-view-action="personnel_approve" data-id="${id}">Approve</button>
        <button class="btn btn-sm btn-danger" data-view-action="personnel_reject" data-id="${id}">Reject</button>
      `;
    }
    if (stage === 'for_interview') {
      return `
        ${profileBtn}
        ${proofBtn}
        <button class="btn btn-sm btn-outline-info" data-view-action="personnel_inspection" data-id="${id}">For Inspection</button>
        <button class="btn btn-sm btn-success" data-view-action="personnel_approve" data-id="${id}">Approve</button>
        <button class="btn btn-sm btn-danger" data-view-action="personnel_reject" data-id="${id}">Reject</button>
      `;
    }
    if (stage === 'for_inspection') {
      return `
        ${profileBtn}
        ${proofBtn}
        <button class="btn btn-sm btn-outline-danger" data-view-action="personnel_inspection_failed" data-id="${id}">Inspection Failed</button>
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
    return `<span style="display:inline-block;background:#e7f1ff;color:#1f4e8c;font-weight:700;padding:4px 10px;border-radius:8px;">${esc(doc)}</span>`;
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

  function formField(label, value) {
    return `
      <div class="tracker-form-field">
        <p class="tracker-form-label">${esc(label)}</p>
        <div class="tracker-form-value">${esc(value ?? '-')}</div>
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

  function renderFormGrid(fields, preferredCols = 0) {
    const items = Array.isArray(fields) ? fields.filter((v) => String(v || '').trim() !== '') : [];
    if (!items.length) return '';
    const count = items.length;
    const cols = Math.max(1, Math.min(preferredCols > 0 ? preferredCols : count, count, 4));
    const className = cols === 4 ? 'tracker-form-grid cols-4'
      : cols === 3 ? 'tracker-form-grid cols-3'
      : cols === 1 ? 'tracker-form-grid cols-1'
      : 'tracker-form-grid';
    return `<div class="${className}">${items.join('')}</div>`;
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
      viewModalNextBtn?.classList.add('d-none');
      return;
    }

    viewDetailsBody.innerHTML = viewDetailsHtml || '<div class="text-muted">No details.</div>';
    viewModalBackBtn?.classList.add('d-none');
    viewModalNextBtn?.classList.remove('d-none');
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

  async function openResidentProfileModal(residentId, residentUserId = '') {
    const rid = String(residentId || '').trim();
    const uid = String(residentUserId || '').trim();
    const searchToken = rid || uid;
    if (!searchToken || !residentProfileModal) return;
    if (viewModalEl && viewModalEl.classList.contains('show') && viewModal) {
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
      if (!match) return;
      fillResidentProfileModal(match);
    } catch (_) {
      // Keep modal open with placeholders if fetch fails.
    }
  }

  function rowHtml(row) {
    const reason = row.status_remarks ? `<div class="text-danger small mt-1">Reason: ${esc(row.status_remarks)}</div>` : '';
    const fullName = fullNameFromRow(row);
    const residentAddress = firstNonEmpty([
      row?.payload?.full_address,
      row?.payload?.full_address_display,
      row?.payload?.address,
      row?.payload?.complete_address,
      '-'
    ]) || '-';
    const residentInfo = residentInfoFromRow(row);
    const purpose = firstNonEmpty([row.purpose, '-']);
    const documentRequested = firstNonEmpty([row.document_type, '-']);
    return `
      <tr data-resident-info="${esc(residentInfo)}" data-resident-address="${esc(residentAddress)}">
        <td class="fw-semibold"><span class="cell-truncate" title="${esc(row.request_id)}">${esc(row.request_id)}</span></td>
        <td><span class="cell-truncate" title="${esc(row.resident_id || '-')}">${esc(row.resident_id || '-')}</span></td>
        <td><span class="cell-truncate" title="${esc(fullName)}">${esc(fullName)}</span></td>
        <td>${documentTypePill({ document_type: documentRequested })}</td>
        <td class="col-purpose-cell">
          <div class="cell-purpose" title="${esc(purpose)}">${esc(purpose)}</div>
          <div class="mt-1 d-none">${esc(residentAddress)}</div>
        </td>
        <td>${badge(row.stage, esc(row.stage_label || row.stage || ''))}${reason}</td>
        <td><span class="cell-truncate" title="${esc(row.submitted_at || '-')}">${esc(row.submitted_at || '-')}</span></td>
        <td>${actionButtons(row)}</td>
      </tr>
    `;
  }

  function statusBucket(row) {
    const stage = String(row?.stage || '').toLowerCase();
    if (stage.includes('rejected')) return 'rejected';
    if (stage === 'inspection_failed') return 'inspection_failed';
    if (stage === 'submitted') return 'submitted';
    if (stage === 'for_interview') return 'for_interview';
    if (stage === 'for_inspection') return 'for_inspection';
    if (stage === 'for_payment') return 'for_payment';
    if (stage === 'payment_submitted') return 'pending_payment';
    if (stage === 'ready_for_claim' || stage === 'payment_verified') return 'release';
    if (stage === 'completed') return 'completed';
    return 'pending';
  }

  function extractArea(row) {
    const payload = row?.payload && typeof row.payload === 'object' ? row.payload : {};
    const residentProfile = row?.resident_profile && typeof row.resident_profile === 'object' ? row.resident_profile : {};
    const raw = firstNonEmpty([
      payload.full_area_number,
      payload.area_number,
      payload.area,
      payload.full_area,
      residentProfile.area_number
    ]);
    if (!raw) {
      const addressProbe = firstNonEmpty([
        payload.full_address,
        payload.full_address_display,
        payload.address,
        payload.complete_address,
        residentProfile.full_address
      ]);
      if (addressProbe) {
        const m = String(addressProbe).match(/\bArea\s*([A-Za-z0-9-]+)\b/i);
        if (m && m[1]) {
          return `Area ${String(m[1]).trim()}`;
        }
      }
      return 'N/A';
    }
    const compact = raw.replace(/\s+/g, ' ').trim();
    return /^area\s+/i.test(compact) ? compact : `Area ${compact}`;
  }

  function matchesModalFilters(row) {
    if (selectedStatusFilters.size > 0 && !selectedStatusFilters.has(statusBucket(row))) return false;
    if (selectedAreaFilters.size > 0 && !selectedAreaFilters.has(extractArea(row))) return false;
    return true;
  }

  function stageBucket(row) {
    const stage = String(row?.stage || '').toLowerCase();
    if (stage === 'completed') return 'completed';
    if (stage === 'ready_for_claim') return 'release';
    return 'pending';
  }

  function matchesStageFilter(row) {
    if (!currentStage) return true;
    return stageBucket(row) === currentStage;
  }

  function renderFilterChecklistOptions(items) {
    if (!filterStatusList || !filterAreaList) return;

    const statusOptions = [
      { key: 'submitted', label: 'Pending Verification' },
      { key: 'for_interview', label: 'For Interview' },
      { key: 'for_inspection', label: 'For Inspection' },
      { key: 'inspection_failed', label: 'Inspection Failed' },
      { key: 'for_payment', label: 'For Payment' },
      { key: 'pending_payment', label: 'Pending Payment' },
      { key: 'release', label: 'Release' },
      { key: 'completed', label: 'Completed' },
      { key: 'rejected', label: 'Rejected' }
    ];

    filterStatusList.innerHTML = statusOptions.map((opt) => `
      <div class="form-check">
        <input class="form-check-input tracker-filter-status" type="checkbox" value="${esc(opt.key)}" id="filterStatus_${esc(opt.key)}" ${selectedStatusFilters.has(opt.key) ? 'checked' : ''}>
        <label class="form-check-label small" for="filterStatus_${esc(opt.key)}">${esc(opt.label)}</label>
      </div>
    `).join('');

    const areas = Array.from(new Set((items || []).map(extractArea))).sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
    filterAreaList.innerHTML = areas.map((area, idx) => `
      <div class="form-check">
        <input class="form-check-input tracker-filter-area" type="checkbox" value="${esc(area)}" id="filterArea_${idx}" ${selectedAreaFilters.has(area) ? 'checked' : ''}>
        <label class="form-check-label small" for="filterArea_${idx}">${esc(area)}</label>
      </div>
    `).join('') || '<div class="small text-muted">No area values found.</div>';
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
      const stageItems = allItems.filter(matchesStageFilter);
      renderFilterChecklistOptions(stageItems);
      const items = stageItems
        .filter(matchesModalFilters);
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
      personnel_interview: 'Set For Interview',
      personnel_inspection: 'Set For Inspection',
      personnel_inspection_failed: 'Mark Inspection Failed',
      personnel_reject: 'Reject Request',
      finance_verify: 'Verify Payment',
      finance_reject: 'Reject Payment',
      mark_ready: 'Mark Ready for Claim',
      mark_completed: 'Mark Completed'
    };
    modalTitle.textContent = labels[type] || 'Update Request';

    if (type === 'personnel_reject' || type === 'finance_reject' || type === 'personnel_inspection_failed') {
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
          ['Last Name', firstNonEmpty([collectFirst('last_name', 'lastname'), collectResidentFirst('last_name')])],
          ['First Name', firstNonEmpty([collectFirst('first_name', 'firstname'), collectResidentFirst('first_name')])],
          ['Middle Name', firstNonEmpty([collectFirst('middle_name', 'middlename'), collectResidentFirst('middle_name')])],
          ['Suffix', firstNonEmpty([collectFirst('suffix_name', 'suffix'), collectResidentFirst('suffix')])],
          ['Contact Number', firstNonEmpty([collectFirst('contact_number', 'phone_number'), collectResidentFirst('contact_number')])],
          ['Full Address', firstNonEmpty([collectFirst('full_address', 'full_address_display', 'address', 'complete_address'), collectResidentFirst('full_address')])],
          ['Birthdate', firstNonEmpty([collectFirst('birthdate', 'date_of_birth', 'child_dob'), collectResidentFirst('birthdate')])],
          ['Age', firstNonEmpty([collectFirst('age'), collectResidentFirst('age')])],
          ['Sex', firstNonEmpty([collectFirst('sex', 'gender', 'child_sex'), collectResidentFirst('sex')])],
          ['Civil Status', firstNonEmpty([collectFirst('civil_status'), collectResidentFirst('civil_status')])]
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
        const purposeText = firstNonEmpty([row.purpose, payload.purpose]);
        if (purposeText) {
          requestFields.push(formField('Purpose', purposeText));
        }
        const purposeKeys = new Set(['purpose', 'request_purpose']);

        Object.keys(payload).forEach((key) => {
          const normalized = String(key);
          if (consumedKeys.has(normalized) || technicalKeys.has(normalized) || purposeKeys.has(normalized)) return;
          const value = payload[key];
          if (value === null || value === undefined) return;
          const text = String(value).trim();
          if (text === '') return;
          requestFields.push(formField(friendlyLabel(normalized), text));
        });

        let html = '';
        html += `<div class="tracker-doc-highlight">Document Requested: ${esc(row.document_type || '-')}</div>`;
        const personalMap = new Map(personalFields);
        if (personalFields.length) {
          const nameFields = [
            formField('Last Name', personalMap.get('Last Name') || '-'),
            formField('First Name', personalMap.get('First Name') || '-'),
            formField('Middle Name', personalMap.get('Middle Name') || '-'),
            (personalMap.get('Suffix') || '').trim() ? formField('Suffix', personalMap.get('Suffix')) : ''
          ];
          const personalFieldsRow = [
            formField('Birthdate', personalMap.get('Birthdate') || '-'),
            formField('Age', personalMap.get('Age') || '-'),
            formField('Sex', personalMap.get('Sex') || '-'),
            formField('Civil Status', personalMap.get('Civil Status') || '-')
          ];
          const contactAddressFields = [
            formField('Contact Number', personalMap.get('Contact Number') || '-'),
            formField('Full Address', personalMap.get('Full Address') || '-')
          ];
          const extraInfo = [];

          let personalHtml = '';
          personalHtml += renderFormGrid(nameFields, 4);
          personalHtml += renderFormGrid(personalFieldsRow, 4);
          personalHtml += renderFormGrid(contactAddressFields, 2);
          personalHtml += renderFormGrid(extraInfo, 2);
          html += formSection('Personal Information', personalHtml);
        }
        if (requestFields.length) {
          html += formSection('Request Details', renderFormGrid(requestFields, 2));
        }

        const submittedDocs = extractSubmittedDocuments(row, payload);
        if (submittedDocs.length) {
          const docsHtml = submittedDocs.map((doc, idx) => `
            <div class="tracker-form-field">
              <p class="tracker-form-label">${esc(doc.label || `Document ${idx + 1}`)}</p>
              <div class="tracker-form-value d-flex justify-content-between align-items-center gap-2">
                <span class="text-truncate">${esc(doc.path || '')}</span>
                <button type="button" class="btn btn-sm btn-outline-primary" data-view-doc-url="${esc(doc.url)}">View</button>
              </div>
            </div>
          `).join('');
          html += formSection('Submitted Documents', `<div class="tracker-form-grid cols-1">${docsHtml}</div>`);
        }

        html += formSection(
          'Request Status',
          renderFormGrid([
            formField('Status', row.stage_label || row.stage || '-'),
            formField('Status Remarks', row.status_remarks || '-'),
            formField('Submitted At', row.submitted_at || '-')
          ], 3)
        );

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
          fullAddress: personalMap.get('Full Address') || '-',
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
        if (viewModalTitle) {
          const requestId = String(row.request_id || '').trim();
          viewModalTitle.textContent = requestId ? `Certificate Request (#${requestId})` : 'Certificate Request';
        }
        if (viewModalActions) {
          viewModalActions.innerHTML = viewModalActionButtons(row);
          viewModalActions.querySelectorAll('button[data-view-action][data-id]').forEach((actionBtn) => {
            actionBtn.addEventListener('click', () => {
              openActionModal(actionBtn.getAttribute('data-view-action') || '', actionBtn.getAttribute('data-id') || '');
            });
          });
          viewModalActions.querySelectorAll('button[data-view-profile-id]').forEach((profileBtn) => {
            profileBtn.addEventListener('click', () => {
              openResidentProfileModal(
                String(profileBtn.getAttribute('data-view-profile-id') || ''),
                String(profileBtn.getAttribute('data-view-profile-user-id') || '')
              );
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
        viewDetailsBody.querySelectorAll('button[data-view-doc-url]').forEach((docBtn) => {
          docBtn.addEventListener('click', () => {
            const docUrl = String(docBtn.getAttribute('data-view-doc-url') || '').trim();
            if (!docUrl || !paymentProofModal || !paymentProofWrap || !paymentProofOpenNew) return;
            paymentProofOpenNew.href = docUrl;
            const lower = docUrl.toLowerCase();
            if (lower.endsWith('.pdf')) {
              paymentProofWrap.innerHTML = `<iframe src="${docUrl}" style="width:100%;height:70vh;border:1px solid #ddd;border-radius:8px;"></iframe>`;
            } else {
              paymentProofWrap.innerHTML = `<img src="${docUrl}" alt="Submitted Document" style="max-width:100%;max-height:70vh;border:1px solid #ddd;border-radius:8px;">`;
            }
            paymentProofModal.show();
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

  btnRefreshList?.addEventListener('click', () => {
    btnRefreshList.classList.add('is-loading');
    load();
    setTimeout(() => btnRefreshList.classList.remove('is-loading'), 450);
  });

  filterButton?.addEventListener('click', () => {
    modalFilter?.show();
  });

  btnApplyFilter?.addEventListener('click', () => {
    selectedStatusFilters = new Set(
      Array.from(document.querySelectorAll('.tracker-filter-status:checked'))
        .map((el) => String(el.value || '').trim())
        .filter((v) => v !== '')
    );
    selectedAreaFilters = new Set(
      Array.from(document.querySelectorAll('.tracker-filter-area:checked'))
        .map((el) => String(el.value || '').trim())
        .filter((v) => v !== '')
    );
    modalFilter?.hide();
    load();
  });

  btnResetModalFilters?.addEventListener('click', () => {
    selectedStatusFilters.clear();
    selectedAreaFilters.clear();
    document.querySelectorAll('.tracker-filter-status, .tracker-filter-area').forEach((el) => {
      el.checked = false;
    });
    load();
  });

  let searchTimer = null;
  searchInput?.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(load, 250);
  });

  viewModalNextBtn?.addEventListener('click', () => {
    switchViewMode('preview');
  });

  viewModalBackBtn?.addEventListener('click', () => {
    switchViewMode('details');
  });

  viewModalEl?.addEventListener('hidden.bs.modal', () => {
    viewDetailsHtml = '';
    viewPreviewState = null;
    switchViewMode('details');
  });

  load();
})();
