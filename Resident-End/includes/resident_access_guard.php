<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (empty($_SESSION['user_id'])) {
    header("Location: ../Guest-End/login.php");
    exit;
}

require_once __DIR__ . "/../../PhpFiles/General/connection.php";

$userId = $_SESSION['user_id'];
$hasResidentProfile = false;

if (isset($conn) && $conn instanceof mysqli) {
    $stmt = $conn->prepare("SELECT resident_id FROM residentinformationtbl WHERE user_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $stmt->store_result();
        $hasResidentProfile = $stmt->num_rows > 0;
        $stmt->close();
    }
}

// Default: resident modules require completed profiling
$allowUnregistered = $allowUnregistered ?? false;

// Not yet profiled → force to registration (unless explicitly allowed)
if (!$hasResidentProfile && !$allowUnregistered) {
    header("Location: resident_registration.php");
    exit;
}

// Already profiled → keep out of registration page
if ($hasResidentProfile && $allowUnregistered) {
    header("Location: resident_dashboard.php");
    exit;
}
