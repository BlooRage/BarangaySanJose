<?php
declare(strict_types=1);

// Delete unified attachments by owner identifier (resident_id, official_id, or user_id).
// CLI usage:
//   php deleteUnifiedAttachments.php --resident_id=2602000001
//   php deleteUnifiedAttachments.php --official_id=O000000001
//   php deleteUnifiedAttachments.php --user_id=USR000000001

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden\n";
    exit(1);
}

require_once __DIR__ . "/../General/connection.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

$options = getopt("", ["resident_id::", "official_id::", "user_id::", "dry_run::"]);
$residentId = trim((string)($options['resident_id'] ?? ''));
$officialId = trim((string)($options['official_id'] ?? ''));
$userId = trim((string)($options['user_id'] ?? ''));
$dryRun = isset($options['dry_run']);

$provided = array_filter([$residentId, $officialId, $userId], static fn($v) => $v !== '');
if (count($provided) !== 1) {
    fwrite(STDERR, "Provide exactly one identifier: --resident_id, --official_id, or --user_id\n");
    exit(1);
}

$projectRoot = realpath(__DIR__ . "/../..");
$allowedBase = $projectRoot ? realpath($projectRoot . "/UnifiedFileAttachment") : false;
if (!$projectRoot || !$allowedBase) {
    fwrite(STDERR, "Failed to resolve project root or attachment directory.\n");
    exit(1);
}

function startsWith(string $haystack, string $needle): bool {
    return strncmp($haystack, $needle, strlen($needle)) === 0;
}

function resolveFilePath(string $rawPath, string $projectRoot): string {
    $rawPath = trim($rawPath);
    if ($rawPath === '') return '';

    $normalized = str_replace("\\", "/", $rawPath);
    if (strpos($normalized, '/BarangaySanJose/') === 0) {
        $rel = substr($normalized, strlen('/BarangaySanJose/'));
        return rtrim($projectRoot, "/") . "/" . ltrim($rel, "/");
    }
    if (strpos($normalized, '/UnifiedFileAttachment/') === 0) {
        $rel = substr($normalized, strlen('/UnifiedFileAttachment/'));
        return rtrim($projectRoot, "/") . "/UnifiedFileAttachment/" . ltrim($rel, "/");
    }

    $isAbsolute = startsWith($rawPath, "/") || (bool)preg_match('/^[A-Za-z]:[\\\\\\/]/', $rawPath);
    if ($isAbsolute) return $rawPath;

    return rtrim($projectRoot, "/") . "/" . ltrim($rawPath, "/");
}

$whereSql = "";
$bindTypes = "";
$bindValue = "";
$scopeLabel = "";

if ($residentId !== '') {
    $whereSql = "uf.source_type = 'ResidentProfiling' AND uf.source_id = ?";
    $bindTypes = "s";
    $bindValue = $residentId;
    $scopeLabel = "resident_id={$residentId}";
} elseif ($officialId !== '') {
    // Supports current official source type and legacy employee source type.
    $whereSql = "(uf.source_type = 'OfficialProfiling' OR uf.source_type = 'EmployeeProfiling') AND uf.source_id = ?";
    $bindTypes = "s";
    $bindValue = $officialId;
    $scopeLabel = "official_id={$officialId}";
} else {
    // Delete attachments uploaded by this user, regardless of source_type.
    $whereSql = "uf.user_id_uploaded_by = ?";
    $bindTypes = "s";
    $bindValue = $userId;
    $scopeLabel = "user_id={$userId}";
}

$selectSql = "
    SELECT
        uf.attachment_id,
        uf.file_path
    FROM unifiedfileattachmenttbl uf
    WHERE {$whereSql}
    ORDER BY uf.attachment_id ASC
";

$selectStmt = $conn->prepare($selectSql);
$selectStmt->bind_param($bindTypes, $bindValue);
$selectStmt->execute();
$rows = $selectStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$selectStmt->close();

$deletedFiles = 0;
$deletedRows = 0;
$missingFiles = 0;
$skippedUnsafe = 0;
$errors = 0;

$deleteStmt = $conn->prepare("DELETE FROM unifiedfileattachmenttbl WHERE attachment_id = ? LIMIT 1");

foreach ($rows as $row) {
    $attachmentId = (int)($row['attachment_id'] ?? 0);
    if ($attachmentId <= 0) continue;

    $storedPath = (string)($row['file_path'] ?? '');
    $resolvedPath = resolveFilePath($storedPath, $projectRoot);
    $real = $resolvedPath !== '' ? realpath($resolvedPath) : false;

    try {
        if ($real !== false) {
            if (!startsWith($real, $allowedBase)) {
                $skippedUnsafe++;
                fwrite(STDERR, "Skipped unsafe path for attachment {$attachmentId}: {$real}\n");
                continue;
            }

            if (!$dryRun) {
                if (!@unlink($real)) {
                    throw new RuntimeException("Failed to delete file: {$real}");
                }
            }
            $deletedFiles++;
        } else {
            $missingFiles++;
        }

        if (!$dryRun) {
            $deleteStmt->bind_param("i", $attachmentId);
            $deleteStmt->execute();
            if ($deleteStmt->affected_rows > 0) {
                $deletedRows++;
            }
        } else {
            $deletedRows++;
        }
    } catch (Throwable $e) {
        $errors++;
        fwrite(STDERR, "Error on attachment {$attachmentId}: " . $e->getMessage() . "\n");
    }
}

$deleteStmt->close();

echo "Unified attachment cleanup by scope complete\n";
echo "Scope: {$scopeLabel}\n";
echo "Mode: " . ($dryRun ? "DRY RUN" : "DELETE") . "\n";
echo "Rows matched: " . count($rows) . "\n";
echo "Files deleted: {$deletedFiles}\n";
echo "Files missing: {$missingFiles}\n";
echo "Rows deleted: {$deletedRows}\n";
echo "Unsafe skipped: {$skippedUnsafe}\n";
echo "Errors: {$errors}\n";

exit($errors > 0 ? 2 : 0);
