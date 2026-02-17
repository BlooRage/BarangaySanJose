document.addEventListener("DOMContentLoaded", () => {
    const saveBtn = document.getElementById("btnSaveAddress");
    if (!saveBtn) return;

    const resultEl = document.getElementById("addressSaveResult");
    const headBlock = document.getElementById("headReassignBlock");
    const headSelect = document.getElementById("newHeadResidentId");
    const headEmpty = document.getElementById("headReassignEmpty");
    const headLoading = document.getElementById("headReassignLoading");
    const deniedAlert = document.getElementById("addressDeniedAlert");
    const deniedText = document.getElementById("addressDeniedText");
    const modalTrigger =
        document.getElementById("btnOpenEditAddress") ||
        document.querySelector('[data-bs-target="#addAddressModal"]');
    const modalEl = document.getElementById("addAddressModal");
    const noticeModalEl = document.getElementById("residentNoticeModal");
    const noticeTitleEl = document.getElementById("residentNoticeTitle");
    const noticeBodyEl = document.getElementById("residentNoticeBody");
    const canEdit = window.RESIDENT_PROFILE_EDIT_ALLOWED !== false;
    const editBlockedMessage =
        window.RESIDENT_PROFILE_EDIT_BLOCK_MESSAGE ||
        "Your account must be verified before you can edit your address.";
    const modalInstance =
        modalEl && window.bootstrap?.Modal ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    const noticeModalInstance =
        noticeModalEl && window.bootstrap?.Modal
            ? bootstrap.Modal.getOrCreateInstance(noticeModalEl)
            : null;
    let requiresReassign = false;
    const isHead = headBlock?.dataset?.isHead === "1";
    const fieldIds = [
        "addressUnitNumber",
        "addressStreetNumber",
        "addressStreetName",
        "addressPhaseNumber",
        "addressSubdivision",
        "addressAreaNumber",
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

    const isValidTextField = (value) => {
        if (value === "") return true;
        return /^[A-Za-z .,'-]+$/.test(value);
    };

    const isValidNumberField = (value) => {
        if (value === "") return true;
        return /^\d+$/.test(value);
    };

    const areaOptions = new Set([
        "Area 01",
        "Area 1A",
        "Area 02",
        "Area 03",
        "Area 04",
        "Area 05",
        "Area 06",
    ]);

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

    const validate = () => {
        const streetNumber = getValue("addressStreetNumber");
        const streetName = getValue("addressStreetName");
        const areaNumber = getValue("addressAreaNumber");

        if (!streetNumber || !streetName || !areaNumber) {
            setMessage("Street number, street name, and area number are required.", true);
            return false;
        }

        const fields = [
            { id: "addressUnitNumber", label: "Unit number", max: 50, type: "number" },
            { id: "addressStreetNumber", label: "Street number", max: 50, type: "number" },
            { id: "addressStreetName", label: "Street name", max: 150, type: "text", gibberish: true },
            { id: "addressPhaseNumber", label: "Phase number", max: 50, type: "number" },
            { id: "addressSubdivision", label: "Subdivision", max: 150, type: "text", gibberish: true },
            { id: "addressAreaNumber", label: "Area number", max: 50, type: "area" },
        ];
        for (const field of fields) {
            const value = getValue(field.id);
            if (value && field.max && value.length > field.max) {
                setMessage(`${field.label} must be ${field.max} characters or less.`, true);
                return false;
            }
            if (field.type === "number" && value && !isValidNumberField(value)) {
                setMessage(`${field.label} must contain numbers only.`, true);
                return false;
            }
            if (field.type === "area" && value && !areaOptions.has(value)) {
                setMessage("Please select a valid area.", true);
                return false;
            }
            if (field.type === "text" && value && !isValidTextField(value)) {
                setMessage(`${field.label} must contain letters only.`, true);
                return false;
            }
            if (value && field.gibberish && looksLikeGibberish(value)) {
                setMessage(`${field.label} looks invalid. Please enter a real ${field.label.toLowerCase()}.`, true);
                return false;
            }
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
        if (headSelect) headSelect.value = "";
        if (resultEl) resultEl.textContent = "";
        if (headEmpty) headEmpty.classList.add("d-none");
        updateSaveState();
    };

    let isPendingRequest = false;

    const updateSaveState = () => {
        const hasChanges = isDirty();
        const valid = hasChanges ? validate() : true;
        const needsHead = requiresReassign && headSelect && !headSelect.value.trim();
        saveBtn.disabled = !hasChanges || !valid || needsHead || isPendingRequest;
    };

    fieldIds.forEach((id) => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener("input", updateSaveState);
        }
    });
    if (headSelect) {
        headSelect.addEventListener("change", updateSaveState);
    }

    saveBtn.addEventListener("click", async () => {
        saveBtn.disabled = true;
        setMessage("");

        if (!validate()) {
            saveBtn.disabled = false;
            return;
        }

        if (requiresReassign) {
            const newHeadId = headSelect ? headSelect.value.trim() : "";
            if (!newHeadId) {
                setMessage("Please assign a new head of household first.", true);
                saveBtn.disabled = false;
                return;
            }
        }

        const payload = {
            unit_number: getValue("addressUnitNumber"),
            street_number: getValue("addressStreetNumber"),
            street_name: getValue("addressStreetName"),
            phase_number: getValue("addressPhaseNumber"),
            subdivision: getValue("addressSubdivision"),
            area_number: getValue("addressAreaNumber"),
        };
        if (requiresReassign && headSelect) {
            payload.new_head_resident_id = headSelect.value.trim();
        }

        try {
            const res = await fetch("../PhpFiles/Resident-End/resident_address_update.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(data.message || "Failed to update address.");
            }
            if (isPendingDuplicateResponse(data.message)) {
                isPendingRequest = true;
                updateSaveState();
                showNotice("Pending Request", data.message || "You already have a pending address edit request.");
                return;
            }

            if (resultEl) resultEl.textContent = "";
            if (noticeTitleEl) noticeTitleEl.textContent = "Request Submitted";
            if (noticeBodyEl) noticeBodyEl.textContent = data.message || "Address edit request submitted.";
            if (noticeModalEl && window.bootstrap?.Modal) {
                bootstrap.Modal.getOrCreateInstance(noticeModalEl).show();
            }
            window.dispatchEvent(new CustomEvent("household:updated"));
            setTimeout(() => {
                window.location.reload();
            }, 800);
        } catch (err) {
            setMessage(err?.message || "Failed to update address.", true);
        } finally {
            saveBtn.disabled = false;
        }
    });

    const loadHeadReassign = async () => {
        if (!headBlock || !headSelect) return;
        if (headLoading) headLoading.classList.remove("d-none");
        try {
            const res = await fetch("../PhpFiles/Resident-End/household_members.php");
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success || !data.is_head || !data.has_household) {
                return;
            }

            const eligible = (data.members || []).filter(
                (m) => m && m.resident_id && m.role !== "Head"
            );

            headSelect.innerHTML = '<option value="">Select a member</option>';
            if (eligible.length === 0) {
            if (headEmpty) headEmpty.classList.remove("d-none");
            headBlock.classList.remove("d-none");
            requiresReassign = true;
            saveBtn.disabled = true;
            return;
        }

            eligible.forEach((m) => {
                const opt = document.createElement("option");
                opt.value = m.resident_id;
                opt.textContent = m.name || "Member";
                headSelect.appendChild(opt);
            });

            if (headEmpty) headEmpty.classList.add("d-none");
            headBlock.classList.remove("d-none");
            requiresReassign = true;
            updateSaveState();
        } catch (e) {
            // ignore
        } finally {
            if (headLoading) headLoading.classList.add("d-none");
        }
    };

    if (headBlock && isHead) {
        headBlock.classList.remove("d-none");
        requiresReassign = true;
        saveBtn.disabled = true;
    }
    loadHeadReassign();
    const showNotice = (title, message) => {
        if (noticeTitleEl) noticeTitleEl.textContent = title || "Notice";
        if (noticeBodyEl) noticeBodyEl.textContent = message || "";
        if (!noticeModalEl || !window.bootstrap?.Modal) return;
        if (modalInstance) {
            modalInstance.hide();
        }
        if (noticeModalInstance) {
            noticeModalInstance.show();
            return;
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
<<<<<<< Updated upstream
<<<<<<< Updated upstream
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
                    message: "Saving changes will send a request for review. Every applied change request requires supporting document/s for verification. Changing your address will remove you from your household.",
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
        if (modalInstance) {
            modalInstance.show();
            return;
        }
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    };

    const primeStatus = () => {
        if (statusPromise) return statusPromise;
        statusPromise = (async () => {
            try {
                const res = await fetch("../PhpFiles/Resident-End/edit_request_status.php?scope=pending&request_type=address");
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success) {
                    if (data.pending) {
                        isPendingRequest = true;
                        if (resultEl) resultEl.textContent = "";
                        if (modalEl && window.bootstrap?.Modal) {
                            const modal = bootstrap.Modal.getInstance(modalEl);
                            if (modal) modal.hide();
                        }
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
            showNotice("Pending Request", "You already have a pending address edit request.");
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
            showNotice("Pending Request", "You already have a pending address edit request.");
        });
        modalEl.addEventListener("hidden.bs.modal", () => {
            resetForm();
        });
    }
    primeStatus();
    updateSaveState();
});
