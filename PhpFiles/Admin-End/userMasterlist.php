<?php
session_start();
require_once "../General/connection.php";
require_once "../General/security.php";
require_once "../General/audit.php";
require_once "../General/adminModulePermissions.php";
require_once "../General/userAccountLocks.php";
require_once "../EmailHandlers/emailSender.php";

requireRoleSession(['SuperAdmin']);

header('Content-Type: application/json; charset=utf-8');

ual_ensure_lock_columns($conn);
$archiveSupport = ual_ensure_archive_support($conn);

function normalizeRole(string $role): string
{
    $k = strtolower(trim($role));
    if ($k === 'officials' || $k === 'official') return 'Official';
    if ($k === 'personnels' || $k === 'personnel') return 'Personnel';
    if ($k === 'admin') return 'Official';
    if ($k === 'employee') return 'Personnel';
    if ($k === 'superadmin') return 'SuperAdmin';
    if ($k === 'resident') return 'Resident';
    return trim($role) !== '' ? trim($role) : 'Unknown';
}

function userMasterlistDisplayName(array $row): string
{
    $mkName = static function ($fn, $mn, $ln, $suf): string {
        $fn = trim((string)$fn);
        $mn = trim((string)$mn);
        $ln = trim((string)$ln);
        $suf = trim((string)$suf);
        if ($fn === '' && $ln === '') return '';
        $mi = $mn !== '' ? substr($mn, 0, 1) . '. ' : '';
        return trim($fn . ' ' . $mi . $ln . ($suf !== '' ? ' ' . $suf : ''));
    };

    $nameOfficial = $mkName($row['o_firstname'] ?? '', $row['o_middlename'] ?? '', $row['o_lastname'] ?? '', $row['o_suffix'] ?? '');
    $nameResident = $mkName($row['r_firstname'] ?? '', $row['r_middlename'] ?? '', $row['r_lastname'] ?? '', $row['r_suffix'] ?? '');
    return $nameOfficial !== '' ? $nameOfficial : ($nameResident !== '' ? $nameResident : '—');
}

function userMasterlistDecryptRow(array $row): array
{
    $row = pii_decrypt_useraccount_row($row) ?? $row;
    return pii_decrypt_assoc($row, [
        'r_firstname',
        'r_middlename',
        'r_lastname',
        'r_suffix',
        'o_firstname',
        'o_middlename',
        'o_lastname',
        'o_suffix',
    ]);
}

function userMasterlistMatchesSearch(array $row, string $needle): bool
{
    $row['display_name'] = userMasterlistDisplayName($row);
    return pii_search_match($row, [
        'user_id',
        'display_name',
        'email',
        'phone_number',
        'role_access',
        'account_role_access',
        'info_role_access',
    ], $needle);
}

function userMasterlistAccountHolderName(array $row): string
{
    $name = trim((string)($row['display_name'] ?? ''));
    if ($name !== '' && $name !== '—') {
        return $name;
    }

    $firstName = trim((string)($row['firstname'] ?? $row['r_firstname'] ?? $row['o_firstname'] ?? ''));
    $middleName = trim((string)($row['middlename'] ?? $row['r_middlename'] ?? $row['o_middlename'] ?? ''));
    $lastName = trim((string)($row['lastname'] ?? $row['r_lastname'] ?? $row['o_lastname'] ?? ''));
    $suffix = trim((string)($row['suffix'] ?? $row['r_suffix'] ?? $row['o_suffix'] ?? ''));
    $middleInitial = $middleName !== '' ? substr($middleName, 0, 1) . '. ' : '';
    $fullName = trim($firstName . ' ' . $middleInitial . $lastName . ($suffix !== '' ? ' ' . $suffix : ''));

    return $fullName !== '' ? $fullName : 'Resident';
}

