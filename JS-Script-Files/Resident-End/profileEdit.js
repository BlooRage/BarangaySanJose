document.addEventListener("DOMContentLoaded", () => {
    const firstName = document.getElementById("editFirstName");
    const middleName = document.getElementById("editMiddleName");
    const lastName = document.getElementById("editLastName");
    const suffix = document.getElementById("editSuffix");
    const civilStatus = document.getElementById("editCivilStatus");
    const nameNotice = document.getElementById("nameDocNotice");
    const nameSection = document.getElementById("nameDocSection");
    const nameIdType = document.getElementById("nameIdType");
    const nameIdFile = document.getElementById("nameIdFile");
    const civilNotice = document.getElementById("civilStatusDocNotice");
    const civilSection = document.getElementById("civilStatusDocSection");
    const civilFile = document.getElementById("civilStatusFile");
    const civilLabel = document.getElementById("civilStatusDocLabel");
    const civilHelp = document.getElementById("civilStatusDocHelp");
    const btnNext = document.getElementById("btnProfileSave");

    if (!firstName || !lastName || !civilStatus || !btnNext) return;

    const initial = {
        firstName: firstName.value.trim(),
        middleName: middleName ? middleName.value.trim() : "",
        lastName: lastName.value.trim(),
        suffix: suffix ? suffix.value.trim() : "",
        civilStatus: civilStatus.value.trim(),
    };

    const isNameChanged = () => {
        return (
            firstName.value.trim() !== initial.firstName ||
            (middleName ? middleName.value.trim() : "") !== initial.middleName ||
            lastName.value.trim() !== initial.lastName ||
            (suffix ? suffix.value.trim() : "") !== initial.suffix
        );
    };

    const isCivilChanged = () => civilStatus.value.trim() !== initial.civilStatus;

    const updateCivilDocLabel = () => {
        const status = civilStatus.value.trim();
        if (status === "Married") {
            if (civilLabel) civilLabel.textContent = "Marriage Certificate";
            if (civilHelp) civilHelp.textContent = "Upload a marriage certificate to support the change.";
        } else if (status === "Widowed") {
            if (civilLabel) civilLabel.textContent = "Death Certificate of Spouse";
            if (civilHelp) civilHelp.textContent = "Upload the spouse's death certificate to support the change.";
        } else {
            if (civilLabel) civilLabel.textContent = "Document";
            if (civilHelp) civilHelp.textContent = "";
        }
    };

    const updateSections = () => {
        const nameChanged = isNameChanged();
        if (nameNotice) nameNotice.classList.toggle("d-none", !nameChanged);
        if (nameSection) nameSection.classList.toggle("d-none", !nameChanged);

        const civilChanged = isCivilChanged();
        if (civilNotice) civilNotice.classList.toggle("d-none", !civilChanged);
        if (civilSection) civilSection.classList.toggle("d-none", !civilChanged);
        updateCivilDocLabel();

        let canProceed = true;
        if (nameChanged) {
            const idTypeOk = nameIdType && nameIdType.value.trim() !== "";
            const idFileOk = nameIdFile && nameIdFile.files && nameIdFile.files.length > 0;
            canProceed = canProceed && idTypeOk && idFileOk;
        }
        if (civilChanged) {
            const requiresDoc = ["Married", "Widowed"].includes(civilStatus.value.trim());
            const civilFileOk = civilFile && civilFile.files && civilFile.files.length > 0;
            if (requiresDoc) {
                canProceed = canProceed && civilFileOk;
            }
        }
        btnNext.disabled = !canProceed;
    };

    [firstName, middleName, lastName, suffix, civilStatus].forEach((el) => {
        if (el) el.addEventListener("input", updateSections);
    });
    if (nameIdType) nameIdType.addEventListener("change", updateSections);
    if (nameIdFile) nameIdFile.addEventListener("change", updateSections);
    if (civilFile) civilFile.addEventListener("change", updateSections);
    if (civilStatus) civilStatus.addEventListener("change", updateSections);

    updateSections();
});
