<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="/Images/favicon_sanjose.png?v=20260211">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Requests</title>

    <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260219-2">
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/EditRequestsStyle.css?v=20260222-1">
</head>
<body>
<div class="d-flex" style="min-height: 100vh;">

<?php
require_once "../PhpFiles/General/connection.php";
require_once "includes/admin_guard.php";
include "includes/sidebar.php";
?>

    <main id="main-display" class="flex-grow-1 p-4 p-md-5 bg-light">
        <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C; font-size: 48px;">
            Edit Requests
        </h2>
        <hr><br>

        <div id="div-tableContainer" class="bg-white p-4 pt-3 rounded-4 shadow-sm border edit-requests-shell">
	            <div class="admin-list-toolbar mb-3 pt-2">
	                <div class="admin-list-tabs pt-2">
	                    <button class="btn btn-outline-primary btn-sm status-filter-btn active fw-bold" data-filter="ALL">&nbsp;&nbsp;All&nbsp;&nbsp;</button>
	                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-bold" data-filter="Approved">&nbsp;&nbsp;Approved&nbsp;&nbsp;</button>
	                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-bold" data-filter="Denied">&nbsp;&nbsp;Denied&nbsp;&nbsp;</button>
                        <button class="btn btn-outline-secondary btn-sm status-filter-btn text-center has-notif fw-bold " data-filter="Pending">
	                        &nbsp;&nbsp;Pending&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
	                        <span id="pendingRequestBadge" class="pending-count-badge d-none text-center fw-bold">0</span>
	                    </button>
	                </div>
	
	                <div class="admin-list-actions admin-list-actions--linear">
	                    <div class="input-group admin-search">
	                        <input type="text" id="searchInput" class="form-control" placeholder="Resident ID or Name">
	                        <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
	                    </div>
	                    <button class="btn btn-outline-secondary btn-linear-control" type="button" data-bs-toggle="modal" data-bs-target="#modalFilter" id="filterButton" title="Filter" aria-label="Filter">
	                        <i class="fas fa-filter"></i>
	                        <span class="visually-hidden">Filter</span>
	                    </button>
	                    <button class="btn btn-outline-secondary btn-linear-control" type="button" data-bs-toggle="modal" data-bs-target="#modalTableColumns" id="btnEditRequestsColumns" title="Columns" aria-label="Columns">
	                        <i class="fa-solid fa-sliders"></i>
	                        <span class="visually-hidden">Columns</span>
	                    </button>
	                    <button class="btn btn-outline-secondary btn-linear-control" type="button" id="btnEditRequestsRefresh" title="Refresh table" aria-label="Refresh table">
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

            <div class="table-responsive">
                <table class="table align-middle" id="table-editRequests">
                    <thead>
                        <tr class="table-light">
                            <th>Request ID</th>
                            <th>Resident</th>
                            <th>Type</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
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
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width: 1200px; width: 75vw;">
        <div class="modal-content border-0 rounded-2 p-4">
            <div class="modal-header border-0">
                <h3 class="fw-bold mb-0">
                    Edit Request - <span id="span-requestTypeHeader"></span>: <span id="span-requestId" class="text-warning"></span>
                </h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="div-infoContainer request-meta">
                    <div class="request-meta__grid">
                        <div class="request-meta__cell">
                            <div class="request-meta__label">Resident:</div>
                            <div id="txt-requestResident" class="request-meta__value"></div>
                            <div id="txt-requestResidentId" class="request-meta__sub"></div>
                        </div>
                        <div class="request-meta__cell">
                            <div class="request-meta__label">Request Type:</div>
                            <div id="txt-requestType" class="request-meta__value text-capitalize"></div>
                        </div>
                        <div class="request-meta__cell">
                            <div class="request-meta__label">Status:</div>
                            <span id="badge-requestStatus" class="badge bg-warning text-white">Pending</span>
                        </div>
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

                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="request-card">
                            <h5 class="fw-bold mb-3">Current Details</h5>
                            <div id="currentDetails" class="request-detail-list"></div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="request-card request-card--highlight">
                            <h5 class="fw-bold mb-3">Requested Changes</h5>
                            <div id="requestedDetails" class="request-detail-list"></div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <h6 class="fw-bold mb-2">Submitted Documents</h6>
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
</script>
<script src="../JS-Script-Files/Admin-End/tableColumnsGeneric.js?v=20260215-1"></script>
<script src="../JS-Script-Files/Admin-End/editRequestsScript.js?v=20260219-1"></script>
</body>
</html>
