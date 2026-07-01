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
    'current_address' => '',
    'email_address' => '',
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
$disabledWeekdays = aps_disabled_weekdays($appointmentSettings);
$unavailableDates = aps_normalize_unavailable_dates($appointmentSettings['unavailable_dates'] ?? []);
$firstAvailableAppointmentDate = aps_first_available_booking_date($appointmentSettings);
$hasAvailableAppointmentDates = !empty($bookingLimits['has_window']) && $firstAvailableAppointmentDate !== null;
$hasAppointmentAvailability = $hasCouncilMemberOptions && $hasAvailableAppointmentDates && $appointmentTimeSlots !== [];
$slotIntervalMinutes = (int)($appointmentSettings['slot_interval_minutes'] ?? 30);
$lunchBreakEnabled = aps_has_lunch_break($appointmentSettings);
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

$contactNumberFieldValue = preg_replace('/\D+/', '', (string)($formValues['contact_number'] ?? ''));
if (preg_match('/^63(9\d{9})$/', $contactNumberFieldValue, $phoneMatch)) {
    $contactNumberFieldValue = $phoneMatch[1];
} elseif (preg_match('/^0(9\d{9})$/', $contactNumberFieldValue, $phoneMatch)) {
    $contactNumberFieldValue = $phoneMatch[1];
} elseif (!preg_match('/^9\d{9}$/', $contactNumberFieldValue)) {
    $contactNumberFieldValue = '';
}
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
    <link rel="stylesheet" href="../CSS-Styles/Guest-End-CSS/GuestPage.css">
    <link rel="stylesheet" href="../CSS-Styles/NavbarFooterStyle.css">
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

        .process-stage-actions,
        .verification-stage {
            margin-top: 1.5rem;
            padding-top: 1.2rem;
            border-top: 1px solid rgba(254, 153, 60, 0.16);
        }

        .process-stage-actions {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .process-stage-note {
            margin: 0.55rem 0 0;
            color: var(--guest-muted);
            line-height: 1.6;
        }

        .verification-stage[hidden] {
            display: none !important;
        }

        .process-stage-heading {
            margin-top: 1.35rem;
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

        /* Guest-end consistency overrides */
        :root {
            --guest-paper: #ffffff;
            --guest-paper-soft: #fff8f1;
            --guest-sand: #fffaf5;
            --guest-ink: #2e2115;
            --guest-muted: #6b5e52;
            --guest-border: rgba(254, 153, 60, 0.34);
            --guest-accent: #fe993c;
            --guest-accent-dark: #de710c;
            --guest-shadow: 0 18px 36px rgba(58, 39, 23, 0.08);
        }

        body {
            background: #ffffff;
            color: var(--guest-ink);
            font-family: 'Geist', sans-serif;
        }

        .guest-shell {
            min-height: auto;
        }

        .guest-main {
            max-width: 1240px;
            padding: 2rem 1.25rem 2.75rem;
        }

        .page-return-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            min-height: 56px;
            margin-bottom: 1.1rem;
            padding: 0.95rem 1.9rem;
            border-radius: 999px;
            background: #f7efe3;
            color: var(--guest-accent-dark);
            text-decoration: none;
            font-family: 'Geist', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            box-shadow: none;
            transition: background-color 0.18s ease, color 0.18s ease, transform 0.18s ease;
        }

        .page-return-btn i {
            font-size: 1.1rem;
            transition: transform 0.18s ease;
        }

        .page-return-btn:hover,
        .page-return-btn:focus-visible {
            background: #f2e4d2;
            color: #b96416;
            transform: translateY(-1px);
        }

        .page-return-btn:hover i,
        .page-return-btn:focus-visible i {
            transform: translateX(-1px);
        }

        .page-return-btn:focus-visible {
            outline: 0;
        }

        .hero-layout {
            grid-template-columns: minmax(0, 1.08fr) minmax(320px, 0.92fr);
        }

        .hero-copy {
            padding: 1.6rem;
            background: var(--guest-paper);
            border: 2px solid var(--guest-border);
            border-radius: 1.6rem;
            box-shadow: var(--guest-shadow);
        }

        .hero-kicker {
            background: #fff1df;
            border-color: rgba(254, 153, 60, 0.24);
            color: var(--guest-accent-dark);
        }

        .hero-title {
            font-family: 'Charis SIL Bold', serif;
            font-size: clamp(2.2rem, 4vw, 3.8rem);
            line-height: 1;
            letter-spacing: -0.04em;
            color: var(--guest-accent);
            max-width: 11ch;
        }

        .hero-title strong {
            color: var(--guest-accent-dark);
        }

        .hero-points li {
            background: var(--guest-paper-soft);
            border: 1px solid rgba(254, 153, 60, 0.24);
            border-left: 6px solid rgba(254, 153, 60, 0.9);
            border-radius: 1.15rem;
            box-shadow: none;
        }

        .hero-points i {
            color: var(--guest-accent-dark);
        }

        .guest-card,
        .guide-card,
        .login-card {
            border: none !important;
            box-shadow: none !important;
        }

        .login-card {
            background:
                radial-gradient(circle at top right, rgba(255, 219, 176, 0.92), rgba(255, 219, 176, 0) 28%),
                linear-gradient(135deg, #fff9f2 0%, #ffffff 100%);
        }

        .login-card h2,
        .guide-card h3,
        .guest-card-header h2,
        .section-heading,
        .otp-panel-header h3 {
            font-family: 'Charis SIL Bold', serif;
            color: var(--guest-accent-dark);
        }

        .login-card h2 {
            font-size: 1.2rem;
        }

        .login-actions .btn {
            border-radius: 0.95rem;
            font-weight: 700;
        }

        .guide-card {
            background: linear-gradient(180deg, #ffffff 0%, #fffaf3 100%);
        }

        .guide-card h3 {
            font-size: 1.15rem;
        }

        .form-shell {
            margin-top: 1rem;
        }

        .guest-card {
            background: transparent !important;
            border-radius: 0;
            padding: 0;
        }

        .guest-card-header {
            margin-bottom: 1.1rem;
            padding-bottom: 0.95rem;
            border-bottom: 1px solid rgba(254, 153, 60, 0.16);
        }

        .guest-card-header > div {
            max-width: none;
        }

        .guest-card-header h2 {
            margin-bottom: 0.3rem;
            font-size: clamp(2rem, 2.8vw, 2.7rem);
            line-height: 1.05;
            letter-spacing: -0.03em;
            max-width: none;
        }

        .guest-card-header p {
            margin: 0.35rem 0 0;
            max-width: 82ch;
            font-size: 0.97rem;
            line-height: 1.55;
        }

        .page-form {
            display: grid;
            gap: 1.2rem;
        }

        .form-section-card {
            padding: 1.45rem 1.5rem 1.55rem;
            border: 1px solid rgba(254, 153, 60, 0.14);
            border-radius: 1.35rem;
            background:
                linear-gradient(180deg, rgba(255, 249, 242, 0.95) 0%, #ffffff 100%);
            box-shadow: 0 16px 32px rgba(58, 39, 23, 0.06);
        }

        .form-section-card--otp {
            background:
                radial-gradient(circle at top right, rgba(255, 224, 193, 0.46), rgba(255, 224, 193, 0) 32%),
                linear-gradient(180deg, rgba(255, 249, 242, 0.98) 0%, #ffffff 100%);
        }

        .form-section-card .form-row {
            margin-bottom: 1rem;
        }

        .form-section-card .form-row:last-of-type {
            margin-bottom: 0;
        }

        .section-heading {
            display: block;
            margin: 0 0 0.35rem;
            font-family: 'Charis SIL Bold', serif;
            font-size: 1.65rem;
            font-weight: 600;
            color: #212529;
            text-align: left;
        }

        .section-caption {
            margin: 0 0 1.15rem;
            max-width: 56ch;
            font-size: 0.95rem;
            line-height: 1.6;
            text-align: left;
        }

        .otp-panel {
            background: transparent !important;
            border: none !important;
            border-radius: 0;
            box-shadow: none !important;
            padding: 0;
        }

        .otp-panel-header h3 {
            font-size: 1.08rem;
        }

        .agreement-row {
            margin-top: 1.3rem;
            padding-top: 1.15rem;
            border-top: 1px solid rgba(254, 153, 60, 0.16);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .agreement-row .check-item {
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            flex: 1 1 320px;
            line-height: 1.6;
            color: var(--guest-muted);
        }

        .agreement-row .check-item input {
            margin-top: 0.2rem;
            flex: 0 0 auto;
        }

        .page-form .top-label {
            margin-bottom: 0.45rem;
            font-weight: 700;
            color: var(--guest-ink);
        }

        .page-form .form-control,
        .page-form .form-select,
        .page-form textarea {
            min-height: 48px;
            border-radius: 0.9rem;
            border: 1px solid rgba(120, 96, 72, 0.26);
            background-color: #ffffff;
            box-shadow: none;
        }

        .page-form textarea.form-control {
            min-height: 118px;
        }

        .phone-input-group {
            display: flex;
            align-items: stretch;
            width: 100%;
        }

        .phone-input-prefix {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 72px;
            padding: 0.8rem 0.95rem;
            border: 1px solid rgba(120, 96, 72, 0.26);
            border-right: 0;
            border-radius: 0.9rem 0 0 0.9rem;
            background: #fffaf3;
            color: var(--guest-accent-dark);
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
        }

        .phone-input-group .form-control {
            border-radius: 0 0.9rem 0.9rem 0 !important;
        }

        .phone-input-group:focus-within .phone-input-prefix {
            border-color: rgba(254, 153, 60, 0.78);
            box-shadow: 0 0 0 0.18rem rgba(254, 153, 60, 0.16);
        }

        #appointmentDate.form-control {
            background-color: #ffffff !important;
            color: var(--guest-ink);
            -webkit-appearance: none;
            appearance: none;
        }

        #appointmentDate + .resident-date-proxy-wrap .resident-date-proxy {
            background: #ffffff !important;
            border-color: rgba(120, 96, 72, 0.26) !important;
            color: var(--guest-ink) !important;
            box-shadow: none !important;
            -webkit-text-fill-color: var(--guest-ink);
        }

        #appointmentDate + .resident-date-proxy-wrap .resident-date-proxy[readonly],
        #appointmentDate + .resident-date-proxy-wrap .resident-date-proxy:hover,
        #appointmentDate + .resident-date-proxy-wrap .resident-date-proxy:focus {
            background: #ffffff !important;
            color: var(--guest-ink) !important;
            -webkit-text-fill-color: var(--guest-ink);
        }

        #appointmentDate + .resident-date-proxy-wrap .resident-date-proxy-icon {
            color: #6b7c93 !important;
        }

        .resident-date-modal .resident-date-calendar-day.is-selected,
        .resident-date-modal .resident-date-calendar-day.is-selected:hover,
        .resident-date-modal .resident-date-calendar-day.is-selected:focus,
        .resident-date-modal .resident-date-calendar-day.is-selected.is-today {
            background: linear-gradient(180deg, #f97316 0%, #ea580c 100%) !important;
            background-color: #ea580c !important;
            border-color: #ea580c !important;
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff;
            box-shadow: 0 10px 20px rgba(234, 88, 12, 0.18) !important;
        }

        #appointmentDate::-webkit-date-and-time-value,
        #appointmentDate::-webkit-datetime-edit,
        #appointmentDate::-webkit-datetime-edit-text,
        #appointmentDate::-webkit-datetime-edit-month-field,
        #appointmentDate::-webkit-datetime-edit-day-field,
        #appointmentDate::-webkit-datetime-edit-year-field {
            color: var(--guest-ink);
        }

        #appointmentDate:invalid::-webkit-datetime-edit {
            color: var(--guest-ink);
            opacity: 1;
        }

        .page-form .form-control:focus,
        .page-form .form-select:focus,
        .page-form textarea:focus {
            border-color: rgba(254, 153, 60, 0.78);
            box-shadow: 0 0 0 0.18rem rgba(254, 153, 60, 0.16);
        }

        .field-helper {
            margin-top: 0.45rem;
            color: var(--guest-muted);
            font-size: 0.92rem;
            line-height: 1.55;
        }

        .otp-recipient-card {
            min-height: 48px;
            display: flex;
            align-items: center;
            padding: 0.85rem 1rem;
            border: 1px solid rgba(120, 96, 72, 0.26);
            border-radius: 0.9rem;
            background: #ffffff;
            color: var(--guest-ink);
            font-weight: 700;
        }

        .otp-recipient-card.is-empty {
            color: var(--guest-muted);
            font-weight: 500;
        }

        .process-next-btn,
        .submit-btn {
            min-width: 210px;
            padding: 0.9rem 1.5rem;
            border-radius: 0.95rem;
            border: 1px solid var(--guest-accent);
            background: var(--guest-accent);
            color: #ffffff;
            font-weight: 700;
        }

        .process-next-btn:not(:disabled):hover,
        .submit-btn:not(:disabled):hover {
            box-shadow: 0 14px 28px rgba(222, 113, 12, 0.24);
        }

        .process-next-btn:disabled,
        .submit-btn:disabled {
            opacity: 0.72;
            cursor: not-allowed;
        }

        .appointment-outline-btn,
        .appointment-soft-btn,
        #verifyOtpBtn {
            border-radius: 0.9rem;
            font-weight: 700;
        }

        .appointment-outline-btn {
            border: 1px solid var(--guest-accent);
            background: #ffffff;
            color: var(--guest-accent-dark);
        }

        .appointment-outline-btn:hover,
        .appointment-outline-btn:focus {
            background: #fff4e6;
            border-color: var(--guest-accent-dark);
            color: var(--guest-accent-dark);
        }

        .appointment-soft-btn {
            border: 1px solid rgba(120, 96, 72, 0.24);
            background: #fffaf3;
            color: var(--guest-ink);
        }

        .appointment-soft-btn:hover,
        .appointment-soft-btn:focus {
            background: #fff2e2;
            color: var(--guest-ink);
        }

        #verifyOtpBtn {
            background: var(--guest-accent);
            border-color: var(--guest-accent);
            color: #ffffff;
        }

        #verifyOtpBtn:hover,
        #verifyOtpBtn:focus {
            background: var(--guest-accent-dark);
            border-color: var(--guest-accent-dark);
            color: #ffffff;
        }

        .appointment-modal .modal-content {
            border-radius: 1.35rem;
            border: 1px solid rgba(254, 153, 60, 0.28);
            overflow: hidden;
        }

        .appointment-modal .modal-header {
            border-bottom: 1px solid rgba(254, 153, 60, 0.16);
        }

        .appointment-modal .modal-title {
            font-family: 'Charis SIL Bold', serif;
            color: var(--guest-accent-dark);
        }

        .appointment-modal .modal-footer {
            border-top: 1px solid rgba(254, 153, 60, 0.16);
        }

        .appointment-modal--otp .modal-dialog {
            max-width: 760px;
            margin: 1.5rem auto;
        }

        .appointment-modal--otp .modal-content {
            border-radius: 1.9rem;
            border: 1px solid rgba(254, 153, 60, 0.42);
            box-shadow: 0 28px 72px rgba(58, 39, 23, 0.22);
            max-height: calc(100vh - 3rem);
        }

        .appointment-modal--otp .modal-header {
            padding: 1.35rem 1.55rem 1.05rem;
        }

        .appointment-modal--otp .modal-title {
            font-size: clamp(1.7rem, 2.2vw, 2.25rem);
            line-height: 1.02;
        }

        .appointment-modal--otp .btn-close {
            width: 2.4rem;
            height: 2.4rem;
            border-radius: 999px;
            background-color: rgba(254, 153, 60, 0.1);
            opacity: 1;
        }

        .appointment-modal--otp .modal-body {
            padding: 1.45rem 1.55rem 1.6rem;
            background:
                radial-gradient(circle at top right, rgba(255, 224, 193, 0.28), rgba(255, 224, 193, 0) 32%),
                linear-gradient(180deg, rgba(255, 249, 242, 0.98) 0%, #ffffff 100%);
            overflow-y: auto;
        }

        .appointment-modal--otp .agreement-row {
            margin-bottom: 0;
        }

        .otp-modal-shell {
            display: grid;
            gap: 1rem;
        }

        .otp-modal-intro {
            margin: 0;
            max-width: 34rem;
            color: var(--guest-muted);
            font-size: 0.95rem;
            line-height: 1.65;
        }

        .otp-modal-card {
            padding: 1.15rem 1.2rem;
            border-radius: 1.35rem;
            border: 1px solid rgba(254, 153, 60, 0.18);
            background: rgba(255, 255, 255, 0.84);
            box-shadow: 0 10px 24px rgba(58, 39, 23, 0.05);
        }

        .otp-panel-header--modal {
            margin-bottom: 0.95rem;
        }

        .otp-panel-header--modal p {
            margin-top: 0;
            max-width: 32rem;
        }

        .otp-recipient-card {
            min-height: 60px;
            gap: 0.85rem;
            padding: 0.95rem 1rem;
            border-color: rgba(254, 153, 60, 0.2);
            border-radius: 1rem;
            background: linear-gradient(180deg, #ffffff 0%, #fff8f1 100%);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
        }

        .otp-recipient-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 999px;
            background: rgba(254, 153, 60, 0.14);
            color: var(--guest-accent-dark);
            flex: 0 0 auto;
        }

        .otp-recipient-value {
            font-size: 1.15rem;
            letter-spacing: -0.02em;
        }

        .otp-helper {
            margin-top: 0.65rem;
            font-size: 0.88rem;
            line-height: 1.55;
        }

        .appointment-modal--otp .otp-actions,
        .appointment-modal--otp .otp-verify-row {
            gap: 1rem;
        }

        .appointment-modal--otp .otp-actions {
            align-items: stretch;
        }

        .appointment-modal--otp .otp-actions .btn,
        .appointment-modal--otp .otp-verify-row .btn {
            min-height: 56px;
            padding-inline: 1.35rem;
            border-radius: 1rem;
        }

        .appointment-modal--otp .otp-verify-row {
            padding: 1rem;
            border-radius: 1.2rem;
            background: rgba(255, 247, 237, 0.9);
            border: 1px solid rgba(254, 153, 60, 0.16);
        }

        .appointment-modal--otp .otp-feedback,
        .appointment-modal--otp .otp-error {
            margin-top: 0.9rem;
            padding: 0.8rem 0.95rem;
            border-radius: 0.95rem;
            line-height: 1.5;
        }

        .appointment-modal--otp .otp-feedback {
            background: rgba(31, 122, 79, 0.1);
            border: 1px solid rgba(31, 122, 79, 0.14);
        }

        .appointment-modal--otp .otp-error {
            background: rgba(180, 35, 24, 0.08);
            border: 1px solid rgba(180, 35, 24, 0.12);
        }

        .otp-cert-card {
            padding: 1.05rem 1.1rem 1.15rem;
            border-radius: 1.35rem;
            border: 1px solid rgba(254, 153, 60, 0.16);
            background: rgba(255, 255, 255, 0.86);
        }

        .otp-cert-copy {
            margin: 0 0 0.7rem;
            color: var(--guest-muted);
            line-height: 1.62;
        }

        .appointment-modal--otp .agreement-row {
            margin-top: 0;
            padding-top: 1rem;
        }

        .appointment-modal--otp .agreement-row .check-item {
            gap: 0.85rem;
            align-items: flex-start;
        }

        .appointment-modal--otp .agreement-row .check-item input {
            width: 1.18rem;
            height: 1.18rem;
            margin-top: 0.15rem;
            flex: 0 0 auto;
            position: static !important;
            border-radius: 0.28rem !important;
            border: 1px solid rgba(120, 96, 72, 0.34) !important;
            background: #ffffff !important;
            box-shadow: none !important;
            transform: none !important;
            appearance: auto !important;
            -webkit-appearance: checkbox !important;
            accent-color: var(--guest-accent);
        }

        .appointment-modal--otp .agreement-row .check-item input::before,
        .appointment-modal--otp .agreement-row .check-item input::after {
            content: none !important;
            display: none !important;
        }

        .appointment-modal--otp .submit-btn {
            min-width: 280px;
        }

        @media (max-width: 767.98px) {
            .appointment-modal--otp .modal-header,
            .appointment-modal--otp .modal-body {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .appointment-modal--otp .otp-actions,
            .appointment-modal--otp .otp-verify-row {
                grid-template-columns: 1fr;
            }

            .appointment-modal--otp .submit-btn {
                width: 100%;
                min-width: 0;
            }
        }

        .verification-stage {
            margin-top: 0;
        }

        @media (max-width: 767.98px) {
            .guest-main {
                padding: 1.35rem 1rem 2.5rem;
            }

            .guest-card-header {
                margin-bottom: 1.4rem;
                padding-bottom: 1rem;
            }

            .guest-card-header h2 {
                font-size: 2rem;
            }

            .hero-copy {
                padding: 1.2rem 1rem;
            }

            .form-section-card {
                padding: 1.15rem 1rem 1.2rem;
                border-radius: 1.1rem;
            }

            .section-heading {
                font-size: 1.35rem;
            }

            .agreement-row {
                align-items: stretch;
            }

            .process-next-btn,
            .submit-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <button onclick="topFunction()" id="goToTop" title="Go to top"><i class="fa-solid fa-arrow-up"></i>&nbsp;&nbsp;Go to top</button>

    <div class="navbarWrapper">
        <nav class="navbar navbar-expand-xl align-items-center navbar-light bg-white shadow-sm">
            <div class="container-fluid align-items-center px-4">
                <a id="navbarBrand" class="navbar-brand" href="<?= htmlspecialchars(appUrl('/'), ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= htmlspecialchars(appUrl('/Images/San_Jose_LOGO.jpg'), ENT_QUOTES, 'UTF-8') ?>" alt="Logo" id="navbarLogo" class="d-inline-block align-text-center">
                    Barangay San Jose
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul id="navbarLinks" class="navbar-nav ms-auto">
                        <li class="nav-item mx-lg-3">
                            <a class="nav-link" href="<?= htmlspecialchars(appUrl('/'), ENT_QUOTES, 'UTF-8') ?>">Home</a>
                        </li>
                        <li class="nav-item mx-lg-3">
                            <a class="nav-link" href="<?= htmlspecialchars(appUrl('/government'), ENT_QUOTES, 'UTF-8') ?>">Government</a>
                        </li>
                        <li class="nav-item mx-lg-3">
                            <a class="nav-link active" aria-current="page" href="<?= htmlspecialchars(appUrl('/services'), ENT_QUOTES, 'UTF-8') ?>">Services</a>
                        </li>
                        <li class="nav-item mx-lg-3">
                            <a class="nav-link" href="<?= htmlspecialchars(appUrl('/news'), ENT_QUOTES, 'UTF-8') ?>">News</a>
                        </li>
                        <li class="nav-item mx-lg-3">
                            <a class="nav-link" href="<?= htmlspecialchars(appUrl('/faq'), ENT_QUOTES, 'UTF-8') ?>">FAQ</a>
                        </li>
                        <li class="nav-item mx-lg-3">
                            <a class="nav-link" href="<?= htmlspecialchars(appUrl('/contact'), ENT_QUOTES, 'UTF-8') ?>">Contact</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-orange text-white px-4 ms-2" href="<?= htmlspecialchars(appUrl('/login'), ENT_QUOTES, 'UTF-8') ?>">Login</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </div>

    <div class="guest-shell">
        <main class="guest-main">
            <section class="form-shell">
                <div class="guest-card">
                    <div class="guest-card-header">
                        <div>
                            <a class="page-return-btn" href="<?= htmlspecialchars(appUrl('/services'), ENT_QUOTES, 'UTF-8') ?>">
                                <i class="bi bi-arrow-left"></i>
                                Return to Services
                            </a>
                            <h2>Guest Appointment Form</h2>
                            <p>All fields marked with <span class="required-asterisk">*</span> are required. Complete OTP verification before submitting the request.</p>
                        </div>
                    </div>

                    <?php if ($feedbackMessage !== '' && $feedbackType !== 'success'): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= htmlspecialchars($feedbackMessage, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <form id="guestAppointmentForm" class="page-form mb-0" method="POST" action="<?= htmlspecialchars(appUrl('/PhpFiles/Guest-End/submitGuestAppointment.php'), ENT_QUOTES, 'UTF-8') ?>">
                        <?= csrfTokenField() ?>
                        <input type="hidden" name="action" value="submit_guest_appointment">

                        <section class="form-section-card">
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
                                <label class="top-label" for="guestCurrentAddress">Current Address <span class="required-asterisk">*</span></label>
                                <textarea
                                    class="form-control"
                                    id="guestCurrentAddress"
                                    name="current_address"
                                    rows="3"
                                    placeholder="House number, street, barangay, municipality/city, province"
                                    required
                                ><?= htmlspecialchars($formValues['current_address'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                <div class="field-helper">Enter the full current address of the person who will attend the appointment.</div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label" for="guestContactNumber">Mobile Number <span class="required-asterisk">*</span></label>
                                <div class="phone-input-group">
                                    <span class="phone-input-prefix">+63</span>
                                    <input
                                        type="text"
                                    class="form-control"
                                    id="guestContactNumber"
                                    name="contact_number"
                                    inputmode="numeric"
                                    autocomplete="tel-national"
                                    maxlength="10"
                                    placeholder="9XXXXXXXXX"
                                    value="<?= htmlspecialchars($contactNumberFieldValue, ENT_QUOTES, 'UTF-8') ?>"
                                    required
                                    >
                                </div>
                                <div class="field-helper">Use your mobile number after +63. This will be used for OTP verification and appointment updates.</div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="full-width">
                                <label class="top-label">Email Address</label>
                                <input type="email" class="form-control" id="guestEmailAddress" name="email_address" value="<?= htmlspecialchars($formValues['email_address'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Optional">
                            </div>
                        </div>
                        </section>

                        <section class="form-section-card">
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
                                <label class="top-label">Subject of Appointment <span class="required-asterisk">*</span></label>
                                <select class="form-select" name="subject" id="appointmentSubject" required>
                                    <option value="">Select</option>
                                    <option value="follow_up" <?= $formValues['subject'] === 'follow_up' ? 'selected' : '' ?>>Follow-up Concern</option>
                                    <option value="consultation" <?= $formValues['subject'] === 'consultation' ? 'selected' : '' ?>>Consultation</option>
                                    <option value="event_coordination" <?= $formValues['subject'] === 'event_coordination' ? 'selected' : '' ?>>Event Coordination</option>
                                    <option value="other" <?= $formValues['subject'] === 'other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row<?= $formValues['subject'] === 'other' ? '' : ' d-none' ?>" id="appointmentSubjectOtherWrap">
                            <div class="full-width">
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
                                <input type="checkbox" id="appointmentCertification" required>I hereby certify that the information provided is true and correct, and I understand that my mobile number will be used for appointment updates.
                            </label>
                        </div>

                        <div class="process-stage-actions" id="appointmentDetailsActions">
                            <div>
                                <p class="process-stage-note">Confirm the statement above, then continue to OTP verification.</p>
                            </div>
                            <button type="button" class="btn process-next-btn" id="appointmentNextBtn" <?= $hasAppointmentAvailability ? '' : 'disabled' ?>>NEXT</button>
                        </div>
                        </section>

                        <div class="modal fade" id="appointmentVerificationStage" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
                            <div class="modal-dialog modal-dialog-centered appointment-modal appointment-modal--otp">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Mobile OTP Verification</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="otp-modal-shell">
                                            <p class="otp-modal-intro">We will send a 6-digit code to the mobile number you entered above. The appointment cannot be submitted until that number is verified.</p>

                                            <div class="otp-panel otp-modal-card">
                                                <div class="otp-panel-header otp-panel-header--modal">
                                                    <div>
                                                        <h3>Send Code</h3>
                                                        <p>Check the mobile number below before sending the OTP.</p>
                                                    </div>
                                                    <div class="otp-verified-pill d-none" id="otpVerifiedPill">
                                                        <i class="bi bi-check-circle-fill"></i> Verified
                                                    </div>
                                                </div>

                                                <div class="otp-actions">
                                                    <div>
                                                        <div class="top-label">Mobile Number for OTP</div>
                                                        <div class="otp-recipient-card">
                                                            <span class="otp-recipient-icon"><i class="bi bi-phone-fill"></i></span>
                                                            <span class="otp-recipient-value" id="otpRecipientPreview">Enter your mobile number above first.</span>
                                                        </div>
                                                        <div class="otp-helper">If you need to change it, use the mobile number field in Guest Information.</div>
                                                    </div>
                                                    <div class="d-flex gap-2 flex-wrap">
                                                        <button type="button" class="btn appointment-outline-btn" id="sendOtpBtn">Send OTP</button>
                                                        <button type="button" class="btn appointment-soft-btn d-none" id="changeOtpNumberBtn">Change Number</button>
                                                    </div>
                                                </div>

                                                <div class="otp-verify-row" id="otpVerifyRow" hidden>
                                                    <div>
                                                        <label class="top-label">Enter OTP <span class="required-asterisk">*</span></label>
                                                        <input type="text" class="form-control" id="guestOtpInput" inputmode="numeric" maxlength="6" placeholder="6-digit OTP">
                                                    </div>
                                                    <div>
                                                        <button type="button" class="btn w-100" id="verifyOtpBtn">Verify OTP</button>
                                                    </div>
                                                </div>

                                                <div class="otp-feedback d-none" id="otpFeedback" aria-live="polite"></div>
                                                <div class="otp-error d-none" id="otpError" aria-live="polite"></div>
                                            </div>

                                            <div class="otp-cert-card">
                                                <p class="otp-cert-copy">Once your mobile number is verified, you can confirm your appointment below.</p>
                                                <button
                                                    type="submit"
                                                    class="submit-btn"
                                                    data-default-label="CONFIRM APPOINTMENT"
                                                    data-loading-label="Confirming..."
                                                    <?= $hasAppointmentAvailability ? '' : 'disabled' ?>
                                                >
                                                    <span class="submit-btn-label">CONFIRM APPOINTMENT</span>
                                                    <span class="submit-btn-spinner" aria-hidden="true"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </section>
        </main>
    </div>

    <div class="footerWrapper">
        <footer id="footer">
            <div class="container">
                <div class="row">
                    <div class="col-8">
                        <img src="<?= htmlspecialchars(appUrl('/Images/San_Jose_LOGO.jpg'), ENT_QUOTES, 'UTF-8') ?>" alt="Logo" id="footerLogo" class="imgfluid rounded-circle p-3">
                    </div>
                    <div class="col">
                        <div class="footerText">
                            <h5>Quick Links</h5>
                            <ul class="list-unstyled">
                                <li><a id="footerLink" class="link-offset-2 link-underline-light link-underline-opacity-0 link-underline-opacity-75-hover" href="https://www.facebook.com/BarangaySanJoseRodriguezRizal">Facebook</a></li>
                                <li><a id="footerLink" class="link-offset-2 link-underline-light link-underline-opacity-0 link-underline-opacity-75-hover" href="<?= htmlspecialchars(appUrl('/contact'), ENT_QUOTES, 'UTF-8') ?>">Contact Us</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col">
                        <div class="footerText">
                            <h5>Barangay Info</h5>
                            <ul class="list-unstyled">
                                <li><a id="footerLink" class="link-offset-2 link-underline-light link-underline-opacity-0 link-underline-opacity-75-hover" href="<?= htmlspecialchars(appUrl('/privacy#privacy'), ENT_QUOTES, 'UTF-8') ?>">Privacy Policy</a></li>
                                <li><a id="footerLink" class="link-offset-2 link-underline-light link-underline-opacity-0 link-underline-opacity-75-hover" href="<?= htmlspecialchars(appUrl('/privacy#terms'), ENT_QUOTES, 'UTF-8') ?>">Terms & Conditions</a></li>
                                <li><a id="footerLink" class="link-offset-2 link-underline-light link-underline-opacity-0 link-underline-opacity-75-hover" href="<?= htmlspecialchars(appUrl('/privacy#disclaimer'), ENT_QUOTES, 'UTF-8') ?>">Disclaimers</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <div
        id="appointmentFeedbackData"
        data-feedback-type="<?= htmlspecialchars($feedbackType, ENT_QUOTES, 'UTF-8') ?>"
        data-feedback-message="<?= htmlspecialchars($feedbackMessage, ENT_QUOTES, 'UTF-8') ?>"
        data-feedback-appointment-id="<?= htmlspecialchars($feedbackAppointmentId, ENT_QUOTES, 'UTF-8') ?>"
        hidden
    ></div>

    <div class="modal fade" id="appointmentSuccessModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered appointment-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Appointment Received</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2" id="appointmentSuccessMessage">Your appointment request has been received.</p>
                    <p class="mb-0 text-muted" id="appointmentSuccessHint">Keep your reference number in case you need to follow up later.</p>
                    <div class="mt-3 d-none" id="appointmentSuccessReferenceWrap">
                        <div class="small text-uppercase fw-semibold text-muted">Reference Number</div>
                        <div class="fs-4 fw-bold" id="appointmentSuccessReference"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(appUrl('/Guest-End/services.html'), ENT_QUOTES, 'UTF-8') ?>">Back to Services</a>
                    <button type="button" class="btn btn-orange text-white" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../JS-Script-Files/Resident-End/dateFieldModal.js"></script>
    <script>
        let topBtn = document.getElementById("goToTop");

        window.onscroll = function() {
            scrollFunction();
        };

        function scrollFunction() {
            if (!topBtn) {
                return;
            }
            if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
                topBtn.style.display = "block";
            } else {
                topBtn.style.display = "none";
            }
        }

        function topFunction() {
            document.body.scrollTop = 0;
            document.documentElement.scrollTop = 0;
        }
    </script>
    <script>
        document.addEventListener("click", function(event) {
            var navbar = document.getElementById("navbarNav");
            var toggler = document.querySelector(".navbar-toggler");
            if (!navbar || !toggler) {
                return;
            }
            var isShown = navbar.classList.contains("show");
            if (!isShown) {
                return;
            }
            var clickedInside = navbar.contains(event.target) || toggler.contains(event.target);
            if (!clickedInside) {
                var collapse = bootstrap.Collapse.getOrCreateInstance(navbar);
                collapse.hide();
            }
        });
    </script>
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
            const appointmentSubjectOtherWrap = document.getElementById("appointmentSubjectOtherWrap");
            const appointmentSubjectOtherInput = document.getElementById("appointmentSubjectOther");
            const appointmentSubjectOtherError = document.getElementById("appointmentSubjectOtherError");
            const appointmentCertificationInput = document.getElementById("appointmentCertification");
            const appointmentFeedbackData = document.getElementById("appointmentFeedbackData");
            const appointmentSuccessModalEl = document.getElementById("appointmentSuccessModal");
            const appointmentSuccessMessage = document.getElementById("appointmentSuccessMessage");
            const appointmentSuccessHint = document.getElementById("appointmentSuccessHint");
            const appointmentSuccessReferenceWrap = document.getElementById("appointmentSuccessReferenceWrap");
            const appointmentSuccessReference = document.getElementById("appointmentSuccessReference");
            const contactNumberInput = document.getElementById("guestContactNumber");
            const otpRecipientPreview = document.getElementById("otpRecipientPreview");
            const emailInput = document.getElementById("guestEmailAddress");
            const appointmentDetailsActions = document.getElementById("appointmentDetailsActions");
            const appointmentNextBtn = document.getElementById("appointmentNextBtn");
            const appointmentVerificationStage = document.getElementById("appointmentVerificationStage");
            const appointmentVerificationModal = appointmentVerificationStage && window.bootstrap
                ? bootstrap.Modal.getOrCreateInstance(appointmentVerificationStage, {
                    backdrop: "static",
                    keyboard: true,
                })
                : null;
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
            let otpStageVisible = false;
            let otpCountdown = 0;
            let otpCountdownTimer = null;
            let focusOtpOnModalOpen = false;
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

            const normalizePhoneDraft = (value) => {
                let digits = String(value || "").replace(/\D+/g, "");
                if (digits.startsWith("63")) {
                    digits = digits.slice(2);
                } else if (digits.startsWith("0")) {
                    digits = digits.slice(1);
                }
                if (digits.length > 10) {
                    digits = digits.slice(0, 10);
                }
                return digits;
            };

            const normalizePhone = (value) => {
                const digits = normalizePhoneDraft(value);
                return /^9\d{9}$/.test(digits) ? digits : "";
            };

            const validateContactNumber = () => {
                if (!contactNumberInput) return true;
                const normalized = normalizePhone(contactNumberInput.value);
                if (normalized === "") {
                    contactNumberInput.setCustomValidity("Please enter a valid mobile number in the format +63 9XXXXXXXXX.");
                    return false;
                }
                if (contactNumberInput.value !== normalized) {
                    contactNumberInput.value = normalized;
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

            const syncOtpRecipientPreview = () => {
                if (!otpRecipientPreview) {
                    return;
                }
                const normalized = normalizePhone(contactNumberInput?.value || "");
                const displayValue = normalized !== "" ? `+63 ${normalized}` : "Enter your mobile number above first.";
                otpRecipientPreview.textContent = displayValue;
                otpRecipientPreview.classList.toggle("is-empty", normalized === "");
            };

            const validateDetailsStep = () => {
                const okSubjectOther = validateSubjectOther();
                const okDate = validateAppointmentDate();
                const okTime = validateAppointmentTime();
                const okPhone = validateContactNumber();
                const okEmail = validateEmail();
                const okCertification = appointmentCertificationInput ? appointmentCertificationInput.checkValidity() : true;

                if (!okPhone) {
                    contactNumberInput?.reportValidity();
                    return false;
                }
                if (!okEmail) {
                    emailInput?.reportValidity();
                    return false;
                }
                if (!okSubjectOther) {
                    appointmentSubjectOtherInput?.reportValidity();
                    return false;
                }
                if (!okDate) {
                    appointmentDateInput?.reportValidity();
                    return false;
                }
                if (!okTime) {
                    appointmentTimeInput?.reportValidity();
                    return false;
                }
                if (!okCertification) {
                    appointmentCertificationInput?.reportValidity();
                    appointmentCertificationInput?.focus();
                    return false;
                }

                const detailFields = Array.from(form.querySelectorAll("input, select, textarea")).filter((field) => {
                    if (!(field instanceof HTMLElement)) {
                        return false;
                    }
                    if (appointmentVerificationStage?.contains(field)) {
                        return false;
                    }
                    if (field instanceof HTMLInputElement && field.type === "hidden") {
                        return false;
                    }
                    if ("disabled" in field && field.disabled) {
                        return false;
                    }
                    if ("readOnly" in field && field.readOnly) {
                        return false;
                    }
                    return true;
                });

                const firstInvalidField = detailFields.find((field) => !field.checkValidity());
                if (firstInvalidField) {
                    firstInvalidField.reportValidity();
                    return false;
                }

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

            const showVerificationStage = (focusOtp = false) => {
                otpStageVisible = true;
                focusOtpOnModalOpen = focusOtp;
                updateState();
                if (appointmentVerificationModal) {
                    appointmentVerificationModal.show();
                    return;
                }
                window.setTimeout(() => {
                    if (focusOtp) {
                        sendOtpBtn?.focus();
                    }
                }, 180);
            };

            const advanceToVerificationStage = () => {
                if (!validateDetailsStep()) {
                    return false;
                }
                showVerificationStage(true);
                return true;
            };

            const updateState = () => {
                if (isSubmitting) {
                    submitBtn.disabled = true;
                    return;
                }
                syncOtpRecipientPreview();
                syncAppointmentAvailability();
                validateSubjectOther();
                validateAppointmentDate();
                validateAppointmentTime();
                validateContactNumber();
                validateEmail();
                updateOtpButtons();
                const hasCouncilMemberSelection = !appointmentCouncilMemberInput || !appointmentCouncilMemberInput.disabled;
                const hasAppointmentWindow = minAllowedIso !== "" && maxAllowedIso !== "";
                if (appointmentNextBtn) {
                    appointmentNextBtn.disabled = !hasConfiguredAppointmentAvailability;
                }
                submitBtn.disabled = !hasConfiguredAppointmentAvailability || !otpVerified || !hasCouncilMemberSelection || !hasAppointmentWindow || !form.checkValidity();
            };

            appointmentNextBtn?.addEventListener("click", () => {
                advanceToVerificationStage();
            });

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
                    showOtpFeedback("Mobile number verified. You can now confirm your appointment.");
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
                appointmentVerificationModal?.hide();
                window.setTimeout(() => {
                    contactNumberInput?.scrollIntoView({ behavior: "smooth", block: "center" });
                    contactNumberInput?.focus();
                }, 180);
            });

            contactNumberInput?.addEventListener("input", () => {
                const draftValue = normalizePhoneDraft(contactNumberInput.value);
                if (contactNumberInput.value !== draftValue) {
                    contactNumberInput.value = draftValue;
                }
                if (!otpVerified) {
                    clearOtpMessages();
                }
                updateState();
            });
            contactNumberInput?.addEventListener("blur", validateContactNumber);
            contactNumberInput?.addEventListener("change", validateContactNumber);
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
            appointmentVerificationStage?.addEventListener("shown.bs.modal", () => {
                otpStageVisible = true;
                updateState();
                if (focusOtpOnModalOpen) {
                    window.setTimeout(() => {
                        sendOtpBtn?.focus();
                    }, 120);
                }
                focusOtpOnModalOpen = false;
            });
            appointmentVerificationStage?.addEventListener("hidden.bs.modal", () => {
                otpStageVisible = false;
                focusOtpOnModalOpen = false;
            });

            form.addEventListener("submit", (e) => {
                const okDetails = validateDetailsStep();
                if (!okDetails) {
                    e.preventDefault();
                    setSubmittingState(false);
                    return;
                }
                if (!otpStageVisible) {
                    e.preventDefault();
                    advanceToVerificationStage();
                    setSubmittingState(false);
                    return;
                }

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
                    if (!okPhone) {
                        contactNumberInput?.reportValidity();
                        contactNumberInput?.focus();
                    }
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
                    appointmentSuccessMessage.textContent = feedbackMessage || "Your appointment is all set.";
                }
                if (appointmentSuccessHint) {
                    appointmentSuccessHint.textContent = feedbackAppointmentId !== ""
                        ? "Keep your reference number in case you need to follow up later."
                        : "You may close this window or head back to services.";
                }
                if (appointmentSuccessReferenceWrap && appointmentSuccessReference) {
                    appointmentSuccessReferenceWrap.classList.toggle("d-none", feedbackAppointmentId === "");
                    appointmentSuccessReference.textContent = feedbackAppointmentId;
                }
                const successModal = bootstrap.Modal.getOrCreateInstance(appointmentSuccessModalEl, {
                    backdrop: "static",
                    keyboard: false,
                });
                successModal.show();
            }

            syncOtpRecipientPreview();
            syncAppointmentAvailability();
            resetOtpVerification();
            setSubmittingState(false);
            updateState();
        });
    </script>
</body>
</html>
