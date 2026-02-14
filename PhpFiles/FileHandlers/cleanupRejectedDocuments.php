<?php
declare(strict_types=1);

// Auto-delete script for rejected documents (unifiedfileattachmenttbl).
// Intended to run via cron (CLI only).
// Retention: 60 days (based on rejected_at when available, otherwise upload_timestamp).

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

$retentionDays = 60;

function parseRejectedAtFromRemarks(string $remarks): ?DateTimeImmutable {
    $parts = array_values(array_filter(array_map('trim', explode(';', $remarks))));
    foreach ($parts as $p) {
        if (stripos($p, 'rejected_at=') === 0) {
            $raw = trim(substr($p, strlen('rejected_at=')));
            if ($raw === '') return null;
            try {
                return new DateTimeImmutable($raw);
            } catch (Throwable $e) {
                return null;
            }
        }
    }
    return null;
}

$sql = "
    SELECT
        uf.attachment_id,
        uf.file_path,
        uf.upload_timestamp,
        uf.remarks,
        s.status_name,
        s.status_type
    FROM unifiedfileattachmenttbl uf
    INNER JOIN statuslookuptbl s
        ON uf.status_id_verify = s.status_id
    WHERE (s.status_name = 'Rejected' OR s.status_name = 'Denied')
    ORDER BY uf.attachment_id ASC
";

$selectStmt = $conn->prepare($sql);
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
$nullSectorLatestStmt = $conn->prepare("UPDATE residentsectormembershiptbl SET latest_attachment_id = NULL WHERE latest_attachment_id = ?");

$cutoff = new DateTimeImmutable("-{$retentionDays} days");

foreach ($rows as $row) {
    $attachmentId = (int)($row['attachment_id'] ?? 0);
    $storedPath = (string)($row['file_path'] ?? '');
    $remarks = (string)($row['remarks'] ?? '');
    $uploadTsRaw = (string)($row['upload_timestamp'] ?? '');
    if ($attachmentId <= 0) {
        continue;
    }

    $rejectedAt = parseRejectedAtFromRemarks($remarks);
    $basis = $rejectedAt;
    if ($basis === null) {
        try {
            $basis = $uploadTsRaw !== '' ? new DateTimeImmutable($uploadTsRaw) : null;
        } catch (Throwable $e) {
            $basis = null;
        }
    }
    if ($basis === null || $basis >= $cutoff) {
        continue; // not yet eligible for cleanup
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

        // Clear any FK-style pointers before deleting attachment rows (best-effort).
        if ($nullSectorLatestStmt) {
            $nullSectorLatestStmt->bind_param("i", $attachmentId);
            $nullSectorLatestStmt->execute();
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
if ($nullSectorLatestStmt) {
    $nullSectorLatestStmt->close();
}

echo "Cleanup complete\n";
echo "Retention policy: {$retentionDays} days\n";
echo "Rejected rows scanned: " . count($rows) . "\n";
echo "Files deleted: {$deletedFiles}\n";
echo "Files missing: {$missingFiles}\n";
echo "Rows deleted: {$deletedRows}\n";
echo "Unsafe skipped: {$skippedUnsafe}\n";
echo "Errors: {$errors}\n";

exit($errors > 0 ? 2 : 0);
