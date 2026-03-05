<?php
$current = basename($_SERVER['PHP_SELF']);

// Group pages by section
$residentMgmtPages = ['ResidentMasterlist.php', 'ResidentArchive.php', 'EditRequests.php', 'SectorMembershipVerification.php', 'HouseholdProfiling.php'];
$certPages = ['CertificateTracker.php'];
$financePages = ['FinancePayments.php'];
$blotterPages = ['BlotterForm.php', 'BlotterTracker.php'];
$userMgmtPages = ['UserMasterlist.php'];
$adminMgmtPages = ['OfficialsManagement.php', 'OfficialInvites.php'];

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
$isCertActive = in_array($current, $certPages);
$isFinanceActive = in_array($current, $financePages);
$isBlotterActive = in_array($current, $blotterPages);
$isUserMgmtActive = in_array($current, $userMgmtPages);
$isAdminMgmtActive = in_array($current, $adminMgmtPages);
$isSuperAdminSidebar = ((string)($_SESSION['role'] ?? '') === 'SuperAdmin');

$adminDisplayName = "Admin User";
$adminPosition = "Administrator";

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
}
?>

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

      <!-- DASHBOARD -->
      <li class="mb-2">
        <a href="<?= htmlspecialchars(appUrl('Admin-End/AdminDashboard.php')) ?>"
           class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $current == 'AdminDashboard.php' ? 'active' : '' ?>"
           style="<?= $current == 'AdminDashboard.php' ? 'outline: none; box-shadow: none;' : '' ?>">
          <i class="fas fa-chart-area"></i> Dashboard
        </a>
      </li>

      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Resident Management</li>

      <!-- RESIDENT MANAGEMENT -->
      <li class="mb-1">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isResidentMgmtActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#resident-mgmt-collapse"
                aria-expanded="<?= $isResidentMgmtActive ? 'true' : 'false' ?>"
                style="<?= $isResidentMgmtActive ? 'outline: none; box-shadow: none;' : '' ?>">
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
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/HouseholdProfiling.php')) ?>"
                 class="link-dark rounded <?= $current == 'HouseholdProfiling.php' ? 'active' : '' ?>">
                Household Profiling
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
          <i class="fas fa-file-circle-check"></i> Certificate Issuance
        </button>

        <div class="collapse <?= $isCertActive ? 'show' : '' ?>" id="cert-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li><a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/CertificateTracker.php')) ?>" class="link-dark rounded <?= $current == 'CertificateTracker.php' ? 'active' : '' ?>">Tracker</a></li>
          </ul>
        </div>
      </li>

      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Finance Department</li>
      <li class="mb-2">
        <a href="<?= htmlspecialchars(appUrl('Admin-End/Certificates/FinancePayments.php')) ?>"
           class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isFinanceActive ? 'active' : '' ?>"
           style="<?= $isFinanceActive ? 'outline: none; box-shadow: none;' : '' ?>">
          <i class="fas fa-money-check-alt"></i> Finance Payments
        </a>
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
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $current == 'BlotterTracker.php' ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#blotter-tools-collapse"
                aria-expanded="<?= $current == 'BlotterTracker.php' ? 'true' : 'false' ?>">
          <i class="fas fa-toolbox"></i> e-Blotter Tools
        </button>

        <div class="collapse <?= $current == 'BlotterTracker.php' ? 'show' : '' ?>" id="blotter-tools-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars(appUrl('Admin-End/Blotter/BlotterTracker.php')) ?>"
                 class="link-dark rounded <?= $current == 'BlotterTracker.php' ? 'active' : '' ?>">
                Tracker
              </a>
            </li>
          </ul>
        </div>
      </li>

      <?php if ($isSuperAdminSidebar): ?>
      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Admin Management</li>
      <!-- PERSONNEL MANAGEMENT -->
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
          <i class="fas fa-right-left"></i> Official Transition Module
        </a>
      </li>

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
          <img src="<?= htmlspecialchars(appUrl('Images/Profile-Placeholder.png')) ?>" width="40" height="40" class="rounded-circle me-2">
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
