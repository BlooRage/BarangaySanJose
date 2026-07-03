<?php

require_once __DIR__ . '/appointmentCouncilMembers.php';
require_once __DIR__ . '/appointmentSettings.php';
require_once __DIR__ . '/appointmentOfficialSchedules.php';
require_once __DIR__ . '/appointmentTimeSlots.php';
require_once __DIR__ . '/uniqueIDGenerate.php';
require_once __DIR__ . '/sendSMS.php';
require_once __DIR__ . '/mailConfigurations.php';
require_once __DIR__ . '/piiCrypto.php';
require_once __DIR__ . '/../EmailHandlers/emailSender.php';

if (!function_exists('apsh_redirect_with_message')) {
    function apsh_redirect_with_message(string $path, string $type, string $message, array $extra = []): void
    {
        $query = array_merge([$type => $message], $extra);
        header('Location: ' . appUrl($path) . '?' . http_build_query($query));
        exit;
    }
}

if (!function_exists('apsh_table_exists')) {
    function apsh_table_exists(mysqli $conn, string $tableName): bool
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

        $stmt->bind_param('s', $tableName);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        $stmt->close();

        return !empty($row);
    }
}

if (!function_exists('apsh_get_table_columns')) {
    function apsh_get_table_columns(mysqli $conn, string $tableName): array
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

        $stmt->bind_param('s', $tableName);
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
}

if (!function_exists('apsh_get_status_id')) {
    function apsh_get_status_id(mysqli $conn, string $name, string $type): ?int
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

        $stmt->bind_param('ss', $name, $type);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return isset($row['status_id']) ? (int)$row['status_id'] : null;
    }
}

if (!function_exists('apsh_ensure_status_id')) {
    function apsh_ensure_status_id(mysqli $conn, string $name, string $type): int
    {
        $existing = apsh_get_status_id($conn, $name, $type);
        if ($existing !== null) {
            return $existing;
        }

        $stmt = $conn->prepare("INSERT INTO statuslookuptbl (status_name, status_type) VALUES (?, ?)");
        if (!$stmt) {
            throw new RuntimeException('Failed to create appointment status lookup.');
        }

        $stmt->bind_param('ss', $name, $type);
        $stmt->execute();
        $statusId = (int)$conn->insert_id;
        $stmt->close();

        return $statusId;
    }
}

if (!function_exists('apsh_normalize_phone')) {
    function apsh_normalize_phone(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', trim($value));
        if ($digits === '') {
            return null;
        }
        if (preg_match('/^9\d{9}$/', $digits)) {
            return '0' . $digits;
        }
        if (preg_match('/^0\d{10}$/', $digits)) {
            return $digits;
        }
        if (preg_match('/^63\d{10}$/', $digits)) {
            return '0' . substr($digits, 2);
        }

        return null;
    }
}

if (!function_exists('apsh_phone_otp_key')) {
    function apsh_phone_otp_key(string $value): ?string
    {
        $normalized = apsh_normalize_phone($value);
        if ($normalized === null) {
            return null;
        }

        return substr($normalized, 1);
    }
}

if (!function_exists('apsh_guest_appointment_captcha_issue')) {
    function apsh_guest_appointment_captcha_issue(bool $forceNew = false): array
    {
        $existing = $_SESSION['guest_appointment_captcha'] ?? null;
        if (
            !$forceNew
            && is_array($existing)
            && isset($existing['left'], $existing['right'], $existing['answer'], $existing['generated_at'])
            && (time() - (int)$existing['generated_at']) <= 1800
        ) {
            return [
                'left' => (int)$existing['left'],
                'right' => (int)$existing['right'],
            ];
        }

        $left = random_int(2, 9);
        $right = random_int(1, 9);
        $_SESSION['guest_appointment_captcha'] = [
            'left' => $left,
            'right' => $right,
            'answer' => $left + $right,
            'generated_at' => time(),
        ];

        return [
            'left' => $left,
            'right' => $right,
        ];
    }
}

