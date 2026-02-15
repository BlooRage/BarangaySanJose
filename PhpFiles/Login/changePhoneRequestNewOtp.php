<?php
require_once __DIR__ . '/changePhoneCommon.php';
require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/sendSMS.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cpn_json(405, ['success' => false, 'message' => 'Method not allowed']);
}

$userId = cpn_require_auth();

if (!isset($conn) || !($conn instanceof mysqli)) {
    cpn_json(500, ['success' => false, 'message' => 'Database connection unavailable']);
}

$payload = cpn_read_payload();
$newPhone10 = cpn_sanitize_phone10((string)($payload['new_phone'] ?? ''));

// Require old verification within 10 minutes
$old = $_SESSION['change_phone_old_verified'] ?? null;
if (!is_array($old) || empty($old['verified']) || empty($old['verified_at']) || (time() - (int)$old['verified_at']) > 600) {
    cpn_json(403, ['success' => false, 'message' => 'Please verify your identity first.']);
}

if (!cpn_is_valid_phone10($newPhone10)) {
    cpn_json(400, ['success' => false, 'message' => 'Phone number must start with 9 and be exactly 10 digits.']);
}

try {
    $acct = cpn_get_user_account($conn, $userId);
    $oldPhone10 = cpn_sanitize_phone10((string)($acct['phone_number'] ?? ''));
    if (cpn_is_valid_phone10($oldPhone10) && $newPhone10 === $oldPhone10) {
        cpn_json(400, ['success' => false, 'message' => 'New phone number must be different from your current phone number.']);
    }

    // Ensure phone isn't already used by another account
    $chk = $conn->prepare("SELECT user_id FROM useraccountstbl WHERE phone_number = ? AND user_id <> ? LIMIT 1");
    if ($chk) {
        $chk->bind_param('ss', $newPhone10, $userId);
        $chk->execute();
        $res = $chk->get_result();
        $exists = $res && $res->num_rows > 0;
        $chk->close();
        if ($exists) {
            cpn_json(400, ['success' => false, 'message' => 'That phone number is already registered.']);
        }
    }

    $purpose = 'change_phone_new_phone';
    $otpCode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    cpn_insert_otp($conn, $userId, $newPhone10, $purpose, $otpCode, 5);

    $_SESSION['change_phone_pending_new_phone'] = [
        'phone10' => $newPhone10,
        'sent_at' => time(),
    ];

    $smsRecipient = '0' . $newPhone10;
    $smsMsg = "Confirm your new phone number. Your OTP code is {$otpCode}";
    $sent = sendSMS($smsRecipient, $smsMsg, $otpCode);
    if (!$sent) {
        cpn_json(500, ['success' => false, 'message' => 'Unable to send OTP SMS. Please try again.']);
    }

    cpn_json(200, [
        'success' => true,
        'masked' => cpn_mask_phone10($newPhone10),
        'message' => 'OTP sent.',
    ]);
} catch (Throwable $e) {
    cpn_json(500, ['success' => false, 'message' => 'Server error. Please try again.']);
}

