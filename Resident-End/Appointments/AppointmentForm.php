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
require_once __DIR__ . "/../../PhpFiles/General/appointmentCouncilMembers.php";
require_once __DIR__ . "/../../PhpFiles/General/appointmentSettings.php";
require_once __DIR__ . "/../../PhpFiles/General/appointmentTimeSlots.php";

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
    'official_user_id' => trim((string)($_GET['official_user_id'] ?? '')),
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
$appointmentSettings = aps_settings_load($conn);
$bookingLimits = aps_booking_date_limits($appointmentSettings);
$minAppointmentDate = (string)($bookingLimits['min_date'] ?? '');
$maxAppointmentDate = (string)($bookingLimits['max_date'] ?? '');
$councilMemberOptions = apcm_fetch_council_members($conn);
$hasCouncilMemberOptions = $councilMemberOptions !== [];
$appointmentTimeSlots = ats_allotted_times($appointmentSettings);
$availableWeekdayLabels = aps_weekdays_label($appointmentSettings['available_weekdays'] ?? []);
$closedWeekdays = aps_closed_weekdays($appointmentSettings);
$closedWeekdayLabels = aps_closed_weekdays_label($appointmentSettings);
$disabledWeekdays = aps_disabled_weekdays($appointmentSettings);
$unavailableDates = aps_normalize_unavailable_dates($appointmentSettings['unavailable_dates'] ?? []);
$firstAvailableAppointmentDate = aps_first_available_booking_date($appointmentSettings);
$hasAvailableAppointmentDates = !empty($bookingLimits['has_window']) && $firstAvailableAppointmentDate !== null;
$hasAppointmentAvailability = $hasCouncilMemberOptions && $hasAvailableAppointmentDates && $appointmentTimeSlots !== [];
$slotIntervalMinutes = (int)($appointmentSettings['slot_interval_minutes'] ?? 30);
$bookingWindowDays = (int)($appointmentSettings['booking_window_days'] ?? 365);
$lunchBreakEnabled = aps_has_lunch_break($appointmentSettings);
$lunchBreakLabel = aps_lunch_break_label($appointmentSettings);
$unavailableDatesCount = count($unavailableDates);
$unavailableDatesSummary = aps_unavailable_dates_label($unavailableDates, 4);
$firstAvailableAppointmentDateLabel = $firstAvailableAppointmentDate !== null
    ? date('F j, Y', strtotime($firstAvailableAppointmentDate))
    : 'No available date configured';
