(() => {
  const form = document.querySelector("form");
  const submitBtn = form?.querySelector(".submit-btn");
  const ownerTypeSelect = document.getElementById("ownerTypeSelect");
  const renterOwnerDetails = document.getElementById("renterOwnerDetails");
  const appNew = document.getElementById("app_new");
  const appRenewal = document.getElementById("app_renewal");
  const documentUploadSection = document.getElementById("documentUploadSection");
  const validIdType = document.getElementById("validIdType");
  const validIdFile = document.getElementById("validIdFile");
  const validIdNumberRow = document.getElementById("validIdNumberRow");
  const validIdNumber = document.getElementById("validIdNumber");
  const validIdNumberError = document.getElementById("validIdNumberError");
  const businessRegType = document.getElementById("businessRegType");
  const businessRegFile = document.getElementById("businessRegFile");
  const proofAddressType = document.getElementById("proofAddressType");
  const proofAddressFile = document.getElementById("proofAddressFile");
  const proofAddressNumberRow = document.getElementById("proofAddressNumberRow");
  const proofAddressNumber = document.getElementById("proofAddressNumber");
  const proofAddressNumberError = document.getElementById("proofAddressNumberError");
  const businessPhotoFile = document.getElementById("businessPhotoFile");
  const businessPhotoDropzone = document.getElementById("businessPhotoDropzone");
  const businessPhotoSelectedFile = document.getElementById("businessPhotoSelectedFile");
  const businessContactNumber = document.getElementById("business_contact_number");
  const businessContactNumberError = document.getElementById("business_contact_number_error");
  const renterOwnerRequired = renterOwnerDetails
    ? Array.from(renterOwnerDetails.querySelectorAll("input[name='ro_ln'], input[name='ro_fn']"))
    : [];
  if (!form || !submitBtn) return;

  const setRequired = (el, required) => {
    if (!el) return;
    el.required = required;
  };

  const normalizeValue = (value) => (value || '').replace(/[\s-]/g, '').toUpperCase();

  const validIdRegexMap = {
    philsys: /^\d{12}$/,
    umid: /^\d{12}$/,
    passport: /^[A-Z]{1,2}\d{7}$/,
    drivers_license: /^\d{10}$/,
    prc: /^\d{7}$/,
    postal: /^\d{12}$/,
    gsis: /^(?:\d{10}|\d{12})$/
  };

  const proofAddressRegexMap = {
    lease: /^.+$/,
    tct: /^(?:TCT|T)?\d{5,10}$/,
    tax_declaration: /^(?:TD)?\d{3,15}$/
  };
  const contactNumberRegex = /^09\d{9}$/;

  const updateNumberRow = (selectEl, rowEl, inputEl) => {
    if (!selectEl || !rowEl || !inputEl) return;
    const hasValue = selectEl.value !== '';
    rowEl.classList.toggle("d-none", !hasValue);
    setRequired(inputEl, hasValue);
    if (!hasValue) {
      inputEl.value = '';
      inputEl.setCustomValidity('');
    } else {
      inputEl.setCustomValidity('');
    }
    inputEl.dataset.regexKey = selectEl.value;
    inputEl.dataset.regexGroup = selectEl.id;
  };

  const validateNumberInput = (inputEl, regexMap, errorEl) => {
    if (!inputEl || !inputEl.required) return;
    const rawValue = inputEl.value.trim();
    if (rawValue === '') {
      inputEl.setCustomValidity('');
      if (errorEl) errorEl.classList.add("d-none");
      return;
    }
    const key = inputEl.dataset.regexKey || '';
    const regex = regexMap[key];
    if (!regex) {
      inputEl.setCustomValidity('Please select a valid option.');
      if (errorEl) errorEl.classList.remove("d-none");
      return;
    }
    const normalized = normalizeValue(rawValue);
    const isInvalid = !regex.test(normalized);
    if (isInvalid) {
      inputEl.setCustomValidity('Please enter a valid number format.');
      if (errorEl) errorEl.classList.remove("d-none");
    } else {
      inputEl.setCustomValidity('');
      if (errorEl) errorEl.classList.add("d-none");
    }
  };

  const renderFile = (inputEl, outputEl) => {
    if (!inputEl || !outputEl) return;
    const names = Array.from(inputEl.files || []).map((file) => file.name);
    outputEl.textContent = names.length ? `Selected: ${names.join(", ")}` : "No file selected";
  };

  const bindDropzone = (dropzone, inputEl, outputEl) => {
    if (!dropzone || !inputEl || !outputEl) return;
    ["dragenter", "dragover"].forEach((eventName) => {
      dropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        dropzone.classList.add("is-dragging");
      });
    });

    ["dragleave", "drop"].forEach((eventName) => {
      dropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        dropzone.classList.remove("is-dragging");
      });
    });

    dropzone.addEventListener("drop", (e) => {
      const dt = e.dataTransfer;
      if (dt && dt.files && dt.files.length) {
        inputEl.files = dt.files;
        renderFile(inputEl, outputEl);
        updateState();
      }
    });
  };

  const updateState = () => {
    const isNewApp = appNew?.checked === true;
    if (documentUploadSection) {
      documentUploadSection.classList.toggle("d-none", !isNewApp);
    }
    setRequired(validIdType, isNewApp);
    setRequired(validIdFile, isNewApp);
    setRequired(businessRegType, isNewApp);
    setRequired(businessRegFile, isNewApp);
    setRequired(proofAddressType, isNewApp);
    setRequired(proofAddressFile, isNewApp);
    setRequired(businessPhotoFile, isNewApp);

    updateNumberRow(validIdType, validIdNumberRow, validIdNumber);
    updateNumberRow(proofAddressType, proofAddressNumberRow, proofAddressNumber);

    if (businessContactNumber) {
      const rawValue = businessContactNumber.value.trim();
      const hasValue = rawValue !== "";
      const isInvalid = hasValue && !contactNumberRegex.test(rawValue);
      businessContactNumber.setCustomValidity(isInvalid ? "Invalid contact number" : "");
      if (businessContactNumberError) {
        businessContactNumberError.classList.toggle("d-none", !isInvalid);
      }
    }

    if (!isNewApp) {
      [validIdType, businessRegType, proofAddressType].forEach((el) => {
        if (el) el.value = '';
      });
      [validIdFile, businessRegFile, proofAddressFile].forEach((el) => {
        if (el) el.value = '';
      });
      if (businessPhotoFile) {
        businessPhotoFile.value = '';
        renderFile(businessPhotoFile, businessPhotoSelectedFile);
      }
      [validIdNumber, proofAddressNumber].forEach((el) => {
        if (el) {
          el.value = '';
          el.setCustomValidity('');
        }
      });
      if (validIdNumberError) validIdNumberError.classList.add("d-none");
      if (proofAddressNumberError) proofAddressNumberError.classList.add("d-none");
      [validIdNumberRow, proofAddressNumberRow].forEach((row) => {
        if (row) row.classList.add("d-none");
      });
    }

    if (ownerTypeSelect && renterOwnerDetails) {
      const isRenterOrOccupant = ownerTypeSelect.value === "Renter" || ownerTypeSelect.value === "Occupant";
      renterOwnerDetails.classList.toggle("d-none", !isRenterOrOccupant);
      renterOwnerRequired.forEach((input) => {
        input.required = isRenterOrOccupant;
      });
    }
    submitBtn.disabled = !form.checkValidity();
  };

  form.addEventListener("input", updateState);
  form.addEventListener("change", updateState);
  validIdType?.addEventListener("change", () => {
    updateNumberRow(validIdType, validIdNumberRow, validIdNumber);
    validateNumberInput(validIdNumber, validIdRegexMap, validIdNumberError);
    updateState();
  });
  proofAddressType?.addEventListener("change", () => {
    updateNumberRow(proofAddressType, proofAddressNumberRow, proofAddressNumber);
    validateNumberInput(proofAddressNumber, proofAddressRegexMap, proofAddressNumberError);
    updateState();
  });
  validIdNumber?.addEventListener("input", () => {
    validateNumberInput(validIdNumber, validIdRegexMap, validIdNumberError);
    updateState();
  });
  proofAddressNumber?.addEventListener("input", () => {
    validateNumberInput(proofAddressNumber, proofAddressRegexMap, proofAddressNumberError);
    updateState();
  });
  businessContactNumber?.addEventListener("input", updateState);
  businessPhotoFile?.addEventListener("change", () => {
    renderFile(businessPhotoFile, businessPhotoSelectedFile);
    updateState();
  });
  bindDropzone(businessPhotoDropzone, businessPhotoFile, businessPhotoSelectedFile);
  updateState();
  renderFile(businessPhotoFile, businessPhotoSelectedFile);
})();