if (!function_exists('apsh_guest_appointment_captcha_is_valid')) {
    function apsh_guest_appointment_captcha_is_valid(string $answer): bool
    {
        $challenge = $_SESSION['guest_appointment_captcha'] ?? null;
        if (
            !is_array($challenge)
            || !isset($challenge['answer'], $challenge['generated_at'])
            || (time() - (int)$challenge['generated_at']) > 1800
        ) {
            return false;
        }

        $normalized = trim($answer);
        if ($normalized === '' || preg_match('/^-?\d+$/', $normalized) !== 1) {
            return false;
        }

        return hash_equals((string)((int)$challenge['answer']), (string)((int)$normalized));
    }
}

if (!function_exists('apsh_normalize_email')) {
    function apsh_normalize_email(string $value): string
    {
        $email = strtolower(trim($value));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '';
        }

        return $email;
    }
}

if (!function_exists('apsh_appointment_status_key')) {
    function apsh_appointment_status_key(string $statusName): string
    {
        return preg_replace('/[\s_-]+/', '', strtolower(trim($statusName)));
    }
}

if (!function_exists('apsh_appointment_status_is_inactive')) {
    function apsh_appointment_status_is_inactive(string $statusName): bool
    {
        $key = apsh_appointment_status_key($statusName);
        return in_array($key, [
            'completed',
            'denied',
            'cancelled',
            'cancelledbyresident',
            'cancelledbyadmin',
            'noshow',
            'closed',
        ], true);
    }
}

if (!function_exists('apsh_find_active_appointment_by_phone')) {
    function apsh_find_active_appointment_by_phone(mysqli $conn, string $contactNumber): ?array
    {
        $normalizedPhone = apsh_normalize_phone($contactNumber);
        if ($normalizedPhone === null) {
            return null;
        }

        $appointmentColumns = apsh_get_table_columns($conn, 'appointmentstbl');
        if (!isset($appointmentColumns['contact_number'])) {
            return null;
        }

        $statusJoin = '';
        $statusSelect = "''";
        if (apsh_table_exists($conn, 'statuslookuptbl') && isset($appointmentColumns['appointment_status_id'])) {
            $statusJoin = "
                LEFT JOIN statuslookuptbl s
                    ON s.status_id = a.appointment_status_id
            ";
            $statusSelect = "COALESCE(s.status_name, '')";
        }

        $scheduleSelect = [];
        foreach (['confirmed_schedule_timestamp', 'preferred_schedule_timestamp', 'schedule_timestamp'] as $column) {
            if (isset($appointmentColumns[$column])) {
                $scheduleSelect[] = "a.{$column}";
            }
        }
        $scheduleSql = $scheduleSelect !== []
            ? 'COALESCE(' . implode(', ', $scheduleSelect) . ')'
            : "''";

        $stmt = $conn->prepare("
            SELECT a.appointment_id,
                   {$statusSelect} AS status_name,
                   {$scheduleSql} AS schedule_timestamp
            FROM appointmentstbl a
            {$statusJoin}
            WHERE a.contact_number = ?
            ORDER BY {$scheduleSql} DESC, a.appointment_id DESC
            LIMIT 25
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to validate existing appointments for this mobile number.');
        }

        $stmt->bind_param('s', $normalizedPhone);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $statusName = trim((string)($row['status_name'] ?? ''));
            if (!apsh_appointment_status_is_inactive($statusName)) {
                $stmt->close();
                return [
                    'appointment_id' => trim((string)($row['appointment_id'] ?? '')),
                    'status_name' => $statusName !== '' ? $statusName : 'Approved',
                    'schedule_timestamp' => trim((string)($row['schedule_timestamp'] ?? '')),
                ];
            }
        }
        $stmt->close();

        return null;
    }
}

if (!function_exists('apsh_active_appointment_phone_message')) {
    function apsh_active_appointment_phone_message(array $appointment): string
    {
        $appointmentId = trim((string)($appointment['appointment_id'] ?? ''));
        $statusName = trim((string)($appointment['status_name'] ?? 'Active'));
        $message = 'This mobile number already has an active appointment';
        if ($appointmentId !== '') {
            $message .= ' (' . $appointmentId . ')';
        }
        $message .= ' with status ' . $statusName . '. Please wait until it is completed, cancelled, or denied before booking another.';

        return $message;
    }
}

