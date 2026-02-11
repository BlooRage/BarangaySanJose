<?php
$allowUnregistered = false;
require_once __DIR__ . "/includes/resident_access_guard.php";
require_once "../PhpFiles/GET/getResidentProfile.php";

$data = getResidentProfileData($conn, $_SESSION['user_id']);
$residentinformationtbl = $data['residentinformationtbl'];

$headOfFamilyRaw = $residentinformationtbl['head_of_family'] ?? '';
$headOfFamilyNormalized = strtolower(trim((string)$headOfFamilyRaw));
$isHeadOfFamily = in_array($headOfFamilyNormalized, ['yes', 'true', '1', 'y'], true);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    
  <link rel="icon" href="/Images/favicon_sanjose.png?v=20260211">
<title>Household Profiling</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/residentDashboard.css">
</head>

<body>

    <div class="d-flex" style="min-height: 100vh;">

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

        <main id="div-mainDisplay" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 pb-md-5 pt-md-0 bg-light">

            <div class="main-head text-center py-1 rounded my-2">
                <h3 class="mb-0 text-black">HOUSEHOLD PROFILING</h3>
            </div>
            <hr class="mt-1 mb-2">

            <?php if (!$isHeadOfFamily): ?>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="alert alert-warning mb-0">
                            Only the head of the family can access household profiling.
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>HOUSEHOLD MEMBERS</strong>
                        <button class="btn btn-success btn-sm">Add Household Member</button>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="border rounded p-3 h-100">
                                    <div class="fw-semibold">Member Name</div>
                                    <div class="text-muted small">Relationship</div>
                                    <div class="text-muted small">Age / Sex</div>
                                    <div class="text-muted small">Civil Status</div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="border rounded p-3 h-100">
                                    <div class="fw-semibold">Member Name</div>
                                    <div class="text-muted small">Relationship</div>
                                    <div class="text-muted small">Age / Sex</div>
                                    <div class="text-muted small">Civil Status</div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="border rounded p-3 h-100">
                                    <div class="fw-semibold">Member Name</div>
                                    <div class="text-muted small">Relationship</div>
                                    <div class="text-muted small">Age / Sex</div>
                                    <div class="text-muted small">Civil Status</div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 text-muted small">
                            Only the head of the family can add or manage household members.
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const burgerBtn = document.getElementById("btn-burger");
        const sidebar = document.getElementById("div-sidebarWrapper");

        if (burgerBtn && sidebar) {
            burgerBtn.addEventListener("click", () => {
                sidebar.classList.toggle("show");
            });
        }
    </script>
</body>

</html>
