<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    
  <link rel="icon" href="/Images/favicon_sanjose.png?v=20260211">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Dashboard - Barangay San Jose</title>


    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">


    <!-- Bootstrap Icons (for logout icon) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/BarangaySanJose/CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <link rel="stylesheet" href="/BarangaySanJose/CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <link rel="stylesheet" href="/BarangaySanJose/CSS-Styles/NavbarFooterStyle.css">
</head>


<body>
<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . "/../../PhpFiles/General/connection.php";

$current = basename($_SERVER['PHP_SELF']);

function activeLink($page, $current) {
  return $page === $current ? 'active' : '';
}

$displayName = "Resident";
$baseUrl = '/BarangaySanJose';
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
  $relative = preg_replace('#^/BarangaySanJose#', '', $publicPath);
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
    INNER JOIN statuslookuptbl s
      ON uf.status_id_verify = s.status_id
    WHERE uf.source_type IN ('ResidentProfiling', 'RESIDENT_PROFILE')
      AND uf.source_id = ?
      AND LOWER(dt.document_type_name) = LOWER('2x2 Picture')
      AND (dt.document_category = 'ResidentProfiling' OR dt.document_category = 'EditRequest' OR dt.document_category IS NULL)
      AND (s.status_name = 'Verified' OR s.status_name = 'Approved')
    ORDER BY uf.upload_timestamp DESC, uf.attachment_id DESC
    LIMIT 1
  ");
  if ($stmtPic) {
    $stmtPic->bind_param("s", $residentId);
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

  <!-- LOGO HEADER (ADMIN-STYLE) -->
  <a href="/BarangaySanJose/Resident-End/AdminDashboard.php" class="d-flex align-items-center pb-3 mb-3 link-dark text-decoration-none border-bottom">
    <img src="/BarangaySanJose/Images/San_Jose_LOGO.jpg" class="me-2" style="width: 32px; height: 32px;">
    <span class="fs-5 fw-semibold logo-name">Barangay San Jose</span>
  </a>

  <!-- RESIDENT PROFILE -->
  <div id="div-sidebarProfile" class="text-center mb-4">
    <img
      src="<?= htmlspecialchars($profileImage) ?>"
      alt="Avatar"
      id="img-sidebarAvatar"
      onerror="this.onerror=null;this.src='/BarangaySanJose/Images/Profile-Placeholder.png';"
      class="rounded-circle mb-2 border shadow-sm"
      width="90"
      height="90"
    >
    <h2 id="txt-sidebarName" class="h6 fw-bold mb-0"><?= htmlspecialchars($displayName) ?></h2>
  </div>

  <!-- NAV LINKS -->
  <div class="sidebar-body d-flex flex-column flex-grow-1">
    <nav id="nav-sidebarLinks" class="text-start flex-grow-1 overflow-auto">

      <div id="group-navHome" class="mb-3">
        <p class="text-muted small fw-bold mb-1">Home</p>
        <a href="/BarangaySanJose/Resident-End/resident_dashboard.php"
           class="a-sidebarLink <?= activeLink('resident_dashboard.php', $current) ?>">
          <i class="fa-solid fa-newspaper"></i>Dashboard
        </a>
      </div>

      <div id="group-navServices" class="mb-3">
        <p class="text-muted small fw-bold mb-1">Services</p>
        <a href="/BarangaySanJose/Resident-End/Certificates/CertificatesLandingPage.php"
           class="a-sidebarLink <?= activeLink('resident_certificates.php', $current) ?>">
          <i class="fa-solid fa-certificate"></i>Certificates
        </a>
        <a href="/BarangaySanJose/Resident-End/Clearances/ClearancesLandingPage.php"
           class="a-sidebarLink <?= activeLink('resident_clearances.php', $current) ?>">
          <i class="fa-solid fa-file-circle-check fa-sm"></i>Clearances
        </a>
        <a href="/BarangaySanJose/Resident-End/BarangayId/BarangayIdLandingPage.php"
           class="a-sidebarLink <?= (in_array($current, ['BarangayIdLandingPage.php', 'BarangayIdForm.php'], true) ? 'active' : '') ?>">
          <i class="fa-solid fa-id-badge fa-lg"></i>Barangay ID
        </a>
        <a href="/BarangaySanJose/Resident-End/Complaints/ComplaintsLandingPage.php"
           class="a-sidebarLink <?= (in_array($current, ['ComplaintsLandingPage.php', 'ComplaintsForm.php'], true) ? 'active' : '') ?>">
          <i class="fa-solid fa-comment-dots"></i>Complaints
        </a>
        <a href="/BarangaySanJose/Resident-End/Appointments/AppointmentsLandingPage.php"
           class="a-sidebarLink <?= (in_array($current, ['AppointmentsLandingPage.php', 'AppointmentForm.php'], true) ? 'active' : '') ?>">
          <i class="fa-regular fa-calendar-days"></i>Appointments
        </a>
      </div>

      <div id="group-navInfo" class="mb-3">
        <p class="text-muted small fw-bold mb-1">Info</p>
        <a href="/BarangaySanJose/Resident-End/resident_certificates.php"
           class="a-sidebarLink <?= activeLink('resident_certificates.php', $current) ?>">
          <i class="fa-solid fa-bullhorn"></i>Announcements
        </a>
        <a href="/BarangaySanJose/Resident-End/resident_transactions.php"
           class="a-sidebarLink <?= activeLink('resident_transactions.php', $current) ?>">
          <i class="fa-solid fa-clock-rotate-left"></i>Transactions
        </a>
      </div>
    </nav>

    <hr>

    <div class="sidebar-actions">
      <a class="account-button btn btn-sm w-100 mb-2"
         href="/BarangaySanJose/Resident-End/resident_profile.php">
        <i class="fa-solid fa-circle-user"></i> Account
      </a>
      <a class="btn btn-danger btn-sm w-100 logout-link"
         href="/BarangaySanJose/PhpFiles/Login/logout.php"
         data-logout-message="Are you sure you want to logout?">
        <i class="bi bi-box-arrow-right me-1"></i> Logout
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
    const links = document.querySelectorAll(".logout-link");
    if (!links.length) return;

    const modalEl = document.getElementById("logoutConfirmModal");
    const msgEl = document.getElementById("logoutConfirmMessage");
    const btnEl = document.getElementById("logoutConfirmBtn");
    if (!modalEl || !msgEl || !btnEl) return;

    const modal = new bootstrap.Modal(modalEl);
    links.forEach((link) => {
      link.addEventListener("click", (e) => {
        e.preventDefault();
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

    let lastBaseUrl = "";
    const getBaseUrl = (url) => (url || "").split("?")[0];
    const PLACEHOLDER_PATH = "/BarangaySanJose/Images/Profile-Placeholder.png";
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
        const res = await fetch("/BarangaySanJose/PhpFiles/Resident-End/getVerifiedProfileImage.php", {
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

    poll();
    setInterval(poll, 15000);
  });
</script>

</body>
</html>
