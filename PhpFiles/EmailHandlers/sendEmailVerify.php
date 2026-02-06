<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// PhpFiles/EmailHandlers/sendEmailVerify.php
session_start();

require_once __DIR__ . "/../General/connection.php";
require_once __DIR__ . "/../General/mailConfigurations.php";
require_once __DIR__ . "/emailSender.php";

function wantsJsonResponse(): bool
{
  $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
  $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
  $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

  return stripos($contentType, 'application/json') !== false
    || stripos($accept, 'application/json') !== false
    || strtolower($requestedWith) === 'xmlhttprequest';
}

function jsonResponse(int $status, array $payload): void
{
  http_response_code($status);
  header('Content-Type: application/json');
  echo json_encode($payload);
  exit;
}

// ✅ must be logged in
$userId = $_SESSION['user_id'] ?? '';
if ($userId === '') {
  if (wantsJsonResponse()) {
    jsonResponse(401, ['success' => false, 'message' => 'Not logged in.']);
  }
  http_response_code(401);
  exit("Not logged in.");
}

// 1) Fetch user's email
$stmt = $conn->prepare("SELECT email, email_verify FROM useraccountstbl WHERE user_id = ? LIMIT 1");
$stmt->bind_param("s", $userId);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
  if (wantsJsonResponse()) {
    jsonResponse(404, ['success' => false, 'message' => 'User not found.']);
  }
  exit("User not found.");
}

$user = $res->fetch_assoc();
$email = $user['email'];

if ((int)$user['email_verify'] === 1) {
  // already verified — redirect back
  if (wantsJsonResponse()) {
    jsonResponse(400, ['success' => false, 'message' => 'Email already verified.']);
  }
  header("Location: ../../Resident-End/resident_dashboard.php?email=already_verified");
  exit;
}

// 2) Generate token (raw in email, hash in DB)
$rawToken  = bin2hex(random_bytes(32));
$tokenHash = password_hash($rawToken, PASSWORD_DEFAULT);
$expiresAt = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');

// 3) Save/overwrite token
$ins = $conn->prepare("
  INSERT INTO emailverificationtokens (user_id, token_hash, expires_at)
  VALUES (?, ?, ?)
  ON DUPLICATE KEY UPDATE
    token_hash = VALUES(token_hash),
    expires_at = VALUES(expires_at),
    used_at = NULL
");
$ins->bind_param("sss", $userId, $tokenHash, $expiresAt);
$ins->execute();

// 4) Build verification link (✅ match your real verify handler)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$rootPath = dirname(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/BarangaySanJose/PhpFiles/EmailHandlers/sendEmailVerify.php')));
$baseUrl = rtrim($scheme . "://" . $host . $rootPath, '/');
$verifyUrl = $baseUrl . "/Guest-End/verifyEmail.php?uid=" . urlencode($userId) . "&token=" . urlencode($rawToken);

// 5) Load SMTP config + send
$smtpConfig = require __DIR__ . "/../General/mailConfigurations.php";
$emailSender = new EmailSender($smtpConfig);

$sent = $emailSender->send([
  'to' => $email,
  'subject' => "Barangay San Jose - Verify Your Email",
  'type' => 'verify',
  'data' => [
    'headline' => "MALIGAYANG BATI KA-BARANGAY SAN JOSE!",
    'verifyUrl' => $verifyUrl,
    'buttonText' => "VERIFY EMAIL",
    'expiresNote' => "This link will expire in 15 minutes.",
  ],
]);

if ($sent) {
  if (wantsJsonResponse()) {
    jsonResponse(200, ['success' => true, 'message' => 'Verification email sent.']);
  }
  header("Location: ../../Resident-End/resident_dashboard.php?email=sent");
  exit;
}

if (wantsJsonResponse()) {
  jsonResponse(500, ['success' => false, 'message' => 'Unable to send verification email.']);
}

header("Location: ../../Resident-End/resident_dashboard.php?email=failed");
exit;
