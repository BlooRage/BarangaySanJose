<?php
session_start();
require_once "../General/connection.php";
require_once "../General/security.php";
require_once "../General/audit.php";
require_once "../General/officialInviteCommon.php";
require_once "../General/adminModulePermissions.php";
require_once "../General/officialGovernance.php";

requireRoleSession(['SuperAdmin']);
oi_ensure_invite_table($conn);
amp_ensure_permission_storage($conn);
ogw_ensure_schema($conn);

header('Content-Type: application/json; charset=utf-8');

function normalizeRole(string $role): string {
    $k = strtolower(trim($role));
    if ($k === 'officials' || $k === 'official' || $k === 'admin') return 'Official';
    if ($k === 'employee') return 'Personnel';
    if ($k === 'personnels' || $k === 'personnel') return 'Personnel';
    if ($k === 'superadmin') return 'SuperAdmin';
    return trim($role);
}

function rowDisplayRole(string $role): string {
    return amp_storage_role_to_display_role($role);
}

function requestedManagementMode(string $rawMode): string {
    $mode = strtolower(trim($rawMode));
    return in_array($mode, ['official', 'personnel', 'admin'], true) ? $mode : 'official';
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
        return 'admin';
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
    if ($role === 'SuperAdmin') {
        return 'admin';
    }
    if ($role === 'Official') {
        return 'official';
    }

    return 'unknown';
}

function managementModeLabel(string $mode): string {
    return match ($mode) {
        'personnel' => 'Personnel Management',
        'admin' => 'Admin Management',
        default => 'Officials Management',
    };
}

function managementEntityLabel(string $mode): string {
    return match ($mode) {
        'personnel' => 'Personnel',
        'admin' => 'Admin',
        default => 'Official',
    };
}

