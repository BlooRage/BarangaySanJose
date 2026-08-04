<?php
require_once __DIR__ . '/../General/security.php';
require_once __DIR__ . '/../General/connection.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (empty($_SESSION['user_id'])) {
    sendJsonErrorAndExit(401, 'Unauthorized');
}

// Check revocation without calling requireAuthenticatedSession(): this polling
// request must never count as resident activity or extend the idle timeout.
enforceCurrentSessionAccountStatus(true);

echo json_encode(['success' => true]);
