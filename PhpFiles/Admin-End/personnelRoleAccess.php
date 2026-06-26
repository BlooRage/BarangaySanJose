<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../General/connection.php";
require_once "../General/security.php";
require_once "../General/audit.php";
require_once "../General/adminModulePermissions.php";

requireRoleSession(['SuperAdmin']);
amp_ensure_permission_storage($conn);

header('Content-Type: application/json; charset=utf-8');

function pra_profile_scope_key(string $departmentKey, string $positionKey): string
{
    return $departmentKey . '||' . $positionKey;
}

function pra_display_value(string $value, string $fallback): string
{
    $value = trim($value);
    return $value !== '' ? $value : $fallback;
}

function pra_permission_summary(array $permissionMap, int $maxLabels = 3): string
{
    if (!$permissionMap) {
        return 'No modules';
    }

    $labels = [];
    foreach (array_keys($permissionMap) as $key) {
        $meta = amp_get_permission_meta($key);
        if (!$meta) {
            continue;
        }
        $label = trim((string)($meta['parent_label'] ?? ''));
        $childLabel = trim((string)($meta['label'] ?? ''));
        $labels[] = $label !== '' ? ($label . ' - ' . $childLabel) : $childLabel;
    }

    sort($labels);
    $labels = array_values(array_unique(array_filter($labels, static fn ($value) => trim((string)$value) !== '')));
    $count = count($labels);
    if ($count === 0) {
        return 'No modules';
    }

    $visible = array_slice($labels, 0, $maxLabels);
    $summary = implode(', ', $visible);
    if ($count > $maxLabels) {
        $summary .= ' +' . ($count - $maxLabels);
    }

    return $summary;
}

function pra_profile_target_id(string $department, string $positionAccess): string
{
    return pra_display_value($department, '[No Department]') . ' :: ' . pra_display_value($positionAccess, '[No Personnel Role]');
}

function pra_default_department_labels(): array
{
    return [
        'Office of the Barangay',
        'Barangay Certificate Issuance',
        'Baranagay Monitoring',
        'Barangay Treasurers Office',
        'Barangay Peace and Order',
    ];
}

function pra_sort_labels(array $values): array
{
    $values = array_values(array_unique(array_filter(array_map(
        static fn ($value) => trim((string)$value),
        $values
    ), static fn ($value) => $value !== '')));

    usort($values, static fn (string $a, string $b): int => strcasecmp($a, $b));
    return $values;
}

function pra_scope_from_saved_meta(string $scopeKey, array $savedMeta): array
{
    $departmentLabel = trim((string)($savedMeta['department_label'] ?? ''));
    $positionLabel = trim((string)($savedMeta['position_label'] ?? ''));

    if ($departmentLabel === '' || $positionLabel === '') {
        [$departmentKey, $positionKey] = array_pad(explode('||', $scopeKey, 2), 2, '');
        if ($departmentLabel === '') {
            $departmentLabel = trim((string)$departmentKey);
        }
        if ($positionLabel === '') {
            $positionLabel = trim((string)$positionKey);
        }
    }

    return amp_get_personnel_role_profile_scope($departmentLabel, $positionLabel);
}

function pra_ensure_editor_department(array &$departments, array &$positionsByDepartment, string $department, array $defaultPositions): void
{
    $department = trim($department);
    if ($department === '') {
        return;
    }

    $departments[$department] = true;
    if (!isset($positionsByDepartment[$department])) {
        $positionsByDepartment[$department] = $defaultPositions;
        return;
    }

    $positionsByDepartment[$department] = pra_sort_labels(array_merge(
        $positionsByDepartment[$department],
        $defaultPositions
    ));
}

function pra_add_editor_position(array &$positionsByDepartment, string $department, string $position): void
{
    $department = trim($department);
    $position = trim($position);
    if ($department === '' || $position === '') {
        return;
    }

    if (!isset($positionsByDepartment[$department])) {
        $positionsByDepartment[$department] = [];
    }

    $positionsByDepartment[$department][] = $position;
    $positionsByDepartment[$department] = pra_sort_labels($positionsByDepartment[$department]);
}

