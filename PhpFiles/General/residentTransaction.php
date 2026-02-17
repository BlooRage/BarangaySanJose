<?php
declare(strict_types=1);

require_once __DIR__ . '/uniqueIDGenerate.php';

/**
 * Best-effort resident transaction logging.
 * Must not break the main workflow if table/schema is missing.
 */

function residentTxClamp(?string $value, int $maxLen = 2000): ?string {
    if ($value === null) return null;
    $s = trim((string)$value);
    if ($s === '') return null;
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($s, 'UTF-8') > $maxLen) {
            return mb_substr($s, 0, $maxLen, 'UTF-8');
        }
        return $s;
    }
    return strlen($s) > $maxLen ? substr($s, 0, $maxLen) : $s;
}

function residentTxEncodeMeta(?array $metadata): ?string {
    if (!$metadata) return null;
    $json = json_encode($metadata, JSON_UNESCAPED_SLASHES);
    if ($json === false) return null;
    return residentTxClamp($json, 10000);
}

function mapEditRequestTransactionType(string $requestType): string {
    $requestType = strtolower(trim($requestType));
    if ($requestType === 'address') return 'EDIT_REQUEST_ADDRESS';
    if ($requestType === 'emergency') return 'EDIT_REQUEST_EMERGENCY';
    return 'EDIT_REQUEST_PROFILE';
}

function mapEditRequestTitle(string $requestType): string {
    $requestType = strtolower(trim($requestType));
    if ($requestType === 'address') return 'Address Edit Request';
    if ($requestType === 'emergency') return 'Emergency Contact Edit Request';
    return 'Profile Edit Request';
}

function mapEditRequestDescription(string $requestType): string {
    $requestType = strtolower(trim($requestType));
    if ($requestType === 'address') return 'Resident submitted an address update request.';
    if ($requestType === 'emergency') return 'Resident submitted an emergency contact update request.';
    return 'Resident submitted a profile information update request.';
}

function upsertResidentTransaction(
    mysqli $conn,
    string $residentUserId,
    string $userId,
    string $sourceType,
    string $sourceId,
    string $transactionType,
    string $title,
    int $statusId,
    ?string $description = null,
    ?array $metadata = null,
    ?string $reviewedBy = null,
    ?string $reviewedAt = null,
    ?string $releasedBy = null,
    ?string $releasedAt = null
): void {
    $transactionId = GenerateTransactionID($conn, 'unifiedtransactiontbl', 'transaction_id');

    $stmt = $conn->prepare("
        INSERT INTO unifiedtransactiontbl
            (transaction_id, user_id, resident_user_id, source_type, source_id, transaction_type, title, details, metadata_json, status_id, reviewed_by, reviewed_at, released_by, released_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            details = VALUES(details),
            metadata_json = VALUES(metadata_json),
            status_id = VALUES(status_id),
            reviewed_by = VALUES(reviewed_by),
            reviewed_at = VALUES(reviewed_at),
            released_by = VALUES(released_by),
            released_at = VALUES(released_at),
            updated_at = CURRENT_TIMESTAMP
    ");
    if (!$stmt) return;

    $transactionId = (string)residentTxClamp($transactionId, 10);
    $userId = (string)residentTxClamp($userId, 12);
    $residentUserId = (string)residentTxClamp($residentUserId, 12);
    $sourceType = (string)residentTxClamp($sourceType, 50);
    $sourceId = (string)residentTxClamp($sourceId, 64);
    $transactionType = (string)residentTxClamp($transactionType, 50);
    $title = (string)residentTxClamp($title, 160);
    $description = residentTxClamp($description, 4000);
    $metadataJson = residentTxEncodeMeta($metadata);
    $statusIdStr = (string)((int)$statusId);
    $reviewedBy = residentTxClamp($reviewedBy, 12);
    $reviewedAt = residentTxClamp($reviewedAt, 32);
    $releasedBy = residentTxClamp($releasedBy, 12);
    $releasedAt = residentTxClamp($releasedAt, 32);

    $stmt->bind_param(
        "ssssssssssssss",
        $transactionId,
        $userId,
        $residentUserId,
        $sourceType,
        $sourceId,
        $transactionType,
        $title,
        $description,
        $metadataJson,
        $statusIdStr,
        $reviewedBy,
        $reviewedAt,
        $releasedBy,
        $releasedAt
    );
    $stmt->execute();
    $stmt->close();
}

function createResidentTransaction(
    mysqli $conn,
    string $residentUserId,
    string $userId,
    string $sourceType,
    string $sourceId,
    string $transactionType,
    string $title,
    int $statusId,
    ?string $description = null,
    ?array $metadata = null
): void {
    upsertResidentTransaction(
        $conn,
        $residentUserId,
        $userId,
        $sourceType,
        $sourceId,
        $transactionType,
        $title,
        $statusId,
        $description,
        $metadata,
        null,
        null,
        null,
        null
    );
}

function updateResidentTransactionStatus(
    mysqli $conn,
    string $sourceType,
    string $sourceId,
    string $transactionType,
    int $statusId,
    ?string $reviewedBy = null,
    ?string $releasedBy = null
): void {
    $stmt = $conn->prepare("
        UPDATE unifiedtransactiontbl
        SET
            status_id = ?,
            reviewed_by = COALESCE(?, reviewed_by),
            reviewed_at = CASE WHEN ? IS NULL THEN reviewed_at ELSE NOW() END,
            released_by = COALESCE(?, released_by),
            released_at = CASE WHEN ? IS NULL THEN released_at ELSE NOW() END,
            updated_at = CURRENT_TIMESTAMP
        WHERE source_type = ? AND source_id = ? AND transaction_type = ?
        LIMIT 1
    ");
    if (!$stmt) return;

    $statusIdStr = (string)((int)$statusId);
    $reviewedBy = residentTxClamp($reviewedBy, 12);
    $releasedBy = residentTxClamp($releasedBy, 12);
    $sourceType = (string)residentTxClamp($sourceType, 50);
    $sourceId = (string)residentTxClamp($sourceId, 64);
    $transactionType = (string)residentTxClamp($transactionType, 50);

    $stmt->bind_param(
        "ssssssss",
        $statusIdStr,
        $reviewedBy,
        $reviewedBy,
        $releasedBy,
        $releasedBy,
        $sourceType,
        $sourceId,
        $transactionType
    );
    $stmt->execute();
    $stmt->close();
}

