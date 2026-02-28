document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("form");
    const submitBtn = form?.querySelector(".submit-btn");
    const landOwnerDetails = document.getElementById("landOwnerDetails");
    const appNew = document.getElementById("app_new");
    const documentUploadSection = document.getElementById("documentUploadSection");
    const fireSafetyFile = document.getElementById("fireSafetyFile");
    const fireSafetyOrNumber = document.getElementById("fireSafetyOrNumber");
    const meralcoYellowFrontFile = document.getElementById("meralcoYellowFrontFile");
    const meralcoYellowBackFile = document.getElementById("meralcoYellowBackFile");
    const meralcoYellowFrontDropzone = document.getElementById("meralcoYellowFrontDropzone");
    const meralcoYellowBackDropzone = document.getElementById("meralcoYellowBackDropzone");
    const meralcoYellowFrontSelected = document.getElementById("meralcoYellowFrontSelected");
    const meralcoYellowBackSelected = document.getElementById("meralcoYellowBackSelected");
    const tctNumberInput = document.getElementById("tct_number");
    const taxDeclarationInput = document.getElementById("tax_declaration_number");
    const tctError = document.getElementById("tct_number_error");
    const taxError = document.getElementById("tax_declaration_number_error");
    const tctRegex = /^(TCT|T)?\d{5,10}$/i;
    const taxRegex = /^(TD)?\d{3,10}\d{0,5}$/i;
    const normalizeId = (value) => value.replace(/[\s-]+/g, "");
    if (!form || !submitBtn) return;

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
        const isNewApp = appNew?.checked === true;
        if (documentUploadSection) {
            documentUploadSection.classList.toggle("d-none", !isNewApp);
        }
        if (fireSafetyFile) fireSafetyFile.required = isNewApp;
        if (fireSafetyOrNumber) fireSafetyOrNumber.required = isNewApp;
        if (meralcoYellowFrontFile) meralcoYellowFrontFile.required = isNewApp;
        if (meralcoYellowBackFile) meralcoYellowBackFile.required = isNewApp;

        if (!isNewApp) {
            if (fireSafetyFile) fireSafetyFile.value = '';
            if (fireSafetyOrNumber) fireSafetyOrNumber.value = '';
            if (meralcoYellowFrontFile) meralcoYellowFrontFile.value = '';
            if (meralcoYellowBackFile) meralcoYellowBackFile.value = '';
            renderFile(meralcoYellowFrontFile, meralcoYellowFrontSelected);
            renderFile(meralcoYellowBackFile, meralcoYellowBackSelected);
        }

        if (tctNumberInput) {
            const rawValue = tctNumberInput.value.trim();
            const normalized = normalizeId(rawValue);
            const hasValue = rawValue !== "";
            const isInvalid = hasValue && !tctRegex.test(normalized);
            tctNumberInput.setCustomValidity(isInvalid ? "Invalid TCT number" : "");
            if (tctError) {
                tctError.classList.toggle("d-none", !isInvalid);
            }
        }
        if (taxDeclarationInput) {
            const rawValue = taxDeclarationInput.value.trim();
            const normalized = normalizeId(rawValue);
            const hasValue = rawValue !== "";
            const isInvalid = hasValue && !taxRegex.test(normalized);
            taxDeclarationInput.setCustomValidity(isInvalid ? "Invalid Tax Declaration number" : "");
            if (taxError) {
                taxError.classList.toggle("d-none", !isInvalid);
            }
        }
        const selected = document.querySelector('input[name="is_land_owner"]:checked');
        const needsOwner = selected?.value === "No";
        if (landOwnerDetails) {
            landOwnerDetails.classList.toggle("d-none", !needsOwner);
            landOwnerDetails.querySelectorAll("input").forEach((input) => {
                input.required = needsOwner && input.name !== "land_owner_middle_name" && input.name !== "land_owner_suffix";
            });
        }
        submitBtn.disabled = !form.checkValidity();
    };

    form.addEventListener("input", updateState);
    form.addEventListener("change", updateState);
    meralcoYellowFrontFile?.addEventListener("change", () => {
        renderFile(meralcoYellowFrontFile, meralcoYellowFrontSelected);
        updateState();
    });
    meralcoYellowBackFile?.addEventListener("change", () => {
        renderFile(meralcoYellowBackFile, meralcoYellowBackSelected);
        updateState();
    });
    bindDropzone(meralcoYellowFrontDropzone, meralcoYellowFrontFile, meralcoYellowFrontSelected);
    bindDropzone(meralcoYellowBackDropzone, meralcoYellowBackFile, meralcoYellowBackSelected);
    updateState();
    renderFile(meralcoYellowFrontFile, meralcoYellowFrontSelected);
    renderFile(meralcoYellowBackFile, meralcoYellowBackSelected);
});
