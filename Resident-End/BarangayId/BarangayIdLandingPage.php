<?php
$allowUnregistered = false;
require_once __DIR__ . "/../includes/resident_access_guard.php";
require_once __DIR__ . "/../../PhpFiles/General/documentRequestWorkflow.php";
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
$actionTitle = 'Apply for a new ID or open your digital copy';
$actionBody = 'Start a new Barangay ID request any time. If your latest request is already completed, you can open the digital version immediately from here.';
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
} elseif ($submissionMode === 'replacement_lost') {
    $heroBadgeText = 'Lost ID reported';
    $heroBadgeIcon = 'fa-solid fa-triangle-exclamation';
    $actionTitle = 'Request a replacement Barangay ID';
    $actionBody = 'Your last released Barangay ID has already been tagged as lost, so the replacement form is now available again.';
} elseif ($submissionMode === 'renewal') {
    $heroBadgeText = 'Renewal window open';
    $heroBadgeIcon = 'fa-solid fa-arrows-rotate';
    $actionTitle = 'Renew your Barangay ID';
    $actionBody = 'Submit the renewal form to request a newly issued card with a fresh 2-year validity.';
} elseif (($barangayIdState['block_reason'] ?? '') === 'active_valid') {
    $heroBadgeText = 'Currently active';
    $heroBadgeIcon = 'fa-solid fa-shield-halved';
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
            --bid-page-border: #f2d9c2;
            --bid-page-ink: #241f1a;
            --bid-page-muted: #675c50;
        }
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
        .page-header-icon {
            height: 52px;
            margin-bottom: 0;
        }
        .page-title {
            font-size: 2.4rem;
            font-weight: 700;
            line-height: 1.05;
            margin: 0;
        }
        .page-flash {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 14px;
            align-items: start;
            padding: 18px 20px;
            margin-bottom: 1.5rem;
            border-radius: 16px;
            border: 1px solid var(--bid-page-border);
            background: #fffaf5;
        }
        .page-flash__icon {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: #fff;
            border: 1px solid var(--bid-page-border);
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
        .page-shell > .page-description {
            margin: 0 0 1.5rem !important;
        }
        .page-subtitle {
            font-size: 1.3rem;
            font-weight: 600;
            color: #1f1f1f;
        }
        .info-card {
            background: #fff7ef;
            border: 1px solid var(--bid-page-border);
            border-radius: 16px;
            padding: 20px 24px;
        }
        .info-card .page-description,
        .apply-section .page-description {
            margin: 0 0 12px !important;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: #fff6e7;
            color: #8a4b00;
            border: 1px solid #efcda4;
            font-size: 0.82rem;
            font-weight: 800;
        }
        .status-title {
            margin: 0;
            font-size: 1.08rem;
            font-weight: 700;
            color: var(--bid-page-ink);
        }
        .status-copy {
            color: #302922;
            line-height: 1.75;
        }
        .info-list {
            padding-left: 1.2rem;
            margin: 0 0 12px !important;
        }
        .info-list li + li {
            margin-top: 0.55rem;
        }
        .meta-chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }
        .meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.68rem 0.9rem;
            border-radius: 16px;
            background: #fff;
            border: 1px solid var(--bid-page-border);
            color: #6a4521;
            font-weight: 700;
        }
        .info-note {
            margin-top: 16px !important;
            color: var(--bid-page-muted);
            line-height: 1.75;
        }
        .apply-section {
            padding-top: 12px;
        }
        .apply-actions {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .apply-actions .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 180px;
            padding: 0.56rem 1.35rem !important;
            border-radius: 8px !important;
            font-size: 0.95rem !important;
            font-weight: 700 !important;
            line-height: 1.2 !important;
        }
        .apply-btn {
            min-width: 180px;
        }
        .digital-id-btn,
        .lost-report-btn {
            background: linear-gradient(135deg, #ff9a3d 0%, #de710c 100%);
            color: #fff;
            border: 1px solid #c76007;
            box-shadow: 0 10px 24px rgba(222, 113, 12, 0.22);
        }
        .digital-id-btn:hover,
        .digital-id-btn:focus-visible,
        .lost-report-btn:hover,
        .lost-report-btn:focus-visible {
            background: linear-gradient(135deg, #ffae58 0%, #c85c00 100%);
            border-color: #b35300;
            color: #fff;
            box-shadow: 0 14px 28px rgba(200, 92, 0, 0.26);
        }
        .apply-note {
            max-width: 780px;
            margin: 14px auto 0;
            font-size: 0.95rem;
            color: var(--bid-page-muted);
            line-height: 1.7;
        }
        .digital-id-note {
            margin: 12px 0 0;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--bid-page-accent-strong);
        }
        @media (max-width: 767.98px) {
            .page-title {
                font-size: 2rem;
            }
            .page-header {
                align-items: flex-start !important;
            }
            .info-card {
                padding: 18px 20px;
            }
            .apply-actions {
                width: 100%;
            }
            .apply-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="d-flex min-vh-100">
        <?php include __DIR__ . '/../includes/resident_sidebar.php'; ?>

        <main id="div-mainDisplay" class="main-content single-service-page flex-grow-1 p-4 p-md-5">
            <div class="page-shell">
                <div class="d-flex align-items-center gap-3 mb-3 page-header">
                    <img src="../../Icons/Dashboard/brgyid.png" class="certificate-icon page-header-icon" alt="Barangay ID Service">
                    <div>
                        <h1 class="page-title mb-1">Barangay ID Application</h1>
                    </div>
                </div>
                <hr>

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
                <p class="page-description mb-4">
                    Welcome to the Barangay San Jose Barangay ID Application. Review the service details below, then submit a new request, renewal, replacement, or open your digital copy once it is completed.
                </p>

                <div class="info-card">
                    <div class="mb-3">
                        <span class="status-pill">
                            <i class="<?= htmlspecialchars($heroBadgeIcon, ENT_QUOTES, 'UTF-8') ?>"></i>
                            <?= htmlspecialchars($heroBadgeText, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                    <h2 class="page-subtitle mb-2">Barangay ID Services and Reminders</h2>
                    <p class="status-title mb-2"><?= htmlspecialchars($actionTitle, ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="page-description status-copy mb-3"><?= htmlspecialchars($actionBody, ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="page-description mb-2">You may use this page to:</p>
                    <ul class="page-description info-list">
                        <li>Apply for a new Barangay ID once you are eligible to submit.</li>
                        <li>Renew your existing Barangay ID within the 3-month renewal window before expiry.</li>
                        <li>Request a replacement after tagging your current Barangay ID as lost.</li>
                        <li>Open your digital Barangay ID once your latest request is completed.</li>
                        <li>Use your issued Barangay ID as local proof of residence for barangay transactions and resident-facing services.</li>
                        <li>Present it when requesting certificates, clearances, permits, and other community-related records.</li>
                    </ul>
                    <?php if ($actionMeta !== []): ?>
                        <div class="meta-chip-row">
                            <?php foreach ($actionMeta as $metaItem): ?>
                                <span class="meta-chip"><?= $metaItem ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <p class="page-description info-note mb-0">
                        Note: Bring additional valid identification whenever a transaction requires secondary proof of identity or extra verification.
                    </p>
                </div>

                <div class="text-center apply-section mt-4">
                    <p class="page-description mb-3">
                        <?= htmlspecialchars($pendingRequestId !== ''
                            ? 'Open your request list below to monitor the status of your active Barangay ID application.'
                            : 'Choose an option below to continue with your Barangay ID request or open your completed digital copy.', ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <div class="apply-actions">
                        <?php if ($pendingRequestId !== ''): ?>
                            <button class="btn apply-btn" type="button" onclick="location.href='<?= htmlspecialchars($documentRequestsUrl, ENT_QUOTES, 'UTF-8') ?>'">View My Requests</button>
                        <?php elseif ($barangayIdState['can_submit_new_request'] ?? false): ?>
                            <button class="btn apply-btn" type="button" data-apply-href="<?= htmlspecialchars($requestFormUrl, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars(match ($submissionMode) {
                                    'renewal' => 'Renew Barangay ID',
                                    'replacement_lost' => 'Request Replacement',
                                    default => 'Open Form',
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
                            : 'Residents with an active valid ID can renew within the 3-month window, or request a replacement after tagging the current ID as lost.', ENT_QUOTES, 'UTF-8') ?>
                    </p>
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
