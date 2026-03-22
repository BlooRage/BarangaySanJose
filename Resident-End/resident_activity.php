<?php
$allowUnregistered = false;
require_once __DIR__ . '/includes/resident_access_guard.php';

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

function ra_table_exists(mysqli $conn, string $tableName): bool
{
    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();

    return !empty($row);
}

function ra_table_columns(mysqli $conn, string $tableName): array
{
    $columns = [];
    $stmt = $conn->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    if (!$stmt) {
        return $columns;
    }

    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $column = strtolower(trim((string)($row['COLUMN_NAME'] ?? '')));
        if ($column !== '') {
            $columns[$column] = true;
        }
    }
    $stmt->close();

    return $columns;
}

function ra_format_datetime(?string $value, string $fallback = '-'): string
{
    $text = trim((string)$value);
    if ($text === '') {
        return $fallback;
    }

    $timestamp = strtotime($text);
    if ($timestamp === false) {
        return $text;
    }

    return date('M d, Y h:i A', $timestamp);
}

function ra_format_date(?string $value, string $fallback = '-'): string
{
    $text = trim((string)$value);
    if ($text === '') {
        return $fallback;
    }

    $timestamp = strtotime($text);
    if ($timestamp === false) {
        return $text;
    }

    return date('M d, Y', $timestamp);
}

function ra_status_meta(string $type, string $statusName, array $row = []): array
{
    $normalized = strtolower(trim($statusName));
    $label = trim($statusName) !== '' ? trim($statusName) : 'Pending';
    $pill = 'pending';

    if ($type === 'complaint') {
        if ((int)($row['escalated_to_blotter'] ?? 0) === 1 || str_contains($normalized, 'endorse')) {
            return ['label' => 'Endorsed to Blotter', 'pill' => 'archived'];
        }
        if (str_contains($normalized, 'resolve')) {
            return ['label' => 'Resolved', 'pill' => 'approved'];
        }
        if (str_contains($normalized, 'drop')) {
            return ['label' => 'Dropped', 'pill' => 'archived'];
        }
        return ['label' => $label, 'pill' => 'pending'];
    }

    if (str_contains($normalized, 'approve') || str_contains($normalized, 'complete') || str_contains($normalized, 'done')) {
        return ['label' => 'Approved', 'pill' => 'approved'];
    }
    if (str_contains($normalized, 'resched')) {
        return ['label' => 'Rescheduled', 'pill' => 'info'];
    }
    if (str_contains($normalized, 'deny') || str_contains($normalized, 'reject')) {
        return ['label' => 'Denied', 'pill' => 'archived'];
    }

    return ['label' => $label, 'pill' => 'pending'];
}

function ra_summary_family(string $transactionType): ?string
{
    $type = strtoupper(trim($transactionType));
    if ($type === 'DOCUMENT_REQUEST') {
        return 'Document Requests';
    }
    if (in_array($type, ['EDIT_REQUEST_PROFILE', 'EDIT_REQUEST_ADDRESS', 'EDIT_REQUEST_EMERGENCY'], true)) {
        return 'Edit Requests';
    }
    if (in_array($type, ['SECTOR_MEMBERSHIP', 'SECTOR_MEMBERSHIP_VERIFICATION'], true)) {
        return 'Sector Membership';
    }
    if (in_array($type, ['RESIDENT_PROFILING', 'PROOF_OF_RESIDENCY'], true)) {
        return 'Resident Profiling';
    }
    return null;
}

function ra_stage_key(?array $metadata): string
{
    if (!is_array($metadata)) {
        return '';
    }
    return strtolower(trim((string)($metadata['stage'] ?? '')));
}

function ra_status_key(string $value): string
{
    return strtolower(preg_replace('/[\s_-]+/', '', trim($value)));
}

