<?php
$allowUnregistered = false;
require_once __DIR__ . "/../includes/resident_access_guard.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="/Images/favicon_sanjose.png?v=20260211">
    <title>Complaints Application</title>
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

        <main id="div-mainDisplay" class="main-content single-service-page flex-grow-1 p-4 p-md-5 bg-light">
            <div class="d-flex align-items-center gap-2 mb-2">
                <img src="../../icons/dashboard/complaintsicon.png" class="certificate-icon" alt="Complaint Service" style="height: 52px; margin-bottom: 0;">
                <h1 class="page-title mb-0">Barangay Complaints</h1>
            </div>
            <hr>

            <p class="page-description">
                Welcome to the Barangay San Jose Online Complaints Application. Select the complaint service below to submit your concern and include complete details for proper action.
            </p>
            <hr>

            <div class="text-center mt-4">
                <p class="page-description mb-4">
                    File a complaint to report issues and request barangay assistance or intervention.
                </p>
                <button class="btn apply-btn" onclick="location.href='ComplaintsForm.php'">Open Form</button>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>

