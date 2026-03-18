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

$unitNumber = trim((string)($residentaddresstbl['unit_number'] ?? ''));
$streetNumber = trim((string)($residentaddresstbl['street_number'] ?? ''));
$streetName = trim((string)($residentaddresstbl['street_name'] ?? ''));
$phaseNumber = trim((string)($residentaddresstbl['phase_number'] ?? ''));
$subdivision = trim((string)($residentaddresstbl['subdivision'] ?? ''));
$areaNumber = trim((string)($residentaddresstbl['area_number'] ?? ''));

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
$applicantUnit = htmlspecialchars($unitNumber, ENT_QUOTES, 'UTF-8');
$applicantHouseOrLot = htmlspecialchars($streetNumber, ENT_QUOTES, 'UTF-8');
$applicantStreetOrBlock = htmlspecialchars($streetName, ENT_QUOTES, 'UTF-8');
$applicantSubdivision = htmlspecialchars($subdivision, ENT_QUOTES, 'UTF-8');
$applicantBarangay = 'San Jose';
$applicantMunicipality = 'Rodriguez (Montalban)';
$applicantProvince = 'Rizal';
$applicantArea = htmlspecialchars($areaNumber, ENT_QUOTES, 'UTF-8');

$cohabitationVariant = strtolower(trim((string)($_GET['variant'] ?? '')));
$isRelationshipJailVisitVariant = in_array($cohabitationVariant, ['relationship_jail_visit', 'conjugal_visit'], true);
$formTitle = $isRelationshipJailVisitVariant ? 'Certificate of Relationship for Jail Visitation' : 'Cohabitation';
$pageTitle = $isRelationshipJailVisitVariant ? 'Certificate of Relationship for Jail Visitation Application' : 'Cohabitation Application';
$defaultPurpose = $isRelationshipJailVisitVariant ? 'Jail Visitation' : '';
$partnerSectionTitle = $isRelationshipJailVisitVariant ? 'Detained Person Information' : 'Cohabitant / Partner Information';
$partnerAddressTitle = $isRelationshipJailVisitVariant ? 'Detained Partner Living Address' : 'Cohabitant Address';
$partnerSameAddressText = $isRelationshipJailVisitVariant ? 'Same living address as applicant' : 'Same address as applicant';
$partnerAddressSystemLabel = $isRelationshipJailVisitVariant ? 'Living Address System' : 'Address System';
$cohabitationStartLabel = $isRelationshipJailVisitVariant ? 'Started Living Together On (Month and Year)' : 'Started Cohabitation On (Month and Year)';
$cohabitationDurationLabel = $isRelationshipJailVisitVariant ? 'Length of Relationship' : 'Cohabitation Duration';
$purposeInputClass = $isRelationshipJailVisitVariant ? 'form-control text-bg-light' : 'form-control';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
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
        .cohabitation-detained-person-row {
            grid-template-columns: repeat(4, 1fr);
        }
        .cohabitation-detained-person-row .col-span-2 {
            grid-column: span 2;
        }
        input[name="cohabitant_dob"],
        #cohabitationStartDisplay {
            background-color: #ffffff !important;
            color: #111827 !important;
            -webkit-text-fill-color: #111827 !important;
            border-color: #a8a7a7 !important;
            opacity: 1 !important;
        }
        input[name="cohabitant_dob"]::-webkit-date-and-time-value {
            color: #111827 !important;
            -webkit-text-fill-color: #111827 !important;
            opacity: 1 !important;
        }
        input[name="cohabitant_dob"]::-webkit-datetime-edit,
        input[name="cohabitant_dob"]:invalid::-webkit-datetime-edit {
            color: #111827 !important;
            -webkit-text-fill-color: #111827 !important;
            opacity: 1 !important;
        }
        #cohabitationStartDisplay::placeholder {
            color: #111827 !important;
            opacity: 1;
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
                        <h1 class="form-title m-0"><?= htmlspecialchars($formTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                    </div>
                    <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

                    <form class="page-form" method="POST" action="<?= htmlspecialchars($baseUrl) ?>/PhpFiles/Resident-End/documentRequestWorkflow.php" id="cohabitationForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="submit_request">
                        <input type="hidden" name="document_type" value="cohabitation">
                        <input type="hidden" name="cohabitation_variant" value="<?= $isRelationshipJailVisitVariant ? 'relationship_jail_visit' : 'standard' ?>">
                        <input type="hidden" name="redirect" value="1">

                        <!-- PERSONAL INFORMATION -->
                        <h2 class="section-title text-center text-dark">Personal Information</h2>
                        <div class="form-row pt-0">
                            <div>
                                <label class="top-label">Last Name <span class="required-asterisk">*</span> </label>
                                <input type="text" name="last_name" readonly value="<?php echo htmlspecialchars($residentinformationtbl['lastname'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="top-label">First Name <span class="required-asterisk">*</span> </label>
                                <input type="text" name="first_name" readonly value="<?php echo htmlspecialchars($residentinformationtbl['firstname'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="top-label">Middle Name</label>
                                <input type="text" name="middle_name" readonly value="<?php echo htmlspecialchars($residentinformationtbl['middlename'] ?? ''); ?>">
                            </div>

                            <div>
                                <label class="top-label">Suffix</label>
                                <input type="text" class="text-bg-light" readonly value="<?php echo htmlspecialchars($residentinformationtbl['suffix'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <input type="hidden" name="suffix_name" value="<?php echo htmlspecialchars($residentinformationtbl['suffix'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                            <label class="top-label">Address <span class="required-asterisk">*</span></label>
                                <input type="text" class="form-control" name="full_address_display" readonly value="<?php echo $fullAddress; ?>">
                                <input type="hidden" name="full_unit_number" value="<?php echo $applicantUnit; ?>">
                                <input type="hidden" name="full_house_lot_number" value="<?php echo $applicantHouseOrLot; ?>">
                                <input type="hidden" name="full_street_block_name" value="<?php echo $applicantStreetOrBlock; ?>">
                                <input type="hidden" name="full_subdivision" value="<?php echo $applicantSubdivision; ?>">
                                <input type="hidden" name="full_barangay" value="<?php echo $applicantBarangay; ?>">
                                <input type="hidden" name="full_area_number" value="<?php echo $applicantArea; ?>">
                                <input type="hidden" name="full_address" value="<?php echo $fullAddress; ?>">
                            </div>
                        </div>

                        <h2 class="section-title text-center text-dark"><?= htmlspecialchars($partnerSectionTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                        <div class="form-row pt-0">
                            <div>
                                <label class="top-label">Last Name <span class="required-asterisk">*</span> </label>
                                <input type="text" name="cohabitant_last" required>
                            </div>
                            <div>
                                <label class="top-label">First Name <span class="required-asterisk">*</span> </label>
                                <input type="text" name="cohabitant_first" required>
                            </div>
                            <div>
                                <label class="top-label">Middle Name</label>
                                <input type="text" name="cohabitant_middle">
                            </div>

                            <div>
                                <label class="top-label">Suffix</label>
                                <select name="cohabitant_suffix">
                                    <option value="">None</option>
                                    <option value="Jr.">Jr.</option>
                                    <option value="Sr.">Sr.</option>
                                    <option value="III">III</option>
                                    <option value="IV">IV</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row cohabitation-detained-person-row">
                            <div>
                                <label class="top-label">Civil Status <span class="required-asterisk">*</span></label>
                                <select name="cohabitant_civil_status" required>
                                    <option value="" selected>Select</option>
                                    <option value="Single">Single</option>
                                    <option value="Married">Married</option>
                                    <option value="Widowed">Widowed</option>
                                    <option value="Separated">Separated</option>
                                    <option value="Annulled">Annulled</option>
                                </select>
                            </div>
                            <div>
                                <label class="top-label text-dark"><?= $isRelationshipJailVisitVariant ? 'Birthday / Date of Birth' : 'Date of Birth' ?> <span class="required-asterisk">*</span></label>
                                <input
                                    type="text"
                                    name="cohabitant_dob"
                                    placeholder="Select date"
                                    onfocus="this.type='date'"
                                    onblur="if(!this.value){this.type='text'}"
                                    required
                                >
                            </div>
                            <div class="col-span-2">
                                <label class="top-label">Nationality <span class="required-asterisk">*</span></label>
                                <input type="text" name="cohabitant_nationality" required>
                            </div>
                            <div class="<?= $isRelationshipJailVisitVariant ? 'd-none' : '' ?>">
                                <label class="top-label">Occupation <i>(leave blank if NA)</i></label>
                                <input type="text" name="cohabitant_occupation">
                            </div>
                        </div>

                        <?php if ($isRelationshipJailVisitVariant): ?>
                        <h2 class="section-title text-center text-dark">Proof of Detention Requirement</h2>
                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label" for="detentionProofType">Proof of Detention Type <span class="required-asterisk">*</span></label>
                                <select class="form-select" id="detentionProofType" name="detention_proof_type" required>
                                    <option value="">Select</option>
                                    <option value="Certificate of Detention">Certificate of Detention</option>
                                    <option value="Commitment Order">Commitment Order</option>
                                    <option value="Arrest Report">Arrest Report</option>
                                </select>
                            </div>
                        </div>
                        <div id="detentionProofDetails" class="d-none">
                            <div class="form-row two-col-row">
                                <div class="full-width">
                                    <div class="id-guidance-card">
                                    <div class="id-guidance-card__title">Proof Upload Guide</div>
                                    <div class="id-guidance-card__meta" id="detentionProofGuideText">Select a detention proof type first to continue.</div>
                                </div>
                                </div>
                            </div>
                            <div id="detentionProofAttachmentRows">
                                <div class="form-row" data-detention-attachment-row="1">
                                    <div class="full-width">
                                        <label class="top-label" for="detentionProofFile1">Attachment 1 <span class="required-asterisk">*</span></label>
                                        <label class="upload-dropzone" data-upload-input="detentionProofFile1" for="detentionProofFile1">
                                            <i class="fa-solid fa-upload"></i>
                                            <div class="detention-proof-prompt" id="detentionProofPrompt1">Drag and drop detention proof or click to upload</div>
                                            <small id="detentionProofFile1Meta">JPG, JPEG, PNG, or PDF. Saved as PDF.</small>
                                            <input type="file" class="form-control upload-dropzone-input" id="detentionProofFile1" name="detention_proof_files[]" accept=".jpg,.jpeg,.png,.pdf" required>
                                        </label>
                                    </div>
                                </div>
                                <div class="form-row d-none" data-detention-attachment-row="2">
                                    <div class="full-width">
                                        <label class="top-label" for="detentionProofFile2">Attachment 2</label>
                                        <label class="upload-dropzone" data-upload-input="detentionProofFile2" for="detentionProofFile2">
                                            <i class="fa-solid fa-upload"></i>
                                            <div class="detention-proof-prompt" id="detentionProofPrompt2">Drag and drop additional attachment or click to upload</div>
                                            <small id="detentionProofFile2Meta">JPG, JPEG, PNG, or PDF. Saved as PDF.</small>
                                            <input type="file" class="form-control upload-dropzone-input" id="detentionProofFile2" name="detention_proof_files[]" accept=".jpg,.jpeg,.png,.pdf">
                                        </label>
                                    </div>
                                </div>
                                <div class="form-row d-none" data-detention-attachment-row="3">
                                    <div class="full-width">
                                        <label class="top-label" for="detentionProofFile3">Attachment 3</label>
                                        <label class="upload-dropzone" data-upload-input="detentionProofFile3" for="detentionProofFile3">
                                            <i class="fa-solid fa-upload"></i>
                                            <div class="detention-proof-prompt" id="detentionProofPrompt3">Drag and drop additional attachment or click to upload</div>
                                            <small id="detentionProofFile3Meta">JPG, JPEG, PNG, or PDF. Saved as PDF.</small>
                                            <input type="file" class="form-control upload-dropzone-input" id="detentionProofFile3" name="detention_proof_files[]" accept=".jpg,.jpeg,.png,.pdf">
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="full-width d-flex justify-content-start">
                                    <button type="button" class="btn btn-outline-secondary" id="addDetentionAttachmentBtn">Add Attachment</button>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <h2 class="section-title text-center text-dark">Partner ID Requirement</h2>
                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label" for="cohabitantIdType">Valid ID Type <span class="required-asterisk">*</span></label>
                                <select class="form-select" id="cohabitantIdType" name="cohabitant_id_type" required>
                                    <option value="">Select</option>
                                    <option value="Philippine National ID">Philippine National ID</option>
                                    <option value="Passport">Passport</option>
                                    <option value="Driver's License">Driver's License</option>
                                    <option value="UMID">UMID</option>
                                    <option value="SSS ID">SSS ID</option>
                                    <option value="PRC ID">PRC ID</option>
                                    <option value="Postal ID">Postal ID</option>
                                    <option value="Voter's ID">Voter's ID</option>
                                    <option value="Senior Citizen ID">Senior Citizen ID</option>
                                    <option value="PWD ID">PWD ID</option>
                                    <option value="Barangay ID">Barangay ID</option>
                                    <option value="Other Government ID">Other Government ID</option>
                                </select>
                            </div>
                        </div>

                        <div id="cohabitantIdDetails" class="d-none">
                        <div class="form-row two-col-row">
                            <div>
                                <label class="top-label" for="cohabitantIdNumber">ID Number <span class="required-asterisk">*</span></label>
                                <input type="text" class="form-control" id="cohabitantIdNumber" name="cohabitant_id_number" required>
                            </div>
                            <div class="id-guidance-card">
                                <div class="id-guidance-card__title">ID Upload Guide</div>
                                <div class="id-guidance-card__meta" id="cohabitantIdGuideText">Select an ID type first to continue.</div>
                            </div>
                        </div>

                        <div id="cohabitantIdUploadRow" class="form-row two-col-row">
                            <div>
                                <label class="top-label" for="cohabitantIdFront"><span id="cohabitantIdFrontLabel">Front of Valid ID</span> <span class="required-asterisk">*</span></label>
                                <label class="upload-dropzone" data-upload-input="cohabitantIdFront" for="cohabitantIdFront">
                                    <i class="fa-solid fa-upload"></i>
                                    <div id="cohabitantIdFrontPrompt">Drag and drop front ID or click to upload</div>
                                    <small id="cohabitantIdFrontMeta">JPG, JPEG, PNG, WEBP, or PDF</small>
                                    <input type="file" class="form-control upload-dropzone-input" id="cohabitantIdFront" name="cohabitant_id_front" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
                                </label>
                            </div>
                            <div id="cohabitantIdBackField">
                                <label class="top-label" for="cohabitantIdBack">Back of Valid ID <span class="required-asterisk">*</span></label>
                                <label class="upload-dropzone" data-upload-input="cohabitantIdBack" for="cohabitantIdBack">
                                    <i class="fa-solid fa-upload"></i>
                                    <div>Drag and drop back ID or click to upload</div>
                                    <small id="cohabitantIdBackMeta">JPG, JPEG, PNG, WEBP, or PDF</small>
                                    <input type="file" class="form-control upload-dropzone-input" id="cohabitantIdBack" name="cohabitant_id_back" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
                                </label>
                            </div>
                        </div>
                        </div>
                        <?php endif; ?>

                        <h2 class="section-title text-center text-dark"><?= htmlspecialchars($partnerAddressTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                        <div class="form-row">
                            <div class="full-width">
                                <div class="beneficiary-block pt-3 pb-2">
                                    <label class="top-label check-item">
                                        <input type="checkbox" id="cohabitantSameAddress" name="cohabitantSameAddress">
                                        <span><?= htmlspecialchars($partnerSameAddressText, ENT_QUOTES, 'UTF-8') ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                            <div id="cohabitantFullAddressWrapper" class="form-row d-none">
                                <div class="full-width">
                                    <label class="top-label">Address Details (Same as Applicant) <span class="required-asterisk">*</span></label>
                                    <input type="text" class="form-control" name="cohabitant_full_address_display" readonly value="<?php echo $fullAddress; ?>">
                                    <input type="hidden" name="cohabitant_full_unit_number" value="<?php echo $applicantUnit; ?>">
                                    <input type="hidden" name="cohabitant_full_house_lot_number" value="<?php echo $applicantHouseOrLot; ?>">
                                    <input type="hidden" name="cohabitant_full_street_block_name" value="<?php echo $applicantStreetOrBlock; ?>">
                                    <input type="hidden" name="cohabitant_full_subdivision" value="<?php echo $applicantSubdivision; ?>">
                                    <input type="hidden" name="cohabitant_full_barangay" value="<?php echo $applicantBarangay; ?>">
                                    <input type="hidden" name="cohabitant_full_area_number" value="<?php echo $applicantArea; ?>">
                                    <input type="hidden" id="cohabitantFullAddress" name="cohabitant_full_address" value="<?php echo $fullAddress; ?>">
                                </div>
                            </div>

                        <div id="cohabitantAddressSystemRow" class="form-row">
                            <div class="full-width">
                                <label class="top-label" for="cohabitantAddressSystem"><?= htmlspecialchars($partnerAddressSystemLabel, ENT_QUOTES, 'UTF-8') ?> <span class="required-asterisk">*</span></label>
                                <select id="cohabitantAddressSystem" name="cohabitant_address_system" required>
                                    <option value="">Select</option>
                                    <option value="house">House Numbering System</option>
                                    <option value="lot_block">Lot/Block System</option>
                                </select>
                            </div>
                        </div>

                        <div id="cohabitantHouseSystemWrapper" class="form-row pt-0 d-none">
                            <div class="full-width">
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <label class="top-label" for="cohabUnitNumber">Unit / Apartment Number</label>
                                        <input type="text" class="form-control" id="cohabUnitNumber" name="cohabitant_unit_number">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="top-label" for="cohabHouseNumber">House Number <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="cohabHouseNumber" name="cohabitant_house_number">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="top-label" for="cohabStreetName">Street Name <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="cohabStreetName" name="cohabitant_street_name">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="top-label" for="cohabitantSubdivision">Subdivision</label>
                                        <input type="text" class="form-control" id="cohabitantSubdivision" name="cohabitant_subdivision">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="cohabitantLotBlockSystemWrapper" class="form-row pt-0 d-none">
                            <div class="full-width">
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <label class="top-label" for="cohabUnitNumberLot">Unit / Apartment Number</label>
                                        <input type="text" class="form-control" id="cohabUnitNumberLot" name="cohabitant_unit_number_lot">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="top-label" for="cohabLotNumber">Lot <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="cohabLotNumber" name="cohabitant_lot_number">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="top-label" for="cohabBlockNumber">Block <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="cohabBlockNumber" name="cohabitant_block_number">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="top-label" for="cohabPhaseNumber">Phase <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="cohabPhaseNumber" name="cohabitant_phase_number">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label class="top-label" for="cohabitantSubdivisionLot">Subdivision</label>
                                        <input type="text" class="form-control" id="cohabitantSubdivisionLot" name="cohabitant_subdivision_lot">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="cohabitantLocationWrapper" class="form-row d-none">
                            <div class="full-width">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="top-label" for="cohabitantRegionSelect">Region <span class="required-asterisk">*</span></label>
                                        <select class="form-select" id="cohabitantRegionSelect" name="cohabitant_region_select" required>
                                            <option value="">Select</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="top-label" for="cohabitantProvince">Province <span class="required-asterisk">*</span></label>
                                        <select class="form-select" id="cohabitantProvince" name="cohabitant_province" required>
                                            <option value="">Select region first</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="top-label" for="cohabitantCity">City / Municipality <span class="required-asterisk">*</span></label>
                                        <select class="form-select" id="cohabitantCity" name="cohabitant_city" required>
                                            <option value="">Select province first</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="full-width">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="top-label" for="cohabitantBarangay">Barangay <span class="required-asterisk">*</span></label>
                                        <select class="form-select" id="cohabitantBarangay" name="cohabitant_barangay" required>
                                            <option value="">Select city first</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="top-label" for="cohabitantPostalCode">Postal Code <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="cohabitantPostalCode" name="cohabitant_postal_code" inputmode="numeric" maxlength="10" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="relationshipLengthWrapper" class="form-row two-col-row<?= $isRelationshipJailVisitVariant ? ' d-none' : '' ?>">
                            <div>
                                <label class="top-label text-dark"><?= htmlspecialchars($cohabitationStartLabel, ENT_QUOTES, 'UTF-8') ?> <span class="required-asterisk">*</span></label>
                                <input type="text" class="form-control" id="cohabitationStartDisplay" value="" placeholder="Select month and year" readonly required>
                                <input type="hidden" id="cohabitationStartDate" name="cohabitation_start_date" value="">
                            </div>
                            <div>
                                <label class="top-label"><?= htmlspecialchars($cohabitationDurationLabel, ENT_QUOTES, 'UTF-8') ?></label>
                                <input type="text" name="cohabitation_duration_display" class="form-control text-bg-light" readonly placeholder="Will be computed from the start date">
                                <input type="hidden" name="cohabitation_duration" value="">
                                <input type="hidden" name="cohabitation_duration_value" value="">
                                <input type="hidden" name="cohabitation_duration_unit" value="">
                            </div>
                        </div>

                        <div class="form-row two-col-row">
                            <div>
                                <label class="top-label">Relationship to Applicant <span class="required-asterisk">*</span></label>
                                <?php if ($isRelationshipJailVisitVariant): ?>
                                    <select class="form-select" id="cohabitantRelationshipSelect" name="cohabitant_relationship" required>
                                        <option value="">Select</option>
                                        <option value="Partner">Partner</option>
                                        <option value="Spouse">Spouse</option>
                                        <option value="Parent">Parent</option>
                                        <option value="Child">Child</option>
                                        <option value="Sibling">Sibling</option>
                                        <option value="Grandparent">Grandparent</option>
                                        <option value="Grandchild">Grandchild</option>
                                        <option value="Relative">Relative</option>
                                        <option value="Friend">Friend</option>
                                        <option value="Guardian">Guardian</option>
                                        <option value="Other">Other</option>
                                    </select>
                                <?php else: ?>
                                    <input type="text" name="cohabitant_relationship" required placeholder="e.g., Partner / Spouse">
                                <?php endif; ?>
                            </div>
                            <div>
                                <label class="top-label">Purpose of Document Request <span class="required-asterisk">*</span></label>
                                <input
                                    type="text"
                                    name="purpose"
                                    required
                                    placeholder="e.g., Legal requirement"
                                    value="<?= htmlspecialchars($defaultPurpose, ENT_QUOTES, 'UTF-8') ?>"
                                    class="<?= $purposeInputClass ?>"
                                    <?= $isRelationshipJailVisitVariant ? 'readonly' : '' ?>
                                >
                            </div>
                        </div>

                        <?php if ($isRelationshipJailVisitVariant): ?>
                        <h2 class="section-title text-center text-dark">Proof of Relationship Requirement</h2>
                        <div class="form-row">
                            <div class="full-width">
                                <div class="id-guidance-card">
                                    <div class="id-guidance-card__title">Relationship Proof Guide</div>
                                    <div class="id-guidance-card__meta">Upload a photo of them together or any document/image that helps prove their relationship. Saved as PDF.</div>
                                </div>
                            </div>
                        </div>
                        <div id="relationshipProofAttachmentRows">
                            <div class="form-row" data-relationship-attachment-row="1">
                                <div class="full-width">
                                    <label class="top-label" for="relationshipProofFile1">Attachment 1 <span class="required-asterisk">*</span></label>
                                    <label class="upload-dropzone" data-upload-input="relationshipProofFile1" for="relationshipProofFile1">
                                        <i class="fa-solid fa-upload"></i>
                                        <div class="relationship-proof-prompt" id="relationshipProofPrompt1">Drag and drop proof of relationship or click to upload</div>
                                        <small id="relationshipProofFile1Meta">JPG, JPEG, PNG, or PDF. Saved as PDF.</small>
                                        <input type="file" class="form-control upload-dropzone-input" id="relationshipProofFile1" name="relationship_proof_files[]" accept=".jpg,.jpeg,.png,.pdf" required>
                                    </label>
                                </div>
                            </div>
                            <div class="form-row d-none" data-relationship-attachment-row="2">
                                <div class="full-width">
                                    <label class="top-label" for="relationshipProofFile2">Attachment 2</label>
                                    <label class="upload-dropzone" data-upload-input="relationshipProofFile2" for="relationshipProofFile2">
                                        <i class="fa-solid fa-upload"></i>
                                        <div class="relationship-proof-prompt" id="relationshipProofPrompt2">Drag and drop additional attachment or click to upload</div>
                                        <small id="relationshipProofFile2Meta">JPG, JPEG, PNG, or PDF. Saved as PDF.</small>
                                        <input type="file" class="form-control upload-dropzone-input" id="relationshipProofFile2" name="relationship_proof_files[]" accept=".jpg,.jpeg,.png,.pdf">
                                    </label>
                                </div>
                            </div>
                            <div class="form-row d-none" data-relationship-attachment-row="3">
                                <div class="full-width">
                                    <label class="top-label" for="relationshipProofFile3">Attachment 3</label>
                                    <label class="upload-dropzone" data-upload-input="relationshipProofFile3" for="relationshipProofFile3">
                                        <i class="fa-solid fa-upload"></i>
                                        <div class="relationship-proof-prompt" id="relationshipProofPrompt3">Drag and drop additional attachment or click to upload</div>
                                        <small id="relationshipProofFile3Meta">JPG, JPEG, PNG, or PDF. Saved as PDF.</small>
                                        <input type="file" class="form-control upload-dropzone-input" id="relationshipProofFile3" name="relationship_proof_files[]" accept=".jpg,.jpeg,.png,.pdf">
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="full-width d-flex justify-content-start">
                                <button type="button" class="btn btn-outline-secondary" id="addRelationshipAttachmentBtn">Add Attachment</button>
                            </div>
                        </div>

                        <h2 class="section-title text-center text-dark">Detention Facility Details</h2>
                        <div class="form-row two-col-row">
                            <div class="full-width">
                                <label class="top-label" for="detentionFacility">Police Station / BJMP <span class="required-asterisk">*</span></label>
                                <select class="form-select" id="detentionFacility" name="detention_facility" required>
                                    <option value="">Select</option>
                                    <option value="Rodriguez Municipal Police Station">Rodriguez Municipal Police Station</option>
                                    <option value="San Mateo Municipal Police Station">San Mateo Municipal Police Station</option>
                                    <option value="Antipolo City Police Station">Antipolo City Police Station</option>
                                    <option value="BJMP Rodriguez District Jail">BJMP Rodriguez District Jail</option>
                                    <option value="BJMP Rizal District Jail">BJMP Rizal District Jail</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div id="detentionFacilityOtherWrapper" class="d-none">
                                <label class="top-label" for="detentionFacilityOther">Other Facility Name <span class="required-asterisk">*</span></label>
                                <input type="text" class="form-control" id="detentionFacilityOther" name="detention_facility_other">
                            </div>
                        </div>
                        <input type="hidden" name="cohabitation_children_count" value="0">
                        <?php endif; ?>

                        <?php if (!$isRelationshipJailVisitVariant): ?>
                        <h2 class="section-title text-center text-dark">Cohabitation Address</h2>
                        <div class="form-row">
                            <div class="full-width">
                                <div class="beneficiary-block pt-3 pb-2">
                                    <label class="top-label check-item">
                                        <input type="checkbox" id="cohabitationSameAddress" name="cohabitationSameAddress">
                                        <span>Cohabitation address is same as applicant address</span>
                                    </label>
                                </div>
                            </div>
                        </div>


                            <div id="cohabitationFullAddressWrapper" class="form-row d-none">
                                <div class="full-width">
                                    <label class="top-label">Address Details (Same as Applicant) <span class="required-asterisk">*</span></label>
                                    <input type="text" class="form-control" name="cohabitation_full_address_display" readonly value="<?php echo $fullAddress; ?>">
                                    <input type="hidden" name="cohabitation_full_unit_number" value="<?php echo $applicantUnit; ?>">
                                    <input type="hidden" name="cohabitation_full_house_lot_number" value="<?php echo $applicantHouseOrLot; ?>">
                                    <input type="hidden" name="cohabitation_full_street_block_name" value="<?php echo $applicantStreetOrBlock; ?>">
                                    <input type="hidden" name="cohabitation_full_subdivision" value="<?php echo $applicantSubdivision; ?>">
                                    <input type="hidden" name="cohabitation_full_barangay" value="<?php echo $applicantBarangay; ?>">
                                    <input type="hidden" name="cohabitation_full_area_number" value="<?php echo $applicantArea; ?>">
                                    <input type="hidden" id="cohabitationFullAddress" name="cohabitation_full_address" value="<?php echo $fullAddress; ?>">
                                </div>
                            </div>

                        <div id="cohabitationAddressSystemRow" class="form-row">
                            <div class="full-width">
                                <label class="top-label" for="cohabitationAddressSystem">Cohabitation Address System <span class="required-asterisk">*</span></label>
                                <select id="cohabitationAddressSystem" name="cohabitation_address_system" required>
                                    <option value="">Select</option>
                                    <option value="house">House Numbering System</option>
                                    <option value="lot_block">Lot/Block System</option>
                                </select>
                            </div>
                        </div>

                        <div id="cohabitationHouseSystemWrapper" class="form-row pt-0 d-none">
                            <div class="full-width">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="top-label" for="cohabitationUnitNumber">Unit / Apartment Number</label>
                                        <input type="text" class="form-control" id="cohabitationUnitNumber" name="cohabitation_unit_number">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="top-label" for="cohabitationHouseNumber">House Number <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="cohabitationHouseNumber" name="cohabitation_house_number">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="top-label" for="cohabitationStreetName">Street Name <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="cohabitationStreetName" name="cohabitation_street_name">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="top-label" for="cohabitationSubdivision">Subdivision</label>
                                        <input type="text" class="form-control" id="cohabitationSubdivision" name="cohabitation_subdivision">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="top-label" for="cohabitationAreaNumber">Area <span class="required-asterisk">*</span></label>
                                        <select class="form-select" id="cohabitationAreaNumber" name="cohabitation_area_number">
                                            <option value="">Select</option>
                                            <option value="Area 01">Area 01</option>
                                            <option value="Area 1A">Area 1A</option>
                                            <option value="Area 02">Area 02</option>
                                            <option value="Area 03">Area 03</option>
                                            <option value="Area 04">Area 04</option>
                                            <option value="Area 05">Area 05</option>
                                            <option value="Area 06">Area 06</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="cohabitationLocalityRow" class="form-row pt-0 d-none">
                            <div>
                                <label class="top-label" for="cohabitationBarangayFixed">Barangay</label>
                                <input type="text" class="form-control text-bg-light" id="cohabitationBarangayFixed" name="cohabitation_barangay" readonly value="<?php echo htmlspecialchars($applicantBarangay, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div>
                                <label class="top-label" for="cohabitationMunicipalityFixed">Municipality</label>
                                <input type="text" class="form-control text-bg-light" id="cohabitationMunicipalityFixed" name="cohabitation_municipality" readonly value="<?php echo htmlspecialchars($applicantMunicipality, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div>
                                <label class="top-label" for="cohabitationProvinceFixed">Province</label>
                                <input type="text" class="form-control text-bg-light" id="cohabitationProvinceFixed" name="cohabitation_province" readonly value="<?php echo htmlspecialchars($applicantProvince, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>

                        <div id="cohabitationLotBlockSystemWrapper" class="form-row pt-0 d-none">
                            <div class="full-width">
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <label class="top-label" for="cohabitationUnitNumberLot">Unit / Apartment Number</label>
                                        <input type="text" class="form-control" id="cohabitationUnitNumberLot" name="cohabitation_unit_number_lot">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="top-label" for="cohabitationLotNumber">Lot <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="cohabitationLotNumber" name="cohabitation_lot_number">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="top-label" for="cohabitationBlockNumber">Block <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="cohabitationBlockNumber" name="cohabitation_block_number">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="top-label" for="cohabitationPhaseNumber">Phase <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="cohabitationPhaseNumber" name="cohabitation_phase_number">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="top-label" for="cohabitationSubdivisionLot">Subdivision</label>
                                        <input type="text" class="form-control" id="cohabitationSubdivisionLot" name="cohabitation_subdivision_lot">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="top-label" for="cohabitationAreaNumberLot">Area <span class="required-asterisk">*</span></label>
                                        <select class="form-select" id="cohabitationAreaNumberLot" name="cohabitation_area_number_lot">
                                            <option value="">Select</option>
                                            <option value="Area 01">Area 01</option>
                                            <option value="Area 1A">Area 1A</option>
                                            <option value="Area 02">Area 02</option>
                                            <option value="Area 03">Area 03</option>
                                            <option value="Area 04">Area 04</option>
                                            <option value="Area 05">Area 05</option>
                                            <option value="Area 06">Area 06</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!$isRelationshipJailVisitVariant): ?>
                            <h2 class="section-title text-center text-dark">Other Cohabitation Details</h2>
                            <div class="form-row">
                                <div class="full-width">
                                    <label class="top-label" for="cohabitationChildrenCount">Do They Have Children? <span class="required-asterisk">*</span></label>
                                    <select class="form-select" id="cohabitationChildrenCount" name="cohabitation_children_count" required>
                                        <option value="">Select</option>
                                        <option value="0">None</option>
                                        <option value="1">1 Child</option>
                                        <option value="2">2 Children</option>
                                        <option value="3">3 Children</option>
                                        <option value="4">4 Children</option>
                                        <option value="5">5 Children</option>
                                    </select>
                                </div>
                            </div>

                            <div id="cohabitationChildFields">
                                <div class="form-row two-col-row d-none" data-child-row="1">
                                    <div>
                                        <label class="top-label" for="cohabitationChild1Name">Child 1 Name <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="cohabitationChild1Name" name="cohabitation_child_1_name">
                                    </div>
                                    <div>
                                        <label class="top-label" for="cohabitationChild1Age">Child 1 Age <span class="required-asterisk">*</span></label>
                                        <input type="number" min="0" class="form-control" id="cohabitationChild1Age" name="cohabitation_child_1_age">
                                    </div>
                                </div>
                                <div class="form-row two-col-row d-none" data-child-row="2">
                                    <div>
                                        <label class="top-label" for="cohabitationChild2Name">Child 2 Name <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="cohabitationChild2Name" name="cohabitation_child_2_name">
                                    </div>
                                    <div>
                                        <label class="top-label" for="cohabitationChild2Age">Child 2 Age <span class="required-asterisk">*</span></label>
                                        <input type="number" min="0" class="form-control" id="cohabitationChild2Age" name="cohabitation_child_2_age">
                                    </div>
                                </div>
                                <div class="form-row two-col-row d-none" data-child-row="3">
                                    <div>
                                        <label class="top-label" for="cohabitationChild3Name">Child 3 Name <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="cohabitationChild3Name" name="cohabitation_child_3_name">
                                    </div>
                                    <div>
                                        <label class="top-label" for="cohabitationChild3Age">Child 3 Age <span class="required-asterisk">*</span></label>
                                        <input type="number" min="0" class="form-control" id="cohabitationChild3Age" name="cohabitation_child_3_age">
                                    </div>
                                </div>
                                <div class="form-row two-col-row d-none" data-child-row="4">
                                    <div>
                                        <label class="top-label" for="cohabitationChild4Name">Child 4 Name <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="cohabitationChild4Name" name="cohabitation_child_4_name">
                                    </div>
                                    <div>
                                        <label class="top-label" for="cohabitationChild4Age">Child 4 Age <span class="required-asterisk">*</span></label>
                                        <input type="number" min="0" class="form-control" id="cohabitationChild4Age" name="cohabitation_child_4_age">
                                    </div>
                                </div>
                                <div class="form-row two-col-row d-none" data-child-row="5">
                                    <div>
                                        <label class="top-label" for="cohabitationChild5Name">Child 5 Name <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="cohabitationChild5Name" name="cohabitation_child_5_name">
                                    </div>
                                    <div>
                                        <label class="top-label" for="cohabitationChild5Age">Child 5 Age <span class="required-asterisk">*</span></label>
                                        <input type="number" min="0" class="form-control" id="cohabitationChild5Age" name="cohabitation_child_5_age">
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>




                                <div class="agreement-row">
                                    <label class="agreement-text check-item">
                                        <input type="checkbox" id="cohabitationAgree" name="cohabitationAgree" required>
                                        I hereby certify that the above information is true and correct to the best of my knowledge and belief.
                                    </label>

                                    <button type="submit" class="submit-btn" id="cohabitationSubmit" disabled>SUBMIT</button>
                                </div>

                    </form>
        </main>

    </div>

    <div class="modal fade residency-picker-modal" id="cohabitationStartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <div class="residency-picker-panel-title text-dark"><?= htmlspecialchars($isRelationshipJailVisitVariant ? 'Started Living Together On' : 'Started Cohabitation On', ENT_QUOTES, 'UTF-8') ?></div>
                        <p class="residency-picker-panel-note mb-0"><?= htmlspecialchars($isRelationshipJailVisitVariant ? 'Choose the month and year when they started living together.' : 'Choose the month and year when cohabitation started.', ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="residency-picker-preview" id="cohabitationStartPreview">No month selected yet.</div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small mb-1" for="cohabitationStartMonth">Month</label>
                            <select class="form-select" id="cohabitationStartMonth">
                                <option value="">Select month</option>
                                <option value="01">January</option>
                                <option value="02">February</option>
                                <option value="03">March</option>
                                <option value="04">April</option>
                                <option value="05">May</option>
                                <option value="06">June</option>
                                <option value="07">July</option>
                                <option value="08">August</option>
                                <option value="09">September</option>
                                <option value="10">October</option>
                                <option value="11">November</option>
                                <option value="12">December</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1" for="cohabitationStartYear">Year</label>
                            <select class="form-select" id="cohabitationStartYear">
                                <option value="">Select year</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="cohabitationStartApply">Apply</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= htmlspecialchars($baseUrl) ?>/JS-Script-Files/Resident-End/dateFieldModal.js"></script>
    <script src="<?= htmlspecialchars($baseUrl) ?>/JS-Script-Files/Resident-End/Certificates/cohabitationFormScript.js?v=20260311-05"></script>
</body>

</html>
