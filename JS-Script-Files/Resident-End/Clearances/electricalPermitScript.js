document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("form");
    const submitBtn = form?.querySelector(".submit-btn");
    const landOwnerDetails = document.getElementById("landOwnerDetails");
    const tctNumberInput = document.getElementById("tct_number");
    const taxDeclarationInput = document.getElementById("tax_declaration_number");
    const tctError = document.getElementById("tct_number_error");
    const taxError = document.getElementById("tax_declaration_number_error");
    const tctRegex = /^(TCT|T)?\d{5,10}$/i;
    const taxRegex = /^(TD)?\d{3,10}\d{0,5}$/i;
    const normalizeId = (value) => value.replace(/[\s-]+/g, "");
    if (!form || !submitBtn) return;

    const updateState = () => {
        if (tctNumberInput) {
            const rawValue = tctNumberInput.value.trim();
            const normalized = normalizeId(rawValue);
            const hasValue = rawValue !== "";
            const isInvalid = hasValue && !tctRegex.test(normalized);
            tctNumberInput.setCustomValidity(isInvalid ? "Invalid TCT number" : "");
            if (tctError) {
                tctError.classList.toggle("d-none", !isInvalid);
            }
        }
        if (taxDeclarationInput) {
            const rawValue = taxDeclarationInput.value.trim();
            const normalized = normalizeId(rawValue);
            const hasValue = rawValue !== "";
            const isInvalid = hasValue && !taxRegex.test(normalized);
            taxDeclarationInput.setCustomValidity(isInvalid ? "Invalid Tax Declaration number" : "");
            if (taxError) {
                taxError.classList.toggle("d-none", !isInvalid);
            }
        }
        const selected = document.querySelector('input[name="is_land_owner"]:checked');
        const needsOwner = selected?.value === "No";
        if (landOwnerDetails) {
            landOwnerDetails.classList.toggle("d-none", !needsOwner);
            landOwnerDetails.querySelectorAll("input").forEach((input) => {
                input.required = needsOwner && input.name !== "land_owner_middle_name" && input.name !== "land_owner_suffix";
            });
        }
        submitBtn.disabled = !form.checkValidity();
    };

    form.addEventListener("input", updateState);
    form.addEventListener("change", updateState);
    updateState();
});
