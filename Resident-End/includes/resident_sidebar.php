<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Dashboard - Barangay San Jose</title>


    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">


    <!-- Bootstrap Icons (for logout icon) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../CSS-Styles/GeneralStyle.css">
    <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <link rel="stylesheet" href="../CSS-Styles/NavbarFooterStyle.css">
</head>


<body>
<?php
$current = basename($_SERVER['PHP_SELF']);

function activeLink($page, $current) {
  return $page === $current ? 'active' : '';
}
?>

<aside id="div-sidebarWrapper"
       class="d-flex flex-column flex-shrink-0 p-3 bg-white border-end shadow-sm"
       style="width: 280px;">

  <!-- LOGO HEADER (ADMIN-STYLE) -->
  <a href="AdminDashboard.php" class="d-flex align-items-center pb-3 mb-3 link-dark text-decoration-none border-bottom">
    <img src="../Images/San_Jose_LOGO.jpg" class="me-2" style="width: 32px; height: 32px;">
    <span class="fs-5 fw-semibold">Barangay San Jose</span>
  </a>

  <!-- RESIDENT PROFILE -->
  <div id="div-sidebarProfile" class="text-center mb-4">
    <img
      src="../Images/Profile-Placeholder.png"
      alt="Avatar"
      id="img-sidebarAvatar"
      class="rounded-circle mb-2 border shadow-sm"
      width="90"
      height="90"
    >
    <h2 id="txt-sidebarName" class="h6 fw-bold mb-0">Juan Dela Cruz</h2>
  </div>

  <!-- NAV LINKS -->
  <nav id="nav-sidebarLinks" class="text-start flex-grow-1">

    <div id="group-navHome" class="mb-3">
      <p class="text-muted small fw-bold mb-1">Home</p>
      <a href="resident_dashboard.php"
         class="a-sidebarLink <?= activeLink('resident_dashboard.php', $current) ?>">
<i class="fa-solid fa-newspaper" style="color: #ff9739;"></i> Dashboard</i>
      </a>
    
    </div>


    <div id="group-navServices" class="mb-3">
      <p class="text-muted small fw-bold mb-1">Services</p>
      <a href="resident_certificates.php"
         class="a-sidebarLink <?= activeLink('resident_certificates.php', $current) ?>">
<i class="fa-solid fa-certificate" style="color: #ff9739;"></i> Certificates</i>

      </a>
      <a href="resident_clearances.php"
         class="a-sidebarLink <?= activeLink('resident_clearances.php', $current) ?>">
<i class="fa-solid fa-file-circle-check fa-sm" style="color: #ff9739;"></i> Clearances</i>

      </a>
      <a href="resident_barangay_id.php"
         class="a-sidebarLink <?= activeLink('resident_barangay_id.php', $current) ?>">
<i class="fa-solid fa-id-badge fa-lg" style="color: #ff9739;"></i> Barangay ID</i>

      </a>
      <a href="resident_complaints.php"
         class="a-sidebarLink <?= activeLink('resident_complaints.php', $current) ?>">
<i class="fa-solid fa-comment-dots" style="color: #ff9739;"></i></i> Complaints</i>

      </a>
      <a href="resident_appointments.php"
         class="a-sidebarLink <?= activeLink('resident_appointments.php', $current) ?>">
<i class="fa-regular fa-calendar-days" style="color: #ff9739;"></i> Appointments</i>

      </a>
    </div>

    <div id="group-navInfo" class="mb-3">
      <p class="text-muted small fw-bold mb-1">Info</p>
      <a href="resident_certificates.php"
         class="a-sidebarLink <?= activeLink('resident_certificates.php', $current) ?>">
<i class="fa-solid fa-bullhorn" style="color: #ff9739;"></i> Announcements</i>

      <a href="resident_appointments.php"
         class="a-sidebarLink <?= activeLink('resident_appointments.php', $current) ?>">
<i class="fa-solid fa-clock-rotate-left" style="color: #ff9739;"></i> Transactions</i>

      </a>

    </div>
  
    
  </nav>

  <hr>

    <!-- ACCOUNT (BOTTOM, ADMIN-STYLE) -->
  <div class="mt-auto">
    <a class="btn btn-outline-info btn-sm w-100 mb-2"
       href="../PhpFiles/Login/logout.php">
      <i class="fa-solid fa-circle-user"></i> Account
    </a>
  </div>
  <!-- LOGOUT (BOTTOM, ADMIN-STYLE) -->
  <div class="mt-auto">
    <a class="btn btn-danger btn-sm w-100"
       href="../PhpFiles/Login/logout.php">
      <i class="bi bi-box-arrow-right me-1"></i> Logout
    </a>
  </div>

</aside>

</body>
</html>
