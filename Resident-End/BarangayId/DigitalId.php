<?php
if (!isset($baseUrl)) {
    $scriptName = str_replace("\\", "/", (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $residentSegmentPos = strpos($scriptName, '/Resident-End/');
    $baseUrl = '';
    if ($residentSegmentPos !== false) {
        $baseUrl = substr($scriptName, 0, $residentSegmentPos);
    } else {
        $baseUrl = dirname($scriptName);
    }
    $baseUrl = rtrim((string)$baseUrl, '/');
    if ($baseUrl === '.' || $baseUrl === '/') {
        $baseUrl = '';
    }
}

$allowUnregistered = false;
require_once __DIR__ . '/../includes/resident_access_guard.php';
require_once __DIR__ . '/../../PhpFiles/General/documentRequestWorkflow.php';
require_once __DIR__ . '/../../PhpFiles/General/documentModuleSettings.php';

if (!function_exists('drd_public_asset_path')) {
    function drd_public_asset_path(string $baseUrl, string $storedPath): string
    {
        $storedPath = trim($storedPath);
        if ($storedPath === '') {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $storedPath)) {
            return $storedPath;
        }

        $normalized = str_replace('\\', '/', $storedPath);
        $marker = '/UnifiedFileAttachment/';
        $markerPos = stripos($normalized, $marker);
        if ($markerPos !== false) {
            return rtrim($baseUrl, '/') . substr($normalized, $markerPos);
        }

        $projectRoot = realpath(__DIR__ . '/../../');
        if ($projectRoot !== false) {
            $rootNorm = str_replace('\\', '/', $projectRoot);
            if (strpos($normalized, $rootNorm) === 0) {
                $relative = substr($normalized, strlen($rootNorm));
                return rtrim($baseUrl, '/') . '/' . ltrim((string)$relative, '/');
            }
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($normalized, '/');
    }
}

if (!function_exists('drd_resolve_request_qr_preview_url')) {
    function drd_resolve_request_qr_preview_url(string $baseUrl, array $requestRow): string
    {
        $storedPath = trim((string)($requestRow['qr_code_path'] ?? ''));
        if ($storedPath !== '') {
            return drd_public_asset_path($baseUrl, $storedPath);
        }

        $requestId = trim((string)($requestRow['request_id'] ?? ''));
        if ($requestId === '') {
            return '';
        }

        $verificationCode = trim((string)($requestRow['verification_code'] ?? ''));
        if ($verificationCode === '') {
            $verificationCode = $requestId;
        }

        $predictedPublicPath = '/UnifiedFileAttachment/IssuedDocuments/QR/qr_' . preg_replace('/[^A-Za-z0-9_-]/', '', $requestId) . '.png';
        $projectRoot = realpath(__DIR__ . '/../../');
        if ($projectRoot !== false) {
            $predictedDiskPath = $projectRoot . $predictedPublicPath;
            if (is_file($predictedDiskPath)) {
                return drd_public_asset_path($baseUrl, $predictedPublicPath);
            }
        }

        $verificationUrl = appBaseUrl() . appUrl('/transactions?request_id=' . rawurlencode($requestId) . '&vc=' . rawurlencode($verificationCode));
        return 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode($verificationUrl);
    }
}

if (!function_exists('drd_resolve_resident_2x2_picture_path')) {
    function drd_resolve_resident_2x2_picture_path(mysqli $conn, string $residentId): string
    {
        $residentId = trim($residentId);
        if ($residentId === '') {
            return '';
        }

        $sql = "
            SELECT uf.file_path
            FROM unifiedfileattachmenttbl uf
            LEFT JOIN documenttypelookuptbl dt
                ON dt.document_type_id = uf.document_type_id
            LEFT JOIN statuslookuptbl sv
                ON sv.status_id = uf.status_id_verify
            LEFT JOIN resident_edit_requesttbl rer
                ON uf.source_type = 'ResidentEditRequest'
               AND rer.request_id = uf.source_id
            LEFT JOIN statuslookuptbl rs
                ON rs.status_id = rer.status_id
            WHERE LOWER(COALESCE(dt.document_type_name, '')) = '2x2 picture'
              AND (
                    LOWER(COALESCE(dt.document_category, 'residentprofiling')) = 'residentprofiling'
                    OR LOWER(COALESCE(dt.document_category, '')) = 'editrequest'
                    OR dt.document_category IS NULL
                  )
              AND (
                    (
                        uf.source_type IN ('ResidentProfiling', 'RESIDENT_PROFILE')
                        AND uf.source_id = ?
                    )
                    OR
                    (
                        uf.source_type = 'ResidentEditRequest'
                        AND rer.resident_id = ?
                        AND rer.request_type = 'profile'
                    )
                  )
            ORDER BY
                CASE
                    WHEN uf.source_type = 'ResidentEditRequest'
                         AND LOWER(COALESCE(rs.status_name, '')) = 'approvedrequest' THEN 0
                    WHEN uf.source_type IN ('ResidentProfiling', 'RESIDENT_PROFILE')
                         AND LOWER(COALESCE(sv.status_name, '')) IN ('verified', 'approved') THEN 0
                    WHEN uf.source_type IN ('ResidentProfiling', 'RESIDENT_PROFILE') THEN 1
                    ELSE 2
                END,
                uf.upload_timestamp DESC,
                uf.attachment_id DESC
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return '';
        }

        $stmt->bind_param('ss', $residentId, $residentId);
        $stmt->execute();
        $stmt->bind_result($resolvedPath);
        $path = ($stmt->fetch() && is_string($resolvedPath)) ? trim($resolvedPath) : '';
        $stmt->close();

        return $path;
    }
}

$embedMode = isset($_GET['embed']) && (string)$_GET['embed'] === '1';
$requestId = trim((string)($_GET['request_id'] ?? ''));
$userId = (string)($_SESSION['user_id'] ?? '');
$errorMessage = '';
$requestRow = null;
$payload = [];
$resolvedProfileImageUrl = '';
$resolvedProfileImagePath = '';
$resolvedQrCodeUrl = '';

if ($requestId === '') {
    $errorMessage = 'Missing request ID.';
} else {
    $requestRow = dr_fetch_request($conn, $requestId);
    if (!$requestRow || (string)($requestRow['resident_user_id'] ?? '') !== $userId) {
        $errorMessage = 'Barangay ID request not found.';
    } elseif (!dr_is_barangay_id_document_type((string)($requestRow['document_type'] ?? ''))) {
        $errorMessage = 'This request is not a Barangay ID.';
    } elseif (strtolower(trim((string)($requestRow['stage'] ?? ''))) !== strtolower((string)DR_STAGE_COMPLETED)) {
        $errorMessage = 'Your digital Barangay ID will be available once the request is marked completed.';
    } else {
        $payload = function_exists('dr_decode_request_payload')
            ? dr_decode_request_payload($requestRow)
            : json_decode((string)($requestRow['request_details'] ?? '{}'), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        if (isset($conn) && $conn instanceof mysqli && $userId !== '') {
            $stmtResident = $conn->prepare("
                SELECT resident_id
                FROM residentinformationtbl
                WHERE user_id = ?
                LIMIT 1
            ");
            if ($stmtResident) {
                $stmtResident->bind_param('s', $userId);
                $stmtResident->execute();
                $stmtResident->bind_result($residentId);
                if ($stmtResident->fetch() && is_string($residentId)) {
                    $resolvedProfileImagePath = drd_resolve_resident_2x2_picture_path($conn, $residentId);
                    $resolvedProfileImageUrl = drd_public_asset_path($baseUrl, $resolvedProfileImagePath);
                }
                $stmtResident->close();
            }
        }

        if ($resolvedProfileImagePath !== '') {
            $payload['id_picture_path'] = $resolvedProfileImagePath;
        }
        if ($resolvedProfileImageUrl !== '') {
            $payload['id_picture_url'] = $resolvedProfileImageUrl;
        }
        $resolvedQrCodeUrl = drd_resolve_request_qr_preview_url($baseUrl, $requestRow);
        if ($resolvedQrCodeUrl !== '') {
            $payload['qr_code_path'] = $resolvedQrCodeUrl;
            $requestRow['qr_code_path'] = $resolvedQrCodeUrl;
        }
    }
}

$documentRequestsUrl = appUrl('/Resident-End/document_requests.php');
$profileImageEndpoint = appUrl('/PhpFiles/Resident-End/getVerifiedProfileImage.php');
$barangayIdTemplateSettings = isset($conn) && $conn instanceof mysqli
    ? dms_resolve_barangay_id_template_settings($conn)
    : [
        'front_template_path' => dms_barangay_id_default_template_paths()['front'],
        'back_template_path' => dms_barangay_id_default_template_paths()['back'],
        'layout' => dms_barangay_id_default_layout(),
    ];
$frontTemplatePublicPath = (string)($barangayIdTemplateSettings['front_template_path'] ?? dms_barangay_id_default_template_paths()['front']);
$backTemplatePublicPath = (string)($barangayIdTemplateSettings['back_template_path'] ?? dms_barangay_id_default_template_paths()['back']);
$frontTemplateDiskPath = dms_module_asset_public_path_to_disk($frontTemplatePublicPath);
$backTemplateDiskPath = dms_module_asset_public_path_to_disk($backTemplatePublicPath);
$frontTemplateVersion = $frontTemplateDiskPath !== '' && is_file($frontTemplateDiskPath) ? (string)@filemtime($frontTemplateDiskPath) : '';
$backTemplateVersion = $backTemplateDiskPath !== '' && is_file($backTemplateDiskPath) ? (string)@filemtime($backTemplateDiskPath) : '';
$frontTemplateUrl = rtrim($baseUrl, '/') . $frontTemplatePublicPath . ($frontTemplateVersion !== '' ? '?v=' . rawurlencode($frontTemplateVersion) : '');
$backTemplateUrl = rtrim($baseUrl, '/') . $backTemplatePublicPath . ($backTemplateVersion !== '' ? '?v=' . rawurlencode($backTemplateVersion) : '');
$serializedRow = $requestRow ? [
    'request_id' => (string)($requestRow['request_id'] ?? ''),
    'document_type' => (string)($requestRow['document_type'] ?? ''),
    'resident_name' => (string)($requestRow['resident_name'] ?? ''),
    'contact_number' => (string)($requestRow['contact_number'] ?? ''),
    'qr_code_path' => (string)($requestRow['qr_code_path'] ?? ''),
    'certificate_number' => (string)($requestRow['certificate_number'] ?? ''),
    'verification_code' => (string)($requestRow['verification_code'] ?? ''),
    'submitted_at' => (string)($requestRow['submitted_at'] ?? ''),
    'ready_at' => (string)($requestRow['ready_at'] ?? ''),
    'completed_at' => (string)($requestRow['completed_at'] ?? ''),
    'release_timestamp' => (string)($requestRow['release_timestamp'] ?? ''),
    'stage' => (string)($requestRow['stage'] ?? ''),
    'purpose' => (string)($requestRow['purpose'] ?? ''),
] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Images/favicon_sanjose.png?v=20260211">
    <title>Digital Barangay ID</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php if (!$embedMode): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/CSS-Styles/Resident-End-CSS/residentDashboard.css">
        <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/CSS-Styles/Guest-End-CSS/GeneralStyle.css">
        <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/CSS-Styles/Resident-End-CSS/barangayIdNav.css">
    <?php endif; ?>
    <style>
        body {
            background:
                radial-gradient(circle at top left, rgba(255, 214, 168, 0.42), transparent 36%),
                linear-gradient(180deg, #fff8ef 0%, #f5efe7 100%);
        }
        .digital-id-page {
            min-height: 100vh;
        }
        .digital-id-main {
            max-width: 1180px;
            margin: 0 auto;
        }
        .digital-id-card-shell {
            border: 1px solid rgba(232, 199, 157, 0.82);
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.94);
            box-shadow: 0 22px 60px rgba(72, 38, 6, 0.12);
        }
        .digital-id-page-title {
            font-family: 'Charis SIL Bold', serif;
            color: #c36000;
            font-size: clamp(2rem, 4vw, 3rem);
            line-height: 1.05;
            margin: 0;
        }
        .digital-id-page-copy {
            color: #6d553c;
            font-size: 0.98rem;
            margin: 0;
        }
        .digital-id-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .digital-id-meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: #fff5e7;
            color: #8b4b00;
            border: 1px solid #f3d0a1;
            font-weight: 700;
            font-size: 0.88rem;
        }
        .digital-id-loading {
            display: grid;
            place-items: center;
            min-height: 240px;
            color: #6d553c;
            text-align: center;
            gap: 12px;
        }
        .digital-id-loading .spinner-border {
            width: 2rem;
            height: 2rem;
            color: #c36000;
        }
        .digital-id-embed {
            background: #fff;
        }
        .digital-id-embed .digital-id-card-shell {
            border: 0;
            border-radius: 0;
            box-shadow: none;
            background: #fff;
        }
        .digital-id-viewer {
            display: grid;
            gap: 18px;
        }
        .digital-id-viewer__toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }
        .digital-id-viewer__badge-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .digital-id-viewer__badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: #fff;
            border: 1px solid rgba(214, 180, 138, 0.95);
            color: #835425;
            font-size: 0.9rem;
            font-weight: 800;
            box-shadow: 0 10px 24px rgba(120, 78, 17, 0.08);
        }
        .digital-id-viewer__badge--side {
            background: linear-gradient(135deg, #fff4e4 0%, #ffe2ba 100%);
            color: #9a4c00;
            border-color: rgba(234, 170, 90, 0.86);
        }
        .digital-id-viewer__hint {
            margin: 0;
            color: #6d553c;
            font-size: 0.95rem;
            line-height: 1.45;
            max-width: 460px;
        }
        .digital-id-viewer__stage {
            position: relative;
            overflow: hidden;
            border-radius: 26px;
            border: 1px solid rgba(198, 214, 245, 0.8);
            background:
                radial-gradient(circle at top left, rgba(227, 237, 255, 0.95), transparent 34%),
                linear-gradient(180deg, #fffdf8 0%, #edf4ff 100%);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95);
            padding: clamp(16px, 2.4vw, 28px);
            min-height: 340px;
            display: grid;
            place-items: center;
        }
        .digital-id-viewer__stage::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                linear-gradient(135deg, rgba(255, 255, 255, 0.5), transparent 48%),
                radial-gradient(circle at 20% 18%, rgba(255, 224, 177, 0.38), transparent 24%);
            pointer-events: none;
        }
        .digital-id-viewer__stage::after {
            content: '';
            position: absolute;
            inset: 16px;
            border-radius: 22px;
            border: 1px dashed rgba(114, 146, 205, 0.22);
            pointer-events: none;
        }
        .digital-id-viewer__card-stage {
            position: relative;
            z-index: 1;
            width: min(100%, clamp(340px, 76vw, 720px));
            margin: 0 auto;
        }
        .digital-id-viewer__card-stage.is-animating-forward {
            animation: digitalIdFlipForward 420ms cubic-bezier(0.22, 1, 0.36, 1);
            transform-origin: center center;
        }
        .digital-id-viewer__card-stage.is-animating-backward {
            animation: digitalIdFlipBackward 420ms cubic-bezier(0.22, 1, 0.36, 1);
            transform-origin: center center;
        }
        .digital-id-viewer__card-stage .barangay-id-card {
            width: 100%;
            margin: 0;
            border-radius: 30px;
            box-shadow: 0 26px 64px rgba(28, 56, 112, 0.14);
        }
        .digital-id-viewer__card-stage .barangay-id-card__label,
        .digital-id-viewer__card-stage .barangay-id-card__field--name,
        .digital-id-viewer__card-stage .barangay-id-card__field--address,
        .digital-id-viewer__card-stage .barangay-id-card__field--birthplace,
        .digital-id-viewer__card-stage .barangay-id-card__field--meta,
        .digital-id-viewer__card-stage .barangay-id-card__field--emergency {
            font-size: clamp(10.8px, 0.34rem + 0.58vw, 14.4px);
            line-height: 1.08;
        }
        .digital-id-viewer__card-stage .barangay-id-card__note {
            font-size: clamp(9.2px, 0.3rem + 0.46vw, 11.6px);
            line-height: 1.18;
        }
        .digital-id-viewer__card-stage .barangay-id-card__field--cardno {
            font-size: clamp(11.6px, 0.38rem + 0.76vw, 16.2px);
        }
        .digital-id-viewer__card-stage .barangay-id-card__bg {
            border-radius: 30px;
        }
        .digital-id-viewer__qr-stage {
            width: min(100%, 540px);
            min-height: 420px;
            display: grid;
            place-items: center;
            gap: 18px;
            padding: 30px;
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.94);
            border: 1px solid rgba(199, 216, 247, 0.95);
            box-shadow: 0 24px 60px rgba(28, 56, 112, 0.12);
        }
        .digital-id-viewer__qr-frame {
            width: min(100%, 360px);
            aspect-ratio: 1 / 1;
            border-radius: 26px;
            background: #fff;
            border: 1px dashed rgba(59, 94, 176, 0.3);
            display: grid;
            place-items: center;
            overflow: hidden;
        }
        .digital-id-viewer__qr-frame img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .digital-id-viewer__qr-placeholder {
            text-align: center;
            color: #5b6785;
            font-weight: 700;
            font-size: 1.05rem;
            padding: 20px;
        }
        .digital-id-viewer__qr-caption {
            margin: 0;
            text-align: center;
            color: #4c5e84;
            font-weight: 600;
            max-width: 420px;
        }
        .digital-id-viewer__footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }
        .digital-id-viewer__footer-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-left: auto;
        }
        .digital-id-viewer__btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 16px;
            font-weight: 800;
            padding: 12px 20px;
            border: 0;
            transition: transform 120ms ease, box-shadow 120ms ease, opacity 120ms ease;
        }
        .digital-id-viewer__btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(28, 56, 112, 0.16);
        }
        .digital-id-viewer__btn--close {
            background: #fff;
            color: #8a4b00;
            border: 1px solid rgba(227, 185, 131, 0.95);
            box-shadow: 0 10px 22px rgba(120, 78, 17, 0.08);
        }
        .digital-id-viewer__btn--mode {
            background: linear-gradient(180deg, #f59b2a 0%, #dd7309 100%);
            color: #fff;
            min-width: 180px;
        }
        @keyframes digitalIdFlipForward {
            0% {
                opacity: 0.45;
                transform: perspective(1400px) rotateY(-16deg) scale(0.97) translateX(-18px);
            }
            55% {
                opacity: 1;
                transform: perspective(1400px) rotateY(6deg) scale(1.012) translateX(6px);
            }
            100% {
                opacity: 1;
                transform: perspective(1400px) rotateY(0deg) scale(1) translateX(0);
            }
        }
        @keyframes digitalIdFlipBackward {
            0% {
                opacity: 0.45;
                transform: perspective(1400px) rotateY(16deg) scale(0.97) translateX(18px);
            }
            55% {
                opacity: 1;
                transform: perspective(1400px) rotateY(-6deg) scale(1.012) translateX(-6px);
            }
            100% {
                opacity: 1;
                transform: perspective(1400px) rotateY(0deg) scale(1) translateX(0);
            }
        }
        @media (max-width: 767.98px) {
            .digital-id-card-shell {
                border-radius: 20px;
            }
            .digital-id-viewer__toolbar {
                align-items: stretch;
            }
            .digital-id-viewer__hint {
                max-width: none;
                font-size: 0.92rem;
            }
            .digital-id-viewer__stage {
                padding: 14px;
                border-radius: 20px;
                min-height: 240px;
            }
            .digital-id-viewer__stage::after {
                inset: 10px;
                border-radius: 16px;
            }
            .digital-id-viewer__card-stage .barangay-id-card,
            .digital-id-viewer__card-stage .barangay-id-card__bg {
                border-radius: 20px;
            }
            .digital-id-viewer__card-stage {
                width: 100%;
            }
            .digital-id-viewer__badge,
            .digital-id-viewer__badge-group {
                width: 100%;
            }
            .digital-id-viewer__badge {
                justify-content: center;
            }
            .digital-id-viewer__footer-actions {
                width: 100%;
                margin-left: 0;
            }
            .digital-id-viewer__btn {
                width: 100%;
                justify-content: center;
            }
            .digital-id-viewer__btn--mode {
                min-width: 0;
            }
        }
        @media (max-width: 1160px) {
            #mobile-header {
                display: block !important;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                z-index: 1030;
                height: auto !important;
                padding: 0 !important;
                margin: 0 !important;
                overflow: visible !important;
            }
            #mobile-header .d-flex {
                width: 100%;
            }
            #div-mainDisplay {
                margin-left: 0 !important;
                width: 100%;
                padding-top: 1rem !important;
            }
            body:not(.digital-id-embed) {
                padding-top: 64px;
            }
            #div-sidebarWrapper {
                position: fixed !important;
                top: 0;
                left: 0;
                height: 100vh !important;
                width: 280px;
                z-index: 1060;
                transform: translateX(-100%);
                transition: transform 0.28s ease;
                box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0);
            }
            #div-sidebarWrapper.show {
                transform: translateX(0);
                box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.25);
            }
        }
        @media (min-width: 1161px) {
            body:not(.digital-id-embed) {
                padding-top: 0;
            }
            #mobile-header {
                display: none !important;
            }
            #div-sidebarWrapper {
                transform: none !important;
            }
        }
    </style>
