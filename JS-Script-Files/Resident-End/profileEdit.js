document.addEventListener("DOMContentLoaded", () => {
    const firstName = document.getElementById("editFirstName");
    const middleName = document.getElementById("editMiddleName");
    const lastName = document.getElementById("editLastName");
    const suffix = document.getElementById("editSuffix");
    const civilStatus = document.getElementById("editCivilStatus");
    const newSurnameRow = document.getElementById("newSurnameRow");
    const newSurnameInput = document.getElementById("editNewSurname");
    const nameNotice = document.getElementById("nameDocNotice");
    const nameSection = document.getElementById("nameDocSection");
    const nameIdType = document.getElementById("nameIdType");
    const nameIdFile = document.getElementById("nameIdFile");
    const generalSupportSection = document.getElementById("generalSupportSection");
    const generalSupportType = document.getElementById("generalSupportType");
    const generalSupportFile = document.getElementById("generalSupportFile");
    const generalSupportNotice = document.getElementById("generalSupportNotice");
    const studentUntickNotice = document.getElementById("studentUntickNotice");
    const studentUntickSection = document.getElementById("studentUntickSection");
    const studentStatusFile = document.getElementById("studentStatusFile");
    const studentStoppedSwitch = document.getElementById("studentStoppedSwitch");
    const civilNotice = document.getElementById("civilStatusDocNotice");
    const civilSection = document.getElementById("civilStatusDocSection");
    const civilFile = document.getElementById("civilStatusFile");
    const civilLabel = document.getElementById("civilStatusDocLabel");
    const civilHelp = document.getElementById("civilStatusDocHelp");
    const btnReview = document.getElementById("btnProfileReview");
    const btnNext = document.getElementById("btnProfileSave");
    const btnBackToForm = document.getElementById("btnProfileBackToForm");
    const successAlert = document.getElementById("profileSuccessAlert");
    const pendingAlert = document.getElementById("profilePendingAlert");
    const deniedAlert = document.getElementById("profileDeniedAlert");
    const deniedText = document.getElementById("profileDeniedText");
    const resultEl = document.getElementById("profileSaveResult");
    const modalEl = document.getElementById("editProfileModal");
    const uploadModalEl = document.getElementById("editProfileUploadModal");
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
    const residentSex = String(window.RESIDENT_PROFILE_SEX || "").trim().toLowerCase();
    const isFemaleResident = residentSex === "female";
    let isPendingRequest = false;
    let profileStage = "form";
    let suppressFormResetOnHide = false;

    if (!firstName || !lastName || !civilStatus || !btnReview || !btnNext) return;

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

    const requiresNewSurname = () =>
        isFemaleResident &&
        isCivilChanged() &&
        ["Married", "Widowed", "Divorced"].includes(civilStatus.value.trim());

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

    const isEmploymentOnlyChanged = () => {
        const currentReligion = religion ? religion.value.trim() : "";
        const currentEmployment = employmentStatus ? employmentStatus.value.trim() : "";
        const currentOccupation = occupation ? occupation.value.trim() : "";
        const currentVoter = voterStatus ? voterStatus.value.trim() : "";
        const currentSector = getSectorValues();

        const employmentChanged =
            currentEmployment !== initial.employmentStatus ||
            currentOccupation !== initial.occupation;
        if (!employmentChanged) return false;

        const otherChanged =
            currentReligion !== initial.religion ||
            currentVoter !== initial.voterStatus ||
            currentSector !== initial.sectorMembership;
        return !otherChanged;
    };

    const getUploadRequirements = () => {
        const nameChanged = isNameChanged();
        const civilChanged = isCivilChanged();
        const requiresCivilDoc = civilChanged && ["Married", "Widowed"].includes(civilStatus.value.trim());
        const sectorInfo = getSectorChangeInfo();
        const requiresStudentUntickProof = sectorInfo.studentUnticked;
        const sectorChanged = sectorInfo.changed;
        const requiresSectorDoc = sectorChanged;
        const requiresGeneralDoc =
            !nameChanged &&
            !requiresCivilDoc &&
            !isEmploymentOnlyChanged() &&
            !requiresStudentUntickProof &&
            !requiresSectorDoc;
        return {
            nameChanged,
            requiresCivilDoc,
            requiresStudentUntickProof,
            requiresSectorDoc,
            requiresGeneralDoc,
            requiresAnyDoc: nameChanged || requiresCivilDoc || requiresGeneralDoc || requiresStudentUntickProof || requiresSectorDoc,
        };
    };

    const parseSectorCsv = (csv) =>
        String(csv || "")
            .split(",")
            .map((s) => s.trim())
            .filter((s) => s !== "");

    const normalizeSector = (sector) => String(sector || "").trim().toLowerCase();

    const getSectorChangeInfo = () => {
        const initialSet = new Set(parseSectorCsv(initial.sectorMembership).map(normalizeSector));
        const currentSet = new Set(
            Array.from(document.querySelectorAll("input[name='sectorMembership[]']:checked"))
                .map((el) => normalizeSector(el.value))
                .filter((v) => v !== "")
        );

        const removed = Array.from(initialSet).filter((sector) => !currentSet.has(sector));
        const added = Array.from(currentSet).filter((sector) => !initialSet.has(sector));
        const removedNonStudent = removed.filter((sector) => sector !== "student");
        return {
            removedNonStudent,
            studentUnticked: removed.includes("student"),
            changed: removed.length > 0 || added.length > 0,
        };
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
        const isInitiallyMarried = String(initial.civilStatus || "").trim().toLowerCase() === "married";
        singleOption.disabled = isInitiallyMarried;
        if (isInitiallyMarried && civilStatus.value === "Single") {
            civilStatus.value = "Married";
        }
    };

    const syncDivorcedByReligion = () => {
        if (!civilStatus || !religion) return;
        const divorcedOption = civilStatus.querySelector('option[value="Divorced"]');
        if (!divorcedOption) return;

        const isMuslim = religion.value.trim().toLowerCase() === "muslim";
        const isInitiallyMarried = String(initial.civilStatus || "").trim().toLowerCase() === "married";

        divorcedOption.disabled = !isMuslim;
        if (!isMuslim && civilStatus.value === "Divorced") {
            civilStatus.value = isInitiallyMarried ? "Married" : (initial.civilStatus || "Single");
        }
    };

    const enforceVoterNoRevert = () => {
        if (!voterStatus) return;
        const notRegisteredOption = voterStatus.querySelector('option[value="Not Registered"]');
        if (!notRegisteredOption) return;

        const initialVoter = String(initial.voterStatus || "").trim().toLowerCase();
        const isInitiallyRegistered =
            initialVoter === "registered" || initialVoter === "registered voter";

        notRegisteredOption.disabled = isInitiallyRegistered;
        if (isInitiallyRegistered && voterStatus.value === "Not Registered") {
            voterStatus.value = "Registered";
        }
    };

    const setMessage = (message, isError = false) => {
        if (!resultEl) return;
        resultEl.textContent = message || "";
        resultEl.className = isError ? "small mb-2 text-danger" : "small mb-2 text-success";
    };

    const escapeHtml = (value) =>
        String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");

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
        const sectorInfo = getSectorChangeInfo();
        if (sectorInfo.removedNonStudent.length > 0) {
            setMessage("Only Student sector membership can be removed. Other sector memberships cannot be unticked.", true);
            return false;
        }
        if (requiresNewSurname()) {
            const ns = newSurnameInput ? newSurnameInput.value.trim() : "";
            if (!isValidPersonName(ns, 2, 30)) {
                setMessage("New surname looks invalid. Please enter a real surname.", true);
                return false;
            }
        }

        setMessage("");
        return true;
    };

    const updateSections = () => {
        const { nameChanged, requiresCivilDoc, requiresGeneralDoc, requiresStudentUntickProof, requiresSectorDoc } = getUploadRequirements();
        updateCivilDocLabel();

        const hasChanges = isNameChanged() || isCivilChanged() || isOtherChanged();

        let canProceed = hasChanges;
        let valid = true;
        if (hasChanges) {
            valid = validate();
        } else {
            setMessage("");
        }

        if (profileStage === "upload") {
            if (studentUntickNotice) studentUntickNotice.classList.toggle("d-none", !requiresStudentUntickProof);
            if (studentUntickSection) studentUntickSection.classList.toggle("d-none", !requiresStudentUntickProof);
            if (newSurnameRow) newSurnameRow.classList.toggle("d-none", !requiresNewSurname());
            if (generalSupportSection) generalSupportSection.classList.toggle("d-none", !(requiresGeneralDoc || requiresSectorDoc));
            if (generalSupportNotice) generalSupportNotice.classList.toggle("d-none", !(requiresGeneralDoc || requiresSectorDoc));
            if (nameNotice) nameNotice.classList.toggle("d-none", !nameChanged);
            if (nameSection) nameSection.classList.toggle("d-none", !nameChanged);
            if (civilNotice) civilNotice.classList.toggle("d-none", !requiresCivilDoc);
            if (civilSection) civilSection.classList.toggle("d-none", !requiresCivilDoc);

            if (requiresGeneralDoc) {
                const generalTypeOk = generalSupportType && generalSupportType.value.trim() !== "";
                const generalFileOk = generalSupportFile && generalSupportFile.files && generalSupportFile.files.length > 0;
                canProceed = canProceed && generalTypeOk && generalFileOk;
            }

            if (nameChanged) {
                const idTypeOk = nameIdType && nameIdType.value.trim() !== "";
                const idFileOk = nameIdFile && nameIdFile.files && nameIdFile.files.length > 0;
                canProceed = canProceed && idTypeOk && idFileOk;
            }
            if (requiresCivilDoc) {
                const civilFileOk = civilFile && civilFile.files && civilFile.files.length > 0;
                canProceed = canProceed && civilFileOk;
            }
            if (requiresStudentUntickProof) {
                const studentFileOk = studentStatusFile && studentStatusFile.files && studentStatusFile.files.length > 0;
                const stoppedConfirmed = studentStoppedSwitch && studentStoppedSwitch.checked;
                canProceed = canProceed && (studentFileOk || stoppedConfirmed);
            }
            if (requiresSectorDoc) {
                const studentFileOk = studentStatusFile && studentStatusFile.files && studentStatusFile.files.length > 0;
                const generalFileOk = generalSupportFile && generalSupportFile.files && generalSupportFile.files.length > 0;
                const generalTypeOk = generalSupportType && generalSupportType.value.trim() !== "";
                const hasValidGeneralDoc = generalFileOk && generalTypeOk;
                canProceed = canProceed && (studentFileOk || hasValidGeneralDoc);
            }
            btnNext.disabled = !canProceed || !valid || isPendingRequest;
            btnReview.disabled = true;
        } else {
            if (studentUntickNotice) studentUntickNotice.classList.add("d-none");
            if (studentUntickSection) studentUntickSection.classList.add("d-none");
            if (newSurnameRow) newSurnameRow.classList.toggle("d-none", !requiresNewSurname());
            if (generalSupportSection) generalSupportSection.classList.remove("d-none");
            if (generalSupportNotice) generalSupportNotice.classList.add("d-none");
            if (nameNotice) nameNotice.classList.add("d-none");
            if (nameSection) nameSection.classList.add("d-none");
            if (civilNotice) civilNotice.classList.add("d-none");
            if (civilSection) civilSection.classList.add("d-none");
            btnReview.disabled = !canProceed || !valid || isPendingRequest;
            btnNext.disabled = true;
        }

    };

    const resetForm = () => {
        profileStage = "form";
        firstName.value = initial.firstName;
        if (middleName) middleName.value = initial.middleName;
        lastName.value = initial.lastName;
        if (suffix) suffix.value = initial.suffix;
        if (newSurnameInput) newSurnameInput.value = "";
        civilStatus.value = initial.civilStatus;
        if (religion) religion.value = initial.religion;
        if (employmentStatus) employmentStatus.value = initial.employmentStatus;
        if (occupation) occupation.value = initial.occupation;
        if (voterStatus) voterStatus.value = initial.voterStatus;

        document.querySelectorAll("input[name='sectorMembership[]']").forEach((el) => {
            el.checked = initial.sectorMembership.split(",").includes(el.value.trim());
        });

        if (nameIdType) nameIdType.value = "";
        if (generalSupportType) generalSupportType.value = "";
        if (nameIdFile) nameIdFile.value = "";
        if (generalSupportFile) generalSupportFile.value = "";
        if (studentStatusFile) studentStatusFile.value = "";
        if (studentStoppedSwitch) studentStoppedSwitch.checked = false;
        if (civilFile) civilFile.value = "";

        if (studentUntickNotice) studentUntickNotice.classList.add("d-none");
        if (studentUntickSection) studentUntickSection.classList.add("d-none");
        if (generalSupportNotice) generalSupportNotice.classList.add("d-none");
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
    if (newSurnameInput) {
        newSurnameInput.addEventListener("input", () => {
            const sanitized = sanitizeNameValue(newSurnameInput.value);
            if (newSurnameInput.value !== sanitized) {
                newSurnameInput.value = sanitized;
            }
            updateSections();
        });
    }
    if (employmentStatus) employmentStatus.addEventListener("change", updateSections);
    if (occupation) occupation.addEventListener("input", updateSections);
    if (voterStatus) voterStatus.addEventListener("change", updateSections);
    document.querySelectorAll("input[name='sectorMembership[]']").forEach((el) => {
        el.addEventListener("change", updateSections);
    });
    if (nameIdType) nameIdType.addEventListener("change", updateSections);
    if (generalSupportType) generalSupportType.addEventListener("change", updateSections);
    if (nameIdFile) nameIdFile.addEventListener("change", updateSections);
    if (generalSupportFile) generalSupportFile.addEventListener("change", updateSections);
    if (studentStatusFile) studentStatusFile.addEventListener("change", updateSections);
    if (studentStoppedSwitch) studentStoppedSwitch.addEventListener("change", updateSections);
    if (civilFile) civilFile.addEventListener("change", updateSections);
    if (civilStatus) civilStatus.addEventListener("change", () => {
        enforceMarriedNoSingle();
        updateSections();
    });
    if (voterStatus) {
        voterStatus.addEventListener("change", () => {
            enforceVoterNoRevert();
            updateSections();
        });
    }
    if (religion) {
        religion.addEventListener("change", () => {
            syncDivorcedByReligion();
            updateSections();
        });
    }

    applySeniorEligibility();
    syncDivorcedByReligion();
    enforceMarriedNoSingle();
    enforceVoterNoRevert();
    updateSections();

    const showNotice = (title, message) => {
        if (noticeTitleEl) noticeTitleEl.textContent = title || "Notice";
        if (noticeBodyEl) noticeBodyEl.textContent = message || "";
        if (!noticeModalEl || !window.bootstrap?.Modal) return;
        if (modalEl && window.bootstrap?.Modal) {
            const openModal = bootstrap.Modal.getInstance(modalEl);
            if (openModal) openModal.hide();
        }
        if (uploadModalEl && window.bootstrap?.Modal) {
            const uploadOpenModal = bootstrap.Modal.getInstance(uploadModalEl);
            if (uploadOpenModal) uploadOpenModal.hide();
        }
        bootstrap.Modal.getOrCreateInstance(noticeModalEl).show();
    };

    const isPendingDuplicateResponse = (message = "") =>
        /already have a pending/i.test(String(message));

    const confirmBeforeContinue = () =>
        new Promise((resolve) => {
            if (window.UniversalModal?.open) {
                window.UniversalModal.open({
                    title: "Before You Continue",
                    message:
                        "Saving changes will send a request for review. Every applied change request requires supporting document/s for verification.",
                    buttons: [
                        { label: "Continue", class: "btn btn-primary", onClick: () => resolve(true) },
                        { label: "Cancel", class: "btn btn-outline-secondary", onClick: () => resolve(false) },
                    ],
                });
                return;
            }
            resolve(window.confirm("Saving changes will send a request for review. Continue?"));
        });

    const getCurrentValues = () => ({
        firstName: firstName.value.trim(),
        middleName: middleName ? middleName.value.trim() : "",
        lastName: (() => {
            const ns = newSurnameInput ? newSurnameInput.value.trim() : "";
            if (requiresNewSurname() && ns !== "") return ns;
            return lastName.value.trim();
        })(),
        suffix: suffix ? suffix.value.trim() : "",
        civilStatus: civilStatus.value.trim(),
        religion: religion ? religion.value.trim() : "",
        employmentStatus: employmentStatus ? employmentStatus.value.trim() : "",
        occupation: occupation ? occupation.value.trim() : "",
        voterStatus: voterStatus ? voterStatus.value.trim() : "",
        sectorMembership: getSectorValues(),
    });

    const formatValue = (value) => (value && String(value).trim() !== "" ? String(value) : "N/A");

    const buildChangeRows = () => {
        const current = getCurrentValues();
        const labels = {
            firstName: "First Name",
            middleName: "Middle Name",
            lastName: "Last Name",
            suffix: "Suffix",
            civilStatus: "Civil Status",
            religion: "Religion",
            employmentStatus: "Employment Status",
            occupation: "Occupation",
            voterStatus: "Voter Status",
            sectorMembership: "Sector Membership",
        };

        return Object.keys(labels)
            .filter((k) => (initial[k] ?? "") !== (current[k] ?? ""))
            .map((k) => ({
                field: labels[k],
                from: formatValue(initial[k]),
                to: formatValue(current[k]),
            }));
    };

    const reviewHtml = (rows) => {
        const items = rows
            .map(
                (r) => `
                <tr>
                    <td class="text-start fw-semibold">${escapeHtml(r.field)}</td>
                    <td class="text-start">${escapeHtml(r.from)}</td>
                    <td class="text-start">${escapeHtml(r.to)}</td>
                </tr>`
            )
            .join("");

        return `
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="text-start">Field</th>
                            <th class="text-start">From</th>
                            <th class="text-start">To</th>
                        </tr>
                    </thead>
                    <tbody>${items}</tbody>
                </table>
            </div>`;
    };

    const openReviewStep = async () => {
        const rows = buildChangeRows();
        if (!rows.length) {
            setMessage("No changes detected.", true);
            return;
        }

        const formWasOpen = await hideFormModalIfOpen();

        if (window.UniversalModal?.open) {
            await new Promise((resolve) => {
                window.UniversalModal.open({
                    title: "Review Changes",
                    messageHtml: reviewHtml(rows),
                    buttons: [
                        { label: "Back", class: "btn btn-outline-secondary", onClick: () => resolve(false) },
                        { label: "Continue", class: "btn btn-primary", onClick: () => resolve(true) },
                    ],
                });
            }).then((proceed) => {
                if (proceed) {
                    const req = getUploadRequirements();
                    if (!req.requiresAnyDoc) {
                        window.UniversalModal.open({
                            title: "Confirm Submission",
                            message: "Submit this profile update request now?",
                            buttons: [
                                {
                                    label: "Submit",
                                    class: "btn btn-primary",
                                    onClick: () => {
                                        submitProfileRequest();
                                    },
                                },
                                {
                                    label: "Back",
                                    class: "btn btn-outline-secondary",
                                    onClick: () => {
                                        if (formWasOpen) {
                                            profileStage = "form";
                                            updateSections();
                                            openModal();
                                        }
                                    },
                                },
                            ],
                        });
                        return;
                    }

                    profileStage = "upload";
                    updateSections();
                    setMessage("Upload the required supporting document(s) before submitting.");
                    openUploadModal();
                    return;
                }
                if (formWasOpen) {
                    profileStage = "form";
                    updateSections();
                    openModal();
                }
            });
            return;
        }

        const req = getUploadRequirements();
        if (!req.requiresAnyDoc) {
            const confirmSubmit = window.confirm("Submit this profile update request now?");
            if (!confirmSubmit) {
                if (formWasOpen) {
                    profileStage = "form";
                    updateSections();
                    openModal();
                }
                return;
            }
            await submitProfileRequest();
            return;
        }

        profileStage = "upload";
        updateSections();
        openUploadModal();
    };

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

    const openUploadModal = () => {
        if (!uploadModalEl || !window.bootstrap?.Modal) return;
        bootstrap.Modal.getOrCreateInstance(uploadModalEl).show();
    };

    const buildProfileFormData = () => {
        const form = new FormData();
        form.append("first_name", firstName.value.trim());
        form.append("middle_name", middleName ? middleName.value.trim() : "");
        form.append("last_name", lastName.value.trim());
        form.append("suffix", suffix ? suffix.value.trim() : "");
        form.append("new_surname", newSurnameInput ? newSurnameInput.value.trim() : "");
        form.append("civil_status", civilStatus.value.trim());
        const religionEl = document.getElementById("editReligion");
        if (religionEl) form.append("religion", religionEl.value.trim());
        const employmentStatusEl = document.getElementById("employmentStatus");
        if (employmentStatusEl) form.append("employment_status", employmentStatusEl.value.trim());
        const occupationEl = document.getElementById("editOccupation");
        if (occupationEl) form.append("occupation", occupationEl.value.trim());

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
        if (generalSupportType && generalSupportType.value.trim() !== "") {
            form.append("supporting_doc_type", generalSupportType.value.trim());
        }
        if (generalSupportFile && generalSupportFile.files && generalSupportFile.files.length) {
            form.append("supporting_file", generalSupportFile.files[0]);
        }
        if (studentStatusFile && studentStatusFile.files && studentStatusFile.files.length) {
            form.append("student_status_file", studentStatusFile.files[0]);
        }
        form.append("student_stopped", studentStoppedSwitch && studentStoppedSwitch.checked ? "1" : "0");
        if (civilFile && civilFile.files && civilFile.files.length) {
            form.append("civil_status_file", civilFile.files[0]);
        }
        return form;
    };

    const submitProfileRequest = async () => {
        btnNext.disabled = true;
        const form = buildProfileFormData();

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
            showNotice("Request Submitted", "Submitted. Please wait for admin to validate.");
            setTimeout(() => {
                window.location.reload();
            }, 1200);
        } catch (err) {
            alert(err?.message || "Failed to submit profile edit request.");
        } finally {
            btnNext.disabled = false;
        }
    };

    const hideFormModalIfOpen = async () => {
        if (!modalEl || !window.bootstrap?.Modal) return false;
        const formModal = bootstrap.Modal.getInstance(modalEl);
        if (!formModal || !modalEl.classList.contains("show")) return false;

        await new Promise((resolve) => {
            const onHidden = () => resolve();
            modalEl.addEventListener("hidden.bs.modal", onHidden, { once: true });
            suppressFormResetOnHide = true;
            formModal.hide();
        });
        return true;
    };

    const primeStatus = () => {
        if (statusPromise) return statusPromise;
        statusPromise = (async () => {
            try {
                const res = await fetch("../PhpFiles/Resident-End/edit_request_status.php");
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success) {
                    isPendingRequest = Boolean(data.pending?.profile);
                    if (data.denied?.profile && deniedAlert) {
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
            await primeStatus();
            if (isPendingRequest) {
                showNotice("Pending Request", "You already have a pending profile edit request.");
                return;
            }
        }

        const proceed = await confirmBeforeContinue();
        if (!proceed) return;
        profileStage = "form";
        updateSections();
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
            if (suppressFormResetOnHide) {
                suppressFormResetOnHide = false;
                return;
            }
            resetForm();
        });
    }

    if (uploadModalEl) {
        uploadModalEl.addEventListener("show.bs.modal", (event) => {
            if (!canEdit) {
                showEditBlocked(event);
                return;
            }
            if (!isPendingRequest) return;
            event.preventDefault();
            showNotice("Pending Request", "You already have a pending profile edit request.");
        });
        uploadModalEl.addEventListener("hidden.bs.modal", () => {
            if (profileStage === "upload") {
                resetForm();
            }
        });
    }

    primeStatus();

    btnReview.addEventListener("click", async () => {
        const hasChanges = isNameChanged() || isCivilChanged() || isOtherChanged();
        if (!hasChanges) {
            setMessage("No changes detected.", true);
            return;
        }
        if (!validate()) return;
        await openReviewStep();
    });

    if (btnBackToForm) {
        btnBackToForm.addEventListener("click", () => {
            profileStage = "form";
            updateSections();
            if (uploadModalEl && window.bootstrap?.Modal) {
                const uploadModal = bootstrap.Modal.getInstance(uploadModalEl);
                if (uploadModal) uploadModal.hide();
            }
            openModal();
        });
    }

    btnNext.addEventListener("click", async () => {
        await submitProfileRequest();
    });
});
