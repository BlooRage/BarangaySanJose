document.addEventListener("DOMContentLoaded", () => {
    const normalizePhone = (value) => {
        const digits = (value || "").replace(/\D/g, "");
        if (digits.startsWith("63") && digits.length === 12) {
            return "0" + digits.slice(2);
        }
        if (digits.startsWith("9") && digits.length === 10) {
            return "0" + digits;
        }
        if (digits.length === 9 && digits.startsWith("9")) {
            return "0" + digits;
        }
        return digits;
    };

    const sanitizePhoneInput = (input) => {
        if (!input) return;
        const digits = (input.value || "").replace(/\D/g, "").slice(0, 9);
        input.value = digits;
    };

    const blockNonDigitKeypress = (event) => {
        const allowedKeys = ["Backspace", "Delete", "ArrowLeft", "ArrowRight", "Tab", "Home", "End"];
        if (allowedKeys.includes(event.key)) {
            return;
        }
        if (!/^\d$/.test(event.key)) {
            event.preventDefault();
        }
    };

    const addInvitePhoneBtn = document.getElementById("btnAddInvitePhone");
    const invitePhoneList = document.getElementById("householdInvitePhoneList");
    if (addInvitePhoneBtn && invitePhoneList) {
        addInvitePhoneBtn.addEventListener("click", () => {
            const wrapper = document.createElement("div");
            wrapper.className = "input-group";
            const prefix = document.createElement("span");
            prefix.className = "input-group-text";
            prefix.textContent = "+63";
            const input = document.createElement("input");
            input.type = "text";
            input.className = "form-control household-invite-phone";
            input.placeholder = "9XXXXXXXXX";
            input.inputMode = "numeric";
            input.maxLength = 9;
            input.setAttribute("pattern", "^\\d{9}$");
            input.addEventListener("input", () => sanitizePhoneInput(input));
            input.addEventListener("keydown", blockNonDigitKeypress);
            const removeBtn = document.createElement("button");
            removeBtn.type = "button";
            removeBtn.className = "btn btn-outline-danger";
            removeBtn.textContent = "Remove";
            removeBtn.addEventListener("click", () => {
                wrapper.remove();
            });
            wrapper.appendChild(prefix);
            wrapper.appendChild(input);
            wrapper.appendChild(removeBtn);
            invitePhoneList.appendChild(wrapper);
        });
    }

    const householdInviteBtn = document.getElementById("btnSendHouseholdInvite");
    if (householdInviteBtn) {
        householdInviteBtn.addEventListener("click", async () => {
            const result = document.getElementById("householdInviteResult");
            const inputs = document.querySelectorAll(".household-invite-phone");
            inputs.forEach((el) => sanitizePhoneInput(el));
            const rawNumbers = Array.from(inputs).map((el) => normalizePhone((el.value || "").trim()));
            const validPattern = /^09\d{9}$/;
            const invalidNumbers = rawNumbers.filter((val) => val !== "" && !validPattern.test(val));
            inputs.forEach((el) => {
                const val = normalizePhone((el.value || "").trim());
                if (val !== "" && !validPattern.test(val)) {
                    el.classList.add("is-invalid");
                } else {
                    el.classList.remove("is-invalid");
                    if (val !== "") {
                        el.value = val;
                    }
                }
            });
            if (invalidNumbers.length) {
                if (result) {
                    result.className = "small mt-2 text-danger";
                    result.textContent = "Invalid number(s). Use +63 then 9 digits.";
                }
                return;
            }
            const phoneNumbers = rawNumbers.filter((val) => val !== "").join("\n");
            if (result) {
                result.className = "small mt-2 text-muted";
                result.textContent = "Sending invites...";
            }
            householdInviteBtn.disabled = true;
            try {
                const res = await fetch("../PhpFiles/SMSHandlers/household_invite_send.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ phone_numbers: phoneNumbers }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    throw new Error(data.message || "Failed to send invites.");
                }
                const lines = [];
                if (data.code) {
                    lines.push(`Invite code: ${data.code}`);
                }
                if (typeof data.sent_count === "number") {
                    lines.push(`Sent to ${data.sent_count} number(s).`);
                }
                if (Array.isArray(data.invalid_numbers) && data.invalid_numbers.length) {
                    lines.push(`Invalid: ${data.invalid_numbers.join(", ")}`);
                }
                if (Array.isArray(data.failed_numbers) && data.failed_numbers.length) {
                    lines.push(`Failed: ${data.failed_numbers.join(", ")}`);
                }
                if (result) {
                    result.className = "small mt-2 text-success";
                    result.textContent = lines.join(" ");
                }
            } catch (err) {
                if (result) {
                    result.className = "small mt-2 text-danger";
                    result.textContent = err?.message || "Failed to send invites.";
                }
            } finally {
                householdInviteBtn.disabled = false;
            }
        });
    }

    const addMemberBtn = document.getElementById("btnAddHouseholdMemberInfo");
    if (addMemberBtn) {
        const nameFields = [
            document.getElementById("hmLastName"),
            document.getElementById("hmFirstName"),
            document.getElementById("hmMiddleName"),
            document.getElementById("hmSuffix"),
        ].filter(Boolean);
        const birthdateField = document.getElementById("hmBirthdate");
        const namePattern = /^[A-Za-z\s]+$/;

        const touched = new Set();

        const validateNames = (showErrors) => {
            let valid = true;
            const lastName = document.getElementById("hmLastName");
            const firstName = document.getElementById("hmFirstName");

            const requiredFields = [lastName, firstName].filter(Boolean);
            requiredFields.forEach((field) => {
                const value = (field.value || "").trim();
                const isValid = value !== "" && namePattern.test(value);
                if (showErrors && (touched.has(field.id) || value !== "")) {
                    field.classList.toggle("is-invalid", !isValid);
                } else if (value === "") {
                    field.classList.remove("is-invalid");
                }
                if (!isValid) valid = false;
            });

            nameFields.forEach((field) => {
                const value = (field.value || "").trim();
                const isOptionalInvalid = value !== "" && !namePattern.test(value);
                if (showErrors && (touched.has(field.id) || value !== "")) {
                    field.classList.toggle("is-invalid", isOptionalInvalid);
                } else if (value === "") {
                    field.classList.remove("is-invalid");
                }
                if (isOptionalInvalid) valid = false;
            });

            addMemberBtn.disabled = !valid;
            return valid;
        };

        nameFields.forEach((field) => {
            field.addEventListener("input", () => {
                touched.add(field.id);
                validateNames(true);
            });
            field.addEventListener("blur", () => {
                touched.add(field.id);
                validateNames(true);
            });
        });
        if (birthdateField) {
            birthdateField.addEventListener("input", () => validateNames(true));
        }
        validateNames(false);

        addMemberBtn.addEventListener("click", async () => {
            const result = document.getElementById("householdMemberAddResult");
            const lastName = document.getElementById("hmLastName")?.value.trim() || "";
            const firstName = document.getElementById("hmFirstName")?.value.trim() || "";
            const middleName = document.getElementById("hmMiddleName")?.value.trim() || "";
            const suffix = document.getElementById("hmSuffix")?.value.trim() || "";
            const birthdate = document.getElementById("hmBirthdate")?.value || "";

            if (!validateNames(true)) {
                if (result) {
                    result.className = "small mt-2 text-danger";
                    result.textContent = "Please provide valid names (letters and spaces only).";
                }
                return;
            }

            if (result) {
                result.className = "small mt-2 text-muted";
                result.textContent = "Adding member...";
            }
            addMemberBtn.disabled = true;
            try {
                const res = await fetch("../PhpFiles/Resident-End/household_member_add.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        last_name: lastName,
                        first_name: firstName,
                        middle_name: middleName,
                        suffix,
                        birthdate,
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    throw new Error(data.message || "Failed to add member.");
                }
                if (result) {
                    result.className = "small mt-2 text-success";
                    result.textContent = "Member added successfully.";
                }
                const fields = ["hmLastName", "hmFirstName", "hmMiddleName", "hmSuffix", "hmBirthdate"];
                fields.forEach((id) => {
                    const el = document.getElementById(id);
                    if (el) el.value = "";
                });
                touched.clear();
                validateNames(false);
                addMemberBtn.disabled = true;
                window.dispatchEvent(new Event("household:updated"));
            } catch (err) {
                if (result) {
                    result.className = "small mt-2 text-danger";
                    result.textContent = err?.message || "Failed to add member.";
                }
            } finally {
                addMemberBtn.disabled = false;
            }
        });
    }

    const initialPhoneInput = document.querySelector(".household-invite-phone");
    if (initialPhoneInput) {
        initialPhoneInput.addEventListener("input", () => sanitizePhoneInput(initialPhoneInput));
        initialPhoneInput.addEventListener("keydown", blockNonDigitKeypress);
    }
});
