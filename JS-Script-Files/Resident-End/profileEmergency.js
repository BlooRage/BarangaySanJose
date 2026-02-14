document.addEventListener("DOMContentLoaded", () => {
    const saveBtn = document.getElementById("btnSaveEmergency");
    if (!saveBtn) return;

    const resultEl = document.getElementById("emergencySaveResult");
    const deniedAlert = document.getElementById("emergencyDeniedAlert");
    const deniedText = document.getElementById("emergencyDeniedText");
    const fieldIds = [
        "emergencyLastName",
        "emergencyFirstName",
        "emergencyMiddleName",
        "emergencySuffix",
        "emergencyContact",
        "emergencyRelationship",
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

    const isValidPersonName = (value, minLetters = 1, maxLen = 50) => {
        if (!value) return false;
        if (value.length > maxLen) return false;
        if (!/^[A-Za-z.' -]+$/.test(value)) return false;
        const letters = value.match(/[A-Za-z]/g) || [];
        return letters.length >= minLetters;
    };

    const isValidAlphaText = (value) => {
        if (!value) return false;
        return /^[A-Za-z .,'-]+$/.test(value);
    };

    const isValidAddressLike = (value) => {
        if (!value) return false;
        return /^[A-Za-z0-9 .,'#()\/&-]+$/.test(value);
    };

    const validate = () => {
        const lastName = getValue("emergencyLastName");
        const firstName = getValue("emergencyFirstName");
        const middleName = getValue("emergencyMiddleName");
        const contact = getValue("emergencyContact");
        const relationship = getValue("emergencyRelationship");
        const address = getValue("emergencyAddress");

        if (!lastName || !firstName || !contact || !relationship || !address) {
            setMessage("Please fill in all required fields.", true);
            return false;
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
            setMessage("Relationship contains invalid characters.", true);
            return false;
        }
        if (address.length > 255) {
            setMessage("Address must be 255 characters or less.", true);
            return false;
        }
        if (!isValidAddressLike(address)) {
            setMessage("Address contains invalid characters.", true);
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

    let isPendingRequest = false;

    const updateSaveState = () => {
        const hasChanges = isDirty();
        const valid = hasChanges ? validate() : true;
        saveBtn.disabled = !hasChanges || !valid || isPendingRequest;
    };

    fieldIds.forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.addEventListener("input", updateSaveState);
    });

    saveBtn.addEventListener("click", async () => {
        saveBtn.disabled = true;
        setMessage("");

        if (!validate()) {
            saveBtn.disabled = false;
            return;
        }

        const payload = {
            last_name: getValue("emergencyLastName"),
            first_name: getValue("emergencyFirstName"),
            middle_name: getValue("emergencyMiddleName"),
            suffix: getValue("emergencySuffix"),
            phone_number: getValue("emergencyContact"),
            relationship: getValue("emergencyRelationship"),
            address: getValue("emergencyAddress"),
        };

        try {
            const res = await fetch("../PhpFiles/Resident-End/resident_emergency_update.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(data.message || "Failed to update emergency contact.");
            }
            setMessage(data.message || "Emergency edit request submitted.");
            setTimeout(() => {
                window.location.reload();
            }, 800);
        } catch (err) {
            setMessage(err?.message || "Failed to update emergency contact.", true);
        } finally {
            saveBtn.disabled = false;
        }
    });

    (async () => {
        try {
            const res = await fetch("../PhpFiles/Resident-End/edit_request_status.php");
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success) {
                if (data.pending?.emergency) {
                    isPendingRequest = true;
                    setMessage("You already have a pending emergency edit request.", true);
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
            updateSaveState();
        }
    })();
    updateSaveState();
});
