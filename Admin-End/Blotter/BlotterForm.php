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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Blotter Form</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
    <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260302-2">
    <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/BlotterMangementStyle.css?v=20260309-2">
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
                <a href="<?= htmlspecialchars($baseUrl) ?>/Admin-End/AdminDashboard.php" class="back-link d-inline-flex align-items-center text-decoration-none text-dark m-0 position-absolute start-0">
                    <i class="bi bi-arrow-left-short fs-3"></i>
                </a>
                <h1 class="form-title m-0">Blotter Form</h1>
            </div>
            <p class="form-subtitle mb-4 text-center">All fields marked with <span class="required-asterisk">*</span> are required.</p>

            <form method="POST" action="../../PhpFiles/Admin-End/blotterManagement.php" id="blotterForm" class="page-form" enctype="multipart/form-data">
                <h3 class="section-title mb-3 text-center">Blotter Information</h3>
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label">Blotter Number (Numero ng Blotter) <span class="required-asterisk">*</span></label>
                        <input type="text" class="form-control" name="blotter_number" required maxlength="50" pattern="^[A-Za-z0-9\\-]+$" title="Use letters, numbers, and hyphens only.">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Date Filed (Petsa ng Paghahain) <span class="required-asterisk">*</span></label>
                        <input type="date" class="form-control" name="date_filed" required readonly>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Time Filed (Oras ng Paghahain) <span class="required-asterisk">*</span></label>
                        <input type="time" class="form-control" name="time_filed" required readonly>
                    </div>
                </div>

                <h3 class="section-title mb-3 text-center">Complainant (Nagreklamo)</h3>
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label">Last Name <span class="required-asterisk">*</span></label>
                        <input type="text" class="form-control" name="complainant_last_name" required minlength="2" maxlength="100" pattern="^[A-Za-z\\s.'-]+$" title="Letters, spaces, apostrophes, and hyphens only.">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">First Name <span class="required-asterisk">*</span></label>
                        <input type="text" class="form-control" name="complainant_first_name" required minlength="2" maxlength="100" pattern="^[A-Za-z\\s.'-]+$" title="Letters, spaces, apostrophes, and hyphens only.">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Middle Name</label>
                        <input type="text" class="form-control" name="complainant_middle_name" maxlength="100" pattern="^[A-Za-z\\s.'-]*$" title="Letters, spaces, apostrophes, and hyphens only.">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Suffix</label>
                        <select class="form-select" name="complainant_suffix">
                            <option value="">None</option>
                            <option value="Jr.">Jr.</option>
                            <option value="Sr.">Sr.</option>
                            <option value="III">III</option>
                            <option value="IV">IV</option>
                        </select>
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
                                <label class="top-label">Barangay</label>
                                <input type="text" name="complainant_barangay" value="Barangay San Jose" readonly>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="top-label">Municipality / City</label>
                                <input type="text" name="complainant_municipality" value="Rodriguez" readonly>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="top-label">Province</label>
                                <input type="text" name="complainant_province" value="Rizal" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Contact Number (Numero ng Telepono)</label>
                        <input type="text" class="form-control" name="complainant_contact_number" inputmode="numeric" maxlength="11" pattern="^09\d{9}$" title="Format: 09XXXXXXXXX" placeholder="09XXXXXXXXX">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Age (Edad)</label>
                        <input type="number" min="1" max="120" class="form-control" name="complainant_age">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Sex (Kasarian)</label>
                        <select class="form-select" name="complainant_sex">
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Prefer not to say">Prefer not to say</option>
                        </select>
                    </div>
                </div>
                <h3 class="section-title mb-3 text-center">Respondent (Inirereklamo)</h3>
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label">Last Name <span class="required-asterisk">*</span></label>
                        <input type="text" class="form-control" name="respondent_last_name" required minlength="2" maxlength="100" pattern="^[A-Za-z\\s.'-]+$" title="Letters, spaces, apostrophes, and hyphens only.">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">First Name <span class="required-asterisk">*</span></label>
                        <input type="text" class="form-control" name="respondent_first_name" required minlength="2" maxlength="100" pattern="^[A-Za-z\\s.'-]+$" title="Letters, spaces, apostrophes, and hyphens only.">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Middle Name</label>
                        <input type="text" class="form-control" name="respondent_middle_name" maxlength="100" pattern="^[A-Za-z\\s.'-]*$" title="Letters, spaces, apostrophes, and hyphens only.">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Suffix</label>
                        <select class="form-select" name="respondent_suffix">
                            <option value="">None</option>
                            <option value="Jr.">Jr.</option>
                            <option value="Sr.">Sr.</option>
                            <option value="III">III</option>
                            <option value="IV">IV</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="full-width">
                        <div class="input-stack">
                            <label class="top-label" for="respondentAddressSystem">Address System <span class="required-asterisk">*</span></label>
                            <select class="form-select w-100" id="respondentAddressSystem" name="respondent_address_system" required>
                                <option value="">Select</option>
                                <option value="house">House Numbering System</option>
                                <option value="lot_block">Lot/Block System</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div id="respondentHouseSystemWrapper" class="form-row pt-0 d-none">
                    <div class="input-stack">
                        <label class="top-label" for="respondentUnitNumber">Unit / Apartment Number</label>
                        <input type="text" id="respondentUnitNumber" name="respondent_unit_number">
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="respondentHouseNumber">House Number <span class="required-asterisk">*</span></label>
                        <input type="text" id="respondentHouseNumber" name="respondent_house_number">
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="respondentStreetName">Street Name <span class="required-asterisk">*</span></label>
                        <input type="text" id="respondentStreetName" name="respondent_street_name">
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="respondentSubdivisionHouse">Subdivision</label>
                        <input type="text" id="respondentSubdivisionHouse" name="respondent_subdivision">
                    </div>
                    <div class="input-stack"></div>
                </div>

                <div id="respondentLotBlockSystemWrapper" class="form-row pt-0 d-none">
                    <div class="input-stack">
                        <label class="top-label" for="respondentLotNumber">Lot <span class="required-asterisk">*</span></label>
                        <input type="text" id="respondentLotNumber" name="respondent_lot_number">
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="respondentBlockNumber">Block <span class="required-asterisk">*</span></label>
                        <input type="text" id="respondentBlockNumber" name="respondent_block_number">
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="respondentPhaseNumber">Phase <span class="required-asterisk">*</span></label>
                        <input type="text" id="respondentPhaseNumber" name="respondent_phase_number">
                    </div>
                    <div class="input-stack">
                        <label class="top-label" for="respondentSubdivisionLot">Subdivision</label>
                        <input type="text" id="respondentSubdivisionLot" name="respondent_subdivision">
                    </div>
                    <div class="input-stack"></div>
                </div>
                <div class="form-row">
                    <div class="full-width">
                        <div class="row mb-3">
                            <div class="col-12 col-md-3">
                                <label class="top-label" for="respondentAreaNumber">Area</label>
                                <select class="form-select w-100" id="respondentAreaNumber" name="respondent_area_number">
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
                                <label class="top-label">Barangay</label>
                                <input type="text" name="respondent_barangay" value="Barangay San Jose" readonly>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="top-label">Municipality / City</label>
                                <input type="text" name="respondent_municipality" value="Rodriguez" readonly>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="top-label">Province</label>
                                <input type="text" name="respondent_province" value="Rizal" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Contact Number (Numero ng Telepono)</label>
                        <input type="text" class="form-control" name="respondent_contact_number" inputmode="numeric" maxlength="11" pattern="^09\d{9}$" title="Format: 09XXXXXXXXX" placeholder="09XXXXXXXXX">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Age (Edad)</label>
                        <input type="number" min="1" max="120" class="form-control" name="respondent_age">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Sex (Kasarian)</label>
                        <select class="form-select" name="respondent_sex">
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Prefer not to say">Prefer not to say</option>
                        </select>
                    </div>
                </div>
                <h3 class="section-title mb-3 text-center">Incident Details</h3>
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label">Date of Incident <span class="required-asterisk">*</span></label>
                        <input type="date" class="form-control" id="incidentDate" name="incident_date" required>
                        <div id="incidentDateError" class="invalid-feedback d-block d-none"></div>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Time of Incident <span class="required-asterisk">*</span></label>
                        <input type="time" class="form-control" id="incidentTime" name="incident_time" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Place of Incident <span class="required-asterisk">*</span></label>
                        <input type="text" class="form-control" name="incident_place" required maxlength="255">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Type of Complaint <span class="required-asterisk">*</span></label>
                        <select class="form-select" id="blotterComplaintType" name="complaint_type" required>
                            <option value="">Select</option>
                            <option value="Alarm and Scandal">Alarm and Scandal</option>
                            <option value="Physical Injury">Physical Injury</option>
                            <option value="Theft">Theft</option>
                            <option value="Pagtatapon ng Basura">Pagtatapon ng Basura</option>
                            <option value="Small Claim">Small Claim</option>
                            <option value="Property Related">Property Related</option>
                            <option value="Light / Grave Threat">Light / Grave Threat</option>
                            <option value="ESTAFA">ESTAFA</option>
                            <option value="Threat">Threat</option>
                            <option value="Anti-Burning Law">Anti-Burning Law</option>
                            <option value="Slander by Deeds">Slander by Deeds</option>
                            <option value="Malicious Mischief">Malicious Mischief</option>
                            <option value="Fraud">Fraud</option>
                            <option value="False Accusation">False Accusation</option>
                            <option value="Breach of Contract">Breach of Contract</option>
                            <option value="Arguments">Arguments</option>
                            <option value="Unjust Vexation">Unjust Vexation</option>
                            <option value="Trespassing">Trespassing</option>
                            <option value="Lost and Found">Lost and Found</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label">If Other, please specify</label>
                        <input type="text" class="form-control" id="blotterComplaintTypeOther" name="complaint_type_other" disabled>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12">
                        <label class="form-label">Narrative Input Method <span class="required-asterisk">*</span></label>
                        <select class="form-select" id="narrativeInputMethod" name="narrative_input_method" required>
                            <option value="" selected>Select</option>
                            <option value="text">Type Narrative Report</option>
                            <option value="file">Upload File (PDF/Image)</option>
                        </select>
                    </div>
                </div>


                <div class="modal fade" id="narrativeSignatureModal" tabindex="-1" aria-labelledby="narrativeSignatureModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="narrativeSignatureModalLabel">Narrative and Signatures</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3 mb-4" id="narrativeTextWrapper">
                                    <div class="col-12">
                                        <label class="form-label">Narrative Report (Salaysay ng Pangyayari) <span class="required-asterisk">*</span></label>
                                        <textarea class="form-control" name="narrative_report" rows="8" required minlength="10" maxlength="5000"></textarea>
                                    </div>
                                </div>

                                <div class="row g-3 mb-4 d-none" id="narrativeFileWrapper">
                                    <div class="col-12">
                                        <label class="form-label">Narrative File (PDF or Image) <span class="required-asterisk">*</span></label>
                                        <div class="upload-box" id="narrativeUploadBox" role="button" tabindex="0">
                                            <div class="upload-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                                            <div class="upload-text">Drag and drop file here</div>
                                            <div class="upload-subtext">or click to choose manually</div>
                                            <div class="upload-subtext mt-1">Accepted: PDF, JPG, JPEG, PNG, WEBP</div>
                                            <input type="file" id="narrativeFileInput" name="narrative_file" accept=".pdf,image/*" class="d-none">
                                        </div>
                                        <div id="narrativeFileName" class="upload-file-name d-none"></div>
                                    </div>
                                </div>

                                <div class="row g-3 mb-2" id="signatureSection">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Complainant Signature <span class="required-asterisk">*</span></label>
                                        <canvas
                                            id="complainantSignatureCanvas"
                                            class="w-100 rounded border bg-white"
                                            style="height: 170px; touch-action: none;"
                                        ></canvas>
                                        <input type="hidden" id="complainantSignatureData" name="complainant_signature">
                                        <div id="complainantSignatureError" class="invalid-feedback d-block d-none">Please provide complainant signature.</div>
                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" id="clearComplainantSignature">Clear Signature</button>
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="openComplainantSignatureFullscreen">Open Fullscreen</button>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Respondent Signature <span class="required-asterisk">*</span></label>
                                        <canvas
                                            id="respondentSignatureCanvas"
                                            class="w-100 rounded border bg-white"
                                            style="height: 170px; touch-action: none;"
                                        ></canvas>
                                        <input type="hidden" id="respondentSignatureData" name="respondent_signature">
                                        <div id="respondentSignatureError" class="invalid-feedback d-block d-none">Please provide respondent signature.</div>
                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" id="clearRespondentSignature">Clear Signature</button>
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="openRespondentSignatureFullscreen">Open Fullscreen</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" id="blotterSubmit" class="btn btn-primary px-5">Submit</button>
                </div>
            </form>

        <div class="modal fade" id="confirmSubmitModal" tabindex="-1" aria-labelledby="confirmSubmitLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="confirmSubmitLabel">Confirm Submission</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to file this Incident Report?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="confirmSubmitBtn">Yes</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="successSubmitModal" tabindex="-1" aria-labelledby="successSubmitLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="successSubmitLabel">Success</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Incident Report Filed.
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade signature-fullscreen-modal" id="signatureFullscreenModal" tabindex="-1" aria-labelledby="signatureFullscreenLabel" aria-hidden="true">
            <div class="modal-dialog modal-fullscreen">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="signatureFullscreenLabel">Signature</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body signature-fullscreen-body">
                        <canvas id="signatureFullscreenCanvas" class="signature-fullscreen-canvas"></canvas>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" id="signatureFullscreenClear">Clear</button>
                        <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="signatureFullscreenSave">Save Signature</button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../JS-Script-Files/Admin-End/blotterManagement.js?v=20260309-2" defer></script>
</body>
</html>
