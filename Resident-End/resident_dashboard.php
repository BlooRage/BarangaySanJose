<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resident Dashboard - Barangay San Jose</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/residentDashboard.css">
</head>

<body>

  <div class="d-flex" style="min-height: 100vh;">

    <!-- ✅ SIDEBAR INCLUDE -->
    <?php include 'includes/resident_sidebar.php'; ?>

    <header id="mobile-header">
      <div class="d-flex align-items-center px-3 py-2 shadow-sm bg-white">
        <div class="d-flex align-items-center gap-2">
          <button class="btn" id="btn-burger" type="button">
            <i class="fa-solid fa-bars fa-lg"></i>
          </button>
          <img src="../Images/San_Jose_LOGO.jpg" alt="Logo" style="width:32px;height:32px">
          <span class="logo-name">Barangay San Jose</span>
        </div>
      </div>
    </header>

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

      <div id="div-serviceGrid" class="row service-grid justify-content-center gx-4">

        <div class="col-12 col-md-4 col-lg-3">
          <div id="card-serviceRequest-certificates"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm"
               onclick="location.href='#'">
            <i class="fa-solid fa-file-lines fa-2xl mb-3"></i><br>
            <span class="fw-bold small">CERTIFICATE REQUEST</span>
          </div>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <div id="card-serviceRequest-clearances"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm"
               onclick="location.href='#'">
            <i class="fa-solid fa-clipboard-check fa-2xl mb-3"></i><br>
            <span class="fw-bold small">CLEARANCES</span>
          </div>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <div id="card-serviceRequest-brgyId"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm"
               onclick="location.href='#'">
            <i class="fa-solid fa-id-card fa-2xl mb-3"></i><br>
            <span class="fw-bold small">BARANGAY ID</span>
          </div>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <div id="card-serviceRequest-appointments"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm"
               onclick="location.href='#'">
            <i class="fa-solid fa-calendar-check fa-2xl mb-3"></i><br>
            <span class="fw-bold small">APPOINTMENTS</span>
          </div>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <div id="card-serviceRequest-announcements"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm"
               onclick="location.href='#'">
            <i class="fa-solid fa-bullhorn fa-2xl mb-3"></i><br>
            <span class="fw-bold small">ANNOUNCEMENTS</span>
          </div>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <div id="card-serviceRequest-transactions"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm"
               onclick="location.href='#'">
            <i class="fa-solid fa-money-check-dollar fa-2xl mb-3"></i><br>
            <span class="fw-bold small">TRANSACTIONS</span>
          </div>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <div id="card-serviceRequest-complaints"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm"
               onclick="location.href='#'">
            <i class="fa-solid fa-comment-dots fa-2xl mb-3"></i><br>
            <span class="fw-bold small">COMPLAINTS</span>
          </div>
        </div>

        <div class="col-12 col-md-4 col-lg-3">
          <div id="card-serviceRequest-profile"
               class="card-action h-100 p-4 rounded-4 text-center d-flex flex-column align-items-center justify-content-center border-0 shadow-sm"
               onclick="location.href='resident_profile.php'">
            <i class="fa-solid fa-user-circle fa-2xl mb-3"></i><br>
            <span class="fw-bold small">MY PROFILE</span>
          </div>
        </div>

      </div>

    </main>
  </div>

  <script>
    const burgerBtn = document.getElementById("btn-burger");
    const sidebar = document.getElementById("div-sidebarWrapper");

    if (burgerBtn && sidebar) {
      burgerBtn.addEventListener("click", () => {
        sidebar.classList.toggle("show");
      });
    }
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
