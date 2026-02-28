(() => {
  const el = (id) => document.getElementById(id);
  const viewerModalEl = el("modal-sectorDocViewer");
  const getViewerModal = () =>
    viewerModalEl ? bootstrap.Modal.getOrCreateInstance(viewerModalEl) : null;

  const state = {
    apps: [],
    filter: "ALL",
    search: "",
    currentPage: 1,
    entriesPerPage: 20,
    active: null, // currently opened application row
  };
  const pendingBadge = el("pendingSectorBadge");

  const entriesPerPageInput = el("sectorEntriesPerPageInput");
  const paginationEl = el("sectorPagination");
  if (entriesPerPageInput) {
    state.entriesPerPage = Math.max(1, Number.parseInt(entriesPerPageInput.value || "20", 10) || 20);
  }

  const sectorMap = {
    pwd: "PWD",
    seniorcitizen: "Senior Citizen",
    student: "Student",
    indigenouspeople: "Indigenous People",
    indigenousperson: "Indigenous People",
    singleparent: "Single Parent",
  };

  const normalizeSectorKey = (raw) =>
    String(raw || "")
      .trim()
      .toLowerCase()
      .replace(/[^a-z]/g, "");

	  const markerToSectorLabel = (marker) => {
	    const m = String(marker || "").trim();
	    if (!m.toLowerCase().startsWith("sector:")) return "";
	    const keyRawFull = m.slice("sector:".length).trim();
	    const keyRaw = keyRawFull.split(":")[0].trim();
	    const norm = normalizeSectorKey(keyRaw);
	    return sectorMap[norm] || keyRaw || "Sector";
	  };

  const extractMarker = (remarks) => String(remarks || "").split(";")[0].trim();

	  const extractReason = (remarks) => {
	    const s = String(remarks || "");
	    const idx = s.toLowerCase().indexOf("reason=");
	    if (idx === -1) return "";
	    return s.slice(idx + "reason=".length).trim();
	  };

	  const extractRejectedReasonFromApp = (app) => {
	    // Prefer any per-document remarks (server truth), fall back to app.remarks if present.
	    const docs = Array.isArray(app?.documents) ? app.documents : [];
	    for (const d of docs) {
	      const r = extractReason(d.remarks);
	      if (r) return r;
	    }
	    return extractReason(app?.remarks);
	  };

  const computeAgeFromBirthdate = (birthdate) => {
    const raw = String(birthdate || "").trim();
    if (!raw) return "—";
    const d = new Date(raw);
    if (Number.isNaN(d.getTime())) return "—";
    const today = new Date();
    let age = today.getFullYear() - d.getFullYear();
    const m = today.getMonth() - d.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < d.getDate())) age -= 1;
    return String(age);
  };

  const fmtStatus = (s) => {
    const raw = String(s || "").trim();
    if (!raw) return "PendingReview";
    const key = raw.toLowerCase().replace(/[\s_]+/g, "");
    if (key.startsWith("pending")) return "PendingReview";
    if (key === "verified" || key === "approve" || key === "approved") return "Verified";
    if (key === "rejected" || key === "denied" || key === "declined") return "Rejected";
    if (key === "archived") return "Archived";
    // Fall back to the original DB string (keeps future statuses visible).
    return raw;
  };

  const statusBadge = (status) => {
    const s = fmtStatus(status);
    const map = {
      PendingReview: { cls: "badge bg-warning text-dark", label: "Pending Review" },
      Verified: { cls: "badge bg-success", label: "Verified" },
      Rejected: { cls: "badge bg-danger", label: "Rejected" },
      Archived: { cls: "badge bg-secondary", label: "Archived" },
    };
    const meta = map[s] || { cls: "badge bg-secondary", label: s };
    const span = document.createElement("span");
    span.className = meta.cls;
    span.innerText = meta.label;
    return span;
  };

  const updatePendingCount = () => {
    if (!pendingBadge) return;
    const count = (state.apps || []).filter((a) => fmtStatus(a?.verify_status) === "PendingReview").length;
    pendingBadge.textContent = String(count);
    pendingBadge.classList.toggle("d-none", count <= 0);
  };

	  const makePreview = (fileUrl, fileName) => {
	    const url = String(fileUrl || "");
	    const name = String(fileName || "");
	    const ext = (name.split(".").pop() || "").toLowerCase();
	    const imageExts = ["jpg", "jpeg", "png", "webp", "gif", "bmp"];

    if (!url) {
      const div = document.createElement("div");
      div.className = "text-muted small";
      div.innerText = "No file preview available.";
      return div;
    }

    // Center the preview content inside the modal.
    const wrapper = document.createElement("div");
    wrapper.className = "d-flex justify-content-center";

    if (ext === "pdf") {
      const iframe = document.createElement("iframe");
      iframe.src = url;
      iframe.style.width = "100%";
      iframe.style.maxWidth = "1100px";
      iframe.style.height = "70vh";
      iframe.style.border = "1px solid #e9ecef";
      iframe.setAttribute("title", "PDF Preview");
      wrapper.appendChild(iframe);
      return wrapper;
    }

    if (imageExts.includes(ext)) {
      const img = document.createElement("img");
      img.src = url;
      img.alt = name || "Document";
      img.className = "img-fluid rounded border";
      img.style.maxWidth = "1100px";
      wrapper.appendChild(img);
      return wrapper;
    }

    const box = document.createElement("div");
    box.className = "border rounded p-3 text-center w-100";
    const txt = document.createElement("div");
    txt.className = "small text-muted mb-2";
    txt.innerText = "Preview not available for this file type.";
    const open = document.createElement("a");
    open.className = "btn btn-outline-primary btn-sm";
    open.href = url;
    open.target = "_blank";
    open.rel = "noopener";
    open.innerText = "Open File";
    box.appendChild(txt);
    box.appendChild(open);
    wrapper.appendChild(box);
    return wrapper;
  };

  const applyFilterAndSearch = (apps) => {
    const f = state.filter;
    const q = state.search.trim().toLowerCase();
    return (apps || []).filter((a) => {
      const status = fmtStatus(a.verify_status);
      if (f !== "ALL" && status !== f) return false;
      if (!q) return true;
      const sectorLabel = markerToSectorLabel(extractMarker(a.marker || a.remarks));
      const statusText = `${sectorLabel} ${status}`.toLowerCase();
      return (
        String(a.resident_id || "").toLowerCase().includes(q) ||
        String(a.full_name || "").toLowerCase().includes(q) ||
        String(a.sector_membership || "").toLowerCase().includes(q) ||
        String(sectorLabel || "").toLowerCase().includes(q) ||
        statusText.includes(q)
      );
    });
  };

  const renderTable = () => {
    const body = el("sectorTableBody");
    const loading = el("sectorAppsLoading");
    const empty = el("sectorAppsEmpty");
    if (!body) return;

    const rows = applyFilterAndSearch(state.apps);
    const totalPages = Math.max(1, Math.ceil(rows.length / state.entriesPerPage));
    if (state.currentPage > totalPages) state.currentPage = totalPages;
    if (state.currentPage < 1) state.currentPage = 1;
    const start = (state.currentPage - 1) * state.entriesPerPage;
    const pageRows = rows.slice(start, start + state.entriesPerPage);
    body.innerHTML = "";
    renderPagination(totalPages, rows.length);

    if (loading) loading.classList.add("d-none");
    if (empty) empty.classList.toggle("d-none", pageRows.length !== 0);

    pageRows.forEach((a) => {
      const tr = document.createElement("tr");

      const tdId = document.createElement("td");
      tdId.innerText = a.resident_id || "—";

      const tdName = document.createElement("td");
      tdName.innerText = a.full_name || "—";

      const tdSectorMembership = document.createElement("td");
      tdSectorMembership.innerText = markerToSectorLabel(extractMarker(a.marker || a.remarks)) || "—";

      const tdStatus = document.createElement("td");
      const sectorLabel = markerToSectorLabel(extractMarker(a.marker || a.remarks));
      const wrap = document.createElement("div");
      wrap.className = "d-flex flex-column gap-1";
      const statusLine = document.createElement("div");
      statusLine.appendChild(statusBadge(a.verify_status));
	      if (fmtStatus(a.verify_status) === "Rejected") {
	        const reason = extractRejectedReasonFromApp(a);
	        if (reason) {
	          const reasonEl = document.createElement("div");
	          reasonEl.className = "small text-muted";
	          reasonEl.innerText = `Reason: ${reason}`;
          wrap.appendChild(reasonEl);
        }
      }
      wrap.appendChild(statusLine);
      tdStatus.appendChild(wrap);

      const tdAction = document.createElement("td");
      const btn = document.createElement("button");
      btn.className = "btn btn-outline-primary btn-sm";
      btn.innerText = "View";
      btn.addEventListener("click", () => openViewer(a));
      tdAction.appendChild(btn);

      tr.appendChild(tdId);
      tr.appendChild(tdName);
      tr.appendChild(tdSectorMembership);
      tr.appendChild(tdStatus);
      tr.appendChild(tdAction);
      body.appendChild(tr);
    });
  };

  const renderPagination = (totalPages, totalRows) => {
    if (!paginationEl) return;
    paginationEl.innerHTML = "";

    const addBtn = (label, page, disabled = false, active = false) => {
      const li = document.createElement("li");
      li.className = `page-item${disabled ? " disabled" : ""}${active ? " active" : ""}`;
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = `page-link${active ? " fw-bold" : ""}`;
      btn.textContent = label;
      btn.disabled = disabled;
      btn.addEventListener("click", () => {
        if (disabled || page === state.currentPage) return;
        state.currentPage = page;
        renderTable();
      });
      li.appendChild(btn);
      paginationEl.appendChild(li);
    };

    if (totalRows <= 0) {
      addBtn("<", 1, true, false);
      addBtn("1", 1, false, true);
      addBtn(">", 1, true, false);
      return;
    }

    addBtn("<", Math.max(1, state.currentPage - 1), state.currentPage <= 1, false);
    let startPage = Math.max(1, state.currentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);
    for (let p = startPage; p <= endPage; p += 1) {
      addBtn(String(p), p, false, p === state.currentPage);
    }
    addBtn(">", Math.min(totalPages, state.currentPage + 1), state.currentPage >= totalPages, false);
  };

	  const openViewer = (app) => {
	    state.active = app;

	    const marker = extractMarker(app.marker || app.remarks);
	    const sectorLabel = markerToSectorLabel(marker);
	    const status = fmtStatus(app.verify_status);
	    const docs = Array.isArray(app.documents) ? app.documents : [];

	    const title = el("sector-docViewer-title");
	    const subtitle = el("sector-docViewer-subtitle");
	    const infoEl = el("sector-docViewer-info");
	    const body = el("sector-docViewer-body");
	    const actions = el("sector-docViewer-actions");

	    if (title) title.innerText = "Sector Membership Proof";
	    if (subtitle) subtitle.innerText = `Purpose: ${sectorLabel} Sector Membership Proof`;

	    if (infoEl) {
      const safeText = (v, fallback = "—") => {
        const s = String(v ?? "").trim();
        return s !== "" ? s : fallback;
      };

      const addressParts = [
        app.unit_number ? `Unit ${app.unit_number}` : "",
        app.house_number || "",
        app.street_name || "",
        app.phase_number || "",
        app.subdivision || "",
        "San Jose",
        app.area_number || "",
        "Rodriguez",
        "Rizal",
        "1860",
      ].filter(Boolean);

      const fullAddress = addressParts.join(", ") || "—";
      const ageValue = computeAgeFromBirthdate(app.birthdate);

      const addInfoRow = (gridEl, colClass, label, value) => {
        const col = document.createElement("div");
        col.className = colClass;
        const strong = document.createElement("strong");
        strong.innerText = `${label}: `;
        const span = document.createElement("span");
        span.innerText = safeText(value);
        col.appendChild(strong);
        col.appendChild(span);
        gridEl.appendChild(col);
      };

      infoEl.innerHTML = "";
      const heading = document.createElement("div");
      heading.className = "fw-bold fs-4 mb-1";
      heading.innerText = "Resident Basic Information";
      infoEl.appendChild(heading);

	      const grid = document.createElement("div");
	      grid.className = "row g-2";
	      addInfoRow(grid, "col-md-6", "Name", app.full_name);
	      addInfoRow(grid, "col-md-6", "Age", ageValue);
	      addInfoRow(grid, "col-md-6", "Sex", app.sex);
      const fmtBirthday = (() => {
        const raw = String(app.birthdate ?? "").trim();
        if (!raw) return "—";
        const d = new Date(raw);
        if (Number.isNaN(d.getTime())) return raw;
        const mm = String(d.getMonth() + 1).padStart(2, "0");
        const dd = String(d.getDate()).padStart(2, "0");
        const yyyy = String(d.getFullYear());
        return `${mm}/${dd}/${yyyy}`;
      })();
      addInfoRow(grid, "col-md-6", "Birthday", fmtBirthday);
	      addInfoRow(grid, "col-12", "Address", fullAddress);
	      addInfoRow(grid, "col-12", "Sector Membership", app.sector_membership);
	      const docType = docs.length ? (docs[0].document_type_name || docs[0].file_name || "") : "";
	      const uploaded = (() => {
	        if (!docs.length) return "";
	        // show the newest upload timestamp among shown documents
	        const ts = docs
	          .map((d) => String(d.upload_timestamp || "").trim())
	          .filter(Boolean)
	          .sort()
	          .pop();
	        return ts || "";
	      })();
	      addInfoRow(grid, "col-12", "Document Type", safeText(docType || "—"));
	      addInfoRow(grid, "col-12", "Uploaded", safeText(uploaded || "—"));

	      infoEl.appendChild(grid);
	    }

	    if (body) {
	      body.innerHTML = "";
	      if (!docs.length) {
	        body.appendChild(makePreview("", ""));
	      } else if (docs.length === 1) {
	        body.appendChild(makePreview(docs[0].file_url, docs[0].file_name));
	      } else {
	        const wrap = document.createElement("div");
	        wrap.className = "row g-3";
	        docs.forEach((d) => {
	          const col = document.createElement("div");
	          col.className = "col-12 col-lg-6";

	          const label = document.createElement("div");
	          label.className = "small text-muted mb-2 text-center";
	          const slot = String(d.slot || "").toLowerCase();
	          label.innerText = slot === "front" ? "Front" : slot === "back" ? "Back" : "Document";

	          col.appendChild(label);
	          col.appendChild(makePreview(d.file_url, d.file_name));
	          wrap.appendChild(col);
	        });
	        body.appendChild(wrap);
	      }
	    }

	    if (actions) {
	      actions.innerHTML = "";

      const approve = document.createElement("button");
      approve.className = "btn btn-success flex-fill";
      approve.innerText = "Verify";
      approve.disabled = status === "Verified";
      approve.addEventListener("click", () => showApproveConfirm());

      const deny = document.createElement("button");
      deny.className = "btn btn-danger flex-fill";
      deny.innerText = "Decline";
      deny.disabled = status === "Rejected";
      deny.addEventListener("click", () => showDenyConfirm());

      actions.appendChild(approve);
      actions.appendChild(deny);
    }

    const modalEl = el("modal-sectorDocViewer");
	    if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
	  };

	  const postUpdateStatus = async (attachmentId, newStatus, reasonText = "") => {
	    const form = new FormData();
	    form.append("update_document_status", "1");
	    form.append("attachment_id", String(attachmentId));
	    form.append("new_status", newStatus); // APPROVED | DENIED | PENDING
	    if (newStatus === "DENIED") {
      form.append("reason_scope", "sector_membership");
      form.append("reason_text", reasonText);
    }

    const res = await fetch("../PhpFiles/Admin-End/residentMasterlist.php", {
      method: "POST",
      body: form,
      headers: { Accept: "application/json" },
    });
    const data = await res.json().catch(() => null);
    if (!res.ok || !data || !data.success) {
      throw new Error((data && data.message) || "Failed to update document status.");
    }
    return data;
  };

	  const showApproveConfirm = () => {
    const modalEl = el("modal-sectorApproveConfirm");
    if (!modalEl) return;
    const viewerModal = getViewerModal();
    if (viewerModal) {
      viewerModal.hide();
    }

    const cancel = el("btn-sectorApproveCancel");
    const confirm = el("btn-sectorApproveConfirm");
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    const onCancel = () => {
      modal.hide();
      if (state.active) {
        openViewer(state.active);
      }
    };
	    const onConfirm = async () => {
	      if (!state.active) return;
	      try {
	        confirm.disabled = true;
	        const ids = Array.isArray(state.active.attachment_ids) && state.active.attachment_ids.length
	          ? state.active.attachment_ids
	          : [state.active.attachment_id];
	        let updatedSectorMembership = null;
	        for (const id of ids) {
	          const data = await postUpdateStatus(id, "APPROVED");
	          if (data && data.sector_membership) updatedSectorMembership = data.sector_membership;
	        }
	        applyStatusUpdate("Verified", updatedSectorMembership);
	        modal.hide();
	      } catch (e) {
	        alert(e.message || String(e));
	      } finally {
	        confirm.disabled = false;
      }
    };

    cancel.onclick = onCancel;
    confirm.onclick = onConfirm;
    modal.show();
  };

	  const showDenyConfirm = () => {
    const modalEl = el("modal-sectorDenyConfirm");
    if (!modalEl) return;
    const viewerModal = getViewerModal();
    if (viewerModal) {
      viewerModal.hide();
    }

    const reason = el("txt-sectorDenyReason");
    const err = el("txt-sectorDenyReasonError");
    const cancel = el("btn-sectorDenyCancel");
    const confirm = el("btn-sectorDenyConfirm");
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    const resetValidation = () => {
      if (err) err.classList.add("d-none");
    };

    const onCancel = () => {
      modal.hide();
      if (reason) reason.value = "";
      resetValidation();
      if (state.active) {
        openViewer(state.active);
      }
    };

	    const onConfirm = async () => {
	      if (!state.active) return;
	      const txt = String(reason && reason.value ? reason.value : "").trim();
      if (!txt) {
        if (err) err.classList.remove("d-none");
        return;
      }
	      try {
	        confirm.disabled = true;
	        const ids = Array.isArray(state.active.attachment_ids) && state.active.attachment_ids.length
	          ? state.active.attachment_ids
	          : [state.active.attachment_id];
	        for (const id of ids) {
	          await postUpdateStatus(id, "DENIED", txt);
	        }
	        applyStatusUpdate("Rejected", null, txt);
	        modal.hide();
	        if (reason) reason.value = "";
	        resetValidation();
      } catch (e) {
        alert(e.message || String(e));
      } finally {
        confirm.disabled = false;
      }
    };

    cancel.onclick = onCancel;
    confirm.onclick = onConfirm;
    resetValidation();
    modal.show();
  };

  const applyStatusUpdate = (newVerifyStatus, updatedSectorMembership = null, deniedReason = "") => {
    const a = state.active;
    if (!a) return;

    a.verify_status = newVerifyStatus;
    if (updatedSectorMembership) {
      a.sector_membership = updatedSectorMembership;
    }
    if (newVerifyStatus === "Rejected") {
      const marker = extractMarker(a.marker || a.remarks);
      a.remarks = deniedReason ? `${marker}; reason=${deniedReason}` : marker;
    }

    // Re-open viewer with updated status, then rerender table.
    updatePendingCount();
    renderTable();
    openViewer(a);
  };

  const loadApps = async () => {
    const loading = el("sectorAppsLoading");
    const empty = el("sectorAppsEmpty");
    if (loading) loading.classList.remove("d-none");
    if (empty) empty.classList.add("d-none");

    const res = await fetch("../PhpFiles/Admin-End/sectorMembershipVerification.php?fetch_sector_applications=1", {
      headers: { Accept: "application/json" },
    });
    const data = await res.json().catch(() => null);
    if (!res.ok || !data || !data.success) {
      if (loading) loading.classList.add("d-none");
      alert((data && data.message) || "Failed to load sector membership applications.");
      return;
    }

    state.apps = Array.isArray(data.data) ? data.data : [];
    updatePendingCount();
    renderTable();
  };

  // ========================
  // AUTO REFRESH + MANUAL REFRESH (60s)
  // ========================
  const AUTO_REFRESH_SECONDS = 60;
  let autoRefreshSecondsLeft = AUTO_REFRESH_SECONDS;
  let autoRefreshInterval = null;
  let autoRefreshInFlight = false;
  const btnRefreshTable = el("btnSectorAppsRefresh");
  const countdownEl = el("sectorAppsAutoRefreshCountdown");

  const setRefreshLoading = (on) => {
    if (!btnRefreshTable) return;
    btnRefreshTable.classList.toggle("is-loading", !!on);
    btnRefreshTable.disabled = !!on;
  };

  const renderCountdown = () => {
    if (!countdownEl) return;
    countdownEl.textContent = autoRefreshSecondsLeft > 0 ? `Auto refresh in ${autoRefreshSecondsLeft}s` : "";
  };

  const resetCountdown = () => {
    autoRefreshSecondsLeft = AUTO_REFRESH_SECONDS;
    renderCountdown();
  };

  const triggerRefresh = async () => {
    if (autoRefreshInFlight) return;
    autoRefreshInFlight = true;
    setRefreshLoading(true);
    try {
      resetCountdown();
      await loadApps();
    } finally {
      autoRefreshInFlight = false;
      setRefreshLoading(false);
    }
  };

  const startAutoRefresh = () => {
    renderCountdown();
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
    autoRefreshInterval = setInterval(() => {
      if (autoRefreshInFlight) return;
      autoRefreshSecondsLeft -= 1;
      if (autoRefreshSecondsLeft <= 0) {
        triggerRefresh().catch(() => {});
        return;
      }
      renderCountdown();
    }, 1000);
  };

  const wireUI = () => {
    const search = el("searchInput");
    if (search) {
      search.addEventListener("input", () => {
        state.search = search.value || "";
        state.currentPage = 1;
        renderTable();
      });
    }

    if (entriesPerPageInput) {
      entriesPerPageInput.addEventListener("change", () => {
        const next = Math.max(1, Number.parseInt(entriesPerPageInput.value || "20", 10) || 20);
        state.entriesPerPage = next;
        entriesPerPageInput.value = String(next);
        state.currentPage = 1;
        renderTable();
      });
    }

    document.querySelectorAll(".filter-btn").forEach((b) => {
      b.addEventListener("click", () => {
        document.querySelectorAll(".filter-btn").forEach((x) => x.classList.remove("active"));
        b.classList.add("active");
        state.filter = b.dataset.filter || "ALL";
        state.currentPage = 1;
        renderTable();
      });
    });

    if (btnRefreshTable) {
      btnRefreshTable.addEventListener("click", () => {
        triggerRefresh().catch(() => {});
      });
    }
  };

  document.addEventListener("DOMContentLoaded", () => {
    wireUI();
    triggerRefresh().catch(() => {});
    startAutoRefresh();
  });
})();
