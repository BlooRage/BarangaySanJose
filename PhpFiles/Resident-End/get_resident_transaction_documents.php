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
$sourceType = trim((string)($_GET['source_type'] ?? ''));
$sourceId = trim((string)($_GET['source_id'] ?? ''));
$purpose = strtolower(trim((string)($_GET['purpose'] ?? 'all')));
$attachmentIdsRaw = trim((string)($_GET['attachment_ids'] ?? ''));
$cutoffAt = trim((string)($_GET['cutoff_at'] ?? ''));

if ($sourceType === '' || $sourceId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing source parameters.']);
    exit;
}

if (!in_array($purpose, ['all', 'proof', 'sector'], true)) {
    $purpose = 'all';
}

$attachmentIds = [];
if ($attachmentIdsRaw !== '') {
    foreach (explode(',', $attachmentIdsRaw) as $token) {
        $id = (int)trim($token);
        if ($id > 0) {
            $attachmentIds[] = $id;
        }
    }
    $attachmentIds = array_values(array_unique($attachmentIds));
}

$residentId = '';
$stmtResident = $conn->prepare("SELECT resident_id FROM residentinformationtbl WHERE user_id = ? LIMIT 1");
if (!$stmtResident) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to validate resident context.']);
    exit;
}
$stmtResident->bind_param("s", $userId);
$stmtResident->execute();
$stmtResident->bind_result($residentId);
$stmtResident->fetch();
$stmtResident->close();

if ($residentId === '' || $sourceId !== $residentId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

function currentAppBasePath(): string {
    $scriptName = str_replace("\\", "/", (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $pos = strpos($scriptName, '/PhpFiles/');
    $base = $pos !== false ? substr($scriptName, 0, $pos) : dirname($scriptName);
    $base = rtrim((string)$base, '/');
    if ($base === '.' || $base === '/') {
        return '';
    }
    return $base;
}

function toPublicPath(string $path): string {
    $normalized = str_replace("\\", "/", trim($path));
    if ($normalized === '') {
        return '';
    }

    $marker = '/UnifiedFileAttachment/';
    $markerPos = stripos($normalized, $marker);
    if ($markerPos !== false) {
        return '..' . substr($normalized, $markerPos);
    }

    $appBase = currentAppBasePath();
    if ($appBase !== '' && strpos($normalized, $appBase . '/') === 0) {
        return $normalized;
    }
    $projectRoot = realpath(__DIR__ . '/../../');
    $projectBase = $projectRoot ? trim((string)basename($projectRoot)) : '';
    $projectPrefix = '/' . $projectBase . '/';
    if ($projectBase !== '' && strpos($normalized, $projectPrefix) === 0) {
        return ($appBase === '' ? '' : $appBase) . substr($normalized, strlen('/' . $projectBase));
    }

    return '../' . ltrim($normalized, '/');
}

$sql = "
    SELECT
        uf.attachment_id,
        dt.document_type_name,
        COALESCE(s.status_name, 'PendingReview') AS status_name,
        uf.file_name,
        uf.file_path,
        uf.file_type,
        uf.remarks,
        uf.upload_timestamp,
        COALESCE(uf.updated_at, uf.upload_timestamp) AS status_changed_at
    FROM unifiedfileattachmenttbl uf
    INNER JOIN documenttypelookuptbl dt ON dt.document_type_id = uf.document_type_id
    LEFT JOIN statuslookuptbl s ON s.status_id = uf.status_id_verify
    WHERE uf.source_type = ?
      AND uf.source_id = ?
      AND dt.document_category = 'ResidentProfiling'
";

if ($purpose === 'proof') {
    $sql .= " AND (uf.remarks IS NULL OR uf.remarks NOT LIKE 'sector:%') ";
} elseif ($purpose === 'sector') {
    $sql .= " AND uf.remarks LIKE 'sector:%' ";
}

if (!empty($attachmentIds)) {
    $placeholders = implode(',', array_fill(0, count($attachmentIds), '?'));
    $sql .= " AND uf.attachment_id IN ($placeholders) ";
}

if ($cutoffAt !== '') {
    $sql .= " AND COALESCE(uf.updated_at, uf.upload_timestamp) <= ? ";
}

$sql .= " ORDER BY COALESCE(uf.updated_at, uf.upload_timestamp) DESC, uf.attachment_id DESC ";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load documents.']);
    exit;
}

$types = "ss";
$params = [$sourceType, $sourceId];
if (!empty($attachmentIds)) {
    $types .= str_repeat('i', count($attachmentIds));
    foreach ($attachmentIds as $id) {
        $params[] = $id;
    }
}
if ($cutoffAt !== '') {
    $types .= "s";
    $params[] = $cutoffAt;
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$docs = [];
while ($row = $res->fetch_assoc()) {
    $docs[] = [
        'attachment_id' => (int)$row['attachment_id'],
        'document_type_name' => (string)$row['document_type_name'],
        'status_name' => (string)$row['status_name'],
        'file_name' => (string)$row['file_name'],
        'file_type' => (string)$row['file_type'],
        'remarks' => (string)($row['remarks'] ?? ''),
        'uploaded_at' => (string)$row['upload_timestamp'],
        'status_changed_at' => (string)($row['status_changed_at'] ?? $row['upload_timestamp']),
        'file_url' => toPublicPath((string)$row['file_path']),
    ];
}
$stmt->close();

echo json_encode([
    'success' => true,
    'items' => $docs,
]);
exit;
