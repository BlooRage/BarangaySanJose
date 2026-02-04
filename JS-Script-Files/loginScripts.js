// loginScripts.js (FIXED - OLD VALIDATIONS RESTORED + NEW MODAL COMPAT)
// Adds: INACTIVE LOGIN → "Verify your account first" → Continue → OTP (same OTP form) → UniversalModal success → Continue redirect
// Keeps: your existing transitions + Signup OTP + Forgot OTP using SAME OTP form

console.log("UniversalModal:", typeof UniversalModal);

// ===== DOM Elements =====
const container = document.querySelector(".login-signup-container");
const loginForm = document.getElementById("loginForm");
const signupForm = document.getElementById("signupForm");
const authImage = document.getElementById("authImage");

const inactiveVerifyStep = document.getElementById("inactive-verify-step");
const inactiveContinueBtn = document.getElementById("inactiveContinueBtn");

const otpForm = document.getElementById("otp-form");
const otpInputFields = otpForm ? otpForm.querySelectorAll(".otp-inputs input") : [];
const verifyOTPBtn = document.getElementById("verifyOTPBtn");

const phoneInput = document.getElementById("RPhoneNumber");
const emailInput = document.getElementById("REmail");
const passwordInput = document.getElementById("RPassword");
const confirmPasswordInput = document.getElementById("RConfirmPassword");

const createAccountBtn = document.getElementById("createAccountBtn");
const forgotLink = document.getElementById("forgotPasswordLink");

const loginUserField = document.getElementById("userAccount");
const loginPasswordField = document.getElementById("loginPassword");

// ===== Error Containers =====
const signupErrors = document.getElementById("signupFormErrors");
const loginErrors = document.getElementById("loginFormErrors");

const newPasswordInput = document.getElementById("newPassword");
const confirmNewPasswordInput = document.getElementById("confirmNewPassword");
const submitNewPasswordBtn = document.getElementById("submitNewPasswordBtn");
const resetPasswordErrors = document.getElementById("resetPasswordErrors");

const forgotEmailInput = document.getElementById("forgotEmail");
const forgotPhoneInput = document.getElementById("forgotPhone");
const forgotContinueBtn = document.getElementById("forgotContinueBtn");
const forgotFormErrors = document.getElementById("forgotFormErrors");

const resendOTPBtn = document.getElementById("resendOTP");
const resendTimer = document.getElementById("resendTimer");

// Back link (new preferred) + fallback (old)
const otpBackLink = document.getElementById("otpBackLink") || document.getElementById("returnToSignup");

// OTP message (new preferred) + fallback
const otpMessage = document.getElementById("otpMessage");

// ===== State =====
const currentOTPByPurpose = {};        // stores last OTP per purpose
const resendCountdownByPurpose = {};   // countdown per purpose
const resendIntervalByPurpose = {};    // interval per purpose

let tempSignupData = null;
let otpFrom = ""; // 'signup' | 'forgot' | 'inactive'
let otpRecipient = ""; // 11-digit recipient used for SMS sending: 0XXXXXXXXXX

let verifiedResetEmail = "";
let verifiedResetPhone = "";

// Inactive flow state
let inactiveSession = {
  userId: null,
  phoneMasked: "+63 •••••• XXXX",
  phone10: "",      // 9XXXXXXXXX (from server)
  redirect: "../PhpFiles/Login/unifiedProfileCheck.php",
};

// ===== Utilities =====
const toggleActiveForm = (show, hide) => {
  if (show) show.classList.add("active");
  if (hide) hide.classList.remove("active");
};

const setErrorBox = (div, message) => {
  if (!div) return;
  div.textContent = message;
  div.style.display = "block";
  div.style.background = "#f8d7da";
  div.style.border = "1px solid #f5c2c7";
  div.style.color = "#000";
  div.style.padding = "10px 12px";
  div.style.borderRadius = "6px";
  div.style.marginBottom = "10px";
};

const hideErrorBox = (div) => {
  if (!div) return;
  div.style.display = "none";
  div.textContent = "";
};

