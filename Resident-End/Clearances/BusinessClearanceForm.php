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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Business Clearance - Barangay San Jose</title>
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
            <h1 class="form-title">Application for Barangay Business Clearance</h1>
            <p class="form-subtitle">First Time Job Seeker</p>
            <p class="form-subtitle tight-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

            <form action="#" method="POST">
                
                <h2 class="section-title text-center text-dark">Owner’s Information</h2>
                <div class="form-row">
                    <div class="input-stack"><label class="top-label">Last Name<span class="required-asterisk">*</span></label><input type="text" name="o_ln" required></div>
                    <div class="input-stack"><label class="top-label">First Name<span class="required-asterisk">*</span></label><input type="text" name="o_fn" required></div>
                    <div class="input-stack"><label class="top-label">Middle Name</label><input type="text" name="o_mn"></div>
                    <div class="input-stack"><label class="top-label">Suffix</label><select name="o_sfx"><option value=\"\">None</option><option value=\"Jr.\">Jr.</option><option value=\"Sr.\">Sr.</option><option value=\"III\">III</option><option value=\"IV\">IV</option></select></div>
                </div>
                <div class="form-row"><div class="full-width"><div class="input-stack"><label class="top-label">Contact Number</label><input type="text" name="o_phone"></div></div></div>
                <div id="ownerAddressWrapper" class="form-row">
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

                <h2 class="section-title text-center text-dark">Business Details</h2>
                <div class="form-row"><div class="full-width"><div class="input-stack"><label class="top-label">Name of Business<span class="required-asterisk">*</span></label><input type="text" name="b_name" required></div></div></div>
                <div id="businessLocationWrapper" class="form-row">
                    <div class="full-width">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="top-label" for="business_unit_number">Unit / Apartment Number</label>
                                <input type="text" id="business_unit_number" name="business_unit_number">
                            </div>
                            <div class="col-md-4">
                                <label class="top-label" for="business_house_number">House Number <span class="required-asterisk">*</span></label>
                                <input type="text" id="business_house_number" name="business_house_number" required>
                            </div>
                            <div class="col-md-4">
                                <label class="top-label" for="business_street_name">Street Name <span class="required-asterisk">*</span></label>
                                <input type="text" id="business_street_name" name="business_street_name" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-row"><div class="full-width"><div class="input-stack"><label class="top-label">Date of Initial Operation<span class="required-asterisk">*</span></label><input type="date" name="b_date" required></div></div></div>
                
                <div class="form-row">
                    <div class="full-width">
                        <div class="d-flex align-items-center justify-content-start gap-3 app-type-row">
                            <p class="if-building-note mb-0">CHOOSE ONE:</p>
                            <div class="check-item">
                                <input type="radio" id="app_new" name="application_type" value="New" class="clearance-radio" required>
                                <label class="app-type-label" for="app_new">New Application</label>
                            </div>
                            <div class="check-item">
                                <input type="radio" id="app_renewal" name="application_type" value="Renewal" class="clearance-radio" required>
                                <label class="app-type-label" for="app_renewal">Renewal</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="full-width"><div class="input-stack"><label class="top-label">Contact Number</label><input type="text" name="b_contact_1"></div></div>
                </div>

                <div class="form-row">
                    <div class="full-width">
                        <label class="top-label">Ownership <span class="required-asterisk">*</span></label>
                        <select name="owner_type" id="ownerTypeSelect" required>
                            <option value="">Select</option>
                            <option value="Owner">Owner</option>
                            <option value="Renter">Renter</option>
                            <option value="Occupant">Occupant</option>
                        </select>
                    </div>
                </div>

                <div id="renterOwnerDetails" class="d-none">
                    <h2 class="section-title text-center text-dark">If Renter Occupant, Name of the Owner</h2>
                    <div class="form-row">
                        <div class="input-stack"><label class="top-label">Last Name<span class="required-asterisk">*</span></label><input type="text" name="ro_ln"></div>
                        <div class="input-stack"><label class="top-label">First Name<span class="required-asterisk">*</span></label><input type="text" name="ro_fn"></div>
                        <div class="input-stack"><label class="top-label">Middle Name</label><input type="text" name="ro_mn"></div>
                        <div class="input-stack"><label class="top-label">Suffix</label><select name="ro_sfx"><option value=\"\">None</option><option value=\"Jr.\">Jr.</option><option value=\"Sr.\">Sr.</option><option value=\"III\">III</option><option value=\"IV\">IV</option></select></div>
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
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const form = document.querySelector("form");
            const submitBtn = form?.querySelector(".submit-btn");
            const ownerTypeSelect = document.getElementById("ownerTypeSelect");
            const renterOwnerDetails = document.getElementById("renterOwnerDetails");
            const renterOwnerRequired = renterOwnerDetails
                ? Array.from(renterOwnerDetails.querySelectorAll("input[name='ro_ln'], input[name='ro_fn']"))
                : [];
            if (!form || !submitBtn) return;

            const updateState = () => {
                if (ownerTypeSelect && renterOwnerDetails) {
                    const isRenterOrOccupant = ownerTypeSelect.value === "Renter" || ownerTypeSelect.value === "Occupant";
                    renterOwnerDetails.classList.toggle("d-none", !isRenterOrOccupant);
                    renterOwnerRequired.forEach((input) => {
                        input.required = isRenterOrOccupant;
                    });
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






