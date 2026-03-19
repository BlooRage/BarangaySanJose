<?php
require_once __DIR__ . "/../../PhpFiles/General/connection.php";
require_once __DIR__ . "/../includes/admin_guard.php";

function at_table_columns(mysqli $conn, string $tableName): array
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

    $stmt->bind_param("s", $tableName);
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

function at_value(array $row, string $key, string $default = ''): string
{
    if (!array_key_exists($key, $row) || $row[$key] === null) {
        return $default;
    }
    $value = trim((string)$row[$key]);
    return $value === '' ? $default : $value;
}

function at_status_key(string $value): string
{
    $normalized = strtolower(trim($value));
    if ($normalized === '') {
        return 'pending';
    }
    if (str_contains($normalized, 'approve')) {
        return 'approved';
    }
    if (str_contains($normalized, 'deny') || str_contains($normalized, 'denied') || str_contains($normalized, 'reject')) {
        return 'denied';
    }
    if (str_contains($normalized, 'resched')) {
        return 'approved';
    }
    if (str_contains($normalized, 'complete') || str_contains($normalized, 'done')) {
        return 'approved';
    }
    return 'pending';
}

function at_status_label(string $value): string
{
    $key = at_status_key($value);
    if ($key === 'approved') {
        return 'Approved';
    }
    if ($key === 'denied') {
        return 'Denied';
    }
    return 'Pending';
}

function at_status_pill(string $value): string
{
    $key = at_status_key($value);
    if ($key === 'approved') {
        return 'approved';
    }
    if ($key === 'denied') {
        return 'denied';
    }
    return 'pending';
}

function at_format_datetime(?string $value, string $fallback = '-'): string
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

function at_format_date(?string $value, string $fallback = '-'): string
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

function at_format_time(?string $value, string $fallback = '-'): string
{
    $text = trim((string)$value);
    if ($text === '') {
        return $fallback;
    }
    $timestamp = strtotime($text);
    if ($timestamp === false) {
        return $text;
    }
    return date('h:i A', $timestamp);
}

function at_flash_message(string $key): string
{
    return trim((string)($_GET[$key] ?? ''));
}

$appointmentRows = [];
$loadError = '';
$appointmentSuccessMessage = at_flash_message('success');
$appointmentErrorMessage = at_flash_message('error');
$highlightAppointmentId = at_flash_message('appointment_id');
$officialOptions = [];

if (!$conn->query("SHOW TABLES LIKE 'appointmentstbl'")->num_rows) {
    $loadError = 'Appointment table is not available. Run the appointment migration first.';
} else {
    $appointmentColumns = at_table_columns($conn, 'appointmentstbl');
    $hasPreferredSchedule = isset($appointmentColumns['preferred_schedule_timestamp']);
    $hasConfirmedSchedule = isset($appointmentColumns['confirmed_schedule_timestamp']);
    $hasReviewTimestamp = isset($appointmentColumns['review_timestamp']);
    $hasAssignedOfficial = isset($appointmentColumns['user_id_official_assigned']);

    $preferredScheduleSelect = $hasPreferredSchedule
        ? 'a.preferred_schedule_timestamp'
        : (isset($appointmentColumns['schedule_timestamp']) ? 'a.schedule_timestamp' : 'NULL');
    $confirmedScheduleSelect = $hasConfirmedSchedule ? 'a.confirmed_schedule_timestamp' : 'NULL';
    $reviewTimestampSelect = $hasReviewTimestamp ? 'a.review_timestamp' : 'NULL';
    $assignedOfficialSelect = $hasAssignedOfficial ? 'a.user_id_official_assigned' : 'NULL';
    $assignedOfficialJoin = $hasAssignedOfficial
        ? "LEFT JOIN officialinformationtbl official
            ON a.user_id_official_assigned = official.user_id"
        : '';
    $assignedOfficialNameSelect = $hasAssignedOfficial
        ? "CONCAT_WS(' ', official.firstname, official.lastname) AS official_name"
        : "NULL AS official_name";

    $sql = "
        SELECT
            a.appointment_id,
            a.name,
            a.contact_number,
            a.subject,
            a.subject_other,
            a.purpose,
            {$preferredScheduleSelect} AS preferred_schedule_timestamp,
            {$confirmedScheduleSelect} AS confirmed_schedule_timestamp,
            a.request_timestamp,
            a.resident_notes,
            a.appointment_remarks,
            {$reviewTimestampSelect} AS review_timestamp,
            COALESCE(s.status_name, 'Pending') AS status_name,
            staff.user_id AS staff_user_id,
            CONCAT_WS(' ', staff.firstname, staff.lastname) AS staff_name,
            {$assignedOfficialSelect} AS official_user_id,
            {$assignedOfficialNameSelect}
        FROM appointmentstbl a
        LEFT JOIN statuslookuptbl s
            ON a.appointment_status_id = s.status_id
        LEFT JOIN officialinformationtbl staff
            ON a.user_id_employee_staff = staff.user_id
        {$assignedOfficialJoin}
        ORDER BY a.request_timestamp DESC, a.appointment_id DESC
    ";

    $result = $conn->query($sql);
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $statusName = at_status_label((string)($row['status_name'] ?? 'Pending'));
            $subject = at_value($row, 'subject', '-');
            $subjectOther = at_value($row, 'subject_other', '');
            if (strcasecmp($subject, 'Other') === 0 && $subjectOther !== '') {
                $subject = 'Other: ' . $subjectOther;
            }

            $appointmentRows[] = [
                'appointment_id' => at_value($row, 'appointment_id', '-'),
                'request_timestamp' => at_value($row, 'request_timestamp', ''),
                'request_timestamp_display' => at_format_datetime($row['request_timestamp'] ?? null),
                'resident_name' => at_value($row, 'name', '-'),
                'contact_number' => at_value($row, 'contact_number', '-'),
                'subject' => $subject,
                'purpose' => at_value($row, 'purpose', '-'),
                'preferred_schedule_timestamp' => at_value($row, 'preferred_schedule_timestamp', ''),
                'preferred_appointment_date' => at_format_date($row['preferred_schedule_timestamp'] ?? null),
                'preferred_appointment_time' => at_format_time($row['preferred_schedule_timestamp'] ?? null),
                'confirmed_schedule_timestamp' => at_value($row, 'confirmed_schedule_timestamp', ''),
                'confirmed_appointment_date' => at_format_date($row['confirmed_schedule_timestamp'] ?? null),
                'confirmed_appointment_time' => at_format_time($row['confirmed_schedule_timestamp'] ?? null),
                'status_name' => $statusName,
                'status_key' => at_status_key($statusName),
                'status_pill' => at_status_pill($statusName),
                'staff_name' => at_value($row, 'staff_name', '-'),
                'official_user_id' => at_value($row, 'official_user_id', ''),
                'official_name' => at_value($row, 'official_name', '-'),
                'resident_notes' => at_value($row, 'resident_notes', '-'),
                'appointment_remarks' => at_value($row, 'appointment_remarks', '-'),
                'review_timestamp_display' => at_format_datetime($row['review_timestamp'] ?? null),
            ];
        }
        $result->free();
    } else {
        $loadError = 'Unable to load appointment records.';
    }
}