if (!function_exists('apsh_guest_appointment_recent_otp_request')) {
    function apsh_guest_appointment_recent_otp_request(mysqli $conn, string $recipientPhoneKey, int $cooldownSeconds = 60): ?array
    {
        $recipientPhoneKey = trim($recipientPhoneKey);
        if ($recipientPhoneKey === '' || !apsh_table_exists($conn, 'otprequesttbl')) {
            return null;
        }

        $stmt = $conn->prepare("
            SELECT request_timestamp
            FROM otprequesttbl
            WHERE recipient = ?
              AND purpose = 'guest_appointment'
            ORDER BY request_timestamp DESC
            LIMIT 1
        ");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('s', $recipientPhoneKey);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $requestTimestamp = trim((string)($row['request_timestamp'] ?? ''));
        $lastRequestAt = $requestTimestamp !== '' ? strtotime($requestTimestamp) : false;
        if ($lastRequestAt === false) {
            return null;
        }

        $remaining = $cooldownSeconds - max(0, time() - $lastRequestAt);
        if ($remaining <= 0) {
            return null;
        }

        return [
            'remaining_seconds' => $remaining,
            'request_timestamp' => $requestTimestamp,
        ];
    }
}

if (!function_exists('apsh_normalize_address_mode')) {
    function apsh_normalize_address_mode(string $value): string
    {
        $mode = strtolower(trim($value));
        if (in_array($mode, ['house', 'street'], true)) {
            return 'house';
        }
        if (in_array($mode, ['lot_block', 'block_lot'], true)) {
            return 'lot_block';
        }

        return '';
    }
}

if (!function_exists('apsh_normalize_address_short_part')) {
    function apsh_normalize_address_short_part(string $value, int $maxLength = 50): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($value));
        if (!is_string($normalized) || $normalized === '') {
            return '';
        }
        if (mb_strlen($normalized) > $maxLength) {
            return '';
        }
        if (!preg_match('/^[A-Za-z0-9#\/-][A-Za-z0-9#\/\- ]*$/', $normalized)) {
            return '';
        }

        return $normalized;
    }
}

if (!function_exists('apsh_normalize_address_text_part')) {
    function apsh_normalize_address_text_part(string $value, int $maxLength = 150): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($value));
        if (!is_string($normalized) || $normalized === '') {
            return '';
        }
        if (mb_strlen($normalized) > $maxLength) {
            return '';
        }
        if (!preg_match("/^[A-Za-z0-9#.,'\/()-][A-Za-z0-9#.,'\/()\- ]*$/", $normalized)) {
            return '';
        }

        return $normalized;
    }
}