</head>
<body class="<?= $embedMode ? 'digital-id-embed' : '' ?>">
<?php if ($embedMode): ?>
    <main class="digital-id-main p-3">
        <section class="digital-id-card-shell p-3 p-md-4">
            <?php if ($errorMessage !== ''): ?>
                <div class="alert alert-warning mb-0"><?= htmlspecialchars($errorMessage) ?></div>
            <?php else: ?>
                <div id="digitalBarangayIdWrap" class="digital-id-loading">
                    <div class="spinner-border" role="status" aria-hidden="true"></div>
                    <div>Loading your digital Barangay ID…</div>
                </div>
            <?php endif; ?>
        </section>
    </main>
<?php else: ?>
    <div class="d-flex min-vh-100 digital-id-page">
        <?php include __DIR__ . '/../includes/resident_sidebar.php'; ?>
        <header id="mobile-header">
            <div class="d-flex align-items-center px-3 py-2 shadow-sm bg-white">
                <button class="btn" id="btn-burger" type="button" aria-label="Open sidebar">
                    <i class="fa-solid fa-bars fa-lg"></i>
                </button>
                <img src="<?= htmlspecialchars($baseUrl) ?>/Images/San_Jose_LOGO.jpg" alt="Logo" style="width:32px;height:32px">
                <span class="logo-name">Barangay San Jose</span>
            </div>
        </header>
        <main id="div-mainDisplay" class="flex-grow-1 p-4 p-md-5">
            <div class="digital-id-main">
                <section class="digital-id-card-shell p-4 p-md-5">
                    <?php if ($errorMessage !== ''): ?>
                        <div class="alert alert-warning mb-0"><?= htmlspecialchars($errorMessage) ?></div>
                    <?php else: ?>
                        <div class="d-grid gap-4">
                            <div class="d-grid gap-3">
                                <a href="<?= htmlspecialchars($documentRequestsUrl) ?>" class="text-decoration-none small fw-semibold" style="color:#8a4b00;">&lt; Back to Document Requests</a>
                                <div class="d-grid gap-2">
                                    <h1 class="digital-id-page-title">Digital Barangay ID</h1>
                                    <p class="digital-id-page-copy">This view mirrors the approved Barangay ID front and back template tied to your completed request.</p>
                                </div>
                                <div class="digital-id-meta">
                                    <span class="digital-id-meta-chip"><i class="fa-solid fa-id-card-clip"></i><?= htmlspecialchars($requestId) ?></span>
                                    <span class="digital-id-meta-chip"><i class="fa-solid fa-circle-check"></i>Completed Request</span>
                                </div>
                            </div>
                            <div id="digitalBarangayIdWrap" class="digital-id-loading">
                                <div class="spinner-border" role="status" aria-hidden="true"></div>
                                <div>Loading your digital Barangay ID…</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>