if ($conn->query("SHOW TABLES LIKE 'officialinformationtbl'")->num_rows) {
    $hasPositionAccess = $conn->query("SHOW COLUMNS FROM officialinformationtbl LIKE 'position_access'");
    $positionField = ($hasPositionAccess instanceof mysqli_result && $hasPositionAccess->num_rows > 0)
        ? 'oi.position_access'
        : 'oi.role_access';

    $officialsSql = "
        SELECT
            oi.user_id,
            CONCAT_WS(' ', oi.firstname, oi.lastname) AS full_name,
            {$positionField} AS position_access,
            oi.department,
            COALESCE(sa.status_name, '') AS account_status
        FROM officialinformationtbl oi
        LEFT JOIN useraccountstbl ua
            ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
        LEFT JOIN statuslookuptbl sa
            ON sa.status_id = ua.status_id_account
        ORDER BY oi.firstname ASC, oi.lastname ASC
    ";
    $officialsResult = $conn->query($officialsSql);
    if ($officialsResult instanceof mysqli_result) {
        while ($official = $officialsResult->fetch_assoc()) {
            $accountStatus = strtolower(trim((string)($official['account_status'] ?? '')));
            if ($accountStatus !== '' && preg_match('/inactive|revoked|suspended|disabled/', $accountStatus)) {
                continue;
            }

            $officialOptions[] = [
                'user_id' => trim((string)($official['user_id'] ?? '')),
                'full_name' => trim((string)($official['full_name'] ?? '')),
                'position_access' => trim((string)($official['position_access'] ?? '')),
                'department' => trim((string)($official['department'] ?? '')),
            ];
        }
        $officialsResult->free();
    }
}

