(() => {
  const cfg = window.ADMIN_TABLE_COLUMNS_CONFIG || null;
  if (!cfg) return;

  const table = document.querySelector(cfg.tableSelector || "");
  const modalEl = document.getElementById(cfg.modalId || "modalTableColumns");
  const listEl = document.getElementById(cfg.listId || "tableColumnsList");
  const resetBtn = document.getElementById(cfg.resetBtnId || "btnTableColumnsReset");

  if (!table || !modalEl || !listEl) return;

  const storageKey =
    cfg.storageKey ||
    `admin_cols:${location.pathname}:${cfg.tableSelector || "table"}`;

  const getThs = () =>
    Array.from(table.querySelectorAll("thead th")).map((th) => ({
      th,
      label: String(th.innerText || "").trim() || "Column",
    }));

  const loadHiddenIdx = () => {
    try {
      const raw = window.localStorage.getItem(storageKey);
      const parsed = raw ? JSON.parse(raw) : (Array.isArray(cfg.defaultHiddenIdxs) ? cfg.defaultHiddenIdxs : []);
      if (!Array.isArray(parsed)) return [];
      return parsed
        .map((n) => Number(n))
        .filter((n) => Number.isInteger(n) && n >= 0);
    } catch {
      return [];
    }
  };

  const saveHiddenIdx = (idxs) => {
    try {
      window.localStorage.setItem(storageKey, JSON.stringify(idxs));
    } catch {
      // ignore
    }
  };

  const applyHidden = (hiddenIdxs) => {
    const hidden = new Set(hiddenIdxs || []);
    const rows = table.querySelectorAll("tr");
    rows.forEach((tr) => {
      const cells = Array.from(tr.children);
      cells.forEach((cell, idx) => {
        cell.style.display = hidden.has(idx) ? "none" : "";
      });
    });
  };

  const renderModal = () => {
    const cols = getThs();
    const hidden = new Set(loadHiddenIdx());

    listEl.innerHTML = "";

    cols.forEach((c, idx) => {
      const col = document.createElement("div");
      col.className = "col-12 col-md-6 col-lg-4";

      const wrap = document.createElement("label");
      wrap.className = "table-columns-check d-flex align-items-center gap-2 w-100";

      const cb = document.createElement("input");
      cb.type = "checkbox";
      cb.className = "form-check-input m-0";
      cb.checked = !hidden.has(idx);
      cb.dataset.colIndex = String(idx);

      const text = document.createElement("div");
      text.className = "fw-semibold";
      text.innerText = c.label;

      cb.addEventListener("change", () => {
        const currentHidden = new Set(loadHiddenIdx());
        if (cb.checked) currentHidden.delete(idx);
        else currentHidden.add(idx);
        const next = Array.from(currentHidden).sort((a, b) => a - b);
        saveHiddenIdx(next);
        applyHidden(next);
      });

      wrap.appendChild(cb);
      wrap.appendChild(text);
      col.appendChild(wrap);
      listEl.appendChild(col);
    });
  };

  const applyFromStorage = () => applyHidden(loadHiddenIdx());

  // Keep settings applied even when table is re-rendered by JS.
  const tbody = table.querySelector("tbody");
  if (tbody && window.MutationObserver) {
    const obs = new MutationObserver(() => applyFromStorage());
    obs.observe(tbody, { childList: true, subtree: true });
  }

  if (window.bootstrap?.Modal) {
    modalEl.addEventListener("show.bs.modal", () => {
      renderModal();
    });
  }

  if (resetBtn) {
    resetBtn.addEventListener("click", () => {
      saveHiddenIdx([]);
      applyFromStorage();
      renderModal();
    });
  }

  applyFromStorage();
})();
