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
<?php
$allowUnregistered = false;
require_once __DIR__ . "/../includes/resident_access_guard.php";
require_once __DIR__ . "/../../PhpFiles/GET/getResidentProfile.php";

$userId = (string)($_SESSION['user_id'] ?? '');
$data = getResidentProfileData($conn, $userId);
$residentinformationtbl = $data['residentinformationtbl'] ?? [];
$residentaddresstbl = $data['residentaddresstbl'] ?? [];
$useraccountstbl = $data['useraccountstbl'] ?? [];

$firstName = htmlspecialchars((string)($residentinformationtbl['firstname'] ?? ''), ENT_QUOTES, 'UTF-8');
$lastName = htmlspecialchars((string)($residentinformationtbl['lastname'] ?? ''), ENT_QUOTES, 'UTF-8');
$middleName = htmlspecialchars((string)($residentinformationtbl['middlename'] ?? ''), ENT_QUOTES, 'UTF-8');
$suffix = (string)($residentinformationtbl['suffix'] ?? '');

$birthdateValue = '';
$birthdateDisplay = (string)($residentinformationtbl['birthdate'] ?? '');
if ($birthdateDisplay !== '') {
    $dt = DateTime::createFromFormat('F j, Y', $birthdateDisplay);
    if (!$dt) {
        try {
            $dt = new DateTime($birthdateDisplay);
        } catch (Exception $e) {
            $dt = null;
        }
    }
    if ($dt instanceof DateTime) {
        $birthdateValue = $dt->format('Y-m-d');
    }
}
$birthplace = htmlspecialchars((string)($residentinformationtbl['birthplace'] ?? ''), ENT_QUOTES, 'UTF-8');

$phoneNumber = htmlspecialchars((string)($useraccountstbl['phone_number'] ?? ''), ENT_QUOTES, 'UTF-8');
$emergencyLast = htmlspecialchars((string)($residentinformationtbl['emergency_last_name'] ?? ''), ENT_QUOTES, 'UTF-8');
$emergencyFirst = htmlspecialchars((string)($residentinformationtbl['emergency_first_name'] ?? ''), ENT_QUOTES, 'UTF-8');
$emergencyMiddle = htmlspecialchars((string)($residentinformationtbl['emergency_middle_name'] ?? ''), ENT_QUOTES, 'UTF-8');
$emergencySuffix = (string)($residentinformationtbl['emergency_suffix'] ?? '');
$emergencyContact = htmlspecialchars((string)($residentinformationtbl['emergency_contact'] ?? ''), ENT_QUOTES, 'UTF-8');

$unitNumber = trim((string)($residentaddresstbl['unit_number'] ?? ''));
$houseNumber = trim((string)($residentaddresstbl['street_number'] ?? ''));
$streetName = trim((string)($residentaddresstbl['street_name'] ?? ''));
$subdivision = trim((string)($residentaddresstbl['subdivision'] ?? ''));
$areaNumber = trim((string)($residentaddresstbl['area_number'] ?? ''));

$fullAddress = implode(', ', array_filter([
    $unitNumber !== '' ? 'Unit ' . $unitNumber : '',
    trim($houseNumber . ' ' . $streetName),
    $subdivision !== '' ? $subdivision . ' Subdivision' : '',
    $areaNumber !== '' ? 'Area ' . $areaNumber : '',
    'San Jose',
    'Rodriguez',
    'Rizal'
], fn($part) => trim((string)$part) !== ''));
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
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/barangayIdNav.css">
</head>

