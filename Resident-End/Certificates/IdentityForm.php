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

$userId = $_SESSION['user_id'] ?? '';
$data = getResidentProfileData($conn, $userId);
$residentinformationtbl = $data['residentinformationtbl'] ?? [];
$residentaddresstbl = $data['residentaddresstbl'] ?? [];
$useraccountstbl = $data['useraccountstbl'] ?? [];

$firstName = htmlspecialchars($residentinformationtbl['firstname'] ?? '', ENT_QUOTES, 'UTF-8');
$lastName = htmlspecialchars($residentinformationtbl['lastname'] ?? '', ENT_QUOTES, 'UTF-8');
$middleName = htmlspecialchars($residentinformationtbl['middlename'] ?? '', ENT_QUOTES, 'UTF-8');
$suffix = $residentinformationtbl['suffix'] ?? '';
$unitNumber = trim((string)($residentaddresstbl['unit_number'] ?? ''));
$streetNumber = trim((string)($residentaddresstbl['street_number'] ?? ''));
$streetName = trim((string)($residentaddresstbl['street_name'] ?? ''));
$phaseNumber = trim((string)($residentaddresstbl['phase_number'] ?? ''));
$subdivision = trim((string)($residentaddresstbl['subdivision'] ?? ''));
$areaNumber = trim((string)($residentaddresstbl['area_number'] ?? ''));
$phoneNumber = htmlspecialchars($useraccountstbl['phone_number'] ?? '', ENT_QUOTES, 'UTF-8');

$streetNameHasBlock = $streetName !== '' && stripos($streetName, 'block') !== false;
$streetNumberHasLot = $streetNumber !== '' && stripos($streetNumber, 'lot') !== false;
$isLotBlockSystem = $streetNameHasBlock || $streetNumberHasLot;

$streetLabel = $streetName;
if ($streetLabel !== '' && stripos($streetLabel, 'street') === false && !$streetNameHasBlock) {
    $streetLabel .= ' Street';
}

$subdivisionLabel = $subdivision !== '' ? $subdivision . ' Subdivision' : '';

$birthdateValue = '';
$birthdateDisplay = $residentinformationtbl['birthdate'] ?? '';
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
$sexValue = (string)($residentinformationtbl['sex'] ?? '');

$fullAddressParts = [];
if ($isLotBlockSystem) {
    $lotNumber = trim((string)preg_replace('/^lot\\s*/i', '', $streetNumber));
    $blockNumber = trim((string)preg_replace('/^(block|blk)\\s*/i', '', $streetName));
    $phaseValue = trim((string)preg_replace('/^phase\\s*/i', '', $phaseNumber));

    $lotLabel = $lotNumber !== '' ? 'Lot ' . $lotNumber : $streetNumber;
    $blockLabel = $blockNumber !== '' ? 'Blk ' . $blockNumber : $streetName;
    $phaseLabel = $phaseValue !== '' ? 'Phase ' . $phaseValue : ($phaseNumber !== '' ? $phaseNumber : '');

    $fullAddressParts = array_filter([
        $lotLabel,
        $blockLabel,
        $phaseLabel,
        $subdivisionLabel,
        'San Jose',
        'Rodriguez',
        'Rizal'
    ], fn($part) => $part !== '');
} else {
    if ($unitNumber !== '') {
        $fullAddressParts = array_filter([
            'Unit ' . $unitNumber,
            $streetLabel,
            $subdivisionLabel,
            'San Jose',
            'Rodriguez',
            'Rizal'
        ], fn($part) => $part !== '');
    } else {
        $streetLine = trim(implode(' ', array_filter([$streetNumber, $streetLabel], fn($part) => $part !== '')));
        $fullAddressParts = array_filter([
            $streetLine,
            $subdivisionLabel,
            'San Jose',
            'Rodriguez',
            'Rizal'
        ], fn($part) => $part !== '');
    }
}

