<?php
require_once "../PhpFiles/General/connection.php";
require_once "../PhpFiles/General/adminModulePermissions.php";
require_once "../PhpFiles/General/audit.php";
require_once "../PhpFiles/General/officialTransitionSettings.php";
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
        amp_replace_official_module_permissions($conn, $officialId, $userId, $permissionKeys, $grantedByUserId);
    }
}

if (!function_exists('ot_upsert_access_profile')) {
    function ot_upsert_access_profile(mysqli $conn, string $officialId, string $userId): void
    {
        amp_upsert_official_access_profile($conn, $officialId, $userId);
    }
}

if (!function_exists('ot_transition_column_exists')) {
    function ot_transition_column_exists(mysqli $conn, string $table, string $column): bool
    {
        $tableEsc = $conn->real_escape_string($table);
        $columnEsc = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
        return $res instanceof mysqli_result && $res->num_rows > 0;
    }
}

if (!function_exists('ot_ensure_transition_schema')) {
    function ot_ensure_transition_schema(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $hasTransitionsTable = $conn->query("SHOW TABLES LIKE 'officialtransitionstbl'");
        if (!($hasTransitionsTable instanceof mysqli_result) || $hasTransitionsTable->num_rows === 0) {
            return;
        }

        $columnDefinitions = [
            'council_id' => "ALTER TABLE officialtransitionstbl ADD COLUMN council_id INT DEFAULT NULL AFTER transition_id",
            'proclamation_date' => "ALTER TABLE officialtransitionstbl ADD COLUMN proclamation_date DATE DEFAULT NULL AFTER batch_label",
            'next_election_date' => "ALTER TABLE officialtransitionstbl ADD COLUMN next_election_date DATE DEFAULT NULL AFTER proclamation_date",
        ];

        foreach ($columnDefinitions as $column => $sql) {
            if (!ot_transition_column_exists($conn, 'officialtransitionstbl', $column)) {
                $conn->query($sql);
            }
        }
    }
}

if (!function_exists('ot_ignored_transition_seat_names')) {
    function ot_ignored_transition_seat_names(): array
    {
        return [
            'SK Chairperson',
            'Lupong Tagapamayapa Member',
            'Barangay Tanod',
            'Barangay Health Worker (BHW)',
            'Day Care Worker',
        ];
    }
}

if (!function_exists('ot_is_managed_transition_seat')) {
    function ot_is_managed_transition_seat(string $seatName): bool
    {
        static $ignored = null;
        if ($ignored === null) {
            $ignored = array_map(
                static fn (string $value): string => strtolower(trim($value)),
                ot_ignored_transition_seat_names()
            );
        }

        $normalized = strtolower(trim($seatName));
        return $normalized !== '' && !in_array($normalized, $ignored, true);
    }
}

if (!function_exists('ot_ignored_transition_seat_sql')) {
    function ot_ignored_transition_seat_sql(mysqli $conn, string $field): string
    {
        $values = array_map(
            static fn (string $value): string => "'" . $conn->real_escape_string(strtolower(trim($value))) . "'",
            ot_ignored_transition_seat_names()
        );
        return 'LOWER(TRIM(' . $field . ')) NOT IN (' . implode(', ', $values) . ')';
    }
}

if (!function_exists('ot_page_decrypt_official_row')) {
    function ot_page_decrypt_official_row(array $row): array
    {
        $row = pii_decrypt_official_row($row) ?? $row;
        $row = pii_decrypt_useraccount_row($row) ?? $row;
        return pii_decrypt_assoc($row, ['firstname', 'middlename', 'lastname', 'suffix']);
    }
}

if (!function_exists('ot_page_format_official_name')) {
    function ot_page_format_official_name(array $row, bool $lastNameFirst = false): string
    {
        $first = trim((string)($row['firstname'] ?? ''));
        $middle = trim((string)($row['middlename'] ?? ''));
        $last = trim((string)($row['lastname'] ?? ''));
        $suffix = trim((string)($row['suffix'] ?? ''));

        if ($lastNameFirst) {
            $parts = [];
            if ($last !== '') {
                $parts[] = $last . ',';
            }
            if ($first !== '') {
                $parts[] = $first;
            }
            if ($middle !== '') {
                $parts[] = $middle;
            }
            if ($suffix !== '') {
                $parts[] = $suffix;
            }
            return trim(implode(' ', $parts), " ,");
        }

        return trim(implode(' ', array_filter([$first, $middle, $last, $suffix], static fn($value): bool => trim((string)$value) !== '')));
    }
}

ot_ensure_transition_schema($conn);

$transitionTool = trim((string)($_GET['tool'] ?? 'current_term'));
if ($transitionTool === '' || in_array($transitionTool, ['tracker', 'new_set', 'past_officials', 'official_permissions', 'kagawad_permissions'], true)) {
    $transitionTool = 'current_term';
}
if (!in_array($transitionTool, ['current_term', 'create_new_term', 'settings'], true)) {
    $transitionTool = 'current_term';
}

$autoOpenNewTermModal = false;
$transitionPageDescription = 'View the current official term, its assigned seats, and the access handovers already in progress.';
if ($transitionTool === 'create_new_term') {
    $transitionPageDescription = 'Create the next official term, generate elected seat handovers, and re-encode the incoming elected winners and appointed officials.';
} elseif ($transitionTool === 'settings') {
    $transitionPageDescription = 'Adjust the reminder and access-revocation timing used by the Official Transition module.';
}
$transitionQueueTitle = $transitionTool === 'create_new_term'
    ? 'New Term Re-encoding Queue'
    : 'Current Term Handover Queue';
$transitionQueueDescription = $transitionTool === 'create_new_term'
    ? 'After creating the term, use this queue to encode elected winners and any appointed officials that still need access setup.'
    : 'Monitor the outgoing and incoming access handovers that belong to the currently active term.';
$scheduleSectionTitle = $transitionTool === 'create_new_term'
    ? 'Term Schedule Templates'
    : 'Official Term-End Schedules';

$officialTransitionFlash = null;
if (!empty($_SESSION['official_transition_flash']) && is_array($_SESSION['official_transition_flash'])) {
    $officialTransitionFlash = $_SESSION['official_transition_flash'];
    unset($_SESSION['official_transition_flash']);
}

