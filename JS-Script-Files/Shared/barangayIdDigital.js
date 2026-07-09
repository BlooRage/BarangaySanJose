(() => {
  const PAGE_WIDTH_MM = 85.6;
  const PAGE_HEIGHT_MM = 54.1;
  const TEMPLATE_ASSET_VERSION = '20260321-02';

  function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (match) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;'
    }[match]));
  }

  function firstNonEmpty(values, fallback = '') {
    const list = Array.isArray(values) ? values : [values];
    for (const value of list) {
      const text = String(value ?? '').trim();
      if (text !== '') {
        return text;
      }
    }
    return fallback;
  }

  function upper(value, fallback = '') {
    const text = String(value ?? '').trim();
    if (!text) return fallback;
    return text.toUpperCase();
  }

  function safeSubstring(value, start, length = null) {
    const text = String(value ?? '');
    if (typeof text.substring === 'function') {
      return length === null ? text.substring(start) : text.substring(start, start + length);
    }
    return text;
  }

  function resolvePublicUrl(appBase, path) {
    const raw = String(path || '').trim();
    if (!raw) return '';
    if (/^https?:\/\//i.test(raw)) return raw;
    if (/^data:/i.test(raw)) return raw;

    let normalized = raw.replace(/\\/g, '/');
    if (normalized.startsWith(appBase + '/')) {
      return normalized;
    }
    if (normalized.startsWith('/')) {
      return `${appBase}${normalized}`;
    }
    normalized = normalized.replace(/^(\.\.\/)+/, '').replace(/^\.\//, '');
    return `${appBase}/${normalized.replace(/^\/+/, '')}`;
  }

  function normalizePhone(value) {
    const digits = String(value ?? '').replace(/\D+/g, '');
    if (!digits) return '';
    if (digits.length === 10 && digits.startsWith('9')) return `0${digits}`;
    if (digits.length === 12 && digits.startsWith('63')) return `0${digits.slice(2)}`;
    return digits;
  }

  function stripAreaFromAddress(address) {
    const raw = String(address || '').trim();
    if (!raw) return '';
    return raw
      .replace(/\s*,\s*Area(?:\s+Area)*\s+[A-Za-z0-9-]+\s*(?=,|$)/gi, '')
      .replace(/(^|,\s*)Area(?:\s+Area)*\s+[A-Za-z0-9-]+\s*,\s*/gi, '$1')
      .replace(/\s+,/g, ',')
      .replace(/,\s*,/g, ', ')
      .replace(/\s{2,}/g, ' ')
      .replace(/^,\s*|\s*,\s*$/g, '')
      .trim();
  }

  function issuedResidenceAddress(address, locality = 'Barangay San Jose, Rodriguez, Rizal') {
    const suffix = String(locality || '').trim();
    const cleaned = stripAreaFromAddress(address);
    if (cleaned && suffix) {
      const normalizedCleaned = cleaned.toLowerCase();
      const normalizedSuffix = suffix.toLowerCase();
      if (normalizedCleaned.includes(normalizedSuffix)) {
        return cleaned;
      }
      return `${cleaned}, ${suffix}`.replace(/,\s*,/g, ', ').trim();
    }
    if (cleaned) return cleaned;
    if (suffix) return suffix;
    return cleaned || '';
  }

  function formatDisplayDate(value) {
    const raw = String(value || '').trim();
    if (!raw) return '';
    const normalized = raw.includes('T') ? raw : raw.replace(' ', 'T');
    const parsed = new Date(normalized);
    if (Number.isNaN(parsed.getTime())) return raw;
    return [
      String(parsed.getMonth() + 1).padStart(2, '0'),
      String(parsed.getDate()).padStart(2, '0'),
      parsed.getFullYear()
    ].join('/');
  }

  function formatCardName(lastName, firstName, middleName, suffix) {
    const last = String(lastName || '').trim();
    const first = String(firstName || '').trim();
    const middle = String(middleName || '').trim();
    const suffixText = String(suffix || '').trim();
    const middleInitial = middle ? `${safeSubstring(middle, 0, 1)}.` : '';
    const trailing = [first, middleInitial, suffixText].filter(Boolean).join(' ');
    return upper(`${last}${trailing ? `, ${trailing}` : ''}`);
  }

  function formatEmergencyName(payload = {}, residentProfile = {}) {
    return formatCardName(
      firstNonEmpty([payload.emergency_last, payload.emergency_last_name, residentProfile.emergency_last_name]),
      firstNonEmpty([payload.emergency_first, payload.emergency_first_name, residentProfile.emergency_first_name]),
      firstNonEmpty([payload.emergency_middle, payload.emergency_middle_name, residentProfile.emergency_middle_name]),
      firstNonEmpty([payload.emergency_suffix, residentProfile.emergency_suffix])
    );
  }

  function computeIssuedDate(row = {}) {
    return firstNonEmpty([
      row.release_timestamp,
      row.completed_at,
      row.ready_at,
      row.submitted_at,
      new Date().toISOString()
    ]);
  }

  function computeCardNumber(overrideValue, requestId, issuedDate) {
    const override = upper(overrideValue);
    if (override) return override;
    const normalizedRequestId = String(requestId || '').replace(/\D+/g, '');
    const serial = normalizedRequestId.slice(-4).padStart(4, '0');
    const year = String(new Date(issuedDate || Date.now()).getFullYear() || new Date().getFullYear());
    return `A${year}-${serial}`;
  }

  function computeValidUntil(overrideValue, issuedDate) {
    const rawOverride = String(overrideValue || '').trim();
    if (rawOverride) {
      const formattedOverride = formatDisplayDate(rawOverride);
      return upper(formattedOverride || rawOverride);
    }
    const baseDate = new Date(issuedDate || Date.now());
    if (Number.isNaN(baseDate.getTime())) {
      return '';
    }
    baseDate.setFullYear(baseDate.getFullYear() + 2);
    return formatDisplayDate(baseDate.toISOString());
  }

  function positionX(mm) {
    return `${(mm / PAGE_WIDTH_MM) * 100}%`;
  }

  function positionY(mm) {
    return `${(mm / PAGE_HEIGHT_MM) * 100}%`;
  }

  function widthPct(mm) {
    return `${(mm / PAGE_WIDTH_MM) * 100}%`;
  }

  function heightPct(mm) {
    return `${(mm / PAGE_HEIGHT_MM) * 100}%`;
  }

  function verificationUrl(appBase, row = {}, payload = {}) {
    const requestId = String(firstNonEmpty([
      row.request_id,
      payload.request_id
    ]) || '').trim();
    if (!requestId || typeof window === 'undefined' || !window.location || !window.location.origin) {
      return '';
    }

    const verificationCode = String(firstNonEmpty([
      row.verification_code,
      payload.verification_code,
      requestId
    ]) || '').trim();
    const appOrigin = `${window.location.origin}${appBase}`;
    return `${appOrigin}/transactions?request_id=${encodeURIComponent(requestId)}&vc=${encodeURIComponent(verificationCode || requestId)}`;
  }

  function qrPreviewUrl(appBase, row = {}, payload = {}) {
    const existing = resolvePublicUrl(appBase, firstNonEmpty([
      row.qr_code_path,
      payload.qr_code_path
    ]));
    const verifyUrl = verificationUrl(appBase, row, payload);
    const fallback = verifyUrl
      ? `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(verifyUrl)}`
      : '';

    return {
      primary: existing || fallback,
      fallback: existing && fallback && existing !== fallback ? fallback : '',
    };
  }

  function ensureStyles() {
    if (document.getElementById('barangay-id-digital-styles')) return;
    const style = document.createElement('style');
    style.id = 'barangay-id-digital-styles';
    style.textContent = `
      .barangay-id-digital {
        display: grid;
        gap: 18px;
      }
      .barangay-id-digital__intro {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
      }
      .barangay-id-digital__eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        border-radius: 999px;
        background: #fff2df;
        color: #8a4b00;
        border: 1px solid #f2cf9e;
        font-weight: 700;
        font-size: 0.85rem;
      }
      .barangay-id-digital__copy {
        margin: 0;
        color: #5f4a32;
        font-size: 0.92rem;
      }
      .barangay-id-digital__grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
      }
      .barangay-id-digital__panel {
        display: grid;
        gap: 10px;
      }
      .barangay-id-digital__label {
        font-size: 0.78rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #7a6146;
      }
      .barangay-id-card {
        position: relative;
        width: 100%;
        aspect-ratio: 856 / 541;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 22px 44px rgba(32, 20, 7, 0.18);
        background: linear-gradient(135deg, #fbf6ef 0%, #f0e4d4 100%);
        border: 1px solid rgba(122, 97, 70, 0.18);
        container-type: inline-size;
        container-name: barangay-id-card;
        --bid-body-size: clamp(10.6px, 0.36rem + 0.76vw, 14.6px);
        --bid-label-size: var(--bid-body-size);
        --bid-field-size: var(--bid-body-size);
        --bid-name-size: clamp(12px, 0.46rem + 1.02vw, 18.6px);
        --bid-address-size: var(--bid-body-size);
        --bid-meta-size: var(--bid-body-size);
        --bid-cardno-size: clamp(10.4px, 0.36rem + 0.88vw, 15.8px);
        --bid-cardno-back-size: clamp(10.8px, 0.38rem + 0.96vw, 16.8px);
        --bid-emergency-size: var(--bid-body-size);
        --bid-note-size: clamp(8px, 0.28rem + 0.52vw, 10.8px);
        --bid-photo-bleed: 4%;
      }
      .barangay-id-card__bg {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
      }
      .barangay-id-card__field,
      .barangay-id-card__label,
      .barangay-id-card__cover,
      .barangay-id-card__photo,
      .barangay-id-card__qr,
      .barangay-id-card__note {
        position: absolute;
      }
      .barangay-id-card__cover {
        z-index: 1;
        background: #fff;
      }
      .barangay-id-card__field,
      .barangay-id-card__label,
      .barangay-id-card__photo,
      .barangay-id-card__qr,
      .barangay-id-card__note {
        z-index: 2;
      }
      .barangay-id-card__label {
        color: #111;
        font-family: Arial, Helvetica, sans-serif;
        font-style: italic;
        font-weight: 500;
        line-height: 1.05;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: var(--bid-label-size);
      }
      .barangay-id-card__field {
        color: #111;
        font-family: Arial, Helvetica, sans-serif;
        font-weight: 700;
        line-height: 1.08;
        text-align: left;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        text-transform: uppercase;
        font-size: var(--bid-field-size);
      }
      .barangay-id-card__field--name {
        font-size: var(--bid-name-size);
        letter-spacing: 0.01em;
      }
      .barangay-id-card__field--address,
      .barangay-id-card__field--birthplace {
        font-size: var(--bid-address-size);
      }
      .barangay-id-card__field--address-wrap {
        white-space: normal;
        text-overflow: clip;
        display: -webkit-box;
        display: box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        line-height: 1.02;
        max-height: calc(2 * 1.02em);
        word-break: break-word;
        overflow-wrap: anywhere;
      }
      .barangay-id-card__field--meta {
        font-size: var(--bid-meta-size);
      }
      .barangay-id-card__field--cardno {
        font-size: var(--bid-cardno-size);
        color: #c62828;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-shadow: 0 0 0 rgba(0, 0, 0, 0.01);
      }
      .barangay-id-card__field--cardno-front {
        font-size: var(--bid-cardno-size);
      }
      .barangay-id-card__field--cardno-back {
        font-size: var(--bid-cardno-back-size);
      }
      .barangay-id-card__field--emergency {
        font-size: var(--bid-emergency-size);
        line-height: 1.04;
      }
      .barangay-id-card__photo {
        overflow: hidden;
        border-radius: 0;
        background: transparent;
      }
      .barangay-id-card__photo img,
      .barangay-id-card__qr img {
        width: 100%;
        height: 100%;
        display: block;
      }
      .barangay-id-card__photo img {
        object-fit: cover;
        object-position: center center;
        width: calc(100% + var(--bid-photo-bleed));
        height: calc(100% + var(--bid-photo-bleed));
        max-width: none;
        margin-top: calc(var(--bid-photo-bleed) * -0.5);
        margin-left: calc(var(--bid-photo-bleed) * -0.5);
        border-radius: 0 !important;
        box-shadow: none !important;
      }
      .barangay-id-card__qr img {
        object-fit: fill;
        image-rendering: pixelated;
      }
      .barangay-id-card__photo--placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6b7280;
        font-size: 0.72rem;
        text-align: center;
        padding: 8px;
        font-weight: 700;
      }
      .barangay-id-card__qr {
        border-radius: 0;
        overflow: hidden;
        background: transparent;
        border: 0;
      }
      .barangay-id-card__qr--placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        color: #5c5c5c;
        font-size: var(--bid-address-size);
        font-style: italic;
        font-weight: 700;
        letter-spacing: 0.04em;
        background: rgba(255, 255, 255, 0.78);
        border: 1.5px dashed rgba(222, 113, 12, 0.7);
      }
      .barangay-id-card__note {
        color: #111;
        font-family: Arial, Helvetica, sans-serif;
        font-style: italic;
        font-weight: 500;
        line-height: 1.24;
        font-size: var(--bid-note-size);
        text-align: center;
      }
      @container barangay-id-card (max-width: 500px) {
        .barangay-id-card {
          --bid-body-size: clamp(9.3px, 2.5cqw, 12.6px);
          --bid-label-size: var(--bid-body-size);
          --bid-field-size: var(--bid-body-size);
          --bid-name-size: clamp(10.4px, 2.92cqw, 14.8px);
          --bid-address-size: var(--bid-body-size);
          --bid-meta-size: var(--bid-body-size);
          --bid-cardno-size: clamp(8.9px, 2.46cqw, 12.8px);
          --bid-cardno-back-size: clamp(9.4px, 2.62cqw, 13.6px);
          --bid-emergency-size: var(--bid-body-size);
          --bid-note-size: clamp(7.6px, 1.96cqw, 9.6px);
        }
      }
      @container barangay-id-card (min-width: 760px) {
        .barangay-id-card {
          --bid-body-size: clamp(11px, 0.4rem + 0.74vw, 15px);
          --bid-label-size: var(--bid-body-size);
          --bid-field-size: var(--bid-body-size);
          --bid-name-size: clamp(12.9px, 0.48rem + 0.9vw, 19px);
          --bid-address-size: var(--bid-body-size);
          --bid-meta-size: var(--bid-body-size);
          --bid-cardno-size: clamp(10.8px, 0.4rem + 0.72vw, 16.1px);
          --bid-cardno-back-size: clamp(11.3px, 0.42rem + 0.78vw, 17.1px);
          --bid-emergency-size: var(--bid-body-size);
          --bid-note-size: clamp(8.2px, 0.3rem + 0.44vw, 11px);
        }
      }
      .barangay-id-digital__actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
      }
      @container barangay-id-card (max-width: 420px) {
        .barangay-id-card {
          border-radius: 14px;
        }
        .barangay-id-card__field {
          letter-spacing: 0;
        }
        .barangay-id-card__field--address-wrap {
          line-height: 0.98;
          max-height: calc(2 * 0.98em);
        }
      }
      @media (max-width: 991.98px) {
        .barangay-id-digital__grid {
          grid-template-columns: 1fr;
        }
      }
    `;
    document.head.appendChild(style);
  }

  function createState({
    appBase = '',
    row = {},
    payload = {},
    residentProfile = {},
    profileImageUrl = '',
    fallbackProfileImageUrl = '',
    frontTemplateUrl = '',
    backTemplateUrl = '',
    templateVariant = '',
  } = {}) {
    const requestId = firstNonEmpty([row.request_id]);
    const issuedDate = computeIssuedDate(row);
    const cardNumber = computeCardNumber(
      firstNonEmpty([payload.barangay_id_number, payload.resident_id_number, payload.resident_id_no]),
      requestId,
      issuedDate
    );
    const validUntil = computeValidUntil(
      firstNonEmpty([payload.barangay_id_valid_until, payload.valid_until, row.document_validity, payload.document_validity]),
      issuedDate
    );
    const fullName = formatCardName(
      firstNonEmpty([payload.last_name, payload.lastname, residentProfile.last_name]),
      firstNonEmpty([payload.first_name, payload.firstname, residentProfile.first_name]),
      firstNonEmpty([payload.middle_name, payload.middlename, residentProfile.middle_name]),
      firstNonEmpty([payload.suffix_name, payload.suffix, residentProfile.suffix])
    ) || upper(firstNonEmpty([payload.resident_name, row.resident_name]), 'RESIDENT');

    const fullAddress = upper(issuedResidenceAddress(firstNonEmpty([
      payload.full_address,
      payload.full_address_display,
      payload.address,
      residentProfile.full_address
    ])));
    const birthdate = upper(formatDisplayDate(firstNonEmpty([
      payload.birthdate,
      payload.date_of_birth,
      residentProfile.birthdate
    ])));
    const birthplace = upper(firstNonEmpty([
      payload.birthplace,
      payload.place_of_birth,
      residentProfile.birthplace
    ]));
    const sex = upper(firstNonEmpty([
      payload.card_sex,
      residentProfile.sex,
      payload.sex,
      payload.gender,
      payload.child_sex,
      payload.sex_display,
      payload.gender_display,
      row.sex
    ]));
    const contactNumber = upper(normalizePhone(firstNonEmpty([
      payload.contact_number,
      payload.phone_number,
      residentProfile.contact_number,
      row.contact_number
    ])));
    const emergencyName = formatEmergencyName(payload, residentProfile);
    const emergencyAddress = upper(firstNonEmpty([
      payload.emergency_address,
      residentProfile.emergency_address
    ]));
    const emergencyContact = upper(normalizePhone(firstNonEmpty([
      payload.emergency_contact,
      payload.emergency_phone_number,
      residentProfile.emergency_contact
    ])));
    const photoUrl = resolvePublicUrl(appBase, firstNonEmpty([
      payload.id_picture_url,
      payload.id_picture_path,
      residentProfile.id_picture_url,
      residentProfile.id_picture_path,
      profileImageUrl,
      fallbackProfileImageUrl
    ]));
    const qrConfig = qrPreviewUrl(appBase, row, payload);
    const qrUrl = String(qrConfig?.primary || '').trim();
    const qrFallbackUrl = String(qrConfig?.fallback || '').trim();
    const resolvedFrontTemplateUrl = frontTemplateUrl || `${appBase}/Resident-End/Certificates/BarangayID/FRONT_EMPTY.png?v=${TEMPLATE_ASSET_VERSION}`;
    const resolvedBackTemplateUrl = backTemplateUrl || `${appBase}/Resident-End/Certificates/BarangayID/BACK_EMPTY.png?v=${TEMPLATE_ASSET_VERSION}`;
    const resolvedTemplateVariant = firstNonEmpty([
      templateVariant,
      (
        /_EMPTY\.[a-z0-9]+(?:[?#].*)?$/i.test(resolvedFrontTemplateUrl)
        && /_EMPTY\.[a-z0-9]+(?:[?#].*)?$/i.test(resolvedBackTemplateUrl)
      ) ? 'empty' : '',
      'empty'
    ]);

    return {
      appBase,
      requestId,
      cardFullName: fullName,
      cardFullAddress: fullAddress,
      cardBirthdate: birthdate,
      cardBirthplace: birthplace,
      cardSex: sex,
      cardContactNumber: contactNumber,
      cardEmergencyName: emergencyName,
      cardEmergencyAddress: emergencyAddress,
      cardEmergencyContact: emergencyContact,
      cardNumber,
      validUntil,
      validityNotice: `This ID is valid until ${validUntil || '____'} except when the holder requests for a new one.`,
      photoUrl,
      qrUrl,
      qrFallbackUrl,
      templateVariant: resolvedTemplateVariant,
      frontTemplateUrl: resolvedFrontTemplateUrl,
      frontTemplateFallbackUrl: resolvedFrontTemplateUrl,
      backTemplateUrl: resolvedBackTemplateUrl,
      backTemplateFallbackUrl: resolvedBackTemplateUrl,
    };
  }

  function render(state, options = {}) {
    ensureStyles();
    const usesEmptyTemplate = String(state.templateVariant || '').toLowerCase() === 'empty';
    const eyebrow = String(options.eyebrow || 'Digital Barangay ID').trim();
    const helper = String(options.helper || '').trim();
    const showIntro = options.showIntro !== false;
    const frontLabel = String(options.frontLabel || 'Front').trim();
    const backLabel = String(options.backLabel || 'Back').trim();

    const introHtml = showIntro ? `
      <div class="barangay-id-digital__intro">
        <span class="barangay-id-digital__eyebrow">${esc(eyebrow)}</span>
        ${helper ? `<p class="barangay-id-digital__copy">${esc(helper)}</p>` : ''}
      </div>
    ` : '';

    const photoHtml = state.photoUrl
      ? `<div class="barangay-id-card__photo" style="left:${positionX(7.9)};top:${positionY(22.1)};width:${widthPct(22)};height:${heightPct(22)};"><img src="${esc(state.photoUrl)}" alt="Resident photo" onerror="this.parentElement.remove();"></div>`
      : '';

    const qrHtml = state.qrUrl
      ? `<div class="barangay-id-card__qr" style="left:${positionX(49)};top:${positionY(11.5)};width:${widthPct(32.3)};height:${heightPct(31.4)};"><img src="${esc(state.qrUrl)}" alt="Verification QR code"${state.qrFallbackUrl ? ` data-fallback="${esc(state.qrFallbackUrl)}"` : ''} onerror="const fallback=this.getAttribute('data-fallback');if(fallback&&this.dataset.fallbackTried!=='1'){this.dataset.fallbackTried='1';this.src=fallback;return;}this.parentElement.remove();"></div>`
      : `<div class="barangay-id-card__qr barangay-id-card__qr--placeholder" style="left:${positionX(49)};top:${positionY(11.5)};width:${widthPct(32.3)};height:${heightPct(31.4)};">QR HERE</div>`;

    const frontCovers = usesEmptyTemplate
      ? ''
      : [
          [31.0, 22.8, 49.2, 27.2],
          [4.8, 43.7, 31.8, 10.4]
        ].map(([x, y, w, h]) => (
          `<div class="barangay-id-card__cover" style="left:${positionX(x)};top:${positionY(y)};width:${widthPct(w)};height:${heightPct(h)};"></div>`
        )).join('');

    const backCovers = usesEmptyTemplate
      ? [
        ].map(([x, y, w, h]) => (
          `<div class="barangay-id-card__cover" style="left:${positionX(x)};top:${positionY(y)};width:${widthPct(w)};height:${heightPct(h)};"></div>`
        )).join('')
      : [
          [57.8, 1.0, 25.0, 6.0],
          [5.8, 14.8, 45.5, 30.6]
        ].map(([x, y, w, h]) => (
          `<div class="barangay-id-card__cover" style="left:${positionX(x)};top:${positionY(y)};width:${widthPct(w)};height:${heightPct(h)};"></div>`
        )).join('');

    const frontFieldsHtml = usesEmptyTemplate ? `
      ${photoHtml}
      <div class="barangay-id-card__field barangay-id-card__field--name" style="left:${positionX(32.5)};top:${positionY(25)};width:${widthPct(45)};">${esc(state.cardFullName || '-')}</div>
      <div class="barangay-id-card__field barangay-id-card__field--address barangay-id-card__field--address-wrap" style="left:${positionX(32.5)};top:${positionY(31)};width:${widthPct(47)};">${esc(state.cardFullAddress || '-')}</div>
      <div class="barangay-id-card__field barangay-id-card__field--meta" style="left:${positionX(32.5)};top:${positionY(39)};width:${widthPct(20.5)};">${esc(state.cardBirthdate || '-')}</div>
      <div class="barangay-id-card__field barangay-id-card__field--meta" style="left:${positionX(57.5)};top:${positionY(39)};width:${widthPct(19.5)};">${esc(state.cardSex || '-')}</div>
      <div class="barangay-id-card__field barangay-id-card__field--birthplace" style="left:${positionX(32.5)};top:${positionY(45)};width:${widthPct(44.8)};">${esc(state.cardBirthplace || '-')}</div>
      <div class="barangay-id-card__field barangay-id-card__field--meta" style="left:${positionX(16.8)};top:${positionY(45)};width:${widthPct(17.4)};">${esc(state.validUntil || '-')}</div>
      <div class="barangay-id-card__field barangay-id-card__field--cardno barangay-id-card__field--cardno-front" style="left:${positionX(11)};top:${positionY(47.7)};width:${widthPct(28.4)};">${esc(state.cardNumber || '-')}</div>
    ` : `
      ${photoHtml}
      <div class="barangay-id-card__label" style="left:${positionX(32.2)};top:${positionY(24.08)};width:${widthPct(10)};">Name</div>
      <div class="barangay-id-card__field barangay-id-card__field--name" style="left:${positionX(32.2)};top:${positionY(25.8)};width:${widthPct(44.8)};">${esc(state.cardFullName || '-')}</div>
      <div class="barangay-id-card__label" style="left:${positionX(32.2)};top:${positionY(30.58)};width:${widthPct(13.5)};">Address</div>
      <div class="barangay-id-card__field barangay-id-card__field--address barangay-id-card__field--address-wrap" style="left:${positionX(31.2)};top:${positionY(32.78)};width:${widthPct(44.8)};">${esc(state.cardFullAddress || '-')}</div>
      <div class="barangay-id-card__label" style="left:${positionX(32.2)};top:${positionY(38.48)};width:${widthPct(20)};">Date of Birth</div>
      <div class="barangay-id-card__field barangay-id-card__field--meta" style="left:${positionX(32.2)};top:${positionY(40.78)};width:${widthPct(20.5)};">${esc(state.cardBirthdate || '-')}</div>
      <div class="barangay-id-card__label" style="left:${positionX(57.2)};top:${positionY(38.48)};width:${widthPct(10)};">Sex</div>
      <div class="barangay-id-card__field barangay-id-card__field--meta" style="left:${positionX(57.2)};top:${positionY(40.78)};width:${widthPct(19.5)};">${esc(state.cardSex || '-')}</div>
      <div class="barangay-id-card__label" style="left:${positionX(32.2)};top:${positionY(44.78)};width:${widthPct(20)};">Place of Birth</div>
      <div class="barangay-id-card__field barangay-id-card__field--birthplace" style="left:${positionX(32.2)};top:${positionY(46.98)};width:${widthPct(44.8)};">${esc(state.cardBirthplace || '-')}</div>
      <div class="barangay-id-card__field barangay-id-card__field--meta" style="left:${positionX(6.0)};top:${positionY(44.78)};width:${widthPct(28.6)};">VALID UNTIL: ${esc(state.validUntil || '-')}</div>
      <div class="barangay-id-card__field barangay-id-card__field--cardno" style="left:${positionX(6.4)};top:${positionY(49.58)};width:${widthPct(28.4)};">${esc(state.cardNumber || '-')}</div>
    `;

    const backFieldsHtml = usesEmptyTemplate ? `
      <div class="barangay-id-card__field barangay-id-card__field--cardno barangay-id-card__field--cardno-back" style="left:${positionX(63)};top:${positionY(3.6)};width:${widthPct(19.8)};text-align:right;">${esc(state.cardNumber || '-')}</div>
      <div class="barangay-id-card__field barangay-id-card__field--name barangay-id-card__field--emergency" style="left:${positionX(7)};top:${positionY(17)};width:${widthPct(35)};text-align:left;">${esc(state.cardEmergencyName || '-')}</div>
      <div class="barangay-id-card__field barangay-id-card__field--address barangay-id-card__field--address-wrap barangay-id-card__field--emergency" style="left:${positionX(7)};top:${positionY(22.08)};width:${widthPct(35)};">${esc(state.cardEmergencyAddress || '-')}</div>
      <div class="barangay-id-card__field barangay-id-card__field--meta barangay-id-card__field--emergency" style="left:${positionX(7)};top:${positionY(28.5)};width:${widthPct(19.0)};">${esc(state.cardEmergencyContact || state.cardContactNumber || '-')}</div>
      ${qrHtml}
    ` : `
      <div class="barangay-id-card__field barangay-id-card__field--cardno barangay-id-card__field--cardno-back" style="left:${positionX(59.5)};top:${positionY(3.3)};width:${widthPct(21.5)};text-align:right;">${esc(state.cardNumber || '-')}</div>
      <div class="barangay-id-card__label" style="left:${positionX(6.9)};top:${positionY(17.5)};width:${widthPct(10)};">Name</div>
      <div class="barangay-id-card__field barangay-id-card__field--name barangay-id-card__field--emergency" style="left:${positionX(6.9)};top:${positionY(19.7)};width:${widthPct(33)};text-align:left;">${esc(state.cardEmergencyName || '-')}</div>
      <div class="barangay-id-card__label" style="left:${positionX(6.9)};top:${positionY(23.8)};width:${widthPct(12)};">Address</div>
      <div class="barangay-id-card__field barangay-id-card__field--address barangay-id-card__field--emergency" style="left:${positionX(6.9)};top:${positionY(26.0)};width:${widthPct(39.6)};">${esc(state.cardEmergencyAddress || '-')}</div>
      <div class="barangay-id-card__label" style="left:${positionX(6.9)};top:${positionY(30.0)};width:${widthPct(12)};">Contact</div>
      <div class="barangay-id-card__field barangay-id-card__field--meta barangay-id-card__field--emergency" style="left:${positionX(6.9)};top:${positionY(32.2)};width:${widthPct(22)};">${esc(state.cardEmergencyContact || state.cardContactNumber || '-')}</div>
      <div class="barangay-id-card__note" style="left:${positionX(7.3)};top:${positionY(36.6)};width:${widthPct(40.5)};">${esc(state.validityNotice || '')}</div>
      ${qrHtml}
    `;

    return `
      <div class="barangay-id-digital">
        ${introHtml}
        <div class="barangay-id-digital__grid">
          <section class="barangay-id-digital__panel">
            <span class="barangay-id-digital__label">${esc(frontLabel)}</span>
            <div class="barangay-id-card">
              <img class="barangay-id-card__bg" src="${esc(state.frontTemplateUrl)}" alt="Barangay ID front template" onerror="this.onerror=null;this.src='${esc(state.frontTemplateFallbackUrl)}';">
              ${frontCovers}
              ${frontFieldsHtml}
            </div>
          </section>
          <section class="barangay-id-digital__panel">
            <span class="barangay-id-digital__label">${esc(backLabel)}</span>
            <div class="barangay-id-card">
              <img class="barangay-id-card__bg" src="${esc(state.backTemplateUrl)}" alt="Barangay ID back template" onerror="this.onerror=null;this.src='${esc(state.backTemplateFallbackUrl)}';">
              ${backCovers}
              ${backFieldsHtml}
            </div>
          </section>
        </div>
      </div>
    `;
  }

  window.BarangayIdDigital = {
    createState,
    render,
  };
})();
