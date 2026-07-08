<?php
require_once __DIR__ . "/includes/admin_guard.php";
require_once __DIR__ . "/../PhpFiles/Admin-End/contentStore.php";
require_once __DIR__ . "/../PhpFiles/Admin-End/announcementAudience.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" href="../Images/favicon_sanjose.png?v=20260211">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard</title>

  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="preconnect" href="https://kit.fontawesome.com" crossorigin>
  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous" defer></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css?v=20260628-1">
</head>

<body>
<div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
  <?php include 'includes/sidebar.php'; ?>
  <?php
    $moduleCards = [];
    $attentionCandidates = [];
    $dashboardAnnouncements = [];
    $announcementDismissVersion = '';

    $addModuleCard = static function (
      bool $visible,
      string $id,
      string $label,
      string $href,
      string $icon,
      string $subtext,
      int $attentionCount = 0
    ) use (&$moduleCards): void {
      if (!$visible) {
        return;
      }

      $moduleCards[] = [
        'id' => $id,
        'label' => $label,
        'href' => $href,
        'icon' => $icon,
        'subtext' => $subtext,
        'count' => max(0, $attentionCount),
      ];
    };

    $addAttention = static function (
      bool $visible,
      string $label,
      int $count,
      string $href,
      string $icon
    ) use (&$attentionCandidates): void {
      if (!$visible) {
        return;
      }

      $attentionCandidates[] = [
        'label' => $label,
        'count' => max(0, $count),
        'href' => $href,
        'icon' => $icon,
      ];
    };

    $residentAttention = $sbModuleCount('resident_profiling');
    $householdAttention = $sbModuleCount('household_profiling');
    $appointmentsAttention = $sbModuleCount('appointments');
    $certificateAttention = $sbModuleCount('certificate_issuance');
    $idAttention = $sbModuleCount('id_issuance');
    $clearanceAttention = $sbModuleCount('clearance_issuance');
    $financeAttention = $sbModuleCount('finance_transactions');
    $blotterAttention = $sbModuleCount('blotter_tools');
    $complaintAttention = $sbModuleCount('complaint_tools');
    $contentAttention = $sbModuleCount('content_management');
    $userAttention = $sbModuleCount('user_management');
    $newsHubHref = appUrl('Admin-End/Contents/Contents.php?tool=tracker&type_filter=news#tracker-card');
    $announcementsHubHref = $sbCan('announcements_tracker')
      ? appUrl('Admin-End/Contents/Contents.php?tool=tracker#tracker-card')
      : ($sbCan('announcements_page')
        ? appUrl('Admin-End/Contents/CreateContent.php?type=page')
        : ($sbCan('announcements_delivery')
          ? appUrl('Admin-End/Contents/CreateContent.php?type=delivery')
          : appUrl('Admin-End/Contents/CreateContent.php?type=faq')));

    $addAttention($sbCanAccessAppointmentTracker, 'Appointments waiting', $appointmentsAttention, appUrl('Admin-End/Appointments/AppointmentTracker.php?tool=tracker'), 'fa-calendar-check');
    $addAttention($sbCan('resident_masterlist') || $sbCan('resident_edit_requests') || $sbCan('resident_sector_membership_verification'), 'Resident profiling tasks', $residentAttention, appUrl('Admin-End/ResidentMasterlist.php'), 'fa-users');
    $addAttention($sbCan('head_of_family_verification') || $sbCan('household_member_verification'), 'Household verifications', $householdAttention, appUrl('Admin-End/HouseholdProfiling.php'), 'fa-house-user');
    $addAttention($sbCan('certificate_issuance'), 'Certificate requests', $certificateAttention, appUrl('Admin-End/Certificates/CertificateTracker.php?filter_document=__certificates__'), 'fa-file-circle-check');
    $addAttention($sbCan('id_issuance_tracker'), 'Barangay ID processing', $idAttention, appUrl('Admin-End/Certificates/CertificateTracker.php?entry=id_issuance'), 'fa-id-card');
    $addAttention($sbCan('clearance_issuance'), 'Clearance requests', $clearanceAttention, appUrl('Admin-End/Certificates/CertificateTracker.php?filter_document=__clearances__'), 'fa-stamp');
    $addAttention($sbCan('finance_payment_tracker') || $sbCan('finance_fee_management'), 'Finance actions', $financeAttention, appUrl('Admin-End/FinancePayments.php?section=tracker'), 'fa-money-check-alt');
    $addAttention($sbCan('blotter_review_queue') || $sbCan('blotter_tracker'), 'Blotter follow-ups', $blotterAttention, appUrl('Admin-End/Blotter/BlotterTracker.php'), 'fa-scale-balanced');
    $addAttention($sbCan('complaint_tracker'), 'Complaint queue', $complaintAttention, appUrl('Admin-End/Complaints/ComplaintTracker.php'), 'fa-comments');
    $addAttention($sbCanAccessContentNavigator, 'Content reviews', $contentAttention, appUrl('Admin-End/Contents/Contents.php'), 'fa-bullhorn');
    $addAttention($sbCan('user_masterlist') || $sbCan('user_archive'), 'User account items', $userAttention, appUrl('Admin-End/UserMasterlist.php'), 'fa-user-shield');

    usort($attentionCandidates, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);
    $topAttention = array_slice($attentionCandidates, 0, 4);
    $pendingTotal = array_sum(array_map(static fn(array $item): int => (int)$item['count'], $attentionCandidates));

    $addModuleCard($sbCanAccessAppointmentTracker, 'appointments', 'Appointments', appUrl('Admin-End/Appointments/AppointmentTracker.php?tool=tracker'), 'fa-calendar-check', 'Review schedules, confirmations, and pending requests.', $appointmentsAttention);
    $addModuleCard($sbCan('resident_masterlist') || $sbCan('resident_edit_requests') || $sbCan('resident_sector_membership_verification'), 'resident-profiling', 'Resident Profiling', appUrl('Admin-End/ResidentMasterlist.php'), 'fa-users', 'Open resident records, profile edits, and verification work.', $residentAttention);
    $addModuleCard($sbCan('household_profiling_main') || $sbCan('head_of_family_verification') || $sbCan('household_member_verification'), 'household-profiling', 'Household Profiling', appUrl('Admin-End/HouseholdProfiling.php'), 'fa-house-user', 'Continue household reviews and member verification tasks.', $householdAttention);
    $addModuleCard($sbCan('dashboard') || $sbCan('area_statistics_summary'), 'statistics', 'Statistics', appUrl($sbCan('dashboard') ? 'Admin-End/AreaManagement/BarangayStatistics.php' : 'Admin-End/AreaManagement/AreaStatistics.php?tab=summary'), 'fa-chart-column', 'Open barangay-wide and area-based operational analytics.', 0);
    $addModuleCard($sbCan('certificate_issuance'), 'certificate-issuance', 'Certificate Issuance', appUrl('Admin-End/Certificates/CertificateTracker.php?filter_document=__certificates__'), 'fa-file-circle-check', 'Track certificate requests and release-ready documents.', $certificateAttention);
    $addModuleCard($sbCan('id_issuance_tracker') || $sbCan('id_issuance_manual'), 'id-issuance', 'ID Issuance', appUrl($sbCan('id_issuance_tracker') ? 'Admin-End/Certificates/CertificateTracker.php?entry=id_issuance' : 'Admin-End/Certificates/CertificateTracker.php?tab=manual&document=barangay_id'), 'fa-id-card', 'Process Barangay ID applications and manual issuance.', $idAttention);
    $addModuleCard($sbCan('clearance_issuance'), 'clearance-issuance', 'Clearance Issuance', appUrl('Admin-End/Certificates/CertificateTracker.php?filter_document=__clearances__'), 'fa-stamp', 'Handle clearance pipelines and business-related releases.', $clearanceAttention);
    $addModuleCard($sbCan('business_monitoring'), 'business-monitoring', 'Business Monitoring', appUrl('Admin-End/BusinessMonitoring.php'), 'fa-store', 'Inspect permits, business records, and local monitoring tasks.', 0);
    $addModuleCard($sbCan('finance_payment_tracker') || $sbCan('finance_create_transaction') || $sbCan('finance_fee_management'), 'finance', 'Finance Transactions', appUrl('Admin-End/FinancePayments.php?section=tracker'), 'fa-money-check-alt', 'Verify payments, create transactions, and manage fees.', $financeAttention);
    $addModuleCard($sbCan('blotter_log_new_incident') || $sbCan('blotter_tracker') || $sbCan('blotter_review_queue'), 'blotter', 'e-Blotter Tools', appUrl($sbCan('blotter_tracker') ? 'Admin-End/Blotter/BlotterTracker.php' : 'Admin-End/Blotter/BlotterForm.php'), 'fa-scale-balanced', 'Log incidents and continue case reviews from the blotter queue.', $blotterAttention);
    $addModuleCard($sbCan('complaint_log_new_incident') || $sbCan('complaint_tracker'), 'complaints', 'Complaint Tools', appUrl($sbCan('complaint_tracker') ? 'Admin-End/Complaints/ComplaintTracker.php' : 'Admin-End/Complaints/ComplaintForm.php'), 'fa-comments', 'Track complaints, intake, and current complaint actions.', $complaintAttention);
    $addModuleCard($sbCan('news_management'), 'news', 'News', $newsHubHref, 'fa-newspaper', 'Open the news tracker and manage published or draft stories.', 0);
    $addModuleCard($sbCan('announcements_page') || $sbCan('announcements_delivery') || $sbCan('announcements_faq') || $sbCan('announcements_tracker'), 'announcements', 'Announcements', $announcementsHubHref, 'fa-bullhorn', 'Create page posts, SMS/email updates, and review the tracker.', $contentAttention);
    $addModuleCard($sbCan('reports_certificate_issuance') || $sbCan('reports_clearance_issuance') || $sbCan('reports_financial') || $sbCan('reports_residents') || $sbCan('reports_blotter') || $sbCan('reports_complaints'), 'reports', 'Reports', appUrl('Admin-End/Reports/Reports.php'), 'fa-chart-line', 'Open module reports, summaries, and export-ready views.', 0);
    $addModuleCard($sbCan('admin_management'), 'admin-management', 'Admin Management', appUrl('Admin-End/AdminManagement.php'), 'fa-user-gear', 'Manage administrator records and admin-only account controls.', 0);
    $addModuleCard($sbCan('user_masterlist') || $sbCan('user_archive'), 'user-management', 'User Management', appUrl($sbCan('user_masterlist') ? 'Admin-End/UserMasterlist.php' : 'Admin-End/UserArchive.php'), 'fa-users-cog', 'Review user access, archive records, and account maintenance.', $userAttention);
    $addModuleCard($isSuperAdminSidebar, 'personnel-management', 'Personnel Management', appUrl('Admin-End/PersonnelTracker.php'), 'fa-user-tie', 'Open the personnel tracker, invites, and access control workspace.', 0);
    $addModuleCard($sbCan('website_settings'), 'website-settings', 'Website Settings', appUrl('Admin-End/WebsiteSettings.php'), 'fa-screwdriver-wrench', 'Toggle maintenance mode and control public website availability.', 0);
    $addModuleCard($sbCan('official_records_management') || $sbCan('official_transition'), 'official-management', 'Official Management', appUrl($sbCan('official_records_management') ? 'Admin-End/OfficialsManagement.php' : 'Admin-End/OfficialTransitions.php'), 'fa-user-tie', 'Manage officials, transitions, and assigned office records.', 0);
    $addModuleCard($sbCan('audit_logs'), 'audit-logs', 'Audit Logs', appUrl('Admin-End/AuditLogs.php'), 'fa-clipboard-list', 'Review recent system actions and accountability trails.', 0);

    $staffViewerContext = ann_audience_fetch_staff_context(
      $conn,
      (string)($_SESSION['user_id'] ?? ''),
      (string)($_SESSION['role'] ?? 'Official')
    );
    $staffAnnouncementGroup = (string)($staffViewerContext['group'] ?? '');
    $announcementItems = announcements_load_all();

    foreach ($announcementItems as $item) {
      if (strtolower((string)($item['status'] ?? 'draft')) !== 'approved') {
        continue;
      }

      $contentType = strtolower(trim((string)($item['content_type'] ?? 'page')));
      if (!in_array($contentType, ['page', 'delivery'], true)) {
        continue;
      }

      $audienceConfig = ann_audience_config($item);
      if ($staffAnnouncementGroup === '' || $audienceConfig['normalized_groups'] === []) {
        continue;
      }
      if (!in_array($staffAnnouncementGroup, $audienceConfig['normalized_groups'], true)) {
        continue;
      }
      if (!ann_audience_matches_viewer($item, $staffViewerContext)) {
        continue;
      }

      $title = trim((string)(($item['public_title'] ?? '') !== '' ? $item['public_title'] : ($item['title'] ?? '')));
      $bodyHtml = trim((string)(($item['public_content_html'] ?? '') !== '' ? $item['public_content_html'] : ($item['content_html'] ?? '')));
      $plainBody = trim(preg_replace('/\s+/', ' ', strip_tags($bodyHtml)));
      $excerpt = $plainBody;
      if ($excerpt !== '' && function_exists('mb_strimwidth')) {
        $excerpt = mb_strimwidth($excerpt, 0, 180, '...');
      }

      $rawPosted = (string)($item['publish_date'] ?? '');
      if ($rawPosted === '' || $rawPosted === '-') {
        $rawPosted = (string)($item['created_at'] ?? '');
      }
      $postedLabel = 'Recently posted';
      $postedTs = strtotime($rawPosted);
      if ($postedTs !== false) {
        $postedLabel = date('M d, Y h:i A', $postedTs);
      }

      $channelMap = [
        'website' => 'Account Page',
        'public' => 'Guest Page',
        'sms' => 'SMS',
        'email' => 'Email',
      ];
      $channels = [];
      foreach ((array)($item['channels'] ?? []) as $channel) {
        $channelKey = strtolower(trim((string)$channel));
        if (isset($channelMap[$channelKey])) {
          $channels[] = $channelMap[$channelKey];
        }
      }
      $channels = array_values(array_unique($channels));

      $dashboardAnnouncements[] = [
        'title' => $title !== '' ? $title : 'Untitled announcement',
        'excerpt' => $excerpt !== '' ? $excerpt : 'Open the content tools to review the full announcement body.',
        'posted_label' => $postedLabel,
        'audience_label' => ann_audience_build_label(
          (string)$audienceConfig['scope'],
          (array)$audienceConfig['areas'],
          (array)$audienceConfig['role_groups']
        ),
        'channels' => $channels,
      ];

      if (count($dashboardAnnouncements) >= 4) {
        break;
      }
    }

    $announcementDismissVersion = md5(json_encode($dashboardAnnouncements, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'staff-announcements-empty');
  ?>

  <main class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light" id="main-display">
    <section class="dashboard-page-header">
      <h1 class="dashboard-page-title">Dashboard</h1>
      <div class="dashboard-page-divider" aria-hidden="true"></div>
    </section>

    <section class="dashboard-attention-panel mb-4">
      <div class="dashboard-section-head">
        <div>
          <h2 class="dashboard-section-title mb-1">Needs Attention</h2>
          <p class="dashboard-section-copy mb-0">Priority queues are calculated from the modules you can currently access.</p>
        </div>
        <span class="dashboard-pill"><?= number_format($pendingTotal) ?> pending items</span>
      </div>

      <?php if ($topAttention !== [] && $pendingTotal > 0): ?>
        <div class="row g-3 dashboard-attention-strip">
          <?php foreach ($topAttention as $item): ?>
            <div class="col-12 col-md-6 col-xl-3">
              <a href="<?= htmlspecialchars($item['href']) ?>" class="attention-tile text-decoration-none text-reset d-flex h-100">
                <span class="attention-tile__icon"><i class="fa-solid <?= htmlspecialchars($item['icon']) ?>"></i></span>
                <div class="attention-tile__body">
                  <span class="attention-tile__label"><?= htmlspecialchars($item['label']) ?></span>
                  <strong class="attention-tile__value"><?= number_format((int)$item['count']) ?></strong>
                </div>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="dashboard-calm-state">
          <div class="dashboard-calm-state__icon"><i class="fa-solid fa-circle-check"></i></div>
          <div>
            <h3 class="dashboard-calm-state__title mb-1">All caught up</h3>
            <p class="mb-0 text-muted">There are no urgent queues from your currently assigned modules. You can jump straight into the module hub below.</p>
          </div>
        </div>
      <?php endif; ?>
    </section>

    <section class="mb-4" id="staff-announcements-section" data-dismiss-version="<?= htmlspecialchars($announcementDismissVersion, ENT_QUOTES, 'UTF-8') ?>">
      <article class="chart-panel dashboard-announcements-panel">
        <div class="chart-panel-head">
          <div>
            <h3 class="chart-title">Staff Announcements</h3>
            <p class="chart-copy mb-0">Approved updates addressed to your staff group appear here on the dashboard.</p>
          </div>
          <div class="dashboard-panel-actions">
            <span class="chart-total"><?= number_format(count($dashboardAnnouncements)) ?> active</span>
            <button
              type="button"
              class="dashboard-dismiss-button"
              id="staff-announcements-dismiss"
              aria-label="Dismiss staff announcements"
              title="Dismiss staff announcements">
              <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
          </div>
        </div>

        <?php if ($dashboardAnnouncements !== []): ?>
          <div class="dashboard-announcements-list">
            <?php foreach ($dashboardAnnouncements as $announcement): ?>
              <article class="dashboard-announcement-card">
                <div class="dashboard-announcement-card__meta">
                  <span class="dashboard-announcement-card__time"><?= htmlspecialchars($announcement['posted_label']) ?></span>
                  <span class="dashboard-announcement-card__audience"><?= htmlspecialchars($announcement['audience_label']) ?></span>
                </div>
                <h4 class="dashboard-announcement-card__title"><?= htmlspecialchars($announcement['title']) ?></h4>
                <p class="dashboard-announcement-card__excerpt mb-0"><?= htmlspecialchars($announcement['excerpt']) ?></p>
                <?php if ($announcement['channels'] !== []): ?>
                  <div class="dashboard-announcement-card__channels">
                    <?php foreach ($announcement['channels'] as $channelLabel): ?>
                      <span class="dashboard-announcement-card__channel"><?= htmlspecialchars($channelLabel) ?></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="dashboard-calm-state">
            <div class="dashboard-calm-state__icon"><i class="fa-solid fa-bullhorn"></i></div>
            <div>
              <h3 class="dashboard-calm-state__title mb-1">No staff announcements yet</h3>
              <p class="mb-0 text-muted">Personnel-wide and official-wide announcements will show up here once they are approved.</p>
            </div>
          </div>
        <?php endif; ?>
      </article>
    </section>

    <section class="mb-4">
      <div class="dashboard-module-section">
        <div id="div-serviceGrid" class="row service-grid justify-content-center">
          <?php foreach ($moduleCards as $card): ?>
            <div class="col-12 col-md-6 col-lg-3">
              <a id="card-serviceRequest-<?= htmlspecialchars($card['id'], ENT_QUOTES, 'UTF-8') ?>"
                 class="card-action"
                 href="<?= htmlspecialchars($card['href'], ENT_QUOTES, 'UTF-8') ?>"
                 aria-label="Open <?= htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8') ?>">
                <?php if ((int)$card['count'] > 0): ?>
                  <span class="card-action__badge"><?= number_format((int)$card['count']) ?></span>
                <?php endif; ?>
                <span class="card-action__icon" aria-hidden="true"><i class="fa-solid <?= htmlspecialchars($card['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
                <span class="card-action__title"><?= htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="card-action__subtext"><?= htmlspecialchars($card['subtext'], ENT_QUOTES, 'UTF-8') ?></span>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.add('dashboard-preload');

    const staffAnnouncementsSection = document.getElementById('staff-announcements-section');
    const dismissButton = document.getElementById('staff-announcements-dismiss');
    if (staffAnnouncementsSection && dismissButton && window.sessionStorage) {
      const dismissVersion = String(staffAnnouncementsSection.dataset.dismissVersion || '');
      const storageKey = 'adminDashboard.staffAnnouncements.dismissed';
      const dismissedVersion = window.sessionStorage.getItem(storageKey);

      if (dismissVersion !== '' && dismissedVersion === dismissVersion) {
        staffAnnouncementsSection.classList.add('d-none');
      }

      dismissButton.addEventListener('click', () => {
        if (dismissVersion !== '') {
          window.sessionStorage.setItem(storageKey, dismissVersion);
        }
        staffAnnouncementsSection.classList.add('d-none');
      });
    }

    window.addEventListener('load', () => {
      window.requestAnimationFrame(() => {
        document.body.classList.add('dashboard-loaded');
      });
    });
  });
</script>
</body>
</html>
