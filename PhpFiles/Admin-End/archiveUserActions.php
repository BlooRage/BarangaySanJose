<?php
require_once "../General/security.php";
header('Content-Type: application/json; charset=utf-8');

requireRoleSession(['SuperAdmin']);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    sendJsonErrorAndExit(405, 'Method not allowed.');
}
verifyCsrfToken(true);

require_once "../General/connection.php";
require_once "../General/audit.php";
require_once "../General/adminModulePermissions.php";
require_once "../General/userAccountLocks.php";

ual_ensure_archive_support($conn);

function archiveUserActionLoadTarget(mysqli $conn, string $userId): ?array
{
    $stmt = $conn->prepare("
        SELECT
            ua.user_id,
            ua.role_access AS account_role_access,
            ua.status_id_account,
            ua.archived_prev_status_id,
            COALESCE(sa.status_name, CONCAT('Status #', ua.status_id_account)) AS account_status,
            COALESCE(sp.status_name, '') AS previous_status_name,
            oi.role_access AS info_role_access
        FROM useraccountstbl ua
        LEFT JOIN statuslookuptbl sa ON sa.status_id = ua.status_id_account
        LEFT JOIN statuslookuptbl sp ON sp.status_id = ua.archived_prev_status_id
        LEFT JOIN officialinformationtbl oi ON oi.user_id COLLATE utf8mb4_general_ci = ua.user_id COLLATE utf8mb4_general_ci
        WHERE ua.user_id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (pii_decrypt_useraccount_row($row) ?? $row) : null;
}

function archiveUserActionManageability(mysqli $conn, array $target, string $actorUserId): array
{
    if ($actorUserId === '') {
        return [false, 'Unable to verify the current account.'];
    }

    $displayRole = amp_storage_role_to_display_role((string)($target['account_role_access'] ?? $target['info_role_access'] ?? ''));
    $reason = amp_get_superadmin_management_disabled_reason($conn, $actorUserId, $displayRole);
    if ($reason !== '') {
        return [false, $reason];
    }

    return [true, ''];
}

try {
    $transactionStarted = false;
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        throw new Exception('Invalid request payload.');
    }

    $action = strtolower(trim((string)($payload['action'] ?? '')));
    $userId = trim((string)($payload['user_id'] ?? ''));
    if ($userId === '') {
        throw new Exception('User account is required.');
    }
    if (!in_array($action, ['restore', 'delete'], true)) {
        throw new Exception('Invalid action.');
    }

    $archiveSupport = ual_ensure_archive_support($conn);
    $statusIds = is_array($archiveSupport['status_ids'] ?? null)
        ? $archiveSupport['status_ids']
        : ual_load_status_ids($conn);
    $archivedStatusId = isset($archiveSupport['archived_status_id'])
        ? (int)$archiveSupport['archived_status_id']
        : (int)($statusIds['archived'] ?? 0);
    $activeStatusId = (int)($statusIds['active'] ?? 0);
    $deletedStatusId = (int)($statusIds['deleted'] ?? 0);

    if ($archivedStatusId <= 0) {
        throw new Exception('Archived status is not configured yet.');
    }

    $target = archiveUserActionLoadTarget($conn, $userId);
    if (!$target) {
        throw new Exception('User account cannot be found.');
    }
    if ((int)($target['status_id_account'] ?? 0) !== $archivedStatusId) {
        throw new Exception('Only archived accounts can be managed from this page.');
    }

    $actorUserId = trim((string)($_SESSION['user_id'] ?? ''));
    $actorRole = trim((string)($_SESSION['role'] ?? ''));
    [$canManage, $manageReason] = archiveUserActionManageability($conn, $target, $actorUserId);
    if (!$canManage) {
        throw new Exception($manageReason !== '' ? $manageReason : 'This account cannot be updated here.');
    }

    $conn->begin_transaction();
    $transactionStarted = true;

    if ($action === 'restore') {
        $restoreStatusId = (int)($target['archived_prev_status_id'] ?? 0);
        if (
            $restoreStatusId <= 0
            || $restoreStatusId === $archivedStatusId
            || ($deletedStatusId > 0 && $restoreStatusId === $deletedStatusId)
        ) {
            $restoreStatusId = $activeStatusId;
        }

        if ($restoreStatusId <= 0) {
            throw new Exception('Active status is not configured yet.');
        }

        $restoreStatusLabel = trim((string)($target['previous_status_name'] ?? ''));
        if ($restoreStatusLabel === '' || strcasecmp($restoreStatusLabel, 'Archived') === 0 || strcasecmp($restoreStatusLabel, 'Deleted') === 0) {
            $restoreStatusLabel = 'Active';
        }

        $stmt = $conn->prepare("
            UPDATE useraccountstbl
            SET status_id_account = ?,
                failed_logins = 0,
                lock_start = NULL,
                lock_until = NULL,
                lock_type = NULL,
                lock_reason = NULL,
                locked_by_user_id = NULL,
                archived_at = NULL,
                archived_prev_status_id = NULL,
                updated_at = NOW()
            WHERE user_id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            throw new Exception('Failed to restore the archived account.');
        }
        $stmt->bind_param('is', $restoreStatusId, $userId);
        $stmt->execute();
        $stmt->close();

        insertUnifiedAuditLog(
            $conn,
            $actorUserId,
            $actorRole,
            'User Archive',
            'UserAccount',
            $userId,
            'USER_ACCOUNT_RESTORE',
            'status_id_account',
            'Archived',
            $restoreStatusLabel,
            null,
            $restoreStatusId
        );

        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Archived account restored successfully.',
        ]);
        exit;
    }

    if ($deletedStatusId <= 0) {
        throw new Exception('Deleted status is not configured yet.');
    }

    $stmt = $conn->prepare("
        UPDATE useraccountstbl
        SET status_id_account = ?,
            failed_logins = 0,
            lock_start = NULL,
            lock_until = NULL,
            lock_type = NULL,
            lock_reason = NULL,
            locked_by_user_id = NULL,
            archived_at = NULL,
            archived_prev_status_id = NULL,
            updated_at = NOW()
        WHERE user_id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        throw new Exception('Failed to delete the archived account.');
    }
    $stmt->bind_param('is', $deletedStatusId, $userId);
    $stmt->execute();
    $stmt->close();

    insertUnifiedAuditLog(
        $conn,
        $actorUserId,
        $actorRole,
        'User Archive',
        'UserAccount',
        $userId,
        'USER_ACCOUNT_DELETE',
        'status_id_account',
        'Archived',
        'Deleted',
        null,
        $deletedStatusId
    );

    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Archived account deleted successfully.',
    ]);
} catch (Exception $e) {
    if (!empty($transactionStarted)) {
        $conn->rollback();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
