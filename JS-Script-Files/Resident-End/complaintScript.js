document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("complaintForm") || document.querySelector("form");
    const submitBtn = form?.querySelector(".submit-btn");
    const submitBtnLabel = submitBtn?.querySelector(".submit-btn-label") || null;
    const incidentDateInput = document.getElementById("incidentDate");
    const incidentDateError = document.getElementById("incidentDateError");
    const incidentTimeInput = document.getElementById("incidentTime");
    const incidentTimeProxy = document.getElementById("incidentTimeProxy");
    const incidentTimeModalEl = document.getElementById("complaintTimeModal");
    const incidentTimePicker = document.getElementById("incidentTimePicker");
    const incidentTimePreview = document.getElementById("incidentTimePreview");
    const incidentTimeApplyBtn = document.getElementById("incidentTimeApplyBtn");
    const incidentTimeClearBtn = document.getElementById("incidentTimeClearBtn");
    const incidentTimeUseNowBtn = document.getElementById("incidentTimeUseNow");
    const incidentAreaInput = document.getElementById("incidentAreaNumber");
    const incidentAreaProxy = document.getElementById("incidentAreaNumberDisplay");
    const incidentAreaModalEl = document.getElementById("complaintAreaHelpModal");
    const incidentAreaOptionButtons = Array.from(document.querySelectorAll("[data-area-value]"));
    const feedbackData = document.getElementById("complaintFeedbackData");
    const recaptchaTokenInput = document.getElementById("complaintRecaptchaToken");
    const complaintTypeConfig = (() => {
        try {
            return JSON.parse(feedbackData?.dataset.complaintTypeConfig || "{}");
        } catch (error) {
            return {};
        }
    })();
    const recaptchaEnabled = String(feedbackData?.dataset.recaptchaEnabled || "") === "1";
    const recaptchaSiteKey = String(feedbackData?.dataset.recaptchaSiteKey || "").trim();
    const recaptchaAction = String(feedbackData?.dataset.recaptchaAction || "resident_complaint_submit").trim() || "resident_complaint_submit";
    const complainantAddressSystem = document.getElementById("complainantAddressSystem");
    const complainantHouseWrapper = document.getElementById("complainantHouseSystemWrapper");
    const complainantLotWrapper = document.getElementById("complainantLotBlockSystemWrapper");
    const complainantHouseNumber = document.getElementById("complainantHouseNumber");
    const complainantStreetName = document.getElementById("complainantStreetName");
    const complainantLotNumber = document.getElementById("complainantLotNumber");
    const complainantBlockNumber = document.getElementById("complainantBlockNumber");
    const complainantPhaseNumber = document.getElementById("complainantPhaseNumber");
    const natureOfComplaint = document.getElementById("natureOfComplaint");
    const natureOther = document.getElementById("natureOther");
    const natureOtherWrap = document.getElementById("natureOtherWrap");
    const natureOtherAsterisk = document.getElementById("natureOtherAsterisk");
    const complaintTypeDynamicFields = document.getElementById("complaintTypeDynamicFields");
    const hasWitnesses = document.getElementById("hasWitnesses");
    const witnessRowsWrap = document.getElementById("witnessRowsWrap");
    const witnessRows = Array.from(document.querySelectorAll("[data-witness-row]"));
    const addWitnessBtn = document.getElementById("addWitnessBtn");
    const witnessRemoveButtons = Array.from(document.querySelectorAll("[data-witness-remove-btn]"));
    const complaintAttachmentSection = document.getElementById("complaintAttachmentSection");
    const complaintAttachmentCloseButtons = Array.from(document.querySelectorAll("[data-attachment-remove-btn]"));
    const attachmentRows = Array.from(document.querySelectorAll("[data-complaint-attachment-row]"));
    const addComplaintAttachmentBtn = document.getElementById("addComplaintAttachmentBtn");
    const phoneInputs = form?.querySelectorAll('input[name="complainant_contact_number"], input[name="witness_contact_number[]"]') || [];
    const incidentTimeModal = incidentTimeModalEl && window.bootstrap ? new bootstrap.Modal(incidentTimeModalEl) : null;
    const incidentAreaModal = incidentAreaModalEl && window.bootstrap ? new bootstrap.Modal(incidentAreaModalEl) : null;
    let isSubmitting = false;
    let recaptchaSubmitPending = false;
    let renderedComplaintType = "";
    const touchedFields = new WeakSet();

    if (!form || !submitBtn) {
        return;
    }

    const defaultSubmitLabel = String(
        submitBtn.dataset.defaultLabel || submitBtnLabel?.textContent || submitBtn.textContent || "Submit"
    ).trim() || "Submit";
    const loadingSubmitLabel = String(submitBtn.dataset.loadingLabel || "Submitting...").trim() || "Submitting...";

    const setSubmittingState = (submitting) => {
        isSubmitting = submitting === true;
        form.setAttribute("aria-busy", isSubmitting ? "true" : "false");
        submitBtn.classList.toggle("is-loading", isSubmitting);
        submitBtn.disabled = isSubmitting;

        if (submitBtnLabel) {
            submitBtnLabel.textContent = isSubmitting ? loadingSubmitLabel : defaultSubmitLabel;
            return;
        }

        submitBtn.textContent = isSubmitting ? loadingSubmitLabel : defaultSubmitLabel;
    };

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
    const formatTimeDisplay = (value) => {
        const raw = String(value || "").trim();
        const match = raw.match(/^(\d{2}):(\d{2})$/);
        if (!match) return "";
        const sample = new Date();
        sample.setHours(Number(match[1]), Number(match[2]), 0, 0);
        return sample.toLocaleTimeString(undefined, {
            hour: "numeric",
            minute: "2-digit",
        });
    };
    const getOldestAllowed = () => {
        const oldest = getNow();
        oldest.setMonth(oldest.getMonth() - 6);
        return oldest;
    };
    const escAttr = (value) => String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/"/g, "&quot;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;");

    const setRequired = (element, isRequired) => {
        if (!element) return;
        if (isRequired) {
            element.setAttribute("required", "required");
        } else {
            element.removeAttribute("required");
        }
    };

    const isPlus63PhoneInput = (input) => String(input?.dataset.phoneUi || "").trim() === "plus63";
    const getFeedbackAnchor = (input) => {
        if (!input) return null;
        const wrapper = input.closest(".phone-input-group");
        if (wrapper && wrapper.contains(input)) {
            return wrapper;
        }
        return input;
    };
    const getExistingFeedbackEl = (input) => {
        if (!input) return null;
        const anchor = getFeedbackAnchor(input);
        const anchorFeedback = anchor?.nextElementSibling;
        if (anchorFeedback && anchorFeedback.classList.contains("invalid-feedback")) {
            return anchorFeedback;
        }
        const inputFeedback = input.nextElementSibling;
        if (inputFeedback && inputFeedback.classList.contains("invalid-feedback")) {
            return inputFeedback;
        }
        return null;
    };
    const ensureFeedbackEl = (input) => {
        if (!input) return null;
        let feedback = getExistingFeedbackEl(input);
        if (feedback) {
            return feedback;
        }
        feedback = document.createElement("div");
        feedback.className = "invalid-feedback";
        const anchor = getFeedbackAnchor(input);
        anchor?.insertAdjacentElement("afterend", feedback);
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

    const isVisibleField = (field) => {
        if (!field || field.disabled) return false;
        if (field.type === "hidden") return false;
        return !field.closest(".d-none");
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
                const feedback = getExistingFeedbackEl(field);
                if (feedback) {
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

    const syncNatureOfComplaint = () => {
        const isOther = String(natureOfComplaint?.value || "").trim() === "Other";
        if (natureOtherWrap) {
            natureOtherWrap.classList.toggle("d-none", !isOther);
        }
        if (natureOther) {
            natureOther.disabled = !isOther;
            setRequired(natureOther, isOther);
            if (!isOther) {
                natureOther.value = "";
                natureOther.setCustomValidity("");
                natureOther.classList.remove("is-invalid");
            }
        }
        natureOtherAsterisk?.classList.toggle("d-none", !isOther);
    };

    const bindFieldValidation = (field) => {
        if (!field) return;
        field.addEventListener("input", () => {
            touchedFields.add(field);
            if (isVisibleField(field)) {
                renderValidity(field);
            }
            updateState();
        });
        field.addEventListener("change", () => {
            touchedFields.add(field);
            if (isVisibleField(field)) {
                renderValidity(field);
            }
            updateState();
        });
        field.addEventListener("blur", () => {
            touchedFields.add(field);
            if (isVisibleField(field)) {
                renderValidity(field);
            }
        });
    };

    const renderComplaintTypeFields = () => {
        if (!complaintTypeDynamicFields || !natureOfComplaint) return;
        const selectedType = String(natureOfComplaint.value || "").trim();
        const definition = complaintTypeConfig?.[selectedType];
        const fields = Array.isArray(definition?.fields) ? definition.fields : [];

        if (!selectedType || !fields.length) {
            complaintTypeDynamicFields.innerHTML = "";
            complaintTypeDynamicFields.classList.add("d-none");
            renderedComplaintType = "";
            return;
        }

        if (renderedComplaintType === selectedType) {
            return;
        }

        const renderField = (field, forceFullWidth = false) => {
            const name = String(field.name || "");
            const label = String(field.label || name);
            const required = !!field.required;
            const asterisk = required ? ' <span class="required-asterisk">*</span>' : "";
            const wrapperClass = forceFullWidth ? ' class="full-width"' : "";

            if (String(field.type || "text") === "select") {
                const options = Array.isArray(field.options) ? field.options : [];
                return `
                    <div${wrapperClass}>
                        <label class="top-label" for="${escAttr(name)}">${label}${asterisk}</label>
                        <select class="form-select" id="${escAttr(name)}" name="${escAttr(name)}" data-dynamic-complaint-field="true">
                            <option value="">Select</option>
                            ${options.map((option) => `<option value="${escAttr(option)}">${option}</option>`).join("")}
                        </select>
                    </div>
                `;
            }

            if (String(field.type || "text") === "textarea") {
                return `
                    <div class="full-width">
                        <label class="top-label" for="${escAttr(name)}">${label}${asterisk}</label>
                        <textarea id="${escAttr(name)}" name="${escAttr(name)}" rows="4" placeholder="${escAttr(field.placeholder || "")}" data-dynamic-complaint-field="true"></textarea>
                    </div>
                `;
            }

            return `
                <div${wrapperClass}>
                    <label class="top-label" for="${escAttr(name)}">${label}${asterisk}</label>
                    <input type="text" id="${escAttr(name)}" name="${escAttr(name)}" placeholder="${escAttr(field.placeholder || "")}" data-dynamic-complaint-field="true">
                </div>
            `;
        };

        const htmlParts = [];
        for (let index = 0; index < fields.length; index += 2) {
            const field = fields[index];
            const nextField = fields[index + 1] || null;
            const fieldType = String(field?.type || "text");

            if (fieldType === "textarea") {
                htmlParts.push(`<div class="form-row">${renderField(field, true)}</div>`);
                continue;
            }

            if (!nextField) {
                htmlParts.push(`<div class="form-row">${renderField(field, true)}</div>`);
                continue;
            }

            if (String(nextField?.type || "text") === "textarea") {
                htmlParts.push(`<div class="form-row">${renderField(field, true)}</div>`);
                htmlParts.push(`<div class="form-row">${renderField(nextField, true)}</div>`);
                continue;
            }

            htmlParts.push(`
                <div class="form-row two-col-row">
                    ${renderField(field)}
                    ${renderField(nextField)}
                </div>
            `);
        }

        complaintTypeDynamicFields.innerHTML = htmlParts.join("");
        complaintTypeDynamicFields.classList.remove("d-none");

        complaintTypeDynamicFields.querySelectorAll("[data-dynamic-complaint-field='true']").forEach((field) => {
            const fieldName = String(field.getAttribute("name") || "");
            const fieldDef = fields.find((item) => String(item.name || "") === fieldName);
            setRequired(field, !!fieldDef?.required);
            bindFieldValidation(field);
        });

        renderedComplaintType = selectedType;
    };

    const ensureDefaultIncidentDate = () => {
        if (!incidentDateInput) return;
        if (String(incidentDateInput.value || "").trim() === "") {
            incidentDateInput.value = toIsoDate(getNow());
        }
    };

    const validateIncidentDate = () => {
        if (!incidentDateInput) return true;
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

    const updateTimePreview = (value) => {
        if (!incidentTimePreview) return;
        const formatted = formatTimeDisplay(value);
        incidentTimePreview.textContent = formatted ? `Selected: ${formatted}` : "No time selected yet.";
    };

    const syncIncidentTimeProxy = () => {
        if (!incidentTimeProxy || !incidentTimeInput) return;
        const formatted = formatTimeDisplay(incidentTimeInput.value);
        incidentTimeProxy.value = formatted;
        incidentTimeProxy.placeholder = "Select time";
        updateTimePreview(incidentTimeInput.value);
    };

    const findAreaOption = (value) => {
        return incidentAreaOptionButtons.find((button) => String(button.dataset.areaValue || "") === String(value || ""));
    };

    const syncIncidentAreaProxy = () => {
        if (!incidentAreaProxy || !incidentAreaInput) return;
        const option = findAreaOption(incidentAreaInput.value);
        incidentAreaProxy.value = option?.dataset.areaLabel || "";
        incidentAreaProxy.placeholder = "Select area";
    };

    const validateIncidentTime = () => {
        if (!incidentTimeInput) return true;
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

    const openTimeModal = () => {
        if (!incidentTimePicker || !incidentTimeModal) return;
        incidentTimePicker.value = String(incidentTimeInput?.value || "").trim();
        updateTimePreview(incidentTimePicker.value);
        incidentTimeModal.show();
    };

    const openAreaModal = () => {
        if (!incidentAreaModal) return;
        incidentAreaModal.show();
    };

    const applyPickedTime = () => {
        if (!incidentTimeInput || !incidentTimePicker) return;
        incidentTimeInput.value = String(incidentTimePicker.value || "").trim();
        touchedFields.add(incidentTimeInput);
        syncIncidentTimeProxy();
        validateIncidentTime();
        renderIfTouched(incidentTimeInput);
        updateState();
    };

    const useCurrentTime = () => {
        const nowValue = toTimeValue(getNow());
        if (incidentTimePicker) {
            incidentTimePicker.value = nowValue;
            updateTimePreview(nowValue);
        }
        if (incidentTimeInput) {
            incidentTimeInput.value = nowValue;
            touchedFields.add(incidentTimeInput);
        }
        syncIncidentTimeProxy();
        validateIncidentTime();
        updateState();
    };

    const normalizePhoneValue = (input) => {
        if (!input) return "";
        let digits = String(input.value || "").replace(/\D/g, "");
        if (isPlus63PhoneInput(input)) {
            if (digits.startsWith("63")) {
                digits = digits.slice(2);
            }
            if (digits.startsWith("0")) {
                digits = digits.slice(1);
            }
            digits = digits.slice(0, 10);
        } else {
            if (digits.length === 10 && digits.startsWith("9")) {
                digits = `0${digits}`;
            }
            digits = digits.slice(0, 11);
        }
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
        const isValid = isPlus63PhoneInput(input)
            ? /^9\d{9}$/.test(value)
            : /^09\d{9}$/.test(value);
        const message = isPlus63PhoneInput(input)
            ? "Contact number must use +63 followed by 10 digits in the format 9XXXXXXXXX."
            : "Contact number must be in the format 09XXXXXXXXX.";
        input.setCustomValidity(isValid ? "" : message);
        renderIfTouched(input);
        return isValid;
    };

    const executeComplaintRecaptcha = async () => {
        if (!recaptchaEnabled) {
            return "";
        }
        if (!(window.grecaptcha && typeof window.grecaptcha.execute === "function")) {
            throw new Error("Security check is still loading. Please try again.");
        }

        await new Promise((resolve) => {
            window.grecaptcha.ready(resolve);
        });

        const token = await window.grecaptcha.execute(recaptchaSiteKey, {
            action: recaptchaAction,
        });
        if (String(token || "").trim() === "") {
            throw new Error("Security verification failed. Please try again.");
        }

        return token;
    };

    const bindDropzone = (inputEl) => {
        if (!inputEl) return;
        const zone = document.querySelector(`.upload-dropzone[data-upload-input="${inputEl.id}"]`);
        const meta = document.getElementById(inputEl.id + "Meta");
        if (!zone) return;
        const defaultMetaText = meta ? meta.textContent : "";
        if (meta && !meta.dataset.defaultText) {
            meta.dataset.defaultText = defaultMetaText;
        }
        const acceptTokens = String(inputEl.getAttribute("accept") || "")
            .split(",")
            .map((token) => token.trim().toLowerCase())
            .filter(Boolean);

        const fileMatchesAccept = (file) => {
            if (!file || !acceptTokens.length) return true;
            const fileName = String(file.name || "");
            const fileExt = fileName.includes(".")
                ? "." + fileName.split(".").pop().toLowerCase()
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

        const resetInputSelection = () => {
            inputEl.value = "";
            if (meta) {
                meta.textContent = defaultMetaText;
            }
        };

        const validateSelectedFile = () => {
            const file = inputEl.files && inputEl.files.length ? inputEl.files[0] : null;
            if (!file) return true;
            if (fileMatchesAccept(file)) return true;
            resetInputSelection();
            alert(defaultMetaText || "Please upload a supported image file.");
            updateState();
            return false;
        };

        const setMeta = () => {
            if (!meta) return;
            const files = inputEl.files ? Array.from(inputEl.files) : [];
            meta.textContent = files.length === 1 ? files[0].name : defaultMetaText;
        };

        inputEl.addEventListener("change", () => {
            if (!validateSelectedFile()) return;
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
            const files = event.dataTransfer?.files;
            if (!files?.length) return;
            const transfer = new DataTransfer();
            transfer.items.add(files[0]);
            inputEl.files = transfer.files;
            if (!validateSelectedFile()) return;
            setMeta();
            updateState();
        });
    };

    const resetAttachmentRow = (row) => {
        if (!row) return;
        row.classList.add("d-none");
        row.querySelectorAll('input[type="file"]').forEach((inputEl) => {
            inputEl.value = "";
            inputEl.disabled = true;
            inputEl.classList.remove("is-invalid");
            const meta = document.getElementById(inputEl.id + "Meta");
            if (meta) {
                meta.textContent = meta.dataset.defaultText || meta.textContent;
            }
        });
    };

    const syncAttachmentRows = () => {
        const visibleRows = attachmentRows.filter((row) => !row.classList.contains("d-none")).length;
        if (complaintAttachmentSection) {
            complaintAttachmentSection.classList.toggle("d-none", visibleRows === 0);
        }
        if (addComplaintAttachmentBtn) {
            addComplaintAttachmentBtn.disabled = visibleRows >= attachmentRows.length;
            addComplaintAttachmentBtn.classList.toggle("d-none", attachmentRows.length <= 1 || visibleRows >= attachmentRows.length);
            addComplaintAttachmentBtn.textContent = visibleRows === 0 ? "Add Image" : "Add Another Image";
        }
    };

    const syncWitnessRows = () => {
        const enabled = String(hasWitnesses?.value || "") === "Yes";
        if (witnessRowsWrap) {
            witnessRowsWrap.classList.toggle("d-none", !enabled);
        }

        const visibleWitnessCount = witnessRows.filter((row) => !row.classList.contains("d-none")).length;

        witnessRows.forEach((row, index) => {
            const isVisible = enabled && !row.classList.contains("d-none");
            row.querySelectorAll("input, select").forEach((field) => {
                field.disabled = !enabled || !isVisible;
                field.classList.remove("is-invalid");
            });
            const lastNameInput = row.querySelector('input[name="witness_last_name[]"]');
            const firstNameInput = row.querySelector('input[name="witness_first_name[]"]');
            const contactInput = row.querySelector('input[name="witness_contact_number[]"]');
            setRequired(lastNameInput, isVisible);
            setRequired(firstNameInput, isVisible);
            setRequired(contactInput, isVisible);
            if (!enabled && index > 0) {
                row.classList.add("d-none");
            }
        });

        if (enabled && witnessRows.length && visibleWitnessCount === 0) {
            witnessRows[0].classList.remove("d-none");
            witnessRows[0].querySelectorAll("input, select").forEach((field) => {
                field.disabled = false;
            });
        }

        const currentVisibleWitnessCount = witnessRows.filter((row) => !row.classList.contains("d-none")).length;
        if (addWitnessBtn) {
            addWitnessBtn.disabled = !enabled || currentVisibleWitnessCount >= witnessRows.length;
            addWitnessBtn.classList.toggle("d-none", !enabled || currentVisibleWitnessCount >= witnessRows.length);
        }
    };

    const resetWitnessRow = (row) => {
        if (!row) return;
        row.classList.add("d-none");
        row.querySelectorAll("input, select").forEach((field) => {
            if (field.tagName === "SELECT") {
                field.selectedIndex = 0;
            } else {
                field.value = "";
            }
            field.disabled = true;
            field.classList.remove("is-invalid");
        });
        const lastNameInput = row.querySelector('input[name="witness_last_name[]"]');
        const firstNameInput = row.querySelector('input[name="witness_first_name[]"]');
        const contactInput = row.querySelector('input[name="witness_contact_number[]"]');
        setRequired(lastNameInput, false);
        setRequired(firstNameInput, false);
        setRequired(contactInput, false);
    };

    const updateState = () => {
        if (isSubmitting) {
            submitBtn.disabled = true;
            return;
        }

        applyAddressSystem();
        syncNatureOfComplaint();
        renderComplaintTypeFields();
        syncWitnessRows();
        syncAttachmentRows();
        syncIncidentBounds();
        validateIncidentDate();
        validateIncidentTime();
        syncIncidentTimeProxy();
        syncIncidentAreaProxy();
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
    natureOfComplaint?.addEventListener("change", updateState);
    hasWitnesses?.addEventListener("change", updateState);
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

    form.querySelectorAll("input, select, textarea").forEach((field) => bindFieldValidation(field));

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
    });

    if (incidentTimeProxy) {
        incidentTimeProxy.addEventListener("click", openTimeModal);
        incidentTimeProxy.addEventListener("focus", () => {
            incidentTimeProxy.blur();
            openTimeModal();
        });
    }

    if (incidentAreaProxy) {
        incidentAreaProxy.addEventListener("click", openAreaModal);
        incidentAreaProxy.addEventListener("focus", () => {
            incidentAreaProxy.blur();
            openAreaModal();
        });
    }

    incidentAreaOptionButtons.forEach((button) => {
        button.addEventListener("click", () => {
            if (!incidentAreaInput || !incidentAreaProxy) return;
            incidentAreaInput.value = String(button.dataset.areaValue || "").trim();
            incidentAreaProxy.value = String(button.dataset.areaLabel || "").trim();
            touchedFields.add(incidentAreaProxy);
            incidentAreaProxy.setCustomValidity("");
            renderIfTouched(incidentAreaProxy);
            incidentAreaModal?.hide();
            updateState();
        });
    });

    incidentTimeUseNowBtn?.addEventListener("click", useCurrentTime);
    incidentTimePicker?.addEventListener("input", () => updateTimePreview(incidentTimePicker.value));
    incidentTimeApplyBtn?.addEventListener("click", () => {
        applyPickedTime();
        incidentTimeModal?.hide();
    });
    incidentTimeClearBtn?.addEventListener("click", () => {
        if (incidentTimePicker) incidentTimePicker.value = "";
        if (incidentTimeInput) incidentTimeInput.value = "";
        syncIncidentTimeProxy();
        validateIncidentTime();
        updateState();
    });

    addComplaintAttachmentBtn?.addEventListener("click", () => {
        const nextRow = attachmentRows.find((row) => row.classList.contains("d-none"));
        if (!nextRow) return;
        nextRow.classList.remove("d-none");
        if (complaintAttachmentSection) {
            complaintAttachmentSection.classList.remove("d-none");
        }
        nextRow.querySelectorAll("input").forEach((field) => {
            field.disabled = false;
        });
        syncAttachmentRows();
        updateState();
    });

    complaintAttachmentCloseButtons.forEach((button) => {
        button.addEventListener("click", () => {
            const row = button.closest("[data-complaint-attachment-row]");
            resetAttachmentRow(row);
            syncAttachmentRows();
            updateState();
        });
    });

    addWitnessBtn?.addEventListener("click", () => {
        const nextRow = witnessRows.find((row) => row.classList.contains("d-none"));
        if (!nextRow) return;
        nextRow.classList.remove("d-none");
        nextRow.querySelectorAll("input, select").forEach((field) => {
            field.disabled = false;
        });
        syncWitnessRows();
        updateState();
    });

    witnessRemoveButtons.forEach((button) => {
        button.addEventListener("click", () => {
            const row = button.closest("[data-witness-row]");
            const visibleRows = witnessRows.filter((item) => !item.classList.contains("d-none"));
            resetWitnessRow(row);
            if (visibleRows.length <= 1 && hasWitnesses) {
                hasWitnesses.value = "No";
            }
            syncWitnessRows();
            updateState();
        });
    });

    attachmentRows.forEach((row) => {
        row.querySelectorAll('input[type="file"]').forEach((input) => bindDropzone(input));
    });

    form.addEventListener("submit", (event) => {
        if (!validateIncidentDate() || !validateIncidentTime()) {
            event.preventDefault();
            if (!validateIncidentDate()) {
                incidentDateInput?.focus();
            } else {
                incidentTimeProxy?.focus();
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

        if (recaptchaEnabled && !recaptchaSubmitPending) {
            event.preventDefault();
            setSubmittingState(true);

            executeComplaintRecaptcha()
                .then((token) => {
                    if (recaptchaTokenInput) {
                        recaptchaTokenInput.value = token;
                    }
                    recaptchaSubmitPending = true;
                    if (typeof form.requestSubmit === "function") {
                        form.requestSubmit(submitBtn);
                    } else {
                        form.submit();
                    }
                })
                .catch((error) => {
                    setSubmittingState(false);
                    recaptchaSubmitPending = false;
                    updateState();

                    const message = error instanceof Error ? error.message : "Security verification failed. Please try again.";
                    if (window.UniversalModal) {
                        window.UniversalModal.open({
                            title: "Security Check Failed",
                            message,
                            buttons: [{ label: "OK", class: "btn btn-danger" }],
                        });
                        return;
                    }
                    alert(message);
                });
            return;
        }

        recaptchaSubmitPending = false;
        setSubmittingState(true);
    });

    ensureDefaultIncidentDate();
    syncIncidentBounds();
    syncNatureOfComplaint();
    renderComplaintTypeFields();
    syncWitnessRows();
    syncAttachmentRows();
    syncIncidentTimeProxy();
    updateState();

    window.addEventListener("pageshow", () => {
        setSubmittingState(false);
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
