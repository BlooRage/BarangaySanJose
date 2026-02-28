<?php
$allowUnregistered = false;
require_once __DIR__ . "/../includes/resident_access_guard.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../../Images/favicon_sanjose.png?v=20260211">
    <title>Clearance Application</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <link rel="stylesheet" href="../../CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/ApplicationLandingPage.css?v=20260228-3">
</head>

<body>
    <div class="d-flex min-vh-100">
        <?php include __DIR__ . '/../includes/resident_sidebar.php'; ?>

        <main id="div-mainDisplay" class="main-content flex-grow-1 p-4 p-md-5 bg-light">
            <h1 class="page-title">Barangay Clearances</h1>
            <hr>

            <p class="page-description">
                Welcome to the Barangay San Jose Online Clearance Application. Please select the clearance you need from the options below to begin your application. Make sure the details you provide are complete and accurate to avoid processing delays.
            </p>

            <p class="section-label">List of clearances:</p>

            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 certificate-grid justify-content-center">
                <div class="col d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="../../Icons/Dashboard/businessclearance.png" class="certificate-icon" alt="For Business Clearance">
                        <h3>FOR BUSINESS CLEARANCE</h3>
                        <p class="certificate-text">
                            Apply for barangay business clearance for new applications, renewals, and compliance checks.
                        </p>
                        <button class="btn apply-btn" type="button" disabled title="Currently unavailable">Apply Now</button>
                    </div>
                </div>

                <div class="col d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="../../Icons/Dashboard/tricycle.png" class="certificate-icon" alt="For Tricycle Permit">
                        <h3>FOR TRICYCLE PERMIT</h3>
                        <p class="certificate-text">Apply for barangay clearance required for tricycle permit processing.</p>
                        <button class="btn apply-btn" type="button" disabled title="Currently unavailable">Apply Now</button>
                    </div>
                </div>

                <div class="col d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="../../Icons/Dashboard/electricity.png" class="certificate-icon" alt="For Electrical Permit">
                        <h3>FOR ELECTRICAL PERMIT</h3>
                        <p class="certificate-text">Apply for barangay clearance required for electrical permit processing.</p>
                        <button class="btn apply-btn" type="button" disabled title="Currently unavailable">Apply Now</button>
                    </div>
                </div>

                <div class="col d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="../../Icons/Dashboard/water.png" class="certificate-icon" alt="For Water Permit">
                        <h3>FOR WATER PERMIT</h3>
                        <p class="certificate-text">Apply for barangay clearance required for water permit processing.</p>
                        <button class="btn apply-btn" onclick="location.href='WaterForm.php'">Apply Now</button>
                    </div>
                </div>

                <div class="col d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="../../Icons/Dashboard/residential.png" class="certificate-icon" alt="For Residential Permit">
                        <h3>FOR RESIDENTIAL PERMIT</h3>
                        <p class="certificate-text">Apply for barangay clearance required for residential permit processing.</p>
                        <button class="btn apply-btn" type="button" disabled title="Currently unavailable">Apply Now</button>
                    </div>
                </div>

                <div class="col d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="../../Icons/Dashboard/commercial.png" class="certificate-icon" alt="For Commercial Permit">
                        <h3>FOR COMMERCIAL PERMIT</h3>
                        <p class="certificate-text">Apply for barangay clearance required for commercial permit processing.</p>
                        <button class="btn apply-btn" type="button" disabled title="Currently unavailable">Apply Now</button>
                    </div>
                </div>

                <!-- <div class="col d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="../../Icons/Dashboard/clearancepermits.png" class="certificate-icon" alt="For Other Permits">
                        <h3>FOR OTHER PERMITS</h3>
                        <p class="certificate-text">Apply for barangay clearance required for other permit processing.</p>
                        <button class="btn apply-btn" onclick="location.href='OtherPermitsForm.php'">Apply Now</button>
                    </div>
                </div> -->
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
