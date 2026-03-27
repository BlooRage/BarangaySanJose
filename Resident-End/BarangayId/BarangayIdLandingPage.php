<?php
$allowUnregistered = false;
require_once __DIR__ . "/../includes/resident_access_guard.php";
require_once __DIR__ . "/../../PhpFiles/General/documentRequestWorkflow.php";

$frontTemplateRelative = 'Resident-End/Certificates/BarangayID/FRONT_EMPTY.png';
$backTemplateRelative = 'Resident-End/Certificates/BarangayID/BACK_EMPTY.png';
$frontTemplateDiskPath = realpath(__DIR__ . '/../Certificates/BarangayID/FRONT_EMPTY.png');
$backTemplateDiskPath = realpath(__DIR__ . '/../Certificates/BarangayID/BACK_EMPTY.png');
$frontTemplateUrl = appUrl($frontTemplateRelative) . '?v=' . (string)(($frontTemplateDiskPath && is_file($frontTemplateDiskPath)) ? @filemtime($frontTemplateDiskPath) : time());
$backTemplateUrl = appUrl($backTemplateRelative) . '?v=' . (string)(($backTemplateDiskPath && is_file($backTemplateDiskPath)) ? @filemtime($backTemplateDiskPath) : time());
$userId = (string)($_SESSION['user_id'] ?? '');
$residentId = (isset($conn) && $conn instanceof mysqli && $userId !== '')
    ? (string)(dr_get_resident_id($conn, $userId) ?? '')
    : '';
$barangayIdState = (isset($conn) && $conn instanceof mysqli)
    ? dr_resident_barangay_id_state($conn, $userId, $residentId)
    : [
        'latest_completed_request_id' => '',
        'latest_completed_valid_until' => '',
        'latest_completed_days_until_expiry' => null,
        'latest_completed_lost' => false,
        'latest_completed_lost_reported_at' => '',
        'pending_request_id' => '',
        'pending_stage' => '',
        'can_submit_new_request' => true,
        'can_report_lost' => false,
        'submission_mode' => 'new',
        'renewal_eligible' => false,
        'renewal_available_on' => '',
        'block_reason' => '',
    ];
$latestDigitalIdRequestId = trim((string)($barangayIdState['latest_completed_request_id'] ?? ''));
$digitalIdViewUrl = $latestDigitalIdRequestId !== ''
    ? appUrl('Resident-End/BarangayId/DigitalId.php?request_id=' . rawurlencode($latestDigitalIdRequestId))
    : '';
$documentRequestsUrl = appUrl('/Resident-End/document_requests.php');
$submissionMode = dr_normalize_barangay_id_request_mode((string)($barangayIdState['submission_mode'] ?? 'new'));
$requestFormUrl = appUrl('Resident-End/BarangayId/BarangayIdForm.php');
if ($submissionMode !== 'new' || $latestDigitalIdRequestId !== '') {
    $query = ['mode' => $submissionMode];
    if ($latestDigitalIdRequestId !== '') {
        $query['source_request_id'] = $latestDigitalIdRequestId;
    }
    $requestFormUrl .= '?' . http_build_query($query);
}
$validUntilDt = dr_parse_datetime_value((string)($barangayIdState['latest_completed_valid_until'] ?? ''), true);
$validUntilLabel = $validUntilDt instanceof DateTimeImmutable ? $validUntilDt->format('F j, Y') : '';
$renewalAvailableDt = dr_parse_datetime_value((string)($barangayIdState['renewal_available_on'] ?? ''));
$renewalAvailableLabel = $renewalAvailableDt instanceof DateTimeImmutable ? $renewalAvailableDt->format('F j, Y') : '';
$pendingRequestId = trim((string)($barangayIdState['pending_request_id'] ?? ''));
$pendingStage = trim((string)($barangayIdState['pending_stage'] ?? ''));
$pendingStageLabel = $pendingStage !== '' ? dr_stage_label($pendingStage) : '';
$daysUntilExpiry = isset($barangayIdState['latest_completed_days_until_expiry']) && $barangayIdState['latest_completed_days_until_expiry'] !== null
    ? (int)$barangayIdState['latest_completed_days_until_expiry']
    : null;
