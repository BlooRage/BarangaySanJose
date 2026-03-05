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
    if (!form || !submitBtn) return;

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

    const setWrapperState = (wrapper, show) => {
        if (!wrapper) return;
        wrapper.classList.toggle("d-none", !show);
        wrapper.querySelectorAll("input, select").forEach((el) => {
            el.disabled = !show;
            if (!show) {
                el.value = "";
                el.setCustomValidity("");
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
        submitBtn.disabled = !form.checkValidity();
    };

    inputMethod?.addEventListener("change", updateState);

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
    setNarrativeMode();
    updateState();
});
