<?php
require_once __DIR__ . '/../includes/admin_guard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Certificate Tracker</title>
  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css">
  <style>
    #viewModal .modal-dialog {
      max-width: 980px;
    }
    #viewModal .modal-content {
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      overflow: hidden;
    }
    #viewModal .modal-header {
      border-bottom: 1px solid #e5e7eb;
      background: #f8fafc;
    }
    #viewModal .tracker-profile-view {
      display: grid;
      gap: 12px;
    }
    #viewModal .tracker-profile-section {
      border: 1px solid #edf2f7;
      border-radius: 10px;
      background: #fff;
      padding: 12px;
    }
    #viewModal .tracker-profile-section h6 {
      margin: 0 0 10px;
      font-size: .9rem;
      color: #374151;
      font-weight: 700;
    }
    #viewModal .tracker-profile-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
    }
    #viewModal .tracker-profile-item {
      background: #f9fafb;
      border: 1px solid #eef2f7;
      border-radius: 10px;
      padding: 10px 12px;
    }
    #viewModal .tracker-profile-label {
      margin: 0 0 4px;
      font-size: .75rem;
      text-transform: uppercase;
      letter-spacing: .03em;
      color: #6b7280;
      font-weight: 700;
    }
    #viewModal .tracker-profile-value {
      margin: 0;
      font-size: .95rem;
      color: #111827;
      font-weight: 600;
      word-break: break-word;
    }

    #residentProfileModal #div-modalSizing {
      max-width: 1200px;
      width: 70vw;
    }
    #residentProfileModal .modal-content {
      border: 0;
      border-radius: .5rem;
      padding: 1rem;
      background: #fff;
    }
    @media (max-width: 768px) {
      #viewModal .tracker-profile-grid {
        grid-template-columns: 1fr;
      }
      #residentProfileModal #div-modalSizing {
        width: 96vw;
      }
    }
  </style>
