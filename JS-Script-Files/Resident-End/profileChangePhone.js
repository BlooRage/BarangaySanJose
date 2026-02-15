document.addEventListener("DOMContentLoaded", () => {
  const link = document.getElementById("changePhoneLink");
  const modalEl = document.getElementById("changePhoneModal");
  if (!link || !modalEl || !window.bootstrap?.Modal) return;

  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

  const errorEl = document.getElementById("cpnError");
  const btnViaPhone = document.getElementById("btnCpnVerifyViaPhone");
  const btnViaEmail = document.getElementById("btnCpnVerifyViaEmail");

  const stepChoose = modalEl.querySelector('.cpn-step[data-step="choose"]');
  const stepOtpOld = modalEl.querySelector('.cpn-step[data-step="otp_old"]');
  const stepNewPhone = modalEl.querySelector('.cpn-step[data-step="new_phone"]');
  const stepOtpNew = modalEl.querySelector('.cpn-step[data-step="otp_new"]');

  const otpOldMsg = document.getElementById("cpnOtpOldMessage");
  const otpNewMsg = document.getElementById("cpnOtpNewMessage");

  const otpOldInputsWrap = document.getElementById("cpnOtpOldInputs");
  const otpNewInputsWrap = document.getElementById("cpnOtpNewInputs");

  const resendOld = document.getElementById("cpnResendOldOtp");
  const resendOldTimer = document.getElementById("cpnResendOldTimer");
  const resendNew = document.getElementById("cpnResendNewOtp");
  const resendNewTimer = document.getElementById("cpnResendNewTimer");

  const btnVerifyOld = document.getElementById("btnCpnVerifyOldOtp");
  const btnSendNew = document.getElementById("btnCpnSendNewOtp");
  const btnVerifyNew = document.getElementById("btnCpnVerifyNewOtp");

  const backToChoose = document.getElementById("cpnBackToChoose");
  const backToOtpOld = document.getElementById("cpnBackToOtpOld");
  const backToNewPhone = document.getElementById("cpnBackToNewPhone");

  const newPhoneEl = document.getElementById("cpnNewPhone");

  // server-known value from PHP (fallback to false if missing)
  const emailVerified = !!window.RESIDENT_PROFILE_EMAIL_VERIFIED;

  let oldMethod = "phone"; // "phone" | "email"
  let newPhone10 = "";

  let resendOldSeconds = 0;
  let resendOldInterval = null;
  let resendNewSeconds = 0;
  let resendNewInterval = null;

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

  const clearFieldError = (el) => {
    if (!el) return;
    el.style.border = "";
  };

  const markFieldError = (el) => {
    if (!el) return;
    el.style.border = "2px solid red";
  };

  const clearOtpErrors = (wrap) => {
    if (!wrap) return;
    wrap.querySelectorAll("input").forEach((i) => {
      i.style.border = "";
    });
  };

  const markOtpErrors = (wrap) => {
    if (!wrap) return;
    wrap.querySelectorAll("input").forEach((i) => {
      i.style.border = "2px solid red";
    });
  };

  const showStep = (key) => {
    [stepChoose, stepOtpOld, stepNewPhone, stepOtpNew].forEach((el) => el && el.classList.add("d-none"));
    setError("");
    const map = { choose: stepChoose, otp_old: stepOtpOld, new_phone: stepNewPhone, otp_new: stepOtpNew };
    map[key]?.classList.remove("d-none");
  };

  const sanitizePhone10 = (v) => String(v || "").replace(/\D/g, "").slice(0, 10);
  const isValidPhone10 = (v) => /^9\d{9}$/.test(v);

  const maskPhone10 = (v) => {
    const p = String(v || "");
    if (!/^9\d{9}$/.test(p)) return "+63 •••••• XXXX";
    return `+63 •••••• ${p.slice(-4)}`;
  };

  const maskEmail = (email) => {
    const e = String(email || "").trim();
    const at = e.indexOf("@");
    if (at <= 1) return "••••@••••";
    return `${e.slice(0, 2)}••••${e.slice(at)}`;
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
        // Clear validation visuals as the user edits
        input.style.border = "";
        setError("");
        if (input.value && inputs[idx + 1]) inputs[idx + 1].focus();
      });
      input.addEventListener("keydown", (e) => {
        if (e.key === "Backspace" && !input.value && inputs[idx - 1]) {
          inputs[idx - 1].focus();
        }
      });
      input.addEventListener("paste", (e) => {
        e.preventDefault();
        const text = (e.clipboardData?.getData("text") || "").replace(/\D/g, "").slice(0, 6);
        if (!text) return;
        inputs.forEach((i) => (i.style.border = ""));
        setError("");
        text.split("").forEach((ch, i) => {
          if (inputs[i]) inputs[i].value = ch;
        });
        inputs[Math.min(text.length, 6) - 1]?.focus();
      });
    });
  };

  const startResendCountdown = (kind, seconds) => {
    const isOld = kind === "old";
    const linkEl = isOld ? resendOld : resendNew;
    const timerEl = isOld ? resendOldTimer : resendNewTimer;

    if (isOld) {
      if (resendOldInterval) clearInterval(resendOldInterval);
      resendOldSeconds = seconds;
    } else {
      if (resendNewInterval) clearInterval(resendNewInterval);
      resendNewSeconds = seconds;
    }

    const tick = () => {
      const left = isOld ? resendOldSeconds : resendNewSeconds;
      if (linkEl) {
        linkEl.style.pointerEvents = left > 0 ? "none" : "auto";
        linkEl.style.opacity = left > 0 ? "0.5" : "1";
      }
      if (timerEl) timerEl.textContent = left > 0 ? `(${left}s)` : "";
      if (left <= 0) return;
      if (isOld) resendOldSeconds -= 1;
      else resendNewSeconds -= 1;
    };

    tick();
    const id = setInterval(() => {
      tick();
      const left = isOld ? resendOldSeconds : resendNewSeconds;
      if (left <= 0) clearInterval(id);
    }, 1000);

    if (isOld) resendOldInterval = id;
    else resendNewInterval = id;
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
    const { res, data } = await postJson("../PhpFiles/Login/changePhoneRequestOtp.php", { method });
    if (!res.ok || !data?.success) {
      throw new Error(data?.message || "Unable to send OTP. Please try again.");
    }
    return data;
  };

  const verifyOldOtp = async (otp) => {
    const { res, data } = await postJson("../PhpFiles/Login/changePhoneVerifyOtp.php", { method: oldMethod, otp });
    if (!res.ok || !data?.success) {
      throw new Error(data?.message || "OTP invalid or expired.");
    }
    return data;
  };

  const requestNewOtp = async (phone10) => {
    const { res, data } = await postJson("../PhpFiles/Login/changePhoneRequestNewOtp.php", { new_phone: phone10 });
    if (!res.ok || !data?.success) {
      throw new Error(data?.message || "Unable to send OTP. Please try again.");
    }
    return data;
  };

  const verifyNewOtp = async (otp) => {
    const { res, data } = await postJson("../PhpFiles/Login/changePhoneVerifyNewOtp.php", { new_phone: newPhone10, otp });
    if (!res.ok || !data?.success) {
      throw new Error(data?.message || "OTP invalid or expired.");
    }
    return data;
  };

  // Wire OTP boxes
  wireOtpInputs(otpOldInputsWrap);
  wireOtpInputs(otpNewInputsWrap);

  // Phone input sanitizer
  newPhoneEl?.addEventListener("input", () => {
    newPhoneEl.value = sanitizePhone10(newPhoneEl.value);
    clearFieldError(newPhoneEl);
    setError("");
  });

  const beginOldOtpFlow = async (method) => {
    oldMethod = method;
    setError("");
    showStep("otp_old");
    clearOtpErrors(otpOldInputsWrap);
    resetOtpInputs(otpOldInputsWrap);

    const data = await requestOldOtp(method);
    if (otpOldMsg) {
      if (method === "email") {
        otpOldMsg.innerHTML = `Check your email. An OTP has been sent to <strong>${maskEmail(data.masked || "")}</strong>`;
      } else {
        otpOldMsg.innerHTML = `Check your phone. An OTP has been sent to <strong>${data.masked || "+63 •••••• XXXX"}</strong>`;
      }
    }
    startResendCountdown("old", 120);
  };

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
      showStep("new_phone");
      if (newPhoneEl) {
        newPhoneEl.value = "";
        clearFieldError(newPhoneEl);
        newPhoneEl.focus();
      }
    } catch (e) {
      setError(e?.message || "OTP invalid or expired.");
      markOtpErrors(otpOldInputsWrap);
      resetOtpInputs(otpOldInputsWrap);
    } finally {
      btnVerifyOld.disabled = false;
    }
  });

  btnSendNew?.addEventListener("click", async () => {
    try {
      setError("");
      clearFieldError(newPhoneEl);
      const v = sanitizePhone10(newPhoneEl?.value || "");
      if (!isValidPhone10(v)) {
        setError("Phone number must start with 9 and be exactly 10 digits.");
        markFieldError(newPhoneEl);
        return;
      }
      btnSendNew.disabled = true;
      const data = await requestNewOtp(v);
      newPhone10 = v;
      if (otpNewMsg) {
        otpNewMsg.innerHTML = `Check your phone. An OTP has been sent to <strong>${data.masked || maskPhone10(v)}</strong>`;
      }
      showStep("otp_new");
      clearOtpErrors(otpNewInputsWrap);
      resetOtpInputs(otpNewInputsWrap);
      startResendCountdown("new", 120);
    } catch (e) {
      setError(e?.message || "Unable to send OTP.");
    } finally {
      btnSendNew.disabled = false;
    }
  });

  resendNew?.addEventListener("click", async () => {
    try {
      setError("");
      if (!newPhone10) {
        showStep("new_phone");
        return;
      }
      await requestNewOtp(newPhone10);
      resetOtpInputs(otpNewInputsWrap);
      startResendCountdown("new", 120);
    } catch (e) {
      setError(e?.message || "Unable to resend OTP.");
    }
  });

  btnVerifyNew?.addEventListener("click", async () => {
    try {
      setError("");
      clearOtpErrors(otpNewInputsWrap);
      const otp = readOtp(otpNewInputsWrap);
      if (!otp) {
        setError("Please enter the 6-digit OTP.");
        markOtpErrors(otpNewInputsWrap);
        return;
      }
      btnVerifyNew.disabled = true;
      const data = await verifyNewOtp(otp);
      modal.hide();
      window.UniversalModal?.open({
        title: "Success",
        message: data.message || "Mobile number changed successfully.",
        buttons: [{ label: "OK", class: "btn btn-success", onClick: () => window.location.reload() }],
      });
    } catch (e) {
      setError(e?.message || "OTP invalid or expired.");
      markOtpErrors(otpNewInputsWrap);
      resetOtpInputs(otpNewInputsWrap);
    } finally {
      btnVerifyNew.disabled = false;
    }
  });

  backToChoose?.addEventListener("click", () => {
    showStep("choose");
  });
  backToOtpOld?.addEventListener("click", () => {
    showStep("otp_old");
  });
  backToNewPhone?.addEventListener("click", () => {
    showStep("new_phone");
  });

  link.addEventListener("click", (e) => {
    e.preventDefault();
    setError("");
    showStep("choose");
    // Toggle email option based on verification status
    if (btnViaEmail) {
      btnViaEmail.classList.toggle("d-none", !emailVerified);
    }
    modal.show();
  });

  modalEl.addEventListener("hidden.bs.modal", () => {
    setError("");
    showStep("choose");
    if (newPhoneEl) newPhoneEl.value = "";
    newPhone10 = "";
    if (resendOldInterval) clearInterval(resendOldInterval);
    if (resendNewInterval) clearInterval(resendNewInterval);
    resendOldInterval = null;
    resendNewInterval = null;
    resendOldSeconds = 0;
    resendNewSeconds = 0;
    if (resendOldTimer) resendOldTimer.textContent = "";
    if (resendNewTimer) resendNewTimer.textContent = "";
  });
});
