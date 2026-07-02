<?php
require_once __DIR__ . '/../General/security.php';
require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/appointmentSubmissionShared.php';

verifyCsrfToken(false);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

apos_schedule_ensure_storage($conn);

if (!apsh_table_exists($conn, 'appointmentstbl')) {
    http_response_code(500);
    exit('Appointment table is not available. Run the appointment migration first.');
}

$action = trim((string)($_POST['action'] ?? 'submit_guest_appointment'));
if ($action !== 'submit_guest_appointment') {
    http_response_code(400);
    exit('Unknown appointment action.');
}

$firstName = trim((string)($_POST['first_name'] ?? ''));
$middleName = trim((string)($_POST['middle_name'] ?? ''));
$lastName = trim((string)($_POST['last_name'] ?? ''));
$suffix = trim((string)($_POST['suffix_name'] ?? ''));
$contactNumber = apsh_normalize_phone((string)($_POST['contact_number'] ?? ''));
$currentAddress = preg_replace('/\s+/', ' ', trim((string)($_POST['current_address'] ?? '')));
$emailAddress = apsh_normalize_email((string)($_POST['email_address'] ?? ''));
$officialUserId = trim((string)($_POST['official_user_id'] ?? ''));
$subject = strtolower(trim((string)($_POST['subject'] ?? '')));
$subjectOther = preg_replace('/\s+/', ' ', trim((string)($_POST['subject_other'] ?? '')));
$appointmentDate = trim((string)($_POST['appointment_date'] ?? ''));
$appointmentTime = trim((string)($_POST['appointment_time'] ?? ''));
$purpose = preg_replace('/\s+/', ' ', trim((string)($_POST['purpose'] ?? '')));

if ($firstName === '' || $lastName === '' || $contactNumber === null || $currentAddress === '' || $officialUserId === '' || $subject === '' || $appointmentDate === '' || $appointmentTime === '' || $purpose === '') {
    apsh_redirect_with_message('/Guest-End/appointments.php', 'error', 'Please complete all required appointment fields.');
}

if (!in_array($subject, ['follow_up', 'consultation', 'event_coordination', 'other'], true)) {
    apsh_redirect_with_message('/Guest-End/appointments.php', 'error', 'Please select a valid subject of appointment.');
}

if ($subject === 'other' && $subjectOther === '') {
    apsh_redirect_with_message('/Guest-End/appointments.php', 'error', 'Please specify the subject when Other is selected.');
}

$otpSession = $_SESSION['guest_appointment_otp_verified'] ?? null;
$otpPhoneKey = apsh_phone_otp_key($contactNumber);
if (
    !is_array($otpSession)
    || !isset($otpSession['phone'], $otpSession['verified_at'])
    || !is_string($otpSession['phone'])
    || $otpPhoneKey === null
    || !hash_equals((string)$otpSession['phone'], $otpPhoneKey)
    || (time() - (int)$otpSession['verified_at']) > 900
) {
    unset($_SESSION['guest_appointment_otp_verified']);
    apsh_redirect_with_message('/Guest-End/appointments.php', 'error', 'Please verify your mobile number through OTP before submitting your appointment.');
}

$appointmentSettings = aps_settings_load($conn);
$councilMembersByUserId = apcm_fetch_council_members_by_user_id($conn);
if ($councilMembersByUserId === []) {
    apsh_redirect_with_message('/Guest-End/appointments.php', 'error', 'No active barangay council members are currently available for appointments.');
}

if (!isset($councilMembersByUserId[$officialUserId])) {
    apsh_redirect_with_message('/Guest-End/appointments.php', 'error', 'Please select a valid barangay council member for your appointment.');
}

try {
    $validatedSchedule = apsh_validate_schedule_selection(
        $conn,
        $appointmentSettings,
        $officialUserId,
        $appointmentDate,
        $appointmentTime
    );
} catch (Throwable $e) {
    apsh_redirect_with_message('/Guest-End/appointments.php', 'error', $e->getMessage() !== '' ? $e->getMessage() : 'Unable to validate the appointment schedule.');
}

