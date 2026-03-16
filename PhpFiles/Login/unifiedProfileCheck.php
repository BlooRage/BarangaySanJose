<?php
session_start();
require '../General/connection.php';
require_once __DIR__ . '/../General/security.php';
require_once __DIR__ . '/../General/officialInviteCommon.php';

$userID = $_SESSION['user_id'] ?? null;
$role   = $_SESSION['role'] ?? null;

if (!$userID || !$role) {
    // Not logged in → redirect to login
    redirectToLogin();
}

// Resume pending official/employee onboarding after login.
if (in_array($role, ['Official', 'Officials', 'Personnel', 'Personnels', 'SuperAdmin', 'Admin', 'Employee'], true) && isset($conn) && $conn instanceof mysqli) {
    oi_ensure_invite_table($conn);
    $stmtInvite = $conn->prepare("
        SELECT invite_id
        FROM officialinvitetbl
        WHERE user_id = ?
          AND status = 'InProgress'
        ORDER BY invite_id DESC
        LIMIT 1
    ");
    if ($stmtInvite) {
        $stmtInvite->bind_param("s", $userID);
        $stmtInvite->execute();
        $invite = $stmtInvite->get_result()->fetch_assoc();
        $stmtInvite->close();
        if ($invite) {
            header('Location: ' . appUrl('/official-onboarding'));
            exit;
        }
    }
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

	case 'Official':
	case 'Officials':
	case 'Personnel':
	case 'Personnels':
	case 'Admin':
	case 'SuperAdmin':
	case 'Employee':
        // Check official profile
        $stmt = $conn->prepare("SELECT official_id FROM officialinformationtbl WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("s", $userID);
        $stmt->execute();
        $result = $stmt->get_result();
        $profileData = $result->fetch_assoc();
        $stmt->close();

        if (!$profileData && in_array($role, ['Official', 'Officials', 'Personnel', 'Personnels', 'SuperAdmin', 'Admin', 'Employee'], true)) {
            header('Location: ' . appUrl('/official-onboarding'));
            exit;
        }

        $normalizedRole = strtolower(trim((string)$role));
        if ($normalizedRole === 'officials') $normalizedRole = 'official';
        if ($normalizedRole === 'personnels') $normalizedRole = 'personnel';
        if ($normalizedRole === 'admin') $normalizedRole = 'official';
        if ($normalizedRole === 'employee') $normalizedRole = 'official';

        if (in_array($normalizedRole, ['official', 'personnel'], true)) {
            $stmtApproval = $conn->prepare("
                SELECT status
                FROM officialinvitetbl
                WHERE user_id = ?
                ORDER BY invite_id DESC
                LIMIT 1
            ");
            if ($stmtApproval) {
                $stmtApproval->bind_param("s", $userID);
                $stmtApproval->execute();
                $approvalRow = $stmtApproval->get_result()->fetch_assoc();
                $stmtApproval->close();
                $inviteStatus = strtolower(trim((string)($approvalRow['status'] ?? '')));
                if ($inviteStatus !== 'completed') {
                    header('Location: ' . appUrl('/official-onboarding'));
                    exit;
                }
            }
        }

        // Current admin surface uses AdminDashboard for all non-resident internal users.
        header('Location: ' . appUrl('/Admin-End/AdminDashboard.php'));
        exit;

    default:
        // Unknown role → logout
        session_destroy();
        redirectToLogin();
}
?>
