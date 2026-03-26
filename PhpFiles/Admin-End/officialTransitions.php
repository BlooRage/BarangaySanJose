<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/security.php';
require_once __DIR__ . '/../General/audit.php';
require_once __DIR__ . '/../General/adminModulePermissions.php';
require_once __DIR__ . '/../General/officialInviteCommon.php';
require_once __DIR__ . '/../General/uniqueIDGenerate.php';

requireRoleSession(['SuperAdmin']);
oi_ensure_invite_table($conn);
amp_ensure_permission_storage($conn);

header('Content-Type: application/json; charset=utf-8');

// ── Helpers ───────────────────────────────────────────────────────────────────

function otJson(array $payload, int $code = 200): never {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function otError(string $message, int $code = 400): never {
    otJson(['success' => false, 'message' => $message], $code);
}

function otGenerateTransitionId(mysqli $conn): string {
    $prefix = 'TRN-' . date('Ymd') . '-';
    $res = $conn->query("SELECT transition_id FROM officialtransitionstbl WHERE transition_id LIKE '{$prefix}%' ORDER BY transition_id DESC LIMIT 1");
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

function otSendOnboardingInviteEmail(string $email, string $fullName, string $roleName, string $inviteLink): bool
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    try {
        require_once __DIR__ . '/../EmailHandlers/emailSender.php';
        if (!class_exists('EmailSender')) {
            return false;
        }
        $mailConfig = require __DIR__ . '/../General/mailConfigurations.php';
        $sender = new EmailSender($mailConfig);
        return $sender->send([
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
    } catch (\Throwable) {
        return false;
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
    if ($done || !otTableExists($conn, 'officialtransitionstbl')) {
        return;
    }
    $done = true;

    $columnDefinitions = [
        'council_id' => "ALTER TABLE officialtransitionstbl ADD COLUMN council_id INT DEFAULT NULL AFTER transition_id",
        'proclamation_date' => "ALTER TABLE officialtransitionstbl ADD COLUMN proclamation_date DATE DEFAULT NULL AFTER batch_label",
        'next_election_date' => "ALTER TABLE officialtransitionstbl ADD COLUMN next_election_date DATE DEFAULT NULL AFTER proclamation_date",
    ];

    foreach ($columnDefinitions as $column => $sql) {
        if (!otColumnExists($conn, 'officialtransitionstbl', $column)) {
            $conn->query($sql);
        }
    }
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
    if (!otTableExists($conn, 'officialtransitionstbl')) {
        $cached = false;
        return $cached;
    }

    $ignoredPositionSql = otIgnoredTransitionSeatSql($conn, 'position');
    $res = $conn->query("
        SELECT 1
        FROM officialtransitionstbl
        WHERE batch_label IS NOT NULL
          AND COALESCE(next_election_date, election_date) IS NOT NULL
          AND {$ignoredPositionSql}
        LIMIT 1
    ");
    $cached = $res instanceof mysqli_result && $res->num_rows > 0;
    if ($res instanceof mysqli_result) {
        $res->close();
    }
    return $cached;
}

if (otTableExists($conn, 'officialtransitionstbl')) {
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
        (string)($transition['proclamation_date'] ?? ''),
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
    $candidateDates = [
        (string)($transition['next_election_date'] ?? ''),
        (string)($transition['election_date'] ?? ''),
    ];
    foreach ($candidateDates as $candidateDate) {
        $normalized = otNormalizeDateOrNull($candidateDate);
        if ($normalized !== null) {
            return $normalized;
        }
    }
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
    $prepared = pii_prepare_useraccount_contacts($email, $phone10);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('A valid email address is required.');
    }
    if (!oi_is_valid_phone10($phone10)) {
        throw new RuntimeException('Mobile number must be a valid 10-digit Philippine mobile number.');
    }

    if ($excludeUserId !== '') {
        $stmt = $conn->prepare("
            SELECT user_id
            FROM useraccountstbl
            WHERE (email_lookup_hash = ? OR phone_lookup_hash = ?)
              AND user_id <> ?
            LIMIT 1
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to validate email and mobile number.');
        }
        $stmt->bind_param('sss', $prepared['email_lookup_hash'], $prepared['phone_lookup_hash'], $excludeUserId);
    } else {
        $stmt = $conn->prepare("
            SELECT user_id
            FROM useraccountstbl
            WHERE email_lookup_hash = ? OR phone_lookup_hash = ?
            LIMIT 1
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to validate email and mobile number.');
        }
        $stmt->bind_param('ss', $prepared['email_lookup_hash'], $prepared['phone_lookup_hash']);
    }

    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        throw new RuntimeException('Email or mobile number is already tied to another account.');
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
    $stmt->bind_param(
        'ssssssssssssssssss',
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
    );
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

    return [
        'email_sent' => $inviteLink !== '' && otSendOnboardingInviteEmail(
            $email,
            $fullName,
            $roleName !== '' ? $roleName : 'Official',
            $inviteLink
        ),
        'sms_sent' => $phone10 !== '' && otSendSMS($phone10, 'Barangay San Jose: Your official onboarding access is ready. Please check your email for the one-time invite link.'),
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
if ($action === 'fetch_transitions') {
    if (!otTableExists($conn, 'officialtransitionstbl')) {
        otJson(['success' => true, 'data' => [], 'total' => 0, 'notice' => 'Migration not yet applied.']);
    }
    $q      = trim((string)($_GET['q']      ?? ''));
    $type   = trim((string)($_GET['type']   ?? ''));
    $limit  = min(max((int)($_GET['limit'] ?? 100), 1), 500);
    $offset = max((int)($_GET['offset'] ?? 0), 0);

    $where = [
        "t.status NOT IN ('Completed','Cancelled')",
        otIgnoredTransitionSeatSql($conn, 't.position'),
    ];
    $params = [];
    $types  = '';

    if ($type !== '') {
        $where[] = "t.transition_type = ?";
        $params[] = $type;
        $types .= 's';
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);
    $sql = "
        SELECT t.*,
               COALESCE(t.proclamation_date, t.effective_date) AS proclamation_date,
               COALESCE(t.next_election_date, t.election_date) AS next_election_date,
               bc.sort_order,
               oi.firstname,
               oi.lastname,
               oi.middlename,
               oi.suffix,
               COALESCE(oi.position_access, oi.role_access) AS outgoing_position
        FROM officialtransitionstbl t
        LEFT JOIN officialinformationtbl oi ON oi.official_id = t.outgoing_official_id
        LEFT JOIN barangaycounciltbl bc ON bc.council_id = t.council_id
        {$whereClause}
        ORDER BY COALESCE(bc.sort_order, 9999) ASC, t.created_at DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) otError('Query prepare failed: ' . $conn->error);
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

// ════════════════════════════════════════════════════════════════════════════
// FETCH: inactive/suspended officials (for Restore Access)
// ════════════════════════════════════════════════════════════════════════════
// (no transition table needed — queries officialinformationtbl directly)
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
    ";
    $sql .= " ORDER BY oi.lastname, oi.firstname LIMIT 100";

    $stmt = $conn->prepare($sql);
    if (!$stmt) otError('Query failed.');
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
    if ($q !== '') {
        $rows = array_slice($rows, 0, 100);
    }
    otJson(['success' => true, 'data' => $rows]);
}

// ════════════════════════════════════════════════════════════════════════════
// FETCH: transition details for access setup
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'fetch_candidates') {
    if (!otTableExists($conn, 'officialtransitionstbl')) {
        otJson(['success' => true, 'candidates' => [], 'transition' => null]);
    }
    $transitionId = trim((string)($_GET['transition_id'] ?? ''));
    if ($transitionId === '') otError('Missing transition_id.');

    $tStmt = $conn->prepare("
        SELECT t.*,
               oi.firstname,
               oi.lastname,
               oi.middlename,
               oi.suffix,
               COALESCE(oi.position_access,oi.role_access) AS outgoing_position
        FROM officialtransitionstbl t
        LEFT JOIN officialinformationtbl oi ON oi.official_id = t.outgoing_official_id
        WHERE t.transition_id = ?
        LIMIT 1
    ");
    $transition = null;
    if ($tStmt) {
        $tStmt->bind_param('s', $transitionId);
        $tStmt->execute();
        $transition = $tStmt->get_result()->fetch_assoc();
        $tStmt->close();
        if ($transition) {
            $transition = otDecryptOfficialContactRow($transition);
            $transition['outgoing_name'] = otFormatOfficialName($transition, true);
        }
    }

    otJson(['success' => true, 'candidates' => [], 'transition' => $transition]);
}

// ════════════════════════════════════════════════════════════════════════════
// POST: create individual transition
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'new_transition') {
    if (!otTableExists($conn, 'officialtransitionstbl')) {
        otError('Database migration has not been applied yet. Please run the migration before using this module.', 503);
    }
    $councilId    = (int)($_POST['council_id']               ?? 0);
    $transType    = trim((string)($_POST['transition_type']  ?? ''));
    $effectiveDate= trim((string)($_POST['effective_date']   ?? ''));
    $reason       = trim((string)($_POST['reason']           ?? ''));
    $batchLabel   = trim((string)($_POST['batch_label']      ?? ''));
    $proclamationDate = trim((string)($_POST['proclamation_date'] ?? ''));
    $nextElectionDate = trim((string)($_POST['next_election_date'] ?? ''));
    if ($nextElectionDate === '') {
        $nextElectionDate = trim((string)($_POST['election_date'] ?? ''));
    }

    if ($councilId <= 0)  otError('Council seat is required.');
    if ($transType === '') otError('Transition type is required.');
    if ($transType === 'Removal' && $reason === '') otError('Reason is required for Removal.');
    if (in_array($transType, ['BarangayElection', 'SKElection'], true)) {
        if ($proclamationDate === '') otError('Proclamation date is required for election transitions.');
        if ($nextElectionDate === '') otError('Next election date is required for election transitions.');
        $effectiveDate = $proclamationDate;
    }

    $hasConfiguredTermSchedule = otHasConfiguredTermSchedule($conn);

    // Load seat info (position, current holder, selection method)
    $seatStmt = $conn->prepare("
        SELECT bc.seat_name, bc.selection_method, bc.current_official_id,
               oi.department, oi.area_number
        FROM barangaycounciltbl bc
        LEFT JOIN officialinformationtbl oi ON oi.official_id = bc.current_official_id
        WHERE bc.council_id = ? AND bc.is_active = 1
        LIMIT 1
    ");
    if (!$seatStmt) otError('Failed to load seat: ' . $conn->error);
    $seatStmt->bind_param('i', $councilId);
    $seatStmt->execute();
    $seat = $seatStmt->get_result()->fetch_assoc();
    $seatStmt->close();
    if (!$seat) otError('Council seat not found or inactive.');

    $position   = (string)($seat['seat_name']             ?? '');
    if (!otIsManagedTransitionSeat($position)) {
        otError('This seat is not managed in Official Transition.');
    }
    $outgoingId = $hasConfiguredTermSchedule ? (string)($seat['current_official_id'] ?? '') : '';
    $department = $hasConfiguredTermSchedule ? (string)($seat['department'] ?? '') : '';
    $areaN      = $hasConfiguredTermSchedule ? (string)($seat['area_number'] ?? '') : '';

    $transId = otGenerateTransitionId($conn);

    $stmt = $conn->prepare("
        INSERT INTO officialtransitionstbl
            (transition_id, council_id, batch_label, proclamation_date, next_election_date, election_date, transition_type, position,
             department, area_number, outgoing_official_id, effective_date, reason, status, created_by)
        VALUES (?, NULLIF(?,0), NULLIF(?,''), NULLIF(?,''), NULLIF(?,''), NULLIF(?,''), ?, ?,
                NULLIF(?,''), NULLIF(?,''), NULLIF(?,''), NULLIF(?,''), NULLIF(?,''), 'Open', ?)
    ");
    if (!$stmt) otError('Insert failed: ' . $conn->error);
    $stmt->bind_param('sissssssssssss',
        $transId, $councilId, $batchLabel, $proclamationDate, $nextElectionDate, $nextElectionDate,
        $transType, $position, $department, $areaN,
        $outgoingId, $effectiveDate, $reason, $actorId
    );
    if (!$stmt->execute()) otError('Failed to create transition: ' . $stmt->error);
    $stmt->close();

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'transition', $transId,
        'create_transition', 'transition_type', null, $transType,
        "Seat: {$position} (council_id={$councilId})" . ($batchLabel ? " | Batch: {$batchLabel}" : '')
    );

    otJson(['success' => true, 'message' => 'Transition created.', 'transition_id' => $transId]);
}

// ════════════════════════════════════════════════════════════════════════════
// POST: create batch transition
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'new_batch') {
    if (!otTableExists($conn, 'officialtransitionstbl')) {
        otError('Database migration has not been applied yet.', 503);
    }
    $batchLabel = trim((string)($_POST['batch_label'] ?? ''));
    $proclamationDate = trim((string)($_POST['proclamation_date'] ?? ''));
    $nextElectionDate = trim((string)($_POST['next_election_date'] ?? ''));
    if ($nextElectionDate === '') {
        $nextElectionDate = trim((string)($_POST['election_date'] ?? ''));
    }

    if ($batchLabel === '') otError('Batch label is required.');
    if ($proclamationDate === '') otError('Proclamation date is required.');
    if ($nextElectionDate === '') otError('Next election date is required.');

    $hasConfiguredTermSchedule = otHasConfiguredTermSchedule($conn);

    $seatListStmt = $conn->prepare("
        SELECT bc.council_id, bc.seat_name, bc.current_official_id,
               oi.department, oi.area_number, bc.seat_group
        FROM barangaycounciltbl bc
        LEFT JOIN officialinformationtbl oi ON oi.official_id = bc.current_official_id
        WHERE bc.is_active = 1
          AND bc.selection_method = 'Elected'
        ORDER BY bc.sort_order, bc.council_id
    ");
    if (!$seatListStmt) {
        otError('Failed to load eligible council seats.');
    }
    $seatListStmt->execute();
    $seatListRes = $seatListStmt->get_result();
    $eligibleSeats = [];
    while ($seatRow = $seatListRes->fetch_assoc()) {
        if (!otIsManagedTransitionSeat((string)($seatRow['seat_name'] ?? ''))) {
            continue;
        }
        $eligibleSeats[] = $seatRow;
    }
    $seatListStmt->close();

    if (empty($eligibleSeats)) {
        otError('No eligible elected seats were found for the batch.');
    }

    $created = [];
    $conn->begin_transaction();
    try {
        foreach ($eligibleSeats as $seat) {
            $councilId  = (int)($seat['council_id'] ?? 0);
            if ($councilId <= 0) {
                continue;
            }
            $transId    = otGenerateTransitionId($conn);
            $position   = (string)($seat['seat_name']           ?? '');
            $outgoingId = $hasConfiguredTermSchedule ? (string)($seat['current_official_id'] ?? '') : '';
            $dept       = $hasConfiguredTermSchedule ? (string)($seat['department'] ?? '') : '';
            $area       = $hasConfiguredTermSchedule ? (string)($seat['area_number'] ?? '') : '';
            $transitionType = ((string)($seat['seat_group'] ?? '') === 'Sangguniang Kabataan')
                ? 'SKElection'
                : 'BarangayElection';

            $stmt = $conn->prepare("
                INSERT INTO officialtransitionstbl
                    (transition_id, council_id, batch_label, proclamation_date, next_election_date, election_date,
                     transition_type, position, department, area_number, outgoing_official_id, effective_date, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?,''), NULLIF(?,''), NULLIF(?,''), ?, 'Open', ?)
            ");
            if (!$stmt) throw new Exception('Insert failed: ' . $conn->error);
            $stmt->bind_param('sisssssssssss',
                $transId, $councilId, $batchLabel, $proclamationDate, $nextElectionDate, $nextElectionDate,
                $transitionType, $position, $dept, $area, $outgoingId, $proclamationDate, $actorId
            );
            if (!$stmt->execute()) throw new Exception('Failed: ' . $stmt->error);
            $stmt->close();

            $created[] = $transId;
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        otError($e->getMessage());
    }

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'batch', $batchLabel,
        'create_batch', 'count', null, (string)count($created),
        "Proclamation: {$proclamationDate} | Next election: {$nextElectionDate}"
    );

    otJson(['success' => true, 'message' => count($created) . ' transitions created for batch.', 'created' => $created]);
}

// ════════════════════════════════════════════════════════════════════════════
// POST: update election date for batch
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'update_election_date') {
    $batchLabel = trim((string)($_POST['original_batch_label'] ?? $_POST['batch_label'] ?? ''));
    $proclamationDate = trim((string)($_POST['proclamation_date'] ?? ''));
    $nextElectionDate = trim((string)($_POST['next_election_date'] ?? ''));
    if ($nextElectionDate === '') {
        $nextElectionDate = trim((string)($_POST['election_date'] ?? ''));
    }

    if ($batchLabel === '') otError('Batch label is required.');
    if ($nextElectionDate === '') otError('Next election date is required.');

    $existingStmt = $conn->prepare("
        SELECT
            COALESCE(MAX(proclamation_date), MAX(effective_date)) AS proclamation_date,
            COALESCE(MAX(next_election_date), MAX(election_date)) AS next_election_date
        FROM officialtransitionstbl
        WHERE batch_label = ?
    ");
    if (!$existingStmt) otError('Failed to load existing term schedule.');
    $existingStmt->bind_param('s', $batchLabel);
    $existingStmt->execute();
    $existingRes = $existingStmt->get_result();
    $existingSchedule = $existingRes ? $existingRes->fetch_assoc() : null;
    $existingStmt->close();

    if (!$existingSchedule || (
        trim((string)($existingSchedule['proclamation_date'] ?? '')) === '' &&
        trim((string)($existingSchedule['next_election_date'] ?? '')) === ''
    )) {
        otError('Term schedule not found.');
    }

    $existingProclamationDate = trim((string)($existingSchedule['proclamation_date'] ?? ''));
    $existingNextElectionDate = trim((string)($existingSchedule['next_election_date'] ?? ''));
    $proclamationDate = $existingProclamationDate !== '' ? $existingProclamationDate : $proclamationDate;

    if ($proclamationDate === '') otError('Proclamation date is required.');

    if ($existingNextElectionDate !== '') {
        $existingYear = date('Y', strtotime($existingNextElectionDate));
        $newYear = date('Y', strtotime($nextElectionDate));
        if ($existingYear !== $newYear) {
            otError('Only the month and day can be changed for the next election date.');
        }
    }

    $stmt = $conn->prepare("
        UPDATE officialtransitionstbl
        SET proclamation_date = ?,
            next_election_date = ?,
            election_date = ?,
            effective_date = CASE WHEN effective_date IS NULL THEN ? ELSE effective_date END,
            notify_3mo_sent = 0, notify_3mo_sent_at = NULL,
            notify_1mo_sent = 0, notify_1mo_sent_at = NULL,
            deactivated_7d_before = 0, deactivated_7d_before_at = NULL,
            notify_post_sent = 0, notify_post_sent_at = NULL
        WHERE batch_label = ?
          AND notify_3mo_sent = 0
          AND notify_1mo_sent = 0
          AND deactivated_7d_before = 0
          AND notify_post_sent = 0
    ");
    if (!$stmt) otError('Update failed.');
    $stmt->bind_param('sssss', $proclamationDate, $nextElectionDate, $nextElectionDate, $proclamationDate, $batchLabel);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'batch', $batchLabel,
        'update_election_date', 'next_election_date', null, $nextElectionDate, 'Updated batch proclamation and next election dates.'
    );

    otJson(['success' => true, 'message' => "Batch dates updated for {$affected} transition(s)."]);
}

// ════════════════════════════════════════════════════════════════════════════
// POST: delete election schedule batch
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'delete_schedule') {
    if (!otTableExists($conn, 'officialtransitionstbl')) {
        otError('Database migration has not been applied yet.', 503);
    }

    $batchLabel   = trim((string)($_POST['batch_label']   ?? ''));
    $electionDate = trim((string)($_POST['election_date'] ?? ''));

    if ($batchLabel === '')   otError('Batch label is required.');
    if ($electionDate === '') otError('Election date is required.');

    $countStmt = $conn->prepare("
        SELECT COUNT(*) AS cnt
        FROM officialtransitionstbl
        WHERE batch_label = ?
          AND election_date = ?
    ");
    if (!$countStmt) otError('Failed to load schedule.');
    $countStmt->bind_param('ss', $batchLabel, $electionDate);
    $countStmt->execute();
    $existingCount = (int)($countStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $countStmt->close();

    if ($existingCount <= 0) {
        otError('Schedule not found.');
    }

    $deleteStmt = $conn->prepare("
        DELETE FROM officialtransitionstbl
        WHERE batch_label = ?
          AND election_date = ?
    ");
    if (!$deleteStmt) otError('Delete failed.');
    $deleteStmt->bind_param('ss', $batchLabel, $electionDate);
    $deleteStmt->execute();
    $deleted = $deleteStmt->affected_rows;
    $deleteStmt->close();

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'batch', $batchLabel,
        'delete_schedule', 'election_date', $electionDate, null,
        "Deleted {$deleted} transition(s) linked to schedule {$batchLabel}."
    );

    otJson(['success' => true, 'message' => "Deleted {$deleted} transition(s) from the schedule."]);
}

// ════════════════════════════════════════════════════════════════════════════
// POST: complete transition (process incoming official directly)
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'complete_transition') {
    $transitionId      = trim((string)($_POST['transition_id'] ?? ''));
    $outcome           = trim((string)($_POST['outcome'] ?? ''));
    $actingUntil       = trim((string)($_POST['acting_until_date'] ?? ''));
    $linkedOfficialId  = trim((string)($_POST['linked_official_id'] ?? ''));
    $linkedResidentId  = trim((string)($_POST['linked_resident_id'] ?? ''));
    $notes             = trim((string)($_POST['notes'] ?? ''));
    $candidateFirstName= trim((string)($_POST['candidate_first_name'] ?? ''));
    $candidateLastName = trim((string)($_POST['candidate_last_name'] ?? ''));
    $candidateMiddle   = trim((string)($_POST['candidate_middle_name'] ?? ''));
    $candidateSuffix   = trim((string)($_POST['candidate_suffix'] ?? ''));
    $candidateEmail    = strtolower(trim((string)($_POST['candidate_email'] ?? '')));
    $candidateMobile   = preg_replace('/[^0-9]/', '', (string)($_POST['candidate_mobile'] ?? ''));

    if ($transitionId === '') otError('Missing transition_id.');
    if ($outcome === '')      otError('Outcome is required.');

    $validOutcomes = ['ReElected','NewPerson','Reactivated','PositionChange','ActingReplacement','NoSuccessor'];
    if (!in_array($outcome, $validOutcomes, true)) otError('Invalid outcome.');

    // Load transition
    $tStmt = $conn->prepare("SELECT * FROM officialtransitionstbl WHERE transition_id = ? LIMIT 1");
    if (!$tStmt) otError('Query failed.');
    $tStmt->bind_param('s', $transitionId);
    $tStmt->execute();
    $transition = $tStmt->get_result()->fetch_assoc();
    $tStmt->close();
    if (!$transition) otError('Transition not found.');
    if ((string)($transition['status'] ?? '') === 'Completed') otError('Transition already completed.');

    $outgoingId = (string)($transition['outgoing_official_id'] ?? '');
    $outgoing   = $outgoingId !== '' ? otGetOfficialUser($conn, $outgoingId) : null;
    $effectiveDate = otResolveEffectiveDate($transition);
    $termEndDate = otResolveTermEndDate($transition);
    $termEndDateSql = $termEndDate ?? '';
    $batchLabel = trim((string)($transition['batch_label'] ?? ''));
    $completionMessage = 'Transition completed successfully.';
    $completionInviteLink = '';
    $completionInviteEmailSent = null;
    $completionInviteSmsSent = null;
    $linkedInviteId = 0;
    $pendingInviteDelivery = null;
    $pendingUserNotifications = [];

    $candidate = null;
    $candidateName = trim($candidateLastName . ', ' . $candidateFirstName);
    if ($candidateMiddle !== '') {
        $candidateName .= ' ' . $candidateMiddle;
    }
    if ($candidateSuffix !== '') {
        $candidateName .= ' ' . $candidateSuffix;
    }
    $candidateContact = $candidateMobile !== '' ? '+63' . $candidateMobile : '';

    if (!in_array($outcome, ['ReElected', 'NoSuccessor'], true)) {
        if ($candidateLastName === '' || $candidateFirstName === '') {
            otError('Last name and first name are required.');
        }
        if ($candidateEmail === '' || !filter_var($candidateEmail, FILTER_VALIDATE_EMAIL)) {
            otError('A valid email address is required.');
        }
        if ($candidateMobile === '' || strlen($candidateMobile) !== 10 || $candidateMobile[0] !== '9') {
            otError('Mobile number must be a valid 10-digit Philippine mobile number.');
        }
        if (in_array($outcome, ['Reactivated', 'PositionChange', 'ActingReplacement'], true) && $linkedOfficialId === '') {
            if ($outcome === 'Reactivated') {
                otError('Select the former official record before completing this transition.');
            } else {
                otError('Select the active official record before completing this transition.');
            }
        }
        if ($outcome === 'NewPerson' && $linkedOfficialId !== '') {
            otError('Clear the existing official selection before completing a brand new onboarding.');
        }

        $candidate = [
            'candidate_type' => in_array($outcome, ['PositionChange', 'ActingReplacement'], true)
                ? 'ActiveOfficial'
                : ($linkedOfficialId !== '' ? 'ReturningOfficial' : 'New'),
            'candidate_name' => $candidateName,
            'candidate_contact' => $candidateContact,
            'linked_official_id' => $linkedOfficialId,
            'linked_resident_id' => $linkedResidentId,
            'notes' => $notes,
            'candidate_first_name' => $candidateFirstName,
            'candidate_last_name' => $candidateLastName,
            'candidate_middle_name' => $candidateMiddle,
            'candidate_suffix' => $candidateSuffix,
            'candidate_email' => $candidateEmail,
            'candidate_mobile' => $candidateMobile,
        ];
    }

    // ── Position uniqueness check (skip for ReElected & NoSuccessor) ─────────
    if (!in_array($outcome, ['ReElected','NoSuccessor'], true)) {
        $posToCheck = (string)($transition['position'] ?? '');
        if ($posToCheck !== '') {
            $posCheck = $conn->prepare("
                SELECT oi.official_id FROM officialinformationtbl oi
                INNER JOIN useraccountstbl ua ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
                INNER JOIN statuslookuptbl sa ON sa.status_id = ua.status_id_account
                WHERE COALESCE(oi.position_access, oi.role_access) = ?
                  AND sa.status_name = 'Active'
                  AND oi.official_id != ?
                LIMIT 1
            ");
            if ($posCheck) {
                $safeOutId = $outgoingId ?: 'NONE';
                $posCheck->bind_param('ss', $posToCheck, $safeOutId);
                $posCheck->execute();
                $existing = $posCheck->get_result()->fetch_assoc();
                $posCheck->close();
                if ($existing) {
                    otError("Position '{$posToCheck}' already has an active official. Resolve that first.");
                }
            }
        }
    }

    $inactiveStatusId = otGetStatusId($conn, 'UserAccount', 'Inactive');
    $activeStatusId   = otGetStatusId($conn, 'UserAccount', 'Active');
    $suspendedStatusId= otGetStatusId($conn, 'UserAccount', 'Suspended');
    if ($outcome !== 'NoSuccessor' && $activeStatusId === null) {
        otError('Active status not found in lookup table.');
    }

    $conn->begin_transaction();
    try {
        // ── Process outgoing official ─────────────────────────────────────────
        if ($outgoing && $outgoingId !== '') {
            $outUserId = (string)($outgoing['user_id'] ?? '');
            $transType = (string)($transition['transition_type'] ?? '');

            // Map transition type → employment status label
            $empStatusMap = [
                'BarangayElection' => 'Term Ended',
                'SKElection'       => 'Term Ended',
                'Resignation'      => 'Resigned',
                'Removal'          => 'Removed',
                'Retirement'       => 'Retired',
                'Reappointment'    => 'Reappointed',
                'Appointment'      => 'Reappointed',
            ];
            $newEmpStatus = $empStatusMap[$transType] ?? 'Term Ended';

            if ($outcome === 'ReElected') {
                $reElectStmt = $conn->prepare("
                    UPDATE officialinformationtbl
                    SET term_start = ?,
                        term_end = NULLIF(?, ''),
                        transition_out_type = NULL,
                        transition_out_date = NULL,
                        transition_out_reason = NULL,
                        batch_label = NULLIF(?, ''),
                        last_updated = CURRENT_TIMESTAMP
                    WHERE official_id = ?
                    LIMIT 1
                ");
                if ($reElectStmt) {
                    $reElectStmt->bind_param('ssss', $effectiveDate, $termEndDateSql, $batchLabel, $outgoingId);
                    $reElectStmt->execute();
                    $reElectStmt->close();
                }
                $newEmpStatus = 'Re-Elected';
            } elseif ($outcome === 'ActingReplacement') {
                // Suspend (not deactivate) original
                if ($suspendedStatusId && $outUserId !== '') {
                    $upStmt = $conn->prepare("UPDATE useraccountstbl SET status_id_account = ?, updated_at = NOW() WHERE user_id = ? LIMIT 1");
                    if ($upStmt) { $upStmt->bind_param('is', $suspendedStatusId, $outUserId); $upStmt->execute(); $upStmt->close(); }
                }
            } else {
                // Deactivate outgoing
                if ($inactiveStatusId && $outUserId !== '') {
                    $upStmt = $conn->prepare("UPDATE useraccountstbl SET status_id_account = ?, updated_at = NOW() WHERE user_id = ? LIMIT 1");
                    if ($upStmt) { $upStmt->bind_param('is', $inactiveStatusId, $outUserId); $upStmt->execute(); $upStmt->close(); }
                }
                // can_return = 0 for Removed officials
                if ($transType === 'Removal') {
                    $conn->query("UPDATE officialinformationtbl SET can_return = 0 WHERE official_id = '{$conn->real_escape_string($outgoingId)}'");
                }
            }

            // Record transition-out info on the official's record
            $empStatusId = otGetStatusIdByPreferredNames($conn, ['Employment', 'Official/Personnel Management'], [$newEmpStatus]);
            $transReason = (string)($transition['reason'] ?? '');
            $outDate     = $effectiveDate;
            if ($outcome !== 'ReElected') {
                $upOi = $conn->prepare("
                    UPDATE officialinformationtbl
                    SET transition_out_type   = ?,
                        transition_out_date   = ?,
                        transition_out_reason = NULLIF(?,''),
                        batch_label           = NULLIF(?,''),
                        status_id_employment  = COALESCE(?, status_id_employment),
                        last_updated          = CURRENT_TIMESTAMP
                    WHERE official_id = ?
                ");
                if ($upOi) {
                    $upOi->bind_param('ssssis', $newEmpStatus, $outDate, $transReason, $batchLabel, $empStatusId, $outgoingId);
                    $upOi->execute();
                    $upOi->close();
                }
            }

            // Notify outgoing official
            $outName    = trim(($outgoing['firstname'] ?? '') . ' ' . ($outgoing['lastname'] ?? ''));
            $position   = (string)($transition['position'] ?? '');
            if ($outcome === 'ReElected') {
                $subject = 'System Access Restored — Welcome Back';
                $msg     = "Dear {$outName},\n\nYour Barangay San Jose system account has been restored for your new term as {$position}. Please contact the IT Administrator if you need to update your credentials.\n\nBarangay San Jose";
            } elseif ($outcome === 'ActingReplacement') {
                $subject = 'System Access — Temporary Suspension';
                $msg     = "Dear {$outName},\n\nYour system access has been temporarily suspended as part of an acting assignment for {$position}. Your access will be restored when the acting period ends. Please contact the IT Administrator for details.\n\nBarangay San Jose";
            } else {
                $subject = 'System Access Update — Official Transition';
                $msg     = "Dear {$outName},\n\nYour Barangay San Jose system account has been updated following the official transition for {$position}. Your employment status has been recorded as: {$newEmpStatus}.\n\nIf you have concerns, please contact the IT Administrator.\n\nBarangay San Jose";
            }
            if ($outUserId !== '') {
                $pendingUserNotifications[] = [
                    'user_id' => $outUserId,
                    'subject' => $subject,
                    'message' => $msg,
                ];
            }
        }

        // ── Process incoming (winner) ─────────────────────────────────────────
        $incomingOfficialId = null;
        $incomingUserId = '';

        if ($outcome === 'Reactivated' && $candidate) {
            $linkedId = (string)($candidate['linked_official_id'] ?? '');
            if ($linkedId !== '' && $activeStatusId !== null) {
                $retOfficial = otGetOfficialUser($conn, $linkedId);
                if ($retOfficial) {
                    $retUserId = (string)($retOfficial['user_id'] ?? '');
                    $candidateEmail = strtolower(trim((string)($candidate['candidate_email'] ?? ($retOfficial['email'] ?? ''))));
                    $candidatePhone = oi_normalize_phone10((string)($candidate['candidate_mobile'] ?? $retOfficial['phone_number'] ?? ''));
                    otAssertContactIsAvailable($conn, $candidateEmail, $candidatePhone, $retUserId);

                    $assignment = otResolveIncomingAccessProfile($transition);
                    $employmentStatusId = otGetStatusIdByPreferredNames(
                        $conn,
                        ['Official/Personnel Management', 'Employment', 'OfficialEmployment', 'UserAccount'],
                        [$assignment['employment_status'], 'Regular', 'Active']
                    );
                    $candidateContact = pii_prepare_useraccount_contacts($candidateEmail, $candidatePhone);

                    $upStmt = $conn->prepare("
                        UPDATE useraccountstbl
                        SET status_id_account = ?,
                            email = ?,
                            email_lookup_hash = ?,
                            phone_number = ?,
                            phone_lookup_hash = ?,
                            role_access = ?,
                            updated_at = NOW()
                        WHERE user_id = ?
                        LIMIT 1
                    ");
                    if ($upStmt) {
                        $activeStatusValue = (string)$activeStatusId;
                        $upStmt->bind_param(
                            'sssssss',
                            $activeStatusValue,
                            $candidateContact['email'],
                            $candidateContact['email_lookup_hash'],
                            $candidateContact['phone_number'],
                            $candidateContact['phone_lookup_hash'],
                            $assignment['account_role'],
                            $retUserId
                        );
                        $upStmt->execute();
                        $upStmt->close();
                    }

                    $upContactStmt = $conn->prepare("
                        UPDATE officialinformationtbl
                        SET contact_number = ?,
                            email = ?,
                            last_updated = CURRENT_TIMESTAMP
                        WHERE official_id = ?
                        LIMIT 1
                    ");
                    if ($upContactStmt) {
                        $officialContact = pii_encrypt_field_map([
                            'contact_number' => $candidatePhone,
                            'email' => $candidateEmail,
                        ]);
                        $upContactStmt->bind_param(
                            'sss',
                            $officialContact['contact_number'],
                            $officialContact['email'],
                            $linkedId
                        );
                        $upContactStmt->execute();
                        $upContactStmt->close();
                    }

                    $upProfile = $conn->prepare("
                        UPDATE officialinformationtbl
                        SET role_access = ?,
                            position_access = ?,
                            department = ?,
                            area_number = ?,
                            selection_method = ?,
                            transition_out_type = NULL,
                            transition_out_date = NULL,
                            transition_out_reason = NULL,
                            term_start = ?,
                            term_end = NULLIF(?, ''),
                            batch_label = NULLIF(?, ''),
                            can_return = 1,
                            status_id_employment = COALESCE(?, status_id_employment),
                            last_updated = CURRENT_TIMESTAMP
                        WHERE official_id = ?
                        LIMIT 1
                    ");
                    if ($upProfile) {
                        $upProfile->bind_param(
                            'ssssssssis',
                            $assignment['official_role'],
                            $assignment['position_access'],
                            $assignment['department'],
                            $assignment['area_number'],
                            $assignment['selection_method'],
                            $effectiveDate,
                            $termEndDateSql,
                            $batchLabel,
                            $employmentStatusId,
                            $linkedId
                        );
                        $upProfile->execute();
                        $upProfile->close();
                    }

                    $invite = otCreateOfficialInvite($conn, [
                        'email' => $candidateEmail,
                        'phone_number' => $candidatePhone,
                        'firstname' => (string)($retOfficial['firstname'] ?? ''),
                        'middlename' => (string)($retOfficial['middlename'] ?? ''),
                        'lastname' => (string)($retOfficial['lastname'] ?? ''),
                        'suffix' => (string)($retOfficial['suffix'] ?? ''),
                        'role_access' => $assignment['account_role'],
                        'position_access' => $assignment['position_access'],
                        'department' => $assignment['department'],
                        'employment_status' => $assignment['employment_status'],
                        'area_number' => $assignment['area_number'],
                        'user_id' => $retUserId,
                    ], $actorId);
                    $linkedInviteId = (int)($invite['invite_id'] ?? 0);
                    $completionInviteLink = (string)($invite['invite_link'] ?? '');
                    $pendingInviteDelivery = $invite;
                    $incomingOfficialId = $linkedId;
                    $incomingUserId = $retUserId;
                    $completionMessage = 'Transition completed. The reactivated official account was updated.';
                }
            }
        } elseif ($outcome === 'PositionChange' && $candidate) {
            $linkedId = (string)($candidate['linked_official_id'] ?? '');
            if ($linkedId !== '') {
                $previousOfficialData = otGetOfficialUser($conn, $linkedId);
                $assignment = otResolveIncomingAccessProfile($transition);
                $positionStmt = $conn->prepare("
                    UPDATE officialinformationtbl
                    SET role_access = ?,
                        position_access = ?,
                        department = ?,
                        area_number = ?,
                        selection_method = ?,
                        term_start = ?,
                        term_end = NULLIF(?, ''),
                        batch_label = NULLIF(?, ''),
                        last_updated = CURRENT_TIMESTAMP
                    WHERE official_id = ?
                    LIMIT 1
                ");
                if ($positionStmt) {
                    $positionStmt->bind_param(
                        'sssssssss',
                        $assignment['official_role'],
                        $assignment['position_access'],
                        $assignment['department'],
                        $assignment['area_number'],
                        $assignment['selection_method'],
                        $effectiveDate,
                        $termEndDateSql,
                        $batchLabel,
                        $linkedId
                    );
                    $positionStmt->execute();
                    $positionStmt->close();
                }
                $incomingOfficialId = $linkedId;
                $incomingUserId = (string)($previousOfficialData['user_id'] ?? '');
                // Auto-open a new transition for the vacated position
                $vacOfficialData = $previousOfficialData;
                if ($vacOfficialData) {
                    $vacTransId  = otGenerateTransitionId($conn);
                    $vacCouncilId = (int)($vacOfficialData['council_id'] ?? 0);
                    $vacPosition = (string)($vacOfficialData['position'] ?? '');
                    $vacDept     = (string)($vacOfficialData['department'] ?? '');
                    $vacArea     = (string)($vacOfficialData['area_number'] ?? '');
                    $autoStmt = $conn->prepare("INSERT INTO officialtransitionstbl (transition_id, council_id, transition_type, position, department, area_number, status, created_by, reason) VALUES (?, NULLIF(?,0), 'Replacement', NULLIF(?,''), NULLIF(?,''), NULLIF(?,''), 'Open', ?, 'Auto-opened: official moved to another position')");
                    if ($autoStmt) {
                        $autoStmt->bind_param('sissss', $vacTransId, $vacCouncilId, $vacPosition, $vacDept, $vacArea, $actorId);
                        $autoStmt->execute();
                        $autoStmt->close();
                    }
                }
            }
        } elseif ($outcome === 'ActingReplacement' && $candidate) {
            $linkedId = (string)($candidate['linked_official_id'] ?? '');
            if ($linkedId !== '' && $activeStatusId !== null) {
                $actOfficial = otGetOfficialUser($conn, $linkedId);
                if ($actOfficial) {
                    $actUserId = (string)($actOfficial['user_id'] ?? '');
                    $upStmt = $conn->prepare("UPDATE useraccountstbl SET status_id_account = ?, updated_at = NOW() WHERE user_id = ? LIMIT 1");
                    if ($upStmt) {
                        $activeStatusValue = (string)$activeStatusId;
                        $upStmt->bind_param('ss', $activeStatusValue, $actUserId);
                        $upStmt->execute();
                        $upStmt->close();
                    }
                    // Mark acting relationship on the acting official
                    $conn->query("UPDATE officialinformationtbl SET acting_for_id = '{$conn->real_escape_string($outgoingId)}', acting_until_date = " . ($actingUntil !== '' ? "'{$conn->real_escape_string($actingUntil)}'" : 'NULL') . ", last_updated = CURRENT_TIMESTAMP WHERE official_id = '{$conn->real_escape_string($linkedId)}'");
                    $incomingOfficialId = $linkedId;
                    $incomingUserId = $actUserId;
                }
            }
        } elseif ($outcome === 'NewPerson' && $candidate) {
            $newIncoming = otCreateIncomingOfficialShell($conn, $transition, $candidate, $actorId, (string)$activeStatusId);
            $incomingOfficialId = (string)($newIncoming['official_id'] ?? '');
            $incomingUserId = (string)($newIncoming['user_id'] ?? '');
            $linkedInviteId = (int)($newIncoming['invite_id'] ?? 0);
            $completionInviteLink = (string)($newIncoming['invite_link'] ?? '');
            $pendingInviteDelivery = [
                'invite_link' => $completionInviteLink,
                'delivery' => is_array($newIncoming['invite_delivery'] ?? null) ? $newIncoming['invite_delivery'] : null,
            ];
            $completionMessage = 'Transition completed. Onboarding access was prepared for the proclaimed official.';
        } elseif ($outcome === 'ReElected') {
            $incomingOfficialId = $outgoingId;
            $incomingUserId = (string)($outgoing['user_id'] ?? '');
        }

        // ── Update transition record ──────────────────────────────────────────
        $isActing   = $outcome === 'ActingReplacement' ? 1 : 0;
        $actingDate = ($isActing && $actingUntil !== '') ? $actingUntil : null;
        $upTrans = $conn->prepare("
            UPDATE officialtransitionstbl
            SET status                = 'Completed',
                outcome               = ?,
                incoming_official_id  = NULLIF(?,''),
                is_acting             = ?,
                acting_until_date     = ?,
                decided_at            = NOW(),
                completed_at          = NOW()
            WHERE transition_id = ?
        ");
        if ($upTrans) {
            $upTrans->bind_param('ssiss',
                $outcome, $incomingOfficialId,
                $isActing, $actingDate, $transitionId
            );
            $upTrans->execute();
            $upTrans->close();
        }

        // ── Update barangaycounciltbl seat ────────────────────────────────────
        $councilId = (int)($transition['council_id'] ?? 0);
        if ($councilId > 0 && otTableExists($conn, 'barangaycounciltbl')) {
            if ($outcome === 'NoSuccessor') {
                // Seat becomes vacant
                $conn->query("UPDATE barangaycounciltbl SET current_official_id = NULL, term_start = NULL, term_end = NULL, updated_at = NOW() WHERE council_id = {$councilId}");
            } elseif ($outcome === 'ReElected') {
                $termEndSql = $termEndDate !== null ? "'" . $conn->real_escape_string($termEndDate) . "'" : 'NULL';
                $conn->query("UPDATE barangaycounciltbl SET term_start = '{$conn->real_escape_string($effectiveDate)}', term_end = {$termEndSql}, updated_at = NOW() WHERE council_id = {$councilId}");
            } elseif ($incomingOfficialId !== null && $incomingOfficialId !== '') {
                // New holder takes the seat
                $safeIncoming = $conn->real_escape_string($incomingOfficialId);
                $termEndSql = $termEndDate !== null ? "'" . $conn->real_escape_string($termEndDate) . "'" : 'NULL';
                $conn->query("UPDATE barangaycounciltbl SET current_official_id = '{$safeIncoming}', term_start = '{$conn->real_escape_string($effectiveDate)}', term_end = {$termEndSql}, updated_at = NOW() WHERE council_id = {$councilId}");
            }
            // ActingReplacement: keep original in current_official_id (they're only suspended, not replaced)
        }

        if ($councilId > 0 && $incomingOfficialId !== null && $incomingOfficialId !== '' && $incomingUserId !== '') {
            amp_apply_seat_permissions_to_official($conn, $councilId, (string)$incomingOfficialId, $incomingUserId, $actorId, 'Official');
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        otError($e->getMessage());
    }

    foreach ($pendingUserNotifications as $notification) {
        otNotifyUser(
            $conn,
            (string)($notification['user_id'] ?? ''),
            (string)($notification['subject'] ?? ''),
            (string)($notification['message'] ?? '')
        );
    }
    if (is_array($pendingInviteDelivery)) {
        $deliveryStatus = otDeliverOnboardingInvite($pendingInviteDelivery);
        $completionInviteEmailSent = (bool)($deliveryStatus['email_sent'] ?? false);
        $completionInviteSmsSent = (bool)($deliveryStatus['sms_sent'] ?? false);
    }
    if ($completionInviteEmailSent !== null || $completionInviteSmsSent !== null) {
        $deliveryParts = [];
        if ($completionInviteEmailSent !== null) {
            $deliveryParts[] = $completionInviteEmailSent ? 'email sent' : 'email not sent';
        }
        if ($completionInviteSmsSent !== null) {
            $deliveryParts[] = $completionInviteSmsSent ? 'SMS sent' : 'SMS not sent';
        }
        if ($deliveryParts) {
            $completionMessage .= ' Delivery status: ' . implode(', ', $deliveryParts) . '.';
        }
    }

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'transition', $transitionId,
        'complete_transition', 'outcome', null, $outcome,
        "Outgoing: {$outgoingId}" . ($candidateName !== '' ? " | Incoming: {$candidateName}" : '')
    );

    otJson([
        'success' => true,
        'message' => $completionMessage,
        'invite_link' => $completionInviteLink,
        'invite_email_sent' => $completionInviteEmailSent,
        'invite_sms_sent' => $completionInviteSmsSent,
    ]);
}

// ════════════════════════════════════════════════════════════════════════════
// POST: cancel transition
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'cancel_transition') {
    $transitionId = trim((string)($_POST['transition_id'] ?? ''));
    $reason       = trim((string)($_POST['reason']        ?? ''));
    if ($transitionId === '') otError('Missing transition_id.');

    $stmt = $conn->prepare("UPDATE officialtransitionstbl SET status='Cancelled', reason=CONCAT(COALESCE(reason,''),' [Cancelled: ',?,' at ',NOW(),']') WHERE transition_id=? AND status != 'Completed'");
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

// ════════════════════════════════════════════════════════════════════════════
// POST: restore access (Quick Action)
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'restore_access') {
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

// ════════════════════════════════════════════════════════════════════════════
// POST: manual resend notification trigger
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'resend_notification') {
    $batchLabel   = trim((string)($_POST['batch_label']   ?? ''));
    $electionDate = trim((string)($_POST['election_date'] ?? ''));
    if ($batchLabel === '' || $electionDate === '') otError('Missing batch_label or election_date.');

    // Reset all unsent flags so cron picks them up again
    $conn->query("
        UPDATE officialtransitionstbl
        SET notify_3mo_sent = 0, notify_3mo_sent_at = NULL,
            notify_1mo_sent = 0, notify_1mo_sent_at = NULL,
            deactivated_7d_before = 0, deactivated_7d_before_at = NULL,
            notify_post_sent = 0, notify_post_sent_at = NULL
        WHERE batch_label = '{$conn->real_escape_string($batchLabel)}'
          AND election_date = '{$conn->real_escape_string($electionDate)}'
    ");

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'batch', $batchLabel,
        'resend_notification', 'election_date', null, $electionDate,
        'Manual: reset notification flags for re-trigger'
    );

    otJson(['success' => true, 'message' => 'Notification flags reset. The cron will re-evaluate and send on its next run.']);
}

// ── Fallthrough ───────────────────────────────────────────────────────────────
otError('Unknown action.', 404);
