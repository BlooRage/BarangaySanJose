<?php
$current = basename($_SERVER['PHP_SELF']);

// Group pages by section
$residentMgmtPages = ['ResidentTracker.php', 'ResidentMasterlist.php', 'ResidentArchive.php', 'EditRequests.php', 'SectorMembershipVerification.php', 'AddResident.php'];
$householdProfilingPages = ['HouseholdProfiling.php', 'HeadOfTheFamilyVerification.php', 'HouseholdMemberVerification.php'];
$appointmentPages = ['AppointmentTracker.php', 'AppointmentRequestVerification.php'];
$certPages = ['CertificateTracker.php'];
$financePages = ['FinancePayments.php'];
$blotterPages = ['BlotterForm.php', 'BlotterTracker.php', 'ReviewQueue.php'];
$complaintPages = ['ComplaintForm.php', 'ComplaintTracker.php'];
$contentMgmtPages = ['Contents.php', 'CreateContent.php'];
$areaManagementPages = ['AreaStatistics.php', 'AreaProfile.php'];
$reportPages = ['Reports.php'];
$userMgmtPages = ['UserMasterlist.php', 'UserArchive.php'];
$personnelMgmtPages = ['PersonnelTracker.php', 'OfficialInvites.php', 'PersonnelRoleAccess.php'];
$adminMgmtPages = ['UserMasterlist.php', 'UserArchive.php', 'PersonnelTracker.php', 'OfficialInvites.php', 'PersonnelRoleAccess.php', 'AuditLogs.php'];
$barangayOfficialMgmtPages = ['OfficialsManagement.php', 'OfficialTransitions.php'];
$officialTransitionPages = ['OfficialTransitions.php'];

$officialTransitionTool = trim((string)($_GET['tool'] ?? 'current_term'));
if ($officialTransitionTool === '' || in_array($officialTransitionTool, ['tracker', 'new_set', 'past_officials', 'official_permissions', 'kagawad_permissions'], true)) {
    $officialTransitionTool = 'current_term';
} elseif ($officialTransitionTool === 'create_new_term') {
    $officialTransitionTool = 'create_new_term';
} elseif ($officialTransitionTool === 'settings') {
    $officialTransitionTool = 'settings';
} else {
    $officialTransitionTool = 'current_term';
}

$appointmentTool = strtolower(trim((string)($_GET['tool'] ?? 'tracker')));
if (!in_array($appointmentTool, ['tracker', 'settings'], true)) {
    $appointmentTool = 'tracker';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('appUrl')) {
    require_once __DIR__ . '/../../PhpFiles/General/security.php';
}
require_once __DIR__ . '/../../PhpFiles/General/adminModulePermissions.php';
require_once __DIR__ . '/../../PhpFiles/General/siteContent.php';
$sbAttentionHelperPath = __DIR__ . '/../../PhpFiles/General/adminSidebarAttention.php';
if (file_exists($sbAttentionHelperPath)) {
    require_once $sbAttentionHelperPath;
}

if ((!isset($conn) || !($conn instanceof mysqli)) && file_exists(__DIR__ . '/../../PhpFiles/General/connection.php')) {
    require_once __DIR__ . '/../../PhpFiles/General/connection.php';
}

