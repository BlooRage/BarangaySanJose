<?php
$allowUnregistered = false;
require_once __DIR__ . "/includes/resident_access_guard.php";
require_once __DIR__ . "/../PhpFiles/Admin-End/contentStore.php";
require_once __DIR__ . "/../PhpFiles/Admin-End/announcementAudience.php";

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

$residentAnnouncements = [];
$announcementItems = announcements_load_all();
$viewerContext = ann_audience_fetch_resident_context($conn, (string)($_SESSION['user_id'] ?? ''));
usort($announcementItems, static function (array $a, array $b): int {
  $aDate = (string)($a['publish_date'] ?? $a['created_at'] ?? '');
  $bDate = (string)($b['publish_date'] ?? $b['created_at'] ?? '');
  $aTs = strtotime($aDate) ?: 0;
  $bTs = strtotime($bDate) ?: 0;
  return $bTs <=> $aTs;
});
foreach ($announcementItems as $item) {
  $channels = array_values(array_filter((array)($item['channels'] ?? []), function ($ch) {
    return in_array((string)$ch, ['website', 'public', 'public_news', 'sms', 'email'], true);
  }));
  $status = strtolower((string)($item['status'] ?? 'draft'));
  if ($status !== 'approved' || !in_array('website', $channels, true)) {
    continue;
  }
  if (!ann_audience_matches_viewer($item, $viewerContext)) {
    continue;
  }

  $rawPosted = (string)($item['publish_date'] ?? '');
  if ($rawPosted === '' || $rawPosted === '-') {
    $rawPosted = (string)($item['created_at'] ?? '');
  }
  $postedDate = '-';
  $postedDateIso = '';
  $ts = strtotime($rawPosted);
  if ($ts !== false) {
    $postedDate = date('M d, Y', $ts);
    $postedDateIso = date('Y-m-d', $ts);
  }

  $title = trim((string)(($item['public_title'] ?? '') !== '' ? $item['public_title'] : ($item['title'] ?? '')));
  $contentHtml = (string)(($item['public_content_html'] ?? '') !== '' ? $item['public_content_html'] : ($item['content_html'] ?? ''));

  $residentAnnouncements[] = [
    'title' => $title !== '' ? $title : 'Untitled Announcement',
    'content_html' => trim($contentHtml) !== '' ? $contentHtml : '<p>Tap to view the latest account-page announcement.</p>',
    'posted_date' => $postedDate,
    'posted_date_iso' => $postedDateIso
  ];
}

