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
                <a href="<?= htmlspecialchars($baseUrl) ?>/Resident-End/Clearances/ClearancesLandingPage.php" class="back-link d-inline-flex align-items-center text-decoration-none text-dark m-0 position-absolute start-0">
                    <i class="bi bi-arrow-left-short fs-3"></i>
                </a>
                <h1 class="form-title m-0">Barangay Clearance for Tricycle Permit</h1>
            </div>
            <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

            <form class="page-form" action="#" method="POST" enctype="multipart/form-data">

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
                            <label class="top-label" for="applicant_full_address">Address <span class="required-asterisk">*</span></label>
                            <input type="text" id="applicant_full_address" name="applicant_full_address" value="<?php echo $ownerFullAddress; ?>" readonly>
                            <input type="hidden" id="applicantUnitNumber" value="<?php echo htmlspecialchars($ownerUnitNumberRaw, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" id="applicantStreetNumber" value="<?php echo htmlspecialchars($ownerHouseNumberRaw, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" id="applicantStreetName" value="<?php echo htmlspecialchars($ownerStreetNameRaw, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" id="applicantSubdivision" value="<?php echo htmlspecialchars($ownerSubdivisionRaw, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" id="applicantFullAddress" value="<?php echo $ownerFullAddress; ?>">
                        </div>
                    </div>
                </div>
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
                <h2 class="section-title text-center text-dark">Vehicle Information</h2>
                <div class="form-row two-col-row">
                    <div class="input-stack">
                        <label class="top-label">Franchise <span class="required-asterisk">*</span></label>
                        <input type="text" name="vehicle_franchise" required>
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Make <span class="required-asterisk">*</span></label>
                        <input type="text" name="vehicle_make" required>
                    </div>
                </div>
                <div class="form-row two-col-row">
                    <div class="input-stack">
                        <label class="top-label">Plate Number <span class="required-asterisk">*</span></label>
                        <input type="text" id="plateNumber" name="plate_number" required>
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Body Number <span class="required-asterisk">*</span></label>
                        <input type="text" id="bodyNumber" name="body_number" inputmode="numeric" pattern="\d+" title="Numbers only" placeholder="Numbers only" required>
                        <div id="bodyNumberError" class="text-danger small d-none">Numbers only.</div>
                    </div>
                </div>
                <div class="form-row two-col-row">
                    <div class="input-stack">
                        <label class="top-label">Chassis Number <span class="required-asterisk">*</span></label>
                        <input type="text" id="chassisNumber" name="chassis_number" pattern="[A-Za-z0-9]{6}-[A-Za-z0-9]{6}" title="Format like AB1234-CD5678" placeholder="XXXXXX-XXXXXX" required>
                        <div id="chassisNumberError" class="text-danger small d-none">Invalid chassis number.</div>
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Motor Number <span class="required-asterisk">*</span></label>
                        <input type="text" id="motorNumber" name="motor_number" inputmode="numeric" pattern="\d+" title="Numbers only" placeholder="Numbers only" required>
                        <div id="motorNumberError" class="text-danger small d-none">Numbers only.</div>
                    </div>
                </div>
                <div class="form-row two-col-row">
                    <div class="input-stack">
                        <label class="top-label">O.R. Number <span class="required-asterisk">*</span></label>
                        <input type="text" id="orNumber" name="or_number" inputmode="numeric" pattern="\d{7,12}" title="7 to 12 digits" placeholder="7 to 12 digits" required>
                        <div id="orNumberError" class="text-danger small d-none">O.R. number must be 7 to 12 digits.</div>
                    </div>
                    <div class="input-stack">
                        <label class="top-label">C.R. Number <span class="required-asterisk">*</span></label>
                        <input type="text" id="crNumber" name="cr_number" inputmode="numeric" pattern="\d{7,12}" title="7 to 12 digits" placeholder="7 to 12 digits" required>
                        <div id="crNumberError" class="text-danger small d-none">C.R. number must be 7 to 12 digits.</div>
                    </div>
                </div>
                <div id="documentUploadSection" class="d-none">
                    <h2 class="section-title text-center text-dark">Document Upload</h2>
                    <p class="form-subtitle">Accepted: PDF, JPG, JPEG, PNG</p>
                    <div class="form-row">
                        <div class="full-width">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="top-label" for="validIdType">Valid Government-Issued ID <span class="required-asterisk">*</span></label>
                                    <select id="validIdType" name="valid_id_type" class="form-select">
                                        <option value="">Select</option>
                                        <option value="philsys">PhilSys ID</option>
                                        <option value="umid">UMID</option>
                                        <option value="passport">Passport</option>
                                        <option value="drivers_license">Driver's License</option>
                                        <option value="prc">PRC ID</option>
                                        <option value="postal">Postal ID</option>
                                        <option value="gsis">GSIS ID</option>
                                        <option value="sss">SSS ID</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="top-label" for="validIdFile">Upload Valid ID <span class="required-asterisk">*</span></label>
                                    <input type="file" id="validIdFile" name="valid_id_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="validIdNumberRow" class="form-row d-none">
                        <div class="full-width">
                            <div class="input-stack">
                                <label class="top-label" for="validIdNumber">Valid ID Number <span class="required-asterisk">*</span></label>
                                <input type="text" id="validIdNumber" name="valid_id_number" placeholder="Enter ID number">
                                <div id="validIdNumberError" class="text-danger small d-none">Invalid ID number</div>
                            </div>
                        </div>
                    </div>
                    <div class="form-row two-col-row">
                        <div class="input-stack">
                            <label class="top-label" for="tricycleTypeSelect">Tricycle Type <span class="required-asterisk">*</span></label>
                            <select id="tricycleTypeSelect" name="tricycle_type" class="form-select">
                                <option value="">Select</option>
                                <option value="PODA">PODA</option>
                                <option value="TODA">TODA</option>
                            </select>
                        </div>
                        <div class="input-stack">
                            <label class="top-label" id="tricycleCertLabel" for="tricycleCertFile">Upload Certification <span class="required-asterisk">*</span></label>
                            <input type="file" id="tricycleCertFile" name="tricycle_cert_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" disabled>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="full-width">
                            <div class="row mb-3">
                                <div class="col-md-12" id="ltoRegCol">
                                    <label class="top-label" for="ltoRegFiles">LTO Registration Documents (O.R. and C.R.) <span class="required-asterisk">*</span></label>
                                    <label class="upload-dropzone" id="ltoRegDropzone" for="ltoRegFiles">
                                        <i class="fa-solid fa-cloud-arrow-up"></i>
                                        <span>Drag files here or click to upload</span>
                                        <small>Accepted: PDF, JPG, JPEG, PNG</small>
                                    </label>
                                    <input type="file" id="ltoRegFiles" name="lto_registration_files[]" class="visually-hidden" accept=".pdf,.jpg,.jpeg,.png" multiple>
                                    <div id="ltoRegSelectedFile" class="selected-files small text-muted mt-2"></div>
                                    <p class="text-muted small mt-2 mb-0">If LTO Registration is not named to the owner/operator, please upload a notarized Deed of Sale.</p>
                                </div>
                                <div class="col-md-6 d-none" id="lastYearClearanceCol">
                                    <label class="top-label" for="lastYearClearanceFile">Barangay Clearance from Previous Year <span class="required-asterisk">*</span></label>
                                    <label class="upload-dropzone" id="lastYearClearanceDropzone" for="lastYearClearanceFile">
                                        <i class="fa-solid fa-cloud-arrow-up"></i>
                                        <span>Drag files here or click to upload</span>
                                        <small>Accepted: PDF, JPG, JPEG, PNG</small>
                                    </label>
                                    <input type="file" id="lastYearClearanceFile" name="last_year_clearance_file" class="visually-hidden" accept=".pdf,.jpg,.jpeg,.png" disabled>
                                    <div id="lastYearClearanceSelectedFile" class="selected-files small text-muted mt-2"></div>
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
    </main>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../JS-Script-Files/Resident-End/Clearances/tricycleClearanceScript.js"></script>
</body>
</html>

