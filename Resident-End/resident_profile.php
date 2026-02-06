<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id'])) {
    header("Location: ../Guest-End/login.php");
    exit;
}

require_once "../PhpFiles/get/getResidentProfile.php";

$data = getResidentProfileData($conn, $_SESSION['user_id']);
$residentinformationtbl = $data['residentinformationtbl'];
$residentaddresstbl = $data['residentaddresstbl'];
$useraccountstbl = $data['useraccountstbl'];

$profileImage = '../Images/Profile-Placeholder.png';
if (!empty($residentinformationtbl['profile_pic'])) {
    $candidate = $residentinformationtbl['profile_pic'];
    if (file_exists($candidate)) {
        $profileImage = $candidate;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Resident Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="../JS-Script-Files/modalHandler.js" defer></script>
    <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <style>
        .main-head {
            color: #fcdabc;
        }
    </style>
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

    <div class="main-head text-center py-1 rounded mb-0 mt-0">
                <h3 class="mb-0 text-black">RESIDENT PROFILE</h3>
            </div>
            <hr class="mt-1 mb-2">
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between">
                    <strong>PERSONAL INFORMATION</strong>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                        Edit
                    </button>
                </div>

                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-12 col-md-12 col-lg-3 d-flex align-items-center justify-content-center">
                            <img src="<?= htmlspecialchars($profileImage) ?>"
                                class="img-fluid rounded-circle mb-2"
                                style="width:170px; height: 170px;">
                        </div>
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="d-flex flex-column gap-2">
                                <div><strong>Name:</strong> <?= $residentinformationtbl['firstname'] . ' ' . $residentinformationtbl['lastname'] ?></div>
                                <div><strong>Age:</strong> <?= $residentinformationtbl['age'] ?></div>
                                <div><strong>Birthdate:</strong> <?= $residentinformationtbl['birthdate'] ?></div>
                                <div><strong>Sex:</strong> <?= $residentinformationtbl['sex'] ?></div>
                                <div><strong>Civil Status:</strong> <?= $residentinformationtbl['civil_status'] ?></div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-5">
                            <div class="d-flex flex-column gap-2">
                                <div><strong>Religion:</strong> <?= $residentinformationtbl['religion'] ?></div>
                                <div><strong>Voter Status:</strong> <?= $residentinformationtbl['voter_status'] ?></div>
                                <div><strong>Head of the Family:</strong> <?= $residentinformationtbl['head_of_family'] ?></div>
                                <div><strong>Occupation:</strong> <?= $residentinformationtbl['occupation'] ?></div>
                                <div><strong>Sector Membership:</strong> <?= $residentinformationtbl['sector_membership'] ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between">
                    <strong>ADDRESS INFORMATION</strong>
                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addAddressModal">
                        Add Address
                    </button>
                </div>
                <div class="card-body">
                    <?= $residentaddresstbl['street_number'] . ' ' . $residentaddresstbl['street_name'] . ', '
                        . $residentaddresstbl['subdivision'] . ' ' . $residentaddresstbl['area_number'] ?>
                </div>
            </div>
             <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between">
                    <strong>EMERGENCY CONTACT INFORMATION</strong>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editEmergencyContactModal">
                        Edit
                    </button>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <strong>Contact Person:</strong> <?= $residentinformationtbl['emergency_name'] ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <strong>Contact Number:</strong> <?= $residentinformationtbl['emergency_contact'] ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header"><strong>ACCOUNT INFORMATION</strong></div>
                <div class="card-body small">
                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <div><strong>Account Type:</strong> <?= $useraccountstbl['type'] ?></div>
                            <div><strong>Account Created:</strong> <?= $useraccountstbl['created'] ?></div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div><strong>Mobile Number:</strong> +63<?= $useraccountstbl['phone_number'] ?></div>
                            <div><strong>Email:</strong> <?= $useraccountstbl['email'] ?>
                                <span class="text-muted fst-italic ms-2">
                                    <?= ($useraccountstbl['email_verify'] ?? 0) ? 'Verified' : 'Not Verified' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <hr class="my-3">
                    <div class="row g-2">
                        <div class="col-12 col-md-6"><a href="javascript:void(0)">Change Password</a></div>
                        <div class="col-12 col-md-6"><a href="javascript:void(0)">Change Email</a></div>
                        <div class="col-12 col-md-6"><a href="javascript:void(0)">Change Phone Number</a></div>
                        <?php if (!(int)($useraccountstbl['email_verify'] ?? 0)): ?>
                            <div class="col-12 col-md-6"><a href="javascript:void(0)" id="verifyEmailLink">Verify Email</a></div>
                        <?php else: ?>
                            <div class="col-12 col-md-6 text-muted fst-italic">Email already verified</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <div class="modal fade" id="editProfileModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">Edit Profile</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <label class="form-label">Full Name</label>
                    <div class="d-flex gap-2 mb-3">
                        <input class="form-control" value="<?= $residentinformationtbl['firstname'] ?>" placeholder="First Name" >
                        <input class="form-control" value="<?= $residentinformationtbl['middlename'] ?>" placeholder="Middle Name" >
                        <input class="form-control" value="<?= $residentinformationtbl['lastname'] ?>" placeholder="Last Name" >
                        <select class="form-control" value="<?= $residentinformationtbl['suffix'] ?>" placeholder="Suffix (e.g. Jr., Sr.)" >
                            <option value="">N/A</option>
                            <option value="Jr.">Jr.</option>
                            <option value="Sr.">Sr.</option>
                            <option value="III">III</option>
                        </select>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Sex</label>
                            <input class="form-control" value="<?= $residentinformationtbl['sex'] ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Birthdate</label>
                            <input class="form-control" value="<?= $residentinformationtbl['birthdate'] ?>" readonly>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Civil Status</label>
                            <select class="form-control" value="<?= $residentinformationtbl['civil_status'] ?>">
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Widowed">Widowed</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Religion</label>
                            <select class="form-control" value="<?= $residentinformationtbl['religion'] ?>">
                                <option value="Roman Catholic">Roman Catholic</option>
                                <option value="Iglesia ni Cristo">Iglesia ni Cristo</option>
                                <option value="Muslim">Muslim</option>
                                <option value="Others">Others</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">

                         <div class="col-md-6">
                            <label class="form-label">Registered Voter</label>
                            <input class="form-select" name="voter_status" value="<?= $residentinformationtbl['voter_status'] ?>" readonly>
                        </div>

                         <div class="col-md-6">
                            <label class="form-label">Employment Status</label>
                            <select class="form-select" name="employment_status" id="employmentStatus" onchange="toggleOccupation()">
                                <option value="Employed" <?= ($residentinformationtbl['employment_status'] == 'Employed') ? 'selected' : '' ?>>Employed</option>
                                <option value="Unemployed" <?= ($residentinformationtbl['employment_status'] == 'Unemployed') ? 'selected' : '' ?>>Unemployed</option>
                            </select>
                        </div>

                    </div>

                     <div class="row mb-3" id="occupationRow" style="display: none;">
                        <div class="col-md-12">
                            <label class="form-label">Occupation</label>
                            <input type="text"
                                class="form-control"
                                name="occupation"
                                value="<?= $residentinformationtbl['occupation'] ?>">
                        </div>
                    </div>


                    <div class="mb-3">
                        <label class="form-label">Sector Membership</label>
                        <select class="form-select" value="<?= $residentinformationtbl['sector_membership'] ?>">
                            <option value="PWD">Person with Disability (PWD)</option>
                            <option value="Single Parent">Single Parent</option>
                            <option value="Student">Student</option>
                            <option value="Senior Citizen">Senior Citizen</option>
                            <option value="Indigenous People">Indigenous People</option>
                            <option value="NA">N/A</option>
                        </select>
                    </div>
                </div>

                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary">Next</button>
                    </div>

                </div>

            </div>
        </div>
    </div>
    </div>


     <div class="modal fade" id="addAddressModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5>Add Address</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="text" class="form-control mb-2" placeholder="Street Number">
                    <input type="text" class="form-control mb-2" placeholder="Street Name">
                    <input type="text" class="form-control mb-2" placeholder="Subdivision">
                    <input type="text" class="form-control mb-2" placeholder="Area Number">
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-success">Save</button>
                </div>

            </div>
        </div>
    </div>

         <div class="modal fade" id="editEmergencyContactModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-md modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5>Edit Emergency Contact</h5>
                        <button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Contact Person</label>
                            <input class="form-control" value="<?= $residentinformationtbl['emergency_name'] ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <input class="form-control" value="<?= $residentinformationtbl['emergency_contact'] ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            function toggleOccupation() {
                const employmentStatus = document.getElementById('employmentStatus').value;
                const occupationRow = document.getElementById('occupationRow');

                if (employmentStatus === 'Employed') {
                    occupationRow.style.display = 'flex';
                } else {
                    occupationRow.style.display = 'none';
                }
            }

             document.addEventListener('DOMContentLoaded', toggleOccupation);
        </script>

    <script>
        const burgerBtn = document.getElementById("btn-burger");
        const sidebar = document.getElementById("div-sidebarWrapper");

        if (burgerBtn && sidebar) {
            burgerBtn.addEventListener("click", () => {
                sidebar.classList.toggle("show");
            });
        }
    </script>
    <script>
        const verifyEmailLink = document.getElementById("verifyEmailLink");
        if (verifyEmailLink) {
            verifyEmailLink.addEventListener("click", async (e) => {
                e.preventDefault();
                try {
                    const controller = new AbortController();
                    if (window.UniversalModal?.open) {
                        window.UniversalModal.open({
                            title: "Please Wait",
                            message: "Sending verification email...",
                            buttons: [
                                {
                                    label: "Cancel",
                                    class: "btn btn-outline-secondary",
                                    onClick: () => {
                                        controller.abort();
                                    },
                                },
                            ],
                        });
                    }
                    const res = await fetch("../PhpFiles/EmailHandlers/sendEmailVerify.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({}),
                        signal: controller.signal,
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.success) {
                        throw new Error(data.message || "Unable to send verification email.");
                    }
                    if (window.UniversalModal?.open) {
                        window.UniversalModal.open({
                            title: "Verification Email Sent",
                            messageHtml: "An email verification has been sent to your email, click the verify button to proceed.<br><b>The verify link will expire in 15 minutes.</b>",
                            buttons: [{ label: "OK", class: "btn btn-primary" }],
                        });
                    } else {
                        alert("Verification Email Sent\nAn email verification has been sent to your email, click the verify button to proceed. The verify link will expire in 15 minutes.");
                    }
                } catch (err) {
                    if (err?.name === "AbortError" || err?.message === "Aborted" || err?.code === DOMException.ABORT_ERR) {
                        return;
                    }
                    if (window.UniversalModal?.open) {
                        window.UniversalModal.open({
                            title: "Error",
                            message: err?.message || "Unable to send verification email.",
                            buttons: [{ label: "OK", class: "btn btn-danger" }],
                        });
                    } else {
                        alert(err?.message || "Unable to send verification email.");
                    }
                }
            });
        }
    </script>
</body>

</html>
