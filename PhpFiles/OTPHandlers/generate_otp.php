<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

require_once '../General/connection.php';
require_once '../General/sendSMS.php';
require_once '../General/uniqueIDGenerate.php';
require_once '../General/appointmentSubmissionShared.php';
require_once '../General/recaptcha.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function otpHandlerTableExists(mysqli $conn, string $tableName): bool
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

function otpHandlerGuestComplaintFindActiveByPhone(mysqli $conn, string $phone): ?array
{
    if (
        !otpHandlerTableExists($conn, 'casereportstbl') ||
        !otpHandlerTableExists($conn, 'complaintstbl') ||
        !otpHandlerTableExists($conn, 'caseparticipantstbl')
    ) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT
            ct.complaint_id,
            c.case_id,
            COALESCE(s.status_name, 'Pending') AS status_name
        FROM caseparticipantstbl cp
        INNER JOIN casereportstbl c ON c.case_id = cp.case_id
        INNER JOIN complaintstbl ct ON ct.case_id = c.case_id
        LEFT JOIN statuslookuptbl s ON s.status_id = c.case_status_id
        WHERE c.report_type = 'Complaint'
          AND cp.participant_role = 'Complainant'
          AND cp.contact_number = ?
          AND LOWER(COALESCE(s.status_name, 'pending')) NOT IN ('resolved', 'closed', 'dropped')
        ORDER BY c.case_id DESC
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return is_array($row) ? $row : null;
}

function otpHandlerRecentOtpRequest(mysqli $conn, string $recipient, string $purpose, int $windowSeconds): ?array
{
    $stmt = $conn->prepare("
        SELECT request_timestamp
        FROM otprequesttbl
        WHERE recipient = ? AND purpose = ?
        ORDER BY request_timestamp DESC
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("ss", $recipient, $purpose);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!is_array($row) || empty($row['request_timestamp'])) {
        return null;
    }

    $requestTimestamp = strtotime((string)$row['request_timestamp']);
    if ($requestTimestamp === false) {
        return null;
    }

    $elapsed = time() - $requestTimestamp;
    if ($elapsed >= $windowSeconds) {
        return null;
    }

    return [
        'remaining_seconds' => max(1, $windowSeconds - $elapsed),
    ];
}

function otpHandlerUserPhone10ByUserId(mysqli $conn, string $userId): string
{
    if ($userId === '') {
        return '';
    }

    $stmt = $conn->prepare("SELECT phone_number FROM useraccountstbl WHERE user_id = ? LIMIT 1");
    if (!$stmt) {
        return '';
    }

    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $row = $row ? (pii_decrypt_useraccount_row($row) ?? $row) : null;
    $digits = preg_replace('/\D+/', '', (string)($row['phone_number'] ?? ''));

    return strlen($digits) >= 10 ? substr($digits, -10) : '';
}

// ===== Validate input =====
if (!is_string($_POST['recipient'] ?? null) || !is_string($_POST['purpose'] ?? null)) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$rawRecipient = trim((string)$_POST['recipient']);
$purpose      = trim((string)$_POST['purpose']);
$user_id      = is_string($_POST['user_id'] ?? null) ? trim((string)$_POST['user_id']) : null;
$captchaAnswer = trim((string)($_POST['captcha_answer'] ?? ''));
$recaptchaToken = trim((string)($_POST['recaptcha_token'] ?? ''));

// ===== Normalize recipient to 10 digits ONLY for DB =====
// Accepts: 09XXXXXXXXX or 9XXXXXXXXX
if (preg_match('/^09\d{9}$/', $rawRecipient)) {
    $recipient_db = substr($rawRecipient, 1); // remove leading 0
} elseif (preg_match('/^9\d{9}$/', $rawRecipient)) {
    $recipient_db = $rawRecipient;
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid phone number format']);
    exit;
}

