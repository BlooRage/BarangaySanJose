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

  function normalizeForGarbageCheck(value) {
    const raw = String(value ?? "").toLowerCase();
    // Keep only ASCII letters/digits so patterns like asdf/qwer/1234 are detected reliably.
    return raw.replace(/[^a-z0-9]+/g, "");
  }

  function isLikelyGarbageText(value, mode = "general") {
    const compact = normalizeForGarbageCheck(value);
    if (!compact) return false;

    // Excessive repeated characters like "aaaaa", "111111".
    if (/(.)\1{3,}/.test(compact)) return true;

    // Repeated short patterns like "ababab", "lololol", "123123123".
    if (compact.length >= 6 && /(.{2,3})\1{2,}/.test(compact)) return true;

    // Common keyboard-mash sequences.
    if (/(asdf|qwer|zxcv|qwerty|poiuy|lkjh|mnbv|abcd|1234|0000)/.test(compact)) return true;

    if (mode === "name") {
      const lettersOnly = String(value ?? "").toLowerCase().replace(/[^a-z]/g, "");
      if (lettersOnly.length >= 8) {
        // Very long letter strings without any vowels are almost always garbage.
        if (!/[aeiou]/.test(lettersOnly)) return true;
      }
    }

    return false;
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
    if (isLikelyGarbageText(text, "name")) return false;
    return validChars.test(text);
  }

  function isValidAlphaText(value) {
    const text = String(value ?? "").trim();
    if (!text) return false;
    if (isLikelyGarbageText(text, "alpha")) return false;
    return /^[A-Za-zÀ-ÖØ-öø-ÿÑñ .,'-]+$/u.test(text);
  }

  function isValidAddressLikeText(value) {
    const text = String(value ?? "").trim();
    if (!text) return false;
    // Only apply "garbage" heuristics when the address contains letters (so numeric-only house/lot numbers are fine).
    if (/[A-Za-zÀ-ÖØ-öø-ÿÑñ]/u.test(text) && isLikelyGarbageText(text, "address")) return false;
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
        if (showMessages) showError(field, "Input appears invalid or random.");
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
        if (showMessages) showError(field, "Input appears invalid or random.");
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
        if (showMessages) showError(field, "Input appears invalid or random.");
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

    // Generic garbage detection for any other free-text inputs (future-proofing).
    // We only apply this when the field contains letters to avoid false-positives on numeric IDs/codes.
    if (
      valid &&
      field.value.trim() &&
      (field.tagName === "TEXTAREA" || field.type === "text") &&
      field.id !== "idNumberInput" &&
      !field.classList.contains("phone-input") &&
      !field.classList.contains("email-input") &&
      !nameFieldIds.has(field.id) &&
      !alphaTextIds.has(field.id) &&
      !addressLikeIds.has(field.id)
    ) {
      const val = field.value.trim();
      if (/[A-Za-zÀ-ÖØ-öø-ÿÑñ]/u.test(val) && isLikelyGarbageText(val, "general")) {
        valid = false;
        if (showMessages) showError(field, "Input appears invalid or random.");
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
    const section = sections[stepIndex];
    if (!section) return true;
    const fields = section.querySelectorAll("input, select, textarea");
    return [...fields].every((field) => validateField(field, false));
  }

  function updateNextButtonState() {
    if (!sections.length) return;
    if (currentStep < 0 || currentStep >= sections.length) {
      currentStep = Math.min(Math.max(currentStep, 0), sections.length - 1);
    }

    const currentSection = sections[currentStep];
    if (!currentSection) return;
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
     BIRTHPLACE WORKFLOW
     =============================== */
  const birthInPhilippines = document.getElementById("birthInPhilippines");
  const birthplacePhilippinesRow = document.getElementById("birthplacePhilippinesRow");
  const birthplaceInternationalRow = document.getElementById("birthplaceInternationalRow");
  const birthRegion = document.getElementById("birthRegion");
  const birthProvince = document.getElementById("birthProvince");
  const birthCity = document.getElementById("birthCity");
  const birthCountry = document.getElementById("birthCountry");
  const birthState = document.getElementById("birthState");
  const birthProvinceRequiredMark = document.getElementById("birthProvinceRequiredMark");
  const birthProvinceOptionalNote = document.getElementById("birthProvinceOptionalNote");
  const birthProvinceApplicable = document.getElementById("birthProvinceApplicable");

  const birthplaceApiBase = "https://psgc.gitlab.io/api";
  const countryStateDataUrl = String(window.COUNTRY_STATE_DATA_URL || "../Public-Assets/Data/countries-states.json");
  const birthplaceCache = {
    regions: null,
    countries: null,
    provincesByRegion: new Map(),
    citiesByRegion: new Map(),
    citiesByProvince: new Map()
  };

  async function fetchBirthplaceJson(path) {
    const res = await fetch(`${birthplaceApiBase}${path}`, { headers: { Accept: "application/json" } });
    if (!res.ok) throw new Error(`Birthplace lookup failed: ${res.status}`);
    return res.json();
  }

  async function fetchCountryStateJson() {
    if (!birthplaceCache.countries) {
      const res = await fetch(countryStateDataUrl, { headers: { Accept: "application/json" } });
      if (!res.ok) throw new Error(`Country/state lookup failed: ${res.status}`);
      birthplaceCache.countries = await res.json();
    }
    return birthplaceCache.countries;
  }

  function fillSelectOptions(select, items, placeholder, getLabel) {
    if (!select) return;
    select.innerHTML = `<option value="">${placeholder}</option>`;
    (Array.isArray(items) ? items : []).forEach((item) => {
      const label = String(getLabel(item) || "").trim();
      const code = String(item?.code || "").trim();
      if (!label || !code) return;
      const opt = document.createElement("option");
      opt.value = label;
      opt.textContent = label;
      opt.dataset.code = code;
      select.appendChild(opt);
    });
  }

  function resetBirthplaceSelect(select, placeholder) {
    if (!select) return;
    select.innerHTML = `<option value="">${placeholder}</option>`;
    select.value = "";
    select.disabled = true;
    clearError(select);
  }

  function setBirthProvinceApplicability(isApplicable) {
    if (birthProvinceApplicable) {
      birthProvinceApplicable.value = isApplicable ? "yes" : "no";
    }
    if (birthProvince) {
      birthProvince.required = isApplicable;
      if (!isApplicable) {
        birthProvince.value = "";
        clearError(birthProvince);
      }
    }
    if (birthProvinceRequiredMark) {
      birthProvinceRequiredMark.classList.toggle("d-none", !isApplicable);
    }
    if (birthProvinceOptionalNote) {
      birthProvinceOptionalNote.classList.toggle("d-none", isApplicable);
    }
  }

  async function loadCountries() {
    if (!birthCountry) return;
    const countries = await fetchCountryStateJson();
    birthCountry.innerHTML = '<option value="">Select country</option>';
    (Array.isArray(countries) ? countries : []).forEach((country) => {
      const name = String(country?.name || "").trim();
      if (!name) return;
      const option = document.createElement("option");
      option.value = name;
      option.textContent = name;
      birthCountry.appendChild(option);
    });
    birthCountry.disabled = false;
  }

  async function loadStatesForCountry(countryName) {
    if (!birthState) return;
    const countries = await fetchCountryStateJson();
    const country = (Array.isArray(countries) ? countries : []).find((entry) => String(entry?.name || "").trim() === countryName);
    const states = Array.isArray(country?.states) ? country.states : [];
    birthState.innerHTML = `<option value="">${states.length ? "Select state / province" : "No state / province list"}</option>`;
    states.forEach((stateName) => {
      const name = String(stateName || "").trim();
      if (!name) return;
      const option = document.createElement("option");
      option.value = name;
      option.textContent = name;
      birthState.appendChild(option);
    });
    birthState.disabled = states.length === 0;
    birthState.required = false;
  }

  async function loadBirthRegions() {
    if (!birthRegion) return;
    if (!birthplaceCache.regions) {
      birthplaceCache.regions = await fetchBirthplaceJson("/regions/");
    }
    fillSelectOptions(
      birthRegion,
      birthplaceCache.regions,
      "Select region",
      (item) => item?.regionName || item?.name
    );
    birthRegion.disabled = false;
  }

  async function loadBirthProvinces(regionCode) {
    if (!birthProvince) return;
    if (!birthplaceCache.provincesByRegion.has(regionCode)) {
      birthplaceCache.provincesByRegion.set(regionCode, await fetchBirthplaceJson(`/regions/${encodeURIComponent(regionCode)}/provinces/`));
    }
    const provinces = birthplaceCache.provincesByRegion.get(regionCode) || [];
    fillSelectOptions(
      birthProvince,
      provinces,
      provinces.length ? "Select province" : "No province selection",
      (item) => item?.name
    );
    birthProvince.disabled = provinces.length === 0;
    return provinces;
  }

  async function loadBirthCitiesByRegion(regionCode) {
    if (!birthCity) return;
    if (!birthplaceCache.citiesByRegion.has(regionCode)) {
      birthplaceCache.citiesByRegion.set(regionCode, await fetchBirthplaceJson(`/regions/${encodeURIComponent(regionCode)}/cities-municipalities/`));
    }
    const cities = birthplaceCache.citiesByRegion.get(regionCode) || [];
    fillSelectOptions(birthCity, cities, "Select municipality / city", (item) => item?.name);
    birthCity.disabled = cities.length === 0;
  }

  async function loadBirthCitiesByProvince(provinceCode) {
    if (!birthCity) return;
    if (!birthplaceCache.citiesByProvince.has(provinceCode)) {
      birthplaceCache.citiesByProvince.set(provinceCode, await fetchBirthplaceJson(`/provinces/${encodeURIComponent(provinceCode)}/cities-municipalities/`));
    }
    const cities = birthplaceCache.citiesByProvince.get(provinceCode) || [];
    fillSelectOptions(birthCity, cities, "Select municipality / city", (item) => item?.name);
    birthCity.disabled = cities.length === 0;
  }

  function toggleBirthplaceMode() {
    const mode = birthInPhilippines ? birthInPhilippines.value : "";
    const isPhilippines = mode === "yes";
    const isInternational = mode === "no";

    if (birthplacePhilippinesRow) birthplacePhilippinesRow.classList.toggle("d-none", !isPhilippines);
    if (birthplaceInternationalRow) birthplaceInternationalRow.classList.toggle("d-none", !isInternational);

    if (birthRegion) {
      birthRegion.disabled = !isPhilippines;
      birthRegion.required = isPhilippines;
      if (!isPhilippines) {
        birthRegion.value = "";
        clearError(birthRegion);
      }
    }

    if (!isPhilippines) {
      resetBirthplaceSelect(birthProvince, "Select province");
      resetBirthplaceSelect(birthCity, "Select municipality / city");
    }

    setBirthProvinceApplicability(false);
    if (birthCity) birthCity.required = isPhilippines;

    if (birthCountry) {
      birthCountry.required = isInternational;
      birthCountry.disabled = !isInternational;
      if (!isInternational) {
        birthCountry.value = "";
        clearError(birthCountry);
      }
    }
    if (birthState) {
      birthState.required = false;
      if (!isInternational) {
        resetBirthplaceSelect(birthState, "Select state / province");
      }
    }

    if (isPhilippines) {
      loadBirthRegions().catch(() => {
        if (birthRegion) birthRegion.innerHTML = '<option value="">Unable to load regions</option>';
      });
    } else if (isInternational) {
      loadCountries().catch(() => {
        if (birthCountry) birthCountry.innerHTML = '<option value="">Unable to load countries</option>';
      });
    }

    updateNextButtonState();
  }

  birthInPhilippines?.addEventListener("change", toggleBirthplaceMode);
  birthRegion?.addEventListener("change", async () => {
    const code = birthRegion.selectedOptions[0]?.dataset.code || "";
    resetBirthplaceSelect(birthProvince, "Select province");
    resetBirthplaceSelect(birthCity, "Select municipality / city");
    setBirthProvinceApplicability(false);
    if (!code) {
      updateNextButtonState();
      return;
    }
    let provinces = [];
    try {
      provinces = await loadBirthProvinces(code);
    } catch (_) {
      if (birthProvince) birthProvince.innerHTML = '<option value="">Unable to load provinces</option>';
      updateNextButtonState();
      return;
    }
    setBirthProvinceApplicability(provinces.length > 0);
    if (!provinces.length) {
      resetBirthplaceSelect(birthProvince, "Province not applicable for selected region");
      await loadBirthCitiesByRegion(code).catch(() => {
        if (birthCity) birthCity.innerHTML = '<option value="">Unable to load municipalities / cities</option>';
      });
    }
    updateNextButtonState();
  });

  birthProvince?.addEventListener("change", async () => {
    const code = birthProvince.selectedOptions[0]?.dataset.code || "";
    resetBirthplaceSelect(birthCity, "Select municipality / city");
    if (!code) {
      updateNextButtonState();
      return;
    }
    await loadBirthCitiesByProvince(code).catch(() => {
      if (birthCity) birthCity.innerHTML = '<option value="">Unable to load municipalities / cities</option>';
    });
    updateNextButtonState();
  });

  birthCountry?.addEventListener("change", async () => {
    const selectedCountry = String(birthCountry.value || "").trim();
    resetBirthplaceSelect(birthState, "Select state / province");
    if (!selectedCountry) {
      updateNextButtonState();
      return;
    }
    await loadStatesForCountry(selectedCountry).catch(() => {
      if (birthState) birthState.innerHTML = '<option value="">Unable to load states / provinces</option>';
    });
    updateNextButtonState();
  });

  toggleBirthplaceMode();

  /* ===============================
     BARANGAY RESIDENCY MONTH/YEAR PICKER
     =============================== */
  const barangayResidencyDisplay = document.getElementById("barangayResidencyDisplay");
  const barangayResidencyMonthYear = document.getElementById("barangayResidencyMonthYear");
  const barangayResidencyModalEl = document.getElementById("barangayResidencyModal");
  const barangayResidencyModal = barangayResidencyModalEl && window.bootstrap ? new bootstrap.Modal(barangayResidencyModalEl) : null;
  const residencyPickerMonth = document.getElementById("residencyPickerMonth");
  const residencyPickerYear = document.getElementById("residencyPickerYear");
  const residencyPickerApply = document.getElementById("residencyPickerApply");
  const residencyPickerCancel = document.getElementById("residencyPickerCancel");
  const residencyPickerPreview = document.getElementById("residencyPickerPreview");

  const residencyMonthLabels = {
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

  function closeResidencyPicker() {
    barangayResidencyModal?.hide();
  }

  function openResidencyPicker() {
    updateResidencyPickerPreview();
    barangayResidencyModal?.show();
  }

  function updateResidencyPickerPreview() {
    if (!residencyPickerPreview) return;
    const month = String(residencyPickerMonth?.value || "").trim();
    const year = String(residencyPickerYear?.value || "").trim();
    residencyPickerPreview.textContent = (month && year)
      ? `Selected: ${residencyMonthLabels[month]} ${year}`
      : "No month selected yet.";
  }

  function syncResidencyPickerFromValue() {
    if (!barangayResidencyMonthYear) return;
    const raw = String(barangayResidencyMonthYear.value || "").trim();
    const match = raw.match(/^(\d{4})-(\d{2})$/);
    if (!match) return;
    if (residencyPickerYear) residencyPickerYear.value = match[1];
    if (residencyPickerMonth) residencyPickerMonth.value = match[2];
    updateResidencyPickerPreview();
  }

  function setResidencyDisplayValue(value) {
    if (!barangayResidencyDisplay || !barangayResidencyMonthYear) return;
    const raw = String(value || "").trim();
    barangayResidencyMonthYear.value = raw;
    const match = raw.match(/^(\d{4})-(\d{2})$/);
    barangayResidencyDisplay.value = match ? `${residencyMonthLabels[match[2]]} ${match[1]}` : "";
    barangayResidencyDisplay.dispatchEvent(new Event("input", { bubbles: true }));
    barangayResidencyDisplay.dispatchEvent(new Event("change", { bubbles: true }));
    clearError(barangayResidencyDisplay);
    updateResidencyPickerPreview();
  }

  if (residencyPickerYear) {
    const currentYear = new Date().getFullYear();
    residencyPickerYear.innerHTML = '<option value="">Select year</option>';
    for (let year = currentYear; year >= currentYear - 120; year -= 1) {
      const option = document.createElement("option");
      option.value = String(year);
      option.textContent = String(year);
      residencyPickerYear.appendChild(option);
    }
  }

  barangayResidencyDisplay?.addEventListener("click", () => {
    syncResidencyPickerFromValue();
    openResidencyPicker();
  });
  barangayResidencyDisplay?.addEventListener("focus", () => {
    syncResidencyPickerFromValue();
    openResidencyPicker();
  });
  barangayResidencyDisplay?.addEventListener("keydown", (event) => {
    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      syncResidencyPickerFromValue();
      openResidencyPicker();
    }
  });

  residencyPickerMonth?.addEventListener("change", updateResidencyPickerPreview);
  residencyPickerYear?.addEventListener("change", updateResidencyPickerPreview);

  residencyPickerApply?.addEventListener("click", () => {
    const month = String(residencyPickerMonth?.value || "").trim();
    const year = String(residencyPickerYear?.value || "").trim();
    if (!month || !year) {
      showError(barangayResidencyDisplay, "Please select month and year.");
      return;
    }
    setResidencyDisplayValue(`${year}-${month}`);
    closeResidencyPicker();
    updateNextButtonState();
  });

  residencyPickerCancel?.addEventListener("click", () => {
    syncResidencyPickerFromValue();
  });

  document.addEventListener("keydown", (event) => {
    if (!barangayResidencyModalEl || !barangayResidencyModalEl.classList.contains("show")) return;
    if (event.key === "Escape") {
      syncResidencyPickerFromValue();
    }
    if (event.key === "Enter" && (document.activeElement === residencyPickerMonth || document.activeElement === residencyPickerYear)) {
      event.preventDefault();
      residencyPickerApply?.click();
    }
  });
  barangayResidencyModalEl?.addEventListener("hidden.bs.modal", syncResidencyPickerFromValue);

  /* ===============================
     HOUSE RESIDENCY START MONTH/YEAR PICKER
     =============================== */
  const residencyStartDisplay = document.getElementById("residencyStartDisplay");
  const residencyStartValue = document.getElementById("residencyDate");
  const residencyStartModalEl = document.getElementById("residencyStartModal");
  const residencyStartModal = residencyStartModalEl && window.bootstrap ? new bootstrap.Modal(residencyStartModalEl) : null;
  const residencyStartMonth = document.getElementById("residencyStartMonth");
  const residencyStartYear = document.getElementById("residencyStartYear");
  const residencyStartApply = document.getElementById("residencyStartApply");
  const residencyStartPreview = document.getElementById("residencyStartPreview");

  function updateResidencyStartPreview() {
    if (!residencyStartPreview) return;
    const month = String(residencyStartMonth?.value || "").trim();
    const year = String(residencyStartYear?.value || "").trim();
    residencyStartPreview.textContent = (month && year)
      ? `Selected: ${residencyMonthLabels[month]} ${year}`
      : "No month selected yet.";
  }

  function syncResidencyStartFromValue() {
    if (!residencyStartValue) return;
    const raw = String(residencyStartValue.value || "").trim();
    const match = raw.match(/^(\d{4})-(\d{2})$/);
    if (!match) {
      if (residencyStartDisplay) residencyStartDisplay.value = "";
      updateResidencyStartPreview();
      return;
    }
    if (residencyStartYear) residencyStartYear.value = match[1];
    if (residencyStartMonth) residencyStartMonth.value = match[2];
    if (residencyStartDisplay) residencyStartDisplay.value = `${residencyMonthLabels[match[2]]} ${match[1]}`;
    updateResidencyStartPreview();
  }

  function setResidencyStartValue(value) {
    if (!residencyStartDisplay || !residencyStartValue) return;
    const raw = String(value || "").trim();
    residencyStartValue.value = raw;
    const match = raw.match(/^(\d{4})-(\d{2})$/);
    residencyStartDisplay.value = match ? `${residencyMonthLabels[match[2]]} ${match[1]}` : "";
    residencyStartDisplay.dispatchEvent(new Event("input", { bubbles: true }));
    residencyStartDisplay.dispatchEvent(new Event("change", { bubbles: true }));
    clearError(residencyStartDisplay);
    updateResidencyStartPreview();
  }

  if (residencyStartYear) {
    const currentYear = new Date().getFullYear();
    residencyStartYear.innerHTML = '<option value="">Select year</option>';
    for (let year = currentYear; year >= currentYear - 120; year -= 1) {
      const option = document.createElement("option");
      option.value = String(year);
      option.textContent = String(year);
      residencyStartYear.appendChild(option);
    }
  }

  residencyStartDisplay?.addEventListener("click", () => {
    syncResidencyStartFromValue();
    updateResidencyStartPreview();
    residencyStartModal?.show();
  });
  residencyStartDisplay?.addEventListener("focus", () => {
    syncResidencyStartFromValue();
    updateResidencyStartPreview();
    residencyStartModal?.show();
  });
  residencyStartDisplay?.addEventListener("keydown", (event) => {
    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      syncResidencyStartFromValue();
      updateResidencyStartPreview();
      residencyStartModal?.show();
    }
  });

  residencyStartMonth?.addEventListener("change", updateResidencyStartPreview);
  residencyStartYear?.addEventListener("change", updateResidencyStartPreview);

  residencyStartApply?.addEventListener("click", () => {
    const month = String(residencyStartMonth?.value || "").trim();
    const year = String(residencyStartYear?.value || "").trim();
    if (!month || !year) {
      showError(residencyStartDisplay, "Please select month and year.");
      return;
    }
    setResidencyStartValue(`${year}-${month}`);
    residencyStartModal?.hide();
    updateNextButtonState();
  });

  residencyStartModalEl?.addEventListener("hidden.bs.modal", syncResidencyStartFromValue);
  syncResidencyStartFromValue();

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
  const idBackWrapper = document.getElementById("idBackWrapper");
  const idUploadLabel = document.getElementById("idUploadLabel");
  const idUploadHint = document.getElementById("idUploadHint");
  const idFrontCaption = document.getElementById("idFrontCaption");
  const idBackCaption = document.getElementById("idBackCaption");

	  const submitBtn = document.getElementById("submitBtn");
	  const sectorMap = {
	    PWD: { checkboxId: "sectorPWD", cardId: "sectorProofPWD" },
	    SeniorCitizen: { checkboxId: "sectorSenior", cardId: "sectorProofSenior" },
	    Student: { checkboxId: "sectorStudent", cardId: "sectorProofStudent" },
	    IndigenousPeople: { checkboxId: "sectorIP", cardId: "sectorProofIP" },
	    SingleParent: { checkboxId: "sectorSP", cardId: "sectorProofSoloParent" }
	  };

	  function isIdLikeSectorDocType(value) {
	    const raw = String(value ?? "").trim();
	    if (!raw) return false;
	    // Treat any doc type containing "ID" as an ID proof (e.g. "Student ID", "PWD ID", "PhilSys ID/ePhilID").
	    return /\bid\b/i.test(raw);
	  }

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

  function isPassportSelected() {
    const raw = String(idTypeSelect?.value ?? "");
    const normalized = raw.toLowerCase().replace(/[^a-z0-9]/g, "");
    return normalized === "passport";
  }

  function applyIdUploadUi() {
    const skipped = !!(skipProofSwitch && skipProofSwitch.checked);
    const usingId = !!(proofTypeSelect && proofTypeSelect.value === "ID");
    const passport = usingId && isPassportSelected();

    // Hide/show the back-side uploader for passport
    if (idBackWrapper) {
      idBackWrapper.classList.toggle("d-none", passport || skipped || !usingId);
    }
    if (idBackInput) {
      // If passport, back is not used
      const disableBack = passport || skipped || !usingId;
      idBackInput.required = usingId && !skipped && !passport;
      idBackInput.disabled = disableBack;
      if (disableBack) {
        idBackInput.value = "";
        clearError(idBackInput);
        const box = idBackInput.closest(".upload-box");
        if (box) {
          box.classList.remove("uploaded", "upload-error");
          const filename = box.querySelector(".uploaded-filename");
          if (filename) filename.remove();
          const removeBtn = box.querySelector(".upload-remove");
          if (removeBtn) removeBtn.remove();
        }
      }
    }

    if (idUploadLabel) {
      idUploadLabel.innerHTML = passport
        ? `Upload Passport <span class="text-danger">*</span>`
        : `Upload ID Front and Back <span class="text-danger">*</span>`;
    }
    if (idUploadHint) {
      idUploadHint.textContent = passport
        ? "Upload a clear photo/scan of your passport."
        : "Upload clear photos/scans of the front and back of your ID.";
    }
    if (idFrontCaption) idFrontCaption.textContent = passport ? "Passport" : "Front";
    if (idBackCaption) idBackCaption.textContent = "Back";

    // Ensure correct required flags for front when using ID
    if (idFrontInput) {
      idFrontInput.required = usingId && !skipped;
      idFrontInput.disabled = skipped || !usingId;
      if (idFrontInput.disabled) {
        idFrontInput.value = "";
        clearError(idFrontInput);
      }
    }
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
	      idPair: card ? card.querySelector(`.sector-upload-idpair[data-sector="${sectorKey}"]`) : null,
	      idHint: card ? card.querySelector(`.sector-idpair-hint[data-sector="${sectorKey}"]`) : null,
	      idFront: card ? card.querySelector(`.sector-doc-idfront[data-sector="${sectorKey}"]`) : null,
	      idBack: card ? card.querySelector(`.sector-doc-idback[data-sector="${sectorKey}"]`) : null,
	      addBtn: card ? card.querySelector(`.add-sector-doc-btn[data-sector="${sectorKey}"]`) : null,
	      maxNote: card ? card.querySelector(`.sector-upload-maxnote[data-sector="${sectorKey}"]`) : null,
	      fileInputs: card ? Array.from(card.querySelectorAll(`.sector-doc-file[data-sector="${sectorKey}"]`)) : []
	    };
	  }

	  function resetSectorField(sectorKey) {
	    const { docType, uploadZone, uploadList, idPair, idHint, idFront, idBack, addBtn, maxNote, fileInputs } = getSectorElements(sectorKey);
	    if (docType) {
	      docType.value = "";
	      docType.required = false;
	      docType.disabled = true;
	      clearError(docType);
    }

	    if (uploadZone) {
	      uploadZone.classList.add("d-none");
	    }

	    if (idPair) idPair.classList.add("d-none");
	    if (idHint) idHint.classList.add("d-none");

	    [idFront, idBack].forEach((input) => {
	      if (!input) return;
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
	    });

	    if (addBtn) {
	      addBtn.disabled = true;
	      addBtn.classList.remove("d-none");
	    }
	    if (maxNote) maxNote.classList.remove("d-none");

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
	    const { docType, uploadZone, uploadList, idPair, idHint, idFront, idBack, addBtn, maxNote, fileInputs } = getSectorElements(sectorKey);
	    if (!docType) return;

	    const hasType = docType.value.trim() !== "";
	    const isIdLike = hasType && isIdLikeSectorDocType(docType.value);

	    if (uploadZone) {
	      uploadZone.classList.toggle("d-none", !hasType);
	    }

	    if (idPair) idPair.classList.toggle("d-none", !isIdLike);
	    if (idHint) idHint.classList.toggle("d-none", !isIdLike);
	    if (uploadList) uploadList.classList.toggle("d-none", isIdLike);
	    if (addBtn) addBtn.classList.toggle("d-none", isIdLike);
	    if (maxNote) maxNote.classList.toggle("d-none", isIdLike);

	    // Disable everything when no doc type is selected.
	    if (!hasType) {
	      fileInputs.forEach((fileInput) => {
	        fileInput.disabled = true;
	        fileInput.required = false;
	        fileInput.value = "";
	        clearError(fileInput);
	      });
	      [idFront, idBack].forEach((input) => {
	        if (!input) return;
	        input.disabled = true;
	        input.required = false;
	        input.value = "";
	        clearError(input);
	      });
	      return;
	    }

	    if (isIdLike) {
	      // Switching to ID flow: clear any multi-attachment selections and keep only ID front/back enabled.
	      if (uploadList) {
	        Array.from(uploadList.children).forEach((child, idx) => {
	          const input = child.querySelector(`.sector-doc-file[data-sector="${sectorKey}"]`);
	          if (input) {
	            input.value = "";
	            clearError(input);
	          }
	          if (idx > 0) child.remove();
	        });
	      }
	      fileInputs.forEach((fileInput) => {
	        fileInput.disabled = true;
	        fileInput.required = false;
	        fileInput.value = "";
	        clearError(fileInput);
	      });
	      [idFront, idBack].forEach((input) => {
	        if (!input) return;
	        input.disabled = false;
	      });
	    } else {
	      // Switching to multi-attachment flow: clear ID front/back selections and enable the first attachment.
	      [idFront, idBack].forEach((input) => {
	        if (!input) return;
	        input.disabled = true;
	        input.required = false;
	        input.value = "";
	        clearError(input);
	      });

	      fileInputs.forEach((fileInput, index) => {
	        fileInput.disabled = false;
	        // required flags are set in updateSectorProofVisibility() to reflect business rules.
	      });
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
	      const { docType, fileInputs, idFront, idBack } = getSectorElements(sectorKey);

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

	      const isIdLike = !!(docType && docType.value && isIdLikeSectorDocType(docType.value));

	      // For ID-like sector proofs, require front+back (when the sector proof is required).
	      if (isIdLike) {
	        fileInputs.forEach((fileInput) => {
	          if (isSectorUploadProhibited(sectorKey)) {
	            fileInput.required = false;
	            fileInput.disabled = true;
	            clearError(fileInput);
	            return;
	          }
	          fileInput.required = false;
	          clearError(fileInput);
	        });
	        [idFront, idBack].forEach((input) => {
	          if (!input) return;
	          if (isSectorUploadProhibited(sectorKey)) {
	            input.required = false;
	            input.disabled = true;
	            clearError(input);
	            return;
	          }
	          input.required = required;
	          if (!required) clearError(input);
	        });
	        return;
	      }

	      // Non-ID proofs behave like the existing multi-attachment flow.
	      [idFront, idBack].forEach((input) => {
	        if (!input) return;
	        input.required = false;
	        input.disabled = true;
	        clearError(input);
	      });

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

    applyIdUploadUi();
    updateNextButtonState();
    updateSubmitButtonState();
  }

  function setProofRequired(isRequired) {
    const requiredFields = [idTypeSelect, idNumberInput, idFrontInput, pictureInput];
    // Back side is required only when NOT passport
    if (!isPassportSelected()) {
      requiredFields.push(idBackInput);
    } else if (idBackInput) {
      idBackInput.required = false;
      clearError(idBackInput);
    }

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
      if (!pictureInput || !pictureInput.files || pictureInput.files.length === 0) return false;
      return isSectorProofComplete();
    }

    if (!idTypeSelect || !idNumberInput || !idFrontInput || !pictureInput) return false;

    if (!idTypeSelect.value.trim()) return false;
    if (!idNumberInput.value.trim()) return false;

    if (idTypeSelect.value === "Student ID") {
      if (!schoolNameInput || !schoolNameInput.value.trim()) return false;
    }

    if (!idFrontInput.files || idFrontInput.files.length === 0) return false;
    if (!isPassportSelected()) {
      if (!idBackInput || !idBackInput.files || idBackInput.files.length === 0) return false;
    }
    if (!pictureInput.files || pictureInput.files.length === 0) return false;

    return isSectorProofComplete();
  }

  function isSectorProofComplete() {
    if (skipProofSwitch && skipProofSwitch.checked) return true;

    const selectedSectorKeys = getSelectedSectorKeys();
    if (selectedSectorKeys.length === 0) return true;

    for (const sectorKey of selectedSectorKeys) {
      if (!isSectorDocumentRequired(sectorKey)) continue;
      const { docType, fileInputs, idFront, idBack } = getSectorElements(sectorKey);

      if (!docType || !docType.value.trim()) {
        return false;
      }
      const isIdLike = isIdLikeSectorDocType(docType.value);
      if (isIdLike) {
        const hasFront = !!(idFront && idFront.files && idFront.files.length > 0);
        const hasBack = !!(idBack && idBack.files && idBack.files.length > 0);
        if (!hasFront || !hasBack) return false;
      } else {
        const hasAttachment = fileInputs.some((fileInput) => fileInput.files && fileInput.files.length > 0);
        if (!hasAttachment) return false;
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
    applyIdUploadUi();
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
      applyIdUploadUi();
      updateSectorProofVisibility();
      updateSubmitButtonState();
    });
  }

	  document.querySelectorAll(".sector-doc-type").forEach((select) => {
	    select.addEventListener("change", () => {
	      // Re-evaluate show/hide + required flags (ID front/back vs multi-attachment).
	      updateSectorProofVisibility();
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
          const rawMsg = String(data?.message || "").trim();
          const isTechnicalMsg = /foreign key|constraint fails|sqlstate|prepare failed|insert .* failed|update .* failed|duplicate entry/i.test(rawMsg);
          const msg = rawMsg && !isTechnicalMsg
            ? rawMsg
            : "Unable to save your resident profiling request right now. Please log out, log in again, and retry.";
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
                window.location.href = data.redirect || "resident_dashboard";
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

  // Some inline scripts on resident_registration.php call these by name (upload box logic).
  // Expose them to avoid "updateNextButtonState is not defined" breaking button flow.
  window.updateNextButtonState = updateNextButtonState;
  window.updateSubmitButtonState = updateSubmitButtonState;
});
