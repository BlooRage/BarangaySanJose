document.addEventListener("DOMContentLoaded", () => {
  const pane = document.getElementById("pane-uploaded-docs");
  if (!pane) return;

  const btnRefresh = document.getElementById("btnRefreshUploadedDocs");
  const loadingEl = document.getElementById("uploadedDocsLoading");
  const errorEl = document.getElementById("uploadedDocsError");
  const emptyEl = document.getElementById("uploadedDocsEmpty");
  const tableWrap = document.getElementById("uploadedDocsTableWrap");
  const tbody = document.getElementById("uploadedDocsTbody");
  const cardsWrap = document.getElementById("uploadedDocsCards");
  const countEl = document.getElementById("uploadedDocsCount");

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

  const setLoading = (on) => {
    if (loadingEl) loadingEl.classList.toggle("d-none", !on);
  };

  const showEmpty = (on) => {
    if (emptyEl) emptyEl.classList.toggle("d-none", !on);
  };

  const showTable = (on) => {
    if (tableWrap) tableWrap.classList.toggle("d-none", !on);
  };

  const setCount = (value) => {
    if (countEl) countEl.textContent = String(value ?? 0);
  };

  const esc = (s) => {
    const t = String(s ?? "");
    return t
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  };

  const fmtDate = (iso) => {
    const v = String(iso ?? "");
    if (!v) return "—";
    const d = new Date(v);
    if (Number.isNaN(d.getTime())) return v;
    return d.toLocaleString();
  };

  let loadedOnce = false;
  let inFlight = false;

  const render = (rows) => {
    if (!tbody) return;
    const list = Array.isArray(rows) ? rows : [];
    // Save for the viewer modal
    pane.dataset.udvRows = JSON.stringify(list);

    if (!list.length) {
      setCount(0);
      showTable(false);
      showEmpty(true);
      tbody.innerHTML = "";
      if (cardsWrap) cardsWrap.innerHTML = "";
      return;
    }

    setCount(list.length);
    showEmpty(false);
    showTable(true);
    tbody.innerHTML = list
      .map((r) => {
        const doc = esc(r.document_type_name || r.file_name || "—");
        const cat = esc(r.document_category || "—");
        const uploaded = esc(fmtDate(r.upload_timestamp));
        const idno = esc(r.id_number || "—");
        const url = String(r.viewer_url || r.public_url || "").trim();
        const fileCell = url
          ? `<span class="uploaded-doc-actions"><button type="button" class="btn btn-sm btn-udv-view" data-attachment-id="${esc(
              r.attachment_id
            )}"><i class="fa-regular fa-eye"></i><span>View</span></button></span>`
          : `<span class="text-muted small">Unavailable</span>`;

        return `
          <tr>
            <td>
              <div class="uploaded-doc-name">${doc}</div>
              <div class="uploaded-doc-type">${esc(r.file_type || "")}</div>
            </td>
            <td><div class="uploaded-doc-category">${cat}</div></td>
            <td>${uploaded}</td>
            <td><span class="uploaded-doc-id">${idno}</span></td>
            <td>${fileCell}</td>
          </tr>
        `;
      })
      .join("");

    if (cardsWrap) {
      cardsWrap.innerHTML = list
        .map((r) => {
          const doc = esc(r.document_type_name || r.file_name || "—");
          const cat = esc(r.document_category || "—");
          const uploaded = esc(fmtDate(r.upload_timestamp));
          const idno = esc(r.id_number || "—");
          const url = String(r.viewer_url || r.public_url || "").trim();
          const action = url
            ? `<button type="button" class="btn btn-sm btn-udv-view w-100" data-attachment-id="${esc(r.attachment_id)}"><i class="fa-regular fa-eye"></i><span>View</span></button>`
            : `<span class="text-muted small">Unavailable</span>`;

          return `
            <article class="uploaded-doc-card">
              <div class="uploaded-doc-card-header">
                <div>
                  <div class="uploaded-doc-name">${doc}</div>
                  <div class="uploaded-doc-type mt-1">${esc(r.file_type || "")}</div>
                </div>
              </div>
              <div class="uploaded-doc-card-meta">
                <div>
                  <div class="uploaded-doc-label">Category</div>
                  <div class="uploaded-doc-value">${cat}</div>
                </div>
                <div>
                  <div class="uploaded-doc-label">Uploaded</div>
                  <div class="uploaded-doc-value">${uploaded}</div>
                </div>
                <div>
                  <div class="uploaded-doc-label">ID Number</div>
                  <div class="uploaded-doc-value">${idno}</div>
                </div>
              </div>
              <div class="mt-3">${action}</div>
            </article>
          `;
        })
        .join("");
    }
  };

  const openViewer = (row) => {
    const modalEl = document.getElementById("modalUploadedDocViewer");
    const bodyEl = document.getElementById("udvBody");
    const titleEl = document.getElementById("udvTitle");
    const openNewTab = document.getElementById("udvOpenNewTab");

    if (!modalEl || !bodyEl || !window.bootstrap?.Modal) return;

    const url = String(row?.viewer_url || row?.public_url || "").trim();
    const openUrl = String(row?.open_url || row?.viewer_url || row?.public_url || "").trim();
    const extRaw = String(row?.file_type || "").toLowerCase();
    const ext = extRaw || (url.split(".").pop() || "").toLowerCase();

    const docName = String(row?.document_type_name || row?.file_name || "Document");
    const idno = String(row?.id_number || "").trim();

    if (titleEl) titleEl.textContent = idno ? `${docName} - ${idno}` : docName;

    bodyEl.innerHTML = "";

    const createPreviewElement = () => {
      if (!url) {
        const div = document.createElement("div");
        div.className = "uploaded-doc-preview-empty";
        div.textContent = "File is unavailable.";
        return div;
      }

      if (["jpg", "jpeg", "png", "webp", "gif"].includes(ext)) {
        const img = document.createElement("img");
        img.src = url;
        img.alt = docName;
        img.className = "img-fluid d-block mx-auto uploaded-doc-preview-image";
        return img;
      }

      if (ext === "pdf") {
        const iframe = document.createElement("iframe");
        iframe.src = url.includes("_ts=") ? url : `${url}${url.includes("?") ? "&" : "?"}_ts=${Date.now()}`;
        iframe.className = "uploaded-doc-preview-frame";
        iframe.title = docName;
        return iframe;
      }

      const div = document.createElement("div");
      div.className = "uploaded-doc-preview-empty";
      div.textContent = "Preview not available for this file type.";
      return div;
    };

    bodyEl.appendChild(createPreviewElement());

    if (openNewTab) {
      if (openUrl) {
        openNewTab.href = openUrl;
        openNewTab.classList.remove("d-none");
      } else {
        openNewTab.href = "#";
        openNewTab.classList.add("d-none");
      }
    }

    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  };

  const resetViewer = () => {
    const bodyEl = document.getElementById("udvBody");
    const titleEl = document.getElementById("udvTitle");
    const openNewTab = document.getElementById("udvOpenNewTab");
    if (bodyEl) bodyEl.innerHTML = "";
    if (titleEl) titleEl.textContent = "Document Preview";
    if (openNewTab) {
      openNewTab.href = "#";
      openNewTab.classList.add("d-none");
    }
  };

  const load = async () => {
    if (inFlight) return;
    inFlight = true;
    setError("");
    showEmpty(false);
    showTable(false);
    setLoading(true);

    try {
      const res = await fetch("../PhpFiles/Resident-End/getVerifiedUploadedDocuments.php", {
        headers: { Accept: "application/json" },
      });
      const text = await res.text();
      let data = null;
      try {
        data = text ? JSON.parse(text) : null;
      } catch {
        data = null;
      }

      if (!res.ok || !data || !data.success) {
        const msg = (data && (data.message || data.error)) || "Failed to load documents.";
        throw new Error(msg);
      }

      render(data.data || []);
      loadedOnce = true;
    } catch (e) {
      setError(e?.message || "Failed to load documents.");
      render([]);
    } finally {
      setLoading(false);
      inFlight = false;
    }
  };

  btnRefresh?.addEventListener("click", () => {
    load().catch(() => {});
  });

  // Delegate click for "View" buttons
  pane.addEventListener("click", (e) => {
    const btn = e.target?.closest?.(".btn-udv-view");
    if (!btn) return;
    const id = String(btn.getAttribute("data-attachment-id") || "").trim();
    if (!id) return;

    let rows = [];
    try {
      rows = JSON.parse(pane.dataset.udvRows || "[]");
    } catch {
      rows = [];
    }
    const row = rows.find((r) => String(r.attachment_id) === id);
    if (!row) return;
    openViewer(row);
  });

  document.getElementById("modalUploadedDocViewer")?.addEventListener("hidden.bs.modal", () => {
    resetViewer();
  });

  // Load only when the tab is opened (and refresh manually if needed).
  const tabBtn = document.getElementById("tab-uploaded-docs");
  tabBtn?.addEventListener("shown.bs.tab", () => {
    if (!loadedOnce) load().catch(() => {});
  });
});
