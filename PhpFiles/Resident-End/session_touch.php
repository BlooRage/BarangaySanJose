<?php
require_once __DIR__ . '/../General/security.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed',
    ]);
    exit;
}

requireAuthenticatedSession(true);
verifyCsrfToken(true);

echo json_encode([
    'success' => true,
    'message' => 'Session active.',
]);
