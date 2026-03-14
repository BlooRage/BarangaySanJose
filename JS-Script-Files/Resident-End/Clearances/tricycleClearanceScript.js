document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("form");
    const submitBtn = form?.querySelector(".submit-btn");
    const appNew = document.getElementById("app_new");
    const appRenewal = document.getElementById("app_renewal");
    const requestPurpose = document.getElementById("tricycleRequestPurpose");
    const documentUploadSection = document.getElementById("documentUploadSection");
    const bodyNumberInput = document.getElementById("bodyNumber");
    const chassisNumberInput = document.getElementById("chassisNumber");
    const motorNumberInput = document.getElementById("motorNumber");
    const orNumberInput = document.getElementById("orNumber");
    const crNumberInput = document.getElementById("crNumber");
    const plateNumberInput = document.getElementById("plateNumber");
    const franchiseeSelect = document.getElementById("franchiseeSelect");
    const vehicleNamedYes = document.getElementById("vehicleNamedYes");
    const vehicleNamedNo = document.getElementById("vehicleNamedNo");
    const deedOfSaleRow = document.getElementById("deedOfSaleRow");
    const deedOfSaleFile = document.getElementById("deedOfSaleFile");
    const orVehicleFile = document.getElementById("orVehicleFile");
    const crVehicleFile = document.getElementById("crVehicleFile");
    const todaPodaCertFile = document.getElementById("todaPodaCertFile");
    const authorizationVehicleFile = document.getElementById("authorizationVehicleFile");
    const lastYearClearanceCol = document.getElementById("lastYearClearanceCol");
    const lastYearClearanceFile = document.getElementById("lastYearClearanceFile");
    const lastYearClearanceSelectedFile = document.getElementById("lastYearClearanceSelectedFile");
    if (!form || !submitBtn) return;

    const setRequired = (el, required) => {
        if (!el) return;
        if (required) el.setAttribute("required", "required");
        else el.removeAttribute("required");
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

    const clearFileInput = (input, output) => {
        if (input) input.value = "";
        if (output) output.textContent = "";
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
            }
        }
        if (result !== value) {
            input.value = result;
        }
    };

    const normalizeNumber = (input) => {
        if (!input) return;
        input.value = input.value.replace(/[\s-]+/g, "");
    };

    const normalizeChassis = (input) => {
        if (!input) return;
        const raw = (input.value || "").replace(/[^a-zA-Z0-9]/g, "").toUpperCase();
        const first = raw.slice(0, 6);
        const second = raw.slice(6, 12);
        input.value = second.length > 0 ? `${first}-${second}` : first;
    };

    const updateRequestPurpose = () => {
        if (!requestPurpose) return;
        requestPurpose.value = appRenewal?.checked === true
            ? "Tricycle Permit - Renewal"
            : "Tricycle Permit - New Application";
    };

    const updateState = () => {
        const isNew = appNew?.checked === true;
        const isRenewal = appRenewal?.checked === true;
        const docsEnabled = isNew || isRenewal;
        const isVehicleNamedToOwner = vehicleNamedYes?.checked === true;
        const needsDeedOfSale = docsEnabled && vehicleNamedNo?.checked === true;

        updateRequestPurpose();

        if (documentUploadSection) {
            documentUploadSection.classList.toggle("d-none", !docsEnabled);
        }

        setRequired(franchiseeSelect, true);
        setRequired(orVehicleFile, docsEnabled);
        setRequired(crVehicleFile, docsEnabled);
        setRequired(todaPodaCertFile, docsEnabled);
        setRequired(lastYearClearanceFile, isRenewal);
        setRequired(vehicleNamedYes, docsEnabled);
        setRequired(vehicleNamedNo, docsEnabled);

        if (deedOfSaleRow) {
            deedOfSaleRow.classList.toggle("d-none", !needsDeedOfSale);
        }
        if (deedOfSaleFile) {
            deedOfSaleFile.disabled = !needsDeedOfSale;
            setRequired(deedOfSaleFile, needsDeedOfSale);
            if (!needsDeedOfSale) {
                clearFileInput(deedOfSaleFile, document.getElementById("deedOfSaleSelectedFile"));
            }
        }

        if (lastYearClearanceCol) {
            lastYearClearanceCol.classList.toggle("d-none", !isRenewal);
        }
        if (lastYearClearanceFile) {
            lastYearClearanceFile.disabled = !isRenewal;
            if (!isRenewal) {
                clearFileInput(lastYearClearanceFile, lastYearClearanceSelectedFile);
            }
        }

        if (!docsEnabled) {
            if (vehicleNamedYes) vehicleNamedYes.checked = false;
            if (vehicleNamedNo) vehicleNamedNo.checked = false;
            clearFileInput(orVehicleFile, document.getElementById("orVehicleSelectedFile"));
            clearFileInput(crVehicleFile, document.getElementById("crVehicleSelectedFile"));
            clearFileInput(todaPodaCertFile, document.getElementById("todaPodaCertSelectedFile"));
            clearFileInput(authorizationVehicleFile, document.getElementById("authorizationVehicleSelectedFile"));
            clearFileInput(deedOfSaleFile, document.getElementById("deedOfSaleSelectedFile"));
            clearFileInput(lastYearClearanceFile, lastYearClearanceSelectedFile);
        } else if (isVehicleNamedToOwner) {
            clearFileInput(deedOfSaleFile, document.getElementById("deedOfSaleSelectedFile"));
        }

        submitBtn.disabled = !form.checkValidity();
    };

    form.addEventListener("input", updateState);
    form.addEventListener("change", updateState);

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

    appNew?.addEventListener("change", updateState);
    appRenewal?.addEventListener("change", updateState);
    vehicleNamedYes?.addEventListener("change", updateState);
    vehicleNamedNo?.addEventListener("change", updateState);

    wireFileDisplay("orVehicleFile", "orVehicleSelectedFile");
    wireFileDisplay("crVehicleFile", "crVehicleSelectedFile");
    wireFileDisplay("todaPodaCertFile", "todaPodaCertSelectedFile");
    wireFileDisplay("authorizationVehicleFile", "authorizationVehicleSelectedFile");
    wireFileDisplay("deedOfSaleFile", "deedOfSaleSelectedFile");
    wireFileDisplay("lastYearClearanceFile", "lastYearClearanceSelectedFile");

    updateState();
});
