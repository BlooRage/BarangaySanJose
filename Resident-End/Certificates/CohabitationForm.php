<?php
$allowUnregistered = false;
require_once __DIR__ . "/../includes/resident_access_guard.php";

require_once __DIR__ . "/../../PhpFiles/GET/getResidentProfile.php";

$userId = $_SESSION['user_id'] ?? '';
$data = getResidentProfileData($conn, $userId);
$residentinformationtbl = $data['residentinformationtbl'] ?? [];
$residentaddresstbl = $data['residentaddresstbl'] ?? [];
$useraccountstbl = $data['useraccountstbl'] ?? [];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Cohabitation Application</title>
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

        <main id="div-mainDisplay" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 bg-light">

            <div class="main-head application-card orange-card py-3 my-md-5 rounded">
                <div class="main-head-content">

                    <a href="/BarangaySanJose/Resident-End/ApplicationsLandingPage.php" class="back-link">&lt; Go Back</a>
                    <h1 class="form-title">Cohabitation</h1>
                    <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

                    <form method="POST" action="" id="cohabitationForm">

                        <!-- PERSONAL INFORMATION -->
                        <h2 class="section-title text-center text-dark">Personal Information</h2>
                        <div class="status-row">
                            <label for="application_date">Application Date:</label>
                            <input type="text" id="application_date" name="application_date" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly>
                        </div>
                        <br>
                        <div class="status-row">
                            <label><input type="checkbox" name="pwd" class="text-center"> PWD</label>
                            <label><input type="checkbox" name="senior" class="text-center"> Senior Citizen</label>
                        </div>
                        <div class="form-row pt-0">
                            <div>
                                <label class="top-label">First Name <span class="required-asterisk">*</span> </label>
                                <input type="text" name="first_name" readonly value="<?php echo htmlspecialchars($residentinformationtbl['firstname'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="top-label">Last Name <span class="required-asterisk">*</span> </label>
                                <input type="text" name="last_name" readonly value="<?php echo htmlspecialchars($residentinformationtbl['lastname'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="top-label">Middle Name</label>
                                <input type="text" name="middle_name" readonly value="<?php echo htmlspecialchars($residentinformationtbl['middlename'] ?? ''); ?>">
                            </div>

                            <div>
                                <label class="top-label">Suffix</label>
                                <select name="suffix_name" class="text-bg-light" readonly>
                                    <option value="" <?php echo (($residentinformationtbl['suffix'] ?? '') === '') ? 'selected' : ''; ?>>None</option>
                                    <option value="Jr." <?php echo (($residentinformationtbl['suffix'] ?? '') === 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                                    <option value="Sr." <?php echo (($residentinformationtbl['suffix'] ?? '') === 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                                    <option value="III" <?php echo (($residentinformationtbl['suffix'] ?? '') === 'III') ? 'selected' : ''; ?>>III</option>
                                    <option value="IV" <?php echo (($residentinformationtbl['suffix'] ?? '') === 'IV') ? 'selected' : ''; ?>>IV</option>
                                </select>
                            </div>
                        </div>

                        <div id="houseSystemWrapper" class="form-row">
                            <div class="full-width">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="top-label" for="unitNumber">Unit / Apartment Number</label>
                                        <input type="text" class="form-control" id="unitNumber" name="unitNumber" readonly value="<?php echo htmlspecialchars($residentaddresstbl['unit_number'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="top-label" for="houseNumber">House Number <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="houseNumber" name="houseNumber" readonly value="<?php echo htmlspecialchars($residentaddresstbl['street_number'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="top-label" for="streetName">Street Name <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="streetName" name="streetName" readonly value="<?php echo htmlspecialchars($residentaddresstbl['street_name'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                                <h2 class="section-title text-center text-dark">Detainee's Information</h2>
                                <div class="form-row pt-0">
                                    <div>
                                        <label class="top-label">First Name <span class="required-asterisk">*</span> </label>
                                        <input type="text" name="detainee_first" required>
                                    </div>
                                    <div>
                                        <label class="top-label">Last Name <span class="required-asterisk">*</span> </label>
                                        <input type="text" name="detainee_last" required>
                                    </div>
                                    <div>
                                        <label class="top-label">Middle Name</label>
                                        <input type="text" name="detainee_middle">
                                    </div>

                                    <div>
                                        <label class="top-label">Suffix</label>
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
                                        <label class="top-label">Kaugnayan (Ex. Anak) <span class="required-asterisk">*</span> </label>
                                        <input type="text" name="detainee_relationship" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="full-width">
                                        <label class="top-label">Place of Detention <span class="required-asterisk">*</span> </label>
                                        <input type="text" name="detainee_place_of_detention" required>
                                    </div>
                                </div>




                                <div class="agreement-row">
                                    <label class="agreement-text check-item">
                                        <input type="checkbox" id="cohabitationAgree" name="cohabitationAgree" required>
                                        I hereby certify that the above information is true and correct to the best of my knowledge and belief.
                                    </label>

                                    <button type="submit" class="submit-btn" id="cohabitationSubmit" disabled>SUBMIT</button>
                                </div>

                    </form>
                </div>
            </div>
        </main>

    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/BarangaySanJose/JS-Script-Files/Resident-End/Certificates/cohabitationFormScript.js"></script>
</body>

</html>










