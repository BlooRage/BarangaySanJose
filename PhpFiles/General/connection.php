<?php
require_once __DIR__ . '/runtimeConfig.php';

// Use Asia/Manila (UTC+08:00) for PHP date/time functions.
date_default_timezone_set('Asia/Manila');

if (!function_exists('db_request_host_name')) {
    function db_request_host_name(): string
    {
        $candidates = [
            (string)($_SERVER['HTTP_HOST'] ?? ''),
            (string)($_SERVER['SERVER_NAME'] ?? ''),
        ];

        foreach ($candidates as $candidate) {
            $candidate = strtolower(trim((string)explode(',', $candidate)[0]));
            if ($candidate === '') {
                continue;
            }

            return preg_replace('/:\d+$/', '', $candidate) ?: '';
        }

        return '';
    }
}

if (!function_exists('db_is_localhost_request')) {
    function db_is_localhost_request(): bool
    {
        $host = db_request_host_name();
        if ($host !== '' && in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        $remoteAddr = strtolower(trim((string)($_SERVER['REMOTE_ADDR'] ?? '')));
        return in_array($remoteAddr, ['127.0.0.1', '::1'], true);
    }
}

$configuredHost = trim((string)runtime_config('db.host', ''));
$localHost = trim((string)runtime_env('DB_HOST_LOCAL', runtime_config('db.host_local', $configuredHost)));
$hostedHost = trim((string)runtime_env('DB_HOST_HOSTED', runtime_config('db.host_hosted', 'localhost')));
$explicitHost = trim((string)runtime_env('DB_HOST', ''));

$hostCandidates = [];
if ($explicitHost !== '') {
    $hostCandidates[] = $explicitHost;
}

if (db_request_host_name() !== '') {
    if (db_is_localhost_request()) {
        $hostCandidates = array_merge($hostCandidates, [$localHost, $hostedHost]);
    } else {
        $hostCandidates = array_merge($hostCandidates, [$hostedHost, $localHost]);
    }
} else {
    $hostCandidates = array_merge($hostCandidates, [$configuredHost, $hostedHost]);
}

$hostCandidates = array_values(array_filter(array_unique(array_map(
    static fn($value) => trim((string)$value),
    $hostCandidates
)), static fn($value) => $value !== ''));

$host = $hostCandidates[0] ?? '';

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
$conn = null;
$connectError = 'No database hosts available.';
$attemptErrors = [];

foreach ($hostCandidates as $candidateHost) {
    $candidateConn = mysqli_init();
    if ($candidateConn instanceof mysqli) {
        // Fail fast when DB host is slow/unreachable to avoid long page stalls.
        $candidateConn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
        if (defined('MYSQLI_OPT_READ_TIMEOUT')) {
            $candidateConn->options(MYSQLI_OPT_READ_TIMEOUT, 10);
        }
        @mysqli_real_connect($candidateConn, $candidateHost, $user, $pass, $dbname, $port > 0 ? $port : 3306);
    }

    if ($candidateConn instanceof mysqli && !$candidateConn->connect_error) {
        $conn = $candidateConn;
        $host = $candidateHost;
        break;
    }

    $connectError = ($candidateConn instanceof mysqli) ? (string)$candidateConn->connect_error : 'mysqli_init failed';
    $attemptErrors[] = $candidateHost . ': ' . $connectError;

    if ($candidateConn instanceof mysqli) {
        @$candidateConn->close();
    }
}

if (!($conn instanceof mysqli) || $conn->connect_error) {
    $attemptSummary = $attemptErrors !== [] ? implode(' | ', $attemptErrors) : $connectError;
    error_log('Database connection failed after trying hosts [' . implode(', ', $hostCandidates) . ']: ' . $attemptSummary);
    http_response_code(500);
    exit('Service temporarily unavailable.');
}

// Prefer UTF-8 for all queries/results.
$conn->set_charset('utf8mb4');

// Force MySQL session timezone to UTC+08:00.
// This affects NOW(), CURRENT_TIMESTAMP, and timestamp defaults for this connection.
$conn->query("SET time_zone = '+08:00'");
