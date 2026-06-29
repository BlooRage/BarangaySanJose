<?php
require_once __DIR__ . "/../../PhpFiles/General/connection.php";
require_once __DIR__ . "/../../PhpFiles/General/appointmentCouncilMembers.php";
require_once __DIR__ . "/../../PhpFiles/General/appointmentSettings.php";
require_once __DIR__ . "/../../PhpFiles/General/appointmentOfficialSchedules.php";
require_once __DIR__ . "/../../PhpFiles/General/appointmentTimeSlots.php";
require_once __DIR__ . "/../includes/admin_guard.php";

$appointmentCurrentUserId = trim((string)($_SESSION['user_id'] ?? ''));
$appointmentCurrentRole = trim((string)($_SESSION['role'] ?? ''));
$appointmentAccess = apcm_get_appointment_admin_scope($conn, $appointmentCurrentUserId, $appointmentCurrentRole);
if (empty($appointmentAccess['can_access_tracker'])) {
    http_response_code(403);
    exit('Access denied.');
}

apos_schedule_ensure_storage($conn);

$feedbackType = !empty($_GET['success']) ? 'success' : (!empty($_GET['error']) ? 'error' : '');
$feedbackMessage = !empty($_GET['success'])
    ? (string)$_GET['success']
    : (!empty($_GET['error']) ? (string)$_GET['error'] : '');

$appointmentSettings = aps_settings_load($conn);
$bookingLimits = aps_booking_date_limits($appointmentSettings);
$minAppointmentDate = (string)($bookingLimits['min_date'] ?? '');
$maxAppointmentDate = (string)($bookingLimits['max_date'] ?? '');
$allCouncilMembers = apcm_fetch_council_members($conn);
$officialOptions = [];
$scopedOfficialUserId = trim((string)($appointmentAccess['scoped_official_user_id'] ?? ''));
foreach ($allCouncilMembers as $member) {
    $memberUserId = trim((string)($member['user_id'] ?? ''));
    if ($memberUserId === '') {
        continue;
    }
    if (empty($appointmentAccess['can_manage_all_tracker']) && $scopedOfficialUserId !== '' && strcasecmp($memberUserId, $scopedOfficialUserId) !== 0) {
        continue;
    }
    $officialOptions[] = $member;
}
$selectedOfficialUserId = trim((string)($_GET['official_user_id'] ?? ''));
if (empty($appointmentAccess['can_manage_all_tracker'])) {
    $selectedOfficialUserId = $scopedOfficialUserId !== '' ? $scopedOfficialUserId : $appointmentCurrentUserId;
}
if ($selectedOfficialUserId === '' && $officialOptions !== []) {
    $selectedOfficialUserId = trim((string)($officialOptions[0]['user_id'] ?? ''));
}

