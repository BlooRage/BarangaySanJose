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

$ownerLastName = htmlspecialchars((string)($residentinformationtbl['lastname'] ?? ''), ENT_QUOTES, 'UTF-8');
$ownerFirstName = htmlspecialchars((string)($residentinformationtbl['firstname'] ?? ''), ENT_QUOTES, 'UTF-8');
$ownerMiddleName = htmlspecialchars((string)($residentinformationtbl['middlename'] ?? ''), ENT_QUOTES, 'UTF-8');
$ownerSuffix = htmlspecialchars((string)($residentinformationtbl['suffix'] ?? ''), ENT_QUOTES, 'UTF-8');
$ownerPhone = htmlspecialchars((string)($useraccountstbl['phone_number'] ?? ''), ENT_QUOTES, 'UTF-8');
$ownerUnitNumberRaw = trim((string)($residentaddresstbl['unit_number'] ?? ''));
$ownerHouseNumberRaw = trim((string)($residentaddresstbl['street_number'] ?? ''));
$ownerStreetNameRaw = trim((string)($residentaddresstbl['street_name'] ?? ''));
$ownerPhaseNumberRaw = trim((string)($residentaddresstbl['phase_number'] ?? ''));
$ownerSubdivisionRaw = trim((string)($residentaddresstbl['subdivision'] ?? ''));
$ownerAreaNumberRaw = trim((string)($residentaddresstbl['area_number'] ?? ''));

$ownerStreetNameHasBlock = $ownerStreetNameRaw !== '' && stripos($ownerStreetNameRaw, 'block') !== false;
$ownerStreetNumberHasLot = $ownerHouseNumberRaw !== '' && stripos($ownerHouseNumberRaw, 'lot') !== false;
$ownerIsLotBlockSystem = $ownerStreetNameHasBlock || $ownerStreetNumberHasLot;

$ownerStreetLabel = $ownerStreetNameRaw;
if ($ownerStreetLabel !== '' && stripos($ownerStreetLabel, 'street') === false && !$ownerStreetNameHasBlock) {
    $ownerStreetLabel .= ' Street';
}

$ownerSubdivisionLabel = $ownerSubdivisionRaw !== '' ? $ownerSubdivisionRaw . ' Subdivision' : '';

$ownerFullAddressParts = [];
if ($ownerIsLotBlockSystem) {
    $ownerLotNumber = trim((string)preg_replace('/^lot\s*/i', '', $ownerHouseNumberRaw));
    $ownerBlockNumber = trim((string)preg_replace('/^(block|blk)\s*/i', '', $ownerStreetNameRaw));
    $ownerPhaseValue = trim((string)preg_replace('/^phase\s*/i', '', $ownerPhaseNumberRaw));

    $ownerLotLabel = $ownerLotNumber !== '' ? 'Lot ' . $ownerLotNumber : $ownerHouseNumberRaw;
    $ownerBlockLabel = $ownerBlockNumber !== '' ? 'Blk ' . $ownerBlockNumber : $ownerStreetNameRaw;
    $ownerPhaseLabel = $ownerPhaseValue !== '' ? 'Phase ' . $ownerPhaseValue : ($ownerPhaseNumberRaw !== '' ? $ownerPhaseNumberRaw : '');

    $ownerFullAddressParts = array_filter([
        $ownerLotLabel,
        $ownerBlockLabel,
        $ownerPhaseLabel,
        $ownerSubdivisionLabel,
        'San Jose',
        'Rodriguez',
        'Rizal'
    ], fn($part) => $part !== '');
} else {
    if ($ownerUnitNumberRaw !== '') {
        $ownerFullAddressParts = array_filter([
            'Unit ' . $ownerUnitNumberRaw,
            $ownerStreetLabel,
            $ownerSubdivisionLabel,
            'San Jose',
            'Rodriguez',
            'Rizal'
        ], fn($part) => $part !== '');
    } else {
        $ownerStreetLine = trim(implode(' ', array_filter([$ownerHouseNumberRaw, $ownerStreetLabel], fn($part) => $part !== '')));
        $ownerFullAddressParts = array_filter([
            $ownerStreetLine,
            $ownerSubdivisionLabel,
            'San Jose',
            'Rodriguez',
            'Rizal'
        ], fn($part) => $part !== '');
    }
}