$scriptName = str_replace("\\", "/", (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$adminSegmentPos = strpos($scriptName, '/Admin-End/');
$baseUrl = '';
if ($adminSegmentPos !== false) {
    $baseUrl = substr($scriptName, 0, $adminSegmentPos);
} else {
    $baseUrl = dirname($scriptName);
}
$baseUrl = rtrim((string)$baseUrl, '/');
if ($baseUrl === '.' || $baseUrl === '/') {
    $baseUrl = '';
}

if (!function_exists('appUrl')) {
    function appUrl(string $path): string
    {
        global $baseUrl;
        return ($baseUrl === '' ? '' : $baseUrl) . '/' . ltrim($path, '/');
    }
}

$isResidentMgmtActive = in_array($current, $residentMgmtPages);
$isHouseholdProfilingActive = in_array($current, $householdProfilingPages);
$isAppointmentActive = in_array($current, $appointmentPages);
$isAppointmentTrackerActive = $current === 'AppointmentTracker.php' && $appointmentTool === 'tracker';
$isAppointmentSettingsActive = $current === 'AppointmentTracker.php' && $appointmentTool === 'settings';
$isFinanceActive = in_array($current, $financePages);
$isBlotterActive = in_array($current, $blotterPages);
$isComplaintActive = in_array($current, $complaintPages);
$isContentMgmtActive = in_array($current, $contentMgmtPages);
$isAreaManagementActive = in_array($current, $areaManagementPages);
$isUserMgmtActive = in_array($current, $userMgmtPages);
$isAdminMgmtActive = in_array($current, $adminMgmtPages);
$isPersonnelMgmtActive = in_array($current, $personnelMgmtPages);
$isBarangayOfficialMgmtActive = in_array($current, $barangayOfficialMgmtPages);
$isOfficialTransitionActive = in_array($current, $officialTransitionPages);
$isReportActive = in_array($current, $reportPages);
$isStatisticsActive = ($current === 'AdminDashboard.php');
$isAdminProfileActive = ($current === 'admin_profile.php');
$reportModule = strtolower(trim((string)($_GET['module'] ?? '')));
if ($reportModule === 'document_requests') {
    $reportModule = 'certificate_issuance';
}
$areaManagementTab = strtolower(trim((string)($_GET['tab'] ?? 'summary')));
$areaManagementArea = trim((string)($_GET['area'] ?? ''));
$isSuperAdminSidebar = ((string)($_SESSION['role'] ?? '') === 'SuperAdmin');
$financeSection = strtolower(trim((string)($_GET['section'] ?? 'tracker')));
$certificateTab = strtolower(trim((string)($_GET['tab'] ?? 'tracker')));
$certificateDocument = strtolower(trim((string)($_GET['document'] ?? '')));
$certificateStage = strtolower(trim((string)($_GET['stage'] ?? '')));
$certificateEntry = strtolower(trim((string)($_GET['entry'] ?? '')));
$certificateFilterDocument = trim((string)($_GET['filter_document'] ?? ''));
$certificateFilterDocumentToken = strtolower(trim($certificateFilterDocument));
$certificateFilterDocumentKey = preg_replace('/[^a-z0-9]+/', '', $certificateFilterDocumentToken) ?? '';
$isIdIssuanceManualActive = $current === 'CertificateTracker.php'
    && $certificateTab === 'manual'
    && $certificateDocument === 'barangay_id';
$isIdIssuanceTrackerActive = $current === 'CertificateTracker.php'
    && !$isIdIssuanceManualActive
    && (
        $certificateEntry === 'id_issuance'
        || $certificateStage === 'barangay_id'
        || strcasecmp($certificateFilterDocument, 'Barangay ID') === 0
    );
$isIdIssuanceActive = $isIdIssuanceTrackerActive || $isIdIssuanceManualActive;
$isCertificateTrackerActive = $current === 'CertificateTracker.php' && !$isIdIssuanceActive;
$isCertActive = $isCertificateTrackerActive;
$isClearanceIssuanceActive = $current === 'CertificateTracker.php'
    && !$isIdIssuanceActive
    && in_array($certificateFilterDocumentToken, ['__clearances__', '__clearance__', 'clearance', 'clearances'], true);
$isClearanceBusinessPermitActive = $current === 'CertificateTracker.php'
    && !$isIdIssuanceActive
    && (
        $certificateFilterDocumentToken === '__clr_business_permit__'
        || $certificateFilterDocumentKey === 'barangayclearanceforbusinesspermit'
        || $certificateFilterDocumentKey === 'clearanceforbusinesspermit'
    );
$isClearanceTricyclePermitActive = $current === 'CertificateTracker.php'
    && !$isIdIssuanceActive
    && (
        $certificateFilterDocumentToken === '__clr_tricycle_permit__'
        || $certificateFilterDocumentKey === 'barangayclearancefortricyclepermit'
        || $certificateFilterDocumentKey === 'clearancefortricyclepermit'
    );
$isClearanceElectricPermitActive = $current === 'CertificateTracker.php'
    && !$isIdIssuanceActive
    && (
        $certificateFilterDocumentToken === '__clr_electric_permit__'
        || $certificateFilterDocumentKey === 'barangayclearanceforelectricalpermit'
        || $certificateFilterDocumentKey === 'clearanceforelectricalpermit'
        || $certificateFilterDocumentKey === 'clearanceforelectricpermit'
    );
$isClearanceWaterPermitActive = $current === 'CertificateTracker.php'
    && !$isIdIssuanceActive
    && (
        $certificateFilterDocumentToken === '__clr_water_permit__'
        || $certificateFilterDocumentKey === 'barangayclearanceforwaterpermit'
        || $certificateFilterDocumentKey === 'clearanceforwaterpermit'
    );
$isClearanceResidentialPermitActive = $current === 'CertificateTracker.php'
    && !$isIdIssuanceActive
    && (
        $certificateFilterDocumentToken === '__clr_residential_permit__'
        || $certificateFilterDocumentKey === 'barangayclearanceforresidentialpermit'
        || $certificateFilterDocumentKey === 'clearanceforresidentialpermit'
    );
$isClearanceCommercialPermitActive = $current === 'CertificateTracker.php'
    && !$isIdIssuanceActive
    && (
        $certificateFilterDocumentToken === '__clr_commercial_permit__'
        || $certificateFilterDocumentKey === 'barangayclearanceforcommercialpermit'
        || $certificateFilterDocumentKey === 'clearanceforcommercialpermit'
    );
$isClearanceIssuanceSectionActive = $isClearanceIssuanceActive
    || $isClearanceBusinessPermitActive
    || $isClearanceTricyclePermitActive
    || $isClearanceElectricPermitActive
    || $isClearanceWaterPermitActive
    || $isClearanceResidentialPermitActive
    || $isClearanceCommercialPermitActive;
$isBusinessMonitoringActive = $current === 'BusinessMonitoring.php'
    || (
        $current === 'CertificateTracker.php'
        && !$isIdIssuanceActive
        && (
            in_array($certificateFilterDocumentToken, ['__business__', 'business', 'business_monitoring', 'businessclearance'], true)
            || strcasecmp($certificateFilterDocument, 'Barangay Clearance for Business Permit') === 0
        )
    );
$isCertificateIssuanceSectionActive = $current === 'CertificateTracker.php'
    && !$isIdIssuanceActive
    && !$isClearanceIssuanceSectionActive
    && !$isBusinessMonitoringActive;
$isCertificateCohabitationActive = $isCertificateIssuanceSectionActive
    && (
        $certificateFilterDocumentToken === '__cert_cohabitation__'
        || $certificateFilterDocumentKey === 'certificateofcohabitation'
    );
$isCertificateGoodMoralActive = $isCertificateIssuanceSectionActive
    && (
        $certificateFilterDocumentToken === '__cert_good_moral__'
        || $certificateFilterDocumentKey === 'certificateofgoodmoral'
        || $certificateFilterDocumentKey === 'goodmoral'
    );
$isCertificateJailVisitationActive = $isCertificateIssuanceSectionActive
    && (
        $certificateFilterDocumentToken === '__cert_jail_visit__'
        || in_array($certificateFilterDocumentKey, [
            'certificateofrelationshipforjailvisitation',
            'certificateforjailvisitation',
            'jailvisitation',
        ], true)
    );
$isCertificateFirstTimeJobSeekerActive = $isCertificateIssuanceSectionActive
    && (
        $certificateFilterDocumentToken === '__cert_first_time_job_seeker__'
        || in_array($certificateFilterDocumentKey, [
            'firsttimejobseekercertificate',
            'certificateforfirsttimejobseeker',
            'firsttimejobseeker',
        ], true)
    );
$isCertificateResidencyActive = $isCertificateIssuanceSectionActive
    && (
        $certificateFilterDocumentToken === '__cert_residency__'
        || in_array($certificateFilterDocumentKey, ['certificateofresidency', 'certificateofresidence', 'residency'], true)
    );
$isCertificateIndigencyActive = $isCertificateIssuanceSectionActive
    && (
        $certificateFilterDocumentToken === '__cert_indigency__'
        || $certificateFilterDocumentKey === 'certificateofindigency'
        || $certificateFilterDocumentKey === 'indigency'
    );
$isFinanceCreateActive = $current === 'FinancePayments.php' && $financeSection === 'create';
$isFinanceFeesActive = $current === 'FinancePayments.php' && $financeSection === 'fees';
$isFinanceTrackerActive = $current === 'FinancePayments.php' && !in_array($financeSection, ['fees', 'create'], true);
$contentCreateType = strtolower(trim((string)($_GET['type'] ?? 'page')));
if (!in_array($contentCreateType, ['page', 'delivery', 'faq'], true)) {
    $contentCreateType = 'page';
}
$contentToolView = strtolower(trim((string)($_GET['tool'] ?? 'tracker')));
if ($contentToolView !== 'tracker') {
    $contentToolView = 'tracker';
}
$contentManagementModule = strtolower(trim((string)($_GET['module'] ?? 'requests')));
if (!in_array($contentManagementModule, ['requests', 'announcements', 'home', 'government', 'services', 'faq', 'contact', 'login'], true)) {
    $contentManagementModule = 'requests';
}
$contentRequestsView = strtolower(trim((string)($_GET['requests_view'] ?? 'my_requests')));
if (!in_array($contentRequestsView, ['my_requests', 'review_queue', 'archived_requests', 'approved_history'], true)) {
    $contentRequestsView = 'my_requests';
}
$isContentCreateSectionActive = $current === 'CreateContent.php';
$isContentToolsSectionActive = $current === 'Contents.php';
$isContentFaqCreateActive = $current === 'CreateContent.php' && $contentCreateType === 'faq';
$isContentNavigatorActive = $current === 'ContentManagement.php' || $isContentFaqCreateActive;
$isContentChangeRequestActive = $current === 'ContentManagement.php'
    && $contentManagementModule === 'requests';

$sbAllowedPermissions = [];
if (isset($conn) && $conn instanceof mysqli && !empty($_SESSION['user_id'])) {
    amp_ensure_permission_storage($conn);
    $sbAllowedPermissions = amp_get_allowed_permission_keys(
        $conn,
        (string)$_SESSION['user_id'],
        (string)($_SESSION['role'] ?? '')
    );
} elseif ($isSuperAdminSidebar) {
    $sbAllowedPermissions = array_fill_keys(amp_get_all_leaf_permission_keys(), true);
}

$sbCan = static function (string $key) use (&$sbAllowedPermissions): bool {
    return amp_permission_key_allowed($sbAllowedPermissions, $key);
};
$sbHasAny = static function (array $keys) use (&$sbAllowedPermissions): bool {
    return amp_permission_keys_have_any($sbAllowedPermissions, $keys);
};

$sbAttentionCounts = function_exists('sbatt_default_counts') ? sbatt_default_counts() : [];
if (isset($conn) && $conn instanceof mysqli && function_exists('sbatt_get_counts')) {
    $sbAttentionCounts = sbatt_get_counts($conn, 45);
}
if (!$sbCanReviewContent) {
    $sbAttentionCounts['content_change_request'] = 0;
}

$sbCount = static function (string $key) use (&$sbAttentionCounts): int {
    return max(0, (int)($sbAttentionCounts[$key] ?? 0));
};
$sbModuleCount = static function (string $key) use (&$sbAttentionCounts): int {
    return max(0, (int)($sbAttentionCounts[$key] ?? 0));
};
$sbRenderAttentionBadge = static function (int $count): string {
    if ($count <= 0) {
        return '';
    }

    $display = $count > 99 ? '99+' : (string)$count;
    return '<span class="sidebar-attention-badge" aria-label="' . htmlspecialchars($display . ' items need attention', ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($display, ENT_QUOTES, 'UTF-8') . '</span>';
};
$sbRenderAttentionDot = static function (int $count): string {
    if ($count <= 0) {
        return '';
    }

    return '<span class="sidebar-attention-dot" aria-hidden="true"></span>';
};

$sbResidentProfilingKeys = [
    'resident_masterlist',
    'resident_edit_requests',
    'resident_archive',
    'resident_sector_membership_verification',
];
$sbHouseholdProfilingKeys = [
    'household_profiling_main',
    'head_of_family_verification',
    'household_member_verification',
];
$sbAreaStatisticsKeys = [
    'area_statistics_summary',
    'area_profile_area_01',
    'area_profile_area_1a',
    'area_profile_area_02',
    'area_profile_area_03',
    'area_profile_area_04',
    'area_profile_area_05',
    'area_profile_area_06',
];
$sbIdIssuanceKeys = [
    'id_issuance_tracker',
    'id_issuance_manual',
];
$sbFinanceKeys = [
    'finance_payment_tracker',
    'finance_create_transaction',
    'finance_fee_management',
];
$sbBlotterKeys = [
    'blotter_log_new_incident',
    'blotter_tracker',
    'blotter_review_queue',
];
$sbComplaintKeys = [
    'complaint_log_new_incident',
    'complaint_tracker',
];
$sbAnnouncementKeys = [
    'announcements_page',
    'announcements_delivery',
    'announcements_faq',
    'announcements_tracker',
];
$sbReportKeys = [
    'reports_certificate_issuance',
    'reports_clearance_issuance',
    'reports_financial',
    'reports_residents',
    'reports_blotter',
    'reports_complaints',
];
$sbAdminKeys = [
    'user_masterlist',
    'officials_management',
    'personnel_invite',
    'official_transition',
    'audit_logs',
];

$sbModuleAttentionCounts = [
    'appointments' => $sbCan('appointments') ? $sbCount('appointments_tracker') : 0,
    'resident_profiling' =>
        ($sbCan('resident_masterlist') ? $sbCount('resident_tracker') : 0)
        + ($sbCan('resident_edit_requests') ? $sbCount('edit_requests') : 0)
        + ($sbCan('resident_sector_membership_verification') ? $sbCount('sector_membership_verification') : 0),
    'household_profiling' =>
        ($sbCan('head_of_family_verification') ? $sbCount('head_of_family_verification') : 0)
        + ($sbCan('household_member_verification') ? $sbCount('household_member_verification') : 0),
    'certificate_issuance' => $sbCan('certificate_issuance') ? $sbCount('certificate_issuance') : 0,
    'id_issuance' => $sbCan('id_issuance_tracker') ? $sbCount('id_issuance_tracker') : 0,
    'clearance_issuance' => $sbCan('clearance_issuance') ? $sbCount('clearance_issuance') : 0,
    'finance_transactions' =>
        ($sbCan('finance_payment_tracker') ? $sbCount('finance_payment_tracker') : 0)
        + ($sbCan('finance_fee_management') ? $sbCount('finance_fee_management') : 0),
    'blotter_tools' => $sbCan('blotter_review_queue') ? $sbCount('blotter_review_queue') : 0,
    'complaint_tools' => $sbCan('complaint_tracker') ? $sbCount('complaint_tracker') : 0,
    'content_management' => $sbCanReviewContent ? $sbCount('content_change_request') : 0,
    'user_management' => ($sbCan('user_masterlist') || $sbCan('user_archive')) ? $sbCount('user_management') : 0,
];
$sbModuleCount = static function (string $key) use (&$sbModuleAttentionCounts): int {
    return max(0, (int)($sbModuleAttentionCounts[$key] ?? 0));
};

$adminDisplayName = "Admin User";
$adminPosition = "Administrator";
$adminProfileImageUrl = appUrl('Images/Profile-Placeholder.png');

function sb_format_position_label(string $systemRole, string $positionAccess, string $department, string $areaNumber): string
{
    $systemRole = trim($systemRole);
    $positionAccess = trim($positionAccess);
    $department = trim($department);
    $areaNumber = trim($areaNumber);

    if ($positionAccess === '') {
        $positionAccess = $systemRole;
    }
    if ($positionAccess === '') {
        return 'Administrator';
    }

    if ($positionAccess === 'IT Administrator' || $positionAccess === 'Barangay Chairman' || $positionAccess === 'Barangay Official') {
        return $positionAccess;
    }

    if (in_array($positionAccess, ['Barangay Police', 'Desk Officer', 'Barangay Secretary', 'Area OIC'], true)) {
        return ($areaNumber !== '' ? $areaNumber : 'Area N/A') . ' - ' . $positionAccess;
    }

    if ($positionAccess === 'Department OIC (Officer In Charge)' && strcasecmp($department, 'Barangay Peace and Order') === 0) {
        return ($areaNumber !== '' ? $areaNumber : 'Area N/A') . ' - OIC (Barangay Police)';
    }

    if (stripos($positionAccess, 'Department ') === 0) {
        $positionAccess = trim(substr($positionAccess, strlen('Department ')));
    }

    if ($department !== '' && strcasecmp($department, 'Office of the Barangay') !== 0) {
        return $department . ' - ' . $positionAccess;
    }

    return $positionAccess;
}

if (!function_exists('sb_to_public_profile_path')) {
    function sb_to_public_profile_path(string $path): string
    {
        $normalized = trim(str_replace("\\", "/", $path));
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('#/+#', '/', $normalized) ?: $normalized;
        $normalized = ltrim($normalized, '/');

        $rootPrefix = trim(appRootPath(), '/');
        if ($rootPrefix !== '' && stripos($normalized, $rootPrefix . '/') === 0) {
            $normalized = substr($normalized, strlen($rootPrefix) + 1);
        }

        $marker = 'UnifiedFileAttachment/';
        $markerPos = stripos($normalized, $marker);
        if ($markerPos !== false) {
            $normalized = substr($normalized, $markerPos);
        }

        return $normalized !== '' ? appUrl($normalized) : '';
    }
}

if (!empty($_SESSION['user_id']) && isset($conn) && $conn instanceof mysqli) {
    $hasPositionAccess = false;
    $colRes = $conn->query("SHOW COLUMNS FROM officialinformationtbl LIKE 'position_access'");
    if ($colRes instanceof mysqli_result && $colRes->num_rows > 0) {
        $hasPositionAccess = true;
    }
    $hasAreaNumber = false;
    $areaColRes = $conn->query("SHOW COLUMNS FROM officialinformationtbl LIKE 'area_number'");
    if ($areaColRes instanceof mysqli_result && $areaColRes->num_rows > 0) {
        $hasAreaNumber = true;
    }
    $selectPosition = $hasPositionAccess ? "position_access" : "NULL AS position_access";
    $selectAreaNumber = $hasAreaNumber ? "area_number" : "NULL AS area_number";
    $stmtInfo = $conn->prepare("
        SELECT firstname, middlename, lastname, suffix, role_access, {$selectPosition}, department, {$selectAreaNumber}
        FROM officialinformationtbl
        WHERE user_id = ?
        LIMIT 1
    ");
    if ($stmtInfo) {
        $stmtInfo->bind_param("s", $_SESSION['user_id']);
        $stmtInfo->execute();
        $info = $stmtInfo->get_result()->fetch_assoc();
        $info = $info ? (pii_decrypt_official_row($info) ?? $info) : null;
        if ($info) {
            $fullName = trim(
                $info['firstname'] . ' ' .
                ($info['middlename'] ? $info['middlename'][0] . '. ' : '') .
                $info['lastname'] .
                ($info['suffix'] ? ' ' . $info['suffix'] : '')
            );
            if ($fullName !== '') {
                $adminDisplayName = $fullName;
            }
            $adminPosition = sb_format_position_label(
                (string)($info['role_access'] ?? ''),
                (string)($info['position_access'] ?? ''),
                (string)($info['department'] ?? ''),
                (string)($info['area_number'] ?? '')
            );
        }
        $stmtInfo->close();
    }

    $stmtAvatar = $conn->prepare("
        SELECT uf.file_path
        FROM unifiedfileattachmenttbl uf
        INNER JOIN documenttypelookuptbl dt
          ON dt.document_type_id = uf.document_type_id
        WHERE uf.source_type = 'OFFICIAL_PROFILE'
          AND uf.source_id = ?
          AND LOWER(dt.document_type_name) = LOWER('2x2 Picture')
          AND dt.document_category = 'OfficialProfiling'
        ORDER BY COALESCE(uf.updated_at, uf.upload_timestamp) DESC, uf.attachment_id DESC
        LIMIT 1
    ");
    if ($stmtAvatar) {
        $stmtAvatar->bind_param("s", $_SESSION['user_id']);
        $stmtAvatar->execute();
        $avatar = $stmtAvatar->get_result()->fetch_assoc();
        $stmtAvatar->close();
        if ($avatar && !empty($avatar['file_path'])) {
            $resolvedAvatarPath = sb_to_public_profile_path((string)$avatar['file_path']);
            if ($resolvedAvatarPath !== '') {
                $adminProfileImageUrl = $resolvedAvatarPath;
            }
        }
    }
}
?>

<script src="<?= htmlspecialchars(appUrl('JS-Script-Files/modalHandler.js'), ENT_QUOTES, 'UTF-8') ?>"></script>

<style>
  #dashboard-sidebar .btn-toggle,
  #dashboard-sidebar .sidebar-direct-link {
    width: 100%;
  }

  #dashboard-sidebar .sidebar-button-label,
  #dashboard-sidebar .sidebar-subnav-text {
    min-width: 0;
  }

  #dashboard-sidebar .sidebar-button-label--certificate {
    font-size: 0.9rem;
    white-space: nowrap;
  }

  #dashboard-sidebar .sidebar-direct-link--certificate {
    gap: 0.35rem;
  }

  #dashboard-sidebar .sidebar-direct-link--certificate .sidebar-attention-badge {
    min-width: 1.3rem;
    height: 1.3rem;
    padding: 0 0.3rem;
    font-size: 0.68rem;
  }

  #dashboard-sidebar .sidebar-icon-wrap {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.2rem;
    min-width: 1.2rem;
  }

  #dashboard-sidebar .sidebar-attention-dot {
    position: absolute;
    top: -0.18rem;
    right: -0.22rem;
    width: 0.58rem;
    height: 0.58rem;
    border-radius: 999px;
    background: #dc3545;
    border: 2px solid #fff;
    box-shadow: 0 0 0 1px rgba(220, 53, 69, 0.14);
  }

  #dashboard-sidebar .sidebar-attention-badge {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.4rem;
    height: 1.4rem;
    padding: 0 0.38rem;
    border-radius: 999px;
    background: #dc3545;
    color: #fff;
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 1;
    flex: 0 0 auto;
  }

  #dashboard-sidebar .sidebar-subnav-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
  }

  #dashboard-sidebar .sidebar-direct-link {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  #admin-mobile-header {
    display: none;
  }

  @media screen and (orientation: portrait) and (max-width: 1024px) {
    #admin-mobile-header {
      display: block;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      z-index: 1200;
    }

    #dashboard-sidebar {
      position: fixed !important;
      top: 0;
      left: 0;
      width: 100% !important;
      min-width: 0;
      height: 100vh;
      transform: translateX(-100%);
      transition: transform 0.3s ease;
      z-index: 1100;
      overflow-y: auto;
    }

    #dashboard-sidebar.show {
      transform: translateX(0);
    }

    body {
      padding-top: 60px;
    }
  }
