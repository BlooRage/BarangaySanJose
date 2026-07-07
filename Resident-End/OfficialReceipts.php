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
require_once __DIR__ . '/../PhpFiles/General/documentRequestWorkflow.php';

$residentUserId   = (string)($_SESSION['user_id'] ?? '');
$workflowEndpoint = $baseUrl . '/PhpFiles/Resident-End/documentRequestWorkflow.php';

function receipts_table_exists(mysqli $conn, string $table): bool
{
    static $cache = [];
    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($tableSafe === '') {
        return false;
    }

    $cacheKey = strtolower($tableSafe);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $tableEsc = $conn->real_escape_string($tableSafe);
    $res = $conn->query("SHOW TABLES LIKE '{$tableEsc}'");
    $cache[$cacheKey] = $res instanceof mysqli_result && $res->num_rows > 0;
    return $cache[$cacheKey];
}

function receipts_column_exists(mysqli $conn, string $table, string $column): bool
{
    static $cache = [];
    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($tableSafe === '') {
        return false;
    }

    $cacheKey = strtolower($tableSafe . '|' . $column);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $columnEsc = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$tableSafe}` LIKE '{$columnEsc}'");
    $cache[$cacheKey] = $res instanceof mysqli_result && $res->num_rows > 0;
    return $cache[$cacheKey];
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

function receipts_format_amount($value): string
{
    if (!is_numeric((string)$value)) {
        return '-';
    }
    return 'PHP ' . number_format((float)$value, 2);
}

function receipts_format_payment_method(?string $value): string
{
    $methodRaw = strtolower(trim((string)$value));
    return match ($methodRaw) {
        'barangay', 'walkin', 'walk-in' => 'Walk-in',
        'gcash' => 'GCash',
        'cash' => 'Cash',
        'online', 'e-payment', 'epayment' => 'Online',
        default => $methodRaw !== '' ? ucwords(str_replace(['_', '-'], ' ', $methodRaw)) : '-',
    };
}

function receipts_resolve_details(string $documentType, ?string $requestDetailsJson = null): string
{
    $details = trim($documentType);
    if ($details === '' && $requestDetailsJson !== null && trim($requestDetailsJson) !== '') {
        $payload = json_decode($requestDetailsJson, true);
        if (is_array($payload)) {
            $details = trim((string)($payload['document_type'] ?? $payload['certificate_type'] ?? ''));
            if ($details === '' && trim((string)($payload['business_name'] ?? '')) !== '') {
                $details = 'Clearance for Barangay Permit';
            }
        }
    }

    if ($details === '') {
        return 'Document Payment';
    }

    $normalized = strtolower(preg_replace('/\s+/', ' ', $details) ?? $details);
    if ($normalized === 'barangay business clearance' || str_contains($normalized, 'business permit')) {
        return 'Clearance for Barangay Permit';
    }

    return $details;
}

function receipts_decode_request_details(array $row): array
{
    if (function_exists('dr_decode_request_payload')) {
        return dr_decode_request_payload($row);
    }

    $payload = json_decode((string)($row['request_details'] ?? '{}'), true);
    return is_array($payload) ? $payload : [];
}

function receipts_build_view_url(string $workflowEndpoint, string $requestId): string
{
    return $workflowEndpoint
        . '?action=download_invoice&request_id=' . rawurlencode($requestId)
        . '&disposition=inline';
}

function receipts_build_download_url(string $workflowEndpoint, string $requestId): string
{
    return $workflowEndpoint
        . '?action=download_invoice&request_id=' . rawurlencode($requestId);
}

$receiptItems = [];
$queryError   = '';

if (isset($conn) && $conn instanceof mysqli) {
    $hasDocumentTable = receipts_table_exists($conn, 'documentrequesttbl');
    $hasFinanceTable = receipts_table_exists($conn, 'financetransactiontbl');

    if ($hasDocumentTable && $hasFinanceTable) {
        $documentTypeCol = receipts_column_exists($conn, 'documentrequesttbl', 'document_type')
            ? 'd.document_type' : "''";
        $requestDetailsCol = receipts_column_exists($conn, 'documentrequesttbl', 'request_details')
            ? 'd.request_details' : "'{}'";
        $requestTimestampCol = receipts_column_exists($conn, 'documentrequesttbl', 'request_timestamp')
            ? 'd.request_timestamp'
            : (receipts_column_exists($conn, 'documentrequesttbl', 'created_at') ? 'd.created_at' : 'NULL');
        $invoiceFileCol = receipts_column_exists($conn, 'documentrequesttbl', 'invoice_file_path')
            ? 'd.invoice_file_path' : "''";
        $paymentIdCol = receipts_column_exists($conn, 'financetransactiontbl', 'transaction_id')
            ? 'f.transaction_id' : "''";
        $amountCol = receipts_column_exists($conn, 'financetransactiontbl', 'transaction_amount')
            ? 'f.transaction_amount' : 'NULL';
        $orNumberCol = receipts_column_exists($conn, 'financetransactiontbl', 'or_number')
            ? 'f.or_number' : "''";
        $paymentMethodCol = receipts_column_exists($conn, 'financetransactiontbl', 'payment_method')
            ? 'f.payment_method' : "''";
        $paymentTimestampCol = receipts_column_exists($conn, 'financetransactiontbl', 'payment_timestamp')
            ? 'f.payment_timestamp' : 'NULL';
        $financeDecisionCol = receipts_column_exists($conn, 'financetransactiontbl', 'finance_decision_at')
            ? 'f.finance_decision_at' : 'NULL';
        $verifiedFilter = receipts_column_exists($conn, 'financetransactiontbl', 'or_number')
            ? "COALESCE(f.or_number, '') <> ''"
            : '0 = 1';
        $invoiceFilter = receipts_column_exists($conn, 'documentrequesttbl', 'invoice_file_path')
            ? "COALESCE(d.invoice_file_path, '') <> ''"
            : '0 = 1';

        $sql = "
            SELECT
                {$paymentIdCol} AS payment_id,
                d.request_id,
                {$documentTypeCol} AS document_type,
                {$requestDetailsCol} AS request_details,
                {$amountCol} AS amount,
                {$orNumberCol} AS or_number,
                {$paymentMethodCol} AS payment_method,
                COALESCE({$financeDecisionCol}, {$paymentTimestampCol}, {$requestTimestampCol}) AS transaction_timestamp,
                {$invoiceFileCol} AS invoice_file_path
            FROM documentrequesttbl d
            INNER JOIN financetransactiontbl f ON f.request_id = d.request_id
            WHERE d.resident_user_id = ?
              AND ({$verifiedFilter} OR {$invoiceFilter})
            ORDER BY transaction_timestamp DESC, payment_id DESC
        ";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('s', $residentUserId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $decodedDetails = receipts_decode_request_details($row);
                $requestId = trim((string)($row['request_id'] ?? ''));
                $paymentId = trim((string)($row['payment_id'] ?? ''));
                $invoicePath = trim((string)($row['invoice_file_path'] ?? ''));
                $orNumber = trim((string)($row['or_number'] ?? ''));
                $canViewReceipt = $requestId !== '' && ($orNumber !== '' || $invoicePath !== '');

                if ($paymentId === '') {
                    $paymentId = $requestId !== '' ? $requestId : '-';
                }

                $receiptItems[] = [
                    'payment_id' => $paymentId,
                    'request_id' => $requestId,
                    'details' => receipts_resolve_details(
                        (string)($row['document_type'] ?? ''),
                        $decodedDetails ? json_encode($decodedDetails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)($row['request_details'] ?? '')
                    ),
                    'price' => receipts_format_amount($row['amount'] ?? null),
                    'timestamp' => receipts_format_datetime((string)($row['transaction_timestamp'] ?? '')),
                    'timestamp_raw' => (string)($row['transaction_timestamp'] ?? ''),
                    'payment_method' => receipts_format_payment_method((string)($row['payment_method'] ?? '')),
                    'payment_method_group' => strtolower(str_replace(' ', '-', receipts_format_payment_method((string)($row['payment_method'] ?? '')))),
                    'receipt_url' => $canViewReceipt ? receipts_build_view_url($workflowEndpoint, $requestId) : '',
                    'download_url' => $canViewReceipt ? receipts_build_download_url($workflowEndpoint, $requestId) : '',
                ];
            }
            $stmt->close();
        } else {
            $queryError = 'Unable to load finance transactions right now.';
        }
    } else {
        $queryError = 'Finance transaction records are not available in this setup yet.';
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
    <link rel="icon" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Images/favicon_sanjose.png?v=20260211">
    <title>Official Receipts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <style>
        :root {
            --receipts-hci-red: #d92d20;
            --receipts-hci-red-dark: #b42318;
            --receipts-hci-red-ring: rgba(217, 45, 32, 0.2);
        }
        body { background: #f8f9fb; }
        #mobile-header { display: none; }
        #div-mainDisplay { background: #f8f9fb !important; }
        .receipts-shell { width: 100%; }
        .txn-page-title {
            font-family: 'Charis SIL Bold', serif;
            color: #de710c;
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
            background: linear-gradient(180deg, #fff8ef 0%, #ffe7c8 100%);
            color: #8d4f13;
            border: 1px solid rgba(243, 165, 83, 0.48);
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
            background: #f58220;
            color: #fff;
            padding: 0 8px;
            font-size: 0.85rem;
        }
        .compact-admin-table { width: 100%; }
        .compact-admin-table thead th {
            padding: 0.7rem 0.95rem;
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            background: #f7f8fa;
            border-bottom: 1px solid #e5e7eb;
            white-space: normal;
            line-height: 1.2;
        }
        .compact-admin-table tbody td {
            padding: 0.78rem 0.95rem;
            font-size: 0.94rem;
            color: #1f2937;
            border-bottom: 1px solid #eceff3;
            vertical-align: middle;
            background: #fff;
        }
        .compact-admin-table tbody tr:last-child td { border-bottom: 0; }
        .compact-admin-table tbody tr:hover td { background: #fcfcfd; }
        .compact-admin-table .compact-table-actions {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            flex-wrap: nowrap;
        }
        .details-value { font-weight: 700; color: #111827; }
        .payment-id {
            font-weight: 700;
            color: #a85a12;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
        }
        .amount-value { font-weight: 700; color: #c66a10; }
        .timestamp-value { color: #334155; }
        .compact-table-btn {
            padding: 0.42rem 0.78rem;
            font-size: 0.82rem;
            line-height: 1.15;
        }
        .btn-receipt-view,
        .btn-receipt-download {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.38rem;
            min-height: 34px;
            border-radius: 0.75rem;
            white-space: nowrap;
            font-weight: 700 !important;
            letter-spacing: 0.01em;
        }
        .btn-receipt-view {
            color: var(--receipts-hci-red) !important;
            border: 1px solid rgba(217, 45, 32, 0.28) !important;
            background: #fff5f4 !important;
        }
        .btn-receipt-view:hover {
            color: var(--receipts-hci-red-dark) !important;
            border-color: rgba(180, 35, 24, 0.32) !important;
            background: #ffe7e5 !important;
        }
        .btn-receipt-download {
            color: #fff !important;
            border: 1px solid var(--receipts-hci-red) !important;
            background: var(--receipts-hci-red) !important;
        }
        .btn-receipt-download:hover {
            color: #fff !important;
            border-color: var(--receipts-hci-red-dark) !important;
            background: var(--receipts-hci-red-dark) !important;
        }
        .btn-receipt-view:focus-visible,
        .btn-receipt-download:focus-visible,
        .btn-receipt-open:focus-visible {
            box-shadow: 0 0 0 0.2rem var(--receipts-hci-red-ring);
        }
        .receipts-table { table-layout: auto; min-width: 100%; }
        .receipts-table th, .receipts-table td { white-space: normal; }
        .receipts-table th:last-child, .receipts-table td:last-child {
            width: 1%;
            min-width: 300px;
            white-space: nowrap;
            text-align: center;
        }
        #receiptCards { display: none; }
        .receipt-card {
            border: 1px solid #eceff3;
            border-radius: 14px;
            padding: 0.95rem;
            background: #fff;
            margin-bottom: 0.75rem;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
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
        .empty-state i { font-size: 2.2rem; color: #f58220; margin-bottom: 0.9rem; }
        #receiptViewerModal .modal-dialog {
            max-width: 1100px;
            width: min(92vw, 1100px);
        }
        #receiptViewerModal .modal-content {
            border: 0;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18);
        }
        #receiptViewerModal .modal-header {
            border-bottom: 1px solid #e9ecef;
            padding: 1rem 1.25rem;
        }
        #receiptViewerModal .modal-body {
            padding: 1rem 1.25rem 1.15rem;
            background: #f8fafc;
        }
        #receiptViewerModal .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 0.9rem 1.25rem 1rem;
        }
        .receipt-modal-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.75rem;
            width: 100%;
            margin: 0;
        }
        .receipt-modal-btn {
            min-height: 46px;
            border-radius: 14px;
            padding: 10px 18px;
            font-weight: 700;
            min-width: 220px;
            box-shadow: none;
            transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease;
        }
        .receipt-modal-btn:hover,
        .receipt-modal-btn:focus {
            transform: none;
            box-shadow: none;
        }
        .btn-receipt-open {
            color: var(--receipts-hci-red);
            background: #fff5f4;
            border: 1px solid rgba(217, 45, 32, 0.28);
        }
        .btn-receipt-open:hover,
        .btn-receipt-open:focus {
            color: var(--receipts-hci-red-dark);
            background: #ffe7e5;
            border-color: rgba(180, 35, 24, 0.32);
            box-shadow: 0 0 0 0.2rem var(--receipts-hci-red-ring);
        }
        .btn-receipt-modal-download {
            color: #fff;
            background: var(--receipts-hci-red);
            border: 1px solid var(--receipts-hci-red);
        }
        .btn-receipt-modal-download:hover,
        .btn-receipt-modal-download:focus {
            color: #fff;
            background: var(--receipts-hci-red-dark);
            border-color: var(--receipts-hci-red-dark);
            box-shadow: 0 0 0 0.2rem var(--receipts-hci-red-ring);
        }
        .btn-receipt-close {
            background: #ffffff;
            color: #344054;
            border: 1px solid #d0d5dd;
        }
        .btn-receipt-close:hover,
        .btn-receipt-close:focus {
            background: #f8fafc;
            color: #1f2937;
            border-color: #cbd5e1;
        }
        .receipt-preview-frame {
            width: 100%;
            height: 70vh;
            border: 1px solid #d9e0e7;
            border-radius: 0.85rem;
            background: #ffffff;
        }
        .receipt-preview-empty {
            min-height: 14rem;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #6b7280;
            background: #ffffff;
            border: 1px dashed #d9e0e7;
            border-radius: 0.85rem;
            padding: 1.25rem;
        }
        @media (max-width: 991.98px) {
            .admin-list-toolbar { flex-direction: column; align-items: stretch; }
            .admin-list-actions { margin-left: 0; justify-content: flex-start; }
        }
        @media (max-width: 767.98px) {
            #div-mainDisplay { padding: 1rem !important; }
            .receipts-subtitle { font-size: 0.95rem; }
            .receipts-card { padding: 0.9rem !important; }
            .admin-list-actions {
                width: 100%;
                gap: 0.6rem;
            }
            .tracker-table-responsive { display: none; }
            #receiptCards { display: block; margin-top: 0.25rem; }
            .txn-page-title { font-size: clamp(1.7rem, 7.5vw, 2.15rem); margin-bottom: 0.4rem; }
            .receipt-card-header { flex-direction: column; align-items: stretch; }
            .receipt-preview-frame { height: 62vh; }
            #receiptViewerModal .modal-footer {
                padding-top: 0.85rem;
            }
            .btn-receipt-view {
                width: 100%;
            }
            .btn-receipt-download {
                width: 100%;
            }
            .receipt-modal-actions {
                flex-direction: column-reverse;
                align-items: stretch;
            }
            .receipt-modal-btn {
                width: 100%;
                min-width: 0;
            }
        }
        @media (max-width: 480px) {
            .receipts-summary { width: 100%; justify-content: space-between; }
            .admin-list-actions {
                flex-direction: column;
                align-items: stretch;
            }
            .receipt-card {
                padding: 0.8rem;
            }
            .receipt-card-header {
                gap: 0.55rem;
            }
            #receiptViewerModal .modal-header,
            #receiptViewerModal .modal-body,
            #receiptViewerModal .modal-footer {
                padding-left: 0.9rem;
                padding-right: 0.9rem;
            }
            .receipt-preview-frame {
                height: 56vh;
            }
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
                Review your verified payments here and open the same official receipt PDF that was sent to your email after finance verification.
            </p>

            <section class="receipts-card p-4 resident-masterlist-shell">
                <?php if ($queryError !== ''): ?>
                    <div class="alert alert-warning mb-0"><?= htmlspecialchars($queryError) ?></div>
                <?php elseif (!$receiptItems): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-receipt"></i>
                        <h2 class="h5 mb-2">No finance transactions yet</h2>
                        <p class="mb-0">Once your payment is verified by the finance office, it will appear here together with its official receipt PDF.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-list-toolbar mb-3 pt-2 flex-wrap">
                        <div class="admin-list-tabs">
                            <button type="button" class="btn btn-outline-primary btn-sm status-filter-btn receipt-tab active" data-tab="all" data-filter="ALL">All</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn receipt-tab" data-tab="walk-in" data-filter="Walk-in">Walk-in</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn receipt-tab" data-tab="gcash" data-filter="GCash">GCash</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn receipt-tab" data-tab="cash" data-filter="Cash">Cash</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn receipt-tab" data-tab="online" data-filter="Online">Online</button>
                        </div>
                        <div class="admin-list-actions">
                            <div class="input-group admin-search">
                                <input id="receiptSearch" class="form-control" placeholder="Search receipts..." />
                                <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                            </div>
                            <button id="receiptFilterBtn" class="btn btn-outline-secondary btn-icon admin-filter" type="button" title="Filter" aria-label="Filter" data-bs-toggle="modal" data-bs-target="#receiptFilterModal">
                                <i class="fas fa-filter"></i>
                            </button>
                            <button id="receiptColumnsBtn" class="btn btn-outline-secondary btn-icon admin-columns" type="button" title="Columns" aria-label="Columns" data-bs-toggle="modal" data-bs-target="#receiptColumnsModal">
                                <i class="fa-solid fa-sliders"></i>
                            </button>
                            <button id="receiptRefreshBtn" class="btn btn-outline-secondary btn-icon admin-refresh" type="button" title="Refresh receipts" aria-label="Refresh receipts">
                                <i class="fa-solid fa-arrows-rotate"></i>
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive compact-admin-table-shell tracker-table-responsive">
                        <table id="receiptTable" class="table align-middle mb-0 compact-admin-table receipts-table">
                            <thead>
                                <tr class="table-light">
                                    <th>ID</th>
                                    <th>Details</th>
                                    <th>Price</th>
                                    <th>Time Stamp</th>
                                    <th>Payment Method</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="receiptTbody">
                                <tr><td colspan="6" class="text-center text-muted py-4">Loading receipts...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="resident-table-footer mt-3 d-flex flex-wrap justify-content-between align-items-center gap-3 tracker-table-responsive">
                        <div class="d-flex align-items-center gap-2">
                            <label for="receiptEntriesPerPageInput" class="small text-muted mb-0">Entries</label>
                            <input id="receiptEntriesPerPageInput" type="number" min="1" step="1" value="20" class="form-control form-control-sm resident-entries-input" />
                        </div>
                        <nav aria-label="Official receipt pagination">
                            <ul class="pagination pagination-sm mb-0" id="receiptPagination"></ul>
                        </nav>
                    </div>

                    <div id="receiptCards" class="mt-2">
                        <div class="text-center text-muted py-4">Loading receipts...</div>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>

<?php if (!$queryError && $receiptItems): ?>
<div class="modal fade" id="receiptFilterModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Filter Receipts</h5>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small text-muted mb-1">Payment Method</label>
                        <select class="form-select" id="receiptMethodFilter">
                            <option value="">All Methods</option>
                            <option value="walk-in">Walk-in</option>
                            <option value="gcash">GCash</option>
                            <option value="cash">Cash</option>
                            <option value="online">Online</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-muted mb-1">Transaction Date From</label>
                        <input type="date" class="form-control" id="receiptDateFrom" />
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-muted mb-1">Transaction Date To</label>
                        <input type="date" class="form-control" id="receiptDateTo" />
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="receiptFilterReset">Reset</button>
                <button type="button" class="btn btn-primary" id="receiptFilterApply" data-bs-dismiss="modal">Apply</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="receiptColumnsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Columns</h5>
            </div>
            <div class="modal-body">
                <div class="row g-2" id="receiptColumnsList"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="receiptColumnsReset">Reset</button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="receiptViewerModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="fw-bold mb-0" id="receiptViewerTitle">Official Receipt</h5>
                    <div class="small text-muted" id="receiptViewerSubtitle"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="receiptViewerEmpty" class="receipt-preview-empty d-none">
                    Receipt preview is unavailable right now.
                </div>
                <iframe id="receiptViewerFrame" class="receipt-preview-frame d-none" title="Official Receipt Preview"></iframe>
            </div>
            <div class="modal-footer">
                <div class="receipt-modal-actions">
                    <button type="button" class="btn btn-receipt-close receipt-modal-btn" data-bs-dismiss="modal">Close</button>
                    <a href="#" class="btn btn-receipt-open receipt-modal-btn d-none" id="receiptViewerOpenNewTab" target="_blank" rel="noopener">
                        Open in new tab
                    </a>
                    <a href="#" class="btn btn-receipt-modal-download receipt-modal-btn d-none" id="receiptViewerDownload">
                        Download
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= htmlspecialchars($baseUrl) ?>/JS-Script-Files/Resident-End/profileSidebar.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const receiptItems = <?= json_encode($receiptItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        let receiptActiveTab = 'all';
        let receiptCurrentPage = 1;
        let receiptEntriesPerPage = 20;

        const modalEl = document.getElementById('receiptViewerModal');
        const frameEl = document.getElementById('receiptViewerFrame');
        const emptyEl = document.getElementById('receiptViewerEmpty');
        const titleEl = document.getElementById('receiptViewerTitle');
        const subtitleEl = document.getElementById('receiptViewerSubtitle');
        const openNewTabEl = document.getElementById('receiptViewerOpenNewTab');
        const downloadEl = document.getElementById('receiptViewerDownload');

        if (!modalEl || !window.bootstrap?.Modal) {
            return;
        }

        const receiptModal = new bootstrap.Modal(modalEl);

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');

        const dateOnly = (value) => String(value || '').trim().slice(0, 10);

        const paginateRows = (rows, currentPage, perPage) => {
            const safePerPage = Math.max(1, Number.parseInt(perPage, 10) || 20);
            const totalPages = Math.max(1, Math.ceil(rows.length / safePerPage));
            const page = Math.min(Math.max(1, currentPage), totalPages);
            const start = (page - 1) * safePerPage;
            return {
                page,
                totalPages,
                items: rows.slice(start, start + safePerPage),
            };
        };

        const renderReceiptPagination = (totalPages) => {
            const pagination = document.getElementById('receiptPagination');
            if (!pagination) {
                return;
            }
            if (totalPages <= 1) {
                pagination.innerHTML = '';
                return;
            }

            const items = [];
            items.push(`
                <li class="page-item ${receiptCurrentPage <= 1 ? 'disabled' : ''}">
                    <button type="button" class="page-link" data-page="${receiptCurrentPage - 1}">Prev</button>
                </li>
            `);
            for (let page = 1; page <= totalPages; page += 1) {
                items.push(`
                    <li class="page-item ${page === receiptCurrentPage ? 'active' : ''}">
                        <button type="button" class="page-link" data-page="${page}">${page}</button>
                    </li>
                `);
            }
            items.push(`
                <li class="page-item ${receiptCurrentPage >= totalPages ? 'disabled' : ''}">
                    <button type="button" class="page-link" data-page="${receiptCurrentPage + 1}">Next</button>
                </li>
            `);
            pagination.innerHTML = items.join('');
            pagination.querySelectorAll('button[data-page]').forEach((button) => {
                button.addEventListener('click', () => {
                    if (button.closest('.page-item')?.classList.contains('disabled')) {
                        return;
                    }
                    receiptCurrentPage = Number.parseInt(button.getAttribute('data-page') || '1', 10) || 1;
                    renderReceipts();
                });
            });
        };

        const getFilteredReceipts = () => {
            const search = String(document.getElementById('receiptSearch')?.value || '').toLowerCase().trim();
            const method = String(document.getElementById('receiptMethodFilter')?.value || '').trim();
            const dateFrom = String(document.getElementById('receiptDateFrom')?.value || '').trim();
            const dateTo = String(document.getElementById('receiptDateTo')?.value || '').trim();

            return receiptItems.filter((item) => {
                const methodGroup = String(item.payment_method_group || '').trim();
                if (receiptActiveTab !== 'all' && methodGroup !== receiptActiveTab) {
                    return false;
                }
                if (method && methodGroup !== method) {
                    return false;
                }

                const sourceDate = dateOnly(item.timestamp_raw || '');
                if (dateFrom && sourceDate && sourceDate < dateFrom) {
                    return false;
                }
                if (dateTo && sourceDate && sourceDate > dateTo) {
                    return false;
                }

                if (search) {
                    const haystack = [
                        item.payment_id,
                        item.details,
                        item.price,
                        item.timestamp,
                        item.payment_method,
                    ].join(' ').toLowerCase();
                    if (!haystack.includes(search)) {
                        return false;
                    }
                }

                return true;
            });
        };

        const resetReceiptViewer = () => {
            if (frameEl) {
                frameEl.src = 'about:blank';
                frameEl.classList.add('d-none');
            }
            if (emptyEl) {
                emptyEl.classList.add('d-none');
            }
            if (openNewTabEl) {
                openNewTabEl.href = '#';
                openNewTabEl.classList.add('d-none');
            }
            if (downloadEl) {
                downloadEl.href = '#';
                downloadEl.classList.add('d-none');
            }
            if (titleEl) {
                titleEl.textContent = 'Official Receipt';
            }
            if (subtitleEl) {
                subtitleEl.textContent = '';
            }
        };

        const openReceiptViewer = ({ url, downloadUrl, title, paymentId }) => {
            resetReceiptViewer();

            if (titleEl) {
                titleEl.textContent = title || 'Official Receipt';
            }
            if (subtitleEl) {
                subtitleEl.textContent = paymentId ? `Transaction ID: ${paymentId}` : '';
            }

            if (url && frameEl) {
                frameEl.src = url;
                frameEl.classList.remove('d-none');
                if (openNewTabEl) {
                    openNewTabEl.href = url;
                    openNewTabEl.classList.remove('d-none');
                }
                if (downloadEl && downloadUrl) {
                    downloadEl.href = downloadUrl;
                    downloadEl.classList.remove('d-none');
                }
            } else if (emptyEl) {
                emptyEl.classList.remove('d-none');
            }

            receiptModal.show();
        };

        const bindReceiptViewButtons = () => {
            document.querySelectorAll('.js-view-receipt').forEach((trigger) => {
                trigger.addEventListener('click', () => {
                    openReceiptViewer({
                        url: String(trigger.dataset.receiptUrl || '').trim(),
                        downloadUrl: String(trigger.dataset.receiptDownloadUrl || '').trim(),
                        title: String(trigger.dataset.receiptTitle || '').trim(),
                        paymentId: String(trigger.dataset.receiptId || '').trim(),
                    });
                });
            });
        };

        const renderReceipts = () => {
            const tbody = document.getElementById('receiptTbody');
            const cards = document.getElementById('receiptCards');
            if (!tbody || !cards) {
                return;
            }

            const rows = getFilteredReceipts();
            const paged = paginateRows(rows, receiptCurrentPage, receiptEntriesPerPage);
            receiptCurrentPage = paged.page;

            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No receipts found.</td></tr>';
                cards.innerHTML = '<div class="text-center text-muted py-4">No receipts found.</div>';
                renderReceiptPagination(1);
                return;
            }

            tbody.innerHTML = paged.items.map((item) => {
                const actionHtml = item.receipt_url
                    ? `
                        <span class="compact-table-actions">
                            <button type="button"
                                    class="btn btn-sm compact-table-btn btn-receipt-view js-view-receipt"
                                    data-receipt-url="${escapeHtml(item.receipt_url || '')}"
                                    data-receipt-download-url="${escapeHtml(item.download_url || '')}"
                                    data-receipt-title="${escapeHtml(item.details || '')}"
                                    data-receipt-id="${escapeHtml(item.payment_id || '')}">
                                <i class="fa-solid fa-file-pdf"></i><span>View Receipt</span>
                            </button>
                            <a href="${escapeHtml(item.download_url || '#')}" class="btn btn-sm compact-table-btn btn-receipt-download">
                                <i class="fa-solid fa-download"></i><span>Download</span>
                            </a>
                        </span>
                    `
                    : '<span class="text-muted small">Unavailable</span>';
                return `
                    <tr>
                        <td class="payment-id">${escapeHtml(item.payment_id || '-')}</td>
                        <td><div class="details-value">${escapeHtml(item.details || '-')}</div></td>
                        <td class="amount-value">${escapeHtml(item.price || '-')}</td>
                        <td class="timestamp-value">${escapeHtml(item.timestamp || '-')}</td>
                        <td>${escapeHtml(item.payment_method || '-')}</td>
                        <td>${actionHtml}</td>
                    </tr>
                `;
            }).join('');

            cards.innerHTML = paged.items.map((item) => {
                const actionHtml = item.receipt_url
                    ? `
                        <button type="button"
                                class="btn btn-sm compact-table-btn btn-receipt-view w-100 js-view-receipt"
                                data-receipt-url="${escapeHtml(item.receipt_url || '')}"
                                data-receipt-download-url="${escapeHtml(item.download_url || '')}"
                                data-receipt-title="${escapeHtml(item.details || '')}"
                                data-receipt-id="${escapeHtml(item.payment_id || '')}">
                            <i class="fa-solid fa-file-pdf"></i><span>View Receipt</span>
                        </button>
                        <a href="${escapeHtml(item.download_url || '#')}" class="btn btn-sm compact-table-btn btn-receipt-download w-100">
                            <i class="fa-solid fa-download"></i><span>Download</span>
                        </a>
                    `
                    : '<span class="text-muted small">Receipt unavailable.</span>';
                return `
                    <article class="receipt-card">
                        <div class="receipt-card-header">
                            <div>
                                <div class="details-value">${escapeHtml(item.details || '-')}</div>
                                <div class="payment-id mt-1">${escapeHtml(item.payment_id || '-')}</div>
                            </div>
                        </div>
                        <div class="receipt-card-meta">
                            <div>
                                <div class="txn-label">Price</div>
                                <div class="txn-value amount-value">${escapeHtml(item.price || '-')}</div>
                            </div>
                            <div>
                                <div class="txn-label">Time Stamp</div>
                                <div class="txn-value timestamp-value">${escapeHtml(item.timestamp || '-')}</div>
                            </div>
                            <div>
                                <div class="txn-label">Payment Method</div>
                                <div class="txn-value">${escapeHtml(item.payment_method || '-')}</div>
                            </div>
                        </div>
                        <div class="mt-3 d-grid gap-2">${actionHtml}</div>
                    </article>
                `;
            }).join('');

            renderReceiptPagination(paged.totalPages);
            bindReceiptViewButtons();
        };

        document.querySelectorAll('.receipt-tab').forEach((button) => {
            button.addEventListener('click', () => {
                document.querySelectorAll('.receipt-tab').forEach((node) => node.classList.remove('active'));
                button.classList.add('active');
                receiptActiveTab = String(button.getAttribute('data-tab') || 'all');
                receiptCurrentPage = 1;
                renderReceipts();
            });
        });

        document.getElementById('receiptSearch')?.addEventListener('input', () => {
            receiptCurrentPage = 1;
            renderReceipts();
        });
        document.getElementById('receiptMethodFilter')?.addEventListener('change', () => {
            receiptCurrentPage = 1;
            renderReceipts();
        });
        document.getElementById('receiptDateFrom')?.addEventListener('change', () => {
            receiptCurrentPage = 1;
            renderReceipts();
        });
        document.getElementById('receiptDateTo')?.addEventListener('change', () => {
            receiptCurrentPage = 1;
            renderReceipts();
        });
        document.getElementById('receiptFilterApply')?.addEventListener('click', () => {
            receiptCurrentPage = 1;
            renderReceipts();
        });
        document.getElementById('receiptFilterReset')?.addEventListener('click', () => {
            const methodFilter = document.getElementById('receiptMethodFilter');
            const dateFrom = document.getElementById('receiptDateFrom');
            const dateTo = document.getElementById('receiptDateTo');
            if (methodFilter) methodFilter.value = '';
            if (dateFrom) dateFrom.value = '';
            if (dateTo) dateTo.value = '';
            receiptCurrentPage = 1;
            renderReceipts();
        });
        document.getElementById('receiptEntriesPerPageInput')?.addEventListener('change', (event) => {
            receiptEntriesPerPage = Math.max(1, Number.parseInt(event.target.value || '20', 10) || 20);
            event.target.value = String(receiptEntriesPerPage);
            receiptCurrentPage = 1;
            renderReceipts();
        });
        document.getElementById('receiptRefreshBtn')?.addEventListener('click', (event) => {
            event.currentTarget?.classList.add('is-loading');
            window.location.reload();
        });

        modalEl.addEventListener('hidden.bs.modal', resetReceiptViewer);
        renderReceipts();
    });
</script>
<?php if (!$queryError && $receiptItems): ?>
<script>
    window.ADMIN_TABLE_COLUMNS_CONFIG = {
        tableSelector: '#receiptTable',
        modalId: 'receiptColumnsModal',
        listId: 'receiptColumnsList',
        resetBtnId: 'receiptColumnsReset',
        storageKey: 'resident_cols_official_receipts_v1',
        defaultHiddenIdxs: []
    };
</script>
<script src="<?= htmlspecialchars($baseUrl) ?>/JS-Script-Files/Admin-End/tableColumnsGeneric.js"></script>
<?php endif; ?>
</body>
</html>
