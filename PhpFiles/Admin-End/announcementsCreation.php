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

$title = trim((string)($_POST["title"] ?? ""));
$contentHtml = trim((string)($_POST["content_html"] ?? ""));
$channels = array_values(array_unique(array_filter((array)($_POST["channels"] ?? []), function ($ch) {
  return in_array((string)$ch, ["website", "sms", "email"], true);
})));
$audienceScope = trim((string)($_POST["audience_scope"] ?? "all"));
$area = trim((string)($_POST["area"] ?? ""));
$roleGroup = trim((string)($_POST["role_group"] ?? ""));
$submitAction = trim((string)($_POST["submit_action"] ?? "draft"));
$scheduleDate = trim((string)($_POST["schedule_date"] ?? ""));
$scheduleTime = trim((string)($_POST["schedule_time"] ?? ""));
$channelContext = strtolower(trim((string)($_POST["channel_context"] ?? "all")));
if (!in_array($channelContext, ["all", "website", "sms", "email"], true)) {
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

$status = $submitAction === "pending" ? "pending" : "draft";
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

$createdBy = trim((string)($_SESSION["user_id"] ?? ""));
if ($createdBy === "") {
  $createdBy = trim((string)($_SESSION["role"] ?? "Admin"));
}

$record = [
  "id" => announcement_generate_id(),
  "title" => $title,
  "audience" => $audience,
  "channels" => $channels,
  "status" => $status,
  "publish_date" => $publishDate,
  "created_by" => $createdBy,
  "content_html" => $contentHtml,
  "created_at" => date("Y-m-d H:i:s")
];

$all = announcements_load_all();
array_unshift($all, $record);
if (!announcements_save_all($all)) {
  ann_redirect_with_flash($redirectUrl, "danger", "Unable to save announcement.");
}

$msg = $status === "pending"
  ? "Announcement submitted for review."
  : "Announcement saved as draft.";
ann_redirect_with_flash($redirectUrl, "success", $msg);
