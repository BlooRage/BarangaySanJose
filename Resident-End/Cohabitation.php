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
    'area_number' => 'Area 01',
    'unit_number' => 'Unit 5B',
    'barangay' => 'San Jose',
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
    <title>Cohabitation Application</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../CSS-Styles/GeneralStyle.css">
    <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/BarangayId.css">
</head>

<body>

    <div class="d-flex min-vh-100">

        <?php include 'includes/resident_sidebar.php'; ?>

        <main id="div-mainDisplay" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 pb-md-5 pt-md-0 bg-light">

            <div class="main-head application-card orange-card py-3 rounded">
                <div class="main-head-content">

                    <a href="javascript:history.back()" class="back-link">&lt; Go Back</a>
                    <h1 class="form-title">Cohabitation</h1>
                    <p class="form-subtitle">All fields marked with * are required</p>

                    <form method="POST" action="">

                        <!-- PERSONAL INFORMATION -->
                        <h2 class="section-title text-center text-dark">Information</h2>
                        <div class="status-row">
                            <label for="application_date">Application Date:</label>
                            <input type="text" id="application_date" name="application_date" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly>
                        </div>
                        <br>
                        <div class="status-row">
                            <label><input type="checkbox" name="pwd" class="text-center"> PWD</label>
                            <label><input type="checkbox" name="senior" class="text-center"> Senior Citizen</label>
                        </div>
                        <div class="form-row PT-0">
                            <div>
                                <label>First Name <span>*</span> </label>
                                <input type="text" name="first_name" readonly value="<?php echo $residentinformationtbl['firstname']; ?>">
                            </div>
                            <div>
                                <label>Last Name <span>*</span> </label>
                                <input type="text" name="last_name" readonly value="<?php echo $residentinformationtbl['lastname']; ?>">
                            </div>
                            <div>
                                <label>Middle Name</label>
                                <input type="text" name="middle_name" readonly value="<?php echo $residentinformationtbl['middlename']; ?>">
                            </div>

                            <div>
                                <label>Suffix</label>
                                <select name="suffix_name" class="text-bg-light" readonly value="<?php echo $residentinformationtbl['suffix']; ?>">
                                    <option value="">None</option>
                                    <option value="Jr.">Jr.</option>
                                    <option value="Sr.">Sr.</option>
                                    <option value="III">III</option>
                                    <option value="IV">IV</option>
                                </select>
                            </div>
                        </div>

                        <div id="houseSystemWrapper" class="form-row">
                            <div class="full-width">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label" for="unitNumber">Unit / Apartment Number</label>
                                        <input type="text" class="form-control" id="unitNumber" name="unitNumber" readonly value="<?php echo $residentaddresstbl['unit_number']; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="houseNumber">House Number <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="houseNumber" name="houseNumber" readonly value="<?php echo $residentaddresstbl['street_number']; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="streetName">Street Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="streetName" name="streetName" readonly value="<?php echo $residentaddresstbl['street_name']; ?>">
                                    </div>
                                </div>

                                <h2 class="section-title text-center text-dark">Detainee's Information</h2>
                                <div class="form-row PT-0">
                                    <div>
                                        <label>First Name <span>*</span> </label>
                                        <input type="text" name="detainee_first" required>
                                    </div>
                                    <div>
                                        <label>Last Name <span>*</span> </label>
                                        <input type="text" name="detainee_last" required>
                                    </div>
                                    <div>
                                        <label>Middle Name</label>
                                        <input type="text" name="detainee_middle">
                                    </div>

                                    <div>
                                        <label>Suffix</label>
                                        <select name="detainee_suffix">
                                            <option value="">None</option>
                                            <option value="Jr.">Jr.</option>
                                            <option value="Sr.">Sr.</option>
                                            <option value="III">III</option>
                                            <option value="IV">IV</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="full-width">
                                        <label>Kaugnayan (Ex. Anak) <span>*</span> </label>
                                        <input type="text" name="detainee_relationship" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="full-width">
                                        <label>Place of Detention <span>*</span> </label>
                                        <input type="text" name="detainee_place_of_detention" required>
                                    </div>
                                </div>




                                <div class="certification-row">
                                    <label class="certification-text">
                                        <input type="checkbox" required>I hereby certify that the above information is true and correct to the best of my knowledge and belief.
                                    </label>

                                    <button type="submit" class="submit-btn">SUBMIT</button>
                                </div>

                    </form>
                </div>
            </div>
        </main>

    </div>
</body>

</html>