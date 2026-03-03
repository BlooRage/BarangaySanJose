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
    <title>Barangay Clearance for Commercial Permit - Barangay San Jose</title>
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

            <h1 class="form-title">Barangay Clearance for Commercial Permit</h1>
            <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

            <form action="#" method="POST">

                <h2 class="section-title text-center text-dark">Applicant Information</h2>
                <div class="form-row">
                    <div class="input-stack">
                        <label class="top-label">Last Name<span class="required-asterisk">*</span></label>
                        <input type="text" name="owner_last_name" required value="<?php echo $ownerLastName; ?>" readonly>
                    </div>
                    <div class="input-stack">
                        <label class="top-label">First Name<span class="required-asterisk">*</span></label>
                        <input type="text" name="owner_first_name" required value="<?php echo $ownerFirstName; ?>" readonly>
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Middle Name</label>
                        <input type="text" name="owner_middle_name" value="<?php echo $ownerMiddleName; ?>" readonly>
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Suffix</label>
                        <input type="text" name="owner_suffix" value="<?php echo $ownerSuffix; ?>" readonly>
                    </div>
                </div>

                <div class="form-row">
                    <div class="contact">
                        <div class="input-stack">
                            <label class="top-label">Contact Number</label>
                            <input type="text" name="owner_phone" value="<?php echo $ownerPhone; ?>" readonly>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="full-width">
                        <div class="input-stack mb-3">
                            <label class="top-label" for="owner_full_address">Address <span class="required-asterisk">*</span></label>
                            <input type="text" id="owner_full_address" name="owner_full_address" value="<?php echo $ownerFullAddress; ?>" readonly>
                        </div>
                        </div>
                </div>
                <div class="form-row">
                    <div class="full-width">
                        <div class="input-stack">
                            <label class="top-label" for="purpose">Purpose <span class="required-asterisk">*</span></label>
                            <textarea id="purpose" name="purpose" rows="4" required style="resize: none;"></textarea>
                        </div>
                    </div>
                </div>

                <h2 class="section-title text-center text-dark">Lot Information</h2>
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

                <div id="documentUploadSection">
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

                    <div class="form-row">
                        <div class="full-width">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="top-label" for="proofAddressType">Proof of Address <span class="required-asterisk">*</span></label>
                                    <select id="proofAddressType" name="proof_address_type" class="form-select">
                                        <option value="">Select</option>
                                        <option value="tct">Transfer Certificate of Title</option>
                                        <option value="tax_declaration">Tax Declaration</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="top-label" for="proofAddressFile">Upload Proof of Address <span class="required-asterisk">*</span></label>
                                    <input type="file" id="proofAddressFile" name="proof_address_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
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
                    <div id="secCertificateWrapper" class="form-row">
                        <div class="full-width">
                            <label class="top-label" for="secCertificateFile">Upload SEC Certificate <span class="required-asterisk">*</span></label>
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
            </div>
        </div>
    </main>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../JS-Script-Files/Resident-End/Clearances/commercialPermitScript.js"></script>
</body>
</html>
