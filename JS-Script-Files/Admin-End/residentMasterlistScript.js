document.addEventListener("DOMContentLoaded", () => {
  const tbody = document.getElementById("tableBody");
  const searchInput = document.getElementById("searchInput");

  const statusDisplayMap = {
    "VerifiedResident": "Verified Resident",
    "PendingVerification": "Pending Verification",
    "NotVerified": "Not Verified"
  };

  let allResidents = [];
  let activeFilter = "ALL";
  let currentViewedResident = null;

  const viewModalEl = document.getElementById("modal-viewEntry");
  const verifyResidentModalEl = document.getElementById("modal-verifyResidentConfirm");
  const declineResidentModalEl = document.getElementById("modal-declineResidentConfirm");
  const declineReasonEl = document.getElementById("txt-declineResidentReason");
  const declineReasonErrorEl = document.getElementById("txt-declineResidentReasonError");
  const btnOpenDeclineResident = document.getElementById("btn-openDeclineResident");
  const btnOpenVerifyResident = document.getElementById("btn-openVerifyResident");
  const residentStatusActionsEl = document.getElementById("div-residentStatusActions");
  const btnCancelVerifyResident = document.getElementById("btn-cancelVerifyResident");
  const btnCloseVerifyResidentConfirm = document.getElementById("btn-closeVerifyResidentConfirm");
  const btnConfirmVerifyResident = document.getElementById("btn-confirmVerifyResident");
  const btnCancelDeclineResident = document.getElementById("btn-cancelDeclineResident");
  const btnCloseDeclineResidentConfirm = document.getElementById("btn-closeDeclineResidentConfirm");
  const btnConfirmDeclineResident = document.getElementById("btn-confirmDeclineResident");

  function getStaticModal(el) {
    if (!el || !window.bootstrap?.Modal) return null;
    return bootstrap.Modal.getOrCreateInstance(el, {
      backdrop: "static",
      keyboard: false
    });
  }

  function showViewModal() {
    getStaticModal(viewModalEl)?.show();
  }

  function hideViewModal() {
    getStaticModal(viewModalEl)?.hide();
  }

  function showVerifyModal() {
    getStaticModal(verifyResidentModalEl)?.show();
  }

  function hideVerifyModal() {
    getStaticModal(verifyResidentModalEl)?.hide();
  }

  function showDeclineModal() {
    getStaticModal(declineResidentModalEl)?.show();
  }

  function hideDeclineModal() {
    getStaticModal(declineResidentModalEl)?.hide();
  }

  function renderResidentStatusBanner(status) {
    const banner = document.getElementById("div-statusBanner");
    if (!banner) return;

    banner.innerText = statusDisplayMap[status] ?? "UNSET";
    banner.className = "mb-0";
    banner.classList.add(
      status === "VerifiedResident" ? "bg-statusApproved" :
      status === "PendingVerification" ? "bg-statusPending" :
      status === "NotVerified" ? "bg-statusDenied" :
      "bg-statusUnset"
    );
  }

  function setResidentStatusActionsVisibility(status) {
    if (!residentStatusActionsEl) return;
    const isPendingVerification = status === "PendingVerification";
    residentStatusActionsEl.classList.toggle("d-none", !isPendingVerification);
  }

  function syncResidentStatusLocally(residentId, status) {
    allResidents = allResidents.map((resident) =>
      String(resident.resident_id) === String(residentId)
        ? { ...resident, status }
        : resident
    );
    applyFilterAndRender();
  }

  async function updateResidentStatus(uiStatus, reasonText, source) {
    if (!currentViewedResident?.resident_id) return;

    const payload = new URLSearchParams({
      update_resident_status: "1",
      resident_id: currentViewedResident.resident_id,
      new_status: uiStatus,
      reason_text: reasonText ?? ""
    });

    const response = await fetch("../PhpFiles/Admin-End/residentMasterlist.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: payload
    });

    const result = await response.json().catch(() => null);
    if (!response.ok || !result?.success) {
      throw new Error(result?.message || "Failed to update resident status.");
    }

    const updatedStatus = result.status || (uiStatus === "APPROVED" ? "VerifiedResident" : "NotVerified");
    currentViewedResident.status = updatedStatus;
    syncResidentStatusLocally(currentViewedResident.resident_id, updatedStatus);
    renderResidentStatusBanner(updatedStatus);
    setResidentStatusActionsVisibility(updatedStatus);

    if (source === "verify") {
      hideVerifyModal();
    } else {
      hideDeclineModal();
      if (declineReasonEl) declineReasonEl.value = "";
      if (declineReasonErrorEl) declineReasonErrorEl.classList.add("d-none");
    }

    showViewModal();
    await fetchResidents(searchInput?.value.trim() ?? "");
  }

  // ========================
  // FETCH RESIDENTS
  // ========================
  function fetchResidents(search = "") {
    const url = `../PhpFiles/Admin-End/residentMasterlist.php?fetch=true&search=${encodeURIComponent(search)}`;
    return fetch(url)
      .then(res => res.json())
      .then(data => {
        allResidents = Array.isArray(data) ? data : [];
        applyFilterAndRender();
        return allResidents;
      })
      .catch(err => {
        console.error("Fetch error:", err);
        return [];
      });
  }
  fetchResidents();

  // ========================
  // FILTER BUTTONS
  // ========================
  document.querySelectorAll(".status-filter-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      document.querySelectorAll(".status-filter-btn").forEach(b => b.classList.remove("active"));
      btn.classList.add("active");
      activeFilter = btn.dataset.filter;
      applyFilterAndRender();
    });
  });

  // ========================
  // FILTER MODAL
  // ========================
  const btnApplyFilter = document.getElementById("btnApplyFilter");
  if (btnApplyFilter) {
    btnApplyFilter.addEventListener("click", () => {
      const checkedBoxes = document.querySelectorAll(".filter-checkbox:checked");
      const filters = {};
      checkedBoxes.forEach(cb => {
        const field = cb.dataset.field;
        if (!filters[field]) filters[field] = [];
        filters[field].push(cb.value);
      });

      const filtered = allResidents.filter(res => {
        for (const field in filters) {
          if (!filters[field].includes(String(res[field]))) return false;
        }
        return true;
      });

      renderTable(filtered);

      const filterModalEl = document.getElementById("modalFilter");
      if (filterModalEl && window.bootstrap?.Modal) {
        const filterModal = bootstrap.Modal.getInstance(filterModalEl);
        filterModal?.hide();
      }
    });
  }

  // ========================
  // TABLE RENDER
  // ========================
  function renderTable(data) {
    tbody.innerHTML = "";
    if (!data.length) {
      tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted">No records found</td></tr>`;
      return;
    }

    data.forEach(row => {
      const badge =
        row.status === "VerifiedResident" ? "success" :
        row.status === "PendingVerification" ? "warning text-dark" :
        row.status === "NotVerified" ? "danger" : "secondary";

      const tr = document.createElement("tr");
      const canArchive = row.status !== "NotVerified" && row.status !== "PendingVerification";
      const canViewDocs = row.status === "PendingVerification";
      tr.innerHTML = `
        <td class="fw-bold">${row.resident_id}</td>
        <td>${row.full_name}</td>
        <td><span class="badge bg-${badge}">${statusDisplayMap[row.status] ?? "UNSET"}</span></td>
        <td class="d-flex gap-1">
          <button type="button" class="btn btn-primary btn-sm text-white viewEntryBtn">View</button>
          ${canViewDocs ? `<button type="button" class="btn btn-info btn-sm text-white viewDocsBtn">Documents</button>` : ""}
          ${canArchive ? `<button type="button" class="btn btn-warning btn-sm text-dark archiveEntryBtn">Archive</button>` : ""}
        </td>
        
      `;

      tr.querySelector(".viewEntryBtn").addEventListener("click", () => openViewEntry(row));
      const docsBtn = tr.querySelector(".viewDocsBtn");
      if (docsBtn) docsBtn.addEventListener("click", () => openDocsModal(row));
      const archiveBtn = tr.querySelector(".archiveEntryBtn");
      if (archiveBtn) archiveBtn.addEventListener("click", () => archiveEntry(row));

      tbody.appendChild(tr);
    });
  }

  function applyFilterAndRender() {
    if (activeFilter === "ALL") renderTable(allResidents);
    else renderTable(allResidents.filter(r => r.status === activeFilter));
  }

  // ========================
  // SEARCH
  // ========================
  let searchTimeout;
  if (searchInput) {
    searchInput.addEventListener("input", () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => fetchResidents(searchInput.value.trim()), 300);
    });
  }

  // ========================
  // RESIDENT STATUS ACTIONS
  // ========================
  if (btnOpenVerifyResident) {
    btnOpenVerifyResident.addEventListener("click", () => {
      if (!currentViewedResident) return;
      hideViewModal();
      showVerifyModal();
    });
  }

  if (btnOpenDeclineResident) {
    btnOpenDeclineResident.addEventListener("click", () => {
      if (!currentViewedResident) return;
      if (declineReasonEl) declineReasonEl.value = "";
      if (declineReasonErrorEl) declineReasonErrorEl.classList.add("d-none");
      hideViewModal();
      showDeclineModal();
    });
  }

  const backToViewFromVerify = () => {
    hideVerifyModal();
    showViewModal();
  };

  const backToViewFromDecline = () => {
    hideDeclineModal();
    if (declineReasonEl) declineReasonEl.value = "";
    if (declineReasonErrorEl) declineReasonErrorEl.classList.add("d-none");
    showViewModal();
  };

  if (btnCancelVerifyResident) {
    btnCancelVerifyResident.addEventListener("click", backToViewFromVerify);
  }
  if (btnCloseVerifyResidentConfirm) {
    btnCloseVerifyResidentConfirm.addEventListener("click", backToViewFromVerify);
  }
  if (btnCancelDeclineResident) {
    btnCancelDeclineResident.addEventListener("click", backToViewFromDecline);
  }
  if (btnCloseDeclineResidentConfirm) {
    btnCloseDeclineResidentConfirm.addEventListener("click", backToViewFromDecline);
  }

  if (btnConfirmVerifyResident) {
    btnConfirmVerifyResident.addEventListener("click", async () => {
      if (!currentViewedResident) return;
      btnConfirmVerifyResident.disabled = true;
      btnConfirmVerifyResident.innerText = "Verifying...";
      try {
        await updateResidentStatus("APPROVED", "", "verify");
      } catch (e) {
        alert(e?.message || "Failed to verify resident.");
      } finally {
        btnConfirmVerifyResident.disabled = false;
        btnConfirmVerifyResident.innerText = "Verify";
      }
    });
  }

  if (btnConfirmDeclineResident) {
    btnConfirmDeclineResident.addEventListener("click", async () => {
      if (!currentViewedResident) return;
      const reason = declineReasonEl?.value.trim() ?? "";
      if (!reason) {
        if (declineReasonErrorEl) declineReasonErrorEl.classList.remove("d-none");
        declineReasonEl?.focus();
        return;
      }
      if (declineReasonErrorEl) declineReasonErrorEl.classList.add("d-none");

      btnConfirmDeclineResident.disabled = true;
      btnConfirmDeclineResident.innerText = "Declining...";
      try {
        await updateResidentStatus("DENIED", reason, "decline");
      } catch (e) {
        alert(e?.message || "Failed to decline resident.");
      } finally {
        btnConfirmDeclineResident.disabled = false;
        btnConfirmDeclineResident.innerText = "Decline";
      }
    });
  }

 // ========================
