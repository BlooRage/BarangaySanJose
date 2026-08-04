<?php
require_once __DIR__ . "/../../PhpFiles/General/security.php";
require_once __DIR__ . "/../../PhpFiles/General/connection.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Enforce auth + 30-min inactivity timeout for Resident pages.
requireRoleSession(['Resident'], false);

if (!function_exists('resident_guard_normalize_public_path')) {
    function resident_guard_normalize_public_path(string $path): string
    {
        $path = trim(str_replace("\\", "/", $path));
        if ($path === '') {
            return '';
        }

        $path = preg_replace('#/+#', '/', $path) ?: $path;
        $path = rtrim($path, '/');
        if ($path === '') {
            return '';
        }

        $rootPath = function_exists('appRootPath') ? appRootPath() : '';
        if ($rootPath !== '' && strpos($path, $rootPath . '/') === 0) {
            $path = substr($path, strlen($rootPath));
        } elseif ($rootPath !== '' && $path === $rootPath) {
            $path = '/';
        }

        return preg_replace('/\.php$/i', '', $path) ?? $path;
    }
}

if (!function_exists('resident_guard_normalize_status_key')) {
    function resident_guard_normalize_status_key(?string $statusName): string
    {
        $statusName = strtolower(trim((string)$statusName));
        return preg_replace('/[\s_-]+/', '', $statusName) ?? '';
    }
}

if (!function_exists('resident_guard_is_verified_status')) {
    function resident_guard_is_verified_status(?string $statusName): bool
    {
        $statusKey = resident_guard_normalize_status_key($statusName);
        if ($statusKey === '' || $statusKey === 'notverified') {
            return false;
        }

        return in_array($statusKey, ['verifiedresident', 'verified', 'approved', 'completed'], true)
            || strpos($statusKey, 'verified') !== false
            || strpos($statusKey, 'approved') !== false
            || strpos($statusKey, 'complete') !== false;
    }
}

$userId = (string)($_SESSION['user_id'] ?? '');
$hasResidentProfile = false;
$residentStatusName = '';
$residentStatusKey = '';
$isResidentVerified = false;
$isResidentNotVerified = false;

if (isset($conn) && $conn instanceof mysqli) {
    $stmt = $conn->prepare("
        SELECT r.resident_id, COALESCE(s.status_name, '') AS status_name
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
            $hasResidentProfile = trim((string)$residentId) !== '';
            $residentStatusKey = resident_guard_normalize_status_key($residentStatusName);
            $isResidentVerified = resident_guard_is_verified_status($residentStatusName);
            $isResidentNotVerified = ($residentStatusKey === 'notverified');
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

// Only apply "not verified yet" routing after the resident has an actual profile record.
if ($hasResidentProfile && !$isResidentVerified) {
    $scriptPath = resident_guard_normalize_public_path((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $allowedForUnverified = [
        '/Resident-End/resident_dashboard.php',
        '/Resident-End/resident_calendar.php',
        '/Resident-End/resident_profile.php',
        '/Resident-End/DocumentUpload.php',
        '/Resident-End/Certificates/CertificatesLandingPage.php',
        '/Resident-End/Clearances/ClearancesLandingPage.php',
        '/Resident-End/BarangayId/BarangayIdLandingPage.php',
        '/Resident-End/document_requests.php',
        '/Resident-End/Announcements/AnnouncementsLandingPage.php',
        '/Resident-End/Appointments/AppointmentsLandingPage.php',
        '/Resident-End/Appointments/AppointmentForm.php',
        '/Resident-End/Complaints/ComplaintsLandingPage.php',
        '/Resident-End/Complaints/ComplaintsForm.php',
        '/Resident-End/appointment_tracker.php',
        '/Resident-End/complaint_tracker.php',
    ];
    $allowedForUnverified = array_map('resident_guard_normalize_public_path', $allowedForUnverified);

    if (!$allowUnverifiedResident && !in_array($scriptPath, $allowedForUnverified, true)) {
        $_SESSION['show_not_verified_modal'] = true;
        header("Location: " . appUrl('/Resident-End/resident_dashboard.php'));
        exit;
    }
}

// Prevent direct access to request forms that administrators have suspended.
if ($hasResidentProfile && $isResidentVerified) {
    require_once __DIR__ . '/../../PhpFiles/General/documentModuleSettings.php';
    $requestPath = strtolower(resident_guard_normalize_public_path((string)($_SERVER['SCRIPT_NAME'] ?? '')));
    $certificateForms = [
        'cohabitationform' => str_contains(strtolower((string)($_SERVER['QUERY_STRING'] ?? '')), 'jail') ? 'jail_visitation' : 'cohabitation',
        'indigencyform' => 'indigency', 'firsttimejobseekerform' => 'first_time_job_seeker',
        'goodmoralform' => 'good_moral', 'residencyform' => 'residency',
    ];
    $clearanceForms = [
        'barangaycertificationform' => 'general', 'businessclearanceform' => 'business_permit',
        'tricycleform' => 'tricycle_permit', 'electricalform' => 'electrical_permit',
        'waterform' => 'water_permit', 'residentialform' => 'residential_permit',
        'commercialform' => 'commercial_permit',
    ];
    $formName = strtolower((string)pathinfo($requestPath, PATHINFO_BASENAME));
    if (isset($certificateForms[$formName])) {
        $settings = dms_resolve_issuance_settings($conn);
        if (empty($settings['online_requests_enabled']) || empty($settings['certificates'][$certificateForms[$formName]]['enabled'])) {
            header('Location: ' . appUrl('/Resident-End/Certificates/CertificatesLandingPage.php?unavailable=1'));
            exit;
        }
    } elseif (isset($clearanceForms[$formName])) {
        $settings = dms_resolve_clearance_settings($conn);
        if (empty($settings['online_requests_enabled']) || empty($settings['clearance_types'][$clearanceForms[$formName]]['enabled'])) {
            header('Location: ' . appUrl('/Resident-End/Clearances/ClearancesLandingPage.php?unavailable=1'));
            exit;
        }
    }
}
