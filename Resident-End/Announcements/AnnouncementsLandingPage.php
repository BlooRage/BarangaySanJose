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

$allowUnregistered = false;
require_once __DIR__ . "/../includes/resident_access_guard.php";
require_once __DIR__ . "/../../PhpFiles/Admin-End/contentStore.php";
require_once __DIR__ . "/../../PhpFiles/Admin-End/announcementAudience.php";

$items = announcements_load_all();
$websiteAnnouncements = [];
$viewerContext = ann_audience_fetch_resident_context($conn, (string)($_SESSION['user_id'] ?? ''));

foreach ($items as $item) {
  $channels = array_values(array_filter((array)($item['channels'] ?? []), function ($ch) {
    return in_array((string)$ch, ['website', 'public', 'public_news', 'sms', 'email'], true);
  }));
  $status = strtolower((string)($item['status'] ?? 'draft'));
  if (!in_array('website', $channels, true)) {
    continue;
  }
  if ($status !== 'approved') {
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
  $postedTs = 0;
  $ts = strtotime($rawPosted);
  if ($ts !== false) {
    $postedTs = (int)$ts;
    $postedDate = date('M d, Y h:i A', $ts);
  }

  $websiteAnnouncements[] = [
    'title' => (string)(($item['public_title'] ?? '') !== '' ? $item['public_title'] : ($item['title'] ?? '')),
    'content_html' => (string)(($item['public_content_html'] ?? '') !== '' ? $item['public_content_html'] : ($item['content_html'] ?? '')),
    'posted_date' => $postedDate,
    'posted_ts' => $postedTs,
  ];
}

usort($websiteAnnouncements, static function (array $a, array $b): int {
  return ((int)($b['posted_ts'] ?? 0)) <=> ((int)($a['posted_ts'] ?? 0));
});

$perPage = 5;
$totalAnnouncements = count($websiteAnnouncements);
$totalPages = max(1, (int)ceil($totalAnnouncements / $perPage));
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) {
  $currentPage = 1;
}
if ($currentPage > $totalPages) {
  $currentPage = $totalPages;
}

