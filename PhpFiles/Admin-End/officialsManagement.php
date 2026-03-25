<?php
session_start();
require_once "../General/connection.php";
require_once "../General/security.php";
require_once "../General/audit.php";
require_once "../General/officialInviteCommon.php";
require_once "../General/adminModulePermissions.php";

requireRoleSession(['SuperAdmin']);
oi_ensure_invite_table($conn);
amp_ensure_permission_storage($conn);

header('Content-Type: application/json; charset=utf-8');

function normalizeRole(string $role): string {
    $k = strtolower(trim($role));
    if ($k === 'officials' || $k === 'official' || $k === 'admin' || $k === 'employee') return 'Official';
    if ($k === 'personnels' || $k === 'personnel') return 'Personnel';
    if ($k === 'superadmin') return 'SuperAdmin';
    return trim($role);
}

function rowDisplayRole(string $role): string {
    return amp_storage_role_to_display_role($role);
}

function requestedManagementMode(string $rawMode): string {
    $mode = strtolower(trim($rawMode));
    return in_array($mode, ['official', 'personnel'], true) ? $mode : 'official';
}

function managementAudienceFromPosition(string $positionAccess, string $fallbackRole = ''): string {
    $position = trim($positionAccess);
    $role = normalizeRole($fallbackRole);

    $officialPositions = ['Barangay Chairman', 'Barangay Official', 'Barangay Secretary'];
    $personnelPositions = [
        'Department Public Assistance Desk',
        'Department Secretary',
        'Department OIC (Officer In Charge)',
        'Barangay Police',
        'Desk Officer',
        'Area OIC',
        'Barangay Treasurer',
    ];

    if (strcasecmp($position, 'IT Administrator') === 0) {
        return 'personnel';
    }
    if (in_array($position, $officialPositions, true)) {
        return 'official';
    }
    if (in_array($position, $personnelPositions, true)) {
        return 'personnel';
    }
    if ($role === 'Personnel') {
        return 'personnel';
    }
    if (in_array($role, ['Official', 'SuperAdmin'], true)) {
        return 'official';
    }

    return 'unknown';
}

