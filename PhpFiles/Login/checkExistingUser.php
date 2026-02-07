<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);


header('Content-Type: application/json');
require '../General/connection.php';

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
    $stmt = $conn->prepare("SELECT user_id FROM useraccountstbl WHERE phone_number = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Query failed']);
        exit;
    }
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $stmt->store_result();
    $response['phoneExists'] = $stmt->num_rows > 0;
    $stmt->close();
}

if ($email) {
    $stmt = $conn->prepare("SELECT user_id FROM useraccountstbl WHERE email = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Query failed']);
        exit;
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    $response['emailExists'] = $stmt->num_rows > 0;
    $stmt->close();
}

echo json_encode($response);
exit;
