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

$lastName = htmlspecialchars((string)($residentinformationtbl['lastname'] ?? ''), ENT_QUOTES, 'UTF-8');
$firstName = htmlspecialchars((string)($residentinformationtbl['firstname'] ?? ''), ENT_QUOTES, 'UTF-8');
$middleName = htmlspecialchars((string)($residentinformationtbl['middlename'] ?? ''), ENT_QUOTES, 'UTF-8');
$suffix = (string)($residentinformationtbl['suffix'] ?? '');
$contactNumber = htmlspecialchars((string)($useraccountstbl['phone_number'] ?? ''), ENT_QUOTES, 'UTF-8');

$unitNumber = trim((string)($residentaddresstbl['unit_number'] ?? ''));
$houseNumber = trim((string)($residentaddresstbl['street_number'] ?? ''));
$streetName = trim((string)($residentaddresstbl['street_name'] ?? ''));
$phaseNumber = trim((string)($residentaddresstbl['phase_number'] ?? ''));
$subdivision = trim((string)($residentaddresstbl['subdivision'] ?? ''));

$normalizedStreetName = $streetName;
if ($normalizedStreetName !== '' && !preg_match('/\b(street|st\.?)$/i', $normalizedStreetName)) {
    $normalizedStreetName .= ' Street';
}

$fullAddress = implode(', ', array_filter([
    $unitNumber !== '' ? 'Unit ' . $unitNumber : '',
    trim($houseNumber . ' ' . $normalizedStreetName),
    $phaseNumber !== '' ? 'Phase ' . $phaseNumber : '',
    $subdivision !== '' ? $subdivision . ' Subdivision' : '',
    'San Jose',
    'Rodriguez',
    'Rizal',
], static fn($part) => trim((string)$part) !== ''));

$formValues = [
    'subject' => '',
    'subject_other' => '',
    'appointment_date' => '',
    'appointment_time' => '',
    'purpose' => '',
];
$feedbackType = !empty($_GET['success']) ? 'success' : (!empty($_GET['error']) ? 'error' : '');
$feedbackMessage = !empty($_GET['success'])
    ? (string)$_GET['success']
    : (!empty($_GET['error']) ? (string)$_GET['error'] : '');
