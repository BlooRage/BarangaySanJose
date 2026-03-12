document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("form");
    const submitBtn = form?.querySelector(".submit-btn");
    const incidentDateInput = document.getElementById("incidentDate");
    const incidentDateError = document.getElementById("incidentDateError");
    const incidentTimeInput = form?.querySelector('input[name="incident_time"]') || null;
    const feedbackData = document.getElementById("complaintFeedbackData");
    const complainantAddressSystem = document.getElementById("complainantAddressSystem");
    const complainantHouseWrapper = document.getElementById("complainantHouseSystemWrapper");
    const complainantLotWrapper = document.getElementById("complainantLotBlockSystemWrapper");
    const complainantHouseNumber = document.getElementById("complainantHouseNumber");
    const complainantStreetName = document.getElementById("complainantStreetName");
    const complainantLotNumber = document.getElementById("complainantLotNumber");
    const complainantBlockNumber = document.getElementById("complainantBlockNumber");
    const complainantPhaseNumber = document.getElementById("complainantPhaseNumber");
    const phoneInputs = form?.querySelectorAll('input[name="complainant_contact_number"], input[name="subject_contact_number"], input[name="witness_contact_number"]') || [];
    let isSubmitting = false;
    const touchedFields = new WeakSet();

    if (!form || !submitBtn) {
        return;
    }

    const getNow = () => new Date();
    const toIsoDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");
        return `${year}-${month}-${day}`;
    };
    const toTimeValue = (date) => {
        const hours = String(date.getHours()).padStart(2, "0");
        const minutes = String(date.getMinutes()).padStart(2, "0");
        return `${hours}:${minutes}`;
    };
    const formatDisplayDateTime = (date) => date.toLocaleString(undefined, {
        year: "numeric",
        month: "long",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit",
    });
    const getOldestAllowed = () => {
        const oldest = getNow();
        oldest.setMonth(oldest.getMonth() - 6);
        return oldest;
    };

    const setRequired = (element, isRequired) => {
        if (!element) return;
        if (isRequired) {
            element.setAttribute("required", "required");
        } else {
            element.removeAttribute("required");
        }
    };

    const ensureFeedbackEl = (input) => {
        if (!input) return null;
        let feedback = input.nextElementSibling;
        if (feedback && feedback.classList.contains("invalid-feedback")) {
            return feedback;
        }
        feedback = document.createElement("div");
        feedback.className = "invalid-feedback";
        input.insertAdjacentElement("afterend", feedback);
        return feedback;
    };

    const getValidationMessage = (input) => {
        if (!input) return "";
        const validity = input.validity;
        if (validity.valid) return "";
        if (validity.valueMissing) return "This field is required.";
        if (validity.typeMismatch) return "Please enter a valid value.";
        if (validity.tooShort) return `Please enter at least ${input.minLength} characters.`;
        if (validity.tooLong) return `Please enter no more than ${input.maxLength} characters.`;
        if (validity.patternMismatch) return input.title || "Please match the requested format.";
        if (validity.rangeUnderflow) return `Value must be at least ${input.min}.`;
        if (validity.rangeOverflow) return `Value must be at most ${input.max}.`;
        if (validity.stepMismatch) return "Please enter a valid value.";
        return input.validationMessage || "Please enter a valid value.";
    };

    const renderValidity = (input) => {
        if (!input || input.disabled) return;
        const feedback = ensureFeedbackEl(input);
        const message = getValidationMessage(input);
        if (message) {
            input.classList.add("is-invalid");
            if (feedback) feedback.textContent = message;
            return;
        }
        input.classList.remove("is-invalid");
        if (feedback) feedback.textContent = "";
    };

    const renderIfTouched = (input) => {
        if (!input || input.disabled) return;
        if (touchedFields.has(input)) {
            renderValidity(input);
            return;
        }
        input.classList.remove("is-invalid");
        const feedback = ensureFeedbackEl(input);
        if (feedback) feedback.textContent = "";
    };

    const renderAllValidity = () => {
        form.querySelectorAll("input, select, textarea").forEach((field) => {
            if (!field.disabled) {
                renderValidity(field);
            }
        });
    };

    const setWrapperState = (wrapper, show) => {
        if (!wrapper) return;
        wrapper.classList.toggle("d-none", !show);
        wrapper.querySelectorAll("input, select, textarea").forEach((field) => {
            field.disabled = !show;
            if (!show) {
                field.value = "";
                field.setCustomValidity("");
                field.classList.remove("is-invalid");
                const feedback = field.nextElementSibling;
                if (feedback && feedback.classList.contains("invalid-feedback")) {
                    feedback.textContent = "";
                }
            }
        });
    };

    const applyAddressSystem = () => {
        if (!complainantAddressSystem) return;
        const mode = String(complainantAddressSystem.value || "");
        const useHouse = mode === "house";
        const useLotBlock = mode === "lot_block";

        setWrapperState(complainantHouseWrapper, useHouse);
        setWrapperState(complainantLotWrapper, useLotBlock);
        setRequired(complainantHouseNumber, useHouse);
        setRequired(complainantStreetName, useHouse);
        setRequired(complainantLotNumber, useLotBlock);
        setRequired(complainantBlockNumber, useLotBlock);
        setRequired(complainantPhaseNumber, useLotBlock);
    };

    const isVisibleField = (field) => {
        if (!field || field.disabled) return false;
        return !field.closest(".d-none");
    };

    const validateIncidentDate = () => {
        if (!incidentDateInput) {
            return true;
        }

        const now = getNow();
        const todayIso = toIsoDate(now);
        const todayDisplay = now.toLocaleDateString(undefined, {
            year: "numeric",
            month: "long",
            day: "numeric",
        });
        const oldestAllowed = getOldestAllowed();
        const oldestIso = toIsoDate(oldestAllowed);
        const oldestDisplay = oldestAllowed.toLocaleDateString(undefined, {
            year: "numeric",
            month: "long",
            day: "numeric",
        });
        const value = String(incidentDateInput.value || "").trim();
        const isFuture = value !== "" && value > todayIso;
        const isTooOld = value !== "" && value < oldestIso;

        if (isFuture) {
            const msg = `Incorrect Input. Date must be on or before ${todayDisplay}`;
            incidentDateInput.setCustomValidity(msg);
            if (incidentDateError) {
                incidentDateError.textContent = msg;
                incidentDateError.classList.remove("d-none");
            }
            return false;
        }

        if (isTooOld) {
            const msg = `Incorrect Input. Date must be on or after ${oldestDisplay}`;
            incidentDateInput.setCustomValidity(msg);
            if (incidentDateError) {
                incidentDateError.textContent = msg;
                incidentDateError.classList.remove("d-none");
            }
            return false;
        }

        incidentDateInput.setCustomValidity("");
        if (incidentDateError) {
            incidentDateError.textContent = "";
            incidentDateError.classList.add("d-none");
        }
        return true;
    };

    const validateIncidentTime = () => {
        if (!incidentTimeInput) {
            return true;
        }
        const dateValue = String(incidentDateInput?.value || "").trim();
        const timeValue = String(incidentTimeInput.value || "").trim();
        const now = getNow();
        const todayIso = toIsoDate(now);
        const currentTime = toTimeValue(now);

        incidentTimeInput.min = "";
        incidentTimeInput.max = dateValue === todayIso ? currentTime : "";

        if (timeValue === "") {
            incidentTimeInput.setCustomValidity("");
            return true;
        }

        if (dateValue === todayIso && timeValue > currentTime) {
            incidentTimeInput.setCustomValidity(`Time must be on or before ${currentTime} for incidents dated today.`);
            return false;
        }

        incidentTimeInput.setCustomValidity("");
        return true;
    };

    const syncIncidentBounds = () => {
        if (!incidentDateInput) return;
        const now = getNow();
        const oldestAllowed = getOldestAllowed();
        incidentDateInput.max = toIsoDate(now);
        incidentDateInput.min = toIsoDate(oldestAllowed);
        validateIncidentTime();
    };

    const normalizePhoneValue = (input) => {
        if (!input) return "";
        let digits = String(input.value || "").replace(/\D/g, "");
        if (digits.length === 10 && digits.startsWith("9")) {
            digits = `0${digits}`;
        }
        digits = digits.slice(0, 11);
        if (input.value !== digits) {
            input.value = digits;
        }
        return digits;
    };

    const syncPhoneValidation = (input) => {
        if (!input) return true;
        const value = normalizePhoneValue(input);
        if (value === "") {
            input.setCustomValidity("");
            renderIfTouched(input);
            return true;
        }
        const isValid = /^09\d{9}$/.test(value);
        input.setCustomValidity(isValid ? "" : "Contact number must be in the format 09XXXXXXXXX.");
        renderIfTouched(input);
        return isValid;
    };

    const updateState = () => {
        if (isSubmitting) {
            submitBtn.disabled = true;
            return;
        }
        applyAddressSystem();
        syncIncidentBounds();
        validateIncidentDate();
        validateIncidentTime();
        phoneInputs.forEach((input) => syncPhoneValidation(input));
        form.querySelectorAll("input, select, textarea").forEach((field) => {
            if (touchedFields.has(field) && isVisibleField(field)) {
                renderValidity(field);
            }
        });
        const hasInvalidVisibleRequired = Array.from(form.elements || []).some((field) => {
            if (!isVisibleField(field) || !field.required) return false;
            return !field.checkValidity();
        });
        submitBtn.disabled = hasInvalidVisibleRequired;
    };

    form.addEventListener("input", updateState);
    form.addEventListener("change", updateState);
    complainantAddressSystem?.addEventListener("change", updateState);
    incidentDateInput?.addEventListener("input", updateState);
    incidentDateInput?.addEventListener("change", updateState);
    incidentDateInput?.addEventListener("keyup", validateIncidentDate);
    incidentDateInput?.addEventListener("blur", validateIncidentDate);
    incidentDateInput?.addEventListener("invalid", validateIncidentDate);
    incidentTimeInput?.addEventListener("input", updateState);
    incidentTimeInput?.addEventListener("change", updateState);
    incidentTimeInput?.addEventListener("blur", () => {
        touchedFields.add(incidentTimeInput);
        renderValidity(incidentTimeInput);
    });

    form.querySelectorAll("input, select, textarea").forEach((field) => {
        field.addEventListener("input", () => {
            touchedFields.add(field);
            if (isVisibleField(field)) {
                renderValidity(field);
            }
        });
        field.addEventListener("change", () => {
            touchedFields.add(field);
            if (isVisibleField(field)) {
                renderValidity(field);
            }
        });
        field.addEventListener("blur", () => {
            touchedFields.add(field);
            if (isVisibleField(field)) {
                renderValidity(field);
            }
        });
    });

    phoneInputs.forEach((input) => {
        input.addEventListener("keypress", (event) => {
            if (event.ctrlKey || event.metaKey || event.altKey) return;
            if (event.key.length === 1 && !/^\d$/.test(event.key)) {
                event.preventDefault();
            }
        });
        input.addEventListener("paste", (event) => {
            const text = (event.clipboardData || window.clipboardData).getData("text");
            if (text && !/^\d+$/.test(text.replace(/\s+/g, ""))) {
                event.preventDefault();
            }
        });
        input.addEventListener("input", () => {
            syncPhoneValidation(input);
            updateState();
        });
        input.addEventListener("blur", () => {
            syncPhoneValidation(input);
            updateState();
        });
    });

    form.addEventListener("submit", (event) => {
        if (!validateIncidentDate() || !validateIncidentTime()) {
            event.preventDefault();
            if (!validateIncidentDate()) {
                incidentDateInput?.focus();
            } else {
                incidentTimeInput?.focus();
            }
            updateState();
            return;
        }

        let phonesValid = true;
        phoneInputs.forEach((input) => {
            if (!syncPhoneValidation(input)) {
                phonesValid = false;
            }
        });
        if (!phonesValid || !form.checkValidity()) {
            event.preventDefault();
            renderAllValidity();
            updateState();
            return;
        }

        isSubmitting = true;
        submitBtn.disabled = true;
    });

    syncIncidentBounds();
    updateState();

    window.addEventListener("pageshow", () => {
        isSubmitting = false;
        updateState();
    });

    const feedbackType = String(feedbackData?.dataset.feedbackType || "").trim();
    const feedbackMessage = String(feedbackData?.dataset.feedbackMessage || "").trim();
    if (feedbackType && feedbackMessage && window.UniversalModal) {
        const title = feedbackType === "success" ? "Complaint Submitted" : "Submission Failed";
        const buttonClass = feedbackType === "success" ? "btn btn-primary" : "btn btn-danger";
        window.UniversalModal.open({
            title,
            message: feedbackMessage,
            buttons: [{ label: "OK", class: buttonClass }],
        });
    }
});
