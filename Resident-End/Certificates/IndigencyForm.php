<?php
$allowUnregistered = false;
require_once __DIR__ . "/../includes/resident_access_guard.php";

require_once __DIR__ . "/../../PhpFiles/GET/getResidentProfile.php";

$data = getResidentProfileData($conn, $_SESSION['user_id']);
$residentinformationtbl = $data['residentinformationtbl'] ?? [];
$residentaddresstbl = $data['residentaddresstbl'] ?? [];
$useraccountstbl = $data['useraccountstbl'] ?? [];

$firstName = htmlspecialchars($residentinformationtbl['firstname'] ?? '', ENT_QUOTES, 'UTF-8');
$lastName = htmlspecialchars($residentinformationtbl['lastname'] ?? '', ENT_QUOTES, 'UTF-8');
$middleName = htmlspecialchars($residentinformationtbl['middlename'] ?? '', ENT_QUOTES, 'UTF-8');
$suffix = $residentinformationtbl['suffix'] ?? '';
$unitNumber = htmlspecialchars($residentaddresstbl['unit_number'] ?? '', ENT_QUOTES, 'UTF-8');
$streetNumber = htmlspecialchars($residentaddresstbl['street_number'] ?? '', ENT_QUOTES, 'UTF-8');
$streetName = htmlspecialchars($residentaddresstbl['street_name'] ?? '', ENT_QUOTES, 'UTF-8');
$subdivision = htmlspecialchars($residentaddresstbl['subdivision'] ?? '', ENT_QUOTES, 'UTF-8');
$areaNumber = htmlspecialchars($residentaddresstbl['area_number'] ?? '', ENT_QUOTES, 'UTF-8');
$phoneNumber = htmlspecialchars($useraccountstbl['phone_number'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Indigency Application</title>
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

                    <a href="/BarangaySanJose/Resident-End/Certificates/CertificatesLandingPage.php" class="back-link">&lt; Go Back</a>
                    <h1 class="form-title">Indigency</h1>
                    <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

                    <form method="POST" action="">

                        <h2 class="section-title text-center text-dark">Personal Information</h2>
                        <div class="status-row">
                            <label for="application_date">Application Date:</label>
                            <input type="text" id="application_date" name="application_date" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly>
                        </div>

                        <div class="form-row pt-0">
                            <div>
                                <label class="top-label">First Name <span class="required-asterisk">*</span> </label>
                                <input type="text" name="first_name" readonly value="<?php echo $firstName; ?>">
                            </div>
                            <div>
                                <label class="top-label">Last Name <span class="required-asterisk">*</span> </label>
                                <input type="text" name="last_name" readonly value="<?php echo $lastName; ?>">
                            </div>
                            <div>
                                <label class="top-label">Middle Name</label>
                                <input type="text" name="middle_name" readonly value="<?php echo $middleName; ?>">
                            </div>
                            <div>
                                <label class="top-label">Suffix</label>
                                <select name="suffix_name_display" class="text-bg-light" disabled>
                                    <option value="" <?php echo ($suffix === '') ? 'selected' : ''; ?>>None</option>
                                    <option value="Jr." <?php echo ($suffix === 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                                    <option value="Sr." <?php echo ($suffix === 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                                    <option value="III" <?php echo ($suffix === 'III') ? 'selected' : ''; ?>>III</option>
                                    <option value="IV" <?php echo ($suffix === 'IV') ? 'selected' : ''; ?>>IV</option>
                                </select>
                                <input type="hidden" name="suffix_name" value="<?php echo htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Contact Number <span class="required-asterisk">*</span></label>
                                <input type="text" name="contact_number" value="<?php echo $phoneNumber; ?>" readonly>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Request for <span class="required-asterisk">*</span></label>
                                <select name="request_purpose" required>
                                    <option value="">Select Purpose</option>
                                    <option value="Scholarship">Scholarship</option>
                                    <option value="Employment">Employment</option>
                                    <option value="Financial Assistance">Financial Assistance</option>
                                    <option value="Medical Assistance">Medical Assistance</option>
                                    <option value="Educational Assistance">Educational Assistance</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">To be submitted to <span class="required-asterisk">*</span></label>
                                <input type="text" name="request_officer" required>
                            </div>
                        </div>

                        <div id="houseSystemWrapper" class="form-row">
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

                        <div class="agreement-row">
                            <label class="agreement-text check-item" for="agreementIndigency">
                                <input type="checkbox" id="agreementIndigency" required>
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