$currentMonthStartDate = date('Y-m-01');
$minAppointmentDate = date('Y-m-d', strtotime('+1 day'));
$maxAppointmentDate = date('Y-m-t');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Appointment Form</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <link rel="stylesheet" href="../../CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/applicationForms.css">
</head>
<body>
    <div class="d-flex min-vh-100">

        <?php include __DIR__ . '/../includes/resident_sidebar.php'; ?>

        <main id="div-mainDisplay" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 pb-md-5 pt-md-0 bg-light">

            <div class="main-head application-card orange-card application-card--muted py-3 mt-5 rounded">
                <div class="main-head-content">
                    <a href="<?= htmlspecialchars($baseUrl) ?>/Resident-End/resident_dashboard.php" class="back-link">&lt; Go Back</a>
                    <h1 class="form-title" style="color: #de710c">Appointment Form</h1>
                    <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

                    <form method="POST" action="<?= htmlspecialchars(appUrl('/PhpFiles/Resident-End/submitAppointment.php'), ENT_QUOTES, 'UTF-8') ?>">
                        <?= csrfTokenField() ?>
                        <input type="hidden" name="action" value="submit_appointment">
                        <?php if ($feedbackMessage !== '' && $feedbackType !== 'success'): ?>
                            <div class="alert alert-<?php echo $feedbackType === 'success' ? 'success' : 'danger'; ?>" role="alert">
                                <?php echo htmlspecialchars($feedbackMessage, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>
                        <h2 class="section-title text-center text-dark">Information</h2>

                        <div class="form-row pt-0">
                            <div>
                                <label class="top-label">Last Name <span class="required-asterisk">*</span></label>
                                <input type="text" name="last_name" value="<?php echo $lastName; ?>" readonly>
                            </div>
                            <div>
                                <label class="top-label">First Name <span class="required-asterisk">*</span></label>
                                <input type="text" name="first_name" value="<?php echo $firstName; ?>" readonly>
                            </div>
                            <div>
                                <label class="top-label">Middle Name</label>
                                <input type="text" name="middle_name" value="<?php echo $middleName; ?>" readonly>
                            </div>
                            <div>
                                <label class="top-label">Suffix</label>
                                <select name="suffix_name" class="text-bg-light" readonly value="<?php echo htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8'); ?>" disabled>
                                    <option value="" <?php echo ($suffix === "") ? "selected" : ""; ?>>None</option>
                                    <option value="Jr." <?php echo ($suffix === "Jr.") ? "selected" : ""; ?>>Jr.</option>
                                    <option value="Sr." <?php echo ($suffix === "Sr.") ? "selected" : ""; ?>>Sr.</option>
                                    <option value="III" <?php echo ($suffix === "III") ? "selected" : ""; ?>>III</option>
                                    <option value="IV" <?php echo ($suffix === "IV") ? "selected" : ""; ?>>IV</option>
                                </select>
                                <input type="hidden" name="suffix_name" value="<?php echo htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Contact Number <span class="required-asterisk">*</span></label>
                                <input type="text" name="contact_number" value="<?php echo $contactNumber; ?>" readonly>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                            <label class="top-label">Address <span class="required-asterisk">*</span></label>
                                <input type="text" name="full_address_display" readonly value="<?php echo htmlspecialchars($fullAddress, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="unitNumber" value="<?php echo htmlspecialchars($unitNumber, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="houseNumber" value="<?php echo htmlspecialchars($houseNumber, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="streetName" value="<?php echo htmlspecialchars($streetName, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>

                        <div class="form-row two-col-row">
                            <div>
                                <label class="top-label">Subject of Appointment <span class="required-asterisk">*</span></label>
                                <select name="subject" id="appointmentSubject" required>
                                    <option value="">Select</option>
                                    <option value="follow_up" <?php echo $formValues['subject'] === 'follow_up' ? 'selected' : ''; ?>>Follow-up Concern</option>
                                    <option value="consultation" <?php echo $formValues['subject'] === 'consultation' ? 'selected' : ''; ?>>Consultation</option>
                                    <option value="event_coordination" <?php echo $formValues['subject'] === 'event_coordination' ? 'selected' : ''; ?>>Event Coordination</option>
                                    <option value="other" <?php echo $formValues['subject'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="top-label">If Other, please specify</label>
                                <input
                                    type="text"
                                    name="subject_other"
                                    id="appointmentSubjectOther"
                                    value="<?php echo htmlspecialchars($formValues['subject_other'], ENT_QUOTES, 'UTF-8'); ?>"
                                    maxlength="150"
                                >
                                <div id="appointmentSubjectOtherError" class="text-danger small mt-1 d-none" aria-live="polite"></div>
                            </div>
                        </div>

                        <div class="form-row two-col-row">
                            <div>
                                <label class="top-label">Date of Appointment <span class="required-asterisk">*</span></label>
                                <input type="date" id="appointmentDate" name="appointment_date" min="<?php echo htmlspecialchars($minAppointmentDate, ENT_QUOTES, 'UTF-8'); ?>" max="<?php echo htmlspecialchars($maxAppointmentDate, ENT_QUOTES, 'UTF-8'); ?>" data-month-start="<?php echo htmlspecialchars($currentMonthStartDate, ENT_QUOTES, 'UTF-8'); ?>" data-month-end="<?php echo htmlspecialchars($maxAppointmentDate, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($formValues['appointment_date'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                <div id="appointmentDateError" class="text-danger small mt-1 d-none" aria-live="polite"></div>
                            </div>
                            <div>
                                <label class="top-label">Time of Appointment <span class="required-asterisk">*</span></label>
                                <input type="time" id="appointmentTime" name="appointment_time" value="<?php echo htmlspecialchars($formValues['appointment_time'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                <div id="appointmentTimeError" class="text-danger small mt-1 d-none" aria-live="polite"></div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Purpose <span class="required-asterisk">*</span></label>
                                <input type="text" name="purpose" value="<?php echo htmlspecialchars($formValues['purpose'], ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                        </div>

                        <div class="agreement-row">
                                    <label class="agreement-text check-item">
                                        <input type="checkbox" required>I hereby certify that the above information is true and correct to the best of my knowledge and belief.
                                    </label>

                                    <button type="submit" class="submit-btn">SUBMIT</button>
                                </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <div
        id="appointmentFeedbackData"
        data-feedback-type="<?= htmlspecialchars($feedbackType, ENT_QUOTES, 'UTF-8') ?>"
        data-feedback-message="<?= htmlspecialchars($feedbackMessage, ENT_QUOTES, 'UTF-8') ?>"
        hidden
    ></div>

    <div class="modal fade" id="appointmentSuccessModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Appointment Submitted</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" id="appointmentSuccessMessage">Your appointment request has been submitted successfully.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../JS-Script-Files/Resident-End/dateFieldModal.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const form = document.querySelector("form");
            const submitBtn = form?.querySelector(".submit-btn");
            const appointmentDateInput = document.getElementById("appointmentDate");
            const appointmentDateError = document.getElementById("appointmentDateError");
            const appointmentTimeInput = document.getElementById("appointmentTime");
            const appointmentTimeError = document.getElementById("appointmentTimeError");
            const appointmentSubjectInput = document.getElementById("appointmentSubject");
            const appointmentSubjectOtherInput = document.getElementById("appointmentSubjectOther");
            const appointmentSubjectOtherError = document.getElementById("appointmentSubjectOtherError");
            const appointmentFeedbackData = document.getElementById("appointmentFeedbackData");
            const appointmentSuccessModalEl = document.getElementById("appointmentSuccessModal");
            const appointmentSuccessMessage = document.getElementById("appointmentSuccessMessage");
            if (!form || !submitBtn) return;

            const today = new Date();
            const todayIso = today.toISOString().split("T")[0];
            const todayDisplay = today.toLocaleDateString(undefined, {
                year: "numeric",
                month: "long",
                day: "numeric",
            });
            const currentYear = today.getFullYear();
            const currentMonth = today.getMonth();
            const currentMonthStartIso = appointmentDateInput?.dataset.monthStart || `${todayIso.slice(0, 7)}-01`;
            const endOfMonthIso = appointmentDateInput?.dataset.monthEnd || new Date(currentYear, currentMonth + 1, 0).toISOString().split("T")[0];
            const currentMonthDisplay = today.toLocaleDateString(undefined, {
                year: "numeric",
                month: "long",
            });

            const validateAppointmentDate = () => {
                if (!appointmentDateInput) return true;

                const value = String(appointmentDateInput.value || "").trim();
                const isTodayOrPast = value !== "" && value <= todayIso;
                const isOutsideCurrentMonth = value !== "" && (value < currentMonthStartIso || value > endOfMonthIso);

                if (isTodayOrPast) {
                    const msg = `Incorrect Input. Date must be after ${todayDisplay}`;
                    appointmentDateInput.setCustomValidity(msg);
                    if (appointmentDateError) {
                        appointmentDateError.textContent = msg;
                        appointmentDateError.classList.remove("d-none");
                    }
                    return false;
                }

                if (isOutsideCurrentMonth) {
                    const msg = `Incorrect Input. Date must be within ${currentMonthDisplay}`;
                    appointmentDateInput.setCustomValidity(msg);
                    if (appointmentDateError) {
                        appointmentDateError.textContent = msg;
                        appointmentDateError.classList.remove("d-none");
                    }
                    return false;
                }

                appointmentDateInput.setCustomValidity("");
                if (appointmentDateError) {
                    appointmentDateError.textContent = "";
                    appointmentDateError.classList.add("d-none");
                }
                return true;
            };

            const enforceCurrentMonthDate = () => {
                if (!appointmentDateInput) return;

                const value = String(appointmentDateInput.value || "").trim();
                if (value === "") return;

                if (value < currentMonthStartIso || value > endOfMonthIso) {
                    appointmentDateInput.value = "";
                }
            };

            const validateAppointmentTime = () => {
                if (!appointmentTimeInput) return true;

                const value = String(appointmentTimeInput.value || "").trim();
                const hasValue = value !== "";
                const minAllowed = "09:01";
                const maxAllowed = "16:59";
                const isOutOfRange = hasValue && (value < minAllowed || value > maxAllowed);

                if (isOutOfRange) {
                    const msg = "Incorrect Input. Time must be between 9:01 AM and 4:59 PM";
                    appointmentTimeInput.setCustomValidity(msg);
                    if (appointmentTimeError) {
                        appointmentTimeError.textContent = msg;
                        appointmentTimeError.classList.remove("d-none");
                    }
                    return false;
                }

                appointmentTimeInput.setCustomValidity("");
                if (appointmentTimeError) {
                    appointmentTimeError.textContent = "";
                    appointmentTimeError.classList.add("d-none");
                }
                return true;
            };

            const validateSubjectOther = () => {
                if (!appointmentSubjectInput || !appointmentSubjectOtherInput) return true;

                const isOther = appointmentSubjectInput.value === "other";
                const value = String(appointmentSubjectOtherInput.value || "").trim();

                appointmentSubjectOtherInput.disabled = !isOther;
                appointmentSubjectOtherInput.required = isOther;

                if (isOther && value === "") {
                    const msg = "Please specify the subject when Other is selected";
                    appointmentSubjectOtherInput.setCustomValidity(msg);
                    if (appointmentSubjectOtherError) {
                        appointmentSubjectOtherError.textContent = msg;
                        appointmentSubjectOtherError.classList.remove("d-none");
                    }
                    return false;
                }

                appointmentSubjectOtherInput.setCustomValidity("");
                if (appointmentSubjectOtherError) {
                    appointmentSubjectOtherError.textContent = "";
                    appointmentSubjectOtherError.classList.add("d-none");
                }
                return true;
            };

            const updateState = () => {
                validateSubjectOther();
                validateAppointmentDate();
                validateAppointmentTime();
                submitBtn.disabled = !form.checkValidity();
            };

            form.addEventListener("input", updateState);
            form.addEventListener("change", updateState);
            appointmentDateInput?.addEventListener("input", updateState);
            appointmentDateInput?.addEventListener("change", () => {
                enforceCurrentMonthDate();
                updateState();
            });
            appointmentDateInput?.addEventListener("keyup", validateAppointmentDate);
            appointmentDateInput?.addEventListener("blur", validateAppointmentDate);
            appointmentDateInput?.addEventListener("invalid", validateAppointmentDate);
            appointmentTimeInput?.addEventListener("input", updateState);
            appointmentTimeInput?.addEventListener("change", updateState);
            appointmentTimeInput?.addEventListener("keyup", validateAppointmentTime);
            appointmentTimeInput?.addEventListener("blur", validateAppointmentTime);
            appointmentTimeInput?.addEventListener("invalid", validateAppointmentTime);
            appointmentSubjectInput?.addEventListener("input", updateState);
            appointmentSubjectInput?.addEventListener("change", updateState);
            appointmentSubjectOtherInput?.addEventListener("input", updateState);
            appointmentSubjectOtherInput?.addEventListener("change", updateState);
            appointmentSubjectOtherInput?.addEventListener("blur", validateSubjectOther);
            appointmentSubjectOtherInput?.addEventListener("invalid", validateSubjectOther);
            form.addEventListener("submit", (e) => {
                const okSubjectOther = validateSubjectOther();
                const okDate = validateAppointmentDate();
                const okTime = validateAppointmentTime();
                if (!okSubjectOther || !okDate || !okTime) {
                    e.preventDefault();
                    if (!okSubjectOther) appointmentSubjectOtherInput?.focus();
                    else if (!okDate) appointmentDateInput?.focus();
                    else appointmentTimeInput?.focus();
                }
            });

            const feedbackType = String(appointmentFeedbackData?.dataset.feedbackType || "").trim();
            const feedbackMessage = String(appointmentFeedbackData?.dataset.feedbackMessage || "").trim();
            if (feedbackType === "success" && feedbackMessage !== "" && appointmentSuccessModalEl && window.bootstrap) {
                if (appointmentSuccessMessage) {
                    appointmentSuccessMessage.textContent = feedbackMessage;
                }
                const successModal = bootstrap.Modal.getOrCreateInstance(appointmentSuccessModalEl, {
                    backdrop: "static",
                    keyboard: false,
                });
                successModal.show();
            }

            updateState();
        });
    </script>
</body>
</html>
