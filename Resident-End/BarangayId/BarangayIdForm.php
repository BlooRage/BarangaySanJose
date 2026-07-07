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
require_once __DIR__ . "/../../PhpFiles/General/documentRequestWorkflow.php";

$userId = (string)($_SESSION['user_id'] ?? '');
$data = getResidentProfileData($conn, $userId);
$residentinformationtbl = $data['residentinformationtbl'] ?? [];
$residentaddresstbl = $data['residentaddresstbl'] ?? [];
$useraccountstbl = $data['useraccountstbl'] ?? [];

$firstName = htmlspecialchars((string)($residentinformationtbl['firstname'] ?? ''), ENT_QUOTES, 'UTF-8');
$lastName = htmlspecialchars((string)($residentinformationtbl['lastname'] ?? ''), ENT_QUOTES, 'UTF-8');
$middleName = htmlspecialchars((string)($residentinformationtbl['middlename'] ?? ''), ENT_QUOTES, 'UTF-8');
$suffix = (string)($residentinformationtbl['suffix'] ?? '');

$birthdateValue = '';
$birthdateDisplay = (string)($residentinformationtbl['birthdate'] ?? '');
if ($birthdateDisplay !== '') {
    $dt = DateTime::createFromFormat('F j, Y', $birthdateDisplay);
    if (!$dt) {
        try {
            $dt = new DateTime($birthdateDisplay);
        } catch (Exception $e) {
            $dt = null;
        }
    }
    if ($dt instanceof DateTime) {
        $birthdateValue = $dt->format('Y-m-d');
    }
}
$birthplace = htmlspecialchars((string)($residentinformationtbl['birthplace'] ?? ''), ENT_QUOTES, 'UTF-8');

$phoneNumber = htmlspecialchars((string)($useraccountstbl['phone_number'] ?? ''), ENT_QUOTES, 'UTF-8');
$emergencyLast = htmlspecialchars((string)($residentinformationtbl['emergency_last_name'] ?? ''), ENT_QUOTES, 'UTF-8');
$emergencyFirst = htmlspecialchars((string)($residentinformationtbl['emergency_first_name'] ?? ''), ENT_QUOTES, 'UTF-8');
$emergencyMiddle = htmlspecialchars((string)($residentinformationtbl['emergency_middle_name'] ?? ''), ENT_QUOTES, 'UTF-8');
$emergencySuffix = (string)($residentinformationtbl['emergency_suffix'] ?? '');
$emergencyContact = htmlspecialchars((string)($residentinformationtbl['emergency_contact'] ?? ''), ENT_QUOTES, 'UTF-8');

$unitNumber = trim((string)($residentaddresstbl['unit_number'] ?? ''));
$houseNumber = trim((string)($residentaddresstbl['street_number'] ?? ''));
$streetName = trim((string)($residentaddresstbl['street_name'] ?? ''));
$subdivision = trim((string)($residentaddresstbl['subdivision'] ?? ''));
$areaNumber = trim((string)($residentaddresstbl['area_number'] ?? ''));

$fullAddress = implode(', ', array_filter([
    $unitNumber !== '' ? 'Unit ' . $unitNumber : '',
    trim($houseNumber . ' ' . $streetName),
    $subdivision !== '' ? $subdivision . ' Subdivision' : '',
    $areaNumber !== '' ? 'Area ' . $areaNumber : '',
    'San Jose',
    'Rodriguez',
    'Rizal'
], fn($part) => trim((string)$part) !== ''));

$residentId = trim((string)($residentinformationtbl['resident_id'] ?? ''));
$barangayIdState = dr_resident_barangay_id_state($conn, $userId, $residentId);
if (!($barangayIdState['can_submit_new_request'] ?? false)) {
    header('Location: ' . appUrl('/Resident-End/BarangayId/BarangayIdLandingPage.php?notice=request_not_allowed'));
    exit;
}

$resolvedMode = dr_normalize_barangay_id_request_mode((string)($barangayIdState['submission_mode'] ?? 'new'));
$requestedMode = dr_normalize_barangay_id_request_mode((string)($_GET['mode'] ?? $resolvedMode));
if ($requestedMode !== $resolvedMode) {
    header('Location: ' . appUrl('/Resident-End/BarangayId/BarangayIdLandingPage.php?notice=request_not_allowed'));
    exit;
}

$sourceRequestId = $resolvedMode === 'new'
    ? ''
    : trim((string)($barangayIdState['latest_completed_request_id'] ?? ''));
$validUntilDisplay = '';
$validUntilDt = dr_parse_datetime_value((string)($barangayIdState['latest_completed_valid_until'] ?? ''), true);
if ($validUntilDt instanceof DateTimeImmutable) {
    $validUntilDisplay = $validUntilDt->format('F j, Y');
}

