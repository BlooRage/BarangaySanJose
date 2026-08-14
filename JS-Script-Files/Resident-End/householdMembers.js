document.addEventListener("DOMContentLoaded", () => {
    const csrfToken = String(window.RESIDENT_CSRF_TOKEN || "").trim();
    const grid = document.getElementById("householdMembersGrid");
    const empty = document.getElementById("householdMembersEmpty");
    if (!grid) return;
    let isHeadOfFamily = Boolean(window.RESIDENT_HOUSEHOLD_IS_HEAD);
    let canManageMembers = Boolean(window.RESIDENT_HOUSEHOLD_CAN_MANAGE);

    const setJoinButtonState = (hasHousehold) => {
        const joinBtn = document.getElementById("btnJoinHousehold");
        if (!joinBtn) return;
        if (hasHousehold) {
            joinBtn.disabled = true;
            joinBtn.textContent = "Already Joined";
        } else {
            joinBtn.disabled = false;
            joinBtn.textContent = "Join Household";
        }
    };

    const updateLeaveButtonState = (hasHousehold) => {
        const leaveBtn = document.getElementById("btnLeaveHousehold");
        if (!leaveBtn) return;
        leaveBtn.disabled = !hasHousehold;
    };

    const addressEl = document.getElementById("householdAddress");
    const minorEl = document.getElementById("householdMinorCount");
    const adultEl = document.getElementById("householdAdultCount");
    const pendingWrapEl = document.getElementById("householdPendingRequestsWrap");
    const pendingCountEl = document.getElementById("householdPendingRequestCount");
    const pendingListEl = document.getElementById("householdPendingRequestsList");
    const householdManageNoticeEl = document.getElementById("householdHeadVerificationNotice");
    const householdManageAlertEl = document.getElementById("householdManageAlert");
    const openManageModalBtn = document.querySelector('[data-bs-target="#householdInviteModal"]');

    const syncHouseholdManageUi = () => {
        const blocked = !canManageMembers;
        const message = String(window.RESIDENT_HOUSEHOLD_MANAGE_MESSAGE || "");
        if (openManageModalBtn) {
            openManageModalBtn.disabled = blocked;
        }
        if (householdManageNoticeEl) {
            householdManageNoticeEl.textContent = message;
            householdManageNoticeEl.classList.toggle("d-none", !blocked || !message);
        }
        if (householdManageAlertEl) {
            householdManageAlertEl.textContent = message;
            householdManageAlertEl.classList.toggle("d-none", !blocked || !message);
        }
    };

    const renderHouseholdInfo = (address, minorCount, adultCount) => {
        if (addressEl) {
            addressEl.textContent = address || "—";
        }
        if (minorEl) {
            minorEl.textContent = typeof minorCount === "number" ? String(minorCount) : "0";
        }
        if (adultEl) {
            adultEl.textContent = typeof adultCount === "number" ? String(adultCount) : "0";
        }
    };

    const formatDate = (value) => {
        const raw = String(value || "").trim();
        if (!raw) return "-";
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
        if (!raw) return "-";
        const date = new Date(raw);
        if (Number.isNaN(date.getTime())) return raw;
        return date.toLocaleDateString("en-PH", { year: "numeric", month: "short", day: "numeric" });
    };

    const renderPendingRequests = (requests) => {
        if (!pendingWrapEl || !pendingListEl || !isHeadOfFamily) {
            if (pendingWrapEl) {
                pendingWrapEl.classList.add("d-none");
            }
            return;
        }
        const rows = Array.isArray(requests) ? requests : [];
        if (pendingCountEl) {
            pendingCountEl.textContent = String(rows.length);
        }
        pendingWrapEl.classList.remove("d-none");
        if (!rows.length) {
            pendingListEl.innerHTML = `
                <div class="px-3 py-2 text-muted small">
                    No pending member verification requests.
                </div>
            `;
            return;
        }

        pendingListEl.innerHTML = rows.map((row, index) => `
            <div class="px-3 py-2 ${index < rows.length - 1 ? "border-bottom" : ""}">
                <div class="fw-semibold">${String(row.member_name || "Pending member")}</div>
                <div class="small text-muted">Birthdate: ${formatBirthdate(row.birthdate)}</div>
                <div class="small text-muted">Request ID ${String(row.request_id || "-")} | Submitted ${formatDate(row.submitted_at)}</div>
            </div>
        `).join("");
    };

    const renderMembers = (members, isHead, currentResidentId) => {
        grid.innerHTML = "";
        if (!members || members.length === 0) {
            if (empty) empty.classList.remove("d-none");
            return;
        }
        if (empty) empty.classList.add("d-none");
        members.forEach((member) => {
            const col = document.createElement("div");
            col.className = "col-12 col-md-6 col-lg-4";

            const card = document.createElement("div");
            card.className = "border rounded p-3 h-100 d-flex flex-column";

            const name = document.createElement("div");
            name.className = member.role === "Head" ? "fw-bold" : "fw-semibold";
            name.textContent = member.name || "Member";

            const role = document.createElement("div");
            role.className = "text-muted small";
            if (member.role === "Head") {
                role.innerHTML = 'Role: <span class="text-dark fw-bold">Head</span>';
            } else {
                role.textContent = member.role ? `Role: ${member.role}` : "Role: Member";
            }

            const ageLine = document.createElement("div");
            ageLine.className = "text-muted small";
            const ageLabel = member.age !== null && member.age !== undefined ? member.age : "—";
            ageLine.textContent = `Age: ${ageLabel}`;

            card.appendChild(name);
            card.appendChild(role);
            card.appendChild(ageLine);

            if (isHead && member.role !== "Head") {
                const actions = document.createElement("div");
                actions.className = "mt-2";
                const removeBtn = document.createElement("button");
                removeBtn.type = "button";
                removeBtn.className = "btn btn-outline-danger btn-sm";
                removeBtn.textContent = "Remove";
                removeBtn.disabled = !canManageMembers;
                if (member.resident_id) {
                    removeBtn.dataset.residentId = member.resident_id;
                }
                if (member.info_member_id) {
                    removeBtn.dataset.infoMemberId = member.info_member_id;
                }
                actions.appendChild(removeBtn);
                card.appendChild(actions);
            }

            if (!isHead && member.resident_id === currentResidentId) {
                card.dataset.isSelf = "1";
            }

            col.appendChild(card);
            grid.appendChild(col);
        });
    };

    const loadMembers = async () => {
        try {
            const res = await fetch("../PhpFiles/Resident-End/household_members.php");
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                setJoinButtonState(false);
                updateLeaveButtonState(false);
                renderHouseholdInfo(null, 0, 0);
                renderPendingRequests([]);
                renderMembers([]);
                return;
            }
            setJoinButtonState(!!data.has_household);
            updateLeaveButtonState(!!data.has_household);
            isHeadOfFamily = Boolean(data.is_head ?? window.RESIDENT_HOUSEHOLD_IS_HEAD);
            window.RESIDENT_HOUSEHOLD_IS_HEAD = isHeadOfFamily;
            canManageMembers = Boolean(data.can_manage_members ?? window.RESIDENT_HOUSEHOLD_CAN_MANAGE);
            window.RESIDENT_HOUSEHOLD_CAN_MANAGE = canManageMembers;
            window.RESIDENT_HOUSEHOLD_MANAGE_MESSAGE = String(data.head_verification_message || window.RESIDENT_HOUSEHOLD_MANAGE_MESSAGE || "");
            syncHouseholdManageUi();
            renderHouseholdInfo(data.address || null, data.minor_count ?? 0, data.adult_count ?? 0);
            renderPendingRequests(data.pending_member_requests || []);
            renderMembers(data.members || [], !!data.is_head, data.resident_id || "");
        } catch (e) {
            setJoinButtonState(false);
            updateLeaveButtonState(false);
            renderHouseholdInfo(null, 0, 0);
            renderPendingRequests([]);
            renderMembers([]);
        }
    };

    const confirmAction = (message) => {
        if (window.UniversalModal?.open) {
            return new Promise((resolve) => {
                window.UniversalModal.open({
                    title: "Confirm Action",
                    message,
                    buttons: [
                        { label: "Cancel", class: "btn btn-outline-secondary", onClick: () => resolve(false) },
                        { label: "Confirm", class: "btn btn-danger", onClick: () => resolve(true) },
                    ],
                });
            });
        }
        return window.UniversalModal.confirm(message);
    };

    const handleRemove = async (residentId, infoMemberId) => {
        if (!canManageMembers) {
            alert(window.RESIDENT_HOUSEHOLD_MANAGE_MESSAGE || "Head of family verification is still pending.");
            return;
        }
        const ok = await confirmAction("Remove this member from the household?");
        if (!ok) return;
        try {
            let res;
            if (infoMemberId) {
                res = await fetch("../PhpFiles/Resident-End/household_member_info_action.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        ...(csrfToken ? { "X-CSRF-TOKEN": csrfToken } : {}),
                    },
                    body: JSON.stringify({ household_member_id: infoMemberId }),
                });
            } else {
                res = await fetch("../PhpFiles/Resident-End/household_member_action.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        ...(csrfToken ? { "X-CSRF-TOKEN": csrfToken } : {}),
                    },
                    body: JSON.stringify({ action: "remove", resident_id: residentId }),
                });
            }
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(data.message || "Failed to remove member.");
            }
            loadMembers();
        } catch (err) {
            alert(err?.message || "Failed to remove member.");
        }
    };

    const handleLeave = async () => {
        const ok = await confirmAction("Leave this household?");
        if (!ok) return;
        const leaveBtn = document.getElementById("btnLeaveHousehold");
        if (!leaveBtn) return;
        leaveBtn.disabled = true;
        try {
            const res = await fetch("../PhpFiles/Resident-End/household_member_action.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    ...(csrfToken ? { "X-CSRF-TOKEN": csrfToken } : {}),
                },
                body: JSON.stringify({ action: "leave" }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(data.message || "Failed to leave household.");
            }
            loadMembers();
            const joinBtn = document.getElementById("btnJoinHousehold");
            if (joinBtn) {
                joinBtn.disabled = false;
                joinBtn.textContent = "Join Household";
            }
        } catch (err) {
            alert(err?.message || "Failed to leave household.");
        } finally {
            leaveBtn.disabled = false;
        }
    };

    grid.addEventListener("click", (event) => {
        const target = event.target;
        if (target && target.matches("button[data-resident-id], button[data-info-member-id]")) {
            const residentId = target.getAttribute("data-resident-id");
            const infoMemberId = target.getAttribute("data-info-member-id");
            handleRemove(residentId, infoMemberId);
        }
    });

    const leaveBtn = document.getElementById("btnLeaveHousehold");
    if (leaveBtn) {
        leaveBtn.addEventListener("click", handleLeave);
    }

    window.addEventListener("household:updated", () => {
        loadMembers();
    });

    const refreshIntervalMs = 60000;
    const refreshTimer = window.setInterval(() => {
        if (document.hidden) return;
        loadMembers();
    }, refreshIntervalMs);

    document.addEventListener("visibilitychange", () => {
        if (!document.hidden) {
            loadMembers();
        }
    });

    window.addEventListener("beforeunload", () => {
        window.clearInterval(refreshTimer);
    }, { once: true });

    syncHouseholdManageUi();
    loadMembers();
});
