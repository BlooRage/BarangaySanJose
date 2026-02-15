<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../General/connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$currentPassword = (string)($payload['current_password'] ?? '');
if (trim($currentPassword) === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Current password is required.']);
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
    exit;
}

$userId = (string)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT password_hash FROM useraccountstbl WHERE user_id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    exit;
}
$stmt->bind_param('s', $userId);
$stmt->execute();
$stmt->bind_result($hash);
if (!$stmt->fetch()) {
    $stmt->close();
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Account not found.']);
    exit;
}
$stmt->close();

if (!password_verify($currentPassword, (string)$hash)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
    exit;
}

echo json_encode(['success' => true]);

