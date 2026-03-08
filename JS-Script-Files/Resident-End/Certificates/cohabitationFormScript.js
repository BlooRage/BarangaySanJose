document.addEventListener("DOMContentLoaded", () => {
    const appBase = (() => {
        const marker = "/Resident-End/";
        const idx = window.location.pathname.indexOf(marker);
        if (idx === -1) return "";
        return window.location.pathname.slice(0, idx);
    })();
    const form = document.getElementById("cohabitationForm");
    const agree = document.getElementById("cohabitationAgree");
    const submitBtn = document.getElementById("cohabitationSubmit");
    if (!form || !agree || !submitBtn) return;
    form.noValidate = true;
    submitBtn.disabled = true;

    const applicantUnit = document.getElementById("unitNumber");
    const applicantHouse = document.getElementById("houseNumber");
    const applicantStreet = document.getElementById("streetName");
    const applicantSubdivision = document.getElementById("applicantSubdivision");
    const applicantArea = document.getElementById("applicantAreaNumber");

    const cohabSameAddress = document.getElementById("cohabitantSameAddress");
    const cohabitantFullAddressWrapper = document.getElementById("cohabitantFullAddressWrapper");
    const cohabitantFullAddress = document.getElementById("cohabitantFullAddress");
    const cohabitantAddressSystemRow = document.getElementById("cohabitantAddressSystemRow");
    const cohabitantAddressSystem = document.getElementById("cohabitantAddressSystem");
    const cohabitantHouseWrapper = document.getElementById("cohabitantHouseSystemWrapper");
    const cohabitantLotWrapper = document.getElementById("cohabitantLotBlockSystemWrapper");
    const cohabitantLocationWrapper = document.getElementById("cohabitantLocationWrapper");

    const cohabUnit = document.getElementById("cohabUnitNumber");
    const cohabHouse = document.getElementById("cohabHouseNumber");
    const cohabStreet = document.getElementById("cohabStreetName");
    const cohabSubdivision = document.getElementById("cohabSubdivision");
    const cohabUnitLot = document.getElementById("cohabUnitNumberLot");
    const cohabLot = document.getElementById("cohabLotNumber");
    const cohabBlock = document.getElementById("cohabBlockNumber");
    const cohabPhase = document.getElementById("cohabPhaseNumber");
    const cohabSubdivisionLot = document.getElementById("cohabitantSubdivisionLot");

    const cohabitantProvince = document.getElementById("cohabitantProvince");
    const cohabitantCity = document.getElementById("cohabitantCity");
    const cohabitantBarangay = document.getElementById("cohabitantBarangay");
    const cohabitantPostal = document.getElementById("cohabitantPostalCode");
    const cohabitantRegionSelect = document.getElementById("cohabitantRegionSelect");

    const cohabitationSameAddress = document.getElementById("cohabitationSameAddress");
    const cohabitationFullAddressWrapper = document.getElementById("cohabitationFullAddressWrapper");
    const cohabitationFullAddress = document.getElementById("cohabitationFullAddress");
    const cohabitationAddressSystemRow = document.getElementById("cohabitationAddressSystemRow");
    const cohabitationAddressSystem = document.getElementById("cohabitationAddressSystem");
    const cohabitationLocalityRow = document.getElementById("cohabitationLocalityRow");
    const cohabitationHouseWrapper = document.getElementById("cohabitationHouseSystemWrapper");
    const cohabitationLotWrapper = document.getElementById("cohabitationLotBlockSystemWrapper");

    const cohabitationUnit = document.getElementById("cohabitationUnitNumber");
    const cohabitationHouse = document.getElementById("cohabitationHouseNumber");
    const cohabitationStreet = document.getElementById("cohabitationStreetName");
    const cohabitationSubdivision = document.getElementById("cohabitationSubdivision");
    const cohabitationArea = document.getElementById("cohabitationAreaNumber");
    const cohabitationUnitLot = document.getElementById("cohabitationUnitNumberLot");
    const cohabitationLot = document.getElementById("cohabitationLotNumber");
    const cohabitationBlock = document.getElementById("cohabitationBlockNumber");
    const cohabitationPhase = document.getElementById("cohabitationPhaseNumber");
    const cohabitationSubdivisionLot = document.getElementById("cohabitationSubdivisionLot");
    const cohabitationAreaLot = document.getElementById("cohabitationAreaNumberLot");
    const cohabitantDob = form.querySelector('input[name="cohabitant_dob"]');
    const cohabitationStartDate = document.getElementById("cohabitationStartDate");
    const cohabitationStartDisplay = document.getElementById("cohabitationStartDisplay");
    const cohabitationStartModalEl = document.getElementById("cohabitationStartModal");
    const cohabitationStartModal = cohabitationStartModalEl && window.bootstrap ? new bootstrap.Modal(cohabitationStartModalEl) : null;
    const cohabitationStartMonth = document.getElementById("cohabitationStartMonth");
    const cohabitationStartYear = document.getElementById("cohabitationStartYear");
    const cohabitationStartApply = document.getElementById("cohabitationStartApply");
    const cohabitationStartPreview = document.getElementById("cohabitationStartPreview");
    const cohabitationDurationDisplay = form.querySelector('input[name="cohabitation_duration_display"]');
    const cohabitationDurationHidden = form.querySelector('input[name="cohabitation_duration"]');
    const cohabitationDurationValue = form.querySelector('input[name="cohabitation_duration_value"]');
    const cohabitationDurationUnit = form.querySelector('input[name="cohabitation_duration_unit"]');
    const cohabitationChildrenCount = document.getElementById("cohabitationChildrenCount");
    const cohabitationChildRows = Array.from(document.querySelectorAll("[data-child-row]"));
    const monthLabels = {
        "01": "January",
        "02": "February",
        "03": "March",
        "04": "April",
        "05": "May",
        "06": "June",
        "07": "July",
        "08": "August",
        "09": "September",
        "10": "October",
        "11": "November",
        "12": "December"
    };

    const setReadOnly = (el, isReadOnly) => {
        if (!el) return;
        if (el.tagName === "SELECT") {
            el.disabled = isReadOnly;
        } else {
            el.readOnly = isReadOnly;
        }
        if (isReadOnly) {
            el.classList.add("text-bg-light");
        } else {
            el.classList.remove("text-bg-light");
        }
    };

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

    const lockSelect = (el, locked, value) => {
        if (!el) return;
        if (locked) {
            el.dataset.locked = "true";
            if (value !== undefined) el.value = value;
            el.classList.add("text-bg-light");
            el.disabled = true;
        } else {
            delete el.dataset.locked;
            el.classList.remove("text-bg-light");
            el.disabled = false;
        }
    };

    const setSelectAvailability = (el, enabled) => {
        if (!el || el.dataset.locked === "true") return;
        el.disabled = !enabled;
    };

    const isFieldVisible = (el) => {
        if (!el) return false;
        if (el.disabled) return false;
        const wrapper = el.closest(".d-none");
        if (wrapper) return false;
        if (el.type === "hidden") return false;
        return true;
    };

    const getVisibleRequiredFields = () =>
        Array.from(form.querySelectorAll("input, select, textarea"))
            .filter((el) => el.required && isFieldVisible(el));

    const hasVisibleRequiredValues = () =>
        getVisibleRequiredFields().every((el) => {
            if (el.type === "checkbox" || el.type === "radio") {
                return el.checked;
            }
            return String(el.value || "").trim() !== "" && el.checkValidity();
        });

    const applyCohabitantAddressSystem = () => {
        const val = cohabitantAddressSystem ? cohabitantAddressSystem.value : "";
        setWrapperState(cohabitantHouseWrapper, val === "house");
        setWrapperState(cohabitantLotWrapper, val === "lot_block");
        if (cohabitantLocationWrapper) {
            cohabitantLocationWrapper.classList.toggle("d-none", !val);
        }

        setRequired(cohabHouse, val === "house");
        setRequired(cohabStreet, val === "house");

        setRequired(cohabLot, val === "lot_block");
        setRequired(cohabBlock, val === "lot_block");
        setRequired(cohabPhase, val === "lot_block");
    };

    const applyCohabitationAddressSystem = () => {
        const val = cohabitationAddressSystem ? cohabitationAddressSystem.value : "";
        if (cohabitationLocalityRow) {
            cohabitationLocalityRow.classList.toggle("d-none", !val);
        }
        setWrapperState(cohabitationHouseWrapper, val === "house");
        setWrapperState(cohabitationLotWrapper, val === "lot_block");

        setRequired(cohabitationHouse, val === "house");
        setRequired(cohabitationStreet, val === "house");
        setRequired(cohabitationArea, val === "house");

        setRequired(cohabitationLot, val === "lot_block");
        setRequired(cohabitationBlock, val === "lot_block");
        setRequired(cohabitationPhase, val === "lot_block");
        setRequired(cohabitationAreaLot, val === "lot_block");
    };

    const fillFromApplicant = (targets) => {
        const unit = applicantUnit?.value || "";
        const house = applicantHouse?.value || "";
        const street = applicantStreet?.value || "";
        const subdivision = applicantSubdivision?.value || "";
        const area = applicantArea?.value || "";
        if (targets.unit) targets.unit.value = unit;
        if (targets.house) targets.house.value = house;
        if (targets.street) targets.street.value = street;
        if (targets.subdivision) targets.subdivision.value = subdivision;
        if (targets.area) targets.area.value = area;
    };

    const getOrCreateInlineError = (inputEl) => {
        if (!inputEl) return null;
        if (inputEl.id) {
            const staticErr = document.getElementById(`${inputEl.id}Error`);
            if (staticErr) return staticErr;
        }
        let err = inputEl.parentElement?.querySelector(".inline-name-error");
        if (!err) {
            err = document.createElement("div");
            err.className = "inline-name-error text-danger small mt-1 d-none";
            err.setAttribute("aria-live", "polite");
            inputEl.insertAdjacentElement("afterend", err);
        }
        return err;
    };

    const validateDateNotFuture = (inputEl, label) => {
        if (!inputEl) return true;
        const value = String(inputEl.value || "").trim();
        const errorAnchor = inputEl.type === "hidden" && cohabitationStartDisplay ? cohabitationStartDisplay : inputEl;
        const errorEl = getOrCreateInlineError(errorAnchor);
        const todayIso = new Date().toISOString().split("T")[0];
        const currentMonthIso = todayIso.slice(0, 7);
        const todayDisplay = new Date().toLocaleDateString(undefined, {
            year: "numeric",
            month: "long",
            day: "numeric",
        });
        const currentMonthDisplay = new Date().toLocaleDateString(undefined, {
            year: "numeric",
            month: "long",
        });

        let message = "";
        if (value) {
            if ((inputEl.type === "month" || inputEl === cohabitationStartDate) && value > currentMonthIso) {
                message = `Incorrect Input. ${label} must be on or before ${currentMonthDisplay}`;
            } else if (inputEl.type !== "month" && value > todayIso) {
                message = `Incorrect Input. ${label} must be on or before ${todayDisplay}`;
            }
        }

        if (typeof inputEl.setCustomValidity === "function") {
            inputEl.setCustomValidity(message);
        }
        if (errorAnchor !== inputEl && typeof errorAnchor.setCustomValidity === "function") {
            errorAnchor.setCustomValidity(message);
        }
        if (errorEl) {
            if (message) {
                errorEl.textContent = message;
                errorEl.classList.remove("d-none");
            } else {
                errorEl.textContent = "";
                errorEl.classList.add("d-none");
            }
        }
        return message === "";
    };

    const validateCohabitationDates = () => {
        const okDob = validateDateNotFuture(cohabitantDob, "Date of birth");
        const okStart = validateDateNotFuture(cohabitationStartDate, "Started cohabitation date");
        return okDob && okStart;
    };

    const updateCohabitationStartPreview = () => {
        if (!cohabitationStartPreview) return;
        const month = String(cohabitationStartMonth?.value || "").trim();
        const year = String(cohabitationStartYear?.value || "").trim();
        cohabitationStartPreview.textContent = month && year
            ? `Selected: ${monthLabels[month]} ${year}`
            : "No month selected yet.";
    };

    const syncCohabitationStartFromValue = () => {
        if (!cohabitationStartDate) return;
        const raw = String(cohabitationStartDate.value || "").trim();
        const match = raw.match(/^(\d{4})-(\d{2})$/);
        if (!match) {
            if (cohabitationStartDisplay) cohabitationStartDisplay.value = "";
            if (cohabitationStartMonth) cohabitationStartMonth.value = "";
            if (cohabitationStartYear) cohabitationStartYear.value = "";
            updateCohabitationStartPreview();
            return;
        }
        if (cohabitationStartYear) cohabitationStartYear.value = match[1];
        if (cohabitationStartMonth) cohabitationStartMonth.value = match[2];
        if (cohabitationStartDisplay) {
            cohabitationStartDisplay.value = `${monthLabels[match[2]]} ${match[1]}`;
        }
        updateCohabitationStartPreview();
    };

    const setCohabitationStartValue = (value) => {
        if (!cohabitationStartDate || !cohabitationStartDisplay) return;
        const raw = String(value || "").trim();
        cohabitationStartDate.value = raw;
        const match = raw.match(/^(\d{4})-(\d{2})$/);
        cohabitationStartDisplay.value = match ? `${monthLabels[match[2]]} ${match[1]}` : "";
        cohabitationStartDisplay.dispatchEvent(new Event("input", { bubbles: true }));
        cohabitationStartDisplay.dispatchEvent(new Event("change", { bubbles: true }));
        cohabitationStartDisplay.setCustomValidity("");
        const errorEl = getOrCreateInlineError(cohabitationStartDisplay);
        if (errorEl) {
            errorEl.textContent = "";
            errorEl.classList.add("d-none");
        }
        updateCohabitationStartPreview();
    };

    const computeCohabitationDuration = () => {
        if (!cohabitationDurationDisplay || !cohabitationDurationHidden || !cohabitationDurationValue || !cohabitationDurationUnit) return;

        const startValue = String(cohabitationStartDate?.value || "").trim();
        if (!startValue) {
            cohabitationDurationDisplay.value = "";
            cohabitationDurationHidden.value = "";
            cohabitationDurationValue.value = "";
            cohabitationDurationUnit.value = "";
            return;
        }

        const startDate = new Date(`${startValue}-01T00:00:00`);
        const today = new Date();
        const currentDate = new Date(today.getFullYear(), today.getMonth(), 1);
        if (Number.isNaN(startDate.getTime()) || startDate > currentDate) {
            cohabitationDurationDisplay.value = "";
            cohabitationDurationHidden.value = "";
            cohabitationDurationValue.value = "";
            cohabitationDurationUnit.value = "";
            return;
        }

        let monthsDiff =
            (currentDate.getFullYear() - startDate.getFullYear()) * 12 +
            (currentDate.getMonth() - startDate.getMonth());

        monthsDiff = Math.max(0, monthsDiff);
        const years = Math.floor(monthsDiff / 12);
        const months = monthsDiff % 12;
        const durationParts = [];

        if (years > 0) {
            durationParts.push(`${years} ${years === 1 ? "Year" : "Years"}`);
        }
        if (months > 0 || durationParts.length === 0) {
            durationParts.push(`${months} ${months === 1 ? "Month" : "Months"}`);
        }

        cohabitationDurationValue.value = String(monthsDiff);
        cohabitationDurationUnit.value = "Months";
        cohabitationDurationDisplay.value = durationParts.join(" ").trim();
        cohabitationDurationHidden.value = cohabitationDurationDisplay.value;
    };

    const applyCohabitationChildrenCount = () => {
        const count = Number.parseInt(String(cohabitationChildrenCount?.value || "0"), 10) || 0;
        cohabitationChildRows.forEach((row) => {
            const rowIndex = Number.parseInt(String(row.dataset.childRow || "0"), 10);
            const isVisible = rowIndex > 0 && rowIndex <= count;
            row.classList.toggle("d-none", !isVisible);
            row.querySelectorAll("input").forEach((input) => {
                input.disabled = !isVisible;
                setRequired(input, isVisible);
                if (!isVisible) {
                    input.value = "";
                }
            });
        });
    };

    let regionIndex = null;

    const buildRegionIndex = (data) => {
        const index = {};
        if (!data || typeof data !== "object") return index;
        Object.values(data).forEach((region) => {
            const regionName = region?.region_name;
            if (!regionName) return;
            const provinces = region?.province_list || {};
            const provinceIndex = {};
            Object.entries(provinces).forEach(([provinceName, provinceData]) => {
                const municipalityList = provinceData?.municipality_list || {};
                const cityIndex = {};
                Object.entries(municipalityList).forEach(([cityName, cityData]) => {
                    cityIndex[cityName] = cityData?.barangay_list || [];
                });
                provinceIndex[provinceName] = cityIndex;
            });
            index[regionName] = provinceIndex;
        });
        return index;
    };

    const populateRegions = (selectedRegion = "") => {
        if (!cohabitantRegionSelect || !regionIndex) return;
        const regions = Object.keys(regionIndex).sort((a, b) => a.localeCompare(b));
        cohabitantRegionSelect.innerHTML = "";

        const placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = "Select";
        cohabitantRegionSelect.appendChild(placeholder);

        regions.forEach((region) => {
            const opt = document.createElement("option");
            opt.value = region;
            opt.textContent = region;
            if (selectedRegion && selectedRegion === region) opt.selected = true;
            cohabitantRegionSelect.appendChild(opt);
        });

        setSelectAvailability(cohabitantRegionSelect, true);
    };

    const populateProvinces = (region, selectedProvince = "") => {
        if (!cohabitantProvince || !regionIndex) return;
        const provinces = regionIndex[region] ? Object.keys(regionIndex[region]) : [];
        const sorted = [...provinces].sort((a, b) => a.localeCompare(b));
        cohabitantProvince.innerHTML = "";

        const placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = sorted.length ? "Select" : "Select region first";
        cohabitantProvince.appendChild(placeholder);

        sorted.forEach((province) => {
            const opt = document.createElement("option");
            opt.value = province;
            opt.textContent = province;
            if (selectedProvince && selectedProvince === province) opt.selected = true;
            cohabitantProvince.appendChild(opt);
        });

        setSelectAvailability(cohabitantProvince, !!region && sorted.length > 0);
    };

    const populateCities = (region, province, selectedCity = "") => {
        if (!cohabitantCity) return;
        const cities =
            regionIndex &&
            regionIndex[region] &&
            regionIndex[region][province]
                ? Object.keys(regionIndex[region][province])
                : [];
        const sortedCities = [...cities].sort((a, b) => a.localeCompare(b));
        cohabitantCity.innerHTML = "";

        const placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = sortedCities.length ? "Select" : "Select province first";
        cohabitantCity.appendChild(placeholder);

        sortedCities.forEach((city) => {
            const opt = document.createElement("option");
            opt.value = city;
            opt.textContent = city;
            if (selectedCity && selectedCity === city) opt.selected = true;
            cohabitantCity.appendChild(opt);
        });

        setSelectAvailability(cohabitantCity, !!region && !!province && sortedCities.length > 0);
    };

    const populateBarangays = (region, province, city, selectedBarangay = "") => {
        if (!cohabitantBarangay) return;
        const barangays =
            regionIndex &&
            regionIndex[region] &&
            regionIndex[region][province] &&
            regionIndex[region][province][city]
                ? regionIndex[region][province][city]
                : [];
        const sorted = [...barangays].sort((a, b) => a.localeCompare(b));
        cohabitantBarangay.innerHTML = "";

        const placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = sorted.length ? "Select" : "Select city first";
        cohabitantBarangay.appendChild(placeholder);

        sorted.forEach((brgy) => {
            const opt = document.createElement("option");
            opt.value = brgy;
            opt.textContent = brgy;
            if (selectedBarangay && selectedBarangay === brgy) opt.selected = true;
            cohabitantBarangay.appendChild(opt);
        });

        setSelectAvailability(cohabitantBarangay, !!region && !!province && !!city && sorted.length > 0);
    };


    const syncCohabitantAddress = () => {
        if (!cohabSameAddress || !cohabitantAddressSystem) return;
        const useApplicant = cohabSameAddress.checked;

        if (useApplicant) {
            lockSelect(cohabitantAddressSystem, true, "house");
            if (cohabitantAddressSystemRow) {
                cohabitantAddressSystemRow.classList.add("d-none");
            }
            if (cohabitantFullAddressWrapper) {
                cohabitantFullAddressWrapper.classList.remove("d-none");
            }
            if (cohabitantFullAddress) {
                cohabitantFullAddress.value = cohabitantFullAddress.value || "";
            }
            setWrapperState(cohabitantHouseWrapper, false);
            setWrapperState(cohabitantLotWrapper, false);
            if (cohabitantLocationWrapper) {
                cohabitantLocationWrapper.classList.add("d-none");
            }

            setRequired(cohabHouse, false);
            setRequired(cohabStreet, false);
            setRequired(cohabLot, false);
            setRequired(cohabBlock, false);
            setRequired(cohabPhase, false);

            lockSelect(cohabitantRegionSelect, true, "REGION IV-A");
            populateProvinces("REGION IV-A", "RIZAL");
            lockSelect(cohabitantProvince, true, "RIZAL");
            populateCities("REGION IV-A", "RIZAL", "RODRIGUEZ (MONTALBAN)");
            lockSelect(cohabitantCity, true, "RODRIGUEZ (MONTALBAN)");
            populateBarangays("REGION IV-A", "RIZAL", "RODRIGUEZ (MONTALBAN)");
            lockSelect(cohabitantBarangay, true, "SAN JOSE");
            if (cohabitantRegionSelect) {
                cohabitantRegionSelect.value = "REGION IV-A";
            }
        } else {
            lockSelect(cohabitantAddressSystem, false);
            lockSelect(cohabitantProvince, false);
            lockSelect(cohabitantCity, false);
            lockSelect(cohabitantRegionSelect, false);
            lockSelect(cohabitantBarangay, false);
            if (cohabitantAddressSystemRow) {
                cohabitantAddressSystemRow.classList.remove("d-none");
            }
            if (cohabitantFullAddressWrapper) {
                cohabitantFullAddressWrapper.classList.add("d-none");
            }
            if (cohabitantLocationWrapper) {
                cohabitantLocationWrapper.classList.remove("d-none");
            }
            if (cohabitantAddressSystem) cohabitantAddressSystem.value = "";
            if (cohabitantRegionSelect) cohabitantRegionSelect.value = "";
            if (cohabitantProvince) cohabitantProvince.value = "";
            if (cohabitantCity) cohabitantCity.value = "";
            if (cohabitantBarangay) cohabitantBarangay.value = "";
            if (cohabitantPostal) cohabitantPostal.value = "";
            [cohabUnit, cohabHouse, cohabStreet, cohabSubdivision, cohabUnitLot, cohabLot, cohabBlock, cohabPhase, cohabSubdivisionLot].forEach((el) => {
                if (!el) return;
                el.value = "";
            });
            applyCohabitantAddressSystem();
        }

        [cohabUnit, cohabHouse, cohabStreet, cohabSubdivision, cohabUnitLot, cohabLot, cohabBlock, cohabPhase, cohabSubdivisionLot].forEach((el) =>
            setReadOnly(el, useApplicant)
        );
    };

    const syncCohabitationAddress = () => {
        if (!cohabitationSameAddress || !cohabitationAddressSystem) return;
        const useApplicant = cohabitationSameAddress.checked;

        if (useApplicant) {
            lockSelect(cohabitationAddressSystem, true, "house");
            if (cohabitationAddressSystemRow) {
                cohabitationAddressSystemRow.classList.add("d-none");
            }
            if (cohabitationFullAddressWrapper) {
                cohabitationFullAddressWrapper.classList.remove("d-none");
            }
            if (cohabitationFullAddress) {
                cohabitationFullAddress.value = cohabitationFullAddress.value || "";
            }
            setWrapperState(cohabitationHouseWrapper, false);
            setWrapperState(cohabitationLotWrapper, false);

            setRequired(cohabitationHouse, false);
            setRequired(cohabitationStreet, false);
            setRequired(cohabitationArea, false);
            setRequired(cohabitationLot, false);
            setRequired(cohabitationBlock, false);
            setRequired(cohabitationPhase, false);
            setRequired(cohabitationAreaLot, false);
        } else {
            lockSelect(cohabitationAddressSystem, false);
            cohabitationAddressSystem.value = "";
            if (cohabitationAddressSystemRow) {
                cohabitationAddressSystemRow.classList.remove("d-none");
            }
            if (cohabitationFullAddressWrapper) {
                cohabitationFullAddressWrapper.classList.add("d-none");
            }
            [
                cohabitationUnit,
                cohabitationHouse,
                cohabitationStreet,
                cohabitationSubdivision,
                cohabitationArea,
                cohabitationUnitLot,
                cohabitationLot,
                cohabitationBlock,
                cohabitationPhase,
                cohabitationSubdivisionLot,
                cohabitationAreaLot
            ].forEach((el) => {
                if (!el) return;
                if (el.tagName === "SELECT") {
                    el.value = "";
                } else {
                    el.value = "";
                }
            });
            applyCohabitationAddressSystem();
        }

        [cohabitationUnit, cohabitationHouse, cohabitationStreet, cohabitationSubdivision, cohabitationArea].forEach((el) =>
            setReadOnly(el, useApplicant)
        );
    };

    const updateSubmitState = () => {
        const datesValid = validateCohabitationDates();
        const requiredFieldsValid = hasVisibleRequiredValues();
        submitBtn.disabled = !(agree.checked && datesValid && requiredFieldsValid);
    };

    if (cohabitantAddressSystem) {
        cohabitantAddressSystem.addEventListener("change", (e) => {
            if (cohabitantAddressSystem.dataset.locked === "true") {
                e.target.value = "house";
                return;
            }
            applyCohabitantAddressSystem();
            updateSubmitState();
        });
        applyCohabitantAddressSystem();
    }

    const initAddressData = async () => {
        try {
            const res = await fetch(`${appBase}/JS-Script-Files/Resident-End/Certificates/data/cluster.json`, {
                cache: "no-store"
            });
            if (!res.ok) return;
            const data = await res.json();
            regionIndex = buildRegionIndex(data);
            populateRegions(cohabitantRegionSelect?.value || "");
            if (cohabitantRegionSelect?.value) {
                populateProvinces(cohabitantRegionSelect.value, cohabitantProvince?.value || "");
            }
            if (cohabitantRegionSelect?.value && cohabitantProvince?.value) {
                populateCities(cohabitantRegionSelect.value, cohabitantProvince.value, cohabitantCity?.value || "");
            }
            if (cohabitantRegionSelect?.value && cohabitantProvince?.value && cohabitantCity?.value) {
                populateBarangays(
                    cohabitantRegionSelect.value,
                    cohabitantProvince.value,
                    cohabitantCity.value,
                    cohabitantBarangay?.value || ""
                );
            }

            syncCohabitantAddress();
        } catch (err) {
            // silently fail; fallback keeps existing placeholders
        }
    };

    if (cohabitantRegionSelect) {
        cohabitantRegionSelect.addEventListener("change", (e) => {
            if (cohabitantRegionSelect.dataset.locked === "true") {
                e.target.value = cohabitantRegionSelect.value || "REGION IV-A";
                return;
            }
            populateProvinces(e.target.value);
            populateCities(e.target.value, "");
            populateBarangays("", "", "");
            updateSubmitState();
        });
    }

    if (cohabitantProvince) {
        cohabitantProvince.addEventListener("change", (e) => {
            if (cohabitantProvince.dataset.locked === "true") {
                e.target.value = cohabitantProvince.value || "RIZAL";
                return;
            }
            const region = cohabitantRegionSelect ? cohabitantRegionSelect.value : "";
            populateCities(region, e.target.value);
            populateBarangays("", "", "");
            updateSubmitState();
        });
    }

    if (cohabitantCity) {
        cohabitantCity.addEventListener("change", (e) => {
            if (cohabitantCity.dataset.locked === "true") {
                e.target.value = cohabitantCity.value || "RODRIGUEZ (MONTALBAN)";
                return;
            }
            const region = cohabitantRegionSelect ? cohabitantRegionSelect.value : "";
            const province = cohabitantProvince ? cohabitantProvince.value : "";
            populateBarangays(region, province, e.target.value);
            updateSubmitState();
        });
    }

    if (cohabitationAddressSystem) {
        cohabitationAddressSystem.addEventListener("change", (e) => {
            if (cohabitationAddressSystem.dataset.locked === "true") {
                e.target.value = "house";
                return;
            }
            applyCohabitationAddressSystem();
            updateSubmitState();
        });
        applyCohabitationAddressSystem();
    }

    if (cohabitationChildrenCount) {
        cohabitationChildrenCount.addEventListener("change", () => {
            applyCohabitationChildrenCount();
            updateSubmitState();
        });
        applyCohabitationChildrenCount();
    }

    if (cohabitationStartYear) {
        const currentYear = new Date().getFullYear();
        cohabitationStartYear.innerHTML = '<option value="">Select year</option>';
        for (let year = currentYear; year >= currentYear - 120; year -= 1) {
            const option = document.createElement("option");
            option.value = String(year);
            option.textContent = String(year);
            cohabitationStartYear.appendChild(option);
        }
    }

    cohabitationStartDisplay?.addEventListener("click", () => {
        syncCohabitationStartFromValue();
        updateCohabitationStartPreview();
        cohabitationStartModal?.show();
    });
    cohabitationStartDisplay?.addEventListener("focus", () => {
        syncCohabitationStartFromValue();
        updateCohabitationStartPreview();
        cohabitationStartModal?.show();
    });
    cohabitationStartDisplay?.addEventListener("keydown", (event) => {
        if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            syncCohabitationStartFromValue();
            updateCohabitationStartPreview();
            cohabitationStartModal?.show();
        }
    });

    cohabitationStartMonth?.addEventListener("change", updateCohabitationStartPreview);
    cohabitationStartYear?.addEventListener("change", updateCohabitationStartPreview);

    cohabitationStartApply?.addEventListener("click", () => {
        const month = String(cohabitationStartMonth?.value || "").trim();
        const year = String(cohabitationStartYear?.value || "").trim();
        if (!month || !year) {
            cohabitationStartDisplay?.setCustomValidity("Please select month and year.");
            const errorEl = getOrCreateInlineError(cohabitationStartDisplay);
            if (errorEl) {
                errorEl.textContent = "Please select month and year.";
                errorEl.classList.remove("d-none");
            }
            return;
        }
        setCohabitationStartValue(`${year}-${month}`);
        cohabitationStartModal?.hide();
        validateCohabitationDates();
        computeCohabitationDuration();
        updateSubmitState();
    });

    cohabitationStartModalEl?.addEventListener("hidden.bs.modal", syncCohabitationStartFromValue);
    syncCohabitationStartFromValue();

    if (cohabSameAddress) {
        cohabSameAddress.addEventListener("change", () => {
            syncCohabitantAddress();
            updateSubmitState();
        });
    }

    if (cohabitationSameAddress) {
        cohabitationSameAddress.addEventListener("change", () => {
            syncCohabitationAddress();
            updateSubmitState();
        });
    }

    updateSubmitState();
    syncCohabitantAddress();
    syncCohabitationAddress();
    initAddressData();
    [cohabitantDob, cohabitationStartDate].forEach((el) => {
        if (!el) return;
        el.addEventListener("input", () => {
            validateCohabitationDates();
            computeCohabitationDuration();
            updateSubmitState();
        });
        el.addEventListener("change", () => {
            validateCohabitationDates();
            computeCohabitationDuration();
            updateSubmitState();
        });
        el.addEventListener("blur", () => {
            validateCohabitationDates();
            computeCohabitationDuration();
            updateSubmitState();
        });
        el.addEventListener("invalid", () => {
            validateCohabitationDates();
            computeCohabitationDuration();
            updateSubmitState();
        });
    });
    computeCohabitationDuration();
    agree.addEventListener("change", updateSubmitState);
    // Avoid heavy whole-form validity checks on every keystroke (causes input lag).
    // We keep explicit field listeners above and validate on change/submit.
    form.addEventListener("change", updateSubmitState);
    form.addEventListener("submit", (e) => {
        updateSubmitState();
        const invalidField = getVisibleRequiredFields().find((el) => !el.checkValidity() || String(el.value || "").trim() === "");
        if (invalidField || !agree.checked) {
            e.preventDefault();
            if (invalidField) {
                invalidField.reportValidity();
                invalidField.focus();
            } else {
                agree.reportValidity();
                agree.focus();
            }
        }
    });
});
