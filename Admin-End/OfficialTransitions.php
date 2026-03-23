<?php
require_once "../PhpFiles/General/connection.php";
require_once "../PhpFiles/General/adminModulePermissions.php";
require_once "../PhpFiles/General/audit.php";
require_once "includes/admin_guard.php";

requireRoleSession(['SuperAdmin'], false);
amp_ensure_permission_storage($conn);

if (!function_exists('ot_filter_catalog_for_regular_officials')) {
    function ot_filter_catalog_for_regular_officials(array $catalog): array
    {
        $filtered = [];
        foreach ($catalog as $section) {
            $items = [];
            foreach (($section['items'] ?? []) as $item) {
                if (!empty($item['children'])) {
                    $children = [];
                    foreach (($item['children'] ?? []) as $child) {
                        if (!empty($child['admin_only']) || empty($child['path'])) {
                            continue;
                        }
                        $children[] = $child;
                    }
                    if ($children) {
                        $item['children'] = $children;
                        unset($item['path']);
                        $items[] = $item;
                    }
                    continue;
                }

                if (!empty($item['admin_only']) || empty($item['path'])) {
                    continue;
                }
                $items[] = $item;
            }

            if ($items) {
                $section['items'] = $items;
                $filtered[] = $section;
            }
        }

        return $filtered;
    }
}

if (!function_exists('ot_replace_module_permissions')) {
    function ot_replace_module_permissions(mysqli $conn, string $officialId, string $userId, array $permissionKeys, string $grantedByUserId): void
    {
        $deleteStmt = $conn->prepare("DELETE FROM officialmodulepermissionstbl WHERE official_id = ?");
        if ($deleteStmt) {
            $deleteStmt->bind_param('s', $officialId);
            $deleteStmt->execute();
            $deleteStmt->close();
        }

        if (!$permissionKeys) {
            return;
        }

        $insertStmt = $conn->prepare("
            INSERT INTO officialmodulepermissionstbl
                (official_id, user_id, permission_key, is_allowed, granted_by_user_id)
            VALUES
                (?, ?, ?, 1, ?)
        ");
        if (!$insertStmt) {
            throw new RuntimeException('Failed to save module permissions.');
        }

        foreach ($permissionKeys as $permissionKey) {
            $insertStmt->bind_param('ssss', $officialId, $userId, $permissionKey, $grantedByUserId);
            $insertStmt->execute();
        }
        $insertStmt->close();
    }
}

if (!function_exists('ot_upsert_access_profile')) {
    function ot_upsert_access_profile(mysqli $conn, string $officialId, string $userId): void
    {
        $stmt = $conn->prepare("
            INSERT INTO officialaccessprofiletbl (official_id, user_id, permissions_initialized)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                permissions_initialized = 1,
                updated_at = CURRENT_TIMESTAMP
        ");
        if (!$stmt) {
            throw new RuntimeException('Failed to save access profile metadata.');
        }
        $stmt->bind_param('ss', $officialId, $userId);
        $stmt->execute();
        $stmt->close();
    }
}

$transitionTool = trim((string)($_GET['tool'] ?? 'tracker'));
if (!in_array($transitionTool, ['tracker', 'kagawad_permissions'], true)) {
    $transitionTool = 'tracker';
}

$officialTransitionFlash = null;
if (!empty($_SESSION['official_transition_flash']) && is_array($_SESSION['official_transition_flash'])) {
    $officialTransitionFlash = $_SESSION['official_transition_flash'];
    unset($_SESSION['official_transition_flash']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string)($_POST['action'] ?? '') === 'save_kagawad_permissions') {
    $officialId = trim((string)($_POST['official_id'] ?? ''));
    $actorUserId = (string)($_SESSION['user_id'] ?? '');

    try {
        if ($officialId === '') {
            throw new RuntimeException('Invalid kagawad record.');
        }

        $stmt = $conn->prepare("
            SELECT
                bc.seat_name,
                oi.official_id,
                oi.user_id,
                oi.firstname,
                oi.lastname,
                oi.middlename,
                oi.suffix,
                oi.role_access AS info_role_access,
                COALESCE(oi.position_access, oi.role_access) AS position_access,
                oi.department,
                oi.term_end,
                ua.role_access AS account_role_access,
                COALESCE(sa.status_name, '') AS account_status
            FROM barangaycounciltbl bc
            INNER JOIN officialinformationtbl oi ON oi.official_id = bc.current_official_id
            INNER JOIN useraccountstbl ua ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
            LEFT JOIN statuslookuptbl sa ON sa.status_id = ua.status_id_account
            WHERE bc.is_active = 1
              AND bc.seat_group = 'Sangguniang Barangay'
              AND bc.seat_name LIKE 'Kagawad%'
              AND oi.official_id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            throw new RuntimeException('Failed to load kagawad record.');
        }
        $stmt->bind_param('s', $officialId);
        $stmt->execute();
        $targetRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$targetRow) {
            throw new RuntimeException('Kagawad record not found.');
        }

        $requestedPermissionKeys = $_POST['permission_keys'] ?? [];
        if (!is_array($requestedPermissionKeys)) {
            $requestedPermissionKeys = [];
        }

        $validKeys = array_fill_keys(amp_get_default_admin_permission_keys(), true);
        $permissionMap = [];
        foreach ($requestedPermissionKeys as $permissionKey) {
            $permissionKey = trim((string)$permissionKey);
            if ($permissionKey !== '' && isset($validKeys[$permissionKey])) {
                $permissionMap[$permissionKey] = true;
            }
        }

        $conn->begin_transaction();
        ot_replace_module_permissions($conn, (string)$targetRow['official_id'], (string)$targetRow['user_id'], array_keys($permissionMap), $actorUserId);
        ot_upsert_access_profile($conn, (string)$targetRow['official_id'], (string)$targetRow['user_id']);
        $conn->commit();

        if (function_exists('insertUnifiedAuditLog')) {
            $targetName = trim(
                (string)($targetRow['firstname'] ?? '') . ' ' .
                ((string)($targetRow['middlename'] ?? '') !== '' ? (string)($targetRow['middlename'] ?? '') . ' ' : '') .
                (string)($targetRow['lastname'] ?? '') .
                ((string)($targetRow['suffix'] ?? '') !== '' ? ' ' . (string)($targetRow['suffix'] ?? '') : '')
            );
            insertUnifiedAuditLog(
                $conn,
                $actorUserId,
                (string)($_SESSION['role'] ?? 'SuperAdmin'),
                'OfficialTransitions',
                'official',
                (string)$targetRow['official_id'],
                'save_kagawad_permissions',
                'module_permissions',
                null,
                implode(', ', array_keys($permissionMap)),
                'Kagawad: ' . ($targetName !== '' ? $targetName : (string)$targetRow['seat_name'])
            );
        }

        $_SESSION['official_transition_flash'] = [
            'type' => 'success',
            'message' => 'Kagawad permissions updated successfully.',
        ];
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }
        $_SESSION['official_transition_flash'] = [
            'type' => 'danger',
            'message' => $e->getMessage(),
        ];
    }

    header('Location: OfficialTransitions.php?tool=kagawad_permissions');
    exit;
}

