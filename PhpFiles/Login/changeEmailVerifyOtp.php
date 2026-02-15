<?php
require_once __DIR__ . '/changeEmailCommon.php';
require_once __DIR__ . '/../General/connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cem_json(405, ['success' => false, 'message' => 'Method not allowed']);
}

$userId = cem_require_auth();

if (!isset($conn) || !($conn instanceof mysqli)) {
    cem_json(500, ['success' => false, 'message' => 'Database connection unavailable']);
}

$payload = cem_read_payload();
$method = strtolower(trim((string)($payload['method'] ?? 'phone')));
$otp = trim((string)($payload['otp'] ?? ''));

if (!in_array($method, ['phone', 'email'], true)) {
    cem_json(400, ['success' => false, 'message' => 'Invalid verification method.']);
}

try {
    $acct = cem_get_user_account($conn, $userId);
    $email = trim((string)($acct['email'] ?? ''));
    $emailVerified = ((int)($acct['email_verify'] ?? 0)) === 1;
    $phone10 = cem_sanitize_phone10((string)($acct['phone_number'] ?? ''));

    if ($method === 'email') {
        if (!$emailVerified || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            cem_json(400, ['success' => false, 'message' => 'Email verification is required to use email OTP.']);
        }

        $sess = $_SESSION['change_email_email_otp'] ?? null;
        if (!is_array($sess) || ($sess['recipient'] ?? '') !== $email) {
            cem_json(400, ['success' => false, 'message' => 'OTP invalid or expired.']);
        }
        if (($sess['status'] ?? '') !== 'pending') {
            cem_json(400, ['success' => false, 'message' => 'OTP invalid or already used.']);
        }
        if (time() > (int)($sess['expires_at'] ?? 0)) {
            $_SESSION['change_email_email_otp']['status'] = 'expired';
            cem_json(400, ['success' => false, 'message' => 'OTP expired.']);
        }
        $hash = (string)($sess['otp_hash'] ?? '');
        if ($hash === '' || !password_verify($otp, $hash)) {
            cem_json(400, ['success' => false, 'message' => 'OTP invalid or expired.']);
        }
        $_SESSION['change_email_email_otp']['status'] = 'verified';
        unset($_SESSION['change_email_email_otp']);
    } else {
        if (!cem_is_valid_phone10($phone10)) {
            cem_json(400, ['success' => false, 'message' => 'Your current phone number is not valid.']);
        }
        cem_verify_latest_sms_otp($conn, $userId, $phone10, 'change_email_old_phone', $otp);
    }

    $_SESSION['change_email_old_verified'] = [
        'verified' => true,
        'method' => $method,
        'verified_at' => time(),
    ];

    cem_json(200, ['success' => true, 'message' => 'Verified.']);
} catch (Throwable $e) {
    cem_json(500, ['success' => false, 'message' => 'Server error. Please try again.']);
}