$ownerFullAddress = htmlspecialchars(implode(', ', $ownerFullAddressParts), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Application for Barangay Clearance for Tricycle Permit - Barangay San Jose</title>
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
        <div class="main-head application-card orange-card application-card--muted py-3 my-5 rounded">
            <div class="main-head-content">
            <a href="<?= htmlspecialchars($baseUrl) ?>/Resident-End/Clearances/ClearancesLandingPage.php" class="back-link">< Go Back</a>

            <h1 class="form-title">Application for Barangay Clearance for Tricycle Permit</h1>
            <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

            <form action="#" method="POST">

                <h2 class="section-title text-center text-dark">Applicant Information</h2>
                <div class="form-row">
                    <div class="input-stack"><label class="top-label">Last Name <span class="required-asterisk">*</span></label><input type="text" name="applicant_last_name" required readonly value="<?php echo $ownerLastName; ?>"></div>
                    <div class="input-stack"><label class="top-label">First Name <span class="required-asterisk">*</span></label><input type="text" name="applicant_first_name" required readonly value="<?php echo $ownerFirstName; ?>"></div>
                    <div class="input-stack"><label class="top-label">Middle Name </label><input type="text" name="applicant_middle_name" readonly value="<?php echo $ownerMiddleName; ?>"></div>
                    <div class="input-stack">
                        <label class="top-label">Suffix</label>
                        <select name="applicant_suffix_display" class="text-bg-light" disabled>
                            <option value="" <?php echo ($ownerSuffix === '') ? 'selected' : ''; ?>>None</option>
                            <option value="Jr." <?php echo ($ownerSuffix === 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                            <option value="Sr." <?php echo ($ownerSuffix === 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                            <option value="III" <?php echo ($ownerSuffix === 'III') ? 'selected' : ''; ?>>III</option>
                            <option value="IV" <?php echo ($ownerSuffix === 'IV') ? 'selected' : ''; ?>>IV</option>
                        </select>
                        <input type="hidden" name="applicant_suffix" value="<?php echo htmlspecialchars($ownerSuffix, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="full-width">
                        <div class="input-stack">
                            <label class="top-label">Contact Number <span class="required-asterisk">*</span></label>
                            <input type="text" name="applicant_contact_number" value="<?php echo $ownerPhone; ?>" readonly required>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="full-width">
                        <div class="input-stack mb-3">
                            <label class="top-label" for="applicant_full_address">Complete Address <span class="required-asterisk">*</span></label>
                            <input type="text" id="applicant_full_address" name="applicant_full_address" value="<?php echo $ownerFullAddress; ?>" readonly>
                            <input type="hidden" id="applicantUnitNumber" value="<?php echo htmlspecialchars($ownerUnitNumberRaw, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" id="applicantStreetNumber" value="<?php echo htmlspecialchars($ownerHouseNumberRaw, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" id="applicantStreetName" value="<?php echo htmlspecialchars($ownerStreetNameRaw, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" id="applicantSubdivision" value="<?php echo htmlspecialchars($ownerSubdivisionRaw, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" id="applicantFullAddress" value="<?php echo $ownerFullAddress; ?>">
                        </div>
                    </div>
                </div>

                <div id="tricycleSection">
                    <h2 class="section-title text-center text-dark" style="font-size: 16px; margin-top: 0;">Driver's Information</h2>

                    <div class="form-row">
                        <div class="input-stack">
                            <label class="top-label">Last Name<span class="required-asterisk">*</span></label>
                            <input type="text" name="d_ln" value="<?php echo $ownerLastName; ?>" readonly>
                        </div>
                        <div class="input-stack">
                            <label class="top-label">First Name<span class="required-asterisk">*</span></label>
                            <input type="text" name="d_fn" value="<?php echo $ownerFirstName; ?>" readonly>
                        </div>
                        <div class="input-stack">
                            <label class="top-label">Middle Name</label>
                            <input type="text" name="d_mn" value="<?php echo $ownerMiddleName; ?>" readonly>
                        </div>
                        <div class="input-stack">
                            <label class="top-label">Suffix</label>
                            <input type="text" name="d_sfx" value="<?php echo $ownerSuffix; ?>" readonly>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="contact">
                            <div class="input-stack">
                                <label class="top-label">Contact Number</label>
                            <input type="text" name="d_phone" value="<?php echo $ownerPhone; ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="full-width">
                            <div class="input-stack mb-3">
                                <label class="top-label" for="driver_full_address">Complete Address <span class="required-asterisk">*</span></label>
                                <input type="text" id="driver_full_address" name="driver_full_address" value="<?php echo $ownerFullAddress; ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="full-width">
                            <div class="input-stack">
                                <label class="top-label">Tricycle Type</label>
                                <select id="tricycleTypeSelect" name="tricycle_type">
                                    <option value="">Select</option>
                                    <option value="Private">Private</option>
                                    <option value="TODA">TODA</option>
                                    <option value="PODA">PODA</option>
                                </select>
                            </div>

                            <div id="privateDetails" class="d-none">
                                <div class="input-stack">
                                    <label class="top-label">OR/CR Number:</label>
                                    <input type="text" name="or_cr">
                                </div>

                                <div class="input-stack">
                                    <label class="top-label">Plate Number:</label>
                                    <input type="text" name="plate">
                                </div>

                                <div class="input-stack">
                                    <label class="top-label">Body Number:</label>
                                    <input type="text" name="body">
                                </div>
                            </div>

                            <div id="todaDetails" class="d-none">
                                <div class="input-stack">
                                    <label class="top-label">Specify TODA:</label>
                                    <input type="text" name="spec_toda">
                                </div>
                            </div>

                            <div id="podaDetails" class="d-none">
                                <div class="input-stack">
                                    <label class="top-label">Specify PODA:</label>
                                    <input type="text" name="spec_poda">
                                </div>
                            </div>
                        </div>
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
            </div>
        </div>
    </main>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const form = document.querySelector("form");
            const submitBtn = form?.querySelector(".submit-btn");
            const tricycleTypeSelect = document.getElementById("tricycleTypeSelect");
            const privateDetails = document.getElementById("privateDetails");
            const todaDetails = document.getElementById("todaDetails");
            const podaDetails = document.getElementById("podaDetails");
            if (!form || !submitBtn) return;

            const updateState = () => {
                if (tricycleTypeSelect && privateDetails && todaDetails && podaDetails) {
                    const type = tricycleTypeSelect.value;
                    privateDetails.classList.toggle("d-none", type !== "Private");
                    todaDetails.classList.toggle("d-none", type !== "TODA");
                    podaDetails.classList.toggle("d-none", type !== "PODA");
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
