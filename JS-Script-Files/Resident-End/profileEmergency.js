document.addEventListener("DOMContentLoaded", () => {
    const csrfToken = String(window.RESIDENT_CSRF_TOKEN || "").trim();
    const saveBtn = document.getElementById("btnSaveEmergency");
    if (!saveBtn) return;

    const resultEl = document.getElementById("emergencySaveResult");
    const deniedAlert = document.getElementById("emergencyDeniedAlert");
    const deniedText = document.getElementById("emergencyDeniedText");
    const modalTrigger =
        document.getElementById("btnOpenEditEmergency") ||
        document.querySelector('[data-bs-target="#editEmergencyContactModal"]');
    const modalEl = document.getElementById("editEmergencyContactModal");
    const noticeModalEl = document.getElementById("residentNoticeModal");
    const noticeTitleEl = document.getElementById("residentNoticeTitle");
    const noticeBodyEl = document.getElementById("residentNoticeBody");
    const canEdit = window.RESIDENT_PROFILE_EDIT_ALLOWED !== false;
    const editBlockedMessage =
        window.RESIDENT_PROFILE_EDIT_BLOCK_MESSAGE ||
        "Your account must be verified before you can edit your emergency contact.";
    const fieldIds = [
        "emergencyLastName",
        "emergencyFirstName",
        "emergencyMiddleName",
        "emergencySuffix",
        "emergencyContact",
        "emergencyRelationship",
        "emergencyRelationshipOther",
        "emergencyAddress",
    ];
    const initialValues = {};
    fieldIds.forEach((id) => {
        const el = document.getElementById(id);
        initialValues[id] = el ? el.value.trim() : "";
    });

    const getValue = (id) => {
        const el = document.getElementById(id);
        return el ? el.value.trim() : "";
    };

    const setMessage = (message, isError = false) => {
        if (!resultEl) return;
        resultEl.textContent = message || "";
        resultEl.className = isError ? "small mt-2 text-danger" : "small mt-2 text-success";
    };

    const sanitizeNameValue = (value) => value.replace(/[^A-Za-z ]+/g, "");

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

    const isValidPersonName = (value, minLetters = 1, maxLen = 50) => {
        if (!value) return false;
        if (value.length > maxLen) return false;
        if (!/^[A-Za-z ]+$/.test(value)) return false;
        const letters = value.match(/[A-Za-z]/g) || [];
        if (letters.length < minLetters) return false;
        if (looksLikeGibberish(value)) return false;
        return true;
    };

    const isValidAlphaText = (value) => {
        if (!value) return false;
        return /^[A-Za-z ]+$/.test(value);
    };

    const isValidAddressText = (value) => {
        if (!value) return false;
        return /^[A-Za-z0-9 ,.\-]+$/.test(value);
    };

    const validate = () => {
        const lastName = getValue("emergencyLastName");
        const firstName = getValue("emergencyFirstName");
        const middleName = getValue("emergencyMiddleName");
        const contact = getValue("emergencyContact");
        let relationship = getValue("emergencyRelationship");
        const relationshipOther = getValue("emergencyRelationshipOther");
        const address = getValue("emergencyAddress");

        if (!lastName || !firstName || !contact || !relationship || !address) {
            setMessage("Please fill in all required fields.", true);
            return false;
        }

        if (relationship.toLowerCase() === "other") {
            if (!relationshipOther) {
                setMessage("Please specify the relationship.", true);
                return false;
            }
            relationship = relationshipOther;
        }

        if (!isValidPersonName(firstName, 2, 30)) {
            setMessage("First name contains invalid characters.", true);
            return false;
        }
        if (!isValidPersonName(lastName, 2, 20)) {
            setMessage("Last name contains invalid characters.", true);
            return false;
        }
        if (middleName && !isValidPersonName(middleName, 1, 20)) {
            setMessage("Middle name contains invalid characters.", true);
            return false;
        }
        if (!/^9\d{9}$/.test(contact)) {
            setMessage("Contact number must be 10 digits and start with 9.", true);
            return false;
        }
        if (relationship.length > 50) {
            setMessage("Relationship must be 50 characters or less.", true);
            return false;
        }
        if (!isValidAlphaText(relationship)) {
            setMessage("Relationship must contain letters only.", true);
            return false;
        }
        if (/[A-Za-z]/.test(relationship) && looksLikeGibberish(relationship)) {
            setMessage("Relationship looks invalid. Please enter a real relationship.", true);
            return false;
        }
        if (address.length > 255) {
            setMessage("Address must be 255 characters or less.", true);
            return false;
        }
        if (!isValidAddressText(address)) {
            setMessage("Address can only contain letters, numbers, commas, and periods.", true);
            return false;
        }
        if (looksLikeGibberish(address)) {
            setMessage("Address looks invalid. Please enter a real address.", true);
            return false;
        }

        setMessage("");
        return true;
    };

    const isDirty = () => {
        return fieldIds.some((id) => {
            const el = document.getElementById(id);
            const current = el ? el.value.trim() : "";
            return current !== (initialValues[id] ?? "");
        });
    };

    const resetForm = () => {
        fieldIds.forEach((id) => {
            const el = document.getElementById(id);
            if (el) el.value = initialValues[id] ?? "";
        });
        hasInteracted = false;
        setMessage("");
        toggleRelationshipOther();
        updateSaveState();
    };

    let isPendingRequest = false;
    let hasInteracted = false;

    const updateSaveState = () => {
        const hasChanges = isDirty();
        const shouldValidate = hasChanges && hasInteracted;
        const valid = shouldValidate ? validate() : true;
        if (!shouldValidate) setMessage("");
        saveBtn.disabled = !hasChanges || !valid || isPendingRequest;
    };

    fieldIds.forEach((id) => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener("input", () => {
                hasInteracted = true;
                updateSaveState();
            });
        }
    });

    ["emergencyLastName", "emergencyFirstName", "emergencyMiddleName"].forEach((id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener("input", () => {
            const sanitized = sanitizeNameValue(el.value);
            if (el.value !== sanitized) {
                el.value = sanitized;
            }
            hasInteracted = true;
            updateSaveState();
        });
    });

    const relationshipSelectEl = document.getElementById("emergencyRelationship");
    const relationshipOtherEl = document.getElementById("emergencyRelationshipOther");
    const toggleRelationshipOther = () => {
        if (!relationshipSelectEl || !relationshipOtherEl) return;
        const isOther = relationshipSelectEl.value.toLowerCase() === "other";
        relationshipOtherEl.classList.toggle("d-none", !isOther);
        if (!isOther) {
            relationshipOtherEl.value = "";
        }
    };
    if (relationshipSelectEl) {
        relationshipSelectEl.addEventListener("change", () => {
            hasInteracted = true;
            toggleRelationshipOther();
            updateSaveState();
        });
    }

    saveBtn.addEventListener("click", async () => {
        saveBtn.disabled = true;
        setMessage("");

        if (!validate()) {
            saveBtn.disabled = false;
            return;
        }

        const relationshipSelect = getValue("emergencyRelationship");
        const relationshipOther = getValue("emergencyRelationshipOther");
        const relationshipValue =
            relationshipSelect.toLowerCase() === "other" && relationshipOther
                ? relationshipOther
                : relationshipSelect;

        const payload = {
            last_name: getValue("emergencyLastName"),
            first_name: getValue("emergencyFirstName"),
            middle_name: getValue("emergencyMiddleName"),
            suffix: getValue("emergencySuffix"),
            phone_number: getValue("emergencyContact"),
            relationship: relationshipValue,
            address: getValue("emergencyAddress"),
        };

        try {
            const res = await fetch("../PhpFiles/Resident-End/resident_emergency_update.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    ...(csrfToken ? { "X-CSRF-TOKEN": csrfToken } : {}),
                },
                body: JSON.stringify(payload),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(data.message || "Failed to update emergency contact.");
            }
            if (isPendingDuplicateResponse(data.message)) {
                isPendingRequest = true;
                updateSaveState();
                showNotice("Pending Request", data.message || "You already have a pending emergency edit request.");
                return;
            }
            if (resultEl) resultEl.textContent = "";
            if (noticeTitleEl) noticeTitleEl.textContent = "Request Submitted";
            if (noticeBodyEl) noticeBodyEl.textContent = data.message || "Emergency edit request submitted.";
            if (noticeModalEl && window.bootstrap?.Modal) {
                bootstrap.Modal.getOrCreateInstance(noticeModalEl).show();
            }
            setTimeout(() => {
                window.location.reload();
            }, 800);
        } catch (err) {
            setMessage(err?.message || "Failed to update emergency contact.", true);
        } finally {
            saveBtn.disabled = false;
        }
    });

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
                const res = await fetch("../PhpFiles/Resident-End/edit_request_status.php");
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success) {
                    if (data.pending?.emergency) {
                        isPendingRequest = true;
                        if (resultEl) resultEl.textContent = "";
                        if (modalEl && window.bootstrap?.Modal) {
                            const modal = bootstrap.Modal.getInstance(modalEl);
                            if (modal) modal.hide();
                        }
                    }
                    if (data.denied?.emergency && deniedAlert) {
                        const remarks = data.denied.emergency.remarks?.trim();
                        const reviewedAt = data.denied.emergency.reviewed_at;
                        let msg = "Your last emergency edit request was denied.";
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
                updateSaveState();
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
            showNotice("Pending Request", "You already have a pending emergency edit request.");
            return;
        }

        openModal();

        if (!statusLoaded) {
            await primeStatus();
            if (isPendingRequest) {
                showNotice("Pending Request", "You already have a pending emergency edit request.");
            }
        }
    };
    if (modalTrigger) {
        modalTrigger.addEventListener("click", handlePendingClick);
    }
    if (modalEl) {
        modalEl.addEventListener("show.bs.modal", (event) => {
            if (!canEdit) {
                showEditBlocked(event);
            }
        });
        modalEl.addEventListener("hidden.bs.modal", () => {
            resetForm();
        });
    }

    primeStatus();
    toggleRelationshipOther();
    updateSaveState();
});
