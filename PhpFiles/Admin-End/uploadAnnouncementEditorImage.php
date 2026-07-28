<?php
require_once __DIR__ . "/../../Admin-End/includes/admin_guard.php";
require_once __DIR__ . "/../General/uploadLimits.php";

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  http_response_code(405);
  echo json_encode(["success" => false, "message" => "Method not allowed."]);
  exit;
}

if (!isset($_FILES["image"])) {
  http_response_code(400);
  echo json_encode(["success" => false, "message" => "No image file uploaded."]);
  exit;
}

$file = $_FILES["image"];
$validationError = app_upload_validate_file($file, 'admin', 'Image', true);
if ($validationError !== null) {
  http_response_code(400);
  echo json_encode(["success" => false, "message" => $validationError]);
  exit;
}

$tmpPath = (string)($file["tmp_name"] ?? "");
if ($tmpPath === "" || !is_uploaded_file($tmpPath)) {
  http_response_code(400);
  echo json_encode(["success" => false, "message" => "Invalid upload source."]);
  exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string)$finfo->file($tmpPath);
$allowedMimes = [
  "image/jpeg" => "jpg",
  "image/png" => "png",
  "image/webp" => "webp",
  "image/gif" => "gif"
];

if (!isset($allowedMimes[$mime])) {
  http_response_code(400);
  echo json_encode(["success" => false, "message" => "Unsupported image type."]);
  exit;
}

$ext = $allowedMimes[$mime];
$filename = "announcement_" . date("Ymd_His") . "_" . bin2hex(random_bytes(6)) . "." . $ext;

$uploadDir = __DIR__ . "/../../UnifiedFileAttachment/Content/Announcements/EditorUploads";
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
  http_response_code(500);
  echo json_encode(["success" => false, "message" => "Unable to create upload directory."]);
  exit;
}

$targetPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
if (!move_uploaded_file($tmpPath, $targetPath)) {
  http_response_code(500);
  echo json_encode(["success" => false, "message" => "Unable to save image."]);
  exit;
}

$scriptName = str_replace("\\", "/", (string)($_SERVER["SCRIPT_NAME"] ?? ""));
$phpFilesPos = strpos($scriptName, "/PhpFiles/");
$baseUrl = "";
if ($phpFilesPos !== false) {
  $baseUrl = substr($scriptName, 0, $phpFilesPos);
}

$publicUrl = rtrim($baseUrl, "/") . "/UnifiedFileAttachment/Content/Announcements/EditorUploads/" . rawurlencode($filename);
echo json_encode(["success" => true, "url" => $publicUrl]);
