<?php
// Use Asia/Manila (UTC+08:00) for PHP date/time functions.
date_default_timezone_set('Asia/Manila');

$host = getenv('DB_HOST') ?: "srv1986.hstgr.io";
//srv1986.hstgr.io
$user = getenv('DB_USER') ?: "u682055666_thesiscaps";
$pass = getenv('DB_PASS') ?: "ThesisCaps123.";
$dbname = getenv('DB_NAME') ?: "u682055666_testingBrgySJ";

mysqli_report(MYSQLI_REPORT_OFF);
$conn = mysqli_init();
if ($conn instanceof mysqli) {
    // Fail fast when DB host is slow/unreachable to avoid long page stalls.
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    if (defined('MYSQLI_OPT_READ_TIMEOUT')) {
        $conn->options(MYSQLI_OPT_READ_TIMEOUT, 10);
    }
    @mysqli_real_connect($conn, $host, $user, $pass, $dbname);
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
