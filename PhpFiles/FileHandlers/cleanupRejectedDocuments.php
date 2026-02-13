<?php
declare(strict_types=1);

// Auto-delete script for rejected resident profiling documents.
// Intended to run via cron (CLI only).
// Retention: 30 days (based on upload_timestamp).

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden\n";
    exit(1);
}

require_once __DIR__ . "/../General/connection.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

$projectRoot = realpath(__DIR__ . "/../..");
$allowedBase = $projectRoot ? realpath($projectRoot . "/UnifiedFileAttachment") : false;

if (!$projectRoot || !$allowedBase) {
    fwrite(STDERR, "Failed to resolve project or attachment base path.\n");
    exit(1);
}

function startsWith(string $haystack, string $needle): bool {
    return strncmp($haystack, $needle, strlen($needle)) === 0;
}

function resolveFilePath(string $rawPath, string $projectRoot): string {
    $rawPath = trim($rawPath);
    if ($rawPath === '') {
        return '';
    }

    $normalized = str_replace("\\", "/", $rawPath);
    if (strpos($normalized, '/BarangaySanJose/') === 0) {
        $rel = substr($normalized, strlen('/BarangaySanJose/'));
        return rtrim($projectRoot, "/") . "/" . ltrim($rel, "/");
    }

    // Absolute path (Unix or Windows drive).
    $isAbsolute = startsWith($rawPath, "/") || (bool)preg_match('/^[A-Za-z]:[\\\\\\/]/', $rawPath);
    if ($isAbsolute) {
        return $rawPath;
    }

    return rtrim($projectRoot, "/") . "/" . ltrim($rawPath, "/");
}

$retentionDays = 30;

$sql = "
    SELECT
        uf.attachment_id,
        uf.file_path,
        uf.upload_timestamp
    FROM unifiedfileattachmenttbl uf
    INNER JOIN statuslookuptbl s
        ON uf.status_id_verify = s.status_id
    WHERE s.status_name = 'Rejected'
      AND s.status_type = 'ResidentDocumentProfiling'
      AND uf.upload_timestamp < (NOW() - INTERVAL ? DAY)
    ORDER BY uf.attachment_id ASC
";

$selectStmt = $conn->prepare($sql);
$selectStmt->bind_param("i", $retentionDays);
$selectStmt->execute();
$result = $selectStmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);
$selectStmt->close();

$deletedFiles = 0;
$missingFiles = 0;
$deletedRows = 0;
$skippedUnsafe = 0;
$errors = 0;

$deleteStmt = $conn->prepare("DELETE FROM unifiedfileattachmenttbl WHERE attachment_id = ? LIMIT 1");

foreach ($rows as $row) {
    $attachmentId = (int)($row['attachment_id'] ?? 0);
    $storedPath = (string)($row['file_path'] ?? '');
    if ($attachmentId <= 0) {
        continue;
    }

    $resolvedPath = resolveFilePath($storedPath, $projectRoot);
    $real = $resolvedPath !== '' ? realpath($resolvedPath) : false;

    try {
        // Delete physical file when it exists and is within allowed attachments base.
        if ($real !== false) {
            if (!startsWith($real, $allowedBase)) {
                $skippedUnsafe++;
                fwrite(STDERR, "Skipped unsafe path for attachment {$attachmentId}: {$real}\n");
                continue;
            }

            if (!@unlink($real)) {
                throw new RuntimeException("Failed to delete file: {$real}");
            }
            $deletedFiles++;
        } else {
            // File already missing; still clean the DB row.
            $missingFiles++;
        }

        $deleteStmt->bind_param("i", $attachmentId);
        $deleteStmt->execute();
        if ($deleteStmt->affected_rows > 0) {
            $deletedRows++;
        }
    } catch (Throwable $e) {
        $errors++;
        fwrite(STDERR, "Error on attachment {$attachmentId}: " . $e->getMessage() . "\n");
    }
}

$deleteStmt->close();

echo "Cleanup complete\n";
echo "Retention policy: {$retentionDays} days\n";
echo "Rejected rows scanned: " . count($rows) . "\n";
echo "Files deleted: {$deletedFiles}\n";
echo "Files missing: {$missingFiles}\n";
echo "Rows deleted: {$deletedRows}\n";
echo "Unsafe skipped: {$skippedUnsafe}\n";
echo "Errors: {$errors}\n";

exit($errors > 0 ? 2 : 0);
