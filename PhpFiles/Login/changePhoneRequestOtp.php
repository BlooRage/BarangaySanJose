<?php
require_once __DIR__ . '/changePhoneCommon.php';
require_once __DIR__ . '/../General/connection.php';

// Email support
require_once __DIR__ . '/../EmailHandlers/emailSender.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cpn_json(405, ['success' => false, 'message' => 'Method not allowed']);
}

$userId = cpn_require_auth();

if (!isset($conn) || !($conn instanceof mysqli)) {
    cpn_json(500, ['success' => false, 'message' => 'Database connection unavailable']);
}

$payload = cpn_read_payload();
$method = strtolower(trim((string)($payload['method'] ?? 'phone')));
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
        $recipient = $phone10; // store as 10 digits
    }

    $otpCode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    if ($method === 'email') {
        // Store email OTP in session (DB schema for otprequesttbl may be phone-only in some installs).
        // Also includes a short anti-spam gate.
        $prev = $_SESSION['change_phone_email_otp'] ?? null;
        if (is_array($prev) && !empty($prev['requested_at']) && (time() - (int)$prev['requested_at']) < 15) {
            $wait = 15 - (time() - (int)$prev['requested_at']);
            cpn_json(429, ['success' => false, 'message' => "Please wait {$wait}s before requesting another OTP."]);
        }

        $_SESSION['change_phone_email_otp'] = [
            'purpose' => $purpose,
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
            'template' => 'emails/changePhoneOtp.php',
            'data' => [
                'otp' => $otpCode,
                'expiresNote' => 'This OTP expires in 5 minutes.',
            ],
        ]);

        if (!$sent) {
            cpn_json(500, ['success' => false, 'message' => 'Unable to send OTP email. Please try again.']);
        }

        cpn_json(200, [
            'success' => true,
            'masked' => $email,
            'message' => 'OTP sent via email.',
        ]);
    }

    // SMS
    cpn_insert_otp($conn, $userId, $recipient, $purpose, $otpCode, 5);
    require_once __DIR__ . '/../General/sendSMS.php';
    $smsRecipient = '0' . $phone10; // 09xxxxxxxxx
    $smsMsg = "Did you request to change your phone number? Your OTP code is {$otpCode}";

    $sent = sendSMS($smsRecipient, $smsMsg, $otpCode);
    if (!$sent) {
        cpn_json(500, ['success' => false, 'message' => 'Unable to send OTP SMS. Please try again.']);
    }

    cpn_json(200, [
        'success' => true,
        'masked' => cpn_mask_phone10($phone10),
        'message' => 'OTP sent via SMS.',
    ]);
} catch (Throwable $e) {
    cpn_json(500, ['success' => false, 'message' => 'Server error. Please try again.']);
}
