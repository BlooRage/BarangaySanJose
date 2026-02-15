document.addEventListener("DOMContentLoaded", () => {
    const saveBtn = document.getElementById("btnSaveAddress");
    if (!saveBtn) return;

    const resultEl = document.getElementById("addressSaveResult");
    const headBlock = document.getElementById("headReassignBlock");
    const headSelect = document.getElementById("newHeadResidentId");
    const headEmpty = document.getElementById("headReassignEmpty");
    const deniedAlert = document.getElementById("addressDeniedAlert");
    const deniedText = document.getElementById("addressDeniedText");
    let requiresReassign = false;
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

    const isValidAddressLike = (value) => {
        if (value === "") return true;
        return /^[A-Za-z0-9 .,'#()\/&-]+$/.test(value);
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
            { id: "addressUnitNumber", label: "Unit number", max: 50 },
            { id: "addressStreetNumber", label: "Street number", max: 50 },
            { id: "addressStreetName", label: "Street name", max: 150 },
            { id: "addressPhaseNumber", label: "Phase number", max: 50 },
            { id: "addressSubdivision", label: "Subdivision", max: 150 },
            { id: "addressAreaNumber", label: "Area number", max: 50 },
        ];
        for (const field of fields) {
            const value = getValue(field.id);
            if (value && field.max && value.length > field.max) {
                setMessage(`${field.label} must be ${field.max} characters or less.`, true);
                return false;
            }
            if (value && !isValidAddressLike(value)) {
                setMessage(`${field.label} contains invalid characters.`, true);
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

            setMessage(data.message || "Address edit request submitted.");
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
        }
    };

    loadHeadReassign();
    (async () => {
        try {
            const res = await fetch("../PhpFiles/Resident-End/edit_request_status.php");
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success) {
                if (data.pending?.address) {
                    isPendingRequest = true;
                    setMessage("You already have a pending address edit request.", true);
                }
                if (data.denied?.address && deniedAlert) {
                    const remarks = data.denied.address.remarks?.trim();
                    const reviewedAt = data.denied.address.reviewed_at;
                    let msg = "Your last address edit request was denied.";
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
            updateSaveState();
        }
    })();
    updateSaveState();
});
