<?php
$allowUnregistered = false;
require_once __DIR__ . "/includes/resident_access_guard.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="/Images/favicon_sanjose.png?v=20260211">
    <title>Document Application</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <link rel="stylesheet" href="../CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/ApplicationLandingPage.css">
</head>

<body>
    <div class="d-flex min-vh-100">
        <?php include 'includes/resident_sidebar.php'; ?>

        <main id="div-mainDisplay" class="main-content flex-grow-1">
            <h1 class="page-title">Barangay Documents</h1>
            <hr class="page-divider">

            <p class="page-description">
                Welcome to the Barangay San Jose Online Document Portal. To better serve our community, we have digitized our application process for essential certificates and clearances. Please select the document you require from the list below to begin your application. Ensure all provided information is accurate to avoid delays in processing.
            </p>

            <p class="section-label">List of documents:</p>

            <div class="row certificate-grid text-center">
                <div class="col-md-6 col-lg-3 certificate-card">
                    <img src="../icons/dashboard/cohab.png" class="certificate-icon" alt="">
                    <h3>COHABITATION</h3>
                    <p class="certificate-text">
                        Official proof of common-law partnership for legal or insurance claims.
                    </p>
                    <button class="btn apply-btn" onclick="location.href='CohabitationForm.php'">Apply Now</button>
                </div>

                <div class="col-md-6 col-lg-3 certificate-card">
                    <img src="../icons/dashboard/indigency.png" class="certificate-icon" alt="">
                    <h3>INDIGENCY</h3>
                    <p class="certificate-text">
                        Required for residents seeking financial, medical, or legal assistance.
                    </p>
                    <button class="btn apply-btn" onclick="location.href='IndigencyForm.php'">Apply Now</button>
                </div>

                <div class="col-md-6 col-lg-3 certificate-card">
                    <img src="../icons/dashboard/jobseekers.png" class="certificate-icon" alt="">
                    <h3>FIRST TIME JOB-SEEKERS</h3>
                    <p class="certificate-text">
                        Avail fee waivers for government documents under Republic Act 11261.
                    </p>
                    <button class="btn apply-btn" onclick="location.href='FirstTimeJobSeekersForm.php'">Apply Now</button>
                </div>

                <div class="col-md-6 col-lg-3 certificate-card position-relative">
                    <img src="../icons/dashboard/identity.png" class="certificate-icon" alt="">
                    <h3>IDENTITY</h3>
                    <p class="certificate-text">
                        A standard certification of your identity and residency in our barangay.
                    </p>
                    <button class="btn apply-btn" onclick="location.href='IdentityForm.php'">Apply Now</button>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