</head>
<body>
<div class="d-flex" style="min-height: 100vh;">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <main id="main-display" class="flex-grow-1 p-4 p-md-5 bg-light">
    <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C; font-size: 48px;">Certificate Issuance</h2>
    <hr class="mb-4">

    <div class="bg-white p-4 rounded-4 shadow-sm border">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="btn-group" role="group" aria-label="Stage filter">
          <button type="button" class="btn btn-outline-secondary active" data-stage-filter="">All</button>
          <button type="button" class="btn btn-outline-secondary" data-stage-filter="submitted">Submitted</button>
          <button type="button" class="btn btn-outline-secondary" data-stage-filter="for_payment">For Payment</button>
          <button type="button" class="btn btn-outline-secondary" data-stage-filter="payment_submitted">Pending Payment</button>
          <button type="button" class="btn btn-outline-secondary" data-stage-filter="finance">Finance</button>
          <button type="button" class="btn btn-outline-secondary" data-stage-filter="ready_for_claim">Ready</button>
          <button type="button" class="btn btn-outline-secondary" data-stage-filter="completed">Completed</button>
          <button type="button" class="btn btn-outline-secondary" data-stage-filter="rejected">Rejected</button>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
          <button type="button" id="btnRefreshList" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrows-rotate me-1"></i>Refresh
          </button>
          <select id="documentTypeFilter" class="form-select" style="max-width: 240px;">
            <option value="">Filter: All Documents</option>
          </select>
          <div class="input-group" style="max-width: 360px;">
            <input type="text" id="searchInput" class="form-control" placeholder="Request ID, resident ID, document">
            <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
          </div>
        </div>
      </div>

      <div class="btn-group mb-3" role="group" aria-label="Status tabs">
        <button type="button" class="btn btn-outline-secondary active" data-status-filter="all">All</button>
        <button type="button" class="btn btn-outline-secondary" data-status-filter="verified">Verified</button>
        <button type="button" class="btn btn-outline-secondary" data-status-filter="denied">Denied</button>
        <button type="button" class="btn btn-outline-secondary" data-status-filter="pending">Pending</button>
      </div>

      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr class="table-light">
              <th>Request ID</th>
              <th>Resident ID</th>
              <th>Full Name</th>
              <th>Information</th>
              <th>Purpose</th>
              <th>Status</th>
              <th>Submitted Date</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="tableBody">
            <tr>
              <td colspan="8" class="text-center text-muted py-4">Loading requests...</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="actionForm" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title" id="actionModalTitle">Update Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="actionType" name="action">
        <input type="hidden" id="actionRequestId" name="request_id">

        <div id="actionReasonWrap" class="d-none mb-3">
          <label class="form-label">Reason</label>
          <textarea id="actionReason" name="reason" class="form-control" rows="3"></textarea>
        </div>

        <div id="actionAmountWrap" class="d-none mb-3">
          <label class="form-label">Amount</label>
          <input id="actionAmount" name="amount" type="number" min="0" step="0.01" class="form-control">
        </div>

        <div id="actionOrWrap" class="d-none mb-3">
          <label class="form-label">OR Number</label>
          <input id="actionOr" name="or_number" type="text" class="form-control">
        </div>

        <div id="actionIssuedWrap" class="d-none mb-3">
          <label class="form-label">Issued File (optional)</label>
          <input id="actionIssued" name="issued_file" type="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
        </div>

        <div id="actionModalError" class="alert alert-danger d-none mb-0"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Submit</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade tracker-profile-modal" id="viewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Request Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="viewDetailsBody" class="tracker-profile-view"></div>
      </div>
      <div class="modal-footer d-flex justify-content-between flex-wrap gap-2">
        <div id="viewModalActions" class="d-flex flex-wrap gap-2"></div>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="paymentProofModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Payment Proof</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="paymentProofWrap" class="w-100 text-center"></div>
      </div>
      <div class="modal-footer">
        <a id="paymentProofOpenNew" class="btn btn-outline-primary" target="_blank" rel="noopener">Open in New Tab</a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade tracker-profile-modal" id="residentProfileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" id="div-modalSizing">
    <div class="modal-content border-0 rounded-2 p-4">
      <div class="modal-header border-0">
        <h3 class="fw-bold">Resident Details: <span id="span-displayID" class="text-warning">#—</span></h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="p-3 rounded-3 mb-3 border-0 bg-white">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="fw-bold mb-0" style="color: #000;">Personal Information</h5>
          </div>

          <div class="row g-3 align-items-center">
            <div class="col-md-3 d-flex justify-content-center align-items-center">
              <img id="img-modalIdPicture"
                   src="../Images/Profile-Placeholder.png"
                   alt="Resident 2x2 image"
                   class="img-fluid rounded-circle"
                   style="width: clamp(120px, 18vw, 170px); height: clamp(120px, 18vw, 170px); object-fit: cover;">
            </div>

            <div class="col-md-9">
              <div class="row g-3">
                <div class="col-md-12 col-lg-4"><p class="text-muted small mb-0">Full Name:</p><p id="txt-modalName" class="fw-bold mb-0">—</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Sex:</p><p id="txt-modalSex" class="fw-bold mb-0">—</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Religion:</p><p id="txt-modalReligion" class="fw-bold mb-0">—</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Age:</p><p id="txt-modalAge" class="fw-bold mb-0">—</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Civil Status:</p><p id="txt-modalCivilStatus" class="fw-bold mb-0">—</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Occupation:</p><p id="txt-modalOccupation" class="fw-bold mb-0">—</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Date of Birth:</p><p id="txt-modalDob" class="fw-bold mb-0">—</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Head of Family:</p><p id="txt-modalHeadOfFam" class="fw-bold mb-0">—</p></div>
                <div class="col-md-6 col-lg-4"><p class="text-muted small mb-0">Voter Status:</p><p id="txt-modalVoterStatus" class="fw-bold mb-0">—</p></div>
                <div class="col-12">
                  <p class="text-muted small mb-0">Sector Membership:</p>
                  <p id="txt-modalSectorMembership" class="fw-bold mb-0">—</p>
                  <div id="div-modalSectorProofStatuses" class="mt-2 d-flex flex-wrap gap-2"></div>
                  <div id="div-modalSectorProofHint" class="text-muted small mt-1">
                    Sector proof status is based on uploaded documents tagged per sector.
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <hr class="my-2">

        <div class="p-3 rounded-3 mb-3 border-0 bg-white">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="fw-bold mb-0" style="color: #000;">Emergency Contact</h5>
          </div>

          <div class="row g-3">
            <div class="col-md-4"><p class="text-muted small mb-0">Full Name:</p><p id="txt-modalEmergencyFullName" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">Contact Number:</p><p id="txt-modalEmergencyContactNumber" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">Relationship:</p><p id="txt-modalEmergencyRelationship" class="fw-bold mb-0">—</p></div>
            <div class="col-md-12"><p class="text-muted small mb-0">Address:</p><p id="txt-modalEmergencyAddress" class="fw-bold mb-0">—</p></div>
          </div>
        </div>

        <hr class="my-2">

        <div class="p-3 rounded-3 mb-3 border-0 bg-white">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="fw-bold mb-0" style="color: #000;">Address Information</h5>
          </div>

          <div class="row g-3">
            <div class="col-md-4" id="addr-unit-number"><p class="text-muted small mb-0">Unit Number:</p><p id="txt-modalUnitNumber" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4" id="addr-house-number"><p class="text-muted small mb-0">House Number:</p><p id="txt-modalHouseNum" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4" id="addr-street-name"><p class="text-muted small mb-0">Street Name:</p><p id="txt-modalStreetName" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4" id="addr-phase-number"><p class="text-muted small mb-0">Phase:</p><p id="txt-modalPhaseNumber" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4" id="addr-subdivision"><p class="text-muted small mb-0">Subdivision:</p><p id="txt-modalSubdivision" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4" id="addr-area-number"><p class="text-muted small mb-0">Area Number:</p><p id="txt-modalAreaNumber" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">Barangay:</p><p id="txt-modalBarangay" class="fw-bold mb-0">Barangay San Jose</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">Municipality / City:</p><p id="txt-modalMunicipalityCity" class="fw-bold mb-0">Rodriguez (Montalban)</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">Province:</p><p id="txt-modalProvince" class="fw-bold mb-0">Rizal</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">House Ownership:</p><p id="txt-modalHouseOwnership" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">House Type:</p><p id="txt-modalHouseType" class="fw-bold mb-0">—</p></div>
            <div class="col-md-4"><p class="text-muted small mb-0">Residency Duration:</p><p id="txt-modalResidencyDuration" class="fw-bold mb-0">—</p></div>
          </div>
        </div>

        <div id="div-statusReadOnlyGroup" class="mt-4">
          <h5 class="fw-bold mb-2" style="color: #000;">Resident Status</h5>
          <div id="div-statusBanner" class="mb-0"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../JS-Script-Files/Admin-End/certificateTrackerScript.js"></script>
</body>
</html>
