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

$residentUserId = (string)($_SESSION['user_id'] ?? '');
$complaints = [];
$appointments = [];
$allActivities = [];
$loadNotices = [];

if ($residentUserId !== '' && isset($conn) && $conn instanceof mysqli) {
    if (
        ra_table_exists($conn, 'casereportstbl') &&
        ra_table_exists($conn, 'complaintstbl') &&
        ra_table_exists($conn, 'statuslookuptbl')
    ) {
        $complaintSql = "
            SELECT
                c.case_id,
                ct.complaint_id,
                c.report_timestamp,
                c.incident_date,
                c.incident_place,
                c.complaint_type,
                COALESCE(s.status_name, 'Pending') AS status_name,
                COALESCE(l.status_name, 'Complaint Only') AS level_name,
                ct.subject_display_name,
                ct.escalated_to_blotter,
                ct.blotter_id
            FROM casereportstbl c
            INNER JOIN complaintstbl ct ON ct.case_id = c.case_id
            LEFT JOIN statuslookuptbl s ON s.status_id = c.case_status_id
            LEFT JOIN statuslookuptbl l ON l.status_id = c.case_level_id
            WHERE c.report_type = 'Complaint'
              AND c.resident_user_id = ?
            ORDER BY c.report_timestamp DESC, c.case_id DESC
        ";

        $stmtComplaint = $conn->prepare($complaintSql);
        if ($stmtComplaint) {
            $stmtComplaint->bind_param('s', $residentUserId);
            $stmtComplaint->execute();
            $resultComplaint = $stmtComplaint->get_result();
            while ($row = $resultComplaint->fetch_assoc()) {
                $statusMeta = ra_status_meta('complaint', (string)($row['status_name'] ?? 'Pending'), $row);
                $complaint = [
                    'kind' => 'Complaint',
                    'title' => (string)($row['complaint_type'] ?? 'Complaint'),
                    'reference_id' => (string)($row['complaint_id'] ?? ''),
                    'secondary_id' => (string)($row['case_id'] ?? ''),
                    'subject' => (string)($row['subject_display_name'] ?? ''),
                    'location' => (string)($row['incident_place'] ?? ''),
                    'incident_date_display' => ra_format_date($row['incident_date'] ?? null),
                    'submitted_at_display' => ra_format_datetime($row['report_timestamp'] ?? null),
                    'level_name' => (string)($row['level_name'] ?? 'Complaint Only'),
                    'status_label' => $statusMeta['label'],
                    'status_pill' => $statusMeta['pill'],
                    'raw_timestamp' => (string)($row['report_timestamp'] ?? ''),
                    'blotter_id' => (string)($row['blotter_id'] ?? ''),
                ];
                $complaints[] = $complaint;
                $allActivities[] = [
                    'type' => 'Complaint',
                    'reference_id' => $complaint['reference_id'],
                    'title' => $complaint['title'],
                    'status_label' => $complaint['status_label'],
                    'status_pill' => $complaint['status_pill'],
                    'timestamp' => $complaint['raw_timestamp'],
                    'timestamp_display' => $complaint['submitted_at_display'],
                    'description' => $complaint['subject'] !== '' ? $complaint['subject'] : $complaint['location'],
                ];
            }
            $stmtComplaint->close();
        } else {
            $loadNotices[] = 'Complaint activity could not be loaded right now.';
        }
    }

    if (ra_table_exists($conn, 'appointmentstbl')) {
        $appointmentColumns = ra_table_columns($conn, 'appointmentstbl');
        $preferredScheduleSelect = isset($appointmentColumns['preferred_schedule_timestamp'])
            ? 'a.preferred_schedule_timestamp'
            : (isset($appointmentColumns['schedule_timestamp']) ? 'a.schedule_timestamp' : 'NULL');
        $confirmedScheduleSelect = isset($appointmentColumns['confirmed_schedule_timestamp'])
            ? 'a.confirmed_schedule_timestamp'
            : 'NULL';
        $reviewTimestampSelect = isset($appointmentColumns['review_timestamp'])
            ? 'a.review_timestamp'
            : 'NULL';
        $statusJoin = ra_table_exists($conn, 'statuslookuptbl')
            ? "LEFT JOIN statuslookuptbl s ON a.appointment_status_id = s.status_id"
            : '';
        $statusSelect = ra_table_exists($conn, 'statuslookuptbl')
            ? "COALESCE(s.status_name, 'Pending') AS status_name"
            : "'Pending' AS status_name";

        $appointmentSql = "
            SELECT
                a.appointment_id,
                a.subject,
                a.subject_other,
                a.purpose,
                {$preferredScheduleSelect} AS preferred_schedule_timestamp,
                {$confirmedScheduleSelect} AS confirmed_schedule_timestamp,
                {$reviewTimestampSelect} AS review_timestamp,
                a.request_timestamp,
                {$statusSelect}
            FROM appointmentstbl a
            {$statusJoin}
            WHERE a.user_id_resident = ?
            ORDER BY a.request_timestamp DESC, a.appointment_id DESC
        ";

        $stmtAppointment = $conn->prepare($appointmentSql);
        if ($stmtAppointment) {
            $stmtAppointment->bind_param('s', $residentUserId);
            $stmtAppointment->execute();
            $resultAppointment = $stmtAppointment->get_result();
            while ($row = $resultAppointment->fetch_assoc()) {
                $statusMeta = ra_status_meta('appointment', (string)($row['status_name'] ?? 'Pending'));
                $subject = trim((string)($row['subject'] ?? 'Appointment'));
                $subjectOther = trim((string)($row['subject_other'] ?? ''));
                if (strcasecmp($subject, 'Other') === 0 && $subjectOther !== '') {
                    $subject = 'Other: ' . $subjectOther;
                }

                $scheduleSource = trim((string)($row['confirmed_schedule_timestamp'] ?? '')) !== ''
                    ? (string)$row['confirmed_schedule_timestamp']
                    : (string)($row['preferred_schedule_timestamp'] ?? '');

                $appointment = [
                    'kind' => 'Appointment',
                    'reference_id' => (string)($row['appointment_id'] ?? ''),
                    'subject' => $subject !== '' ? $subject : 'Appointment',
                    'purpose' => trim((string)($row['purpose'] ?? '')),
                    'requested_at_display' => ra_format_datetime($row['request_timestamp'] ?? null),
                    'schedule_display' => ra_format_datetime($scheduleSource, 'To be scheduled'),
                    'reviewed_at_display' => ra_format_datetime($row['review_timestamp'] ?? null),
                    'status_label' => $statusMeta['label'],
                    'status_pill' => $statusMeta['pill'],
                    'raw_timestamp' => (string)($row['request_timestamp'] ?? ''),
                ];
                $appointments[] = $appointment;
                $allActivities[] = [
                    'type' => 'Appointment',
                    'reference_id' => $appointment['reference_id'],
                    'title' => $appointment['subject'],
                    'status_label' => $appointment['status_label'],
                    'status_pill' => $appointment['status_pill'],
                    'timestamp' => $appointment['raw_timestamp'],
                    'timestamp_display' => $appointment['requested_at_display'],
                    'description' => $appointment['schedule_display'],
                ];
            }
            $stmtAppointment->close();
        } else {
            $loadNotices[] = 'Appointment activity could not be loaded right now.';
        }
    }
}

