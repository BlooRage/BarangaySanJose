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

$allowUnregistered = false;
require_once __DIR__ . '/includes/resident_access_guard.php';

$residentUserId   = (string)($_SESSION['user_id'] ?? '');
$workflowEndpoint = $baseUrl . '/PhpFiles/Resident-End/documentRequestWorkflow.php';

function receipts_column_exists(mysqli $conn, string $table, string $column): bool
{
    $tableEsc  = $conn->real_escape_string($table);
    $columnEsc = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function receipts_format_datetime(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '-';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return $value;
    }
    return date('M j, Y g:i A', $ts);
}

$receiptItems = [];
$queryError   = '';

if (isset($conn) && $conn instanceof mysqli) {
    $hasInvoiceCol = receipts_column_exists($conn, 'documentrequesttbl', 'invoice_file_path');

    if ($hasInvoiceCol) {
        $documentTypeCol = receipts_column_exists($conn, 'documentrequesttbl', 'document_type')
            ? 'document_type' : "''";
        $stageCol        = receipts_column_exists($conn, 'documentrequesttbl', 'stage')
            ? 'stage' : "''";
        $amountCol       = receipts_column_exists($conn, 'documentrequesttbl', 'amount')
            ? 'amount' : 'NULL';
        $orNumberCol     = receipts_column_exists($conn, 'documentrequesttbl', 'or_number')
            ? 'or_number' : "''";
        $financeDecisionCol = receipts_column_exists($conn, 'documentrequesttbl', 'finance_decision_at')
            ? 'finance_decision_at'
            : (receipts_column_exists($conn, 'documentrequesttbl', 'payment_submitted_at')
                ? 'payment_submitted_at' : 'NULL');
        $reqTimestampCol = receipts_column_exists($conn, 'documentrequesttbl', 'request_timestamp')
            ? 'request_timestamp' : 'NULL';

        // Fetch finance_transaction amount when available for cleaner display.
        $sql = "
            SELECT
                d.request_id,
                {$documentTypeCol} AS document_type,
                COALESCE(f.transaction_amount, {$amountCol}) AS amount,
                COALESCE(f.or_number, {$orNumberCol}) AS or_number,
                COALESCE(f.payment_method, '') AS payment_method,
                {$financeDecisionCol} AS payment_date,
                {$reqTimestampCol} AS request_timestamp,
                d.invoice_file_path
            FROM documentrequesttbl d
            LEFT JOIN financetransactiontbl f ON f.request_id = d.request_id
            WHERE d.resident_user_id = ?
              AND d.invoice_file_path IS NOT NULL
              AND d.invoice_file_path <> ''
            ORDER BY COALESCE({$financeDecisionCol}, {$reqTimestampCol}) DESC, d.request_id DESC
        ";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('s', $residentUserId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $amountVal = isset($row['amount']) && is_numeric($row['amount'])
                    ? 'PHP ' . number_format((float)$row['amount'], 2) : '-';
                $methodRaw = trim((string)($row['payment_method'] ?? ''));
                $methodLabel = match (strtolower($methodRaw)) {
                    'barangay'            => 'Walk-in',
                    'gcash'               => 'GCash',
                    'online', 'e-payment' => 'Online',
                    default               => ucfirst($methodRaw !== '' ? $methodRaw : '-'),
                };
                $receiptItems[] = [
                    'request_id'   => (string)($row['request_id'] ?? ''),
                    'document_type' => trim((string)($row['document_type'] ?? 'Document Request')),
                    'amount'        => $amountVal,
                    'or_number'     => trim((string)($row['or_number'] ?? '-')),
                    'payment_method' => $methodLabel,
                    'payment_date'  => receipts_format_datetime((string)($row['payment_date'] ?? '')),
                    'invoice_path'  => trim((string)($row['invoice_file_path'] ?? '')),
                ];
            }
            $stmt->close();
        } else {
            $queryError = 'Unable to load official receipts right now.';
        }
    } else {
        $queryError = 'Official receipts are not available in this setup yet.';
    }
} else {
    $queryError = 'Database connection is unavailable.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Receipts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <style>
        body { background: #f8f9fb; }
        #mobile-header { display: none; }
        #div-mainDisplay { background: #f8f9fb !important; }
        .receipts-shell { width: 100%; }
        .txn-page-title {
            font-family: 'Charis SIL Bold', serif;
            color: #177245;
            font-size: clamp(2rem, 4.4vw, 3rem);
            line-height: 1.1;
            margin: 0 0 0.65rem 0;
        }
        .receipts-subtitle { color: #5f6b7a; max-width: 760px; }
        .receipts-card {
            background: #ffffff;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 1rem;
            box-shadow: 0 0.125rem 0.25rem rgba(15, 23, 42, 0.04);
        }
        .resident-masterlist-shell .table-responsive {
            overflow-x: auto;
            overflow-y: visible;
            -webkit-overflow-scrolling: touch;
        }
        .compact-admin-table-shell::-webkit-scrollbar { height: 8px; }
        .compact-admin-table-shell::-webkit-scrollbar-thumb {
            background: rgba(108, 117, 125, 0.45);
            border-radius: 999px;
        }
        .compact-admin-table-shell::-webkit-scrollbar-track { background: transparent; }
        .admin-list-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .admin-list-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-left: auto;
        }
        .receipts-summary {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(180deg, #ecfdf5 0%, #d1fae5 100%);
            color: #065f46;
            border: 1px solid rgba(34, 197, 94, 0.45);
            border-radius: 999px;
            padding: 8px 14px;
            font-weight: 700;
        }
        .receipts-summary .count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 28px;
            height: 28px;
            border-radius: 999px;
            background: #198754;
            color: #fff;
            padding: 0 8px;
            font-size: 0.85rem;
        }
        .compact-admin-table { width: 100%; }
        .compact-admin-table thead th {
            padding: 0.52rem 0.85rem;
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            background: #f7f8fa;
            border-bottom: 1px solid #e5e7eb;
            white-space: normal;
            line-height: 1.2;
        }
        .compact-admin-table tbody td {
            padding: 0.38rem 0.85rem;
            font-size: 0.94rem;
            color: #1f2937;
            border-bottom: 1px solid #eceff3;
            vertical-align: middle;
            background: #fff;
        }
        .compact-admin-table tbody tr:last-child td { border-bottom: 0; }
        .compact-admin-table tbody tr:hover td { background: #fcfcfd; }
        .doc-title { font-weight: 700; color: #111827; }
        .request-id { font-weight: 700; color: #065f46; }
        .or-number { font-weight: 700; color: #177245; font-family: monospace; }
        .amount-value { font-weight: 700; color: #177245; }
        .compact-table-btn {
            padding: 0.22rem 0.5rem;
            font-size: 0.76rem;
            line-height: 1.15;
        }
        .btn-invoice-dl {
            color: #fff;
            border-color: #198754;
            background: #198754;
            font-weight: 400 !important;
        }
        .btn-invoice-dl:hover {
            color: #fff;
            border-color: #157347;
            background: #157347;
        }
        .receipts-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            background: #eefaf2;
            color: #177245;
            border: 1px solid #cfead9;
            font-size: 0.83rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .receipts-table { table-layout: auto; min-width: 100%; }
        .receipts-table th, .receipts-table td { white-space: normal; }
        .receipts-table th:last-child, .receipts-table td:last-child { min-width: 120px; }
        #receiptCards { display: none; }
        .receipt-card {
            border: 1px solid #eceff3;
            border-radius: 12px;
            padding: 0.9rem;
            background: #fff;
            margin-bottom: 0.75rem;
        }
        .receipt-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .receipt-card-meta { display: grid; grid-template-columns: 1fr; gap: 0.5rem; }
        .receipt-card .txn-label {
            font-size: 0.78rem;
            color: #495057;
            text-transform: uppercase;
            letter-spacing: .02em;
            font-weight: 800;
        }
        .receipt-card .txn-value {
            font-size: 0.96rem;
            color: #212529;
            word-break: break-word;
            white-space: normal;
        }
        .empty-state { padding: 3rem 1.5rem; text-align: center; color: #6b7280; }
        .empty-state i { font-size: 2.2rem; color: #198754; margin-bottom: 0.9rem; }
        @media (max-width: 991.98px) {
            .admin-list-toolbar { flex-direction: column; align-items: stretch; }
            .admin-list-actions { margin-left: 0; justify-content: flex-start; }
        }
        @media (max-width: 767.98px) {
            #div-mainDisplay { padding: 1rem !important; }
            .receipts-subtitle { font-size: 0.95rem; }
            .receipts-card { padding: 0.9rem !important; }
            .table-responsive { display: none; }
            #receiptCards { display: block; margin-top: 0.25rem; }
            .txn-page-title { font-size: clamp(1.7rem, 7.5vw, 2.15rem); margin-bottom: 0.4rem; }
            .receipt-card-header { flex-direction: column; align-items: stretch; }
            .receipts-status-pill { align-self: flex-start; }
        }
        @media (max-width: 480px) {
            .receipts-table { min-width: 720px; }
            .receipts-table td, .receipts-table th { font-size: 0.875rem; }
            .receipts-summary { width: 100%; justify-content: space-between; }
        }
        @media (max-width: 1160px) {
            #mobile-header {
                display: block !important;
                position: fixed;
                top: 0; left: 0;
                width: 100%;
                z-index: 1030;
                height: auto !important;
                padding: 0 !important;
                margin: 0 !important;
                overflow: visible !important;
            }
            #mobile-header .d-flex { width: 100%; }
            #div-mainDisplay { margin-left: 0 !important; width: 100%; padding-top: 1rem !important; }
            body { padding-top: 64px; }
            #div-sidebarWrapper {
                position: fixed !important;
                top: 0; left: 0;
                height: 100vh !important;
                width: 280px;
                z-index: 1060;
                transform: translateX(-100%);
                transition: transform 0.28s ease;
                box-shadow: 0 0 0 9999px rgba(0,0,0,0);
            }
            #div-sidebarWrapper.show {
                transform: translateX(0);
                box-shadow: 0 0 0 9999px rgba(0,0,0,0.25);
            }
        }
        @media (min-width: 1161px) {
            body { padding-top: 0; }
            #mobile-header { display: none !important; }
            #div-sidebarWrapper { transform: none !important; }
        }
    </style>
</head>
<body>
<div class="d-flex min-vh-100">
    <?php include __DIR__ . '/includes/resident_sidebar.php'; ?>

    <header id="mobile-header">
        <div class="d-flex align-items-center px-3 py-2 shadow-sm bg-white">
            <button class="btn" id="btn-burger" type="button" aria-label="Open sidebar">
                <i class="fa-solid fa-bars fa-lg"></i>
            </button>
            <img src="<?= htmlspecialchars($baseUrl) ?>/Images/San_Jose_LOGO.jpg" alt="Logo" style="width:32px;height:32px">
            <span class="logo-name">Barangay San Jose</span>
        </div>
    </header>

    <main id="div-mainDisplay" class="flex-grow-1 p-4 p-md-5">
        <div class="receipts-shell">
            <h1 class="txn-page-title">Official Receipts</h1>
            <hr class="mt-0 mb-3">
            <p class="receipts-subtitle mb-4">
                Download your official payment receipts here. Receipts are generated automatically once your payment is verified by the barangay finance office.
            </p>

            <section class="receipts-card p-4 resident-masterlist-shell">
                <?php if ($queryError !== ''): ?>
                    <div class="alert alert-warning mb-0"><?= htmlspecialchars($queryError) ?></div>
                <?php elseif (!$receiptItems): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-receipt"></i>
                        <h2 class="h5 mb-2">No official receipts yet</h2>
                        <p class="mb-0">Once your payment is verified by the finance office, your official receipt will appear here for download.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-list-toolbar mb-3">
                        <div>
                            <h2 class="h5 mb-1 fw-bold">Payment Receipts</h2>
                            <p class="text-muted small mb-0">All verified payment receipts for your document requests.</p>
                        </div>
                        <div class="admin-list-actions">
                            <div class="receipts-summary">
                                <span>Receipts</span>
                                <span class="count"><?= count($receiptItems) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive compact-admin-table-shell">
                        <table class="table align-middle mb-0 compact-admin-table receipts-table">
                            <thead>
                                <tr class="table-light">
                                    <th>Request ID</th>
                                    <th>Document</th>
                                    <th>Amount</th>
                                    <th>OR No.</th>
                                    <th>Payment Method</th>
                                    <th>Date Verified</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($receiptItems as $item): ?>
                                    <?php $requestId = (string)$item['request_id']; ?>
                                    <tr>
                                        <td class="request-id"><?= htmlspecialchars($requestId) ?></td>
                                        <td><div class="doc-title"><?= htmlspecialchars((string)$item['document_type']) ?></div></td>
                                        <td class="amount-value"><?= htmlspecialchars((string)$item['amount']) ?></td>
                                        <td class="or-number"><?= htmlspecialchars((string)$item['or_number']) ?></td>
                                        <td><?= htmlspecialchars((string)$item['payment_method']) ?></td>
                                        <td><?= htmlspecialchars((string)$item['payment_date']) ?></td>
                                        <td><span class="receipts-status-pill"><i class="fa-solid fa-circle-check"></i> Verified</span></td>
                                        <td>
                                            <a class="btn btn-sm compact-table-btn btn-invoice-dl"
                                               href="<?= htmlspecialchars($workflowEndpoint . '?action=download_invoice&request_id=' . rawurlencode($requestId)) ?>">
                                                <i class="fa-solid fa-receipt me-1"></i>Download
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="receiptCards" class="mt-2">
                        <?php foreach ($receiptItems as $item): ?>
                            <?php $requestId = (string)$item['request_id']; ?>
                            <article class="receipt-card">
                                <div class="receipt-card-header">
                                    <div>
                                        <div class="doc-title"><?= htmlspecialchars((string)$item['document_type']) ?></div>
                                        <div class="request-id mt-1"><?= htmlspecialchars($requestId) ?></div>
                                    </div>
                                    <span class="receipts-status-pill"><i class="fa-solid fa-circle-check"></i> Verified</span>
                                </div>
                                <div class="receipt-card-meta">
                                    <div>
                                        <div class="txn-label">Amount</div>
                                        <div class="txn-value amount-value"><?= htmlspecialchars((string)$item['amount']) ?></div>
                                    </div>
                                    <div>
                                        <div class="txn-label">OR No.</div>
                                        <div class="txn-value or-number"><?= htmlspecialchars((string)$item['or_number']) ?></div>
                                    </div>
                                    <div>
                                        <div class="txn-label">Payment Method</div>
                                        <div class="txn-value"><?= htmlspecialchars((string)$item['payment_method']) ?></div>
                                    </div>
                                    <div>
                                        <div class="txn-label">Date Verified</div>
                                        <div class="txn-value"><?= htmlspecialchars((string)$item['payment_date']) ?></div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <a class="btn btn-sm compact-table-btn btn-invoice-dl w-100"
                                       href="<?= htmlspecialchars($workflowEndpoint . '?action=download_invoice&request_id=' . rawurlencode($requestId)) ?>">
                                        <i class="fa-solid fa-receipt me-1"></i>Download Receipt
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= htmlspecialchars($baseUrl) ?>/JS-Script-Files/Resident-End/profileSidebar.js"></script>
</body>
</html>
