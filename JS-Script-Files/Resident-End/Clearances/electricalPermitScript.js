document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("form");
    const submitBtn = form?.querySelector(".submit-btn");
    const landOwnerDetails = document.getElementById("landOwnerDetails");
    const documentUploadSection = document.getElementById("documentUploadSection");
    const lotAddressSystemRow = document.getElementById("lotAddressSystemRow");
    const lotAddressSystem = document.getElementById("lotAddressSystem");
    const lotSameAddress = document.getElementById("lotSameAddress");
    const lotFullAddressWrapper = document.getElementById("lotFullAddressWrapper");
    const lotFullAddress = document.getElementById("lotFullAddress");
    const lotHouseWrapper = document.getElementById("lotHouseSystemWrapper");
    const lotBlockWrapper = document.getElementById("lotBlockSystemWrapper");
    const lotUnitNumber = document.getElementById("lot_unit_number");
    const lotStreetNumber = document.getElementById("lot_street_number");
    const lotStreetName = document.getElementById("lot_street_name");
    const lotSubdivisionHouse = document.getElementById("lot_subdivision");
    const lotNumber = document.getElementById("lot_number");
    const blockNumber = document.getElementById("block_number");
    const lotPhaseNumber = document.getElementById("lot_phase_number");
    const lotSubdivisionBlock = document.getElementById("lot_subdivision_block");
    const applicantUnitNumber = document.getElementById("applicantUnitNumber");
    const applicantStreetNumber = document.getElementById("applicantStreetNumber");
    const applicantStreetName = document.getElementById("applicantStreetName");
    const applicantSubdivision = document.getElementById("applicantSubdivision");
    const applicantFullAddress = document.getElementById("applicantFullAddress");
    if (!form || !submitBtn) return;

    const setWrapperState = (wrapper, enabled) => {
        if (!wrapper) return;
        wrapper.classList.toggle("d-none", !enabled);
        wrapper.querySelectorAll("input, select").forEach((el) => {
            el.disabled = !enabled;
            if (!enabled) {
                el.value = "";
                if (el.type === "checkbox" || el.type === "radio") el.checked = false;
            }
        });
    };

    const setRequired = (el, required) => {
        if (!el) return;
        if (required) el.setAttribute("required", "required");
        else el.removeAttribute("required");
    };

    const setReadOnly = (el, isReadOnly) => {
        if (!el) return;
        el.readOnly = isReadOnly;
        if (isReadOnly) {
            el.classList.add("text-bg-light");
        } else {
            el.classList.remove("text-bg-light");
        }
    };

    const applyLotAddressSystem = () => {
        const val = lotAddressSystem ? lotAddressSystem.value : "";
        setWrapperState(lotHouseWrapper, val === "house");
        setWrapperState(lotBlockWrapper, val === "lot_block");

        setRequired(lotStreetNumber, val === "house");
        setRequired(lotStreetName, val === "house");
        setRequired(lotSubdivisionHouse, false);
        setRequired(lotNumber, val === "lot_block");
        setRequired(blockNumber, val === "lot_block");
        setRequired(lotPhaseNumber, val === "lot_block");
        setRequired(lotSubdivisionBlock, false);
    };

    const syncLotAddress = () => {
        if (!lotSameAddress || !lotAddressSystem) return;
        const useApplicant = lotSameAddress.checked;

        if (useApplicant) {
            if (lotAddressSystemRow) {
                lotAddressSystemRow.classList.add("d-none");
            }
            if (lotFullAddressWrapper) {
                lotFullAddressWrapper.classList.remove("d-none");
            }
            lotAddressSystem.value = "house";
            if (lotFullAddress && applicantFullAddress) {
                lotFullAddress.value = applicantFullAddress.value || lotFullAddress.value || "";
            }
            if (lotUnitNumber && applicantUnitNumber) {
                lotUnitNumber.value = applicantUnitNumber.value || "";
            }
            if (lotStreetNumber && applicantStreetNumber) {
                lotStreetNumber.value = applicantStreetNumber.value || "";
            }
            if (lotStreetName && applicantStreetName) {
                lotStreetName.value = applicantStreetName.value || "";
            }
            if (lotSubdivisionHouse && applicantSubdivision) {
                lotSubdivisionHouse.value = applicantSubdivision.value || "";
            }
        } else {
            if (lotAddressSystemRow) {
                lotAddressSystemRow.classList.remove("d-none");
            }
            if (lotFullAddressWrapper) {
                lotFullAddressWrapper.classList.add("d-none");
            }
            lotAddressSystem.value = "";
        }

        [lotUnitNumber, lotStreetNumber, lotStreetName, lotSubdivisionHouse].forEach((el) =>
            setReadOnly(el, useApplicant)
        );
    };

    const updateState = () => {
        if (documentUploadSection) {
            documentUploadSection.classList.remove("d-none");
        }
        const selected = document.querySelector('input[name="is_land_owner"]:checked');
        const needsOwner = selected?.value === "No";
        if (landOwnerDetails) {
            landOwnerDetails.classList.toggle("d-none", !needsOwner);
            landOwnerDetails.querySelectorAll("input").forEach((input) => {
                input.required = needsOwner && input.name !== "land_owner_middle_name" && input.name !== "land_owner_suffix";
            });
        }
        syncLotAddress();
        applyLotAddressSystem();
        submitBtn.disabled = !form.checkValidity();
    };

    form.addEventListener("input", updateState);
    form.addEventListener("change", updateState);
    lotAddressSystem?.addEventListener("change", updateState);
    lotSameAddress?.addEventListener("change", updateState);
    updateState();
});