$fullName = trim(implode(' ', array_values(array_filter([
    $firstName,
    $middleName,
    $lastName,
    $suffix,
], static fn(string $part): bool => $part !== ''))));
if ($fullName === '') {
    apsh_redirect_with_message('/Guest-End/appointments.php', 'error', 'A valid guest name is required.');
}

$appointmentId = GenerateAppointmentID($conn);
if (!$appointmentId) {
    apsh_redirect_with_message('/Guest-End/appointments.php', 'error', 'Unable to generate an appointment ID right now.');
}

try {
    $statusId = apsh_ensure_status_id($conn, 'Approved', 'Appointment');
    $officialContact = apsh_load_official_delivery_contact($conn, $officialUserId, $councilMembersByUserId[$officialUserId] ?? []);
} catch (Throwable $e) {
    apsh_redirect_with_message('/Guest-End/appointments.php', 'error', 'Unable to prepare the assigned official details for this appointment.');
}

$appointmentColumns = apsh_get_table_columns($conn, 'appointmentstbl');
if (!isset($appointmentColumns['user_id_official_assigned'])) {
    apsh_redirect_with_message('/Guest-End/appointments.php', 'error', 'The appointment module is missing the council member assignment field. Please run the latest appointment migration first.');
}

$subjectLabel = apsh_normalize_subject_label($subject);
$normalizedSubjectOther = $subject === 'other' ? $subjectOther : null;
$notes = [];
if ($currentAddress !== '') {
    $notes[] = 'Guest address: ' . $currentAddress;
}
if ($subject === 'other' && $subjectOther !== '') {
    $notes[] = 'Other subject detail: ' . $subjectOther;
}
$notes[] = 'Booking source: Guest OTP';
$residentNotes = implode("\n", $notes);

$preferredScheduleTimestamp = (string)($validatedSchedule['preferred_schedule_timestamp'] ?? '');
$confirmedScheduleTimestamp = (string)($validatedSchedule['confirmed_schedule_timestamp'] ?? '');
$meetingLocation = (string)($validatedSchedule['meeting_location'] ?? '');
$now = new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get() ?: 'Asia/Manila'));
$assignedOfficial = $councilMembersByUserId[$officialUserId] ?? [];
$officialDisplayName = trim((string)($assignedOfficial['full_name'] ?? ''));
if ($officialDisplayName === '') {
    $officialDisplayName = trim((string)($assignedOfficial['option_label'] ?? ''));
}
$scheduleLabel = '';
if ($confirmedScheduleTimestamp !== '') {
    try {
        $scheduleDate = new DateTimeImmutable($confirmedScheduleTimestamp, new DateTimeZone(date_default_timezone_get() ?: 'Asia/Manila'));
        $scheduleLabel = $scheduleDate->format('F j, Y \a\t g:i A');
    } catch (Throwable $e) {
        $scheduleLabel = '';
    }
}

