document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("blotterForm");
    const submitBtn = document.getElementById("blotterSubmit");
    const inputMethod = document.getElementById("narrativeInputMethod");
    const textWrapper = document.getElementById("narrativeTextWrapper");
    const fileWrapper = document.getElementById("narrativeFileWrapper");
    const narrativeText = form?.querySelector('textarea[name="narrative_report"]');
    const fileInput = document.getElementById("narrativeFileInput");
    const uploadBox = document.getElementById("narrativeUploadBox");
    const fileNameEl = document.getElementById("narrativeFileName");
    const confirmSubmitModalEl = document.getElementById("confirmSubmitModal");
    const successSubmitModalEl = document.getElementById("successSubmitModal");
    const confirmSubmitBtn = document.getElementById("confirmSubmitBtn");
    const complainantAddressSystem = document.getElementById("complainantAddressSystem");
    const complainantHouseWrapper = document.getElementById("complainantHouseSystemWrapper");
    const complainantLotWrapper = document.getElementById("complainantLotBlockSystemWrapper");
    const complainantHouseNumber = document.getElementById("complainantHouseNumber");
    const complainantStreetName = document.getElementById("complainantStreetName");
    const complainantLotNumber = document.getElementById("complainantLotNumber");
    const complainantBlockNumber = document.getElementById("complainantBlockNumber");
    const complainantPhaseNumber = document.getElementById("complainantPhaseNumber");

    const respondentAddressSystem = document.getElementById("respondentAddressSystem");
    const respondentHouseWrapper = document.getElementById("respondentHouseSystemWrapper");
    const respondentLotWrapper = document.getElementById("respondentLotBlockSystemWrapper");
    const respondentHouseNumber = document.getElementById("respondentHouseNumber");
    const respondentStreetName = document.getElementById("respondentStreetName");
    const respondentLotNumber = document.getElementById("respondentLotNumber");
    const respondentBlockNumber = document.getElementById("respondentBlockNumber");
    const respondentPhaseNumber = document.getElementById("respondentPhaseNumber");
    const phoneInputs = form?.querySelectorAll('input[name="complainant_contact_number"], input[name="respondent_contact_number"]') || [];
    const dateFiledInput = form?.querySelector('input[name="date_filed"]');
    const timeFiledInput = form?.querySelector('input[name="time_filed"]');
    if (!form || !submitBtn) return;

    const confirmModal = confirmSubmitModalEl ? new bootstrap.Modal(confirmSubmitModalEl) : null;
    const successModal = successSubmitModalEl ? new bootstrap.Modal(successSubmitModalEl) : null;
    let submitConfirmed = false;

    const setNarrativeMode = () => {
        const mode = inputMethod?.value || "text";
        const useFile = mode === "file";

        textWrapper?.classList.toggle("d-none", useFile);
        fileWrapper?.classList.toggle("d-none", !useFile);

        if (narrativeText) narrativeText.required = !useFile;
        if (fileInput) fileInput.required = useFile;

        if (useFile && narrativeText) {
            narrativeText.value = "";
            narrativeText.setCustomValidity("");
        }
        if (!useFile && fileInput) {
            fileInput.setCustomValidity("");
        }
    };

    const updateFileName = () => {
        if (!fileInput || !fileNameEl) return;
        const file = fileInput.files && fileInput.files[0];
        if (!file) {
            fileNameEl.textContent = "";
            fileNameEl.classList.add("d-none");
            return;
        }
        fileNameEl.textContent = `Selected file: ${file.name}`;
        fileNameEl.classList.remove("d-none");
    };

    const setRequired = (el, on) => {
        if (!el) return;
        if (on) el.setAttribute("required", "required");
        else el.removeAttribute("required");
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
        const v = input.validity;
        if (v.valid) return "";
        if (v.valueMissing) return "This field is required.";
        if (v.typeMismatch) return "Please enter a valid value.";
        if (v.tooShort) return `Please enter at least ${input.minLength} characters.`;
        if (v.tooLong) return `Please enter no more than ${input.maxLength} characters.`;
        if (v.patternMismatch) return input.title || "Please match the requested format.";
        if (v.rangeUnderflow) return `Value must be at least ${input.min}.`;
        if (v.rangeOverflow) return `Value must be at most ${input.max}.`;
        if (v.stepMismatch) return "Please enter a valid value.";
        return input.validationMessage || "Please enter a valid value.";
    };

    const renderValidity = (input) => {
        if (!input) return;
        const message = getValidationMessage(input);
        const feedback = ensureFeedbackEl(input);
        if (message) {
            input.classList.add("is-invalid");
            if (feedback) feedback.textContent = message;
        } else {
            input.classList.remove("is-invalid");
            if (feedback) feedback.textContent = "";
        }
    };

    const touchedFields = new WeakSet();

    const renderAllValidity = () => {
        form.querySelectorAll("input, select, textarea").forEach((el) => {
            if (el.disabled) return;
            renderValidity(el);
        });
    };

    const setWrapperState = (wrapper, show) => {
        if (!wrapper) return;
        wrapper.classList.toggle("d-none", !show);
        wrapper.querySelectorAll("input, select").forEach((el) => {
            el.disabled = !show;
            if (!show) {
                el.value = "";
                el.setCustomValidity("");
                el.classList.remove("is-invalid");
                const feedback = el.nextElementSibling;
                if (feedback && feedback.classList.contains("invalid-feedback")) {
                    feedback.textContent = "";
                }
            }
        });
    };

    const applyAddressSystem = (systemSelect, houseWrapper, lotWrapper, houseFields, lotFields) => {
        const mode = String(systemSelect?.value || "");
        const isHouse = mode === "house";
        const isLot = mode === "lot_block";

        setWrapperState(houseWrapper, isHouse);
        setWrapperState(lotWrapper, isLot);

        houseFields.forEach((f) => setRequired(f, isHouse));
        lotFields.forEach((f) => setRequired(f, isLot));
    };

    const isVisibleField = (el) => {
        if (!el) return false;
        if (el.disabled) return false;
        if (el.closest(".d-none")) return false;
        return true;
    };

    const hasInvalidRequiredVisibleFields = () => {
        const fields = Array.from(form.elements || []);
        return fields.some((el) => {
            if (!isVisibleField(el)) return false;
            if (!el.required) return false;
            return !el.checkValidity();
        });
    };

    const updateState = () => {
        setNarrativeMode();
        applyAddressSystem(
            complainantAddressSystem,
            complainantHouseWrapper,
            complainantLotWrapper,
            [complainantHouseNumber, complainantStreetName],
            [complainantLotNumber, complainantBlockNumber, complainantPhaseNumber]
        );
        applyAddressSystem(
            respondentAddressSystem,
            respondentHouseWrapper,
            respondentLotWrapper,
            [respondentHouseNumber, respondentStreetName],
            [respondentLotNumber, respondentBlockNumber, respondentPhaseNumber]
        );
        setRequired(complainantAddressSystem, true);
        setRequired(respondentAddressSystem, true);
        submitBtn.disabled = hasInvalidRequiredVisibleFields();
        // Only show errors for fields the user has touched.
        form.querySelectorAll("input, select, textarea").forEach((el) => {
            if (el.disabled) return;
            if (touchedFields.has(el)) {
                renderValidity(el);
            }
        });
    };

    const setFiledDateTime = () => {
        const now = new Date();
        if (dateFiledInput) {
            const yyyy = now.getFullYear();
            const mm = String(now.getMonth() + 1).padStart(2, "0");
            const dd = String(now.getDate()).padStart(2, "0");
            dateFiledInput.value = `${yyyy}-${mm}-${dd}`;
        }
        if (timeFiledInput) {
            const hh = String(now.getHours()).padStart(2, "0");
            const min = String(now.getMinutes()).padStart(2, "0");
            timeFiledInput.value = `${hh}:${min}`;
        }
    };

    const normalizePhoneValue = (input) => {
        if (!input) return "";
        const digits = String(input.value || "").replace(/\D/g, "");
        const trimmed = digits.slice(0, 11);
        if (input.value !== trimmed) {
            input.value = trimmed;
        }
        return trimmed;
    };

    const syncPhoneValidation = (input) => {
        if (!input) return;
        const value = normalizePhoneValue(input);
        if (value === "") {
            input.setCustomValidity("");
            renderValidity(input);
            return;
        }
        const isValid = /^09\d{9}$/.test(value);
        input.setCustomValidity(isValid ? "" : "Contact number must be in the format 09XXXXXXXXX.");
        if (isValid) {
            input.classList.remove("is-invalid");
            const feedback = ensureFeedbackEl(input);
            if (feedback) feedback.textContent = "";
        } else {
            renderValidity(input);
        }
    };

    inputMethod?.addEventListener("change", updateState);
    form.querySelectorAll("input, select, textarea").forEach((el) => {
        el.addEventListener("input", () => {
            touchedFields.add(el);
            renderValidity(el);
        });
        el.addEventListener("change", () => {
            touchedFields.add(el);
            renderValidity(el);
        });
        el.addEventListener("blur", () => {
            touchedFields.add(el);
            renderValidity(el);
        });
    });
    phoneInputs.forEach((input) => {
        input.addEventListener("keypress", (e) => {
            if (e.ctrlKey || e.metaKey || e.altKey) return;
            const key = e.key;
            if (key.length === 1 && !/^\d$/.test(key)) {
                e.preventDefault();
            }
        });
        input.addEventListener("paste", (e) => {
            const text = (e.clipboardData || window.clipboardData).getData("text");
            if (text && !/^\d+$/.test(text)) {
                e.preventDefault();
            }
        });
        input.addEventListener("input", () => {
            syncPhoneValidation(input);
    updateState();
});
        input.addEventListener("blur", () => syncPhoneValidation(input));
    });

    uploadBox?.addEventListener("click", () => fileInput?.click());
    uploadBox?.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            fileInput?.click();
        }
    });

    fileInput?.addEventListener("change", () => {
        updateFileName();
        updateState();
    });

    complainantAddressSystem?.addEventListener("change", updateState);
    respondentAddressSystem?.addEventListener("change", updateState);

    uploadBox?.addEventListener("dragover", (e) => {
        e.preventDefault();
        uploadBox.classList.add("drag-over");
    });
    uploadBox?.addEventListener("dragleave", () => {
        uploadBox.classList.remove("drag-over");
    });
    uploadBox?.addEventListener("drop", (e) => {
        e.preventDefault();
        uploadBox.classList.remove("drag-over");
        if (!fileInput || !e.dataTransfer?.files?.length) return;
        fileInput.files = e.dataTransfer.files;
        updateFileName();
        updateState();
    });

    form.addEventListener("input", updateState);
    form.addEventListener("change", updateState);
    form.addEventListener("submit", (e) => {
        if (!submitConfirmed) {
            e.preventDefault();
            e.stopPropagation();
            updateState();
            if (!form.checkValidity()) {
                renderAllValidity();
                return;
            }
            confirmModal?.show();
            return;
        }
        updateState();
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
            renderAllValidity();
        }
    });

    confirmSubmitBtn?.addEventListener("click", () => {
        submitConfirmed = true;
        confirmModal?.hide();
        form.requestSubmit();
    });

    setNarrativeMode();
    setFiledDateTime();
    if (timeFiledInput) {
        setInterval(setFiledDateTime, 1000);
    }
    phoneInputs.forEach((input) => syncPhoneValidation(input));
    updateState();

    const params = new URLSearchParams(window.location.search);
    if (params.get("success") === "1") {
        successModal?.show();
    }
});
