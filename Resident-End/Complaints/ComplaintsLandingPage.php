<?php
$allowUnregistered = false;
require_once __DIR__ . "/../includes/resident_access_guard.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="/Images/favicon_sanjose.png?v=20260211">
    <title>Complaints</title>
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
            <h1 class="page-title">Complaints</h1>
            <hr>

            <p class="page-description">
                Submit concerns and incidents for barangay assistance. Please provide complete and accurate details to help us process your complaint properly.
            </p>

            <p class="section-label">Available service:</p>

            <div class="row certificate-grid text-center justify-content-center">
                <div class="col-md-6 col-lg-4 d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="../../icons/dashboard/complaintsicon.png" class="certificate-icon" alt="Complaints">
                        <h3>COMPLAINT FORM</h3>
                        <p class="certificate-text">
                            File a complaint and provide incident details, involved parties, and witness information.
                        </p>
                        <button class="btn apply-btn" onclick="location.href='ComplaintsForm.php'">Open Form</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
