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
require_once __DIR__ . "/../includes/resident_access_guard.php";
require_once __DIR__ . "/../../PhpFiles/General/documentModuleSettings.php";

$issuanceSettings = dms_resolve_issuance_settings($conn);

$certificateApplyTriggerAttrs = $isResidentVerified
    ? 'data-bs-toggle="modal" data-bs-target="#requirementsModal"'
    : 'data-verify-required="1"';

$documentCards = [
    [
        'icon' => 'cohab.png',
        'alt' => 'Cohabitation Certificate',
        'title' => 'Cohabitation',
        'description' => 'Proof of common-law partnership for legal, insurance, or official requests.',
        'apply_href' => 'CohabitationForm',
        'requirements_title' => 'Cohabitation Requirements',
        'requirements_html' => <<<HTML
<p class="mb-2">Please prepare the following before filing your request:</p>
<ul class="mb-0 ps-3 requirements-top">
    <li class="mb-2">Any valid government-issued ID of the applicant</li>
    <li class="mb-2">Supporting proof that both partners currently share one household</li>
    <li class="mb-2">Basic personal details of your partner for barangay verification</li>
    <li>Additional verification may be requested if the record needs confirmation</li>
</ul>
HTML,
        'fee_label' => 'Fee: Php50.00',
        'fee_class' => 'fee-note--standard',
    ],
    [
        'icon' => 'cohab.png',
        'alt' => 'Certificate of Relationship for Jail Visitation',
        'title' => 'Relationship for Jail Visitation',
        'description' => 'Certificate for relationship verification required during jail visitation processing.',
        'apply_href' => 'CohabitationForm?variant=relationship_jail_visit',
        'requirements_title' => 'Relationship for Jail Visitation Requirements',
        'requirements_html' => <<<HTML
<p class="mb-2">Prepare these details before continuing:</p>
<ul class="mb-0 ps-3 requirements-top">
    <li class="mb-2">Any valid government-issued ID of the applicant</li>
    <li class="mb-2">Proof or supporting details showing your relationship to the person you will visit</li>
    <li class="mb-2">Complete name of the person for visitation processing</li>
    <li>Additional barangay confirmation may be requested when needed</li>
</ul>
HTML,
        'fee_label' => 'Fee: Php50.00',
        'fee_class' => 'fee-note--standard',
    ],
    [
        'icon' => 'indigency.png',
        'alt' => 'Certificate of Indigency',
        'title' => 'Indigency',
        'description' => 'For residents requesting financial, medical, educational, or legal assistance.',
        'apply_href' => 'IndigencyForm',
        'requirements_title' => 'Indigency Requirements',
        'requirements_html' => <<<HTML
<p class="mb-2">Please have these ready before submitting:</p>
<ul class="mb-0 ps-3 requirements-top">
    <li class="mb-2">Any valid government-issued ID of the applicant</li>
    <li class="mb-2">Purpose of request or the office, school, hospital, or institution that requires the certificate</li>
    <li class="mb-2">Supporting details about the assistance being requested</li>
    <li>Barangay residency details must match your resident account information</li>
</ul>
HTML,
        'fee_label' => 'Free',
        'fee_class' => 'fee-note--free',
    ],
    [
        'icon' => 'jobseekers.png',
        'alt' => 'First Time Job Seeker Certificate',
        'title' => 'First Time Job-Seekers',
        'description' => 'Use your first-time job seeker privilege for eligible government document requests.',
        'apply_href' => 'FirstTimeJobSeekerForm',
        'requirements_title' => 'First Time Job-Seekers Requirements',
        'requirements_html' => <<<HTML
<p class="mb-2">Before applying, make sure you have:</p>
<ul class="mb-0 ps-3 requirements-top">
    <li class="mb-2">Any valid government-issued ID of the applicant</li>
    <li class="mb-2">Updated resident account information, especially your address and residency details</li>
    <li class="mb-2">Accurate personal information for the certificate request</li>
    <li>Additional confirmation may be requested to validate first-time job seeker eligibility</li>
</ul>
HTML,
        'fee_label' => 'Free',
        'fee_class' => 'fee-note--free',
    ],
    [
        'icon' => 'goodmoral.png',
        'alt' => 'Certificate of Good Moral',
        'title' => 'Good Moral',
        'description' => 'Commonly requested for school, employment, scholarship, or official requirements.',
        'apply_href' => 'GoodMoralForm',
        'requirements_title' => 'Good Moral Requirements',
        'requirements_html' => <<<HTML
<p class="mb-2">Please prepare these before submission:</p>
<ul class="mb-0 ps-3 requirements-top">
    <li class="mb-2">Any valid government-issued ID of the applicant</li>
    <li class="mb-2">Purpose of request, such as school, work, scholarship, or other official use</li>
    <li class="mb-2">Accurate personal and residency details in your resident account</li>
    <li>Additional barangay verification may be requested depending on the purpose</li>
</ul>
HTML,
        'fee_label' => 'Fee: Php50.00',
        'fee_class' => 'fee-note--standard',
    ],
    [
        'icon' => 'residency.png',
        'alt' => 'Certificate of Residency',
        'title' => 'Residency',
        'description' => 'Proof of your current address and active residency within Barangay San Jose.',
        'apply_href' => 'ResidencyForm',
        'requirements_title' => 'Residency Requirements',
        'requirements_html' => <<<HTML
<p class="mb-2">Please review these before filing:</p>
<ul class="mb-0 ps-3 requirements-top">
    <li class="mb-2">Any valid government-issued ID of the applicant</li>
    <li class="mb-2">Complete and updated address details in your resident account</li>
    <li class="mb-2">Residency information must match your barangay records</li>
    <li>Supporting proof of residence may be requested when additional confirmation is needed</li>
</ul>
HTML,
        'fee_label' => 'Fee: Php50.00',
        'fee_class' => 'fee-note--standard',
    ],
];
$documentCards = array_values(array_filter($documentCards, static function (array $card) use ($issuanceSettings): bool {
    $href = strtolower((string)($card['apply_href'] ?? ''));
    $key = str_contains($href, 'jail') ? 'jail_visitation' : dms_issuance_certificate_key($href);
    return $key === '' || !empty($issuanceSettings['certificates'][$key]['enabled']);
}));
if (empty($issuanceSettings['online_requests_enabled'])) $documentCards = [];
foreach ($documentCards as &$documentCard) {
    if (dms_issuance_certificate_key((string)($documentCard['apply_href'] ?? '')) === 'first_time_job_seeker'
        && empty($issuanceSettings['first_time_job_seeker_exempt'])) {
        $documentCard['fee_label'] = 'Standard certificate fee applies';
        $documentCard['fee_class'] = 'fee-note--standard';
    }
}
unset($documentCard);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="<?= htmlspecialchars($baseUrl) ?>/Images/favicon_sanjose.png?v=20260211">
    <title>Document Application</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <link rel="stylesheet" href="../../CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/ApplicationLandingPage.css?v=20260623-29">
    <style>
        .requirements-top {
            list-style-type: disc;
        }

        .requirements-top > li::marker {
            color: #de710c;
            font-size: 1.05em;
        }
    </style>
