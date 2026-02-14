<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

require_once __DIR__ . '/../General/connection.php';

$profileImage = '../Images/Profile-Placeholder.png';
$residentId = '';

if (!function_exists('toPublicPath')) {
function toPublicPath($path): ?string {
    $path = trim((string)$path);
    if ($path === '') {
        return null;
    }

    $normalized = str_replace("\\", "/", $path);
    $normalized = preg_replace('#/+#', '/', $normalized);

    $parts = explode('/', $normalized);
    $cleanParts = [];
    foreach ($parts as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            array_pop($cleanParts);
            continue;
        }
        $cleanParts[] = $part;
    }
    $normalized = '/' . implode('/', $cleanParts);

    $marker = '/UnifiedFileAttachment/';
    $markerPos = stripos($normalized, $marker);
    if ($markerPos !== false) {
        $public = substr($normalized, $markerPos);
        return '..' . $public;
    }

    if (strpos($normalized, '/BarangaySanJose/') === 0) {
        return $normalized;
    }

    $webRoot = realpath(__DIR__ . "/../..");
    if ($webRoot) {
        $rootNorm = str_replace("\\", "/", $webRoot);
        if (strpos($normalized, $rootNorm) === 0) {
            $rel = substr($normalized, strlen($rootNorm));
            if ($rel === '') {
                return null;
            }
            if ($rel[0] !== '/') {
                $rel = '/' . $rel;
            }
            return '../' . ltrim($rel, '/');
        }
    }

    return '../' . ltrim($normalized, '/');
}
}

if (isset($conn) && $conn instanceof mysqli) {
    $stmt = $conn->prepare("
        SELECT resident_id
        FROM residentinformationtbl
        WHERE user_id = ?
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("s", $_SESSION['user_id']);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $residentId = $row['resident_id'] ?? '';
        }
        $stmt->close();
    }
}

if ($residentId !== '' && isset($conn) && $conn instanceof mysqli) {
    $stmtPic = $conn->prepare("
        SELECT uf.file_path
        FROM unifiedfileattachmenttbl uf
        INNER JOIN documenttypelookuptbl dt
            ON uf.document_type_id = dt.document_type_id
        INNER JOIN statuslookuptbl s
            ON uf.status_id_verify = s.status_id
        WHERE uf.source_type = 'ResidentProfiling'
          AND uf.source_id = ?
          AND dt.document_type_name = '2x2 Picture'
          AND dt.document_category = 'ResidentProfiling'
          AND s.status_name = 'Verified'
          AND s.status_type = 'ResidentDocumentProfiling'
        ORDER BY uf.upload_timestamp DESC, uf.attachment_id DESC
        LIMIT 1
    ");
    if ($stmtPic) {
        $stmtPic->bind_param("s", $residentId);
        $stmtPic->execute();
        $stmtPic->bind_result($verifiedPicPath);
        if ($stmtPic->fetch() && !empty($verifiedPicPath)) {
            $publicPath = toPublicPath($verifiedPicPath);
            if (!empty($publicPath)) {
                $profileImage = $publicPath;
            }
        }
        $stmtPic->close();
    }
}

echo json_encode([
    'success' => true,
    'profile_image' => $profileImage,
]);
