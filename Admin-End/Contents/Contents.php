<?php
require_once __DIR__ . "/../includes/admin_guard.php";
require_once __DIR__ . "/../../PhpFiles/General/connection.php";
require_once __DIR__ . "/../../PhpFiles/Admin-End/contentStore.php";

$deliveryChannel = strtolower(trim((string)($_GET['channel'] ?? 'all')));
if (!in_array($deliveryChannel, ['all', 'website', 'public', 'public_news', 'sms', 'email'], true)) {
  $deliveryChannel = 'all';
}

$toolView = strtolower(trim((string)($_GET['tool'] ?? 'tracker')));
if (!in_array($toolView, ['tracker', 'review_queue'], true)) {
  $toolView = 'tracker';
}

$statusFilter = strtolower(trim((string)($_GET['status'] ?? 'all')));
if (!in_array($statusFilter, ['all', 'approved', 'denied', 'pending', 'draft'], true)) {
  $statusFilter = 'all';
}

$searchTerm = trim((string)($_GET['q'] ?? ''));
$queueSearchTerm = trim((string)($_GET['queue_q'] ?? ''));
$queueChannelFilter = strtolower(trim((string)($_GET['queue_channel'] ?? 'all')));
if (!in_array($queueChannelFilter, ['all', 'website', 'public', 'public_news', 'sms', 'email'], true)) {
  $queueChannelFilter = 'all';
}
$sessionRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
$isSuperAdmin = $sessionRole === 'superadmin';
$currentUserId = trim((string)($_SESSION['user_id'] ?? ''));

$channelLabels = [
  'public' => 'Guest Page',
  'public_news' => 'Public News',
  'website' => 'Account Page',
  'sms' => 'SMS',
  'email' => 'Email'
];

$typeLabels = [
  'page' => 'Page Announcement',
  'delivery' => 'SMS and Email Announcement',
  'faq' => 'FAQs Page'
];

$statusLabels = [
  'approved' => 'Approved',
  'pending' => 'Pending',
  'draft' => 'Draft',
  'denied' => 'Denied'
];

function ann_creator_display_from_user_id(mysqli $conn, string $userId, string $fallback): string
{
  static $cache = [];
  if ($userId === '') {
    return $fallback !== '' ? $fallback : 'Admin';
  }
  if (isset($cache[$userId])) {
    return $cache[$userId];
  }

  $hasPositionAccess = false;
  $colRes = $conn->query("SHOW COLUMNS FROM officialinformationtbl LIKE 'position_access'");
  if ($colRes instanceof mysqli_result && $colRes->num_rows > 0) {
    $hasPositionAccess = true;
  }
  $selectPosition = $hasPositionAccess ? "position_access" : "NULL AS position_access";

  $stmt = $conn->prepare("
    SELECT firstname, middlename, lastname, suffix, role_access, {$selectPosition}
    FROM officialinformationtbl
    WHERE user_id = ?
    LIMIT 1
  ");
  if (!$stmt) {
    $cache[$userId] = $fallback !== '' ? $fallback : $userId;
    return $cache[$userId];
  }

  $stmt->bind_param("s", $userId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$row) {
    $cache[$userId] = $fallback !== '' ? $fallback : $userId;
    return $cache[$userId];
  }

  $firstName = trim((string)($row['firstname'] ?? ''));
  $middleName = trim((string)($row['middlename'] ?? ''));
  $lastName = trim((string)($row['lastname'] ?? ''));
  $suffix = trim((string)($row['suffix'] ?? ''));
  $givenNameParts = preg_split('/\s+/', trim($firstName . ' ' . $middleName)) ?: [];
  $initials = [];
  foreach ($givenNameParts as $part) {
    $part = trim((string)$part);
    if ($part === '') {
      continue;
    }
    $initials[] = strtoupper(substr($part, 0, 1));
  }
  $firstInitial = $initials ? implode('.', $initials) . '.' : '';
  $fullName = trim(
    ($lastName !== '' ? $lastName : '') .
    (($lastName !== '' && $firstInitial !== '') ? ', ' : '') .
    $firstInitial .
    ($suffix !== '' ? ' ' . $suffix : '')
  );
  $rawPosition = trim((string)($row['position_access'] ?? ''));
  if ($rawPosition === '') {
    $rawPosition = trim((string)($row['role_access'] ?? ''));
  }
  $positionMap = [
    'IT Administrator' => 'IT Admin',
    'Barangay Chairman' => 'Brgy. Chair',
    'Barangay Official' => 'Brgy. Official',
    'Barangay Police' => 'Brgy. Police',
    'Barangay Secretary' => 'Brgy. Sec.',
    'Desk Officer' => 'Desk Off.',
    'Area OIC' => 'Area OIC',
    'Department OIC (Officer In Charge)' => 'Dept. OIC',
    'SuperAdmin' => 'SuperAdmin',
    'Official' => 'Official',
    'Personnel' => 'Personnel',
    'Employee' => 'Employee'
  ];
  $position = $positionMap[$rawPosition] ?? $rawPosition;

  $label = $fullName;
  if ($label === '') {
    $label = $fallback !== '' ? $fallback : $userId;
  }
  if ($position !== '') {
    $label .= ' - ' . $position;
  }
  $cache[$userId] = $label;
  return $cache[$userId];
}

function ann_owner_user_id(array $item): string
{
  $ownerUserId = trim((string)($item['created_by_user_id'] ?? ''));
  if ($ownerUserId !== '') {
    return $ownerUserId;
  }

  $createdByLegacy = trim((string)($item['created_by'] ?? ''));
  if ($createdByLegacy !== '' && strpos($createdByLegacy, ' - ') === false) {
    return $createdByLegacy;
  }

  return '';
}

function ann_is_owned_by_current_user(array $item, string $currentUserId, string $currentUserDisplay): bool
{
  if ($currentUserId === '') {
    return false;
  }

  $ownerUserId = ann_owner_user_id($item);
  if ($ownerUserId !== '' && $ownerUserId === $currentUserId) {
    return true;
  }

  $createdByLabel = trim((string)($item['created_by'] ?? ''));
  if ($ownerUserId === '' && $createdByLabel !== '' && $currentUserDisplay !== '' && $createdByLabel === $currentUserDisplay) {
    return true;
  }

  return false;
}

function ann_display_status(array $item, string $currentUserId, string $currentUserDisplay): string
{
  $status = strtolower((string)($item['status'] ?? 'draft'));
  $reviewResult = strtolower((string)($item['review_result'] ?? ''));
  if ($status === 'draft' && $reviewResult === 'denied' && ann_is_owned_by_current_user($item, $currentUserId, $currentUserDisplay)) {
    return 'denied';
  }
  return $status;
}

function ann_placements_from_channels(array $channels): array
{
  $placements = [];
  if (in_array('public_news', $channels, true)) {
    $placements[] = 'public_news';
  }
  if (in_array('public', $channels, true) || in_array('website', $channels, true)) {
    $placements[] = 'announcement';
  }
  return $placements;
}

$currentUserDisplayLabel = ann_creator_display_from_user_id($conn, $currentUserId, $currentUserId);

$announcementRows = [];
$storedAnnouncements = announcements_load_all();
foreach ($storedAnnouncements as $item) {
  $channels = array_values(array_filter((array)($item['channels'] ?? []), function ($ch) {
    return in_array($ch, ['website', 'public', 'public_news', 'sms', 'email'], true);
  }));
  $status = strtolower((string)($item['status'] ?? 'draft'));
  if (!in_array($status, ['approved', 'pending', 'draft'], true)) {
    $status = 'draft';
  }

  $createdByRoleRaw = (string)($item['created_by_role'] ?? 'Admin');
  $createdByRole = function_exists('normalizeRoleName')
    ? normalizeRoleName($createdByRoleRaw)
    : strtolower(trim($createdByRoleRaw));
  $createdByUserId = trim((string)($item['created_by_user_id'] ?? ''));
  $createdByStored = (string)($item['created_by'] ?? 'Admin');
  if ($createdByUserId === '' && $createdByStored !== '' && strpos($createdByStored, ' - ') === false) {
    $createdByUserId = $createdByStored;
  }
  $createdByDisplay = ann_creator_display_from_user_id($conn, $createdByUserId, $createdByStored);

  $announcementRows[] = [
    'id' => (string)($item['id'] ?? ''),
    'title' => (string)($item['title'] ?? ''),
    'content_type' => (string)($item['content_type'] ?? 'page'),
    'audience' => (string)($item['audience'] ?? 'All Residents'),
    'channels' => $channels,
    'placements' => ann_placements_from_channels($channels),
    'status' => $status,
    'publish_date' => (string)($item['publish_date'] ?? '-'),
    'created_by' => $createdByDisplay,
    'created_by_user_id' => $createdByUserId,
    'created_by_role' => $createdByRole,
    'content_html' => (string)($item['content_html'] ?? ''),
    'public_news_title' => (string)($item['public_news_title'] ?? ''),
    'public_news_content_html' => (string)($item['public_news_content_html'] ?? ''),
    'public_title' => (string)($item['public_title'] ?? ''),
    'public_content_html' => (string)($item['public_content_html'] ?? ''),
    'review_result' => strtolower((string)($item['review_result'] ?? '')),
    'review_note' => (string)($item['review_note'] ?? ''),
    'created_at' => (string)($item['created_at'] ?? ''),
    'updated_at' => (string)($item['updated_at'] ?? '')
  ];
}

$flash = $_SESSION['announcement_flash'] ?? null;
unset($_SESSION['announcement_flash']);

$filteredByChannel = array_values(array_filter($announcementRows, function ($item) use ($deliveryChannel, $currentUserId) {
  $status = strtolower((string)($item['status'] ?? 'draft'));
  if ($status === 'draft') {
    global $currentUserDisplayLabel;
    if (!ann_is_owned_by_current_user($item, $currentUserId, (string)$currentUserDisplayLabel)) {
      return false;
    }
  }

  if ($deliveryChannel === 'all') {
    return true;
  }
  return in_array($deliveryChannel, $item['channels'], true);
}));

$statusCounts = ['all' => 0, 'approved' => 0, 'denied' => 0, 'pending' => 0, 'draft' => 0];
foreach ($filteredByChannel as $item) {
  $status = ann_display_status($item, $currentUserId, $currentUserDisplayLabel);
  $statusCounts['all']++;
  if (isset($statusCounts[$status])) {
    $statusCounts[$status]++;
  }
}

$visibleRows = array_values(array_filter($filteredByChannel, function ($item) use ($statusFilter, $searchTerm, $channelLabels, $statusLabels, $currentUserId, $currentUserDisplayLabel) {
  $displayStatus = ann_display_status($item, $currentUserId, $currentUserDisplayLabel);
  if ($statusFilter !== 'all' && $displayStatus !== $statusFilter) {
    return false;
  }
  if ($searchTerm === '') {
    return true;
  }
  $haystack = strtolower(implode(' ', [
    $item['title'],
    $item['audience'],
    implode(', ', array_map(function ($ch) use ($channelLabels) {
      return $channelLabels[$ch] ?? strtoupper($ch);
    }, $item['channels'])),
    $statusLabels[$displayStatus] ?? $displayStatus,
    $item['created_by']
  ]));
  return str_contains($haystack, strtolower($searchTerm));
}));

$reviewQueueBaseRows = [];
$reviewQueueRows = [];
if ($isSuperAdmin) {
  $reviewQueueBaseRows = array_values(array_filter($announcementRows, function ($item) {
    if ((string)($item['status'] ?? '') !== 'pending') {
      return false;
    }
    if ((string)($item['created_by_role'] ?? '') === 'superadmin') {
      return false;
    }
    return true;
  }));

  $reviewQueueRows = array_values(array_filter($reviewQueueBaseRows, function ($item) use ($queueChannelFilter, $queueSearchTerm, $channelLabels) {
    if ($queueChannelFilter !== 'all' && !in_array($queueChannelFilter, (array)($item['channels'] ?? []), true)) {
      return false;
    }

    if ($queueSearchTerm === '') {
      return true;
    }

    $haystack = strtolower(implode(' ', [
      (string)($item['title'] ?? ''),
      (string)($item['audience'] ?? ''),
      (string)($item['created_by'] ?? ''),
      implode(', ', array_map(function ($ch) use ($channelLabels) {
        return $channelLabels[$ch] ?? strtoupper((string)$ch);
      }, (array)($item['channels'] ?? [])))
    ]));

    return str_contains($haystack, strtolower($queueSearchTerm));
  }));
}

$announcementDetailsMap = [];
foreach ($announcementRows as $row) {
  $announcementDetailsMap[(string)$row['id']] = [
    'id' => (string)$row['id'],
    'title' => (string)$row['title'],
    'content_type' => (string)($row['content_type'] ?? 'page'),
    'audience' => (string)$row['audience'],
    'channels' => array_values((array)$row['channels']),
    'placements' => array_values((array)($row['placements'] ?? [])),
    'status' => (string)$row['status'],
    'created_by_role' => (string)$row['created_by_role'],
    'created_by_user_id' => (string)($row['created_by_user_id'] ?? ''),
    'publish_date' => (string)$row['publish_date'],
    'created_by' => (string)$row['created_by'],
    'content_html' => (string)$row['content_html'],
    'public_news_title' => (string)($row['public_news_title'] ?? ''),
    'public_news_content_html' => (string)($row['public_news_content_html'] ?? ''),
    'public_title' => (string)($row['public_title'] ?? ''),
    'public_content_html' => (string)($row['public_content_html'] ?? ''),
    'sms_message' => (string)($row['sms_message'] ?? ''),
    'email_subject' => (string)($row['email_subject'] ?? ''),
    'faq_items' => ann_decode_faq_items((string)($row['faq_items_json'] ?? '')),
    'review_result' => (string)($row['review_result'] ?? ''),
    'review_note' => (string)($row['review_note'] ?? ''),
    'created_at' => (string)$row['created_at'],
    'updated_at' => (string)$row['updated_at']
  ];
}

function buildAnnouncementsUrl(string $channel, string $status, string $searchTerm = ''): string
{
  global $queueSearchTerm, $queueChannelFilter;
  $query = ['channel' => $channel, 'status' => $status];
  if ($searchTerm !== '') {
    $query['q'] = $searchTerm;
  }
  if ($queueSearchTerm !== '') {
    $query['queue_q'] = $queueSearchTerm;
  }
  if ($queueChannelFilter !== 'all') {
    $query['queue_channel'] = $queueChannelFilter;
  }
  return appUrl('/Admin-End/Contents/Contents.php') . '?' . http_build_query($query);
}

function buildReviewQueueUrl(string $channel, string $status, string $searchTerm, string $queueChannel, string $queueSearch): string
{
  $query = [
    'channel' => $channel,
    'status' => $status
  ];
  if ($searchTerm !== '') {
    $query['q'] = $searchTerm;
  }
  if ($queueChannel !== 'all') {
    $query['queue_channel'] = $queueChannel;
  }
  if ($queueSearch !== '') {
    $query['queue_q'] = $queueSearch;
  }
  return appUrl('/Admin-End/Contents/Contents.php') . '?' . http_build_query($query);
}

function announcement_ordered_channels(array $channels): array
{
  $order = ['public_news', 'public', 'website', 'sms', 'email'];
  $normalized = array_values(array_unique(array_filter($channels, function ($ch) use ($order) {
    return in_array((string)$ch, $order, true);
  })));

  usort($normalized, function ($a, $b) use ($order) {
    return array_search((string)$a, $order, true) <=> array_search((string)$b, $order, true);
  });

  return $normalized;
}

function ann_decode_faq_items(?string $json): array
{
  $decoded = json_decode((string)$json, true);
  if (!is_array($decoded)) {
    return [];
  }

  return array_values(array_filter(array_map(static function ($item): array {
    if (!is_array($item)) {
      return ['question' => '', 'answer' => ''];
    }

    return [
      'question' => trim((string)($item['question'] ?? '')),
      'answer' => trim((string)($item['answer'] ?? '')),
    ];
  }, $decoded), static function (array $item): bool {
    return $item['question'] !== '' || $item['answer'] !== '';
  }));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="icon" href="../../Images/favicon_sanjose.png?v=20260211">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Content Tools</title>

  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../../summernote-0.9.0-dist/summernote-lite.min.css?v=20260307-2" rel="stylesheet">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ContentManagementStyle.css?v=20260318-36">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260227-2">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/EditRequestsStyle.css?v=20260227-5">
  <style>
    #main-display {
      min-width: 0;
      width: 100%;
      overflow-x: hidden;
    }

    .announcement-shell .table-responsive {
      display: block;
      width: 100%;
      max-width: 100%;
      overflow-x: auto;
      overflow-y: visible;
      -webkit-overflow-scrolling: touch;
    }

    .announcement-shell #table-appData {
      width: max-content;
      min-width: 100%;
    }

    .announcement-shell #table-appData thead th,
    .announcement-shell #table-appData tbody td {
      min-width: 160px;
    }

    .announcement-shell #table-reviewQueueData {
      width: max-content;
      min-width: 100%;
    }

    .announcement-shell #table-reviewQueueData thead th,
    .announcement-shell #table-reviewQueueData tbody td {
      min-width: 160px;
    }

    .announcement-shell #table-appData th,
    .announcement-shell #table-appData td,
    .announcement-shell #table-reviewQueueData th,
    .announcement-shell #table-reviewQueueData td {
      white-space: nowrap;
    }

    #review-queue-card,
    #tracker-card {
      width: 100%;
      max-width: 100%;
      min-width: 0;
      overflow: hidden;
    }
  </style>
