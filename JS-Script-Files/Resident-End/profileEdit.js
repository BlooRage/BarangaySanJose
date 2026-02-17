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
    const resultEl = document.getElementById("profileSaveResult");
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
    const canEdit = window.RESIDENT_PROFILE_EDIT_ALLOWED !== false;
    const editBlockedMessage =
        window.RESIDENT_PROFILE_EDIT_BLOCK_MESSAGE ||
        "Your account must be verified before you can edit your profile.";
    let isPendingRequest = false;

    if (!firstName || !lastName || !civilStatus || !btnNext) return;

    const getSectorValues = () =>
        Array.from(document.querySelectorAll("input[name='sectorMembership[]']:checked"))
            .map((el) => el.value.trim())
            .filter((v) => v !== "")
            .sort()
            .join(",");

    const applySeniorEligibility = () => {
        const seniorCheckbox = document.getElementById("sectorSenior");
        if (!seniorCheckbox) return;
        const age = Number.isFinite(window.RESIDENT_PROFILE_AGE)
            ? window.RESIDENT_PROFILE_AGE
            : Number(window.RESIDENT_PROFILE_AGE);
        const isEligible = Number.isFinite(age) && age >= 60;
        if (!isEligible) {
            seniorCheckbox.checked = false;
            seniorCheckbox.disabled = true;
        } else {
            seniorCheckbox.disabled = false;
        }
    };

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

    const sanitizeNameValue = (value) => value.replace(/[^A-Za-z ]+/g, "");

    const enforceMarriedNoSingle = () => {
        if (!civilStatus) return;
        const singleOption = civilStatus.querySelector('option[value="Single"]');
        if (!singleOption) return;
        if (civilStatus.value === "Married") {
            singleOption.disabled = true;
        }
    };

    const setMessage = (message, isError = false) => {
        if (!resultEl) return;
        resultEl.textContent = message || "";
        resultEl.className = isError ? "small mb-2 text-danger" : "small mb-2 text-success";
    };

    const looksLikeGibberish = (value) => {
        const letters = value.match(/[A-Za-z]/g) || [];
        const letterCount = letters.length;
        if (letterCount < 6) return false;

        const lower = value.toLowerCase();
        const vowelCount = (lower.match(/[aeiou]/g) || []).length;
        let longestConsonantRun = 0;
        let currentRun = 0;
        for (const ch of lower) {
            if (/[a-z]/.test(ch)) {
                if ("aeiou".includes(ch)) {
                    currentRun = 0;
                } else {
                    currentRun += 1;
                    if (currentRun > longestConsonantRun) longestConsonantRun = currentRun;
                }
            } else {
                currentRun = 0;
            }
        }

        const uniqueLetters = new Set(letters.map((l) => l.toLowerCase())).size;
        if (letterCount >= 8 && vowelCount === 0) return true;
        if (letterCount >= 10 && vowelCount <= 1) return true;
        if (longestConsonantRun >= 6) return true;
        if (letterCount >= 8 && uniqueLetters <= 3) return true;
        return false;
    };

    const isValidPersonName = (value, minLetters, maxLen) => {
        if (!value) return false;
        if (value.length > maxLen) return false;
        if (!/^[A-Za-z ]+$/.test(value)) return false;
        const letters = value.match(/[A-Za-z]/g) || [];
        if (letters.length < minLetters) return false;
        if (looksLikeGibberish(value)) return false;
        return true;
    };

    const isValidOccupation = (value, maxLen = 100) => {
        if (!value) return true;
        if (value.length > maxLen) return false;
        if (!/^[A-Za-z ]+$/.test(value)) return false;
        if (looksLikeGibberish(value)) return false;
        return true;
    };

    const validate = () => {
        const fName = firstName.value.trim();
        const mName = middleName ? middleName.value.trim() : "";
        const lName = lastName.value.trim();
        const occ = occupation ? occupation.value.trim() : "";

        if (!isValidPersonName(fName, 2, 30)) {
            setMessage("First name looks invalid. Please enter a real first name.", true);
            return false;
        }
        if (mName && !isValidPersonName(mName, 1, 30)) {
            setMessage("Middle name looks invalid. Please enter a real middle name.", true);
            return false;
        }
        if (!isValidPersonName(lName, 2, 30)) {
            setMessage("Last name looks invalid. Please enter a real last name.", true);
            return false;
        }
        if (!isValidOccupation(occ)) {
            setMessage("Occupation looks invalid. Please enter a real occupation.", true);
            return false;
        }

        setMessage("");
        return true;
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
        let valid = true;
        if (hasChanges) {
            valid = validate();
        } else {
            setMessage("");
        }
        btnNext.disabled = !canProceed || !valid || isPendingRequest;
    };

    const resetForm = () => {
        firstName.value = initial.firstName;
        if (middleName) middleName.value = initial.middleName;
        lastName.value = initial.lastName;
        if (suffix) suffix.value = initial.suffix;
        civilStatus.value = initial.civilStatus;
        if (religion) religion.value = initial.religion;
        if (employmentStatus) employmentStatus.value = initial.employmentStatus;
        if (occupation) occupation.value = initial.occupation;
        if (voterStatus) voterStatus.value = initial.voterStatus;

        document.querySelectorAll("input[name='sectorMembership[]']").forEach((el) => {
            el.checked = initial.sectorMembership.split(",").includes(el.value.trim());
        });

        if (nameIdType) nameIdType.value = "";
        if (nameIdFile) nameIdFile.value = "";
        if (civilFile) civilFile.value = "";

        if (nameNotice) nameNotice.classList.add("d-none");
        if (nameSection) nameSection.classList.add("d-none");
        if (civilNotice) civilNotice.classList.add("d-none");
        if (civilSection) civilSection.classList.add("d-none");
        setMessage("");
        enforceMarriedNoSingle();
        updateSections();
    };

    [firstName, middleName, lastName].forEach((el) => {
        if (!el) return;
        el.addEventListener("input", () => {
            const sanitized = sanitizeNameValue(el.value);
            if (el.value !== sanitized) {
                el.value = sanitized;
            }
            updateSections();
        });
    });
    [suffix, civilStatus].forEach((el) => {
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
    if (civilStatus) civilStatus.addEventListener("change", () => {
        enforceMarriedNoSingle();
        updateSections();
    });

    applySeniorEligibility();
    enforceMarriedNoSingle();
    updateSections();

    const showNotice = (title, message) => {
        if (noticeTitleEl) noticeTitleEl.textContent = title || "Notice";
        if (noticeBodyEl) noticeBodyEl.textContent = message || "";
        if (!noticeModalEl || !window.bootstrap?.Modal) return;
        if (modalEl && window.bootstrap?.Modal) {
            const openModal = bootstrap.Modal.getInstance(modalEl);
            if (openModal) openModal.hide();
        }
        bootstrap.Modal.getOrCreateInstance(noticeModalEl).show();
    };

    const isPendingDuplicateResponse = (message = "") =>
        /already have a pending/i.test(String(message));

<<<<<<< Updated upstream
<<<<<<< Updated upstream
<<<<<<< Updated upstream
<<<<<<< Updated upstream
<<<<<<< Updated upstream
    const confirmDocumentRequirement = () =>
        new Promise((resolve) => {
            if (window.UniversalModal?.open) {
                window.UniversalModal.open({
                    title: "Before You Continue",
                    message: "Saving changes will send a request for review. Every applied change request requires supporting document/s for verification.",
                    buttons: [
                        {
                            label: "Continue",
                            class: "btn btn-primary",
                            onClick: () => resolve(true),
                        },
                        {
                            label: "Cancel",
                            class: "btn btn-outline-secondary",
                            onClick: () => resolve(false),
                        },
                    ],
                });
                return;
            }
            resolve(window.confirm("Every applied change request requires supporting documents for verification. Continue?"));
        });

=======
>>>>>>> Stashed changes
=======
>>>>>>> Stashed changes
=======
>>>>>>> Stashed changes
=======
>>>>>>> Stashed changes
=======
>>>>>>> Stashed changes
    const showEditBlocked = (event) => {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        showNotice("Verification Required", editBlockedMessage);
    };

    let statusLoaded = false;
    let statusPromise = null;

    const openModal = () => {
        if (!modalEl || !window.bootstrap?.Modal) return;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    };

    const primeStatus = () => {
        if (statusPromise) return statusPromise;
        statusPromise = (async () => {
            try {
                const res = await fetch("../PhpFiles/Resident-End/edit_request_status.php?scope=pending&request_type=profile");
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success) {
                    isPendingRequest = Boolean(data.pending);
                }
            } catch (e) {
                // ignore
            } finally {
                statusLoaded = true;
                updateSections();
            }
        })();
        return statusPromise;
    };

    const handlePendingClick = async (event) => {
        if (!canEdit) {
            showEditBlocked(event);
            return;
        }
        event.preventDefault();
        event.stopPropagation();

        if (isPendingRequest) {
            showNotice("Pending Request", "You already have a pending profile edit request.");
            return;
        }

        if (!statusLoaded) {
            primeStatus();
        }

        const proceed = await confirmDocumentRequirement();
        if (!proceed) return;
        openModal();
    };
    if (modalTrigger) {
        modalTrigger.addEventListener("click", handlePendingClick);
    }
    if (modalEl) {
        modalEl.addEventListener("show.bs.modal", (event) => {
            if (!canEdit) {
                showEditBlocked(event);
                return;
            }
            if (!isPendingRequest) return;
            event.preventDefault();
            showNotice("Pending Request", "You already have a pending profile edit request.");
        });
        modalEl.addEventListener("hidden.bs.modal", () => {
            resetForm();
        });
    }

    primeStatus();

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
            if (isPendingDuplicateResponse(data.message)) {
                isPendingRequest = true;
                updateSections();
                showNotice("Pending Request", data.message || "You already have a pending profile edit request.");
                return;
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
