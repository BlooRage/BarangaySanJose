<?php
require_once "../PhpFiles/General/connection.php";

$stats = [
  'total' => 0,
  'verified' => 0,
  'pending' => 0,
  'denied' => 0
];

$statsQuery = "
  SELECT
    SUM(CASE WHEN s.status_name = 'PendingVerification' THEN 1 ELSE 0 END) AS pending_count,
    SUM(CASE WHEN s.status_name = 'VerifiedResident' THEN 1 ELSE 0 END) AS verified_count,
    SUM(CASE WHEN s.status_name = 'NotVerified' THEN 1 ELSE 0 END) AS denied_count,
    SUM(CASE WHEN s.status_name <> 'Archived' OR s.status_name IS NULL THEN 1 ELSE 0 END) AS total_count
  FROM residentinformationtbl r
  LEFT JOIN statuslookuptbl s ON r.status_id_resident = s.status_id
";

if ($result = $conn->query($statsQuery)) {
  if ($row = $result->fetch_assoc()) {
    $stats['pending'] = (int)($row['pending_count'] ?? 0);
    $stats['verified'] = (int)($row['verified_count'] ?? 0);
    $stats['denied'] = (int)($row['denied_count'] ?? 0);
    $stats['total'] = (int)($row['total_count'] ?? 0);
  }
  $result->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  
  <link rel="icon" href="/Images/favicon_sanjose.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard</title>

  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
</head>

<body>

<div class="d-flex" style="min-height: 100vh;">

  <!-- SIDEBAR INCLUDE -->
  <?php include 'includes/sidebar.php'; ?>

  <!-- MAIN CONTENT -->
  <main class="flex-grow-1 p-4 p-md-5 bg-light" id="main-display">

    <h1 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C; font-size: 48px;">Admin Dashboard</h1>
    <hr><br>

    <section id="dashboard-stats" class="mb-4">
      <div class="stats-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
        <h2 class="stats-title m-0">Overview</h2>
        <span class="stats-subtitle">Snapshot as of <?php echo date('F j, Y'); ?></span>
      </div>
      <div class="row g-3 stats-grid">
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="stats-card">
            <div class="stats-icon bg-amber"><i class="fa-solid fa-file-lines"></i></div>
            <div>
              <div class="stats-label">Pending Verification</div>
              <div class="stats-value"><?php echo number_format($stats['pending']); ?></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="stats-card">
            <div class="stats-icon bg-emerald"><i class="fa-solid fa-clipboard-check"></i></div>
            <div>
              <div class="stats-label">Verified Residents</div>
              <div class="stats-value"><?php echo number_format($stats['verified']); ?></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="stats-card">
            <div class="stats-icon bg-sky"><i class="fa-solid fa-user-group"></i></div>
            <div>
              <div class="stats-label">Total Residents</div>
              <div class="stats-value"><?php echo number_format($stats['total']); ?></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="stats-card">
            <div class="stats-icon bg-rose"><i class="fa-solid fa-file-circle-xmark"></i></div>
            <div>
              <div class="stats-label">Not Verified</div>
              <div class="stats-value"><?php echo number_format($stats['denied']); ?></div>
            </div>
          </div>
        </div>
      </div>
    </section>

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

        <div class="col-6 col-md-4 col-lg-3">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
