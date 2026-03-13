<?php
$allowUnregistered = false;
require_once __DIR__ . "/../includes/resident_access_guard.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../../Images/favicon_sanjose.png?v=20260211">
    <title>Complaints Application</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <link rel="stylesheet" href="../../CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/ApplicationLandingPage.css?v=20260228-3">
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
        .info-list {
            padding-left: 1.2rem;
            margin-bottom: 0;
        }
        .apply-section {
            padding-top: 12px;
        }
        .apply-btn {
            min-width: 180px;
        }
    </style>
</head>

<body>
    <div class="d-flex min-vh-100">
        <?php include __DIR__ . '/../includes/resident_sidebar.php'; ?>

        <main id="div-mainDisplay" class="main-content single-service-page flex-grow-1 p-4 p-md-5">
            <div class="page-shell">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <img src="../../Icons/Dashboard/complaintsicon.png" class="certificate-icon" alt="Complaint Service" style="height: 52px; margin-bottom: 0;">
                    <div>
                        <h1 class="page-title mb-1">Barangay Complaints</h1>
                    </div>
                </div>
                <hr>

                <p class="page-description mb-4">
                    Welcome to the Barangay San Jose Online Complaints Application. Submit your concern with complete details for proper action and response.
                </p>

                <div class="info-card">
                    <h2 class="page-subtitle mb-2">Possible Complaints You Can File</h2>
                    <p class="page-description mb-2">You may report the following:</p>
                    <ul class="page-description info-list">
                        <li>Neighborhood disputes (noise, boundary, property issues)</li>
                        <li>Harassment, threats, or public disturbances</li>
                        <li>Domestic concerns requiring barangay mediation</li>
                        <li>Vandalism, littering, or environmental concerns</li>
                        <li>Obstruction of public pathways or right-of-way</li>
                        <li>Unpermitted activities or violations of barangay ordinances</li>
                        <li>Animal-related issues (strays, nuisance, or safety risks)</li>
                        <li>Business-related concerns within the barangay</li>
                    </ul>
                    <p class="page-description mt-3 mb-0">
                        Note: Provide clear details, dates, and locations to help with verification and action.
                    </p>
                </div>

                <div class="text-center apply-section mt-4">
                    <p class="page-description mb-3">
                        File a complaint to request barangay assistance or intervention.
                    </p>
                    <button class="btn apply-btn" type="button" onclick="location.href='ComplaintsForm.php'">Open Form</button>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
