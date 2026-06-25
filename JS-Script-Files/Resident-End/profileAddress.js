document.addEventListener("DOMContentLoaded", () => {
    const csrfToken = String(window.RESIDENT_CSRF_TOKEN || "").trim();
    const AUTO_RESIDENCY_DURATION = "Less than 6 months";
    const saveBtn = document.getElementById("btnSaveAddress");
    const reviewBtn = document.getElementById("btnAddressReview");
    if (!saveBtn || !reviewBtn) return;

    const resultEl = document.getElementById("addressSaveResult");
    const uploadResultEl = document.getElementById("addressUploadResult");
    const deniedAlert = document.getElementById("addressDeniedAlert");
    const deniedText = document.getElementById("addressDeniedText");
    const modalTrigger =
        document.getElementById("btnOpenEditAddress") ||
        document.querySelector('[data-bs-target="#addAddressModal"]');
    const modalEl = document.getElementById("addAddressModal");
    const uploadModalEl = document.getElementById("editAddressUploadModal");
    const backToFormBtn = document.getElementById("btnAddressBackToForm");
    const noticeModalEl = document.getElementById("residentNoticeModal");
    const noticeTitleEl = document.getElementById("residentNoticeTitle");
    const noticeBodyEl = document.getElementById("residentNoticeBody");
    const beforeModalEl = document.getElementById("beforeEditModal");
    const beforeContinueBtn = document.getElementById("btnBeforeEditContinue");
    const canEdit = window.RESIDENT_PROFILE_EDIT_ALLOWED !== false;
    const editBlockedMessage =
        window.RESIDENT_PROFILE_EDIT_BLOCK_MESSAGE ||
        "Your account must be verified before you can change your address.";
    let suppressFormResetOnHide = false;
    const addressSystemEl = document.getElementById("addressSystemEdit");
    const houseWrapper = document.getElementById("addressHouseWrapper");
    const lotBlockWrapper = document.getElementById("addressLotBlockWrapper");
    const houseTypeEl = document.getElementById("addressHouseType");
    const houseTypeOtherEl = document.getElementById("addressHouseTypeOther");
    const addressSupportTypeEl = document.getElementById("addressSupportType");
    const addressSupportFileEl = document.getElementById("addressSupportFile");
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
        "addressSupportType",
        "addressSupportFile",
        "addressResidencyDuration",
    ];
    const allowedSupportDocTypes = new Set([
        "Contract of Lease",
        "Transfer Certificate of Title",
        "Tax Declaration",
    ]);
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

    const getCurrentProfilePayload = () => ({
        address_system: modalEl?.dataset?.currentAddressSystem || "house",
        unit_number: modalEl?.dataset?.currentUnitNumber || "",
        street_number: modalEl?.dataset?.currentStreetNumber || "",
        street_name: modalEl?.dataset?.currentStreetName || "",
        phase_number: modalEl?.dataset?.currentPhaseNumber || "",
        subdivision: modalEl?.dataset?.currentSubdivision || "",
        area_number: modalEl?.dataset?.currentAreaNumber || "",
        house_ownership: modalEl?.dataset?.currentHouseOwnership || "",
        house_type: modalEl?.dataset?.currentHouseType || "",
        residency_duration: modalEl?.dataset?.currentResidencyDuration || AUTO_RESIDENCY_DURATION,
    });

    const setMessage = (message, isError = false) => {
        if (!resultEl) return;
        resultEl.textContent = message || "";
        resultEl.className = isError ? "small mb-2 text-danger" : "small mb-2 text-success";
    };

    const setUploadMessage = (message, isError = false) => {
        if (!uploadResultEl) return;
        uploadResultEl.textContent = message || "";
        uploadResultEl.className = isError ? "small mt-2 text-danger" : "small mt-2 text-success";
    };

    const clearFieldErrors = () => {
        fieldIds.forEach((id) => {
            const el = document.getElementById(id);
            if (el) el.classList.remove("is-invalid");
        });
    };

    const failField = (fieldId, message, showErrors = true) => {
        if (!showErrors) {
            return false;
        }
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

    const validate = ({ showErrors = true, includeSupportDocs = true } = {}) => {
        if (showErrors) {
            clearFieldErrors();
        }
        const system = getAddressSystem();
        if (!["house", "lot_block"].includes(system)) {
            return failField("addressSystemEdit", "Please select an address system.", showErrors);
        }
        const address = getAddressFields();

        if (system === "house") {
            if (!address.street_number) return failField("addressStreetNumberHouse", "House number is required.", showErrors);
            if (!address.street_name) return failField("addressStreetNameHouse", "Street name is required.", showErrors);
            if (!address.area_number) return failField("addressAreaNumberHouse", "Area is required.", showErrors);
        } else {
            if (!address.street_number) return failField("addressLotNumber", "Lot number is required.", showErrors);
            if (!address.phase_number) return failField("addressBlockNumber", "Block number is required.", showErrors);
            if (!address.area_number) return failField("addressAreaNumberLot", "Area is required.", showErrors);
        }

        if (!getValue("addressHouseOwnership") || !getHouseTypeValue()) {
            if (!getValue("addressHouseOwnership")) return failField("addressHouseOwnership", "House ownership is required.", showErrors);
            if (!getHouseTypeValue()) return failField("addressHouseType", "House type is required.", showErrors);
            return false;
        }

        if (includeSupportDocs) {
            const supportType = addressSupportTypeEl ? addressSupportTypeEl.value.trim() : "";
            const supportFiles = addressSupportFileEl ? Array.from(addressSupportFileEl.files || []) : [];
            if (!allowedSupportDocTypes.has(supportType)) {
                return failField("addressSupportType", "Please select a valid supporting document type.", showErrors);
            }
            if (supportFiles.length === 0) {
                return failField("addressSupportFile", "Please upload at least one supporting document.", showErrors);
            }
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
                return failField(field.id, `${field.label} must be ${field.max} characters or less.`, showErrors);
            }
            if (field.type === "number" && value && !isValidAddressLikeField(value)) {
                return failField(field.id, `${field.label} contains invalid characters.`, showErrors);
            }
            if (field.type === "area" && value && !areaOptions.has(value)) {
                return failField(field.id, "Please select a valid area.", showErrors);
            }
            if (field.type === "text" && value && !isValidAddressLikeField(value)) {
                return failField(field.id, `${field.label} contains invalid characters.`, showErrors);
            }
            if (value && field.gibberish && looksLikeGibberish(value)) {
                return failField(field.id, `${field.label} looks invalid. Please enter a real ${field.label.toLowerCase()}.`, showErrors);
            }
        }

        const houseTypeCustom = getValue("addressHouseTypeOther");
        if (houseTypeCustom && !isValidTextField(houseTypeCustom)) {
            return failField("addressHouseTypeOther", "House type must contain valid text only.", showErrors);
        }

        if (showErrors) {
            setMessage("");
        }
        return true;
    };

    let initialPayload = {};
    let currentProfilePayload = getCurrentProfilePayload();

    const isDirty = () => {
        const current = buildPayload();
        const initial = initialPayload || {};
        return JSON.stringify(current) !== JSON.stringify(initial);
    };

    const escapeHtml = (value) =>
        String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#039;");

    const formatValue = (value) => (value && String(value).trim() !== "" ? String(value) : "N/A");

    const humanizeAddressSystem = (value) =>
        value === "lot_block" ? "Lot/Block System" : value === "house" ? "House Numbering System" : formatValue(value);

    const buildChangeRows = () => {
        const current = buildPayload();
        const initial = currentProfilePayload || {};
        const labels = {
            address_system: "Address System",
            unit_number: "Unit / Apartment Number",
            street_number: current.address_system === "lot_block" ? "Lot" : "House Number",
            street_name: "Street Name",
            phase_number: current.address_system === "lot_block" ? "Block" : "Phase",
            subdivision: "Subdivision",
            area_number: "Area",
            house_ownership: "House Ownership",
            house_type: "House Type",
            residency_duration: "Residency Duration",
        };
        const rows = [];

        Object.entries(labels).forEach(([key, label]) => {
            const fromRaw = initial[key] ?? "";
            const toRaw = current[key] ?? "";
            const from = key === "address_system" ? humanizeAddressSystem(fromRaw) : formatValue(fromRaw);
            const to = key === "address_system" ? humanizeAddressSystem(toRaw) : formatValue(toRaw);
            if (from !== to) {
                rows.push({ field: label, from, to });
            }
        });

        return rows;
    };

    const reviewHtml = (rows) => {
        const items = rows
            .map(
                (row) => `
                <tr>
                    <td class="text-start fw-semibold">${escapeHtml(row.field)}</td>
                    <td class="text-start">${escapeHtml(row.from)}</td>
                    <td class="text-start">${escapeHtml(row.to)}</td>
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

    const resetForm = () => {
        fieldIds.forEach((id) => {
            const el = document.getElementById(id);
            if (!el) return;
            if (el.type === "file") {
                el.value = "";
                return;
            }
            el.value = initialValues[id] ?? "";
        });
        const residencyEl = document.getElementById("addressResidencyDuration");
        if (residencyEl) residencyEl.value = AUTO_RESIDENCY_DURATION;
        toggleAddressSystem();
        toggleHouseTypeOther();
        if (resultEl) resultEl.textContent = "";
        if (uploadResultEl) uploadResultEl.textContent = "";
        clearFieldErrors();
        updateSaveState();
    };

    let isPendingRequest = false;

    const updateSaveState = () => {
        const hasChanges = isDirty();
        const valid = hasChanges ? validate({ showErrors: false, includeSupportDocs: false }) : true;
        if (!hasChanges) {
            setMessage("");
            clearFieldErrors();
        } else if (valid) {
            setMessage("");
            clearFieldErrors();
        } else if (isPendingRequest) {
            setMessage("You already have a pending address change request.", true);
        } else {
            setMessage("");
            clearFieldErrors();
        }
        saveBtn.disabled = !hasChanges || !valid || isPendingRequest;
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

    const openUploadModal = () => {
        if (!uploadModalEl || !window.bootstrap?.Modal) return;
        bootstrap.Modal.getOrCreateInstance(uploadModalEl).show();
    };

    const openReviewStep = async () => {
        setMessage("");
        if (!isDirty()) {
            setMessage("No changes detected.", true);
            return;
        }
        if (!validate({ showErrors: true, includeSupportDocs: false })) {
            return;
        }
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
                    setUploadMessage("Upload the required supporting document(s) before submitting.");
                    openUploadModal();
                    return;
                }
                if (formWasOpen) {
                    openModal();
                }
            });
            return;
        }

        const proceed = window.confirm("Review complete. Continue to document upload?");
        if (proceed) {
            setUploadMessage("Upload the required supporting document(s) before submitting.");
            openUploadModal();
            return;
        }
        if (formWasOpen) {
            openModal();
        }
    };

    reviewBtn.addEventListener("click", openReviewStep);

    saveBtn.addEventListener("click", async () => {
        saveBtn.disabled = true;
        setUploadMessage("");

        if (!validate({ showErrors: true, includeSupportDocs: true })) {
            saveBtn.disabled = false;
            return;
        }

        const payload = buildPayload();
        const formData = new FormData();
        Object.entries(payload).forEach(([key, value]) => {
            formData.append(key, value);
        });
        if (addressSupportTypeEl) {
            formData.append("supporting_address_type", addressSupportTypeEl.value.trim());
        }
        if (csrfToken) {
            formData.append("csrf_token", csrfToken);
        }
        if (addressSupportFileEl) {
            Array.from(addressSupportFileEl.files || []).forEach((file) => {
                formData.append("supporting_address_file[]", file);
            });
        }

        try {
            const res = await fetch("../PhpFiles/Resident-End/resident_address_update.php", {
                method: "POST",
                body: formData,
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
            if (uploadResultEl) uploadResultEl.textContent = "";
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
            setUploadMessage(err?.message || "Failed to update address.", true);
        } finally {
            saveBtn.disabled = false;
        }
    });

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
    currentProfilePayload = getCurrentProfilePayload();
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

    if (backToFormBtn) {
        backToFormBtn.addEventListener("click", () => {
            if (uploadModalEl && window.bootstrap?.Modal) {
                const uploadModal = bootstrap.Modal.getInstance(uploadModalEl);
                if (uploadModal) {
                    uploadModal.hide();
                }
            }
            setTimeout(() => {
                openModal();
            }, 150);
        });
    }

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
                        if (uploadResultEl) uploadResultEl.textContent = "";
                        if (modalEl && window.bootstrap?.Modal) {
                            const modal = bootstrap.Modal.getInstance(modalEl);
                            if (modal) modal.hide();
                        }
                        if (uploadModalEl && window.bootstrap?.Modal) {
                            const uploadModal = bootstrap.Modal.getInstance(uploadModalEl);
                            if (uploadModal) uploadModal.hide();
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
            if (suppressFormResetOnHide) {
                suppressFormResetOnHide = false;
                return;
            }
            resetForm();
        });
    }
    if (uploadModalEl) {
        uploadModalEl.addEventListener("hidden.bs.modal", () => {
            clearFieldErrors();
            setUploadMessage("");
        });
    }
    primeStatus();
    updateSaveState();
});
