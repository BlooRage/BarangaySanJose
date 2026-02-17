document.addEventListener("DOMContentLoaded", () => {
    const AUTO_RESIDENCY_DURATION = "Less than 6 months";
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
    const beforeModalEl = document.getElementById("beforeChangeAddressModal");
    const beforeContinueBtn = document.getElementById("btnBeforeAddressContinue");
    const canEdit = window.RESIDENT_PROFILE_EDIT_ALLOWED !== false;
    const editBlockedMessage =
        window.RESIDENT_PROFILE_EDIT_BLOCK_MESSAGE ||
        "Your account must be verified before you can change your address.";
    let requiresReassign = false;
    const isHead = headBlock?.dataset?.isHead === "1";
    const addressSystemEl = document.getElementById("addressSystemEdit");
    const houseWrapper = document.getElementById("addressHouseWrapper");
    const lotBlockWrapper = document.getElementById("addressLotBlockWrapper");
    const houseTypeEl = document.getElementById("addressHouseType");
    const houseTypeOtherEl = document.getElementById("addressHouseTypeOther");
    const fieldIds = [
        "addressSystemEdit",
        "addressUnitNumberHouse",
        "addressStreetNumberHouse",
        "addressStreetNameHouse",
        "addressPhaseNumberHouse",
        "addressSubdivisionHouse",
        "addressAreaNumberHouse",
        "addressUnitNumberLot",
        "addressLotNumber",
        "addressBlockNumber",
        "addressStreetNameLot",
        "addressSubdivisionLot",
        "addressAreaNumberLot",
        "addressHouseOwnership",
        "addressHouseType",
        "addressHouseTypeOther",
        "addressResidencyDuration",
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

    const getAddressSystem = () => (addressSystemEl ? addressSystemEl.value : "");

    const getHouseTypeValue = () => {
        const selected = getValue("addressHouseType");
        if (selected !== "Other") return selected;
        return getValue("addressHouseTypeOther");
    };

    const getAddressFields = () => {
        const system = getAddressSystem();
        if (system === "lot_block") {
            return {
                address_system: "lot_block",
                unit_number: getValue("addressUnitNumberLot"),
                street_number: getValue("addressLotNumber"),
                street_name: getValue("addressStreetNameLot"),
                phase_number: getValue("addressBlockNumber"),
                subdivision: getValue("addressSubdivisionLot"),
                area_number: getValue("addressAreaNumberLot"),
            };
        }
        return {
            address_system: "house",
            unit_number: getValue("addressUnitNumberHouse"),
            street_number: getValue("addressStreetNumberHouse"),
            street_name: getValue("addressStreetNameHouse"),
            phase_number: getValue("addressPhaseNumberHouse"),
            subdivision: getValue("addressSubdivisionHouse"),
            area_number: getValue("addressAreaNumberHouse"),
        };
    };

    const buildPayload = () => {
        const addr = getAddressFields();
        return {
            ...addr,
            house_ownership: getValue("addressHouseOwnership"),
            house_type: getHouseTypeValue(),
            residency_duration: AUTO_RESIDENCY_DURATION,
        };
    };

    const setMessage = (message, isError = false) => {
        if (!resultEl) return;
        resultEl.textContent = message || "";
        resultEl.className = isError ? "small mb-2 text-danger" : "small mb-2 text-success";
    };

    const clearFieldErrors = () => {
        fieldIds.forEach((id) => {
            const el = document.getElementById(id);
            if (el) el.classList.remove("is-invalid");
        });
    };

    const failField = (fieldId, message) => {
        clearFieldErrors();
        const el = document.getElementById(fieldId);
        if (el) el.classList.add("is-invalid");
        setMessage(message, true);
        return false;
    };

    const isValidTextField = (value) => {
        if (value === "") return true;
        return /^[A-Za-z .,'-]+$/.test(value);
    };

    const isValidAddressLikeField = (value) => {
        if (value === "") return true;
        return /^[A-Za-z0-9 .,'#()\/&-]+$/.test(value);
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
        clearFieldErrors();
        const system = getAddressSystem();
        if (!["house", "lot_block"].includes(system)) {
            return failField("addressSystemEdit", "Please select an address system.");
        }
        const address = getAddressFields();

        if (system === "house") {
            if (!address.street_number) return failField("addressStreetNumberHouse", "House number is required.");
            if (!address.street_name) return failField("addressStreetNameHouse", "Street name is required.");
            if (!address.area_number) return failField("addressAreaNumberHouse", "Area is required.");
        } else {
            if (!address.street_number) return failField("addressLotNumber", "Lot number is required.");
            if (!address.phase_number) return failField("addressBlockNumber", "Block number is required.");
            if (!address.area_number) return failField("addressAreaNumberLot", "Area is required.");
        }

        if (!getValue("addressHouseOwnership") || !getHouseTypeValue()) {
            if (!getValue("addressHouseOwnership")) return failField("addressHouseOwnership", "House ownership is required.");
            if (!getHouseTypeValue()) return failField("addressHouseType", "House type is required.");
            return false;
        }

        const fields = system === "lot_block"
            ? [
                { id: "addressUnitNumberLot", label: "Unit number", max: 50, type: "number" },
                { id: "addressLotNumber", label: "Lot", max: 50, type: "number" },
                { id: "addressBlockNumber", label: "Block", max: 50, type: "number" },
                { id: "addressStreetNameLot", label: "Street name", max: 150, type: "text", gibberish: true },
                { id: "addressSubdivisionLot", label: "Subdivision", max: 150, type: "text", gibberish: true },
                { id: "addressAreaNumberLot", label: "Area number", max: 50, type: "area" },
              ]
            : [
                { id: "addressUnitNumberHouse", label: "Unit number", max: 50, type: "number" },
                { id: "addressStreetNumberHouse", label: "House number", max: 50, type: "number" },
                { id: "addressStreetNameHouse", label: "Street name", max: 150, type: "text", gibberish: true },
                { id: "addressPhaseNumberHouse", label: "Phase", max: 50, type: "number" },
                { id: "addressSubdivisionHouse", label: "Subdivision", max: 150, type: "text", gibberish: true },
                { id: "addressAreaNumberHouse", label: "Area number", max: 50, type: "area" },
              ];
        for (const field of fields) {
            const value = getValue(field.id);
            if (value && field.max && value.length > field.max) {
                return failField(field.id, `${field.label} must be ${field.max} characters or less.`);
            }
            if (field.type === "number" && value && !isValidAddressLikeField(value)) {
                return failField(field.id, `${field.label} contains invalid characters.`);
            }
            if (field.type === "area" && value && !areaOptions.has(value)) {
                return failField(field.id, "Please select a valid area.");
            }
            if (field.type === "text" && value && !isValidAddressLikeField(value)) {
                return failField(field.id, `${field.label} contains invalid characters.`);
            }
            if (value && field.gibberish && looksLikeGibberish(value)) {
                return failField(field.id, `${field.label} looks invalid. Please enter a real ${field.label.toLowerCase()}.`);
            }
        }

        const houseTypeCustom = getValue("addressHouseTypeOther");
        if (houseTypeCustom && !isValidTextField(houseTypeCustom)) {
            return failField("addressHouseTypeOther", "House type must contain valid text only.");
        }

        setMessage("");
        return true;
    };

    let initialPayload = {};

    const isDirty = () => {
        const current = buildPayload();
        const initial = initialPayload || {};
        return JSON.stringify(current) !== JSON.stringify(initial);
    };

    const resetForm = () => {
        fieldIds.forEach((id) => {
            const el = document.getElementById(id);
            if (el) el.value = initialValues[id] ?? "";
        });
        const residencyEl = document.getElementById("addressResidencyDuration");
        if (residencyEl) residencyEl.value = AUTO_RESIDENCY_DURATION;
        toggleAddressSystem();
        toggleHouseTypeOther();
        if (headSelect) headSelect.value = "";
        if (resultEl) resultEl.textContent = "";
        clearFieldErrors();
        if (headEmpty) headEmpty.classList.add("d-none");
        updateSaveState();
    };

    let isPendingRequest = false;

    const updateSaveState = () => {
        const hasChanges = isDirty();
        const valid = hasChanges ? validate() : true;
        const needsHead = requiresReassign && headSelect && !headSelect.value.trim();
        if (!hasChanges) {
            setMessage("");
            clearFieldErrors();
        } else if (needsHead) {
            setMessage("Please assign a new head of household first.", true);
        } else if (isPendingRequest) {
            setMessage("You already have a pending address change request.", true);
        }
        saveBtn.disabled = !hasChanges || !valid || needsHead || isPendingRequest;
    };

    const toggleAddressSystem = () => {
        const mode = getAddressSystem();
        const isHouse = mode === "house";
        const isLotBlock = mode === "lot_block";
        if (houseWrapper) houseWrapper.classList.toggle("d-none", !isHouse);
        if (lotBlockWrapper) lotBlockWrapper.classList.toggle("d-none", !isLotBlock);

        const clearIds = (ids) => {
            ids.forEach((id) => {
                const el = document.getElementById(id);
                if (el) el.value = "";
            });
        };
        if (isHouse) {
            clearIds(["addressUnitNumberLot", "addressLotNumber", "addressBlockNumber", "addressStreetNameLot", "addressSubdivisionLot", "addressAreaNumberLot"]);
        } else if (isLotBlock) {
            clearIds(["addressUnitNumberHouse", "addressStreetNumberHouse", "addressStreetNameHouse", "addressPhaseNumberHouse", "addressSubdivisionHouse", "addressAreaNumberHouse"]);
        }
    };

    const toggleHouseTypeOther = () => {
        const show = getValue("addressHouseType") === "Other";
        if (houseTypeOtherEl) houseTypeOtherEl.classList.toggle("d-none", !show);
    };

    fieldIds.forEach((id) => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener("input", updateSaveState);
            el.addEventListener("change", updateSaveState);
        }
    });
    if (addressSystemEl) {
        addressSystemEl.addEventListener("change", () => {
            toggleAddressSystem();
            updateSaveState();
        });
    }
    if (houseTypeEl) {
        houseTypeEl.addEventListener("change", () => {
            toggleHouseTypeOther();
            updateSaveState();
        });
    }
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

        const payload = buildPayload();
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
                showNotice("Pending Request", data.message || "You already have a pending address change request.");
                return;
            }

            if (resultEl) resultEl.textContent = "";
            if (noticeTitleEl) noticeTitleEl.textContent = "Request Submitted";
            if (noticeBodyEl) noticeBodyEl.textContent = data.message || "Address change request submitted.";
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
                requiresReassign = false;
                headBlock.classList.add("d-none");
                if (headEmpty) headEmpty.classList.add("d-none");
                updateSaveState();
                return;
            }

            const eligible = (data.members || []).filter(
                (m) => m && m.resident_id && m.role !== "Head"
            );

            headSelect.innerHTML = '<option value="">Select a member</option>';
            if (eligible.length === 0) {
                requiresReassign = false;
                if (headEmpty) headEmpty.classList.add("d-none");
                headBlock.classList.add("d-none");
                updateSaveState();
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
        requiresReassign = false;
    }
    toggleAddressSystem();
    toggleHouseTypeOther();
    fieldIds.forEach((id) => {
        const el = document.getElementById(id);
        initialValues[id] = el ? el.value.trim() : "";
    });
    const residencyEl = document.getElementById("addressResidencyDuration");
    if (residencyEl) {
        residencyEl.value = AUTO_RESIDENCY_DURATION;
        initialValues.addressResidencyDuration = AUTO_RESIDENCY_DURATION;
    }
    initialPayload = buildPayload();
    loadHeadReassign();
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

    const showBeforeYouGo = () => {
        if (!beforeModalEl || !beforeContinueBtn || !window.bootstrap?.Modal) {
            return Promise.resolve(true);
        }
        const modal = bootstrap.Modal.getOrCreateInstance(beforeModalEl);
        return new Promise((resolve) => {
            let done = false;
            const finish = (value) => {
                if (done) return;
                done = true;
                resolve(value);
            };
            const onContinue = (event) => {
                if (event) event.preventDefault();
                modal.hide();
                finish(true);
            };
            const onHidden = () => {
                beforeContinueBtn.removeEventListener("click", onContinue);
                finish(false);
            };
            beforeContinueBtn.addEventListener("click", onContinue);
            beforeModalEl.addEventListener("hidden.bs.modal", onHidden, { once: true });
            modal.show();
        });
    };

    const primeStatus = () => {
        if (statusPromise) return statusPromise;
        statusPromise = (async () => {
            try {
                const res = await fetch("../PhpFiles/Resident-End/edit_request_status.php");
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success) {
                    if (data.pending?.address) {
                        isPendingRequest = true;
                        if (resultEl) resultEl.textContent = "";
                        if (modalEl && window.bootstrap?.Modal) {
                            const modal = bootstrap.Modal.getInstance(modalEl);
                            if (modal) modal.hide();
                        }
                    }
                    if (data.denied?.address && deniedAlert) {
                        const remarks = data.denied.address.remarks?.trim();
                        const reviewedAt = data.denied.address.reviewed_at;
                        let msg = "Your last address change request was denied.";
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
            showNotice("Pending Request", "You already have a pending address change request.");
            return;
        }

        if (!statusLoaded) {
            await primeStatus();
            if (isPendingRequest) {
                showNotice("Pending Request", "You already have a pending address change request.");
                return;
            }
        }

        const proceed = await showBeforeYouGo();
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
            showNotice("Pending Request", "You already have a pending address change request.");
        });
        modalEl.addEventListener("hidden.bs.modal", () => {
            resetForm();
        });
    }
    primeStatus();
    updateSaveState();
});
