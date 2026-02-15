document.addEventListener("DOMContentLoaded", () => {
  const link = document.getElementById("changeEmailLink");
  const modalEl = document.getElementById("changeEmailModal");
  if (!link || !modalEl || !window.bootstrap?.Modal) return;

  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

  const emailVerified = !!window.RESIDENT_PROFILE_EMAIL_VERIFIED;

  const errorEl = document.getElementById("cemError");
  const btnViaPhone = document.getElementById("btnCemVerifyViaPhone");
  const btnViaEmail = document.getElementById("btnCemVerifyViaEmail");

  const stepChoose = modalEl.querySelector('.cem-step[data-step="choose"]');
  const stepOtpOld = modalEl.querySelector('.cem-step[data-step="otp_old"]');
  const stepNewEmail = modalEl.querySelector('.cem-step[data-step="new_email"]');
  const stepSent = modalEl.querySelector('.cem-step[data-step="sent"]');

  const otpOldMsg = document.getElementById("cemOtpOldMessage");
  const sentMsg = document.getElementById("cemSentMessage");

  const otpOldInputsWrap = document.getElementById("cemOtpOldInputs");
  const resendOld = document.getElementById("cemResendOldOtp");
  const resendOldTimer = document.getElementById("cemResendOldTimer");

  const btnVerifyOld = document.getElementById("btnCemVerifyOldOtp");
  const backToChoose = document.getElementById("cemBackToChoose");
  const backToOtpOld = document.getElementById("cemBackToOtpOld");

  const newEmailEl = document.getElementById("cemNewEmail");
  const btnSendVerification = document.getElementById("btnCemSendVerification");

  let oldMethod = "phone"; // "phone" | "email"
  let resendOldSeconds = 0;
  let resendOldInterval = null;

  const setError = (msg) => {
    if (!errorEl) return;
    if (!msg) {
      errorEl.classList.add("d-none");
      errorEl.textContent = "";
      return;
    }
    errorEl.textContent = msg;
    errorEl.classList.remove("d-none");
  };

  const showStep = (key) => {
    [stepChoose, stepOtpOld, stepNewEmail, stepSent].forEach((el) => el && el.classList.add("d-none"));
    setError("");
    const map = { choose: stepChoose, otp_old: stepOtpOld, new_email: stepNewEmail, sent: stepSent };
    map[key]?.classList.remove("d-none");
  };

  const clearFieldError = (el) => el && (el.style.border = "");
  const markFieldError = (el) => el && (el.style.border = "2px solid red");

  const clearOtpErrors = (wrap) => {
    if (!wrap) return;
    wrap.querySelectorAll("input").forEach((i) => (i.style.border = ""));
  };
  const markOtpErrors = (wrap) => {
    if (!wrap) return;
    wrap.querySelectorAll("input").forEach((i) => (i.style.border = "2px solid red"));
  };

  const resetOtpInputs = (wrap) => {
    const inputs = wrap ? Array.from(wrap.querySelectorAll("input")) : [];
    inputs.forEach((i) => (i.value = ""));
    inputs[0]?.focus();
  };

  const readOtp = (wrap) => {
    const inputs = wrap ? Array.from(wrap.querySelectorAll("input")) : [];
    const code = inputs.map((i) => (i.value || "").replace(/\D/g, "")).join("");
    return code.length === 6 ? code : "";
  };

  const wireOtpInputs = (wrap) => {
    const inputs = wrap ? Array.from(wrap.querySelectorAll("input")) : [];
    inputs.forEach((input, idx) => {
      input.addEventListener("input", () => {
        input.value = (input.value || "").replace(/\D/g, "").slice(0, 1);
        input.style.border = "";
        setError("");
        if (input.value && inputs[idx + 1]) inputs[idx + 1].focus();
      });
      input.addEventListener("keydown", (e) => {
        if (e.key === "Backspace" && !input.value && inputs[idx - 1]) inputs[idx - 1].focus();
      });
      input.addEventListener("paste", (e) => {
        e.preventDefault();
        const text = (e.clipboardData?.getData("text") || "").replace(/\D/g, "").slice(0, 6);
        if (!text) return;
        inputs.forEach((i) => {
          i.value = "";
          i.style.border = "";
        });
        setError("");
        text.split("").forEach((ch, i) => {
          if (inputs[i]) inputs[i].value = ch;
        });
        inputs[Math.min(text.length, 6) - 1]?.focus();
      });
    });
  };

  const startResendCountdown = (seconds) => {
    if (resendOldInterval) clearInterval(resendOldInterval);
    resendOldSeconds = seconds;

    const tick = () => {
      const left = resendOldSeconds;
      if (resendOld) {
        resendOld.style.pointerEvents = left > 0 ? "none" : "auto";
        resendOld.style.opacity = left > 0 ? "0.5" : "1";
      }
      if (resendOldTimer) resendOldTimer.textContent = left > 0 ? `(${left}s)` : "";
      if (left <= 0) return;
      resendOldSeconds -= 1;
    };

    tick();
    resendOldInterval = setInterval(() => {
      tick();
      if (resendOldSeconds <= 0) {
        clearInterval(resendOldInterval);
        resendOldInterval = null;
      }
    }, 1000);
  };

  const postJson = async (url, body) => {
    const res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body || {}),
    });
    const text = await res.text();
    let data = null;
    try {
      data = JSON.parse(text);
    } catch {
      console.error("Non-JSON response:", text.slice(0, 300));
    }
    return { res, data };
  };

  const requestOldOtp = async (method) => {
    const { res, data } = await postJson("../PhpFiles/Login/changeEmailRequestOtp.php", { method });
    if (!res.ok || !data?.success) throw new Error(data?.message || "Unable to send OTP. Please try again.");
    return data;
  };

  const verifyOldOtp = async (otp) => {
    const { res, data } = await postJson("../PhpFiles/Login/changeEmailVerifyOtp.php", { method: oldMethod, otp });
    if (!res.ok || !data?.success) throw new Error(data?.message || "OTP invalid or expired.");
    return data;
  };

  const sendVerification = async (newEmail) => {
    const { res, data } = await postJson("../PhpFiles/Login/changeEmailSendVerification.php", { new_email: newEmail });
    if (!res.ok || !data?.success) throw new Error(data?.message || "Unable to send verification email.");
    return data;
  };

  // Same basic email check used elsewhere in the project (no double-escaping).
  const isValidEmail = (email) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email || "").trim());

  const beginOldOtpFlow = async (method) => {
    oldMethod = method;
    setError("");
    showStep("otp_old");
    clearOtpErrors(otpOldInputsWrap);
    resetOtpInputs(otpOldInputsWrap);

    const data = await requestOldOtp(method);
    if (otpOldMsg) {
      otpOldMsg.innerHTML =
        method === "email"
          ? `Check your email. An OTP has been sent to <strong>${data.masked || "••••@••••"}</strong>`
          : `Check your phone. An OTP has been sent to <strong>${data.masked || "+63 •••••• XXXX"}</strong>`;
    }
    startResendCountdown(120);
  };

  // wire otp boxes + new email input
  wireOtpInputs(otpOldInputsWrap);
  newEmailEl?.addEventListener("input", () => {
    clearFieldError(newEmailEl);
    setError("");
  });

  btnViaPhone?.addEventListener("click", async () => {
    try {
      btnViaPhone.disabled = true;
      btnViaEmail && (btnViaEmail.disabled = true);
      await beginOldOtpFlow("phone");
    } catch (e) {
      setError(e?.message || "Unable to send OTP.");
      showStep("choose");
    } finally {
      btnViaPhone.disabled = false;
      btnViaEmail && (btnViaEmail.disabled = false);
    }
  });

  btnViaEmail?.addEventListener("click", async () => {
    try {
      btnViaPhone && (btnViaPhone.disabled = true);
      btnViaEmail.disabled = true;
      await beginOldOtpFlow("email");
    } catch (e) {
      setError(e?.message || "Unable to send OTP.");
      showStep("choose");
    } finally {
      btnViaPhone && (btnViaPhone.disabled = false);
      btnViaEmail.disabled = false;
    }
  });

  resendOld?.addEventListener("click", async () => {
    try {
      setError("");
      await beginOldOtpFlow(oldMethod);
    } catch (e) {
      setError(e?.message || "Unable to resend OTP.");
    }
  });

  btnVerifyOld?.addEventListener("click", async () => {
    try {
      setError("");
      clearOtpErrors(otpOldInputsWrap);
      const otp = readOtp(otpOldInputsWrap);
      if (!otp) {
        setError("Please enter the 6-digit OTP.");
        markOtpErrors(otpOldInputsWrap);
        return;
      }
      btnVerifyOld.disabled = true;
      await verifyOldOtp(otp);
      showStep("new_email");
      if (newEmailEl) {
        newEmailEl.value = "";
        clearFieldError(newEmailEl);
        newEmailEl.focus();
      }
    } catch (e) {
      setError(e?.message || "OTP invalid or expired.");
      markOtpErrors(otpOldInputsWrap);
      resetOtpInputs(otpOldInputsWrap);
    } finally {
      btnVerifyOld.disabled = false;
    }
  });

  btnSendVerification?.addEventListener("click", async () => {
    try {
      setError("");
      clearFieldError(newEmailEl);
      const v = String(newEmailEl?.value || "").trim();
      if (!v || !isValidEmail(v)) {
        setError("Please enter a valid email address.");
        markFieldError(newEmailEl);
        return;
      }

      btnSendVerification.disabled = true;
      const data = await sendVerification(v);
      if (sentMsg) {
        sentMsg.innerHTML =
          (data.messageHtml ||
            "Check your email and click the Verify Email button. <br><b>This link will expire in 15 minutes.</b>");
      }
      showStep("sent");
    } catch (e) {
      setError(e?.message || "Unable to send verification email.");
    } finally {
      btnSendVerification.disabled = false;
    }
  });

  backToChoose?.addEventListener("click", () => showStep("choose"));
  backToOtpOld?.addEventListener("click", () => showStep("otp_old"));

  link.addEventListener("click", (e) => {
    e.preventDefault();
    setError("");
    showStep("choose");
    if (btnViaEmail) btnViaEmail.classList.toggle("d-none", !emailVerified);
    modal.show();
  });

  modalEl.addEventListener("hidden.bs.modal", () => {
    setError("");
    showStep("choose");
    if (newEmailEl) newEmailEl.value = "";
    if (resendOldInterval) clearInterval(resendOldInterval);
    resendOldInterval = null;
    resendOldSeconds = 0;
    if (resendOldTimer) resendOldTimer.textContent = "";
  });
});
