<?php
// PhpFiles/EmailHandlers/smtpHealthCheck.php
session_start();

require_once __DIR__ . "/../General/connection.php";
require_once __DIR__ . "/../General/mailConfigurations.php";
require_once __DIR__ . "/emailSender.php";

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? '';
if ($userId === '') {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Not logged in.']);
  exit;
}

$stmt = $conn->prepare("SELECT email FROM useraccountstbl WHERE user_id = ? LIMIT 1");
$stmt->bind_param("s", $userId);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
  http_response_code(404);
  echo json_encode(['success' => false, 'message' => 'User not found.']);
  exit;
}
$email = ($res->fetch_assoc())['email'] ?? '';
if ($email === '') {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'No email on file.']);
  exit;
}

$smtpConfig = require __DIR__ . "/../General/mailConfigurations.php";
$emailSender = new EmailSender($smtpConfig);

$sent = $emailSender->send([
  'to' => $email,
  'subject' => 'SMTP Health Check - Barangay San Jose',
  'bodyHtml' => '<p>This is a test email to verify SMTP delivery.</p>',
]);

if ($sent) {
  echo json_encode(['success' => true, 'message' => 'SMTP health check email sent.']);
  exit;
}

http_response_code(500);
echo json_encode(['success' => false, 'message' => 'SMTP health check failed.']);
