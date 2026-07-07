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

$residentUserId = (string)($_SESSION['user_id'] ?? '');
$workflowEndpoint = $baseUrl . '/PhpFiles/Resident-End/documentRequestWorkflow.php';

function downloads_column_exists(mysqli $conn, string $table, string $column): bool
{
    $tableEsc = $conn->real_escape_string($table);
    $columnEsc = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function downloads_format_datetime(?string $value): string
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

$downloadItems = [];
$queryError = '';

if (isset($conn) && $conn instanceof mysqli) {
    $requestTimestampCol = downloads_column_exists($conn, 'documentrequesttbl', 'request_timestamp')
        ? 'request_timestamp'
        : (downloads_column_exists($conn, 'documentrequesttbl', 'created_at') ? 'created_at' : "NULL");
    $releaseTimestampCol = downloads_column_exists($conn, 'documentrequesttbl', 'release_timestamp')
        ? 'release_timestamp'
        : (downloads_column_exists($conn, 'documentrequesttbl', 'ready_at') ? 'ready_at' : "NULL");
    $validityCol = downloads_column_exists($conn, 'documentrequesttbl', 'document_validity')
        ? 'document_validity'
        : "NULL";
    $documentTypeCol = downloads_column_exists($conn, 'documentrequesttbl', 'document_type')
        ? 'document_type'
        : "''";
    $stageCol = downloads_column_exists($conn, 'documentrequesttbl', 'stage')
        ? 'stage'
        : "''";
    $issuedFileCol = downloads_column_exists($conn, 'documentrequesttbl', 'issued_file_path');
    $invoiceFileCol = downloads_column_exists($conn, 'documentrequesttbl', 'invoice_file_path')
        ? 'invoice_file_path'
        : "NULL";
    $detailsCol = downloads_column_exists($conn, 'documentrequesttbl', 'request_details')
        ? 'request_details'
        : "'{}'";

    if ($issuedFileCol) {
        $sql = "
            SELECT
                request_id,
                {$documentTypeCol} AS document_type,
                {$detailsCol} AS request_details,
                {$stageCol} AS stage,
                {$requestTimestampCol} AS request_timestamp,
                {$releaseTimestampCol} AS release_timestamp,
                {$validityCol} AS document_validity,
                issued_file_path,
                {$invoiceFileCol} AS invoice_file_path
            FROM documentrequesttbl
            WHERE resident_user_id = ?
              AND issued_file_path IS NOT NULL
              AND issued_file_path <> ''
              AND ({$stageCol} = 'completed' OR {$stageCol} = '')
            ORDER BY COALESCE({$releaseTimestampCol}, {$requestTimestampCol}) DESC, request_id DESC
        ";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('s', $residentUserId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $payload = function_exists('dr_decode_request_payload')
                    ? dr_decode_request_payload($row)
                    : json_decode((string)($row['request_details'] ?? '{}'), true);
                if (!is_array($payload)) {
                    $payload = [];
                }
                $documentType = trim((string)($row['document_type'] ?? ''));
                if ($documentType === '') {
                    $documentType = trim((string)($payload['document_type'] ?? ''));
                }
                if ($documentType === '') {
                    $documentType = 'Document Request';
                }

                $purpose = trim((string)($payload['request_purpose'] ?? $payload['purpose'] ?? ''));
                $invoicePath = trim((string)($row['invoice_file_path'] ?? ''));
                $validUntilValue = (string)($row['document_validity'] ?? '');
                if (dr_is_barangay_id_document_type($documentType)) {
                    $barangayIdValidUntil = dr_barangay_id_valid_until_datetime($row);
                    if ($barangayIdValidUntil instanceof DateTimeImmutable) {
                        $validUntilValue = $barangayIdValidUntil->format('Y-m-d H:i:s');
                    }
                }
                $docTypeToken = preg_replace('/[^a-z0-9]+/i', '', strtolower($documentType));
                $isBarangayId = strpos($docTypeToken, 'barangayid') !== false;
                $viewUrl = $isBarangayId
                    ? ($baseUrl . '/Resident-End/BarangayId/DigitalId.php?request_id=' . rawurlencode((string)($row['request_id'] ?? '')) . '&embed=1')
                    : ($workflowEndpoint . '?action=view_issued&request_id=' . rawurlencode((string)($row['request_id'] ?? '')));
                $downloadUrl = $workflowEndpoint . '?action=download_issued&request_id=' . rawurlencode((string)($row['request_id'] ?? ''));
                $invoiceUrl = $invoicePath !== ''
                    ? ($workflowEndpoint . '?action=download_invoice&request_id=' . rawurlencode((string)($row['request_id'] ?? '')))
                    : '';
                $category = 'other';
                $documentTypeLower = strtolower($documentType);
                if ($isBarangayId) {
                    $category = 'barangay-id';
                } elseif (strpos($documentTypeLower, 'clearance') !== false || strpos($documentTypeLower, 'permit') !== false) {
                    $category = 'clearance';
                } elseif (
                    strpos($documentTypeLower, 'certificate') !== false
                    || strpos($documentTypeLower, 'residency') !== false
                    || strpos($documentTypeLower, 'indigency') !== false
                    || strpos($documentTypeLower, 'cohabitation') !== false
                ) {
                    $category = 'certificate';
                }

                $downloadItems[] = [
                    'request_id' => (string)($row['request_id'] ?? ''),
                    'document_type' => $documentType,
                    'purpose' => $purpose !== '' ? $purpose : '-',
                    'submitted_at' => downloads_format_datetime((string)($row['request_timestamp'] ?? '')),
                    'submitted_at_raw' => (string)($row['request_timestamp'] ?? ''),
                    'released_at' => downloads_format_datetime((string)($row['release_timestamp'] ?? '')),
                    'released_at_raw' => (string)($row['release_timestamp'] ?? ''),
                    'valid_until' => downloads_format_datetime($validUntilValue),
                    'valid_until_raw' => $validUntilValue,
                    'category' => $category,
                    'view_url' => $viewUrl,
                    'download_url' => $downloadUrl,
                    'invoice_path' => $invoicePath,
                    'invoice_url' => $invoiceUrl,
                ];
            }
            $stmt->close();
        } else {
            $queryError = 'Unable to load downloadable documents right now.';
        }
    } else {
        $queryError = 'Issued document storage is not available in this setup yet.';
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
    <title>Downloads</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260707-transactions-ui3">
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <style>
        :root {
            --downloads-hci-red: #d92d20;
            --downloads-hci-red-dark: #b42318;
            --downloads-hci-red-ring: rgba(217, 45, 32, 0.2);
        }
        body {
            background: #f8f9fb;
        }
        #mobile-header {
            display: none;
        }
        #div-mainDisplay {
            background: #f8f9fb !important;
        }
        .downloads-shell {
            width: 100%;
        }
        .txn-page-title {
            font-family: 'Charis SIL Bold', serif;
            color: #de710c;
            font-size: clamp(2rem, 4.4vw, 3rem);
            line-height: 1.1;
            margin: 0 0 0.65rem 0;
        }
        .downloads-subtitle {
            color: #5f6b7a;
            max-width: 760px;
        }
        .downloads-card {
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
        .compact-admin-table-shell::-webkit-scrollbar {
            height: 8px;
        }
        .compact-admin-table-shell::-webkit-scrollbar-thumb {
            background: rgba(108, 117, 125, 0.45);
            border-radius: 999px;
        }
        .compact-admin-table-shell::-webkit-scrollbar-track {
            background: transparent;
        }
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
        .downloads-summary {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(180deg, #fff6ec 0%, #ffe9d1 100%);
            color: #a35300;
            border: 1px solid rgba(254, 153, 60, 0.45);
            border-radius: 999px;
            padding: 8px 14px;
            font-weight: 700;
        }
        .downloads-summary .count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 28px;
            height: 28px;
            border-radius: 999px;
            background: #de710c;
            color: #fff;
            padding: 0 8px;
            font-size: 0.85rem;
        }
        .compact-admin-table {
            width: 100%;
        }
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
        .compact-admin-table tbody tr:last-child td {
            border-bottom: 0;
        }
        .compact-admin-table tbody tr:hover td {
            background: #fcfcfd;
        }
        .doc-title {
            font-weight: 700;
            color: #111827;
        }
        .doc-purpose {
            color: #6b7280;
            font-size: 0.92rem;
        }
        .request-id {
            font-weight: 700;
            color: #7c3f00;
        }
        .compact-admin-table .compact-table-actions,
        .download-actions {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            flex-wrap: nowrap;
        }
        .downloads-status-pill {
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
        .downloads-table {
            table-layout: auto;
            min-width: 100%;
        }
        .downloads-table th,
        .downloads-table td {
            white-space: normal;
        }
        .downloads-table th:first-child,
        .downloads-table td:first-child,
        .downloads-table th:nth-child(7),
        .downloads-table td:nth-child(7),
        .downloads-table th:nth-child(8),
        .downloads-table td:nth-child(8) {
            white-space: nowrap;
        }
        .downloads-table th:last-child,
        .downloads-table td:last-child {
            min-width: 150px;
        }
        #downloadCards {
            display: none;
        }
        .download-card {
            border: 1px solid #eceff3;
            border-radius: 12px;
            padding: 0.9rem;
            background: #fff;
            margin-bottom: 0.75rem;
        }
        .download-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .download-card-meta {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.5rem;
        }
        .download-card .txn-label {
            font-size: 0.78rem;
            color: #495057;
            text-transform: uppercase;
            letter-spacing: .02em;
            font-weight: 800;
        }
        .download-card .txn-value {
            font-size: 0.96rem;
            color: #212529;
            word-break: break-word;
            white-space: normal;
        }
        .empty-state {
            padding: 3rem 1.5rem;
            text-align: center;
            color: #6b7280;
        }
        .empty-state i {
            font-size: 2.2rem;
            color: #de710c;
            margin-bottom: 0.9rem;
        }
        #downloadViewerModal .modal-dialog {
            max-width: 1100px;
            width: min(92vw, 1100px);
        }
        #downloadViewerModal .modal-content {
            border: 0;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18);
        }
        #downloadViewerModal .modal-header {
            border-bottom: 1px solid #e9ecef;
            padding: 1rem 1.25rem;
        }
        #downloadViewerModal .modal-body {
            padding: 1rem 1.25rem 1.15rem;
            background: #f8fafc;
        }
        #downloadViewerModal .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 0.9rem 1.25rem 1rem;
        }
        .download-preview-frame {
            width: 100%;
            height: 70vh;
            border: 1px solid #d9e0e7;
            border-radius: 0.85rem;
            background: #ffffff;
        }
        .download-preview-empty {
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
        .download-modal-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.75rem;
            width: 100%;
            margin: 0;
        }
        .download-modal-btn {
            min-height: 46px;
            border-radius: 14px;
            padding: 10px 18px;
            font-weight: 700;
            min-width: 220px;
            box-shadow: none;
            transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease;
        }
        .download-modal-btn:hover,
        .download-modal-btn:focus {
            transform: none;
            box-shadow: none;
        }
        .btn-download-modal-open {
            color: #fff;
            background: var(--downloads-hci-red);
            border: 1px solid var(--downloads-hci-red);
        }
        .btn-download-modal-open:hover,
        .btn-download-modal-open:focus {
            color: #fff;
            background: var(--downloads-hci-red-dark);
            border-color: var(--downloads-hci-red-dark);
            box-shadow: 0 0 0 0.2rem var(--downloads-hci-red-ring);
        }
        .btn-download-modal-close {
            background: #ffffff;
            color: #344054;
            border: 1px solid #d0d5dd;
        }
        .btn-download-modal-close:hover,
        .btn-download-modal-close:focus {
            background: #f8fafc;
            color: #1f2937;
            border-color: #cbd5e1;
        }
        @media (max-width: 991.98px) {
            .admin-list-toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            .admin-list-actions {
                margin-left: 0;
                justify-content: flex-start;
            }
        }
        @media (max-width: 767.98px) {
            #div-mainDisplay {
                padding: 1rem !important;
            }
            .downloads-subtitle {
                font-size: 0.95rem;
            }
            .downloads-card {
                padding: 0.9rem !important;
            }
            .admin-list-actions {
                width: 100%;
                gap: 0.6rem;
            }
            .tracker-table-responsive {
                display: none;
            }
            #downloadCards {
                display: block;
                margin-top: 0.25rem;
            }
            .txn-page-title {
                font-size: clamp(1.7rem, 7.5vw, 2.15rem);
                margin-bottom: 0.4rem;
            }
            .download-card-header {
                flex-direction: column;
                align-items: stretch;
            }
            .downloads-status-pill {
                align-self: flex-start;
            }
            .download-actions {
                flex-direction: column;
            }
            .download-actions .btn {
                width: 100%;
            }
            .download-preview-frame {
                height: 62vh;
            }
            .download-modal-actions {
                flex-direction: column-reverse;
                align-items: stretch;
            }
            .download-modal-btn {
                width: 100%;
                min-width: 0;
            }
        }
        @media (max-width: 480px) {
            .downloads-summary {
                width: 100%;
                justify-content: space-between;
            }
            .admin-list-actions {
                flex-direction: column;
                align-items: stretch;
            }
            .download-card {
                padding: 0.8rem;
            }
            .download-card-header {
                gap: 0.55rem;
            }
            #downloadViewerModal .modal-header,
            #downloadViewerModal .modal-body,
            #downloadViewerModal .modal-footer {
                padding-left: 0.9rem;
                padding-right: 0.9rem;
            }
            .download-preview-frame {
                height: 56vh;
            }
        }
        @media (max-width: 1160px) {
            #mobile-header {
                display: block !important;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                z-index: 1030;
                height: auto !important;
                padding: 0 !important;
                margin: 0 !important;
                overflow: visible !important;
            }
            #mobile-header .d-flex {
                width: 100%;
            }
            #div-mainDisplay {
                margin-left: 0 !important;
                width: 100%;
                padding-top: 1rem !important;
            }
            body {
                padding-top: 64px;
            }
            #div-sidebarWrapper {
                position: fixed !important;
                top: 0;
                left: 0;
                height: 100vh !important;
                width: 280px;
                z-index: 1060;
                transform: translateX(-100%);
                transition: transform 0.28s ease;
                box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0);
            }
            #div-sidebarWrapper.show {
                transform: translateX(0);
                box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.25);
            }
        }
        @media (min-width: 1161px) {
            body {
                padding-top: 0;
            }
            #mobile-header {
                display: none !important;
            }
            #div-sidebarWrapper {
                transform: none !important;
            }
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
        <div class="downloads-shell">
            <h1 class="txn-page-title">Downloads</h1>
            <hr class="mt-0 mb-3">
            <p class="downloads-subtitle mb-4">
                Download your approved and released document requests here. Only documents that are ready and have an issued file available will appear in this list.
            </p>

            <section class="downloads-card p-4 resident-masterlist-shell">
                <?php if ($queryError !== ''): ?>
                    <div class="alert alert-warning mb-0"><?= htmlspecialchars($queryError) ?></div>
                <?php elseif (!$downloadItems): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-file-arrow-down"></i>
                        <h2 class="h5 mb-2">No downloadable documents yet</h2>
                        <p class="mb-0">Once your document requests are approved, issued, and ready for release, they will appear here for download.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-list-toolbar mb-3 pt-2 flex-wrap">
                        <div class="admin-list-tabs">
                            <button type="button" class="btn btn-outline-primary btn-sm status-filter-btn download-tab active" data-tab="all" data-filter="ALL">All</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn download-tab" data-tab="certificate" data-filter="Certificates">Certificates</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn download-tab" data-tab="clearance" data-filter="Clearances">Clearances</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn download-tab" data-tab="barangay-id" data-filter="Barangay ID">Barangay ID</button>
                        </div>
                        <div class="admin-list-actions">
                            <div class="input-group admin-search">
                                <input id="downloadSearch" class="form-control" placeholder="Search downloads..." />
                                <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                            </div>
                            <button id="downloadFilterBtn" class="btn btn-outline-secondary btn-icon admin-filter" type="button" title="Filter" aria-label="Filter" data-bs-toggle="modal" data-bs-target="#downloadFilterModal">
                                <i class="fas fa-filter"></i>
                            </button>
                            <button id="downloadColumnsBtn" class="btn btn-outline-secondary btn-icon admin-columns" type="button" title="Columns" aria-label="Columns" data-bs-toggle="modal" data-bs-target="#downloadColumnsModal">
                                <i class="fa-solid fa-sliders"></i>
                            </button>
                            <button id="downloadRefreshBtn" class="btn btn-outline-secondary btn-icon admin-refresh" type="button" title="Refresh downloads" aria-label="Refresh downloads">
                                <i class="fa-solid fa-arrows-rotate"></i>
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive compact-admin-table-shell tracker-table-responsive">
                        <table id="downloadsTable" class="table align-middle mb-0 compact-admin-table downloads-table">
                            <thead>
                                <tr class="table-light">
                                    <th>Request ID</th>
                                    <th>Document</th>
                                    <th>Purpose</th>
                                    <th>Submitted</th>
                                    <th>Released</th>
                                    <th>Valid Until</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="downloadsTbody">
                                <tr><td colspan="8" class="text-center text-muted py-4">Loading downloads...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="resident-table-footer mt-3 d-flex flex-wrap justify-content-between align-items-center gap-3 tracker-table-responsive">
                        <div class="d-flex align-items-center gap-2">
                            <label for="downloadEntriesPerPageInput" class="small text-muted mb-0">Entries</label>
                            <input id="downloadEntriesPerPageInput" type="number" min="1" step="1" value="20" class="form-control form-control-sm resident-entries-input" />
                        </div>
                        <nav aria-label="Downloads pagination">
                            <ul class="pagination pagination-sm mb-0" id="downloadPagination"></ul>
                        </nav>
                    </div>

                    <div id="downloadCards" class="mt-2">
                        <div class="text-center text-muted py-4">Loading downloads...</div>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>

