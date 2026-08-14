<?php
require_once __DIR__ . '/../General/security.php';
require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/adminModulePermissions.php';
require_once __DIR__ . '/../General/appointmentSubmissionShared.php';

requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin'], false);
verifyCsrfToken(false);
if (!amp_current_user_has_module_permission($conn, 'appointments')) {
    header('Location: ' . appUrl('/Admin-End/Appointments/WalkInAppointmentForm.php?error=' . rawurlencode('You do not have permission to create appointments.')));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

apos_schedule_ensure_storage($conn);

if (!apsh_table_exists($conn, 'appointmentstbl')) {
    http_response_code(500);
    exit('Appointment table is not available. Run the appointment migration first.');
}

$action = trim((string)($_POST['action'] ?? 'create_walkin_appointment'));
if ($action !== 'create_walkin_appointment') {
    http_response_code(400);
    exit('Unknown appointment action.');
}

$currentUserId = trim((string)($_SESSION['user_id'] ?? ''));
$currentRole = trim((string)($_SESSION['role'] ?? ''));
$appointmentAccess = apcm_get_appointment_admin_scope($conn, $currentUserId, $currentRole);
if (empty($appointmentAccess['can_access_tracker'])) {
    header('Location: ' . appUrl('/Admin-End/Appointments/AppointmentTracker.php?tool=tracker&error=' . rawurlencode('Appointment tracker access is not available for your account.')));
    exit;
}

$firstName = trim((string)($_POST['first_name'] ?? ''));
$middleName = trim((string)($_POST['middle_name'] ?? ''));
$lastName = trim((string)($_POST['last_name'] ?? ''));
$suffix = trim((string)($_POST['suffix_name'] ?? ''));
$contactNumberRaw = trim((string)($_POST['contact_number'] ?? ''));
$contactNumber = apsh_normalize_phone($contactNumberRaw);
$emailAddress = apsh_normalize_email((string)($_POST['email_address'] ?? ''));
$address = preg_replace('/\s+/', ' ', trim((string)($_POST['current_address'] ?? '')));
$deskNote = preg_replace('/\s+/', ' ', trim((string)($_POST['desk_note'] ?? '')));
$officialUserId = trim((string)($_POST['official_user_id'] ?? ''));
$subject = strtolower(trim((string)($_POST['subject'] ?? '')));
$subjectOther = preg_replace('/\s+/', ' ', trim((string)($_POST['subject_other'] ?? '')));
$appointmentDate = trim((string)($_POST['appointment_date'] ?? ''));
$appointmentTime = trim((string)($_POST['appointment_time'] ?? ''));
$purpose = preg_replace('/\s+/', ' ', trim((string)($_POST['purpose'] ?? '')));

if (empty($appointmentAccess['can_manage_all_tracker'])) {
    $officialUserId = trim((string)($appointmentAccess['scoped_official_user_id'] ?? $currentUserId));
}

if ($firstName === '' || $lastName === '' || $officialUserId === '' || $subject === '' || $appointmentDate === '' || $appointmentTime === '') {
    header('Location: ' . appUrl('/Admin-End/Appointments/WalkInAppointmentForm.php?error=' . rawurlencode('Please complete all required walk-in appointment fields.')));
    exit;
}

if ($contactNumberRaw !== '' && $contactNumber === null) {
    header('Location: ' . appUrl('/Admin-End/Appointments/WalkInAppointmentForm.php?error=' . rawurlencode('Please enter a valid mobile number in the format 09XXXXXXXXX.')));
    exit;
}

if (!in_array($subject, ['follow_up', 'consultation', 'event_coordination', 'other'], true)) {
    header('Location: ' . appUrl('/Admin-End/Appointments/WalkInAppointmentForm.php?error=' . rawurlencode('Please select a valid subject of appointment.')));
    exit;
}

if ($subject === 'other' && $subjectOther === '') {
    header('Location: ' . appUrl('/Admin-End/Appointments/WalkInAppointmentForm.php?error=' . rawurlencode('Please specify the subject when Other is selected.')));
    exit;
}

$appointmentSettings = aps_settings_load($conn);
$councilMembersByUserId = apcm_fetch_council_members_by_user_id($conn);
if ($councilMembersByUserId === []) {
    header('Location: ' . appUrl('/Admin-End/Appointments/WalkInAppointmentForm.php?error=' . rawurlencode('No active barangay council members are currently available for appointments.')));
    exit;
}

if (!isset($councilMembersByUserId[$officialUserId])) {
    header('Location: ' . appUrl('/Admin-End/Appointments/WalkInAppointmentForm.php?error=' . rawurlencode('Please select a valid barangay council member for the walk-in appointment.')));
    exit;
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
    header('Location: ' . appUrl('/Admin-End/Appointments/WalkInAppointmentForm.php?error=' . rawurlencode($e->getMessage() !== '' ? $e->getMessage() : 'Unable to validate the appointment schedule.')));
    exit;
}

$fullName = trim(implode(' ', array_values(array_filter([
    $firstName,
    $middleName,
    $lastName,
    $suffix,
], static fn(string $part): bool => $part !== ''))));
if ($fullName === '') {
    header('Location: ' . appUrl('/Admin-End/Appointments/WalkInAppointmentForm.php?error=' . rawurlencode('A valid applicant name is required.')));
    exit;
}

$appointmentId = GenerateAppointmentID($conn);
if (!$appointmentId) {
    header('Location: ' . appUrl('/Admin-End/Appointments/WalkInAppointmentForm.php?error=' . rawurlencode('Unable to generate an appointment ID right now.')));
    exit;
}

try {
    $statusId = apsh_ensure_status_id($conn, 'Approved', 'Appointment');
    $officialContact = apsh_load_official_delivery_contact($conn, $officialUserId, $councilMembersByUserId[$officialUserId] ?? []);
} catch (Throwable $e) {
    header('Location: ' . appUrl('/Admin-End/Appointments/WalkInAppointmentForm.php?error=' . rawurlencode('Unable to prepare the assigned official details for this appointment.')));
    exit;
}

$appointmentColumns = apsh_get_table_columns($conn, 'appointmentstbl');
if (!isset($appointmentColumns['user_id_official_assigned'])) {
    header('Location: ' . appUrl('/Admin-End/Appointments/WalkInAppointmentForm.php?error=' . rawurlencode('The appointment module is missing the council member assignment field. Please run the latest appointment migration first.')));
    exit;
}

$subjectLabel = apsh_normalize_subject_label($subject);
$normalizedSubjectOther = $subject === 'other' ? $subjectOther : null;
$notes = [];
if ($address !== '') {
    $notes[] = 'Walk-in address: ' . $address;
}
if ($subject === 'other' && $subjectOther !== '') {
    $notes[] = 'Other subject detail: ' . $subjectOther;
}
if ($deskNote !== '') {
    $notes[] = 'Desk note: ' . $deskNote;
}
$notes[] = 'Booking source: Walk-in Desk';
$residentNotes = implode("\n", $notes);

$preferredScheduleTimestamp = (string)($validatedSchedule['preferred_schedule_timestamp'] ?? '');
$confirmedScheduleTimestamp = (string)($validatedSchedule['confirmed_schedule_timestamp'] ?? '');
$meetingLocation = (string)($validatedSchedule['meeting_location'] ?? '');
$now = new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get() ?: 'Asia/Manila'));

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
        $insertValues[] = 'walkin_desk';
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

    if (isset($appointmentColumns['user_id_employee_staff'])) {
        $insertColumns[] = 'user_id_employee_staff';
        $insertValues[] = $currentUserId !== '' ? $currentUserId : null;
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
        error_log('createWalkInAppointment notification warnings: ' . implode(' | ', $notificationErrors));
    }

    header('Location: ' . appUrl('/Admin-End/Appointments/AppointmentTracker.php?tool=tracker&success=' . rawurlencode('Walk-in appointment encoded successfully.') . '&appointment_id=' . rawurlencode($appointmentId)));
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    error_log('createWalkInAppointment failed: ' . $e->getMessage());
    header('Location: ' . appUrl('/Admin-End/Appointments/WalkInAppointmentForm.php?error=' . rawurlencode($e->getMessage() !== '' ? $e->getMessage() : 'Unable to save the walk-in appointment right now.')));
    exit;
}
