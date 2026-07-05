<?php
define('ADMIN_GUARD_LIGHT', true);
define('ADMIN_SIDEBAR_DEFER_DB', true);
require_once __DIR__ . "/../includes/admin_guard.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../../Images/favicon_sanjose.png?v=20260211">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint Tracker</title>

    <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
    <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260227-2">
    <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/BlotterMangementStyle.css?v=20260305-1">
    <style>
        .complaint-tracker-shell {
            max-width: 1340px;
            margin: 0 auto;
        }

        #complaintTrackerPageTabs .nav-link {
            color: #d76f12;
            font-weight: 600;
        }

        #complaintTrackerPageTabs .nav-link:hover,
        #complaintTrackerPageTabs .nav-link:focus-visible {
            color: #b45309;
        }

        #complaintTrackerPageTabs .nav-link.active,
        #complaintTrackerPageTabs .nav-link.active:hover,
        #complaintTrackerPageTabs .nav-link.active:focus-visible {
            color: #d76f12;
        }

        #complaintTrackerPanel {
            border-top-left-radius: 0 !important;
        }

        .complaint-tracker-shell .btn,
        #complaintTrackerPageTabs .nav-link {
            transition: opacity 0.16s ease, transform 0.16s ease;
        }

        .complaint-tracker-shell .btn:disabled,
        #complaintTrackerPageTabs .nav-link:disabled {
            cursor: wait;
        }

        .complaint-tracker-shell .admin-list-toolbar {
            overflow-x: visible;
            overflow-y: visible;
            flex-wrap: wrap;
            row-gap: 14px;
            align-items: center;
        }

        .complaint-tracker-shell .admin-list-actions .input-group-text,
        .complaint-tracker-shell .admin-list-actions .form-control {
            height: 38px;
        }

        .complaint-tracker-shell .admin-search {
            min-width: 320px;
            max-width: 420px;
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

        #table-appData td.complaint-table-empty {
            text-align: center !important;
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

        #viewModal .complaint-intake-editor {
            display: flex;
            flex-direction: column;
            align-items: stretch;
        }

        #viewModal .complaint-intake-editor textarea {
            width: 100%;
            min-height: 132px;
        }

        #viewModal .complaint-intake-actions {
            display: flex;
            justify-content: flex-end;
        }

        #viewModal .complaint-admin-editor,
        #viewModal .complaint-witness-editor {
            display: grid;
            gap: 12px;
            padding: 14px 16px;
            border: 1px solid #dde3ea;
            border-radius: 14px;
            background: #fff;
        }

        #viewModal .complaint-admin-editor-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 12px;
            align-items: end;
        }

        #viewModal .complaint-admin-editor-field {
            display: grid;
            gap: 6px;
            min-width: 0;
        }

        #viewModal .complaint-admin-editor-actions,
        #viewModal .complaint-witness-editor-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        #viewModal .complaint-admin-helper {
            margin: 0;
            color: #5f6b7a;
            font-size: 0.92rem;
            line-height: 1.45;
        }

        #viewModal .complaint-admin-warning {
            margin: 0;
            padding: 10px 12px;
            border: 1px solid #f4d29b;
            border-radius: 12px;
            background: #fff7e8;
            color: #8a5406;
            font-size: 0.92rem;
            line-height: 1.45;
        }

        #viewModal .complaint-witness-section {
            display: grid;
            gap: 14px;
        }

        #viewModal .complaint-witness-trigger {
            display: flex;
            justify-content: flex-end;
        }

        #viewModal .complaint-action-dropdown .dropdown-menu {
            display: inline-flex;
            flex-direction: column;
            align-items: stretch;
            width: fit-content;
            min-width: 0;
            max-width: calc(100vw - 2rem);
            margin-top: 0.7rem;
            margin-bottom: 0.7rem;
            padding: 0.7rem 0.45rem;
            border: 1px solid #eadac9;
            border-radius: 18px;
            box-shadow: 0 22px 46px rgba(39, 28, 18, 0.16);
            background: #fffdfa;
        }

        #viewModal .complaint-action-dropdown .dropdown-item {
            display: grid;
            grid-template-columns: 42px minmax(0, 1fr);
            align-items: center;
            gap: 12px;
            width: auto;
            inline-size: max-content;
            max-width: 100%;
            margin-bottom: 0.25rem;
            padding: 0.8rem 0.95rem;
            border: 1px solid transparent;
            border-radius: 12px;
            font-weight: 700;
            text-align: left;
            transition: background-color 0.18s ease, color 0.18s ease, transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }

        #viewModal .complaint-action-dropdown .dropdown-item:last-child {
            margin-bottom: 0;
        }

        #viewModal .complaint-action-dropdown .dropdown-menu li + li {
            margin-top: 0.35rem;
        }

        #viewModal .complaint-action-dropdown .dropdown-item:hover,
        #viewModal .complaint-action-dropdown .dropdown-item:focus,
        #viewModal .complaint-action-dropdown .dropdown-item:active {
            transform: translateX(2px);
            box-shadow: 0 8px 18px rgba(39, 28, 18, 0.08);
        }

        #viewModal .complaint-action-dropdown .complaint-action-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.98rem;
            border: 1px solid transparent;
        }

        #viewModal .complaint-action-dropdown .complaint-action-copy {
            display: grid;
            gap: 2px;
            min-width: 0;
        }

        #viewModal .complaint-action-dropdown .complaint-action-label {
            font-size: 0.98rem;
            font-weight: 800;
            line-height: 1.2;
        }

        #viewModal .complaint-action-dropdown .complaint-action-hint {
            font-size: 0.77rem;
            font-weight: 500;
            line-height: 1.35;
            opacity: 0.82;
        }

        #viewModal .complaint-action-dropdown .dropdown-item.action-investigate {
            color: #9a5b05;
            background: #fff8ec;
            border-color: #f2d8ab;
        }

        #viewModal .complaint-action-dropdown .dropdown-item.action-investigate .complaint-action-icon {
            background: #ffe8bf;
            border-color: #f4d29b;
        }

        #viewModal .complaint-action-dropdown .dropdown-item.action-investigate:hover,
        #viewModal .complaint-action-dropdown .dropdown-item.action-investigate:focus,
        #viewModal .complaint-action-dropdown .dropdown-item.action-investigate:active {
            color: #7a4702;
            background: #ffefcf;
        }

        #viewModal .complaint-action-dropdown .dropdown-item.action-progress {
            color: #0f5f78;
            background: #f1fbfe;
            border-color: #cbe8f0;
        }

        #viewModal .complaint-action-dropdown .dropdown-item.action-progress .complaint-action-icon {
            background: #d8f4fb;
            border-color: #b9e3ee;
        }

        #viewModal .complaint-action-dropdown .dropdown-item.action-progress:hover,
        #viewModal .complaint-action-dropdown .dropdown-item.action-progress:focus,
        #viewModal .complaint-action-dropdown .dropdown-item.action-progress:active {
            color: #0a4d62;
            background: #def5fb;
        }

        #viewModal .complaint-action-dropdown .dropdown-item.action-resolve {
            color: #166534;
            background: #f2fbf4;
            border-color: #cfe6d4;
        }

        #viewModal .complaint-action-dropdown .dropdown-item.action-resolve .complaint-action-icon {
            background: #daf3df;
            border-color: #badfc4;
        }

        #viewModal .complaint-action-dropdown .dropdown-item.action-resolve:hover,
        #viewModal .complaint-action-dropdown .dropdown-item.action-resolve:focus,
        #viewModal .complaint-action-dropdown .dropdown-item.action-resolve:active {
            color: #12542b;
            background: #e0f5e5;
        }

        #viewModal .complaint-action-dropdown .dropdown-item.action-endorse {
            color: #1d4ed8;
            background: #f2f6ff;
            border-color: #d5e1ff;
        }

        #viewModal .complaint-action-dropdown .dropdown-item.action-endorse .complaint-action-icon {
            background: #deebff;
            border-color: #c8d9ff;
        }

        #viewModal .complaint-action-dropdown .dropdown-item.action-endorse:hover,
        #viewModal .complaint-action-dropdown .dropdown-item.action-endorse:focus,
        #viewModal .complaint-action-dropdown .dropdown-item.action-endorse:active {
            color: #1e40af;
            background: #e2ecff;
        }

        #viewModal .complaint-action-dropdown .dropdown-item.action-close {
            color: #475467;
            background: #f8fafc;
            border-color: #dde4ec;
        }

        #viewModal .complaint-action-dropdown .dropdown-item.action-close .complaint-action-icon {
            background: #edf2f7;
            border-color: #d9e0e8;
        }

        #viewModal .complaint-action-dropdown .dropdown-item.action-close:hover,
        #viewModal .complaint-action-dropdown .dropdown-item.action-close:focus,
        #viewModal .complaint-action-dropdown .dropdown-item.action-close:active {
            color: #344054;
            background: #eef3f8;
        }

        #viewModal .complaint-notes-layout {
            display: grid;
            gap: 14px;
        }

        #viewModal .complaint-note-card {
            border: 1px solid #dde3ea;
            border-radius: 14px;
            padding: 16px 18px;
            background: #ffffff;
            box-shadow: none;
        }

        #viewModal .complaint-note-card.is-resident {
            border-color: #dde3ea;
            background: #ffffff;
        }

        #viewModal .complaint-note-card.is-investigation {
            border-color: #dde3ea;
            background: #ffffff;
        }

        #viewModal .complaint-note-card.is-progress {
            border-color: #dde3ea;
            background: #ffffff;
        }

        #viewModal .complaint-note-card.is-resolution {
            border-color: #dde3ea;
            background: #ffffff;
        }

        #viewModal .complaint-note-card-header {
            display: grid;
            gap: 4px;
            margin-bottom: 12px;
        }

        #viewModal .complaint-note-card-title {
            margin: 0;
            font-size: 0.98rem;
            font-weight: 800;
            color: #2c241c;
        }

        #viewModal .complaint-note-card-subtitle {
            margin: 2px 0 0;
            font-size: 0.82rem;
            color: #7a6d61;
        }

        #viewModal .complaint-note-card-body {
            margin: 0;
            color: #2a3342;
            line-height: 1.65;
            white-space: pre-wrap;
            word-break: break-word;
        }

        #viewModal .complaint-note-stack {
            display: grid;
            gap: 14px;
        }

        #viewModal .complaint-note-meta-card {
            border: 1px solid #dde3ea;
            border-radius: 14px;
            padding: 14px 16px;
            background: #fff;
            box-shadow: none;
        }

        #viewModal .complaint-note-meta-label {
            margin: 0 0 6px;
            font-size: 0.83rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #746659;
        }

        #viewModal .complaint-note-meta-value {
            margin: 0;
            color: #243041;
            line-height: 1.55;
            white-space: pre-wrap;
            word-break: break-word;
        }

        #viewModal .complaint-attachment-grid {
            display: grid;
            gap: 14px;
        }

        #viewModal .complaint-attachment-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            border: 1px solid #d7dee8;
            border-radius: 18px;
            background: #ffffff;
            padding: 1rem 1.15rem;
        }

        #viewModal .complaint-attachment-body {
            display: grid;
            gap: 0.3rem;
            min-width: 0;
        }

        #viewModal .complaint-attachment-name {
            margin: 0;
            font-size: 0.98rem;
            font-weight: 800;
            color: #1f2937;
            word-break: break-word;
        }

        #viewModal .complaint-attachment-meta {
            margin: 0;
            color: #5f6b7a;
            line-height: 1.4;
            word-break: break-word;
        }

        #viewModal .complaint-attachment-actions {
            display: flex;
            flex-shrink: 0;
            align-items: center;
        }

        #viewModal .complaint-attachment-actions .btn {
            min-width: 100px;
        }

        #attachmentViewerModal .modal-dialog {
            max-width: min(1080px, calc(100vw - 2rem));
        }

        #attachmentViewerModal .modal-content {
            border-radius: 18px;
            overflow: hidden;
        }

        #attachmentViewerModal .modal-body {
            background: #f5f7fb;
            padding: 1rem;
        }

        #attachmentViewerBody {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: min(70vh, 760px);
            border-radius: 14px;
            background: #ffffff;
            overflow: hidden;
        }

        #attachmentViewerBody img,
        #attachmentViewerBody iframe {
            display: block;
            width: 100%;
            max-width: 100%;
            height: min(70vh, 760px);
            border: 0;
        }

        #attachmentViewerBody img {
            object-fit: contain;
            background: #ffffff;
        }

        #attachmentViewerBody .attachment-viewer-empty {
            padding: 2rem;
            color: #6c757d;
            text-align: center;
        }

        @media (max-width: 767.98px) {
            #complaintTrackerPageTabs {
                gap: 0.5rem;
            }

            #complaintTrackerPageTabs .nav-item {
                width: 100%;
            }

            #complaintTrackerPageTabs .nav-link {
                width: 100%;
            }

            .complaint-tracker-shell .admin-search {
                min-width: 0;
                max-width: none;
                width: 100%;
            }

            #viewModal .complaint-action-dropdown .dropdown-menu {
                width: calc(100vw - 1.5rem);
                margin-top: 0.55rem;
                margin-bottom: 0.55rem;
            }

            #viewModal .complaint-admin-editor-row {
                grid-template-columns: 1fr;
            }

            #viewModal .complaint-admin-editor-actions .btn,
            #viewModal .complaint-witness-editor-actions .btn {
                width: 100%;
            }

            #viewModal .complaint-attachment-card {
                align-items: flex-start;
                flex-direction: column;
            }

            #viewModal .complaint-attachment-actions .btn {
                width: 100%;
            }
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

        <ul class="nav nav-tabs mb-0" id="complaintTrackerPageTabs" style="border-bottom:0">
            <li class="nav-item">
                <button class="nav-link complaint-status-scope-tab active fw-semibold" type="button" data-filter="">All</button>
            </li>
            <li class="nav-item">
                <button class="nav-link complaint-status-scope-tab fw-semibold" type="button" data-filter="active">Active</button>
            </li>
            <li class="nav-item">
                <button class="nav-link complaint-status-scope-tab fw-semibold" type="button" data-filter="resolved">Resolved</button>
            </li>
            <li class="nav-item">
                <button class="nav-link complaint-status-scope-tab fw-semibold" type="button" data-filter="closed">Closed</button>
            </li>
        </ul>

        <div id="complaintTrackerPanel" class="bg-white p-4 rounded-4 shadow-sm border complaint-tracker-shell resident-masterlist-shell">
            <div class="admin-list-toolbar mb-3 pt-2 flex-wrap">
                <div class="admin-list-actions">
                    <div class="input-group admin-search">
                        <input type="text" id="searchInput" class="form-control" placeholder="Complaint ID, complainant, subject, complaint type">
                        <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                    </div>
                    <button class="btn btn-outline-secondary btn-icon admin-filter" type="button" data-bs-toggle="modal" data-bs-target="#modalComplaintFilter" id="btnComplaintFilter" title="Filter" aria-label="Filter">
                        <i class="fas fa-filter"></i>
                        <span class="visually-hidden">Filter</span>
                    </button>
                    <button class="btn btn-outline-secondary btn-icon admin-columns" type="button" data-bs-toggle="modal" data-bs-target="#modalTableColumns" id="btnComplaintColumns" title="Columns" aria-label="Columns">
                        <i class="fa-solid fa-sliders"></i>
                        <span class="visually-hidden">Columns</span>
                    </button>
                    <button class="btn btn-outline-secondary btn-icon admin-refresh" type="button" id="btnComplaintTableRefresh" title="Refresh table" aria-label="Refresh table">
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
                            <td colspan="8" class="text-center text-muted py-4">Complaint tracking will appear here once the admin data endpoint is connected.</td>
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

