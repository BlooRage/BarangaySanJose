<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    
  <link rel="icon" href="/Images/favicon_sanjose.png?v=20260211">
<title>Document Application</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../CSS-Styles/GeneralStyle.css">
    <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/ApplicationLandingPage.css">
</head>

<body>
    
    <div class="d-flex min-vh-100">

        <?php include 'includes/resident_sidebar.php'; ?>
        <main class="main-content">

    <h1 class="page-title">
        Barangay Documents
    </h1>
    <hr style="color: #ff7a18 !important;">

    <p class="page-description">
        Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor
        incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis
        nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
        Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu
        fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in
        culpa qui officia deserunt mollit anim id est laborum.
    </p>

    <p class="section-label">
        List of documents:
    </p>

    <div class="row certificate-grid text-center">

        <div class="col-lg-4 certificate-card">
            <img src="../icons/cohab.png" class="certificate-icon" alt="">
            <h3>COHABITATION</h3>
            <p class="certificate-text">
                Lorem ipsum dolor sit amet, consectetur adipiscing elit,
                sed do eiusmod tempor incididunt ut
            </p>
            <button class="btn apply-btn">Apply Now</button>
        </div>

        <div class="col-lg-4 certificate-card">
            <img src="../icons/indigency.png" class="certificate-icon" alt="">
            <h3>INDIGENCY</h3>
            <p class="certificate-text">
                Lorem ipsum dolor sit amet, consectetur adipiscing elit,
                sed do eiusmod tempor incididunt ut
            </p>
            <button class="btn apply-btn">Apply Now</button>
        </div>

        <div class="col-lg-4 certificate-card">
            <img src="../icons/clearance.png" class="certificate-icon" alt="">
            <h3>CLEARANCES</h3>
            <p class="certificate-text">
                Lorem ipsum dolor sit amet, consectetur adipiscing elit,
                sed do eiusmod tempor incididunt ut
            </p>
            <button class="btn apply-btn" onclick="location.href='ClearanceLandingPage.php'">Apply Now</button>
        </div>

        <div class="col-lg-4 certificate-card">
            <img src="../icons/jobseekers.png" class="certificate-icon" alt="">
            <h3>FIRT TIME JOB-SEEKERS</h3>
            <p class="certificate-text">
                Lorem ipsum dolor sit amet, consectetur adipiscing elit,
                sed do eiusmod tempor incididunt ut
            </p>
            <button class="btn apply-btn">Apply Now</button>
        </div>

        <div class="col-lg-4 certificate-card position-relative">
            <img src="../icons/identity.png" class="certificate-icon" alt="">
            <h3>IDENTITY</h3>
            <p class="certificate-text">
                Lorem ipsum dolor sit amet, consectetur adipiscing elit,
                sed do eiusmod tempor incididunt ut
            </p>
            <button class="btn apply-btn" >Apply Now</button>
        </div>

        <div class="col-lg-4 certificate-card">
            <img src="../icons/brgyid.png" class="certificate-icon" alt="">
            <h3>BARANGAY ID</h3>
            <p class="certificate-text">
                Lorem ipsum dolor sit amet, consectetur adipiscing elit,
                sed do eiusmod tempor incididunt ut
            </p>
            <button class="btn apply-btn" onclick="location.href='BarangayIdApplication.php'">Apply Now</button>
        </div>

    </div>
</main>


           





