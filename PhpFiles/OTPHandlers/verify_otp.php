<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../General/security.php';
require_once '../General/connection.php';

if (
    !is_string($_POST['recipient'] ?? null)
    || !is_string($_POST['otp'] ?? null)
    || !is_string($_POST['purpose'] ?? null)
) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$rawRecipient = trim((string)$_POST['recipient']);
$otp_input    = trim((string)$_POST['otp']);
$purpose      = trim((string)$_POST['purpose']);

if (!preg_match('/^\d{6}$/', $otp_input)) {
    echo json_encode(['success' => false, 'error' => 'Please enter a valid 6-digit OTP.']);
    exit;
}

if (!in_array($purpose, ['signup', 'forgot', 'inactive', 'admin_2fa', 'guest_appointment', 'guest_complaint'], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid OTP purpose.']);
    exit;
}

if ($purpose === 'admin_2fa' && (string)($_SESSION['pending_verify'] ?? '') !== 'admin_2fa') {
    echo json_encode(['success' => false, 'error' => 'Two-factor session expired. Please login again.']);
    exit;
}

// ===== Normalize recipient to 10-digit DB format =====
// Accepts: 09XXXXXXXXX or 9XXXXXXXXX
if (preg_match('/^09\d{9}$/', $rawRecipient)) {
    $recipient = substr($rawRecipient, 1); // 9XXXXXXXXX
} elseif (preg_match('/^9\d{9}$/', $rawRecipient)) {
    $recipient = $rawRecipient;
} elseif (preg_match('/^0\d{10}$/', $rawRecipient)) {
    $recipient = substr($rawRecipient, 1); // also accept 0XXXXXXXXX
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid phone number']);
    exit;
}

 

// ===== Manila timezone =====
date_default_timezone_set('Asia/Manila');
$current_time = date('Y-m-d H:i:s');

// ===== Fetch latest OTP request =====
$stmt = $conn->prepare("
    SELECT otp_id, otp_code_hash, otp_expiry, status_id_otp
    FROM otprequesttbl
    WHERE recipient = ? AND purpose = ?
    ORDER BY request_timestamp DESC
    LIMIT 1
");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to verify OTP. Please try again.']);
    exit;
}
$stmt->bind_param("ss", $recipient, $purpose);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'error' => 'OTP invalid or expired']);
    exit;
}

$row = $result->fetch_assoc();
$stmt->close();

$otp_id     = (int)$row['otp_id'];
$otp_hash   = (string)$row['otp_code_hash'];
$otp_expiry = (string)$row['otp_expiry'];
$status_id  = (int)$row['status_id_otp'];

// ===== Status IDs =====
$STATUS_PENDING  = 6;
$STATUS_VERIFIED = 7;
$STATUS_EXPIRED  = 8;

// ===== Expiry check =====
if (strtotime($otp_expiry) < strtotime($current_time)) {
    $update = $conn->prepare("UPDATE otprequesttbl SET status_id_otp = ? WHERE otp_id = ?");
    if ($update) {
        $update->bind_param("ii", $STATUS_EXPIRED, $otp_id);
        $update->execute();
        $update->close();
    }
    unset($_SESSION['otp_verification_attempts'][(string)$otp_id]);

    echo json_encode(['success' => false, 'error' => 'OTP expired']);
    exit;
}

// ===== Already used check =====
if ($status_id !== $STATUS_PENDING) {
    echo json_encode(['success' => false, 'error' => 'OTP invalid or already used']);
    exit;
}

