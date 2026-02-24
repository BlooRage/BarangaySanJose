<?php
if (!isset($baseUrl)) {
    $scriptName = str_replace("\\", "/", (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $residentSegmentPos = strpos($scriptName, '/Resident-End/');
    $baseUrl = '';
    if ($residentSegmentPos !== false) {
        $baseUrl = substr($scriptName, 0, $residentSegmentPos);
    } else {
        $baseUrl = dirname($scriptName);
    }
    $baseUrl = rtrim((string)$baseUrl, '/');
    if ($baseUrl === '.' || $baseUrl === '/') {
        $baseUrl = '';
    }
}
?>
﻿<?php
$allowUnregistered = false;
require_once __DIR__ . "/../includes/resident_access_guard.php";

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
    <title>Barangay ID Application</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/residentDashboard.css">
<link rel="stylesheet" href="../../CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/applicationForms.css">
</head>

<body>

    <div class="d-flex min-vh-100">

        <?php include __DIR__ . '/../includes/resident_sidebar.php'; ?>

        <main id="div-mainDisplay" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 pb-md-5 pt-md-0 bg-light">

            <div class="main-head application-card orange-card py-3 my-5 rounded">
                <div class="main-head-content">

                    <a href="<?= htmlspecialchars($baseUrl) ?>/Resident-End/resident_dashboard.php" class="back-link">&lt; Go Back</a>

                    <h1 class="form-title">Application for Barangay ID</h1>
                    <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

                    <form method="POST" action="">

                        <!-- PERSONAL INFORMATION -->
                        <h2 class="section-title text-center text-dark">Personal Information</h2>

                        <div class="status-row">
                            <label><input type="checkbox" name="pwd" class="text-center"> PWD</label>
                            <label><input type="checkbox" name="senior" class="text-center"> Senior Citizen</label>
                        </div>

                        <div class="form-row">
                            <div>
                                <label class="top-label">Last Name <span class="required-asterisk">*</span></label>
                                <input type="text" name="last_name" required>
                            </div>

                            <div>
                                <label class="top-label">First Name <span class="required-asterisk">*</span></label>
                                <input type="text" name="first_name" required>
                            </div>

                            <div>
                                <label class="top-label">Middle Name</label>
                                <input type="text" name="middle_name">
                            </div>

                            <div>
                                <label class="top-label">Suffix</label>
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
                            <div>
                                <label class="top-label">Date of Birth <span class="required-asterisk">*</span></label>
                                <input type="date">
                            </div>

                            <div>
                                <label class="top-label">Birthplace <span class="required-asterisk">*</span></label>
                                <input type="text">
                            </div>
                            <div class="phonenum">
                                <label class="top-label">Contact Number <span class="required-asterisk">*</span></label>
                                <input type="tel" name="phone_number" required>
                            </div>



                        </div>

                        <div id="houseSystemWrapper" class="form-row">
                            <div class="full-width">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="top-label" for="unitNumber">Unit / Apartment Number</label>
                                        <input type="text" class="form-control" id="unitNumber" name="unitNumber" readonly value="<?php echo $residentaddresstbl['unit_number']; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="top-label" for="houseNumber">House Number <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="houseNumber" name="houseNumber" readonly value="<?php echo $residentaddresstbl['street_number']; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="top-label" for="streetName">Street Name <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="streetName" name="streetName" readonly value="<?php echo $residentaddresstbl['street_name']; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <br>
                        <!-- EMERGENCY CONTACT -->
                        <h2 class="section-title text-center pb-2 text-dark">
                            Contact Person in case of Emergency (Family Member)
                        </h2>


                        <div class="form-row">
                            <div>
                                <label class="top-label">First Name <span class="required-asterisk">*</span> </label>
                                <input type="text" name="emergency_first" required>
                            </div>

                            <div>
                                <label class="top-label">Last Name <span class="required-asterisk">*</span> </label>
                                <input type="text" name="emergency_last" required>
                            </div>

                            <div>
                                <label class="top-label">Middle Name</label>
                                <input type="text" name="emergency_middle">
                            </div>

                            <div>
                                <!-- suffix is select drop down-->
                                <label class="top-label">Suffix</label>
                                <select name="emergency_suffix">
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
                                <label class="top-label">Contact Number <span class="required-asterisk">*</span> </label>
                                <input type="tel" name="emergency_contact" required>
                            </div>
                        </div>

                        <!-- CERTIFICATION -->
                        <div class="agreement-row">
                            <label class="agreement-text">
                                <input type="checkbox" required>
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










