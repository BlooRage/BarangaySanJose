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
        :root {
            --bid-page-accent: #de710c;
            --bid-page-accent-strong: #b85a00;
            --bid-page-accent-soft: #fff2df;
            --bid-page-border: #f1d4b5;
            --bid-page-ink: #2b2118;
            --bid-page-muted: #6d6257;
            --bid-page-panel: rgba(255, 250, 244, 0.94);
        }
        body {
            background:
                radial-gradient(circle at top left, rgba(255, 221, 187, 0.5), transparent 32%),
                radial-gradient(circle at 100% 0%, rgba(255, 239, 214, 0.85), transparent 26%),
                linear-gradient(180deg, #fffdfb 0%, #fff8ef 100%);
        }
        #div-mainDisplay {
            background: transparent !important;
        }
        .page-shell {
            max-width: 1240px;
            margin: 0 auto;
            display: grid;
            gap: 28px;
        }
        .barangay-id-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(280px, 0.6fr);
            gap: 22px;
            align-items: stretch;
            padding: clamp(22px, 3vw, 34px);
            border-radius: 30px;
            border: 1px solid rgba(241, 212, 181, 0.9);
            background:
                linear-gradient(135deg, rgba(255, 251, 246, 0.98), rgba(255, 244, 229, 0.92)),
                linear-gradient(180deg, #ffffff 0%, #fff6ea 100%);
            box-shadow: 0 22px 48px rgba(145, 87, 24, 0.09);
        }
        .barangay-id-hero__copy {
            display: grid;
            gap: 16px;
        }
        .barangay-id-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            width: fit-content;
            padding: 10px 16px;
            border-radius: 999px;
            background: var(--bid-page-accent-soft);
            border: 1px solid #f4c994;
            color: var(--bid-page-accent-strong);
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .barangay-id-title-row {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .barangay-id-title-mark {
            width: 68px;
            height: 68px;
            display: grid;
            place-items: center;
            border-radius: 20px;
            background: #fff;
            border: 1px solid rgba(241, 212, 181, 0.9);
            box-shadow: 0 14px 30px rgba(145, 87, 24, 0.1);
        }
        .barangay-id-title-mark img {
            width: 38px;
            height: 38px;
            object-fit: contain;
        }
        .page-title {
            font-size: clamp(2.3rem, 4vw, 4rem);
            font-weight: 700;
            margin: 0;
            line-height: 0.96;
        }
        .barangay-id-hero__copy .page-description {
            margin: 0 !important;
            max-width: 56rem;
            font-size: 1.08rem;
            line-height: 1.75;
            color: var(--bid-page-muted);
        }
        .barangay-id-chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        .barangay-id-chip {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 0.78rem 1rem;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(241, 212, 181, 0.92);
            color: #6a4521;
            font-weight: 700;
            box-shadow: 0 10px 24px rgba(145, 87, 24, 0.06);
        }
        .barangay-id-hero__aside {
            display: grid;
            gap: 14px;
            padding: 22px;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.82);
            border: 1px solid rgba(241, 212, 181, 0.92);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95);
        }
        .barangay-id-hero__aside-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            padding: 8px 12px;
            border-radius: 999px;
            background: #fff6e7;
            color: #8a4b00;
            border: 1px solid #f4cf9f;
            font-size: 0.82rem;
            font-weight: 800;
        }
        .barangay-id-hero__aside-title {
            margin: 0;
            font-size: 1.5rem;
            line-height: 1.1;
            color: var(--bid-page-ink);
        }
        .barangay-id-hero__aside-copy {
            margin: 0;
            color: var(--bid-page-muted);
            line-height: 1.65;
            font-size: 0.98rem;
        }
        .barangay-id-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(320px, 0.92fr);
            gap: 24px;
            align-items: start;
        }
        .barangay-id-panel {
            background: var(--bid-page-panel);
            border: 1px solid var(--bid-page-border);
            border-radius: 28px;
            padding: clamp(22px, 2.8vw, 30px);
            box-shadow: 0 20px 44px rgba(145, 87, 24, 0.08);
        }
        .barangay-id-panel__head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }
        .panel-kicker {
            margin: 0 0 6px;
            font-size: 0.8rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--bid-page-accent-strong);
        }
        .page-subtitle {
            font-size: clamp(1.55rem, 2.4vw, 2.1rem);
            font-weight: 700;
            color: var(--bid-page-ink);
            margin: 0;
            line-height: 1.08;
        }
        .panel-copy {
            margin: 10px 0 0;
            color: var(--bid-page-muted);
            line-height: 1.65;
            font-size: 0.98rem;
        }
        .preview-switch {
            display: grid;
            grid-auto-flow: column;
            gap: 10px;
            padding: 8px;
            border-radius: 18px;
            background: #fff9f1;
            border: 1px solid rgba(241, 212, 181, 0.92);
        }
        .preview-switch__btn {
            border: 0;
            background: transparent;
            color: #7a5a33;
            padding: 0.72rem 1rem;
            border-radius: 14px;
            font-size: 0.92rem;
            font-weight: 800;
            transition: background-color 140ms ease, color 140ms ease, box-shadow 140ms ease, transform 140ms ease;
        }
        .preview-switch__btn:hover {
            color: var(--bid-page-accent-strong);
            transform: translateY(-1px);
        }
        .preview-switch__btn.is-active {
            background: linear-gradient(135deg, #fe993c 0%, #de710c 100%);
            color: #fff;
            box-shadow: 0 12px 22px rgba(222, 113, 12, 0.2);
        }
        .preview-stage {
            position: relative;
            aspect-ratio: 856 / 541;
            border-radius: 30px;
            overflow: hidden;
            background:
                radial-gradient(circle at top left, rgba(255, 220, 171, 0.34), transparent 32%),
                linear-gradient(180deg, #fffaf2 0%, #fff4e6 100%);
            border: 1px solid rgba(241, 212, 181, 0.96);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9), 0 16px 34px rgba(145, 87, 24, 0.1);
        }
        .preview-stage::after {
            content: '';
            position: absolute;
            inset: 16px;
            border-radius: 22px;
            border: 1px dashed rgba(222, 113, 12, 0.16);
            pointer-events: none;
        }
        .preview-stage__img {
            position: absolute;
            inset: 18px;
            width: calc(100% - 36px);
            height: calc(100% - 36px);
            object-fit: contain;
            opacity: 0;
            transform: scale(0.985) translateY(8px);
            transition: opacity 220ms ease, transform 220ms ease;
            pointer-events: none;
            filter: drop-shadow(0 14px 30px rgba(145, 87, 24, 0.14));
        }
        .preview-stage__img.is-active {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
        .preview-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            margin-top: 16px;
        }
        .preview-footer__note {
            margin: 0;
            color: var(--bid-page-muted);
            font-size: 0.95rem;
            line-height: 1.55;
        }
        .preview-footer__chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.78rem 1rem;
            border-radius: 16px;
            background: #fff;
            border: 1px solid rgba(241, 212, 181, 0.92);
            color: #73481f;
            font-weight: 700;
            box-shadow: 0 10px 22px rgba(145, 87, 24, 0.08);
        }
        .use-list {
            display: grid;
            gap: 14px;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .use-list__item {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 14px;
            align-items: start;
        }
        .use-list__icon {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: #fff6e7;
            border: 1px solid #f4cf9f;
            color: var(--bid-page-accent);
            font-size: 1rem;
            box-shadow: 0 10px 20px rgba(145, 87, 24, 0.08);
        }
        .use-list__item strong {
            display: block;
            font-size: 1rem;
            color: var(--bid-page-ink);
        }
        .use-list__item span {
            display: block;
            margin-top: 3px;
            color: var(--bid-page-muted);
            line-height: 1.55;
        }
        .info-callout {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 12px;
            align-items: start;
            margin-top: 20px;
            padding: 18px 18px 18px 16px;
            border-radius: 20px;
            background: #fff;
            border: 1px solid rgba(241, 212, 181, 0.96);
            box-shadow: 0 12px 24px rgba(145, 87, 24, 0.06);
        }
        .info-callout__icon {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: #fff4e7;
            color: var(--bid-page-accent-strong);
            border: 1px solid #f4cf9f;
        }
        .info-callout p {
            margin: 0;
            color: var(--bid-page-muted);
            line-height: 1.65;
        }
        .action-panel {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 22px;
            align-items: center;
            padding: clamp(22px, 2.6vw, 30px);
            border-radius: 28px;
            border: 1px solid rgba(241, 212, 181, 0.92);
            background: linear-gradient(135deg, rgba(255, 248, 238, 0.98), rgba(255, 242, 223, 0.9));
            box-shadow: 0 20px 44px rgba(145, 87, 24, 0.08);
        }
        .action-panel__copy {
            display: grid;
            gap: 10px;
        }
        .action-panel__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(241, 212, 181, 0.96);
            color: var(--bid-page-accent-strong);
            font-size: 0.82rem;
            font-weight: 800;
        }
        .action-panel__title {
            margin: 0;
            font-size: clamp(1.5rem, 2.5vw, 2rem);
            line-height: 1.08;
            color: var(--bid-page-ink);
        }
        .action-panel__body {
            margin: 0;
            color: var(--bid-page-muted);
            line-height: 1.68;
            max-width: 52rem;
        }
        .action-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .action-meta__item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.74rem 0.95rem;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.88);
            border: 1px solid rgba(241, 212, 181, 0.94);
            color: #6a4521;
            font-weight: 700;
        }
        .apply-section {
            display: grid;
            gap: 12px;
        }
        .apply-actions .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            min-width: 224px;
            height: 58px;
            padding: 0.9rem 1.5rem !important;
            border-radius: 14px !important;
            font-size: 1rem !important;
            font-weight: 800 !important;
            line-height: 1.1 !important;
            margin: 0;
            box-shadow: 0 16px 30px rgba(138, 75, 0, 0.12);
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
            margin: 0;
            font-size: 0.95rem;
            font-style: italic;
            color: var(--bid-page-muted);
        }
        .digital-id-note {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--bid-page-accent-strong);
        }
        @media (max-width: 991.98px) {
            .barangay-id-hero,
            .barangay-id-layout,
            .action-panel {
                grid-template-columns: 1fr;
            }
            .action-panel {
                align-items: start;
            }
            .apply-actions {
                width: 100%;
                justify-content: stretch;
            }
            .apply-actions .btn {
                width: 100%;
            }
        }
        @media (max-width: 767.98px) {
            .barangay-id-title-row {
                align-items: flex-start;
            }
            .barangay-id-title-mark {
                width: 58px;
                height: 58px;
                border-radius: 18px;
            }
            .preview-switch {
                width: 100%;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                grid-auto-flow: row;
            }
            .preview-switch__btn {
                width: 100%;
            }
            .preview-stage::after {
                inset: 10px;
                border-radius: 18px;
            }
            .preview-stage__img {
                inset: 12px;
                width: calc(100% - 24px);
                height: calc(100% - 24px);
            }
        }
    </style>