const resetErrors = (formType = "signup") => {
  const errorsDiv = formType === "signup" ? signupErrors : loginErrors;
  hideErrorBox(errorsDiv);

  if (formType === "signup") {
    [phoneInput, emailInput, passwordInput, confirmPasswordInput].forEach((el) => {
      if (el) el.style.border = "";
    });
  } else {
    [loginUserField, loginPasswordField].forEach((el) => {
      if (el) el.style.border = "";
    });
  }
};

const showError = (message, formType = "signup", field = null, clearBothPasswords = false) => {
  const errorsDiv = formType === "signup" ? signupErrors : loginErrors;
  setErrorBox(errorsDiv, message);

  if (formType === "login") {
    if (loginPasswordField) loginPasswordField.value = "";
    if (field) field.style.border = "2px solid red";
    if (field) field.focus();
    return;
  }

  if (field) {
    field.style.border = "2px solid red";
    field.focus();

    if (formType === "signup") {
      if (clearBothPasswords) {
        if (passwordInput) passwordInput.value = "";
        if (confirmPasswordInput) confirmPasswordInput.value = "";
      } else if (field === confirmPasswordInput && confirmPasswordInput) {
        confirmPasswordInput.value = "";
      }
    }
  }
};

function hideAllAuthScreens() {
  // main forms
  if (loginForm) loginForm.classList.remove("active");
  if (signupForm) signupForm.classList.remove("active");

  // steps
  document.querySelectorAll(".fp-step").forEach((step) => step.classList.remove("active"));

  // otp container might not be .fp-step in older HTML
  if (otpForm) otpForm.classList.remove("active");
}

// ===== Step Navigation =====
const showStep = (stepId) => {
  hideAllAuthScreens();

  const el = document.getElementById(stepId);
  if (el) el.classList.add("active");
};

// ===== Form Switching (keeps transitions) =====
const switchToSignup = () => {
  if (container) container.classList.add("signup-mode");
  hideAllAuthScreens();
  toggleActiveForm(signupForm, loginForm);
  if (authImage) authImage.classList.replace("login-image", "signup-image");
};

const switchToLogin = () => {
  if (container) container.classList.remove("signup-mode");
  hideAllAuthScreens();
  toggleActiveForm(loginForm, signupForm);
  if (authImage) authImage.classList.replace("signup-image", "login-image");

  // reset otp state
  otpFrom = "";
  otpRecipient = "";
  tempSignupData = null;

  // reset inactive state
  inactiveSession.userId = null;
  inactiveSession.phone10 = "";
  inactiveSession.phoneMasked = "+63 •••••• XXXX";
  inactiveSession.redirect = "../PhpFiles/Login/unifiedProfileCheck.php";
};

// ===== Normalize Phone =====
// 10-digit DB format (9XXXXXXXXX)
function phoneForDB(phone) {
  return (phone || "").replace(/\D/g, "").slice(-10);
}
// 11-digit for SMS send (0XXXXXXXXXX)
function phoneForOTP(phone) {
  const digits = (phone || "").replace(/\D/g, "").slice(-10);
  return "0" + digits;
}

// ===== Force numeric input for phones (RESTORED OLD VERSION) =====
if (phoneInput) {
  phoneInput.addEventListener("input", () => {
    phoneInput.value = phoneInput.value.replace(/[^0-9]/g, "");
  });
}
if (forgotPhoneInput) {
  forgotPhoneInput.addEventListener("input", () => {
    forgotPhoneInput.value = forgotPhoneInput.value.replace(/[^0-9]/g, "");
  });
}

// ===== Toggle Password Visibility (needed by inline onclick in HTML) =====
const togglePassword = (inputId, eyeId) => {
  const input = document.getElementById(inputId);
  const eye = document.getElementById(eyeId);
  if (!input || !eye) return;

  if (input.type === "password") {
    input.type = "text";
    eye.classList.replace("bi-eye", "bi-eye-slash");
  } else {
    input.type = "password";
    eye.classList.replace("bi-eye-slash", "bi-eye");
  }
};
window.togglePassword = togglePassword;

