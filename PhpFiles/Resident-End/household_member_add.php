<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

$lastName = trim((string)($payload['last_name'] ?? ''));
$firstName = trim((string)($payload['first_name'] ?? ''));
$middleName = trim((string)($payload['middle_name'] ?? ''));
$suffix = trim((string)($payload['suffix'] ?? ''));
$birthdate = trim((string)($payload['birthdate'] ?? ''));

if ($lastName === '' || $firstName === '' || $birthdate === '') {
    echo json_encode(['success' => false, 'message' => 'Last name, first name, and birthdate are required.']);
    exit;
}

$dob = DateTime::createFromFormat('Y-m-d', $birthdate);
if (!$dob || $dob->format('Y-m-d') !== $birthdate) {
    echo json_encode(['success' => false, 'message' => 'Invalid birthdate format.']);
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
    exit;
}

// Resolve resident id + ensure head of family
$residentId = '';
$isHead = false;
$stmt = $conn->prepare("
    SELECT resident_id, head_of_family
    FROM residentinformationtbl
    WHERE user_id = ?
    LIMIT 1
");
$stmt->bind_param("s", $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($residentId, $headFlag);
if ($stmt->fetch()) {
    $isHead = ((int)$headFlag) === 1;
}
$stmt->close();

if ($residentId === '' || !$isHead) {
    echo json_encode(['success' => false, 'message' => 'Only the head can add members.']);
    exit;
}

$middleName = $middleName !== '' ? $middleName : null;
$suffix = $suffix !== '' ? $suffix : null;

// Prevent duplicates under the same head
$dup = $conn->prepare("
    SELECT 1
    FROM householdmemberinfotbl
    WHERE fam_head_id = ?
      AND last_name = ?
      AND first_name = ?
      AND (middle_name <=> ?)
      AND (suffix <=> ?)
      AND (birthdate <=> ?)
    LIMIT 1
");
$dup->bind_param("ssssss", $residentId, $lastName, $firstName, $middleName, $suffix, $birthdate);
$dup->execute();
$dup->store_result();
if ($dup->num_rows > 0) {
    $dup->close();
    echo json_encode(['success' => false, 'message' => 'Member already exists in your household.']);
    exit;
}
$dup->close();

$ins = $conn->prepare("
    INSERT INTO householdmemberinfotbl
        (fam_head_id, last_name, first_name, middle_name, suffix, birthdate)
    VALUES (?, ?, ?, ?, ?, ?)
");
$ins->bind_param("ssssss", $residentId, $lastName, $firstName, $middleName, $suffix, $birthdate);
if (!$ins->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to add member.']);
    exit;
}
$ins->close();

echo json_encode(['success' => true]);
?>
