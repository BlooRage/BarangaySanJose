<?php
$residentinformationtbl = [
    'firstname' => 'Juan',
    'middlename' => '',
    'lastname' => 'Dela Cruz',
    'suffix' => '',
    'sex' => 'Male',
    'birthdate' => 'January 1, 1999',
    'age' => 18,
    'civil_status' => 'Single',
    'head_of_family' => 'No',
    'voter_status' => 'Registered Voter',
    'occupation' => 'Barista',
    'employment_status' => 'Employed',
    'occupation_detail' => '',
    'religion' => 'Roman Catholic',
    'sector_membership' => 'Student, PWD',
    'emergency_name' => 'Maria Dela Cruz',
    'emergency_contact' => '09123456789',
    'profile_pic' => 'profile_pic_juan.png'
];

$residentaddresstbl = [
    'address_id' => 1,
    'resident_id' => 101,
    'street_number' => '14A',
    'street_name' => 'Chico St',
    'subdivision' => '',
    'area_number' => 'Area 01'
];

$useraccountstbl = [
    'type' => 'Resident',
    'created' => 'March 12, 2024',
    'last_password_change' => 'August 3, 2025',
    'email' => 'juan.delacruz@email.com',
    'phone_number' => '09123456789'
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Resident Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .main-head {
            background-color: #f6e7d9;
            color: #fcdabc;
        }
    </style>
</head>

<body>

    <div class="d-flex min-vh-100">

        <?php include 'includes/resident_sidebar.php'; ?>

        <main class="flex-grow-1 p-4 bg-light">

            <div class="main-head text-center py-3 rounded mb-4">
                <h3 class="mb-0">RESIDENT PROFILE</h3>
            </div>
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between">
                    <strong>PERSONAL INFORMATION</strong>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                        Edit
                    </button>
                </div>

                <div class="card-body">
                    <table class="table table-borderless align-middle">
                        <tr>
                            <td rowspan="6" style="width:30%; text-align:center;">
                                <img src="profile.jpg"
                                    class="img-fluid rounded-circle mb-2"
                                    style="width:200px; height: 200px;">
                            </td>
                            <td><strong>Name:</strong><br>
                                <?= $residentinformationtbl['firstname'] . ' ' . $residentinformationtbl['lastname'] ?>
                            </td>
                            <td><strong>Sex:</strong><br><?= $residentinformationtbl['sex'] ?></td>
                        </tr>

                        <tr>
                            <td><strong>Age:</strong><br><?= $residentinformationtbl['age'] ?></td>
                            <td><strong>Civil Status:</strong><br><?= $residentinformationtbl['civil_status'] ?></td>
                        </tr>

                        <tr>
                            <td><strong>Birthdate:</strong><br><?= $residentinformationtbl['birthdate'] ?></td>
                            <td><strong>Religion:</strong><br><?= $residentinformationtbl['religion'] ?></td>
                        </tr>

                        <tr>
                            <td><strong>Voter Status:</strong><br><?= $residentinformationtbl['voter_status'] ?></td>
                            <td><strong>Head of the Family:</strong><br><?= $residentinformationtbl['head_of_family'] ?></td>
                        </tr>

                        <tr>
                            <td><strong>Occupation:</strong><br><?= $residentinformationtbl['occupation'] ?></td>
                        </tr>

                        <tr>
                            <td><strong>Sector Membership:</strong><br><?= $residentinformationtbl['sector_membership'] ?></td>
                        </tr>
                    </table>
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
                    <p><strong>Contact Person:</strong> <?= $residentinformationtbl['emergency_name'] ?></p>
                    <p><strong>Contact Number:</strong> <?= $residentinformationtbl['emergency_contact'] ?></p>
                </div>
            </div>

             <div class="card shadow-sm">
                <div class="card-header"><strong>ACCOUNT INFORMATION</strong></div>
                <div class="card-body">
                    <p><strong>Account Type:</strong> <?= $useraccountstbl['type'] ?></p>
                    <p><strong>Email:</strong> <?= $useraccountstbl['email'] ?></p>
                    <p><strong>Account Created:</strong> <?= $useraccountstbl['created'] ?></p>
                    <p><strong>Last Password Change:</strong> <?= $useraccountstbl['last_password_change'] ?></p>
                    <p><strong>Password:</strong> •••••••••</p>
                </div>
            </div>

        </main>
    </div>

     <div class="modal fade" id="editProfileModal" tabindex="-1">
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


     <div class="modal fade" id="addAddressModal" tabindex="-1">
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

         <div class="modal fade" id="editEmergencyContactModal" tabindex="-1">
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

</body>

</html>