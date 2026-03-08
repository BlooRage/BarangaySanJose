(() => {
  const form = document.getElementById("indigencyRequestForm");
  const agreement = document.getElementById("agreementIndigency");
  const submitBtn = form?.querySelector(".submit-btn");
  const submissionTargetType = document.getElementById("submissionTargetType");
  const governmentOfficialRow = document.getElementById("governmentOfficialRow");
  const governmentPositionSelect = document.getElementById("governmentPositionSelect");
  const governmentPositionOther = document.getElementById("governmentPositionOther");
  const governmentPositionDetail = document.getElementById("governmentPositionDetail");
  const governmentOfficialSelect = document.getElementById("governmentOfficialSelect");
  const governmentOfficialOther = document.getElementById("governmentOfficialOther");
  const institutionRow = document.getElementById("institutionRow");
  const institutionName = document.getElementById("institutionName");
  const institutionPerson = document.getElementById("institutionPerson");
  const institutionPosition = document.getElementById("institutionPosition");
  const requestOfficerFinal = document.getElementById("requestOfficerFinal");
  const governmentOfficeFinal = document.getElementById("governmentOfficeFinal");
  const governmentPositionFinal = document.getElementById("governmentPositionFinal");
  const governmentOfficialFinal = document.getElementById("governmentOfficialFinal");
  const governmentDirectory = window.INDIGENCY_GOVERNMENT_DIRECTORY || { officials: [] };

  if (!form || !agreement || !submitBtn || !submissionTargetType) return;

  const OTHER_VALUE = "__other__";

  const isShown = (element) => !!element && !element.classList.contains("d-none") && !element.closest(".d-none");

  const clearField = (field) => {
    if (!field) return;
    if (field.type === "checkbox") {
      field.checked = false;
      return;
    }
    field.value = "";
  };

  const setFieldState = (field, { required = false, enabled = true, clear = false } = {}) => {
    if (!field) return;
    field.disabled = !enabled;
    field.required = required && enabled;
    if (clear) clearField(field);
  };

  const getOfficialPosition = (officialId) => {
    const selectedId = String(officialId || "").trim();
    if (!selectedId) return "";
    const official = governmentDirectory.officials.find((item) => String(item?.id || "") === selectedId);
    return String(official?.position_name || "").trim();
  };

  const getOfficialName = (officialId) => {
    const selectedId = String(officialId || "").trim();
    if (!selectedId) return "";
    const official = governmentDirectory.officials.find((item) => String(item?.id || "") === selectedId);
    return String(official?.name || "").trim();
  };

  const getOfficialLocation = (officialId) => {
    const selectedId = String(officialId || "").trim();
    if (!selectedId) return "";
    const official = governmentDirectory.officials.find((item) => String(item?.id || "") === selectedId);
    return String(official?.jurisdiction_location || "").trim();
  };

  const syncGovernmentState = () => {
    const isGovernment = submissionTargetType.value === "government_official";
    const groupValue = String(governmentPositionSelect?.value || "").trim();
    const officialValue = String(governmentOfficialSelect?.value || "").trim();
    const isOtherGroup = groupValue === OTHER_VALUE;
    const isOtherOfficial = officialValue === OTHER_VALUE || isOtherGroup;

    if (governmentOfficialRow) {
      governmentOfficialRow.classList.toggle("d-none", !isGovernment);
    }

    setFieldState(governmentPositionSelect, {
      required: isGovernment,
      enabled: isGovernment,
      clear: !isGovernment,
    });

    setFieldState(governmentPositionOther, {
      required: isGovernment && isOtherGroup,
      enabled: isGovernment && isOtherGroup,
      clear: !isGovernment || !isOtherGroup,
    });
    if (governmentPositionOther) {
      governmentPositionOther.classList.toggle("d-none", !(isGovernment && isOtherGroup));
    }

    setFieldState(governmentPositionDetail, {
      required: isGovernment,
      enabled: isGovernment,
      clear: !isGovernment,
    });

    setFieldState(governmentOfficialSelect, {
      required: isGovernment && !isOtherGroup,
      enabled: isGovernment && groupValue !== "",
      clear: !isGovernment || groupValue === "",
    });

    setFieldState(governmentOfficialOther, {
      required: isGovernment && isOtherOfficial,
      enabled: isGovernment && isOtherOfficial,
      clear: !isGovernment || !isOtherOfficial,
    });
    if (governmentOfficialOther) {
      governmentOfficialOther.classList.toggle("d-none", !(isGovernment && isOtherOfficial));
    }

    if (isGovernment && governmentOfficialSelect && !isOtherOfficial) {
      const selectedOption = governmentOfficialSelect.selectedOptions?.[0] || null;
      if (selectedOption?.hidden) {
        governmentOfficialSelect.value = "";
      }
    }

    if (!isGovernment) {
      if (governmentOfficeFinal) governmentOfficeFinal.value = "";
      if (governmentPositionFinal) governmentPositionFinal.value = "";
      if (governmentOfficialFinal) governmentOfficialFinal.value = "";
      return;
    }

    if (governmentPositionDetail && !isOtherOfficial) {
      const resolvedPosition = getOfficialPosition(governmentOfficialSelect?.value);
      if (resolvedPosition) {
        governmentPositionDetail.value = resolvedPosition;
      }
    }

    const officeText = isOtherGroup
      ? String(governmentPositionOther?.value || "").trim()
      : getOfficialLocation(governmentOfficialSelect?.value);
    const positionText = String(governmentPositionDetail?.value || "").trim();
    const officialText = isOtherOfficial
      ? String(governmentOfficialOther?.value || "").trim()
      : getOfficialName(governmentOfficialSelect?.value);

    if (governmentOfficeFinal) governmentOfficeFinal.value = officeText;
    if (governmentPositionFinal) governmentPositionFinal.value = positionText;
    if (governmentOfficialFinal) governmentOfficialFinal.value = officialText;
  };

  const syncInstitutionState = () => {
    const isInstitution = submissionTargetType.value === "institution";

    if (institutionRow) {
      institutionRow.classList.toggle("d-none", !isInstitution);
    }

    setFieldState(institutionName, {
      required: isInstitution,
      enabled: isInstitution,
      clear: !isInstitution,
    });
    setFieldState(institutionPerson, {
      required: false,
      enabled: isInstitution,
      clear: !isInstitution,
    });
    setFieldState(institutionPosition, {
      required: false,
      enabled: isInstitution,
      clear: !isInstitution,
    });
  };

  const buildRequestOfficer = () => {
    const selectedType = String(submissionTargetType.value || "").trim();

    if (selectedType === "government_official") {
      const groupValue = String(governmentPositionSelect?.value || "").trim();
      const officialValue = String(governmentOfficialSelect?.value || "").trim();
      const officeText = groupValue === OTHER_VALUE
      ? String(governmentPositionOther?.value || "").trim()
      : getOfficialLocation(governmentOfficialSelect?.value);
      const positionText = String(governmentPositionDetail?.value || "").trim();
      const officialText = officialValue === OTHER_VALUE || groupValue === OTHER_VALUE
        ? String(governmentOfficialOther?.value || "").trim()
        : getOfficialName(governmentOfficialSelect?.value);
      return [officialText, positionText, officeText].filter(Boolean).join(" - ");
    }

    if (selectedType === "institution") {
      const institutionText = String(institutionName?.value || "").trim();
      const personText = String(institutionPerson?.value || "").trim();
      const positionText = String(institutionPosition?.value || "").trim();
      const attentionText = [personText, positionText].filter(Boolean).join(", ");
      if (!institutionText) return "";
      return attentionText ? `${institutionText} - ATTN: ${attentionText}` : institutionText;
    }

    return "";
  };

  const findFirstInvalidField = () => {
    const fields = Array.from(form.querySelectorAll("input, select, textarea"));
    return fields.find((field) => {
      if (!field.required || field.disabled || field.type === "hidden") return false;
      if (!isShown(field)) return false;
      return !field.checkValidity();
    }) || null;
  };

  const updateSubmitState = () => {
    syncGovernmentState();
    syncInstitutionState();

    if (requestOfficerFinal) {
      requestOfficerFinal.value = buildRequestOfficer();
    }

    const invalidField = findFirstInvalidField();
    submitBtn.disabled = !!invalidField;
    submitBtn.title = invalidField
      ? `Complete the required field: ${invalidField.name || invalidField.id || "unknown field"}`
      : "";
  };

  submissionTargetType.addEventListener("change", updateSubmitState);
  governmentPositionSelect?.addEventListener("change", updateSubmitState);
  governmentOfficialSelect?.addEventListener("change", updateSubmitState);
  governmentPositionOther?.addEventListener("input", updateSubmitState);
  governmentOfficialOther?.addEventListener("input", updateSubmitState);
  governmentPositionDetail?.addEventListener("input", updateSubmitState);
  institutionName?.addEventListener("input", updateSubmitState);
  institutionPerson?.addEventListener("input", updateSubmitState);
  institutionPosition?.addEventListener("input", updateSubmitState);
  agreement.addEventListener("change", updateSubmitState);
  form.addEventListener("input", updateSubmitState);
  form.addEventListener("change", updateSubmitState);

  form.addEventListener("submit", (event) => {
    updateSubmitState();
    const invalidField = findFirstInvalidField();
    if (invalidField) {
      event.preventDefault();
      invalidField.focus();
      return;
    }
    if (requestOfficerFinal) {
      requestOfficerFinal.value = buildRequestOfficer();
    }
  });

  updateSubmitState();
})();
