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
      width: min(92vw, 1180px);
      max-width: 1180px;
      height: 82vh;
    }
    #viewModal .modal-content {
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      overflow: hidden;
      height: 100%;
    }
    #viewModal .modal-header {
      border-bottom: 1px solid #e5e7eb;
      background: #f8fafc;
    }
    #viewModal .tracker-profile-view {
      display: grid;
      gap: 12px;
    }
    #viewModal .modal-body {
      overflow-y: auto;
      overflow-x: hidden;
      min-height: 0;
    }
    #viewModal .tracker-doc-highlight {
      border: 1px solid #fde68a;
      background: #fffbeb;
      color: #92400e;
      border-radius: 10px;
      padding: 10px 12px;
      font-weight: 700;
    }
    #viewModal .tracker-form-section {
      border-top: 1px solid #e5e7eb;
      padding-top: 10px;
      margin-top: 4px;
    }
    #viewModal .tracker-form-section-title {
      margin: 2px 0 10px;
      font-size: 1rem;
      font-weight: 700;
      color: #1f2937;
    }
    #viewModal .tracker-form-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px 12px;
    }
    #viewModal .tracker-form-grid.cols-4 {
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    #viewModal .tracker-form-grid.cols-3 {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    #viewModal .tracker-form-grid.cols-1 {
      grid-template-columns: 1fr;
    }
    #viewModal .tracker-form-field {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    #viewModal .tracker-form-label {
      margin: 0;
      font-size: .76rem;
      color: #6b7280;
      font-weight: 700;
      text-transform: none;
      letter-spacing: 0;
    }
    #viewModal .tracker-form-value {
      min-height: 38px;
      border: 1px solid #dbe0e6;
      border-radius: 8px;
      background: #f8fafc;
      padding: 8px 10px;
      font-size: .92rem;
      color: #111827;
      font-weight: 500;
      word-break: break-word;
    }
    #viewModal .doc-preview-shell {
      display: grid;
      place-items: center;
      padding: 4px 0 14px;
    }
    #viewModal .doc-preview-stage {
      border: 1px solid #d9dee6;
      border-radius: 10px;
      background:
        linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
      padding: 12px;
    }
    #viewModal .doc-preview-label {
      display: inline-block;
      margin-bottom: 10px;
      padding: 4px 10px;
      border-radius: 999px;
      background: #111827;
      color: #fff;
      font-size: .72rem;
      font-weight: 700;
      letter-spacing: .02em;
    }
    #viewModal .doc-preview-paper {
      width: min(100%, 794px);
      min-height: 1123px;
      border: 1px solid #cfd8e3;
      border-radius: 6px;
      background: #fff;
      box-shadow: 0 14px 30px rgba(15, 23, 42, .12);
      padding: 32px 42px;
      position: relative;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency {
      font-family: "Times New Roman", Times, serif;
      padding: 28px 46px 36px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-head-center p {
      font-size: .88rem;
      line-height: 1.08;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-head-center .rep {
      font-size: 1.02rem;
      letter-spacing: .01em;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-head-center .barangay {
      font-size: 1.18rem;
      letter-spacing: .01em;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-head-center .doc-head-office {
      font-size: 1.06rem;
      font-weight: 700;
      letter-spacing: .01em;
      line-height: 1.1;
      border: 0 !important;
      border-left: 0 !important;
      box-shadow: none !important;
      padding: 0 !important;
      margin-left: 0 !important;
      text-indent: 0 !important;
      background: transparent !important;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-head-center .doc-head-office::before,
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-head-center .doc-head-office::after {
      content: none !important;
      display: none !important;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-title {
      font-size: 1.9rem;
      margin: 8px 0 16px;
      letter-spacing: 0;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-title--indigency {
      margin: 14px 0 20px;
      text-align: center;
      font-family: Arial, Helvetica, sans-serif;
      text-transform: uppercase;
      line-height: 1.2;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-title--indigency .office {
      font-size: 17px;
      font-weight: 800;
      letter-spacing: .01em;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-title--indigency .certificate {
      margin-top: 8px;
      font-size: 12px;
      font-weight: 800;
      letter-spacing: .01em;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-body {
      font-size: 1.08rem;
      line-height: 1.75;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-body p {
      margin: 0 0 16px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-body {
      font-size: 1.02rem;
      line-height: 1.72;
      text-align: justify;
      margin-top: 4px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-signature {
      position: absolute;
      right: 66px;
      bottom: 300px;
      margin-top: 0;
      justify-items: center;
      text-align: center;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-signature .name {
      min-width: 260px;
      margin-top: 0;
      padding-top: 6px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-issuedby {
      position: absolute;
      left: 48px;
      bottom: 292px;
      font-size: .95rem;
      line-height: 1.35;
      text-align: left;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-footer {
      position: absolute;
      width: 68%;
      left: 16%;
      bottom: 64px;
      font-family: Arial, Helvetica, sans-serif;
      font-size: .78rem;
      text-align: center;
      font-style: italic;
      color: #111827;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-qr {
      right: 34px;
      bottom: 56px;
      width: 92px;
      font-size: 0;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-preview-qr-box {
      width: 84px;
      height: 84px;
      border-style: solid;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-to-block {
      display: grid;
      grid-template-columns: 56px 18px 1fr;
      align-items: start;
      margin: 10px 0 18px;
      column-gap: 4px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-to-lines {
      padding-top: 2px;
    }
    #viewModal .doc-preview-paper.doc-preview-paper--indigency .doc-to-lines .line {
      display: block;
      width: 320px;
      max-width: 100%;
      border-bottom: 2px solid #1f2937;
      margin: 0 0 10px;
      height: 0;
    }
    #viewModal .doc-preview-head {
      display: grid;
      grid-template-columns: 100px 1fr 100px;
      align-items: center;
      gap: 14px;
      margin-bottom: 16px;
    }
    #viewModal .doc-preview-logo {
      width: 92px;
      height: 92px;
      object-fit: contain;
      border-radius: 50%;
      justify-self: center;
    }
    #viewModal .doc-preview-head-center {
      text-align: center;
      color: #111827;
      line-height: 1.18;
    }
    #viewModal .doc-preview-head-center p {
      margin: 0;
      font-size: .78rem;
    }
    #viewModal .doc-preview-head-center .rep {
      font-size: .94rem;
      font-weight: 800;
      letter-spacing: .02em;
    }
    #viewModal .doc-preview-head-center .barangay {
      font-size: 1rem;
      font-weight: 800;
      letter-spacing: .02em;
      margin-top: 2px;
    }
    #viewModal .doc-preview-head-center .doc-head-office {
      font-size: .96rem;
      font-weight: 800;
      margin-top: 2px;
      border: 0 !important;
      border-left: 0 !important;
      box-shadow: none !important;
      padding: 0 !important;
      text-indent: 0 !important;
      background: transparent !important;
    }
    #viewModal .doc-preview-head-center .doc-head-office::before,
    #viewModal .doc-preview-head-center .doc-head-office::after {
      content: none !important;
      display: none !important;
    }
    #viewModal .doc-preview-head-line {
      border-bottom: 2px solid #9ca3af;
      margin-top: 10px;
    }
    #viewModal .doc-preview-title {
      text-align: center;
      font-size: 1.1rem;
      font-weight: 800;
      letter-spacing: .03em;
      margin: 10px 0 14px;
      text-transform: uppercase;
    }
    #viewModal .doc-preview-body {
      font-size: .95rem;
      color: #111827;
      line-height: 1.55;
    }
    #viewModal .doc-preview-body p {
      margin: 0 0 12px;
      text-align: justify;
    }
    #viewModal .doc-preview-hint {
      margin: 0 0 10px;
      font-size: .78rem;
      color: #92400e;
      background: #fff7d6;
      border: 1px solid #fde68a;
      border-radius: 7px;
      padding: 6px 8px;
    }
    #viewModal .doc-editable {
      background: #fff6bf;
      border: 1px dashed #d97706;
      border-radius: 4px;
      padding: 0 4px;
      min-width: 24px;
      display: inline-block;
      outline: none;
    }
    #viewModal .doc-editable:focus {
      border-style: solid;
      box-shadow: 0 0 0 2px rgba(245, 158, 11, .2);
    }
    #viewModal .doc-editable-multiline {
      white-space: pre-line;
      min-width: 280px;
    }
    #viewModal .doc-preview-signature {
      margin-top: 22px;
      display: grid;
      justify-items: end;
      color: #111827;
    }
    #viewModal .doc-preview-signature .name {
      margin-top: 28px;
      font-weight: 700;
      border-top: 1px solid #1f2937;
      padding-top: 4px;
      min-width: 220px;
      text-align: center;
    }
    #viewModal .doc-preview-qr {
      position: absolute;
      right: 30px;
      bottom: 34px;
      width: 118px;
      text-align: center;
      color: #374151;
      font-size: .68rem;
      font-weight: 700;
      letter-spacing: .02em;
    }
    #viewModal .doc-preview-qr-box {
      width: 108px;
      height: 108px;
      border: 1px dashed #6b7280;
      border-radius: 6px;
      margin: 0 auto 6px;
      display: grid;
      place-items: center;
      background: #f9fafb;
      overflow: hidden;
    }
    #viewModal .doc-preview-qr-box img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }
    #viewModal .tracker-profile-section {
      border: 0;
      border-radius: 0;
      background: transparent;
      padding: 0;
    }
    #viewModal .tracker-profile-section h6 {
      margin: 2px 0 8px;
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
      background: transparent;
      border: 0;
      border-radius: 0;
      padding: 2px 0;
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
      #viewModal .tracker-form-grid {
        grid-template-columns: 1fr;
      }
      #viewModal .tracker-form-grid.cols-4 {
        grid-template-columns: 1fr;
      }
      #viewModal .tracker-form-grid.cols-3 {
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

<div class="modal fade tracker-profile-modal" id="viewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewModalTitle">Certificate Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="viewDetailsBody" class="tracker-profile-view"></div>
      </div>
      <div class="modal-footer d-flex justify-content-between flex-wrap gap-2">
        <div id="viewModalActions" class="d-flex flex-wrap gap-2"></div>
        <div class="d-flex flex-wrap gap-2">
          <button type="button" id="viewModalBackBtn" class="btn btn-outline-secondary d-none">Back</button>
          <button type="button" id="viewModalNextBtn" class="btn btn-primary">Next</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
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