</head>

<body>
    <div class="d-flex min-vh-100">
        <?php include __DIR__ . '/../includes/resident_sidebar.php'; ?>

        <main id="div-mainDisplay" class="main-content single-service-page flex-grow-1 p-4 p-md-5">
            <div class="page-shell">
                <section class="barangay-id-hero">
                    <div class="barangay-id-hero__copy">
                        <span class="barangay-id-eyebrow">
                            <i class="fa-solid fa-id-card"></i>
                            Resident Service
                        </span>
                        <div class="barangay-id-title-row">
                            <div class="barangay-id-title-mark">
                                <img src="../../Icons/Dashboard/brgyid.png" alt="Barangay ID Service">
                            </div>
                            <div>
                                <h1 class="page-title">Barangay ID Application</h1>
                            </div>
                        </div>
                        <p class="page-description">
                            Request your physical Barangay ID, review the official card layout before applying, and open your digital copy online once your latest request is completed.
                        </p>
                        <div class="barangay-id-chip-row">
                            <span class="barangay-id-chip"><i class="fa-solid fa-building-columns"></i>Official barangay-issued ID</span>
                            <span class="barangay-id-chip"><i class="fa-solid fa-mobile-screen-button"></i>Digital ID ready after completion</span>
                            <span class="barangay-id-chip"><i class="fa-solid fa-arrows-rotate"></i>Free renewal every 2 years</span>
                        </div>
                    </div>
                    <aside class="barangay-id-hero__aside">
                        <span class="barangay-id-hero__aside-badge">
                            <i class="fa-solid fa-circle-check"></i>
                            <?= $digitalIdViewUrl !== '' ? 'Digital copy available' : 'Application ready' ?>
                        </span>
                        <h2 class="barangay-id-hero__aside-title">
                            <?= $digitalIdViewUrl !== '' ? 'Your latest completed Barangay ID can already be viewed online.' : 'Preview the official design, then continue with your request.' ?>
                        </h2>
                        <p class="barangay-id-hero__aside-copy">
                            <?= $digitalIdViewUrl !== ''
                                ? 'You can open the digital copy instantly or submit another request if you need a renewal or replacement.'
                                : 'Use the preview as your guide before filing a new request. Your digital copy will appear here once your latest request is completed.' ?>
                        </p>
                    </aside>
                </section>

                <div class="barangay-id-layout">
                    <section class="barangay-id-panel">
                        <div class="barangay-id-panel__head">
                            <div>
                                <p class="panel-kicker">Template Preview</p>
                                <h2 class="page-subtitle">See the front and back of the official Barangay ID</h2>
                                <p class="panel-copy">Use the switcher to review the card layout before you apply. This is the same design used for approved IDs.</p>
                            </div>
                            <div class="preview-switch" role="tablist" aria-label="Barangay ID preview sides">
                                <button type="button" class="preview-switch__btn is-active" data-preview-target="front" aria-pressed="true">Front ID</button>
                                <button type="button" class="preview-switch__btn" data-preview-target="back" aria-pressed="false">Back ID</button>
                            </div>
                        </div>
                        <div class="preview-stage">
                            <img src="<?= htmlspecialchars($frontTemplateUrl) ?>" class="preview-stage__img is-active" data-preview-image="front" alt="Barangay ID template front">
                            <img src="<?= htmlspecialchars($backTemplateUrl) ?>" class="preview-stage__img" data-preview-image="back" alt="Barangay ID template back">
                        </div>
                        <div class="preview-footer">
                            <p class="preview-footer__note">Template preview only. Resident details, photo, and QR code appear once the request is processed and approved.</p>
                            <span class="preview-footer__chip"><i class="fa-solid fa-eye"></i>Front and back preview</span>
                        </div>
                    </section>

                    <section class="barangay-id-panel h-100">
                        <div class="barangay-id-panel__head">
                            <div>
                                <p class="panel-kicker">Uses and Benefits</p>
                                <h2 class="page-subtitle">Where you can use your Barangay ID</h2>
                                <p class="panel-copy">Bring it for local verification, barangay transactions, and community-related requirements.</p>
                            </div>
                        </div>
                        <ul class="use-list">
                            <li class="use-list__item">
                                <span class="use-list__icon"><i class="fa-solid fa-location-dot"></i></span>
                                <div>
                                    <strong>Proof of residence</strong>
                                    <span>Confirm that you are a resident of Barangay San Jose for local transactions and verification.</span>
                                </div>
                            </li>
                            <li class="use-list__item">
                                <span class="use-list__icon"><i class="fa-solid fa-file-signature"></i></span>
                                <div>
                                    <strong>Barangay hall requests</strong>
                                    <span>Use it when requesting certificates, clearances, permits, and other barangay-issued documents.</span>
                                </div>
                            </li>
                            <li class="use-list__item">
                                <span class="use-list__icon"><i class="fa-solid fa-people-group"></i></span>
                                <div>
                                    <strong>Programs and community services</strong>
                                    <span>Present it when accessing barangay programs, benefits, and resident-facing services.</span>
                                </div>
                            </li>
                            <li class="use-list__item">
                                <span class="use-list__icon"><i class="fa-solid fa-school"></i></span>
                                <div>
                                    <strong>School, clinic, and neighborhood records</strong>
                                    <span>Serve as a local identification document for nearby institutions and associations that request barangay residency proof.</span>
                                </div>
                            </li>
                        </ul>
                        <div class="info-callout">
                            <span class="info-callout__icon"><i class="fa-solid fa-circle-info"></i></span>
                            <p>Acceptance may still vary by agency or establishment. Bring additional valid IDs whenever a transaction asks for secondary proof of identity.</p>
                        </div>
                    </section>
                </div>

                <section class="action-panel">
                    <div class="action-panel__copy">
                        <span class="action-panel__eyebrow">
                            <i class="fa-solid fa-bolt"></i>
                            Next Step
                        </span>
                        <h2 class="action-panel__title">Apply for a new ID or open your digital copy</h2>
                        <p class="action-panel__body">
                            Start a new Barangay ID request any time. If your latest request is already completed, you can open the digital version immediately from here.
                        </p>
                        <div class="action-meta">
                            <?php if ($digitalIdViewUrl !== ''): ?>
                                <span class="action-meta__item"><i class="fa-solid fa-circle-check"></i>Your latest released Barangay ID is already available online.</span>
                            <?php endif; ?>
                            <span class="action-meta__item"><i class="fa-solid fa-arrows-rotate"></i>Renewal is free every 2 years.</span>
                            <span class="action-meta__item"><i class="fa-solid fa-triangle-exclamation"></i>Lost Barangay ID renewal costs Php50.00.</span>
                        </div>
                    </div>
                    <div class="apply-section">
                        <div class="apply-actions">
                        <button class="btn apply-btn" type="button" data-apply-href="<?= htmlspecialchars(appUrl('Resident-End/BarangayId/BarangayIdForm.php'), ENT_QUOTES, 'UTF-8') ?>">Apply Now</button>
                        <?php if ($digitalIdViewUrl !== ''): ?>
                            <button class="btn digital-id-btn" type="button" onclick="location.href='<?= htmlspecialchars($digitalIdViewUrl) ?>'">View Digital ID</button>
                        <?php endif; ?>
                        </div>
                        <?php if ($digitalIdViewUrl !== ''): ?>
                            <p class="digital-id-note">Your digital copy is ready for viewing.</p>
                        <?php endif; ?>
                        <p class="apply-note">Review the preview first if you want to confirm the official card layout before submitting your request.</p>
                    </div>
                </section>
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

        const previewButtons = Array.from(document.querySelectorAll('[data-preview-target]'));
        const previewImages = Array.from(document.querySelectorAll('[data-preview-image]'));

        const setPreviewSide = (target) => {
            const nextTarget = String(target || 'front').trim().toLowerCase() === 'back' ? 'back' : 'front';
            previewButtons.forEach((button) => {
                const isActive = button.getAttribute('data-preview-target') === nextTarget;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
            previewImages.forEach((image) => {
                image.classList.toggle('is-active', image.getAttribute('data-preview-image') === nextTarget);
            });
        };

        previewButtons.forEach((button) => {
            button.addEventListener('click', () => {
                setPreviewSide(button.getAttribute('data-preview-target') || 'front');
            });
        });
    </script>
</body>

</html>
