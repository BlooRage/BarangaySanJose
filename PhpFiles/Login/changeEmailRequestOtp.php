<?php
require_once __DIR__ . '/changeEmailCommon.php';
require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/sendSMS.php';
require_once __DIR__ . '/../EmailHandlers/emailSender.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cem_json(405, ['success' => false, 'message' => 'Method not allowed']);
}

$userId = cem_require_auth();

if (!isset($conn) || !($conn instanceof mysqli)) {
    cem_json(500, ['success' => false, 'message' => 'Database connection unavailable']);
}

$payload = cem_read_payload();
$method = strtolower(trim((string)($payload['method'] ?? 'phone')));
if (!in_array($method, ['phone', 'email'], true)) {
    cem_json(400, ['success' => false, 'message' => 'Invalid verification method.']);
}

try {
    $acct = cem_get_user_account($conn, $userId);
    $email = trim((string)($acct['email'] ?? ''));
    $emailVerified = ((int)($acct['email_verify'] ?? 0)) === 1;
    $phone10 = cem_sanitize_phone10((string)($acct['phone_number'] ?? ''));

    $otpCode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    if ($method === 'email') {
        if (!$emailVerified || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            cem_json(400, ['success' => false, 'message' => 'Email verification is required to use email OTP.']);
        }

        // Store email OTP in session (keeps DB OTP table phone-only safe).
        $prev = $_SESSION['change_email_email_otp'] ?? null;
        if (is_array($prev) && !empty($prev['requested_at']) && (time() - (int)$prev['requested_at']) < 15) {
            $wait = 15 - (time() - (int)$prev['requested_at']);
            cem_json(429, ['success' => false, 'message' => "Please wait {$wait}s before requesting another OTP."]);
        }

        $_SESSION['change_email_email_otp'] = [
            'recipient' => $email,
            'otp_hash' => password_hash($otpCode, PASSWORD_DEFAULT),
            'expires_at' => time() + 300,
            'requested_at' => time(),
            'status' => 'pending',
        ];

        $smtp = require __DIR__ . '/../General/mailConfigurations.php';
        $sender = new EmailSender($smtp);

        $subject = "Your OTP code is {$otpCode}";
        $sent = $sender->send([
            'to' => $email,
            'from_email' => 'otp@barangaysanjose-montalban.com',
            'from_name' => 'Barangay San Jose OTP',
            'subject' => $subject,
            'template' => 'emails/changeEmailOtp.php',
            'data' => [
                'otp' => $otpCode,
                'expiresNote' => 'This OTP expires in 5 minutes.',
            ],
        ]);

        if (!$sent) {
            cem_json(500, ['success' => false, 'message' => 'Unable to send OTP email. Please try again.']);
        }

        cem_json(200, [
            'success' => true,
            'masked' => cem_mask_email($email),
            'message' => 'OTP sent via email.',
        ]);
    }

    // SMS
    if (!cem_is_valid_phone10($phone10)) {
        cem_json(400, ['success' => false, 'message' => 'Your current phone number is not valid.']);
    }

    $purpose = 'change_email_old_phone';
    cem_insert_sms_otp($conn, $userId, $phone10, $purpose, $otpCode, 5);

    $smsRecipient = '0' . $phone10;
    $smsMsg = "Did you request to change your email? Your OTP code is {$otpCode}";
    $sent = sendSMS($smsRecipient, $smsMsg, $otpCode);
    if (!$sent) {
        cem_json(500, ['success' => false, 'message' => 'Unable to send OTP SMS. Please try again.']);
    }

    cem_json(200, ['success' => true, 'masked' => cem_mask_phone10($phone10), 'message' => 'OTP sent via SMS.']);
} catch (Throwable $e) {
    cem_json(500, ['success' => false, 'message' => 'Server error. Please try again.']);
}
