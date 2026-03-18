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

$cacheKey = 'verified_profile_image_cache';
$cacheTtlSeconds = 300;
$cachedProfile = $_SESSION[$cacheKey] ?? null;
if (is_array($cachedProfile)) {
    $cachedUserId = (string)($cachedProfile['user_id'] ?? '');
    $cachedImage = trim((string)($cachedProfile['profile_image'] ?? ''));
    $cachedAt = (int)($cachedProfile['cached_at'] ?? 0);

    if (
        $cachedUserId === (string)$_SESSION['user_id']
        && $cachedImage !== ''
        && $cachedAt > 0
        && (time() - $cachedAt) < $cacheTtlSeconds
    ) {
        echo json_encode([
            'success' => true,
            'profile_image' => $cachedImage,
        ]);
        exit;
    }
}

require_once __DIR__ . '/../General/connection.php';

$scriptName = str_replace("\\", "/", (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$phpSegmentPos = strpos($scriptName, '/PhpFiles/');
$baseUrl = '';
if ($phpSegmentPos !== false) {
    $baseUrl = substr($scriptName, 0, $phpSegmentPos);
} else {
    $baseUrl = dirname($scriptName);
}
$baseUrl = rtrim((string)$baseUrl, '/');
if ($baseUrl === '.' || $baseUrl === '/') {
    $baseUrl = '';
}

$profileImage = $baseUrl . '/Images/Profile-Placeholder.png';
$residentId = '';

if (!function_exists('toPublicPath')) {
function toPublicPath($path): ?string {
    global $baseUrl;
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
        return $baseUrl . $public;
    }

    if ($baseUrl !== '' && strpos($normalized, $baseUrl . '/') === 0) {
        return $normalized;
    }

    $projectRoot = realpath(__DIR__ . "/../..");
    $projectBase = $projectRoot ? trim((string)basename($projectRoot)) : '';
    $projectPrefix = '/' . $projectBase . '/';
    if ($projectBase !== '' && strpos($normalized, $projectPrefix) === 0) {
        return ($baseUrl === '' ? '' : $baseUrl) . substr($normalized, strlen('/' . $projectBase));
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
            return $baseUrl . $rel;
        }
    }

    return $baseUrl . '/' . ltrim($normalized, '/');
}
}

if (!function_exists('publicPathExists')) {
function publicPathExists(?string $publicPath): bool {
    global $baseUrl;
    $publicPath = trim((string)$publicPath);
    if ($publicPath === '') {
        return false;
    }
    if (preg_match('#^https?://#i', $publicPath)) {
        return true;
    }
    if ($baseUrl !== '' && strpos($publicPath, $baseUrl) === 0) {
        $relative = substr($publicPath, strlen($baseUrl));
    } else {
        $projectRoot = realpath(__DIR__ . "/../..");
        $projectBase = $projectRoot ? trim((string)basename($projectRoot)) : '';
        if ($projectBase !== '' && strpos($publicPath, '/' . $projectBase) === 0) {
            $relative = substr($publicPath, strlen('/' . $projectBase));
        } else {
            $relative = $publicPath;
        }
    }
    $relative = '/' . ltrim((string)$relative, '/');
    $absolute = realpath(__DIR__ . "/../.." . $relative);
    if ($absolute === false) {
        return false;
    }
    return is_file($absolute);
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
        LEFT JOIN statuslookuptbl s
            ON uf.status_id_verify = s.status_id
        LEFT JOIN resident_edit_requesttbl rer
            ON uf.source_type = 'ResidentEditRequest'
           AND rer.request_id = uf.source_id
        LEFT JOIN statuslookuptbl rs
            ON rer.status_id = rs.status_id
        WHERE LOWER(dt.document_type_name) = LOWER('2x2 Picture')
          AND (dt.document_category = 'ResidentProfiling' OR dt.document_category = 'EditRequest' OR dt.document_category IS NULL)
          AND (
                (
                    uf.source_type IN ('ResidentProfiling', 'RESIDENT_PROFILE')
                    AND uf.source_id = ?
                    AND (s.status_name = 'Verified' OR s.status_name = 'Approved')
                )
                OR
                (
                    uf.source_type = 'ResidentEditRequest'
                    AND rer.resident_id = ?
                    AND rer.request_type = 'profile'
                    AND rs.status_name = 'ApprovedRequest'
                )
          )
        ORDER BY uf.upload_timestamp DESC, uf.attachment_id DESC
        LIMIT 1
    ");
    if ($stmtPic) {
        $stmtPic->bind_param("ss", $residentId, $residentId);
        $stmtPic->execute();
        $stmtPic->bind_result($verifiedPicPath);
        if ($stmtPic->fetch() && !empty($verifiedPicPath)) {
            $publicPath = toPublicPath($verifiedPicPath);
            if (!empty($publicPath) && publicPathExists($publicPath)) {
                $profileImage = $publicPath;
            }
        }
        $stmtPic->close();
    }
}

$_SESSION[$cacheKey] = [
    'user_id' => (string)$_SESSION['user_id'],
    'profile_image' => $profileImage,
    'cached_at' => time(),
];

echo json_encode([
    'success' => true,
    'profile_image' => $profileImage,
]);
