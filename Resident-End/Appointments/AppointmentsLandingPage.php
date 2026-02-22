<?php
$allowUnregistered = false;
require_once __DIR__ . "/../includes/resident_access_guard.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="/Images/favicon_sanjose.png?v=20260211">
    <title>Appointments</title>
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
            <h1 class="page-title">Appointments</h1>
            <hr>

            <p class="page-description">
                Schedule an appointment with Barangay San Jose for concerns, consultation, and document-related follow-ups. Please choose the service below to proceed.
            </p>

            <p class="section-label">Available service:</p>

            <div class="row certificate-grid text-center justify-content-center">
                <div class="col-md-6 col-lg-4 d-flex">
                    <div class="certificate-card card-action w-100">
                        <img src="../../icons/dashboard/appointmentsicon.png" class="certificate-icon" alt="Appointments">
                        <h3>APPOINTMENT FORM</h3>
                        <p class="certificate-text">
                            Book your preferred date and time for appointment requests and barangay concerns.
                        </p>
                        <button class="btn apply-btn" onclick="location.href='AppointmentForm.php'">Open Form</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