if (!in_array($purpose, ['signup', 'forgot', 'inactive', 'admin_2fa', 'guest_appointment', 'guest_complaint'], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid OTP purpose.']);
    exit;
}

if ($purpose === 'signup') {
    $expectedPhone = pii_normalize_phone10((string)($_SESSION['signup_otp_phone'] ?? ''));
    if ($expectedPhone === '' || $expectedPhone !== $recipient_db) {
        echo json_encode(['success' => false, 'error' => 'Signup verification session expired. Please review your details again.']);
        exit;
    }
}

if ($purpose === 'forgot') {
    $expectedPhone = pii_normalize_phone10((string)($_SESSION['forgot_password_otp_phone'] ?? ''));
    if ($expectedPhone === '' || $expectedPhone !== $recipient_db) {
        echo json_encode(['success' => false, 'error' => 'Password reset verification expired. Please start again.']);
        exit;
    }
}

if ($purpose === 'inactive' || $purpose === 'admin_2fa') {
    $pendingUserId = trim((string)($_SESSION['pending_user_id'] ?? ''));
    $pendingVerify = trim((string)($_SESSION['pending_verify'] ?? ''));
    if ($pendingUserId === '' || $pendingVerify !== $purpose) {
        echo json_encode(['success' => false, 'error' => 'Session expired. Please login again.']);
        exit;
    }

    $user_id = $pendingUserId;
    $expectedPhone = otpHandlerUserPhone10ByUserId($conn, $pendingUserId);
    if ($expectedPhone === '' || $expectedPhone !== $recipient_db) {
        echo json_encode(['success' => false, 'error' => 'The verification number does not match your account.']);
        exit;
    }
}

if (recaptcha_v3_should_enforce()) {
    $recaptchaActionMap = [
        'signup' => 'login_signup_otp',
        'forgot' => 'login_forgot_otp',
        'inactive' => 'login_inactive_otp',
        'admin_2fa' => 'login_admin_2fa_otp',
        'guest_appointment' => 'guest_appointment_otp',
        'guest_complaint' => 'guest_complaint_otp',
    ];
    $recaptchaAction = $recaptchaActionMap[$purpose] ?? '';
    $recaptchaCheck = recaptcha_v3_verify($recaptchaToken, $recaptchaAction);
    if (empty($recaptchaCheck['success'])) {
        echo json_encode([
            'success' => false,
            'error' => (string)($recaptchaCheck['message'] ?? 'Security verification failed. Please try again.'),
        ]);
        exit;
    }
}

if ($purpose === 'guest_complaint') {
    $activeComplaint = otpHandlerGuestComplaintFindActiveByPhone($conn, '0' . $recipient_db);
    if (is_array($activeComplaint)) {
        $reference = trim((string)($activeComplaint['complaint_id'] ?? $activeComplaint['case_id'] ?? ''));
        $referenceText = $reference !== '' ? " Reference: {$reference}." : '';
        echo json_encode([
            'success' => false,
            'error' => 'This mobile number already has an active complaint under review. Please wait until it is completed before submitting another one.' . $referenceText,
        ]);
        exit;
    }

    $recentOtpRequest = otpHandlerRecentOtpRequest($conn, $recipient_db, $purpose, 60);
    if (is_array($recentOtpRequest)) {
        $remainingSeconds = (int)($recentOtpRequest['remaining_seconds'] ?? 0);
        echo json_encode([
            'success' => false,
            'error' => $remainingSeconds > 0
                ? "Please wait {$remainingSeconds} seconds before requesting another OTP."
                : 'Please wait before requesting another OTP.',
        ]);
        exit;
    }
}

if ($purpose === 'guest_appointment') {
    $activeAppointment = apsh_find_active_appointment_by_phone($conn, '0' . $recipient_db);
    if (is_array($activeAppointment)) {
        echo json_encode(['success' => false, 'error' => apsh_active_appointment_phone_message($activeAppointment)]);
        exit;
    }

    $recentOtpRequest = apsh_guest_appointment_recent_otp_request($conn, $recipient_db, 60);
    if (is_array($recentOtpRequest)) {
        $remainingSeconds = (int)($recentOtpRequest['remaining_seconds'] ?? 0);
        echo json_encode([
            'success' => false,
            'error' => $remainingSeconds > 0
                ? "Please wait {$remainingSeconds} seconds before requesting another OTP."
                : 'Please wait before requesting another OTP.',
        ]);
        exit;
    }
}

if ($purpose === 'signup' || $purpose === 'forgot' || $purpose === 'inactive' || $purpose === 'admin_2fa') {
    $recentOtpRequest = otpHandlerRecentOtpRequest($conn, $recipient_db, $purpose, 60);
    if (is_array($recentOtpRequest)) {
        $remainingSeconds = (int)($recentOtpRequest['remaining_seconds'] ?? 0);
        echo json_encode([
            'success' => false,
            'error' => $remainingSeconds > 0
                ? "Please wait {$remainingSeconds} seconds before requesting another OTP."
                : 'Please wait before requesting another OTP.',
        ]);
        exit;
    }
}

// ===== Generate 6-digit OTP =====
$otp_code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

// ===== Hash OTP =====
$otp_hash = password_hash($otp_code, PASSWORD_DEFAULT);

// ===== Manila Time =====
date_default_timezone_set('Asia/Manila');
$request_time = date('Y-m-d H:i:s');
$expiry_time  = date('Y-m-d H:i:s', strtotime('+5 minutes'));

// ===== Status IDs =====
$STATUS_PENDING = 6;

// ===== Insert OTP Request =====
$otp_id = 0;
try {
    $otp_id = insertOtpRequest($conn, $user_id, $recipient_db, $purpose, $otp_hash, $expiry_time, $request_time, $STATUS_PENDING);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Unable to store OTP request.']);
    exit;
}

// ===== Send OTP on server-side only (do not expose OTP to client) =====
$recipient_sms = '0' . $recipient_db; // 11 digits
$message = "Your OTP code is $otp_code";
$sent = sendSMS($recipient_sms, $message, $otp_code);

if (!$sent) {
    if ($otp_id > 0) {
        $cleanup = $conn->prepare("DELETE FROM otprequesttbl WHERE otp_id = ? LIMIT 1");
        if ($cleanup) {
            $cleanup->bind_param("i", $otp_id);
            $cleanup->execute();
            $cleanup->close();
        }
    }

    echo json_encode([
        'success' => false,
        'error' => getLastSmsError() !== '' ? getLastSmsError() : 'Failed to send OTP. Please try again.'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'expires_at' => $expiry_time
]);
?>
