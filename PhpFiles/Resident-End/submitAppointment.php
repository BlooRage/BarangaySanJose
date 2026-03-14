<?php
require_once __DIR__ . "/../General/security.php";
require_once __DIR__ . "/../General/connection.php";
require_once __DIR__ . "/../General/uniqueIDGenerate.php";
require_once __DIR__ . "/../GET/getResidentProfile.php";

function appointmentRedirectWithMessage(string $type, string $message, array $extra = []): void
{
    $query = array_merge([$type => $message], $extra);
    header('Location: ' . appUrl('/Resident-End/Appointments/AppointmentForm.php') . '?' . http_build_query($query));
    exit;
}

function appointmentTableExists(mysqli $conn, string $tableName): bool
{
    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("s", $tableName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();

    return !empty($row);
}

function appointmentGetTableColumns(mysqli $conn, string $tableName): array
{
    $columns = [];
    $stmt = $conn->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    if (!$stmt) {
        return $columns;
    }

    $stmt->bind_param("s", $tableName);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $column = strtolower(trim((string)($row['COLUMN_NAME'] ?? '')));
        if ($column !== '') {
            $columns[$column] = true;
        }
    }
    $stmt->close();

    return $columns;
}

function appointmentGetStatusId(mysqli $conn, string $name, string $type): ?int
{
    $stmt = $conn->prepare("
        SELECT status_id
        FROM statuslookuptbl
        WHERE status_name = ? AND status_type = ?
        ORDER BY status_id ASC
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("ss", $name, $type);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return isset($row['status_id']) ? (int)$row['status_id'] : null;
}

function appointmentEnsureStatusId(mysqli $conn, string $name, string $type): int
{
    $existing = appointmentGetStatusId($conn, $name, $type);
    if ($existing !== null) {
        return $existing;
    }

    $stmt = $conn->prepare("INSERT INTO statuslookuptbl (status_name, status_type) VALUES (?, ?)");
    if (!$stmt) {
        throw new Exception("Failed to create appointment status lookup.");
    }

    $stmt->bind_param("ss", $name, $type);
    $stmt->execute();
    $statusId = (int)$conn->insert_id;
    $stmt->close();

    return $statusId;
}

function appointmentNormalizePhone(string $value): ?string
{
    $digits = preg_replace('/\D+/', '', trim($value));
    if ($digits === '') {
        return null;
    }
    if (preg_match('/^9\d{9}$/', $digits)) {
        $digits = '0' . $digits;
    }
    return $digits;
}

function appointmentNormalizeSubjectLabel(string $value): string
{
    $normalized = strtolower(trim($value));
    $map = [
        'follow_up' => 'Follow-up Concern',
        'consultation' => 'Consultation',
        'event_coordination' => 'Event Coordination',
        'other' => 'Other',
    ];
    return $map[$normalized] ?? $value;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

requireRoleSession(['Resident'], false);
verifyCsrfToken(false);

$action = trim((string)($_POST['action'] ?? 'submit_appointment'));
if ($action !== 'submit_appointment') {
    http_response_code(400);
    exit('Unknown appointment action.');
}

if (!appointmentTableExists($conn, 'appointmentstbl')) {
    http_response_code(500);
    exit('Appointment table is not available. Run the appointment migration first.');
}

$userId = (string)($_SESSION['user_id'] ?? '');
$data = getResidentProfileData($conn, $userId);
$residentinformationtbl = $data['residentinformationtbl'] ?? [];
$useraccountstbl = $data['useraccountstbl'] ?? [];

if ($userId === '' || empty($residentinformationtbl)) {
    appointmentRedirectWithMessage('error', 'Resident profile is required before submitting an appointment.');
}

$subject = strtolower(trim((string)($_POST['subject'] ?? '')));
$subjectOther = trim((string)($_POST['subject_other'] ?? ''));
$appointmentDate = trim((string)($_POST['appointment_date'] ?? ''));
$appointmentTime = trim((string)($_POST['appointment_time'] ?? ''));
$purpose = trim((string)($_POST['purpose'] ?? ''));

$allowedSubjects = ['follow_up', 'consultation', 'event_coordination', 'other'];
if (!in_array($subject, $allowedSubjects, true)) {
    appointmentRedirectWithMessage('error', 'Please select a valid subject of appointment.');
}

if ($subject === 'other' && $subjectOther === '') {
    appointmentRedirectWithMessage('error', 'Please specify the subject when Other is selected.');
}

if ($appointmentDate === '' || $appointmentTime === '' || $purpose === '') {
    appointmentRedirectWithMessage('error', 'Please complete all required appointment fields.');
}

$timezone = new DateTimeZone(date_default_timezone_get() ?: 'Asia/Manila');
$now = new DateTimeImmutable('now', $timezone);
$minAppointmentDate = $now->modify('+1 day')->format('Y-m-d');
$monthStart = $now->format('Y-m-01');
$monthEnd = $now->format('Y-m-t');

$schedule = DateTimeImmutable::createFromFormat('Y-m-d H:i', $appointmentDate . ' ' . $appointmentTime, $timezone);
if (!$schedule || $schedule->format('Y-m-d') !== $appointmentDate || $schedule->format('H:i') !== $appointmentTime) {
    appointmentRedirectWithMessage('error', 'Appointment date or time is invalid.');
}

if ($appointmentDate < $minAppointmentDate || $appointmentDate < $monthStart || $appointmentDate > $monthEnd) {
    appointmentRedirectWithMessage('error', 'Date of appointment must be within the current month and after today.');
}

if ($appointmentTime < '09:01' || $appointmentTime > '16:59') {
    appointmentRedirectWithMessage('error', 'Time of appointment must be between 9:01 AM and 4:59 PM.');
}

$firstName = trim((string)($residentinformationtbl['firstname'] ?? ''));
$middleName = trim((string)($residentinformationtbl['middlename'] ?? ''));
$lastName = trim((string)($residentinformationtbl['lastname'] ?? ''));
$suffix = trim((string)($residentinformationtbl['suffix'] ?? ''));
$contactNumber = appointmentNormalizePhone((string)($useraccountstbl['phone_number'] ?? ''));

if ($firstName === '' || $lastName === '' || $contactNumber === null) {
    appointmentRedirectWithMessage('error', 'Your resident profile is missing required contact details.');
}

$nameParts = array_filter([$firstName, $middleName, $lastName, $suffix], static fn($value) => trim((string)$value) !== '');
$residentName = implode(' ', $nameParts);
$statusId = appointmentEnsureStatusId($conn, 'Pending', 'Appointment');
$appointmentId = GenerateAppointmentID($conn);

if (!$appointmentId) {
    appointmentRedirectWithMessage('error', 'Unable to generate an appointment ID right now.');
}

$subjectLabel = appointmentNormalizeSubjectLabel($subject);
$normalizedSubjectOther = $subject === 'other' ? $subjectOther : null;
$residentNotes = $subject === 'other' ? 'Other subject detail: ' . $subjectOther : null;
$residentUserId = $userId !== '' ? $userId : null;
$preferredScheduleTimestamp = $schedule->format('Y-m-d H:i:s');
$appointmentColumns = appointmentGetTableColumns($conn, 'appointmentstbl');

$conn->begin_transaction();
try {
    $insertColumns = [
        'appointment_id',
        'user_id_resident',
        'name',
        'contact_number',
        'subject',
        'subject_other',
        'purpose',
    ];
    $insertValues = [
        $appointmentId,
        $residentUserId,
        $residentName,
        $contactNumber,
        $subjectLabel,
        $normalizedSubjectOther,
        $purpose,
    ];
    $bindTypes = 'sssssss';

    if (isset($appointmentColumns['preferred_schedule_timestamp'])) {
        $insertColumns[] = 'preferred_schedule_timestamp';
        $insertValues[] = $preferredScheduleTimestamp;
        $bindTypes .= 's';
    } elseif (isset($appointmentColumns['schedule_timestamp'])) {
        $insertColumns[] = 'schedule_timestamp';
        $insertValues[] = $preferredScheduleTimestamp;
        $bindTypes .= 's';
    } else {
        throw new Exception('Appointment schedule column is missing from appointmentstbl.');
    }

    if (isset($appointmentColumns['appointment_status_id'])) {
        $insertColumns[] = 'appointment_status_id';
        $insertValues[] = $statusId;
        $bindTypes .= 'i';
    }

    if (isset($appointmentColumns['resident_notes'])) {
        $insertColumns[] = 'resident_notes';
        $insertValues[] = $residentNotes;
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
        throw new Exception("Prepare failed (appointment insert): " . $conn->error);
    }

    $stmt->bind_param($bindTypes, ...$insertValues);

    if (!$stmt->execute()) {
        $error = $stmt->error ?: $conn->error;
        $stmt->close();
        throw new Exception("Failed to insert appointment: " . $error);
    }
    $stmt->close();

    $conn->commit();
    appointmentRedirectWithMessage('success', 'Appointment request submitted successfully.', [
        'appointment_id' => $appointmentId,
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('submitAppointment failed: ' . $e->getMessage());
    appointmentRedirectWithMessage('error', 'Unable to submit your appointment request right now.');
}
