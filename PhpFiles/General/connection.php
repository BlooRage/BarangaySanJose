<?php
require_once __DIR__ . '/runtimeConfig.php';

// Use Asia/Manila (UTC+08:00) for PHP date/time functions.
date_default_timezone_set('Asia/Manila');

$host = trim((string)runtime_env('DB_HOST', runtime_config('db.host', '')));
$port = (int)runtime_env('DB_PORT', runtime_config('db.port', 3306));
$user = trim((string)runtime_env('DB_USER', runtime_config('db.user', '')));
$pass = (string)runtime_env('DB_PASS', runtime_config('db.pass', ''));
$dbname = trim((string)runtime_env('DB_NAME', runtime_config('db.name', '')));

if ($host === '' || $user === '' || $dbname === '') {
    error_log('Database configuration is incomplete. Set DB_HOST, DB_USER, DB_PASS, and DB_NAME via environment or config.runtime.local.php.');
    http_response_code(500);
    exit('Service temporarily unavailable.');
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = mysqli_init();
if ($conn instanceof mysqli) {
    // Fail fast when DB host is slow/unreachable to avoid long page stalls.
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    if (defined('MYSQLI_OPT_READ_TIMEOUT')) {
        $conn->options(MYSQLI_OPT_READ_TIMEOUT, 10);
    }
    @mysqli_real_connect($conn, $host, $user, $pass, $dbname, $port > 0 ? $port : 3306);
}

if (!($conn instanceof mysqli) || $conn->connect_error) {
    $connectError = ($conn instanceof mysqli) ? (string)$conn->connect_error : 'mysqli_init failed';
    error_log('Database connection failed: ' . $connectError);
    http_response_code(500);
    exit('Service temporarily unavailable.');
}

// Prefer UTF-8 for all queries/results.
$conn->set_charset('utf8mb4');

// Force MySQL session timezone to UTC+08:00.
// This affects NOW(), CURRENT_TIMESTAMP, and timestamp defaults for this connection.
$conn->query("SET time_zone = '+08:00'");
