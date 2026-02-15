<?php
session_start();
require '../General/connection.php';
require_once __DIR__ . '/../General/security.php';

$userID = $_SESSION['user_id'] ?? null;
$role   = $_SESSION['role'] ?? null;

if (!$userID || !$role) {
    // Not logged in → redirect to login
    redirectToLogin();
}

switch ($role) {
    case 'Resident':
        // Check resident profile
        $stmt = $conn->prepare("SELECT resident_id FROM residentinformationtbl WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("s", $userID);
        $stmt->execute();
        $result = $stmt->get_result();
        $profileData = $result->fetch_assoc();
        $stmt->close();

        if (!$profileData) {
            header('Location: ' . appUrl('/Resident-End/resident_registration.php'));
            exit;
        }

        header('Location: ' . appUrl('/Resident-End/resident_dashboard.php'));
        exit;

	case 'Employee':
	    header('Location: ' . appUrl('/Admin-End/AdminDashboard.php'));
	    exit;
	case 'Official':
	case 'Admin':
	case 'SuperAdmin':
        // Check official profile
        $stmt = $conn->prepare("SELECT official_id FROM officialinformationtbl WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("s", $userID);
        $stmt->execute();
        $result = $stmt->get_result();
        $profileData = $result->fetch_assoc();
        $stmt->close();

        if (!$profileData) {
            header('Location: ' . appUrl('/Admin-End/official_profile_form.php'));
            exit;
        }

        // Redirect based on role
        if ($role === 'SuperAdmin' || $role === 'Admin') {
            header('Location: ' . appUrl('/Admin-End/AdminDashboard.php'));
        } else {
            header('Location: ' . appUrl('/Admin-End/official_dashboard.php'));
        }
        exit;

    default:
        // Unknown role → logout
        session_destroy();
        redirectToLogin();
}
?>
