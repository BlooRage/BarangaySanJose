<?php
require_once __DIR__ . "/../includes/admin_guard.php";
require_once __DIR__ . "/../../PhpFiles/General/connection.php";
require_once __DIR__ . "/../../PhpFiles/Admin-End/announcementsStore.php";

$deliveryChannel = strtolower(trim((string)($_GET['channel'] ?? 'all')));
if (!in_array($deliveryChannel, ['all', 'website', 'sms', 'email'], true)) {
  $deliveryChannel = 'all';
}

$statusFilter = strtolower(trim((string)($_GET['status'] ?? 'all')));
if (!in_array($statusFilter, ['all', 'approved', 'pending', 'draft'], true)) {
  $statusFilter = 'all';
}

$searchTerm = trim((string)($_GET['q'] ?? ''));
$queueSearchTerm = trim((string)($_GET['queue_q'] ?? ''));
$queueChannelFilter = strtolower(trim((string)($_GET['queue_channel'] ?? 'all')));
if (!in_array($queueChannelFilter, ['all', 'website', 'sms', 'email'], true)) {
  $queueChannelFilter = 'all';
}
$sessionRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
$isSuperAdmin = $sessionRole === 'superadmin';
$currentUserId = trim((string)($_SESSION['user_id'] ?? ''));

$channelLabels = [
  'website' => 'Website',
  'sms' => 'SMS',
  'email' => 'Email'
];

