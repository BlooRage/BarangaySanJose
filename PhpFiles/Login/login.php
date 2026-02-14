<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

require '../General/connection.php';

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
    $email = $userInput;
    $phone = null;
} else {
    $email = null;
    $phone = $normalizedPhone;
}

/* =============================
   FETCH USER
   (Added phone_number + email for masking/flows)
============================= */
if ($email) {
    $stmt = $conn->prepare("
        SELECT
            user_id,
            email,
            phone_number,
            password_hash,
            failed_logins,
            status_id_account,
            lock_start,
            role_access
        FROM useraccountstbl
        WHERE email = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $email);
} else {
    $stmt = $conn->prepare("
        SELECT
            user_id,
            email,
            phone_number,
            password_hash,
            failed_logins,
            status_id_account,
            lock_start,
            role_access
        FROM useraccountstbl
        WHERE RIGHT(phone_number,10) = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $phone);
}

$stmt->execute();
$result   = $stmt->get_result();
$userData = $result->fetch_assoc();

if (!$userData) {
    echo json_encode(['success' => false, 'error' => 'Account cannot be found.']);
    exit;
}

/* =============================
   LOAD STATUS IDS (case-insensitive)
============================= */
$statusStmt = $conn->prepare("
    SELECT status_id, status_name
    FROM statuslookuptbl
    WHERE status_type = 'UserAccount'
");
$statusStmt->execute();
$statusResult = $statusStmt->get_result();

$statuses = [];
while ($row = $statusResult->fetch_assoc()) {
    $key = strtolower(trim($row['status_name']));
    $statuses[$key] = $row['status_id'];
}
$statusStmt->close();

$lockedStatusId      = $statuses['locked'] ?? null;
$activeStatusId      = $statuses['active'] ?? null;
$inactiveStatusId    = $statuses['inactive'] ?? null;       // ✅ added
$deactivatedStatusId = $statuses['deactivated'] ?? null;
$deletedStatusId     = $statuses['deleted'] ?? null;

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
    $lockStart = strtotime($userData['lock_start'] ?? '1970-01-01');

    // still locked
    if (time() - $lockStart < $lockDuration) {
        echo json_encode([
            'success' => false,
            'error'   => 'Account is locked. Please try again later.'
        ]);
        exit;
    }

    // lock expired → unlock to Active if available, else just clear lock fields
    if ($activeStatusId !== null) {
        $unlock = $conn->prepare("
            UPDATE useraccountstbl
            SET status_id_account = ?, failed_logins = 0, lock_start = NULL
            WHERE user_id = ?
        ");
        $unlock->bind_param('is', $activeStatusId, $userData['user_id']);
        $unlock->execute();

        // reflect locally
        $userData['status_id_account'] = $activeStatusId;
    } else {
        $unlock = $conn->prepare("
            UPDATE useraccountstbl
            SET failed_logins = 0, lock_start = NULL
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
            $updateStmt = $conn->prepare("
                UPDATE useraccountstbl
                SET failed_logins = 0,
                    status_id_account = ?,
                    lock_start = NOW()
                WHERE user_id = ?
            ");
            $updateStmt->bind_param('is', $lockedStatusId, $userData['user_id']);
            $updateStmt->execute();
        } else {
            $updateStmt = $conn->prepare("
                UPDATE useraccountstbl
                SET failed_logins = 0,
                    lock_start = NOW()
                WHERE user_id = ?
            ");
            $updateStmt->bind_param('s', $userData['user_id']);
            $updateStmt->execute();
        }

        echo json_encode(['success' => false, 'error' => 'Account locked due to failed attempts.']);
        exit;
    }

    // update failed logins only
    $updateStmt = $conn->prepare("
        UPDATE useraccountstbl
        SET failed_logins = ?, lock_start = NULL
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
            last_login = NOW()
        WHERE user_id = ?s
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

echo json_encode([
    'success'  => true,
    'status'   => 'active',
    'redirect' => '../PhpFiles/Login/unifiedProfileCheck.php'
]);
exit;
?>
