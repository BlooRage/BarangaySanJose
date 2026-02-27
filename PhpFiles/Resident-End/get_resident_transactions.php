<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../General/connection.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
    exit;
}

$userId = (string)$_SESSION['user_id'];
$limit = (int)($_GET['limit'] ?? 50);
if ($limit < 1) $limit = 50;
if ($limit > 200) $limit = 200;

$offset = (int)($_GET['offset'] ?? 0);
if ($offset < 0) $offset = 0;

$type = trim((string)($_GET['type'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

function txnExtractAttachmentIds(?array $meta): array {
    if (!$meta || !isset($meta['attachment_ids']) || !is_array($meta['attachment_ids'])) {
        return [];
    }
    $ids = [];
    foreach ($meta['attachment_ids'] as $v) {
        $id = (int)$v;
        if ($id > 0) $ids[] = $id;
    }
    return array_values(array_unique($ids));
}

function txnDocPurposeFromType(string $txnType): string {
    $k = strtoupper(trim($txnType));
    if ($k === 'PROOF_OF_RESIDENCY') return 'proof';
    if ($k === 'SECTOR_MEMBERSHIP' || $k === 'SECTOR_MEMBERSHIP_VERIFICATION') return 'sector';
    return 'all';
}

function txnAggregateDocStatus(
    mysqli $conn,
    string $residentId,
    string $purpose,
    array $attachmentIds = [],
    ?string $cutoffAt = null
): ?string {
    if ($residentId === '') return null;

    $sql = "
        SELECT COALESCE(s.status_name, 'PendingReview') AS status_name
        FROM unifiedfileattachmenttbl uf
        INNER JOIN documenttypelookuptbl dt ON dt.document_type_id = uf.document_type_id
        LEFT JOIN statuslookuptbl s ON s.status_id = uf.status_id_verify
        WHERE uf.source_type = 'ResidentProfiling'
          AND uf.source_id = ?
          AND dt.document_category = 'ResidentProfiling'
    ";
    $types = "s";
    $params = [$residentId];

    if ($purpose === 'proof') {
        $sql .= " AND (uf.remarks IS NULL OR uf.remarks NOT LIKE 'sector:%') ";
    } elseif ($purpose === 'sector') {
        $sql .= " AND uf.remarks LIKE 'sector:%' ";
    }

    if (!empty($attachmentIds)) {
        $sql .= " AND uf.attachment_id IN (" . implode(',', array_fill(0, count($attachmentIds), '?')) . ") ";
        $types .= str_repeat('i', count($attachmentIds));
        foreach ($attachmentIds as $id) {
            $params[] = (int)$id;
        }
    }

    $cutoffAt = trim((string)$cutoffAt);
    if ($cutoffAt !== '') {
        $sql .= " AND COALESCE(uf.updated_at, uf.upload_timestamp) <= ? ";
        $types .= "s";
        $params[] = $cutoffAt;
    }

    $sql .= " ORDER BY COALESCE(uf.updated_at, uf.upload_timestamp) DESC, uf.attachment_id DESC ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) return null;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $hasAny = false;
    $hasPending = false;
    $hasRejected = false;
    $hasVerified = false;

    while ($row = $res->fetch_assoc()) {
        $hasAny = true;
        $k = strtolower(trim((string)($row['status_name'] ?? '')));
        if ($k === '') continue;
        if (strpos($k, 'pending') !== false || strpos($k, 'review') !== false || $k === 'notverified') {
            $hasPending = true;
            continue;
        }
        if (strpos($k, 'rejected') !== false || strpos($k, 'denied') !== false) {
            $hasRejected = true;
            continue;
        }
        if (strpos($k, 'verified') !== false || strpos($k, 'approved') !== false) {
            $hasVerified = true;
            continue;
        }
    }
    $stmt->close();

    if (!$hasAny) return null;
    // If any relevant file is rejected/denied, reflect that immediately.
    if ($hasRejected) return 'Rejected';
    if ($hasPending) return 'PendingReview';
    if ($hasVerified) return 'Verified';
    return 'PendingReview';
}

function txnNormalizeResidentStatusToParent(string $residentStatusName): ?string {
    $key = strtolower(trim($residentStatusName));
    $key = preg_replace('/[\s_-]+/', '', $key);
    if ($key === 'verifiedresident' || $key === 'verified' || $key === 'approved') {
        return 'Verified';
    }
    if ($key === 'notverified' || $key === 'rejected' || $key === 'denied') {
        return 'Rejected';
    }
    if ($key === 'pendingverification' || $key === 'pendingreview') {
        return 'PendingReview';
    }
    return null;
}

function txnIsDeniedStatus(string $statusName): bool {
    $k = strtolower(trim($statusName));
    if ($k === '') return false;
    return (strpos($k, 'rejected') !== false || strpos($k, 'denied') !== false || $k === 'notverified');
}

function txnParseRejectReasonFromRemarks(string $remarks): string {
    $raw = trim($remarks);
    if ($raw === '') return '';
    if (preg_match('/(?:^|;)\s*reason\s*=\s*(.+)$/i', $raw, $m)) {
        return trim((string)($m[1] ?? ''));
    }
    return '';
}

function txnFindLatestRejectedReason(
    mysqli $conn,
    string $residentId,
    string $purpose,
    array $attachmentIds = [],
    ?string $cutoffAt = null
): string {
    if ($residentId === '') return '';

    $sql = "
        SELECT
            COALESCE(s.status_name, 'PendingReview') AS status_name,
            COALESCE(uf.remarks, '') AS remarks
        FROM unifiedfileattachmenttbl uf
        INNER JOIN documenttypelookuptbl dt ON dt.document_type_id = uf.document_type_id
        LEFT JOIN statuslookuptbl s ON s.status_id = uf.status_id_verify
        WHERE uf.source_type = 'ResidentProfiling'
          AND uf.source_id = ?
          AND dt.document_category = 'ResidentProfiling'
    ";
    $types = "s";
    $params = [$residentId];

    if ($purpose === 'proof') {
        $sql .= " AND (uf.remarks IS NULL OR uf.remarks NOT LIKE 'sector:%') ";
    } elseif ($purpose === 'sector') {
        $sql .= " AND uf.remarks LIKE 'sector:%' ";
    }

    if (!empty($attachmentIds)) {
        $sql .= " AND uf.attachment_id IN (" . implode(',', array_fill(0, count($attachmentIds), '?')) . ") ";
        $types .= str_repeat('i', count($attachmentIds));
        foreach ($attachmentIds as $id) {
            $params[] = (int)$id;
        }
    }

    $cutoffAt = trim((string)$cutoffAt);
    if ($cutoffAt !== '') {
        $sql .= " AND COALESCE(uf.updated_at, uf.upload_timestamp) <= ? ";
        $types .= "s";
        $params[] = $cutoffAt;
    }

    $sql .= " ORDER BY COALESCE(uf.updated_at, uf.upload_timestamp) DESC, uf.attachment_id DESC ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) return '';
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $statusKey = strtolower(trim((string)($row['status_name'] ?? '')));
        if (strpos($statusKey, 'rejected') === false && strpos($statusKey, 'denied') === false) {
            continue;
        }
        $reason = txnParseRejectReasonFromRemarks((string)($row['remarks'] ?? ''));
        if ($reason !== '') {
            $stmt->close();
            return $reason;
        }
    }

    $stmt->close();
    return '';
}

function txnSectorKeyFromMeta(?array $meta): string {
    if (!$meta || !is_array($meta)) return '';
    $raw = trim((string)($meta['sector_key'] ?? ''));
    if ($raw === '' && isset($meta['sectors']) && is_array($meta['sectors']) && !empty($meta['sectors'][0])) {
        $raw = trim((string)$meta['sectors'][0]);
    }
    if ($raw === '') return '';

    $normalized = strtolower(preg_replace('/[^a-z]/', '', $raw));
    $map = [
        'pwd' => 'PWD',
        'singleparent' => 'SingleParent',
        'student' => 'Student',
        'seniorcitizen' => 'SeniorCitizen',
        'indigenouspeople' => 'IndigenousPeople',
    ];
    return $map[$normalized] ?? $raw;
}

function txnResubmitScopeKey(array $row): string {
    $txnType = strtoupper(trim((string)($row['transaction_type'] ?? '')));
    if (in_array($txnType, ['PROOF_OF_RESIDENCY', 'RESIDENT_PROFILING'], true)) {
        return 'profiling';
    }
    if (in_array($txnType, ['SECTOR_MEMBERSHIP', 'SECTOR_MEMBERSHIP_VERIFICATION'], true)) {
        $sectorKey = txnSectorKeyFromMeta(is_array($row['metadata'] ?? null) ? $row['metadata'] : null);
        return $sectorKey !== '' ? ('sector:' . $sectorKey) : 'sector:*';
    }
    return '';
}

function txnSuppressStaleResubmitUrls(array $items): array {
    if (empty($items)) return $items;

    $latestTxByScope = [];
    foreach ($items as $row) {
        $scope = txnResubmitScopeKey($row);
        if ($scope === '') continue;
        $txid = (string)($row['transaction_id'] ?? '');
        if (!isset($latestTxByScope[$scope])) {
            $latestTxByScope[$scope] = $txid;
        }
    }

    for ($i = 0; $i < count($items); $i++) {
        $meta = is_array($items[$i]['metadata'] ?? null) ? $items[$i]['metadata'] : [];
        $hasResubmit = isset($meta['resubmit_url']) && trim((string)$meta['resubmit_url']) !== '';
        if (!$hasResubmit) continue;
        if (!txnIsDeniedStatus((string)($items[$i]['status_name'] ?? ''))) continue;

        $scope = txnResubmitScopeKey($items[$i]);
        if ($scope === '') continue;
        $currentTxId = (string)($items[$i]['transaction_id'] ?? '');
        $latestScopeTxId = (string)($latestTxByScope[$scope] ?? '');

        // Keep resubmit only for the latest transaction in the same scope.
        if ($latestScopeTxId !== '' && $latestScopeTxId !== $currentTxId) {
            unset($meta['resubmit_url']);
            $items[$i]['metadata'] = $meta;
        }
    }

    return $items;
}

// Prefer residenttransactiontbl (new generalized ledger).
$sql = "
    SELECT
        t.transaction_id,
        COALESCE(t.resident_user_id, t.user_id) AS resident_user_id,
        t.source_type,
        t.source_id,
        t.transaction_type,
        t.title,
        t.details AS description,
        COALESCE(s.status_name, CONCAT('Status #', t.status_id)) AS status_name,
        t.status_id,
        t.metadata_json,
        t.created_at,
        t.updated_at,
        t.reviewed_at
    FROM residenttransactiontbl t
    LEFT JOIN statuslookuptbl s ON s.status_id = t.status_id
    WHERE (t.resident_user_id = ? OR t.user_id = ?)
";
$types = "ss";
$params = [$userId, $userId];

if ($type !== '') {
    $sql .= " AND t.transaction_type = ? ";
    $types .= "s";
    $params[] = $type;
}
if ($status !== '') {
    $sql .= " AND COALESCE(s.status_name, '') = ? ";
    $types .= "s";
    $params[] = $status;
}

$sql .= " ORDER BY COALESCE(t.updated_at, t.reviewed_at, t.created_at) DESC, t.transaction_id DESC LIMIT ? OFFSET ? ";
$types .= "ii";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load transactions. Ensure residenttransactiontbl exists and uses the expected columns.']);
    exit;
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $meta = null;
    if (!empty($row['metadata_json'])) {
        $decoded = json_decode((string)$row['metadata_json'], true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }

    $items[] = [
        'transaction_id' => (string)$row['transaction_id'],
        'resident_user_id' => (string)$row['resident_user_id'],
        'source_type' => (string)$row['source_type'],
        'source_id' => (string)$row['source_id'],
        'transaction_type' => (string)$row['transaction_type'],
        'title' => (string)$row['title'],
        'description' => (string)($row['description'] ?? ''),
        'status_name' => (string)$row['status_name'],
        'status_id' => isset($row['status_id']) ? (int)$row['status_id'] : null,
        'metadata' => $meta,
        'created_at' => (string)$row['created_at'],
        'updated_at' => (string)$row['updated_at'],
        'reviewed_at' => $row['reviewed_at'] !== null ? (string)$row['reviewed_at'] : null,
        'has_documents' => false,
    ];
}
$stmt->close();

// Resolve resident_id once for document-context flags.
$residentId = '';
$residentStatusName = '';
$residentStmt = $conn->prepare("SELECT resident_id FROM residentinformationtbl WHERE user_id = ? LIMIT 1");
if ($residentStmt) {
    $residentStmt->bind_param("s", $userId);
    $residentStmt->execute();
    $residentStmt->bind_result($residentId);
    $residentStmt->fetch();
    $residentStmt->close();
}

if ($residentId !== '') {
    $residentStatusStmt = $conn->prepare("
        SELECT COALESCE(s.status_name, '') AS status_name
        FROM residentinformationtbl r
        LEFT JOIN statuslookuptbl s ON s.status_id = r.status_id_resident
        WHERE r.resident_id = ?
        LIMIT 1
    ");
    if ($residentStatusStmt) {
        $residentStatusStmt->bind_param("s", $residentId);
        $residentStatusStmt->execute();
        $residentStatusStmt->bind_result($residentStatusName);
        $residentStatusStmt->fetch();
        $residentStatusStmt->close();
    }
}

// Mark regular transactions that can expose resident profiling document statuses.
for ($i = 0; $i < count($items); $i++) {
    $rowSourceId = (string)($items[$i]['source_id'] ?? '');
    $rowTxnType = strtoupper((string)($items[$i]['transaction_type'] ?? ''));
    $isProfilingTxn = in_array($rowTxnType, [
        'RESIDENT_PROFILING',
        'PROOF_OF_RESIDENCY',
        'SECTOR_MEMBERSHIP',
        'SECTOR_MEMBERSHIP_VERIFICATION',
    ], true);
    if (!$isProfilingTxn || $rowSourceId === '') {
        continue;
    }
    if ($residentId !== '' && $rowSourceId !== $residentId) {
        continue;
    }
    $items[$i]['has_documents'] = true;

    if ($rowTxnType === 'RESIDENT_PROFILING') {
        $derivedParentStatus = txnNormalizeResidentStatusToParent((string)$residentStatusName);
        if ($derivedParentStatus !== null) {
            $items[$i]['status_name'] = $derivedParentStatus;
        }
        if (txnIsDeniedStatus((string)($items[$i]['status_name'] ?? ''))) {
            $metaCurrent = is_array($items[$i]['metadata'] ?? null) ? $items[$i]['metadata'] : [];
            if (!isset($metaCurrent['resubmit_url']) || trim((string)$metaCurrent['resubmit_url']) === '') {
                $metaCurrent['resubmit_url'] = 'DocumentUpload.php?mode=profiling';
            }
            $items[$i]['metadata'] = $metaCurrent;
        }
        continue;
    }

    if ($rowTxnType === 'PROOF_OF_RESIDENCY') {
        $attachmentIds = txnExtractAttachmentIds(is_array($items[$i]['metadata'] ?? null) ? $items[$i]['metadata'] : null);
        $cutoffAt = (string)($items[$i]['reviewed_at'] ?? '');
        if ($cutoffAt === '') {
            $cutoffAt = (string)($items[$i]['updated_at'] ?? '');
        }
        if ($cutoffAt === '') {
            $cutoffAt = (string)($items[$i]['created_at'] ?? '');
        }

        $docStatus = txnAggregateDocStatus($conn, $residentId, 'proof', $attachmentIds, $cutoffAt);
        if ($docStatus !== null) {
            $items[$i]['status_name'] = $docStatus;
        }

        if (txnIsDeniedStatus((string)($items[$i]['status_name'] ?? ''))) {
            $derivedReason = txnFindLatestRejectedReason($conn, $residentId, 'proof', $attachmentIds, $cutoffAt);
            $metaCurrent = is_array($items[$i]['metadata'] ?? null) ? $items[$i]['metadata'] : [];
            if ($derivedReason !== '' && (!isset($metaCurrent['denied_reason']) || trim((string)$metaCurrent['denied_reason']) === '')) {
                $metaCurrent['denied_reason'] = $derivedReason;
            }
            if (!isset($metaCurrent['resubmit_url']) || trim((string)$metaCurrent['resubmit_url']) === '') {
                $metaCurrent['resubmit_url'] = 'DocumentUpload.php?mode=profiling';
            }
            $items[$i]['metadata'] = $metaCurrent;
        }
        continue;
    }

    // Sector parent transactions auto-follow their document verification.
    // Resident registration parent transactions are driven by resident status.
    $isDocDrivenTxn = in_array($rowTxnType, [
        'SECTOR_MEMBERSHIP',
        'SECTOR_MEMBERSHIP_VERIFICATION'
    ], true);
    if ($isDocDrivenTxn) {
        $purpose = txnDocPurposeFromType($rowTxnType);
        $attachmentIds = txnExtractAttachmentIds(is_array($items[$i]['metadata'] ?? null) ? $items[$i]['metadata'] : null);
        $docStatus = txnAggregateDocStatus($conn, $residentId, $purpose, $attachmentIds, null);
        if ($docStatus !== null) {
            $items[$i]['status_name'] = $docStatus;
        }
        if (strcasecmp((string)($items[$i]['status_name'] ?? ''), 'Rejected') === 0) {
            $derivedReason = txnFindLatestRejectedReason($conn, $residentId, $purpose, $attachmentIds, null);
            if ($derivedReason !== '') {
                $metaCurrent = is_array($items[$i]['metadata'] ?? null) ? $items[$i]['metadata'] : [];
                if (!isset($metaCurrent['denied_reason']) || trim((string)$metaCurrent['denied_reason']) === '') {
                    $metaCurrent['denied_reason'] = $derivedReason;
                }
                $items[$i]['metadata'] = $metaCurrent;
            }
        }
    }

    // Rejected resident proof/registration requests should also be resubmittable.
    if (in_array($rowTxnType, ['RESIDENT_PROFILING', 'PROOF_OF_RESIDENCY'], true)
        && txnIsDeniedStatus((string)($items[$i]['status_name'] ?? ''))
    ) {
        $metaCurrent = is_array($items[$i]['metadata'] ?? null) ? $items[$i]['metadata'] : [];
        if (!isset($metaCurrent['resubmit_url']) || trim((string)$metaCurrent['resubmit_url']) === '') {
            $metaCurrent['resubmit_url'] = 'DocumentUpload.php?mode=profiling';
        }
        $items[$i]['metadata'] = $metaCurrent;
    }

    // Rejected sector requests should be closed and immediately resubmittable from transactions.
    if (in_array($rowTxnType, ['SECTOR_MEMBERSHIP', 'SECTOR_MEMBERSHIP_VERIFICATION'], true)
        && txnIsDeniedStatus((string)($items[$i]['status_name'] ?? ''))
    ) {
        $metaCurrent = is_array($items[$i]['metadata'] ?? null) ? $items[$i]['metadata'] : [];
        if (!isset($metaCurrent['resubmit_url']) || trim((string)$metaCurrent['resubmit_url']) === '') {
            $metaCurrent['resubmit_url'] = 'DocumentUpload.php?mode=sector';
        }
        $items[$i]['metadata'] = $metaCurrent;
    }
}

$items = txnSuppressStaleResubmitUrls($items);

usort($items, static function (array $a, array $b): int {
    $aTsSource = (string)($a['updated_at'] ?? '');
    if ($aTsSource === '') {
        $aTsSource = (string)($a['reviewed_at'] ?? '');
    }
    if ($aTsSource === '') {
        $aTsSource = (string)($a['created_at'] ?? '');
    }

    $bTsSource = (string)($b['updated_at'] ?? '');
    if ($bTsSource === '') {
        $bTsSource = (string)($b['reviewed_at'] ?? '');
    }
    if ($bTsSource === '') {
        $bTsSource = (string)($b['created_at'] ?? '');
    }

    $at = strtotime($aTsSource) ?: 0;
    $bt = strtotime($bTsSource) ?: 0;
    return $bt <=> $at;
});

if ($offset > 0 || $limit > 0) {
    $items = array_slice($items, $offset, $limit);
}

echo json_encode([
    'success' => true,
    'items' => $items,
    'limit' => $limit,
    'offset' => $offset,
]);
