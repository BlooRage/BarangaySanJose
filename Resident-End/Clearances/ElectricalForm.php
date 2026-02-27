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

$data = getResidentProfileData($conn, $_SESSION['user_id']);
$residentinformationtbl = $data['residentinformationtbl'] ?? [];
$residentaddresstbl = $data['residentaddresstbl'] ?? [];
$useraccountstbl = $data['useraccountstbl'] ?? [];

$firstName = htmlspecialchars($residentinformationtbl['firstname'] ?? '', ENT_QUOTES, 'UTF-8');
$lastName = htmlspecialchars($residentinformationtbl['lastname'] ?? '', ENT_QUOTES, 'UTF-8');
$middleName = htmlspecialchars($residentinformationtbl['middlename'] ?? '', ENT_QUOTES, 'UTF-8');
$suffix = $residentinformationtbl['suffix'] ?? '';
$phoneNumber = htmlspecialchars($useraccountstbl['phone_number'] ?? '', ENT_QUOTES, 'UTF-8');

$unitNumber = trim((string)($residentaddresstbl['unit_number'] ?? ''));
$streetNumber = trim((string)($residentaddresstbl['street_number'] ?? ''));
$streetName = trim((string)($residentaddresstbl['street_name'] ?? ''));
$phaseNumber = trim((string)($residentaddresstbl['phase_number'] ?? ''));
$subdivision = trim((string)($residentaddresstbl['subdivision'] ?? ''));

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
    <title>Application for Barangay Clearance for Electrical Permit - Barangay San Jose</title>
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
            <h1 class="form-title">Barangay Clearance for Electrical Permit</h1>
            <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

            <form action="#" method="POST">
                <h2 class="section-title text-center text-dark">Applicant Information</h2>
                 <div class="form-row">
                    <div class="full-width">
                        <div class="d-flex align-items-center justify-content-start gap-3 app-type-row">
                            <p class="if-building-note mb-0">APPLICATION TYPE:</p>
                            <div class="check-item">
                                <input type="radio" id="app_new" name="application_type" value="New" class="clearance-radio" required>
                                <label class="app-type-label" for="app_new">New Application</label>
                            </div>
                            <div class="check-item">
                                <input type="radio" id="app_renewal" name="application_type" value="Renewal" class="clearance-radio" required>
                                <label class="app-type-label" for="app_renewal">Renewal</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="input-stack"><label class="top-label">Last Name <span class="required-asterisk">*</span></label><input type="text" name="applicant_last_name" required readonly value="<?php echo $lastName; ?>"></div>
                    <div class="input-stack"><label class="top-label">First Name <span class="required-asterisk">*</span></label><input type="text" name="applicant_first_name" required readonly value="<?php echo $firstName; ?>"></div>
                    <div class="input-stack"><label class="top-label">Middle Name </label><input type="text" name="applicant_middle_name" readonly value="<?php echo $middleName; ?>"></div>
                    <div class="input-stack">
                        <label class="top-label">Suffix</label>
                        <select name="applicant_suffix_display" class="text-bg-light" disabled>
                            <option value="" <?php echo ($suffix === '') ? 'selected' : ''; ?>>None</option>
                            <option value="Jr." <?php echo ($suffix === 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                            <option value="Sr." <?php echo ($suffix === 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                            <option value="III" <?php echo ($suffix === 'III') ? 'selected' : ''; ?>>III</option>
                            <option value="IV" <?php echo ($suffix === 'IV') ? 'selected' : ''; ?>>IV</option>
                        </select>
                        <input type="hidden" name="applicant_suffix" value="<?php echo htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="full-width">
                        <div class="input-stack">
                            <label class="top-label">Full Address <span class="required-asterisk">*</span></label>
                            <input type="text" name="applicant_full_address" readonly value="<?php echo $fullAddress; ?>">
                        </div>
                    </div>
                </div>

                <h2 class="section-title text-center text-dark">Lot Location</h2>
                <div class="form-row">
                    <div class="input-stack"><label class="top-label">Lot Number </label><input type="text" name="lot_number"></div>
                    <div class="input-stack"><label class="top-label">Block Number </label><input type="text" name="block_number"></div>
                    <div class="input-stack">
                        <label class="top-label">TCT Number <span class="required-asterisk">*</span></label>
                        <input type="text" name="tct_number" id="tct_number" required title="Format: TCT/T + 5-10 digits (e.g., TCT-12345)">
                        <div id="tct_number_error" class="text-danger small d-none">Invalid TCT number</div>
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Tax Declaration Number <span class="required-asterisk">*</span></label>
                        <input type="text" name="tax_declaration_number" id="tax_declaration_number" required title="Format: TD + 3-10 digits, optional -digits suffix (e.g., TD-123456-01)">
                        <div id="tax_declaration_number_error" class="text-danger small d-none">Invalid Tax Declaration number</div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="full-width">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="top-label" for="lot_street_number">Street Number <span class="required-asterisk">*</span></label>
                                <input type="text" id="lot_street_number" name="lot_street_number" required>
                            </div>
                            <div class="col-md-4">
                                <label class="top-label" for="lot_street_name">Street Name <span class="required-asterisk">*</span></label>
                                <input type="text" id="lot_street_name" name="lot_street_name" required>
                            </div>
                            <div class="col-md-4">
                                <label class="top-label" for="lot_subdivision">Subdivision <span class="required-asterisk">*</span></label>
                                <input type="text" id="lot_subdivision" name="lot_subdivision" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="full-width">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="top-label" for="lot_barangay">Barangay</label>
                                <input type="text" id="lot_barangay" name="lot_barangay" readonly value="San Jose">
                            </div>
                            <div class="col-md-4">
                                <label class="top-label" for="lot_city">Municipality / City</label>
                                <input type="text" id="lot_city" name="lot_city" readonly value="Rodriguez (Montalban)">
                            </div>
                            <div class="col-md-4">
                                <label class="top-label" for="lot_province">Province</label>
                                <input type="text" id="lot_province" name="lot_province" readonly value="Rizal">
                            </div>
                        </div>
                    </div>
                </div>

                <h2 class="section-title text-center text-dark">Meralco Details</h2>
                <div class="form-row">
                    <div class="full-width">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="top-label" for="meralco_business_center">Meralco Business Center<span class="required-asterisk">*</span></label>
                                <select id="meralco_business_center" name="meralco_business_center" required>
                                    <option value="">Select</option>
                                    <option value="Rodriguez Extension Office">Rodriguez Extension Office</option>
                                    <option value="Marikina Branch">Marikina Branch</option>
                                    <option value="Commonwealth Branch">Commonwealth Branch</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="top-label" for="meralco_case_number">Meralco Case Number<span class="required-asterisk">*</span></label>
                                <input type="text" id="meralco_case_number" name="meralco_case_number" required>
                            </div>
                        </div>
                    </div>
                </div>

                <h2 class="section-title text-center text-dark">Owner Details</h2>
                <div class="form-row">
                    <div class="full-width">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="top-label" for="ownership_type">Form of Ownership<span class="required-asterisk">*</span></label>
                                <select id="ownership_type" name="ownership_type" class="form-select w-100" required>
                                    <option value="">Select</option>
                                    <option value="Individual">Individual</option>
                                    <option value="Partnership">Partnership</option>
                                    <option value="Company">Company</option>
                                    <option value="Government">Government</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="top-label">Are you the land owner?<span class="required-asterisk">*</span></label>
                                <div class="d-flex align-items-center gap-3 app-type-row">
                                    <div class="check-item">
                                        <input type="radio" id="land_owner_yes" name="is_land_owner" value="Yes" class="clearance-radio" required>
                                        <label class="app-type-label" for="land_owner_yes">Yes</label>
                                    </div>
                                    <div class="check-item">
                                        <input type="radio" id="land_owner_no" name="is_land_owner" value="No" class="clearance-radio" required>
                                        <label class="app-type-label" for="land_owner_no">No</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="landOwnerDetails" class="form-row d-none">
                    <div class="input-stack"><label class="top-label">Last Name<span class="required-asterisk">*</span></label><input type="text" name="land_owner_last_name"></div>
                    <div class="input-stack"><label class="top-label">First Name<span class="required-asterisk">*</span></label><input type="text" name="land_owner_first_name"></div>
                    <div class="input-stack"><label class="top-label">Middle Name</label><input type="text" name="land_owner_middle_name"></div>
                    <div class="input-stack"><label class="top-label">Suffix</label><input type="text" name="land_owner_suffix"></div>
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
        const landOwnerDetails = document.getElementById("landOwnerDetails");
        const tctNumberInput = document.getElementById("tct_number");
        const taxDeclarationInput = document.getElementById("tax_declaration_number");
        const tctError = document.getElementById("tct_number_error");
        const taxError = document.getElementById("tax_declaration_number_error");
        const tctRegex = /^(TCT|T)?\d{5,10}$/i;
        const taxRegex = /^(TD)?\d{3,10}\d{0,5}$/i;
        const normalizeId = (value) => value.replace(/[\s-]+/g, "");
        if (!form || !submitBtn) return;

        const updateState = () => {
            if (tctNumberInput) {
                const rawValue = tctNumberInput.value.trim();
                const normalized = normalizeId(rawValue);
                const hasValue = rawValue !== "";
                const isInvalid = hasValue && !tctRegex.test(normalized);
                tctNumberInput.setCustomValidity(isInvalid ? "Invalid TCT number" : "");
                if (tctError) {
                    tctError.classList.toggle("d-none", !isInvalid);
                }
            }
            if (taxDeclarationInput) {
                const rawValue = taxDeclarationInput.value.trim();
                const normalized = normalizeId(rawValue);
                const hasValue = rawValue !== "";
                const isInvalid = hasValue && !taxRegex.test(normalized);
                taxDeclarationInput.setCustomValidity(isInvalid ? "Invalid Tax Declaration number" : "");
                if (taxError) {
                    taxError.classList.toggle("d-none", !isInvalid);
                }
            }
            const selected = document.querySelector('input[name="is_land_owner"]:checked');
            const needsOwner = selected?.value === "No";
            if (landOwnerDetails) {
                landOwnerDetails.classList.toggle("d-none", !needsOwner);
                landOwnerDetails.querySelectorAll("input").forEach((input) => {
                    input.required = needsOwner && input.name !== "land_owner_middle_name" && input.name !== "land_owner_suffix";
                });
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
