<?php
$allowUnregistered = false;
require_once __DIR__ . "/../includes/resident_access_guard.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="/Images/favicon_sanjose.png?v=20260211">
    <title>Clearance Application</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <link rel="stylesheet" href="../../CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/ApplicationLandingPage.css">
</head>

<body>
    <div class="d-flex min-vh-100">
        <?php include '../includes/resident_sidebar.php'; ?>

        <main id="div-mainDisplay" class="main-content flex-grow-1">
            <h1 class="page-title">Barangay Clearances</h1>
            <hr>

            <p class="page-description">
                Welcome to the Barangay San Jose Online Clearance Application. Please select the clearance you need from the options below to begin your application. Make sure the details you provide are complete and accurate to avoid processing delays.
            </p>

            <p class="section-label">List of clearances:</p>

            <div class="row certificate-grid text-center justify-content-center">
                <div class="col-md-6 col-lg-4 d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="../../icons/dashboard/brgycert.png" class="certificate-icon" alt="Barangay Certification">
                        <h3>BARANGAY CERTIFICATION</h3>
                        <p class="certificate-text">
                            Apply for a barangay certification for general legal, educational, or personal requirements.
                        </p>
                        <button class="btn apply-btn" onclick="location.href='BarangayCertificationForm.php'">Apply Now</button>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="../../icons/dashboard/clearancepermits.png" class="certificate-icon" alt="Barangay Clearance for Permits">
                        <h3>BARANGAY CLEARANCE FOR PERMITS</h3>
                        <p class="certificate-text">
                            Apply for barangay clearance required for permit processing and related approvals.
                        </p>
                        <button class="btn apply-btn" onclick="location.href='ClearancePermitsForm.php'">Apply Now</button>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="../../icons/dashboard/businessclearance.png" class="certificate-icon" alt="Barangay Business Clearance">
                        <h3>BARANGAY BUSINESS CLEARANCE</h3>
                        <p class="certificate-text">
                            Apply for barangay business clearance for new applications, renewals, and compliance checks.
                        </p>
                        <button class="btn apply-btn" onclick="location.href='BusinessClearanceForm.php'">Apply Now</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
