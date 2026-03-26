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
$isResidentVerified = false;

if (isset($conn) && $conn instanceof mysqli) {
    $residentStatusName = '';
    $stmt = $conn->prepare("
        SELECT r.resident_id, COALESCE(s.status_name, '')
        FROM residentinformationtbl r
        LEFT JOIN statuslookuptbl s ON r.status_id_resident = s.status_id
        WHERE r.user_id = ?
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $stmt->bind_result($residentId, $residentStatusName);
        if ($stmt->fetch()) {
            $hasResidentProfile = !empty($residentId);
            $statusKey = strtolower((string)preg_replace('/[^a-z0-9]/i', '', (string)$residentStatusName));
            $isResidentVerified = in_array($statusKey, ['verifiedresident', 'verified', 'approved'], true);
        }
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

$allowUnverifiedResident = $allowUnverifiedResident ?? false;

if (!$isResidentVerified) {
    $scriptPath = str_replace("\\", "/", (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $allowedForUnverified = [
        '/Resident-End/resident_dashboard.php',
        '/Resident-End/resident_profile.php',
        '/Resident-End/DocumentUpload.php',
        '/Resident-End/Announcements/AnnouncementsLandingPage.php',
        '/Resident-End/Appointments/AppointmentsLandingPage.php',
        '/Resident-End/Appointments/AppointmentForm.php',
        '/Resident-End/Complaints/ComplaintsLandingPage.php',
        '/Resident-End/Complaints/ComplaintsForm.php',
        '/Resident-End/appointment_tracker.php',
        '/Resident-End/complaint_tracker.php',
    ];

    if (!$allowUnverifiedResident && !in_array($scriptPath, $allowedForUnverified, true)) {
        $_SESSION['show_not_verified_modal'] = true;
        header("Location: " . appUrl('/Resident-End/resident_dashboard.php'));
        exit;
    }
}