function ra_is_resident_attention_needed(string $transactionType, string $statusName, ?array $metadata = null): bool
{
    $family = ra_summary_family($transactionType);
    if ($family === null) {
        return false;
    }

    $stage = ra_stage_key($metadata);
    if ($family === 'Document Requests') {
        return in_array($stage, ['for_payment', 'payment_rejected', 'ready_for_claim', 'for_interview', 'for_inspection'], true);
    }

    $statusKey = ra_status_key($statusName);
    return in_array($statusKey, ['rejected', 'denied', 'notverified'], true);
}

function ra_is_active_transaction(string $transactionType, string $statusName, ?array $metadata = null): bool
{
    $family = ra_summary_family($transactionType);
    if ($family === null) {
        return false;
    }

    $stage = ra_stage_key($metadata);
    if ($family === 'Document Requests') {
        return !in_array($stage, ['completed', 'cancelled', 'rejected', 'interview_failed', 'inspection_failed'], true);
    }

    $statusKey = ra_status_key($statusName);
    return !in_array($statusKey, ['approved', 'verified', 'completed', 'done', 'rejected', 'denied', 'notverified', 'cancelled'], true);
}

function ra_resident_transaction_status(string $transactionType, string $statusName, ?array $metadata = null): array
{
    $family = ra_summary_family($transactionType);
    if ($family === 'Document Requests' && is_array($metadata)) {
        $stageLabel = trim((string)($metadata['stage_label'] ?? ''));
        $stage = ra_stage_key($metadata);
        $pill = 'pending';
        if (in_array($stage, ['completed'], true)) {
            $pill = 'approved';
        } elseif (in_array($stage, ['cancelled', 'rejected', 'interview_failed', 'inspection_failed'], true)) {
            $pill = 'archived';
        } elseif (in_array($stage, ['ready_for_claim', 'payment_verified'], true)) {
            $pill = 'info';
        }
        return [
            'label' => $stageLabel !== '' ? $stageLabel : (trim($statusName) !== '' ? trim($statusName) : 'Submitted'),
            'pill' => $pill,
        ];
    }

    return ra_status_meta('transaction', $statusName, []);
}

$residentUserId = (string)($_SESSION['user_id'] ?? '');
$summaryActivities = [];
$summaryTotalActivities = 0;
$summaryNeedsAttention = 0;
$summaryActiveTransactions = 0;
$summaryTrackedServices = 0;
$loadNotices = [];

if ($residentUserId !== '' && isset($conn) && $conn instanceof mysqli) {
    if (ra_table_exists($conn, 'residenttransactiontbl')) {
        $summarySql = "
            SELECT
                t.transaction_id,
                t.transaction_type,
                t.title,
                t.details,
                COALESCE(s.status_name, CONCAT('Status #', t.status_id)) AS status_name,
                t.metadata_json,
                t.created_at,
                t.updated_at,
                t.reviewed_at
            FROM residenttransactiontbl t
            LEFT JOIN statuslookuptbl s ON s.status_id = t.status_id
            WHERE (t.resident_user_id = ? OR t.user_id = ?)
            ORDER BY COALESCE(t.updated_at, t.reviewed_at, t.created_at) DESC, t.transaction_id DESC
        ";
        $stmtSummary = $conn->prepare($summarySql);
        if ($stmtSummary) {
            $stmtSummary->bind_param('ss', $residentUserId, $residentUserId);
            $stmtSummary->execute();
            $resultSummary = $stmtSummary->get_result();
            $trackedServices = [];

            while ($row = $resultSummary->fetch_assoc()) {
                $transactionType = (string)($row['transaction_type'] ?? '');
                $family = ra_summary_family($transactionType);
                if ($family === null) {
                    continue;
                }

                $metadata = null;
                $metadataJson = trim((string)($row['metadata_json'] ?? ''));
                if ($metadataJson !== '') {
                    $decoded = json_decode($metadataJson, true);
                    if (is_array($decoded)) {
                        $metadata = $decoded;
                    }
                }

                $statusMeta = ra_resident_transaction_status($transactionType, (string)($row['status_name'] ?? ''), $metadata);
                $timestamp = trim((string)($row['updated_at'] ?? ''));
                if ($timestamp === '') {
                    $timestamp = trim((string)($row['reviewed_at'] ?? ''));
                }
                if ($timestamp === '') {
                    $timestamp = trim((string)($row['created_at'] ?? ''));
                }

                $summaryActivities[] = [
                    'type' => $family,
                    'reference_id' => (string)($row['transaction_id'] ?? ''),
                    'title' => (string)($row['title'] ?? $family),
                    'status_label' => $statusMeta['label'],
                    'status_pill' => $statusMeta['pill'],
                    'timestamp' => $timestamp,
                    'timestamp_display' => ra_format_datetime($timestamp),
                    'description' => trim((string)($row['details'] ?? '')),
                ];

                $summaryTotalActivities++;
                if (ra_is_resident_attention_needed($transactionType, (string)($row['status_name'] ?? ''), $metadata)) {
                    $summaryNeedsAttention++;
                }
                if (ra_is_active_transaction($transactionType, (string)($row['status_name'] ?? ''), $metadata)) {
                    $summaryActiveTransactions++;
                }
                $trackedServices[$family] = true;
            }

            $summaryTrackedServices = count($trackedServices);
            $stmtSummary->close();
        } else {
            $loadNotices[] = 'Transaction summary could not be loaded right now.';
        }
    }

}