<?php endif; ?>

<?php if ($errorMessage === ''): ?>
    <script src="<?= htmlspecialchars($baseUrl) ?>/JS-Script-Files/Shared/barangayIdDigital.js?v=20260713-01"></script>
    <script>
        (() => {
            const wrap = document.getElementById('digitalBarangayIdWrap');
            if (!wrap || !window.BarangayIdDigital || typeof window.BarangayIdDigital.createState !== 'function') {
                return;
            }

            const row = <?= json_encode($serializedRow, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> || {};
            const payload = <?= json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> || {};
            const appBase = <?= json_encode($baseUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const profileImageEndpoint = <?= json_encode($profileImageEndpoint, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const initialProfileImageUrl = <?= json_encode($resolvedProfileImageUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const documentRequestsUrl = <?= json_encode($documentRequestsUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const embedMode = <?= $embedMode ? 'true' : 'false' ?>;
            const frontTemplateUrl = <?= json_encode($frontTemplateUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const backTemplateUrl = <?= json_encode($backTemplateUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const layoutConfig = <?= json_encode($barangayIdTemplateSettings['layout'] ?? dms_barangay_id_default_layout(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

            function closeDigitalIdView() {
                if (!embedMode) {
                    window.location.href = documentRequestsUrl;
                    return;
                }
                try {
                    const parentDoc = window.parent && window.parent.document;
                    const modalEl = parentDoc ? parentDoc.getElementById('paymentProofModal') : null;
                    if (modalEl) {
                        const modalApi = window.parent && window.parent.bootstrap && window.parent.bootstrap.Modal;
                        if (modalApi && typeof modalApi.getInstance === 'function') {
                            const instance = modalApi.getInstance(modalEl) || new modalApi(modalEl);
                            instance.hide();
                            return;
                        }
                        const dismissBtn = modalEl.querySelector('[data-bs-dismiss="modal"], .btn-close');
                        if (dismissBtn instanceof HTMLElement) {
                            dismissBtn.click();
                            return;
                        }
                    }
                } catch (_) {}
                if (window.history.length > 1) {
                    window.history.back();
                }
            }

            function buildDigitalViewer(state) {
                const scratch = document.createElement('div');
                scratch.innerHTML = window.BarangayIdDigital.render(state, {
                    showIntro: false,
                    frontLabel: 'Front Template',
                    backLabel: 'Back Template',
                });
                window.BarangayIdDigital.hydrate(scratch);

                const renderedCards = scratch.querySelectorAll('.barangay-id-card');
                const frontCard = renderedCards[0] || null;
                const backCard = renderedCards[1] || null;

                if (!frontCard || !backCard) {
                    wrap.className = '';
                    wrap.innerHTML = scratch.innerHTML;
                    return;
                }

                wrap.className = 'digital-id-viewer';
                wrap.innerHTML = `
                    <div class="digital-id-viewer__toolbar">
                        <div class="digital-id-viewer__badge-group">
                            <span class="digital-id-viewer__badge digital-id-viewer__badge--side" data-digital-side-badge>
                                <i class="fa-solid fa-id-card"></i>
                                Front of ID
                            </span>
                            <span class="digital-id-viewer__badge">
                                <i class="fa-solid fa-shield-halved"></i>
                                Resident Copy
                            </span>
                        </div>
                        <p class="digital-id-viewer__hint" data-digital-side-hint>
                            Front view of your completed Barangay ID.
                        </p>
                    </div>
                    <div class="digital-id-viewer__stage" data-digital-id-stage></div>
                    <div class="digital-id-viewer__footer">
                        <button type="button" class="digital-id-viewer__btn digital-id-viewer__btn--close" data-digital-close>
                            <i class="fa-solid fa-arrow-left"></i>
                            Close
                        </button>
                        <div class="digital-id-viewer__footer-actions">
                            <button type="button" class="digital-id-viewer__btn digital-id-viewer__btn--mode" data-digital-flip>
                                <i class="fa-solid fa-right-left"></i>
                                Show Back ID
                            </button>
                        </div>
                    </div>
                `;

                const stage = wrap.querySelector('[data-digital-id-stage]');
                const closeBtn = wrap.querySelector('[data-digital-close]');
                const flipBtn = wrap.querySelector('[data-digital-flip]');
                const sideBadge = wrap.querySelector('[data-digital-side-badge]');
                const sideHint = wrap.querySelector('[data-digital-side-hint]');
                let currentSide = 'front';

                const showCard = (card, animationClass = '') => {
                    stage.replaceChildren();
                    const frame = document.createElement('div');
                    frame.className = 'digital-id-viewer__card-stage';
                    if (animationClass) {
                        frame.classList.add(animationClass);
                    }
                    frame.appendChild(card.cloneNode(true));
                    stage.appendChild(frame);
                };

                const renderSide = () => {
                    const isBack = currentSide === 'back';
                    if (sideBadge) {
                        sideBadge.innerHTML = isBack
                            ? '<i class="fa-solid fa-address-card"></i>Back of ID'
                            : '<i class="fa-solid fa-id-card"></i>Front of ID';
                    }
                    if (sideHint) {
                        sideHint.textContent = isBack
                            ? 'Back view with emergency contact details and the verification QR code.'
                            : 'Front view of your completed Barangay ID.';
                    }
                    if (flipBtn) {
                        flipBtn.innerHTML = isBack
                            ? '<i class="fa-solid fa-right-left"></i>Show Front ID'
                            : '<i class="fa-solid fa-right-left"></i>Show Back ID';
                    }
                    showCard(
                        isBack ? backCard : frontCard,
                        isBack ? 'is-animating-forward' : 'is-animating-backward'
                    );
                };

                closeBtn?.addEventListener('click', closeDigitalIdView);
                flipBtn?.addEventListener('click', () => {
                    currentSide = currentSide === 'front' ? 'back' : 'front';
                    renderSide();
                });

                renderSide();
            }

            function renderDigitalCard(profileImageUrl = '') {
                const state = window.BarangayIdDigital.createState({
                    appBase,
                    row,
                    payload,
                    profileImageUrl,
                    frontTemplateUrl,
                    backTemplateUrl,
                    layoutConfig,
                    fallbackProfileImageUrl: `${appBase}/Images/Profile-Placeholder.png`,
                });
                buildDigitalViewer(state);
            }

            renderDigitalCard(initialProfileImageUrl);

            fetch(profileImageEndpoint, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            })
                .then((res) => res.ok ? res.json() : Promise.reject(new Error('Unable to load profile image.')))
                .then((data) => {
                    const profileImage = String(data?.profile_image || '').trim();
                    if (profileImage) {
                        renderDigitalCard(profileImage);
                    }
                })
                .catch(() => {});
        })();
    </script>
<?php endif; ?>
<?php if (!$embedMode): ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= htmlspecialchars($baseUrl) ?>/JS-Script-Files/Resident-End/profileSidebar.js"></script>
<?php endif; ?>
</body>
</html>
