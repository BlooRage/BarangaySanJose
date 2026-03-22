<?php
$current = basename($_SERVER['PHP_SELF']);

// Group pages by section
$residentMgmtPages = ['ResidentMasterlist.php', 'ResidentArchive.php', 'EditRequests.php', 'SectorMembershipVerification.php'];
$householdProfilingPages = ['HouseholdProfiling.php', 'HeadOfTheFamilyVerification.php'];
$appointmentPages = ['AppointmentTracker.php'];
$certPages = ['CertificateTracker.php'];
$financePages = ['FinancePayments.php'];
$blotterPages = ['BlotterForm.php', 'BlotterTracker.php', 'ReviewQueue.php'];
$complaintPages = ['ComplaintForm.php', 'ComplaintTracker.php'];
$contentMgmtPages = ['Contents.php', 'CreateContent.php'];
$areaManagementPages = ['AreaStatistics.php', 'AreaProfile.php'];
$reportPages = ['Reports.php'];
$userMgmtPages = ['UserMasterlist.php'];
$adminMgmtPages = ['OfficialsManagement.php', 'OfficialInvites.php'];

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('appUrl')) {
    require_once __DIR__ . '/../../PhpFiles/General/security.php';
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
$isFinanceActive = in_array($current, $financePages);
$isBlotterActive = in_array($current, $blotterPages);
$isComplaintActive = in_array($current, $complaintPages);
$isContentMgmtActive = in_array($current, $contentMgmtPages);
$isAreaManagementActive = in_array($current, $areaManagementPages);
$isUserMgmtActive = in_array($current, $userMgmtPages);
$isAdminMgmtActive = in_array($current, $adminMgmtPages);
$isReportActive = in_array($current, $reportPages);
$isStatisticsActive = ($current === 'AdminDashboard.php');
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
$isFinanceFeesActive = $current === 'FinancePayments.php' && $financeSection === 'fees';
$isFinanceCashbookActive = $current === 'FinancePayments.php' && $financeSection === 'cashbook';
$isFinanceTrackerActive = $current === 'FinancePayments.php' && !in_array($financeSection, ['fees', 'cashbook'], true);
$contentCreateType = strtolower(trim((string)($_GET['type'] ?? 'page')));
if (!in_array($contentCreateType, ['page', 'delivery', 'faq'], true)) {
    $contentCreateType = 'page';
}
$contentToolView = strtolower(trim((string)($_GET['tool'] ?? 'tracker')));
if ($contentToolView !== 'tracker') {
    $contentToolView = 'tracker';
}
$isContentCreateSectionActive = $current === 'CreateContent.php';
$isContentToolsSectionActive = $current === 'Contents.php';

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

      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Home</li>
      <li class="mb-2">
        <a href="<?= htmlspecialchars(appUrl('Admin-End/AdminDashboard.php')) ?>"
           class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isStatisticsActive ? 'active' : '' ?>"
           style="<?= $isStatisticsActive ? 'outline: none; box-shadow: none;' : '' ?>">
          <i class="fas fa-house"></i> Dashboard
        </a>
      </li>

      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Office of the Barangay</li>
      <li class="mb-2">
        <a href="<?= htmlspecialchars(appUrl('Admin-End/Appointments/AppointmentTracker.php')) ?>"
           class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isAppointmentActive ? 'active' : '' ?>"
           style="<?= $isAppointmentActive ? 'outline: none; box-shadow: none;' : '' ?>">
          <i class="fas fa-calendar-check"></i> Appointments
        </a>
      </li>

      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Resident Management</li>
      <li class="mb-1">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isResidentMgmtActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#resident-mgmt-collapse"
                aria-expanded="<?= $isResidentMgmtActive ? 'true' : 'false' ?>">
          <i class="fas fa-user-group"></i> Resident Profiling
        </button>
        <div class="collapse <?= $isResidentMgmtActive ? 'show' : '' ?>" id="resident-mgmt-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/ResidentMasterlist.php')) ?>"
                 class="link-dark rounded <?= $current == 'ResidentMasterlist.php' ? 'active' : '' ?>">
                Masterlist
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/EditRequests.php')) ?>"
                 class="link-dark rounded <?= $current == 'EditRequests.php' ? 'active' : '' ?>">
                Edit Requests
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/ResidentArchive.php')) ?>"
                 class="link-dark rounded <?= $current == 'ResidentArchive.php' ? 'active' : '' ?>">
                Resident Archive
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/SectorMembershipVerification.php')) ?>"
                 class="link-dark rounded <?= $current == 'SectorMembershipVerification.php' ? 'active' : '' ?>">
                Sector Membership Verification
              </a>
            </li>
          </ul>
        </div>
      </li>
      <li class="mb-1">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isHouseholdProfilingActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#household-profiling-collapse"
                aria-expanded="<?= $isHouseholdProfilingActive ? 'true' : 'false' ?>">
          <i class="fas fa-house"></i> Household Profiling
        </button>
        <div class="collapse <?= $isHouseholdProfilingActive ? 'show' : '' ?>" id="household-profiling-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/HouseholdProfiling.php')) ?>"
                 class="link-dark rounded <?= $current == 'HouseholdProfiling.php' ? 'active' : '' ?>">
                Household Profiling
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/HeadOfTheFamilyVerification.php')) ?>"
                 class="link-dark rounded <?= $current == 'HeadOfTheFamilyVerification.php' ? 'active' : '' ?>">
                Head of the Family Verification
              </a>
            </li>
          </ul>
      </li>
      <li class="mb-1">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isAppointmentActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#appointment-collapse"
                aria-expanded="<?= $isAppointmentActive ? 'true' : 'false' ?>">
          <i class="fas fa-calendar-check"></i> Appointments
        </button>

        <div class="collapse <?= $isAppointmentActive ? 'show' : '' ?>" id="appointment-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Appointments/AppointmentTracker.php')) ?>"
                 class="link-dark rounded <?= $current == 'AppointmentTracker.php' ? 'active' : '' ?>">
                Tracker
              </a>
            </li>
          </ul>
        </div>
      </li>

      <!-- CERTIFICATE ISSUANCE (Resident Management) -->
      <li class="mb-2">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isCertActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#cert-collapse"
                aria-expanded="<?= $isCertActive ? 'true' : 'false' ?>">
          <i class="fas fa-file-circle-check"></i> Document Issuance
        </button>

        <div class="collapse <?= $isCertActive ? 'show' : '' ?>" id="cert-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/CertificateTracker.php')) ?>"
                 class="link-dark rounded <?= $isCertificateTrackerActive ? 'active' : '' ?>">
                Tracker
              </a>
            </li>
          </ul>
        </div>
      </li>

      <li class="mb-2">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isIdIssuanceActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#id-issuance-collapse"
                aria-expanded="<?= $isIdIssuanceActive ? 'true' : 'false' ?>">
          <i class="fas fa-id-card"></i> ID ISSUANCE
        </button>

        <div class="collapse <?= $isIdIssuanceActive ? 'show' : '' ?>" id="id-issuance-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/CertificateTracker.php?entry=id_issuance')) ?>"
                 class="link-dark rounded <?= $isIdIssuanceTrackerActive ? 'active' : '' ?>">
                Tracker
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/CertificateTracker.php?tab=manual&document=barangay_id')) ?>"
                 class="link-dark rounded <?= $isIdIssuanceManualActive ? 'active' : '' ?>">
                Manual Issuance
              </a>
            </li>
          </ul>
        </div>
      </li>

      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Finance Department</li>
      <li class="mb-1">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isFinanceActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#finance-collapse"
                aria-expanded="<?= $isFinanceActive ? 'true' : 'false' ?>">
          <i class="fas fa-money-check-alt"></i> Finance Payments
        </button>

        <div class="collapse <?= $isFinanceActive ? 'show' : '' ?>" id="finance-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/FinancePayments.php')) ?>"
                 class="link-dark rounded <?= $isFinanceTrackerActive ? 'active' : '' ?>">
                Payment Tracker
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/FinancePayments.php')) ?>?section=cashbook"
                 class="link-dark rounded <?= $isFinanceCashbookActive ? 'active' : '' ?>">
                Cashbook
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/FinancePayments.php')) ?>?section=fees"
                 class="link-dark rounded <?= $isFinanceFeesActive ? 'active' : '' ?>">
                Fee Management
              </a>
            </li>
          </ul>
        </div>
      </li>

      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">e-Blotter Management</li>
      <li class="mb-2">
        <a href="<?= htmlspecialchars(appUrl('Admin-End/Blotter/BlotterForm.php')) ?>"
           class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $current == 'BlotterForm.php' ? 'active' : '' ?>"
           style="<?= $current == 'BlotterForm.php' ? 'outline: none; box-shadow: none;' : '' ?>">
          <i class="fas fa-file-pen"></i> Log New Incident
        </a>
      </li>
      <li class="mb-1">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isBlotterActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#blotter-tools-collapse"
                aria-expanded="<?= $isBlotterActive ? 'true' : 'false' ?>">
          <i class="fas fa-toolbox"></i> e-Blotter Tools
        </button>

        <div class="collapse <?= $isBlotterActive ? 'show' : '' ?>" id="blotter-tools-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Blotter/BlotterTracker.php')) ?>"
                 class="link-dark rounded <?= $current == 'BlotterTracker.php' ? 'active' : '' ?>">
                Tracker
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Blotter/ReviewQueue.php')) ?>"
                 class="link-dark rounded <?= $current == 'ReviewQueue.php' ? 'active' : '' ?>">
                Review Queue
              </a>
            </li>
          </ul>
        </div>
      </li>

      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Complaints and Grievances</li>
      <li class="mb-2">
        <a href="<?= htmlspecialchars(appUrl('Admin-End/Complaints/ComplaintForm.php')) ?>"
           class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $current == 'ComplaintForm.php' ? 'active' : '' ?>"
           style="<?= $current == 'ComplaintForm.php' ? 'outline: none; box-shadow: none;' : '' ?>">
          <i class="fas fa-file-pen"></i> Log New Incident
        </a>
      </li>
      <li class="mb-1">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isComplaintActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#complaint-tools-collapse"
                aria-expanded="<?= $isComplaintActive ? 'true' : 'false' ?>">
          <i class="fas fa-comments"></i> Complaint Tools
        </button>

        <div class="collapse <?= $isComplaintActive ? 'show' : '' ?>" id="complaint-tools-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Complaints/ComplaintTracker.php')) ?>"
                 class="link-dark rounded <?= $current == 'ComplaintTracker.php' ? 'active' : '' ?>">
                Tracker
              </a>
            </li>
          </ul>
        </div>
      </li>

      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Content Management</li>
      <li class="mb-1">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isContentCreateSectionActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#content-create-collapse"
                aria-expanded="<?= $isContentCreateSectionActive ? 'true' : 'false' ?>">
          <i class="fas fa-file-pen"></i> Create
        </button>

        <div class="collapse <?= $isContentCreateSectionActive ? 'show' : '' ?>" id="content-create-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Contents/CreateContent.php')) ?>?type=page"
                 class="link-dark rounded <?= ($current === 'CreateContent.php' && $contentCreateType === 'page') ? 'active' : '' ?>">
                Page Announcement
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Contents/CreateContent.php')) ?>?type=delivery"
                 class="link-dark rounded <?= ($current === 'CreateContent.php' && $contentCreateType === 'delivery') ? 'active' : '' ?>">
                SMS and Email Announcement
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Contents/CreateContent.php')) ?>?type=faq"
                 class="link-dark rounded <?= ($current === 'CreateContent.php' && $contentCreateType === 'faq') ? 'active' : '' ?>">
                FAQs Page
              </a>
            </li>
          </ul>
        </div>
      </li>
      <li class="mb-2">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isAreaManagementActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#area-management-collapse"
                aria-expanded="<?= $isAreaManagementActive ? 'true' : 'false' ?>">
          <i class="fas fa-map-location-dot"></i> Area Statistics
        </button>
        <div class="collapse <?= $isAreaManagementActive ? 'show' : '' ?>" id="area-management-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/AreaManagement/AreaStatistics.php?tab=summary')) ?>"
                 class="link-dark rounded <?= ($current == 'AreaStatistics.php' && $areaManagementTab === 'summary') ? 'active' : '' ?>">
                Summary
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/AreaManagement/AreaProfile.php?area=' . rawurlencode('Area 01'))) ?>"
                 class="link-dark rounded <?= ($current == 'AreaProfile.php' && $areaManagementArea === 'Area 01') ? 'active' : '' ?>">
                Area 01
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/AreaManagement/AreaProfile.php?area=' . rawurlencode('Area 1A'))) ?>"
                 class="link-dark rounded <?= ($current == 'AreaProfile.php' && $areaManagementArea === 'Area 1A') ? 'active' : '' ?>">
                Area 1A
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/AreaManagement/AreaProfile.php?area=' . rawurlencode('Area 02'))) ?>"
                 class="link-dark rounded <?= ($current == 'AreaProfile.php' && $areaManagementArea === 'Area 02') ? 'active' : '' ?>">
                Area 02
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/AreaManagement/AreaProfile.php?area=' . rawurlencode('Area 03'))) ?>"
                 class="link-dark rounded <?= ($current == 'AreaProfile.php' && $areaManagementArea === 'Area 03') ? 'active' : '' ?>">
                Area 03
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/AreaManagement/AreaProfile.php?area=' . rawurlencode('Area 04'))) ?>"
                 class="link-dark rounded <?= ($current == 'AreaProfile.php' && $areaManagementArea === 'Area 04') ? 'active' : '' ?>">
                Area 04
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/AreaManagement/AreaProfile.php?area=' . rawurlencode('Area 05'))) ?>"
                 class="link-dark rounded <?= ($current == 'AreaProfile.php' && $areaManagementArea === 'Area 05') ? 'active' : '' ?>">
                Area 05
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/AreaManagement/AreaProfile.php?area=' . rawurlencode('Area 06'))) ?>"
                 class="link-dark rounded <?= ($current == 'AreaProfile.php' && $areaManagementArea === 'Area 06') ? 'active' : '' ?>">
                Area 06
              </a>
            </li>
          </ul>
        </div>
      </li>

      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Barangay Issuance</li>
      <li class="mb-2">
        <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/CertificateTracker.php?filter_document=__certificates__')) ?>"
           class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isCertificateIssuanceSectionActive ? 'active' : '' ?>"
           style="<?= $isCertificateIssuanceSectionActive ? 'outline: none; box-shadow: none;' : '' ?>">
          <i class="fas fa-file-circle-check"></i> Certificate Issuance
        </a>
      </li>
      <li class="mb-2">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isIdIssuanceActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#id-issuance-collapse"
                aria-expanded="<?= $isIdIssuanceActive ? 'true' : 'false' ?>">
          <i class="fas fa-id-card"></i> ID Issuance
        </button>
        <div class="collapse <?= $isIdIssuanceActive ? 'show' : '' ?>" id="id-issuance-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/CertificateTracker.php?entry=id_issuance')) ?>"
                 class="link-dark rounded <?= $isIdIssuanceTrackerActive ? 'active' : '' ?>">
                Tracker
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/CertificateTracker.php?tab=manual&document=barangay_id')) ?>"
                 class="link-dark rounded <?= $isIdIssuanceManualActive ? 'active' : '' ?>">
                Manual Issuance
              </a>
            </li>
          </ul>
        </div>
      </li>

      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Barangay Monitoring</li>
      <li class="mb-2">
        <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/CertificateTracker.php?filter_document=__clearances__')) ?>"
           class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isClearanceIssuanceSectionActive ? 'active' : '' ?>"
           style="<?= $isClearanceIssuanceSectionActive ? 'outline: none; box-shadow: none;' : '' ?>">
          <i class="fas fa-stamp"></i> Clearance Issuance
        </a>
      </li>
      <li class="mb-2">
        <a href="<?= htmlspecialchars(appUrl('Admin-End/BusinessMonitoring.php')) ?>"
           class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isBusinessMonitoringActive ? 'active' : '' ?>"
           style="<?= $isBusinessMonitoringActive ? 'outline: none; box-shadow: none;' : '' ?>">
          <i class="fas fa-store"></i> Business Monitoring
        </a>
      </li>

      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Barangay Treasury</li>
      <li class="mb-2">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isFinanceActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#finance-collapse"
                aria-expanded="<?= $isFinanceActive ? 'true' : 'false' ?>">
          <i class="fas fa-money-check-alt"></i> Finance Transactions
        </button>
        <div class="collapse <?= $isFinanceActive ? 'show' : '' ?>" id="finance-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/FinancePayments.php')) ?>"
                 class="link-dark rounded <?= $isFinanceTrackerActive ? 'active' : '' ?>">
                Payment Tracker
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/FinancePayments.php')) ?>?section=cashbook"
                 class="link-dark rounded <?= $isFinanceCashbookActive ? 'active' : '' ?>">
                Cashbook
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/FinancePayments.php')) ?>?section=fees"
                 class="link-dark rounded <?= $isFinanceFeesActive ? 'active' : '' ?>">
                Fee Management
              </a>
            </li>
          </ul>
        </div>
      </li>

      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Barangay Peace and Order</li>
      <li class="mb-1">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isComplaintActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#complaint-tools-collapse"
                aria-expanded="<?= $isComplaintActive ? 'true' : 'false' ?>">
          <i class="fas fa-comments"></i> Complaint Tools
        </button>
        <div class="collapse <?= $isComplaintActive ? 'show' : '' ?>" id="complaint-tools-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Complaints/ComplaintTracker.php')) ?>"
                 class="link-dark rounded <?= $current == 'ComplaintTracker.php' ? 'active' : '' ?>">
                Tracker
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Complaints/ComplaintForm.php')) ?>"
                 class="link-dark rounded <?= $current == 'ComplaintForm.php' ? 'active' : '' ?>">
                Create Complaint
              </a>
            </li>
          </ul>
        </div>
      </li>
      <li class="mb-2">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isBlotterActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#blotter-tools-collapse"
                aria-expanded="<?= $isBlotterActive ? 'true' : 'false' ?>">
          <i class="fas fa-toolbox"></i> E-Blotter Tools
        </button>
        <div class="collapse <?= $isBlotterActive ? 'show' : '' ?>" id="blotter-tools-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Blotter/BlotterTracker.php')) ?>"
                 class="link-dark rounded <?= $current == 'BlotterTracker.php' ? 'active' : '' ?>">
                Tracker
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Blotter/ReviewQueue.php')) ?>"
                 class="link-dark rounded <?= $current == 'ReviewQueue.php' ? 'active' : '' ?>">
                Review Queue
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Blotter/BlotterForm.php')) ?>"
                 class="link-dark rounded <?= $current == 'BlotterForm.php' ? 'active' : '' ?>">
                Log New Incident
              </a>
            </li>
          </ul>
        </div>
      </li>

      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">General Modules</li>
      <li class="mb-1">
        <a href="javascript:void(0)"
           class="btn btn-toggle d-flex align-items-center justify-content-start text-start gap-2 rounded w-100 text-muted"
           style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: default; opacity: 0.8;"
           aria-disabled="true">
          <i class="fas fa-bullhorn"></i> Announcements
        </a>
      </li>
      <li class="mb-2">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isReportActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#reports-collapse"
                aria-expanded="<?= $isReportActive ? 'true' : 'false' ?>">
          <i class="fas fa-chart-bar"></i> Reports
        </button>
        <div class="collapse <?= $isReportActive ? 'show' : '' ?>" id="reports-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Reports/Reports.php')) ?>?module=certificate_issuance"
                 class="link-dark rounded <?= ($current === 'Reports.php' && $reportModule === 'certificate_issuance') ? 'active' : '' ?>">
                Certificate Issuance
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Reports/Reports.php')) ?>?module=clearance_issuance"
                 class="link-dark rounded <?= ($current === 'Reports.php' && $reportModule === 'clearance_issuance') ? 'active' : '' ?>">
                Clearance Issuance
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Reports/Reports.php')) ?>?module=financial"
                 class="link-dark rounded <?= ($current === 'Reports.php' && $reportModule === 'financial') ? 'active' : '' ?>">
                Financial
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Reports/Reports.php')) ?>?module=residents"
                 class="link-dark rounded <?= ($current === 'Reports.php' && $reportModule === 'residents') ? 'active' : '' ?>">
                Residents
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Reports/Reports.php')) ?>?module=appointments"
                 class="link-dark rounded <?= ($current === 'Reports.php' && $reportModule === 'appointments') ? 'active' : '' ?>">
                Appointments
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Reports/Reports.php')) ?>?module=blotter"
                 class="link-dark rounded <?= ($current === 'Reports.php' && $reportModule === 'blotter') ? 'active' : '' ?>">
                Blotter
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Reports/Reports.php')) ?>?module=complaints"
                 class="link-dark rounded <?= ($current === 'Reports.php' && $reportModule === 'complaints') ? 'active' : '' ?>">
                Complaints
              </a>
            </li>
          </ul>
        </div>
      </li>

      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Content Management</li>
      <li class="mb-1">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isContentCreateSectionActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#content-create-collapse"
                aria-expanded="<?= $isContentCreateSectionActive ? 'true' : 'false' ?>">
          <i class="fas fa-file-pen"></i> Create
        </button>
        <div class="collapse <?= $isContentCreateSectionActive ? 'show' : '' ?>" id="content-create-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Contents/CreateContent.php')) ?>?type=page"
                 class="link-dark rounded <?= ($current === 'CreateContent.php' && $contentCreateType === 'page') ? 'active' : '' ?>">
                Page Announcement
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Contents/CreateContent.php')) ?>?type=delivery"
                 class="link-dark rounded <?= ($current === 'CreateContent.php' && $contentCreateType === 'delivery') ? 'active' : '' ?>">
                SMS and Email Announcement
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Contents/CreateContent.php')) ?>?type=faq"
                 class="link-dark rounded <?= ($current === 'CreateContent.php' && $contentCreateType === 'faq') ? 'active' : '' ?>">
                FAQs Page
              </a>
            </li>
          </ul>
        </div>
      </li>
      <li class="mb-2">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isContentToolsSectionActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#content-tools-collapse"
                aria-expanded="<?= $isContentToolsSectionActive ? 'true' : 'false' ?>">
          <i class="fas fa-folder-open"></i> Content Tools
        </button>
        <div class="collapse <?= $isContentToolsSectionActive ? 'show' : '' ?>" id="content-tools-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Contents/Contents.php')) ?>?tool=tracker#tracker-card"
                 class="link-dark rounded <?= ($current === 'Contents.php' && $contentToolView === 'tracker') ? 'active' : '' ?>">
                Tracker
              </a>
            </li>
          </ul>
        </div>
      </li>

      <?php if ($isSuperAdminSidebar): ?>
      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Admin Management</li>
      <li class="mb-1">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isUserMgmtActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#usermgmt-collapse"
                aria-expanded="<?= $isUserMgmtActive ? 'true' : 'false' ?>">
          <i class="fas fa-users-cog"></i> User Management
        </button>
        <div class="collapse <?= $isUserMgmtActive ? 'show' : '' ?>" id="usermgmt-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/UserMasterlist.php')) ?>"
                 class="link-dark rounded <?= $current == 'UserMasterlist.php' ? 'active' : '' ?>">
                User Masterlist
              </a>
            </li>
          </ul>
        </div>
      </li>
      <li class="mb-1">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isAdminMgmtActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#adminmgmt-collapse"
                aria-expanded="<?= $isAdminMgmtActive ? 'true' : 'false' ?>">
          <i class="fas fa-user-shield"></i> Personnel Management
        </button>
        <div class="collapse <?= $isAdminMgmtActive ? 'show' : '' ?>" id="adminmgmt-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/OfficialsManagement.php')) ?>"
                 class="link-dark rounded <?= $current == 'OfficialsManagement.php' ? 'active' : '' ?>">
                Officials Management
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/OfficialInvites.php')) ?>"
                 class="link-dark rounded <?= $current == 'OfficialInvites.php' ? 'active' : '' ?>">
                Personnel Invite
              </a>
            </li>
          </ul>
        </div>
      </li>
      <li class="mb-1">
        <a href="javascript:void(0)"
           class="btn btn-toggle d-flex align-items-center justify-content-start text-start gap-2 rounded w-100 text-muted"
           style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: default; opacity: 0.8;"
           aria-disabled="true">
          <i class="fas fa-right-left"></i> Official Transition
        </a>
      </li>
      <li class="mb-1">
        <a href="<?= htmlspecialchars(appUrl('Admin-End/AuditLogs.php')) ?>"
           class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $current == 'AuditLogs.php' ? 'active' : '' ?>"
           style="<?= $current == 'AuditLogs.php' ? 'outline: none; box-shadow: none;' : '' ?>">
          <i class="fas fa-clipboard-list"></i> Audit Logs
        </a>
      </li>
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
          <li><a class="dropdown-item" href="<?= htmlspecialchars(appUrl('Admin-End/admin_profile.php')) ?>">Profile</a></li>
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
        if (portraitMq.matches) sidebar.classList.remove("show");
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
