(() => {
  function ready(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn, { once: true });
      return;
    }
    fn();
  }

  function parseIsoDate(value) {
    const raw = String(value || "").trim();
    const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) return null;
    return {
      year: Number(match[1]),
      month: Number(match[2]),
      day: Number(match[3])
    };
  }

  function toIsoDate(year, month, day) {
    return `${String(year).padStart(4, "0")}-${String(month).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
  }

  function formatDisplay(isoDate) {
    const parsed = parseIsoDate(isoDate);
    if (!parsed) return "";
    const date = new Date(parsed.year, parsed.month - 1, parsed.day);
    return date.toLocaleDateString("en-US", {
      month: "long",
      day: "numeric",
      year: "numeric"
    });
  }

  ready(() => {
    if (!window.bootstrap) return;

    if (!document.getElementById("residentDateModalStyles")) {
      const style = document.createElement("style");
      style.id = "residentDateModalStyles";
      style.textContent = `
        .resident-date-proxy-wrap {
          position: relative;
        }
        .resident-date-proxy {
          padding-right: 44px;
          cursor: pointer;
          background: #fff;
          border-color: #cbd5e1;
          color: #0f172a;
          box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
          transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
        }
        .resident-date-proxy[readonly] {
          background: #fff !important;
          color: #0f172a !important;
          opacity: 1;
          -webkit-text-fill-color: #0f172a;
        }
        .resident-date-proxy::placeholder {
          color: #64748b;
          opacity: 1;
        }
        .resident-date-proxy:hover {
          background: #f8fafc;
          border-color: #94a3b8;
        }
        .resident-date-proxy:focus {
          background: #fff;
          border-color: #3b82f6;
          box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.18);
          outline: none;
        }
        .resident-date-proxy-icon {
          position: absolute;
          top: 50%;
          right: 14px;
          transform: translateY(-50%);
          color: #64748b;
          pointer-events: none;
          transition: color 0.18s ease;
        }
        .resident-date-proxy-wrap:hover .resident-date-proxy-icon,
        .resident-date-proxy:focus + .resident-date-proxy-icon {
          color: #2563eb;
        }
        .resident-date-modal .modal-content {
          border-radius: 18px;
        }
        .resident-date-modal-preview {
          margin: 0 0 12px;
          padding: 10px 12px;
          border-radius: 12px;
          background: #f8fafc;
          color: #111827;
          font-size: 0.85rem;
          font-weight: 600;
        }
      `;
      document.head.appendChild(style);
    }

    const modalHost = document.createElement("div");
    modalHost.innerHTML = `
      <div class="modal fade resident-date-modal" id="residentDateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <div>
                <div class="fw-bold text-dark" id="residentDateModalTitle">Select Date</div>
                <div class="small text-muted">Choose month, day, and year.</div>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="resident-date-modal-preview" id="residentDateModalPreview">No date selected yet.</div>
              <div class="row g-2">
                <div class="col-4">
                  <label class="form-label small mb-1" for="residentDateModalMonth">Month</label>
                  <select class="form-select" id="residentDateModalMonth">
                    <option value="">Month</option>
                    <option value="1">January</option>
                    <option value="2">February</option>
                    <option value="3">March</option>
                    <option value="4">April</option>
                    <option value="5">May</option>
                    <option value="6">June</option>
                    <option value="7">July</option>
                    <option value="8">August</option>
                    <option value="9">September</option>
                    <option value="10">October</option>
                    <option value="11">November</option>
                    <option value="12">December</option>
                  </select>
                </div>
                <div class="col-4">
                  <label class="form-label small mb-1" for="residentDateModalDay">Day</label>
                  <select class="form-select" id="residentDateModalDay">
                    <option value="">Day</option>
                  </select>
                </div>
                <div class="col-4">
                  <label class="form-label small mb-1" for="residentDateModalYear">Year</label>
                  <select class="form-select" id="residentDateModalYear">
                    <option value="">Year</option>
                  </select>
                </div>
              </div>
              <div class="text-danger small mt-2 d-none" id="residentDateModalError"></div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" id="residentDateModalClear">Clear</button>
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-primary" id="residentDateModalApply">Apply</button>
            </div>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(modalHost.firstElementChild);

    const modalEl = document.getElementById("residentDateModal");
    const modal = new bootstrap.Modal(modalEl);
    const modalTitle = document.getElementById("residentDateModalTitle");
    const modalPreview = document.getElementById("residentDateModalPreview");
    const modalError = document.getElementById("residentDateModalError");
    const monthSelect = document.getElementById("residentDateModalMonth");
    const daySelect = document.getElementById("residentDateModalDay");
    const yearSelect = document.getElementById("residentDateModalYear");
    const applyBtn = document.getElementById("residentDateModalApply");
    const clearBtn = document.getElementById("residentDateModalClear");

    let activeInput = null;
    let activeProxy = null;

    function setModalError(message = "") {
      if (!modalError) return;
      modalError.textContent = String(message || "").trim();
      modalError.classList.toggle("d-none", !message);
    }

    function populateYears(minIso, maxIso) {
      const minYear = parseIsoDate(minIso)?.year ?? (new Date().getFullYear() - 120);
      const maxYear = parseIsoDate(maxIso)?.year ?? (new Date().getFullYear() + 10);
      yearSelect.innerHTML = '<option value="">Year</option>';
      for (let year = maxYear; year >= minYear; year -= 1) {
        const option = document.createElement("option");
        option.value = String(year);
        option.textContent = String(year);
        yearSelect.appendChild(option);
      }
    }

    function populateDays() {
      const year = Number(yearSelect.value || 0);
      const month = Number(monthSelect.value || 0);
      const currentDay = Number(daySelect.value || 0);
      const daysInMonth = year && month ? new Date(year, month, 0).getDate() : 31;
      daySelect.innerHTML = '<option value="">Day</option>';
      for (let day = 1; day <= daysInMonth; day += 1) {
        const option = document.createElement("option");
        option.value = String(day);
        option.textContent = String(day);
        daySelect.appendChild(option);
      }
      if (currentDay && currentDay <= daysInMonth) {
        daySelect.value = String(currentDay);
      }
    }

    function updatePreview() {
      const year = Number(yearSelect.value || 0);
      const month = Number(monthSelect.value || 0);
      const day = Number(daySelect.value || 0);
      if (!year || !month || !day) {
        modalPreview.textContent = "No date selected yet.";
        return;
      }
      modalPreview.textContent = `Selected: ${formatDisplay(toIsoDate(year, month, day))}`;
    }

    function syncModalFromInput(input) {
      const minIso = String(input.getAttribute("min") || "").trim();
      const maxIso = String(input.getAttribute("max") || "").trim();
      populateYears(minIso, maxIso);
      const parsed = parseIsoDate(input.value);
      if (parsed) {
        yearSelect.value = String(parsed.year);
        monthSelect.value = String(parsed.month);
        populateDays();
        daySelect.value = String(parsed.day);
      } else {
        monthSelect.value = "";
        yearSelect.value = "";
        populateDays();
      }
      updatePreview();
      setModalError("");
    }

    function validateSelection(input, isoDate) {
      const minIso = String(input.getAttribute("min") || "").trim();
      const maxIso = String(input.getAttribute("max") || "").trim();
      if (minIso && isoDate < minIso) return `Date must be on or after ${formatDisplay(minIso)}.`;
      if (maxIso && isoDate > maxIso) return `Date must be on or before ${formatDisplay(maxIso)}.`;
      return "";
    }

    function updateProxyValue(input, proxy) {
      proxy.value = formatDisplay(input.value) || "";
      proxy.dispatchEvent(new Event("input", { bubbles: true }));
      proxy.dispatchEvent(new Event("change", { bubbles: true }));
    }

    function openForInput(input, proxy) {
      activeInput = input;
      activeProxy = proxy;
      const label = document.querySelector(`label[for="${input.id}"]`);
      modalTitle.textContent = label ? label.textContent.replace(/\s+/g, " ").trim() : "Select Date";
      syncModalFromInput(input);
      modal.show();
    }

    monthSelect.addEventListener("change", () => {
      populateDays();
      updatePreview();
    });
    yearSelect.addEventListener("change", () => {
      populateDays();
      updatePreview();
    });
    daySelect.addEventListener("change", updatePreview);

    applyBtn.addEventListener("click", () => {
      if (!activeInput || !activeProxy) return;
      const year = Number(yearSelect.value || 0);
      const month = Number(monthSelect.value || 0);
      const day = Number(daySelect.value || 0);
      if (!year || !month || !day) {
        setModalError("Please select month, day, and year.");
        return;
      }
      const isoDate = toIsoDate(year, month, day);
      const message = validateSelection(activeInput, isoDate);
      if (message) {
        setModalError(message);
        return;
      }
      activeInput.value = isoDate;
      activeInput.dispatchEvent(new Event("input", { bubbles: true }));
      activeInput.dispatchEvent(new Event("change", { bubbles: true }));
      updateProxyValue(activeInput, activeProxy);
      setModalError("");
      modal.hide();
    });

    clearBtn.addEventListener("click", () => {
      if (!activeInput || !activeProxy) return;
      activeInput.value = "";
      activeInput.dispatchEvent(new Event("input", { bubbles: true }));
      activeInput.dispatchEvent(new Event("change", { bubbles: true }));
      updateProxyValue(activeInput, activeProxy);
      setModalError("");
      modal.hide();
    });

    modalEl.addEventListener("hidden.bs.modal", () => {
      activeInput = null;
      activeProxy = null;
      setModalError("");
    });

    document.querySelectorAll('input[type="date"]:not([readonly]):not([disabled]):not([data-date-modal-ignore])').forEach((input) => {
      if (input.dataset.dateModalApplied === "1") return;
      input.dataset.dateModalApplied = "1";

      const proxyWrap = document.createElement("div");
      proxyWrap.className = "resident-date-proxy-wrap";

      const proxy = document.createElement("input");
      proxy.type = "text";
      proxy.className = `${input.className || "form-control"} resident-date-proxy`;
      proxy.placeholder = input.getAttribute("placeholder") || "Select date";
      proxy.readOnly = true;
      proxy.autocomplete = "off";
      proxy.setAttribute("role", "button");
      proxy.setAttribute("aria-haspopup", "dialog");
      proxy.title = "Click to select a date";

      const icon = document.createElement("i");
      icon.className = "fa-regular fa-calendar resident-date-proxy-icon";
      icon.setAttribute("aria-hidden", "true");

      proxyWrap.appendChild(proxy);
      proxyWrap.appendChild(icon);
      input.insertAdjacentElement("afterend", proxyWrap);

      input.type = "hidden";
      updateProxyValue(input, proxy);
      input.focus = () => {
        proxy.focus();
        openForInput(input, proxy);
      };

      proxy.addEventListener("click", () => openForInput(input, proxy));
      proxy.addEventListener("focus", () => openForInput(input, proxy));
      proxy.addEventListener("keydown", (event) => {
        if (event.key === "Enter" || event.key === " ") {
          event.preventDefault();
          openForInput(input, proxy);
        }
      });
    });
  });
})();
