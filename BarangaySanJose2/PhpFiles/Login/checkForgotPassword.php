<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
require '../General/connection.php';
header('Content-Type: application/json');

$phone = $_POST['phone'] ?? '';
$email = $_POST['email'] ?? '';

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


$stmt = $conn->prepare("
    SELECT user_id 
    FROM useraccountstbl 
    WHERE email = ? AND phone_number = ?
    LIMIT 1
");
$stmt->bind_param("ss", $email, $phone);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'error' => 'No account found matching the provided email and phone number.'
    ]);
    exit;
}

$stmt->close();


echo json_encode([
    'success' => true
]);
exit;
?>
