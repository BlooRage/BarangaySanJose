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
$isSuperAdmin = ((string)($_SESSION['role'] ?? '') === 'SuperAdmin');

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

  $announcementRows[] = [
    'id' => (string)($item['id'] ?? ''),
    'title' => (string)($item['title'] ?? ''),
    'audience' => (string)($item['audience'] ?? 'All Residents'),
    'channels' => $channels,
    'status' => $status,
    'publish_date' => (string)($item['publish_date'] ?? '-'),
    'created_by' => (string)($item['created_by'] ?? 'Admin')
  ];
}

$flash = $_SESSION['announcement_flash'] ?? null;
unset($_SESSION['announcement_flash']);

$filteredByChannel = array_values(array_filter($announcementRows, function ($item) use ($deliveryChannel) {
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

$visibleRows = array_values(array_filter($filteredByChannel, function ($item) use ($statusFilter, $searchTerm, $channelLabels, $statusLabels) {
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
    $item['created_by']
  ]));
  return str_contains($haystack, strtolower($searchTerm));
}));

function buildAnnouncementsUrl(string $channel, string $status, string $searchTerm = ''): string
{
  $query = ['channel' => $channel, 'status' => $status];
  if ($searchTerm !== '') {
    $query['q'] = $searchTerm;
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
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ContentManagementStyle.css?v=20260307-6">
</head>
<body>
  <div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
      <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C; ">
        Announcements
      </h2>
      <hr><br>

      <?php if (is_array($flash) && isset($flash['type'], $flash['message'])): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> mb-3" role="alert">
          <?= htmlspecialchars((string)$flash['message']) ?>
        </div>
      <?php endif; ?>

      <div class="d-flex flex-wrap align-items-center justify-content-start gap-3 mb-4">
        <a href="CreateAnnouncement.php<?= $deliveryChannel !== 'all' ? '?channel=' . urlencode($deliveryChannel) : '' ?>" class="btn btn-primary fw-semibold">
          <i class="fa-solid fa-plus me-1"></i> Create Announcement
        </a>
      </div>

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
                <input type="text" id="searchInput" name="q" class="form-control" placeholder="Search title, audience, creator" value="<?= htmlspecialchars($searchTerm) ?>">
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
                <th>Created By</th>
                <th>Publish Date</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody id="tableBody">
              <?php if (!$visibleRows): ?>
                <tr>
                  <td colspan="7" class="text-center text-muted py-4">No announcements match the current filters.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($visibleRows as $item): ?>
                  <?php
                    $status = $item['status'];
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
                    <td><?= htmlspecialchars($item['created_by']) ?></td>
                    <td><?= htmlspecialchars($item['publish_date']) ?></td>
                    <td class="text-end">
                      <div class="announcement-row-actions">
                        <div class="announcement-primary-actions">
                          <button class="btn btn-primary btn-sm text-white">View</button>
                          <button class="btn btn-warning btn-sm text-dark">Edit</button>
                          <?php if ($status === 'draft' || $isSuperAdmin): ?>
                            <button
                              class="btn btn-danger btn-sm text-white btn-delete-announcement"
                              type="button"
                              data-bs-toggle="modal"
                              data-bs-target="#modalDeleteAnnouncement"
                              data-title="<?= htmlspecialchars($item['title']) ?>">
                              Delete
                            </button>
                          <?php endif; ?>
                        </div>
                        <?php if ($status === 'pending' && $isSuperAdmin): ?>
                          <div class="announcement-review-actions">
                            <button class="btn btn-success btn-sm text-white">Approve</button>
                            <button class="btn btn-danger btn-sm text-white">Return</button>
                          </div>
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
                <a href="<?= htmlspecialchars(buildAnnouncementsUrl('all', 'all')) ?>" class="btn btn-warning"><i class="fas fa-undo"></i>&nbsp;Reset</a>
                <button type="submit" class="btn btn-primary">Apply Filter</button>
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
      <div class="modal-content">
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
          <button type="button" class="btn btn-danger">Delete</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
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
      if (!modalEl || !titleEl) return;

      modalEl.addEventListener("show.bs.modal", function (event) {
        const triggerBtn = event.relatedTarget;
        const title = triggerBtn?.getAttribute("data-title") || "-";
        titleEl.textContent = title;
      });
    })();
  </script>
  <script src="../../JS-Script-Files/Admin-End/tableColumnsGeneric.js?v=20260215-1"></script>
</body>
</html>
