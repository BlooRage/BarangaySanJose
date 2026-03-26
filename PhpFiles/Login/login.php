<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once "../General/security.php";
require_once "../General/userAccountLocks.php";
require_once __DIR__ . "/redirectDestination.php";
header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

require '../General/connection.php';
ual_ensure_lock_columns($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$userInput = trim($_POST['user'] ?? '');
$password  = $_POST['loginPassword'] ?? '';

if ($userInput === '' || $password === '') {
    echo json_encode(['success' => false, 'error' => 'Please enter your credentials']);
    exit;
}

/* =============================
   LOGIN ATTEMPT CONFIG
============================= */
$maxAttempts  = 3;
$lockDuration = 300; // seconds

/* =============================
   NORMALIZE PHONE / EMAIL
============================= */
$digits = preg_replace('/\D/', '', $userInput);
$normalizedPhone = substr($digits, -10);

if (filter_var($userInput, FILTER_VALIDATE_EMAIL)) {
    $email = pii_normalize_email($userInput);
    $phone = null;
} else {
    $email = null;
    $phone = pii_normalize_phone10($normalizedPhone);
}

/* =============================
   FETCH USER
   (Added phone_number + email for masking/flows)
============================= */
if ($email) {
    $emailHash = pii_lookup_hash($email, 'useraccount.email');
    $stmt = $conn->prepare("
        SELECT
            user_id,
            email,
            phone_number,
            password_hash,
            failed_logins,
            status_id_account,
            lock_start,
            lock_until,
            lock_type,
            lock_reason,
            locked_by_user_id,
            role_access
        FROM useraccountstbl
        WHERE email_lookup_hash = ?
        LIMIT 1
    ");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Unable to process login right now.']);
        exit;
    }
    $stmt->bind_param('s', $emailHash);
} else {
    $phoneHash = pii_lookup_hash($phone, 'useraccount.phone');
    $stmt = $conn->prepare("
        SELECT
            user_id,
            email,
            phone_number,
            password_hash,
            failed_logins,
            status_id_account,
            lock_start,
            lock_until,
            lock_type,
            lock_reason,
            locked_by_user_id,
            role_access
        FROM useraccountstbl
        WHERE phone_lookup_hash = ?
        LIMIT 1
    ");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Unable to process login right now.']);
        exit;
    }
    $stmt->bind_param('s', $phoneHash);
}

$stmt->execute();
$result   = $stmt->get_result();
$userData = $result->fetch_assoc();
$userData = $userData ? pii_decrypt_useraccount_row($userData) : null;

if (!$userData) {
    echo json_encode(['success' => false, 'error' => 'Account cannot be found.']);
    exit;
}

/* =============================
   LOAD STATUS IDS (case-insensitive)
============================= */
$statuses = ual_load_status_ids($conn);

$lockedStatusId      = $statuses['locked'] ?? null;
$activeStatusId      = $statuses['active'] ?? null;
$inactiveStatusId    = $statuses['inactive'] ?? null;       // ✅ added
$deactivatedStatusId = $statuses['deactivated'] ?? null;
$deletedStatusId     = $statuses['deleted'] ?? null;

ual_release_expired_locks($conn, $lockedStatusId, $activeStatusId, $lockDuration);

/* =============================
   ACCOUNT STATUS CHECK (Deactivated/Deleted)
============================= */
if ($deactivatedStatusId !== null && (int)$userData['status_id_account'] === (int)$deactivatedStatusId) {
    echo json_encode(['success' => false, 'error' => 'Account Deactivated.']);
    exit;
}
if ($deletedStatusId !== null && (int)$userData['status_id_account'] === (int)$deletedStatusId) {
    echo json_encode(['success' => false, 'error' => 'Account cannot be found.']);
    exit;
}

/* =============================
   LOCK CHECK
============================= */
if ($lockedStatusId !== null && (int)$userData['status_id_account'] === (int)$lockedStatusId) {
    $lockState = ual_get_lock_state($userData, $lockDuration);

    if (!$lockState['is_expired']) {
        $errorMessage = 'Account is locked.';
        if ($lockState['is_permanent']) {
            $errorMessage = 'Account is locked permanently. Please contact the barangay office.';
        } elseif (!empty($lockState['lock_until_ts'])) {
            $errorMessage = 'Account is locked until ' . date('F j, Y g:i A', (int)$lockState['lock_until_ts']) . '.';
        }
        echo json_encode([
            'success' => false,
            'error'   => $errorMessage
        ]);
        exit;
    }

    // lock expired → unlock to Active if available, else just clear lock fields
    if ($activeStatusId !== null) {
        $unlock = $conn->prepare("
            UPDATE useraccountstbl
            SET status_id_account = ?,
                failed_logins = 0,
                lock_start = NULL,
                lock_until = NULL,
                lock_type = NULL,
                lock_reason = NULL,
                locked_by_user_id = NULL
            WHERE user_id = ?
        ");
        $unlock->bind_param('is', $activeStatusId, $userData['user_id']);
        $unlock->execute();

        // reflect locally
        $userData['status_id_account'] = $activeStatusId;
    } else {
        $unlock = $conn->prepare("
            UPDATE useraccountstbl
            SET failed_logins = 0,
                lock_start = NULL,
                lock_until = NULL,
                lock_type = NULL,
                lock_reason = NULL,
                locked_by_user_id = NULL
            WHERE user_id = ?
        ");
        $unlock->bind_param('s', $userData['user_id']);
        $unlock->execute();
    }

    $userData['failed_logins'] = 0;
}

/* =============================
   PASSWORD CHECK
============================= */
if (!password_verify($password, $userData['password_hash'])) {

    $failedLogins = (int)$userData['failed_logins'] + 1;

    if ($failedLogins >= $maxAttempts) {

        // lock account
        if ($lockedStatusId !== null) {
            $lockUntil = date('Y-m-d H:i:s', time() + $lockDuration);
            $updateStmt = $conn->prepare("
                UPDATE useraccountstbl
                SET failed_logins = 0,
                    status_id_account = ?,
                    lock_start = NOW(),
                    lock_until = ?,
                    lock_type = 'temporary',
                    lock_reason = 'Failed login attempts',
                    locked_by_user_id = NULL
                WHERE user_id = ?
            ");
            $updateStmt->bind_param('iss', $lockedStatusId, $lockUntil, $userData['user_id']);
            $updateStmt->execute();
        } else {
            $lockUntil = date('Y-m-d H:i:s', time() + $lockDuration);
            $updateStmt = $conn->prepare("
                UPDATE useraccountstbl
                SET failed_logins = 0,
                    lock_start = NOW(),
                    lock_until = ?,
                    lock_type = 'temporary',
                    lock_reason = 'Failed login attempts',
                    locked_by_user_id = NULL
                WHERE user_id = ?
            ");
            $updateStmt->bind_param('ss', $lockUntil, $userData['user_id']);
            $updateStmt->execute();
        }

        echo json_encode(['success' => false, 'error' => 'Account locked due to failed attempts.']);
        exit;
    }

    // update failed logins only
    $updateStmt = $conn->prepare("
        UPDATE useraccountstbl
        SET failed_logins = ?,
            lock_start = NULL,
            lock_until = NULL,
            lock_type = NULL,
            lock_reason = NULL,
            locked_by_user_id = NULL
        WHERE user_id = ?
    ");
    $updateStmt->bind_param('is', $failedLogins, $userData['user_id']);
    $updateStmt->execute();

    echo json_encode(['success' => false, 'error' => 'Invalid credentials.']);
    exit;
}

/* =============================
   ✅ INACTIVE FLOW (NEW)
   If status is Inactive, DO NOT log them in yet.
   Return JSON telling front-end to show:
   "Let's verify your account first" → Continue → OTP
============================= */
if ($inactiveStatusId !== null && (int)$userData['status_id_account'] === (int)$inactiveStatusId) {

    // store only a "pending verification" session
    $_SESSION['pending_user_id'] = $userData['user_id'];
    $_SESSION['pending_verify']  = 'inactive';

    // mask phone: show last 4 digits
    $phoneDigits = preg_replace('/\D/', '', (string)($userData['phone_number'] ?? ''));
    $last4 = ($phoneDigits !== '' && strlen($phoneDigits) >= 4) ? substr($phoneDigits, -4) : 'XXXX';
    $masked = '+63 •••••• ' . $last4;

    echo json_encode([
        'success'      => true,
        'status'       => 'inactive',
        'user_id'      => $userData['user_id'],     // you can remove this if you prefer session-only
        'phone_masked' => $masked
    ]);
    exit;
}

/* =============================
   LOGIN SUCCESS (ACTIVE / OTHERS)
   NOTE: your original code forces status to Active on login.
   Keeping it as-is.
============================= */
if ($activeStatusId !== null) {
    $updateStmt = $conn->prepare("
        UPDATE useraccountstbl
        SET failed_logins = 0,
            status_id_account = ?,
            lock_start = NULL,
            lock_until = NULL,
            lock_type = NULL,
            lock_reason = NULL,
            locked_by_user_id = NULL,
            last_login = NOW()
        WHERE user_id = ?
    ");
    $updateStmt->bind_param('is', $activeStatusId, $userData['user_id']);
    $updateStmt->execute();
} else {
    $updateStmt = $conn->prepare("
        UPDATE useraccountstbl
        SET failed_logins = 0,
            lock_start = NULL,
            lock_until = NULL,
            lock_type = NULL,
            lock_reason = NULL,
            locked_by_user_id = NULL,
            last_login = NOW()
        WHERE user_id = ?
    ");
    $updateStmt->bind_param('s', $userData['user_id']);
    $updateStmt->execute();
}

/* =============================
   SESSION + REDIRECT
============================= */
unset($_SESSION['pending_user_id'], $_SESSION['pending_verify']);

// Prevent session fixation after successful authentication.
session_regenerate_id(true);

$_SESSION['user_id']    = $userData['user_id'];
$_SESSION['role']       = $userData['role_access'];
$_SESSION['logged_in']  = true;
$_SESSION['last_activity'] = time();
$_SESSION['show_not_verified_modal'] = true;

echo json_encode([
    'success'  => true,
    'status'   => 'active',
    'redirect' => resolveUnifiedProfileRedirect($conn, (string)$userData['user_id'], (string)$userData['role_access'])
]);
exit;
?>
