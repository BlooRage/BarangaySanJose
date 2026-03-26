<?php
// FILE: ../PhpFiles/GET/getAccountContact.php

require_once __DIR__ . '/../General/security.php';
header('Content-Type: application/json; charset=utf-8');

require_once "../General/connection.php"; // adjust if your path differs

requireAuthenticatedSession(true);

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT phone_number, email FROM useraccountstbl WHERE user_id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param("s", $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$row = $row ? (pii_decrypt_useraccount_row($row) ?? $row) : [];

$phone = $row['phone_number'] ?? '';
$email = $row['email'] ?? '';

// Normalize phone for +63 UI (expects 10 digits like 9XXXXXXXXX)
$digits = preg_replace('/\D+/', '', (string)$phone);
if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
    $digits = substr($digits, 1);
}

echo json_encode([
    "success" => true,
    "phone_number" => $digits,
    "email" => $email
]);
exit;
