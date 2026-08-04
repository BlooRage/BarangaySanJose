<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../Images/favicon_sanjose.png?v=20260211">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Requests</title>

    <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260227-2">
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/EditRequestsStyle.css?v=20260227-5">
    <style>
        #modal-viewRequest .modal-dialog {
            width: min(88vw, 920px);
            max-width: 920px;
            height: 78vh;
        }

        #modal-viewRequest .modal-content {
            border: 0;
            border-radius: .5rem;
            padding: 1rem;
            background: #ffffff;
            overflow: hidden;
            height: 100%;
        }

        #modal-viewRequest .modal-header {
            border-bottom: 1px solid #e9ecef;
            background: #ffffff;
        }

        #modal-viewRequest .modal-body {
            overflow-y: auto;
            overflow-x: hidden;
            min-height: 0;
            background: #ffffff;
            padding: 14px;
        }

        #modal-viewRequest .tracker-profile-view {
            display: grid;
            gap: 12px;
        }

        #modal-viewRequest .tracker-form-section {
            border: 1px solid #e78924;
            background: #ffffff;
            border-radius: 12px;
            padding: 12px;
            margin-top: 10px;
        }

        #modal-viewRequest .tracker-form-section-title {
            margin: 0 0 10px;
            font-size: 1rem;
            font-weight: 700;
            color: #212529;
            border-bottom: 1px dashed #e9ecef;
            padding-bottom: 6px;
        }

        #modal-viewRequest .request-meta__label,
        #modal-viewRequest .request-detail .label {
            margin: 6px 0 0;
            font-size: 0.76rem;
            color: #6b7280;
            font-weight: 700;
        }

        #modal-viewRequest .request-meta__value,
        #modal-viewRequest .request-detail .value,
        #modal-viewRequest .request-meta__sub {
            min-height: 38px;
            border: 1px solid #dbe0e6;
            border-radius: 8px;
            background: #f8fafc;
            padding: 8px 10px;
            font-size: 0.92rem;
            color: #111827;
            font-weight: 500;
            word-break: break-word;
        }

        #modal-viewRequest .request-meta__sub {
            font-size: 0.85rem;
            font-weight: 500;
        }

        #modal-viewRequest .request-meta__grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px 12px;
        }

        #modal-viewRequest .request-meta__cell--wide {
            grid-column: 1 / -1;
        }

        #modal-viewRequest .request-card {
            border: 1px solid #e78924;
            background: #ffffff;
        }

        #modal-viewRequest .request-card--highlight {
            border-color: #e78924;
            background: #ffffff;
        }

        #modal-viewRequest .request-detail {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 6px;
        }

        #modal-viewRequest .request-detail.changed {
            background: #fff8ef;
            border: 1px solid #f1d2a6;
        }
    </style>
</head>
<body>
<div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">

<?php
require_once "../PhpFiles/General/connection.php";
require_once "includes/admin_guard.php";
include "includes/sidebar.php";