$hasActiveValidId = ($barangayIdState['latest_completed_is_valid'] ?? false) && !($barangayIdState['latest_completed_lost'] ?? false);
$notice = trim((string)($_GET['notice'] ?? ''));
$flash = null;
if ($notice === 'lost_reported') {
    $flash = [
        'tone' => 'success',
        'icon' => 'fa-solid fa-circle-check',
        'title' => 'Barangay ID tagged as lost',
        'body' => 'You can now submit a replacement Barangay ID request. The replacement will receive a fresh 2-year validity once released.',
    ];
} elseif ($notice === 'lost_error') {
    $flash = [
        'tone' => 'error',
        'icon' => 'fa-solid fa-circle-exclamation',
        'title' => 'Unable to tag the Barangay ID as lost',
        'body' => 'Please refresh the page and try again. If the problem continues, check whether you already have an active replacement request in progress.',
    ];
} elseif ($notice === 'request_not_allowed') {
    $flash = [
        'tone' => 'warning',
        'icon' => 'fa-solid fa-hourglass-half',
        'title' => 'A new Barangay ID request is not available yet',
        'body' => 'Residents can only renew within the 3-month renewal window, or request a replacement after tagging the current ID as lost.',
    ];
}

$heroBadgeText = 'Application ready';
$heroBadgeIcon = 'fa-solid fa-circle-check';
$heroAsideTitle = 'Preview the official design, then continue with your request.';
$heroAsideCopy = 'Use the preview as your guide before filing a new request. Your digital copy will appear here once your latest request is completed.';
$actionTitle = 'Apply for a new ID or open your digital copy';
$actionBody = 'Start a new Barangay ID request any time. If your latest request is already completed, you can open the digital version immediately from here.';
$actionMeta = [];

if ($pendingRequestId !== '') {
    $heroBadgeText = 'Request in progress';
    $heroBadgeIcon = 'fa-solid fa-spinner';
    $heroAsideTitle = 'You already have an active Barangay ID request.';
    $heroAsideCopy = 'Track the request first before creating another one. Once this request is completed, rejected, or cancelled, the next available action will appear here automatically.';
    $actionTitle = 'Your current Barangay ID request is already being processed';
    $actionBody = 'Open your request list to monitor the status instead of submitting another application.';
    $actionMeta[] = '<i class="fa-solid fa-hashtag"></i>Request ID: ' . htmlspecialchars($pendingRequestId, ENT_QUOTES, 'UTF-8');
    if ($pendingStageLabel !== '') {
        $actionMeta[] = '<i class="fa-solid fa-list-check"></i>Status: ' . htmlspecialchars($pendingStageLabel, ENT_QUOTES, 'UTF-8');
    }
} elseif ($submissionMode === 'replacement_lost') {
    $heroBadgeText = 'Lost ID reported';
    $heroBadgeIcon = 'fa-solid fa-triangle-exclamation';
    $heroAsideTitle = 'Your Barangay ID is marked as lost.';
    $heroAsideCopy = 'You can now submit a replacement request. Once released, the replacement ID will carry a fresh 2-year validity based on the new issue date.';
    $actionTitle = 'Request a replacement Barangay ID';
    $actionBody = 'Your last released Barangay ID has already been tagged as lost, so the replacement form is now available again.';
} elseif ($submissionMode === 'renewal') {
    $heroBadgeText = 'Renewal window open';
    $heroBadgeIcon = 'fa-solid fa-arrows-rotate';
    $heroAsideTitle = 'Your Barangay ID can now be renewed.';
    $heroAsideCopy = $validUntilLabel !== ''
        ? 'Your current Barangay ID is valid until ' . $validUntilLabel . '. Because it is already within the 3-month renewal window, the renewal form is available again.'
        : 'Your current Barangay ID is already within the renewal window, so the renewal form is available again.';
    $actionTitle = 'Renew your Barangay ID';
    $actionBody = 'Submit the renewal form to request a newly issued card with a fresh 2-year validity.';
} elseif (($barangayIdState['block_reason'] ?? '') === 'active_valid') {
    $heroBadgeText = 'Currently active';
    $heroBadgeIcon = 'fa-solid fa-shield-halved';
    $heroAsideTitle = 'Your current Barangay ID is still active.';
    $heroAsideCopy = $renewalAvailableLabel !== ''
        ? 'Renewal opens on ' . $renewalAvailableLabel . '. If this active ID is lost before then, tag it as lost first to unlock the replacement form.'
        : 'Renewal becomes available again three months before expiry. If this active ID is lost before then, tag it as lost first to unlock the replacement form.';
    $actionTitle = 'Your Barangay ID is still valid';
    $actionBody = 'You cannot file a new request yet while the current ID is still active, unless you first tag the current Barangay ID as lost.';
}

