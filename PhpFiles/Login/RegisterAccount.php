<?php
require_once __DIR__ . "/../General/security.php";

header('Content-Type: application/json');
require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/uniqueIDGenerate.php';
require_once __DIR__ . '/redirectDestination.php';

$registrationSettings = wms_load_settings($conn);
if (empty($registrationSettings['registration_enabled'])) {
    echo json_encode(['success' => false, 'error' => (string)$registrationSettings['registration_message'], 'registration_closed' => true]);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 0); // Never show errors to browser
ini_set('log_errors', 1);
error_reporting(E_ALL);

function isSequentialPhone($phone) {
    if (!preg_match('/^\d{10}$/', $phone)) return false;
    $seqAsc = "0123456789";
    $seqDesc = "9876543210";
    $last9 = substr($phone, 1);

    if (strpos($seqAsc, $phone) !== false || strpos($seqDesc, $phone) !== false) return true;
    if (strpos($seqAsc, $last9) !== false || strpos($seqDesc, $last9) !== false) return true;

    return false;
}

function lookupRecentVerifiedSignupOtp(mysqli $conn, string $phoneNumber): ?array
{
    $verifiedStatusId = 7;
    $recentCutoff = date('Y-m-d H:i:s', strtotime('-15 minutes'));

    $stmt = $conn->prepare("
        SELECT otp_id, request_timestamp
        FROM otprequesttbl
        WHERE recipient = ?
          AND purpose = 'signup'
          AND status_id_otp = ?
          AND request_timestamp >= ?
        ORDER BY request_timestamp DESC
        LIMIT 1
    ");
    if (!$stmt) {
        throw new Exception("Database error (otp fallback): " . $conn->error);
    }

    $stmt->bind_param("sis", $phoneNumber, $verifiedStatusId, $recentCutoff);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

try {
    // Only allow POST
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Invalid request method.");
    }

    // ===== Get POST Data =====
    $PhoneNumber = pii_normalize_phone10(trim($_POST['RPhoneNumber'] ?? ''));
    $Email       = pii_normalize_email(trim($_POST['REmail'] ?? ''));
    $Password    = $_POST['RPassword'] ?? '';
    $requestedService = normalizeRequestedResidentService($_POST['post_login_service'] ?? '');

    // ===== Validation =====
    $errors = [];

    // ✅ Must start with 9 and be exactly 10 digits
    if (!preg_match('/^9[0-9]{9}$/', $PhoneNumber)) {
        $errors[] = "Invalid phone number.";
    } elseif (preg_match('/^9(\d)\1{8}$/', $PhoneNumber)) {
        $errors[] = "Invalid phone number.";
    } elseif (isSequentialPhone($PhoneNumber)) {
        $errors[] = "Invalid phone number.";
    }

    if ($Email === '') {
        $errors[] = "Email address is required.";
    } elseif (!preg_match('/^[A-Za-z0-9._%+-]{2,}@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/', $Email)) {
        $errors[] = "Invalid email address (at least 2 characters before @).";
    }

    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $Password)) {
        $errors[] = "Password must be at least 8 characters, including uppercase, lowercase, number, and special character.";
    }

    if (!empty($errors)) throw new Exception(implode(" ", $errors));

    // ===== Require server-side OTP verification for signup =====
    $signupOtp = $_SESSION['signup_otp_verified'] ?? null;
    if (!is_array($signupOtp)) {
        $fallbackOtp = lookupRecentVerifiedSignupOtp($conn, $PhoneNumber);
        if ($fallbackOtp) {
            $signupOtp = [
                'phone' => $PhoneNumber,
                'otp_id' => (int)($fallbackOtp['otp_id'] ?? 0),
                'verified_at' => time(),
            ];
            $_SESSION['signup_otp_verified'] = $signupOtp;
        }
    }
    if (!is_array($signupOtp)) {
        throw new Exception("Phone OTP verification is required before creating an account.");
    }

    $verifiedPhone = (string)($signupOtp['phone'] ?? '');
    $verifiedAt = (int)($signupOtp['verified_at'] ?? 0);
    if ($verifiedPhone !== $PhoneNumber) {
        throw new Exception("Phone OTP verification does not match the provided phone number.");
    }
    if ($verifiedAt <= 0 || (time() - $verifiedAt) > 600) {
        unset($_SESSION['signup_otp_verified']);
        throw new Exception("Phone OTP session expired. Please verify again.");
    }

    $verifiedOtpId = (int)($signupOtp['otp_id'] ?? 0);
    if ($verifiedOtpId <= 0) {
        unset($_SESSION['signup_otp_verified']);
        throw new Exception("Invalid OTP verification session. Please verify again.");
    }

    $otpStatusVerified = 7;
    $otpCheck = $conn->prepare("
        SELECT otp_id
        FROM otprequesttbl
        WHERE otp_id = ?
          AND recipient = ?
          AND purpose = 'signup'
          AND status_id_otp = ?
        LIMIT 1
    ");
    if (!$otpCheck) throw new Exception("Database error (otp check): " . $conn->error);
    $otpCheck->bind_param("isi", $verifiedOtpId, $PhoneNumber, $otpStatusVerified);
    $otpCheck->execute();
    $otpRes = $otpCheck->get_result();
    $otpValid = $otpRes && $otpRes->num_rows > 0;
    $otpCheck->close();
    if (!$otpValid) {
        unset($_SESSION['signup_otp_verified']);
        throw new Exception("Phone OTP verification is invalid. Please verify again.");
    }

    // ===== Check Existing Phone/Email =====
    $lookup = pii_prepare_useraccount_contacts($Email, $PhoneNumber);
    $existingPhone = pii_select_first_useraccount_by_lookup_hashes(
        $conn,
        'phone_lookup_hash',
        pii_lookup_hash_candidates($PhoneNumber, 'useraccount.phone'),
        ['user_id']
    ) !== null;
    $existingEmail = pii_select_first_useraccount_by_lookup_hashes(
        $conn,
        'email_lookup_hash',
        pii_lookup_hash_candidates($Email, 'useraccount.email'),
        ['user_id']
    ) !== null;

    if ($existingPhone || $existingEmail) {
        $msg = [];
        if ($existingPhone) $msg[] = "Phone number is already registered.";
        if ($existingEmail) $msg[] = "Email is already registered.";
        throw new Exception(implode(" ", $msg));
    }

    // ===== Role Setup =====
    $RoleAccess = "Resident";// Resident-only registration
    $AccountCreated = date('Y-m-d H:i:s');
    $LastLogin = $AccountCreated;

    // ===== Generate User ID using Role =====
    $UserID = GenerateUserID($conn, $RoleAccess);
    if (!$UserID) throw new Exception("Could not generate User ID.");

    // ===== Password Hash =====
    $PasswordHash = password_hash($Password, PASSWORD_DEFAULT);

    // ===== Active Status ID =====
    $statusStmt = $conn->prepare("SELECT status_id FROM statuslookuptbl WHERE status_name = 'Active' AND status_type = 'UserAccount' LIMIT 1");
    if (!$statusStmt) throw new Exception("Database error (status lookup): " . $conn->error);
    $statusStmt->execute();
    $statusResult = $statusStmt->get_result();
    if ($statusResult->num_rows === 0) throw new Exception("Active status not found in lookup table.");
    $statusRow = $statusResult->fetch_assoc();
    $ActiveStatusID = $statusRow['status_id'];
    $statusStmt->close();

    // ===== Insert into useraccountstbl =====
    $stmt = $conn->prepare("
        INSERT INTO useraccountstbl
(user_id, phone_number, phone_lookup_hash, phoneNum_verify, email, email_lookup_hash, email_verify, password_hash, role_access, account_created, last_login, status_id_account)
VALUES (?, ?, ?, 1, ?, ?, 0, ?, ?, ?, ?, ?)

    ");
    if (!$stmt) throw new Exception("Database error: " . $conn->error);

    $stmt->bind_param(
        "sssssssssi",
        $UserID,
        $lookup['phone_number'],
        $lookup['phone_lookup_hash'],
        $lookup['email'],
        $lookup['email_lookup_hash'],
        $PasswordHash,
        $RoleAccess,
        $AccountCreated,
        $LastLogin,
        $ActiveStatusID
    );

    if (!$stmt->execute()) throw new Exception("Unable to create account. " . $stmt->error);
    $stmt->close();

    // Consume signup OTP session so it cannot be reused.
    unset($_SESSION['signup_otp_verified']);

    // ===== Auto Login =====
    $_SESSION['user_id'] = $UserID;
    $_SESSION['phone'] = $PhoneNumber;
    $_SESSION['email'] = $Email;
    $_SESSION['role'] = $RoleAccess;
    $_SESSION['status'] = 'Active';
    $_SESSION['logged_in'] = true;
    $_SESSION['last_activity'] = time();

    echo json_encode([
        "success" => true,
        "redirect" => resolveRequestedPostLoginRedirect($conn, $UserID, $RoleAccess, $requestedService)
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
    exit;
}
?>
