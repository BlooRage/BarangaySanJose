<?php

require_once __DIR__ . '/runtimeConfig.php';
require_once __DIR__ . '/piiCrypto.php';

function applyBaselineSecurityHeaders(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(self)');
    header("Content-Security-Policy: default-src 'self' https: data: blob:; script-src 'self' https: 'unsafe-inline'; style-src 'self' https: 'unsafe-inline'; img-src 'self' https: data: blob:; font-src 'self' https: data:; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
    if (appRequestIsHttps()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function initializeSecureSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    $isHttpsTransport = appRequestIsHttpsTransport();
    $useSecureCookie = $isHttpsTransport || (appForceHttpsConfigured() && !appRequestIsLocalhost());

    // Session ini + cookie params must be configured before headers are sent.
    // Some pages may accidentally output content earlier (BOM/whitespace), so
    // guard these calls to avoid warnings while still attempting to start.
    if (!headers_sent()) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $useSecureCookie,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        return;
    }

    @session_start();
}

applyBaselineSecurityHeaders();
initializeSecureSession();

function appConfiguredBaseUrlRaw(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    if (appRequestIsLocalhost()) {
        return $cached = '';
    }

    $configured = trim((string)runtime_env('APP_BASE_URL', runtime_config('app.base_url', '')));
    return $cached = rtrim($configured, '/');
}

function appNormalizeRootPath(string $path): string
{
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '' || $path === '.' || $path === '/') {
        return '';
    }

    return '/' . trim($path, '/');
}

function appPreferredProjectRootPath(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    return $cached = appRequestIsLocalhost() ? '/BarangaySanJose' : '';
}

function appConfiguredRootPath(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $explicit = trim((string)runtime_env('APP_ROOT_PATH', runtime_config('app.root_path', '')));
    if ($explicit !== '') {
        $normalized = appNormalizeRootPath($explicit);
        if (appRequestIsLocalhost()) {
            return $cached = appPreferredProjectRootPath();
        }
        if ($normalized === '/BarangaySanJose') {
            return $cached = '';
        }
        return $cached = $normalized;
    }

    $configuredBaseUrl = appConfiguredBaseUrlRaw();
    if ($configuredBaseUrl !== '') {
        $path = (string)(parse_url($configuredBaseUrl, PHP_URL_PATH) ?? '');
        $normalized = appNormalizeRootPath($path);
        if (appRequestIsLocalhost()) {
            return $cached = appPreferredProjectRootPath();
        }
        if ($normalized === '/BarangaySanJose') {
            return $cached = '';
        }
        return $cached = $normalized;
    }

    return $cached = appPreferredProjectRootPath();
}

function appHasConfiguredRootContext(): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    if (appConfiguredBaseUrlRaw() !== '') {
        return $cached = true;
    }

    if (getenv('APP_ROOT_PATH') !== false) {
        return $cached = true;
    }

    $config = runtime_config_all();
    return $cached = isset($config['app']) && is_array($config['app']) && array_key_exists('root_path', $config['app']);
}

function appRequestHeader(string $key): string
{
    $value = $_SERVER[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }

    $parts = explode(',', $value);
    return trim((string)($parts[0] ?? ''));
}

function appRequestIsHttps(): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    if (appForceHttpsConfigured()) {
        return $cached = true;
    }

    return $cached = appRequestIsHttpsTransport();
}

function appForceHttpsConfigured(): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    if (appRequestIsLocalhost()) {
        return $cached = false;
    }

    return $cached = runtime_bool(runtime_env('APP_FORCE_HTTPS', runtime_config('app.force_https', null)), false);
}