</style>

<header id="admin-mobile-header">
  <div class="d-flex align-items-center px-3 py-2 shadow-sm bg-white">
    <div class="d-flex align-items-center gap-2">
      <button class="btn" id="btn-admin-burger" type="button" aria-label="Toggle sidebar">
        <i class="fa-solid fa-bars fa-lg"></i>
      </button>
      <img src="<?= htmlspecialchars(appUrl('Images/San_Jose_LOGO.jpg')) ?>" alt="Logo" style="width:32px;height:32px">
      <span class="logo-name">Barangay San Jose</span>
    </div>
  </div>
</header>

<div class="d-flex flex-column flex-shrink-0 p-3 bg-white shadow-sm"
     style="width: 280px;"
     id="dashboard-sidebar">

  <!-- LOGO -->
  <a href="<?= htmlspecialchars(appUrl('Admin-End/AdminDashboard.php')) ?>" class="sidebar-brand-link pb-3 mb-3 link-dark text-decoration-none border-bottom">
    <img src="<?= htmlspecialchars(appUrl('Images/San_Jose_LOGO.jpg')) ?>" class="sidebar-brand-logo" alt="Barangay San Jose Logo">
    <span class="sidebar-brand-title">Barangay San Jose</span>
  </a>

  <div class="sidebar-body d-flex flex-column flex-grow-1">
    <ul class="list-unstyled ps-0 flex-grow-1 mb-0">

      <?php if ($sbCan('dashboard')): ?>
      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Home</li>
      <li class="mb-2">
        <a href="<?= htmlspecialchars(appUrl('Admin-End/AdminDashboard.php')) ?>"
           class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isStatisticsActive ? 'active' : '' ?>"
           style="<?= $isStatisticsActive ? 'outline: none; box-shadow: none;' : '' ?>">
          <i class="fas fa-house"></i> Dashboard
        </a>
      </li>
      <?php endif; ?>

      <?php if ($sbCan('appointments')): ?>
      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Office of the Barangay</li>
      <li class="mb-1">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isAppointmentActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#appointments-collapse"
                aria-expanded="<?= $isAppointmentActive ? 'true' : 'false' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-calendar-check"></i>
            <?= $sbRenderAttentionDot($sbModuleCount('appointments')) ?>
          </span>
          <span class="sidebar-button-label">Appointments</span>
        </button>
        <div class="collapse <?= $isAppointmentActive ? 'show' : '' ?>" id="appointments-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Appointments/AppointmentTracker.php?tool=tracker')) ?>"
                 class="link-dark rounded sidebar-subnav-link <?= $isAppointmentTrackerActive ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Tracker</span>
                <?= $sbRenderAttentionBadge($sbCount('appointments_tracker')) ?>
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Appointments/AppointmentTracker.php?tool=settings')) ?>"
                 class="link-dark rounded sidebar-subnav-link <?= $isAppointmentSettingsActive ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Settings</span>
              </a>
            </li>
          </ul>
        </div>
      </li>
      <?php endif; ?>

      <?php if ($sbHasAny(array_merge($sbResidentProfilingKeys, $sbHouseholdProfilingKeys, $sbAreaStatisticsKeys))): ?>
      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Resident Management</li>
      <?php if ($sbHasAny($sbResidentProfilingKeys)): ?>
      <li class="mb-1">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isResidentMgmtActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#resident-mgmt-collapse"
                aria-expanded="<?= $isResidentMgmtActive ? 'true' : 'false' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-user-group"></i>
            <?= $sbRenderAttentionDot($sbModuleCount('resident_profiling')) ?>
          </span>
          <span class="sidebar-button-label">Resident Profiling</span>
        </button>
        <div class="collapse <?= $isResidentMgmtActive ? 'show' : '' ?>" id="resident-mgmt-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <?php if ($sbCan('resident_masterlist')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/ResidentTracker.php')) ?>"
                 class="link-dark rounded sidebar-subnav-link <?= in_array($current, ['ResidentTracker.php', 'AddResident.php'], true) ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Resident Tracker</span>
                <?= $sbRenderAttentionBadge($sbCount('resident_tracker')) ?>
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/ResidentMasterlist.php')) ?>"
                 class="link-dark rounded sidebar-subnav-link <?= $current == 'ResidentMasterlist.php' ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Resident Masterlist</span>
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('resident_edit_requests')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/EditRequests.php')) ?>"
                 class="link-dark rounded sidebar-subnav-link <?= $current == 'EditRequests.php' ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Edit Requests</span>
                <?= $sbRenderAttentionBadge($sbCount('edit_requests')) ?>
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('resident_archive')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/ResidentArchive.php')) ?>"
                 class="link-dark rounded sidebar-subnav-link <?= $current == 'ResidentArchive.php' ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Resident Archive</span>
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('resident_sector_membership_verification')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/SectorMembershipVerification.php')) ?>"
                 class="link-dark rounded sidebar-subnav-link <?= $current == 'SectorMembershipVerification.php' ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Sector Membership Verification</span>
                <?= $sbRenderAttentionBadge($sbCount('sector_membership_verification')) ?>
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
      <?php endif; ?>
      <?php if ($sbHasAny($sbHouseholdProfilingKeys)): ?>
      <li class="mb-1">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isHouseholdProfilingActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#household-profiling-collapse"
                aria-expanded="<?= $isHouseholdProfilingActive ? 'true' : 'false' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-house"></i>
            <?= $sbRenderAttentionDot($sbModuleCount('household_profiling')) ?>
          </span>
          <span class="sidebar-button-label">Household Profiling</span>
        </button>
        <div class="collapse <?= $isHouseholdProfilingActive ? 'show' : '' ?>" id="household-profiling-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <?php if ($sbCan('household_profiling_main')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/HouseholdProfiling.php')) ?>"
                 class="link-dark rounded sidebar-subnav-link <?= $current == 'HouseholdProfiling.php' ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Household Profiling</span>
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('head_of_family_verification')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/HeadOfTheFamilyVerification.php')) ?>"
                 class="link-dark rounded sidebar-subnav-link <?= $current == 'HeadOfTheFamilyVerification.php' ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Head of the Family Verification</span>
                <?= $sbRenderAttentionBadge($sbCount('head_of_family_verification')) ?>
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('household_member_verification')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/HouseholdMemberVerification.php')) ?>"
                 class="link-dark rounded sidebar-subnav-link <?= $current == 'HouseholdMemberVerification.php' ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Household Member Verification</span>
                <?= $sbRenderAttentionBadge($sbCount('household_member_verification')) ?>
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
      <?php endif; ?>

      <?php if ($sbHasAny($sbAreaStatisticsKeys)): ?>
      <li class="mb-2">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isAreaManagementActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#area-management-collapse"
                aria-expanded="<?= $isAreaManagementActive ? 'true' : 'false' ?>">
          <i class="fas fa-map-location-dot"></i> Area Statistics
        </button>
        <div class="collapse <?= $isAreaManagementActive ? 'show' : '' ?>" id="area-management-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <?php if ($sbCan('area_statistics_summary')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/AreaManagement/AreaStatistics.php?tab=summary')) ?>"
                 class="link-dark rounded <?= ($current == 'AreaStatistics.php' && $areaManagementTab === 'summary') ? 'active' : '' ?>">
                Summary
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('area_profile_area_01')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/AreaManagement/AreaProfile.php?area=' . rawurlencode('Area 01'))) ?>"
                 class="link-dark rounded <?= ($current == 'AreaProfile.php' && $areaManagementArea === 'Area 01') ? 'active' : '' ?>">
                Area 01
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('area_profile_area_1a')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/AreaManagement/AreaProfile.php?area=' . rawurlencode('Area 1A'))) ?>"
                 class="link-dark rounded <?= ($current == 'AreaProfile.php' && $areaManagementArea === 'Area 1A') ? 'active' : '' ?>">
                Area 1A
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('area_profile_area_02')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/AreaManagement/AreaProfile.php?area=' . rawurlencode('Area 02'))) ?>"
                 class="link-dark rounded <?= ($current == 'AreaProfile.php' && $areaManagementArea === 'Area 02') ? 'active' : '' ?>">
                Area 02
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('area_profile_area_03')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/AreaManagement/AreaProfile.php?area=' . rawurlencode('Area 03'))) ?>"
                 class="link-dark rounded <?= ($current == 'AreaProfile.php' && $areaManagementArea === 'Area 03') ? 'active' : '' ?>">
                Area 03
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('area_profile_area_04')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/AreaManagement/AreaProfile.php?area=' . rawurlencode('Area 04'))) ?>"
                 class="link-dark rounded <?= ($current == 'AreaProfile.php' && $areaManagementArea === 'Area 04') ? 'active' : '' ?>">
                Area 04
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('area_profile_area_05')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/AreaManagement/AreaProfile.php?area=' . rawurlencode('Area 05'))) ?>"
                 class="link-dark rounded <?= ($current == 'AreaProfile.php' && $areaManagementArea === 'Area 05') ? 'active' : '' ?>">
                Area 05
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('area_profile_area_06')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/AreaManagement/AreaProfile.php?area=' . rawurlencode('Area 06'))) ?>"
                 class="link-dark rounded <?= ($current == 'AreaProfile.php' && $areaManagementArea === 'Area 06') ? 'active' : '' ?>">
                Area 06
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
      <?php endif; ?>
      <?php endif; ?>

      <?php if ($sbCan('certificate_issuance') || $sbHasAny($sbIdIssuanceKeys)): ?>
      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Barangay Issuance</li>
      <?php if ($sbCan('certificate_issuance')): ?>
      <li class="mb-2">
        <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/CertificateTracker.php?filter_document=__certificates__')) ?>"
           class="btn btn-toggle sidebar-direct-link sidebar-direct-link--certificate rounded <?= $isCertificateIssuanceSectionActive ? 'active' : '' ?>"
           style="<?= $isCertificateIssuanceSectionActive ? 'outline: none; box-shadow: none;' : '' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-file-circle-check"></i>
            <?= $sbRenderAttentionDot($sbModuleCount('certificate_issuance')) ?>
          </span>
          <span class="sidebar-button-label sidebar-button-label--certificate">Certificate Issuance</span>
          <?= $sbRenderAttentionBadge($sbCount('certificate_issuance')) ?>
        </a>
      </li>
      <?php endif; ?>
      <?php if ($sbHasAny($sbIdIssuanceKeys)): ?>
      <li class="mb-2">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isIdIssuanceActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#id-issuance-collapse"
                aria-expanded="<?= $isIdIssuanceActive ? 'true' : 'false' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-id-card"></i>
            <?= $sbRenderAttentionDot($sbModuleCount('id_issuance')) ?>
          </span>
          <span class="sidebar-button-label">ID Issuance</span>
        </button>

        <div class="collapse <?= $isIdIssuanceActive ? 'show' : '' ?>" id="id-issuance-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <?php if ($sbCan('id_issuance_tracker')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/CertificateTracker.php?entry=id_issuance')) ?>"
                 class="link-dark rounded sidebar-subnav-link <?= $isIdIssuanceTrackerActive ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Tracker</span>
                <?= $sbRenderAttentionBadge($sbCount('id_issuance_tracker')) ?>
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('id_issuance_manual')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/CertificateTracker.php?tab=manual&document=barangay_id')) ?>"
                 class="link-dark rounded sidebar-subnav-link <?= $isIdIssuanceManualActive ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Manual Issuance</span>
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
      <?php endif; ?>
      <?php endif; ?>

      <?php if ($sbCan('clearance_issuance') || $sbCan('business_monitoring')): ?>
      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Barangay Monitoring</li>
      <?php if ($sbCan('clearance_issuance')): ?>
      <li class="mb-2">
        <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/CertificateTracker.php?filter_document=__clearances__')) ?>"
           class="btn btn-toggle sidebar-direct-link rounded <?= $isClearanceIssuanceSectionActive ? 'active' : '' ?>"
           style="<?= $isClearanceIssuanceSectionActive ? 'outline: none; box-shadow: none;' : '' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-stamp"></i>
            <?= $sbRenderAttentionDot($sbModuleCount('clearance_issuance')) ?>
          </span>
          <span class="sidebar-button-label">Clearance Issuance</span>
          <?= $sbRenderAttentionBadge($sbCount('clearance_issuance')) ?>
        </a>
      </li>
      <?php endif; ?>
      <?php if ($sbCan('business_monitoring')): ?>
      <li class="mb-2">
        <a href="<?= htmlspecialchars(appUrl('Admin-End/BusinessMonitoring.php')) ?>"
           class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isBusinessMonitoringActive ? 'active' : '' ?>"
           style="<?= $isBusinessMonitoringActive ? 'outline: none; box-shadow: none;' : '' ?>">
          <i class="fas fa-store"></i> Business Monitoring
        </a>
      </li>
      <?php endif; ?>
      <?php endif; ?>

      <?php if ($sbHasAny($sbFinanceKeys)): ?>
      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Barangay Treasury</li>
      <li class="mb-2">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isFinanceActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#finance-collapse"
                aria-expanded="<?= $isFinanceActive ? 'true' : 'false' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-money-check-alt"></i>
            <?= $sbRenderAttentionDot($sbModuleCount('finance_transactions')) ?>
          </span>
          <span class="sidebar-button-label">Finance Transactions</span>
        </button>
        <div class="collapse <?= $isFinanceActive ? 'show' : '' ?>" id="finance-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <?php if ($sbCan('finance_payment_tracker')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/FinancePayments.php')) ?>"
                 class="link-dark rounded sidebar-subnav-link <?= $isFinanceTrackerActive ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Payment Tracker</span>
                <?= $sbRenderAttentionBadge($sbCount('finance_payment_tracker')) ?>
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('finance_create_transaction')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/FinancePayments.php')) ?>?section=create"
                 class="link-dark rounded sidebar-subnav-link <?= $isFinanceCreateActive ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Create Transaction</span>
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('finance_fee_management')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/FinancePayments.php')) ?>?section=fees"
                 class="link-dark rounded sidebar-subnav-link <?= $isFinanceFeesActive ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Fee Management</span>
                <?= $sbRenderAttentionBadge($sbCount('finance_fee_management')) ?>
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
      <?php endif; ?>

      <?php if ($sbHasAny($sbBlotterKeys)): ?>
      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">e-Blotter Management</li>
      <?php if ($sbCan('blotter_log_new_incident')): ?>
      <li class="mb-2">
        <a href="<?= htmlspecialchars(appUrl('Admin-End/Blotter/BlotterForm.php')) ?>"
           class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $current == 'BlotterForm.php' ? 'active' : '' ?>"
           style="<?= $current == 'BlotterForm.php' ? 'outline: none; box-shadow: none;' : '' ?>">
          <i class="fas fa-file-pen"></i> Log New Incident
        </a>
      </li>
      <?php endif; ?>
      <?php if ($sbCan('blotter_tracker') || $sbCan('blotter_review_queue')): ?>
      <li class="mb-1">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isBlotterActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#blotter-tools-collapse"
                aria-expanded="<?= $isBlotterActive ? 'true' : 'false' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-toolbox"></i>
            <?= $sbRenderAttentionDot($sbModuleCount('blotter_tools')) ?>
          </span>
          <span class="sidebar-button-label">e-Blotter Tools</span>
        </button>

        <div class="collapse <?= $isBlotterActive ? 'show' : '' ?>" id="blotter-tools-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <?php if ($sbCan('blotter_tracker')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Blotter/BlotterTracker.php')) ?>"
                 class="link-dark rounded sidebar-subnav-link <?= $current == 'BlotterTracker.php' ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Tracker</span>
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('blotter_review_queue')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Blotter/ReviewQueue.php')) ?>"
                 class="link-dark rounded sidebar-subnav-link <?= $current == 'ReviewQueue.php' ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Review Queue</span>
                <?= $sbRenderAttentionBadge($sbCount('blotter_review_queue')) ?>
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
      <?php endif; ?>
      <?php endif; ?>

      <?php if ($sbHasAny($sbComplaintKeys)): ?>
      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Complaints and Grievances</li>
      <?php if ($sbCan('complaint_log_new_incident')): ?>
      <li class="mb-2">
        <a href="<?= htmlspecialchars(appUrl('Admin-End/Complaints/ComplaintForm.php')) ?>"
           class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $current == 'ComplaintForm.php' ? 'active' : '' ?>"
           style="<?= $current == 'ComplaintForm.php' ? 'outline: none; box-shadow: none;' : '' ?>">
          <i class="fas fa-file-pen"></i> Log New Incident
        </a>
      </li>
      <?php endif; ?>
      <?php if ($sbCan('complaint_tracker')): ?>
      <li class="mb-1">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isComplaintActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#complaint-tools-collapse"
                aria-expanded="<?= $isComplaintActive ? 'true' : 'false' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-comments"></i>
            <?= $sbRenderAttentionDot($sbModuleCount('complaint_tools')) ?>
          </span>
          <span class="sidebar-button-label">Complaint Tools</span>
        </button>

        <div class="collapse <?= $isComplaintActive ? 'show' : '' ?>" id="complaint-tools-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Complaints/ComplaintTracker.php')) ?>"
                 class="link-dark rounded sidebar-subnav-link <?= $current == 'ComplaintTracker.php' ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Tracker</span>
                <?= $sbRenderAttentionBadge($sbCount('complaint_tracker')) ?>
              </a>
            </li>
          </ul>
        </div>
      </li>
      <?php endif; ?>
      <?php endif; ?>

      <?php if ($sbCan('announcements_tracker') || $sbCan('announcements_faq')): ?>
      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Content Management</li>
      <li class="mb-2">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isContentNavigatorActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#content-management-collapse"
                aria-expanded="<?= $isContentNavigatorActive ? 'true' : 'false' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-sitemap"></i>
            <?= $sbRenderAttentionDot($sbModuleCount('content_management')) ?>
          </span>
          <span class="sidebar-button-label">Content Management</span>
        </button>
        <div class="collapse <?= $isContentNavigatorActive ? 'show' : '' ?>" id="content-management-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Contents/ContentManagement.php')) ?>?module=requests&amp;requests_view=my_requests"
                 class="link-dark rounded sidebar-subnav-link <?= $isContentChangeRequestActive ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Content Change Request</span>
              </a>
            </li>
            <?php if ($sbCanReviewContent): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Contents/ContentManagement.php')) ?>?module=requests&amp;requests_view=review_queue"
                 class="link-dark rounded sidebar-subnav-link <?= ($current === 'ContentManagement.php' && $contentManagementModule === 'requests' && $contentRequestsView === 'review_queue') ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Review Queue</span>
                <?= $sbRenderAttentionBadge($sbCount('content_change_request')) ?>
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCanReviewContent): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Contents/ContentManagement.php')) ?>?module=requests&amp;requests_view=approved_history"
                 class="link-dark rounded sidebar-subnav-link <?= ($current === 'ContentManagement.php' && $contentManagementModule === 'requests' && $contentRequestsView === 'approved_history') ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Approved Version History</span>
              </a>
            </li>
            <?php endif; ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Contents/ContentManagement.php')) ?>?module=home"
                 class="link-dark rounded sidebar-subnav-link <?= ($current === 'ContentManagement.php' && $contentManagementModule === 'home') ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Home Page</span>
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Contents/ContentManagement.php')) ?>?module=government"
                 class="link-dark rounded sidebar-subnav-link <?= ($current === 'ContentManagement.php' && $contentManagementModule === 'government') ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Government</span>
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Contents/ContentManagement.php')) ?>?module=services"
                 class="link-dark rounded sidebar-subnav-link <?= ($current === 'ContentManagement.php' && $contentManagementModule === 'services') ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Services</span>
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Contents/ContentManagement.php')) ?>?module=faq"
                 class="link-dark rounded sidebar-subnav-link <?= (($current === 'ContentManagement.php' && $contentManagementModule === 'faq') || $isContentFaqCreateActive) ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">FAQ</span>
              </a>
            </li>
            <?php if ($sbCan('announcements_faq')): ?>
            <!-- <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Contents/CreateContent.php')) ?>?type=faq"
                 class="link-dark rounded sidebar-subnav-link <?= $isContentFaqCreateActive ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">FAQ Entries</span>
              </a>
            </li> -->
            <?php endif; ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Contents/ContentManagement.php')) ?>?module=contact"
                 class="link-dark rounded sidebar-subnav-link <?= ($current === 'ContentManagement.php' && $contentManagementModule === 'contact') ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Contact</span>
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Contents/ContentManagement.php')) ?>?module=login"
                 class="link-dark rounded sidebar-subnav-link <?= ($current === 'ContentManagement.php' && $contentManagementModule === 'login') ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Login</span>
              </a>
            </li>
          </ul>
        </div>
      </li>
      <?php endif; ?>
      <?php if ($sbHasAny(array_merge($sbAnnouncementKeys, $sbReportKeys))): ?>
      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">General Modules</li>
      <?php if ($sbHasAny($sbAnnouncementKeys)): ?>
      <li class="mb-2">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isContentMgmtActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#announcements-collapse"
                aria-expanded="<?= $isContentMgmtActive ? 'true' : 'false' ?>">
          <i class="fas fa-bullhorn"></i> Announcements
        </button>
        <div class="collapse <?= $isContentMgmtActive ? 'show' : '' ?>" id="announcements-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <?php if ($sbCan('announcements_page')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Contents/CreateContent.php')) ?>?type=page"
                 class="link-dark rounded <?= ($current === 'CreateContent.php' && $contentCreateType === 'page') ? 'active' : '' ?>">
                Page Announcement
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('announcements_delivery')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Contents/CreateContent.php')) ?>?type=delivery"
                 class="link-dark rounded <?= ($current === 'CreateContent.php' && $contentCreateType === 'delivery') ? 'active' : '' ?>">
                SMS and Email
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('announcements_tracker')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Contents/Contents.php')) ?>?tool=tracker#tracker-card"
                 class="link-dark rounded <?= ($current === 'Contents.php' && $contentToolView === 'tracker') ? 'active' : '' ?>">
                Tracker
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
      <?php endif; ?>
      <?php if ($sbHasAny($sbReportKeys)): ?>
      <li class="mb-2">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isReportActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#reports-collapse"
                aria-expanded="<?= $isReportActive ? 'true' : 'false' ?>">
          <i class="fas fa-chart-bar"></i> Reports
        </button>
        <div class="collapse <?= $isReportActive ? 'show' : '' ?>" id="reports-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <?php if ($sbCan('reports_certificate_issuance')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Reports/Reports.php')) ?>?module=certificate_issuance"
                 class="link-dark rounded <?= ($current === 'Reports.php' && $reportModule === 'certificate_issuance') ? 'active' : '' ?>">
                Certificate Issuance
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('reports_clearance_issuance')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Reports/Reports.php')) ?>?module=clearance_issuance"
                 class="link-dark rounded <?= ($current === 'Reports.php' && $reportModule === 'clearance_issuance') ? 'active' : '' ?>">
                Clearance Issuance
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('reports_financial')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Reports/Reports.php')) ?>?module=financial"
                 class="link-dark rounded <?= ($current === 'Reports.php' && $reportModule === 'financial') ? 'active' : '' ?>">
                Financial
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('reports_residents')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Reports/Reports.php')) ?>?module=residents"
                 class="link-dark rounded <?= ($current === 'Reports.php' && $reportModule === 'residents') ? 'active' : '' ?>">
                Residents
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('reports_blotter')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Reports/Reports.php')) ?>?module=blotter"
                 class="link-dark rounded <?= ($current === 'Reports.php' && $reportModule === 'blotter') ? 'active' : '' ?>">
                Blotter
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('reports_complaints')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Reports/Reports.php')) ?>?module=complaints"
                 class="link-dark rounded <?= ($current === 'Reports.php' && $reportModule === 'complaints') ? 'active' : '' ?>">
                Complaints
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
      <?php endif; ?>
      <?php endif; ?>

      <?php if ($isSuperAdminSidebar && $sbHasAny($sbAdminKeys)): ?>
      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Admin Management</li>
      <?php if ($sbCan('user_masterlist') || $sbCan('user_archive')): ?>
      <li class="mb-1">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isUserMgmtActive ? 'active' : '' ?> <?= $isUserMgmtActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#usermanagement-collapse"
                aria-expanded="<?= $isUserMgmtActive ? 'true' : 'false' ?>">
          <i class="fas fa-users-cog"></i> User Management
        </button>
        <div class="collapse <?= $isUserMgmtActive ? 'show' : '' ?>" id="usermanagement-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <?php if ($sbCan('user_masterlist')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/UserMasterlist.php')) ?>"
                 class="link-dark rounded <?= $current == 'UserMasterlist.php' ? 'active' : '' ?>">
                User Masterlist
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('user_archive')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/UserArchive.php')) ?>"
                 class="link-dark rounded <?= $current == 'UserArchive.php' ? 'active' : '' ?>">
                User Archive
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
      <?php endif; ?>
      <?php if ($isSuperAdminSidebar): ?>
      <li class="mb-1">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isPersonnelMgmtActive ? 'active' : '' ?> <?= $isPersonnelMgmtActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#personnelmanagement-collapse"
                aria-expanded="<?= $isPersonnelMgmtActive ? 'true' : 'false' ?>">
          <i class="fas fa-user-tie"></i> Personnel Management
        </button>
        <div class="collapse <?= $isPersonnelMgmtActive ? 'show' : '' ?>" id="personnelmanagement-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/PersonnelTracker.php')) ?>"
                 class="link-dark rounded <?= $current == 'PersonnelTracker.php' ? 'active' : '' ?>">
                Tracker
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/OfficialInvites.php')) ?>"
                 class="link-dark rounded <?= $current == 'OfficialInvites.php' ? 'active' : '' ?>">
                Account Invite
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/PersonnelRoleAccess.php')) ?>"
                 class="link-dark rounded <?= $current == 'PersonnelRoleAccess.php' ? 'active' : '' ?>">
                Role Based Permissions
              </a>
            </li>
          </ul>
        </div>
      </li>
      <?php endif; ?>
      <?php if ($sbCan('audit_logs')): ?>
      <li class="mb-1">
        <a href="<?= htmlspecialchars(appUrl('Admin-End/AuditLogs.php')) ?>"
           class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $current == 'AuditLogs.php' ? 'active' : '' ?>"
           style="<?= $current == 'AuditLogs.php' ? 'outline: none; box-shadow: none;' : '' ?>">
          <i class="fas fa-clipboard-list"></i> Audit Logs
        </a>
      </li>
      <?php endif; ?>
      <?php if ($sbCan('officials_management') || $sbCan('official_transition')): ?>
      <li class="mb-1 mt-3 text-muted small fw-semibold px-2">Barangay Official Management</li>
      <?php if ($sbCan('officials_management')): ?>
      <li class="mb-1">
        <a href="<?= htmlspecialchars(appUrl('Admin-End/OfficialsManagement.php')) ?>"
           class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $current == 'OfficialsManagement.php' ? 'active' : '' ?>"
           style="<?= $current == 'OfficialsManagement.php' ? 'outline: none; box-shadow: none;' : '' ?>">
          <i class="fas fa-user-shield"></i> Official Management
        </a>
      </li>
      <?php endif; ?>
      <?php if ($sbCan('official_transition')): ?>
      <li class="mb-1">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isOfficialTransitionActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#officialtransition-collapse"
                aria-expanded="<?= $isOfficialTransitionActive ? 'true' : 'false' ?>">
          <i class="fas fa-right-left"></i> Official Transition
        </button>
        <div class="collapse <?= $isOfficialTransitionActive ? 'show' : '' ?>" id="officialtransition-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/OfficialTransitions.php?tool=current_term')) ?>"
                 class="link-dark rounded <?= $current == 'OfficialTransitions.php' && $officialTransitionTool === 'current_term' ? 'active' : '' ?>">
                Current Term
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/OfficialTransitions.php?tool=create_new_term')) ?>"
                 class="link-dark rounded <?= $current == 'OfficialTransitions.php' && $officialTransitionTool === 'create_new_term' ? 'active' : '' ?>">
                Create New Term
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/OfficialTransitions.php?tool=settings')) ?>"
                 class="link-dark rounded <?= $current == 'OfficialTransitions.php' && $officialTransitionTool === 'settings' ? 'active' : '' ?>">
                Settings
              </a>
            </li>
          </ul>
        </div>
      </li>
      <?php endif; ?>
      <?php endif; ?>
      <?php endif; ?>

    </ul>

    <hr>

    <div class="sidebar-actions">
      <div class="dropdown mb-2 w-100">
        <a href="#" class="d-flex align-items-center link-dark text-decoration-none dropdown-toggle w-100"
           data-bs-toggle="dropdown">
          <img src="<?= htmlspecialchars($adminProfileImageUrl, ENT_QUOTES, 'UTF-8') ?>"
               width="40"
               height="40"
               class="rounded-circle me-2"
               alt="<?= htmlspecialchars($adminDisplayName, ENT_QUOTES, 'UTF-8') ?>"
               style="object-fit: cover;"
               onerror="this.onerror=null;this.src='<?= htmlspecialchars(appUrl('Images/Profile-Placeholder.png'), ENT_QUOTES, 'UTF-8') ?>';">
          <div class="flex-grow-1" style="min-width: 0;">
            <span class="d-block fw-bold text-truncate mb-0"><?= htmlspecialchars($adminDisplayName) ?></span>
            <small class="d-block text-muted text-truncate"><?= htmlspecialchars($adminPosition) ?></small>
          </div>
        </a>
        <ul class="dropdown-menu text-small shadow">
          <li><a class="dropdown-item <?= $isAdminProfileActive ? 'active' : '' ?>" href="<?= htmlspecialchars(appUrl('Admin-End/admin_profile.php')) ?>">Profile</a></li>
          <li><a class="dropdown-item" href="<?= htmlspecialchars(appUrl('PhpFiles/Login/logout.php')) ?>">Sign out</a></li>
        </ul>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    const burgerBtn = document.getElementById("btn-admin-burger");
    const sidebar = document.getElementById("dashboard-sidebar");
    if (!burgerBtn || !sidebar) return;

    const portraitMq = window.matchMedia("(orientation: portrait) and (max-width: 1024px)");

    const syncMode = () => {
      if (!portraitMq.matches) {
        sidebar.classList.remove("show");
      }
    };

    burgerBtn.addEventListener("click", () => {
      if (!portraitMq.matches) return;
      sidebar.classList.toggle("show");
    });

    sidebar.querySelectorAll("a").forEach((link) => {
      link.addEventListener("click", () => {
        if (!portraitMq.matches) return;
        if (String(link.getAttribute("data-bs-toggle") || "").toLowerCase() === "dropdown") {
          return;
        }
        sidebar.classList.remove("show");
      });
    });

    if (typeof portraitMq.addEventListener === "function") {
      portraitMq.addEventListener("change", syncMode);
    } else if (typeof portraitMq.addListener === "function") {
      portraitMq.addListener(syncMode);
    }

    syncMode();
  })();
</script>
