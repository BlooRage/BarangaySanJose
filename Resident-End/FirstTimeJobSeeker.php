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
    <title>First Time Job Seeker</title>
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

                    <h1 class="form-title">First Time Job Seekers</h1>
                    <p class="form-subtitle">All fields marked with * are required</p>

                    <form method="POST" action="">

                        <!-- PERSONAL INFORMATION -->
                        <h2 class="section-title text-center text-dark">Personal Information</h2>

                        <div class="status-row">
                            <label for="application_date">Application Date:</label>
                            <input type="text" id="application_date" name="application_date" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly>
                        </div>

                        <div class="form-row">
                            <div>
                                <label>Last Name <span>* </span></label>
                                <input type="text" name="last_name" required>
                            </div>

                            <div>
                                <label>First Name <span>* </span></label>
                                <input type="text" name="first_name" required>
                            </div>

                            <div>
                                <label>Middle Name</label>
                                <input type="text" name="middle_name">
                            </div>

                            <div>
                                <label>Suffix</label>
                                <select name="suffix">
                                    <option value="">None</option>
                                    <option value="Jr.">Jr.</option>
                                    <option value="Sr.">Sr.</option>
                                    <option value="III">III</option>
                                    <option value="IV">IV</option>
                                    <option value="V">Others</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <!-- birthdate and age readonly -->
                            <div class="col-12">
                                <label>Birthdate <span>* </span></label>
                                <input type="text" name="birthdate" value="<?php echo $residentinformationtbl['birthdate']; ?>" readonly>
                            </div>
                            <div class="col-12">
                                <label>Age <span>* </span></label>
                                <input type="text" name="age" value="<?php echo $residentinformationtbl['age']; ?>" readonly>
                            </div>
                            <div class="col-12">
                                <!-- sex/gender readonly -->
                                <label>Sex/Gender <span>* </span></label>
                                <input type="text" name="sex" value="<?php echo $residentinformationtbl['sex']; ?>" readonly>
                            </div>
                            <div class="col-12">
                                <!-- years of residency readonly -->
                                <label>Years of Residency <span>* </span></label>
                                <input type="text" name="years_of_residency" value="<?php echo ($residentaddresstbl['address_id']); ?> years" readonly>
                            </div>
                        </div>
                        <!-- phone numb readonly and educational attainment input -->
                        <div class="form-row" style="grid-template-columns: repeat(2, 1fr);">
                            <div class="col-12">
                                <label>Phone Number <span>* </span></label>
                                <input type="text" name="phone_number" value="<?php echo $useraccountstbl['phone_number']; ?>" readonly>
                            </div>
                            <div class="col-12">
                                <label>Educational Attainment <span>* </span></label>
                                <input type="text" name="educational_attainment" required>
                            </div>
                        </div>
                        <!-- address separate inputs readonly -->
                        <div class="form-row">
                            <div class="col-12">
                                <label>Street Number <span>* </span></label>
                                <input type="text" name="street_number" value="<?php echo $residentaddresstbl['street_number']; ?>" readonly>
                            </div>
                            <div class="col-12">
                                <label>Street Name <span>* </span></label>
                                <input type="text" name="street_name" value="<?php echo $residentaddresstbl['street_name']; ?>" readonly>
                            </div>
                            <div class="col-12">
                                <label>Subdivision</label>
                                <input type="text" name="subdivision" value="<?php echo $residentaddresstbl['subdivision']; ?>" readonly>
                            </div>
                            <div class="col-12">
                                <label>Area Number <span>* </span></label>
                                <input type="text" name="area_number" value="<?php echo $residentaddresstbl['area_number']; ?>" readonly>
                            </div>
                            <div class="col-12">
                                <label>Unit Number</label>
                                <input type="text" name="unit_number" value="<?php echo $residentaddresstbl['unit_number']; ?>" readonly>
                            </div>
                            <div class="col-12">
                                <label>Barangay <span>* </span></label>
                                <input type="text" name="barangay" value="<?php echo $residentaddresstbl['barangay']; ?>" readonly>
                            </div>
                            <div class="col-12">
                                <label>City/Municipality <span>* </span></label>
                                <input type="text" name="city_municipality" value="San Jose City" readonly>
                            </div>
                            <div class="col-12">
                                <label>Province <span>* </span></label>
                                <input type="text" name="province" value="Montalban" readonly>
                            </div>
                        </div>
                        <hr style="color: #ff7a18 !important; width: 3px !important;" class="my-4">
                        <div>
                            <div class="col-12">
                                <label>Are you a beneficiary of JobStart Program under RA No. 10869 otherwise known as “An Act Institutionalizing the Nationwide Implementation of the JobStart Philippines Program and Providing for its Benefits and Program Components”? <span>* </span></label>
                                <div class="checkbox-group pt-2">
                                    <label><input type="checkbox" name="jobstart_beneficiary" value="Yes"> Yes</label>
                                    <label><input type="checkbox" name="jobstart_beneficiary" value="No"> No</label>
                                </div>
                            </div>
                        </div>
                        <div class="certification-row">

                            <label class="toggle-wrapper">
                                <input type="checkbox" class="toggle-input" required>
                                <span class="toggle-slider"></span>
                            </label>

                            <label class="certification-text">
                                I hereby certify that the above information is true and correct to the best of my knowledge and belief.
                            </label>

                            <button type="submit" class="submit-btn">SUBMIT</button>

                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>