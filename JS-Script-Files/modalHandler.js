(() => {
  if (window.UniversalModal && window.UniversalModal.__version) {
    return;
  }

  const nativeAlert = typeof window.alert === "function" ? window.alert.bind(window) : () => {};
  const MODAL_VERSION = "20260318-03";
  const MODAL_ID = "universalModal";
  const STYLESHEET_ID = "universalModalStylesheet";
  const OBSERVER_FLAG = "__universalModalObserverBound";
  const queuedRequests = [];
  let modalInstance = null;
  let activeRequest = null;

  const toneMeta = {
    info: {
      title: "Information",
      icon: "bi bi-info-circle-fill",
      buttonClass: "btn btn-primary modalBtn",
    },
    success: {
      title: "Success",
      icon: "bi bi-check-circle-fill",
      buttonClass: "btn btn-success modalBtn",
    },
    warning: {
      title: "Warning",
      icon: "bi bi-exclamation-triangle-fill",
      buttonClass: "btn btn-warning modalBtn",
    },
    danger: {
      title: "Attention",
      icon: "bi bi-x-circle-fill",
      buttonClass: "btn btn-danger modalBtn",
    },
  };

  function resolveAssetBase() {
    const scripts = Array.from(document.scripts || []);
    const handlerScript = scripts.find((script) => {
      const src = script?.src || "";
      return /\/JS-Script-Files\/modalHandler\.js(?:\?|$)/.test(src);
    });

    if (!handlerScript || !handlerScript.src) {
      return "";
    }

    try {
      const url = new URL(handlerScript.src, window.location.href);
      return url.pathname.replace(/\/JS-Script-Files\/modalHandler\.js(?:\?.*)?$/, "");
    } catch (error) {
      return "";
    }
  }

  function ensureStylesheet() {
    if (document.getElementById(STYLESHEET_ID)) {
      return;
    }

    const hrefBase = resolveAssetBase();
    const link = document.createElement("link");
    link.id = STYLESHEET_ID;
    link.rel = "stylesheet";
    link.href = `${hrefBase}/CSS-Styles/modalStyle.css?v=${MODAL_VERSION}`;
    document.head.appendChild(link);
  }

  function normalizeTone(tone, message = "") {
    const rawTone = String(tone || "").trim().toLowerCase();
    if (rawTone === "error") return "danger";
    if (rawTone === "warn") return "warning";
    if (toneMeta[rawTone]) return rawTone;

    const text = String(message || "").toLowerCase();
    if (/(success|saved|sent|created|updated|approved|verified|completed|restored|deleted|submitted|logged)/.test(text)) {
      return "success";
    }
    if (/(fail|failed|unable|invalid|error|denied|rejected|expired|required|cannot|can't|missing)/.test(text)) {
      return "danger";
    }
    if (/(warning|attention|review|pending|careful|notice)/.test(text)) {
      return "warning";
    }

    return "info";
  }

  function titleForTone(tone, explicitTitle = "") {
    const cleanTitle = String(explicitTitle || "").trim();
    if (cleanTitle !== "") {
      return cleanTitle;
    }

    return toneMeta[tone]?.title || toneMeta.info.title;
  }

  function injectModal(force = false) {
    ensureStylesheet();

    const existing = document.getElementById(MODAL_ID);
    if (existing && force) {
      try {
        const existingInstance = bootstrap?.Modal?.getInstance?.(existing);
        if (existingInstance) existingInstance.dispose();
      } catch (error) {}
      existing.remove();
      modalInstance = null;
      activeRequest = null;
    }

    if (document.getElementById(MODAL_ID)) {
      return;
    }

    document.body.insertAdjacentHTML(
      "beforeend",
      `
      <div class="modal fade uniform-modal" id="${MODAL_ID}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" id="umDialog">
          <div class="modal-content modalContainer" id="umContent" data-tone="info">
            <div class="modal-header justify-content-center border-0 pb-0">
              <h5 class="modal-title text-center w-100" id="umTitle"></h5>
            </div>
            <hr class="my-3" />
            <div class="modal-body text-center pt-0" id="umBody">
              <div class="uniform-modal__icon" id="umIconWrap" aria-hidden="true">
                <i id="umIcon" class="bi bi-info-circle-fill"></i>
              </div>
              <div class="uniform-modal__copy">
                <p class="mb-0" id="umMessage"></p>
              </div>
            </div>
            <div class="modal-footer border-0 pt-0 d-flex justify-content-center" id="umActions"></div>
          </div>
        </div>
      </div>
      `
    );

    const modalEl = document.getElementById(MODAL_ID);
    modalEl.addEventListener("hidden.bs.modal", () => {
      activeRequest = null;
      if (queuedRequests.length > 0) {
        renderAndShow(queuedRequests.shift());
      }
    });
  }

  function decorateButtonClass(className, tone) {
    const baseClass = String(className || "").trim();
    const toneClass = toneMeta[tone]?.buttonClass || toneMeta.info.buttonClass;

    if (baseClass === "") {
      return toneClass;
    }

    if (baseClass.includes("modalBtn")) {
      return baseClass;
    }

    return `${baseClass} modalBtn`.trim();
  }

  function buildButtons(actionsEl, buttons, tone) {
    actionsEl.innerHTML = "";

    const safeButtons = Array.isArray(buttons) && buttons.length > 0
      ? buttons
      : [{ label: "OK", class: toneMeta[tone]?.buttonClass || toneMeta.info.buttonClass }];

    const count = safeButtons.length;
    actionsEl.className = "modal-footer border-0 pt-0 d-flex justify-content-center gap-2 flex-wrap";
    if (count === 1) {
      actionsEl.classList.add("flex-column");
    }

    safeButtons.forEach((buttonConfig) => {
      const buttonEl = document.createElement("button");
      buttonEl.type = "button";
      buttonEl.textContent = buttonConfig.label || "OK";
      buttonEl.className = decorateButtonClass(buttonConfig.class, tone);
      if (buttonConfig.disabled) {
        buttonEl.disabled = true;
      }

      if (count === 1) {
        buttonEl.classList.add("w-100");
      } else if (count === 2) {
        buttonEl.style.flex = "1 1 0";
        buttonEl.style.minWidth = "0";
      } else {
        buttonEl.style.flex = "1 1 160px";
      }

      buttonEl.onclick = () => {
        try {
          if (typeof buttonConfig.onClick === "function") {
            buttonConfig.onClick();
          }
        } finally {
          if (buttonConfig.closeOnClick !== false && modalInstance) {
            modalInstance.hide();
          }
        }
      };

      actionsEl.appendChild(buttonEl);
    });
  }

  function renderAndShow(request) {
    injectModal(false);

    const modalEl = document.getElementById(MODAL_ID);
    const dialogEl = document.getElementById("umDialog");
    const contentEl = document.getElementById("umContent");
    const titleEl = document.getElementById("umTitle");
    const messageEl = document.getElementById("umMessage");
    const iconEl = document.getElementById("umIcon");
    const actionsEl = document.getElementById("umActions");
    const tone = normalizeTone(request.tone, request.message || request.messageHtml || "");

    titleEl.textContent = titleForTone(tone, request.title);
    if (request.messageHtml) {
      messageEl.innerHTML = request.messageHtml;
    } else {
      messageEl.textContent = request.message || "";
    }

    dialogEl.className = "modal-dialog";
    if (request.centered !== false) {
      dialogEl.classList.add("modal-dialog-centered");
    }
    if (request.size) {
      dialogEl.classList.add(request.size);
    }

    contentEl.setAttribute("data-tone", tone);
    iconEl.className = toneMeta[tone]?.icon || toneMeta.info.icon;

    buildButtons(actionsEl, request.buttons, tone);
    activeRequest = request;

    if (!bootstrap?.Modal) {
      nativeAlert(request.message || messageEl.textContent || titleEl.textContent);
      activeRequest = null;
      if (queuedRequests.length > 0) {
        renderAndShow(queuedRequests.shift());
      }
      return;
    }

    if (!modalInstance) {
      modalInstance = new bootstrap.Modal(modalEl, {
        backdrop: "static",
        keyboard: false,
      });
    }

    modalInstance.show();
  }

  function open(options = {}) {
    const request = {
      title: "",
      message: "",
      messageHtml: "",
      buttons: [],
      centered: true,
      size: "",
      tone: "info",
      forceTemplate: true,
      ...options,
    };

    const modalEl = document.getElementById(MODAL_ID);
    const isBusy = !!activeRequest || !!(modalEl && modalEl.classList.contains("show"));

    if (isBusy) {
      queuedRequests.push(request);
      return;
    }

    if (request.forceTemplate) {
      injectModal(true);
    } else {
      injectModal(false);
    }

    renderAndShow(request);
  }

  function alertToneFromClasses(classList) {
    if (classList.contains("alert-danger")) return "danger";
    if (classList.contains("alert-success")) return "success";
    if (classList.contains("alert-warning")) return "warning";
    if (classList.contains("alert-info") || classList.contains("alert-light")) return "info";
    return "info";
  }

  function isAlertVisible(alertEl) {
    if (!(alertEl instanceof HTMLElement)) return false;
    if (!alertEl.classList.contains("alert")) return false;
    if (alertEl.closest(`#${MODAL_ID}`)) return false;
    if (alertEl.dataset.modalUpgrade === "false") return false;
    if (alertEl.classList.contains("d-none")) return false;
    if (alertEl.hidden) return false;

    const style = window.getComputedStyle(alertEl);
    if (style.display === "none" || style.visibility === "hidden") return false;
    if (!String(alertEl.textContent || "").trim()) return false;

    return true;
  }

  function isPageLevelAlert(alertEl) {
    if (alertEl.dataset.modalUpgrade === "true") {
      return true;
    }
    if (alertEl.dataset.modalInline === "true") {
      return false;
    }
    if (alertEl.closest(".modal")) {
      return false;
    }
    return true;
  }

  function alertSignature(alertEl) {
    const tone = alertToneFromClasses(alertEl.classList);
    const content = (alertEl.innerHTML || "").trim();
    return `${tone}::${content}`;
  }

  function hideOriginalAlert(alertEl) {
    alertEl.classList.add("d-none");
    alertEl.dataset.modalUpgraded = "true";
  }

  function upgradeAlertElement(alertEl) {
    if (!isAlertVisible(alertEl) || !isPageLevelAlert(alertEl)) {
      return;
    }

    const signature = alertSignature(alertEl);
    if (alertEl.dataset.modalShownSignature === signature) {
      return;
    }

    alertEl.dataset.modalShownSignature = signature;
    const tone = alertToneFromClasses(alertEl.classList);

    open({
      tone,
      title: alertEl.dataset.modalTitle || "",
      messageHtml: alertEl.innerHTML,
      forceTemplate: false,
    });

    hideOriginalAlert(alertEl);
  }

  function upgradeVisibleAlerts(root = document) {
    const alerts = root.querySelectorAll ? root.querySelectorAll(".alert") : [];
    alerts.forEach(upgradeAlertElement);
  }

  function bindAlertObserver() {
    if (document.body[OBSERVER_FLAG]) {
      return;
    }

    document.body[OBSERVER_FLAG] = true;

    const observer = new MutationObserver((mutations) => {
      const candidates = new Set();

      mutations.forEach((mutation) => {
        if (mutation.target instanceof HTMLElement) {
          const directAlert = mutation.target.classList?.contains("alert")
            ? mutation.target
            : mutation.target.closest?.(".alert");
          if (directAlert) {
            candidates.add(directAlert);
          }
        }

        mutation.addedNodes.forEach((node) => {
          if (!(node instanceof HTMLElement)) return;
          if (node.classList.contains("alert")) {
            candidates.add(node);
          }
          node.querySelectorAll?.(".alert").forEach((alertEl) => candidates.add(alertEl));
        });
      });

      candidates.forEach(upgradeAlertElement);
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true,
      attributes: true,
      characterData: true,
      attributeFilter: ["class", "style", "hidden"],
    });
  }

  function wireGlobalAlertOverride() {
    window.alert = (message) => {
      const text = Array.isArray(message) ? message.join("\n") : String(message ?? "");
      open({
        tone: normalizeTone("", text),
        title: "",
        message: text,
        forceTemplate: false,
      });
    };
  }

  function success(options = {}) {
    open({ tone: "success", ...options, forceTemplate: false });
  }

  function info(options = {}) {
    open({ tone: "info", ...options, forceTemplate: false });
  }

  function warning(options = {}) {
    open({ tone: "warning", ...options, forceTemplate: false });
  }

  function danger(options = {}) {
    open({ tone: "danger", ...options, forceTemplate: false });
  }

  function bootstrapOnceReady() {
    ensureStylesheet();
    upgradeVisibleAlerts(document);
    bindAlertObserver();
    wireGlobalAlertOverride();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootstrapOnceReady, { once: true });
  } else {
    bootstrapOnceReady();
  }

  window.UniversalModal = {
    __version: MODAL_VERSION,
    open,
    success,
    info,
    warning,
    danger,
    upgradeVisibleAlerts,
  };
})();
