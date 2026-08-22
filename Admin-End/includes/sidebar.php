<?php
$current = basename($_SERVER['PHP_SELF']);

// Group pages by section
$residentMgmtPages = ['ResidentTracker.php', 'ResidentMasterlist.php', 'ResidentArchive.php', 'EditRequests.php', 'SectorMembershipVerification.php', 'AddResident.php'];
$householdProfilingPages = ['HouseholdProfiling.php', 'HeadOfTheFamilyVerification.php', 'HouseholdMemberVerification.php'];
$appointmentPages = ['AppointmentTracker.php', 'AppointmentRequestVerification.php', 'WalkInAppointmentForm.php'];
$certPages = ['CertificateTracker.php'];
$financePages = ['FinancePayments.php'];
$blotterPages = ['BlotterTracker.php', 'ReviewQueue.php'];
$complaintPages = ['ComplaintTracker.php'];
$contentMgmtPages = ['Contents.php', 'CreateContent.php', 'CreateNews.php'];
$areaManagementPages = ['BarangayStatistics.php', 'AreaStatistics.php', 'AreaProfile.php'];
$reportPages = ['Reports.php', 'ReportsLanding.php', 'ReportCategoryLanding.php', 'ReportSignatorySettings.php'];
$userMgmtPages = ['UserMasterlist.php', 'UserArchive.php'];
$personnelMgmtPages = ['PersonnelTracker.php', 'OfficialInvites.php', 'PersonnelRoleAccess.php'];
$adminRecordsPages = ['AdminManagement.php'];
$adminMgmtPages = ['UserMasterlist.php', 'UserArchive.php', 'AdminManagement.php', 'PersonnelTracker.php', 'OfficialInvites.php', 'PersonnelRoleAccess.php', 'AuditLogs.php', 'WebsiteSettings.php'];
$barangayOfficialMgmtPages = ['OfficialsManagement.php', 'OfficialTransitions.php'];
$officialTransitionPages = ['OfficialTransitions.php'];

$officialTransitionTool = 'current_term';
$officialTransitionPanel = strtolower(trim((string)($_GET['panel'] ?? '')));

$appointmentTool = strtolower(trim((string)($_GET['tool'] ?? 'tracker')));
if (!in_array($appointmentTool, ['tracker', 'settings', 'schedule'], true)) {
    $appointmentTool = 'tracker';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('appUrl')) {
    require_once __DIR__ . '/../../PhpFiles/General/security.php';
}
require_once __DIR__ . '/../../PhpFiles/General/adminModulePermissions.php';
require_once __DIR__ . '/../../PhpFiles/General/appointmentCouncilMembers.php';
require_once __DIR__ . '/../../PhpFiles/General/siteContent.php';
$sbAttentionHelperPath = __DIR__ . '/../../PhpFiles/General/adminSidebarAttention.php';
if (file_exists($sbAttentionHelperPath)) {
    require_once $sbAttentionHelperPath;
}