usort($allActivities, static function (array $a, array $b): int {
    return strcmp((string)($b['timestamp'] ?? ''), (string)($a['timestamp'] ?? ''));
});

$pendingCount = 0;
foreach ($allActivities as $activity) {
    if (($activity['status_pill'] ?? '') === 'pending' || ($activity['status_pill'] ?? '') === 'info') {
        $pendingCount++;
    }
}

$recentActivity = array_slice($allActivities, 0, 6);
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
            grid-template-columns: repeat(3, minmax(0, 1fr));
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
            white-space: normal;
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
                        <div class="summary-value"><?= count($allActivities) ?></div>
                        <div class="summary-subtext">Combined complaint and appointment records.</div>
                    </article>
                    <article class="summary-card">
                        <div class="summary-label">Needs Attention</div>
                        <div class="summary-value"><?= $pendingCount ?></div>
                        <div class="summary-subtext">Pending or recently rescheduled items.</div>
                    </article>
                    <article class="summary-card">
                        <div class="summary-label">Tracked Services</div>
                        <div class="summary-value"><?= count($complaints) + count($appointments) > 0 ? 2 : 0 ?></div>
                        <div class="summary-subtext">Complaints and appointments only.</div>
                    </article>
                </div>

                <?php foreach ($loadNotices as $notice): ?>
                    <div class="alert alert-warning mt-3 mb-0" role="alert"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endforeach; ?>

                <section class="section-card">
                    <h2 class="section-heading">Recent Activity</h2>
                    <p class="muted-copy mb-0">Your latest submissions appear here first.</p>

                    <?php if (empty($recentActivity)): ?>
                        <div class="empty-state">No activity records found yet. Once you submit a complaint or an appointment, it will appear here.</div>
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

                <section class="section-card">
                    <h2 class="section-heading">Complaints</h2>
                    <p class="muted-copy mb-0">Complaint records submitted through your resident account.</p>

                    <?php if (empty($complaints)): ?>
                        <div class="empty-state">No complaint activity yet.</div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="activity-table">
                                <thead>
                                    <tr>
                                        <th>Complaint ID</th>
                                        <th>Type</th>
                                        <th>Subject</th>
                                        <th>Incident Date</th>
                                        <th>Submitted</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($complaints as $complaint): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($complaint['reference_id'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                                                <span class="feed-meta">Case <?= htmlspecialchars($complaint['secondary_id'], ENT_QUOTES, 'UTF-8') ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($complaint['title'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <?= htmlspecialchars($complaint['subject'] !== '' ? $complaint['subject'] : '-', ENT_QUOTES, 'UTF-8') ?><br>
                                                <span class="feed-meta"><?= htmlspecialchars($complaint['location'] !== '' ? $complaint['location'] : 'No location noted', ENT_QUOTES, 'UTF-8') ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($complaint['incident_date_display'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($complaint['submitted_at_display'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <span class="status-pill <?= htmlspecialchars($complaint['status_pill'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars($complaint['status_label'], ENT_QUOTES, 'UTF-8') ?>
                                                </span><br>
                                                <span class="feed-meta">
                                                    <?= htmlspecialchars($complaint['level_name'], ENT_QUOTES, 'UTF-8') ?>
                                                    <?php if ($complaint['blotter_id'] !== ''): ?>
                                                        | Blotter <?= htmlspecialchars($complaint['blotter_id'], ENT_QUOTES, 'UTF-8') ?>
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="section-card">
                    <h2 class="section-heading">Appointments</h2>
                    <p class="muted-copy mb-0">Appointment requests you submitted to the barangay office.</p>

                    <?php if (empty($appointments)): ?>
                        <div class="empty-state">No appointment activity yet.</div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="activity-table appointments-table">
                                <thead>
                                    <tr>
                                        <th><span style="white-space: nowrap;">Appointment ID</span></th>
                                        <th>Subject</th>
                                        <th>Schedule</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $appointment): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($appointment['reference_id'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                            <td>
                                                <?= htmlspecialchars($appointment['subject'], ENT_QUOTES, 'UTF-8') ?><br>
                                                <span class="feed-meta">
                                                    <?= htmlspecialchars($appointment['purpose'] !== '' ? $appointment['purpose'] : 'No purpose noted', ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($appointment['schedule_display'], ENT_QUOTES, 'UTF-8') ?><br>
                                                <span class="feed-meta">
                                                    Reviewed: <?= htmlspecialchars($appointment['reviewed_at_display'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-pill <?= htmlspecialchars($appointment['status_pill'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars($appointment['status_label'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
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