<body>

    <div class="d-flex min-vh-100">

        <?php include __DIR__ . '/../includes/resident_sidebar.php'; ?>

        <main id="div-mainDisplay" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 pb-md-5 pt-md-0 bg-light">

            <div class="main-head application-card orange-card application-card--muted py-3 my-5 rounded">
                <div class="main-head-content">

                    <a href="<?= htmlspecialchars(appUrl('Resident-End/BarangayId/BarangayIdLandingPage.php')) ?>" class="back-link">&lt; Go Back</a>

                    <h1 class="form-title">Application for Barangay ID</h1>
                    <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>
                    <?php
                    $barangayIdNavActive = 'apply';
                    include __DIR__ . '/includes/barangay_id_nav.php';
                    ?>

                    <form method="POST" action="<?= htmlspecialchars($baseUrl) ?>/PhpFiles/Resident-End/documentRequestWorkflow.php">
                        <input type="hidden" name="action" value="submit_request">
                        <input type="hidden" name="document_type" value="Barangay ID">
                        <input type="hidden" name="purpose" value="Barangay ID Application">
                        <input type="hidden" name="request_purpose" value="Barangay ID Application">
                        <input type="hidden" name="redirect" value="1">

                        <!-- PERSONAL INFORMATION -->
                        <h2 class="section-title text-center text-dark">Personal Information</h2>


                        <div class="form-row">
                            <div>
                                <label class="top-label">Last Name <span class="required-asterisk">*</span></label>
                                <input type="text" name="last_name" required readonly value="<?php echo $lastName; ?>">
                            </div>

                            <div>
                                <label class="top-label">First Name <span class="required-asterisk">*</span></label>
                                <input type="text" name="first_name" required readonly value="<?php echo $firstName; ?>">
                            </div>

                            <div>
                                <label class="top-label">Middle Name</label>
                                <input type="text" name="middle_name" readonly value="<?php echo $middleName; ?>">
                            </div>

                            <div>
                                <label class="top-label">Suffix</label>
                                <select name="suffix" disabled>
                                    <option value="" <?php echo ($suffix === '') ? 'selected' : ''; ?>>None</option>
                                    <option value="Jr." <?php echo ($suffix === 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                                    <option value="Sr." <?php echo ($suffix === 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                                    <option value="III" <?php echo ($suffix === 'III') ? 'selected' : ''; ?>>III</option>
                                    <option value="IV" <?php echo ($suffix === 'IV') ? 'selected' : ''; ?>>IV</option>
                                    <option value="V">Others</option>
                                </select>
                                <input type="hidden" name="suffix_name" value="<?php echo htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div>
                                <label class="top-label">Date of Birth <span class="required-asterisk">*</span></label>
                                <input type="date" name="birthdate" required <?php echo $birthdateValue !== '' ? 'readonly' : ''; ?> value="<?php echo htmlspecialchars($birthdateValue, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>

                            <div>
                                <label class="top-label">Birthplace <span class="required-asterisk">*</span></label>
                                <input type="text" name="birthplace" required value="<?php echo $birthplace; ?>" <?php echo $birthplace !== '' ? 'readonly' : ''; ?>>
                            </div>
                            <div class="phone">
                                <label class="top-label">Contact Number <span class="required-asterisk">*</span></label>
                                <input type="tel" name="contact_number" required <?php echo $phoneNumber !== '' ? 'readonly' : ''; ?> value="<?php echo $phoneNumber; ?>">
                            </div>



                        </div>

                        <div class="form-row">
                            <div class="full-width">
                            <label class="top-label">Address <span class="required-asterisk">*</span></label>
                                <input type="text" name="full_address_display" <?php echo $fullAddress !== '' ? 'readonly' : ''; ?> value="<?php echo htmlspecialchars($fullAddress, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="full_address" value="<?php echo htmlspecialchars($fullAddress, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="unitNumber" value="<?php echo htmlspecialchars($unitNumber, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="houseNumber" value="<?php echo htmlspecialchars($houseNumber, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="streetName" value="<?php echo htmlspecialchars($streetName, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                        <br>
                        <!-- EMERGENCY CONTACT -->
                        <h2 class="section-title text-center pb-2 text-dark">
                            Contact Person in case of Emergency (Family Member)
                        </h2>


                        <div class="form-row">
                            <div>
                                <label class="top-label">Last Name <span class="required-asterisk">*</span> </label>
                                <input type="text" name="emergency_last" required value="<?php echo $emergencyLast; ?>">
                            </div>

                            <div>
                                <label class="top-label">First Name <span class="required-asterisk">*</span> </label>
                                <input type="text" name="emergency_first" required value="<?php echo $emergencyFirst; ?>">
                            </div>

                            <div>
                                <label class="top-label">Middle Name</label>
                                <input type="text" name="emergency_middle" value="<?php echo $emergencyMiddle; ?>">
                            </div>

                            <div>
                                <!-- suffix is select drop down-->
                                <label class="top-label">Suffix</label>
                                <select name="emergency_suffix">
                                    <option value="" <?php echo ($emergencySuffix === '') ? 'selected' : ''; ?>>None</option>
                                    <option value="Jr." <?php echo ($emergencySuffix === 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                                    <option value="Sr." <?php echo ($emergencySuffix === 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                                    <option value="III" <?php echo ($emergencySuffix === 'III') ? 'selected' : ''; ?>>III</option>
                                    <option value="IV" <?php echo ($emergencySuffix === 'IV') ? 'selected' : ''; ?>>IV</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Contact Number <span class="required-asterisk">*</span> </label>
                                <input type="tel" name="emergency_contact" required value="<?php echo $emergencyContact; ?>">
                            </div>
                        </div>

                        <!-- CERTIFICATION -->
                        <div class="agreement-row">
                            <label class="agreement-text" for="barangayIdAgreement">
                                <input type="checkbox" id="barangayIdAgreement" required>
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
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const form = document.querySelector("form");
            const submitBtn = form?.querySelector(".submit-btn");
            const fullAddressDisplay = form?.querySelector('input[name="full_address_display"]');
            const fullAddressHidden = form?.querySelector('input[name="full_address"]');
            if (!form || !submitBtn) return;

            const updateState = () => {
                if (fullAddressDisplay && fullAddressHidden) {
                    fullAddressHidden.value = fullAddressDisplay.value.trim();
                }
                submitBtn.disabled = !form.checkValidity();
            };

            form.addEventListener("input", updateState);
            form.addEventListener("change", updateState);
            updateState();
        });
    </script>
</body>

</html>






