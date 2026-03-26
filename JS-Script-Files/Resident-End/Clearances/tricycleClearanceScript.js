document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("form");
    const submitBtn = form?.querySelector(".submit-btn");
    const applicationTypeSelect = document.getElementById("applicationTypeSelect");
    const requestPurpose = document.getElementById("tricycleRequestPurpose");
    const documentUploadSection = document.getElementById("documentUploadSection");
    const bodyNumberInput = document.getElementById("bodyNumber");
    const chassisNumberInput = document.getElementById("chassisNumber");
    const motorNumberInput = document.getElementById("motorNumber");
    const orNumberInput = document.getElementById("orNumber");
    const crNumberInput = document.getElementById("crNumber");
    const plateNumberInput = document.getElementById("plateNumber");
    const franchiseeSelect = document.getElementById("franchiseeSelect");
    const vehicleMakeSelect = document.getElementById("vehicleMakeSelect");
    const vehicleMakeOtherRow = document.getElementById("vehicleMakeOtherRow");
    const vehicleMakeOtherInput = document.getElementById("vehicleMakeOther");
    const todaPodaLocationInput = document.getElementById("todaPodaLocation");
    const todaPodaLocationValueInput = document.getElementById("todaPodaLocationValue");
    const otherTodaPodaLocationRow = document.getElementById("otherTodaPodaLocationRow");
    const otherTodaPodaLocationInput = document.getElementById("otherTodaPodaLocation");
    const otherTodaPodaLocationError = document.getElementById("otherTodaPodaLocationError");
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

    const normalizeFranchisee = (value) => String(value || "").trim().replace(/\s+/g, " ").toUpperCase();

    const franchiseeLocationMap = {
        "PRIVATE - FAMILY USE": "",
        "PRIVATE - DELIVERY USE": "",
        "SJ1 - NEW ROTODA": "AREA 1",
        "SJ-1 NEW ROTODA": "AREA 1",
        "SJ2 - SUBTODA": "AREA 2",
        "SJ-2 SUBTODA": "AREA 2",
        "SJ3 - BAGONG BUHAY TODA": "AREA 3",
        "SJ-3 BAGONG BUHAY TODA": "AREA 3",
        "SJ4 - KV1 TODA": "AREA 4",
        "SJ-4 KV1 TODA": "AREA 4",
        "SJ5 - UPLAND TODA": "AREA 5",
        "SJ-5 UPLAND TODA": "AREA 5",
        "SUBPODA": "",
        "SUB-PODA": "",
        "OTHERS": null
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
        requestPurpose.value = String(applicationTypeSelect?.value || "").trim() === "Renewal"
            ? "Tricycle Permit - Renewal"
            : "Tricycle Permit - New Application";
    };

    const updateTodaPodaLocation = () => {
        if (!todaPodaLocationInput || !franchiseeSelect) return;
        const franchiseeKey = normalizeFranchisee(franchiseeSelect.value);
        const mappedLocation = Object.prototype.hasOwnProperty.call(franchiseeLocationMap, franchiseeKey)
            ? franchiseeLocationMap[franchiseeKey]
            : "";
        const isOther = franchiseeKey === "OTHERS";
        const wasOther = otherTodaPodaLocationInput?.dataset.active === "true";

        todaPodaLocationInput.disabled = true;
        todaPodaLocationInput.classList.add("location-prefilled-display");

        if (isOther) {
            todaPodaLocationInput.value = "";
            if (otherTodaPodaLocationRow) {
                otherTodaPodaLocationRow.classList.remove("d-none");
            }
            if (otherTodaPodaLocationInput) {
                otherTodaPodaLocationInput.disabled = false;
                if (!wasOther) {
                    otherTodaPodaLocationInput.value = "";
                }
                otherTodaPodaLocationInput.placeholder = "Enter TODA / PODA location";
                otherTodaPodaLocationInput.dataset.active = "true";
            }
            if (todaPodaLocationValueInput) {
                todaPodaLocationValueInput.value = String(otherTodaPodaLocationInput?.value || "").trim();
            }
            return;
        }

        todaPodaLocationInput.value = mappedLocation ?? "";
        if (todaPodaLocationValueInput) {
            todaPodaLocationValueInput.value = mappedLocation ?? "";
        }
        if (otherTodaPodaLocationRow) {
            otherTodaPodaLocationRow.classList.add("d-none");
        }
        if (otherTodaPodaLocationInput) {
            otherTodaPodaLocationInput.disabled = true;
            otherTodaPodaLocationInput.value = "";
            otherTodaPodaLocationInput.dataset.active = "false";
        }
        clearErrorState(otherTodaPodaLocationInput, otherTodaPodaLocationError);
    };

    const updateState = () => {
        const applicationType = String(applicationTypeSelect?.value || "").trim();
        const isNew = applicationType === "New";
        const isRenewal = applicationType === "Renewal";
        const docsEnabled = isNew || isRenewal;
        const isVehicleNamedToOwner = vehicleNamedYes?.checked === true;
        const needsDeedOfSale = docsEnabled && vehicleNamedNo?.checked === true;
        const isOtherFranchisee = normalizeFranchisee(franchiseeSelect?.value) === "OTHERS";
        const isOtherVehicleMake = String(vehicleMakeSelect?.value || "").trim() === "Others";

        updateRequestPurpose();
        updateTodaPodaLocation();

        if (documentUploadSection) {
            documentUploadSection.classList.toggle("d-none", !docsEnabled);
        }

        if (vehicleMakeOtherRow) {
            vehicleMakeOtherRow.classList.toggle("d-none", !isOtherVehicleMake);
        }
        if (vehicleMakeOtherInput) {
            const shouldFocusVehicleMakeOther = isOtherVehicleMake && vehicleMakeOtherInput.dataset.active !== "true";
            vehicleMakeOtherInput.disabled = !isOtherVehicleMake;
            setRequired(vehicleMakeOtherInput, isOtherVehicleMake);
            vehicleMakeOtherInput.dataset.active = isOtherVehicleMake ? "true" : "false";
            if (!isOtherVehicleMake) {
                vehicleMakeOtherInput.value = "";
            } else if (shouldFocusVehicleMakeOther) {
                window.requestAnimationFrame(() => {
                    vehicleMakeOtherInput.focus();
                });
            }
        }

        setRequired(franchiseeSelect, true);
        setRequired(vehicleMakeSelect, true);
        setRequired(otherTodaPodaLocationInput, isOtherFranchisee);
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
    bindValidation(otherTodaPodaLocationInput, otherTodaPodaLocationError, {
        required: "Location is required when franchisee is Others.",
        pattern: "Location is required when franchisee is Others."
    });

    applicationTypeSelect?.addEventListener("change", updateState);
    vehicleNamedYes?.addEventListener("change", updateState);
    vehicleNamedNo?.addEventListener("change", updateState);
    franchiseeSelect?.addEventListener("change", updateState);
    vehicleMakeSelect?.addEventListener("change", updateState);
    otherTodaPodaLocationInput?.addEventListener("input", () => {
        if (todaPodaLocationValueInput) {
            todaPodaLocationValueInput.value = String(otherTodaPodaLocationInput.value || "").trim();
        }
    });
    otherTodaPodaLocationInput?.addEventListener("blur", () => {
        if (todaPodaLocationValueInput) {
            todaPodaLocationValueInput.value = String(otherTodaPodaLocationInput.value || "").trim();
        }
    });

    wireFileDisplay("orVehicleFile", "orVehicleSelectedFile");
    wireFileDisplay("crVehicleFile", "crVehicleSelectedFile");
    wireFileDisplay("todaPodaCertFile", "todaPodaCertSelectedFile");
    wireFileDisplay("authorizationVehicleFile", "authorizationVehicleSelectedFile");
    wireFileDisplay("deedOfSaleFile", "deedOfSaleSelectedFile");
    wireFileDisplay("lastYearClearanceFile", "lastYearClearanceSelectedFile");

    updateState();
});
