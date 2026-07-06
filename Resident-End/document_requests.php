<?php
if (!isset($baseUrl)) {
    $scriptName = str_replace("\\", "/", (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $residentSegmentPos = strpos($scriptName, '/Resident-End/');
    $baseUrl = '';
    if ($residentSegmentPos !== false) {
        $baseUrl = substr($scriptName, 0, $residentSegmentPos);
    } else {
        $baseUrl = dirname($scriptName);
    }
    $baseUrl = rtrim((string)$baseUrl, '/');
    if ($baseUrl === '.' || $baseUrl === '/') {
        $baseUrl = '';
    }
}
?>
<?php
$allowUnregistered = false;
require_once __DIR__ . '/includes/resident_access_guard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Images/favicon_sanjose.png?v=20260211">
  <title>Document Requests</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/CSS-Styles/Resident-End-CSS/residentDashboard.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/CSS-Styles/Guest-End-CSS/GeneralStyle.css">
  <style>
    .tracker-shell {
      border-color: #f1e1cf !important;
    }
    .tracker-title {
      font-family: 'Charis SIL Bold', serif;
      color: #DE710C;
      font-size: clamp(2rem, 4.4vw, 3rem);
      line-height: 1.1;
      margin: 0 0 0.65rem 0;
    }
    .tracker-toolbar {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: nowrap;
      overflow-x: auto;
      padding-bottom: 2px;
      margin-bottom: 1rem;
    }
    .tracker-tabs,
    .tracker-actions {
      display: flex;
      align-items: center;
      gap: 8px;
      flex: 0 0 auto;
      white-space: nowrap;
    }
    .tracker-actions {
      margin-left: auto;
      flex-wrap: nowrap;
    }
    .pending-summary-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border: 1px solid #f3d9ad;
      background: #fff7eb;
      color: #8a4b00;
      font-weight: 700;
      border-radius: 999px;
      padding: 6px 10px;
      line-height: 1;
      white-space: nowrap;
    }
    .pending-summary-badge .count {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 24px;
      height: 24px;
      border-radius: 999px;
      background: #de710c;
      color: #fff;
      font-size: 0.8rem;
      padding: 0 7px;
    }
    .tracker-actions .input-group {
      min-width: 260px;
      max-width: 420px;
      flex: 1 1 auto;
    }
    .tracker-tab.btn {
      border-radius: 999px;
      padding: 0.35rem 0.85rem;
      font-weight: 600;
      border-color: #e3e6ea;
      color: #495057;
      background: #fff;
    }
    .tracker-tab.btn.active {
      border-color: rgba(254, 153, 60, 0.7);
      color: #a04f00;
      background: linear-gradient(180deg, #fff6ec 0%, #ffe9d1 100%);
    }
    .btn-icon {
      width: 38px;
      height: 38px;
      padding: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex: 0 0 38px;
      border-radius: 10px;
      line-height: 1;
    }
    .btn-icon i {
      margin: 0 !important;
    }
    .refresh-btn {
      border-color: rgba(254, 153, 60, 0.45);
      color: #b85b00;
      background: linear-gradient(180deg, #fffaf4 0%, #fff3e4 100%);
      font-weight: 700;
      transition: transform 120ms ease, box-shadow 120ms ease;
    }
    .refresh-btn:hover {
      box-shadow: 0 10px 18px rgba(222, 113, 12, 0.14);
      transform: translateY(-1px);
    }
    .refresh-btn.is-loading i {
      animation: docSpin 900ms linear infinite;
    }
    @keyframes docSpin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }
    #requestCards {
      display: none;
    }
    .request-card {
      border: 1px solid #eceff3;
      border-radius: 12px;
      padding: 0.85rem 0.9rem;
      background: #fff;
      margin-bottom: 0.75rem;
    }
    .request-card .tracker-label {
      font-size: 0.78rem;
      color: #495057;
      text-transform: uppercase;
      letter-spacing: .02em;
      font-weight: 800;
    }
    .request-card .tracker-value {
      font-size: 0.96rem;
      color: #212529;
      word-break: break-word;
      white-space: normal;
    }
    .table-responsive .table th:last-child,
    .table-responsive .table td:last-child {
      width: 1%;
      min-width: 290px;
      white-space: nowrap;
    }
    .request-actions {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      flex-wrap: nowrap;
      white-space: nowrap;
    }
    .request-actions .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 36px;
      padding: 0.42rem 0.78rem;
      border-radius: 0.75rem;
      font-size: 0.82rem;
      line-height: 1.15;
      font-weight: 600;
      white-space: nowrap;
      box-shadow: none !important;
    }
    .request-actions .btn-outline-secondary,
    .request-actions .btn-outline-dark {
      color: #475467;
      border-color: #cfd6de;
      background: #fff;
    }
    .request-actions .btn-outline-secondary:hover,
    .request-actions .btn-outline-dark:hover,
    .request-actions .btn-outline-secondary:focus-visible,
    .request-actions .btn-outline-dark:focus-visible {
      color: #1f2937;
      border-color: #b8c2cc;
      background: #f8fafc;
    }
    .request-actions .btn-outline-primary,
    .request-actions .btn-primary,
    .request-actions .btn-success {
      color: #fff;
      border-color: #de710c;
      background: #de710c;
    }
    .request-actions .btn-outline-primary:hover,
    .request-actions .btn-primary:hover,
    .request-actions .btn-success:hover,
    .request-actions .btn-outline-primary:focus-visible,
    .request-actions .btn-primary:focus-visible,
    .request-actions .btn-success:focus-visible {
      color: #fff;
      border-color: #b95606;
      background: #b95606;
    }
    #viewModal .modal-dialog {
      max-width: 1100px;
      width: min(92vw, 1100px);
    }
    #viewModal .modal-content {
      border: 1px solid rgba(15, 23, 42, 0.08);
      border-radius: 1rem;
      overflow: hidden;
      background: #fff;
      box-shadow: 0 24px 60px rgba(15, 23, 42, 0.14);
    }
    #viewModal .modal-header,
    #viewModal .modal-body,
    #viewModal .modal-footer {
      padding: 1rem 1.25rem;
    }
    #viewModal .modal-header {
      border-bottom: 1px solid #e9ecef;
    }
    #viewModal .modal-body {
      background: #f8fafc;
    }
    #viewModal .modal-footer {
      border-top: 1px solid #e9ecef;
      justify-content: flex-end;
      background: #fff;
    }
    #viewModal .tracker-profile-view {
      display: grid;
      gap: 1rem;
    }
    #viewModal .tracker-form-section {
      border: 1px solid #e6ebf2;
      border-radius: 14px;
      background: #ffffff;
      padding: 1rem;
      margin-top: 0;
      display: grid;
      gap: 0.85rem;
    }
    #viewModal .tracker-form-section-title {
      margin: 0;
      font-size: 0.98rem;
      font-weight: 700;
      color: #111827;
      border-bottom: 1px solid #eef2f6;
      padding-bottom: 0.55rem;
    }
    #viewModal .tracker-form-subsection {
      display: grid;
      gap: 10px;
      padding: 0.9rem;
      border: 1px solid #edf1f5;
      border-radius: 12px;
      background: #fbfcfe;
    }
    #viewModal .tracker-form-subsection + .tracker-form-subsection {
      margin-top: 4px;
    }
    #viewModal .tracker-form-subsection-title {
      margin: 0;
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: #667085;
    }
    #viewModal .tracker-form-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 14px 12px;
    }
    #viewModal .tracker-form-grid.cols-1 {
      grid-template-columns: 1fr;
    }
    #viewModal .tracker-form-grid.cols-3 {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    #viewModal .tracker-form-grid.cols-4 {
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    #viewModal .tracker-form-field {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    #viewModal .tracker-form-label {
      margin: 0;
      line-height: 1.2;
      font-size: 0.74rem;
      color: #667085;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }
    #viewModal .tracker-form-value {
      min-height: 42px;
      border: 1px solid #dde4ec;
      border-radius: 10px;
      background: #ffffff;
      padding: 10px 12px;
      font-size: 0.92rem;
      line-height: 1.45;
      color: #1f2937;
      font-weight: 500;
      word-break: break-word;
    }
    #viewModal .view-form-section {
      display: grid;
      gap: 14px;
    }
    #viewModal .view-form-row {
      display: grid;
      gap: 12px;
    }
    #viewModal .view-form-row.cols-2 {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    #viewModal .view-form-row.cols-3 {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    #viewModal .view-form-row.cols-4 {
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    #viewModal .view-form-row.cols-5 {
      grid-template-columns: repeat(5, minmax(0, 1fr));
    }
    #viewModal .view-form-field {
      display: grid;
      gap: 6px;
    }
    #viewModal .view-form-field.span-2 {
      grid-column: span 2;
    }
    #viewModal .view-form-field.span-3 {
      grid-column: span 3;
    }
    #viewModal .view-form-field.span-4 {
      grid-column: span 4;
    }
    #viewModal .view-form-label {
      margin: 0;
      font-size: 0.74rem;
      color: #667085;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }
    #viewModal .view-form-label .required {
      color: #dc2626;
    }
    #viewModal .view-form-control {
      width: 100%;
      min-height: 42px;
      padding: 10px 12px;
      border: 1px solid #dde4ec;
      border-radius: 10px;
      background: #ffffff;
      color: #1f2937;
      font-weight: 500;
      display: flex;
      align-items: center;
      line-height: 1.45;
      word-break: break-word;
    }
    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      border-radius: 999px;
      padding: 0.34rem 0.78rem;
      font-size: 0.82rem;
      font-weight: 700;
      white-space: nowrap;
      border: 1px solid transparent;
    }
    .status-pill.pending { color: #9a3412; background: #ffedd5; border-color: #fdba74; }
    .status-pill.approved { color: #166534; background: #dcfce7; border-color: #86efac; }
    .status-pill.archived { color: #991b1b; background: #fee2e2; border-color: #fca5a5; }
    @media (max-width: 991.98px) {
      .tracker-toolbar {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
      }
      .tracker-tabs,
      .tracker-actions {
        width: 100%;
      }
      .tracker-tabs {
        overflow-x: auto;
        padding-bottom: 4px;
      }
      .tracker-actions {
        margin-left: 0;
        justify-content: flex-start;
        overflow-x: auto;
        padding-bottom: 2px;
      }
      .tracker-actions .input-group {
        min-width: 0;
        max-width: none;
        width: 100%;
      }
    }
    @media (max-width: 767.98px) {
      #div-mainDisplay {
        padding: 1rem !important;
      }
      .tracker-shell {
        padding: 0.95rem !important;
        border-radius: 1rem !important;
      }
      .tracker-actions {
        flex-wrap: wrap;
        overflow: visible;
      }
      .tracker-actions > .pending-summary-badge {
        width: 100%;
        justify-content: space-between;
      }
      .tracker-actions .btn-icon {
        width: 44px;
        height: 44px;
        flex: 0 0 44px;
      }
      .table-responsive {
        display: none;
      }
      #requestCards {
        display: block;
      }
      #viewModal .modal-dialog {
        width: calc(100vw - 1rem);
      }
      #viewModal .view-form-row.cols-2,
      #viewModal .view-form-row.cols-3,
      #viewModal .view-form-row.cols-4,
      #viewModal .view-form-row.cols-5 {
        grid-template-columns: 1fr;
      }
      #viewModal .view-form-field.span-2,
      #viewModal .view-form-field.span-3,
      #viewModal .view-form-field.span-4 {
        grid-column: span 1;
      }
      #viewModal .tracker-form-grid,
      #viewModal .tracker-form-grid.cols-3,
      #viewModal .tracker-form-grid.cols-4 {
        grid-template-columns: 1fr;
      }
      .request-actions {
        width: 100%;
        flex-wrap: wrap;
      }
      .request-actions .btn {
        flex: 1 1 100%;
      }
    }
    @media (max-width: 480px) {
      .tracker-title {
        font-size: clamp(1.7rem, 8vw, 2.2rem);
      }
      .tracker-tabs {
        gap: 0.5rem;
      }
      .tracker-tab.btn {
        font-size: 0.9rem;
        padding: 0.45rem 0.8rem;
      }
      #viewModal .modal-header,
      #viewModal .modal-body,
      #viewModal .modal-footer {
        padding-left: 0.9rem;
        padding-right: 0.9rem;
      }
    }
  </style>
