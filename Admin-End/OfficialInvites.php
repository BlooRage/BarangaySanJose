<?php
require_once __DIR__ . "/../PhpFiles/General/security.php";
require_once __DIR__ . "/../PhpFiles/General/connection.php";
require_once __DIR__ . "/../PhpFiles/General/officialInviteCommon.php";
require_once __DIR__ . "/../PhpFiles/General/adminModulePermissions.php";
require_once __DIR__ . "/../PhpFiles/General/sendSMS.php";
require_once __DIR__ . "/../PhpFiles/EmailHandlers/emailSender.php";
require_once __DIR__ . "/../PhpFiles/General/mailConfigurations.php";
require_once __DIR__ . "/../PhpFiles/General/audit.php";

requireRoleSession(['SuperAdmin'], false);
oi_ensure_invite_table($conn);
amp_ensure_permission_storage($conn);

$flash = ['type' => '', 'message' => ''];
$flashOrigin = '';
$csrfToken = ensureCsrfToken();
if (!empty($_SESSION['official_invite_flash']) && is_array($_SESSION['official_invite_flash'])) {
    $flash = $_SESSION['official_invite_flash'];
    unset($_SESSION['official_invite_flash']);
}
if (!empty($_SESSION['official_invite_flash_origin'])) {
    $flashOrigin = (string)$_SESSION['official_invite_flash_origin'];
    unset($_SESSION['official_invite_flash_origin']);
}