usort($summaryActivities, static function (array $a, array $b): int {
    return strcmp((string)($b['timestamp'] ?? ''), (string)($a['timestamp'] ?? ''));
});

$recentActivity = array_slice($summaryActivities, 0, 6);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Images/favicon_sanjose.png?v=20260211">
    <title>Resident Activity</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <style>
        body {
            background: #f8f9fa;
        }

        #div-mainDisplay {
            background: #f8f9fa !important;
        }

        .activity-shell {
            max-width: 1220px;
            margin: 0 auto;
        }

        .summary-card,
        .section-card {
            background: #ffffff;
            border: 1px solid #e3e6ea;
            border-radius: 20px;
            box-shadow: 0 12px 28px rgba(24, 31, 42, 0.08);
        }

        .txn-page-title {
            font-family: 'Charis SIL Bold', serif;
            color: #DE710C;
            font-size: clamp(2rem, 4.4vw, 3rem);
            line-height: 1.1;
            margin: 0 0 0.65rem 0;
        }

        .muted-copy {
            color: #5f6470;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .summary-card {
            padding: 1rem 1.1rem;
        }

        .summary-label {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #8b6a46;
            margin-bottom: 0.3rem;
        }

        .summary-value {
            font-size: 2rem;
            font-weight: 700;
            color: #222;
            line-height: 1;
        }

        .summary-subtext {
            color: #6c757d;
            margin-top: 0.45rem;
            font-size: 0.95rem;
        }

        .section-card {
            padding: 1.2rem;
            margin-top: 1.25rem;
        }

        .section-heading {
            color: #222;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 0.2rem;
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

        .status-pill.pending {
            color: #9a3412;
            background: #ffedd5;
            border-color: #fdba74;
        }

        .status-pill.approved {
            color: #166534;
            background: #dcfce7;
            border-color: #86efac;
        }

        .status-pill.archived {
            color: #991b1b;
            background: #fee2e2;
            border-color: #fca5a5;
        }

        .status-pill.info {
            color: #1d4ed8;
            background: #dbeafe;
            border-color: #93c5fd;
        }

        .activity-feed {
            display: grid;
            gap: 0.8rem;
            margin-top: 1rem;
        }

        .feed-item {
            border: 1px solid #e3e6ea;
            border-radius: 16px;
            padding: 0.95rem 1rem;
            background: #fffdfa;
        }

        .feed-title {
            font-weight: 700;
            color: #222;
        }

        .feed-meta {
            color: #6c757d;
            font-size: 0.92rem;
        }

        .table-wrap {
            overflow-x: auto;
            margin-top: 1rem;
        }

        .activity-table {
            width: 100%;
            min-width: 760px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .activity-table th,
        .activity-table td {
            padding: 0.9rem 0.85rem;
            vertical-align: top;
            border-bottom: 1px solid #e9ecef;
            text-align: left;
        }

        .activity-table th {
            color: #6b7280;
            font-size: 0.84rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .activity-table tr:last-child td {
            border-bottom: 0;
        }

        .appointments-table th:first-child,
        .appointments-table td:first-child {
            width: 190px;
            min-width: 190px;
            white-space: nowrap;
        }

        .appointments-table th:nth-child(2),
        .appointments-table td:nth-child(2) {
            width: auto;
        }

        .appointments-table th:nth-child(3),
        .appointments-table td:nth-child(3) {
            width: 260px;
            min-width: 260px;
        }

        .appointments-table td:nth-child(3) {
            white-space: nowrap;
        }

        .appointments-table td:nth-child(3) .feed-meta {
            white-space: nowrap;
            font-size: 0.76rem;
            line-height: 1.1;
        }

        .appointments-table th:nth-child(4),
        .appointments-table td:nth-child(4) {
            width: 150px;
            min-width: 150px;
            white-space: nowrap;
        }

        .empty-state {
            border: 1px dashed #ced4da;
            border-radius: 16px;
            padding: 1.25rem;
            color: #6c757d;
            background: #f8f9fa;
            margin-top: 1rem;
        }

        @media (max-width: 991px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="d-flex min-vh-100">
        <?php include __DIR__ . '/includes/resident_sidebar.php'; ?>

        <main id="div-mainDisplay" class="main-content flex-grow-1 p-4 p-md-5 bg-light">
            <div class="activity-shell">
                <h2 class="txn-page-title">Activity</h2>
                <hr class="mt-0 mb-3">

                <div class="summary-grid">
                    <article class="summary-card">
                        <div class="summary-label">Total Activities</div>
                        <div class="summary-value"><?= $summaryTotalActivities ?></div>
                        <div class="summary-subtext">All resident requests from documents, edits, sector membership, and profiling.</div>
                    </article>
                    <article class="summary-card">
                        <div class="summary-label">Needs Attention</div>
                        <div class="summary-value"><?= $summaryNeedsAttention ?></div>
                        <div class="summary-subtext">Transactions waiting for resident action like payment, claim, interview, inspection, or resubmission.</div>
                    </article>
                    <article class="summary-card">
                        <div class="summary-label">Active Transactions</div>
                        <div class="summary-value"><?= $summaryActiveTransactions ?></div>
                        <div class="summary-subtext">Requests that are still open and moving through the workflow.</div>
                    </article>
                    <article class="summary-card">
                        <div class="summary-label">Tracked Services</div>
                        <div class="summary-value"><?= $summaryTrackedServices ?></div>
                        <div class="summary-subtext">Distinct resident service groups currently tracked in your transaction history.</div>
                    </article>
                </div>

                <?php foreach ($loadNotices as $notice): ?>
                    <div class="alert alert-warning mt-3 mb-0" role="alert"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endforeach; ?>

                <section class="section-card">
                    <h2 class="section-heading">Recent Activity</h2>
                    <p class="muted-copy mb-0">Your latest resident transactions and service submissions appear here first.</p>

                    <?php if (empty($recentActivity)): ?>
                        <div class="empty-state">No activity records found yet. Once you submit a resident request, it will appear here.</div>
                    <?php else: ?>
                        <div class="activity-feed">
                            <?php foreach ($recentActivity as $item): ?>
                                <article class="feed-item">
                                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                        <div>
                                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                                <span class="feed-meta"><?= htmlspecialchars($item['type'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <span class="status-pill <?= htmlspecialchars($item['status_pill'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars($item['status_label'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </div>
                                            <div class="feed-title">
                                                <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                                <?php if ($item['reference_id'] !== ''): ?>
                                                    <span class="text-muted">#<?= htmlspecialchars($item['reference_id'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (($item['description'] ?? '') !== ''): ?>
                                                <div class="feed-meta"><?= htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="feed-meta"><?= htmlspecialchars($item['timestamp_display'], ENT_QUOTES, 'UTF-8') ?></div>
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
</body>
</html>
