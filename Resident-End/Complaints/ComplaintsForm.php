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
﻿<?php
$allowUnregistered = false;
require_once __DIR__ . "/../includes/resident_access_guard.php";
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
                <p class="form-subtitle">All personal details will remain confidential.</p>
                <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

                <form method="POST" action="">
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
                            <input type="text" name="complainant_sex" required>
                        </div>
                        <div class="phone">
                            <label class="top-label">Contact Number <span class="required-asterisk">*</span></label>
                            <input type="text" name="complainant_contact_number" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="full-width">
                            <label class="top-label">Complete Address <span class="required-asterisk">*</span></label>
                            <input type="text" name="complainant_address" required>
                        </div>
                    </div>

                    <h2 class="section-title text-center text-dark">Subject of Complaint</h2>

                    <div class="form-row two-col-row">
                        <div>
                            <label class="top-label">Name (Last Name, First Name Middle Name) <span class="required-asterisk">*</span></label>
                            <input type="text" name="subject_name" placeholder="Dela Cruz, Juan Miguel" pattern="^[A-Za-z][A-Za-z'\\s-]*,\\s*[A-Za-z][A-Za-z'\\s-]*\\s+[A-Za-z][A-Za-z'\\s-]*$" title="Use format: Last Name, First Name Middle Name" required>
                        </div>
                        <div>
                            <label class="top-label">Contact Number</label>
                            <input type="text" name="subject_contact_number">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="full-width">
                            <label class="top-label">Address <span class="required-asterisk">*</span></label>
                            <input type="text" name="subject_address" required>
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
                    <div class="mb-3">
                            <label class="top-label">Location of the Incident <span class="required-asterisk">*</span></label>
                            <input type="text" name="incident_location" required>
                        </div>
                    <div class="form-row">
                        <div class="full-width">
                            <label class="top-label">Short narration of the incident <span class="required-asterisk">*</span></label>
                            <textarea name="incident_narration" rows="6" required></textarea>
                        </div>
                    </div>

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
<script src="../../JS-Script-Files/Resident-End/dateFieldModal.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        const form = document.querySelector("form");
        const submitBtn = form?.querySelector(".submit-btn");
        const incidentDateInput = document.getElementById("incidentDate");
        const incidentDateError = document.getElementById("incidentDateError");
        if (!form || !submitBtn) return;

        const today = new Date();
        const todayIso = today.toISOString().split("T")[0];
        const todayDisplay = today.toLocaleDateString(undefined, {
            year: "numeric",
            month: "long",
            day: "numeric",
        });

        const validateIncidentDate = () => {
            if (!incidentDateInput) return true;

            const value = String(incidentDateInput.value || "").trim();
            const isFuture = value !== "" && value > todayIso;

            if (isFuture) {
                const msg = `Incorrect Input. Date must be on or before ${todayDisplay}`;
                incidentDateInput.setCustomValidity(msg);
                if (incidentDateError) {
                    incidentDateError.textContent = msg;
                    incidentDateError.classList.remove("d-none");
                }
                return false;
            }

            incidentDateInput.setCustomValidity("");
            if (incidentDateError) {
                incidentDateError.textContent = "";
                incidentDateError.classList.add("d-none");
            }
            return true;
        };

        const updateState = () => {
            validateIncidentDate();
            submitBtn.disabled = !form.checkValidity();
        };

        form.addEventListener("input", updateState);
        form.addEventListener("change", updateState);
        incidentDateInput?.addEventListener("input", updateState);
        incidentDateInput?.addEventListener("change", updateState);
        incidentDateInput?.addEventListener("keyup", validateIncidentDate);
        incidentDateInput?.addEventListener("blur", validateIncidentDate);
        incidentDateInput?.addEventListener("invalid", validateIncidentDate);
        form.addEventListener("submit", (e) => {
            if (!validateIncidentDate()) {
                e.preventDefault();
                incidentDateInput?.focus();
            }
        });
        updateState();
    });
</script>
</body>
</html>
