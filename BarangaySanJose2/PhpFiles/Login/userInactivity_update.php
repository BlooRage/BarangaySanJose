<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

require __DIR__ . '/../General/connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'error' => 'Invalid request']);
  exit;
}

if (!isset($_SESSION['pending_user_id']) || ($_SESSION['pending_verify'] ?? '') !== 'inactive') {
  echo json_encode(['success' => false, 'error' => 'Session expired. Please login again.']);
  exit;
}

$user_id = $_SESSION['pending_user_id'];

// Get user phone + role
$stmt = $conn->prepare("SELECT phone_number, role_access, status_id_account FROM useraccountstbl WHERE user_id = ? LIMIT 1");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if (!$user) {
  echo json_encode(['success' => false, 'error' => 'Account not found.']);
  exit;
}

$digits = preg_replace('/\D/', '', (string)$user['phone_number']);
$recipient = substr($digits, -10); // 9XXXXXXXXX
if (!$recipient || strlen($recipient) !== 10) {
  echo json_encode(['success' => false, 'error' => 'Invalid phone number on record.']);
  exit;
}

// Ensure latest OTP for this phone + purpose is VERIFIED (status_id_otp=7 in your verify_otp.php)
$purpose = "inactive";
$STATUS_VERIFIED = 7;

$otpStmt = $conn->prepare("
  SELECT status_id_otp
  FROM otprequesttbl
  WHERE recipient = ? AND purpose = ?
  ORDER BY request_timestamp DESC
  LIMIT 1
");
$otpStmt->bind_param("ss", $recipient, $purpose);
$otpStmt->execute();
$otpRes = $otpStmt->get_result();

if ($otpRes->num_rows === 0) {
  echo json_encode(['success' => false, 'error' => 'No OTP found. Please request OTP again.']);
  exit;
}
$otpRow = $otpRes->fetch_assoc();

if ((int)$otpRow['status_id_otp'] !== $STATUS_VERIFIED) {
  echo json_encode(['success' => false, 'error' => 'OTP not verified.']);
  exit;
}

// Lookup Active/Inactive IDs
$lookup = $conn->prepare("SELECT status_id, status_name FROM statuslookuptbl WHERE status_type='UserAccount'");
$lookup->execute();
$lres = $lookup->get_result();

$statuses = [];
while ($r = $lres->fetch_assoc()) {
  $statuses[strtolower(trim($r['status_name']))] = $r['status_id'];
}
$lookup->close();

$activeStatusId   = $statuses['active'] ?? null;
$inactiveStatusId = $statuses['inactive'] ?? null;

if ($activeStatusId === null || $inactiveStatusId === null) {
  echo json_encode(['success' => false, 'error' => 'Missing Active/Inactive in statuslookuptbl.']);
  exit;
}

// Activate if currently inactive
if ((int)$user['status_id_account'] === (int)$inactiveStatusId) {
  $up = $conn->prepare("UPDATE useraccountstbl SET status_id_account = ?, last_login = NOW() WHERE user_id = ?");
  $up->bind_param("is", $activeStatusId, $user_id);
  $up->execute();
}

// Create real login session
unset($_SESSION['pending_user_id'], $_SESSION['pending_verify']);

$_SESSION['user_id']   = $user_id;
$_SESSION['role']      = $user['role_access'];
$_SESSION['logged_in'] = true;

echo json_encode([
  'success' => true,
  'redirect' => '../PhpFiles/Login/unifiedProfileCheck.php'
]);
exit;