function managementWorkflowScope(string $mode): string {
    return match ($mode) {
        'personnel' => 'Personnel',
        'admin' => 'SuperAdmin',
        default => 'Official',
    };
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

function officialsManagementDecryptRow(array $row): array
{
    $row = pii_decrypt_assoc($row, ['firstname', 'middlename', 'lastname', 'suffix']);
    return pii_decrypt_useraccount_row($row) ?? $row;
}

function officialsManagementMatchesSearch(array $row, string $needle): bool
{
    return pii_search_match($row, [
        'official_id',
        'user_id',
        'firstname',
        'middlename',
        'lastname',
        'department',
        'position_access',
        'email',
        'phone_number',
    ], $needle);
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

function superadminManagementDisabledReason(mysqli $conn, string $actorUserId, array $targetAccount): string {
    $targetDisplayRole = rowDisplayRole((string)($targetAccount['account_role_access'] ?? $targetAccount['info_role_access'] ?? $targetAccount['role_access'] ?? ''));
    return amp_get_superadmin_management_disabled_reason($conn, $actorUserId, $targetDisplayRole);
}

function ensureActorCanModifyTarget(string $actorUserId, string $actorProtectedCode, array $targetAccount): void {
    $targetProtectedCode = amp_get_protected_code($targetAccount);

    if ($targetProtectedCode === 'BARANGAY_CAPTAIN') {
        throw new Exception('The Barangay Captain account is managed through official transitions.');
    }
}

function replaceOfficialModulePermissions(mysqli $conn, string $officialId, string $userId, array $permissionKeys, string $grantedByUserId): void {
    amp_replace_official_module_permissions($conn, $officialId, $userId, $permissionKeys, $grantedByUserId);
}

function upsertOfficialAccessProfile(mysqli $conn, string $officialId, string $userId): void {
    amp_upsert_official_access_profile($conn, $officialId, $userId);
}

function officialsManagementEnsureProfileColumns(mysqli $conn): void {
    $columnSql = [
        'position_access' => "ALTER TABLE officialinformationtbl ADD COLUMN position_access VARCHAR(100) DEFAULT NULL AFTER role_access",
        'area_number' => "ALTER TABLE officialinformationtbl ADD COLUMN area_number VARCHAR(50) NULL AFTER department",
        'emergency_contact_name' => "ALTER TABLE officialinformationtbl ADD COLUMN emergency_contact_name VARCHAR(255) NULL AFTER email",
        'emergency_contact_relationship' => "ALTER TABLE officialinformationtbl ADD COLUMN emergency_contact_relationship VARCHAR(255) NULL AFTER emergency_contact_name",
        'emergency_contact_phone' => "ALTER TABLE officialinformationtbl ADD COLUMN emergency_contact_phone VARCHAR(255) NULL AFTER emergency_contact_relationship",
        'emergency_contact_address' => "ALTER TABLE officialinformationtbl ADD COLUMN emergency_contact_address VARCHAR(512) NULL AFTER emergency_contact_phone",
        'house_number' => "ALTER TABLE officialinformationtbl ADD COLUMN house_number VARCHAR(255) NULL AFTER emergency_contact_address",
        'street_name' => "ALTER TABLE officialinformationtbl ADD COLUMN street_name VARCHAR(255) NULL AFTER house_number",
        'subdivision' => "ALTER TABLE officialinformationtbl ADD COLUMN subdivision VARCHAR(255) NULL AFTER street_name",
        'address_mode' => "ALTER TABLE officialinformationtbl ADD COLUMN address_mode VARCHAR(255) NULL AFTER subdivision",
        'block_number' => "ALTER TABLE officialinformationtbl ADD COLUMN block_number VARCHAR(255) NULL AFTER address_mode",
        'lot_number' => "ALTER TABLE officialinformationtbl ADD COLUMN lot_number VARCHAR(255) NULL AFTER block_number",
        'barangay' => "ALTER TABLE officialinformationtbl ADD COLUMN barangay VARCHAR(255) NULL AFTER lot_number",
        'municipality_city' => "ALTER TABLE officialinformationtbl ADD COLUMN municipality_city VARCHAR(255) NULL AFTER barangay",
        'province' => "ALTER TABLE officialinformationtbl ADD COLUMN province VARCHAR(255) NULL AFTER municipality_city",
    ];

    foreach ($columnSql as $column => $sql) {
        if (!amp_column_exists($conn, 'officialinformationtbl', $column)) {
            $conn->query($sql);
        }
    }
}

function officialsManagementNormalizePhone10(string $rawPhone): string {
    $digits = preg_replace('/\D+/', '', $rawPhone);
    if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
        $digits = substr($digits, 1);
    }
    if (strlen($digits) === 12 && str_starts_with($digits, '63')) {
        $digits = substr($digits, 2);
    }
    return substr($digits, 0, 10);
}

function officialsManagementFormatPhoneForInput(string $rawPhone): string {
    $digits = preg_replace('/\D+/', '', $rawPhone);
    if (strlen($digits) === 10) {
        return '0' . $digits;
    }
    if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
        return $digits;
    }
    if (strlen($digits) === 12 && str_starts_with($digits, '63')) {
        return '0' . substr($digits, 2);
    }
    return trim($rawPhone);
}

function officialsManagementIsValidDate(string $date): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    return $dt instanceof DateTimeImmutable && $dt->format('Y-m-d') === $date;
}

function officialsManagementBuildFullName(array $row): string {
    return trim(implode(' ', array_values(array_filter([
        trim((string)($row['firstname'] ?? '')),
        trim((string)($row['middlename'] ?? '')),
        trim((string)($row['lastname'] ?? '')),
        trim((string)($row['suffix'] ?? '')),
    ], static fn ($value): bool => $value !== ''))));
}

function officialsManagementAuditProfileSummary(array $row): string {
    $name = officialsManagementBuildFullName($row);
    $phone = officialsManagementFormatPhoneForInput((string)($row['contact_number'] ?? ''));
    $email = trim((string)($row['email'] ?? ''));

    return trim(implode(' / ', array_values(array_filter([
        $name,
        $phone,
        $email,
    ], static fn ($value): bool => trim((string)$value) !== ''))));
}

function officialsManagementAssertContactAvailable(mysqli $conn, string $email, string $phone10, string $excludeUserId = ''): void {
    $prepared = pii_prepare_useraccount_contacts($email, $phone10);

    $clauses = [];
    $params = [];
    $types = '';

    if (!empty($prepared['email_lookup_hash'])) {
        $clauses[] = 'email_lookup_hash = ?';
        $params[] = $prepared['email_lookup_hash'];
        $types .= 's';
    }
    if (!empty($prepared['phone_lookup_hash'])) {
        $clauses[] = 'phone_lookup_hash = ?';
        $params[] = $prepared['phone_lookup_hash'];
        $types .= 's';
    }

    if (!$clauses) {
        return;
    }

    $sql = 'SELECT user_id FROM useraccountstbl WHERE (' . implode(' OR ', $clauses) . ')';
    if ($excludeUserId !== '') {
        $sql .= ' AND user_id <> ?';
        $params[] = $excludeUserId;
        $types .= 's';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Unable to validate contact information.');
    }

    $refs = [$types];
    foreach ($params as $idx => $value) {
        $refs[] = &$params[$idx];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        throw new Exception('Email address or mobile number is already assigned to another account.');
    }
}

