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
    input.classList.add("is-invalid");

    const targetSelector = input.getAttribute("data-error-target");
    const target = targetSelector ? document.querySelector(targetSelector) : null;

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
    const error = input._errorEl;
    if (error && document.contains(error)) {
      error.remove();
    }
    input._errorEl = null;
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


  function validateField(field, showMessages = false) {
    // ✅ Skip hidden/disabled fields completely
    if (!isActuallyVisible(field)) return true;

    clearError(field);
    let valid = true;

    // REQUIRED
    if (field.hasAttribute("required")) {
      if (field.type === "checkbox" && !field.checked) {
        valid = false;
        if (showMessages) showError(field, "This field is required.");
      } else if (field.type === "radio") {
        const group = document.querySelectorAll(`input[name="${field.name}"]`);
        if (![...group].some((r) => r.checked)) {
          valid = false;
          if (showMessages) showError(field, "Please select an option.");
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
      const fields = sections[currentStep].querySelectorAll("input, select, textarea");
      let firstInvalid = null;

      fields.forEach((f) => {
        const valid = validateField(f, true);
        if (!valid && !firstInvalid) firstInvalid = f;
      });

      if (firstInvalid) {
        firstInvalid.scrollIntoView({ behavior: "smooth", block: "center" });
        firstInvalid.focus();
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

  const idTypeSelect = document.getElementById("idTypeSelect");
  const idNumberInput = document.getElementById("idNumberInput");
  const schoolNameWrapper = document.getElementById("schoolNameWrapper");
  const schoolNameInput = document.getElementById("schoolNameInput");

  const idFrontInput = document.getElementById("idFrontInput");
  const idBackInput = document.getElementById("idBackInput");
  const pictureInput = document.getElementById("pictureInput");

  const submitBtn = document.getElementById("submitBtn");

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
        el.classList.remove("is-invalid");
        const err = el.nextElementSibling;
        if (err && err.classList.contains("error-message")) err.remove();

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

    const proofTypeSelect = document.getElementById("proofTypeSelect");
    if (!proofTypeSelect || !proofTypeSelect.value.trim()) return false;

    if (proofTypeSelect.value === "Document") {
      const documentTypeSelect = document.getElementById("documentTypeSelect");
      if (!documentTypeSelect || !documentTypeSelect.value.trim()) return false;

      const docInputs = document.querySelectorAll('input[name="documentProof[]"]');
      let hasDoc = false;
      docInputs.forEach((inp) => {
        if (inp.files && inp.files.length > 0) hasDoc = true;
      });
      return hasDoc;
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
    const proofTypeSelect = document.getElementById("proofTypeSelect");

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
      updateNextButtonState();
      updateSubmitButtonState();
    });
  }

  // initial
  toggleStudentSchool();
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

      if (!isProofComplete()) {
        const fields = sections[currentStep].querySelectorAll("input, select, textarea");
        let firstInvalid = null;

        fields.forEach((f) => {
          const valid = validateField(f, true);
          if (!valid && !firstInvalid) firstInvalid = f;
        });

        if (firstInvalid) {
          firstInvalid.scrollIntoView({ behavior: "smooth", block: "center" });
          firstInvalid.focus();
        }
        return;
      }

      // set client timestamp
      if (clientSubmittedAt) clientSubmittedAt.value = new Date().toISOString();

      const submitButton = document.getElementById("submitBtn") || form.querySelector('button[type="submit"]');
      if (submitButton) submitButton.disabled = true;

      try {
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
      }
    });
  }
});
