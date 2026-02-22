<?php
$residentinformationtbl = [
    'firstname' => 'Juan',
    'middlename' => '',
    'lastname' => 'Dela Cruz',
    'suffix' => '',
    'sex' => 'Male',
    'birthdate' => 'January 1, 1999',
    'age' => 18
];

$residentaddresstbl = [
    'street_number' => '14A',
    'street_name' => 'Chico St',
    'subdivision' => '',
    'area_number' => 'Area 01',
    'unit_number' => 'Unit 5B',
    'barangay' => 'San Jose'
];

$useraccountstbl = [
    'phone_number' => '09123456789'
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Application for Barangay Certification</title>
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

        <?php include '../includes/resident_sidebar.php'; ?>

        <main id="div-mainDisplay" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 pb-md-5 pt-md-0 bg-light">
            <div class="main-head application-card orange-card py-3 rounded">
                <div class="main-head-content">

                    <a href="/BarangaySanJose/Resident-End/Clearances/ClearancesLandingPage.php" class="back-link">&lt; Go Back</a>

                    <h1 class="form-title">Application for Barangay Certification</h1>
                    <p class="form-subtitle">First Time Job Seeker</p>
                    <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

                    <form method="POST" action="">

                        <h2 class="section-title text-center text-dark">Personal Information</h2>

                        <div class="form-row">
                            <div>
                                <label class="top-label">Last Name <span class="required-asterisk">*</span></label>
                                <input type="text" name="last_name" value="<?php echo $residentinformationtbl['lastname']; ?>" readonly>
                            </div>
                            <div>
                                <label class="top-label">First Name <span class="required-asterisk">*</span></label>
                                <input type="text" name="first_name" value="<?php echo $residentinformationtbl['firstname']; ?>" readonly>
                            </div>
                            <div>
                                <label class="top-label">Middle Name</label>
                                <input type="text" name="middle_name" value="<?php echo $residentinformationtbl['middlename']; ?>" readonly>
                            </div>
                            <div>
                                <label class="top-label">Suffix</label>
                                <input type="text" name="suffix" value="<?php echo $residentinformationtbl['suffix']; ?>" readonly>
                            </div>
                        </div>

                        <div class="form-row">
                            <div>
                                <label class="top-label">Birthdate <span class="required-asterisk">*</span></label>
                                <input type="text" name="birthdate" value="<?php echo $residentinformationtbl['birthdate']; ?>" readonly>
                            </div>
                            <div>
                                <label class="top-label">Age <span class="required-asterisk">*</span></label>
                                <input type="text" name="age" value="<?php echo $residentinformationtbl['age']; ?>" readonly>
                            </div>
                            <div>
                                <label class="top-label">Sex/Gender <span class="required-asterisk">*</span></label>
                                <input type="text" name="sex" value="<?php echo $residentinformationtbl['sex']; ?>" readonly>
                            </div>
                            <div>
                                <label class="top-label">Years/Months Residency <span class="required-asterisk">*</span></label>
                                <input type="text" name="residency_length" required>
                            </div>
                        </div>

                        <div class="form-row two-col-row">
                            <div>
                                <label class="top-label">Contact Number</label>
                                <input type="text" name="phone_number" value="<?php echo $useraccountstbl['phone_number']; ?>" readonly>
                            </div>
                            <div>
                                <label class="top-label">Educational Attainment <span class="required-asterisk">*</span></label>
                                <input type="text" name="educational_attainment" required>
                            </div>
                        </div>

                        <div id="residentAddressWrapper" class="form-row">
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

                        <hr class="my-4">

                        <div>
                            <label>
                                Are you a beneficiary of JobStart Program under RA No. 10869?
                                <span class="required-asterisk">*</span>
                            </label>
                            <div class="checkbox-group pt-2">
                                <label><input type="radio" name="jobstart_beneficiary" value="Yes" required> Yes</label>
                                <label><input type="radio" name="jobstart_beneficiary" value="No" required> No</label>
                            </div>
                        </div>

                        <div class="agreement-row">
                            <label class="toggle-wrapper">
                                <input type="checkbox" class="toggle-input" required>
                                <span class="toggle-slider"></span>
                            </label>

                            <label class="agreement-text">
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




