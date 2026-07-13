(() => {
  const PAGE_WIDTH_MM = 85.6;
  const PAGE_HEIGHT_MM = 54.1;
  const TEMPLATE_ASSET_VERSION = '20260713-01';
  const DEFAULT_IMAGE_PLACEHOLDER = 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="320" viewBox="0 0 320 320"><rect width="320" height="320" rx="32" fill="#f8efe4"/><circle cx="160" cy="118" r="54" fill="#e2c8aa"/><path d="M76 262c18-45 56-70 84-70s66 25 84 70" fill="#e2c8aa"/><text x="160" y="300" font-family="Arial" font-size="24" font-weight="700" text-anchor="middle" fill="#8a5c2b">PHOTO</text></svg>'
  );

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
    if (normalized.startsWith(`${appBase}/`)) {
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

  function defaultSampleData() {
    return {
      cardFullName: 'DELA CRUZ, JUAN S.',
      cardFullAddress: 'AREA 1, BARANGAY SAN JOSE, RODRIGUEZ, RIZAL',
      cardBirthdate: '04/16/1998',
      cardBirthplace: 'RODRIGUEZ, RIZAL',
      cardSex: 'MALE',
      cardContactNumber: '09171234567',
      cardEmergencyName: 'DELA CRUZ, MARIA L.',
      cardEmergencyAddress: 'AREA 1, BARANGAY SAN JOSE, RODRIGUEZ, RIZAL',
      cardEmergencyContact: '09179876543',
      cardNumber: 'A2026-0001',
      validUntil: '07/13/2028',
      validityNotice: 'This ID is valid until 07/13/2028 except when the holder requests for a new one.'
    };
  }

  function defaultLayoutConfig() {
    return {
      version: 1,
      page: {
        width_mm: PAGE_WIDTH_MM,
        height_mm: PAGE_HEIGHT_MM
      },
      fields: [
        { id: 'front_photo', label: 'Resident Photo', type: 'image', source: 'photoUrl', side: 'front', x: 7.9, y: 22.1, w: 22, h: 22, fit: 'cover', z: 2 },
        { id: 'front_name_label', label: 'Label: Name', type: 'label', text: 'Name', side: 'front', x: 32.2, y: 24.08, w: 10, h: 3.2, align: 'left', fontStyle: 'I', fontSize: 5.1, minFontSize: 4.0, color: '#111111', z: 2 },
        { id: 'front_name_value', label: 'Full Name', type: 'text', source: 'cardFullName', side: 'front', x: 32.2, y: 25.8, w: 44.8, h: 4.8, align: 'left', fontStyle: 'B', fontSize: 7.2, minFontSize: 4.6, uppercase: true, z: 2 },
        { id: 'front_address_label', label: 'Label: Address', type: 'label', text: 'Address', side: 'front', x: 32.2, y: 30.58, w: 13.5, h: 3.2, align: 'left', fontStyle: 'I', fontSize: 5.1, minFontSize: 4.0, color: '#111111', z: 2 },
        { id: 'front_address_value', label: 'Address', type: 'text', source: 'cardFullAddress', side: 'front', x: 31.2, y: 32.78, w: 44.8, h: 6, align: 'left', fontStyle: 'B', fontSize: 5.6, minFontSize: 3.2, uppercase: true, multiline: true, maxLines: 2, z: 2 },
        { id: 'front_birthdate_label', label: 'Label: Date of Birth', type: 'label', text: 'Date of Birth', side: 'front', x: 32.2, y: 38.48, w: 20, h: 3.2, align: 'left', fontStyle: 'I', fontSize: 5.1, minFontSize: 4.0, color: '#111111', z: 2 },
        { id: 'front_birthdate_value', label: 'Birthdate', type: 'text', source: 'cardBirthdate', side: 'front', x: 32.2, y: 40.78, w: 20.5, h: 4.4, align: 'left', fontStyle: 'B', fontSize: 6.4, minFontSize: 4.4, uppercase: true, z: 2 },
        { id: 'front_sex_label', label: 'Label: Sex', type: 'label', text: 'Sex', side: 'front', x: 57.2, y: 38.48, w: 10, h: 3.2, align: 'left', fontStyle: 'I', fontSize: 5.1, minFontSize: 4.0, color: '#111111', z: 2 },
        { id: 'front_sex_value', label: 'Sex', type: 'text', source: 'cardSex', side: 'front', x: 57.2, y: 40.78, w: 19.5, h: 4.4, align: 'left', fontStyle: 'B', fontSize: 6.4, minFontSize: 4.4, uppercase: true, z: 2 },
        { id: 'front_birthplace_label', label: 'Label: Place of Birth', type: 'label', text: 'Place of Birth', side: 'front', x: 32.2, y: 44.78, w: 20, h: 3.2, align: 'left', fontStyle: 'I', fontSize: 5.1, minFontSize: 4.0, color: '#111111', z: 2 },
        { id: 'front_birthplace_value', label: 'Birthplace', type: 'text', source: 'cardBirthplace', side: 'front', x: 32.2, y: 46.98, w: 44.8, h: 4.2, align: 'left', fontStyle: 'B', fontSize: 5.5, minFontSize: 4.0, uppercase: true, z: 2 },
        { id: 'front_valid_until_value', label: 'Valid Until', type: 'text', source: 'validUntil', prefix: 'VALID UNTIL: ', side: 'front', x: 6.0, y: 44.78, w: 28.6, h: 4.2, align: 'left', fontStyle: 'B', fontSize: 4.8, minFontSize: 3.7, uppercase: true, z: 2 },
        { id: 'front_card_number_value', label: 'Card Number', type: 'text', source: 'cardNumber', side: 'front', x: 6.4, y: 49.58, w: 28.4, h: 4.4, align: 'left', fontStyle: 'B', fontSize: 6.8, minFontSize: 4.4, uppercase: true, color: '#c62828', z: 2 },
        { id: 'back_card_number_value', label: 'Card Number (Back)', type: 'text', source: 'cardNumber', side: 'back', x: 59.5, y: 3.3, w: 21.5, h: 4.6, align: 'right', fontStyle: 'B', fontSize: 7.6, minFontSize: 5.0, uppercase: true, color: '#c62828', z: 2 },
        { id: 'back_emergency_name_label', label: 'Label: Emergency Name', type: 'label', text: 'Name', side: 'back', x: 6.9, y: 17.5, w: 10, h: 3, align: 'left', fontStyle: 'I', fontSize: 5.0, minFontSize: 4.0, color: '#111111', z: 2 },
        { id: 'back_emergency_name_value', label: 'Emergency Contact Name', type: 'text', source: 'cardEmergencyName', side: 'back', x: 6.9, y: 19.7, w: 33, h: 4.6, align: 'left', fontStyle: 'B', fontSize: 6.0, minFontSize: 4.3, uppercase: true, z: 2 },
        { id: 'back_emergency_address_label', label: 'Label: Emergency Address', type: 'label', text: 'Address', side: 'back', x: 6.9, y: 23.8, w: 12, h: 3, align: 'left', fontStyle: 'I', fontSize: 5.0, minFontSize: 4.0, color: '#111111', z: 2 },
        { id: 'back_emergency_address_value', label: 'Emergency Address', type: 'text', source: 'cardEmergencyAddress', side: 'back', x: 6.9, y: 26.0, w: 39.6, h: 6, align: 'left', fontStyle: 'B', fontSize: 5.0, minFontSize: 3.2, uppercase: true, multiline: true, maxLines: 2, z: 2 },
        { id: 'back_emergency_contact_label', label: 'Label: Emergency Contact', type: 'label', text: 'Contact', side: 'back', x: 6.9, y: 30.0, w: 12, h: 3, align: 'left', fontStyle: 'I', fontSize: 5.0, minFontSize: 4.0, color: '#111111', z: 2 },
        { id: 'back_emergency_contact_value', label: 'Emergency Contact Number', type: 'text', source: 'cardEmergencyContact', fallbackSource: 'cardContactNumber', side: 'back', x: 6.9, y: 32.2, w: 22, h: 4.4, align: 'left', fontStyle: 'B', fontSize: 6.0, minFontSize: 4.3, uppercase: true, z: 2 },
        { id: 'back_validity_notice', label: 'Validity Notice', type: 'text', source: 'validityNotice', side: 'back', x: 7.3, y: 36.6, w: 40.5, h: 6.4, align: 'center', fontStyle: 'I', fontSize: 4.2, minFontSize: 3.2, uppercase: false, multiline: true, maxLines: 3, z: 2 },
        { id: 'back_signatory', label: 'Punong Barangay Signatory', type: 'signatory', side: 'back', x: 9.1, y: 38.2, w: 30.8, h: 12, z: 3 },
        { id: 'back_qr', label: 'Verification QR', type: 'qr', source: 'qrUrl', side: 'back', x: 49.0, y: 11.5, w: 32.3, h: 31.4, fit: 'fill', z: 2 }
      ]
    };
  }

  function fieldLibrary() {
    return [
      { type: 'text', label: 'Text Field', source: 'cardFullName', side: 'front', w: 28, h: 4.8, fontStyle: 'B', fontSize: 6, minFontSize: 4.2, uppercase: true, align: 'left', multiline: false, maxLines: 1, color: '#111111' },
      { type: 'label', label: 'Label Field', text: 'Label', side: 'front', w: 16, h: 3, fontStyle: 'I', fontSize: 5, minFontSize: 4, uppercase: false, align: 'left', multiline: false, maxLines: 1, color: '#111111' },
      { type: 'image', label: 'Image Field', source: 'photoUrl', side: 'front', w: 18, h: 18, fit: 'cover' },
      { type: 'qr', label: 'QR Field', source: 'qrUrl', side: 'back', w: 18, h: 18, fit: 'fill' },
      { type: 'signatory', label: 'Signatory Block', side: 'back', w: 30, h: 12 },
      { type: 'cover', label: 'Cover Block', side: 'front', w: 18, h: 8, backgroundColor: '#ffffff' }
    ];
  }

  function normalizeNumber(value, fallback, min, max) {
    const parsed = Number.parseFloat(value);
    const safe = Number.isFinite(parsed) ? parsed : fallback;
    return Math.min(max, Math.max(min, Number(safe.toFixed(2))));
  }

  function normalizeInteger(value, fallback, min, max) {
    const parsed = Number.parseInt(value, 10);
    const safe = Number.isFinite(parsed) ? parsed : fallback;
    return Math.min(max, Math.max(min, safe));
  }

  function normalizeBoolean(value, fallback = false) {
    if (typeof value === 'boolean') return value;
    if (typeof value === 'number') return value !== 0;
    const normalized = String(value ?? '').trim().toLowerCase();
    if (!normalized) return fallback;
    return ['1', 'true', 'yes', 'on'].includes(normalized);
  }

  function normalizeLayoutField(field = {}, index = 0) {
    const rawType = String(field.type || 'text').trim().toLowerCase();
    const type = ['text', 'label', 'image', 'qr', 'signatory', 'cover'].includes(rawType) ? rawType : 'text';
    const rawAlign = String(field.align || 'left').trim().toLowerCase();
    const align = ['left', 'center', 'right'].includes(rawAlign) ? rawAlign : 'left';
    let fontStyle = String(field.fontStyle || (type === 'label' ? 'I' : 'B')).trim().toUpperCase();
    if (!['', 'B', 'I', 'BI', 'IB'].includes(fontStyle)) {
      fontStyle = type === 'label' ? 'I' : 'B';
    }
    if (fontStyle === 'IB') fontStyle = 'BI';
    const color = /^#[0-9A-Fa-f]{6}$/.test(String(field.color || '').trim()) ? String(field.color).trim() : '#111111';
    const backgroundColor = /^#[0-9A-Fa-f]{6}$/.test(String(field.backgroundColor || '').trim()) ? String(field.backgroundColor).trim() : '#ffffff';
    const rawFit = String(field.fit || 'cover').trim().toLowerCase();
    const fit = ['cover', 'contain', 'fill'].includes(rawFit) ? rawFit : 'cover';
    const side = String(field.side || 'front').trim().toLowerCase() === 'back' ? 'back' : 'front';
    const rawId = String(field.id || '').trim().toLowerCase().replace(/[^a-z0-9_-]+/g, '_');

    const normalized = {
      id: rawId || `field_${index + 1}`,
      label: String(field.label || 'Field').trim() || `Field ${index + 1}`,
      type,
      side,
      x: normalizeNumber(field.x, 0, 0, PAGE_WIDTH_MM),
      y: normalizeNumber(field.y, 0, 0, PAGE_HEIGHT_MM),
      w: normalizeNumber(field.w, 10, 1.2, PAGE_WIDTH_MM),
      h: normalizeNumber(field.h, 4, 1.0, PAGE_HEIGHT_MM),
      z: normalizeInteger(field.z, 2, 1, 20),
      source: String(field.source || '').trim(),
      fallbackSource: String(field.fallbackSource || '').trim(),
      prefix: String(field.prefix || '').trim(),
      text: String(field.text || '').trim(),
      align,
      fontStyle,
      fontSize: normalizeNumber(field.fontSize, type === 'label' ? 5 : 6, 2.8, 20),
      minFontSize: normalizeNumber(field.minFontSize, type === 'label' ? 4 : 4.2, 2.4, 18),
      color,
      backgroundColor,
      uppercase: normalizeBoolean(field.uppercase, type !== 'label' && type !== 'cover'),
      multiline: normalizeBoolean(field.multiline, false),
      maxLines: normalizeInteger(field.maxLines, 2, 1, 5),
      fit
    };
    if (normalized.minFontSize > normalized.fontSize) {
      normalized.minFontSize = normalized.fontSize;
    }
    return normalized;
  }

  function normalizeLayoutConfig(layoutConfig = null) {
    const defaults = defaultLayoutConfig();
    const config = layoutConfig && typeof layoutConfig === 'object' ? layoutConfig : defaults;
    const fields = Array.isArray(config.fields) && config.fields.length
      ? config.fields.map((field, index) => normalizeLayoutField(field, index))
      : defaults.fields.map((field, index) => normalizeLayoutField(field, index));
    fields.sort((left, right) => {
      const sideCompare = String(left.side || '').localeCompare(String(right.side || ''));
      if (sideCompare !== 0) return sideCompare;
      if (left.z !== right.z) return left.z - right.z;
      return String(left.id || '').localeCompare(String(right.id || ''));
    });
    return {
      version: normalizeInteger(config.version, 1, 1, 10),
      page: {
        width_mm: PAGE_WIDTH_MM,
        height_mm: PAGE_HEIGHT_MM
      },
      fields
    };
  }

  function mmToPct(value, total) {
    return `${(Number(value || 0) / total) * 100}%`;
  }

  function sideFields(layoutConfig, side) {
    const normalizedSide = side === 'back' ? 'back' : 'front';
    return normalizeLayoutConfig(layoutConfig).fields.filter((field) => field.side === normalizedSide);
  }

  function fitToObjectPosition(fit) {
    if (fit === 'contain') return 'contain';
    if (fit === 'fill') return 'fill';
    return 'cover';
  }

  function verificationUrl(appBase, row = {}, payload = {}) {
    const requestId = String(firstNonEmpty([row.request_id, payload.request_id]) || '').trim();
    if (!requestId || typeof window === 'undefined' || !window.location || !window.location.origin) {
      return '';
    }
    const verificationCode = String(firstNonEmpty([row.verification_code, payload.verification_code, requestId]) || '').trim();
    const appOrigin = `${window.location.origin}${appBase}`;
    return `${appOrigin}/transactions?request_id=${encodeURIComponent(requestId)}&vc=${encodeURIComponent(verificationCode || requestId)}`;
  }

  function qrPreviewUrl(appBase, row = {}, payload = {}) {
    const existing = resolvePublicUrl(appBase, firstNonEmpty([row.qr_code_path, payload.qr_code_path]));
    const verifyUrl = verificationUrl(appBase, row, payload);
    const fallback = verifyUrl
      ? `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(verifyUrl)}`
      : '';
    return {
      primary: existing || fallback,
      fallback: existing && fallback && existing !== fallback ? fallback : ''
    };
  }

  function mergeSampleData(baseState, sampleData = {}) {
    const defaults = defaultSampleData();
    const merged = { ...baseState };
    Object.keys(defaults).forEach((key) => {
      if (sampleData && Object.prototype.hasOwnProperty.call(sampleData, key)) {
        merged[key] = String(sampleData[key] ?? '').trim() || defaults[key];
      }
    });
    if (!String(merged.validityNotice || '').trim()) {
      merged.validityNotice = `This ID is valid until ${merged.validUntil || '____'} except when the holder requests for a new one.`;
    }
    return merged;
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
    layoutConfig = null,
    sampleData = null
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
    const birthdate = upper(formatDisplayDate(firstNonEmpty([payload.birthdate, payload.date_of_birth, residentProfile.birthdate])));
    const birthplace = upper(firstNonEmpty([payload.birthplace, payload.place_of_birth, residentProfile.birthplace]));
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
    const emergencyAddress = upper(firstNonEmpty([payload.emergency_address, residentProfile.emergency_address]));
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
      fallbackProfileImageUrl,
      DEFAULT_IMAGE_PLACEHOLDER
    ]));
    const qrConfig = qrPreviewUrl(appBase, row, payload);
    const qrUrl = String(qrConfig?.primary || '').trim();
    const qrFallbackUrl = String(qrConfig?.fallback || '').trim();
    const resolvedFrontTemplateUrl = frontTemplateUrl || `${appBase}/Resident-End/Certificates/BarangayID/FRONT_EMPTY.png?v=${TEMPLATE_ASSET_VERSION}`;
    const resolvedBackTemplateUrl = backTemplateUrl || `${appBase}/Resident-End/Certificates/BarangayID/BACK_EMPTY.png?v=${TEMPLATE_ASSET_VERSION}`;

    const baseState = {
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
      photoUrl: photoUrl || DEFAULT_IMAGE_PLACEHOLDER,
      qrUrl,
      qrFallbackUrl,
      punongSignatoryName: upper(firstNonEmpty([row.punong_signatory_name]), 'HON. GLENN S. EVANGELISTA'),
      punongSignatoryTitle: firstNonEmpty([row.punong_signatory_title], 'Punong Barangay') || 'Punong Barangay',
      punongSignatorySignatureUrl: resolvePublicUrl(appBase, firstNonEmpty([row.punong_signatory_signature_path])),
      templateVariant: firstNonEmpty([templateVariant, 'empty']),
      frontTemplateUrl: resolvedFrontTemplateUrl,
      frontTemplateFallbackUrl: resolvedFrontTemplateUrl,
      backTemplateUrl: resolvedBackTemplateUrl,
      backTemplateFallbackUrl: resolvedBackTemplateUrl,
      layoutConfig: normalizeLayoutConfig(layoutConfig)
    };

    return sampleData && typeof sampleData === 'object'
      ? { ...mergeSampleData(baseState, sampleData), layoutConfig: normalizeLayoutConfig(layoutConfig) }
      : baseState;
  }

  function createSampleState(options = {}) {
    return createState({
      ...options,
      sampleData: options.sampleData && typeof options.sampleData === 'object'
        ? options.sampleData
        : defaultSampleData()
    });
  }

  function getStateValue(state, field) {
    if (!field || typeof field !== 'object') return '';
    if (field.type === 'label') return String(field.text || '').trim();
    if (field.type === 'cover') return '';
    if (field.type === 'signatory') return '';

    const source = String(field.source || '').trim();
    const fallbackSource = String(field.fallbackSource || '').trim();
    const prefix = String(field.prefix || '');
    let value = source ? String(state?.[source] ?? '').trim() : '';
    if (!value && fallbackSource) {
      value = String(state?.[fallbackSource] ?? '').trim();
    }
    if (!value && field.type === 'qr') {
      value = String(state?.qrUrl || '').trim();
    }
    if (field.type === 'image' && !value) {
      value = String(state?.photoUrl || '').trim();
    }
    if (value && field.uppercase) {
      value = value.toUpperCase();
    }
    return `${prefix}${value}`.trim();
  }

  function signatoryHtml(state, field, page) {
    const left = mmToPct(field.x, page.width_mm);
    const top = mmToPct(field.y, page.height_mm);
    const width = mmToPct(field.w, page.width_mm);
    const height = mmToPct(field.h, page.height_mm);
    return `
      <div class="barangay-id-card__signatory" style="left:${left};top:${top};width:${width};height:${height};z-index:${field.z};">
        <div class="barangay-id-card__signatory-ink">
          ${state.punongSignatorySignatureUrl ? `<img src="${esc(state.punongSignatorySignatureUrl)}" alt="${esc(state.punongSignatoryName || 'Punong Barangay')} signature">` : ''}
        </div>
        <div class="barangay-id-card__signatory-line"></div>
        <div class="barangay-id-card__signatory-name">${esc(state.punongSignatoryName || 'HON. GLENN S. EVANGELISTA')}</div>
        <div class="barangay-id-card__signatory-title">${esc(state.punongSignatoryTitle || 'Punong Barangay')}</div>
      </div>
    `;
  }

  function renderField(state, field, page) {
    const left = mmToPct(field.x, page.width_mm);
    const top = mmToPct(field.y, page.height_mm);
    const width = mmToPct(field.w, page.width_mm);
    const height = mmToPct(field.h, page.height_mm);

    if (field.type === 'cover') {
      return `<div class="barangay-id-card__cover" style="left:${left};top:${top};width:${width};height:${height};background:${esc(field.backgroundColor || '#ffffff')};z-index:${field.z};"></div>`;
    }

    if (field.type === 'signatory') {
      return signatoryHtml(state, field, page);
    }

    if (field.type === 'image' || field.type === 'qr') {
      const imageUrl = getStateValue(state, field);
      const placeholderText = field.type === 'qr' ? 'QR HERE' : 'IMAGE';
      const className = field.type === 'qr' ? 'barangay-id-card__qr' : 'barangay-id-card__photo';
      const objectFit = fitToObjectPosition(field.fit);
      if (!imageUrl) {
        return `<div class="${className} ${className}--placeholder" style="left:${left};top:${top};width:${width};height:${height};z-index:${field.z};">${esc(placeholderText)}</div>`;
      }
      const fallbackAttr = field.type === 'qr' && state.qrFallbackUrl
        ? ` data-fallback="${esc(state.qrFallbackUrl)}"`
        : '';
      const onError = field.type === 'qr'
        ? "const fallback=this.getAttribute('data-fallback');if(fallback&&this.dataset.fallbackTried!=='1'){this.dataset.fallbackTried='1';this.src=fallback;return;}this.parentElement.remove();"
        : 'this.src="' + esc(DEFAULT_IMAGE_PLACEHOLDER) + '";this.onerror=null;';
      return `
        <div class="${className}" style="left:${left};top:${top};width:${width};height:${height};z-index:${field.z};">
          <img src="${esc(imageUrl)}" alt="${esc(field.label || placeholderText)}"${fallbackAttr} onerror="${onError}" style="object-fit:${objectFit};">
        </div>
      `;
    }

    const value = getStateValue(state, field) || '-';
    const baseClass = field.type === 'label' ? 'barangay-id-card__label' : 'barangay-id-card__field';
    const multilineClass = field.multiline ? ' barangay-id-card__text--multiline' : '';
    const alignClass = field.align === 'center'
      ? ' barangay-id-card__text--center'
      : field.align === 'right'
        ? ' barangay-id-card__text--right'
        : '';
    const lineHeight = field.multiline ? 1.04 : 1.05;
    return `
      <div
        class="${baseClass}${multilineClass}${alignClass}"
        data-bid-autofit="1"
        data-bid-max-font="${esc(field.fontSize)}"
        data-bid-min-font="${esc(field.minFontSize)}"
        data-bid-max-lines="${esc(field.maxLines || 1)}"
        data-bid-multiline="${field.multiline ? '1' : '0'}"
        style="left:${left};top:${top};width:${width};height:${height};z-index:${field.z};color:${esc(field.color)};font-weight:${field.fontStyle.includes('B') ? '800' : '500'};font-style:${field.fontStyle.includes('I') ? 'italic' : 'normal'};text-align:${esc(field.align)};line-height:${lineHeight};"
      >${esc(value)}</div>
    `;
  }

  function ensureStyles() {
    if (document.getElementById('barangay-id-digital-styles')) return;
    const style = document.createElement('style');
    style.id = 'barangay-id-digital-styles';
    style.textContent = `
      .barangay-id-digital { display:grid; gap:18px; }
      .barangay-id-digital__intro { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
      .barangay-id-digital__eyebrow { display:inline-flex; align-items:center; gap:8px; padding:6px 12px; border-radius:999px; background:#fff2df; color:#8a4b00; border:1px solid #f2cf9e; font-weight:700; font-size:0.85rem; }
      .barangay-id-digital__copy { margin:0; color:#5f4a32; font-size:0.92rem; }
      .barangay-id-digital__grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:18px; }
      .barangay-id-digital__panel { display:grid; gap:10px; }
      .barangay-id-digital__label { font-size:0.78rem; font-weight:800; letter-spacing:0.08em; text-transform:uppercase; color:#7a6146; }
      .barangay-id-card { position:relative; width:100%; aspect-ratio:856 / 541; border-radius:18px; overflow:hidden; box-shadow:0 22px 44px rgba(32, 20, 7, 0.18); background:linear-gradient(135deg, #fbf6ef 0%, #f0e4d4 100%); border:1px solid rgba(122, 97, 70, 0.18); container-type:inline-size; container-name:barangay-id-card; }
      .barangay-id-card__bg { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
      .barangay-id-card__cover,
      .barangay-id-card__field,
      .barangay-id-card__label,
      .barangay-id-card__photo,
      .barangay-id-card__qr,
      .barangay-id-card__signatory { position:absolute; }
      .barangay-id-card__cover { z-index:1; }
      .barangay-id-card__field,
      .barangay-id-card__label,
      .barangay-id-card__photo,
      .barangay-id-card__qr { z-index:2; overflow:hidden; }
      .barangay-id-card__field,
      .barangay-id-card__label { font-family:Arial, Helvetica, sans-serif; color:#111; overflow:hidden; }
      .barangay-id-card__label { white-space:nowrap; text-overflow:ellipsis; }
      .barangay-id-card__field { letter-spacing:0.01em; }
      .barangay-id-card__text--multiline { white-space:normal; overflow-wrap:anywhere; word-break:break-word; }
      .barangay-id-card__text--center { text-align:center; }
      .barangay-id-card__text--right { text-align:right; }
      .barangay-id-card__photo img,
      .barangay-id-card__qr img { width:100%; height:100%; display:block; }
      .barangay-id-card__photo { background:transparent; }
      .barangay-id-card__photo img { object-position:center center; }
      .barangay-id-card__qr { background:transparent; }
      .barangay-id-card__qr img { image-rendering:pixelated; }
      .barangay-id-card__qr--placeholder,
      .barangay-id-card__photo--placeholder { display:flex; align-items:center; justify-content:center; text-align:center; font:700 10px/1 Arial, Helvetica, sans-serif; color:#7a5a35; background:rgba(255,255,255,0.72); border:1.5px dashed rgba(222,113,12,0.72); }
      .barangay-id-card__signatory { z-index:3; display:grid; justify-items:center; color:#111; font-family:Arial, Helvetica, sans-serif; }
      .barangay-id-card__signatory-ink { width:100%; min-height:52%; display:flex; align-items:end; justify-content:center; }
      .barangay-id-card__signatory-ink img { max-width:100%; max-height:100%; display:block; object-fit:contain; }
      .barangay-id-card__signatory-line { width:100%; border-top:1.2px solid #111; margin-top:0.5%; }
      .barangay-id-card__signatory-name { font-size:clamp(8.2px, 0.28rem + 0.56vw, 11.2px); line-height:1.05; font-weight:800; text-align:center; text-transform:uppercase; margin-top:1.2%; }
      .barangay-id-card__signatory-title { font-size:clamp(7.6px, 0.24rem + 0.48vw, 9.8px); line-height:1.02; text-align:center; margin-top:0.4%; }
      @media (max-width: 991.98px) { .barangay-id-digital__grid { grid-template-columns:1fr; } }
    `;
    document.head.appendChild(style);
  }

  function autoFitElement(element) {
    if (!(element instanceof HTMLElement)) return;
    const maxFont = Number.parseFloat(element.dataset.bidMaxFont || '6') || 6;
    const minFont = Number.parseFloat(element.dataset.bidMinFont || '4') || 4;
    const isMultiline = element.dataset.bidMultiline === '1';
    const maxLines = Math.max(1, Number.parseInt(element.dataset.bidMaxLines || '1', 10) || 1);
    element.style.fontSize = `${maxFont}px`;
    if (isMultiline) {
      element.style.display = '-webkit-box';
      element.style.webkitBoxOrient = 'vertical';
      element.style.webkitLineClamp = String(maxLines);
    } else {
      element.style.whiteSpace = 'nowrap';
      element.style.textOverflow = 'ellipsis';
    }

    let size = maxFont;
    while (size > minFont) {
      const fitsWidth = element.scrollWidth <= element.clientWidth + 1;
      const fitsHeight = element.scrollHeight <= element.clientHeight + 1;
      if (fitsWidth && fitsHeight) {
        break;
      }
      size = Number((size - 0.2).toFixed(2));
      element.style.fontSize = `${size}px`;
    }
  }

  function hydrate(root) {
    ensureStyles();
    const scope = root instanceof Element ? root : document;
    scope.querySelectorAll('[data-bid-autofit="1"]').forEach((element) => autoFitElement(element));
  }

  function render(state, options = {}) {
    ensureStyles();
    const layout = normalizeLayoutConfig(options.layoutConfig || state?.layoutConfig || null);
    const eyebrow = String(options.eyebrow || 'Digital Barangay ID').trim();
    const helper = String(options.helper || '').trim();
    const showIntro = options.showIntro !== false;
    const frontLabel = String(options.frontLabel || 'Front').trim();
    const backLabel = String(options.backLabel || 'Back').trim();
    const frontFields = sideFields(layout, 'front').map((field) => renderField(state, field, layout.page)).join('');
    const backFields = sideFields(layout, 'back').map((field) => renderField(state, field, layout.page)).join('');

    const introHtml = showIntro ? `
      <div class="barangay-id-digital__intro">
        <span class="barangay-id-digital__eyebrow">${esc(eyebrow)}</span>
        ${helper ? `<p class="barangay-id-digital__copy">${esc(helper)}</p>` : ''}
      </div>
    ` : '';

    return `
      <div class="barangay-id-digital">
        ${introHtml}
        <div class="barangay-id-digital__grid">
          <section class="barangay-id-digital__panel">
            <span class="barangay-id-digital__label">${esc(frontLabel)}</span>
            <div class="barangay-id-card">
              <img class="barangay-id-card__bg" src="${esc(state.frontTemplateUrl || '')}" alt="Barangay ID front template" onerror="this.onerror=null;this.src='${esc(state.frontTemplateFallbackUrl || '')}';">
              ${frontFields}
            </div>
          </section>
          <section class="barangay-id-digital__panel">
            <span class="barangay-id-digital__label">${esc(backLabel)}</span>
            <div class="barangay-id-card">
              <img class="barangay-id-card__bg" src="${esc(state.backTemplateUrl || '')}" alt="Barangay ID back template" onerror="this.onerror=null;this.src='${esc(state.backTemplateFallbackUrl || '')}';">
              ${backFields}
            </div>
          </section>
        </div>
      </div>
    `;
  }

  function renderInto(container, state, options = {}) {
    if (!(container instanceof Element)) return;
    container.innerHTML = render(state, options);
    hydrate(container);
  }

  window.BarangayIdDigital = {
    createState,
    createSampleState,
    defaultLayoutConfig,
    defaultSampleData,
    fieldLibrary,
    normalizeLayoutConfig,
    render,
    renderInto,
    hydrate
  };
})();
