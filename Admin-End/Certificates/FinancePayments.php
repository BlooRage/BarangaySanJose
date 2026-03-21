<?php
require_once __DIR__ . '/../../PhpFiles/General/connection.php';
require_once __DIR__ . '/../includes/admin_guard.php';

$financeBaseUrl = appUrl('/Admin-End/Certificates/FinancePayments.php');
$financeSection = strtolower(trim((string)($_GET['section'] ?? 'tracker')));
if (!in_array($financeSection, ['tracker', 'fees', 'cashbook'], true)) {
  $financeSection = 'tracker';
}

$financeFilterDocument = trim((string)($_GET['filter_document'] ?? ''));
$barangayIdAdminNavActive = 'payments';

function fp_redirect(string $baseUrl, string $section = 'tracker'): void
{
  $target = $baseUrl;
  if ($section !== 'tracker') {
    $target .= '?section=' . rawurlencode($section);
  }
  header('Location: ' . $target);
  exit;
}

function fp_set_flash(string $type, string $message): void
{
  $_SESSION['finance_fee_flash'] = [
    'type' => $type,
    'message' => $message,
  ];
}

function fp_take_flash(): ?array
{
  $flash = $_SESSION['finance_fee_flash'] ?? null;
  unset($_SESSION['finance_fee_flash']);
  return is_array($flash) ? $flash : null;
}

function fp_ensure_general_fees_table(mysqli $conn): void
{
  static $done = false;
  if ($done) {
    return;
  }

  $conn->query("
    CREATE TABLE IF NOT EXISTS generalfeestbl (
      fee_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      document_type_id INT(11) NOT NULL,
      amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (fee_id),
      UNIQUE KEY uq_generalfees_document_type (document_type_id),
      KEY idx_generalfees_amount (amount)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  ");

  $done = true;
}

function fp_is_fee_document_type(string $documentTypeName): bool
{
  return stripos(trim($documentTypeName), 'certificate') !== false;
}

function fp_parse_amount(mixed $rawAmount): float
{
  if ($rawAmount === null || $rawAmount === '') {
    throw new RuntimeException('Amount is required.');
  }
  if (!is_numeric($rawAmount)) {
    throw new RuntimeException('Amount must be a valid number.');
  }
  $amount = round((float)$rawAmount, 2);
  if ($amount < 0) {
    throw new RuntimeException('Amount cannot be negative.');
  }
  return $amount;
}

function fp_fetch_document_type(mysqli $conn, int $documentTypeId): ?array
{
  $stmt = $conn->prepare("
    SELECT document_type_id, document_type_name, document_category
    FROM documenttypelookuptbl
    WHERE document_type_id = ?
    LIMIT 1
  ");
  if (!$stmt) {
    return null;
  }
  $stmt->bind_param('i', $documentTypeId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc() ?: null;
  $stmt->close();
  return $row;
}

function fp_fetch_cashbook_entries(mysqli $conn, string $dateFrom, string $dateTo): array
{
  $rows = [];
  $stmt = $conn->prepare("
    SELECT
      dr.request_id,
      COALESCE(dr.document_type, 'Unknown') AS document_type,
      dr.or_number,
      dr.certificate_number,
      COALESCE(dr.fee_amount, 0) AS amount,
      COALESCE(dr.payment_method, '') AS payment_method,
      dr.finance_decision_at,
      dr.finance_user_id,
      TRIM(CONCAT_WS(' ',
        NULLIF(TRIM(COALESCE(ft.applicant_firstname, '')), ''),
        NULLIF(TRIM(COALESCE(ft.applicant_middleInitial, '')), ''),
        NULLIF(TRIM(COALESCE(ft.applicant_lastname, '')), '')
      )) AS applicant_name
    FROM documentrequesttbl dr
    LEFT JOIN financetransactiontbl ft ON ft.request_id = dr.request_id
    WHERE dr.or_number IS NOT NULL
      AND dr.or_number != ''
      AND DATE(dr.finance_decision_at) BETWEEN ? AND ?
    ORDER BY dr.finance_decision_at DESC
  ");
  if (!$stmt) return [];
  $stmt->bind_param('ss', $dateFrom, $dateTo);
  $stmt->execute();
  $result = $stmt->get_result();
  while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
  }
  $stmt->close();
  return $rows;
}

if ($financeSection === 'fees' || $_SERVER['REQUEST_METHOD'] === 'POST') {
  fp_ensure_general_fees_table($conn);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verifyCsrfToken(false);
  $action = trim((string)($_POST['action'] ?? ''));

  try {
    if ($action === 'add_fee') {
      $documentTypeId = (int)($_POST['document_type_id'] ?? 0);
      if ($documentTypeId <= 0) {
        throw new RuntimeException('Please choose a document type.');
      }

      $documentType = fp_fetch_document_type($conn, $documentTypeId);
      if (!$documentType) {
        throw new RuntimeException('Selected document type was not found.');
      }
      if (!fp_is_fee_document_type((string)($documentType['document_type_name'] ?? ''))) {
        throw new RuntimeException('Only certificate-type document requests can be priced here.');
      }

      $checkStmt = $conn->prepare("SELECT fee_id FROM generalfeestbl WHERE document_type_id = ? LIMIT 1");
      if ($checkStmt) {
        $checkStmt->bind_param('i', $documentTypeId);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();
        if ($exists) {
          throw new RuntimeException('A price already exists for that document type.');
        }
      }

      $amount = fp_parse_amount($_POST['amount'] ?? null);
      $insertStmt = $conn->prepare("INSERT INTO generalfeestbl (document_type_id, amount) VALUES (?, ?)");
      if (!$insertStmt) {
        throw new RuntimeException('Failed to prepare the insert statement.');
      }
      $insertStmt->bind_param('id', $documentTypeId, $amount);
      if (!$insertStmt->execute()) {
        $insertStmt->close();
        throw new RuntimeException('Failed to add the price.');
      }
      $insertStmt->close();

      fp_set_flash('success', 'Price added successfully.');
    } elseif ($action === 'update_fee') {
      $feeId = (int)($_POST['fee_id'] ?? 0);
      if ($feeId <= 0) {
        throw new RuntimeException('Fee record was not found.');
      }

      $amount = fp_parse_amount($_POST['amount'] ?? null);
      $updateStmt = $conn->prepare("UPDATE generalfeestbl SET amount = ?, updated_at = CURRENT_TIMESTAMP WHERE fee_id = ? LIMIT 1");
      if (!$updateStmt) {
        throw new RuntimeException('Failed to prepare the update statement.');
      }
      $updateStmt->bind_param('di', $amount, $feeId);
      if (!$updateStmt->execute()) {
        $updateStmt->close();
        throw new RuntimeException('Failed to update the price.');
      }
      $affected = $updateStmt->affected_rows;
      $updateStmt->close();
      if ($affected < 0) {
        throw new RuntimeException('Fee record was not updated.');
      }

      fp_set_flash('success', 'Price updated successfully.');
    } elseif ($action === 'delete_fee') {
      $feeId = (int)($_POST['fee_id'] ?? 0);
      if ($feeId <= 0) {
        throw new RuntimeException('Fee record was not found.');
      }

      $deleteStmt = $conn->prepare("DELETE FROM generalfeestbl WHERE fee_id = ? LIMIT 1");
      if (!$deleteStmt) {
        throw new RuntimeException('Failed to prepare the delete statement.');
      }
      $deleteStmt->bind_param('i', $feeId);
      if (!$deleteStmt->execute()) {
        $deleteStmt->close();
        throw new RuntimeException('Failed to delete the price.');
      }
      $deleteStmt->close();

      fp_set_flash('success', 'Price deleted successfully.');
    }
  } catch (Throwable $e) {
    fp_set_flash('danger', $e->getMessage());
  }

  fp_redirect($financeBaseUrl, 'fees');
}

$feeFlash = fp_take_flash();
$feeRows = [];
$availableFeeDocuments = [];
$editingFee = null;

$cashbookEntries   = [];
$cashbookTotal     = 0.0;
$cashbookDateFrom  = date('Y-m-d');
$cashbookDateTo    = date('Y-m-d');

if ($financeSection === 'cashbook') {
  $cashbookDateFrom = trim((string)($_GET['date_from'] ?? date('Y-m-d')));
  $cashbookDateTo   = trim((string)($_GET['date_to']   ?? date('Y-m-d')));
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $cashbookDateFrom)) $cashbookDateFrom = date('Y-m-d');
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $cashbookDateTo))   $cashbookDateTo   = date('Y-m-d');
  if ($cashbookDateFrom > $cashbookDateTo)                      $cashbookDateTo   = $cashbookDateFrom;
  $cashbookEntries = fp_fetch_cashbook_entries($conn, $cashbookDateFrom, $cashbookDateTo);
  $cashbookTotal   = (float)array_sum(array_column($cashbookEntries, 'amount'));
}

