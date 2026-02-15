<?php
require_once '../General/connection.php';
session_start();
header('Content-Type: application/json');

if (!isset($_POST['recipient']) || !isset($_POST['otp']) || !isset($_POST['purpose'])) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$rawRecipient = trim($_POST['recipient']);
$otp_input    = trim($_POST['otp']);
$purpose      = trim($_POST['purpose']);

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
$stmt->bind_param("ss", $recipient, $purpose);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'OTP invalid or expired']);
    exit;
}

$row = $result->fetch_assoc();

$otp_id     = $row['otp_id'];
$otp_hash   = $row['otp_code_hash'];
$otp_expiry = $row['otp_expiry'];
$status_id  = $row['status_id_otp'];

// ===== Status IDs =====
$STATUS_PENDING  = 6;
$STATUS_VERIFIED = 7;
$STATUS_EXPIRED  = 8;

// ===== Expiry check =====
if (strtotime($otp_expiry) < strtotime($current_time)) {
    $update = $conn->prepare("UPDATE otprequesttbl SET status_id_otp = ? WHERE otp_id = ?");
    $update->bind_param("ii", $STATUS_EXPIRED, $otp_id);
    $update->execute();

    echo json_encode(['success' => false, 'error' => 'OTP expired']);
    exit;
}

// ===== Already used check =====
if ($status_id !== $STATUS_PENDING) {
    echo json_encode(['success' => false, 'error' => 'OTP invalid or already used']);
    exit;
}

// ===== Verify OTP =====
if (!password_verify($otp_input, $otp_hash)) {
    echo json_encode(['success' => false, 'error' => 'OTP invalid or expired']);
    exit;
}

// ===== Mark as verified =====
$update = $conn->prepare("UPDATE otprequesttbl SET status_id_otp = ? WHERE otp_id = ?");
$update->bind_param("ii", $STATUS_VERIFIED, $otp_id);
$update->execute();

// Bind forgot-password flow to a verified server-side session.
if ($purpose === 'forgot') {
    $acctStmt = $conn->prepare("
        SELECT user_id, email, phone_number
        FROM useraccountstbl
        WHERE phone_number = ?
        LIMIT 1
    ");
    if ($acctStmt) {
        $acctStmt->bind_param("s", $recipient);
        $acctStmt->execute();
        $acctRes = $acctStmt->get_result();
        if ($acctRow = $acctRes->fetch_assoc()) {
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

echo json_encode(['success' => true]);
?>
