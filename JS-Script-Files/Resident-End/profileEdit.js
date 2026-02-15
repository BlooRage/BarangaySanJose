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
    const successAlert = document.getElementById("profileSuccessAlert");
    const pendingAlert = document.getElementById("profilePendingAlert");
    const deniedAlert = document.getElementById("profileDeniedAlert");
    const deniedText = document.getElementById("profileDeniedText");
    const modalEl = document.getElementById("editProfileModal");
    let isPendingRequest = false;

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
        btnNext.disabled = !canProceed || isPendingRequest;
    };

    [firstName, middleName, lastName, suffix, civilStatus].forEach((el) => {
        if (el) el.addEventListener("input", updateSections);
    });
    if (nameIdType) nameIdType.addEventListener("change", updateSections);
    if (nameIdFile) nameIdFile.addEventListener("change", updateSections);
    if (civilFile) civilFile.addEventListener("change", updateSections);
    if (civilStatus) civilStatus.addEventListener("change", updateSections);

    updateSections();

    (async () => {
        try {
            const res = await fetch("../PhpFiles/Resident-End/edit_request_status.php");
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success && data.pending?.profile) {
                isPendingRequest = true;
                if (pendingAlert) pendingAlert.classList.remove("d-none");
                if (modalEl) {
                    const inputs = modalEl.querySelectorAll("input, select, textarea, button");
                    inputs.forEach((el) => {
                        if (el.classList.contains("btn-close") || el.dataset.bsDismiss === "modal") return;
                        el.disabled = true;
                    });
                }
            }
            if (res.ok && data.success && data.denied?.profile && deniedAlert) {
                const remarks = data.denied.profile.remarks?.trim();
                const reviewedAt = data.denied.profile.reviewed_at;
                let msg = "Your last profile edit request was denied.";
                if (remarks) {
                    msg += ` Reason: ${remarks}`;
                }
                if (reviewedAt) {
                    msg += ` (Reviewed: ${new Date(reviewedAt).toLocaleString()})`;
                }
                if (deniedText) {
                    deniedText.textContent = msg;
                } else {
                    deniedAlert.textContent = msg;
                }
                deniedAlert.classList.remove("d-none");
            }
        } catch (e) {
            // ignore
        } finally {
            updateSections();
        }
    })();

    btnNext.addEventListener("click", async () => {
        btnNext.disabled = true;
        const form = new FormData();
        form.append("first_name", firstName.value.trim());
        form.append("middle_name", middleName ? middleName.value.trim() : "");
        form.append("last_name", lastName.value.trim());
        form.append("suffix", suffix ? suffix.value.trim() : "");
        form.append("civil_status", civilStatus.value.trim());
        const religion = document.getElementById("editReligion");
        if (religion) form.append("religion", religion.value.trim());
        const employmentStatus = document.getElementById("employmentStatus");
        if (employmentStatus) form.append("employment_status", employmentStatus.value.trim());
        const occupation = document.getElementById("editOccupation");
        if (occupation) form.append("occupation", occupation.value.trim());

        const sectors = Array.from(document.querySelectorAll("input[name='sectorMembership[]']:checked"))
            .map((el) => el.value.trim())
            .filter((v) => v !== "");
        if (sectors.length) {
            form.append("sector_membership", sectors.join(","));
        }

        if (nameIdType && nameIdType.value.trim() !== "") {
            form.append("name_id_type", nameIdType.value.trim());
        }
        if (nameIdFile && nameIdFile.files && nameIdFile.files.length) {
            form.append("name_id_file", nameIdFile.files[0]);
        }
        if (civilFile && civilFile.files && civilFile.files.length) {
            form.append("civil_status_file", civilFile.files[0]);
        }

        try {
            const res = await fetch("../PhpFiles/Resident-End/resident_profile_update.php", {
                method: "POST",
                body: form,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(data.message || "Failed to submit profile edit request.");
            }
            if (successAlert) {
                successAlert.textContent = data.message || "Profile edit request submitted.";
                successAlert.classList.remove("d-none");
            }
            setTimeout(() => {
                window.location.reload();
            }, 1200);
        } catch (err) {
            alert(err?.message || "Failed to submit profile edit request.");
        } finally {
            btnNext.disabled = false;
        }
    });
});
