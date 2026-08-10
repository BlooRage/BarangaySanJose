<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/security.php';
require_once __DIR__ . '/../General/adminModulePermissions.php';
require_once __DIR__ . '/../General/officialSignature.php';

requireRoleSession(['SuperAdmin', 'Official', 'Officials'], false);
header('Content-Type: application/json; charset=utf-8');

function osig_reply(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

$userId = trim((string)($_SESSION['user_id'] ?? ''));
$account = $userId !== '' ? amp_get_official_account_by_user_id($conn, $userId) : null;
$officialId = trim((string)($account['official_id'] ?? ''));
if (!$account || $officialId === '' || amp_get_protected_code($account) !== 'BARANGAY_CAPTAIN') {
    osig_reply(['success' => false, 'message' => 'Official signature setup is available only to the current Barangay Chairman.'], 403);
}

$action = trim((string)($_POST['action'] ?? ''));
if ($action === 'skip') {
    $_SESSION['official_signature_skipped'] = true;
    osig_reply(['success' => true]);
}
if ($action === 'remove') {
    osig_ensure_schema($conn);
    $stmt = $conn->prepare("UPDATE officialsignaturetbl SET is_active = 0, deactivated_at = NOW() WHERE official_id = ? AND is_active = 1");
    $stmt?->bind_param('s', $officialId);
    $stmt?->execute();
    $stmt?->close();
    unset($_SESSION['official_signature_skipped']);
    osig_reply(['success' => true, 'message' => 'Official signature removed.']);
}

if ($action !== 'save') osig_reply(['success' => false, 'message' => 'Invalid signature action.'], 400);
$dataUrl = trim((string)($_POST['signature_data'] ?? ''));
$method = strtolower(trim((string)($_POST['creation_method'] ?? 'draw')));
if (!in_array($method, ['draw', 'upload', 'type'], true)) $method = 'draw';
if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $match)) {
    osig_reply(['success' => false, 'message' => 'Create or upload a valid signature first.'], 422);
}
$binary = base64_decode($match[1], true);
if ($binary === false || strlen($binary) < 100 || strlen($binary) > 3 * 1024 * 1024 || @getimagesizefromstring($binary) === false) {
    osig_reply(['success' => false, 'message' => 'The signature image is invalid or too large.'], 422);
}

// A transparent canvas is technically a valid PNG, but it is not a usable
// signature. Reject it here instead of activating an invisible document asset.
if (function_exists('imagecreatefromstring')) {
    $signatureImage = @imagecreatefromstring($binary);
    if ($signatureImage !== false) {
        $imageWidth = imagesx($signatureImage);
        $imageHeight = imagesy($signatureImage);
        $stepX = max(1, (int)floor($imageWidth / 300));
        $stepY = max(1, (int)floor($imageHeight / 100));
        $visiblePixels = 0;
        for ($y = 0; $y < $imageHeight && $visiblePixels < 8; $y += $stepY) {
            for ($x = 0; $x < $imageWidth && $visiblePixels < 8; $x += $stepX) {
                $rgba = imagecolorat($signatureImage, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                if ($alpha < 118) $visiblePixels++;
            }
        }
        imagedestroy($signatureImage);
        if ($visiblePixels < 8) {
            osig_reply(['success' => false, 'message' => 'The signature is blank. Draw or upload a visible signature before saving.'], 422);
        }
    }
}

$projectRoot = realpath(__DIR__ . '/../../');
$relativeDir = '/UnifiedFileAttachment/OfficialSignatures/' . preg_replace('/[^A-Za-z0-9_-]/', '', $officialId);
$diskDir = $projectRoot . $relativeDir;
if (!is_dir($diskDir) && !mkdir($diskDir, 0775, true) && !is_dir($diskDir)) {
    osig_reply(['success' => false, 'message' => 'Unable to prepare signature storage.'], 500);
}
$fileName = 'signature_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.png';
if (file_put_contents($diskDir . '/' . $fileName, $binary, LOCK_EX) === false) {
    osig_reply(['success' => false, 'message' => 'Unable to save the signature.'], 500);
}
$filePath = $relativeDir . '/' . $fileName;

osig_ensure_schema($conn);
$conn->begin_transaction();
try {
    $off = $conn->prepare("UPDATE officialsignaturetbl SET is_active = 0, deactivated_at = NOW() WHERE official_id = ? AND is_active = 1");
    $off->bind_param('s', $officialId);
    $off->execute();
    $off->close();
    $ins = $conn->prepare("INSERT INTO officialsignaturetbl (official_id, user_id, file_path, signature_blob, creation_method) VALUES (?, ?, ?, ?, ?)");
    $nullBlob = null;
    $ins->bind_param('sssbs', $officialId, $userId, $filePath, $nullBlob, $method);
    $ins->send_long_data(3, $binary);
    $ins->execute();
    $ins->close();
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    @unlink($diskDir . '/' . $fileName);
    osig_reply(['success' => false, 'message' => 'Unable to activate the signature.'], 500);
}
osig_reply(['success' => true, 'message' => 'Official signature saved and activated.', 'file_path' => appUrl($filePath)]);
