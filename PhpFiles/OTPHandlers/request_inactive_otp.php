<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../General/security.php';
header('Content-Type: application/json; charset=UTF-8');
date_default_timezone_set('Asia/Manila');

require __DIR__ . '/../General/connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'error' => 'Invalid request']);
  exit;
}

$pendingPurpose = (string)($_SESSION['pending_verify'] ?? '');
if (!isset($_SESSION['pending_user_id']) || !in_array($pendingPurpose, ['inactive', 'admin_2fa'], true)) {
  echo json_encode(['success' => false, 'error' => 'Session expired. Please login again.']);
  exit;
}

$user_id = $_SESSION['pending_user_id'];

// Query the registered phone number from the database.
$stmt = $conn->prepare("SELECT phone_number FROM useraccountstbl WHERE user_id = ? LIMIT 1");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$row = $row ? (pii_decrypt_useraccount_row($row) ?? $row) : null;

if (!$row) {
  echo json_encode(['success' => false, 'error' => 'Account not found.']);
  exit;
}

$digits = preg_replace('/\D/', '', (string)$row['phone_number']);
$phone10 = substr($digits, -10); // 9XXXXXXXXX

if (!$phone10 || strlen($phone10) !== 10) {
  echo json_encode(['success' => false, 'error' => 'Invalid phone number on record.']);
  exit;
}

$masked = '+63 •••••• ' . substr($phone10, -4);

echo json_encode([
  'success' => true,
  'phone10' => $phone10,          // Return the registered phone (10 digits).
  'phone_masked' => $masked
  ,'purpose' => $pendingPurpose
]);
exit;