$dashboardCards = [
  [
    'id' => 'certificates',
    'href' => 'Certificates/CertificatesLandingPage',
    'label' => 'Certificate Request',
    'subtext' => 'Quick access to resident requests',
    'icon' => 'fa-solid fa-file-lines',
  ],
  [
    'id' => 'clearances',
    'href' => 'Clearances/ClearancesLandingPage',
    'label' => 'Clearance Request',
    'subtext' => 'Manage barangay clearance requests',
    'icon' => 'fa-solid fa-clipboard-check',
  ],
  [
    'id' => 'barangay-id',
    'href' => 'BarangayId/BarangayIdLandingPage',
    'label' => 'Barangay ID',
    'subtext' => 'Access ID application and status',
    'icon' => 'fa-solid fa-id-card',
  ],
  [
    'id' => 'complaints',
    'href' => 'Complaints/ComplaintsLandingPage',
    'label' => 'Complaints',
    'subtext' => 'Submit concerns and follow-ups',
    'icon' => 'fa-solid fa-comment-dots',
  ],
  [
    'id' => 'appointments',
    'href' => 'Appointments/AppointmentsLandingPage',
    'label' => 'Appointments',
    'subtext' => 'View schedules and set visits',
    'icon' => 'fa-solid fa-calendar-check',
  ],
  [
    'id' => 'announcements',
    'href' => 'Announcements/AnnouncementsLandingPage',
    'label' => 'Announcements',
    'subtext' => 'Read community notices and updates',
    'icon' => 'fa-solid fa-bullhorn',
  ],
  [
    'id' => 'downloads',
    'href' => 'Downloads',
    'label' => 'Downloads',
    'subtext' => 'Get your generated files and records',
    'icon' => 'fa-solid fa-download',
  ],
  [
    'id' => 'transactions',
    'href' => 'resident_transactions',
    'label' => 'Transactions',
    'subtext' => 'Review payments and activity',
    'icon' => 'fa-solid fa-money-check-dollar',
  ],
  [
    'id' => 'official-receipts',
    'href' => 'OfficialReceipts',
    'label' => 'Official Receipts',
    'subtext' => 'View issued barangay payment receipts',
    'icon' => 'fa-solid fa-receipt',
  ],
  [
    'id' => 'calendar',
    'href' => 'resident_calendar',
    'label' => 'Calendar',
    'subtext' => 'See resident events and schedules',
    'icon' => 'fa-regular fa-calendar-days',
  ],
  [
    'id' => 'household-profile',
    'href' => 'resident_household',
    'label' => 'Household Profile',
    'subtext' => 'Manage your household and family record',
    'icon' => 'fa-solid fa-house-user',
  ],
  [
    'id' => 'account-profile',
    'href' => 'resident_profile',
    'label' => 'Account Profile',
    'subtext' => 'Update account and resident information',
    'icon' => 'fa-solid fa-user-circle',
  ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  
  <link rel="icon" href="../Images/favicon_sanjose.png?v=20260211">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resident Dashboard - Barangay San Jose</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/residentDashboard.css">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/ContentManagementStyle.css?v=20260815-modal-layer-1">
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

    <?php include __DIR__ . '/includes/resident_sidebar.php'; ?>

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

      <div id="div-welcomeBanner" class="rounded-4 overflow-hidden mb-4 shadow-sm border-orange-thin">
        <div id="div-bannerHeader" class="bg-orange text-center py-3">
          <h3 class="text-white fw-bold mb-0">WELCOME, RESIDENTS OF BARANGAY SAN JOSE!</h3>
        </div>
        <div id="div-bannerBody" class="bg-white p-3 p-md-4 text-center">
          <p id="txt-bannerLorem" class="text-muted mb-0">
            Welcome to your official Barangay San Jose resident dashboard. Stay updated with barangay announcements, receive important community reminders, and quickly access services such as certificates, clearances, barangay ID requests, appointments, complaints, and transactions. Use this page to keep track of your account activity and stay informed about the latest barangay updates.
          </p>
        </div>
      </div>

      <?php if ($residentAnnouncements): ?>
        <section class="mb-4">
          <div class="dashboard-announcement-panel">
            <div class="dashboard-announcements-stack" id="dashboardAnnouncementsStack">
              <?php foreach ($residentAnnouncements as $index => $announcement): ?>
                <div class="dashboard-announcement-banner rounded-4 overflow-hidden shadow-sm border-orange-thin position-relative mb-3" id="dashboardAnnouncementCard<?= (int)$index ?>" onclick="location.href='Announcements/AnnouncementsLandingPage'" role="button" tabindex="0">
                  <button type="button" class="dashboard-announcement-close" data-announcement-close="dashboardAnnouncementCard<?= (int)$index ?>" aria-label="Close">×</button>
                  <div class="bg-orange text-center py-3">
                    <h3 class="text-white fw-bold mb-0"><?= htmlspecialchars($announcement['title']) ?></h3>
                  </div>
                  <div class="bg-white p-3 p-md-4 dashboard-announcement-body">
                    <div class="dashboard-announcement-preview"><?= $announcement['content_html'] ?></div>
                    <div class="dashboard-announcement-footer">
                      <div class="dashboard-announcement-posted">Posted: <?= htmlspecialchars($announcement['posted_date']) ?></div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </section>
      <?php endif; ?>

      <?php if ($isResidentNotVerified): ?>
        <div class="verify-cta-card rounded-4 overflow-hidden shadow-sm border-orange-thin bg-white mb-4" id="verifyCtaCard">
          <button type="button" class="verify-cta-close" id="verifyCtaCloseBtn" aria-label="Close">×</button>
          <div class="bg-orange text-center py-2">
            <h3 class="text-white fw-bold mb-0">ACCOUNT VERIFICATION</h3>
          </div>
          <div class="verify-cta-body p-3 p-md-4 text-center">
            <p class="text-muted mb-2">Want to access most modules? Verify now.</p>
            <a href="DocumentUpload" class="btn btn-primary px-4">Verify Now</a>
          </div>
        </div>
      <?php endif; ?>

      <h2 id="txt-sectionTitle" class="fw-bold border-bottom pb-2 mb-4">DASHBOARD</h2>

      <div id="div-serviceGrid" class="row service-grid justify-content-center">
        <?php foreach ($dashboardCards as $card): ?>
        <div class="col-12 col-md-6 col-lg-3">
          <a id="card-serviceRequest-<?= htmlspecialchars($card['id'], ENT_QUOTES, 'UTF-8') ?>"
             class="card-action"
             href="<?= htmlspecialchars($card['href'], ENT_QUOTES, 'UTF-8') ?>"
             aria-label="Open <?= htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8') ?>">
            <span class="card-action__icon" aria-hidden="true"><i class="<?= htmlspecialchars($card['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
            <span class="card-action__title"><?= htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="card-action__subtext"><?= htmlspecialchars($card['subtext'], ENT_QUOTES, 'UTF-8') ?></span>
          </a>
        </div>
        <?php endforeach; ?>

      </div>

    </main>
  </div>

  <div class="modal fade" id="notVerifiedResidentModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-0 pb-0 bg-white">
          <h5 class="modal-title w-100 text-center text-dark">Resident Verification</h5>
        </div>
        <hr class="my-0">
        <div class="modal-body text-center">
          You are not yet a verified resident, which means you cannot access most modules.
        </div>
        <div class="modal-footer border-0 pt-0 d-flex gap-2">
          <a href="DocumentUpload" class="btn btn-primary flex-fill">Verify Now</a>
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

    document.querySelectorAll("[data-announcement-close]").forEach((closeBtn) => {
      closeBtn.addEventListener("click", (event) => {
        event.stopPropagation();
        const cardId = closeBtn.getAttribute("data-announcement-close");
        if (!cardId) return;
        const card = document.getElementById(cardId);
        if (card) {
          card.classList.add("d-none");
        }
      });
    });

  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
