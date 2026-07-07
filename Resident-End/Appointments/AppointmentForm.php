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
require_once __DIR__ . "/../../PhpFiles/General/appointmentOfficialSchedules.php";
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
$officialScheduleMap = apos_fetch_schedule_map(
    $conn,
    array_values(array_filter(array_map(static function (array $member): string {
        return trim((string)($member['user_id'] ?? ''));
    }, $councilMemberOptions), static function (string $userId): bool {
        return $userId !== '';
    })),
    $appointmentSettings
);
$bookedSlotMap = apos_fetch_booked_slots_map(
    $conn,
    array_values(array_filter(array_map(static function (array $member): string {
        return trim((string)($member['user_id'] ?? ''));
    }, $councilMemberOptions), static function (string $userId): bool {
        return $userId !== '';
    }))
);
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
        #appointmentDate.form-control {
            background-color: #ffffff !important;
            color: #212529;
            -webkit-appearance: none;
            appearance: none;
        }
        #appointmentDate + .resident-date-proxy-wrap .resident-date-proxy {
            background: #ffffff !important;
            border-color: #ced4da !important;
            color: #212529 !important;
            box-shadow: none !important;
            -webkit-text-fill-color: #212529;
        }
        #appointmentDate + .resident-date-proxy-wrap .resident-date-proxy[readonly],
        #appointmentDate + .resident-date-proxy-wrap .resident-date-proxy:hover,
        #appointmentDate + .resident-date-proxy-wrap .resident-date-proxy:focus {
            background: #ffffff !important;
            color: #212529 !important;
            -webkit-text-fill-color: #212529;
        }
        #appointmentDate + .resident-date-proxy-wrap .resident-date-proxy-icon {
            color: #6b7c93 !important;
        }
        input[type="date"].form-control::-webkit-date-and-time-value,
        input[type="time"].form-control::-webkit-date-and-time-value {
            text-align: left;
        }
        input[type="date"].form-control::-webkit-calendar-picker-indicator,
        input[type="time"].form-control::-webkit-calendar-picker-indicator {
            opacity: 1;
        }
        .submit-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            min-width: 190px;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }
        .submit-btn:not(:disabled):hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(254, 153, 60, 0.22);
        }
        .submit-btn.is-loading {
            pointer-events: none;
        }
        .submit-btn-spinner {
            width: 1rem;
            height: 1rem;
            border: 2px solid rgba(255, 255, 255, 0.35);
            border-top-color: #ffffff;
            border-radius: 999px;
            display: none;
            flex: 0 0 auto;
            animation: appointmentSubmitSpin 0.8s linear infinite;
        }
        .submit-btn.is-loading .submit-btn-spinner {
            display: inline-block;
        }
        @keyframes appointmentSubmitSpin {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
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
                                <label class="top-label">Date of Appointment <span class="required-asterisk">*</span></label>
                                <input type="date" class="form-control" id="appointmentDate" name="appointment_date" min="<?php echo htmlspecialchars($minAppointmentDate, ENT_QUOTES, 'UTF-8'); ?>" max="<?php echo htmlspecialchars($maxAppointmentDate, ENT_QUOTES, 'UTF-8'); ?>" data-year-end="<?php echo htmlspecialchars($maxAppointmentDate, ENT_QUOTES, 'UTF-8'); ?>" data-date-disabled-weekdays="<?php echo htmlspecialchars(implode(',', $disabledWeekdays), ENT_QUOTES, 'UTF-8'); ?>" data-date-disabled-dates="<?php echo htmlspecialchars(implode(',', $unavailableDates), ENT_QUOTES, 'UTF-8'); ?>" data-available-weekdays="<?php echo htmlspecialchars($availableWeekdayLabels, ENT_QUOTES, 'UTF-8'); ?>" data-date-modal-style="calendar" placeholder="Select date" value="<?php echo htmlspecialchars($formValues['appointment_date'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                <div id="appointmentDateError" class="text-danger small mt-1 d-none" aria-live="polite"></div>
                            </div>
                            <div>
                                <label class="top-label">Time of Appointment <span class="required-asterisk">*</span></label>
                                <select class="form-select" id="appointmentTime" name="appointment_time" required>
                                    <option value="">Select allotted time</option>
                                </select>
                                <div id="appointmentTimeError" class="text-danger small mt-1 d-none" aria-live="polite"></div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Meeting Location</label>
                                <input type="text" class="form-control" id="appointmentMeetingLocation" value="" readonly>
                                <div id="appointmentLocationHelp" class="text-muted small mt-1">Choose a council member and date to load the official meeting location for that schedule.</div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Subject of Appointment <span class="required-asterisk">*</span></label>
                                <select class="form-select" name="subject" id="appointmentSubject" required>
                                    <option value="">Select</option>
                                    <option value="follow_up" <?php echo $formValues['subject'] === 'follow_up' ? 'selected' : ''; ?>>Follow-up Concern</option>
                                    <option value="consultation" <?php echo $formValues['subject'] === 'consultation' ? 'selected' : ''; ?>>Consultation</option>
                                    <option value="event_coordination" <?php echo $formValues['subject'] === 'event_coordination' ? 'selected' : ''; ?>>Event Coordination</option>
                                    <option value="other" <?php echo $formValues['subject'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row<?php echo $formValues['subject'] === 'other' ? '' : ' d-none'; ?>" id="appointmentSubjectOtherWrap">
                            <div class="full-width">
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

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Purpose</label>
                                <textarea class="form-control" name="purpose" rows="4"><?php echo htmlspecialchars($formValues['purpose'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                        </div>

                        <div class="agreement-row">
                            <label class="agreement-text check-item">
                                <input type="checkbox" required>I hereby certify that the above information is true and correct to the best of my knowledge and belief.
                            </label>

                            <button
                                type="submit"
                                class="submit-btn"
                                data-default-label="SUBMIT"
                                data-loading-label="Submitting..."
                                <?php echo $hasAppointmentAvailability ? '' : 'disabled'; ?>
                            >
                                <span class="submit-btn-label">SUBMIT</span>
                                <span class="submit-btn-spinner" aria-hidden="true"></span>
                            </button>
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
                    <h5 class="modal-title">Appointment Confirmed</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" id="appointmentSuccessMessage">Your appointment has been confirmed successfully.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../JS-Script-Files/Resident-End/dateFieldModal.js?v=20260707-date-proxy-white"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const form = document.querySelector("form");
            const submitBtn = form?.querySelector(".submit-btn");
            const submitBtnLabel = submitBtn?.querySelector(".submit-btn-label");
            const appointmentDateInput = document.getElementById("appointmentDate");
            const appointmentDateError = document.getElementById("appointmentDateError");
            const appointmentTimeInput = document.getElementById("appointmentTime");
            const appointmentTimeError = document.getElementById("appointmentTimeError");
            const appointmentMeetingLocationInput = document.getElementById("appointmentMeetingLocation");
            const appointmentLocationHelp = document.getElementById("appointmentLocationHelp");
            const appointmentCouncilMemberInput = document.getElementById("appointmentCouncilMember");
            const appointmentSubjectInput = document.getElementById("appointmentSubject");
            const appointmentSubjectOtherWrap = document.getElementById("appointmentSubjectOtherWrap");
            const appointmentSubjectOtherInput = document.getElementById("appointmentSubjectOther");
            const appointmentSubjectOtherError = document.getElementById("appointmentSubjectOtherError");
            const appointmentFeedbackData = document.getElementById("appointmentFeedbackData");
            const appointmentSuccessModalEl = document.getElementById("appointmentSuccessModal");
            const appointmentSuccessMessage = document.getElementById("appointmentSuccessMessage");
            const officialScheduleMap = <?= json_encode($officialScheduleMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const bookedSlotMap = <?= json_encode($bookedSlotMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const appointmentGlobalScheduleConfig = <?= json_encode([
                'startTime' => aps_schedule_start_time(),
                'endTime' => aps_schedule_end_time(),
                'slotIntervalMinutes' => (int)$slotIntervalMinutes,
                'lunchBreak' => $lunchBreakEnabled ? [
                    'start' => (string)($appointmentSettings['lunch_start_time'] ?? '12:00'),
                    'end' => (string)($appointmentSettings['lunch_end_time'] ?? '13:00'),
                ] : null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const hasConfiguredAppointmentAvailability = <?= $hasAppointmentAvailability ? 'true' : 'false' ?>;
            if (!form || !submitBtn) return;
            let isSubmitting = false;
            const defaultSubmitLabel = String(submitBtn.dataset.defaultLabel || "SUBMIT").trim() || "SUBMIT";
            const loadingSubmitLabel = String(submitBtn.dataset.loadingLabel || "Submitting...").trim() || "Submitting...";

            const setSubmittingState = (submitting) => {
                isSubmitting = submitting === true;
                form.setAttribute("aria-busy", isSubmitting ? "true" : "false");
                submitBtn.classList.toggle("is-loading", isSubmitting);
                submitBtn.disabled = isSubmitting || submitBtn.disabled;
                if (submitBtnLabel) {
                    submitBtnLabel.textContent = isSubmitting ? loadingSubmitLabel : defaultSubmitLabel;
                }
            };

            const today = new Date();
            const todayDisplay = today.toLocaleDateString(undefined, {
                year: "numeric",
                month: "long",
                day: "numeric",
            });
            const currentBookingMoment = () => {
                const now = new Date();
                return {
                    isoDate: `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}-${String(now.getDate()).padStart(2, "0")}`,
                    minutes: (now.getHours() * 60) + now.getMinutes(),
                };
            };
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

            const toMinutes = (value) => {
                const match = String(value || "").trim().match(/^(\d{2}):(\d{2})$/);
                if (!match) return null;
                return (Number(match[1]) * 60) + Number(match[2]);
            };

            const formatTimeLabel = (value) => {
                const minutes = toMinutes(value);
                if (minutes === null) return String(value || "").trim() || "-";
                const hour24 = Math.floor(minutes / 60);
                const minute = minutes % 60;
                const hour12 = hour24 % 12 === 0 ? 12 : hour24 % 12;
                const suffix = hour24 >= 12 ? "PM" : "AM";
                return `${String(hour12).padStart(2, "0")}:${String(minute).padStart(2, "0")} ${suffix}`;
            };

            const scheduleDayForSelection = () => {
                const officialUserId = String(appointmentCouncilMemberInput?.value || "").trim();
                const isoDate = String(appointmentDateInput?.value || "").trim();
                const schedule = officialScheduleMap[officialUserId] || null;
                const parsedDate = parseIsoDate(isoDate);
                if (!schedule || !parsedDate) {
                    return null;
                }
                const weekday = String(parsedDate.getDay());
                return schedule[weekday] || schedule[Number(weekday)] || null;
            };

            const bookedAppointmentIdsFor = (officialUserId, isoDate, timeValue) => {
                const officialKey = String(officialUserId || "").trim();
                const dateKey = String(isoDate || "").trim();
                const timeKey = String(timeValue || "").trim();
                const officialBookings = bookedSlotMap[officialKey] || null;
                if (!officialBookings || !dateKey || !timeKey) {
                    return [];
                }
                const dateBookings = officialBookings[dateKey] || null;
                if (!dateBookings) {
                    return [];
                }
                return Array.isArray(dateBookings[timeKey]) ? dateBookings[timeKey] : [];
            };

            const buildOfficialSlots = () => {
                const officialUserId = String(appointmentCouncilMemberInput?.value || "").trim();
                const isoDate = String(appointmentDateInput?.value || "").trim();
                const dayEntry = scheduleDayForSelection();
                if (!dayEntry || dayEntry.enabled !== true || disabledDates.has(isoDate)) {
                    return { dayEntry: null, slots: [] };
                }

                const parsedDate = parseIsoDate(isoDate);
                const weekday = parsedDate ? String(parsedDate.getDay()) : "";
                if (weekday !== "" && disabledWeekdays.has(weekday)) {
                    return { dayEntry: null, slots: [] };
                }

                const startMinutes = Math.max(
                    toMinutes(dayEntry.start_time || "") ?? 0,
                    toMinutes(appointmentGlobalScheduleConfig.startTime || "") ?? 0
                );
                const endMinutes = Math.min(
                    toMinutes(dayEntry.end_time || "") ?? 0,
                    toMinutes(appointmentGlobalScheduleConfig.endTime || "") ?? 0
                );
                const interval = Math.max(5, Number(appointmentGlobalScheduleConfig.slotIntervalMinutes || 30));
                if (!Number.isFinite(startMinutes) || !Number.isFinite(endMinutes) || startMinutes >= endMinutes) {
                    return { dayEntry: null, slots: [] };
                }

                const lunchBreak = appointmentGlobalScheduleConfig.lunchBreak || null;
                const lunchStart = lunchBreak ? toMinutes(lunchBreak.start || "") : null;
                const lunchEnd = lunchBreak ? toMinutes(lunchBreak.end || "") : null;
                const currentBookingState = currentBookingMoment();
                const slots = [];

                for (let current = startMinutes; current <= endMinutes; current += interval) {
                    const slotEnd = current + interval;
                    if (
                        lunchStart !== null
                        && lunchEnd !== null
                        && current < lunchEnd
                        && slotEnd > lunchStart
                    ) {
                        continue;
                    }

                    const hours = Math.floor(current / 60);
                    const minutes = current % 60;
                    const value = `${String(hours).padStart(2, "0")}:${String(minutes).padStart(2, "0")}`;
                    if (isoDate === currentBookingState.isoDate && current <= currentBookingState.minutes) {
                        continue;
                    }
                    if (bookedAppointmentIdsFor(officialUserId, isoDate, value).length > 0) {
                        continue;
                    }
                    slots.push({ value, label: formatTimeLabel(value) });
                }

                return { dayEntry, slots };
            };

            const syncAppointmentAvailability = () => {
                if (!appointmentTimeInput) return;

                const officialUserId = String(appointmentCouncilMemberInput?.value || "").trim();
                const isoDate = String(appointmentDateInput?.value || "").trim();
                const selectedTime = String(appointmentTimeInput.value || "").trim();
                const { dayEntry, slots } = buildOfficialSlots();

                appointmentTimeInput.innerHTML = '<option value="">Select allotted time</option>';
                slots.forEach((slot) => {
                    const option = document.createElement("option");
                    option.value = slot.value;
                    option.textContent = slot.label;
                    if (selectedTime !== "" && selectedTime === slot.value) {
                        option.selected = true;
                    }
                    appointmentTimeInput.appendChild(option);
                });
                if (selectedTime !== "" && !slots.some((slot) => slot.value === selectedTime)) {
                    appointmentTimeInput.value = "";
                }

                if (appointmentMeetingLocationInput) {
                    appointmentMeetingLocationInput.value = dayEntry && String(dayEntry.meeting_location || "").trim()
                        ? String(dayEntry.meeting_location || "").trim()
                        : "";
                }
                if (appointmentLocationHelp) {
                    if (officialUserId === "") {
                        appointmentLocationHelp.textContent = "Choose a council member and date to load the official meeting location for that schedule.";
                    } else if (isoDate === "") {
                        appointmentLocationHelp.textContent = "Choose an appointment date to load the weekly meeting location for the selected council member.";
                    } else if (!dayEntry) {
                        appointmentLocationHelp.textContent = "That council member is not available on the selected date.";
                    } else if (slots.length === 0) {
                        appointmentLocationHelp.textContent = "No remaining appointment times are available for that council member on the selected date.";
                    } else {
                        appointmentLocationHelp.textContent = "Meeting location is pulled from the weekly schedule of the selected council member.";
                    }
                }
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
                    const msg = `Incorrect Input. Date must be on or after ${todayDisplay}`;
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

                const officialUserId = String(appointmentCouncilMemberInput?.value || "").trim();
                if (officialUserId !== "") {
                    const { dayEntry, slots } = buildOfficialSlots();
                    if (!dayEntry) {
                        const msg = "The selected council member is not available on that date";
                        appointmentDateInput.setCustomValidity(msg);
                        if (appointmentDateError) {
                            appointmentDateError.textContent = msg;
                            appointmentDateError.classList.remove("d-none");
                        }
                        return false;
                    }
                    if (slots.length === 0) {
                        const msg = "No remaining appointment times are available for the selected council member on that date";
                        appointmentDateInput.setCustomValidity(msg);
                        if (appointmentDateError) {
                            appointmentDateError.textContent = msg;
                            appointmentDateError.classList.remove("d-none");
                        }
                        return false;
                    }
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
                const isInvalidSlot = hasValue && !buildOfficialSlots().slots.some((slot) => slot.value === value);

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

                appointmentSubjectOtherWrap?.classList.toggle("d-none", !isOther);
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
                if (!isOther) {
                    appointmentSubjectOtherInput.value = "";
                }
                return true;
            };

            const updateState = () => {
                if (isSubmitting) {
                    submitBtn.disabled = true;
                    return;
                }
                syncAppointmentAvailability();
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
                    setSubmittingState(false);
                    return;
                }

                e.preventDefault();
                setSubmittingState(true);
                window.setTimeout(() => {
                    HTMLFormElement.prototype.submit.call(form);
                }, 120);
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

            syncAppointmentAvailability();
            setSubmittingState(false);
            updateState();
        });
    </script>
</body>
</html>
