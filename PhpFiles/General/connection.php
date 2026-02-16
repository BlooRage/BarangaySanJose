<?php
// Use Asia/Manila (UTC+08:00) for PHP date/time functions.
date_default_timezone_set('Asia/Manila');

$host = "srv1986.hstgr.io";
$user = "u682055666_thesiscaps";
$pass = "ThesisCaps123.";
$dbname = "u682055666_testingBrgySJ";

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    // If connection fails, stop execution and show error
    die("Connection Failed: " . $conn->connect_error);
} else {
    // Optional: Uncomment this line if you want confirmation of success
    //echo "âœ… Connected successfully to $dbname";
}

// Prefer UTF-8 for all queries/results.
$conn->set_charset('utf8mb4');

// Force MySQL session timezone to UTC+08:00.
// This affects NOW(), CURRENT_TIMESTAMP, and timestamp defaults for this connection.
$conn->query("SET time_zone = '+08:00'");
?>
