<?php
$current = basename($_SERVER['PHP_SELF']);

// Group pages by section
$profilingPages = ['ResidentMasterlist.php', 'ResidentArchive.php', 'EditRequests.php', 'SectorMembershipVerification.php'];
$certPages = ['CertificateTracker.php', 'approved.php', 'denied.php'];
$toolsPages = ['AuditLogs.php'];

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isProfilingActive = in_array($current, $profilingPages);
$isCertActive = in_array($current, $certPages);
$isToolsActive = in_array($current, $toolsPages);

$adminDisplayName = "Admin User";
$adminPosition = "Administrator";
if (!empty($_SESSION['user_id']) && isset($conn) && $conn instanceof mysqli) {
    $stmtInfo = $conn->prepare("
        SELECT firstname, middlename, lastname, suffix, role_access, department
        FROM officialinformationtbl
        WHERE user_id = ?
        LIMIT 1
    ");
    if ($stmtInfo) {
        $stmtInfo->bind_param("s", $_SESSION['user_id']);
        $stmtInfo->execute();
        $info = $stmtInfo->get_result()->fetch_assoc();
        if ($info) {
            $fullName = trim(
                $info['firstname'] . ' ' .
                ($info['middlename'] ? $info['middlename'][0] . '. ' : '') .
                $info['lastname'] .
                ($info['suffix'] ? ' ' . $info['suffix'] : '')
            );
            if ($fullName !== '') {
                $adminDisplayName = $fullName;
            }
            $adminPosition = $info['role_access'] ?: ($info['department'] ?: $adminPosition);
        }
        $stmtInfo->close();
    }
}
?>

<div class="d-flex flex-column flex-shrink-0 p-3 bg-white shadow-sm"
     style="width: 280px;"
     id="dashboard-sidebar">

  <!-- LOGO -->
  <a href="AdminDashboard.php" class="d-flex align-items-center pb-3 mb-3 link-dark text-decoration-none border-bottom">
    <img src="../Images/San_Jose_LOGO.jpg" class="me-2" style="width: 32px; height: 32px;">
    <span class="fs-5 fw-semibold logo-name">Barangay San Jose</span>
  </a>

  <div class="sidebar-body d-flex flex-column flex-grow-1">
    <ul class="list-unstyled ps-0 flex-grow-1 mb-0">

      <li class="mb-1 mt-2 text-muted small fw-semibold px-2">Home</li>

      <!-- DASHBOARD -->
      <li class="mb-2">
        <a href="AdminDashboard.php"
           class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $current == 'AdminDashboard.php' ? 'active' : '' ?>"
           style="<?= $current == 'AdminDashboard.php' ? 'outline: none; box-shadow: none;' : '' ?>">
          <i class="fas fa-chart-area"></i> Dashboard
        </a>
      </li>

      <li class="mb-1 mt-3 text-muted small fw-semibold px-2">Management</li>

      <!-- RESIDENT PROFILING -->
      <li class="mb-1">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isProfilingActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#profiling-collapse"
                aria-expanded="<?= $isProfilingActive ? 'true' : 'false' ?>"
                style="<?= $isProfilingActive ? 'outline: none; box-shadow: none;' : '' ?>">
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
              <a href="EditRequests.php"
                 class="link-dark rounded <?= $current == 'EditRequests.php' ? 'active' : '' ?>">
                Edit Requests
              </a>
            </li>
            <li>
              <a href="ResidentArchive.php"
                 class="link-dark rounded <?= $current == 'ResidentArchive.php' ? 'active' : '' ?>">
                Resident Archive
              </a>
            </li>
            <li>
              <a href="SectorMembershipVerification.php"
                 class="link-dark rounded <?= $current == 'SectorMembershipVerification.php' ? 'active' : '' ?>">
                Sector Membership Verification
              </a>
            </li>
          </ul>
        </div>
      </li>

      <!-- HOUSEHOLD PROFILING -->
      <li class="mb-1">
        <a href="HouseholdProfiling.php"
           class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $current == 'HouseholdProfiling.php' ? 'active' : '' ?>"
           style="<?= $current == 'HouseholdProfiling.php' ? 'outline: none; box-shadow: none;' : '' ?>">
          <i class="fa-solid fa-house"></i> Household Profiling
        </a>
      </li>

      <!-- CERTIFICATE ISSUANCE -->
      <li class="mb-2">
        <button class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isCertActive ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                data-bs-target="#cert-collapse"
                aria-expanded="<?= $isCertActive ? 'true' : 'false' ?>">
          <i class="fas fa-file-circle-check"></i> Certificate Issuance
        </button>

        <div class="collapse <?= $isCertActive ? 'show' : '' ?>" id="cert-collapse">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li><a href="CertificateTracker.php" class="link-dark rounded <?= $current == 'CertificateTracker.php' ? 'active' : '' ?>">Tracker</a></li>
            <li><a href="approved.php" class="link-dark rounded <?= $current == 'approved.php' ? 'active' : '' ?>">Approved Documents</a></li>
            <li><a href="denied.php" class="link-dark rounded <?= $current == 'denied.php' ? 'active' : '' ?>">Denied Documents</a></li>
          </ul>
        </div>
      </li>

      <li class="mb-1 mt-3 text-muted small fw-semibold px-2">Tools</li>

      <li class="mb-1">
        <a href="AuditLogs.php"
           class="btn btn-toggle d-flex align-items-center gap-2 rounded <?= $isToolsActive ? 'active' : '' ?>"
           style="<?= $isToolsActive ? 'outline: none; box-shadow: none;' : '' ?>">
          <i class="fas fa-clipboard-list"></i> Audit Logs
        </a>
      </li>

    </ul>

    <hr>

    <div class="sidebar-actions">
      <div class="dropdown mb-2 w-100">
        <a href="#" class="d-flex align-items-center link-dark text-decoration-none dropdown-toggle w-100"
           data-bs-toggle="dropdown">
          <img src="../Images/Profile-Placeholder.png" width="40" height="40" class="rounded-circle me-2">
          <div class="flex-grow-1" style="min-width: 0;">
            <span class="d-block fw-bold text-truncate mb-0"><?= htmlspecialchars($adminDisplayName) ?></span>
            <small class="d-block text-muted text-truncate"><?= htmlspecialchars($adminPosition) ?></small>
          </div>
        </a>
        <ul class="dropdown-menu text-small shadow">
          <li><a class="dropdown-item" href="admin_profile.php">Profile</a></li>
          <li><a class="dropdown-item" href="../PhpFiles/Login/logout.php">Sign out</a></li>
        </ul>
      </div>
    </div>
  </div>
</div>
