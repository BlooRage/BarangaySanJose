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

$embedMode = isset($_GET['embed']) && (string)$_GET['embed'] === '1';
$requestId = trim((string)($_GET['request_id'] ?? ''));
$userId = (string)($_SESSION['user_id'] ?? '');
$errorMessage = '';
$requestRow = null;
$payload = [];

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
        @media (max-width: 767.98px) {
            .digital-id-card-shell {
                border-radius: 20px;
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
    <script src="<?= htmlspecialchars($baseUrl) ?>/JS-Script-Files/Shared/barangayIdDigital.js?v=20260318-01"></script>
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
            const embedMode = <?= $embedMode ? 'true' : 'false' ?>;

            function renderDigitalCard(profileImageUrl = '') {
                const state = window.BarangayIdDigital.createState({
                    appBase,
                    row,
                    payload,
                    profileImageUrl,
                    fallbackProfileImageUrl: `${appBase}/Images/Profile-Placeholder.png`,
                });
                wrap.className = '';
                wrap.innerHTML = window.BarangayIdDigital.render(state, {
                    eyebrow: embedMode ? 'Digital Barangay ID' : 'Resident Digital Barangay ID',
                    helper: embedMode ? '' : 'Use the PDF buttons above if you need the printable issued version.',
                    showIntro: !embedMode,
                    frontLabel: 'Front Template',
                    backLabel: 'Back Template',
                });
            }

            renderDigitalCard('');

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