$formContext = [
    'new' => [
        'title' => 'New Barangay ID Application',
        'subtitle' => 'Complete the resident details below to submit your first Barangay ID request.',
        'purpose' => 'Barangay ID Application',
        'submit_label' => 'SUBMIT APPLICATION',
        'badge' => 'New Application',
        'tone' => 'new',
    ],
    'renewal' => [
        'title' => 'Renew Barangay ID',
        'subtitle' => $validUntilDisplay !== ''
            ? 'Your current Barangay ID is now within the renewal window. Once approved, the renewed ID will receive a fresh 2-year validity from its new issue date.'
            : 'Your current Barangay ID is now eligible for renewal. Once approved, the renewed ID will receive a fresh 2-year validity from its new issue date.',
        'purpose' => 'Barangay ID Renewal',
        'submit_label' => 'SUBMIT RENEWAL',
        'badge' => 'Renewal',
        'tone' => 'renewal',
    ],
    'replacement_lost' => [
        'title' => 'Replacement for Lost Barangay ID',
        'subtitle' => 'Your previous Barangay ID is marked as lost. Submit this replacement request to receive a new Barangay ID with a fresh 2-year validity once released.',
        'purpose' => 'Barangay ID Replacement (Lost)',
        'submit_label' => 'SUBMIT REPLACEMENT',
        'badge' => 'Lost Replacement',
        'tone' => 'lost',
    ],
];
$activeFormContext = $formContext[$resolvedMode] ?? $formContext['new'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Images/favicon_sanjose.png?v=20260211">
    <title>Barangay ID Application</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/residentDashboard.css">
<link rel="stylesheet" href="../../CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/applicationForms.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/barangayIdNav.css">
    <style>
        .request-context-card {
            display: grid;
            gap: 10px;
            padding: 18px 20px;
            margin-bottom: 20px;
            border-radius: 22px;
            border: 1px solid #f1d3b4;
            background: linear-gradient(135deg, #fffaf3 0%, #fff2e1 100%);
            box-shadow: 0 16px 32px rgba(138, 75, 0, 0.08);
        }
        .request-context-card__badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            padding: 8px 12px;
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid #f1d3b4;
            color: #9a5603;
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .request-context-card h2 {
            margin: 0;
            color: #432815;
            font-size: 1.2rem;
            line-height: 1.2;
        }
        .request-context-card p {
            margin: 0;
            color: #6d6257;
            line-height: 1.7;
        }
    </style>
</head>

<body>

    <div class="d-flex min-vh-100">

        <?php include __DIR__ . '/../includes/resident_sidebar.php'; ?>

        <main id="div-mainDisplay" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 pb-md-5 pt-md-0 bg-light">

            <div class="main-head application-card orange-card application-card--muted py-3 my-5 rounded">
                <div class="main-head-content">

                    <a href="<?= htmlspecialchars(appUrl('Resident-End/BarangayId/BarangayIdLandingPage.php')) ?>" class="back-link">&lt; Go Back</a>

                    <h1 class="form-title"><?= htmlspecialchars($activeFormContext['title'], ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

                    <div class="request-context-card">
                        <span class="request-context-card__badge">
                            <i class="fa-solid fa-id-card"></i>
                            <?= htmlspecialchars($activeFormContext['badge'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <h2><?= htmlspecialchars($activeFormContext['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <p><?= htmlspecialchars($activeFormContext['subtitle'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>

                    <form method="POST" action="<?= htmlspecialchars($baseUrl) ?>/PhpFiles/Resident-End/documentRequestWorkflow.php">
                        <input type="hidden" name="action" value="submit_request">
                        <input type="hidden" name="document_type" value="Barangay ID">
                        <input type="hidden" name="purpose" value="<?= htmlspecialchars($activeFormContext['purpose'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="request_purpose" value="<?= htmlspecialchars($activeFormContext['purpose'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="barangay_id_request_mode" value="<?= htmlspecialchars($resolvedMode, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="barangay_id_source_request_id" value="<?= htmlspecialchars($sourceRequestId, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="redirect" value="1">

                        <!-- PERSONAL INFORMATION -->
                        <h2 class="section-title text-center text-dark">Personal Information</h2>


                        <div class="form-row">
                            <div>
                                <label class="top-label">Last Name <span class="required-asterisk">*</span></label>
                                <input type="text" name="last_name" required readonly value="<?php echo $lastName; ?>">
                            </div>

                            <div>
                                <label class="top-label">First Name <span class="required-asterisk">*</span></label>
                                <input type="text" name="first_name" required readonly value="<?php echo $firstName; ?>">
                            </div>

                            <div>
                                <label class="top-label">Middle Name</label>
                                <input type="text" name="middle_name" readonly value="<?php echo $middleName; ?>">
                            </div>

                            <div>
                                <label class="top-label">Suffix</label>
                                <select name="suffix" disabled>
                                    <option value="" <?php echo ($suffix === '') ? 'selected' : ''; ?>>None</option>
                                    <option value="Jr." <?php echo ($suffix === 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                                    <option value="Sr." <?php echo ($suffix === 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                                    <option value="III" <?php echo ($suffix === 'III') ? 'selected' : ''; ?>>III</option>
                                    <option value="IV" <?php echo ($suffix === 'IV') ? 'selected' : ''; ?>>IV</option>
                                    <option value="V">Others</option>
                                </select>
                                <input type="hidden" name="suffix_name" value="<?php echo htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div>
                                <label class="top-label">Date of Birth <span class="required-asterisk">*</span></label>
                                <input type="date" name="birthdate" required max="<?= date('Y-m-d') ?>" data-date-modal-style="calendar" <?php echo $birthdateValue !== '' ? 'readonly' : ''; ?> value="<?php echo htmlspecialchars($birthdateValue, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>

                            <div>
                                <label class="top-label">Birthplace <span class="required-asterisk">*</span></label>
                                <input type="text" name="birthplace" required value="<?php echo $birthplace; ?>" <?php echo $birthplace !== '' ? 'readonly' : ''; ?>>
                            </div>
                            <div class="phone">
                                <label class="top-label">Contact Number <span class="required-asterisk">*</span></label>
                                <input type="tel" name="contact_number" required <?php echo $phoneNumber !== '' ? 'readonly' : ''; ?> value="<?php echo $phoneNumber; ?>">
                            </div>



                        </div>

                        <div class="form-row">
                            <div class="full-width">
                            <label class="top-label">Address <span class="required-asterisk">*</span></label>
                                <input type="text" name="full_address_display" <?php echo $fullAddress !== '' ? 'readonly' : ''; ?> value="<?php echo htmlspecialchars($fullAddress, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="full_address" value="<?php echo htmlspecialchars($fullAddress, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="unitNumber" value="<?php echo htmlspecialchars($unitNumber, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="houseNumber" value="<?php echo htmlspecialchars($houseNumber, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="streetName" value="<?php echo htmlspecialchars($streetName, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                        <br>
                        <!-- EMERGENCY CONTACT -->
                        <h2 class="section-title text-center pb-2 text-dark">
                            Contact Person in case of Emergency (Family Member)
                        </h2>


                        <div class="form-row">
                            <div>
                                <label class="top-label">Last Name <span class="required-asterisk">*</span> </label>
                                <input type="text" name="emergency_last" required value="<?php echo $emergencyLast; ?>">
                            </div>

                            <div>
                                <label class="top-label">First Name <span class="required-asterisk">*</span> </label>
                                <input type="text" name="emergency_first" required value="<?php echo $emergencyFirst; ?>">
                            </div>

                            <div>
                                <label class="top-label">Middle Name</label>
                                <input type="text" name="emergency_middle" value="<?php echo $emergencyMiddle; ?>">
                            </div>

                            <div>
                                <!-- suffix is select drop down-->
                                <label class="top-label">Suffix</label>
                                <select name="emergency_suffix">
                                    <option value="" <?php echo ($emergencySuffix === '') ? 'selected' : ''; ?>>None</option>
                                    <option value="Jr." <?php echo ($emergencySuffix === 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                                    <option value="Sr." <?php echo ($emergencySuffix === 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                                    <option value="III" <?php echo ($emergencySuffix === 'III') ? 'selected' : ''; ?>>III</option>
                                    <option value="IV" <?php echo ($emergencySuffix === 'IV') ? 'selected' : ''; ?>>IV</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Contact Number <span class="required-asterisk">*</span> </label>
                                <input type="tel" name="emergency_contact" required value="<?php echo $emergencyContact; ?>">
                            </div>
                        </div>

                        <!-- CERTIFICATION -->
                        <div class="agreement-row">
                            <label class="agreement-text" for="barangayIdAgreement">
                                <input type="checkbox" id="barangayIdAgreement" required>
                                I hereby certify that the above information is true and correct to the best of my knowledge and belief.
                            </label>

                            <button type="submit" class="submit-btn"><?= htmlspecialchars($activeFormContext['submit_label'], ENT_QUOTES, 'UTF-8') ?></button>
                        </div>

                    </form>
                </div>
            </div>
        </main>

    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../JS-Script-Files/Resident-End/dateFieldModal.js?v=20260707-date-proxy-white"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const form = document.querySelector("form");
            const submitBtn = form?.querySelector(".submit-btn");
            const fullAddressDisplay = form?.querySelector('input[name="full_address_display"]');
            const fullAddressHidden = form?.querySelector('input[name="full_address"]');
            if (!form || !submitBtn) return;

            const updateState = () => {
                if (fullAddressDisplay && fullAddressHidden) {
                    fullAddressHidden.value = fullAddressDisplay.value.trim();
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
