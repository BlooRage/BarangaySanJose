<?php

function applyBaselineSecurityHeaders(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header("Content-Security-Policy: default-src 'self' https: data: blob:; script-src 'self' https: 'unsafe-inline'; style-src 'self' https: 'unsafe-inline'; img-src 'self' https: data: blob:; font-src 'self' https: data:; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
    if (
        (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
    ) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function initializeSecureSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
    );
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

applyBaselineSecurityHeaders();
initializeSecureSession();

// Build a stable app-root URL prefix so redirects work whether the app is hosted at:
// - domain root (e.g. "/Admin-End/..."), or
// - a subfolder (e.g. "/your-app/Admin-End/...").
function appRootPath(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $markers = ['/PhpFiles/', '/Resident-End/', '/Admin-End/', '/Guest-End/'];
    foreach ($markers as $marker) {
        $pos = strpos($scriptName, $marker);
        if ($pos !== false) {
            $prefix = rtrim(substr($scriptName, 0, $pos), '/');
            if ($prefix === '' || $prefix === '/') {
                return $cached = '';
            }
            return $cached = $prefix;
        }
    }

    $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    if ($dir === '' || $dir === '.' || $dir === '/') {
        return $cached = '';
    }
    return $cached = $dir;
}

function appUrl(string $path): string
{
    $p = '/' . ltrim($path, '/');
    if (!preg_match('/^([^?#]*)(.*)$/', $p, $matches)) {
        return appRootPath() . $p;
    }

    $pathOnly = $matches[1];
    $suffix = $matches[2];

    if (stripos($pathOnly, '/PhpFiles/') !== 0) {
        $pathOnly = preg_replace('/\.(php|html)$/i', '', $pathOnly);
    }

    $publicAliases = [
        '/index' => '/',
        '/PhpFiles/Login/logout.php' => '/logout',
        '/Guest-End/login' => '/login',
        '/Guest-End/government' => '/government',
        '/Guest-End/services' => '/services',
        '/Guest-End/news' => '/news',
        '/Guest-End/faq' => '/faq',
        '/Guest-End/contact' => '/contact',
        '/Guest-End/TransactionInformation' => '/transaction-information',
        '/Guest-End/official_onboarding' => '/official-onboarding',
        '/Guest-End/verifyEmail' => '/verify-email',
    ];

    if (isset($publicAliases[$pathOnly])) {
        $pathOnly = $publicAliases[$pathOnly];
    }

    return appRootPath() . $pathOnly . $suffix;
}

function appBaseUrl(): string
{
    $configured = trim((string)(getenv('APP_BASE_URL') ?: ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $scheme = (
        (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
    ) ? 'https' : 'http';

    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        $host = trim((string)($_SERVER['SERVER_NAME'] ?? ''));
    }

    if ($host === '') {
        // Safe fallback when host headers are unavailable.
        $host = 'localhost';
    }

    // Base domain only; path prefixing is handled by appUrl().
    return rtrim($scheme . '://' . $host, '/');
}

function ensureCsrfToken(): string
{
    $existing = (string)($_SESSION['csrf_token'] ?? '');
    if ($existing !== '' && strlen($existing) >= 32) {
        return $existing;
    }
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

function csrfTokenField(): string
{
    $token = ensureCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function verifyCsrfToken(bool $json = false): void
{
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    $requestToken = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $ok = $sessionToken !== '' && $requestToken !== '' && hash_equals($sessionToken, $requestToken);
    if ($ok) {
        return;
    }
    if ($json) {
        sendJsonErrorAndExit(419, 'Invalid or missing CSRF token.');
    }
    http_response_code(419);
    exit('Invalid or missing CSRF token.');
}

function redirectToLogin(string $queryString = ''): void
{
    $qs = (string)$queryString;
    if ($qs !== '' && $qs[0] !== '?') {
        $qs = '?' . ltrim($qs, '?');
    }
    header('Location: ' . appUrl('/login') . $qs);
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

    $role = normalizeRoleName((string)($_SESSION['role'] ?? ''));
    $allowed = array_map('normalizeRoleName', $allowedRoles);
    if (!in_array($role, $allowed, true)) {
        if ($json) {
            sendJsonErrorAndExit(403, 'Forbidden');
        }
        redirectToLogin();
    }
}

function normalizeRoleName(string $role): string
{
    $role = strtolower(trim($role));
    $map = [
        'officials' => 'official',
        'admin' => 'official',
        'employee' => 'official',
        'personnels' => 'personnel',
    ];
    return $map[$role] ?? $role;
}
