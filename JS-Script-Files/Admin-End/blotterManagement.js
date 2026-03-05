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

    const updateState = () => {
        setNarrativeMode();
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