$fullAddress = htmlspecialchars(implode(', ', $fullAddressParts), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Identity - Barangay San Jose</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/residentDashboard.css">
<link rel="stylesheet" href="../../CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/applicationForms.css">
    <style>
        body {
            background: #fffdfb;
        }
        #div-mainDisplay {
            background: #ffffff !important;
        }
        #div-mainDisplay .form-title,
        #div-mainDisplay .form-subtitle,
        #div-mainDisplay .back-link {
            max-width: 1300px;
            margin-left: auto;
            margin-right: auto;
        }
        #div-mainDisplay .page-form {
            max-width: 1300px;
            margin: 0 auto;
            padding-bottom: 48px;
        }
        h1 {
            font-size: 2.8rem !important;
            font-weight: 700;
        }
        h2.section-title,
        h3.section-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-top: 32px;
            margin-bottom: 24px;
        }
    </style></head>
<body>
<div class="d-flex min-vh-100">
    <?php include __DIR__ . '/../includes/resident_sidebar.php'; ?>

    <main id="div-mainDisplay" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 pb-md-5 pt-md-0">
        
            <div class="position-relative d-flex align-items-center justify-content-center mb-2 pt-4">
                <a href="<?= htmlspecialchars(appUrl('Resident-End/Certificates/CertificatesLandingPage.php')) ?>" class="back-link d-inline-flex align-items-center text-decoration-none text-dark m-0 position-absolute start-0">
                    <i class="bi bi-arrow-left-short fs-3"></i>
                </a>
                <h1 class="form-title m-0">Identity</h1>
            </div>
            <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

           <form class="page-form" action="<?= htmlspecialchars($baseUrl) ?>/PhpFiles/Resident-End/documentRequestWorkflow.php" method="POST">
                <input type="hidden" name="action" value="submit_request">
                <input type="hidden" name="document_type" value="identity">
                <input type="hidden" name="purpose" value="Certificate of Identity Application">
                <input type="hidden" name="redirect" value="1">

                <h2 class="section-title text-center text-dark">Applicant Information</h2>
                <div class="form-row">
                    <div class="input-stack">
                        <label class="top-label">Last Name<span class="required-asterisk">*</span></label>
                        <input type="text" name="last_name" readonly value="<?php echo $lastName; ?>">
                    </div>
                    <div class="input-stack">
                        <label class="top-label">First Name<span class="required-asterisk">*</span></label>
                        <input type="text" name="first_name" readonly value="<?php echo $firstName; ?>">
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Middle Name</label>
                        <input type="text" name="middle_name" readonly value="<?php echo $middleName; ?>">
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Suffix</label>
                        <input type="text" class="text-bg-light" readonly value="<?php echo htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <input type="hidden" name="suffix_name" value="<?php echo htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="form-row">
                    <div class="full-width">
                        <div class="input-stack">
                            <label class="top-label">Contact Number<span class="required-asterisk">*</span></label>
                            <input type="text" name="contact_number" readonly value="<?php echo $phoneNumber; ?>">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="full-width">
                        <div class="input-stack">
                            <label class="top-label">Full Address <span class="required-asterisk">*</span></label>
                            <input type="text" name="full_address" readonly value="<?php echo $fullAddress; ?>">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="phone">
                        <div class="input-stack">
                            <label class="top-label">Date of Birth<span class="required-asterisk">*</span></label>
                            <input type="date" name="child_dob" value="<?php echo htmlspecialchars($birthdateValue, ENT_QUOTES, 'UTF-8'); ?>" readonly required>
                        </div>
                    </div>
                    <div class="phone">
                        <div class="input-stack">
                            <label class="top-label">Kasarian / Sex<span class="required-asterisk">*</span></label>
                            <select name="child_sex_display" class="text-bg-light" disabled required>
                                <option value="" <?php echo ($sexValue === '') ? 'selected' : ''; ?> disabled>Select</option>
                                <option value="Male" <?php echo ($sexValue === 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($sexValue === 'Female') ? 'selected' : ''; ?>>Female</option>
                            </select>
                            <input type="hidden" name="child_sex" value="<?php echo htmlspecialchars($sexValue, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="phone">
                        <div class="input-stack">
                            <label class="top-label">Place of Birth<span class="required-asterisk">*</span></label>
                            <input type="text" name="child_birthplace" required>
                        </div>
                    </div>
                    <div class="phone">
                        <div class="input-stack">
                            <label class="top-label">Nationality<span class="required-asterisk">*</span></label>
                            <select id="child_nationality_choice" class="form-select" required>
                                <option value="Filipino" selected>Filipino</option>
                                <option value="Other">Other</option>
                            </select>
                            <input type="hidden" id="child_nationality" name="child_nationality" value="Filipino">
                            <input type="text" id="child_nationality_other" class="form-control mt-2" placeholder="Please specify" style="display: none;">
                        </div>
                    </div>
                </div>

                <h2 class="section-title text-center text-dark">Father’s Information</h2>
                <div class="form-row">
                    <div class="input-stack">
                        <label class="top-label">Last Name<span class="required-asterisk">*</span></label>
                        <input type="text" name="father_last_name" required>
                    </div>
                    <div class="input-stack">
                        <label class="top-label">First Name<span class="required-asterisk">*</span></label>
                        <input type="text" name="father_first_name" required>
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Middle Name</label>
                        <input type="text" name="father_middle_name">
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Suffix</label>
                        <input type="text" name="father_suffix">
                    </div>
                </div>

                <h2 class="section-title text-center text-dark">Mother’s Information</h2>
                <div class="form-row">
                    <div class="input-stack">
                        <label class="top-label">Last Name<span class="required-asterisk">*</span></label>
                        <input type="text" name="mother_last_name" required>
                    </div>
                    <div class="input-stack">
                        <label class="top-label">First Name<span class="required-asterisk">*</span></label>
                        <input type="text" name="mother_first_name" required>
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Middle Name</label>
                        <input type="text" name="mother_middle_name">
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Suffix</label>
                        <input type="text" name="mother_suffix">
                    </div>
                </div>

                <div class="agreement-row">
                    <div class="agreement-text check-item">
                        <input type="checkbox" id="agreement" required>
                        <label for="agreement">I hereby certify that the above information is true and correct to the best of my knowledge and belief.</label>
                    </div>
                    <button type="submit" class="submit-btn">SUBMIT</button>
                </div>

            </form>
    </main>
</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../JS-Script-Files/Resident-End/formValidationHighlight.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const form = document.querySelector("form");
            const submitBtn = form?.querySelector(".submit-btn");
            if (!form || !submitBtn) return;

            const nationalitySelect = form.querySelector("#child_nationality_choice");
            const nationalityHidden = form.querySelector("#child_nationality");
            const nationalityOther = form.querySelector("#child_nationality_other");

            const syncNationality = () => {
                if (!nationalitySelect || !nationalityHidden || !nationalityOther) return;
                if (nationalitySelect.value === "Other") {
                    nationalityOther.style.display = "";
                    nationalityOther.required = true;
                    nationalityHidden.value = nationalityOther.value.trim();
                } else {
                    nationalityOther.style.display = "none";
                    nationalityOther.required = false;
                    nationalityOther.value = "";
                    nationalityHidden.value = nationalitySelect.value;
                }
            };

            const updateState = () => {
                submitBtn.disabled = !form.checkValidity();
            };

            nationalitySelect?.addEventListener("change", () => {
                syncNationality();
                updateState();
            });
            nationalityOther?.addEventListener("input", () => {
                syncNationality();
                updateState();
            });

            form.addEventListener("input", updateState);
            form.addEventListener("change", updateState);
            syncNationality();
            updateState();
        });
    </script>
</body>
</html>