<div class="modal fade" id="modalComplaintFilter" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-4">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Filter Complaints</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <hr>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="fw-bold small mb-2 d-block">Date Range</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small mb-1" for="complaintFilterDateFrom">From</label>
                            <input type="date" class="form-control" id="complaintFilterDateFrom">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1" for="complaintFilterDateTo">To</label>
                            <input type="date" class="form-control" id="complaintFilterDateTo">
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="fw-bold small mb-2">Type of Complaint</label>
                    <div class="d-flex flex-column gap-2" id="complaintFilterTypeList"></div>
                </div>
                <div class="mb-3">
                    <label class="fw-bold small mb-2">Area Number</label>
                    <div class="d-flex flex-column gap-2" id="complaintFilterAreaList"></div>
                </div>
                <div class="mb-0">
                    <label class="fw-bold small mb-2">Sector Membership</label>
                    <div class="d-flex flex-column gap-2" id="complaintFilterSectorList"></div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" id="btnComplaintFilterReset">Reset</button>
                <button type="button" class="btn btn-primary" id="btnComplaintFilterApply">Apply Filter</button>
            </div>
        </div>
    </div>
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <div class="dropdown complaint-action-dropdown ms-auto" id="complaintActionButtons">
                    <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        Action
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <button type="button" class="dropdown-item action-investigate" id="btnComplaintInvestigate">
                                <span class="complaint-action-icon"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
                                <span class="complaint-action-copy">
                                    <span class="complaint-action-label">Start Investigation</span>
                                    <span class="complaint-action-hint">Review and validate the complaint details.</span>
                                </span>
                            </button>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item action-progress" id="btnComplaintActionInProgress">
                                <span class="complaint-action-icon"><i class="fa-solid fa-person-walking-arrow-right" aria-hidden="true"></i></span>
                                <span class="complaint-action-copy">
                                    <span class="complaint-action-label">Start Action</span>
                                    <span class="complaint-action-hint">Record that barangay action is already ongoing.</span>
                                </span>
                            </button>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item action-resolve" id="btnComplaintResolve">
                                <span class="complaint-action-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
                                <span class="complaint-action-copy">
                                    <span class="complaint-action-label">Mark Resolved</span>
                                    <span class="complaint-action-hint">Finish the complaint with a successful outcome.</span>
                                </span>
                            </button>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item action-endorse" id="btnComplaintEndorse">
                                <span class="complaint-action-icon"><i class="fa-solid fa-share-from-square" aria-hidden="true"></i></span>
                                <span class="complaint-action-copy">
                                    <span class="complaint-action-label">Send for Blotter Review</span>
                                    <span class="complaint-action-hint">Escalate this complaint for blotter evaluation.</span>
                                </span>
                            </button>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item action-close" id="btnComplaintClose">
                                <span class="complaint-action-icon"><i class="fa-solid fa-folder-closed" aria-hidden="true"></i></span>
                                <span class="complaint-action-copy">
                                    <span class="complaint-action-label">Close Complaint</span>
                                    <span class="complaint-action-hint">End the complaint without marking it resolved.</span>
                                </span>
                            </button>
                        </li>
                    </ul>
                </div>
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
                    <label for="complaintActionRemarks" class="form-label">Case Update Notes</label>
                    <textarea id="complaintActionRemarks" class="form-control" rows="4" placeholder="Add status update notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="btnComplaintActionReturn">Return</button>
                <button type="button" class="btn btn-primary" id="btnComplaintActionProceed">Update</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="attachmentViewerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="attachmentViewerTitle">Attachment Viewer</h5>
                    <div class="text-muted small" id="attachmentViewerSubtitle">Review uploaded complaint attachments.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="attachmentViewerBody">
                    <div class="attachment-viewer-empty">Select an attachment to preview.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="btnAttachmentViewerReturn">Return</button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
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
<script src="../../JS-Script-Files/Admin-End/complaintTracker.js?v=20260704-complaint-top-tabs-exact"></script>
</body>
</html>
