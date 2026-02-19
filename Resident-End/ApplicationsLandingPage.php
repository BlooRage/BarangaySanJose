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

            <div class="row certificate-grid text-center justify-content-center">
                <div class="col-md-6 col-lg-4 d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="../icons/dashboard/cohab.png" class="certificate-icon" alt="">
                        <h3>COHABITATION</h3>
                        <p class="certificate-text">
                            Official proof of common-law partnership for legal or insurance claims.
                        </p>
                        <button class="btn apply-btn" onclick="location.href='Certificates/CohabitationForm.php'">Apply Now</button>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="../icons/dashboard/indigency.png" class="certificate-icon" alt="">
                        <h3>INDIGENCY</h3>
                        <p class="certificate-text">
                            Required for residents seeking financial, medical, or legal assistance.
                        </p>
                        <button class="btn apply-btn" onclick="location.href='Certificates/IndigencyForm.php'">Apply Now</button>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="../icons/dashboard/jobseekers.png" class="certificate-icon" alt="">
                        <h3>FIRST TIME JOB-SEEKERS</h3>
                        <p class="certificate-text">
                            Avail fee waivers for government documents under Republic Act 11261.
                        </p>
                        <button class="btn apply-btn" onclick="location.href='Certificates/FirstTimeJobSeekerForm.php'">Apply Now</button>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="../icons/dashboard/goodmoral.png" class="certificate-icon" alt="">
                        <h3>GOOD MORAL</h3>
                        <p class="certificate-text">
                            Request a good moral certificate for school, employment, or other requirements.
                        </p>
                        <button class="btn apply-btn" onclick="location.href='Certificates/GoodMoralForm.php'">Apply Now</button>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="../icons/dashboard/residency.png" class="certificate-icon" alt="">
                        <h3>RESIDENCY</h3>
                        <p class="certificate-text">
                            Request a residency certificate as proof of address and community residence.
                        </p>
                        <button class="btn apply-btn" onclick="location.href='Certificates/ResidencyForm.php'">Apply Now</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