function userMasterlistLoadArchiveNoticeRecipient(mysqli $conn, string $userId): ?array
{
    $stmt = $conn->prepare("
        SELECT
            ua.user_id,
            ua.email,
            ua.phone_number,
            ri.firstname AS r_firstname,
            ri.middlename AS r_middlename,
            ri.lastname AS r_lastname,
            ri.suffix AS r_suffix,
            oi.firstname AS o_firstname,
            oi.middlename AS o_middlename,
            oi.lastname AS o_lastname,
            oi.suffix AS o_suffix
        FROM useraccountstbl ua
        LEFT JOIN residentinformationtbl ri ON ri.user_id COLLATE utf8mb4_general_ci = ua.user_id COLLATE utf8mb4_general_ci
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

    if (!$row) {
        return null;
    }

    $row = userMasterlistDecryptRow($row);
    $row['display_name'] = userMasterlistDisplayName($row);
    return $row;
}

function userMasterlistSendArchiveNotice(array $recipient): array
{
    $result = [
        'sms' => 'skipped',
        'email' => 'skipped',
    ];

    $displayName = userMasterlistAccountHolderName($recipient);
    $email = trim((string)($recipient['email'] ?? ''));

    if ($email !== '') {
        $smtpConfig = require __DIR__ . '/../General/mailConfigurations.php';
        $emailSender = new EmailSender($smtpConfig);
        $archivedAt = date('F j, Y g:i A');
        $bodyHtml = '
            <p>Hello ' . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . ',</p>
            <p>Your Barangay San Jose account was archived on ' . htmlspecialchars($archivedAt, ENT_QUOTES, 'UTF-8') . ' and can no longer be used to log in.</p>
            <p>If you believe this was done by mistake, please contact the barangay office for assistance.</p>
        ';
        $bodyText = implode("\n", [
            'Hello ' . $displayName . ',',
            '',
            'Your Barangay San Jose account was archived on ' . $archivedAt . ' and can no longer be used to log in.',
            'If you believe this was done by mistake, please contact the barangay office for assistance.',
        ]);

        $result['email'] = $emailSender->send([
            'type' => 'transaction',
            'to' => $email,
            'subject' => 'Your Barangay San Jose account was archived',
            'bodyHtml' => $bodyHtml,
            'bodyText' => $bodyText,
        ]) ? 'sent' : 'failed';
    }

    return $result;
}

function userMasterlistArchiveNoticeSummary(array $delivery): string
{
    $labels = ['sms' => 'SMS', 'email' => 'email'];
    $sent = [];
    $failed = [];
    $skipped = [];

    foreach ($labels as $key => $label) {
        $status = trim((string)($delivery[$key] ?? 'skipped'));
        if ($status === 'sent') {
            $sent[] = $label;
        } elseif ($status === 'failed') {
            $failed[] = $label;
        } else {
            $skipped[] = $label;
        }
    }

    $parts = [];
    if ($sent !== []) {
        $parts[] = 'Notice sent by ' . implode(' and ', $sent) . '.';
    }
    if ($failed !== []) {
        $parts[] = 'Could not deliver ' . implode(' and ', $failed) . '.';
    }
    if ($sent === [] && $skipped !== []) {
        $parts[] = 'No notice was sent because no contact details are on file.';
    }

    return implode(' ', $parts);
}

function parseLockUntilInput(string $rawValue): ?DateTimeImmutable
{
    $value = trim($rawValue);
    if ($value === '') {
        return null;
    }

    $tz = new DateTimeZone('Asia/Manila');
    $formats = ['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'Y-m-d H:i'];
    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $value, $tz);
        if ($dt instanceof DateTimeImmutable && $dt->format($format) === $value) {
            return $dt;
        }
    }

    return null;
}

