document.addEventListener("DOMContentLoaded", function () {
  var csrfToken = String(window.ADMIN_EDIT_REQUESTS_CSRF_TOKEN || "").trim();
  var tbody = document.getElementById("tableBody");
  var searchInput = document.getElementById("searchInput");
  var statusButtons = Array.prototype.slice.call(document.querySelectorAll(".status-filter-btn"));
  var typeFilterSelect = document.querySelector(".request-type-filter");
  var typeFilterCheckboxes = Array.prototype.slice.call(document.querySelectorAll('input.request-type-checkbox[name="requestTypeFilter"]'));
  var pendingBadge = document.getElementById("pendingRequestBadge");
  var btnRefreshTable = document.getElementById("btnEditRequestsRefresh");
  var entriesPerPageInput = document.getElementById("editRequestsEntriesPerPageInput");
  var paginationEl = document.getElementById("editRequestsPagination");

  var viewModalEl = document.getElementById("modal-viewRequest");
  var denyModalEl = document.getElementById("modal-denyRequest");
  var editDocViewerEl = document.getElementById("modal-editDocViewer");
  var editDocsInlineLoading = document.getElementById("edit-docs-inline-loading");
  var editDocsInlineEmpty = document.getElementById("edit-docs-inline-empty");
  var editDocsInlineList = document.getElementById("edit-docs-inline-list");
  var editDocViewerBody = document.getElementById("edit-doc-viewer-body");
  var editDocViewerTitle = document.getElementById("edit-doc-viewer-title");
  var editDocViewerSubtitle = document.getElementById("edit-doc-viewer-subtitle");
  var editDocViewerReturn = document.getElementById("edit-doc-viewer-return");
  var denyRemarksEl = document.getElementById("denyRemarks");
  var denyRemarksErrorEl = document.getElementById("denyRemarksError");
  var btnConfirmDeny = document.getElementById("btnConfirmDeny");
  var btnViewApproveRequest = document.getElementById("btnViewApproveRequest");
  var btnViewDenyRequest = document.getElementById("btnViewDenyRequest");

  var spanRequestId = document.getElementById("span-requestId");
  var spanRequestTypeHeader = document.getElementById("span-requestTypeHeader");
  var txtRequestResident = document.getElementById("txt-requestResident");
  var txtRequestResidentId = document.getElementById("txt-requestResidentId");
  var txtRequestType = document.getElementById("txt-requestType");
  var txtRequestStatus = document.getElementById("txt-requestStatus");
  var txtRequestCreated = document.getElementById("txt-requestCreated");
  var txtRequestReviewed = document.getElementById("txt-requestReviewed");
  var currentDetailsEl = document.getElementById("currentDetails");
  var requestedDetailsEl = document.getElementById("requestedDetails");

  var allRequests = [];
  var activeStatus = "ALL";
  var currentPage = 1;
  var entriesPerPage = Math.max(1, parseInt(entriesPerPageInput && entriesPerPageInput.value ? entriesPerPageInput.value : "20", 10) || 20);
  var pendingDenyId = null;
  var currentViewedRequestId = null;
  var currentViewedStatusText = "";
  var autoRefreshTimeout = null;
  var autoRefreshInFlight = false;

  function statusLabel(statusName) {
    if (!statusName) return "Unknown";
    if (statusName === "PendingRequest") return "Pending";
    if (statusName === "ApprovedRequest") return "Approved";
    if (statusName === "DeniedRequest") return "Denied";
    return statusName;
  }

  function statusPillClass(statusText) {
    if (statusText === "Approved") return "approved";
    if (statusText === "Pending") return "pending";
    if (statusText === "Denied") return "denied";
    return "";
  }

  function formatDate(value) {
    if (!value) return "-";
    var d = new Date(value);
    if (isNaN(d.getTime())) return value;
    return d.toLocaleString();
  }

  function esc(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function getModal(el) {
    if (!el || !window.bootstrap || !window.bootstrap.Modal) return null;
    return window.bootstrap.Modal.getOrCreateInstance(el, { backdrop: "static", keyboard: false });
  }

  function closeFloatingActionUi() {
    if (window.AdminTableActions && typeof window.AdminTableActions.closeOpenDropdowns === "function") {
      window.AdminTableActions.closeOpenDropdowns();
    }

    var openDropdownToggles = Array.prototype.slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"][aria-expanded="true"]'));
    Array.prototype.slice.call(document.querySelectorAll(".dropdown-menu.show, body > .admin-table-action-menu-portal")).forEach(function (menu) {
      var toggle = menu.previousElementSibling;
      if (toggle && openDropdownToggles.indexOf(toggle) === -1) openDropdownToggles.push(toggle);
      menu.classList.remove("show");
    });

    openDropdownToggles.forEach(function (toggle) {
      if (window.bootstrap && window.bootstrap.Dropdown) {
        window.bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
      } else {
        toggle.setAttribute("aria-expanded", "false");
      }
    });

    Array.prototype.slice.call(document.querySelectorAll(".tooltip.show, .popover.show")).forEach(function (el) {
      el.parentNode.removeChild(el);
    });
  }

  function showModal(el) {
    if (!el) return;
    closeFloatingActionUi();
    var modal = getModal(el);
    if (modal) {
      modal.show();
      return;
    }
    el.classList.add("show");
    el.style.display = "block";
    el.removeAttribute("aria-hidden");
    el.setAttribute("aria-modal", "true");
    document.body.classList.add("modal-open");
  }

  function hideModal(el) {
    if (!el) return;
    var modal = getModal(el);
    if (modal) {
      modal.hide();
      return;
    }
    el.classList.remove("show");
    el.style.display = "none";
    el.setAttribute("aria-hidden", "true");
    el.removeAttribute("aria-modal");
    document.body.classList.remove("modal-open");
  }

  function humanizeKey(key) {
    var map = {
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
      head_of_family: "Resident Role"
    };
    return map[key] || String(key || "").replace(/_/g, " ").replace(/\b\w/g, function (m) { return m.toUpperCase(); });
  }

  function normalizeValue(value) {
    if (value === null || value === undefined) return "";
    return String(value).trim().toLowerCase();
  }

  function renderDetailList(el, items) {
    if (!el) return;
    if (!items || !items.length) {
      el.innerHTML = '<div class="text-muted small">No data available.</div>';
      return;
    }
    el.innerHTML = items.map(function (item) {
      return '' +
        '<div class="request-detail ' + (item.changed ? "changed" : "") + '">' +
          '<div class="label">' + esc(item.label) + ':</div>' +
          '<div class="value">' + esc(item.value || "-") + '</div>' +
        '</div>';
    }).join("");
  }

  function formatRequestedValue(key, value) {
    if (key === "voter_status") {
      if (value === 1 || value === "1") return "Registered";
      if (value === 0 || value === "0") return "Not Registered";
    }
    if (key === "occupation") {
      if (value === 1 || value === "1") return "Employed";
      if (value === 0 || value === "0") return "Unemployed";
    }
    if (key === "head_of_family") {
      if (value === 1 || value === "1") return "Head of the Family";
      if (value === 0 || value === "0") return "Resident";
    }
    return value;
  }

  function setViewActionButtons(statusText) {
    var isPending = statusText === "Pending";
    if (btnViewApproveRequest) btnViewApproveRequest.classList.toggle("d-none", !isPending);
    if (btnViewDenyRequest) btnViewDenyRequest.classList.toggle("d-none", !isPending);
  }

  function setRefreshLoading(on) {
    if (!btnRefreshTable) return;
    btnRefreshTable.classList.toggle("is-loading", !!on);
    btnRefreshTable.disabled = !!on;
  }

  function getFilteredRows() {
    var search = String(searchInput && searchInput.value ? searchInput.value : "").trim().toLowerCase();
    var typeSelected = String(typeFilterSelect && typeFilterSelect.value ? typeFilterSelect.value : "ALL");
    var checkedTypes = typeFilterCheckboxes.filter(function (cb) { return cb && cb.checked; })
      .map(function (cb) { return String(cb.value || "").trim(); })
      .filter(Boolean);
    var activeTypes = checkedTypes.length ? checkedTypes : (typeSelected !== "ALL" ? [typeSelected] : []);

    return allRequests.filter(function (row) {
      var rowStatus = statusLabel(row.status_name);
      var matchStatus = activeStatus === "ALL" || rowStatus === activeStatus;
      var matchType = activeTypes.length === 0 || activeTypes.indexOf(String(row.request_type || "")) !== -1;
      var text = String((row.resident_id || "") + " " + (row.resident_name || "")).toLowerCase();
      return matchStatus && matchType && (search === "" || text.indexOf(search) !== -1);
    });
  }

  function renderPagination(totalPages, totalRows) {
    if (!paginationEl) return;
    paginationEl.innerHTML = "";
    function addBtn(label, page, disabled, active) {
      var li = document.createElement("li");
      li.className = "page-item" + (disabled ? " disabled" : "") + (active ? " active" : "");
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "page-link" + (active ? " fw-bold" : "");
      btn.textContent = label;
      btn.disabled = !!disabled;
      btn.addEventListener("click", function () {
        if (disabled || page === currentPage) return;
        currentPage = page;
        renderTable();
      });
      li.appendChild(btn);
      paginationEl.appendChild(li);
    }
    if (totalRows <= 0) {
      addBtn("<", 1, true, false);
      addBtn("1", 1, false, true);
      addBtn(">", 1, true, false);
      return;
    }
    addBtn("<", Math.max(1, currentPage - 1), currentPage <= 1, false);
    var startPage = Math.max(1, currentPage - 2);
    var endPage = Math.min(totalPages, startPage + 4);
    if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);
    for (var p = startPage; p <= endPage; p += 1) addBtn(String(p), p, false, p === currentPage);
    addBtn(">", Math.min(totalPages, currentPage + 1), currentPage >= totalPages, false);
  }

  function renderTable() {
    if (!tbody) return;
    var filtered = getFilteredRows();
    var totalPages = Math.max(1, Math.ceil(filtered.length / entriesPerPage));
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;
    var start = (currentPage - 1) * entriesPerPage;
    var pageRows = filtered.slice(start, start + entriesPerPage);
    renderPagination(totalPages, filtered.length);

    if (!pageRows.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No edit requests yet.</td></tr>';
      return;
    }

    tbody.innerHTML = pageRows.map(function (row) {
      var rowStatus = statusLabel(row.status_name);
      var pending = rowStatus === "Pending";
      return '' +
        '<tr>' +
          '<td>' + esc(row.request_id) + '</td>' +
          '<td>' + esc(row.resident_id || "-") + '</td>' +
          '<td><div class="fw-semibold">' + esc(row.resident_name || "-") + '</div></td>' +
          '<td class="text-capitalize">' + esc(row.request_type || "-") + '</td>' +
          '<td>' + esc(formatDate(row.created_at)) + '</td>' +
          '<td><span class="status-pill ' + statusPillClass(rowStatus) + '">' + esc(rowStatus) + '</span></td>' +
          '<td><div class="compact-table-actions">' +
            '<button type="button" class="btn btn-primary btn-sm compact-table-btn js-edit-request-action" data-action="view" data-id="' + esc(row.request_id) + '">View</button>' +
            '<button type="button" class="btn btn-success btn-sm compact-table-btn js-edit-request-action" data-action="approve" data-id="' + esc(row.request_id) + '"' + (pending ? "" : " disabled") + '>Approve</button>' +
            '<button type="button" class="btn btn-danger btn-sm compact-table-btn js-edit-request-action" data-action="deny" data-id="' + esc(row.request_id) + '"' + (pending ? "" : " disabled") + '>Deny</button>' +
          '</div></td>' +
        '</tr>';
    }).join("");

    Array.prototype.slice.call(tbody.querySelectorAll(".js-edit-request-action")).forEach(function (button) {
      button.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();
        handleRequestAction(button.getAttribute("data-action"), button.getAttribute("data-id"));
      });
    });
  }

  function buildCurrentItems(req, current) {
    var items = [];
    if (req.request_type === "address") {
      items.push(
        { label: "Unit Number", value: current.address && current.address.unit_number, key: "unit_number" },
        { label: "Street Number", value: current.address && current.address.street_number, key: "street_number" },
        { label: "Street Name", value: current.address && current.address.street_name, key: "street_name" },
        { label: "Phase Number", value: current.address && current.address.phase_number, key: "phase_number" },
        { label: "Subdivision", value: current.address && current.address.subdivision, key: "subdivision" },
        { label: "Area Number", value: current.address && current.address.area_number, key: "area_number" },
        { label: "House Ownership", value: current.address && current.address.house_ownership, key: "house_ownership" },
        { label: "House Type", value: current.address && current.address.house_type, key: "house_type" },
        { label: "Residency Duration", value: current.address && current.address.residency_duration, key: "residency_duration" }
      );
    } else if (req.request_type === "emergency") {
      items.push(
        { label: "Last Name", value: current.emergency && current.emergency.last_name, key: "last_name" },
        { label: "First Name", value: current.emergency && current.emergency.first_name, key: "first_name" },
        { label: "Middle Name", value: current.emergency && current.emergency.middle_name, key: "middle_name" },
        { label: "Suffix", value: current.emergency && current.emergency.suffix, key: "suffix" },
        { label: "Contact Number", value: current.emergency && current.emergency.phone_number, key: "phone_number" },
        { label: "Relationship", value: current.emergency && current.emergency.relationship, key: "relationship" },
        { label: "Address", value: current.emergency && current.emergency.address, key: "address" }
      );
    } else {
      items.push(
        { label: "Last Name", value: current.profile && current.profile.lastname, key: "lastname" },
        { label: "First Name", value: current.profile && current.profile.firstname, key: "firstname" },
        { label: "Middle Name", value: current.profile && current.profile.middlename, key: "middlename" },
        { label: "Suffix", value: current.profile && current.profile.suffix, key: "suffix" },
        { label: "Civil Status", value: current.profile && current.profile.civil_status, key: "civil_status" },
        { label: "Religion", value: current.profile && current.profile.religion, key: "religion" },
        { label: "Occupation", value: current.profile && (current.profile.occupation_detail || current.profile.occupation), key: "occupation_detail" },
        { label: "Sector Membership", value: current.profile && current.profile.sector_membership, key: "sector_membership" },
        { label: "Voter Status", value: current.profile && current.profile.voter_status, key: "voter_status" },
        { label: "Resident Role", value: current.profile && current.profile.head_of_family, key: "head_of_family" }
      );
    }
    return items;
  }

  function renderDocs(docs) {
    if (!editDocsInlineList) return;
    if (!docs || !docs.length) {
      editDocsInlineList.innerHTML = "";
      if (editDocsInlineEmpty) editDocsInlineEmpty.classList.remove("d-none");
      return;
    }
    if (editDocsInlineEmpty) editDocsInlineEmpty.classList.add("d-none");
    editDocsInlineList.innerHTML = docs.map(function (doc) {
      var label = doc.status_name || "PendingReview";
      var lower = label.toLowerCase();
      var cls = lower.indexOf("verified") !== -1 ? "doc-row--verified" :
        (lower.indexOf("rejected") !== -1 || lower.indexOf("denied") !== -1 ? "doc-row--denied" : "doc-row--pending");
      var url = doc.file_url || doc.file_path || "";
      return '' +
        '<div class="doc-row border rounded-3 p-3 ' + cls + '">' +
          '<div class="d-flex justify-content-between align-items-start doc-row__grid">' +
            '<div class="doc-row__info">' +
              '<div class="fw-bold">' + esc(doc.document_type_name || "Document") + '</div>' +
              '<div class="text-muted small">Uploaded: ' + esc(formatDate(doc.upload_timestamp)) + '</div>' +
            '</div>' +
            '<div class="doc-row__view">' +
              '<button type="button" class="btn btn-primary btn-sm js-edit-doc-view" data-doc-url="' + esc(url) + '" data-doc-title="' + esc(doc.document_type_name || "Document") + '">View</button>' +
            '</div>' +
          '</div>' +
        '</div>';
    }).join("");
  }

  function loadRequestDetails(requestId) {
    fetch("../PhpFiles/Admin-End/edit_requests.php?view=" + encodeURIComponent(requestId))
      .then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) {
          if (!res.ok || !data.success) throw new Error(data.message || "Failed to load request details.");
          return data;
        });
      })
      .then(function (data) {
        var req = data.request || {};
        var current = data.current || {};
        var changes = data.requested_changes || {};
        var statusText = statusLabel(req.status_name);

        if (spanRequestId) spanRequestId.textContent = req.request_id || requestId;
        if (spanRequestTypeHeader) spanRequestTypeHeader.textContent = req.request_type ? humanizeKey(req.request_type) : "Request";
        if (txtRequestResident) txtRequestResident.textContent = req.resident_name || "-";
        if (txtRequestResidentId) txtRequestResidentId.textContent = req.resident_id || "-";
        if (txtRequestType) txtRequestType.textContent = req.request_type || "-";
        if (txtRequestStatus) txtRequestStatus.textContent = statusText;
        if (txtRequestCreated) txtRequestCreated.textContent = formatDate(req.created_at);
        if (txtRequestReviewed) txtRequestReviewed.textContent = formatDate(req.reviewed_at);
        var reviewedBy = document.getElementById("txt-requestReviewedBy");
        if (reviewedBy) reviewedBy.textContent = req.reviewed_by_name || "-";

        currentViewedRequestId = req.request_id || requestId;
        currentViewedStatusText = statusText;
        setViewActionButtons(statusText);

        var currentItems = buildCurrentItems(req, current);
        var changeKeys = Object.keys(changes || {});
        renderDetailList(currentDetailsEl, currentItems.map(function (item) {
          return {
            label: item.label,
            value: item.value,
            key: item.key,
            changed: changeKeys.indexOf(item.key) !== -1 && normalizeValue(item.value) !== normalizeValue(changes[item.key])
          };
        }));
        renderDetailList(requestedDetailsEl, currentItems.map(function (item) {
          var requested = changeKeys.indexOf(item.key) !== -1 ? changes[item.key] : item.value;
          requested = formatRequestedValue(item.key, requested);
          return {
            label: item.label,
            value: requested,
            key: item.key,
            changed: normalizeValue(item.value) !== normalizeValue(requested)
          };
        }));

        showModal(viewModalEl);
        if (editDocsInlineLoading) editDocsInlineLoading.classList.remove("d-none");
        if (editDocsInlineEmpty) editDocsInlineEmpty.classList.add("d-none");
        if (editDocsInlineList) editDocsInlineList.innerHTML = "";

        return fetch("../PhpFiles/Admin-End/edit_requests.php?docs=" + encodeURIComponent(currentViewedRequestId));
      })
      .then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) {
          if (!res.ok || !data.success) throw new Error(data.message || "Failed to load documents.");
          renderDocs(data.documents || []);
        });
      })
      .catch(function (err) {
        alert(err && err.message ? err.message : "Failed to load request details.");
        if (editDocsInlineEmpty) {
          editDocsInlineEmpty.textContent = err && err.message ? err.message : "Failed to load documents.";
          editDocsInlineEmpty.classList.remove("d-none");
        }
      })
      .then(function () {
        if (editDocsInlineLoading) editDocsInlineLoading.classList.add("d-none");
      });
  }

  function updateRequestStatus(requestId, action) {
    var confirmText = "Are you sure you want to " + action + " this request?";
    closeFloatingActionUi();
    var confirmation = window.UniversalModal && window.UniversalModal.confirm
      ? window.UniversalModal.confirm(confirmText)
      : Promise.resolve(window.confirm(confirmText));

    confirmation.then(function (ok) {
      if (!ok) return null;
      return fetch("../PhpFiles/Admin-End/edit_requests.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken
        },
        body: JSON.stringify({ action: action, request_id: requestId })
      });
    }).then(function (res) {
      if (!res) return null;
      return res.json().catch(function () { return {}; }).then(function (data) {
        if (!res.ok || !data.success) throw new Error(data.message || "Failed to update request.");
        return loadRequests();
      });
    }).then(function () {
      hideModal(viewModalEl);
    }).catch(function (err) {
      alert(err && err.message ? err.message : "Failed to update request.");
    });
  }

  function handleRequestAction(action, requestId) {
    if (!requestId) return;
    if (action === "view") {
      loadRequestDetails(requestId);
      return;
    }
    if (action === "approve") {
      updateRequestStatus(requestId, "approve");
      return;
    }
    if (action === "deny") {
      pendingDenyId = requestId;
      if (denyRemarksEl) denyRemarksEl.value = "";
      if (denyRemarksErrorEl) denyRemarksErrorEl.classList.add("d-none");
      showModal(denyModalEl);
    }
  }

  window.editRequestsHandleAction = handleRequestAction;

  function loadRequests() {
    if (!tbody || autoRefreshInFlight) return Promise.resolve();
    autoRefreshInFlight = true;
    setRefreshLoading(true);
    return fetch("../PhpFiles/Admin-End/edit_requests.php?fetch=1")
      .then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) {
          if (!res.ok || !data.success) throw new Error(data.message || "Failed to load edit requests.");
          allRequests = data.requests && data.requests.length ? data.requests : [];
          if (pendingBadge) {
            var count = data.pending_count != null ? data.pending_count : allRequests.filter(function (r) {
              return statusLabel(r.status_name) === "Pending";
            }).length;
            pendingBadge.textContent = String(count);
            pendingBadge.classList.toggle("d-none", count <= 0);
          }
          renderTable();
        });
      })
      .catch(function (err) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">' + esc(err && err.message ? err.message : "Failed to load edit requests.") + '</td></tr>';
      })
      .then(function () {
        autoRefreshInFlight = false;
        setRefreshLoading(false);
      });
  }

  function scheduleAutoRefresh() {
    if (autoRefreshTimeout) clearTimeout(autoRefreshTimeout);
    autoRefreshTimeout = setTimeout(function () {
      loadRequests().then(scheduleAutoRefresh);
    }, 30000);
  }

  statusButtons.forEach(function (btn) {
    btn.addEventListener("click", function () {
      statusButtons.forEach(function (b) { b.classList.remove("active"); });
      btn.classList.add("active");
      activeStatus = btn.getAttribute("data-filter") || "ALL";
      currentPage = 1;
      renderTable();
    });
  });
  if (searchInput) searchInput.addEventListener("input", function () { currentPage = 1; renderTable(); });
  if (typeFilterSelect) typeFilterSelect.addEventListener("change", function () { currentPage = 1; renderTable(); });
  typeFilterCheckboxes.forEach(function (cb) {
    cb.addEventListener("change", function () { currentPage = 1; renderTable(); });
  });
  if (entriesPerPageInput) {
    entriesPerPageInput.addEventListener("change", function () {
      entriesPerPage = Math.max(1, parseInt(entriesPerPageInput.value || "20", 10) || 20);
      entriesPerPageInput.value = String(entriesPerPage);
      currentPage = 1;
      renderTable();
    });
  }
  if (btnRefreshTable) btnRefreshTable.addEventListener("click", function () { loadRequests(); });

  document.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function (button) {
    button.addEventListener("click", function () {
      if (!window.bootstrap || !window.bootstrap.Modal) hideModal(button.closest(".modal"));
    });
  });

  if (btnViewApproveRequest) {
    btnViewApproveRequest.addEventListener("click", function () {
      if (currentViewedRequestId && currentViewedStatusText === "Pending") updateRequestStatus(currentViewedRequestId, "approve");
    });
  }
  if (btnViewDenyRequest) {
    btnViewDenyRequest.addEventListener("click", function () {
      if (!currentViewedRequestId || currentViewedStatusText !== "Pending") return;
      pendingDenyId = currentViewedRequestId;
      if (denyRemarksEl) denyRemarksEl.value = "";
      if (denyRemarksErrorEl) denyRemarksErrorEl.classList.add("d-none");
      hideModal(viewModalEl);
      showModal(denyModalEl);
    });
  }
  if (btnConfirmDeny) {
    btnConfirmDeny.addEventListener("click", function () {
      if (!pendingDenyId) return;
      var remarks = String(denyRemarksEl && denyRemarksEl.value ? denyRemarksEl.value : "").trim();
      if (!remarks) {
        if (denyRemarksErrorEl) denyRemarksErrorEl.classList.remove("d-none");
        return;
      }
      if (denyRemarksErrorEl) denyRemarksErrorEl.classList.add("d-none");
      fetch("../PhpFiles/Admin-End/edit_requests.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken
        },
        body: JSON.stringify({ action: "deny", request_id: pendingDenyId, admin_notes: remarks })
      }).then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) {
          if (!res.ok || !data.success) throw new Error(data.message || "Failed to deny request.");
          hideModal(denyModalEl);
          pendingDenyId = null;
          return loadRequests();
        });
      }).catch(function (err) {
        alert(err && err.message ? err.message : "Failed to deny request.");
      });
    });
  }
  if (editDocsInlineList) {
    editDocsInlineList.addEventListener("click", function (event) {
      var target = event.target && event.target.closest ? event.target.closest(".js-edit-doc-view") : null;
      if (!target) return;
      var url = target.getAttribute("data-doc-url") || "";
      var title = target.getAttribute("data-doc-title") || "Document";
      if (editDocViewerTitle) editDocViewerTitle.textContent = title;
      if (editDocViewerSubtitle) editDocViewerSubtitle.textContent = "";
      if (editDocViewerBody) {
        var ext = (url.split(".").pop() || "").toLowerCase();
        editDocViewerBody.innerHTML = ext === "pdf"
          ? '<iframe src="' + esc(url) + '" style="width:100%;height:70vh;border:0;"></iframe>'
          : '<img src="' + esc(url) + '" alt="' + esc(title) + '" style="max-width:100%;height:auto;display:block;margin:0 auto;">';
      }
      hideModal(viewModalEl);
      showModal(editDocViewerEl);
    });
  }
  if (editDocViewerReturn) {
    editDocViewerReturn.addEventListener("click", function () {
      hideModal(editDocViewerEl);
      showModal(viewModalEl);
    });
  }

  loadRequests();
  scheduleAutoRefresh();
});