$maxAppointmentDateLabel = $maxAppointmentDate !== '' ? date('F j, Y', strtotime($maxAppointmentDate)) : 'No maximum date available';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../../Images/favicon_sanjose.png?v=20260211">
    <title>Appointment Form</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <link rel="stylesheet" href="../../CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <link rel="stylesheet" href="../../CSS-Styles/Resident-End-CSS/applicationForms.css">
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
            max-width: 1180px;
            margin-left: auto;
            margin-right: auto;
        }
        #div-mainDisplay .page-form {
            max-width: 1180px;
            margin: 0 auto;
            padding-bottom: 48px;
        }
        #div-mainDisplay .section-title,
        #div-mainDisplay .section-kicker {
            max-width: 1180px;
            margin-left: auto;
            margin-right: auto;
        }
        .section-kicker {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.35rem 0.8rem;
            border-radius: 999px;
            border: 1px solid #f2d3b8;
            background: #fff7ef;
            color: #a35300;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .appointment-guide {
            max-width: 1180px;
            margin: 1.1rem auto 1.5rem;
            border: 1px solid #f2d9c2;
            border-radius: 16px;
            background: linear-gradient(180deg, #fffaf6 0%, #fff7ef 100%);
            padding: 1rem 1.15rem;
        }
        .appointment-guide-title {
            font-weight: 700;
            color: #7c3f00;
            margin-bottom: 0.35rem;
        }
        .appointment-guide-text {
            color: #5f6b7a;
            margin-bottom: 0;
            font-size: 0.95rem;
        }
        h1.form-title {
            font-size: 2.8rem !important;
            font-weight: 700;
        }
        h2.section-title {
            font-size: 1.35rem;
            font-weight: 600;
            margin-top: 12px;
            margin-bottom: 24px;
        }
        .form-divider {
            max-width: 1180px;
            margin: 1.75rem auto 1.25rem;
            border-color: #ebe5df;
        }
        input[type="date"].form-control,
        input[type="time"].form-control {
            background-color: #ffffff !important;
            color: #212529;
        }
        input[type="date"].form-control::-webkit-date-and-time-value,
        input[type="time"].form-control::-webkit-date-and-time-value {
            text-align: left;
        }
        input[type="date"].form-control::-webkit-calendar-picker-indicator,
        input[type="time"].form-control::-webkit-calendar-picker-indicator {
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="d-flex min-vh-100">

        <?php include __DIR__ . '/../includes/resident_sidebar.php'; ?>

        <main id="div-mainDisplay" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 pb-md-5 pt-md-0">
            <div class="position-relative d-flex align-items-center justify-content-center mb-2 pt-4">
                <a href="<?= htmlspecialchars(appUrl('Resident-End/Appointments/AppointmentsLandingPage.php')) ?>" class="back-link d-inline-flex align-items-center text-decoration-none text-dark m-0 position-absolute start-0">
                    <i class="bi bi-arrow-left-short fs-3"></i>
                </a>
                <h1 class="form-title m-0">Appointment Form</h1>
            </div>
            <p class="form-subtitle">All fields marked with <span class="required-asterisk">*</span> are required</p>

            <div class="appointment-guide">
                <div class="appointment-guide-title">Before You Submit</div>
                <p class="appointment-guide-text">Choose the barangay council member you want to meet, then pick one of the available dates on <strong><?= htmlspecialchars($availableWeekdayLabels, ENT_QUOTES, 'UTF-8') ?></strong>. Appointments use <strong><?= htmlspecialchars((string)$slotIntervalMinutes, ENT_QUOTES, 'UTF-8') ?>-minute</strong> time allotments<?php if ($lunchBreakEnabled): ?> with lunch time blocked from <strong><?= htmlspecialchars($lunchBreakLabel, ENT_QUOTES, 'UTF-8') ?></strong><?php endif; ?> and can be scheduled up to <strong><?= htmlspecialchars((string)$bookingWindowDays, ENT_QUOTES, 'UTF-8') ?> days ahead</strong>, capped through <strong><?= htmlspecialchars($maxAppointmentDateLabel, ENT_QUOTES, 'UTF-8') ?></strong>. If you select <strong>Other</strong> as the subject, include a short specific description.</p>
                <?php if ($closedWeekdays !== []): ?>
                    <p class="appointment-guide-text mt-2">Weekly appointment closures are set for <strong><?= htmlspecialchars($closedWeekdayLabels, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
                <?php endif; ?>
                <?php if ($unavailableDatesCount > 0): ?>
                    <p class="appointment-guide-text mt-2">Blocked dates are also disabled in the calendar, including <strong><?= htmlspecialchars($unavailableDatesSummary, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
                <?php endif; ?>
                <?php if ($hasAvailableAppointmentDates): ?>
                    <p class="appointment-guide-text mt-2">Earliest available date: <strong><?= htmlspecialchars($firstAvailableAppointmentDateLabel, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
                <?php else: ?>
                    <p class="appointment-guide-text mt-2 text-danger">No appointment dates are currently available based on the saved appointment settings.</p>
                <?php endif; ?>
            </div>

            <form class="page-form" method="POST" action="<?= htmlspecialchars(appUrl('/PhpFiles/Resident-End/submitAppointment.php'), ENT_QUOTES, 'UTF-8') ?>">
                        <?= csrfTokenField() ?>
                        <input type="hidden" name="action" value="submit_appointment">
                        <?php if ($feedbackMessage !== '' && $feedbackType !== 'success'): ?>
                            <div class="alert alert-<?php echo $feedbackType === 'success' ? 'success' : 'danger'; ?>" role="alert">
                                <?php echo htmlspecialchars($feedbackMessage, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>
                        <h2 class="section-title text-center text-dark">Resident Information</h2>

                        <div class="form-row pt-0">
                            <div>
                                <label class="top-label">Last Name <span class="required-asterisk">*</span></label>
                                <input type="text" class="form-control" name="last_name" value="<?php echo $lastName; ?>" readonly>
                            </div>
                            <div>
                                <label class="top-label">First Name <span class="required-asterisk">*</span></label>
                                <input type="text" class="form-control" name="first_name" value="<?php echo $firstName; ?>" readonly>
                            </div>
                            <div>
                                <label class="top-label">Middle Name</label>
                                <input type="text" class="form-control" name="middle_name" value="<?php echo $middleName; ?>" readonly>
                            </div>
                            <div>
                                <label class="top-label">Suffix</label>
                                <select name="suffix_name" class="form-select text-bg-light" readonly value="<?php echo htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8'); ?>" disabled>
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
                                <input type="text" class="form-control" name="contact_number" value="<?php echo $contactNumber; ?>" readonly>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                            <label class="top-label">Address <span class="required-asterisk">*</span></label>
                                <input type="text" class="form-control" name="full_address_display" readonly value="<?php echo htmlspecialchars($fullAddress, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="unitNumber" value="<?php echo htmlspecialchars($unitNumber, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="houseNumber" value="<?php echo htmlspecialchars($houseNumber, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="streetName" value="<?php echo htmlspecialchars($streetName, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>

                        <hr class="form-divider">

                        <h2 class="section-title text-center text-dark">Appointment Details</h2>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Barangay Council Member <span class="required-asterisk">*</span></label>
                                <select
                                    class="form-select"
                                    name="official_user_id"
                                    id="appointmentCouncilMember"
                                    required
                                    <?php echo $hasCouncilMemberOptions ? '' : 'disabled'; ?>
                                >
                                    <option value="">Select council member</option>
                                    <?php foreach ($councilMemberOptions as $member): ?>
                                        <option
                                            value="<?php echo htmlspecialchars((string)($member['user_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            <?php echo $formValues['official_user_id'] === (string)($member['user_id'] ?? '') ? 'selected' : ''; ?>
                                        >
                                            <?php echo htmlspecialchars((string)($member['option_label'] ?? $member['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($hasCouncilMemberOptions): ?>
                                    <div class="text-muted small mt-1">Appointments are routed to currently serving barangay council members only.</div>
                                <?php else: ?>
                                    <div class="text-danger small mt-1">No active barangay council members are currently available for appointments. Please contact the barangay office.</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-row two-col-row">
                            <div>
                                <label class="top-label">Subject of Appointment <span class="required-asterisk">*</span></label>
                                <select class="form-select" name="subject" id="appointmentSubject" required>
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
                                    class="form-control"
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
                                <input type="date" class="form-control" id="appointmentDate" name="appointment_date" min="<?php echo htmlspecialchars($minAppointmentDate, ENT_QUOTES, 'UTF-8'); ?>" max="<?php echo htmlspecialchars($maxAppointmentDate, ENT_QUOTES, 'UTF-8'); ?>" data-year-end="<?php echo htmlspecialchars($maxAppointmentDate, ENT_QUOTES, 'UTF-8'); ?>" data-date-disabled-weekdays="<?php echo htmlspecialchars(implode(',', $disabledWeekdays), ENT_QUOTES, 'UTF-8'); ?>" data-date-disabled-dates="<?php echo htmlspecialchars(implode(',', $unavailableDates), ENT_QUOTES, 'UTF-8'); ?>" data-available-weekdays="<?php echo htmlspecialchars($availableWeekdayLabels, ENT_QUOTES, 'UTF-8'); ?>" data-date-modal-style="calendar" placeholder="Select date" value="<?php echo htmlspecialchars($formValues['appointment_date'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                <div id="appointmentDateError" class="text-danger small mt-1 d-none" aria-live="polite"></div>
                            </div>
                            <div>
                                <label class="top-label">Time of Appointment <span class="required-asterisk">*</span></label>
                                <select class="form-select" id="appointmentTime" name="appointment_time" required>
                                    <option value="">Select allotted time</option>
                                    <?php foreach ($appointmentTimeSlots as $slotValue => $slotLabel): ?>
                                        <option value="<?php echo htmlspecialchars($slotValue, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $formValues['appointment_time'] === $slotValue ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($slotLabel, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="appointmentTimeError" class="text-danger small mt-1 d-none" aria-live="polite"></div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Purpose <span class="required-asterisk">*</span></label>
                                <textarea class="form-control" name="purpose" rows="4" required><?php echo htmlspecialchars($formValues['purpose'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                        </div>

                        <div class="agreement-row">
                            <label class="agreement-text check-item">
                                <input type="checkbox" required>I hereby certify that the above information is true and correct to the best of my knowledge and belief.
                            </label>

                            <button type="submit" class="submit-btn" <?php echo $hasAppointmentAvailability ? '' : 'disabled'; ?>>SUBMIT</button>
                        </div>
            </form>
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
            const appointmentCouncilMemberInput = document.getElementById("appointmentCouncilMember");
            const appointmentSubjectInput = document.getElementById("appointmentSubject");
            const appointmentSubjectOtherInput = document.getElementById("appointmentSubjectOther");
            const appointmentSubjectOtherError = document.getElementById("appointmentSubjectOtherError");
            const appointmentFeedbackData = document.getElementById("appointmentFeedbackData");
            const appointmentSuccessModalEl = document.getElementById("appointmentSuccessModal");
            const appointmentSuccessMessage = document.getElementById("appointmentSuccessMessage");
            const allottedAppointmentTimes = new Set(<?= json_encode(array_keys($appointmentTimeSlots), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
            const hasConfiguredAppointmentAvailability = <?= $hasAppointmentAvailability ? 'true' : 'false' ?>;
            if (!form || !submitBtn) return;

            const today = new Date();
            const todayDisplay = today.toLocaleDateString(undefined, {
                year: "numeric",
                month: "long",
                day: "numeric",
            });
            const minAllowedIso = String(appointmentDateInput?.min || "").trim();
            const maxAllowedIso = String(appointmentDateInput?.max || "").trim();
            const availableWeekdaysLabel = String(appointmentDateInput?.dataset.availableWeekdays || "").trim();
            const disabledWeekdays = new Set(
                String(appointmentDateInput?.dataset.dateDisabledWeekdays || "")
                    .split(",")
                    .map((value) => value.trim())
                    .filter((value) => value !== "")
            );
            const disabledDates = new Set(
                String(appointmentDateInput?.dataset.dateDisabledDates || "")
                    .split(",")
                    .map((value) => value.trim())
                    .filter((value) => value !== "")
            );

            const parseIsoDate = (value) => {
                const text = String(value || "").trim();
                const match = text.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                if (!match) return null;
                return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
            };

            const validateAppointmentDate = () => {
                if (!appointmentDateInput) return true;

                const value = String(appointmentDateInput.value || "").trim();
                const parsedDate = parseIsoDate(value);
                const weekday = parsedDate ? String(parsedDate.getDay()) : "";
                const isBeforeMin = value !== "" && minAllowedIso !== "" && value < minAllowedIso;
                const isAfterMax = value !== "" && maxAllowedIso !== "" && value > maxAllowedIso;
                const isUnavailableDate = value !== "" && disabledDates.has(value);
                const isUnavailableWeekday = value !== "" && weekday !== "" && disabledWeekdays.has(weekday);

                if (isBeforeMin) {
                    const msg = `Incorrect Input. Date must be after ${todayDisplay}`;
                    appointmentDateInput.setCustomValidity(msg);
                    if (appointmentDateError) {
                        appointmentDateError.textContent = msg;
                        appointmentDateError.classList.remove("d-none");
                    }
                    return false;
                }

                if (isAfterMax) {
                    const msg = "Incorrect Input. Date is outside the current booking window";
                    appointmentDateInput.setCustomValidity(msg);
                    if (appointmentDateError) {
                        appointmentDateError.textContent = msg;
                        appointmentDateError.classList.remove("d-none");
                    }
                    return false;
                }

                if (isUnavailableWeekday) {
                    const msg = availableWeekdaysLabel
                        ? `Incorrect Input. Appointments are only available on ${availableWeekdaysLabel}`
                        : "Incorrect Input. The selected date is not available";
                    appointmentDateInput.setCustomValidity(msg);
                    if (appointmentDateError) {
                        appointmentDateError.textContent = msg;
                        appointmentDateError.classList.remove("d-none");
                    }
                    return false;
                }

                if (isUnavailableDate) {
                    const msg = "Incorrect Input. The selected date is unavailable for appointments";
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

            const enforceCurrentYearDate = () => {
                if (!appointmentDateInput) return;

                const value = String(appointmentDateInput.value || "").trim();
                if (value === "") return;

                const parsedDate = parseIsoDate(value);
                const weekday = parsedDate ? String(parsedDate.getDay()) : "";
                if (
                    (minAllowedIso !== "" && value < minAllowedIso)
                    || (maxAllowedIso !== "" && value > maxAllowedIso)
                    || disabledDates.has(value)
                    || (weekday !== "" && disabledWeekdays.has(weekday))
                ) {
                    appointmentDateInput.value = "";
                }
            };

            const validateAppointmentTime = () => {
                if (!appointmentTimeInput) return true;

                const value = String(appointmentTimeInput.value || "").trim();
                const hasValue = value !== "";
                const isInvalidSlot = hasValue && !allottedAppointmentTimes.has(value);

                if (isInvalidSlot) {
                    const msg = "Incorrect Input. Please choose one of the allotted appointment times";
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
                const hasCouncilMemberSelection = !appointmentCouncilMemberInput || !appointmentCouncilMemberInput.disabled;
                const hasAppointmentWindow = minAllowedIso !== "" && maxAllowedIso !== "";
                submitBtn.disabled = !hasConfiguredAppointmentAvailability || !hasCouncilMemberSelection || !hasAppointmentWindow || !form.checkValidity();
            };

            form.addEventListener("input", updateState);
            form.addEventListener("change", updateState);
            appointmentCouncilMemberInput?.addEventListener("input", updateState);
            appointmentCouncilMemberInput?.addEventListener("change", updateState);
            appointmentDateInput?.addEventListener("input", updateState);
            appointmentDateInput?.addEventListener("change", () => {
                enforceCurrentYearDate();
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