function managementAudienceFromRow(array $row): string {
    $protectedCode = amp_get_protected_code($row);
    if ($protectedCode === 'BARANGAY_CAPTAIN') {
        return 'official';
    }

    return managementAudienceFromPosition(
        (string)($row['position_access'] ?? $row['position'] ?? ''),
        (string)($row['account_role_access'] ?? $row['info_role_access'] ?? $row['role_access'] ?? '')
    );
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

function profileApprovalStateFromInvite(?string $inviteStatus, string $role): string {
    $roleKey = strtolower(trim($role));
    if ($roleKey === 'superadmin') {
        return 'Approved';
    }
    $s = strtolower(trim((string)$inviteStatus));
    if ($s === 'completed') return 'Approved';
    if ($s === 'rejectedapproval') return 'Rejected';
    if ($s === 'pendingapproval') return 'PendingApproval';
    if ($s === 'inprogress' || $s === 'pending') return 'Onboarding';
    return 'PendingApproval';
}

function officialsPermissionSummary(array $permissionMap, int $maxLabels = 3): string {
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

function getStatusIdByNames(mysqli $conn, string $statusType, array $preferredNames): ?int {
    return amp_get_status_id_by_names($conn, $statusType, $preferredNames);
}

function loadOfficialAccountOrFail(mysqli $conn, string $userId): array {
    $row = amp_get_official_account_by_user_id($conn, $userId);
    if (!$row) {
        throw new Exception('Account record not found.');
    }
    return $row;
}

function ensureActorCanModifyTarget(string $actorUserId, string $actorProtectedCode, array $targetAccount): void {
    $targetProtectedCode = amp_get_protected_code($targetAccount);
    $targetUserId = (string)($targetAccount['user_id'] ?? '');

    if ($targetProtectedCode === 'BARANGAY_CAPTAIN') {
        throw new Exception('The Barangay Captain account is managed through official transitions.');
    }

    if ($targetProtectedCode === 'IT_SUPERADMIN' && $actorUserId !== $targetUserId) {
        throw new Exception('The protected IT SuperAdmin account cannot be modified by other users.');
    }

    if ($actorProtectedCode === 'BARANGAY_CAPTAIN' && $targetProtectedCode === 'IT_SUPERADMIN' && $actorUserId !== $targetUserId) {
        throw new Exception('The Barangay Captain cannot remove or change the protected IT SuperAdmin account.');
    }
}

function replaceOfficialModulePermissions(mysqli $conn, string $officialId, string $userId, array $permissionKeys, string $grantedByUserId): void {
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
        throw new Exception('Failed to save module permissions.');
    }

    foreach ($permissionKeys as $permissionKey) {
        $insertStmt->bind_param('ssss', $officialId, $userId, $permissionKey, $grantedByUserId);
        $insertStmt->execute();
    }
    $insertStmt->close();
}

function upsertOfficialAccessProfile(mysqli $conn, string $officialId, string $userId): void {
    $stmt = $conn->prepare("
        INSERT INTO officialaccessprofiletbl (official_id, user_id, permissions_initialized)
        VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE
            user_id = VALUES(user_id),
            permissions_initialized = 1,
            updated_at = CURRENT_TIMESTAMP
    ");
    if (!$stmt) {
        throw new Exception('Failed to save access profile metadata.');
    }
    $stmt->bind_param('ss', $officialId, $userId);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $actorRole = (string)($_SESSION['role'] ?? '');
        $actorUserId = (string)($_SESSION['user_id'] ?? '');
        if ($actorRole !== 'SuperAdmin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only SuperAdmin can revoke or restore permissions.']);
            exit;
        }

        $action = trim((string)($_POST['action'] ?? ''));
        $requestedMode = requestedManagementMode((string)($_POST['mode'] ?? 'official'));
        $auditModuleName = $requestedMode === 'personnel' ? 'Personnel Management' : 'Officials Management';
        $officialId = trim((string)($_POST['official_id'] ?? ''));
        if ($officialId === '') {
            throw new Exception('Invalid account record.');
        }

        $statusActiveId = getStatusIdByNames($conn, 'UserAccount', ['Active']);
        $statusInactiveId = getStatusIdByNames($conn, 'UserAccount', ['Inactive', 'Revoked', 'Suspended', 'Disabled']);
        if ($statusActiveId === null || $statusInactiveId === null) {
            throw new Exception('Required UserAccount statuses (Active/Inactive) are missing.');
        }

        $actorAccount = $actorUserId !== '' ? amp_get_official_account_by_user_id($conn, $actorUserId) : null;
        $actorProtectedCode = $actorAccount ? amp_get_protected_code($actorAccount) : '';

        $targetStmt = $conn->prepare("
            SELECT oi.official_id, oi.user_id, ua.status_id_account, ua.role_access,
                   iv.invite_id, iv.status AS invite_status
            FROM officialinformationtbl oi
            INNER JOIN useraccountstbl ua
                ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
            LEFT JOIN officialinvitetbl iv
                ON iv.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
               AND iv.invite_id = (
                    SELECT MAX(oi2.invite_id)
                    FROM officialinvitetbl oi2
                    WHERE oi2.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
               )
            WHERE oi.official_id = ?
            LIMIT 1
        ");
        if (!$targetStmt) throw new Exception('Failed to load account record.');
        $targetStmt->bind_param("s", $officialId);
        $targetStmt->execute();
        $target = $targetStmt->get_result()->fetch_assoc();
        $targetStmt->close();
        if (!$target) {
            throw new Exception('Account record not found.');
        }
        $userId = (string)($target['user_id'] ?? '');
        if ($userId === '') {
            throw new Exception('Target account is invalid.');
        }
        $targetAccount = loadOfficialAccountOrFail($conn, $userId);
        $targetAudience = managementAudienceFromRow($targetAccount);
        if ($requestedMode === 'personnel' && $targetAudience !== 'personnel') {
            throw new Exception('This record is not available in Personnel Tracker.');
        }
        if ($requestedMode === 'official' && $targetAudience !== 'official') {
            throw new Exception('This record is not available in Official Management.');
        }
        $targetProtectedCode = amp_get_protected_code($targetAccount);
        $targetDisplayRole = rowDisplayRole((string)($targetAccount['account_role_access'] ?? ''));
        $oldPermissionMap = amp_get_effective_permission_keys_for_row($conn, $targetAccount);
        $oldPermissionSummary = officialsPermissionSummary($oldPermissionMap);
        ensureActorCanModifyTarget($actorUserId, $actorProtectedCode, $targetAccount);

        $nextStatusId = null;
        $auditAction = '';
        if ($action === 'revoke_permission') {
            if ($targetDisplayRole === 'SuperAdmin' && amp_count_active_superadmins_excluding($conn, $userId) <= 0) {
                throw new Exception('At least one active SuperAdmin account must remain.');
            }
            $nextStatusId = $statusInactiveId;
            $auditAction = 'OFFICIAL_PERMISSION_REVOKE';
        } elseif ($action === 'restore_permission') {
            $nextStatusId = $statusActiveId;
            $auditAction = 'OFFICIAL_PERMISSION_RESTORE';
        } elseif ($action === 'approve_profile' || $action === 'reject_profile') {
            $inviteId = (int)($target['invite_id'] ?? 0);
            if ($inviteId <= 0) {
                throw new Exception('No onboarding invite found for this account.');
            }
            $newInviteStatus = $action === 'approve_profile' ? 'Completed' : 'RejectedApproval';
            $acceptedAtSql = $action === 'approve_profile' ? "NOW()" : "NULL";
            $nextOnboardingStep = $action === 'approve_profile' ? 'completed' : 'document_upload';
            $upInvite = $conn->prepare("
                UPDATE officialinvitetbl
                SET status = ?,
                    onboarding_step = ?,
                    accepted_at = {$acceptedAtSql},
                    updated_at = NOW()
                WHERE invite_id = ?
                LIMIT 1
            ");
            if (!$upInvite) {
                throw new Exception('Failed to update profile approval state.');
            }
            $upInvite->bind_param("ssi", $newInviteStatus, $nextOnboardingStep, $inviteId);
            $upInvite->execute();
            $upInvite->close();

            insertUnifiedAuditLog(
                $conn,
                (string)($_SESSION['user_id'] ?? ''),
                $actorRole,
                $auditModuleName,
                'OfficialProfileApproval',
                $officialId,
                $action === 'approve_profile' ? 'OFFICIAL_PROFILE_APPROVE' : 'OFFICIAL_PROFILE_REJECT',
                'invite_status',
                (string)($target['invite_status'] ?? ''),
                $newInviteStatus,
                $action === 'approve_profile' ? 'Official/Personnel profile approved.' : 'Official/Personnel profile rejected.',
                null
            );

            echo json_encode([
                'success' => true,
                'message' => $action === 'approve_profile' ? 'Profile approved successfully.' : 'Profile rejected successfully.',
                'updated' => true
            ]);
            exit;
        } elseif ($action === 'update_access_profile') {
            $displayRole = trim((string)($_POST['display_role'] ?? 'Admin'));
            $accessExpiresOn = trim((string)($_POST['access_expires_on'] ?? ''));
            $requestedPermissionKeys = $_POST['permission_keys'] ?? [];
            if (!is_array($requestedPermissionKeys)) {
                $requestedPermissionKeys = [];
            }

            if ($targetProtectedCode === 'BARANGAY_CAPTAIN') {
                throw new Exception('The Barangay Captain access level is managed through official transitions.');
            }

            if (!in_array($displayRole, ['Admin', 'SuperAdmin'], true)) {
                throw new Exception('Invalid display role.');
            }
            if ($targetProtectedCode !== '' && $displayRole !== 'SuperAdmin') {
                throw new Exception('Protected accounts must remain SuperAdmin.');
            }

            $validPermissionKeys = array_fill_keys(amp_get_all_leaf_permission_keys(), true);
            $permissionMap = [];
            foreach ($requestedPermissionKeys as $permissionKey) {
                $permissionKey = trim((string)$permissionKey);
                if ($permissionKey !== '' && isset($validPermissionKeys[$permissionKey])) {
                    $permissionMap[$permissionKey] = true;
                }
            }

            if ($displayRole !== 'SuperAdmin') {
                foreach (array_keys($permissionMap) as $permissionKey) {
                    if (amp_is_admin_only_permission($permissionKey)) {
                        throw new Exception('Admin-only modules require the SuperAdmin display role.');
                    }
                }
            }

            if ($targetProtectedCode === 'IT_SUPERADMIN') {
                foreach (amp_get_it_superadmin_locked_permission_keys() as $permissionKey) {
                    $permissionMap[$permissionKey] = true;
                }
            }

            if ($accessExpiresOn !== '') {
                $dt = DateTimeImmutable::createFromFormat('Y-m-d', $accessExpiresOn);
                if (!$dt || $dt->format('Y-m-d') !== $accessExpiresOn) {
                    throw new Exception('Access expiration must be a valid date.');
                }
            }

            $nextStorageRole = $displayRole === 'SuperAdmin'
                ? 'SuperAdmin'
                : amp_storage_role_for_admin_display(
                    (string)($targetAccount['position_access'] ?? ''),
                    (string)($targetAccount['account_role_access'] ?? '')
                );

            if ($targetDisplayRole === 'SuperAdmin' && $nextStorageRole !== 'SuperAdmin' && amp_count_active_superadmins_excluding($conn, $userId) <= 0) {
                throw new Exception('At least one active SuperAdmin account must remain.');
            }

            $today = new DateTimeImmutable('today');
            $expiresImmediately = false;
            if ($accessExpiresOn !== '') {
                try {
                    $expiryDate = new DateTimeImmutable($accessExpiresOn);
                    $expiresImmediately = $expiryDate < $today;
                } catch (Throwable) {
                    $expiresImmediately = false;
                }
            }

            if ($expiresImmediately && $targetDisplayRole === 'SuperAdmin' && amp_count_active_superadmins_excluding($conn, $userId) <= 0) {
                throw new Exception('At least one active SuperAdmin account must remain.');
            }

            $conn->begin_transaction();
            try {
                $upOi = $conn->prepare("
                    UPDATE officialinformationtbl
                    SET role_access = ?,
                        term_end = NULLIF(?, ''),
                        last_updated = CURRENT_TIMESTAMP
                    WHERE official_id = ?
                    LIMIT 1
                ");
                if (!$upOi) {
                    throw new Exception('Failed to update official access profile.');
                }
                $upOi->bind_param('sss', $nextStorageRole, $accessExpiresOn, $officialId);
                $upOi->execute();
                $upOi->close();

                $upUa = $conn->prepare("UPDATE useraccountstbl SET role_access = ?, updated_at = NOW() WHERE user_id = ? LIMIT 1");
                if (!$upUa) {
                    throw new Exception('Failed to sync account role.');
                }
                $upUa->bind_param('ss', $nextStorageRole, $userId);
                $upUa->execute();
                $upUa->close();

                if ($expiresImmediately) {
                    $upStatus = $conn->prepare("UPDATE useraccountstbl SET status_id_account = ?, updated_at = NOW() WHERE user_id = ? LIMIT 1");
                    if ($upStatus) {
                        $upStatus->bind_param('is', $statusInactiveId, $userId);
                        $upStatus->execute();
                        $upStatus->close();
                    }
                }

                replaceOfficialModulePermissions($conn, $officialId, $userId, array_keys($permissionMap), $actorUserId);
                upsertOfficialAccessProfile($conn, $officialId, $userId);
                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }

            insertUnifiedAuditLog(
                $conn,
                $actorUserId,
                $actorRole,
                $auditModuleName,
                'OfficialAccessProfile',
                $officialId,
                'OFFICIAL_ACCESS_PROFILE_UPDATE',
                'display_role / term_end / module_permissions',
                $targetDisplayRole . ' / ' . (string)($targetAccount['term_end'] ?? '') . ' / ' . $oldPermissionSummary,
                $displayRole . ' / ' . $accessExpiresOn . ' / ' . officialsPermissionSummary($permissionMap),
                'Updated access profile and module checklist.',
                null
            );

            echo json_encode([
                'success' => true,
                'message' => 'Access profile updated successfully.',
                'updated' => true,
            ]);
            exit;
        } elseif ($action === 'promote') {
            if ($targetProtectedCode !== '') {
                throw new Exception('Protected accounts cannot be reassigned through promotion.');
            }
            $newPosition = trim((string)($_POST['new_position'] ?? ''));
            $areaNumber  = trim((string)($_POST['area_number'] ?? ''));
            if ($newPosition === '') {
                throw new Exception('New position is required.');
            }

            $positionsByRole = [
                'SuperAdmin' => ['IT Administrator', 'Barangay Chairman'],
                'Official'   => ['Barangay Official', 'Barangay Secretary'],
                'Personnel'  => [
                    'Department Public Assistance Desk',
                    'Department Secretary',
                    'Department OIC (Officer In Charge)',
                    'Barangay Police',
                    'Desk Officer',
                    'Area OIC',
                    'Barangay Treasurer',
                ],
            ];
            $newRole = null;
            foreach ($positionsByRole as $roleKey => $positions) {
                if (in_array($newPosition, $positions, true)) {
                    $newRole = $roleKey;
                    break;
                }
            }
            if ($newRole === null) {
                throw new Exception('Invalid position selected.');
            }
            $newAudience = managementAudienceFromPosition($newPosition, $newRole);
            if ($requestedMode === 'personnel' && $newAudience !== 'personnel') {
                throw new Exception('Personnel Tracker can only assign personnel positions.');
            }
            if ($requestedMode === 'official' && $newAudience !== 'official') {
                throw new Exception('Official Management can only assign official positions.');
            }

            $posColExists  = false;
            $pColRes = $conn->query("SHOW COLUMNS FROM officialinformationtbl LIKE 'position_access'");
            if ($pColRes instanceof mysqli_result && $pColRes->num_rows > 0) $posColExists = true;

            $areaColExists = false;
            $aColRes = $conn->query("SHOW COLUMNS FROM officialinformationtbl LIKE 'area_number'");
            if ($aColRes instanceof mysqli_result && $aColRes->num_rows > 0) $areaColExists = true;

            if ($posColExists && $areaColExists) {
                $upOi = $conn->prepare("UPDATE officialinformationtbl SET role_access = ?, position_access = ?, area_number = ?, last_updated = CURRENT_TIMESTAMP WHERE official_id = ? LIMIT 1");
                if (!$upOi) throw new Exception('Failed to update official record.');
                $upOi->bind_param("ssss", $newRole, $newPosition, $areaNumber, $officialId);
            } elseif ($posColExists) {
                $upOi = $conn->prepare("UPDATE officialinformationtbl SET role_access = ?, position_access = ?, last_updated = CURRENT_TIMESTAMP WHERE official_id = ? LIMIT 1");
                if (!$upOi) throw new Exception('Failed to update official record.');
                $upOi->bind_param("sss", $newRole, $newPosition, $officialId);
            } else {
                $upOi = $conn->prepare("UPDATE officialinformationtbl SET role_access = ?, last_updated = CURRENT_TIMESTAMP WHERE official_id = ? LIMIT 1");
                if (!$upOi) throw new Exception('Failed to update official record.');
                $upOi->bind_param("ss", $newRole, $officialId);
            }
            $upOi->execute();
            $upOi->close();

            $upUa = $conn->prepare("UPDATE useraccountstbl SET role_access = ?, updated_at = NOW() WHERE user_id = ? LIMIT 1");
            if (!$upUa) throw new Exception('Failed to sync user account role.');
            $upUa->bind_param("ss", $newRole, $userId);
            $upUa->execute();
            $upUa->close();

            insertUnifiedAuditLog(
                $conn,
                (string)($_SESSION['user_id'] ?? ''),
                $actorRole,
                $auditModuleName,
                'OfficialAccount',
                $officialId,
                'OFFICIAL_PROMOTE',
                'position_access',
                (string)($target['role_access'] ?? ''),
                "{$newRole} / {$newPosition}",
                "Promoted to {$newRole} / {$newPosition}.",
                null
            );

            echo json_encode([
                'success' => true,
                'message' => "Position updated to {$newPosition} successfully.",
                'updated' => true,
            ]);
            exit;
        } elseif ($action === 'change_department') {
            if ($targetProtectedCode !== '') {
                throw new Exception('Protected accounts cannot be reassigned through department changes.');
            }
            $newDepartment = trim((string)($_POST['new_department'] ?? ''));
            $newDeptPosition = trim((string)($_POST['new_position'] ?? ''));
            $areaNumber    = trim((string)($_POST['area_number'] ?? ''));
            if ($newDepartment === '') {
                throw new Exception('Department is required.');
            }
            if ($newDeptPosition === '') {
                throw new Exception('Position is required.');
            }
            $newAudience = managementAudienceFromPosition($newDeptPosition, '');
            if ($requestedMode === 'personnel' && $newAudience !== 'personnel') {
                throw new Exception('Personnel Tracker can only assign personnel positions.');
            }
            if ($requestedMode === 'official' && $newAudience !== 'official') {
                throw new Exception('Official Management can only assign official positions.');
            }

            $posColExists2  = false;
            $pColRes2 = $conn->query("SHOW COLUMNS FROM officialinformationtbl LIKE 'position_access'");
            if ($pColRes2 instanceof mysqli_result && $pColRes2->num_rows > 0) $posColExists2 = true;

            $areaColExists2 = false;
            $aColRes2 = $conn->query("SHOW COLUMNS FROM officialinformationtbl LIKE 'area_number'");
            if ($aColRes2 instanceof mysqli_result && $aColRes2->num_rows > 0) $areaColExists2 = true;

            // Fetch current values for audit log
            $oldDept = '';
            $oldPos  = '';
            $deptFetch = $conn->prepare("SELECT department, position_access FROM officialinformationtbl WHERE official_id = ? LIMIT 1");
            if ($deptFetch) {
                $deptFetch->bind_param("s", $officialId);
                $deptFetch->execute();
                $deptRow = $deptFetch->get_result()->fetch_assoc();
                $oldDept = (string)($deptRow['department'] ?? '');
                $oldPos  = (string)($deptRow['position_access'] ?? '');
                $deptFetch->close();
            }

            if ($posColExists2 && $areaColExists2 && $areaNumber !== '') {
                $upDept = $conn->prepare("UPDATE officialinformationtbl SET department = ?, position_access = ?, area_number = ?, last_updated = CURRENT_TIMESTAMP WHERE official_id = ? LIMIT 1");
                if (!$upDept) throw new Exception('Failed to update department.');
                $upDept->bind_param("ssss", $newDepartment, $newDeptPosition, $areaNumber, $officialId);
            } elseif ($posColExists2) {
                $upDept = $conn->prepare("UPDATE officialinformationtbl SET department = ?, position_access = ?, last_updated = CURRENT_TIMESTAMP WHERE official_id = ? LIMIT 1");
                if (!$upDept) throw new Exception('Failed to update department.');
                $upDept->bind_param("sss", $newDepartment, $newDeptPosition, $officialId);
            } else {
                $upDept = $conn->prepare("UPDATE officialinformationtbl SET department = ?, last_updated = CURRENT_TIMESTAMP WHERE official_id = ? LIMIT 1");
                if (!$upDept) throw new Exception('Failed to update department.');
                $upDept->bind_param("ss", $newDepartment, $officialId);
            }
            $upDept->execute();
            $upDept->close();

            insertUnifiedAuditLog(
                $conn,
                (string)($_SESSION['user_id'] ?? ''),
                $actorRole,
                $auditModuleName,
                'OfficialAccount',
                $officialId,
                'OFFICIAL_DEPT_CHANGE',
                'department / position_access',
                "{$oldDept} / {$oldPos}",
                "{$newDepartment} / {$newDeptPosition}",
                "Department changed to {$newDepartment}, position to {$newDeptPosition}.",
                null
            );

            echo json_encode([
                'success' => true,
                'message' => "Department changed to {$newDepartment} — {$newDeptPosition} successfully.",
                'updated' => true,
            ]);
            exit;
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
            $auditModuleName,
            'OfficialAccount',
            $officialId,
            $auditAction,
            'status_id_account',
            (string)($target['status_id_account'] ?? ''),
            (string)$nextStatusId,
            $action === 'revoke_permission' ? 'Account permissions revoked (account set inactive).' : 'Account permissions restored (account set active).',
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
    $requestedMode = requestedManagementMode((string)($_GET['mode'] ?? 'official'));
    $roleFilter = trim((string)($_GET['role'] ?? 'ALL'));
    if (!in_array($roleFilter, ['ALL', 'Admin', 'SuperAdmin'], true)) {
        $roleFilter = 'ALL';
    }
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

    $hasAreaNumber = false;
    $areaColRes = $conn->query("SHOW COLUMNS FROM officialinformationtbl LIKE 'area_number'");
    if ($areaColRes instanceof mysqli_result && $areaColRes->num_rows > 0) {
        $hasAreaNumber = true;
    }
    $areaField = $hasAreaNumber ? 'oi.area_number' : 'NULL AS area_number';

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
            {$areaField},
            oi.department,
            oi.term_end,
            oi.date_hired,
            COALESCE(se.status_name, CONCAT('Status #', oi.status_id_employment)) AS employment_status,
            ua.email,
            ua.phone_number,
            ua.role_access AS account_role_access,
            COALESCE(sa.status_name, CONCAT('Status #', ua.status_id_account)) AS account_status,
            iv.status AS invite_status
        FROM officialinformationtbl oi
        INNER JOIN useraccountstbl ua ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
        LEFT JOIN statuslookuptbl se ON se.status_id = oi.status_id_employment
        LEFT JOIN statuslookuptbl sa ON sa.status_id = ua.status_id_account
        LEFT JOIN officialinvitetbl iv
            ON iv.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
           AND iv.invite_id = (
                SELECT MAX(oi2.invite_id)
                FROM officialinvitetbl oi2
                WHERE oi2.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
           )
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
    $actorUserId = (string)($_SESSION['user_id'] ?? '');
    while ($row = $res->fetch_assoc()) {
        $role = normalizeRole((string)($row['account_role_access'] ?? $row['info_role_access'] ?? ''));
        if (!in_array($role, ['Official', 'Personnel', 'SuperAdmin'], true)) {
            continue;
        }
        $protectedCode = amp_get_protected_code($row);
        $positionAccess = trim((string)($row['position_access'] ?? ''));
        $audience = managementAudienceFromRow($row);
        if ($requestedMode === 'personnel' && $audience !== 'personnel') {
            continue;
        }
        if ($requestedMode === 'personnel' && $actorUserId !== '' && strcasecmp((string)($row['user_id'] ?? ''), $actorUserId) === 0) {
            continue;
        }
        if ($requestedMode === 'official' && $audience !== 'official') {
            continue;
        }
        $displayRole = rowDisplayRole((string)($row['account_role_access'] ?? $row['info_role_access'] ?? ''));
        if ($roleFilter !== 'ALL' && $displayRole !== $roleFilter) {
            continue;
        }

        $fullName = trim(
            (string)($row['firstname'] ?? '') . ' ' .
            ((string)($row['middlename'] ?? '') !== '' ? substr((string)$row['middlename'], 0, 1) . '. ' : '') .
            (string)($row['lastname'] ?? '') .
            ((string)($row['suffix'] ?? '') !== '' ? ' ' . (string)$row['suffix'] : '')
        );

        $permissionMap = amp_get_effective_permission_keys_for_row($conn, $row);
        $canEditAccess = !(
            $protectedCode === 'BARANGAY_CAPTAIN'
            || ($protectedCode === 'IT_SUPERADMIN' && $actorUserId !== (string)($row['user_id'] ?? ''))
        );

        $rows[] = [
            'official_id' => (string)($row['official_id'] ?? ''),
            'user_id' => (string)($row['user_id'] ?? ''),
            'full_name' => $fullName !== '' ? $fullName : '—',
            'role_access' => $audience === 'personnel' ? 'Personnel' : 'Official',
            'display_role' => $displayRole,
            'position_access' => $positionAccess,
            'area_number' => (string)($row['area_number'] ?? ''),
            'department' => (string)($row['department'] ?? ''),
            'employment_status' => (string)($row['employment_status'] ?? ''),
            'access_expires_on' => (string)($row['term_end'] ?? ''),
            'date_hired' => (string)($row['date_hired'] ?? ''),
            'email' => (string)($row['email'] ?? ''),
            'phone_number' => (string)($row['phone_number'] ?? ''),
            'account_status' => (string)($row['account_status'] ?? ''),
            'permission_state' => permissionStateFromAccountStatus((string)($row['account_status'] ?? '')),
            'profile_approval_state' => profileApprovalStateFromInvite((string)($row['invite_status'] ?? ''), $role),
            'protected_code' => $protectedCode,
            'protected_label' => amp_get_protected_label($protectedCode),
            'module_count' => count($permissionMap),
            'module_summary' => officialsPermissionSummary($permissionMap),
            'permission_keys' => array_keys($permissionMap),
            'locked_permission_keys' => $protectedCode === 'IT_SUPERADMIN' ? amp_get_it_superadmin_locked_permission_keys() : [],
            'can_edit_access' => $canEditAccess,
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
            'SuperAdmin' => count(array_filter($rows, fn($r) => ($r['display_role'] ?? '') === 'SuperAdmin')),
            'Admin' => count(array_filter($rows, fn($r) => ($r['display_role'] ?? '') === 'Admin')),
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
