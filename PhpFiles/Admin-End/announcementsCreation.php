<?php
require_once __DIR__ . "/../General/connection.php";
require_once __DIR__ . "/../../Admin-End/includes/admin_guard.php";
require_once __DIR__ . "/../General/adminModulePermissions.php";
require_once __DIR__ . "/contentStore.php";
require_once __DIR__ . "/announcementDelivery.php";
require_once __DIR__ . "/announcementAudience.php";
require_once __DIR__ . "/newsContent.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: " . appUrl('/Admin-End/Contents/Contents.php'));
  exit;
}

function ann_redirect_with_flash(string $location, string $type, string $message): void
{
  $_SESSION["announcement_flash"] = [
    "type" => $type,
    "message" => $message
  ];
  header("Location: " . $location);
  exit;
}

function ann_creator_position_label(array $row): string
{
  $positionAccess = trim((string)($row["position_access"] ?? ""));
  $roleAccess = trim((string)($row["role_access"] ?? ""));
  $raw = $positionAccess !== "" ? $positionAccess : $roleAccess;
  if ($raw !== "") {
    $map = [
      "IT Administrator" => "IT Admin",
      "Barangay Chairman" => "Brgy. Chair",
      "Barangay Official" => "Brgy. Official",
      "Barangay Police" => "Brgy. Police",
      "Barangay Secretary" => "Brgy. Sec.",
      "Desk Officer" => "Desk Off.",
      "Area OIC" => "Area OIC",
      "Department OIC (Officer In Charge)" => "Dept. OIC",
      "SuperAdmin" => "SuperAdmin",
      "Official" => "Official",
      "Personnel" => "Personnel",
      "Employee" => "Personnel"
    ];
    return $map[$raw] ?? $raw;
  }

  return "Admin";
}

function ann_creator_display_label(mysqli $conn, string $userId, string $fallbackRole): string
{
  if ($userId === "") {
    return $fallbackRole !== "" ? $fallbackRole : "Admin";
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
    return $userId;
  }

  $stmt->bind_param("s", $userId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$row) {
    return $userId;
  }

  if (function_exists('pii_decrypt_official_row')) {
    $row = pii_decrypt_official_row($row) ?? $row;
  }

  $firstName = trim((string)($row["firstname"] ?? ""));
  $middleName = trim((string)($row["middlename"] ?? ""));
  $lastName = trim((string)($row["lastname"] ?? ""));
  $suffix = trim((string)($row["suffix"] ?? ""));
  $givenNameParts = preg_split('/\s+/', trim($firstName . " " . $middleName)) ?: [];
  $initials = [];
  foreach ($givenNameParts as $part) {
    $part = trim((string)$part);
    if ($part === "") {
      continue;
    }
    $initials[] = strtoupper(substr($part, 0, 1));
  }
  $firstInitial = $initials ? implode(".", $initials) . "." : "";
  $fullName = trim(
    ($lastName !== "" ? $lastName : "") .
    (($lastName !== "" && $firstInitial !== "") ? ", " : "") .
    $firstInitial .
    ($suffix !== "" ? " " . $suffix : "")
  );

  $position = ann_creator_position_label($row);
  if ($fullName === "") {
    return $position !== "" ? $position : $userId;
  }
  return $fullName . " - " . $position;
}

function ann_faq_items_from_post(): array
{
  $questions = (array)($_POST['faq_questions'] ?? []);
  $answers = (array)($_POST['faq_answers'] ?? []);
  $max = max(count($questions), count($answers));
  $items = [];

  for ($i = 0; $i < $max; $i++) {
    $question = trim((string)($questions[$i] ?? ''));
    $answer = trim((string)($answers[$i] ?? ''));
    if ($question === '' && trim(strip_tags($answer)) === '') {
      continue;
    }
    $items[] = [
      'question' => $question,
      'answer' => $answer,
    ];
  }

  return $items;
}