</head>
<body>
<div class="d-flex min-vh-100">
  <?php include __DIR__ . '/includes/resident_sidebar.php'; ?>

  <main id="div-mainDisplay" class="flex-grow-1 p-4 p-md-5 bg-light">
    <h2 class="tracker-title">Document Requests</h2>
    <hr class="mt-0 mb-3">

    <div class="bg-white rounded-4 shadow-sm border p-4 tracker-shell">
      <div class="tracker-toolbar">
        <div class="tracker-tabs">
          <button type="button" class="btn tracker-tab active" data-tab="all">All</button>
          <button type="button" class="btn tracker-tab" data-tab="pending">Pending</button>
          <button type="button" class="btn tracker-tab" data-tab="completed">Completed</button>
          <button type="button" class="btn tracker-tab" data-tab="cancelled">Cancelled</button>
        </div>
        <div class="tracker-actions">
          <div id="requestPendingSummary" class="pending-summary-badge d-none" aria-live="polite">
            <span>Pending</span>
            <span id="requestPendingCount" class="count">0</span>
          </div>
          <div class="input-group">
            <input id="requestSearch" class="form-control" placeholder="Search..." />
            <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
          </div>
          <button id="btnFilter" class="btn btn-outline-secondary btn-icon" type="button" title="Filter" aria-label="Filter" data-bs-toggle="modal" data-bs-target="#requestFilterModal">
            <i class="fas fa-filter"></i>
          </button>
          <button id="btnRefresh" class="btn refresh-btn btn-icon" type="button" title="Refresh document requests" aria-label="Refresh document requests">
            <i class="fa-solid fa-arrows-rotate"></i>
          </button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table align-middle">
          <thead class="table-light">
            <tr>
              <th>Request ID</th>
              <th>Document</th>
              <th>Purpose</th>
              <th>Fee</th>
              <th>Status</th>
              <th>Submitted</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="requestRows">
            <tr><td colspan="7" class="text-center text-muted py-4">Loading requests...</td></tr>
          </tbody>
        </table>
      </div>
      <div id="requestCards" class="mt-2">
        <div class="text-center text-muted py-4">Loading requests...</div>
      </div>
    </div>
  </main>
