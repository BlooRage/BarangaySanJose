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
    const blotterComplaintType = document.getElementById("blotterComplaintType");
    const blotterComplaintTypeOther = document.getElementById("blotterComplaintTypeOther");
    const blotterComplaintTypeOtherAsterisk = document.getElementById("blotterComplaintTypeOtherAsterisk");
    const incidentDetailsSection = document.getElementById("incidentDetailsSection");
    const complaintTypeOtherRow = document.getElementById("complaintTypeOtherRow");
    const phoneInputs = form?.querySelectorAll('input[name="complainant_contact_number"], input[name="respondent_contact_number"]') || [];
    const dateFiledInput = form?.querySelector('input[name="date_filed"]');
    const timeFiledInput = form?.querySelector('input[name="time_filed"]');
    const incidentDateInput = document.getElementById("incidentDate");
    const incidentTimeInput = document.getElementById("incidentTime");
    const incidentPlaceInput = document.getElementById("incidentPlace");
    const incidentDateError = document.getElementById("incidentDateError");
    const complainantSignatureCanvas = document.getElementById("complainantSignatureCanvas");
    const complainantSignatureData = document.getElementById("complainantSignatureData");
    const complainantSignatureError = document.getElementById("complainantSignatureError");
    const clearComplainantSignatureBtn = document.getElementById("clearComplainantSignature");
    const respondentSignatureCanvas = document.getElementById("respondentSignatureCanvas");
    const respondentSignatureData = document.getElementById("respondentSignatureData");
    const respondentSignatureError = document.getElementById("respondentSignatureError");
    const clearRespondentSignatureBtn = document.getElementById("clearRespondentSignature");
    const signatureSection = document.getElementById("signatureSection");
    const openComplainantSignatureFullscreenBtn = document.getElementById("openComplainantSignatureFullscreen");
    const openRespondentSignatureFullscreenBtn = document.getElementById("openRespondentSignatureFullscreen");
    const signatureFullscreenModalEl = document.getElementById("signatureFullscreenModal");
    const signatureFullscreenTitle = document.getElementById("signatureFullscreenLabel");
    const signatureFullscreenCanvas = document.getElementById("signatureFullscreenCanvas");
    const signatureFullscreenClearBtn = document.getElementById("signatureFullscreenClear");
    const signatureFullscreenSaveBtn = document.getElementById("signatureFullscreenSave");
    const narrativeSignatureModalEl = document.getElementById("narrativeSignatureModal");
    const openNarrativeSignatureModalBtn = document.getElementById("openNarrativeSignatureModal");
    const narrativeEditorLauncherRow = document.getElementById("narrativeEditorLauncherRow");
    if (!form || !submitBtn) return;

    const confirmModal = confirmSubmitModalEl ? new bootstrap.Modal(confirmSubmitModalEl) : null;
    const successModal = successSubmitModalEl ? new bootstrap.Modal(successSubmitModalEl) : null;
    const signatureFullscreenModal = signatureFullscreenModalEl ? new bootstrap.Modal(signatureFullscreenModalEl) : null;
    const narrativeSignatureModal = narrativeSignatureModalEl ? new bootstrap.Modal(narrativeSignatureModalEl) : null;
    let submitConfirmed = false;

    const setNarrativeMode = () => {
        const mode = inputMethod?.value || "";
        const useText = mode === "text";
        const useFile = mode === "file";

        textWrapper?.classList.toggle("d-none", !useText);
        fileWrapper?.classList.toggle("d-none", !useFile);
        signatureSection?.classList.toggle("d-none", !useText);
        narrativeEditorLauncherRow?.classList.toggle("d-none", !(useText || useFile));

        if (openNarrativeSignatureModalBtn) {
            openNarrativeSignatureModalBtn.textContent = useText
                ? "Open Narrative and Signatures"
                : useFile
                    ? "Open Narrative Attachment"
                    : "Open Narrative Editor";
        }

        if (narrativeText) narrativeText.required = useText;
        if (fileInput) fileInput.required = useFile;
        setRequired(complainantSignatureData, useText);
        setRequired(respondentSignatureData, useText);
        if (!useText) {
            complainantSignatureData?.setCustomValidity("");
            respondentSignatureData?.setCustomValidity("");
            complainantSignatureError?.classList.add("d-none");
            respondentSignatureError?.classList.add("d-none");
        }

        if (useFile && narrativeText) {
            narrativeText.value = "";
            narrativeText.setCustomValidity("");
        }
        if (!useFile && fileInput) {
            fileInput.setCustomValidity("");
        }

        toggleIncidentDetailsDisabled(useFile);
    };

    const incidentDetailFields = [
        incidentDateInput,
        incidentTimeInput,
        incidentPlaceInput,
        blotterComplaintType,
        blotterComplaintTypeOther
    ].filter(Boolean);

    const toggleIncidentDetailsDisabled = (disabled) => {
        incidentDetailsSection?.classList.toggle("opacity-50", disabled);
        complaintTypeOtherRow?.classList.toggle("d-none", disabled);

        incidentDetailFields.forEach((field) => {
            if (!field) return;

            if (!Object.prototype.hasOwnProperty.call(field.dataset, "originalRequired")) {
                field.dataset.originalRequired = field.required ? "1" : "0";
            }

            field.disabled = disabled;
            field.required = !disabled && field.dataset.originalRequired === "1";

            if (disabled) {
                field.setCustomValidity("");
                field.classList.remove("is-invalid");
                const feedback = ensureFeedbackEl(field);
                if (feedback) feedback.textContent = "";
            }
        });
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

    const syncBlotterComplaintType = () => {
        if (!blotterComplaintType || !blotterComplaintTypeOther) return;
        const isOther = String(blotterComplaintType.value || "").trim() === "Other";
        blotterComplaintTypeOther.disabled = !isOther;
        setRequired(blotterComplaintTypeOther, isOther);
        blotterComplaintTypeOtherAsterisk?.classList.toggle("d-none", !isOther);
        if (!isOther) {
            blotterComplaintTypeOther.value = "";
            blotterComplaintTypeOther.setCustomValidity("");
            blotterComplaintTypeOther.classList.remove("is-invalid");
            const feedback = ensureFeedbackEl(blotterComplaintTypeOther);
            if (feedback) feedback.textContent = "";
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

    const toIsoDate = (date) => {
        const yyyy = date.getFullYear();
        const mm = String(date.getMonth() + 1).padStart(2, "0");
        const dd = String(date.getDate()).padStart(2, "0");
        return `${yyyy}-${mm}-${dd}`;
    };

    const toTimeValue = (date) => {
        const hh = String(date.getHours()).padStart(2, "0");
        const min = String(date.getMinutes()).padStart(2, "0");
        return `${hh}:${min}`;
    };

    const initSignaturePad = (canvas, hiddenInput, errorEl, clearBtn) => {
        if (!canvas || !hiddenInput) return null;
        const ctx = canvas.getContext("2d");
        if (!ctx) return null;
        let drawing = false;
        let hasStroke = false;

        const paintBackground = () => {
            const rect = canvas.getBoundingClientRect();
            ctx.fillStyle = "#ffffff";
            ctx.fillRect(0, 0, rect.width, rect.height);
        };

        const setDrawingStyle = () => {
            ctx.lineWidth = 2;
            ctx.lineCap = "round";
            ctx.lineJoin = "round";
            ctx.strokeStyle = "#111827";
        };

        const drawFromData = (dataUrl) => {
            paintBackground();
            if (!dataUrl) {
                hasStroke = false;
                hiddenInput.value = "";
                hiddenInput.setCustomValidity("Signature is required.");
                if (errorEl) errorEl.classList.remove("d-none");
                return;
            }
            const rect = canvas.getBoundingClientRect();
            const img = new Image();
            img.onload = () => {
                ctx.drawImage(img, 0, 0, rect.width, rect.height);
                hasStroke = true;
                hiddenInput.value = canvas.toDataURL("image/png");
                hiddenInput.setCustomValidity("");
                if (errorEl) errorEl.classList.add("d-none");
            };
            img.src = dataUrl;
        };

        const resizeCanvas = () => {
            const ratio = window.devicePixelRatio || 1;
            const rect = canvas.getBoundingClientRect();
            const width = Math.max(1, Math.floor(rect.width * ratio));
            const height = Math.max(1, Math.floor(rect.height * ratio));
            const snapshot = hiddenInput.value || (hasStroke ? canvas.toDataURL("image/png") : "");
            canvas.width = width;
            canvas.height = height;
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.scale(ratio, ratio);
            setDrawingStyle();
            drawFromData(snapshot);
        };

        const point = (event) => {
            const rect = canvas.getBoundingClientRect();
            const source = event.touches && event.touches[0] ? event.touches[0] : event;
            return {
                x: source.clientX - rect.left,
                y: source.clientY - rect.top
            };
        };

        const start = (event) => {
            event.preventDefault();
            drawing = true;
            const p = point(event);
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
        };

        const move = (event) => {
            if (!drawing) return;
            event.preventDefault();
            const p = point(event);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
            hasStroke = true;
            hiddenInput.value = canvas.toDataURL("image/png");
            hiddenInput.setCustomValidity("");
            if (errorEl) errorEl.classList.add("d-none");
        };

        const end = () => {
            drawing = false;
        };

        const clear = () => drawFromData("");

        canvas.addEventListener("mousedown", start);
        canvas.addEventListener("mousemove", move);
        window.addEventListener("mouseup", end);
        canvas.addEventListener("mouseleave", end);
        canvas.addEventListener("touchstart", start, { passive: false });
        canvas.addEventListener("touchmove", move, { passive: false });
        canvas.addEventListener("touchend", end);
        canvas.addEventListener("touchcancel", end);
        clearBtn?.addEventListener("click", clear);
        window.addEventListener("resize", resizeCanvas);

        resizeCanvas();
        clear();

        return {
            validate: () => {
                const ok = !!hiddenInput.value;
                hiddenInput.setCustomValidity(ok ? "" : "Signature is required.");
                if (errorEl) errorEl.classList.toggle("d-none", ok);
                return ok;
            },
            resize: resizeCanvas,
            clear,
            setData: (dataUrl) => drawFromData(dataUrl || ""),
            getData: () => hiddenInput.value
        };
    };

    const initFullscreenSignaturePad = (canvas) => {
        if (!canvas) return null;
        const ctx = canvas.getContext("2d");
        if (!ctx) return null;
        let drawing = false;
        let hasStroke = false;

        const setDrawingStyle = () => {
            ctx.lineWidth = 2;
            ctx.lineCap = "round";
            ctx.lineJoin = "round";
            ctx.strokeStyle = "#111827";
        };

        const fillWhite = () => {
            const rect = canvas.getBoundingClientRect();
            ctx.fillStyle = "#ffffff";
            ctx.fillRect(0, 0, rect.width, rect.height);
        };

        const resize = (dataUrl) => {
            const ratio = window.devicePixelRatio || 1;
            const rect = canvas.getBoundingClientRect();
            canvas.width = Math.max(1, Math.floor(rect.width * ratio));
            canvas.height = Math.max(1, Math.floor(rect.height * ratio));
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.scale(ratio, ratio);
            setDrawingStyle();
            fillWhite();
            hasStroke = !!dataUrl;
            if (!dataUrl) return;
            const img = new Image();
            img.onload = () => ctx.drawImage(img, 0, 0, rect.width, rect.height);
            img.src = dataUrl;
        };

        const point = (event) => {
            const rect = canvas.getBoundingClientRect();
            const source = event.touches && event.touches[0] ? event.touches[0] : event;
            return {
                x: source.clientX - rect.left,
                y: source.clientY - rect.top
            };
        };

        const start = (event) => {
            event.preventDefault();
            drawing = true;
            const p = point(event);
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
        };

        const move = (event) => {
            if (!drawing) return;
            event.preventDefault();
            const p = point(event);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
            hasStroke = true;
        };

        const end = () => {
            drawing = false;
        };

        canvas.addEventListener("mousedown", start);
        canvas.addEventListener("mousemove", move);
        window.addEventListener("mouseup", end);
        canvas.addEventListener("mouseleave", end);
        canvas.addEventListener("touchstart", start, { passive: false });
        canvas.addEventListener("touchmove", move, { passive: false });
        canvas.addEventListener("touchend", end);
        canvas.addEventListener("touchcancel", end);

        return {
            resize,
            clear: () => {
                hasStroke = false;
                resize("");
            },
            setData: (dataUrl) => resize(dataUrl || ""),
            getData: () => (hasStroke ? canvas.toDataURL("image/png") : "")
        };
    };

    const complainantSignaturePad = initSignaturePad(
        complainantSignatureCanvas,
        complainantSignatureData,
        complainantSignatureError,
        clearComplainantSignatureBtn
    );
    const respondentSignaturePad = initSignaturePad(
        respondentSignatureCanvas,
        respondentSignatureData,
        respondentSignatureError,
        clearRespondentSignatureBtn
    );
    const fullscreenSignaturePad = initFullscreenSignaturePad(signatureFullscreenCanvas);
    let activeSignaturePad = null;

    const openSignatureFullscreen = (targetPad, label) => {
        if (!signatureFullscreenModal || !fullscreenSignaturePad || !targetPad) return;
        activeSignaturePad = targetPad;
        if (signatureFullscreenTitle) {
            signatureFullscreenTitle.textContent = `${label} Signature`;
        }
        signatureFullscreenModal.show();
    };

    signatureFullscreenModalEl?.addEventListener("shown.bs.modal", () => {
        if (!fullscreenSignaturePad || !activeSignaturePad) return;
        fullscreenSignaturePad.setData(activeSignaturePad.getData());
    });

    signatureFullscreenSaveBtn?.addEventListener("click", () => {
        if (!fullscreenSignaturePad || !activeSignaturePad) return;
        activeSignaturePad.setData(fullscreenSignaturePad.getData());
        signatureFullscreenModal?.hide();
    });

    signatureFullscreenClearBtn?.addEventListener("click", () => {
        fullscreenSignaturePad?.clear();
    });

    openComplainantSignatureFullscreenBtn?.addEventListener("click", () => {
        openSignatureFullscreen(complainantSignaturePad, "Complainant");
    });

    openRespondentSignatureFullscreenBtn?.addEventListener("click", () => {
        openSignatureFullscreen(respondentSignaturePad, "Respondent");
    });

    openNarrativeSignatureModalBtn?.addEventListener("click", () => {
        narrativeSignatureModal?.show();
    });

    narrativeSignatureModalEl?.addEventListener("shown.bs.modal", () => {
        complainantSignaturePad?.resize();
        respondentSignaturePad?.resize();
        updateState();
    });

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

    const validateIncidentDateTime = () => {
        if ((inputMethod?.value || "") === "file") return true;
        if (!incidentDateInput) return true;
        const now = new Date();
        const todayIso = toIsoDate(now);
        const currentTime = toTimeValue(now);
        const dateValue = String(incidentDateInput.value || "").trim();
        const timeValue = String(incidentTimeInput?.value || "").trim();

        incidentDateInput.max = todayIso;
        if (incidentTimeInput) {
            incidentTimeInput.max = dateValue === todayIso ? currentTime : "";
        }

        if (dateValue !== "" && dateValue > todayIso) {
            const msg = `Date must be on or before ${todayIso}.`;
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

        if (incidentTimeInput) {
            if (dateValue === todayIso && timeValue !== "" && timeValue > currentTime) {
                incidentTimeInput.setCustomValidity(`Time must be on or before ${currentTime} for incidents dated today.`);
                return false;
            }
            incidentTimeInput.setCustomValidity("");
        }

        return true;
    };

    const updateState = () => {
        setNarrativeMode();
        syncBlotterComplaintType();
        validateIncidentDateTime();
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

    inputMethod?.addEventListener("change", () => {
        updateState();
        if ((inputMethod?.value || "") !== "") {
            narrativeSignatureModal?.show();
        }
    });
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

    incidentDateInput?.addEventListener("input", updateState);
    incidentDateInput?.addEventListener("change", updateState);
    incidentDateInput?.addEventListener("blur", () => {
        touchedFields.add(incidentDateInput);
        renderValidity(incidentDateInput);
    });
    incidentTimeInput?.addEventListener("input", updateState);
    incidentTimeInput?.addEventListener("change", updateState);
    incidentTimeInput?.addEventListener("blur", () => {
        touchedFields.add(incidentTimeInput);
        renderValidity(incidentTimeInput);
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
    blotterComplaintType?.addEventListener("change", updateState);

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
        const requiresSignature = (inputMethod?.value || "") === "text";
        const signaturesValid =
            !requiresSignature ||
            ((!complainantSignaturePad || complainantSignaturePad.validate()) &&
            (!respondentSignaturePad || respondentSignaturePad.validate()));
        if (!submitConfirmed) {
            e.preventDefault();
            e.stopPropagation();
            updateState();
            if (!validateIncidentDateTime()) {
                renderAllValidity();
                return;
            }
            if (!signaturesValid) {
                return;
            }
            if (!form.checkValidity()) {
                renderAllValidity();
                return;
            }
            confirmModal?.show();
            return;
        }
        updateState();
        if (!validateIncidentDateTime()) {
            e.preventDefault();
            e.stopPropagation();
            renderAllValidity();
            return;
        }
        if (!signaturesValid) {
            e.preventDefault();
            e.stopPropagation();
            return;
        }
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
    syncBlotterComplaintType();
    setFiledDateTime();
    phoneInputs.forEach((input) => syncPhoneValidation(input));
    updateState();

    const params = new URLSearchParams(window.location.search);
    if (params.get("success") === "1") {
        successModal?.show();
    }
});

