document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("form");
    const submitBtn = form?.querySelector(".submit-btn");
    const lotAddressSystem = document.getElementById("lotAddressSystem");
    const lotHouseWrapper = document.getElementById("lotHouseSystemWrapper");
    const lotBlockWrapper = document.getElementById("lotBlockSystemWrapper");
    const lotBarangayRow = document.getElementById("lotBarangayRow");
    const lotUnitNumber = document.getElementById("lot_unit_number");
    const lotStreetNumber = document.getElementById("lot_street_number");
    const lotStreetName = document.getElementById("lot_street_name");
    const lotSubdivisionHouse = document.getElementById("lot_subdivision");
    const lotNumber = document.getElementById("lot_number");
    const blockNumber = document.getElementById("block_number");
    const lotPhaseNumber = document.getElementById("lot_phase_number");
    const lotSubdivisionBlock = document.getElementById("lot_subdivision_block");
    const proofAddressType = document.getElementById("proofAddressType");
    const proofAddressFile = document.getElementById("proofAddressFile");
    const proofAddressDropzone = document.getElementById("proofAddressDropzone");
    const proofAddressSelectedFile = document.getElementById("proofAddressSelectedFile");
    const proofAddressNumberRow = document.getElementById("proofAddressNumberRow");
    const proofAddressNumber = document.getElementById("proofAddressNumber");
    const proofAddressNumberError = document.getElementById("proofAddressNumberError");
    const sitePhotoFile = document.getElementById("sitePhotoFile");
    const sitePhotoDropzone = document.getElementById("sitePhotoDropzone");
    const sitePhotoSelectedFile = document.getElementById("sitePhotoSelectedFile");
    const secCertificateFile = document.getElementById("secCertificateFile");
    const secCertificateDropzone = document.getElementById("secCertificateDropzone");
    const secCertificateSelectedFile = document.getElementById("secCertificateSelectedFile");
    if (!form || !submitBtn) return;

    const setWrapperState = (wrapper, enabled) => {
        if (!wrapper) return;
        wrapper.classList.toggle("d-none", !enabled);
        wrapper.querySelectorAll("input, select").forEach((el) => {
            el.disabled = !enabled;
            if (!enabled) {
                el.value = "";
                if (el.type === "checkbox" || el.type === "radio") el.checked = false;
            }
        });
    };

    const setRequired = (el, required) => {
        if (!el) return;
        if (required) el.setAttribute("required", "required");
        else el.removeAttribute("required");
    };

    const setReadOnly = (el, isReadOnly) => {
        if (!el) return;
        el.readOnly = isReadOnly;
        if (isReadOnly) {
            el.classList.add("text-bg-light");
        } else {
            el.classList.remove("text-bg-light");
        }
    };

    const setEnabled = (wrapper, enabled) => {
        if (!wrapper) return;
        wrapper.querySelectorAll("input, select, textarea").forEach((el) => {
            el.disabled = !enabled;
        });
    };

    const normalizeValue = (value) => (value || "").replace(/[\s-]/g, "").toUpperCase();

    const proofAddressRegexMap = {
        tct: /^(?:TCT|T)?\d{5,10}$/,
        tax_declaration: /^(?:TD)?\d{3,15}$/
    };

    const updateNumberRow = (selectEl, rowEl, inputEl) => {
        if (!selectEl || !rowEl || !inputEl) return;
        const hasValue = selectEl.value !== "";
        const needsNumber = hasValue;
        rowEl.classList.toggle("d-none", !needsNumber);
        setRequired(inputEl, needsNumber);
        if (!needsNumber) {
            inputEl.value = "";
            inputEl.setCustomValidity("");
        } else {
            inputEl.setCustomValidity("");
        }
        inputEl.dataset.regexKey = selectEl.value;
        inputEl.dataset.regexGroup = selectEl.id;
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
        const val = lotAddressSystem ? lotAddressSystem.value : "";
        setWrapperState(lotHouseWrapper, val === "house");
        setWrapperState(lotBlockWrapper, val === "lot_block");

        setRequired(lotAddressSystem, true);
        setRequired(lotStreetNumber, val === "house");
        setRequired(lotStreetName, val === "house");
        setRequired(lotSubdivisionHouse, false);
        setRequired(lotNumber, val === "lot_block");
        setRequired(blockNumber, val === "lot_block");
        setRequired(lotPhaseNumber, val === "lot_block");
        setRequired(lotSubdivisionBlock, false);

        setRequired(proofAddressType, true);
        setRequired(proofAddressFile, true);
        setRequired(sitePhotoFile, true);
        setRequired(secCertificateFile, true);
        updateNumberRow(proofAddressType, proofAddressNumberRow, proofAddressNumber);

        setEnabled(lotBarangayRow, true);
        [lotUnitNumber, lotStreetNumber, lotStreetName, lotSubdivisionHouse].forEach((el) =>
            setReadOnly(el, false)
        );
        submitBtn.disabled = !form.checkValidity();
    };

    form.addEventListener("input", updateState);
    form.addEventListener("change", updateState);
    lotAddressSystem?.addEventListener("change", updateState);
    proofAddressType?.addEventListener("change", () => {
        updateNumberRow(proofAddressType, proofAddressNumberRow, proofAddressNumber);
        validateNumberInput(proofAddressNumber, proofAddressRegexMap, proofAddressNumberError);
        updateState();
    });
    proofAddressNumber?.addEventListener("input", () => {
        validateNumberInput(proofAddressNumber, proofAddressRegexMap, proofAddressNumberError);
        updateState();
    });
    proofAddressFile?.addEventListener("change", () => {
        renderFile(proofAddressFile, proofAddressSelectedFile);
        updateState();
    });
    sitePhotoFile?.addEventListener("change", () => {
        renderFile(sitePhotoFile, sitePhotoSelectedFile);
        updateState();
    });
    secCertificateFile?.addEventListener("change", () => {
        renderFile(secCertificateFile, secCertificateSelectedFile);
        updateState();
    });
    bindDropzone(proofAddressDropzone, proofAddressFile, proofAddressSelectedFile);
    bindDropzone(sitePhotoDropzone, sitePhotoFile, sitePhotoSelectedFile);
    bindDropzone(secCertificateDropzone, secCertificateFile, secCertificateSelectedFile);
    updateState();
    renderFile(proofAddressFile, proofAddressSelectedFile);
    renderFile(sitePhotoFile, sitePhotoSelectedFile);
    renderFile(secCertificateFile, secCertificateSelectedFile);
});