$sbDeferDb = defined('ADMIN_SIDEBAR_DEFER_DB') && ADMIN_SIDEBAR_DEFER_DB === true;
if (
    !$sbDeferDb
    && (!isset($conn) || !($conn instanceof mysqli))
    && file_exists(__DIR__ . '/../../PhpFiles/General/connection.php')
) {
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
$isAppointmentScheduleActive = $current === 'AppointmentTracker.php' && $appointmentTool === 'schedule';
$isFinanceActive = in_array($current, $financePages);
$isBlotterActive = in_array($current, $blotterPages);
$isComplaintActive = in_array($current, $complaintPages);
$isContentMgmtActive = in_array($current, $contentMgmtPages);
$isAreaManagementActive = in_array($current, $areaManagementPages);
$isUserMgmtActive = in_array($current, $userMgmtPages);
$isAdminRecordsActive = in_array($current, $adminRecordsPages);
$isAdminMgmtActive = in_array($current, $adminMgmtPages);
$isWebsiteSettingsActive = ($current === 'WebsiteSettings.php');
$sidebarCertificateTab = strtolower(trim((string)($_GET['tab'] ?? '')));
$sidebarFeeScope = strtolower(trim((string)($_GET['fee_scope'] ?? '')));
$isCertificateFeeSettingsRoute = $current === 'CertificateTracker.php'
    && $sidebarCertificateTab === 'fees'
    && $sidebarFeeScope === 'issuance';
$isMonitoringFeeSettingsRoute = $current === 'CertificateTracker.php'
    && $sidebarCertificateTab === 'fees'
    && $sidebarFeeScope === 'monitoring';
$isCertificateIssuanceSettingsActive = in_array($current, [
    'CertificateIssuanceSettings.php',
    'IssuanceGeneralSettings.php',
    'IssuanceCertificateSettings.php',
    'IssuanceNotificationSettings.php',
    'IndigencyRecipientSettings.php',
    'IssuanceFeeSettings.php',
], true) || $isCertificateFeeSettingsRoute;
$isCertificateIssuanceSettingsSubtabActive = $current === 'CertificateIssuanceSettings.php';
$isBusinessMonitoringSettingsActive = in_array($current, ['BusinessMonitoringSettings.php', 'ClearanceDocumentSettings.php', 'ClearanceGeneralSettings.php', 'ClearanceTypeSettings.php', 'ClearanceNotificationSettings.php'], true) || $isMonitoringFeeSettingsRoute;
$isBusinessMonitoringSettingsSubtabActive = $current === 'BusinessMonitoringSettings.php';
$isBarangayIdSettingsActive = ($current === 'BarangayIdSettings.php');
$isPersonnelMgmtActive = in_array($current, $personnelMgmtPages);
$isBarangayOfficialMgmtActive = in_array($current, $barangayOfficialMgmtPages);
$isOfficialTransitionActive = in_array($current, $officialTransitionPages);
$isReportActive = in_array($current, $reportPages);
$isStatisticsActive = ($current === 'AdminDashboard.php');
$isAdminProfileActive = ($current === 'admin_profile.php');
$areaManagementTab = strtolower(trim((string)($_GET['tab'] ?? 'summary')));
$areaManagementArea = trim((string)($_GET['area'] ?? ''));
$isSuperAdminSidebar = (strtolower(trim((string)($_SESSION['role'] ?? ''))) === 'superadmin');
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
$isIdIssuanceActive = $isIdIssuanceActive || $isBarangayIdSettingsActive;
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
    || $isClearanceTricyclePermitActive
    || $isClearanceElectricPermitActive
    || $isClearanceWaterPermitActive
    || $isClearanceResidentialPermitActive
    || $isBusinessMonitoringSettingsActive;
$isClearanceIssuanceTrackerActive = $isClearanceIssuanceSectionActive
    && !$isBusinessMonitoringSettingsActive;
$isEstablishmentMonitoringActive = $current === 'EstablishmentMonitoring.php';
$isBusinessMonitoringTrackerActive = $current === 'CertificateTracker.php'
    && !$isIdIssuanceActive
    && (
        in_array($certificateFilterDocumentToken, ['__business__', 'business', 'business_monitoring', 'businessclearance'], true)
        || strcasecmp($certificateFilterDocument, 'Barangay Clearance for Business Permit') === 0
    );
$isBusinessMonitoringActive = $current === 'BusinessMonitoring.php' || $isBusinessMonitoringTrackerActive;
$isCertificateIssuanceSectionActive = $current === 'CertificateTracker.php'
    && !$isIdIssuanceActive
    && !$isClearanceIssuanceSectionActive
    && !$isEstablishmentMonitoringActive
    && !$isBusinessMonitoringActive;
$isCertificateIssuanceSectionActive = $isCertificateIssuanceSectionActive || $isCertificateIssuanceSettingsActive;
$isCertificateIssuanceTrackerActive = $isCertificateIssuanceSectionActive && !$isCertificateIssuanceSettingsActive;
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
$isFinanceSettingsActive = $current === 'FinancePayments.php' && $financeSection === 'settings';
$isFinanceTrackerActive = $current === 'FinancePayments.php' && !in_array($financeSection, ['fees', 'create', 'settings'], true);
$contentCreateType = strtolower(trim((string)($_GET['type'] ?? 'page')));
if (!in_array($contentCreateType, ['page', 'news', 'delivery', 'faq'], true)) {
    $contentCreateType = 'page';
}
$sidebarContentTypeFilter = strtolower(trim((string)($_GET['type_filter'] ?? 'all')));
if (!in_array($sidebarContentTypeFilter, ['all', 'page', 'news', 'delivery', 'faq'], true)) {
    $sidebarContentTypeFilter = 'all';
}
$contentToolView = strtolower(trim((string)($_GET['tool'] ?? 'tracker')));
if ($contentToolView !== 'tracker') {
    $contentToolView = 'tracker';
}
$contentManagementModule = strtolower(trim((string)($_GET['module'] ?? 'overview')));
if (!in_array($contentManagementModule, ['overview', 'requests', 'announcements', 'home', 'government', 'services', 'faq', 'contact', 'login'], true)) {
    $contentManagementModule = 'overview';
}
$isContentCreateSectionActive = in_array($current, ['CreateContent.php', 'CreateNews.php'], true);
$isContentToolsSectionActive = $current === 'Contents.php' && $sidebarContentTypeFilter !== 'news';
$isContentFaqCreateActive = $current === 'CreateContent.php' && $contentCreateType === 'faq';
$isContentNewsCreateActive = $current === 'CreateNews.php';
$isNewsManagementActive = ($current === 'Contents.php' && $sidebarContentTypeFilter === 'news') || $isContentNewsCreateActive;

$sbAllowedPermissions = [];
if ($sbDeferDb && isset($allowedPermissions) && is_array($allowedPermissions)) {
    // The admin guard already resolved these for the current request. Reuse
    // them so lightweight pages do not repeat the permission queries.
    $sbAllowedPermissions = $allowedPermissions;
} elseif (!$sbDeferDb && isset($conn) && $conn instanceof mysqli && !empty($_SESSION['user_id'])) {
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

$sbSidebarUserId = trim((string)($_SESSION['user_id'] ?? ''));
$sbSidebarRole = trim((string)($_SESSION['role'] ?? ''));
$sbSidebarCacheScope = md5($sbSidebarUserId . '|' . $sbSidebarRole);
$sbCurrentOfficialAccount = (isset($currentOfficialAccount) && is_array($currentOfficialAccount))
    ? $currentOfficialAccount
    : null;
$sbAppointmentAccess = [
    'can_access_tracker' => true,
    'can_access_settings' => true,
];
if (!$sbDeferDb && isset($conn) && $conn instanceof mysqli && $sbSidebarUserId !== '') {
    $sbAppointmentAccessCacheKey = 'admin_sidebar_appointment_scope:' . $sbSidebarCacheScope;
    $sbCachedAppointmentAccess = function_exists('amp_session_cache_get')
        ? amp_session_cache_get($sbAppointmentAccessCacheKey, 300)
        : null;

    if (is_array($sbCachedAppointmentAccess)) {
        $sbAppointmentAccess = array_replace($sbAppointmentAccess, $sbCachedAppointmentAccess);
    } else {
        $sbAppointmentAccess = apcm_get_appointment_admin_scope($conn, $sbSidebarUserId, $sbSidebarRole);
        if (function_exists('amp_session_cache_put')) {
            amp_session_cache_put($sbAppointmentAccessCacheKey, $sbAppointmentAccess);
        }
    }
}
$sbCanAccessAppointmentTracker = $sbCan('appointments') && !empty($sbAppointmentAccess['can_access_tracker']);
$sbCanAccessAppointmentSettings = $sbCanAccessAppointmentTracker && !empty($sbAppointmentAccess['can_access_settings']);
$sbCanAccessAppointmentSchedule = $sbCanAccessAppointmentTracker && !empty($sbAppointmentAccess['can_access_schedule']);
$sbCanReviewContent = strtolower($sbSidebarRole) === 'superadmin';
if (!$sbCanReviewContent && $sbCurrentOfficialAccount) {
    $sbCanReviewContent = strtolower(trim((string)($sbCurrentOfficialAccount['position_access'] ?? ''))) === 'barangay secretary';
} elseif (!$sbDeferDb && !$sbCanReviewContent && isset($conn) && $conn instanceof mysqli && function_exists('cms_content_can_review')) {
    $sbCanReviewContentCacheKey = 'admin_sidebar_content_review:' . $sbSidebarCacheScope;
    $sbCachedCanReview = function_exists('amp_session_cache_get')
        ? amp_session_cache_get($sbCanReviewContentCacheKey, 300)
        : null;

    if (is_bool($sbCachedCanReview)) {
        $sbCanReviewContent = $sbCachedCanReview;
    } else {
        $sbCanReviewContent = cms_content_can_review($conn, $sbSidebarUserId, $sbSidebarRole);
        if (function_exists('amp_session_cache_put')) {
            amp_session_cache_put($sbCanReviewContentCacheKey, $sbCanReviewContent);
        }
    }
}
$sbCanAccessContentNavigator = $sbCan('announcements_tracker');

// Highlight a collapsible section's main navigation only when the current
// route has no active subtab that is actually available to this user.
$sbMainNavActiveWithoutSubtab = static function (bool $sectionActive, array $availableActiveSubtabs): bool {
    return $sectionActive && !in_array(true, $availableActiveSubtabs, true);
};

$isContentNavigatorActive = $current === 'ContentManagement.php'
    || ($isContentFaqCreateActive && $sbCanAccessContentNavigator);
$isAnnouncementsActive = $isContentMgmtActive
    && !$isNewsManagementActive
    && !$isContentNavigatorActive;

$isAppointmentMainNavActive = $sbMainNavActiveWithoutSubtab($isAppointmentActive, [
    $sbCanAccessAppointmentTracker && $isAppointmentTrackerActive,
    $sbCanAccessAppointmentSettings && $isAppointmentSettingsActive,
    $sbCanAccessAppointmentSchedule && $isAppointmentScheduleActive,
]);
$isResidentMainNavActive = $sbMainNavActiveWithoutSubtab($isResidentMgmtActive, [
    $sbCan('resident_masterlist') && $current === 'ResidentTracker.php',
    $sbCan('resident_masterlist') && $current === 'ResidentMasterlist.php',
    $sbCan('resident_edit_requests') && $current === 'EditRequests.php',
    $sbCan('resident_archive') && $current === 'ResidentArchive.php',
    $sbCan('resident_sector_membership_verification') && $current === 'SectorMembershipVerification.php',
]);
$isHouseholdMainNavActive = $sbMainNavActiveWithoutSubtab($isHouseholdProfilingActive, [
    $sbCan('household_profiling_main') && $current === 'HouseholdProfiling.php',
    $sbCan('head_of_family_verification') && $current === 'HeadOfTheFamilyVerification.php',
    $sbCan('household_member_verification') && $current === 'HouseholdMemberVerification.php',
]);
$isAreaManagementMainNavActive = $sbMainNavActiveWithoutSubtab($isAreaManagementActive, [
    $sbCan('dashboard') && $current === 'BarangayStatistics.php',
    $sbCan('area_statistics_summary') && $current === 'AreaStatistics.php' && $areaManagementTab === 'summary',
    $sbCan('area_profile_area_01') && $current === 'AreaProfile.php' && $areaManagementArea === 'Area 01',
    $sbCan('area_profile_area_1a') && $current === 'AreaProfile.php' && $areaManagementArea === 'Area 1A',
    $sbCan('area_profile_area_02') && $current === 'AreaProfile.php' && $areaManagementArea === 'Area 02',
    $sbCan('area_profile_area_03') && $current === 'AreaProfile.php' && $areaManagementArea === 'Area 03',
    $sbCan('area_profile_area_04') && $current === 'AreaProfile.php' && $areaManagementArea === 'Area 04',
    $sbCan('area_profile_area_05') && $current === 'AreaProfile.php' && $areaManagementArea === 'Area 05',
    $sbCan('area_profile_area_06') && $current === 'AreaProfile.php' && $areaManagementArea === 'Area 06',
]);
$isCertificateIssuanceMainNavActive = $sbMainNavActiveWithoutSubtab($isCertificateIssuanceSectionActive, [
    $sbCan('certificate_issuance') && $isCertificateIssuanceTrackerActive,
    $sbCan('certificate_issuance') && $isCertificateIssuanceSettingsSubtabActive,
]);
$isIdIssuanceMainNavActive = $sbMainNavActiveWithoutSubtab($isIdIssuanceActive, [
    $sbCan('id_issuance_tracker') && $isIdIssuanceTrackerActive,
    $sbCan('id_issuance_manual') && $isIdIssuanceManualActive,
    $sbCan('id_issuance_tracker') && $isBarangayIdSettingsActive,
]);
$isClearanceIssuanceMainNavActive = $sbMainNavActiveWithoutSubtab($isClearanceIssuanceSectionActive, [
    $sbCan('clearance_issuance') && $isClearanceIssuanceTrackerActive,
    $sbCan('clearance_issuance') && $isBusinessMonitoringSettingsSubtabActive,
]);
$isFinanceMainNavActive = $sbMainNavActiveWithoutSubtab($isFinanceActive, [
    $sbCan('finance_payment_tracker') && $isFinanceTrackerActive,
    $sbCan('finance_create_transaction') && $isFinanceCreateActive,
    $sbCan('finance_fee_management') && $isFinanceFeesActive,
]);
$isBlotterMainNavActive = $sbMainNavActiveWithoutSubtab($isBlotterActive, [
    $sbCan('blotter_tracker') && $current === 'BlotterTracker.php',
    $sbCan('blotter_review_queue') && $current === 'ReviewQueue.php',
]);
$isComplaintMainNavActive = $sbMainNavActiveWithoutSubtab($isComplaintActive, [
    $sbCan('complaint_tracker') && $current === 'ComplaintTracker.php',
]);
$isContentNavigatorMainNavActive = $sbMainNavActiveWithoutSubtab($isContentNavigatorActive, [
    $sbCanAccessContentNavigator && $current === 'ContentManagement.php' && $contentManagementModule === 'overview',
    $sbCanAccessContentNavigator && $current === 'ContentManagement.php' && $contentManagementModule === 'requests',
]);
$isAnnouncementsMainNavActive = $sbMainNavActiveWithoutSubtab($isAnnouncementsActive, [
    $sbCan('announcements_page') && $current === 'CreateContent.php' && $contentCreateType === 'page',
    $sbCan('announcements_delivery') && $current === 'CreateContent.php' && $contentCreateType === 'delivery',
    $sbCan('announcements_tracker') && $current === 'Contents.php' && $contentToolView === 'tracker',
]);
$isUserManagementMainNavActive = $sbMainNavActiveWithoutSubtab($isUserMgmtActive, [
    $sbCan('user_masterlist') && $current === 'UserMasterlist.php',
    $sbCan('user_archive') && $current === 'UserArchive.php',
]);
$isPersonnelManagementMainNavActive = $sbMainNavActiveWithoutSubtab($isPersonnelMgmtActive, [
    $sbCan('officials_management') && $current === 'PersonnelTracker.php',
    $sbCan('personnel_invite') && $current === 'OfficialInvites.php',
    $sbCan('officials_management') && $current === 'PersonnelRoleAccess.php',
]);
$isOfficialTransitionMainNavActive = $sbMainNavActiveWithoutSubtab($isOfficialTransitionActive, [
    $sbCan('official_transition') && $current === 'OfficialTransitions.php' && $officialTransitionTool === 'current_term',
]);

$sbAttentionCounts = function_exists('sbatt_default_counts') ? sbatt_default_counts() : [];
if ($sbDeferDb) {
    // Lightweight pages may reuse warm attention data, but must never refresh
    // it synchronously and delay the page that the user is opening.
    $sbCachedAttention = $_SESSION['admin_sidebar_attention_counts_v1'] ?? null;
    $sbCachedAttentionCounts = null;
    if (
        is_array($sbCachedAttention)
        && (int)($sbCachedAttention['expires_at'] ?? 0) >= time()
        && is_array($sbCachedAttention['counts'] ?? null)
    ) {
        $sbCachedAttentionCounts = $sbCachedAttention['counts'];
    } elseif (function_exists('sbatt_shared_cache_get')) {
        $sbCachedAttentionCounts = sbatt_shared_cache_get('admin_sidebar_attention_counts_v1', 300);
    }
    if (is_array($sbCachedAttentionCounts)) {
        $sbAttentionCounts = array_merge($sbAttentionCounts, $sbCachedAttentionCounts);
    }
} elseif (isset($conn) && $conn instanceof mysqli && function_exists('sbatt_get_counts')) {
    $sbAttentionCounts = sbatt_get_counts($conn, 300);
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
    'dashboard',
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
    'finance_payment_settings',
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
$sbNewsKeys = [
    'news_management',
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
    'admin_management',
    'officials_management',
    'official_records_management',
    'personnel_invite',
    'official_transition',
    'audit_logs',
    'website_settings',
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
    'content_management' => ($sbCanAccessContentNavigator && $sbCanReviewContent) ? $sbCount('content_change_request') : 0,
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

if ($sbSidebarUserId !== '') {
    $sbProfileCacheKey = 'admin_sidebar_profile:' . md5($sbSidebarUserId);
    $sbCachedProfile = function_exists('amp_session_cache_get')
        ? amp_session_cache_get($sbProfileCacheKey, 300)
        : null;

    if (is_array($sbCachedProfile)) {
        $adminDisplayName = trim((string)($sbCachedProfile['display_name'] ?? $adminDisplayName)) ?: $adminDisplayName;
        $adminPosition = trim((string)($sbCachedProfile['position'] ?? $adminPosition)) ?: $adminPosition;
        $adminProfileImageUrl = trim((string)($sbCachedProfile['image_url'] ?? $adminProfileImageUrl)) ?: $adminProfileImageUrl;
    } elseif (!$sbDeferDb && isset($conn) && $conn instanceof mysqli) {
        $sbSchemaCacheKey = 'admin_sidebar_officialinfo_schema_v1';
        $sbOfficialInfoSchema = function_exists('amp_session_cache_get')
            ? amp_session_cache_get($sbSchemaCacheKey, 1800)
            : null;

        if (!is_array($sbOfficialInfoSchema)) {
            $sbOfficialInfoSchema = [
                'has_position_access' => false,
                'has_area_number' => false,
            ];

            $colRes = $conn->query("SHOW COLUMNS FROM officialinformationtbl LIKE 'position_access'");
            if ($colRes instanceof mysqli_result) {
                $sbOfficialInfoSchema['has_position_access'] = $colRes->num_rows > 0;
                $colRes->free();
            }

            $areaColRes = $conn->query("SHOW COLUMNS FROM officialinformationtbl LIKE 'area_number'");
            if ($areaColRes instanceof mysqli_result) {
                $sbOfficialInfoSchema['has_area_number'] = $areaColRes->num_rows > 0;
                $areaColRes->free();
            }

            if (function_exists('amp_session_cache_put')) {
                amp_session_cache_put($sbSchemaCacheKey, $sbOfficialInfoSchema);
            }
        }

        $hasPositionAccess = !empty($sbOfficialInfoSchema['has_position_access']);
        $hasAreaNumber = !empty($sbOfficialInfoSchema['has_area_number']);
        $selectPosition = $hasPositionAccess ? "position_access" : "NULL AS position_access";
        $selectAreaNumber = $hasAreaNumber ? "area_number" : "NULL AS area_number";

        $stmtInfo = $conn->prepare("
            SELECT firstname, middlename, lastname, suffix, role_access, {$selectPosition}, department, {$selectAreaNumber}
            FROM officialinformationtbl
            WHERE user_id = ?
            LIMIT 1
        ");
        if ($stmtInfo) {
            $stmtInfo->bind_param("s", $sbSidebarUserId);
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
            $stmtAvatar->bind_param("s", $sbSidebarUserId);
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

        if (function_exists('amp_session_cache_put')) {
            amp_session_cache_put($sbProfileCacheKey, [
                'display_name' => $adminDisplayName,
                'position' => $adminPosition,
                'image_url' => $adminProfileImageUrl,
            ]);
        }
    }
}
?>

<script src="<?= htmlspecialchars(appUrl('JS-Script-Files/modalHandler.js?v=20260812-01'), ENT_QUOTES, 'UTF-8') ?>"></script>
<link rel="stylesheet" href="<?= htmlspecialchars(appUrl('CSS-Styles/Admin-End-CSS/TablePagination.css?v=20260805-admin-table-unification'), ENT_QUOTES, 'UTF-8') ?>">
<script defer src="<?= htmlspecialchars(appUrl('JS-Script-Files/Admin-End/tablePagination.js?v=20260805-admin-table-unification'), ENT_QUOTES, 'UTF-8') ?>"></script>

<style>
  :root {
    --admin-sidebar-expanded: 284px;
    --admin-sidebar-collapsed: 92px;
  }

  #dashboard-sidebar {
    width: var(--admin-sidebar-expanded);
    min-width: var(--admin-sidebar-expanded);
    transition: width 0.24s ease, min-width 0.24s ease, padding 0.24s ease;
    z-index: 30;
  }

  #dashboard-sidebar .sidebar-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding-bottom: 1rem;
    margin-bottom: 1rem;
    border-bottom: 1px solid #d7dde5;
  }

  #dashboard-sidebar .sidebar-brand-link {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 0.75rem;
  }

  #dashboard-sidebar .sidebar-brand-logo {
    display: block;
    width: 40px;
    height: 40px;
    max-width: 40px;
    max-height: 40px;
    flex: 0 0 40px;
    border-radius: 50%;
    object-fit: cover;
  }

  #dashboard-sidebar .sidebar-brand-title {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  #dashboard-sidebar .sidebar-edge-toggle {
    position: absolute;
    top: 50%;
    right: 0;
    z-index: 40;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    width: 50px;
    height: 50px;
    padding: 0;
    border-radius: 999px;
    border: 1px solid rgba(232, 190, 141, 0.82) !important;
    background: linear-gradient(180deg, #fff9f2 0%, #ffe8c7 100%);
    color: #9d5a13;
    box-shadow:
      0 14px 28px rgba(15, 23, 42, 0.12),
      0 0 0 6px rgba(255, 247, 237, 0.94),
      inset 0 1px 0 rgba(255, 255, 255, 0.96);
    transform: translate(50%, -50%);
    transition:
      transform 0.22s ease,
      background 0.22s ease,
      color 0.22s ease,
      box-shadow 0.22s ease,
      border-color 0.22s ease;
  }

  #dashboard-sidebar .sidebar-edge-toggle:hover,
  #dashboard-sidebar .sidebar-edge-toggle:focus-visible {
    transform: translate(50%, -50%) scale(1.04);
    background: linear-gradient(180deg, #fff7ee 0%, #ffdfb2 100%);
    color: #7f4300;
    border-color: rgba(223, 155, 82, 0.88) !important;
    box-shadow:
      0 18px 34px rgba(15, 23, 42, 0.16),
      0 6px 18px rgba(254, 153, 60, 0.14),
      0 0 0 7px rgba(255, 247, 237, 0.98),
      inset 0 1px 0 rgba(255, 255, 255, 0.98);
  }

  #dashboard-sidebar .sidebar-edge-toggle:focus-visible {
    outline: 3px solid rgba(254, 153, 60, 0.22);
    outline-offset: 3px;
  }

  #dashboard-sidebar .sidebar-edge-toggle i {
    position: relative;
    z-index: 1;
    font-size: 1rem;
    transition: transform 0.25s ease;
  }

  #dashboard-sidebar .btn-toggle,
  #dashboard-sidebar .sidebar-direct-link {
    width: 100%;
  }

  #dashboard-sidebar .sidebar-button-label,
  #dashboard-sidebar .sidebar-subnav-text {
    min-width: 0;
  }

  #dashboard-sidebar .sidebar-button-label {
    white-space: nowrap;
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

  #dashboard-sidebar .sidebar-direct-link > .sidebar-attention-badge {
    display: none;
  }

  #dashboard-sidebar .sidebar-icon-wrap > .sidebar-attention-badge {
    display: none;
  }

  #dashboard-sidebar .sidebar-subnav-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
  }

  #dashboard-sidebar .sidebar-direct-link {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  #dashboard-sidebar .sidebar-direct-link.active,
  #dashboard-sidebar .sidebar-direct-link.active:hover,
  #dashboard-sidebar .sidebar-direct-link.active:focus {
    background-color: #de710c !important;
    color: #ffffff !important;
    font-weight: 600;
  }

  #dashboard-sidebar .sidebar-direct-link.active i,
  #dashboard-sidebar .sidebar-direct-link.active .sidebar-button-label {
    color: #ffffff !important;
  }

  #dashboard-sidebar .sidebar-profile-trigger {
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }

  body.admin-sidebar-collapsed #dashboard-sidebar {
    width: var(--admin-sidebar-collapsed);
    min-width: var(--admin-sidebar-collapsed);
    padding-left: 0.75rem !important;
    padding-right: 0.75rem !important;
  }

  body.admin-sidebar-collapsed #dashboard-sidebar .sidebar-header {
    justify-content: center;
    padding-bottom: 0.9rem;
    margin-bottom: 1rem;
  }

  body.admin-sidebar-collapsed #dashboard-sidebar .sidebar-brand-link,
  body.admin-sidebar-collapsed #dashboard-sidebar .btn-toggle,
  body.admin-sidebar-collapsed #dashboard-sidebar .sidebar-direct-link,
  body.admin-sidebar-collapsed #dashboard-sidebar .sidebar-profile-trigger {
    justify-content: center;
  }

  body.admin-sidebar-collapsed #dashboard-sidebar .sidebar-profile-trigger img {
    margin-right: 0 !important;
  }

  body.admin-sidebar-collapsed #dashboard-sidebar .sidebar-brand-link,
  body.admin-sidebar-collapsed #dashboard-sidebar .btn-toggle,
  body.admin-sidebar-collapsed #dashboard-sidebar .sidebar-direct-link {
    gap: 0;
  }

  body.admin-sidebar-collapsed #dashboard-sidebar .sidebar-brand-title,
  body.admin-sidebar-collapsed #dashboard-sidebar .sidebar-button-label,
  body.admin-sidebar-collapsed #dashboard-sidebar .sidebar-button-label--certificate,
  body.admin-sidebar-collapsed #dashboard-sidebar .sidebar-subnav-text,
  body.admin-sidebar-collapsed #dashboard-sidebar .sidebar-profile-copy,
  body.admin-sidebar-collapsed #dashboard-sidebar li.text-muted.small.fw-semibold,
  body.admin-sidebar-collapsed #dashboard-sidebar hr,
  body.admin-sidebar-collapsed #dashboard-sidebar .sidebar-direct-link > .sidebar-attention-badge,
  body.admin-sidebar-collapsed #dashboard-sidebar .sidebar-attention-dot,
  body.admin-sidebar-collapsed #dashboard-sidebar .dropdown-toggle::after {
    display: none !important;
  }

  body.admin-sidebar-collapsed #dashboard-sidebar .sidebar-edge-toggle i {
    transform: rotate(180deg);
  }

  body.admin-sidebar-collapsed #dashboard-sidebar .sidebar-edge-toggle {
    width: 46px;
    height: 46px;
  }

  body.admin-sidebar-collapsed #dashboard-sidebar .sidebar-brand-logo {
    width: 40px;
    height: 40px;
    flex-basis: 40px;
  }

  body.admin-sidebar-collapsed #dashboard-sidebar .sidebar-icon-wrap {
    width: 2rem;
    min-width: 2rem;
    height: 2rem;
  }

  body.admin-sidebar-collapsed #dashboard-sidebar .collapse {
    display: none !important;
  }

  body.admin-sidebar-collapsed #dashboard-sidebar .btn-toggle,
  body.admin-sidebar-collapsed #dashboard-sidebar .sidebar-direct-link {
    min-height: 3.1rem;
    padding: 0.8rem 0.55rem;
  }

  body.admin-sidebar-collapsed #dashboard-sidebar .btn-toggle i,
  body.admin-sidebar-collapsed #dashboard-sidebar .sidebar-direct-link i,
  body.admin-sidebar-collapsed #dashboard-sidebar .sidebar-profile-trigger i {
    width: 1.6rem;
    font-size: 1.35rem;
    line-height: 1;
    text-align: center;
  }

  body.admin-sidebar-collapsed #dashboard-sidebar .sidebar-icon-wrap > .sidebar-attention-badge,
  body.admin-sidebar-collapsed #dashboard-sidebar .sidebar-direct-link[data-sidebar-has-badge="true"] > .sidebar-attention-badge {
    display: inline-flex !important;
    position: absolute;
    top: -0.38rem;
    right: -0.62rem;
    margin-left: 0;
    min-width: 1.18rem;
    height: 1.18rem;
    padding: 0 0.22rem;
    font-size: 0.62rem;
    box-shadow: 0 0 0 2px #fff;
  }

  #admin-mobile-header {
    display: none;
  }

  @media screen and (max-width: 768px), screen and (orientation: portrait) and (max-width: 1024px) {
    html,
    body {
      overflow-x: hidden;
    }

    body.admin-sidebar-open {
      overflow: hidden;
      touch-action: none;
    }

    body.admin-sidebar-open::before {
      content: "";
      position: fixed;
      inset: 0;
      z-index: 1090;
      background: rgba(15, 23, 42, 0.38);
      backdrop-filter: blur(2px);
    }

    #admin-mobile-header {
      display: block;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      z-index: 1200;
    }

    #admin-mobile-header .d-flex {
      min-height: 58px;
      gap: 0.6rem;
      padding-left: 0.9rem !important;
      padding-right: 0.9rem !important;
    }

    #btn-admin-burger {
      width: 44px;
      height: 44px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0;
      border-radius: 12px;
    }

    #dashboard-sidebar {
      position: fixed !important;
      top: 0;
      left: 0;
      width: min(88vw, 360px) !important;
      min-width: 0;
      height: 100vh;
      transform: translateX(-100%);
      transition: transform 0.3s ease;
      z-index: 1100;
      overflow-y: auto;
      border-right: 1px solid rgba(215, 221, 229, 0.9);
      box-shadow: 0 22px 44px rgba(15, 23, 42, 0.18);
    }

    #dashboard-sidebar.show {
      transform: translateX(0);
    }

    #dashboard-sidebar .sidebar-edge-toggle {
      display: none;
    }

    body {
      padding-top: 60px;
    }

    #main-display {
      width: 100%;
      min-width: 0;
      padding: 1rem !important;
    }
  }

  @media (max-width: 480px) {
    #main-display {
      padding: 0.85rem !important;
    }
  }

  @media (min-width: 769px) {
    #dashboard-sidebar {
      overflow: visible;
    }

    #main-display {
      position: relative;
      z-index: 1;
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
     id="dashboard-sidebar">

  <div class="sidebar-header">
    <a href="<?= htmlspecialchars(appUrl('Admin-End/AdminDashboard.php')) ?>" class="sidebar-brand-link link-dark text-decoration-none">
      <img src="<?= htmlspecialchars(appUrl('Images/San_Jose_LOGO.jpg')) ?>" class="sidebar-brand-logo" alt="Barangay San Jose Logo">
      <span class="sidebar-brand-title">Barangay San Jose</span>
    </a>
    <button type="button" class="sidebar-edge-toggle" id="btn-admin-sidebar-collapse" aria-label="Collapse navigation" aria-pressed="false" title="Collapse navigation" hidden>
      <i class="fa-solid fa-chevron-left"></i>
    </button>
  </div>

  <div class="sidebar-body d-flex flex-column flex-grow-1">
    <ul class="list-unstyled ps-0 flex-grow-1 mb-0">

      <?php if ($sbCan('dashboard')): ?>
      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Home</li>
      <li class="mb-2">
        <a href="<?= htmlspecialchars(appUrl('Admin-End/AdminDashboard.php')) ?>"
           class="btn btn-toggle sidebar-direct-link rounded <?= $isStatisticsActive ? 'active' : '' ?>"
           style="<?= $isStatisticsActive ? 'outline: none; box-shadow: none;' : '' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-house"></i>
          </span>
          <span class="sidebar-button-label">Dashboard</span>
        </a>
      </li>
      <?php endif; ?>

      <?php if ($sbCanAccessAppointmentTracker): ?>
      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Office of the Barangay</li>
      <li class="mb-1">
        <button type="button"
                class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isAppointmentMainNavActive ? 'active' : '' ?> <?= $isAppointmentActive ? '' : 'collapsed' ?>"
                data-sidebar-toggle="collapse"
                data-sidebar-target="#appointments-collapse"
                aria-controls="appointments-collapse"
                aria-expanded="<?= $isAppointmentActive ? 'true' : 'false' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-calendar-check"></i>
            <?= $sbRenderAttentionDot($sbModuleCount('appointments')) ?>
            <?= $sbRenderAttentionBadge($sbModuleCount('appointments')) ?>
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
            <?php if ($sbCanAccessAppointmentSettings): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Appointments/AppointmentTracker.php?tool=settings')) ?>"
                 class="link-dark rounded sidebar-subnav-link <?= $isAppointmentSettingsActive ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Settings</span>
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCanAccessAppointmentSchedule): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Appointments/AppointmentTracker.php?tool=schedule')) ?>"
                 class="link-dark rounded sidebar-subnav-link <?= $isAppointmentScheduleActive ? 'active' : '' ?>">
                <span class="sidebar-subnav-text"><?= $sbCanAccessAppointmentSettings ? 'Official Schedules' : 'My Schedule' ?></span>
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
      <?php endif; ?>

      <?php if ($sbHasAny(array_merge($sbResidentProfilingKeys, $sbHouseholdProfilingKeys, $sbAreaStatisticsKeys))): ?>
      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Resident Management</li>
      <?php if ($sbHasAny($sbResidentProfilingKeys)): ?>
      <li class="mb-1">
        <button type="button"
                class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isResidentMainNavActive ? 'active' : '' ?> <?= $isResidentMgmtActive ? '' : 'collapsed' ?>"
                data-sidebar-toggle="collapse"
                data-sidebar-target="#resident-mgmt-collapse"
                aria-controls="resident-mgmt-collapse"
                aria-expanded="<?= $isResidentMgmtActive ? 'true' : 'false' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-user-group"></i>
            <?= $sbRenderAttentionDot($sbModuleCount('resident_profiling')) ?>
            <?= $sbRenderAttentionBadge($sbModuleCount('resident_profiling')) ?>
          </span>
          <span class="sidebar-button-label">Resident Profiling</span>
        </button>
        <div class="collapse <?= $isResidentMgmtActive ? 'show' : '' ?>" id="resident-mgmt-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <?php if ($sbCan('resident_masterlist')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/ResidentTracker.php')) ?>"
                 class="link-dark rounded sidebar-subnav-link <?= $current === 'ResidentTracker.php' ? 'active' : '' ?>">
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
        <button type="button"
                class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isHouseholdMainNavActive ? 'active' : '' ?> <?= $isHouseholdProfilingActive ? '' : 'collapsed' ?>"
                data-sidebar-toggle="collapse"
                data-sidebar-target="#household-profiling-collapse"
                aria-controls="household-profiling-collapse"
                aria-expanded="<?= $isHouseholdProfilingActive ? 'true' : 'false' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-house"></i>
            <?= $sbRenderAttentionDot($sbModuleCount('household_profiling')) ?>
            <?= $sbRenderAttentionBadge($sbModuleCount('household_profiling')) ?>
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
        <button type="button"
                class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isAreaManagementMainNavActive ? 'active' : '' ?> <?= $isAreaManagementActive ? '' : 'collapsed' ?>"
                data-sidebar-toggle="collapse"
                data-sidebar-target="#area-management-collapse"
                aria-controls="area-management-collapse"
                aria-expanded="<?= $isAreaManagementActive ? 'true' : 'false' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-map-location-dot"></i>
          </span>
          <span class="sidebar-button-label">Statistics</span>
        </button>
        <div class="collapse <?= $isAreaManagementActive ? 'show' : '' ?>" id="area-management-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <?php if ($sbCan('dashboard')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/AreaManagement/BarangayStatistics.php')) ?>"
                 class="link-dark rounded <?= $current == 'BarangayStatistics.php' ? 'active' : '' ?>">
                Barangay Statistics
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('area_statistics_summary')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/AreaManagement/AreaStatistics.php?tab=summary')) ?>"
                 class="link-dark rounded <?= ($current == 'AreaStatistics.php' && $areaManagementTab === 'summary') ? 'active' : '' ?>">
                Area Statistics
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
        <button type="button"
           class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isCertificateIssuanceMainNavActive ? 'active' : '' ?> <?= $isCertificateIssuanceSectionActive ? '' : 'collapsed' ?>"
           data-sidebar-toggle="collapse"
           data-sidebar-target="#certificate-issuance-collapse"
           aria-controls="certificate-issuance-collapse"
           aria-expanded="<?= $isCertificateIssuanceSectionActive ? 'true' : 'false' ?>"
           data-sidebar-has-badge="<?= $sbCount('certificate_issuance') > 0 ? 'true' : 'false' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-file-circle-check"></i>
            <?= $sbRenderAttentionDot($sbModuleCount('certificate_issuance')) ?>
            <?= $sbRenderAttentionBadge($sbCount('certificate_issuance')) ?>
          </span>
          <span class="sidebar-button-label sidebar-button-label--certificate">Certificate Issuance</span>
        </button>
        <div class="collapse <?= $isCertificateIssuanceSectionActive ? 'show' : '' ?>" id="certificate-issuance-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/CertificateTracker.php?filter_document=__certificates__')) ?>"
                 class="link-dark rounded sidebar-subnav-link <?= $isCertificateIssuanceTrackerActive ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Tracker</span>
                <?= $sbRenderAttentionBadge($sbCount('certificate_issuance')) ?>
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/CertificateIssuanceSettings.php')) ?>"
                 class="link-dark rounded sidebar-subnav-link <?= $isCertificateIssuanceSettingsSubtabActive ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Settings</span>
              </a>
            </li>
          </ul>
        </div>
      </li>
      <?php endif; ?>
      <?php if ($sbHasAny($sbIdIssuanceKeys)): ?>
      <li class="mb-2">
        <button type="button"
                class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isIdIssuanceMainNavActive ? 'active' : '' ?> <?= $isIdIssuanceActive ? '' : 'collapsed' ?>"
                data-sidebar-toggle="collapse"
                data-sidebar-target="#id-issuance-collapse"
                aria-controls="id-issuance-collapse"
                aria-expanded="<?= $isIdIssuanceActive ? 'true' : 'false' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-id-card"></i>
            <?= $sbRenderAttentionDot($sbModuleCount('id_issuance')) ?>
            <?= $sbRenderAttentionBadge($sbModuleCount('id_issuance')) ?>
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
            <?php if ($sbCan('id_issuance_tracker')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/BarangayIdSettings.php')) ?>"
                 class="link-dark rounded sidebar-subnav-link <?= $isBarangayIdSettingsActive ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Settings</span>
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
      <li class="mb-2">
        <button type="button"
           class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isClearanceIssuanceMainNavActive ? 'active' : '' ?> <?= $isClearanceIssuanceSectionActive ? '' : 'collapsed' ?>"
           data-sidebar-toggle="collapse"
           data-sidebar-target="#clearance-issuance-collapse"
           aria-controls="clearance-issuance-collapse"
           aria-expanded="<?= $isClearanceIssuanceSectionActive ? 'true' : 'false' ?>"
           data-sidebar-has-badge="<?= $sbCount('clearance_issuance') > 0 ? 'true' : 'false' ?>"
        >
          <span class="sidebar-icon-wrap">
            <i class="fas fa-stamp"></i>
            <?= $sbRenderAttentionDot($sbModuleCount('clearance_issuance')) ?>
            <?= $sbRenderAttentionBadge($sbCount('clearance_issuance')) ?>
          </span>
          <span class="sidebar-button-label">Clearance Issuance</span>
        </button>
        <div class="collapse <?= $isClearanceIssuanceSectionActive ? 'show' : '' ?>" id="clearance-issuance-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <?php if ($sbCan('clearance_issuance')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/CertificateTracker.php?filter_document=__clearances__')) ?>"
                 class="link-dark rounded sidebar-subnav-link <?= $isClearanceIssuanceTrackerActive ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Tracker</span>
                <?= $sbRenderAttentionBadge($sbCount('clearance_issuance')) ?>
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/BusinessMonitoringSettings.php')) ?>"
                 class="link-dark rounded sidebar-subnav-link <?= $isBusinessMonitoringSettingsSubtabActive ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Settings</span>
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
      <?php if ($sbCan('business_monitoring')): ?>
      <li class="mb-2">
        <a href="<?= htmlspecialchars(appUrl('Admin-End/EstablishmentMonitoring.php')) ?>"
           class="btn btn-toggle sidebar-direct-link rounded <?= $isEstablishmentMonitoringActive ? 'active' : '' ?>"
           style="<?= $isEstablishmentMonitoringActive ? 'outline: none; box-shadow: none;' : '' ?>">
          <span class="sidebar-icon-wrap"><i class="fas fa-store"></i></span>
          <span class="sidebar-button-label">Establishment Monitoring</span>
        </a>
      </li>
      <li class="mb-2">
        <a href="<?= htmlspecialchars(appUrl('Admin-End/BusinessMonitoring.php')) ?>"
           class="btn btn-toggle sidebar-direct-link rounded <?= $isBusinessMonitoringActive ? 'active' : '' ?>"
           style="<?= $isBusinessMonitoringActive ? 'outline: none; box-shadow: none;' : '' ?>">
          <span class="sidebar-icon-wrap"><i class="fas fa-building"></i></span>
          <span class="sidebar-button-label">Business Monitoring</span>
        </a>
      </li>
      <?php endif; ?>
      <?php endif; ?>

      <?php if ($sbHasAny($sbFinanceKeys)): ?>
      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Barangay Treasury</li>
      <li class="mb-2">
        <button type="button"
                class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isFinanceMainNavActive ? 'active' : '' ?> <?= $isFinanceActive ? '' : 'collapsed' ?>"
                data-sidebar-toggle="collapse"
                data-sidebar-target="#finance-collapse"
                aria-controls="finance-collapse"
                aria-expanded="<?= $isFinanceActive ? 'true' : 'false' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-money-check-alt"></i>
            <?= $sbRenderAttentionDot($sbModuleCount('finance_transactions')) ?>
            <?= $sbRenderAttentionBadge($sbModuleCount('finance_transactions')) ?>
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
            <?php if ($sbCan('finance_payment_settings')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/FinancePayments.php')) ?>?section=settings"
                 class="link-dark rounded sidebar-subnav-link <?= $isFinanceSettingsActive ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Finance Settings</span>
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
      <?php endif; ?>

      <?php if ($sbCan('blotter_tracker') || $sbCan('blotter_review_queue')): ?>
      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">e-Blotter Management</li>
      <?php if ($sbCan('blotter_tracker') || $sbCan('blotter_review_queue')): ?>
      <li class="mb-1">
        <button type="button"
                class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isBlotterMainNavActive ? 'active' : '' ?> <?= $isBlotterActive ? '' : 'collapsed' ?>"
                data-sidebar-toggle="collapse"
                data-sidebar-target="#blotter-tools-collapse"
                aria-controls="blotter-tools-collapse"
                aria-expanded="<?= $isBlotterActive ? 'true' : 'false' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-toolbox"></i>
            <?= $sbRenderAttentionDot($sbModuleCount('blotter_tools')) ?>
            <?= $sbRenderAttentionBadge($sbModuleCount('blotter_tools')) ?>
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

      <?php if ($sbCan('complaint_tracker')): ?>
      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Complaints and Grievances</li>
      <?php if ($sbCan('complaint_tracker')): ?>
      <li class="mb-1">
        <button type="button"
                class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isComplaintMainNavActive ? 'active' : '' ?> <?= $isComplaintActive ? '' : 'collapsed' ?>"
                data-sidebar-toggle="collapse"
                data-sidebar-target="#complaint-tools-collapse"
                aria-controls="complaint-tools-collapse"
                aria-expanded="<?= $isComplaintActive ? 'true' : 'false' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-comments"></i>
            <?= $sbRenderAttentionDot($sbModuleCount('complaint_tools')) ?>
            <?= $sbRenderAttentionBadge($sbModuleCount('complaint_tools')) ?>
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

      <?php if ($sbCanAccessContentNavigator): ?>
      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Content Management</li>
      <li class="mb-2">
        <button type="button"
                class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isContentNavigatorMainNavActive ? 'active' : '' ?> <?= $isContentNavigatorActive ? '' : 'collapsed' ?>"
                data-sidebar-toggle="collapse"
                data-sidebar-target="#content-management-collapse"
                aria-controls="content-management-collapse"
                aria-expanded="<?= $isContentNavigatorActive ? 'true' : 'false' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-sitemap"></i>
            <?= $sbRenderAttentionDot($sbModuleCount('content_management')) ?>
            <?= $sbRenderAttentionBadge($sbModuleCount('content_management')) ?>
          </span>
          <span class="sidebar-button-label">Content Management</span>
        </button>
        <div class="collapse <?= $isContentNavigatorActive ? 'show' : '' ?>" id="content-management-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Contents/ContentManagement.php')) ?>"
                 class="link-dark rounded sidebar-subnav-link <?= ($current === 'ContentManagement.php' && $contentManagementModule === 'overview') ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Overview</span>
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Contents/ContentManagement.php')) ?>?module=requests"
                 class="link-dark rounded sidebar-subnav-link <?= ($current === 'ContentManagement.php' && $contentManagementModule === 'requests') ? 'active' : '' ?>">
                <span class="sidebar-subnav-text">Content Requests</span>
                <?= $sbRenderAttentionBadge($sbCount('content_change_request')) ?>
              </a>
            </li>
          </ul>
        </div>
      </li>
      <?php endif; ?>
      <?php if ($sbHasAny(array_merge($sbNewsKeys, $sbAnnouncementKeys, $sbReportKeys))): ?>
      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">General Modules</li>
      <?php if ($sbCan('news_management')): ?>
      <li class="mb-2">
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Contents/Contents.php')) ?>?tool=tracker&amp;type_filter=news#tracker-card"
           class="btn btn-toggle sidebar-direct-link rounded <?= $isNewsManagementActive ? 'active' : '' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-newspaper"></i>
          </span>
          <span class="sidebar-button-label">News</span>
        </a>
      </li>
      <?php endif; ?>
      <?php if ($sbHasAny($sbAnnouncementKeys)): ?>
      <li class="mb-2">
        <button type="button"
                class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isAnnouncementsMainNavActive ? 'active' : '' ?> <?= $isAnnouncementsActive ? '' : 'collapsed' ?>"
                data-sidebar-toggle="collapse"
                data-sidebar-target="#announcements-collapse"
                aria-controls="announcements-collapse"
                aria-expanded="<?= $isAnnouncementsActive ? 'true' : 'false' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-bullhorn"></i>
          </span>
          <span class="sidebar-button-label">Announcements</span>
        </button>
        <div class="collapse <?= $isAnnouncementsActive ? 'show' : '' ?>" id="announcements-collapse">
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
                 class="link-dark rounded <?= ($isAnnouncementsActive && $current === 'Contents.php' && $contentToolView === 'tracker') ? 'active' : '' ?>">
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
        <a href="<?= htmlspecialchars(appUrl('Admin-End/Reports/Reports.php')) ?>"
           class="btn btn-toggle sidebar-direct-link rounded <?= $isReportActive ? 'active' : '' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-chart-bar"></i>
          </span>
          <span class="sidebar-button-label">Reports</span>
        </a>
      </li>
      <?php endif; ?>
      <?php endif; ?>

      <?php if ($isSuperAdminSidebar && $sbHasAny($sbAdminKeys)): ?>
      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Admin Management</li>
      <?php if ($sbCan('admin_management')): ?>
      <li class="mb-1">
        <a href="<?= htmlspecialchars(appUrl('Admin-End/AdminManagement.php')) ?>"
           class="btn btn-toggle sidebar-direct-link rounded <?= $isAdminRecordsActive ? 'active' : '' ?>"
           style="<?= $isAdminRecordsActive ? 'outline: none; box-shadow: none;' : '' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-user-gear"></i>
          </span>
          <span class="sidebar-button-label">Admin Management</span>
        </a>
      </li>
      <?php endif; ?>
      <?php if ($sbCan('user_masterlist') || $sbCan('user_archive')): ?>
      <li class="mb-1">
        <button type="button"
                class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isUserManagementMainNavActive ? 'active' : '' ?> <?= $isUserMgmtActive ? '' : 'collapsed' ?>"
                data-sidebar-toggle="collapse"
                data-sidebar-target="#usermanagement-collapse"
                aria-controls="usermanagement-collapse"
                aria-expanded="<?= $isUserMgmtActive ? 'true' : 'false' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-users-cog"></i>
            <?= $sbRenderAttentionDot($sbModuleCount('user_management')) ?>
            <?= $sbRenderAttentionBadge($sbModuleCount('user_management')) ?>
          </span>
          <span class="sidebar-button-label">User Management</span>
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
      <?php if ($isSuperAdminSidebar && ($sbCan('officials_management') || $sbCan('personnel_invite'))): ?>
      <li class="mb-1">
        <button type="button"
                class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isPersonnelManagementMainNavActive ? 'active' : '' ?> <?= $isPersonnelMgmtActive ? '' : 'collapsed' ?>"
                data-sidebar-toggle="collapse"
                data-sidebar-target="#personnelmanagement-collapse"
                aria-controls="personnelmanagement-collapse"
                aria-expanded="<?= $isPersonnelMgmtActive ? 'true' : 'false' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-user-tie"></i>
          </span>
          <span class="sidebar-button-label">Personnel Management</span>
        </button>
        <div class="collapse <?= $isPersonnelMgmtActive ? 'show' : '' ?>" id="personnelmanagement-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <?php if ($sbCan('officials_management')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/PersonnelTracker.php')) ?>"
                 class="link-dark rounded <?= $current == 'PersonnelTracker.php' ? 'active' : '' ?>">
                Tracker
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('personnel_invite')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/OfficialInvites.php')) ?>"
                 class="link-dark rounded <?= $current == 'OfficialInvites.php' ? 'active' : '' ?>">
                Account Invite
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('officials_management')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/PersonnelRoleAccess.php')) ?>"
                 class="link-dark rounded <?= $current == 'PersonnelRoleAccess.php' ? 'active' : '' ?>">
                Personnel Access Control
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
      <?php endif; ?>
      <?php if ($sbCan('audit_logs')): ?>
      <li class="mb-1">
        <a href="<?= htmlspecialchars(appUrl('Admin-End/AuditLogs.php')) ?>"
           class="btn btn-toggle sidebar-direct-link rounded <?= $current == 'AuditLogs.php' ? 'active' : '' ?>"
           style="<?= $current == 'AuditLogs.php' ? 'outline: none; box-shadow: none;' : '' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-clipboard-list"></i>
          </span>
          <span class="sidebar-button-label">Audit Logs</span>
        </a>
      </li>
      <?php endif; ?>
      <?php if ($sbCan('website_settings')): ?>
      <li class="mb-1">
        <a href="<?= htmlspecialchars(appUrl('Admin-End/WebsiteSettings.php')) ?>"
           class="btn btn-toggle sidebar-direct-link rounded <?= $isWebsiteSettingsActive ? 'active' : '' ?>"
           style="<?= $isWebsiteSettingsActive ? 'outline: none; box-shadow: none;' : '' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-screwdriver-wrench"></i>
          </span>
          <span class="sidebar-button-label">Website Settings</span>
        </a>
      </li>
      <?php endif; ?>
      <?php if ($sbCan('official_records_management') || $sbCan('official_transition')): ?>
      <li class="mb-1 mt-3 text-muted small fw-semibold px-2">Barangay Officials</li>
      <li class="mb-1">
        <button type="button"
                class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isBarangayOfficialMgmtActive ? 'active' : '' ?> <?= $isBarangayOfficialMgmtActive ? '' : 'collapsed' ?>"
                data-sidebar-toggle="collapse"
                data-sidebar-target="#officialworkspace-collapse"
                aria-controls="officialworkspace-collapse"
                aria-expanded="<?= $isBarangayOfficialMgmtActive ? 'true' : 'false' ?>">
          <span class="sidebar-icon-wrap">
            <i class="fas fa-user-shield"></i>
          </span>
          <span class="sidebar-button-label">Officials &amp; Access</span>
        </button>
        <div class="collapse <?= $isBarangayOfficialMgmtActive ? 'show' : '' ?>" id="officialworkspace-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <?php if ($sbCan('official_transition')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/OfficialTransitions.php?panel=seat')) ?>"
                 class="link-dark rounded <?= $current == 'OfficialTransitions.php' && $officialTransitionPanel !== 'access' ? 'active' : '' ?>">
                Seats &amp; Onboarding
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('official_records_management')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/OfficialsManagement.php')) ?>"
                 class="link-dark rounded <?= $current == 'OfficialsManagement.php' ? 'active' : '' ?>">
                Official Records
              </a>
            </li>
            <?php endif; ?>
            <?php if ($sbCan('official_transition')): ?>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/OfficialTransitions.php?panel=access')) ?>"
                 class="link-dark rounded <?= $current == 'OfficialTransitions.php' && $officialTransitionPanel === 'access' ? 'active' : '' ?>">
                Access Templates
              </a>
            </li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
      <?php endif; ?>
      <?php endif; ?>

    </ul>

    <hr>

    <div class="sidebar-actions">
      <div class="dropdown mb-2 w-100">
        <a href="#" class="link-dark text-decoration-none dropdown-toggle w-100 sidebar-profile-trigger"
           data-bs-toggle="dropdown">
          <img src="<?= htmlspecialchars($adminProfileImageUrl, ENT_QUOTES, 'UTF-8') ?>"
               width="40"
               height="40"
               class="rounded-circle me-2"
               alt="<?= htmlspecialchars($adminDisplayName, ENT_QUOTES, 'UTF-8') ?>"
               style="object-fit: cover;"
               onerror="this.onerror=null;this.src='<?= htmlspecialchars(appUrl('Images/Profile-Placeholder.png'), ENT_QUOTES, 'UTF-8') ?>';">
          <div class="flex-grow-1 sidebar-profile-copy" style="min-width: 0;">
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
    const desktopToggleBtn = document.getElementById("btn-admin-sidebar-collapse");
    if (!sidebar) return;
    const collapseButtons = Array.from(sidebar.querySelectorAll('[data-sidebar-toggle="collapse"]'));
    const topLevelItems = Array.from(sidebar.querySelectorAll(".btn-toggle, .sidebar-direct-link, .sidebar-profile-trigger"));
    const drawerMq = window.matchMedia("(max-width: 768px), (orientation: portrait) and (max-width: 1024px)");
    const desktopQuery = window.matchMedia("(min-width: 769px)");
    const collapsedClass = "admin-sidebar-collapsed";
    const storageKey = "adminSidebarCollapsed";

    const mobileHeaderEl = document.getElementById("admin-mobile-header");
    const isCollapsibleViewport = () => {
      return desktopQuery.matches && (!mobileHeaderEl || window.getComputedStyle(mobileHeaderEl).display === "none");
    };

    const readStoredCollapsed = () => {
      try {
        return window.localStorage.getItem(storageKey) === "1";
      } catch (error) {
        return false;
      }
    };

    const writeStoredCollapsed = (collapsed) => {
      try {
        window.localStorage.setItem(storageKey, collapsed ? "1" : "0");
      } catch (error) {
        // Ignore storage access issues.
      }
    };

    const syncMobileOpenState = () => {
      const isOpen = drawerMq.matches && sidebar.classList.contains("show");
      document.body.classList.toggle("admin-sidebar-open", isOpen);
    };

    const getFirstLink = (button) => {
      const targetSelector = String(button.getAttribute("data-sidebar-target") || "").trim();
      if (!targetSelector) {
        return null;
      }

      const target = sidebar.querySelector(targetSelector);
      if (!target) {
        return null;
      }

      return target.querySelector("a[href]");
    };

    const getItemLabel = (element) => {
      const preferred = element.querySelector(".sidebar-button-label, .sidebar-brand-title");
      if (preferred) {
        return String(preferred.textContent || "").trim();
      }

      const profileName = element.querySelector(".sidebar-profile-copy .fw-bold");
      if (profileName) {
        return String(profileName.textContent || "").trim();
      }

      return String(element.textContent || "").replace(/\s+/g, " ").trim();
    };

    const updateCollapsedTitles = () => {
      const collapsed = isCollapsibleViewport() && document.body.classList.contains(collapsedClass);
      topLevelItems.forEach((item) => {
        const label = getItemLabel(item);
        if (!label) {
          return;
        }

        if (collapsed) {
          item.setAttribute("title", label);
          item.setAttribute("aria-label", label);
        } else {
          item.removeAttribute("title");
        }
      });
    };

    const updateDesktopToggle = () => {
      if (!desktopToggleBtn) {
        return;
      }

      const canCollapse = isCollapsibleViewport();
      const collapsed = canCollapse && document.body.classList.contains(collapsedClass);
      desktopToggleBtn.hidden = !canCollapse;
      desktopToggleBtn.setAttribute("aria-pressed", collapsed ? "true" : "false");
      desktopToggleBtn.setAttribute("aria-label", collapsed ? "Expand navigation" : "Collapse navigation");
      desktopToggleBtn.title = collapsed ? "Expand navigation" : "Collapse navigation";
    };

    const setDesktopCollapsed = (collapsed, persist = true) => {
      const shouldCollapse = isCollapsibleViewport() && collapsed;
      document.body.classList.toggle(collapsedClass, shouldCollapse);
      sidebar.classList.toggle("is-collapsed", shouldCollapse);

      if (persist) {
        writeStoredCollapsed(shouldCollapse);
      }

      updateDesktopToggle();
      updateCollapsedTitles();
    };

    const bindSidebarCollapse = (button) => {
      const targetSelector = String(button.getAttribute("data-sidebar-target") || "").trim();
      if (!targetSelector) {
        return;
      }

      const target = sidebar.querySelector(targetSelector);
      if (!target) {
        return;
      }

      const syncState = () => {
        const expanded = target.classList.contains("show");
        button.classList.toggle("collapsed", !expanded);
        button.setAttribute("aria-expanded", expanded ? "true" : "false");
      };

      button.addEventListener("click", (event) => {
        if (isCollapsibleViewport() && document.body.classList.contains(collapsedClass)) {
          const firstLink = getFirstLink(button);
          if (firstLink) {
            window.location.href = firstLink.href;
            return;
          }
        }

        event.preventDefault();
        target.classList.toggle("show");
        syncState();
      });

      syncState();
    };

    const syncMode = () => {
      if (!drawerMq.matches) {
        sidebar.classList.remove("show");
        setDesktopCollapsed(readStoredCollapsed(), false);
      } else {
        document.body.classList.remove(collapsedClass);
        sidebar.classList.remove("is-collapsed");
      }

      syncMobileOpenState();
      updateDesktopToggle();
      updateCollapsedTitles();
    };

    if (burgerBtn) {
      burgerBtn.addEventListener("click", () => {
        if (!drawerMq.matches) return;
        sidebar.classList.toggle("show");
        syncMobileOpenState();
      });
    }

    if (desktopToggleBtn) {
      desktopToggleBtn.addEventListener("click", () => {
        if (!isCollapsibleViewport()) {
          return;
        }

        const nextCollapsed = !document.body.classList.contains(collapsedClass);
        setDesktopCollapsed(nextCollapsed, true);
      });
    }

    sidebar.querySelectorAll("a").forEach((link) => {
      link.addEventListener("click", () => {
        if (!drawerMq.matches) return;
        if (String(link.getAttribute("data-bs-toggle") || "").toLowerCase() === "dropdown") {
          return;
        }
        sidebar.classList.remove("show");
        syncMobileOpenState();
      });
    });

    document.addEventListener("click", (event) => {
      if (!drawerMq.matches || !sidebar.classList.contains("show")) {
        return;
      }

      const target = event.target;
      if (!(target instanceof Node)) {
        return;
      }

      if (sidebar.contains(target) || burgerBtn?.contains(target)) {
        return;
      }

      sidebar.classList.remove("show");
      syncMobileOpenState();
    });

    document.addEventListener("keydown", (event) => {
      if (event.key !== "Escape" || !drawerMq.matches || !sidebar.classList.contains("show")) {
        return;
      }

      sidebar.classList.remove("show");
      syncMobileOpenState();
    });

    if (typeof drawerMq.addEventListener === "function") {
      drawerMq.addEventListener("change", syncMode);
    } else if (typeof drawerMq.addListener === "function") {
      drawerMq.addListener(syncMode);
    }

    if (typeof desktopQuery.addEventListener === "function") {
      desktopQuery.addEventListener("change", syncMode);
    } else if (typeof desktopQuery.addListener === "function") {
      desktopQuery.addListener(syncMode);
    }

    collapseButtons.forEach(bindSidebarCollapse);
    window.addEventListener("resize", syncMode);
    syncMode();
  })();
</script>
<script src="<?= htmlspecialchars(appUrl('/JS-Script-Files/websitePreferences.js'), ENT_QUOTES, 'UTF-8') ?>" data-endpoint="<?= htmlspecialchars(appUrl('/PhpFiles/GET/getWebsitePreferences.php'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(appUrl('/JS-Script-Files/Admin-End/adminTableActions.js?v=20260812-action-dropdown-anchor'), ENT_QUOTES, 'UTF-8') ?>"></script>
