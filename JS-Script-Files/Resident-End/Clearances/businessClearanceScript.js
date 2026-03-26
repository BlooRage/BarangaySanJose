(() => {
  const form = document.querySelector("form.page-form") || document.querySelector("form");
  const submitBtn = form?.querySelector(".submit-btn");
  const uploadLimitBytes = Number(form?.dataset.uploadLimitBytes || (25 * 1024 * 1024));
  const uploadLimitLabel = String(form?.dataset.uploadLimitLabel || "25MB").trim() || "25MB";
  const uploadValidationModalEl = document.getElementById("businessUploadValidationModal");
  const uploadValidationModalTitle = document.getElementById("businessUploadValidationModalTitle");
  const uploadValidationModalMessage = document.getElementById("businessUploadValidationModalMessage");
  const uploadValidationModal = uploadValidationModalEl && window.bootstrap
    ? new window.bootstrap.Modal(uploadValidationModalEl)
    : null;
  const businessRenewalHistoryDataEl = document.getElementById("businessRenewalHistoryData");
  const ownerTypeSelect = document.getElementById("ownerTypeSelect");
  const renterOwnerDetails = document.getElementById("renterOwnerDetails");
  const applicationTypeSelect = document.getElementById("applicationTypeSelect");
  const applicationTypeDependentFields = document.getElementById("applicationTypeDependentFields");
  const renewalBusinessHistoryRow = document.getElementById("renewalBusinessHistoryRow");
  const renewalBusinessHistorySelect = document.getElementById("renewalBusinessHistorySelect");
  const documentUploadSection = document.getElementById("documentUploadSection");
  const documentUploadNew = document.getElementById("documentUploadNew");
  const documentUploadRenewal = document.getElementById("documentUploadRenewal");
  const businessName = document.getElementById("businessName");
  const businessSameAddress = document.getElementById("businessSameAddress");
  const businessAddressSystemRow = document.getElementById("businessAddressSystemRow");
  const businessAddressSystem = document.getElementById("businessAddressSystem");
  const businessFullAddressWrapper = document.getElementById("businessFullAddressWrapper");
  const ownerFullAddress = document.getElementById("owner_full_address");
  const businessRegType = document.getElementById("businessRegType");
  const proofAddressType = document.getElementById("proofAddressType");
  const proofAddressNumberRow = document.getElementById("proofAddressNumberRow");
  const proofAddressNumber = document.getElementById("proofAddressNumber");
  const proofAddressNumberError = document.getElementById("proofAddressNumberError");
  const renewalBusinessRegType = document.getElementById("renewalBusinessRegType");
  const renewalProofAddressType = document.getElementById("renewalProofAddressType");
  const renewalProofAddressNumberRow = document.getElementById("renewalProofAddressNumberRow");
  const renewalProofAddressNumber = document.getElementById("renewalProofAddressNumber");
  const renewalProofAddressNumberError = document.getElementById("renewalProofAddressNumberError");
  const businessContactNumber = document.getElementById("business_contact_number");
  const businessContactNumberError = document.getElementById("business_contact_number_error");
  const businessRequestPurpose = document.getElementById("businessRequestPurpose");
  const businessHouseSystemWrapper = document.getElementById("businessHouseSystemWrapper");
  const businessSubdivisionHouseRow = document.getElementById("businessSubdivisionHouseRow");
  const businessBlockSystemWrapper = document.getElementById("businessBlockSystemWrapper");
  const businessUnitNumber = document.getElementById("business_unit_number");
  const businessStreetNumber = document.getElementById("business_street_number");
  const businessStreetName = document.getElementById("business_street_name");
  const businessSubdivision = document.getElementById("business_subdivision");
  const businessLotNumber = document.getElementById("business_lot_number");
  const businessBlockNumber = document.getElementById("business_block_number");
  const businessPhaseNumber = document.getElementById("business_phase_number");
  const businessSubdivisionBlock = document.getElementById("business_subdivision_block");
  const businessBarangay = document.getElementById("business_barangay");
  const businessCity = document.getElementById("business_city");
  const businessProvince = document.getElementById("business_province");
  const businessFullAddress = document.getElementById("businessFullAddress");
  const initialOperationDate = document.getElementById("initialOperationDate");
  const businessType = document.getElementById("business_type");
  const renterOwnerLastName = form?.querySelector("input[name='ro_ln']");
  const renterOwnerFirstName = form?.querySelector("input[name='ro_fn']");
  const renterOwnerMiddleName = form?.querySelector("input[name='ro_mn']");
  const renterOwnerSuffix = form?.querySelector("select[name='ro_sfx']");
  const renterOwnerRequired = renterOwnerDetails
    ? Array.from(renterOwnerDetails.querySelectorAll("input[name='ro_ln'], input[name='ro_fn']"))
    : [];

  if (!form || !submitBtn) return;

  const formatFileSize = (bytes) => {
    if (!Number.isFinite(bytes) || bytes <= 0) return "0 MB";
    const megabytes = bytes / (1024 * 1024);
    const digits = megabytes >= 10 ? 1 : 2;
    return `${megabytes.toFixed(digits).replace(/\.0+$/, "").replace(/(\.\d*[1-9])0$/, "$1")} MB`;
  };

  const showUploadValidationModal = (message, title = "Upload Error") => {
    const cleanMessage = String(message || "").trim() || "Please choose a valid file.";
    const cleanTitle = String(title || "").trim() || "Upload Error";
    if (uploadValidationModalTitle) {
      uploadValidationModalTitle.textContent = cleanTitle;
    }
    if (uploadValidationModalMessage) {
      uploadValidationModalMessage.textContent = cleanMessage;
    }
    if (uploadValidationModal) {
      uploadValidationModal.show();
      return;
    }
    window.alert(cleanMessage);
  };

  const setRequired = (el, required) => {
    if (!el) return;
    if (required) {
      el.setAttribute("required", "required");
    } else {
      el.removeAttribute("required");
    }
  };

  const clearInput = (el) => {
    if (!el) return;
    if (el.type === "checkbox" || el.type === "radio") {
      el.checked = false;
    } else {
      el.value = "";
    }
    el.setCustomValidity("");
    if (typeof el.__resetDropzoneMeta === "function") {
      el.__resetDropzoneMeta();
    }
  };

  const setWrapperState = (wrapper, enabled) => {
    if (!wrapper) return;
    wrapper.classList.toggle("d-none", !enabled);
    wrapper.querySelectorAll("input, select").forEach((el) => {
      el.disabled = !enabled;
      if (!enabled) {
        clearInput(el);
      }
    });
  };

  const normalizeValue = (value) => (value || "").replace(/[\s-]/g, "").toUpperCase();
  const proofAddressRegexMap = {
    lease: /^.+$/,
    tct: /^(?:TCT|T)?\d{5,10}$/,
    tax_declaration: /^(?:TD)?\d{3,15}$/
  };
  const contactNumberRegex = /^09\d{9}$/;
  const businessRenewalHistory = (() => {
    if (!businessRenewalHistoryDataEl) return [];
    try {
      const parsed = JSON.parse(businessRenewalHistoryDataEl.textContent || "[]");
      return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
      return [];
    }
  })();
  const businessRenewalHistoryByRequestId = new Map(
    businessRenewalHistory
      .filter((record) => record && typeof record === "object" && String(record.request_id || "").trim() !== "")
      .map((record) => [String(record.request_id).trim(), record])
  );

  const inferBusinessAddressSystem = (record) => {
    const explicitSystem = String(record?.business_address_system || "").trim();
    if (explicitSystem === "house" || explicitSystem === "lot_block") {
      return explicitSystem;
    }
    if (
      String(record?.business_lot_number || "").trim() !== "" ||
      String(record?.business_block_number || "").trim() !== "" ||
      String(record?.business_phase_number || "").trim() !== ""
    ) {
      return "lot_block";
    }
    if (
      String(record?.business_unit_number || "").trim() !== "" ||
      String(record?.business_street_number || "").trim() !== "" ||
      String(record?.business_street_name || "").trim() !== "" ||
      String(record?.business_subdivision || "").trim() !== ""
    ) {
      return "house";
    }
    return "";
  };

  const normalizeOwnerType = (value) => {
    const normalizedValue = String(value || "").trim().toLowerCase();
    if (normalizedValue === "owner") return "Owner";
    if (normalizedValue === "renter") return "Renter";
    return "";
  };

  const normalizeDateValue = (value) => {
    const rawValue = String(value || "").trim();
    if (rawValue === "") return "";
    const leadingDateMatch = rawValue.match(/^(\d{4}-\d{2}-\d{2})/);
    if (leadingDateMatch) {
      return leadingDateMatch[1];
    }
    const parsedDate = new Date(rawValue);
    if (Number.isNaN(parsedDate.getTime())) {
      return "";
    }
    const year = parsedDate.getFullYear();
    const month = String(parsedDate.getMonth() + 1).padStart(2, "0");
    const day = String(parsedDate.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
  };

  const createAttachmentGroup = (rowKeys, inputIds, addBtnId) => ({
    rows: rowKeys
      .map((key) => document.querySelector(`[data-business-attachment-row="${key}"]`))
      .filter(Boolean),
    inputs: inputIds.map((id) => document.getElementById(id)).filter(Boolean),
    addBtn: document.getElementById(addBtnId)
  });

  const businessRegGroup = createAttachmentGroup(
    ["business-reg-1", "business-reg-2", "business-reg-3"],
    ["businessRegFile1", "businessRegFile2", "businessRegFile3"],
    "addBusinessRegAttachmentBtn"
  );
  const proofAddressGroup = createAttachmentGroup(
    ["proof-address-1", "proof-address-2", "proof-address-3"],
    ["proofAddressFile1", "proofAddressFile2", "proofAddressFile3"],
    "addProofAddressAttachmentBtn"
  );
  const businessPhotoGroup = createAttachmentGroup(
    ["business-photo-1", "business-photo-2", "business-photo-3"],
    ["businessPhotoFile1", "businessPhotoFile2", "businessPhotoFile3"],
    "addBusinessPhotoAttachmentBtn"
  );
  const renewalBusinessRegGroup = createAttachmentGroup(
    ["renewal-business-reg-1", "renewal-business-reg-2", "renewal-business-reg-3"],
    ["renewalBusinessRegFile1", "renewalBusinessRegFile2", "renewalBusinessRegFile3"],
    "addRenewalBusinessRegAttachmentBtn"
  );
  const renewalProofAddressGroup = createAttachmentGroup(
    ["renewal-proof-address-1", "renewal-proof-address-2", "renewal-proof-address-3"],
    ["renewalProofAddressFile1", "renewalProofAddressFile2", "renewalProofAddressFile3"],
    "addRenewalProofAddressAttachmentBtn"
  );
  const renewalBusinessPhotoGroup = createAttachmentGroup(
    ["renewal-business-photo-1", "renewal-business-photo-2", "renewal-business-photo-3"],
    ["renewalBusinessPhotoFile1", "renewalBusinessPhotoFile2", "renewalBusinessPhotoFile3"],
    "addRenewalBusinessPhotoAttachmentBtn"
  );

  const attachmentGroups = [
    businessRegGroup,
    proofAddressGroup,
    businessPhotoGroup,
    renewalBusinessRegGroup,
    renewalProofAddressGroup,
    renewalBusinessPhotoGroup
  ];

  const resetAttachmentGroup = (group) => {
    group.rows.forEach((row, index) => {
      row.classList.toggle("d-none", index > 0);
    });
    group.inputs.forEach((input, index) => {
      clearInput(input);
      input.disabled = index > 0;
      setRequired(input, false);
    });
  };

  const syncAttachmentGroup = (group, enabled) => {
    if (!enabled) {
      resetAttachmentGroup(group);
    }

    group.rows.forEach((row, index) => {
      const input = group.inputs[index];
      const isVisible = !row.classList.contains("d-none");
      if (!enabled && index > 0) {
        row.classList.add("d-none");
      }
      if (input) {
        input.disabled = !enabled || !isVisible;
        setRequired(input, enabled && index === 0);
        if ((!enabled || !isVisible) && input.value !== "") {
          clearInput(input);
        } else if (typeof input.__syncDropzoneMeta === "function") {
          input.__syncDropzoneMeta();
        }
      }
    });

    if (group.addBtn) {
      const visibleCount = group.rows.filter((row) => !row.classList.contains("d-none")).length;
      group.addBtn.classList.toggle("d-none", !enabled);
      group.addBtn.disabled = !enabled || visibleCount >= group.rows.length;
    }
  };

  const bindAttachmentButton = (group) => {
    if (!group.addBtn) return;
    group.addBtn.addEventListener("click", () => {
      const nextRow = group.rows.find((row) => row.classList.contains("d-none"));
      if (!nextRow) return;
      nextRow.classList.remove("d-none");
      const nextIndex = group.rows.indexOf(nextRow);
      const nextInput = group.inputs[nextIndex];
      if (nextInput) {
        nextInput.disabled = false;
      }
      updateState();
    });
  };

  const updateNumberRow = (selectEl, rowEl, inputEl) => {
    if (!selectEl || !rowEl || !inputEl) return;
    const hasValue = selectEl.value !== "";
    const needsNumber = hasValue && selectEl.value !== "lease";
    rowEl.classList.toggle("d-none", !needsNumber);
    setRequired(inputEl, needsNumber);
    if (!needsNumber) {
      inputEl.value = "";
      inputEl.setCustomValidity("");
    } else {
      inputEl.setCustomValidity("");
    }
    inputEl.dataset.regexKey = selectEl.value;
  };

  const validateNumberInput = (inputEl, regexMap, errorEl) => {
    if (!inputEl) return;
    if (!inputEl.required) {
      inputEl.setCustomValidity("");
      if (errorEl) errorEl.classList.add("d-none");
      return;
    }

    const rawValue = inputEl.value.trim();
    if (rawValue === "") {
      inputEl.setCustomValidity("");
      if (errorEl) errorEl.classList.add("d-none");
      return;
    }

    const regex = regexMap[inputEl.dataset.regexKey || ""];
    if (!regex) {
      inputEl.setCustomValidity("Please select a valid option.");
      if (errorEl) errorEl.classList.remove("d-none");
      return;
    }

    const isInvalid = !regex.test(normalizeValue(rawValue));
    inputEl.setCustomValidity(isInvalid ? "Please enter a valid number format." : "");
    if (errorEl) {
      errorEl.classList.toggle("d-none", !isInvalid);
    }
  };

  const buildBusinessFullAddress = () => {
    if (businessSameAddress?.checked) {
      return (ownerFullAddress?.value || "").trim();
    }

    const system = (businessAddressSystem?.value || "").trim();
    if (system === "lot_block") {
      const lotNumber = (businessLotNumber?.value || "").trim();
      const blockNumber = (businessBlockNumber?.value || "").trim();
      const phaseNumber = (businessPhaseNumber?.value || "").trim();
      if (lotNumber === "" && blockNumber === "" && phaseNumber === "") {
        return "";
      }

      return [
        lotNumber ? `Lot ${lotNumber}` : "",
        blockNumber ? `Blk ${blockNumber}` : "",
        phaseNumber ? `Phase ${phaseNumber}` : "",
        (businessSubdivisionBlock?.value || "").trim(),
        (businessBarangay?.value || "").trim(),
        (businessCity?.value || "").trim(),
        (businessProvince?.value || "").trim()
      ]
        .filter(Boolean)
        .join(", ");
    }

    const streetLine = [
      (businessStreetNumber?.value || "").trim(),
      (businessStreetName?.value || "").trim()
    ]
      .filter(Boolean)
      .join(" ")
      .trim();

    if (streetLine === "") {
      return "";
    }

    return [
      businessUnitNumber?.value ? `Unit ${businessUnitNumber.value.trim()}` : "",
      streetLine,
      (businessSubdivision?.value || "").trim(),
      (businessBarangay?.value || "").trim(),
      (businessCity?.value || "").trim(),
      (businessProvince?.value || "").trim()
    ]
      .filter(Boolean)
      .join(", ");
  };

  const bindDropzone = (inputEl) => {
    if (!inputEl) return;
    const zone = document.querySelector(`.upload-dropzone[data-upload-input="${inputEl.id}"]`);
    const meta = document.getElementById(`${inputEl.id}Meta`);
    if (!zone) return;

    const defaultMetaText = meta ? meta.textContent : "";
    const acceptTokens = String(inputEl.getAttribute("accept") || "")
      .split(",")
      .map((token) => token.trim().toLowerCase())
      .filter(Boolean);

    const allowedMessage = () => {
      const base = String(defaultMetaText || "Please upload a supported file.")
        .replace(/\s*Saved as PDF\.\s*$/i, ".")
        .trim();
      return base || "Please upload a supported file.";
    };

    const fileMatchesAccept = (file) => {
      if (!file || !acceptTokens.length) return true;
      const fileName = String(file.name || "");
      const fileExt = fileName.includes(".")
        ? `.${fileName.split(".").pop().toLowerCase()}`
        : "";
      const fileType = String(file.type || "").toLowerCase();

      return acceptTokens.some((token) => {
        if (token.startsWith(".")) {
          return token === fileExt;
        }
        if (token.endsWith("/*")) {
          return fileType.startsWith(token.slice(0, -1));
        }
        return fileType === token;
      });
    };

    const setMeta = () => {
      if (!meta) return;
      const file = inputEl.files && inputEl.files.length ? inputEl.files[0] : null;
      meta.textContent = file ? file.name : defaultMetaText;
    };

    const resetSelection = () => {
      inputEl.value = "";
      setMeta();
    };

    inputEl.__resetDropzoneMeta = setMeta;
    inputEl.__syncDropzoneMeta = setMeta;

    const validateSelectedFile = () => {
      const file = inputEl.files && inputEl.files.length ? inputEl.files[0] : null;
      if (!file) {
        return true;
      }
      if (!fileMatchesAccept(file)) {
        resetSelection();
        showUploadValidationModal(allowedMessage(), "Invalid File Type");
        return false;
      }
      if (Number.isFinite(uploadLimitBytes) && uploadLimitBytes > 0 && Number(file.size || 0) > uploadLimitBytes) {
        resetSelection();
        showUploadValidationModal(
          `${file.name} is ${formatFileSize(Number(file.size || 0))}. The maximum allowed size is ${uploadLimitLabel}. Please choose a smaller file.`,
          "File Too Large"
        );
        return false;
      }
      return true;
    };

    inputEl.addEventListener("change", () => {
      if (!validateSelectedFile()) {
        updateState();
        return;
      }
      setMeta();
      updateState();
    });

    ["dragenter", "dragover"].forEach((eventName) => {
      zone.addEventListener(eventName, (event) => {
        event.preventDefault();
        zone.classList.add("is-dragging");
      });
    });

    ["dragleave", "dragend", "drop"].forEach((eventName) => {
      zone.addEventListener(eventName, (event) => {
        event.preventDefault();
        zone.classList.remove("is-dragging");
      });
    });

    zone.addEventListener("drop", (event) => {
      if (inputEl.disabled) return;
      const droppedFiles = event.dataTransfer ? event.dataTransfer.files : null;
      if (!droppedFiles || !droppedFiles.length) return;
      const dt = new DataTransfer();
      dt.items.add(droppedFiles[0]);
      inputEl.files = dt.files;
      if (!validateSelectedFile()) {
        updateState();
        return;
      }
      inputEl.dispatchEvent(new Event("change", { bubbles: true }));
    });

    setMeta();
  };

  const clearStaticFields = (elements) => {
    elements.forEach((el) => {
      if (!el) return;
      el.value = "";
      el.setCustomValidity("");
    });
  };

  const setFieldValue = (el, value) => {
    if (!el) return;
    el.value = String(value ?? "");
    el.setCustomValidity("");
  };

  const applyRenewalHistoryRecord = (record) => {
    if (!record || typeof record !== "object") return;

    const isSameAddress = record.business_same_address === true;
    const businessAddressSystemValue = isSameAddress ? "" : inferBusinessAddressSystem(record);

    setFieldValue(businessName, record.business_name);
    setFieldValue(businessType, record.business_type);
    setFieldValue(initialOperationDate, normalizeDateValue(record.initial_operation_date));
    setFieldValue(businessContactNumber, record.business_contact_number);
    setFieldValue(ownerTypeSelect, normalizeOwnerType(record.owner_type));

    if (businessSameAddress) {
      businessSameAddress.checked = isSameAddress;
    }

    setFieldValue(businessAddressSystem, businessAddressSystemValue);
    setFieldValue(businessUnitNumber, record.business_unit_number);
    setFieldValue(businessStreetNumber, record.business_street_number);
    setFieldValue(businessStreetName, record.business_street_name);
    setFieldValue(businessSubdivision, record.business_subdivision);
    setFieldValue(businessLotNumber, record.business_lot_number);
    setFieldValue(businessBlockNumber, record.business_block_number);
    setFieldValue(businessPhaseNumber, record.business_phase_number);
    setFieldValue(businessSubdivisionBlock, record.business_subdivision_block);
    setFieldValue(businessBarangay, record.business_barangay || "San Jose");
    setFieldValue(businessCity, record.business_city || "Rodriguez (Montalban)");
    setFieldValue(businessProvince, record.business_province || "Rizal");

    setFieldValue(renewalBusinessRegType, record.business_reg_type);
    setFieldValue(renewalProofAddressType, record.proof_address_type);
    setFieldValue(renewalProofAddressNumber, record.proof_address_number);

    setFieldValue(renterOwnerLastName, record.renter_owner_last_name);
    setFieldValue(renterOwnerFirstName, record.renter_owner_first_name);
    setFieldValue(renterOwnerMiddleName, record.renter_owner_middle_name);
    setFieldValue(renterOwnerSuffix, record.renter_owner_suffix);

    updateState();

    if (initialOperationDate) {
      initialOperationDate.dispatchEvent(new Event("change", { bubbles: true }));
    }
  };

  function updateState() {
    const applicationType = String(applicationTypeSelect?.value || "").trim();
    const hasApplicationType = applicationType !== "";
    const isNewApp = applicationType === "New";
    const isRenewal = applicationType === "Renewal";
    const isSameAddress = businessSameAddress?.checked === true;
    const businessSystem = (businessAddressSystem?.value || "").trim();

    if (applicationTypeDependentFields) {
      applicationTypeDependentFields.classList.toggle("d-none", !hasApplicationType);
      applicationTypeDependentFields.disabled = !hasApplicationType;
    }

    if (businessRequestPurpose) {
      businessRequestPurpose.value = hasApplicationType
        ? (isRenewal ? "Business Permit - Renewal" : "Business Permit - New Application")
        : "";
    }

    renewalBusinessHistoryRow?.classList.toggle("d-none", !isRenewal);
    if (renewalBusinessHistorySelect) {
      renewalBusinessHistorySelect.disabled = !isRenewal || businessRenewalHistoryByRequestId.size === 0;
    }

    documentUploadSection?.classList.toggle("d-none", !(isNewApp || isRenewal));
    documentUploadNew?.classList.toggle("d-none", !isNewApp);
    documentUploadRenewal?.classList.toggle("d-none", !isRenewal);

    businessAddressSystemRow?.classList.toggle("d-none", isSameAddress);
    businessFullAddressWrapper?.classList.toggle("d-none", !isSameAddress);
    setRequired(businessAddressSystem, !isSameAddress);

    setWrapperState(businessHouseSystemWrapper, !isSameAddress && businessSystem === "house");
    setWrapperState(businessSubdivisionHouseRow, !isSameAddress && businessSystem === "house");
    setWrapperState(businessBlockSystemWrapper, !isSameAddress && businessSystem === "lot_block");

    setRequired(businessStreetNumber, !isSameAddress && businessSystem === "house");
    setRequired(businessStreetName, !isSameAddress && businessSystem === "house");
    setRequired(businessLotNumber, !isSameAddress && businessSystem === "lot_block");
    setRequired(businessBlockNumber, !isSameAddress && businessSystem === "lot_block");
    setRequired(businessPhaseNumber, !isSameAddress && businessSystem === "lot_block");

    if (!isNewApp) {
      clearStaticFields([proofAddressNumber]);
      if (proofAddressNumberError) proofAddressNumberError.classList.add("d-none");
      if (proofAddressType) proofAddressType.value = "";
      if (businessRegType) businessRegType.value = "";
    }

    if (!isRenewal) {
      clearStaticFields([renewalProofAddressNumber]);
      if (renewalProofAddressNumberError) renewalProofAddressNumberError.classList.add("d-none");
      if (renewalProofAddressType) renewalProofAddressType.value = "";
      if (renewalBusinessRegType) renewalBusinessRegType.value = "";
    }

    setRequired(businessRegType, isNewApp);
    setRequired(proofAddressType, isNewApp);
    setRequired(renewalBusinessRegType, isRenewal);
    setRequired(renewalProofAddressType, isRenewal);

    syncAttachmentGroup(businessRegGroup, isNewApp);
    syncAttachmentGroup(proofAddressGroup, isNewApp);
    syncAttachmentGroup(businessPhotoGroup, isNewApp);
    syncAttachmentGroup(renewalBusinessRegGroup, isRenewal);
    syncAttachmentGroup(renewalProofAddressGroup, isRenewal);
    syncAttachmentGroup(renewalBusinessPhotoGroup, isRenewal);

    if (businessFullAddress) {
      businessFullAddress.value = buildBusinessFullAddress();
    }

    updateNumberRow(proofAddressType, proofAddressNumberRow, proofAddressNumber);
    updateNumberRow(renewalProofAddressType, renewalProofAddressNumberRow, renewalProofAddressNumber);
    validateNumberInput(proofAddressNumber, proofAddressRegexMap, proofAddressNumberError);
    validateNumberInput(renewalProofAddressNumber, proofAddressRegexMap, renewalProofAddressNumberError);

    if (businessContactNumber) {
      const rawValue = businessContactNumber.value.trim();
      const isInvalid = rawValue !== "" && !contactNumberRegex.test(rawValue);
      businessContactNumber.setCustomValidity(isInvalid ? "Invalid contact number" : "");
      if (businessContactNumberError) {
        businessContactNumberError.classList.toggle("d-none", !isInvalid);
      }
    }

    if (ownerTypeSelect && renterOwnerDetails) {
      const isRenter = ownerTypeSelect.value === "Renter";
      renterOwnerDetails.classList.toggle("d-none", !isRenter);
      renterOwnerRequired.forEach((input) => {
        setRequired(input, isRenter);
      });
    }

    submitBtn.disabled = !form.checkValidity();
  }

  attachmentGroups.forEach((group) => {
    bindAttachmentButton(group);
    group.inputs.forEach(bindDropzone);
  });

  form.addEventListener("input", updateState);
  form.addEventListener("change", updateState);
  businessSameAddress?.addEventListener("change", updateState);
  businessAddressSystem?.addEventListener("change", updateState);
  proofAddressType?.addEventListener("change", updateState);
  renewalProofAddressType?.addEventListener("change", updateState);
  proofAddressNumber?.addEventListener("input", updateState);
  renewalProofAddressNumber?.addEventListener("input", updateState);
  businessContactNumber?.addEventListener("input", updateState);
  renewalBusinessHistorySelect?.addEventListener("change", () => {
    const selectedRequestId = String(renewalBusinessHistorySelect.value || "").trim();
    if (selectedRequestId === "") return;
    applyRenewalHistoryRecord(businessRenewalHistoryByRequestId.get(selectedRequestId));
  });

  updateState();
})();
