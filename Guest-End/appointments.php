<?php
require_once __DIR__ . '/../PhpFiles/General/security.php';
require_once __DIR__ . '/../PhpFiles/General/connection.php';
require_once __DIR__ . '/../PhpFiles/General/appointmentCouncilMembers.php';
require_once __DIR__ . '/../PhpFiles/General/appointmentSettings.php';
require_once __DIR__ . '/../PhpFiles/General/appointmentOfficialSchedules.php';
require_once __DIR__ . '/../PhpFiles/General/appointmentTimeSlots.php';

apos_schedule_ensure_storage($conn);

$feedbackType = !empty($_GET['success']) ? 'success' : (!empty($_GET['error']) ? 'error' : '');
$feedbackMessage = !empty($_GET['success'])
    ? (string)$_GET['success']
    : (!empty($_GET['error']) ? (string)$_GET['error'] : '');
$feedbackAppointmentId = trim((string)($_GET['appointment_id'] ?? ''));

$formValues = [
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'suffix_name' => '',
    'contact_number' => '',
    'email_address' => '',
    'current_address' => '',
    'official_user_id' => trim((string)($_GET['official_user_id'] ?? '')),
    'subject' => '',
    'subject_other' => '',
    'appointment_date' => '',
    'appointment_time' => '',
    'purpose' => '',
];

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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Guest Appointments</title>
    <link rel="icon" href="<?= htmlspecialchars(appUrl('/Images/favicon_sanjose.png'), ENT_QUOTES, 'UTF-8') ?>?v=20260211">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../CSS-Styles/Guest-End-CSS/GeneralStyle.css">
    <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/applicationForms.css">
    <style>
        :root {
            --guest-bg: #f5f0ea;
            --guest-ink: #1c2b39;
            --guest-muted: #5f6b7a;
            --guest-border: #ecd3b6;
            --guest-accent: #e97f16;
            --guest-accent-dark: #b85a00;
            --guest-card: #fffdf9;
            --guest-success: #1f7a4f;
        }

        body {
            margin: 0;
            background:
                radial-gradient(circle at top left, rgba(255, 221, 184, 0.92), transparent 30%),
                linear-gradient(180deg, #f9f3ec 0%, #f6f0e9 34%, #ffffff 100%);
            color: var(--guest-ink);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        .guest-shell {
            min-height: 100vh;
        }

        .guest-header {
            position: sticky;
            top: 0;
            z-index: 30;
            backdrop-filter: blur(14px);
            background: rgba(255, 250, 245, 0.9);
            border-bottom: 1px solid rgba(236, 211, 182, 0.85);
        }

        .guest-nav {
            max-width: 1180px;
            margin: 0 auto;
            padding: 0.95rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .guest-brand {
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            text-decoration: none;
            color: var(--guest-ink);
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .guest-brand img {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            object-fit: cover;
            border: 1px solid rgba(233, 127, 22, 0.16);
            box-shadow: 0 10px 20px rgba(28, 43, 57, 0.08);
        }

        .guest-nav-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .guest-nav-actions .btn {
            border-radius: 999px;
            font-weight: 700;
            padding-inline: 1rem;
        }

        .guest-main {
            max-width: 1180px;
            margin: 0 auto;
            padding: 2rem 1.25rem 3.5rem;
        }

        .hero-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(360px, 0.95fr);
            gap: 1.6rem;
            align-items: start;
        }

        .hero-copy {
            padding: 1rem 0.25rem 0;
        }

        .hero-kicker {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            margin-bottom: 1rem;
            padding: 0.42rem 0.82rem;
            border-radius: 999px;
            background: rgba(233, 127, 22, 0.11);
            color: var(--guest-accent-dark);
            border: 1px solid rgba(233, 127, 22, 0.18);
            font-size: 0.85rem;
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .hero-title {
            margin: 0;
            font-size: clamp(2.35rem, 4vw, 4.2rem);
            line-height: 0.95;
            font-weight: 800;
            letter-spacing: -0.05em;
            max-width: 9ch;
        }

        .hero-title strong {
            color: var(--guest-accent-dark);
            font-weight: 800;
        }

        .hero-text {
            margin: 1.2rem 0 0;
            font-size: 1.05rem;
            line-height: 1.75;
            color: var(--guest-muted);
            max-width: 58ch;
        }

        .hero-points {
            list-style: none;
            margin: 1.5rem 0 0;
            padding: 0;
            display: grid;
            gap: 0.85rem;
            max-width: 42rem;
        }

        .hero-points li {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
            padding: 0.95rem 1rem;
            border-radius: 1rem;
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(236, 211, 182, 0.9);
            box-shadow: 0 12px 24px rgba(28, 43, 57, 0.05);
        }

        .hero-points i {
            color: var(--guest-accent);
            margin-top: 0.15rem;
        }

        .hero-points strong {
            display: block;
            margin-bottom: 0.2rem;
        }

        .guest-card,
        .guide-card,
        .login-card {
            background: var(--guest-card);
            border: 1px solid var(--guest-border);
            border-radius: 1.6rem;
            box-shadow: 0 22px 40px rgba(28, 43, 57, 0.08);
        }

        .hero-stack {
            display: grid;
            gap: 1rem;
        }

        .login-card {
            padding: 1.2rem 1.2rem 1.15rem;
            background:
                radial-gradient(circle at top right, rgba(255, 224, 193, 0.84), rgba(255, 224, 193, 0) 30%),
                linear-gradient(135deg, #fffaf4 0%, #fffdf9 100%);
        }

        .login-card h2 {
            margin: 0 0 0.4rem;
            font-size: 1.15rem;
            font-weight: 800;
        }

        .login-card p {
            margin: 0;
            color: var(--guest-muted);
            line-height: 1.65;
        }

        .login-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .login-actions .btn {
            border-radius: 999px;
            font-weight: 700;
        }

        .guide-card {
            padding: 1.25rem 1.35rem;
        }

        .guide-card h3 {
            margin: 0 0 0.45rem;
            font-size: 1rem;
            font-weight: 800;
            color: var(--guest-accent-dark);
        }

        .guide-card p {
            margin: 0;
            color: var(--guest-muted);
            line-height: 1.65;
            font-size: 0.96rem;
        }

        .guide-card p + p {
            margin-top: 0.7rem;
        }

        .form-shell {
            margin-top: 1.55rem;
        }

        .guest-card {
            padding: 1.4rem 1.5rem 1.6rem;
        }

        .guest-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .guest-card-header h2 {
            margin: 0;
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .guest-card-header p {
            margin: 0.4rem 0 0;
            color: var(--guest-muted);
            line-height: 1.6;
        }

        .summary-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.5rem 0.85rem;
            border-radius: 999px;
            background: #fff6ea;
            color: var(--guest-accent-dark);
            border: 1px solid rgba(233, 127, 22, 0.16);
            font-size: 0.9rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .alert {
            border-radius: 1rem;
        }

        .section-heading {
            font-size: 1.12rem;
            font-weight: 800;
            margin: 1.2rem 0 1rem;
            color: var(--guest-ink);
        }

        .section-caption {
            margin: -0.6rem 0 0.9rem;
            color: var(--guest-muted);
            font-size: 0.94rem;
        }

        .otp-panel {
            padding: 1rem;
            border-radius: 1.2rem;
            background: linear-gradient(180deg, #fff9f2 0%, #fffdf9 100%);
            border: 1px solid rgba(233, 127, 22, 0.18);
            margin-bottom: 1.2rem;
        }

        .otp-panel-header {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: flex-start;
            margin-bottom: 0.8rem;
        }

        .otp-panel-header h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 800;
            color: var(--guest-accent-dark);
        }

        .otp-panel-header p {
            margin: 0.25rem 0 0;
            color: var(--guest-muted);
            font-size: 0.93rem;
            line-height: 1.6;
        }

        .otp-verified-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.42rem 0.8rem;
            border-radius: 999px;
            background: rgba(31, 122, 79, 0.12);
            color: var(--guest-success);
            border: 1px solid rgba(31, 122, 79, 0.16);
            font-weight: 700;
            font-size: 0.88rem;
        }

        .otp-actions,
        .otp-verify-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 0.8rem;
            align-items: end;
        }

        .otp-verify-row {
            margin-top: 0.85rem;
        }

        .otp-helper,
        .otp-feedback,
        .otp-error {
            margin-top: 0.75rem;
            font-size: 0.92rem;
        }

        .otp-helper {
            color: var(--guest-muted);
        }

        .otp-feedback {
            color: var(--guest-success);
        }

        .otp-error {
            color: #b42318;
        }

        .otp-verify-row[hidden] {
            display: none !important;
        }

        .submit-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            min-width: 210px;
            border-radius: 999px;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        .submit-btn:not(:disabled):hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 28px rgba(233, 127, 22, 0.24);
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

        .agreement-row {
            margin-top: 1.3rem;
        }

        .agreement-row .check-item {
            color: var(--guest-muted);
        }

        textarea.form-control,
        input.form-control,
        select.form-select {
            border-radius: 0.95rem;
        }

        @keyframes appointmentSubmitSpin {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 991.98px) {
            .hero-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767.98px) {
            .guest-nav {
                padding: 0.85rem 1rem;
            }

            .guest-main {
                padding: 1.35rem 1rem 2.5rem;
            }

            .guest-card {
                padding: 1.15rem 1rem 1.25rem;
                border-radius: 1.25rem;
            }

            .guest-card-header {
                flex-direction: column;
            }

            .otp-actions,
            .otp-verify-row {
                grid-template-columns: 1fr;
            }

            .hero-title {
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <div class="guest-shell">
        <header class="guest-header">
            <div class="guest-nav">
                <a class="guest-brand" href="<?= htmlspecialchars(appUrl('/Guest-End/services.html'), ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= htmlspecialchars(appUrl('/Images/San_Jose_LOGO.jpg'), ENT_QUOTES, 'UTF-8') ?>" alt="Barangay San Jose Logo">
                    <span>Barangay San Jose Appointments</span>
                </a>
                <div class="guest-nav-actions">
                    <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(appUrl('/Guest-End/services.html'), ENT_QUOTES, 'UTF-8') ?>">Back to Services</a>
                    <a class="btn btn-warning text-white" href="<?= htmlspecialchars(appUrl('/Guest-End/login.php?service=appointments'), ENT_QUOTES, 'UTF-8') ?>">Resident Sign In</a>
                </div>
            </div>
        </header>

        <main class="guest-main">
            <section class="hero-layout">
                <div class="hero-copy">
                    <div class="hero-kicker"><i class="bi bi-shield-check"></i>Guest Booking with OTP</div>
                    <h1 class="hero-title">Book a barangay <strong>appointment</strong> even without a resident account.</h1>
                    <p class="hero-text">Guests can book online by verifying a mobile number first. Registered residents can still sign in to use the resident portal and auto-filled details.</p>

                    <ul class="hero-points">
                        <li>
                            <i class="bi bi-phone"></i>
                            <div>
                                <strong>OTP before submission</strong>
                                Mobile verification helps confirm the contact number is real before the booking is saved.
                            </div>
                        </li>
                        <li>
                            <i class="bi bi-calendar2-week"></i>
                            <div>
                                <strong>Live official schedule</strong>
                                Dates, time slots, and meeting locations follow the current weekly schedule of the selected council member.
                            </div>
                        </li>
                        <li>
                            <i class="bi bi-person-badge"></i>
                            <div>
                                <strong>Resident portal still available</strong>
                                Already registered? Sign in to use the faster resident flow instead of booking as a guest.
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="hero-stack">
                    <aside class="login-card">
                        <h2>Already a registered resident?</h2>
                        <p>Use your resident account for direct booking and resident-side appointment tracking. Guest booking is best for visitors, new requesters, or people who have not registered yet.</p>
                        <div class="login-actions">
                            <a class="btn btn-dark" href="<?= htmlspecialchars(appUrl('/Guest-End/login.php?service=appointments'), ENT_QUOTES, 'UTF-8') ?>">Sign In as Resident</a>
                            <a class="btn btn-outline-dark" href="<?= htmlspecialchars(appUrl('/Resident-End/resident_registration.php'), ENT_QUOTES, 'UTF-8') ?>">Create Resident Account</a>
                        </div>
                    </aside>

                    <aside class="guide-card">
                        <h3>Booking Window</h3>
                        <p>Appointments use <strong><?= htmlspecialchars((string)$slotIntervalMinutes, ENT_QUOTES, 'UTF-8') ?>-minute</strong> time allotments and can be booked up to <strong><?= htmlspecialchars((string)$bookingWindowDays, ENT_QUOTES, 'UTF-8') ?> days ahead</strong>, capped through <strong><?= htmlspecialchars($maxAppointmentDateLabel, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
                        <?php if ($lunchBreakEnabled): ?>
                            <p>Lunch time is blocked from <strong><?= htmlspecialchars($lunchBreakLabel, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
                        <?php endif; ?>
                        <p>Appointments are available on <strong><?= htmlspecialchars($availableWeekdayLabels, ENT_QUOTES, 'UTF-8') ?></strong><?php if ($closedWeekdays !== []): ?> and closed on <strong><?= htmlspecialchars($closedWeekdayLabels, ENT_QUOTES, 'UTF-8') ?></strong><?php endif; ?>.</p>
                        <?php if ($unavailableDatesCount > 0): ?>
                            <p>Blocked dates include <strong><?= htmlspecialchars($unavailableDatesSummary, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
                        <?php endif; ?>
                        <p>Earliest available date: <strong><?= htmlspecialchars($firstAvailableAppointmentDateLabel, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
                    </aside>
                </div>
            </section>

            <section class="form-shell">
                <div class="guest-card">
                    <div class="guest-card-header">
                        <div>
                            <h2>Guest Appointment Form</h2>
                            <p>All fields marked with <span class="required-asterisk">*</span> are required. Complete OTP verification before submitting the request.</p>
                        </div>
                        <div class="summary-chip"><i class="bi bi-envelope-paper"></i>No QR required</div>
                    </div>

                    <?php if ($feedbackMessage !== '' && $feedbackType !== 'success'): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= htmlspecialchars($feedbackMessage, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <form id="guestAppointmentForm" class="page-form mb-0" method="POST" action="<?= htmlspecialchars(appUrl('/PhpFiles/Guest-End/submitGuestAppointment.php'), ENT_QUOTES, 'UTF-8') ?>">
                        <?= csrfTokenField() ?>
                        <input type="hidden" name="action" value="submit_guest_appointment">

                        <h3 class="section-heading">Guest Information</h3>
                        <p class="section-caption">Enter the person who will attend or represent the booking.</p>

                        <div class="form-row pt-0">
                            <div>
                                <label class="top-label">Last Name <span class="required-asterisk">*</span></label>
                                <input type="text" class="form-control" name="last_name" value="<?= htmlspecialchars($formValues['last_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div>
                                <label class="top-label">First Name <span class="required-asterisk">*</span></label>
                                <input type="text" class="form-control" name="first_name" value="<?= htmlspecialchars($formValues['first_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div>
                                <label class="top-label">Middle Name</label>
                                <input type="text" class="form-control" name="middle_name" value="<?= htmlspecialchars($formValues['middle_name'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div>
                                <label class="top-label">Suffix</label>
                                <select name="suffix_name" class="form-select">
                                    <option value="" <?= $formValues['suffix_name'] === '' ? 'selected' : '' ?>>None</option>
                                    <option value="Jr." <?= $formValues['suffix_name'] === 'Jr.' ? 'selected' : '' ?>>Jr.</option>
                                    <option value="Sr." <?= $formValues['suffix_name'] === 'Sr.' ? 'selected' : '' ?>>Sr.</option>
                                    <option value="III" <?= $formValues['suffix_name'] === 'III' ? 'selected' : '' ?>>III</option>
                                    <option value="IV" <?= $formValues['suffix_name'] === 'IV' ? 'selected' : '' ?>>IV</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Current Address</label>
                                <textarea class="form-control" name="current_address" rows="3" placeholder="House / street / subdivision / city"><?= htmlspecialchars($formValues['current_address'], ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                        </div>

                        <div class="otp-panel">
                            <div class="otp-panel-header">
                                <div>
                                    <h3>Mobile OTP Verification</h3>
                                    <p>Use a reachable mobile number. The appointment cannot be submitted until this number is verified.</p>
                                </div>
                                <div class="otp-verified-pill d-none" id="otpVerifiedPill">
                                    <i class="bi bi-check-circle-fill"></i> Verified
                                </div>
                            </div>

                            <div class="otp-actions">
                                <div>
                                    <label class="top-label">Mobile Number <span class="required-asterisk">*</span></label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="guestContactNumber"
                                        name="contact_number"
                                        inputmode="numeric"
                                        maxlength="11"
                                        pattern="^09\\d{9}$"
                                        title="Format: 09XXXXXXXXX"
                                        placeholder="09XXXXXXXXX"
                                        value="<?= htmlspecialchars($formValues['contact_number'], ENT_QUOTES, 'UTF-8') ?>"
                                        required
                                    >
                                    <div class="otp-helper">OTP is sent through SMS using the entered mobile number.</div>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button type="button" class="btn btn-outline-warning" id="sendOtpBtn">Send OTP</button>
                                    <button type="button" class="btn btn-outline-secondary d-none" id="changeOtpNumberBtn">Change Number</button>
                                </div>
                            </div>

                            <div class="otp-verify-row" id="otpVerifyRow" hidden>
                                <div>
                                    <label class="top-label">Enter OTP <span class="required-asterisk">*</span></label>
                                    <input type="text" class="form-control" id="guestOtpInput" inputmode="numeric" maxlength="6" placeholder="6-digit OTP">
                                </div>
                                <div>
                                    <button type="button" class="btn btn-dark w-100" id="verifyOtpBtn">Verify OTP</button>
                                </div>
                            </div>

                            <div class="otp-feedback d-none" id="otpFeedback" aria-live="polite"></div>
                            <div class="otp-error d-none" id="otpError" aria-live="polite"></div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Email Address</label>
                                <input type="email" class="form-control" id="guestEmailAddress" name="email_address" value="<?= htmlspecialchars($formValues['email_address'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Optional">
                            </div>
                        </div>

                        <hr class="form-divider">

                        <h3 class="section-heading">Appointment Details</h3>
                        <p class="section-caption">Pick the council member, available schedule, and the reason for your visit.</p>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Barangay Council Member <span class="required-asterisk">*</span></label>
                                <select
                                    class="form-select"
                                    name="official_user_id"
                                    id="appointmentCouncilMember"
                                    required
                                    <?= $hasCouncilMemberOptions ? '' : 'disabled' ?>
                                >
                                    <option value="">Select council member</option>
                                    <?php foreach ($councilMemberOptions as $member): ?>
                                        <option
                                            value="<?= htmlspecialchars((string)($member['user_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            <?= $formValues['official_user_id'] === (string)($member['user_id'] ?? '') ? 'selected' : '' ?>
                                        >
                                            <?= htmlspecialchars((string)($member['option_label'] ?? $member['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
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
                                    <option value="follow_up" <?= $formValues['subject'] === 'follow_up' ? 'selected' : '' ?>>Follow-up Concern</option>
                                    <option value="consultation" <?= $formValues['subject'] === 'consultation' ? 'selected' : '' ?>>Consultation</option>
                                    <option value="event_coordination" <?= $formValues['subject'] === 'event_coordination' ? 'selected' : '' ?>>Event Coordination</option>
                                    <option value="other" <?= $formValues['subject'] === 'other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="top-label">If Other, please specify</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    name="subject_other"
                                    id="appointmentSubjectOther"
                                    value="<?= htmlspecialchars($formValues['subject_other'], ENT_QUOTES, 'UTF-8') ?>"
                                    maxlength="150"
                                >
                                <div id="appointmentSubjectOtherError" class="text-danger small mt-1 d-none" aria-live="polite"></div>
                            </div>
                        </div>

                        <div class="form-row two-col-row">
                            <div>
                                <label class="top-label">Date of Appointment <span class="required-asterisk">*</span></label>
                                <input type="date" class="form-control" id="appointmentDate" name="appointment_date" min="<?= htmlspecialchars($minAppointmentDate, ENT_QUOTES, 'UTF-8') ?>" max="<?= htmlspecialchars($maxAppointmentDate, ENT_QUOTES, 'UTF-8') ?>" data-year-end="<?= htmlspecialchars($maxAppointmentDate, ENT_QUOTES, 'UTF-8') ?>" data-date-disabled-weekdays="<?= htmlspecialchars(implode(',', $disabledWeekdays), ENT_QUOTES, 'UTF-8') ?>" data-date-disabled-dates="<?= htmlspecialchars(implode(',', $unavailableDates), ENT_QUOTES, 'UTF-8') ?>" data-available-weekdays="<?= htmlspecialchars($availableWeekdayLabels, ENT_QUOTES, 'UTF-8') ?>" data-date-modal-style="calendar" placeholder="Select date" value="<?= htmlspecialchars($formValues['appointment_date'], ENT_QUOTES, 'UTF-8') ?>" required>
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
                                <textarea class="form-control" name="purpose" rows="4" required><?= htmlspecialchars($formValues['purpose'], ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                        </div>

                        <div class="agreement-row">
                            <label class="agreement-text check-item">
                                <input type="checkbox" required>I hereby certify that the information provided is true and that I understand the contact number above will be used for appointment updates.
                            </label>

                            <button
                                type="submit"
                                class="submit-btn"
                                data-default-label="SUBMIT APPOINTMENT"
                                data-loading-label="Submitting..."
                                <?= $hasAppointmentAvailability ? '' : 'disabled' ?>
                            >
                                <span class="submit-btn-label">SUBMIT APPOINTMENT</span>
                                <span class="submit-btn-spinner" aria-hidden="true"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </main>
    </div>

    <div
        id="appointmentFeedbackData"
        data-feedback-type="<?= htmlspecialchars($feedbackType, ENT_QUOTES, 'UTF-8') ?>"
        data-feedback-message="<?= htmlspecialchars($feedbackMessage, ENT_QUOTES, 'UTF-8') ?>"
        data-feedback-appointment-id="<?= htmlspecialchars($feedbackAppointmentId, ENT_QUOTES, 'UTF-8') ?>"
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
                    <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(appUrl('/Guest-End/services.html'), ENT_QUOTES, 'UTF-8') ?>">Back to Services</a>
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../JS-Script-Files/Resident-End/dateFieldModal.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const form = document.getElementById("guestAppointmentForm");
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
            const appointmentFeedbackData = document.getElementById("appointmentFeedbackData");
            const appointmentSuccessModalEl = document.getElementById("appointmentSuccessModal");
            const appointmentSuccessMessage = document.getElementById("appointmentSuccessMessage");
            const contactNumberInput = document.getElementById("guestContactNumber");
            const emailInput = document.getElementById("guestEmailAddress");
            const sendOtpBtn = document.getElementById("sendOtpBtn");
            const changeOtpNumberBtn = document.getElementById("changeOtpNumberBtn");
            const otpVerifyRow = document.getElementById("otpVerifyRow");
            const otpInput = document.getElementById("guestOtpInput");
            const verifyOtpBtn = document.getElementById("verifyOtpBtn");
            const otpFeedback = document.getElementById("otpFeedback");
            const otpError = document.getElementById("otpError");
            const otpVerifiedPill = document.getElementById("otpVerifiedPill");
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
            let otpVerified = false;
            let otpCountdown = 0;
            let otpCountdownTimer = null;
            const defaultSubmitLabel = String(submitBtn.dataset.defaultLabel || "SUBMIT APPOINTMENT").trim() || "SUBMIT APPOINTMENT";
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

            const clearOtpMessages = () => {
                otpFeedback?.classList.add("d-none");
                otpError?.classList.add("d-none");
                if (otpFeedback) otpFeedback.textContent = "";
                if (otpError) otpError.textContent = "";
            };

            const showOtpFeedback = (message, isError = false) => {
                clearOtpMessages();
                const target = isError ? otpError : otpFeedback;
                target?.classList.remove("d-none");
                if (target) target.textContent = message;
            };

            const normalizePhone = (value) => {
                const digits = String(value || "").replace(/\D+/g, "");
                return /^09\d{9}$/.test(digits) ? digits : "";
            };

            const validateContactNumber = () => {
                if (!contactNumberInput) return true;
                const normalized = normalizePhone(contactNumberInput.value);
                if (normalized === "") {
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

            const updateOtpButtons = () => {
                if (sendOtpBtn) {
                    const label = otpCountdown > 0 ? `Resend in ${otpCountdown}s` : "Send OTP";
                    sendOtpBtn.textContent = label;
                    sendOtpBtn.disabled = otpVerified || otpCountdown > 0 || !validateContactNumber();
                }
                if (verifyOtpBtn) {
                    verifyOtpBtn.disabled = otpVerified;
                }
            };

            const startOtpCountdown = (seconds) => {
                otpCountdown = Math.max(0, Number(seconds || 0));
                if (otpCountdownTimer) {
                    window.clearInterval(otpCountdownTimer);
                }
                updateOtpButtons();
                if (otpCountdown <= 0) {
                    return;
                }
                otpCountdownTimer = window.setInterval(() => {
                    otpCountdown = Math.max(0, otpCountdown - 1);
                    updateOtpButtons();
                    if (otpCountdown <= 0 && otpCountdownTimer) {
                        window.clearInterval(otpCountdownTimer);
                        otpCountdownTimer = null;
                    }
                }, 1000);
            };

            const setOtpVerifiedState = (verified) => {
                otpVerified = verified === true;
                if (contactNumberInput) {
                    contactNumberInput.readOnly = otpVerified;
                }
                otpVerifiedPill?.classList.toggle("d-none", !otpVerified);
                changeOtpNumberBtn?.classList.toggle("d-none", !otpVerified);
                if (otpVerifyRow) {
                    otpVerifyRow.hidden = otpVerified;
                }
                if (otpInput) {
                    otpInput.disabled = otpVerified;
                }
                updateOtpButtons();
            };

            const resetOtpVerification = () => {
                otpVerified = false;
                otpCountdown = 0;
                if (otpCountdownTimer) {
                    window.clearInterval(otpCountdownTimer);
                    otpCountdownTimer = null;
                }
                if (contactNumberInput) {
                    contactNumberInput.readOnly = false;
                }
                if (otpInput) {
                    otpInput.value = "";
                    otpInput.disabled = false;
                }
                if (otpVerifyRow) {
                    otpVerifyRow.hidden = true;
                }
                changeOtpNumberBtn?.classList.add("d-none");
                otpVerifiedPill?.classList.add("d-none");
                clearOtpMessages();
                updateOtpButtons();
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
                updateOtpButtons();
                const hasCouncilMemberSelection = !appointmentCouncilMemberInput || !appointmentCouncilMemberInput.disabled;
                const hasAppointmentWindow = minAllowedIso !== "" && maxAllowedIso !== "";
                submitBtn.disabled = !hasConfiguredAppointmentAvailability || !otpVerified || !hasCouncilMemberSelection || !hasAppointmentWindow || !form.checkValidity();
            };

            sendOtpBtn?.addEventListener("click", async () => {
                if (!validateContactNumber()) {
                    contactNumberInput?.reportValidity();
                    return;
                }

                const recipient = normalizePhone(contactNumberInput?.value || "");
                if (recipient === "") {
                    return;
                }

                clearOtpMessages();
                sendOtpBtn.disabled = true;
                sendOtpBtn.textContent = "Sending...";

                try {
                    const formData = new FormData();
                    formData.append("recipient", recipient);
                    formData.append("purpose", "guest_appointment");

                    const response = await fetch("../PhpFiles/OTPHandlers/generate_otp.php", {
                        method: "POST",
                        body: formData,
                        credentials: "same-origin",
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(String(data.error || "Failed to send OTP. Please try again."));
                    }

                    if (otpVerifyRow) {
                        otpVerifyRow.hidden = false;
                    }
                    showOtpFeedback("OTP sent successfully. Enter the 6-digit code to verify your mobile number.");
                    startOtpCountdown(60);
                    otpInput?.focus();
                } catch (error) {
                    showOtpFeedback(error instanceof Error ? error.message : "Failed to send OTP. Please try again.", true);
                    updateOtpButtons();
                }
            });

            verifyOtpBtn?.addEventListener("click", async () => {
                if (!validateContactNumber()) {
                    contactNumberInput?.reportValidity();
                    return;
                }

                const recipient = normalizePhone(contactNumberInput?.value || "");
                const otpValue = String(otpInput?.value || "").trim();
                if (!/^\d{6}$/.test(otpValue)) {
                    showOtpFeedback("Please enter the 6-digit OTP code.", true);
                    otpInput?.focus();
                    return;
                }

                clearOtpMessages();
                verifyOtpBtn.disabled = true;
                verifyOtpBtn.textContent = "Verifying...";

                try {
                    const formData = new FormData();
                    formData.append("recipient", recipient);
                    formData.append("purpose", "guest_appointment");
                    formData.append("otp", otpValue);

                    const response = await fetch("../PhpFiles/OTPHandlers/verify_otp.php", {
                        method: "POST",
                        body: formData,
                        credentials: "same-origin",
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(String(data.error || "Failed to verify OTP."));
                    }

                    setOtpVerifiedState(true);
                    showOtpFeedback("Mobile number verified. You may now submit the appointment.");
                    updateState();
                } catch (error) {
                    showOtpFeedback(error instanceof Error ? error.message : "Failed to verify OTP.", true);
                } finally {
                    verifyOtpBtn.disabled = otpVerified;
                    verifyOtpBtn.textContent = "Verify OTP";
                }
            });

            changeOtpNumberBtn?.addEventListener("click", () => {
                resetOtpVerification();
                contactNumberInput?.focus();
            });

            contactNumberInput?.addEventListener("input", () => {
                if (!otpVerified) {
                    clearOtpMessages();
                }
                updateState();
            });
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
                if (!otpVerified) {
                    e.preventDefault();
                    showOtpFeedback("Please verify your mobile number through OTP before submitting the appointment.", true);
                    contactNumberInput?.focus();
                    return;
                }
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

            const feedbackType = String(appointmentFeedbackData?.dataset.feedbackType || "").trim();
            const feedbackMessage = String(appointmentFeedbackData?.dataset.feedbackMessage || "").trim();
            const feedbackAppointmentId = String(appointmentFeedbackData?.dataset.feedbackAppointmentId || "").trim();
            if (feedbackType === "success" && feedbackMessage !== "" && appointmentSuccessModalEl && window.bootstrap) {
                if (appointmentSuccessMessage) {
                    appointmentSuccessMessage.textContent = feedbackAppointmentId !== ""
                        ? `${feedbackMessage} Please keep this reference number for follow-up: ${feedbackAppointmentId}.`
                        : feedbackMessage;
                }
                const successModal = bootstrap.Modal.getOrCreateInstance(appointmentSuccessModalEl, {
                    backdrop: "static",
                    keyboard: false,
                });
                successModal.show();
            }

            syncAppointmentAvailability();
            resetOtpVerification();
            setSubmittingState(false);
            updateState();
        });
    </script>
</body>
</html>
