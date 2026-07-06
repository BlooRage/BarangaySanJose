<?php
require_once __DIR__ . '/../General/security.php';
require '../General/connection.php';
require_once __DIR__ . '/redirectDestination.php';

$userID = $_SESSION['user_id'] ?? null;
$role   = $_SESSION['role'] ?? null;
$requestedService = normalizeRequestedResidentService($_GET['service'] ?? '');

if (!$userID || !$role) {
    $loginQuery = $requestedService !== '' ? 'service=' . rawurlencode($requestedService) : '';
    redirectToLogin($loginQuery);
}

enforceCurrentSessionAccountStatus(false);
header('Location: ' . resolveRequestedPostLoginRedirect($conn, (string)$userID, (string)$role, $requestedService));
exit;
