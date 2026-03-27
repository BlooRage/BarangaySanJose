(() => {
  const el = (id) => document.getElementById(id);
  const bodyEl = el("householdMemberVerificationBody");
  const loadingEl = el("householdMemberVerificationLoading");
  const emptyEl = el("householdMemberVerificationEmpty");
  const searchEl = el("hmvSearchInput");
  const pendingBadgeEl = el("pendingHouseholdMemberBadge");
  const refreshBtn = el("btnHouseholdMemberVerificationRefresh");
  const modalEl = el("modal-householdMemberVerification");
  const modal = modalEl ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;

  const state = {
    rows: [],
    filter: "ALL",
    search: "",
    active: null,
  };

  const fmtStatus = (status) => {
    const raw = String(status || "").trim();
    if (!raw) return "PendingReview";
    const key = raw.toLowerCase().replace(/[\s_]+/g, "");
    if (key === "approved" || key === "verified") return "Approved";
    if (key === "rejected" || key === "declined" || key === "denied") return "Rejected";
    return "PendingReview";
  };

  const statusPillHtml = (status) => {
    const normalized = fmtStatus(status);
    const classMap = {
      PendingReview: "status-pill pending",
      Approved: "status-pill approved",
      Rejected: "status-pill denied",
    };
    const labelMap = {
      PendingReview: "Pending Review",
      Approved: "Approved",
      Rejected: "Rejected",
    };
    return `<span class="${classMap[normalized] || classMap.PendingReview}">${labelMap[normalized] || normalized}</span>`;
  };

  const escapeHtml = (value) =>
    String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");

  const formatDate = (value) => {
    const raw = String(value || "").trim();
    if (!raw) return "—";
    const date = new Date(raw);
    if (Number.isNaN(date.getTime())) return raw;
    return date.toLocaleString("en-PH", {
      year: "numeric",
      month: "short",
      day: "numeric",
      hour: "numeric",
      minute: "2-digit",
    });
  };

  const formatBirthdate = (value) => {
    const raw = String(value || "").trim();
    if (!raw) return "—";
    const date = new Date(raw);
    if (Number.isNaN(date.getTime())) return raw;
    return date.toLocaleDateString("en-PH", { year: "numeric", month: "short", day: "numeric" });
  };

  const renderDocumentPreview = (row) => {
    const wrap = el("hmvModalDocumentWrap");
    if (!wrap) return;
    const url = String(row?.file_url || "").trim();
    const fileName = String(row?.file_name || "").trim();
    const ext = fileName.split(".").pop()?.toLowerCase() || "";

    if (!url) {
      wrap.innerHTML = `<div class="text-muted small">No birth certificate file is attached.</div>`;
      return;
    }

    if (ext === "pdf") {
      wrap.innerHTML = `<iframe src="${escapeHtml(url)}" title="Birth Certificate Preview" style="width:100%;height:70vh;border:0;"></iframe>`;
      return;
    }

    if (["jpg", "jpeg", "png", "webp"].includes(ext)) {
      wrap.innerHTML = `<div class="text-center"><img src="${escapeHtml(url)}" alt="Birth Certificate" class="img-fluid rounded border"></div>`;
      return;
    }

    wrap.innerHTML = `
      <div class="text-center">
        <div class="text-muted small mb-2">Preview not available for this file type.</div>
        <a class="btn btn-outline-primary btn-sm" href="${escapeHtml(url)}" target="_blank" rel="noopener">Open File</a>
      </div>
    `;
  };

  const filteredRows = () => {
    const q = state.search.trim().toLowerCase();
    return state.rows.filter((row) => {
      const status = fmtStatus(row.status);
      if (state.filter !== "ALL" && status !== state.filter) return false;
      if (!q) return true;
      const haystack = [
        row.request_id,
        row.head_full_name,
        row.fam_head_id,
        row.member_full_name,
        row.birthdate,
      ].join(" ").toLowerCase();
      return haystack.includes(q);
    });
  };

  const updatePendingBadge = () => {
    if (!pendingBadgeEl) return;
    const count = state.rows.filter((row) => fmtStatus(row.status) === "PendingReview").length;
    pendingBadgeEl.textContent = String(count);
    pendingBadgeEl.classList.toggle("d-none", count <= 0);
  };

  const renderTable = () => {
    if (!bodyEl) return;
    const rows = filteredRows();
    if (loadingEl) loadingEl.classList.add("d-none");
    emptyEl?.classList.toggle("d-none", rows.length > 0);

    if (!rows.length) {
      bodyEl.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">No requests found.</td></tr>`;
      return;
    }

    bodyEl.innerHTML = rows.map((row) => `
      <tr>
        <td>${escapeHtml(row.request_id)}</td>
        <td>${escapeHtml(row.head_full_name || "—")}<div class="small text-muted">${escapeHtml(row.fam_head_id || "")}</div></td>
        <td>${escapeHtml(row.member_full_name || "—")}</td>
        <td>${escapeHtml(formatBirthdate(row.birthdate))}</td>
        <td>${statusPillHtml(row.status)}</td>
        <td>${escapeHtml(formatDate(row.submitted_at))}</td>
        <td><button class="btn btn-outline-primary btn-sm" data-request-id="${escapeHtml(row.request_id)}">View</button></td>
      </tr>
    `).join("");
  };

  const openModal = (row) => {
    state.active = row;
    el("hmvModalSubtitle").textContent = `Request ID ${row.request_id}`;
    el("hmvModalHeadName").textContent = row.head_full_name || "—";
    el("hmvModalHeadResidentId").textContent = row.fam_head_id || "—";
    el("hmvModalLastName").textContent = row.last_name || "—";
    el("hmvModalFirstName").textContent = row.first_name || "—";
    el("hmvModalMiddleName").textContent = row.middle_name || "—";
    el("hmvModalSuffix").textContent = row.suffix || "—";
    el("hmvModalBirthdate").textContent = formatBirthdate(row.birthdate);
    el("hmvModalStatus").innerHTML = statusPillHtml(row.status);
    el("hmvReviewRemarks").value = row.review_remarks || "";
    renderDocumentPreview(row);

    const isPending = fmtStatus(row.status) === "PendingReview";
    el("hmvModalActions")?.classList.toggle("d-none", !isPending);
    modal?.show();
  };

  const fetchRows = async () => {
    if (loadingEl) loadingEl.classList.remove("d-none");
    try {
      const res = await fetch("../PhpFiles/Admin-End/householdMemberVerification.php?fetch_member_requests=1", {
        credentials: "same-origin",
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.success) throw new Error(data.message || "Failed to load requests.");
      state.rows = Array.isArray(data.data) ? data.data : [];
      updatePendingBadge();
      renderTable();
    } catch (error) {
      if (loadingEl) loadingEl.classList.add("d-none");
      if (bodyEl) {
        bodyEl.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${escapeHtml(error?.message || "Failed to load requests.")}</td></tr>`;
      }
      emptyEl?.classList.add("d-none");
    }
  };

  const submitReview = async (action) => {
    const row = state.active;
    if (!row) return;
    const reviewRemarks = String(el("hmvReviewRemarks")?.value || "").trim();
    try {
      const res = await fetch("../PhpFiles/Admin-End/householdMemberVerification.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({
          action,
          request_id: row.request_id,
          review_remarks: reviewRemarks,
        }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.success) throw new Error(data.message || "Failed to review request.");
      modal?.hide();
      await fetchRows();
    } catch (error) {
      window.alert(error?.message || "Failed to review request.");
    }
  };

  document.querySelectorAll(".hmv-filter-btn").forEach((button) => {
    button.addEventListener("click", () => {
      document.querySelectorAll(".hmv-filter-btn").forEach((btn) => btn.classList.remove("active", "btn-outline-primary"));
      document.querySelectorAll(".hmv-filter-btn").forEach((btn) => btn.classList.add("btn-outline-secondary"));
      button.classList.add("active", "btn-outline-primary");
      button.classList.remove("btn-outline-secondary");
      state.filter = String(button.dataset.filter || "ALL");
      renderTable();
    });
  });

  searchEl?.addEventListener("input", () => {
    state.search = String(searchEl.value || "");
    renderTable();
  });

  refreshBtn?.addEventListener("click", fetchRows);

  bodyEl?.addEventListener("click", (event) => {
    const button = event.target instanceof HTMLElement ? event.target.closest("button[data-request-id]") : null;
    if (!button) return;
    const requestId = Number.parseInt(String(button.getAttribute("data-request-id") || "0"), 10);
    const row = state.rows.find((item) => Number(item.request_id) === requestId);
    if (row) openModal(row);
  });

  el("btnHmvApprove")?.addEventListener("click", () => submitReview("approve_member_request"));
  el("btnHmvReject")?.addEventListener("click", () => submitReview("reject_member_request"));

  fetchRows();
})();
