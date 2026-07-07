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
require_once __DIR__ . "/../../PhpFiles/General/complaintTypeDetails.php";
require_once __DIR__ . "/../../PhpFiles/General/recaptcha.php";

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
$complainantEmail = htmlspecialchars((string)($useraccountstbl['email'] ?? ''), ENT_QUOTES, 'UTF-8');
$complainantContactNumberRaw = trim((string)($useraccountstbl['phone_number'] ?? ''));
if (preg_match('/^9\d{9}$/', $complainantContactNumberRaw)) {
    $complainantContactNumberRaw = '0' . $complainantContactNumberRaw;
}
$complainantContactNumber = htmlspecialchars($complainantContactNumberRaw, ENT_QUOTES, 'UTF-8');

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
$complaintRecaptchaEnabled = recaptcha_v3_frontend_enabled();
$complaintRecaptchaSiteKey = $complaintRecaptchaEnabled ? recaptcha_v3_site_key() : '';

$lastNameReadonly = $complainantLastName !== '' ? 'readonly' : '';
$firstNameReadonly = $complainantFirstName !== '' ? 'readonly' : '';
$middleNameReadonly = 'readonly';
$suffixReadonly = 'readonly';
$ageReadonly = $complainantAge !== '' ? 'readonly' : '';
$sexReadonly = $complainantSex !== '' ? 'readonly' : '';
$emailReadonly = 'readonly';
$contactReadonly = $complainantContactNumber !== '' ? 'readonly' : '';
$addressReadonly = $complainantAddress !== '' ? 'readonly' : '';
$complaintTypeConfigJson = htmlspecialchars(json_encode(complaintTypePublicConfig(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
$areaOptions = [
    'Area 01' => 'San Jose Proper',
    'Area 1A' => 'Litex Village, Abatex Christine Creek, Med. Heights',
    'Area 02' => 'VFW, Amychelle, Christine Villa Parnshey, Villa Ana, Zaniga Farm',
    'Area 03' => 'Relocation',
    'Area 04' => 'Kasiglahan Phase 1-B, Kasiglahan Phase 1-C, Kasiglahan Phase 1-D, Kasiglahan Phase 1-M, Kasiglahan Phase 1-A',
    'Area 05' => 'Kasiglahan Phase 1-K, Kasiglahan Phase 1K1, Kasiglahan Phase 1K2, Kasiglahan Phase 1-E, Kasiglahan Phase 1-G',
    'Area 06' => 'Sub-Urban, Metro Manila Hills',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="<?= htmlspecialchars((string)$baseUrl, ENT_QUOTES, 'UTF-8') ?>/Images/favicon_sanjose.png?v=20260211">
    <title>Complaint Form</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php if ($complaintRecaptchaEnabled): ?>
        <script src="https://www.google.com/recaptcha/api.js?render=<?= htmlspecialchars($complaintRecaptchaSiteKey, ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endif; ?>
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <link rel="stylesheet" href="../../CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/applicationForms.css?v=20260704-complaint-all-modals">
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
    </style>
</head>
<body>
    <div class="d-flex min-vh-100">
        <?php include __DIR__ . '/../includes/resident_sidebar.php'; ?>

        <header id="mobile-header">
            <div class="d-flex align-items-center px-3 py-2 shadow-sm bg-white">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn" id="btn-burger" type="button" aria-label="Open sidebar">
                        <i class="fa-solid fa-bars fa-lg"></i>
                    </button>
                    <img src="../../Images/San_Jose_LOGO.jpg" alt="Logo" style="width:32px;height:32px">
                    <span class="logo-name">Barangay San Jose</span>
                </div>
            </div>
        </header>

        <main id="div-mainDisplay" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 pb-md-5 pt-md-0">
            <div class="position-relative d-flex align-items-center justify-content-center mb-2 pt-4">
                <a href="<?= htmlspecialchars(appUrl('Resident-End/Complaints/ComplaintsLandingPage.php')) ?>" class="back-link d-inline-flex align-items-center text-decoration-none text-dark m-0 position-absolute start-0">
                    <i class="bi bi-arrow-left-short fs-3" aria-hidden="true"></i>
                </a>
                <h1 class="form-title m-0">Complaint Form</h1>
            </div>
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
                data-complaint-type-config="<?= $complaintTypeConfigJson ?>"
                data-recaptcha-enabled="<?= $complaintRecaptchaEnabled ? '1' : '0' ?>"
                data-recaptcha-site-key="<?= htmlspecialchars($complaintRecaptchaSiteKey, ENT_QUOTES, 'UTF-8') ?>"
                data-recaptcha-action="resident_complaint_submit"
                hidden
            ></div>

            <form class="page-form" id="complaintForm" method="POST" enctype="multipart/form-data" action="<?= htmlspecialchars($baseUrl) ?>/PhpFiles/Resident-End/submitComplaints.php">
                <?= csrfTokenField() ?>
                <input type="hidden" name="action" value="submit_complaint">
                <input type="hidden" name="recaptcha_token" id="complaintRecaptchaToken" value="">
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

                    <div class="form-row two-col-row">
                        <div>
                            <label class="top-label">Age <span class="required-asterisk">*</span></label>
                            <input type="number" name="complainant_age" min="1" required value="<?php echo $complainantAge; ?>" <?php echo $ageReadonly; ?>>
                        </div>
                        <div>
                            <label class="top-label">Sex <span class="required-asterisk">*</span></label>
                            <input type="text" name="complainant_sex" required value="<?php echo $complainantSex; ?>" <?php echo $sexReadonly; ?>>
                        </div>
                    </div>

                    <div class="form-row two-col-row">
                        <div>
                            <label class="top-label">Email Address</label>
                            <input type="email" name="complainant_email" value="<?php echo $complainantEmail; ?>" placeholder="No email on file" <?php echo $emailReadonly; ?>>
                        </div>
                        <div>
                            <label class="top-label">Contact Number <span class="required-asterisk">*</span></label>
                            <input type="text" name="complainant_contact_number" inputmode="numeric" maxlength="11" pattern="^09\d{9}$" title="Format: 09XXXXXXXXX" placeholder="09XXXXXXXXX" required value="<?php echo $complainantContactNumber; ?>" <?php echo $contactReadonly; ?>>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="full-width">
                            <label class="top-label">Address <span class="required-asterisk">*</span></label>
                            <input type="text" name="complainant_address" required value="<?php echo $complainantAddress; ?>" <?php echo $addressReadonly; ?>>
                        </div>
                    </div>

                    <h2 class="section-title text-center text-dark">Complaint Information</h2>

                    <div class="form-row two-col-row">
                        <div>
                            <label class="top-label">Date of the Incident <span class="required-asterisk">*</span></label>
                            <input type="date" id="incidentDate" name="incident_date" class="complaint-picker-proxy" data-date-modal-style="calendar" required>
                            <div id="incidentDateError" class="text-danger small mt-1 d-none" aria-live="polite"></div>
                        </div>
                        <div>
                            <label class="top-label">Time of the Incident <i>(recommended)</i></label>
                            <input type="time" id="incidentTime" name="incident_time" class="d-none">
                            <input type="text" id="incidentTimeProxy" class="form-control complaint-picker-proxy" placeholder="Select time" readonly>
                        </div>
                    </div>

                    <div class="form-row two-col-row">
                        <div>
                            <label class="top-label">Location of the Incident <span class="required-asterisk">*</span></label>
                            <input type="text" name="incident_location" required>
                        </div>
                        <div>
                            <label class="top-label" for="incidentAreaNumberDisplay">Area Number <span class="required-asterisk">*</span></label>
                            <input type="hidden" name="incident_area_number" id="incidentAreaNumber">
                            <input type="text" class="form-control area-picker-input complaint-picker-proxy" id="incidentAreaNumberDisplay" placeholder="Select area" readonly required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="full-width">
                            <label class="top-label">Nature of Complaint <span class="required-asterisk">*</span></label>
                            <select id="natureOfComplaint" name="nature_of_complaint" required>
                                <option value="">Select</option>
                                <option value="Disturbance">Disturbance</option>
                                <option value="Property Dispute">Property Dispute</option>
                                <option value="Noise Complaint">Noise Complaint</option>
                                <option value="Physical Altercation / Violence">Physical Altercation / Violence</option>
                                <option value="Barangay Safety Hazard">Barangay Safety Hazard</option>
                                <option value="General Concern">General Concern</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row d-none" id="natureOtherWrap">
                        <div class="full-width">
                            <label class="top-label">If Other, please specify <span id="natureOtherAsterisk" class="required-asterisk d-none">*</span></label>
                            <input type="text" id="natureOther" name="nature_other">
                        </div>
                    </div>

                    <div id="complaintTypeDynamicFields" class="d-none"></div>

                    <div class="form-row">
                        <div class="full-width">
                            <label class="top-label">Short narration of the incident <span class="required-asterisk">*</span></label>
                            <textarea name="incident_narration" rows="6" required></textarea>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="full-width d-flex justify-content-start">
                            <button type="button" class="btn complaint-action-btn" id="addComplaintAttachmentBtn">
                                <i class="fa-regular fa-image" aria-hidden="true"></i>
                                <span>Add Image</span>
                            </button>
                        </div>
                    </div>

                    <div id="complaintAttachmentSection" class="d-none">
                        <div id="complaintAttachmentRows">
                            <div class="form-row d-none" data-complaint-attachment-row="1">
                                <div class="full-width">
                                    <label class="top-label" for="complaintImage1">Attachment 1</label>
                                    <div class="complaint-upload-wrap complaint-upload-wrap--with-close">
                                        <button type="button" class="attachment-close-btn attachment-row-close-btn" data-attachment-remove-btn aria-label="Remove attachment 1">X</button>
                                        <label class="upload-dropzone" data-upload-input="complaintImage1" for="complaintImage1">
                                            <i class="fa-solid fa-upload"></i>
                                            <div id="complaintImagePrompt1">Drag and drop image or click to upload</div>
                                            <small id="complaintImage1Meta">JPG, JPEG, PNG, or WEBP. Optional.</small>
                                            <input type="file" class="form-control upload-dropzone-input" id="complaintImage1" name="complaint_images[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" disabled>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row d-none" data-complaint-attachment-row="2">
                                <div class="full-width">
                                    <label class="top-label" for="complaintImage2">Attachment 2</label>
                                    <div class="complaint-upload-wrap complaint-upload-wrap--with-close">
                                        <button type="button" class="attachment-close-btn attachment-row-close-btn" data-attachment-remove-btn aria-label="Remove attachment 2">X</button>
                                        <label class="upload-dropzone" data-upload-input="complaintImage2" for="complaintImage2">
                                            <i class="fa-solid fa-upload"></i>
                                            <div id="complaintImagePrompt2">Drag and drop additional image or click to upload</div>
                                            <small id="complaintImage2Meta">JPG, JPEG, PNG, or WEBP. Optional.</small>
                                            <input type="file" class="form-control upload-dropzone-input" id="complaintImage2" name="complaint_images[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" disabled>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row d-none" data-complaint-attachment-row="3">
                                <div class="full-width">
                                    <label class="top-label" for="complaintImage3">Attachment 3</label>
                                    <div class="complaint-upload-wrap complaint-upload-wrap--with-close">
                                        <button type="button" class="attachment-close-btn attachment-row-close-btn" data-attachment-remove-btn aria-label="Remove attachment 3">X</button>
                                        <label class="upload-dropzone" data-upload-input="complaintImage3" for="complaintImage3">
                                            <i class="fa-solid fa-upload"></i>
                                            <div id="complaintImagePrompt3">Drag and drop additional image or click to upload</div>
                                            <small id="complaintImage3Meta">JPG, JPEG, PNG, or WEBP. Optional.</small>
                                            <input type="file" class="form-control upload-dropzone-input" id="complaintImage3" name="complaint_images[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" disabled>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h2 class="section-title text-center text-dark">Witness Information</h2>

                    <div class="form-row">
                        <div class="full-width">
                            <label class="top-label">Do you have a witness? <span class="required-asterisk">*</span></label>
                            <select class="form-select" id="hasWitnesses" name="has_witnesses" required>
                                <option value="">Select</option>
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                        </div>
                    </div>

                    <div id="witnessRowsWrap" class="d-none">
                        <div class="id-guidance-card mb-3">
                            <div class="id-guidance-card__title">Witness Guide</div>
                            <div class="id-guidance-card__meta">You can add up to 3 witnesses. Last name, first name, and contact number are required for each witness you add. Full address is optional.</div>
                        </div>

                        <?php for ($witnessIndex = 1; $witnessIndex <= 3; $witnessIndex++): ?>
                            <div class="witness-entry<?= $witnessIndex === 1 ? '' : ' d-none' ?>" data-witness-row="<?= $witnessIndex ?>">
                                <div class="witness-entry-card complaint-upload-wrap complaint-upload-wrap--with-close">
                                    <button type="button" class="attachment-close-btn witness-remove-btn" data-witness-remove-btn aria-label="Remove witness <?= $witnessIndex ?>">X</button>
                                    <div class="form-row">
                                        <div>
                                            <label class="top-label">Last Name <span class="required-asterisk">*</span></label>
                                            <input type="text" name="witness_last_name[]">
                                        </div>
                                        <div>
                                            <label class="top-label">First Name <span class="required-asterisk">*</span></label>
                                            <input type="text" name="witness_first_name[]">
                                        </div>
                                        <div>
                                            <label class="top-label">Middle Initial</label>
                                            <input type="text" name="witness_middle_name[]" maxlength="10">
                                        </div>
                                        <div>
                                            <label class="top-label">Suffix</label>
                                            <select class="form-select" name="witness_suffix[]">
                                                <option value="">None</option>
                                                <option value="Jr.">Jr.</option>
                                                <option value="Sr.">Sr.</option>
                                                <option value="III">III</option>
                                                <option value="IV">IV</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-row two-col-row pt-0">
                                        <div>
                                            <label class="top-label">Contact Number <span class="required-asterisk">*</span></label>
                                            <input type="text" name="witness_contact_number[]" inputmode="numeric" maxlength="11" pattern="^09\d{9}$" title="Format: 09XXXXXXXXX" placeholder="09XXXXXXXXX">
                                        </div>
                                        <div>
                                            <label class="top-label">Full Address</label>
                                            <input type="text" name="witness_address[]">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>

                        <div class="form-row">
                            <div class="full-width d-flex justify-content-start">
                                <button type="button" class="btn btn-outline-secondary witness-add-btn" id="addWitnessBtn">Add Another Witness</button>
                            </div>
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
        </main>
    </div>
    <div class="modal fade complaint-form-modal" id="complaintTimeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <div class="complaint-form-modal__heading">Select Time</div>
                        <div class="complaint-form-modal__subheading">Choose the incident time or use the current time.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body complaint-form-modal__body">
                    <div class="form-row">
                        <div class="full-width">
                            <label class="top-label" for="incidentTimePicker">Time of the Incident</label>
                            <input type="time" class="form-control" id="incidentTimePicker">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <button type="button" class="btn complaint-form-modal__secondary-btn" id="incidentTimeUseNow">Use current time</button>
                        <div class="small text-muted text-end" id="incidentTimePreview">No time selected yet.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn complaint-form-modal__secondary-btn" id="incidentTimeClearBtn">Clear</button>
                    <button type="button" class="btn complaint-form-modal__primary-btn" id="incidentTimeApplyBtn">Apply</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade complaint-form-modal" id="complaintAreaHelpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable complaint-area-modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title complaint-form-modal__heading">Barangay Area Guide</h5>
                    <button type="button" class="complaint-area-close-btn" data-bs-dismiss="modal" aria-label="Close">×</button>
                </div>
                <div class="modal-body complaint-form-modal__body">
                    <p class="mb-3">Choose the barangay area where the incident happened. If the incident is near a boundary, select the nearest known area.</p>
                    <div class="d-flex flex-column gap-2 complaint-area-options">
                        <?php foreach ($areaOptions as $areaOption => $areaLocation): ?>
                            <button
                                type="button"
                                class="area-guide-option"
                                data-area-value="<?= htmlspecialchars($areaOption, ENT_QUOTES, 'UTF-8') ?>"
                                data-area-label="<?= htmlspecialchars($areaOption . ' - ' . $areaLocation, ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <span class="area-guide-option__title"><?= htmlspecialchars($areaOption, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="area-guide-option__meta"><?= htmlspecialchars($areaLocation, ENT_QUOTES, 'UTF-8') ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const burgerBtn = document.getElementById("btn-burger");
    const sidebar = document.getElementById("div-sidebarWrapper");

    if (burgerBtn && sidebar) {
        burgerBtn.addEventListener("click", () => {
            sidebar.classList.toggle("show");
        });
    }
</script>
<script src="../../JS-Script-Files/modalHandler.js"></script>
<script src="../../JS-Script-Files/Resident-End/dateFieldModal.js?v=20260707-date-proxy-white"></script>
<script src="../../JS-Script-Files/Resident-End/complaintScript.js"></script>
</body>
</html>