if ($financeSection === 'fees') {
  $feeRowsResult = $conn->query("
    SELECT
      gf.fee_id,
      gf.document_type_id,
      gf.amount,
      gf.updated_at,
      dt.document_type_name,
      dt.document_category
    FROM generalfeestbl gf
    LEFT JOIN documenttypelookuptbl dt
      ON dt.document_type_id = gf.document_type_id
    ORDER BY COALESCE(dt.document_type_name, CONCAT('Document Type #', gf.document_type_id)) ASC
  ");
  if ($feeRowsResult instanceof mysqli_result) {
    while ($row = $feeRowsResult->fetch_assoc()) {
      $feeRows[] = $row;
    }
    $feeRowsResult->close();
  }

  $availableDocsResult = $conn->query("
    SELECT
      dt.document_type_id,
      dt.document_type_name
    FROM documenttypelookuptbl dt
    LEFT JOIN generalfeestbl gf
      ON gf.document_type_id = dt.document_type_id
    WHERE gf.document_type_id IS NULL
      AND dt.document_category = 'DocumentRequest'
      AND LOWER(dt.document_type_name) LIKE '%certificate%'
    ORDER BY dt.document_type_name ASC
  ");
  if ($availableDocsResult instanceof mysqli_result) {
    while ($row = $availableDocsResult->fetch_assoc()) {
      $availableFeeDocuments[] = $row;
    }
    $availableDocsResult->close();
  }

  $editFeeId = (int)($_GET['edit_fee'] ?? 0);
  if ($editFeeId > 0) {
    foreach ($feeRows as $row) {
      if ((int)($row['fee_id'] ?? 0) === $editFeeId) {
        $editingFee = $row;
        break;
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Finance Payments</title>
  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/barangayIdAdminNav.css">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css">
  <style>
    .finance-fee-shell {
      max-width: 1360px;
      margin: 0 auto;
    }
    .finance-fee-card {
      border: 1px solid #ececec;
      border-radius: 24px;
      background: #fff;
      box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.06);
    }
    .finance-fee-form-note {
      font-size: 0.9rem;
      color: #6c757d;
    }
    .finance-fee-table th,
    .finance-fee-table td {
      vertical-align: middle;
    }
    .finance-fee-amount {
      font-weight: 700;
      color: #198754;
    }
    .finance-fee-empty {
      border: 1px dashed #d0d7de;
      border-radius: 16px;
      background: #f8fafc;
      padding: 16px;
      color: #6b7280;
    }
    .certificate-tracker-shell {
      max-width: 1340px;
      margin: 0 auto;
    }
    .certificate-tracker-shell .admin-list-toolbar {
      overflow-x: visible;
      overflow-y: visible;
      flex-wrap: wrap;
      row-gap: 12px;
    }
    .certificate-tracker-shell .admin-list-tabs {
      gap: 12px;
      overflow: visible;
    }
    .certificate-tracker-shell .stage-filter-btn {
      border-radius: 10px;
      border-width: 1px;
      min-width: 140px;
      position: relative;
    }
    .certificate-tracker-shell .stage-filter-btn .tab-count {
      position: absolute;
      top: -7px;
      right: -7px;
      min-width: 20px;
      height: 20px;
      padding: 0 6px;
      border-radius: 999px;
      background: #dc3545;
      color: #fff;
      font-size: .72rem;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      line-height: 1;
      box-shadow: none;
    }
    .certificate-tracker-shell .status-filter-btn[data-status-filter="all"] {
      border-color: #0d6efd;
      color: #0d6efd;
    }
    .certificate-tracker-shell .status-filter-btn[data-status-filter="all"].active {
      background: #0d6efd;
      border-color: #0d6efd;
      color: #fff;
    }
    .certificate-tracker-shell .status-filter-btn:not([data-status-filter="all"]).active {
      background: #fff3e4;
      border-color: #fe993c;
      color: #a04f00;
    }
    .certificate-tracker-shell .admin-list-actions .input-group-text,
    .certificate-tracker-shell .admin-list-actions .form-control {
      height: 38px;
    }
    .certificate-tracker-shell .admin-search {
      min-width: 300px;
      max-width: 360px;
    }
    .certificate-tracker-shell .table-responsive {
      overflow-x: auto;
      overflow-y: visible;
      -webkit-overflow-scrolling: touch;
    }
    #table-certificateTracker {
      table-layout: auto;
      width: 100%;
      min-width: 1100px;
    }
    #table-certificateTracker th,
    #table-certificateTracker td {
      vertical-align: middle;
    }
    #table-certificateTracker .col-request-id { width: 11%; }
    #table-certificateTracker .col-resident-id { width: 11%; }
    #table-certificateTracker .col-full-name { width: 18%; }
    #table-certificateTracker .col-document { width: 15%; }
    #table-certificateTracker .col-purpose { width: 17%; }
    #table-certificateTracker .col-status { width: 13%; }
    #table-certificateTracker .col-submitted { width: 10%; }
    #table-certificateTracker .col-action { width: 15%; }

    #viewModal .modal-dialog {
      width: min(88vw, 920px);
      max-width: 920px;
      height: 78vh;
    }
    #viewModal .modal-content {
      border: 0;
      border-radius: .5rem;
      padding: 1rem;
      overflow: hidden;
      height: 100%;
      background: #ffffff;
    }
    #viewModal .modal-header {
      border-bottom: 1px solid #e9ecef;
      background: #ffffff;
    }
    #viewModal .modal-body {
      overflow-y: auto;
      overflow-x: hidden;
      min-height: 0;
      background: #ffffff;
      padding: 14px;
    }
    #viewModal .tracker-profile-view {
      display: grid;
      gap: 12px;
    }
    #viewModal .tracker-doc-highlight {
      border: 1px solid #bfdbfe;
      background: #dbeafe;
      color: #1e3a8a;
      border-radius: 12px;
      padding: 10px 14px;
      font-weight: 700;
    }
    #viewModal .tracker-form-section {
      border: 1px solid #e78924;
      background: #ffffff;
      border-radius: 12px;
      padding: 12px;
      margin-top: 10px;
    }
    #viewModal .tracker-form-section-title {
      margin: 0 0 10px;
      font-size: 1rem;
      font-weight: 700;
      color: #212529;
      border-bottom: 1px dashed #e9ecef;
      padding-bottom: 6px;
    }
    #viewModal .tracker-form-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 14px 12px;
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
    #viewModal .tracker-form-field--wide {
      grid-column: 1 / -1;
    }
    #viewModal .tracker-form-label {
      margin: 6px 0 0;
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
    #viewModal .tracker-status-actions {
      margin-top: 10px;
      padding-top: 10px;
      border-top: 1px dashed #e5e7eb;
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
      width: 100%;
    }
    #viewModal .submitted-docs-grid {
      display: grid;
      grid-template-columns: minmax(240px, 1fr) minmax(280px, 1.1fr);
      gap: 16px;
    }
    #viewModal .submitted-docs-list {
      display: grid;
      gap: 12px;
    }
    #viewModal .submitted-docs-item {
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      padding: 10px 12px;
      background: #ffffff;
    }
    #viewModal .submitted-docs-item__label {
      font-size: .76rem;
      font-weight: 700;
      color: #6b7280;
      margin-bottom: 6px;
    }
    #viewModal .submitted-docs-item__meta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }
    #viewModal .submitted-docs-preview {
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      background: #f8fafc;
      padding: 12px;
      display: grid;
      grid-template-rows: auto 1fr;
      gap: 10px;
      min-height: 220px;
    }
    #viewModal .submitted-docs-preview__header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }
    #viewModal .submitted-docs-preview__name {
      font-weight: 600;
      color: #111827;
    }
    #viewModal .submitted-docs-preview__placeholder {
      color: #6b7280;
      font-size: .9rem;
      text-align: center;
      padding: 24px 8px;
    }
    #viewModal .submitted-docs-preview__body iframe {
      width: 100%;
      height: 60vh;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      background: #fff;
    }
    #viewModal .submitted-docs-preview__body img {
      max-width: 100%;
      max-height: 60vh;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      background: #fff;
    }
    @media (max-width: 767px) {
      #viewModal .tracker-form-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 992px) {
      #viewModal .submitted-docs-grid { grid-template-columns: 1fr; }
      #viewModal .submitted-docs-preview__body iframe,
      #viewModal .submitted-docs-preview__body img { height: 45vh; }
    }
    @media (max-width: 767px) {
      .certificate-tracker-shell .stage-filter-btn {
        min-width: 118px;
      }
    }
    #paymentsPanel,
    #clearanceFeesPanel {
      border-top-left-radius: 0 !important;
    }
    #feeTypesTableBody tr { cursor: default; }
    .cashbook-table th, .cashbook-table td { font-size: .88rem; }
    @media print {
      body { background: #fff !important; }
      #main-display { padding: 0 !important; }
      .d-print-none, aside, header, #dashboard-sidebar, #admin-mobile-header { display: none !important; }
      .cashbook-shell { max-width: 100% !important; }
      .finance-fee-card { box-shadow: none !important; border: none !important; }
    }
  </style>
</head>
<body>
<div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
    <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C;">Finance Payments</h2>
    <hr class="mb-4">
    <?php if ($financeSection === 'tracker' && strcasecmp($financeFilterDocument, 'Barangay ID') === 0): ?>
      <?php include __DIR__ . '/includes/barangay_id_admin_nav.php'; ?>
    <?php endif; ?>

    <?php if ($financeSection === 'fees'): ?>
      <ul class="nav nav-tabs mb-0" id="feesTabs" style="border-bottom:0">
        <li class="nav-item">
          <button class="nav-link active fw-semibold" id="tabGeneralFees" type="button">
            <i class="fas fa-file-invoice-dollar me-1"></i>General Fees
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link fw-semibold" id="tabClearanceFees" type="button">
            <i class="fas fa-tags me-1"></i>Clearance Fees
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link fw-semibold" id="tabPendingRequests" type="button">
            <i class="fas fa-bell me-1"></i>Pending Requests
            <span id="pendingRequestsBadge" class="badge bg-danger ms-1 d-none">0</span>
          </button>
        </li>
      </ul>
      <div id="generalFeesPanel" class="finance-fee-shell">
        <?php if ($feeFlash): ?>
          <div class="alert alert-<?= htmlspecialchars((string)($feeFlash['type'] ?? 'info'), ENT_QUOTES, 'UTF-8') ?> rounded-4 shadow-sm">
            <?= htmlspecialchars((string)($feeFlash['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
          </div>
        <?php endif; ?>

        <div class="row g-4">
          <div class="col-12 col-lg-4">
            <div class="finance-fee-card p-4 h-100">
              <h4 class="mb-2" style="font-family: 'Charis SIL Bold'; color: #DE710C;">
                <?= $editingFee ? 'Edit Price' : 'Add New Price' ?>
              </h4>
              <p class="finance-fee-form-note mb-4">
                Manage the prices stored in <code>generalfeestbl</code>. Only certificate-type document requests are handled here.
              </p>

              <?php if ($editingFee): ?>
                <form method="post" action="<?= htmlspecialchars($financeBaseUrl, ENT_QUOTES, 'UTF-8') ?>?section=fees" class="d-grid gap-3">
                  <?= csrfTokenField() ?>
                  <input type="hidden" name="action" value="update_fee">
                  <input type="hidden" name="fee_id" value="<?= (int)($editingFee['fee_id'] ?? 0) ?>">

                  <div>
                    <label class="form-label fw-semibold">Document Type</label>
                    <input type="text"
                           class="form-control"
                           value="<?= htmlspecialchars((string)($editingFee['document_type_name'] ?? ('Document Type #' . (int)($editingFee['document_type_id'] ?? 0))), ENT_QUOTES, 'UTF-8') ?>"
                           readonly>
                  </div>

                  <div>
                    <label class="form-label fw-semibold">Amount</label>
                    <input type="number"
                           name="amount"
                           class="form-control"
                           min="0"
                           step="0.01"
                           value="<?= htmlspecialchars(number_format((float)($editingFee['amount'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                           required>
                  </div>

                  <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="<?= htmlspecialchars($financeBaseUrl, ENT_QUOTES, 'UTF-8') ?>?section=fees" class="btn btn-outline-secondary">Cancel</a>
                  </div>
                </form>
              <?php else: ?>
                <form method="post" action="<?= htmlspecialchars($financeBaseUrl, ENT_QUOTES, 'UTF-8') ?>?section=fees" class="d-grid gap-3">
                  <?= csrfTokenField() ?>
                  <input type="hidden" name="action" value="add_fee">

                  <div>
                    <label for="feeDocumentType" class="form-label fw-semibold">Document Type</label>
                    <select id="feeDocumentType" name="document_type_id" class="form-select" required>
                      <option value="">Select a certificate request</option>
                      <?php foreach ($availableFeeDocuments as $document): ?>
                        <option value="<?= (int)($document['document_type_id'] ?? 0) ?>">
                          <?= htmlspecialchars((string)($document['document_type_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <?php if (!$availableFeeDocuments): ?>
                      <div class="form-text">All available certificate request types already have prices assigned.</div>
                    <?php endif; ?>
                  </div>

                  <div>
                    <label for="feeAmount" class="form-label fw-semibold">Amount</label>
                    <input id="feeAmount"
                           type="number"
                           name="amount"
                           class="form-control"
                           min="0"
                           step="0.01"
                           placeholder="0.00"
                           required>
                  </div>

                  <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary" <?= !$availableFeeDocuments ? 'disabled' : '' ?>>Add Price</button>
                  </div>
                </form>
              <?php endif; ?>
            </div>
          </div>

          <div class="col-12 col-lg-8">
            <div class="finance-fee-card p-4 h-100">
              <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                <div>
                  <h4 class="mb-1" style="font-family: 'Charis SIL Bold'; color: #DE710C;">Current General Fees</h4>
                  <p class="text-muted mb-0">Edit, add, or remove the prices currently stored in <code>generalfeestbl</code>.</p>
                </div>
                <div class="badge bg-light text-dark border px-3 py-2">
                  <?= count($feeRows) ?> fee<?= count($feeRows) === 1 ? '' : 's' ?>
                </div>
              </div>

              <?php if (!$feeRows): ?>
                <div class="finance-fee-empty">
                  No prices are currently saved in <code>generalfeestbl</code>.
                </div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table finance-fee-table align-middle">
                    <thead class="table-light">
                      <tr>
                        <th>Document Type</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Last Updated</th>
                        <th class="text-end">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($feeRows as $row): ?>
                        <tr>
                          <td>
                            <div class="fw-semibold">
                              <?= htmlspecialchars((string)($row['document_type_name'] ?? ('Document Type #' . (int)($row['document_type_id'] ?? 0))), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div class="small text-muted">Document Type ID: <?= (int)($row['document_type_id'] ?? 0) ?></div>
                          </td>
                          <td><?= htmlspecialchars((string)($row['document_category'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></td>
                          <td class="finance-fee-amount">PHP <?= number_format((float)($row['amount'] ?? 0), 2) ?></td>
                          <td><?= htmlspecialchars((string)($row['updated_at'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></td>
                          <td class="text-end">
                            <div class="d-inline-flex flex-wrap gap-2 justify-content-end">
                              <a href="<?= htmlspecialchars($financeBaseUrl, ENT_QUOTES, 'UTF-8') ?>?section=fees&amp;edit_fee=<?= (int)($row['fee_id'] ?? 0) ?>"
                                 class="btn btn-sm btn-outline-primary">
                                Edit
                              </a>
                              <form method="post" action="<?= htmlspecialchars($financeBaseUrl, ENT_QUOTES, 'UTF-8') ?>?section=fees" onsubmit="return confirm('Delete this price?');">
                                <?= csrfTokenField() ?>
                                <input type="hidden" name="action" value="delete_fee">
                                <input type="hidden" name="fee_id" value="<?= (int)($row['fee_id'] ?? 0) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                              </form>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div><!-- end #generalFeesPanel -->

      <!-- ── CLEARANCE FEES PANEL ───────────────────────────────────────────── -->
      <div id="clearanceFeesPanel" class="d-none bg-white p-4 rounded-4 rounded-tl-0 shadow-sm border">
        <div class="row g-4">

          <!-- Left: fee types table -->
          <div class="col-lg-7">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="fw-bold mb-0">Clearance Fee Types</h5>
              <button class="btn btn-sm btn-outline-secondary" id="btnRefreshFeeTable" title="Refresh">
                <i class="fa-solid fa-arrows-rotate"></i>
              </button>
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle">
                <thead class="table-light">
                  <tr>
                    <th>#</th>
                    <th>Fee Name</th>
                    <th>Default Amount</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody id="feeTypesTableBody">
                  <tr><td colspan="5" class="text-center text-muted py-3">Loading…</td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Right: add / edit form -->
          <div class="col-lg-5">
            <div class="border rounded-3 p-3 bg-light">
              <h6 class="fw-semibold mb-3" id="feeFormTitle">
                <i class="fas fa-plus-circle me-1 text-primary"></i>Add Fee Type
              </h6>
              <input type="hidden" id="feeFormId">
              <div class="mb-3">
                <label class="form-label fw-semibold small">Fee Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="feeFormName" placeholder="e.g. Inspection Fee, Application Fee">
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold small">Default Amount (₱)</label>
                <div class="input-group">
                  <span class="input-group-text">₱</span>
                  <input type="number" class="form-control" id="feeFormAmount" value="0.00" min="0" step="0.01">
                </div>
                <div class="form-text">Used as the pre-filled default when admin tags fees to a request.</div>
              </div>
              <div class="mb-3 form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="feeFormActive" checked>
                <label class="form-check-label small" for="feeFormActive">Active (appears when tagging fees)</label>
              </div>
              <div id="feeFormError" class="alert alert-danger d-none py-2 small mb-3"></div>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary flex-fill" id="feeFormSaveBtn">
                  <i class="fas fa-save me-1"></i>Save
                </button>
                <button type="button" class="btn btn-outline-secondary" id="feeFormCancelBtn" title="Reset form">
                  <i class="fas fa-times"></i>
                </button>
              </div>
            </div>
          </div>

        </div>
      </div><!-- end #clearanceFeesPanel -->

      <!-- ── PENDING FEE CHANGE REQUESTS PANEL ──────────────────────────── -->
      <div id="pendingRequestsPanel" class="d-none bg-white p-4 rounded-4 rounded-tl-0 shadow-sm border">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0">Pending Fee Change Requests</h5>
          <button class="btn btn-sm btn-outline-secondary" id="btnRefreshPendingRequests" title="Refresh">
            <i class="fa-solid fa-arrows-rotate"></i>
          </button>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th>Type</th>
                <th>Fee Name</th>
                <th>Current Amount</th>
                <th>Proposed Amount</th>
                <th>Notes</th>
                <th>Requested By</th>
                <th>Submitted</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody id="pendingRequestsBody">
              <tr><td colspan="8" class="text-center text-muted py-3">Loading…</td></tr>
            </tbody>
          </table>
        </div>
      </div>

    <?php elseif ($financeSection === 'cashbook'): ?>
      <div class="cashbook-shell finance-fee-shell">
        <div class="finance-fee-card p-4">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4 d-print-none">
            <div>
              <h4 class="mb-1" style="font-family: 'Charis SIL Bold'; color: #DE710C;">Cashbook</h4>
              <p class="text-muted mb-0 small">Daily collection log of all verified payments with issued OR numbers.</p>
            </div>
            <button class="btn btn-outline-dark btn-sm" onclick="window.print()">
              <i class="fas fa-print me-1"></i>Print
            </button>
          </div>

          <!-- Date range filter -->
          <form method="get" action="" class="d-flex flex-wrap gap-2 align-items-end mb-4 d-print-none">
            <input type="hidden" name="section" value="cashbook">
            <div>
              <label class="form-label fw-semibold small mb-1">Date From</label>
              <input type="date" name="date_from" class="form-control form-control-sm"
                     value="<?= htmlspecialchars($cashbookDateFrom, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div>
              <label class="form-label fw-semibold small mb-1">Date To</label>
              <input type="date" name="date_to" class="form-control form-control-sm"
                     value="<?= htmlspecialchars($cashbookDateTo, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-sm align-self-end">Apply</button>
            <?php if ($cashbookDateFrom !== date('Y-m-d') || $cashbookDateTo !== date('Y-m-d')): ?>
              <a href="?section=cashbook" class="btn btn-outline-secondary btn-sm align-self-end">Today</a>
            <?php endif; ?>
          </form>

          <!-- Print-only header -->
          <div class="d-none d-print-block mb-3">
            <h5 class="fw-bold">Barangay San Jose — Collection Register</h5>
            <div class="small">Period: <?= htmlspecialchars($cashbookDateFrom, ENT_QUOTES, 'UTF-8') ?> to <?= htmlspecialchars($cashbookDateTo, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="small text-muted">Printed: <?= date('Y-m-d H:i') ?></div>
          </div>

          <?php if (!$cashbookEntries): ?>
            <div class="finance-fee-empty text-center py-5">
              <i class="fas fa-book-open fa-2x mb-3 d-block text-muted"></i>
              No verified payments found for the selected date range.
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm table-bordered align-middle finance-fee-table cashbook-table">
                <thead class="table-light">
                  <tr>
                    <th>#</th>
                    <th>Date &amp; Time</th>
                    <th>OR Number</th>
                    <th>Certificate No.</th>
                    <th>Resident / Applicant</th>
                    <th>Document Type</th>
                    <th>Method</th>
                    <th class="text-end">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($cashbookEntries as $i => $entry): ?>
                    <?php $method = strtolower((string)($entry['payment_method'] ?? '')); ?>
                    <tr>
                      <td class="text-muted small"><?= $i + 1 ?></td>
                      <td class="small"><?= htmlspecialchars((string)($entry['finance_decision_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                      <td class="fw-semibold"><?= htmlspecialchars((string)($entry['or_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                      <td class="small text-muted"><?= htmlspecialchars((string)($entry['certificate_number'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                      <td>
                        <div class="fw-semibold small"><?= htmlspecialchars((string)($entry['applicant_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="text-muted" style="font-size:.73rem"><?= htmlspecialchars((string)($entry['request_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                      </td>
                      <td class="small"><?= htmlspecialchars((string)($entry['document_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                      <td>
                        <span class="badge <?= $method === 'gcash' ? 'bg-primary' : 'bg-secondary' ?>">
                          <?= $method === 'gcash' ? 'GCash' : ($method === 'barangay' ? 'Walk-in' : htmlspecialchars(ucfirst($method), ENT_QUOTES, 'UTF-8')) ?>
                        </span>
                      </td>
                      <td class="text-end finance-fee-amount">₱<?= number_format((float)($entry['amount'] ?? 0), 2) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                  <tr>
                    <td colspan="7" class="text-end">
                      Total Collections (<?= count($cashbookEntries) ?> transaction<?= count($cashbookEntries) === 1 ? '' : 's' ?>)
                    </td>
                    <td class="text-end finance-fee-amount">₱<?= number_format($cashbookTotal, 2) ?></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

    <?php else: ?>
      <div class="bg-white p-4 rounded-4 shadow-sm border resident-masterlist-shell certificate-tracker-shell">
        <div class="admin-list-toolbar mb-3">
          <div class="admin-list-tabs">
            <button type="button" class="btn btn-outline-primary btn-sm status-filter-btn stage-filter-btn active" data-status-filter="all">All</button>
            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn stage-filter-btn fw-semibold" data-status-filter="verified">Verified</button>
            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn stage-filter-btn fw-semibold" data-status-filter="unpaid">Unpaid <span class="tab-count" id="unpaidTabCount">0</span></button>
            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn stage-filter-btn fw-semibold" data-status-filter="pending_verification">Pending Verification <span class="tab-count" id="pendingVerificationTabCount">0</span></button>
          </div>

          <div class="admin-list-actions">
            <div class="input-group admin-search">
              <input type="text" id="searchInput" class="form-control" placeholder="Request ID, resident ID, name, document">
              <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
            </div>
            <button class="btn btn-outline-secondary btn-icon admin-filter" type="button" data-bs-toggle="modal" data-bs-target="#modalFinanceFilter" id="btnFinanceFilter" title="Filter" aria-label="Filter">
              <i class="fas fa-filter"></i>
              <span class="visually-hidden">Filter</span>
            </button>
            <button class="btn btn-outline-secondary btn-icon admin-columns" type="button" data-bs-toggle="modal" data-bs-target="#modalFinanceColumns" id="btnFinanceColumns" title="Columns" aria-label="Columns">
              <i class="fa-solid fa-sliders"></i>
              <span class="visually-hidden">Columns</span>
            </button>
            <button class="btn btn-outline-secondary btn-icon admin-refresh" type="button" id="btnRefreshList" title="Refresh table" aria-label="Refresh table">
              <i class="fa-solid fa-arrows-rotate"></i>
              <span class="visually-hidden">Refresh</span>
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table id="table-certificateTracker" class="table align-middle">
            <thead>
              <tr class="table-light">
                <th class="col-request-id">Request ID</th>
                <th class="col-resident-id">Resident ID</th>
                <th class="col-full-name">Full Name</th>
                <th class="col-document">Document Requested</th>
                <th class="col-purpose">Purpose</th>
                <th class="col-status">Status</th>
                <th class="col-submitted">Submitted Date</th>
                <th class="col-action">Action</th>
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
    <?php endif; ?>
  </main>
</div>

<?php if ($financeSection === 'tracker' || $financeSection === 'cashbook'): ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>
<?php if ($financeSection === 'tracker'): ?>
<div class="modal fade" id="modalFinanceFilter" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-3">
      <div class="modal-header border-0 pb-1">
        <h5 class="modal-title fw-bold">Filter Finance Payments</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <hr class="my-2">
      <div class="modal-body">
        <div class="mb-3">
          <label for="financeFilterDocumentType" class="form-label fw-semibold">Document Type</label>
          <select id="financeFilterDocumentType" class="form-select">
            <option value="">All documents</option>
          </select>
        </div>
        <div>
          <label for="financeFilterPaymentMethod" class="form-label fw-semibold">Payment Method</label>
          <select id="financeFilterPaymentMethod" class="form-select">
            <option value="">All payment methods</option>
            <option value="gcash">GCash</option>
            <option value="barangay">Pay in Barangay</option>
          </select>
        </div>
      </div>
      <div class="modal-footer border-0 pt-1">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-warning" id="btnFinanceFilterReset">Reset</button>
        <button type="button" class="btn btn-primary" id="btnFinanceFilterApply">Apply Filter</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalFinanceColumns" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-3">
      <div class="modal-header border-0 pb-1">
        <h5 class="modal-title fw-bold">Choose Columns</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <hr class="my-2">
      <div class="modal-body d-grid gap-2">
        <label class="table-columns-check form-check d-flex align-items-center justify-content-between mb-0">
          <span>Request ID</span><input class="form-check-input" type="checkbox" data-finance-col-index="1" checked>
        </label>
        <label class="table-columns-check form-check d-flex align-items-center justify-content-between mb-0">
          <span>Resident ID</span><input class="form-check-input" type="checkbox" data-finance-col-index="2" checked>
        </label>
        <label class="table-columns-check form-check d-flex align-items-center justify-content-between mb-0">
          <span>Full Name</span><input class="form-check-input" type="checkbox" data-finance-col-index="3" checked>
        </label>
        <label class="table-columns-check form-check d-flex align-items-center justify-content-between mb-0">
          <span>Document Requested</span><input class="form-check-input" type="checkbox" data-finance-col-index="4" checked>
        </label>
        <label class="table-columns-check form-check d-flex align-items-center justify-content-between mb-0">
          <span>Purpose</span><input class="form-check-input" type="checkbox" data-finance-col-index="5" checked>
        </label>
        <label class="table-columns-check form-check d-flex align-items-center justify-content-between mb-0">
          <span>Status</span><input class="form-check-input" type="checkbox" data-finance-col-index="6" checked>
        </label>
        <label class="table-columns-check form-check d-flex align-items-center justify-content-between mb-0">
          <span>Submitted Date</span><input class="form-check-input" type="checkbox" data-finance-col-index="7" checked>
        </label>
        <label class="table-columns-check form-check d-flex align-items-center justify-content-between mb-0">
          <span>Action</span><input class="form-check-input" type="checkbox" data-finance-col-index="8" checked>
        </label>
      </div>
      <div class="modal-footer border-0 pt-1">
        <button type="button" class="btn btn-warning" id="btnFinanceColumnsReset">Reset</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content p-3" id="actionForm" enctype="multipart/form-data">
      <div class="modal-header justify-content-center border-0 pb-0">
        <h5 class="modal-title fw-bold text-center w-100" id="actionModalTitle">Update Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <hr class="my-2">
      <div class="modal-body">
        <input type="hidden" id="actionType" name="action">
        <input type="hidden" id="actionRequestId" name="request_id">
        <div id="actionPrompt" class="d-none mb-3"></div>

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
      <div class="modal-footer border-0 pt-0 d-flex gap-2 w-100">
        <button type="button" id="actionCancelBtn" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="modal">Return</button>
        <button type="submit" id="actionSubmitBtn" class="btn btn-primary flex-fill">Submit</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewModalTitle">Certificate Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="viewDetailsBody" class="tracker-profile-view"></div>
      </div>
      <div class="modal-footer d-flex justify-content-between flex-wrap gap-2">
        <div class="d-flex flex-wrap gap-2" id="viewModalActions">
          <button type="button" class="btn btn-primary d-none" id="viewModalDocBtn">View Document</button>
          <button type="button" class="btn btn-outline-secondary d-none" id="viewModalBackBtn">Back</button>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-success d-none" id="viewModalWalkInBtn">Record Walk-in Payment</button>
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
        <h5 class="modal-title" id="paymentProofTitle">Payment Proof</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="paymentProofWrap" class="w-100 text-center"></div>
      </div>
      <div class="modal-footer">
        <button type="button" id="paymentProofReturnBtn" class="btn btn-secondary d-none">Return</button>
        <button type="button" id="paymentProofPrintBtn" class="btn btn-outline-dark d-none">Print</button>
        <a id="paymentProofOpenNew" class="btn btn-outline-primary" target="_blank" rel="noopener">Open in New Tab</a>
        <button type="button" id="paymentProofCloseBtn" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="submittedFileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="submittedFileTitle">Submitted Attachment Viewer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="submittedFileWrap" class="w-100 text-center"></div>
      </div>
      <div class="modal-footer">
        <button type="button" id="submittedFileReturnBtn" class="btn btn-secondary d-none">Return</button>
        <a id="submittedFileOpenNew" class="btn btn-outline-primary" target="_blank" rel="noopener">Open Attachment in New Tab</a>
        <button type="button" id="submittedFileCloseBtn" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
window.CERT_TRACKER_DEFAULT_STAGE = 'finance';
</script>
<script src="../../JS-Script-Files/Shared/barangayIdDigital.js?v=20260320-26"></script>
<script src="../../JS-Script-Files/Admin-End/certificateTrackerScript.js?v=20260320-06"></script>
<?php else: ?>
<script>
(function () {
  'use strict';

  const API = (function () {
    const base = window.location.pathname.replace(/\/[^/]*$/, '');
    return base.replace(/\/Admin-End\/Certificates$/, '') + '/PhpFiles/Admin-End/documentRequestWorkflow.php';
  })();

  // ── Page tab switching ────────────────────────────────────────────────────
  const tabGeneralFees      = document.getElementById('tabGeneralFees');
  const tabClearanceFees    = document.getElementById('tabClearanceFees');
  const tabPendingRequests  = document.getElementById('tabPendingRequests');
  const generalFeesPanel    = document.getElementById('generalFeesPanel');
  const feesPanel           = document.getElementById('clearanceFeesPanel');
  const pendingRequestsPanel = document.getElementById('pendingRequestsPanel');
  let feesLoaded = false;
  let pendingLoaded = false;

  function showGeneralTab() {
    tabGeneralFees.classList.add('active');
    tabClearanceFees.classList.remove('active');
    tabPendingRequests.classList.remove('active');
    generalFeesPanel.classList.remove('d-none');
    feesPanel.classList.add('d-none');
    pendingRequestsPanel.classList.add('d-none');
  }

  function showFeesTab() {
    tabClearanceFees.classList.add('active');
    tabGeneralFees.classList.remove('active');
    tabPendingRequests.classList.remove('active');
    feesPanel.classList.remove('d-none');
    generalFeesPanel.classList.add('d-none');
    pendingRequestsPanel.classList.add('d-none');
    if (!feesLoaded) { feesLoaded = true; loadFeeTypes(); }
  }

  function showPendingTab() {
    tabPendingRequests.classList.add('active');
    tabGeneralFees.classList.remove('active');
    tabClearanceFees.classList.remove('active');
    pendingRequestsPanel.classList.remove('d-none');
    generalFeesPanel.classList.add('d-none');
    feesPanel.classList.add('d-none');
    if (!pendingLoaded) { pendingLoaded = true; loadPendingRequests(); }
  }

  tabGeneralFees.addEventListener('click', showGeneralTab);
  tabClearanceFees.addEventListener('click', showFeesTab);
  tabPendingRequests.addEventListener('click', showPendingTab);

  // ── Helpers ───────────────────────────────────────────────────────────────
  function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g, (m) =>
      ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
  }

  function showFormError(msg) {
    const el = document.getElementById('feeFormError');
    if (!el) return;
    el.textContent = msg;
    el.classList.toggle('d-none', !msg);
  }

  // ── Load & render fee types ───────────────────────────────────────────────
  async function loadFeeTypes() {
    const tbody = document.getElementById('feeTypesTableBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-1"></i>Loading…</td></tr>';
    try {
      const res  = await fetch(`${API}?action=list_fee_types`);
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'Failed to load.');
      renderFeeTable(data.fee_types || []);
    } catch (e) {
      tbody.innerHTML = `<tr><td colspan="5" class="text-danger text-center py-3">${esc(e.message)}</td></tr>`;
    }
  }

  function renderFeeTable(rows) {
    const tbody = document.getElementById('feeTypesTableBody');
    if (!tbody) return;
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="text-muted text-center py-3">No fee types yet. Add one using the form.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map((ft, i) => `
      <tr>
        <td class="text-muted small">${i + 1}</td>
        <td class="fw-semibold">${esc(ft.fee_name)}</td>
        <td>₱${Number(ft.default_amount).toFixed(2)}</td>
        <td>
          <span class="badge ${ft.status === 'approved' ? 'bg-success' : 'bg-secondary'}">
            ${ft.status === 'approved' ? 'Active' : (ft.status || 'Inactive')}
          </span>
        </td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-primary me-1 py-0 px-2"
            onclick="financeEditFee(${ft.fee_type_id},${JSON.stringify(ft.fee_name)},${ft.default_amount},${JSON.stringify(ft.status)})">
            <i class="fas fa-pen"></i>
          </button>
          <button class="btn btn-sm btn-outline-danger py-0 px-2"
            onclick="financeDeleteFee(${ft.fee_type_id},${JSON.stringify(ft.fee_name)})">
            <i class="fas fa-trash"></i>
          </button>
        </td>
      </tr>`).join('');
  }

  // ── Form helpers ──────────────────────────────────────────────────────────
  function resetForm() {
    document.getElementById('feeFormId').value     = '';
    document.getElementById('feeFormName').value   = '';
    document.getElementById('feeFormAmount').value = '0.00';
    document.getElementById('feeFormActive').checked = true;
    document.getElementById('feeFormTitle').innerHTML =
      '<i class="fas fa-plus-circle me-1 text-primary"></i>Add Fee Type';
    showFormError('');
  }

  window.financeEditFee = function (id, name, amount, status) {
    document.getElementById('feeFormId').value     = id;
    document.getElementById('feeFormName').value   = name;
    document.getElementById('feeFormAmount').value = Number(amount).toFixed(2);
    document.getElementById('feeFormActive').checked = status === 'approved';
    document.getElementById('feeFormTitle').innerHTML =
      '<i class="fas fa-pen me-1 text-warning"></i>Edit Fee Type';
    showFormError('');
    document.getElementById('feeFormName').focus();
  };

  window.financeDeleteFee = async function (id, name) {
    if (!confirm(`Delete fee type "${name}"?\n\nThis cannot be undone.`)) return;
    try {
      const body = new FormData();
      body.append('action', 'delete_fee_type');
      body.append('fee_type_id', id);
      const res  = await fetch(API, { method: 'POST', body });
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'Delete failed.');
      await loadFeeTypes();
    } catch (e) {
      alert(e.message);
    }
  };

  // ── Save (create / update) ────────────────────────────────────────────────
  async function saveFeeType() {
    const id     = document.getElementById('feeFormId').value.trim();
    const name   = document.getElementById('feeFormName').value.trim();
    const amount = parseFloat(document.getElementById('feeFormAmount').value) || 0;
    const active = document.getElementById('feeFormActive').checked;
    showFormError('');
    if (!name) { showFormError('Fee name is required.'); document.getElementById('feeFormName').focus(); return; }

    const saveBtn = document.getElementById('feeFormSaveBtn');
    saveBtn.disabled = true;
    const origHtml = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving…';

    try {
      const body = new FormData();
      body.append('action', 'save_fee_type');
      if (id) body.append('fee_type_id', id);
      body.append('fee_name', name);
      body.append('default_amount', String(amount));
      body.append('status', active ? 'approved' : 'rejected');
      const res  = await fetch(API, { method: 'POST', body });
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'Save failed.');
      resetForm();
      await loadFeeTypes();
    } catch (e) {
      showFormError(e.message);
    } finally {
      saveBtn.disabled = false;
      saveBtn.innerHTML = origHtml;
    }
  }

  // ── Wire buttons ──────────────────────────────────────────────────────────
  document.getElementById('feeFormSaveBtn').addEventListener('click', saveFeeType);
  document.getElementById('feeFormCancelBtn').addEventListener('click', resetForm);
  document.getElementById('btnRefreshFeeTable').addEventListener('click', loadFeeTypes);

  // Allow Enter key in fee name field to save
  document.getElementById('feeFormName').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); saveFeeType(); }
  });

  // ── Pending Fee Change Requests ───────────────────────────────────────────
  async function loadPendingRequests() {
    const tbody = document.getElementById('pendingRequestsBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-1"></i>Loading…</td></tr>';
    try {
      const res  = await fetch(`${API}?action=list_fee_change_requests&scope=all_pending`);
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'Failed.');
      renderPendingRequests(data.requests || data.fee_types || []);
    } catch (e) {
      tbody.innerHTML = `<tr><td colspan="8" class="text-danger text-center py-3">${esc(e.message)}</td></tr>`;
    }
  }

  function updatePendingBadge(count) {
    const badge = document.getElementById('pendingRequestsBadge');
    if (!badge) return;
    if (count > 0) {
      badge.textContent = count;
      badge.classList.remove('d-none');
    } else {
      badge.classList.add('d-none');
    }
  }

  function renderPendingRequests(rows) {
    updatePendingBadge(rows.length);
    const tbody = document.getElementById('pendingRequestsBody');
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-muted text-center py-3">No pending requests.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(r => `
      <tr>
        <td><span class="badge bg-secondary">${r.change_type === 'new_type' ? 'New Type' : 'Price Edit'}</span></td>
        <td class="fw-semibold">${esc(r.fee_name)}</td>
        <td>${r.change_type === 'price_edit' ? '₱' + Number(r.default_amount).toFixed(2) : '—'}</td>
        <td>₱${Number(r.proposed_amount || r.default_amount).toFixed(2)}</td>
        <td class="small text-muted">${esc(r.notes || '—')}</td>
        <td class="small">${esc(r.requested_by_user_id || '—')}</td>
        <td class="small text-muted">${esc(r.updated_at || r.created_at || '')}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-success me-1 py-0 px-2" onclick="financeApproveFcr(${r.fee_type_id})">Approve</button>
          <button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="financeRejectFcr(${r.fee_type_id})">Reject</button>
        </td>
      </tr>`).join('');
  }

  window.financeApproveFcr = async function(id) {
    if (!confirm('Approve this fee change request? The catalog will be updated automatically.')) return;
    try {
      const fd = new FormData();
      fd.append('action', 'process_fee_change_request');
      fd.append('fee_type_id', id);
      fd.append('decision', 'approved');
      const res  = await fetch(API, { method: 'POST', body: fd });
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'Approve failed.');
      pendingLoaded = false;
      await loadPendingRequests();
    } catch (e) { alert(e.message); }
  };

  window.financeRejectFcr = async function(id) {
    const reviewNotes = prompt('Reason for rejection (optional):') ?? '';
    try {
      const fd = new FormData();
      fd.append('action', 'process_fee_change_request');
      fd.append('fee_type_id', id);
      fd.append('decision', 'rejected');
      fd.append('review_notes', reviewNotes);
      const res  = await fetch(API, { method: 'POST', body: fd });
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'Reject failed.');
      pendingLoaded = false;
      await loadPendingRequests();
    } catch (e) { alert(e.message); }
  };

  document.getElementById('btnRefreshPendingRequests').addEventListener('click', () => {
    pendingLoaded = false;
    loadPendingRequests();
  });
})();
</script>
<?php endif; ?>
</body>
</html>