// ===== OTP Auto-Focus =====
otpInputFields.forEach((input, index) => {
  input.addEventListener("input", () => {
    input.value = input.value.replace(/[^0-9]/g, "");
    if (input.value && index < otpInputFields.length - 1) {
      otpInputFields[index + 1].focus();
    }
  });

  input.addEventListener("keydown", (e) => {
    if (e.key === "Backspace" && !input.value && index > 0) {
      otpInputFields[index - 1].focus();
    }
  });
});

// ===== OTP Error =====
function showOtpError(message) {
  if (!otpForm) return;

  let otpErrorDiv = document.getElementById("otpFormErrors");
  if (!otpErrorDiv) {
    otpErrorDiv = document.createElement("div");
    otpErrorDiv.id = "otpFormErrors";
    otpForm.prepend(otpErrorDiv);
  }

  setErrorBox(otpErrorDiv, message);

  otpInputFields.forEach((input) => (input.style.border = "2px solid red"));
  if (otpInputFields[0]) otpInputFields[0].focus();
}

function clearOtpUI() {
  otpInputFields.forEach((input) => {
    input.value = "";
    input.style.border = "";
  });
  const otpErrorDiv = document.getElementById("otpFormErrors");
  if (otpErrorDiv) hideErrorBox(otpErrorDiv);
}

// ===== Back to Login (used by multiple flows) =====
function backToLogin() {
  switchToLogin();
}

// ===== OTP Back behavior (NO stacking) =====
function updateOtpBackUI() {
  if (!otpBackLink) return;

  if (otpFrom === "signup") {
    otpBackLink.textContent = "Back to Signup";
    otpBackLink.onclick = () => switchToSignup();
    return;
  }

  if (otpFrom === "forgot") {
    otpBackLink.textContent = "Back to Forgot Password";
    otpBackLink.onclick = () => showStep("forgotpassword-verify");
    return;
  }

  if (otpFrom === "inactive") {
    otpBackLink.textContent = "Back";
    otpBackLink.onclick = () => showStep("inactive-verify-step");
    return;
  }

  // fallback
  otpBackLink.textContent = "Back";
  otpBackLink.onclick = backToLogin;
}

// ===== Show OTP Form (reused) =====
function showOTPForm(purpose, userData = {}) {
  otpFrom = purpose;
  if (otpForm) otpForm.dataset.purpose = purpose;

  hideAllAuthScreens();

  if (otpForm) otpForm.classList.add("active");

  clearOtpUI();
  if (otpInputFields[0]) otpInputFields[0].focus();

  // Update message
  let displayText = "";

  if (userData?.phoneMasked) {
    displayText = userData.phoneMasked;
  } else if (userData?.phone) {
    const phone10 = phoneForDB(userData.phone);
    displayText = `+63 ******${phone10.slice(-4)}`;
  }

  if (otpMessage) {
    otpMessage.innerHTML = `Check your phone. An OTP has been sent to <strong>${displayText || "+63 •••••• XXXX"}</strong>`;
  } else {
    const strong = otpForm?.querySelector(".otp-text strong");
    if (strong) strong.textContent = displayText || "+63 •••••• XXXX";
  }

  updateOtpBackUI();

  // Auto-send OTP only if we already know recipient (inactive may not yet)
  if (userData?.phone) {
    const phone10 = phoneForDB(userData.phone);
    otpRecipient = phoneForOTP(phone10); // 0XXXXXXXXXX
    sendOTP(otpRecipient, purpose);
    startResendCountdown(purpose, 120);
  } else if (userData?.phone10) {
    otpRecipient = phoneForOTP(userData.phone10);
    sendOTP(otpRecipient, purpose);
    startResendCountdown(purpose, 120);
  }
}