$offset = ($currentPage - 1) * $perPage;
$pagedAnnouncements = array_slice($websiteAnnouncements, $offset, $perPage);
$announcementsPageUrl = htmlspecialchars(appUrl('/Resident-End/Announcements/AnnouncementsLandingPage.php'), ENT_QUOTES, 'UTF-8');
$paginationStart = max(1, $currentPage - 2);
$paginationEnd = min($totalPages, $currentPage + 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="<?= htmlspecialchars((string)$baseUrl, ENT_QUOTES, 'UTF-8') ?>/Images/favicon_sanjose.png?v=20260211">
  <title>Resident Announcements</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../../CSS-Styles/Guest-End-CSS/GeneralStyle.css">
  <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/residentDashboard.css">
  <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/ApplicationLandingPage.css?v=20260228-3">
  <style>
    body.announcements-page {
      background: linear-gradient(180deg, #f3f5fa 0%, #fbfcff 22%, #ffffff 100%);
    }

    .announcements-page #div-mainDisplay {
      background: transparent !important;
    }

    .announcements-page .page-title {
      margin-bottom: 1.5rem;
      font-family: 'Geist', sans-serif;
      font-weight: 800;
      letter-spacing: -0.04em;
      font-size: clamp(2.4rem, 4vw, 3.7rem);
      line-height: 1.02;
    }

    .announcements-page .page-divider {
      height: 1px;
      margin: 0 0 1.5rem;
      background: #cfc5bc;
    }

    .announcements-page .page-description {
      margin: 0 0 2rem !important;
      max-width: 72rem;
      color: #445064;
      font-family: 'Geist', sans-serif;
      font-size: 1.02rem;
      line-height: 1.72;
    }

    .announcements-shell {
      width: 100%;
      max-width: none;
    }

    .announcements-list {
      display: grid;
      gap: 1.15rem;
    }

    .announcement-entry {
      position: relative;
      display: flex;
      flex-direction: column;
      gap: 1rem;
      width: 100%;
      padding: 1.45rem 1.55rem 1.5rem;
      border-radius: 1.85rem;
      border: 1px solid #efcfab;
      background:
        radial-gradient(circle at top right, rgba(255, 228, 188, 0.72), rgba(255, 228, 188, 0) 22%),
        linear-gradient(135deg, #fffefb 0%, #fff8f1 100%);
      box-shadow:
        0 18px 40px rgba(15, 23, 42, 0.07),
        inset 0 1px 0 rgba(255, 255, 255, 0.92);
      overflow: hidden;
    }

    .announcement-entry::before {
      content: "";
      position: absolute;
      inset: 0;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95);
      pointer-events: none;
    }

    .announcement-entry__top,
    .announcement-body {
      position: relative;
      z-index: 1;
    }

    .announcement-entry__top {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 0.9rem 1.2rem;
      padding-bottom: 0.9rem;
      border-bottom: 1px solid rgba(239, 207, 171, 0.82);
    }

    .announcement-title {
      margin: 0;
      max-width: 24ch;
      font-family: 'Geist', sans-serif;
      font-weight: 800;
      letter-spacing: -0.035em;
      color: #c96c14;
      font-size: clamp(1.32rem, 1.7vw, 1.75rem);
      line-height: 1.12;
      text-wrap: balance;
    }

    .announcement-posted {
      flex: 0 0 auto;
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.6rem 0.85rem;
      border-radius: 999px;
      border: 1px solid #efdcc9;
      background: #fffaf5;
      color: #7a4b16;
      font-family: 'Geist', sans-serif;
      font-size: 0.88rem;
      font-weight: 700;
      line-height: 1.2;
      white-space: nowrap;
    }

    .announcement-body {
      color: #39485f;
      font-family: 'Geist', sans-serif;
      line-height: 1.72;
      font-size: 1rem;
    }

    .announcement-body h1,
    .announcement-body h2,
    .announcement-body h3,
    .announcement-body h4,
    .announcement-body h5,
    .announcement-body h6 {
      margin: 0 0 0.8rem;
      font-family: 'Geist', sans-serif !important;
      font-size: clamp(1.02rem, 1.15vw, 1.18rem);
      font-weight: 800;
      letter-spacing: -0.02em;
      line-height: 1.3;
      color: #b86417 !important;
    }

    .announcement-body p,
    .announcement-body li,
    .announcement-body div,
    .announcement-body span,
    .announcement-body td,
    .announcement-body th,
    .announcement-body blockquote {
      font-family: 'Geist', sans-serif !important;
      color: #4a5970 !important;
      font-size: 1rem;
      line-height: 1.72;
    }

    .announcement-body p,
    .announcement-body ul,
    .announcement-body ol,
    .announcement-body blockquote,
    .announcement-body table {
      margin-bottom: 1rem;
    }

    .announcement-body ul,
    .announcement-body ol {
      padding-left: 1.3rem;
    }

    .announcement-body strong,
    .announcement-body b {
      color: #243247 !important;
      font-weight: 700;
    }

    .announcement-body p > strong:only-child,
    .announcement-body p > b:only-child,
    .announcement-body div > strong:only-child,
    .announcement-body div > b:only-child,
    .announcement-body li > strong:only-child,
    .announcement-body li > b:only-child {
      color: inherit !important;
      font-weight: 400 !important;
    }

    .announcement-body a {
      color: #a75816 !important;
      font-weight: 700;
      text-decoration-thickness: 0.08em;
    }

    .announcement-body > :first-child {
      margin-top: 0;
    }

    .announcement-body > :last-child {
      margin-bottom: 0;
    }

    .announcement-body img,
    .announcement-body video,
    .announcement-body iframe,
    .announcement-body table {
      max-width: 100%;
      height: auto;
    }

    .announcement-body * {
      word-break: break-word;
    }

    .announcements-empty {
      text-align: center;
      color: #6c757d;
      padding: 2.4rem 1.4rem;
    }

    .announcements-pagination {
      margin-top: 1.35rem;
      display: flex;
      justify-content: center;
    }

    .announcements-pagination .pagination {
      gap: 0.45rem;
      margin-bottom: 0;
      flex-wrap: wrap;
    }

    .announcements-pagination .page-item {
      display: flex;
    }

    .announcements-pagination .page-link {
      min-width: 2.7rem;
      height: 2.7rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 0.9rem !important;
      border: 1px solid #efd2b2;
      background: #ffffff;
      color: #7a4b16;
      font-weight: 800;
      box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
    }

    .announcements-pagination .page-link:hover {
      background: #fff6ea;
      border-color: #e7bf8f;
      color: #b85a00;
    }

    .announcements-pagination .page-item.active .page-link {
      background: #f58220;
      border-color: #f58220;
      color: #ffffff;
      box-shadow: 0 8px 18px rgba(245, 130, 32, 0.22);
    }

    .announcements-pagination .page-item.disabled .page-link {
      background: #f9fafb;
      border-color: #ece5dc;
      color: #b3a394;
      box-shadow: none;
    }

    @media (max-width: 767.98px) {
      .announcements-page .main-content {
        padding-top: 4.25rem !important;
      }

      .announcement-entry {
        padding: 1.15rem 1.05rem 1.15rem;
        border-radius: 1.45rem;
      }

      .announcement-entry__top {
        flex-direction: column;
        align-items: flex-start;
      }

      .announcement-title {
        font-size: 1.18rem;
      }

      .announcement-posted {
        white-space: normal;
      }

      .announcement-body {
        font-size: 0.95rem;
        line-height: 1.65;
      }

      .announcements-pagination .page-link {
        min-width: 2.45rem;
        height: 2.45rem;
        padding: 0.45rem 0.65rem;
      }
    }
  </style>
</head>
<body class="documents-page announcements-page">
  <div class="d-flex min-vh-100">
    <?php include __DIR__ . '/../includes/resident_sidebar.php'; ?>

    <header id="mobile-header">
      <div class="d-flex align-items-center px-3 py-2 shadow-sm bg-white">
        <div class="d-flex align-items-center gap-2">
          <button class="btn" id="btn-burger" type="button" aria-label="Open sidebar">
            <i class="fa-solid fa-bars fa-lg"></i>
          </button>
          <img src="<?= htmlspecialchars((string)$baseUrl, ENT_QUOTES, 'UTF-8') ?>/Images/San_Jose_LOGO.jpg" alt="Logo" style="width:32px;height:32px">
          <span class="logo-name">Barangay San Jose</span>
        </div>
      </div>
    </header>

    <main id="div-mainDisplay" class="main-content flex-grow-1 p-4 p-md-5">
      <div class="announcements-shell">
        <h1 class="page-title">Announcements</h1>
        <hr class="page-divider">

        <p class="page-description">
          Stay informed with verified barangay updates, advisories, and reminders posted through the official resident announcement channel.
        </p>

        <div class="announcements-list">
          <?php if (!$pagedAnnouncements): ?>
            <div class="announcement-entry announcements-empty">No announcements available yet.</div>
          <?php else: ?>
            <?php foreach ($pagedAnnouncements as $ann): ?>
              <article class="announcement-entry">
                <div class="announcement-entry__top">
                  <h2 class="announcement-title"><?= htmlspecialchars((string)$ann['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                  <div class="announcement-posted">
                    <i class="fa-regular fa-clock"></i>
                    <span><?= htmlspecialchars((string)$ann['posted_date'], ENT_QUOTES, 'UTF-8') ?></span>
                  </div>
                </div>
                <div class="announcement-body">
                  <?= trim((string)$ann['content_html']) !== '' ? $ann['content_html'] : '<span class="text-muted">No content.</span>' ?>
                </div>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
          <nav class="announcements-pagination" aria-label="Announcements pages">
            <ul class="pagination">
              <li class="page-item<?= $currentPage <= 1 ? ' disabled' : '' ?>">
                <a class="page-link" href="<?= $currentPage <= 1 ? '#' : ($announcementsPageUrl . '?page=' . ($currentPage - 1)) ?>" aria-label="Previous page">‹</a>
              </li>

              <?php for ($page = $paginationStart; $page <= $paginationEnd; $page++): ?>
                <li class="page-item<?= $page === $currentPage ? ' active' : '' ?>">
                  <a class="page-link" href="<?= $announcementsPageUrl . '?page=' . $page ?>" aria-label="Page <?= $page ?>"><?= $page ?></a>
                </li>
              <?php endfor; ?>

              <li class="page-item<?= $currentPage >= $totalPages ? ' disabled' : '' ?>">
                <a class="page-link" href="<?= $currentPage >= $totalPages ? '#' : ($announcementsPageUrl . '?page=' . ($currentPage + 1)) ?>" aria-label="Next page">›</a>
              </li>
            </ul>
          </nav>
        <?php endif; ?>
      </div>
    </main>
  </div>

  <script>
    const burgerBtn = document.getElementById("btn-burger");
    const sidebar = document.getElementById("div-sidebarWrapper");

    if (burgerBtn && sidebar) {
      burgerBtn.addEventListener("click", () => {
        sidebar.classList.toggle("show");
      });
    }
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