function loadUserMasterlistTarget(mysqli $conn, string $userId): ?array
{
    $stmt = $conn->prepare("
        SELECT
            ua.user_id,
            ua.email,
            ua.phone_number,
            ua.role_access AS account_role_access,
            ua.status_id_account,
            ua.lock_start,
            ua.lock_until,
            ua.lock_type,
            ua.lock_reason,
            ua.locked_by_user_id,
            ua.archived_at,
            ua.archived_prev_status_id,
            oi.role_access AS info_role_access,
            oi.position_access,
            oi.official_id
        FROM useraccountstbl ua
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

    return $row ? userMasterlistDecryptRow($row) : null;
}

function currentStatusLabel(array $row, ?int $lockedStatusId): string
{
    $statusName = trim((string)($row['account_status'] ?? ''));
    $statusId = (int)($row['status_id_account'] ?? 0);
    if ($lockedStatusId !== null && $statusId === (int)$lockedStatusId) {
        return ual_lock_status_label(ual_get_lock_state($row));
    }
    return $statusName !== '' ? $statusName : ('Status #' . (string)($row['status_id_account'] ?? ''));
}

function currentLockability(mysqli $conn, array $row, string $actorUserId, ?int $activeStatusId, ?int $lockedStatusId): array
{
    $userId = (string)($row['user_id'] ?? '');
    if ($userId === '' || $actorUserId === '') {
        return [false, 'Unable to verify the current account.'];
    }
    if ($actorUserId === $userId) {
        return [false, 'You cannot change the lock state of your own account.'];
    }

    $targetDisplayRole = amp_storage_role_to_display_role((string)($row['account_role_access'] ?? $row['info_role_access'] ?? $row['role_access'] ?? ''));
    $superadminManageReason = amp_get_superadmin_management_disabled_reason($conn, $actorUserId, $targetDisplayRole);
    if ($superadminManageReason !== '') {
        return [false, $superadminManageReason];
    }

    $statusId = (int)($row['status_id_account'] ?? 0);
    if ($lockedStatusId !== null && $statusId === (int)$lockedStatusId) {
        if ($activeStatusId === null) {
            return [false, 'Active status is not configured, so locked accounts cannot be restored yet.'];
        }
        return [true, ''];
    }

    if ($activeStatusId !== null && $statusId !== (int)$activeStatusId) {
        return [false, 'Only active accounts can be manually locked from this page.'];
    }

    if ($lockedStatusId === null) {
        return [false, 'Locked status is not configured yet.'];
    }

    return [true, ''];
}

function currentArchiveability(
    mysqli $conn,
    array $row,
    string $actorUserId,
    ?int $activeStatusId,
    ?int $lockedStatusId,
    ?int $archivedStatusId,
    ?int $deletedStatusId
): array {
    $userId = trim((string)($row['user_id'] ?? ''));
    if ($userId === '' || $actorUserId === '') {
        return [false, 'Unable to verify the current account.'];
    }
    if ($actorUserId === $userId) {
        return [false, 'You cannot archive your own account.'];
    }
    if ($archivedStatusId === null) {
        return [false, 'Archived status is not configured yet.'];
    }

    $targetDisplayRole = amp_storage_role_to_display_role((string)($row['account_role_access'] ?? $row['info_role_access'] ?? $row['role_access'] ?? ''));
    $superadminManageReason = amp_get_superadmin_management_disabled_reason($conn, $actorUserId, $targetDisplayRole);
    if ($superadminManageReason !== '') {
        return [false, $superadminManageReason];
    }

    $statusId = (int)($row['status_id_account'] ?? 0);
    if ($statusId === (int)$archivedStatusId) {
        return [false, 'This account is already archived.'];
    }
    if ($deletedStatusId !== null && $statusId === (int)$deletedStatusId) {
        return [false, 'Deleted accounts cannot be archived.'];
    }

    if (
        $targetDisplayRole === 'SuperAdmin'
        && $activeStatusId !== null
        && $statusId === (int)$activeStatusId
        && amp_count_active_superadmins_excluding($conn, $userId) <= 0
    ) {
        return [false, 'At least one active SuperAdmin account must remain.'];
    }

    if ($lockedStatusId !== null && $statusId === (int)$lockedStatusId) {
        return [true, ''];
    }

    return [true, ''];
}

$statusIds = is_array($archiveSupport['status_ids'] ?? null)
    ? $archiveSupport['status_ids']
    : ual_load_status_ids($conn);
$lockedStatusId = $statusIds['locked'] ?? null;
$activeStatusId = $statusIds['active'] ?? null;
$archivedStatusId = isset($archiveSupport['archived_status_id']) ? (int)$archiveSupport['archived_status_id'] : ($statusIds['archived'] ?? null);
$deactivatedStatusId = $statusIds['deactivated'] ?? null;
$deletedStatusId = $statusIds['deleted'] ?? null;

ual_release_expired_locks($conn, $lockedStatusId, $activeStatusId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $action = trim((string)($_POST['action'] ?? ''));
        $actorUserId = trim((string)($_SESSION['user_id'] ?? ''));
        $actorRole = trim((string)($_SESSION['role'] ?? ''));
        $userId = trim((string)($_POST['user_id'] ?? ''));
        if ($userId === '') {
            throw new Exception('User account is required.');
        }

        $target = loadUserMasterlistTarget($conn, $userId);
        if (!$target) {
            throw new Exception('User account cannot be found.');
        }

        $targetStatusStmt = $conn->prepare("
            SELECT COALESCE(sa.status_name, CONCAT('Status #', ua.status_id_account)) AS account_status
            FROM useraccountstbl ua
            LEFT JOIN statuslookuptbl sa ON sa.status_id = ua.status_id_account
            WHERE ua.user_id = ?
            LIMIT 1
        ");
        if (!$targetStatusStmt) {
            throw new Exception('Unable to load account status.');
        }
        $targetStatusStmt->bind_param('s', $userId);
        $targetStatusStmt->execute();
        $statusRow = $targetStatusStmt->get_result()->fetch_assoc();
        $targetStatusStmt->close();
        if ($statusRow) {
            $target['account_status'] = (string)($statusRow['account_status'] ?? '');
        }

        [$canManage, $manageReason] = currentLockability($conn, $target, $actorUserId, $activeStatusId, $lockedStatusId);
        if (!$canManage) {
            throw new Exception($manageReason !== '' ? $manageReason : 'This account cannot be updated here.');
        }

        $targetRole = normalizeRole((string)($target['account_role_access'] ?? ''));
        if ($action === 'lock_account') {
            if ($lockedStatusId === null) {
                throw new Exception('Locked status is not configured yet.');
            }

            $currentStatusId = (int)($target['status_id_account'] ?? 0);
            if (
                ($activeStatusId !== null && $currentStatusId !== (int)$activeStatusId)
                && ($currentStatusId !== (int)$lockedStatusId)
            ) {
                throw new Exception('Only active accounts can be locked from this page.');
            }

            if ($deactivatedStatusId !== null && $currentStatusId === (int)$deactivatedStatusId) {
                throw new Exception('Deactivated accounts cannot be manually locked.');
            }
            if ($deletedStatusId !== null && $currentStatusId === (int)$deletedStatusId) {
                throw new Exception('Deleted accounts cannot be manually locked.');
            }

            if ($targetRole === 'SuperAdmin' && amp_count_active_superadmins_excluding($conn, $userId) <= 0) {
                throw new Exception('At least one active SuperAdmin account must remain.');
            }

            $lockMode = strtolower(trim((string)($_POST['lock_mode'] ?? '')));
            if (!in_array($lockMode, ['temporary', 'permanent'], true)) {
                throw new Exception('Select whether the lock is temporary or permanent.');
            }

            $lockReason = trim((string)($_POST['lock_reason'] ?? ''));
            if (function_exists('mb_strlen') && mb_strlen($lockReason, 'UTF-8') > 255) {
                throw new Exception('Lock reason must be 255 characters or fewer.');
            }
            if (!function_exists('mb_strlen') && strlen($lockReason) > 255) {
                throw new Exception('Lock reason must be 255 characters or fewer.');
            }

            $lockUntilSql = null;
            $lockUntilHuman = '';
            if ($lockMode === 'temporary') {
                $lockUntil = parseLockUntilInput((string)($_POST['lock_until'] ?? ''));
                if (!$lockUntil) {
                    throw new Exception('Choose a valid lock end date and time.');
                }

                $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
                if ($lockUntil <= $now) {
                    throw new Exception('The lock end date and time must be in the future.');
                }

                $lockUntilSql = $lockUntil->format('Y-m-d H:i:s');
                $lockUntilHuman = $lockUntil->format('F j, Y g:i A');
            }

            $oldStatusLabel = currentStatusLabel($target, $lockedStatusId);

            $update = $conn->prepare("
                UPDATE useraccountstbl
                SET status_id_account = ?,
                    failed_logins = 0,
                    lock_start = NOW(),
                    lock_until = ?,
                    lock_type = ?,
                    lock_reason = NULLIF(?, ''),
                    locked_by_user_id = ?,
                    updated_at = NOW()
                WHERE user_id = ?
                LIMIT 1
            ");
            if (!$update) {
                throw new Exception('Failed to apply the account lock.');
            }

            $update->bind_param('isssss', $lockedStatusId, $lockUntilSql, $lockMode, $lockReason, $actorUserId, $userId);
            $update->execute();
            $update->close();

            $newStatusLabel = $lockMode === 'permanent'
                ? 'Locked permanently'
                : ('Locked until ' . $lockUntilHuman);

            insertUnifiedAuditLog(
                $conn,
                $actorUserId,
                $actorRole,
                'User Masterlist',
                'UserAccount',
                $userId,
                'USER_ACCOUNT_LOCK',
                'status_id_account / lock_type / lock_until',
                $oldStatusLabel,
                $newStatusLabel,
                $lockReason !== '' ? $lockReason : null,
                null
            );

            echo json_encode([
                'success' => true,
                'message' => $lockMode === 'permanent'
                    ? 'Account locked permanently.'
                    : 'Account locked until ' . $lockUntilHuman . '.',
            ]);
            exit;
        }

        if ($action === 'unlock_account') {
            if ($activeStatusId === null) {
                throw new Exception('Active status is not configured yet.');
            }

            $currentStatusId = (int)($target['status_id_account'] ?? 0);
            if ($lockedStatusId === null || $currentStatusId !== (int)$lockedStatusId) {
                throw new Exception('Only locked accounts can be unlocked from this page.');
            }

            $oldStatusLabel = currentStatusLabel($target, $lockedStatusId);

            $update = $conn->prepare("
                UPDATE useraccountstbl
                SET status_id_account = ?,
                    failed_logins = 0,
                    lock_start = NULL,
                    lock_until = NULL,
                    lock_type = NULL,
                    lock_reason = NULL,
                    locked_by_user_id = NULL,
                    updated_at = NOW()
                WHERE user_id = ?
                LIMIT 1
            ");
            if (!$update) {
                throw new Exception('Failed to unlock the account.');
            }
            $update->bind_param('is', $activeStatusId, $userId);
            $update->execute();
            $update->close();

            insertUnifiedAuditLog(
                $conn,
                $actorUserId,
                $actorRole,
                'User Masterlist',
                'UserAccount',
                $userId,
                'USER_ACCOUNT_UNLOCK',
                'status_id_account / lock_type / lock_until',
                $oldStatusLabel,
                'Active',
                null,
                null
            );

            echo json_encode([
                'success' => true,
                'message' => 'Account unlocked successfully.',
            ]);
            exit;
        }

        if ($action === 'archive_account') {
            [$canArchive, $archiveReason] = currentArchiveability(
                $conn,
                $target,
                $actorUserId,
                $activeStatusId,
                $lockedStatusId,
                $archivedStatusId,
                $deletedStatusId
            );
            if (!$canArchive) {
                throw new Exception($archiveReason !== '' ? $archiveReason : 'This account cannot be archived here.');
            }

            if ($archivedStatusId === null) {
                throw new Exception('Archived status is not configured yet.');
            }

            $currentStatusId = (int)($target['status_id_account'] ?? 0);
            $restoreStatusId = $currentStatusId;
            if ($lockedStatusId !== null && $restoreStatusId === (int)$lockedStatusId) {
                $restoreStatusId = $activeStatusId ?? $restoreStatusId;
            }
            if ($archivedStatusId !== null && $restoreStatusId === (int)$archivedStatusId) {
                $restoreStatusId = $activeStatusId ?? $restoreStatusId;
            }
            if ($deletedStatusId !== null && $restoreStatusId === (int)$deletedStatusId) {
                $restoreStatusId = $activeStatusId ?? $restoreStatusId;
            }

            $oldStatusLabel = currentStatusLabel($target, $lockedStatusId);

            $update = $conn->prepare("
                UPDATE useraccountstbl
                SET status_id_account = ?,
                    failed_logins = 0,
                    lock_start = NULL,
                    lock_until = NULL,
                    lock_type = NULL,
                    lock_reason = NULL,
                    locked_by_user_id = NULL,
                    archived_at = NOW(),
                    archived_prev_status_id = ?,
                    updated_at = NOW()
                WHERE user_id = ?
                LIMIT 1
            ");
            if (!$update) {
                throw new Exception('Failed to archive the account.');
            }
            $update->bind_param('iis', $archivedStatusId, $restoreStatusId, $userId);
            $update->execute();
            $update->close();

            $noticeRecipient = userMasterlistLoadArchiveNoticeRecipient($conn, $userId);
            $noticeSummary = '';
            if ($noticeRecipient) {
                $noticeSummary = userMasterlistArchiveNoticeSummary(userMasterlistSendArchiveNotice($noticeRecipient));
            }

            insertUnifiedAuditLog(
                $conn,
                $actorUserId,
                $actorRole,
                'User Masterlist',
                'UserAccount',
                $userId,
                'USER_ACCOUNT_ARCHIVE',
                'status_id_account',
                $oldStatusLabel,
                'Archived',
                null,
                $archivedStatusId
            );

            echo json_encode([
                'success' => true,
                'message' => trim('Account archived successfully. ' . $noticeSummary),
            ]);
            exit;
        }

        throw new Exception('Invalid action.');
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

if (!isset($_GET['fetch_user_masterlist'])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Not found']);
    exit;
}

try {
    $q = trim((string)($_GET['q'] ?? ''));
    $roleFilter = normalizeRole((string)($_GET['role'] ?? 'ALL'));
    $verificationFilter = strtolower(trim((string)($_GET['verification'] ?? 'ALL')));
    $limit = (int)($_GET['limit'] ?? 500);
    if ($limit <= 0) $limit = 500;
    if ($limit > 1000) $limit = 1000;

    $sql = "
        SELECT
            ua.user_id,
            ua.email,
            ua.phone_number,
            ua.email_verify,
            ua.phoneNum_verify,
            ua.role_access,
            ua.account_created,
            ua.last_login,
            ua.status_id_account,
            ua.lock_start,
            ua.lock_until,
            ua.lock_type,
            ua.lock_reason,
            ua.locked_by_user_id,
            COALESCE(sa.status_name, CONCAT('Status #', ua.status_id_account)) AS account_status,
            ri.firstname AS r_firstname,
            ri.middlename AS r_middlename,
            ri.lastname AS r_lastname,
            ri.suffix AS r_suffix,
            oi.firstname AS o_firstname,
            oi.middlename AS o_middlename,
            oi.lastname AS o_lastname,
            oi.suffix AS o_suffix,
            oi.role_access AS info_role_access,
            oi.position_access
        FROM useraccountstbl ua
        LEFT JOIN statuslookuptbl sa ON sa.status_id = ua.status_id_account
        LEFT JOIN residentinformationtbl ri ON ri.user_id COLLATE utf8mb4_general_ci = ua.user_id COLLATE utf8mb4_general_ci
        LEFT JOIN officialinformationtbl oi ON oi.user_id COLLATE utf8mb4_general_ci = ua.user_id COLLATE utf8mb4_general_ci
    ";

    $params = [];
    $types = '';
    $whereClauses = [];
    if ($archivedStatusId !== null) {
        $whereClauses[] = "ua.status_id_account <> ?";
        $params[] = $archivedStatusId;
        $types .= 'i';
    }
    if ($deletedStatusId !== null) {
        $whereClauses[] = "ua.status_id_account <> ?";
        $params[] = $deletedStatusId;
        $types .= 'i';
    }
    if ($whereClauses !== []) {
        $sql .= " WHERE " . implode(" AND ", $whereClauses);
    }
    $sql .= " ORDER BY ua.account_created DESC, ua.user_id DESC";
    if ($q === '') {
        $sql .= " LIMIT ?";
        $params[] = $limit;
        $types .= 'i';
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);

    if ($types !== '') {
        $refs = [];
        $refs[] = $types;
        foreach ($params as $k => $v) {
            $refs[] = &$params[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }

    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    $pendingCount = 0;
    $actorUserId = trim((string)($_SESSION['user_id'] ?? ''));
    while ($row = $res->fetch_assoc()) {
        $row = userMasterlistDecryptRow($row);
        if ($q !== '' && !userMasterlistMatchesSearch($row, $q)) {
            continue;
        }

        $displayName = userMasterlistDisplayName($row);
        $normalizedRole = normalizeRole((string)($row['role_access'] ?? ''));
        $isVerified = ((int)($row['email_verify'] ?? 0) === 1) && ((int)($row['phoneNum_verify'] ?? 0) === 1);
        $verification = $isVerified ? 'Verified' : 'Pending';

        if ($verification === 'Pending') {
            $pendingCount++;
        }

        if ($roleFilter !== 'ALL' && $normalizedRole !== $roleFilter) {
            continue;
        }
        if ($verificationFilter === 'pending' && $verification !== 'Pending') {
            continue;
        }
        if ($verificationFilter === 'verified' && $verification !== 'Verified') {
            continue;
        }

        $isLocked = $lockedStatusId !== null && (int)($row['status_id_account'] ?? 0) === (int)$lockedStatusId;
        $lockState = $isLocked ? ual_get_lock_state($row) : [
            'type' => '',
            'lock_until' => '',
            'reason' => '',
            'locked_by_user_id' => '',
            'is_permanent' => false,
            'is_expired' => false,
        ];

        [$canManageLock, $manageLockDisabledReason] = currentLockability($conn, $row, $actorUserId, $activeStatusId, $lockedStatusId);
        [$canArchiveAccount, $archiveAccountDisabledReason] = currentArchiveability(
            $conn,
            $row,
            $actorUserId,
            $activeStatusId,
            $lockedStatusId,
            $archivedStatusId,
            $deletedStatusId
        );

        $rows[] = [
            'user_id' => (string)$row['user_id'],
            'display_name' => $displayName,
            'email' => (string)$row['email'],
            'phone_number' => (string)$row['phone_number'],
            'role_access' => $normalizedRole,
            'account_status' => (string)$row['account_status'],
            'account_status_display' => $isLocked ? ual_lock_status_label($lockState) : (string)$row['account_status'],
            'verification_status' => $verification,
            'account_created' => (string)($row['account_created'] ?? ''),
            'last_login' => (string)($row['last_login'] ?? ''),
            'is_locked' => $isLocked,
            'lock_type' => (string)($lockState['type'] ?? ''),
            'lock_until' => (string)($lockState['lock_until'] ?? ''),
            'lock_reason' => (string)($lockState['reason'] ?? ''),
            'locked_by_user_id' => (string)($lockState['locked_by_user_id'] ?? ''),
            'can_manage_lock' => $canManageLock,
            'manage_lock_disabled_reason' => $manageLockDisabledReason,
            'can_archive_account' => $canArchiveAccount,
            'archive_account_disabled_reason' => $archiveAccountDisabledReason,
        ];
    }
    $stmt->close();

    if ($q !== '') {
        $rows = array_slice($rows, 0, $limit);
    }

    echo json_encode([
        'success' => true,
        'data' => $rows,
        'pending_count' => $pendingCount,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
