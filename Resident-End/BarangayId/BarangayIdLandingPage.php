<?php
$allowUnregistered = false;
require_once __DIR__ . "/../includes/resident_access_guard.php";
require_once __DIR__ . "/../../PhpFiles/General/documentRequestWorkflow.php";
require_once __DIR__ . "/../../PhpFiles/General/documentModuleSettings.php";
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
$barangayIdOperationalSettings = (isset($conn) && $conn instanceof mysqli)
    ? dms_resolve_barangay_id_operational_settings($conn)
    : ['online_application_enabled' => true];
$onlineApplicationEnabled = (bool)($barangayIdOperationalSettings['online_application_enabled'] ?? true);
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
$hasIssuedBarangayId = $latestDigitalIdRequestId !== '';
$hasNoIssuedBarangayIdYet = !$hasIssuedBarangayId && $pendingRequestId === '';
$daysUntilExpiry = isset($barangayIdState['latest_completed_days_until_expiry']) && $barangayIdState['latest_completed_days_until_expiry'] !== null
    ? (int)$barangayIdState['latest_completed_days_until_expiry']
    : null;
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
} elseif ($notice === 'online_application_disabled') {
    $flash = [
        'tone' => 'warning',
        'icon' => 'fa-solid fa-lock',
        'title' => 'Online Barangay ID applications are disabled',
        'body' => 'Please visit the barangay office for new, renewal, or replacement Barangay ID applications.',
    ];
}

$heroBadgeText = 'Application ready';
$heroBadgeIcon = 'fa-solid fa-circle-check';
$actionTitle = 'Apply for a new Barangay ID';
$actionBody = 'Start a new Barangay ID request here. After approval, your digital ID will also appear on this page.';
$actionMeta = [];

if ($pendingRequestId !== '') {
    $heroBadgeText = 'Request in progress';
    $heroBadgeIcon = 'fa-solid fa-spinner';
    $actionTitle = 'Your current Barangay ID request is already being processed';
    $actionBody = 'Open your request list to monitor the status instead of submitting another application.';
    $actionMeta[] = '<i class="fa-solid fa-hashtag"></i>Request ID: ' . htmlspecialchars($pendingRequestId, ENT_QUOTES, 'UTF-8');
    if ($pendingStageLabel !== '') {
        $actionMeta[] = '<i class="fa-solid fa-list-check"></i>Status: ' . htmlspecialchars($pendingStageLabel, ENT_QUOTES, 'UTF-8');
    }
} elseif ($hasNoIssuedBarangayIdYet && $submissionMode === 'new') {
    $heroBadgeText = 'No Barangay ID yet';
    $heroBadgeIcon = 'fa-solid fa-id-card';
    $actionTitle = 'Apply for your first Barangay ID';
    $actionBody = 'You do not have an issued Barangay ID yet. Submit your first application below.';
} elseif ($submissionMode === 'replacement_lost') {
    $heroBadgeText = 'Lost ID reported';
    $heroBadgeIcon = 'fa-solid fa-triangle-exclamation';
    $actionTitle = 'Request a replacement Barangay ID';
    $actionBody = 'Your most recent Barangay ID was marked as lost. You can submit a replacement request now.';
} elseif ($submissionMode === 'renewal') {
    $heroBadgeText = 'Renewal window open';
    $heroBadgeIcon = 'fa-solid fa-arrows-rotate';
    $actionTitle = 'Renew your Barangay ID';
    $actionBody = 'Your Barangay ID is already within the renewal window. Submit a renewal request for a fresh 2-year validity.';
} elseif (($barangayIdState['block_reason'] ?? '') === 'active_valid') {
    $heroBadgeText = 'Currently active';
    $heroBadgeIcon = 'fa-solid fa-shield-halved';
    $actionTitle = 'Your Barangay ID is still valid';
    $actionBody = 'Your current Barangay ID is still active. New requests stay locked until the renewal window opens, unless you report this ID as lost.';
}

if (!$onlineApplicationEnabled && $pendingRequestId === '' && (bool)($barangayIdState['can_submit_new_request'] ?? false)) {
    $heroBadgeText = 'Online applications disabled';
    $heroBadgeIcon = 'fa-solid fa-lock';
    $actionTitle = 'Online Barangay ID applications are currently disabled';
    $actionBody = 'Please visit the barangay office for new, renewal, or replacement Barangay ID applications.';
}

