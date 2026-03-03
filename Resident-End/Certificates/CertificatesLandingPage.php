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
    <link rel="icon" href="<?= htmlspecialchars($baseUrl) ?>/Images/favicon_sanjose.png?v=20260211">
    <title>Document Application</title>
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
            <h1 class="page-title">Barangay Documents</h1>
            <hr>

            <p class="page-description">
                Welcome to the Barangay San Jose Online Document Application. To better serve our community, we have digitized our application process for essential certificates and clearances. Please select the document you require from the list below to begin your application. Ensure all provided information is accurate to avoid delays in processing.
            </p>

            <p class="section-label">List of documents:</p>

            <div class="row certificate-grid text-center justify-content-center">
                <div class="col-md-6 col-lg-4 d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="<?= htmlspecialchars($baseUrl) ?>/Icons/Dashboard/cohab.png" class="certificate-icon" alt="Cohabitation Certificate">
                        <h3>COHABITATION</h3>
                        <p class="certificate-text">
                            Official proof of common-law partnership for legal or insurance claims.
                        </p>
                        <button class="btn apply-btn" type="button" onclick="location.href='CohabitationForm.php'">Apply Now</button>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="<?= htmlspecialchars($baseUrl) ?>/Icons/Dashboard/indigency.png" class="certificate-icon" alt="Certificate of Indigency">
                        <h3>INDIGENCY</h3>
                        <p class="certificate-text">
                            Required for residents seeking financial, medical, or legal assistance.
                        </p>
                        <button class="btn apply-btn" onclick="location.href='IndigencyForm.php'">Apply Now</button>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="<?= htmlspecialchars($baseUrl) ?>/Icons/Dashboard/jobseekers.png" class="certificate-icon" alt="First Time Job Seeker Certificate">
                        <h3>FIRST TIME JOB-SEEKERS</h3>
                        <p class="certificate-text">
                            Avail fee waivers for government documents under Republic Act 11261.
                        </p>
                        <button class="btn apply-btn" type="button" disabled title="Currently unavailable">Apply Now</button>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="<?= htmlspecialchars($baseUrl) ?>/Icons/Dashboard/goodmoral.png" class="certificate-icon" alt="Certificate of Good Moral">
                        <h3>GOOD MORAL</h3>
                        <p class="certificate-text">
                            Request a good moral certificate for school, employment, or other requirements.
                        </p>
                        <button class="btn apply-btn" onclick="location.href='GoodMoralForm.php'">Apply Now</button>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="<?= htmlspecialchars($baseUrl) ?>/Icons/Dashboard/residency.png" class="certificate-icon" alt="Certificate of Residency">
                        <h3>RESIDENCY</h3>
                        <p class="certificate-text">
                            Request a residency certificate as proof of address and community residence.
                        </p>
                        <button class="btn apply-btn" type="button" onclick="location.href='ResidencyForm.php'">Apply Now</button>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4 d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="<?= htmlspecialchars($baseUrl) ?>/Icons/Dashboard/identity.png" class="certificate-icon" alt="Certificate of Identity">
                        <h3>IDENTITY</h3>
                        <p class="certificate-text">
                            Request an identity certificate for official identification and verification purposes.
                        </p>
                        <button class="btn apply-btn" type="button" disabled title="Currently unavailable">Apply Now</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
