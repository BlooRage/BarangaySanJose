(() => {
  const el = (id) => document.getElementById(id);
  const bodyEl = el("householdMemberVerificationBody");
  const emptyEl = el("householdMemberVerificationEmpty");
  const searchEl = el("hmvSearchInput");
  const pendingBadgeEl = el("pendingHouseholdMemberBadge");
  const refreshBtn = el("btnHouseholdMemberVerificationRefresh");
  const filterDateFromEl = el("hmvFilterDateFrom");
  const filterDateToEl = el("hmvFilterDateTo");
  const btnApplyFilter = el("btnHmvApplyFilter");
  const btnResetFilter = el("btnHmvResetFilter");
  const modalEl = el("modal-householdMemberVerification");
  const modal = modalEl ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
  const confirmModalEl = el("hmvActionConfirmModal");
  const confirmModal = confirmModalEl ? bootstrap.Modal.getOrCreateInstance(confirmModalEl) : null;
  const confirmTitleEl = el("hmvActionConfirmTitle");
  const confirmMessageEl = el("hmvActionConfirmMessage");
  const confirmRemarksWrapEl = el("hmvActionConfirmRemarksWrap");
  const confirmRemarksEl = el("hmvActionConfirmRemarks");
  const confirmActionBtn = el("btnHmvConfirmAction");

  const state = {
    rows: [],
    filter: "ALL",
    search: "",
    active: null,
    pendingAction: "",
    modalFilters: {
      dateFrom: "",
      dateTo: "",
    },
  };

  const fmtStatus = (status) => {
    const raw = String(status || "").trim();
    if (!raw) return "PendingReview";
    const key = raw.toLowerCase().replace(/[\s_]+/g, "");
    if (key === "approved" || key === "verified" || key === "active") return "Approved";
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
      PendingReview: "Pending",
      Approved: "Approved",
      Rejected: "Rejected",
    };
    return `<span class="${classMap[normalized] || classMap.PendingReview}">${labelMap[normalized] || "Pending"}</span>`;
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

    if (["jpg", "jpeg", "png"].includes(ext)) {
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

  const normalizeDateValue = (value) => {
    const raw = String(value || "").trim();
    if (!raw) return "";
    const isoMatch = raw.match(/^(\d{4}-\d{2}-\d{2})/);
    if (isoMatch) return isoMatch[1];
    const parsed = new Date(raw);
    if (Number.isNaN(parsed.getTime())) return "";
    const year = parsed.getFullYear();
    const month = String(parsed.getMonth() + 1).padStart(2, "0");
    const day = String(parsed.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
  };

  const filteredRows = () => {
    const q = state.search.trim().toLowerCase();
    return state.rows.filter((row) => {
      const status = fmtStatus(row.status);
      if (state.filter !== "ALL" && status !== state.filter) return false;
      const submittedDate = normalizeDateValue(row.submitted_at);
      if (state.modalFilters.dateFrom && (!submittedDate || submittedDate < state.modalFilters.dateFrom)) return false;
      if (state.modalFilters.dateTo && (!submittedDate || submittedDate > state.modalFilters.dateTo)) return false;
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

    Array.from(bodyEl.querySelectorAll("tr")).forEach((tableRow, index) => {
      const headCell = tableRow.children[1];
      if (!headCell) return;
      headCell.textContent = String(rows[index]?.head_full_name || "â€”");
      if (!String(rows[index]?.head_full_name || "").trim()) {
        headCell.textContent = "-";
      }
    });
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
    renderDocumentPreview(row);

    const isPending = fmtStatus(row.status) === "PendingReview";
    el("hmvModalActions")?.classList.toggle("d-none", !isPending);
    modal?.show();
  };

  const fetchRows = async () => {
    if (refreshBtn) refreshBtn.classList.add("is-loading");
    if (bodyEl) {
      bodyEl.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">Loading requests...</td></tr>`;
    }
    emptyEl?.classList.add("d-none");
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
      if (bodyEl) {
        bodyEl.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${escapeHtml(error?.message || "Failed to load requests.")}</td></tr>`;
      }
      emptyEl?.classList.add("d-none");
    } finally {
      if (refreshBtn) refreshBtn.classList.remove("is-loading");
    }
  };

  const submitReview = async (action) => {
    const row = state.active;
    if (!row) return;
    let reviewRemarks = "";
    if (action === "reject_member_request") {
      reviewRemarks = String(confirmRemarksEl?.value || "").trim();
      if (!reviewRemarks) {
        window.alert("Rejection remarks are required.");
        confirmRemarksEl?.focus();
        return;
      }
    }
    try {
      if (confirmActionBtn) confirmActionBtn.disabled = true;
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
      confirmModal?.hide();
      modal?.hide();
      await fetchRows();
    } catch (error) {
      window.alert(error?.message || "Failed to review request.");
    } finally {
      if (confirmActionBtn) confirmActionBtn.disabled = false;
    }
  };

  const openActionConfirm = (action) => {
    const row = state.active;
    if (!row) return;
    state.pendingAction = action;
    const isReject = action === "reject_member_request";
    if (confirmTitleEl) {
      confirmTitleEl.textContent = isReject ? "Confirm Rejection" : "Confirm Approval";
    }
    if (confirmMessageEl) {
      confirmMessageEl.textContent = isReject
        ? `Reject household member verification request ${row.request_id}?`
        : `Approve household member verification request ${row.request_id}?`;
    }
    if (confirmRemarksWrapEl) {
      confirmRemarksWrapEl.classList.toggle("d-none", !isReject);
    }
    if (confirmRemarksEl) {
      confirmRemarksEl.value = isReject ? String(row.review_remarks || "") : "";
    }
    if (confirmActionBtn) {
      confirmActionBtn.textContent = isReject ? "Reject" : "Approve";
      confirmActionBtn.classList.toggle("btn-danger", isReject);
      confirmActionBtn.classList.toggle("btn-success", !isReject);
      confirmActionBtn.classList.remove("btn-primary");
    }
    confirmModal?.show();
    if (isReject) {
      window.setTimeout(() => confirmRemarksEl?.focus(), 150);
    }
  };

  document.querySelectorAll(".hmv-filter-btn").forEach((button) => {
    button.addEventListener("click", () => {
      document.querySelectorAll(".hmv-filter-btn").forEach((btn) => btn.classList.remove("active"));
      state.filter = String(button.dataset.filter || "ALL");
      button.classList.add("active");
      renderTable();
    });
  });

  searchEl?.addEventListener("input", () => {
    state.search = String(searchEl.value || "");
    renderTable();
  });

  refreshBtn?.addEventListener("click", fetchRows);
  btnApplyFilter?.addEventListener("click", () => {
    state.modalFilters.dateFrom = String(filterDateFromEl?.value || "").trim();
    state.modalFilters.dateTo = String(filterDateToEl?.value || "").trim();
    bootstrap.Modal.getOrCreateInstance(el("modalHouseholdMemberVerificationFilter"))?.hide();
    renderTable();
  });
  btnResetFilter?.addEventListener("click", () => {
    state.modalFilters.dateFrom = "";
    state.modalFilters.dateTo = "";
    if (filterDateFromEl) filterDateFromEl.value = "";
    if (filterDateToEl) filterDateToEl.value = "";
    renderTable();
  });

  bodyEl?.addEventListener("click", (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;
    const button = target.closest("button[data-request-id]");
    if (!button) return;
    const requestId = Number.parseInt(String(button.getAttribute("data-request-id") || "0"), 10);
    const row = state.rows.find((item) => Number(item.request_id) === requestId);
    if (row) openModal(row);
  });

  el("btnHmvApprove")?.addEventListener("click", () => openActionConfirm("approve_member_request"));
  el("btnHmvReject")?.addEventListener("click", () => openActionConfirm("reject_member_request"));
  confirmActionBtn?.addEventListener("click", () => submitReview(state.pendingAction));

  fetchRows();
})();
