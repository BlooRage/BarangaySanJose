<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../General/uniqueIDGenerate.php';

function cem_read_payload(): array
{
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }
    return is_array($payload) ? $payload : [];
}

function cem_json(int $status, array $data): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function cem_require_auth(): string
{
    $uid = (string)($_SESSION['user_id'] ?? '');
    if ($uid === '') {
        cem_json(401, ['success' => false, 'message' => 'Unauthorized']);
    }
    return $uid;
}

function cem_set_manila_tz(): void
{
    date_default_timezone_set('Asia/Manila');
}

function cem_sanitize_phone10(string $v): string
{
    return substr(preg_replace('/\\D+/', '', $v), 0, 10);
}

function cem_is_valid_phone10(string $v): bool
{
    return (bool)preg_match('/^9\\d{9}$/', $v);
}

function cem_mask_phone10(string $v): string
{
    $p = cem_sanitize_phone10($v);
    if (!cem_is_valid_phone10($p)) return '+63 •••••• XXXX';
    return '+63 •••••• ' . substr($p, -4);
}

function cem_mask_email(string $email): string
{
    $e = trim((string)$email);
    $at = strpos($e, '@');
    if ($at === false || $at < 2) return '••••@••••';
    $local = substr($e, 0, $at);
    $domain = substr($e, $at);
    $prefix = substr($local, 0, 2);
    return $prefix . '••••' . $domain;
}

function cem_get_user_account(mysqli $conn, string $userId): array
{
    $stmt = $conn->prepare("SELECT user_id, email, email_verify, phone_number, phoneNum_verify FROM useraccountstbl WHERE user_id = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception('Failed to prepare account query.');
    }
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$row) {
        throw new Exception('Account not found.');
    }
    return pii_decrypt_useraccount_row($row) ?? [];
}

function cem_insert_sms_otp(mysqli $conn, string $userId, string $recipientPhone10, string $purpose, string $otpCode, int $ttlMinutes = 5): void
{
    cem_set_manila_tz();

    $recipientPhone10 = cem_sanitize_phone10($recipientPhone10);
    if (!cem_is_valid_phone10($recipientPhone10)) {
        throw new Exception('Invalid phone number.');
    }

    // Basic anti-spam: 15s gap for same (user, recipient, purpose)
    $gapSeconds = 15;
    $chk = $conn->prepare("
        SELECT request_timestamp
        FROM otprequesttbl
        WHERE user_id = ? AND recipient = ? AND purpose = ?
        ORDER BY request_timestamp DESC
        LIMIT 1
    ");
    if ($chk) {
        $chk->bind_param('sss', $userId, $recipientPhone10, $purpose);
        $chk->execute();
        $chkRes = $chk->get_result();
        if ($chkRes && ($r = $chkRes->fetch_assoc())) {
            $lastTs = strtotime((string)$r['request_timestamp']);
            if ($lastTs && (time() - $lastTs) < $gapSeconds) {
                $wait = $gapSeconds - (time() - $lastTs);
                $chk->close();
                cem_json(429, ['success' => false, 'message' => "Please wait {$wait}s before requesting another OTP."]);
            }
        }
        $chk->close();
    }

    $otpHash = password_hash($otpCode, PASSWORD_DEFAULT);
    $requestTime = date('Y-m-d H:i:s');
    $expiryTime = date('Y-m-d H:i:s', strtotime("+{$ttlMinutes} minutes"));

    $STATUS_PENDING = 6;
    insertOtpRequest($conn, $userId, $recipientPhone10, $purpose, $otpHash, $expiryTime, $requestTime, $STATUS_PENDING);
}

function cem_verify_latest_sms_otp(mysqli $conn, string $userId, string $recipientPhone10, string $purpose, string $otpInput): void
{
    cem_set_manila_tz();

    $otpInput = trim((string)$otpInput);
    if (!preg_match('/^\\d{6}$/', $otpInput)) {
        cem_json(400, ['success' => false, 'message' => 'Please enter the 6-digit OTP.']);
    }

    $recipientPhone10 = cem_sanitize_phone10($recipientPhone10);
    if (!cem_is_valid_phone10($recipientPhone10)) {
        cem_json(400, ['success' => false, 'message' => 'Invalid phone number.']);
    }

    $stmt = $conn->prepare("
        SELECT otp_id, otp_code_hash, otp_expiry, status_id_otp
        FROM otprequesttbl
        WHERE user_id = ? AND recipient = ? AND purpose = ?
        ORDER BY request_timestamp DESC
        LIMIT 1
    ");
    if (!$stmt) {
        throw new Exception('Failed to prepare OTP lookup.');
    }
    $stmt->bind_param('sss', $userId, $recipientPhone10, $purpose);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) cem_json(400, ['success' => false, 'message' => 'OTP invalid or expired.']);

    $otpId = (int)$row['otp_id'];
    $otpHash = (string)$row['otp_code_hash'];
    $otpExpiry = (string)$row['otp_expiry'];
    $statusId = (int)$row['status_id_otp'];

    $STATUS_PENDING = 6;
    $STATUS_VERIFIED = 7;
    $STATUS_EXPIRED = 8;

    if (strtotime($otpExpiry) < time()) {
        $up = $conn->prepare("UPDATE otprequesttbl SET status_id_otp = ? WHERE otp_id = ?");
        if ($up) {
            $up->bind_param('ii', $STATUS_EXPIRED, $otpId);
            $up->execute();
            $up->close();
        }
        cem_json(400, ['success' => false, 'message' => 'OTP expired.']);
    }

    if ($statusId !== $STATUS_PENDING) {
        cem_json(400, ['success' => false, 'message' => 'OTP invalid or already used.']);
    }

    if (!password_verify($otpInput, $otpHash)) {
        cem_json(400, ['success' => false, 'message' => 'OTP invalid or expired.']);
    }

    $up = $conn->prepare("UPDATE otprequesttbl SET status_id_otp = ? WHERE otp_id = ?");
    if ($up) {
        $up->bind_param('ii', $STATUS_VERIFIED, $otpId);
        $up->execute();
        $up->close();
    }
}
