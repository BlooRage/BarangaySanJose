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
    const religion = document.getElementById("editReligion");
    const employmentStatus = document.getElementById("employmentStatus");
    const occupation = document.getElementById("editOccupation");
    const voterStatus = document.getElementById("editVoterStatus");
    const modalTrigger =
        document.getElementById("btnOpenEditProfile") ||
        document.querySelector('[data-bs-target="#editProfileModal"]');
    const noticeModalEl = document.getElementById("residentNoticeModal");
    const noticeTitleEl = document.getElementById("residentNoticeTitle");
    const noticeBodyEl = document.getElementById("residentNoticeBody");
    let isPendingRequest = false;

    if (!firstName || !lastName || !civilStatus || !btnNext) return;

    const getSectorValues = () =>
        Array.from(document.querySelectorAll("input[name='sectorMembership[]']:checked"))
            .map((el) => el.value.trim())
            .filter((v) => v !== "")
            .sort()
            .join(",");

    const initial = {
        firstName: firstName.value.trim(),
        middleName: middleName ? middleName.value.trim() : "",
        lastName: lastName.value.trim(),
        suffix: suffix ? suffix.value.trim() : "",
        civilStatus: civilStatus.value.trim(),
        religion: religion ? religion.value.trim() : "",
        employmentStatus: employmentStatus ? employmentStatus.value.trim() : "",
        occupation: occupation ? occupation.value.trim() : "",
        voterStatus: voterStatus ? voterStatus.value.trim() : "",
        sectorMembership: getSectorValues(),
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

    const isOtherChanged = () => {
        const currentReligion = religion ? religion.value.trim() : "";
        const currentEmployment = employmentStatus ? employmentStatus.value.trim() : "";
        const currentOccupation = occupation ? occupation.value.trim() : "";
        const currentVoter = voterStatus ? voterStatus.value.trim() : "";
        const currentSector = getSectorValues();
        return (
            currentReligion !== initial.religion ||
            currentEmployment !== initial.employmentStatus ||
            currentOccupation !== initial.occupation ||
            currentVoter !== initial.voterStatus ||
            currentSector !== initial.sectorMembership
        );
    };

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

        const hasChanges = isNameChanged() || isCivilChanged() || isOtherChanged();

        let canProceed = hasChanges;
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
    if (religion) religion.addEventListener("change", updateSections);
    if (employmentStatus) employmentStatus.addEventListener("change", updateSections);
    if (occupation) occupation.addEventListener("input", updateSections);
    if (voterStatus) voterStatus.addEventListener("change", updateSections);
    document.querySelectorAll("input[name='sectorMembership[]']").forEach((el) => {
        el.addEventListener("change", updateSections);
    });
    if (nameIdType) nameIdType.addEventListener("change", updateSections);
    if (nameIdFile) nameIdFile.addEventListener("change", updateSections);
    if (civilFile) civilFile.addEventListener("change", updateSections);
    if (civilStatus) civilStatus.addEventListener("change", updateSections);

    updateSections();

    const showNotice = (title, message) => {
        if (noticeTitleEl) noticeTitleEl.textContent = title || "Notice";
        if (noticeBodyEl) noticeBodyEl.textContent = message || "";
        if (!noticeModalEl || !window.bootstrap?.Modal) return;
        bootstrap.Modal.getOrCreateInstance(noticeModalEl).show();
    };

    let statusLoaded = false;

    const openModal = () => {
        if (!modalEl || !window.bootstrap?.Modal) return;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    };

    const handlePendingClick = async (event) => {
        event.preventDefault();
        event.stopPropagation();

        if (!statusLoaded) {
            try {
                const res = await fetch("../PhpFiles/Resident-End/edit_request_status.php");
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success) {
                    isPendingRequest = Boolean(data.pending?.profile);
                }
            } catch (e) {
                // ignore
            } finally {
                statusLoaded = true;
            }
        }

        if (isPendingRequest) {
            showNotice("Pending Request", "You already have a pending profile edit request.");
            return;
        }
        openModal();
    };
    if (modalTrigger) {
        modalTrigger.addEventListener("click", handlePendingClick);
    }
    if (modalEl) {
        modalEl.addEventListener("show.bs.modal", (event) => {
            if (!isPendingRequest) return;
            event.preventDefault();
            showNotice("Pending Request", "You already have a pending profile edit request.");
        });
    }

    (async () => {
        try {
            const res = await fetch("../PhpFiles/Resident-End/edit_request_status.php");
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success && data.pending?.profile) {
                isPendingRequest = true;
                if (pendingAlert) pendingAlert.classList.add("d-none");
                if (modalEl && window.bootstrap?.Modal) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
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
            statusLoaded = true;
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
            if (successAlert) successAlert.classList.add("d-none");
            showNotice("Request Submitted", data.message || "Profile edit request submitted.");
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
