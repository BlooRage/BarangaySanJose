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

  function formatMonthYear(year, month) {
    return new Date(year, month - 1, 1).toLocaleDateString("en-US", {
      month: "long",
      year: "numeric"
    });
  }

  function shiftMonth(year, month, delta) {
    const shifted = new Date(year, month - 1 + delta, 1);
    return {
      year: shifted.getFullYear(),
      month: shifted.getMonth() + 1
    };
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
          background: #fff !important;
          background-color: #fff !important;
          background-image: none !important;
          border-color: #cbd5e1;
          color: #0f172a;
          box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
          transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
        }
        .resident-date-proxy[readonly] {
          background: #fff !important;
          background-color: #fff !important;
          color: #0f172a !important;
          opacity: 1;
          -webkit-text-fill-color: #0f172a;
        }
        .application-card input.resident-date-proxy[readonly],
        .form-row input.resident-date-proxy[readonly],
        .status-row input.resident-date-proxy[readonly],
        .application-card input.resident-date-proxy[readonly]:focus,
        .form-row input.resident-date-proxy[readonly]:focus,
        .status-row input.resident-date-proxy[readonly]:focus {
          background: #fff !important;
          background-color: #fff !important;
          color: #0f172a !important;
          border-color: #cbd5e1 !important;
          -webkit-text-fill-color: #0f172a !important;
          opacity: 1 !important;
          cursor: pointer !important;
        }
        .resident-date-proxy::placeholder {
          color: #64748b;
          opacity: 1;
        }
        .resident-date-proxy:hover {
          background: #fff;
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
        .resident-date-modal {
          /* This picker can open from inside another Bootstrap modal. */
          z-index: 2080 !important;
        }
        .modal-backdrop.resident-date-modal-backdrop {
          z-index: 2070 !important;
        }
        .resident-date-modal .modal-content {
          border-radius: 18px;
        }
        .resident-date-modal--complaint .modal-content {
          border: 1px solid #f2d3b8;
          border-radius: 20px;
          box-shadow: 0 24px 48px rgba(77, 45, 12, 0.18);
          background: linear-gradient(180deg, #fffaf5 0%, #ffffff 100%);
          overflow: hidden;
        }
        .resident-date-modal--complaint .modal-header {
          padding: 20px 22px 14px;
          border-bottom: 1px solid #f2e3d3;
          background: linear-gradient(180deg, #fffaf5 0%, #ffffff 100%);
        }
        .resident-date-modal--complaint .btn-close {
          opacity: 0.78;
        }
        .resident-date-modal--complaint .btn-close:hover,
        .resident-date-modal--complaint .btn-close:focus {
          opacity: 1;
          box-shadow: none;
        }
        .resident-date-modal-heading {
          font-family: 'Charis SIL Bold', serif;
          font-size: 20px;
          color: #2f2419;
          line-height: 1.2;
        }
        .resident-date-modal-subheading {
          margin-top: 4px;
          color: #6b7280;
          font-size: 14px;
          line-height: 1.5;
        }
        .resident-date-modal--complaint .modal-body {
          padding: 20px 22px 16px;
          background: #ffffff;
        }
        .resident-date-modal--calendar .modal-dialog {
          max-width: 760px;
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
        .resident-date-modal--complaint .resident-date-modal-preview {
          background: #fff7ef;
          color: #7c3f00;
          border: 1px solid #f2dfcb;
        }
        .resident-date-calendar-shell {
          display: grid;
          gap: 12px;
        }
        .resident-date-calendar-toolbar {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 10px;
        }
        .resident-date-calendar-controls {
          flex: 1;
          display: grid;
          gap: 8px;
          justify-items: center;
        }
        .resident-date-calendar-nav {
          width: 38px;
          height: 38px;
          border: 1px solid #dbe3ef;
          border-radius: 10px;
          background: #fff;
          color: #334155;
          font-size: 1.05rem;
          line-height: 1;
          transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease;
        }
        .resident-date-calendar-nav:hover {
          background: #f8fafc;
          border-color: #cbd5e1;
          color: #1e293b;
        }
        .resident-date-modal--complaint .resident-date-calendar-nav:hover {
          background: #fff7ed;
          border-color: #fdba74;
          color: #9a3412;
        }
        .resident-date-calendar-nav:disabled {
          cursor: not-allowed;
          background: #f8fafc;
          border-color: #e5e7eb;
          color: #cbd5e1;
        }
        .resident-date-calendar-title {
          flex: 1;
          text-align: center;
          font-size: 1rem;
          font-weight: 700;
          color: #0f172a;
        }
        .resident-date-calendar-picker {
          display: flex;
          flex-wrap: wrap;
          justify-content: center;
          gap: 8px;
          width: min(100%, 320px);
        }
        .resident-date-calendar-select {
          flex: 1 1 140px;
          min-width: 0;
          border: 1px solid #dbe3ef;
          border-radius: 10px;
          background-color: #fff;
          color: #0f172a;
          font-size: 0.95rem;
          font-weight: 600;
          box-shadow: none;
        }
        .resident-date-calendar-select:focus {
          border-color: #fb923c;
          box-shadow: 0 0 0 0.2rem rgba(249, 115, 22, 0.12);
        }
        .resident-date-modal--complaint .resident-date-calendar-select:focus {
          border-color: #e8872f;
          box-shadow: 0 0 0 0.2rem rgba(232, 135, 47, 0.14);
        }
        .resident-date-calendar-weekdays,
        .resident-date-calendar-grid {
          display: grid;
          grid-template-columns: repeat(7, minmax(0, 1fr));
          gap: 6px;
        }
        .resident-date-calendar-weekday {
          text-align: center;
          min-height: 30px;
          display: flex;
          align-items: flex-end;
          justify-content: center;
          padding: 0 2px 2px;
          font-size: 0.7rem;
          font-weight: 700;
          line-height: 1.15;
          color: #64748b;
        }
        .resident-date-calendar-day {
          min-height: 42px;
          border: 1px solid #e2e8f0;
          border-radius: 12px;
          background: #fff;
          color: #0f172a;
          font-size: 0.95rem;
          font-weight: 600;
          transition: transform 0.15s ease, background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
        }
        .resident-date-calendar-day:hover:not(:disabled) {
          background: linear-gradient(180deg, #f97316 0%, #ea580c 100%);
          border-color: #ea580c;
          color: #fff;
          transform: translateY(-1px);
          box-shadow: 0 10px 20px rgba(234, 88, 12, 0.18);
        }
        .resident-date-calendar-day.is-selected,
        .resident-date-calendar-day.is-selected:hover:not(:disabled) {
          background: linear-gradient(180deg, #f97316 0%, #ea580c 100%);
          border-color: #ea580c;
          color: #fff;
          box-shadow: 0 10px 20px rgba(234, 88, 12, 0.18);
        }
        .resident-date-calendar-day.is-today:not(.is-selected) {
          border-color: #fb923c;
          color: #c2410c;
          background: #fff7ed;
        }
        .resident-date-calendar-day.is-placeholder {
          visibility: hidden;
          pointer-events: none;
          border-color: transparent;
          background: transparent;
          box-shadow: none;
        }
        .resident-date-calendar-day:disabled {
          cursor: not-allowed;
          color: #cbd5e1;
          background: #f8fafc;
          border-color: #eef2f7;
          box-shadow: none;
          transform: none;
        }
        .resident-date-modal--complaint .modal-footer {
          padding: 14px 22px 20px;
          border-top: 1px solid #f2e3d3;
          background: #fffdfb;
          gap: 10px;
        }
        .resident-date-modal-secondary-btn {
          min-height: 48px;
          border-radius: 12px;
        }
        .resident-date-modal--complaint .resident-date-modal-secondary-btn {
          border: 1px solid #d6dce5;
          background: #ffffff;
          color: #7c3f00;
          font-weight: 600;
        }
        .resident-date-modal--complaint .resident-date-modal-secondary-btn:hover,
        .resident-date-modal--complaint .resident-date-modal-secondary-btn:focus {
          border-color: #e8872f;
          background: #fff7f0;
          color: #b75f0d;
          box-shadow: none;
        }
        .resident-date-modal-primary-btn {
          min-height: 48px;
          border-radius: 12px;
        }
        .resident-date-modal--complaint .resident-date-modal-primary-btn {
          border: 1px solid #e8872f;
          background: linear-gradient(135deg, #f59b3d 0%, #e8872f 100%);
          color: #ffffff;
          font-weight: 700;
        }
        .resident-date-modal--complaint .resident-date-modal-primary-btn:hover,
        .resident-date-modal--complaint .resident-date-modal-primary-btn:focus {
          border-color: #cf6f14;
          background: linear-gradient(135deg, #e8872f 0%, #cf6f14 100%);
          box-shadow: 0 12px 24px rgba(232, 135, 47, 0.22);
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
                <div class="resident-date-modal-heading" id="residentDateModalTitle">Select Date</div>
                <div class="resident-date-modal-subheading" id="residentDateModalSubtitle">Choose month, day, and year.</div>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="resident-date-modal-preview" id="residentDateModalPreview">No date selected yet.</div>

              <div id="residentDateSelectBody">
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
              </div>

              <div id="residentDateCalendarBody" class="d-none">
                <div class="resident-date-calendar-shell">
                  <div class="resident-date-calendar-toolbar">
                    <button type="button" class="resident-date-calendar-nav" id="residentDateCalendarPrev" aria-label="Previous month">&lsaquo;</button>
                    <div class="resident-date-calendar-controls">
                      <div class="resident-date-calendar-title" id="residentDateCalendarTitle">Month Year</div>
                      <div class="resident-date-calendar-picker">
                        <label class="visually-hidden" for="residentDateCalendarMonth">Month</label>
                        <select class="form-select resident-date-calendar-select" id="residentDateCalendarMonth">
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
                        <label class="visually-hidden" for="residentDateCalendarYear">Year</label>
                        <select class="form-select resident-date-calendar-select" id="residentDateCalendarYear">
                          <option value="">Year</option>
                        </select>
                      </div>
                    </div>
                    <button type="button" class="resident-date-calendar-nav" id="residentDateCalendarNext" aria-label="Next month">&rsaquo;</button>
                  </div>
                  <div class="resident-date-calendar-weekdays">
                    <div class="resident-date-calendar-weekday">Sunday</div>
                    <div class="resident-date-calendar-weekday">Monday</div>
                    <div class="resident-date-calendar-weekday">Tuesday</div>
                    <div class="resident-date-calendar-weekday">Wednesday</div>
                    <div class="resident-date-calendar-weekday">Thursday</div>
                    <div class="resident-date-calendar-weekday">Friday</div>
                    <div class="resident-date-calendar-weekday">Saturday</div>
                  </div>
                  <div class="resident-date-calendar-grid" id="residentDateCalendarGrid"></div>
                </div>
              </div>

              <div class="text-danger small mt-2 d-none" id="residentDateModalError"></div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary resident-date-modal-secondary-btn" id="residentDateModalClear">Clear</button>
              <button type="button" class="btn btn-outline-secondary resident-date-modal-secondary-btn" data-bs-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-primary resident-date-modal-primary-btn" id="residentDateModalApply">Apply</button>
            </div>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(modalHost.firstElementChild);

    const modalEl = document.getElementById("residentDateModal");
    modalEl.style.setProperty("z-index", "2080", "important");
    const modal = new bootstrap.Modal(modalEl);
    const modalTitle = document.getElementById("residentDateModalTitle");
    const modalSubtitle = document.getElementById("residentDateModalSubtitle");
    const modalPreview = document.getElementById("residentDateModalPreview");
    const modalError = document.getElementById("residentDateModalError");
    const selectBody = document.getElementById("residentDateSelectBody");
    const calendarBody = document.getElementById("residentDateCalendarBody");
    const monthSelect = document.getElementById("residentDateModalMonth");
    const daySelect = document.getElementById("residentDateModalDay");
    const yearSelect = document.getElementById("residentDateModalYear");
    const calendarTitle = document.getElementById("residentDateCalendarTitle");
    const calendarMonthSelect = document.getElementById("residentDateCalendarMonth");
    const calendarYearSelect = document.getElementById("residentDateCalendarYear");
    const calendarGrid = document.getElementById("residentDateCalendarGrid");
    const calendarPrevBtn = document.getElementById("residentDateCalendarPrev");
    const calendarNextBtn = document.getElementById("residentDateCalendarNext");
    const applyBtn = document.getElementById("residentDateModalApply");
    const clearBtn = document.getElementById("residentDateModalClear");

    let activeInput = null;
    let activeProxy = null;
    let activeMode = "select";
    let calendarCursorYear = 0;
    let calendarCursorMonth = 0;
    let calendarSelectedIso = "";
    let calendarSelectedDates = [];

    function setModalError(message = "") {
      if (!modalError) return;
      modalError.textContent = String(message || "").trim();
      modalError.classList.toggle("d-none", !message);
    }

    const inputValueDescriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value");
    const inputDisabledDescriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "disabled");

    function getInputMode(input) {
      return String(input?.dataset?.dateModalStyle || "").trim().toLowerCase() === "select"
        ? "select"
        : "calendar";
    }

    function patchInputInstanceProperty(input, propertyName, descriptor, onSet) {
      if (!(input instanceof HTMLInputElement) || !descriptor || typeof descriptor.get !== "function" || typeof descriptor.set !== "function") {
        return;
      }

      const ownDescriptor = Object.getOwnPropertyDescriptor(input, propertyName);
      if (ownDescriptor && ownDescriptor.configurable === false) {
        return;
      }

      Object.defineProperty(input, propertyName, {
        configurable: true,
        enumerable: descriptor.enumerable ?? true,
        get() {
          return descriptor.get.call(this);
        },
        set(value) {
          descriptor.set.call(this, value);
          onSet();
        }
      });
    }

    function isComplaintDateInput(input) {
      if (!(input instanceof HTMLInputElement)) {
        return false;
      }

      if (input.id === "incidentDate" || input.name === "incident_date") {
        return true;
      }

      return !!(input.form && input.form.id === "complaintForm");
    }

    function getTodayIso() {
      const today = new Date();
      return toIsoDate(today.getFullYear(), today.getMonth() + 1, today.getDate());
    }

    function getDateModalDefaultIso(input) {
      const defaultValue = String(input?.dataset?.dateModalDefault || "").trim().toLowerCase();
      if (defaultValue === "today") {
        return getTodayIso();
      }
      if (/^\d{4}-\d{2}-\d{2}$/.test(defaultValue)) {
        return defaultValue;
      }
      return "";
    }

    function normalizeFieldToken(value) {
      return String(value || "")
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "");
    }

    function isBirthdateLikeInput(input) {
      if (!(input instanceof HTMLInputElement)) {
        return false;
      }

      return [
        input.id,
        input.name,
        input.getAttribute("data-date-field"),
        input.getAttribute("aria-label")
      ].some((value) => {
        const token = normalizeFieldToken(value);
        return token === "birthdate"
          || token === "dateofbirth"
          || token === "dob"
          || token.endsWith("birthdate")
          || token.endsWith("dateofbirth")
          || token.endsWith("dob")
          || token.includes("childdob")
          || token.includes("cohabitantdob")
          || token.includes("cohabitantbirthdate")
          || token.includes("partnerdob")
          || token.includes("partnerbirthdate")
          || token.includes("manualbirthdate");
      });
    }

    function applyPastOnlyDateConstraints(input) {
      if (!isBirthdateLikeInput(input)) {
        return;
      }

      const todayIso = getTodayIso();
      const currentMax = String(input.getAttribute("max") || "").trim();
      if (!currentMax || currentMax > todayIso) {
        input.setAttribute("max", todayIso);
      }
    }

    function getDisabledWeekdays(input) {
      return new Set(
        String(input?.dataset?.dateDisabledWeekdays || "")
          .split(",")
          .map((value) => value.trim())
          .filter((value) => value !== "")
      );
    }

    function getDisabledDates(input) {
      return new Set(
        String(input?.dataset?.dateDisabledDates || "")
          .split(",")
          .map((value) => value.trim())
          .filter((value) => value !== "")
      );
    }

    function normalizeIsoDates(values) {
      return Array.from(new Set(
        Array.isArray(values) ? values : String(values || "").split(",")
      ))
        .map((value) => String(value || "").trim())
        .filter((value) => /^\d{4}-\d{2}-\d{2}$/.test(value))
        .sort();
    }

    function getMultiValueTarget(input) {
      const rawTarget = String(input?.dataset?.dateMultiTarget || "").trim();
      if (!rawTarget) return null;

      if (/^[A-Za-z][A-Za-z0-9_-]*$/.test(rawTarget)) {
        return document.getElementById(rawTarget);
      }

      try {
        return document.querySelector(rawTarget);
      } catch (error) {
        return null;
      }
    }

    function isMultiCalendarInput(input) {
      return getInputMode(input) === "calendar"
        && String(input?.dataset?.dateModalSelection || "").trim().toLowerCase() === "multiple"
        && getMultiValueTarget(input) instanceof HTMLInputElement;
    }

    function getMultiSelectedDates(input) {
      const target = getMultiValueTarget(input);
      return target instanceof HTMLInputElement ? normalizeIsoDates(target.value) : [];
    }

    function setMultiSelectedDates(input, dates) {
      const target = getMultiValueTarget(input);
      if (!(target instanceof HTMLInputElement)) {
        return;
      }

      const normalizedDates = normalizeIsoDates(dates);
      target.value = normalizedDates.join(",");
      target.dispatchEvent(new Event("input", { bubbles: true }));
      target.dispatchEvent(new Event("change", { bubbles: true }));
    }

    function formatMultiDatePreview(isoDates) {
      const dates = normalizeIsoDates(isoDates);
      if (dates.length === 0) {
        return "No dates selected yet.";
      }
      if (dates.length <= 3) {
        return `Selected: ${dates.map((isoDate) => formatDisplay(isoDate)).join(", ")}`;
      }
      return `Selected: ${dates.slice(0, 2).map((isoDate) => formatDisplay(isoDate)).join(", ")} +${dates.length - 2} more`;
    }

    function setModalMode(mode) {
      activeMode = mode === "calendar" ? "calendar" : "select";
      selectBody.classList.toggle("d-none", activeMode !== "select");
      calendarBody.classList.toggle("d-none", activeMode !== "calendar");
      modalEl.classList.toggle("resident-date-modal--calendar", activeMode === "calendar");
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

    function updateSelectPreview() {
      const year = Number(yearSelect.value || 0);
      const month = Number(monthSelect.value || 0);
      const day = Number(daySelect.value || 0);
      if (!year || !month || !day) {
        modalPreview.textContent = "No date selected yet.";
        return;
      }
      modalPreview.textContent = `Selected: ${formatDisplay(toIsoDate(year, month, day))}`;
    }

    function updateCalendarPreview() {
      if (isMultiCalendarInput(activeInput)) {
        modalPreview.textContent = formatMultiDatePreview(calendarSelectedDates);
        return;
      }

      modalPreview.textContent = calendarSelectedIso
        ? `Selected: ${formatDisplay(calendarSelectedIso)}`
        : "No date selected yet.";
    }

    function validateSelection(input, isoDate) {
      const minIso = String(input.getAttribute("min") || "").trim();
      const maxIso = String(input.getAttribute("max") || "").trim();
      const disabledDates = getDisabledDates(input);
      const disabledWeekdays = getDisabledWeekdays(input);
      const availableWeekdaysLabel = String(input?.dataset?.availableWeekdays || "").trim();
      const parsedDate = parseIsoDate(isoDate);
      if (minIso && isoDate < minIso) return `Date must be on or after ${formatDisplay(minIso)}.`;
      if (maxIso && isoDate > maxIso) return `Date must be on or before ${formatDisplay(maxIso)}.`;
      if (disabledDates.has(isoDate)) return "The selected date is unavailable.";
      if (parsedDate && disabledWeekdays.has(String(new Date(parsedDate.year, parsedDate.month - 1, parsedDate.day).getDay()))) {
        return availableWeekdaysLabel
          ? `Appointments are only available on ${availableWeekdaysLabel}.`
          : "The selected date is unavailable.";
      }
      return "";
    }

    function updateProxyValue(input, proxy) {
      if (isMultiCalendarInput(input)) {
        const selectedDates = getMultiSelectedDates(input);
        proxy.value = selectedDates.length === 0
          ? ""
          : (selectedDates.length === 1
            ? formatDisplay(selectedDates[0])
            : `${selectedDates.length} dates selected`);
        proxy.dispatchEvent(new Event("input", { bubbles: true }));
        proxy.dispatchEvent(new Event("change", { bubbles: true }));
        return;
      }

      proxy.value = formatDisplay(input.value) || "";
      proxy.dispatchEvent(new Event("input", { bubbles: true }));
      proxy.dispatchEvent(new Event("change", { bubbles: true }));
    }

    function syncSelectFromInput(input) {
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
      updateSelectPreview();
      setModalError("");
    }

    function syncCalendarFromInput(input) {
      const selectedDates = isMultiCalendarInput(input) ? getMultiSelectedDates(input) : [];
      const parsed = selectedDates[0] ? parseIsoDate(selectedDates[0]) : parseIsoDate(input.value);
      const defaultParsed = !parsed ? parseIsoDate(getDateModalDefaultIso(input)) : null;
      const minParsed = parseIsoDate(String(input.getAttribute("min") || "").trim());
      const todayParsed = parseIsoDate(getTodayIso());
      const base = parsed || defaultParsed || minParsed || todayParsed || {
        year: new Date().getFullYear(),
        month: new Date().getMonth() + 1,
        day: new Date().getDate()
      };

      calendarCursorYear = base.year;
      calendarCursorMonth = base.month;
      calendarSelectedIso = (parsed || defaultParsed) ? toIsoDate(base.year, base.month, base.day) : "";
      calendarSelectedDates = selectedDates;
      renderCalendar();
      setModalError("");
    }

    function clampCalendarCursor() {
      if (!activeInput) return;

      const minParsed = parseIsoDate(String(activeInput.getAttribute("min") || "").trim());
      const maxParsed = parseIsoDate(String(activeInput.getAttribute("max") || "").trim());
      if (minParsed) {
        const beforeMin = calendarCursorYear < minParsed.year
          || (calendarCursorYear === minParsed.year && calendarCursorMonth < minParsed.month);
        if (beforeMin) {
          calendarCursorYear = minParsed.year;
          calendarCursorMonth = minParsed.month;
        }
      }
      if (maxParsed) {
        const afterMax = calendarCursorYear > maxParsed.year
          || (calendarCursorYear === maxParsed.year && calendarCursorMonth > maxParsed.month);
        if (afterMax) {
          calendarCursorYear = maxParsed.year;
          calendarCursorMonth = maxParsed.month;
        }
      }
    }

    function populateCalendarYears() {
      if (!calendarYearSelect || !activeInput) return;

      const minIso = String(activeInput.getAttribute("min") || "").trim();
      const maxIso = String(activeInput.getAttribute("max") || "").trim();
      const minYear = parseIsoDate(minIso)?.year ?? (new Date().getFullYear() - 120);
      const maxYear = parseIsoDate(maxIso)?.year ?? (new Date().getFullYear() + 10);
      const currentValue = String(calendarYearSelect.value || "");
      calendarYearSelect.innerHTML = "";
      for (let year = maxYear; year >= minYear; year -= 1) {
        const option = document.createElement("option");
        option.value = String(year);
        option.textContent = String(year);
        calendarYearSelect.appendChild(option);
      }
      if (currentValue && calendarYearSelect.querySelector(`option[value="${currentValue}"]`)) {
        calendarYearSelect.value = currentValue;
      }
    }

    function syncCalendarSelectors() {
      if (!calendarMonthSelect || !calendarYearSelect || !activeInput) return;

      clampCalendarCursor();
      populateCalendarYears();

      const minParsed = parseIsoDate(String(activeInput.getAttribute("min") || "").trim());
      const maxParsed = parseIsoDate(String(activeInput.getAttribute("max") || "").trim());

      Array.from(calendarMonthSelect.options).forEach((option) => {
        const month = Number(option.value || 0);
        let disabled = false;
        if (month > 0 && minParsed && calendarCursorYear === minParsed.year && month < minParsed.month) {
          disabled = true;
        }
        if (month > 0 && maxParsed && calendarCursorYear === maxParsed.year && month > maxParsed.month) {
          disabled = true;
        }
        option.disabled = disabled;
      });

      calendarMonthSelect.value = String(calendarCursorMonth);
      calendarYearSelect.value = String(calendarCursorYear);

      const atMinMonth = !!minParsed
        && calendarCursorYear === minParsed.year
        && calendarCursorMonth === minParsed.month;
      const atMaxMonth = !!maxParsed
        && calendarCursorYear === maxParsed.year
        && calendarCursorMonth === maxParsed.month;
      if (calendarPrevBtn) calendarPrevBtn.disabled = atMinMonth;
      if (calendarNextBtn) calendarNextBtn.disabled = atMaxMonth;
    }

    function renderCalendar() {
      if (!activeInput || !calendarGrid) return;

      clampCalendarCursor();
      const todayIso = getTodayIso();
      const minIso = String(activeInput.getAttribute("min") || "").trim();
      const maxIso = String(activeInput.getAttribute("max") || "").trim();
      const disabledWeekdays = getDisabledWeekdays(activeInput);
      const disabledDates = getDisabledDates(activeInput);
      const selectedDates = new Set(calendarSelectedDates);
      const multiSelect = isMultiCalendarInput(activeInput);
      const firstWeekday = new Date(calendarCursorYear, calendarCursorMonth - 1, 1).getDay();
      const daysInCurrentMonth = new Date(calendarCursorYear, calendarCursorMonth, 0).getDate();
      calendarTitle.textContent = formatMonthYear(calendarCursorYear, calendarCursorMonth);
      syncCalendarSelectors();
      calendarGrid.innerHTML = "";

      for (let cellIndex = 0; cellIndex < 42; cellIndex += 1) {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "resident-date-calendar-day";
        const isOutsideMonth = cellIndex < firstWeekday || cellIndex >= firstWeekday + daysInCurrentMonth;

        if (isOutsideMonth) {
          button.textContent = "";
          button.disabled = true;
          button.classList.add("is-placeholder");
          calendarGrid.appendChild(button);
          continue;
        }

        const dayNumber = cellIndex - firstWeekday + 1;
        const isoDate = toIsoDate(calendarCursorYear, calendarCursorMonth, dayNumber);
        const weekday = String(new Date(calendarCursorYear, calendarCursorMonth - 1, dayNumber).getDay());
        const isDisabled = (minIso && isoDate < minIso) || (maxIso && isoDate > maxIso) || disabledWeekdays.has(weekday) || disabledDates.has(isoDate);
        const isSelected = multiSelect ? selectedDates.has(isoDate) : calendarSelectedIso === isoDate;
        const isToday = todayIso === isoDate;

        button.textContent = String(dayNumber);
        button.disabled = isDisabled;
        if (isSelected) {
          button.classList.add("is-selected");
        }
        if (isToday) {
          button.classList.add("is-today");
        }

        button.addEventListener("click", () => {
          if (multiSelect) {
            if (selectedDates.has(isoDate)) {
              calendarSelectedDates = calendarSelectedDates.filter((value) => value !== isoDate);
            } else {
              calendarSelectedDates = normalizeIsoDates([...calendarSelectedDates, isoDate]);
            }
            calendarSelectedIso = calendarSelectedDates[calendarSelectedDates.length - 1] || "";
          } else {
            calendarSelectedIso = isoDate;
          }
          updateCalendarPreview();
          renderCalendar();
        });

        calendarGrid.appendChild(button);
      }

      updateCalendarPreview();
    }

    function openForInput(input, proxy) {
      if (!(input instanceof HTMLInputElement) || input.matches(":disabled")) {
        return;
      }

      applyPastOnlyDateConstraints(input);

      activeInput = input;
      activeProxy = proxy;
      modalEl.classList.toggle("resident-date-modal--complaint", isComplaintDateInput(input));

      const label = document.querySelector(`label[for="${input.id}"]`);
      modalTitle.textContent = label ? label.textContent.replace(/\s+/g, " ").trim() : "Select Date";

      const mode = getInputMode(input);
      setModalMode(mode);

      if (mode === "calendar") {
        modalSubtitle.textContent = isMultiCalendarInput(input)
          ? "Choose one or more dates from the calendar."
          : "Choose a date from the calendar.";
        syncCalendarFromInput(input);
      } else {
        modalSubtitle.textContent = input.name === "incident_date"
          ? "Choose month, day, and year. Complaints older than 6 months are invalid."
          : "Choose month, day, and year.";
        syncSelectFromInput(input);
      }

      modal.show();
    }

    monthSelect.addEventListener("change", () => {
      populateDays();
      updateSelectPreview();
    });

    yearSelect.addEventListener("change", () => {
      populateDays();
      updateSelectPreview();
    });

    daySelect.addEventListener("change", updateSelectPreview);

    calendarPrevBtn.addEventListener("click", () => {
      const next = shiftMonth(calendarCursorYear, calendarCursorMonth, -1);
      calendarCursorYear = next.year;
      calendarCursorMonth = next.month;
      renderCalendar();
    });

    calendarNextBtn.addEventListener("click", () => {
      const next = shiftMonth(calendarCursorYear, calendarCursorMonth, 1);
      calendarCursorYear = next.year;
      calendarCursorMonth = next.month;
      renderCalendar();
    });

    calendarMonthSelect?.addEventListener("change", () => {
      const nextMonth = Number(calendarMonthSelect.value || 0);
      if (!nextMonth) return;
      calendarCursorMonth = nextMonth;
      renderCalendar();
    });

    calendarYearSelect?.addEventListener("change", () => {
      const nextYear = Number(calendarYearSelect.value || 0);
      if (!nextYear) return;
      calendarCursorYear = nextYear;
      renderCalendar();
    });

    applyBtn.addEventListener("click", () => {
      if (!activeInput || !activeProxy) return;

      let isoDate = "";
      if (activeMode === "calendar") {
        if (isMultiCalendarInput(activeInput)) {
          const selectedDates = normalizeIsoDates(calendarSelectedDates);
          if (selectedDates.length === 0) {
            setModalError("Please choose at least one date from the calendar.");
            return;
          }

          for (const selectedDate of selectedDates) {
            const message = validateSelection(activeInput, selectedDate);
            if (message) {
              setModalError(message);
              return;
            }
          }

          setMultiSelectedDates(activeInput, selectedDates);
          activeInput.value = selectedDates[selectedDates.length - 1] || "";
          activeInput.dispatchEvent(new Event("input", { bubbles: true }));
          activeInput.dispatchEvent(new Event("change", { bubbles: true }));
          updateProxyValue(activeInput, activeProxy);
          setModalError("");
          modal.hide();
          return;
        }

        isoDate = String(calendarSelectedIso || "").trim();
        if (!isoDate) {
          setModalError("Please choose a date from the calendar.");
          return;
        }
      } else {
        const year = Number(yearSelect.value || 0);
        const month = Number(monthSelect.value || 0);
        const day = Number(daySelect.value || 0);
        if (!year || !month || !day) {
          setModalError("Please select month, day, and year.");
          return;
        }
        isoDate = toIsoDate(year, month, day);
      }

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
      if (isMultiCalendarInput(activeInput)) {
        calendarSelectedDates = [];
        setMultiSelectedDates(activeInput, []);
      }
      activeInput.value = "";
      activeInput.dispatchEvent(new Event("input", { bubbles: true }));
      activeInput.dispatchEvent(new Event("change", { bubbles: true }));
      updateProxyValue(activeInput, activeProxy);
      calendarSelectedIso = "";
      setModalError("");
      modal.hide();
    });

    modalEl.addEventListener("show.bs.modal", () => {
      window.requestAnimationFrame(() => {
        const backdrops = document.querySelectorAll("body > .modal-backdrop");
        const pickerBackdrop = backdrops[backdrops.length - 1];
        if (pickerBackdrop) {
          pickerBackdrop.classList.add("resident-date-modal-backdrop");
          pickerBackdrop.style.setProperty("z-index", "2070", "important");
        }
      });
    });

    modalEl.addEventListener("hidden.bs.modal", () => {
      document.querySelectorAll(".resident-date-modal-backdrop").forEach((backdrop) => {
        backdrop.classList.remove("resident-date-modal-backdrop");
        backdrop.style.removeProperty("z-index");
      });
      activeInput = null;
      activeProxy = null;
      calendarSelectedIso = "";
      calendarSelectedDates = [];
      setModalError("");
    });

    function enhanceDateInput(input) {
      if (!(input instanceof HTMLInputElement) || input.type !== "date" || input.readOnly || input.hasAttribute("data-date-modal-ignore")) {
        return;
      }

      applyPastOnlyDateConstraints(input);
      if (input.dataset.dateModalApplied === "1") return;
      input.dataset.dateModalApplied = "1";

      const proxyWrap = document.createElement("div");
      proxyWrap.className = "resident-date-proxy-wrap";

      const proxy = document.createElement("input");
      proxy.type = "text";
      const proxyClassList = String(input.className || "form-control")
        .split(/\s+/)
        .filter(Boolean)
        .filter((className) => ![
          "text-bg-light",
          "bg-light",
          "bg-light-subtle",
          "text-muted",
          "text-secondary"
        ].includes(className));
      if (!proxyClassList.includes("form-control")) {
        proxyClassList.unshift("form-control");
      }
      proxy.className = `${proxyClassList.join(" ")} resident-date-proxy`;
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
      const syncProxyState = () => {
        proxy.disabled = input.disabled;
        proxy.setAttribute("aria-disabled", input.disabled ? "true" : "false");
        proxy.classList.toggle("is-disabled", !!input.disabled);
        updateProxyValue(input, proxy);
      };

      patchInputInstanceProperty(input, "value", inputValueDescriptor, syncProxyState);
      patchInputInstanceProperty(input, "disabled", inputDisabledDescriptor, syncProxyState);
      syncProxyState();
      input.focus = () => {
        proxy.focus();
        openForInput(input, proxy);
      };

      input.addEventListener("input", syncProxyState);
      input.addEventListener("change", syncProxyState);
      proxy.addEventListener("click", () => openForInput(input, proxy));
      proxy.addEventListener("focus", () => openForInput(input, proxy));
      proxy.addEventListener("keydown", (event) => {
        if (event.key === "Enter" || event.key === " ") {
          event.preventDefault();
          openForInput(input, proxy);
        }
      });
    }

    function enhanceDateInputs(root = document) {
      if (root instanceof HTMLInputElement) {
        enhanceDateInput(root);
        return;
      }
      if (!(root instanceof Element || root instanceof Document)) {
        return;
      }

      root.querySelectorAll('input[type="date"]:not([readonly]):not([data-date-modal-ignore])').forEach(enhanceDateInput);
    }

    enhanceDateInputs(document);

    if (document.body) {
      const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
          mutation.addedNodes.forEach((node) => {
            if (node instanceof HTMLInputElement) {
              enhanceDateInput(node);
              return;
            }
            if (node instanceof Element) {
              enhanceDateInputs(node);
            }
          });
        });
      });
      observer.observe(document.body, { childList: true, subtree: true });
    }
  });
})();
