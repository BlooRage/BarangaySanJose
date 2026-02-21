<?php
session_start();
require_once "../General/connection.php";
require_once "../General/security.php";
require_once "../General/audit.php";

requireRoleSession(['SuperAdmin']);

header('Content-Type: application/json; charset=utf-8');

function normalizeRole(string $role): string {
    $k = strtolower(trim($role));
    if ($k === 'officials' || $k === 'official' || $k === 'admin' || $k === 'employee') return 'Official';
    if ($k === 'personnels' || $k === 'personnel') return 'Personnel';
    if ($k === 'superadmin') return 'SuperAdmin';
    return trim($role);
}

function permissionStateFromAccountStatus(string $statusName): string {
    $k = strtolower(trim($statusName));
    if (
        strpos($k, 'inactive') !== false ||
        strpos($k, 'revoked') !== false ||
        strpos($k, 'suspended') !== false ||
        strpos($k, 'disabled') !== false
    ) {
        return 'Revoked';
    }
    return 'Active';
}

function getStatusIdByNames(mysqli $conn, string $statusType, array $preferredNames): ?int {
    if (!$preferredNames) return null;
    $all = [];
    $stmt = $conn->prepare("SELECT status_id, status_name FROM statuslookuptbl WHERE status_type = ?");
    if (!$stmt) return null;
    $stmt->bind_param("s", $statusType);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $all[strtolower(trim((string)($r['status_name'] ?? '')))] = (int)($r['status_id'] ?? 0);
    }
    $stmt->close();
    foreach ($preferredNames as $name) {
        $key = strtolower(trim((string)$name));
        if ($key !== '' && isset($all[$key]) && $all[$key] > 0) {
            return (int)$all[$key];
        }
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $actorRole = (string)($_SESSION['role'] ?? '');
        if ($actorRole !== 'SuperAdmin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only SuperAdmin can revoke or restore permissions.']);
            exit;
        }

        $action = trim((string)($_POST['action'] ?? ''));
        $officialId = trim((string)($_POST['official_id'] ?? ''));
        if ($officialId === '') {
            throw new Exception('Invalid official record.');
        }

        $statusActiveId = getStatusIdByNames($conn, 'UserAccount', ['Active']);
        $statusInactiveId = getStatusIdByNames($conn, 'UserAccount', ['Inactive', 'Revoked', 'Suspended', 'Disabled']);
        if ($statusActiveId === null || $statusInactiveId === null) {
            throw new Exception('Required UserAccount statuses (Active/Inactive) are missing.');
        }

        $targetStmt = $conn->prepare("
            SELECT oi.official_id, oi.user_id, ua.status_id_account, ua.role_access
            FROM officialinformationtbl oi
            INNER JOIN useraccountstbl ua
                ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
            WHERE oi.official_id = ?
            LIMIT 1
        ");
        if (!$targetStmt) throw new Exception('Failed to load official account.');
        $targetStmt->bind_param("s", $officialId);
        $targetStmt->execute();
        $target = $targetStmt->get_result()->fetch_assoc();
        $targetStmt->close();
        if (!$target) {
            throw new Exception('Official not found.');
        }
        $userId = (string)($target['user_id'] ?? '');
        if ($userId === '') {
            throw new Exception('Target account is invalid.');
        }

        $nextStatusId = null;
        $auditAction = '';
        if ($action === 'revoke_permission') {
            $nextStatusId = $statusInactiveId;
            $auditAction = 'OFFICIAL_PERMISSION_REVOKE';
        } elseif ($action === 'restore_permission') {
            $nextStatusId = $statusActiveId;
            $auditAction = 'OFFICIAL_PERMISSION_RESTORE';
        } else {
            throw new Exception('Invalid action.');
        }

        $up = $conn->prepare("UPDATE useraccountstbl SET status_id_account = ?, updated_at = NOW() WHERE user_id = ? LIMIT 1");
        if (!$up) throw new Exception('Failed to update account status.');
        $up->bind_param("is", $nextStatusId, $userId);
        $up->execute();
        $affected = $up->affected_rows;
        $up->close();

        insertUnifiedAuditLog(
            $conn,
            (string)($_SESSION['user_id'] ?? ''),
            $actorRole,
            'Officials Management',
            'OfficialAccount',
            $officialId,
            $auditAction,
            'status_id_account',
            (string)($target['status_id_account'] ?? ''),
            (string)$nextStatusId,
            $action === 'revoke_permission' ? 'Official permissions revoked (account set inactive).' : 'Official permissions restored (account set active).',
            null
        );

        echo json_encode([
            'success' => true,
            'message' => $action === 'revoke_permission'
                ? 'Permissions revoked successfully.'
                : 'Permissions restored successfully.',
            'updated' => $affected > 0
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

if (!isset($_GET['fetch_officials_management'])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Not found']);
    exit;
}

try {
    $q = trim((string)($_GET['q'] ?? ''));
    $roleFilter = normalizeRole((string)($_GET['role'] ?? 'ALL'));
    $permissionFilter = trim((string)($_GET['permission'] ?? 'ALL'));
    $limit = (int)($_GET['limit'] ?? 500);
    if ($limit <= 0) $limit = 500;
    if ($limit > 1000) $limit = 1000;

    $hasPositionAccess = false;
    $colRes = $conn->query("SHOW COLUMNS FROM officialinformationtbl LIKE 'position_access'");
    if ($colRes instanceof mysqli_result && $colRes->num_rows > 0) {
        $hasPositionAccess = true;
    }
    $positionField = $hasPositionAccess ? 'oi.position_access' : 'oi.role_access';

    $sql = "
        SELECT
            oi.official_id,
            oi.user_id,
            oi.lastname,
            oi.firstname,
            oi.middlename,
            oi.suffix,
            oi.role_access AS info_role_access,
            {$positionField} AS position_access,
            oi.department,
            oi.date_hired,
            COALESCE(se.status_name, CONCAT('Status #', oi.status_id_employment)) AS employment_status,
            ua.email,
            ua.phone_number,
            ua.role_access AS account_role_access,
            COALESCE(sa.status_name, CONCAT('Status #', ua.status_id_account)) AS account_status
        FROM officialinformationtbl oi
        INNER JOIN useraccountstbl ua ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
        LEFT JOIN statuslookuptbl se ON se.status_id = oi.status_id_employment
        LEFT JOIN statuslookuptbl sa ON sa.status_id = ua.status_id_account
    ";

    $params = [];
    $types = '';
    if ($q !== '') {
        $sql .= "
            WHERE (
                oi.official_id LIKE ?
                OR oi.user_id LIKE ?
                OR oi.firstname LIKE ?
                OR oi.lastname LIKE ?
                OR oi.department LIKE ?
                OR {$positionField} LIKE ?
                OR ua.email LIKE ?
            )
        ";
        $like = '%' . $q . '%';
        $params = [$like, $like, $like, $like, $like, $like, $like];
        $types = 'sssssss';
    }

    $sql .= " ORDER BY oi.official_id DESC LIMIT ?";
    $params[] = $limit;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);

    $refs = [];
    $refs[] = $types;
    foreach ($params as $k => $v) {
        $refs[] = &$params[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);

    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $role = normalizeRole((string)($row['account_role_access'] ?? $row['info_role_access'] ?? ''));
        if (!in_array($role, ['Official', 'Personnel', 'SuperAdmin'], true)) {
            continue;
        }
        if ($roleFilter !== 'ALL' && $role !== $roleFilter) {
            continue;
        }

        $fullName = trim(
            (string)($row['firstname'] ?? '') . ' ' .
            ((string)($row['middlename'] ?? '') !== '' ? substr((string)$row['middlename'], 0, 1) . '. ' : '') .
            (string)($row['lastname'] ?? '') .
            ((string)($row['suffix'] ?? '') !== '' ? ' ' . (string)$row['suffix'] : '')
        );

        $rows[] = [
            'official_id' => (string)($row['official_id'] ?? ''),
            'user_id' => (string)($row['user_id'] ?? ''),
            'full_name' => $fullName !== '' ? $fullName : '—',
            'role_access' => $role,
            'position_access' => (string)($row['position_access'] ?? ''),
            'department' => (string)($row['department'] ?? ''),
            'employment_status' => (string)($row['employment_status'] ?? ''),
            'date_hired' => (string)($row['date_hired'] ?? ''),
            'email' => (string)($row['email'] ?? ''),
            'phone_number' => (string)($row['phone_number'] ?? ''),
            'account_status' => (string)($row['account_status'] ?? ''),
            'permission_state' => permissionStateFromAccountStatus((string)($row['account_status'] ?? '')),
        ];
    }
    $stmt->close();

    if ($permissionFilter === 'Active' || $permissionFilter === 'Revoked') {
        $rows = array_values(array_filter($rows, static function ($r) use ($permissionFilter) {
            return (string)($r['permission_state'] ?? 'Active') === $permissionFilter;
        }));
    }

    echo json_encode([
        'success' => true,
        'data' => $rows,
        'can_manage_actions' => ((string)($_SESSION['role'] ?? '') === 'SuperAdmin'),
        'role_counts' => [
            'SuperAdmin' => count(array_filter($rows, fn($r) => ($r['role_access'] ?? '') === 'SuperAdmin')),
            'Official' => count(array_filter($rows, fn($r) => ($r['role_access'] ?? '') === 'Official')),
            'Personnel' => count(array_filter($rows, fn($r) => ($r['role_access'] ?? '') === 'Personnel')),
        ],
        'permission_counts' => [
            'Active' => count(array_filter($rows, fn($r) => ($r['permission_state'] ?? 'Active') === 'Active')),
            'Revoked' => count(array_filter($rows, fn($r) => ($r['permission_state'] ?? 'Active') === 'Revoked')),
        ],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