// ===== Countdown (single implementation) =====
function startResendCountdown(purpose, seconds = 120) {
  if (!resendOTPBtn || !resendTimer) return;

  if (resendIntervalByPurpose[purpose]) {
    clearInterval(resendIntervalByPurpose[purpose]);
    resendIntervalByPurpose[purpose] = null;
  }

  resendCountdownByPurpose[purpose] = seconds;

  resendOTPBtn.style.pointerEvents = "none";
  resendOTPBtn.style.opacity = 0.5;
  resendTimer.textContent = `(${seconds}s)`;
  resendOTPBtn.textContent = "Resend OTP";

  resendIntervalByPurpose[purpose] = setInterval(() => {
    resendCountdownByPurpose[purpose]--;

    const left = resendCountdownByPurpose[purpose];
    resendTimer.textContent = left > 0 ? `(${left}s)` : "";

    if (left <= 0) {
      clearInterval(resendIntervalByPurpose[purpose]);
      resendIntervalByPurpose[purpose] = null;
      resendCountdownByPurpose[purpose] = 0;

      resendOTPBtn.style.pointerEvents = "auto";
      resendOTPBtn.style.opacity = 1;
      resendTimer.textContent = "";
      resendOTPBtn.textContent = "Resend OTP";
    }
  }, 1000);
}

// ===== Send OTP (generate_otp.php -> send_otp.php) =====
async function sendOTP(recipient, purpose, reuse = false) {
  try {
    let otpCode;

    if (reuse && currentOTPByPurpose[purpose]) {
      otpCode = currentOTPByPurpose[purpose];
    } else {
      const genForm = new FormData();
      genForm.append("recipient", recipient); // 0XXXXXXXXXX
      genForm.append("purpose", purpose);

      const genRes = await fetch("../PhpFiles/OTPHandlers/generate_otp.php", {
        method: "POST",
        body: genForm,
      });

      const genText = await genRes.text();
      console.log("GENERATE OTP RAW:", genText);

      let genData;
      try {
        genData = JSON.parse(genText);
      } catch {
        throw new Error("Invalid JSON from generate_otp.php");
      }

      if (!genData.success || !genData.otp_code) {
        throw new Error(genData.error || "OTP generation failed");
      }

      otpCode = genData.otp_code;
      currentOTPByPurpose[purpose] = otpCode;
    }

    const sendRes = await fetch("../PhpFiles/OTPHandlers/send_otp.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({ recipient, otp_code: otpCode }),
    });

    const sendText = await sendRes.text();
    console.log("SEND OTP RAW:", sendText);

    let sendData;
    try {
      sendData = JSON.parse(sendText);
    } catch {
      throw new Error("Invalid JSON from send_otp.php");
    }

    if (!sendData.success) throw new Error(sendData.error || "OTP sending failed");
  } catch (err) {
    console.error("Error sending OTP:", err.message);
    showOtpError("Unable to send OTP. Please try again later.");
  }
}

// ===== Resend OTP (single listener) =====
if (resendOTPBtn) {
  resendOTPBtn.addEventListener("click", async () => {
    if (!otpFrom) return;
    if (resendCountdownByPurpose[otpFrom] > 0) return;
    if (!otpRecipient) {
      showOtpError("Unable to resend OTP. Please go back and try again.");
      return;
    }

    try {
      await sendOTP(otpRecipient, otpFrom, true);
      resendOTPBtn.textContent = "OTP Resent!";
      startResendCountdown(otpFrom, 120);
    } catch (err) {
      console.error("Resend OTP Error:", err);
      showOtpError("Unable to resend OTP. Please try again later.");
    }
  });
}

// ===== Forgot Password =====
function showForgotError(message, highlightFields = []) {
  setErrorBox(forgotFormErrors, message);

  [forgotPhoneInput, forgotEmailInput].forEach((input) => {
    if (input) input.style.border = "";
  });

  highlightFields.forEach((input) => {
    if (!input) return;
    input.style.border = "2px solid red";
    input.focus();
  });
}

if (forgotLink) {
  forgotLink.addEventListener("click", (e) => {
    e.preventDefault();
    showStep("forgotpassword-verify");
  });
}

