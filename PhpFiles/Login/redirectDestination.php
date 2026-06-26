<?php
require_once __DIR__ . '/../General/officialInviteCommon.php';

if (!function_exists('redirectDestinationInternalUrl')) {
    function redirectDestinationInternalUrl(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        return rtrim(appRootPath(), '/') . $path;
    }
}

if (!function_exists('resolveUnifiedProfileRedirect')) {
    function resolveUnifiedProfileRedirect(mysqli $conn, ?string $userId, ?string $role): string
    {
        $userId = trim((string)$userId);
        $role = trim((string)$role);

        if ($userId === '' || $role === '') {
            return appUrl('/login');
        }

        $officialRoles = ['Official', 'Officials', 'Personnel', 'Personnels', 'SuperAdmin', 'Admin'];
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
                        return redirectDestinationInternalUrl('/Resident-End/resident_registration.php');
                    }
                }
                return redirectDestinationInternalUrl('/Resident-End/resident_dashboard.php');

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
                if ($normalizedRole === 'admin') {
                    $normalizedRole = 'official';
                }
                if ($normalizedRole === 'employee') {
                    $normalizedRole = 'personnel';
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

                return redirectDestinationInternalUrl('/Admin-End/AdminDashboard.php');

            default:
                return appUrl('/logout');
        }
    }
}

if (!function_exists('normalizeRequestedResidentService')) {
    function normalizeRequestedResidentService(?string $service): string
    {
        $service = strtolower(trim((string)$service));
        if ($service === '') {
            return '';
        }

        $service = preg_replace('/[^a-z0-9]+/', '-', $service);
        $service = trim((string)$service, '-');

        $aliases = [
            'barangayid' => 'barangay-id',
            'barangay-id' => 'barangay-id',
            'complaint' => 'complaints',
            'complaints' => 'complaints',
            'certificate' => 'certificates',
            'certificates' => 'certificates',
            'clearance' => 'clearances',
            'clearances' => 'clearances',
            'appointment' => 'appointments',
            'appointments' => 'appointments',
        ];

        return $aliases[$service] ?? '';
    }
}

if (!function_exists('residentServiceDisplayName')) {
    function residentServiceDisplayName(?string $service): string
    {
        $service = normalizeRequestedResidentService($service);
        $labels = [
            'certificates' => 'Certificates',
            'clearances' => 'Clearances',
            'barangay-id' => 'Barangay ID',
            'appointments' => 'Appointments',
            'complaints' => 'Complaints',
        ];

        return $labels[$service] ?? '';
    }
}

if (!function_exists('resolveResidentServiceRedirect')) {
    function resolveResidentServiceRedirect(?string $service): ?string
    {
        $service = normalizeRequestedResidentService($service);
        if ($service === '') {
            return null;
        }

        $routes = [
            'certificates' => appUrl('/Resident-End/Certificates/CertificatesLandingPage.php'),
            'clearances' => appUrl('/Resident-End/Clearances/ClearancesLandingPage.php'),
            'barangay-id' => appUrl('/Resident-End/BarangayId/BarangayIdLandingPage.php'),
            'appointments' => appUrl('/Resident-End/Appointments/AppointmentsLandingPage.php'),
            'complaints' => appUrl('/Resident-End/Complaints/ComplaintsLandingPage.php'),
        ];

        return $routes[$service] ?? null;
    }
}

if (!function_exists('resolveRequestedPostLoginRedirect')) {
    function resolveRequestedPostLoginRedirect(mysqli $conn, ?string $userId, ?string $role, ?string $requestedService = null): string
    {
        $defaultRedirect = resolveUnifiedProfileRedirect($conn, $userId, $role);
        $normalizedRole = strtolower(trim((string)$role));

        if ($normalizedRole !== 'resident') {
            return $defaultRedirect;
        }

        $serviceRedirect = resolveResidentServiceRedirect($requestedService);
        if ($serviceRedirect === null) {
            return $defaultRedirect;
        }

        if (stripos($defaultRedirect, '/Resident-End/resident_registration') !== false) {
            return $defaultRedirect;
        }

        return $serviceRedirect;
    }
}