// ── Council seats (source of truth for the transition module) ────────────────
$councilSeats = [];
$hasCouncilTbl = $conn->query("SHOW TABLES LIKE 'barangaycounciltbl'")->num_rows > 0;
if ($hasCouncilTbl) {
    $csRes = $conn->query("
        SELECT bc.council_id, bc.seat_name, bc.selection_method, bc.seat_group,
               bc.sort_order, bc.term_start, bc.term_end,
               bc.current_official_id,
               CONCAT(oi.lastname, ', ', oi.firstname,
                      IFNULL(CONCAT(' ', oi.middlename),''),
                      IFNULL(CONCAT(' ', oi.suffix),''))  AS current_official_name,
               COALESCE(sa.status_name,'')                 AS account_status
        FROM barangaycounciltbl bc
        LEFT JOIN officialinformationtbl oi
               ON oi.official_id = bc.current_official_id
        LEFT JOIN useraccountstbl ua
               ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
        LEFT JOIN statuslookuptbl sa ON sa.status_id = ua.status_id_account
        WHERE bc.is_active = 1
        ORDER BY bc.sort_order, bc.council_id
    ");
    if ($csRes instanceof mysqli_result) {
        while ($r = $csRes->fetch_assoc()) $councilSeats[] = $r;
        $csRes->close();
    }
}

// Group seats by seat_group for the modal dropdowns
$councilSeatsByGroup = [];
foreach ($councilSeats as $cs) {
    $councilSeatsByGroup[$cs['seat_group']][] = $cs;
}

$batchPreviewSeats = [
    'BarangayElection' => [],
    'SKElection' => [],
];
foreach ($councilSeats as $cs) {
    $selectionMethod = (string)($cs['selection_method'] ?? '');
    $seatGroup = (string)($cs['seat_group'] ?? '');
    if ($selectionMethod !== 'Elected') {
        continue;
    }
    if ($seatGroup === 'Sangguniang Barangay') {
        $batchPreviewSeats['BarangayElection'][] = $cs;
    } elseif ($seatGroup === 'Sangguniang Kabataan') {
        $batchPreviewSeats['SKElection'][] = $cs;
    }
}

// Active officials still needed for Quick Actions / Restore / Credentials
$activeOfficials = [];
$aoRes = $conn->query("
    SELECT oi.official_id, oi.firstname, oi.lastname, oi.middlename, oi.suffix,
           COALESCE(oi.position_access, oi.role_access) AS position,
           oi.department, oi.area_number,
           oi.acting_for_id,
           ua.email, ua.phone_number,
           COALESCE(se.status_name,'') AS employment_status
    FROM officialinformationtbl oi
    LEFT JOIN statuslookuptbl se ON se.status_id = oi.status_id_employment
    INNER JOIN useraccountstbl ua ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
    INNER JOIN statuslookuptbl sa ON sa.status_id = ua.status_id_account
    WHERE sa.status_name IN ('Active','Suspended','Acting')
    ORDER BY oi.lastname, oi.firstname
");
if ($aoRes instanceof mysqli_result) {
    while ($r = $aoRes->fetch_assoc()) $activeOfficials[] = $r;
    $aoRes->close();
}

$hasTransTbl = $conn->query("SHOW TABLES LIKE 'officialtransitionstbl'")->num_rows > 0;

// ── Election schedules ────────────────────────────────────────────────────────
$electionSchedules = [];
if ($hasTransTbl) {
    $elRes = $conn->query("
        SELECT batch_label, election_date,
               MAX(notify_3mo_sent)       AS n3,
               MAX(notify_1mo_sent)       AS n1,
               MAX(deactivated_7d_before) AS n7,
               MAX(notify_post_sent)      AS np,
               COUNT(*)                   AS position_count,
               SUM(status='Completed')    AS completed_count
        FROM officialtransitionstbl
        WHERE election_date IS NOT NULL AND batch_label IS NOT NULL
        GROUP BY batch_label, election_date
        ORDER BY election_date DESC
    ");
    if ($elRes instanceof mysqli_result) {
        while ($r = $elRes->fetch_assoc()) {
            $electionSchedules[] = $r;
        }
        $elRes->close();
    }
}

$departmentOptions = [
    'Office of the Barangay',
    'Barangay Certificate Issuance',
    'Barangay Monitoring',
    'Barangay Treasurers Office',
    'Barangay Peace and Order',
];
$areaOptions = ['Barangay Wide','Area 01','Area 1A','Area 02','Area 03','Area 04','Area 05','Area 06'];

$defaultOfficialPermissionKeys = amp_get_default_admin_permission_keys();
$kagawadPermissionCatalog = ot_filter_catalog_for_regular_officials(amp_get_permission_catalog());

$kagawadOfficials = [];
if ($hasCouncilTbl) {
    $kgStmt = $conn->prepare("
        SELECT
            bc.council_id,
            bc.seat_name,
            bc.sort_order,
            bc.current_official_id,
            oi.official_id,
            oi.user_id,
            oi.firstname,
            oi.lastname,
            oi.middlename,
            oi.suffix,
            oi.role_access AS info_role_access,
            COALESCE(oi.position_access, oi.role_access) AS position_access,
            oi.department,
            oi.area_number,
            oi.term_end,
            ua.email,
            ua.phone_number,
            ua.role_access AS account_role_access,
            COALESCE(sa.status_name, '') AS account_status
        FROM barangaycounciltbl bc
        LEFT JOIN officialinformationtbl oi ON oi.official_id = bc.current_official_id
        LEFT JOIN useraccountstbl ua ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
        LEFT JOIN statuslookuptbl sa ON sa.status_id = ua.status_id_account
        WHERE bc.is_active = 1
          AND bc.seat_group = 'Sangguniang Barangay'
          AND bc.seat_name LIKE 'Kagawad%'
        ORDER BY bc.sort_order, bc.council_id
    ");
    if ($kgStmt) {
        $kgStmt->execute();
        $kgRes = $kgStmt->get_result();
        while ($row = $kgRes->fetch_assoc()) {
            $officialId = trim((string)($row['official_id'] ?? ''));
            $permissionMap = $officialId !== '' ? amp_get_effective_permission_keys_for_row($conn, $row) : [];
            $fullName = trim(
                (string)($row['firstname'] ?? '') . ' ' .
                ((string)($row['middlename'] ?? '') !== '' ? (string)($row['middlename'] ?? '') . ' ' : '') .
                (string)($row['lastname'] ?? '') .
                ((string)($row['suffix'] ?? '') !== '' ? ' ' . (string)($row['suffix'] ?? '') : '')
            );

            $kagawadOfficials[] = [
                'council_id' => (int)($row['council_id'] ?? 0),
                'seat_name' => (string)($row['seat_name'] ?? ''),
                'official_id' => $officialId,
                'user_id' => (string)($row['user_id'] ?? ''),
                'full_name' => $fullName !== '' ? $fullName : 'Vacant',
                'position_access' => (string)($row['position_access'] ?? ''),
                'department' => (string)($row['department'] ?? ''),
                'area_number' => (string)($row['area_number'] ?? ''),
                'term_end' => (string)($row['term_end'] ?? ''),
                'email' => (string)($row['email'] ?? ''),
                'phone_number' => (string)($row['phone_number'] ?? ''),
                'account_status' => (string)($row['account_status'] ?? ''),
                'permission_keys' => array_keys($permissionMap),
                'permission_count' => count($permissionMap),
                'has_official' => $officialId !== '',
            ];
        }
        $kgStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" href="../Images/favicon_sanjose.png?v=20260211">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Official Transition Module</title>

  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260319-1">
  <style>
    #main-display { min-width: 0; }

    /* ── Election schedule timeline pills ── */
    .notif-pill { font-size: .72rem; white-space: nowrap; }
    .notif-pill.sent    { background:#d1e7dd; color:#0f5132; }
    .notif-pill.pending { background:#fff3cd; color:#664d03; }
    .notif-pill.na      { background:#e9ecef; color:#6c757d; }

    /* ── Transition table ── */
    .ot-table-shell { overflow-x: auto; }
    .ot-table { min-width: 1100px; }
    .ot-table th { white-space: nowrap; font-size: .82rem; }
    .ot-table td { vertical-align: middle; font-size: .84rem; white-space: nowrap; max-width: 200px; overflow: hidden; text-overflow: ellipsis; }

    /* ── Status badges ── */
    .badge-ot-open       { background:#cfe2ff; color:#0a58ca; }
    .badge-ot-encoding   { background:#fff3cd; color:#856404; }
    .badge-ot-pending    { background:#ffe5d0; color:#974002; }
    .badge-ot-decided    { background:#d2f4ea; color:#0a3622; }
    .badge-ot-completed  { background:#d1e7dd; color:#0f5132; }
    .badge-ot-cancelled  { background:#e2e3e5; color:#41464b; }

    /* ── Candidate list in modal ── */
    .candidate-item { border: 1px solid #dee2e6; border-radius: .4rem; }
    .candidate-item.selected { border-color: #198754; background: #f0fff4; }
    .candidate-type-badge { font-size: .7rem; }
    .linked-search-item { cursor: pointer; }
    .linked-search-item:hover,
    .linked-search-item:focus { background: #f8f9fa; }

    /* ── Quick actions ── */
    .quick-action-btn { text-align: left; }

    /* ── Module sub-navigation ── */
    .ot-subnav {
      display: flex;
      gap: .75rem;
      flex-wrap: wrap;
      margin-bottom: 1.5rem;
    }
    .ot-subnav-link {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      padding: .7rem 1rem;
      border: 1px solid #d6dbe1;
      border-radius: .8rem;
      background: #fff;
      color: #334155;
      text-decoration: none;
      font-weight: 600;
      font-size: .92rem;
    }
    .ot-subnav-link:hover,
    .ot-subnav-link:focus-visible {
      border-color: #0d6efd;
      color: #0d6efd;
    }
    .ot-subnav-link.active {
      border-color: #0d6efd;
      background: #eaf2ff;
      color: #0d6efd;
      box-shadow: inset 0 0 0 1px rgba(13, 110, 253, .12);
    }

    /* ── Kagawad permissions ── */
    .kagawad-permission-card + .kagawad-permission-card { margin-top: 1rem; }
    .kagawad-permission-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: .85rem 1rem;
    }
    .kagawad-permission-group {
      border: 1px solid #eef1f4;
      border-radius: .85rem;
      background: #fcfcfd;
      padding: .95rem 1rem;
    }
    .kagawad-permission-group-title {
      font-size: .92rem;
      font-weight: 700;
      color: #334155;
      margin-bottom: .65rem;
    }
    .kagawad-permission-items {
      display: grid;
      gap: .45rem;
    }
    .kagawad-permission-items .form-check-label {
      font-size: .88rem;
      color: #475569;
    }
  </style>
</head>
<body>
<div class="d-flex flex-column flex-md-row" style="min-height:100vh;">
  <?php include "includes/sidebar.php"; ?>

  <main class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light" id="main-display">

    <!-- ══════════════════════════════════════════════════════════ HEADER -->
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
      <div>
        <h2 style="font-family:'Charis SIL Bold';color:#DE710C;">
          Official Transition Module
        </h2>
        <p class="text-muted mb-0" style="font-size:.9rem;">
          Manage election cycles, position handovers, and access setup for barangay officials.
        </p>
      </div>
      <?php if ($transitionTool === 'tracker'): ?>
      <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-outline-secondary btn-sm" id="btnOtQuickActions"
                data-bs-toggle="modal" data-bs-target="#modalQuickActions">
          <i class="fas fa-bolt me-1"></i> Quick Actions
        </button>
        <button class="btn btn-outline-primary btn-sm" id="btnNewBatch"
                data-bs-toggle="modal" data-bs-target="#modalNewBatch">
          <i class="fas fa-layer-group me-1"></i> New Batch
        </button>
        <button class="btn btn-primary btn-sm" id="btnNewTransition"
                data-bs-toggle="modal" data-bs-target="#modalNewTransition">
          <i class="fas fa-plus me-1"></i> New Transition
        </button>
      </div>
      <?php endif; ?>
    </div>
    <hr class="mb-4">

    <div class="ot-subnav">
      <a href="OfficialTransitions.php?tool=tracker"
         class="ot-subnav-link <?= $transitionTool === 'tracker' ? 'active' : '' ?>">
        <i class="fas fa-list-check"></i>
        <span>Tracker</span>
      </a>
      <a href="OfficialTransitions.php?tool=kagawad_permissions"
         class="ot-subnav-link <?= $transitionTool === 'kagawad_permissions' ? 'active' : '' ?>">
        <i class="fas fa-user-check"></i>
        <span>Kagawad Permissions</span>
      </a>
    </div>

    <?php if (!empty($officialTransitionFlash['message'])): ?>
      <div class="alert alert-<?= htmlspecialchars((string)($officialTransitionFlash['type'] ?? 'info'), ENT_QUOTES, 'UTF-8') ?> mb-4">
        <?= htmlspecialchars((string)$officialTransitionFlash['message'], ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <?php if ($transitionTool === 'tracker'): ?>
    <!-- ══════════════════════════════════════════════════════════ ELECTION SCHEDULES -->
    <div class="bg-white rounded-3 shadow-sm border mb-4">
      <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
        <div class="d-flex align-items-center gap-2">
          <i class="fas fa-calendar-alt text-primary"></i>
          <span class="fw-semibold">Election Schedules</span>
        </div>
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAddElection">
          <i class="fas fa-plus me-1"></i> Add Election Date
        </button>
      </div>
      <div class="p-3">
        <?php if (empty($electionSchedules)): ?>
          <p class="text-muted mb-0 small text-center py-2">No election dates scheduled.</p>
        <?php else: ?>
          <?php
            $notifPillClass = static function (int $sent): string {
                return $sent ? 'sent' : 'pending';
            };
            $notifPillLabel = static function (int $sent, bool $isPast, string $label): string {
                return $sent ? 'Sent' : ($isPast ? 'Missed' : $label);
            };
          ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" id="electionScheduleTable">
              <thead class="table-light">
                <tr>
                  <th>Batch / Label</th>
                  <th>Election Date</th>
                  <th>Positions</th>
                  <th>3-Month Notice</th>
                  <th>1-Month Notice</th>
                  <th>7-Day Deactivation</th>
                  <th>Post-Election Notice</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($electionSchedules as $es): ?>
                  <?php
                    $ed    = $es['election_date'];
                    $label = htmlspecialchars($es['batch_label'] ?? '', ENT_QUOTES, 'UTF-8');
                    $daysUntil = (int)round((strtotime($ed) - time()) / 86400);
                    $isPast = $daysUntil < 0;
                    $edFmt  = date('M d, Y', strtotime($ed));
                  ?>
                  <tr>
                    <td class="fw-semibold"><?= $label ?></td>
                    <td>
                      <?= $edFmt ?>
                      <?php if ($daysUntil > 0): ?>
                        <span class="badge bg-secondary ms-1"><?= $daysUntil ?>d away</span>
                      <?php elseif ($daysUntil === 0): ?>
                        <span class="badge bg-warning text-dark ms-1">Today</span>
                      <?php else: ?>
                        <span class="badge bg-light text-muted ms-1"><?= abs($daysUntil) ?>d ago</span>
                      <?php endif; ?>
                    </td>
                    <td><?= (int)$es['position_count'] ?> pos / <?= (int)$es['completed_count'] ?> done</td>
                    <td><span class="badge notif-pill <?= $notifPillClass((int)$es['n3']) ?>"><?= $notifPillLabel((int)$es['n3'], $isPast, 'Pending') ?></span></td>
                    <td><span class="badge notif-pill <?= $notifPillClass((int)$es['n1']) ?>"><?= $notifPillLabel((int)$es['n1'], $isPast, 'Pending') ?></span></td>
                    <td><span class="badge notif-pill <?= $notifPillClass((int)$es['n7']) ?>"><?= $notifPillLabel((int)$es['n7'], $isPast, 'Pending') ?></span></td>
                    <td><span class="badge notif-pill <?= $notifPillClass((int)$es['np']) ?>"><?= $notifPillLabel((int)$es['np'], $daysUntil > 0, 'Pending') ?></span></td>
                    <td>
                      <div class="d-flex gap-1 justify-content-end">
                      <button class="btn btn-xs btn-outline-secondary py-0 px-2"
                              onclick="otResendNotif(<?= htmlspecialchars(json_encode($es['batch_label']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($ed), ENT_QUOTES, 'UTF-8') ?>)"
                              title="Manually trigger pending notifications">
                        <i class="fas fa-redo fa-xs"></i>
                      </button>
                      <button class="btn btn-xs btn-outline-danger py-0 px-2"
                              onclick="otDeleteSchedule(<?= htmlspecialchars(json_encode($es['batch_label']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($ed), ENT_QUOTES, 'UTF-8') ?>)"
                              title="Delete this schedule and its linked transitions">
                        <i class="fas fa-trash fa-xs"></i>
                      </button>
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
    <?php else: ?>
    <div class="bg-white rounded-3 shadow-sm border mb-4">
      <div class="p-3 p-md-4 border-bottom">
        <div class="fw-semibold mb-1">Kagawad Default Access</div>
        <div class="text-muted small">
          Right now, the system default is role-based, not position-based:
          <strong>regular officials/Admins</strong> start with all non-admin-only sidebar permissions,
          while <strong>SuperAdmin</strong> gets every module. This screen lets you override that default per kagawad.
        </div>
      </div>
      <div class="p-3 p-md-4">
        <?php
          $availablePermissionCount = count($defaultOfficialPermissionKeys);
          $assignedKagawads = array_values(array_filter($kagawadOfficials, static fn ($row) => !empty($row['has_official'])));
        ?>
        <div class="row g-3 mb-3">
          <div class="col-12 col-md-4">
            <div class="border rounded-3 p-3 h-100 bg-light">
              <div class="small text-muted">Current Kagawads</div>
              <div class="fs-4 fw-bold"><?= count($assignedKagawads) ?></div>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div class="border rounded-3 p-3 h-100 bg-light">
              <div class="small text-muted">Available Checklist Items</div>
              <div class="fs-4 fw-bold"><?= $availablePermissionCount ?></div>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div class="border rounded-3 p-3 h-100 bg-light">
              <div class="small text-muted">Default Behavior</div>
              <div class="fw-semibold">All non-admin-only modules</div>
            </div>
          </div>
        </div>

        <?php if (empty($kagawadOfficials)): ?>
          <div class="text-muted small text-center py-4">No kagawad seats were found in the council records yet.</div>
        <?php else: ?>
          <?php foreach ($kagawadOfficials as $index => $kagawad): ?>
            <div class="border rounded-3 p-3 p-md-4 kagawad-permission-card">
              <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                <div>
                  <div class="fw-bold fs-5"><?= htmlspecialchars((string)$kagawad['seat_name'], ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="text-muted">
                    <?= htmlspecialchars((string)$kagawad['full_name'], ENT_QUOTES, 'UTF-8') ?>
                    <?php if (!empty($kagawad['official_id'])): ?>
                      <span class="badge bg-light text-dark border ms-2"><?= htmlspecialchars((string)$kagawad['official_id'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="small text-muted mt-1">
                    <?= htmlspecialchars((string)($kagawad['department'] ?: 'Office of the Barangay'), ENT_QUOTES, 'UTF-8') ?>
                    <?php if (!empty($kagawad['email'])): ?>
                      • <?= htmlspecialchars((string)$kagawad['email'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                    <?php if (!empty($kagawad['phone_number'])): ?>
                      • +63<?= htmlspecialchars((string)$kagawad['phone_number'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="text-md-end">
                  <div class="small text-muted">Checked Modules</div>
                  <div class="fw-bold"><?= (int)$kagawad['permission_count'] ?></div>
                  <div class="small text-muted"><?= htmlspecialchars((string)($kagawad['account_status'] ?: 'Unknown'), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
              </div>

              <?php if (empty($kagawad['has_official'])): ?>
                <div class="alert alert-light border mb-0">This seat is currently vacant, so there is no kagawad account to configure yet.</div>
              <?php else: ?>
                <form method="post" action="OfficialTransitions.php?tool=kagawad_permissions">
                  <input type="hidden" name="action" value="save_kagawad_permissions">
                  <input type="hidden" name="official_id" value="<?= htmlspecialchars((string)$kagawad['official_id'], ENT_QUOTES, 'UTF-8') ?>">

                  <div class="kagawad-permission-grid mb-3">
                    <?php foreach ($kagawadPermissionCatalog as $section): ?>
                      <div class="kagawad-permission-group">
                        <div class="kagawad-permission-group-title"><?= htmlspecialchars((string)$section['section'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="kagawad-permission-items">
                          <?php foreach (($section['items'] ?? []) as $item): ?>
                            <?php if (!empty($item['children'])): ?>
                              <?php foreach (($item['children'] ?? []) as $child): ?>
                                <?php
                                  $childKey = (string)($child['key'] ?? '');
                                  $isChecked = in_array($childKey, $kagawad['permission_keys'], true);
                                  $label = trim((string)($item['label'] ?? '') . ' - ' . (string)($child['label'] ?? ''));
                                ?>
                                <div class="form-check">
                                  <input class="form-check-input"
                                         type="checkbox"
                                         name="permission_keys[]"
                                         value="<?= htmlspecialchars($childKey, ENT_QUOTES, 'UTF-8') ?>"
                                         id="kgPerm<?= $index ?><?= htmlspecialchars($childKey, ENT_QUOTES, 'UTF-8') ?>"
                                         <?= $isChecked ? 'checked' : '' ?>>
                                  <label class="form-check-label" for="kgPerm<?= $index ?><?= htmlspecialchars($childKey, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                  </label>
                                </div>
                              <?php endforeach; ?>
                            <?php else: ?>
                              <?php
                                $itemKey = (string)($item['key'] ?? '');
                                $isChecked = in_array($itemKey, $kagawad['permission_keys'], true);
                              ?>
                              <div class="form-check">
                                <input class="form-check-input"
                                       type="checkbox"
                                       name="permission_keys[]"
                                       value="<?= htmlspecialchars($itemKey, ENT_QUOTES, 'UTF-8') ?>"
                                       id="kgPerm<?= $index ?><?= htmlspecialchars($itemKey, ENT_QUOTES, 'UTF-8') ?>"
                                       <?= $isChecked ? 'checked' : '' ?>>
                                <label class="form-check-label" for="kgPerm<?= $index ?><?= htmlspecialchars($itemKey, ENT_QUOTES, 'UTF-8') ?>">
                                  <?= htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') ?>
                                </label>
                              </div>
                            <?php endif; ?>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>

                  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="small text-muted">
                      Leaving the checklist empty will remove all saved module access for this kagawad.
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">
                      <i class="fas fa-save me-1"></i> Save Kagawad Permissions
                    </button>
                  </div>
                </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($transitionTool === 'tracker'): ?>
    <!-- ══════════════════════════════════════════════════════════ TRANSITIONS TABLE -->
    <div class="bg-white rounded-3 shadow-sm border">
      <!-- Toolbar -->
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 p-3 border-bottom">
        <div class="d-flex gap-1 flex-wrap" id="otTabBtns">
          <button class="btn btn-sm btn-outline-primary active" data-ot-tab="active">Active</button>
          <button class="btn btn-sm btn-outline-secondary" data-ot-tab="history">History</button>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap ms-auto">
          <div class="input-group" style="max-width:280px;">
            <input type="text" class="form-control form-control-sm" id="otSearch"
                   placeholder="Search position, official, batch…">
            <span class="input-group-text bg-white"><i class="fas fa-search fa-xs"></i></span>
          </div>
          <select class="form-select form-select-sm" id="otTypeFilter" style="max-width:180px;">
            <option value="">All types</option>
            <option value="BarangayElection">Barangay Election</option>
            <option value="SKElection">SK Election</option>
            <option value="Appointment">Appointment</option>
            <option value="Reappointment">Reappointment</option>
            <option value="Resignation">Resignation</option>
            <option value="Removal">Removal</option>
            <option value="Retirement">Retirement</option>
          </select>
          <button class="btn btn-sm btn-outline-secondary" id="btnOtRefresh" title="Refresh">
            <i class="fas fa-arrows-rotate"></i>
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="p-3 ot-table-shell">
        <table class="table table-hover align-middle ot-table mb-0" id="otTable">
          <thead class="table-light">
            <tr>
              <th>Transition ID</th>
              <th>Type</th>
              <th>Position</th>
              <th>Outgoing Official</th>
              <th>Batch / Election</th>
              <th>Effective Date</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="otTableBody">
            <tr>
              <td colspan="8" class="text-center text-muted py-4">
                <i class="fas fa-spinner fa-spin me-2"></i> Loading…
              </td>
            </tr>
          </tbody>
        </table>
        <div id="otTablePagination" class="d-flex justify-content-end mt-2"></div>
      </div>
    </div>
    <?php endif; ?>

  </main><!-- /main -->
</div><!-- /flex wrapper -->


<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL: New Individual Transition
══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalNewTransition" tabindex="-1" aria-labelledby="modalNewTransitionLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalNewTransitionLabel">
          <i class="fas fa-plus-circle me-2 text-primary"></i> New Transition
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="formNewTransition" novalidate>
        <div class="modal-body">
          <?php if (empty($councilSeats)): ?>
            <div class="alert alert-warning">
              <i class="fas fa-exclamation-triangle me-2"></i>
              No council seats found. Run <code>20260323_create_barangaycouncil_schema.sql</code> and assign officials to their seats first.
            </div>
          <?php else: ?>
          <div class="row g-3">
            <!-- Council Seat -->
            <div class="col-12">
              <label class="form-label fw-semibold">Council Seat <span class="text-danger">*</span></label>
              <select class="form-select" name="council_id" id="ntCouncilId" required>
                <option value="">— Select a seat —</option>
                <?php foreach ($councilSeatsByGroup as $group => $seats): ?>
                  <optgroup label="<?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?>">
                    <?php foreach ($seats as $cs): ?>
                      <?php
                        $holder = $cs['current_official_name']
                            ? htmlspecialchars(trim($cs['current_official_name']), ENT_QUOTES, 'UTF-8')
                            : '<em>Vacant</em>';
                        $holderText = $cs['current_official_name']
                            ? trim($cs['current_official_name'])
                            : 'Vacant';
                      ?>
                      <option value="<?= (int)$cs['council_id'] ?>"
                              data-seat="<?= htmlspecialchars($cs['seat_name'], ENT_QUOTES, 'UTF-8') ?>"
                              data-method="<?= htmlspecialchars($cs['selection_method'], ENT_QUOTES, 'UTF-8') ?>"
                              data-official-id="<?= htmlspecialchars($cs['current_official_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                              data-official-name="<?= htmlspecialchars($holderText, ENT_QUOTES, 'UTF-8') ?>"
                              data-account-status="<?= htmlspecialchars($cs['account_status'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($cs['seat_name'], ENT_QUOTES, 'UTF-8') ?>
                        — <?= $holderText ?>
                      </option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Auto-filled seat info card -->
            <div class="col-12" id="ntSeatInfoWrap" style="display:none;">
              <div class="alert alert-secondary py-2 mb-0 small">
                <div class="d-flex gap-3 flex-wrap">
                  <div><span class="text-muted">Position:</span> <strong id="ntSeatName"></strong></div>
                  <div><span class="text-muted">Selection:</span> <strong id="ntSeatMethod"></strong></div>
                  <div><span class="text-muted">Current holder:</span> <strong id="ntSeatHolder"></strong>
                    <span id="ntSeatHolderStatus" class="badge ms-1"></span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Transition Type — options filtered by selection_method -->
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Transition Type <span class="text-danger">*</span></label>
              <select class="form-select" name="transition_type" id="ntType" required>
                <option value="">— Select a seat first —</option>
              </select>
            </div>

            <!-- Effective Date -->
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Effective Date</label>
              <input type="date" class="form-control" name="effective_date" id="ntEffectiveDate">
            </div>

            <!-- Batch Label (election types only) -->
            <div class="col-12 col-md-6" id="ntBatchLabelWrap" style="display:none;">
              <label class="form-label fw-semibold">Batch Label</label>
              <input type="text" class="form-control" name="batch_label" id="ntBatchLabel"
                     placeholder="e.g. 2025 Barangay Election">
            </div>
            <!-- Election Date (election types only) -->
            <div class="col-12 col-md-6" id="ntElectionDateWrap" style="display:none;">
              <label class="form-label fw-semibold">Election Date</label>
              <input type="date" class="form-control" name="election_date" id="ntElectionDate">
            </div>

            <!-- Reason -->
            <div class="col-12">
              <label class="form-label fw-semibold" id="ntReasonLabel">Reason</label>
              <textarea class="form-control" name="reason" id="ntReason" rows="2"
                        placeholder="Optional — required for Removal"></textarea>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="btnSubmitNewTransition">
            <i class="fas fa-save me-1"></i> Create Transition
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL: New Batch Transition
══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalNewBatch" tabindex="-1" aria-labelledby="modalNewBatchLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalNewBatchLabel">
          <i class="fas fa-layer-group me-2 text-primary"></i> New Batch Transition
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="formNewBatch" novalidate>
        <div class="modal-body">
          <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
              <label class="form-label fw-semibold">Batch Label <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="batch_label" id="nbLabel" required
                     placeholder="e.g. 2025 Barangay Election">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label fw-semibold">Batch Type <span class="text-danger">*</span></label>
              <select class="form-select" name="batch_type" id="nbType" required>
                <option value="">— Select —</option>
                <option value="BarangayElection">Barangay Election (Term End)</option>
                <option value="SKElection">SK Election (Term End)</option>
              </select>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label fw-semibold">Election Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" name="election_date" id="nbElectionDate" required>
            </div>
          </div>

          <p class="fw-semibold mb-2">Included council seats:</p>
          <div class="alert alert-info py-2 small mb-3">
            <i class="fas fa-info-circle me-1"></i>
            The system will automatically include the elected seats covered by the selected batch type. Outgoing officials are detected per seat automatically.
          </div>
          <?php if (empty($batchPreviewSeats['BarangayElection']) && empty($batchPreviewSeats['SKElection'])): ?>
            <div class="alert alert-warning small">No council seats found. Run the migration first.</div>
          <?php else: ?>
          <div id="nbAutoSeatPreviewEmpty" class="alert alert-light border small mb-0">
            Select a batch type to preview the seats that will be included.
          </div>
          <div class="table-responsive d-none" id="nbAutoSeatPreviewWrap">
            <table class="table table-sm align-middle">
              <thead class="table-light">
                <tr>
                  <th>Seat</th>
                  <th>Selection</th>
                  <th>Group</th>
                  <th>Current Holder</th>
                  <th>Account</th>
                </tr>
              </thead>
              <tbody id="nbAutoSeatPreviewBody">
                <tr>
                  <td colspan="5" class="text-center text-muted py-4">Select a batch type to preview the seats that will be included.</td>
                </tr>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="btnSubmitNewBatch">
            <i class="fas fa-layer-group me-1"></i> Create Batch
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL: Add Election Date (standalone, no batch)
══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalAddElection" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-calendar-plus me-2 text-primary"></i> Add / Update Election Date</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="formAddElection" novalidate>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Batch Label <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="batch_label" id="aeBatchLabel" required
                   placeholder="e.g. 2025 Barangay Election">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Election Date <span class="text-danger">*</span></label>
            <input type="date" class="form-control" name="election_date" id="aeElectionDate" required>
          </div>
          <div class="alert alert-warning py-2 small">
            <i class="fas fa-exclamation-triangle me-1"></i>
            If you are updating an existing batch date, all unprocessed notification flags for that batch will be reset.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Save Election Date
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL: Official Access Setup
══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalCandidates" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="fas fa-users me-2 text-primary"></i>
          Official Access Setup — <span id="candidatesModalPositionLabel" class="text-muted fw-normal"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="candidatesTransitionId">
        <input type="hidden" id="candidatesTransitionStatus">

        <!-- Outgoing official info -->
        <div id="outgoingOfficialInfo" class="alert alert-secondary py-2 small mb-3 d-none">
          <i class="fas fa-sign-out-alt me-1"></i>
          <strong>Outgoing:</strong> <span id="outgoingOfficialName"></span>
          — <span id="outgoingOfficialPosition"></span>
        </div>

        <!-- Current saved official preview -->
        <div id="candidatesList" class="mb-3">
          <div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-2"></i> Loading…</div>
        </div>

        <!-- Add official -->
        <div id="addCandidateSection" class="border rounded p-3">
          <p class="fw-semibold mb-2 small">Official Information</p>
          <div class="row g-2">
            <div class="col-12 col-md-5">
              <label for="formerOfficialMode" class="form-label small fw-semibold mb-1">Former Official?</label>
              <select class="form-select form-select-sm" id="formerOfficialMode">
                <option value="" selected>— Select option —</option>
                <option value="new">No, this is a new official</option>
                <option value="former">Yes, search former official</option>
              </select>
              <div class="small text-muted mt-2">Choose Yes only if this person previously served and you want to reactivate the old account.</div>
            </div>
            <div class="col-12" id="linkedIdWrap" style="display:none;">
              <label for="linkedOfficialSearch" class="form-label small fw-semibold mb-1" id="linkedIdLabel">Search Former Officials</label>
              <div class="small text-muted mb-2">If this person previously served, search them here to auto-fill the details and reactivate the old account.</div>
              <input type="hidden" id="newCandidateLinkedId" value="">
              <input type="text" class="form-control form-control-sm" id="linkedOfficialSearch"
                     placeholder="Search former official by name, ID, or position">
              <div id="linkedOfficialSelected" class="small text-success fw-semibold mt-2 d-none"></div>
              <div id="linkedOfficialSearchResults" class="border rounded mt-2 bg-white d-none" style="max-height: 220px; overflow-y: auto;"></div>
            </div>
          </div>

          <div id="newOfficialFieldsWrap" style="display:none;">
            <div class="small text-danger fw-semibold mb-3">* Required fields</div>

            <div class="border rounded p-3 mb-3">
              <div class="fw-semibold text-muted mb-3">Identity</div>
              <div class="row g-2">
                <div class="col-12 col-md-3">
                  <label for="newCandidateLastName" class="form-label small fw-semibold mb-1">Last Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control form-control-sm" id="newCandidateLastName"
                         placeholder="Last name">
                </div>
                <div class="col-12 col-md-3">
                  <label for="newCandidateFirstName" class="form-label small fw-semibold mb-1">First Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control form-control-sm" id="newCandidateFirstName"
                         placeholder="First name">
                </div>
                <div class="col-12 col-md-3">
                  <label for="newCandidateMiddleName" class="form-label small fw-semibold mb-1">Middle Name</label>
                  <input type="text" class="form-control form-control-sm" id="newCandidateMiddleName"
                         placeholder="Middle name">
                </div>
                <div class="col-12 col-md-3">
                  <label for="newCandidateSuffix" class="form-label small fw-semibold mb-1">Suffix</label>
                  <select class="form-select form-select-sm" id="newCandidateSuffix">
                    <option value="">None</option>
                    <option value="Jr.">Jr.</option>
                    <option value="Sr.">Sr.</option>
                    <option value="II">II</option>
                    <option value="III">III</option>
                    <option value="IV">IV</option>
                    <option value="V">V</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="border rounded p-3 mb-3">
              <div class="fw-semibold text-muted mb-3">Contact</div>
              <div class="row g-2">
                <div class="col-12 col-md-6">
                  <label for="newCandidateEmail" class="form-label small fw-semibold mb-1">Email <span class="text-danger">*</span></label>
                  <input type="email" class="form-control form-control-sm" id="newCandidateEmail"
                         placeholder="name@example.com">
                </div>
                <div class="col-12 col-md-6">
                  <label for="newCandidateMobile" class="form-label small fw-semibold mb-1">Mobile Number <span class="text-danger">*</span></label>
                  <div class="input-group input-group-sm">
                    <span class="input-group-text">+63</span>
                    <input type="text" class="form-control" id="newCandidateMobile"
                           inputmode="numeric" maxlength="10" placeholder="9XXXXXXXXX">
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="row g-2">
            <div class="col-12">
              <label for="newCandidateNotes" class="form-label small fw-semibold mb-1">Notes</label>
              <input type="text" class="form-control form-control-sm" id="newCandidateNotes"
                     placeholder="Notes (optional)">
            </div>
            <div class="col-12">
              <div class="small text-muted">
                This position accepts one official only. The information here will be saved when you click <span class="fw-semibold">Continue to Finalize</span>.
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-warning btn-sm" id="btnMarkPendingDecision">
          <i class="fas fa-key me-1"></i> Continue to Finalize
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL: Select Winner & Complete Transition
══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalSelectWinner" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="fas fa-key me-2 text-warning"></i>
          Finalize Access & Complete Transition
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="winnerTransitionId">

        <div class="alert alert-info py-2 small mb-3">
          <i class="fas fa-info-circle me-1"></i>
          Review the encoded official for this position. The access action below is set automatically from the information you entered in the first modal.
        </div>

        <!-- Encoded official preview -->
        <div id="winnerCandidatesList" class="mb-4">
          <div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-2"></i> Loading…</div>
        </div>

        <!-- Auto-selected access action -->
        <div id="winnerOutcomeSection">
          <input type="hidden" id="winnerOutcome" value="">
          <label class="form-label fw-semibold">Access Action</label>
          <div id="winnerOutcomeSummary" class="border rounded p-3 bg-light text-muted">
            The system will determine the access action after the official information is loaded.
          </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success" id="btnCompleteTransition">
          <i class="fas fa-check-circle me-1"></i> Complete Access Setup
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL: Quick Actions
══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalQuickActions" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-bolt me-2 text-warning"></i> Quick Actions</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">
          These actions apply directly without a full transition workflow.
        </p>
        <div class="d-grid gap-2">
          <button class="btn btn-outline-success quick-action-btn" id="btnQaRestoreAccess">
            <i class="fas fa-unlock me-2"></i>
            <strong>Restore Access</strong>
            <small class="d-block text-muted fw-normal">Reactivate a suspended or inactive official's account.</small>
          </button>
          <button class="btn btn-outline-primary quick-action-btn" id="btnQaChangeCredentials">
            <i class="fas fa-key me-2"></i>
            <strong>Change Credentials</strong>
            <small class="d-block text-muted fw-normal">Update email, phone, or force a password reset.</small>
          </button>
          <button class="btn btn-outline-warning quick-action-btn" id="btnQaEndActing">
            <i class="fas fa-user-clock me-2"></i>
            <strong>End Acting Assignment</strong>
            <small class="d-block text-muted fw-normal">Restore the original official and remove the acting replacement's access.</small>
          </button>
          <button class="btn btn-outline-secondary quick-action-btn" id="btnQaChangePosition">
            <i class="fas fa-arrows-rotate me-2"></i>
            <strong>Direct Position Change</strong>
            <small class="d-block text-muted fw-normal">Move an official to a different position without an election.</small>
          </button>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL: Restore Access
══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalRestoreAccess" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-unlock me-2 text-success"></i> Restore Official Access</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <input type="text" class="form-control" id="restoreSearch" placeholder="Search by name, ID, or position…">
        </div>
        <div id="restoreOfficialsList">
          <div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-2"></i> Loading…</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL: Change Credentials
══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalChangeCredentials" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-key me-2 text-primary"></i> Change Credentials</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- Step 1: Pick official -->
        <div id="credStep1">
          <div class="mb-3">
            <input type="text" class="form-control" id="credSearch" placeholder="Search official by name or ID…">
          </div>
          <div id="credOfficialsList">
            <div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-2"></i> Loading…</div>
          </div>
        </div>
        <!-- Step 2: Edit fields -->
        <div id="credStep2" class="d-none">
          <p class="fw-semibold mb-3">Editing: <span id="credOfficialNameLabel"></span></p>
          <input type="hidden" id="credOfficialId">
          <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" class="form-control" id="credEmail" placeholder="New email address">
          </div>
          <div class="mb-3">
            <label class="form-label">Phone Number</label>
            <input type="text" class="form-control" id="credPhone" placeholder="New phone number">
          </div>
          <div class="mb-0">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="credForcePasswordReset">
              <label class="form-check-label" for="credForcePasswordReset">
                Force password reset on next login
              </label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button class="btn btn-outline-secondary btn-sm" id="btnCredBack" onclick="otCredStep(1)">
          <i class="fas fa-arrow-left me-1"></i> Back
        </button>
        <button class="btn btn-primary" id="btnCredSave" style="display:none;">
          <i class="fas fa-save me-1"></i> Save Changes
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     TOAST
══════════════════════════════════════════════════════════════════════════ -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
  <div id="otToast" class="toast align-items-center border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body fw-semibold" id="otToastMsg"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<!-- ── Data passed to JS ────────────────────────────────────────────────── -->
<script>
  window.OT_DATA = {
    activeOfficials: <?= json_encode(array_values($activeOfficials), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    councilSeats:    <?= json_encode(array_values($councilSeats),    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    departments: <?= json_encode($departmentOptions, JSON_UNESCAPED_UNICODE) ?>,
    areas: <?= json_encode($areaOptions, JSON_UNESCAPED_UNICODE) ?>
  };
</script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    window.OT_BATCH_SEAT_PREVIEW = <?= json_encode($batchPreviewSeats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <script src="../JS-Script-Files/Admin-End/officialTransitionsScript.js?v=20260323-2"></script>
</body>
</html>
