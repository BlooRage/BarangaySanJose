<?php
declare(strict_types=1);

require_once __DIR__ . '/adminModulePermissions.php';
require_once __DIR__ . '/officialInviteCommon.php';
require_once __DIR__ . '/uniqueIDGenerate.php';
require_once __DIR__ . '/audit.php';

if (!function_exists('ogw_table_exists')) {
    function ogw_table_exists(mysqli $conn, string $table): bool
    {
        $tableEsc = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '{$tableEsc}'");
        return $res instanceof mysqli_result && $res->num_rows > 0;
    }
}

if (!function_exists('ogw_column_exists')) {
    function ogw_column_exists(mysqli $conn, string $table, string $column): bool
    {
        $tableEsc = $conn->real_escape_string($table);
        $columnEsc = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
        return $res instanceof mysqli_result && $res->num_rows > 0;
    }
}

if (!function_exists('ogw_ensure_schema')) {
    function ogw_ensure_schema(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        oi_ensure_invite_table($conn);
        amp_ensure_permission_storage($conn);

        $conn->query("
            CREATE TABLE IF NOT EXISTS officialprofileworkflowtbl (
                profile_workflow_id INT NOT NULL,
                official_id VARCHAR(20) NOT NULL,
                user_id VARCHAR(20) DEFAULT NULL,
                module_scope VARCHAR(40) NOT NULL DEFAULT 'Official',
                profile_stage VARCHAR(40) NOT NULL DEFAULT 'Draft',
                ready_for_invite TINYINT(1) NOT NULL DEFAULT 0,
                onboarding_status VARCHAR(40) NOT NULL DEFAULT 'NotInvited',
                access_review_status VARCHAR(40) NOT NULL DEFAULT 'PendingAccessApproval',
                last_invite_id INT DEFAULT NULL,
                last_invite_sent_at DATETIME DEFAULT NULL,
                last_reviewed_by_user_id VARCHAR(20) DEFAULT NULL,
                last_reviewed_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (profile_workflow_id),
                UNIQUE KEY uniq_profile_workflow_official (official_id),
                UNIQUE KEY uniq_profile_workflow_user (user_id),
                KEY idx_profile_workflow_stage (profile_stage),
                KEY idx_profile_workflow_scope (module_scope)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS officialgovernancetransitiontbl (
                transition_id VARCHAR(40) NOT NULL,
                council_id INT DEFAULT NULL,
                batch_label VARCHAR(120) DEFAULT NULL,
                transition_type VARCHAR(40) NOT NULL,
                seat_name VARCHAR(120) NOT NULL,
                department VARCHAR(160) DEFAULT NULL,
                area_number VARCHAR(50) DEFAULT NULL,
                outgoing_official_id VARCHAR(20) DEFAULT NULL,
                incoming_official_id VARCHAR(20) DEFAULT NULL,
                effective_date DATE DEFAULT NULL,
                acting_until_date DATE DEFAULT NULL,
                reason VARCHAR(255) DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                account_action VARCHAR(40) NOT NULL DEFAULT 'PendingReview',
                access_action VARCHAR(40) NOT NULL DEFAULT 'PendingAccessReview',
                status VARCHAR(40) NOT NULL DEFAULT 'PendingSuperAdminApproval',
                approved_by_user_id VARCHAR(20) DEFAULT NULL,
                approved_at DATETIME DEFAULT NULL,
                completed_at DATETIME DEFAULT NULL,
                created_by_user_id VARCHAR(20) DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (transition_id),
                KEY idx_governance_transition_status (status),
                KEY idx_governance_transition_batch (batch_label),
                KEY idx_governance_transition_council (council_id),
                KEY idx_governance_transition_outgoing (outgoing_official_id),
                KEY idx_governance_transition_incoming (incoming_official_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        if (!ogw_column_exists($conn, 'officialaccessprofiletbl', 'display_role')) {
            $conn->query("ALTER TABLE officialaccessprofiletbl ADD COLUMN display_role VARCHAR(40) DEFAULT NULL AFTER user_id");
        }
        if (!ogw_column_exists($conn, 'officialaccessprofiletbl', 'area_assignee_access')) {
            $conn->query("ALTER TABLE officialaccessprofiletbl ADD COLUMN area_assignee_access VARCHAR(50) DEFAULT NULL AFTER display_role");
        }
        if (!ogw_column_exists($conn, 'officialaccessprofiletbl', 'area_coverage_access')) {
            $conn->query("ALTER TABLE officialaccessprofiletbl ADD COLUMN area_coverage_access TEXT DEFAULT NULL AFTER area_assignee_access");
        }
        if (!ogw_column_exists($conn, 'officialaccessprofiletbl', 'access_status')) {
            $conn->query("ALTER TABLE officialaccessprofiletbl ADD COLUMN access_status VARCHAR(40) NOT NULL DEFAULT 'PendingAccessApproval' AFTER area_coverage_access");
        }
        if (!ogw_column_exists($conn, 'officialaccessprofiletbl', 'reviewed_by_user_id')) {
            $conn->query("ALTER TABLE officialaccessprofiletbl ADD COLUMN reviewed_by_user_id VARCHAR(20) DEFAULT NULL AFTER access_status");
        }
        if (!ogw_column_exists($conn, 'officialaccessprofiletbl', 'reviewed_at')) {
            $conn->query("ALTER TABLE officialaccessprofiletbl ADD COLUMN reviewed_at DATETIME DEFAULT NULL AFTER reviewed_by_user_id");
        }

        $conn->query("
            CREATE TABLE IF NOT EXISTS officialaccessrolepermissiontbl (
                role_permission_id INT NOT NULL,
                department_key VARCHAR(160) NOT NULL DEFAULT '',
                position_key VARCHAR(160) NOT NULL DEFAULT '',
                department_label VARCHAR(160) DEFAULT NULL,
                position_label VARCHAR(160) DEFAULT NULL,
                permission_key VARCHAR(120) NOT NULL,
                is_allowed TINYINT(1) NOT NULL DEFAULT 1,
                granted_by_user_id VARCHAR(20) DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (role_permission_id),
                UNIQUE KEY uniq_access_role_permission (department_key, position_key, permission_key),
                KEY idx_access_role_scope (department_key, position_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS officialaccessroleprofiletbl (
                role_access_profile_id INT NOT NULL,
                department_key VARCHAR(160) NOT NULL DEFAULT '',
                position_key VARCHAR(160) NOT NULL DEFAULT '',
                department_label VARCHAR(160) DEFAULT NULL,
                position_label VARCHAR(160) DEFAULT NULL,
                area_assignee_access VARCHAR(50) DEFAULT NULL,
                area_coverage_access TEXT DEFAULT NULL,
                permissions_initialized TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (role_access_profile_id),
                UNIQUE KEY uniq_access_role_profile (department_key, position_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        idg_ensure_numeric_generated_key($conn, 'officialprofileworkflowtbl', 'profile_workflow_id', 'INT NOT NULL');
        idg_ensure_numeric_generated_key($conn, 'officialaccessrolepermissiontbl', 'role_permission_id', 'INT NOT NULL');
        idg_ensure_numeric_generated_key($conn, 'officialaccessroleprofiletbl', 'role_access_profile_id', 'INT NOT NULL');
    }
}

if (!function_exists('ogw_generate_transition_id')) {
    function ogw_generate_transition_id(): string
    {
        return 'OGT-' . date('YmdHis') . '-' . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('ogw_normalize_scope')) {
    function ogw_normalize_scope(string $value): string
    {
        $value = str_replace(["\r", "\n", "\t"], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return strtolower(trim($value));
    }
}

if (!function_exists('ogw_fetch_status_id')) {
    function ogw_fetch_status_id(mysqli $conn, string $statusType, array $preferredNames): ?int
    {
        return amp_get_status_id_by_names($conn, $statusType, $preferredNames);
    }
}

if (!function_exists('ogw_management_scope_from_row')) {
    function ogw_management_scope_from_row(array $row): string
    {
        $position = strtolower(trim((string)($row['position_access'] ?? $row['role_access'] ?? '')));
        if (in_array($position, array_map('strtolower', amp_get_personnel_position_labels()), true) || strtolower(trim((string)($row['role_access'] ?? ''))) === 'personnel') {
            return 'Personnel';
        }
        if (strtolower(trim((string)($row['role_access'] ?? ''))) === 'superadmin') {
            return 'SuperAdmin';
        }
        return 'Official';
    }
}

if (!function_exists('ogw_profile_row_complete')) {
    function ogw_profile_row_complete(array $row): bool
    {
        $required = [
            trim((string)($row['firstname'] ?? '')),
            trim((string)($row['lastname'] ?? '')),
            trim((string)($row['contact_number'] ?? '')),
            trim((string)($row['email'] ?? '')),
            trim((string)($row['department'] ?? '')),
            trim((string)($row['position_access'] ?? $row['role_access'] ?? '')),
        ];
        foreach ($required as $value) {
            if ($value === '') {
                return false;
            }
        }

        $phone10 = oi_normalize_phone10((string)($row['contact_number'] ?? ''));
        if (!oi_is_valid_phone10($phone10)) {
            return false;
        }

        return filter_var((string)($row['email'] ?? ''), FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('ogw_sync_profile_workflow')) {
    function ogw_sync_profile_workflow(mysqli $conn, string $officialId): array
    {
        ogw_ensure_schema($conn);
        $stmt = $conn->prepare("
            SELECT oi.official_id, oi.user_id, oi.firstname, oi.lastname, oi.middlename, oi.suffix,
                   oi.contact_number, oi.email, oi.department, COALESCE(oi.position_access, oi.role_access) AS position_access,
                   ua.status_id_account,
                   iv.invite_id, iv.status AS invite_status
            FROM officialinformationtbl oi
            INNER JOIN useraccountstbl ua
                ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
            LEFT JOIN officialinvitetbl iv
                ON iv.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
               AND iv.invite_id = (
                    SELECT MAX(iv2.invite_id)
                    FROM officialinvitetbl iv2
                    WHERE iv2.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
               )
            WHERE oi.official_id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            throw new RuntimeException('Failed to load profile workflow source.');
        }
        $stmt->bind_param('s', $officialId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            throw new RuntimeException('Official record not found.');
        }
        $row = pii_decrypt_official_row($row) ?? $row;
        $row = pii_decrypt_useraccount_row($row) ?? $row;

        $moduleScope = ogw_management_scope_from_row($row);
        $isComplete = ogw_profile_row_complete($row);
        $inviteStatus = strtolower(trim((string)($row['invite_status'] ?? '')));
        $profileStage = 'Draft';
        $onboardingStatus = 'NotInvited';

        if ($inviteStatus !== '') {
            $onboardingStatus = ucfirst($inviteStatus);
            if ($inviteStatus === 'completed') {
                $profileStage = 'Active';
            } elseif (in_array($inviteStatus, ['pending', 'inprogress', 'pendingapproval', 'rejectedapproval'], true)) {
                $profileStage = 'Invite Sent';
            }
        } elseif ($isComplete) {
            $profileStage = 'Ready for Invite';
        }

        $existingStmt = $conn->prepare("SELECT profile_workflow_id, ready_for_invite FROM officialprofileworkflowtbl WHERE official_id = ? LIMIT 1");
        if (!$existingStmt) {
            throw new RuntimeException('Failed to load profile workflow.');
        }
        $existingStmt->bind_param('s', $officialId);
        $existingStmt->execute();
        $existing = $existingStmt->get_result()->fetch_assoc();
        $existingStmt->close();

        $readyForInvite = $isComplete ? 1 : 0;
        $lastInviteId = (int)($row['invite_id'] ?? 0);

        if ($existing) {
            $workflowId = (int)$existing['profile_workflow_id'];
            $up = $conn->prepare("
                UPDATE officialprofileworkflowtbl
                SET user_id = ?,
                    module_scope = ?,
                    profile_stage = ?,
                    ready_for_invite = ?,
                    onboarding_status = ?,
                    access_review_status = CASE
                        WHEN profile_stage = 'Active' THEN 'Access Granted'
                        ELSE access_review_status
                    END,
                    last_invite_id = NULLIF(?, 0),
                    last_invite_sent_at = CASE WHEN ? > 0 THEN NOW() ELSE last_invite_sent_at END,
                    updated_at = NOW()
                WHERE profile_workflow_id = ?
                LIMIT 1
            ");
            if ($up) {
                $userId = (string)($row['user_id'] ?? '');
                $up->bind_param('sssisiii', $userId, $moduleScope, $profileStage, $readyForInvite, $onboardingStatus, $lastInviteId, $lastInviteId, $workflowId);
                $up->execute();
                $up->close();
            }
        } else {
            $workflowId = GenerateTenDigitMetaID($conn, 'officialprofileworkflowtbl', 'profile_workflow_id');
            if ($workflowId === false) {
                throw new RuntimeException('Failed to generate profile workflow ID.');
            }
            $ins = $conn->prepare("
                INSERT INTO officialprofileworkflowtbl
                    (profile_workflow_id, official_id, user_id, module_scope, profile_stage, ready_for_invite, onboarding_status, access_review_status, last_invite_id, last_invite_sent_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'PendingAccessApproval', NULLIF(?, 0), CASE WHEN ? > 0 THEN NOW() ELSE NULL END)
            ");
            if ($ins) {
                $userId = (string)($row['user_id'] ?? '');
                $workflowIdInt = (int)$workflowId;
                $ins->bind_param('issssisii', $workflowIdInt, $officialId, $userId, $moduleScope, $profileStage, $readyForInvite, $onboardingStatus, $lastInviteId, $lastInviteId);
                $ins->execute();
                $ins->close();
            }
        }

        return [
            'module_scope' => $moduleScope,
            'profile_stage' => $profileStage,
            'ready_for_invite' => $readyForInvite === 1,
            'onboarding_status' => $onboardingStatus,
            'last_invite_id' => $lastInviteId,
        ];
    }
}

if (!function_exists('ogw_prepare_invite_delivery')) {
    function ogw_prepare_invite_delivery(mysqli $conn, array $row, string $actorUserId): array
    {
        ogw_ensure_schema($conn);
        $phone10 = oi_normalize_phone10((string)($row['contact_number'] ?? ''));
        $email = strtolower(trim((string)($row['email'] ?? '')));
        if (!oi_is_valid_phone10($phone10) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Official contact details are incomplete for onboarding invite sending.');
        }

        $roleAccess = trim((string)($row['role_access'] ?? 'Official'));
        if ($roleAccess === '') {
            $roleAccess = 'Official';
        }
        $positionAccess = trim((string)($row['position_access'] ?? $row['role_access'] ?? ''));
        $department = trim((string)($row['department'] ?? ''));
        $areaNumber = trim((string)($row['area_number'] ?? 'Barangay Wide'));

        $token = oi_generate_invite_token();
        $inviteCode = oi_generate_invite_code($conn, $areaNumber);
        $expiresAt = (new DateTimeImmutable('+48 hours'))->format('Y-m-d H:i:s');
        $inviteEmailData = pii_prepare_official_invite_contacts($email, $phone10);

        $stmt = $conn->prepare("
            INSERT INTO officialinvitetbl
                (invite_code, invite_token_hash, invite_email, invite_email_lookup_hash, invite_phone, invite_phone_lookup_hash,
                 firstname, middlename, lastname, suffix, role_access, position_access, department, employment_status, area_number,
                 status, onboarding_step, invited_by_user_id, expires_at, user_id)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, NULLIF(?, ''), ?, NULLIF(?, ''), ?, NULL, NULLIF(?, ''), 'Pending', 'password', ?, ?, ?)
        ");
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare onboarding invite.');
        }
        oi_bind_string_params($stmt, [
            $inviteCode,
            $token['hash'],
            $inviteEmailData['invite_email'],
            $inviteEmailData['invite_email_lookup_hash'],
            $inviteEmailData['invite_phone'],
            $inviteEmailData['invite_phone_lookup_hash'],
            $row['firstname'],
            $row['middlename'],
            $row['lastname'],
            $row['suffix'],
            $roleAccess,
            $positionAccess,
            $department,
            $areaNumber,
            $actorUserId,
            $expiresAt,
            $row['user_id']
        ]);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Failed to store onboarding invite. ' . $error);
        }
        $inviteId = (int)$stmt->insert_id;
        $stmt->close();

        $inviteLink = appUrl('/official-onboarding?invite=' . urlencode($token['raw']));
        $emailSent = false;
        $smsSent = false;
        $deliveryNotes = [];

        try {
            require_once __DIR__ . '/../EmailHandlers/emailSender.php';
            if (class_exists('EmailSender')) {
                $mailConfig = require __DIR__ . '/mailConfigurations.php';
                $sender = new EmailSender($mailConfig);
                $emailSent = $sender->send([
                    'type' => 'onboarding_access',
                    'to' => $email,
                    'subject' => 'Barangay San Jose Official Account Invite',
                    'data' => [
                        'headline' => 'Official Account Onboarding Access',
                        'recipientName' => trim((string)($row['firstname'] ?? '') . ' ' . (string)($row['lastname'] ?? '')),
                        'roleName' => $positionAccess !== '' ? $positionAccess : $roleAccess,
                        'actionUrl' => $inviteLink,
                        'buttonText' => 'START ONBOARDING',
                        'expiresNote' => 'This invite link expires in 48 hours.',
                    ],
                    'bodyText' => "Use this secure link to start your Barangay San Jose onboarding: {$inviteLink}",
                ]);
            }
        } catch (Throwable $e) {
            $deliveryNotes[] = 'Email: ' . $e->getMessage();
        }

        try {
            require_once __DIR__ . '/sendSMS.php';
            if (function_exists('sendSMS')) {
                $smsSent = (bool)sendSMS('0' . $phone10, 'Barangay San Jose: Your official onboarding invite is ready. Please check your email for the secure access link.');
            }
        } catch (Throwable $e) {
            $deliveryNotes[] = 'SMS: ' . $e->getMessage();
        }

        return [
            'invite_id' => $inviteId,
            'invite_link' => $inviteLink,
            'delivery' => [
                'email_sent' => $emailSent,
                'sms_sent' => $smsSent,
                'notes' => implode(' | ', $deliveryNotes),
            ],
        ];
    }
}

if (!function_exists('ogw_send_official_invite')) {
    function ogw_send_official_invite(mysqli $conn, string $officialId, string $actorUserId): array
    {
        ogw_ensure_schema($conn);
        $stmt = $conn->prepare("
            SELECT oi.official_id, oi.user_id, oi.firstname, oi.lastname, oi.middlename, oi.suffix,
                   oi.contact_number, oi.email, oi.department, oi.area_number, oi.role_access,
                   COALESCE(oi.position_access, oi.role_access) AS position_access
            FROM officialinformationtbl oi
            WHERE oi.official_id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            throw new RuntimeException('Failed to load official record.');
        }
        $stmt->bind_param('s', $officialId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            throw new RuntimeException('Official record not found.');
        }
        $row = pii_decrypt_official_row($row) ?? $row;

        $workflow = ogw_sync_profile_workflow($conn, $officialId);
        if (empty($workflow['ready_for_invite'])) {
            throw new RuntimeException('Complete the official profile first before sending the invite.');
        }

        $result = ogw_prepare_invite_delivery($conn, $row, $actorUserId);
        ogw_sync_profile_workflow($conn, $officialId);

        return $result;
    }
}

if (!function_exists('ogw_send_all_ready_invites')) {
    function ogw_send_all_ready_invites(mysqli $conn, string $actorUserId, string $scope = ''): array
    {
        ogw_ensure_schema($conn);
        $rows = [];
        $stmt = $conn->prepare("
            SELECT official_id
            FROM officialinformationtbl
            ORDER BY official_id ASC
        ");
        if (!$stmt) {
            throw new RuntimeException('Failed to load official list.');
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = (string)($row['official_id'] ?? '');
        }
        $stmt->close();

        $sent = [];
        $skipped = [];
        foreach ($rows as $officialId) {
            if ($officialId === '') {
                continue;
            }
            try {
                $workflow = ogw_sync_profile_workflow($conn, $officialId);
                if ($scope !== '' && strcasecmp((string)($workflow['module_scope'] ?? ''), $scope) !== 0) {
                    continue;
                }
                if (empty($workflow['ready_for_invite'])) {
                    $skipped[] = ['official_id' => $officialId, 'reason' => 'Profile not ready for invite.'];
                    continue;
                }
                $result = ogw_send_official_invite($conn, $officialId, $actorUserId);
                $sent[] = ['official_id' => $officialId, 'invite_id' => (int)($result['invite_id'] ?? 0)];
            } catch (Throwable $e) {
                $skipped[] = ['official_id' => $officialId, 'reason' => $e->getMessage()];
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }
}

if (!function_exists('ogw_verify_actor_password')) {
    function ogw_verify_actor_password(mysqli $conn, string $actorUserId, string $actorPassword): void
    {
        if ($actorUserId === '' || trim($actorPassword) === '') {
            throw new RuntimeException('Password confirmation is required.');
        }
        $stmt = $conn->prepare("SELECT password_hash FROM useraccountstbl WHERE user_id = ? LIMIT 1");
        if (!$stmt) {
            throw new RuntimeException('Unable to verify your password right now.');
        }
        $stmt->bind_param('s', $actorUserId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $hash = (string)($row['password_hash'] ?? '');
        if ($hash === '' || !password_verify($actorPassword, $hash)) {
            throw new RuntimeException('Authorization failed: incorrect current password.');
        }
    }
}

if (!function_exists('ogw_mask_email')) {
    function ogw_mask_email(string $email): string
    {
        $email = strtolower(trim($email));
        if ($email === '' || strpos($email, '@') === false) {
            return '';
        }
        [$local, $domain] = explode('@', $email, 2);
        $localMasked = strlen($local) <= 2
            ? substr($local, 0, 1) . str_repeat('*', max(0, strlen($local) - 1))
            : substr($local, 0, 2) . str_repeat('*', max(0, strlen($local) - 2));
        return $localMasked . '@' . $domain;
    }
}

if (!function_exists('ogw_mask_phone')) {
    function ogw_mask_phone(string $phone10): string
    {
        $digits = oi_normalize_phone10($phone10);
        if (!oi_is_valid_phone10($digits)) {
            return '';
        }
        return '0' . substr($digits, 0, 2) . '***' . substr($digits, -3);
    }
}

if (!function_exists('ogw_load_actor_delivery_contacts')) {
    function ogw_load_actor_delivery_contacts(mysqli $conn, string $actorUserId): array
    {
        if ($actorUserId === '') {
            throw new RuntimeException('SuperAdmin session is missing.');
        }

        $stmt = $conn->prepare("
            SELECT ua.user_id,
                   ua.email,
                   ua.phone_number,
                   oi.firstname,
                   oi.lastname,
                   oi.contact_number AS official_contact_number,
                   oi.email AS official_email
            FROM useraccountstbl ua
            LEFT JOIN officialinformationtbl oi
                ON oi.user_id COLLATE utf8mb4_general_ci = ua.user_id COLLATE utf8mb4_general_ci
            WHERE ua.user_id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to load SuperAdmin contact details.');
        }
        $stmt->bind_param('s', $actorUserId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            throw new RuntimeException('SuperAdmin account not found.');
        }

        $row = pii_decrypt_official_row($row) ?? $row;
        $row = pii_decrypt_useraccount_row($row) ?? $row;

        $email = strtolower(trim((string)($row['official_email'] ?? '')));
        if ($email === '') {
            $email = strtolower(trim((string)($row['email'] ?? '')));
        }

        $phone10 = oi_normalize_phone10((string)($row['official_contact_number'] ?? ''));
        if (!oi_is_valid_phone10($phone10)) {
            $phone10 = oi_normalize_phone10((string)($row['phone_number'] ?? ''));
        }

        $fullName = trim(implode(' ', array_filter([
            trim((string)($row['firstname'] ?? '')),
            trim((string)($row['lastname'] ?? '')),
        ], static fn ($value): bool => $value !== '')));
        if ($fullName === '') {
            $fullName = 'SuperAdmin';
        }

        return [
            'full_name' => $fullName,
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '',
            'phone10' => oi_is_valid_phone10($phone10) ? $phone10 : '',
        ];
    }
}

if (!function_exists('ogw_cleanup_secure_action_challenges')) {
    function ogw_cleanup_secure_action_challenges(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $bucket = $_SESSION['ogw_secure_action_challenges'] ?? null;
        if (!is_array($bucket)) {
            $_SESSION['ogw_secure_action_challenges'] = [];
            return;
        }
        $now = time();
        foreach ($bucket as $key => $challenge) {
            $expiresAt = (int)($challenge['expires_at'] ?? 0);
            if ($expiresAt > 0 && $expiresAt < $now) {
                unset($bucket[$key]);
            }
        }
        $_SESSION['ogw_secure_action_challenges'] = $bucket;
    }
}

if (!function_exists('ogw_issue_secure_action_otp')) {
    function ogw_issue_secure_action_otp(
        mysqli $conn,
        string $actorUserId,
        string $actorPassword,
        string $moduleKey,
        string $actionKey,
        string $targetLabel = ''
    ): array {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Session is required for secure confirmation.');
        }

        ogw_verify_actor_password($conn, $actorUserId, $actorPassword);
        ogw_cleanup_secure_action_challenges();

        $contacts = ogw_load_actor_delivery_contacts($conn, $actorUserId);
        $email = (string)($contacts['email'] ?? '');
        $phone10 = (string)($contacts['phone10'] ?? '');
        $isLocalPreviewAllowed = function_exists('db_is_localhost_request') && db_is_localhost_request();

        $challengeKey = bin2hex(random_bytes(16));
        $purpose = 'ogw-sec-' . substr(hash('sha256', $moduleKey . '|' . $actionKey . '|' . $challengeKey), 0, 24);
        $primaryRecipient = $phone10 !== '' ? $phone10 : $email;
        $otpCode = oi_generate_otp();
        $expiryTime = oi_insert_otp($conn, $actorUserId, $primaryRecipient, $purpose, $otpCode, 5);

        $actionLabel = trim($targetLabel) !== '' ? $targetLabel : ucwords(str_replace(['_', '-'], ' ', $actionKey));
        $subject = 'Barangay San Jose secure action OTP';
        $bodyText = "A secure action was requested for {$actionLabel}. Your OTP code is {$otpCode}. It expires in 5 minutes.";

        $emailSent = false;
        $smsSent = false;
        $deliveryNotes = [];
        $usedPreviewFallback = false;

        if ($email === '' && $phone10 === '') {
            if (!$isLocalPreviewAllowed) {
                throw new RuntimeException('No deliverable SuperAdmin email or mobile number is configured for OTP confirmation.');
            }
            $deliveryNotes[] = 'Local preview fallback used because no deliverable SuperAdmin email or mobile number is configured.';
        }

        if ($email !== '') {
            try {
                require_once __DIR__ . '/../EmailHandlers/emailSender.php';
                if (class_exists('EmailSender')) {
                    $mailConfig = require __DIR__ . '/mailConfigurations.php';
                    $sender = new EmailSender($mailConfig);
                    $emailSent = $sender->send([
                        'to' => $email,
                        'subject' => $subject,
                        'bodyText' => $bodyText,
                        'bodyHtml' => '<p>' . htmlspecialchars($bodyText, ENT_QUOTES, 'UTF-8') . '</p>',
                    ]);
                    if (!$emailSent && method_exists($sender, 'getLastError')) {
                        $deliveryNotes[] = 'Email: ' . trim((string)$sender->getLastError());
                    }
                }
            } catch (Throwable $e) {
                $deliveryNotes[] = 'Email: ' . $e->getMessage();
            }
        }

        if ($phone10 !== '') {
            try {
                require_once __DIR__ . '/sendSMS.php';
                if (function_exists('sendSMS')) {
                    $smsSent = (bool)sendSMS('0' . $phone10, "Barangay San Jose secure action OTP: {$otpCode}. Expires in 5 minutes.", $otpCode);
                    if (!$smsSent && function_exists('getLastSmsError')) {
                        $lastSmsError = trim((string)getLastSmsError());
                        if ($lastSmsError !== '') {
                            $deliveryNotes[] = 'SMS: ' . $lastSmsError;
                        }
                    }
                }
            } catch (Throwable $e) {
                $deliveryNotes[] = 'SMS: ' . $e->getMessage();
            }
        }

        if (!$emailSent && !$smsSent) {
            if (!$isLocalPreviewAllowed) {
                throw new RuntimeException('OTP could not be delivered. ' . trim(implode(' | ', array_filter($deliveryNotes))));
            }
            $usedPreviewFallback = true;
            if ($deliveryNotes === []) {
                $deliveryNotes[] = 'Local preview fallback used because no delivery channel succeeded.';
            }
        }

        $_SESSION['ogw_secure_action_challenges'][$challengeKey] = [
            'actor_user_id' => $actorUserId,
            'module_key' => $moduleKey,
            'action_key' => $actionKey,
            'purpose' => $purpose,
            'recipient' => $primaryRecipient,
            'expires_at' => strtotime((string)$expiryTime),
            'issued_at' => time(),
            'target_label' => $actionLabel,
        ];

        $deliveryTargets = [];
        if ($smsSent && $phone10 !== '') {
            $deliveryTargets[] = 'mobile ' . ogw_mask_phone($phone10);
        }
        if ($emailSent && $email !== '') {
            $deliveryTargets[] = 'email ' . ogw_mask_email($email);
        }
        if ($usedPreviewFallback) {
            $deliveryTargets[] = 'local OTP preview';
        }

        return [
            'challenge_key' => $challengeKey,
            'expires_at' => $expiryTime,
            'delivery_label' => implode(' and ', $deliveryTargets),
            'otp_preview' => $usedPreviewFallback ? $otpCode : '',
            'delivery_warning' => $usedPreviewFallback ? trim(implode(' | ', array_filter($deliveryNotes))) : '',
        ];
    }
}

if (!function_exists('ogw_consume_secure_action_otp')) {
    function ogw_consume_secure_action_otp(
        mysqli $conn,
        string $actorUserId,
        string $challengeKey,
        string $otpCode,
        string $moduleKey,
        string $actionKey
    ): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Session is required for secure confirmation.');
        }

        ogw_cleanup_secure_action_challenges();
        $challengeKey = trim($challengeKey);
        $otpCode = trim($otpCode);
        $bucket = $_SESSION['ogw_secure_action_challenges'] ?? [];
        $challenge = is_array($bucket) ? ($bucket[$challengeKey] ?? null) : null;
        if (!is_array($challenge)) {
            throw new RuntimeException('OTP confirmation expired. Request a new confirmation code first.');
        }

        if (
            (string)($challenge['actor_user_id'] ?? '') !== $actorUserId ||
            (string)($challenge['module_key'] ?? '') !== $moduleKey ||
            (string)($challenge['action_key'] ?? '') !== $actionKey
        ) {
            unset($_SESSION['ogw_secure_action_challenges'][$challengeKey]);
            throw new RuntimeException('OTP confirmation does not match this action.');
        }

        oi_verify_latest_otp(
            $conn,
            $actorUserId,
            (string)($challenge['recipient'] ?? ''),
            (string)($challenge['purpose'] ?? ''),
            $otpCode
        );

        unset($_SESSION['ogw_secure_action_challenges'][$challengeKey]);
    }
}
