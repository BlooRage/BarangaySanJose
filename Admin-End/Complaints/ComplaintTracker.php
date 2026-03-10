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

        #table-appData th,
        #table-appData td {
            text-align: left;
            vertical-align: middle;
        }

        .status-pill.info {
            color: #2049b3;
            background: #d6e2f2;
            border: 2px solid #c0d1e8;
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

        <div id="div-tableContainer" class="bg-white p-4 rounded-4 shadow-sm border complaint-tracker-shell">
            <div class="admin-list-toolbar mb-3 pt-2 flex-wrap">
                <div class="admin-list-tabs">
                    <button class="btn btn-outline-primary btn-sm status-filter-btn active" type="button" data-filter="">All</button>
                    <button class="btn btn-outline-secondary btn-sm status-filter-btn" type="button" data-filter="pending">Pending</button>
                    <button class="btn btn-outline-secondary btn-sm status-filter-btn" type="button" data-filter="review">Under Review</button>
                    <button class="btn btn-outline-secondary btn-sm status-filter-btn" type="button" data-filter="escalated">Escalated</button>
                </div>

                <div class="admin-list-actions">
                    <div class="input-group admin-search">
                        <input type="text" id="searchInput" class="form-control" placeholder="Case ID, complainant, subject, complaint type">
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

            <div class="table-responsive">
                <table id="table-appData" class="table align-middle">
                    <thead>
                        <tr class="table-light">
                            <th>Case ID</th>
                            <th>Date Submitted</th>
                            <th>Complainant</th>
                            <th>Subject</th>
                            <th>Complaint Type</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <tr>
                            <td colspan="7" class="text-start text-muted py-4">Complaint tracking will appear here once the admin data endpoint is connected.</td>
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
    <div class="modal-dialog modal-xl modal-dialog-centered">
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
            <div class="modal-footer d-flex justify-content-end">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
        storageKey: "admin_cols_complaint_tracker_v1",
        defaultHiddenIdxs: []
    };
</script>
<script src="../../JS-Script-Files/Admin-End/tableColumnsGeneric.js?v=20260215-1"></script>
<script src="../../JS-Script-Files/Admin-End/complaintTracker.js?v=20260311-1"></script>
</body>
</html>
