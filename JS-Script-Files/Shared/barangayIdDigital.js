(() => {
  const PAGE_WIDTH_MM = 85.6;
  const PAGE_HEIGHT_MM = 54.1;

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
      .replace(/\bArea\s+\d+\b/gi, '')
      .replace(/\bArea\b/gi, '')
      .replace(/\s+,/g, ',')
      .replace(/,\s*,/g, ', ')
      .replace(/\s{2,}/g, ' ')
      .replace(/^,\s*|\s*,\s*$/g, '')
      .trim();
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

  function formatMonthYear(value) {
    const raw = String(value || '').trim();
    if (!raw) return '';
    const normalized = raw.includes('T') ? raw : raw.replace(' ', 'T');
    const parsed = new Date(normalized);
    if (Number.isNaN(parsed.getTime())) return raw.toUpperCase();
    return parsed.toLocaleDateString('en-US', {
      month: 'long',
      year: 'numeric'
    }).toUpperCase();
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
    const override = upper(overrideValue);
    if (override) return override;
    const baseDate = new Date(issuedDate || Date.now());
    if (Number.isNaN(baseDate.getTime())) {
      return '';
    }
    baseDate.setFullYear(baseDate.getFullYear() + 2);
    return formatMonthYear(baseDate.toISOString());
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
      }
      .barangay-id-card__bg {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
      }
      .barangay-id-card__field,
      .barangay-id-card__photo,
      .barangay-id-card__qr,
      .barangay-id-card__note {
        position: absolute;
        z-index: 2;
      }
      .barangay-id-card__field {
        color: #111;
        font-family: Arial, Helvetica, sans-serif;
        font-weight: 700;
        line-height: 1.08;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        text-transform: uppercase;
      }
      .barangay-id-card__field--name {
        font-size: clamp(11px, 1.28vw, 16px);
        letter-spacing: 0.01em;
      }
      .barangay-id-card__field--address,
      .barangay-id-card__field--birthplace {
        font-size: clamp(9px, 1.02vw, 12px);
      }
      .barangay-id-card__field--meta {
        font-size: clamp(10px, 1.04vw, 12px);
      }
      .barangay-id-card__field--cardno {
        font-size: clamp(10px, 1.06vw, 13px);
        letter-spacing: 0.04em;
      }
      .barangay-id-card__photo {
        overflow: hidden;
        border-radius: 6px;
        background: linear-gradient(180deg, #f6f6f6 0%, #e7e7e7 100%);
      }
      .barangay-id-card__photo img,
      .barangay-id-card__qr img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
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
        border-radius: 6px;
        overflow: hidden;
        background: rgba(255,255,255,0.98);
        border: 1px solid rgba(0,0,0,0.12);
      }
      .barangay-id-card__qr--placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6b7280;
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0.06em;
      }
      .barangay-id-card__note {
        color: #111;
        font-family: Arial, Helvetica, sans-serif;
        font-weight: 700;
        line-height: 1.16;
        font-size: clamp(7.2px, 0.82vw, 9.4px);
        text-transform: uppercase;
      }
      .barangay-id-digital__actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
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
  } = {}) {
    const requestId = firstNonEmpty([row.request_id]);
    const issuedDate = computeIssuedDate(row);
    const cardNumber = computeCardNumber(
      firstNonEmpty([payload.barangay_id_number, payload.resident_id_number, payload.resident_id_no]),
      requestId,
      issuedDate
    );
    const validUntil = computeValidUntil(
      firstNonEmpty([payload.barangay_id_valid_until, payload.valid_until]),
      issuedDate
    );
    const fullName = formatCardName(
      firstNonEmpty([payload.last_name, payload.lastname, residentProfile.last_name]),
      firstNonEmpty([payload.first_name, payload.firstname, residentProfile.first_name]),
      firstNonEmpty([payload.middle_name, payload.middlename, residentProfile.middle_name]),
      firstNonEmpty([payload.suffix_name, payload.suffix, residentProfile.suffix])
    ) || upper(firstNonEmpty([payload.resident_name, row.resident_name]), 'RESIDENT');

    const fullAddress = upper(stripAreaFromAddress(firstNonEmpty([
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
    const sex = upper(firstNonEmpty([payload.sex, payload.gender, residentProfile.sex]));
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
      payload.id_picture_path,
      residentProfile.id_picture_path,
      profileImageUrl,
      fallbackProfileImageUrl
    ]));
    const qrUrl = resolvePublicUrl(appBase, firstNonEmpty([
      row.qr_code_path,
      payload.qr_code_path
    ]));

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
      validityNotice: `THIS ID IS VALID UNTIL ${validUntil || '____'} EXCEPT WHEN THE HOLDER REQUESTS FOR A NEW ONE.`,
      photoUrl,
      qrUrl,
      frontTemplateUrl: frontTemplateUrl || `${appBase}/Resident-End/Certificates/BarangayID/FRONT.png`,
      frontTemplateFallbackUrl: `${appBase}/Images/Barangayid/SAMPLE.png`,
      backTemplateUrl: backTemplateUrl || `${appBase}/Resident-End/Certificates/BarangayID/BACK.png`,
      backTemplateFallbackUrl: `${appBase}/Images/Barangayid/BACK.png`,
    };
  }

  function render(state, options = {}) {
    ensureStyles();
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
      ? `<img src="${esc(state.photoUrl)}" alt="Resident photo" onerror="this.parentElement.innerHTML='<div class=&quot;barangay-id-card__photo--placeholder&quot;>No photo available</div>';">`
      : '<div class="barangay-id-card__photo--placeholder">No photo available</div>';

    const qrHtml = state.qrUrl
      ? `<img src="${esc(state.qrUrl)}" alt="Verification QR code" onerror="this.parentElement.classList.add('barangay-id-card__qr--placeholder');this.parentElement.textContent='NO QR';">`
      : 'NO QR';

    return `
      <div class="barangay-id-digital">
        ${introHtml}
        <div class="barangay-id-digital__grid">
          <section class="barangay-id-digital__panel">
            <span class="barangay-id-digital__label">${esc(frontLabel)}</span>
            <div class="barangay-id-card">
              <img class="barangay-id-card__bg" src="${esc(state.frontTemplateUrl)}" alt="Barangay ID front template" onerror="this.onerror=null;this.src='${esc(state.frontTemplateFallbackUrl)}';">
              <div class="barangay-id-card__photo" style="left:${positionX(6.8)};top:${positionY(16.3)};width:${widthPct(18.8)};height:${heightPct(22.6)};">
                ${photoHtml}
              </div>
              <div class="barangay-id-card__field barangay-id-card__field--name" style="left:${positionX(32.2)};top:${positionY(25.6)};width:${widthPct(43.8)};">${esc(state.cardFullName || '-')}</div>
              <div class="barangay-id-card__field barangay-id-card__field--address" style="left:${positionX(32.2)};top:${positionY(31.5)};width:${widthPct(43.8)};">${esc(state.cardFullAddress || '-')}</div>
              <div class="barangay-id-card__field barangay-id-card__field--meta" style="left:${positionX(32.2)};top:${positionY(39.4)};width:${widthPct(19.2)};">${esc(state.cardBirthdate || '-')}</div>
              <div class="barangay-id-card__field barangay-id-card__field--meta" style="left:${positionX(57.2)};top:${positionY(39.4)};width:${widthPct(18)};">${esc(state.cardSex || '-')}</div>
              <div class="barangay-id-card__field barangay-id-card__field--birthplace" style="left:${positionX(32.2)};top:${positionY(45.2)};width:${widthPct(43.8)};">${esc(state.cardBirthplace || '-')}</div>
              <div class="barangay-id-card__field barangay-id-card__field--meta" style="left:${positionX(6.0)};top:${positionY(44.8)};width:${widthPct(26.6)};">VALID UNTIL: ${esc(state.validUntil || '-')}</div>
              <div class="barangay-id-card__field barangay-id-card__field--cardno" style="left:${positionX(6.4)};top:${positionY(49.4)};width:${widthPct(27.2)};">${esc(state.cardNumber || '-')}</div>
            </div>
          </section>
          <section class="barangay-id-digital__panel">
            <span class="barangay-id-digital__label">${esc(backLabel)}</span>
            <div class="barangay-id-card">
              <img class="barangay-id-card__bg" src="${esc(state.backTemplateUrl)}" alt="Barangay ID back template" onerror="this.onerror=null;this.src='${esc(state.backTemplateFallbackUrl)}';">
              <div class="barangay-id-card__field barangay-id-card__field--cardno" style="left:${positionX(59.5)};top:${positionY(3.1)};width:${widthPct(21)};">${esc(state.cardNumber || '-')}</div>
              <div class="barangay-id-card__field barangay-id-card__field--name" style="left:${positionX(6.9)};top:${positionY(17.3)};width:${widthPct(31.8)};">${esc(state.cardEmergencyName || '-')}</div>
              <div class="barangay-id-card__field barangay-id-card__field--address" style="left:${positionX(6.9)};top:${positionY(22.3)};width:${widthPct(38.6)};">${esc(state.cardEmergencyAddress || '-')}</div>
              <div class="barangay-id-card__field barangay-id-card__field--meta" style="left:${positionX(6.9)};top:${positionY(27.4)};width:${widthPct(20)};">${esc(state.cardEmergencyContact || state.cardContactNumber || '-')}</div>
              <div class="barangay-id-card__note" style="left:${positionX(6.6)};top:${positionY(32.1)};width:${widthPct(41)};">${esc(state.validityNotice || '')}</div>
              <div class="barangay-id-card__qr${state.qrUrl ? '' : ' barangay-id-card__qr--placeholder'}" style="left:${positionX(60.8)};top:${positionY(27.2)};width:${widthPct(18.5)};height:${heightPct(18.5)};">
                ${qrHtml}
              </div>
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
