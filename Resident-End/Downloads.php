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
            background: #fffdfb;
        }
        #div-mainDisplay {
            background: #ffffff !important;
        }
        .downloads-shell {
            max-width: 1300px;
            margin: 0 auto;
        }
        .downloads-title {
            font-family: 'Charis SIL Bold', serif;
            color: #de710c;
            font-size: clamp(2rem, 4.2vw, 3rem);
            line-height: 1.1;
            margin: 0;
        }
        .downloads-subtitle {
            color: #5f6b7a;
            max-width: 780px;
            margin-bottom: 1.5rem;
        }
        .downloads-card {
            background: #ffffff;
            border: 1px solid #f2dcc5;
            border-radius: 18px;
            box-shadow: 0 14px 36px rgba(15, 23, 42, 0.08);
        }
        .downloads-summary {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #fff2e4;
            color: #a35300;
            border: 1px solid #f2c79b;
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
        .downloads-table-wrap {
            overflow-x: auto;
        }
        .downloads-table {
            --bs-table-bg: #ffffff;
            --bs-table-striped-bg: #ffffff;
            --bs-table-hover-bg: #fffaf5;
            --bs-table-accent-bg: transparent;
            margin-bottom: 0;
            min-width: 920px;
            background: #ffffff;
        }
        .downloads-table thead th {
            background: #fff3e4;
            color: #7c3f00;
            border-bottom-color: #f1d6ba;
            font-size: 0.84rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .downloads-table tbody,
        .downloads-table tbody tr,
        .downloads-table tbody td {
            background: #ffffff !important;
        }
        .downloads-table tbody td {
            vertical-align: middle;
            border-color: #f3e5d6;
        }
        .downloads-table tbody tr:hover td {
            background: #fffaf5 !important;
        }
        .doc-title {
            font-weight: 700;
            color: #1f2937;
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
    </style>
</head>
<body>
<div class="d-flex min-vh-100">
    <?php include __DIR__ . '/includes/resident_sidebar.php'; ?>

    <main id="div-mainDisplay" class="flex-grow-1 p-4 p-md-5">
        <div class="downloads-shell">
            <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-4">
                <div>
                    <h1 class="downloads-title">Downloads</h1>
                    <hr class="mt-3 mb-3">
                    <p class="downloads-subtitle mb-0">
                        Download your approved and released document requests here. Only documents that are ready and have an issued file available will appear in this list.
                    </p>
                </div>
                <div class="downloads-summary">
                    <span>Available Files</span>
                    <span class="count"><?= count($downloadItems) ?></span>
                </div>
            </div>

            <section class="downloads-card p-3 p-md-4">
                <?php if ($queryError !== ''): ?>
                    <div class="alert alert-warning mb-0"><?= htmlspecialchars($queryError) ?></div>
                <?php elseif (!$downloadItems): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-file-arrow-down"></i>
                        <h2 class="h5 mb-2">No downloadable documents yet</h2>
                        <p class="mb-0">Once your document requests are approved, issued, and ready for release, they will appear here for download.</p>
                    </div>
                <?php else: ?>
                    <div class="downloads-table-wrap">
                        <table class="table downloads-table">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Document</th>
                                    <th>Purpose</th>
                                    <th>Submitted</th>
                                    <th>Released</th>
                                    <th>Valid Until</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($downloadItems as $item): ?>
                                    <?php
                                    $requestId = (string)$item['request_id'];
                                    $viewUrl = $workflowEndpoint . '?action=view_issued&request_id=' . rawurlencode($requestId);
                                    $downloadUrl = $workflowEndpoint . '?action=download_issued&request_id=' . rawurlencode($requestId);
                                    ?>
                                    <tr>
                                        <td class="request-id"><?= htmlspecialchars($requestId) ?></td>
                                        <td><div class="doc-title"><?= htmlspecialchars((string)$item['document_type']) ?></div></td>
                                        <td class="doc-purpose"><?= htmlspecialchars((string)$item['purpose']) ?></td>
                                        <td><?= htmlspecialchars((string)$item['submitted_at']) ?></td>
                                        <td><?= htmlspecialchars((string)$item['released_at']) ?></td>
                                        <td><?= htmlspecialchars((string)$item['valid_until']) ?></td>
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
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