$statusLabels = [
  'approved' => 'Approved',
  'pending' => 'Pending',
  'draft' => 'Draft'
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

$currentUserDisplayLabel = ann_creator_display_from_user_id($conn, $currentUserId, $currentUserId);

$announcementRows = [];
$storedAnnouncements = announcements_load_all();
foreach ($storedAnnouncements as $item) {
  $channels = array_values(array_filter((array)($item['channels'] ?? []), function ($ch) {
    return in_array($ch, ['website', 'sms', 'email'], true);
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
    'audience' => (string)($item['audience'] ?? 'All Residents'),
    'channels' => $channels,
    'status' => $status,
    'publish_date' => (string)($item['publish_date'] ?? '-'),
    'created_by' => $createdByDisplay,
    'created_by_user_id' => $createdByUserId,
    'created_by_role' => $createdByRole,
    'content_html' => (string)($item['content_html'] ?? ''),
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

$statusCounts = ['all' => 0, 'approved' => 0, 'pending' => 0, 'draft' => 0];
foreach ($filteredByChannel as $item) {
  $status = $item['status'];
  $statusCounts['all']++;
  if (isset($statusCounts[$status])) {
    $statusCounts[$status]++;
  }
}

$visibleRows = array_values(array_filter($filteredByChannel, function ($item) use ($statusFilter, $searchTerm, $channelLabels, $statusLabels, $isSuperAdmin) {
  if ($statusFilter !== 'all' && $item['status'] !== $statusFilter) {
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
    $statusLabels[$item['status']] ?? $item['status'],
    $isSuperAdmin ? $item['created_by'] : ''
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
    'audience' => (string)$row['audience'],
    'channels' => array_values((array)$row['channels']),
    'status' => (string)$row['status'],
    'created_by_role' => (string)$row['created_by_role'],
    'created_by_user_id' => (string)($row['created_by_user_id'] ?? ''),
    'publish_date' => (string)$row['publish_date'],
    'created_by' => (string)$row['created_by'],
    'content_html' => (string)$row['content_html'],
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
  return 'Announcements.php?' . http_build_query($query);
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
  return 'Announcements.php?' . http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="icon" href="../../Images/favicon_sanjose.png?v=20260211">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Announcements</title>

  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../../summernote-0.9.0-dist/summernote-lite.min.css?v=20260307-2" rel="stylesheet">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ContentManagementStyle.css?v=20260307-16">
</head>
<body>
  <div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
      <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C; ">
        Announcements
      </h2>
      <hr><br>

      <div class="d-flex flex-wrap align-items-center justify-content-start gap-3 mb-4">
        <a href="CreateAnnouncement.php<?= $deliveryChannel !== 'all' ? '?channel=' . urlencode($deliveryChannel) : '' ?>" class="btn btn-primary fw-semibold">
          <i class="fa-solid fa-plus me-1"></i> Create Announcement
        </a>
      </div>

      <?php if ($isSuperAdmin): ?>
        <div class="announcement-shell p-3 p-md-4 shadow-sm mb-4">
          <div class="review-queue-top d-flex flex-wrap align-items-start justify-content-between gap-3 mb-2">
            <div class="review-queue-title-wrap">
              <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <h5 class="mb-0 fw-bold review-queue-heading">Review Queue</h5>
                <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis">
                  Showing <?= (int)count($reviewQueueRows) ?> of <?= (int)count($reviewQueueBaseRows) ?> pending
                </span>
              </div>
              <p class="small text-muted mb-0 review-queue-description">Shows pending announcements submitted by admin/official/personnel accounts.</p>
            </div>

            <div class="admin-list-actions review-queue-actions">
              <form class="admin-search" method="get" action="Announcements.php">
                <input type="hidden" name="channel" value="<?= htmlspecialchars($deliveryChannel) ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                <input type="hidden" name="q" value="<?= htmlspecialchars($searchTerm) ?>">
                <input type="hidden" name="queue_channel" value="<?= htmlspecialchars($queueChannelFilter) ?>">
                <div class="input-group">
                  <input type="text" name="queue_q" class="form-control" placeholder="Search title, audience, creator" value="<?= htmlspecialchars($queueSearchTerm) ?>">
                  <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                </div>
              </form>

              <button class="btn btn-outline-secondary btn-icon" type="button" data-bs-toggle="modal" data-bs-target="#modalReviewQueueFilter" title="Filter review queue" aria-label="Filter review queue">
                <i class="fas fa-filter"></i>
                <span class="visually-hidden">Filter review queue</span>
              </button>
              <button class="btn btn-outline-secondary btn-icon" type="button" data-bs-toggle="modal" data-bs-target="#modalReviewQueueColumns" title="Review queue columns" aria-label="Review queue columns">
                <i class="fa-solid fa-sliders"></i>
                <span class="visually-hidden">Review queue columns</span>
              </button>
              <a class="btn btn-outline-secondary btn-icon" href="<?= htmlspecialchars(buildReviewQueueUrl($deliveryChannel, $statusFilter, $searchTerm, $queueChannelFilter, $queueSearchTerm)) ?>" title="Refresh review queue" aria-label="Refresh review queue">
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
                  <th>Audience</th>
                  <th>Channels</th>
                  <th>Created By</th>
                  <th class="text-end">Review Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$reviewQueueRows): ?>
                  <tr>
                    <td colspan="5" class="text-center text-muted py-4">No pending announcements in the review queue.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($reviewQueueRows as $item): ?>
                    <?php
                      $queueChannelsText = implode(', ', array_map(function ($ch) use ($channelLabels) {
                        return $channelLabels[$ch] ?? strtoupper($ch);
                      }, $item['channels']));
                    ?>
                    <tr>
                      <td><?= htmlspecialchars($item['title']) ?></td>
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
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <div class="announcement-shell resident-masterlist-shell p-3 p-md-4 shadow-sm">
        <div class="admin-list-toolbar mb-3 pt-2 flex-wrap">
          <div class="admin-list-tabs">
            <a href="<?= htmlspecialchars(buildAnnouncementsUrl($deliveryChannel, 'all', $searchTerm)) ?>" data-filter="ALL" class="btn btn-outline-primary btn-sm status-filter-btn fw-semibold <?= $statusFilter === 'all' ? 'active' : '' ?>">
              &nbsp;&nbsp;All&nbsp;&nbsp;
            </a>
            <a href="<?= htmlspecialchars(buildAnnouncementsUrl($deliveryChannel, 'approved', $searchTerm)) ?>" data-filter="Approved" class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold <?= $statusFilter === 'approved' ? 'active' : '' ?>">
              &nbsp;&nbsp;Approved&nbsp;&nbsp;
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

          <div class="admin-list-actions">
            <form class="admin-search" method="get" action="Announcements.php">
              <input type="hidden" name="channel" value="<?= htmlspecialchars($deliveryChannel) ?>">
              <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
              <div class="input-group">
                <input type="text" id="searchInput" name="q" class="form-control" placeholder="<?= $isSuperAdmin ? 'Search title, audience, creator' : 'Search title, audience' ?>" value="<?= htmlspecialchars($searchTerm) ?>">
                <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
              </div>
            </form>

            <button class="btn btn-outline-secondary btn-icon" type="button" data-bs-toggle="modal" data-bs-target="#modalFilter" id="filterButton" title="Filter" aria-label="Filter">
              <i class="fas fa-filter"></i>
              <span class="visually-hidden">Filter</span>
            </button>
            <button class="btn btn-outline-secondary btn-icon" type="button" data-bs-toggle="modal" data-bs-target="#modalTableColumns" id="btnAnnouncementsColumns" title="Columns" aria-label="Columns">
              <i class="fa-solid fa-sliders"></i>
              <span class="visually-hidden">Columns</span>
            </button>
            <a class="btn btn-outline-secondary btn-icon" id="btnAnnouncementsTableRefresh" href="<?= htmlspecialchars(buildAnnouncementsUrl($deliveryChannel, $statusFilter)) ?>" title="Refresh table" aria-label="Refresh table">
              <i class="fa-solid fa-arrows-rotate"></i>
              <span class="visually-hidden">Refresh</span>
            </a>
          </div>
        </div>

        <div class="table-responsive">
          <table id="table-appData" class="table align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Title</th>
                <th>Audience</th>
                <th>Channels</th>
                <th>Status</th>
                <?php if ($isSuperAdmin): ?>
                  <th>Created By</th>
                <?php endif; ?>
                <th>Publish Date</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody id="tableBody">
              <?php if (!$visibleRows): ?>
                <tr>
                  <td colspan="<?= $isSuperAdmin ? '7' : '6' ?>" class="text-center text-muted py-4">No announcements match the current filters.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($visibleRows as $item): ?>
                  <?php
                    $status = $item['status'];
                    $isSuperAdminCreated = ((string)($item['created_by_role'] ?? '') === 'superadmin');
                    $isOwnedByCurrentUser = ann_is_owned_by_current_user($item, $currentUserId, $currentUserDisplayLabel);
                    $statusClass = $status === 'approved' ? 'approved' : ($status === 'pending' ? 'pending' : 'archived');
                    $channelsText = implode(', ', array_map(function ($ch) use ($channelLabels) {
                      return $channelLabels[$ch] ?? strtoupper($ch);
                    }, $item['channels']));
                  ?>
                  <tr>
                    <td><?= htmlspecialchars($item['title']) ?></td>
                    <td><?= htmlspecialchars($item['audience']) ?></td>
                    <td><?= htmlspecialchars($channelsText) ?></td>
                    <td><span class="status-pill <?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars($statusLabels[$status]) ?></span></td>
                    <?php if ($isSuperAdmin): ?>
                      <td><?= htmlspecialchars($item['created_by']) ?></td>
                    <?php endif; ?>
                    <td><?= htmlspecialchars($item['publish_date']) ?></td>
                    <td class="text-end">
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
                            <button
                              class="btn btn-warning btn-sm text-dark btn-edit-announcement"
                              type="button"
                              data-bs-toggle="modal"
                              data-bs-target="#modalEditAnnouncement"
                              data-id="<?= htmlspecialchars($item['id']) ?>">
                              Edit
                            </button>
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
          </div>
          <nav aria-label="Announcements pagination">
            <ul class="pagination pagination-sm mb-0" id="announcementsPagination"></ul>
          </nav>
        </div>

        <div class="modal fade" id="modalFilter" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
          <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content p-4" method="get" action="Announcements.php">
              <input type="hidden" name="q" value="<?= htmlspecialchars($searchTerm) ?>">

              <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Filter Announcements</h5>
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
                      <span>Website</span>
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
      <form class="modal-content" id="deleteAnnouncementForm" method="post" action="../../PhpFiles/Admin-End/announcementsActions.php">
        <?= csrfTokenField() ?>
        <input type="hidden" id="deleteAnnouncementIdInput" name="announcement_id" value="">
        <input type="hidden" id="deleteChannelInput" name="channel" value="<?= htmlspecialchars($deliveryChannel) ?>">
        <input type="hidden" id="deleteStatusInput" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
        <input type="hidden" id="deleteQueryInput" name="q" value="<?= htmlspecialchars($searchTerm) ?>">
        <input type="hidden" id="deleteQueueChannelInput" name="queue_channel" value="<?= htmlspecialchars($queueChannelFilter) ?>">
        <input type="hidden" id="deleteQueueQueryInput" name="queue_q" value="<?= htmlspecialchars($queueSearchTerm) ?>">
        <input type="hidden" name="action" value="delete">
        <div class="modal-header">
          <h5 class="modal-title">Delete Announcement</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2">You are about to delete this announcement:</p>
          <p class="fw-semibold mb-0" id="deleteAnnouncementTitle">-</p>
          <p class="small text-muted mt-2 mb-0">This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Delete</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal fade" id="modalReviewQueueFilter" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content p-4" method="get" action="Announcements.php">
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
                <span>Website</span>
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
      <div class="modal-content announcement-details-content">
        <div class="modal-header border-0">
          <h3 class="announcement-details-title mb-0">
            Announcement Details: <span id="viewAnnouncementRef" class="announcement-details-id">#-</span>
          </h3>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="announcement-details-container">
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
                <p class="announcement-detail-label">Audience</p>
                <p class="announcement-detail-value" id="viewAnnouncementAudience">-</p>
              </div>
              <div class="col-md-6">
                <p class="announcement-detail-label">Channels</p>
                <p class="announcement-detail-value" id="viewAnnouncementChannels">-</p>
              </div>
              <div class="col-md-6">
                <p class="announcement-detail-label">Status</p>
                <p class="announcement-detail-value" id="viewAnnouncementStatus">-</p>
              </div>
              <div class="col-md-6">
                <p class="announcement-detail-label">Publish Date</p>
                <p class="announcement-detail-value" id="viewAnnouncementPublishDate">-</p>
              </div>
            </div>
            <hr>
            <div>
              <p class="announcement-detail-label mb-1">Announcement Content</p>
              <div id="viewAnnouncementContent" class="border rounded p-3 bg-white" style="min-height: 160px;"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0">
          <form id="viewSubmitReviewForm" method="post" action="../../PhpFiles/Admin-End/announcementsActions.php" class="d-inline">
            <?= csrfTokenField() ?>
            <input type="hidden" name="action" value="submit_review">
            <input type="hidden" id="viewSubmitReviewAnnouncementId" name="announcement_id" value="">
            <input type="hidden" name="channel" value="<?= htmlspecialchars($deliveryChannel) ?>">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <input type="hidden" name="q" value="<?= htmlspecialchars($searchTerm) ?>">
            <input type="hidden" name="queue_channel" value="<?= htmlspecialchars($queueChannelFilter) ?>">
            <input type="hidden" name="queue_q" value="<?= htmlspecialchars($queueSearchTerm) ?>">
            <button type="button" class="btn btn-primary text-white d-none" id="btnViewSubmitReviewAnnouncement">Submit for Review</button>
          </form>
          <form id="viewApproveForm" method="post" action="../../PhpFiles/Admin-End/announcementsActions.php" class="d-inline">
            <?= csrfTokenField() ?>
            <input type="hidden" name="action" value="approve">
            <input type="hidden" id="viewApproveAnnouncementId" name="announcement_id" value="">
            <input type="hidden" name="channel" value="<?= htmlspecialchars($deliveryChannel) ?>">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <input type="hidden" name="q" value="<?= htmlspecialchars($searchTerm) ?>">
            <input type="hidden" name="queue_channel" value="<?= htmlspecialchars($queueChannelFilter) ?>">
            <input type="hidden" name="queue_q" value="<?= htmlspecialchars($queueSearchTerm) ?>">
            <button type="button" class="btn btn-success text-white d-none" id="btnViewApproveAnnouncement">Approve</button>
          </form>
          <form id="viewDenyForm" method="post" action="../../PhpFiles/Admin-End/announcementsActions.php" class="d-inline">
            <?= csrfTokenField() ?>
            <input type="hidden" name="action" value="deny">
            <input type="hidden" id="viewDenyAnnouncementId" name="announcement_id" value="">
            <input type="hidden" name="channel" value="<?= htmlspecialchars($deliveryChannel) ?>">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <input type="hidden" name="q" value="<?= htmlspecialchars($searchTerm) ?>">
            <input type="hidden" name="queue_channel" value="<?= htmlspecialchars($queueChannelFilter) ?>">
            <input type="hidden" name="queue_q" value="<?= htmlspecialchars($queueSearchTerm) ?>">
            <button type="button" class="btn btn-danger text-white d-none" id="btnViewDenyAnnouncement">Deny</button>
          </form>
          <button type="button" class="btn btn-danger text-white d-none" id="btnViewDeleteAnnouncement">Delete</button>
          <button type="button" class="btn btn-secondary" id="btnViewCloseAnnouncement" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalEditAnnouncement" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <form class="modal-content" id="editAnnouncementForm" method="post" action="../../PhpFiles/Admin-End/announcementsActions.php">
        <?= csrfTokenField() ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" id="editAnnouncementIdInput" name="announcement_id" value="">
        <input type="hidden" name="channel" value="<?= htmlspecialchars($deliveryChannel) ?>">
        <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
        <input type="hidden" name="q" value="<?= htmlspecialchars($searchTerm) ?>">
        <input type="hidden" name="queue_channel" value="<?= htmlspecialchars($queueChannelFilter) ?>">
        <input type="hidden" name="queue_q" value="<?= htmlspecialchars($queueSearchTerm) ?>">
        <div class="modal-header">
          <h5 class="modal-title">Edit Announcement</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="editAnnouncementTitleInput" class="form-label">Title</label>
            <input type="text" class="form-control" id="editAnnouncementTitleInput" name="title" required>
          </div>
          <div class="mb-3">
            <label for="editAnnouncementAudienceInput" class="form-label">Audience</label>
            <input type="text" class="form-control" id="editAnnouncementAudienceInput" name="audience" required>
          </div>
          <div class="mb-3">
            <label class="form-label d-block">Delivery Channels</label>
            <div class="d-flex flex-wrap gap-3">
              <label class="form-check-label d-flex align-items-center gap-2">
                <input class="form-check-input m-0" type="checkbox" name="channels[]" value="website" id="editChannelWebsite">
                <span>Website</span>
              </label>
              <label class="form-check-label d-flex align-items-center gap-2">
                <input class="form-check-input m-0" type="checkbox" name="channels[]" value="sms" id="editChannelSms">
                <span>SMS</span>
              </label>
              <label class="form-check-label d-flex align-items-center gap-2">
                <input class="form-check-input m-0" type="checkbox" name="channels[]" value="email" id="editChannelEmail">
                <span>Email</span>
              </label>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label for="editAnnouncementStatusInput" class="form-label">Status</label>
              <select class="form-select" id="editAnnouncementStatusInput" name="status_update">
                <option value="draft">Draft</option>
                <option value="pending">Pending</option>
                <?php if ($isSuperAdmin): ?>
                  <option value="approved">Approved</option>
                <?php endif; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label for="editAnnouncementPublishDateInput" class="form-label">Publish Date</label>
              <input type="text" class="form-control" id="editAnnouncementPublishDateInput" name="publish_date" placeholder="YYYY-MM-DD HH:MM">
            </div>
          </div>
          <div class="mt-3">
            <label for="editAnnouncementContentInput" class="form-label">Content</label>
            <div id="editAnnouncementEditor"></div>
            <input type="hidden" id="editAnnouncementContentInput" name="content_html" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <?php if ($isSuperAdmin): ?>
    <div class="modal fade" id="modalSuperAdminRepostConfirm" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Confirm Repost</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p class="mb-0">This announcement was edited. Are you sure it is ready to post again?</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="btnConfirmSuperAdminRepost">Yes, Post Again</button>
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
          <div class="modal-header">
            <h5 class="modal-title"><?= htmlspecialchars($flashTitle) ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p class="mb-0"><?= htmlspecialchars((string)$flash['message']) ?></p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="../../summernote-0.9.0-dist/summernote-lite.min.js?v=20260307-2"></script>
  <script>
    const ANNOUNCEMENT_DATA = <?= json_encode($announcementDetailsMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const CURRENT_USER_ID = <?= json_encode($currentUserId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const CURRENT_USER_DISPLAY = <?= json_encode($currentUserDisplayLabel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const ANNOUNCEMENT_CHANNEL_LABELS = {
      website: "Website",
      sms: "SMS",
      email: "Email"
    };

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
      const superAdminRepostModalEl = document.getElementById("modalSuperAdminRepostConfirm");
      const confirmSuperAdminRepostBtn = document.getElementById("btnConfirmSuperAdminRepost");
      const submitReviewBtn = document.getElementById("btnViewSubmitReviewAnnouncement");
      const approveBtn = document.getElementById("btnViewApproveAnnouncement");
      const denyBtn = document.getElementById("btnViewDenyAnnouncement");
      const deleteBtn = document.getElementById("btnViewDeleteAnnouncement");
      const closeBtn = document.getElementById("btnViewCloseAnnouncement");
      const submitReviewForm = document.getElementById("viewSubmitReviewForm");
      const approveForm = document.getElementById("viewApproveForm");
      const denyForm = document.getElementById("viewDenyForm");
      const submitReviewIdInput = document.getElementById("viewSubmitReviewAnnouncementId");
      const approveIdInput = document.getElementById("viewApproveAnnouncementId");
      const denyIdInput = document.getElementById("viewDenyAnnouncementId");
      if (!viewModal || !editModal) return;
      const isSuperAdminSession = <?= $isSuperAdmin ? 'true' : 'false' ?>;
      const editEditorEl = $("#editAnnouncementEditor");
      let editEditorReady = false;
      let superAdminEditConfirmed = false;
      const fullToolbar = [
        ["style", ["style"]],
        ["font", ["bold", "italic", "underline", "clear"]],
        ["fontname", ["fontname"]],
        ["fontsize", ["fontsize"]],
        ["color", ["color"]],
        ["para", ["ul", "ol", "paragraph"]],
        ["table", ["table"]],
        ["insert", ["link", "picture", "video"]],
        ["view", ["fullscreen", "codeview", "help"]]
      ];

      function initEditEditor() {
        if (editEditorReady || !editEditorEl.length) return;
        editEditorEl.summernote({
          placeholder: "Update announcement content...",
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
                if (file.size > 5 * 1024 * 1024) {
                  alert("Image must be 5MB or less.");
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
                  editEditorEl.summernote("insertImage", imageUrl);
                } catch (err) {
                  alert(err.message || "Unable to upload image.");
                }
              }
            }
          }
        });
        const toolbarGroups = editEditorEl.next(".note-editor").find(".note-toolbar .note-btn-group").length;
        if (toolbarGroups <= 1) {
          editEditorEl.summernote("destroy");
          editEditorEl.summernote({
            placeholder: "Update announcement content...",
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
                  if (file.size > 5 * 1024 * 1024) {
                    alert("Image must be 5MB or less.");
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
                    editEditorEl.summernote("insertImage", imageUrl);
                  } catch (err) {
                    alert(err.message || "Unable to upload image.");
                  }
                }
              }
            }
          });
        }
        editEditorReady = true;
      }

      function statusText(status) {
        const v = String(status || "").toLowerCase();
        if (v === "approved") return "Approved";
        if (v === "pending") return "Pending";
        return "Draft";
      }

      function channelText(channels) {
        if (!Array.isArray(channels) || channels.length === 0) return "-";
        return channels.map((ch) => ANNOUNCEMENT_CHANNEL_LABELS[ch] || String(ch).toUpperCase()).join(", ");
      }

      function applyModalFooterLayout(modalEl) {
        if (!modalEl) return;
        if (["modalFilter", "modalReviewQueueFilter", "modalTableColumns", "modalReviewQueueColumns"].includes(modalEl.id)) {
          return;
        }
        const footer = modalEl.querySelector(".modal-footer");
        if (!footer) return;
        footer.classList.remove("modal-grid-actions");

        const candidates = Array.from(footer.children).filter((el) => {
          if (!el) return false;
          if (el.classList.contains("d-none")) return false;
          if (el.tagName === "FORM") {
            const visibleBtn = el.querySelector(".btn:not(.d-none), button:not(.d-none)");
            return !!visibleBtn;
          }
          return true;
        });

        candidates.forEach((el) => {
          el.classList.remove("modal-action-primary", "modal-action-secondary");
        });

        if (!candidates.length) return;
        const primary = candidates[0];
        primary.classList.add("modal-action-primary");
        candidates.slice(1).forEach((el) => el.classList.add("modal-action-secondary"));

        if (modalEl.id === "modalViewAnnouncement" && candidates.length >= 2) {
          footer.classList.add("modal-grid-actions");
        }
      }

      viewModal.addEventListener("show.bs.modal", function (event) {
        const trigger = event.relatedTarget;
        const id = trigger?.getAttribute("data-id") || "";
        const data = ANNOUNCEMENT_DATA[id];
        if (!data) return;
        const viewRefEl = document.getElementById("viewAnnouncementRef");

        if (viewRefEl) viewRefEl.textContent = "#" + (data.id || "-");
        document.getElementById("viewAnnouncementTitle").textContent = data.title || "-";
        document.getElementById("viewAnnouncementAudience").textContent = data.audience || "-";
        document.getElementById("viewAnnouncementChannels").textContent = channelText(data.channels);
        document.getElementById("viewAnnouncementStatus").textContent = statusText(data.status);
        document.getElementById("viewAnnouncementPublishDate").textContent = data.publish_date || "-";
        document.getElementById("viewAnnouncementCreatedBy").textContent = data.created_by || "-";
        document.getElementById("viewAnnouncementContent").innerHTML = data.content_html && String(data.content_html).trim() !== ""
          ? data.content_html
          : '<span class="text-muted">No content.</span>';

        if (submitReviewIdInput) submitReviewIdInput.value = data.id || "";
        if (approveIdInput) approveIdInput.value = data.id || "";
        if (denyIdInput) denyIdInput.value = data.id || "";

        const ownerId = String(data.created_by_user_id || "");
        const createdByLabel = String(data.created_by || "");
        const isOwner = ownerId === String(CURRENT_USER_ID || "")
          || (ownerId === "" && createdByLabel !== "" && createdByLabel === String(CURRENT_USER_DISPLAY || ""));
        const canSubmitReview = !<?= $isSuperAdmin ? 'true' : 'false' ?>
          && isOwner
          && String(data.status || "").toLowerCase() === "draft";
        const canReview = <?= $isSuperAdmin ? 'true' : 'false' ?>
          && String(data.status || "").toLowerCase() === "pending"
          && String(data.created_by_role || "").toLowerCase() !== "superadmin";
        const canDelete = String(data.status || "").toLowerCase() === "draft"
          && (<?= $isSuperAdmin ? 'true' : 'false' ?> || isOwner);

        if (submitReviewForm) submitReviewForm.classList.toggle("d-none", !canSubmitReview);
        if (approveForm) approveForm.classList.toggle("d-none", !canReview);
        if (denyForm) denyForm.classList.toggle("d-none", !canReview);
        if (submitReviewBtn) submitReviewBtn.classList.toggle("d-none", !canSubmitReview);
        if (approveBtn) approveBtn.classList.toggle("d-none", !canReview);
        if (denyBtn) denyBtn.classList.toggle("d-none", !canReview);
        if (deleteBtn) {
          deleteBtn.classList.toggle("d-none", !canDelete);
          deleteBtn.setAttribute("data-id", data.id || "");
          deleteBtn.setAttribute("data-title", data.title || "-");
        }
        applyModalFooterLayout(viewModal);
      });

      editModal.addEventListener("show.bs.modal", function (event) {
        initEditEditor();
        const trigger = event.relatedTarget;
        const id = trigger?.getAttribute("data-id") || "";
        const data = ANNOUNCEMENT_DATA[id];
        if (!data) return;

        document.getElementById("editAnnouncementIdInput").value = data.id || "";
        document.getElementById("editAnnouncementTitleInput").value = data.title || "";
        document.getElementById("editAnnouncementAudienceInput").value = data.audience || "";
        document.getElementById("editAnnouncementPublishDateInput").value = data.publish_date && data.publish_date !== "-" ? data.publish_date : "";
        if (editEditorReady) {
          editEditorEl.summernote("code", data.content_html || "");
        }

        const statusInput = document.getElementById("editAnnouncementStatusInput");
        const statusVal = String(data.status || "draft").toLowerCase();
        statusInput.value = statusVal;
        if (statusInput.value !== statusVal) {
          statusInput.value = <?= $isSuperAdmin ? '"draft"' : '"pending"' ?>;
        }

        const channels = Array.isArray(data.channels) ? data.channels : [];
        document.getElementById("editChannelWebsite").checked = channels.includes("website");
        document.getElementById("editChannelSms").checked = channels.includes("sms");
        document.getElementById("editChannelEmail").checked = channels.includes("email");
      });

      if (editForm) {
        editForm.addEventListener("submit", function (event) {
          if (editEditorReady) {
            document.getElementById("editAnnouncementContentInput").value = editEditorEl.summernote("code");
          }
          if (isSuperAdminSession && !superAdminEditConfirmed) {
            event.preventDefault();
            if (superAdminRepostModalEl) {
              const modalInstance = bootstrap.Modal.getOrCreateInstance(superAdminRepostModalEl);
              modalInstance.show();
            }
          }
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

      if (editModal) {
        editModal.addEventListener("show.bs.modal", function () {
          superAdminEditConfirmed = false;
        });
      }

      if (submitReviewBtn) {
        submitReviewBtn.addEventListener("click", function () {
          const formEl = document.getElementById("viewSubmitReviewForm");
          if (!formEl) return;
          formEl.submit();
        });
      }

      if (approveBtn) {
        approveBtn.addEventListener("click", function () {
          const formEl = document.getElementById("viewApproveForm");
          if (!formEl) return;
          formEl.submit();
        });
      }

      if (denyBtn) {
        denyBtn.addEventListener("click", function () {
          const formEl = document.getElementById("viewDenyForm");
          if (!formEl) return;
          formEl.submit();
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

      [
        "modalViewAnnouncement",
        "modalDeleteAnnouncement",
        "modalEditAnnouncement",
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

      applyHidden(loadHidden());
    })();

    (function () {
      const flashModalEl = document.getElementById("modalAnnouncementFlash");
      if (!flashModalEl) return;
      const flashModal = new bootstrap.Modal(flashModalEl);
      flashModal.show();
    })();
  </script>
  <script src="../../JS-Script-Files/Admin-End/tableColumnsGeneric.js?v=20260215-1"></script>
</body>
</html>
