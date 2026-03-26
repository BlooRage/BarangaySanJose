document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("form.page-form") || document.querySelector("form");
    const submitBtn = form?.querySelector(".submit-btn");
    const applicationTypeSelect = document.getElementById("applicationTypeSelect");
    const requestPurpose = document.getElementById("tricycleRequestPurpose");
    const renewalTricycleHistoryRow = document.getElementById("renewalTricycleHistoryRow");
    const renewalTricycleHistorySelect = document.getElementById("renewalTricycleHistorySelect");
    const tricycleRenewalHistoryDataEl = document.getElementById("tricycleRenewalHistoryData");
    const documentUploadSection = document.getElementById("documentUploadSection");
    const renewalUploadGuidance = document.getElementById("renewalUploadGuidance");
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
    const orVehicleLabelText = document.getElementById("orVehicleLabelText");
    const orVehicleRequiredMark = document.getElementById("orVehicleRequiredMark");
    const crVehicleRequiredMark = document.getElementById("crVehicleRequiredMark");
    const todaPodaRequiredMark = document.getElementById("todaPodaRequiredMark");
    const deedOfSaleRequiredMark = document.getElementById("deedOfSaleRequiredMark");
    const lastYearClearanceRequiredMark = document.getElementById("lastYearClearanceRequiredMark");

    if (!form || !submitBtn) return;

    const tricycleRenewalHistory = (() => {
        if (!tricycleRenewalHistoryDataEl) return [];
        try {
            const parsed = JSON.parse(tricycleRenewalHistoryDataEl.textContent || "[]");
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    })();
    const tricycleRenewalHistoryByRequestId = new Map(
        tricycleRenewalHistory
            .filter((record) => record && typeof record === "object" && String(record.request_id || "").trim() !== "")
            .map((record) => [String(record.request_id).trim(), record])
    );

    const setRequired = (el, required) => {
        if (!el) return;
        if (required) el.setAttribute("required", "required");
        else el.removeAttribute("required");
    };

    const setRequiredMarkerVisible = (markerEl, visible) => {
        if (!markerEl) return;
        markerEl.classList.toggle("d-none", !visible);
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
    const normalizeSimpleValue = (value) => String(value || "").trim().toLowerCase();
    const knownVehicleMakes = ["Rusi", "Yamaha", "Kawasaki", "Honda"];

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

    const setFieldValue = (input, value) => {
        if (!input) return;
        input.value = String(value ?? "");
    };

    const setRadioSelection = (value) => {
        const normalizedValue = normalizeSimpleValue(value);
        if (vehicleNamedYes) {
            vehicleNamedYes.checked = normalizedValue === "yes";
        }
        if (vehicleNamedNo) {
            vehicleNamedNo.checked = normalizedValue === "no";
        }
    };

    const setMatchingSelectValue = (selectEl, targetValue, normalizer = (value) => String(value || "").trim()) => {
        if (!selectEl) return "";
        const normalizedTarget = normalizer(targetValue);
        const match = Array.from(selectEl.options).find((option) => normalizer(option.value) === normalizedTarget);
        selectEl.value = match ? match.value : "";
        return selectEl.value;
    };

    const applyRenewalHistoryRecord = (record) => {
        if (!record || typeof record !== "object") return;

        setMatchingSelectValue(franchiseeSelect, record.franchisee, normalizeFranchisee);

        const vehicleMake = String(record.vehicle_make || "").trim();
        const matchedVehicleMake = Array.from(vehicleMakeSelect?.options || []).find(
            (option) => normalizeSimpleValue(option.value) === normalizeSimpleValue(vehicleMake)
        );
        if (vehicleMakeSelect) {
            vehicleMakeSelect.value = matchedVehicleMake
                ? matchedVehicleMake.value
                : (vehicleMake !== "" ? "Others" : "");
        }

        setFieldValue(plateNumberInput, String(record.plate_number || "").toUpperCase());
        setFieldValue(bodyNumberInput, record.body_number);
        setFieldValue(chassisNumberInput, record.chassis_number);
        setFieldValue(motorNumberInput, record.motor_number);
        setFieldValue(orNumberInput, record.or_number);
        setFieldValue(crNumberInput, record.cr_number);
        setRadioSelection(record.vehicle_named_to_owner);

        enforcePlateLimit(plateNumberInput);
        normalizeNumber(bodyNumberInput);
        normalizeChassis(chassisNumberInput);
        normalizeNumber(motorNumberInput);
        normalizeNumber(orNumberInput);
        normalizeNumber(crNumberInput);

        updateState();

        if (vehicleMakeSelect?.value === "Others") {
            setFieldValue(vehicleMakeOtherInput, vehicleMake);
        }

        if (normalizeFranchisee(franchiseeSelect?.value) === "OTHERS") {
            setFieldValue(otherTodaPodaLocationInput, record.location_of_toda_poda);
            if (todaPodaLocationValueInput) {
                todaPodaLocationValueInput.value = String(record.location_of_toda_poda || "").trim();
            }
        }

        updateState();
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

        renewalTricycleHistoryRow?.classList.toggle("d-none", !isRenewal);
        if (renewalTricycleHistorySelect) {
            renewalTricycleHistorySelect.disabled = !isRenewal || tricycleRenewalHistoryByRequestId.size === 0;
        }

        if (documentUploadSection) {
            documentUploadSection.classList.toggle("d-none", !docsEnabled);
        }
        renewalUploadGuidance?.classList.toggle("d-none", !isRenewal);

        if (orVehicleLabelText) {
            orVehicleLabelText.textContent = isRenewal ? "Updated O.R. of the Vehicle" : "O.R. of the Vehicle";
        }
        setRequiredMarkerVisible(orVehicleRequiredMark, docsEnabled);
        setRequiredMarkerVisible(crVehicleRequiredMark, isNew);
        setRequiredMarkerVisible(todaPodaRequiredMark, isNew);
        setRequiredMarkerVisible(deedOfSaleRequiredMark, isNew && needsDeedOfSale);
        setRequiredMarkerVisible(lastYearClearanceRequiredMark, false);

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
        setRequired(crVehicleFile, isNew);
        setRequired(todaPodaCertFile, isNew);
        setRequired(lastYearClearanceFile, false);
        setRequired(vehicleNamedYes, docsEnabled);
        setRequired(vehicleNamedNo, docsEnabled);

        if (deedOfSaleRow) {
            deedOfSaleRow.classList.toggle("d-none", !needsDeedOfSale);
        }
        if (deedOfSaleFile) {
            deedOfSaleFile.disabled = !needsDeedOfSale;
            setRequired(deedOfSaleFile, isNew && needsDeedOfSale);
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
    renewalTricycleHistorySelect?.addEventListener("change", () => {
        const selectedRequestId = String(renewalTricycleHistorySelect.value || "").trim();
        if (selectedRequestId === "") return;
        applyRenewalHistoryRecord(tricycleRenewalHistoryByRequestId.get(selectedRequestId));
    });
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
