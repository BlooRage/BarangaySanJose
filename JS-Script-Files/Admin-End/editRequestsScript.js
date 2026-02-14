document.addEventListener("DOMContentLoaded", () => {
  const tbody = document.getElementById("tableBody");
  const searchInput = document.getElementById("searchInput");
  const typeFilter = document.querySelector(".request-type-filter");
  const statusButtons = document.querySelectorAll(".status-filter-btn");
  const pendingBadge = document.getElementById("pendingRequestBadge");

  let allRequests = [];
  let activeStatus = "ALL";

  const statusLabel = (statusName) => {
    if (!statusName) return "Unknown";
    if (statusName === "PendingRequest") return "Pending";
    if (statusName === "ApprovedRequest") return "Approved";
    if (statusName === "DeniedRequest") return "Denied";
    return statusName;
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
    const type = typeFilter?.value || "ALL";

    const filtered = allRequests.filter((row) => {
      const matchStatus =
        activeStatus === "ALL" || statusLabel(row.status_name) === activeStatus;
      const matchType = type === "ALL" || row.request_type === type;
      const text = `${row.resident_id || ""} ${row.resident_name || ""}`.toLowerCase();
      const matchSearch = search === "" || text.includes(search);
      return matchStatus && matchType && matchSearch;
    });

    if (filtered.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="6" class="text-center text-muted py-4">
            No edit requests yet.
          </td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = filtered
      .map((row) => {
        const statusText = statusLabel(row.status_name);
        return `
          <tr>
            <td>${row.request_id}</td>
            <td>
              <div class="fw-semibold">${row.resident_name || "—"}</div>
              <div class="text-muted small">${row.resident_id || "—"}</div>
            </td>
            <td class="text-capitalize">${row.request_type || "—"}</td>
            <td>${formatDate(row.created_at)}</td>
            <td>
              <span class="status-pill ${statusText.toLowerCase()}">${statusText}</span>
            </td>
            <td>
              <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm" data-action="view" data-id="${row.request_id}">View</button>
                <button class="btn btn-success btn-sm" data-action="approve" data-id="${row.request_id}" ${statusText !== "Pending" ? "disabled" : ""}>Approve</button>
                <button class="btn btn-danger btn-sm" data-action="deny" data-id="${row.request_id}" ${statusText !== "Pending" ? "disabled" : ""}>Deny</button>
              </div>
            </td>
          </tr>
        `;
      })
      .join("");
  };

  const viewModalEl = document.getElementById("modal-viewRequest");
  const denyModalEl = document.getElementById("modal-denyRequest");
  const denyRemarksEl = document.getElementById("denyRemarks");
  const denyRemarksErrorEl = document.getElementById("denyRemarksError");
  const btnConfirmDeny = document.getElementById("btnConfirmDeny");
  let pendingDenyId = null;
  const spanRequestId = document.getElementById("span-requestId");
  const spanRequestTypeHeader = document.getElementById("span-requestTypeHeader");
  const txtRequestResident = document.getElementById("txt-requestResident");
  const txtRequestResidentId = document.getElementById("txt-requestResidentId");
  const txtRequestType = document.getElementById("txt-requestType");
  const badgeRequestStatus = document.getElementById("badge-requestStatus");
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
          <td colspan="6" class="text-center text-danger py-4">
            ${err?.message || "Failed to load edit requests."}
          </td>
        </tr>
      `;
    }
  };

  const updateRequestStatus = async (requestId, action) => {
    const ok = window.confirm(`Are you sure you want to ${action} this request?`);
    if (!ok) return;
    try {
      const res = await fetch("../PhpFiles/Admin-End/edit_requests.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
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
      renderTable();
    });
  });

  if (searchInput) {
    searchInput.addEventListener("input", renderTable);
  }

  if (typeFilter) {
    typeFilter.addEventListener("change", renderTable);
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
            if (txtRequestResidentId) txtRequestResidentId.textContent = req.resident_id ? `Resident ID: ${req.resident_id}` : "—";
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
            if (badgeRequestStatus) {
              badgeRequestStatus.textContent = statusText;
              badgeRequestStatus.className = `status-pill ${statusText.toLowerCase()}`;
            }

            const currentItems = [];
            if (req.request_type === "address") {
              currentItems.push(
                { label: "Unit Number", value: current.address?.unit_number, key: "unit_number" },
                { label: "Street Number", value: current.address?.street_number, key: "street_number" },
                { label: "Street Name", value: current.address?.street_name, key: "street_name" },
                { label: "Phase Number", value: current.address?.phase_number, key: "phase_number" },
                { label: "Subdivision", value: current.address?.subdivision, key: "subdivision" },
                { label: "Area Number", value: current.address?.area_number, key: "area_number" }
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

            const requestedItems = Object.keys(changes).map((key) => {
              const currentItem = currentItems.find((item) => item.key === key);
              const currentVal = normalizeValue(currentItem ? currentItem.value : "");
              const requestedVal = normalizeValue(changes[key]);
              return {
                label: humanizeKey(key),
                value: changes[key],
                changed: currentVal !== requestedVal,
              };
            });
            renderDetailList(requestedDetailsEl, requestedItems);

            getStaticModal(viewModalEl)?.show();
          } catch (err) {
            alert(err?.message || "Failed to load request details.");
          }
        })();
      }
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
          headers: { "Content-Type": "application/json" },
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
});