function officialsManagementProfileEditability(mysqli $conn, string $actorUserId, string $actorProtectedCode, array $targetAccount): array {
    try {
        ensureActorCanModifyTarget($actorUserId, $actorProtectedCode, $targetAccount);
    } catch (Exception $e) {
        return [false, $e->getMessage()];
    }

    $superadminManageReason = superadminManagementDisabledReason($conn, $actorUserId, $targetAccount);
    if ($superadminManageReason !== '') {
        return [false, $superadminManageReason];
    }

    return [true, ''];
}

function officialsManagementLoadProfileDetailOrFail(mysqli $conn, string $officialId): array {
    $stmt = $conn->prepare("
        SELECT
            oi.official_id,
            oi.user_id,
            oi.lastname,
            oi.firstname,
            oi.middlename,
            oi.suffix,
            oi.birthdate,
            oi.sex,
            oi.civil_status,
            oi.contact_number,
            oi.email,
            oi.department,
            oi.position_access,
            oi.area_number,
            oi.date_hired,
            oi.emergency_contact_name,
            oi.emergency_contact_relationship,
            oi.emergency_contact_phone,
            oi.emergency_contact_address,
            oi.house_number,
            oi.street_name,
            oi.subdivision,
            oi.address_mode,
            oi.block_number,
            oi.lot_number,
            oi.barangay,
            oi.municipality_city,
            oi.province,
            ua.phone_number AS account_phone_number,
            ua.email AS account_email,
            ua.role_access AS account_role_access,
            COALESCE(sa.status_name, CONCAT('Status #', ua.status_id_account)) AS account_status,
            COALESCE(se.status_name, CONCAT('Status #', oi.status_id_employment)) AS employment_status
        FROM officialinformationtbl oi
        INNER JOIN useraccountstbl ua
            ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
        LEFT JOIN statuslookuptbl sa ON sa.status_id = ua.status_id_account
        LEFT JOIN statuslookuptbl se ON se.status_id = oi.status_id_employment
        WHERE oi.official_id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        throw new Exception('Failed to load profile details.');
    }

    $stmt->bind_param('s', $officialId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        throw new Exception('Profile not found.');
    }

    $row = pii_decrypt_official_row($row) ?? $row;
    $row = pii_decrypt_assoc($row, ['account_phone_number', 'account_email']);

    $contactNumber = trim((string)($row['contact_number'] ?? ''));
    $accountPhone = trim((string)($row['account_phone_number'] ?? ''));
    $email = trim((string)($row['email'] ?? ''));
    $accountEmail = trim((string)($row['account_email'] ?? ''));

    if ($contactNumber === '') {
        $contactNumber = $accountPhone;
    }
    if ($email === '') {
        $email = $accountEmail;
    }

    $row['contact_number'] = officialsManagementFormatPhoneForInput($contactNumber);
    $row['email'] = $email;
    $row['emergency_contact_phone'] = officialsManagementFormatPhoneForInput((string)($row['emergency_contact_phone'] ?? ''));
    $row['address_mode'] = in_array(strtolower(trim((string)($row['address_mode'] ?? ''))), ['street', 'block_lot'], true)
        ? strtolower(trim((string)($row['address_mode'] ?? '')))
        : 'street';
    $row['display_role'] = rowDisplayRole((string)($row['account_role_access'] ?? ''));
    $row['full_name'] = officialsManagementBuildFullName($row);

    return $row;
}

