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
        .status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 96px;
            padding: 0.32rem 0.75rem;
            border-radius: 999px;
            border: 1px solid transparent;
            font-size: 0.82rem;
            font-weight: 700;
            line-height: 1.2;
            white-space: nowrap;
        }
        .status-pill.pending {
            color: #6c5a06;
            background: #f4e8b7;
            border-color: #e9db9f;
        }
        .status-pill.approved {
            color: #166534;
            background: #dcfce7;
            border-color: #bbf7d0;
        }
        .status-pill.denied {
            color: #991b1b;
            background: #fee2e2;
            border-color: #fecaca;
        }
        .household-member-verification-shell #btnHouseholdMemberVerificationRefresh.is-loading i {
            animation: adminSpin 900ms linear infinite;
        }
        #modal-householdMemberVerification .modal-content {
            border: 1px solid #e9ecef;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }
        #modal-householdMemberVerification .modal-header,
        #modal-householdMemberVerification .modal-body,
        #modal-householdMemberVerification .modal-footer {
            padding: 1rem 1.25rem;
        }
        #modal-householdMemberVerification .modal-body {
            background: #fff;
        }
        #modal-householdMemberVerification .tracker-profile-view {
            display: grid;
            gap: 16px;
        }
        #modal-householdMemberVerification .tracker-form-section {
            display: grid;
            gap: 12px;
            border: 1px solid #e78924;
            border-radius: 16px;
            background: #fff;
            padding: 16px;
        }
        #modal-householdMemberVerification .tracker-form-section-title {
            margin: 0;
            padding-bottom: 10px;
            border-bottom: 1px dashed #e5e7eb;
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
        }
        #modal-householdMemberVerification .tracker-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px 16px;
        }
        #modal-householdMemberVerification .tracker-form-grid.cols-3 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        #modal-householdMemberVerification .tracker-form-grid.cols-1 {
            grid-template-columns: 1fr;
        }
        #modal-householdMemberVerification .tracker-form-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 0;
        }
        #modal-householdMemberVerification .tracker-form-field--wide {
            grid-column: 1 / -1;
        }
        #modal-householdMemberVerification .tracker-form-label {
            margin: 0;
            font-size: 0.8rem;
            font-weight: 700;
            color: #64748b;
            line-height: 1.2;
        }
        #modal-householdMemberVerification .tracker-form-value {
            min-height: 46px;
            border: 1px solid #d7dee7;
            border-radius: 12px;
            background: #f8fafc;
            padding: 10px 14px;
            font-size: 0.95rem;
            font-weight: 600;
            color: #0f172a;
            line-height: 1.45;
            word-break: break-word;
            display: flex;
            align-items: center;
        }
        #modal-householdMemberVerification #hmvModalDocumentWrap {
            border: 1px solid #d7dee7 !important;
            border-radius: 12px !important;
            background: #f8fafc !important;
            padding: 12px !important;
        }
        #modal-householdMemberVerification #hmvReviewRemarks {
            border-radius: 12px;
            min-height: 116px;
            resize: vertical;
        }
        #modal-householdMemberVerification .hmv-modal-footer-start,
        #modal-householdMemberVerification .hmv-modal-footer-end {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        #modal-householdMemberVerification .hmv-modal-footer-end {
            justify-content: flex-end;
        }
        #modal-householdMemberVerification .modal-footer .btn {
            min-width: 108px;
        }
        #hmvActionConfirmModal .modal-content {
            border-radius: 16px;
        }
        #hmvActionConfirmModal .modal-footer {
            justify-content: flex-end;
            gap: 10px;
        }
        #hmvActionConfirmModal .modal-footer .btn {
            min-width: 108px;
        }
        #hmvActionConfirmRemarksWrap textarea {
            border-radius: 12px;
            min-height: 120px;
            resize: vertical;
        }
        @media (max-width: 991.98px) {
            #modal-householdMemberVerification .tracker-form-grid,
            #modal-householdMemberVerification .tracker-form-grid.cols-3 {
                grid-template-columns: 1fr;
            }
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
                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold hmv-filter-btn" data-filter="Approved">&nbsp;&nbsp;Approved&nbsp;&nbsp;</button>
                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold hmv-filter-btn" data-filter="Rejected">&nbsp;&nbsp;Rejected&nbsp;&nbsp;</button>
                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold has-notif hmv-filter-btn" data-filter="PendingReview">
                        &nbsp;&nbsp;Pending <span id="pendingHouseholdMemberBadge" class="pending-count-badge d-none">0</span>
                    </button>
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
                <table class="table align-middle compact-admin-table" id="table-householdMemberVerification" data-table-pagination>
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
        <div class="modal-content">
            <div class="modal-header border-0">
                <div>
                    <h5 class="modal-title mb-0">Household Member Verification</h5>
                    <div class="small text-muted" id="hmvModalSubtitle"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="tracker-profile-view">
                    <section class="tracker-form-section">
                        <h6 class="tracker-form-section-title">Verification Summary</h6>
                        <div class="tracker-form-grid">
                            <div class="tracker-form-field">
                                <label class="tracker-form-label">Head of Family</label>
                                <div class="tracker-form-value" id="hmvModalHeadName">-</div>
                            </div>
                            <div class="tracker-form-field">
                                <label class="tracker-form-label">Head Resident ID</label>
                                <div class="tracker-form-value" id="hmvModalHeadResidentId">-</div>
                            </div>
                        </div>
                        <div class="tracker-form-grid cols-3">
                            <div class="tracker-form-field">
                                <label class="tracker-form-label">Member Last Name</label>
                                <div class="tracker-form-value" id="hmvModalLastName">-</div>
                            </div>
                            <div class="tracker-form-field">
                                <label class="tracker-form-label">Member First Name</label>
                                <div class="tracker-form-value" id="hmvModalFirstName">-</div>
                            </div>
                            <div class="tracker-form-field">
                                <label class="tracker-form-label">Member Birthdate</label>
                                <div class="tracker-form-value" id="hmvModalBirthdate">-</div>
                            </div>
                            <div class="tracker-form-field">
                                <label class="tracker-form-label">Middle Name</label>
                                <div class="tracker-form-value" id="hmvModalMiddleName">-</div>
                            </div>
                            <div class="tracker-form-field">
                                <label class="tracker-form-label">Suffix</label>
                                <div class="tracker-form-value" id="hmvModalSuffix">-</div>
                            </div>
                            <div class="tracker-form-field">
                                <label class="tracker-form-label">Status</label>
                                <div class="tracker-form-value" id="hmvModalStatus">-</div>
                            </div>
                        </div>
                    </section>

                    <section class="tracker-form-section">
                        <h6 class="tracker-form-section-title">Birth Certificate</h6>
                        <div id="hmvModalDocumentWrap"></div>
                    </section>

                </div>
            </div>
            <div class="modal-footer border-0 d-flex justify-content-between">
                <div class="hmv-modal-footer-start">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
                <div class="hmv-modal-footer-end" id="hmvModalActions">
                    <button type="button" class="btn btn-danger" id="btnHmvReject">Reject</button>
                    <button type="button" class="btn btn-success" id="btnHmvApprove">Approve</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="hmvActionConfirmModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="hmvActionConfirmTitle">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="hmvActionConfirmMessage">Are you sure you want to continue?</p>
                <div id="hmvActionConfirmRemarksWrap" class="mt-3 d-none">
                    <label for="hmvActionConfirmRemarks" class="form-label small text-muted">Rejection Remarks</label>
                    <textarea class="form-control" id="hmvActionConfirmRemarks" rows="4" placeholder="Enter rejection remarks"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" id="btnHmvReturnToReview">Return</button>
                <button type="button" class="btn btn-primary" id="btnHmvConfirmAction">Confirm</button>
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
<script src="../JS-Script-Files/Resident-End/dateFieldModal.js?v=20260707-date-proxy-white"></script>
<script src="../JS-Script-Files/Admin-End/tableColumnsGeneric.js?v=20260215-1"></script>
<script src="../JS-Script-Files/Admin-End/householdMemberVerificationScript.js?v=20260328-04"></script>
</body>
</html>