function appRequestIsHttpsTransport(): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $https = strtolower(trim((string)($_SERVER['HTTPS'] ?? '')));
    if ($https !== '' && $https !== 'off') {
        return $cached = true;
    }

    $forwardedProto = strtolower(appRequestHeader('HTTP_X_FORWARDED_PROTO'));
    if ($forwardedProto === 'https') {
        return $cached = true;
    }

    $forwardedSsl = strtolower(appRequestHeader('HTTP_X_FORWARDED_SSL'));
    if ($forwardedSsl === 'on') {
        return $cached = true;
    }

    $forwardedPort = appRequestHeader('HTTP_X_FORWARDED_PORT');
    if ($forwardedPort === '443') {
        return $cached = true;
    }

    $cfVisitor = trim((string)($_SERVER['HTTP_CF_VISITOR'] ?? ''));
    if ($cfVisitor !== '' && stripos($cfVisitor, '"https"') !== false) {
        return $cached = true;
    }

    return $cached = ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function appRequestIsLocalhost(): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $hostCandidates = [
        (string)($_SERVER['HTTP_HOST'] ?? ''),
        (string)($_SERVER['SERVER_NAME'] ?? ''),
    ];

    foreach ($hostCandidates as $candidate) {
        $candidate = strtolower(trim((string)explode(',', $candidate)[0]));
        if ($candidate === '') {
            continue;
        }

        $hostOnly = preg_replace('/:\d+$/', '', $candidate);
        if (in_array($hostOnly, ['localhost', '127.0.0.1', '::1'], true)) {
            return $cached = true;
        }
    }

    return $cached = false;
}

function appSanitizeHost(string $host): string
{
    $host = trim($host);
    if ($host === '') {
        return '';
    }

    $host = trim((string)explode(',', $host)[0]);
    if ($host === '') {
        return '';
    }

    if (!preg_match('/^[A-Za-z0-9.-]+(?::\d+)?$/', $host)) {
        return '';
    }

    return strtolower($host);
}

function appRequestHost(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $configuredBaseUrl = appConfiguredBaseUrlRaw();
    if ($configuredBaseUrl !== '') {
        $configuredScheme = strtolower((string)(parse_url($configuredBaseUrl, PHP_URL_SCHEME) ?? ''));
        $configuredHost = (string)(parse_url($configuredBaseUrl, PHP_URL_HOST) ?? '');
        $configuredPort = (int)(parse_url($configuredBaseUrl, PHP_URL_PORT) ?? 0);
        $configuredHost = appSanitizeHost($configuredHost);
        if ($configuredHost !== '') {
            if ($configuredPort > 0 && !(
                ($configuredPort === 80 && $configuredScheme === 'http')
                || ($configuredPort === 443 && $configuredScheme === 'https')
            )) {
                $configuredHost .= ':' . $configuredPort;
            }
            return $cached = $configuredHost;
        }
    }

    $configuredHost = appSanitizeHost((string)runtime_env('APP_HOST', runtime_config('app.host', '')));
    if ($configuredHost !== '') {
        return $cached = $configuredHost;
    }

    $candidates = [
        appRequestHeader('HTTP_X_FORWARDED_HOST'),
        (string)($_SERVER['HTTP_HOST'] ?? ''),
        (string)($_SERVER['SERVER_NAME'] ?? ''),
    ];

    foreach ($candidates as $candidate) {
        $candidate = appSanitizeHost($candidate);
        if ($candidate !== '') {
            return $cached = $candidate;
        }
    }

    return $cached = '127.0.0.1';
}

