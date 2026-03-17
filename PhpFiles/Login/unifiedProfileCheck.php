<?php
require_once __DIR__ . '/../General/security.php';
require '../General/connection.php';
require_once __DIR__ . '/redirectDestination.php';

$userID = $_SESSION['user_id'] ?? null;
$role   = $_SESSION['role'] ?? null;

if (!$userID || !$role) {
    redirectToLogin();
}
header('Location: ' . resolveUnifiedProfileRedirect($conn, (string)$userID, (string)$role));
exit;
