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
    { value: 'validityNotice', label: 'Validity Notice' },
    { value: 'photoUrl', label: 'Resident Photo' },
    { value: 'qrUrl', label: 'Verification QR' },
  ];
  const fieldTemplates = {
    text: { type: 'text', label: 'Text Field', source: 'cardFullName', w: 28, h: 4.8, fontStyle: 'B', fontSize: 6.0, minFontSize: 4.2, uppercase: true, align: 'left', multiline: false, maxLines: 1, color: '#111111' },
    image: { type: 'image', label: 'Image Field', source: 'photoUrl', w: 16, h: 16, fit: 'cover' },
    qr: { type: 'qr', label: 'QR Field', source: 'qrUrl', w: 16, h: 16, fit: 'fill' },
    signatory: { type: 'signatory', label: 'Signatory Block', w: 28, h: 12 },
    cover: { type: 'cover', label: 'Cover Block', w: 18, h: 8, backgroundColor: '#ffffff' },
  };

  const state = {
    activeSide: 'front',
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
      back_validity_notice: 'Validity Note',
      back_signatory: 'Punong Barangay Signatory',
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
        validUntil: 'Value: Valid Until',
        validityNotice: 'Value: Notice'
      };
      if (valueBySource[source]) {
        return valueBySource[source];
      }
    }
    const compactById = {
      front_valid_until_value: 'Value: Valid Until',
      front_card_number_value: 'Value: Card No.',
      back_card_number_value: 'Value: Card No.',
      back_signatory: 'Signatory',
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
    if (field.type === 'cover') {
      return dimension === 'h' ? 6 : 10;
    }
    if (field.type === 'text') {
      const source = String(field.source || '').trim();
      if (source === 'cardFullAddress' || source === 'cardBirthplace' || source === 'validityNotice') {
        return dimension === 'h' ? 6 : 20;
      }
      if (source === 'cardFullName') {
        return dimension === 'h' ? 6 : 18;
      }
      return dimension === 'h' ? 5 : 12;
    }

    return dimension === 'h' ? 4.5 : 12;
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

  function renderCanvas() {
    ensureSelection();
    canvas.style.backgroundImage = `url("${state.activeSide === 'front' ? state.frontTemplateUrl : state.backTemplateUrl}")`;
    canvas.innerHTML = '';

    activeFields().forEach((field) => {
      const fieldEl = document.createElement('button');
      fieldEl.type = 'button';
      fieldEl.className = `bid-editor-field${field.id === state.selectedFieldId ? ' is-selected' : ''}`;
      fieldEl.dataset.fieldId = field.id;
      fieldEl.style.left = mmToPctX(field.x);
      fieldEl.style.top = mmToPctY(field.y);
      fieldEl.style.width = mmToPctX(field.w);
      fieldEl.style.height = mmToPctY(field.h);
      fieldEl.style.zIndex = String(field.z || 2);
      const displayName = compactFieldName(field);
      const purpose = fieldPurposeText(field);
      fieldEl.innerHTML = `
        <span class="bid-editor-field__sample${canvasAlignmentClass(field)}">${escapeHtml(displayName).replace(/\n/g, '<br>')}</span>
        ${purpose ? `<span class="visually-hidden">${escapeHtml(purpose)}</span>` : ''}
        <span class="bid-editor-field__resize" data-bid-resize="1"></span>
      `;

      fieldEl.addEventListener('click', () => {
        state.selectedFieldId = field.id;
        renderAll();
      });

      fieldEl.addEventListener('pointerdown', (event) => {
        const isResize = event.target instanceof HTMLElement && event.target.dataset.bidResize === '1';
        beginPointerInteraction(event, field.id, isResize ? 'resize' : 'move');
      });

      canvas.appendChild(fieldEl);
    });
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
      step = '0.1',
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
      inputMarkup({ label: 'Width (mm)', prop: 'w', type: 'number', value: field.w, min: String(minWidth), max: String(pageWidth()) }),
      inputMarkup({ label: 'Height (mm)', prop: 'h', type: 'number', value: field.h, min: String(minHeight), max: String(pageHeight()) }),
      inputMarkup({ label: 'Layer Order', prop: 'z', type: 'number', value: field.z, min: '1', max: '20', step: '1' }),
    ];

    if (field.type === 'text') {
      blocks.push(inputMarkup({ label: 'Font Size', prop: 'fontSize', type: 'number', value: field.fontSize, min: '2.8', max: '20' }));
      blocks.push(inputMarkup({ label: 'Min Font Size', prop: 'minFontSize', type: 'number', value: field.minFontSize, min: '2.4', max: '18' }));
      blocks.push(inputMarkup({
        label: 'Alignment',
        prop: 'align',
        type: 'select',
        options: [
          selectMarkup(field.align, 'left', 'Left'),
          selectMarkup(field.align, 'center', 'Center'),
          selectMarkup(field.align, 'right', 'Right')
        ].join('')
      }));
      blocks.push(inputMarkup({
        label: 'Font Style',
        prop: 'fontStyle',
        type: 'select',
        options: [
          selectMarkup(field.fontStyle, 'B', 'Bold'),
          selectMarkup(field.fontStyle, 'I', 'Italic'),
          selectMarkup(field.fontStyle, 'BI', 'Bold + Italic'),
          selectMarkup(field.fontStyle, '', 'Regular')
        ].join('')
      }));
      blocks.push(inputMarkup({ label: 'Text Color', prop: 'color', type: 'color', value: field.color, step: '1' }));
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
      blocks.push(inputMarkup({ label: 'Max Lines', prop: 'maxLines', type: 'number', value: field.maxLines, min: '1', max: '5', step: '1' }));
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
      helper: 'This preview reflects the current uploaded templates, field layout, sample values, and signatory block.',
      frontLabel: 'Front Card',
      backLabel: 'Back Card'
    });
  }

  function renderSideButtons() {
    sideButtons.forEach((button) => {
      button.classList.toggle('is-active', button.dataset.bidSideBtn === state.activeSide);
    });
  }

  function renderAll() {
    state.layout = window.BarangayIdDigital.normalizeLayoutConfig(state.layout);
    ensureSelection();
    syncHiddenFields();
    renderSideButtons();
    renderCanvas();
    renderFieldList();
    renderInspector();
    renderSamplePreview();
  }

  function updateFieldProp(fieldId, prop, rawValue) {
    const field = getFieldById(fieldId);
    if (!field) return;
    const squareLocked = isSquareLockedField(field);
    const minWidth = fieldMinimumSize(field, 'w');
    const minHeight = fieldMinimumSize(field, 'h');

    const numericProps = new Set(['x', 'y', 'w', 'h', 'fontSize', 'minFontSize', 'z', 'maxLines']);
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

    if (prop === 'w') field.w = Math.max(minWidth, Math.min(pageWidth(), Number(field.w || minWidth)));
    if (prop === 'h') field.h = Math.max(minHeight, Math.min(pageHeight(), Number(field.h || minHeight)));
    if (squareLocked) {
      const minSquare = Math.max(minWidth, minHeight);
      const maxSquare = Math.max(minSquare, Math.min(pageWidth() - Number(field.x || 0), pageHeight() - Number(field.y || 0)));
      const squareSize = Math.max(minSquare, Math.min(maxSquare, Number(field.w || field.h || minSquare)));
      field.w = squareSize;
      field.h = squareSize;
    }
    if (prop === 'x') field.x = Math.max(0, Math.min(pageWidth() - field.w, Number(field.x || 0)));
    if (prop === 'y') field.y = Math.max(0, Math.min(pageHeight() - field.h, Number(field.y || 0)));
    if (prop === 'fontSize') field.fontSize = Math.max(2.8, Math.min(20, Number(field.fontSize || 2.8)));
    if (prop === 'minFontSize') field.minFontSize = Math.max(2.4, Math.min(Number(field.fontSize || 18), Number(field.minFontSize || 2.4)));
    if (prop === 'maxLines') field.maxLines = Math.max(1, Math.min(5, Number(field.maxLines || 1)));
    if (prop === 'z') field.z = Math.max(1, Math.min(20, Number(field.z || 1)));
    renderAll();
  }

  function addField(type) {
    const template = fieldTemplates[type];
    if (!template) return;
    const existingIds = new Set(state.layout.fields.map((field) => String(field.id || '')));
    let suffix = 1;
    let nextId = `${type}_${suffix}`;
    while (existingIds.has(nextId)) {
      suffix += 1;
      nextId = `${type}_${suffix}`;
    }
    const field = {
      ...template,
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
        const maxSquare = Math.max(minSquare, Math.min(pageWidth() - field.x, pageHeight() - field.y));
        const clampedSize = Math.max(minSquare, Math.min(maxSquare, nextSize));
        field.w = clampedSize;
        field.h = clampedSize;
      } else {
        field.w = Math.max(minWidth, Math.min(pageWidth() - field.x, Number((state.interaction.startField.w + dxMm).toFixed(2))));
        field.h = Math.max(minHeight, Math.min(pageHeight() - field.y, Number((state.interaction.startField.h + dyMm).toFixed(2))));
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

  addButtons.forEach((button) => {
    button.addEventListener('click', () => addField(String(button.dataset.bidAddType || '').trim()));
  });

  deleteSelectedFieldBtn?.addEventListener('click', deleteSelectedField);

  restoreDefaultsBtn?.addEventListener('click', () => {
    state.layout = window.BarangayIdDigital.normalizeLayoutConfig(defaultLayout);
    state.selectedFieldId = '';
    renderAll();
  });

  handleTemplatePreview(document.getElementById('frontTemplateFile'), frontTemplatePreview, 'front');
  handleTemplatePreview(document.getElementById('backTemplateFile'), backTemplatePreview, 'back');

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

  canvas?.addEventListener('pointermove', handlePointerMove);
  canvas?.addEventListener('pointerup', endPointerInteraction);
  canvas?.addEventListener('pointercancel', endPointerInteraction);
  canvas?.addEventListener('pointerleave', (event) => {
    if (state.interaction && !(event.buttons & 1)) {
      endPointerInteraction();
    }
  });

  window.addEventListener('beforeunload', cleanupObjectUrls);
  form?.addEventListener('submit', () => {
    syncHiddenFields();
  });

  renderSignaturePreview(state.signatory.signatureUrl);
  ensureSelection();
  renderAll();
})();