$csrfToken = ensureCsrfToken();
?>

    <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
        <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C;">
            Edit Requests
        </h2>
        <hr><br>

        <div id="div-tableContainer" class="bg-white p-4 rounded-4 shadow-sm border edit-requests-shell">
	            <div class="admin-list-toolbar mb-3 pt-2 flex-wrap">
	                <div class="admin-list-tabs">
	                    <button class="btn btn-outline-primary btn-sm status-filter-btn active" data-filter="ALL">&nbsp;&nbsp;All&nbsp;&nbsp;</button>
	                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold" data-filter="Approved">&nbsp;&nbsp;Approved&nbsp;&nbsp;</button>
	                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold" data-filter="Denied">&nbsp;&nbsp;Denied&nbsp;&nbsp;</button>
                        <button class="btn btn-outline-secondary btn-sm status-filter-btn has-notif fw-semibold" data-filter="Pending">
	                        &nbsp;&nbsp;Pending
	                        <span id="pendingRequestBadge" class="pending-count-badge d-none">0</span>
	                    </button>
	                </div>
	
	                <div class="admin-list-actions">
	                    <div class="input-group admin-search">
	                        <input type="text" id="searchInput" class="form-control" placeholder="Resident ID or Name">
	                        <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
	                    </div>
	                    <button class="btn btn-outline-secondary btn-icon admin-filter" type="button" data-bs-toggle="modal" data-bs-target="#modalFilter" id="filterButton" title="Filter" aria-label="Filter">
	                        <i class="fas fa-filter"></i>
	                        <span class="visually-hidden">Filter</span>
	                    </button>
	                    <button class="btn btn-outline-secondary btn-icon admin-columns" type="button" data-bs-toggle="modal" data-bs-target="#modalTableColumns" id="btnEditRequestsColumns" title="Columns" aria-label="Columns">
	                        <i class="fa-solid fa-sliders"></i>
	                        <span class="visually-hidden">Columns</span>
	                    </button>
	                    <button class="btn btn-outline-secondary btn-icon admin-refresh" type="button" id="btnEditRequestsRefresh" title="Refresh table" aria-label="Refresh table">
	                        <i class="fa-solid fa-arrows-rotate"></i>
	                        <span class="visually-hidden">Refresh</span>
	                    </button>
	                    <span id="editRequestsAutoRefreshCountdown" class="small text-muted d-none"></span>
	                </div>
	            </div>

            <!-- FILTER MODAL -->
            <div class="modal fade" id="modalFilter" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content p-4">
                        <div class="modal-header border-0">
                            <h5 class="modal-title fw-bold">Filter Requests</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
	                        <hr>
	                        <div class="modal-body">
	                            <div class="form-label fw-semibold mb-2">Request Type</div>
	                            <div class="d-flex flex-column gap-2">
	                                <label class="d-flex align-items-center gap-2">
	                                    <input class="form-check-input m-0 request-type-checkbox" type="checkbox" value="profile" name="requestTypeFilter">
	                                    <span>Profile</span>
	                                </label>
	                                <label class="d-flex align-items-center gap-2">
	                                    <input class="form-check-input m-0 request-type-checkbox" type="checkbox" value="address" name="requestTypeFilter">
	                                    <span>Address</span>
	                                </label>
	                                <label class="d-flex align-items-center gap-2">
	                                    <input class="form-check-input m-0 request-type-checkbox" type="checkbox" value="emergency" name="requestTypeFilter">
	                                    <span>Emergency</span>
	                                </label>
	                            </div>
	                            <div class="small text-muted mt-3">If none are selected, all request types will be shown.</div>
	                        </div>
                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TABLE COLUMNS MODAL -->
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

            <div class="table-responsive compact-admin-table-shell">
                <table class="table align-middle compact-admin-table" id="table-editRequests">
                    <thead>
                        <tr class="table-light">
                            <th>Request ID</th>
                            <th>Resident ID</th>
                            <th>Resident</th>
                            <th>Type</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                No edit requests yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="resident-table-footer mt-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <label for="editRequestsEntriesPerPageInput" class="small text-muted mb-0">Entries</label>
                    <input
                        id="editRequestsEntriesPerPageInput"
                        type="number"
                        min="1"
                        step="1"
                        value="20"
                        class="form-control form-control-sm resident-entries-input"
                    />
                </div>
                <nav aria-label="Edit requests pagination">
                    <ul class="pagination pagination-sm mb-0" id="editRequestsPagination"></ul>
                </nav>
            </div>
        </div>
    </main>
</div>