function ann_build_faq_html(array $items): string
{
  $blocks = [];
  foreach ($items as $item) {
    $question = htmlspecialchars((string)($item['question'] ?? ''), ENT_QUOTES, 'UTF-8');
    $answer = trim((string)($item['answer'] ?? ''));
    $paragraphs = preg_split('/\r?\n\r?\n+/', $answer) ?: [];
    $htmlParts = [];
    foreach ($paragraphs as $paragraph) {
      $paragraph = trim($paragraph);
      if ($paragraph === '') {
        continue;
      }
      $htmlParts[] = '<p>' . nl2br(htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8')) . '</p>';
    }
    if (!$htmlParts) {
      $htmlParts[] = '<p></p>';
    }
    $blocks[] = '<div class="faq-content-item"><h4>' . $question . '</h4><div class="faq-content-answer">' . implode('', $htmlParts) . '</div></div>';
  }

  return implode('', $blocks);
}

function ann_should_send_delivery_for_record(array $record): bool
{
  $contentType = strtolower(trim((string)($record['content_type'] ?? '')));
  if ($contentType === 'news') {
    return false;
  }

  $channels = array_values(array_filter((array)($record['channels'] ?? []), static function ($channel): bool {
    return is_string($channel) && $channel !== '';
  }));

  return (bool)array_intersect($channels, ['sms', 'email']);
}

$contentType = strtolower(trim((string)($_POST['content_type'] ?? 'page')));
if (!in_array($contentType, ['page', 'news', 'delivery', 'faq'], true)) {
  $contentType = 'page';
}

$requiredPermissionKey = match ($contentType) {
  'news' => 'news_management',
  'delivery' => 'announcements_delivery',
  'faq' => 'announcements_faq',
  default => 'announcements_page',
};
$allowedPermissions = amp_get_allowed_permission_keys($conn, (string)($_SESSION['user_id'] ?? ''), (string)($_SESSION['role'] ?? ''));
if (!amp_permission_key_allowed($allowedPermissions, $requiredPermissionKey)) {
  header('Location: ' . appUrl('/Admin-End/Contents/Contents.php'));
  exit;
}

$title = trim((string)($_POST["title"] ?? ""));
$announcementId = trim((string)($_POST["announcement_id"] ?? ""));
$contentHtml = trim((string)($_POST["content_html"] ?? ""));
$publicNewsTitle = trim((string)($_POST["public_news_title"] ?? ""));
$publicNewsContentHtml = trim((string)($_POST["public_news_content_html"] ?? ""));
$headlineImageUrl = trim((string)($_POST["headline_image_url"] ?? ""));
$newsBodyHtml = trim((string)($_POST["news_body_html"] ?? ""));
$newsSectionsJsonInput = trim((string)($_POST["news_sections_json"] ?? ""));
$publicTitle = trim((string)($_POST["public_title"] ?? ""));
$publicContentHtml = trim((string)($_POST["public_content_html"] ?? ""));
$placements = array_values(array_unique(array_filter((array)($_POST["placements"] ?? []), function ($ch) {
  return in_array((string)$ch, ["announcement", "public_news"], true);
})));
$channels = array_values(array_unique(array_filter((array)($_POST["channels"] ?? []), function ($ch) {
  return in_array((string)$ch, ["website", "public", "public_news", "sms", "email"], true);
})));
$audienceScope = strtolower(trim((string)($_POST["audience_scope"] ?? "all")));
if (!in_array($audienceScope, ['all', 'custom'], true)) {
  $audienceScope = 'all';
}
$areas = ann_audience_unique_strings((array)($_POST["area"] ?? []));
$roleGroups = ann_audience_unique_strings((array)($_POST["role_group"] ?? []));
$area = implode(', ', $areas);
$roleGroup = implode(', ', $roleGroups);
$submitAction = trim((string)($_POST["submit_action"] ?? "draft"));
$emailSubjectInput = trim((string)($_POST["email_subject"] ?? ""));
$smsMessageInput = trim((string)($_POST["sms_message"] ?? ""));
$scheduleDate = trim((string)($_POST["schedule_date"] ?? ""));
$scheduleTime = trim((string)($_POST["schedule_time"] ?? ""));
$channelContext = strtolower(trim((string)($_POST["channel_context"] ?? "all")));
if (!in_array($channelContext, ["all", "website", "public", "public_news", "sms", "email"], true)) {
  $channelContext = "all";
}

$redirectBase = appUrl('/Admin-End/Contents/Contents.php');
$redirectUrl = $channelContext === "all" ? $redirectBase : ($redirectBase . "?channel=" . urlencode($channelContext));
if ($contentType === 'news') {
  if ($submitAction === 'draft') {
    $redirectUrl = $redirectBase . '?type_filter=news&news_scope=draft';
  } elseif ($scheduleDate !== '' && $scheduleTime !== '') {
    $redirectUrl = $redirectBase . '?type_filter=news&news_scope=scheduled';
  } else {
    $redirectUrl = $redirectBase . '?type_filter=news';
  }
}

$plainContent = trim(strip_tags($contentHtml));
$faqItemsJson = '';
$newsSectionsJson = '';
$hasAnnouncementPlacement = in_array("announcement", $placements, true);
$isDualPlacement = $hasAnnouncementPlacement && in_array("public_news", $placements, true);

if ($contentType === 'page') {
  $contentChannels = [];
  if (in_array("public_news", $placements, true)) {
    $contentChannels[] = "public_news";
  }
  if ($hasAnnouncementPlacement) {
    if (in_array("public", $channels, true)) {
      $contentChannels[] = "public";
    }
    if (in_array("website", $channels, true)) {
      $contentChannels[] = "website";
    }
  }
  $channels = array_values(array_unique($contentChannels));

  if (!$channels) {
    ann_redirect_with_flash($redirectUrl, "warning", "Select at least one delivery channel.");
  }

  if ($hasAnnouncementPlacement && !array_intersect(["public", "website"], $channels)) {
    ann_redirect_with_flash($redirectUrl, "warning", "Select Guest Page or Account Page when Announcements is selected.");
  }

  if ($title === "") {
    $title = $publicNewsTitle !== "" ? $publicNewsTitle : $publicTitle;
  }

  if ($isDualPlacement) {
    if ($publicNewsTitle === "" || trim(strip_tags($publicNewsContentHtml)) === "") {
      ann_redirect_with_flash($redirectUrl, "warning", "News Section title and content are required when both placements are selected.");
    }
    if ($publicTitle === "" || trim(strip_tags($publicContentHtml)) === "") {
      ann_redirect_with_flash($redirectUrl, "warning", "Announcements title and content are required when both placements are selected.");
    }

    $title = $publicNewsTitle;
    $contentHtml = $publicNewsContentHtml;
  } else {
    if ($title === "") {
      ann_redirect_with_flash($redirectUrl, "warning", "Announcement title is required.");
    }
    if ($plainContent === "") {
      ann_redirect_with_flash($redirectUrl, "warning", "Announcement content is required.");
    }

    if (in_array("public_news", $placements, true)) {
      $publicNewsTitle = $title;
      $publicNewsContentHtml = $contentHtml;
    }
    if ($hasAnnouncementPlacement) {
      $publicTitle = $title;
      $publicContentHtml = $contentHtml;
    }
  }
} elseif ($contentType === 'news') {
  $placements = ['public_news'];
  $channels = ['public_news'];
  $publicTitle = '';
  $publicContentHtml = '';
  $areas = [];
  $roleGroups = [];
  $area = '';
  $roleGroup = '';
  $audienceScope = 'all';

  if ($title === '') {
    ann_redirect_with_flash($redirectUrl, "warning", "News heading is required.");
  }
  if ($headlineImageUrl === '') {
    ann_redirect_with_flash($redirectUrl, "warning", "Upload a headline image before saving the news article.");
  }
  if (trim(strip_tags($newsBodyHtml)) === '') {
    ann_redirect_with_flash($redirectUrl, "warning", "News body is required.");
  }

  $newsSections = ann_news_decode_sections_json($newsSectionsJsonInput);
  $contentHtml = ann_news_compose_html($headlineImageUrl, $newsBodyHtml, $newsSections, $title);
  $plainContent = trim(strip_tags($contentHtml));
  if ($plainContent === '') {
    ann_redirect_with_flash($redirectUrl, "warning", "News content is required.");
  }

  $publicNewsTitle = $title;
  $publicNewsContentHtml = $contentHtml;
  $newsSectionsJson = $newsSections ? (json_encode($newsSections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '') : '';
} elseif ($contentType === 'delivery') {
  $placements = [];
  $publicNewsTitle = '';
  $publicNewsContentHtml = '';
  $headlineImageUrl = '';
  $newsSectionsJson = '';
  $publicTitle = '';
  $publicContentHtml = '';
  $channels = array_values(array_unique(array_filter($channels, function ($ch) {
    return in_array((string)$ch, ['sms', 'email'], true);
  })));

  if (!$channels) {
    ann_redirect_with_flash($redirectUrl, 'warning', 'Select SMS or Email for this announcement type.');
  }
  if ($title === '') {
    ann_redirect_with_flash($redirectUrl, 'warning', 'Title is required.');
  }
  if ($plainContent === '') {
    ann_redirect_with_flash($redirectUrl, 'warning', 'Body is required.');
  }
  if (in_array('sms', $channels, true) && mb_strlen($smsMessageInput) > 320) {
    ann_redirect_with_flash($redirectUrl, 'warning', 'SMS message must be 320 characters or less.');
  }
} else {
  $placements = [];
  $channels = [];
  $publicNewsTitle = '';
  $publicNewsContentHtml = '';
  $headlineImageUrl = '';
  $newsSectionsJson = '';
  $publicTitle = '';
  $publicContentHtml = '';
  $faqItems = ann_faq_items_from_post();

  if (!$faqItems) {
    ann_redirect_with_flash($redirectUrl, 'warning', 'Add at least one FAQ question and answer.');
  }
  if (count($faqItems) > 20) {
    ann_redirect_with_flash($redirectUrl, 'warning', 'You can only save up to 20 FAQ questions in one content item.');
  }
  foreach ($faqItems as $faqItem) {
    if (($faqItem['question'] ?? '') === '' || trim(strip_tags((string)($faqItem['answer'] ?? ''))) === '') {
      ann_redirect_with_flash($redirectUrl, 'warning', 'Complete both the question and answer for every FAQ entry.');
    }
  }

  $title = (string)($faqItems[0]['question'] ?? 'FAQ Item');
  $contentHtml = ann_build_faq_html($faqItems);
  $plainContent = trim(strip_tags($contentHtml));
  $faqItemsJson = json_encode($faqItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if ($faqItemsJson === false) {
    $faqItemsJson = '';
  }
}

if ($audienceScope === 'custom' && !$areas && !$roleGroups) {
  ann_redirect_with_flash($redirectUrl, "warning", "Choose at least one area or role group for a custom audience.");
}

$audienceConfig = ann_audience_config([
  'audience_scope' => $audienceScope,
  'areas' => $areas,
  'role_groups' => $roleGroups,
]);
$areas = $audienceConfig['areas'];
$roleGroups = $audienceConfig['role_groups'];
$area = implode(', ', $areas);
$roleGroup = implode(', ', $roleGroups);
$audienceScope = $audienceConfig['scope'];

$sessionRole = strtolower(trim((string)($_SESSION["role"] ?? "")));
$isSuperAdmin = $sessionRole === "superadmin";
$status = "draft";
if ($submitAction === "pending" && !$isSuperAdmin) {
  $status = "pending";
}
if ($submitAction === "approved" && $isSuperAdmin) {
  $status = "approved";
}
$audience = ann_audience_build_label($audienceScope, $areas, $roleGroups);

$publishDate = "-";
if ($scheduleDate !== "") {
  $publishDate = $scheduleDate;
  if ($scheduleTime !== "") {
    $publishDate .= " " . $scheduleTime;
  }
}

$createdByUserId = trim((string)($_SESSION["user_id"] ?? ""));
$createdByRole = trim((string)($_SESSION["role"] ?? "Admin"));
$createdByDisplay = ann_creator_display_label($conn, $createdByUserId, $createdByRole);

$record = [
  "id" => $announcementId !== '' ? $announcementId : announcement_generate_id(),
  "title" => $title,
  "content_type" => $contentType,
  "audience" => $audience,
  "audience_scope" => $audienceScope,
  "area" => $area,
  "role_group" => $roleGroup,
  "channels" => $channels,
  "status" => $status,
  "publish_date" => $publishDate,
  "created_by" => $createdByDisplay,
  "created_by_user_id" => $createdByUserId,
  "created_by_role" => $createdByRole,
  "content_html" => $contentHtml,
  "public_news_title" => $publicNewsTitle,
  "public_news_content_html" => $publicNewsContentHtml,
  "news_headline_image_url" => $headlineImageUrl,
  "news_sections_json" => $newsSectionsJson,
  "public_title" => $publicTitle,
  "public_content_html" => $publicContentHtml,
  "created_at" => date("Y-m-d H:i:s"),
  "faq_items_json" => $faqItemsJson,
];
$record = array_merge($record, ann_delivery_compose_fields($record, $emailSubjectInput, $smsMessageInput));

$all = announcements_load_all();
$existingRecord = null;
$existingIndex = null;
if ($announcementId !== '') {
  foreach ($all as $idx => $item) {
    if ((string)($item['id'] ?? '') !== $announcementId) {
      continue;
    }
    $existingRecord = $item;
    $existingIndex = $idx;
    break;
  }
}

if ($existingRecord !== null) {
  $existingContentType = strtolower(trim((string)($existingRecord['content_type'] ?? 'page')));
  $existingStatus = strtolower(trim((string)($existingRecord['status'] ?? 'draft')));
  $existingOwnerUserId = trim((string)($existingRecord['created_by_user_id'] ?? ''));
  $fallbackOwnerUserId = trim((string)($existingRecord['created_by'] ?? ''));
  if ($existingOwnerUserId === '' && strpos($fallbackOwnerUserId, ' - ') === false) {
    $existingOwnerUserId = $fallbackOwnerUserId;
  }
  $isOwnedByCurrentUser = $createdByUserId !== '' && $existingOwnerUserId !== '' && $existingOwnerUserId === $createdByUserId;

  if ($existingContentType !== 'news') {
    ann_redirect_with_flash($redirectUrl, "danger", "Only news drafts can be updated from this page.");
  }
  if (!$isSuperAdmin && !$isOwnedByCurrentUser) {
    ann_redirect_with_flash($redirectUrl, "danger", "Only the content creator or SuperAdmin can update this news draft.");
  }
  if ($existingStatus === 'archived') {
    ann_redirect_with_flash($redirectUrl, "warning", "Archived news articles cannot be updated from this page.");
  }

  $record['created_at'] = (string)($existingRecord['created_at'] ?? date("Y-m-d H:i:s"));
  $record['created_by'] = (string)($existingRecord['created_by'] ?? $createdByDisplay);
  $record['created_by_user_id'] = (string)($existingRecord['created_by_user_id'] ?? $createdByUserId);
  $record['created_by_role'] = (string)($existingRecord['created_by_role'] ?? $createdByRole);
  $record['updated_at'] = date("Y-m-d H:i:s");
  $record['updated_by'] = $createdByUserId !== '' ? $createdByUserId : $createdByDisplay;
  $record['review_result'] = $status === "approved" ? "approved" : (($status === "draft") ? (string)($existingRecord['review_result'] ?? '') : '');
  $record['review_note'] = $status === "draft" ? (string)($existingRecord['review_note'] ?? '') : '';
  $record['reviewed_at'] = $status === "approved" ? date("Y-m-d H:i:s") : (string)($existingRecord['reviewed_at'] ?? '');
  $record['reviewed_by'] = $status === "approved" ? $createdByUserId : (string)($existingRecord['reviewed_by'] ?? '');
  $all[$existingIndex] = array_merge($existingRecord, $record);
} else {
  array_unshift($all, $record);
}
if (!announcements_save_all($all)) {
  ann_redirect_with_flash($redirectUrl, "danger", "Unable to save content item.");
}

$itemLabel = [
  'page' => 'Page announcement',
  'news' => 'News article',
  'delivery' => 'SMS and email announcement',
  'faq' => 'FAQ item'
][$contentType] ?? 'Content item';

$msg = $itemLabel . " saved as draft.";
if ($status === "pending") {
  $msg = ucfirst($itemLabel) . " submitted for review.";
}
if ($status === "approved") {
  $savedRecord = $existingRecord !== null && $existingIndex !== null ? $all[$existingIndex] : $all[0];
  if (ann_should_send_delivery_for_record($savedRecord)) {
    $deliveryResult = ann_delivery_send($conn, $savedRecord);
    announcements_save_all($all);
    $msg = ucfirst($itemLabel) . " posted successfully." . ann_delivery_message_suffix($deliveryResult);
  } else {
    $msg = ucfirst($itemLabel) . " posted successfully.";
  }
}

if ($contentType === 'news') {
  if ($status === 'draft') {
    $redirectUrl = $redirectBase . '?type_filter=news&news_scope=draft';
  } elseif ($scheduleDate !== '' && $scheduleTime !== '') {
    $redirectUrl = $redirectBase . '?type_filter=news&news_scope=scheduled';
  } else {
    $redirectUrl = $redirectBase . '?type_filter=news';
  }
}
ann_redirect_with_flash($redirectUrl, "success", $msg);
