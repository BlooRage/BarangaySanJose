<?php
// Guest-End/verifyEmail.php
require_once __DIR__ . "/../PhpFiles/General/connection.php";

$uid   = $_GET['uid'] ?? '';
$token = $_GET['token'] ?? '';

if ($uid === '' || $token === '') {
  http_response_code(400);
  exit("Invalid verification link.");
}

// Fetch user email + verify status
$u = $conn->prepare("SELECT email, email_verify FROM useraccountstbl WHERE user_id=? LIMIT 1");
$u->bind_param("s", $uid);
$u->execute();
$ur = $u->get_result();

if ($ur->num_rows === 0) {
  exit("Account not found.");
}

$user = $ur->fetch_assoc();
$email = $user['email'];

if ((int)$user['email_verify'] === 1) {
  exit("✅ Email already verified: " . htmlspecialchars($email));
}

// Fetch token record
$t = $conn->prepare("SELECT token_hash, expires_at, used_at FROM emailverificationtokens WHERE user_id=? LIMIT 1");
$t->bind_param("s", $uid);
$t->execute();
$tr = $t->get_result();

if ($tr->num_rows === 0) {
  exit("Invalid or used link.");
}

$tok = $tr->fetch_assoc();

if (!empty($tok['used_at'])) exit("Token already used.");
if (strtotime($tok['expires_at']) < time()) exit("Token expired. Please request a new one.");
if (!password_verify($token, $tok['token_hash'])) exit("Invalid token.");

// Mark verified + mark token used
$conn->begin_transaction();

try {
  $now = date('Y-m-d H:i:s');

  $up1 = $conn->prepare("UPDATE useraccountstbl SET email_verify=1 WHERE user_id=? LIMIT 1");
  $up1->bind_param("s", $uid);
  $up1->execute();

  $up2 = $conn->prepare("UPDATE emailverificationtokens SET used_at=? WHERE user_id=? LIMIT 1");
  $up2->bind_param("ss", $now, $uid);
  $up2->execute();

  $conn->commit();

  echo "✅ Email verified successfully: " . htmlspecialchars($email);
  echo "<br><br><a href='../Guest-End/login.php'>Go to Login</a>";

} catch (Exception $e) {
  $conn->rollback();
  http_response_code(500);
  echo "Something went wrong. Please try again.";
}
