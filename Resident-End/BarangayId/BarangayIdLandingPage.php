<?php
$allowUnregistered = false;
require_once __DIR__ . "/../includes/resident_access_guard.php";

$frontTemplateRelative = 'Resident-End/Certificates/BarangayID/FRONT_EMPTY.png';
$backTemplateRelative = 'Resident-End/Certificates/BarangayID/BACK_EMPTY.png';
$frontTemplateDiskPath = realpath(__DIR__ . '/../Certificates/BarangayID/FRONT_EMPTY.png');
$backTemplateDiskPath = realpath(__DIR__ . '/../Certificates/BarangayID/BACK_EMPTY.png');
$frontTemplateUrl = appUrl($frontTemplateRelative) . '?v=' . (string)(($frontTemplateDiskPath && is_file($frontTemplateDiskPath)) ? @filemtime($frontTemplateDiskPath) : time());
$backTemplateUrl = appUrl($backTemplateRelative) . '?v=' . (string)(($backTemplateDiskPath && is_file($backTemplateDiskPath)) ? @filemtime($backTemplateDiskPath) : time());
$latestDigitalIdRequestId = '';
if (isset($conn) && $conn instanceof mysqli && isset($userId)) {
    $sql = "
        SELECT request_id
        FROM documentrequesttbl
        WHERE resident_user_id = ?
          AND LOWER(TRIM(COALESCE(document_type, ''))) = 'barangay id'
          AND LOWER(TRIM(COALESCE(stage, ''))) = 'completed'
        ORDER BY COALESCE(release_timestamp, completed_at, ready_at, submitted_at, request_timestamp) DESC, request_id DESC
        LIMIT 1
    ";
    $stmtDigitalId = $conn->prepare($sql);
    if ($stmtDigitalId) {
        $stmtDigitalId->bind_param('s', $userId);
        $stmtDigitalId->execute();
        $stmtDigitalId->bind_result($resolvedRequestId);
        if ($stmtDigitalId->fetch() && is_string($resolvedRequestId)) {
            $latestDigitalIdRequestId = trim($resolvedRequestId);
        }
        $stmtDigitalId->close();
    }
}
$digitalIdViewUrl = $latestDigitalIdRequestId !== ''
    ? appUrl('Resident-End/BarangayId/DigitalId.php?request_id=' . rawurlencode($latestDigitalIdRequestId))
    : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../../Images/favicon_sanjose.png?v=20260211">
    <title>Barangay ID Application</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <link rel="stylesheet" href="../../CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/ApplicationLandingPage.css?v=20260228-3">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/barangayIdNav.css">
    <style>
        body {
            background: #fffdfb;
        }
        #div-mainDisplay {
            background: #ffffff !important;
        }
        .page-shell {
            max-width: 1100px;
            margin: 0 auto;
        }
        .page-title {
            font-size: 2.4rem;
            font-weight: 700;
        }
        .page-subtitle {
            font-size: 1.3rem;
            font-weight: 600;
            color: #1f1f1f;
        }
        .info-card {
            background: #fff7ef;
            border: 1px solid #f2d9c2;
            border-radius: 16px;
            padding: 20px 24px;
        }
        .id-overview-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
            gap: 24px;
            align-items: start;
        }
        .id-preview-panel {
            background: linear-gradient(180deg, #fffaf4 0%, #fff2e2 100%);
            border: 1px solid #f2d9c2;
            border-radius: 20px;
            padding: 22px;
            box-shadow: 0 14px 30px rgba(138, 75, 0, 0.08);
        }
        .id-preview-stack {
            display: grid;
            gap: 18px;
        }
        .id-preview-card {
            display: grid;
            gap: 10px;
        }
        .id-preview-label {
            margin: 0;
            font-size: 0.92rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #8a4b00;
        }
        .info-list {
            padding-left: 1.2rem;
            margin-bottom: 0;
        }
        .apply-section {
            padding-top: 12px;
        }
        .apply-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .apply-actions .btn {
            min-width: 210px;
            min-height: 58px;
            padding: 0.9rem 1.5rem !important;
            border-radius: 14px !important;
            font-size: 1rem !important;
            font-weight: 800 !important;
            line-height: 1.1 !important;
            box-shadow: 0 14px 28px rgba(138, 75, 0, 0.12);
            transition: transform 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease, color 0.18s ease, border-color 0.18s ease;
        }
        .apply-actions .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 34px rgba(138, 75, 0, 0.16);
        }
        .apply-btn {
            min-width: 210px;
        }
        .digital-id-btn {
            min-width: 210px;
            background: #fff4e7;
            color: #8a4b00;
            border: 1px solid #f2c28a;
            box-shadow: 0 14px 28px rgba(138, 75, 0, 0.1);
        }
        .digital-id-btn:hover {
            background: #fc8d3d;
            border-color: #fc8d3d;
            color: #fff;
        }
        .apply-note {
            margin-top: 10px;
            font-size: 0.92rem;
            font-style: italic;
            color: #6b7280;
        }
        .digital-id-note {
            margin-top: 10px;
            font-size: 0.92rem;
            font-weight: 600;
            color: #8a4b00;
        }
        .id-sample-img {
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.18);
        }
        @media (max-width: 991.98px) {
            .id-overview-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="d-flex min-vh-100">
        <?php include __DIR__ . '/../includes/resident_sidebar.php'; ?>

        <main id="div-mainDisplay" class="main-content single-service-page flex-grow-1 p-4 p-md-5">
            <div class="page-shell">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <img src="../../Icons/Dashboard/brgyid.png" class="certificate-icon" alt="Barangay ID Service" style="height: 52px; margin-bottom: 0;">
                    <div>
                        <h1 class="page-title mb-1">Barangay ID Application</h1>
                    </div>
                </div>
                <p class="page-description mb-4">
                    Welcome to the Barangay San Jose Online Barangay ID Application. Select the service below to proceed with your Barangay ID request.
                </p>

                <div class="id-overview-layout mt-4">
                    <section class="id-preview-panel">
                        <h2 class="page-subtitle mb-3">Barangay ID Preview</h2>
                        <div class="id-preview-stack">
                            <div class="id-preview-card">
                                <p class="id-preview-label">Front ID</p>
                                <img src="<?= htmlspecialchars($frontTemplateUrl) ?>" class="img-fluid rounded id-sample-img" alt="Barangay ID Template - Front">
                            </div>
                            <div class="id-preview-card">
                                <p class="id-preview-label">Back ID</p>
                                <img src="<?= htmlspecialchars($backTemplateUrl) ?>" class="img-fluid rounded id-sample-img" alt="Barangay ID Template - Back">
                            </div>
                        </div>
                    </section>

                    <section class="info-card h-100">
                        <h2 class="page-subtitle mb-2">Where Can You Use Barangay ID</h2>
                        <p class="page-description mb-2">Your Barangay ID can be presented for:</p>
                        <ul class="page-description info-list">
                            <li>Verification of residence within Barangay San Jose</li>
                            <li>Transactions and requests at the barangay hall (certificates, clearances, permits)</li>
                            <li>Access to barangay programs, benefits, and community services</li>
                            <li>Local identification for school or clinic records and other community requirements</li>
                            <li>Supporting ID for local businesses and neighborhood associations</li>
                            <li>Supports Digital ID</li>
                        </ul>
                        <p class="page-description mt-3 mb-0">
                            Note: Acceptance may vary by agency or establishment. Please bring additional valid IDs if required.
                        </p>
                    </section>
                </div>

                <div class="text-center apply-section mt-4">
                    <div class="apply-actions">
                        <button class="btn apply-btn" type="button" data-apply-href="<?= htmlspecialchars(appUrl('Resident-End/BarangayId/BarangayIdForm.php'), ENT_QUOTES, 'UTF-8') ?>">Apply Now</button>
                        <?php if ($digitalIdViewUrl !== ''): ?>
                            <button class="btn digital-id-btn" type="button" onclick="location.href='<?= htmlspecialchars($digitalIdViewUrl) ?>'">View Digital ID</button>
                        <?php endif; ?>
                    </div>
                    <?php if ($digitalIdViewUrl !== ''): ?>
                        <p class="digital-id-note mb-0">Your latest released Barangay ID is already available online.</p>
                    <?php endif; ?>
                    <p class="apply-note mb-0">Renewal is free every 2 years. Renewal of lost Barangay ID will cost Php50.00</p>
                </div>
            </div>
        </main>
    </div>
    <?php include __DIR__ . '/../includes/document_issuance_verification_modal.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const isResidentVerified = <?= $isResidentVerified ? 'true' : 'false' ?>;
        const verificationRequiredModalEl = document.getElementById('residentVerificationRequiredModal');
        const verificationRequiredModal = (!isResidentVerified && verificationRequiredModalEl && window.bootstrap?.Modal)
            ? bootstrap.Modal.getOrCreateInstance(verificationRequiredModalEl)
            : null;

        document.querySelectorAll('[data-apply-href]').forEach((button) => {
            button.addEventListener('click', () => {
                const applyHref = button.getAttribute('data-apply-href') || '';
                if (applyHref === '') {
                    return;
                }

                if (!isResidentVerified) {
                    verificationRequiredModal?.show();
                    return;
                }

                window.location.href = applyHref;
            });
        });
    </script>
</body>

</html>