$conn->begin_transaction();
try {
    if (apsh_slot_is_already_booked($conn, $appointmentColumns, $officialUserId, $confirmedScheduleTimestamp)) {
        throw new RuntimeException('The selected schedule was just taken. Please choose another available appointment slot.');
    }

    $insertColumns = [
        'appointment_id',
        'user_id_resident',
        'name',
        'contact_number',
        'user_id_official_assigned',
        'subject',
        'subject_other',
        'purpose',
    ];
    $insertValues = [
        $appointmentId,
        null,
        $fullName,
        $contactNumber,
        $officialUserId,
        $subjectLabel,
        $normalizedSubjectOther,
        $purpose,
    ];
    $bindTypes = 'ssssssss';

    if (isset($appointmentColumns['email_address'])) {
        $insertColumns[] = 'email_address';
        $insertValues[] = $emailAddress !== '' ? $emailAddress : null;
        $bindTypes .= 's';
    }

    if (isset($appointmentColumns['booking_channel'])) {
        $insertColumns[] = 'booking_channel';
        $insertValues[] = 'guest_otp';
        $bindTypes .= 's';
    }

    if (isset($appointmentColumns['preferred_schedule_timestamp'])) {
        $insertColumns[] = 'preferred_schedule_timestamp';
        $insertValues[] = $preferredScheduleTimestamp;
        $bindTypes .= 's';
    } elseif (isset($appointmentColumns['schedule_timestamp'])) {
        $insertColumns[] = 'schedule_timestamp';
        $insertValues[] = $preferredScheduleTimestamp;
        $bindTypes .= 's';
    } else {
        throw new RuntimeException('Appointment schedule column is missing from appointmentstbl.');
    }

    if (isset($appointmentColumns['confirmed_schedule_timestamp'])) {
        $insertColumns[] = 'confirmed_schedule_timestamp';
        $insertValues[] = $confirmedScheduleTimestamp;
        $bindTypes .= 's';
    }

    if (isset($appointmentColumns['appointment_status_id'])) {
        $insertColumns[] = 'appointment_status_id';
        $insertValues[] = $statusId;
        $bindTypes .= 'i';
    }

    if (isset($appointmentColumns['review_timestamp'])) {
        $insertColumns[] = 'review_timestamp';
        $insertValues[] = $now->format('Y-m-d H:i:s');
        $bindTypes .= 's';
    }

    if (isset($appointmentColumns['resident_notes'])) {
        $insertColumns[] = 'resident_notes';
        $insertValues[] = $residentNotes !== '' ? $residentNotes : null;
        $bindTypes .= 's';
    }

    if (isset($appointmentColumns['meeting_location'])) {
        $insertColumns[] = 'meeting_location';
        $insertValues[] = $meetingLocation !== '' ? $meetingLocation : null;
        $bindTypes .= 's';
    }

    $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
    $columnSql = implode(",\n                ", $insertColumns);
    $stmt = $conn->prepare("
        INSERT INTO appointmentstbl
            (
                {$columnSql}
            )
        VALUES
            ({$placeholders})
    ");
    if (!$stmt) {
        throw new RuntimeException('Prepare failed (appointment insert): ' . $conn->error);
    }

    $stmt->bind_param($bindTypes, ...$insertValues);
    if (!$stmt->execute()) {
        $error = $stmt->error ?: $conn->error;
        $stmt->close();
        throw new RuntimeException('Failed to insert appointment: ' . $error);
    }
    $stmt->close();

    $conn->commit();
    unset($_SESSION['guest_appointment_otp_verified']);

    $notificationErrors = apsh_send_notifications([
        'full_name' => $fullName,
        'phone_number' => $contactNumber,
    ], $officialContact, [
        'appointment_id' => $appointmentId,
        'subject' => $subjectLabel,
        'purpose' => $purpose,
        'meeting_location' => $meetingLocation,
        'confirmed_schedule_timestamp' => $confirmedScheduleTimestamp,
    ]);
    if ($notificationErrors !== []) {
        error_log('submitGuestAppointment notification warnings: ' . implode(' | ', $notificationErrors));
    }

    apsh_redirect_with_message(
        '/Guest-End/appointments.php',
        'success',
        'Appointment confirmed!',
        [
            'appointment_id' => $appointmentId,
            'official_name' => $officialDisplayName,
            'meeting_location' => $meetingLocation,
            'schedule_label' => $scheduleLabel,
        ]
    );
} catch (Throwable $e) {
    $conn->rollback();
    error_log('submitGuestAppointment failed: ' . $e->getMessage());
    apsh_redirect_with_message('/Guest-End/appointments.php', 'error', $e->getMessage() !== '' ? $e->getMessage() : 'Unable to submit your appointment right now.');
}