</head>

<body class="documents-page documents-page--stacked-grid">
    <div class="d-flex min-vh-100">
        <?php include __DIR__ . '/../includes/resident_sidebar.php'; ?>

        <header id="mobile-header">
            <div class="d-flex align-items-center px-3 py-2 shadow-sm bg-white">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn" id="btn-burger" type="button" aria-label="Open sidebar">
                        <i class="fa-solid fa-bars fa-lg"></i>
                    </button>
                    <img src="<?= htmlspecialchars($baseUrl) ?>/Images/San_Jose_LOGO.jpg" alt="Logo" style="width:32px;height:32px">
                    <span class="logo-name">Barangay San Jose</span>
                </div>
            </div>
        </header>

        <main id="div-mainDisplay" class="main-content flex-grow-1 p-4 p-md-5 bg-light">
            <h1 class="page-title">Barangay Documents</h1>
            <hr>

            <p class="page-description">
                Welcome to the Barangay San Jose Online Document Application. To better serve our community, we have digitized our application process for essential certificates and clearances. Please select the document you require from the list below to begin your application. Ensure all provided information is accurate to avoid delays in processing.
            </p>

            <?php if (!$isResidentVerified): ?>
                <div class="alert alert-warning border-0 shadow-sm mb-4 documents-alert" role="alert">
                    Verify your resident account first to request documents online. If needed, you can still visit the barangay office for walk-in processing.
                </div>
            <?php endif; ?>

            <p class="section-label">List of documents:</p>

            <?php if ($documentCards === []): ?>
                <div class="alert alert-info">Online certificate requests are currently unavailable. Please contact the barangay office.</div>
            <?php endif; ?>

            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 certificate-grid justify-content-center">
                <?php foreach ($documentCards as $card): ?>
                    <div class="col d-flex">
                        <div class="certificate-card card-action w-100">
                            <div class="certificate-card__main">
                                <div class="certificate-icon-wrap">
                                    <img
                                        src="<?= htmlspecialchars($baseUrl) ?>/Icons/Dashboard/<?= htmlspecialchars($card['icon'], ENT_QUOTES, 'UTF-8') ?>"
                                        class="certificate-icon"
                                        alt="<?= htmlspecialchars($card['alt'], ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                </div>
                                <div class="certificate-card__content">
                                    <h3 class="certificate-card__title"><?= htmlspecialchars(strtoupper($card['title']), ENT_QUOTES, 'UTF-8') ?></h3>
                                    <p class="certificate-text">
                                        <?= htmlspecialchars($card['description'], ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                </div>
                            </div>
                            <div class="certificate-card__footer">
                                <button
                                    class="btn apply-btn"
                                    type="button"
                                    <?= $certificateApplyTriggerAttrs ?>
                                    data-title="<?= htmlspecialchars($card['requirements_title'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-apply-href="<?= htmlspecialchars($card['apply_href'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-body="<?= htmlspecialchars($card['requirements_html'], ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    Apply Now
                                </button>
                                <span class="fee-note <?= htmlspecialchars($card['fee_class'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($card['fee_label'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Certificate of Identity intentionally hidden for now. -->
            </div>
        </main>
    </div>

    <div class="modal fade" id="requirementsModal" tabindex="-1" aria-labelledby="requirementsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="requirementsModalLabel">Requirements</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="requirementsModalBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a class="btn btn-primary" id="requirementsProceedBtn" href="#">Proceed to Application</a>
                </div>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../includes/document_issuance_verification_modal.php'; ?>

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
        const requirementsModal = document.getElementById('requirementsModal');
        const verificationRequiredModalEl = document.getElementById('residentVerificationRequiredModal');

        if (requirementsModal) {
            requirementsModal.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget;
                const title = button?.getAttribute('data-title') || 'Requirements';
                const body = button?.getAttribute('data-body') || '';
                const applyHref = button?.getAttribute('data-apply-href') || '#';
                const modalTitle = requirementsModal.querySelector('.modal-title');
                const modalBody = requirementsModal.querySelector('#requirementsModalBody');
                const proceedBtn = requirementsModal.querySelector('#requirementsProceedBtn');

                modalTitle.textContent = title;
                modalBody.innerHTML = body;
                proceedBtn.setAttribute('href', applyHref);
            });
        }

        if (verificationRequiredModalEl && window.bootstrap?.Modal) {
            const verificationRequiredModal = bootstrap.Modal.getOrCreateInstance(verificationRequiredModalEl);
            document.querySelectorAll('[data-verify-required="1"]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    verificationRequiredModal.show();
                });
            });
        }
    </script>
</body>

</html>
