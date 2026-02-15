<?php
require_once __DIR__ . '/changePhoneCommon.php';
require_once __DIR__ . '/../General/connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cpn_json(405, ['success' => false, 'message' => 'Method not allowed']);
}

$userId = cpn_require_auth();

if (!isset($conn) || !($conn instanceof mysqli)) {
    cpn_json(500, ['success' => false, 'message' => 'Database connection unavailable']);
}

$payload = cpn_read_payload();
$method = strtolower(trim((string)($payload['method'] ?? 'phone')));
$otp = trim((string)($payload['otp'] ?? ''));

if (!in_array($method, ['phone', 'email'], true)) {
    cpn_json(400, ['success' => false, 'message' => 'Invalid verification method.']);
}

try {
    $acct = cpn_get_user_account($conn, $userId);
    $email = trim((string)($acct['email'] ?? ''));
    $emailVerified = ((int)($acct['email_verify'] ?? 0)) === 1;
    $phone10 = cpn_sanitize_phone10((string)($acct['phone_number'] ?? ''));

    if ($method === 'email') {
        if (!$emailVerified || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            cpn_json(400, ['success' => false, 'message' => 'Email verification is required to use email OTP.']);
        }
        $purpose = 'change_phone_old_email';
        $recipient = $email;
    } else {
        if (!cpn_is_valid_phone10($phone10)) {
            cpn_json(400, ['success' => false, 'message' => 'Your current phone number is not valid.']);
        }
        $purpose = 'change_phone_old_phone';
        $recipient = $phone10;
    }

    if ($method === 'email') {
        $sess = $_SESSION['change_phone_email_otp'] ?? null;
        if (!is_array($sess) || ($sess['purpose'] ?? '') !== $purpose || ($sess['recipient'] ?? '') !== $email) {
            cpn_json(400, ['success' => false, 'message' => 'OTP invalid or expired.']);
        }
        if (($sess['status'] ?? '') !== 'pending') {
            cpn_json(400, ['success' => false, 'message' => 'OTP invalid or already used.']);
        }
        if (time() > (int)($sess['expires_at'] ?? 0)) {
            $_SESSION['change_phone_email_otp']['status'] = 'expired';
            cpn_json(400, ['success' => false, 'message' => 'OTP expired.']);
        }
        $hash = (string)($sess['otp_hash'] ?? '');
        if ($hash === '' || !password_verify($otp, $hash)) {
            cpn_json(400, ['success' => false, 'message' => 'OTP invalid or expired.']);
        }
        $_SESSION['change_phone_email_otp']['status'] = 'verified';
    } else {
        cpn_verify_latest_otp($conn, $userId, $recipient, $purpose, $otp);
    }

    $_SESSION['change_phone_old_verified'] = [
        'verified' => true,
        'method' => $method,
        'verified_at' => time(),
    ];

    // Reduce session clutter after successful email OTP verification.
    if ($method === 'email') {
        unset($_SESSION['change_phone_email_otp']);
    }

    cpn_json(200, ['success' => true, 'message' => 'Verified.']);
} catch (Throwable $e) {
    cpn_json(500, ['success' => false, 'message' => 'Server error. Please try again.']);
}
