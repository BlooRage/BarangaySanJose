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
$applicantArea = htmlspecialchars($areaNumber, ENT_QUOTES, 'UTF-8');
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

        <main id="div-mainDisplay" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 pb-md-5 pt-md-0 bg-light">

            <div class="main-head application-card orange-card py-3 my-5 rounded application-card--muted">
                <div class="main-head-content">

                    <a href="/BarangaySanJose/Resident-End/Certificates/CertificatesLandingPage.php" class="back-link">&lt; Go Back</a>
                    <h1 class="form-title">Cohabitation</h1>
                    <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

                    <form method="POST" action="" id="cohabitationForm">

                        <!-- PERSONAL INFORMATION -->
                        <h2 class="section-title text-center text-dark">Personal Information</h2>
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
                                <select name="suffix_name_display" class="text-bg-light" disabled>
                                    <option value="" <?php echo (($residentinformationtbl['suffix'] ?? '') === '') ? 'selected' : ''; ?>>None</option>
                                    <option value="Jr." <?php echo (($residentinformationtbl['suffix'] ?? '') === 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                                    <option value="Sr." <?php echo (($residentinformationtbl['suffix'] ?? '') === 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                                    <option value="III" <?php echo (($residentinformationtbl['suffix'] ?? '') === 'III') ? 'selected' : ''; ?>>III</option>
                                    <option value="IV" <?php echo (($residentinformationtbl['suffix'] ?? '') === 'IV') ? 'selected' : ''; ?>>IV</option>
                                </select>
                                <input type="hidden" name="suffix_name" value="<?php echo htmlspecialchars($residentinformationtbl['suffix'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Complete Address <span class="required-asterisk">*</span></label>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="top-label">Unit / Apartment Number</label>
                                        <input type="text" class="form-control" name="full_unit_number" readonly value="<?php echo $applicantUnit; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="top-label">House / Lot Number</label>
                                        <input type="text" class="form-control" name="full_house_lot_number" readonly value="<?php echo $applicantHouseOrLot; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="top-label">Street / Block Name</label>
                                        <input type="text" class="form-control" name="full_street_block_name" readonly value="<?php echo $applicantStreetOrBlock; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="top-label">Subdivision</label>
                                        <input type="text" class="form-control" name="full_subdivision" readonly value="<?php echo $applicantSubdivision; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="top-label">Barangay</label>
                                        <input type="text" class="form-control" name="full_barangay" readonly value="<?php echo $applicantBarangay; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="top-label">Area</label>
                                        <input type="text" class="form-control" name="full_area_number" readonly value="<?php echo $applicantArea; ?>">
                                    </div>
                                </div>
                                <input type="hidden" name="full_address" value="<?php echo $fullAddress; ?>">
                            </div>
                        </div>

                        <h2 class="section-title text-center text-dark">Cohabitant / Partner Information</h2>
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

                        <div class="form-row">
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
                                <label class="top-label">Nationality <span class="required-asterisk">*</span></label>
                                <input type="text" name="cohabitant_nationality" required>
                            </div>
                            <div>
                                <label class="top-label">Date of Birth <span class="required-asterisk">*</span></label>
                                <input type="date" name="cohabitant_dob" required>
                            </div>
                            <div>
                                <label class="top-label">Occupation</label>
                                <input type="text" name="cohabitant_occupation">
                            </div>
                        </div>

                        <h2 class="section-title text-center text-dark">Cohabitant Address</h2>
                            <div class="full-width">
                                <div class="beneficiary-block">
                                    <label class="top-label check-item">
                                        <input type="checkbox" id="cohabitantSameAddress" name="cohabitantSameAddress">
                                        Same address as applicant
                                    </label>
                                </div>
                            </div>

                            <div id="cohabitantFullAddressWrapper" class="form-row d-none">
                                <div class="full-width">
                                    <label class="top-label">Address Details (Same as Applicant) <span class="required-asterisk">*</span></label>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="top-label">Unit / Apartment Number</label>
                                            <input type="text" class="form-control" name="cohabitant_full_unit_number" readonly value="<?php echo $applicantUnit; ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="top-label">House / Lot Number</label>
                                            <input type="text" class="form-control" name="cohabitant_full_house_lot_number" readonly value="<?php echo $applicantHouseOrLot; ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="top-label">Street / Block Name</label>
                                            <input type="text" class="form-control" name="cohabitant_full_street_block_name" readonly value="<?php echo $applicantStreetOrBlock; ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="top-label">Subdivision</label>
                                            <input type="text" class="form-control" name="cohabitant_full_subdivision" readonly value="<?php echo $applicantSubdivision; ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="top-label">Barangay</label>
                                            <input type="text" class="form-control" name="cohabitant_full_barangay" readonly value="<?php echo $applicantBarangay; ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="top-label">Area</label>
                                            <input type="text" class="form-control" name="cohabitant_full_area_number" readonly value="<?php echo $applicantArea; ?>">
                                        </div>
                                    </div>
                                    <input type="hidden" id="cohabitantFullAddress" name="cohabitant_full_address" value="<?php echo $fullAddress; ?>">
                                </div>
                            </div>

                        <div id="cohabitantAddressSystemRow" class="form-row">
                            <div class="full-width">
                                <label class="top-label" for="cohabitantAddressSystem">Address System <span class="required-asterisk">*</span></label>
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
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="top-label" for="cohabitantBarangay">Barangay <span class="required-asterisk">*</span></label>
                                        <select class="form-select" id="cohabitantBarangay" name="cohabitant_barangay" required>
                                            <option value="">Select city first</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="top-label" for="cohabSubdivision">Subdivision</label>
                                        <input type="text" class="form-control" id="cohabSubdivision" name="cohabitant_subdivision">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="top-label" for="cohabitantPostalCode">Postal Code <span class="required-asterisk">*</span></label>
                                        <input type="text" class="form-control" id="cohabitantPostalCode" name="cohabitant_postal_code" inputmode="numeric" maxlength="10" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-row two-col-row">
                            <div>
                                <label class="top-label">Relationship to Applicant <span class="required-asterisk">*</span></label>
                                <input type="text" name="cohabitant_relationship" required placeholder="e.g., Partner / Spouse">
                            </div>
                            <div>
                                <label class="top-label">Cohabitation Duration <span class="required-asterisk">*</span></label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <input type="number" min="1" name="cohabitation_duration_value" class="form-control" required placeholder="e.g., 3">
                                    </div>
                                    <div class="col-6">
                                        <select name="cohabitation_duration_unit" class="form-select" required>
                                            <option value="">Select</option>
                                            <option value="Years">Years</option>
                                            <option value="Months">Months</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-row two-col-row">
                            <div>
                                <label class="top-label">Started Cohabitation On <span class="required-asterisk">*</span></label>
                                <input type="date" name="cohabitation_start_date" required>
                            </div>
                            <div>
                                <label class="top-label">Purpose of Certificate <span class="required-asterisk">*</span></label>
                                <input type="text" name="cohabitation_purpose" required placeholder="e.g., Legal requirement">
                            </div>
                        </div>

                        <h2 class="section-title text-center text-dark">Cohabitation Address</h2>
                            <div class="full-width">
                                <div class="beneficiary-block">
                                    <label class="top-label check-item">
                                        <input type="checkbox" id="cohabitationSameAddress" name="cohabitationSameAddress">
                                        Cohabitation address is same as applicant address
                                    </label>
                                </div>
                            </div>
  

                            <div id="cohabitationFullAddressWrapper" class="form-row d-none">
                                <div class="full-width">
                                    <label class="top-label">Address Details (Same as Applicant) <span class="required-asterisk">*</span></label>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="top-label">Unit / Apartment Number</label>
                                            <input type="text" class="form-control" name="cohabitation_full_unit_number" readonly value="<?php echo $applicantUnit; ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="top-label">House / Lot Number</label>
                                            <input type="text" class="form-control" name="cohabitation_full_house_lot_number" readonly value="<?php echo $applicantHouseOrLot; ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="top-label">Street / Block Name</label>
                                            <input type="text" class="form-control" name="cohabitation_full_street_block_name" readonly value="<?php echo $applicantStreetOrBlock; ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="top-label">Subdivision</label>
                                            <input type="text" class="form-control" name="cohabitation_full_subdivision" readonly value="<?php echo $applicantSubdivision; ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="top-label">Barangay</label>
                                            <input type="text" class="form-control" name="cohabitation_full_barangay" readonly value="<?php echo $applicantBarangay; ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="top-label">Area</label>
                                            <input type="text" class="form-control" name="cohabitation_full_area_number" readonly value="<?php echo $applicantArea; ?>">
                                        </div>
                                    </div>
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




