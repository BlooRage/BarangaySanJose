<?php
$allowUnregistered = false;
require_once __DIR__ . "/../includes/resident_access_guard.php";

require_once __DIR__ . "/../../PhpFiles/GET/getResidentProfile.php";

$userId = $_SESSION['user_id'] ?? '';
$data = getResidentProfileData($conn, $userId);
$residentinformationtbl = $data['residentinformationtbl'] ?? [];
$residentaddresstbl = $data['residentaddresstbl'] ?? [];
$useraccountstbl = $data['useraccountstbl'] ?? [];

$firstName = htmlspecialchars($residentinformationtbl['firstname'] ?? '', ENT_QUOTES, 'UTF-8');
$lastName = htmlspecialchars($residentinformationtbl['lastname'] ?? '', ENT_QUOTES, 'UTF-8');
$middleName = htmlspecialchars($residentinformationtbl['middlename'] ?? '', ENT_QUOTES, 'UTF-8');
$suffix = $residentinformationtbl['suffix'] ?? '';
$birthdate = htmlspecialchars($residentinformationtbl['birthdate'] ?? '', ENT_QUOTES, 'UTF-8');
$age = htmlspecialchars((string)($residentinformationtbl['age'] ?? ''), ENT_QUOTES, 'UTF-8');
$sex = htmlspecialchars($residentinformationtbl['sex'] ?? '', ENT_QUOTES, 'UTF-8');
$unitNumber = htmlspecialchars($residentaddresstbl['unit_number'] ?? '', ENT_QUOTES, 'UTF-8');
$streetNumber = htmlspecialchars($residentaddresstbl['street_number'] ?? '', ENT_QUOTES, 'UTF-8');
$streetName = htmlspecialchars($residentaddresstbl['street_name'] ?? '', ENT_QUOTES, 'UTF-8');
$subdivision = htmlspecialchars($residentaddresstbl['subdivision'] ?? '', ENT_QUOTES, 'UTF-8');
$areaNumber = htmlspecialchars($residentaddresstbl['area_number'] ?? '', ENT_QUOTES, 'UTF-8');
$phoneNumber = htmlspecialchars($useraccountstbl['phone_number'] ?? '', ENT_QUOTES, 'UTF-8');
$yearsOfResidency = htmlspecialchars((string)($residentaddresstbl['address_id'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>First Time Job Seeker</title>
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

            <div class="main-head application-card orange-card py-3 rounded application-card--muted">
                <div class="main-head-content">

                    <a href="/BarangaySanJose/Resident-End/ApplicationsLandingPage.php" class="back-link">&lt; Go Back</a>

                    <h1 class="form-title">First Time Job Seeker</h1>
                    <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

                    <form method="POST" action="">

                        <h2 class="section-title text-center text-dark">Personal Information</h2>

                        <div class="status-row">
                            <label for="application_date">Application Date:</label>
                            <input type="text" id="application_date" name="application_date" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly>
                        </div>

                        <div class="form-row pt-0">
                            <div>
                                <label class="top-label">Last Name <span class="required-asterisk">*</span></label>
                                <input type="text" name="last_name" value="<?php echo $lastName; ?>" readonly>
                            </div>

                            <div>
                                <label class="top-label">First Name <span class="required-asterisk">*</span></label>
                                <input type="text" name="first_name" value="<?php echo $firstName; ?>" readonly>
                            </div>

                            <div>
                                <label class="top-label">Middle Name</label>
                                <input type="text" name="middle_name" value="<?php echo $middleName; ?>" readonly>
                            </div>

                            <div>
                                <label class="top-label">Suffix</label>
                                <select name="suffix_display" class="text-bg-light" disabled>
                                    <option value="" <?php echo ($suffix === '') ? 'selected' : ''; ?>>None</option>
                                    <option value="Jr." <?php echo ($suffix === 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                                    <option value="Sr." <?php echo ($suffix === 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                                    <option value="III" <?php echo ($suffix === 'III') ? 'selected' : ''; ?>>III</option>
                                    <option value="IV" <?php echo ($suffix === 'IV') ? 'selected' : ''; ?>>IV</option>
                                </select>
                                <input type="hidden" name="suffix" value="<?php echo htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div>
                                <label class="top-label">Birthdate <span class="required-asterisk">*</span></label>
                                <input type="text" name="birthdate" value="<?php echo $birthdate; ?>" readonly>
                            </div>
                            <div>
                                <label class="top-label">Age <span class="required-asterisk">*</span></label>
                                <input type="text" name="age" value="<?php echo $age; ?>" readonly>
                            </div>
                            <div>
                                <label class="top-label">Sex/Gender <span class="required-asterisk">*</span></label>
                                <input type="text" name="sex" value="<?php echo $sex; ?>" readonly>
                            </div>
                            <div>
                                <label class="top-label">Years of Residency <span class="required-asterisk">*</span></label>
                                <input type="text" name="years_of_residency" value="<?php echo $yearsOfResidency; ?> years" readonly>
                            </div>
                        </div>

                        <div class="form-row two-col-row">
                            <div>
                                <label class="top-label">Contact Number <span class="required-asterisk">*</span></label>
                                <input type="text" name="phone_number" value="<?php echo $phoneNumber; ?>" readonly>
                            </div>
                            <div>
                                <label class="top-label">Educational Attainment <span class="required-asterisk">*</span></label>
                                <select name="educational_attainment" required>
                                    <option value="">Select Educational Attainment</option>
                                    <option value="Elementary Graduate" >Elementary Graduate</option>
                                    <option value="High School Graduate" >High School Graduate</option>
                                    <option value="College Undergraduate">College Undergraduate</option>
                                    <option value="College Graduate">College Graduate</option>
                                </select>
                            </div>
                        </div>

                        <div id="residentAddressWrapper" class="form-row">
                            <div class="full-width">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="top-label" for="unitNumber">Unit / Apartment Number</label>
                                        <input type="text" class="form-control" id="unitNumber" name="unitNumber" readonly value="<?php echo $unitNumber; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="top-label" for="houseNumber">House Number <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="houseNumber" name="houseNumber" readonly value="<?php echo $streetNumber; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="top-label" for="streetName">Street Name <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="streetName" name="streetName" readonly value="<?php echo $streetName; ?>">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="top-label" for="applicantSubdivision">Subdivision</label>
                                        <input type="text" class="form-control" id="applicantSubdivision" name="applicantSubdivision" readonly value="<?php echo $subdivision; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="top-label" for="applicantAreaNumber">Area <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="applicantAreaNumber" name="applicantAreaNumber" readonly value="<?php echo $areaNumber; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width beneficiary-block">
                                <label class="top-label beneficiary-question">
                                    Are you a beneficiary of the JobStart Program under RA No. 10869?
                                    <span class="required-asterisk">*</span>
                                </label>
                                <div class="beneficiary-options" role="radiogroup" aria-label="JobStart beneficiary">
                                    <label class="beneficiary-option">
                                        <input type="radio" name="jobstart_beneficiary" value="Yes" required>
                                        <span>Yes</span>
                                    </label>
                                    <label class="beneficiary-option">
                                        <input type="radio" name="jobstart_beneficiary" value="No" required>
                                        <span>No</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="agreement-row">
                            <label class="agreement-text check-item" for="agreementJobSeeker">
                                <input type="checkbox" id="agreementJobSeeker" required>
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

