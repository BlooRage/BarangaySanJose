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
$ownerSuffix = (string)($residentinformationtbl['suffix'] ?? '');
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
    $ownerLotNumber = trim((string)preg_replace('/^lot\\s*/i', '', $ownerHouseNumberRaw));
    $ownerBlockNumber = trim((string)preg_replace('/^(block|blk)\\s*/i', '', $ownerStreetNameRaw));
    $ownerPhaseValue = trim((string)preg_replace('/^phase\\s*/i', '', $ownerPhaseNumberRaw));

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
    <title>Business Clearance - Barangay San Jose</title>
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
        input[type="date"] {
            background-color: #ffffff !important;
            color: #212529;
        }
        input[type="date"]::-webkit-datetime-edit,
        input[type="date"]::-webkit-date-and-time-value {
            color: #212529;
        }
    </style>
</head>
<body>
<div class="d-flex min-vh-100">
    <?php include __DIR__ . '/../includes/resident_sidebar.php'; ?>
    <main id="div-mainDisplay" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 pb-md-5 pt-md-0">
            <div class="position-relative d-flex align-items-center justify-content-center mb-2 pt-4">
                <a href="<?= htmlspecialchars(appUrl('Resident-End/Clearances/ClearancesLandingPage.php')) ?>" class="back-link d-inline-flex align-items-center text-decoration-none text-dark m-0 position-absolute start-0">
                    <i class="bi bi-arrow-left-short fs-3"></i>
                </a>
                <h1 class="form-title m-0">Barangay Business Clearance</h1>
            </div>
            <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

            <form class="page-form" action="<?= htmlspecialchars($baseUrl) ?>/PhpFiles/Resident-End/documentRequestWorkflow.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="submit_request">
                <input type="hidden" name="document_type" value="Barangay Clearance for Business Permit">
                <input type="hidden" name="redirect" value="1">
                <input type="hidden" name="request_purpose" id="businessRequestPurpose" value="Business Permit - New Application">
                
                <h2 class="section-title text-center text-dark">Applicant Information</h2>
                <div class="form-row">
                    <div class="input-stack"><label class="top-label">Last Name <span class="required-asterisk">*</span></label><input type="text" name="o_ln" required value="<?php echo $ownerLastName; ?>" readonly></div>
                    <div class="input-stack"><label class="top-label">First Name <span class="required-asterisk">*</span></label><input type="text" name="o_fn" required value="<?php echo $ownerFirstName; ?>" readonly></div>
                    <div class="input-stack"><label class="top-label">Middle Name </label><input type="text" name="o_mn" value="<?php echo $ownerMiddleName; ?>" readonly></div>
                    <div class="input-stack">
                        <label class="top-label">Suffix</label>
                        <select name="o_sfx" disabled>
                            <option value="" <?php echo ($ownerSuffix === '') ? 'selected' : ''; ?>>None</option>
                            <option value="Jr." <?php echo ($ownerSuffix === 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                            <option value="Sr." <?php echo ($ownerSuffix === 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                            <option value="III" <?php echo ($ownerSuffix === 'III') ? 'selected' : ''; ?>>III</option>
                            <option value="IV" <?php echo ($ownerSuffix === 'IV') ? 'selected' : ''; ?>>IV</option>
                        </select>
                        <input type="hidden" name="o_sfx" value="<?php echo htmlspecialchars((string)$ownerSuffix, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <div class="form-row"><div class="full-width"><div class="input-stack"><label class="top-label">Contact Number <span class="required-asterisk">*</span></label><input type="text" name="o_phone" value="<?php echo $ownerPhone; ?>" readonly></div></div></div>
                <div id="ownerAddressWrapper" class="form-row">
                    <div class="full-width">
                        <div class="input-stack mb-3">
                            <label class="top-label" for="owner_full_address">Address <span class="required-asterisk">*</span></label>
                            <input type="text" id="owner_full_address" name="owner_full_address" value="<?php echo $ownerFullAddress; ?>" readonly>
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
                <h2 class="section-title text-center text-dark">Business Details</h2>
                <div class="form-row"><div class="full-width"><div class="input-stack"><label class="top-label">Name of Business <span class="required-asterisk">*</span></label><input type="text" name="business_name" required></div></div></div>
                <div class="form-row">
                    <div class="full-width">
                        <label class="top-label check-item">
                            <input type="checkbox" id="businessSameAddress" name="business_same_address">
                            <span>Same address as applicant</span>
                        </label>
                    </div>
                </div>
                <div id="businessAddressSystemRow" class="form-row">
                    <div class="full-width">
                        <div class="input-stack">
                            <label class="top-label" for="businessAddressSystem">Address System <span class="required-asterisk">*</span></label>
                            <select id="businessAddressSystem" name="business_address_system" class="form-select w-100">
                                <option value="">Select</option>
                                <option value="house">House Numbering System</option>
                                <option value="lot_block">Lot/Block System</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div id="businessFullAddressWrapper" class="form-row d-none">
                    <div class="full-width">
                        <label class="top-label">Address Details (Same as Applicant) <span class="required-asterisk">*</span></label>
                        <input type="text" class="form-control" id="businessFullAddressDisplay" readonly value="<?php echo $ownerFullAddress; ?>">
                    </div>
                </div>
                <div id="businessHouseSystemWrapper" class="form-row pt-0 d-none">
                    <div class="full-width">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="top-label" for="business_unit_number">Unit / Apartment Number</label>
                                <input type="text" id="business_unit_number" name="business_unit_number">
                            </div>
                            <div class="col-md-4">
                                <label class="top-label" for="business_street_number">Street Number <span class="required-asterisk">*</span></label>
                                <input type="text" id="business_street_number" name="business_street_number">
                            </div>
                            <div class="col-md-4">
                                <label class="top-label" for="business_street_name">Street Name <span class="required-asterisk">*</span></label>
                                <input type="text" id="business_street_name" name="business_street_name">
                            </div>
                        </div>
                    </div>
                </div>
                <div id="businessSubdivisionHouseRow" class="form-row pt-0 d-none">
                    <div class="full-width">
                        <div class="input-stack">
                            <label class="top-label" for="business_subdivision">Subdivision</label>
                            <input type="text" id="business_subdivision" name="business_subdivision">
                        </div>
                    </div>
                </div>
                <div id="businessBlockSystemWrapper" class="form-row pt-0 d-none">
                    <div class="input-stack">
                        <label class="top-label" for="business_lot_number">Lot Number <span class="required-asterisk">*</span></label>
                        <input type="text" id="business_lot_number" name="business_lot_number">
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="business_block_number">Block Number <span class="required-asterisk">*</span></label>
                        <input type="text" id="business_block_number" name="business_block_number">
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="business_phase_number">Phase <span class="required-asterisk">*</span></label>
                        <input type="text" id="business_phase_number" name="business_phase_number">
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="business_subdivision_block">Subdivision</label>
                        <input type="text" id="business_subdivision_block" name="business_subdivision_block">
                    </div>
                </div>
                <div id="businessBarangayRow" class="form-row">
                    <div class="full-width">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="top-label" for="business_barangay">Barangay</label>
                                <input type="text" id="business_barangay" name="business_barangay" readonly value="San Jose">
                            </div>
                            <div class="col-md-4">
                                <label class="top-label" for="business_city">Municipality / City</label>
                                <input type="text" id="business_city" name="business_city" readonly value="Rodriguez (Montalban)">
                            </div>
                            <div class="col-md-4">
                                <label class="top-label" for="business_province">Province</label>
                                <input type="text" id="business_province" name="business_province" readonly value="Rizal">
                            </div>
                        </div>
                        <input type="hidden" id="businessFullAddress" name="business_full_address" value="">
                    </div>
                </div>
                <div class="form-row two-col-row">
                    <div>
                        <label class="top-label">Date of Initial Operation <span class="required-asterisk">*</span></label>
                        <input
                            type="text"
                            name="initial_operation_date"
                            placeholder="Select date"
                            onfocus="this.type='date'"
                            onblur="if(!this.value){this.type='text'}"
                            required
                        >
                    </div>
                    <div>
                        <label class="top-label">Contact Number</label>
                        <input type="text" id="business_contact_number" name="business_contact_number" inputmode="numeric" maxlength="11">
                        <div id="business_contact_number_error" class="text-danger small d-none">Invalid contact number</div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="full-width">
                        <label class="top-label">Nature / Type of Business <span class="required-asterisk">*</span></label>
                        <input type="text" name="business_type" id="business_type" required placeholder="e.g. Retail Store, Food & Beverages, Services">
                    </div>
                </div>
                <div class="form-row">
                    <div class="full-width">
                        <label class="top-label">Ownership <span class="required-asterisk">*</span></label>
                        <select name="owner_type" id="ownerTypeSelect" required>
                            <option value="">Select</option>
                            <option value="Owner">Owner</option>
                            <option value="Renter">Renter</option>
                        </select>
                    </div>
                </div>

                <div id="documentUploadSection" class="d-none">
                    <h2 class="section-title text-center text-dark">Document Upload</h2>
                    <p class="form-subtitle">Accepted: PDF, JPG, JPEG, PNG</p>

                    <div id="documentUploadNew">
                    <div class="form-row">
                        <div class="full-width">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="top-label" for="businessRegType">Business Registration <span class="required-asterisk">*</span></label>
                                    <select id="businessRegType" name="business_reg_type" class="form-select">
                                        <option value="">Select</option>
                                        <option value="dti">DTI Certificate (For sole proprietors)</option>
                                        <option value="sec">SEC Certificate (For corporations)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="top-label" for="businessRegFile">Upload Business Registration <span class="required-asterisk">*</span></label>
                                    <input type="file" id="businessRegFile" name="business_reg_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="full-width">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="top-label" for="proofAddressType">Proof of Business Address <span class="required-asterisk">*</span></label>
                                    <select id="proofAddressType" name="proof_address_type" class="form-select">
                                        <option value="">Select</option>
                                        <option value="lease">Contract of Lease</option>
                                        <option value="tct">Transfer Certificate of Title</option>
                                        <option value="tax_declaration">Tax Declaration</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="top-label" for="proofAddressFile">Upload Proof of Address <span class="required-asterisk">*</span></label>
                                    <label class="upload-dropzone" id="proofAddressDropzone" for="proofAddressFile">
                                        <i class="fa-solid fa-cloud-arrow-up"></i>
                                        <span>Drag files here or click to upload</span>
                                    </label>
                                    <input type="file" id="proofAddressFile" name="proof_address_file" class="visually-hidden" accept=".pdf,.jpg,.jpeg,.png">
                                    <div id="proofAddressSelectedFile" class="selected-files small text-muted mt-2">No file selected</div>
                                </div>
                            </div>
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

                    <div class="form-row">
                        <div class="full-width">
                            <label class="top-label" for="businessPhotoFile">Picture of Establishment or Business <span class="required-asterisk">*</span></label>
                            <label class="upload-dropzone" id="businessPhotoDropzone" for="businessPhotoFile">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <span>Drag files here or click to upload</span>
                            </label>
                            <input type="file" id="businessPhotoFile" name="business_photo_file" class="visually-hidden" accept=".pdf,.jpg,.jpeg,.png">
                            <div id="businessPhotoSelectedFile" class="selected-files small text-muted mt-2"></div>
                        </div>
                    </div>
                    </div>

                    <div id="documentUploadRenewal" class="d-none">
                        <div class="form-row">
                            <div class="full-width">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="top-label" for="renewalBusinessRegType">Updated Business Registration <span class="required-asterisk">*</span></label>
                                        <select id="renewalBusinessRegType" name="renewal_business_reg_type" class="form-select">
                                            <option value="">Select</option>
                                            <option value="dti">DTI Certificate (For sole proprietors)</option>
                                            <option value="sec">SEC Certificate (For corporations)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="top-label" for="renewalBusinessRegFile">Upload Business Registration <span class="required-asterisk">*</span></label>
                                        <input type="file" id="renewalBusinessRegFile" name="renewal_business_reg_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="top-label" for="renewalProofAddressType">Updated Business Address <span class="required-asterisk">*</span></label>
                                        <select id="renewalProofAddressType" name="renewal_proof_address_type" class="form-select">
                                            <option value="">Select</option>
                                            <option value="lease">Contract of Lease</option>
                                            <option value="tct">Transfer Certificate of Title</option>
                                            <option value="tax_declaration">Tax Declaration</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="top-label" for="renewalProofAddressFile">Upload Proof of Address <span class="required-asterisk">*</span></label>
                                        <label class="upload-dropzone" id="renewalProofAddressDropzone" for="renewalProofAddressFile">
                                            <i class="fa-solid fa-cloud-arrow-up"></i>
                                            <span>Drag files here or click to upload</span>
                                        </label>
                                        <input type="file" id="renewalProofAddressFile" name="renewal_proof_address_file" class="visually-hidden" accept=".pdf,.jpg,.jpeg,.png">
                                        <div id="renewalProofAddressSelectedFile" class="selected-files small text-muted mt-2">No file selected</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="renewalProofAddressNumberRow" class="form-row d-none">
                            <div class="full-width">
                                <div class="input-stack">
                                    <label class="top-label" for="renewalProofAddressNumber">Document Number <span class="required-asterisk">*</span></label>
                                    <input type="text" id="renewalProofAddressNumber" name="renewal_proof_address_number" placeholder="Enter document number">
                                    <div id="renewalProofAddressNumberError" class="text-danger small d-none">Invalid document number</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="renterOwnerDetails" class="d-none">
                    <h2 class="section-title text-center text-dark">If Renter Occupant, Name of the Owner</h2>
                    <div class="form-row">
                        <div class="input-stack"><label class="top-label">Last Name<span class="required-asterisk">*</span></label><input type="text" name="ro_ln"></div>
                        <div class="input-stack"><label class="top-label">First Name<span class="required-asterisk">*</span></label><input type="text" name="ro_fn"></div>
                        <div class="input-stack"><label class="top-label">Middle Name</label><input type="text" name="ro_mn"></div>
                        <div class="input-stack"><label class="top-label">Suffix</label><select name="ro_sfx"><option value="">None</option><option value="Jr.">Jr.</option><option value="Sr.">Sr.</option><option value="III">III</option><option value="IV">IV</option></select></div>
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
    <script src="../../JS-Script-Files/Resident-End/dateFieldModal.js"></script>
    <script src="../../JS-Script-Files/Resident-End/Clearances/businessClearanceScript.js"></script>
</body>
</html>