$pendingCount = 0;
$approvedCount = 0;
$deniedCount = 0;
foreach ($appointmentRows as $row) {
    if ($row['status_key'] === 'pending') {
        $pendingCount++;
    } elseif ($row['status_key'] === 'approved') {
        $approvedCount++;
    } elseif ($row['status_key'] === 'denied') {
        $deniedCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../../Images/favicon_sanjose.png?v=20260211">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Tracker</title>

    <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
    <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260227-2">
    <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/BlotterMangementStyle.css?v=20260305-1">
    <style>
        .appointment-tracker-shell {
            max-width: 1340px;
            margin: 0 auto;
        }

        #viewModal .modal-content {
            border: 1px solid #e9ecef;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }

        #viewModal .modal-header,
        #viewModal .modal-body,
        #viewModal .modal-footer {
            padding: 1rem 1.25rem;
        }

        #viewModal .tracker-profile-view {
            display: grid;
            gap: 12px;
        }

        #viewModal .modal-body {
            background: #fff;
        }

        #table-appointmentData th,
        #table-appointmentData td {
            text-align: left !important;
            vertical-align: middle;
        }

        #viewModal .tracker-form-section {
            border-color: #e78924;
            margin-top: 0;
            display: grid;
            gap: 12px;
        }

        #viewModal .tracker-form-section-title,
        #viewModal .tracker-form-label {
            margin: 0;
        }

        #viewModal .tracker-form-grid {
            display: grid;
            gap: 12px;
        }

        #viewModal .tracker-form-grid.cols-1 {
            grid-template-columns: minmax(0, 1fr);
        }

        #viewModal .tracker-form-grid.cols-2,
        #viewModal .tracker-form-grid:not(.cols-1):not(.cols-3):not(.cols-4) {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        #viewModal .tracker-form-grid.cols-3 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        #viewModal .tracker-form-grid.cols-4 {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        #viewModal .tracker-form-field {
            display: grid;
            gap: 6px;
            align-content: start;
        }

        #viewModal .tracker-form-label {
            line-height: 1.2;
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 600;
        }

        #viewModal .tracker-form-value {
            min-height: 46px;
            padding: 10px 12px;
            border: 1px solid #d8dee5;
            border-radius: 12px;
            background: #f8f9fa;
            line-height: 1.45;
            word-break: break-word;
        }

        #viewModal .tracker-form-section > .tracker-form-grid + .tracker-form-grid,
        #viewModal .tracker-form-section > .tracker-form-grid + .tracker-form-field,
        #viewModal .tracker-form-section > .tracker-form-field + .tracker-form-grid,
        #viewModal .tracker-form-section > .tracker-form-field + .tracker-form-field {
            margin-top: 0;
        }

        @media (max-width: 991.98px) {
            #viewModal .tracker-form-grid.cols-4,
            #viewModal .tracker-form-grid.cols-3 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            #viewModal .tracker-form-grid,
            #viewModal .tracker-form-grid.cols-4,
            #viewModal .tracker-form-grid.cols-3,
            #viewModal .tracker-form-grid.cols-2 {
                grid-template-columns: minmax(0, 1fr);
            }
        }
    </style>