$departmentOptions = [
    'Office of the Barangay',
    'Barangay Certificate Issuance',
    'Baranagay Monitoring',
    'Barangay Treasurers Office',
    'Barangay Peace and Order',
];
$positionAccessOptions = [
    'IT Administrator',
    'Barangay Official',
    'Barangay Secretary',
    'Department Public Assistance Desk',
    'Department Secretary',
    'Department OIC (Officer In Charge)',
    'Barangay Police',
    'Desk Officer',
    'Area OIC',
    'Barangay Treasurer',
];
$positionsByRole = [
    'SuperAdmin' => ['IT Administrator'],
    'Official' => ['Barangay Official', 'Barangay Secretary'],
    'Personnel' => [
        'Department Public Assistance Desk',
        'Department Secretary',
        'Department OIC (Officer In Charge)',
        'Barangay Police',
        'Desk Officer',
        'Area OIC',
        'Barangay Treasurer',
    ],
];
$areaRequiredPositions = ['Barangay Secretary', 'Barangay Police', 'Desk Officer', 'Area OIC'];
$deptRes = $conn->query("
    SELECT DISTINCT department
    FROM officialinformationtbl
    WHERE department IS NOT NULL AND TRIM(department) <> ''
    ORDER BY department ASC
");
if ($deptRes) {
    while ($d = $deptRes->fetch_assoc()) {
        $val = trim((string)($d['department'] ?? ''));
        if ($val !== '' && !in_array($val, $departmentOptions, true)) {
            $departmentOptions[] = $val;
        }
    }
}
sort($departmentOptions);
$areaNumberOptions = [
    'Barangay Wide',
    'Area 01',
    'Area 1A',
    'Area 02',
    'Area 03',
    'Area 04',
    'Area 05',
    'Area 06',
];
$areaRes = $conn->query("
    SELECT DISTINCT area_number
    FROM residentaddresstbl
    WHERE area_number IS NOT NULL AND TRIM(area_number) <> ''
    ORDER BY area_number ASC
");
if ($areaRes) {
    while ($a = $areaRes->fetch_assoc()) {
        $val = trim((string)($a['area_number'] ?? ''));
        if ($val !== '' && !in_array($val, $areaNumberOptions, true)) {
            $areaNumberOptions[] = $val;
        }
    }
}

function set_invite_flash(string $type, string $message): void {
    $_SESSION['official_invite_flash'] = ['type' => $type, 'message' => $message];
}

function redirect_self(): void {
    header("Location: " . appUrl('/Admin-End/OfficialInvites.php'));
    exit;
}

function fetch_invite_by_id(mysqli $conn, int $inviteId): ?array {
    $stmt = $conn->prepare("SELECT * FROM officialinvitetbl WHERE invite_id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param("i", $inviteId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (pii_decrypt_official_invite_row($row) ?? $row) : null;
}

function oi_invite_json_response(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function oi_access_label_valid(string $value, int $maxLength = 100): bool {
    $value = trim($value);
    if ($value === '' || strlen($value) > $maxLength) {
        return false;
    }
    return (bool)preg_match("/^[A-Za-z0-9][A-Za-z0-9 .,'()&\\/-]*$/", $value);
}

function oi_permission_labels_from_keys(array $permissionKeys): array {
    $labels = [];
    foreach ($permissionKeys as $permissionKey) {
        $permissionKey = trim((string)$permissionKey);
        if ($permissionKey === '') {
            continue;
        }

        $meta = amp_get_permission_meta($permissionKey);
        if (!$meta) {
            continue;
        }

        $parentLabel = trim((string)($meta['parent_label'] ?? ''));
        $childLabel = trim((string)($meta['label'] ?? $permissionKey));
        $label = $parentLabel !== '' ? $parentLabel . ' - ' . $childLabel : $childLabel;
        if ($label !== '') {
            $labels[] = $label;
        }
    }

    $labels = array_values(array_unique($labels));
    usort($labels, static fn (string $a, string $b): int => strcasecmp($a, $b));
    return $labels;
}

function oi_permission_summary_from_keys(array $permissionKeys, int $maxLabels = 4): string {
    $labels = oi_permission_labels_from_keys($permissionKeys);
    if (!$labels) {
        return 'No modules';
    }

    $visible = array_slice($labels, 0, $maxLabels);
    $summary = implode(', ', $visible);
    if (count($labels) > $maxLabels) {
        $summary .= ' +' . (count($labels) - $maxLabels);
    }
    return $summary;
}

function oi_normalize_permission_keys(array $permissionKeys): array {
    $validPermissionKeys = array_fill_keys(amp_get_all_leaf_permission_keys(), true);
    $normalized = [];
    foreach ($permissionKeys as $permissionKey) {
        $permissionKey = trim((string)$permissionKey);
        if ($permissionKey !== '' && isset($validPermissionKeys[$permissionKey])) {
            $normalized[$permissionKey] = true;
        }
    }
    return array_keys($normalized);
}

function oi_fetch_personnel_access_profiles(mysqli $conn): array {
    amp_ensure_permission_storage($conn);

    $profiles = [];
    $profileRes = $conn->query("
        SELECT department_key, position_key, department_label, position_label, updated_at
        FROM officialaccessroleprofiletbl
        WHERE permissions_initialized = 1
        ORDER BY COALESCE(NULLIF(department_label, ''), department_key) ASC,
                 COALESCE(NULLIF(position_label, ''), position_key) ASC
    ");
    if ($profileRes instanceof mysqli_result) {
        while ($row = $profileRes->fetch_assoc()) {
            $departmentKey = trim((string)($row['department_key'] ?? ''));
            $positionKey = trim((string)($row['position_key'] ?? ''));
            if ($departmentKey === '' || $positionKey === '') {
                continue;
            }

            $profileId = $departmentKey . '||' . $positionKey;
            $department = trim((string)($row['department_label'] ?? ''));
            $position = trim((string)($row['position_label'] ?? ''));
            $profiles[$profileId] = [
                'profile_id' => $profileId,
                'department_key' => $departmentKey,
                'position_key' => $positionKey,
                'department' => $department !== '' ? $department : $departmentKey,
                'position_access' => $position !== '' ? $position : $positionKey,
                'permission_keys' => [],
                'permission_labels' => [],
                'permission_count' => 0,
                'permission_summary' => 'No modules',
                'updated_at' => (string)($row['updated_at'] ?? ''),
            ];
        }
        $profileRes->free();
    }

    if (!$profiles) {
        return [];
    }

    $validPermissionKeys = array_fill_keys(amp_get_all_leaf_permission_keys(), true);
    $permissionRes = $conn->query("
        SELECT department_key, position_key, permission_key
        FROM officialaccessrolepermissiontbl
        WHERE is_allowed = 1
        ORDER BY permission_key ASC
    ");
    if ($permissionRes instanceof mysqli_result) {
        while ($row = $permissionRes->fetch_assoc()) {
            $profileId = trim((string)($row['department_key'] ?? '')) . '||' . trim((string)($row['position_key'] ?? ''));
            $permissionKey = trim((string)($row['permission_key'] ?? ''));
            if ($permissionKey !== '' && isset($validPermissionKeys[$permissionKey]) && isset($profiles[$profileId])) {
                $profiles[$profileId]['permission_keys'][$permissionKey] = true;
            }
        }
        $permissionRes->free();
    }

    foreach ($profiles as &$profile) {
        $permissionKeys = array_keys($profile['permission_keys']);
        $profile['permission_keys'] = $permissionKeys;
        $profile['permission_labels'] = oi_permission_labels_from_keys($permissionKeys);
        $profile['permission_count'] = count($permissionKeys);
        $profile['permission_summary'] = oi_permission_summary_from_keys($permissionKeys);
    }
    unset($profile);

    $rows = array_values($profiles);
    usort($rows, static function (array $a, array $b): int {
        $departmentCompare = strcasecmp((string)($a['department'] ?? ''), (string)($b['department'] ?? ''));
        if ($departmentCompare !== 0) {
            return $departmentCompare;
        }
        return strcasecmp((string)($a['position_access'] ?? ''), (string)($b['position_access'] ?? ''));
    });

    return $rows;
}

function oi_find_personnel_access_profile(mysqli $conn, string $department, string $positionAccess): ?array {
    $scope = amp_get_personnel_role_profile_scope($department, $positionAccess);
    $targetId = $scope['department_key'] . '||' . $scope['position_key'];
    foreach (oi_fetch_personnel_access_profiles($conn) as $profile) {
        if (($profile['profile_id'] ?? '') === $targetId) {
            return $profile;
        }

        $profileScope = amp_get_personnel_role_profile_scope(
            (string)($profile['department'] ?? ''),
            (string)($profile['position_access'] ?? '')
        );
        if ($profileScope['department_key'] === $scope['department_key'] && $profileScope['position_key'] === $scope['position_key']) {
            return $profile;
        }
    }
    return null;
}

function verify_actor_password_or_fail(mysqli $conn, string $actorUserId, bool $isSuperAdminActor, string $actorPassword): void {
    if (trim($actorUserId) === '') {
        set_invite_flash('danger', 'Session expired. Please login again.');
        redirect_self();
    }
    if (trim($actorPassword) === '') {
        set_invite_flash('danger', 'Please enter your current password to authorize this action.');
        redirect_self();
    }
    $pwdStmt = $conn->prepare("SELECT password_hash FROM useraccountstbl WHERE user_id = ? LIMIT 1");
    if (!$pwdStmt) {
        set_invite_flash('danger', 'Unable to verify your password right now.');
        redirect_self();
    }
    $pwdStmt->bind_param("s", $actorUserId);
    $pwdStmt->execute();
    $pwdRow = $pwdStmt->get_result()->fetch_assoc();
    $pwdStmt->close();
    $actorHash = (string)($pwdRow['password_hash'] ?? '');
    if ($actorHash === '' || !password_verify($actorPassword, $actorHash)) {
        set_invite_flash('danger', 'Authorization failed: incorrect current password.');
        redirect_self();
    }
}

function verify_sender_password_for_invite_or_fail(mysqli $conn, string $actorUserId, string $actorPassword): void {
    if (trim($actorUserId) === '') {
        set_invite_flash('danger', 'Session expired. Please login again.');
        redirect_self();
    }
    if (trim($actorPassword) === '') {
        set_invite_flash('danger', 'Please enter your current password to confirm invite sending.');
        redirect_self();
    }
    $pwdStmt = $conn->prepare("SELECT password_hash FROM useraccountstbl WHERE user_id = ? LIMIT 1");
    if (!$pwdStmt) {
        set_invite_flash('danger', 'Unable to verify your password right now.');
        redirect_self();
    }
    $pwdStmt->bind_param("s", $actorUserId);
    $pwdStmt->execute();
    $pwdRow = $pwdStmt->get_result()->fetch_assoc();
    $pwdStmt->close();
    $actorHash = (string)($pwdRow['password_hash'] ?? '');
    if ($actorHash === '' || !password_verify($actorPassword, $actorHash)) {
        set_invite_flash('danger', 'Authorization failed: incorrect current password.');
        redirect_self();
    }
}

function send_invite_email(array $invite, string $rawToken): bool {
    $smtpConfig = require __DIR__ . "/../PhpFiles/General/mailConfigurations.php";
    $sender = new EmailSender($smtpConfig);
    $baseUrl = oi_app_base_url();
    $link = appBaseUrl() . appUrl('/official-onboarding?invite=' . urlencode($rawToken));
    $fullName = trim(
        (string)($invite['firstname'] ?? '')
        . ' '
        . (string)($invite['middlename'] ?? '')
        . ' '
        . (string)($invite['lastname'] ?? '')
    );
    $role = (string)($invite['role_access'] ?? 'Official');

    return $sender->send([
        'type' => 'onboarding_access',
        'to' => (string)$invite['invite_email'],
        'subject' => 'Barangay San Jose Account Invite',
        'data' => [
            'headline' => 'Official Account Onboarding Access',
            'recipientName' => $fullName !== '' ? $fullName : 'Official',
            'roleName' => $role,
            'actionUrl' => $link,
            'buttonText' => 'START ONBOARDING',
            'expiresNote' => 'This invite link expires in 48 hours.',
        ],
        'bodyText' => "You were invited to onboard your Barangay San Jose account as {$role}.\nSTRICTLY ONE-TIME ACCESS.\nOpen: {$link}",
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    $actorUserId = (string)($_SESSION['user_id'] ?? '');
    $actorRole = (string)($_SESSION['role'] ?? 'SuperAdmin');
    $isSuperAdminActor = in_array($actorRole, ['SuperAdmin'], true);

    if ($action === 'list_personnel_access_profiles') {
        verifyCsrfToken(true);
        oi_invite_json_response([
            'success' => true,
            'profiles' => oi_fetch_personnel_access_profiles($conn),
        ]);
    }

    if ($action === 'create_personnel_access_profile') {
        verifyCsrfToken(true);

        $department = trim((string)($_POST['department'] ?? ''));
        $positionAccess = trim((string)($_POST['position_access'] ?? ''));
        $requestedPermissionKeys = $_POST['permission_keys'] ?? [];
        if (!is_array($requestedPermissionKeys)) {
            $requestedPermissionKeys = [];
        }
        $permissionKeys = oi_normalize_permission_keys($requestedPermissionKeys);

        if (!oi_access_label_valid($department) || !oi_access_label_valid($positionAccess)) {
            oi_invite_json_response([
                'success' => false,
                'message' => 'Enter a valid department and title. Use letters, numbers, spaces, and common punctuation only.',
            ], 422);
        }

        if (!$permissionKeys) {
            oi_invite_json_response([
                'success' => false,
                'message' => 'Select at least one module or tracker for this title.',
            ], 422);
        }

        if (oi_find_personnel_access_profile($conn, $department, $positionAccess)) {
            oi_invite_json_response([
                'success' => false,
                'message' => 'This department and title already has an access profile. Select it from the dropdown instead.',
            ], 409);
        }

        $conn->begin_transaction();
        try {
            amp_replace_personnel_role_module_permissions($conn, $department, $positionAccess, $permissionKeys, $actorUserId);
            amp_upsert_personnel_role_access_profile($conn, $department, $positionAccess);
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            oi_invite_json_response([
                'success' => false,
                'message' => 'Unable to create the access profile right now.',
            ], 500);
        }

        insertUnifiedAuditLog(
            $conn,
            $actorUserId,
            $actorRole,
            'Access Control',
            'PersonnelRoleAccessProfile',
            $department . ' :: ' . $positionAccess,
            'PERSONNEL_ROLE_ACCESS_CREATE_FROM_INVITE',
            'module_permissions',
            null,
            oi_permission_summary_from_keys($permissionKeys),
            'Created personnel access profile from Account Invite.',
            null
        );

        oi_invite_json_response([
            'success' => true,
            'message' => 'Access profile created. You can now use this title for the invite.',
            'profile' => oi_find_personnel_access_profile($conn, $department, $positionAccess),
            'profiles' => oi_fetch_personnel_access_profiles($conn),
        ]);
    }

    if ($action === 'precheck_invite') {
        verifyCsrfToken(true);
        header('Content-Type: application/json');
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $phone10 = oi_normalize_phone10((string)($_POST['phone_number'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !oi_is_valid_phone10($phone10)) {
            echo json_encode([
                'ok' => false,
                'exists' => false,
                'message' => 'Please provide a valid email and phone number before sending invite.'
            ]);
            exit;
        }

        $contactLookup = pii_prepare_useraccount_contacts($email, $phone10);
        $exists = $conn->prepare("
            SELECT user_id
            FROM useraccountstbl
            WHERE email_lookup_hash = ? OR phone_lookup_hash = ?
            LIMIT 1
        ");
        $hasExisting = false;
        if ($exists) {
            $exists->bind_param("ss", $contactLookup['email_lookup_hash'], $contactLookup['phone_lookup_hash']);
            $exists->execute();
            $hit = $exists->get_result()->fetch_assoc();
            $exists->close();
            $hasExisting = (bool)$hit;
        }

        echo json_encode([
            'ok' => true,
            'exists' => $hasExisting,
            'message' => $hasExisting ? 'Email or phone number is already tied to an existing account.' : ''
        ]);
        exit;
    }

    if ($action === 'create_invite') {
        verifyCsrfToken(false);
        $lastName = trim((string)($_POST['last_name'] ?? ''));
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $middleName = trim((string)($_POST['middle_name'] ?? ''));
        $suffix = trim((string)($_POST['suffix'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $phone10 = oi_normalize_phone10((string)($_POST['phone_number'] ?? ''));
        $roleAccess = trim((string)($_POST['role_access'] ?? ''));
        $positionAccess = trim((string)($_POST['position_access'] ?? ''));
        $department = trim((string)($_POST['department'] ?? ''));
        $areaNumber = trim((string)($_POST['area_number'] ?? ''));
        $actorPassword = (string)($_POST['actor_password'] ?? '');

        if ($lastName === '' || $firstName === '' || $email === '' || $roleAccess === '' || $department === '') {
            set_invite_flash('danger', 'Last name, first name, email, role, and department are required.');
            redirect_self();
        }
        if (!preg_match('/^[A-Za-z][A-Za-z .\'-]{0,99}$/', $lastName) || !preg_match('/^[A-Za-z][A-Za-z .\'-]{0,99}$/', $firstName)) {
            set_invite_flash('danger', 'Invalid name format.');
            redirect_self();
        }
        if ($middleName !== '' && !preg_match('/^[A-Za-z][A-Za-z .\'-]{0,99}$/', $middleName)) {
            set_invite_flash('danger', 'Invalid middle name format.');
            redirect_self();
        }
        if ($suffix !== '' && !preg_match('/^[A-Za-z0-9 .\'-]{1,20}$/', $suffix)) {
            set_invite_flash('danger', 'Invalid suffix format.');
            redirect_self();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_invite_flash('danger', 'Invalid email address.');
            redirect_self();
        }
        if (!oi_is_valid_phone10($phone10)) {
            set_invite_flash('danger', 'Invalid mobile number. Use 9XXXXXXXXX.');
            redirect_self();
        }
        if ($roleAccess === 'Officials') {
            $roleAccess = 'Official';
        } elseif ($roleAccess === 'Personnels') {
            $roleAccess = 'Personnel';
        }
        if (!in_array($roleAccess, ['Official', 'Personnel', 'SuperAdmin'], true)) {
            set_invite_flash('danger', 'Role must be Official, Personnel, or SuperAdmin.');
            redirect_self();
        }
        if ($positionAccess === '') {
            set_invite_flash('danger', 'Position access is required.');
            redirect_self();
        }
        if ($areaNumber !== '' && !in_array($areaNumber, $areaNumberOptions, true)) {
            set_invite_flash('danger', 'Please select a valid area number.');
            redirect_self();
        }
        $selectedPersonnelProfile = null;
        if ($roleAccess === 'Personnel') {
            if (!oi_access_label_valid($department) || !oi_access_label_valid($positionAccess)) {
                set_invite_flash('danger', 'Select a valid Personnel Access Control profile before sending the invite.');
                redirect_self();
            }
            $selectedPersonnelProfile = oi_find_personnel_access_profile($conn, $department, $positionAccess);
            if (!$selectedPersonnelProfile) {
                set_invite_flash('danger', 'This personnel department and title does not have a saved access profile yet. Add it in Personnel Access Control first.');
                redirect_self();
            }
            if ((int)($selectedPersonnelProfile['permission_count'] ?? 0) <= 0) {
                set_invite_flash('danger', 'The selected personnel title has no granted modules. Update its access profile before inviting personnel.');
                redirect_self();
            }
        } else {
            $allowedForRole = $positionsByRole[$roleAccess] ?? [];
            if (!in_array($positionAccess, $allowedForRole, true)) {
                set_invite_flash('danger', "Selected position access is not allowed for {$roleAccess}.");
                redirect_self();
            }
            $department = 'Office of the Barangay';
        }

        $needsArea = in_array($positionAccess, $areaRequiredPositions, true);
        if ($positionAccess === 'Department OIC (Officer In Charge)' && $department === 'Barangay Peace and Order') {
            $needsArea = true;
        }
        if ($needsArea && $areaNumber === '') {
            set_invite_flash('danger', 'Area Number is required for this position.');
            redirect_self();
        }
        if (!$needsArea && $areaNumber === '' && $department === 'Office of the Barangay') {
            $areaNumber = 'Barangay Wide';
        }
        $employmentStatus = in_array($positionAccess, ['Barangay Chairman', 'Barangay Official', 'Barangay Secretary'], true)
            ? 'Regular Government Officials'
            : 'Regular';

        verify_sender_password_for_invite_or_fail($conn, $actorUserId, $actorPassword);

        $contactLookup = pii_prepare_useraccount_contacts($email, $phone10);
        $exists = $conn->prepare("
            SELECT user_id
            FROM useraccountstbl
            WHERE email_lookup_hash = ? OR phone_lookup_hash = ?
            LIMIT 1
        ");
        if ($exists) {
            $exists->bind_param("ss", $contactLookup['email_lookup_hash'], $contactLookup['phone_lookup_hash']);
            $exists->execute();
            $hit = $exists->get_result()->fetch_assoc();
            $exists->close();
            if ($hit) {
                set_invite_flash('danger', 'Email or phone number is already tied to an existing account.');
                redirect_self();
            }
        }

        $token = oi_generate_invite_token();
        $expiresAt = (new DateTimeImmutable('+48 hours'))->format('Y-m-d H:i:s');
        $inviteId = 0;
        $ok = false;

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $inviteCode = oi_generate_invite_code($conn, $areaNumber);
            } catch (Throwable $e) {
                set_invite_flash('danger', $e->getMessage());
                redirect_self();
            }

            $stmt = $conn->prepare("
                INSERT INTO officialinvitetbl
                    (invite_code, invite_token_hash, invite_email, invite_email_lookup_hash, invite_phone, invite_phone_lookup_hash, firstname, middlename, lastname, suffix, role_access, position_access, department, employment_status, area_number, status, onboarding_step, invited_by_user_id, expires_at)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 'password', ?, ?)
            ");
            if (!$stmt) {
                set_invite_flash('danger', 'Failed to create invite.');
                redirect_self();
            }
            $inviteContact = pii_prepare_official_invite_contacts($email, $phone10);
            $inviteName = pii_encrypt_field_map([
                'firstname' => $firstName,
                'middlename' => $middleName,
                'lastname' => $lastName,
                'suffix' => $suffix,
            ]);
            oi_bind_string_params($stmt, [
                $inviteCode,
                $token['hash'],
                $inviteContact['invite_email'],
                $inviteContact['invite_email_lookup_hash'],
                $inviteContact['invite_phone'],
                $inviteContact['invite_phone_lookup_hash'],
                $inviteName['firstname'],
                $inviteName['middlename'],
                $inviteName['lastname'],
                $inviteName['suffix'],
                $roleAccess,
                $positionAccess,
                $department,
                $employmentStatus,
                $areaNumber,
                $actorUserId,
                $expiresAt
            ]);
            $ok = $stmt->execute();
            $inviteId = (int)$stmt->insert_id;
            $errNo = (int)$stmt->errno;
            $stmt->close();

            if ($ok && $inviteId > 0) {
                break;
            }
            if ($errNo !== 1062) {
                break;
            }
        }

        if (!$ok || $inviteId <= 0) {
            set_invite_flash('danger', 'Failed to create invite.');
            redirect_self();
        }

        $invite = fetch_invite_by_id($conn, $inviteId);
        $emailSent = $invite ? send_invite_email($invite, $token['raw']) : false;
        $smsRoleText = strtolower($roleAccess);
        $smsSent = sendSMS('0' . $phone10, "Barangay San Jose: You were invited as a {$smsRoleText} account. Please check your email for your account invite link.");

        insertUnifiedAuditLog(
            $conn,
            $actorUserId,
            $actorRole,
            'Official Invites',
            'OfficialInvite',
            (string)$inviteId,
            'OFFICIAL_INVITE_CREATE',
            'invite_email / role_access',
            null,
            $email . ' / ' . $roleAccess,
            'Invite sent via email; SMS notification dispatched.',
            null
        );

        $msg = $emailSent
            ? 'Invite created and email sent.'
            : 'Invite created but email send failed (check SMTP).';
        if (!$smsSent) {
            $msg .= ' SMS notification failed.';
        }
        set_invite_flash($emailSent ? 'success' : 'warning', $msg);
        $_SESSION['official_invite_flash_origin'] = 'create_invite';
        redirect_self();
    }

    if ($action === 'revoke_invite') {
        verifyCsrfToken(false);
        $inviteId = (int)($_POST['invite_id'] ?? 0);
        $actorPassword = (string)($_POST['actor_password'] ?? '');
        verify_actor_password_or_fail($conn, $actorUserId, $isSuperAdminActor, $actorPassword);
        if ($inviteId <= 0) {
            set_invite_flash('danger', 'Invalid invite.');
            redirect_self();
        }
        $stmt = $conn->prepare("
            UPDATE officialinvitetbl
            SET status = 'Revoked', revoked_at = NOW()
            WHERE invite_id = ? AND status IN ('Pending', 'InProgress')
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param("i", $inviteId);
            $stmt->execute();
            $stmt->close();
        }
        set_invite_flash('success', 'Invite revoked.');
        $_SESSION['official_invite_flash_origin'] = 'revoke_invite';
        redirect_self();
    }

    if ($action === 'resend_invite') {
        verifyCsrfToken(false);
        $inviteId = (int)($_POST['invite_id'] ?? 0);
        $actorPassword = (string)($_POST['actor_password'] ?? '');
        verify_actor_password_or_fail($conn, $actorUserId, $isSuperAdminActor, $actorPassword);
        $invite = fetch_invite_by_id($conn, $inviteId);
        if (!$invite) {
            set_invite_flash('danger', 'Invite not found.');
            redirect_self();
        }
        if ((string)$invite['status'] !== 'Pending' || !empty($invite['token_used_at'])) {
            set_invite_flash('warning', 'Only unused pending invites can be resent.');
            redirect_self();
        }
        $token = oi_generate_invite_token();
        $expiresAt = (new DateTimeImmutable('+48 hours'))->format('Y-m-d H:i:s');
        $up = $conn->prepare("
            UPDATE officialinvitetbl
            SET invite_token_hash = ?, expires_at = ?, updated_at = NOW()
            WHERE invite_id = ?
            LIMIT 1
        ");
        if ($up) {
            $up->bind_param("ssi", $token['hash'], $expiresAt, $inviteId);
            $up->execute();
            $up->close();
        }
        $invite = fetch_invite_by_id($conn, $inviteId);
        $emailSent = $invite ? send_invite_email($invite, $token['raw']) : false;
        $smsSent = sendSMS('0' . (string)$invite['invite_phone'], 'Barangay San Jose: Your official invite was resent. Please check your email.');
        set_invite_flash($emailSent ? 'success' : 'warning', $emailSent ? 'Invite resent.' : 'Invite updated but email failed.');
        if (!$smsSent) {
            set_invite_flash('warning', 'Invite resent email processed, but SMS notification failed.');
        }
        $_SESSION['official_invite_flash_origin'] = 'resend_invite';
        redirect_self();
    }
}

$rows = [];
$q = $conn->query("
    SELECT invite_id, invite_code, invite_email, invite_phone, firstname, middlename, lastname, suffix, role_access, position_access, department, employment_status, area_number, status, onboarding_step, expires_at, created_at, updated_at
    FROM officialinvitetbl
    WHERE LOWER(TRIM(role_access)) IN ('official', 'officials', 'personnel', 'personnels', 'superadmin', 'admin', 'employee')
    ORDER BY invite_id DESC
    LIMIT 100
");
if ($q) {
    while ($row = $q->fetch_assoc()) {
        $rows[] = pii_decrypt_official_invite_row($row) ?? $row;
    }
}

$personnelAccessProfiles = oi_fetch_personnel_access_profiles($conn);
$personnelInvitePermissionCatalog = amp_get_permission_catalog();

function oi_status_pill_class(string $value, string $type = 'generic'): string {
    $normalized = strtolower(trim($value));

    if ($type === 'invite_status') {
        if ($normalized === 'completed') {
            return 'approved';
        }
        if ($normalized === 'revoked' || $normalized === 'expired') {
            return 'denied';
        }
        if ($normalized === 'pending' || $normalized === 'inprogress') {
            return 'pending';
        }
    }

    if ($type === 'invite_step') {
        if ($normalized === 'completed') {
            return 'approved';
        }
        if ($normalized === 'password' || $normalized === 'profile' || $normalized === 'verification') {
            return 'pending';
        }
    }

    if (preg_match('/active|approved|verified|completed/', $normalized)) {
        return 'approved';
    }
    if (preg_match('/revoked|rejected|denied|expired|inactive|disabled|suspended/', $normalized)) {
        return 'denied';
    }
    if (preg_match('/pending|progress|review|password|profile|verification/', $normalized)) {
        return 'pending';
    }

    return 'archived';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="../Images/favicon_sanjose.png?v=20260211">
    <title>Account Invite</title>
    <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260319-1">
    <style>
      #main-display { min-width: 0; }
      .invite-form-shell { border: 1px solid #f1e1cf; border-radius: 16px; background: #fff; }
      .invite-form-header { border-bottom: 1px solid #f3e7d9; padding-bottom: 12px; margin-bottom: 16px; }
      .invite-section { border: 1px solid #ececec; border-radius: 12px; background: #fcfcfd; padding: 14px 14px 10px; }
      .invite-section-title { font-size: 0.9rem; font-weight: 700; color: #4b5563; margin-bottom: 10px; }
      .invite-form-shell .form-label { font-weight: 600; margin-bottom: 0.35rem; }
      .invite-help { font-size: 0.8rem; color: #6b7280; }
      .invite-required { color: #dc3545; font-weight: 700; }
      .invite-submit-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-top: 14px; flex-wrap: wrap; }
      .invite-submit-note { font-size: 0.82rem; color: #6b7280; }
      .invite-label-action {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 0.35rem;
      }
      .invite-label-action .form-label {
        margin-bottom: 0;
      }
      .invite-access-preview {
        display: none;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        background: #fff;
        padding: 12px 14px;
        margin-top: 12px;
      }
      .invite-access-preview.is-visible {
        display: block;
      }
      .invite-access-preview-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        font-weight: 700;
        color: #111827;
      }
      .invite-access-preview-meta {
        color: #6b7280;
        font-size: 0.82rem;
        margin-top: 2px;
      }
      .invite-access-module-list,
      .invite-profile-permission-groups {
        display: grid;
        gap: 8px;
      }
      .invite-access-module-list {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        margin-top: 10px;
      }
      .invite-access-module {
        border: 1px solid #edf0f4;
        border-radius: 8px;
        background: #fcfcfd;
        padding: 8px 10px;
        font-size: 0.82rem;
        font-weight: 650;
        color: #374151;
      }
      .invite-profile-permission-groups {
        max-height: 42vh;
        overflow-y: auto;
        padding-right: 4px;
      }
      .invite-profile-permission-section {
        border: 1px solid #ececec;
        border-radius: 12px;
        background: #fff;
        overflow: hidden;
      }
      .invite-profile-permission-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 12px;
        background: #faf7f2;
        border-bottom: 1px solid #f1f1f1;
        font-weight: 700;
      }
      .invite-profile-permission-actions {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
      }
      .invite-profile-permission-items {
        display: grid;
        gap: 8px;
        padding: 10px 12px 12px;
      }
      .invite-profile-permission-item {
        border: 1px solid #edf0f4;
        border-radius: 8px;
        padding: 8px 10px;
        background: #fff;
      }
      .invite-profile-permission-item.is-child {
        margin-left: 14px;
        background: #fcfcfd;
      }
      .invite-profile-permission-item label {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        width: 100%;
        cursor: pointer;
      }
      .invite-profile-permission-item input {
        margin-top: 0.2rem;
      }
      .invite-profile-permission-main {
        display: block;
        font-weight: 700;
        color: #111827;
        line-height: 1.25;
      }
      .invite-profile-permission-sub {
        display: block;
        color: #6b7280;
        font-size: 0.78rem;
        line-height: 1.25;
        margin-top: 2px;
      }
      #inviteAuthorizationModal .modal-content,
      #inviteSendingModal .modal-content,
      #inviteResultModal .modal-content,
      #personnelProfileCreateModal .modal-content,
      #inviteAuthorizationModal .form-control,
      #inviteAuthorizationModal .btn,
      #inviteSendingModal .btn,
      #inviteResultModal .btn,
      #personnelProfileCreateModal .btn,
      #personnelProfileCreateModal .form-control,
      #personnelProfileCreateModal .form-select {
        font-family: 'Geist', sans-serif;
      }
      .invite-modal-title-centered { width: 100%; text-align: center; font-weight: 700; }
      .invite-auth-summary { font-size: 0.95rem; line-height: 1.45; color: #374151; }
      .invite-auth-summary strong { color: #111827; }
      .invite-history-shell {
        border: 1px solid #f1e1cf;
        border-radius: 16px;
        background: #fff;
        width: 100%;
        max-width: 100%;
        overflow-x: hidden;
        overflow-y: visible;
      }
      .invite-history-shell .table-responsive {
        overflow-x: auto;
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
      }
      .invite-history-table {
        min-width: 1280px;
      }
      .invite-history-table th,
      .invite-history-table td {
        vertical-align: middle;
      }
      .invite-history-table th:nth-child(2),
      .invite-history-table td:nth-child(2) {
        min-width: 220px;
        white-space: nowrap;
      }
      .invite-history-table th:nth-child(9),
      .invite-history-table td:nth-child(9) {
        min-width: 185px;
        white-space: nowrap;
      }
      .invite-history-table td:nth-child(10) {
        white-space: nowrap;
      }
    </style>
</head>
<body class="bg-light">
<div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include 'includes/sidebar.php'; ?>

    <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
        <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C; ">
            Account Invite
        </h2>
        <hr>
        <br>
        <script>
          window.OFFICIAL_INVITE_FLASH = <?= json_encode([
              'type' => (string)($flash['type'] ?? ''),
              'message' => (string)($flash['message'] ?? ''),
              'origin' => $flashOrigin,
          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
          window.OFFICIAL_INVITE_ACCESS = <?= json_encode([
              'csrfToken' => $csrfToken,
              'departmentOptions' => array_values($departmentOptions),
              'personnelProfiles' => $personnelAccessProfiles,
              'permissionCatalog' => $personnelInvitePermissionCatalog,
              'defaultPermissionKeys' => array_values(amp_get_default_admin_permission_keys()),
          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        </script>

        <?php if (!empty($flash['message'])): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type'] ?: 'info', ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars((string)$flash['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm mb-4 invite-form-shell">
            <div class="card-body">
                <div class="invite-form-header">
                    <h5 class="card-title mb-1">Send Account Invite</h5>
                    <div class="invite-help">Create onboarding access by entering identity and work assignment details.</div>
                </div>
                <form method="post" id="createInviteForm">
                    <input type="hidden" name="action" value="create_invite">
                    <input type="hidden" name="actor_password" id="inviteActorPasswordHidden" value="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <p class="small text-muted mb-3"><span class="invite-required">*</span> Required fields</p>

                    <div class="invite-section mb-3">
                        <div class="invite-section-title">Identity</div>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Last Name <span class="invite-required">*</span></label>
                                <input class="form-control" name="last_name" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">First Name <span class="invite-required">*</span></label>
                                <input class="form-control" name="first_name" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Middle Name</label>
                                <input class="form-control" name="middle_name">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Suffix</label>
                                <select class="form-select" name="suffix">
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

                    <div class="invite-section mb-3">
                        <div class="invite-section-title">Contact</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="invite-required">*</span></label>
                                <input class="form-control" name="email" type="email" placeholder="name@example.com" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mobile Number <span class="invite-required">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">+63</span>
                                    <input class="form-control" name="phone_number" placeholder="9XXXXXXXXX" inputmode="numeric" pattern="9[0-9]{9}" maxlength="10" required>
                                </div>
                            </div>
                        </div>
                        <div id="invitePrecheckError" class="text-danger small mt-2" style="display:none;"></div>
                    </div>

                    <div class="invite-section mb-3">
                        <div class="invite-section-title">Access Assignment</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Role <span class="invite-required">*</span></label>
                                <select class="form-select" name="role_access" id="roleAccessSelect" required>
                                    <option value="" selected disabled>Select Role</option>
                                    <option value="Official">Official</option>
                                    <option value="Personnel">Personnel</option>
                                    <option value="SuperAdmin">SuperAdmin</option>
                                </select>
                                <div class="invite-help">Choose the role before selecting the matching position access.</div>
                            </div>
                            <div class="col-md-6">
                                <div class="invite-label-action">
                                    <label class="form-label" for="positionAccessSelect">Title / Position <span class="invite-required">*</span></label>
                                    <button type="button" class="btn btn-outline-primary btn-sm d-none" id="openPersonnelProfileCreateBtn">
                                        <i class="fas fa-plus me-1"></i>Add Title
                                    </button>
                                </div>
                                <select class="form-select" name="position_access" id="positionAccessSelect" required>
                                    <option value="">Select Title / Position</option>
                                    <?php foreach ($positionAccessOptions as $position): ?>
                                        <option value="<?= htmlspecialchars((string)$position, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$position, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invite-help" id="positionHelp">Only positions allowed for the selected role will stay available.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Department <span class="invite-required">*</span></label>
                                <select class="form-select" name="department" id="departmentSelect" required>
                                    <option value="">Select Department</option>
                                    <?php foreach ($departmentOptions as $dept): ?>
                                        <option value="<?= htmlspecialchars((string)$dept, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$dept, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invite-help" id="departmentHelp">Assign the account to the department where this user will work.</div>
                            </div>
                            <div class="col-md-6" id="areaNumberGroup">
                                <label class="form-label">Area Designation <span class="invite-required" id="areaRequiredMark">*</span></label>
                                <select class="form-select" name="area_number" id="areaNumberSelect">
                                    <option value="">Select Area Number</option>
                                    <?php foreach ($areaNumberOptions as $area): ?>
                                        <option value="<?= htmlspecialchars((string)$area, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$area, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invite-help" id="areaNumberHelp">Required for area-scoped positions such as Barangay Secretary, Barangay Police, Desk Officer, and Area OIC.</div>
                            </div>
                            <div class="col-12">
                                <div class="invite-access-preview" id="personnelAccessPreview">
                                    <div class="invite-access-preview-title">
                                        <span id="personnelAccessPreviewTitle">Access Preview</span>
                                        <span class="badge text-bg-light" id="personnelAccessPreviewCount">0 modules</span>
                                    </div>
                                    <div class="invite-access-preview-meta" id="personnelAccessPreviewMeta">Select a personnel access profile to review the modules this invite will inherit.</div>
                                    <div class="invite-access-module-list" id="personnelAccessPreviewModules"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="invite-submit-row">
                        <div class="invite-submit-note">An email invite and SMS notification will be sent after submit.</div>
                        <button type="button" class="btn btn-primary px-4" id="openInviteAuthorizationModal">Send Invite</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="div-tableContainer" class="shadow-sm mb-4 p-4 invite-history-shell resident-masterlist-shell">
            <div class="invite-form-header">
                <h5 class="card-title mb-1">Recent Invites</h5>
                <div class="invite-help">Latest 100 invite records with onboarding state and remaining actions.</div>
            </div>
                <div class="table-responsive compact-admin-table-shell">
                    <table class="table table-sm align-middle mb-0 invite-history-table compact-admin-table compact-admin-table--wide" data-table-pagination>
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Role - Position</th>
                            <th>Department</th>
                            <th>Area - Employment</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Step</th>
                            <th>Expires</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $name = trim(($r['firstname'] ?? '') . ' ' . (($r['middlename'] ?? '') !== '' ? ($r['middlename'] . ' ') : '') . ($r['lastname'] ?? '') . (($r['suffix'] ?? '') !== '' ? (' ' . $r['suffix']) : ''));
                            $statusValue = (string)($r['status'] ?? '');
                            $stepValue = (string)($r['onboarding_step'] ?? '');
                            ?>
                            <tr>
                                <td><?= htmlspecialchars((string)((trim((string)($r['invite_code'] ?? '')) !== '') ? $r['invite_code'] : (string)((int)$r['invite_id'])), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?= htmlspecialchars(ucfirst(normalizeRoleName((string)$r['role_access'])), ENT_QUOTES, 'UTF-8') ?>
                                    -
                                    <?= htmlspecialchars((string)($r['position_access'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars((string)($r['department'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars((string)(trim((string)($r['area_number'] ?? '')) !== '' ? $r['area_number'] : 'N/A'), ENT_QUOTES, 'UTF-8') ?>
                                    -
                                    <?= htmlspecialchars((string)($r['employment_status'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars((string)$r['invite_email'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="text-muted">+63<?= htmlspecialchars((string)$r['invite_phone'], ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                <td><span class="status-pill <?= htmlspecialchars(oi_status_pill_class($statusValue, 'invite_status'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusValue, ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><span class="status-pill <?= htmlspecialchars(oi_status_pill_class($stepValue, 'invite_step'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($stepValue, ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= htmlspecialchars((string)$r['expires_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <div class="compact-table-actions">
                                    <?php if ((string)$r['status'] === 'Pending'): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="resend_invite">
                                            <input type="hidden" name="invite_id" value="<?= (int)$r['invite_id'] ?>">
                                            <input type="hidden" name="actor_password" value="">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <button
                                                type="button"
                                                class="btn btn-outline-primary btn-sm compact-table-btn js-invite-action-btn"
                                                data-action-label="Resend"
                                                data-action-verb="resend"
                                                data-invite-name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                                            >Resend</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (in_array((string)$r['status'], ['Pending', 'InProgress'], true)): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="revoke_invite">
                                            <input type="hidden" name="invite_id" value="<?= (int)$r['invite_id'] ?>">
                                            <input type="hidden" name="actor_password" value="">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <button
                                                type="button"
                                                class="btn btn-outline-danger btn-sm compact-table-btn js-invite-action-btn"
                                                data-action-label="Revoke"
                                                data-action-verb="revoke"
                                                data-invite-name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                                            >Revoke</button>
                                        </form>
                                    <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
        </div>

    </main>
</div>
<div class="modal fade" id="inviteAuthorizationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title invite-modal-title-centered" id="inviteAuthorizationModalTitle">Confirm Invite Authorization</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="invite-auth-summary mb-3" id="inviteAuthorizationSummary">
          You are about to grant account access.
        </div>
        <label for="inviteActorPasswordModalInput" class="form-label">Your Current Password <span class="invite-required">*</span></label>
        <input type="password" class="form-control" id="inviteActorPasswordModalInput" autocomplete="current-password" placeholder="Enter your password">
        <div class="invite-help mt-2">Enter password to allow the transaction.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmInviteAuthorizationBtn">Confirm</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="inviteSendingModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title invite-modal-title-centered">Sending Invite</h5>
      </div>
      <div class="modal-body text-center">
        <div class="spinner-border text-primary mb-3" role="status" aria-hidden="true"></div>
        <div>Please wait while we send the invite and notification.</div>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="inviteResultModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title invite-modal-title-centered" id="inviteResultTitle">Invite Result</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center" id="inviteResultBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="personnelProfileCreateModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title invite-modal-title-centered">Create Personnel Access Profile</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info mb-3">
          Add the title first, then choose the modules and trackers personnel with this title can see.
        </div>
        <div id="personnelProfileCreateError" class="alert alert-danger d-none"></div>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold" for="personnelProfileCreateDepartment">Department</label>
            <select class="form-select" id="personnelProfileCreateDepartment">
              <option value="">Select Department</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold" for="personnelProfileCreatePosition">New Title / Position</label>
            <input class="form-control" id="personnelProfileCreatePosition" maxlength="100" placeholder="Example: Blotter Reviewer">
          </div>
        </div>
        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-2">
          <div>
            <div class="fw-bold">Module Access</div>
            <div class="invite-help" id="personnelProfileCreatePermissionSummary">0 modules selected</div>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="personnelProfileCreateCheckDefaults">Use Default</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="personnelProfileCreateClearAll">Clear All</button>
          </div>
        </div>
        <div class="invite-profile-permission-groups" id="personnelProfileCreatePermissionGroups"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="personnelProfileCreateSaveBtn">Create Profile</button>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  (function () {
	    const accessOptions = window.OFFICIAL_INVITE_ACCESS || {};
	    const roleSelect = document.getElementById('roleAccessSelect');
	    const positionSelect = document.getElementById('positionAccessSelect');
	    const departmentSelect = document.getElementById('departmentSelect');
	    const departmentHelp = document.getElementById('departmentHelp');
	    const positionHelp = document.getElementById('positionHelp');
	    const areaGroup = document.getElementById('areaNumberGroup');
	    const areaSelect = document.getElementById('areaNumberSelect');
	    const areaHelp = document.getElementById('areaNumberHelp');
	    const areaRequiredMark = document.getElementById('areaRequiredMark');
	    const addProfileBtn = document.getElementById('openPersonnelProfileCreateBtn');
	    const previewEl = document.getElementById('personnelAccessPreview');
	    const previewTitleEl = document.getElementById('personnelAccessPreviewTitle');
	    const previewCountEl = document.getElementById('personnelAccessPreviewCount');
	    const previewMetaEl = document.getElementById('personnelAccessPreviewMeta');
	    const previewModulesEl = document.getElementById('personnelAccessPreviewModules');
	    const createModalEl = document.getElementById('personnelProfileCreateModal');
	    const createDepartmentSelect = document.getElementById('personnelProfileCreateDepartment');
	    const createPositionInput = document.getElementById('personnelProfileCreatePosition');
	    const createErrorEl = document.getElementById('personnelProfileCreateError');
	    const createPermissionGroupsEl = document.getElementById('personnelProfileCreatePermissionGroups');
	    const createPermissionSummaryEl = document.getElementById('personnelProfileCreatePermissionSummary');
	    const createSaveBtn = document.getElementById('personnelProfileCreateSaveBtn');
	    const createDefaultBtn = document.getElementById('personnelProfileCreateCheckDefaults');
	    const createClearBtn = document.getElementById('personnelProfileCreateClearAll');
	    if (!roleSelect || !positionSelect || !departmentSelect || !departmentHelp || !areaGroup || !areaSelect) return;

	    const positionsByRole = <?= json_encode($positionsByRole, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
	    const departmentOptions = Array.isArray(accessOptions.departmentOptions) ? accessOptions.departmentOptions : <?= json_encode(array_values($departmentOptions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
	    const areaRequiredPositions = new Set(<?= json_encode(array_values($areaRequiredPositions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
	    const permissionCatalog = Array.isArray(accessOptions.permissionCatalog) ? accessOptions.permissionCatalog : [];
	    const defaultPermissionKeys = Array.isArray(accessOptions.defaultPermissionKeys) ? accessOptions.defaultPermissionKeys.map(String) : [];
	    const csrfToken = String(accessOptions.csrfToken || '');
	    let personnelProfiles = [];
	    let profileCreatePermissionMap = {};

	    const escapeHtml = function (value) {
	      return String(value ?? '')
	        .replace(/&/g, '&amp;')
	        .replace(/</g, '&lt;')
	        .replace(/>/g, '&gt;')
	        .replace(/"/g, '&quot;')
	        .replace(/'/g, '&#39;');
	    };

	    const sortLabels = function (values) {
	      return Array.from(new Set(
	        values.map(function (value) {
	          return String(value ?? '').replace(/\s+/g, ' ').trim();
	        }).filter(Boolean)
	      )).sort(function (a, b) { return a.localeCompare(b); });
	    };

	    const normalizeScopeValue = function (value) {
	      return String(value ?? '')
	        .replace(/[\r\n\t]+/g, ' ')
	        .replace(/\s+/g, ' ')
	        .trim()
	        .toLowerCase();
	    };

	    const buildProfileId = function (department, positionAccess) {
	      return normalizeScopeValue(department) + '||' + normalizeScopeValue(positionAccess);
	    };

	    const normalizeProfile = function (profile) {
	      const department = String(profile?.department || profile?.department_label || '').replace(/\s+/g, ' ').trim();
	      const positionAccess = String(profile?.position_access || profile?.position_label || '').replace(/\s+/g, ' ').trim();
	      return {
	        profile_id: String(profile?.profile_id || buildProfileId(department, positionAccess)),
	        department,
	        position_access: positionAccess,
	        permission_keys: Array.isArray(profile?.permission_keys) ? profile.permission_keys.map(String) : [],
	        permission_labels: Array.isArray(profile?.permission_labels) ? profile.permission_labels.map(String) : [],
	        permission_count: Number(profile?.permission_count || 0),
	        permission_summary: String(profile?.permission_summary || 'No modules'),
	        updated_at: String(profile?.updated_at || ''),
	      };
	    };

	    const setPersonnelProfiles = function (profiles) {
	      personnelProfiles = (Array.isArray(profiles) ? profiles : [])
	        .map(normalizeProfile)
	        .filter(function (profile) {
	          return profile.department !== '' && profile.position_access !== '';
	        })
	        .sort(function (a, b) {
	          const deptCompare = a.department.localeCompare(b.department);
	          return deptCompare !== 0 ? deptCompare : a.position_access.localeCompare(b.position_access);
	        });
	    };

	    const permissionMetaMap = (function () {
	      const map = {};
	      permissionCatalog.forEach(function (section) {
	        (section.items || []).forEach(function (item) {
	          const children = Array.isArray(item.children) ? item.children : [];
	          if (children.length) {
	            children.forEach(function (child) {
	              if (!child || !child.key) return;
	              map[String(child.key)] = {
	                label: String(child.label || child.key),
	                parentLabel: String(item.label || ''),
	                section: String(section.section || ''),
	              };
	            });
	            return;
	          }
	          if (!item || !item.key) return;
	          map[String(item.key)] = {
	            label: String(item.label || item.key),
	            parentLabel: '',
	            section: String(section.section || ''),
	          };
	        });
	      });
	      return map;
	    })();

	    const permissionLabelsFromKeys = function (permissionKeys) {
	      return sortLabels((permissionKeys || []).map(function (key) {
	        const meta = permissionMetaMap[String(key)] || {};
	        const parentLabel = String(meta.parentLabel || '').trim();
	        const label = String(meta.label || key).trim();
	        return parentLabel ? parentLabel + ' - ' + label : label;
	      }));
	    };

	    const permissionSummaryFromKeys = function (permissionKeys, maxLabels) {
	      const labels = permissionLabelsFromKeys(permissionKeys);
	      if (!labels.length) return 'No modules';
	      const visible = labels.slice(0, maxLabels || 4);
	      return labels.length > visible.length
	        ? visible.join(', ') + ' +' + (labels.length - visible.length)
	        : visible.join(', ');
	    };

	    const refillOptions = function (selectEl, values, placeholder) {
	      const current = selectEl.value;
	      selectEl.innerHTML = '';
	      const head = document.createElement('option');
      head.value = '';
      head.textContent = placeholder;
      selectEl.appendChild(head);
      values.forEach(function (value) {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = value;
        if (value === current) option.selected = true;
        selectEl.appendChild(option);
      });
      if (Array.from(selectEl.options).some(function (option) { return option.value === current; })) {
        selectEl.value = current;
      } else {
	        selectEl.value = '';
	      }
	    };

	    const getPersonnelDepartments = function () {
	      return sortLabels(personnelProfiles.map(function (profile) { return profile.department; }));
	    };

	    const getPersonnelPositionsForDepartment = function (department) {
	      return sortLabels(personnelProfiles
	        .filter(function (profile) { return profile.department === department; })
	        .map(function (profile) { return profile.position_access; }));
	    };

	    const findPersonnelProfile = function (department, positionAccess) {
	      const targetId = buildProfileId(department, positionAccess);
	      return personnelProfiles.find(function (profile) {
	        return buildProfileId(profile.department, profile.position_access) === targetId;
	      }) || null;
	    };

	    const renderPersonnelAccessPreview = function () {
	      if (!previewEl || !previewTitleEl || !previewCountEl || !previewMetaEl || !previewModulesEl) return;
	      const isPersonnel = roleSelect.value === 'Personnel';
	      previewEl.classList.toggle('is-visible', isPersonnel);
	      if (!isPersonnel) {
	        previewModulesEl.innerHTML = '';
	        return;
	      }

	      const department = departmentSelect.value;
	      const positionAccess = positionSelect.value;
	      const profile = findPersonnelProfile(department, positionAccess);
	      if (!personnelProfiles.length) {
	        previewTitleEl.textContent = 'No Access Profiles Yet';
	        previewCountEl.textContent = '0 modules';
	        previewMetaEl.textContent = 'Use Add Title to create a profile before sending a personnel invite.';
	        previewModulesEl.innerHTML = '';
	        return;
	      }
	      if (!profile) {
	        previewTitleEl.textContent = 'Access Preview';
	        previewCountEl.textContent = '0 modules';
	        previewMetaEl.textContent = 'Select a configured department and title to preview the modules this invite will inherit.';
	        previewModulesEl.innerHTML = '';
	        return;
	      }

	      const labels = profile.permission_labels.length
	        ? profile.permission_labels
	        : permissionLabelsFromKeys(profile.permission_keys);
	      const count = Number(profile.permission_count || labels.length || profile.permission_keys.length);
	      previewTitleEl.textContent = profile.position_access;
	      previewCountEl.textContent = count + ' module' + (count === 1 ? '' : 's');
	      previewMetaEl.textContent = profile.department + ' - permissions from Personnel Access Control';
	      previewModulesEl.innerHTML = labels.length
	        ? labels.slice(0, 12).map(function (label) {
	            return '<div class="invite-access-module">' + escapeHtml(label) + '</div>';
	          }).join('') + (labels.length > 12 ? '<div class="invite-access-module">+' + (labels.length - 12) + ' more</div>' : '')
	        : '<div class="text-danger small">This access profile has no granted modules.</div>';
	    };

	    const syncRoleConditions = function (preferred) {
	      preferred = preferred || {};
	      const role = roleSelect.value;
	      const currentDepartment = preferred.department || departmentSelect.value;
	      const currentPosition = preferred.position_access || positionSelect.value;

	      if (role === 'Official' || role === 'SuperAdmin') {
	        refillOptions(positionSelect, positionsByRole[role] || [], 'Select Title / Position');
	        if (currentPosition && Array.from(positionSelect.options).some(function (option) { return option.value === currentPosition; })) {
	          positionSelect.value = currentPosition;
	        }
	        refillOptions(departmentSelect, ['Office of the Barangay'], 'Select Department');
	        departmentSelect.value = 'Office of the Barangay';
	        departmentHelp.textContent = 'Official and SuperAdmin invites are assigned to Office of the Barangay.';
	        if (positionHelp) positionHelp.textContent = 'Only positions allowed for the selected role will stay available.';
	        if (addProfileBtn) addProfileBtn.classList.add('d-none');
	      } else if (role === 'Personnel') {
	        const departments = getPersonnelDepartments();
	        refillOptions(departmentSelect, departments, departments.length ? 'Select Department' : 'No access profiles yet');
	        if (currentDepartment && departments.includes(currentDepartment)) {
	          departmentSelect.value = currentDepartment;
	        }
	        const positions = getPersonnelPositionsForDepartment(departmentSelect.value);
	        refillOptions(positionSelect, positions, departmentSelect.value ? 'Select Title / Position' : 'Select Department First');
	        if (currentPosition && positions.includes(currentPosition)) {
	          positionSelect.value = currentPosition;
	        }
	        departmentHelp.textContent = departments.length
	          ? 'Personnel departments come from saved Personnel Access Control profiles.'
	          : 'Create a Personnel Access Control profile before sending personnel invites.';
	        if (positionHelp) positionHelp.textContent = 'Titles come from Personnel Access Control and carry the module permissions shown below.';
	        if (addProfileBtn) addProfileBtn.classList.remove('d-none');
	      } else {
	        refillOptions(positionSelect, [], 'Select Role First');
	        refillOptions(departmentSelect, departmentOptions, 'Select Department');
	        departmentHelp.textContent = 'Choose the role before assigning the department.';
	        if (positionHelp) positionHelp.textContent = 'Choose the role before selecting the matching title or position.';
	        if (addProfileBtn) addProfileBtn.classList.add('d-none');
	      }

	      syncAreaRequirement();
	      renderPersonnelAccessPreview();
	    };

	    const syncAreaRequirement = function () {
	      const position = positionSelect.value;
	      const department = departmentSelect.value;
	      const role = roleSelect.value;
	      let needsArea = areaRequiredPositions.has(position);
	      if (position === 'Department OIC (Officer In Charge)' && department === 'Barangay Peace and Order') {
	        needsArea = true;
	      }
	      areaSelect.required = needsArea;
	      const showArea = needsArea || role === 'Personnel';
	      areaGroup.style.display = showArea ? '' : 'none';
	      if (areaRequiredMark) areaRequiredMark.style.display = needsArea ? '' : 'none';
	      if (areaHelp) {
	        areaHelp.textContent = needsArea
	          ? 'Required for area-scoped positions such as Barangay Secretary, Barangay Police, Desk Officer, and Area OIC.'
	          : 'Optional. Use this when the personnel title is assigned to a specific area.';
	      }
	      if (!showArea) {
	        areaSelect.value = department === 'Office of the Barangay' ? 'Barangay Wide' : '';
	      } else if (!needsArea && department === 'Office of the Barangay' && !areaSelect.value) {
	        areaSelect.value = 'Barangay Wide';
	      }
	    };

	    const showCreateError = function (message) {
	      if (!createErrorEl) return;
	      createErrorEl.textContent = message || '';
	      createErrorEl.classList.toggle('d-none', !message);
	    };

	    const setCreatePermissionMap = function (permissionKeys) {
	      profileCreatePermissionMap = {};
	      (permissionKeys || []).forEach(function (key) {
	        const normalized = String(key || '').trim();
	        if (normalized) profileCreatePermissionMap[normalized] = true;
	      });
	      renderCreatePermissionGroups();
	      updateCreatePermissionSummary();
	    };

	    const updateCreatePermissionSummary = function () {
	      if (!createPermissionSummaryEl) return;
	      const keys = Object.keys(profileCreatePermissionMap);
	      createPermissionSummaryEl.textContent = keys.length + ' module' + (keys.length === 1 ? '' : 's') + ' selected - ' + permissionSummaryFromKeys(keys, 3);
	    };

	    const getChildKeys = function (item) {
	      return Array.isArray(item.children)
	        ? item.children.map(function (child) { return String(child.key || '').trim(); }).filter(Boolean)
	        : [];
	    };

	    const bindCreatePermissionEvents = function () {
	      if (!createPermissionGroupsEl) return;
	      createPermissionGroupsEl.querySelectorAll('.invite-profile-permission-child').forEach(function (checkbox) {
	        checkbox.addEventListener('change', function () {
	          const key = String(checkbox.getAttribute('data-key') || '').trim();
	          if (!key) return;
	          if (checkbox.checked) profileCreatePermissionMap[key] = true;
	          else delete profileCreatePermissionMap[key];
	          renderCreatePermissionGroups();
	          updateCreatePermissionSummary();
	        });
	      });
	      createPermissionGroupsEl.querySelectorAll('.invite-profile-permission-parent').forEach(function (checkbox) {
	        checkbox.addEventListener('change', function () {
	          const keys = String(checkbox.getAttribute('data-child-keys') || '').split(',').map(function (value) {
	            return value.trim();
	          }).filter(Boolean);
	          keys.forEach(function (key) {
	            if (checkbox.checked) profileCreatePermissionMap[key] = true;
	            else delete profileCreatePermissionMap[key];
	          });
	          renderCreatePermissionGroups();
	          updateCreatePermissionSummary();
	        });
	      });
	      createPermissionGroupsEl.querySelectorAll('.invite-profile-permission-toggle').forEach(function (button) {
	        button.addEventListener('click', function () {
	          const keys = String(button.getAttribute('data-section-keys') || '').split(',').map(function (value) {
	            return value.trim();
	          }).filter(Boolean);
	          const mode = String(button.getAttribute('data-mode') || 'on');
	          keys.forEach(function (key) {
	            if (mode === 'on') profileCreatePermissionMap[key] = true;
	            else delete profileCreatePermissionMap[key];
	          });
	          renderCreatePermissionGroups();
	          updateCreatePermissionSummary();
	        });
	      });
	    };

	    const renderCreatePermissionGroups = function () {
	      if (!createPermissionGroupsEl) return;
	      const html = permissionCatalog.map(function (section) {
	        const sectionLeafKeys = [];
	        const itemsHtml = (section.items || []).map(function (item) {
	          const children = Array.isArray(item.children) ? item.children : [];
	          if (children.length) {
	            const childKeys = getChildKeys(item);
	            childKeys.forEach(function (key) { sectionLeafKeys.push(key); });
	            const selectedChildren = childKeys.filter(function (key) { return Boolean(profileCreatePermissionMap[key]); });
	            const parentChecked = childKeys.length > 0 && selectedChildren.length === childKeys.length;
	            const parentPartial = selectedChildren.length > 0 && selectedChildren.length < childKeys.length;
	            const childHtml = children.map(function (child) {
	              const key = String(child.key || '').trim();
	              const checked = Boolean(profileCreatePermissionMap[key]);
	              return '<div class="invite-profile-permission-item is-child">' +
	                '<label>' +
	                  '<input type="checkbox" class="invite-profile-permission-child" data-key="' + escapeHtml(key) + '" ' + (checked ? 'checked' : '') + '>' +
	                  '<span><span class="invite-profile-permission-main">' + escapeHtml(child.label || key) + '</span>' +
	                  '<span class="invite-profile-permission-sub">' + escapeHtml(section.section || '') + '</span></span>' +
	                '</label>' +
	              '</div>';
	            }).join('');
	            return '<div class="invite-profile-permission-item">' +
	              '<label>' +
	                '<input type="checkbox" class="invite-profile-permission-parent" data-child-keys="' + escapeHtml(childKeys.join(',')) + '" ' + (parentChecked ? 'checked' : '') + ' ' + (parentPartial ? 'data-partial="1"' : '') + '>' +
	                '<span><span class="invite-profile-permission-main">' + escapeHtml(item.label || item.key) + '</span>' +
	                '<span class="invite-profile-permission-sub">Toggle all modules in this group.</span></span>' +
	              '</label>' +
	            '</div>' + childHtml;
	          }
	          const key = String(item.key || '').trim();
	          sectionLeafKeys.push(key);
	          const checked = Boolean(profileCreatePermissionMap[key]);
	          return '<div class="invite-profile-permission-item">' +
	            '<label>' +
	              '<input type="checkbox" class="invite-profile-permission-child" data-key="' + escapeHtml(key) + '" ' + (checked ? 'checked' : '') + '>' +
	              '<span><span class="invite-profile-permission-main">' + escapeHtml(item.label || key) + '</span>' +
	              '<span class="invite-profile-permission-sub">' + escapeHtml(section.section || '') + '</span></span>' +
	            '</label>' +
	          '</div>';
	        }).join('');
	        return '<div class="invite-profile-permission-section">' +
	          '<div class="invite-profile-permission-head">' +
	            '<span>' + escapeHtml(section.section || 'Modules') + '</span>' +
	            '<span class="invite-profile-permission-actions">' +
	              '<button type="button" class="btn btn-outline-secondary btn-sm invite-profile-permission-toggle" data-mode="on" data-section-keys="' + escapeHtml(sectionLeafKeys.join(',')) + '">Check all</button>' +
	              '<button type="button" class="btn btn-outline-secondary btn-sm invite-profile-permission-toggle" data-mode="off" data-section-keys="' + escapeHtml(sectionLeafKeys.join(',')) + '">Clear</button>' +
	            '</span>' +
	          '</div>' +
	          '<div class="invite-profile-permission-items">' + itemsHtml + '</div>' +
	        '</div>';
	      }).join('');
	      createPermissionGroupsEl.innerHTML = html || '<div class="text-muted small">No module permissions are available.</div>';
	      createPermissionGroupsEl.querySelectorAll('.invite-profile-permission-parent[data-partial="1"]').forEach(function (input) {
	        input.indeterminate = true;
	      });
	      bindCreatePermissionEvents();
	    };

	    const notify = function (message, tone, title) {
	      if (window.UniversalModal && typeof window.UniversalModal.open === 'function') {
	        window.UniversalModal.open({ message: message, tone: tone || 'info', title: title || '' });
	        return;
	      }
	      window.alert(message);
	    };

	    const confirmAction = function (message, options) {
	      if (window.UniversalModal && typeof window.UniversalModal.confirm === 'function') {
	        return window.UniversalModal.confirm(message, options || {});
	      }
	      return Promise.resolve(window.confirm(message));
	    };

	    const validAccessLabel = function (value) {
	      return /^[A-Za-z0-9][A-Za-z0-9 .,'()&\/-]{0,99}$/.test(String(value || '').trim());
	    };

	    const openCreateProfileModal = function () {
	      if (!createModalEl || !createDepartmentSelect || !createPositionInput) return;
	      const departments = sortLabels([].concat(departmentOptions, getPersonnelDepartments()));
	      refillOptions(createDepartmentSelect, departments, 'Select Department');
	      if (roleSelect.value === 'Personnel' && departmentSelect.value && departments.includes(departmentSelect.value)) {
	        createDepartmentSelect.value = departmentSelect.value;
	      }
	      createPositionInput.value = '';
	      showCreateError('');
	      setCreatePermissionMap(defaultPermissionKeys);
	      bootstrap.Modal.getOrCreateInstance(createModalEl).show();
	      setTimeout(function () {
	        if (createDepartmentSelect.value) createPositionInput.focus();
	        else createDepartmentSelect.focus();
	      }, 120);
	    };

	    const parseJsonResponse = async function (res) {
	      const text = await res.text();
	      if (!text) return null;
	      try {
	        return JSON.parse(text);
	      } catch (error) {
	        return null;
	      }
	    };

	    const createPersonnelAccessProfile = async function () {
	      const department = String(createDepartmentSelect?.value || '').replace(/\s+/g, ' ').trim();
	      const positionAccess = String(createPositionInput?.value || '').replace(/\s+/g, ' ').trim();
	      const permissionKeys = Object.keys(profileCreatePermissionMap);

	      showCreateError('');
	      if (!department) {
	        showCreateError('Select the department for this title.');
	        createDepartmentSelect?.focus();
	        return;
	      }
	      if (!validAccessLabel(positionAccess)) {
	        showCreateError('Enter a valid title. Use letters, numbers, spaces, and common punctuation only.');
	        createPositionInput?.focus();
	        return;
	      }
	      if (findPersonnelProfile(department, positionAccess)) {
	        showCreateError('This department and title already has an access profile. Select it in the invite dropdown.');
	        return;
	      }
	      if (!permissionKeys.length) {
	        showCreateError('Select at least one module or tracker for this title.');
	        return;
	      }

	      const confirmed = await confirmAction(
	        'Create "' + positionAccess + '" under "' + department + '" with ' + permissionKeys.length + ' granted module' + (permissionKeys.length === 1 ? '' : 's') + '?',
	        { title: 'Confirm Access Profile', confirmLabel: 'Create Profile' }
	      );
	      if (!confirmed) return;

	      if (createSaveBtn) createSaveBtn.disabled = true;
	      const body = new FormData();
	      body.append('action', 'create_personnel_access_profile');
	      body.append('csrf_token', csrfToken);
	      body.append('department', department);
	      body.append('position_access', positionAccess);
	      permissionKeys.forEach(function (key) {
	        body.append('permission_keys[]', key);
	      });

	      try {
	        const res = await fetch('OfficialInvites.php', {
	          method: 'POST',
	          body: body,
	          credentials: 'same-origin',
	          headers: { Accept: 'application/json' }
	        });
	        const data = await parseJsonResponse(res);
	        if (!res.ok || !data || data.success !== true) {
	          throw new Error((data && data.message) || 'Unable to create access profile.');
	        }
	        setPersonnelProfiles(data.profiles || []);
	        roleSelect.value = 'Personnel';
	        syncRoleConditions({ department: department, position_access: positionAccess });
	        bootstrap.Modal.getOrCreateInstance(createModalEl).hide();
	        notify(data.message || 'Access profile created.', 'success', 'Access Profile Created');
	      } catch (error) {
	        showCreateError(error?.message || 'Unable to create access profile.');
	      } finally {
	        if (createSaveBtn) createSaveBtn.disabled = false;
	      }
	    };

	    setPersonnelProfiles(accessOptions.personnelProfiles || []);

	    roleSelect.addEventListener('change', syncRoleConditions);
	    positionSelect.addEventListener('change', function () {
	      syncAreaRequirement();
	      renderPersonnelAccessPreview();
	    });
	    departmentSelect.addEventListener('change', function () {
	      if (roleSelect.value === 'Personnel') {
	        const positions = getPersonnelPositionsForDepartment(departmentSelect.value);
	        refillOptions(positionSelect, positions, departmentSelect.value ? 'Select Title / Position' : 'Select Department First');
	      }
	      syncAreaRequirement();
	      renderPersonnelAccessPreview();
	    });
	    if (addProfileBtn) addProfileBtn.addEventListener('click', openCreateProfileModal);
	    if (createSaveBtn) createSaveBtn.addEventListener('click', createPersonnelAccessProfile);
	    if (createDefaultBtn) createDefaultBtn.addEventListener('click', function () {
	      setCreatePermissionMap(defaultPermissionKeys);
	    });
	    if (createClearBtn) createClearBtn.addEventListener('click', function () {
	      setCreatePermissionMap([]);
	    });
	    if (createPositionInput) createPositionInput.addEventListener('input', function () {
	      showCreateError('');
	    });
	    if (createDepartmentSelect) createDepartmentSelect.addEventListener('change', function () {
	      showCreateError('');
	    });
	    window.OfficialInviteAccess = {
	      getSelectedPersonnelProfile: function () {
	        if (roleSelect.value !== 'Personnel') return null;
	        return findPersonnelProfile(departmentSelect.value, positionSelect.value);
	      }
	    };
	    syncRoleConditions();
	  })();

  (function () {
    const form = document.getElementById('createInviteForm');
    const openBtn = document.getElementById('openInviteAuthorizationModal');
    const confirmBtn = document.getElementById('confirmInviteAuthorizationBtn');
    const hiddenPwd = document.getElementById('inviteActorPasswordHidden');
    const modalPwd = document.getElementById('inviteActorPasswordModalInput');
    const summary = document.getElementById('inviteAuthorizationSummary');
    const title = document.getElementById('inviteAuthorizationModalTitle');
    const precheckError = document.getElementById('invitePrecheckError');
    const firstName = form ? form.querySelector('input[name="first_name"]') : null;
    const middleName = form ? form.querySelector('input[name="middle_name"]') : null;
    const lastName = form ? form.querySelector('input[name="last_name"]') : null;
    const suffix = form ? form.querySelector('select[name="suffix"]') : null;
    const email = form ? form.querySelector('input[name="email"]') : null;
	    const phone = form ? form.querySelector('input[name="phone_number"]') : null;
	    const role = form ? form.querySelector('[name="role_access"]') : null;
	    const department = form ? form.querySelector('[name="department"]') : null;
	    const position = form ? form.querySelector('[name="position_access"]') : null;
	    const area = form ? form.querySelector('[name="area_number"]') : null;
	    const modalEl = document.getElementById('inviteAuthorizationModal');
	    const sendingModalEl = document.getElementById('inviteSendingModal');
	    if (!form || !openBtn || !confirmBtn || !hiddenPwd || !modalPwd || !modalEl || !summary || !precheckError || !sendingModalEl || typeof bootstrap === 'undefined') return;

	    const authModal = new bootstrap.Modal(modalEl);
	    const sendingModal = new bootstrap.Modal(sendingModalEl);
	    let pendingSubmit = null;

	    const escapeHtml = function (value) {
	      return String(value ?? '')
	        .replace(/&/g, '&amp;')
	        .replace(/</g, '&lt;')
	        .replace(/>/g, '&gt;')
	        .replace(/"/g, '&quot;')
	        .replace(/'/g, '&#39;');
	    };

    const openAuthorizationModal = function (config) {
      modalPwd.value = '';
      if (title) {
        title.textContent = config.title || 'Confirm Invite Authorization';
      }
      summary.innerHTML = config.summary || 'You are about to grant account access.';
      confirmBtn.textContent = config.confirmText || 'Confirm';
      confirmBtn.classList.toggle('btn-danger', !!config.danger);
      confirmBtn.classList.toggle('btn-primary', !config.danger);
      pendingSubmit = typeof config.onConfirm === 'function' ? config.onConfirm : null;
      authModal.show();
      setTimeout(function () { modalPwd.focus(); }, 120);
    };

	    openBtn.addEventListener('click', function () {
	      if (!form.reportValidity()) return;
	      precheckError.style.display = 'none';
	      precheckError.textContent = '';
	      const roleVal = role ? role.value.trim() : '';
	      const selectedPersonnelProfile = window.OfficialInviteAccess && typeof window.OfficialInviteAccess.getSelectedPersonnelProfile === 'function'
	        ? window.OfficialInviteAccess.getSelectedPersonnelProfile()
	        : null;
	      if (roleVal === 'Personnel' && (!selectedPersonnelProfile || Number(selectedPersonnelProfile.permission_count || 0) <= 0)) {
	        precheckError.textContent = 'Select a Personnel Access Control profile with at least one granted module before sending the invite.';
	        precheckError.style.display = '';
	        return;
	      }
	      const payload = new FormData();
	      payload.append('action', 'precheck_invite');
	      payload.append('email', email ? email.value : '');
	      payload.append('phone_number', phone ? phone.value : '');
	      payload.append('csrf_token', '<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>');

      fetch('OfficialInvites.php', {
        method: 'POST',
        body: payload
      }).then(function (res) {
        return res.json();
      }).then(function (data) {
        if (!data || data.ok !== true) {
          precheckError.textContent = (data && data.message) ? data.message : 'Unable to validate invite details right now.';
          precheckError.style.display = '';
          return;
        }
        if (data.exists) {
          precheckError.textContent = data.message || 'Email or phone number is already tied to an existing account.';
          precheckError.style.display = '';
          if (email) email.value = '';
          if (phone) phone.value = '';
          if (email) email.focus();
          return;
        }

        modalPwd.value = '';
        hiddenPwd.value = '';
        const fn = firstName ? firstName.value.trim() : '';
        const mn = middleName ? middleName.value.trim() : '';
        const ln = lastName ? lastName.value.trim() : '';
        const sx = suffix ? suffix.value.trim() : '';
	        const fullName = [fn, mn, ln, sx].filter(Boolean).join(' ').replace(/\s+/g, ' ').trim();
	        const emailVal = email ? email.value.trim() : '';
	        const phoneVal = phone ? phone.value.trim() : '';
	        const roleText = roleVal === 'SuperAdmin' ? 'superadmin' : (roleVal || 'account').toLowerCase();
	        const departmentVal = department ? department.value.trim() : '';
	        const positionVal = position ? position.value.trim() : '';
	        const areaVal = area ? area.value.trim() : '';
	        const assignmentHtml = [
	          positionVal ? '<strong>' + escapeHtml(positionVal) + '</strong>' : '',
	          departmentVal ? escapeHtml(departmentVal) : '',
	          areaVal ? escapeHtml(areaVal) : ''
	        ].filter(Boolean).join(' - ');
	        const modulesHtml = roleVal === 'Personnel' && selectedPersonnelProfile
	          ? '<div class="mt-2"><strong>Modules:</strong> ' + escapeHtml(selectedPersonnelProfile.permission_summary || 'No modules') + '</div>'
	          : '';
	        openAuthorizationModal({
	          title: 'Confirm Invite Authorization',
	          summary:
	            'You are about to give <strong>' + escapeHtml(fullName || 'this user') + '</strong> with email <strong>' + escapeHtml(emailVal || 'N/A') + '</strong> and phone <strong>+63' + escapeHtml(phoneVal || 'N/A') + '</strong> access to become a <strong>' + escapeHtml(roleText) + '</strong>.' +
	            (assignmentHtml ? '<div class="mt-2"><strong>Assignment:</strong> ' + assignmentHtml + '</div>' : '') +
	            modulesHtml,
	          confirmText: 'Confirm & Send Invite',
	          danger: false,
          onConfirm: function (pwd) {
            hiddenPwd.value = pwd;
            authModal.hide();
            sendingModal.show();
            form.submit();
          }
        });
      }).catch(function () {
        precheckError.textContent = 'Unable to validate invite details right now.';
        precheckError.style.display = '';
      });
    });

    confirmBtn.addEventListener('click', function () {
      const pwd = modalPwd.value.trim();
      if (pwd === '') {
        modalPwd.focus();
        return;
      }
      if (pendingSubmit) {
        pendingSubmit(pwd);
      }
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
      modalPwd.value = '';
      pendingSubmit = null;
    });

    document.querySelectorAll('.js-invite-action-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const ownerForm = btn.closest('form');
        const passwordField = ownerForm ? ownerForm.querySelector('input[name="actor_password"]') : null;
        const actionLabel = btn.getAttribute('data-action-label') || 'Confirm';
        const actionVerb = btn.getAttribute('data-action-verb') || 'process';
        const inviteName = btn.getAttribute('data-invite-name') || 'this invite';
        if (!ownerForm || !passwordField) return;

	        openAuthorizationModal({
	          title: actionLabel + ' Invite',
	          summary: 'Enter your current password to ' + escapeHtml(actionVerb) + ' the invite for <strong>' + escapeHtml(inviteName) + '</strong>.',
	          confirmText: actionLabel,
          danger: actionLabel.toLowerCase() === 'revoke',
          onConfirm: function (pwd) {
            passwordField.value = pwd;
            authModal.hide();
            ownerForm.submit();
          }
        });
      });
    });
  })();

  (function () {
    if (typeof bootstrap === 'undefined' || !window.OFFICIAL_INVITE_FLASH) return;
    const flash = window.OFFICIAL_INVITE_FLASH;
    if (!flash.message || flash.origin !== 'create_invite') return;
    const modalEl = document.getElementById('inviteResultModal');
    const titleEl = document.getElementById('inviteResultTitle');
    const bodyEl = document.getElementById('inviteResultBody');
    if (!modalEl || !titleEl || !bodyEl) return;
    if (flash.type === 'success') {
      titleEl.textContent = 'Invite Sent';
      bodyEl.textContent = flash.message;
    } else {
      titleEl.textContent = 'Invite Status';
      bodyEl.textContent = flash.message;
    }
    const resultModal = new bootstrap.Modal(modalEl);
    resultModal.show();
  })();
</script>
</body>
</html>
