<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../General/security.php';

header('Content-Type: application/json');
require '../General/connection.php';

$registrationSettings = wms_load_settings($conn);
if (empty($registrationSettings['registration_enabled'])) {
    echo json_encode(['success' => false, 'error' => (string)$registrationSettings['registration_message'], 'registration_closed' => true]);
    exit;
}

if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$phone = trim((string)($_POST['phone'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));

if ($phone === '' && $email === '') {
    echo json_encode(['success' => false, 'error' => 'Phone or email must be provided']);
    exit;
}

$response = [
    'success' => true,
    'phoneExists' => false,
    'emailExists' => false
];

if ($phone) {
    $phone = pii_normalize_phone10($phone);
    $response['phoneExists'] = pii_select_first_useraccount_by_lookup_hashes(
        $conn,
        'phone_lookup_hash',
        pii_lookup_hash_candidates($phone, 'useraccount.phone'),
        ['user_id']
    ) !== null;

    if (!$response['phoneExists']) {
        $_SESSION['signup_otp_phone'] = $phone;
    } else {
        unset($_SESSION['signup_otp_phone']);
    }
}

if ($email) {
    $email = pii_normalize_email($email);
    $response['emailExists'] = pii_select_first_useraccount_by_lookup_hashes(
        $conn,
        'email_lookup_hash',
        pii_lookup_hash_candidates($email, 'useraccount.email'),
        ['user_id']
    ) !== null;
}

echo json_encode($response);
exit;
