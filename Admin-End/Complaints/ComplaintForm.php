<?php
if (!isset($baseUrl)) {
    $scriptName = str_replace("\\", "/", (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $adminSegmentPos = strpos($scriptName, '/Admin-End/');
    $baseUrl = '';
    if ($adminSegmentPos !== false) {
        $baseUrl = substr($scriptName, 0, $adminSegmentPos);
    } else {
        $baseUrl = dirname($scriptName);
    }
    $baseUrl = rtrim((string)$baseUrl, '/');
    if ($baseUrl === '.' || $baseUrl === '/') {
        $baseUrl = '';
    }
}

require_once __DIR__ . "/../../PhpFiles/General/connection.php";
require_once __DIR__ . "/../includes/admin_guard.php";

$feedbackType = !empty($_GET['success']) ? 'success' : (!empty($_GET['error']) ? 'error' : '');
$feedbackMessage = !empty($_GET['success'])
    ? (string)$_GET['success']
    : (!empty($_GET['error']) ? (string)$_GET['error'] : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../../Images/favicon_sanjose.png?v=20260211">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
    <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260227-2">
    <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/BlotterMangementStyle.css?v=20260305-1">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/applicationForms.css">
    <style>
        body {
            background: #fffdfb;
        }
        #main-display {
            background: #ffffff !important;
        }
        #main-display .form-title,
        #main-display .form-subtitle,
        #main-display .back-link {
            max-width: 1300px;
            margin-left: auto;
            margin-right: auto;
        }
        #main-display .page-form {
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
<div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main id="main-display" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 pb-md-5 pt-md-0">
        <div class="position-relative d-flex align-items-center justify-content-center mb-2 pt-4">
            <a href="<?= htmlspecialchars(appUrl('Admin-End/Complaints/ComplaintTracker.php')) ?>" class="back-link d-inline-flex align-items-center text-decoration-none text-dark m-0 position-absolute start-0">
                <i class="bi bi-arrow-left-short fs-3"></i>
            </a>
            <h1 class="form-title m-0">Complaint Form</h1>
        </div>
        <p class="form-subtitle mb-2 text-center">Use this form to encode a complaint on behalf of a resident or walk-in complainant.</p>
        <p class="form-subtitle mb-4 text-center">All fields marked with <span class="required-asterisk">*</span> are required.</p>

        <div
            id="complaintFeedbackData"
            data-feedback-type="<?= htmlspecialchars($feedbackType, ENT_QUOTES, 'UTF-8') ?>"
            data-feedback-message="<?= htmlspecialchars($feedbackMessage, ENT_QUOTES, 'UTF-8') ?>"
            hidden
        ></div>

        <form method="POST" action="<?= htmlspecialchars($baseUrl) ?>/PhpFiles/Admin-End/complaintManagement.php" class="page-form">
            <?= csrfTokenField() ?>
            <input type="hidden" name="action" value="submit_complaint">

            <h2 class="section-title text-center text-dark">Complainant's Information</h2>

                <div class="form-row">
                    <div>
                        <label class="top-label">Last Name <span class="required-asterisk">*</span></label>
                        <input type="text" name="complainant_last_name" required>
                    </div>
                    <div>
                        <label class="top-label">First Name <span class="required-asterisk">*</span></label>
                        <input type="text" name="complainant_first_name" required>
                    </div>
                    <div>
                        <label class="top-label">Middle Name</label>
                        <input type="text" name="complainant_middle_name">
                    </div>
                    <div>
                        <label class="top-label">Suffix</label>
                        <select name="complainant_suffix">
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
                        <label class="top-label">Age <span class="required-asterisk">*</span></label>
                        <input type="number" name="complainant_age" min="1" required>
                    </div>
                    <div>
                        <label class="top-label">Sex <span class="required-asterisk">*</span></label>
                        <select name="complainant_sex" required>
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Prefer not to say">Prefer not to say</option>
                        </select>
                    </div>
                    <div class="phone">
                        <label class="top-label">Contact Number <span class="required-asterisk">*</span></label>
                        <input type="text" name="complainant_contact_number" inputmode="numeric" maxlength="11" pattern="^09\d{9}$" title="Format: 09XXXXXXXXX" placeholder="09XXXXXXXXX" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="full-width">
                        <div class="input-stack">
                            <label class="top-label" for="complainantAddressSystem">Address System <span class="required-asterisk">*</span></label>
                            <select class="form-select w-100" id="complainantAddressSystem" name="complainant_address_system" required>
                                <option value="">Select</option>
                                <option value="house">House Numbering System</option>
                                <option value="lot_block">Lot/Block System</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div id="complainantHouseSystemWrapper" class="form-row pt-0 d-none">
                    <div class="input-stack">
                        <label class="top-label" for="complainantUnitNumber">Unit / Apartment Number</label>
                        <input type="text" id="complainantUnitNumber" name="complainant_unit_number">
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="complainantHouseNumber">House Number <span class="required-asterisk">*</span></label>
                        <input type="text" id="complainantHouseNumber" name="complainant_house_number">
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="complainantStreetName">Street Name <span class="required-asterisk">*</span></label>
                        <input type="text" id="complainantStreetName" name="complainant_street_name">
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="complainantSubdivisionHouse">Subdivision</label>
                        <input type="text" id="complainantSubdivisionHouse" name="complainant_subdivision">
                    </div>
                    <div class="input-stack"></div>
                </div>

                <div id="complainantLotBlockSystemWrapper" class="form-row pt-0 d-none">
                    <div class="input-stack">
                        <label class="top-label" for="complainantLotNumber">Lot <span class="required-asterisk">*</span></label>
                        <input type="text" id="complainantLotNumber" name="complainant_lot_number">
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="complainantBlockNumber">Block <span class="required-asterisk">*</span></label>
                        <input type="text" id="complainantBlockNumber" name="complainant_block_number">
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="complainantPhaseNumber">Phase <span class="required-asterisk">*</span></label>
                        <input type="text" id="complainantPhaseNumber" name="complainant_phase_number">
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="complainantSubdivisionLot">Subdivision</label>
                        <input type="text" id="complainantSubdivisionLot" name="complainant_subdivision">
                    </div>
                    <div class="input-stack"></div>
                </div>

                <div class="form-row">
                    <div class="full-width">
                        <div class="row mb-3">
                            <div class="col-12 col-md-3">
                                <label class="top-label" for="complainantAreaNumber">Area</label>
                                <select class="form-select w-100" id="complainantAreaNumber" name="complainant_area_number">
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
                            <div class="col-12 col-md-3">
                                <label class="top-label">Barangay <span class="required-asterisk">*</span></label>
                                <input type="text" name="complainant_barangay" value="Barangay San Jose" readonly>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="top-label">Municipality / City <span class="required-asterisk">*</span></label>
                                <input type="text" name="complainant_municipality" value="Rodriguez" readonly>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="top-label">Province <span class="required-asterisk">*</span></label>
                                <input type="text" name="complainant_province" value="Rizal" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <h2 class="section-title text-center text-dark">Person, Establishment, or Matter Being Reported</h2>
                <p class="form-subtitle text-center mb-3">
                    This may refer to a resident, a business, a group, an unknown person, or a general concern.
                </p>

                <div class="form-row two-col-row">
                    <div>
                        <label class="top-label">Reported Subject Type <span class="required-asterisk">*</span></label>
                        <select name="subject_kind" required>
                            <option value="">Select</option>
                            <option value="Resident">Resident</option>
                            <option value="NonResident">Non-Resident</option>
                            <option value="Business">Business</option>
                            <option value="Organization">Organization</option>
                            <option value="Unknown">Unknown Person</option>
                            <option value="GeneralConcern">General Concern</option>
                        </select>
                    </div>
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
                        <input type="text" name="subject_contact_number" inputmode="numeric" maxlength="11" pattern="^09\d{9}$" title="Format: 09XXXXXXXXX" placeholder="09XXXXXXXXX">
                    </div>
                    <div>
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
                        <select id="natureOfComplaint" name="nature_of_complaint" required>
                            <option value="">Select</option>
                            <option value="Disturbance">Disturbance</option>
                            <option value="Property Dispute">Property Dispute</option>
                            <option value="Noise Complaint">Noise Complaint</option>
                            <option value="Physical Altercation">Physical Altercation</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="top-label">If Other, please specify <span id="natureOtherAsterisk" class="required-asterisk d-none">*</span></label>
                        <input type="text" id="natureOther" name="nature_other">
                    </div>
                </div>

                <div class="form-row two-col-row">
                    <div>
                        <label class="top-label">Date of the Incident <span class="required-asterisk">*</span></label>
                        <input type="date" id="incidentDate" name="incident_date" required>
                        <div id="incidentDateError" class="text-danger small mt-1 d-none" aria-live="polite"></div>
                    </div>
                    <div>
                        <label class="top-label">Time of the Incident <i>(recommended)</i></label>
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

                <div class="form-row">
                    <div class="full-width">
                        <label class="top-label">Intake Notes</label>
                        <textarea name="initial_notes" rows="4" placeholder="Enter intake or initial review notes, if any."></textarea>
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
                        <input type="text" name="witness_contact_number" inputmode="numeric" maxlength="11" pattern="^09\d{9}$" title="Format: 09XXXXXXXXX" placeholder="09XXXXXXXXX">
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
                    I hereby certify that the above information is true and correct to the best of my knowledge and belief. <span class="required-asterisk">*</span>
                </label>
                <button type="submit" class="submit-btn">SUBMIT</button>
            </div>
        </form>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../JS-Script-Files/modalHandler.js"></script>
<script src="../../JS-Script-Files/Resident-End/dateFieldModal.js"></script>
<script src="../../JS-Script-Files/Resident-End/complaintScript.js"></script>
</body>
</html>