<!-- VIEW REQUEST MODAL -->
<div class="modal fade" id="modal-viewRequest" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-2 p-4">
            <div class="modal-header border-0">
                <h5 class="modal-title">
                    Edit Request - <span id="span-requestTypeHeader"></span>: <span id="span-requestId" class="text-warning"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="div-infoContainer request-meta tracker-form-section">
                    <h5 class="tracker-form-section-title">Request Summary</h5>
                    <div class="request-meta__grid">
                        <div class="request-meta__cell request-meta__cell--wide">
                            <div class="request-meta__label">Resident ID:</div>
                            <div id="txt-requestResidentId" class="request-meta__value"></div>
                        </div>
                        <div class="request-meta__cell request-meta__cell--wide">
                            <div class="request-meta__label">Resident:</div>
                            <div id="txt-requestResident" class="request-meta__value"></div>
                        </div>
                        <div class="request-meta__cell">
                            <div class="request-meta__label">Request Type:</div>
                            <div id="txt-requestType" class="request-meta__value text-capitalize"></div>
                        </div>
                        <div class="request-meta__cell">
                            <div class="request-meta__label">Status:</div>
                            <div id="txt-requestStatus" class="request-meta__value"></div>
                        </div>
                        <div class="request-meta__row3 request-meta__cell--wide">
                            <div class="request-meta__cell">
                                <div class="request-meta__label">Submitted:</div>
                                <div id="txt-requestCreated" class="request-meta__value"></div>
                            </div>
                            <div class="request-meta__cell">
                                <div class="request-meta__label">Reviewed:</div>
                                <div id="txt-requestReviewed" class="request-meta__value"></div>
                            </div>
                            <div class="request-meta__cell">
                                <div class="request-meta__label">Reviewed By:</div>
                                <div id="txt-requestReviewedBy" class="request-meta__value"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="request-card tracker-form-section">
                            <h5 class="tracker-form-section-title">Current Details</h5>
                            <div id="currentDetails" class="request-detail-list"></div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="request-card request-card--highlight tracker-form-section">
                            <h5 class="tracker-form-section-title">Requested Changes</h5>
                            <div id="requestedDetails" class="request-detail-list"></div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 tracker-form-section">
                    <h6 class="tracker-form-section-title">Submitted Documents</h6>
                    <div id="edit-docs-inline-loading" class="text-muted small mb-2">Loading documents...</div>
                    <div id="edit-docs-inline-empty" class="text-muted small d-none">No submitted documents found.</div>
                    <div id="edit-docs-inline-list" class="d-flex flex-column gap-2"></div>
                </div>
            </div>

            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- DOCUMENT VIEWER MODAL -->
<div class="modal fade doc-viewer-modal" id="modal-editDocViewer" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content p-3">
      <div class="modal-header border-0">
        <div class="w-100">
          <h5 class="fw-bold mb-0" id="edit-doc-viewer-title">Document Preview</h5>
          <div class="small text-muted" id="edit-doc-viewer-subtitle"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="edit-doc-viewer-body" class="w-100 mb-3"></div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-outline-secondary" id="edit-doc-viewer-return">Return</button>
      </div>
    </div>
  </div>
</div>

<!-- DENY REQUEST MODAL -->
<div class="modal fade" id="modal-denyRequest" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Deny Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label small text-muted">Remarks / Reason</label>
                <textarea id="denyRemarks" class="form-control" rows="3" placeholder="Enter denial remarks..."></textarea>
                <div id="denyRemarksError" class="text-danger small mt-2 d-none">Remarks are required.</div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="btnConfirmDeny">Deny Request</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  window.ADMIN_TABLE_COLUMNS_CONFIG = {
    tableSelector: "#table-editRequests",
    modalId: "modalTableColumns",
    listId: "tableColumnsList",
    resetBtnId: "btnTableColumnsReset",
    storageKey: "admin_cols_edit_requests_v1"
  };
  window.ADMIN_EDIT_REQUESTS_CSRF_TOKEN = <?= json_encode($csrfToken, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="../JS-Script-Files/Admin-End/tableColumnsGeneric.js?v=20260215-1"></script>
<script src="../JS-Script-Files/Admin-End/editRequestsScript.js?v=20260804-resident-role"></script>
</body>
</html>
