<?php
require_once __DIR__ . "/../General/connection.php";
require_once __DIR__ . "/../../Admin-End/includes/admin_guard.php";
require_once __DIR__ . "/announcementsStore.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: ../../Admin-End/Announcements/Announcements.php");
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
      "Employee" => "Employee"
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

$title = trim((string)($_POST["title"] ?? ""));
$contentHtml = trim((string)($_POST["content_html"] ?? ""));
$channels = array_values(array_unique(array_filter((array)($_POST["channels"] ?? []), function ($ch) {
  return in_array((string)$ch, ["website", "public", "public_news", "sms", "email"], true);
})));
$audienceScope = trim((string)($_POST["audience_scope"] ?? "all"));
$area = trim((string)($_POST["area"] ?? ""));
$roleGroup = trim((string)($_POST["role_group"] ?? ""));
$submitAction = trim((string)($_POST["submit_action"] ?? "draft"));
$scheduleDate = trim((string)($_POST["schedule_date"] ?? ""));
$scheduleTime = trim((string)($_POST["schedule_time"] ?? ""));
$channelContext = strtolower(trim((string)($_POST["channel_context"] ?? "all")));
if (!in_array($channelContext, ["all", "website", "public", "public_news", "sms", "email"], true)) {
  $channelContext = "all";
}

$redirectBase = "../../Admin-End/Announcements/Announcements.php";
$redirectUrl = $channelContext === "all" ? $redirectBase : ($redirectBase . "?channel=" . urlencode($channelContext));

if ($title === "") {
  ann_redirect_with_flash($redirectUrl, "warning", "Announcement title is required.");
}

$plainContent = trim(strip_tags($contentHtml));
if ($plainContent === "") {
  ann_redirect_with_flash($redirectUrl, "warning", "Announcement content is required.");
}

if (!$channels) {
  ann_redirect_with_flash($redirectUrl, "warning", "Select at least one delivery channel.");
}

$sessionRole = strtolower(trim((string)($_SESSION["role"] ?? "")));
$isSuperAdmin = $sessionRole === "superadmin";
$status = "draft";
if ($submitAction === "pending" && !$isSuperAdmin) {
  $status = "pending";
}
if ($submitAction === "approved" && $isSuperAdmin) {
  $status = "approved";
}
$audience = "All Residents";
if ($audienceScope === "custom") {
  $parts = [];
  if ($area !== "") {
    $parts[] = $area;
  }
  if ($roleGroup !== "") {
    $parts[] = $roleGroup;
  }
  $audience = $parts ? implode(", ", $parts) : "Custom Audience";
}

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
  "id" => announcement_generate_id(),
  "title" => $title,
  "audience" => $audience,
  "channels" => $channels,
  "status" => $status,
  "publish_date" => $publishDate,
  "created_by" => $createdByDisplay,
  "created_by_user_id" => $createdByUserId,
  "created_by_role" => $createdByRole,
  "content_html" => $contentHtml,
  "created_at" => date("Y-m-d H:i:s")
];

$all = announcements_load_all();
array_unshift($all, $record);
if (!announcements_save_all($all)) {
  ann_redirect_with_flash($redirectUrl, "danger", "Unable to save announcement.");
}

$msg = "Announcement saved as draft.";
if ($status === "pending") {
  $msg = "Announcement submitted for review.";
}
if ($status === "approved") {
  $msg = "Announcement posted successfully.";
}
ann_redirect_with_flash($redirectUrl, "success", $msg);
