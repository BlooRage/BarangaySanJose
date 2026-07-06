<?php
require_once __DIR__ . '/../General/security.php';
require_once __DIR__ . '/../General/connection.php';

requireRoleSession(['Resident'], true);

function renderViewerError(string $title, string $message, int $status = 404): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="<?= htmlspecialchars(appUrl('Images/favicon_sanjose.png?v=20260211'), ENT_QUOTES, 'UTF-8') ?>">
  <title>{$safeTitle}</title>
  <style>
    :root {
      color-scheme: light;
      font-family: Arial, Helvetica, sans-serif;
    }
    body {
      margin: 0;
      min-height: 100vh;
      display: grid;
      place-items: center;
      background: #f8fafc;
      color: #1f2937;
    }
    .viewer-error {
      width: min(92vw, 680px);
      padding: 28px 24px;
      border: 1px solid #dbe4ee;
      border-radius: 18px;
      background: #ffffff;
      box-shadow: 0 18px 42px rgba(15, 23, 42, 0.08);
    }
    .viewer-error h1 {
      margin: 0 0 10px;
      font-size: 1.5rem;
      line-height: 1.15;
    }
    .viewer-error p {
      margin: 0;
      color: #667085;
      font-size: 0.98rem;
      line-height: 1.6;
    }
  </style>
</head>
<body>
  <section class="viewer-error">
    <h1>{$safeTitle}</h1>
    <p>{$safeMessage}</p>
  </section>
</body>
</html>
HTML;
    exit;
}

function resolveUnifiedAttachmentAbsolutePath(string $storedPath): ?string
{
    $storedPath = trim($storedPath);
    if ($storedPath === '') {
        return null;
    }

    $projectRoot = realpath(dirname(__DIR__, 2));
    if ($projectRoot === false) {
        return null;
    }

    $allowedBase = realpath($projectRoot . '/UnifiedFileAttachment');
    if ($allowedBase === false) {
        return null;
    }

    $normalized = str_replace("\\", "/", $storedPath);
    $normalized = preg_replace('#/+#', '/', $normalized);

    $candidate = $normalized;
    if (stripos($normalized, 'UnifiedFileAttachment/') === 0) {
        $candidate = $projectRoot . '/' . ltrim($normalized, '/');
    } else {
        $marker = '/UnifiedFileAttachment/';
        $pos = stripos($normalized, $marker);
        if ($pos !== false) {
            $candidate = $projectRoot . substr($normalized, $pos);
        }
    }

    $absolute = realpath($candidate);
    if ($absolute === false || !is_file($absolute)) {
        return null;
    }

    $absoluteNorm = str_replace("\\", "/", $absolute);
    $allowedNorm = rtrim(str_replace("\\", "/", $allowedBase), '/');
    if ($absoluteNorm !== $allowedNorm && strpos($absoluteNorm, $allowedNorm . '/') !== 0) {
        return null;
    }

    return $absolute;
}

function detectMimeType(string $absolutePath, string $fallbackName = ''): string
{
    $ext = strtolower(pathinfo($fallbackName ?: $absolutePath, PATHINFO_EXTENSION));
    $map = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    ];

    if (isset($map[$ext])) {
        return $map[$ext];
    }

    if (function_exists('mime_content_type')) {
        $detected = @mime_content_type($absolutePath);
        if (is_string($detected) && trim($detected) !== '') {
            return $detected;
        }
    }

    return 'application/octet-stream';
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    renderViewerError('File Unavailable', 'The document service is currently unavailable.', 500);
}

$attachmentId = (int)($_GET['attachment_id'] ?? 0);
if ($attachmentId <= 0) {
    renderViewerError('File Unavailable', 'No document was selected for preview.');
}

$userId = (string)($_SESSION['user_id'] ?? '');
if ($userId === '') {
    renderViewerError('Session Expired', 'Please sign in again and reopen the document.', 401);
}

$stmt = $conn->prepare("
    SELECT
        uf.file_name,
        uf.file_path,
        uf.file_type,
        dt.document_type_name,
        uf.id_number
    FROM unifiedfileattachmenttbl uf
    INNER JOIN documenttypelookuptbl dt
        ON uf.document_type_id = dt.document_type_id
    INNER JOIN statuslookuptbl s
        ON uf.status_id_verify = s.status_id
    WHERE uf.attachment_id = ?
      AND uf.user_id_uploaded_by = ?
      AND uf.deleted_at IS NULL
      AND s.status_name = 'Verified'
      AND s.status_type = 'ResidentDocumentProfiling'
    LIMIT 1
");

if (!$stmt) {
    renderViewerError('File Unavailable', 'Unable to prepare the document preview.', 500);
}

$stmt->bind_param('is', $attachmentId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    renderViewerError('File Not Found', 'This document is no longer available for preview.', 404);
}

$absolutePath = resolveUnifiedAttachmentAbsolutePath((string)($row['file_path'] ?? ''));
if ($absolutePath === null) {
    renderViewerError('File Not Found', 'The requested file could not be located.', 404);
}

$fileName = trim((string)($row['file_name'] ?? ''));
if ($fileName === '') {
    $fileName = basename($absolutePath);
}

$mimeType = detectMimeType($absolutePath, $fileName);
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string)filesize($absolutePath));
header('Content-Disposition: inline; filename="' . str_replace('"', '', basename($fileName)) . '"');
header('X-Content-Type-Options: nosniff');
readfile($absolutePath);
exit;
?>