$hasCouncilMemberOptions = $officialOptions !== [];
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
    }, $officialOptions), static function (string $userId): bool {
        return $userId !== '';
    })),
    $appointmentSettings
);
$bookedSlotMap = apos_fetch_booked_slots_map(
    $conn,
    array_values(array_filter(array_map(static function (array $member): string {
        return trim((string)($member['user_id'] ?? ''));
    }, $officialOptions), static function (string $userId): bool {
        return $userId !== '';
    }))
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../../Images/favicon_sanjose.png?v=20260211">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encode Walk-in Appointment</title>
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
            max-width: 1280px;
            margin-left: auto;
            margin-right: auto;
        }

        #main-display .page-form {
            max-width: 1280px;
            margin: 0 auto;
            padding-bottom: 48px;
        }

        .form-banner {
            max-width: 1280px;
            margin: 1rem auto 1.5rem;
            padding: 1rem 1.1rem;
            border-radius: 18px;
            border: 1px solid #f2d9c2;
            background: linear-gradient(180deg, #fffaf6 0%, #fff7ef 100%);
        }

        .form-banner-title {
            font-weight: 700;
            color: #7c3f00;
            margin-bottom: 0.35rem;
        }

        .form-banner-text {
            color: #5f6b7a;
            margin-bottom: 0;
            font-size: 0.96rem;
        }

        h1 {
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
            max-width: 1280px;
            margin: 1.75rem auto 1.25rem;
            border-color: #ebe5df;
        }

        .submit-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            min-width: 220px;
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
<div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main id="main-display" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 pb-md-5 pt-md-0">
        <div class="position-relative d-flex align-items-center justify-content-center mb-2 pt-4">
            <a href="<?= htmlspecialchars(appUrl('/Admin-End/Appointments/AppointmentTracker.php?tool=tracker'), ENT_QUOTES, 'UTF-8') ?>" class="back-link d-inline-flex align-items-center text-decoration-none text-dark m-0 position-absolute start-0">
                <i class="bi bi-arrow-left-short fs-3"></i>
            </a>
            <h1 class="form-title m-0">Encode Walk-in Appointment</h1>
        </div>
        <p class="form-subtitle mb-2 text-center">Use this form to encode face-to-face or desk-assisted appointment bookings.</p>
        <p class="form-subtitle mb-4 text-center">All fields marked with <span class="required-asterisk">*</span> are required.</p>

        <?php if ($feedbackMessage !== '' && $feedbackType !== 'success'): ?>
            <div class="alert alert-danger max-w-100" role="alert" style="max-width:1280px;margin:0 auto 1rem;">
                <?= htmlspecialchars($feedbackMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="form-banner">
            <div class="form-banner-title">Before You Save</div>
            <p class="form-banner-text">This creates a confirmed walk-in appointment directly in the tracker. Schedules follow the same weekly council-member availability used by the resident booking flow, including <strong><?= htmlspecialchars((string)$slotIntervalMinutes, ENT_QUOTES, 'UTF-8') ?>-minute</strong> time allotments<?php if ($lunchBreakEnabled): ?> and lunch blocking from <strong><?= htmlspecialchars($lunchBreakLabel, ENT_QUOTES, 'UTF-8') ?></strong><?php endif; ?>.</p>
            <?php if ($closedWeekdays !== []): ?>
                <p class="form-banner-text mt-2">Weekly closures: <strong><?= htmlspecialchars($closedWeekdayLabels, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
            <?php endif; ?>
            <?php if ($unavailableDatesCount > 0): ?>
                <p class="form-banner-text mt-2">Blocked dates include <strong><?= htmlspecialchars($unavailableDatesSummary, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
            <?php endif; ?>
            <p class="form-banner-text mt-2">Earliest available date: <strong><?= htmlspecialchars($firstAvailableAppointmentDateLabel, ENT_QUOTES, 'UTF-8') ?></strong>. Current booking window ends on <strong><?= htmlspecialchars($maxAppointmentDateLabel, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
        </div>

        <form id="walkInAppointmentForm" method="POST" action="<?= htmlspecialchars(appUrl('/PhpFiles/Admin-End/createWalkInAppointment.php'), ENT_QUOTES, 'UTF-8') ?>" class="page-form">
            <?= csrfTokenField() ?>
            <input type="hidden" name="action" value="create_walkin_appointment">

            <h2 class="section-title text-center text-dark">Applicant Information</h2>

            <div class="form-row pt-0">
                <div>
                    <label class="top-label">Last Name <span class="required-asterisk">*</span></label>
                    <input type="text" class="form-control" name="last_name" required>
                </div>
                <div>
                    <label class="top-label">First Name <span class="required-asterisk">*</span></label>
                    <input type="text" class="form-control" name="first_name" required>
                </div>
                <div>
                    <label class="top-label">Middle Name</label>
                    <input type="text" class="form-control" name="middle_name">
                </div>
                <div>
                    <label class="top-label">Suffix</label>
                    <select name="suffix_name" class="form-select">
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
                    <label class="top-label">Contact Number <span class="required-asterisk">*</span></label>
                    <input type="text" class="form-control" id="walkInContactNumber" name="contact_number" inputmode="numeric" maxlength="11" pattern="^09\d{9}$" title="Format: 09XXXXXXXXX" placeholder="09XXXXXXXXX" required>
                </div>
                <div>
                    <label class="top-label">Email Address</label>
                    <input type="email" class="form-control" id="walkInEmailAddress" name="email_address" placeholder="Optional">
                </div>
            </div>

            <div class="form-row">
                <div class="full-width">
                    <label class="top-label">Current Address</label>
                    <textarea class="form-control" name="current_address" rows="3" placeholder="Optional walk-in address"></textarea>
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
                        <?= $hasCouncilMemberOptions ? '' : 'disabled' ?>
                        <?= empty($appointmentAccess['can_manage_all_tracker']) ? 'data-locked-official="1"' : '' ?>
                    >
                        <option value="">Select council member</option>
                        <?php foreach ($officialOptions as $member): ?>
                            <?php $memberUserId = trim((string)($member['user_id'] ?? '')); ?>
                            <option value="<?= htmlspecialchars($memberUserId, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedOfficialUserId === $memberUserId ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)($member['option_label'] ?? $member['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($appointmentAccess['can_manage_all_tracker'])): ?>
                        <div class="text-muted small mt-1">Choose the council member who will receive the walk-in booking.</div>
                    <?php else: ?>
                        <div class="text-muted small mt-1">This form is locked to your own official schedule.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-row two-col-row">
                <div>
                    <label class="top-label">Subject of Appointment <span class="required-asterisk">*</span></label>
                    <select class="form-select" name="subject" id="appointmentSubject" required>
                        <option value="">Select</option>
                        <option value="follow_up">Follow-up Concern</option>
                        <option value="consultation">Consultation</option>
                        <option value="event_coordination">Event Coordination</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="top-label">If Other, please specify</label>
                    <input
                        type="text"
                        class="form-control"
                        name="subject_other"
                        id="appointmentSubjectOther"
                        maxlength="150"
                    >
                    <div id="appointmentSubjectOtherError" class="text-danger small mt-1 d-none" aria-live="polite"></div>
                </div>
            </div>

            <div class="form-row two-col-row">
                <div>
                    <label class="top-label">Date of Appointment <span class="required-asterisk">*</span></label>
                    <input type="date" class="form-control" id="appointmentDate" name="appointment_date" min="<?= htmlspecialchars($minAppointmentDate, ENT_QUOTES, 'UTF-8') ?>" max="<?= htmlspecialchars($maxAppointmentDate, ENT_QUOTES, 'UTF-8') ?>" data-year-end="<?= htmlspecialchars($maxAppointmentDate, ENT_QUOTES, 'UTF-8') ?>" data-date-disabled-weekdays="<?= htmlspecialchars(implode(',', $disabledWeekdays), ENT_QUOTES, 'UTF-8') ?>" data-date-disabled-dates="<?= htmlspecialchars(implode(',', $unavailableDates), ENT_QUOTES, 'UTF-8') ?>" data-available-weekdays="<?= htmlspecialchars($availableWeekdayLabels, ENT_QUOTES, 'UTF-8') ?>" data-date-modal-style="calendar" placeholder="Select date" required>
                    <div id="appointmentDateError" class="text-danger small mt-1 d-none" aria-live="polite"></div>
                </div>
                <div>
                    <label class="top-label">Time of Appointment <span class="required-asterisk">*</span></label>
                    <select class="form-select" id="appointmentTime" name="appointment_time" required>
                        <option value="">Select council member and date first</option>
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
                    <label class="top-label">Purpose <span class="required-asterisk">*</span></label>
                    <textarea class="form-control" name="purpose" rows="4" required></textarea>
                </div>
            </div>

            <div class="form-row">
                <div class="full-width">
                    <label class="top-label">Desk Note</label>
                    <textarea class="form-control" name="desk_note" rows="3" placeholder="Optional staff note for the tracker"></textarea>
                </div>
            </div>

            <div class="agreement-row">
                <label class="agreement-text check-item">
                    <input type="checkbox" required>I confirm that this appointment was encoded with the applicant’s consent and the details above were reviewed during face-to-face booking.
                </label>

                <button
                    type="submit"
                    class="submit-btn"
                    data-default-label="SAVE WALK-IN APPOINTMENT"
                    data-loading-label="Saving..."
                    <?= $hasAppointmentAvailability ? '' : 'disabled' ?>
                >
                    <span class="submit-btn-label">SAVE WALK-IN APPOINTMENT</span>
                    <span class="submit-btn-spinner" aria-hidden="true"></span>
                </button>
            </div>
        </form>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../JS-Script-Files/Resident-End/dateFieldModal.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        const form = document.getElementById("walkInAppointmentForm");
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
        const appointmentSubjectOtherInput = document.getElementById("appointmentSubjectOther");
        const appointmentSubjectOtherError = document.getElementById("appointmentSubjectOtherError");
        const contactNumberInput = document.getElementById("walkInContactNumber");
        const emailInput = document.getElementById("walkInEmailAddress");
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
        const defaultSubmitLabel = String(submitBtn.dataset.defaultLabel || "SAVE WALK-IN APPOINTMENT").trim() || "SAVE WALK-IN APPOINTMENT";
        const loadingSubmitLabel = String(submitBtn.dataset.loadingLabel || "Saving...").trim() || "Saving...";

        const setSubmittingState = (submitting) => {
            isSubmitting = submitting === true;
            form.setAttribute("aria-busy", isSubmitting ? "true" : "false");
            submitBtn.classList.toggle("is-loading", isSubmitting);
            submitBtn.disabled = isSubmitting || submitBtn.disabled;
            if (submitBtnLabel) {
                submitBtnLabel.textContent = isSubmitting ? loadingSubmitLabel : defaultSubmitLabel;
            }
        };

        if (appointmentCouncilMemberInput?.dataset.lockedOfficial === "1") {
            appointmentCouncilMemberInput.setAttribute("disabled", "disabled");
            const hiddenOfficialInput = document.createElement("input");
            hiddenOfficialInput.type = "hidden";
            hiddenOfficialInput.name = "official_user_id";
            hiddenOfficialInput.value = String(appointmentCouncilMemberInput.value || "").trim();
            form.appendChild(hiddenOfficialInput);
        }

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

        const getOfficialUserId = () => {
            if (appointmentCouncilMemberInput?.disabled) {
                return String(form.querySelector('input[name="official_user_id"]')?.value || "").trim();
            }
            return String(appointmentCouncilMemberInput?.value || "").trim();
        };

        const scheduleDayForSelection = () => {
            const officialUserId = getOfficialUserId();
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
            const officialUserId = getOfficialUserId();
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
                if (bookedAppointmentIdsFor(officialUserId, isoDate, value).length > 0) {
                    continue;
                }
                slots.push({ value, label: formatTimeLabel(value) });
            }

            return { dayEntry, slots };
        };

        const syncAppointmentAvailability = () => {
            if (!appointmentTimeInput) return;

            const officialUserId = getOfficialUserId();
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
                    appointmentLocationHelp.textContent = "All appointment times for that council member are already taken on the selected date.";
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

            const officialUserId = getOfficialUserId();
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
                    const msg = "All appointment times for the selected council member are already taken on that date";
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

        const validateContactNumber = () => {
            if (!contactNumberInput) return true;
            const normalized = String(contactNumberInput.value || "").replace(/\D+/g, "");
            if (!/^09\d{9}$/.test(normalized)) {
                contactNumberInput.setCustomValidity("Please enter a valid mobile number in the format 09XXXXXXXXX.");
                return false;
            }
            contactNumberInput.setCustomValidity("");
            return true;
        };

        const validateEmail = () => {
            if (!emailInput) return true;
            const value = String(emailInput.value || "").trim();
            if (value === "") {
                emailInput.setCustomValidity("");
                return true;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                emailInput.setCustomValidity("Please enter a valid email address.");
                return false;
            }
            emailInput.setCustomValidity("");
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
            validateContactNumber();
            validateEmail();
            const hasCouncilMemberSelection = getOfficialUserId() !== "";
            const hasAppointmentWindow = minAllowedIso !== "" && maxAllowedIso !== "";
            submitBtn.disabled = !hasConfiguredAppointmentAvailability || !hasCouncilMemberSelection || !hasAppointmentWindow || !form.checkValidity();
        };

        contactNumberInput?.addEventListener("input", updateState);
        emailInput?.addEventListener("input", updateState);
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
            const okPhone = validateContactNumber();
            const okEmail = validateEmail();
            if (!okSubjectOther || !okDate || !okTime || !okPhone || !okEmail) {
                e.preventDefault();
                if (!okPhone) contactNumberInput?.focus();
                else if (!okEmail) emailInput?.focus();
                else if (!okSubjectOther) appointmentSubjectOtherInput?.focus();
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

        syncAppointmentAvailability();
        setSubmittingState(false);
        updateState();
    });
</script>
</body>
</html>
