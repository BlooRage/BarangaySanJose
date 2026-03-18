(() => {
  const init = () => {
    const form = document.querySelector("form");
    if (!form) return;

    const touchedFields = new WeakSet();

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
      const shouldRender = touchedFields.has(field) || (!field.validity.valid && hasValue);

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
        if (!isTrackableField(field)) return;
        touchedFields.add(field);
        applyFieldState(field);
      },
      true
    );

    form.addEventListener("submit", () => {
      form.querySelectorAll("input, select, textarea").forEach((field) => {
        if (!isTrackableField(field) || field.validity.valid || !isVisibleField(field)) return;
        touchedFields.add(field);
      });
      applyAllTouchedStates();
    });

    form.addEventListener("input", applyAllTouchedStates);
    form.addEventListener("change", applyAllTouchedStates);
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
