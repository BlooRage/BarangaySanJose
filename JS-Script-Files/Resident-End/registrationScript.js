/**
 * registrationScript.js (FULL COPY-PASTE) — UPDATED FOR +63 PHONE UI + DISABLED ACCOUNT CONTACT
 * Fixes:
 * - Next buttons enable correctly (including Step 1 checkbox)
 * - Hidden fields (d-none / display:none / skipped proof) do NOT block validation
 * - Proof of Identity submit enable logic (Skip OR complete required proof fields)
 * - Student ID shows School Name and requires it only when needed
 * - AJAX submit (prevents raw JSON page)
 * - +63 phone UI: validates 10 digits starting with 9 (9XXXXXXXXX), NOT 09XXXXXXXXX
 * - getAccountContact fetch moved INSIDE DOMContentLoaded and triggers Next button state refresh
 */

document.addEventListener("DOMContentLoaded", () => {
  const steps = document.querySelectorAll(".progress-steps li");
  const sections = document.querySelectorAll(".step");

  let currentStep = 0;

  /* ===============================
     HELPERS
     =============================== */
  function showError(input, message) {
    const targetSelector = input.getAttribute("data-error-target");
    const target = targetSelector ? document.querySelector(targetSelector) : null;
    const isVoterEmploymentGroup = !!(target && target.classList.contains("voter-toggle-group"));
    const isBoxedGroupTarget = !!(
      target &&
      (
        target.classList.contains("voter-toggle-group") ||
        target.id === "div-policyGroup"
      )
    );

    if (isBoxedGroupTarget) {
      // For privacy + voter + employment, highlight the whole group container.
      input.classList.remove("is-invalid");
      target.classList.add("invalid-target-box");

      // Force button-level error visuals for voter/employment groups.
      if (isVoterEmploymentGroup) {
        target.querySelectorAll(".btn").forEach((btn) => {
          btn.style.borderColor = "#dc3545";
          btn.style.backgroundColor = "#ffe5e5";
          btn.style.color = "#b02a37";
        });
        target.querySelectorAll(".btn-check").forEach((radio) => {
          if (radio.checked) {
            const checkedLabel = target.querySelector(`label[for="${radio.id}"]`);
            if (checkedLabel) {
              checkedLabel.style.borderColor = "#dc3545";
              checkedLabel.style.backgroundColor = "#ffd6db";
              checkedLabel.style.color = "#8f1d2c";
            }
          }
        });
      }
    } else {
      // For contact number and other fields, keep classic input border highlight.
      input.classList.add("is-invalid");
      if (target) target.classList.remove("invalid-target-box");
    }

    if (input.type === "file") {
      const uploadBox = input.closest(".upload-box");
      if (uploadBox) {
        uploadBox.classList.add("upload-error");
      }
    }

    let error = input._errorEl;
    if (!error || !document.contains(error)) {
      error = document.createElement("div");
      error.className = "error-message text-danger small mt-1";
      if (target) {
        target.insertAdjacentElement("afterend", error);
      } else {
        input.insertAdjacentElement("afterend", error);
      }
      input._errorEl = error;
    }

    error.textContent = message;
  }

  function clearError(input) {
    input.classList.remove("is-invalid");
    const targetSelector = input.getAttribute("data-error-target");
    const target = targetSelector ? document.querySelector(targetSelector) : null;
    if (
      target &&
      (
        target.classList.contains("voter-toggle-group") ||
        target.id === "div-policyGroup"
      )
    ) {
      target.classList.remove("invalid-target-box");

      if (target.classList.contains("voter-toggle-group")) {
        target.querySelectorAll(".btn").forEach((btn) => {
          btn.style.borderColor = "";
          btn.style.backgroundColor = "";
          btn.style.color = "";
        });
      }
    }
    const error = input._errorEl;
    if (error && document.contains(error)) {
      error.remove();
    }
    input._errorEl = null;

    if (input.type === "file") {
      const uploadBox = input.closest(".upload-box");
      if (uploadBox) {
        uploadBox.classList.remove("upload-error");
      }
    }
  }

  function clearRadioGroupErrors(name) {
    if (!name) return;
    const group = document.querySelectorAll(`input[type="radio"][name="${name}"]`);
    group.forEach((radio) => clearError(radio));
  }

  function isValidPersonName(value, minLetters = 1) {
    const text = String(value ?? "").trim();
    if (!text) return false;
    const validChars = /^[A-Za-zÀ-ÖØ-öø-ÿÑñ.' -]+$/;
    const letters = text.match(/[A-Za-zÀ-ÖØ-öø-ÿÑñ]/g) || [];
    if (letters.length < minLetters) return false;
    // Must start/end with a letter for cleaner Philippine-style names.
    if (!/^[A-Za-zÀ-ÖØ-öø-ÿÑñ]/.test(text) || !/[A-Za-zÀ-ÖØ-öø-ÿÑñ]$/.test(text)) {
      return false;
    }
    return validChars.test(text);
  }

  function isValidAlphaText(value) {
    const text = String(value ?? "").trim();
    if (!text) return false;
    return /^[A-Za-zÀ-ÖØ-öø-ÿÑñ .,'-]+$/u.test(text);
  }

  function isValidAddressLikeText(value) {
    const text = String(value ?? "").trim();
    if (!text) return false;
    return /^[A-Za-z0-9À-ÖØ-öø-ÿÑñ .,'#()\/&-]+$/u.test(text);
  }

  function isValidIdNumber(value) {
    const text = String(value ?? "").trim();
    if (!text) return false;
    return /^[A-Za-z0-9-]{3,50}$/.test(text);
  }

function isActuallyVisible(el) {
  if (!el) return false;

  // ignore hidden inputs always
  if (el.type === "hidden") return false;

  // ignore disabled always
  if (el.disabled) return false;

  // ignore elements that are not actually rendered (covers d-none, display:none, hidden parents, etc.)
  // offsetParent is null when display:none (except fixed elements, which inputs aren't)
  if (el.offsetParent === null) return false;

  const style = window.getComputedStyle(el);
  if (style.visibility === "hidden") return false;

  return true;
}


  function validateField(field, showMessages = false, options = {}) {
    const { includeHiddenSteps = false } = options;

    // For full-form submit checks, hidden step fields should still be validated.
    // Disabled and hidden inputs are always ignored.
    if (field.type === "hidden" || field.disabled) return true;
    if (!includeHiddenSteps && !isActuallyVisible(field)) return true;

    clearError(field);
    let valid = true;

    // REQUIRED
    if (field.hasAttribute("required")) {
      if (field.type === "checkbox" && !field.checked) {
        valid = false;
        if (showMessages) showError(field, "This field is required.");
      } else if (field.type === "radio") {
        const group = document.querySelectorAll(`input[type="radio"][name="${field.name}"]`);
        const firstRadio = group.length ? group[0] : field;

        // Validate radio group once only (prevents duplicate error messages).
        if (field !== firstRadio) {
          return true;
        }

        clearRadioGroupErrors(field.name);

        if (![...group].some((r) => r.checked)) {
          valid = false;
          if (showMessages) showError(firstRadio, "Please select an option.");
        }
      } else if (field.type === "file") {
        if (!field.files || field.files.length === 0) {
          valid = false;
          if (showMessages) showError(field, "Please upload a file.");
        }
      } else if (!field.value.trim()) {
        valid = false;
        if (showMessages) showError(field, "This field is required.");
      }
    }

    // Name fields: letters, spaces, apostrophe, hyphen only.
    const nameFieldIds = new Set([
      "firstName",
      "lastName",
      "middleName",
      "emergencyFirstName",
      "emergencyLastName",
      "emergencyMiddleName"
    ]);
    if (nameFieldIds.has(field.id) && field.value.trim()) {
      const requiredNameIds = new Set(["firstName", "lastName", "emergencyFirstName", "emergencyLastName"]);
      const minLetters = requiredNameIds.has(field.id) ? 2 : 1;
      if (!isValidPersonName(field.value, minLetters)) {
        valid = false;
        if (showMessages) showError(field, "Please enter a valid name.");
      }
    }

    const alphaTextIds = new Set([
      "occupationInput",
      "religionOther",
      "suffixOther",
      "schoolNameInput",
      "emergencySuffixOther",
      "emergencyRelationshipOther"
    ]);
    if (alphaTextIds.has(field.id) && field.value.trim()) {
      if (!isValidAlphaText(field.value)) {
        valid = false;
        if (showMessages) showError(field, "Please enter valid text.");
      }
    }

    const addressLikeIds = new Set([
      "houseNumber",
      "streetName",
      "phaseNumber",
      "unitNumber",
      "unitNumberLot",
      "lotNumber",
      "blockNumber",
      "subdivisionSitio",
      "subdivisionLotBlock",
      "emergencyAddress"
    ]);
    if (addressLikeIds.has(field.id) && field.value.trim()) {
      if (!isValidAddressLikeText(field.value)) {
        valid = false;
        if (showMessages) showError(field, "Please enter valid characters only.");
      }
    }

    if (field.id === "idNumberInput" && field.value.trim()) {
      if (!isValidIdNumber(field.value)) {
        valid = false;
        if (showMessages) showError(field, "ID Number must be 3-50 characters (letters, numbers, hyphen only).");
      }
    }

    // ✅ PHONE (+63 UI): expects 10 digits starting with 9 (9XXXXXXXXX)
    if (field.classList.contains("phone-input") && field.value.trim()) {
      const v = field.value.trim();
      if (!/^9\d{9}$/.test(v)) {
        valid = false;
        if (showMessages) showError(field, "Phone must be 10 digits and start with 9 (e.g., 9XXXXXXXXX).");
      }
    }

    // ✅ AGE: must be at least 18 years old
    if (field.id === "dateOfBirth" && field.value) {
      const dob = new Date(field.value);
      if (!isNaN(dob.getTime())) {
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
          age--;
        }
        if (age < 18) {
          valid = false;
          if (showMessages) showError(field, "You must be at least 18 years old to register.");
        }
      }
    }

    // EMAIL (simple allowed TLDs)
    if (field.classList.contains("email-input") && field.value.trim()) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.(com|net|org|edu|gov|ph)$/;
      if (!emailRegex.test(field.value)) {
        valid = false;
        if (showMessages) showError(field, "Enter a valid email like name@gmail.com");
      }
    }

    return valid;
  }

  function validateAllSteps(showMessages = false) {
    let firstInvalid = null;
    let firstInvalidStep = -1;

    sections.forEach((section, stepIndex) => {
      const fields = section.querySelectorAll("input, select, textarea");
      fields.forEach((field) => {
        const valid = validateField(field, showMessages, { includeHiddenSteps: true });
        if (!valid && !firstInvalid) {
          firstInvalid = field;
          firstInvalidStep = stepIndex;
        }
      });
    });

    return {
      valid: !firstInvalid,
      firstInvalid,
      firstInvalidStep
    };
  }

  function validateStepsThrough(maxStepIndex, showMessages = false) {
    let firstInvalid = null;
    let firstInvalidStep = -1;

    sections.forEach((section, stepIndex) => {
      if (stepIndex > maxStepIndex) return;
      const fields = section.querySelectorAll("input, select, textarea");
      fields.forEach((field) => {
        const valid = validateField(field, showMessages, { includeHiddenSteps: true });
        if (!valid && !firstInvalid) {
          firstInvalid = field;
          firstInvalidStep = stepIndex;
        }
      });
    });

    return {
      valid: !firstInvalid,
      firstInvalid,
      firstInvalidStep
    };
  }

  function isStepValid(stepIndex) {
    const fields = sections[stepIndex].querySelectorAll("input, select, textarea");
    return [...fields].every((field) => validateField(field, false));
  }

  function updateNextButtonState() {
    const currentSection = sections[currentStep];
    const nextBtn = currentSection.querySelector(".next-btn");
    if (!nextBtn) return;

    const blocked = !isStepValid(currentStep);
    nextBtn.disabled = false; // keep clickable so we can focus invalid fields
    nextBtn.classList.toggle("btn-disabled", blocked);
    nextBtn.setAttribute("aria-disabled", blocked ? "true" : "false");
  }

  function updateUI() {
    steps.forEach((step, index) => {
      step.classList.toggle("active", index === currentStep);
      step.classList.toggle("completed", index < currentStep);
    });

    sections.forEach((section, index) => {
      section.classList.toggle("active-step", index === currentStep);
    });

    updateNextButtonState();
  }

  /* ===============================
     INPUT SANITIZERS + LIVE VALIDATION
     =============================== */
  // ✅ all phone-inputs limited to 10 digits now (for +63 UI)
  document.querySelectorAll(".phone-input").forEach((input) => {
    input.addEventListener("input", () => {
      input.value = input.value.replace(/\D/g, "").slice(0, 10);
    });
  });

  // Strict real-time filter for name fields.
  const nameFieldIds = [
    "firstName",
    "lastName",
    "middleName",
    "emergencyFirstName",
    "emergencyLastName",
    "emergencyMiddleName"
  ];
  nameFieldIds.forEach((id) => {
    const input = document.getElementById(id);
    if (!input) return;

    const sanitizeName = (value) => String(value ?? "").replace(/[^A-Za-zÀ-ÖØ-öø-ÿÑñ.' -]/gu, "");

    input.addEventListener("beforeinput", (e) => {
      const incoming = e.data ?? "";
      if (!incoming) return;
      if (sanitizeName(incoming) !== incoming) {
        e.preventDefault();
      }
    });

    input.addEventListener("paste", (e) => {
      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData)?.getData("text") ?? "";
      const clean = sanitizeName(pasted);
      const start = input.selectionStart ?? input.value.length;
      const end = input.selectionEnd ?? input.value.length;
      const next = input.value.slice(0, start) + clean + input.value.slice(end);
      input.value = next;
      const caret = start + clean.length;
      input.setSelectionRange(caret, caret);
      input.dispatchEvent(new Event("input", { bubbles: true }));
    });

    input.addEventListener("input", () => {
      const original = input.value;
      // Allow letters (incl. accented), spaces, apostrophe, and hyphen only.
      const filtered = sanitizeName(original);
      if (filtered !== original) {
        input.value = filtered;
      }
    });
  });

  // Real-time filter for alpha-text fields (letters, spaces, period, apostrophe, hyphen).
  const alphaTextFieldIds = [
    "occupationInput",
    "religionOther",
    "suffixOther",
    "schoolNameInput",
    "emergencySuffixOther",
    "emergencyRelationshipOther"
  ];
  alphaTextFieldIds.forEach((id) => {
    const input = document.getElementById(id);
    if (!input) return;

    const sanitizeAlpha = (value) => String(value ?? "").replace(/[^A-Za-zÀ-ÖØ-öø-ÿÑñ .,'-]/gu, "");

    input.addEventListener("beforeinput", (e) => {
      const incoming = e.data ?? "";
      if (!incoming) return;
      if (sanitizeAlpha(incoming) !== incoming) e.preventDefault();
    });

    input.addEventListener("paste", (e) => {
      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData)?.getData("text") ?? "";
      const clean = sanitizeAlpha(pasted);
      const start = input.selectionStart ?? input.value.length;
      const end = input.selectionEnd ?? input.value.length;
      input.value = input.value.slice(0, start) + clean + input.value.slice(end);
      const caret = start + clean.length;
      input.setSelectionRange(caret, caret);
      input.dispatchEvent(new Event("input", { bubbles: true }));
    });

    input.addEventListener("input", () => {
      const filtered = sanitizeAlpha(input.value);
      if (filtered !== input.value) input.value = filtered;
    });
  });

  // Real-time filter for address-like fields.
  const addressLikeFieldIds = [
    "houseNumber",
    "streetName",
    "phaseNumber",
    "unitNumber",
    "unitNumberLot",
    "lotNumber",
    "blockNumber",
    "subdivisionSitio",
    "subdivisionLotBlock",
    "emergencyAddress"
  ];
  addressLikeFieldIds.forEach((id) => {
    const input = document.getElementById(id);
    if (!input) return;

    const sanitizeAddress = (value) => String(value ?? "").replace(/[^A-Za-z0-9À-ÖØ-öø-ÿÑñ .,'#()\/&-]/gu, "");

    input.addEventListener("beforeinput", (e) => {
      const incoming = e.data ?? "";
      if (!incoming) return;
      if (sanitizeAddress(incoming) !== incoming) e.preventDefault();
    });

    input.addEventListener("paste", (e) => {
      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData)?.getData("text") ?? "";
      const clean = sanitizeAddress(pasted);
      const start = input.selectionStart ?? input.value.length;
      const end = input.selectionEnd ?? input.value.length;
      input.value = input.value.slice(0, start) + clean + input.value.slice(end);
      const caret = start + clean.length;
      input.setSelectionRange(caret, caret);
      input.dispatchEvent(new Event("input", { bubbles: true }));
    });

    input.addEventListener("input", () => {
      const filtered = sanitizeAddress(input.value);
      if (filtered !== input.value) input.value = filtered;
    });
  });

  // Real-time filter for ID number (letters, numbers, hyphen only).
  const idNumberInputSanitize = document.getElementById("idNumberInput");
  if (idNumberInputSanitize) {
    const sanitizeIdNumber = (value) => String(value ?? "").replace(/[^A-Za-z0-9-]/g, "");

    idNumberInputSanitize.addEventListener("beforeinput", (e) => {
      const incoming = e.data ?? "";
      if (!incoming) return;
      if (sanitizeIdNumber(incoming) !== incoming) e.preventDefault();
    });

    idNumberInputSanitize.addEventListener("paste", (e) => {
      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData)?.getData("text") ?? "";
      const clean = sanitizeIdNumber(pasted);
      const start = idNumberInputSanitize.selectionStart ?? idNumberInputSanitize.value.length;
      const end = idNumberInputSanitize.selectionEnd ?? idNumberInputSanitize.value.length;
      idNumberInputSanitize.value = idNumberInputSanitize.value.slice(0, start) + clean + idNumberInputSanitize.value.slice(end);
      const caret = start + clean.length;
      idNumberInputSanitize.setSelectionRange(caret, caret);
      idNumberInputSanitize.dispatchEvent(new Event("input", { bubbles: true }));
    });

    idNumberInputSanitize.addEventListener("input", () => {
      const filtered = sanitizeIdNumber(idNumberInputSanitize.value);
      if (filtered !== idNumberInputSanitize.value) idNumberInputSanitize.value = filtered;
    });
  }

  // Catch-all sanitizer for all text-like inputs to prevent random characters.
  const strictNameIds = new Set([
    "firstName",
    "lastName",
    "middleName",
    "emergencyFirstName",
    "emergencyLastName",
    "emergencyMiddleName"
  ]);
  const strictAlphaIds = new Set([
    "occupationInput",
    "religionOther",
    "suffixOther",
    "schoolNameInput",
    "emergencySuffixOther",
    "emergencyRelationshipOther"
  ]);
  const strictAddressIds = new Set([
    "houseNumber",
    "streetName",
    "phaseNumber",
    "unitNumber",
    "unitNumberLot",
    "lotNumber",
    "blockNumber",
    "subdivisionSitio",
    "subdivisionLotBlock",
    "emergencyAddress"
  ]);

  const sanitizeByField = (field, value) => {
    const text = String(value ?? "");
    if (field.classList.contains("phone-input") || field.type === "tel") {
      return text.replace(/\D/g, "");
    }
    if (field.id === "idNumberInput") {
      return text.replace(/[^A-Za-z0-9-]/g, "");
    }
    if (strictNameIds.has(field.id)) {
      return text.replace(/[^A-Za-zÀ-ÖØ-öø-ÿÑñ.' -]/gu, "");
    }
    if (strictAlphaIds.has(field.id)) {
      return text.replace(/[^A-Za-zÀ-ÖØ-öø-ÿÑñ .,'-]/gu, "");
    }
    if (strictAddressIds.has(field.id)) {
      return text.replace(/[^A-Za-z0-9À-ÖØ-öø-ÿÑñ .,'#()\/&-]/gu, "");
    }
    if (field.type === "email" || field.classList.contains("email-input")) {
      return text.replace(/[^A-Za-z0-9._%+\-@]/g, "");
    }
    // Default safe text sanitizer for any other free-text field.
    return text.replace(/[^A-Za-z0-9À-ÖØ-öø-ÿÑñ .,'#()\/&-]/gu, "");
  };

  document.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], textarea').forEach((field) => {
    field.addEventListener("input", () => {
      const cleaned = sanitizeByField(field, field.value);
      if (cleaned !== field.value) {
        field.value = cleaned;
      }
    });
  });

  // Max length limits for text inputs (enforced live).
  const maxLengthById = {
    lastName: 20,
    firstName: 30,
    middleName: 20,
    suffixOther: 3,
    religionOther: 100,
    occupationInput: 20,
    schoolNameInput: 150,
    idNumberInput: 50,
    unitNumber: 50,
    unitNumberLot: 50,
    houseNumber: 50,
    streetName: 150,
    lotNumber: 50,
    blockNumber: 50,
    phaseNumber: 50,
    subdivisionSitio: 150,
    subdivisionLotBlock: 150,
    emergencyLastName: 20,
    emergencyFirstName: 30,
    emergencyMiddleName: 20,
    emergencySuffixOther: 3,
    emergencyRelationshipOther: 100,
    emergencyAddress: 255
  };

  Object.entries(maxLengthById).forEach(([id, max]) => {
    const el = document.getElementById(id);
    if (!el) return;
    el.maxLength = max;
    el.addEventListener("input", () => {
      if (el.value.length > max) {
        el.value = el.value.slice(0, max);
      }
    });
  });

  // Default max length for most text-like fields not explicitly listed above.
  document.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], textarea').forEach((el) => {
    if (el.type === "hidden") return;
    if (el.maxLength && el.maxLength > 0) return;

    let defaultMax = 150;
    if (el.classList.contains("phone-input")) defaultMax = 10;
    if (el.classList.contains("email-input") || el.type === "email") defaultMax = 150;
    if (el.tagName === "TEXTAREA") defaultMax = 255;

    el.maxLength = defaultMax;
    el.addEventListener("input", () => {
      if (el.value.length > defaultMax) {
        el.value = el.value.slice(0, defaultMax);
      }
    });
  });

  document.querySelectorAll("input, select, textarea").forEach((field) => {
    field.addEventListener("input", () => {
      validateField(field, true);
      updateNextButtonState();
      updateSubmitButtonState(); // documents step
    });

    field.addEventListener("change", () => {
      validateField(field, true);
      updateNextButtonState();
      updateSubmitButtonState();
    });
  });

  /* ===============================
     NEXT / PREV BUTTONS
     =============================== */
  document.querySelectorAll(".next-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      const steppedCheck = validateStepsThrough(currentStep, true);

      if (!steppedCheck.valid) {
        if (steppedCheck.firstInvalidStep >= 0 && steppedCheck.firstInvalidStep !== currentStep) {
          currentStep = steppedCheck.firstInvalidStep;
          updateUI();
        }
        requestAnimationFrame(() => {
          steppedCheck.firstInvalid.scrollIntoView({ behavior: "smooth", block: "center" });
          steppedCheck.firstInvalid.focus();
        });
        return;
      }

      if (currentStep < sections.length - 1) {
        currentStep++;
        updateUI();
      }
    });
  });

  document.querySelectorAll(".prev-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      if (currentStep > 0) {
        currentStep--;
        updateUI();
      }
    });
  });

  /* ===============================
     TOGGLE "OTHER" INPUTS (Suffix, Religion, House Type, Emergency Suffix)
     =============================== */
  document.querySelectorAll(".toggle-other").forEach((select) => {
    select.addEventListener("change", () => {
      const targetClass = select.dataset.target;
      const container =
        select.closest(".col-md-3, .col-md-6, .col-md-12, .mb-3, .form-group, .col, .row, div") || document;

      const otherInput = container.querySelector("." + targetClass);
      if (!otherInput) return;

      if (select.value === "Other") {
        otherInput.classList.remove("d-none");
        otherInput.required = true;
      } else {
        otherInput.classList.add("d-none");
        otherInput.required = false;
        otherInput.value = "";
        clearError(otherInput);
      }

      updateNextButtonState();
    });
  });

  /* ===============================
     ADDRESS SYSTEM TOGGLE
     =============================== */
  const addressSystemSelect = document.getElementById("addressSystem");
  const houseSystemWrapper = document.getElementById("houseSystemWrapper");
  const lotBlockSystemWrapper = document.getElementById("lotBlockSystemWrapper");

  function setWrapperState(wrapper, enabled) {
    if (!wrapper) return;
    wrapper.classList.toggle("d-none", !enabled);
    wrapper.querySelectorAll("input, select").forEach((el) => {
      el.disabled = !enabled;
      if (!enabled) {
        el.value = "";
        if (el.type === "checkbox" || el.type === "radio") el.checked = false;
        clearError(el);
      }
    });
  }

  function setRequired(el, required) {
    if (!el) return;
    if (required) el.setAttribute("required", "required");
    else el.removeAttribute("required");
  }

  function applyAddressSystem() {
    const val = addressSystemSelect ? addressSystemSelect.value : "";

    setWrapperState(houseSystemWrapper, val === "house");
    setWrapperState(lotBlockSystemWrapper, val === "lot_block");

    setRequired(document.getElementById("houseNumber"), val === "house");
    setRequired(document.getElementById("streetName"), val === "house");
    setRequired(document.getElementById("areaNumber"), val === "house");

    setRequired(document.getElementById("phaseNumber"), false);
    setRequired(document.getElementById("lotNumber"), val === "lot_block");
    setRequired(document.getElementById("blockNumber"), val === "lot_block");
    setRequired(document.getElementById("areaNumberLotBlock"), val === "lot_block");

    updateNextButtonState();
  }

  if (addressSystemSelect) {
    addressSystemSelect.addEventListener("change", applyAddressSystem);
    applyAddressSystem();
  }

  /* ===============================
     EMPLOYED / UNEMPLOYED TOGGLE
     =============================== */
  const employed = document.getElementById("employed");
  const unemployed = document.getElementById("unemployed");
  const occupationWrapper = document.getElementById("occupationWrapper");
  const occupationInput = document.getElementById("occupationInput");

  function toggleOccupation() {
    const isEmployed = employed && employed.checked;

    if (occupationWrapper && occupationInput) {
      occupationWrapper.classList.toggle("d-none", !isEmployed);
      occupationInput.required = !!isEmployed;

      if (!isEmployed) {
        occupationInput.value = "";
        clearError(occupationInput);
      }
    }

    updateNextButtonState();
  }

  if (employed) employed.addEventListener("change", toggleOccupation);
  if (unemployed) unemployed.addEventListener("change", toggleOccupation);
  toggleOccupation();

  /* ===============================
     PROOF OF IDENTITY (SKIP + STUDENT ID) + SUBMIT ENABLE
     =============================== */
  const skipProofSwitch = document.getElementById("skipProofSwitch");
  const proofIdentityFields = document.getElementById("proofIdentityFields");
  const proofTypeSelect = document.getElementById("proofTypeSelect");

  const idTypeSelect = document.getElementById("idTypeSelect");
  const idNumberInput = document.getElementById("idNumberInput");
  const schoolNameWrapper = document.getElementById("schoolNameWrapper");
  const schoolNameInput = document.getElementById("schoolNameInput");

  const idFrontInput = document.getElementById("idFrontInput");
  const idBackInput = document.getElementById("idBackInput");
  const pictureInput = document.getElementById("pictureInput");
  const sectorProofSection = document.getElementById("sectorProofSection");

  const submitBtn = document.getElementById("submitBtn");
  const sectorMap = {
    PWD: { checkboxId: "sectorPWD", cardId: "sectorProofPWD" },
    SeniorCitizen: { checkboxId: "sectorSenior", cardId: "sectorProofSenior" },
    Student: { checkboxId: "sectorStudent", cardId: "sectorProofStudent" },
    IndigenousPeople: { checkboxId: "sectorIP", cardId: "sectorProofIP" },
    SingleParent: { checkboxId: "sectorSP", cardId: "sectorProofSoloParent" }
  };

  function getSelectedSectorKeys() {
    return Object.entries(sectorMap)
      .filter(([, meta]) => {
        const checkbox = document.getElementById(meta.checkboxId);
        return !!(checkbox && checkbox.checked);
      })
      .map(([key]) => key);
  }

  function isNationalIdSelected() {
    const raw = String(idTypeSelect?.value ?? "");
    const normalized = raw.toLowerCase().replace(/[^a-z0-9]/g, "");
    // Accept common values/labels.
    return (
      normalized === "nationalid" ||
      normalized === "philsysid" ||
      normalized === "philsysidephilid" ||
      normalized === "ephilid"
    );
  }

  function isSectorDocumentRequired(sectorKey) {
    if (sectorKey === "SingleParent") return false;
    if (sectorKey === "SeniorCitizen") {
      return !(proofTypeSelect && proofTypeSelect.value === "ID");
    }
    if (sectorKey === "IndigenousPeople") {
      return !(proofTypeSelect && proofTypeSelect.value === "ID" && isNationalIdSelected());
    }
    return true;
  }

  function isSectorUploadProhibited(sectorKey) {
    // Business rules:
    // - If Proof Type is ID: do not allow uploading Senior Citizen sector proof (age can be validated already).
    // - If Proof Type is ID + (National ID / PhilSys / ePhilID): do not allow uploading Indigenous People proof.
    const usingId = !!(proofTypeSelect && proofTypeSelect.value === "ID");
    if (!usingId) return false;

    if (sectorKey === "SeniorCitizen") return true;
    if (sectorKey === "IndigenousPeople" && isNationalIdSelected()) return true;
    return false;
  }

  function getSectorElements(sectorKey) {
    const card = document.getElementById(sectorMap[sectorKey].cardId);
    return {
      card,
      docType: card ? card.querySelector(`.sector-doc-type[data-sector="${sectorKey}"]`) : null,
      uploadZone: card ? card.querySelector(`.sector-upload-zone[data-sector="${sectorKey}"]`) : null,
      uploadList: card ? card.querySelector(`.sector-upload-list[data-sector="${sectorKey}"]`) : null,
      addBtn: card ? card.querySelector(`.add-sector-doc-btn[data-sector="${sectorKey}"]`) : null,
      fileInputs: card ? Array.from(card.querySelectorAll(`.sector-doc-file[data-sector="${sectorKey}"]`)) : []
    };
  }

  function resetSectorField(sectorKey) {
    const { docType, uploadZone, uploadList, addBtn, fileInputs } = getSectorElements(sectorKey);
    if (docType) {
      docType.value = "";
      docType.required = false;
      docType.disabled = true;
      clearError(docType);
    }

    if (uploadZone) {
      uploadZone.classList.add("d-none");
    }

    if (addBtn) {
      addBtn.disabled = true;
    }

    if (uploadList) {
      const items = Array.from(uploadList.children);
      items.forEach((item, idx) => {
        const input = item.querySelector(`.sector-doc-file[data-sector="${sectorKey}"]`);
        if (input) {
          input.value = "";
          input.required = false;
          input.disabled = true;
          clearError(input);
          const box = input.closest(".upload-box");
          if (box) {
            box.classList.remove("uploaded", "upload-error");
            const filename = box.querySelector(".uploaded-filename");
            if (filename) filename.remove();
            const removeBtn = box.querySelector(".upload-remove");
            if (removeBtn) removeBtn.remove();
          }
        }

        // Keep only first attachment box and remove dynamic extras.
        if (idx > 0) item.remove();
      });
    } else {
      fileInputs.forEach((fileInput) => {
        fileInput.value = "";
        fileInput.required = false;
        fileInput.disabled = true;
        clearError(fileInput);
      });
    }
  }

  function updateSectorUploadZoneState(sectorKey) {
    const { docType, uploadZone, addBtn, fileInputs } = getSectorElements(sectorKey);
    if (!docType) return;

    const hasType = docType.value.trim() !== "";
    if (uploadZone) {
      uploadZone.classList.toggle("d-none", !hasType);
    }
    if (addBtn) {
      addBtn.disabled = !hasType;
    }

    fileInputs.forEach((fileInput, index) => {
      fileInput.disabled = !hasType;
      if (!hasType) {
        fileInput.required = false;
        fileInput.value = "";
        clearError(fileInput);
      } else {
        // only first box should carry required flag when applicable
        fileInput.required = index === 0;
      }
    });

    if (!hasType) {
      const { uploadList } = getSectorElements(sectorKey);
      if (uploadList) {
        Array.from(uploadList.children).forEach((child, idx) => {
          if (idx > 0) child.remove();
        });
      }
    }
  }

  function updateSectorProofVisibility() {
    const skipped = !!(skipProofSwitch && skipProofSwitch.checked);
    const selectedSectorKeys = getSelectedSectorKeys();
    const shouldShowSection = !skipped && selectedSectorKeys.length > 0;

    if (sectorProofSection) {
      sectorProofSection.classList.toggle("d-none", !shouldShowSection);
    }

    Object.keys(sectorMap).forEach((sectorKey) => {
      const { card, docType } = getSectorElements(sectorKey);
      const isSelected = selectedSectorKeys.includes(sectorKey) && shouldShowSection;

      if (card) {
        card.classList.toggle("d-none", !isSelected);
      }

      if (!isSelected) {
        resetSectorField(sectorKey);
        return;
      }

      if (isSectorUploadProhibited(sectorKey)) {
        // Selected but uploads are prohibited: clear any existing selection/files and keep controls disabled.
        resetSectorField(sectorKey);
        if (card) card.classList.add("opacity-75");
        return;
      }

      if (card) card.classList.remove("opacity-75");
      if (docType) docType.disabled = false;
      updateSectorUploadZoneState(sectorKey);
    });

    Object.keys(sectorMap).forEach((sectorKey) => {
      const selected = selectedSectorKeys.includes(sectorKey) && shouldShowSection;
      const required = selected && isSectorDocumentRequired(sectorKey);
      const { docType, fileInputs } = getSectorElements(sectorKey);

      if (docType) {
        if (isSectorUploadProhibited(sectorKey)) {
          docType.required = false;
          docType.disabled = true;
          clearError(docType);
          return;
        }
        docType.required = required;
        if (!required) clearError(docType);
      }
      fileInputs.forEach((fileInput, index) => {
        if (isSectorUploadProhibited(sectorKey)) {
          fileInput.required = false;
          fileInput.disabled = true;
          clearError(fileInput);
          return;
        }
        fileInput.required = required && index === 0;
        if (!required) clearError(fileInput);
      });
    });
  }

  function toggleStudentSchool() {
    if (!idTypeSelect || !schoolNameWrapper) return;

    const isStudent = idTypeSelect.value === "Student ID";
    schoolNameWrapper.classList.toggle("d-none", !isStudent);

    const skipped = !!(skipProofSwitch && skipProofSwitch.checked);

    if (schoolNameInput) {
      schoolNameInput.required = isStudent && !skipped;
      if (!schoolNameInput.required) {
        schoolNameInput.value = "";
        clearError(schoolNameInput);
      }
    }

    updateNextButtonState();
    updateSubmitButtonState();
  }

  function setProofRequired(isRequired) {
    const requiredFields = [idTypeSelect, idNumberInput, idFrontInput, idBackInput, pictureInput];

    requiredFields.forEach((el) => {
      if (!el) return;
      el.required = isRequired;

      if (!isRequired) {
        clearError(el);

        // optional: clear files when skipping
        if (el.type === "file") el.value = "";
      }
    });

    const isStudent = idTypeSelect && idTypeSelect.value === "Student ID";
    if (schoolNameInput) {
      schoolNameInput.required = isRequired && isStudent;
      if (!schoolNameInput.required) {
        schoolNameInput.value = "";
        clearError(schoolNameInput);
      }
    }
  }

  function isProofComplete() {
    if (skipProofSwitch && skipProofSwitch.checked) return true;

    if (!proofTypeSelect || !proofTypeSelect.value.trim()) return false;

    if (proofTypeSelect.value === "Document") {
      const documentTypeSelect = document.getElementById("documentTypeSelect");
      if (!documentTypeSelect || !documentTypeSelect.value.trim()) return false;

      const docInputs = document.querySelectorAll('input[name="documentProof[]"]');
      let hasDoc = false;
      docInputs.forEach((inp) => {
        if (inp.files && inp.files.length > 0) hasDoc = true;
      });
      if (!hasDoc) return false;
      return isSectorProofComplete();
    }

    if (!idTypeSelect || !idNumberInput || !idFrontInput || !idBackInput || !pictureInput) return false;

    if (!idTypeSelect.value.trim()) return false;
    if (!idNumberInput.value.trim()) return false;

    if (idTypeSelect.value === "Student ID") {
      if (!schoolNameInput || !schoolNameInput.value.trim()) return false;
    }

    if (!idFrontInput.files || idFrontInput.files.length === 0) return false;
    if (!idBackInput.files || idBackInput.files.length === 0) return false;
    if (!pictureInput.files || pictureInput.files.length === 0) return false;

    return isSectorProofComplete();
  }

  function isSectorProofComplete() {
    if (skipProofSwitch && skipProofSwitch.checked) return true;

    const selectedSectorKeys = getSelectedSectorKeys();
    if (selectedSectorKeys.length === 0) return true;

    for (const sectorKey of selectedSectorKeys) {
      if (!isSectorDocumentRequired(sectorKey)) continue;
      const { docType, fileInputs } = getSectorElements(sectorKey);

      if (!docType || !docType.value.trim()) {
        return false;
      }
      const hasAttachment = fileInputs.some((fileInput) => fileInput.files && fileInput.files.length > 0);
      if (!hasAttachment) {
        return false;
      }
    }
    return true;
  }

  function updateSubmitButtonState() {
    if (!submitBtn) return;
    const blocked = !isProofComplete();
    submitBtn.disabled = false;
    submitBtn.classList.toggle("btn-disabled", blocked);
    submitBtn.setAttribute("aria-disabled", blocked ? "true" : "false");
  }

  function applySkipState() {
    const skipped = !!(skipProofSwitch && skipProofSwitch.checked);
    const proofTypeWrapper = document.getElementById("proofTypeWrapper");

    if (proofIdentityFields) {
      proofIdentityFields.classList.toggle("d-none", skipped);
    }

    if (proofTypeWrapper) {
      proofTypeWrapper.classList.toggle("d-none", skipped);
    }

    if (proofTypeSelect) {
      proofTypeSelect.disabled = skipped;
      if (skipped) {
        proofTypeSelect.value = "";
        proofTypeSelect.dispatchEvent(new Event("change"));
      }
    }

    setProofRequired(!skipped);
    toggleStudentSchool();
    updateSectorProofVisibility();
    updateNextButtonState();
    updateSubmitButtonState();
  }

  if (skipProofSwitch) {
    skipProofSwitch.addEventListener("change", () => {
      if (skipProofSwitch.checked) {
        window.UniversalModal?.open({
          title: "Skip Proof of Identity?",
          message:
            "If you skip uploading proof of identity, some services/modules may be restricted until your profile is fully verified. You can upload documents later in your account.",
          buttons: [
            {
              label: "Continue (Skip)",
              class: "btn btn-warning",
              onClick: () => applySkipState(),
            },
            {
              label: "Cancel",
              class: "btn btn-outline-secondary",
              onClick: () => {
                skipProofSwitch.checked = false;
                applySkipState();
              },
            },
          ],
        });
      } else {
        applySkipState();
      }
    });
  }

  if (idTypeSelect) {
    idTypeSelect.addEventListener("change", () => {
      toggleStudentSchool();
      updateSectorProofVisibility();
      updateNextButtonState();
      updateSubmitButtonState();
    });
  }

  if (proofTypeSelect) {
    proofTypeSelect.addEventListener("change", () => {
      updateSectorProofVisibility();
      updateSubmitButtonState();
    });
  }

  document.querySelectorAll(".sector-doc-type").forEach((select) => {
    select.addEventListener("change", () => {
      const sectorKey = String(select.dataset.sector || "");
      if (!sectorKey) return;
      updateSectorUploadZoneState(sectorKey);
      updateNextButtonState();
      updateSubmitButtonState();
    });
  });

  Object.values(sectorMap).forEach((meta) => {
    const checkbox = document.getElementById(meta.checkboxId);
    if (!checkbox) return;
    checkbox.addEventListener("change", () => {
      updateSectorProofVisibility();
      updateNextButtonState();
      updateSubmitButtonState();
    });
  });

  // initial
  toggleStudentSchool();
  updateSectorProofVisibility();
  applySkipState();
  updateUI();

  /* ===============================
     FETCH ACCOUNT CONTACT (PHONE/EMAIL) — INSIDE DOM READY
     =============================== */
  fetch("../PhpFiles/GET/getAccountContact.php", {
    method: "GET",
    credentials: "same-origin",
    headers: { "Accept": "application/json" }
  })
    .then(r => r.json())
    .then(data => {
      if (!data || !data.success) return;

      // phone from API should already be 10 digits (9XXXXXXXXX), but sanitize anyway
      const phone = String(data.phone_number ?? "").replace(/\D/g, "").slice(0, 10);
      const email = String(data.email ?? "");

      // visible (disabled) inputs
      const phoneVisible = document.getElementById("phoneNumber");
      const emailVisible = document.getElementById("emailAddress");

      // hidden inputs that will POST + VALIDATE
      const phoneHidden = document.getElementById("phoneNumberHidden");
      const emailHidden = document.getElementById("emailAddressHidden");

      if (phoneVisible) phoneVisible.value = phone;
      if (phoneHidden) phoneHidden.value = phone;

      if (emailVisible) emailVisible.value = email;
      if (emailHidden) emailHidden.value = email;

      // re-evaluate Next button now that required values exist
      updateNextButtonState();
    })
    .catch(() => {});

  /* ===============================
     AJAX SUBMIT (PREVENT RAW JSON PAGE)
     =============================== */
  const form = document.getElementById("residentRegistrationForm");
  const clientSubmittedAt = document.getElementById("clientSubmittedAt");

  if (form) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();

      const fullCheck = validateAllSteps(true);
      if (!fullCheck.valid || !isProofComplete()) {
        if (fullCheck.firstInvalid) {
          if (fullCheck.firstInvalidStep >= 0 && currentStep !== fullCheck.firstInvalidStep) {
            currentStep = fullCheck.firstInvalidStep;
            updateUI();
          }
          requestAnimationFrame(() => {
            fullCheck.firstInvalid.scrollIntoView({ behavior: "smooth", block: "center" });
            fullCheck.firstInvalid.focus();
          });
        }
        return;
      }

      // set client timestamp
      if (clientSubmittedAt) clientSubmittedAt.value = new Date().toISOString();

      const submitButton = document.getElementById("submitBtn") || form.querySelector('button[type="submit"]');
      if (submitButton) submitButton.disabled = true;

      let temporarilyEnabled = [];
      try {
        form.querySelectorAll("input, select, textarea").forEach((el) => {
          if (!el.disabled) return;
          const hasValue = el.type === "file"
            ? (el.files && el.files.length > 0)
            : (String(el.value ?? "").trim() !== "");
          if (hasValue) {
            el.disabled = false;
            temporarilyEnabled.push(el);
          }
        });

        const res = await fetch(form.action, {
          method: "POST",
          body: new FormData(form),
        });

        const data = await res.json().catch(() => null);

        if (!res.ok || !data || !data.success) {
          const msg = data?.message || "Something went wrong. Please try again.";
          window.UniversalModal?.open({
            title: "Error",
            message: msg,
            buttons: [{ label: "Close", class: "btn btn-outline-secondary", onClick: () => {} }],
          });

          if (submitButton) submitButton.disabled = false;
          return;
        }

        window.UniversalModal?.open({
          title: "Success",
          message: "Profile Information Successfully Saved!",
          buttons: [
            {
              label: "Go to Dashboard",
              class: "btn btn-success",
              onClick: () => {
                window.location.href = data.redirect || "resident_dashboard.php";
              },
            },
          ],
        });
      } catch (err) {
        console.error(err);
        window.UniversalModal?.open({
          title: "Error",
          message: "Network error. Please try again.",
          buttons: [{ label: "Close", class: "btn btn-outline-secondary", onClick: () => {} }],
        });

        if (submitButton) submitButton.disabled = false;
      } finally {
        temporarilyEnabled.forEach((el) => { el.disabled = true; });
      }
    });
  }
});
