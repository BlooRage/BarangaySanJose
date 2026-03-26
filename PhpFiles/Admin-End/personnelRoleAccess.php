<?php
session_start();

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

function pra_load_saved_profile_maps(mysqli $conn): array
{
    $profileMetaMap = [];
    $permissionMap = [];

    $profileRes = $conn->query("
        SELECT department_key, position_key, department_label, position_label, updated_at
        FROM personnelroleaccessprofiletbl
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
        SELECT department_key, position_key, permission_key
        FROM personnelrolemodulepermissionstbl
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
        }
        $permissionRes->close();
    }

    return [$profileMetaMap, $permissionMap];
}

function pra_fetch_personnel_role_profiles(mysqli $conn): array
{
    $hasPositionAccess = amp_column_exists($conn, 'officialinformationtbl', 'position_access');
    $positionField = $hasPositionAccess ? 'oi.position_access' : 'oi.role_access';
    $hasSuffix = amp_column_exists($conn, 'officialinformationtbl', 'suffix');
    $nameExpr = $hasSuffix
        ? "TRIM(CONCAT_WS(' ', NULLIF(oi.firstname, ''), NULLIF(oi.middlename, ''), NULLIF(oi.lastname, ''), NULLIF(oi.suffix, '')))"
        : "TRIM(CONCAT_WS(' ', NULLIF(oi.firstname, ''), NULLIF(oi.middlename, ''), NULLIF(oi.lastname, '')))";

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
            ua.role_access AS account_role_access,
            {$nameExpr} AS full_name
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
            'Personnel Role Based Permissions',
            'PersonnelRoleAccessProfile',
            pra_profile_target_id($department, $positionAccess),
            'PERSONNEL_ROLE_ACCESS_SAVE',
            'module_permissions',
            pra_permission_summary($oldPermissionMap),
            pra_permission_summary($permissionMap),
            'Updated personnel role-based permission profile.',
            null
        );

        echo json_encode([
            'success' => true,
            'message' => 'Role-based permissions saved successfully.',
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
            'Personnel Role Based Permissions',
            'PersonnelRoleAccessProfile',
            pra_profile_target_id($department, $positionAccess),
            'PERSONNEL_ROLE_ACCESS_RESET',
            'module_permissions',
            pra_permission_summary($oldPermissionMap),
            pra_permission_summary($defaultPermissionMap),
            'Reset personnel role-based permission profile to default permissions.',
            null
        );

        echo json_encode([
            'success' => true,
            'message' => 'Role-based permissions reset to defaults.',
        ]);
        exit;
    }

    $payload = pra_fetch_personnel_role_profiles($conn);
    echo json_encode([
        'success' => true,
        'data' => $payload['rows'],
        'summary' => $payload['summary'],
        'filters' => $payload['filters'],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to process role-based permissions.',
    ]);
}