if (!function_exists('apsh_compose_structured_guest_address')) {
    function apsh_compose_structured_guest_address(array $address): string
    {
        $mode = apsh_normalize_address_mode((string)($address['address_system'] ?? ''));
        $subdivision = trim((string)($address['subdivision'] ?? ''));
        $area = trim((string)($address['area_number'] ?? ''));
        $barangay = trim((string)($address['barangay'] ?? 'Barangay San Jose'));
        $municipalityCity = trim((string)($address['municipality_city'] ?? 'Rodriguez'));
        $province = trim((string)($address['province'] ?? 'Rizal'));

        $parts = [];
        if ($mode === 'house') {
            $lineParts = array_values(array_filter([
                trim((string)($address['unit_number'] ?? '')),
                trim(implode(' ', array_filter([
                    trim((string)($address['house_number'] ?? '')),
                    trim((string)($address['street_name'] ?? '')),
                ], static fn(string $part): bool => $part !== ''))),
            ], static fn(string $part): bool => $part !== ''));
            if ($lineParts !== []) {
                $parts[] = implode(', ', $lineParts);
            }
        } elseif ($mode === 'lot_block') {
            $lotBlockLine = trim(implode(' ', array_filter([
                trim((string)($address['lot_number'] ?? '')) !== '' ? 'Lot ' . trim((string)($address['lot_number'] ?? '')) : '',
                trim((string)($address['block_number'] ?? '')) !== '' ? 'Block ' . trim((string)($address['block_number'] ?? '')) : '',
                trim((string)($address['phase_number'] ?? '')) !== '' ? 'Phase ' . trim((string)($address['phase_number'] ?? '')) : '',
            ], static fn(string $part): bool => $part !== '')));
            if ($lotBlockLine !== '') {
                $parts[] = $lotBlockLine;
            }
        }

        foreach ([$subdivision, $area, $barangay, $municipalityCity, $province] as $part) {
            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return implode(', ', $parts);
    }
}

if (!function_exists('apsh_normalize_subject_label')) {
    function apsh_normalize_subject_label(string $value): string
    {
        $normalized = strtolower(trim($value));
        $map = [
            'follow_up' => 'Follow-up Concern',
            'consultation' => 'Consultation',
            'event_coordination' => 'Event Coordination',
            'other' => 'Other',
        ];

        return $map[$normalized] ?? trim($value);
    }
}

if (!function_exists('apsh_display_name')) {
    function apsh_display_name(array $row, string $fallback = ''): string
    {
        $parts = array_filter([
            trim((string)($row['firstname'] ?? '')),
            trim((string)($row['middlename'] ?? '')),
            trim((string)($row['lastname'] ?? '')),
            trim((string)($row['suffix'] ?? '')),
        ], static fn($value) => $value !== '');

        $fullName = preg_replace('/\s+/', ' ', trim(implode(' ', $parts)));
        if ($fullName !== '') {
            return $fullName;
        }

        return trim($fallback);
    }
}

if (!function_exists('apsh_official_title_from_position')) {
    function apsh_official_title_from_position(string $positionAccess, string $seatName = ''): string
    {
        $label = strtolower(trim($positionAccess . ' ' . $seatName));
        if ($label === '') {
            return '';
        }
        if (strpos($label, 'punong barangay') !== false || strpos($label, 'barangay captain') !== false || strpos($label, 'kapitan') !== false) {
            return 'Kapitan';
        }
        if (strpos($label, 'kagawad') !== false || strpos($label, 'councilor') !== false) {
            return 'Kagawad';
        }
        if (strpos($label, 'barangay secretary') !== false || preg_match('/\bsecretary\b/', $label)) {
            return 'Barangay Secretary';
        }
        if (strpos($label, 'barangay treasurer') !== false || preg_match('/\btreasurer\b/', $label)) {
            return 'Barangay Treasurer';
        }

        return '';
    }
}

if (!function_exists('apsh_official_message_label')) {
    function apsh_official_message_label(array $official): string
    {
        $fullName = trim((string)($official['full_name'] ?? ''));
        $positionAccess = trim((string)($official['position_access'] ?? ''));
        $seatName = trim((string)($official['seat_name'] ?? ''));
        $title = apsh_official_title_from_position($positionAccess, $seatName);
        if ($title !== '' && $fullName !== '') {
            return $title . ' ' . $fullName;
        }
        if ($fullName !== '') {
            return $fullName;
        }

        return 'the assigned barangay official';
    }
}

if (!function_exists('apsh_format_timestamp_label')) {
    function apsh_format_timestamp_label(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'the selected schedule';
        }

        try {
            $timestamp = new DateTimeImmutable($value);
            return $timestamp->format('F j, Y g:i A');
        } catch (Throwable $e) {
            return $value;
        }
    }
}

if (!function_exists('apsh_format_sms_timestamp_label')) {
    function apsh_format_sms_timestamp_label(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'the selected schedule';
        }

        try {
            $timestamp = new DateTimeImmutable($value);
            return $timestamp->format('M j, Y g:i A');
        } catch (Throwable $e) {
            return $value;
        }
    }
}

if (!function_exists('apsh_sms_clip')) {
    function apsh_sms_clip(string $value, int $maxLength): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value));
        if ($value === '') {
            return '';
        }
        if ($maxLength < 4 || strlen($value) <= $maxLength) {
            return $value;
        }

        return rtrim(substr($value, 0, $maxLength - 3)) . '...';
    }
}