if (forgotContinueBtn) {
  forgotContinueBtn.addEventListener("click", async () => {
    hideErrorBox(forgotFormErrors);

    if (forgotEmailInput) forgotEmailInput.style.border = "";
    if (forgotPhoneInput) forgotPhoneInput.style.border = "";

    const phone = (forgotPhoneInput?.value || "").trim();
    const email = (forgotEmailInput?.value || "").trim();

    if (!phone || !email) {
      showForgotError("Email and phone number are required.", [forgotPhoneInput, forgotEmailInput]);
      return;
    }

    // ✅ FIXED REGEX (no double slashes)
    if (!/^9\d{9}$/.test(phone)) {
      showForgotError("Phone number must start with 9 and be exactly 10 digits.", [forgotPhoneInput]);
      return;
    }

    // ✅ FIXED REGEX (no double slashes)
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      showForgotError("Please enter a valid email address.", [forgotEmailInput]);
      return;
    }

    try {
      const formData = new FormData();
      formData.append("phone", phone);
      formData.append("email", email);

      const res = await fetch("../PhpFiles/Login/checkForgotPassword.php", {
        method: "POST",
        body: formData,
      });

      const data = await res.json();

      if (!data.success) {
        showForgotError(data.error, [forgotPhoneInput, forgotEmailInput]);
        return;
      }

      showOTPForm("forgot", { phone });
    } catch (err) {
      console.error(err);
      showForgotError("Unable to verify account. Please try again later.", [forgotPhoneInput, forgotEmailInput]);
    }
  });
}

// ===== Signup Validation =====
const validateForm = (formType = "signup") => {
  resetErrors(formType);

  let firstError = "";
  let errorField = null;
  let clearBothPasswords = false;

  if (formType === "signup") {
    const phone = (phoneInput?.value || "").trim();
    const password = passwordInput?.value || "";
    const confirmPassword = confirmPasswordInput?.value || "";

    // ✅ FIXED REGEX (no double slashes)
    if (!/^9\d{9}$/.test(phone)) {
      firstError = "Phone number must start with 9 and be exactly 10 digits.";
      errorField = phoneInput;
    } else if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/.test(password)) {
      firstError = "Password must be at least 8 characters with uppercase, lowercase, number, and special character.";
      errorField = passwordInput;
      clearBothPasswords = true;
    } else if (password !== confirmPassword || !confirmPassword) {
      firstError = "Passwords do not match.";
      errorField = confirmPasswordInput;
    }
  }

  if (firstError) {
    showError(firstError, formType, errorField, clearBothPasswords);
    return false;
  }

  return true;
};

// ===== Signup → OTP Step =====
if (createAccountBtn) {
  createAccountBtn.addEventListener("click", async () => {
    if (!validateForm("signup")) return;

    const phone = (phoneInput?.value || "").trim();

    try {
      const formData = new FormData();
      formData.append("phone", phone);

      const res = await fetch("../PhpFiles/Login/checkExistingUser.php", {
        method: "POST",
        body: formData,
      });

      const data = await res.json();

      if (!data.success) {
        showError(data.error, "signup");
        return;
      }

      if (data.phoneExists) {
        showError("Phone Number is already registered.", "signup", phoneInput);
        return;
      }

      tempSignupData = {
        phone,
        email: (emailInput?.value || "").trim(),
        password: passwordInput?.value || "",
      };

      showOTPForm("signup", { phone }); // auto send OTP
    } catch (err) {
      console.error("Fetch error:", err);
      showError("Unable to verify account. Please try again later.", "signup");
    }
  });
}

// ===== INACTIVE: Continue → server-side lookup → show OTP form (auto send) =====
if (inactiveContinueBtn) {
  inactiveContinueBtn.addEventListener("click", async () => {
    try {
      const res = await fetch("../PhpFiles/OTPHandlers/request_inactive_otp.php", { method: "POST" });
      const data = await res.json();

      if (!data.success) {
        const div = document.getElementById("inactiveVerifyErrors");
        setErrorBox(div, data.error || "Unable to send OTP. Please try again.");
        return;
      }

      inactiveSession.phone10 = data.phone10 || inactiveSession.phone10;
      inactiveSession.phoneMasked = data.phone_masked || inactiveSession.phoneMasked;

      showOTPForm("inactive", {
        phone10: inactiveSession.phone10,
        phoneMasked: inactiveSession.phoneMasked,
      });
    } catch (err) {
      console.error(err);
      const div = document.getElementById("inactiveVerifyErrors");
      setErrorBox(div, "Network error. Please try again.");
    }
  });
}