$transitionSettingDefinitions = ot_transition_settings_definitions();
$transitionSettings = ot_transition_settings_load($conn);
$transitionSettingLabels = [
    'it_admin' => ot_transition_settings_days_label((int)$transitionSettings['it_admin_update_notice_days'], 'IT Reminder'),
    'outgoing' => ot_transition_settings_days_label((int)$transitionSettings['outgoing_notice_days'], 'Outgoing Notice'),
    'revoke' => ot_transition_settings_days_label((int)$transitionSettings['access_revoke_days'], 'Access Revocation'),
    'post' => ot_transition_settings_days_label((int)$transitionSettings['post_election_followup_days'], 'Post-Election Follow-up'),
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string)($_POST['action'] ?? '') === 'save_notification_settings') {
    $actorUserId = (string)($_SESSION['user_id'] ?? '');

    try {
        $settingsToSave = [];
        foreach ($transitionSettingDefinitions as $key => $definition) {
            $rawValue = trim((string)($_POST[$key] ?? ''));
            if ($rawValue === '' || filter_var($rawValue, FILTER_VALIDATE_INT) === false) {
                throw new RuntimeException('All notification timing values must be whole numbers.');
            }

            $value = (int)$rawValue;
            $min = (int)($definition['min'] ?? 0);
            $max = (int)($definition['max'] ?? 365);
            if ($value < $min || $value > $max) {
                throw new RuntimeException(sprintf('%s must be between %d and %d days.', (string)($definition['label'] ?? $key), $min, $max));
            }
            $settingsToSave[$key] = $value;
        }

        if ($settingsToSave['it_admin_update_notice_days'] < $settingsToSave['outgoing_notice_days']) {
            throw new RuntimeException('The IT administrator reminder should happen on or before the outgoing notice window.');
        }
        if ($settingsToSave['outgoing_notice_days'] < $settingsToSave['access_revoke_days']) {
            throw new RuntimeException('Outgoing notice must be scheduled on or before the privilege revocation day.');
        }

        ot_transition_settings_upsert($conn, $settingsToSave, $actorUserId);

        insertUnifiedAuditLog(
            $conn,
            $actorUserId,
            (string)($_SESSION['role'] ?? 'SuperAdmin'),
            'OfficialTransitions',
            'settings',
            'notification_timing',
            'save_notification_settings',
            'values',
            null,
            json_encode($settingsToSave, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'Updated official transition notification timing settings.'
        );

        $_SESSION['official_transition_flash'] = [
            'type' => 'success',
            'message' => 'Official transition settings updated.',
        ];
    } catch (Throwable $e) {
        $_SESSION['official_transition_flash'] = [
            'type' => 'danger',
            'message' => $e->getMessage(),
        ];
    }

    header('Location: OfficialTransitions.php?tool=settings');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && in_array((string)($_POST['action'] ?? ''), ['save_kagawad_permissions', 'save_official_permissions'], true)) {
    $councilId = (int)($_POST['council_id'] ?? 0);
    $actorUserId = (string)($_SESSION['user_id'] ?? '');

    try {
        if ($councilId <= 0) {
            throw new RuntimeException('Invalid council seat.');
        }

        $stmt = $conn->prepare("
            SELECT
                bc.council_id,
                bc.seat_name,
                bc.seat_group,
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
                oi.term_end,
                ua.role_access AS account_role_access,
                COALESCE(sa.status_name, '') AS account_status
            FROM barangaycounciltbl bc
            LEFT JOIN officialinformationtbl oi ON oi.official_id = bc.current_official_id
            LEFT JOIN useraccountstbl ua ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
            LEFT JOIN statuslookuptbl sa ON sa.status_id = ua.status_id_account
            WHERE bc.is_active = 1
              AND bc.council_id = ?
              AND bc.seat_name NOT LIKE 'Punong Barangay%'
            LIMIT 1
        ");
        if (!$stmt) {
            throw new RuntimeException('Failed to load official record.');
        }
        $stmt->bind_param('i', $councilId);
        $stmt->execute();
        $targetRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $targetRow = $targetRow ? ot_page_decrypt_official_row($targetRow) : null;

        if (!$targetRow) {
            throw new RuntimeException('Council seat not found.');
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
        amp_replace_seat_module_permissions($conn, $councilId, array_keys($permissionMap), $actorUserId);
        amp_upsert_seat_access_profile($conn, $councilId);

        $currentOfficialId = trim((string)($targetRow['official_id'] ?? ''));
        $currentUserId = trim((string)($targetRow['user_id'] ?? ''));
        $currentRole = (string)($targetRow['account_role_access'] ?? $targetRow['info_role_access'] ?? 'Official');
        if ($currentOfficialId !== '' && $currentUserId !== '') {
            amp_apply_seat_permissions_to_official($conn, $councilId, $currentOfficialId, $currentUserId, $actorUserId, $currentRole);
        }
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
                'council_seat',
                (string)$targetRow['council_id'],
                'save_official_permissions',
                'module_permissions',
                null,
                implode(', ', array_keys($permissionMap)),
                'Seat: ' . (string)$targetRow['seat_name'] . ($targetName !== '' ? ' | Current holder: ' . $targetName : ' | Current holder: Vacant')
            );
        }

        $_SESSION['official_transition_flash'] = [
            'type' => 'success',
            'message' => trim((string)($targetRow['official_id'] ?? '')) !== ''
                ? 'Seat module template updated and synced to the current holder.'
                : 'Seat module template updated. It will apply when this seat is filled.',
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

    header('Location: OfficialTransitions.php?tool=current_term');
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
               oi.firstname,
               oi.lastname,
               oi.middlename,
               oi.suffix,
               COALESCE(oi.position_access, oi.role_access) AS current_position_access,
               oi.department,
               oi.area_number,
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
        while ($r = $csRes->fetch_assoc()) {
            $r = ot_page_decrypt_official_row($r);
            $r['current_official_name'] = ot_page_format_official_name($r, true);
            $councilSeats[] = $r;
        }
        $csRes->close();
    }
}
$councilSeats = array_values(array_filter(
    $councilSeats,
    static fn (array $seat): bool => ot_is_managed_transition_seat((string)($seat['seat_name'] ?? ''))
));

$otFormatDateLabel = static function (?string $value): string {
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00') {
        return '—';
    }
    $ts = strtotime($value);
    return $ts ? date('M d, Y', $ts) : '—';
};
$councilSeatsByGroup = [];
$appointedPreviewSeats = [];
$currentTermSeatCount = 0;
$currentTermFilledCount = 0;
$currentTermVacantCount = 0;
$currentTermElectedCount = 0;
$currentTermAppointedCount = 0;
$batchPreviewSeats = [];

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
    while ($r = $aoRes->fetch_assoc()) {
        $activeOfficials[] = ot_page_decrypt_official_row($r);
    }
    $aoRes->close();
}

$hasTransTbl = $conn->query("SHOW TABLES LIKE 'officialtransitionstbl'")->num_rows > 0;