// Build a stable app-root URL prefix so redirects work whether the app is hosted at:
// - domain root (e.g. "/Admin-End/..."), or
// - a subfolder (e.g. "/your-app/Admin-End/...").
function appRootPath(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $configured = appConfiguredRootPath();
    if ($configured !== '' || appHasConfiguredRootContext()) {
        return $cached = $configured;
    }

    if (appRequestIsLocalhost()) {
        return $cached = appPreferredProjectRootPath();
    }

    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $markers = ['/PhpFiles/', '/Resident-End/', '/Admin-End/', '/Guest-End/'];
    foreach ($markers as $marker) {
        $pos = strpos($scriptName, $marker);
        if ($pos !== false) {
            $prefix = rtrim(substr($scriptName, 0, $pos), '/');
            if ($prefix === '/BarangaySanJose') {
                return $cached = '';
            }
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
        '/PhpFiles/Login/unifiedProfileCheck.php' => '/account-redirect',
        '/Guest-End/login' => '/login',
        '/Guest-End/government' => '/government',
        '/Guest-End/services' => '/services',
        '/Guest-End/news' => '/news',
        '/Guest-End/faq' => '/faq',
        '/Guest-End/contact' => '/contact',
        '/Guest-End/transactions' => '/transactions',
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
    $configured = appConfiguredBaseUrlRaw();
    if ($configured !== '') {
        $scheme = (string)(parse_url($configured, PHP_URL_SCHEME) ?? '');
        $host = (string)(parse_url($configured, PHP_URL_HOST) ?? '');
        $port = (int)(parse_url($configured, PHP_URL_PORT) ?? 0);
        $host = appSanitizeHost($host);

        if ($scheme !== '' && $host !== '') {
            if ($port > 0 && !(($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))) {
                $host .= ':' . $port;
            }
            return rtrim($scheme . '://' . $host, '/');
        }

        return rtrim($configured, '/');
    }

    $scheme = appRequestIsHttps() ? 'https' : 'http';
    $host = appRequestHost();

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

function destroySessionAndExit(
    bool $json = true,
    int $statusCode = 401,
    string $message = 'Session expired. Please login again.',
    string $loginQuery = '?session=expired'
): void
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

    redirectToLogin($loginQuery);
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

function enforceCurrentSessionAccountStatus(bool $json = true): void
{
    $userId = trim((string)($_SESSION['user_id'] ?? ''));
    if ($userId === '') {
        return;
    }

    $conn = $GLOBALS['conn'] ?? null;
    if (!($conn instanceof mysqli)) {
        return;
    }

    static $cache = [];
    if (array_key_exists($userId, $cache)) {
        $sessionStatus = $cache[$userId];
    } else {
        $stmt = $conn->prepare("
            SELECT status_id_account
            FROM useraccountstbl
            WHERE user_id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return;
        }

        $stmt->bind_param('s', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            destroySessionAndExit($json, 401, 'Account cannot be found.', '?account=missing');
        }

        require_once __DIR__ . '/userAccountLocks.php';
        $statuses = ual_load_status_ids($conn);
        $sessionStatus = [
            'status_id' => (int)($row['status_id_account'] ?? 0),
            'locked' => isset($statuses['locked']) ? (int)$statuses['locked'] : null,
            'archived' => isset($statuses['archived']) ? (int)$statuses['archived'] : null,
            'deactivated' => isset($statuses['deactivated']) ? (int)$statuses['deactivated'] : null,
            'deleted' => isset($statuses['deleted']) ? (int)$statuses['deleted'] : null,
        ];
        $cache[$userId] = $sessionStatus;
    }

    $statusId = (int)($sessionStatus['status_id'] ?? 0);

    if (($sessionStatus['archived'] ?? null) !== null && $statusId === (int)$sessionStatus['archived']) {
        destroySessionAndExit($json, 403, 'Account Archived.', '?account=archived');
    }
    if (($sessionStatus['deactivated'] ?? null) !== null && $statusId === (int)$sessionStatus['deactivated']) {
        destroySessionAndExit($json, 403, 'Account Deactivated.', '?account=deactivated');
    }
    if (($sessionStatus['deleted'] ?? null) !== null && $statusId === (int)$sessionStatus['deleted']) {
        destroySessionAndExit($json, 403, 'Account cannot be found.', '?account=deleted');
    }
    if (($sessionStatus['locked'] ?? null) !== null && $statusId === (int)$sessionStatus['locked']) {
        destroySessionAndExit($json, 403, 'Account is locked. Please contact the barangay office.', '?account=locked');
    }
}

function requireAuthenticatedSession(bool $json = true): void {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] === '') {
        if ($json) {
            sendJsonErrorAndExit(401, 'Unauthorized');
        }
        redirectToLogin();
    }

    enforceCurrentSessionAccountStatus($json);
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
        'employee' => 'personnel',
        'personnels' => 'personnel',
    ];
    return $map[$role] ?? $role;
}
