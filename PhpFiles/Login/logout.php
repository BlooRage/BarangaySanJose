<?php
session_start();
require_once __DIR__ . '/../General/security.php';

$_SESSION = [];

if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000,
    $params["path"], $params["domain"],
    $params["secure"], $params["httponly"]
  );
}

session_destroy();

$logoutReason = strtolower(trim((string)($_GET['reason'] ?? '')));
$accountLogoutReasons = ['archived', 'deactivated', 'deleted', 'locked'];
if ($logoutReason === 'expired') {
  redirectToLogin('?session=expired');
}
redirectToLogin(in_array($logoutReason, $accountLogoutReasons, true) ? '?account=' . $logoutReason : '');
?>
