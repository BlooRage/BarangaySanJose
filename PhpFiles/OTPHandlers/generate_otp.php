<?php
header('Content-Type: application/json');
require_once '../General/connection.php';
require_once '../General/sendSMS.php';

// ===== Validate input =====
if (!isset($_POST['recipient']) || !isset($_POST['purpose'])) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$rawRecipient = trim($_POST['recipient']);
$purpose      = trim($_POST['purpose']);
$user_id      = $_POST['user_id'] ?? null;

// ===== Normalize recipient to 10 digits ONLY for DB =====
// Accepts: 09XXXXXXXXX or 9XXXXXXXXX
if (preg_match('/^09\d{9}$/', $rawRecipient)) {
    $recipient_db = substr($rawRecipient, 1); // remove leading 0
} elseif (preg_match('/^9\d{9}$/', $rawRecipient)) {
    $recipient_db = $rawRecipient;
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid phone number format']);
    exit;
}

// ===== Generate 6-digit OTP =====
$otp_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

// ===== Hash OTP =====
$otp_hash = password_hash($otp_code, PASSWORD_DEFAULT);

// ===== Manila Time =====
date_default_timezone_set('Asia/Manila');
$request_time = date('Y-m-d H:i:s');
$expiry_time  = date('Y-m-d H:i:s', strtotime('+5 minutes'));

// ===== Status IDs =====
$STATUS_PENDING = 6;

// ===== Insert OTP Request =====
$stmt = $conn->prepare("
    INSERT INTO otprequesttbl
    (user_id, recipient, purpose, otp_code_hash, otp_expiry, request_timestamp, status_id_otp)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "ssssssi",
    $user_id,
    $recipient_db,   // ðŸ”¥ ALWAYS 10 DIGITS
    $purpose,
    $otp_hash,
    $expiry_time,
    $request_time,
    $STATUS_PENDING
);

$stmt->execute();
$stmt->close();

// ===== Send OTP on server-side only (do not expose OTP to client) =====
$recipient_sms = '0' . $recipient_db; // 11 digits
$message = "Your OTP code is $otp_code";
$sent = sendSMS($recipient_sms, $message, $otp_code);

if (!$sent) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to send OTP. Please try again.'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'expires_at' => $expiry_time
]);
?>