if (!function_exists('apsh_load_official_delivery_contact')) {
    function apsh_load_official_delivery_contact(mysqli $conn, string $officialUserId, array $fallback = []): array
    {
        $officialUserId = trim($officialUserId);
        if ($officialUserId === '') {
            return [
                'user_id' => '',
                'full_name' => trim((string)($fallback['full_name'] ?? $fallback['option_label'] ?? 'Barangay Official')),
                'phone_number' => '',
                'email' => '',
                'position_access' => trim((string)($fallback['position_access'] ?? '')),
                'seat_name' => trim((string)($fallback['seat_name'] ?? '')),
            ];
        }

        $stmt = $conn->prepare("
            SELECT ua.user_id,
                   ua.email,
                   ua.phone_number,
                   oi.firstname,
                   oi.middlename,
                   oi.lastname,
                   oi.suffix,
                   oi.contact_number AS official_contact_number,
                   oi.email AS official_email
            FROM useraccountstbl ua
            LEFT JOIN officialinformationtbl oi
                ON oi.user_id COLLATE utf8mb4_general_ci = ua.user_id COLLATE utf8mb4_general_ci
            WHERE ua.user_id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to load the assigned official contact details.');
        }

        $stmt->bind_param('s', $officialUserId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            throw new RuntimeException('Assigned official account could not be found.');
        }

        $row = pii_decrypt_official_row($row) ?? $row;
        $row = pii_decrypt_useraccount_row($row) ?? $row;

        $fullName = apsh_display_name($row, (string)($fallback['full_name'] ?? $fallback['option_label'] ?? 'Barangay Official'));
        $officialEmail = pii_decrypt_string((string)($row['official_email'] ?? ''));
        $officialContactNumber = pii_decrypt_string((string)($row['official_contact_number'] ?? ''));
        $email = apsh_normalize_email($officialEmail);
        if ($email === '') {
            $email = apsh_normalize_email((string)($row['email'] ?? ''));
        }

        $phoneNumber = apsh_normalize_phone($officialContactNumber);
        if ($phoneNumber === null) {
            $phoneNumber = apsh_normalize_phone((string)($row['phone_number'] ?? ''));
        }
        $positionAccess = trim((string)apcm_current_official_position($conn, $officialUserId));
        $seatName = trim((string)($fallback['seat_name'] ?? ''));

        return [
            'user_id' => $officialUserId,
            'full_name' => $fullName !== '' ? $fullName : 'Barangay Official',
            'phone_number' => $phoneNumber ?? '',
            'email' => $email,
            'position_access' => $positionAccess,
            'seat_name' => $seatName,
        ];
    }
}

if (!function_exists('apsh_validate_schedule_selection')) {
    function apsh_validate_schedule_selection(
        mysqli $conn,
        array $appointmentSettings,
        string $officialUserId,
        string $appointmentDate,
        string $appointmentTime,
        ?DateTimeImmutable $now = null
    ): array {
        $timezone = new DateTimeZone(date_default_timezone_get() ?: 'Asia/Manila');
        $now = $now ?? new DateTimeImmutable('now', $timezone);
        $bookingLimits = aps_booking_date_limits($appointmentSettings, $now);
        $minAppointmentDate = (string)($bookingLimits['min_date'] ?? '');
        $maxAppointmentDate = (string)($bookingLimits['max_date'] ?? '');

        if (empty($bookingLimits['has_window']) || aps_first_available_booking_date($appointmentSettings, $now) === null) {
            throw new RuntimeException('No appointment dates are currently available based on the saved appointment settings.');
        }

        $schedule = DateTimeImmutable::createFromFormat('Y-m-d H:i', $appointmentDate . ' ' . $appointmentTime, $timezone);
        if (!$schedule || $schedule->format('Y-m-d') !== $appointmentDate || $schedule->format('H:i') !== $appointmentTime) {
            throw new RuntimeException('Appointment date or time is invalid.');
        }

        if ($schedule <= $now) {
            throw new RuntimeException('Please select a remaining appointment time later than the current time.');
        }

        if ($appointmentDate < $minAppointmentDate || $appointmentDate > $maxAppointmentDate) {
            throw new RuntimeException('Date of appointment is outside the current booking window.');
        }

        if (!aps_is_date_available($appointmentSettings, $appointmentDate)) {
            throw new RuntimeException('The selected appointment date is unavailable for official appointments.');
        }

        $officialAvailability = apos_effective_schedule_for_user_date($conn, $officialUserId, $appointmentDate, $appointmentSettings);
        if ($officialAvailability === null) {
            throw new RuntimeException('The selected council member is not available on that appointment date.');
        }

        if (!array_key_exists($appointmentTime, (array)($officialAvailability['slots'] ?? []))) {
            throw new RuntimeException('Please select one of the allotted appointment times for that council member.');
        }

        return [
            'preferred_schedule_timestamp' => $schedule->format('Y-m-d H:i:s'),
            'confirmed_schedule_timestamp' => $schedule->format('Y-m-d H:i:s'),
            'meeting_location' => apos_normalize_location($officialAvailability['meeting_location'] ?? ''),
        ];
    }
}

