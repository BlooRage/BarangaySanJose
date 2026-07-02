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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="<?= htmlspecialchars((string)$baseUrl, ENT_QUOTES, 'UTF-8') ?>/Images/favicon_sanjose.png?v=20260211">
    <title>Appointment Application</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <link rel="stylesheet" href="../../CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/ApplicationLandingPage.css?v=20260228-3">
    <style>
        body.appointments-page {
            background: linear-gradient(180deg, #f3f5fa 0%, #fbfcff 22%, #ffffff 100%);
        }

        .appointments-page #div-mainDisplay {
            background: transparent !important;
        }

        .appointments-page .page-title {
            margin-bottom: 1.5rem;
        }

        .appointments-page .page-divider {
            height: 1px;
            margin: 0 0 1.5rem;
            background: #cfc5bc;
        }

        .appointments-page .page-description {
            margin: 0 0 2rem !important;
            color: #212529;
        }

        .appointments-shell {
            width: 100%;
            max-width: none;
        }

        .appointments-card {
            width: 100%;
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 1.35rem;
            padding: 1.7rem 1.8rem 1.75rem;
            border-radius: 2rem;
            border: 1px solid #efcfab;
            background:
                radial-gradient(circle at top right, rgba(255, 228, 188, 0.72), rgba(255, 228, 188, 0) 24%),
                linear-gradient(135deg, #fffefb 0%, #fff8f1 100%);
            box-shadow:
                0 18px 40px rgba(15, 23, 42, 0.07),
                inset 0 1px 0 rgba(255, 255, 255, 0.92);
            overflow: hidden;
        }

        .appointments-card::before {
            content: "";
            position: absolute;
            inset: 0;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95);
            pointer-events: none;
        }

        .appointments-card__main,
        .appointments-card__footer {
            position: relative;
            z-index: 1;
        }

        .appointments-card__main {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            max-width: none;
        }

        .appointments-card__eyebrow {
            margin: 0;
            color: #c96c14;
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .appointments-card__title {
            margin: 0;
            color: #182236;
            font-size: clamp(1.7rem, 2.5vw, 2.3rem);
            line-height: 1.1;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .appointments-card__intro,
        .appointments-card__note,
        .appointments-card__cta-copy {
            margin: 0;
            color: #55647a;
            font-size: 1rem;
            line-height: 1.65;
        }

        .appointments-checklist {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem 2rem;
            width: 100%;
        }

        .appointments-checklist li {
            position: relative;
            padding-left: 1.35rem;
            color: #1f2937;
            font-size: 1rem;
            line-height: 1.55;
        }

        .appointments-checklist li::before {
            content: "";
            position: absolute;
            top: 0.72rem;
            left: 0;
            width: 0.45rem;
            height: 0.45rem;
            border-radius: 50%;
            background: #f58220;
            transform: translateY(-50%);
        }

        .appointments-card__note {
            color: #7c4d17;
            font-weight: 700;
        }

        .appointments-card__footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem 1.25rem;
            padding-top: 0.4rem;
            border-top: 1px solid rgba(239, 207, 171, 0.82);
        }

        .appointments-card__cta-copy {
            max-width: 42rem;
            color: #39485f;
        }

        .appointments-page .apply-btn {
            width: min(100%, 12.5rem);
            min-width: 0;
            margin-top: 0;
            padding: 0.92rem 1.35rem !important;
            border-radius: 0.95rem !important;
            background: #f58220 !important;
            box-shadow: 0 6px 14px rgba(245, 130, 32, 0.16);
            font-size: 1.02rem !important;
            font-weight: 800 !important;
            line-height: 1 !important;
            border: 0 !important;
        }

        .appointments-page .apply-btn:hover {
            background: #e97615 !important;
        }

        @media (max-width: 991.98px) {
            .appointments-checklist {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767.98px) {
            .appointments-page .main-content {
                padding-top: 4.25rem !important;
            }

            .appointments-card {
                width: 100%;
                padding: 1.3rem 1.2rem 1.25rem;
                border-radius: 1.7rem;
                gap: 1.1rem;
            }

            .appointments-card__title {
                font-size: 1.55rem;
            }

            .appointments-card__intro,
            .appointments-card__note,
            .appointments-card__cta-copy,
            .appointments-checklist li {
                font-size: 0.95rem;
            }

            .appointments-card__footer {
                flex-direction: column;
                align-items: stretch;
                border-top: 0;
                padding-top: 0;
            }

            .appointments-page .apply-btn {
                width: 100%;
            }
        }
    </style>
</head>

<body class="documents-page appointments-page">
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
            <div class="appointments-shell">
                <h1 class="page-title">Barangay Appointments</h1>
                <hr class="page-divider">

                <p class="page-description">
                    Welcome to the Barangay San Jose Online Appointment Application. Schedule your visit and submit details ahead for a faster transaction.
                </p>

                <section class="appointments-card">
                    <div class="appointments-card__main">
                        <p class="appointments-card__eyebrow">Appointment Guide</p>
                        <h2 class="appointments-card__title">What you can set an appointment for</h2>
                        <p class="appointments-card__intro">Common appointment purposes include the following:</p>

                        <ul class="appointments-checklist">
                            <li>Assistance programs and social services consultations</li>
                            <li>Meetings for mediation or case updates</li>
                            <li>Community project or event coordination</li>
                            <li>General consultation with barangay staff</li>
                            <li>Follow-up visits for barangay services or submitted concerns</li>
                            <li>Document-related concerns that require an in-person visit</li>
                        </ul>

                        <p class="appointments-card__note">Availability depends on office schedules, so choose your preferred date and time carefully.</p>
                    </div>

                    <div class="appointments-card__footer">
                        <p class="appointments-card__cta-copy">Set an appointment with the barangay office for your selected service, concern, or consultation schedule.</p>
                        <button
                            class="btn apply-btn"
                            type="button"
                            onclick="window.location.href='<?= htmlspecialchars((string)$baseUrl, ENT_QUOTES, 'UTF-8') ?>/Resident-End/Appointments/AppointmentForm.php'"
                        >Open Form</button>
                    </div>
                </section>
            </div>
        </main>
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
</body>

</html>
