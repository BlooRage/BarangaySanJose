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
require_once __DIR__ . "/../../PhpFiles/Admin-End/announcementsStore.php";

$items = announcements_load_all();
$websiteAnnouncements = [];

foreach ($items as $item) {
  $channels = array_values(array_filter((array)($item['channels'] ?? []), function ($ch) {
    return in_array((string)$ch, ['website', 'sms', 'email'], true);
  }));
  $status = strtolower((string)($item['status'] ?? 'draft'));
  if (!in_array('website', $channels, true)) {
    continue;
  }
  // Resident feed only receives approved website announcements.
  if ($status !== 'approved') {
    continue;
  }

  $rawPosted = (string)($item['publish_date'] ?? '');
  if ($rawPosted === '' || $rawPosted === '-') {
    $rawPosted = (string)($item['created_at'] ?? '');
  }
  $postedDate = '-';
  $ts = strtotime($rawPosted);
  if ($ts !== false) {
    $postedDate = date('M d, Y h:i A', $ts);
  }

  $websiteAnnouncements[] = [
    'title' => (string)($item['title'] ?? ''),
    'content_html' => (string)($item['content_html'] ?? ''),
    'posted_date' => $postedDate
  ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="<?= htmlspecialchars($baseUrl) ?>/Images/favicon_sanjose.png?v=20260211">
  <title>Resident Announcements</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../../CSS-Styles/Guest-End-CSS/GeneralStyle.css">
  <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/residentDashboard.css">
  <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/ApplicationLandingPage.css?v=20260228-3">
  <style>
    .announcements-list {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .announcement-entry {
      background: #ffffff;
      border: 1px solid #f2d3b8;
      border-radius: 18px;
      box-shadow: 0 10px 24px rgba(17, 24, 39, 0.08);
      padding: 1.25rem 1.35rem;
      transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    }

    .announcement-entry:hover {
      transform: translateY(-2px);
      border-color: #fc8d3d;
      box-shadow: 0 14px 28px rgba(17, 24, 39, 0.1);
    }

    .announcement-title {
      font-family: 'Charis SIL Bold', serif;
      color: #de710c;
      font-size: 1.25rem;
      margin-bottom: 0.2rem;
    }

    .announcement-meta {
      color: #6c757d;
      font-size: 0.9rem;
    }

    .announcement-posted {
      color: #6c757d;
      font-size: 0.85rem;
      white-space: nowrap;
      margin-top: 0.2rem;
    }

    .announcement-body {
      margin-top: 0.75rem;
      color: #212529;
      line-height: 1.55;
      word-wrap: break-word;
    }

    .announcement-body img {
      max-width: 100%;
      height: auto;
      border-radius: 8px;
    }

    @media (max-width: 767px) {
      .announcement-entry {
        border-radius: 14px;
        padding: 1rem;
      }
      .announcement-title {
        font-size: 1.1rem;
      }
    }
  </style>
</head>
<body>
  <div class="d-flex min-vh-100">
    <?php include __DIR__ . '/../includes/resident_sidebar.php'; ?>

    <main id="div-mainDisplay" class="main-content flex-grow-1 p-4 p-md-5 bg-light">
      <h1 class="page-title">Announcements</h1>
      <hr>

      <p class="page-description">
        Stay informed with verified barangay updates, advisories, and reminders posted through the official website channel.
      </p>

      <p class="section-label">Latest announcements:</p>

      <div class="announcements-list">
        <?php if (!$websiteAnnouncements): ?>
          <div class="announcement-entry text-center text-muted py-4">No announcements available yet.</div>
        <?php else: ?>
          <?php foreach ($websiteAnnouncements as $ann): ?>
            <article class="announcement-entry">
              <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                  <h2 class="announcement-title"><?= htmlspecialchars($ann['title']) ?></h2>
                </div>
                <div class="announcement-posted">Posted: <?= htmlspecialchars($ann['posted_date']) ?></div>
              </div>
              <div class="announcement-body">
                <?= $ann['content_html'] !== '' ? $ann['content_html'] : '<span class="text-muted">No content.</span>' ?>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