if (!function_exists('apsh_slot_is_already_booked')) {
    function apsh_slot_is_already_booked(mysqli $conn, array $appointmentColumns, string $officialUserId, string $scheduleTimestamp): bool
    {
        $officialUserId = trim($officialUserId);
        $scheduleTimestamp = trim($scheduleTimestamp);
        if (
            $officialUserId === ''
            || $scheduleTimestamp === ''
            || !isset($appointmentColumns['user_id_official_assigned'])
        ) {
            return false;
        }

        $timestampColumns = [];
        foreach (['confirmed_schedule_timestamp', 'preferred_schedule_timestamp', 'schedule_timestamp'] as $column) {
            if (isset($appointmentColumns[$column])) {
                $timestampColumns[] = $column;
            }
        }

        if ($timestampColumns === []) {
            return false;
        }

        $timeClauses = [];
        $bindTypes = 's';
        $bindValues = [$officialUserId];
        foreach ($timestampColumns as $column) {
            $timeClauses[] = "a.{$column} = ?";
            $bindTypes .= 's';
            $bindValues[] = $scheduleTimestamp;
        }

        $statusJoin = '';
        $statusFilter = '';
        if (apsh_table_exists($conn, 'statuslookuptbl') && isset($appointmentColumns['appointment_status_id'])) {
            $statusJoin = "
                LEFT JOIN statuslookuptbl s
                    ON s.status_id = a.appointment_status_id
            ";
            $statusFilter = "AND LOWER(TRIM(COALESCE(s.status_name, ''))) <> 'denied'";
        }

        $sql = "
            SELECT a.appointment_id
            FROM appointmentstbl a
            {$statusJoin}
            WHERE a.user_id_official_assigned = ?
              AND (" . implode(' OR ', $timeClauses) . ")
              {$statusFilter}
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Unable to validate the selected appointment slot.');
        }

        $stmt->bind_param($bindTypes, ...$bindValues);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (bool)$row;
    }
}

