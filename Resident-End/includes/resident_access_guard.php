<?php
require_once __DIR__ . "/../../PhpFiles/General/security.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Enforce auth + 30-min inactivity timeout for Resident pages.
requireRoleSession(['Resident'], false);

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
    header("Location: " . appUrl('/Resident-End/resident_registration.php'));
    exit;
}

// Already profiled → keep out of registration page
if ($hasResidentProfile && $allowUnregistered) {
    header("Location: " . appUrl('/Resident-End/resident_dashboard.php'));
    exit;
}
