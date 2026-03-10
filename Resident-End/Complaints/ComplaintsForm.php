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

$allowUnregistered = false;
require_once __DIR__ . "/../includes/resident_access_guard.php";
require_once __DIR__ . "/../../PhpFiles/GET/getResidentProfile.php";

$userId = (string)($_SESSION['user_id'] ?? '');
$data = getResidentProfileData($conn, $userId);
$residentinformationtbl = $data['residentinformationtbl'] ?? [];
$residentaddresstbl = $data['residentaddresstbl'] ?? [];
$useraccountstbl = $data['useraccountstbl'] ?? [];

$complainantLastName = htmlspecialchars((string)($residentinformationtbl['lastname'] ?? ''), ENT_QUOTES, 'UTF-8');
$complainantFirstName = htmlspecialchars((string)($residentinformationtbl['firstname'] ?? ''), ENT_QUOTES, 'UTF-8');
$complainantMiddleName = htmlspecialchars((string)($residentinformationtbl['middlename'] ?? ''), ENT_QUOTES, 'UTF-8');
$complainantSuffix = htmlspecialchars((string)($residentinformationtbl['suffix'] ?? ''), ENT_QUOTES, 'UTF-8');
$complainantAge = htmlspecialchars((string)($residentinformationtbl['age'] ?? ''), ENT_QUOTES, 'UTF-8');
$complainantSex = htmlspecialchars((string)($residentinformationtbl['sex'] ?? ''), ENT_QUOTES, 'UTF-8');
$complainantContactNumber = htmlspecialchars((string)($useraccountstbl['phone_number'] ?? ''), ENT_QUOTES, 'UTF-8');

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
    $lotNumber = trim((string)preg_replace('/^lot\s*/i', '', $streetNumber));
    $blockNumber = trim((string)preg_replace('/^(block|blk)\s*/i', '', $streetName));
    $phaseValue = trim((string)preg_replace('/^phase\s*/i', '', $phaseNumber));

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

$complainantAddress = htmlspecialchars(implode(', ', $fullAddressParts), ENT_QUOTES, 'UTF-8');