// Bound guesses for the current OTP without requiring a database schema change.
// The OTP itself is invalidated after the fifth failed attempt in this session.
$attemptKey = (string)$otp_id;
$attemptStore = $_SESSION['otp_verification_attempts'] ?? [];
if (!is_array($attemptStore)) {
    $attemptStore = [];
}
$now = time();
foreach ($attemptStore as $key => $attemptState) {
    $updatedAt = is_array($attemptState) ? (int)($attemptState['updated_at'] ?? 0) : 0;
    if ($updatedAt <= 0 || ($now - $updatedAt) > 600) {
        unset($attemptStore[$key]);
    }
}
$currentAttemptState = $attemptStore[$attemptKey] ?? [];
$attemptCount = is_array($currentAttemptState) ? (int)($currentAttemptState['count'] ?? 0) : 0;
if ($attemptCount >= 5) {
    $update = $conn->prepare("UPDATE otprequesttbl SET status_id_otp = ? WHERE otp_id = ? AND status_id_otp = ?");
    if ($update) {
        $update->bind_param("iii", $STATUS_EXPIRED, $otp_id, $STATUS_PENDING);
        $update->execute();
        $update->close();
    }
    unset($attemptStore[$attemptKey]);
    $_SESSION['otp_verification_attempts'] = $attemptStore;
    echo json_encode(['success' => false, 'error' => 'Too many failed attempts. Please request a new OTP.']);
    exit;
}

// ===== Verify OTP =====
if (!password_verify($otp_input, $otp_hash)) {
    $attemptCount++;
    $attemptStore[$attemptKey] = [
        'count' => $attemptCount,
        'updated_at' => $now,
    ];

    if ($attemptCount >= 5) {
        $update = $conn->prepare("UPDATE otprequesttbl SET status_id_otp = ? WHERE otp_id = ? AND status_id_otp = ?");
        if ($update) {
            $update->bind_param("iii", $STATUS_EXPIRED, $otp_id, $STATUS_PENDING);
            $update->execute();
            $update->close();
        }
        unset($attemptStore[$attemptKey]);
        $_SESSION['otp_verification_attempts'] = $attemptStore;
        echo json_encode(['success' => false, 'error' => 'Too many failed attempts. Please request a new OTP.']);
        exit;
    }

    $_SESSION['otp_verification_attempts'] = $attemptStore;
    echo json_encode(['success' => false, 'error' => 'OTP invalid or expired']);
    exit;
}

// ===== Mark as verified =====
$update = $conn->prepare("UPDATE otprequesttbl SET status_id_otp = ? WHERE otp_id = ?");
if (!$update) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to verify OTP. Please try again.']);
    exit;
}
$update->bind_param("ii", $STATUS_VERIFIED, $otp_id);
$update->execute();
$update->close();
unset($attemptStore[$attemptKey]);
$_SESSION['otp_verification_attempts'] = $attemptStore;

// Bind signup flow to a verified server-side session.
if ($purpose === 'signup') {
    $_SESSION['signup_otp_verified'] = [
        'phone' => (string)$recipient,
        'otp_id' => (int)$otp_id,
        'verified_at' => time()
    ];
}

// Bind forgot-password flow to a verified server-side session.
if ($purpose === 'forgot') {
    $phoneLookupHash = pii_lookup_hash($recipient, 'useraccount.phone');
    $acctStmt = $conn->prepare("
        SELECT user_id, email, phone_number
        FROM useraccountstbl
        WHERE phone_lookup_hash = ?
        LIMIT 1
    ");
    if ($acctStmt) {
        $acctStmt->bind_param("s", $phoneLookupHash);
        $acctStmt->execute();
        $acctRes = $acctStmt->get_result();
        if ($acctRow = $acctRes->fetch_assoc()) {
            $acctRow = pii_decrypt_useraccount_row($acctRow) ?? $acctRow;
            $_SESSION['password_reset_verified'] = [
                'user_id' => (string)$acctRow['user_id'],
                'email' => (string)$acctRow['email'],
                'phone' => (string)$acctRow['phone_number'],
                'verified_at' => time()
            ];
        }
        $acctStmt->close();
    }
}

if ($purpose === 'guest_appointment') {
    $_SESSION['guest_appointment_otp_verified'] = [
        'phone' => (string)$recipient,
        'otp_id' => (int)$otp_id,
        'verified_at' => time(),
    ];
}

if ($purpose === 'guest_complaint') {
    $_SESSION['guest_complaint_otp_verified'] = [
        'phone' => (string)$recipient,
        'otp_id' => (int)$otp_id,
        'verified_at' => time(),
    ];
}

echo json_encode(['success' => true]);
?>
