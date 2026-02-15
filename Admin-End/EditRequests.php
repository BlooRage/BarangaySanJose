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
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260212-5">
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/EditRequestsStyle.css?v=20260213-2">
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

        <div id="div-tableContainer" class="bg-white p-4 rounded-4 shadow-sm border">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-outline-primary btn-sm status-filter-btn active" data-filter="ALL">All</button>
                    <div class="position-relative">
                        <button class="btn btn-outline-custom btn-sm status-filter-btn fw-bold" data-filter="Pending">
                            Pending
                        </button>
                        <span id="pendingRequestBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none">
                            0
                        </span>
                    </div>
                    <button class="btn btn-outline-custom btn-sm status-filter-btn fw-bold" data-filter="Approved">Approved</button>
                    <button class="btn btn-outline-custom btn-sm status-filter-btn fw-bold" data-filter="Denied">Denied</button>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <select class="form-select form-select-sm request-type-filter" style="max-width: 180px;">
                        <option value="ALL">All Types</option>
                        <option value="profile">Profile</option>
                        <option value="address">Address</option>
                        <option value="emergency">Emergency</option>
                    </select>
                    <div class="input-group" style="max-width: 300px;">
                        <input type="text" id="searchInput" class="form-control" placeholder="Resident ID or Name">
                        <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
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
                            <span id="badge-requestStatus" class="status-pill pending">Pending</span>
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
            </div>

            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
<script src="../JS-Script-Files/Admin-End/editRequestsScript.js"></script>
</body>
</html>
