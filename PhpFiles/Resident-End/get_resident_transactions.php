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

// Prefer unifiedtransactiontbl (new generalized ledger).
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
    FROM unifiedtransactiontbl t
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
    echo json_encode(['success' => false, 'message' => 'Failed to load transactions. Ensure unifiedtransactiontbl exists and uses the expected columns.']);
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
$residentStmt = $conn->prepare("SELECT resident_id FROM residentinformationtbl WHERE user_id = ? LIMIT 1");
if ($residentStmt) {
    $residentStmt->bind_param("s", $userId);
    $residentStmt->execute();
    $residentStmt->bind_result($residentId);
    $residentStmt->fetch();
    $residentStmt->close();
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

    // Keep parent transaction status aligned with its relevant document statuses.
    $purpose = txnDocPurposeFromType($rowTxnType);
    $attachmentIds = txnExtractAttachmentIds(is_array($items[$i]['metadata'] ?? null) ? $items[$i]['metadata'] : null);
    $docStatus = txnAggregateDocStatus($conn, $residentId, $purpose, $attachmentIds, null);
    if ($docStatus !== null) {
        $items[$i]['status_name'] = $docStatus;
    }
}

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