<?php if (!$queryError && $downloadItems): ?>
<div class="modal fade" id="downloadFilterModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Filter Downloads</h5>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small text-muted mb-1">Document Type</label>
                        <select class="form-select" id="downloadTypeFilter">
                            <option value="">All Documents</option>
                            <option value="certificate">Certificates</option>
                            <option value="clearance">Clearances</option>
                            <option value="barangay-id">Barangay ID</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-muted mb-1">Released Date From</label>
                        <input type="date" class="form-control" id="downloadDateFrom" />
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-muted mb-1">Released Date To</label>
                        <input type="date" class="form-control" id="downloadDateTo" />
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="downloadFilterReset">Reset</button>
                <button type="button" class="btn btn-primary" id="downloadFilterApply" data-bs-dismiss="modal">Apply</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="downloadColumnsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Columns</h5>
            </div>
            <div class="modal-body">
                <div class="row g-2" id="downloadColumnsList"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="downloadColumnsReset">Reset</button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="downloadViewerModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="fw-bold mb-0" id="downloadViewerTitle">Document Preview</h5>
                    <div class="small text-muted" id="downloadViewerSubtitle"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="downloadViewerEmpty" class="download-preview-empty d-none">
                    Document preview is unavailable right now.
                </div>
                <iframe id="downloadViewerFrame" class="download-preview-frame d-none" title="Document Preview"></iframe>
            </div>
            <div class="modal-footer">
                <div class="download-modal-actions">
                    <button type="button" class="btn btn-download-modal-close download-modal-btn" data-bs-dismiss="modal">Close</button>
                    <a href="#" class="btn btn-download-modal-open download-modal-btn d-none" id="downloadViewerOpenNewTab" target="_blank" rel="noopener">
                        Open in new tab
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
        const downloadItems = <?= json_encode($downloadItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        let downloadActiveTab = 'all';
        let downloadCurrentPage = 1;
        let downloadEntriesPerPage = 20;

        const modalEl = document.getElementById('downloadViewerModal');
        const frameEl = document.getElementById('downloadViewerFrame');
        const emptyEl = document.getElementById('downloadViewerEmpty');
        const titleEl = document.getElementById('downloadViewerTitle');
        const subtitleEl = document.getElementById('downloadViewerSubtitle');
        const openNewTabEl = document.getElementById('downloadViewerOpenNewTab');

        if (!modalEl || !window.bootstrap?.Modal) {
            return;
        }

        const viewerModal = new bootstrap.Modal(modalEl);

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

        const renderDownloadPagination = (totalPages) => {
            const pagination = document.getElementById('downloadPagination');
            if (!pagination) {
                return;
            }
            if (totalPages <= 1) {
                pagination.innerHTML = '';
                return;
            }

            const items = [];
            items.push(`
                <li class="page-item ${downloadCurrentPage <= 1 ? 'disabled' : ''}">
                    <button type="button" class="page-link" data-page="${downloadCurrentPage - 1}">Prev</button>
                </li>
            `);
            for (let page = 1; page <= totalPages; page += 1) {
                items.push(`
                    <li class="page-item ${page === downloadCurrentPage ? 'active' : ''}">
                        <button type="button" class="page-link" data-page="${page}">${page}</button>
                    </li>
                `);
            }
            items.push(`
                <li class="page-item ${downloadCurrentPage >= totalPages ? 'disabled' : ''}">
                    <button type="button" class="page-link" data-page="${downloadCurrentPage + 1}">Next</button>
                </li>
            `);
            pagination.innerHTML = items.join('');
            pagination.querySelectorAll('button[data-page]').forEach((button) => {
                button.addEventListener('click', () => {
                    if (button.closest('.page-item')?.classList.contains('disabled')) {
                        return;
                    }
                    downloadCurrentPage = Number.parseInt(button.getAttribute('data-page') || '1', 10) || 1;
                    renderDownloads();
                });
            });
        };

        const getFilteredDownloads = () => {
            const search = String(document.getElementById('downloadSearch')?.value || '').toLowerCase().trim();
            const type = String(document.getElementById('downloadTypeFilter')?.value || '').trim();
            const dateFrom = String(document.getElementById('downloadDateFrom')?.value || '').trim();
            const dateTo = String(document.getElementById('downloadDateTo')?.value || '').trim();

            return downloadItems.filter((item) => {
                const category = String(item.category || 'other').trim();
                if (downloadActiveTab !== 'all' && category !== downloadActiveTab) {
                    return false;
                }
                if (type && category !== type) {
                    return false;
                }

                const releasedDate = dateOnly(item.released_at_raw || item.submitted_at_raw || '');
                if (dateFrom && releasedDate && releasedDate < dateFrom) {
                    return false;
                }
                if (dateTo && releasedDate && releasedDate > dateTo) {
                    return false;
                }

                if (search) {
                    const haystack = [
                        item.request_id,
                        item.document_type,
                        item.purpose,
                        item.submitted_at,
                        item.released_at,
                        item.valid_until,
                    ].join(' ').toLowerCase();
                    if (!haystack.includes(search)) {
                        return false;
                    }
                }

                return true;
            });
        };

        const resetViewer = () => {
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
            if (titleEl) {
                titleEl.textContent = 'Document Preview';
            }
            if (subtitleEl) {
                subtitleEl.textContent = '';
            }
        };

        const openViewer = ({ url, title, requestId }) => {
            resetViewer();

            if (titleEl) {
                titleEl.textContent = title || 'Document Preview';
            }
            if (subtitleEl) {
                subtitleEl.textContent = requestId ? `Request ID: ${requestId}` : '';
            }

            if (url && frameEl) {
                const viewUrl = url.includes('_ts=') ? url : `${url}${url.includes('?') ? '&' : '?'}_ts=${Date.now()}`;
                frameEl.src = viewUrl;
                frameEl.classList.remove('d-none');
                if (openNewTabEl) {
                    openNewTabEl.href = viewUrl;
                    openNewTabEl.classList.remove('d-none');
                }
            } else if (emptyEl) {
                emptyEl.classList.remove('d-none');
            }

            viewerModal.show();
        };

        const bindDownloadViewButtons = () => {
            document.querySelectorAll('.js-view-download').forEach((trigger) => {
                trigger.addEventListener('click', () => {
                    openViewer({
                        url: String(trigger.dataset.viewUrl || '').trim(),
                        title: String(trigger.dataset.viewTitle || '').trim(),
                        requestId: String(trigger.dataset.viewRequestId || '').trim(),
                    });
                });
            });
        };

        const renderDownloads = () => {
            const tbody = document.getElementById('downloadsTbody');
            const cards = document.getElementById('downloadCards');
            if (!tbody || !cards) {
                return;
            }

            const rows = getFilteredDownloads();
            const paged = paginateRows(rows, downloadCurrentPage, downloadEntriesPerPage);
            downloadCurrentPage = paged.page;

            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No downloads found.</td></tr>';
                cards.innerHTML = '<div class="text-center text-muted py-4">No downloads found.</div>';
                renderDownloadPagination(1);
                return;
            }

            tbody.innerHTML = paged.items.map((item) => {
                const invoiceAction = item.invoice_url
                    ? `
                        <a class="btn btn-sm compact-table-btn btn-invoice" href="${escapeHtml(item.invoice_url)}">
                            <i class="fa-solid fa-receipt me-1"></i>Invoice
                        </a>
                    `
                    : '';
                return `
                    <tr>
                        <td class="request-id">${escapeHtml(item.request_id || '-')}</td>
                        <td><div class="doc-title">${escapeHtml(item.document_type || '-')}</div></td>
                        <td class="doc-purpose">${escapeHtml(item.purpose || '-')}</td>
                        <td>${escapeHtml(item.submitted_at || '-')}</td>
                        <td>${escapeHtml(item.released_at || '-')}</td>
                        <td>${escapeHtml(item.valid_until || '-')}</td>
                        <td><span class="downloads-status-pill"><i class="fa-solid fa-circle-check"></i> Ready</span></td>
                        <td>
                            <div class="compact-table-actions">
                                <button type="button"
                                        class="btn btn-sm compact-table-btn btn-view-download js-view-download"
                                        data-view-url="${escapeHtml(item.view_url || '')}"
                                        data-view-title="${escapeHtml(item.document_type || '')}"
                                        data-view-request-id="${escapeHtml(item.request_id || '')}">
                                    <i class="fa-regular fa-eye me-1"></i>View
                                </button>
                                <a class="btn btn-sm compact-table-btn btn-download" href="${escapeHtml(item.download_url || '#')}">
                                    <i class="fa-solid fa-download me-1"></i>Download
                                </a>
                                ${invoiceAction}
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');

            cards.innerHTML = paged.items.map((item) => {
                const invoiceAction = item.invoice_url
                    ? `
                        <a class="btn btn-sm compact-table-btn btn-invoice" href="${escapeHtml(item.invoice_url)}">
                            <i class="fa-solid fa-receipt me-1"></i>Invoice
                        </a>
                    `
                    : '';
                return `
                    <article class="download-card">
                        <div class="download-card-header">
                            <div>
                                <div class="doc-title">${escapeHtml(item.document_type || '-')}</div>
                                <div class="request-id mt-1">${escapeHtml(item.request_id || '-')}</div>
                            </div>
                            <span class="downloads-status-pill"><i class="fa-solid fa-circle-check"></i> Ready</span>
                        </div>
                        <div class="download-card-meta">
                            <div>
                                <div class="txn-label">Purpose</div>
                                <div class="txn-value">${escapeHtml(item.purpose || '-')}</div>
                            </div>
                            <div>
                                <div class="txn-label">Submitted</div>
                                <div class="txn-value">${escapeHtml(item.submitted_at || '-')}</div>
                            </div>
                            <div>
                                <div class="txn-label">Released</div>
                                <div class="txn-value">${escapeHtml(item.released_at || '-')}</div>
                            </div>
                            <div>
                                <div class="txn-label">Valid Until</div>
                                <div class="txn-value">${escapeHtml(item.valid_until || '-')}</div>
                            </div>
                        </div>
                        <div class="download-actions mt-3">
                            <button type="button"
                                    class="btn btn-sm compact-table-btn btn-view-download js-view-download"
                                    data-view-url="${escapeHtml(item.view_url || '')}"
                                    data-view-title="${escapeHtml(item.document_type || '')}"
                                    data-view-request-id="${escapeHtml(item.request_id || '')}">
                                <i class="fa-regular fa-eye me-1"></i>View
                            </button>
                            <a class="btn btn-sm compact-table-btn btn-download" href="${escapeHtml(item.download_url || '#')}">
                                <i class="fa-solid fa-download me-1"></i>Download
                            </a>
                            ${invoiceAction}
                        </div>
                    </article>
                `;
            }).join('');

            renderDownloadPagination(paged.totalPages);
            bindDownloadViewButtons();
        };

        document.querySelectorAll('.download-tab').forEach((button) => {
            button.addEventListener('click', () => {
                document.querySelectorAll('.download-tab').forEach((node) => node.classList.remove('active'));
                button.classList.add('active');
                downloadActiveTab = String(button.getAttribute('data-tab') || 'all');
                downloadCurrentPage = 1;
                renderDownloads();
            });
        });

        document.getElementById('downloadSearch')?.addEventListener('input', () => {
            downloadCurrentPage = 1;
            renderDownloads();
        });
        document.getElementById('downloadTypeFilter')?.addEventListener('change', () => {
            downloadCurrentPage = 1;
            renderDownloads();
        });
        document.getElementById('downloadDateFrom')?.addEventListener('change', () => {
            downloadCurrentPage = 1;
            renderDownloads();
        });
        document.getElementById('downloadDateTo')?.addEventListener('change', () => {
            downloadCurrentPage = 1;
            renderDownloads();
        });
        document.getElementById('downloadFilterApply')?.addEventListener('click', () => {
            downloadCurrentPage = 1;
            renderDownloads();
        });
        document.getElementById('downloadFilterReset')?.addEventListener('click', () => {
            const typeFilter = document.getElementById('downloadTypeFilter');
            const dateFrom = document.getElementById('downloadDateFrom');
            const dateTo = document.getElementById('downloadDateTo');
            if (typeFilter) typeFilter.value = '';
            if (dateFrom) dateFrom.value = '';
            if (dateTo) dateTo.value = '';
            downloadCurrentPage = 1;
            renderDownloads();
        });
        document.getElementById('downloadEntriesPerPageInput')?.addEventListener('change', (event) => {
            downloadEntriesPerPage = Math.max(1, Number.parseInt(event.target.value || '20', 10) || 20);
            event.target.value = String(downloadEntriesPerPage);
            downloadCurrentPage = 1;
            renderDownloads();
        });
        document.getElementById('downloadRefreshBtn')?.addEventListener('click', (event) => {
            event.currentTarget?.classList.add('is-loading');
            window.location.reload();
        });

        modalEl.addEventListener('hidden.bs.modal', resetViewer);
        renderDownloads();
    });
</script>
<?php if (!$queryError && $downloadItems): ?>
<script>
    window.ADMIN_TABLE_COLUMNS_CONFIG = {
        tableSelector: '#downloadsTable',
        modalId: 'downloadColumnsModal',
        listId: 'downloadColumnsList',
        resetBtnId: 'downloadColumnsReset',
        storageKey: 'resident_cols_downloads_v1',
        defaultHiddenIdxs: []
    };
</script>
<script src="<?= htmlspecialchars($baseUrl) ?>/JS-Script-Files/Admin-End/tableColumnsGeneric.js"></script>
<?php endif; ?>
</body>
</html>
