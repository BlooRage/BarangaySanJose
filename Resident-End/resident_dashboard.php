<?php
$allowUnregistered = false;
require_once __DIR__ . "/includes/resident_access_guard.php";

$isResidentNotVerified = false;
$showNotVerifiedModal = false;

if (isset($conn) && $conn instanceof mysqli) {
  $statusName = '';
  $stmt = $conn->prepare("
    SELECT s.status_name
    FROM residentinformationtbl r
    LEFT JOIN statuslookuptbl s ON r.status_id_resident = s.status_id
    WHERE r.user_id = ?
    LIMIT 1
  ");

  if ($stmt) {
    $stmt->bind_param("s", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($statusName);
    if ($stmt->fetch()) {
      $statusKey = strtolower((string)preg_replace('/[^a-z0-9]/i', '', (string)$statusName));
      $isResidentNotVerified = ($statusKey === 'notverified');
    }
    $stmt->close();
  }
}

if (!empty($_SESSION['show_not_verified_modal']) && $isResidentNotVerified) {
  $showNotVerifiedModal = true;
}

if (isset($_SESSION['show_not_verified_modal'])) {
  unset($_SESSION['show_not_verified_modal']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  
  <link rel="icon" href="/Images/favicon_sanjose.png?v=20260211">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resident Dashboard - Barangay San Jose</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/residentDashboard.css">
  <style>
    .verify-cta-card {
      position: relative;
    }
    .verify-cta-close {
      position: absolute;
      top: 8px;
      right: 10px;
      width: 28px;
      height: 28px;
      border: none;
      border-radius: 50%;
      background: rgba(0, 0, 0, 0.15);
      color: #fff;
      font-size: 18px;
      line-height: 1;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.2s ease;
    }
    .verify-cta-card:hover .verify-cta-close {
      opacity: 1;
      pointer-events: auto;
    }
  </style>
</head>

<body>

  <div class="d-flex" style="min-height: 100vh;">

    <?php include 'includes/resident_sidebar.php'; ?>

    <header id="mobile-header">
      <div class="d-flex align-items-center px-3 py-2 shadow-sm bg-white">
        <div class="d-flex align-items-center gap-2">
          <button class="btn" id="btn-burger" type="button">
            <i class="fa-solid fa-bars fa-lg"></i>
          </button>
          <img src="../Images/San_Jose_LOGO.jpg" alt="Logo" style="width:32px;height:32px">
          <span class="logo-name">Barangay San Jose</span>
        </div>
      </div>
    </header>

    <main id="div-mainDisplay" class="flex-grow-1 p-4 p-md-5 bg-light">

      <div id="div-welcomeBanner" class="rounded-4 overflow-hidden mb-3 shadow-sm border-orange-thin">
        <div id="div-bannerHeader" class="bg-orange text-center py-3">
          <h3 class="text-white fw-bold mb-0">WELCOME, RESIDENTS OF BARANGAY SAN JOSE!</h3>
        </div>
        <div id="div-bannerBody" class="bg-white p-5 text-center">
          <p id="txt-bannerLorem" class="text-muted mb-0">
            LOREM IPSUM DOLOR SIT AMET, CONSECTETUR ADIPISCING ELIT. SED DO EIUSMOD TEMPOR INCIDIDUNT UT LABORE ET DOLORE MAGNA ALIQUA.
          </p>
        </div>
      </div>

      <?php if ($isResidentNotVerified): ?>
        <div class="verify-cta-card rounded-4 overflow-hidden shadow-sm border-orange-thin bg-white mb-4" id="verifyCtaCard">
          <button type="button" class="verify-cta-close" id="verifyCtaCloseBtn" aria-label="Close">×</button>
          <div class="bg-orange text-center py-2">
            <h3 class="text-white fw-bold mb-0">ACCOUNT VERIFICATION</h3>
          </div>
          <div class="p-3 p-md-4 text-center">
            <p class="text-muted mb-2">Want to access most modules? Verify now.</p>
            <a href="DocumentUpload.php" class="btn btn-primary px-4">Verify Now</a>
          </div>
        </div>
      <?php endif; ?>

      <h2 id="txt-sectionTitle" class="fw-bold border-bottom pb-2 mb-4">DASHBOARD</h2>

      <div id="div-serviceGrid" class="row service-grid justify-content-center gx-4">

        <div class="col-12 col-md-4 col-lg-3">
          <div id="card-serviceRequest-certificates"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm"
               onclick="location.href='ApplicationsLandingPage.php'">
            <i class="fa-solid fa-file-lines fa-2xl mb-3"></i><br>
            <span class="fw-bold small">CERTIFICATE REQUEST</span>
          </div>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <div id="card-serviceRequest-clearances"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm"
               onclick="location.href='#'">
            <i class="fa-solid fa-clipboard-check fa-2xl mb-3"></i><br>
            <span class="fw-bold small">CLEARANCES</span>
          </div>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <div id="card-serviceRequest-brgyId"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm"
               onclick="location.href='#'">
            <i class="fa-solid fa-id-card fa-2xl mb-3"></i><br>
            <span class="fw-bold small">BARANGAY ID</span>
          </div>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <div id="card-serviceRequest-appointments"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm"
               onclick="location.href='#'">
            <i class="fa-solid fa-calendar-check fa-2xl mb-3"></i><br>
            <span class="fw-bold small">APPOINTMENTS</span>
          </div>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <div id="card-serviceRequest-announcements"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm"
               onclick="location.href='#'">
            <i class="fa-solid fa-bullhorn fa-2xl mb-3"></i><br>
            <span class="fw-bold small">ANNOUNCEMENTS</span>
          </div>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <div id="card-serviceRequest-transactions"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm"
               onclick="location.href='resident_transactions.php'">
            <i class="fa-solid fa-money-check-dollar fa-2xl mb-3"></i><br>
            <span class="fw-bold small">TRANSACTIONS</span>
          </div>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <div id="card-serviceRequest-complaints"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm"
               onclick="location.href='#'">
            <i class="fa-solid fa-comment-dots fa-2xl mb-3"></i><br>
            <span class="fw-bold small">COMPLAINTS</span>
          </div>
        </div>

        <div class="col-6 col-md-4 col-lg-3">
          <div id="card-serviceRequest-profile"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm"
               onclick="location.href='resident_profile.php'">
            <i class="fa-solid fa-user-circle fa-2xl mb-3"></i><br>
            <span class="fw-bold small">MY PROFILE</span>
          </div>
        </div>

      </div>

    </main>
  </div>

  <div class="modal fade" id="notVerifiedResidentModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-0 pb-0 bg-orange">
          <h5 class="modal-title w-100 text-center text-white">Resident Verification</h5>
        </div>
        <div class="modal-body text-center">
          You are not yet a verified resident, which means you cannot access most modules.
        </div>
        <div class="modal-footer border-0 pt-0 d-flex gap-2">
          <a href="DocumentUpload.php" class="btn btn-primary flex-fill">Verify Now</a>
          <button type="button" class="btn btn-secondary flex-fill" data-bs-dismiss="modal">Later</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    const burgerBtn = document.getElementById("btn-burger");
    const sidebar = document.getElementById("div-sidebarWrapper");

    if (burgerBtn && sidebar) {
      burgerBtn.addEventListener("click", () => {
        sidebar.classList.toggle("show");
      });
    }

    document.addEventListener("DOMContentLoaded", () => {
      const shouldShow = <?= $showNotVerifiedModal ? 'true' : 'false' ?>;
      if (!shouldShow || !window.bootstrap?.Modal) return;

      const modalEl = document.getElementById("notVerifiedResidentModal");
      if (!modalEl) return;

      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    });

    const verifyCtaCard = document.getElementById("verifyCtaCard");
    const verifyCtaCloseBtn = document.getElementById("verifyCtaCloseBtn");
    if (verifyCtaCard && verifyCtaCloseBtn) {
      verifyCtaCloseBtn.addEventListener("click", () => {
        verifyCtaCard.classList.add("d-none");
      });
    }
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
