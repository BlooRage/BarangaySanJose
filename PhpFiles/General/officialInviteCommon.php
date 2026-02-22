<?php
declare(strict_types=1);

if (!function_exists('oi_normalize_phone10')) {
    function oi_normalize_phone10(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '63')) {
            $digits = substr($digits, 2);
        }
        return substr($digits, 0, 10);
    }
}

if (!function_exists('oi_is_valid_phone10')) {
    function oi_is_valid_phone10(string $phone10): bool
    {
        return (bool)preg_match('/^9\d{9}$/', $phone10);
    }
}

if (!function_exists('oi_generate_otp')) {
    function oi_generate_otp(): string
    {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('oi_generate_invite_token')) {
    function oi_generate_invite_token(): array
    {
        $raw = bin2hex(random_bytes(32));
        return [
            'raw' => $raw,
            'hash' => password_hash($raw, PASSWORD_DEFAULT),
        ];
    }
}

if (!function_exists('oi_app_base_url')) {
    function oi_app_base_url(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        $parts = array_values(array_filter(explode('/', trim($script, '/'))));
        $knownTopDirs = [
            'Admin-End',
            'Resident-End',
            'Guest-End',
            'PhpFiles',
            'CSS-Styles',
            'JS-Script-Files',
            'Images',
            'UnifiedFileAttachment',
            'Fonts',
        ];

        $prefix = '';
        if (!empty($parts) && !in_array($parts[0], $knownTopDirs, true)) {
            $prefix = '/' . $parts[0];
        }

        if ($host === '' || stripos($host, 'localhost') !== false || str_starts_with($host, '127.0.0.1')) {
            return 'https://barangaysanjose-montalban.com';
        }

        return rtrim($scheme . '://' . $host . $prefix, '/');
    }
}

if (!function_exists('oi_parse_full_name')) {
    function oi_parse_full_name(string $fullName): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $fullName));
        if ($name === '') {
            return ['firstname' => '', 'middlename' => '', 'lastname' => '', 'suffix' => ''];
        }
        $parts = explode(' ', $name);
        if (count($parts) === 1) {
            return ['firstname' => $parts[0], 'middlename' => '', 'lastname' => $parts[0], 'suffix' => ''];
        }
        $firstname = array_shift($parts);
        $lastname = array_pop($parts);
        $middlename = implode(' ', $parts);
        return ['firstname' => $firstname, 'middlename' => $middlename, 'lastname' => $lastname, 'suffix' => ''];
    }
}

