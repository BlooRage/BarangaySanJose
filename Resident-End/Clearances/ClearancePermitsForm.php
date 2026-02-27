<?php
if (!isset($permitFormType)) {
    $permitFormType = '';
}
$isTricycleForm = $permitFormType === 'tricycle';
$isOtherPermitsForm = $permitFormType === 'other_permits';
$permitFormTitleMap = [
    'tricycle' => 'Application for Barangay Clearance for Tricycle Permit',
    'electrical' => 'Application for Barangay Clearance for Electrical Permit',
    'water' => 'Application for Barangay Clearance for Water Permit',
    'residential' => 'Application for Barangay Clearance for Residential Permit',
    'commercial' => 'Application for Barangay Clearance for Commercial Permit',
    'other_permits' => 'Application for Barangay Clearance for Other Permits',
];
$permitFormHeading = $permitFormTitleMap[$permitFormType] ?? 'Application for Barangay Clearance for Permits';

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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($permitFormHeading) ?> - Barangay San Jose</title>
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
            
            <h1 class="form-title"><?= htmlspecialchars($permitFormHeading) ?></h1>
            <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

            <form action="#" method="POST">
                

                <h2 class="section-title text-center text-dark">Owner’s Information</h2>
                <div class="form-row">
                    <div class="input-stack">
                        <label class="top-label">Last Name<span class="required-asterisk">*</span></label>
                        <input type="text" name="owner_last_name" required>
                    </div>
                    <div class="input-stack">
                        <label class="top-label">First Name<span class="required-asterisk">*</span></label>
                        <input type="text" name="owner_first_name" required>
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Middle Name</label>
                        <input type="text" name="owner_middle_name">
                    </div>
                    <div class="input-stack">
                        <label class="top-label">Suffix</label>
                        <input type="text" name="owner_suffix">
                    </div>
                </div>

                <div class="form-row">
                    <div class="contact">
                        <div class="input-stack">
                            <label class="top-label">Contact Number</label>
                            <input type="text" name="owner_phone">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="full-width">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="top-label" for="owner_unit_number">Unit / Apartment Number</label>
                                <input type="text" id="owner_unit_number" name="owner_unit_number">
                            </div>
                            <div class="col-md-4">
                                <label class="top-label" for="owner_house_number">House Number <span class="required-asterisk">*</span></label>
                                <input type="text" id="owner_house_number" name="owner_house_number" required>
                            </div>
                            <div class="col-md-4">
                                <label class="top-label" for="owner_street_name">Street Name <span class="required-asterisk">*</span></label>
                                <input type="text" id="owner_street_name" name="owner_street_name" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="full-width">
                        <div class="d-flex align-items-center justify-content-start gap-3 app-type-row">
                            <p class="if-building-note mb-0">CHOOSE ONE:</p>
                            <div class="check-item"><input type="radio" name="app_type" id="new" value="New" class="clearance-radio" required><label class="app-type-label" for="new">New Application</label></div>
                            <div class="check-item"><input type="radio" name="app_type" id="ren" value="Renewal" class="clearance-radio" required><label class="app-type-label" for="ren">Renewal</label></div>
                        </div>
                    </div>
                </div>

                <?php if ($isOtherPermitsForm): ?>
                <div class="form-row">
                    <div class="full-width">
                        <div class="input-stack">
                            <label class="top-label" for="specified_permit">Specify the permit for application:<span class="required-asterisk">*</span></label>
                            <input type="text" id="specified_permit" name="specified_permit" required>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($isTricycleForm): ?>
                <div id="tricycleSection">
                    <h2 class="section-title text-center text-dark" style="font-size: 16px; margin-top: 0;">Driver's Information</h2>
                    
                    <div class="form-row">
                        <div class="input-stack">
                            <label class="top-label">Last Name<span class="required-asterisk">*</span></label>
                            <input type="text" name="d_ln">
                        </div>
                        <div class="input-stack">
                            <label class="top-label">First Name<span class="required-asterisk">*</span></label>
                            <input type="text" name="d_fn">
                        </div>
                        <div class="input-stack">
                            <label class="top-label">Middle Name</label>
                            <input type="text" name="d_mn">
                        </div>
                        <div class="input-stack">
                            <label class="top-label">Suffix</label>
                            <input type="text" name="d_sfx">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="contact">
                            <div class="input-stack">
                                <label class="top-label">Contact Number</label>
                                <input type="text" name="d_phone">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="full-width">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="top-label" for="driver_unit_number">Unit / Apartment Number</label>
                                    <input type="text" id="driver_unit_number" name="driver_unit_number">
                                </div>
                                <div class="col-md-4">
                                    <label class="top-label" for="driver_house_number">House Number <span class="required-asterisk">*</span></label>
                                    <input type="text" id="driver_house_number" name="driver_house_number" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="top-label" for="driver_street_name">Street Name <span class="required-asterisk">*</span></label>
                                    <input type="text" id="driver_street_name" name="driver_street_name" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="full-width">
                            <div class="input-stack">
                                <label class="top-label">Tricycle Type</label>
                                <select id="tricycleTypeSelect" name="tricycle_type">
                                    <option value="">Select</option>
                                    <option value="Private">Private</option>
                                    <option value="TODA">TODA</option>
                                    <option value="PODA">PODA</option>
                                </select>
                            </div>

                            <div id="privateDetails" class="d-none">
                                <div class="input-stack">
                                    <label class="top-label">OR/CR Number:</label>
                                    <input type="text" name="or_cr">
                                </div>

                                <div class="input-stack">
                                    <label class="top-label">Plate Number:</label>
                                    <input type="text" name="plate">
                                </div>

                                <div class="input-stack">
                                    <label class="top-label">Body Number:</label>
                                    <input type="text" name="body">
                                </div>
                            </div>

                            <div id="todaDetails" class="d-none">
                                <div class="input-stack">
                                    <label class="top-label">Specify TODA:</label>
                                    <input type="text" name="spec_toda">
                                </div>
                            </div>

                            <div id="podaDetails" class="d-none">
                                <div class="input-stack">
                                    <label class="top-label">Specify PODA:</label>
                                    <input type="text" name="spec_poda">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

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
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const form = document.querySelector("form");
            const submitBtn = form?.querySelector(".submit-btn");
            const tricycleTypeSelect = document.getElementById("tricycleTypeSelect");
            const privateDetails = document.getElementById("privateDetails");
            const todaDetails = document.getElementById("todaDetails");
            const podaDetails = document.getElementById("podaDetails");
            const specifiedPermitInput = document.getElementById("specified_permit");
            if (!form || !submitBtn) return;

            const updateState = () => {
                if (specifiedPermitInput) {
                    const words = specifiedPermitInput.value.trim().split(/\s+/).filter(Boolean);
                    if (words.length > 50) {
                        specifiedPermitInput.value = words.slice(0, 50).join(" ");
                    }
                }
                if (tricycleTypeSelect && privateDetails && todaDetails && podaDetails) {
                    const type = tricycleTypeSelect.value;
                    privateDetails.classList.toggle("d-none", type !== "Private");
                    todaDetails.classList.toggle("d-none", type !== "TODA");
                    podaDetails.classList.toggle("d-none", type !== "PODA");
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