</head>
<body>
  <div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
      <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C; ">
        Content Tools
      </h2>
      <hr><br>

      <?php if ($isSuperAdmin): ?>
        <div id="review-queue-card" class="announcement-shell edit-requests-shell bg-white p-4 pt-3 rounded-4 shadow-sm border mb-4">
          <div class="review-queue-top d-flex flex-wrap align-items-start justify-content-between gap-3 mb-2">
            <div class="review-queue-title-wrap">
              <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <h5 class="mb-0 fw-bold review-queue-heading">Review Queue</h5>
              </div>
              <p class="small text-muted mb-0 review-queue-description">Shows pending content submitted by admin, official, and personnel accounts.</p>
            </div>

            <div class="admin-list-actions admin-list-actions--linear review-queue-actions">
              <form class="announcement-search-form" method="get" action="<?= htmlspecialchars(appUrl('/Admin-End/Contents/Contents.php')) ?>">
                <input type="hidden" name="channel" value="<?= htmlspecialchars($deliveryChannel) ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                <input type="hidden" name="q" value="<?= htmlspecialchars($searchTerm) ?>">
                <input type="hidden" name="queue_channel" value="<?= htmlspecialchars($queueChannelFilter) ?>">
                <div class="input-group admin-search">
                  <input type="text" name="queue_q" class="form-control" placeholder="Search title, audience, creator" value="<?= htmlspecialchars($queueSearchTerm) ?>">
                  <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                </div>
              </form>

              <button class="btn btn-outline-secondary btn-linear-control" type="button" data-bs-toggle="modal" data-bs-target="#modalReviewQueueFilter" title="Filter review queue" aria-label="Filter review queue">
                <i class="fas fa-filter"></i>
                <span class="visually-hidden">Filter review queue</span>
              </button>
              <button class="btn btn-outline-secondary btn-linear-control" type="button" data-bs-toggle="modal" data-bs-target="#modalReviewQueueColumns" title="Review queue columns" aria-label="Review queue columns">
                <i class="fa-solid fa-sliders"></i>
                <span class="visually-hidden">Review queue columns</span>
              </button>
              <a class="btn btn-outline-secondary btn-linear-control" id="btnReviewQueueRefresh" href="<?= htmlspecialchars(buildReviewQueueUrl($deliveryChannel, $statusFilter, $searchTerm, $queueChannelFilter, $queueSearchTerm)) ?>" title="Refresh review queue" aria-label="Refresh review queue">
                <i class="fa-solid fa-arrows-rotate"></i>
                <span class="visually-hidden">Refresh review queue</span>
              </a>
            </div>
          </div>

          <div class="table-responsive">
            <table id="table-reviewQueueData" class="table align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Title</th>
                  <th>Content Type</th>
                  <th>Audience</th>
                  <th>Channels</th>
                  <th>Created By</th>
                  <th class="text-end">Review Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$reviewQueueRows): ?>
                  <tr>
                    <td colspan="7" class="text-center text-muted py-4">No pending content in the review queue.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($reviewQueueRows as $item): ?>
                    <?php
                      $orderedQueueChannels = announcement_ordered_channels((array)$item['channels']);
                      $queueChannelsText = implode(', ', array_map(function ($ch) use ($channelLabels) {
                        return $channelLabels[$ch] ?? strtoupper($ch);
                      }, $orderedQueueChannels));
                    ?>
                    <tr>
                      <td><?= htmlspecialchars($item['title']) ?></td>
                      <td><?= htmlspecialchars($typeLabels[$item['content_type']] ?? 'Page Announcement') ?></td>
                      <td><?= htmlspecialchars($item['audience']) ?></td>
                      <td><?= htmlspecialchars($queueChannelsText) ?></td>
                      <td><?= htmlspecialchars($item['created_by']) ?></td>
                      <td class="text-end">
                        <div class="announcement-primary-actions justify-content-end">
                            <button
                            class="btn btn-primary btn-sm text-white btn-view-announcement"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#modalViewAnnouncement"
                            data-id="<?= htmlspecialchars($item['id']) ?>">
                            View
                          </button>
                          <form method="post" action="../../PhpFiles/Admin-End/announcementsActions.php" class="d-inline">
                            <?= csrfTokenField() ?>
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="announcement_id" value="<?= htmlspecialchars($item['id']) ?>">
                            <input type="hidden" name="channel" value="<?= htmlspecialchars($deliveryChannel) ?>">
                            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                            <input type="hidden" name="q" value="<?= htmlspecialchars($searchTerm) ?>">
                            <input type="hidden" name="queue_channel" value="<?= htmlspecialchars($queueChannelFilter) ?>">
                            <input type="hidden" name="queue_q" value="<?= htmlspecialchars($queueSearchTerm) ?>">
                            <button class="btn btn-success btn-sm" type="submit">Approve</button>
                          </form>
                          <form method="post" action="../../PhpFiles/Admin-End/announcementsActions.php" class="d-inline">
                            <?= csrfTokenField() ?>
                            <input type="hidden" name="action" value="deny">
                            <input type="hidden" name="announcement_id" value="<?= htmlspecialchars($item['id']) ?>">
                            <input type="hidden" name="channel" value="<?= htmlspecialchars($deliveryChannel) ?>">
                            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                            <input type="hidden" name="q" value="<?= htmlspecialchars($searchTerm) ?>">
                            <input type="hidden" name="queue_channel" value="<?= htmlspecialchars($queueChannelFilter) ?>">
                            <input type="hidden" name="queue_q" value="<?= htmlspecialchars($queueSearchTerm) ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Deny</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div class="resident-table-footer mt-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-2">
              <label class="small text-muted mb-0">Entries</label>
              <span class="small fw-semibold"><?= (int)count($reviewQueueRows) ?></span>
              <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis">
                Showing <?= (int)count($reviewQueueRows) ?> of <?= (int)count($reviewQueueBaseRows) ?> pending
              </span>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div id="tracker-card" class="announcement-shell edit-requests-shell bg-white p-4 pt-3 rounded-4 shadow-sm border">

        <div class="admin-list-toolbar mb-3 pt-2">
          <div class="admin-list-toolbar-start">
            <div class="admin-list-tabs">
              <a href="<?= htmlspecialchars(buildAnnouncementsUrl($deliveryChannel, 'all', $searchTerm)) ?>" data-filter="ALL" class="btn btn-outline-primary btn-sm status-filter-btn fw-semibold <?= $statusFilter === 'all' ? 'active' : '' ?>">
                &nbsp;&nbsp;All&nbsp;&nbsp;
              </a>
              <a href="<?= htmlspecialchars(buildAnnouncementsUrl($deliveryChannel, 'approved', $searchTerm)) ?>" data-filter="Approved" class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold <?= $statusFilter === 'approved' ? 'active' : '' ?>">
                &nbsp;&nbsp;Approved&nbsp;&nbsp;
              </a>
              <a href="<?= htmlspecialchars(buildAnnouncementsUrl($deliveryChannel, 'denied', $searchTerm)) ?>" data-filter="Denied" class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold <?= $statusFilter === 'denied' ? 'active' : '' ?>">
                &nbsp;&nbsp;Denied&nbsp;&nbsp;
              </a>
              <a href="<?= htmlspecialchars(buildAnnouncementsUrl($deliveryChannel, 'draft', $searchTerm)) ?>" data-filter="Draft" class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold <?= $statusFilter === 'draft' ? 'active' : '' ?>">
                &nbsp;&nbsp;Draft&nbsp;&nbsp;
              </a>
              <a href="<?= htmlspecialchars(buildAnnouncementsUrl($deliveryChannel, 'pending', $searchTerm)) ?>" data-filter="Pending" class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold has-notif <?= $statusFilter === 'pending' ? 'active' : '' ?>">
                &nbsp;&nbsp;Pending
                <?php if ($statusCounts['pending'] > 0): ?>
                  <span class="pending-count-badge"><?= (int)$statusCounts['pending'] ?></span>
                <?php endif; ?>
              </a>
            </div>
          </div>

          <div class="admin-list-toolbar-end">
            <div class="admin-list-actions admin-list-actions--linear">
              <form class="announcement-search-form" method="get" action="<?= htmlspecialchars(appUrl('/Admin-End/Contents/Contents.php')) ?>">
                <input type="hidden" name="channel" value="<?= htmlspecialchars($deliveryChannel) ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                <div class="input-group admin-search">
                  <input type="text" id="searchInput" name="q" class="form-control" placeholder="Search title, audience, creator" value="<?= htmlspecialchars($searchTerm) ?>">
                  <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                </div>
              </form>

              <button class="btn btn-outline-secondary btn-linear-control" type="button" data-bs-toggle="modal" data-bs-target="#modalFilter" id="filterButton" title="Filter" aria-label="Filter">
                <i class="fas fa-filter"></i>
                <span class="visually-hidden">Filter</span>
              </button>
              <button class="btn btn-outline-secondary btn-linear-control" type="button" data-bs-toggle="modal" data-bs-target="#modalTableColumns" id="btnAnnouncementsColumns" title="Columns" aria-label="Columns">
                <i class="fa-solid fa-sliders"></i>
                <span class="visually-hidden">Columns</span>
              </button>
              <a class="btn btn-outline-secondary btn-linear-control" id="btnAnnouncementsTableRefresh" href="<?= htmlspecialchars(buildAnnouncementsUrl($deliveryChannel, $statusFilter)) ?>" title="Refresh table" aria-label="Refresh table">
                <i class="fa-solid fa-arrows-rotate"></i>
                <span class="visually-hidden">Refresh</span>
              </a>
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table id="table-appData" class="table align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Title</th>
                <th>Content Type</th>
                <th>Audience</th>
                <th>Channels</th>
                <th>Created By</th>
                <th>Publish Date</th>
                <th class="announcement-status-col">Status</th>
                <th class="text-end announcement-action-col">Actions</th>
              </tr>
            </thead>
            <tbody id="tableBody">
              <?php if (!$visibleRows): ?>
                <tr>
                  <td colspan="9" class="text-center text-muted py-4">No content items match the current filters.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($visibleRows as $item): ?>
                  <?php
                    $status = $item['status'];
                    $isSuperAdminCreated = ((string)($item['created_by_role'] ?? '') === 'superadmin');
                    $isOwnedByCurrentUser = ann_is_owned_by_current_user($item, $currentUserId, $currentUserDisplayLabel);
                    $disableSuperAdminEdit = $isSuperAdmin && $status === 'pending';
                    $reviewResult = strtolower((string)($item['review_result'] ?? ''));
                    $displayStatus = ($status === 'draft' && $reviewResult === 'denied' && $isOwnedByCurrentUser) ? 'denied' : $status;
                    $statusClass = $displayStatus === 'approved'
                      ? 'approved'
                      : ($displayStatus === 'pending'
                        ? 'pending'
                        : ($displayStatus === 'denied' ? 'denied' : 'archived'));
                    $orderedChannels = announcement_ordered_channels((array)$item['channels']);
                    $channelsText = implode(', ', array_map(function ($ch) use ($channelLabels) {
                      return $channelLabels[$ch] ?? strtoupper($ch);
                    }, $orderedChannels));
                  ?>
                  <tr>
                    <td><?= htmlspecialchars($item['title']) ?></td>
                    <td><?= htmlspecialchars($typeLabels[$item['content_type']] ?? 'Page Announcement') ?></td>
                    <td><?= htmlspecialchars($item['audience']) ?></td>
                    <td><?= htmlspecialchars($channelsText) ?></td>
                    <td><?= htmlspecialchars($item['created_by']) ?></td>
                    <td><?= htmlspecialchars($item['publish_date']) ?></td>
                    <td class="announcement-status-col"><span class="status-pill <?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars($statusLabels[$displayStatus] ?? $displayStatus) ?></span></td>
                    <td class="text-end announcement-action-col">
                      <div class="announcement-row-actions">
                        <div class="announcement-primary-actions">
                          <button
                            class="btn btn-primary btn-sm text-white btn-view-announcement"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#modalViewAnnouncement"
                            data-id="<?= htmlspecialchars($item['id']) ?>">
                            View
                          </button>
                          <?php if ($isSuperAdmin || $isOwnedByCurrentUser): ?>
                            <?php if ($disableSuperAdminEdit): ?>
                              <button
                                class="btn btn-warning btn-sm text-dark btn-edit-announcement disabled"
                                type="button"
                                disabled
                                aria-disabled="true"
                                title="Pending content in the review queue cannot be edited here.">
                                Edit
                              </button>
                            <?php else: ?>
                              <button
                                class="btn btn-warning btn-sm text-dark btn-edit-announcement"
                                type="button"
                                data-bs-toggle="modal"
                                data-bs-target="#modalEditAnnouncement"
                                data-id="<?= htmlspecialchars($item['id']) ?>">
                                Edit
                              </button>
                            <?php endif; ?>
                          <?php endif; ?>
                        </div>
                        <?php if ($status === 'pending' && $isSuperAdmin && !$isSuperAdminCreated): ?>
                          <div class="announcement-review-actions"></div>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="resident-table-footer mt-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
          <div class="d-flex align-items-center gap-2">
            <label for="entriesPerPageInput" class="small text-muted mb-0">Entries</label>
            <input
              id="entriesPerPageInput"
              type="number"
              min="1"
              step="1"
              value="20"
              class="form-control form-control-sm resident-entries-input"
            />
            <?php if ($isSuperAdmin): ?>
              <span id="announcementsVisibleCountBadge" class="badge rounded-pill bg-warning-subtle text-warning-emphasis">
                Showing <?= (int)count($visibleRows) ?> of <?= (int)count($filteredByChannel) ?>
              </span>
            <?php endif; ?>
          </div>
          <nav aria-label="Announcements pagination">
            <ul class="pagination pagination-sm mb-0" id="announcementsPagination"></ul>
          </nav>
        </div>

        <div class="modal fade" id="modalFilter" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
          <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content p-4" method="get" action="<?= htmlspecialchars(appUrl('/Admin-End/Contents/Contents.php')) ?>">
              <input type="hidden" name="q" value="<?= htmlspecialchars($searchTerm) ?>">

              <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Filter Content</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>

              <hr>

              <div class="modal-body">
                <div class="mb-3">
                  <label class="fw-bold small mb-2">Status</label>
                  <div class="d-flex flex-column gap-2">
                    <label class="d-flex align-items-center gap-2">
                      <input class="form-check-input m-0" type="radio" name="status" value="all" <?= $statusFilter === 'all' ? 'checked' : '' ?>>
                      <span>All</span>
                    </label>
                    <label class="d-flex align-items-center gap-2">
                      <input class="form-check-input m-0" type="radio" name="status" value="approved" <?= $statusFilter === 'approved' ? 'checked' : '' ?>>
                      <span>Approved</span>
                    </label>
                    <label class="d-flex align-items-center gap-2">
                      <input class="form-check-input m-0" type="radio" name="status" value="denied" <?= $statusFilter === 'denied' ? 'checked' : '' ?>>
                      <span>Denied</span>
                    </label>
                    <label class="d-flex align-items-center gap-2">
                      <input class="form-check-input m-0" type="radio" name="status" value="pending" <?= $statusFilter === 'pending' ? 'checked' : '' ?>>
                      <span>Pending</span>
                    </label>
                    <label class="d-flex align-items-center gap-2">
                      <input class="form-check-input m-0" type="radio" name="status" value="draft" <?= $statusFilter === 'draft' ? 'checked' : '' ?>>
                      <span>Draft</span>
                    </label>
                  </div>
                </div>

                <div class="mb-2">
                  <label class="fw-bold small mb-2">Delivery Channel</label>
                  <div class="d-flex flex-column gap-2">
                    <label class="d-flex align-items-center gap-2">
                      <input class="form-check-input m-0" type="radio" name="channel" value="all" <?= $deliveryChannel === 'all' ? 'checked' : '' ?>>
                      <span>All Channels</span>
                    </label>
                    <label class="d-flex align-items-center gap-2">
                      <input class="form-check-input m-0" type="radio" name="channel" value="website" <?= $deliveryChannel === 'website' ? 'checked' : '' ?>>
                      <span>Account Page</span>
                    </label>
                    <label class="d-flex align-items-center gap-2">
                      <input class="form-check-input m-0" type="radio" name="channel" value="public" <?= $deliveryChannel === 'public' ? 'checked' : '' ?>>
                      <span>Guest Page</span>
                    </label>
                    <label class="d-flex align-items-center gap-2">
                      <input class="form-check-input m-0" type="radio" name="channel" value="public_news" <?= $deliveryChannel === 'public_news' ? 'checked' : '' ?>>
                      <span>Public News</span>
                    </label>
                    <label class="d-flex align-items-center gap-2">
                      <input class="form-check-input m-0" type="radio" name="channel" value="sms" <?= $deliveryChannel === 'sms' ? 'checked' : '' ?>>
                      <span>SMS</span>
                    </label>
                    <label class="d-flex align-items-center gap-2">
                      <input class="form-check-input m-0" type="radio" name="channel" value="email" <?= $deliveryChannel === 'email' ? 'checked' : '' ?>>
                      <span>Email</span>
                    </label>
                  </div>
                </div>
              </div>

              <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Apply Filter</button>
                <a href="<?= htmlspecialchars(buildAnnouncementsUrl('all', 'all')) ?>" class="btn btn-warning"><i class="fas fa-undo"></i>&nbsp;Reset</a>
              </div>
            </form>
          </div>
        </div>
      </div>
    </main>
  </div>

  <div class="modal fade" id="modalTableColumns" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Columns</h5>
        </div>
        <div class="modal-body">
          <div class="row g-2" id="tableColumnsList"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" id="btnTableColumnsReset">Reset</button>
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalDeleteAnnouncement" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content p-3" id="deleteAnnouncementForm" method="post" action="../../PhpFiles/Admin-End/announcementsActions.php">
        <?= csrfTokenField() ?>
        <input type="hidden" id="deleteAnnouncementIdInput" name="announcement_id" value="">
        <input type="hidden" id="deleteChannelInput" name="channel" value="<?= htmlspecialchars($deliveryChannel) ?>">
        <input type="hidden" id="deleteStatusInput" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
        <input type="hidden" id="deleteQueryInput" name="q" value="<?= htmlspecialchars($searchTerm) ?>">
        <input type="hidden" id="deleteQueueChannelInput" name="queue_channel" value="<?= htmlspecialchars($queueChannelFilter) ?>">
        <input type="hidden" id="deleteQueueQueryInput" name="queue_q" value="<?= htmlspecialchars($queueSearchTerm) ?>">
        <input type="hidden" name="action" value="delete">
        <div class="modal-header justify-content-center border-0 pb-0">
          <h5 class="modal-title fw-bold text-center w-100">Delete Content</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <hr class="my-2">
        <div class="modal-body text-center">
          <p class="mb-2">Are you sure you want to delete this content item?</p>
          <p class="fw-semibold mb-0" id="deleteAnnouncementTitle">-</p>
          <p class="small text-muted mt-2 mb-0">This action cannot be undone.</p>
        </div>
        <div class="modal-footer border-0 pt-0 d-flex gap-2 w-100">
          <button type="button" class="btn btn-secondary flex-fill" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger flex-fill">Delete</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal fade" id="modalReviewQueueFilter" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content p-4" method="get" action="<?= htmlspecialchars(appUrl('/Admin-End/Contents/Contents.php')) ?>">
        <input type="hidden" name="channel" value="<?= htmlspecialchars($deliveryChannel) ?>">
        <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
        <input type="hidden" name="q" value="<?= htmlspecialchars($searchTerm) ?>">
        <input type="hidden" name="queue_q" value="<?= htmlspecialchars($queueSearchTerm) ?>">

        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold">Filter Review Queue</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <hr>

        <div class="modal-body">
          <div class="mb-2">
            <label class="fw-bold small mb-2">Delivery Channel</label>
            <div class="d-flex flex-column gap-2">
              <label class="d-flex align-items-center gap-2">
                <input class="form-check-input m-0" type="radio" name="queue_channel" value="all" <?= $queueChannelFilter === 'all' ? 'checked' : '' ?>>
                <span>All Channels</span>
              </label>
                <label class="d-flex align-items-center gap-2">
                  <input class="form-check-input m-0" type="radio" name="queue_channel" value="website" <?= $queueChannelFilter === 'website' ? 'checked' : '' ?>>
                  <span>Account Page</span>
                </label>
                <label class="d-flex align-items-center gap-2">
                  <input class="form-check-input m-0" type="radio" name="queue_channel" value="public" <?= $queueChannelFilter === 'public' ? 'checked' : '' ?>>
                  <span>Guest Page</span>
                </label>
                <label class="d-flex align-items-center gap-2">
                  <input class="form-check-input m-0" type="radio" name="queue_channel" value="public_news" <?= $queueChannelFilter === 'public_news' ? 'checked' : '' ?>>
                  <span>Public News</span>
                </label>
                <label class="d-flex align-items-center gap-2">
                  <input class="form-check-input m-0" type="radio" name="queue_channel" value="sms" <?= $queueChannelFilter === 'sms' ? 'checked' : '' ?>>
                <span>SMS</span>
              </label>
              <label class="d-flex align-items-center gap-2">
                <input class="form-check-input m-0" type="radio" name="queue_channel" value="email" <?= $queueChannelFilter === 'email' ? 'checked' : '' ?>>
                <span>Email</span>
              </label>
            </div>
          </div>
        </div>

        <div class="modal-footer border-0">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Apply Filter</button>
          <a href="<?= htmlspecialchars(buildReviewQueueUrl($deliveryChannel, $statusFilter, $searchTerm, 'all', '')) ?>" class="btn btn-warning"><i class="fas fa-undo"></i>&nbsp;Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="modal fade" id="modalReviewQueueColumns" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Review Queue Columns</h5>
        </div>
        <div class="modal-body">
          <div class="row g-2" id="reviewQueueColumnsList"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" id="btnReviewQueueColumnsReset">Reset</button>
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalViewAnnouncement" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable announcement-details-dialog">
      <div class="modal-content announcement-details-content border-0 rounded-2 p-4">
        <div class="modal-header border-0">
          <h5 class="modal-title announcement-details-title mb-0">
            Content Details: <span id="viewAnnouncementRef" class="announcement-details-id">#-</span>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="announcement-details-card">
            <h5 class="announcement-card-title">Content Summary</h5>
            <div class="row g-3">
              <div class="col-md-6">
                <p class="announcement-detail-label">Title</p>
                <p class="announcement-detail-value" id="viewAnnouncementTitle">-</p>
              </div>
              <div class="col-md-6">
                <p class="announcement-detail-label">Created By</p>
                <p class="announcement-detail-value" id="viewAnnouncementCreatedBy">-</p>
              </div>
              <div class="col-md-6">
                <p class="announcement-detail-label">Content Type</p>
                <p class="announcement-detail-value" id="viewAnnouncementType">-</p>
              </div>
              <div class="col-md-6">
                <p class="announcement-detail-label">Audience</p>
                <p class="announcement-detail-value" id="viewAnnouncementAudience">-</p>
              </div>
              <div class="col-md-6" id="viewAnnouncementPlacementsGroup">
                <p class="announcement-detail-label">Page Placement</p>
                <p class="announcement-detail-value" id="viewAnnouncementPlacements">-</p>
              </div>
              <div class="col-md-6" id="viewAnnouncementChannelsGroup">
                <p class="announcement-detail-label">Delivery</p>
                <p class="announcement-detail-value" id="viewAnnouncementChannels">-</p>
              </div>
              <div class="col-12 d-none" id="viewDeliveryMeta">
                <div class="row g-3">
                  <div class="col-md-6 d-none" id="viewSmsMessageGroup">
                    <p class="announcement-detail-label">SMS Message</p>
                    <div class="announcement-content-surface" id="viewAnnouncementSmsMessage">-</div>
                  </div>
                  <div class="col-md-6 d-none" id="viewEmailSubjectGroup">
                    <p class="announcement-detail-label">Email Subject</p>
                    <p class="announcement-detail-value" id="viewAnnouncementEmailSubject">-</p>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <p class="announcement-detail-label">Status</p>
                <p class="announcement-detail-value announcement-detail-status status-draft" id="viewAnnouncementStatus">-</p>
              </div>
              <div class="col-md-6">
                <p class="announcement-detail-label">Publish Date</p>
                <p class="announcement-detail-value" id="viewAnnouncementPublishDate">-</p>
              </div>
            </div>
          </div>
          <div class="announcement-details-card announcement-details-card--content mt-4">
            <h5 class="announcement-card-title" id="viewAnnouncementContentHeading">Content</h5>
            <div id="viewAnnouncementReviewNotice" class="alert alert-danger d-none mb-3" role="alert"></div>
            <div id="viewAnnouncementContent" class="announcement-content-surface"></div>
            <div id="viewAnnouncementDualContent" class="d-none">
              <div class="announcement-details-card announcement-details-card--content mt-3">
                <h6 class="announcement-card-title announcement-placement-title">News Section</h6>
                <div id="viewAnnouncementNewsContent" class="announcement-content-surface"></div>
              </div>
              <div class="announcement-details-card announcement-details-card--content mt-3">
                <h6 class="announcement-card-title announcement-placement-title">Announcements</h6>
                <div id="viewAnnouncementPublicContent" class="announcement-content-surface"></div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0">
          <div class="announcement-modal-footer-start">
            <button type="button" class="btn btn-warning text-dark d-none" id="btnViewEditAnnouncement">Edit</button>
          </div>
          <div class="announcement-modal-footer-end">
            <button type="button" class="btn btn-outline-danger d-none" id="btnViewDeleteAnnouncement">Delete</button>
            <button type="button" class="btn btn-secondary" id="btnViewCloseAnnouncement" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalEditAnnouncement" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <form class="modal-content border-0 rounded-2 p-4" id="editAnnouncementForm" method="post" action="../../PhpFiles/Admin-End/announcementsActions.php">
        <?= csrfTokenField() ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" id="editAnnouncementIdInput" name="announcement_id" value="">
        <input type="hidden" name="channel" value="<?= htmlspecialchars($deliveryChannel) ?>">
        <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
        <input type="hidden" name="q" value="<?= htmlspecialchars($searchTerm) ?>">
        <input type="hidden" name="queue_channel" value="<?= htmlspecialchars($queueChannelFilter) ?>">
        <input type="hidden" name="queue_q" value="<?= htmlspecialchars($queueSearchTerm) ?>">
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold">Edit Content</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="announcement-form-card">
            <h5 class="announcement-card-title">Content Details</h5>
            <div class="mb-3">
              <label class="form-label">Content Type</label>
              <input type="text" class="form-control" id="editAnnouncementTypeDisplay" value="-" readonly>
            </div>
            <div class="mb-3" id="editAudienceGroup">
              <label for="editAnnouncementAudienceInput" class="form-label">Audience</label>
              <input type="text" class="form-control" id="editAnnouncementAudienceInput" name="audience" required>
            </div>
            <div class="mb-3" id="editPagePlacementGroup">
              <label class="form-label d-block">Page Placement</label>
              <div class="d-flex flex-wrap gap-3" id="editPageDestinationGroup">
                <label class="form-check-label d-flex align-items-center gap-2">
                  <input class="form-check-input m-0 edit-placement-checkbox" type="checkbox" name="placements[]" value="public_news" id="editPlacementPublicNews">
                  <span>News Section</span>
                </label>
                <label class="form-check-label d-flex align-items-center gap-2">
                  <input class="form-check-input m-0 edit-placement-checkbox" type="checkbox" name="placements[]" value="announcement" id="editPlacementAnnouncement">
                  <span>Announcements</span>
                </label>
              </div>
            </div>
            <div class="mb-3" id="editAdditionalDeliveryGroup">
              <label class="form-label d-block" id="editAdditionalDeliveryLabel">Delivery</label>
              <div class="d-flex flex-wrap gap-3">
                <label class="form-check-label d-flex align-items-center gap-2">
                  <input class="form-check-input m-0" type="checkbox" name="channels[]" value="public" id="editChannelPublic">
                  <span>Guest Page</span>
                </label>
                <label class="form-check-label d-flex align-items-center gap-2">
                  <input class="form-check-input m-0" type="checkbox" name="channels[]" value="website" id="editChannelWebsite">
                  <span>Account Page</span>
                </label>
                </div>
              <div class="d-flex flex-wrap gap-3 mt-3" id="editDirectDeliveryGroup">
                <label class="form-check-label d-flex align-items-center gap-2">
                  <input class="form-check-input m-0" type="checkbox" name="channels[]" value="sms" id="editChannelSms">
                  <span>SMS</span>
                </label>
                <label class="form-check-label d-flex align-items-center gap-2">
                  <input class="form-check-input m-0" type="checkbox" name="channels[]" value="email" id="editChannelEmail">
                  <span>Email</span>
                </label>
              </div>
              <div id="editDeliveryMessageFields" class="d-none mt-3">
                <div class="row g-3">
                  <div class="col-12 col-lg-7">
                    <label for="editSmsMessageInput" class="form-label">SMS Message</label>
                    <textarea id="editSmsMessageInput" class="form-control" name="sms_message" rows="4" maxlength="320"></textarea>
                    <small class="text-muted" id="editSmsCounter">0 / 320 characters</small>
                  </div>
                  <div class="col-12 col-lg-5">
                    <label for="editEmailSubjectInput" class="form-label">Email Subject</label>
                    <input type="text" class="form-control" id="editEmailSubjectInput" name="email_subject" placeholder="Enter email subject">
                  </div>
                </div>
              </div>
            </div>
            <div class="row g-3">
              <?php if ($isSuperAdmin): ?>
                <div class="col-md-6">
                  <label class="form-label">Status</label>
                  <div class="announcement-detail-value announcement-detail-status status-draft" id="editAnnouncementStatusDisplay">-</div>
                  <input type="hidden" id="editAnnouncementStatusInput" name="status_update" value="draft">
                </div>
                <div class="col-md-6">
              <?php else: ?>
                <input type="hidden" id="editAnnouncementStatusInput" name="status_update" value="draft">
                <div class="col-12">
              <?php endif; ?>
                <label for="editAnnouncementPublishDateInput" class="form-label">Publish Date</label>
                <input type="text" class="form-control" id="editAnnouncementPublishDateInput" name="publish_date" placeholder="YYYY-MM-DD HH:MM">
              </div>
            </div>
          </div>
          <div class="announcement-form-card mt-4">
            <h5 class="announcement-card-title">Content</h5>
            <div id="editSharedContentFields">
              <div class="announcement-primary-title-wrap">
                <label for="editAnnouncementTitleInput" class="form-label" id="editTitleLabel">Title</label>
                <input type="text" class="form-control announcement-primary-title-input" id="editAnnouncementTitleInput" name="title" required>
              </div>
              <label for="editAnnouncementContentInput" class="form-label" id="editBodyLabel">Body</label>
              <div id="editAnnouncementEditor"></div>
            </div>
            <input type="hidden" id="editAnnouncementContentInput" name="content_html" required>
            <div id="editDualPlacementFields" class="d-none">
              <div class="announcement-dual-card mt-2">
                <h6 class="announcement-card-title announcement-placement-title">News Section</h6>
                <label for="editPublicNewsTitle" class="form-label fw-semibold">Title</label>
                <input id="editPublicNewsTitle" name="public_news_title" type="text" class="form-control announcement-secondary-title-input" placeholder="Enter news section title">
                <label class="form-label mt-3">Body</label>
                <div id="editPublicNewsEditor"></div>
                <input type="hidden" id="editPublicNewsContent" name="public_news_content_html">
              </div>
              <div class="announcement-dual-card mt-3">
                <h6 class="announcement-card-title announcement-placement-title">Announcements</h6>
                <label for="editPublicAnnouncementTitle" class="form-label fw-semibold">Title</label>
                <input id="editPublicAnnouncementTitle" name="public_title" type="text" class="form-control announcement-secondary-title-input" placeholder="Enter announcements title">
                <label class="form-label mt-3">Body</label>
                <div id="editPublicAnnouncementEditor"></div>
                <input type="hidden" id="editPublicAnnouncementContent" name="public_content_html">
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0">
          <div class="announcement-modal-footer-start">
            <button type="submit" class="btn btn-warning text-dark" id="btnEditSaveDraft">Save Changes</button>
            <button type="submit" class="btn btn-primary d-none" id="btnEditSubmitReview">Submit for Review</button>
          </div>
          <div class="announcement-modal-footer-end">
            <button type="button" class="btn btn-outline-danger d-none" id="btnEditDeleteAnnouncement">Delete</button>
            <button type="button" class="btn btn-secondary" id="btnEditCancel">Close</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="modal fade" id="modalDeniedAnnouncementDraftNotice" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-0 pb-0 bg-white">
          <h5 class="modal-title w-100 text-center text-dark">Draft Notice</h5>
        </div>
        <hr class="my-0">
        <div class="modal-body text-center">
          <p class="mb-0">This content item was denied. If you continue editing, your changes will now be saved as draft.</p>
        </div>
        <div class="modal-footer border-0 pt-0 d-flex gap-2">
          <button type="button" class="btn btn-warning text-dark flex-fill" id="btnConfirmDeniedAnnouncementDraftNotice">Continue Editing</button>
          <button type="button" class="btn btn-secondary flex-fill" data-bs-dismiss="modal">Later</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalDeniedAnnouncementSaveConfirm" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-0 pb-0 bg-white">
          <h5 class="modal-title w-100 text-center text-dark">Confirm Save Changes</h5>
        </div>
        <hr class="my-0">
        <div class="modal-body text-center">
          <p class="mb-0">Are you sure you want to save these changes? This content item will be moved to draft status.</p>
        </div>
        <div class="modal-footer border-0 pt-0 d-flex gap-2">
          <button type="button" class="btn btn-warning text-dark flex-fill" id="btnConfirmDeniedAnnouncementSave">Yes, Save Changes</button>
          <button type="button" class="btn btn-secondary flex-fill" data-bs-dismiss="modal">Later</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalDeniedAnnouncementResubmitConfirm" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-0 pb-0 bg-white">
          <h5 class="modal-title w-100 text-center text-dark">Confirm Resubmission</h5>
        </div>
        <hr class="my-0">
        <div class="modal-body text-center">
          <p class="mb-0">Are you sure that you are ready to submit again?</p>
        </div>
        <div class="modal-footer border-0 pt-0 d-flex gap-2">
          <button type="button" class="btn btn-primary flex-fill" id="btnConfirmDeniedAnnouncementResubmit">Yes, Submit Again</button>
          <button type="button" class="btn btn-secondary flex-fill" data-bs-dismiss="modal">Later</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalApprovedAnnouncementSaveConfirm" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-0 pb-0 bg-white">
          <h5 class="modal-title w-100 text-center text-dark">Confirm Save Changes</h5>
        </div>
        <hr class="my-0">
        <div class="modal-body text-center">
          <p class="mb-0">Are you sure you want to save these changes? These changes will be submitted for review.</p>
        </div>
        <div class="modal-footer border-0 pt-0 d-flex gap-2">
          <button type="button" class="btn btn-warning text-dark flex-fill" id="btnConfirmApprovedAnnouncementSave">Yes, Save Changes</button>
          <button type="button" class="btn btn-secondary flex-fill" data-bs-dismiss="modal">Later</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalPendingAnnouncementSaveConfirm" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-0 pb-0 bg-white">
          <h5 class="modal-title w-100 text-center text-dark">Confirm Save Changes</h5>
        </div>
        <hr class="my-0">
        <div class="modal-body text-center">
          <p class="mb-0">Are you sure you want to save these changes and update the pending approval for review?</p>
        </div>
        <div class="modal-footer border-0 pt-0 d-flex gap-2">
          <button type="button" class="btn btn-warning text-dark flex-fill" id="btnConfirmPendingAnnouncementSave">Yes, Save Changes</button>
          <button type="button" class="btn btn-secondary flex-fill" data-bs-dismiss="modal">Later</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalSuperAdminApprovedCloseConfirm" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-0 pb-0 bg-white">
          <h5 class="modal-title w-100 text-center text-dark">Confirm Close</h5>
        </div>
        <hr class="my-0">
        <div class="modal-body text-center">
          <p class="mb-0">Are you sure you want to close? Any unsaved changes will be lost.</p>
        </div>
        <div class="modal-footer border-0 pt-0 d-flex gap-2">
          <button type="button" class="btn btn-primary flex-fill" id="btnConfirmSuperAdminApprovedClose">Yes, Close</button>
          <button type="button" class="btn btn-secondary flex-fill" data-bs-dismiss="modal">Later</button>
        </div>
      </div>
    </div>
  </div>

  <?php if ($isSuperAdmin): ?>
    <div class="modal fade" id="modalSuperAdminRepostConfirm" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header border-0 pb-0 bg-white">
            <h5 class="modal-title w-100 text-center text-dark">Confirm Save Changes</h5>
          </div>
          <hr class="my-0">
          <div class="modal-body text-center">
            <p class="mb-0">Are you ready to save these changes? When you save, this content item will be posted.</p>
          </div>
          <div class="modal-footer border-0 pt-0 d-flex gap-2">
            <button type="button" class="btn btn-primary flex-fill" id="btnConfirmSuperAdminRepost">Yes, Save Changes</button>
            <button type="button" class="btn btn-secondary flex-fill" data-bs-dismiss="modal">Later</button>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if (is_array($flash) && isset($flash['message'])): ?>
    <?php
      $flashType = strtolower((string)($flash['type'] ?? 'info'));
      $flashTitle = 'Notice';
      if ($flashType === 'success') {
        $flashTitle = 'Success';
      } elseif ($flashType === 'warning') {
        $flashTitle = 'Warning';
      } elseif ($flashType === 'danger') {
        $flashTitle = 'Action Failed';
      }
    ?>
    <div class="modal fade" id="modalAnnouncementFlash" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header border-0 pb-0 bg-white">
            <h5 class="modal-title w-100 text-center text-dark"><?= htmlspecialchars($flashTitle) ?></h5>
          </div>
          <hr class="my-0">
          <div class="modal-body text-center">
            <p class="mb-0"><?= htmlspecialchars((string)$flash['message']) ?></p>
          </div>
          <div class="modal-footer border-0 pt-0 d-flex gap-2">
            <button type="button" class="btn btn-primary flex-fill" data-bs-dismiss="modal">Okay</button>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="../../summernote-0.9.0-dist/summernote-lite.min.js?v=20260307-2"></script>
  <script id="announcementDataPayload" type="application/json"><?= json_encode($announcementDetailsMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
  <script>
    let ANNOUNCEMENT_DATA = JSON.parse(document.getElementById("announcementDataPayload")?.textContent || "{}");
    const CURRENT_USER_ID = <?= json_encode($currentUserId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const CURRENT_USER_DISPLAY = <?= json_encode($currentUserDisplayLabel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const ANNOUNCEMENT_CHANNEL_LABELS = {
      public: "Guest Page",
      public_news: "News Section",
      website: "Account Page",
      sms: "SMS",
      email: "Email"
    };
    const ANNOUNCEMENT_PLACEMENT_LABELS = {
      public_news: "News Section",
      announcement: "Announcements"
    };
    const ANNOUNCEMENT_TYPE_LABELS = <?= json_encode($typeLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    window.ADMIN_TABLE_COLUMNS_CONFIG = {
      tableSelector: "#table-appData",
      modalId: "modalTableColumns",
      listId: "tableColumnsList",
      resetBtnId: "btnTableColumnsReset",
      storageKey: "admin_cols_announcements_v2",
      defaultHiddenIdxs: []
    };

    (function () {
      const modalEl = document.getElementById("modalDeleteAnnouncement");
      const titleEl = document.getElementById("deleteAnnouncementTitle");
      const idEl = document.getElementById("deleteAnnouncementIdInput");
      const channelEl = document.getElementById("deleteChannelInput");
      const statusEl = document.getElementById("deleteStatusInput");
      const queryEl = document.getElementById("deleteQueryInput");
      const queueChannelEl = document.getElementById("deleteQueueChannelInput");
      const queueQueryEl = document.getElementById("deleteQueueQueryInput");
      if (!modalEl || !titleEl || !idEl || !channelEl || !statusEl || !queryEl || !queueChannelEl || !queueQueryEl) return;

      modalEl.addEventListener("show.bs.modal", function (event) {
        const triggerBtn = event.relatedTarget;
        if (!triggerBtn) {
          return;
        }
        const id = triggerBtn?.getAttribute("data-id") || "";
        const channel = triggerBtn?.getAttribute("data-channel") || "all";
        const status = triggerBtn?.getAttribute("data-status") || "all";
        const q = triggerBtn?.getAttribute("data-q") || "";
        const queueChannel = triggerBtn?.getAttribute("data-queue-channel") || "all";
        const queueQ = triggerBtn?.getAttribute("data-queue-q") || "";
        const title = triggerBtn?.getAttribute("data-title") || "-";
        idEl.value = id;
        channelEl.value = channel;
        statusEl.value = status;
        queryEl.value = q;
        queueChannelEl.value = queueChannel;
        queueQueryEl.value = queueQ;
        titleEl.textContent = title;
      });
    })();

    (function () {
      const viewModal = document.getElementById("modalViewAnnouncement");
      const editModal = document.getElementById("modalEditAnnouncement");
      const editForm = document.getElementById("editAnnouncementForm");
      const editCancelBtn = document.getElementById("btnEditCancel");
      const editDeleteBtn = document.getElementById("btnEditDeleteAnnouncement");
      const editSaveDraftBtn = document.getElementById("btnEditSaveDraft");
      const editSubmitReviewBtn = document.getElementById("btnEditSubmitReview");
      const deniedSaveModalEl = document.getElementById("modalDeniedAnnouncementSaveConfirm");
      const confirmDeniedSaveBtn = document.getElementById("btnConfirmDeniedAnnouncementSave");
      const deniedResubmitModalEl = document.getElementById("modalDeniedAnnouncementResubmitConfirm");
      const confirmDeniedResubmitBtn = document.getElementById("btnConfirmDeniedAnnouncementResubmit");
      const approvedSaveModalEl = document.getElementById("modalApprovedAnnouncementSaveConfirm");
      const confirmApprovedSaveBtn = document.getElementById("btnConfirmApprovedAnnouncementSave");
      const pendingSaveModalEl = document.getElementById("modalPendingAnnouncementSaveConfirm");
      const confirmPendingSaveBtn = document.getElementById("btnConfirmPendingAnnouncementSave");
      const superAdminApprovedCloseModalEl = document.getElementById("modalSuperAdminApprovedCloseConfirm");
      const confirmSuperAdminApprovedCloseBtn = document.getElementById("btnConfirmSuperAdminApprovedClose");
      const deniedDraftNoticeModalEl = document.getElementById("modalDeniedAnnouncementDraftNotice");
      const confirmDeniedDraftNoticeBtn = document.getElementById("btnConfirmDeniedAnnouncementDraftNotice");
      const viewEditBtn = document.getElementById("btnViewEditAnnouncement");
      const superAdminRepostModalEl = document.getElementById("modalSuperAdminRepostConfirm");
      const confirmSuperAdminRepostBtn = document.getElementById("btnConfirmSuperAdminRepost");
      const deleteBtn = document.getElementById("btnViewDeleteAnnouncement");
      if (!viewModal || !editModal) return;
      const isSuperAdminSession = <?= $isSuperAdmin ? 'true' : 'false' ?>;
      const editEditorEl = $("#editAnnouncementEditor");
      const editPublicNewsEditorEl = $("#editPublicNewsEditor");
      const editPublicAnnouncementEditorEl = $("#editPublicAnnouncementEditor");
      const editPlacementPublicNews = document.getElementById("editPlacementPublicNews");
      const editPlacementAnnouncement = document.getElementById("editPlacementAnnouncement");
      const editSharedContentFields = document.getElementById("editSharedContentFields");
      const editDualPlacementFields = document.getElementById("editDualPlacementFields");
      const editPublicNewsTitleInput = document.getElementById("editPublicNewsTitle");
      const editPublicAnnouncementTitleInput = document.getElementById("editPublicAnnouncementTitle");
      const editPublicNewsContentInput = document.getElementById("editPublicNewsContent");
      const editPublicAnnouncementContentInput = document.getElementById("editPublicAnnouncementContent");
      const editAnnouncementTypeDisplay = document.getElementById("editAnnouncementTypeDisplay");
      const editAudienceGroup = document.getElementById("editAudienceGroup");
      const editPagePlacementGroup = document.getElementById("editPagePlacementGroup");
      const editAdditionalDeliveryGroup = document.getElementById("editAdditionalDeliveryGroup");
      const editAdditionalDeliveryLabel = document.getElementById("editAdditionalDeliveryLabel");
      const editPageDestinationGroup = document.getElementById("editPageDestinationGroup");
      const editDirectDeliveryGroup = document.getElementById("editDirectDeliveryGroup");
      const editTitleLabel = document.getElementById("editTitleLabel");
      const editBodyLabel = document.getElementById("editBodyLabel");
      const editDeliveryMessageFields = document.getElementById("editDeliveryMessageFields");
      const editSmsMessageInput = document.getElementById("editSmsMessageInput");
      const editSmsCounter = document.getElementById("editSmsCounter");
      const editEmailSubjectInput = document.getElementById("editEmailSubjectInput");
      const editFaqFields = document.getElementById("editFaqFields");
      const editFaqQuestionTarget = document.getElementById("editFaqQuestionTarget");
      const editFaqItemCount = document.getElementById("editFaqItemCount");
      const editFaqItemsContainer = document.getElementById("editFaqItemsContainer");
      const editFaqMaxItems = 20;
      let editEditorReady = false;
      let editPublicNewsEditorReady = false;
      let editPublicAnnouncementEditorReady = false;
      let superAdminEditConfirmed = false;
      let deniedSaveConfirmed = false;
      let deniedResubmitConfirmed = false;
      let approvedSaveConfirmed = false;
      let pendingSaveConfirmed = false;
      let isDeniedDraftEdit = false;
      let isApprovedReviewEdit = false;
      let isAdminPendingEdit = false;
      let isSuperAdminApprovedEdit = false;
      let requiresDeniedResubmitConfirm = false;
      let canShowPrimarySubmit = false;
      let pendingDeniedEditId = "";
      let editSubmitMode = "save";
      let currentEditContentType = "page";
      const fullToolbar = [
        ["style", ["style"]],
        ["font", ["bold", "italic", "underline", "clear"]],
        ["fontname", ["fontname"]],
        ["fontsize", ["fontsize"]],
        ["color", ["color"]],
        ["para", ["ul", "ol", "paragraph"]],
        ["table", ["table"]],
        ["insert", ["link", "picture"]],
        ["view", ["fullscreen", "codeview", "help"]]
      ];

      function buildEditorConfig(placeholderText) {
        return {
          placeholder: placeholderText,
          height: 240,
          minHeight: 200,
          dialogsInBody: true,
          fontNames: [
            "Arial", "Arial Black", "Comic Sans MS", "Courier New", "Helvetica", "Impact",
            "Lucida Grande", "Tahoma", "Times New Roman", "Trebuchet MS", "Verdana", "Georgia"
          ],
          fontSizes: ["8", "9", "10", "11", "12", "14", "16", "18", "20", "24", "28", "32", "36", "48", "64", "82", "150"],
          toolbar: fullToolbar,
          callbacks: {
            onImageUpload: async function (files) {
              for (const file of files) {
                if (!file) continue;
                if (file.size > 25 * 1024 * 1024) {
                  alert("Image must be 25MB or less.");
                  continue;
                }
                try {
                  const formData = new FormData();
                  formData.append("image", file);
                  const response = await fetch("../../PhpFiles/Admin-End/uploadAnnouncementEditorImage.php", {
                    method: "POST",
                    body: formData
                  });
                  const payload = await response.json();
                  const imageUrl = payload.url || payload.location || "";
                  if (!response.ok || (!payload.success && !imageUrl) || !imageUrl) {
                    throw new Error(payload.message || "Image upload failed.");
                  }
                  $(this).summernote("insertImage", imageUrl);
                } catch (err) {
                  alert(err.message || "Unable to upload image.");
                }
              }
            }
          }
        };
      }

      function initSpecificEditor(editorEl, placeholderText, onReady) {
        if (!editorEl.length) return;
        editorEl.summernote(buildEditorConfig(placeholderText));
        const toolbarGroups = editorEl.next(".note-editor").find(".note-toolbar .note-btn-group").length;
        if (toolbarGroups <= 1) {
          editorEl.summernote("destroy");
          editorEl.summernote(buildEditorConfig(placeholderText));
        }
        onReady();
      }

      function initEditEditor() {
        if (!editEditorReady && editEditorEl.length) {
          initSpecificEditor(editEditorEl, "Update content...", function () {
            editEditorReady = true;
          });
        }
        if (!editPublicNewsEditorReady && editPublicNewsEditorEl.length) {
          initSpecificEditor(editPublicNewsEditorEl, "Update the news section content...", function () {
            editPublicNewsEditorReady = true;
          });
        }
        if (!editPublicAnnouncementEditorReady && editPublicAnnouncementEditorEl.length) {
          initSpecificEditor(editPublicAnnouncementEditorEl, "Update the announcements content...", function () {
            editPublicAnnouncementEditorReady = true;
          });
        }
      }

      function updateEditSmsCounter() {
        if (!editSmsCounter || !editSmsMessageInput) return;
        const value = editSmsMessageInput.value || "";
        editSmsCounter.textContent = `${value.length} / 320 characters`;
      }

      function statusText(status, reviewResult = "", isOwner = false) {
        const v = String(status || "").toLowerCase();
        const review = String(reviewResult || "").toLowerCase();
        if (v === "draft" && review === "denied" && isOwner) return "Denied";
        if (v === "approved") return "Approved";
        if (v === "pending") return "Pending";
        return "Draft";
      }

      function channelText(channels) {
        if (!Array.isArray(channels) || channels.length === 0) return "-";
        return channels.map((ch) => ANNOUNCEMENT_CHANNEL_LABELS[ch] || String(ch).toUpperCase()).join(", ");
      }

      function placementText(placements) {
        if (!Array.isArray(placements) || placements.length === 0) return "-";
        return placements.map((placement) => ANNOUNCEMENT_PLACEMENT_LABELS[placement] || String(placement)).join(", ");
      }

      function typeText(contentType) {
        const key = String(contentType || "page").toLowerCase();
        return ANNOUNCEMENT_TYPE_LABELS[key] || ANNOUNCEMENT_TYPE_LABELS.page;
      }

      function applyEditContentTypeMode(data) {
        currentEditContentType = String(data.content_type || "page").toLowerCase();
        const isPage = currentEditContentType === "page";
        const isDelivery = currentEditContentType === "delivery";
        const isFaq = currentEditContentType === "faq";
        if (editAnnouncementTypeDisplay) {
          editAnnouncementTypeDisplay.value = typeText(currentEditContentType);
        }
        if (editAudienceGroup) {
          editAudienceGroup.classList.toggle("d-none", isFaq);
        }
        if (editPagePlacementGroup) {
          editPagePlacementGroup.classList.toggle("d-none", !isPage);
        }
        if (editAdditionalDeliveryGroup) {
          editAdditionalDeliveryGroup.classList.toggle("d-none", isFaq);
        }
        if (editAdditionalDeliveryLabel) {
          editAdditionalDeliveryLabel.textContent = isPage ? "Page Destinations" : "Delivery";
        }
        if (editPageDestinationGroup) {
          editPageDestinationGroup.classList.toggle("d-none", !isPage);
        }
        if (editDirectDeliveryGroup) {
          editDirectDeliveryGroup.classList.toggle("d-none", !isDelivery);
        }
        if (editDeliveryMessageFields) {
          editDeliveryMessageFields.classList.toggle("d-none", !isDelivery);
        }
        if (editTitleLabel) {
          editTitleLabel.textContent = isFaq ? "Question" : "Title";
        }
        if (editBodyLabel) {
          editBodyLabel.textContent = isFaq ? "Answer" : "Body";
        }
        if (!isPage) {
          if (editSharedContentFields) editSharedContentFields.classList.remove("d-none");
          if (editDualPlacementFields) editDualPlacementFields.classList.add("d-none");
        }
      }

      function syncEditAnnouncementContent() {
        if (editEditorReady) {
          document.getElementById("editAnnouncementContentInput").value = editEditorEl.summernote("code");
        }
        if (editPublicNewsEditorReady && editPublicNewsContentInput) {
          editPublicNewsContentInput.value = editPublicNewsEditorEl.summernote("code");
        }
        if (editPublicAnnouncementEditorReady && editPublicAnnouncementContentInput) {
          editPublicAnnouncementContentInput.value = editPublicAnnouncementEditorEl.summernote("code");
        }
      }

      function updateEditPlacementState() {
        if (currentEditContentType !== "page") {
          if (editSharedContentFields) editSharedContentFields.classList.remove("d-none");
          if (editDualPlacementFields) editDualPlacementFields.classList.add("d-none");
          return;
        }
        const dualPlacement = !!editPlacementPublicNews?.checked && !!editPlacementAnnouncement?.checked;
        const sharedTitleInput = document.getElementById("editAnnouncementTitleInput");
        if (sharedTitleInput && !dualPlacement) {
          if (editPlacementPublicNews?.checked && editPublicNewsTitleInput?.value.trim()) {
            sharedTitleInput.value = editPublicNewsTitleInput.value;
          } else if (editPlacementAnnouncement?.checked && editPublicAnnouncementTitleInput?.value.trim()) {
            sharedTitleInput.value = editPublicAnnouncementTitleInput.value;
          }
        }
        if (editSharedContentFields) {
          editSharedContentFields.classList.toggle("d-none", dualPlacement);
        }
        if (editDualPlacementFields) {
          editDualPlacementFields.classList.toggle("d-none", !dualPlacement);
        }
        if (sharedTitleInput) {
          sharedTitleInput.required = !dualPlacement;
        }
        if (editPublicNewsTitleInput) {
          editPublicNewsTitleInput.required = dualPlacement;
        }
        if (editPublicAnnouncementTitleInput) {
          editPublicAnnouncementTitleInput.required = dualPlacement;
        }
        if (!editPlacementAnnouncement?.checked) {
          const guestCheckbox = document.getElementById("editChannelPublic");
          const websiteCheckbox = document.getElementById("editChannelWebsite");
          if (guestCheckbox) guestCheckbox.checked = false;
          if (websiteCheckbox) websiteCheckbox.checked = false;
        }
      }

      function applyStatusHighlight(el, status, reviewResult = "", isOwner = false) {
        if (!el) return;
        const effectiveStatus = statusText(status, reviewResult, isOwner).toLowerCase();
        el.classList.remove("status-approved", "status-pending", "status-denied", "status-draft");
        if (effectiveStatus === "approved") {
          el.classList.add("status-approved");
          return;
        }
        if (effectiveStatus === "pending") {
          el.classList.add("status-pending");
          return;
        }
        if (effectiveStatus === "denied") {
          el.classList.add("status-denied");
          return;
        }
        el.classList.add("status-draft");
      }

      function applyModalFooterLayout(modalEl) {
        if (!modalEl) return;
        if (["modalFilter", "modalReviewQueueFilter", "modalTableColumns", "modalReviewQueueColumns"].includes(modalEl.id)) {
          return;
        }
        const footer = modalEl.querySelector(".modal-footer");
        if (!footer) return;
        footer.classList.remove("modal-grid-actions");

        const footerChildren = Array.from(footer.children);
        const candidates = footerChildren.filter((el) => {
          if (!el) return false;
          let isVisible = !el.classList.contains("d-none");
          if (isVisible && el.tagName === "FORM") {
            const visibleBtn = el.querySelector(".btn:not(.d-none), button:not(.d-none)");
            isVisible = !!visibleBtn;
          }
          // Enforce hidden elements to not occupy grid slots.
          if (el.style) {
            el.style.display = isVisible ? "" : "none";
          }
          return isVisible;
        });

        footerChildren.forEach((el) => {
          el.classList.remove("modal-action-primary", "modal-action-secondary", "modal-action-fullrow");
          if (el.style) {
            el.style.order = "";
            el.style.gridColumn = "";
          }
        });

        if (!candidates.length) return;

        if (candidates.length === 2) {
          candidates.forEach((el, idx) => {
            el.classList.add("modal-action-secondary");
            if (el.style) {
              el.style.order = String(idx + 1);
            }
          });
          return;
        }

        if (modalEl.id === "modalViewAnnouncement") {
          if (candidates.length >= 3) {
            if (candidates.length === 4) {
              const approveEl = candidates.find((el) => el.id === "viewApproveForm") || candidates[0];
              const denyEl = candidates.find((el) => el.id === "viewDenyForm") || candidates[1];
              const deleteEl = candidates.find((el) => el.id === "btnViewDeleteAnnouncement") || candidates[2];
              const closeEl = candidates.find((el) => el.id === "btnViewCloseAnnouncement") || candidates[3];
              const ordered = [];
              [approveEl, denyEl, deleteEl, closeEl].forEach((el) => {
                if (el && !ordered.includes(el)) {
                  ordered.push(el);
                }
              });
              candidates.forEach((el) => {
                if (!ordered.includes(el)) {
                  ordered.push(el);
                }
              });

              ordered.forEach((el, idx) => {
                el.classList.add("modal-action-secondary");
                if (el.style) {
                  el.style.order = String(idx + 1);
                }
              });
              footer.classList.add("modal-grid-actions");
              return;
            }

            const primary = candidates[0];
            primary.classList.add("modal-action-primary");
            candidates.slice(1).forEach((el) => el.classList.add("modal-action-secondary"));
            footer.classList.add("modal-grid-actions");
            return;
          }
        }

        if (modalEl.id === "modalEditAnnouncement" && candidates.length >= 2) {
          const cancelEl = candidates.find((el) => el.id === "btnEditCancel") || null;
          const nonCancel = candidates.filter((el) => el !== cancelEl);

          if (nonCancel.length === 2 && cancelEl) {
            nonCancel.forEach((el, idx) => {
              el.classList.add("modal-action-secondary");
              if (el.style) {
                el.style.order = String(idx + 1);
              }
            });
            cancelEl.classList.add("modal-action-fullrow");
            if (cancelEl.style) {
              cancelEl.style.order = "3";
            }
            footer.classList.add("modal-grid-actions");
            return;
          }

          if (nonCancel.length === 1 && cancelEl) {
            nonCancel[0].classList.add("modal-action-primary");
            cancelEl.classList.add("modal-action-fullrow");
            if (cancelEl.style) {
              cancelEl.style.order = "2";
            }
            footer.classList.add("modal-grid-actions");
            return;
          }
        }

        const primary = candidates[0];
        primary.classList.add("modal-action-primary");
        candidates.slice(1).forEach((el) => el.classList.add("modal-action-secondary"));
      }

      viewModal.addEventListener("show.bs.modal", function (event) {
        const trigger = event.relatedTarget;
        const id = trigger?.getAttribute("data-id") || "";
        const data = ANNOUNCEMENT_DATA[id];
        if (!data) return;
        const viewRefEl = document.getElementById("viewAnnouncementRef");

        if (viewRefEl) viewRefEl.textContent = "#" + (data.id || "-");
        document.getElementById("viewAnnouncementTitle").textContent = data.title || "-";
        document.getElementById("viewAnnouncementType").textContent = typeText(data.content_type);
        document.getElementById("viewAnnouncementAudience").textContent = data.audience || "-";
        document.getElementById("viewAnnouncementPublishDate").textContent = data.publish_date || "-";
        document.getElementById("viewAnnouncementCreatedBy").textContent = data.created_by || "-";
        const contentType = String(data.content_type || "page").toLowerCase();
        const isPage = contentType === "page";
        const isDelivery = contentType === "delivery";
        const placementsGroup = document.getElementById("viewAnnouncementPlacementsGroup");
        const channelsGroup = document.getElementById("viewAnnouncementChannelsGroup");
        const channelsLabel = channelsGroup ? channelsGroup.querySelector(".announcement-detail-label") : null;
        const viewDeliveryMeta = document.getElementById("viewDeliveryMeta");
        const viewSmsMessageGroup = document.getElementById("viewSmsMessageGroup");
        const viewEmailSubjectGroup = document.getElementById("viewEmailSubjectGroup");
        const viewSmsMessage = document.getElementById("viewAnnouncementSmsMessage");
        const viewEmailSubject = document.getElementById("viewAnnouncementEmailSubject");
        if (placementsGroup) {
          placementsGroup.classList.toggle("d-none", !isPage);
        }
        if (channelsGroup) {
          channelsGroup.classList.toggle("d-none", contentType === "faq");
        }
        if (channelsLabel) {
          channelsLabel.textContent = isPage ? "Page Destinations" : "Delivery";
        }
        if (viewDeliveryMeta) {
          const hasSms = isDelivery && Array.isArray(data.channels) && data.channels.includes("sms") && String(data.sms_message || "").trim() !== "";
          const hasEmail = isDelivery && Array.isArray(data.channels) && data.channels.includes("email") && String(data.email_subject || "").trim() !== "";
          viewDeliveryMeta.classList.toggle("d-none", !(hasSms || hasEmail));
          if (viewSmsMessageGroup) viewSmsMessageGroup.classList.toggle("d-none", !hasSms);
          if (viewEmailSubjectGroup) viewEmailSubjectGroup.classList.toggle("d-none", !hasEmail);
          if (viewSmsMessage) viewSmsMessage.textContent = hasSms ? String(data.sms_message || "") : "-";
          if (viewEmailSubject) viewEmailSubject.textContent = hasEmail ? String(data.email_subject || "") : "-";
        }
        document.getElementById("viewAnnouncementPlacements").textContent = placementText(data.placements || []);
        document.getElementById("viewAnnouncementChannels").textContent = isPage
          ? channelText((data.channels || []).filter((channel) => channel === "public" || channel === "website"))
          : channelText((data.channels || []).filter((channel) => channel === "sms" || channel === "email"));
        const hasNewsPlacement = isPage && Array.isArray(data.placements) && data.placements.includes("public_news");
        const hasAnnouncementPlacement = isPage && Array.isArray(data.placements) && data.placements.includes("announcement");
        const isDualPlacement = hasNewsPlacement && hasAnnouncementPlacement;
        const contentHeading = document.getElementById("viewAnnouncementContentHeading");
        const contentSurface = document.getElementById("viewAnnouncementContent");
        const dualContentSurface = document.getElementById("viewAnnouncementDualContent");
        const newsContentSurface = document.getElementById("viewAnnouncementNewsContent");
        const publicContentSurface = document.getElementById("viewAnnouncementPublicContent");
        if (contentHeading) {
          contentHeading.textContent = contentType === "faq" ? "FAQ Entries" : "Content";
        }
        if (contentSurface && dualContentSurface && newsContentSurface && publicContentSurface) {
          contentSurface.classList.toggle("d-none", isDualPlacement);
          dualContentSurface.classList.toggle("d-none", !isDualPlacement);
          contentSurface.innerHTML = data.content_html && String(data.content_html).trim() !== ""
            ? data.content_html
            : '<span class="text-muted">No content.</span>';
          newsContentSurface.innerHTML = data.public_news_content_html && String(data.public_news_content_html).trim() !== ""
            ? data.public_news_content_html
            : '<span class="text-muted">No news section content.</span>';
          publicContentSurface.innerHTML = data.public_content_html && String(data.public_content_html).trim() !== ""
            ? data.public_content_html
            : '<span class="text-muted">No announcements content.</span>';
        }

        const ownerId = String(data.created_by_user_id || "");
        const createdByLabel = String(data.created_by || "");
        const isOwner = ownerId === String(CURRENT_USER_ID || "")
          || (ownerId === "" && createdByLabel !== "" && createdByLabel === String(CURRENT_USER_DISPLAY || ""));
        const reviewResult = String(data.review_result || "").toLowerCase();
        const reviewNoticeEl = document.getElementById("viewAnnouncementReviewNotice");
        const viewStatusEl = document.getElementById("viewAnnouncementStatus");
        const isDeniedDraft = String(data.status || "").toLowerCase() === "draft" && reviewResult === "denied" && isOwner;
        const canEditFromView = <?= $isSuperAdmin ? 'true' : 'false' ?> || isOwner;
        const canDelete = <?= $isSuperAdmin ? 'true' : 'false' ?>
          || (String(data.status || "").toLowerCase() === "draft" && isOwner);

        if (viewStatusEl) {
          viewStatusEl.textContent = statusText(data.status, reviewResult, isOwner);
          applyStatusHighlight(viewStatusEl, data.status, reviewResult, isOwner);
        }
        if (reviewNoticeEl) {
          const showDeniedNotice = isDeniedDraft;
          reviewNoticeEl.classList.toggle("d-none", !showDeniedNotice);
          reviewNoticeEl.textContent = showDeniedNotice
            ? (String(data.review_note || "").trim() || "This content item is denied. You can edit or delete it.")
            : "";
        }

        if (viewEditBtn) {
          viewEditBtn.classList.toggle("d-none", !(isDeniedDraft && canEditFromView));
          viewEditBtn.setAttribute("data-id", data.id || "");
        }
        if (deleteBtn) {
          deleteBtn.classList.toggle("d-none", !canDelete);
          deleteBtn.setAttribute("data-id", data.id || "");
          deleteBtn.setAttribute("data-title", data.title || "-");
        }
        applyModalFooterLayout(viewModal);
      });

      editModal.addEventListener("show.bs.modal", function (event) {
        initEditEditor();
        const trigger = event.relatedTarget || editModal._deniedDraftTrigger || null;
        const id = trigger?.getAttribute("data-id") || "";
        const data = ANNOUNCEMENT_DATA[id];
        if (!data) return;

        document.getElementById("editAnnouncementIdInput").value = data.id || "";
        applyEditContentTypeMode(data);
        document.getElementById("editAnnouncementAudienceInput").value = data.audience || "";
        document.getElementById("editAnnouncementPublishDateInput").value = data.publish_date && data.publish_date !== "-" ? data.publish_date : "";
        if (editSmsMessageInput) editSmsMessageInput.value = data.sms_message || "";
        if (editEmailSubjectInput) editEmailSubjectInput.value = data.email_subject || "";
        updateEditSmsCounter();
        if (editEditorReady) {
          editEditorEl.summernote("code", data.content_html || "");
        }
        if (editPublicNewsEditorReady) {
          editPublicNewsEditorEl.summernote("code", data.public_news_content_html || "");
        }
        if (editPublicAnnouncementEditorReady) {
          editPublicAnnouncementEditorEl.summernote("code", data.public_content_html || "");
        }
        document.getElementById("editAnnouncementTitleInput").value = data.title || "";
        if (editPublicNewsTitleInput) editPublicNewsTitleInput.value = data.public_news_title || "";
        if (editPublicAnnouncementTitleInput) editPublicAnnouncementTitleInput.value = data.public_title || "";

        const statusInput = document.getElementById("editAnnouncementStatusInput");
        const statusDisplay = document.getElementById("editAnnouncementStatusDisplay");
        const statusVal = String(data.status || "draft").toLowerCase();
        statusInput.value = statusVal;
        if (statusInput.value !== statusVal) {
          statusInput.value = <?= $isSuperAdmin ? '"draft"' : '"pending"' ?>;
        }

        const channels = Array.isArray(data.channels) ? data.channels : [];
        const placements = Array.isArray(data.placements) ? data.placements : [];
        if (editPlacementPublicNews) editPlacementPublicNews.checked = placements.includes("public_news");
        if (editPlacementAnnouncement) editPlacementAnnouncement.checked = placements.includes("announcement");
        document.getElementById("editChannelPublic").checked = channels.includes("public");
        document.getElementById("editChannelWebsite").checked = channels.includes("website");
        document.getElementById("editChannelSms").checked = channels.includes("sms");
        document.getElementById("editChannelEmail").checked = channels.includes("email");
        updateEditPlacementState();

        const ownerId = String(data.created_by_user_id || "");
        const createdByLabel = String(data.created_by || "");
        const isOwner = ownerId === String(CURRENT_USER_ID || "")
          || (ownerId === "" && createdByLabel !== "" && createdByLabel === String(CURRENT_USER_DISPLAY || ""));
        const reviewResult = String(data.review_result || "").toLowerCase();
        if (statusDisplay) {
          statusDisplay.textContent = statusText(statusInput.value, reviewResult, isOwner);
          applyStatusHighlight(statusDisplay, statusInput.value, reviewResult, isOwner);
        }
        applyStatusHighlight(statusInput, statusInput.value);
        const canUseDeniedFlow = statusVal === "draft" && reviewResult === "denied" && (isOwner || isSuperAdminSession);
        const canUseApprovedReviewFlow = !isSuperAdminSession && isOwner && statusVal === "approved";
        const canUsePendingReviewFlow = !isSuperAdminSession && isOwner && statusVal === "pending";
        isSuperAdminApprovedEdit = isSuperAdminSession && statusVal === "approved";
        const canEditSubmitReview = canUseDeniedFlow || (!canUsePendingReviewFlow && !isSuperAdminApprovedEdit && (isSuperAdminSession || isOwner));
        isDeniedDraftEdit = canUseDeniedFlow;
        isApprovedReviewEdit = canUseApprovedReviewFlow;
        isAdminPendingEdit = canUsePendingReviewFlow;
        requiresDeniedResubmitConfirm = canUseDeniedFlow;
        canShowPrimarySubmit = canEditSubmitReview;
        deniedSaveConfirmed = false;
        deniedResubmitConfirmed = false;
        approvedSaveConfirmed = false;
        pendingSaveConfirmed = false;
        if (editSubmitReviewBtn) {
          editSubmitReviewBtn.classList.toggle("d-none", isApprovedReviewEdit || isAdminPendingEdit || isSuperAdminApprovedEdit || !canEditSubmitReview);
          editSubmitReviewBtn.textContent = (isSuperAdminSession && !isDeniedDraftEdit)
            ? "Post"
            : "Submit for Review";
        }
        if (editDeleteBtn) {
          editDeleteBtn.classList.toggle("d-none", !isSuperAdminApprovedEdit);
        }
        if (editCancelBtn) {
          editCancelBtn.textContent = "Close";
        }
        if (editSaveDraftBtn) {
          editSaveDraftBtn.textContent = isApprovedReviewEdit ? "Save Changes" : "Save Changes";
        }
        editSubmitMode = "save";
      });

      if (editForm) {
        editForm.addEventListener("submit", function (event) {
          syncEditAnnouncementContent();
          const statusInput = document.getElementById("editAnnouncementStatusInput");
          if (statusInput) {
            if (editSubmitMode === "submit_review") {
              statusInput.value = (isSuperAdminSession && !isDeniedDraftEdit) ? "approved" : "pending";
            } else if (isSuperAdminApprovedEdit) {
              statusInput.value = "approved";
            } else if (isAdminPendingEdit) {
              statusInput.value = "pending";
            } else if (isApprovedReviewEdit) {
              statusInput.value = "pending";
            } else if (!isSuperAdminSession || isDeniedDraftEdit) {
              statusInput.value = "draft";
            }
          }
          if (editSubmitMode === "save" && isAdminPendingEdit && !pendingSaveConfirmed) {
            event.preventDefault();
            if (pendingSaveModalEl) {
              const modalInstance = bootstrap.Modal.getOrCreateInstance(pendingSaveModalEl);
              modalInstance.show();
            }
            return;
          }
          if (editSubmitMode === "save" && isSuperAdminSession && !isDeniedDraftEdit && !superAdminEditConfirmed) {
            event.preventDefault();
            if (superAdminRepostModalEl) {
              const modalInstance = bootstrap.Modal.getOrCreateInstance(superAdminRepostModalEl);
              modalInstance.show();
            }
            return;
          }
          if (editSubmitMode === "save" && isApprovedReviewEdit && !approvedSaveConfirmed) {
            event.preventDefault();
            if (approvedSaveModalEl) {
              const modalInstance = bootstrap.Modal.getOrCreateInstance(approvedSaveModalEl);
              modalInstance.show();
            }
            return;
          }
          if (editSubmitMode === "save" && isDeniedDraftEdit && !deniedSaveConfirmed) {
            event.preventDefault();
            if (deniedSaveModalEl) {
              const modalInstance = bootstrap.Modal.getOrCreateInstance(deniedSaveModalEl);
              modalInstance.show();
            }
            return;
          }
          if (editSubmitMode === "submit_review" && requiresDeniedResubmitConfirm && !deniedResubmitConfirmed) {
            event.preventDefault();
            if (deniedResubmitModalEl) {
              const modalInstance = bootstrap.Modal.getOrCreateInstance(deniedResubmitModalEl);
              modalInstance.show();
            }
            return;
          }
          if (isSuperAdminSession && canShowPrimarySubmit && editSubmitMode === "submit_review" && !isDeniedDraftEdit && !superAdminEditConfirmed) {
            event.preventDefault();
            if (superAdminRepostModalEl) {
              const modalInstance = bootstrap.Modal.getOrCreateInstance(superAdminRepostModalEl);
              modalInstance.show();
            }
          }
        });
      }

      [editPlacementPublicNews, editPlacementAnnouncement].forEach((checkbox) => {
        if (!checkbox) return;
        checkbox.addEventListener("change", updateEditPlacementState);
      });
      editSmsMessageInput?.addEventListener("input", updateEditSmsCounter);

      if (confirmApprovedSaveBtn && editForm) {
        confirmApprovedSaveBtn.addEventListener("click", function () {
          approvedSaveConfirmed = true;
          if (approvedSaveModalEl) {
            const modalInstance = bootstrap.Modal.getOrCreateInstance(approvedSaveModalEl);
            modalInstance.hide();
          }
          editForm.submit();
        });
      }

      if (confirmPendingSaveBtn && editForm) {
        confirmPendingSaveBtn.addEventListener("click", function () {
          pendingSaveConfirmed = true;
          if (pendingSaveModalEl) {
            const modalInstance = bootstrap.Modal.getOrCreateInstance(pendingSaveModalEl);
            modalInstance.hide();
          }
          editForm.submit();
        });
      }

      if (confirmDeniedSaveBtn && editForm) {
        confirmDeniedSaveBtn.addEventListener("click", function () {
          deniedSaveConfirmed = true;
          if (deniedSaveModalEl) {
            const modalInstance = bootstrap.Modal.getOrCreateInstance(deniedSaveModalEl);
            modalInstance.hide();
          }
          editForm.submit();
        });
      }

      if (confirmDeniedResubmitBtn && editForm) {
        confirmDeniedResubmitBtn.addEventListener("click", function () {
          deniedResubmitConfirmed = true;
          if (deniedResubmitModalEl) {
            const modalInstance = bootstrap.Modal.getOrCreateInstance(deniedResubmitModalEl);
            modalInstance.hide();
          }
          editForm.submit();
        });
      }

      function openDeniedDraftNotice(id) {
        pendingDeniedEditId = id || "";
        if (!pendingDeniedEditId || !deniedDraftNoticeModalEl) {
          return;
        }
        const modalInstance = bootstrap.Modal.getOrCreateInstance(deniedDraftNoticeModalEl);
        modalInstance.show();
      }

      if (confirmDeniedDraftNoticeBtn) {
        confirmDeniedDraftNoticeBtn.addEventListener("click", function () {
          const id = pendingDeniedEditId;
          if (deniedDraftNoticeModalEl) {
            const modalInstance = bootstrap.Modal.getOrCreateInstance(deniedDraftNoticeModalEl);
            modalInstance.hide();
          }
          const data = ANNOUNCEMENT_DATA[id];
          if (!data || !editModal) {
            return;
          }
          const viewInstance = bootstrap.Modal.getInstance(viewModal);
          if (viewInstance) {
            viewInstance.hide();
          }
          const editTrigger = document.querySelector('.btn-edit-announcement[data-id="' + CSS.escape(id) + '"]');
          const editInstance = bootstrap.Modal.getOrCreateInstance(editModal);
          editModal._deniedDraftTrigger = editTrigger || null;
          editInstance.show(editTrigger || undefined);
        });
      }

      if (confirmSuperAdminRepostBtn && editForm) {
        confirmSuperAdminRepostBtn.addEventListener("click", function () {
          superAdminEditConfirmed = true;
          if (superAdminRepostModalEl) {
            const modalInstance = bootstrap.Modal.getOrCreateInstance(superAdminRepostModalEl);
            modalInstance.hide();
          }
          editForm.submit();
        });
      }

      if (confirmSuperAdminApprovedCloseBtn && editModal) {
        confirmSuperAdminApprovedCloseBtn.addEventListener("click", function () {
          if (superAdminApprovedCloseModalEl) {
            const modalInstance = bootstrap.Modal.getOrCreateInstance(superAdminApprovedCloseModalEl);
            modalInstance.hide();
          }
          const editInstance = bootstrap.Modal.getOrCreateInstance(editModal);
          editInstance.hide();
        });
      }

      if (editModal) {
        editModal.addEventListener("show.bs.modal", function () {
          superAdminEditConfirmed = false;
          deniedSaveConfirmed = false;
          deniedResubmitConfirmed = false;
          approvedSaveConfirmed = false;
          pendingSaveConfirmed = false;
          isDeniedDraftEdit = false;
          isApprovedReviewEdit = false;
          isAdminPendingEdit = false;
          isSuperAdminApprovedEdit = false;
          requiresDeniedResubmitConfirm = false;
          canShowPrimarySubmit = false;
        });
        editModal.addEventListener("hidden.bs.modal", function () {
          editModal._deniedDraftTrigger = null;
        });
      }

      if (editSubmitReviewBtn) {
        editSubmitReviewBtn.addEventListener("click", function () {
          editSubmitMode = "submit_review";
        });
      }

      if (editForm) {
        const saveDraftBtn = document.getElementById("btnEditSaveDraft");
        if (saveDraftBtn) {
          saveDraftBtn.addEventListener("click", function () {
            editSubmitMode = "save";
          });
        }
      }

      if (editCancelBtn) {
        editCancelBtn.addEventListener("click", function () {
          if (isSuperAdminApprovedEdit && superAdminApprovedCloseModalEl) {
            const modalInstance = bootstrap.Modal.getOrCreateInstance(superAdminApprovedCloseModalEl);
            modalInstance.show();
            return;
          }
          const editInstance = bootstrap.Modal.getOrCreateInstance(editModal);
          editInstance.hide();
        });
      }

      if (editDeleteBtn) {
        editDeleteBtn.addEventListener("click", function () {
          const deleteModalEl = document.getElementById("modalDeleteAnnouncement");
          const deleteTitleEl = document.getElementById("deleteAnnouncementTitle");
          const deleteIdEl = document.getElementById("deleteAnnouncementIdInput");
          const deleteChannelEl = document.getElementById("deleteChannelInput");
          const deleteStatusEl = document.getElementById("deleteStatusInput");
          const deleteQueryEl = document.getElementById("deleteQueryInput");
          const deleteQueueChannelEl = document.getElementById("deleteQueueChannelInput");
          const deleteQueueQueryEl = document.getElementById("deleteQueueQueryInput");
          const currentId = document.getElementById("editAnnouncementIdInput")?.value || "";
          const currentTitle = document.getElementById("editAnnouncementTitleInput")?.value || "-";
          if (!deleteModalEl || !deleteTitleEl || !deleteIdEl || !deleteChannelEl || !deleteStatusEl || !deleteQueryEl || !deleteQueueChannelEl || !deleteQueueQueryEl || !currentId) {
            return;
          }

          deleteIdEl.value = currentId;
          deleteTitleEl.textContent = currentTitle;
          deleteChannelEl.value = "<?= htmlspecialchars($deliveryChannel, ENT_QUOTES, 'UTF-8') ?>";
          deleteStatusEl.value = "<?= htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8') ?>";
          deleteQueryEl.value = "<?= htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') ?>";
          deleteQueueChannelEl.value = "<?= htmlspecialchars($queueChannelFilter, ENT_QUOTES, 'UTF-8') ?>";
          deleteQueueQueryEl.value = "<?= htmlspecialchars($queueSearchTerm, ENT_QUOTES, 'UTF-8') ?>";

          const editInstance = bootstrap.Modal.getOrCreateInstance(editModal);
          editInstance.hide();
          const deleteInstance = bootstrap.Modal.getOrCreateInstance(deleteModalEl);
          deleteInstance.show();
        });
      }

      const editStatusInputEl = document.getElementById("editAnnouncementStatusInput");
      if (editStatusInputEl && editStatusInputEl.tagName === "SELECT") {
        editStatusInputEl.addEventListener("change", function () {
          applyStatusHighlight(editStatusInputEl, editStatusInputEl.value);
        });
      }

      if (deleteBtn) {
        deleteBtn.addEventListener("click", function () {
          const id = deleteBtn.getAttribute("data-id") || "";
          const title = deleteBtn.getAttribute("data-title") || "-";
          const deleteModalEl = document.getElementById("modalDeleteAnnouncement");
          const deleteTitleEl = document.getElementById("deleteAnnouncementTitle");
          const deleteIdEl = document.getElementById("deleteAnnouncementIdInput");
          const deleteChannelEl = document.getElementById("deleteChannelInput");
          const deleteStatusEl = document.getElementById("deleteStatusInput");
          const deleteQueryEl = document.getElementById("deleteQueryInput");
          const deleteQueueChannelEl = document.getElementById("deleteQueueChannelInput");
          const deleteQueueQueryEl = document.getElementById("deleteQueueQueryInput");
          if (!deleteModalEl || !deleteTitleEl || !deleteIdEl || !deleteChannelEl || !deleteStatusEl || !deleteQueryEl || !deleteQueueChannelEl || !deleteQueueQueryEl) {
            return;
          }

          deleteIdEl.value = id;
          deleteChannelEl.value = "<?= htmlspecialchars($deliveryChannel, ENT_QUOTES, 'UTF-8') ?>";
          deleteStatusEl.value = "<?= htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8') ?>";
          deleteQueryEl.value = "<?= htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') ?>";
          deleteQueueChannelEl.value = "<?= htmlspecialchars($queueChannelFilter, ENT_QUOTES, 'UTF-8') ?>";
          deleteQueueQueryEl.value = "<?= htmlspecialchars($queueSearchTerm, ENT_QUOTES, 'UTF-8') ?>";
          deleteTitleEl.textContent = title;

          const viewInstance = bootstrap.Modal.getInstance(viewModal);
          if (viewInstance) {
            viewInstance.hide();
          }
          const deleteInstance = new bootstrap.Modal(deleteModalEl);
          deleteInstance.show();
        });
      }

      if (viewEditBtn) {
        viewEditBtn.addEventListener("click", function () {
          const id = viewEditBtn.getAttribute("data-id") || "";
          if (!id) {
            return;
          }
          openDeniedDraftNotice(id);
        });
      }

      document.addEventListener("click", function (event) {
        const target = event.target;
        if (!(target instanceof Element)) {
          return;
        }
        const btn = target.closest(".btn-edit-announcement");
        if (!(btn instanceof HTMLElement)) {
          return;
        }
        if (btn.hasAttribute("disabled") || btn.classList.contains("disabled")) {
          return;
        }
        const id = btn.getAttribute("data-id") || "";
        const data = ANNOUNCEMENT_DATA[id];
        if (!data) {
          return;
        }
        const ownerId = String(data.created_by_user_id || "");
        const createdByLabel = String(data.created_by || "");
        const isOwner = ownerId === String(CURRENT_USER_ID || "")
          || (ownerId === "" && createdByLabel !== "" && createdByLabel === String(CURRENT_USER_DISPLAY || ""));
        const isDeniedDraft = String(data.status || "").toLowerCase() === "draft"
          && String(data.review_result || "").toLowerCase() === "denied"
          && (isOwner || isSuperAdminSession);
        if (!isDeniedDraft) {
          return;
        }
        event.preventDefault();
        event.stopPropagation();
        openDeniedDraftNotice(id);
      });

      [
        "modalViewAnnouncement",
        "modalDeleteAnnouncement",
        "modalEditAnnouncement",
        "modalDeniedAnnouncementDraftNotice",
        "modalDeniedAnnouncementSaveConfirm",
        "modalDeniedAnnouncementResubmitConfirm",
        "modalApprovedAnnouncementSaveConfirm",
        "modalPendingAnnouncementSaveConfirm",
        "modalSuperAdminApprovedCloseConfirm",
        "modalSuperAdminRepostConfirm",
        "modalFilter",
        "modalReviewQueueFilter",
        "modalTableColumns",
        "modalReviewQueueColumns",
        "modalAnnouncementFlash"
      ].forEach((id) => {
        const modalEl = document.getElementById(id);
        if (!modalEl) return;
        modalEl.addEventListener("shown.bs.modal", function () {
          applyModalFooterLayout(modalEl);
        });
      });
    })();

    (function () {
      const tableBody = document.getElementById("tableBody");
      const entriesPerPageInput = document.getElementById("entriesPerPageInput");
      const paginationEl = document.getElementById("announcementsPagination");
      if (!tableBody || !entriesPerPageInput || !paginationEl) return;

      function getDataRows() {
        return Array.from(tableBody.querySelectorAll("tr")).filter((row) => {
          return !row.querySelector("td[colspan]");
        });
      }

      let currentPage = 1;
      let entriesPerPage = Math.max(1, Number.parseInt(entriesPerPageInput.value || "20", 10) || 20);

      function renderPagination(totalRows) {
        paginationEl.innerHTML = "";
        const totalPages = Math.max(1, Math.ceil(totalRows / entriesPerPage));
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        function createPageItem(label, page, disabled, active = false) {
          const li = document.createElement("li");
          li.className = `page-item ${disabled ? "disabled" : ""} ${active ? "active" : ""}`.trim();

          const btn = document.createElement("button");
          btn.type = "button";
          btn.className = "page-link";
          btn.textContent = label;
          btn.disabled = !!disabled;
          btn.addEventListener("click", () => {
            if (disabled || page === currentPage) return;
            currentPage = page;
            renderPage();
          });
          li.appendChild(btn);
          paginationEl.appendChild(li);
        }

        createPageItem("<", Math.max(1, currentPage - 1), currentPage <= 1, false);
        for (let page = 1; page <= totalPages; page++) {
          createPageItem(String(page), page, false, page === currentPage);
        }
        createPageItem(">", Math.min(totalPages, currentPage + 1), currentPage >= totalPages, false);
      }

      function renderPage() {
        const rows = getDataRows();
        const totalRows = rows.length;
        const totalPages = Math.max(1, Math.ceil(totalRows / entriesPerPage));
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const start = (currentPage - 1) * entriesPerPage;
        const end = start + entriesPerPage;

        rows.forEach((row, idx) => {
          row.style.display = idx >= start && idx < end ? "" : "none";
        });

        renderPagination(totalRows);
      }

      entriesPerPageInput.addEventListener("change", () => {
        const next = Math.max(1, Number.parseInt(entriesPerPageInput.value || "20", 10) || 20);
        entriesPerPage = next;
        entriesPerPageInput.value = String(next);
        currentPage = 1;
        renderPage();
      });

      window.renderAnnouncementsPage = renderPage;
      renderPage();
    })();

    (function () {
      const table = document.getElementById("table-reviewQueueData");
      const modal = document.getElementById("modalReviewQueueColumns");
      const list = document.getElementById("reviewQueueColumnsList");
      const resetBtn = document.getElementById("btnReviewQueueColumnsReset");
      if (!table || !modal || !list || !resetBtn) return;

      const storageKey = "admin_cols_review_queue_v1";
      const headers = Array.from(table.querySelectorAll("thead th")).map((th) => th.textContent.trim());

      function loadHidden() {
        try {
          const parsed = JSON.parse(localStorage.getItem(storageKey) || "[]");
          return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
          return [];
        }
      }

      function saveHidden(hiddenIdxs) {
        localStorage.setItem(storageKey, JSON.stringify(hiddenIdxs));
      }

      function applyHidden(hiddenIdxs) {
        const hiddenSet = new Set(hiddenIdxs);
        const rows = table.querySelectorAll("tr");
        rows.forEach((row) => {
          Array.from(row.children).forEach((cell, idx) => {
            cell.style.display = hiddenSet.has(idx) ? "none" : "";
          });
        });
      }

      function renderList() {
        const hidden = loadHidden();
        list.innerHTML = "";
        headers.forEach((label, idx) => {
          const col = document.createElement("div");
          col.className = "col-12 col-md-6";
          const wrap = document.createElement("label");
          wrap.className = "d-flex align-items-center gap-2 border rounded p-2";
          const cb = document.createElement("input");
          cb.type = "checkbox";
          cb.className = "form-check-input m-0";
          cb.checked = !hidden.includes(idx);
          cb.addEventListener("change", () => {
            const current = new Set(loadHidden());
            if (cb.checked) {
              current.delete(idx);
            } else {
              current.add(idx);
            }
            const next = Array.from(current.values()).sort((a, b) => a - b);
            saveHidden(next);
            applyHidden(next);
          });
          const text = document.createElement("span");
          text.textContent = label;
          wrap.appendChild(cb);
          wrap.appendChild(text);
          col.appendChild(wrap);
          list.appendChild(col);
        });
      }

      modal.addEventListener("show.bs.modal", renderList);
      resetBtn.addEventListener("click", () => {
        saveHidden([]);
        applyHidden([]);
        renderList();
      });

      window.applyReviewQueueHiddenColumns = () => applyHidden(loadHidden());
      applyHidden(loadHidden());
    })();

    (function () {
      const flashModalEl = document.getElementById("modalAnnouncementFlash");
      if (!flashModalEl) return;
      const flashModal = new bootstrap.Modal(flashModalEl);
      flashModal.show();
    })();

    (function () {
      const announcementsRefreshBtn = document.getElementById("btnAnnouncementsTableRefresh");
      const reviewQueueRefreshBtn = document.getElementById("btnReviewQueueRefresh");
      const announcementsTableBody = document.getElementById("tableBody");
      const reviewQueueBody = document.querySelector("#table-reviewQueueData tbody");
      const visibleCountBadge = document.getElementById("announcementsVisibleCountBadge");
      const AUTO_REFRESH_SECONDS = 15;
      let autoRefreshSecondsLeft = AUTO_REFRESH_SECONDS;
      let autoRefreshInterval = null;
      let autoRefreshInFlight = false;

      function setRefreshLoading(on) {
        [announcementsRefreshBtn, reviewQueueRefreshBtn].forEach((button) => {
          if (!button) return;
          button.classList.toggle("is-loading", !!on);
          button.setAttribute("aria-busy", on ? "true" : "false");
        });
      }

      async function refreshAnnouncementTables() {
        if (autoRefreshInFlight || !announcementsTableBody || !reviewQueueBody) return;
        autoRefreshInFlight = true;
        autoRefreshSecondsLeft = AUTO_REFRESH_SECONDS;
        setRefreshLoading(true);
        try {
          const response = await fetch(window.location.href, {
            credentials: "same-origin",
            headers: { "X-Requested-With": "XMLHttpRequest" }
          });
          const html = await response.text();
          if (!response.ok) {
            throw new Error("Failed to refresh announcement tables.");
          }
          const doc = new DOMParser().parseFromString(html, "text/html");
          const nextAnnouncementsBody = doc.getElementById("tableBody");
          const nextReviewQueueBody = doc.querySelector("#table-reviewQueueData tbody");
          const nextAnnouncementData = doc.getElementById("announcementDataPayload");
          if (!nextAnnouncementsBody || !nextReviewQueueBody || !nextAnnouncementData) {
            throw new Error("Refreshed announcement data was incomplete.");
          }

          announcementsTableBody.innerHTML = nextAnnouncementsBody.innerHTML;
          reviewQueueBody.innerHTML = nextReviewQueueBody.innerHTML;
          ANNOUNCEMENT_DATA = JSON.parse(nextAnnouncementData.textContent || "{}");

          const nextVisibleCountBadge = doc.getElementById("announcementsVisibleCountBadge");
          if (visibleCountBadge && nextVisibleCountBadge) {
            visibleCountBadge.textContent = nextVisibleCountBadge.textContent;
          }

          if (typeof window.renderAnnouncementsPage === "function") {
            window.renderAnnouncementsPage();
          }
          if (typeof window.applyReviewQueueHiddenColumns === "function") {
            window.applyReviewQueueHiddenColumns();
          }
        } catch (error) {
          console.error("Unable to refresh announcement tables:", error);
        } finally {
          autoRefreshInFlight = false;
          setRefreshLoading(false);
        }
      }

      function triggerRefresh(event) {
        if (event) {
          event.preventDefault();
        }
        refreshAnnouncementTables().catch(() => {});
      }

      announcementsRefreshBtn?.addEventListener("click", triggerRefresh);
      reviewQueueRefreshBtn?.addEventListener("click", triggerRefresh);

      autoRefreshInterval = window.setInterval(() => {
        if (autoRefreshInFlight) return;
        autoRefreshSecondsLeft -= 1;
        if (autoRefreshSecondsLeft <= 0) {
          refreshAnnouncementTables().catch(() => {});
        }
      }, 1000);
    })();
  </script>
  <script src="../../JS-Script-Files/Admin-End/tableColumnsGeneric.js?v=20260215-1"></script>
</body>
</html>













