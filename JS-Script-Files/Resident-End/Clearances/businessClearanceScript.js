(() => {
  const form = document.querySelector("form");
  const submitBtn = form?.querySelector(".submit-btn");
  const ownerTypeSelect = document.getElementById("ownerTypeSelect");
  const renterOwnerDetails = document.getElementById("renterOwnerDetails");
  const applicationTypeSelect = document.getElementById("applicationTypeSelect");
  const documentUploadSection = document.getElementById("documentUploadSection");
  const businessSameAddress = document.getElementById("businessSameAddress");
  const businessAddressSystemRow = document.getElementById("businessAddressSystemRow");
  const businessAddressSystem = document.getElementById("businessAddressSystem");
  const businessFullAddressWrapper = document.getElementById("businessFullAddressWrapper");
  const ownerFullAddress = document.getElementById("owner_full_address");
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
  const documentUploadNew = document.getElementById("documentUploadNew");
  const documentUploadRenewal = document.getElementById("documentUploadRenewal");
  const renewalBusinessRegType = document.getElementById("renewalBusinessRegType");
  const renewalBusinessRegFile = document.getElementById("renewalBusinessRegFile");
  const renewalProofAddressType = document.getElementById("renewalProofAddressType");
  const renewalProofAddressFile = document.getElementById("renewalProofAddressFile");
  const renewalProofAddressNumberRow = document.getElementById("renewalProofAddressNumberRow");
  const renewalProofAddressNumber = document.getElementById("renewalProofAddressNumber");
  const renewalProofAddressNumberError = document.getElementById("renewalProofAddressNumberError");
  const businessContactNumber = document.getElementById("business_contact_number");
  const businessContactNumberError = document.getElementById("business_contact_number_error");
  const businessRequestPurpose = document.getElementById("businessRequestPurpose");
  const businessHouseSystemWrapper = document.getElementById("businessHouseSystemWrapper");
  const businessSubdivisionHouseRow = document.getElementById("businessSubdivisionHouseRow");
  const businessBlockSystemWrapper = document.getElementById("businessBlockSystemWrapper");
  const businessBarangayRow = document.getElementById("businessBarangayRow");
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
  const renterOwnerRequired = renterOwnerDetails
    ? Array.from(renterOwnerDetails.querySelectorAll("input[name='ro_ln'], input[name='ro_fn']"))
    : [];
  if (!form || !submitBtn) return;

  const setRequired = (el, required) => {
    if (!el) return;
    el.required = required;
  };

  const setWrapperState = (wrapper, enabled) => {
    if (!wrapper) return;
    wrapper.classList.toggle("d-none", !enabled);
    wrapper.querySelectorAll("input, select").forEach((el) => {
      el.disabled = !enabled;
      if (!enabled) {
        el.value = "";
      }
    });
  };

  const normalizeValue = (value) => (value || '').replace(/[\s-]/g, '').toUpperCase();

  const proofAddressRegexMap = {
    lease: /^.+$/,
    tct: /^(?:TCT|T)?\d{5,10}$/,
    tax_declaration: /^(?:TD)?\d{3,15}$/
  };
  const contactNumberRegex = /^09\d{9}$/;

  const updateNumberRow = (selectEl, rowEl, inputEl) => {
    if (!selectEl || !rowEl || !inputEl) return;
    const hasValue = selectEl.value !== '';
    const needsNumber = hasValue && selectEl.value !== 'lease';
    rowEl.classList.toggle("d-none", !needsNumber);
    setRequired(inputEl, needsNumber);
    if (!needsNumber) {
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

  const buildBusinessFullAddress = () => {
    if (businessSameAddress?.checked) {
      return (ownerFullAddress?.value || '').trim();
    }

    const system = (businessAddressSystem?.value || '').trim();
    if (system === 'lot_block') {
      const lotNumber = (businessLotNumber?.value || '').trim();
      const blockNumber = (businessBlockNumber?.value || '').trim();
      const phaseNumber = (businessPhaseNumber?.value || '').trim();
      if (lotNumber === '' && blockNumber === '' && phaseNumber === '') {
        return '';
      }
      const parts = [
        lotNumber ? `Lot ${lotNumber}` : '',
        blockNumber ? `Blk ${blockNumber}` : '',
        phaseNumber ? `Phase ${phaseNumber}` : '',
        (businessSubdivisionBlock?.value || '').trim(),
        (businessBarangay?.value || '').trim(),
        (businessCity?.value || '').trim(),
        (businessProvince?.value || '').trim()
      ].filter(Boolean);
      return parts.join(', ');
    }

    const streetLine = [businessStreetNumber?.value || '', businessStreetName?.value || ''].filter(Boolean).join(' ').trim();
    if (streetLine === '') {
      return '';
    }

    const parts = [
      businessUnitNumber?.value ? `Unit ${businessUnitNumber.value.trim()}` : '',
      streetLine,
      (businessSubdivision?.value || '').trim(),
      (businessBarangay?.value || '').trim(),
      (businessCity?.value || '').trim(),
      (businessProvince?.value || '').trim()
    ].filter(Boolean);
    return parts.join(', ');
  };

  const updateState = () => {
    const applicationType = String(applicationTypeSelect?.value || "").trim();
    const isNewApp = applicationType === "New";
    const isRenewal = applicationType === "Renewal";
    const isSameAddress = businessSameAddress?.checked === true;
    const businessSystem = (businessAddressSystem?.value || '').trim();
    if (businessRequestPurpose) {
      businessRequestPurpose.value = isRenewal
        ? "Business Permit - Renewal"
        : "Business Permit - New Application";
    }
    if (documentUploadSection) {
      documentUploadSection.classList.toggle("d-none", !(isNewApp || isRenewal));
    }
    if (documentUploadNew) {
      documentUploadNew.classList.toggle("d-none", !isNewApp);
    }
    if (documentUploadRenewal) {
      documentUploadRenewal.classList.toggle("d-none", !isRenewal);
    }

    if (businessAddressSystemRow) {
      businessAddressSystemRow.classList.toggle("d-none", isSameAddress);
    }
    if (businessFullAddressWrapper) {
      businessFullAddressWrapper.classList.toggle("d-none", !isSameAddress);
    }
    setRequired(businessAddressSystem, !isSameAddress);

    setWrapperState(businessHouseSystemWrapper, !isSameAddress && businessSystem === 'house');
    setWrapperState(businessSubdivisionHouseRow, !isSameAddress && businessSystem === 'house');
    setWrapperState(businessBlockSystemWrapper, !isSameAddress && businessSystem === 'lot_block');

    setRequired(businessStreetNumber, !isSameAddress && businessSystem === 'house');
    setRequired(businessStreetName, !isSameAddress && businessSystem === 'house');
    setRequired(businessLotNumber, !isSameAddress && businessSystem === 'lot_block');
    setRequired(businessBlockNumber, !isSameAddress && businessSystem === 'lot_block');
    setRequired(businessPhaseNumber, !isSameAddress && businessSystem === 'lot_block');

    setRequired(businessRegType, isNewApp);
    setRequired(businessRegFile, isNewApp);
    setRequired(proofAddressType, isNewApp);
    setRequired(proofAddressFile, isNewApp);
    setRequired(businessPhotoFile, isNewApp);
    setRequired(renewalBusinessRegType, isRenewal);
    setRequired(renewalBusinessRegFile, isRenewal);
    setRequired(renewalProofAddressType, isRenewal);
    setRequired(renewalProofAddressFile, isRenewal);

    if (businessFullAddress) {
      businessFullAddress.value = buildBusinessFullAddress();
    }

    updateNumberRow(proofAddressType, proofAddressNumberRow, proofAddressNumber);
    updateNumberRow(renewalProofAddressType, renewalProofAddressNumberRow, renewalProofAddressNumber);

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
      [businessRegType, proofAddressType].forEach((el) => {
        if (el) el.value = '';
      });
      [businessRegFile, proofAddressFile].forEach((el) => {
        if (el) el.value = '';
      });
      if (businessPhotoFile) {
        businessPhotoFile.value = '';
      }
      [proofAddressNumber].forEach((el) => {
        if (el) {
          el.value = '';
          el.setCustomValidity('');
        }
      });
      if (proofAddressNumberError) proofAddressNumberError.classList.add("d-none");
      [proofAddressNumberRow].forEach((row) => {
        if (row) row.classList.add("d-none");
      });
    }

    if (!isRenewal) {
      [renewalBusinessRegType, renewalProofAddressType].forEach((el) => {
        if (el) el.value = '';
      });
      [renewalBusinessRegFile, renewalProofAddressFile].forEach((el) => {
        if (el) el.value = '';
      });
      [renewalProofAddressNumber].forEach((el) => {
        if (el) {
          el.value = '';
          el.setCustomValidity('');
        }
      });
      if (renewalProofAddressNumberError) renewalProofAddressNumberError.classList.add("d-none");
      [renewalProofAddressNumberRow].forEach((row) => {
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
  businessSameAddress?.addEventListener("change", updateState);
  businessAddressSystem?.addEventListener("change", updateState);
  proofAddressType?.addEventListener("change", () => {
    updateNumberRow(proofAddressType, proofAddressNumberRow, proofAddressNumber);
    validateNumberInput(proofAddressNumber, proofAddressRegexMap, proofAddressNumberError);
    updateState();
  });
  renewalProofAddressType?.addEventListener("change", () => {
    updateNumberRow(renewalProofAddressType, renewalProofAddressNumberRow, renewalProofAddressNumber);
    validateNumberInput(renewalProofAddressNumber, proofAddressRegexMap, renewalProofAddressNumberError);
    updateState();
  });
  proofAddressNumber?.addEventListener("input", () => {
    validateNumberInput(proofAddressNumber, proofAddressRegexMap, proofAddressNumberError);
    updateState();
  });
  renewalProofAddressNumber?.addEventListener("input", () => {
    validateNumberInput(renewalProofAddressNumber, proofAddressRegexMap, renewalProofAddressNumberError);
    updateState();
  });
  businessContactNumber?.addEventListener("input", updateState);
  businessRegFile?.addEventListener("change", updateState);
  proofAddressFile?.addEventListener("change", updateState);
  businessPhotoFile?.addEventListener("change", updateState);
  renewalBusinessRegFile?.addEventListener("change", updateState);
  renewalProofAddressFile?.addEventListener("change", updateState);
  if (businessPhotoDropzone && businessPhotoFile && businessPhotoSelectedFile) {
    const renderBusinessPhotoFile = () => {
      const names = Array.from(businessPhotoFile.files || []).map((file) => file.name);
      businessPhotoSelectedFile.textContent = names.length ? `Selected: ${names.join(", ")}` : "";
    };

    ["dragenter", "dragover"].forEach((eventName) => {
      businessPhotoDropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        businessPhotoDropzone.classList.add("is-dragging");
      });
    });

    ["dragleave", "drop"].forEach((eventName) => {
      businessPhotoDropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        businessPhotoDropzone.classList.remove("is-dragging");
      });
    });

    businessPhotoDropzone.addEventListener("drop", (e) => {
      const dt = e.dataTransfer;
      if (dt && dt.files && dt.files.length) {
        businessPhotoFile.files = dt.files;
        renderBusinessPhotoFile();
        updateState();
      }
    });

    businessPhotoFile.addEventListener("change", () => {
      renderBusinessPhotoFile();
      updateState();
    });

    renderBusinessPhotoFile();
  }
  updateState();
})();
