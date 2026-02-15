document.addEventListener("DOMContentLoaded", () => {
  const pane = document.getElementById("pane-uploaded-docs");
  if (!pane) return;

  const btnRefresh = document.getElementById("btnRefreshUploadedDocs");
  const loadingEl = document.getElementById("uploadedDocsLoading");
  const errorEl = document.getElementById("uploadedDocsError");
  const emptyEl = document.getElementById("uploadedDocsEmpty");
  const tableWrap = document.getElementById("uploadedDocsTableWrap");
  const tbody = document.getElementById("uploadedDocsTbody");

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
      showTable(false);
      showEmpty(true);
      tbody.innerHTML = "";
      return;
    }

    showEmpty(false);
    showTable(true);
    tbody.innerHTML = list
      .map((r) => {
        const doc = esc(r.document_type_name || r.file_name || "—");
        const cat = esc(r.document_category || "—");
        const uploaded = esc(fmtDate(r.upload_timestamp));
        const idno = esc(r.id_number || "—");
        const url = String(r.public_url || "").trim();
        const fileCell = url
          ? `<button type="button" class="btn btn-outline-primary btn-sm btn-udv-view" data-attachment-id="${esc(
              r.attachment_id
            )}">View</button>`
          : `<span class="text-muted small">Unavailable</span>`;

        return `
          <tr>
            <td>
              <div class="fw-semibold">${doc}</div>
              <div class="text-muted small">${esc(r.file_type || "")}</div>
            </td>
            <td>${cat}</td>
            <td>${uploaded}</td>
            <td>${idno}</td>
            <td>${fileCell}</td>
          </tr>
        `;
      })
      .join("");
  };

  const openViewer = (row) => {
    const modalEl = document.getElementById("modalUploadedDocViewer");
    const bodyEl = document.getElementById("udvBody");
    const titleEl = document.getElementById("udvTitle");
    const subtitleEl = document.getElementById("udvSubtitle");
    const openNewTab = document.getElementById("udvOpenNewTab");

    if (!modalEl || !bodyEl || !window.bootstrap?.Modal) return;

    const url = String(row?.public_url || "").trim();
    const extRaw = String(row?.file_type || "").toLowerCase();
    const ext = extRaw || (url.split(".").pop() || "").toLowerCase();

    const docName = String(row?.document_type_name || row?.file_name || "Document");
    const cat = String(row?.document_category || "");
    const uploaded = fmtDate(row?.upload_timestamp);
    const idno = String(row?.id_number || "").trim();

    if (titleEl) titleEl.textContent = docName;
    if (subtitleEl) {
      const parts = [];
      if (cat) parts.push(`Category: ${cat}`);
      if (uploaded && uploaded !== "—") parts.push(`Uploaded: ${uploaded}`);
      if (idno) parts.push(`ID: ${idno}`);
      subtitleEl.textContent = parts.join(" | ");
    }

    bodyEl.innerHTML = "";

    const createPreviewElement = () => {
      if (!url) {
        const div = document.createElement("div");
        div.className = "text-muted";
        div.textContent = "File is unavailable.";
        return div;
      }

      if (["jpg", "jpeg", "png", "webp", "gif"].includes(ext)) {
        const img = document.createElement("img");
        img.src = url;
        img.alt = docName;
        img.className = "img-fluid d-block mx-auto";
        return img;
      }

      if (ext === "pdf") {
        const iframe = document.createElement("iframe");
        iframe.src = url;
        iframe.className = "w-100";
        iframe.style.height = "70vh";
        iframe.title = docName;
        return iframe;
      }

      const div = document.createElement("div");
      div.className = "text-muted";
      div.textContent = "Preview not available for this file type.";
      return div;
    };

    bodyEl.appendChild(createPreviewElement());

    if (openNewTab) {
      if (url) {
        openNewTab.href = url;
        openNewTab.classList.remove("d-none");
      } else {
        openNewTab.href = "#";
        openNewTab.classList.add("d-none");
      }
    }

    bootstrap.Modal.getOrCreateInstance(modalEl).show();
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

  // Load only when the tab is opened (and refresh manually if needed).
  const tabBtn = document.getElementById("tab-uploaded-docs");
  tabBtn?.addEventListener("shown.bs.tab", () => {
    if (!loadedOnce) load().catch(() => {});
  });
});
