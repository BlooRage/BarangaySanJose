<?php
require_once __DIR__ . '/../PhpFiles/General/security.php';
require_once __DIR__ . '/../PhpFiles/General/complaintTypeDetails.php';
require_once __DIR__ . '/../PhpFiles/General/recaptcha.php';

$feedbackType = !empty($_GET['success']) ? 'success' : (!empty($_GET['error']) ? 'error' : '');
$feedbackMessage = !empty($_GET['success'])
    ? (string)$_GET['success']
    : (!empty($_GET['error']) ? (string)$_GET['error'] : '');
$complaintTypeConfigJson = htmlspecialchars(json_encode(complaintTypePublicConfig(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
$complaintRecaptchaEnabled = recaptcha_v3_frontend_enabled();
$complaintRecaptchaSiteKey = $complaintRecaptchaEnabled ? recaptcha_v3_site_key() : '';
$areaOptions = [
    'Area 01' => 'San Jose Proper',
    'Area 1A' => 'Litex Village, Abatex Christine Creek, Med. Heights',
    'Area 02' => 'VFW, Amychelle, Christine Villa Parnshey, Villa Ana, Zaniga Farm',
    'Area 03' => 'Relocation',
    'Area 04' => 'Kasiglahan Phase 1-B, Kasiglahan Phase 1-C, Kasiglahan Phase 1-D, Kasiglahan Phase 1-M, Kasiglahan Phase 1-A',
    'Area 05' => 'Kasiglahan Phase 1-K, Kasiglahan Phase 1K1, Kasiglahan Phase 1K2, Kasiglahan Phase 1-E, Kasiglahan Phase 1-G',
    'Area 06' => 'Sub-Urban, Metro Manila Hills',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Guest Complaint Form</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?= htmlspecialchars(appUrl('/Images/favicon_sanjose.png'), ENT_QUOTES, 'UTF-8') ?>?v=20260211">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php if ($complaintRecaptchaEnabled): ?>
        <script src="https://www.google.com/recaptcha/api.js?render=<?= htmlspecialchars($complaintRecaptchaSiteKey, ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endif; ?>
    <link rel="stylesheet" href="../CSS-Styles/Guest-End-CSS/GuestPage.css">
    <link rel="stylesheet" href="../CSS-Styles/NavbarFooterStyle.css">
    <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/applicationForms.css?v=20260704-complaint-all-modals">
    <style>
        :root {
            --guest-paper: #ffffff;
            --guest-paper-soft: #fff8f1;
            --guest-ink: #2e2115;
            --guest-muted: #6b5e52;
            --guest-border: rgba(254, 153, 60, 0.34);
            --guest-accent: #fe993c;
            --guest-accent-dark: #de710c;
            --guest-shadow: 0 18px 36px rgba(58, 39, 23, 0.08);
        }

        body {
            margin: 0;
            background: #ffffff;
            color: var(--guest-ink);
            font-family: "Geist", "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        .guest-shell {
            min-height: auto;
        }

        .guest-main {
            padding-top: 2rem;
            padding-bottom: 2.75rem;
        }

        .form-shell {
            margin-top: 1rem;
        }

        .guest-card {
            background: transparent;
            border: 0;
            box-shadow: none;
            padding: 0;
        }

        .guest-card-header {
            margin-bottom: 1.1rem;
            padding-bottom: 0.95rem;
            border-bottom: 1px solid rgba(254, 153, 60, 0.16);
        }

        .guest-card-header h2 {
            margin: 0 0 0.3rem;
            font-family: "Charis SIL Bold", Georgia, serif;
            font-size: clamp(2rem, 2.8vw, 2.7rem);
            line-height: 1.05;
            letter-spacing: -0.03em;
            color: var(--guest-accent-dark);
        }

        .guest-card-header p {
            margin: 0.35rem 0 0;
            font-size: 0.97rem;
            line-height: 1.55;
            color: var(--guest-muted);
        }

        .page-return-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            min-height: 56px;
            margin-bottom: 1.1rem;
            padding: 0.95rem 1.9rem;
            border-radius: 999px;
            background: #f7efe3;
            color: var(--guest-accent-dark);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 700;
            transition: background-color 0.18s ease, color 0.18s ease, transform 0.18s ease;
        }

        .page-return-btn i {
            font-size: 1.1rem;
            transition: transform 0.18s ease;
        }

        .page-return-btn:hover,
        .page-return-btn:focus-visible {
            background: #f2e4d2;
            color: #b96416;
            transform: translateY(-1px);
        }

        .page-return-btn:hover i,
        .page-return-btn:focus-visible i {
            transform: translateX(-1px);
        }

        .page-return-btn:focus-visible {
            outline: 0;
        }

        .page-form {
            display: grid;
            gap: 1.2rem;
            margin: 0;
        }

        .form-section-card {
            padding: 1.45rem 1.5rem 1.55rem;
            border: 1px solid rgba(254, 153, 60, 0.14);
            border-radius: 1.35rem;
            background: linear-gradient(180deg, rgba(255, 249, 242, 0.95) 0%, #ffffff 100%);
            box-shadow: 0 16px 32px rgba(58, 39, 23, 0.06);
        }

        .form-section-card .form-row {
            margin-bottom: 1rem;
        }

        .form-section-card .form-row:last-of-type {
            margin-bottom: 0;
        }

        .section-heading {
            display: block;
            margin: 0 0 0.35rem;
            font-family: "Charis SIL Bold", Georgia, serif;
            font-size: 1.65rem;
            font-weight: 600;
            color: #212529;
            text-align: left;
        }

        .section-caption {
            margin: 0 0 1.15rem;
            font-size: 0.95rem;
            line-height: 1.6;
            text-align: left;
            color: var(--guest-muted);
        }

        .page-form .top-label {
            margin-bottom: 0.45rem;
            font-weight: 700;
            color: var(--guest-ink);
        }

        .page-form input[type="text"],
        .page-form input[type="email"],
        .page-form input[type="number"],
        .page-form input[type="date"],
        .page-form input[type="time"],
        .page-form .form-select,
        .page-form textarea {
            min-height: 48px;
            border-radius: 0.9rem;
            border: 1px solid rgba(120, 96, 72, 0.26);
            background-color: #ffffff;
            box-shadow: none;
        }

        .page-form textarea {
            min-height: 118px;
        }

        .page-form input[type="text"]:focus,
        .page-form input[type="email"]:focus,
        .page-form input[type="number"]:focus,
        .page-form input[type="date"]:focus,
        .page-form input[type="time"]:focus,
        .page-form .form-select:focus,
        .page-form textarea:focus {
            border-color: rgba(254, 153, 60, 0.78);
            box-shadow: 0 0 0 0.18rem rgba(254, 153, 60, 0.16);
        }

        .complaint-picker-proxy,
        .area-picker-input,
        input[readonly] {
            background: #ffffff !important;
        }

        .field-helper {
            margin-top: 0.45rem;
            color: var(--guest-muted);
            font-size: 0.92rem;
            line-height: 1.55;
        }

        .phone-input-group {
            display: flex;
            align-items: stretch;
            width: 100%;
        }

        .phone-input-prefix {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 72px;
            padding: 0.8rem 0.95rem;
            border: 1px solid rgba(120, 96, 72, 0.26);
            border-right: 0;
            border-radius: 0.9rem 0 0 0.9rem;
            background: #fffaf3;
            color: var(--guest-accent-dark);
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
        }

        .phone-input-group .form-control {
            border-radius: 0 0.9rem 0.9rem 0 !important;
        }

        .phone-input-group:focus-within .phone-input-prefix {
            border-color: rgba(254, 153, 60, 0.78);
            box-shadow: 0 0 0 0.18rem rgba(254, 153, 60, 0.16);
        }

        .otp-feedback {
            color: #1f7a4f;
        }

        .otp-error {
            color: #b42318;
        }

        .otp-verify-row[hidden] {
            display: none !important;
        }

        .process-stage-actions {
            margin-top: 1.5rem;
            padding-top: 1.2rem;
            border-top: 1px solid rgba(254, 153, 60, 0.16);
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .process-stage-note {
            margin: 0.55rem 0 0;
            color: var(--guest-muted);
            line-height: 1.6;
        }

        .agreement-row {
            margin-top: 1.3rem;
            padding-top: 1.15rem;
            border-top: 1px solid rgba(254, 153, 60, 0.16);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .agreement-row .check-item {
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            flex: 1 1 320px;
            line-height: 1.6;
            color: var(--guest-muted);
        }

        .agreement-row .check-item input {
            margin-top: 0.2rem;
            flex: 0 0 auto;
        }

        .process-next-btn,
        .submit-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            min-width: 210px;
            padding: 0.9rem 1.5rem;
            border-radius: 0.95rem;
            border: 1px solid var(--guest-accent);
            background: var(--guest-accent);
            color: #ffffff;
            font-weight: 700;
        }

        .process-next-btn:not(:disabled):hover,
        .submit-btn:not(:disabled):hover {
            box-shadow: 0 14px 28px rgba(222, 113, 12, 0.24);
        }

        .process-next-btn:disabled,
        .submit-btn:disabled {
            opacity: 0.72;
            cursor: not-allowed;
        }

        .appointment-outline-btn {
            border-radius: 0.9rem;
            border: 1px solid var(--guest-accent);
            background: #ffffff;
            color: var(--guest-accent-dark);
            font-weight: 700;
        }

        .appointment-outline-btn:hover,
        .appointment-outline-btn:focus {
            background: #fff4e6;
            border-color: var(--guest-accent-dark);
            color: var(--guest-accent-dark);
        }

        .complaint-modal .modal-content {
            border-radius: 1.35rem;
            border: 1px solid rgba(254, 153, 60, 0.28);
            background: #ffffff;
            overflow: hidden;
        }

        #complaintVerificationStage,
        .complaint-form-modal {
            z-index: 2000;
        }

        .modal-backdrop.show {
            z-index: 1990;
        }

        .complaint-modal--otp .modal-dialog {
            max-width: 820px;
            margin: 1.5rem auto;
        }

        .complaint-modal--otp .modal-content {
            border-radius: 1.9rem;
            border: 1px solid rgba(254, 153, 60, 0.42);
            background: linear-gradient(180deg, #fffaf5 0%, #ffffff 100%);
            box-shadow: 0 28px 72px rgba(58, 39, 23, 0.22);
            max-height: calc(100vh - 5rem);
            overflow: hidden;
        }

        .complaint-modal--otp .modal-header {
            padding: 1.1rem 1.45rem 0.9rem;
            border-bottom: 1px solid rgba(254, 153, 60, 0.16);
        }

        .complaint-modal--otp .modal-title {
            font-family: "Charis SIL Bold", Georgia, serif;
            font-size: clamp(1.45rem, 2vw, 1.95rem);
            line-height: 1.08;
            color: var(--guest-accent-dark);
        }

        .complaint-modal--otp .btn-close {
            width: 1.9rem;
            height: 1.9rem;
            padding: 0;
            margin: 0;
            flex: 0 0 auto;
            border: 1px solid rgba(191, 87, 0, 0.18);
            border-radius: 999px;
            background-color: #fff4e8;
            background-size: 0.85rem;
            opacity: 0.82;
            box-shadow: none;
        }

        .complaint-modal--otp .btn-close:hover,
        .complaint-modal--otp .btn-close:focus {
            opacity: 1;
        }

        .complaint-modal--otp .modal-body {
            padding: 1.1rem 1.45rem 1.45rem;
            background: #ffffff;
            overflow-y: auto;
        }

        .otp-modal-shell {
            display: grid;
            gap: 0.8rem;
        }

        .otp-simple-hero {
            display: grid;
            justify-items: center;
            gap: 0.7rem;
            text-align: center;
        }

        .otp-simple-icon {
            width: clamp(68px, 7vw, 84px);
            height: auto;
        }

        .otp-modal-intro {
            margin: 0;
            max-width: 39rem;
            color: var(--guest-muted);
            font-size: 0.92rem;
            line-height: 1.55;
            text-align: center;
        }

        .otp-modal-card {
            padding: 0.15rem 0 0;
            border: 0;
            border-radius: 0;
            background: transparent;
            box-shadow: none;
        }

        .otp-simple-number {
            margin: 0;
            text-align: center;
            color: var(--guest-muted);
            font-size: 0.95rem;
            line-height: 1.45;
        }

        .otp-recipient-value {
            font-size: 1rem;
            font-weight: 700;
            color: var(--guest-ink);
        }

        .otp-recipient-value.is-empty {
            color: var(--guest-muted);
        }

        .otp-security-note {
            margin: 0.85rem 0 0;
            color: var(--guest-muted);
            font-size: 0.92rem;
            line-height: 1.6;
            text-align: center;
        }

        .otp-send-actions,
        .otp-resend-row {
            display: flex;
            justify-content: center;
        }

        .otp-send-btn {
            min-width: 160px;
            min-height: 52px;
            padding-inline: 1.35rem;
        }

        .complaint-modal--otp .otp-actions,
        .complaint-modal--otp .otp-verify-row {
            display: grid;
            gap: 0.7rem;
            grid-template-columns: 1fr;
            justify-items: center;
        }

        .complaint-modal--otp .otp-actions {
            align-items: start;
        }

        .complaint-modal--otp .otp-verify-row {
            padding: 0;
            margin-top: 0.1rem;
            border: 0;
            border-radius: 0;
            background: transparent;
        }

        .complaint-modal--otp .otp-verify-row > div:first-child {
            width: 100%;
            display: flex;
            justify-content: center;
        }

        .otp-code-field {
            position: relative;
            width: 100%;
            max-width: 22.5rem;
        }

        .otp-code-boxes {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 0.55rem;
            width: 100%;
        }

        .otp-code-box {
            width: 100%;
            min-width: 42px;
            min-height: 56px;
            aspect-ratio: 1 / 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.9rem;
            border: 1px solid rgba(120, 96, 72, 0.24);
            background: #ffffff;
            color: var(--guest-ink);
            font-size: 1.25rem;
            font-weight: 800;
            line-height: 1;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.92);
            transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
        }

        .otp-code-box.is-filled {
            border-color: rgba(254, 153, 60, 0.42);
            background: linear-gradient(180deg, #ffffff 0%, #fff9f2 100%);
        }

        .otp-code-box.is-active {
            border-color: rgba(254, 153, 60, 0.82);
            box-shadow: 0 0 0 0.18rem rgba(254, 153, 60, 0.14);
            transform: translateY(-1px);
        }

        .otp-code-input {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: text;
            z-index: 2;
        }

        .complaint-modal--otp .otp-feedback,
        .complaint-modal--otp .otp-error {
            margin-top: 0.25rem;
            padding: 0;
            border: 0;
            border-radius: 0;
            font-size: 0.82rem;
            line-height: 1.45;
            text-align: center;
            background: transparent;
        }

        .otp-link-btn {
            appearance: none;
            -webkit-appearance: none;
            display: inline;
            padding: 0;
            border: 0;
            background: transparent;
            color: var(--guest-accent-dark);
            font-weight: 700;
            text-decoration: underline;
            text-underline-offset: 0.2rem;
            box-shadow: none !important;
        }

        .otp-link-btn:hover,
        .otp-link-btn:focus {
            color: var(--guest-accent);
            background: transparent;
        }

        .otp-link-btn:disabled {
            color: var(--guest-muted);
            cursor: default;
            opacity: 1;
        }

        .otp-cert-card {
            padding: 0.85rem 0 0.2rem;
            border: none;
            border-radius: 0;
            background: transparent;
            text-align: center;
        }

        .complaint-modal--otp .submit-btn {
            min-width: 280px;
        }

        .guest-footer-note {
            margin-top: 1.5rem;
            color: var(--guest-muted);
            font-size: 0.95rem;
            text-align: center;
        }

        @media (max-width: 767.98px) {
            .guest-main {
                padding-top: 1.35rem;
                padding-bottom: 2.5rem;
            }

            .guest-card-header h2 {
                font-size: 2rem;
            }

            .form-section-card {
                padding: 1.15rem 1rem 1.2rem;
                border-radius: 1.1rem;
            }

            .section-heading {
                font-size: 1.35rem;
            }

            .agreement-row {
                align-items: stretch;
            }

            .process-stage-actions {
                align-items: stretch;
            }

            .process-next-btn,
            .submit-btn {
                width: 100%;
            }

            .complaint-modal--otp .modal-header,
            .complaint-modal--otp .modal-body {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .complaint-modal--otp .submit-btn {
                min-width: 0;
            }
        }
    </style>
</head>
<body>
    <button onclick="topFunction()" id="goToTop" title="Go to top"><i class="fa-solid fa-arrow-up"></i>&nbsp;&nbsp;Go to top</button>

    <div class="navbarWrapper">
        <nav class="navbar navbar-expand-xl align-items-center navbar-light bg-white shadow-sm">
            <div class="container-fluid align-items-center px-4">
                <a id="navbarBrand" class="navbar-brand" href="<?= htmlspecialchars(appUrl('/'), ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= htmlspecialchars(appUrl('/Images/San_Jose_LOGO.jpg'), ENT_QUOTES, 'UTF-8') ?>" alt="Logo" id="navbarLogo" class="d-inline-block align-text-center">
                    Barangay San Jose
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul id="navbarLinks" class="navbar-nav ms-auto">
                        <li class="nav-item mx-lg-3">
                            <a class="nav-link" href="<?= htmlspecialchars(appUrl('/'), ENT_QUOTES, 'UTF-8') ?>">Home</a>
                        </li>
                        <li class="nav-item mx-lg-3">
                            <a class="nav-link" href="<?= htmlspecialchars(appUrl('/government'), ENT_QUOTES, 'UTF-8') ?>">Government</a>
                        </li>
                        <li class="nav-item mx-lg-3">
                            <a class="nav-link active" aria-current="page" href="<?= htmlspecialchars(appUrl('/services'), ENT_QUOTES, 'UTF-8') ?>">Services</a>
                        </li>
                        <li class="nav-item mx-lg-3">
                            <a class="nav-link" href="<?= htmlspecialchars(appUrl('/news'), ENT_QUOTES, 'UTF-8') ?>">News</a>
                        </li>
                        <li class="nav-item mx-lg-3">
                            <a class="nav-link" href="<?= htmlspecialchars(appUrl('/faq'), ENT_QUOTES, 'UTF-8') ?>">FAQ</a>
                        </li>
                        <li class="nav-item mx-lg-3">
                            <a class="nav-link" href="<?= htmlspecialchars(appUrl('/contact'), ENT_QUOTES, 'UTF-8') ?>">Contact</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-orange text-white px-4 ms-2" href="<?= htmlspecialchars(appUrl('/login'), ENT_QUOTES, 'UTF-8') ?>">Login</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </div>

    <div class="guest-shell">
        <main class="guest-main container">
            <section class="form-shell">
                <div class="guest-card">
                    <div class="guest-card-header">
                        <div>
                            <a class="page-return-btn" href="<?= htmlspecialchars(appUrl('/services'), ENT_QUOTES, 'UTF-8') ?>">
                                <i class="bi bi-arrow-left"></i>
                                Return to Services
                            </a>
                            <h2>Guest Complaint Form</h2>
                            <p>All fields marked with <span class="required-asterisk">*</span> are required. Provide complete complaint details so barangay personnel can review and verify the report promptly.</p>
                        </div>
                    </div>

                    <div
                        id="complaintFeedbackData"
                        data-feedback-type="<?= htmlspecialchars($feedbackType, ENT_QUOTES, 'UTF-8') ?>"
                        data-feedback-message="<?= htmlspecialchars($feedbackMessage, ENT_QUOTES, 'UTF-8') ?>"
                        data-complaint-type-config="<?= $complaintTypeConfigJson ?>"
                        data-recaptcha-enabled="0"
                        data-recaptcha-site-key="<?= htmlspecialchars($complaintRecaptchaSiteKey, ENT_QUOTES, 'UTF-8') ?>"
                        data-recaptcha-action="guest_complaint_otp"
                        hidden
                    ></div>

                    <form class="page-form mb-0" id="complaintForm" method="POST" enctype="multipart/form-data" action="<?= htmlspecialchars(appUrl('/PhpFiles/Guest-End/submitGuestComplaint.php'), ENT_QUOTES, 'UTF-8') ?>">
                <?= csrfTokenField() ?>
                <input type="hidden" name="action" value="submit_complaint">
                <input type="hidden" name="recaptcha_token" id="complaintRecaptchaToken" value="">

                        <section class="form-section-card">
                            <h3 class="section-heading">Complainant Information</h3>
                            <p class="section-caption">Enter the details of the person submitting the complaint so the barangay can validate and follow up on the report.</p>

                <div class="form-row pt-0">
                    <div>
                        <label class="top-label">Last Name <span class="required-asterisk">*</span></label>
                        <input type="text" name="complainant_last_name" required>
                    </div>
                    <div>
                        <label class="top-label">First Name <span class="required-asterisk">*</span></label>
                        <input type="text" name="complainant_first_name" required>
                    </div>
                    <div>
                        <label class="top-label">Middle Name</label>
                        <input type="text" name="complainant_middle_name">
                    </div>
                    <div>
                        <label class="top-label">Suffix</label>
                        <input type="text" name="complainant_suffix" placeholder="None">
                    </div>
                </div>

                <div class="form-row two-col-row">
                    <div>
                        <label class="top-label">Age <span class="required-asterisk">*</span></label>
                        <input type="number" name="complainant_age" min="1" required>
                    </div>
                    <div>
                        <label class="top-label">Sex <span class="required-asterisk">*</span></label>
                        <select class="form-select" name="complainant_sex" required>
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Prefer not to say">Prefer not to say</option>
                        </select>
                    </div>
                </div>

                <div class="form-row two-col-row">
                    <div>
                        <label class="top-label">Email Address</label>
                        <input type="email" name="complainant_email" placeholder="Optional">
                    </div>
                    <div>
                        <label class="top-label">Contact Number <span class="required-asterisk">*</span></label>
                        <div class="phone-input-group">
                            <span class="phone-input-prefix">+63</span>
                            <input
                                type="text"
                                class="form-control"
                                name="complainant_contact_number"
                                inputmode="numeric"
                                autocomplete="tel-national"
                                maxlength="10"
                                pattern="^9\d{9}$"
                                title="Use +63 followed by 10 digits in the format 9XXXXXXXXX."
                                placeholder="9XXXXXXXXX"
                                data-phone-ui="plus63"
                                required
                            >
                        </div>
                        <div class="field-helper">Use your mobile number after +63. This will help barangay personnel reach you for complaint updates or verification.</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="full-width">
                        <label class="top-label">Address <span class="required-asterisk">*</span></label>
                        <textarea name="complainant_address" rows="3" required placeholder="House number, street, barangay, municipality/city, province"></textarea>
                        <div class="field-helper">Enter your current address so barangay personnel can contact you for complaint review or follow-up.</div>
                    </div>
                </div>

                        </section>

                        <section class="form-section-card">
                            <h3 class="section-heading">Complaint Details</h3>
                            <p class="section-caption">Describe when and where the incident happened, what type of concern it is, and any details that can help with assessment.</p>

                <div class="form-row two-col-row">
                    <div>
                        <label class="top-label">Date of the Incident <span class="required-asterisk">*</span></label>
                        <input type="date" id="incidentDate" name="incident_date" class="complaint-picker-proxy" required>
                        <div id="incidentDateError" class="text-danger small mt-1 d-none" aria-live="polite"></div>
                    </div>
                    <div>
                        <label class="top-label">Time of the Incident <i>(recommended)</i></label>
                        <input type="time" id="incidentTime" name="incident_time" class="d-none">
                        <input type="text" id="incidentTimeProxy" class="form-control complaint-picker-proxy" placeholder="Select time" readonly>
                    </div>
                </div>

                <div class="form-row two-col-row">
                    <div>
                        <label class="top-label">Location of the Incident <span class="required-asterisk">*</span></label>
                        <input type="text" name="incident_location" required>
                    </div>
                    <div>
                        <label class="top-label" for="incidentAreaNumberDisplay">Area Number <span class="required-asterisk">*</span></label>
                        <input type="hidden" name="incident_area_number" id="incidentAreaNumber">
                        <input type="text" class="form-control area-picker-input complaint-picker-proxy" id="incidentAreaNumberDisplay" placeholder="Select area" readonly required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="full-width">
                        <label class="top-label">Nature of Complaint <span class="required-asterisk">*</span></label>
                        <select id="natureOfComplaint" name="nature_of_complaint" required>
                            <option value="">Select</option>
                            <option value="Disturbance">Disturbance</option>
                            <option value="Property Dispute">Property Dispute</option>
                            <option value="Noise Complaint">Noise Complaint</option>
                            <option value="Physical Altercation / Violence">Physical Altercation / Violence</option>
                            <option value="Barangay Safety Hazard">Barangay Safety Hazard</option>
                            <option value="General Concern">General Concern</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-row d-none" id="natureOtherWrap">
                    <div class="full-width">
                        <label class="top-label">If Other, please specify <span id="natureOtherAsterisk" class="required-asterisk d-none">*</span></label>
                        <input type="text" id="natureOther" name="nature_other">
                    </div>
                </div>

                <div id="complaintTypeDynamicFields" class="d-none"></div>

                <div class="form-row">
                    <div class="full-width">
                        <label class="top-label">Short narration of the incident <span class="required-asterisk">*</span></label>
                        <textarea name="incident_narration" rows="6" required></textarea>
                        <div class="field-helper">Summarize what happened, who was involved, and any immediate impact on peace, order, or safety.</div>
                    </div>
                </div>

                <div id="complaintAttachmentSection" class="d-none">
                    <div id="complaintAttachmentRows">
                        <?php for ($attachmentIndex = 1; $attachmentIndex <= 3; $attachmentIndex++): ?>
                            <div class="form-row d-none" data-complaint-attachment-row="<?= $attachmentIndex ?>">
                                <div class="full-width">
                                    <label class="top-label" for="complaintImage<?= $attachmentIndex ?>">Attachment <?= $attachmentIndex ?></label>
                                    <div class="complaint-upload-wrap complaint-upload-wrap--with-close">
                                        <button type="button" class="attachment-close-btn attachment-row-close-btn" data-attachment-remove-btn aria-label="Remove attachment <?= $attachmentIndex ?>">X</button>
                                        <label class="upload-dropzone" data-upload-input="complaintImage<?= $attachmentIndex ?>" for="complaintImage<?= $attachmentIndex ?>">
                                            <i class="fa-solid fa-upload"></i>
                                            <div id="complaintImagePrompt<?= $attachmentIndex ?>"><?= $attachmentIndex === 1 ? 'Drag and drop image or click to upload' : 'Drag and drop additional image or click to upload' ?></div>
                                            <small id="complaintImage<?= $attachmentIndex ?>Meta">JPG, JPEG, PNG, or WEBP. Optional.</small>
                                            <input type="file" class="form-control upload-dropzone-input" id="complaintImage<?= $attachmentIndex ?>" name="complaint_images[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" disabled>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="full-width d-flex justify-content-start">
                        <button type="button" class="btn complaint-action-btn" id="addComplaintAttachmentBtn">
                            <i class="fa-regular fa-image" aria-hidden="true"></i>
                            <span>Add Image</span>
                        </button>
                    </div>
                </div>

                        </section>

                        <section class="form-section-card">
                            <h3 class="section-heading">Witness Information</h3>
                            <p class="section-caption">Add witness details only if there are people who can help confirm the incident. You may leave this section at “No” if none are available.</p>

                <div class="form-row">
                    <div class="full-width">
                        <label class="top-label">Do you have a witness? <span class="required-asterisk">*</span></label>
                        <select class="form-select" id="hasWitnesses" name="has_witnesses" required>
                            <option value="">Select</option>
                            <option value="No">No</option>
                            <option value="Yes">Yes</option>
                        </select>
                    </div>
                </div>

                <div id="witnessRowsWrap" class="d-none">
                    <div class="id-guidance-card mb-3">
                        <div class="id-guidance-card__title">Witness Guide</div>
                        <div class="id-guidance-card__meta">You can add up to 3 witnesses. Last name, first name, and contact number are required for each witness you add. Full address is optional.</div>
                    </div>

                    <?php for ($witnessIndex = 1; $witnessIndex <= 3; $witnessIndex++): ?>
                        <div class="witness-entry<?= $witnessIndex === 1 ? '' : ' d-none' ?>" data-witness-row="<?= $witnessIndex ?>">
                            <div class="witness-entry-card complaint-upload-wrap complaint-upload-wrap--with-close">
                                <button type="button" class="attachment-close-btn witness-remove-btn" data-witness-remove-btn aria-label="Remove witness <?= $witnessIndex ?>">X</button>
                                <div class="form-row">
                                    <div>
                                        <label class="top-label">Last Name <span class="required-asterisk">*</span></label>
                                        <input type="text" name="witness_last_name[]">
                                    </div>
                                    <div>
                                        <label class="top-label">First Name <span class="required-asterisk">*</span></label>
                                        <input type="text" name="witness_first_name[]">
                                    </div>
                                    <div>
                                        <label class="top-label">Middle Initial</label>
                                        <input type="text" name="witness_middle_name[]" maxlength="10">
                                    </div>
                                    <div>
                                        <label class="top-label">Suffix</label>
                                        <select class="form-select" name="witness_suffix[]">
                                            <option value="">None</option>
                                            <option value="Jr.">Jr.</option>
                                            <option value="Sr.">Sr.</option>
                                            <option value="III">III</option>
                                            <option value="IV">IV</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-row two-col-row pt-0">
                                    <div>
                                        <label class="top-label">Contact Number <span class="required-asterisk">*</span></label>
                                        <div class="phone-input-group">
                                            <span class="phone-input-prefix">+63</span>
                                            <input
                                                type="text"
                                                class="form-control"
                                                name="witness_contact_number[]"
                                                inputmode="numeric"
                                                autocomplete="tel-national"
                                                maxlength="10"
                                                pattern="^9\d{9}$"
                                                title="Use +63 followed by 10 digits in the format 9XXXXXXXXX."
                                                placeholder="9XXXXXXXXX"
                                                data-phone-ui="plus63"
                                            >
                                        </div>
                                    </div>
                                    <div>
                                        <label class="top-label">Full Address</label>
                                        <input type="text" name="witness_address[]">
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>

                    <div class="form-row">
                        <div class="full-width d-flex justify-content-start">
                            <button type="button" class="btn btn-outline-secondary witness-add-btn" id="addWitnessBtn">Add Another Witness</button>
                        </div>
                    </div>
                </div>

                <div class="agreement-row">
                    <label class="agreement-text check-item" for="agreementComplaint">
                        <input type="checkbox" id="agreementComplaint" name="certify" required>
                        I hereby certify that the above information is true and correct to the best of my knowledge and belief.
                    </label>
                </div>

                <div class="process-stage-actions" id="complaintDetailsActions">
                    <div>
                        <p class="process-stage-note">Confirm the statement above, then continue to OTP verification before submitting the complaint.</p>
                    </div>
                    <button type="button" class="btn process-next-btn" id="complaintNextBtn">NEXT</button>
                </div>
                        </section>

                        <div class="modal fade" id="complaintVerificationStage" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
                            <div class="modal-dialog modal-dialog-centered complaint-modal complaint-modal--otp">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Mobile OTP Verification</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="otp-modal-shell">
                                            <div class="otp-simple-hero">
                                                <img src="<?= htmlspecialchars(appUrl('/Images/SMS-OTP.png'), ENT_QUOTES, 'UTF-8') ?>" alt="OTP Icon" class="otp-simple-icon">
                                                <p class="otp-modal-intro">Check your phone. We’ll send a 6-digit code to the mobile number below before you can submit the complaint.</p>
                                            </div>

                                            <div class="otp-panel otp-modal-card">
                                                <div class="otp-actions">
                                                    <div>
                                                        <p class="otp-simple-number">OTP will be sent to <span class="otp-recipient-value" id="complaintOtpRecipientPreview">+63 •••••• XXXX</span></p>
                                                    </div>
                                                    <div class="otp-send-actions">
                                                        <button type="button" class="btn appointment-outline-btn otp-send-btn" id="complaintSendOtpBtn">Send OTP</button>
                                                    </div>
                                                </div>

                                                <?php if ($complaintRecaptchaEnabled): ?>
                                                    <p class="otp-security-note">Protected by reCAPTCHA. OTP requests are screened automatically before sending.</p>
                                                <?php endif; ?>

                                                <div class="otp-verify-row" id="complaintOtpVerifyRow" hidden>
                                                    <div>
                                                        <div class="otp-code-field">
                                                            <input type="text" class="otp-code-input" id="guestComplaintOtpInput" inputmode="numeric" maxlength="6" autocomplete="one-time-code" aria-label="6-digit OTP">
                                                            <div class="otp-code-boxes" id="guestComplaintOtpBoxes" aria-hidden="true">
                                                                <span class="otp-code-box"></span>
                                                                <span class="otp-code-box"></span>
                                                                <span class="otp-code-box"></span>
                                                                <span class="otp-code-box"></span>
                                                                <span class="otp-code-box"></span>
                                                                <span class="otp-code-box"></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="otp-feedback d-none" id="complaintOtpFeedback" aria-live="polite"></div>
                                                <div class="otp-error d-none" id="complaintOtpError" aria-live="polite"></div>
                                                <div class="otp-resend-row">
                                                    <button type="button" class="otp-link-btn d-none" id="complaintResendOtpBtn">Resend OTP</button>
                                                </div>
                                            </div>

                                            <div class="otp-cert-card">
                                                <button type="submit" class="submit-btn" id="complaintSubmitBtn">SUBMIT COMPLAINT</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>

                    <p class="guest-footer-note">Guest complaints are subject to review and verification by barangay personnel.</p>
                </div>
            </section>
        </main>
    </div>

    <div class="footerWrapper">
        <footer id="footer">
            <div class="container">
                <div class="row">
                    <div class="col-8">
                        <img src="<?= htmlspecialchars(appUrl('/Images/San_Jose_LOGO.jpg'), ENT_QUOTES, 'UTF-8') ?>" alt="Logo" id="footerLogo" class="imgfluid rounded-circle p-3">
                    </div>
                    <div class="col">
                        <div class="footerText">
                            <h5>Quick Links</h5>
                            <ul class="list-unstyled">
                                <li><a id="footerLink" class="link-offset-2 link-underline-light link-underline-opacity-0 link-underline-opacity-75-hover" href="https://www.facebook.com/BarangaySanJoseRodriguezRizal">Facebook</a></li>
                                <li><a id="footerLink" class="link-offset-2 link-underline-light link-underline-opacity-0 link-underline-opacity-75-hover" href="<?= htmlspecialchars(appUrl('/contact'), ENT_QUOTES, 'UTF-8') ?>">Contact Us</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col">
                        <div class="footerText">
                            <h5>Barangay Info</h5>
                            <ul class="list-unstyled">
                                <li><a id="footerLink" class="link-offset-2 link-underline-light link-underline-opacity-0 link-underline-opacity-75-hover" href="<?= htmlspecialchars(appUrl('/privacy#privacy'), ENT_QUOTES, 'UTF-8') ?>">Privacy Policy</a></li>
                                <li><a id="footerLink" class="link-offset-2 link-underline-light link-underline-opacity-0 link-underline-opacity-75-hover" href="<?= htmlspecialchars(appUrl('/privacy#terms'), ENT_QUOTES, 'UTF-8') ?>">Terms & Conditions</a></li>
                                <li><a id="footerLink" class="link-offset-2 link-underline-light link-underline-opacity-0 link-underline-opacity-75-hover" href="<?= htmlspecialchars(appUrl('/privacy#disclaimer'), ENT_QUOTES, 'UTF-8') ?>">Disclaimers</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <div class="modal fade complaint-form-modal" id="complaintTimeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <div class="complaint-form-modal__heading">Select Time</div>
                        <div class="complaint-form-modal__subheading">Choose the incident time or use the current time.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body complaint-form-modal__body">
                    <div class="form-row">
                        <div class="full-width">
                            <label class="top-label" for="incidentTimePicker">Time of the Incident</label>
                            <input type="time" class="form-control" id="incidentTimePicker">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <button type="button" class="btn complaint-form-modal__secondary-btn" id="incidentTimeUseNow">Use current time</button>
                        <div class="small text-muted text-end" id="incidentTimePreview">No time selected yet.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn complaint-form-modal__secondary-btn" id="incidentTimeClearBtn">Clear</button>
                    <button type="button" class="btn complaint-form-modal__primary-btn" id="incidentTimeApplyBtn">Apply</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade complaint-form-modal" id="complaintAreaHelpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable complaint-area-modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title complaint-form-modal__heading">Barangay Area Guide</h5>
                    <button type="button" class="complaint-area-close-btn" data-bs-dismiss="modal" aria-label="Close">×</button>
                </div>
                <div class="modal-body complaint-form-modal__body">
                    <p class="mb-3">Choose the barangay area where the incident happened. If the incident is near a boundary, select the nearest known area.</p>
                    <div class="d-flex flex-column gap-2 complaint-area-options">
                        <?php foreach ($areaOptions as $areaOption => $areaLocation): ?>
                            <button
                                type="button"
                                class="area-guide-option"
                                data-area-value="<?= htmlspecialchars($areaOption, ENT_QUOTES, 'UTF-8') ?>"
                                data-area-label="<?= htmlspecialchars($areaOption . ' - ' . $areaLocation, ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <span class="area-guide-option__title"><?= htmlspecialchars($areaOption, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="area-guide-option__meta"><?= htmlspecialchars($areaLocation, ENT_QUOTES, 'UTF-8') ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../JS-Script-Files/modalHandler.js"></script>
    <script src="../JS-Script-Files/Resident-End/dateFieldModal.js?v=20260704-complaint-all-modals"></script>
    <script>
        let topBtn = document.getElementById("goToTop");

        window.onscroll = function() {
            scrollFunction();
        };

        function scrollFunction() {
            if (!topBtn) {
                return;
            }
            if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
                topBtn.style.display = "block";
            } else {
                topBtn.style.display = "none";
            }
        }

        function topFunction() {
            document.body.scrollTop = 0;
            document.documentElement.scrollTop = 0;
        }
    </script>
    <script>
        document.addEventListener("click", function(event) {
            var navbar = document.getElementById("navbarNav");
            var toggler = document.querySelector(".navbar-toggler");
            if (!navbar || !toggler) {
                return;
            }
            var isShown = navbar.classList.contains("show");
            if (!isShown) {
                return;
            }
            var clickedInside = navbar.contains(event.target) || toggler.contains(event.target);
            if (!clickedInside) {
                var collapse = bootstrap.Collapse.getOrCreateInstance(navbar);
                collapse.hide();
            }
        });
    </script>
    <script src="../JS-Script-Files/Resident-End/complaintScript.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const form = document.getElementById("complaintForm");
            const contactNumberInput = form?.querySelector('input[name="complainant_contact_number"]');
            const nextBtn = document.getElementById("complaintNextBtn");
            const submitBtn = document.getElementById("complaintSubmitBtn");
            const verificationStage = document.getElementById("complaintVerificationStage");
            const verificationModal = verificationStage && window.bootstrap
                ? bootstrap.Modal.getOrCreateInstance(verificationStage, {
                    backdrop: "static",
                    keyboard: true,
                })
                : null;
            const sendOtpBtn = document.getElementById("complaintSendOtpBtn");
            const resendOtpBtn = document.getElementById("complaintResendOtpBtn");
            const otpVerifyRow = document.getElementById("complaintOtpVerifyRow");
            const otpInput = document.getElementById("guestComplaintOtpInput");
            const otpBoxes = Array.from(document.querySelectorAll("#guestComplaintOtpBoxes .otp-code-box"));
            const otpFeedback = document.getElementById("complaintOtpFeedback");
            const otpError = document.getElementById("complaintOtpError");
            const otpRecipientPreview = document.getElementById("complaintOtpRecipientPreview");
            const recaptchaEnabled = <?= $complaintRecaptchaEnabled ? 'true' : 'false' ?>;
            const recaptchaSiteKey = <?= json_encode($complaintRecaptchaSiteKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

            if (!form || !contactNumberInput || !nextBtn || !submitBtn) {
                return;
            }

            let otpSent = false;
            let otpVerified = false;
            let otpSentRecipient = "";
            let otpCountdown = 0;
            let otpCountdownTimer = null;
            let otpStageVisible = false;
            let otpVerificationInFlight = false;
            let focusOtpOnModalOpen = false;

            const normalizePhoneDraft = (value) => {
                let digits = String(value || "").replace(/\D/g, "");
                if (digits.startsWith("63")) {
                    digits = digits.slice(2);
                }
                if (digits.startsWith("0")) {
                    digits = digits.slice(1);
                }
                return digits.slice(0, 10);
            };

            const normalizePhone = (value) => {
                const digits = normalizePhoneDraft(value);
                return /^9\d{9}$/.test(digits) ? digits : "";
            };

            const clearOtpMessages = () => {
                if (otpFeedback) {
                    otpFeedback.textContent = "";
                    otpFeedback.classList.add("d-none");
                }
                if (otpError) {
                    otpError.textContent = "";
                    otpError.classList.add("d-none");
                }
            };

            const showOtpFeedback = (message, isError = false) => {
                const target = isError ? otpError : otpFeedback;
                const other = isError ? otpFeedback : otpError;
                if (other) {
                    other.textContent = "";
                    other.classList.add("d-none");
                }
                if (target) {
                    target.textContent = String(message || "").trim();
                    target.classList.toggle("d-none", target.textContent === "");
                }
            };

            const syncOtpBoxes = () => {
                if (!otpInput) {
                    return;
                }
                const value = String(otpInput.value || "").replace(/\D/g, "").slice(0, 6);
                if (otpInput.value !== value) {
                    otpInput.value = value;
                }

                otpBoxes.forEach((box, index) => {
                    const digit = value[index] || "";
                    box.textContent = digit;
                    box.classList.toggle("is-filled", digit !== "");
                    box.classList.remove("is-active");
                });

                if (document.activeElement === otpInput && !otpVerified) {
                    const activeIndex = Math.min(value.length, Math.max(otpBoxes.length - 1, 0));
                    const activeBox = otpBoxes[activeIndex] || otpBoxes[otpBoxes.length - 1] || null;
                    activeBox?.classList.add("is-active");
                }
            };

            const syncOtpRecipientPreview = () => {
                if (!otpRecipientPreview) {
                    return;
                }
                const normalized = normalizePhone(contactNumberInput.value || "");
                if (normalized !== "") {
                    otpRecipientPreview.textContent = `+63 ${normalized}`;
                    otpRecipientPreview.classList.remove("is-empty");
                    return;
                }
                otpRecipientPreview.textContent = "Enter your mobile number above first.";
                otpRecipientPreview.classList.add("is-empty");
            };

            const executeComplaintRecaptcha = async () => {
                if (!recaptchaEnabled) {
                    return "";
                }
                if (!(window.grecaptcha && typeof window.grecaptcha.execute === "function")) {
                    throw new Error("Security check is still loading. Please try again.");
                }

                await new Promise((resolve) => {
                    window.grecaptcha.ready(resolve);
                });

                const token = await window.grecaptcha.execute(recaptchaSiteKey, {
                    action: "guest_complaint_otp",
                });
                if (String(token || "").trim() === "") {
                    throw new Error("Security verification failed. Please try again.");
                }

                return token;
            };

            const getDetailFields = () => Array.from(form.querySelectorAll("input, select, textarea")).filter((field) => {
                if (!(field instanceof HTMLElement)) {
                    return false;
                }
                if (verificationStage?.contains(field)) {
                    return false;
                }
                if ("disabled" in field && field.disabled) {
                    return false;
                }
                if (field instanceof HTMLInputElement && field.type === "hidden") {
                    return false;
                }
                return true;
            });

            const validateDetailsStep = () => {
                const firstInvalidField = getDetailFields().find((field) => {
                    if (typeof field.checkValidity !== "function") {
                        return false;
                    }
                    return !field.checkValidity();
                });
                if (!firstInvalidField) {
                    return true;
                }
                firstInvalidField.reportValidity();
                firstInvalidField.focus();
                return false;
            };

            const hasInvalidDetails = () => getDetailFields().some((field) => {
                if (typeof field.checkValidity !== "function") {
                    return false;
                }
                return !field.checkValidity();
            });

            const updateOtpButtons = () => {
                const canUseCurrentNumber = normalizePhone(contactNumberInput.value || "") !== "" && contactNumberInput.checkValidity();
                if (sendOtpBtn) {
                    sendOtpBtn.classList.toggle("d-none", otpSent || otpVerified);
                    sendOtpBtn.disabled = otpVerified || otpSent || !canUseCurrentNumber;
                    if (!otpSent) {
                        sendOtpBtn.textContent = "Send OTP";
                    }
                }
                if (resendOtpBtn) {
                    resendOtpBtn.classList.toggle("d-none", !otpSent || otpVerified);
                    resendOtpBtn.textContent = otpCountdown > 0 ? `Resend in ${otpCountdown}s` : "Resend OTP";
                    resendOtpBtn.disabled = otpVerified || otpCountdown > 0 || !canUseCurrentNumber;
                }
            };

            const startOtpCountdown = (seconds) => {
                otpCountdown = Math.max(0, Number(seconds || 0));
                if (otpCountdownTimer) {
                    window.clearInterval(otpCountdownTimer);
                }
                updateOtpButtons();
                if (otpCountdown <= 0) {
                    return;
                }
                otpCountdownTimer = window.setInterval(() => {
                    otpCountdown = Math.max(0, otpCountdown - 1);
                    updateOtpButtons();
                    if (otpCountdown <= 0 && otpCountdownTimer) {
                        window.clearInterval(otpCountdownTimer);
                        otpCountdownTimer = null;
                    }
                }, 1000);
            };

            const updateStageState = () => {
                syncOtpRecipientPreview();
                updateOtpButtons();
                submitBtn.disabled = !otpVerified || hasInvalidDetails();
            };

            const setOtpVerifiedState = (verified) => {
                otpVerified = verified === true;
                contactNumberInput.readOnly = otpVerified;
                if (otpVerifyRow) {
                    otpVerifyRow.hidden = otpVerified;
                }
                if (otpInput) {
                    otpInput.disabled = otpVerified;
                }
                syncOtpBoxes();
                updateStageState();
            };

            const resetOtpVerification = () => {
                otpVerified = false;
                otpSent = false;
                otpSentRecipient = "";
                otpCountdown = 0;
                if (otpCountdownTimer) {
                    window.clearInterval(otpCountdownTimer);
                    otpCountdownTimer = null;
                }
                contactNumberInput.readOnly = false;
                if (otpInput) {
                    otpInput.value = "";
                    otpInput.disabled = false;
                }
                if (otpVerifyRow) {
                    otpVerifyRow.hidden = true;
                }
                clearOtpMessages();
                syncOtpBoxes();
                updateStageState();
            };

            const showVerificationStage = (focusOtp = false) => {
                otpStageVisible = true;
                focusOtpOnModalOpen = focusOtp;
                updateStageState();
                if (verificationModal) {
                    verificationModal.show();
                    return;
                }
                window.setTimeout(() => {
                    if (focusOtp) {
                        sendOtpBtn?.focus();
                    }
                }, 180);
            };

            const advanceToVerificationStage = () => {
                if (!validateDetailsStep()) {
                    return false;
                }
                showVerificationStage(true);
                return true;
            };

            const verifyOtpCode = async () => {
                if (otpVerificationInFlight) {
                    return;
                }

                const recipient = normalizePhone(contactNumberInput.value || "");
                const otpValue = String(otpInput?.value || "").trim();
                if (recipient === "") {
                    contactNumberInput.reportValidity();
                    return;
                }
                if (!/^\d{6}$/.test(otpValue)) {
                    showOtpFeedback("Please enter the 6-digit OTP code.", true);
                    otpInput?.focus();
                    return;
                }

                clearOtpMessages();
                otpVerificationInFlight = true;

                try {
                    const formData = new FormData();
                    formData.append("recipient", recipient);
                    formData.append("purpose", "guest_complaint");
                    formData.append("otp", otpValue);

                    const response = await fetch("../PhpFiles/OTPHandlers/verify_otp.php", {
                        method: "POST",
                        body: formData,
                        credentials: "same-origin",
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(String(data.error || "Failed to verify OTP."));
                    }

                    setOtpVerifiedState(true);
                    showOtpFeedback("Mobile number verified. You can now submit the complaint.");
                } catch (error) {
                    showOtpFeedback(error instanceof Error ? error.message : "Failed to verify OTP.", true);
                } finally {
                    otpVerificationInFlight = false;
                }
            };

            nextBtn.addEventListener("click", () => {
                advanceToVerificationStage();
            });

            sendOtpBtn?.addEventListener("click", async () => {
                if (!validateDetailsStep()) {
                    return;
                }

                const recipient = normalizePhone(contactNumberInput.value || "");
                if (recipient === "") {
                    contactNumberInput.reportValidity();
                    return;
                }

                clearOtpMessages();
                sendOtpBtn.disabled = true;
                sendOtpBtn.textContent = "Sending...";
                if (resendOtpBtn) {
                    resendOtpBtn.disabled = true;
                }

                try {
                    const recaptchaToken = await executeComplaintRecaptcha();
                    const formData = new FormData();
                    formData.append("recipient", recipient);
                    formData.append("purpose", "guest_complaint");
                    if (recaptchaToken !== "") {
                        formData.append("recaptcha_token", recaptchaToken);
                    }

                    const response = await fetch("../PhpFiles/OTPHandlers/generate_otp.php", {
                        method: "POST",
                        body: formData,
                        credentials: "same-origin",
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(String(data.error || "Failed to send OTP. Please try again."));
                    }

                    otpSent = true;
                    otpSentRecipient = recipient;
                    if (otpVerifyRow) {
                        otpVerifyRow.hidden = false;
                    }
                    showOtpFeedback("OTP sent successfully. Enter the 6-digit code to verify your mobile number.");
                    startOtpCountdown(60);
                    updateStageState();
                    otpInput?.focus();
                } catch (error) {
                    showOtpFeedback(error instanceof Error ? error.message : "Failed to send OTP. Please try again.", true);
                    updateStageState();
                } finally {
                    if (sendOtpBtn) {
                        sendOtpBtn.textContent = "Send OTP";
                    }
                }
            });

            resendOtpBtn?.addEventListener("click", () => {
                if (resendOtpBtn.disabled) {
                    return;
                }
                sendOtpBtn?.click();
            });

            contactNumberInput.addEventListener("input", () => {
                const draftValue = normalizePhoneDraft(contactNumberInput.value);
                if (contactNumberInput.value !== draftValue) {
                    contactNumberInput.value = draftValue;
                }
                if (!otpVerified && otpSent && normalizePhone(contactNumberInput.value) !== otpSentRecipient) {
                    resetOtpVerification();
                } else {
                    clearOtpMessages();
                    updateStageState();
                }
            });

            contactNumberInput.addEventListener("change", updateStageState);
            contactNumberInput.addEventListener("blur", updateStageState);

            otpInput?.addEventListener("input", () => {
                syncOtpBoxes();
                const otpValue = String(otpInput.value || "").trim();
                if (/^\d{6}$/.test(otpValue) && !otpVerified && !otpVerificationInFlight) {
                    void verifyOtpCode();
                }
            });
            otpInput?.addEventListener("focus", syncOtpBoxes);
            otpInput?.addEventListener("blur", syncOtpBoxes);

            verificationStage?.addEventListener("shown.bs.modal", () => {
                otpStageVisible = true;
                updateStageState();
                if (focusOtpOnModalOpen) {
                    window.setTimeout(() => {
                        sendOtpBtn?.focus();
                    }, 120);
                }
                focusOtpOnModalOpen = false;
            });

            verificationStage?.addEventListener("hidden.bs.modal", () => {
                otpStageVisible = false;
                focusOtpOnModalOpen = false;
            });

            form.addEventListener("submit", (event) => {
                if (otpVerified) {
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();

                if (!otpStageVisible) {
                    advanceToVerificationStage();
                    return;
                }

                if (!validateDetailsStep()) {
                    return;
                }

                showOtpFeedback("Please verify your mobile number through OTP before submitting the complaint.", true);
                if (!otpSent) {
                    sendOtpBtn?.focus();
                    return;
                }
                otpInput?.focus();
            }, true);

            form.addEventListener("input", updateStageState);
            form.addEventListener("change", updateStageState);

            syncOtpRecipientPreview();
            resetOtpVerification();
            syncOtpBoxes();
            updateStageState();
        });
    </script>
</body>
</html>
