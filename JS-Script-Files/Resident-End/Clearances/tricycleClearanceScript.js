document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("form");
    const submitBtn = form?.querySelector(".submit-btn");
    const tricycleTypeSelect = document.getElementById("tricycleTypeSelect");
    const tricycleCertLabel = document.getElementById("tricycleCertLabel");
    const tricycleCertFile = document.getElementById("tricycleCertFile");
    const appNew = document.getElementById("app_new");
    const appRenewal = document.getElementById("app_renewal");
    const documentUploadSection = document.getElementById("documentUploadSection");
    const bodyNumberInput = document.getElementById("bodyNumber");
    const chassisNumberInput = document.getElementById("chassisNumber");
    const motorNumberInput = document.getElementById("motorNumber");
    const orNumberInput = document.getElementById("orNumber");
    const crNumberInput = document.getElementById("crNumber");
    const plateNumberInput = document.getElementById("plateNumber");
    const validIdType = document.getElementById("validIdType");
    const validIdFile = document.getElementById("validIdFile");
    const validIdNumberRow = document.getElementById("validIdNumberRow");
    const validIdNumber = document.getElementById("validIdNumber");
    const validIdNumberError = document.getElementById("validIdNumberError");
    const ltoRegFiles = document.getElementById("ltoRegFiles");
    const ltoRegSelectedFile = document.getElementById("ltoRegSelectedFile");
    const ltoRegCol = document.getElementById("ltoRegCol");
    const lastYearClearanceCol = document.getElementById("lastYearClearanceCol");
    const lastYearClearanceFile = document.getElementById("lastYearClearanceFile");
    const lastYearClearanceSelectedFile = document.getElementById("lastYearClearanceSelectedFile");
    if (!form || !submitBtn) return;

    const setRequired = (el, required) => {
        if (!el) return;
        if (required) el.setAttribute("required", "required");
        else el.removeAttribute("required");
    };

    const updateCertificationLabel = () => {
        if (!tricycleCertLabel) return;
        const hasAppType = appNew?.checked === true || appRenewal?.checked === true;
        const type = tricycleTypeSelect?.value?.trim();
        const labelText = type ? `Upload ${type} Certification` : "Upload Certification";
        tricycleCertLabel.innerHTML = `${labelText} <span class="required-asterisk">*</span>`;
        if (tricycleCertFile) {
            const canEnable = hasAppType && type;
            tricycleCertFile.disabled = !canEnable;
            if (!canEnable) {
                tricycleCertFile.value = "";
            }
        }
    };

    const wireFileDisplay = (inputId, outputId) => {
        const input = document.getElementById(inputId);
        const output = document.getElementById(outputId);
        if (!input || !output) return;
        const render = () => {
            if (!input.files || input.files.length === 0) {
                output.textContent = "";
                return;
            }
            output.textContent = Array.from(input.files).map((file) => file.name).join(", ");
        };
        input.addEventListener("change", render);
        render();
    };

    const updateState = () => {
        const isNew = appNew?.checked === true;
        const isRenewal = appRenewal?.checked === true;
        const docsEnabled = isNew || isRenewal;

        if (documentUploadSection) {
            documentUploadSection.classList.toggle("d-none", !docsEnabled);
        }

        setRequired(tricycleTypeSelect, docsEnabled);
        setRequired(tricycleCertFile, docsEnabled);
        setRequired(validIdType, docsEnabled);
        setRequired(validIdFile, docsEnabled);
        setRequired(ltoRegFiles, docsEnabled);
        setRequired(lastYearClearanceFile, isRenewal);

        if (ltoRegCol) {
            ltoRegCol.classList.toggle("col-md-12", !isRenewal);
            ltoRegCol.classList.toggle("col-md-6", isRenewal);
        }
        if (lastYearClearanceCol) {
            lastYearClearanceCol.classList.toggle("d-none", !isRenewal);
        }
        if (lastYearClearanceFile) {
            lastYearClearanceFile.disabled = !isRenewal;
            if (!isRenewal) {
                lastYearClearanceFile.value = "";
                if (lastYearClearanceSelectedFile) lastYearClearanceSelectedFile.textContent = "";
            }
        }

        if (!docsEnabled) {
            if (tricycleTypeSelect) tricycleTypeSelect.value = "";
            if (validIdType) validIdType.value = "";
            if (validIdNumber) {
                validIdNumber.value = "";
                validIdNumber.setCustomValidity("");
            }
            if (validIdNumberRow) validIdNumberRow.classList.add("d-none");
            if (validIdNumberError) validIdNumberError.classList.add("d-none");
            if (validIdFile) validIdFile.value = "";
            if (ltoRegFiles) ltoRegFiles.value = "";
            if (ltoRegSelectedFile) ltoRegSelectedFile.textContent = "";
            if (lastYearClearanceFile) lastYearClearanceFile.value = "";
            if (lastYearClearanceSelectedFile) lastYearClearanceSelectedFile.textContent = "";
        }

        updateCertificationLabel();
        updateValidIdNumberRow();
        submitBtn.disabled = !form.checkValidity();
    };

    const setErrorState = (input, errorEl, message) => {
        if (!input || !errorEl) return;
        errorEl.textContent = message;
        errorEl.classList.remove("d-none");
        input.classList.add("is-invalid");
    };

    const clearErrorState = (input, errorEl) => {
        if (!input || !errorEl) return;
        errorEl.classList.add("d-none");
        input.classList.remove("is-invalid");
    };

    const validateField = (input, errorEl, messages) => {
        if (!input || !errorEl) return true;
        const value = input.value.trim();
        const touched = input.dataset.touched === "true";

        if (value === "") {
            if (touched && input.required) {
                setErrorState(input, errorEl, messages.required);
                return false;
            }
            clearErrorState(input, errorEl);
            return true;
        }

        if (input.validity.patternMismatch) {
            setErrorState(input, errorEl, messages.pattern);
            return false;
        }

        clearErrorState(input, errorEl);
        return true;
    };

    const bindValidation = (input, errorEl, messages, normalize = null) => {
        if (!input || !errorEl) return;
        const run = () => {
            if (typeof normalize === "function") normalize(input);
            validateField(input, errorEl, messages);
            updateState();
        };
        input.addEventListener("input", () => {
            input.dataset.touched = "true";
            run();
        });
        input.addEventListener("blur", () => {
            input.dataset.touched = "true";
            run();
        });
        run();
    };

    form.addEventListener("input", updateState);
    form.addEventListener("change", updateState);
    const enforcePlateLimit = (input) => {
        if (!input) return;
        const value = (input.value || "").toUpperCase();
        let count = 0;
        let result = "";
        for (const char of value) {
            if (/[a-zA-Z0-9]/.test(char)) {
                if (count >= 7) continue;
                count += 1;
                result += char;
                continue;
            }
            if (char === "-" || char === " ") {
                result += char;
                continue;
            }
        }
        if (result !== value) {
            input.value = result;
        }
    };
    const normalizeNumber = (input) => {
        input.value = input.value.replace(/[\s-]+/g, "");
    };
    const normalizeChassis = (input) => {
        if (!input) return;
        const raw = (input.value || "").replace(/[^a-zA-Z0-9]/g, "").toUpperCase();
        const first = raw.slice(0, 6);
        const second = raw.slice(6, 12);
        input.value = second.length > 0 ? `${first}-${second}` : first;
    };
    const normalizeValue = (value) => (value || "").replace(/[\s-]/g, "").toUpperCase();
    const validIdRegexMap = {
        philsys: /^\d{12}$/,
        umid: /^\d{12}$/,
        passport: /^[A-Z]{1,2}\d{7}$/,
        drivers_license: /^\d{10}$/,
        prc: /^\d{7}$/,
        postal: /^\d{12}$/,
        gsis: /^(?:\d{10}|\d{12})$/,
        sss: /^\d{10}$/
    };
    const updateValidIdNumberRow = () => {
        if (!validIdType || !validIdNumberRow || !validIdNumber) return;
        const hasValue = validIdType.value !== "";
        validIdNumberRow.classList.toggle("d-none", !hasValue);
        setRequired(validIdNumber, hasValue);
        if (!hasValue) {
            validIdNumber.value = "";
            validIdNumber.setCustomValidity("");
        } else {
            validIdNumber.setCustomValidity("");
        }
        validIdNumber.dataset.regexKey = validIdType.value;
    };
    const validateNumberInput = (inputEl, regexMap, errorEl) => {
        if (!inputEl || !inputEl.required) return;
        const rawValue = inputEl.value.trim();
        if (rawValue === "") {
            inputEl.setCustomValidity("");
            if (errorEl) errorEl.classList.add("d-none");
            return;
        }
        const key = inputEl.dataset.regexKey || "";
        const regex = regexMap[key];
        if (!regex) {
            inputEl.setCustomValidity("Please select a valid option.");
            if (errorEl) errorEl.classList.remove("d-none");
            return;
        }
        const normalized = normalizeValue(rawValue);
        const isInvalid = !regex.test(normalized);
        if (isInvalid) {
            inputEl.setCustomValidity("Please enter a valid number format.");
            if (errorEl) errorEl.classList.remove("d-none");
        } else {
            inputEl.setCustomValidity("");
            if (errorEl) errorEl.classList.add("d-none");
        }
    };
    plateNumberInput?.addEventListener("input", () => {
        enforcePlateLimit(plateNumberInput);
    });
    bindValidation(bodyNumberInput, document.getElementById("bodyNumberError"), {
        required: "Body number is required.",
        pattern: "Numbers only."
    });
    bindValidation(chassisNumberInput, document.getElementById("chassisNumberError"), {
        required: "Chassis number is required.",
        pattern: "Invalid chassis number."
    }, normalizeChassis);
    bindValidation(motorNumberInput, document.getElementById("motorNumberError"), {
        required: "Motor number is required.",
        pattern: "Numbers only."
    });
    bindValidation(orNumberInput, document.getElementById("orNumberError"), {
        required: "O.R. number is required.",
        pattern: "O.R. number must be 7 to 12 digits."
    }, normalizeNumber);
    bindValidation(crNumberInput, document.getElementById("crNumberError"), {
        required: "C.R. number is required.",
        pattern: "C.R. number must be 7 to 12 digits."
    }, normalizeNumber);
    validIdType?.addEventListener("change", () => {
        updateValidIdNumberRow();
        validateNumberInput(validIdNumber, validIdRegexMap, validIdNumberError);
        updateState();
    });
    validIdNumber?.addEventListener("input", () => {
        validateNumberInput(validIdNumber, validIdRegexMap, validIdNumberError);
        updateState();
    });
    tricycleTypeSelect?.addEventListener("change", updateCertificationLabel);
    appNew?.addEventListener("change", updateState);
    appRenewal?.addEventListener("change", updateState);
    updateCertificationLabel();
    wireFileDisplay("tricycleCertFile", "tricycleCertSelectedFile");
    wireFileDisplay("ltoRegFiles", "ltoRegSelectedFile");
    wireFileDisplay("lastYearClearanceFile", "lastYearClearanceSelectedFile");
    updateValidIdNumberRow();
    updateState();
});