</head>
<body>
<div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
        <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C;">Appointment Tracker</h2>
        <hr><br>

        <div id="div-tableContainer" class="bg-white p-4 rounded-4 shadow-sm border appointment-tracker-shell resident-masterlist-shell">
            <div class="admin-list-toolbar mb-3 pt-2 flex-wrap">
                <div class="admin-list-tabs">
                    <button class="btn btn-outline-primary btn-sm status-filter-btn active" type="button" data-filter="">&nbsp;&nbsp;All&nbsp;&nbsp;</button>
                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold" type="button" data-filter="approved">&nbsp;&nbsp;Approved&nbsp;&nbsp;</button>
                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold" type="button" data-filter="denied">&nbsp;&nbsp;Denied&nbsp;&nbsp;</button>
                    <button class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold has-notif" type="button" data-filter="pending">
                        &nbsp;&nbsp;Pending
                        <span class="pending-count-badge <?= $pendingCount > 0 ? '' : 'd-none' ?>" id="pendingAppointmentBadge"><?= (int)$pendingCount ?></span>
                    </button>
                </div>

                <div class="admin-list-actions">
                    <div class="input-group admin-search">
                        <input type="text" id="searchInput" class="form-control" placeholder="Appointment ID, resident, subject, purpose">
                        <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                    </div>
                    <button class="btn btn-outline-secondary btn-icon" type="button" data-bs-toggle="modal" data-bs-target="#modalTableColumns" id="btnAppointmentColumns" title="Columns" aria-label="Columns">
                        <i class="fa-solid fa-sliders"></i>
                        <span class="visually-hidden">Columns</span>
                    </button>
                    <button class="btn btn-outline-secondary btn-icon" type="button" id="btnAppointmentTableRefresh" title="Refresh table" aria-label="Refresh table">
                        <i class="fa-solid fa-arrows-rotate"></i>
                        <span class="visually-hidden">Refresh</span>
                    </button>
                </div>
            </div>

            <div class="table-responsive compact-admin-table-shell">
                <table id="table-appointmentData" class="table align-middle compact-admin-table">
                    <thead>
                        <tr class="table-light">
                            <th>Appointment ID</th>
                            <th>Date Submitted</th>
                            <th>Resident</th>
                            <th>Subject</th>
                            <th>Preferred Schedule</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php if ($loadError !== ''): ?>
                            <tr>
                                <td colspan="7" class="text-start text-muted py-4"><?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php elseif ($appointmentRows === []): ?>
                            <tr>
                                <td colspan="7" class="text-start text-muted py-4">No appointment records found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($appointmentRows as $row): ?>
                                <tr
                                    class="<?= $highlightAppointmentId !== '' && $highlightAppointmentId === $row['appointment_id'] ? 'table-warning' : '' ?>"
                                    data-status="<?= htmlspecialchars($row['status_key'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-search="<?= htmlspecialchars(strtolower(implode(' ', [
                                        $row['appointment_id'],
                                        $row['resident_name'],
                                        $row['subject'],
                                        $row['purpose'],
                                        $row['status_name'],
                                    ])), ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    <td><?= htmlspecialchars($row['appointment_id'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($row['request_timestamp_display'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($row['resident_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($row['subject'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($row['preferred_appointment_date'] . ' ' . $row['preferred_appointment_time'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><span class="status-pill <?= htmlspecialchars($row['status_pill'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($row['status_name'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td>
                                        <button
                                            class="btn btn-sm btn-outline-secondary"
                                            type="button"
                                            data-bs-toggle="modal"
                                            data-bs-target="#viewModal"
                                            data-appointment-id="<?= htmlspecialchars($row['appointment_id'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-request-timestamp="<?= htmlspecialchars($row['request_timestamp_display'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-resident-name="<?= htmlspecialchars($row['resident_name'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-contact-number="<?= htmlspecialchars($row['contact_number'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-subject="<?= htmlspecialchars($row['subject'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-purpose="<?= htmlspecialchars($row['purpose'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-preferred-appointment-date="<?= htmlspecialchars($row['preferred_appointment_date'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-preferred-appointment-time="<?= htmlspecialchars($row['preferred_appointment_time'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-confirmed-appointment-date="<?= htmlspecialchars($row['confirmed_appointment_date'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-confirmed-appointment-time="<?= htmlspecialchars($row['confirmed_appointment_time'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-preferred-schedule-timestamp="<?= htmlspecialchars($row['preferred_schedule_timestamp'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-confirmed-schedule-timestamp="<?= htmlspecialchars($row['confirmed_schedule_timestamp'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-status-name="<?= htmlspecialchars($row['status_name'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-staff-name="<?= htmlspecialchars($row['staff_name'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-official-user-id="<?= htmlspecialchars($row['official_user_id'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-official-name="<?= htmlspecialchars($row['official_name'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-resident-notes="<?= htmlspecialchars($row['resident_notes'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-appointment-remarks="<?= htmlspecialchars($row['appointment_remarks'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-review-timestamp="<?= htmlspecialchars($row['review_timestamp_display'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-status-key="<?= htmlspecialchars($row['status_key'], ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                            View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="resident-table-footer mt-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <label for="entriesPerPageInput" class="small text-muted mb-0">Entries</label>
                    <input id="entriesPerPageInput" type="number" min="1" step="1" value="20" class="form-control form-control-sm resident-entries-input">
                </div>
                <nav aria-label="Appointment pagination">
                    <ul class="pagination pagination-sm mb-0" id="appointmentPagination"></ul>
                </nav>
            </div>
        </div>
    </main>
</div>

<div class="modal fade" id="modalTableColumns" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Columns</h5>
            </div>
            <div class="modal-body">
                <div class="row g-2" id="tableColumnsList"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="btnTableColumnsReset">Reset</button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="appointmentFeedbackModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="appointmentFeedbackModalTitle">Appointment Update</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="appointmentFeedbackModalMessage">-</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade tracker-profile-modal" id="viewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width: 1500px; width: 75vw;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="viewDetailsBody" class="tracker-profile-view">
                    <section class="tracker-form-section">
                        <h6 class="tracker-form-section-title">Appointment Summary</h6>
                        <div class="tracker-form-grid cols-4">
                            <div class="tracker-form-field"><p class="tracker-form-label">Appointment ID</p><div class="tracker-form-value" id="viewAppointmentId">-</div></div>
                            <div class="tracker-form-field"><p class="tracker-form-label">Date Submitted</p><div class="tracker-form-value" id="viewRequestTimestamp">-</div></div>
                            <div class="tracker-form-field"><p class="tracker-form-label">Status</p><div class="tracker-form-value" id="viewStatusName">-</div></div>
                            <div class="tracker-form-field"><p class="tracker-form-label">Review Timestamp</p><div class="tracker-form-value" id="viewReviewTimestamp">-</div></div>
                        </div>
                    </section>

                    <section class="tracker-form-section">
                        <h6 class="tracker-form-section-title">Resident and Assignment</h6>
                        <div class="tracker-form-grid cols-4">
                            <div class="tracker-form-field"><p class="tracker-form-label">Resident Name</p><div class="tracker-form-value" id="viewResidentName">-</div></div>
                            <div class="tracker-form-field"><p class="tracker-form-label">Contact Number</p><div class="tracker-form-value" id="viewContactNumber">-</div></div>
                            <div class="tracker-form-field"><p class="tracker-form-label">Reviewed By Staff</p><div class="tracker-form-value" id="viewStaffName">-</div></div>
                            <div class="tracker-form-field"><p class="tracker-form-label">Assigned Official</p><div class="tracker-form-value" id="viewOfficialName">-</div></div>
                        </div>
                    </section>

                    <section class="tracker-form-section">
                        <h6 class="tracker-form-section-title">Appointment Details</h6>
                        <div class="tracker-form-grid cols-1">
                            <div class="tracker-form-field"><p class="tracker-form-label">Subject</p><div class="tracker-form-value" id="viewSubject">-</div></div>
                        </div>
                        <div class="tracker-form-grid cols-2">
                            <div class="tracker-form-field"><p class="tracker-form-label">Preferred Date</p><div class="tracker-form-value" id="viewPreferredAppointmentDate">-</div></div>
                            <div class="tracker-form-field"><p class="tracker-form-label">Preferred Time</p><div class="tracker-form-value" id="viewPreferredAppointmentTime">-</div></div>
                        </div>
                        <div class="tracker-form-grid cols-2">
                            <div class="tracker-form-field"><p class="tracker-form-label">Confirmed Date</p><div class="tracker-form-value" id="viewConfirmedAppointmentDate">-</div></div>
                            <div class="tracker-form-field"><p class="tracker-form-label">Confirmed Time</p><div class="tracker-form-value" id="viewConfirmedAppointmentTime">-</div></div>
                        </div>
                        <div class="tracker-form-grid cols-1">
                            <div class="tracker-form-field"><p class="tracker-form-label">Purpose</p><div class="tracker-form-value" id="viewPurpose">-</div></div>
                        </div>
                    </section>

                    <section class="tracker-form-section">
                        <h6 class="tracker-form-section-title">Notes</h6>
                        <div class="tracker-form-grid cols-1">
                            <div class="tracker-form-field"><p class="tracker-form-label">Resident Notes</p><div class="tracker-form-value" id="viewResidentNotes">-</div></div>
                            <div class="tracker-form-field"><p class="tracker-form-label">Appointment Remarks</p><div class="tracker-form-value" id="viewAppointmentRemarks">-</div></div>
                        </div>
                    </section>

                    <section class="tracker-form-section">
                        <h6 class="tracker-form-section-title">Review Action</h6>
                        <div class="tracker-form-value d-none" id="reviewLockedMessage">
                            This appointment has already been reviewed. Status changes can no longer be modified.
                        </div>
                        <form method="post" action="<?= htmlspecialchars(appUrl('/PhpFiles/Admin-End/appointmentManagement.php'), ENT_QUOTES, 'UTF-8') ?>" id="appointmentReviewForm" class="tracker-profile-view">
                            <?= csrfTokenField() ?>
                            <input type="hidden" name="appointment_id" id="reviewAppointmentId" value="">
                            <input type="hidden" name="action" id="reviewActionInput" value="">

                            <div class="tracker-form-grid cols-3">
                                <div class="tracker-form-field">
                                    <label class="tracker-form-label" for="reviewOfficialUserId">Assigned Official</label>
                                    <select class="form-select" name="official_user_id" id="reviewOfficialUserId">
                                        <option value="">Select official</option>
                                        <?php foreach ($officialOptions as $official): ?>
                                            <?php
                                            $officialLabel = $official['full_name'] !== '' ? $official['full_name'] : $official['user_id'];
                                            $officialMeta = trim(implode(' | ', array_filter([
                                                $official['position_access'],
                                                $official['department'],
                                            ], static fn($value) => $value !== '')));
                                            if ($officialMeta !== '') {
                                                $officialLabel .= ' - ' . $officialMeta;
                                            }
                                            ?>
                                            <option value="<?= htmlspecialchars($official['user_id'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($officialLabel, ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="tracker-form-field">
                                    <label class="tracker-form-label" for="reviewConfirmedDate">Confirmed Date</label>
                                    <input class="form-control" type="date" name="confirmed_date" id="reviewConfirmedDate">
                                </div>
                                <div class="tracker-form-field">
                                    <label class="tracker-form-label" for="reviewConfirmedTime">Confirmed Time</label>
                                    <input class="form-control" type="time" name="confirmed_time" id="reviewConfirmedTime" min="09:01" max="16:59">
                                </div>
                            </div>

                            <div class="tracker-form-grid cols-1">
                                <div class="tracker-form-field">
                                    <label class="tracker-form-label" for="reviewAppointmentRemarks">Review Remarks</label>
                                    <textarea class="form-control" name="appointment_remarks" id="reviewAppointmentRemarks" rows="3" placeholder="Add approval, denial, or reschedule notes"></textarea>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-2 justify-content-end">
                                <button type="button" class="btn btn-outline-danger" data-review-action="deny_appointment">Deny</button>
                                <button type="button" class="btn btn-outline-warning" data-review-action="reschedule_appointment">Reschedule</button>
                                <button type="button" class="btn btn-success" data-review-action="approve_appointment">Approve</button>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="appointmentActionConfirmModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="appointmentActionConfirmTitle">Confirm Appointment Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2" id="appointmentActionConfirmMessage">This action cannot be undone.</p>
                <p class="mb-0 text-muted small">Once you continue, the appointment status will be finalized and can no longer be changed.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="appointmentActionReturnBtn">Return</button>
                <button type="button" class="btn btn-primary" id="appointmentActionConfirmBtn">Continue</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    window.ADMIN_TABLE_COLUMNS_CONFIG = {
        tableSelector: "#table-appointmentData",
        modalId: "modalTableColumns",
        listId: "tableColumnsList",
        resetBtnId: "btnTableColumnsReset",
        storageKey: "admin_cols_appointment_tracker_v1",
        defaultHiddenIdxs: []
    };
</script>
<script src="../../JS-Script-Files/Admin-End/tableColumnsGeneric.js?v=20260215-1"></script>
<script>
    (() => {
        const feedbackSuccessMessage = <?= json_encode($appointmentSuccessMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const feedbackErrorMessage = <?= json_encode($appointmentErrorMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const tableBody = document.getElementById("tableBody");
        const searchInput = document.getElementById("searchInput");
        const entriesPerPageInput = document.getElementById("entriesPerPageInput");
        const paginationEl = document.getElementById("appointmentPagination");
        const refreshBtn = document.getElementById("btnAppointmentTableRefresh");
        const pendingBadge = document.getElementById("pendingAppointmentBadge");
        const filterButtons = Array.from(document.querySelectorAll(".status-filter-btn"));
        const reviewForm = document.getElementById("appointmentReviewForm");
        const reviewActionInput = document.getElementById("reviewActionInput");
        const reviewAppointmentId = document.getElementById("reviewAppointmentId");
        const reviewOfficialUserId = document.getElementById("reviewOfficialUserId");
        const reviewConfirmedDate = document.getElementById("reviewConfirmedDate");
        const reviewConfirmedTime = document.getElementById("reviewConfirmedTime");
        const reviewRemarks = document.getElementById("reviewAppointmentRemarks");
        const reviewActionButtons = Array.from(document.querySelectorAll("[data-review-action]"));
        const reviewLockedMessage = document.getElementById("reviewLockedMessage");
        const feedbackModalEl = document.getElementById("appointmentFeedbackModal");
        const feedbackModalTitle = document.getElementById("appointmentFeedbackModalTitle");
        const feedbackModalMessage = document.getElementById("appointmentFeedbackModalMessage");
        const modal = document.getElementById("viewModal");
        const confirmModalEl = document.getElementById("appointmentActionConfirmModal");
        const confirmModalTitle = document.getElementById("appointmentActionConfirmTitle");
        const confirmModalMessage = document.getElementById("appointmentActionConfirmMessage");
        const confirmModalReturnBtn = document.getElementById("appointmentActionReturnBtn");
        const confirmModalConfirmBtn = document.getElementById("appointmentActionConfirmBtn");
        const viewModalInstance = modal ? bootstrap.Modal.getOrCreateInstance(modal) : null;
        const confirmModalInstance = confirmModalEl ? bootstrap.Modal.getOrCreateInstance(confirmModalEl) : null;

        let allRows = Array.from(tableBody?.querySelectorAll("tr") || []).filter((row) => row.dataset.status !== undefined);
        let currentPage = 1;
        let activeFilter = "";
        let pendingReviewAction = "";
        const AUTO_REFRESH_SECONDS = 15;
        let autoRefreshSecondsLeft = AUTO_REFRESH_SECONDS;
        let autoRefreshInterval = null;
        let autoRefreshInFlight = false;

        function setRefreshLoading(on) {
            if (!refreshBtn) return;
            refreshBtn.classList.toggle("is-loading", !!on);
            refreshBtn.disabled = !!on;
        }

        function updatePendingBadge() {
            if (!pendingBadge) return;
            const count = allRows.filter((row) => String(row.dataset.status || "").trim().toLowerCase() === "pending").length;
            pendingBadge.textContent = String(count);
            pendingBadge.classList.toggle("d-none", count <= 0);
        }

        async function refreshTableOnly() {
            if (autoRefreshInFlight || !tableBody) return;
            autoRefreshInFlight = true;
            autoRefreshSecondsLeft = AUTO_REFRESH_SECONDS;
            setRefreshLoading(true);
            try {
                const response = await fetch(window.location.href, {
                    credentials: "same-origin",
                    headers: { "X-Requested-With": "XMLHttpRequest" }
                });
                const html = await response.text();
                if (!response.ok) {
                    throw new Error("Failed to refresh appointments.");
                }
                const doc = new DOMParser().parseFromString(html, "text/html");
                const nextBody = doc.getElementById("tableBody");
                if (!nextBody) {
                    throw new Error("Refreshed appointment table was not found.");
                }
                tableBody.innerHTML = nextBody.innerHTML;
                allRows = Array.from(tableBody.querySelectorAll("tr")).filter((row) => row.dataset.status !== undefined);
                updatePendingBadge();
                renderTable();
            } catch (error) {
                console.error("Unable to refresh appointment tracker:", error);
            } finally {
                autoRefreshInFlight = false;
                setRefreshLoading(false);
            }
        }

        function triggerRefresh() {
            refreshTableOnly().catch(() => {});
        }

        function startAutoRefresh() {
            if (autoRefreshInterval) window.clearInterval(autoRefreshInterval);
            autoRefreshInterval = window.setInterval(() => {
                if (autoRefreshInFlight) return;
                autoRefreshSecondsLeft -= 1;
                if (autoRefreshSecondsLeft <= 0) {
                    triggerRefresh();
                }
            }, 1000);
        }

        function renderPagination(total) {
            if (!paginationEl) return;
            const perPage = Math.max(1, Number(entriesPerPageInput?.value || 20));
            const totalPages = Math.max(1, Math.ceil(total / perPage));
            currentPage = Math.min(currentPage, totalPages);

            const makeBtn = (label, page, disabled = false, active = false) => `
                <li class="page-item ${disabled ? "disabled" : ""} ${active ? "active" : ""}">
                    <button class="page-link" data-page="${page}" ${disabled ? "disabled" : ""}>${label}</button>
                </li>
            `;

            const html = [];
            html.push(makeBtn("Prev", currentPage - 1, currentPage <= 1));
            for (let page = 1; page <= totalPages; page += 1) {
                html.push(makeBtn(String(page), page, false, page === currentPage));
            }
            html.push(makeBtn("Next", currentPage + 1, currentPage >= totalPages));
            paginationEl.innerHTML = html.join("");

            paginationEl.querySelectorAll("button[data-page]").forEach((button) => {
                button.addEventListener("click", () => {
                    const page = Number(button.getAttribute("data-page") || 1);
                    if (!Number.isFinite(page)) return;
                    currentPage = page;
                    renderTable();
                });
            });
        }

        function getFilteredRows() {
            const term = String(searchInput?.value || "").trim().toLowerCase();
            return allRows.filter((row) => {
                const matchesFilter = !activeFilter || String(row.dataset.status || "").trim().toLowerCase() === activeFilter;
                if (!matchesFilter) return false;

                if (!term) return true;
                return String(row.dataset.search || "").includes(term);
            });
        }

        function renderTable() {
            const filteredRows = getFilteredRows();
            const perPage = Math.max(1, Number(entriesPerPageInput?.value || 20));
            const start = (currentPage - 1) * perPage;
            const end = start + perPage;

            allRows.forEach((row) => {
                row.style.display = "none";
            });

            filteredRows.slice(start, end).forEach((row) => {
                row.style.display = "";
            });

            renderPagination(filteredRows.length);
        }

        function setFilterButtonState(activeValue) {
            filterButtons.forEach((button) => {
                const isActive = String(button.dataset.filter || "") === activeValue;
                button.classList.toggle("active", isActive);
                button.classList.toggle("btn-outline-primary", isActive);
                button.classList.toggle("btn-outline-secondary", !isActive);
            });
        }

        filterButtons.forEach((button) => {
            button.addEventListener("click", () => {
                activeFilter = String(button.dataset.filter || "").trim().toLowerCase();
                currentPage = 1;
                setFilterButtonState(String(button.dataset.filter || ""));
                renderTable();
            });
        });

        searchInput?.addEventListener("input", () => {
            currentPage = 1;
            renderTable();
        });

        entriesPerPageInput?.addEventListener("change", () => {
            currentPage = 1;
            renderTable();
        });

        refreshBtn?.addEventListener("click", triggerRefresh);

        modal?.addEventListener("show.bs.modal", (event) => {
            const button = event.relatedTarget;
            if (!(button instanceof HTMLElement)) return;

            const setText = (id, value) => {
                const element = document.getElementById(id);
                if (element) {
                    element.textContent = String(value || "").trim() || "-";
                }
            };

            setText("viewAppointmentId", button.dataset.appointmentId);
            setText("viewRequestTimestamp", button.dataset.requestTimestamp);
            setText("viewStatusName", button.dataset.statusName);
            setText("viewReviewTimestamp", button.dataset.reviewTimestamp);
            setText("viewResidentName", button.dataset.residentName);
            setText("viewContactNumber", button.dataset.contactNumber);
            setText("viewStaffName", button.dataset.staffName);
            setText("viewOfficialName", button.dataset.officialName);
            setText("viewSubject", button.dataset.subject);
            setText("viewPreferredAppointmentDate", button.dataset.preferredAppointmentDate);
            setText("viewPreferredAppointmentTime", button.dataset.preferredAppointmentTime);
            setText("viewConfirmedAppointmentDate", button.dataset.confirmedAppointmentDate);
            setText("viewConfirmedAppointmentTime", button.dataset.confirmedAppointmentTime);
            setText("viewPurpose", button.dataset.purpose);
            setText("viewResidentNotes", button.dataset.residentNotes);
            setText("viewAppointmentRemarks", button.dataset.appointmentRemarks);

            if (reviewAppointmentId) {
                reviewAppointmentId.value = String(button.dataset.appointmentId || "").trim();
            }
            if (reviewOfficialUserId) {
                reviewOfficialUserId.value = String(button.dataset.officialUserId || "").trim();
            }
            if (reviewConfirmedDate) {
                reviewConfirmedDate.value = "";
                const confirmedStamp = String(button.dataset.confirmedScheduleTimestamp || "").trim();
                const preferredStamp = String(button.dataset.preferredScheduleTimestamp || "").trim();
                const stampToUse = confirmedStamp || preferredStamp;
                if (stampToUse) {
                    reviewConfirmedDate.value = stampToUse.slice(0, 10);
                }
            }
            if (reviewConfirmedTime) {
                reviewConfirmedTime.value = "";
                const confirmedStamp = String(button.dataset.confirmedScheduleTimestamp || "").trim();
                const preferredStamp = String(button.dataset.preferredScheduleTimestamp || "").trim();
                const stampToUse = confirmedStamp || preferredStamp;
                if (stampToUse.length >= 16) {
                    reviewConfirmedTime.value = stampToUse.slice(11, 16);
                }
            }
            if (reviewRemarks) {
                const remarks = String(button.dataset.appointmentRemarks || "").trim();
                reviewRemarks.value = remarks && remarks !== '-' ? remarks : '';
            }

            const isPending = String(button.dataset.statusKey || "").trim() === "pending";
            reviewLockedMessage?.classList.toggle("d-none", isPending);
            reviewActionButtons.forEach((actionButton) => {
                actionButton.classList.toggle("d-none", !isPending);
            });
        });

        reviewActionButtons.forEach((button) => {
            button.addEventListener("click", (event) => {
                if (!(event.currentTarget instanceof HTMLButtonElement)) {
                    return;
                }

                const action = String(event.currentTarget.dataset.reviewAction || "").trim();
                if (!action) {
                    return;
                }

                const needsSchedule = action === "approve_appointment" || action === "reschedule_appointment";
                if (needsSchedule) {
                    if (!reviewConfirmedDate?.value || !reviewConfirmedTime?.value) {
                        window.alert("Confirmed date and time are required for this action.");
                        return;
                    }
                }

                pendingReviewAction = action;
                if (reviewActionInput) {
                    reviewActionInput.value = action;
                }

                if (confirmModalTitle) {
                    confirmModalTitle.textContent = action === "approve_appointment"
                        ? "Confirm Approval"
                        : (action === "reschedule_appointment" ? "Confirm Reschedule" : "Confirm Denial");
                }
                if (confirmModalMessage) {
                    confirmModalMessage.textContent = action === "approve_appointment"
                        ? "You are about to approve this appointment. This action cannot be undone."
                        : (action === "reschedule_appointment"
                            ? "You are about to reschedule this appointment. This action cannot be undone."
                            : "You are about to deny this appointment. This action cannot be undone.");
                }

                viewModalInstance?.hide();
                confirmModalInstance?.show();
            });
        });

        confirmModalReturnBtn?.addEventListener("click", () => {
            confirmModalInstance?.hide();
            window.setTimeout(() => {
                viewModalInstance?.show();
            }, 150);
        });

        confirmModalConfirmBtn?.addEventListener("click", () => {
            if (!pendingReviewAction) {
                return;
            }
            if (reviewActionInput) {
                reviewActionInput.value = pendingReviewAction;
            }
            reviewForm?.submit();
        });

        if (feedbackModalEl && (feedbackSuccessMessage || feedbackErrorMessage)) {
            if (feedbackModalTitle) {
                feedbackModalTitle.textContent = feedbackSuccessMessage ? "Success" : "Unable To Update";
            }
            if (feedbackModalMessage) {
                feedbackModalMessage.textContent = feedbackSuccessMessage || feedbackErrorMessage || "-";
            }
            const feedbackModal = new bootstrap.Modal(feedbackModalEl);
            feedbackModal.show();
        }

        setFilterButtonState("");
        updatePendingBadge();
        renderTable();
        startAutoRefresh();
    })();
</script>
</body>
</html>
