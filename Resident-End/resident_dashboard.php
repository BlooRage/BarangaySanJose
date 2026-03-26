<?php
$allowUnregistered = false;
require_once __DIR__ . "/includes/resident_access_guard.php";
require_once __DIR__ . "/../PhpFiles/Admin-End/contentStore.php";
require_once __DIR__ . "/../PhpFiles/Admin-End/announcementAudience.php";

$isResidentVerified = false;
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
      $isResidentVerified = in_array($statusKey, ['verifiedresident', 'verified', 'approved'], true);
    }
    $stmt->close();
  }
}

if (!empty($_SESSION['show_not_verified_modal']) && !$isResidentVerified) {
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
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/ContentManagementStyle.css">
  <style>
    .dashboard-main-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.45fr) minmax(300px, 0.78fr);
      gap: 1.25rem;
      align-items: start;
    }
    .dashboard-announcement-panel,
    .dashboard-calendar-panel {
      min-width: 0;
    }
    .dashboard-calendar-card {
      background: #fff;
      border: 1px solid #f1dac4;
      border-radius: 16px;
      box-shadow: 0 14px 34px rgba(15, 23, 42, 0.06);
      overflow: hidden;
      position: sticky;
      top: 1.25rem;
    }
    .dashboard-calendar-head {
      padding: 1rem 1.1rem 0.9rem;
      background: linear-gradient(135deg, #fff8ef 0%, #fff1df 100%);
      border-bottom: 1px solid #f5dfc8;
    }
    .dashboard-calendar-kicker {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      font-size: 0.76rem;
      font-weight: 800;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #a35300;
      margin-bottom: 0.45rem;
    }
    .dashboard-calendar-title {
      font-family: 'Charis SIL Bold', serif;
      font-size: 1.45rem;
      color: #7c3f00;
      margin: 0 0 0.25rem;
    }
    .dashboard-calendar-copy {
      color: #6b7280;
      margin: 0;
      font-size: 0.94rem;
      line-height: 1.5;
    }
    .dashboard-calendar-body {
      padding: 1rem 1.1rem 1.1rem;
    }
    .dashboard-calendar-toolbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      margin-bottom: 0.85rem;
    }
    .dashboard-calendar-month {
      font-weight: 800;
      color: #1f2937;
      font-size: 1rem;
    }
    .dashboard-calendar-nav {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      border: 1px solid #e7d8c8;
      background: #fffaf4;
      color: #8a5308;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .dashboard-calendar-nav:hover {
      background: #fff2e4;
      border-color: #de710c;
      color: #de710c;
    }
    .dashboard-calendar-weekdays,
    .dashboard-calendar-grid {
      display: grid;
      grid-template-columns: repeat(7, minmax(0, 1fr));
      gap: 0.35rem;
    }
    .dashboard-calendar-weekdays {
      margin-bottom: 0.45rem;
    }
    .dashboard-calendar-weekdays span {
      text-align: center;
      font-size: 0.76rem;
      font-weight: 700;
      color: #9aa3af;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .dashboard-calendar-day {
      border: 0;
      min-height: 42px;
      border-radius: 10px;
      background: #fff;
      color: #1f2937;
      font-weight: 700;
      position: relative;
      transition: background-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
    }
    .dashboard-calendar-day:hover {
      background: #fff3e4;
      color: #a35300;
      transform: translateY(-1px);
    }
    .dashboard-calendar-day.is-outside {
      color: #c1c7d0;
      background: #fafafa;
    }
    .dashboard-calendar-day.is-selected {
      background: #de710c;
      color: #fff;
      box-shadow: 0 10px 20px rgba(222, 113, 12, 0.22);
    }
    .dashboard-calendar-day.has-announcements::after {
      content: "";
      position: absolute;
      bottom: 7px;
      left: 50%;
      transform: translateX(-50%);
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: #de710c;
    }
    .dashboard-calendar-day.is-selected.has-announcements::after {
      background: #fff;
    }
    .dashboard-calendar-footer {
      display: flex;
      flex-wrap: wrap;
      gap: 0.65rem;
      align-items: center;
      justify-content: space-between;
      margin-top: 1rem;
      padding-top: 0.95rem;
      border-top: 1px solid #f0ede8;
    }
    .dashboard-calendar-selection {
      font-size: 0.9rem;
      color: #5f6b7a;
      margin: 0;
    }
    .dashboard-calendar-reset {
      border: 1px solid #ead7c2;
      background: #fff;
      color: #7c3f00;
      border-radius: 999px;
      padding: 0.45rem 0.9rem;
      font-weight: 700;
      font-size: 0.86rem;
    }
    .dashboard-calendar-reset:hover {
      background: #fff5ea;
      border-color: #de710c;
      color: #de710c;
    }
    .dashboard-empty-filter {
      display: none;
      background: #fff;
      border: 1px dashed #ecc9a4;
      border-radius: 16px;
      padding: 2rem 1.5rem;
      text-align: center;
      color: #6b7280;
      box-shadow: 0 10px 28px rgba(15, 23, 42, 0.04);
    }
    .dashboard-empty-filter.is-visible {
      display: block;
    }
    .dashboard-empty-filter i {
      font-size: 1.8rem;
      color: #de710c;
      margin-bottom: 0.8rem;
    }
    .verify-cta-card {
      position: relative;
    }
    .card-action.is-locked {
      position: relative;
      cursor: pointer;
      border: 1px solid #f5d5b4 !important;
      background: linear-gradient(180deg, #fffaf5 0%, #fff4e8 100%);
    }
    .card-action.is-locked::after {
      content: "Verification Required";
      position: absolute;
      top: 12px;
      right: 12px;
      padding: 0.28rem 0.55rem;
      border-radius: 999px;
      background: rgba(124, 63, 0, 0.1);
      color: #8a5308;
      font-size: 0.7rem;
      font-weight: 800;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }
    .card-action.is-locked:hover {
      background: linear-gradient(180deg, #fff4e8 0%, #ffe9d3 100%);
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
    @media (max-width: 991.98px) {
      .dashboard-main-grid {
        grid-template-columns: 1fr;
      }
      .dashboard-calendar-card {
        position: static;
      }
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
        <section class="dashboard-main-grid mb-4">
          <div class="dashboard-announcement-panel">
            <div class="dashboard-announcements-stack" id="dashboardAnnouncementsStack">
              <?php foreach ($residentAnnouncements as $index => $announcement): ?>
                <div class="dashboard-announcement-banner rounded-4 overflow-hidden shadow-sm border-orange-thin position-relative mb-3" id="dashboardAnnouncementCard<?= (int)$index ?>" data-announcement-date="<?= htmlspecialchars($announcement['posted_date_iso']) ?>" onclick="location.href='Announcements/AnnouncementsLandingPage'" role="button" tabindex="0">
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
            <div class="dashboard-empty-filter" id="dashboardAnnouncementEmptyState">
              <i class="fa-regular fa-calendar-xmark"></i>
              <h3 class="h5 mb-2">No announcements for this date</h3>
              <p class="mb-0">Pick another day on the calendar to check account-page announcements for that schedule.</p>
            </div>
          </div>

          <aside class="dashboard-calendar-panel">
            <div class="dashboard-calendar-card">
              <div class="dashboard-calendar-head">
                <div class="dashboard-calendar-kicker"><i class="fa-regular fa-calendar-days"></i> Announcement Calendar</div>
                <h3 class="dashboard-calendar-title">Browse by Date</h3>
                <p class="dashboard-calendar-copy"><i>Gamitin ang kalendaryo upang paghiwa-hiwalayin ang mga anunsyo sa dashboard batay sa petsa ng pag-post. Ang mga petsang may anunsyo ay nakatala sa ibaba.</i> </p>
              </div>
              <div class="dashboard-calendar-body">
                <div class="dashboard-calendar-toolbar">
                  <button type="button" class="dashboard-calendar-nav" id="dashboardCalendarPrev" aria-label="Previous month">
                    <i class="fa-solid fa-chevron-left"></i>
                  </button>
                  <div class="dashboard-calendar-month" id="dashboardCalendarMonthLabel">-</div>
                  <button type="button" class="dashboard-calendar-nav" id="dashboardCalendarNext" aria-label="Next month">
                    <i class="fa-solid fa-chevron-right"></i>
                  </button>
                </div>
                <div class="dashboard-calendar-weekdays">
                  <span>Sun</span>
                  <span>Mon</span>
                  <span>Tue</span>
                  <span>Wed</span>
                  <span>Thu</span>
                  <span>Fri</span>
                  <span>Sat</span>
                </div>
                <div class="dashboard-calendar-grid" id="dashboardCalendarGrid" aria-live="polite"></div>
                <div class="dashboard-calendar-footer">
                  <p class="dashboard-calendar-selection" id="dashboardCalendarSelection">Showing all announcement dates</p>
                  <button type="button" class="dashboard-calendar-reset" id="dashboardCalendarReset">Show All</button>
                </div>
              </div>
            </div>
          </aside>
        </section>
      <?php endif; ?>

      <?php if (!$isResidentVerified): ?>
        <div class="verify-cta-card rounded-4 overflow-hidden shadow-sm border-orange-thin bg-white mb-4" id="verifyCtaCard">
          <button type="button" class="verify-cta-close" id="verifyCtaCloseBtn" aria-label="Close">×</button>
          <div class="bg-orange text-center py-2">
            <h3 class="text-white fw-bold mb-0">ACCOUNT VERIFICATION</h3>
          </div>
          <div class="verify-cta-body p-3 p-md-4 text-center">
            <p class="text-muted mb-2">Upload your supporting documents to unlock resident-only modules. You may also request your desired document through a walk-in visit at the barangay.</p>
            <a href="DocumentUpload" class="btn btn-primary px-4">Verify Now</a>
          </div>
        </div>
      <?php endif; ?>

      <h2 id="txt-sectionTitle" class="fw-bold border-bottom pb-2 mb-4">DASHBOARD</h2>

      <div id="div-serviceGrid" class="row service-grid justify-content-center gx-4">

        <div class="col-12 col-md-4 col-lg-3">
          <div id="card-serviceRequest-certificates"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm <?= !$isResidentVerified ? 'is-locked' : '' ?>"
               <?= !$isResidentVerified ? 'data-verify-required="1" data-target-url="Certificates/CertificatesLandingPage"' : 'onclick="location.href=\'Certificates/CertificatesLandingPage\'"' ?>>
            <i class="fa-solid fa-file-lines fa-2xl mb-3"></i><br>
            <span class="fw-bold small">CERTIFICATE REQUEST</span>
          </div>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <div id="card-serviceRequest-clearances"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm"
               onclick="location.href='Clearances/ClearancesLandingPage'">
            <i class="fa-solid fa-clipboard-check fa-2xl mb-3"></i><br>
            <span class="fw-bold small">CLEARANCES</span>
          </div>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <div id="card-serviceRequest-brgyId"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm <?= !$isResidentVerified ? 'is-locked' : '' ?>"
               <?= !$isResidentVerified ? 'data-verify-required="1" data-target-url="BarangayId/BarangayIdLandingPage"' : 'onclick="location.href=\'BarangayId/BarangayIdLandingPage\'"' ?>>
            <i class="fa-solid fa-id-card fa-2xl mb-3"></i><br>
            <span class="fw-bold small">BARANGAY ID</span>
          </div>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <div id="card-serviceRequest-appointments"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm"
               onclick="location.href='Appointments/AppointmentsLandingPage'">
            <i class="fa-solid fa-calendar-check fa-2xl mb-3"></i><br>
            <span class="fw-bold small">APPOINTMENTS</span>
          </div>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <div id="card-serviceRequest-announcements"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm"
               onclick="location.href='Announcements/AnnouncementsLandingPage'">
            <i class="fa-solid fa-bullhorn fa-2xl mb-3"></i><br>
            <span class="fw-bold small">ANNOUNCEMENTS</span>
          </div>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <div id="card-serviceRequest-transactions"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm <?= !$isResidentVerified ? 'is-locked' : '' ?>"
               <?= !$isResidentVerified ? 'data-verify-required="1" data-target-url="resident_transactions"' : 'onclick="location.href=\'resident_transactions\'"' ?>>
            <i class="fa-solid fa-money-check-dollar fa-2xl mb-3"></i><br>
            <span class="fw-bold small">TRANSACTIONS</span>
          </div>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <div id="card-serviceRequest-complaints"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm"
               onclick="location.href='Complaints/ComplaintsLandingPage'">
            <i class="fa-solid fa-comment-dots fa-2xl mb-3"></i><br>
            <span class="fw-bold small">COMPLAINTS</span>
          </div>
        </div>

        <div class="col-6 col-md-4 col-lg-3">
          <div id="card-serviceRequest-profile"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm"
               onclick="location.href='resident_profile'">
            <i class="fa-solid fa-user-circle fa-2xl mb-3"></i><br>
            <span class="fw-bold small">MY PROFILE</span>
          </div>
        </div>

      </div>

    </main>
  </div>

  <div class="modal fade" id="notVerifiedResidentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-0 pb-0 bg-white">
          <h5 class="modal-title w-100 text-center text-dark">Resident Verification</h5>
          <button type="button" class="btn-close position-absolute end-0 top-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <hr class="my-0">
        <div class="modal-body text-center">
          <p class="mb-2">You must be a verified resident to access this module.</p>
          <p class="text-muted mb-0">Upload supporting documents to continue online, or request your desired document through a walk-in visit at the barangay.</p>
        </div>
        <div class="modal-footer border-0 pt-0 d-flex gap-2 justify-content-center">
          <a href="DocumentUpload" class="btn btn-primary px-4">Verify Now</a>
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
      const modalEl = document.getElementById("notVerifiedResidentModal");
      if (!modalEl || !window.bootstrap?.Modal) return;

      const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      if (shouldShow) {
        modal.show();
      }

      document.querySelectorAll("[data-verify-required='1']").forEach((card) => {
        card.addEventListener("click", () => {
          modal.show();
        });
      });
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

    (() => {
      const announcementCards = Array.from(document.querySelectorAll("[data-announcement-date]"));
      const calendarGrid = document.getElementById("dashboardCalendarGrid");
      const monthLabel = document.getElementById("dashboardCalendarMonthLabel");
      const selectionLabel = document.getElementById("dashboardCalendarSelection");
      const resetBtn = document.getElementById("dashboardCalendarReset");
      const prevBtn = document.getElementById("dashboardCalendarPrev");
      const nextBtn = document.getElementById("dashboardCalendarNext");
      const emptyState = document.getElementById("dashboardAnnouncementEmptyState");
      if (!announcementCards.length || !calendarGrid || !monthLabel || !selectionLabel || !resetBtn || !prevBtn || !nextBtn) {
        return;
      }

      const announcementDates = Array.from(new Set(announcementCards
        .map((card) => String(card.getAttribute("data-announcement-date") || "").trim())
        .filter(Boolean)));

      const today = new Date();
      let selectedDate = "";
      let currentViewDate = announcementDates.length
        ? new Date(announcementDates[0] + "T00:00:00")
        : new Date(today.getFullYear(), today.getMonth(), 1);

      function formatIso(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");
        return `${year}-${month}-${day}`;
      }

      function formatReadable(isoDate) {
        if (!isoDate) {
          return "Showing all announcement dates";
        }
        const date = new Date(isoDate + "T00:00:00");
        return `Showing announcements for ${date.toLocaleDateString(undefined, { month: "long", day: "numeric", year: "numeric" })}`;
      }

      function applyAnnouncementFilter() {
        let visibleCount = 0;
        announcementCards.forEach((card) => {
          const cardDate = String(card.getAttribute("data-announcement-date") || "").trim();
          const shouldShow = selectedDate === "" || cardDate === selectedDate;
          card.classList.toggle("d-none", !shouldShow);
          if (shouldShow) {
            visibleCount += 1;
          }
        });
        selectionLabel.textContent = formatReadable(selectedDate);
        if (emptyState) {
          emptyState.classList.toggle("is-visible", visibleCount === 0);
        }
      }

      function renderCalendar() {
        const year = currentViewDate.getFullYear();
        const month = currentViewDate.getMonth();
        monthLabel.textContent = currentViewDate.toLocaleDateString(undefined, { month: "long", year: "numeric" });
        calendarGrid.innerHTML = "";

        const firstDay = new Date(year, month, 1);
        const startWeekday = firstDay.getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrevMonth = new Date(year, month, 0).getDate();
        const totalCells = Math.ceil((startWeekday + daysInMonth) / 7) * 7;

        for (let cellIndex = 0; cellIndex < totalCells; cellIndex += 1) {
          let dateObj;
          let isOutside = false;

          if (cellIndex < startWeekday) {
            dateObj = new Date(year, month - 1, daysInPrevMonth - startWeekday + cellIndex + 1);
            isOutside = true;
          } else if (cellIndex >= startWeekday + daysInMonth) {
            dateObj = new Date(year, month + 1, cellIndex - (startWeekday + daysInMonth) + 1);
            isOutside = true;
          } else {
            dateObj = new Date(year, month, cellIndex - startWeekday + 1);
          }

          const iso = formatIso(dateObj);
          const button = document.createElement("button");
          button.type = "button";
          button.className = "dashboard-calendar-day";
          button.textContent = String(dateObj.getDate());
          if (isOutside) {
            button.classList.add("is-outside");
          }
          if (announcementDates.includes(iso)) {
            button.classList.add("has-announcements");
          }
          if (selectedDate === iso) {
            button.classList.add("is-selected");
          }
          button.addEventListener("click", () => {
            selectedDate = iso;
            currentViewDate = new Date(dateObj.getFullYear(), dateObj.getMonth(), 1);
            applyAnnouncementFilter();
            renderCalendar();
          });
          calendarGrid.appendChild(button);
        }
      }

      prevBtn.addEventListener("click", () => {
        currentViewDate = new Date(currentViewDate.getFullYear(), currentViewDate.getMonth() - 1, 1);
        renderCalendar();
      });

      nextBtn.addEventListener("click", () => {
        currentViewDate = new Date(currentViewDate.getFullYear(), currentViewDate.getMonth() + 1, 1);
        renderCalendar();
      });

      resetBtn.addEventListener("click", () => {
        selectedDate = "";
        applyAnnouncementFilter();
        renderCalendar();
      });

      applyAnnouncementFilter();
      renderCalendar();
    })();
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