// VIEW ENTRY MODAL
// ========================
function openViewEntry(data) {
  currentViewedResident = data;
  document.getElementById("input-appId").value = data.resident_id;
  document.getElementById("span-displayID").innerText = "#" + data.resident_id;
  const idPictureEl = document.getElementById("img-modalIdPicture");
  if (idPictureEl) {
    const placeholder = "../Images/Profile-Placeholder.png";
    const candidate = (data.id_picture_url ?? "").trim();
    idPictureEl.src = candidate !== "" ? candidate : placeholder;
  }

  // Personal Info
  document.getElementById("txt-modalName").innerText = data.full_name ?? "—";
  const dob = data.birthdate ?? "";
  document.getElementById("txt-modalDob").innerText = dob || "—";
  const ageEl = document.getElementById("txt-modalAge");
  if (ageEl) {
    if (!dob) {
      ageEl.innerText = "—";
    } else {
      const dobDate = new Date(dob);
      if (Number.isNaN(dobDate.getTime())) {
        ageEl.innerText = "—";
      } else {
        const today = new Date();
        let age = today.getFullYear() - dobDate.getFullYear();
        const m = today.getMonth() - dobDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dobDate.getDate())) {
          age -= 1;
        }
        ageEl.innerText = String(age);
      }
    }
  }
  document.getElementById("txt-modalSex").innerText = data.sex ?? "—";
  document.getElementById("txt-modalCivilStatus").innerText = data.civil_status ?? "—";
  document.getElementById("txt-modalHeadOfFam").innerText = data.head_of_family === 1 ? "Yes" : "No";
  document.getElementById("txt-modalVoterStatus").innerText = data.voter_status === 1 ? "Registered" : "Not Registered";
  document.getElementById("txt-modalOccupation").innerText = data.occupation_display || "Unemployed";
  document.getElementById("txt-modalReligion").innerText = data.religion ?? "—";
  document.getElementById("txt-modalSectorMembership").innerText = data.sector_membership ?? "—";

  // Emergency Info
  document.getElementById("txt-modalEmergencyFullName").innerText = data.emergency_full_name ?? "—";
  document.getElementById("txt-modalEmergencyContactNumber").innerText = data.emergency_contact_number ?? "—";
  document.getElementById("txt-modalEmergencyRelationship").innerText = data.emergency_relationship ?? "—";
  document.getElementById("txt-modalEmergencyAddress").innerText = data.emergency_address ?? "—";

  // Address Info (hide empty fields including label)
  const isEmpty = (value) => value === null || value === undefined || String(value).trim() === "";
  const setAddressField = (containerId, valueId, value) => {
    const container = document.getElementById(containerId);
    const valueEl = document.getElementById(valueId);
    if (!container || !valueEl) return;
    if (isEmpty(value)) {
      container.classList.add("d-none");
      valueEl.innerText = "";
      return;
    }
    container.classList.remove("d-none");
    valueEl.innerText = value;
  };

  setAddressField("addr-unit-number", "txt-modalUnitNumber", data.unit_number);
  setAddressField("addr-house-number", "txt-modalHouseNum", data.house_number);
  setAddressField("addr-street-name", "txt-modalStreetName", data.street_name);
  setAddressField("addr-phase-number", "txt-modalPhaseNumber", data.phase_number);
  setAddressField("addr-subdivision", "txt-modalSubdivision", data.subdivision);
  setAddressField("addr-area-number", "txt-modalAreaNumber", data.area_number);

  // Read-only
  document.getElementById("txt-modalBarangay").innerText = "Barangay San Jose";
  document.getElementById("txt-modalMunicipalityCity").innerText = "Rodriguez (Montalban)";
  document.getElementById("txt-modalProvince").innerText = "Rizal";

  // House Info
  document.getElementById("txt-modalHouseOwnership").innerText = data.house_ownership ?? "—";
  document.getElementById("txt-modalHouseType").innerText = data.house_type ?? "—";
  document.getElementById("txt-modalResidencyDuration").innerText = data.residency_duration ?? "—";

  // Read-only status banner
  renderResidentStatusBanner(data.status);
  setResidentStatusActionsVisibility(data.status);

  // STATIC MODAL: cannot close by click outside or Esc
  showViewModal();

  // Load verified documents for this resident
  const verifiedListEl = document.getElementById("view-verified-docs");
  const verifiedWrapperEl = document.getElementById("view-verified-docs-wrapper");
  if (verifiedListEl && verifiedWrapperEl) {
    verifiedListEl.innerHTML = "";
    verifiedWrapperEl.classList.add("d-none");
    fetch(`../PhpFiles/Admin-End/residentMasterlist.php?fetch_documents=1&resident_id=${encodeURIComponent(data.resident_id)}`)
      .then(res => res.json())
      .then(items => {
        const docs = Array.isArray(items) ? items : [];
        const verified = docs.filter(d => {
          const isVerified = String(d.verify_status ?? "").toLowerCase().includes("verified");
          const docName = String(d.document_type_name ?? "").toLowerCase();
          const is2x2 = docName === "2x2 picture" || docName.includes("2x2");
          return isVerified && !is2x2;
        });
        if (!verified.length) {
          verifiedWrapperEl.classList.add("d-none");
          return;
        }
        verifiedWrapperEl.classList.remove("d-none");
        verified.forEach(doc => {
          const row = document.createElement("div");
          row.className = "doc-row border rounded-3 p-2 bg-white";

          const rowGrid = document.createElement("div");
          rowGrid.className = "doc-row__grid d-flex align-items-center justify-content-between gap-2";

          const left = document.createElement("div");
          left.className = "doc-row__info";

          const nameRow = document.createElement("div");
          nameRow.className = "doc-row__name";
          const name = document.createElement("div");
          name.className = "fw-bold";
          name.innerText = doc.document_type_name || doc.file_name || "Document";

          const metaRow = document.createElement("div");
          metaRow.className = "doc-row__meta text-muted small";
          const meta = document.createElement("div");
          const uploaded = doc.upload_timestamp ? `Uploaded: ${doc.upload_timestamp}` : "";
          if (uploaded) {
            meta.innerText = uploaded;
            metaRow.appendChild(meta);
          }

          nameRow.appendChild(name);
          left.appendChild(nameRow);
          if (metaRow.childNodes.length) {
            left.appendChild(metaRow);
          }

          const action = document.createElement("div");
          action.className = "doc-row__view";

          if (doc.file_url) {
            const btn = document.createElement("button");
            btn.type = "button";
            btn.className = "btn btn-sm btn-primary";
            btn.innerText = "View";
            btn.addEventListener("click", () => openDocViewer(doc, document.getElementById("modal-viewEntry"), { readOnly: true }));
            action.appendChild(btn);
          }

          rowGrid.appendChild(left);
          rowGrid.appendChild(action);
          row.appendChild(rowGrid);
          verifiedListEl.appendChild(row);
        });
        if (verifiedListEl.childElementCount === 0) {
          verifiedWrapperEl.classList.add("d-none");
        }
      })
      .catch(() => {
        verifiedWrapperEl.classList.add("d-none");
      });
  }
}

