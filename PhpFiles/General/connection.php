<?php
require_once __DIR__ . '/runtimeConfig.php';

// Default shared target for the whole app.
// Change this to 'localhost' or 'svr' when you want to switch globally in code.
$dbConnectionTarget = $GLOBALS['dbConnectionTarget'] ?? 'svr';

if (!function_exists('db_normalize_connection_target')) {
    function db_normalize_connection_target(?string $target, string $fallback = 'svr'): string
    {
        $normalized = strtolower(trim((string)$target));
        if ($normalized === 'server') {
            $normalized = 'svr';
        }

        return in_array($normalized, ['localhost', 'svr'], true) ? $normalized : $fallback;
    }
}

if (!function_exists('db_connection_profiles')) {
    function db_connection_profiles(): array
    {
        static $profiles = null;
        if (is_array($profiles)) {
            return $profiles;
        }

        $serverHost = (string)runtime_env('DB_SERVER_HOST', (string)runtime_config('db.host', 'srv1986.hstgr.io'));
        $serverPort = (int)runtime_env('DB_SERVER_PORT', (string)runtime_config('db.port', 3306));
        $serverUser = (string)runtime_env('DB_SERVER_USER', (string)runtime_config('db.user', 'u682055666_thesiscaps'));
        $serverPass = (string)runtime_env('DB_SERVER_PASS', (string)runtime_config('db.pass', 'ThesisCaps123.'));
        $serverName = (string)runtime_env('DB_SERVER_NAME', (string)runtime_config('db.name', 'u682055666_testingBrgySJ'));

        $profiles = [
            'localhost' => [
                'host' => (string)runtime_env('DB_LOCAL_HOST', '127.0.0.1'),
                'port' => (int)runtime_env('DB_LOCAL_PORT', 3306),
                'user' => (string)runtime_env('DB_LOCAL_USER', 'root'),
                'pass' => (string)runtime_env('DB_LOCAL_PASS', ''),
                'name' => (string)runtime_env('DB_LOCAL_NAME', $serverName),
            ],
            'svr' => [
                'host' => $serverHost,
                'port' => $serverPort > 0 ? $serverPort : 3306,
                'user' => $serverUser,
                'pass' => $serverPass,
                'name' => $serverName,
            ],
        ];

        return $profiles;
    }
}

if (!function_exists('db_get_connection_target')) {
    function db_get_connection_target(string $fallback = 'svr'): string
    {
        $runtimeTarget = runtime_env('DB_CONNECTION_TARGET', null);
        $globalTarget = $GLOBALS['dbConnectionTarget'] ?? null;
        $target = $runtimeTarget !== null ? (string)$runtimeTarget : (string)$globalTarget;

        return db_normalize_connection_target($target, $fallback);
    }
}

if (!function_exists('db_set_connection_target')) {
    function db_set_connection_target(string $target): string
    {
        $normalized = db_normalize_connection_target($target);
        $GLOBALS['dbConnectionTarget'] = $normalized;
        return $normalized;
    }
}

if (!function_exists('db_get_connection_config')) {
    function db_get_connection_config(?string $target = null): array
    {
        $profiles = db_connection_profiles();
        $normalized = db_normalize_connection_target($target ?? db_get_connection_target());

        return $profiles[$normalized] ?? $profiles['svr'];
    }
}

if (!function_exists('db_disconnect')) {
    function db_disconnect(): void
    {
        if (($GLOBALS['_shared_db_connection'] ?? null) instanceof mysqli) {
            @$GLOBALS['_shared_db_connection']->close();
        }

        unset($GLOBALS['_shared_db_connection'], $GLOBALS['_shared_db_target']);
    }
}

if (!function_exists('db_connect')) {
    function db_connect(?string $target = null, bool $forceReconnect = false): mysqli
    {
        $normalized = db_normalize_connection_target($target ?? db_get_connection_target());
        $existing = $GLOBALS['_shared_db_connection'] ?? null;
        $existingTarget = $GLOBALS['_shared_db_target'] ?? null;

        if (
            !$forceReconnect
            && $existing instanceof mysqli
            && $existingTarget === $normalized
            && !$existing->connect_errno
        ) {
            $GLOBALS['conn'] = $existing;
            return $existing;
        }

        if ($existing instanceof mysqli) {
            @$existing->close();
        }

        $config = db_get_connection_config($normalized);
        $host = trim((string)($config['host'] ?? ''));
        $port = (int)($config['port'] ?? 3306);
        $user = trim((string)($config['user'] ?? ''));
        $pass = (string)($config['pass'] ?? '');
        $dbname = trim((string)($config['name'] ?? ''));

        if ($host === '' || $user === '' || $dbname === '') {
            die("Connection Failed [{$normalized}]: incomplete database configuration.");
        }

        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = mysqli_init();
        if (!($conn instanceof mysqli)) {
            die("Connection Failed [{$normalized}]: mysqli_init failed.");
        }

        $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
        if (defined('MYSQLI_OPT_READ_TIMEOUT')) {
            $conn->options(MYSQLI_OPT_READ_TIMEOUT, 10);
        }

        @mysqli_real_connect($conn, $host, $user, $pass, $dbname, $port > 0 ? $port : 3306);
        if ($conn->connect_error) {
            die("Connection Failed [{$normalized}]: " . $conn->connect_error);
        }

        $conn->set_charset('utf8mb4');

        $GLOBALS['_shared_db_connection'] = $conn;
        $GLOBALS['_shared_db_target'] = $normalized;
        $GLOBALS['dbConnectionTarget'] = $normalized;
        $GLOBALS['host'] = $host;
        $GLOBALS['port'] = $port;
        $GLOBALS['user'] = $user;
        $GLOBALS['pass'] = $pass;
        $GLOBALS['dbname'] = $dbname;
        $GLOBALS['conn'] = $conn;

        return $conn;
    }
}

if (!function_exists('db_refresh_connection')) {
    function db_refresh_connection(?string $target = null): mysqli
    {
        return db_connect($target, true);
    }
}

if (!function_exists('db_use_localhost')) {
    function db_use_localhost(): mysqli
    {
        db_set_connection_target('localhost');
        return db_refresh_connection('localhost');
    }
}

if (!function_exists('db_use_svr')) {
    function db_use_svr(): mysqli
    {
        db_set_connection_target('svr');
        return db_refresh_connection('svr');
    }
}

$conn = db_connect((string)$dbConnectionTarget);
