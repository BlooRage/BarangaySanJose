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
    <title>Barangay Clearance for Water Permit - Barangay San Jose</title>
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
                <a href="<?= htmlspecialchars(appUrl('Resident-End/Clearances/ClearancesLandingPage.php')) ?>" class="back-link d-inline-flex align-items-center text-decoration-none text-dark m-0 position-absolute start-0">
                    <i class="bi bi-arrow-left-short fs-3"></i>
                </a>
                <h1 class="form-title m-0">Barangay Clearance for Water Permit</h1>
            </div>
            <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

            <form class="page-form" action="<?= htmlspecialchars($baseUrl) ?>/PhpFiles/Resident-End/documentRequestWorkflow.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="submit_request">
                <input type="hidden" name="document_type" value="Barangay Clearance for Water Permit">
                <input type="hidden" name="redirect" value="1">
                <input type="hidden" name="request_purpose" value="Water Permit Application">
                <h2 class="section-title text-center text-dark">Applicant Information</h2>
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
                            <label class="top-label">Contact Number <span class="required-asterisk">*</span></label>
                            <input type="text" name="applicant_contact_number" value="<?php echo $phoneNumber; ?>" readonly required>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="full-width">
                        <div class="input-stack">
                            <label class="top-label">Address <span class="required-asterisk">*</span></label>
                            <input type="text" name="applicant_full_address" readonly value="<?php echo $fullAddress; ?>">
                            <input type="hidden" id="applicantUnitNumber" value="<?php echo htmlspecialchars($unitNumber, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" id="applicantStreetNumber" value="<?php echo htmlspecialchars($streetNumber, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" id="applicantStreetName" value="<?php echo htmlspecialchars($streetName, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" id="applicantSubdivision" value="<?php echo htmlspecialchars($subdivision, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" id="applicantFullAddress" value="<?php echo $fullAddress; ?>">
                        </div>
                    </div>
                </div>

                <h2 class="section-title text-center text-dark">Lot Location</h2>
                <div class="form-row">
                    <div class="full-width">
                        <div class="beneficiary-block pt-3 pb-2">
                            <label class="top-label check-item">
                                <input type="checkbox" id="lotSameAddress" name="lot_same_address">
                                <span>Same address as applicant</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div id="lotAddressSystemRow" class="form-row">
                    <div class="full-width">
                        <div class="input-stack">
                            <label class="top-label" for="lotAddressSystem">Address System <span class="required-asterisk">*</span></label>
                            <select id="lotAddressSystem" name="lot_address_system" class="form-select w-100" required>
                                <option value="">Select</option>
                                <option value="house">House Numbering System</option>
                                <option value="lot_block">Lot/Block System</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div id="lotFullAddressWrapper" class="form-row d-none">
                    <div class="full-width">
                        <label class="top-label">Address Details (Same as Applicant) <span class="required-asterisk">*</span></label>
                        <input type="text" class="form-control" id="lotFullAddress" readonly value="<?php echo $fullAddress; ?>">
                    </div>
                </div>
                <div id="lotHouseSystemWrapper" class="form-row pt-0 d-none">
                    <div class="input-stack">
                        <label class="top-label" for="lot_unit_number">Unit / Apartment Number</label>
                        <input type="text" id="lot_unit_number" name="lot_unit_number">
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="lot_street_number">Street Number <span class="required-asterisk">*</span></label>
                        <input type="text" id="lot_street_number" name="lot_street_number">
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="lot_street_name">Street Name <span class="required-asterisk">*</span></label>
                        <input type="text" id="lot_street_name" name="lot_street_name">
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="lot_subdivision">Subdivision</label>
                        <input type="text" id="lot_subdivision" name="lot_subdivision">
                    </div>
                </div>
                <div id="lotBlockSystemWrapper" class="form-row pt-0 d-none">
                    <div class="input-stack">
                        <label class="top-label" for="lot_number">Lot Number <span class="required-asterisk">*</span></label>
                        <input type="text" id="lot_number" name="lot_number">
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="block_number">Block Number <span class="required-asterisk">*</span></label>
                        <input type="text" id="block_number" name="block_number">
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="lot_phase_number">Phase <span class="required-asterisk">*</span></label>
                        <input type="text" id="lot_phase_number" name="lot_phase_number">
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="lot_subdivision_block">Subdivision</label>
                        <input type="text" id="lot_subdivision_block" name="lot_subdivision">
                    </div>
                </div>
                <div id="lotBarangayRow" class="form-row">
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

                <h2 class="section-title text-center text-dark">Owner Details</h2>
                <div class="form-row">
                    <div class="full-width">
                        <label class="top-label" for="ownership_type">Form of Ownership<span class="required-asterisk">*</span></label>
                        <select id="ownership_type" name="ownership_type" class="form-select w-100" required>
                            <option value="">Select</option>
                            <option value="Individual">Individual</option>
                            <option value="Partnership">Partnership</option>
                            <option value="Company">Company</option>
                        </select>
                    </div>
                </div>

                <div id="documentUploadSection">
                    <h2 class="section-title text-center text-dark">Document Upload</h2>
                    <p class="form-subtitle">Accepted: PDF, JPG, JPEG, PNG</p>
                    <div class="form-row">
                        <div class="full-width">
                            <div class="input-stack">
                                <label class="top-label" for="proofAddressType">Proof of Address <span class="required-asterisk">*</span></label>
                                <select id="proofAddressType" name="proof_address_type" class="form-select">
                                    <option value="">Select</option>
                                    <option value="lease">Contract of Lease</option>
                                    <option value="tct">Transfer Certificate of Title</option>
                                    <option value="tax_declaration">Tax Declaration</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="full-width">
                            <label class="top-label" for="proofAddressFile">Upload Proof of Address <span class="required-asterisk">*</span></label>
                            <label class="upload-dropzone" id="proofAddressDropzone" for="proofAddressFile">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <span>Drag files here or click to upload</span>
                            </label>
                            <input type="file" id="proofAddressFile" name="proof_address_file" class="visually-hidden" accept=".pdf,.jpg,.jpeg,.png">
                            <div id="proofAddressSelectedFile" class="selected-files small text-muted mt-2">No file selected</div>
                        </div>
                    </div>
                    <div id="proofAddressNumberRow" class="form-row d-none">
                        <div class="full-width">
                            <div class="input-stack">
                                <label class="top-label" for="proofAddressNumber">Document Number <span class="required-asterisk">*</span></label>
                                <input type="text" id="proofAddressNumber" name="proof_address_number" placeholder="Enter document number">
                                <div id="proofAddressNumberError" class="text-danger small d-none">Invalid document number</div>
                            </div>
                        </div>
                    </div>
                    <div id="secCertificateWrapper" class="form-row d-none">
                        <div class="full-width">
                            <label class="top-label" for="secCertificateFile">Upload SEC Certificate (for Partnership or Company) <span class="required-asterisk">*</span></label>
                            <label class="upload-dropzone" id="secCertificateDropzone" for="secCertificateFile">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <span>Drag files here or click to upload</span>
                            </label>
                            <input type="file" id="secCertificateFile" name="sec_certificate_file" class="visually-hidden" accept=".pdf,.jpg,.jpeg,.png">
                            <div id="secCertificateSelectedFile" class="selected-files small text-muted mt-2"></div>
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
<script src="../../JS-Script-Files/Resident-End/formValidationHighlight.js"></script>
<script src="../../JS-Script-Files/Resident-End/Clearances/waterPermitScript.js"></script>
</body>
</html>
