<?php
header('Content-Type: application/json');
require '../General/connection.php';
require '../General/uniqueIDGenerate.php';

session_start();
ini_set('display_errors', 0); // Never show errors to browser
ini_set('log_errors', 1);
error_reporting(E_ALL);

try {
    // Only allow POST
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Invalid request method.");
    }

    // ===== Get POST Data =====
    $PhoneNumber = trim($_POST['RPhoneNumber'] ?? '');
    $Email       = trim($_POST['REmail'] ?? '');
    $Password    = $_POST['RPassword'] ?? '';

    // ✅ Normalize phone: digits only, keep last 10
    $PhoneNumber = preg_replace('/\D+/', '', $PhoneNumber);
    $PhoneNumber = substr($PhoneNumber, -10);

    // ===== Validation =====
    $errors = [];

    // ✅ Must start with 9 and be exactly 10 digits
    if (!preg_match('/^9[0-9]{9}$/', $PhoneNumber)) {
        $errors[] = "Phone number must start with 9 and be exactly 10 digits.";
    }

    if ($Email === '') {
        $errors[] = "Email address is required.";
    } elseif (!filter_var($Email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address.";
    }

    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $Password)) {
        $errors[] = "Password must be at least 8 characters, including uppercase, lowercase, number, and special character.";
    }

    if (!empty($errors)) throw new Exception(implode(" ", $errors));

    // ===== Check Existing Phone/Email =====
    $stmt = $conn->prepare("SELECT phone_number, email FROM useraccountstbl WHERE phone_number = ? OR email = ?");
    if (!$stmt) throw new Exception("Database error: " . $conn->error);
    $stmt->bind_param("ss", $PhoneNumber, $Email);
    $stmt->execute();
    $result = $stmt->get_result();

    $existingPhone = false;
    $existingEmail = false;
    while ($row = $result->fetch_assoc()) {
        if ($row['phone_number'] === $PhoneNumber) $existingPhone = true;
        if ($row['email'] === $Email) $existingEmail = true;
    }
    $stmt->close();

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
(user_id, phone_number, phoneNum_verify, email, email_verify, password_hash, role_access, account_created, last_login, status_id_account)
VALUES (?, ?, 1, ?, 0, ?, ?, ?, ?, ?)

    ");
    if (!$stmt) throw new Exception("Database error: " . $conn->error);

    $stmt->bind_param(
        "sssssssi",
        $UserID,
        $PhoneNumber,
        $Email,
        $PasswordHash,
        $RoleAccess,
        $AccountCreated,
        $LastLogin,
        $ActiveStatusID
    );

    if (!$stmt->execute()) throw new Exception("Unable to create account. " . $stmt->error);
    $stmt->close();

    // ===== Auto Login =====
    $_SESSION['user_id'] = $UserID;
    $_SESSION['phone'] = $PhoneNumber;
    $_SESSION['email'] = $Email;
    $_SESSION['role'] = $RoleAccess;
    $_SESSION['status'] = 'Active';
    $_SESSION['logged_in'] = true;

    echo json_encode([
        "success" => true,
        "redirect" => "../PhpFiles/Login/unifiedProfileCheck.php"
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
