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
    const cohabitantLastName = document.getElementById("cohabitantLastName") || form.querySelector('input[name="cohabitant_last"]');
    const cohabitantFirstName = document.getElementById("cohabitantFirstName") || form.querySelector('input[name="cohabitant_first"]');
    const cohabitantMiddleName = document.getElementById("cohabitantMiddleName") || form.querySelector('input[name="cohabitant_middle"]');
    const cohabitantDob = form.querySelector('input[name="cohabitant_dob"]');
    const cohabitationStartDate = form.querySelector('input[name="cohabitation_start_date"]');

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
        } else {
            delete el.dataset.locked;
            el.classList.remove("text-bg-light");
        }
    };

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

    const isLikelyGarbageName = (text) => {
        const value = String(text || "").trim().toLowerCase();
        if (!value) return false;
        if (/^(test|testing|dummy|unknown|null|none|n\/a|na|xxx|asdf|qwerty|zxcv)$/i.test(value)) return true;
        if (/(.)\1{3,}/i.test(value)) return true;

        const lettersOnly = value.replace(/[^a-z]/g, "");
        if (lettersOnly.length >= 5 && /^[bcdfghjklmnpqrstvwxyz]+$/i.test(lettersOnly)) return true;
        if (/(asdf|qwer|zxcv|poiuy|lkjh)/i.test(lettersOnly)) return true;

        return false;
    };

    const validateNameField = (inputEl, label, { required = true, allowSingleChar = false } = {}) => {
        if (!inputEl) return true;
        const raw = String(inputEl.value || "").trim();
        const errorEl = getOrCreateInlineError(inputEl);
        let message = "";

        if (!raw) {
            if (required) message = `${label} is required.`;
        } else if (!/^[A-Za-z][A-Za-z\s-]*$/.test(raw)) {
            message = `${label} must contain letters only. Allowed symbol: dash (-).`;
        } else if (/--|\s{2,}/.test(raw)) {
            message = `${label} contains invalid punctuation or spacing.`;
        } else {
            const lettersOnlyLength = raw.replace(/[^A-Za-z]/g, "").length;
            if (!allowSingleChar && lettersOnlyLength < 2) {
                message = `${label} must be at least 2 letters.`;
            } else if (isLikelyGarbageName(raw)) {
                message = `${label} appears invalid. Please enter a valid name.`;
            }
        }

        inputEl.setCustomValidity(message);
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

    const validateCohabitantNames = () => {
        const okLast = validateNameField(cohabitantLastName, "Last name", { required: true, allowSingleChar: false });
        const okFirst = validateNameField(cohabitantFirstName, "First name", { required: true, allowSingleChar: false });
        const okMiddle = validateNameField(cohabitantMiddleName, "Middle name", { required: false, allowSingleChar: true });
        return okLast && okFirst && okMiddle;
    };

    const validateDateNotFuture = (inputEl, label) => {
        if (!inputEl) return true;
        const value = String(inputEl.value || "").trim();
        const errorEl = getOrCreateInlineError(inputEl);
        const todayIso = new Date().toISOString().split("T")[0];
        const todayDisplay = new Date().toLocaleDateString(undefined, {
            year: "numeric",
            month: "long",
            day: "numeric",
        });

        let message = "";
        if (value && value > todayIso) {
            message = `Incorrect Input. ${label} must be on or before ${todayDisplay}`;
        }

        inputEl.setCustomValidity(message);
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
            if (cohabitantRegionSelect) {
                cohabitantRegionSelect.value = "REGION IV-A";
            }
        } else {
            lockSelect(cohabitantAddressSystem, false);
            lockSelect(cohabitantProvince, false);
            lockSelect(cohabitantCity, false);
            lockSelect(cohabitantRegionSelect, false);
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
            [cohabUnit, cohabHouse, cohabStreet, cohabSubdivision, cohabUnitLot, cohabLot, cohabBlock, cohabPhase].forEach((el) => {
                if (!el) return;
                el.value = "";
            });
            applyCohabitantAddressSystem();
        }

        [cohabUnit, cohabHouse, cohabStreet, cohabSubdivision].forEach((el) => setReadOnly(el, useApplicant));
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
        validateCohabitantNames();
        validateCohabitationDates();
        const isValid = form.checkValidity();
        submitBtn.disabled = !(agree.checked && isValid);
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
    [cohabitantLastName, cohabitantFirstName, cohabitantMiddleName].forEach((el) => {
        if (!el) return;
        el.addEventListener("input", () => {
            validateCohabitantNames();
            updateSubmitState();
        });
        el.addEventListener("blur", () => {
            validateCohabitantNames();
            updateSubmitState();
        });
    });
    [cohabitantDob, cohabitationStartDate].forEach((el) => {
        if (!el) return;
        el.addEventListener("input", () => {
            validateCohabitationDates();
            updateSubmitState();
        });
        el.addEventListener("change", () => {
            validateCohabitationDates();
            updateSubmitState();
        });
        el.addEventListener("blur", () => {
            validateCohabitationDates();
            updateSubmitState();
        });
        el.addEventListener("invalid", () => {
            validateCohabitationDates();
            updateSubmitState();
        });
    });
    agree.addEventListener("change", updateSubmitState);
        form.addEventListener("input", updateSubmitState);
    form.addEventListener("change", updateSubmitState);
});
