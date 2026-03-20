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
        $decodedPayload = json_decode((string)($requestRow['request_details'] ?? '{}'), true);
        $payload = is_array($decodedPayload) ? $decodedPayload : [];

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
    }
}

$downloadUrl = $requestId !== ''
    ? appUrl('/PhpFiles/Resident-End/documentRequestWorkflow.php?action=download_issued&request_id=' . rawurlencode($requestId))
    : '#';
$pdfViewUrl = $requestId !== ''
    ? appUrl('/PhpFiles/Resident-End/documentRequestWorkflow.php?action=view_issued&request_id=' . rawurlencode($requestId))
    : '#';
$documentRequestsUrl = appUrl('/Resident-End/document_requests.php');
$profileImageEndpoint = appUrl('/PhpFiles/Resident-End/getVerifiedProfileImage.php');
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
        .digital-id-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .digital-id-actions .btn {
            border-radius: 999px;
            font-weight: 700;
            padding-inline: 16px;
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
        .digital-id-viewer__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(221, 226, 235, 0.95);
        }
        .digital-id-viewer__status {
            display: flex;
            align-items: center;
            gap: 16px;
            min-width: 0;
        }
        .digital-id-viewer__status-icon {
            width: 58px;
            height: 58px;
            border-radius: 18px;
            background: linear-gradient(180deg, #ecf4ff 0%, #f8fbff 100%);
            border: 1px solid rgba(76, 108, 193, 0.2);
            color: #1e4eb6;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.55rem;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.75);
        }
        .digital-id-viewer__status-text {
            min-width: 0;
        }
        .digital-id-viewer__status-title {
            margin: 0;
            font-size: clamp(1.35rem, 2.7vw, 2.35rem);
            line-height: 1.05;
            font-weight: 800;
            color: #1c4fb7;
        }
        .digital-id-viewer__status-title span {
            color: #6bb12f;
        }
        .digital-id-viewer__status-copy {
            margin: 6px 0 0;
            color: #6c7280;
            font-size: 0.95rem;
        }
        .digital-id-viewer__chips {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .digital-id-viewer__chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: #f4f8ff;
            border: 1px solid rgba(89, 123, 209, 0.2);
            color: #25417d;
            font-weight: 700;
            font-size: 0.9rem;
        }
        .digital-id-viewer__stage {
            position: relative;
            overflow: hidden;
            border-radius: 26px;
            border: 1px solid rgba(198, 214, 245, 0.8);
            background:
                radial-gradient(circle at top left, rgba(227, 237, 255, 0.95), transparent 34%),
                linear-gradient(180deg, #fafdff 0%, #edf4ff 100%);
            padding: 24px;
            min-height: 340px;
            display: grid;
            place-items: center;
        }
        .digital-id-viewer__card-stage {
            width: min(100%, 1160px);
            margin: 0 auto;
        }
        .digital-id-viewer__card-stage .barangay-id-card {
            width: 100%;
            margin: 0;
            border-radius: 28px;
            box-shadow: 0 24px 60px rgba(28, 56, 112, 0.16);
        }
        .digital-id-viewer__card-stage .barangay-id-card__bg {
            border-radius: 28px;
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
            border-radius: 16px;
            font-weight: 800;
            padding: 12px 18px;
            border: 0;
            transition: transform 120ms ease, box-shadow 120ms ease, opacity 120ms ease;
        }
        .digital-id-viewer__btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(28, 56, 112, 0.16);
        }
        .digital-id-viewer__btn--close {
            background: linear-gradient(180deg, #f51f24 0%, #d91318 100%);
            color: #fff;
        }
        .digital-id-viewer__btn--mode {
            background: linear-gradient(180deg, #2553be 0%, #143d9d 100%);
            color: #fff;
            min-width: 152px;
        }
        .digital-id-viewer__btn--mode.is-active {
            background: linear-gradient(180deg, #2d67ea 0%, #1e4fc1 100%);
            box-shadow: 0 16px 30px rgba(37, 83, 190, 0.24);
        }
        .digital-id-viewer__btn--mode.is-inactive {
            opacity: 0.82;
        }
        @media (max-width: 767.98px) {
            .digital-id-card-shell {
                border-radius: 20px;
            }
            .digital-id-viewer__status {
                align-items: flex-start;
            }
            .digital-id-viewer__stage {
                padding: 14px;
                border-radius: 20px;
                min-height: 240px;
            }
            .digital-id-viewer__card-stage .barangay-id-card,
            .digital-id-viewer__card-stage .barangay-id-card__bg {
                border-radius: 20px;
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
    <div class="d-flex digital-id-page">
        <?php include __DIR__ . '/../includes/resident_sidebar.php'; ?>
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
                                <?php
                                $barangayIdNavActive = 'digital';
                                $barangayIdNavRequestId = $requestId;
                                include __DIR__ . '/includes/barangay_id_nav.php';
                                ?>
                                <div class="digital-id-meta">
                                    <span class="digital-id-meta-chip"><i class="fa-solid fa-id-card-clip"></i><?= htmlspecialchars($requestId) ?></span>
                                    <span class="digital-id-meta-chip"><i class="fa-solid fa-circle-check"></i>Completed Request</span>
                                </div>
                                <div class="digital-id-actions">
                                    <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($pdfViewUrl) ?>" target="_blank" rel="noopener">
                                        <i class="fa-regular fa-eye me-2"></i>Open PDF
                                    </a>
                                    <a class="btn btn-primary" href="<?= htmlspecialchars($downloadUrl) ?>">
                                        <i class="fa-solid fa-download me-2"></i>Download PDF
                                    </a>
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
    <script src="<?= htmlspecialchars($baseUrl) ?>/JS-Script-Files/Shared/barangayIdDigital.js?v=20260320-26"></script>
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
                    <div class="digital-id-viewer__header">
                        <div class="digital-id-viewer__status">
                            <span class="digital-id-viewer__status-icon"><i class="fa-regular fa-id-card"></i></span>
                            <div class="digital-id-viewer__status-text">
                                <p class="digital-id-viewer__status-title">Digital ID is <span>Active</span></p>
                                <p class="digital-id-viewer__status-copy">This resident-side view mirrors the released Barangay ID and keeps the front, back, and QR verification handy.</p>
                            </div>
                        </div>
                        <div class="digital-id-viewer__chips">
                            <span class="digital-id-viewer__chip"><i class="fa-solid fa-id-card-clip"></i>${state.cardNumber || '-'}</span>
                            <span class="digital-id-viewer__chip"><i class="fa-solid fa-circle-check"></i>Ready for verification</span>
                        </div>
                    </div>
                    <div class="digital-id-viewer__stage" data-digital-id-stage></div>
                    <div class="digital-id-viewer__footer">
                        <button type="button" class="digital-id-viewer__btn digital-id-viewer__btn--close" data-digital-close>Close</button>
                        <div class="digital-id-viewer__footer-actions">
                            <button type="button" class="digital-id-viewer__btn digital-id-viewer__btn--mode" data-digital-mode="front"><i class="fa-regular fa-id-card me-2"></i>Frontside</button>
                            <button type="button" class="digital-id-viewer__btn digital-id-viewer__btn--mode" data-digital-mode="back"><i class="fa-solid fa-right-left me-2"></i>Backside</button>
                            <button type="button" class="digital-id-viewer__btn digital-id-viewer__btn--mode" data-digital-mode="qr"><i class="fa-solid fa-qrcode me-2"></i>QR Code</button>
                        </div>
                    </div>
                `;

                const stage = wrap.querySelector('[data-digital-id-stage]');
                const modeButtons = Array.from(wrap.querySelectorAll('[data-digital-mode]'));
                const closeBtn = wrap.querySelector('[data-digital-close]');

                const setButtonState = (activeMode) => {
                    modeButtons.forEach((button) => {
                        const isActive = String(button.getAttribute('data-digital-mode') || '') === activeMode;
                        button.classList.toggle('is-active', isActive);
                        button.classList.toggle('is-inactive', !isActive);
                    });
                };

                const showCard = (card) => {
                    stage.replaceChildren();
                    const frame = document.createElement('div');
                    frame.className = 'digital-id-viewer__card-stage';
                    frame.appendChild(card.cloneNode(true));
                    stage.appendChild(frame);
                };

                const showQr = () => {
                    stage.replaceChildren();
                    const qrStage = document.createElement('div');
                    qrStage.className = 'digital-id-viewer__qr-stage';
                    const qrFrame = document.createElement('div');
                    qrFrame.className = 'digital-id-viewer__qr-frame';

                    if (state.qrUrl) {
                        const qrImage = document.createElement('img');
                        qrImage.src = state.qrUrl;
                        qrImage.alt = 'Barangay ID QR code';
                        qrFrame.appendChild(qrImage);
                    } else {
                        const placeholder = document.createElement('div');
                        placeholder.className = 'digital-id-viewer__qr-placeholder';
                        placeholder.textContent = 'QR code is not available yet.';
                        qrFrame.appendChild(placeholder);
                    }

                    const caption = document.createElement('p');
                    caption.className = 'digital-id-viewer__qr-caption';
                    caption.textContent = state.qrUrl
                        ? 'Use this QR code when staff or offices need to verify the released Barangay ID.'
                        : 'The released Barangay ID QR will appear here once it is available for verification.';

                    qrStage.appendChild(qrFrame);
                    qrStage.appendChild(caption);
                    stage.appendChild(qrStage);
                };

                const renderMode = (mode) => {
                    setButtonState(mode);
                    if (mode === 'back') {
                        showCard(backCard);
                        return;
                    }
                    if (mode === 'qr') {
                        showQr();
                        return;
                    }
                    showCard(frontCard);
                };

                closeBtn?.addEventListener('click', closeDigitalIdView);
                modeButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        renderMode(String(button.getAttribute('data-digital-mode') || 'front'));
                    });
                });

                renderMode('front');
            }

            function renderDigitalCard(profileImageUrl = '') {
                const state = window.BarangayIdDigital.createState({
                    appBase,
                    row,
                    payload,
                    profileImageUrl,
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
</body>
</html>
