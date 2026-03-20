(() => {
  const init = () => {
    const form = document.querySelector("form");
    if (!form) return;

    const touchedFields = new WeakSet();
    let submitAttempted = false;
    const submitControls = Array.from(
      form.querySelectorAll('button[type="submit"], input[type="submit"]')
    );

    const getSubmitLabel = (control) => {
      if (!control) return "Submit";
      const tagName = String(control.tagName || "").toLowerCase();
      if (tagName === "input") {
        return String(control.value || "Submit");
      }
      return String(control.innerHTML || control.textContent || "Submit");
    };

    const setSubmitLoadingState = () => {
      submitControls.forEach((control) => {
        if (!control) return;
        if (!control.dataset.originalLabel) {
          control.dataset.originalLabel = getSubmitLabel(control);
        }

        const tagName = String(control.tagName || "").toLowerCase();
        if (tagName === "input") {
          control.value = "Submitting...";
        } else {
          control.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Submitting...';
        }

        control.disabled = true;
        control.setAttribute("aria-busy", "true");
      });
    };

    const resetSubmitLoadingState = () => {
      delete form.dataset.submitting;
      submitControls.forEach((control) => {
        if (!control) return;
        const originalLabel = control.dataset.originalLabel;
        if (originalLabel) {
          const tagName = String(control.tagName || "").toLowerCase();
          if (tagName === "input") {
            control.value = originalLabel;
          } else {
            control.innerHTML = originalLabel;
          }
        }
        control.disabled = false;
        control.removeAttribute("aria-busy");
      });
      syncSubmitDisabledState();
    };

    const syncSubmitDisabledState = () => {
      if (form.dataset.submitting === "true") return;
      const isValid = form.checkValidity();
      submitControls.forEach((control) => {
        if (!control) return;
        control.disabled = !isValid;
      });
    };

    const isTrackableField = (field) => {
      if (!field || field.disabled) return false;
      const tagName = String(field.tagName || "").toLowerCase();
      if (!["input", "select", "textarea"].includes(tagName)) return false;
      const type = String(field.type || "").toLowerCase();
      return !["hidden", "button", "submit", "reset"].includes(type);
    };

    const isVisibleField = (field) => {
      if (!isTrackableField(field)) return false;
      return !field.closest(".d-none");
    };

    const applyFieldState = (field) => {
      if (!isTrackableField(field)) return;
      if (!isVisibleField(field)) {
        field.classList.remove("is-invalid");
        return;
      }

      const hasValue = field.type === "checkbox" || field.type === "radio"
        ? field.checked
        : String(field.value || "").trim() !== "";
      const shouldRender = touchedFields.has(field) || submitAttempted;

      if (!shouldRender) {
        field.classList.remove("is-invalid");
        return;
      }

      field.classList.toggle("is-invalid", !field.validity.valid);
    };

    const applyAllTouchedStates = () => {
      form.querySelectorAll("input, select, textarea").forEach((field) => {
        applyFieldState(field);
      });
    };

    form.querySelectorAll("input, select, textarea").forEach((field) => {
      if (!isTrackableField(field)) return;

      const handleInteraction = () => {
        touchedFields.add(field);
        applyFieldState(field);
      };

      field.addEventListener("input", handleInteraction);
      field.addEventListener("change", handleInteraction);
      field.addEventListener("blur", handleInteraction);
    });

    form.addEventListener(
      "invalid",
      (event) => {
        const field = event.target;
        if (!isTrackableField(field) || !submitAttempted) return;
        touchedFields.add(field);
        applyFieldState(field);
      },
      true
    );

    form.addEventListener("submit", (event) => {
      submitAttempted = true;
      form.querySelectorAll("input, select, textarea").forEach((field) => {
        if (!isTrackableField(field) || field.validity.valid || !isVisibleField(field)) return;
        touchedFields.add(field);
      });
      applyAllTouchedStates();

      if (!form.checkValidity()) {
        resetSubmitLoadingState();
        return;
      }

      if (form.dataset.submitting === "true") {
        event.preventDefault();
        return;
      }

      form.dataset.submitting = "true";
      setSubmitLoadingState();
    });

    form.addEventListener("input", applyAllTouchedStates);
    form.addEventListener("change", applyAllTouchedStates);
    form.addEventListener("input", syncSubmitDisabledState);
    form.addEventListener("change", syncSubmitDisabledState);
    window.addEventListener("pageshow", resetSubmitLoadingState);
    syncSubmitDisabledState();
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
