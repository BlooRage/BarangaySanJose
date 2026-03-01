<?php
// Use Asia/Manila (UTC+08:00) for PHP date/time functions.
date_default_timezone_set('Asia/Manila');

$host = getenv('DB_HOST') ?: "srv1986.hstgr.io ";
//srv1986.hstgr.io
$user = getenv('DB_USER') ?: "u682055666_thesiscaps";
$pass = getenv('DB_PASS') ?: "ThesisCaps123.";
$dbname = getenv('DB_NAME') ?: "u682055666_testingBrgySJ";

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    http_response_code(500);
    exit('Service temporarily unavailable.');
}

// Prefer UTF-8 for all queries/results.
$conn->set_charset('utf8mb4');

// Force MySQL session timezone to UTC+08:00.
// This affects NOW(), CURRENT_TIMESTAMP, and timestamp defaults for this connection.
$conn->query("SET time_zone = '+08:00'");
?>

