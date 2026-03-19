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
    <title>Complaint Tracker</title>

    <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
    <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260227-2">
    <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/BlotterMangementStyle.css?v=20260305-1">
    <style>
        .complaint-tracker-shell {
            max-width: 1340px;
            margin: 0 auto;
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

        #table-appData th,
        #table-appData td {
            text-align: left !important;
            vertical-align: middle;
        }

        .status-pill.info {
            color: #2049b3;
            background: #d6e2f2;
            border: 2px solid #c0d1e8;
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
        <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C;">
            Complaint Tracker
        </h2>
        <hr><br>

        <div id="div-tableContainer" class="bg-white p-4 rounded-4 shadow-sm border complaint-tracker-shell resident-masterlist-shell">
            <div class="admin-list-toolbar mb-3 pt-2 flex-wrap">
                <div class="admin-list-tabs">
                    <button class="btn btn-outline-primary btn-sm status-filter-btn active" type="button" data-filter="">&nbsp;&nbsp;All&nbsp;&nbsp;</button>
                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold" type="button" data-filter="resolved">&nbsp;&nbsp;Resolved&nbsp;&nbsp;</button>
                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold" type="button" data-filter="escalated">&nbsp;&nbsp;Escalated&nbsp;&nbsp;</button>
                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold has-notif" type="button" data-filter="pending">
                        &nbsp;&nbsp;Pending
                        <span class="pending-count-badge d-none" id="pendingComplaintBadge">0</span>
                    </button>
                </div>

                <div class="admin-list-actions">
                    <div class="input-group admin-search">
                        <input type="text" id="searchInput" class="form-control" placeholder="Complaint ID, complainant, subject, complaint type">
                        <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                    </div>
                    <button class="btn btn-outline-secondary btn-icon" type="button" data-bs-toggle="modal" data-bs-target="#modalTableColumns" id="btnComplaintColumns" title="Columns" aria-label="Columns">
                        <i class="fa-solid fa-sliders"></i>
                        <span class="visually-hidden">Columns</span>
                    </button>
                    <button class="btn btn-outline-secondary btn-icon" type="button" id="btnComplaintTableRefresh" title="Refresh table" aria-label="Refresh table">
                        <i class="fa-solid fa-arrows-rotate"></i>
                        <span class="visually-hidden">Refresh</span>
                    </button>
                </div>
            </div>

            <div class="table-responsive compact-admin-table-shell">
                <table id="table-appData" class="table align-middle compact-admin-table">
                    <thead>
                        <tr class="table-light">
                            <th>Complaint ID</th>
                            <th>Date Submitted</th>
                            <th>Complainant</th>
                            <th>Subject</th>
                            <th>Complaint Type</th>
                            <th>Status</th>
                            <th>Complaint Level</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <tr>
                            <td colspan="8" class="text-start text-muted py-4">Complaint tracking will appear here once the admin data endpoint is connected.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="resident-table-footer mt-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <label for="entriesPerPageInput" class="small text-muted mb-0">Entries</label>
                    <input
                        id="entriesPerPageInput"
                        type="number"
                        min="1"
                        step="1"
                        value="20"
                        class="form-control form-control-sm resident-entries-input"
                    />
                </div>
                <nav aria-label="Complaint pagination">
                    <ul class="pagination pagination-sm mb-0" id="complaintPagination"></ul>
                </nav>
            </div>
        </div>
    </main>
</div>

<div class="modal fade" id="modalTableColumns" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Columns</h5>
            </div>
            <div class="modal-body">
                <div class="row g-2" id="tableColumnsList"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="btnTableColumnsReset">Reset</button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade tracker-profile-modal" id="viewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" id="div-modalSizing" style="max-width: 1500px; width: 75vw;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewModalTitle">Complaint Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="viewDetailsBody" class="tracker-profile-view">
                    <div class="text-muted small">Complaint detail preview will be available once the tracker logic is connected.</div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between flex-wrap gap-2">
                <div class="d-flex flex-wrap gap-2" id="complaintActionButtons">
                    <button type="button" class="btn btn-success" id="btnComplaintResolve">Mark Resolved</button>
                    <button type="button" class="btn btn-primary" id="btnComplaintEndorse">Endorse to Blotter</button>
                    <button type="button" class="btn btn-danger" id="btnComplaintDrop">Drop Complaint</button>
                </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="complaintActionModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="complaintActionModalTitle">Update Complaint</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-0">
                    <label for="complaintActionRemarks" class="form-label">Screening Notes</label>
                    <textarea id="complaintActionRemarks" class="form-control" rows="4" placeholder="Add screening notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="btnComplaintActionReturn">Return</button>
                <button type="button" class="btn btn-primary" id="btnComplaintActionProceed">Update</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="complaintActionConfirmModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Update</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="complaintActionConfirmText">
                Are you sure you want to update this complaint?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="btnComplaintActionConfirmReturn">Return</button>
                <button type="button" class="btn btn-danger" id="btnComplaintActionConfirm">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    window.ADMIN_TABLE_COLUMNS_CONFIG = {
        tableSelector: "#table-appData",
        modalId: "modalTableColumns",
        listId: "tableColumnsList",
        resetBtnId: "btnTableColumnsReset",
        storageKey: "admin_cols_complaint_tracker_v2",
        defaultHiddenIdxs: [3]
    };
</script>
<script src="../../JS-Script-Files/Admin-End/tableColumnsGeneric.js?v=20260215-1"></script>
<script src="../../JS-Script-Files/Admin-End/complaintTracker.js?v=20260311-1"></script>
</body>
</html>



