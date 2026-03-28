document.addEventListener("DOMContentLoaded", () => {
  const csrfToken = String(window.ADMIN_EDIT_REQUESTS_CSRF_TOKEN || "").trim();
  const tbody = document.getElementById("tableBody");
  const searchInput = document.getElementById("searchInput");
  // Filter modal may provide either a select (legacy) or checkboxes (current).
  const typeFilterSelect = document.querySelector(".request-type-filter");
  const typeFilterCheckboxes = Array.from(
    document.querySelectorAll('input.request-type-checkbox[name="requestTypeFilter"]')
  );
  const statusButtons = document.querySelectorAll(".status-filter-btn");
  const pendingBadge = document.getElementById("pendingRequestBadge");
  const btnRefreshTable = document.getElementById("btnEditRequestsRefresh");
  const entriesPerPageInput = document.getElementById("editRequestsEntriesPerPageInput");
  const paginationEl = document.getElementById("editRequestsPagination");

  const setRefreshLoading = (on) => {
    if (!btnRefreshTable) return;
    btnRefreshTable.classList.toggle("is-loading", !!on);
    btnRefreshTable.disabled = !!on;
  };

  const AUTO_REFRESH_MS = 30000;
  let autoRefreshTimeout = null;
  let autoRefreshInFlight = false;

  let allRequests = [];
  let activeStatus = "ALL";
  let currentPage = 1;
  let entriesPerPage = Math.max(1, Number.parseInt(entriesPerPageInput?.value || "20", 10) || 20);

  const statusLabel = (statusName) => {
    if (!statusName) return "Unknown";
    if (statusName === "PendingRequest") return "Pending";
    if (statusName === "ApprovedRequest") return "Approved";
    if (statusName === "DeniedRequest") return "Denied";
    return statusName;
  };

  const modalStatusBadgeClass = (statusText) => {
    if (statusText === "Approved") return "bg-success text-white";
    if (statusText === "Pending") return "bg-warning text-white";
    if (statusText === "Denied") return "bg-danger text-white";
    return "bg-secondary text-white";
  };

  const statusPillClass = (statusText) => {
    if (statusText === "Approved") return "approved";
    if (statusText === "Pending") return "pending";
    if (statusText === "Denied") return "denied";
    return "";
  };

  const formatDate = (value) => {
    if (!value) return "—";
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleString();
  };

  const renderTable = () => {
    if (!tbody) return;
    const search = (searchInput?.value || "").trim().toLowerCase();
    const typeSelected = String(typeFilterSelect?.value || "ALL");
    const checkedTypes = typeFilterCheckboxes
      .filter((cb) => cb && cb.checked)
      .map((cb) => String(cb.value || "").trim())
      .filter(Boolean);
    const activeTypes = checkedTypes.length ? checkedTypes : (typeSelected !== "ALL" ? [typeSelected] : []);

    const filtered = allRequests.filter((row) => {
      const matchStatus =
        activeStatus === "ALL" || statusLabel(row.status_name) === activeStatus;
      const matchType = activeTypes.length === 0 || activeTypes.includes(String(row.request_type || ""));
      const text = `${row.resident_id || ""} ${row.resident_name || ""}`.toLowerCase();
      const matchSearch = search === "" || text.includes(search);
      return matchStatus && matchType && matchSearch;
    });
    const totalPages = Math.max(1, Math.ceil(filtered.length / entriesPerPage));
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;
    const start = (currentPage - 1) * entriesPerPage;
    const pageRows = filtered.slice(start, start + entriesPerPage);
    renderPagination(totalPages, filtered.length);

    if (pageRows.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="7" class="text-center text-muted py-4">
            No edit requests yet.
          </td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = pageRows
      .map((row) => {
        const statusText = statusLabel(row.status_name);
        return `
          <tr>
            <td>${row.request_id}</td>
            <td>${row.resident_id || "—"}</td>
            <td><div class="fw-semibold">${row.resident_name || "—"}</div></td>
            <td class="text-capitalize">${row.request_type || "—"}</td>
            <td>${formatDate(row.created_at)}</td>
            <td>
              <span class="status-pill ${statusPillClass(statusText)}">${statusText}</span>
            </td>
            <td>
              <div class="compact-table-actions">
                <button class="btn btn-primary btn-sm compact-table-btn" data-action="view" data-id="${row.request_id}">View</button>
                <button class="btn btn-success btn-sm compact-table-btn" data-action="approve" data-id="${row.request_id}" ${statusText !== "Pending" ? "disabled" : ""}>Approve</button>
                <button class="btn btn-danger btn-sm compact-table-btn" data-action="deny" data-id="${row.request_id}" ${statusText !== "Pending" ? "disabled" : ""}>Deny</button>
              </div>
            </td>
          </tr>
        `;
      })
      .join("");
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
    for (let p = startPage; p <= endPage; p += 1) {
      addBtn(String(p), p, false, p === currentPage);
    }
    addBtn(">", Math.min(totalPages, currentPage + 1), currentPage >= totalPages, false);
  };

  const viewModalEl = document.getElementById("modal-viewRequest");
  const editDocsInlineLoading = document.getElementById("edit-docs-inline-loading");
  const editDocsInlineEmpty = document.getElementById("edit-docs-inline-empty");
  const editDocsInlineList = document.getElementById("edit-docs-inline-list");
  const editDocViewerEl = document.getElementById("modal-editDocViewer");
  const editDocViewerBody = document.getElementById("edit-doc-viewer-body");
  const editDocViewerTitle = document.getElementById("edit-doc-viewer-title");
  const editDocViewerSubtitle = document.getElementById("edit-doc-viewer-subtitle");
  const editDocViewerReturn = document.getElementById("edit-doc-viewer-return");
  const denyModalEl = document.getElementById("modal-denyRequest");
  const denyRemarksEl = document.getElementById("denyRemarks");
  const denyRemarksErrorEl = document.getElementById("denyRemarksError");
  const btnConfirmDeny = document.getElementById("btnConfirmDeny");
  let pendingDenyId = null;
  let currentViewedRequestId = null;
  const spanRequestId = document.getElementById("span-requestId");
  const spanRequestTypeHeader = document.getElementById("span-requestTypeHeader");
  const txtRequestResident = document.getElementById("txt-requestResident");
  const txtRequestResidentId = document.getElementById("txt-requestResidentId");
  const txtRequestType = document.getElementById("txt-requestType");
  const txtRequestStatus = document.getElementById("txt-requestStatus");
  const txtRequestCreated = document.getElementById("txt-requestCreated");
  const txtRequestReviewed = document.getElementById("txt-requestReviewed");
  const currentDetailsEl = document.getElementById("currentDetails");
  const requestedDetailsEl = document.getElementById("requestedDetails");

  const getStaticModal = (el) => {
    if (!el || !window.bootstrap?.Modal) return null;
    return bootstrap.Modal.getOrCreateInstance(el, { backdrop: "static", keyboard: false });
  };

  const renderDetailList = (el, items) => {
    if (!el) return;
    if (!items || items.length === 0) {
      el.innerHTML = `<div class="text-muted small">No data available.</div>`;
      return;
    }
    el.innerHTML = items
      .map(
        (item) => `
          <div class="request-detail ${item.changed ? "changed" : ""}">
            <div class="label">${item.label}:</div>
            <div class="value">${item.value || "—"}</div>
          </div>
        `
      )
      .join("");
  };

  const humanizeKey = (key) => {
    const map = {
      unit_number: "Unit Number",
      street_number: "Street Number",
      street_name: "Street Name",
      phase_number: "Phase Number",
      subdivision: "Subdivision",
      area_number: "Area Number",
      house_ownership: "House Ownership",
      house_type: "House Type",
      residency_duration: "Residency Duration",
      address_system: "Address System",
      new_head_resident_id: "New Head Resident ID",
      last_name: "Last Name",
      first_name: "First Name",
      middle_name: "Middle Name",
      suffix: "Suffix",
      phone_number: "Contact Number",
      relationship: "Relationship",
      address: "Address",
      religion: "Religion",
      civil_status: "Civil Status",
      sector_membership: "Sector Membership",
      occupation: "Occupation",
      occupation_detail: "Occupation Detail",
      voter_status: "Voter Status",
    };
    return map[key] || key.replace(/_/g, " ").replace(/\b\w/g, (m) => m.toUpperCase());
  };

  const loadRequests = async () => {
    if (!tbody) return;
    if (autoRefreshInFlight) return;
    autoRefreshInFlight = true;
    setRefreshLoading(true);
    try {
      const res = await fetch("../PhpFiles/Admin-End/edit_requests.php?fetch=1");
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.success) {
        throw new Error(data.message || "Failed to load edit requests.");
      }
      allRequests = Array.isArray(data.requests) ? data.requests : [];
      if (pendingBadge) {
        const count = data.pending_count ?? allRequests.filter((r) => statusLabel(r.status_name) === "Pending").length;
        pendingBadge.textContent = String(count);
        pendingBadge.classList.toggle("d-none", count <= 0);
      }
      renderTable();
    } catch (err) {
      tbody.innerHTML = `
        <tr>
          <td colspan="7" class="text-center text-danger py-4">
            ${err?.message || "Failed to load edit requests."}
          </td>
        </tr>
      `;
    } finally {
      autoRefreshInFlight = false;
      setRefreshLoading(false);
    }
  };

  const scheduleAutoRefresh = () => {
    if (autoRefreshTimeout) clearTimeout(autoRefreshTimeout);
    autoRefreshTimeout = setTimeout(() => {
      if (autoRefreshInFlight) {
        scheduleAutoRefresh();
        return;
      }
      triggerRefresh().catch(() => {});
    }, AUTO_REFRESH_MS);
  };

  const triggerRefresh = async () => {
    scheduleAutoRefresh();
    await loadRequests();
  };

  if (btnRefreshTable) {
    btnRefreshTable.addEventListener("click", () => {
      triggerRefresh().catch(() => {});
    });
  }

  const updateRequestStatus = async (requestId, action) => {
    const ok = window.confirm(`Are you sure you want to ${action} this request?`);
    if (!ok) return;
    try {
      const res = await fetch("../PhpFiles/Admin-End/edit_requests.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          ...(csrfToken ? { "X-CSRF-TOKEN": csrfToken } : {}),
        },
        body: JSON.stringify({ action, request_id: requestId }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.success) {
        throw new Error(data.message || "Failed to update request.");
      }
      await loadRequests();
    } catch (err) {
      alert(err?.message || "Failed to update request.");
    }
  };

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
    searchInput.addEventListener("input", () => {
      currentPage = 1;
      renderTable();
    });
  }

  if (typeFilterSelect) {
    typeFilterSelect.addEventListener("change", () => {
      currentPage = 1;
      renderTable();
    });
  }
  if (typeFilterCheckboxes.length) {
    typeFilterCheckboxes.forEach((cb) => cb.addEventListener("change", () => {
      currentPage = 1;
      renderTable();
    }));
  }

  if (entriesPerPageInput) {
    entriesPerPageInput.addEventListener("change", () => {
      const next = Math.max(1, Number.parseInt(entriesPerPageInput.value || "20", 10) || 20);
      entriesPerPage = next;
      entriesPerPageInput.value = String(next);
      currentPage = 1;
      renderTable();
    });
  }

  if (tbody) {
    tbody.addEventListener("click", (event) => {
      const target = event.target;
      if (!target || !target.dataset?.action) return;
      const action = target.dataset.action;
      const requestId = target.dataset.id;
      if (!requestId) return;
      if (action === "approve" || action === "deny") {
        if (action === "deny") {
          pendingDenyId = requestId;
          if (denyRemarksEl) denyRemarksEl.value = "";
          if (denyRemarksErrorEl) denyRemarksErrorEl.classList.add("d-none");
          getStaticModal(denyModalEl)?.show();
        } else {
          updateRequestStatus(requestId, action);
        }
      } else if (action === "view") {
        (async () => {
          try {
            const res = await fetch(`../PhpFiles/Admin-End/edit_requests.php?view=${requestId}`);
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
              throw new Error(data.message || "Failed to load request details.");
            }

            const req = data.request || {};
            const current = data.current || {};
            const changes = data.requested_changes || {};

            if (spanRequestId) spanRequestId.textContent = req.request_id || requestId;
            if (spanRequestTypeHeader) {
              spanRequestTypeHeader.textContent = req.request_type ? humanizeKey(req.request_type) : "Request";
            }
            if (txtRequestResident) txtRequestResident.textContent = req.resident_name || "—";
            if (txtRequestResidentId) txtRequestResidentId.textContent = req.resident_id || "—";
            if (txtRequestType) txtRequestType.textContent = req.request_type || "—";
            if (txtRequestCreated) txtRequestCreated.textContent = formatDate(req.created_at);
            if (txtRequestReviewed) {
              txtRequestReviewed.textContent = formatDate(req.reviewed_at);
            }
            const txtRequestReviewedBy = document.getElementById("txt-requestReviewedBy");
            if (txtRequestReviewedBy) {
              txtRequestReviewedBy.textContent = req.reviewed_by_name || "—";
            }

            const statusText = statusLabel(req.status_name);
            if (txtRequestStatus) {
              txtRequestStatus.textContent = statusText;
            }

            const currentItems = [];
            if (req.request_type === "address") {
              currentItems.push(
                { label: "Unit Number", value: current.address?.unit_number, key: "unit_number" },
                { label: "Street Number", value: current.address?.street_number, key: "street_number" },
                { label: "Street Name", value: current.address?.street_name, key: "street_name" },
                { label: "Phase Number", value: current.address?.phase_number, key: "phase_number" },
                { label: "Subdivision", value: current.address?.subdivision, key: "subdivision" },
                { label: "Area Number", value: current.address?.area_number, key: "area_number" },
                { label: "House Ownership", value: current.address?.house_ownership, key: "house_ownership" },
                { label: "House Type", value: current.address?.house_type, key: "house_type" },
                { label: "Residency Duration", value: current.address?.residency_duration, key: "residency_duration" }
              );
            } else if (req.request_type === "emergency") {
              currentItems.push(
                { label: "Last Name", value: current.emergency?.last_name, key: "last_name" },
                { label: "First Name", value: current.emergency?.first_name, key: "first_name" },
                { label: "Middle Name", value: current.emergency?.middle_name, key: "middle_name" },
                { label: "Suffix", value: current.emergency?.suffix, key: "suffix" },
                { label: "Contact Number", value: current.emergency?.phone_number, key: "phone_number" },
                { label: "Relationship", value: current.emergency?.relationship, key: "relationship" },
                { label: "Address", value: current.emergency?.address, key: "address" }
              );
            } else if (req.request_type === "profile") {
              currentItems.push(
                { label: "Last Name", value: current.profile?.lastname, key: "lastname" },
                { label: "First Name", value: current.profile?.firstname, key: "firstname" },
                { label: "Middle Name", value: current.profile?.middlename, key: "middlename" },
                { label: "Suffix", value: current.profile?.suffix, key: "suffix" },
                { label: "Civil Status", value: current.profile?.civil_status, key: "civil_status" },
                { label: "Religion", value: current.profile?.religion, key: "religion" },
                { label: "Occupation", value: current.profile?.occupation_detail || current.profile?.occupation, key: "occupation_detail" },
                { label: "Sector Membership", value: current.profile?.sector_membership, key: "sector_membership" },
                { label: "Voter Status", value: current.profile?.voter_status, key: "voter_status" }
              );
            }
            const changeKeys = new Set(Object.keys(changes || {}));
            const normalizeValue = (val) => {
              if (val === null || val === undefined) return "";
              return String(val).trim().toLowerCase();
            };
            const currentWithFlags = currentItems.map((item) => {
              if (!item.key || !changeKeys.has(item.key)) {
                return { ...item, changed: false };
              }
              const currentVal = normalizeValue(item.value);
              const requestedVal = normalizeValue(changes[item.key]);
              return { ...item, changed: currentVal !== requestedVal };
            });
            renderDetailList(currentDetailsEl, currentWithFlags);

            const formatRequestedValue = (key, value) => {
              if (key === "voter_status") {
                if (value === 1 || value === "1") return "Registered";
                if (value === 0 || value === "0") return "Not Registered";
              }
              if (key === "occupation") {
                if (value === 1 || value === "1") return "Employed";
                if (value === 0 || value === "0") return "Unemployed";
              }
              return value;
            };

            const requestedItems = currentItems.map((item) => {
              const rawRequestedVal = changeKeys.has(item.key) ? changes[item.key] : item.value;
              const requestedVal = formatRequestedValue(item.key, rawRequestedVal);
              const currentValNorm = normalizeValue(item.value);
              const requestedValNorm = normalizeValue(requestedVal);
              return {
                label: item.label,
                value: requestedVal,
                key: item.key,
                changed: currentValNorm !== requestedValNorm,
              };
            });
            renderDetailList(requestedDetailsEl, requestedItems);

            currentViewedRequestId = req.request_id || requestId;
            getStaticModal(viewModalEl)?.show();

            if (editDocsInlineLoading) editDocsInlineLoading.classList.remove("d-none");
            if (editDocsInlineEmpty) editDocsInlineEmpty.classList.add("d-none");
            if (editDocsInlineList) editDocsInlineList.innerHTML = "";
            try {
              const docsRes = await fetch(`../PhpFiles/Admin-End/edit_requests.php?docs=${currentViewedRequestId}`);
              const docsData = await docsRes.json().catch(() => ({}));
              if (!docsRes.ok || !docsData.success) {
                throw new Error(docsData.message || "Failed to load documents.");
              }
              renderDocs(docsData.documents || []);
            } catch (docErr) {
              if (editDocsInlineEmpty) {
                editDocsInlineEmpty.textContent = docErr?.message || "Failed to load documents.";
                editDocsInlineEmpty.classList.remove("d-none");
              }
            } finally {
              if (editDocsInlineLoading) editDocsInlineLoading.classList.add("d-none");
            }
          } catch (err) {
            alert(err?.message || "Failed to load request details.");
          }
        })();
      }
    });
  }

  const renderDocs = (docs) => {
    if (!editDocsInlineList) return;
    if (!docs || docs.length === 0) {
      editDocsInlineList.innerHTML = "";
      if (editDocsInlineEmpty) editDocsInlineEmpty.classList.remove("d-none");
      return;
    }
    if (editDocsInlineEmpty) editDocsInlineEmpty.classList.add("d-none");
    editDocsInlineList.innerHTML = docs
      .map((doc) => {
        const statusLabel = doc.status_name || "PendingReview";
        const statusClass =
          statusLabel.toLowerCase().includes("verified") ? "doc-row--verified" :
          statusLabel.toLowerCase().includes("rejected") || statusLabel.toLowerCase().includes("denied") ? "doc-row--denied" :
          "doc-row--pending";
        const uploadedAt = doc.upload_timestamp ? new Date(doc.upload_timestamp).toLocaleString() : "—";
        return `
          <div class="doc-row border rounded-3 p-3 ${statusClass}">
            <div class="d-flex justify-content-between align-items-start doc-row__grid">
              <div class="doc-row__info">
                <div class="fw-bold">${doc.document_type_name || "Document"}</div>
                <div class="text-muted small">Uploaded: ${uploadedAt}</div>
              </div>
              <div class="doc-row__view">
                <button class="btn btn-primary btn-sm" data-doc-url="${doc.file_url || doc.file_path}" data-doc-title="${doc.document_type_name || "Document"}">View</button>
              </div>
            </div>
          </div>
        `;
      })
      .join("");
  };

  if (editDocsInlineList) {
    editDocsInlineList.addEventListener("click", (event) => {
      const target = event.target;
      if (target && target.dataset?.docUrl) {
        const url = target.dataset.docUrl;
        const title = target.dataset.docTitle || "Document";
        if (editDocViewerTitle) editDocViewerTitle.textContent = title;
        if (editDocViewerSubtitle) editDocViewerSubtitle.textContent = "";
        if (editDocViewerBody) {
          const ext = url.split(".").pop()?.toLowerCase() || "";
          if (ext === "pdf") {
            editDocViewerBody.innerHTML = `<iframe src="${url}" style="width:100%;height:70vh;border:0;"></iframe>`;
          } else {
            editDocViewerBody.innerHTML = `<img src="${url}" alt="${title}" style="max-width:100%;height:auto;display:block;margin:0 auto;">`;
          }
        }
        getStaticModal(viewModalEl)?.hide();
        getStaticModal(editDocViewerEl)?.show();
      }
    });
  }

  if (editDocViewerReturn) {
    editDocViewerReturn.addEventListener("click", () => {
      getStaticModal(editDocViewerEl)?.hide();
      getStaticModal(viewModalEl)?.show();
    });
  }

  if (btnConfirmDeny) {
    btnConfirmDeny.addEventListener("click", async () => {
      if (!pendingDenyId) return;
      const remarks = (denyRemarksEl?.value || "").trim();
      if (!remarks) {
        if (denyRemarksErrorEl) denyRemarksErrorEl.classList.remove("d-none");
        return;
      }
      if (denyRemarksErrorEl) denyRemarksErrorEl.classList.add("d-none");
      try {
        const res = await fetch("../PhpFiles/Admin-End/edit_requests.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            ...(csrfToken ? { "X-CSRF-TOKEN": csrfToken } : {}),
          },
          body: JSON.stringify({ action: "deny", request_id: pendingDenyId, admin_notes: remarks }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) {
          throw new Error(data.message || "Failed to deny request.");
        }
        getStaticModal(denyModalEl)?.hide();
        pendingDenyId = null;
        await loadRequests();
      } catch (err) {
        alert(err?.message || "Failed to deny request.");
      }
    });
  }

  loadRequests();
  scheduleAutoRefresh();
});