// ── Election schedules ────────────────────────────────────────────────────────
$electionSchedules = [];
if ($hasTransTbl) {
    $ignoredPositionSql = ot_ignored_transition_seat_sql($conn, 'position');
    $elRes = $conn->query("
        SELECT
               batch_label,
               COALESCE(MAX(proclamation_date), MAX(effective_date)) AS proclamation_date,
               COALESCE(MAX(next_election_date), MAX(election_date)) AS next_election_date,
               MAX(notify_3mo_sent)       AS n3,
               MAX(notify_1mo_sent)       AS n1,
               MAX(deactivated_7d_before) AS n7,
               MAX(notify_post_sent)      AS np,
               COUNT(*)                   AS position_count,
               SUM(status='Completed')    AS completed_count
        FROM officialtransitionstbl
        WHERE (next_election_date IS NOT NULL OR election_date IS NOT NULL)
          AND batch_label IS NOT NULL
          AND {$ignoredPositionSql}
        GROUP BY
            batch_label,
            COALESCE(next_election_date, election_date),
            COALESCE(proclamation_date, effective_date)
        ORDER BY COALESCE(next_election_date, election_date) DESC
    ");
    if ($elRes instanceof mysqli_result) {
        while ($r = $elRes->fetch_assoc()) {
            $electionSchedules[] = $r;
        }
        $elRes->close();
    }
}

$currentTermEditableSchedule = null;
$closestUpcomingTs = null;
$todayTs = strtotime(date('Y-m-d'));
foreach ($electionSchedules as $schedule) {
    $scheduleDate = trim((string)($schedule['next_election_date'] ?? ''));
    $scheduleTs = $scheduleDate !== '' ? strtotime($scheduleDate) : false;
    if ($scheduleTs === false || $scheduleTs < $todayTs) {
        continue;
    }
    if ($closestUpcomingTs === null || $scheduleTs < $closestUpcomingTs) {
        $closestUpcomingTs = $scheduleTs;
        $currentTermEditableSchedule = $schedule;
    }
}
if ($currentTermEditableSchedule === null && !empty($electionSchedules)) {
    $currentTermEditableSchedule = $electionSchedules[0];
}
$newTermEditableSchedule = $electionSchedules[0] ?? null;
$termEditSchedule = $transitionTool === 'create_new_term'
    ? $newTermEditableSchedule
    : $currentTermEditableSchedule;
$hasConfiguredTermSchedule = !empty($electionSchedules);

if (!$hasConfiguredTermSchedule) {
    foreach ($councilSeats as &$seat) {
        $seat['current_official_id'] = '';
        $seat['current_position_access'] = '';
        $seat['department'] = '';
        $seat['area_number'] = '';
        $seat['current_official_name'] = '';
        $seat['account_status'] = '';
        $seat['term_start'] = '';
        $seat['term_end'] = '';
    }
    unset($seat);
    $activeOfficials = [];
}

foreach ($councilSeats as $cs) {
    $councilSeatsByGroup[$cs['seat_group']][] = $cs;
}

$appointedPreviewSeats = array_values(array_filter(
    $councilSeats,
    static fn (array $seat): bool => strcasecmp((string)($seat['selection_method'] ?? ''), 'Elected') !== 0
));

$currentTermSeatCount = count($councilSeats);
$currentTermFilledCount = count(array_filter(
    $councilSeats,
    static fn (array $seat): bool => trim((string)($seat['current_official_id'] ?? '')) !== ''
));
$currentTermVacantCount = max($currentTermSeatCount - $currentTermFilledCount, 0);
$currentTermElectedCount = count(array_filter(
    $councilSeats,
    static fn (array $seat): bool => strcasecmp((string)($seat['selection_method'] ?? ''), 'Elected') === 0
));
$currentTermAppointedCount = max($currentTermSeatCount - $currentTermElectedCount, 0);

foreach ($councilSeats as $cs) {
    $selectionMethod = (string)($cs['selection_method'] ?? '');
    if ($selectionMethod !== 'Elected') {
        continue;
    }
    $batchPreviewSeats[] = $cs;
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
$officialPermissionCatalog = ot_filter_catalog_for_regular_officials(amp_get_permission_catalog());

$seatAccessOfficials = [];
if ($hasCouncilTbl) {
    $kgStmt = $conn->prepare("
        SELECT
            bc.council_id,
            bc.seat_name,
            bc.seat_group,
            bc.selection_method,
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
          AND bc.seat_name NOT LIKE 'Punong Barangay%'
        ORDER BY bc.sort_order, bc.council_id
    ");
    if ($kgStmt) {
        $kgStmt->execute();
        $kgRes = $kgStmt->get_result();
        while ($row = $kgRes->fetch_assoc()) {
            $row = ot_page_decrypt_official_row($row);
            if (!ot_is_managed_transition_seat((string)($row['seat_name'] ?? ''))) {
                continue;
            }
            $officialId = $hasConfiguredTermSchedule ? trim((string)($row['official_id'] ?? '')) : '';
            $councilId = (int)($row['council_id'] ?? 0);
            $seatRole = (string)($row['account_role_access'] ?? $row['info_role_access'] ?? 'Official');
            $permissionMap = amp_get_effective_permission_keys_for_council($conn, $councilId, $seatRole);
            $fullName = trim(
                (string)($row['firstname'] ?? '') . ' ' .
                ((string)($row['middlename'] ?? '') !== '' ? (string)($row['middlename'] ?? '') . ' ' : '') .
                (string)($row['lastname'] ?? '') .
                ((string)($row['suffix'] ?? '') !== '' ? ' ' . (string)($row['suffix'] ?? '') : '')
            );

            $seatAccessOfficials[] = [
                'council_id' => (int)($row['council_id'] ?? 0),
                'seat_name' => (string)($row['seat_name'] ?? ''),
                'seat_group' => (string)($row['seat_group'] ?? ''),
                'selection_method' => (string)($row['selection_method'] ?? ''),
                'official_id' => $officialId,
                'user_id' => $hasConfiguredTermSchedule ? (string)($row['user_id'] ?? '') : '',
                'full_name' => ($hasConfiguredTermSchedule && $fullName !== '') ? $fullName : 'Vacant',
                'position_access' => $hasConfiguredTermSchedule ? (string)($row['position_access'] ?? '') : '',
                'department' => $hasConfiguredTermSchedule ? (string)($row['department'] ?? '') : '',
                'area_number' => $hasConfiguredTermSchedule ? (string)($row['area_number'] ?? '') : '',
                'term_end' => $hasConfiguredTermSchedule ? (string)($row['term_end'] ?? '') : '',
                'email' => $hasConfiguredTermSchedule ? (string)($row['email'] ?? '') : '',
                'phone_number' => $hasConfiguredTermSchedule ? (string)($row['phone_number'] ?? '') : '',
                'account_status' => $hasConfiguredTermSchedule ? (string)($row['account_status'] ?? '') : '',
                'has_saved_template' => amp_has_saved_seat_access_profile($conn, $councilId),
                'permission_keys' => array_keys($permissionMap),
                'permission_count' => count($permissionMap),
                'has_official' => $hasConfiguredTermSchedule && $officialId !== '',
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
  <title>Official Transition</title>

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
    .ot-overview-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
      gap: 1rem;
    }
    .ot-overview-card {
      background: #fff;
      border: 1px solid #dbe4f0;
      border-radius: 1rem;
      padding: 1rem 1.1rem;
      box-shadow: 0 .35rem .9rem rgba(15, 23, 42, .05);
    }
    .ot-overview-label {
      font-size: .78rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: #64748b;
      margin-bottom: .4rem;
    }
    .ot-overview-value {
      font-size: 1.8rem;
      font-weight: 700;
      color: #0f172a;
      line-height: 1;
    }
    .ot-overview-note {
      margin-top: .45rem;
      font-size: .82rem;
      color: #64748b;
    }
    .ot-roster-table td,
    .ot-seat-preview-table td {
      white-space: normal;
      vertical-align: middle;
    }
    .ot-workflow-steps {
      display: grid;
      gap: .85rem;
    }
    .ot-workflow-step {
      display: flex;
      gap: .85rem;
      align-items: flex-start;
      padding: .9rem 1rem;
      border: 1px solid #e5e7eb;
      border-radius: .95rem;
      background: #fcfcfd;
    }
    .ot-workflow-step-index {
      flex: 0 0 auto;
      width: 2rem;
      height: 2rem;
      border-radius: 999px;
      background: #eaf2ff;
      color: #0d6efd;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: .95rem;
    }
    .ot-table-toolbar {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: .75rem;
      flex-wrap: wrap;
      width: 100%;
    }
    .ot-table-toolbar .input-group {
      flex: 1 1 420px;
      min-width: 280px;
      max-width: 720px;
    }
    .ot-table-toolbar-controls {
      display: flex;
      align-items: center;
      gap: .75rem;
      flex-wrap: wrap;
      justify-content: flex-end;
      flex: 0 0 auto;
    }
    .ot-table-toolbar-controls .form-select {
      width: 180px;
    }
    .ot-table-toolbar-controls .btn {
      flex: 0 0 auto;
    }
    @media (max-width: 767.98px) {
      .ot-table-toolbar {
        justify-content: stretch;
        align-items: stretch;
      }
      .ot-table-toolbar .input-group {
        flex-basis: 100%;
        min-width: 0;
        max-width: none;
      }
      .ot-table-toolbar-controls {
        width: 100%;
        justify-content: stretch;
      }
      .ot-table-toolbar-controls .form-select,
      .ot-table-toolbar-controls .btn {
        width: 100%;
      }
    }

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

    /* ── Official access cards ── */
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
<body data-ot-tool="<?= htmlspecialchars($transitionTool, ENT_QUOTES, 'UTF-8') ?>"
      data-ot-autostart="<?= $autoOpenNewTermModal ? 'new-batch' : '' ?>">
<div class="d-flex flex-column flex-md-row" style="min-height:100vh;">
  <?php include "includes/sidebar.php"; ?>

  <main class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light" id="main-display">

    <!-- ══════════════════════════════════════════════════════════ HEADER -->
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
      <div>
        <h2 style="font-family:'Charis SIL Bold';color:#DE710C;">
          Official Transition
        </h2>
        <p class="text-muted mb-0" style="font-size:.9rem;">
          <?= htmlspecialchars($transitionPageDescription, ENT_QUOTES, 'UTF-8') ?>
        </p>
      </div>
      <?php if ($transitionTool === 'current_term'): ?>
      <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-outline-secondary btn-sm" id="btnOtQuickActions"
                data-bs-toggle="modal" data-bs-target="#modalQuickActions"
                <?= !$hasConfiguredTermSchedule ? 'disabled' : '' ?>>
          <i class="fas fa-bolt me-1"></i> Access Actions
        </button>
        <a class="btn btn-outline-primary btn-sm" href="OfficialTransitions.php?tool=create_new_term">
          <i class="fas fa-layer-group me-1"></i> Create New Term
        </a>
        <button class="btn btn-primary btn-sm" id="btnNewTransition"
                data-bs-toggle="modal" data-bs-target="#modalNewTransition"
                <?= !$hasConfiguredTermSchedule ? 'disabled' : '' ?>>
          <i class="fas fa-plus me-1"></i> New Official Handover
        </button>
      </div>
      <?php endif; ?>
    </div>
    <hr class="mb-4">

    <?php if (!empty($officialTransitionFlash['message'])): ?>
      <div class="alert alert-<?= htmlspecialchars((string)($officialTransitionFlash['type'] ?? 'info'), ENT_QUOTES, 'UTF-8') ?> mb-4">
        <?= htmlspecialchars((string)$officialTransitionFlash['message'], ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>
    <?php if ($transitionTool === 'current_term' && !$hasConfiguredTermSchedule): ?>
      <div class="alert alert-warning mb-4">
        No term is configured yet. Current officials are treated as inactive until a term is created.
      </div>
    <?php endif; ?>

    <?php if ($transitionTool === 'settings'): ?>
    <div class="row g-4 mb-4">
      <div class="col-md-6 col-xl-3">
        <div class="ot-overview-card h-100">
          <div class="ot-overview-label">IT Reminder</div>
          <div class="ot-overview-value"><?= (int)$transitionSettings['it_admin_update_notice_days'] ?></div>
          <div class="ot-overview-note">Days before election to remind the IT Administrator to review the term schedule.</div>
        </div>
      </div>
      <div class="col-md-6 col-xl-3">
        <div class="ot-overview-card h-100">
          <div class="ot-overview-label">Outgoing Notice</div>
          <div class="ot-overview-value"><?= (int)$transitionSettings['outgoing_notice_days'] ?></div>
          <div class="ot-overview-note">Days before election to notify outgoing officials that their access will be revoked.</div>
        </div>
      </div>
      <div class="col-md-6 col-xl-3">
        <div class="ot-overview-card h-100">
          <div class="ot-overview-label">Access Revocation</div>
          <div class="ot-overview-value"><?= (int)$transitionSettings['access_revoke_days'] ?></div>
          <div class="ot-overview-note">Days before election when outgoing official privileges are revoked automatically.</div>
        </div>
      </div>
      <div class="col-md-6 col-xl-3">
        <div class="ot-overview-card h-100">
          <div class="ot-overview-label">Post-Election Follow-up</div>
          <div class="ot-overview-value"><?= (int)$transitionSettings['post_election_followup_days'] ?></div>
          <div class="ot-overview-note">Days after election to remind the IT Administrator to review unresolved transitions.</div>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-3 shadow-sm border mb-4">
      <div class="p-3 border-bottom">
        <div class="fw-semibold">Notification Timing Settings</div>
        <div class="small text-muted">Set how early reminders are sent before the election date and when outgoing access is revoked.</div>
      </div>
      <form method="post" class="p-3">
        <input type="hidden" name="action" value="save_notification_settings">
        <div class="row g-4">
          <?php foreach ($transitionSettingDefinitions as $settingKey => $settingDefinition): ?>
            <?php
              $settingValue = (int)($transitionSettings[$settingKey] ?? $settingDefinition['default'] ?? 0);
              $settingMin = (int)($settingDefinition['min'] ?? 0);
              $settingMax = (int)($settingDefinition['max'] ?? 365);
            ?>
            <div class="col-12 col-lg-6">
              <label class="form-label fw-semibold" for="<?= htmlspecialchars($settingKey, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars((string)($settingDefinition['label'] ?? $settingKey), ENT_QUOTES, 'UTF-8') ?>
              </label>
              <div class="input-group">
                <input
                  type="number"
                  class="form-control"
                  id="<?= htmlspecialchars($settingKey, ENT_QUOTES, 'UTF-8') ?>"
                  name="<?= htmlspecialchars($settingKey, ENT_QUOTES, 'UTF-8') ?>"
                  min="<?= $settingMin ?>"
                  max="<?= $settingMax ?>"
                  step="1"
                  value="<?= $settingValue ?>"
                  required
                >
                <span class="input-group-text">days</span>
              </div>
              <div class="form-text">
                <?= htmlspecialchars((string)($settingDefinition['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="alert alert-info small mt-4 mb-0">
          Recommended order: IT reminder should be the earliest window, outgoing notice should come before access revocation, and access revocation must stay at 1 day or more before election day.
        </div>
        <div class="d-flex justify-content-end mt-4">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Save Settings
          </button>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <?php if ($transitionTool === 'current_term'): ?>
    <div class="ot-overview-grid mb-4">
      <div class="ot-overview-card">
        <div class="ot-overview-label">Council Seats</div>
        <div class="ot-overview-value"><?= (int)$currentTermSeatCount ?></div>
        <div class="ot-overview-note">Active seats in the current council structure.</div>
      </div>
      <div class="ot-overview-card">
        <div class="ot-overview-label">Seats Filled</div>
        <div class="ot-overview-value"><?= (int)$currentTermFilledCount ?></div>
        <div class="ot-overview-note">Seats that already have an assigned current official.</div>
      </div>
      <div class="ot-overview-card">
        <div class="ot-overview-label">Vacant Seats</div>
        <div class="ot-overview-value"><?= (int)$currentTermVacantCount ?></div>
        <div class="ot-overview-note">Seats that still need a current term official.</div>
      </div>
      <div class="ot-overview-card">
        <div class="ot-overview-label">Elected Seats</div>
        <div class="ot-overview-value"><?= (int)$currentTermElectedCount ?></div>
        <div class="ot-overview-note">Seats that normally change through election-based term turnover.</div>
      </div>
      <div class="ot-overview-card">
        <div class="ot-overview-label">Appointed Seats</div>
        <div class="ot-overview-value"><?= (int)$currentTermAppointedCount ?></div>
        <div class="ot-overview-note">Seats that are encoded through appointment or reappointment.</div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════ TERM DETAILS -->
    <div class="bg-white rounded-3 shadow-sm border mb-4">
      <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
        <div class="d-flex align-items-center gap-2">
          <i class="fas fa-calendar-alt text-primary"></i>
          <span class="fw-semibold"><?= htmlspecialchars($scheduleSectionTitle, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAddElection"
                <?= $termEditSchedule === null ? 'disabled' : '' ?>>
          <i class="fas fa-edit me-1"></i> Edit Term Details
        </button>
      </div>
      <div class="p-3">
        <?php if (empty($electionSchedules)): ?>
          <p class="text-muted mb-0 small text-center py-2">No term transition dates scheduled.</p>
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
                  <th>Term / Label</th>
                  <th>Proclamation Date</th>
                  <th>Next Election Date</th>
                  <th>Positions</th>
                  <th><?= htmlspecialchars($transitionSettingLabels['it_admin'], ENT_QUOTES, 'UTF-8') ?></th>
                  <th><?= htmlspecialchars($transitionSettingLabels['outgoing'], ENT_QUOTES, 'UTF-8') ?></th>
                  <th><?= htmlspecialchars($transitionSettingLabels['revoke'], ENT_QUOTES, 'UTF-8') ?></th>
                  <th><?= htmlspecialchars($transitionSettingLabels['post'], ENT_QUOTES, 'UTF-8') ?></th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($electionSchedules as $es): ?>
                  <?php
                    $proclamationDate = (string)($es['proclamation_date'] ?? '');
                    $nextElectionDate = (string)($es['next_election_date'] ?? '');
                    $label = htmlspecialchars($es['batch_label'] ?? '', ENT_QUOTES, 'UTF-8');
                    $daysUntil = $nextElectionDate !== '' ? (int)round((strtotime($nextElectionDate) - time()) / 86400) : 0;
                    $isPast = $daysUntil < 0;
                    $proclamationFmt = $proclamationDate !== '' ? date('M d, Y', strtotime($proclamationDate)) : '—';
                    $nextElectionFmt = $nextElectionDate !== '' ? date('M d, Y', strtotime($nextElectionDate)) : '—';
                  ?>
                  <tr>
                    <td class="fw-semibold"><?= $label ?></td>
                    <td><?= htmlspecialchars($proclamationFmt, ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                      <?= htmlspecialchars($nextElectionFmt, ENT_QUOTES, 'UTF-8') ?>
                      <?php if ($nextElectionDate !== '' && $daysUntil > 0): ?>
                        <span class="badge bg-secondary ms-1"><?= $daysUntil ?>d away</span>
                      <?php elseif ($nextElectionDate !== '' && $daysUntil === 0): ?>
                        <span class="badge bg-warning text-dark ms-1">Today</span>
                      <?php elseif ($nextElectionDate !== ''): ?>
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
                              onclick="otResendNotif(<?= htmlspecialchars(json_encode($es['batch_label']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($nextElectionDate), ENT_QUOTES, 'UTF-8') ?>)"
                              title="Manually trigger pending notifications">
                        <i class="fas fa-redo fa-xs"></i>
                      </button>
                      <button class="btn btn-xs btn-outline-danger py-0 px-2"
                              onclick="otDeleteSchedule(<?= htmlspecialchars(json_encode($es['batch_label']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($nextElectionDate), ENT_QUOTES, 'UTF-8') ?>)"
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

    <div class="bg-white rounded-3 shadow-sm border mb-4">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 p-3 border-bottom">
        <div>
          <div class="fw-semibold">Current Term Officials</div>
          <div class="small text-muted">View the official currently assigned to each active council seat.</div>
        </div>
      </div>
      <div class="p-3">
        <?php if (empty($councilSeats)): ?>
          <div class="text-center text-muted py-4">No active council seats were found for the current term.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 ot-roster-table">
              <thead class="table-light">
                <tr>
                  <th>Seat</th>
                  <th>Selection</th>
                  <th>Current Official</th>
                  <th>Access Profile</th>
                  <th>Department</th>
                  <th>Account Status</th>
                  <th>Term Dates</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($councilSeats as $seat): ?>
                  <?php
                    $holderName = trim((string)($seat['current_official_name'] ?? ''));
                    $selectionMethod = trim((string)($seat['selection_method'] ?? ''));
                    $selectionClass = strcasecmp($selectionMethod, 'Elected') === 0
                        ? 'bg-primary-subtle text-primary-emphasis border border-primary-subtle'
                        : 'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle';
                    $accountStatus = trim((string)($seat['account_status'] ?? ''));
                    $accountClass = 'bg-light text-dark';
                    if (stripos($accountStatus, 'active') !== false || stripos($accountStatus, 'acting') !== false) {
                        $accountClass = 'bg-success';
                    } elseif (stripos($accountStatus, 'suspend') !== false) {
                        $accountClass = 'bg-warning text-dark';
                    } elseif (stripos($accountStatus, 'inactive') !== false || stripos($accountStatus, 'disabled') !== false || stripos($accountStatus, 'revoked') !== false) {
                        $accountClass = 'bg-secondary';
                    }
                    $termStartLabel = $otFormatDateLabel((string)($seat['term_start'] ?? ''));
                    $termEndLabel = $otFormatDateLabel((string)($seat['term_end'] ?? ''));
                    $positionAccess = trim((string)($seat['current_position_access'] ?? ''));
                    $departmentLabel = trim((string)($seat['department'] ?? ''));
                  ?>
                  <tr>
                    <td class="fw-semibold"><?= htmlspecialchars((string)($seat['seat_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                      <span class="badge <?= htmlspecialchars($selectionClass, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($selectionMethod !== '' ? $selectionMethod : '—', ENT_QUOTES, 'UTF-8') ?>
                      </span>
                    </td>
                    <td>
                      <?php if ($holderName !== ''): ?>
                        <?= htmlspecialchars($holderName, ENT_QUOTES, 'UTF-8') ?>
                      <?php else: ?>
                        <span class="text-muted fst-italic">Vacant</span>
                      <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($positionAccess !== '' ? $positionAccess : '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($departmentLabel !== '' ? $departmentLabel : '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                      <?php if ($accountStatus !== ''): ?>
                        <span class="badge <?= htmlspecialchars($accountClass, ENT_QUOTES, 'UTF-8') ?>">
                          <?= htmlspecialchars($accountStatus, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                      <?php else: ?>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div><?= htmlspecialchars($termStartLabel, ENT_QUOTES, 'UTF-8') ?></div>
                      <div class="small text-muted">to <?= htmlspecialchars($termEndLabel, ENT_QUOTES, 'UTF-8') ?></div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php elseif ($transitionTool === 'create_new_term'): ?>
    <div class="row g-4 mb-4">
      <div class="col-xl-7">
        <div class="bg-white rounded-3 shadow-sm border h-100">
          <div class="p-3 border-bottom d-flex align-items-start justify-content-between flex-wrap gap-3">
            <div>
              <div class="fw-semibold">Create New Term Workflow</div>
              <div class="small text-muted">Use this page to prepare the next term and encode the incoming officials who will receive access.</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <button class="btn btn-primary btn-sm" id="btnNewBatch"
                      data-bs-toggle="modal" data-bs-target="#modalNewBatch">
                <i class="fas fa-layer-group me-1"></i> Create New Term
              </button>
              <button class="btn btn-outline-primary btn-sm" id="btnNewTransition"
                      data-bs-toggle="modal" data-bs-target="#modalNewTransition">
                <i class="fas fa-user-plus me-1"></i> Encode Appointed Official
              </button>
            </div>
          </div>
          <div class="p-3">
            <div class="ot-workflow-steps">
              <div class="ot-workflow-step">
                <span class="ot-workflow-step-index">1</span>
                <div>
                  <div class="fw-semibold">Create the term record</div>
                  <div class="small text-muted">Set the term label, proclamation date, and next election date for the incoming term.</div>
                </div>
              </div>
              <div class="ot-workflow-step">
                <span class="ot-workflow-step-index">2</span>
                <div>
                  <div class="fw-semibold">Encode elected winners</div>
                  <div class="small text-muted">The system will auto-generate handover rows for the elected seats below. Open each row and encode the winner’s official details.</div>
                </div>
              </div>
              <div class="ot-workflow-step">
                <span class="ot-workflow-step-index">3</span>
                <div>
                  <div class="fw-semibold">Encode appointed officials</div>
                  <div class="small text-muted">Use <span class="fw-semibold">Encode Appointed Official</span> for appointment and reappointment seats that are not auto-generated by the new term record.</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-xl-5">
        <div class="bg-white rounded-3 shadow-sm border h-100">
          <div class="p-3 border-bottom">
            <div class="fw-semibold">New Term Snapshot</div>
            <div class="small text-muted">What gets created automatically and what still needs manual encoding.</div>
          </div>
          <div class="p-3">
            <div class="ot-overview-grid">
              <div class="ot-overview-card">
                <div class="ot-overview-label">Auto-generated Seats</div>
                <div class="ot-overview-value"><?= (int)count($batchPreviewSeats) ?></div>
                <div class="ot-overview-note">Elected seats that will receive transition rows when the term is created.</div>
              </div>
              <div class="ot-overview-card">
                <div class="ot-overview-label">Manual Seats</div>
                <div class="ot-overview-value"><?= (int)count($appointedPreviewSeats) ?></div>
                <div class="ot-overview-note">Appointed or reappointed seats to encode manually afterward.</div>
              </div>
            </div>
            <div class="alert alert-info small mt-3 mb-0">
              <i class="fas fa-info-circle me-1"></i>
              Create New Term handles the elected winners queue. Appointed officials still go through the handover form using <span class="fw-semibold">Appointment</span> or <span class="fw-semibold">Reappointment</span>.
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4 mb-4">
      <div class="col-xl-7">
        <div class="bg-white rounded-3 shadow-sm border h-100">
          <div class="p-3 border-bottom">
            <div class="fw-semibold">Elected Seats To Generate</div>
            <div class="small text-muted">These seats are included automatically when you create the next term.</div>
          </div>
          <div class="p-3">
            <?php if (empty($batchPreviewSeats)): ?>
              <div class="text-center text-muted py-4">No elected seats are configured in the active council structure yet.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 ot-seat-preview-table">
                  <thead class="table-light">
                    <tr>
                      <th>Seat</th>
                      <th>Current Holder</th>
                      <th>Account</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($batchPreviewSeats as $seat): ?>
                      <?php
                        $holderName = trim((string)($seat['current_official_name'] ?? ''));
                        $accountStatus = trim((string)($seat['account_status'] ?? ''));
                        $accountClass = 'bg-light text-dark';
                        if (stripos($accountStatus, 'active') !== false || stripos($accountStatus, 'acting') !== false) {
                            $accountClass = 'bg-success';
                        } elseif (stripos($accountStatus, 'suspend') !== false) {
                            $accountClass = 'bg-warning text-dark';
                        } elseif (stripos($accountStatus, 'inactive') !== false || stripos($accountStatus, 'disabled') !== false || stripos($accountStatus, 'revoked') !== false) {
                            $accountClass = 'bg-secondary';
                        }
                      ?>
                      <tr>
                        <td class="fw-semibold"><?= htmlspecialchars((string)($seat['seat_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                          <?php if ($holderName !== ''): ?>
                            <?= htmlspecialchars($holderName, ENT_QUOTES, 'UTF-8') ?>
                          <?php else: ?>
                            <span class="text-muted fst-italic">Vacant</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if ($accountStatus !== ''): ?>
                            <span class="badge <?= htmlspecialchars($accountClass, ENT_QUOTES, 'UTF-8') ?>">
                              <?= htmlspecialchars($accountStatus, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                          <?php else: ?>
                            <span class="text-muted">—</span>
                          <?php endif; ?>
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
      <div class="col-xl-5">
        <div class="bg-white rounded-3 shadow-sm border h-100">
          <div class="p-3 border-bottom">
            <div class="fw-semibold">Appointed Seats To Encode Manually</div>
            <div class="small text-muted">These seats stay manual and should be encoded through Appointment or Reappointment.</div>
          </div>
          <div class="p-3">
            <?php if (empty($appointedPreviewSeats)): ?>
              <div class="text-center text-muted py-4">No appointed seats were found in the active council structure.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 ot-seat-preview-table">
                  <thead class="table-light">
                    <tr>
                      <th>Seat</th>
                      <th>Current Holder</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($appointedPreviewSeats as $seat): ?>
                      <?php $holderName = trim((string)($seat['current_official_name'] ?? '')); ?>
                      <tr>
                        <td class="fw-semibold"><?= htmlspecialchars((string)($seat['seat_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                          <?php if ($holderName !== ''): ?>
                            <?= htmlspecialchars($holderName, ENT_QUOTES, 'UTF-8') ?>
                          <?php else: ?>
                            <span class="text-muted fst-italic">Vacant</span>
                          <?php endif; ?>
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
    </div>
    <?php endif; ?>

    <?php if ($transitionTool === 'create_new_term'): ?>
    <!-- ══════════════════════════════════════════════════════════ TERM DETAILS -->
    <div class="bg-white rounded-3 shadow-sm border mb-4">
      <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
        <div class="d-flex align-items-center gap-2">
          <i class="fas fa-calendar-alt text-primary"></i>
          <span class="fw-semibold"><?= htmlspecialchars($scheduleSectionTitle, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalNewBatch">
          <i class="fas fa-plus me-1"></i> Add Term Details
        </button>
      </div>
      <div class="p-3">
        <?php if (empty($electionSchedules)): ?>
          <p class="text-muted mb-0 small text-center py-2">No term transition dates scheduled.</p>
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
                  <th>Term / Label</th>
                  <th>Proclamation Date</th>
                  <th>Next Election Date</th>
                  <th>Positions</th>
                  <th><?= htmlspecialchars($transitionSettingLabels['it_admin'], ENT_QUOTES, 'UTF-8') ?></th>
                  <th><?= htmlspecialchars($transitionSettingLabels['outgoing'], ENT_QUOTES, 'UTF-8') ?></th>
                  <th><?= htmlspecialchars($transitionSettingLabels['revoke'], ENT_QUOTES, 'UTF-8') ?></th>
                  <th><?= htmlspecialchars($transitionSettingLabels['post'], ENT_QUOTES, 'UTF-8') ?></th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($electionSchedules as $es): ?>
                  <?php
                    $proclamationDate = (string)($es['proclamation_date'] ?? '');
                    $nextElectionDate = (string)($es['next_election_date'] ?? '');
                    $label = htmlspecialchars($es['batch_label'] ?? '', ENT_QUOTES, 'UTF-8');
                    $daysUntil = $nextElectionDate !== '' ? (int)round((strtotime($nextElectionDate) - time()) / 86400) : 0;
                    $isPast = $daysUntil < 0;
                    $proclamationFmt = $proclamationDate !== '' ? date('M d, Y', strtotime($proclamationDate)) : '—';
                    $nextElectionFmt = $nextElectionDate !== '' ? date('M d, Y', strtotime($nextElectionDate)) : '—';
                  ?>
                  <tr>
                    <td class="fw-semibold"><?= $label ?></td>
                    <td><?= htmlspecialchars($proclamationFmt, ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                      <?= htmlspecialchars($nextElectionFmt, ENT_QUOTES, 'UTF-8') ?>
                      <?php if ($nextElectionDate !== '' && $daysUntil > 0): ?>
                        <span class="badge bg-secondary ms-1"><?= $daysUntil ?>d away</span>
                      <?php elseif ($nextElectionDate !== '' && $daysUntil === 0): ?>
                        <span class="badge bg-warning text-dark ms-1">Today</span>
                      <?php elseif ($nextElectionDate !== ''): ?>
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
                              onclick="otResendNotif(<?= htmlspecialchars(json_encode($es['batch_label']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($nextElectionDate), ENT_QUOTES, 'UTF-8') ?>)"
                              title="Manually trigger pending notifications">
                        <i class="fas fa-redo fa-xs"></i>
                      </button>
                      <button class="btn btn-xs btn-outline-danger py-0 px-2"
                              onclick="otDeleteSchedule(<?= htmlspecialchars(json_encode($es['batch_label']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($nextElectionDate), ENT_QUOTES, 'UTF-8') ?>)"
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
    <?php endif; ?>

    <?php if ($transitionTool === 'create_new_term'): ?>
    <!-- ══════════════════════════════════════════════════════════ TRANSITIONS TABLE -->
    <div class="bg-white rounded-3 shadow-sm border">
      <!-- Toolbar -->
      <div class="p-3 border-bottom">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
          <div>
            <div class="fw-semibold"><?= htmlspecialchars($transitionQueueTitle, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="small text-muted"><?= htmlspecialchars($transitionQueueDescription, ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div class="ot-table-toolbar">
            <div class="input-group">
              <input type="text" class="form-control form-control-sm" id="otSearch"
                     placeholder="Search position, official, term…">
              <span class="input-group-text bg-white"><i class="fas fa-search fa-xs"></i></span>
            </div>
            <div class="ot-table-toolbar-controls">
              <select class="form-select form-select-sm" id="otTypeFilter">
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
              <th>Term / Election</th>
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
     MODAL: New Official Handover
══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalNewTransition" tabindex="-1" aria-labelledby="modalNewTransitionLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalNewTransitionLabel">
          <i class="fas fa-plus-circle me-2 text-primary"></i> New Official Handover
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
                  <div><span class="text-muted">Outgoing official:</span> <strong id="ntSeatHolder"></strong>
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
                     placeholder="e.g. 2025 Election Batch">
            </div>
            <!-- Proclamation Date (election types only) -->
            <div class="col-12 col-md-6" id="ntProclamationDateWrap" style="display:none;">
              <label class="form-label fw-semibold">Proclamation Date</label>
              <input type="date" class="form-control" name="proclamation_date" id="ntProclamationDate">
            </div>
            <!-- Next Election Date (election types only) -->
            <div class="col-12 col-md-6" id="ntNextElectionDateWrap" style="display:none;">
              <label class="form-label fw-semibold">Next Election Date</label>
              <input type="date" class="form-control" name="next_election_date" id="ntNextElectionDate">
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
            <i class="fas fa-save me-1"></i> Create Official Handover
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL: Create New Term
══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalNewBatch" tabindex="-1" aria-labelledby="modalNewBatchLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalNewBatchLabel">
          <i class="fas fa-layer-group me-2 text-primary"></i> Create New Term
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="formNewBatch" novalidate>
        <div class="modal-body">
          <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
              <label class="form-label fw-semibold">Term Label <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="batch_label" id="nbLabel" required
                     placeholder="e.g. 2025-2028 Barangay Term">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label fw-semibold">Proclamation Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" name="proclamation_date" id="nbProclamationDate" required>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label fw-semibold">Next Election Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" name="next_election_date" id="nbNextElectionDate" required>
            </div>
          </div>

          <p class="fw-semibold mb-2">Included council seats:</p>
          <div class="alert alert-info py-2 small mb-3">
            <i class="fas fa-info-circle me-1"></i>
            The system will automatically include every elected seat in the active council structure. Outgoing officials are detected per seat automatically for the new term.
          </div>
          <?php if (empty($batchPreviewSeats)): ?>
            <div class="alert alert-warning small">No council seats found. Run the migration first.</div>
          <?php else: ?>
          <div id="nbAutoSeatPreviewEmpty" class="alert alert-light border small mb-0">
            The elected seats for this batch are shown below.
          </div>
          <div class="table-responsive" id="nbAutoSeatPreviewWrap">
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
                  <td colspan="5" class="text-center text-muted py-4">Loading elected seats…</td>
                </tr>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="btnSubmitNewBatch">
            <i class="fas fa-layer-group me-1"></i> Create Term
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
        <h5 class="modal-title"><i class="fas fa-calendar-plus me-2 text-primary"></i> Edit Term Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="formAddElection" novalidate>
        <div class="modal-body">
          <input type="hidden" name="original_batch_label" id="aeOriginalBatchLabel">
          <input type="hidden" name="proclamation_date" id="aeProclamationDateHidden">
          <div class="mb-3">
            <label class="form-label fw-semibold">Term Label <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="aeBatchLabel" disabled
                   placeholder="e.g. 2025-2028 Barangay Term">
            <div class="form-text text-muted">This is locked after the term is created.</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Proclamation Date <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="aeProclamationDate" disabled>
            <div class="form-text text-muted">This stays fixed to preserve the original term record.</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Next Election Date <span class="text-danger">*</span></label>
            <input type="date" class="form-control" name="next_election_date" id="aeNextElectionDate" required>
            <div class="form-text text-muted" id="aeNextElectionHelp">You can adjust the month and day, but the election year stays locked.</div>
          </div>
          <div class="alert alert-info py-2 small d-none" id="aeNoEditableScheduleAlert">
            <i class="fas fa-info-circle me-1"></i>
            Create a term first before editing its details.
          </div>
          <div class="alert alert-warning py-2 small">
            <i class="fas fa-exclamation-triangle me-1"></i>
            If you are updating an existing term schedule, all unprocessed notification flags for that term will be reset.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="btnSubmitEditTermDetails">
            <i class="fas fa-save me-1"></i> Save Term Details
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

        <!-- Current transition draft preview -->
        <div id="candidatesList" class="mb-3">
          <div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-2"></i> Loading…</div>
        </div>

        <!-- Add official -->
        <div id="addCandidateSection" class="border rounded p-3">
          <p class="fw-semibold mb-2 small">Incoming Official Information</p>
          <div class="row g-2">
            <div class="col-12 col-md-5">
              <label for="formerOfficialMode" class="form-label small fw-semibold mb-1">Existing Official Record?</label>
              <select class="form-select form-select-sm" id="formerOfficialMode">
                <option value="" selected>— Select option —</option>
                <option value="new">No, this is a new official</option>
                <option value="former">Use former official</option>
                <option value="active">Use active official</option>
              </select>
              <div class="small text-muted mt-2">Choose an existing record if this person already has a system profile. Pick a new official only for first-time onboarding.</div>
            </div>
            <div class="col-12" id="linkedIdWrap" style="display:none;">
              <label for="linkedOfficialSearch" class="form-label small fw-semibold mb-1" id="linkedIdLabel">Search Official Records</label>
              <div class="small text-muted mb-2" id="linkedIdHelp">Search an existing official record to auto-fill the identity and account details.</div>
              <input type="hidden" id="newCandidateLinkedId" value="">
              <input type="text" class="form-control form-control-sm" id="linkedOfficialSearch"
                     placeholder="Search official by name, ID, or position">
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
                This position accepts one official only. Nothing is stored in a separate incoming-official table. Review the encoded details first, then complete the transition.
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-warning btn-sm" id="btnMarkPendingDecision">
          <i class="fas fa-key me-1"></i> Review and Continue
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
          Finalize Access, Assign Account, and Notify
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="winnerTransitionId">

        <div class="alert alert-info py-2 small mb-3">
          <i class="fas fa-info-circle me-1"></i>
          Review the official details for this position. When you finish here, the system will process the account immediately and send onboarding access when needed.
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
          <div id="winnerActingOptions" class="border rounded p-3 mt-3 bg-light d-none">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="winnerUseActingReplacement">
              <label class="form-check-label fw-semibold" for="winnerUseActingReplacement">
                Treat this as a temporary acting replacement
              </label>
            </div>
            <div class="small text-muted mt-1 mb-2">
              Leave this unchecked for a permanent position change. Turn it on when the selected active official is only covering the seat temporarily.
            </div>
            <label for="winnerActingUntilDate" class="form-label small fw-semibold mb-1">Acting Until Date</label>
            <input type="date" class="form-control form-control-sm" id="winnerActingUntilDate" disabled>
          </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success" id="btnCompleteTransition">
          <i class="fas fa-check-circle me-1"></i> Complete and Notify
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
    window.OT_EDIT_SCHEDULE = <?= json_encode($termEditSchedule, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <script src="../JS-Script-Files/Admin-End/officialTransitionsScript.js?v=20260325-2"></script>
</body>
</html>
