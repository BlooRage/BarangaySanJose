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
      src="https://media.istockphoto.com/id/1495088043/vector/user-profile-icon-avatar-or-person-icon-profile-picture-portrait-symbol-default-portrait.jpg"
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
<i class="fa-solid fa-newspaper" style="color: #ff9739;">Dashboard</i>
      </a>
      <a href="resident_account.php"
         class="a-sidebarLink <?= activeLink('resident_account.php', $current) ?>">
<i class="fa-regular fa-circle-user" style="color: #ff9739;">Account</i>
      </a>
    </div>

    <div id="group-navApps" class="mb-3">
      <p class="text-muted small fw-bold mb-1">Applications</p>
      <a href="resident_payments.php"
         class="a-sidebarLink <?= activeLink('resident_payments.php', $current) ?>">
<i class="fa-solid fa-peso-sign" style="color: #ff9739;">Payments</i>
      </a>
      <a href="resident_transactions.php"
         class="a-sidebarLink <?= activeLink('resident_transactions.php', $current) ?>">
<i class="fa-solid fa-clock-rotate-left" style="color: #ff9739;">Transactions</i>

      </a>
    </div>

    <div id="group-navServices" class="mb-3">
      <p class="text-muted small fw-bold mb-1">Services</p>
      <a href="resident_certificates.php"
         class="a-sidebarLink <?= activeLink('resident_certificates.php', $current) ?>">
Certificates <i class="fa-solid fa-certificate" style="color: #ff9739;">Certificates</i>

      </a>
      <a href="resident_clearances.php"
         class="a-sidebarLink <?= activeLink('resident_clearances.php', $current) ?>">
Clearances <i class="fa-solid fa-file-circle-check" style="color: #ff9739;">Clearances</i>

      </a>
      <a href="resident_barangay_id.php"
         class="a-sidebarLink <?= activeLink('resident_barangay_id.php', $current) ?>">
Barangay ID <i class="fa-solid fa-id-badge" style="color: #ff9739;">Barangay ID</i>

      </a>
      <a href="resident_complaints.php"
         class="a-sidebarLink <?= activeLink('resident_complaints.php', $current) ?>">
Complaints <i class="fa-solid fa-person-burst" style="color: #ff9739;">Complaints</i>

      </a>
      <a href="resident_appointments.php"
         class="a-sidebarLink <?= activeLink('resident_appointments.php', $current) ?>">
Appointment <i class="fa-regular fa-calendar-days" style="color: #ff9739;">Appointments</i>

      </a>
    </div>

  </nav>

  <hr>

  <!-- LOGOUT (BOTTOM, ADMIN-STYLE) -->
  <div class="mt-auto">
    <a class="btn btn-outline-danger btn-sm w-100"
       href="../PhpFiles/Login/logout.php">
      <i class="bi bi-box-arrow-right me-1"></i> Logout
    </a>
  </div>

</aside>