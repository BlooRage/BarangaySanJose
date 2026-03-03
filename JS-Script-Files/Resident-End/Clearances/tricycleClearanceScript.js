document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("form");
    const submitBtn = form?.querySelector(".submit-btn");
    const tricycleTypeSelect = document.getElementById("tricycleTypeSelect");
    const tricycleCertLabel = document.getElementById("tricycleCertLabel");
    const tricycleCertFile = document.getElementById("tricycleCertFile");
    const bodyNumberInput = document.getElementById("bodyNumber");
    const chassisNumberInput = document.getElementById("chassisNumber");
    const motorNumberInput = document.getElementById("motorNumber");
    const orNumberInput = document.getElementById("orNumber");
    const crNumberInput = document.getElementById("crNumber");
    const plateNumberInput = document.getElementById("plateNumber");
    if (!form || !submitBtn) return;

    const updateCertificationLabel = () => {
        if (!tricycleCertLabel) return;
        const type = tricycleTypeSelect?.value?.trim();
        const labelText = type ? `Upload ${type} Certification` : "Upload Certification";
        tricycleCertLabel.innerHTML = `${labelText} <span class="required-asterisk">*</span>`;
        if (tricycleCertFile) {
            tricycleCertFile.disabled = !type;
            if (!type) {
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
    tricycleTypeSelect?.addEventListener("change", updateCertificationLabel);
    updateCertificationLabel();
    wireFileDisplay("tricycleCertFile", "tricycleCertSelectedFile");
    wireFileDisplay("ltoRegFiles", "ltoRegSelectedFile");
    updateState();
});
