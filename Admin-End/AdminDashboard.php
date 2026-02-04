<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
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
    <hr>

    <div class="row g-4 mt-3">
      <div class="col-md-3">
        <div class="border rounded-4 p-4 text-center shadow-sm h-100">
          <img src="../Images/user.png" width="60">
          <p class="mt-3 fw-semibold">USER MANAGEMENT</p>
        </div>
      </div>

      <div class="col-md-3">
        <div class="border rounded-4 p-4 text-center shadow-sm h-100">
          <img src="../Images/employee.png" width="60">
          <p class="mt-3 fw-semibold">EMPLOYEE MANAGEMENT</p>
        </div>
      </div>

      <div class="col-md-3">
        <div class="border rounded-4 p-4 text-center shadow-sm h-100">
          <img src="../Images/certificate.png" width="60">
          <p class="mt-3 fw-semibold">CERTIFICATE REQUEST MANAGEMENT</p>
        </div>
      </div>

      <div class="col-md-3">
        <div class="border rounded-4 p-4 text-center shadow-sm h-100">
          <img src="../Images/certificate.png" width="60">
          <p class="mt-3 fw-semibold">AUDIT LOGS</p>
        </div>
      </div>
    </div>

  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