// ===== Verify OTP (signup/forgot/inactive) =====
if (verifyOTPBtn) {
  verifyOTPBtn.addEventListener("click", async () => {
    let otp = "";
    otpInputFields.forEach((input) => (otp += (input.value || "").trim()));

    if (otp.length !== 6) {
      showOtpError("Please enter a 6-digit OTP");
      return;
    }

    try {
      let recipientPhone10 = "";

      if (otpFrom === "forgot") {
        recipientPhone10 = phoneForDB((forgotPhoneInput?.value || "").trim());
      } else if (otpFrom === "signup") {
        if (!tempSignupData) {
          showOtpError("Session expired. Please start signup again.");
          switchToSignup();
          return;
        }
        recipientPhone10 = phoneForDB(tempSignupData.phone);
      } else if (otpFrom === "inactive") {
        recipientPhone10 = phoneForDB(inactiveSession.phone10);
        if (!recipientPhone10) {
          showOtpError("Session expired. Please go back and try again.");
          showStep("inactive-verify-step");
          return;
        }
      } else {
        showOtpError("Invalid OTP flow. Please go back and try again.");
        backToLogin();
        return;
      }

      const formData = new FormData();
      formData.append("recipient", recipientPhone10); // 10 digits
      formData.append("otp", otp);
      formData.append("purpose", otpFrom);

      const res = await fetch("../PhpFiles/OTPHandlers/verify_otp.php", {
        method: "POST",
        body: formData,
      });

      const data = await res.json();

      if (!data.success) {
        showOtpError(data.error || "Incorrect OTP");
        otpInputFields.forEach((input) => (input.value = ""));
        if (otpInputFields[0]) otpInputFields[0].focus();
        return;
      }

      clearOtpUI();

      // ----- INACTIVE VERIFIED: activate + create session, then modal
      if (otpFrom === "inactive") {
        // ✅ IMPORTANT: restore correct filename casing for Hostinger
        const vRes = await fetch("../PhpFiles/Login/UserInactivity_update.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ ok: true }),
        });

        const vData = await vRes.json();

        if (!vData.success) {
          showOtpError(vData.error || "Unable to verify account. Please try again.");
          return;
        }

        inactiveSession.redirect = vData.redirect || inactiveSession.redirect;

        UniversalModal.open({
          title: "Account Verified",
          message: "Account verification successful.",
          buttons: [
            {
              label: "Continue",
              class: "btn btn-success w-100",
              onClick: () => (window.location.href = inactiveSession.redirect),
            },
          ],
        });

        return;
      }

      // ----- SIGNUP VERIFIED → register
      if (otpFrom === "signup") {
        if (otpForm) otpForm.classList.remove("active");

        const signupData = new FormData();
        signupData.append("RPhoneNumber", phoneForDB(tempSignupData.phone));
        signupData.append("REmail", tempSignupData.email);
        signupData.append("RPassword", tempSignupData.password);

        const signupRes = await fetch("../PhpFiles/Login/RegisterAccount.php", {
          method: "POST",
          body: signupData,
        });

        const signupResult = await signupRes.json();

        if (signupResult.success) {
          UniversalModal.open({
            title: "Account Created!",
            message: "Your account has been successfully created.",
            buttons: [
              {
                label: "Continue",
                class: "btn btn-primary w-100",
                onClick: () => (window.location.href = signupResult.redirect),
              },
            ],
          });
        } else {
          showError(signupResult.error || "Unable to create account.", "signup");
          switchToSignup();
        }

        return;
      }

      // ----- FORGOT VERIFIED → reset password step
      if (otpFrom === "forgot") {
        verifiedResetEmail = (forgotEmailInput?.value || "").trim();
        verifiedResetPhone = (forgotPhoneInput?.value || "").trim();

        if (otpForm) otpForm.classList.remove("active");
        showStep("reset-password-step");
      }
    } catch (err) {
      console.error(err);
      showOtpError("Unable to verify OTP. Please try again later.");
    }
  });
}

