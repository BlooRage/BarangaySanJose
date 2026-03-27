<?php
require_once __DIR__ . "/includes/admin_guard.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../Images/favicon_sanjose.png?v=20260211">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Household Member Verification</title>

    <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css">
    <style>
        .household-member-verification-shell #btnHouseholdMemberVerificationRefresh.is-loading i {
            animation: adminSpin 900ms linear infinite;
        }
    </style>
</head>
<body>
<div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include 'includes/sidebar.php'; ?>

    <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
        <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C;">Household Member Verification</h2>
        <hr><br>

        <div class="bg-white p-4 rounded-4 shadow-sm border resident-masterlist-shell household-member-verification-shell">
            <div class="admin-list-toolbar mb-3 pt-2 flex-wrap">
                <div class="admin-list-tabs">
                    <button class="btn btn-outline-primary btn-sm status-filter-btn fw-semibold hmv-filter-btn active" data-filter="ALL">&nbsp;&nbsp;All&nbsp;&nbsp;</button>
                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold has-notif hmv-filter-btn" data-filter="PendingReview">
                        &nbsp;&nbsp;Pending <span id="pendingHouseholdMemberBadge" class="pending-count-badge d-none">0</span>
                    </button>
                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold hmv-filter-btn" data-filter="Approved">&nbsp;&nbsp;Approved&nbsp;&nbsp;</button>
                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold hmv-filter-btn" data-filter="Rejected">&nbsp;&nbsp;Rejected&nbsp;&nbsp;</button>
                </div>

                <div class="admin-list-actions">
                    <div class="input-group admin-search">
                        <input type="text" id="hmvSearchInput" class="form-control" placeholder="Request ID, head, member name">
                        <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                    </div>
                    <button class="btn btn-outline-secondary btn-icon admin-filter" type="button" data-bs-toggle="modal" data-bs-target="#modalHouseholdMemberVerificationFilter" id="filterButton" title="Filter" aria-label="Filter">
                        <i class="fas fa-filter"></i>
                        <span class="visually-hidden">Filter</span>
                    </button>
                    <button class="btn btn-outline-secondary btn-icon admin-columns" type="button" data-bs-toggle="modal" data-bs-target="#modalHouseholdMemberVerificationColumns" id="btnHouseholdMemberVerificationColumns" title="Columns" aria-label="Columns">
                        <i class="fa-solid fa-sliders"></i>
                        <span class="visually-hidden">Columns</span>
                    </button>
                    <button class="btn btn-outline-secondary btn-icon admin-refresh" type="button" id="btnHouseholdMemberVerificationRefresh" title="Refresh table" aria-label="Refresh table">
                        <i class="fa-solid fa-arrows-rotate"></i>
                        <span class="visually-hidden">Refresh</span>
                    </button>
                </div>
            </div>

            <div id="householdMemberVerificationEmpty" class="text-muted small d-none">No household member verification requests found.</div>

            <div class="table-responsive compact-admin-table-shell">
                <table class="table align-middle compact-admin-table" id="table-householdMemberVerification">
                    <thead>
                        <tr class="table-light">
                            <th>Request ID</th>
                            <th>Head of Family</th>
                            <th>Member Name</th>
                            <th>Birthdate</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="householdMemberVerificationBody">
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Loading requests...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<div class="modal fade" id="modalHouseholdMemberVerificationFilter" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-4">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Filter Requests</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <hr>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="hmvFilterDateFrom" class="fw-bold small mb-2">Submitted From</label>
                    <input type="date" id="hmvFilterDateFrom" class="form-control">
                </div>
                <div class="mb-2">
                    <label for="hmvFilterDateTo" class="fw-bold small mb-2">Submitted To</label>
                    <input type="date" id="hmvFilterDateTo" class="form-control">
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="btnHmvApplyFilter">Apply Filter</button>
                <button type="button" class="btn btn-warning" id="btnHmvResetFilter"><i class="fas fa-undo"></i>&nbsp;Reset</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalHouseholdMemberVerificationColumns" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Columns</h5>
      </div>
      <div class="modal-body">
        <div class="row g-2" id="householdMemberVerificationColumnsList"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" id="btnHouseholdMemberVerificationColumnsReset">Reset</button>
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modal-householdMemberVerification" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content p-3">
            <div class="modal-header border-0">
                <div>
                    <h5 class="modal-title mb-0">Household Member Verification</h5>
                    <div class="small text-muted" id="hmvModalSubtitle"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Head of Family</div>
                        <div class="fw-semibold" id="hmvModalHeadName">-</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Head Resident ID</div>
                        <div class="fw-semibold" id="hmvModalHeadResidentId">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Member Last Name</div>
                        <div class="fw-semibold" id="hmvModalLastName">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Member First Name</div>
                        <div class="fw-semibold" id="hmvModalFirstName">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Member Birthdate</div>
                        <div class="fw-semibold" id="hmvModalBirthdate">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Middle Name</div>
                        <div class="fw-semibold" id="hmvModalMiddleName">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Suffix</div>
                        <div class="fw-semibold" id="hmvModalSuffix">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Status</div>
                        <div class="fw-semibold" id="hmvModalStatus">-</div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="text-muted small mb-2">Birth Certificate</div>
                    <div id="hmvModalDocumentWrap" class="border rounded p-2 bg-light"></div>
                </div>

                <div>
                    <label for="hmvReviewRemarks" class="form-label small text-muted">Review Remarks</label>
                    <textarea class="form-control" id="hmvReviewRemarks" rows="3" placeholder="Optional remarks for approval or rejection"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 d-flex justify-content-between">
                <div class="d-flex gap-2" id="hmvModalActions">
                    <button type="button" class="btn btn-danger" id="btnHmvReject">Reject</button>
                    <button type="button" class="btn btn-success" id="btnHmvApprove">Approve</button>
                </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  window.ADMIN_TABLE_COLUMNS_CONFIG = {
    tableSelector: "#table-householdMemberVerification",
    modalId: "modalHouseholdMemberVerificationColumns",
    listId: "householdMemberVerificationColumnsList",
    resetBtnId: "btnHouseholdMemberVerificationColumnsReset",
    storageKey: "admin_cols_household_member_verification_v1"
  };
</script>
<script src="../JS-Script-Files/Admin-End/tableColumnsGeneric.js?v=20260215-1"></script>
<script src="../JS-Script-Files/Admin-End/householdMemberVerificationScript.js"></script>
</body>
</html>
