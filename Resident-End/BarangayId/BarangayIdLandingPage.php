<?php
$allowUnregistered = false;
require_once __DIR__ . "/../includes/resident_access_guard.php";
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
        .id-sample-img {
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.18);
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
                <hr>

                <p class="page-description mb-4">
                    Welcome to the Barangay San Jose Online Barangay ID Application. Select the service below to proceed with your Barangay ID request.
                </p>

                <div class="info-card">
                    <h2 class="page-subtitle mb-2">Where You Can Use Your Barangay ID</h2>
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
                </div>

                <div class="mt-4">
                    <h2 class="page-subtitle mb-3">Barangay ID Sample</h2>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="h-100">
                                <img src="../../Images/Barangayid/SAMPLE.png" class="img-fluid rounded id-sample-img" alt="Barangay ID Sample - Front">
                                <p class="page-description mt-2 mb-0 text-center">Front</p>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="h-100">
                                <img src="../../Images/Barangayid/BACK.png" class="img-fluid rounded id-sample-img" alt="Barangay ID Sample - Back">
                                <p class="page-description mt-2 mb-0 text-center">Back</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center apply-section mt-4">
                    <button class="btn apply-btn" type="button" onclick="location.href='<?= htmlspecialchars(appUrl('Resident-End/BarangayId/BarangayIdForm.php')) ?>'">Apply Now</button>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
