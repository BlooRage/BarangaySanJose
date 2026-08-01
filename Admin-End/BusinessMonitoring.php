<?php
require_once __DIR__ . '/../PhpFiles/General/connection.php';
require_once __DIR__ . '/includes/admin_guard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= htmlspecialchars(ensureCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="icon" href="../Images/favicon_sanjose.png?v=20260211">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Monitoring</title>

    <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260227-2">
    <style>
        .business-monitoring-shell {
            max-width: 1540px;
            margin: 0 auto;
        }

        .business-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 0.35rem 0.7rem;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.01em;
        }

        .business-status-badge.pending {
            color: #7a4b00;
            background: #fff0c2;
        }

        .business-status-badge.verified {
            color: #0b5d32;
            background: #d7f5e6;
        }

        .business-status-badge.denied {
            color: #8b1e2d;
            background: #fde2e6;
        }

        .business-status-badge.operational {
            color: #0b5d32;
            background: #d7f5e6;
        }

        .business-status-badge.closed {
            color: #8b1e2d;
            background: #fde2e6;
        }

        .business-status-badge.archived {
            color: #4b5563;
            background: #e5e7eb;
        }

        .business-view-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px 12px;
        }

        .business-view-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .business-view-field.full-width {
            grid-column: 1 / -1;
        }

        .business-view-label {
            font-size: 0.78rem;
            font-weight: 700;
            color: #6b7280;
            margin: 0;
        }

        .business-view-value {
            min-height: 44px;
            border: 1px solid #dbe0e6;
            border-radius: 10px;
            background: #f8fafc;
            padding: 10px 12px;
            color: #111827;
            word-break: break-word;
        }

        .submitted-docs-section {
            margin-top: 20px;
            padding-top: 18px;
            border-top: 1px solid #e5e7eb;
        }

        .submitted-docs-list {
            display: grid;
            gap: 10px;
        }

        .submitted-docs-item {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 12px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .submitted-docs-item__meta {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1 1 auto;
        }

        .submitted-docs-item__label {
            font-weight: 600;
            color: #111827;
        }

        .submitted-docs-item__name {
            color: #6b7280;
            font-size: 0.9rem;
            word-break: break-word;
        }

        .business-document-viewer {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            min-height: 70vh;
            overflow: hidden;
        }

        .business-document-viewer iframe {
            width: 100%;
            min-height: 70vh;
            border: 0;
            background: #fff;
        }

        .business-document-viewer img {
            max-width: 100%;
            max-height: 70vh;
            object-fit: contain;
            display: block;
        }

        .business-document-viewer__placeholder {
            color: #6b7280;
            font-size: 0.95rem;
            text-align: center;
            padding: 24px;
        }

        @media (max-width: 767.98px) {
            .business-view-grid {
                grid-template-columns: 1fr;
            }

            .submitted-docs-item {
                align-items: flex-start;
                flex-direction: column;
            }

            .business-document-viewer,
            .business-document-viewer iframe {
                min-height: 50vh;
            }
        }
    </style>
</head>

<body>
<div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
        <div class="mb-4">
            <h2 class="mb-0" style="font-family: 'Charis SIL Bold'; color: #DE710C;">
                Business Monitoring
            </h2>
        </div>
        <hr><br>

        <div class="bg-white p-4 rounded-4 shadow-sm border business-monitoring-shell resident-masterlist-shell">
            <div class="admin-list-toolbar mb-3 pt-2 flex-wrap">
                <div class="admin-list-tabs">
                    <div class="text-muted small">Shows completed business permit clearances and establishment records.</div>
                </div>

                <div class="admin-list-actions">
                    <div class="input-group admin-search">
                        <input type="text" id="searchInput" class="form-control" placeholder="Plate number, business name, business type, address">
                        <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                    </div>
                    <button class="btn btn-outline-secondary btn-icon admin-filter" type="button" data-bs-toggle="modal" data-bs-target="#modalFilter" id="btnBusinessFilter" title="Filter" aria-label="Filter">
                        <i class="fas fa-filter"></i>
                        <span class="visually-hidden">Filter</span>
                    </button>
                    <button class="btn btn-outline-secondary btn-icon admin-columns" type="button" data-bs-toggle="modal" data-bs-target="#modalTableColumns" id="btnBusinessColumns" title="Columns" aria-label="Columns">
                        <i class="fa-solid fa-sliders"></i>
                        <span class="visually-hidden">Columns</span>
                    </button>
                    <button class="btn btn-outline-secondary btn-icon admin-refresh" type="button" id="btnBusinessRefresh" title="Refresh table" aria-label="Refresh table">
                        <i class="fa-solid fa-arrows-rotate"></i>
                        <span class="visually-hidden">Refresh</span>
                    </button>
                    <span id="businessAutoRefreshCountdown" class="small text-muted"></span>
                </div>
            </div>

            <div class="table-responsive compact-admin-table-shell">
                <table class="table align-middle compact-admin-table compact-admin-table--wide" id="table-businessMonitoring">
                    <thead>
                        <tr class="table-light">
                            <th>Plate Number</th>
                            <th>Business Name</th>
                            <th>Business Type</th>
                            <th>Business Address</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="businessMonitoringTbody">
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Loading business requests...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="resident-table-footer mt-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <label for="businessEntriesPerPageInput" class="small text-muted mb-0">Entries</label>
                    <input
                        id="businessEntriesPerPageInput"
                        type="number"
                        min="1"
                        step="1"
                        value="20"
                        class="form-control form-control-sm resident-entries-input"
                    />
                </div>
                <nav aria-label="Business monitoring pagination">
                    <ul class="pagination pagination-sm mb-0" id="businessPagination"></ul>
                </nav>
            </div>
        </div>
    </main>
</div>

<div class="modal fade" id="businessViewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="businessViewModalTitle">Business Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="businessViewModalBody">
                <div class="text-muted">Select a request to view details.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="businessDocumentModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="businessDocumentModalTitle">Submitted Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" id="businessDocumentModalBody">
                <div class="business-document-viewer">
                    <div class="business-document-viewer__placeholder">Select a document to preview.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Return</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalFilter" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-4">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Filter Businesses</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <hr>

            <div class="modal-body">
                <div class="mb-3">
                    <label class="fw-bold small mb-2 d-block">Date Range</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small mb-1" for="businessFilterDateFrom">From</label>
                            <input type="date" class="form-control" id="businessFilterDateFrom">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1" for="businessFilterDateTo">To</label>
                            <input type="date" class="form-control" id="businessFilterDateTo">
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="fw-bold small mb-2">Status</label>
                    <div class="d-flex flex-column gap-2">
                        <label class="d-flex align-items-center gap-2">
                            <input class="form-check-input m-0 business-filter-checkbox" type="checkbox" value="pending" data-field="status_bucket">
                            <span>Pending</span>
                        </label>
                        <label class="d-flex align-items-center gap-2">
                            <input class="form-check-input m-0 business-filter-checkbox" type="checkbox" value="verified" data-field="status_bucket">
                            <span>Verified</span>
                        </label>
                        <label class="d-flex align-items-center gap-2">
                            <input class="form-check-input m-0 business-filter-checkbox" type="checkbox" value="denied" data-field="status_bucket">
                            <span>Denied</span>
                        </label>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="fw-bold small mb-2">Establishment Status</label>
                    <div class="d-flex flex-column gap-2">
                        <label class="d-flex align-items-center gap-2">
                            <input class="form-check-input m-0 business-filter-checkbox" type="checkbox" value="operational" data-field="establishment_status">
                            <span>Operational</span>
                        </label>
                        <label class="d-flex align-items-center gap-2">
                            <input class="form-check-input m-0 business-filter-checkbox" type="checkbox" value="closed" data-field="establishment_status">
                            <span>Closed</span>
                        </label>
                        <label class="d-flex align-items-center gap-2">
                            <input class="form-check-input m-0 business-filter-checkbox" type="checkbox" value="archived" data-field="establishment_status">
                            <span>Archived</span>
                        </label>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="fw-bold small mb-2">Type of Request</label>
                    <div class="d-flex flex-column gap-2" id="businessFilterTypeList"></div>
                </div>

                <div class="mb-0">
                    <label class="fw-bold small mb-2">Owner Type</label>
                    <div class="d-flex flex-column gap-2">
                        <label class="d-flex align-items-center gap-2">
                            <input class="form-check-input m-0 business-filter-checkbox" type="checkbox" value="Owner" data-field="owner_type">
                            <span>Owner</span>
                        </label>
                        <label class="d-flex align-items-center gap-2">
                            <input class="form-check-input m-0 business-filter-checkbox" type="checkbox" value="Renter" data-field="owner_type">
                            <span>Renter</span>
                        </label>
                    </div>
                </div>

                <div class="mb-0 mt-3">
                    <label class="fw-bold small mb-2">Area Number</label>
                    <div class="d-flex flex-column gap-2" id="businessFilterAreaList"></div>
                </div>

                <div class="mb-0 mt-3">
                    <label class="fw-bold small mb-2">Sector Membership</label>
                    <div class="d-flex flex-column gap-2" id="businessFilterSectorList"></div>
                </div>
            </div>

            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" id="btnBusinessFilterReset">Reset</button>
                <button type="button" class="btn btn-primary" id="btnBusinessFilterApply">Apply Filter</button>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    window.ADMIN_TABLE_COLUMNS_CONFIG = {
        tableSelector: "#table-businessMonitoring",
        modalId: "modalTableColumns",
        listId: "tableColumnsList",
        resetBtnId: "btnTableColumnsReset",
        storageKey: "admin_cols_business_monitoring_v2"
    };
</script>
<script src="../JS-Script-Files/Resident-End/dateFieldModal.js?v=20260707-date-proxy-white"></script>
<script src="../JS-Script-Files/Admin-End/tableColumnsGeneric.js?v=20260215-1"></script>
<script src="../JS-Script-Files/Admin-End/businessMonitoring.js?v=20260801-1"></script>
</body>
</html>
