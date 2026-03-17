<?php
require_once __DIR__ . '/../General/officialInviteCommon.php';

if (!function_exists('resolveUnifiedProfileRedirect')) {
    function resolveUnifiedProfileRedirect(mysqli $conn, ?string $userId, ?string $role): string
    {
        $userId = trim((string)$userId);
        $role = trim((string)$role);

        if ($userId === '' || $role === '') {
            return appUrl('/login');
        }

        $officialRoles = ['Official', 'Officials', 'Personnel', 'Personnels', 'SuperAdmin', 'Admin', 'Employee'];
        if (in_array($role, $officialRoles, true)) {
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
                $stmtInvite->bind_param('s', $userId);
                $stmtInvite->execute();
                $invite = $stmtInvite->get_result()->fetch_assoc();
                $stmtInvite->close();
                if ($invite) {
                    return appUrl('/official-onboarding');
                }
            }
        }

        switch ($role) {
            case 'Resident':
                $stmt = $conn->prepare("SELECT resident_id FROM residentinformationtbl WHERE user_id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('s', $userId);
                    $stmt->execute();
                    $profileData = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$profileData) {
                        return appUrl('/Resident-End/resident_registration.php');
                    }
                }
                return appUrl('/Resident-End/resident_dashboard.php');

            case 'Official':
            case 'Officials':
            case 'Personnel':
            case 'Personnels':
            case 'Admin':
            case 'SuperAdmin':
            case 'Employee':
                $stmt = $conn->prepare("SELECT official_id FROM officialinformationtbl WHERE user_id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('s', $userId);
                    $stmt->execute();
                    $profileData = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$profileData) {
                        return appUrl('/official-onboarding');
                    }
                } else {
                    return appUrl('/official-onboarding');
                }

                $normalizedRole = strtolower($role);
                if ($normalizedRole === 'officials') {
                    $normalizedRole = 'official';
                }
                if ($normalizedRole === 'personnels') {
                    $normalizedRole = 'personnel';
                }
                if ($normalizedRole === 'admin' || $normalizedRole === 'employee') {
                    $normalizedRole = 'official';
                }

                if (in_array($normalizedRole, ['official', 'personnel'], true)) {
                    $stmtApproval = $conn->prepare("
                        SELECT status
                        FROM officialinvitetbl
                        WHERE user_id = ?
                        ORDER BY invite_id DESC
                        LIMIT 1
                    ");
                    if ($stmtApproval) {
                        $stmtApproval->bind_param('s', $userId);
                        $stmtApproval->execute();
                        $approvalRow = $stmtApproval->get_result()->fetch_assoc();
                        $stmtApproval->close();
                        $inviteStatus = strtolower(trim((string)($approvalRow['status'] ?? '')));
                        if ($inviteStatus !== 'completed') {
                            return appUrl('/official-onboarding');
                        }
                    }
                }

                return appUrl('/Admin-End/AdminDashboard.php');

            default:
                return appUrl('/logout');
        }
    }
}