// ========================
// VIEW SUBMITTED DOCUMENTS MODAL
// ========================
function openDocsModal(data, opts = {}) {
  const modalEl = document.getElementById("modal-viewDocs");
  const titleEl = document.getElementById("docs-modal-title");
  const listPending = document.getElementById("docs-list-pending");
  const listVerified = document.getElementById("docs-list-verified");
  const listDenied = document.getElementById("docs-list-denied");
  const sectionPending = document.getElementById("docs-section-pending");
  const sectionVerified = document.getElementById("docs-section-verified");
  const sectionDenied = document.getElementById("docs-section-denied");
  const emptyEl = document.getElementById("docs-empty");
  const loadingEl = document.getElementById("docs-loading");

  if (!modalEl) return;

  if (modalEl.parentElement !== document.body) {
    document.body.appendChild(modalEl);
  }

  if (titleEl) {
    titleEl.innerText = `Submitted Documents: #${data.resident_id}`;
  }
  const addressParts = [
    data.unit_number ? `Unit ${data.unit_number}` : "",
    data.house_number || "",
    data.street_name || "",
    data.phase_number || "",
    data.subdivision || "",
    "San Jose",
    data.area_number || "",
    "Rodriguez",
    "Rizal",
    "1860"
  ].filter(Boolean);
  window.currentDocsResident = {
    full_name: data.full_name ?? "—",
    birthdate: data.birthdate ?? "—",
    full_address: addressParts.join(", ") || "—"
  };
  window.lastDocsResident = { ...data };
  if (listPending) listPending.innerHTML = "";
  if (listVerified) listVerified.innerHTML = "";
  if (listDenied) listDenied.innerHTML = "";
  if (sectionPending) sectionPending.classList.add("d-none");
  if (sectionVerified) sectionVerified.classList.add("d-none");
  if (sectionDenied) sectionDenied.classList.add("d-none");
  if (emptyEl) emptyEl.classList.add("d-none");
  if (loadingEl) loadingEl.classList.remove("d-none");

  const url = `../PhpFiles/Admin-End/residentMasterlist.php?fetch_documents=1&resident_id=${encodeURIComponent(data.resident_id)}`;
  fetch(url)
    .then(res => res.json())
    .then(items => {
      if (loadingEl) loadingEl.classList.add("d-none");
      const docs = Array.isArray(items) ? items : [];
      if (!docs.length) {
        if (emptyEl) emptyEl.classList.remove("d-none");
        return;
      }

      const pendingDocs = [];
      const verifiedDocs = [];
      const deniedDocs = [];

      docs.forEach(doc => {
        const status = String(doc.verify_status ?? "").toLowerCase();
        if (status.includes("verified")) verifiedDocs.push(doc);
        else if (status.includes("rejected") || status.includes("denied")) deniedDocs.push(doc);
        else pendingDocs.push(doc);
      });

      const normalizedRemark = (doc) => String(doc.remarks ?? "").trim().toLowerCase();
      const isFrontBackDoc = (doc) => {
        const remark = normalizedRemark(doc);
        return remark === "idfront" || remark === "idback";
      };
      const sideOf = (doc) => (normalizedRemark(doc) === "idfront" ? "front" : "back");

      const mergeFrontBackDocs = (bucketDocs) => {
        const output = [];
        const waiting = new Map();

        const byNewest = [...bucketDocs].sort((a, b) => {
          const at = new Date(a.upload_timestamp || 0).getTime();
          const bt = new Date(b.upload_timestamp || 0).getTime();
          return bt - at;
        });

        byNewest.forEach((doc) => {
          if (!isFrontBackDoc(doc)) {
            output.push(doc);
            return;
          }

          const side = sideOf(doc);
          const opposite = side === "front" ? "back" : "front";
          const key = `${doc.document_type_name || ""}|${doc.verify_status || ""}`;
          const queueKey = `${key}|${opposite}`;
          const ownQueueKey = `${key}|${side}`;
          const queue = waiting.get(queueKey) || [];

          if (queue.length) {
            const pair = queue.shift();
            if (!queue.length) waiting.delete(queueKey);
            else waiting.set(queueKey, queue);

            const frontDoc = side === "front" ? doc : pair;
            const backDoc = side === "back" ? doc : pair;
            const latestTimestamp = [frontDoc.upload_timestamp, backDoc.upload_timestamp]
              .filter(Boolean)
              .sort()
              .pop() || "";

            output.push({
              attachment_id: `${frontDoc.attachment_id}_${backDoc.attachment_id}`,
              document_type_name: `${frontDoc.document_type_name || "ID"} (Front & Back)`,
              verify_status: frontDoc.verify_status || backDoc.verify_status || "",
              upload_timestamp: latestTimestamp,
              file_type: "combined",
              remarks: "front+back",
              combined_files: {
                front: frontDoc,
                back: backDoc
              }
            });
            return;
          }

          const ownQueue = waiting.get(ownQueueKey) || [];
          ownQueue.push(doc);
          waiting.set(ownQueueKey, ownQueue);
        });

        waiting.forEach((q) => q.forEach((doc) => output.push(doc)));
        return output;
      };

      const pendingMerged = mergeFrontBackDocs(pendingDocs);
      const verifiedMerged = mergeFrontBackDocs(verifiedDocs);
      const deniedMerged = mergeFrontBackDocs(deniedDocs);

      const renderDocRow = (doc, container, opts = {}) => {
        const row = document.createElement("div");
        row.className = "doc-row border rounded-3 p-2";

        const rowGrid = document.createElement("div");
        rowGrid.className = "doc-row__grid d-flex align-items-center justify-content-between gap-2";

        const left = document.createElement("div");
        left.className = "doc-row__info";
        const nameRow = document.createElement("div");
        nameRow.className = "doc-row__name";
        const name = document.createElement("div");
        name.className = "fw-bold";
        name.innerText = doc.document_type_name || doc.file_name || "Document";

        const metaRow = document.createElement("div");
        metaRow.className = "doc-row__meta text-muted small";
        const meta = document.createElement("div");
        const uploaded = doc.upload_timestamp ? `Uploaded: ${doc.upload_timestamp}` : "Uploaded: —";
        let statusText = doc.verify_status ? `Status: ${doc.verify_status}` : "Status: —";
        if (opts.showReason && doc.remarks) {
          statusText += ` • Reason: ${doc.remarks}`;
        }
        meta.innerText = `${uploaded} • ${statusText}`;
        metaRow.appendChild(meta);
        nameRow.appendChild(name);
        left.appendChild(nameRow);
        left.appendChild(metaRow);

        const action = document.createElement("div");
        action.className = "doc-row__view";
        if (doc.file_url || doc.combined_files) {
          const btn = document.createElement("button");
          btn.type = "button";
          btn.className = "btn btn-sm btn-primary";
          btn.innerText = "View";
          btn.addEventListener("click", () => openDocViewer(doc, modalEl));
          action.appendChild(btn);
        } else {
          const span = document.createElement("span");
          span.className = "text-muted small";
          span.innerText = "No file";
          action.appendChild(span);
        }

        rowGrid.appendChild(left);
        rowGrid.appendChild(action);

        row.appendChild(rowGrid);
        if (container) container.appendChild(row);
      };

      if (pendingMerged.length) {
        if (sectionPending) sectionPending.classList.remove("d-none");
        pendingMerged.forEach(doc => renderDocRow(doc, listPending));
      }
      if (verifiedMerged.length) {
        if (sectionVerified) sectionVerified.classList.remove("d-none");
        verifiedMerged.forEach(doc => renderDocRow(doc, listVerified));
      }
      if (deniedMerged.length) {
        if (sectionDenied) sectionDenied.classList.remove("d-none");
        deniedMerged.forEach(doc => renderDocRow(doc, listDenied, { showReason: true }));
      }
    })
    .catch(() => {
      if (loadingEl) loadingEl.classList.add("d-none");
      if (emptyEl) emptyEl.classList.remove("d-none");
    });

  if (!opts.refreshOnly) {
    if (window.bootstrap?.Modal) {
      const modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl, {
        backdrop: "static",
        keyboard: false
      });
      modalInstance.show();
    } else {
      modalEl.classList.add("show");
      modalEl.style.display = "block";
      modalEl.removeAttribute("aria-hidden");
      modalEl.setAttribute("aria-modal", "true");
    }
  }
}

