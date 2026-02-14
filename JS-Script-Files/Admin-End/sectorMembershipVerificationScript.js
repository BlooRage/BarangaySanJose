(() => {
  const el = (id) => document.getElementById(id);

  const state = {
    apps: [],
    filter: "ALL",
    search: "",
    active: null, // currently opened application row
  };

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
    const keyRaw = m.slice("sector:".length).trim();
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

  const makePreview = (fileUrl, fileName) => {
    const url = String(fileUrl || "");
    const name = String(fileName || "");
    const ext = (name.split(".").pop() || "").toLowerCase();

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

    // images fallback
    const img = document.createElement("img");
    img.src = url;
    img.alt = name || "Document";
    img.className = "img-fluid rounded border";
    img.style.maxWidth = "1100px";
    wrapper.appendChild(img);
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
    body.innerHTML = "";

    if (loading) loading.classList.add("d-none");
    if (empty) empty.classList.toggle("d-none", rows.length !== 0);

    rows.forEach((a) => {
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
        const reason = extractReason(a.remarks);
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

  const openViewer = (app) => {
    state.active = app;

    const marker = extractMarker(app.marker || app.remarks);
    const sectorLabel = markerToSectorLabel(marker);
    const status = fmtStatus(app.verify_status);

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
      addInfoRow(grid, "col-12", "Document Type", safeText(app.document_type_name || app.file_name || "—"));
      addInfoRow(grid, "col-12", "Uploaded", safeText(app.upload_timestamp));

      infoEl.appendChild(grid);
    }

    if (body) {
      body.innerHTML = "";
      body.appendChild(makePreview(app.file_url, app.file_name));
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

    const cancel = el("btn-sectorApproveCancel");
    const confirm = el("btn-sectorApproveConfirm");
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    const onCancel = () => modal.hide();
    const onConfirm = async () => {
      if (!state.active) return;
      try {
        confirm.disabled = true;
        const data = await postUpdateStatus(state.active.attachment_id, "APPROVED");
        applyStatusUpdate("Verified", data.sector_membership || null);
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
        await postUpdateStatus(state.active.attachment_id, "DENIED", txt);
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
    renderTable();
  };

  const wireUI = () => {
    const search = el("searchInput");
    if (search) {
      search.addEventListener("input", () => {
        state.search = search.value || "";
        renderTable();
      });
    }

    document.querySelectorAll(".filter-btn").forEach((b) => {
      b.addEventListener("click", () => {
        document.querySelectorAll(".filter-btn").forEach((x) => x.classList.remove("active"));
        b.classList.add("active");
        state.filter = b.dataset.filter || "ALL";
        renderTable();
      });
    });
  };

  document.addEventListener("DOMContentLoaded", () => {
    wireUI();
    loadApps();
  });
})();
