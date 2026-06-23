<?php
require_once __DIR__ . '/../General/security.php';
require_once __DIR__ . '/../General/connection.php';

requireRoleSession(['Resident'], true);

header('Content-Type: application/json; charset=utf-8');

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
    exit;
}

function toPublicPath(?string $path): ?string
{
    $path = trim((string)$path);
    if ($path === '') return null;

    $normalized = str_replace("\\", "/", $path);
    $normalized = preg_replace('#/+#', '/', $normalized);

    $marker = '/UnifiedFileAttachment/';
    $pos = stripos($normalized, $marker);
    if ($pos !== false) {
        // This endpoint is consumed from Resident-End pages, so prefix with ".."
        return '..' . substr($normalized, $pos);
    }

    // If it's already a relative path starting with UnifiedFileAttachment, normalize to ../UnifiedFileAttachment/...
    if (stripos($normalized, 'UnifiedFileAttachment/') === 0) {
        return '../' . $normalized;
    }

    return null;
}

try {
    $userId = (string)($_SESSION['user_id'] ?? '');

    // Resident id is used as source_id for ResidentProfiling uploads.
    $residentId = '';
    $r = $conn->prepare("SELECT resident_id FROM residentinformationtbl WHERE user_id = ? LIMIT 1");
    if ($r) {
        $r->bind_param('s', $userId);
        $r->execute();
        $res = $r->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $residentId = (string)($row['resident_id'] ?? '');
        $r->close();
    }

    // Fetch verified attachments uploaded by this user.
    $stmt = $conn->prepare("
        SELECT
            uf.attachment_id,
            uf.source_type,
            uf.source_id,
            uf.file_name,
            uf.file_path,
            uf.file_type,
            uf.upload_timestamp,
            uf.remarks,
            uf.id_number,
            dt.document_type_name,
            dt.document_category
        FROM unifiedfileattachmenttbl uf
        INNER JOIN documenttypelookuptbl dt
            ON uf.document_type_id = dt.document_type_id
        INNER JOIN statuslookuptbl s
            ON uf.status_id_verify = s.status_id
        WHERE uf.user_id_uploaded_by = ?
          AND uf.deleted_at IS NULL
          AND s.status_name = 'Verified'
          AND s.status_type = 'ResidentDocumentProfiling'
        ORDER BY uf.upload_timestamp DESC, uf.attachment_id DESC
    ");
    if (!$stmt) {
        throw new Exception('Failed to prepare query.');
    }

    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'attachment_id' => (string)($row['attachment_id'] ?? ''),
            'source_type' => (string)($row['source_type'] ?? ''),
            'source_id' => (string)($row['source_id'] ?? ''),
            'file_name' => (string)($row['file_name'] ?? ''),
            'file_type' => (string)($row['file_type'] ?? ''),
            'upload_timestamp' => (string)($row['upload_timestamp'] ?? ''),
            'remarks' => (string)($row['remarks'] ?? ''),
            'id_number' => (string)($row['id_number'] ?? ''),
            'document_type_name' => (string)($row['document_type_name'] ?? ''),
            'document_category' => (string)($row['document_category'] ?? ''),
            'public_url' => toPublicPath((string)($row['file_path'] ?? '')),
            'viewer_url' => '../PhpFiles/Resident-End/viewUploadedDocument.php?attachment_id=' . rawurlencode((string)($row['attachment_id'] ?? '')),
            'open_url' => '../PhpFiles/Resident-End/viewUploadedDocument.php?attachment_id=' . rawurlencode((string)($row['attachment_id'] ?? '')),
            'is_profile_source' => ($residentId !== '' && (string)($row['source_type'] ?? '') === 'ResidentProfiling' && (string)($row['source_id'] ?? '') === $residentId),
        ];
    }

    $stmt->close();

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
}