// ===== Reset Password Submit =====
function showResetPasswordError(message, field = null, clearBoth = false) {
  if (!resetPasswordErrors) return;
  setErrorBox(resetPasswordErrors, message);

  if (clearBoth) {
    if (newPasswordInput) newPasswordInput.value = "";
    if (confirmNewPasswordInput) confirmNewPasswordInput.value = "";
    if (newPasswordInput) newPasswordInput.focus();
    return;
  }

  if (field) {
    field.style.border = "2px solid red";
    field.value = "";
    field.focus();
  }
}

if (submitNewPasswordBtn) {
  submitNewPasswordBtn.addEventListener("click", async () => {
    hideErrorBox(resetPasswordErrors);

    if (newPasswordInput) newPasswordInput.style.border = "";
    if (confirmNewPasswordInput) confirmNewPasswordInput.style.border = "";

    const password = (newPasswordInput?.value || "").trim();
    const confirm = (confirmNewPasswordInput?.value || "").trim();

    if (!verifiedResetEmail || !verifiedResetPhone) {
      showResetPasswordError("Session expired. Please request OTP again.");
      backToLogin();
      return;
    }

    if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/.test(password)) {
      showResetPasswordError(
        "Password must be at least 8 characters with uppercase, lowercase, number, and special character.",
        null,
        true
      );
      return;
    }

    if (password !== confirm) {
      showResetPasswordError("Passwords do not match.", confirmNewPasswordInput);
      return;
    }

    try {
      const formData = new FormData();
      formData.append("email", verifiedResetEmail);
      formData.append("phone", phoneForDB(verifiedResetPhone));
      formData.append("newPassword", password);

      const res = await fetch("../PhpFiles/Login/resetPassword.php", {
        method: "POST",
        body: formData,
      });

      const data = await res.json();

      if (!data.success) {
        showResetPasswordError(data.error);
        return;
      }

      verifiedResetEmail = "";
      verifiedResetPhone = "";
      if (newPasswordInput) newPasswordInput.value = "";
      if (confirmNewPasswordInput) confirmNewPasswordInput.value = "";

      UniversalModal.open({
        title: "Password Reset",
        message: "Your password has been successfully reset. You can now log in.",
        buttons: [
          {
            label: "Login",
            class: "btn btn-primary w-100",
            onClick: backToLogin,
          },
        ],
      });
    } catch (err) {
      console.error(err);
      showResetPasswordError("Unable to reset password. Please try again later.");
    }
  });
}

// ===== Login Submit (UPDATED to handle INACTIVE status) =====
if (loginForm) {
  loginForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    resetErrors("login");

    const username = (loginUserField?.value || "").trim();
    const password = (loginPasswordField?.value || "").trim();

    if (!username) {
      return showError("Please enter your phone or email.", "login", loginUserField);
    }
    if (!password) {
      return showError("Please enter your password.", "login", loginPasswordField);
    }

    try {
      const formData = new FormData();
      formData.append("user", username);
      formData.append("loginPassword", password);

      const res = await fetch("../PhpFiles/Login/login.php", {
        method: "POST",
        body: formData,
      });

      const raw = await res.text();
      console.log("SERVER RAW RESPONSE:", raw);

      let data = null;
      try {
        data = JSON.parse(raw);
      } catch {
        data = null;
      }

      if (!res.ok) {
        const msg = data?.error ? data.error : `Unable to login. HTTP ${res.status}`;
        showError(msg, "login", loginPasswordField);
        return;
      }

      if (!data || !data.success) {
        return showError(data?.error || "Unable to login.", "login", loginPasswordField);
      }

      // ✅ Inactive flow
      if (data.status === "inactive") {
        otpFrom = "inactive";
        inactiveSession.userId = data.user_id || null;
        inactiveSession.phoneMasked = data.phone_masked || "+63 •••••• XXXX";
        inactiveSession.redirect = data.redirect || "../PhpFiles/Login/unifiedProfileCheck.php";

        showStep("inactive-verify-step");
        return;
      }

      // Active user
      window.location.href = data.redirect;
    } catch (err) {
      console.error("LOGIN ERROR:", err);
      showError("Unable to login. Please try again later.", "login");
    }
  });
}

// ===== Expose (HTML uses onclick="...") =====
window.switchToSignup = switchToSignup;
window.switchToLogin = switchToLogin;
window.backToLogin = backToLogin;