function pra_load_saved_profile_maps(mysqli $conn): array
{
    $profileMetaMap = [];
    $permissionMap = [];

    $profileRes = $conn->query("
        SELECT department_key, position_key, department_label, position_label, updated_at
        FROM officialaccessroleprofiletbl
        WHERE permissions_initialized = 1
    ");
    if ($profileRes instanceof mysqli_result) {
        while ($row = $profileRes->fetch_assoc()) {
            $scopeKey = pra_profile_scope_key(
                (string)($row['department_key'] ?? ''),
                (string)($row['position_key'] ?? '')
            );
            $profileMetaMap[$scopeKey] = [
                'department_label' => trim((string)($row['department_label'] ?? '')),
                'position_label' => trim((string)($row['position_label'] ?? '')),
                'updated_at' => (string)($row['updated_at'] ?? ''),
            ];
        }
        $profileRes->close();
    }

    $permissionRes = $conn->query("
        SELECT department_key, position_key, department_label, position_label, permission_key
        FROM officialaccessrolepermissiontbl
        WHERE is_allowed = 1
    ");
    if ($permissionRes instanceof mysqli_result) {
        while ($row = $permissionRes->fetch_assoc()) {
            $scopeKey = pra_profile_scope_key(
                (string)($row['department_key'] ?? ''),
                (string)($row['position_key'] ?? '')
            );
            $permissionKey = trim((string)($row['permission_key'] ?? ''));
            if ($permissionKey !== '') {
                $permissionMap[$scopeKey][$permissionKey] = true;
            }

            if (!isset($profileMetaMap[$scopeKey])) {
                $profileMetaMap[$scopeKey] = [
                    'department_label' => trim((string)($row['department_label'] ?? '')),
                    'position_label' => trim((string)($row['position_label'] ?? '')),
                    'updated_at' => '',
                ];
                continue;
            }

            if (($profileMetaMap[$scopeKey]['department_label'] ?? '') === '') {
                $profileMetaMap[$scopeKey]['department_label'] = trim((string)($row['department_label'] ?? ''));
            }
            if (($profileMetaMap[$scopeKey]['position_label'] ?? '') === '') {
                $profileMetaMap[$scopeKey]['position_label'] = trim((string)($row['position_label'] ?? ''));
            }
        }
        $permissionRes->close();
    }

    return [$profileMetaMap, $permissionMap];
}

function pra_fetch_editor_options(mysqli $conn, array $groups, array $profileMetaMap): array
{
    $departments = [];
    $positionsByDepartment = [];
    $defaultPositions = pra_sort_labels(amp_get_personnel_position_labels());

    foreach (pra_default_department_labels() as $department) {
        pra_ensure_editor_department($departments, $positionsByDepartment, $department, $defaultPositions);
    }

    foreach ($groups as $group) {
        $scope = (array)($group['scope'] ?? []);
        $department = trim((string)($scope['department_label'] ?? ''));
        $position = trim((string)($scope['position_label'] ?? ''));
        pra_ensure_editor_department($departments, $positionsByDepartment, $department, $defaultPositions);
        pra_add_editor_position($positionsByDepartment, $department, $position);
    }

    foreach ($profileMetaMap as $scopeKey => $savedMeta) {
        $scope = pra_scope_from_saved_meta($scopeKey, $savedMeta);
        $department = trim((string)($scope['department_label'] ?? ''));
        $position = trim((string)($scope['position_label'] ?? ''));
        pra_ensure_editor_department($departments, $positionsByDepartment, $department, $defaultPositions);
        pra_add_editor_position($positionsByDepartment, $department, $position);
    }

    $hasPositionAccess = amp_column_exists($conn, 'officialinformationtbl', 'position_access');
    $positionField = $hasPositionAccess ? 'oi.position_access' : 'oi.role_access';
    $stmt = $conn->prepare("
        SELECT
            COALESCE(oi.department, '') AS department,
            {$positionField} AS position_access,
            oi.role_access AS info_role_access,
            ua.role_access AS account_role_access
        FROM officialinformationtbl oi
        INNER JOIN useraccountstbl ua
            ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
        ORDER BY COALESCE(oi.department, '') ASC, {$positionField} ASC, oi.official_id ASC
    ");
    if ($stmt instanceof mysqli_stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if (!amp_row_uses_personnel_role_profile($row)) {
                continue;
            }

            $scope = amp_get_personnel_role_profile_scope(
                (string)($row['department'] ?? ''),
                (string)($row['position_access'] ?? '')
            );
            $department = trim((string)($scope['department_label'] ?? ''));
            $position = trim((string)($scope['position_label'] ?? ''));

            pra_ensure_editor_department($departments, $positionsByDepartment, $department, $defaultPositions);
            pra_add_editor_position($positionsByDepartment, $department, $position);
        }
        $stmt->close();
    }

    $departmentList = pra_sort_labels(array_keys($departments));
    foreach ($departmentList as $department) {
        $positionsByDepartment[$department] = pra_sort_labels($positionsByDepartment[$department] ?? $defaultPositions);
    }

    return [
        'departments' => $departmentList,
        'positions_by_department' => $positionsByDepartment,
    ];
}

function pra_fetch_personnel_role_profiles(mysqli $conn): array
{
    $hasPositionAccess = amp_column_exists($conn, 'officialinformationtbl', 'position_access');
    $positionField = $hasPositionAccess ? 'oi.position_access' : 'oi.role_access';

    [$profileMetaMap, $savedPermissionMap] = pra_load_saved_profile_maps($conn);

    $defaultPermissionMap = [];
    foreach (amp_get_default_admin_permission_keys() as $key) {
        $defaultPermissionMap[$key] = true;
    }

    $stmt = $conn->prepare("
        SELECT
            oi.official_id,
            oi.user_id,
            oi.role_access AS info_role_access,
            {$positionField} AS position_access,
            COALESCE(oi.department, '') AS department,
            ua.role_access AS account_role_access
        FROM officialinformationtbl oi
        INNER JOIN useraccountstbl ua
            ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
        ORDER BY COALESCE(oi.department, '') ASC, {$positionField} ASC, oi.lastname ASC, oi.firstname ASC, oi.official_id ASC
    ");
    if (!$stmt) {
        throw new RuntimeException('Failed to load personnel role profiles.');
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $groups = [];
    $personnelCount = 0;
    while ($row = $res->fetch_assoc()) {
        if (!amp_row_uses_personnel_role_profile($row)) {
            continue;
        }

        $scope = amp_get_personnel_role_profile_scope(
            (string)($row['department'] ?? ''),
            (string)($row['position_access'] ?? '')
        );
        $scopeKey = pra_profile_scope_key($scope['department_key'], $scope['position_key']);

        if (!isset($groups[$scopeKey])) {
            $groups[$scopeKey] = [
                'scope' => $scope,
                'personnel_count' => 0,
            ];
        }

        $groups[$scopeKey]['personnel_count'] += 1;
        $personnelCount += 1;
    }
    $stmt->close();

    foreach ($profileMetaMap as $scopeKey => $savedMeta) {
        if (isset($groups[$scopeKey])) {
            if (($groups[$scopeKey]['scope']['department_label'] ?? '') === '' && trim((string)($savedMeta['department_label'] ?? '')) !== '') {
                $groups[$scopeKey]['scope']['department_label'] = trim((string)$savedMeta['department_label']);
            }
            if (($groups[$scopeKey]['scope']['position_label'] ?? '') === '' && trim((string)($savedMeta['position_label'] ?? '')) !== '') {
                $groups[$scopeKey]['scope']['position_label'] = trim((string)$savedMeta['position_label']);
            }
            continue;
        }

        $groups[$scopeKey] = [
            'scope' => pra_scope_from_saved_meta($scopeKey, $savedMeta),
            'personnel_count' => 0,
        ];
    }

    $rows = [];
    $customProfiles = 0;
    foreach ($groups as $scopeKey => $group) {
        $scope = $group['scope'];
        $savedMeta = $profileMetaMap[$scopeKey] ?? null;
        $hasSavedProfile = $savedMeta !== null;
        $effectivePermissionMap = $hasSavedProfile
            ? ($savedPermissionMap[$scopeKey] ?? [])
            : $defaultPermissionMap;

        foreach (array_keys($effectivePermissionMap) as $key) {
            if (amp_is_admin_only_permission($key)) {
                unset($effectivePermissionMap[$key]);
            }
        }

        if ($hasSavedProfile) {
            $customProfiles += 1;
        }

        $rows[] = [
            'profile_id' => $scopeKey,
            'department' => $scope['department_label'],
            'department_display' => pra_display_value(
                $savedMeta['department_label'] ?? $scope['department_label'],
                'Unassigned Department'
            ),
            'position_access' => $scope['position_label'],
            'position_display' => pra_display_value(
                $savedMeta['position_label'] ?? $scope['position_label'],
                'Unassigned Personnel Role'
            ),
            'personnel_count' => (int)$group['personnel_count'],
            'has_saved_profile' => $hasSavedProfile,
            'access_source' => $hasSavedProfile ? 'Custom Permissions' : 'Default Permissions',
            'permission_keys' => array_keys($effectivePermissionMap),
            'permission_count' => count($effectivePermissionMap),
            'permission_summary' => pra_permission_summary($effectivePermissionMap),
            'updated_at' => (string)($savedMeta['updated_at'] ?? ''),
        ];
    }

    usort($rows, static function (array $a, array $b): int {
        $departmentCompare = strcasecmp((string)($a['department_display'] ?? ''), (string)($b['department_display'] ?? ''));
        if ($departmentCompare !== 0) {
            return $departmentCompare;
        }
        return strcasecmp((string)($a['position_display'] ?? ''), (string)($b['position_display'] ?? ''));
    });

    $departments = [];
    $positions = [];
    foreach ($rows as $row) {
        $departments[$row['department_display']] = true;
        $positions[$row['position_display']] = true;
    }

    $editorOptions = pra_fetch_editor_options($conn, $groups, $profileMetaMap);

    return [
        'rows' => $rows,
        'summary' => [
            'profiles_total' => count($rows),
            'custom_profiles' => $customProfiles,
            'default_profiles' => count($rows) - $customProfiles,
            'assigned_personnel_total' => $personnelCount,
        ],
        'filters' => [
            'departments' => array_keys($departments),
            'positions' => array_keys($positions),
        ],
        'editor_options' => $editorOptions,
    ];
}

$action = strtolower(trim((string)($_REQUEST['action'] ?? 'list_profiles')));
$actorUserId = (string)($_SESSION['user_id'] ?? '');
$actorRole = (string)($_SESSION['role'] ?? 'SuperAdmin');

try {
    if ($action === 'save_profile_permissions') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
            exit;
        }

        $department = trim((string)($_POST['department'] ?? ''));
        $positionAccess = trim((string)($_POST['position_access'] ?? ''));
        if ($department === '' || $positionAccess === '') {
            throw new RuntimeException('Department and position are required.');
        }
        $requestedPermissionKeys = $_POST['permission_keys'] ?? [];
        if (!is_array($requestedPermissionKeys)) {
            $requestedPermissionKeys = [];
        }

        $validPermissionKeys = array_fill_keys(amp_get_default_admin_permission_keys(), true);
        $permissionMap = [];
        foreach ($requestedPermissionKeys as $permissionKey) {
            $permissionKey = trim((string)$permissionKey);
            if ($permissionKey !== '' && isset($validPermissionKeys[$permissionKey])) {
                $permissionMap[$permissionKey] = true;
            }
        }

        $oldPermissionMap = amp_get_effective_permission_keys_for_personnel_role($conn, $department, $positionAccess);

        $conn->begin_transaction();
        try {
            amp_replace_personnel_role_module_permissions($conn, $department, $positionAccess, array_keys($permissionMap), $actorUserId);
            amp_upsert_personnel_role_access_profile($conn, $department, $positionAccess);
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }

        insertUnifiedAuditLog(
            $conn,
            $actorUserId,
            $actorRole,
            'Access Control',
            'PersonnelRoleAccessProfile',
            pra_profile_target_id($department, $positionAccess),
            'PERSONNEL_ROLE_ACCESS_SAVE',
            'module_permissions',
            pra_permission_summary($oldPermissionMap),
            pra_permission_summary($permissionMap),
            'Updated access control profile.',
            null
        );

        echo json_encode([
            'success' => true,
            'message' => 'Access control profile saved successfully.',
        ]);
        exit;
    }

    if ($action === 'reset_profile_permissions') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
            exit;
        }

        $department = trim((string)($_POST['department'] ?? ''));
        $positionAccess = trim((string)($_POST['position_access'] ?? ''));
        if ($department === '' || $positionAccess === '') {
            throw new RuntimeException('Department and position are required.');
        }
        $oldPermissionMap = amp_get_effective_permission_keys_for_personnel_role($conn, $department, $positionAccess);

        $conn->begin_transaction();
        try {
            amp_delete_personnel_role_access_profile($conn, $department, $positionAccess);
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }

        $defaultPermissionMap = [];
        foreach (amp_get_default_admin_permission_keys() as $key) {
            $defaultPermissionMap[$key] = true;
        }

        insertUnifiedAuditLog(
            $conn,
            $actorUserId,
            $actorRole,
            'Access Control',
            'PersonnelRoleAccessProfile',
            pra_profile_target_id($department, $positionAccess),
            'PERSONNEL_ROLE_ACCESS_RESET',
            'module_permissions',
            pra_permission_summary($oldPermissionMap),
            pra_permission_summary($defaultPermissionMap),
            'Reset access control profile to default permissions.',
            null
        );

        echo json_encode([
            'success' => true,
            'message' => 'Access control profile reset to defaults.',
        ]);
        exit;
    }

    $payload = pra_fetch_personnel_role_profiles($conn);
    echo json_encode([
        'success' => true,
        'data' => $payload['rows'],
        'summary' => $payload['summary'],
        'filters' => $payload['filters'],
        'editor_options' => $payload['editor_options'],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to process access control changes.',
    ]);
}
