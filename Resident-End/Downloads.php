477777777<?php
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
                issued_file_path
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
                $payload = json_decode((string)($row['request_details'] ?? '{}'), true);
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
                $downloadItems[] = [
                    'request_id' => (string)($row['request_id'] ?? ''),
                    'document_type' => $documentType,
                    'purpose' => $purpose !== '' ? $purpose : '-',
                    'submitted_at' => downloads_format_datetime((string)($row['request_timestamp'] ?? '')),
                    'released_at' => downloads_format_datetime((string)($row['release_timestamp'] ?? '')),
                    'valid_until' => downloads_format_datetime((string)($row['document_validity'] ?? '')),
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
    <title>Downloads</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <style>
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
        .table-responsive {
            overflow-x: auto;
        }
        .audit-table {
            min-width: 920px;
            --bs-table-hover-bg: #fff8f1;
            --bs-table-accent-bg: transparent;
        }
        .audit-table thead th {
            background: #f8f9fb;
            color: #495057;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border-bottom-color: #e3e6ea;
            white-space: nowrap;
        }
        .audit-table tbody td {
            vertical-align: middle;
            border-color: #edf0f3;
            background: #fff !important;
        }
        .audit-table tbody tr:hover td {
            background: #fff8f1 !important;
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
        .download-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn-download {
            background: #de710c;
            border-color: #de710c;
            color: #fff;
            font-weight: 700;
        }
        .btn-download:hover {
            background: #c76309;
            border-color: #c76309;
            color: #fff;
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
            .table-responsive {
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
        }
        @media (max-width: 480px) {
            .audit-table {
                min-width: 760px;
            }
            .audit-table td,
            .audit-table th {
                font-size: 0.875rem;
            }
            .downloads-summary {
                width: 100%;
                justify-content: space-between;
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
            <div class="mb-4">
                <div>
                    <h1 class="txn-page-title">Downloads</h1>
                    <hr class="mt-0 mb-3">
                    <p class="downloads-subtitle mb-0">
                        Download your approved and released document requests here. Only documents that are ready and have an issued file available will appear in this list.
                    </p>
                </div>
            </div>

            <section class="downloads-card p-4 audit-shell">
                <?php if ($queryError !== ''): ?>
                    <div class="alert alert-warning mb-0"><?= htmlspecialchars($queryError) ?></div>
                <?php elseif (!$downloadItems): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-file-arrow-down"></i>
                        <h2 class="h5 mb-2">No downloadable documents yet</h2>
                        <p class="mb-0">Once your document requests are approved, issued, and ready for release, they will appear here for download.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-list-toolbar mb-3">
                        <div>
                            <h2 class="h5 mb-1 fw-bold">Released Documents</h2>
                            <p class="text-muted small mb-0">This table follows the same transaction-style layout used in your resident history pages.</p>
                        </div>
                        <div class="admin-list-actions">
                            <div class="downloads-summary">
                                <span>Available Files</span>
                                <span class="count"><?= count($downloadItems) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 audit-table">
                            <thead>
                                <tr>
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
                            <tbody>
                                <?php foreach ($downloadItems as $item): ?>
                                    <?php
                                    $requestId = (string)$item['request_id'];
                                    $docTypeToken = preg_replace('/[^a-z0-9]+/i', '', strtolower((string)($item['document_type'] ?? '')));
                                    $isBarangayId = strpos($docTypeToken, 'barangayid') !== false;
                                    $viewUrl = $isBarangayId
                                        ? ($baseUrl . '/Resident-End/BarangayId/DigitalId.php?request_id=' . rawurlencode($requestId))
                                        : ($workflowEndpoint . '?action=view_issued&request_id=' . rawurlencode($requestId));
                                    $downloadUrl = $workflowEndpoint . '?action=download_issued&request_id=' . rawurlencode($requestId);
                                    ?>
                                    <tr>
                                        <td class="request-id"><?= htmlspecialchars($requestId) ?></td>
                                        <td><div class="doc-title"><?= htmlspecialchars((string)$item['document_type']) ?></div></td>
                                        <td class="doc-purpose"><?= htmlspecialchars((string)$item['purpose']) ?></td>
                                        <td><?= htmlspecialchars((string)$item['submitted_at']) ?></td>
                                        <td><?= htmlspecialchars((string)$item['released_at']) ?></td>
                                        <td><?= htmlspecialchars((string)$item['valid_until']) ?></td>
                                        <td><span class="downloads-status-pill"><i class="fa-solid fa-circle-check"></i> Ready</span></td>
                                        <td>
                                            <div class="download-actions">
                                                <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($viewUrl) ?>" target="_blank" rel="noopener">
                                                    <i class="fa-regular fa-eye me-1"></i>View
                                                </a>
                                                <a class="btn btn-sm btn-download" href="<?= htmlspecialchars($downloadUrl) ?>">
                                                    <i class="fa-solid fa-download me-1"></i>Download
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="downloadCards" class="mt-2">
                        <?php foreach ($downloadItems as $item): ?>
                            <?php
                            $requestId = (string)$item['request_id'];
                            $docTypeToken = preg_replace('/[^a-z0-9]+/i', '', strtolower((string)($item['document_type'] ?? '')));
                            $isBarangayId = strpos($docTypeToken, 'barangayid') !== false;
                            $viewUrl = $isBarangayId
                                ? ($baseUrl . '/Resident-End/BarangayId/DigitalId.php?request_id=' . rawurlencode($requestId))
                                : ($workflowEndpoint . '?action=view_issued&request_id=' . rawurlencode($requestId));
                            $downloadUrl = $workflowEndpoint . '?action=download_issued&request_id=' . rawurlencode($requestId);
                            ?>
                            <article class="download-card">
                                <div class="download-card-header">
                                    <div>
                                        <div class="doc-title"><?= htmlspecialchars((string)$item['document_type']) ?></div>
                                        <div class="request-id mt-1"><?= htmlspecialchars($requestId) ?></div>
                                    </div>
                                    <span class="downloads-status-pill"><i class="fa-solid fa-circle-check"></i> Ready</span>
                                </div>
                                <div class="download-card-meta">
                                    <div>
                                        <div class="txn-label">Purpose</div>
                                        <div class="txn-value"><?= htmlspecialchars((string)$item['purpose']) ?></div>
                                    </div>
                                    <div>
                                        <div class="txn-label">Submitted</div>
                                        <div class="txn-value"><?= htmlspecialchars((string)$item['submitted_at']) ?></div>
                                    </div>
                                    <div>
                                        <div class="txn-label">Released</div>
                                        <div class="txn-value"><?= htmlspecialchars((string)$item['released_at']) ?></div>
                                    </div>
                                    <div>
                                        <div class="txn-label">Valid Until</div>
                                        <div class="txn-value"><?= htmlspecialchars((string)$item['valid_until']) ?></div>
                                    </div>
                                </div>
                                <div class="download-actions mt-3">
                                    <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($viewUrl) ?>" target="_blank" rel="noopener">
                                        <i class="fa-regular fa-eye me-1"></i>View
                                    </a>
                                    <a class="btn btn-sm btn-download" href="<?= htmlspecialchars($downloadUrl) ?>">
                                        <i class="fa-solid fa-download me-1"></i>Download
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