if ($validUntilLabel !== '') {
    $actionMeta[] = '<i class="fa-solid fa-calendar-check"></i>Valid until: ' . htmlspecialchars($validUntilLabel, ENT_QUOTES, 'UTF-8');
}
if ($daysUntilExpiry !== null && $daysUntilExpiry >= 0 && $pendingRequestId === '') {
    $actionMeta[] = '<i class="fa-solid fa-hourglass-half"></i>' . htmlspecialchars((string)$daysUntilExpiry, ENT_QUOTES, 'UTF-8') . ' day' . ($daysUntilExpiry === 1 ? '' : 's') . ' until expiry';
}
if ($pendingRequestId === '' && $hasIssuedBarangayId) {
    $actionMeta[] = '<i class="fa-solid fa-calendar-plus"></i>Approved Barangay IDs receive a fresh 2-year validity.';
}
$displayActionMeta = array_slice($actionMeta, 0, 2);
$showRequestsButton = $pendingRequestId !== '';
$showApplyButton = $onlineApplicationEnabled && !$showRequestsButton && (bool)($barangayIdState['can_submit_new_request'] ?? false);
$showDigitalButton = $digitalIdViewUrl !== '';
$showLostButton = (bool)($barangayIdState['can_report_lost'] ?? false);
$hasActionButtons = $showRequestsButton || $showApplyButton || $showDigitalButton || $showLostButton;
$displayActionMetaCount = count($displayActionMeta);
$actionButtonCount = (int)$showRequestsButton + (int)$showApplyButton + (int)$showDigitalButton + (int)$showLostButton;
$actionButtonLabel = match ($submissionMode) {
    'renewal' => 'Renew Barangay ID',
    'replacement_lost' => 'Request Replacement',
    default => $hasNoIssuedBarangayIdYet ? 'Apply for Barangay ID' : 'Open Form',
};
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
    <link rel="stylesheet" href="../../CSS-Styles/modalStyle.css">
    <style>
        :root {
            --bid-page-accent: #de710c;
            --bid-page-accent-strong: #b85a00;
            --bid-page-accent-soft: #fff2df;
            --bid-page-border: #f1d4b6;
            --bid-page-ink: #231a12;
            --bid-page-muted: #6f6357;
            --bid-page-panel: #fffaf4;
        }

        body.barangay-id-landing {
            background: linear-gradient(180deg, #f3f5fa 0%, #fbfcff 22%, #ffffff 100%);
        }

        .barangay-id-landing #div-mainDisplay {
            background: transparent !important;
        }

        .bid-shell,
        .bid-flow {
            width: 100%;
            max-width: none;
            margin: 0;
        }

        .documents-page.barangay-id-landing .page-title {
            margin-bottom: 1.5rem;
        }

        .documents-page.barangay-id-landing .bid-page-head {
            margin-bottom: 0;
        }

        .documents-page.barangay-id-landing .bid-page-head hr {
            margin: 0 0 1.5rem;
            border-color: #cfc5bc;
            opacity: 1;
        }

        .documents-page.barangay-id-landing .bid-page-head .page-description {
            max-width: none;
        }

        .bid-action-shell {
            width: 100%;
            max-width: none;
            margin: 0 0 1rem;
        }

        .page-flash {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 14px;
            align-items: start;
            padding: 18px 20px;
            margin-bottom: 1.5rem;
            border-radius: 20px;
            border: 1px solid var(--bid-page-border);
            background: rgba(255, 250, 244, 0.92);
            box-shadow: 0 18px 36px rgba(35, 26, 18, 0.06);
        }

        .page-flash__icon {
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            border-radius: 15px;
            background: #fff;
            border: 1px solid var(--bid-page-border);
            color: var(--bid-page-accent-strong);
        }

        .page-flash__title {
            margin: 0 0 4px;
            font-size: 1.05rem;
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

        .bid-hero {
            position: relative;
            overflow: hidden;
            padding: 2rem;
            border-radius: 30px;
            border: 1px solid var(--bid-page-border);
            background:
                radial-gradient(circle at top right, rgba(255, 219, 171, 0.58), rgba(255, 219, 171, 0) 28%),
                linear-gradient(135deg, #fffdf9 0%, #fff6eb 52%, #fffdfa 100%);
            box-shadow:
                0 24px 52px rgba(35, 26, 18, 0.08),
                inset 0 1px 0 rgba(255, 255, 255, 0.95);
        }

        .bid-hero::before {
            content: "";
            position: absolute;
            inset: 1px;
            border-radius: inherit;
            border: 1px solid rgba(255, 255, 255, 0.58);
            pointer-events: none;
        }

        .bid-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.55rem 0.9rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(241, 212, 182, 0.94);
            color: var(--bid-page-accent-strong);
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .bid-hero__body {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1.25fr) minmax(320px, 0.95fr);
            gap: 1.5rem;
            align-items: start;
        }

        .bid-hero__copy {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .bid-hero__heading {
            display: flex;
            align-items: center;
            gap: 1.1rem;
        }

        .bid-hero__icon {
            width: 88px;
            height: 88px;
            flex: 0 0 auto;
            display: grid;
            place-items: center;
            border-radius: 26px;
            background: linear-gradient(180deg, #ffe7c2 0%, #ffd89f 100%);
            border: 1px solid #f2d0a9;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.95),
                0 16px 32px rgba(222, 113, 12, 0.12);
        }

        .bid-hero__icon img {
            width: 46px;
            height: 46px;
            object-fit: contain;
        }

        .bid-hero__lede {
            margin: 0.8rem 0 0;
            max-width: 40rem;
            color: #43382e;
            font-size: 1.05rem;
            line-height: 1.78;
        }

        .bid-highlight-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.85rem;
        }

        .bid-highlight-card {
            padding: 1rem 1rem 0.95rem;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.78);
            border: 1px solid rgba(241, 212, 182, 0.9);
            box-shadow: 0 12px 28px rgba(35, 26, 18, 0.04);
        }

        .bid-highlight-card__label {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            margin-bottom: 0.55rem;
            color: var(--bid-page-accent-strong);
            font-size: 0.8rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .bid-highlight-card h3 {
            margin: 0 0 0.35rem;
            font-size: 1rem;
            font-weight: 800;
            color: var(--bid-page-ink);
        }

        .bid-highlight-card p {
            margin: 0;
            color: var(--bid-page-muted);
            font-size: 0.92rem;
            line-height: 1.55;
        }

        .bid-status-card {
            height: 100%;
            width: 100%;
            max-width: none;
            padding: 1.55rem 1.65rem;
            border-radius: 22px;
            background: #ffffff;
            border: 1px solid #eadbc9;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.06);
            display: flex;
            flex-direction: column;
            gap: 0.9rem;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            width: fit-content;
            padding: 0.58rem 0.9rem;
            border-radius: 999px;
            background: #fff8ee;
            color: #8a4b00;
            border: 1px solid #efd4b4;
            font-size: 0.82rem;
            font-weight: 800;
        }

        .status-title {
            margin: 0;
            font-size: 1.24rem;
            font-weight: 800;
            line-height: 1.28;
            color: var(--bid-page-ink);
        }

        .status-copy {
            margin: 0;
            max-width: 46rem;
            color: #43382e;
            line-height: 1.72;
        }

        .bid-meta-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.1rem;
        }

        .meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.72rem 0.9rem;
            border-radius: 14px;
            background: #fffaf5;
            border: 1px solid #efdcc9;
            color: #6a4521;
            font-weight: 700;
            line-height: 1.35;
        }

        .bid-meta-list.bid-meta-list--paired,
        .bid-actions.bid-actions--paired {
            width: 100%;
            max-width: none;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.65rem;
        }

        .bid-meta-list--paired .meta-chip,
        .bid-actions--paired .bid-btn {
            width: 100%;
        }

        .bid-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            margin-top: 0.1rem;
        }

        .bid-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            width: auto;
            min-width: 180px;
            min-height: 48px;
            padding: 0.78rem 1rem !important;
            border-radius: 10px !important;
            font-size: 0.92rem !important;
            line-height: 1.2 !important;
            font-weight: 700 !important;
            border: 1px solid transparent !important;
            box-shadow: none !important;
            transition: border-color 0.18s ease, background-color 0.18s ease, color 0.18s ease;
        }

        .bid-btn i {
            font-size: 0.95rem;
        }

        .bid-btn--primary {
            background: #de710c !important;
            color: #fff !important;
            border-color: #de710c !important;
        }

        .bid-btn--primary:hover,
        .bid-btn--primary:focus-visible {
            background: #c86208 !important;
            color: #fff !important;
            border-color: #c86208 !important;
        }

        .bid-btn--secondary {
            background: #fff !important;
            color: var(--bid-page-accent-strong) !important;
            border-color: #efcfa9 !important;
        }

        .bid-btn--secondary:hover,
        .bid-btn--secondary:focus-visible {
            background: #fff8ef !important;
            color: var(--bid-page-accent-strong) !important;
            border-color: #e7be8e !important;
        }

        .bid-btn--warning {
            background: #fff !important;
            color: #b42318 !important;
            border-color: #f0c9c5 !important;
        }

        .bid-btn--warning:hover,
        .bid-btn--warning:focus-visible {
            background: #fff5f4 !important;
            color: #9f1d14 !important;
            border-color: #e8b0aa !important;
        }

        .bid-status-note,
        .bid-status-footnote {
            margin: 0;
            color: var(--bid-page-muted);
            line-height: 1.68;
            font-size: 0.94rem;
        }

        .bid-status-note {
            max-width: 44rem;
            color: var(--bid-page-accent-strong);
            font-weight: 700;
        }

        .barangay-id-lost-modal .modal-content {
            background: #ffffff !important;
        }

        .bid-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(300px, 0.9fr);
            gap: 1rem;
            margin-top: 1rem;
        }

        .bid-panel {
            padding: 1.25rem;
            border-radius: 22px;
            border: 1px solid #eadbc9;
            background: #ffffff;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.05);
        }

        .bid-panel--soft {
            background: #fffdfb;
        }

        .bid-panel__eyebrow {
            margin: 0 0 0.45rem;
            color: var(--bid-page-accent-strong);
            font-size: 0.8rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .bid-panel__header h2 {
            margin: 0 0 0.45rem;
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--bid-page-ink);
        }

        .bid-panel__header p {
            margin: 0;
            color: var(--bid-page-muted);
            line-height: 1.7;
        }

        .bid-service-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .bid-service-card {
            padding: 0.9rem;
            border-radius: 16px;
            background: #fcfbf9;
            border: 1px solid #eee1d2;
        }

        .bid-service-card__icon {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            margin-bottom: 0.7rem;
            border-radius: 12px;
            background: linear-gradient(180deg, #ffe8c5 0%, #ffd9a3 100%);
            color: var(--bid-page-accent-strong);
            font-size: 1rem;
        }

        .bid-service-card h3 {
            margin: 0 0 0.4rem;
            font-size: 0.96rem;
            font-weight: 800;
            color: var(--bid-page-ink);
        }

        .bid-service-card p {
            margin: 0;
            color: var(--bid-page-muted);
            font-size: 0.9rem;
            line-height: 1.54;
        }

        .bid-checklist {
            list-style: none;
            margin: 1.15rem 0 0;
            padding: 0;
            display: grid;
            gap: 0.8rem;
        }

        .bid-checklist li {
            position: relative;
            padding-left: 1.65rem;
            color: #3f352b;
            line-height: 1.7;
        }

        .bid-checklist li::before {
            content: "\f058";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            top: 0.05rem;
            left: 0;
            color: var(--bid-page-accent-strong);
        }

        .bid-reminder {
            margin-top: 1.1rem;
            padding: 1rem 1rem 1rem 0.95rem;
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 0.75rem;
            border-radius: 18px;
            background: #fcfbf9;
            border: 1px solid #eee1d2;
        }

        .bid-reminder i {
            color: var(--bid-page-accent-strong);
            margin-top: 0.2rem;
        }

        .bid-reminder p {
            margin: 0;
            color: var(--bid-page-muted);
            line-height: 1.68;
        }

        @media (max-width: 991.98px) {
            .bid-hero__body,
            .bid-layout {
                grid-template-columns: 1fr;
            }

            .bid-status-card {
                height: auto;
            }
        }

        @media (max-width: 767.98px) {
            .bid-shell {
                padding-top: 0.35rem;
            }

            .bid-flow,
            .status-copy,
            .bid-status-note {
                max-width: none;
            }

            .bid-meta-list.bid-meta-list--paired,
            .bid-actions.bid-actions--paired {
                max-width: none;
                grid-template-columns: 1fr;
            }

            .bid-action-shell {
                max-width: none;
            }

            .bid-page-head {
                margin-bottom: 1.2rem;
            }

            .bid-page-head hr {
                margin: 0.95rem 0 1.2rem;
            }

            .bid-hero {
                padding: 1.2rem;
                border-radius: 24px;
            }

            .bid-hero__heading {
                align-items: flex-start;
                flex-direction: column;
                gap: 0.9rem;
            }

            .bid-hero__icon {
                width: 72px;
                height: 72px;
                border-radius: 20px;
            }

            .bid-hero__icon img {
                width: 38px;
                height: 38px;
            }

            .page-title {
                font-size: 2rem;
            }

            .bid-hero__lede,
            .page-description {
                font-size: 0.95rem;
                line-height: 1.68;
            }

            .bid-highlight-grid,
            .bid-service-grid {
                grid-template-columns: 1fr;
            }

            .bid-status-card,
            .bid-panel {
                padding: 1.15rem;
                border-radius: 22px;
            }

            .bid-actions {
                gap: 0.55rem;
                display: grid;
            }

            .bid-btn {
                width: 100%;
                font-size: 0.92rem !important;
                padding: 0.82rem 0.95rem !important;
            }
        }
    </style>
</head>

<body class="documents-page barangay-id-landing">
    <div class="d-flex min-vh-100">
        <?php include __DIR__ . '/../includes/resident_sidebar.php'; ?>

        <header id="mobile-header">
            <div class="d-flex align-items-center px-3 py-2 shadow-sm bg-white">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn" id="btn-burger" type="button" aria-label="Open sidebar">
                        <i class="fa-solid fa-bars fa-lg"></i>
                    </button>
                    <img src="<?= htmlspecialchars((string)$baseUrl, ENT_QUOTES, 'UTF-8') ?>/Images/San_Jose_LOGO.jpg" alt="Logo" style="width:32px;height:32px">
                    <span class="logo-name">Barangay San Jose</span>
                </div>
            </div>
        </header>

        <main id="div-mainDisplay" class="main-content flex-grow-1 p-4 p-md-5">
            <div class="bid-shell">
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
                <div class="bid-flow">
                    <section class="bid-page-head">
                        <h1 class="page-title">Barangay ID</h1>
                        <hr>
                        <p class="page-description">
                            Apply for a new Barangay ID, renew when eligible, request a replacement, or open your digital copy after approval.
                        </p>
                    </section>

                    <section class="bid-action-shell">
                        <aside class="bid-status-card">
                            <span class="status-pill">
                                <i class="<?= htmlspecialchars($heroBadgeIcon, ENT_QUOTES, 'UTF-8') ?>"></i>
                                <?= htmlspecialchars($heroBadgeText, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <h2 class="status-title"><?= htmlspecialchars($actionTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                            <p class="status-copy"><?= htmlspecialchars($actionBody, ENT_QUOTES, 'UTF-8') ?></p>

                            <?php if ($displayActionMeta !== []): ?>
                                <div class="bid-meta-list<?= $displayActionMetaCount === 2 ? ' bid-meta-list--paired' : '' ?>">
                                    <?php foreach ($displayActionMeta as $metaItem): ?>
                                        <span class="meta-chip"><?= $metaItem ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($hasActionButtons): ?>
                                <div class="bid-actions<?= $actionButtonCount === 2 ? ' bid-actions--paired' : '' ?>">
                                    <?php if ($showRequestsButton): ?>
                                        <button class="btn bid-btn bid-btn--primary" type="button" onclick="location.href='<?= htmlspecialchars($documentRequestsUrl, ENT_QUOTES, 'UTF-8') ?>'">
                                            <i class="fa-solid fa-list-check"></i>
                                            View My Requests
                                        </button>
                                    <?php elseif ($showApplyButton): ?>
                                        <button class="btn bid-btn bid-btn--primary" type="button" data-apply-href="<?= htmlspecialchars($requestFormUrl, ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="fa-solid fa-id-card"></i>
                                            <?= htmlspecialchars($actionButtonLabel, ENT_QUOTES, 'UTF-8') ?>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($showDigitalButton): ?>
                                        <button class="btn bid-btn bid-btn--secondary" type="button" onclick="location.href='<?= htmlspecialchars($digitalIdViewUrl, ENT_QUOTES, 'UTF-8') ?>'">
                                            <i class="fa-solid fa-qrcode"></i>
                                            View Digital ID
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($showLostButton): ?>
                                        <button class="btn bid-btn bid-btn--warning" type="button" data-report-lost data-request-id="<?= htmlspecialchars($latestDigitalIdRequestId, ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="fa-solid fa-triangle-exclamation"></i>
                                            Tag ID as Lost
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($submissionMode === 'replacement_lost' && $pendingRequestId === ''): ?>
                                <p class="bid-status-note">Lost status recorded. Replacement is now available.</p>
                            <?php elseif (($barangayIdState['block_reason'] ?? '') === 'active_valid' && $renewalAvailableLabel !== ''): ?>
                                <p class="bid-status-note">Renewal opens on <?= htmlspecialchars($renewalAvailableLabel, ENT_QUOTES, 'UTF-8') ?>, unless this ID is reported as lost first.</p>
                            <?php elseif ($digitalIdViewUrl !== ''): ?>
                                <p class="bid-status-note">Your digital Barangay ID is ready to open here.</p>
                            <?php endif; ?>

                        </aside>
                    </section>
                </div>
            </div>
        </main>
    </div>
    <?php include __DIR__ . '/../includes/document_issuance_verification_modal.php'; ?>
    <form id="barangayIdLostForm" method="POST" action="<?= htmlspecialchars(appUrl('/PhpFiles/Resident-End/documentRequestWorkflow.php'), ENT_QUOTES, 'UTF-8') ?>" class="d-none">
        <input type="hidden" name="action" value="report_barangay_id_lost">
        <input type="hidden" name="request_id" value="">
        <input type="hidden" name="redirect" value="1">
    </form>

    <div class="modal fade uniform-modal barangay-id-lost-modal" id="barangayIdLostModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" id="umContent">
                <div class="modal-header">
                    <h5 class="modal-title text-black" id="barangayIdLostModalLabel">Confirm Lost Barangay ID</h5>
                </div>
                <hr class="my-0">
                <div class="modal-body">
                    <div class="uniform-modal__copy">
                        <p class="mb-0">This will mark your current Barangay ID as lost and unlock the replacement form. Continue only if the card is really lost.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="row g-2 w-100">
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-secondary modalBtn w-100" data-bs-dismiss="modal">Cancel</button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-danger modalBtn w-100" id="confirmBarangayIdLostBtn">Tag as Lost</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const burgerBtn = document.getElementById("btn-burger");
        const sidebar = document.getElementById("div-sidebarWrapper");

        if (burgerBtn && sidebar) {
            burgerBtn.addEventListener("click", () => {
                sidebar.classList.toggle("show");
            });
        }
    </script>
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

        const lostForm = document.getElementById('barangayIdLostForm');
        const lostRequestInput = lostForm?.querySelector('input[name="request_id"]') || null;
        const lostModalEl = document.getElementById('barangayIdLostModal');
        const lostModal = (lostModalEl && window.bootstrap?.Modal)
            ? bootstrap.Modal.getOrCreateInstance(lostModalEl)
            : null;
        const confirmLostBtn = document.getElementById('confirmBarangayIdLostBtn');
        let pendingLostRequestId = '';

        document.querySelectorAll('[data-report-lost]').forEach((button) => {
            button.addEventListener('click', () => {
                const targetRequestId = String(button.getAttribute('data-request-id') || '').trim();
                if (!targetRequestId || !lostForm) {
                    return;
                }

                pendingLostRequestId = targetRequestId;
                if (lostRequestInput) {
                    lostRequestInput.value = targetRequestId;
                }

                if (!lostModal) {
                    lostForm.submit();
                    return;
                }

                lostModal.show();
            });
        });

        if (confirmLostBtn) {
            confirmLostBtn.addEventListener('click', () => {
                if (!pendingLostRequestId || !lostForm) {
                    return;
                }

                if (lostRequestInput) {
                    lostRequestInput.value = pendingLostRequestId;
                }

                lostModal?.hide();
                lostForm.submit();
            });
        }

        lostModalEl?.addEventListener('hidden.bs.modal', () => {
            pendingLostRequestId = '';
            if (lostRequestInput) {
                lostRequestInput.value = '';
            }
        });
    </script>
</body>

</html>