if (!function_exists('oi_get_status_id')) {
    function oi_get_status_id(mysqli $conn, string $name, array $types): ?int
    {
        foreach ($types as $type) {
            $stmt = $conn->prepare("
                SELECT status_id
                FROM statuslookuptbl
                WHERE status_name = ? AND status_type = ?
                LIMIT 1
            ");
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param("ss", $name, $type);
            $stmt->execute();
            $stmt->bind_result($statusId);
            $found = $stmt->fetch();
            $stmt->close();
            if ($found) {
                return (int)$statusId;
            }
        }
        return null;
    }
}

if (!function_exists('oi_insert_otp')) {
    function oi_insert_otp(mysqli $conn, string $userId, string $recipient, string $purpose, string $otpCode, int $ttlMinutes = 5): string
    {
        date_default_timezone_set('Asia/Manila');

        $gapSeconds = 15;
        $chk = $conn->prepare("
            SELECT request_timestamp
            FROM otprequesttbl
            WHERE user_id = ? AND recipient = ? AND purpose = ?
            ORDER BY request_timestamp DESC
            LIMIT 1
        ");
        if ($chk) {
            $chk->bind_param('sss', $userId, $recipient, $purpose);
            $chk->execute();
            $res = $chk->get_result();
            if ($res && ($r = $res->fetch_assoc())) {
                $lastTs = strtotime((string)$r['request_timestamp']);
                if ($lastTs && (time() - $lastTs) < $gapSeconds) {
                    $wait = $gapSeconds - (time() - $lastTs);
                    $chk->close();
                    throw new RuntimeException("Please wait {$wait}s before requesting another OTP.");
                }
            }
            $chk->close();
        }

        $otpHash = password_hash($otpCode, PASSWORD_DEFAULT);
        $requestTime = date('Y-m-d H:i:s');
        $expiryTime = date('Y-m-d H:i:s', strtotime("+{$ttlMinutes} minutes"));
        $statusPending = 6;

        $stmt = $conn->prepare("
            INSERT INTO otprequesttbl
                (user_id, recipient, purpose, otp_code_hash, otp_expiry, request_timestamp, status_id_otp)
            VALUES
                (?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare OTP insert.');
        }
        $stmt->bind_param('ssssssi', $userId, $recipient, $purpose, $otpHash, $expiryTime, $requestTime, $statusPending);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Failed to store OTP.');
        }
        $stmt->close();
        return $expiryTime;
    }
}

if (!function_exists('oi_verify_latest_otp')) {
    function oi_verify_latest_otp(mysqli $conn, string $userId, string $recipient, string $purpose, string $otpInput): void
    {
        $otpInput = trim($otpInput);
        if (!preg_match('/^\d{6}$/', $otpInput)) {
            throw new RuntimeException('Please enter a valid 6-digit OTP.');
        }

        $stmt = $conn->prepare("
            SELECT otp_id, otp_code_hash, otp_expiry, status_id_otp
            FROM otprequesttbl
            WHERE user_id = ? AND recipient = ? AND purpose = ?
            ORDER BY request_timestamp DESC
            LIMIT 1
        ");
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare OTP lookup.');
        }
        $stmt->bind_param('sss', $userId, $recipient, $purpose);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            throw new RuntimeException('OTP invalid or expired.');
        }

        $otpId = (int)$row['otp_id'];
        $otpHash = (string)$row['otp_code_hash'];
        $otpExpiry = (string)$row['otp_expiry'];
        $statusId = (int)$row['status_id_otp'];

        $statusPending = 6;
        $statusVerified = 7;
        $statusExpired = 8;

        if (strtotime($otpExpiry) < time()) {
            $up = $conn->prepare("UPDATE otprequesttbl SET status_id_otp = ? WHERE otp_id = ?");
            if ($up) {
                $up->bind_param('ii', $statusExpired, $otpId);
                $up->execute();
                $up->close();
            }
            throw new RuntimeException('OTP expired.');
        }

        if ($statusId !== $statusPending || !password_verify($otpInput, $otpHash)) {
            throw new RuntimeException('OTP invalid or expired.');
        }

        $up = $conn->prepare("UPDATE otprequesttbl SET status_id_otp = ? WHERE otp_id = ?");
        if ($up) {
            $up->bind_param('ii', $statusVerified, $otpId);
            $up->execute();
            $up->close();
        }
    }
}

if (!function_exists('oi_ensure_invite_table')) {
    function oi_ensure_invite_table(mysqli $conn): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS officialinvitetbl (
                invite_id INT NOT NULL AUTO_INCREMENT,
                invite_code VARCHAR(12) DEFAULT NULL,
                invite_token_hash VARCHAR(255) NOT NULL,
                invite_email VARCHAR(120) NOT NULL,
                invite_phone VARCHAR(20) NOT NULL,
                firstname VARCHAR(100) NOT NULL,
                middlename VARCHAR(100) DEFAULT NULL,
                lastname VARCHAR(100) NOT NULL,
                suffix VARCHAR(20) DEFAULT NULL,
                role_access VARCHAR(64) NOT NULL,
                department VARCHAR(100) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'Pending',
                onboarding_step VARCHAR(40) NOT NULL DEFAULT 'password',
                invited_by_user_id VARCHAR(12) NOT NULL,
                user_id VARCHAR(12) DEFAULT NULL,
                expires_at DATETIME NOT NULL,
                token_used_at DATETIME DEFAULT NULL,
                password_set_at DATETIME DEFAULT NULL,
                email_verified_at DATETIME DEFAULT NULL,
                phone_verified_at DATETIME DEFAULT NULL,
                profile_completed_at DATETIME DEFAULT NULL,
                accepted_at DATETIME DEFAULT NULL,
                revoked_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (invite_id),
                UNIQUE KEY uq_officialinvite_code (invite_code),
                KEY idx_officialinvite_email (invite_email),
                KEY idx_officialinvite_phone (invite_phone),
                KEY idx_officialinvite_user (user_id),
                KEY idx_officialinvite_status (status),
                KEY idx_officialinvite_expires (expires_at),
                CONSTRAINT fk_officialinvite_user FOREIGN KEY (user_id)
                    REFERENCES useraccountstbl (user_id)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ";
        $conn->query($sql);

        // Backward-compatible column additions for evolving invite metadata.
        $hasPosition = $conn->query("SHOW COLUMNS FROM officialinvitetbl LIKE 'position_access'");
        if ($hasPosition instanceof mysqli_result && $hasPosition->num_rows === 0) {
            $conn->query("ALTER TABLE officialinvitetbl ADD COLUMN position_access VARCHAR(100) DEFAULT NULL AFTER role_access");
        }

        $hasEmploymentStatus = $conn->query("SHOW COLUMNS FROM officialinvitetbl LIKE 'employment_status'");
        if ($hasEmploymentStatus instanceof mysqli_result && $hasEmploymentStatus->num_rows === 0) {
            $conn->query("ALTER TABLE officialinvitetbl ADD COLUMN employment_status VARCHAR(120) DEFAULT NULL AFTER department");
        }

        $hasAreaNumber = $conn->query("SHOW COLUMNS FROM officialinvitetbl LIKE 'area_number'");
        if ($hasAreaNumber instanceof mysqli_result && $hasAreaNumber->num_rows === 0) {
            $conn->query("ALTER TABLE officialinvitetbl ADD COLUMN area_number VARCHAR(50) DEFAULT NULL AFTER employment_status");
        }

        $hasInviteCode = $conn->query("SHOW COLUMNS FROM officialinvitetbl LIKE 'invite_code'");
        if ($hasInviteCode instanceof mysqli_result && $hasInviteCode->num_rows === 0) {
            $conn->query("ALTER TABLE officialinvitetbl ADD COLUMN invite_code VARCHAR(12) DEFAULT NULL AFTER invite_id");
        }

        $hasInviteCodeIndex = $conn->query("SHOW INDEX FROM officialinvitetbl WHERE Key_name = 'uq_officialinvite_code'");
        if ($hasInviteCodeIndex instanceof mysqli_result && $hasInviteCodeIndex->num_rows === 0) {
            $conn->query("ALTER TABLE officialinvitetbl ADD UNIQUE KEY uq_officialinvite_code (invite_code)");
        }

        $hasOfficialPosition = $conn->query("SHOW COLUMNS FROM officialinformationtbl LIKE 'position_access'");
        if ($hasOfficialPosition instanceof mysqli_result && $hasOfficialPosition->num_rows === 0) {
            $conn->query("ALTER TABLE officialinformationtbl ADD COLUMN position_access VARCHAR(100) DEFAULT NULL AFTER role_access");
            $conn->query("
                UPDATE officialinformationtbl
                SET position_access = role_access
                WHERE role_access IS NOT NULL AND TRIM(role_access) <> ''
            ");
        }

        $hasOfficialArea = $conn->query("SHOW COLUMNS FROM officialinformationtbl LIKE 'area_number'");
        if ($hasOfficialArea instanceof mysqli_result && $hasOfficialArea->num_rows === 0) {
            $conn->query("ALTER TABLE officialinformationtbl ADD COLUMN area_number VARCHAR(50) NULL AFTER department");
        }
    }
}

if (!function_exists('oi_normalize_area_code')) {
    function oi_normalize_area_code(string $areaNumber): string
    {
        $value = strtoupper(trim($areaNumber));
        if ($value === '' || $value === 'BARANGAY WIDE' || $value === 'BW') {
            return 'BW';
        }

        if (preg_match('/^AREA\s*1A$/i', $value)) {
            return '1A';
        }

        if (preg_match('/^AREA\s*0*([1-9])$/i', $value, $m)) {
            return str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }

        if (preg_match('/^(0[1-9]|1A|BW)$/i', $value, $m)) {
            return strtoupper($m[1]);
        }

        return 'BW';
    }
}

if (!function_exists('oi_generate_invite_code')) {
    function oi_generate_invite_code(mysqli $conn, string $areaNumber): string
    {
        $areaCode = oi_normalize_area_code($areaNumber);
        $prefix = date('ym') . $areaCode;
        $like = $prefix . '%';

        $stmt = $conn->prepare("
            SELECT MAX(CAST(RIGHT(invite_code, 3) AS UNSIGNED)) AS max_seq
            FROM officialinvitetbl
            WHERE invite_code LIKE ?
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to generate invite code.');
        }
        $stmt->bind_param("s", $like);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $nextSeq = (int)($row['max_seq'] ?? 0) + 1;
        if ($nextSeq > 999) {
            throw new RuntimeException("Invite code limit reached for {$prefix}.");
        }

        return $prefix . str_pad((string)$nextSeq, 3, '0', STR_PAD_LEFT);
    }
}
