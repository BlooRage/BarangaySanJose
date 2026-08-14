document.addEventListener("DOMContentLoaded", () => {
  const link = document.getElementById("changePasswordLink");
  const modalEl = document.getElementById("changePasswordModal");
  const confirmEl = document.getElementById("confirmChangePasswordModal");
  if (!link || !modalEl || !confirmEl || !window.bootstrap?.Modal) return;

  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  const confirmModal = bootstrap.Modal.getOrCreateInstance(confirmEl);

  const currentPwEl = document.getElementById("currentPassword");
  const newPwEl = document.getElementById("newPassword");
  const confirmPwEl = document.getElementById("confirmNewPassword");
  const errorEl = document.getElementById("changePasswordError");
  const openConfirmBtn = document.getElementById("btnOpenConfirmChangePassword");
  const confirmBtn = document.getElementById("btnConfirmChangePassword");

  const pwPolicy = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;

  const setErrorBox = (message) => {
    if (!errorEl) return;
    if (!message) {
      errorEl.classList.add("d-none");
      errorEl.textContent = "";
      return;
    }
    errorEl.textContent = message;
    errorEl.classList.remove("d-none");
  };

  const markInvalid = (el) => {
    if (!el) return;
    el.classList.add("is-invalid");
    el.style.border = "2px solid #dc3545";
  };

  const clearInvalid = (el) => {
    if (!el) return;
    el.classList.remove("is-invalid");
    el.style.border = "";
  };

  const clearAllInvalid = () => {
    [currentPwEl, newPwEl, confirmPwEl].forEach(clearInvalid);
  };

  const clearFields = () => {
    if (currentPwEl) currentPwEl.value = "";
    if (newPwEl) newPwEl.value = "";
    if (confirmPwEl) confirmPwEl.value = "";
    clearAllInvalid();
    setErrorBox("");
  };

  const showError = (message, field = null, clearNewAndConfirm = false) => {
    setErrorBox(message);
    if (clearNewAndConfirm) {
      if (newPwEl) newPwEl.value = "";
      if (confirmPwEl) confirmPwEl.value = "";
    }

    if (field) {
      markInvalid(field);
      field.focus?.();
      return;
    }
  };

  const validate = () => {
    const currentPw = (currentPwEl?.value || "").trim();
    const newPw = newPwEl?.value || "";
    const confirmPw = confirmPwEl?.value || "";

    if (!currentPw || !newPw || !confirmPw) {
      clearAllInvalid();
      if (!currentPw) markInvalid(currentPwEl);
      if (!newPw) markInvalid(newPwEl);
      if (!confirmPw) markInvalid(confirmPwEl);
      showError("Please fill in all required fields.");
      return false;
    }
    if (newPw !== confirmPw) {
      clearAllInvalid();
      markInvalid(confirmPwEl);
      showError("Passwords do not match.", confirmPwEl);
      return false;
    }
    if (!pwPolicy.test(newPw)) {
      clearAllInvalid();
      markInvalid(newPwEl);
      markInvalid(confirmPwEl);
      showError(
        "Password must be at least 8 characters with uppercase, lowercase, number, and special character.",
        newPwEl,
        true
      );
      return false;
    }
    if (currentPw === newPw) {
      clearAllInvalid();
      markInvalid(newPwEl);
      markInvalid(confirmPwEl);
      showError("New password must be different from current password.", newPwEl, true);
      return false;
    }
    clearAllInvalid();
    setErrorBox("");
    return true;
  };

  link.addEventListener("click", (e) => {
    e.preventDefault();
    changeCompleted = false;
    transitioningToConfirm = false;
    clearFields();
    modal.show();
    setTimeout(() => currentPwEl?.focus(), 50);
  });

  let transitioningToConfirm = false;
  let changeCompleted = false;

  const runAfterModalHidden = (element, instance, callback) => {
    if (!element?.classList.contains("show")) {
      callback();
      return;
    }
    element.addEventListener("hidden.bs.modal", callback, { once: true });
    instance.hide();
  };

	  openConfirmBtn?.addEventListener("click", () => {
	    (async () => {
	      if (!validate()) return;
	      // If any "show password" is on, hide it before proceeding.
	      forceHideAllPasswords();
	
	      // Verify current password server-side before showing the warning modal.
	      setBusy(true);
	      try {
        const res = await fetch("../PhpFiles/Login/verifyCurrentPassword.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            current_password: currentPwEl?.value || "",
          }),
        });
        const data = await readJsonResponse(res);
        if (!data) {
          clearAllInvalid();
          showError("Server returned an unexpected response. Please refresh the page and try again.");
          return;
        }
        if (!res.ok || !data.success) {
          const msg = data.message || "Current password is incorrect.";
          clearAllInvalid();
          showError(msg, currentPwEl);
          return;
        }

        transitioningToConfirm = true;
        runAfterModalHidden(modalEl, modal, () => confirmModal.show());
      } catch (err) {
        showError(err?.message || "Unable to verify current password. Please try again.");
      } finally {
        setBusy(false);
      }
    })();
  });

  const setBusy = (busy) => {
    if (openConfirmBtn) openConfirmBtn.disabled = busy;
    if (confirmBtn) confirmBtn.disabled = busy;
    if (confirmBtn) confirmBtn.textContent = busy ? "Changing..." : "Yes, Change";
  };

  const readJsonResponse = async (res) => {
    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch {
      console.error("Non-JSON response from server:", text.slice(0, 300));
      return null;
    }
  };

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

  const forceHidePassword = (inputId, eyeId) => {
    const input = document.getElementById(inputId);
    const eye = document.getElementById(eyeId);
    if (input) input.type = "password";
    if (eye) {
      eye.classList.remove("bi-eye-slash");
      if (!eye.classList.contains("bi-eye")) eye.classList.add("bi-eye");
    }
  };

  const forceHideAllPasswords = () => {
    forceHidePassword("currentPassword", "eyeCurrentPw");
    forceHidePassword("newPassword", "eyeNewPw");
    forceHidePassword("confirmNewPassword", "eyeConfirmPw");
  };

  document.querySelectorAll("[data-toggle-password][data-eye-id]").forEach((el) => {
    el.addEventListener("click", () => {
      const inputId = el.getAttribute("data-toggle-password") || "";
      const eyeId = el.getAttribute("data-eye-id") || "";
      if (!inputId || !eyeId) return;
      togglePassword(inputId, eyeId);
    });
  });

  const wireClearOnInput = (el) => {
    if (!el) return;
    el.addEventListener("input", () => {
      clearInvalid(el);
      setErrorBox("");
    });
  };
  wireClearOnInput(currentPwEl);
  wireClearOnInput(newPwEl);
  wireClearOnInput(confirmPwEl);

  confirmBtn?.addEventListener("click", async () => {
    if (!validate()) {
      confirmModal.hide();
      return;
    }

    // Ensure passwords are hidden when submitting.
    forceHideAllPasswords();

    setBusy(true);
    try {
      const res = await fetch("../PhpFiles/Login/changePassword.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          current_password: currentPwEl?.value || "",
          new_password: newPwEl?.value || "",
        }),
      });
      const data = await readJsonResponse(res);
      if (!data) {
        throw new Error("Server returned an unexpected response. Please refresh the page and try again.");
      }
      if (!res.ok || !data.success) {
        throw new Error(data.message || "Unable to change password. Please try again.");
      }

      changeCompleted = true;
      transitioningToConfirm = false;
      clearFields();
      runAfterModalHidden(confirmEl, confirmModal, () => {
        window.UniversalModal?.open({
          title: "Success",
          message: data.message || "Password changed successfully.",
          buttons: [{ label: "OK", class: "btn btn-success", onClick: () => {} }],
        });
      });
    } catch (err) {
      transitioningToConfirm = false;
      const msg = err?.message || "Unable to change password. Please try again.";
      runAfterModalHidden(confirmEl, confirmModal, () => {
        modal.show();
        // Try to highlight the most relevant field for common server-side errors.
        clearAllInvalid();
        if (/current password/i.test(msg)) {
          showError(msg, currentPwEl);
        } else if (/different from current/i.test(msg) || /at least 8|uppercase|lowercase|special|number/i.test(msg)) {
          showError(msg, newPwEl, true);
          markInvalid(confirmPwEl);
        } else {
          showError(msg);
        }
      });
    } finally {
      setBusy(false);
    }
  });

  modalEl.addEventListener("hidden.bs.modal", () => {
    if (transitioningToConfirm) return;
    confirmModal.hide();
    clearFields();
    forceHideAllPasswords();
    setBusy(false);
  });

  confirmEl.addEventListener("hidden.bs.modal", () => {
    if (changeCompleted) return;
    if (transitioningToConfirm) {
      transitioningToConfirm = false;
      modal.show();
      setTimeout(() => currentPwEl?.focus(), 50);
    }
  });
});
