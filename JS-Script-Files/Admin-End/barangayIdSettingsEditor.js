(() => {
  const payloadEl = document.getElementById('barangayIdSettingsPayload');
  if (!payloadEl || !window.BarangayIdDigital) return;

  let payload = {};
  try {
    payload = JSON.parse(payloadEl.textContent || '{}') || {};
  } catch (_) {
    payload = {};
  }

  const form = document.getElementById('barangayIdSettingsForm');
  const canvas = document.getElementById('barangayIdEditorCanvas');
  const fieldList = document.getElementById('barangayIdFieldList');
  const inspector = document.getElementById('barangayIdFieldInspector');
  const samplePreview = document.getElementById('barangayIdSamplePreview');
  const layoutJsonInput = document.getElementById('barangayIdLayoutJson');
  const sampleJsonInput = document.getElementById('barangayIdSampleJson');
  const frontTemplatePreview = document.getElementById('frontTemplatePreview');
  const backTemplatePreview = document.getElementById('backTemplatePreview');
  const deleteSelectedFieldBtn = document.getElementById('deleteSelectedField');
  const restoreDefaultsBtn = document.getElementById('restoreBarangayIdDefaults');
  const signatureFileInput = document.getElementById('signatureFilePunong');
  const signaturePreview = document.getElementById('punongSignaturePreview');
  const removeSignatureCheckbox = document.getElementById('removeSignaturePunong');
  const sideButtons = Array.from(document.querySelectorAll('[data-bid-side-btn]'));
  const addButtons = Array.from(document.querySelectorAll('[data-bid-add-type]'));
  const sampleInputs = Array.from(document.querySelectorAll('[data-bid-sample-input]'));
  const processStepButtons = Array.from(document.querySelectorAll('[data-bid-step]'));
  const processShortcutButtons = Array.from(document.querySelectorAll('[data-bid-go-step]'));
  const previousStepBtn = document.querySelector('[data-bid-step-prev]');
  const nextStepBtn = document.querySelector('[data-bid-step-next]');
  const saveStepBtn = document.querySelector('[data-bid-step-save]');
  const uploadActionSelects = Array.from(document.querySelectorAll('[data-bid-upload-action]'));
  const addFieldSelect = document.getElementById('barangayIdAddFieldSelect');
  const quickColorInput = document.getElementById('barangayIdQuickColor');
  const quickFontStyleSelect = document.getElementById('barangayIdQuickFontStyle');
  const quickAlignmentWrap = document.getElementById('barangayIdQuickAlignment');
  const quickAlignmentButtons = Array.from(document.querySelectorAll('[data-bid-quick-align]'));
  const quickUppercaseInput = document.getElementById('barangayIdQuickUppercase');
  const quickMultilineInput = document.getElementById('barangayIdQuickMultiline');
  const quickMaxLinesInput = document.getElementById('barangayIdQuickMaxLines');
  const quickCornerRadiusWrap = document.getElementById('barangayIdQuickCornerRadiusWrap');
  const quickCornerRadiusInput = document.getElementById('barangayIdQuickCornerRadius');
  const editorViewButtons = Array.from(document.querySelectorAll('[data-bid-editor-view-btn]'));
  const editorViewPanels = Array.from(document.querySelectorAll('[data-bid-editor-view-panel]'));
  const sideSelector = document.getElementById('barangayIdSideSelector');

  const defaultLayout = window.BarangayIdDigital.defaultLayoutConfig();
  const defaultSample = window.BarangayIdDigital.defaultSampleData();
  const sourceOptions = [
    { value: 'cardFullName', label: 'Full Name' },
    { value: 'cardFullAddress', label: 'Full Address' },
    { value: 'cardBirthdate', label: 'Birthdate' },
    { value: 'cardBirthplace', label: 'Birthplace' },
    { value: 'cardSex', label: 'Sex' },
    { value: 'cardContactNumber', label: 'Contact Number' },
    { value: 'cardEmergencyName', label: 'Emergency Name' },
    { value: 'cardEmergencyAddress', label: 'Emergency Address' },
    { value: 'cardEmergencyContact', label: 'Emergency Contact Number' },
    { value: 'cardNumber', label: 'Card Number' },
    { value: 'validUntil', label: 'Valid Until' },
    { value: 'photoUrl', label: 'Resident Photo' },
    { value: 'punongSignatorySignatureUrl', label: 'Signature' },
    { value: 'qrUrl', label: 'Verification QR' },
  ];
  const fieldTemplates = {
    text: { type: 'text', label: 'Text Field', source: 'cardFullName', w: 28, h: 4.8, fontStyle: 'B', fontSize: 6.0, minFontSize: 4.2, uppercase: true, align: 'left', multiline: false, maxLines: 1, color: '#111111' },
    image: { type: 'image', label: 'Image Field', source: 'photoUrl', w: 16, h: 16, fit: 'cover', cornerRadius: 0 },
    qr: { type: 'qr', label: 'QR Field', source: 'qrUrl', w: 16, h: 16, fit: 'fill' },
    signature: { type: 'image', label: 'Signature', source: 'punongSignatorySignatureUrl', w: 30, h: 8, fit: 'contain' },
    cover: { type: 'cover', label: 'Cover Block', w: 18, h: 8, backgroundColor: '#ffffff' },
  };

  const state = {
    activeSide: 'front',
    editorView: 'editor',
    layout: window.BarangayIdDigital.normalizeLayoutConfig(payload.layoutConfig || defaultLayout),
    sampleData: { ...defaultSample, ...(payload.sampleData && typeof payload.sampleData === 'object' ? payload.sampleData : {}) },
    selectedFieldId: '',
    frontTemplateUrl: String(payload.frontTemplateUrl || '').trim(),
    backTemplateUrl: String(payload.backTemplateUrl || '').trim(),
    signatory: {
      name: String(payload.signatory?.name || 'HON. GLENN S. EVANGELISTA').trim() || 'HON. GLENN S. EVANGELISTA',
      title: String(payload.signatory?.title || 'Punong Barangay').trim() || 'Punong Barangay',
      signatureUrl: String(payload.signatory?.signatureUrl || '').trim()
    },
    interaction: null,
    objectUrls: []
  };
  const originalTemplateUrls = {
    front: state.frontTemplateUrl,
    back: state.backTemplateUrl
  };

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (match) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;'
    }[match]));
  }

  function cleanupObjectUrls() {
    state.objectUrls.forEach((url) => {
      try { URL.revokeObjectURL(url); } catch (_) {}
    });
    state.objectUrls = [];
  }

  function setProcessStep(rawStep, options = {}) {
    const step = Math.max(1, Math.min(3, Number.parseInt(rawStep, 10) || 1));
    form.dataset.bidActiveStep = String(step);
    processStepButtons.forEach((button) => {
      const buttonStep = Number.parseInt(button.dataset.bidStep || '1', 10) || 1;
      button.classList.toggle('is-active', buttonStep === step);
      button.classList.toggle('is-complete', buttonStep < step);
      button.setAttribute('aria-current', buttonStep === step ? 'step' : 'false');
    });
    if (previousStepBtn) previousStepBtn.hidden = step === 1;
    if (nextStepBtn) nextStepBtn.hidden = step === 3;
    if (saveStepBtn) saveStepBtn.hidden = step !== 3;
    renderAll();
    if (options.scroll !== false) {
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  function pageWidth() {
    return Number(state.layout?.page?.width_mm || 85.6);
  }

  function pageHeight() {
    return Number(state.layout?.page?.height_mm || 54.1);
  }

  function normalizeSampleData() {
    state.sampleData.validityNotice = `This ID is valid until ${String(state.sampleData.validUntil || '').trim() || '____'} except when the holder requests for a new one.`;
  }

  function syncHiddenFields() {
    normalizeSampleData();
    layoutJsonInput.value = JSON.stringify(state.layout, null, 2);
    sampleJsonInput.value = JSON.stringify(state.sampleData, null, 2);
  }

  function fieldTypeLabel(type) {
    return String(type || 'field').replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
  }

  function friendlyFieldName(field) {
    if (!field || typeof field !== 'object') return 'Field';
    const byId = {
      front_photo: 'Resident Photo',
      front_name_value: 'Resident Name',
      front_address_value: 'Resident Address',
      front_birthdate_value: 'Birthdate Value',
      front_sex_value: 'Sex Value',
      front_birthplace_value: 'Birthplace Value',
      front_valid_until_value: 'Valid Until Text',
      front_card_number_value: 'Card Number',
      back_card_number_value: 'Card Number',
      back_emergency_name_value: 'Emergency Contact Name',
      back_emergency_address_value: 'Emergency Address',
      back_emergency_contact_value: 'Emergency Contact Number',
      back_signature: 'Signature',
      back_qr: 'Verification QR Code'
    };
    const direct = byId[String(field.id || '').trim()];
    if (direct) return direct;

    const rawLabel = String(field.label || '').trim();
    if (rawLabel) {
      if (/^text field$/i.test(rawLabel)) return 'Text Value';
      if (/^image field$/i.test(rawLabel)) return 'Image Field';
      if (/^qr field$/i.test(rawLabel)) return 'QR Code';
      return rawLabel;
    }

    if (field.type === 'text') return 'Text Value';
    if (field.type === 'image') return 'Image Field';
    if (field.type === 'qr') return 'QR Code';
    if (field.type === 'signatory') return 'Signatory Block';
    if (field.type === 'cover') return 'Cover Block';
    return fieldTypeLabel(field.type);
  }

  function fieldPurposeText(field) {
    if (!field || typeof field !== 'object') return '';
    if (field.type === 'text') {
      const source = sourceOptions.find((option) => option.value === String(field.source || '').trim());
      return source ? `Uses sample data: ${source.label}` : 'Uses linked text data';
    }
    if (field.type === 'image' && field.source === 'punongSignatorySignatureUrl') return 'Uses uploaded Punong Barangay signature';
    if (field.type === 'image') return 'Uses resident photo';
    if (field.type === 'qr') return 'Uses verification QR code';
    if (field.type === 'signatory') return 'Uses Punong Barangay signature';
    if (field.type === 'cover') return 'Masks old design underneath';
    return '';
  }

  function compactFieldName(field) {
    const full = friendlyFieldName(field);
    const source = String(field?.source || '').trim();
    if (field?.type === 'text') {
      const valueBySource = {
        cardFullName: 'Value: Name',
        cardFullAddress: 'Value: Address',
        cardBirthdate: 'Value: Birthdate',
        cardBirthplace: 'Value: Birthplace',
        cardSex: 'Value: Sex',
        cardContactNumber: 'Value: Contact No.',
        cardEmergencyName: 'Value: Emergency Name',
        cardEmergencyAddress: 'Value: Emergency Address',
        cardEmergencyContact: 'Value: Emergency Contact',
        cardNumber: 'Value: Card No.',
        validUntil: 'Value: Valid Until'
      };
      if (valueBySource[source]) {
        return valueBySource[source];
      }
    }
    const compactById = {
      front_valid_until_value: 'Value: Valid Until',
      front_card_number_value: 'Value: Card No.',
      back_card_number_value: 'Value: Card No.',
      back_signature: 'Signature',
      back_qr: 'QR Code'
    };
    return compactById[String(field?.id || '').trim()] || full;
  }

  function activeFields() {
    return state.layout.fields.filter((field) => field.side === state.activeSide);
  }

  function isSquareLockedField(field) {
    if (!field || typeof field !== 'object') return false;
    if (field.type === 'qr') return true;
    return field.type === 'image' && String(field.source || '').trim() === 'photoUrl';
  }

  function fieldUsesMaxSize(field) {
    if (!field || typeof field !== 'object') return true;
    if (field.type === 'image' || field.type === 'qr') return false;
    if (field.type === 'text') {
      const source = String(field.source || '').trim();
      const id = String(field.id || '').trim();
      return source !== 'cardNumber' && !id.includes('card_number');
    }
    return true;
  }

  function fieldMinimumSize(field, axis) {
    const dimension = axis === 'h' ? 'h' : 'w';
    if (!field || typeof field !== 'object') {
      return dimension === 'h' ? 4.5 : 12;
    }

    if (field.type === 'image') {
      return dimension === 'h' ? 12 : 12;
    }
    if (field.type === 'qr') {
      return dimension === 'h' ? 12 : 12;
    }
    if (field.type === 'signatory') {
      return dimension === 'h' ? 10 : 22;
    }

    if (state.editorView === 'layout' && field.type === 'cover') {
      return `<span class="bid-editor-field__resize" data-bid-resize="1"></span>`;
    }
    if (field.type === 'cover') {
      return dimension === 'h' ? 6 : 10;
    }
    if (field.type === 'text') {
      return dimension === 'h' ? 2.2 : 5;
    }

    return dimension === 'h' ? 2.2 : 5;
  }

  function fieldMaximumSize(field, axis) {
    const dimension = axis === 'h' ? 'h' : 'w';
    const pageLimit = dimension === 'h' ? pageHeight() : pageWidth();
    if (!fieldUsesMaxSize(field)) {
      return pageLimit;
    }
    const key = dimension === 'h' ? 'maxH' : 'maxW';
    const value = Number(field?.[key] || 0);
    if (Number.isFinite(value) && value > 0) {
      return Math.max(fieldMinimumSize(field, dimension), Math.min(pageLimit, value));
    }
    return pageLimit;
  }

  function canvasAlignmentClass(field) {
    if (field?.type === 'image' && String(field.source || '').trim() === 'photoUrl') {
      return ' is-center';
    }
    return field?.align === 'center' ? ' is-center' : field?.align === 'right' ? ' is-right' : '';
  }

  function getFieldById(fieldId) {
    return state.layout.fields.find((field) => field.id === fieldId) || null;
  }

  function ensureSelection() {
    const fields = activeFields();
    if (!fields.length) {
      state.selectedFieldId = '';
      return;
    }
    if (!state.selectedFieldId || !fields.some((field) => field.id === state.selectedFieldId)) {
      state.selectedFieldId = fields[0].id;
    }
  }

  function sampleState() {
    return window.BarangayIdDigital.createSampleState({
      appBase: String(payload.appBase || '').trim(),
      frontTemplateUrl: state.frontTemplateUrl,
      backTemplateUrl: state.backTemplateUrl,
      layoutConfig: state.layout,
      sampleData: state.sampleData,
      row: {
        punong_signatory_name: state.signatory.name,
        punong_signatory_title: state.signatory.title,
        punong_signatory_signature_path: removeSignatureCheckbox?.checked ? '' : state.signatory.signatureUrl
      },
      fallbackProfileImageUrl: `${String(payload.appBase || '').trim()}/Images/Profile-Placeholder.png`
    });
  }

  function mmToPctX(value) {
    return `${(Number(value || 0) / pageWidth()) * 100}%`;
  }

  function mmToPctY(value) {
    return `${(Number(value || 0) / pageHeight()) * 100}%`;
  }

  function sampleValueForField(field) {
    if (field.type === 'cover') {
      return 'Cover Block';
    }
    if (field.type === 'signatory') {
      return `${state.signatory.name}\n${state.signatory.title}`;
    }
    if (field.type === 'image') {
      return 'Image';
    }
    if (field.type === 'qr') {
      return 'QR';
    }
    const currentSampleState = sampleState();
    const source = String(field.source || '').trim();
    const fallbackSource = String(field.fallbackSource || '').trim();
    let value = source ? String(currentSampleState?.[source] ?? '').trim() : '';
    if (!value && fallbackSource) {
      value = String(currentSampleState?.[fallbackSource] ?? '').trim();
    }
    const prefixed = `${String(field.prefix || '')}${value}`.trim();
    return prefixed || 'Text';
  }

  function cardPixelScale() {
    const width = canvas?.getBoundingClientRect?.().width || 856;
    return Math.max(0.55, width / 856);
  }

  function editorFontPx(field) {
    const fontSize = Number(field?.fontSize || 6) || 6;
    return Math.max(6, Number((fontSize * 1.33 * cardPixelScale()).toFixed(2)));
  }

  function editorLabelFontPx(field, label) {
    const rect = canvas?.getBoundingClientRect?.();
    const canvasWidth = rect?.width || 856;
    const canvasHeight = rect?.height || ((canvasWidth / pageWidth()) * pageHeight());
    const fieldWidthPx = (Number(field?.w || 0) / pageWidth()) * canvasWidth;
    const fieldHeightPx = (Number(field?.h || 0) / pageHeight()) * canvasHeight;
    const textLength = Math.max(8, String(label || '').length);
    const widthFit = (fieldWidthPx - 12) / (textLength * 0.58);
    const heightFit = fieldHeightPx * 0.58;
    return Math.max(7, Math.min(28, Number(Math.min(widthFit, heightFit).toFixed(2))));
  }

  function fitToObjectPosition(fit) {
    if (fit === 'contain') return 'contain';
    if (fit === 'fill') return 'fill';
    return 'cover';
  }

  function mediaUrlForField(field) {
    const currentSampleState = sampleState();
    if (field.type === 'qr') {
      return String(currentSampleState.qrUrl || currentSampleState.qrFallbackUrl || '').trim();
    }
    if (field.type === 'image') {
      const source = String(field.source || '').trim() || 'photoUrl';
      const fallback = source === 'photoUrl' ? currentSampleState.photoUrl : '';
      return String(currentSampleState?.[source] || fallback || '').trim();
    }
    return '';
  }

  function editorFieldInnerHtml(field, displayName, purpose) {
    const deleteControl = state.editorView === 'editor'
      ? `<span class="bid-editor-field__delete" data-bid-delete-field="1" title="Remove ${escapeHtml(displayName)}" aria-label="Remove ${escapeHtml(displayName)}">×</span>`
      : '';
    if (field.type === 'image' || field.type === 'qr') {
      const mediaUrl = mediaUrlForField(field);
      const label = field.type === 'qr'
        ? 'QR'
        : String(field.source || '') === 'punongSignatorySignatureUrl'
          ? 'Signature'
          : 'Photo';
      return `
        ${mediaUrl ? `<img class="bid-editor-field__media${label === 'Signature' ? ' is-signature' : ''}" src="${escapeHtml(mediaUrl)}" alt="${escapeHtml(displayName)}">` : `<span class="bid-editor-field__placeholder${label === 'Signature' ? ' is-signature' : ''}">${escapeHtml(label)}</span>`}
        ${state.editorView === 'editor' ? `<span class="bid-editor-field__tag">${escapeHtml(displayName)}</span>` : ''}
        ${purpose ? `<span class="visually-hidden">${escapeHtml(purpose)}</span>` : ''}
        ${deleteControl}
        <span class="bid-editor-field__resize" data-bid-resize="1"></span>
      `;
    }

    if (field.type === 'signatory') {
      return `
        <span class="bid-editor-field__tag">${escapeHtml(displayName)}</span>
        ${purpose ? `<span class="visually-hidden">${escapeHtml(purpose)}</span>` : ''}
        ${deleteControl}
        <span class="bid-editor-field__resize" data-bid-resize="1"></span>
      `;
    }

    if (state.editorView === 'layout' && field.type === 'text') {
      const alignmentClass = field.align === 'center' ? ' is-center' : field.align === 'right' ? ' is-right' : '';
      const multilineClass = fieldIsMultiline(field) ? ' is-multiline' : '';
      const maxLines = Math.max(1, Math.min(3, Number(field.maxLines || 1)));
      const addressMultiplier = ['cardFullAddress', 'cardEmergencyAddress'].includes(String(field.source || '')) ? 3 : 1;
      const configuredMaxFont = Number(field.fontSize || 6);
      const heightDrivenFont = fieldIsMultiline(field)
        ? (Number(field.h || 0) / Math.max(1, Math.min(maxLines, 3))) * 4.15
        : Number(field.h || 0) * 4.15;
      // Keep these values in the same unscaled units used by the final ID
      // renderer. fitLayoutSample applies the live card-width scale below.
      const maxFont = Math.max(2.8, Math.min(72, Math.max(configuredMaxFont * 1.33, heightDrivenFont || configuredMaxFont) * addressMultiplier));
      const minFont = Math.max(1.4, Math.min(Number(field.minFontSize || 3.2) * 1.15 * addressMultiplier, maxFont));
      const sampleValue = sampleValueForField(field);
      return `
        <span class="bid-editor-field__sample${alignmentClass}${multilineClass}"
          data-bid-autofit="1"
          data-bid-scale="1"
          data-bid-max-font="${maxFont.toFixed(2)}"
          data-bid-min-font="${Math.min(minFont, maxFont).toFixed(2)}"
          data-bid-max-lines="${maxLines}"
          data-bid-line-height="${fieldIsMultiline(field) ? '1.04' : '1.05'}"
          data-bid-multiline="${fieldIsMultiline(field) ? '1' : '0'}"
          data-bid-value="${escapeHtml(sampleValue)}">${escapeHtml(sampleValue)}</span>
        <span class="bid-editor-field__resize" data-bid-resize="1"></span>
      `;
    }

    return `
      <span class="bid-editor-field__tag">${escapeHtml(displayName)}</span>
      ${purpose ? `<span class="visually-hidden">${escapeHtml(purpose)}</span>` : ''}
      ${deleteControl}
      <span class="bid-editor-field__resize" data-bid-resize="1"></span>
    `;
  }

  function fieldIsMultiline(field) {
    return !!field?.multiline || Number(field?.maxLines || 1) > 1;
  }

  function fitLayoutSample(element) {
    if (!(element instanceof HTMLElement)) return;
    const fieldBox = element.parentElement;
    if (!(fieldBox instanceof HTMLElement) || fieldBox.clientWidth <= 1 || fieldBox.clientHeight <= 1) return;

    const multiline = element.dataset.bidMultiline === '1';
    const maxLines = Math.max(1, Math.min(3, Number.parseInt(element.dataset.bidMaxLines || '1', 10) || 1));
    // This is deliberately identical to barangayIdDigital.autoFitElement:
    // previews scale configured font units from a 300px reference card.
    const cardScale = Math.max(0.35, canvas.clientWidth / 300);
    const maxFont = Math.max(2, (Number.parseFloat(element.dataset.bidMaxFont || '8') || 8) * cardScale);
    const minFont = Math.max(1.4 * cardScale, Math.min(maxFont, (Number.parseFloat(element.dataset.bidMinFont || '3') || 3) * cardScale));
    const lineHeight = Math.max(0.9, Math.min(1.4, Number.parseFloat(element.dataset.bidLineHeight || '1.05') || 1.05));
    const originalValue = String(element.dataset.bidValue || element.textContent || '').trim();

    // Always restore the sample value before measuring. The shared final-ID
    // fitter may replace text nodes with line breaks, but editable layout
    // fields must remain deterministic across every re-render and resize.
    element.textContent = originalValue;
    element.style.display = 'block';
    element.style.lineHeight = String(lineHeight);
    element.style.whiteSpace = 'nowrap';
    element.style.overflowWrap = multiline ? 'normal' : '';
    element.style.wordBreak = 'normal';

    const fontStep = 0.2 * cardScale;
    let size = maxFont;
    element.style.fontSize = `${size}px`;
    if (multiline && maxLines > 1 && element.scrollWidth > fieldBox.clientWidth + 1) {
      size = maxFont / 2;
      element.style.fontSize = `${size}px`;
    }
    element.style.whiteSpace = multiline ? 'normal' : 'nowrap';
    const fits = () => {
      const renderedLines = Math.max(1, Math.round(element.scrollHeight / Math.max(1, size * lineHeight)));
      return element.scrollWidth <= fieldBox.clientWidth + 1
        && element.scrollHeight <= fieldBox.clientHeight + 1
        && (!multiline || renderedLines <= maxLines);
    };

    while (size > minFont && !fits()) {
      size = Math.max(minFont, Number((size - fontStep).toFixed(2)));
      element.style.fontSize = `${size}px`;
    }
  }

  function renderCanvas() {
    ensureSelection();
    canvas.style.backgroundImage = `url("${state.activeSide === 'front' ? state.frontTemplateUrl : state.backTemplateUrl}")`;
    canvas.innerHTML = '';

    activeFields().forEach((field) => {
      const fieldEl = document.createElement('button');
      fieldEl.type = 'button';
      fieldEl.className = `bid-editor-field${field.type === 'image' || field.type === 'qr' ? ' is-media' : ''}${field.id === state.selectedFieldId ? ' is-selected' : ''}`;
      fieldEl.dataset.fieldId = field.id;
      fieldEl.title = `${friendlyFieldName(field)} - drag to move, resize from the corner`;
      fieldEl.style.left = mmToPctX(field.x);
      fieldEl.style.top = mmToPctY(field.y);
      fieldEl.style.width = mmToPctX(field.w);
      fieldEl.style.height = mmToPctY(field.h);
      fieldEl.style.borderRadius = `${Math.max(0, Math.min(50, Number(field.cornerRadius || 0)))}%`;
      fieldEl.style.zIndex = String(field.z || 2);
      if (field.type === 'cover') fieldEl.style.backgroundColor = field.backgroundColor || '#ffffff';
      fieldEl.style.setProperty('--bid-editor-font-size', `${editorFontPx(field)}px`);
      fieldEl.style.setProperty('--bid-editor-line-height', fieldIsMultiline(field) ? '1.04' : '1.05');
      fieldEl.style.setProperty('--bid-editor-color', field.color || '#111111');
      fieldEl.style.setProperty('--bid-editor-font-weight', String(field.fontStyle || '').includes('B') ? '800' : '400');
      fieldEl.style.setProperty('--bid-editor-font-style', String(field.fontStyle || '').includes('I') ? 'italic' : 'normal');
      fieldEl.style.setProperty('--bid-editor-text-transform', field.uppercase ? 'uppercase' : 'none');
      fieldEl.style.setProperty('--bid-editor-object-fit', fitToObjectPosition(field.fit));
      const displayName = compactFieldName(field);
      fieldEl.style.setProperty('--bid-editor-label-font-size', `${editorLabelFontPx(field, displayName)}px`);
      const purpose = fieldPurposeText(field);
      fieldEl.innerHTML = editorFieldInnerHtml(field, displayName, purpose);

      fieldEl.addEventListener('click', () => {
        state.selectedFieldId = field.id;
        renderAll();
      });

      fieldEl.querySelector('[data-bid-delete-field="1"]')?.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        state.layout.fields = state.layout.fields.filter((candidate) => candidate.id !== field.id);
        state.selectedFieldId = '';
        renderAll();
      });

      fieldEl.addEventListener('pointerdown', (event) => {
        if (event.target instanceof HTMLElement && event.target.dataset.bidDeleteField === '1') return;
        const isResize = event.target instanceof HTMLElement && event.target.dataset.bidResize === '1';
        beginPointerInteraction(event, field.id, isResize ? 'resize' : 'move');
      });

      canvas.appendChild(fieldEl);
    });
    if (state.editorView === 'layout') {
      canvas.querySelectorAll('.bid-editor-field__sample').forEach(fitLayoutSample);
    }
  }

  function renderFieldList() {
    ensureSelection();
    const fields = activeFields();
    fieldList.innerHTML = '';
    if (!fields.length) {
      fieldList.innerHTML = '<div class="text-muted small">No fields on this side yet. Add one from the toolbar above.</div>';
      return;
    }

    fields.forEach((field) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = `bid-field-item${field.id === state.selectedFieldId ? ' is-active' : ''}`;
      const displayName = friendlyFieldName(field);
      const purpose = fieldPurposeText(field);
      button.innerHTML = `
        <div class="bid-field-item__meta">
          <span class="bid-field-type">${fieldTypeLabel(field.type)}</span>
          <span>X ${field.x.toFixed(1)} / Y ${field.y.toFixed(1)}</span>
        </div>
        <div class="fw-semibold">${escapeHtml(displayName)}</div>
        ${purpose ? `<div class="small text-muted">${escapeHtml(purpose)}</div>` : ''}
        <div class="small text-muted">${field.w.toFixed(1)}mm x ${field.h.toFixed(1)}mm</div>
      `;
      button.addEventListener('click', () => {
        state.selectedFieldId = field.id;
        renderAll();
      });
      fieldList.appendChild(button);
    });
  }

  function selectMarkup(selected, value, label) {
    return `<option value="${escapeHtml(value)}"${selected === value ? ' selected' : ''}>${escapeHtml(label)}</option>`;
  }

  function sourceOptionsMarkup(selectedValue) {
    return sourceOptions.map((option) => selectMarkup(selectedValue, option.value, option.label)).join('');
  }

  function inputMarkup(config) {
    const {
      label,
      prop,
      type = 'text',
      value = '',
      step = 'any',
      min = '',
      max = '',
      checked = false,
      options = '',
      col = ''
    } = config;
    const wrapperClass = col || '';
    if (type === 'checkbox') {
      return `
        <div class="${wrapperClass}">
          <label class="form-check mt-4">
            <input class="form-check-input" type="checkbox" data-bid-prop="${prop}" ${checked ? 'checked' : ''}>
            <span class="form-check-label">${escapeHtml(label)}</span>
          </label>
        </div>
      `;
    }
    if (type === 'select') {
      return `
        <label class="form-label mb-0 ${wrapperClass}">
          <span class="fw-semibold d-block mb-1">${escapeHtml(label)}</span>
          <select class="form-select" data-bid-prop="${prop}">${options}</select>
        </label>
      `;
    }
    return `
      <label class="form-label mb-0 ${wrapperClass}">
        <span class="fw-semibold d-block mb-1">${escapeHtml(label)}</span>
        <input class="form-control" type="${type}" data-bid-prop="${prop}" value="${escapeHtml(value)}" step="${step}" min="${min}" max="${max}">
      </label>
    `;
  }

  function renderInspector() {
    const field = getFieldById(state.selectedFieldId);
    inspector.innerHTML = '';
    deleteSelectedFieldBtn.disabled = !field;
    if (!field) {
      inspector.innerHTML = '<div class="text-muted small">Select a field from the editor or field list to edit it.</div>';
      return;
    }

    const minWidth = fieldMinimumSize(field, 'w');
    const minHeight = fieldMinimumSize(field, 'h');
    const maxWidth = fieldMaximumSize(field, 'w');
    const maxHeight = fieldMaximumSize(field, 'h');

    const blocks = [
      inputMarkup({ label: 'Field Name', prop: 'label', value: friendlyFieldName(field) }),
      `
        <label class="form-label mb-0">
          <span class="fw-semibold d-block mb-1">Type</span>
          <input class="form-control" type="text" value="${escapeHtml(fieldTypeLabel(field.type))}" readonly>
        </label>
      `,
      inputMarkup({ label: 'X (mm)', prop: 'x', type: 'number', value: field.x, min: '0', max: String(pageWidth()) }),
      inputMarkup({ label: 'Y (mm)', prop: 'y', type: 'number', value: field.y, min: '0', max: String(pageHeight()) }),
      inputMarkup({ label: 'Width (mm)', prop: 'w', type: 'number', value: field.w, min: String(minWidth), max: String(maxWidth) }),
      inputMarkup({ label: 'Height (mm)', prop: 'h', type: 'number', value: field.h, min: String(minHeight), max: String(maxHeight) }),
      inputMarkup({ label: 'Layer Order', prop: 'z', type: 'number', value: field.z, min: '1', max: '20', step: '1' }),
    ];

    if (fieldUsesMaxSize(field)) {
      blocks.splice(6, 0,
        inputMarkup({ label: 'Max Width (mm)', prop: 'maxW', type: 'number', value: field.maxW || field.w, min: String(minWidth), max: String(pageWidth()) }),
        inputMarkup({ label: 'Max Height (mm)', prop: 'maxH', type: 'number', value: field.maxH || field.h, min: String(minHeight), max: String(pageHeight()) })
      );
    }

    if (field.type === 'text') {
      blocks.splice(2, 0,
        inputMarkup({
          label: 'Alignment',
          prop: 'align',
          type: 'select',
          options: [
            selectMarkup(field.align, 'left', 'Left'),
            selectMarkup(field.align, 'center', 'Center'),
            selectMarkup(field.align, 'right', 'Right')
          ].join('')
        }),
        inputMarkup({
          label: 'Font Style',
          prop: 'fontStyle',
          type: 'select',
          options: [
            selectMarkup(field.fontStyle, 'B', 'Bold'),
            selectMarkup(field.fontStyle, 'I', 'Italic'),
            selectMarkup(field.fontStyle, 'BI', 'Bold + Italic'),
            selectMarkup(field.fontStyle, '', 'Regular')
          ].join('')
        }),
        inputMarkup({ label: 'Text Color', prop: 'color', type: 'color', value: field.color, step: '1' }),
        inputMarkup({ label: 'Max Font Size', prop: 'fontSize', type: 'number', value: field.fontSize, min: '2.8', max: '36' }),
        inputMarkup({ label: 'Min Font Size', prop: 'minFontSize', type: 'number', value: field.minFontSize, min: '2.4', max: '24' })
      );
      blocks.push(inputMarkup({
        label: 'Data Source',
        prop: 'source',
        type: 'select',
        options: sourceOptionsMarkup(field.source)
      }));
      blocks.push(inputMarkup({
        label: 'Fallback Source',
        prop: 'fallbackSource',
        type: 'select',
        options: selectMarkup(field.fallbackSource, '', 'None') + sourceOptionsMarkup(field.fallbackSource)
      }));
      blocks.push(inputMarkup({ label: 'Prefix', prop: 'prefix', value: field.prefix }));
      blocks.push(inputMarkup({ label: 'Uppercase Text', prop: 'uppercase', type: 'checkbox', checked: !!field.uppercase }));
      blocks.push(inputMarkup({ label: 'Multiline Fit', prop: 'multiline', type: 'checkbox', checked: !!field.multiline }));
      blocks.push(inputMarkup({ label: 'Max Lines', prop: 'maxLines', type: 'number', value: field.maxLines, min: '1', max: '12', step: '1' }));
    }

    if (field.type === 'image' || field.type === 'qr') {
      blocks.push(inputMarkup({
        label: 'Data Source',
        prop: 'source',
        type: 'select',
        options: sourceOptionsMarkup(field.source)
      }));
      blocks.push(inputMarkup({
        label: 'Image Fit',
        prop: 'fit',
        type: 'select',
        options: [
          selectMarkup(field.fit, 'cover', 'Cover'),
          selectMarkup(field.fit, 'contain', 'Contain'),
          selectMarkup(field.fit, 'fill', 'Fill')
        ].join('')
      }));
    }

    if (field.type === 'cover') {
      blocks.push(inputMarkup({ label: 'Cover Color', prop: 'backgroundColor', type: 'color', value: field.backgroundColor, step: '1' }));
    }

    inspector.innerHTML = blocks.join('');
    inspector.querySelectorAll('[data-bid-prop]').forEach((input) => {
      const prop = input.dataset.bidProp;
      if (!prop) return;
      const eventName = input.type === 'checkbox' || input.tagName === 'SELECT' ? 'change' : 'input';
      input.addEventListener(eventName, () => updateFieldProp(field.id, prop, input.type === 'checkbox' ? input.checked : input.value));
    });
  }

  function renderSamplePreview() {
    const previewState = sampleState();
    window.BarangayIdDigital.renderInto(samplePreview, previewState, {
      eyebrow: 'Sample Preview',
      helper: 'This preview reflects the current uploaded templates, field layout, sample values, and signature image.',
      frontLabel: 'Front Card',
      backLabel: 'Back Card'
    });
  }

  function renderSideButtons() {
    sideButtons.forEach((button) => {
      button.classList.toggle('is-active', button.dataset.bidSideBtn === state.activeSide);
    });
  }

  function renderEditorView() {
    editorViewButtons.forEach((button) => {
      button.classList.toggle('is-active', button.dataset.bidEditorViewBtn === state.editorView);
    });
    editorViewPanels.forEach((panel) => {
      panel.hidden = panel.dataset.bidEditorViewPanel !== 'editor';
    });
    if (sideSelector) sideSelector.hidden = false;
  }

  function renderQuickControls() {
    const field = getFieldById(state.selectedFieldId);
    const isText = field?.type === 'text';
    const isImage = field?.type === 'image';
    [quickColorInput, quickFontStyleSelect, quickUppercaseInput, quickMultilineInput, quickMaxLinesInput].forEach((control) => {
      if (control) control.disabled = !isText;
    });
    quickAlignmentButtons.forEach((button) => {
      button.disabled = !isText;
      button.classList.toggle('is-active', isText && button.dataset.bidQuickAlign === field.align);
    });
    quickAlignmentWrap?.classList.toggle('is-disabled', !isText);
    if (quickCornerRadiusWrap) quickCornerRadiusWrap.hidden = !isImage;
    if (quickCornerRadiusInput) {
      quickCornerRadiusInput.disabled = !isImage;
      quickCornerRadiusInput.value = String(Math.max(0, Math.min(50, Number(field?.cornerRadius || 0))));
    }
    if (!isText) return;
    if (quickColorInput) quickColorInput.value = /^#[0-9a-f]{6}$/i.test(field.color || '') ? field.color : '#111111';
    if (quickFontStyleSelect) {
      const fontStyle = String(field.fontStyle || '').toUpperCase();
      quickFontStyleSelect.value = fontStyle.includes('B') && fontStyle.includes('I')
        ? 'BI'
        : fontStyle.includes('B')
          ? 'B'
          : fontStyle.includes('I')
            ? 'I'
            : '';
    }
    if (quickUppercaseInput) quickUppercaseInput.checked = !!field.uppercase;
    if (quickMultilineInput) quickMultilineInput.checked = !!field.multiline || Number(field.maxLines || 1) > 1;
    if (quickMaxLinesInput) {
      quickMaxLinesInput.value = String(Math.max(1, Math.min(3, Number(field.maxLines || 1))));
      quickMaxLinesInput.disabled = !isText || !quickMultilineInput?.checked;
    }
  }

  function renderAll() {
    state.layout = window.BarangayIdDigital.normalizeLayoutConfig(state.layout);
    ensureSelection();
    syncHiddenFields();
    renderEditorView();
    renderSideButtons();
    renderCanvas();
    renderFieldList();
    renderInspector();
    renderQuickControls();
    renderSamplePreview();
  }

  function updateFieldProp(fieldId, prop, rawValue) {
    const field = getFieldById(fieldId);
    if (!field) return;
    const squareLocked = isSquareLockedField(field);
    const minWidth = fieldMinimumSize(field, 'w');
    const minHeight = fieldMinimumSize(field, 'h');

    const numericProps = new Set(['x', 'y', 'w', 'h', 'maxW', 'maxH', 'fontSize', 'minFontSize', 'z', 'maxLines', 'cornerRadius']);
    const booleanProps = new Set(['uppercase', 'multiline']);

    if (numericProps.has(prop)) {
      const parsed = prop === 'z'
        ? Number.parseInt(rawValue, 10)
        : Number.parseFloat(rawValue);
      if (Number.isFinite(parsed)) {
        field[prop] = prop === 'z' || prop === 'maxLines' ? parsed : Number(parsed.toFixed(2));
      }
    } else if (booleanProps.has(prop)) {
      field[prop] = !!rawValue;
    } else {
      field[prop] = String(rawValue ?? '');
    }

    if (squareLocked && (prop === 'w' || prop === 'h')) {
      const minSquare = Math.max(minWidth, minHeight);
      const squareSize = Math.max(minSquare, Number(prop === 'w' ? field.w : field.h) || minSquare);
      field.w = squareSize;
      field.h = squareSize;
    }

    if (prop === 'maxW') field.maxW = Math.max(minWidth, Math.min(pageWidth(), Number(field.maxW || field.w || minWidth)));
    if (prop === 'maxH') field.maxH = Math.max(minHeight, Math.min(pageHeight(), Number(field.maxH || field.h || minHeight)));
    const maxWidth = fieldMaximumSize(field, 'w');
    const maxHeight = fieldMaximumSize(field, 'h');
    if (prop === 'w' || prop === 'maxW') field.w = Math.max(minWidth, Math.min(maxWidth, Number(field.w || minWidth)));
    if (prop === 'h' || prop === 'maxH') field.h = Math.max(minHeight, Math.min(maxHeight, Number(field.h || minHeight)));
    if (squareLocked) {
      const minSquare = Math.max(minWidth, minHeight);
      const maxSquare = Math.max(minSquare, Math.min(
        fieldMaximumSize(field, 'w'),
        fieldMaximumSize(field, 'h'),
        pageWidth() - Number(field.x || 0),
        pageHeight() - Number(field.y || 0)
      ));
      const squareSize = Math.max(minSquare, Math.min(maxSquare, Number(field.w || field.h || minSquare)));
      field.w = squareSize;
      field.h = squareSize;
    }
    if (prop === 'x') field.x = Math.max(0, Math.min(pageWidth() - field.w, Number(field.x || 0)));
    if (prop === 'y') field.y = Math.max(0, Math.min(pageHeight() - field.h, Number(field.y || 0)));
    if (prop === 'fontSize') field.fontSize = Math.max(2.8, Math.min(36, Number(field.fontSize || 2.8)));
    if (prop === 'minFontSize') field.minFontSize = Math.max(2.4, Math.min(Number(field.fontSize || 24), Number(field.minFontSize || 2.4)));
    if (prop === 'maxLines') {
      field.maxLines = Math.max(1, Math.min(12, Number(field.maxLines || 1)));
      if (field.maxLines > 1) {
        field.multiline = true;
      }
    }
    if (prop === 'multiline' && !field.multiline) {
      field.maxLines = 1;
    }
    if (prop === 'z') field.z = Math.max(1, Math.min(20, Number(field.z || 1)));
    if (prop === 'cornerRadius') field.cornerRadius = Math.max(0, Math.min(50, Number(field.cornerRadius || 0)));
    renderAll();
  }

  function addField(type) {
    const requested = String(type || '').trim();
    const source = requested.startsWith('source:') ? requested.slice(7) : '';
    const resolvedType = source === 'photoUrl'
      ? 'image'
      : source === 'qrUrl'
        ? 'qr'
        : source === 'punongSignatorySignatureUrl'
          ? 'signature'
          : source
            ? 'text'
            : requested;
    const template = fieldTemplates[resolvedType];
    if (!template) return;
    const existingIds = new Set(state.layout.fields.map((field) => String(field.id || '')));
    let suffix = 1;
    const idPrefix = (source || resolvedType).replace(/[^a-z0-9]+/gi, '_').toLowerCase();
    let nextId = `${idPrefix}_${suffix}`;
    while (existingIds.has(nextId)) {
      suffix += 1;
      nextId = `${idPrefix}_${suffix}`;
    }
    const field = {
      ...template,
      ...(source ? { source, label: sourceOptions.find((option) => option.value === source)?.label || template.label } : {}),
      id: nextId,
      side: state.activeSide,
      x: 8,
      y: 8,
      z: 2 + activeFields().length
    };
    state.layout.fields.push(field);
    state.selectedFieldId = nextId;
    renderAll();
  }

  function deleteSelectedField() {
    if (!state.selectedFieldId) return;
    state.layout.fields = state.layout.fields.filter((field) => field.id !== state.selectedFieldId);
    state.selectedFieldId = '';
    renderAll();
  }

  function beginPointerInteraction(event, fieldId, mode) {
    const field = getFieldById(fieldId);
    if (!field || !canvas) return;
    const rect = canvas.getBoundingClientRect();
    state.selectedFieldId = fieldId;
    state.interaction = {
      fieldId,
      mode,
      pointerId: event.pointerId,
      startClientX: event.clientX,
      startClientY: event.clientY,
      startField: { x: field.x, y: field.y, w: field.w, h: field.h },
      rect
    };
    canvas.setPointerCapture?.(event.pointerId);
    event.preventDefault();
    renderAll();
  }

  function endPointerInteraction() {
    state.interaction = null;
  }

  function handlePointerMove(event) {
    if (!state.interaction) return;
    const field = getFieldById(state.interaction.fieldId);
    if (!field) return;
    const squareLocked = isSquareLockedField(field);
    const minWidth = fieldMinimumSize(field, 'w');
    const minHeight = fieldMinimumSize(field, 'h');
    const maxWidth = fieldMaximumSize(field, 'w');
    const maxHeight = fieldMaximumSize(field, 'h');

    const dxPx = event.clientX - state.interaction.startClientX;
    const dyPx = event.clientY - state.interaction.startClientY;
    const dxMm = (dxPx / state.interaction.rect.width) * pageWidth();
    const dyMm = (dyPx / state.interaction.rect.height) * pageHeight();

    if (state.interaction.mode === 'move') {
      field.x = Math.max(0, Math.min(pageWidth() - field.w, Number((state.interaction.startField.x + dxMm).toFixed(2))));
      field.y = Math.max(0, Math.min(pageHeight() - field.h, Number((state.interaction.startField.y + dyMm).toFixed(2))));
    } else {
      if (squareLocked) {
        const delta = Math.abs(dxMm) >= Math.abs(dyMm) ? dxMm : dyMm;
        const nextSize = Number((state.interaction.startField.w + delta).toFixed(2));
        const minSquare = Math.max(minWidth, minHeight);
        const maxSquare = Math.max(minSquare, Math.min(maxWidth, maxHeight, pageWidth() - field.x, pageHeight() - field.y));
        const clampedSize = Math.max(minSquare, Math.min(maxSquare, nextSize));
        field.w = clampedSize;
        field.h = clampedSize;
      } else {
        field.w = Math.max(minWidth, Math.min(maxWidth, pageWidth() - field.x, Number((state.interaction.startField.w + dxMm).toFixed(2))));
        field.h = Math.max(minHeight, Math.min(maxHeight, pageHeight() - field.y, Number((state.interaction.startField.h + dyMm).toFixed(2))));
      }
    }

    renderAll();
  }

  function handleTemplatePreview(fileInput, previewImg, sideKey) {
    if (!(fileInput instanceof HTMLInputElement) || !previewImg) return;
    fileInput.addEventListener('change', () => {
      const file = fileInput.files && fileInput.files[0];
      if (!file) return;
      const url = URL.createObjectURL(file);
      state.objectUrls.push(url);
      previewImg.src = url;
      if (sideKey === 'front') {
        state.frontTemplateUrl = url;
      } else {
        state.backTemplateUrl = url;
      }
      renderAll();
    });
  }

  function bindUploadAction(select) {
    const side = select.dataset.bidUploadAction === 'back' ? 'back' : 'front';
    const fileInput = document.getElementById(side === 'front' ? 'frontTemplateFile' : 'backTemplateFile');
    const removeInput = document.getElementById(side === 'front' ? 'removeFrontTemplate' : 'removeBackTemplate');
    const fileWrap = document.querySelector(`[data-bid-upload-file-wrap="${side}"]`);
    if (!(fileInput instanceof HTMLInputElement) || !(removeInput instanceof HTMLInputElement) || !fileWrap) return;

    select.addEventListener('change', () => {
      const action = String(select.value || 'keep');
      fileWrap.hidden = action !== 'upload';
      removeInput.checked = action === 'default';
      if (action !== 'upload') {
        fileInput.value = '';
      }
      const previewImg = side === 'front' ? frontTemplatePreview : backTemplatePreview;
      const defaultUrl = `${String(payload.appBase || '').replace(/\/$/, '')}/Resident-End/Certificates/BarangayID/${side === 'front' ? 'FRONT_EMPTY.png' : 'BACK_EMPTY.png'}`;
      const nextUrl = action === 'default' ? defaultUrl : originalTemplateUrls[side];
      if (side === 'front') {
        state.frontTemplateUrl = nextUrl;
      } else {
        state.backTemplateUrl = nextUrl;
      }
      if (previewImg) previewImg.src = nextUrl;
      renderAll();
    });
  }

  function renderSignaturePreview(url) {
    if (!signaturePreview) return;
    if (!url) {
      signaturePreview.innerHTML = '<div class="text-center text-muted px-3">No signature uploaded yet.</div>';
      return;
    }
    signaturePreview.innerHTML = `<img src="${url}" alt="Punong Barangay signature preview">`;
  }

  sampleInputs.forEach((input) => {
    const key = input.dataset.bidSampleInput;
    if (!key) return;
    input.addEventListener('input', () => {
      state.sampleData[key] = String(input.value || '').trim();
      renderAll();
    });
  });

  sideButtons.forEach((button) => {
    button.addEventListener('click', () => {
      state.activeSide = button.dataset.bidSideBtn === 'back' ? 'back' : 'front';
      ensureSelection();
      renderAll();
    });
  });

  editorViewButtons.forEach((button) => {
    button.addEventListener('click', () => {
      state.editorView = button.dataset.bidEditorViewBtn === 'layout' ? 'layout' : 'editor';
      renderAll();
    });
  });

  addButtons.forEach((button) => {
    button.addEventListener('click', () => addField(String(button.dataset.bidAddType || '').trim()));
  });

  addFieldSelect?.addEventListener('change', () => {
    const type = String(addFieldSelect.value || '').trim();
    if (type) addField(type);
    addFieldSelect.value = '';
  });

  quickColorInput?.addEventListener('input', () => {
    if (state.selectedFieldId) updateFieldProp(state.selectedFieldId, 'color', quickColorInput.value);
  });

  quickFontStyleSelect?.addEventListener('change', () => {
    if (state.selectedFieldId) {
      updateFieldProp(state.selectedFieldId, 'fontStyle', quickFontStyleSelect.value);
    }
  });

  quickAlignmentButtons.forEach((button) => {
    button.addEventListener('click', () => {
      if (state.selectedFieldId && !button.disabled) {
        updateFieldProp(state.selectedFieldId, 'align', button.dataset.bidQuickAlign || 'left');
      }
    });
  });

  quickUppercaseInput?.addEventListener('change', () => {
    if (state.selectedFieldId) updateFieldProp(state.selectedFieldId, 'uppercase', quickUppercaseInput.checked);
  });

  quickMultilineInput?.addEventListener('change', () => {
    if (!state.selectedFieldId) return;
    const field = getFieldById(state.selectedFieldId);
    if (!field || field.type !== 'text') return;
    field.multiline = quickMultilineInput.checked;
    field.maxLines = quickMultilineInput.checked
      ? Math.max(2, Math.min(3, Number(quickMaxLinesInput?.value || field.maxLines || 2)))
      : 1;
    renderAll();
  });

  quickMaxLinesInput?.addEventListener('change', () => {
    if (!state.selectedFieldId || quickMaxLinesInput.disabled) return;
    const field = getFieldById(state.selectedFieldId);
    if (!field || field.type !== 'text') return;
    field.maxLines = Math.max(1, Math.min(3, Number.parseInt(quickMaxLinesInput.value || '1', 10) || 1));
    field.multiline = field.maxLines > 1;
    renderAll();
  });

  quickCornerRadiusInput?.addEventListener('change', () => {
    if (state.selectedFieldId && !quickCornerRadiusInput.disabled) {
      updateFieldProp(state.selectedFieldId, 'cornerRadius', quickCornerRadiusInput.value);
    }
  });

  deleteSelectedFieldBtn?.addEventListener('click', deleteSelectedField);

  restoreDefaultsBtn?.addEventListener('click', () => {
    state.layout = window.BarangayIdDigital.normalizeLayoutConfig(defaultLayout);
    state.selectedFieldId = '';
    renderAll();
  });

  handleTemplatePreview(document.getElementById('frontTemplateFile'), frontTemplatePreview, 'front');
  handleTemplatePreview(document.getElementById('backTemplateFile'), backTemplatePreview, 'back');
  uploadActionSelects.forEach(bindUploadAction);

  signatureFileInput?.addEventListener('change', () => {
    const file = signatureFileInput.files && signatureFileInput.files[0];
    if (!file) return;
    const url = URL.createObjectURL(file);
    state.objectUrls.push(url);
    state.signatory.signatureUrl = url;
    removeSignatureCheckbox.checked = false;
    renderSignaturePreview(url);
    renderAll();
  });

  removeSignatureCheckbox?.addEventListener('change', () => {
    renderAll();
  });

  processStepButtons.forEach((button) => {
    button.addEventListener('click', () => setProcessStep(button.dataset.bidStep));
  });
  processShortcutButtons.forEach((button) => {
    button.addEventListener('click', () => setProcessStep(button.dataset.bidGoStep));
  });
  previousStepBtn?.addEventListener('click', () => {
    setProcessStep((Number.parseInt(form.dataset.bidActiveStep || '1', 10) || 1) - 1);
  });
  nextStepBtn?.addEventListener('click', () => {
    setProcessStep((Number.parseInt(form.dataset.bidActiveStep || '1', 10) || 1) + 1);
  });

  canvas?.addEventListener('pointermove', handlePointerMove);
  canvas?.addEventListener('pointerup', endPointerInteraction);
  canvas?.addEventListener('pointercancel', endPointerInteraction);
  canvas?.addEventListener('pointerleave', (event) => {
    if (state.interaction && !(event.buttons & 1)) {
      endPointerInteraction();
    }
  });

  window.addEventListener('beforeunload', cleanupObjectUrls);
  form?.addEventListener('submit', (event) => {
    const activeStep = Number.parseInt(form.dataset.bidActiveStep || '1', 10) || 1;
    if (activeStep < 3) {
      event.preventDefault();
      setProcessStep(activeStep + 1);
      return;
    }
    syncHiddenFields();
  });

  renderSignaturePreview(state.signatory.signatureUrl);
  ensureSelection();
  setProcessStep(1, { scroll: false });
})();
