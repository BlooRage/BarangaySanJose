<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resident Dashboard - Barangay San Jose</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />

  <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/residentDashboard.css">
</head>

<body>

  <div class="d-flex" style="min-height: 100vh;">

    <!-- ✅ SIDEBAR INCLUDE -->
    <?php include 'includes/resident_sidebar.php'; ?>

    <!-- ✅ MAIN -->
    <main id="div-mainDisplay" class="flex-grow-1 p-4 p-md-5 bg-light">

      <div id="div-welcomeBanner" class="rounded-4 overflow-hidden mb-5 shadow-sm border-orange-thin">
        <div id="div-bannerHeader" class="bg-orange text-center py-3">
          <h3 class="text-white fw-bold mb-0">WELCOME, RESIDENTS OF BARANGAY SAN JOSE!</h3>
        </div>
        <div id="div-bannerBody" class="bg-white p-5 text-center">
          <p id="txt-bannerLorem" class="text-muted mb-0">
            LOREM IPSUM DOLOR SIT AMET, CONSECTETUR ADIPISCING ELIT. SED DO EIUSMOD TEMPOR INCIDIDUNT UT LABORE ET DOLORE MAGNA ALIQUA.
          </p>
        </div>
      </div>

      <h2 id="txt-sectionTitle" class="fw-bold border-bottom pb-2 mb-4">DASHBOARD</h2>

      <div id="div-serviceGrid" class="row g-4 justify-content-center">

        <div class="col-6 col-md-4 col-lg-3">
          <div id="card-serviceRequest-certificates"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm"
               onclick="location.href='#'">
            <img src="../Icons/certManagement.png" alt="Icon" class="img-serviceIcon mb-3">
            <span class="fw-bold text-white small">CERTIFICATE REQUEST</span>
          </div>
        </div>

        <div class="col-6 col-md-4 col-lg-3">
          <div id="card-serviceRequest-clearances"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm"
               onclick="location.href='#'">
            <img src="../Icons/certManagement.png" alt="Icon" class="img-serviceIcon mb-3">
            <span class="fw-bold text-white small">CLEARANCES</span>
          </div>
        </div>

        <div class="col-6 col-md-4 col-lg-3">
          <div id="card-serviceRequest-brgyId"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm"
               onclick="location.href='#'">
            <img src="../Icons/certManagement.png" alt="Icon" class="img-serviceIcon mb-3">
            <span class="fw-bold text-white small">BARANGAY ID</span>
          </div>
        </div>

        <div class="col-6 col-md-4 col-lg-3">
          <div id="card-serviceRequest-appointments"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm"
               onclick="location.href='#'">
            <img src="../Icons/certManagement.png" alt="Icon" class="img-serviceIcon mb-3">
            <span class="fw-bold text-white small">APPOINTMENTS</span>
          </div>
        </div>

      </div>

    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>