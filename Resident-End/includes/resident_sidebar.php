<?php
if (!isset($baseUrl)) {
  $scriptName = str_replace("\\", "/", (string)($_SERVER['SCRIPT_NAME'] ?? ''));
  $residentSegmentPos = strpos($scriptName, '/Resident-End/');
  $baseUrl = '';
  if ($residentSegmentPos !== false) {
    $baseUrl = substr($scriptName, 0, $residentSegmentPos);
  } else {
    $baseUrl = dirname($scriptName);
  }
  $baseUrl = rtrim((string)$baseUrl, '/');
  if ($baseUrl === '.' || $baseUrl === '/') {
    $baseUrl = '';
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    
  <link rel="icon" href="<?= htmlspecialchars((string)$baseUrl, ENT_QUOTES, 'UTF-8') ?>/Images/favicon_sanjose.png?v=20260211">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Dashboard - Barangay San Jose</title>


    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">


    <!-- Bootstrap Icons (for logout icon) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars((string)($baseUrl ?? ''), ENT_QUOTES, 'UTF-8') ?>/CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <link rel="stylesheet" href="<?= htmlspecialchars((string)($baseUrl ?? ''), ENT_QUOTES, 'UTF-8') ?>/CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <link rel="stylesheet" href="<?= htmlspecialchars((string)($baseUrl ?? ''), ENT_QUOTES, 'UTF-8') ?>/CSS-Styles/NavbarFooterStyle.css">
    <script src="<?= htmlspecialchars((string)($baseUrl ?? ''), ENT_QUOTES, 'UTF-8') ?>/JS-Script-Files/modalHandler.js" defer></script>
</head>


<body>
<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . "/../../PhpFiles/General/connection.php";

$current = basename((string)($_SERVER['PHP_SELF'] ?? ''));

function activeLink($page, $current) {
  return $page === $current ? 'active' : '';
}

function isCurrentPage($current, array $pages): bool {
  return in_array($current, $pages, true);
}

function activeGroup($current, array $pages): string {
  return isCurrentPage($current, $pages) ? 'active' : '';
}

$dashboardPages = ['resident_dashboard.php'];
$householdPages = ['resident_household.php'];
$calendarPages = ['resident_calendar.php'];
$certificatePages = [
  'CertificatesLandingPage.php',
  'CohabitationForm.php',
  'FirstTimeJobSeekerForm.php',
  'GoodMoralForm.php',
  'IdentityForm.php',
  'IndigencyForm.php',
  'ResidencyForm.php',
];
$clearancePages = [
  'ClearancesLandingPage.php',
  'BarangayCertificationForm.php',
  'BusinessClearanceForm.php',
  'CommercialForm.php',
  'ElectricalForm.php',
  'OtherPermitsForm.php',
  'ResidentialForm.php',
  'TricycleForm.php',
  'WaterForm.php',
];
$barangayIdPages = [
  'BarangayIdLandingPage.php',
  'BarangayIdForm.php',
  'DigitalId.php',
];
$complaintPages = [
  'ComplaintsLandingPage.php',
  'ComplaintsForm.php',
];
$appointmentPages = [
  'AppointmentsLandingPage.php',
  'AppointmentForm.php',
];
$announcementPages = ['AnnouncementsLandingPage.php'];
$downloadPages = ['Downloads.php'];
$officialReceiptPages = ['OfficialReceipts.php'];
$accountPages = ['resident_profile.php'];

$transactionPages = [
  'resident_activity.php',
  'resident_transactions.php',
  'document_requests.php',
  'appointment_tracker.php',
  'complaint_tracker.php',
];
$isTransactionsActive = in_array($current, $transactionPages, true);

$displayName = "Resident";
$profileImage = $baseUrl . '/Images/Profile-Placeholder.png';
$residentId = '';
$isHeadOfFamily = false;

if (!function_exists('toPublicPath')) {
function toPublicPath($path): ?string {
  global $baseUrl;

  $path = trim((string)$path);
  if ($path === '') {
    return null;
  }

  $normalized = str_replace("\\", "/", $path);
  $normalized = preg_replace('#/+#', '/', $normalized);

  $parts = explode('/', $normalized);
  $cleanParts = [];
  foreach ($parts as $part) {
    if ($part === '' || $part === '.') {
      continue;
    }
    if ($part === '..') {
      array_pop($cleanParts);
      continue;
    }
    $cleanParts[] = $part;
  }
  $normalized = '/' . implode('/', $cleanParts);

  $marker = '/UnifiedFileAttachment/';
  $markerPos = stripos($normalized, $marker);
  if ($markerPos !== false) {
    $public = substr($normalized, $markerPos);
    return $baseUrl . $public;
  }

  $webRoot = realpath(__DIR__ . "/../..");
  if ($webRoot) {
    $rootNorm = str_replace("\\", "/", $webRoot);
    if (strpos($normalized, $rootNorm) === 0) {
      $rel = substr($normalized, strlen($rootNorm));
      if ($rel === '') {
        return null;
      }
      if ($rel[0] !== '/') {
        $rel = '/' . $rel;
      }
      return $baseUrl . $rel;
    }
  }

  return $baseUrl . '/' . ltrim($normalized, '/');
}
}

if (!function_exists('publicPathExists')) {
function publicPathExists(?string $publicPath): bool {
  $publicPath = trim((string)$publicPath);
  if ($publicPath === '') {
    return false;
  }
  if (preg_match('#^https?://#i', $publicPath)) {
    return true;
  }
  global $baseUrl;
  $relative = $publicPath;
  if ($baseUrl !== '' && strpos($relative, $baseUrl) === 0) {
    $relative = substr($relative, strlen($baseUrl));
  }
  $relative = '/' . ltrim((string)$relative, '/');
  $absolute = realpath(__DIR__ . "/../.." . $relative);
  if ($absolute === false) {
    return false;
  }
  return is_file($absolute);
}
}

if (!empty($_SESSION['user_id']) && isset($conn) && $conn instanceof mysqli) {
  $stmt = $conn->prepare("
    SELECT resident_id, firstname, middlename, lastname, suffix, head_of_family
    FROM residentinformationtbl
    WHERE user_id = ?
    LIMIT 1
  ");

  if ($stmt) {
    $stmt->bind_param("s", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
      $row = pii_decrypt_resident_row($row) ?? $row;
      $residentId = $row['resident_id'] ?? '';
      $fullName = trim(
        $row['firstname'] . ' ' .
        ($row['middlename'] ? $row['middlename'][0] . '. ' : '') .
        $row['lastname'] .
        ($row['suffix'] ? ' ' . $row['suffix'] : '')
      );
      if ($fullName !== '') {
        $displayName = $fullName;
      }
      $headOfFamilyRaw = $row['head_of_family'] ?? '';
      $headOfFamilyNormalized = strtolower(trim((string)$headOfFamilyRaw));
      $isHeadOfFamily = in_array($headOfFamilyNormalized, ['yes', 'true', '1', 'y'], true);
    }
    $stmt->close();
  }
}

if ($residentId !== '' && isset($conn) && $conn instanceof mysqli) {
  $stmtPic = $conn->prepare("
    SELECT uf.file_path
    FROM unifiedfileattachmenttbl uf
    INNER JOIN documenttypelookuptbl dt
      ON uf.document_type_id = dt.document_type_id
    LEFT JOIN statuslookuptbl s
      ON uf.status_id_verify = s.status_id
    LEFT JOIN resident_edit_requesttbl rer
      ON uf.source_type = 'ResidentEditRequest'
     AND rer.request_id = uf.source_id
    LEFT JOIN statuslookuptbl rs
      ON rer.status_id = rs.status_id
    WHERE LOWER(dt.document_type_name) = LOWER('2x2 Picture')
      AND (dt.document_category = 'ResidentProfiling' OR dt.document_category = 'EditRequest' OR dt.document_category IS NULL)
      AND (
            (
              uf.source_type IN ('ResidentProfiling', 'RESIDENT_PROFILE')
              AND uf.source_id = ?
              AND (s.status_name = 'Verified' OR s.status_name = 'Approved')
            )
            OR
            (
              uf.source_type = 'ResidentEditRequest'
              AND rer.resident_id = ?
              AND rer.request_type = 'profile'
              AND rs.status_name = 'ApprovedRequest'
            )
      )
    ORDER BY uf.upload_timestamp DESC, uf.attachment_id DESC
    LIMIT 1
  ");
  if ($stmtPic) {
    $stmtPic->bind_param("ss", $residentId, $residentId);
    $stmtPic->execute();
    $stmtPic->bind_result($verifiedPicPath);
    if ($stmtPic->fetch() && !empty($verifiedPicPath)) {
      $publicPath = toPublicPath($verifiedPicPath);
      if (!empty($publicPath) && publicPathExists($publicPath)) {
        $profileImage = $publicPath;
      }
    }
    $stmtPic->close();
  }
}
?>

<aside id="div-sidebarWrapper"
       class="d-flex flex-column flex-shrink-0 p-3 bg-white border-end shadow-sm">

  <div class="sidebar-header">
    <!-- LOGO HEADER (ADMIN-STYLE) -->
    <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Resident-End/resident_dashboard"
       class="sidebar-brand-link link-dark text-decoration-none"
       title="Dashboard Home">
      <img src="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Images/San_Jose_LOGO.jpg"
           alt="Barangay San Jose Logo"
           class="sidebar-brand-logo">
      <span class="sidebar-brand-title logo-name">Barangay San Jose</span>
    </a>

    <button type="button"
            id="btn-sidebarCollapse"
            class="sidebar-edge-toggle"
            aria-label="Collapse navigation"
            aria-pressed="false"
            title="Collapse navigation"
            hidden>
      <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
    </button>
  </div>

  <!-- RESIDENT PROFILE -->
  <div id="div-sidebarProfile" class="text-center mb-4">
    <img
      src="<?= htmlspecialchars($profileImage) ?>"
      alt="Avatar"
      id="img-sidebarAvatar"
      onerror="this.onerror=null;this.src='<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Images/Profile-Placeholder.png';"
      class="rounded-circle mb-2 border shadow-sm"
      width="90"
      height="90"
    >
    <h2 id="txt-sidebarName" class="h6 fw-bold mb-0 sidebar-profile-name"><?= htmlspecialchars($displayName) ?></h2>
  </div>

  <!-- NAV LINKS -->
  <div class="sidebar-body d-flex flex-column flex-grow-1">
    <nav id="nav-sidebarLinks" class="text-start flex-grow-1 overflow-auto">

      <div id="group-navHome" class="mb-3">
        <p class="text-muted small fw-bold mb-1 sidebar-group-title">Home</p>
        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Resident-End/resident_dashboard"
           class="a-sidebarLink <?= activeGroup($current, $dashboardPages) ?>"
           <?= isCurrentPage($current, $dashboardPages) ? 'aria-current="page"' : '' ?>
           title="Dashboard">
          <i class="fa-solid fa-newspaper"></i><span class="sidebar-link-text">Dashboard</span>
        </a>
        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Resident-End/resident_household"
           class="a-sidebarLink <?= activeGroup($current, $householdPages) ?>"
           <?= isCurrentPage($current, $householdPages) ? 'aria-current="page"' : '' ?>
           title="Household Profile">
          <i class="fa-solid fa-house-user"></i><span class="sidebar-link-text">Household Profile</span>
        </a>
        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Resident-End/resident_calendar"
           class="a-sidebarLink <?= activeGroup($current, $calendarPages) ?>"
           <?= isCurrentPage($current, $calendarPages) ? 'aria-current="page"' : '' ?>
           title="Calendar">
          <i class="fa-regular fa-calendar-days"></i><span class="sidebar-link-text">Calendar</span>
        </a>
      </div>

      <div id="group-navServices" class="mb-3">
        <p class="text-muted small fw-bold mb-1 sidebar-group-title">Services</p>
        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Resident-End/Certificates/CertificatesLandingPage"
           class="a-sidebarLink <?= activeGroup($current, $certificatePages) ?>"
           <?= isCurrentPage($current, $certificatePages) ? 'aria-current="page"' : '' ?>
           title="Certificates">
          <i class="fa-solid fa-certificate"></i><span class="sidebar-link-text">Certificates</span>
        </a>
        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Resident-End/Clearances/ClearancesLandingPage"
           class="a-sidebarLink <?= activeGroup($current, $clearancePages) ?>"
           <?= isCurrentPage($current, $clearancePages) ? 'aria-current="page"' : '' ?>
           title="Clearances">
          <i class="fa-solid fa-file-circle-check fa-sm"></i><span class="sidebar-link-text">Clearances</span>
        </a>
        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Resident-End/BarangayId/BarangayIdLandingPage"
           class="a-sidebarLink <?= activeGroup($current, $barangayIdPages) ?>"
           <?= isCurrentPage($current, $barangayIdPages) ? 'aria-current="page"' : '' ?>
           title="Barangay ID">
          <i class="fa-solid fa-id-badge fa-lg"></i><span class="sidebar-link-text">Barangay ID</span>
        </a>
        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Resident-End/Complaints/ComplaintsLandingPage"
           class="a-sidebarLink <?= activeGroup($current, $complaintPages) ?>"
           <?= isCurrentPage($current, $complaintPages) ? 'aria-current="page"' : '' ?>
           title="Complaints">
          <i class="fa-solid fa-comment-dots"></i><span class="sidebar-link-text">Complaints</span>
        </a>
        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Resident-End/Appointments/AppointmentsLandingPage"
           class="a-sidebarLink <?= activeGroup($current, $appointmentPages) ?>"
           <?= isCurrentPage($current, $appointmentPages) ? 'aria-current="page"' : '' ?>
           title="Appointments">
          <i class="fa-solid fa-business-time"></i><span class="sidebar-link-text">Appointments</span>
        </a>
      </div>

      <div id="group-navInfo" class="mb-3">
        <p class="text-muted small fw-bold mb-1 sidebar-group-title">Info</p>
        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Resident-End/Announcements/AnnouncementsLandingPage"
           class="a-sidebarLink <?= activeGroup($current, $announcementPages) ?>"
           <?= isCurrentPage($current, $announcementPages) ? 'aria-current="page"' : '' ?>
           title="Announcements">
          <i class="fa-solid fa-bullhorn"></i><span class="sidebar-link-text">Announcements</span>
        </a>
        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Resident-End/Downloads"
           class="a-sidebarLink <?= activeGroup($current, $downloadPages) ?>"
           <?= isCurrentPage($current, $downloadPages) ? 'aria-current="page"' : '' ?>
           title="Downloads">
          <i class="fa-solid fa-download"></i><span class="sidebar-link-text">Downloads</span>
        </a>
        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Resident-End/OfficialReceipts"
           class="a-sidebarLink <?= activeGroup($current, $officialReceiptPages) ?>"
           <?= isCurrentPage($current, $officialReceiptPages) ? 'aria-current="page"' : '' ?>
           title="Official Receipts">
          <i class="fa-solid fa-receipt"></i><span class="sidebar-link-text">Official Receipts</span>
        </a>
        <button id="btn-sidebarTransactions"
                class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isTransactionsActive ? 'active' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#resident-transactions-collapse"
                data-collapsed-href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Resident-End/resident_transactions"
                aria-expanded="<?= $isTransactionsActive ? 'true' : 'false' ?>"
                title="Transactions">
          <i class="fa-solid fa-clock-rotate-left"></i><span class="sidebar-link-text">Transactions</span>
        </button>
        <div class="collapse <?= $isTransactionsActive ? 'show' : '' ?>" id="resident-transactions-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Resident-End/resident_transactions"
                 class="link-dark rounded <?= activeLink('resident_transactions.php', $current) ?>">
                All Transactions
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Resident-End/document_requests"
                 class="link-dark rounded <?= activeLink('document_requests.php', $current) ?>">
                Document Request
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Resident-End/appointment_tracker"
                 class="link-dark rounded <?= activeLink('appointment_tracker.php', $current) ?>">
                Appointment Schedules
              </a>
            </li>
            <li>
              <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Resident-End/complaint_tracker"
                 class="link-dark rounded <?= activeLink('complaint_tracker.php', $current) ?>">
                Complaint Tracking
              </a>
            </li>
          </ul>
        </div>
      </div>
    </nav>

    <hr>

    <div class="sidebar-actions">
      <a class="account-button btn btn-sm w-100 mb-2 <?= activeGroup($current, $accountPages) ?>"
         href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Resident-End/resident_profile"
         <?= isCurrentPage($current, $accountPages) ? 'aria-current="page"' : '' ?>
         title="Account">
        <i class="fa-solid fa-circle-user"></i><span class="sidebar-action-text">Account</span>
      </a>
      <a class="btn btn-danger btn-sm w-100 logout-link"
         href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/logout"
         title="Logout"
         data-logout-message="Are you sure you want to logout?">
        <i class="bi bi-box-arrow-right me-1"></i><span class="sidebar-action-text">Logout</span>
      </a>
    </div>
  </div>

</aside>

<!-- Logout Confirm Modal -->
<div class="modal fade uniform-modal" id="logoutConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-black">Confirm Logout</h5>
      </div>
      <div class="modal-body">
        <p id="logoutConfirmMessage" class="mb-0">Are you sure you want to logout?</p>
      </div>
      <div class="modal-footer">
        <div class="row g-2 w-100 logout-btn-row">
          <div class="col-6 logout-btn-col">
            <button type="button" class="btn btn-outline-secondary w-100" data-bs-dismiss="modal">Cancel</button>
          </div>
          <div class="col-6 logout-btn-col">
            <a id="logoutConfirmBtn" class="btn btn-danger w-100" href="#">Logout</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener("DOMContentLoaded", () => {
    const sidebarEl = document.getElementById("div-sidebarWrapper");
    const toggleBtn = document.getElementById("btn-sidebarCollapse");
    const transactionsBtn = document.getElementById("btn-sidebarTransactions");
    const ensureMobileHeader = () => {
      const existingHeader = document.getElementById("mobile-header");
      if (existingHeader) {
        return existingHeader;
      }

      const mainDisplayEl = document.getElementById("div-mainDisplay");
      const headerEl = document.createElement("header");
      headerEl.id = "mobile-header";
      headerEl.innerHTML = `
        <div class="d-flex align-items-center px-3 py-2 shadow-sm bg-white">
          <div class="d-flex align-items-center gap-2">
            <button class="btn" id="btn-burger" type="button" aria-label="Open sidebar" onclick="document.getElementById('div-sidebarWrapper')?.classList.toggle('show')">
              <i class="fa-solid fa-bars fa-lg"></i>
            </button>
            <img src="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Images/San_Jose_LOGO.jpg" alt="Logo" style="width:32px;height:32px">
            <span class="logo-name">Barangay San Jose</span>
          </div>
        </div>
      `;

      const hostEl = mainDisplayEl?.parentNode || document.body;
      if (hostEl && mainDisplayEl) {
        hostEl.insertBefore(headerEl, mainDisplayEl);
      } else if (hostEl) {
        hostEl.insertBefore(headerEl, hostEl.firstChild);
      }

      return headerEl;
    };

    const mobileHeaderEl = ensureMobileHeader();
    const desktopQuery = window.matchMedia("(min-width: 769px)");
    const mobileQuery = window.matchMedia("(max-width: 768px)");
    const storageKey = "residentSidebarCollapsed";
    const isCollapsibleViewport = () => {
      return desktopQuery.matches && (!mobileHeaderEl || window.getComputedStyle(mobileHeaderEl).display === "none");
    };

    const syncMobileSidebarState = () => {
      const isOpen = mobileQuery.matches && !!sidebarEl?.classList.contains("show");
      document.body.classList.toggle("resident-sidebar-open", isOpen);
    };

    const applySidebarState = (collapsed) => {
      if (!sidebarEl || !toggleBtn) return;

      const canCollapse = isCollapsibleViewport();
      const shouldCollapse = canCollapse && collapsed;
      sidebarEl.classList.toggle("is-collapsed", shouldCollapse);
      document.body.classList.toggle("resident-sidebar-collapsed", shouldCollapse);
      if (toggleBtn) {
        toggleBtn.hidden = !canCollapse;
      }
      toggleBtn.setAttribute("aria-pressed", shouldCollapse ? "true" : "false");
      toggleBtn.setAttribute("aria-label", shouldCollapse ? "Expand navigation" : "Collapse navigation");
      toggleBtn.title = shouldCollapse ? "Expand navigation" : "Collapse navigation";
    };

    const syncSidebarMode = () => {
      const collapsed = localStorage.getItem(storageKey) === "1";
      applySidebarState(collapsed);
      if (!mobileQuery.matches) {
        sidebarEl?.classList.remove("show");
      }
      syncMobileSidebarState();
    };

    if (toggleBtn) {
      toggleBtn.addEventListener("click", () => {
        if (!isCollapsibleViewport()) return;
        const nextCollapsed = !sidebarEl?.classList.contains("is-collapsed");
        localStorage.setItem(storageKey, nextCollapsed ? "1" : "0");
        applySidebarState(nextCollapsed);
      });
    }

    if (transactionsBtn) {
      transactionsBtn.addEventListener("click", (event) => {
        if (!isCollapsibleViewport() || !sidebarEl?.classList.contains("is-collapsed")) return;
        const fallbackHref = transactionsBtn.dataset.collapsedHref || "";
        if (fallbackHref === "") return;
        event.preventDefault();
        window.location.href = fallbackHref;
      });
    }

    document.addEventListener("click", (event) => {
      if (!mobileQuery.matches || !sidebarEl?.classList.contains("show")) {
        return;
      }

      const target = event.target;
      if (!(target instanceof Node)) {
        return;
      }

      if (sidebarEl.contains(target) || (target instanceof Element && target.closest("#btn-burger"))) {
        return;
      }

      sidebarEl.classList.remove("show");
      syncMobileSidebarState();
    });

    document.addEventListener("keydown", (event) => {
      if (event.key !== "Escape" || !mobileQuery.matches || !sidebarEl?.classList.contains("show")) {
        return;
      }

      sidebarEl.classList.remove("show");
      syncMobileSidebarState();
    });

    if (sidebarEl && typeof MutationObserver !== "undefined") {
      const observer = new MutationObserver(syncMobileSidebarState);
      observer.observe(sidebarEl, { attributes: true, attributeFilter: ["class"] });
    }

    if (typeof desktopQuery.addEventListener === "function") {
      desktopQuery.addEventListener("change", syncSidebarMode);
    } else if (typeof desktopQuery.addListener === "function") {
      desktopQuery.addListener(syncSidebarMode);
    }

    window.addEventListener("resize", syncSidebarMode);
    syncSidebarMode();
  });
</script>

<script>
  document.addEventListener("DOMContentLoaded", () => {
    const prefetchedUrls = new Set();
    const sameOrigin = window.location.origin;
    const currentUrl = new URL(window.location.href);
    currentUrl.hash = "";

    const toPrefetchableUrl = (href) => {
      try {
        const url = new URL(String(href || ""), window.location.href);
        if (url.origin !== sameOrigin) {
          return null;
        }
        if (url.protocol !== "http:" && url.protocol !== "https:") {
          return null;
        }
        url.hash = "";
        if (url.toString() === currentUrl.toString()) {
          return null;
        }
        return url.toString();
      } catch (error) {
        return null;
      }
    };

    const warmUrl = (href) => {
      const normalizedUrl = toPrefetchableUrl(href);
      if (!normalizedUrl || prefetchedUrls.has(normalizedUrl)) {
        return;
      }

      prefetchedUrls.add(normalizedUrl);

      const prefetchLink = document.createElement("link");
      prefetchLink.rel = "prefetch";
      prefetchLink.as = "document";
      prefetchLink.href = normalizedUrl;
      document.head.appendChild(prefetchLink);
    };

    const navLinks = Array.from(document.querySelectorAll(
      "#nav-sidebarLinks a[href], #resident-transactions-collapse a[href], .sidebar-actions a[href]"
    )).filter((link) => {
      if (!link || !link.href) {
        return false;
      }
      const href = String(link.getAttribute("href") || "");
      return href !== "" && href !== "#";
    });

    navLinks.forEach((link) => {
      const warm = () => warmUrl(link.href);
      link.addEventListener("mouseenter", warm, { passive: true });
      link.addEventListener("focus", warm, { passive: true });
      link.addEventListener("touchstart", warm, { passive: true });
    });

    const backgroundTargets = navLinks
      .map((link) => link.href)
      .filter((href, index, list) => list.indexOf(href) === index)
      .slice(0, 8);

    const warmInBackground = () => {
      backgroundTargets.forEach((href, index) => {
        window.setTimeout(() => warmUrl(href), 140 * index);
      });
    };

    if (typeof window.requestIdleCallback === "function") {
      window.requestIdleCallback(warmInBackground, { timeout: 1500 });
    } else {
      window.setTimeout(warmInBackground, 700);
    }
  });
</script>

<script>
  document.addEventListener("DOMContentLoaded", () => {
    const links = document.querySelectorAll(".logout-link");
    if (!links.length) return;

    const sidebarEl = document.getElementById("div-sidebarWrapper");
    const modalEl = document.getElementById("logoutConfirmModal");
    const msgEl = document.getElementById("logoutConfirmMessage");
    const btnEl = document.getElementById("logoutConfirmBtn");
    if (!modalEl || !msgEl || !btnEl) return;

    const modal = new bootstrap.Modal(modalEl);
    links.forEach((link) => {
      link.addEventListener("click", (e) => {
        e.preventDefault();
        sidebarEl?.classList.remove("show");
        msgEl.textContent = link.dataset.logoutMessage || "Are you sure you want to logout?";
        btnEl.href = link.href;
        modal.show();
      });
    });
  });
</script>

<script>
  document.addEventListener("DOMContentLoaded", () => {
    const sidebarImg = document.getElementById("img-sidebarAvatar");
    const profileImg = document.getElementById("img-profileAvatar");
    if (!sidebarImg && !profileImg) return;

    const POLL_INTERVAL_MS = 5 * 60 * 1000;
    let lastBaseUrl = "";
    let pollTimer = null;
    const getBaseUrl = (url) => (url || "").split("?")[0];
    const BASE_URL = <?= json_encode($baseUrl, JSON_UNESCAPED_SLASHES) ?>;
    const PLACEHOLDER_PATH = `${BASE_URL}/Images/Profile-Placeholder.png`;
    const isPlaceholder = (url) => getBaseUrl(url).includes(PLACEHOLDER_PATH);

    const updateImages = (url) => {
      if (!url) return;
      const baseUrl = getBaseUrl(url);
      if (baseUrl === "" || baseUrl === lastBaseUrl) return;

      // Never downgrade a currently loaded real image to placeholder during polling.
      const currentSidebar = getBaseUrl(sidebarImg?.src || "");
      const currentProfile = getBaseUrl(profileImg?.src || "");
      const hasRealLoaded = (currentSidebar && !isPlaceholder(currentSidebar)) || (currentProfile && !isPlaceholder(currentProfile));
      if (isPlaceholder(baseUrl) && hasRealLoaded) {
        return;
      }

      lastBaseUrl = baseUrl;
      const cacheBusted = `${baseUrl}?v=${Date.now()}`;
      if (sidebarImg) sidebarImg.src = cacheBusted;
      if (profileImg) profileImg.src = cacheBusted;
    };

    const poll = async () => {
      try {
        const res = await fetch(`${BASE_URL}/PhpFiles/Resident-End/getVerifiedProfileImage.php`, {
          headers: { "Accept": "application/json" }
        });
        if (!res.ok) return;
        const data = await res.json();
        if (data && data.success && data.profile_image) {
          updateImages(data.profile_image);
        }
      } catch (e) {
        // ignore polling errors
      }
    };

    const startPolling = () => {
      if (pollTimer) {
        clearInterval(pollTimer);
      }
      pollTimer = window.setInterval(() => {
        if (document.hidden) return;
        poll();
      }, POLL_INTERVAL_MS);
    };

    poll();
    startPolling();

    document.addEventListener("visibilitychange", () => {
      if (!document.hidden) {
        poll();
      }
    });
  });
</script>

<script src="<?= htmlspecialchars(appUrl('/JS-Script-Files/websitePreferences.js'), ENT_QUOTES, 'UTF-8') ?>" data-endpoint="<?= htmlspecialchars(appUrl('/PhpFiles/GET/getWebsitePreferences.php'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