function openDocViewer(doc, parentModalEl, opts = {}) {
  const viewerEl = document.getElementById("modal-docViewer");
  const bodyEl = document.getElementById("doc-viewer-body");
  const titleEl = document.getElementById("doc-viewer-title");
  const returnBtn = document.getElementById("doc-viewer-return");
  const infoWrapEl = document.getElementById("doc-viewer-info");
  const infoNameEl = document.getElementById("doc-viewer-fullname");
  const infoBirthdayEl = document.getElementById("doc-viewer-birthday");
  const infoAddressEl = document.getElementById("doc-viewer-fulladdress");
  const actionsEl = document.getElementById("doc-viewer-actions");

  if (!viewerEl || !bodyEl || !returnBtn || !actionsEl) return;

  if (viewerEl.parentElement !== document.body) {
    document.body.appendChild(viewerEl);
  }

  const fileUrl = doc.file_url || "";
  const fileName = doc.document_type_name || doc.file_name || "Document";
  const ext = (doc.file_type || "").toLowerCase() || (fileUrl.split(".").pop() || "").toLowerCase();

  titleEl.innerText = fileName;
  bodyEl.innerHTML = "";
  actionsEl.innerHTML = "";

  const residentInfo = window.currentDocsResident || {};
  if (infoWrapEl) {
    if (opts.readOnly) {
      infoWrapEl.classList.add("d-none");
    } else {
      infoWrapEl.classList.remove("d-none");
    }
  }
  if (infoNameEl) infoNameEl.innerText = residentInfo.full_name ?? "—";
  if (infoBirthdayEl) infoBirthdayEl.innerText = residentInfo.birthdate ?? "—";
  if (infoAddressEl) infoAddressEl.innerText = residentInfo.full_address ?? "—";

  const createPreviewElement = (url, displayName, detectedExt) => {
    let preview;
    if (["jpg", "jpeg", "png", "webp", "gif"].includes(detectedExt)) {
      preview = document.createElement("img");
      preview.src = url;
      preview.alt = displayName;
      preview.className = "img-fluid d-block mx-auto";
      return preview;
    }
    if (detectedExt === "pdf") {
      preview = document.createElement("iframe");
      preview.src = url;
      preview.className = "w-100";
      preview.style.height = "62vh";
      return preview;
    }
    preview = document.createElement("div");
    preview.className = "text-muted";
    preview.innerText = "Preview not available for this file type.";
    return preview;
  };

  if (doc.combined_files?.front && doc.combined_files?.back) {
    const wrapper = document.createElement("div");
    wrapper.className = "row g-3";

    const renderSide = (label, fileDoc) => {
      const col = document.createElement("div");
      col.className = "col-12 col-lg-6";
      const card = document.createElement("div");
      card.className = "border rounded-3 p-2";

      const heading = document.createElement("div");
      heading.className = "fw-bold mb-2";
      heading.innerText = label;

      const docUrl = fileDoc.file_url || "";
      const docExt = (fileDoc.file_type || "").toLowerCase() || (docUrl.split(".").pop() || "").toLowerCase();
      const preview = createPreviewElement(docUrl, `${fileName} - ${label}`, docExt);

      card.appendChild(heading);
      card.appendChild(preview);
      col.appendChild(card);
      return col;
    };

    wrapper.appendChild(renderSide("Front", doc.combined_files.front));
    wrapper.appendChild(renderSide("Back", doc.combined_files.back));
    bodyEl.appendChild(wrapper);
  } else {
    const previewEl = createPreviewElement(fileUrl, fileName, ext);
    bodyEl.appendChild(previewEl);
  }

  if (!opts.readOnly) {
  const statusSelect = document.createElement("select");
  statusSelect.className = "form-select form-select-sm doc-viewer__status";
  statusSelect.style.minWidth = "140px";
  statusSelect.innerHTML = `
    <option value="PENDING">Pending</option>
    <option value="APPROVED">Approved</option>
    <option value="DENIED">Denied</option>
  `;
  const currentStatus = String(doc.verify_status ?? "").toLowerCase();
  if (currentStatus.includes("verified")) statusSelect.value = "APPROVED";
  else if (currentStatus.includes("rejected") || currentStatus.includes("denied")) statusSelect.value = "DENIED";
  else statusSelect.value = "PENDING";

  const reasonInput = document.createElement("input");
  reasonInput.type = "text";
  reasonInput.className = "form-control form-control-sm d-none doc-viewer__reason";
  reasonInput.placeholder = "Cause of denial";
  reasonInput.style.minWidth = "260px";

  const saveBtn = document.createElement("button");
  saveBtn.type = "button";
  saveBtn.className = "btn btn-sm btn-success doc-viewer__update";
  saveBtn.innerText = "Update";
  saveBtn.addEventListener("click", async () => {
    if (statusSelect.value === "DENIED" && !reasonInput.value.trim()) {
      reasonInput.classList.add("is-invalid");
      reasonInput.classList.remove("d-none");
      return;
    }
    reasonInput.classList.remove("is-invalid");
    if (statusSelect.value === "DENIED") {
      const ok = window.confirm("Are you sure you want to deny this document?");
      if (!ok) return;
    }

    const updateOne = async (attachmentId) => {
      const fd = new FormData();
      fd.append("update_document_status", "1");
      fd.append("attachment_id", attachmentId);
      fd.append("new_status", statusSelect.value);
      fd.append("reason_scope", "");
      fd.append("reason_text", reasonInput.value.trim());

      const res = await fetch("../PhpFiles/Admin-End/residentMasterlist.php", {
        method: "POST",
        body: fd
      });
      const result = await res.json().catch(() => null);
      if (!res.ok || !result || !result.success) {
        throw new Error(result?.message || "Failed to update document status.");
      }
      return result;
    };

    saveBtn.disabled = true;
    saveBtn.innerText = "Saving...";
    try {
      const idsToUpdate = doc.combined_files
        ? [doc.combined_files.front.attachment_id, doc.combined_files.back.attachment_id]
        : [doc.attachment_id];

      const results = await Promise.all(idsToUpdate.map((id) => updateOne(id)));
      const result = results[0];

      const imageResult = results.find((r) => r.profile_image_url);
      if (imageResult?.profile_image_url) {
        const newUrl = `${imageResult.profile_image_url}?v=${Date.now()}`;
        if (window.lastDocsResident) {
          window.lastDocsResident.id_picture_url = imageResult.profile_image_url;
        }
        const modalImg = document.getElementById("img-modalIdPicture");
        if (modalImg) {
          modalImg.src = newUrl;
        }
      }
      doc.verify_status = result.status || doc.verify_status;
      if (doc.combined_files) {
        doc.combined_files.front.verify_status = doc.verify_status;
        doc.combined_files.back.verify_status = doc.verify_status;
      }
      if (statusSelect.value === "DENIED") {
        doc.remarks = reasonInput.value.trim();
        if (doc.combined_files) {
          doc.combined_files.front.remarks = doc.remarks;
          doc.combined_files.back.remarks = doc.remarks;
        }
      } else {
        doc.remarks = "";
        if (doc.combined_files) {
          doc.combined_files.front.remarks = "";
          doc.combined_files.back.remarks = "";
        }
      }
      if (window.lastDocsResident) {
        openDocsModal(window.lastDocsResident, { refreshOnly: true });
      }
      saveBtn.disabled = false;
      saveBtn.innerText = "Update";

      const viewerModal = bootstrap.Modal.getInstance(viewerEl);
      if (window.UniversalModal?.open) {
        window.UniversalModal.open({
          title: "Success",
          message: "Document status updated.",
          buttons: [
            {
              label: "Return",
              class: "btn btn-success",
              onClick: () => {
                viewerModal?.hide();
                  if (parentModalEl) {
                    const listModal = bootstrap.Modal.getOrCreateInstance(parentModalEl, {
                      backdrop: "static",
                      keyboard: false
                    });
                    listModal.show();
                    if (window.lastDocsResident) {
                      openDocsModal(window.lastDocsResident, { refreshOnly: true });
                    }
                  }
              }
            }
          ]
        });
      } else {
        viewerModal?.hide();
        if (parentModalEl) {
          const listModal = bootstrap.Modal.getOrCreateInstance(parentModalEl, {
            backdrop: "static",
            keyboard: false
          });
          listModal.show();
        }
      }
    } catch (e) {
      alert(e?.message || "Failed to update document status.");
    } finally {
      saveBtn.disabled = false;
      saveBtn.innerText = "Update";
    }
  });

  statusSelect.addEventListener("change", () => {
    if (statusSelect.value === "DENIED") {
      reasonInput.classList.remove("d-none");
    } else {
      reasonInput.classList.add("d-none");
      reasonInput.value = "";
      reasonInput.classList.remove("is-invalid");
    }
  });

  actionsEl.appendChild(statusSelect);
  actionsEl.appendChild(reasonInput);
  actionsEl.appendChild(saveBtn);
  }

  const parentModal = parentModalEl ? bootstrap.Modal.getInstance(parentModalEl) : null;
  if (parentModal) parentModal.hide();

  const viewerModal = bootstrap.Modal.getOrCreateInstance(viewerEl, {
    backdrop: "static",
    keyboard: false
  });
  viewerModal.show();

  const onReturn = () => {
    viewerModal.hide();
    if (parentModalEl) {
      bootstrap.Modal.getOrCreateInstance(parentModalEl, {
        backdrop: "static",
        keyboard: false
      }).show();
    }
    returnBtn.removeEventListener("click", onReturn);
  };
  returnBtn.addEventListener("click", onReturn);
}

