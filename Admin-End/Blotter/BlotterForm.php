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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
    <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260302-2">
    <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/BlotterMangementStyle.css?v=20260305-2">
</head>
<body>
<div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main id="main-display" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 pb-md-5 pt-md-5 bg-light">
        <h2 class="mb-3" style="font-family: 'Charis SIL Bold'; color: #DE710C;">Blotter Form</h2>
        <hr class="mb-4">

        <div class="blotter-shell py-3 rounded">
            <a href="<?= htmlspecialchars($baseUrl) ?>/Admin-End/AdminDashboard.php" class="back-link">&lt; Go Back</a>

            <div class="text-center mt-3 mb-4">
                <h1 class="form-title mb-2">Blotter Form</h1>
                <p class="form-subtitle mb-0">All fields marked with <span class="required-asterisk">*</span> are required.</p>
            </div>

            <form method="POST" action="" id="blotterForm" novalidate>
                <h3 class="section-title mb-3 text-center">Blotter Information</h3>
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label">Blotter Number (Numero ng Blotter) <span class="required-asterisk">*</span></label>
                        <input type="text" class="form-control" name="blotter_number" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Date Filed (Petsa ng Paghahain) <span class="required-asterisk">*</span></label>
                        <input type="date" class="form-control" name="date_filed" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Time Filed (Oras ng Paghahain) <span class="required-asterisk">*</span></label>
                        <input type="time" class="form-control" name="time_filed" required>
                    </div>
                </div>

                <h3 class="section-title mb-3 text-center">Complainant (Nagreklamo)</h3>
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label">Last Name <span class="required-asterisk">*</span></label>
                        <input type="text" class="form-control" name="complainant_last_name" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">First Name <span class="required-asterisk">*</span></label>
                        <input type="text" class="form-control" name="complainant_first_name" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Middle Name</label>
                        <input type="text" class="form-control" name="complainant_middle_name">
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

                <div class="row g-3 mb-3">
                    <div class="col-12 col-lg-4">
                        <label class="form-label">Address System</label>
                        <select class="form-select" name="complainant_address_system">
                            <option value="">Select</option>
                            <option value="house">House Numbering System</option>
                            <option value="lot_block">Lot/Block System</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label">Unit / Apartment Number</label>
                        <input type="text" class="form-control" name="complainant_unit_number">
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label">Subdivision</label>
                        <input type="text" class="form-control" name="complainant_subdivision">
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label">House Number</label>
                        <input type="text" class="form-control" name="complainant_house_number">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Street Name</label>
                        <input type="text" class="form-control" name="complainant_street_name">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Area</label>
                        <select class="form-select" name="complainant_area_number">
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
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Lot</label>
                        <input type="text" class="form-control" name="complainant_lot_number">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Block</label>
                        <input type="text" class="form-control" name="complainant_block_number">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Phase</label>
                        <input type="text" class="form-control" name="complainant_phase_number">
                    </div>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Barangay</label>
                        <input type="text" class="form-control" name="complainant_barangay" value="Barangay San Jose" muted readonly>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Municipality / City</label>
                        <input type="text" class="form-control" name="complainant_municipality" value="Rodriguez" muted readonly>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Province</label>
                        <input type="text" class="form-control" name="complainant_province" value="Rizal" muted readonly>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Contact Number (Numero ng Telepono)</label>
                        <input type="text" class="form-control" name="complainant_contact_number">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Age (Edad)</label>
                        <input type="number" min="1" class="form-control" name="complainant_age">
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
                        <input type="text" class="form-control" name="respondent_last_name" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">First Name <span class="required-asterisk">*</span></label>
                        <input type="text" class="form-control" name="respondent_first_name" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Middle Name</label>
                        <input type="text" class="form-control" name="respondent_middle_name">
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

                <div class="row g-3 mb-3">
                    <div class="col-12 col-lg-4">
                        <label class="form-label">Address System</label>
                        <select class="form-select" name="respondent_address_system">
                            <option value="">Select</option>
                            <option value="house">House Numbering System</option>
                            <option value="lot_block">Lot/Block System</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label">Unit / Apartment Number</label>
                        <input type="text" class="form-control" name="respondent_unit_number">
                    </div>
                    <div class="col-12 col-lg-4">
                        <label class="form-label">Subdivision</label>
                        <input type="text" class="form-control" name="respondent_subdivision">
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label">House Number</label>
                        <input type="text" class="form-control" name="respondent_house_number">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Street Name</label>
                        <input type="text" class="form-control" name="respondent_street_name">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Area</label>
                        <select class="form-select" name="respondent_area_number">
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
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Lot</label>
                        <input type="text" class="form-control" name="respondent_lot_number">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Block</label>
                        <input type="text" class="form-control" name="respondent_block_number">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Phase</label>
                        <input type="text" class="form-control" name="respondent_phase_number">
                    </div>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Barangay</label>
                        <input type="text" class="form-control" name="respondent_barangay" value="Barangay San Jose" muted readonly>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Municipality / City</label>
                        <input type="text" class="form-control" name="respondent_municipality" value="Rodriguez" muted readonly>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Province</label>
                        <input type="text" class="form-control" name="respondent_province" value="Rizal" muted readonly>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Contact Number (Numero ng Telepono)</label>
                        <input type="text" class="form-control" name="respondent_contact_number">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Age (Edad)</label>
                        <input type="number" min="1" class="form-control" name="respondent_age">
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
                        <input type="date" class="form-control" name="incident_date" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Time of Incident <span class="required-asterisk">*</span></label>
                        <input type="time" class="form-control" name="incident_time" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Place of Incident <span class="required-asterisk">*</span></label>
                        <input type="text" class="form-control" name="incident_place" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Type of Complaint <span class="required-asterisk">*</span></label>
                        <select class="form-select" name="complaint_type" required>
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
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12">
                        <label class="form-label">Narrative Input Method <span class="required-asterisk">*</span></label>
                        <select class="form-select" id="narrativeInputMethod" name="narrative_input_method" required>
                            <option value="text" selected>Type Narrative Report</option>
                            <option value="file">Upload File (PDF/Image)</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-4" id="narrativeTextWrapper">
                    <div class="col-12">
                        <label class="form-label">Narrative Report (Salaysay ng Pangyayari) <span class="required-asterisk">*</span></label>
                        <textarea class="form-control" name="narrative_report" rows="8" required></textarea>
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

                <div class="text-center">
                    <button type="submit" id="blotterSubmit" class="btn btn-primary px-5">Submit</button>
                </div>
            </form>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../JS-Script-Files/Admin-End/blotterManagement.js?v=20260305-1" defer></script>
</body>
</html>
