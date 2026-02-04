<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// PhpFiles/EmailHandlers/testSendVerify.php
session_start();

require_once __DIR__ . "/../General/connection.php";
require_once __DIR__ . "/../General/mailConfigurations.php";
require_once __DIR__ . "/emailSender.php";

// ✅ must be logged in
$userId = $_SESSION['user_id'] ?? '';
if ($userId === '') {
  http_response_code(401);
  exit("Not logged in.");
}

// 1) Fetch user's email
$stmt = $conn->prepare("SELECT email, email_verify FROM useraccountstbl WHERE user_id = ? LIMIT 1");
$stmt->bind_param("s", $userId);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) exit("User not found.");

$user = $res->fetch_assoc();
$email = $user['email'];

if ((int)$user['email_verify'] === 1) {
  // already verified — redirect back
  header("Location: ../../Resident-End/resident_dashboard.php?email=already_verified");
  exit;
}

// 2) Generate token (raw in email, hash in DB)
$rawToken  = bin2hex(random_bytes(32));
$tokenHash = password_hash($rawToken, PASSWORD_DEFAULT);
$expiresAt = (new DateTime('+30 minutes'))->format('Y-m-d H:i:s');

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
$baseUrl = "localhost/BarangaySanJose2";
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
    'expiresNote' => "This link will expire in 30 minutes.",
  ],
]);

if ($sent) {
  header("Location: ../../Resident-End/resident_dashboard.php?email=sent");
  exit;
}

header("Location: ../../Resident-End/resident_dashboard.php?email=failed");
exit;
