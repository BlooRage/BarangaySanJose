<?php
require_once __DIR__ . "/../General/connection.php";
require_once __DIR__ . "/../General/security.php";
require_once __DIR__ . "/announcementsStore.php";

requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin', 'Employee'], false);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: ../../Admin-End/Announcements/Announcements.php");
  exit;
}
verifyCsrfToken(false);

function ann_action_redirect(string $channel, string $status, string $q, string $queueQ, string $queueChannel, string $type, string $message): void
{
  $query = ['channel' => $channel, 'status' => $status];
  if ($q !== '') {
    $query['q'] = $q;
  }
  if ($queueQ !== '') {
    $query['queue_q'] = $queueQ;
  }
  if ($queueChannel !== 'all') {
    $query['queue_channel'] = $queueChannel;
  }
  $_SESSION['announcement_flash'] = ['type' => $type, 'message' => $message];
  header("Location: ../../Admin-End/Announcements/Announcements.php?" . http_build_query($query));
  exit;
}

function ann_action_current_user_display(mysqli $conn, string $userId, string $fallback): string
{
  if ($userId === '') {
    return $fallback;
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
    return $fallback;
  }

  $stmt->bind_param("s", $userId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$row) {
    return $fallback;
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
  $nameShort = trim(
    ($lastName !== '' ? $lastName : '') .
    (($lastName !== '' && $initials) ? ', ' : '') .
    ($initials ? implode('.', $initials) . '.' : '') .
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

  if ($nameShort === '') {
    return $fallback;
  }
  return $position !== '' ? ($nameShort . ' - ' . $position) : $nameShort;
}

$action = strtolower(trim((string)($_POST['action'] ?? '')));
$announcementId = trim((string)($_POST['announcement_id'] ?? ''));
$channel = strtolower(trim((string)($_POST['channel'] ?? 'all')));
$status = strtolower(trim((string)($_POST['status'] ?? 'all')));
$q = trim((string)($_POST['q'] ?? ''));
$queueQ = trim((string)($_POST['queue_q'] ?? ''));
$queueChannel = strtolower(trim((string)($_POST['queue_channel'] ?? 'all')));

if (!in_array($channel, ['all', 'website', 'sms', 'email'], true)) {
  $channel = 'all';
}
if (!in_array($status, ['all', 'approved', 'pending', 'draft'], true)) {
  $status = 'all';
}
if (!in_array($queueChannel, ['all', 'website', 'sms', 'email'], true)) {
  $queueChannel = 'all';
}
if ($announcementId === '' || !in_array($action, ['approve', 'deny', 'delete', 'update', 'submit_review'], true)) {
  ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'Invalid announcement action.');
}

$sessionRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
$isSuperAdmin = $sessionRole === 'superadmin';
$currentUserId = trim((string)($_SESSION['user_id'] ?? ''));
$currentUserDisplayLabel = ann_action_current_user_display($conn, $currentUserId, $currentUserId);
$rows = announcements_load_all();
$found = false;

foreach ($rows as $idx => $item) {
  if ((string)($item['id'] ?? '') !== $announcementId) {
    continue;
  }

  $found = true;
  $currentStatus = strtolower((string)($item['status'] ?? 'draft'));
  $ownerUserId = trim((string)($item['created_by_user_id'] ?? ''));
  if ($ownerUserId === '') {
    $ownerUserId = trim((string)($item['created_by'] ?? ''));
  }
  $isOwnedByCurrentUser = ($currentUserId !== '' && $ownerUserId === $currentUserId);
  if (!$isOwnedByCurrentUser && trim((string)($item['created_by_user_id'] ?? '')) === '') {
    $createdByLabel = trim((string)($item['created_by'] ?? ''));
    if ($createdByLabel !== '' && $currentUserDisplayLabel !== '' && $createdByLabel === $currentUserDisplayLabel) {
      $isOwnedByCurrentUser = true;
    }
  }
  $creatorRoleRaw = (string)($item['created_by_role'] ?? 'Admin');
  $creatorRole = function_exists('normalizeRoleName')
    ? normalizeRoleName($creatorRoleRaw)
    : strtolower(trim($creatorRoleRaw));
  $isCreatedBySuperAdmin = $creatorRole === 'superadmin';

  if ($action === 'approve') {
    if (!$isSuperAdmin) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Only SuperAdmin can approve announcements.');
    }
    if ($isCreatedBySuperAdmin) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'SuperAdmin-created announcements are not part of the review queue.');
    }
    if ($currentStatus !== 'pending') {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'Only pending announcements can be approved.');
    }
    $rows[$idx]['status'] = 'approved';
    $rows[$idx]['reviewed_at'] = date('Y-m-d H:i:s');
    $rows[$idx]['reviewed_by'] = (string)($_SESSION['user_id'] ?? 'SuperAdmin');
    if (empty((string)($rows[$idx]['publish_date'] ?? '')) || (string)($rows[$idx]['publish_date'] ?? '-') === '-') {
      $rows[$idx]['publish_date'] = date('Y-m-d H:i');
    }
    if (!announcements_save_all($rows)) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Unable to save approval change.');
    }
    ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'success', 'Announcement approved.');
  }

  if ($action === 'deny') {
    if (!$isSuperAdmin) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Only SuperAdmin can deny announcements.');
    }
    if ($isCreatedBySuperAdmin) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'SuperAdmin-created announcements are not part of the review queue.');
    }
    if ($currentStatus !== 'pending') {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'Only pending announcements can be denied.');
    }
    // Denied announcements are returned to Draft for revision.
    $rows[$idx]['status'] = 'draft';
    $rows[$idx]['reviewed_at'] = date('Y-m-d H:i:s');
    $rows[$idx]['reviewed_by'] = (string)($_SESSION['user_id'] ?? 'SuperAdmin');
    if (!announcements_save_all($rows)) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Unable to save deny change.');
    }
    ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'success', 'Announcement denied and returned to draft.');
  }

  if ($action === 'update') {
    if ($currentStatus === 'draft' && !$isOwnedByCurrentUser) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Only the announcement creator can edit an unsubmitted draft.');
    }
    if (!$isSuperAdmin && !$isOwnedByCurrentUser) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Only the announcement creator can edit this announcement.');
    }

    $title = trim((string)($_POST['title'] ?? ''));
    $audience = trim((string)($_POST['audience'] ?? ''));
    $contentHtml = trim((string)($_POST['content_html'] ?? ''));
    $publishDate = trim((string)($_POST['publish_date'] ?? '-'));
    $nextStatus = strtolower(trim((string)($_POST['status_update'] ?? $currentStatus)));
    $channels = array_values(array_unique(array_filter((array)($_POST['channels'] ?? []), function ($ch) {
      return in_array((string)$ch, ['website', 'sms', 'email'], true);
    })));

    if ($title === '' || $audience === '') {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'Title and audience are required.');
    }
    if (trim(strip_tags($contentHtml)) === '') {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'Announcement content is required.');
    }
    if (!$channels) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'Select at least one delivery channel.');
    }
    if (!in_array($nextStatus, ['draft', 'pending', 'approved'], true)) {
      $nextStatus = $currentStatus;
    }
    if (!$isSuperAdmin) {
      if ($currentStatus === 'approved') {
        // Any Admin edit to a published announcement must go through SuperAdmin review again.
        $nextStatus = 'pending';
      } elseif ($nextStatus === 'approved') {
        $nextStatus = 'pending';
      }
    }

    $rows[$idx]['title'] = $title;
    $rows[$idx]['audience'] = $audience;
    $rows[$idx]['channels'] = $channels;
    $rows[$idx]['content_html'] = $contentHtml;
    $rows[$idx]['publish_date'] = $publishDate === '' ? '-' : $publishDate;
    $rows[$idx]['status'] = $nextStatus;
    $rows[$idx]['updated_at'] = date('Y-m-d H:i:s');
    $rows[$idx]['updated_by'] = (string)($_SESSION['user_id'] ?? ($_SESSION['role'] ?? 'Admin'));

    if (!announcements_save_all($rows)) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Unable to update announcement.');
    }
    $msg = (!$isSuperAdmin && $currentStatus === 'approved')
      ? 'Announcement updated and resubmitted for review.'
      : 'Announcement updated successfully.';
    ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'success', $msg);
  }

  if ($action === 'submit_review') {
    if ($isSuperAdmin) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'SuperAdmin announcements do not require review submission.');
    }
    if (!$isOwnedByCurrentUser) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Only the announcement creator can submit this for review.');
    }
    if ($currentStatus !== 'draft') {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'Only draft announcements can be submitted for review.');
    }

    $rows[$idx]['status'] = 'pending';
    $rows[$idx]['updated_at'] = date('Y-m-d H:i:s');
    $rows[$idx]['updated_by'] = (string)($_SESSION['user_id'] ?? ($_SESSION['role'] ?? 'Admin'));

    if (!announcements_save_all($rows)) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Unable to submit announcement for review.');
    }
    ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'success', 'Announcement submitted for review.');
  }

  if ($action === 'delete') {
    if ($currentStatus === 'draft' && !$isOwnedByCurrentUser) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Only the announcement creator can delete an unsubmitted draft.');
    }
    if (!$isSuperAdmin && !$isOwnedByCurrentUser) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Only the announcement creator can delete this announcement.');
    }
    if ($currentStatus !== 'draft') {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Only draft announcements can be deleted.');
    }
    array_splice($rows, $idx, 1);
    if (!announcements_save_all($rows)) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Unable to delete announcement.');
    }
    ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'success', 'Announcement deleted.');
  }
}

if (!$found) {
  ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'Announcement not found.');
}
