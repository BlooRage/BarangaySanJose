<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../General/security.php';
require '../General/connection.php';
header('Content-Type: application/json');

$phone = pii_normalize_phone10((string)($_POST['phone'] ?? ''));
$email = pii_normalize_email((string)($_POST['email'] ?? ''));

if (!$phone || !$email) {
    echo json_encode([
        'success' => false,
        'error' => 'Email and phone number are required.'
    ]);
    exit;
}


if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid email format.'
    ]);
    exit;
}

if (!preg_match('/^[0-9]{10}$/', $phone)) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid phone number format.'
    ]);
    exit;
}

$userAccount = pii_select_first_useraccount_by_contact_hashes(
    $conn,
    pii_lookup_hash_candidates($email, 'useraccount.email'),
    pii_lookup_hash_candidates($phone, 'useraccount.phone'),
    ['user_id']
);

if (!$userAccount) {
    echo json_encode([
        'success' => false,
        'error' => 'No account found matching the provided email and phone number.'
    ]);
    exit;
}

echo json_encode([
    'success' => true
]);
exit;
?>
