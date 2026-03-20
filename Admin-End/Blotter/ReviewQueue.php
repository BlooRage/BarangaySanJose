<?php
require_once __DIR__ . "/../../PhpFiles/General/connection.php";
require_once __DIR__ . "/../includes/admin_guard.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../../Images/favicon_sanjose.png?v=20260211">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blotter Review Queue</title>
    <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
    <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260227-2">
    <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/BlotterMangementStyle.css?v=20260320-2">
    <style>
        body {
            overflow-x: hidden;
        }

        #main-display {
            min-width: 0;
            overflow-x: hidden;
        }

        #div-tableContainer {
            width: 100%;
            max-width: 100%;
            min-width: 0;
        }

        .blotter-tracker-shell .compact-admin-table-shell {
            width: 100%;
            max-width: 100%;
        }

        #table-appData th,
        #table-appData td {
            text-align: left;
        }

        #viewModal .modal-content {
            border: 1px solid #e9ecef;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }

        #viewModal .modal-header,
        #viewModal .modal-body,
        #viewModal .modal-footer {
            padding: 1rem 1.25rem;
        }

        #viewModal .modal-body {
            background: #fff;
        }

        #viewModal .tracker-profile-view {
            display: grid;
            gap: 12px;
        }

        #viewModal .tracker-form-section {
            border-color: #e78924;
            margin-top: 0;
            display: grid;
            gap: 12px;
        }

        #viewModal .tracker-form-section-title {
            margin: 0;
        }

        #viewModal .tracker-form-grid {
            gap: 12px;
        }

        #viewModal .tracker-form-section > .tracker-form-grid + .tracker-form-grid,
        #viewModal .tracker-form-section > .tracker-form-grid + .tracker-form-field,
        #viewModal .tracker-form-section > .tracker-form-field + .tracker-form-grid,
        #viewModal .tracker-form-section > .tracker-form-field + .tracker-form-field {
            margin-top: 0;
        }

        #viewModal .tracker-form-field {
            gap: 6px;
        }

        #viewModal .tracker-form-label {
            margin: 0;
            line-height: 1.2;
        }

        #viewModal .tracker-form-value {
            line-height: 1.45;
        }
    </style>
</head>
<body>
<div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
        <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C;">Blotter Review Queue</h2>
        <hr><br>

        <div id="div-tableContainer" class="bg-white p-4 rounded-4 shadow-sm border blotter-tracker-shell resident-masterlist-shell">
            <div class="admin-list-toolbar mb-3 pt-2 flex-wrap">
                <div class="admin-list-tabs">
                    <button class="btn btn-outline-primary btn-sm status-filter-btn fw-semibold active" type="button" data-filter="">&nbsp;&nbsp;All&nbsp;&nbsp;</button>
                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold" type="button" data-filter="approved">&nbsp;&nbsp;Approved&nbsp;&nbsp;</button>
                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold" type="button" data-filter="rejected">&nbsp;&nbsp;Rejected&nbsp;&nbsp;</button>
                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold has-notif" type="button" data-filter="pending">
                        &nbsp;&nbsp;Pending
                        <span id="pendingReviewBadge" class="pending-count-badge d-none">0</span>
                    </button>
                </div>

                <div class="admin-list-actions">
                    <div class="input-group admin-search">
                        <input type="text" id="searchInput" class="form-control" placeholder="Request ID, Complaint ID, Complainant, Complaint Type">
                        <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                    </div>
                    <button class="btn btn-outline-secondary btn-icon" type="button" id="btnQueueRefresh" title="Refresh table" aria-label="Refresh table">
                        <i class="fa-solid fa-arrows-rotate"></i>
                        <span class="visually-hidden">Refresh</span>
                    </button>
                </div>
            </div>

            <div class="table-responsive compact-admin-table-shell">
                <table id="table-appData" class="table align-middle compact-admin-table compact-admin-table--wide">
                    <thead>
                        <tr class="table-light">
                            <th>Request ID</th>
                            <th>Complaint ID</th>
                            <th>Requested At</th>
                            <th>Complainant</th>
                            <th>Complaint Type</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <tr>
                            <td colspan="7" class="text-start text-muted py-4">Loading blotter requests...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<div class="modal fade tracker-profile-modal" id="viewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width: 1500px; width: 75vw;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewModalTitle">Blotter Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="viewDetailsBody" class="tracker-profile-view"></div>
            </div>
            <div class="modal-footer d-flex justify-content-between flex-wrap gap-2">
                <div class="d-flex flex-wrap gap-2" id="requestActionButtons">
                    <button type="button" class="btn btn-success" id="btnApproveRequest">Approve and Create Blotter</button>
                    <button type="button" class="btn btn-danger" id="btnRejectRequest">Reject Request</button>
                </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="requestActionModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requestActionModalTitle">Update Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3 d-none" id="blotterNumberGroup">
                    <label for="blotterNumberInput" class="form-label">Blotter Number</label>
                    <input type="text" id="blotterNumberInput" class="form-control" maxlength="50" placeholder="Enter blotter number">
                </div>
                <label for="requestActionNotes" class="form-label">Review Notes</label>
                <textarea id="requestActionNotes" class="form-control" rows="4" placeholder="Add review notes..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="btnRequestActionReturn">Return</button>
                <button type="button" class="btn btn-primary" id="btnRequestActionProceed">Continue</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="requestActionConfirmModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Update</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="requestActionConfirmText">Are you sure you want to update this request?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="btnRequestActionConfirmReturn">Return</button>
                <button type="button" class="btn btn-danger" id="btnRequestActionConfirm">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../JS-Script-Files/Admin-End/blotterReviewQueue.js?v=20260320-1"></script>
</body>
</html>