</div>

<div class="modal fade" id="requestFilterModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Filter Document Requests</h5>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label small text-muted mb-1">Status</label>
            <select class="form-select" id="requestStatusFilter">
              <option value="">All Status</option>
              <option value="for_payment">For Payment</option>
              <option value="payment_rejected">Payment Rejected</option>
              <option value="ready_for_claim">Ready for Claim</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label small text-muted mb-1">Date From</label>
            <input type="date" class="form-control" id="requestDateFrom">
          </div>
          <div class="col-12">
            <label class="form-label small text-muted mb-1">Date To</label>
            <input type="date" class="form-control" id="requestDateTo">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" id="requestFilterReset">Reset</button>
        <button type="button" class="btn btn-primary" id="requestFilterApply" data-bs-dismiss="modal">Apply</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="paymentModeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="paymentModeForm">
      <div class="modal-header">
        <h5 class="modal-title" id="paymentModeTitle">Select Mode of Payment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="paymentModeRequestId" name="request_id">
        <label class="form-label">Payment Option</label>
        <select class="form-select" id="paymentModeSelect" name="payment_method" required>
          <option value="">Select an option</option>
          <option value="barangay">Pay in Barangay</option>
          <option value="gcash">Pay via GCash</option>
        </select>
        <div class="alert alert-danger d-none mt-3" id="paymentModeError"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="barangayPaymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Pay in Barangay Instructions</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">Follow these steps:</p>
        <ol class="mb-3">
          <li>Go to the finance window.</li>
          <li>Show your request ID.</li>
          <li>Pay the required amount.</li>
          <li>Go to the issuance office to claim your requested document.</li>
        </ol>
        <p class="text-muted small mb-0" id="barangayDeadlineNote">You have until - to complete the payment before the request is automatically cancelled.</p>
      </div>
      <div class="modal-footer">
        <button type="button" id="barangayChangeModeBtn" class="btn btn-outline-primary">Change Mode of Payment</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="gcashPaymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="gcashPaymentForm" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title">Pay via GCash</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="gcashRequestId" name="request_id">
        <input type="hidden" name="payment_method" value="gcash">
        <div class="mb-3 text-center">
          <div class="form-text mb-2">Scan this QR for GCash payment.</div>
          <img
            id="gcashQrImage"
            src="<?= htmlspecialchars((string)$baseUrl, ENT_QUOTES, 'UTF-8') ?>/Images/GCASH_QR.jpg"
            alt="GCash QR Code"
            style="max-width:220px;width:100%;height:auto;border:1px solid #ddd;border-radius:8px;"
            onerror="this.style.display='none';document.getElementById('gcashQrMissing')?.classList.remove('d-none');"
          >
          <div id="gcashQrMissing" class="form-text text-danger d-none">GCash QR image not found. Please contact the barangay office.</div>
        </div>
        <label class="form-label">Reference ID / Transaction Number</label>
        <input type="text" class="form-control mb-3" name="payment_reference" id="gcashReference" placeholder="Enter GCash transaction number" required>
        <label class="form-label">Payment Proof</label>
        <input type="file" class="form-control" name="payment_proof" id="gcashProof" accept=".jpg,.jpeg,.png,.webp,image/*" required>
        <div class="form-text">Upload your GCash payment proof.</div>
        <div class="alert alert-danger d-none mt-3" id="gcashError"></div>
      </div>
      <div class="modal-footer">
        <button type="button" id="gcashChangeModeBtn" class="btn btn-outline-secondary">Change Mode of Payment</button>
        <button type="submit" class="btn btn-primary">Pay Now</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewModalTitle">Certificate Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="viewDetailsBody" class="tracker-profile-view"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="paymentProofModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="paymentProofTitle">Payment Proof</h5>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
  const endpoint = '<?= htmlspecialchars($baseUrl) ?>/PhpFiles/Resident-End/documentRequestWorkflow.php';
  const digitalBarangayIdPage = '<?= htmlspecialchars($baseUrl) ?>/Resident-End/BarangayId/DigitalId.php';
  const tbody = document.getElementById('requestRows');
  const cards = document.getElementById('requestCards');
  const btnRefresh = document.getElementById('btnRefresh');
  const paymentModeModal = new bootstrap.Modal(document.getElementById('paymentModeModal'));
  const paymentModeForm = document.getElementById('paymentModeForm');
  const paymentModeTitle = document.getElementById('paymentModeTitle');
  const paymentModeRequestId = document.getElementById('paymentModeRequestId');
  const paymentModeSelect = document.getElementById('paymentModeSelect');
  const paymentModeError = document.getElementById('paymentModeError');
  const barangayPaymentModal = new bootstrap.Modal(document.getElementById('barangayPaymentModal'));
  const barangayChangeModeBtn = document.getElementById('barangayChangeModeBtn');
  const barangayDeadlineNote = document.getElementById('barangayDeadlineNote');
  const gcashPaymentModal = new bootstrap.Modal(document.getElementById('gcashPaymentModal'));
  const gcashPaymentForm = document.getElementById('gcashPaymentForm');
  const gcashSubmitBtn = gcashPaymentForm ? gcashPaymentForm.querySelector('button[type="submit"]') : null;
  const gcashRequestId = document.getElementById('gcashRequestId');
  const gcashReference = document.getElementById('gcashReference');
  const gcashProof = document.getElementById('gcashProof');
  const gcashError = document.getElementById('gcashError');
  const gcashChangeModeBtn = document.getElementById('gcashChangeModeBtn');
  const viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
  const viewModalTitle = document.getElementById('viewModalTitle');
  const viewDetailsBody = document.getElementById('viewDetailsBody');
  const paymentProofModal = new bootstrap.Modal(document.getElementById('paymentProofModal'));
  const paymentProofTitle = document.getElementById('paymentProofTitle');
  const paymentProofWrap = document.getElementById('paymentProofWrap');
  const paymentProofOpenNew = document.getElementById('paymentProofOpenNew');
  let itemById = new Map();
  let allItems = [];
  let activeTab = 'all';
  let gcashSubmitting = false;

  function setGcashSubmittingState(isSubmitting) {
    gcashSubmitting = !!isSubmitting;
    if (gcashSubmitBtn) {
      if (!gcashSubmitBtn.dataset.defaultText) {
        gcashSubmitBtn.dataset.defaultText = gcashSubmitBtn.textContent || 'Pay Now';
      }
      gcashSubmitBtn.disabled = gcashSubmitting;
      gcashSubmitBtn.textContent = gcashSubmitting
        ? 'Submitting...'
        : (gcashSubmitBtn.dataset.defaultText || 'Pay Now');
    }
    if (gcashChangeModeBtn) {
      gcashChangeModeBtn.disabled = gcashSubmitting;
    }
  }

  function badge(stage, label) {
    const key = String(stage || '').toLowerCase();
    if (key === 'cancelled') return `<span class="badge bg-danger">${label}</span>`;
    if (key.includes('rejected')) return `<span class="badge bg-danger">${label}</span>`;
    if (key === 'completed') return `<span class="badge bg-success">${label}</span>`;
    if (key === 'ready_for_claim') return `<span class="badge bg-primary">${label}</span>`;
    if (key === 'for_payment') return `<span class="badge bg-warning text-dark">${label}</span>`;
    return `<span class="badge bg-secondary">${label}</span>`;
  }

  function statusPillClass(stage) {
    const key = String(stage || '').toLowerCase();
    if (key === 'completed' || key === 'ready_for_claim') return 'approved';
    if (key === 'cancelled' || key.includes('rejected')) return 'archived';
    return 'pending';
  }

  function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>\"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#39;'}[m]));
  }

  function friendlyLabel(key) {
    const raw = String(key || '').trim();
    if (!raw) return '';
    const map = {
      document_type: 'Document Type',
      request_purpose: 'Request Purpose',
      o_ln: 'Applicant Last Name',
      o_fn: 'Applicant First Name',
      o_mn: 'Applicant Middle Name',
      o_phone: 'Applicant Phone',
      owner_full_address: 'Applicant Full Address',
      application_type: 'Application Type',
      business_name: 'Business Name',
      b_name: 'Business Name',
      business_same_address: 'Same as Applicant Address',
      business_barangay: 'Business Barangay',
      business_city: 'Business City',
      business_province: 'Business Province',
      business_full_address: 'Business Full Address',
      initial_operation_date: 'Initial Operation Date',
      business_contact_number: 'Business Contact Number',
      b_contact_1: 'Business Contact Number',
      business_type: 'Business Type',
      owner_type: 'Owner Type',
      business_reg_type: 'Business Registration Type',
      renewal_business_reg_type: 'Business Registration Type',
      full_address: 'Full Address',
      full_address_display: 'Full Address',
      birthdate: 'Birthdate',
      birthplace: 'Birthplace',
      location: 'Location'
    };
    if (map[raw]) return map[raw];
    return raw.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
  }

  function isEmptyFieldValue(value) {
    const text = String(value ?? '').trim();
    if (!text) return true;
    return ['-', '—', 'n/a', 'na', 'null', 'undefined'].includes(text.toLowerCase());
  }

  function formField(label, value, raw = false) {
    const text = String(value ?? '').trim();
    const rendered = raw ? (text || '-') : escapeHtml(text || '-');
    return `
      <div class="tracker-form-field">
        <p class="tracker-form-label">${escapeHtml(label)}</p>
        <div class="tracker-form-value">${rendered}</div>
      </div>
    `;
  }

  function gridClassByCount(count, maxCols = 4) {
    const n = Math.max(1, Math.min(maxCols, Number(count) || 1));
    if (n >= 4) return 'cols-4';
    if (n === 3) return 'cols-3';
    if (n === 2) return '';
    return 'cols-1';
  }

  function renderFieldGrid(fields, maxCols = 4) {
    const clean = (Array.isArray(fields) ? fields : []).filter((f) => f && String(f.value ?? '').trim() !== '');
    if (!clean.length) return '';
    const cls = gridClassByCount(clean.length, maxCols);
    return `<div class="tracker-form-grid ${cls}">${clean.map((f) => formField(f.label, f.value, !!f.raw)).join('')}</div>`;
  }

  function formSection(title, content) {
    return `
      <section class="tracker-form-section">
        <h6 class="tracker-form-section-title">${escapeHtml(title)}</h6>
        ${content}
      </section>
    `;
  }

  function viewFormField(label, value, { span = 1, required = false } = {}) {
    const text = String(value ?? '').trim();
    if (!text) return '';
    const spanClass = span > 1 ? ` span-${Math.min(4, Math.max(2, Number(span) || 1))}` : '';
    return `
      <div class="view-form-field${spanClass}">
        <p class="view-form-label">${escapeHtml(label)}${required ? ' <span class="required">*</span>' : ''}</p>
        <div class="view-form-control">${escapeHtml(text)}</div>
      </div>
    `;
  }

  function viewFormRow(fields, cols = 4) {
    const html = (Array.isArray(fields) ? fields : []).filter(Boolean).join('');
    if (!html) return '';
    return `<div class="view-form-row cols-${cols}">${html}</div>`;
  }

  function parsePayload(payloadLike) {
    if (payloadLike && typeof payloadLike === 'object' && !Array.isArray(payloadLike)) {
      return payloadLike;
    }
    const text = String(payloadLike ?? '').trim();
    if (!text) return {};
    try {
      const parsed = JSON.parse(text);
      if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
        return parsed;
      }
    } catch (_) {}
    return {};
  }

  function formatDateTime(value) {
    const raw = String(value || '').trim();
    if (!raw) return '';
    const normalized = raw.replace(' ', 'T');
    const date = new Date(normalized);
    if (Number.isNaN(date.getTime())) return raw;
    return date.toLocaleString();
  }

  function shouldHidePayloadKey(key) {
    const normalized = String(key || '').trim().toLowerCase();
    if (!normalized) return true;
    if (['action', 'csrf_token', 'redirect'].includes(normalized)) return true;
    return /(file|files|path|proof|attachment|upload|front|back)/.test(normalized);
  }

  function buildGenericSubmittedFields(row, payload) {
    const submittedFields = [];
    const documentTypeText = String(row?.document_type || payload?.document_type || '').toLowerCase();
    const hidePersonalBirthFields = documentTypeText.includes('business permit');
    Object.keys(payload || {}).forEach((key) => {
      const k = String(key || '').trim();
      if (!k || shouldHidePayloadKey(k)) return;
      const normalizedKey = k.toLowerCase();
      if (hidePersonalBirthFields && (normalizedKey === 'birthdate' || normalizedKey === 'birthplace')) return;
      const value = payload[k];
      const text = Array.isArray(value) || (value && typeof value === 'object')
        ? JSON.stringify(value)
        : String(value ?? '').trim();
      if (isEmptyFieldValue(text)) return;
      submittedFields.push({ key: k, label: friendlyLabel(k), value: text });
    });

    const requestDetailsRaw = String(row.request_details ?? '').trim();
    if (!submittedFields.length && requestDetailsRaw && requestDetailsRaw !== '{}' && requestDetailsRaw !== '[]') {
      submittedFields.push({ key: 'request_details', label: 'Request Details', value: requestDetailsRaw });
    }

    if (!submittedFields.length) {
      [
        ['purpose', 'Purpose', row.purpose],
        ['status', 'Status', row.stage_label || row.stage],
        ['submitted_at', 'Submitted At', row.submitted_at],
        ['status_remarks', 'Status Remarks', row.status_remarks]
      ].forEach(([key, label, value]) => {
        const text = String(value ?? '').trim();
        if (!isEmptyFieldValue(text)) {
          submittedFields.push({ key, label, value: text });
        }
      });
    }

    return submittedFields;
  }

  function renderGroupedFieldSections(sections) {
    const blocks = (Array.isArray(sections) ? sections : []).map((section) => {
      const grid = renderFieldGrid(section.fields || [], section.maxCols || 4);
      if (!grid) return '';
      return `
        <div class="tracker-form-subsection">
          <h6 class="tracker-form-subsection-title">${escapeHtml(section.title || 'Details')}</h6>
          ${grid}
        </div>
      `;
    }).filter(Boolean);

    return blocks.join('');
  }

  function buildSubmittedFieldSections(fields) {
    const order = ['request', 'owner', 'contact', 'owner_address', 'business', 'business_address', 'other'];
    const config = {
      request: { title: 'Request Details', maxCols: 4, fields: [] },
      owner: { title: 'Applicant Information', maxCols: 4, fields: [] },
      contact: { title: 'Contact Details', maxCols: 4, fields: [] },
      owner_address: { title: 'Applicant Address', maxCols: 2, fields: [] },
      business: { title: 'Business Information', maxCols: 4, fields: [] },
      business_address: { title: 'Business Address', maxCols: 3, fields: [] },
      other: { title: 'Other Details', maxCols: 4, fields: [] }
    };

    const categoryOf = (field) => {
      const key = String(field?.key || '').trim().toLowerCase();
      const label = String(field?.label || '').trim().toLowerCase();
      const source = `${key} ${label}`;

      if (/(document_type|request_purpose|application_type|purpose|request_details|status|submitted_at|status_remarks)/.test(source)) return 'request';
      if (/(business_name|business_type|business_reg_type|renewal_business_reg_type|owner_type|initial_operation_date)/.test(source)) return 'business';
      if (/(business_same_address|business_barangay|business_city|business_province|business_full_address|location)/.test(source)) return 'business_address';
      if (/(owner_full_address|full_address|birthplace)/.test(source)) return 'owner_address';
      if (/(phone|contact|email)/.test(source)) return 'contact';
      if (/(^o_|owner|name|birthdate|age|sex|gender|civil_status|civil status|occupation|nationality|religion)/.test(source)) return 'owner';
      return 'other';
    };

    (Array.isArray(fields) ? fields : []).forEach((field) => {
      const bucket = categoryOf(field);
      config[bucket].fields.push({ label: field.label, value: field.value, raw: field.raw });
    });

    return order.map((key) => config[key]).filter((section) => section.fields.length);
  }

  function buildRequestViewHtml(row, payload) {
    const summarySection = formSection('Request Summary', [
      renderFieldGrid([
        { label: 'Request ID', value: row.request_id || '-' },
        { label: 'Document', value: row.document_type || '-' },
      ], 2),
      renderFieldGrid([
        { label: 'Purpose', value: row.purpose || '-' },
        { label: 'Fee', value: feeTextOf(row) },
      ], 2),
      renderFieldGrid([
        { label: 'Status', value: row.stage_label || row.stage || '-' },
        { label: 'Submitted At', value: row.submitted_at || '-' },
      ], 2),
    ].join(''));

    const documentType = String(row.document_type || payload.document_type || '').toLowerCase();
    const variant = String(payload.cohabitation_variant || '').toLowerCase();
    const isCohabitation = documentType.includes('cohab') || variant !== '';

    if (isCohabitation) {
      const detailsSection = formSection('Submitted Form Details', `
        <div class="view-form-section">
          ${viewFormRow([
            viewFormField('Doc Type', payload.document_type || row.document_type || '-'),
            viewFormField('Cohab Variant', payload.cohabitation_variant || '-'),
          ], 2)}
          ${viewFormRow([
            viewFormField('Last Name', payload.last_name || '', { required: true }),
            viewFormField('First Name', payload.first_name || '', { required: true }),
            viewFormField('Middle Name', payload.middle_name || ''),
          ], 3)}
          ${viewFormRow([
            viewFormField('Full Address', payload.full_address || payload.full_address_display || '', { span: 4, required: true }),
          ], 4)}
          ${viewFormRow([
            viewFormField('Cohabitant Last', payload.cohabitant_last || payload.cohabitant_last_name || ''),
            viewFormField('Cohabitant First', payload.cohabitant_first || ''),
            viewFormField('Cohabitant Middle', payload.cohabitant_middle || ''),
          ], 3)}
          ${viewFormRow([
            viewFormField('Civil Status', payload.cohabitant_civil_status || '', { span: 2, required: true }),
            viewFormField('Cohabitant ID Number', payload.cohabitant_id_number || '', { span: 2 }),
          ], 4)}
          ${viewFormRow([
            viewFormField('Cohabitant Full Address', payload.cohabitant_full_address || payload.cohabitant_full_address_display || '', { span: 4 }),
          ], 4)}
          ${viewFormRow([
            viewFormField('Cohab Start Date', payload.cohabitation_start_date || '', { span: 2 }),
            viewFormField('Duration', payload.cohabitation_duration || payload.cohabitation_duration_display || '', { span: 2 }),
          ], 4)}
          ${viewFormRow([
            viewFormField('Cohab Duration Value', payload.cohabitation_duration_value || '', { span: 2 }),
            viewFormField('Unit', payload.cohabitation_duration_unit || '', { span: 2 }),
          ], 4)}
          ${viewFormRow([
            viewFormField('Cohabitant Relationship', payload.cohabitant_relationship || '', { span: 2, required: true }),
            viewFormField('Purpose', payload.purpose || row.purpose || '', { span: 2, required: true }),
          ], 4)}
        </div>
      `);

      return `${summarySection}${detailsSection}`;
    }

    const submittedFields = buildGenericSubmittedFields(row, payload);
    const submittedSections = buildSubmittedFieldSections(submittedFields);
    const submittedContent = renderGroupedFieldSections(submittedSections);

    return [
      summarySection,
      formSection('Submitted Form Details', submittedContent || '<div class="text-muted">No submitted details.</div>'),
    ].join('');
  }

  function openRequestView(row) {
    if (!row) return;
    const payload = parsePayload(row.payload);
    viewDetailsBody.innerHTML = buildRequestViewHtml(row, payload);
    if (viewModalTitle) {
      const requestId = String(row.request_id || '').trim();
      viewModalTitle.textContent = requestId ? `Certificate Request (#${requestId})` : 'Certificate Request';
    }
    viewModal.show();
  }

  function paymentActions(row) {
    const requestId = String(row.request_id || '');
    const viewBtn = `<button class="btn btn-sm btn-outline-secondary" data-view="${escapeHtml(requestId)}">View</button>`;
    const method = String(row.payment_method || '').toLowerCase();
    const hasMode = method === 'gcash' || method === 'barangay';
    const modeLabel = hasMode ? 'Change Mode of Payment' : 'Select Mode of Payment';
    const modeButtonClass = hasMode ? 'btn-outline-secondary' : 'btn-outline-primary';
    const modeBtn = `<button type="button" class="btn btn-sm ${modeButtonClass}" data-open-mode="${escapeHtml(requestId)}">${modeLabel}</button>`;
    const payNowBtn = `<button class="btn btn-sm btn-primary" data-pay-now="${escapeHtml(requestId)}">Pay Now</button>`;

    if (method === 'gcash') {
      return `<span class="request-actions">${viewBtn}${payNowBtn}${modeBtn}</span>`;
    }
    if (method === 'barangay') {
      return `<span class="request-actions">${viewBtn}${modeBtn}</span>`;
    }
    return `<span class="request-actions">${viewBtn}${modeBtn}</span>`;
  }

  function filterBucket(row) {
    const stage = String(row?.stage || '').toLowerCase();
    if (stage === 'completed') return 'completed';
    if (stage === 'cancelled' || stage === 'rejected' || stage === 'interview_failed') return 'cancelled';
    return 'pending';
  }

  function feeTextOf(row) {
    return (row.fee_amount !== null && row.fee_amount !== undefined && String(row.fee_amount) !== '')
      ? `PHP ${Number(row.fee_amount).toFixed(2)}`
      : '-';
  }

  function isBarangayIdRequest(row) {
    const docType = String(row?.document_type || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
    return docType.includes('barangayid');
  }

  function filteredItems() {
    const search = String(document.getElementById('requestSearch')?.value || '').toLowerCase().trim();
    const status = String(document.getElementById('requestStatusFilter')?.value || '').toLowerCase().trim();
    const dateFrom = String(document.getElementById('requestDateFrom')?.value || '').trim();
    const dateTo = String(document.getElementById('requestDateTo')?.value || '').trim();

    return allItems.filter((row) => {
      if (activeTab !== 'all' && filterBucket(row) !== activeTab) return false;
      if (status && String(row.stage || '').toLowerCase() !== status) return false;

      const haystack = [
        row.request_id,
        row.document_type,
        row.purpose,
        row.stage_label,
        row.stage
      ].join(' ').toLowerCase();
      if (search && !haystack.includes(search)) return false;

      const rawSubmitted = String(row.submitted_at_raw || row.submitted_at || '').trim();
      const sourceDate = rawSubmitted.slice(0, 10);
      if (dateFrom && sourceDate && sourceDate < dateFrom) return false;
      if (dateTo && sourceDate && sourceDate > dateTo) return false;
      return true;
    });
  }

  function updatePendingSummary() {
    const wrap = document.getElementById('requestPendingSummary');
    const countEl = document.getElementById('requestPendingCount');
    if (!wrap || !countEl) return;
    const pendingCount = allItems.filter((row) => filterBucket(row) === 'pending').length;
    countEl.textContent = String(pendingCount);
    wrap.classList.toggle('d-none', pendingCount <= 0);
  }

  function bindRowActions() {
    tbody.querySelectorAll('button[data-open-mode]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = String(btn.getAttribute('data-open-mode') || '');
        if (!id) return;
        openModeModal(id);
      });
    });

    tbody.querySelectorAll('button[data-pay-now]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = String(btn.getAttribute('data-pay-now') || '');
        openGcashModal(id);
      });
    });

    tbody.querySelectorAll('button[data-view]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = String(btn.getAttribute('data-view') || '');
        const row = itemById.get(id);
        if (!row) return;
        openRequestView(row);
      });
    });

    tbody.querySelectorAll('button[data-proof]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = String(btn.getAttribute('data-proof') || '');
        const row = itemById.get(id);
        if (!row || !row.payment_proof_path) return;
        const proofUrl = `${endpoint}?action=view_payment_proof&request_id=${encodeURIComponent(id)}&_ts=${Date.now()}`;
        openFileViewerModal({
          title: 'Payment Proof',
          viewUrl: proofUrl,
          linkText: 'Open in New Tab',
          linkUrl: proofUrl,
          isPdf: String(row.payment_proof_path || '').toLowerCase().endsWith('.pdf')
        });
      });
    });

    tbody.querySelectorAll('button[data-issued]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = String(btn.getAttribute('data-issued') || '');
        const row = itemById.get(id);
        const downloadUrl = `${endpoint}?action=download_issued&request_id=${encodeURIComponent(id)}`;
        const fullDigitalIdUrl = `${digitalBarangayIdPage}?request_id=${encodeURIComponent(id)}&_ts=${Date.now()}`;
        const viewUrl = row && isBarangayIdRequest(row)
          ? `${digitalBarangayIdPage}?request_id=${encodeURIComponent(id)}&embed=1&_ts=${Date.now()}`
          : `${endpoint}?action=view_issued&request_id=${encodeURIComponent(id)}&_ts=${Date.now()}`;
        openFileViewerModal({
          title: row && isBarangayIdRequest(row) ? 'Digital Barangay ID' : 'Issued Document (PDF)',
          viewUrl,
          linkText: row && isBarangayIdRequest(row) ? 'Open Full Page' : 'Download PDF',
          linkUrl: row && isBarangayIdRequest(row) ? fullDigitalIdUrl : downloadUrl,
          isPdf: true
        });
      });
    });
  }

  function render() {
    const items = filteredItems();
    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No requests found.</td></tr>';
      if (cards) cards.innerHTML = '<div class="text-center text-muted py-4">No requests found.</div>';
      return;
    }

    tbody.innerHTML = items.map((r) => {
      let action = '<span class="text-muted small">-</span>';
      const viewBtn = `<button class="btn btn-sm btn-outline-secondary" data-view="${escapeHtml(r.request_id)}">View</button>`;
      const proofBtn = r.payment_proof_path
        ? `<button class="btn btn-sm btn-outline-dark" data-proof="${escapeHtml(r.request_id)}">View Payment</button>`
        : '';
      const issuedBtn = `<button class="btn btn-sm btn-success" data-issued="${escapeHtml(r.request_id)}">${isBarangayIdRequest(r) ? 'View Digital ID' : 'View Document'}</button>`;
      if (r.stage === 'for_payment' || r.stage === 'payment_rejected') {
        action = paymentActions(r);
      } else if (r.stage === 'completed') {
        action = `<span class="request-actions">${viewBtn}${proofBtn}${issuedBtn}</span>`;
      } else {
        action = `<span class="request-actions">${viewBtn}${proofBtn}</span>`;
      }

      const reason = r.status_remarks ? `<div class="text-danger small mt-1">Reason: ${escapeHtml(r.status_remarks)}</div>` : '';
      const paymentDeadline = (r.stage === 'for_payment' || r.stage === 'payment_rejected') && r.payment_deadline
        ? `<div class="text-muted small mt-1">Payment deadline: ${escapeHtml(formatDateTime(r.payment_deadline))}</div>`
        : '';
      return `
        <tr>
          <td class="fw-semibold">${escapeHtml(r.request_id)}</td>
          <td>${escapeHtml(r.document_type)}</td>
          <td>${escapeHtml(r.purpose || '-')}</td>
          <td>${escapeHtml(feeTextOf(r))}</td>
          <td>${badge(r.stage, escapeHtml(r.stage_label || r.stage || ''))}${reason}${paymentDeadline}</td>
          <td>${escapeHtml(r.submitted_at || '-')}</td>
          <td>${action}</td>
        </tr>
      `;
    }).join('');

    if (cards) {
      cards.innerHTML = items.map((r) => `
        <article class="request-card">
          <div class="tracker-label">Request</div>
          <div class="tracker-value fw-semibold">${escapeHtml(r.request_id || '-')}</div>
          <div class="tracker-label mt-2">Document</div>
          <div class="tracker-value">${escapeHtml(r.document_type || '-')}</div>
          <div class="tracker-label mt-2">Purpose</div>
          <div class="tracker-value">${escapeHtml(r.purpose || '-')}</div>
          <div class="tracker-label mt-2">Status</div>
          <div class="tracker-value">${badge(r.stage, escapeHtml(r.stage_label || r.stage || ''))}</div>
          <div class="tracker-label mt-2">Submitted</div>
          <div class="tracker-value">${escapeHtml(r.submitted_at || '-')}</div>
        </article>
      `).join('');
    }

    bindRowActions();
  }

  function openModeModal(requestId) {
    const row = itemById.get(String(requestId || ''));
    const method = String(row?.payment_method || '').toLowerCase();
    paymentModeError.classList.add('d-none');
    paymentModeError.textContent = '';
    paymentModeForm.reset();
    paymentModeRequestId.value = String(requestId || '');
    paymentModeTitle.textContent = method ? 'Change Mode of Payment' : 'Select Mode of Payment';
    if (method === 'gcash' || method === 'barangay') {
      paymentModeSelect.value = method;
    }
    paymentModeModal.show();
  }

  function openGcashModal(requestId) {
    gcashError.classList.add('d-none');
    gcashError.textContent = '';
    gcashPaymentForm.reset();
    gcashRequestId.value = String(requestId || '');
    gcashPaymentModal.show();
  }

  function updateBarangayDeadlineNote(requestId) {
    if (!barangayDeadlineNote) return;
    const row = itemById.get(String(requestId || ''));
    const deadlineRaw = String(row?.payment_deadline || '').trim();
    if (deadlineRaw) {
      const normalized = deadlineRaw.replace(' ', 'T');
      const dt = new Date(normalized);
      const mmddyyyy = Number.isNaN(dt.getTime())
        ? deadlineRaw
        : `${String(dt.getMonth() + 1).padStart(2, '0')}/${String(dt.getDate()).padStart(2, '0')}/${dt.getFullYear()}`;
      barangayDeadlineNote.textContent = `You have until ${mmddyyyy} to complete the payment before the request is automatically cancelled.`;
    } else {
      barangayDeadlineNote.textContent = 'You have until the payment deadline to complete the payment before the request is automatically cancelled.';
    }
  }

  async function setPaymentMode(requestId, paymentMethod) {
    const fd = new FormData();
    fd.append('action', 'select_payment_mode');
    fd.append('request_id', String(requestId || ''));
    fd.append('payment_method', String(paymentMethod || ''));

    const data = await fetchJson(endpoint, {
      method: 'POST',
      body: fd
    });
    if (!data.success) {
      throw new Error(data.message || 'Unable to save payment mode.');
    }
    return data;
  }

  async function fetchJson(url, options = {}) {
    const headers = new Headers(options.headers || {});
    if (!headers.has('Accept')) {
      headers.set('Accept', 'application/json');
    }
    headers.set('X-Requested-With', 'XMLHttpRequest');

    const res = await fetch(url, { ...options, headers, credentials: 'same-origin' });
    const contentType = (res.headers.get('content-type') || '').toLowerCase();
    const text = await res.text();
    const looksHtml = text.trim().startsWith('<!DOCTYPE') || text.trim().startsWith('<html');

    if (!contentType.includes('application/json')) {
      if (looksHtml) {
        throw new Error('Session expired or invalid endpoint response. Please reload and login again.');
      }
      throw new Error('Unexpected response format from server.');
    }

    let data;
    try {
      data = JSON.parse(text);
    } catch (_) {
      throw new Error('Invalid JSON response from server.');
    }

    if (!res.ok) {
      throw new Error(data?.message || `Request failed (${res.status}).`);
    }
    return data;
  }

  async function load() {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Loading requests...</td></tr>';
    try {
      const data = await fetchJson(endpoint + '?action=list&limit=80');
      if (!data.success) throw new Error(data.message || 'Unable to load requests');

      const items = Array.isArray(data.items) ? data.items : [];
      itemById = new Map(items.map((it) => [String(it.request_id), it]));
      if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No requests yet.</td></tr>';
        return;
      }

      tbody.innerHTML = items.map((r) => {
        let action = '<span class="text-muted small">-</span>';
        const viewBtn = `<button class="btn btn-sm btn-outline-secondary" data-view="${escapeHtml(r.request_id)}">View</button>`;
        const proofBtn = r.payment_proof_path
          ? `<button class="btn btn-sm btn-outline-dark" data-proof="${escapeHtml(r.request_id)}">View Payment</button>`
          : '';
        const issuedBtn = `<button class="btn btn-sm btn-success" data-issued="${escapeHtml(r.request_id)}">${isBarangayIdRequest(r) ? 'View Digital ID' : 'View Document'}</button>`;
        if (r.stage === 'for_payment' || r.stage === 'payment_rejected') {
          action = paymentActions(r);
        } else if (r.stage === 'completed') {
          action = `<span class="request-actions">${viewBtn}${proofBtn}${issuedBtn}</span>`;
        } else {
          action = `<span class="request-actions">${viewBtn}${proofBtn}</span>`;
        }

        const reason = r.status_remarks ? `<div class="text-danger small mt-1">Reason: ${escapeHtml(r.status_remarks)}</div>` : '';
        const paymentDeadline = (r.stage === 'for_payment' || r.stage === 'payment_rejected') && r.payment_deadline
          ? `<div class="text-muted small mt-1">Payment deadline: ${escapeHtml(formatDateTime(r.payment_deadline))}</div>`
          : '';
        const feeText = (r.fee_amount !== null && r.fee_amount !== undefined && String(r.fee_amount) !== '')
          ? `₱${Number(r.fee_amount).toFixed(2)}`
          : '-';
        return `
          <tr>
            <td class="fw-semibold">${escapeHtml(r.request_id)}</td>
            <td>${escapeHtml(r.document_type)}</td>
            <td>${escapeHtml(r.purpose || '-')}</td>
            <td>${escapeHtml(feeText)}</td>
            <td>${badge(r.stage, escapeHtml(r.stage_label || r.stage || ''))}${reason}${paymentDeadline}</td>
            <td>${escapeHtml(r.submitted_at || '-')}</td>
            <td>${action}</td>
          </tr>
        `;
      }).join('');

      tbody.querySelectorAll('button[data-open-mode]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const id = String(btn.getAttribute('data-open-mode') || '');
          if (!id) return;
          openModeModal(id);
        });
      });

      tbody.querySelectorAll('button[data-pay-now]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const id = String(btn.getAttribute('data-pay-now') || '');
          openGcashModal(id);
        });
      });

      tbody.querySelectorAll('button[data-view]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const id = String(btn.getAttribute('data-view') || '');
          const row = itemById.get(id);
          if (!row) return;
          openRequestView(row);
        });
      });

      tbody.querySelectorAll('button[data-proof]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const id = String(btn.getAttribute('data-proof') || '');
          const row = itemById.get(id);
          if (!row || !row.payment_proof_path) return;
          const proofUrl = `${endpoint}?action=view_payment_proof&request_id=${encodeURIComponent(id)}&_ts=${Date.now()}`;
          openFileViewerModal({
            title: 'Payment Proof',
            viewUrl: proofUrl,
            linkText: 'Open in New Tab',
            linkUrl: proofUrl,
            isPdf: String(row.payment_proof_path || '').toLowerCase().endsWith('.pdf')
          });
        });
      });

      tbody.querySelectorAll('button[data-issued]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const id = String(btn.getAttribute('data-issued') || '');
          const row = itemById.get(id);
          const downloadUrl = `${endpoint}?action=download_issued&request_id=${encodeURIComponent(id)}`;
          const fullDigitalIdUrl = `${digitalBarangayIdPage}?request_id=${encodeURIComponent(id)}&_ts=${Date.now()}`;
          const viewUrl = row && isBarangayIdRequest(row)
            ? `${digitalBarangayIdPage}?request_id=${encodeURIComponent(id)}&embed=1&_ts=${Date.now()}`
            : `${endpoint}?action=view_issued&request_id=${encodeURIComponent(id)}&_ts=${Date.now()}`;
          openFileViewerModal({
            title: row && isBarangayIdRequest(row) ? 'Digital Barangay ID' : 'Issued Document (PDF)',
            viewUrl,
            linkText: row && isBarangayIdRequest(row) ? 'Open Full Page' : 'Download PDF',
            linkUrl: row && isBarangayIdRequest(row) ? fullDigitalIdUrl : downloadUrl,
            isPdf: true
          });
        });
      });
    } catch (err) {
      tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${escapeHtml(err.message || err)}</td></tr>`;
    }
  }

  async function load() {
    btnRefresh?.classList.add('is-loading');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Loading requests...</td></tr>';
    if (cards) cards.innerHTML = '<div class="text-center text-muted py-4">Loading requests...</div>';
    try {
      const data = await fetchJson(endpoint + '?action=list&limit=80');
      if (!data.success) throw new Error(data.message || 'Unable to load requests');

      allItems = Array.isArray(data.items) ? data.items : [];
      itemById = new Map(allItems.map((it) => [String(it.request_id), it]));
      updatePendingSummary();
      if (!allItems.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No requests yet.</td></tr>';
        if (cards) cards.innerHTML = '<div class="text-center text-muted py-4">No requests yet.</div>';
        return;
      }

      render();
    } catch (err) {
      tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${escapeHtml(err.message || err)}</td></tr>`;
      if (cards) cards.innerHTML = `<div class="text-center text-danger py-4">${escapeHtml(err.message || err)}</div>`;
      document.getElementById('requestPendingSummary')?.classList.add('d-none');
    } finally {
      btnRefresh?.classList.remove('is-loading');
    }
  }

  function openFileViewerModal({ title, viewUrl, linkText, linkUrl, isPdf }) {
    if (paymentProofTitle) {
      paymentProofTitle.textContent = String(title || 'Document');
    }
    paymentProofOpenNew.textContent = String(linkText || 'Open in New Tab');
    paymentProofOpenNew.href = String(linkUrl || viewUrl || '#');

    if (isPdf) {
      paymentProofWrap.innerHTML = `<iframe src="${viewUrl}" style="width:100%;height:70vh;border:1px solid #ddd;border-radius:8px;"></iframe>`;
    } else {
      paymentProofWrap.innerHTML = `<img src="${viewUrl}" alt="Document Preview" style="max-width:100%;max-height:70vh;border:1px solid #ddd;border-radius:8px;">`;
    }
    paymentProofModal.show();
  }

  gcashPaymentForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (gcashSubmitting) return;
    setGcashSubmittingState(true);
    gcashError.classList.add('d-none');
    gcashError.textContent = '';

    const fd = new FormData(gcashPaymentForm);
    fd.append('action', 'submit_payment');

    try {
      const data = await fetchJson(endpoint, {
        method: 'POST',
        body: fd
      });
      if (!data.success) throw new Error(data.message || 'Unable to submit payment.');
      gcashPaymentModal.hide();
      load().catch(() => {});
    } catch (err) {
      gcashError.textContent = err.message || String(err);
      gcashError.classList.remove('d-none');
      setGcashSubmittingState(false);
    }
  });

  document.getElementById('gcashPaymentModal')?.addEventListener('hidden.bs.modal', () => {
    setGcashSubmittingState(false);
  });

  paymentModeForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    paymentModeError.classList.add('d-none');
    paymentModeError.textContent = '';

    const requestId = String(paymentModeRequestId.value || '');
    const mode = String(paymentModeSelect.value || '').toLowerCase();
    if (!requestId || (mode !== 'gcash' && mode !== 'barangay')) {
      paymentModeError.textContent = 'Please select a valid payment option.';
      paymentModeError.classList.remove('d-none');
      return;
    }

    try {
      await setPaymentMode(requestId, mode);
      paymentModeModal.hide();
      paymentModeRequestId.value = requestId;
      const row = itemById.get(requestId);
      if (row) {
        row.payment_method = mode;
        if (String(row.stage || '').toLowerCase() === 'payment_rejected') {
          row.stage = 'for_payment';
          row.stage_label = 'For Payment';
          row.status_remarks = null;
        }
        itemById.set(requestId, row);
      }
      if (mode === 'barangay') {
        updateBarangayDeadlineNote(requestId);
        barangayPaymentModal.show();
      }
      load().catch(() => {});
    } catch (err) {
      paymentModeError.textContent = err.message || String(err);
      paymentModeError.classList.remove('d-none');
    }
  });

  gcashChangeModeBtn.addEventListener('click', () => {
    const requestId = String(gcashRequestId.value || '');
    gcashPaymentModal.hide();
    if (requestId) openModeModal(requestId);
  });

  barangayChangeModeBtn.addEventListener('click', () => {
    const requestId = String(paymentModeRequestId.value || gcashRequestId.value || '');
    barangayPaymentModal.hide();
    if (requestId) openModeModal(requestId);
  });

  document.querySelectorAll('.tracker-tab').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tracker-tab').forEach((node) => node.classList.remove('active'));
      btn.classList.add('active');
      activeTab = String(btn.getAttribute('data-tab') || 'all');
      render();
    });
  });

  document.getElementById('requestSearch')?.addEventListener('input', render);
  document.getElementById('requestStatusFilter')?.addEventListener('change', render);
  document.getElementById('requestDateFrom')?.addEventListener('change', render);
  document.getElementById('requestDateTo')?.addEventListener('change', render);
  document.getElementById('requestFilterApply')?.addEventListener('click', render);
  document.getElementById('requestFilterReset')?.addEventListener('click', () => {
    document.getElementById('requestStatusFilter').value = '';
    document.getElementById('requestDateFrom').value = '';
    document.getElementById('requestDateTo').value = '';
    render();
  });

  btnRefresh.addEventListener('click', load);
  load();
})();
</script>
</body>
</html>