// ========================
// EDIT ENTRY MODAL (DISABLED)
// ========================
/*
function openEditEntry(data) {
  const modalEl = document.getElementById("modal-editEntry");
  const form = modalEl ? modalEl.querySelector("form") : null;

  document.getElementById("edit-residentId").value = data.resident_id;

  // Names
  document.getElementById("edit-lastname").value = data.lastname ?? "";
  document.getElementById("edit-firstname").value = data.firstname ?? "";
  document.getElementById("edit-middlename").value = data.middlename ?? "";
  document.getElementById("edit-suffix").value = data.suffix ?? "";

  // Personal Info
  document.getElementById("edit-birthdate").value = data.birthdate ?? "";
  document.getElementById("edit-sex").value = data.sex ?? "";
  document.getElementById("edit-civil").value = data.civil_status ?? "";
  document.getElementById("edit-religion").value = data.religion ?? "";
  document.getElementById("edit-voterStatus").value = data.voter_status ?? "";
  document.getElementById("edit-occupation").value = data.occupation_display ?? "";

  // Sector Membership
  const sectors = (data.sector_membership ?? "").split(",");
  document.querySelectorAll("#modal-editEntry input[name='sectorMembership[]']").forEach(cb => {
    cb.checked = sectors.includes(cb.value);
  });

  // Emergency Contact
  document.getElementById("edit-userId").value = data.user_id;
  document.getElementById("edit-ec-firstname").value = data.emergency_first_name ?? "";
  document.getElementById("edit-ec-lastname").value = data.emergency_last_name ?? "";
  document.getElementById("edit-ec-middlename").value = data.emergency_middle_name ?? "";
  document.getElementById("edit-ec-suffix").value = data.emergency_suffix ?? "";
  document.getElementById("edit-ec-contact").value = data.emergency_contact_number ?? "";
  document.getElementById("edit-ec-address").value = data.emergency_address ?? "";
  document.getElementById("edit-ec-relationship").value = data.emergency_relationship ?? "";

  // ========================
  // INIT BOOTSTRAP MODAL (STATIC)
  // ========================
  const editModal = new bootstrap.Modal(modalEl, {
    backdrop: 'static',  // cannot close by clicking outside
    keyboard: false     // cannot close by pressing Esc
  });
  editModal.show();

  // ========================
  // CONFIRM CLOSE BUTTON
  // ========================
  const closeBtn = modalEl.querySelector(".btn-close"); // assumes you have <button class="btn-close"></button>
  if (closeBtn) {
    closeBtn.addEventListener("click", e => {
      if (!form || !hasFormChanges(form) || confirm("You have unsaved changes. Are you sure you want to close?")) {
        editModal.hide();
      }
    });
  }

  // ========================
  // CONFIRM SAVE CHANGES
  // ========================
  if (form && !form.dataset.confirmBound) {
    form.addEventListener("submit", e => {
      if (!confirm("Are you sure you want to save these changes?")) {
        e.preventDefault(); // cancel submission if user says no
      }
    });
    form.dataset.confirmBound = "1";
  }

  // ========================
  // SAVE BUTTON ENABLE/DISABLE (ONLY IF CHANGED)
  // ========================
  if (form) {
    const saveBtn = form.querySelector("button[type='submit']");
    const setSaveState = () => {
      if (!saveBtn) return;
      const current = getFormState(form);
      const original = JSON.parse(form.dataset.originalState || "{}");
      saveBtn.disabled = JSON.stringify(current) === JSON.stringify(original);
    };

    form.dataset.originalState = JSON.stringify(getFormState(form));
    setSaveState();

    if (!form.dataset.changeBound) {
      form.addEventListener("input", setSaveState);
      form.addEventListener("change", setSaveState);
      form.dataset.changeBound = "1";
    }
  }
}

function getFormState(form) {
  const state = {};
  const checkboxGroups = {};

  const elements = Array.from(form.elements).filter(el => {
    if (!el.name && !el.id) return false;
    if (el.type === "button" || el.type === "submit" || el.type === "reset") return false;
    if (el.type === "hidden") return false;
    return true;
  });

  elements.forEach(el => {
    const key = el.name || el.id;
    if (el.type === "checkbox") {
      if (!checkboxGroups[key]) checkboxGroups[key] = [];
      if (el.checked) checkboxGroups[key].push(el.value);
      return;
    }

    const value = typeof el.value === "string" ? el.value.trim() : el.value;
    state[key] = value ?? "";
  });

  Object.keys(checkboxGroups).forEach(key => {
    const values = checkboxGroups[key].slice().sort();
    state[key] = values;
  });

  return state;
}

function hasFormChanges(form) {
  const original = JSON.parse(form.dataset.originalState || "{}");
  const current = getFormState(form);
  return JSON.stringify(current) !== JSON.stringify(original);
}
*/

  // ========================
  // "Other" toggle handlers
  // ========================
  document.querySelectorAll(".toggle-other").forEach(sel => {
    sel.addEventListener("change", () => {
      const targetId = sel.dataset.target;
      const target = document.getElementById(targetId);
      if (!target) return;
      if (sel.value === "Other") target.classList.remove("d-none");
      else target.classList.add("d-none");
    });
  });

  // ========================
  // RESET FILTER MODAL
  // ========================
  const btnResetModal = document.getElementById("btnResetModalFilters");
  if (btnResetModal) {
    btnResetModal.addEventListener("click", () => document.querySelectorAll(".filter-checkbox").forEach(cb => cb.checked = false));
  }

  // ========================
  // ARCHIVE RESIDENT
  // ========================
  function archiveEntry(row) {
    if (!confirm(`Archive Resident #${row.resident_id}? This can be restored later.`)) return;

    fetch("../PhpFiles/Admin-End/residentMasterlist.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({
        archive_resident: 1,
        resident_id: row.resident_id
      })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        alert("Resident archived successfully.");
        fetchResidents(searchInput.value.trim());
      } else {
        alert(data.message || "Failed to archive resident.");
      }
    })
    .catch(err => {
      console.error(err);
      alert("Server error.");
    });
  }

});
