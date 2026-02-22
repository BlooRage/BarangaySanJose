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
    <title>Residency Application</title>
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

        <main id="div-mainDisplay" class="flex-grow-1 px-4 pb-4  pt-0 px-md-5 pb-md-5 pt-md-0 bg-light">
            <div class="main-head application-card orange-card py-3 my-5 rounded application-card--muted">
                <div class="main-head-content">
                    <a href="/BarangaySanJose/Resident-End/Certificates/CertificatesLandingPage.php" class="back-link">&lt; Go Back</a>
                    <h1 class="form-title">Residency</h1>
                    <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

                    <form method="POST" action="" enctype="multipart/form-data">
                        <h2 class="section-title text-center text-dark">Information</h2>

                        <div class="form-row pt-0">
                            <div>
                                <label class="top-label">First Name <span class="required-asterisk">*</span></label>
                                <input type="text" name="first_name" readonly value="<?php echo $firstName; ?>">
                            </div>
                            <div>
                                <label class="top-label">Last Name <span class="required-asterisk">*</span></label>
                                <input type="text" name="last_name" readonly value="<?php echo $lastName; ?>">
                            </div>
                            <div>
                                <label class="top-label">Middle Name</label>
                                <input type="text" name="middle_name" readonly value="<?php echo $middleName; ?>">
                            </div>
                            <div>
                                <label class="top-label">Suffix</label>
                                <select name="suffix_name_display" class="text-bg-light" disabled>
                                    <option value="" <?php echo ($suffix === '') ? 'selected' : ''; ?>>None</option>
                                    <option value="Jr." <?php echo ($suffix === 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                                    <option value="Sr." <?php echo ($suffix === 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                                    <option value="III" <?php echo ($suffix === 'III') ? 'selected' : ''; ?>>III</option>
                                    <option value="IV" <?php echo ($suffix === 'IV') ? 'selected' : ''; ?>>IV</option>
                                </select>
                                <input type="hidden" name="suffix_name" value="<?php echo htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Contact Number <span class="required-asterisk">*</span></label>
                                <input type="text" name="contact_number" readonly value="<?php echo $phoneNumber; ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Full Address <span class="required-asterisk">*</span></label>
                                <input type="text" name="full_address" readonly value="<?php echo $fullAddress; ?>">
                            </div>
                        </div>

                        <div class="form-row two-col-row">
                            <div>
                                <label class="top-label">Years of Residency <span class="required-asterisk">*</span></label>
                                <input type="number" min="0" name="years_of_residency" required>
                            </div>
                            <div>
                                <label class="top-label">Months of Residency <span class="required-asterisk">*</span></label>
                                <input type="number" min="0" max="11" name="months_of_residency" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Purpose <span class="required-asterisk">*</span></label>
                                <textarea name="purpose" rows="5" required></textarea>
                            </div>
                        </div>

                        <h2 class="section-title text-center text-dark">Document Upload</h2>

                        <div class="form-row two-col-row">
                            <div>
                                <label class="top-label" for="residencyArrangement">Living Arrangement <span class="required-asterisk">*</span></label>
                                <select id="residencyArrangement" name="residency_arrangement" required>
                                    <option value="" selected>Select living arrangement</option>
                                    <option value="renting">Renting</option>
                                    <option value="relatives">Living with relatives</option>
                                </select>
                            </div>
                            <div>
                                <label class="top-label" for="residencyDocumentType">Supporting Document Type <span class="required-asterisk">*</span></label>
                                <select id="residencyDocumentType" name="supporting_document_type" required disabled></select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label" for="supportingFile">Upload: Supporting Document <span class="required-asterisk">*</span></label>
                                <label class="upload-dropzone" id="supportingDropzone" for="supportingFile">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                    <span>Drag files here or click to upload</span>
                                    <small>Accepted: PDF, JPG, JPEG, PNG</small>
                                </label>
                                <input type="file" id="supportingFile" name="supporting_file" class="visually-hidden" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div id="supportingSelectedFile" class="selected-files small text-muted mt-2"></div>
                            </div>
                        </div>

                        <div class="agreement-row">
                            <label class="agreement-text check-item" for="agreementResidency">
                                <input type="checkbox" id="agreementResidency" required>
                                I hereby certify that the above information is true and correct to the best of my knowledge and belief.
                            </label>
                            <button type="submit" class="submit-btn">SUBMIT</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../JS-Script-Files/Resident-End/Certificates/residencyFormScript.js"></script>
</body>
</html>
