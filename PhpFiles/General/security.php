<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function sendJsonErrorAndExit(int $statusCode, string $message): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit;
}

function requireAuthenticatedSession(bool $json = true): void {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] === '') {
        if ($json) {
            sendJsonErrorAndExit(401, 'Unauthorized');
        }
        header("Location: ../../Guest-End/login.php");
        exit;
    }
}

function requireRoleSession(array $allowedRoles, bool $json = true): void {
    requireAuthenticatedSession($json);

    $role = (string)($_SESSION['role'] ?? '');
    if (!in_array($role, $allowedRoles, true)) {
        if ($json) {
            sendJsonErrorAndExit(403, 'Forbidden');
        }
        header("Location: ../../Guest-End/login.php");
        exit;
    }
}

