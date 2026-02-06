// modalHandler.js (FINAL - Centered Layout, NO X, Full-width buttons)
const UniversalModal = (() => {
  let modalInstance = null;

  function injectModal(force = false) {
    const existing = document.getElementById("universalModal");

    // If old modal exists and we want to ensure the template is correct, remove it
    if (existing && force) {
      try {
        // If bootstrap instance exists, dispose it to avoid weird behavior
        const inst = bootstrap.Modal.getInstance(existing);
        if (inst) inst.dispose();
      } catch (e) {}
      existing.remove();
      modalInstance = null;
    }

    // If modal already exists (and we didn't force replace), just reuse it
    if (document.getElementById("universalModal")) return;

    document.body.insertAdjacentHTML(
      "beforeend",
      `
      <div class="modal fade" id="universalModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" id="umDialog">
          <div class="modal-content" id="umContent">

            <!-- TITLE -->
            <div class="modal-header justify-content-center border-0 pb-0">
              <h5 class="modal-title text-center w-100 text-black" id="umTitle"></h5>
            </div>

            <hr class="my-3" />

            <!-- MESSAGE -->
            <div class="modal-body text-center pt-0" id="umBody">
              <p class="mb-0" id="umMessage"></p>
            </div>

            <!-- BUTTONS -->
            <div class="modal-footer border-0 pt-0 d-flex justify-content-center" id="umActions"></div>

          </div>
        </div>
      </div>
      `
    );
  }

  function buildButtons(actionsEl, buttons) {
    actionsEl.innerHTML = "";

    const safeButtons =
      Array.isArray(buttons) && buttons.length
        ? buttons
        : [{ label: "OK", class: "btn btn-primary" }];

    const count = safeButtons.length;

    actionsEl.className = "modal-footer border-0 pt-0 d-flex justify-content-center gap-2 flex-wrap";
    if (count === 1) actionsEl.classList.add("flex-column");

    safeButtons.forEach((btn) => {
      const b = document.createElement("button");
      b.type = "button";
      b.textContent = btn.label || "OK";
      b.className = btn.class || "btn btn-primary";
      if (btn.disabled) b.disabled = true;

      if (count === 1) {
        b.classList.add("w-100");
      } else if (count === 2) {
        b.style.flex = "1 1 0";
        b.style.minWidth = "0";
      } else {
        b.style.flex = "1 1 140px";
      }

      b.onclick = () => {
        try {
          if (btn.onClick) btn.onClick();
        } finally {
          if (modalInstance) modalInstance.hide();
        }
      };

      actionsEl.appendChild(b);
    });
  }

  function open({
    title = "",
    message = "",
    messageHtml = "",
    buttons = [],
    centered = true,
    size = "", // "", "modal-sm", "modal-lg", "modal-xl"
    forceTemplate = true, // ✅ default: ensure old modal with X is replaced
  }) {
    injectModal(forceTemplate);

    const modalEl = document.getElementById("universalModal");
    const dialogEl = document.getElementById("umDialog");
    const actionsEl = document.getElementById("umActions");

    document.getElementById("umTitle").textContent = title;
    if (messageHtml) {
      document.getElementById("umMessage").innerHTML = messageHtml;
    } else {
      document.getElementById("umMessage").textContent = message;
    }

    dialogEl.className = "modal-dialog";
    if (centered) dialogEl.classList.add("modal-dialog-centered");
    if (size) dialogEl.classList.add(size);

    buildButtons(actionsEl, buttons);

    if (!modalInstance) {
      modalInstance = new bootstrap.Modal(modalEl, {
        backdrop: "static",
        keyboard: false,
      });
    }

    modalInstance.show();
  }

  return { open };
})();

window.UniversalModal = UniversalModal;
