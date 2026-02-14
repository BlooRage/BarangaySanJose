<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once "../General/connection.php";
require_once "../General/security.php";

// Migration tool: rewrites unifiedfileattachmenttbl.file_path to a portable, project-relative format
// e.g. "/BarangaySanJose/UnifiedFileAttachment/..." or absolute paths -> "UnifiedFileAttachment/..."
//
// Usage:
// - POST with mode=dry_run (default) to preview changes
// - POST with mode=apply&confirm=YES to apply
//
// This must remain admin-only.
requireRoleSession(['Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$mode = strtolower(trim((string)($_POST['mode'] ?? 'dry_run')));
$confirm = trim((string)($_POST['confirm'] ?? ''));
$apply = ($mode === 'apply');

if ($apply && $confirm !== 'YES') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => "Missing confirmation. Send confirm=YES to apply."]);
    exit;
}

function normalizeSlashes(string $path): string {
    $p = str_replace("\\", "/", trim($path));
    $p = preg_replace('#/+#', '/', $p);
    return $p;
}

function toPortableAttachmentPath(string $rawPath): ?string {
    $p = normalizeSlashes($rawPath);
    if ($p === '') return null;

    // Already portable.
    if (stripos($p, 'UnifiedFileAttachment/') === 0) {
        return $p;
    }

    $marker = '/UnifiedFileAttachment/';
    $pos = stripos($p, $marker);
    if ($pos === false) return null;

    return ltrim(substr($p, $pos), '/');
}

try {
    $stmt = $conn->prepare("
        SELECT attachment_id, file_path
        FROM unifiedfileattachmenttbl
        WHERE file_path LIKE '%UnifiedFileAttachment/%'
        ORDER BY attachment_id ASC
    ");
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $updates = [];
    foreach ($rows as $row) {
        $id = (int)($row['attachment_id'] ?? 0);
        $old = (string)($row['file_path'] ?? '');
        if ($id <= 0 || $old === '') continue;

        $new = toPortableAttachmentPath($old);
        if ($new === null) continue;

        if ($new !== $old) {
            $updates[] = ['attachment_id' => $id, 'old' => $old, 'new' => $new];
        }
    }

    if (!$apply) {
        echo json_encode([
            'success' => true,
            'mode' => 'dry_run',
            'matched_rows' => count($rows),
            'would_update' => count($updates),
            'sample' => array_slice($updates, 0, 20)
        ]);
        exit;
    }

    $conn->begin_transaction();
    $updStmt = $conn->prepare("
        UPDATE unifiedfileattachmenttbl
        SET file_path = ?
        WHERE attachment_id = ?
        LIMIT 1
    ");
    if (!$updStmt) {
        throw new Exception("Prepare failed (update): " . $conn->error);
    }

    $updated = 0;
    foreach ($updates as $u) {
        $new = (string)$u['new'];
        $id = (int)$u['attachment_id'];
        $updStmt->bind_param("si", $new, $id);
        $updStmt->execute();
        if ($updStmt->affected_rows > 0) $updated++;
    }
    $updStmt->close();
    $conn->commit();

    echo json_encode([
        'success' => true,
        'mode' => 'apply',
        'matched_rows' => count($rows),
        'updated_rows' => $updated
    ]);
    exit;
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