$lastNameReadonly = $complainantLastName !== '' ? 'readonly' : '';
$firstNameReadonly = $complainantFirstName !== '' ? 'readonly' : '';
$middleNameReadonly = $complainantMiddleName !== '' ? 'readonly' : '';
$suffixReadonly = 'readonly';
$ageReadonly = $complainantAge !== '' ? 'readonly' : '';
$sexReadonly = $complainantSex !== '' ? 'readonly' : '';
$contactReadonly = $complainantContactNumber !== '' ? 'readonly' : '';
$addressReadonly = $complainantAddress !== '' ? 'readonly' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Complaint Form</title>
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
        <div class="main-head application-card orange-card application-card--muted py-3 rounded">
            <div class="main-head-content">
                <a href="<?= htmlspecialchars($baseUrl) ?>/Resident-End/resident_dashboard.php" class="back-link">&lt; Go Back</a>

                <h1 class="form-title">Complaint Form</h1>
                <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

                <?php
                $feedbackType = !empty($_GET['success']) ? 'success' : (!empty($_GET['error']) ? 'error' : '');
                $feedbackMessage = !empty($_GET['success'])
                    ? (string)$_GET['success']
                    : (!empty($_GET['error']) ? (string)$_GET['error'] : '');
                ?>
                <div
                    id="complaintFeedbackData"
                    data-feedback-type="<?= htmlspecialchars($feedbackType, ENT_QUOTES, 'UTF-8') ?>"
                    data-feedback-message="<?= htmlspecialchars($feedbackMessage, ENT_QUOTES, 'UTF-8') ?>"
                    hidden
                ></div>

                <form method="POST" action="<?= htmlspecialchars($baseUrl) ?>/PhpFiles/Resident-End/submitComplaints.php">
                    <?= csrfTokenField() ?>
                    <input type="hidden" name="action" value="submit_complaint">
                    <h2 class="section-title text-center text-dark">Complainant's Information</h2>

                    <div class="form-row">
                        <div>
                            <label class="top-label">Last Name <span class="required-asterisk">*</span></label>
                            <input type="text" name="complainant_last_name" required value="<?php echo $complainantLastName; ?>" <?php echo $lastNameReadonly; ?>>
                        </div>
                        <div>
                            <label class="top-label">First Name <span class="required-asterisk">*</span></label>
                            <input type="text" name="complainant_first_name" required value="<?php echo $complainantFirstName; ?>" <?php echo $firstNameReadonly; ?>>
                        </div>
                        <div>
                            <label class="top-label">Middle Name</label>
                            <input type="text" name="complainant_middle_name" value="<?php echo $complainantMiddleName; ?>" <?php echo $middleNameReadonly; ?>>
                        </div>
                        <div>
                            <label class="top-label">Suffix</label>
                            <input type="text" name="complainant_suffix" value="<?php echo $complainantSuffix; ?>" placeholder="None" <?php echo $suffixReadonly; ?>>
                        </div>
                    </div>

                    <div class="form-row">
                        <div>
                            <label class="top-label">Age <span class="required-asterisk">*</span></label>
                            <input type="number" name="complainant_age" min="1" required value="<?php echo $complainantAge; ?>" <?php echo $ageReadonly; ?>>
                        </div>
                        <div>
                            <label class="top-label">Sex <span class="required-asterisk">*</span></label>
                            <input type="text" name="complainant_sex" required value="<?php echo $complainantSex; ?>" <?php echo $sexReadonly; ?>>
                        </div>
                        <div class="phone">
                            <label class="top-label">Contact Number <span class="required-asterisk">*</span></label>
                            <input type="text" name="complainant_contact_number" required value="<?php echo $complainantContactNumber; ?>" <?php echo $contactReadonly; ?>>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="full-width">
                            <label class="top-label">Address <span class="required-asterisk">*</span></label>
                            <input type="text" name="complainant_address" required value="<?php echo $complainantAddress; ?>" <?php echo $addressReadonly; ?>>
                        </div>
                    </div>

                    <h2 class="section-title text-center text-dark">Person, Establishment, or Matter Being Reported</h2>
                    <p class="form-subtitle text-center mb-3">
                        This may refer to a resident, a business, a group, an unknown person, or a general concern.
                    </p>

                    <div class="form-row two-col-row">
                        <div>
                            <label class="top-label">Name of Person / Business / Organization / Description <span class="required-asterisk">*</span></label>
                            <input
                                type="text"
                                name="subject_name"
                                placeholder="e.g. Dela Cruz, Juan Miguel / ABC Store / Unknown individuals"
                                title="Enter the name of the person, business, organization, or a short description."
                                required
                            >
                        </div>
                        <div>
                            <label class="top-label">Contact Number</label>
                            <input type="text" name="subject_contact_number">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="full-width">
                            <label class="top-label">Known Address / Location / Area Involved <span class="required-asterisk">*</span></label>
                            <input
                                type="text"
                                name="subject_address"
                                placeholder="Enter a known address, location, area involved, or N/A if not known"
                                required
                            >
                        </div>
                    </div>

                    <h2 class="section-title text-center text-dark">Complaint Information</h2>

                    <div class="form-row two-col-row">
                        <div>
                            <label class="top-label">Nature of Complaint <span class="required-asterisk">*</span></label>
                            <select name="nature_of_complaint" required>
                                <option value="">Select</option>
                                <option value="Disturbance">Disturbance</option>
                                <option value="Property Dispute">Property Dispute</option>
                                <option value="Noise Complaint">Noise Complaint</option>
                                <option value="Physical Altercation">Physical Altercation</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="top-label">If Other, please specify</label>
                            <input type="text" name="nature_other">
                        </div>
                    </div>

                    <div class="form-row two-col-row">
                        <div class="">
                            <label class="top-label">Date of the Incident <span class="required-asterisk">*</span></label>
                            <input type="date" id="incidentDate" name="incident_date" required>
                            <div id="incidentDateError" class="text-danger small mt-1 d-none" aria-live="polite"></div>
                        </div>
                        <div>
                            <label class="top-label">Time of the Incident <i>
                                (recommended)
                            </i></label>
                            <input type="time" name="incident_time">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="full-width">
                            <label class="top-label">Location of the Incident <span class="required-asterisk">*</span></label>
                            <input type="text" name="incident_location" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="full-width">
                            <label class="top-label">Short narration of the incident <span class="required-asterisk">*</span></label>
                            <textarea name="incident_narration" rows="6" required></textarea>
                        </div>
                    </div>

                    <h2 class="section-title text-center text-dark">Witness Information</h2>

                    <div class="form-row two-col-row">
                        <div>
                            <label class="top-label">Name of Witness (Last Name, First Name Middle Name)</label>
                            <input type="text" name="witness_name" placeholder="Dela Cruz, Juan Miguel" pattern="^[A-Za-z][A-Za-z'\\s-]*,\\s*[A-Za-z][A-Za-z'\\s-]*\\s+[A-Za-z][A-Za-z'\\s-]*$" title="Use format: Last Name, First Name Middle Name">
                        </div>
                        <div>
                            <label class="top-label">Witness Contact Number</label>
                            <input type="text" name="witness_contact_number">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="full-width">
                            <label class="top-label">Witness Address</label>
                            <input type="text" name="witness_address">
                        </div>
                    </div>

                    <div class="agreement-row">
                        <label class="agreement-text check-item" for="agreementComplaint">
                            <input type="checkbox" id="agreementComplaint" name="certify" required>
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
<script src="../../JS-Script-Files/modalHandler.js"></script>
<script src="../../JS-Script-Files/Resident-End/dateFieldModal.js"></script>
<script src="../../JS-Script-Files/Resident-End/complaintScript.js"></script>
</body>
</html>