if (!function_exists('apsh_send_notifications')) {
    function apsh_send_notifications(array $applicant, array $official, array $appointment): array
    {
        $errors = [];
        $appointmentId = trim((string)($appointment['appointment_id'] ?? ''));
        $subject = trim((string)($appointment['subject'] ?? ''));
        $purpose = trim((string)($appointment['purpose'] ?? ''));
        $location = trim((string)($appointment['meeting_location'] ?? ''));
        $scheduleLabel = apsh_format_timestamp_label((string)($appointment['confirmed_schedule_timestamp'] ?? ''));
        $smsScheduleLabel = apsh_format_sms_timestamp_label((string)($appointment['confirmed_schedule_timestamp'] ?? ''));
        $applicantName = trim((string)($applicant['full_name'] ?? 'Applicant'));
        $applicantPhone = trim((string)($applicant['phone_number'] ?? ''));
        $locationLabel = $location !== '' ? $location : 'the assigned meeting location';
        $officialName = trim((string)($official['full_name'] ?? 'Barangay Official'));
        $officialLabel = apsh_official_message_label($official);
        $officialPhone = trim((string)($official['phone_number'] ?? ''));
        $officialEmail = trim((string)($official['email'] ?? ''));
        $smsLocationLabel = apsh_sms_clip($locationLabel, 30);
        $smsOfficialLabel = apsh_sms_clip($officialLabel, 36);
        $smsApplicantName = apsh_sms_clip($applicantName, 28);
        $applicantSms = "Your appointment with {$smsOfficialLabel} is confirmed for {$smsScheduleLabel} at {$smsLocationLabel}.";
        $officialSms = "{$smsApplicantName} booked an appointment with you for {$smsScheduleLabel} at {$smsLocationLabel}.";

        if ($applicantPhone !== '') {
            if (!sendSMS($applicantPhone, $applicantSms)) {
                $errors[] = 'Applicant SMS failed' . (getLastSmsError() !== '' ? ': ' . getLastSmsError() : '.');
            }
        } else {
            $errors[] = 'Applicant SMS skipped because no mobile number is on file.';
        }

        if ($officialPhone !== '') {
            if (!sendSMS($officialPhone, $officialSms)) {
                $errors[] = 'Official SMS failed' . (getLastSmsError() !== '' ? ': ' . getLastSmsError() : '.');
            }
        } else {
            $errors[] = 'Official SMS skipped because no mobile number is on file.';
        }

        if ($officialEmail !== '') {
            $smtpConfig = require __DIR__ . '/mailConfigurations.php';
            $emailSender = new EmailSender($smtpConfig);
            $bodyHtml = '
                <p>Hello ' . htmlspecialchars($officialName, ENT_QUOTES, 'UTF-8') . ',</p>
                <p>A barangay appointment has been confirmed and added to your schedule.</p>
                <ul>
                    <li><strong>Appointment ID:</strong> ' . htmlspecialchars($appointmentId, ENT_QUOTES, 'UTF-8') . '</li>
                    <li><strong>Applicant:</strong> ' . htmlspecialchars($applicantName, ENT_QUOTES, 'UTF-8') . '</li>
                    <li><strong>Schedule:</strong> ' . htmlspecialchars($scheduleLabel, ENT_QUOTES, 'UTF-8') . '</li>
                    <li><strong>Meeting location:</strong> ' . htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8') . '</li>
                    <li><strong>Subject:</strong> ' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</li>
                    <li><strong>Purpose:</strong> ' . htmlspecialchars($purpose, ENT_QUOTES, 'UTF-8') . '</li>
                    <li><strong>Applicant contact:</strong> ' . htmlspecialchars($applicantPhone !== '' ? $applicantPhone : 'Not available', ENT_QUOTES, 'UTF-8') . '</li>
                </ul>
                <p>Please open the appointment tracker if any follow-up or rescheduling is needed.</p>
            ';
            $bodyText = implode("\n", [
                "Hello {$officialName},",
                '',
                'A barangay appointment has been confirmed and added to your schedule.',
                "Appointment ID: {$appointmentId}",
                "Applicant: {$applicantName}",
                "Schedule: {$scheduleLabel}",
                "Meeting location: {$locationLabel}",
                "Subject: {$subject}",
                "Purpose: {$purpose}",
                'Applicant contact: ' . ($applicantPhone !== '' ? $applicantPhone : 'Not available'),
            ]);

            if (!$emailSender->send([
                'type' => 'transaction',
                'to' => $officialEmail,
                'subject' => 'New Appointment Confirmed: ' . ($appointmentId !== '' ? $appointmentId : 'Barangay San Jose'),
                'bodyHtml' => $bodyHtml,
                'bodyText' => $bodyText,
            ])) {
                $errors[] = 'Official email failed' . ($emailSender->getLastError() !== '' ? ': ' . $emailSender->getLastError() : '.');
            }
        } else {
            $errors[] = 'Official email skipped because no email address is on file.';
        }

        return $errors;
    }
}
