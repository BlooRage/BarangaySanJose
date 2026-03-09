document.addEventListener("DOMContentLoaded", () => {
  const tbody = document.getElementById("hofTbody");
  const searchInput = document.getElementById("hofSearch");
  const refreshBtn = document.getElementById("btnHofRefresh");
  const countdownEl = document.getElementById("hofAutoRefreshCountdown");
  const statusButtons = document.querySelectorAll(".status-filter-btn");
  const pendingBadge = document.getElementById("pendingHofBadge");
  const entriesPerPageInput = document.getElementById("hofEntriesPerPageInput");
  const paginationEl = document.getElementById("hofPagination");

  const approveModalAddress = document.getElementById("approveAddressDisplay");
  const approveApplicantsBody = document.getElementById("approveApplicantsBody");
  const approveGroupKey = document.getElementById("approveGroupKey");
  const approveError = document.getElementById("approveHeadError");
  const btnConfirmApprove = document.getElementById("btnConfirmApproveHead");

  let rowsRaw = [];
  let activeStatus = "ALL";
  let currentPage = 1;
  let entriesPerPage = Math.max(1, Number.parseInt(entriesPerPageInput?.value || "20", 10) || 20);

  const AUTO_REFRESH_SECONDS = 60;
  let secondsLeft = AUTO_REFRESH_SECONDS;
  let autoRefreshInterval = null;
  let inFlight = false;

  const setRefreshLoading = (on) => {
    if (!refreshBtn) return;
    refreshBtn.classList.toggle("is-loading", !!on);
    refreshBtn.disabled = !!on;
  };

  const safe = (v) => {
    const s = String(v ?? "").trim();
    return s !== "" ? s : "-";
  };

  const statusPill = (status) => {
    const key = String(status || "").toLowerCase() === "approved"
      ? "approved"
      : (String(status || "").toLowerCase() === "declined" ? "declined" : "pending");
    const text = key === "approved" ? "Approved" : (key === "declined" ? "Declined" : "Pending");
    return `<span class="status-pill ${key}">${text}</span>`;
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
        if (disabled || page === currentPage) return;
        currentPage = page;
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

    addBtn("<", Math.max(1, currentPage - 1), currentPage <= 1, false);
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);
    for (let p = startPage; p <= endPage; p += 1) addBtn(String(p), p, false, p === currentPage);
    addBtn(">", Math.min(totalPages, currentPage + 1), currentPage >= totalPages, false);
  };

  const filteredRows = () => {
    const q = (searchInput?.value || "").trim().toLowerCase();
    let rows = [...rowsRaw];

    if (activeStatus !== "ALL") {
      rows = rows.filter((r) => String(r.verification_status || "Pending") === activeStatus);
    }

    if (q) {
      rows = rows.filter((r) => {
        const text = `${r.group_key || ""} ${r.address_id || ""} ${r.address_display || ""} ${r.area_number || ""} ${r.verification_status || ""} ${r.decided_by_user_id || ""}`.toLowerCase();
        return text.includes(q);
      });
    }
    return rows;
  };

  const updatePendingBadge = () => {
    if (!pendingBadge) return;
    const count = rowsRaw.filter((r) => String(r.verification_status || "Pending") === "Pending").length;
    pendingBadge.textContent = String(count);
    pendingBadge.classList.toggle("d-none", count <= 0);
  };

  const renderTable = () => {
    if (!tbody) return;
    const rows = filteredRows();
    const totalPages = Math.max(1, Math.ceil(rows.length / entriesPerPage));
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;
    const start = (currentPage - 1) * entriesPerPage;
    const pageRows = rows.slice(start, start + entriesPerPage);
    renderPagination(totalPages, rows.length);

    tbody.innerHTML = "";
    if (!pageRows.length) {
      tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">No records found.</td></tr>`;
      return;
    }

    pageRows.forEach((row) => {
      const status = String(row.verification_status || "Pending");
      const canAct = status === "Pending";
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td title="${safe(row.address_display)}">${safe(row.address_display)}</td>
        <td>${safe(row.area_number)}</td>
        <td>${Number(row.household_count ?? 0)}</td>
        <td>${statusPill(status)}</td>
        <td>${safe(row.decided_by_user_id)}</td>
        <td>${safe(row.decided_at)}</td>
        <td>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-success btn-sm btn-approve" ${canAct ? "" : "disabled"}>Approve</button>
            <button type="button" class="btn btn-danger btn-sm btn-decline" ${canAct ? "" : "disabled"}>Decline</button>
          </div>
        </td>
      `;

      tr.querySelector(".btn-approve")?.addEventListener("click", () => openApproveModal(row));
      tr.querySelector(".btn-decline")?.addEventListener("click", () => declineGroup(row));
      tbody.appendChild(tr);
    });
  };

  const fetchRows = async () => {
    if (inFlight) return;
    inFlight = true;
    setRefreshLoading(true);
    try {
      const res = await fetch("../PhpFiles/Admin-End/headOfFamilyApplications.php?fetch=1", {
        headers: { Accept: "application/json" }
      });
      const data = await res.json().catch(() => ([]));
      if (!res.ok || !Array.isArray(data)) throw new Error("Failed to load head applications.");
      rowsRaw = data;
      updatePendingBadge();
      renderTable();
    } catch (err) {
      if (tbody) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${err?.message || "Failed to load records."}</td></tr>`;
      }
    } finally {
      inFlight = false;
      setRefreshLoading(false);
    }
  };

  const renderCountdown = () => {
    if (!countdownEl) return;
    countdownEl.textContent = secondsLeft > 0 ? `Auto refresh in ${secondsLeft}s` : "";
  };

  const resetCountdown = () => {
    secondsLeft = AUTO_REFRESH_SECONDS;
    renderCountdown();
  };

  const triggerRefresh = () => {
    resetCountdown();
    fetchRows().catch(() => {});
  };

  const startAutoRefresh = () => {
    renderCountdown();
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
    autoRefreshInterval = setInterval(() => {
      if (inFlight) return;
      secondsLeft -= 1;
      if (secondsLeft <= 0) {
        triggerRefresh();
        return;
      }
      renderCountdown();
    }, 1000);
  };

  const openApproveModal = (row) => {
    if (!row || !Array.isArray(row.households) || !row.households.length) return;
    approveModalAddress.textContent = row.address_display || row.address_id || "-";
    approveGroupKey.value = row.group_key || "";
    approveError.classList.add("d-none");
    approveApplicantsBody.innerHTML = "";

    row.households.forEach((h, idx) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td><input class="form-check-input" type="radio" name="approvedResident" value="${h.resident_id || ""}" ${idx === 0 ? "checked" : ""}></td>
        <td>${safe(h.resident_id)}</td>
        <td>${safe(h.head_full_name)}</td>
        <td>${Number(h.member_count ?? 1)}</td>
      `;
      approveApplicantsBody.appendChild(tr);
    });

    const modalEl = document.getElementById("modalApproveHead");
    if (!modalEl) return;
    new bootstrap.Modal(modalEl, { backdrop: "static", keyboard: false }).show();
  };

  const approveGroup = async () => {
    const groupKey = approveGroupKey.value.trim();
    const selected = document.querySelector("input[name='approvedResident']:checked");
    const approvedResidentId = selected ? String(selected.value || "").trim() : "";
    if (!groupKey || !approvedResidentId) {
      approveError.classList.remove("d-none");
      return;
    }
    approveError.classList.add("d-none");
    btnConfirmApprove.disabled = true;
    try {
      const res = await fetch("../PhpFiles/Admin-End/headOfFamilyApplications.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "approve_head_group",
          group_key: groupKey,
          approved_resident_id: approvedResidentId
        })
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.success) throw new Error(data.message || "Failed to approve application.");
      const modalEl = document.getElementById("modalApproveHead");
      const modal = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;
      if (modal) modal.hide();
      fetchRows().catch(() => {});
    } catch (err) {
      alert(err?.message || "Failed to approve application.");
    } finally {
      btnConfirmApprove.disabled = false;
    }
  };

  const declineGroup = async (row) => {
    if (!row?.group_key) return;
    const ok = window.confirm("Decline this head-of-family application?");
    if (!ok) return;
    try {
      const res = await fetch("../PhpFiles/Admin-End/headOfFamilyApplications.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "decline_head_group",
          group_key: row.group_key
        })
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.success) throw new Error(data.message || "Failed to decline application.");
      fetchRows().catch(() => {});
    } catch (err) {
      alert(err?.message || "Failed to decline application.");
    }
  };

  refreshBtn?.addEventListener("click", triggerRefresh);
  btnConfirmApprove?.addEventListener("click", approveGroup);

  statusButtons.forEach((btn) => {
    btn.addEventListener("click", () => {
      statusButtons.forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      activeStatus = btn.dataset.filter || "ALL";
      currentPage = 1;
      renderTable();
    });
  });

  if (searchInput) {
    let timer;
    searchInput.addEventListener("input", () => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        currentPage = 1;
        renderTable();
      }, 200);
    });
  }

  if (entriesPerPageInput) {
    const applyEntries = () => {
      const parsed = Number.parseInt(entriesPerPageInput.value || "20", 10);
      entriesPerPage = Math.max(1, Number.isNaN(parsed) ? 20 : parsed);
      entriesPerPageInput.value = String(entriesPerPage);
      currentPage = 1;
      renderTable();
    };
    entriesPerPageInput.addEventListener("input", applyEntries);
    entriesPerPageInput.addEventListener("change", applyEntries);
  }

  fetchRows().catch(() => {});
  startAutoRefresh();
});
