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
    return $row ?: null;
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

if (otTableExists($conn, 'upcomingofficialstbl')) {
    otEnsureUpcomingOfficialFields($conn);
}
if (otTableExists($conn, 'officialtransitionstbl')) {
    otEnsureTransitionFields($conn);
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
            WHERE (email = ? OR phone_number = ?)
              AND user_id <> ?
            LIMIT 1
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to validate email and mobile number.');
        }
        $stmt->bind_param('sss', $email, $phone10, $excludeUserId);
    } else {
        $stmt = $conn->prepare("
            SELECT user_id
            FROM useraccountstbl
            WHERE email = ? OR phone_number = ?
            LIMIT 1
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to validate email and mobile number.');
        }
        $stmt->bind_param('ss', $email, $phone10);
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

    $stmt = $conn->prepare("
        INSERT INTO officialinvitetbl
            (invite_code, invite_token_hash, invite_email, invite_phone, firstname, middlename, lastname, suffix,
             role_access, position_access, department, employment_status, area_number, status, onboarding_step,
             invited_by_user_id, user_id, expires_at)
        VALUES
            (?, ?, ?, ?, ?, NULLIF(?, ''), ?, NULLIF(?, ''),
             ?, NULLIF(?, ''), ?, NULLIF(?, ''), ?, 'Pending', 'password',
             ?, NULLIF(?, ''), ?)
    ");
    if (!$stmt) {
        throw new RuntimeException('Failed to create onboarding invite.');
    }
    $stmt->bind_param(
        'ssssssssssssssss',
        $inviteCode,
        $token['hash'],
        $email,
        $phone10,
        $firstname,
        $middlename,
        $lastname,
        $suffix,
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
    $subject = 'Barangay San Jose Official Onboarding Access';
    $body = "Dear " . ($fullName !== '' ? $fullName : 'Official') . ",\n\n"
        . "Your account access for Barangay San Jose is ready.\n"
        . "Position: " . ($positionAccess !== '' ? $positionAccess : $roleAccess) . "\n"
        . "Department: " . ($department !== '' ? $department : 'Office of the Barangay') . "\n\n"
        . "Open this one-time onboarding access link:\n{$inviteLink}\n\n"
        . "This link expires in 48 hours.\n\n"
        . "Barangay San Jose";

    $emailSent = otSendOnboardingInviteEmail(
        $email,
        $fullName,
        $positionAccess !== '' ? $positionAccess : $roleAccess,
        $inviteLink
    );
    $smsSent = otSendSMS($phone10, 'Barangay San Jose: Your official onboarding access is ready. Please check your email for the one-time invite link.');

    return [
        'invite_id' => $inviteId,
        'invite_link' => $inviteLink,
        'email_sent' => $emailSent,
        'sms_sent' => $smsSent,
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
    $accountStmt = $conn->prepare("
        INSERT INTO useraccountstbl
            (user_id, phone_number, phoneNum_verify, email, email_verify, password_hash, status_id_account, role_access, account_created, last_login, updated_at)
        VALUES
            (?, ?, 0, ?, 0, ?, ?, ?, NOW(), NOW(), NOW())
    ");
    if (!$accountStmt) {
        throw new RuntimeException('Failed to prepare incoming official account creation.');
    }
    $accountStmt->bind_param(
        'ssssss',
        $userId,
        $phone10,
        $email,
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
    $officialStmt = $conn->prepare("
        INSERT INTO officialinformationtbl
            (user_id, lastname, firstname, middlename, suffix, birthdate, sex, civil_status, contact_number, email,
             area_access, department, selection_method, term_start, term_end, batch_label, area_number,
             role_access, position_access, status_id_employment, date_hired)
        VALUES
            (?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), '1900-01-01', 'Other', 'Single', ?, ?,
             ?, ?, NULLIF(?, ''), ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''),
             ?, NULLIF(?, ''), ?, ?)
    ");
    if (!$officialStmt) {
        throw new RuntimeException('Failed to prepare the incoming official profile.');
    }
    $lastname = trim((string)($candidate['candidate_last_name'] ?? ''));
    $firstname = trim((string)($candidate['candidate_first_name'] ?? ''));
    $middlename = trim((string)($candidate['candidate_middle_name'] ?? ''));
    $suffix = trim((string)($candidate['candidate_suffix'] ?? ''));
    $selectionMethod = (string)$assignment['selection_method'];
    $officialStmt->bind_param(
        'ssssssssssssssssis',
        $userId,
        $lastname,
        $firstname,
        $middlename,
        $suffix,
        $phone10,
        $email,
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
    $officialId = (string)$conn->insert_id;
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
        'email_sent' => (bool)($invite['email_sent'] ?? false),
        'sms_sent' => (bool)($invite['sms_sent'] ?? false),
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
            CONCAT(oi.lastname, ', ', oi.firstname,
                   IFNULL(CONCAT(' ', oi.middlename),''),
                   IFNULL(CONCAT(' ', oi.suffix),''))     AS current_official_name,
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

    $where = [];
    $params = [];
    $types  = '';

    $where[] = "t.status NOT IN ('Completed','Cancelled')";
    $where[] = otIgnoredTransitionSeatSql($conn, 't.position');
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = "(t.transition_id LIKE ? OR t.position LIKE ? OR t.batch_label LIKE ? OR CONCAT(oi.lastname,' ',oi.firstname) LIKE ?)";
        $params = array_merge($params, [$like, $like, $like, $like]);
        $types .= 'ssss';
    }
    if ($type !== '') {
        $where[] = "t.transition_type = ?";
        $params[] = $type;
        $types .= 's';
    }

    $whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "
        SELECT t.*,
               COALESCE(t.proclamation_date, t.effective_date) AS proclamation_date,
               COALESCE(t.next_election_date, t.election_date) AS next_election_date,
               bc.sort_order,
               CONCAT(oi.lastname, ', ', oi.firstname, IFNULL(CONCAT(' ', oi.middlename),''), IFNULL(CONCAT(' ', oi.suffix),'')) AS outgoing_name,
               COALESCE(oi.position_access, oi.role_access) AS outgoing_position
        FROM officialtransitionstbl t
        LEFT JOIN officialinformationtbl oi ON oi.official_id = t.outgoing_official_id
        LEFT JOIN barangaycounciltbl bc ON bc.council_id = t.council_id
        {$whereClause}
        ORDER BY COALESCE(bc.sort_order, 9999) ASC, t.created_at DESC
        LIMIT ? OFFSET ?
    ";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $stmt = $conn->prepare($sql);
    if (!$stmt) otError('Query prepare failed: ' . $conn->error);

    $refs = [$types];
    foreach ($params as $k => $v) $refs[] = &$params[$k];
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Total count
    $countSql = "SELECT COUNT(*) AS cnt FROM officialtransitionstbl t LEFT JOIN officialinformationtbl oi ON oi.official_id = t.outgoing_official_id {$whereClause}";
    $countTypes = substr($types, 0, -2);
    $countParams = array_slice($params, 0, -2);
    $countStmt = $conn->prepare($countSql);
    $total = 0;
    if ($countStmt) {
        if ($countTypes !== '' && $countParams) {
            $crefs = [$countTypes];
            foreach ($countParams as $k => $v) $crefs[] = &$countParams[$k];
            call_user_func_array([$countStmt, 'bind_param'], $crefs);
        }
        $countStmt->execute();
        $total = (int)($countStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        $countStmt->close();
    }

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
               CONCAT(oi.lastname, ', ', oi.firstname) AS full_name,
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
    $params = [];
    $types  = '';
    if ($q !== '') {
        $like = '%' . $q . '%';
        $sql .= " AND (oi.official_id LIKE ? OR oi.firstname LIKE ? OR oi.lastname LIKE ? OR COALESCE(oi.position_access,oi.role_access) LIKE ?)";
        $params = [$like, $like, $like, $like];
        $types  = 'ssss';
    }
    $sql .= " ORDER BY oi.lastname, oi.firstname LIMIT 100";

    $stmt = $conn->prepare($sql);
    if (!$stmt) otError('Query failed.');
    if ($types !== '') {
        $refs = [$types];
        foreach ($params as $k => $v) $refs[] = &$params[$k];
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    otJson(['success' => true, 'data' => $rows]);
}

// ════════════════════════════════════════════════════════════════════════════
// FETCH: candidates for a transition
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'fetch_candidates') {
    if (!otTableExists($conn, 'officialtransitionstbl')) {
        otJson(['success' => true, 'candidates' => [], 'transition' => null]);
    }
    $transitionId = trim((string)($_GET['transition_id'] ?? ''));
    if ($transitionId === '') otError('Missing transition_id.');

    $stmt = $conn->prepare("SELECT * FROM upcomingofficialstbl WHERE transition_id = ? ORDER BY upcoming_id ASC");
    if (!$stmt) otError('Query failed.');
    $stmt->bind_param('s', $transitionId);
    $stmt->execute();
    $candidates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $tStmt = $conn->prepare("SELECT t.*, CONCAT(oi.lastname,', ',oi.firstname) AS outgoing_name, COALESCE(oi.position_access,oi.role_access) AS outgoing_position FROM officialtransitionstbl t LEFT JOIN officialinformationtbl oi ON oi.official_id = t.outgoing_official_id WHERE t.transition_id = ? LIMIT 1");
    $transition = null;
    if ($tStmt) {
        $tStmt->bind_param('s', $transitionId);
        $tStmt->execute();
        $transition = $tStmt->get_result()->fetch_assoc();
        $tStmt->close();
    }

    otJson(['success' => true, 'candidates' => $candidates, 'transition' => $transition]);
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
// POST: save encoded official information
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'add_candidate') {
    otEnsureUpcomingOfficialFields($conn);

    $transitionId      = trim((string)($_POST['transition_id']       ?? ''));
    $linkedOfficialId  = trim((string)($_POST['linked_official_id']  ?? ''));
    $linkedResidentId  = trim((string)($_POST['linked_resident_id']  ?? ''));
    $notes             = trim((string)($_POST['notes']               ?? ''));
    $candidateFirstName= trim((string)($_POST['candidate_first_name']?? ''));
    $candidateLastName = trim((string)($_POST['candidate_last_name'] ?? ''));
    $candidateMiddle   = trim((string)($_POST['candidate_middle_name'] ?? ''));
    $candidateSuffix   = trim((string)($_POST['candidate_suffix']    ?? ''));
    $candidateEmail    = trim((string)($_POST['candidate_email']     ?? ''));
    $candidateMobile   = preg_replace('/[^0-9]/', '', (string)($_POST['candidate_mobile'] ?? ''));

    if ($transitionId  === '') otError('Missing transition_id.');
    if ($candidateLastName === '' || $candidateFirstName === '') {
        otError('Last name and first name are required.');
    }
    if ($candidateEmail === '' || !filter_var($candidateEmail, FILTER_VALIDATE_EMAIL)) {
        otError('A valid email address is required.');
    }
    if ($candidateMobile === '' || strlen($candidateMobile) !== 10 || $candidateMobile[0] !== '9') {
        otError('Mobile number must be a valid 10-digit Philippine mobile number.');
    }

    $candidateType = $linkedOfficialId !== '' ? 'ReturningOfficial' : 'New';

    $candidateName = trim($candidateLastName . ', ' . $candidateFirstName);
    if ($candidateMiddle !== '') {
        $candidateName .= ' ' . $candidateMiddle;
    }
    if ($candidateSuffix !== '') {
        $candidateName .= ' ' . $candidateSuffix;
    }
    $candidateContact = '+63' . $candidateMobile;

    $existingId = 0;
    $existingStmt = $conn->prepare("
        SELECT upcoming_id
        FROM upcomingofficialstbl
        WHERE transition_id = ?
        ORDER BY is_selected DESC, upcoming_id ASC
        LIMIT 1
    ");
    if ($existingStmt) {
        $existingStmt->bind_param('s', $transitionId);
        $existingStmt->execute();
        $existingId = (int)($existingStmt->get_result()->fetch_assoc()['upcoming_id'] ?? 0);
        $existingStmt->close();
    }

    if ($existingId > 0) {
        $stmt = $conn->prepare("
            UPDATE upcomingofficialstbl
            SET candidate_type = ?,
                candidate_name = ?,
                candidate_contact = NULLIF(?, ''),
                linked_official_id = NULLIF(?, ''),
                linked_resident_id = NULLIF(?, ''),
                notes = NULLIF(?, ''),
                encoded_by = ?,
                candidate_first_name = NULLIF(?, ''),
                candidate_last_name = NULLIF(?, ''),
                candidate_middle_name = NULLIF(?, ''),
                candidate_suffix = NULLIF(?, ''),
                candidate_email = NULLIF(?, ''),
                candidate_mobile = NULLIF(?, ''),
                is_selected = 1
            WHERE upcoming_id = ?
            LIMIT 1
        ");
        if (!$stmt) otError('Update failed: ' . $conn->error);
        $stmt->bind_param('sssssssssssssi',
            $candidateType, $candidateName, $candidateContact,
            $linkedOfficialId, $linkedResidentId, $notes, $actorId,
            $candidateFirstName, $candidateLastName, $candidateMiddle,
            $candidateSuffix, $candidateEmail, $candidateMobile, $existingId
        );
        if (!$stmt->execute()) otError('Failed to save official information: ' . $stmt->error);
        $stmt->close();
        $newId = $existingId;
        $message = 'Official information updated.';
        $auditAction = 'update_candidate';
        $oldValue = 'Existing encoded official';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO upcomingofficialstbl
                (transition_id, candidate_type, candidate_name, candidate_contact,
                 linked_official_id, linked_resident_id, notes, encoded_by,
                 candidate_first_name, candidate_last_name, candidate_middle_name,
                 candidate_suffix, candidate_email, candidate_mobile, is_selected)
            VALUES (?, ?, ?, NULLIF(?,''), NULLIF(?,''), NULLIF(?,''), NULLIF(?,''), ?,
                    NULLIF(?,''), NULLIF(?,''), NULLIF(?,''), NULLIF(?,''), NULLIF(?,''), NULLIF(?,''), 1)
        ");
        if (!$stmt) otError('Insert failed: ' . $conn->error);
        $stmt->bind_param('ssssssssssssss',
            $transitionId, $candidateType, $candidateName, $candidateContact,
            $linkedOfficialId, $linkedResidentId, $notes, $actorId,
            $candidateFirstName, $candidateLastName, $candidateMiddle,
            $candidateSuffix, $candidateEmail, $candidateMobile
        );
        if (!$stmt->execute()) otError('Failed to save official information: ' . $stmt->error);
        $newId = $conn->insert_id;
        $stmt->close();
        $message = 'Official information saved.';
        $auditAction = 'add_candidate';
        $oldValue = null;
    }

    $cleanupStmt = $conn->prepare("DELETE FROM upcomingofficialstbl WHERE transition_id = ? AND upcoming_id <> ?");
    if ($cleanupStmt) {
        $cleanupStmt->bind_param('si', $transitionId, $newId);
        $cleanupStmt->execute();
        $cleanupStmt->close();
    }

    // Update transition status to CandidateEncoding if still Open
    $conn->query("UPDATE officialtransitionstbl SET status='CandidateEncoding' WHERE transition_id='{$conn->real_escape_string($transitionId)}' AND status='Open'");

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'candidate', (string)$newId,
        $auditAction, 'candidate_name', $oldValue, $candidateName,
        "Transition: {$transitionId} | Type: {$candidateType}"
    );

    otJson(['success' => true, 'message' => $message, 'upcoming_id' => $newId]);
}

// ════════════════════════════════════════════════════════════════════════════
// POST: remove access entry
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'remove_candidate') {
    $upcomingId = (int)($_POST['upcoming_id'] ?? 0);
    if ($upcomingId <= 0) otError('Invalid access entry ID.');

    $row = $conn->query("SELECT candidate_name, transition_id FROM upcomingofficialstbl WHERE upcoming_id = {$upcomingId} LIMIT 1")->fetch_assoc();
    if (!$row) otError('Official record not found.');

    $conn->query("DELETE FROM upcomingofficialstbl WHERE upcoming_id = {$upcomingId}");

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'candidate', (string)$upcomingId,
        'remove_candidate', 'candidate_name', (string)$row['candidate_name'], null,
        "Transition: {$row['transition_id']}"
    );

    otJson(['success' => true, 'message' => 'Official removed.']);
}

// ════════════════════════════════════════════════════════════════════════════
// POST: mark transition as pending decision
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'mark_pending_decision') {
    $transitionId = trim((string)($_POST['transition_id'] ?? ''));
    if ($transitionId === '') otError('Missing transition_id.');

    $count = (int)$conn->query("SELECT COUNT(*) AS c FROM upcomingofficialstbl WHERE transition_id='{$conn->real_escape_string($transitionId)}'")->fetch_assoc()['c'];
    if ($count === 0) otError('Complete the official information before continuing.');

    $stmt = $conn->prepare("UPDATE officialtransitionstbl SET status='PendingDecision' WHERE transition_id=? AND status IN ('CandidateEncoding','Open')");
    if (!$stmt) otError('Update failed.');
    $stmt->bind_param('s', $transitionId);
    $stmt->execute();
    $stmt->close();

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'transition', $transitionId,
        'mark_pending_decision', 'status', 'CandidateEncoding', 'PendingDecision', null
    );

    otJson(['success' => true, 'message' => 'Transition marked as ready for decision.']);
}

// ════════════════════════════════════════════════════════════════════════════
// POST: complete transition (select winner + process)
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'complete_transition') {
    $transitionId  = trim((string)($_POST['transition_id']    ?? ''));
    $candidateId   = (int)($_POST['selected_candidate_id']    ?? 0);
    $outcome       = trim((string)($_POST['outcome']          ?? ''));
    $actingUntil   = trim((string)($_POST['acting_until_date']?? ''));

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

    // ── Load selected candidate (required for most outcomes) ─────────────────
    $candidate = null;
    if ($outcome !== 'NoSuccessor' && $candidateId > 0) {
        $cStmt = $conn->prepare("SELECT * FROM upcomingofficialstbl WHERE upcoming_id = ? AND transition_id = ? LIMIT 1");
        if ($cStmt) {
            $cStmt->bind_param('is', $candidateId, $transitionId);
            $cStmt->execute();
            $candidate = $cStmt->get_result()->fetch_assoc();
            $cStmt->close();
        }
    }
    if (!in_array($outcome, ['ReElected', 'NoSuccessor'], true) && !$candidate) {
        otError('Selected official information was not found for this transition.');
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
            if ($outUserId !== '') otNotifyUser($conn, $outUserId, $subject, $msg);
        }

        // ── Process incoming (winner) ─────────────────────────────────────────
        $incomingOfficialId = null;
        $incomingUserId = '';
        $linkedInviteId = 0;
        $completionInviteLink = '';
        $completionInviteEmailSent = null;
        $completionInviteSmsSent = null;

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

                    $upStmt = $conn->prepare("
                        UPDATE useraccountstbl
                        SET status_id_account = ?,
                            email = ?,
                            phone_number = ?,
                            role_access = ?,
                            updated_at = NOW()
                        WHERE user_id = ?
                        LIMIT 1
                    ");
                    if ($upStmt) {
                        $activeStatusValue = (string)$activeStatusId;
                        $upStmt->bind_param('sssss', $activeStatusValue, $candidateEmail, $candidatePhone, $assignment['account_role'], $retUserId);
                        $upStmt->execute();
                        $upStmt->close();
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
                    $completionInviteEmailSent = (bool)($invite['email_sent'] ?? false);
                    $completionInviteSmsSent = (bool)($invite['sms_sent'] ?? false);
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
                    $vacPosition = (string)($vacOfficialData['position'] ?? '');
                    $vacDept     = (string)($vacOfficialData['department'] ?? '');
                    $vacArea     = (string)($vacOfficialData['area_number'] ?? '');
                    $autoStmt = $conn->prepare("INSERT INTO officialtransitionstbl (transition_id, transition_type, position, department, area_number, status, created_by, reason) VALUES (?, 'Replacement', NULLIF(?,''), NULLIF(?,''), NULLIF(?,''), 'Open', ?, 'Auto-opened: official moved to another position')");
                    if ($autoStmt) {
                        $autoStmt->bind_param('sssss', $vacTransId, $vacPosition, $vacDept, $vacArea, $actorId);
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
            $completionInviteEmailSent = (bool)($newIncoming['email_sent'] ?? false);
            $completionInviteSmsSent = (bool)($newIncoming['sms_sent'] ?? false);
            $completionMessage = 'Transition completed. Onboarding access was prepared for the proclaimed official.';
        } elseif ($outcome === 'ReElected') {
            $incomingOfficialId = $outgoingId;
            $incomingUserId = (string)($outgoing['user_id'] ?? '');
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

        // ── Mark selected candidate ───────────────────────────────────────────
        if ($candidateId > 0 && $outcome !== 'NoSuccessor') {
            $conn->query("UPDATE upcomingofficialstbl SET is_selected = 0 WHERE transition_id = '{$conn->real_escape_string($transitionId)}'");
            $candidateUpdateParts = ["is_selected = 1"];
            if ($incomingOfficialId !== null && $incomingOfficialId !== '') {
                $candidateUpdateParts[] = "linked_official_id = '{$conn->real_escape_string((string)$incomingOfficialId)}'";
            }
            if ($linkedInviteId > 0) {
                $candidateUpdateParts[] = "linked_invite_id = {$linkedInviteId}";
            }
            $conn->query("UPDATE upcomingofficialstbl SET " . implode(', ', $candidateUpdateParts) . " WHERE upcoming_id = {$candidateId}");
        }

        // ── Update transition record ──────────────────────────────────────────
        $isActing   = $outcome === 'ActingReplacement' ? 1 : 0;
        $actingDate = ($isActing && $actingUntil !== '') ? $actingUntil : null;
        $upTrans = $conn->prepare("
            UPDATE officialtransitionstbl
            SET status                = 'Completed',
                outcome               = ?,
                selected_candidate_id = NULLIF(?,0),
                incoming_official_id  = NULLIF(?,''),
                is_acting             = ?,
                acting_until_date     = ?,
                decided_at            = NOW(),
                completed_at          = NOW()
            WHERE transition_id = ?
        ");
        if ($upTrans) {
            $upTrans->bind_param('siisss',
                $outcome, $candidateId, $incomingOfficialId,
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

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'transition', $transitionId,
        'complete_transition', 'outcome', null, $outcome,
        "Outgoing: {$outgoingId} | Candidate: {$candidateId}"
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

    $setParts = ['updated_at = NOW()'];
    $params   = [];
    $types    = '';

    if ($newEmail !== '') {
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) otError('Invalid email address.');
        $setParts[] = 'email = ?';
        $params[]   = $newEmail;
        $types     .= 's';
    }
    if ($newPhone !== '') {
        $setParts[] = 'phone_number = ?';
        $params[]   = $newPhone;
        $types     .= 's';
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
