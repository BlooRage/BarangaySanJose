<?php
$current = basename($_SERVER['PHP_SELF']);

// Group pages by section
$profilingPages = ['ResidentMasterlist.php', 'ResidentArchive.php'];
$certPages = ['requests.php', 'approved.php', 'denied.php'];

$isProfilingActive = in_array($current, $profilingPages);
$isCertActive = in_array($current, $certPages);
?>

<div class="d-flex flex-column flex-shrink-0 p-3 bg-white shadow-sm"
     style="width: 280px;"
     id="dashboard-sidebar">

  <!-- LOGO -->
  <a href="AdminDashboard.php" class="d-flex align-items-center pb-3 mb-3 link-dark text-decoration-none border-bottom">
    <img src="../Images/San_Jose_LOGO.jpg" class="me-2" style="width: 32px; height: 32px;">
    <span class="fs-5 fw-semibold">Barangay San Jose</span>
  </a>

  <ul class="list-unstyled ps-0 flex-grow-1">

    <!-- DASHBOARD -->
    <li class="mb-1">
      <a href="AdminDashboard.php"
         class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $current == 'AdminDashboard.php' ? 'active' : '' ?>">
        <i class="fas fa-chart-area"></i> Dashboard
      </a>
    </li>

    <!-- RESIDENT PROFILING -->
    <li class="mb-1">
      <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isProfilingActive ? '' : 'collapsed' ?>"
              data-bs-toggle="collapse"
              data-bs-target="#profiling-collapse"
              aria-expanded="<?= $isProfilingActive ? 'true' : 'false' ?>">
        <i class="fas fa-user-group"></i> Resident Profiling
      </button>

      <div class="collapse <?= $isProfilingActive ? 'show' : '' ?>" id="profiling-collapse">
        <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
          <li>
            <a href="ResidentMasterlist.php"
               class="link-dark rounded <?= $current == 'ResidentMasterlist.php' ? 'active' : '' ?>">
              Masterlist
            </a>
          </li>
          <li>
            <a href="ResidentArchive.php"
               class="link-dark rounded <?= $current == 'ResidentArchive.php' ? 'active' : '' ?>">
              Resident Archive
            </a>
          </li>
        </ul>
      </div>
    </li>

    <!-- CERTIFICATE ISSUANCE -->
    <li class="mb-1">
      <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isCertActive ? '' : 'collapsed' ?>"
              data-bs-toggle="collapse"
              data-bs-target="#cert-collapse"
              aria-expanded="<?= $isCertActive ? 'true' : 'false' ?>">
        <i class="fas fa-file-circle-check"></i> Certificate Issuance
      </button>

      <div class="collapse <?= $isCertActive ? 'show' : '' ?>" id="cert-collapse">
        <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
          <li><a href="requests.php" class="link-dark rounded <?= $current == 'requests.php' ? 'active' : '' ?>">Requests</a></li>
          <li><a href="approved.php" class="link-dark rounded <?= $current == 'approved.php' ? 'active' : '' ?>">Approved Documents</a></li>
          <li><a href="denied.php" class="link-dark rounded <?= $current == 'denied.php' ? 'active' : '' ?>">Denied Documents</a></li>
        </ul>
      </div>
    </li>

  </ul>

  <hr>

  <!-- USER DROPDOWN -->
  <div class="dropdown mt-auto">
    <a href="#" class="d-flex align-items-center link-dark text-decoration-none dropdown-toggle"
       data-bs-toggle="dropdown">
      <img src="../Images/Default Icon.png" width="32" height="32" class="rounded-circle me-2">
      <strong>Juan Dela Cruz</strong>
    </a>

    <ul class="dropdown-menu text-small shadow">
      <li><a class="dropdown-item" href="#">Settings</a></li>
      <li><a class="dropdown-item" href="#">Profile</a></li>
      <li><hr class="dropdown-divider"></li>
      <li><a class="dropdown-item" href="#">Sign out</a></li>
    </ul>
  </div>
</div>