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
    <link rel="icon" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Images/favicon_sanjose.png?v=20260211">
    <title>Good Moral Application</title>
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
                        <h1 class="form-title m-0">Good Moral</h1>
                    </div>
                    <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

                    <form class="page-form" method="POST" action="<?= htmlspecialchars($baseUrl) ?>/PhpFiles/Resident-End/documentRequestWorkflow.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="submit_request">
                        <input type="hidden" name="document_type" value="goodmoral">
                        <input type="hidden" name="redirect" value="1">
                        <h2 class="section-title text-center text-dark">Information</h2>

                        <div class="form-row pt-0">
                            <div>
                                <label class="top-label">Last Name <span class="required-asterisk">*</span></label>
                                <input type="text" name="last_name" readonly value="<?php echo $lastName; ?>">
                            </div>
                            <div>
                                <label class="top-label">First Name <span class="required-asterisk">*</span></label>
                                <input type="text" name="first_name" readonly value="<?php echo $firstName; ?>">
                            </div>
                            
                            <div>
                                <label class="top-label">Middle Name</label>
                                <input type="text" name="middle_name" readonly value="<?php echo $middleName; ?>">
                            </div>
                            <div>
                                <label class="top-label">Suffix</label>
                                <input type="text" class="text-bg-light" readonly value="<?php echo htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <input type="hidden" name="suffix_name" value="<?php echo htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="form-row">
                            <div class="tablet-full">
                                <label class="top-label">Contact Number <span class="required-asterisk">*</span></label>
                                <input type="text" name="contact_number" readonly value="<?php echo $phoneNumber; ?>">
                            </div>
                            <div class="span-3">
                                <label class="top-label">Address <span class="required-asterisk">*</span></label>
                                <input type="text" name="full_address" readonly value="<?php echo $fullAddress; ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Purpose <span class="required-asterisk">*</span></label>
                                <select id="purposeSelect" required>
                                    <option value="" selected disabled>Select purpose</option>
                                    <option value="Employment">Employment</option>
                                    <option value="Government Aid-Programs">Government Aid-Programs</option>
                                    <option value="Business Permit Application">Business Permit Application</option>
                                    <option value="School Requirement">School Requirement</option>
                                    <option value="Scholarship">Scholarship</option>
                                    <option value="Board Examination">Board Examination</option>
                                    <option value="Others">Others</option>
                                </select>
                                <div id="purposeOtherWrap" class="mt-2 d-none">
                                    <label class="top-label" for="purposeOther">Please specify <span class="required-asterisk">*</span></label>
                                    <input type="text" id="purposeOther" placeholder="Enter your purpose">
                                </div>
                                <input type="hidden" name="purpose" id="purposeFinal">
                            </div>
                        </div>

                        <div class="agreement-row">
                            <label class="agreement-text check-item" for="agreementGoodMoral">
                                <input type="checkbox" id="agreementGoodMoral" required>
                                I hereby certify that the above information is true and correct to the best of my knowledge and belief.
                            </label>
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
            const purposeSelect = document.getElementById("purposeSelect");
            const purposeOtherWrap = document.getElementById("purposeOtherWrap");
            const purposeOther = document.getElementById("purposeOther");
            const purposeFinal = document.getElementById("purposeFinal");
            if (!form || !submitBtn || !purposeSelect || !purposeOtherWrap || !purposeOther || !purposeFinal) return;

            const syncPurposeValue = () => {
                const choice = String(purposeSelect.value || "").trim();
                if (choice === "Others") {
                    purposeOtherWrap.classList.remove("d-none");
                    purposeOther.required = true;
                    purposeFinal.value = String(purposeOther.value || "").trim();
                } else {
                    purposeOtherWrap.classList.add("d-none");
                    purposeOther.required = false;
                    purposeOther.value = "";
                    purposeFinal.value = choice;
                }
            };

            const updateState = () => {
                syncPurposeValue();
                submitBtn.disabled = !form.checkValidity();
            };

            purposeSelect.addEventListener("change", updateState);
            purposeOther.addEventListener("input", updateState);
            form.addEventListener("input", updateState);
            form.addEventListener("change", updateState);
            updateState();
        });
    </script>
</body>
</html>
