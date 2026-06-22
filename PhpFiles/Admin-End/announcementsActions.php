<?php
require_once __DIR__ . "/../General/connection.php";
require_once __DIR__ . "/../General/security.php";
require_once __DIR__ . "/contentStore.php";
require_once __DIR__ . "/announcementDelivery.php";
require_once __DIR__ . "/announcementAudience.php";
require_once __DIR__ . "/newsContent.php";

requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin', 'Employee'], false);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: " . appUrl('/Admin-End/Contents/Contents.php'));
  exit;
}
verifyCsrfToken(false);

function ann_action_redirect(string $channel, string $status, string $q, string $queueQ, string $queueChannel, string $type, string $message): void
{
  global $typeFilter, $queueType;
  $query = ['channel' => $channel, 'status' => $status];
  if ($typeFilter !== 'all') {
    $query['type_filter'] = $typeFilter;
  }
  if ($q !== '') {
    $query['q'] = $q;
  }
  if ($queueQ !== '') {
    $query['queue_q'] = $queueQ;
  }
  if ($queueChannel !== 'all') {
    $query['queue_channel'] = $queueChannel;
  }
  if ($queueType !== 'all') {
    $query['queue_type'] = $queueType;
  }
  $_SESSION['announcement_flash'] = ['type' => $type, 'message' => $message];
  header("Location: " . appUrl('/Admin-End/Contents/Contents.php') . "?" . http_build_query($query));
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
$typeFilter = strtolower(trim((string)($_POST['type_filter'] ?? 'all')));
$q = trim((string)($_POST['q'] ?? ''));
$queueQ = trim((string)($_POST['queue_q'] ?? ''));
$queueChannel = strtolower(trim((string)($_POST['queue_channel'] ?? 'all')));
$queueType = strtolower(trim((string)($_POST['queue_type'] ?? 'all')));

if (!in_array($channel, ['all', 'website', 'public', 'public_news', 'sms', 'email'], true)) {
  $channel = 'all';
}
if (!in_array($status, ['all', 'approved', 'denied', 'pending', 'draft'], true)) {
  $status = 'all';
}
if (!in_array($typeFilter, ['all', 'page', 'news', 'delivery', 'faq'], true)) {
  $typeFilter = 'all';
}
if (!in_array($queueChannel, ['all', 'website', 'public', 'public_news', 'sms', 'email'], true)) {
  $queueChannel = 'all';
}
if (!in_array($queueType, ['all', 'page', 'news', 'delivery', 'faq'], true)) {
  $queueType = 'all';
}
if ($announcementId === '' || !in_array($action, ['approve', 'deny', 'delete', 'update', 'submit_review'], true)) {
  ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'Invalid content action.');
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
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Only SuperAdmin can approve content items.');
    }
    if ($isCreatedBySuperAdmin) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'SuperAdmin-created content items are not part of the review queue.');
    }
    if ($currentStatus !== 'pending') {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'Only pending content items can be approved.');
    }
    $rows[$idx]['status'] = 'approved';
    $rows[$idx]['review_result'] = 'approved';
    $rows[$idx]['review_note'] = '';
    $rows[$idx]['reviewed_at'] = date('Y-m-d H:i:s');
    $rows[$idx]['reviewed_by'] = (string)($_SESSION['user_id'] ?? 'SuperAdmin');
    if (empty((string)($rows[$idx]['publish_date'] ?? '')) || (string)($rows[$idx]['publish_date'] ?? '-') === '-') {
      $rows[$idx]['publish_date'] = date('Y-m-d H:i');
    }
    if (!announcements_save_all($rows)) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Unable to save approval change.');
    }
    $deliveryResult = ann_delivery_send($conn, $rows[$idx]);
    announcements_save_all($rows);
    ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'success', 'Content approved.' . ann_delivery_message_suffix($deliveryResult));
  }

  if ($action === 'deny') {
    if (!$isSuperAdmin) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Only SuperAdmin can deny content items.');
    }
    if ($isCreatedBySuperAdmin) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'SuperAdmin-created content items are not part of the review queue.');
    }
    if ($currentStatus !== 'pending') {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'Only pending content items can be denied.');
    }
    // Denied content items are returned to draft for revision.
    $rows[$idx]['status'] = 'draft';
    $rows[$idx]['review_result'] = 'denied';
    $rows[$idx]['review_note'] = 'Your content item was denied and returned to draft for revision.';
    $rows[$idx]['reviewed_at'] = date('Y-m-d H:i:s');
    $rows[$idx]['reviewed_by'] = (string)($_SESSION['user_id'] ?? 'SuperAdmin');
    if (!announcements_save_all($rows)) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Unable to save deny change.');
    }
    ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'success', 'Content denied and returned to draft.');
  }

  if ($action === 'update') {
    if ($currentStatus === 'draft' && !$isOwnedByCurrentUser) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Only the content creator can edit an unsubmitted draft.');
    }
    if (!$isSuperAdmin && !$isOwnedByCurrentUser) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Only the content creator can edit this content item.');
    }

    $title = trim((string)($_POST['title'] ?? ''));
    $contentHtml = trim((string)($_POST['content_html'] ?? ''));
    $publicNewsTitle = trim((string)($_POST['public_news_title'] ?? ''));
    $publicNewsContentHtml = trim((string)($_POST['public_news_content_html'] ?? ''));
    $headlineImageUrlInput = trim((string)($_POST['headline_image_url'] ?? ''));
    $newsSectionsJsonInput = trim((string)($_POST['news_sections_json'] ?? ''));
    $publicTitle = trim((string)($_POST['public_title'] ?? ''));
    $publicContentHtml = trim((string)($_POST['public_content_html'] ?? ''));
    $audienceScope = strtolower(trim((string)($_POST['audience_scope'] ?? (string)($item['audience_scope'] ?? 'all'))));
    if (!in_array($audienceScope, ['all', 'custom'], true)) {
      $audienceScope = 'all';
    }
    $areas = ann_audience_unique_strings((array)($_POST['area'] ?? ann_audience_parse_csv_values((string)($item['area'] ?? ''))));
    $roleGroups = ann_audience_unique_strings((array)($_POST['role_group'] ?? ann_audience_parse_csv_values((string)($item['role_group'] ?? ''))));
    $publishDate = trim((string)($_POST['publish_date'] ?? '-'));
    $smsMessageInput = trim((string)($_POST['sms_message'] ?? ''));
    $emailSubjectInput = trim((string)($_POST['email_subject'] ?? ''));
    $nextStatus = strtolower(trim((string)($_POST['status_update'] ?? $currentStatus)));
    $recordContentType = strtolower(trim((string)($item['content_type'] ?? 'page')));
    if (!in_array($recordContentType, ['page', 'news', 'delivery', 'faq'], true)) {
      $recordContentType = 'page';
    }
    $placements = array_values(array_unique(array_filter((array)($_POST['placements'] ?? []), function ($placement) {
      return in_array((string)$placement, ['announcement', 'public_news'], true);
    })));
    $channels = array_values(array_unique(array_filter((array)($_POST['channels'] ?? []), function ($ch) {
      return in_array((string)$ch, ['website', 'public', 'public_news', 'sms', 'email'], true);
    })));

    if ($audienceScope === 'custom' && !$areas && !$roleGroups) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'Choose at least one area or role group for a custom audience.');
    }

    $audienceConfig = ann_audience_config([
      'audience_scope' => $audienceScope,
      'areas' => $areas,
      'role_groups' => $roleGroups,
    ]);
    $audienceScope = $audienceConfig['scope'];
    $areas = $audienceConfig['areas'];
    $roleGroups = $audienceConfig['role_groups'];
    $area = implode(', ', $areas);
    $roleGroup = implode(', ', $roleGroups);
    $audience = ann_audience_build_label($audienceScope, $areas, $roleGroups);

    $hasAnnouncementPlacement = false;
    $hasNewsPlacement = false;
    $newsHeadlineImageUrl = trim((string)($item['news_headline_image_url'] ?? ''));
    $newsSectionsJson = trim((string)($item['news_sections_json'] ?? ''));
    if ($recordContentType === 'page') {
      if (!$placements) {
        ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'Select at least one page placement.');
      }
      $hasAnnouncementPlacement = in_array('announcement', $placements, true);
      $hasNewsPlacement = in_array('public_news', $placements, true);
      if ($hasAnnouncementPlacement && !array_intersect(['public', 'website'], $channels)) {
        ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'Select Guest Page or Account Page when Announcements is selected.');
      }

      if ($hasAnnouncementPlacement && $hasNewsPlacement) {
        if ($publicNewsTitle === '' || trim(strip_tags($publicNewsContentHtml)) === '') {
          ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'News Section title and body are required when both placements are selected.');
        }
        if ($publicTitle === '' || trim(strip_tags($publicContentHtml)) === '') {
          ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'Announcements title and body are required when both placements are selected.');
        }
        $title = $publicNewsTitle;
        $contentHtml = $publicNewsContentHtml;
      } elseif ($hasNewsPlacement) {
        if ($title === '' || trim(strip_tags($contentHtml)) === '') {
          ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'Title and body are required.');
        }
        $publicNewsTitle = $title;
        $publicNewsContentHtml = $contentHtml;
        $publicTitle = '';
        $publicContentHtml = '';
      } elseif ($hasAnnouncementPlacement) {
        if ($title === '' || trim(strip_tags($contentHtml)) === '') {
          ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'Title and body are required.');
        }
        $publicTitle = $title;
        $publicContentHtml = $contentHtml;
        $publicNewsTitle = '';
        $publicNewsContentHtml = '';
      }

      $resolvedChannels = [];
      if ($hasNewsPlacement) {
        $resolvedChannels[] = 'public_news';
      }
      if ($hasAnnouncementPlacement) {
        if (in_array('public', $channels, true)) {
          $resolvedChannels[] = 'public';
        }
        if (in_array('website', $channels, true)) {
          $resolvedChannels[] = 'website';
        }
      }
      $channels = array_values(array_unique($resolvedChannels));
      if (!$channels) {
        ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'Select at least one valid delivery destination.');
      }
    } elseif ($recordContentType === 'news') {
      $placements = ['public_news'];
      $channels = ['public_news'];
      $publicTitle = '';
      $publicContentHtml = '';
      $audienceScope = 'all';
      $areas = [];
      $roleGroups = [];
      $area = '';
      $roleGroup = '';
      $audience = 'All Residents';

      if ($title === '' || trim(strip_tags($contentHtml)) === '') {
        ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'News heading and body are required.');
      }

      $publicNewsTitle = $title;
      $publicNewsContentHtml = $contentHtml;
      $resolvedHeadlineImageUrl = $headlineImageUrlInput !== ''
        ? $headlineImageUrlInput
        : ($newsHeadlineImageUrl !== '' ? $newsHeadlineImageUrl : ann_news_extract_first_image_url($contentHtml));
      if ($resolvedHeadlineImageUrl === '') {
        ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'News article needs a headline image.');
      }
      $newsHeadlineImageUrl = $resolvedHeadlineImageUrl;

      $resolvedSectionsJson = $newsSectionsJsonInput !== '' ? $newsSectionsJsonInput : $newsSectionsJson;
      if ($resolvedSectionsJson !== '') {
        $resolvedSections = ann_news_decode_sections_json($resolvedSectionsJson);
        $newsSectionsJson = $resolvedSections ? (json_encode($resolvedSections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '') : '';
      } else {
        $newsSectionsJson = '';
      }
    } elseif ($recordContentType === 'delivery') {
      $placements = [];
      $publicNewsTitle = '';
      $publicNewsContentHtml = '';
      $newsHeadlineImageUrl = '';
      $newsSectionsJson = '';
      $publicTitle = '';
      $publicContentHtml = '';
      $channels = array_values(array_unique(array_filter($channels, function ($ch) {
        return in_array((string)$ch, ['sms', 'email'], true);
      })));
      if ($title === '' || trim(strip_tags($contentHtml)) === '') {
        ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'Title and body are required.');
      }
      if (!$channels) {
        ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'Select SMS or Email for this content type.');
      }
      if (in_array('sms', $channels, true) && mb_strlen($smsMessageInput) > 320) {
        ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'SMS message must be 320 characters or less.');
      }
    } else {
      $placements = [];
      $channels = [];
      $publicNewsTitle = '';
      $publicNewsContentHtml = '';
      $newsHeadlineImageUrl = '';
      $newsSectionsJson = '';
      $publicTitle = '';
      $publicContentHtml = '';
      if ($title === '' || trim(strip_tags($contentHtml)) === '') {
        ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'Question and answer are required.');
      }
    }
    if (!in_array($nextStatus, ['draft', 'pending', 'approved'], true)) {
      $nextStatus = $currentStatus;
    }
    if (!$isSuperAdmin) {
      if ($currentStatus === 'approved') {
        // Any Admin edit to published content must go through SuperAdmin review again.
        $nextStatus = 'pending';
      } elseif ($currentStatus === 'pending') {
        // Editing already submitted content keeps it in the pending review queue.
        $nextStatus = 'pending';
      } elseif ($nextStatus === 'approved') {
        $nextStatus = 'pending';
      }
    }

    $rows[$idx]['title'] = $title;
    $rows[$idx]['audience'] = $audience;
    $rows[$idx]['audience_scope'] = $audienceScope;
    $rows[$idx]['area'] = $area;
    $rows[$idx]['role_group'] = $roleGroup;
    $rows[$idx]['channels'] = $channels;
    $rows[$idx]['content_html'] = $contentHtml;
    $rows[$idx]['public_news_title'] = $publicNewsTitle;
    $rows[$idx]['public_news_content_html'] = $publicNewsContentHtml;
    $rows[$idx]['news_headline_image_url'] = $newsHeadlineImageUrl;
    $rows[$idx]['news_sections_json'] = $newsSectionsJson;
    $rows[$idx]['public_title'] = $publicTitle;
    $rows[$idx]['public_content_html'] = $publicContentHtml;
    if ($recordContentType === 'faq') {
      $rows[$idx]['faq_items_json'] = json_encode([[
        'question' => $title,
        'answer' => $contentHtml
      ]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
    $rows[$idx]['publish_date'] = $publishDate === '' ? '-' : $publishDate;
    $rows[$idx]['status'] = $nextStatus;
    $rows[$idx]['updated_at'] = date('Y-m-d H:i:s');
    $rows[$idx]['updated_by'] = (string)($_SESSION['user_id'] ?? ($_SESSION['role'] ?? 'Admin'));
    $rows[$idx] = array_merge($rows[$idx], ann_delivery_compose_fields($rows[$idx], $emailSubjectInput, $smsMessageInput));
    if ($nextStatus === 'draft') {
      if (strtolower((string)($item['review_result'] ?? '')) === 'denied') {
        $rows[$idx]['review_result'] = '';
        $rows[$idx]['review_note'] = '';
      }
    } else {
      $rows[$idx]['review_result'] = $nextStatus === 'approved' ? 'approved' : '';
      $rows[$idx]['review_note'] = '';
    }

    if (!announcements_save_all($rows)) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Unable to update content item.');
    }
    $deliverySuffix = '';
    if ($isSuperAdmin && $nextStatus === 'approved') {
      $deliveryResult = ann_delivery_send($conn, $rows[$idx]);
      announcements_save_all($rows);
      $deliverySuffix = ann_delivery_message_suffix($deliveryResult);
    }
    $msg = (!$isSuperAdmin && $nextStatus === 'pending')
      ? 'Content submitted for review. Please wait for approval.'
      : ((strtolower((string)($item['review_result'] ?? '')) === 'denied' && $nextStatus === 'draft')
        ? 'Content changes saved as draft.'
        : 'Content updated successfully.' . $deliverySuffix);
    ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'success', $msg);
  }

  if ($action === 'submit_review') {
    if ($isSuperAdmin) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'SuperAdmin content items do not require review submission.');
    }
    if (!$isOwnedByCurrentUser) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Only the content creator can submit this for review.');
    }
    if ($currentStatus !== 'draft') {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'Only draft content items can be submitted for review.');
    }

    $rows[$idx]['status'] = 'pending';
    $rows[$idx]['updated_at'] = date('Y-m-d H:i:s');
    $rows[$idx]['updated_by'] = (string)($_SESSION['user_id'] ?? ($_SESSION['role'] ?? 'Admin'));
    $rows[$idx]['review_result'] = '';
    $rows[$idx]['review_note'] = '';

    if (!announcements_save_all($rows)) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Unable to submit content item for review.');
    }
    ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'success', 'Content submitted for review.');
  }

  if ($action === 'delete') {
    if (!$isSuperAdmin && $currentStatus === 'draft' && !$isOwnedByCurrentUser) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Only the content creator can delete an unsubmitted draft.');
    }
    if (!$isSuperAdmin && !$isOwnedByCurrentUser) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Only the content creator can delete this content item.');
    }
    if (!$isSuperAdmin && $currentStatus !== 'draft') {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Only draft content items can be deleted.');
    }
    array_splice($rows, $idx, 1);
    if (!announcements_save_all($rows)) {
      ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'danger', 'Unable to delete content item.');
    }
    ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'success', 'Content deleted.');
  }
}

if (!$found) {
  ann_action_redirect($channel, $status, $q, $queueQ, $queueChannel, 'warning', 'Content item not found.');
}