officialsManagementEnsureProfileColumns($conn);

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
        $auditModuleName = managementModeLabel($requestedMode);
        $secureModuleKey = match ($requestedMode) {
            'personnel' => 'officials_management',
            'admin' => 'admin_management',
            default => 'official_records_management',
        };
        if ($action === 'request_secure_action_otp') {
            $secureAction = trim((string)($_POST['secure_action'] ?? ''));
            $allowedSecureActions = ['send_all_invites', 'send_invite', 'revoke_permission', 'restore_permission', 'lock_account', 'unlock_account'];
            if (!in_array($secureAction, $allowedSecureActions, true)) {
                throw new Exception('This action cannot use secure confirmation.');
            }

            $targetLabel = strtolower(managementEntityLabel($requestedMode)) . ' workflow action';
            $officialIdForLabel = trim((string)($_POST['official_id'] ?? ''));
            if ($officialIdForLabel !== '') {
                $targetDetail = officialsManagementLoadProfileDetailOrFail($conn, $officialIdForLabel);
                $targetLabel = trim((string)($targetDetail['full_name'] ?? ''));
                if ($targetLabel === '') {
                    $targetLabel = $officialIdForLabel;
                }
                if (in_array($secureAction, ['revoke_permission', 'lock_account'], true)) {
                    $targetLabel = 'lock account for ' . $targetLabel;
                } elseif (in_array($secureAction, ['restore_permission', 'unlock_account'], true)) {
                    $targetLabel = 'unlock account for ' . $targetLabel;
                } elseif ($secureAction === 'send_invite') {
                    $targetLabel = 'send onboarding invite for ' . $targetLabel;
                }
            } elseif ($secureAction === 'send_all_invites') {
                $targetLabel = 'send all ready onboarding invites';
            }

            $challenge = ogw_issue_secure_action_otp(
                $conn,
                $actorUserId,
                (string)($_POST['actor_password'] ?? ''),
                $secureModuleKey,
                $secureAction,
                $targetLabel
            );
            echo json_encode([
                'success' => true,
                'message' => 'OTP sent to ' . ($challenge['delivery_label'] !== '' ? $challenge['delivery_label'] : 'your verified contact') . '.',
                'challenge_key' => $challenge['challenge_key'],
                'expires_at' => $challenge['expires_at'],
                'delivery_label' => $challenge['delivery_label'],
            ]);
            exit;
        }
        if ($action === 'send_all_invites') {
            ogw_consume_secure_action_otp(
                $conn,
                $actorUserId,
                (string)($_POST['challenge_key'] ?? ''),
                (string)($_POST['otp_code'] ?? ''),
                $secureModuleKey,
                'send_all_invites'
            );
            $scope = managementWorkflowScope($requestedMode);
            $result = ogw_send_all_ready_invites($conn, $actorUserId, $scope);
            insertUnifiedAuditLog(
                $conn,
                $actorUserId,
                $actorRole,
                $auditModuleName,
                'OfficialProfileWorkflow',
                $scope,
                'OFFICIAL_SEND_ALL_INVITES',
                'invite_batch',
                null,
                (string)count($result['sent']),
                'Sent onboarding invites for ready profiles.'
            );
            echo json_encode([
                'success' => true,
                'message' => count($result['sent']) . ' invite(s) sent.',
                'sent' => $result['sent'],
                'skipped' => $result['skipped'],
            ]);
            exit;
        }
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
        if ($requestedMode === 'admin' && $targetAudience !== 'admin') {
            throw new Exception('This record is not available in Admin Management.');
        }
        if ($requestedMode === 'official' && $targetAudience !== 'official') {
            throw new Exception('This record is not available in Official Management.');
        }
        $targetProtectedCode = amp_get_protected_code($targetAccount);
        $targetDisplayRole = rowDisplayRole((string)($targetAccount['account_role_access'] ?? $targetAccount['info_role_access'] ?? $targetAccount['role_access'] ?? ''));
        $oldPermissionMap = amp_get_effective_permission_keys_for_row($conn, $targetAccount);
        $oldPermissionSummary = officialsPermissionSummary($oldPermissionMap);
        ensureActorCanModifyTarget($actorUserId, $actorProtectedCode, $targetAccount);
        $superadminManageReason = superadminManagementDisabledReason($conn, $actorUserId, $targetAccount);
        if ($superadminManageReason !== '') {
            throw new Exception($superadminManageReason);
        }

        if ($action === 'mark_ready_for_invite') {
            $workflow = ogw_sync_profile_workflow($conn, $officialId);
            if (!$workflow['ready_for_invite']) {
                throw new Exception('Complete the profile first before marking it ready for invite.');
            }
            echo json_encode([
                'success' => true,
                'message' => 'Profile is ready for invite.',
                'workflow' => $workflow,
            ]);
            exit;
        }

        if ($action === 'send_invite') {
            ogw_consume_secure_action_otp(
                $conn,
                $actorUserId,
                (string)($_POST['challenge_key'] ?? ''),
                (string)($_POST['otp_code'] ?? ''),
                $secureModuleKey,
                'send_invite'
            );
            $invite = ogw_send_official_invite($conn, $officialId, $actorUserId);
            insertUnifiedAuditLog(
                $conn,
                $actorUserId,
                $actorRole,
                $auditModuleName,
                'OfficialProfileWorkflow',
                $officialId,
                'OFFICIAL_SEND_INVITE',
                'invite_id',
                null,
                (string)($invite['invite_id'] ?? ''),
                'Sent onboarding invite from profile workflow.'
            );
            echo json_encode([
                'success' => true,
                'message' => 'Onboarding invite sent successfully.',
                'invite' => $invite,
            ]);
            exit;
        }

        if ($action === 'update_profile_info') {
            $targetDetail = officialsManagementLoadProfileDetailOrFail($conn, $officialId);

            $lastName = trim((string)($_POST['lastname'] ?? ''));
            $firstName = trim((string)($_POST['firstname'] ?? ''));
            $middleName = trim((string)($_POST['middlename'] ?? ''));
            $suffix = trim((string)($_POST['suffix'] ?? ''));
            $birthdate = trim((string)($_POST['birthdate'] ?? ''));
            $sex = trim((string)($_POST['sex'] ?? ''));
            $civilStatus = trim((string)($_POST['civil_status'] ?? ''));
            $contactNumberRaw = trim((string)($_POST['contact_number'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $emergencyName = trim((string)($_POST['emergency_contact_name'] ?? ''));
            $emergencyRelationship = trim((string)($_POST['emergency_contact_relationship'] ?? ''));
            $emergencyPhoneRaw = trim((string)($_POST['emergency_contact_phone'] ?? ''));
            $emergencyAddress = trim((string)($_POST['emergency_contact_address'] ?? ''));
            $addressMode = strtolower(trim((string)($_POST['address_mode'] ?? 'street')));
            $houseNumber = trim((string)($_POST['house_number'] ?? ''));
            $streetName = trim((string)($_POST['street_name'] ?? ''));
            $subdivision = trim((string)($_POST['subdivision'] ?? ''));
            $blockNumber = trim((string)($_POST['block_number'] ?? ''));
            $lotNumber = trim((string)($_POST['lot_number'] ?? ''));
            $barangay = trim((string)($_POST['barangay'] ?? ''));
            $municipalityCity = trim((string)($_POST['municipality_city'] ?? ''));
            $province = trim((string)($_POST['province'] ?? ''));

            if ($lastName === '' || $firstName === '' || $birthdate === '' || $sex === '' || $civilStatus === '' || $contactNumberRaw === '' || $email === '') {
                throw new Exception('Complete the required personal and contact fields.');
            }

            $namePattern = '/^[A-Za-z][A-Za-z .\'-]{0,99}$/';
            if (!preg_match($namePattern, $lastName) || !preg_match($namePattern, $firstName)) {
                throw new Exception('First name and last name contain invalid characters.');
            }
            if ($middleName !== '' && !preg_match($namePattern, $middleName)) {
                throw new Exception('Middle name contains invalid characters.');
            }
            if ($suffix !== '' && !preg_match('/^[A-Za-z0-9 .\'-]{1,20}$/', $suffix)) {
                throw new Exception('Suffix contains invalid characters.');
            }
            if (!officialsManagementIsValidDate($birthdate)) {
                throw new Exception('Birthdate must be a valid date.');
            }
            $birthdateObj = new DateTimeImmutable($birthdate);
            if ($birthdateObj > new DateTimeImmutable('today')) {
                throw new Exception('Birthdate cannot be in the future.');
            }
            if (!in_array($sex, ['Male', 'Female', 'Other'], true)) {
                throw new Exception('Select a valid sex value.');
            }
            if (!in_array($civilStatus, ['Single', 'Married', 'Widowed', 'Separated'], true)) {
                throw new Exception('Select a valid civil status.');
            }

            $contactNumber = officialsManagementNormalizePhone10($contactNumberRaw);
            if (!preg_match('/^9\d{9}$/', $contactNumber)) {
                throw new Exception('Mobile number must use the format 09XXXXXXXXX.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email address is invalid.');
            }

            $hasEmergencyData = $emergencyName !== '' || $emergencyRelationship !== '' || $emergencyPhoneRaw !== '' || $emergencyAddress !== '';
            $emergencyPhone = '';
            if ($hasEmergencyData) {
                if ($emergencyName === '' || $emergencyRelationship === '' || $emergencyPhoneRaw === '' || $emergencyAddress === '') {
                    throw new Exception('Complete all emergency contact fields or leave them all blank.');
                }
                $emergencyPhone = officialsManagementNormalizePhone10($emergencyPhoneRaw);
                if (!preg_match('/^9\d{9}$/', $emergencyPhone)) {
                    throw new Exception('Emergency contact number must use the format 09XXXXXXXXX.');
                }
            }

            if (!in_array($addressMode, ['street', 'block_lot'], true)) {
                $addressMode = 'street';
            }

            $hasAddressData = $houseNumber !== '' || $streetName !== '' || $subdivision !== '' || $blockNumber !== '' || $lotNumber !== '' || $barangay !== '' || $municipalityCity !== '' || $province !== '';
            if ($hasAddressData) {
                if ($barangay === '' || $municipalityCity === '' || $province === '') {
                    throw new Exception('Barangay, municipality/city, and province are required when saving an address.');
                }
                if ($addressMode === 'street' && ($houseNumber === '' || $streetName === '')) {
                    throw new Exception('House number and street name are required for street-mode addresses.');
                }
                if ($addressMode === 'block_lot' && ($blockNumber === '' || $lotNumber === '')) {
                    throw new Exception('Block number and lot number are required for block/lot addresses.');
                }
            } else {
                $addressMode = 'street';
            }

            if ($addressMode === 'street') {
                $blockNumber = '';
                $lotNumber = '';
            } else {
                $houseNumber = '';
                $streetName = '';
            }

            officialsManagementAssertContactAvailable($conn, $email, $contactNumber, $userId);
            $preparedContacts = pii_prepare_useraccount_contacts($email, $contactNumber);
            $encryptedOfficial = pii_encrypt_field_map([
                'lastname' => $lastName,
                'firstname' => $firstName,
                'middlename' => $middleName,
                'suffix' => $suffix,
                'birthdate' => $birthdate,
                'sex' => $sex,
                'civil_status' => $civilStatus,
                'contact_number' => $contactNumber,
                'email' => $email,
                'emergency_contact_name' => $emergencyName,
                'emergency_contact_relationship' => $emergencyRelationship,
                'emergency_contact_phone' => $emergencyPhone,
                'emergency_contact_address' => $emergencyAddress,
                'house_number' => $houseNumber,
                'street_name' => $streetName,
                'address_mode' => $hasAddressData ? $addressMode : '',
                'block_number' => $blockNumber,
                'lot_number' => $lotNumber,
                'barangay' => $barangay,
                'municipality_city' => $municipalityCity,
                'province' => $province,
            ]);

            $oldSummary = officialsManagementAuditProfileSummary($targetDetail);
            $newSummary = officialsManagementAuditProfileSummary([
                'firstname' => $firstName,
                'middlename' => $middleName,
                'lastname' => $lastName,
                'suffix' => $suffix,
                'contact_number' => $contactNumber,
                'email' => $email,
            ]);

            $conn->begin_transaction();
            try {
                $updateAccount = $conn->prepare("
                    UPDATE useraccountstbl
                    SET email = ?,
                        email_lookup_hash = ?,
                        phone_number = ?,
                        phone_lookup_hash = ?,
                        updated_at = NOW()
                    WHERE user_id = ?
                    LIMIT 1
                ");
                if (!$updateAccount) {
                    throw new Exception('Failed to prepare user account update.');
                }
                oi_bind_string_params($updateAccount, [
                    $preparedContacts['email'],
                    $preparedContacts['email_lookup_hash'],
                    $preparedContacts['phone_number'],
                    $preparedContacts['phone_lookup_hash'],
                    $userId
                ]);
                $updateAccount->execute();
                $updateAccount->close();

                $updateOfficial = $conn->prepare("
                    UPDATE officialinformationtbl
                    SET lastname = ?,
                        firstname = ?,
                        middlename = ?,
                        suffix = ?,
                        birthdate = ?,
                        sex = ?,
                        civil_status = ?,
                        contact_number = ?,
                        email = ?,
                        emergency_contact_name = ?,
                        emergency_contact_relationship = ?,
                        emergency_contact_phone = ?,
                        emergency_contact_address = ?,
                        house_number = ?,
                        street_name = ?,
                        subdivision = ?,
                        address_mode = ?,
                        block_number = ?,
                        lot_number = ?,
                        barangay = ?,
                        municipality_city = ?,
                        province = ?,
                        last_updated = CURRENT_TIMESTAMP
                    WHERE official_id = ?
                    LIMIT 1
                ");
                if (!$updateOfficial) {
                    throw new Exception('Failed to prepare profile update.');
                }
                oi_bind_string_params($updateOfficial, [
                    $encryptedOfficial['lastname'],
                    $encryptedOfficial['firstname'],
                    $encryptedOfficial['middlename'],
                    $encryptedOfficial['suffix'],
                    $encryptedOfficial['birthdate'],
                    $encryptedOfficial['sex'],
                    $encryptedOfficial['civil_status'],
                    $encryptedOfficial['contact_number'],
                    $encryptedOfficial['email'],
                    $encryptedOfficial['emergency_contact_name'],
                    $encryptedOfficial['emergency_contact_relationship'],
                    $encryptedOfficial['emergency_contact_phone'],
                    $encryptedOfficial['emergency_contact_address'],
                    $encryptedOfficial['house_number'],
                    $encryptedOfficial['street_name'],
                    $subdivision,
                    $encryptedOfficial['address_mode'],
                    $encryptedOfficial['block_number'],
                    $encryptedOfficial['lot_number'],
                    $encryptedOfficial['barangay'],
                    $encryptedOfficial['municipality_city'],
                    $encryptedOfficial['province'],
                    $officialId
                ]);
                $updateOfficial->execute();
                $updateOfficial->close();

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
                'OfficialProfile',
                $officialId,
                'OFFICIAL_PROFILE_INFO_UPDATE',
                'profile_information',
                $oldSummary,
                $newSummary,
                'Updated profile information.',
                null
            );

            $updatedProfile = officialsManagementLoadProfileDetailOrFail($conn, $officialId);
            [$canEditProfile, $editProfileDisabledReason] = officialsManagementProfileEditability($conn, $actorUserId, $actorProtectedCode, $targetAccount);

            echo json_encode([
                'success' => true,
                'message' => managementEntityLabel($requestedMode) . ' profile updated successfully.',
                'data' => array_merge($updatedProfile, [
                    'can_edit_profile' => $canEditProfile,
                    'edit_profile_disabled_reason' => $editProfileDisabledReason,
                ]),
            ]);
            exit;
        }

        $nextStatusId = null;
        $auditAction = '';
        if ($action === 'revoke_permission' || $action === 'lock_account') {
            ogw_consume_secure_action_otp(
                $conn,
                $actorUserId,
                (string)($_POST['challenge_key'] ?? ''),
                (string)($_POST['otp_code'] ?? ''),
                $secureModuleKey,
                'revoke_permission'
            );
            if ($targetDisplayRole === 'SuperAdmin' && amp_count_active_superadmins_excluding($conn, $userId) <= 0) {
                throw new Exception('At least one active SuperAdmin account must remain.');
            }
            $nextStatusId = $statusInactiveId;
            $auditAction = 'OFFICIAL_ACCOUNT_LOCK';
        } elseif ($action === 'restore_permission' || $action === 'unlock_account') {
            ogw_consume_secure_action_otp(
                $conn,
                $actorUserId,
                (string)($_POST['challenge_key'] ?? ''),
                (string)($_POST['otp_code'] ?? ''),
                $secureModuleKey,
                'restore_permission'
            );
            $nextStatusId = $statusActiveId;
            $auditAction = 'OFFICIAL_ACCOUNT_UNLOCK';
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
            throw new Exception('Access assignment now belongs to Access Control. Use the Access Control module for permissions and area access.');
        } elseif ($action === 'promote') {
            throw new Exception('Seat changes and promotions now belong to Official Transition.');
        } elseif ($action === 'change_department') {
            throw new Exception('Seat assignment, department assignment, and demotion now belong to Official Transition.');
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
            'message' => ($action === 'revoke_permission' || $action === 'lock_account')
                ? 'Account locked successfully.'
                : 'Account unlocked successfully.',
            'updated' => $affected > 0
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['fetch_official_profile'])) {
    try {
        $officialId = trim((string)($_GET['official_id'] ?? ''));
        if ($officialId === '') {
            throw new Exception('Official record is required.');
        }

        $requestedMode = requestedManagementMode((string)($_GET['mode'] ?? 'official'));
        if (!in_array($requestedMode, ['personnel', 'admin'], true)) {
            throw new Exception('Profile viewing is available in Personnel Tracker and Admin Management only.');
        }

        $actorUserId = (string)($_SESSION['user_id'] ?? '');
        $actorAccount = $actorUserId !== '' ? amp_get_official_account_by_user_id($conn, $actorUserId) : null;
        $actorProtectedCode = $actorAccount ? amp_get_protected_code($actorAccount) : '';

        $profile = officialsManagementLoadProfileDetailOrFail($conn, $officialId);
        $targetAccount = loadOfficialAccountOrFail($conn, (string)($profile['user_id'] ?? ''));
        $targetAudience = managementAudienceFromRow($targetAccount);
        if ($requestedMode === 'personnel' && $targetAudience !== 'personnel') {
            throw new Exception('This record is not available in Personnel Tracker.');
        }
        if ($requestedMode === 'admin' && $targetAudience !== 'admin') {
            throw new Exception('This record is not available in Admin Management.');
        }

        [$canEditProfile, $editProfileDisabledReason] = officialsManagementProfileEditability($conn, $actorUserId, $actorProtectedCode, $targetAccount);

        echo json_encode([
            'success' => true,
            'data' => array_merge($profile, [
                'can_edit_profile' => $canEditProfile,
                'edit_profile_disabled_reason' => $editProfileDisabledReason,
            ]),
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

    $sql .= " ORDER BY oi.official_id DESC";
    if ($q === '') {
        $sql .= " LIMIT ?";
        $params[] = $limit;
        $types .= 'i';
    }

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
        $row = officialsManagementDecryptRow($row);
        if ($q !== '' && !officialsManagementMatchesSearch($row, $q)) {
            continue;
        }

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
        if ($requestedMode === 'admin' && $audience !== 'admin') {
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
        $editAccessDisabledReason = '';
        if ($protectedCode === 'BARANGAY_CAPTAIN') {
            $editAccessDisabledReason = 'The Barangay Captain account is managed through official transitions.';
        } else {
            $editAccessDisabledReason = amp_get_superadmin_management_disabled_reason($conn, $actorUserId, $displayRole);
        }
        $canEditAccess = ($editAccessDisabledReason === '');
        $workflow = ogw_sync_profile_workflow($conn, (string)($row['official_id'] ?? ''));

        $rows[] = [
            'official_id' => (string)($row['official_id'] ?? ''),
            'user_id' => (string)($row['user_id'] ?? ''),
            'full_name' => $fullName !== '' ? $fullName : '—',
            'role_access' => $audience === 'personnel' ? 'Personnel' : ($audience === 'admin' ? 'Admin' : 'Official'),
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
            'profile_stage' => (string)($workflow['profile_stage'] ?? 'Draft'),
            'ready_for_invite' => !empty($workflow['ready_for_invite']),
            'onboarding_status' => (string)($workflow['onboarding_status'] ?? 'NotInvited'),
            'protected_code' => $protectedCode,
            'protected_label' => amp_get_protected_label($protectedCode),
            'module_count' => count($permissionMap),
            'module_summary' => officialsPermissionSummary($permissionMap),
            'permission_keys' => array_keys($permissionMap),
            'locked_permission_keys' => $protectedCode === 'IT_SUPERADMIN' ? amp_get_it_superadmin_locked_permission_keys() : [],
            'can_edit_access' => $canEditAccess,
            'edit_access_disabled_reason' => $editAccessDisabledReason,
        ];
    }
    $stmt->close();

    if ($q !== '') {
        $rows = array_slice($rows, 0, $limit);
    }

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
