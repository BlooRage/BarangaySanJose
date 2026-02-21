<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Build a stable app-root URL prefix so redirects work whether the app is hosted at:
// - domain root (e.g. "/Admin-End/..."), or
// - a subfolder (e.g. "/BarangaySanJose/Admin-End/...").
function appRootPath(): string
{
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $script = '/' . ltrim($script, '/');
    $parts = array_values(array_filter(explode('/', trim($script, '/')), fn($p) => $p !== ''));
    if (!$parts) return '';

    $first = $parts[0];
    $knownTopDirs = [
        'Admin-End',
        'Resident-End',
        'Guest-End',
        'PhpFiles',
        'CSS-Styles',
        'JS-Script-Files',
        'Images',
        'UnifiedFileAttachment',
        'Fonts',
    ];

    // If the first path segment is already a known app directory, app is at domain root.
    if (in_array($first, $knownTopDirs, true)) {
        return '';
    }

    // Otherwise, treat the first segment as the app's folder name.
    return '/' . $first;
}

function appUrl(string $path): string
{
    $p = '/' . ltrim($path, '/');
    return appRootPath() . $p;
}

function redirectToLogin(string $queryString = ''): void
{
    $qs = (string)$queryString;
    if ($qs !== '' && $qs[0] !== '?') {
        $qs = '?' . ltrim($qs, '?');
    }
    header('Location: ' . appUrl('/Guest-End/login.php') . $qs);
    exit;
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

function destroySessionAndExit(bool $json = true, int $statusCode = 401, string $message = 'Session expired. Please login again.'): void
{
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }

    @session_destroy();

    if ($json) {
        sendJsonErrorAndExit($statusCode, $message);
    }

    redirectToLogin('?session=expired');
}

// Enforce inactivity-based auto logout. Default: 30 minutes idle.
function enforceSessionInactivityTimeout(int $timeoutSeconds = 1800, bool $json = true): void
{
    if (empty($_SESSION['user_id'])) {
        return; // not logged in
    }

    $now = time();
    $last = (int)($_SESSION['last_activity'] ?? 0);

    if ($last > 0 && ($now - $last) > $timeoutSeconds) {
        destroySessionAndExit($json, 401, 'Session expired. Please login again.');
    }

    // Touch on every request after auth to keep server-side activity timestamp fresh.
    $_SESSION['last_activity'] = $now;
}

function requireAuthenticatedSession(bool $json = true): void {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] === '') {
        if ($json) {
            sendJsonErrorAndExit(401, 'Unauthorized');
        }
        redirectToLogin();
    }

    enforceSessionInactivityTimeout(1800, $json);
}

function requireRoleSession(array $allowedRoles, bool $json = true): void {
    requireAuthenticatedSession($json);

    $role = (string)($_SESSION['role'] ?? '');
    if (!in_array($role, $allowedRoles, true)) {
        if ($json) {
            sendJsonErrorAndExit(403, 'Forbidden');
        }
        redirectToLogin();
    }
}
