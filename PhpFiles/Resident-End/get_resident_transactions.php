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

$sql .= " ORDER BY t.created_at DESC, t.transaction_id DESC LIMIT ? OFFSET ? ";
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
    ];
}
$stmt->close();

echo json_encode([
    'success' => true,
    'items' => $items,
    'limit' => $limit,
    'offset' => $offset,
]);