if ($validUntilLabel !== '') {
    $actionMeta[] = '<i class="fa-solid fa-calendar-check"></i>Valid until: ' . htmlspecialchars($validUntilLabel, ENT_QUOTES, 'UTF-8');
}
if ($daysUntilExpiry !== null && $daysUntilExpiry >= 0 && $pendingRequestId === '') {
    $actionMeta[] = '<i class="fa-solid fa-hourglass-half"></i>' . htmlspecialchars((string)$daysUntilExpiry, ENT_QUOTES, 'UTF-8') . ' day' . ($daysUntilExpiry === 1 ? '' : 's') . ' until expiry';
}
if ($pendingRequestId === '') {
    $actionMeta[] = '<i class="fa-solid fa-calendar-plus"></i>Approved Barangay IDs receive a fresh 2-year validity.';
}
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
        .page-flash {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 14px;
            align-items: start;
            padding: 18px 20px;
            border-radius: 22px;
            border: 1px solid rgba(241, 212, 181, 0.92);
            background: rgba(255, 251, 246, 0.96);
            box-shadow: 0 18px 36px rgba(145, 87, 24, 0.07);
        }
        .page-flash__icon {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 16px;
            background: #fff;
            border: 1px solid rgba(241, 212, 181, 0.92);
            color: var(--bid-page-accent-strong);
        }
        .page-flash__title {
            margin: 0 0 4px;
            font-size: 1.06rem;
            font-weight: 800;
            color: var(--bid-page-ink);
        }
        .page-flash__body {
            margin: 0;
            color: var(--bid-page-muted);
            line-height: 1.65;
        }
        .page-flash--success .page-flash__icon {
            color: #1f8c49;
        }
        .page-flash--error .page-flash__icon {
            color: #c23f2e;
        }
        .page-flash--warning .page-flash__icon {
            color: #b85a00;
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
        .apply-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
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
        .lost-report-btn {
            min-width: 210px;
            background: #ffffff;
            color: #8a4b00;
            border: 1px dashed #e3a768;
            box-shadow: 0 14px 28px rgba(138, 75, 0, 0.08);
        }
        .lost-report-btn:hover {
            background: #fff6eb;
            border-color: #de710c;
            color: #8a4b00;
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
                <?php if (is_array($flash)): ?>
                    <section class="page-flash page-flash--<?= htmlspecialchars((string)($flash['tone'] ?? 'warning'), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="page-flash__icon">
                            <i class="<?= htmlspecialchars((string)($flash['icon'] ?? 'fa-solid fa-circle-info'), ENT_QUOTES, 'UTF-8') ?>"></i>
                        </div>
                        <div>
                            <h2 class="page-flash__title"><?= htmlspecialchars((string)($flash['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
                            <p class="page-flash__body"><?= htmlspecialchars((string)($flash['body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </section>
                <?php endif; ?>
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
                            <span class="barangay-id-chip"><i class="fa-solid fa-arrows-rotate"></i>Renewal opens 3 months before expiry</span>
                        </div>
                    </div>
                    <aside class="barangay-id-hero__aside">
                        <span class="barangay-id-hero__aside-badge">
                            <i class="<?= htmlspecialchars($heroBadgeIcon, ENT_QUOTES, 'UTF-8') ?>"></i>
                            <?= htmlspecialchars($heroBadgeText, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <h2 class="barangay-id-hero__aside-title">
                            <?= htmlspecialchars($heroAsideTitle, ENT_QUOTES, 'UTF-8') ?>
                        </h2>
                        <p class="barangay-id-hero__aside-copy">
                            <?= htmlspecialchars($heroAsideCopy, ENT_QUOTES, 'UTF-8') ?>
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
                        <h2 class="action-panel__title"><?= htmlspecialchars($actionTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="action-panel__body"><?= htmlspecialchars($actionBody, ENT_QUOTES, 'UTF-8') ?></p>
                        <div class="action-meta">
                            <?php foreach ($actionMeta as $metaItem): ?>
                                <span class="action-meta__item"><?= $metaItem ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="apply-section">
                        <div class="apply-actions">
                            <?php if ($pendingRequestId !== ''): ?>
                                <button class="btn apply-btn" type="button" onclick="location.href='<?= htmlspecialchars($documentRequestsUrl, ENT_QUOTES, 'UTF-8') ?>'">View My Requests</button>
                            <?php elseif ($barangayIdState['can_submit_new_request'] ?? false): ?>
                                <button class="btn apply-btn" type="button" data-apply-href="<?= htmlspecialchars($requestFormUrl, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars(match ($submissionMode) {
                                        'renewal' => 'Renew Barangay ID',
                                        'replacement_lost' => 'Request Replacement',
                                        default => 'Apply Now',
                                    }, ENT_QUOTES, 'UTF-8') ?>
                                </button>
                            <?php endif; ?>
                            <?php if ($digitalIdViewUrl !== ''): ?>
                                <button class="btn digital-id-btn" type="button" onclick="location.href='<?= htmlspecialchars($digitalIdViewUrl, ENT_QUOTES, 'UTF-8') ?>'">View Digital ID</button>
                            <?php endif; ?>
                            <?php if ($barangayIdState['can_report_lost'] ?? false): ?>
                                <button class="btn lost-report-btn" type="button" data-report-lost data-request-id="<?= htmlspecialchars($latestDigitalIdRequestId, ENT_QUOTES, 'UTF-8') ?>">Tag ID as Lost</button>
                            <?php endif; ?>
                        </div>
                        <?php if ($submissionMode === 'replacement_lost' && $pendingRequestId === ''): ?>
                            <p class="digital-id-note">Lost status recorded. The replacement form is open again.</p>
                        <?php elseif (($barangayIdState['block_reason'] ?? '') === 'active_valid' && $renewalAvailableLabel !== ''): ?>
                            <p class="digital-id-note">Renewal becomes available on <?= htmlspecialchars($renewalAvailableLabel, ENT_QUOTES, 'UTF-8') ?> unless the active ID is tagged as lost first.</p>
                        <?php elseif ($digitalIdViewUrl !== ''): ?>
                            <p class="digital-id-note">Your latest completed Barangay ID can be opened online here.</p>
                        <?php endif; ?>
                        <p class="apply-note">
                            <?= htmlspecialchars($pendingRequestId !== ''
                                ? 'Monitor the active request first. Once it is completed, rejected, or cancelled, the next available Barangay ID action will appear here automatically.'
                                : 'Review the preview first if you want to confirm the official card layout before submitting your request.', ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <?php include __DIR__ . '/../includes/document_issuance_verification_modal.php'; ?>
    <form id="barangayIdLostForm" method="POST" action="<?= htmlspecialchars(appUrl('/PhpFiles/Resident-End/documentRequestWorkflow.php'), ENT_QUOTES, 'UTF-8') ?>" class="d-none">
        <input type="hidden" name="action" value="report_barangay_id_lost">
        <input type="hidden" name="request_id" value="">
        <input type="hidden" name="redirect" value="1">
    </form>

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

        const lostForm = document.getElementById('barangayIdLostForm');
        document.querySelectorAll('[data-report-lost]').forEach((button) => {
            button.addEventListener('click', () => {
                const targetRequestId = String(button.getAttribute('data-request-id') || '').trim();
                if (!targetRequestId || !lostForm) {
                    return;
                }
                const confirmed = window.confirm('Tag this Barangay ID as lost and unlock the replacement form?');
                if (!confirmed) {
                    return;
                }
                const requestInput = lostForm.querySelector('input[name="request_id"]');
                if (requestInput) {
                    requestInput.value = targetRequestId;
                }
                lostForm.submit();
            });
        });
    </script>
</body>

</html>
