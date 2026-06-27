<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/security.php';
require_once __DIR__ . '/../General/audit.php';
require_once __DIR__ . '/../General/adminModulePermissions.php';
require_once __DIR__ . '/../General/officialInviteCommon.php';
require_once __DIR__ . '/../General/uniqueIDGenerate.php';
require_once __DIR__ . '/../General/officialGovernance.php';

requireRoleSession(['SuperAdmin']);
oi_ensure_invite_table($conn);
amp_ensure_permission_storage($conn);
ogw_ensure_schema($conn);

header('Content-Type: application/json; charset=utf-8');

// ── Helpers ───────────────────────────────────────────────────────────────────

function otSecureDebugLog(string $message, array $context = []): void {
    $logFile = sys_get_temp_dir() . '/official_transition_secure_debug.log';
    $payload = [
        'time' => date('c'),
        'message' => $message,
        'context' => $context,
    ];
    @file_put_contents($logFile, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
}

function otJson(array $payload, int $code = 200): never {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function otBindStringParams(mysqli_stmt $stmt, array $values): void {
    if ($values === []) {
        return;
    }

    $types = str_repeat('s', count($values));
    $refs = [$types];
    foreach ($values as $idx => $value) {
        $values[$idx] = (string)$value;
        $refs[] = &$values[$idx];
    }

    if (!call_user_func_array([$stmt, 'bind_param'], $refs)) {
        throw new RuntimeException('Failed to bind SQL parameters.');
    }
}

function otError(string $message, int $code = 400): never {
    otJson(['success' => false, 'message' => $message], $code);
}

function otGenerateTransitionId(mysqli $conn): string {
    $prefix = 'TRN-' . date('Ymd') . '-';
    $res = $conn->query("SELECT transition_id FROM officialgovernancetransitiontbl WHERE transition_id LIKE '{$prefix}%' ORDER BY transition_id DESC LIMIT 1");
    $last = $res instanceof mysqli_result ? $res->fetch_assoc() : null;
    $seq = 1;
    if ($last) {
        $parts = explode('-', (string)($last['transition_id'] ?? ''));
        $seq = ((int)end($parts)) + 1;
    }
    return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
}

function otGetStatusId(mysqli $conn, string $statusType, string $statusName): ?int {
    $stmt = $conn->prepare("SELECT status_id FROM statuslookuptbl WHERE status_type = ? AND status_name = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('ss', $statusType, $statusName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['status_id'] : null;
}

function otGetOfficialUser(mysqli $conn, string $officialId): ?array {
    $stmt = $conn->prepare("
        SELECT oi.official_id, oi.user_id, oi.firstname, oi.lastname, oi.middlename, oi.suffix,
               COALESCE(oi.position_access, oi.role_access) AS position,
               oi.department, oi.area_number,
               ua.email, ua.phone_number, ua.role_access AS ua_role,
               ua.status_id_account,
               COALESCE(se.status_name, '') AS employment_status,
               COALESCE(sa.status_name, '') AS account_status_name
        FROM officialinformationtbl oi
        INNER JOIN useraccountstbl ua ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
        LEFT JOIN statuslookuptbl se ON se.status_id = oi.status_id_employment
        LEFT JOIN statuslookuptbl sa ON sa.status_id = ua.status_id_account
        WHERE oi.official_id = ?
        LIMIT 1
    ");
    if (!$stmt) return null;
    $stmt->bind_param('s', $officialId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $row = $row ? (pii_decrypt_official_row($row) ?? $row) : null;
    $row = $row ? (pii_decrypt_useraccount_row($row) ?? $row) : null;
    if ($row && otTableExists($conn, 'barangaycounciltbl')) {
        $seatStmt = $conn->prepare("
            SELECT council_id
            FROM barangaycounciltbl
            WHERE current_official_id = ?
            LIMIT 1
        ");
        if ($seatStmt) {
            $seatStmt->bind_param('s', $officialId);
            $seatStmt->execute();
            $seatRow = $seatStmt->get_result()->fetch_assoc();
            $seatStmt->close();
            $row['council_id'] = (int)($seatRow['council_id'] ?? 0);
        }
    }
    return $row ?: null;
}

function otDemoteOfficialRecord(mysqli $conn, string $officialId, int $inactiveStatusId, string $reason = ''): array
{
    $official = otGetOfficialUser($conn, $officialId);
    if (!$official) {
        throw new RuntimeException('Official not found.');
    }

    $userId = trim((string)($official['user_id'] ?? ''));
    if ($userId === '') {
        throw new RuntimeException('Official has no linked user account.');
    }

    if (strcasecmp(trim((string)($official['ua_role'] ?? '')), 'SuperAdmin') === 0
        && amp_count_active_superadmins_excluding($conn, $userId) <= 0) {
        throw new RuntimeException('At least one active SuperAdmin account must remain.');
    }

    $seatId = (int)($official['council_id'] ?? 0);

    $updateAccount = $conn->prepare("
        UPDATE useraccountstbl
        SET status_id_account = ?, updated_at = NOW()
        WHERE user_id = ?
        LIMIT 1
    ");
    if (!$updateAccount) {
        throw new RuntimeException('Failed to update account status.');
    }
    $updateAccount->bind_param('is', $inactiveStatusId, $userId);
    $updateAccount->execute();
    $updateAccount->close();

    $updateOfficial = $conn->prepare("
        UPDATE officialinformationtbl
        SET acting_for_id = NULL,
            acting_until_date = NULL,
            transition_out_type = 'Demoted',
            transition_out_date = CURDATE(),
            transition_out_reason = NULLIF(?, ''),
            last_updated = CURRENT_TIMESTAMP
        WHERE official_id = ?
        LIMIT 1
    ");
    if (!$updateOfficial) {
        throw new RuntimeException('Failed to update official record.');
    }
    $updateOfficial->bind_param('ss', $reason, $officialId);
    $updateOfficial->execute();
    $updateOfficial->close();

    if ($seatId > 0) {
        $clearSeat = $conn->prepare("
            UPDATE barangaycounciltbl
            SET current_official_id = NULL,
                updated_at = NOW()
            WHERE council_id = ?
            LIMIT 1
        ");
        if ($clearSeat) {
            $clearSeat->bind_param('i', $seatId);
            $clearSeat->execute();
            $clearSeat->close();
        }
    }

    return [
        'official' => $official,
        'seat_id' => $seatId,
    ];
}

function otFormatOfficialName(array $row, bool $lastNameFirst = false): string
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

function otDecryptOfficialContactRow(array $row): array
{
    $row = pii_decrypt_official_row($row) ?? $row;
    $row = pii_decrypt_useraccount_row($row) ?? $row;
    return pii_decrypt_assoc($row, ['firstname', 'middlename', 'lastname', 'suffix']);
}

function otSendEmail(string $email, string $subject, string $body): void {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    try {
        require_once __DIR__ . '/../EmailHandlers/emailSender.php';
        if (!class_exists('EmailSender')) {
            return;
        }
        $mailConfig = require __DIR__ . '/../General/mailConfigurations.php';
        $sender = new EmailSender($mailConfig);
        $sender->send([
            'to' => $email,
            'subject' => $subject,
            'bodyHtml' => nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')),
            'bodyText' => $body,
        ]);
    } catch (\Throwable) { /* best-effort */ }
}

function otSendOnboardingInviteEmailDetailed(string $email, string $fullName, string $roleName, string $inviteLink): array
{
    $result = ['sent' => false, 'error' => ''];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $result['error'] = 'A valid invite email address is missing.';
        return $result;
    }

    try {
        require_once __DIR__ . '/../EmailHandlers/emailSender.php';
        if (!class_exists('EmailSender')) {
            $result['error'] = 'Email sender dependency is unavailable.';
            return $result;
        }
        $mailConfig = require __DIR__ . '/../General/mailConfigurations.php';
        $sender = new EmailSender($mailConfig);
        $result['sent'] = $sender->send([
            'type' => 'onboarding_access',
            'to' => $email,
            'subject' => 'Barangay San Jose Official Account Invite',
            'data' => [
                'headline' => 'Official Account Onboarding Access',
                'recipientName' => $fullName !== '' ? $fullName : 'Official',
                'roleName' => $roleName !== '' ? $roleName : 'Official',
                'actionUrl' => $inviteLink,
                'buttonText' => 'START ONBOARDING',
                'expiresNote' => 'This invite link expires in 48 hours.',
            ],
            'bodyText' => "You were invited to onboard your Barangay San Jose account as {$roleName}.\nSTRICTLY ONE-TIME ACCESS.\nOpen: {$inviteLink}",
        ]);
        if (!$result['sent']) {
            $result['error'] = trim($sender->getLastError());
            if ($result['error'] === '') {
                $result['error'] = 'The SMTP server rejected the onboarding email.';
            }
            error_log('[officialTransitions][invite_email] ' . $result['error']);
        }
        return $result;
    } catch (\Throwable) {
        $result['error'] = 'An unexpected error occurred while sending the onboarding email.';
        error_log('[officialTransitions][invite_email] ' . $result['error']);
        return $result;
    }
}

function otSendSMS(string $phone, string $message): bool {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if ($phone === '') {
        return false;
    }
    try {
        $smsPath = __DIR__ . '/../General/sendSMS.php';
        if (!file_exists($smsPath)) {
            return false;
        }
        require_once $smsPath;
        if (function_exists('sendSMS')) {
            return (bool)@sendSMS($phone, $message);
        }
    } catch (\Throwable) { /* best-effort */ }
    return false;
}

function otSendInviteSmsDetailed(string $phone, string $message): array
{
    $result = ['sent' => false, 'error' => ''];
    $phone10 = oi_normalize_phone10($phone);
    if (!oi_is_valid_phone10($phone10)) {
        $result['error'] = 'A valid 10-digit mobile number is missing.';
        return $result;
    }

    try {
        $smsPath = __DIR__ . '/../General/sendSMS.php';
        if (!file_exists($smsPath)) {
            $result['error'] = 'SMS sender dependency is unavailable.';
            return $result;
        }
        require_once $smsPath;
        if (!function_exists('sendSMS')) {
            $result['error'] = 'SMS sender function is unavailable.';
            return $result;
        }

        $result['sent'] = (bool)sendSMS('0' . $phone10, $message);
        if (!$result['sent']) {
            $result['error'] = function_exists('getLastSmsError') ? trim((string)getLastSmsError()) : '';
            if ($result['error'] === '') {
                $result['error'] = 'The SMS gateway rejected the onboarding notification.';
            }
            error_log('[officialTransitions][invite_sms] ' . $result['error']);
        }
        return $result;
    } catch (\Throwable) {
        $result['error'] = 'An unexpected error occurred while sending the onboarding SMS.';
        error_log('[officialTransitions][invite_sms] ' . $result['error']);
        return $result;
    }
}

function otNotifyUser(mysqli $conn, string $userId, string $subject, string $message): void {
    $stmt = $conn->prepare("SELECT email, phone_number FROM useraccountstbl WHERE user_id = ? LIMIT 1");
    if (!$stmt) return;
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $acct = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$acct) return;
    $acct = pii_decrypt_useraccount_row($acct) ?? $acct;
    $email = trim((string)($acct['email'] ?? ''));
    $phone = trim((string)($acct['phone_number'] ?? ''));
    otSendEmail($email, $subject, $message);
    otSendSMS($phone, $message);
}

// ── Route ──────────────────────────────────────────────────────────────────
$action = trim((string)($_POST['action'] ?? $_GET['action'] ?? ''));
$actorId   = (string)($_SESSION['user_id'] ?? '');
$actorRole = (string)($_SESSION['role'] ?? 'SuperAdmin');
$otSecureModuleKey = 'official_transition';

function otRequireSecureChallenge(mysqli $conn, string $actorId, string $moduleKey, string $actionKey): void {
    ogw_consume_secure_action_otp(
        $conn,
        $actorId,
        (string)($_POST['challenge_key'] ?? ''),
        (string)($_POST['otp_code'] ?? ''),
        $moduleKey,
        $actionKey
    );
}

// ── Table existence guard (migration may not have run yet) ───────────────────
function otTableExists(mysqli $conn, string $table): bool {
    $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function otColumnExists(mysqli $conn, string $table, string $column): bool {
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function otEnsureTransitionFields(mysqli $conn): void {
    static $done = false;
    if ($done || !otTableExists($conn, 'officialgovernancetransitiontbl')) {
        return;
    }
    $done = true;
}

function otEnsureUpcomingOfficialFields(mysqli $conn): void {
    static $done = false;
    if ($done || !otTableExists($conn, 'upcomingofficialstbl')) {
        return;
    }
    $done = true;

    $columnDefinitions = [
        'candidate_first_name' => "ALTER TABLE upcomingofficialstbl ADD COLUMN candidate_first_name VARCHAR(100) DEFAULT NULL AFTER candidate_name",
        'candidate_last_name' => "ALTER TABLE upcomingofficialstbl ADD COLUMN candidate_last_name VARCHAR(100) DEFAULT NULL AFTER candidate_first_name",
        'candidate_middle_name' => "ALTER TABLE upcomingofficialstbl ADD COLUMN candidate_middle_name VARCHAR(100) DEFAULT NULL AFTER candidate_last_name",
        'candidate_suffix' => "ALTER TABLE upcomingofficialstbl ADD COLUMN candidate_suffix VARCHAR(20) DEFAULT NULL AFTER candidate_middle_name",
        'candidate_email' => "ALTER TABLE upcomingofficialstbl ADD COLUMN candidate_email VARCHAR(150) DEFAULT NULL AFTER candidate_suffix",
        'candidate_mobile' => "ALTER TABLE upcomingofficialstbl ADD COLUMN candidate_mobile VARCHAR(30) DEFAULT NULL AFTER candidate_email",
    ];

    foreach ($columnDefinitions as $column => $sql) {
        if (!otColumnExists($conn, 'upcomingofficialstbl', $column)) {
            $conn->query($sql);
        }
    }
}

function otIgnoredTransitionSeatNames(): array {
    return [
        'SK Chairperson',
        'Lupong Tagapamayapa Member',
        'Barangay Tanod',
        'Barangay Health Worker (BHW)',
        'Day Care Worker',
    ];
}

function otIsManagedTransitionSeat(string $seatName): bool {
    static $ignored = null;
    if ($ignored === null) {
        $ignored = array_map(
            static fn (string $value): string => strtolower(trim($value)),
            otIgnoredTransitionSeatNames()
        );
    }

    $normalized = strtolower(trim($seatName));
    return $normalized !== '' && !in_array($normalized, $ignored, true);
}

function otIgnoredTransitionSeatSql(mysqli $conn, string $field): string {
    $values = array_map(
        static fn (string $value): string => "'" . $conn->real_escape_string(strtolower(trim($value))) . "'",
        otIgnoredTransitionSeatNames()
    );
    return 'LOWER(TRIM(' . $field . ')) NOT IN (' . implode(', ', $values) . ')';
}

function otHasConfiguredTermSchedule(mysqli $conn): bool {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    if (!otTableExists($conn, 'officialgovernancetransitiontbl')) {
        $cached = false;
        return $cached;
    }

    $ignoredPositionSql = otIgnoredTransitionSeatSql($conn, 'seat_name');
    $res = $conn->query("
        SELECT 1
        FROM officialgovernancetransitiontbl
        WHERE batch_label IS NOT NULL
          AND {$ignoredPositionSql}
        LIMIT 1
    ");
    $cached = $res instanceof mysqli_result && $res->num_rows > 0;
    if ($res instanceof mysqli_result) {
        $res->close();
    }
    return $cached;
}

if (otTableExists($conn, 'officialgovernancetransitiontbl')) {
    otEnsureTransitionFields($conn);
}
if (otTableExists($conn, 'upcomingofficialstbl')) {
    otEnsureUpcomingOfficialFields($conn);
}

function otNormalizeDateOrNull(string $value): ?string {
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    if (!$dt || $dt->format('Y-m-d') !== $value) {
        return null;
    }
    return $value;
}

function otResolveEffectiveDate(array $transition): string {
    $candidateDates = [
        (string)($transition['effective_date'] ?? ''),
        date('Y-m-d'),
    ];
    foreach ($candidateDates as $candidateDate) {
        $normalized = otNormalizeDateOrNull($candidateDate);
        if ($normalized !== null) {
            return $normalized;
        }
    }
    return date('Y-m-d');
}

function otResolveTermEndDate(array $transition): ?string {
    return null;
}

function otNormalizeAreaNumber(string $areaNumber): string {
    $value = trim($areaNumber);
    return $value !== '' ? $value : 'Barangay Wide';
}

function otAreaNumberToAreaAccess(string $areaNumber): string {
    return match (strtolower(trim($areaNumber))) {
        'area 01' => 'Area01',
        'area 1a' => 'Area1A',
        'area 02' => 'Area02',
        'area 03' => 'Area03',
        'area 04' => 'Area04',
        'area 05' => 'Area05',
        'area 06' => 'Area06',
        default => 'BarangayWide',
    };
}

function otGetStatusIdByPreferredNames(mysqli $conn, array $statusTypes, array $preferredNames): ?int {
    foreach ($statusTypes as $statusType) {
        foreach ($preferredNames as $statusName) {
            $statusName = trim((string)$statusName);
            if ($statusName === '') {
                continue;
            }
            $statusId = otGetStatusId($conn, (string)$statusType, $statusName);
            if ($statusId !== null) {
                return $statusId;
            }
        }
    }
    return null;
}

function otAssertContactIsAvailable(mysqli $conn, string $email, string $phone10, string $excludeUserId = ''): void {
    $email = strtolower(trim($email));
    $phone10 = oi_normalize_phone10($phone10);
    $excludeUserId = trim($excludeUserId);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('A valid email address is required.');
    }
    if (!oi_is_valid_phone10($phone10)) {
        throw new RuntimeException('Mobile number must be a valid 10-digit Philippine mobile number.');
    }

    $emailMatch = pii_select_first_useraccount_by_lookup_hashes(
        $conn,
        'email_lookup_hash',
        pii_lookup_hash_candidates($email, 'useraccount.email'),
        ['user_id', 'role_access']
    );
    if ($emailMatch && trim((string)($emailMatch['user_id'] ?? '')) !== $excludeUserId) {
        $userId = trim((string)($emailMatch['user_id'] ?? ''));
        $role = trim((string)($emailMatch['role_access'] ?? ''));
        $suffix = $userId !== '' ? " (User ID: {$userId}" . ($role !== '' ? ", Role: {$role}" : '') . ')' : '';
        throw new RuntimeException('Email address is already tied to another account' . $suffix . '.');
    }

    $phoneMatch = pii_select_first_useraccount_by_lookup_hashes(
        $conn,
        'phone_lookup_hash',
        pii_lookup_hash_candidates($phone10, 'useraccount.phone'),
        ['user_id', 'role_access']
    );
    if ($phoneMatch && trim((string)($phoneMatch['user_id'] ?? '')) !== $excludeUserId) {
        $userId = trim((string)($phoneMatch['user_id'] ?? ''));
        $role = trim((string)($phoneMatch['role_access'] ?? ''));
        $suffix = $userId !== '' ? " (User ID: {$userId}" . ($role !== '' ? ", Role: {$role}" : '') . ')' : '';
        throw new RuntimeException('Mobile number is already tied to another account' . $suffix . '.');
    }
}

function otResolveIncomingAccessProfile(array $transition): array {
    $position = trim((string)($transition['position'] ?? ''));
    $positionLower = strtolower($position);
    $department = trim((string)($transition['department'] ?? ''));
    $areaNumber = otNormalizeAreaNumber((string)($transition['area_number'] ?? ''));
    $selectionMethod = in_array((string)($transition['transition_type'] ?? ''), ['BarangayElection', 'SKElection'], true)
        ? 'Elected'
        : 'Appointed';

    $accountRole = 'Official';
    $officialRole = 'Official';
    $positionAccess = $position !== '' ? $position : 'Barangay Official';

    if (str_contains($positionLower, 'punong barangay') || str_contains($positionLower, 'barangay captain') || $positionLower === 'barangay chairman') {
        $accountRole = 'SuperAdmin';
        $officialRole = 'SuperAdmin';
        $positionAccess = 'Barangay Chairman';
        $department = $department !== '' ? $department : 'Office of the Barangay';
        $areaNumber = 'Barangay Wide';
    } elseif (str_contains($positionLower, 'kagawad')) {
        $accountRole = 'Official';
        $officialRole = 'Official';
        $positionAccess = 'Barangay Official';
        $department = $department !== '' ? $department : 'Office of the Barangay';
        $areaNumber = 'Barangay Wide';
    } elseif ($positionLower === 'barangay secretary') {
        $accountRole = 'Official';
        $officialRole = 'Secretary';
        $positionAccess = 'Barangay Secretary';
        $department = $department !== '' ? $department : 'Office of the Barangay';
        $areaNumber = 'Barangay Wide';
    } elseif ($positionLower === 'barangay treasurer') {
        $accountRole = 'Personnel';
        $officialRole = 'Finance';
        $positionAccess = 'Barangay Treasurer';
        $department = $department !== '' ? $department : 'Barangay Treasurers Office';
        $areaNumber = 'Barangay Wide';
    } elseif (str_contains($positionLower, 'lupong')) {
        $accountRole = 'Official';
        $officialRole = 'Official';
        $positionAccess = 'Lupong Tagapamayapa Member';
        $department = $department !== '' ? $department : 'Barangay Peace and Order';
        $areaNumber = 'Barangay Wide';
    } elseif (str_contains($positionLower, 'tanod')) {
        $accountRole = 'Personnel';
        $officialRole = 'BarangayPolice';
        $positionAccess = 'Barangay Police';
        $department = $department !== '' ? $department : 'Barangay Peace and Order';
        $areaNumber = 'Barangay Wide';
    } elseif (str_contains($positionLower, 'health worker')) {
        $accountRole = 'Personnel';
        $officialRole = 'Monitoring';
        $positionAccess = 'Barangay Health Worker (BHW)';
        $department = $department !== '' ? $department : 'Barangay Monitoring';
        $areaNumber = 'Barangay Wide';
    } elseif (str_contains($positionLower, 'day care')) {
        $accountRole = 'Personnel';
        $officialRole = 'Monitoring';
        $positionAccess = 'Day Care Worker';
        $department = $department !== '' ? $department : 'Office of the Barangay';
        $areaNumber = 'Barangay Wide';
    } elseif (str_contains($positionLower, 'sk chair')) {
        $accountRole = 'Official';
        $officialRole = 'Official';
        $positionAccess = 'SK Chairperson';
        $department = $department !== '' ? $department : 'Office of the Barangay';
        $areaNumber = 'Barangay Wide';
    } else {
        $department = $department !== '' ? $department : 'Office of the Barangay';
    }

    return [
        'account_role' => $accountRole,
        'official_role' => $officialRole,
        'position_access' => $positionAccess,
        'department' => $department,
        'area_number' => $areaNumber,
        'area_access' => otAreaNumberToAreaAccess($areaNumber),
        'selection_method' => $selectionMethod,
        'employment_status' => $selectionMethod === 'Elected' ? 'Regular Government Officials' : 'Regular',
    ];
}

function otCreateOfficialInvite(mysqli $conn, array $invitePayload, string $actorUserId): array {
    $email = strtolower(trim((string)($invitePayload['email'] ?? '')));
    $phone10 = oi_normalize_phone10((string)($invitePayload['phone_number'] ?? ''));
    $firstname = trim((string)($invitePayload['firstname'] ?? ''));
    $middlename = trim((string)($invitePayload['middlename'] ?? ''));
    $lastname = trim((string)($invitePayload['lastname'] ?? ''));
    $suffix = trim((string)($invitePayload['suffix'] ?? ''));
    $roleAccess = trim((string)($invitePayload['role_access'] ?? 'Official'));
    $positionAccess = trim((string)($invitePayload['position_access'] ?? ''));
    $department = trim((string)($invitePayload['department'] ?? ''));
    $employmentStatus = trim((string)($invitePayload['employment_status'] ?? 'Regular'));
    $areaNumber = otNormalizeAreaNumber((string)($invitePayload['area_number'] ?? ''));
    $userId = trim((string)($invitePayload['user_id'] ?? ''));

    $token = oi_generate_invite_token();
    $inviteCode = oi_generate_invite_code($conn, $areaNumber);
    $expiresAt = (new DateTimeImmutable('+48 hours'))->format('Y-m-d H:i:s');

    if ($userId !== '') {
        $revokeStmt = $conn->prepare("
            UPDATE officialinvitetbl
            SET status = 'Revoked',
                revoked_at = NOW(),
                updated_at = NOW()
            WHERE user_id = ?
              AND status IN ('Pending', 'InProgress')
        ");
        if ($revokeStmt) {
            $revokeStmt->bind_param('s', $userId);
            $revokeStmt->execute();
            $revokeStmt->close();
        }
    }

    $inviteContact = pii_prepare_official_invite_contacts($email, $phone10);
    $inviteName = pii_encrypt_field_map([
        'firstname' => $firstname,
        'middlename' => $middlename,
        'lastname' => $lastname,
        'suffix' => $suffix,
    ]);
    $stmt = $conn->prepare("
        INSERT INTO officialinvitetbl
            (invite_code, invite_token_hash, invite_email, invite_email_lookup_hash, invite_phone, invite_phone_lookup_hash, firstname, middlename, lastname, suffix,
             role_access, position_access, department, employment_status, area_number, status, onboarding_step,
             invited_by_user_id, user_id, expires_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, NULLIF(?, ''),
             ?, NULLIF(?, ''), ?, NULLIF(?, ''), ?, 'Pending', 'password',
             ?, NULLIF(?, ''), ?)
    ");
    if (!$stmt) {
        throw new RuntimeException('Failed to create onboarding invite.');
    }
    otBindStringParams($stmt, [
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
        $userId,
        $expiresAt
    ]);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Failed to create onboarding invite: ' . $error);
    }
    $inviteId = (int)$stmt->insert_id;
    $stmt->close();

    $inviteLink = appBaseUrl() . appUrl('/official-onboarding?invite=' . urlencode($token['raw']));
    $fullName = trim($firstname . ' ' . ($middlename !== '' ? $middlename . ' ' : '') . $lastname . ($suffix !== '' ? ' ' . $suffix : ''));

    return [
        'invite_id' => $inviteId,
        'invite_link' => $inviteLink,
        'delivery' => [
            'email' => $email,
            'phone_number' => $phone10,
            'full_name' => $fullName,
            'role_name' => $positionAccess !== '' ? $positionAccess : $roleAccess,
        ],
    ];
}

function otDeliverOnboardingInvite(array $invite): array
{
    $inviteLink = trim((string)($invite['invite_link'] ?? ''));
    $delivery = is_array($invite['delivery'] ?? null) ? $invite['delivery'] : [];
    $email = strtolower(trim((string)($delivery['email'] ?? '')));
    $phone10 = oi_normalize_phone10((string)($delivery['phone_number'] ?? ''));
    $fullName = trim((string)($delivery['full_name'] ?? ''));
    $roleName = trim((string)($delivery['role_name'] ?? ''));

    $emailResult = $inviteLink !== ''
        ? otSendOnboardingInviteEmailDetailed(
            $email,
            $fullName,
            $roleName !== '' ? $roleName : 'Official',
            $inviteLink
        )
        : ['sent' => false, 'error' => 'Onboarding invite link is missing.'];
    $smsResult = $phone10 !== ''
        ? otSendInviteSmsDetailed($phone10, 'Barangay San Jose: Your official onboarding access is ready. Please check your email for the one-time invite link.')
        : ['sent' => false, 'error' => 'Invite mobile number is missing.'];

    return [
        'email_sent' => (bool)($emailResult['sent'] ?? false),
        'email_error' => trim((string)($emailResult['error'] ?? '')),
        'sms_sent' => (bool)($smsResult['sent'] ?? false),
        'sms_error' => trim((string)($smsResult['error'] ?? '')),
    ];
}

function otCreateIncomingOfficialShell(mysqli $conn, array $transition, array $candidate, string $actorUserId, string $activeStatusId): array {
    $email = strtolower(trim((string)($candidate['candidate_email'] ?? '')));
    $phone10 = oi_normalize_phone10((string)($candidate['candidate_mobile'] ?? $candidate['candidate_contact'] ?? ''));
    otAssertContactIsAvailable($conn, $email, $phone10);

    $assignment = otResolveIncomingAccessProfile($transition);
    $employmentStatusId = otGetStatusIdByPreferredNames(
        $conn,
        ['Official/Personnel Management', 'Employment', 'OfficialEmployment', 'UserAccount'],
        [$assignment['employment_status'], 'Regular', 'Active']
    );
    if ($employmentStatusId === null) {
        throw new RuntimeException('Employment status is missing in the lookup table.');
    }

    $userId = GenerateUserID($conn, $assignment['account_role']);
    if (!$userId) {
        throw new RuntimeException('Failed to generate a user ID for the incoming official.');
    }

    $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $accountContact = pii_prepare_useraccount_contacts($email, $phone10);
    $accountStmt = $conn->prepare("
        INSERT INTO useraccountstbl
            (user_id, phone_number, phone_lookup_hash, phoneNum_verify, email, email_lookup_hash, email_verify, password_hash, status_id_account, role_access, account_created, last_login, updated_at)
        VALUES
            (?, ?, ?, 0, ?, ?, 0, ?, ?, ?, NOW(), NOW(), NOW())
    ");
    if (!$accountStmt) {
        throw new RuntimeException('Failed to prepare incoming official account creation.');
    }
    $accountStmt->bind_param(
        'ssssssss',
        $userId,
        $accountContact['phone_number'],
        $accountContact['phone_lookup_hash'],
        $accountContact['email'],
        $accountContact['email_lookup_hash'],
        $passwordHash,
        $activeStatusId,
        $assignment['account_role']
    );
    if (!$accountStmt->execute()) {
        $error = $accountStmt->error;
        $accountStmt->close();
        throw new RuntimeException('Failed to create the incoming official account: ' . $error);
    }
    $accountStmt->close();

    $effectiveDate = otResolveEffectiveDate($transition);
    $termEndDate = otResolveTermEndDate($transition) ?? '';
    $batchLabel = trim((string)($transition['batch_label'] ?? ''));
    $officialId = GenerateOfficialPersonnelID($conn, $userId);
    if ($officialId === false || trim((string)$officialId) === '') {
        throw new RuntimeException('Failed to generate the incoming official ID.');
    }
    $lastname = trim((string)($candidate['candidate_last_name'] ?? ''));
    $firstname = trim((string)($candidate['candidate_first_name'] ?? ''));
    $middlename = trim((string)($candidate['candidate_middle_name'] ?? ''));
    $suffix = trim((string)($candidate['candidate_suffix'] ?? ''));
    $selectionMethod = (string)$assignment['selection_method'];
    $officialIdentity = pii_encrypt_field_map([
        'lastname' => $lastname,
        'firstname' => $firstname,
        'middlename' => $middlename,
        'suffix' => $suffix,
        'birthdate' => '1900-01-01',
        'sex' => 'Other',
        'civil_status' => 'Single',
        'contact_number' => $phone10,
        'email' => $email,
    ]);
    $officialStmt = $conn->prepare("
        INSERT INTO officialinformationtbl
            (official_id, user_id, lastname, firstname, middlename, suffix, birthdate, sex, civil_status, contact_number, email,
             area_access, department, selection_method, term_start, term_end, batch_label, area_number,
             role_access, position_access, status_id_employment, date_hired)
        VALUES
            (?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?, ?, ?,
             ?, ?, NULLIF(?, ''), ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''),
             ?, NULLIF(?, ''), ?, ?)
    ");
    if (!$officialStmt) {
        throw new RuntimeException('Failed to prepare the incoming official profile.');
    }
    $officialStmt->bind_param(
        'ssssssssssssssssssssis',
        $officialId,
        $userId,
        $officialIdentity['lastname'],
        $officialIdentity['firstname'],
        $officialIdentity['middlename'],
        $officialIdentity['suffix'],
        $officialIdentity['birthdate'],
        $officialIdentity['sex'],
        $officialIdentity['civil_status'],
        $officialIdentity['contact_number'],
        $officialIdentity['email'],
        $assignment['area_access'],
        $assignment['department'],
        $selectionMethod,
        $effectiveDate,
        $termEndDate,
        $batchLabel,
        $assignment['area_number'],
        $assignment['official_role'],
        $assignment['position_access'],
        $employmentStatusId,
        $effectiveDate
    );
    if (!$officialStmt->execute()) {
        $error = $officialStmt->error;
        $officialStmt->close();
        throw new RuntimeException('Failed to create the incoming official profile: ' . $error);
    }
    $officialStmt->close();

    $invite = otCreateOfficialInvite($conn, [
        'email' => $email,
        'phone_number' => $phone10,
        'firstname' => $firstname,
        'middlename' => $middlename,
        'lastname' => $lastname,
        'suffix' => $suffix,
        'role_access' => $assignment['account_role'],
        'position_access' => $assignment['position_access'],
        'department' => $assignment['department'],
        'employment_status' => $assignment['employment_status'],
        'area_number' => $assignment['area_number'],
        'user_id' => $userId,
    ], $actorUserId);

    return [
        'official_id' => $officialId,
        'user_id' => $userId,
        'invite_id' => (int)($invite['invite_id'] ?? 0),
        'invite_link' => (string)($invite['invite_link'] ?? ''),
        'invite_delivery' => is_array($invite['delivery'] ?? null) ? $invite['delivery'] : null,
        'email' => $email,
        'phone_number' => $phone10,
        'position_access' => $assignment['position_access'],
        'department' => $assignment['department'],
    ];
}

// ════════════════════════════════════════════════════════════════════════════
// FETCH: council seats (for New Transition / New Batch modals)
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'fetch_council_seats') {
    if (!otTableExists($conn, 'barangaycounciltbl')) {
        otJson(['success' => true, 'seats' => []]);
    }
    $hasConfiguredTermSchedule = otHasConfiguredTermSchedule($conn);

    $res = $conn->query("
        SELECT
            bc.council_id,
            bc.seat_name,
            bc.selection_method,
            bc.seat_group,
            bc.sort_order,
            bc.term_start,
            bc.term_end,
            bc.is_active,
            bc.current_official_id,
            oi.firstname,
            oi.lastname,
            oi.middlename,
            oi.suffix,
            COALESCE(sa.status_name,'')                    AS account_status
        FROM barangaycounciltbl bc
        LEFT JOIN officialinformationtbl oi
               ON oi.official_id = bc.current_official_id
        LEFT JOIN useraccountstbl ua
               ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
        LEFT JOIN statuslookuptbl sa
               ON sa.status_id = ua.status_id_account
        WHERE bc.is_active = 1
        ORDER BY bc.sort_order, bc.council_id
    ");

    $seats = [];
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            if (!otIsManagedTransitionSeat((string)($row['seat_name'] ?? ''))) {
                continue;
            }
            $row = otDecryptOfficialContactRow($row);
            $row['current_official_name'] = otFormatOfficialName($row, true);
            if (!$hasConfiguredTermSchedule) {
                $row['current_official_id'] = '';
                $row['current_official_name'] = '';
                $row['account_status'] = '';
                $row['term_start'] = '';
                $row['term_end'] = '';
            }
            $seats[] = $row;
        }
        $res->close();
    }

    otJson(['success' => true, 'seats' => $seats]);
}

// ════════════════════════════════════════════════════════════════════════════
// FETCH: transitions list
// ════════════════════════════════════════════════════════════════════════════
if (!function_exists('ot_governance_transition_table')) {
    function ot_governance_transition_table(): string
    {
        return 'officialgovernancetransitiontbl';
    }
}

if (!function_exists('ot_governance_fetch_transition')) {
    function ot_governance_fetch_transition(mysqli $conn, string $transitionId): ?array
    {
        $stmt = $conn->prepare("
            SELECT *
            FROM officialgovernancetransitiontbl
            WHERE transition_id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $transitionId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('ot_governance_create_shell')) {
    function ot_governance_create_shell(mysqli $conn, array $transition, array $candidate, string $actorId, int $inactiveStatusId): array
    {
        $assignment = otResolveIncomingAccessProfile([
            'position' => (string)($transition['seat_name'] ?? ''),
            'department' => (string)($transition['department'] ?? ''),
            'area_number' => (string)($transition['area_number'] ?? ''),
            'transition_type' => (string)($transition['transition_type'] ?? ''),
        ]);
        $email = strtolower(trim((string)($candidate['candidate_email'] ?? '')));
        $phone10 = oi_normalize_phone10((string)($candidate['candidate_mobile'] ?? ''));
        otAssertContactIsAvailable($conn, $email, $phone10);

        $employmentStatusId = otGetStatusIdByPreferredNames(
            $conn,
            ['Official/Personnel Management', 'Employment', 'OfficialEmployment', 'UserAccount'],
            [$assignment['employment_status'], 'Regular', 'Active']
        );
        if ($employmentStatusId === null) {
            throw new RuntimeException('Employment status is missing in the lookup table.');
        }

        $userId = GenerateUserID($conn, $assignment['account_role']);
        if (!$userId) {
            throw new RuntimeException('Failed to generate a user ID for the incoming official.');
        }

        $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $accountContact = pii_prepare_useraccount_contacts($email, $phone10);
        $accountStmt = $conn->prepare("
            INSERT INTO useraccountstbl
                (user_id, phone_number, phone_lookup_hash, phoneNum_verify, email, email_lookup_hash, email_verify, password_hash, status_id_account, role_access, account_created, last_login, updated_at)
            VALUES
                (?, ?, ?, 0, ?, ?, 0, ?, ?, ?, NOW(), NOW(), NOW())
        ");
        if (!$accountStmt) {
            throw new RuntimeException('Failed to prepare incoming official account creation.');
        }
        $inactiveStatusValue = (string)$inactiveStatusId;
        $accountStmt->bind_param(
            'ssssssss',
            $userId,
            $accountContact['phone_number'],
            $accountContact['phone_lookup_hash'],
            $accountContact['email'],
            $accountContact['email_lookup_hash'],
            $passwordHash,
            $inactiveStatusValue,
            $assignment['account_role']
        );
        if (!$accountStmt->execute()) {
            $error = $accountStmt->error;
            $accountStmt->close();
            throw new RuntimeException('Failed to create incoming official account: ' . $error);
        }
        $accountStmt->close();

        $officialId = GenerateOfficialPersonnelID($conn, $userId);
        if ($officialId === false || trim((string)$officialId) === '') {
            throw new RuntimeException('Failed to generate the incoming official ID.');
        }

        $lastname = trim((string)($candidate['candidate_last_name'] ?? ''));
        $firstname = trim((string)($candidate['candidate_first_name'] ?? ''));
        $middlename = trim((string)($candidate['candidate_middle_name'] ?? ''));
        $suffix = trim((string)($candidate['candidate_suffix'] ?? ''));
        $effectiveDate = trim((string)($transition['effective_date'] ?? '')) ?: date('Y-m-d');
        $officialIdentity = pii_encrypt_field_map([
            'lastname' => $lastname,
            'firstname' => $firstname,
            'middlename' => $middlename,
            'suffix' => $suffix,
            'birthdate' => '1900-01-01',
            'sex' => 'Other',
            'civil_status' => 'Single',
            'contact_number' => $phone10,
            'email' => $email,
        ]);

        $officialStmt = $conn->prepare("
            INSERT INTO officialinformationtbl
                (official_id, user_id, lastname, firstname, middlename, suffix, birthdate, sex, civil_status, contact_number, email,
                 area_access, department, selection_method, term_start, term_end, batch_label, area_number,
                 role_access, position_access, status_id_employment, date_hired)
            VALUES
                (?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?, ?, ?,
                 ?, ?, NULLIF(?, ''), ?, NULL, NULLIF(?, ''), NULLIF(?, ''),
                 ?, NULLIF(?, ''), ?, ?)
        ");
        if (!$officialStmt) {
            throw new RuntimeException('Failed to prepare incoming official profile.');
        }
        $selectionMethod = (string)$assignment['selection_method'];
        $batchLabel = trim((string)($transition['batch_label'] ?? ''));
        $officialStmt->bind_param(
            'ssssssssssssssssssssis',
            $officialId,
            $userId,
            $officialIdentity['lastname'],
            $officialIdentity['firstname'],
            $officialIdentity['middlename'],
            $officialIdentity['suffix'],
            $officialIdentity['birthdate'],
            $officialIdentity['sex'],
            $officialIdentity['civil_status'],
            $officialIdentity['contact_number'],
            $officialIdentity['email'],
            $assignment['area_access'],
            $assignment['department'],
            $selectionMethod,
            $effectiveDate,
            $batchLabel,
            $assignment['area_number'],
            $assignment['official_role'],
            $assignment['position_access'],
            $employmentStatusId,
            $effectiveDate
        );
        if (!$officialStmt->execute()) {
            $error = $officialStmt->error;
            $officialStmt->close();
            throw new RuntimeException('Failed to create incoming official profile: ' . $error);
        }
        $officialStmt->close();

        ogw_sync_profile_workflow($conn, $officialId);

        return [
            'official_id' => $officialId,
            'user_id' => $userId,
        ];
    }
}

if ($action === 'request_secure_action_otp') {
    $secureAction = trim((string)($_POST['secure_action'] ?? ''));
    otSecureDebugLog('request_secure_action_otp:start', [
        'actor_id' => $actorId,
        'secure_action' => $secureAction,
        'transition_id' => trim((string)($_POST['transition_id'] ?? '')),
        'official_id' => trim((string)($_POST['official_id'] ?? $_POST['acting_official_id'] ?? '')),
        'batch_label' => trim((string)($_POST['batch_label'] ?? '')),
        'has_actor_password' => trim((string)($_POST['actor_password'] ?? '')) !== '',
    ]);
    $allowedSecureActions = [
        'complete_transition',
        'cancel_transition',
        'delete_schedule',
        'restore_access',
        'change_credentials',
        'end_acting',
        'demote_official',
        'demote_batch',
    ];
    if (!in_array($secureAction, $allowedSecureActions, true)) {
        otError('This action cannot use secure confirmation.');
    }

    $targetLabel = ucwords(str_replace('_', ' ', $secureAction));
    $transitionId = trim((string)($_POST['transition_id'] ?? ''));
    $officialId = trim((string)($_POST['official_id'] ?? $_POST['acting_official_id'] ?? ''));
    $batchLabel = trim((string)($_POST['batch_label'] ?? ''));

    if ($transitionId !== '') {
        $transition = ot_governance_fetch_transition($conn, $transitionId);
        if ($transition) {
            $targetLabel = trim((string)($transition['seat_name'] ?? 'transition')) . ' (' . $transitionId . ')';
        }
    } elseif ($officialId !== '') {
        $official = otGetOfficialUser($conn, $officialId);
        if ($official) {
            $targetLabel = trim(otFormatOfficialName($official, true));
        }
    } elseif ($batchLabel !== '') {
        $targetLabel = 'governance cycle ' . $batchLabel;
    }

    try {
        $challenge = ogw_issue_secure_action_otp(
            $conn,
            $actorId,
            (string)($_POST['actor_password'] ?? ''),
            $otSecureModuleKey,
            $secureAction,
            $targetLabel
        );
    } catch (Throwable $e) {
        otSecureDebugLog('request_secure_action_otp:error', [
            'actor_id' => $actorId,
            'secure_action' => $secureAction,
            'error' => $e->getMessage(),
        ]);
        otError($e->getMessage());
    }

    otSecureDebugLog('request_secure_action_otp:success', [
        'actor_id' => $actorId,
        'secure_action' => $secureAction,
        'delivery_label' => (string)($challenge['delivery_label'] ?? ''),
        'challenge_key_prefix' => substr((string)($challenge['challenge_key'] ?? ''), 0, 8),
        'used_preview_fallback' => (string)($challenge['otp_preview'] ?? '') !== '',
    ]);

    otJson([
        'success' => true,
        'message' => 'OTP sent to ' . ($challenge['delivery_label'] !== '' ? $challenge['delivery_label'] : 'your verified contact') . '.',
        'challenge_key' => $challenge['challenge_key'],
        'expires_at' => $challenge['expires_at'],
        'delivery_label' => $challenge['delivery_label'],
        'otp_preview' => (string)($challenge['otp_preview'] ?? ''),
        'delivery_warning' => (string)($challenge['delivery_warning'] ?? ''),
    ]);
}

if ($action === 'fetch_transitions') {
    $q      = trim((string)($_GET['q'] ?? ''));
    $type   = trim((string)($_GET['type'] ?? ''));
    $limit  = min(max((int)($_GET['limit'] ?? 100), 1), 500);
    $offset = max((int)($_GET['offset'] ?? 0), 0);

    $where = ["status NOT IN ('Cancelled')"];
    $params = [];
    $types = '';
    if ($type !== '') {
        $where[] = 'transition_type = ?';
        $params[] = $type;
        $types .= 's';
    }
    $sql = "
        SELECT t.transition_id,
               t.transition_type,
               t.seat_name AS position,
               t.batch_label,
               t.effective_date,
               t.status,
               t.reason,
               t.acting_until_date,
               t.department,
               t.area_number,
               t.outgoing_official_id,
               oi.firstname,
               oi.lastname,
               oi.middlename,
               oi.suffix,
               COALESCE(oi.position_access, oi.role_access) AS outgoing_position
        FROM officialgovernancetransitiontbl t
        LEFT JOIN officialinformationtbl oi ON oi.official_id = t.outgoing_official_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY t.created_at DESC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        otError('Query prepare failed: ' . $conn->error);
    }
    if ($types !== '') {
        $refs = [$types];
        foreach ($params as $k => $v) {
            $refs[] = &$params[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $filteredRows = [];
    while ($row = $res->fetch_assoc()) {
        $row = otDecryptOfficialContactRow($row);
        $row['outgoing_name'] = otFormatOfficialName($row, true);
        $row['is_acting'] = 0;
        if ($q !== '' && !pii_search_match($row, ['transition_id', 'position', 'batch_label', 'outgoing_name'], $q)) {
            continue;
        }
        $filteredRows[] = $row;
    }
    $stmt->close();
    $total = count($filteredRows);
    $rows = array_slice($filteredRows, $offset, $limit);
    otJson(['success' => true, 'data' => $rows, 'total' => $total]);
}

if ($action === 'fetch_candidates') {
    $transitionId = trim((string)($_GET['transition_id'] ?? ''));
    if ($transitionId === '') {
        otError('Missing transition_id.');
    }
    $transition = ot_governance_fetch_transition($conn, $transitionId);
    if (!$transition) {
        otJson(['success' => true, 'candidates' => [], 'transition' => null]);
    }
    $transition['position'] = (string)($transition['seat_name'] ?? '');
    if (!empty($transition['outgoing_official_id'])) {
        $outgoing = otGetOfficialUser($conn, (string)$transition['outgoing_official_id']);
        if ($outgoing) {
            $transition['firstname'] = $outgoing['firstname'] ?? '';
            $transition['lastname'] = $outgoing['lastname'] ?? '';
            $transition['middlename'] = $outgoing['middlename'] ?? '';
            $transition['suffix'] = $outgoing['suffix'] ?? '';
            $transition['outgoing_name'] = otFormatOfficialName($outgoing, true);
            $transition['outgoing_position'] = (string)($outgoing['position'] ?? '');
        }
    }
    otJson(['success' => true, 'candidates' => [], 'transition' => $transition]);
}

if ($action === 'new_transition') {
    $councilId = (int)($_POST['council_id'] ?? 0);
    $transType = trim((string)($_POST['transition_type'] ?? ''));
    $effectiveDate = trim((string)($_POST['effective_date'] ?? ''));
    $reason = trim((string)($_POST['reason'] ?? ''));
    $batchLabel = trim((string)($_POST['batch_label'] ?? ''));
    if ($councilId <= 0) otError('Council seat is required.');
    if ($transType === '') otError('Transition type is required.');
    if ($effectiveDate === '') {
        $effectiveDate = date('Y-m-d');
    }
    $seatStmt = $conn->prepare("
        SELECT bc.council_id, bc.seat_name, bc.current_official_id, oi.department, oi.area_number
        FROM barangaycounciltbl bc
        LEFT JOIN officialinformationtbl oi ON oi.official_id = bc.current_official_id
        WHERE bc.council_id = ? AND bc.is_active = 1
        LIMIT 1
    ");
    if (!$seatStmt) otError('Failed to load seat.');
    $seatStmt->bind_param('i', $councilId);
    $seatStmt->execute();
    $seat = $seatStmt->get_result()->fetch_assoc();
    $seatStmt->close();
    if (!$seat) otError('Council seat not found or inactive.');

    $transitionId = ogw_generate_transition_id();
    $stmt = $conn->prepare("
        INSERT INTO officialgovernancetransitiontbl
            (transition_id, council_id, batch_label, transition_type, seat_name, department, area_number, outgoing_official_id, effective_date, reason, status, created_by_user_id)
        VALUES (?, ?, NULLIF(?, ''), ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), 'PendingSuperAdminApproval', ?)
    ");
    if (!$stmt) otError('Insert failed: ' . $conn->error);
    $seatName = (string)($seat['seat_name'] ?? '');
    $department = (string)($seat['department'] ?? '');
    $areaNumber = (string)($seat['area_number'] ?? '');
    $outgoingId = (string)($seat['current_official_id'] ?? '');
    $stmt->bind_param('sisssssssss', $transitionId, $councilId, $batchLabel, $transType, $seatName, $department, $areaNumber, $outgoingId, $effectiveDate, $reason, $actorId);
    if (!$stmt->execute()) otError('Failed to create transition: ' . $stmt->error);
    $stmt->close();
    insertUnifiedAuditLog($conn, $actorId, $actorRole, 'Official Transition', 'transition', $transitionId, 'create_transition', 'transition_type', null, $transType, 'Created transition draft.');
    otJson(['success' => true, 'message' => 'Transition created.', 'transition_id' => $transitionId]);
}

if ($action === 'new_batch') {
    $batchLabel = trim((string)($_POST['batch_label'] ?? ''));
    if ($batchLabel === '') otError('Governance cycle label is required.');
    $effectiveDate = trim((string)($_POST['effective_date'] ?? ''));
    if ($effectiveDate === '') {
        $effectiveDate = date('Y-m-d');
    }
    $seatRes = $conn->query("
        SELECT bc.council_id, bc.seat_name, bc.current_official_id, oi.department, oi.area_number
        FROM barangaycounciltbl bc
        LEFT JOIN officialinformationtbl oi ON oi.official_id = bc.current_official_id
        WHERE bc.is_active = 1
          AND bc.selection_method = 'Elected'
        ORDER BY bc.sort_order, bc.council_id
    ");
    if (!($seatRes instanceof mysqli_result)) otError('Failed to load elected seats.');
    $created = [];
    while ($seat = $seatRes->fetch_assoc()) {
        $transitionId = ogw_generate_transition_id();
        $transitionType = strtolower(trim((string)($seat['seat_name'] ?? ''))) === 'sk chairperson' ? 'SKElection' : 'BarangayElection';
        $stmt = $conn->prepare("
            INSERT INTO officialgovernancetransitiontbl
                (transition_id, council_id, batch_label, transition_type, seat_name, department, area_number, outgoing_official_id, effective_date, status, created_by_user_id)
            VALUES (?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?, 'PendingSuperAdminApproval', ?)
        ");
        if (!$stmt) otError('Insert failed.');
        $seatName = (string)($seat['seat_name'] ?? '');
        $department = (string)($seat['department'] ?? '');
        $areaNumber = (string)($seat['area_number'] ?? '');
        $outgoingId = (string)($seat['current_official_id'] ?? '');
        $stmt->bind_param('sissssssss', $transitionId, $seat['council_id'], $batchLabel, $transitionType, $seatName, $department, $areaNumber, $outgoingId, $effectiveDate, $actorId);
        if ($stmt->execute()) {
            $created[] = $transitionId;
        }
        $stmt->close();
    }
    insertUnifiedAuditLog($conn, $actorId, $actorRole, 'Official Transition', 'batch', $batchLabel, 'create_batch', 'count', null, (string)count($created), 'Created governance cycle.');
    otJson(['success' => true, 'message' => count($created) . ' transitions created for the governance cycle.', 'created' => $created]);
}

if ($action === 'update_election_date') {
    $batchLabel = trim((string)($_POST['original_batch_label'] ?? $_POST['batch_label'] ?? ''));
    $newBatchLabel = trim((string)($_POST['batch_label'] ?? ''));
    if ($batchLabel === '' || $newBatchLabel === '') otError('Governance cycle label is required.');
    $stmt = $conn->prepare("
        UPDATE officialgovernancetransitiontbl
        SET batch_label = ?,
            updated_at = NOW()
        WHERE batch_label = ?
    ");
    if (!$stmt) otError('Update failed.');
    $stmt->bind_param('ss', $newBatchLabel, $batchLabel);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    otJson(['success' => true, 'message' => "Governance cycle label updated for {$affected} transition(s)."]);
}

if ($action === 'delete_schedule') {
    otRequireSecureChallenge($conn, $actorId, $otSecureModuleKey, 'delete_schedule');
    $batchLabel = trim((string)($_POST['batch_label'] ?? ''));
    if ($batchLabel === '') otError('Governance cycle label is required.');
    $stmt = $conn->prepare("
        DELETE FROM officialgovernancetransitiontbl
        WHERE batch_label = ?
    ");
    if (!$stmt) otError('Delete failed.');
    $stmt->bind_param('s', $batchLabel);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();
    otJson(['success' => true, 'message' => "Deleted {$deleted} transition(s) from the schedule."]);
}

if ($action === 'complete_transition') {
    otRequireSecureChallenge($conn, $actorId, $otSecureModuleKey, 'complete_transition');
    $transitionId = trim((string)($_POST['transition_id'] ?? ''));
    $outcome = trim((string)($_POST['outcome'] ?? ''));
    $linkedOfficialId = trim((string)($_POST['linked_official_id'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $candidate = [
        'candidate_first_name' => trim((string)($_POST['candidate_first_name'] ?? '')),
        'candidate_last_name' => trim((string)($_POST['candidate_last_name'] ?? '')),
        'candidate_middle_name' => trim((string)($_POST['candidate_middle_name'] ?? '')),
        'candidate_suffix' => trim((string)($_POST['candidate_suffix'] ?? '')),
        'candidate_email' => strtolower(trim((string)($_POST['candidate_email'] ?? ''))),
        'candidate_mobile' => preg_replace('/[^0-9]/', '', (string)($_POST['candidate_mobile'] ?? '')),
    ];
    if ($transitionId === '' || $outcome === '') otError('Transition and outcome are required.');
    $transition = ot_governance_fetch_transition($conn, $transitionId);
    if (!$transition) otError('Transition not found.');

    $inactiveStatusId = otGetStatusId($conn, 'UserAccount', 'Inactive');
    $activeStatusId = otGetStatusId($conn, 'UserAccount', 'Active');
    if ($inactiveStatusId === null || $activeStatusId === null) {
        otError('Active or inactive account status is missing.');
    }

    $incomingOfficialId = '';
    $incomingUserId = '';
    $outgoingId = (string)($transition['outgoing_official_id'] ?? '');
    $councilId = (int)($transition['council_id'] ?? 0);
    $inviteResponse = [
        'invite_link' => '',
        'invite_email_sent' => null,
        'invite_email_error' => '',
        'invite_sms_sent' => null,
        'invite_sms_error' => '',
    ];

    $conn->begin_transaction();
    try {
        if ($outgoingId !== '' && $outcome !== 'ReElected') {
            $outgoing = otGetOfficialUser($conn, $outgoingId);
            if ($outgoing && !empty($outgoing['user_id'])) {
                $upAcct = $conn->prepare("UPDATE useraccountstbl SET status_id_account = ?, updated_at = NOW() WHERE user_id = ? LIMIT 1");
                if ($upAcct) {
                    $upAcct->bind_param('is', $inactiveStatusId, $outgoing['user_id']);
                    $upAcct->execute();
                    $upAcct->close();
                }
            }
        }

        if ($outcome === 'ReElected') {
            $incomingOfficialId = $outgoingId;
            $existing = $incomingOfficialId !== '' ? otGetOfficialUser($conn, $incomingOfficialId) : null;
            $incomingUserId = (string)($existing['user_id'] ?? '');
        } elseif ($outcome !== 'NoSuccessor') {
            if ($linkedOfficialId !== '') {
                $existing = otGetOfficialUser($conn, $linkedOfficialId);
                if (!$existing) {
                    throw new RuntimeException('Linked official record could not be found.');
                }
                $incomingOfficialId = $linkedOfficialId;
                $incomingUserId = (string)($existing['user_id'] ?? '');
            } else {
                if ($candidate['candidate_first_name'] === '' || $candidate['candidate_last_name'] === '' || $candidate['candidate_email'] === '' || $candidate['candidate_mobile'] === '') {
                    throw new RuntimeException('Complete the incoming official name and contact details first.');
                }
                $shell = ot_governance_create_shell($conn, $transition, $candidate, $actorId, (int)$inactiveStatusId);
                $incomingOfficialId = (string)($shell['official_id'] ?? '');
                $incomingUserId = (string)($shell['user_id'] ?? '');
            }

            $assignment = otResolveIncomingAccessProfile([
                'position' => (string)($transition['seat_name'] ?? ''),
                'department' => (string)($transition['department'] ?? ''),
                'area_number' => (string)($transition['area_number'] ?? ''),
                'transition_type' => (string)($transition['transition_type'] ?? ''),
            ]);
            $effectiveDate = trim((string)($transition['effective_date'] ?? '')) ?: date('Y-m-d');
            $upOfficial = $conn->prepare("
                UPDATE officialinformationtbl
                SET role_access = ?,
                    position_access = ?,
                    department = ?,
                    area_number = ?,
                    selection_method = ?,
                    term_start = ?,
                    batch_label = NULLIF(?, ''),
                    last_updated = CURRENT_TIMESTAMP
                WHERE official_id = ?
                LIMIT 1
            ");
            if ($upOfficial) {
                $batchLabel = trim((string)($transition['batch_label'] ?? ''));
                $upOfficial->bind_param('ssssssss', $assignment['official_role'], $assignment['position_access'], $assignment['department'], $assignment['area_number'], $assignment['selection_method'], $effectiveDate, $batchLabel, $incomingOfficialId);
                $upOfficial->execute();
                $upOfficial->close();
            }
            $upUser = $conn->prepare("UPDATE useraccountstbl SET role_access = ?, status_id_account = ?, updated_at = NOW() WHERE user_id = ? LIMIT 1");
            if ($upUser) {
                $statusToUse = $linkedOfficialId !== '' ? $activeStatusId : $inactiveStatusId;
                $upUser->bind_param('sis', $assignment['account_role'], $statusToUse, $incomingUserId);
                $upUser->execute();
                $upUser->close();
            }
            amp_upsert_official_access_profile($conn, $incomingOfficialId, $incomingUserId);
            $upProfile = $conn->prepare("
                UPDATE officialaccessprofiletbl
                SET display_role = ?,
                    area_assignee_access = NULLIF(?, ''),
                    area_coverage_access = NULLIF(?, ''),
                    access_status = 'PendingAccessApproval',
                    reviewed_by_user_id = ?,
                    reviewed_at = NOW()
                WHERE official_id = ?
                LIMIT 1
            ");
            if ($upProfile) {
                $coverage = trim((string)($assignment['area_number'] ?? ''));
                $upProfile->bind_param('sssss', $assignment['account_role'], $assignment['area_number'], $coverage, $actorId, $incomingOfficialId);
                $upProfile->execute();
                $upProfile->close();
            }
            ogw_sync_profile_workflow($conn, $incomingOfficialId);
        }

        if ($councilId > 0) {
            $seatStmt = $conn->prepare("
                UPDATE barangaycounciltbl
                SET current_official_id = NULLIF(?, ''),
                    term_start = COALESCE(NULLIF(?, ''), term_start),
                    updated_at = NOW()
                WHERE council_id = ?
                LIMIT 1
            ");
            if ($seatStmt) {
                $effectiveDate = trim((string)($transition['effective_date'] ?? '')) ?: date('Y-m-d');
                $seatStmt->bind_param('ssi', $incomingOfficialId, $effectiveDate, $councilId);
                $seatStmt->execute();
                $seatStmt->close();
            }
        }

        $completeStmt = $conn->prepare("
            UPDATE officialgovernancetransitiontbl
            SET incoming_official_id = NULLIF(?, ''),
                notes = NULLIF(?, ''),
                status = 'Completed',
                account_action = CASE WHEN ? = 'NoSuccessor' THEN 'DemotedOnly' ELSE 'DemotedAndReassigned' END,
                access_action = CASE WHEN ? = 'NoSuccessor' THEN 'NoIncomingAccess' ELSE 'PendingAccessReview' END,
                approved_by_user_id = ?,
                approved_at = NOW(),
                completed_at = NOW(),
                updated_at = NOW()
            WHERE transition_id = ?
            LIMIT 1
        ");
        if (!$completeStmt) {
            throw new RuntimeException('Failed to finalize transition.');
        }
        $completeStmt->bind_param('ssssss', $incomingOfficialId, $notes, $outcome, $outcome, $actorId, $transitionId);
        $completeStmt->execute();
        $completeStmt->close();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        otError($e->getMessage());
    }

    $shouldSendOnboardingInvite = in_array($outcome, ['NewPerson', 'Reactivated'], true) && $incomingOfficialId !== '';
    if ($shouldSendOnboardingInvite) {
        try {
            $incomingOfficial = otGetOfficialUser($conn, $incomingOfficialId);
            if (!$incomingOfficial) {
                throw new RuntimeException('Incoming official record could not be loaded for onboarding invite delivery.');
            }

            $invite = otCreateOfficialInvite($conn, [
                'email' => (string)($incomingOfficial['email'] ?? ''),
                'phone_number' => (string)($incomingOfficial['phone_number'] ?? ''),
                'firstname' => (string)($incomingOfficial['firstname'] ?? ''),
                'middlename' => (string)($incomingOfficial['middlename'] ?? ''),
                'lastname' => (string)($incomingOfficial['lastname'] ?? ''),
                'suffix' => (string)($incomingOfficial['suffix'] ?? ''),
                'role_access' => (string)($incomingOfficial['ua_role'] ?? 'Official'),
                'position_access' => (string)($incomingOfficial['position'] ?? ''),
                'department' => (string)($incomingOfficial['department'] ?? ''),
                'employment_status' => (string)($incomingOfficial['employment_status'] ?? 'Regular'),
                'area_number' => (string)($incomingOfficial['area_number'] ?? ''),
                'user_id' => (string)($incomingOfficial['user_id'] ?? ''),
            ], $actorId);

            $delivery = otDeliverOnboardingInvite($invite);
            ogw_sync_profile_workflow($conn, $incomingOfficialId);

            $inviteResponse['invite_link'] = (string)($invite['invite_link'] ?? '');
            $inviteResponse['invite_email_sent'] = (bool)($delivery['email_sent'] ?? false);
            $inviteResponse['invite_email_error'] = trim((string)($delivery['email_error'] ?? ''));
            $inviteResponse['invite_sms_sent'] = (bool)($delivery['sms_sent'] ?? false);
            $inviteResponse['invite_sms_error'] = trim((string)($delivery['sms_error'] ?? ''));
        } catch (Throwable $e) {
            $inviteResponse['invite_email_sent'] = false;
            $inviteResponse['invite_sms_sent'] = false;
            $inviteResponse['invite_email_error'] = $e->getMessage();
            $inviteResponse['invite_sms_error'] = $e->getMessage();
        }
    }

    insertUnifiedAuditLog($conn, $actorId, $actorRole, 'Official Transition', 'transition', $transitionId, 'complete_transition', 'status', 'PendingSuperAdminApproval', 'Completed', 'Completed transition workflow.');
    otJson([
        'success' => true,
        'message' => 'Transition completed successfully. Incoming access stays pending until Access Control approves it.',
        'invite_link' => $inviteResponse['invite_link'],
        'invite_email_sent' => $inviteResponse['invite_email_sent'],
        'invite_email_error' => $inviteResponse['invite_email_error'],
        'invite_sms_sent' => $inviteResponse['invite_sms_sent'],
        'invite_sms_error' => $inviteResponse['invite_sms_error'],
    ]);
}

if ($action === 'fetch_inactive_officials') {
    $q = trim((string)($_GET['q'] ?? ''));
    $sql = "
        SELECT oi.official_id,
               oi.firstname,
               oi.lastname,
               oi.middlename,
               oi.suffix,
               COALESCE(oi.position_access, oi.role_access) AS position,
               oi.department,
               ua.email,
               ua.phone_number,
               COALESCE(sa.status_name,'') AS account_status,
               COALESCE(se.status_name,'') AS employment_status,
               oi.transition_out_type,
               oi.transition_out_date,
               oi.can_return
        FROM officialinformationtbl oi
        INNER JOIN useraccountstbl ua ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
        LEFT JOIN statuslookuptbl sa ON sa.status_id = ua.status_id_account
        LEFT JOIN statuslookuptbl se ON se.status_id = oi.status_id_employment
        WHERE sa.status_name IN ('Inactive','Suspended','Revoked','Disabled')
        ORDER BY oi.lastname, oi.firstname
        LIMIT 100
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        otError('Query failed.');
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $row = otDecryptOfficialContactRow($row);
        $row['full_name'] = otFormatOfficialName($row, true);
        if ($q !== '' && !pii_search_match($row, ['official_id', 'full_name', 'position', 'department', 'email', 'phone_number'], $q)) {
            continue;
        }
        $rows[] = $row;
    }
    $stmt->close();
    otJson(['success' => true, 'data' => $rows]);
}


// ════════════════════════════════════════════════════════════════════════════
// POST: cancel transition
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'cancel_transition') {
    otRequireSecureChallenge($conn, $actorId, $otSecureModuleKey, 'cancel_transition');
    $transitionId = trim((string)($_POST['transition_id'] ?? ''));
    $reason       = trim((string)($_POST['reason']        ?? ''));
    if ($transitionId === '') otError('Missing transition_id.');

    $stmt = $conn->prepare("
        UPDATE officialgovernancetransitiontbl
        SET status = 'Cancelled',
            notes = CONCAT(COALESCE(notes,''), CASE WHEN COALESCE(notes,'') = '' THEN '' ELSE '\n' END, '[Cancelled: ', ?, ' at ', NOW(), ']'),
            updated_at = NOW()
        WHERE transition_id = ?
          AND status <> 'Completed'
        LIMIT 1
    ");
    if (!$stmt) otError('Update failed.');
    $stmt->bind_param('ss', $reason, $transitionId);
    $stmt->execute();
    $stmt->close();

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'transition', $transitionId,
        'cancel_transition', 'status', null, 'Cancelled', $reason ?: null
    );

    otJson(['success' => true, 'message' => 'Transition cancelled.']);
}

if ($action === 'demote_official') {
    otRequireSecureChallenge($conn, $actorId, $otSecureModuleKey, 'demote_official');
    $officialId = trim((string)($_POST['official_id'] ?? ''));
    $reason = trim((string)($_POST['reason'] ?? ''));
    if ($officialId === '') {
        otError('Missing official_id.');
    }

    $inactiveStatusId = otGetStatusId($conn, 'UserAccount', 'Inactive');
    if ($inactiveStatusId === null) {
        otError('Inactive status not found.');
    }

    $conn->begin_transaction();
    try {
        $result = otDemoteOfficialRecord($conn, $officialId, $inactiveStatusId, $reason);
        $syncTransition = $conn->prepare("
            UPDATE officialgovernancetransitiontbl
            SET account_action = 'DemotedOnly',
                access_action = CASE
                    WHEN incoming_official_id IS NULL OR incoming_official_id = '' THEN 'NoIncomingAccess'
                    ELSE access_action
                END,
                updated_at = NOW()
            WHERE outgoing_official_id = ?
              AND status <> 'Cancelled'
        ");
        if ($syncTransition) {
            $syncTransition->bind_param('s', $officialId);
            $syncTransition->execute();
            $syncTransition->close();
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        otError($e->getMessage());
    }

    $official = (array)($result['official'] ?? []);
    $name = trim(otFormatOfficialName($official));
    insertUnifiedAuditLog(
        $conn,
        $actorId,
        $actorRole,
        'OfficialTransitions',
        'official',
        $officialId,
        'demote_official',
        'account_status',
        (string)($official['account_status_name'] ?? ''),
        'Inactive',
        $reason !== '' ? $reason : 'Seat removed from active assignment.'
    );

    otJson([
        'success' => true,
        'message' => ($name !== '' ? $name : 'Official') . ' was demoted and removed from the active seat assignment.',
    ]);
}

if ($action === 'demote_batch') {
    otRequireSecureChallenge($conn, $actorId, $otSecureModuleKey, 'demote_batch');
    $batchLabel = trim((string)($_POST['batch_label'] ?? ''));
    $reason = trim((string)($_POST['reason'] ?? ''));
    if ($batchLabel === '') {
        otError('Missing batch_label.');
    }

    $inactiveStatusId = otGetStatusId($conn, 'UserAccount', 'Inactive');
    if ($inactiveStatusId === null) {
        otError('Inactive status not found.');
    }

    $stmt = $conn->prepare("
        SELECT DISTINCT outgoing_official_id
        FROM officialgovernancetransitiontbl
        WHERE batch_label = ?
          AND outgoing_official_id IS NOT NULL
          AND outgoing_official_id <> ''
    ");
    if (!$stmt) {
        otError('Failed to load governance cycle officials.');
    }
    $stmt->bind_param('s', $batchLabel);
    $stmt->execute();
    $res = $stmt->get_result();
    $officialIds = [];
    while ($row = $res->fetch_assoc()) {
        $officialId = trim((string)($row['outgoing_official_id'] ?? ''));
        if ($officialId !== '') {
            $officialIds[] = $officialId;
        }
    }
    $stmt->close();

    if ($officialIds === []) {
        otError('No outgoing officials were found for this governance cycle.');
    }

    $demoted = [];
    $conn->begin_transaction();
    try {
        foreach ($officialIds as $officialId) {
            $result = otDemoteOfficialRecord($conn, $officialId, $inactiveStatusId, $reason !== '' ? $reason : ('Governance cycle demotion: ' . $batchLabel));
            $demoted[] = trim(otFormatOfficialName((array)($result['official'] ?? [])));
        }
        $syncBatch = $conn->prepare("
            UPDATE officialgovernancetransitiontbl
            SET account_action = 'DemotedOnly',
                access_action = CASE
                    WHEN incoming_official_id IS NULL OR incoming_official_id = '' THEN 'NoIncomingAccess'
                    ELSE access_action
                END,
                updated_at = NOW()
            WHERE batch_label = ?
              AND status <> 'Cancelled'
        ");
        if ($syncBatch) {
            $syncBatch->bind_param('s', $batchLabel);
            $syncBatch->execute();
            $syncBatch->close();
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        otError($e->getMessage());
    }

    insertUnifiedAuditLog(
        $conn,
        $actorId,
        $actorRole,
        'OfficialTransitions',
        'batch',
        $batchLabel,
        'demote_batch',
        'official_count',
        null,
        (string)count($demoted),
        $reason !== '' ? $reason : 'Election-period demote all.'
    );

    otJson([
        'success' => true,
        'message' => count($demoted) . ' outgoing official(s) were demoted for governance cycle ' . $batchLabel . '.',
        'demoted' => $demoted,
    ]);
}

// ════════════════════════════════════════════════════════════════════════════
// POST: restore access (Quick Action)
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'restore_access') {
    otRequireSecureChallenge($conn, $actorId, $otSecureModuleKey, 'restore_access');
    $officialId = trim((string)($_POST['official_id'] ?? ''));
    if ($officialId === '') otError('Missing official_id.');

    $official = otGetOfficialUser($conn, $officialId);
    if (!$official) otError('Official not found.');

    $activeStatusId = otGetStatusId($conn, 'UserAccount', 'Active');
    if (!$activeStatusId) otError('Active status not found in lookup table.');

    $userId = (string)($official['user_id'] ?? '');
    if ($userId === '') otError('No linked user account found.');

    $oldStatus = (string)($official['account_status_name'] ?? '');

    $stmt = $conn->prepare("UPDATE useraccountstbl SET status_id_account = ?, updated_at = NOW() WHERE user_id = ? LIMIT 1");
    if (!$stmt) otError('Update failed.');
    $stmt->bind_param('is', $activeStatusId, $userId);
    $stmt->execute();
    $stmt->close();

    // Clear acting fields if they were suspended as acting
    $conn->query("UPDATE officialinformationtbl SET acting_for_id = NULL, acting_until_date = NULL, transition_out_type = NULL, transition_out_date = NULL, transition_out_reason = NULL, last_updated = CURRENT_TIMESTAMP WHERE official_id = '{$conn->real_escape_string($officialId)}'");

    // Notify official
    $name = trim(($official['firstname'] ?? '') . ' ' . ($official['lastname'] ?? ''));
    otNotifyUser($conn, $userId,
        'System Access Restored',
        "Dear {$name},\n\nYour Barangay San Jose system account has been reactivated by the IT Administrator. Please log in and contact the IT Administrator if you need to update your credentials.\n\nBarangay San Jose"
    );

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'official', $officialId,
        'restore_access', 'account_status', $oldStatus, 'Active', 'Quick Action: restore access'
    );

    otJson(['success' => true, 'message' => "Access restored for {$name}."]);
}

// ════════════════════════════════════════════════════════════════════════════
// POST: change credentials (Quick Action)
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'change_credentials') {
    otRequireSecureChallenge($conn, $actorId, $otSecureModuleKey, 'change_credentials');
    $officialId        = trim((string)($_POST['official_id']          ?? ''));
    $newEmail          = trim((string)($_POST['email']                ?? ''));
    $newPhone          = trim((string)($_POST['phone']                ?? ''));
    $forcePasswordReset= (int)($_POST['force_password_reset']        ?? 0);

    if ($officialId === '') otError('Missing official_id.');

    $official = otGetOfficialUser($conn, $officialId);
    if (!$official) otError('Official not found.');

    $userId   = (string)($official['user_id'] ?? '');
    if ($userId === '') otError('No linked user account.');

    $finalEmail = $newEmail !== '' ? strtolower($newEmail) : strtolower(trim((string)($official['email'] ?? '')));
    $finalPhone = $newPhone !== '' ? oi_normalize_phone10($newPhone) : oi_normalize_phone10((string)($official['phone_number'] ?? ''));
    if ($newEmail !== '' || $newPhone !== '') {
        otAssertContactIsAvailable($conn, $finalEmail, $finalPhone, $userId);
    }

    $setParts = ['updated_at = NOW()'];
    $params   = [];
    $types    = '';
    $officialContactChanges = [];

    if ($newEmail !== '') {
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) otError('Invalid email address.');
        $setParts[] = 'email = ?';
        $setParts[] = 'email_lookup_hash = ?';
        $preparedEmail = pii_prepare_useraccount_contacts($newEmail, (string)($official['phone_number'] ?? ''));
        $params[]   = $preparedEmail['email'];
        $params[]   = $preparedEmail['email_lookup_hash'];
        $types     .= 's';
        $types     .= 's';
        $officialContactChanges['email'] = pii_encrypt_string($newEmail);
    }
    if ($newPhone !== '') {
        $normalizedPhone = oi_normalize_phone10($newPhone);
        if (!oi_is_valid_phone10($normalizedPhone)) otError('Invalid mobile number.');
        $setParts[] = 'phone_number = ?';
        $setParts[] = 'phone_lookup_hash = ?';
        $preparedPhone = pii_prepare_useraccount_contacts((string)($official['email'] ?? ''), $normalizedPhone);
        $params[]   = $preparedPhone['phone_number'];
        $params[]   = $preparedPhone['phone_lookup_hash'];
        $types     .= 's';
        $types     .= 's';
        $officialContactChanges['contact_number'] = pii_encrypt_string($normalizedPhone);
    }
    if ($forcePasswordReset) {
        // Set a flag or blank the password hash to force reset — implementation depends on auth flow
        $setParts[] = 'password_hash = NULL';
    }

    if (count($setParts) <= 1) otError('Nothing to update.');

    $params[] = $userId;
    $types   .= 's';
    $sql      = 'UPDATE useraccountstbl SET ' . implode(', ', $setParts) . ' WHERE user_id = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) otError('Update failed.');
    $refs = [$types];
    foreach ($params as $k => $v) $refs[] = &$params[$k];
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
    $stmt->close();

    if ($officialContactChanges !== []) {
        $officialSetParts = [];
        $officialParams = [];
        $officialTypes = '';
        foreach ($officialContactChanges as $field => $value) {
            $officialSetParts[] = $field . ' = ?';
            $officialParams[] = $value;
            $officialTypes .= 's';
        }
        $officialSetParts[] = 'last_updated = CURRENT_TIMESTAMP';
        $officialParams[] = $officialId;
        $officialTypes .= 's';
        $officialSql = 'UPDATE officialinformationtbl SET ' . implode(', ', $officialSetParts) . ' WHERE official_id = ? LIMIT 1';
        $officialStmt = $conn->prepare($officialSql);
        if ($officialStmt) {
            $officialRefs = [$officialTypes];
            foreach ($officialParams as $k => $value) {
                $officialRefs[] = &$officialParams[$k];
            }
            call_user_func_array([$officialStmt, 'bind_param'], $officialRefs);
            $officialStmt->execute();
            $officialStmt->close();
        }
    }

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'official', $officialId,
        'change_credentials', 'fields', null,
        implode(', ', array_filter([
            $newEmail ? 'email' : '',
            $newPhone ? 'phone' : '',
            $forcePasswordReset ? 'password_reset' : '',
        ])),
        'Quick Action: credential update'
    );

    otJson(['success' => true, 'message' => 'Credentials updated.']);
}

// ════════════════════════════════════════════════════════════════════════════
// POST: end acting assignment (Quick Action)
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'end_acting') {
    otRequireSecureChallenge($conn, $actorId, $otSecureModuleKey, 'end_acting');
    $actingOfficialId = trim((string)($_POST['acting_official_id']   ?? ''));
    if ($actingOfficialId === '') otError('Missing acting_official_id.');

    $actingOfficial = otGetOfficialUser($conn, $actingOfficialId);
    if (!$actingOfficial) otError('Acting official not found.');

    // Find the original official this one was covering for
    $actingForId = '';
    $aRes = $conn->query("SELECT acting_for_id FROM officialinformationtbl WHERE official_id = '{$conn->real_escape_string($actingOfficialId)}' LIMIT 1");
    if ($aRes instanceof mysqli_result) {
        $aRow = $aRes->fetch_assoc();
        $actingForId = trim((string)($aRow['acting_for_id'] ?? ''));
    }

    $inactiveStatusId = otGetStatusId($conn, 'UserAccount', 'Inactive');
    $activeStatusId   = otGetStatusId($conn, 'UserAccount', 'Active');

    $conn->begin_transaction();
    try {
        // Deactivate acting official
        $actUserId = (string)($actingOfficial['user_id'] ?? '');
        if ($inactiveStatusId && $actUserId !== '') {
            $stmt = $conn->prepare("UPDATE useraccountstbl SET status_id_account = ?, updated_at = NOW() WHERE user_id = ? LIMIT 1");
            if ($stmt) { $stmt->bind_param('is', $inactiveStatusId, $actUserId); $stmt->execute(); $stmt->close(); }
        }
        $conn->query("UPDATE officialinformationtbl SET acting_for_id = NULL, acting_until_date = NULL, transition_out_type = 'ActingEnded', transition_out_date = CURDATE(), last_updated = CURRENT_TIMESTAMP WHERE official_id = '{$conn->real_escape_string($actingOfficialId)}'");

        // Restore original official
        if ($actingForId !== '') {
            $originalOfficial = otGetOfficialUser($conn, $actingForId);
            if ($originalOfficial && $activeStatusId) {
                $origUserId = (string)($originalOfficial['user_id'] ?? '');
                $stmt = $conn->prepare("UPDATE useraccountstbl SET status_id_account = ?, updated_at = NOW() WHERE user_id = ? LIMIT 1");
                if ($stmt) { $stmt->bind_param('is', $activeStatusId, $origUserId); $stmt->execute(); $stmt->close(); }
                $conn->query("UPDATE officialinformationtbl SET transition_out_type = NULL, transition_out_date = NULL, transition_out_reason = NULL, last_updated = CURRENT_TIMESTAMP WHERE official_id = '{$conn->real_escape_string($actingForId)}'");
                // Notify original official
                $origName = trim(($originalOfficial['firstname'] ?? '') . ' ' . ($originalOfficial['lastname'] ?? ''));
                otNotifyUser($conn, $origUserId,
                    'System Access Restored — Acting Period Ended',
                    "Dear {$origName},\n\nThe acting assignment for your position has ended. Your system access has been restored. Please log in and contact the IT Administrator if you need assistance.\n\nBarangay San Jose"
                );
            }
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        otError($e->getMessage());
    }

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'official', $actingOfficialId,
        'end_acting', 'acting_for_id', $actingForId, null, 'Quick Action: end acting assignment'
    );

    otJson(['success' => true, 'message' => 'Acting assignment ended. Original official restored.']);
}

// ── Fallthrough ───────────────────────────────────────────────────────────────
otError('Unknown action.', 404);
