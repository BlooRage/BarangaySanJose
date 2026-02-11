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

$memberId = (int)($payload['household_member_id'] ?? 0);
if ($memberId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing household member.']);
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
    echo json_encode(['success' => false, 'message' => 'Only the head can remove members.']);
    exit;
}

// Ensure member belongs to this head
$stmt = $conn->prepare("
    SELECT 1
    FROM householdmemberinfotbl
    WHERE household_member_id = ? AND fam_head_id = ?
    LIMIT 1
");
$stmt->bind_param("is", $memberId, $residentId);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Member not found.']);
    exit;
}
$stmt->close();

$del = $conn->prepare("
    DELETE FROM householdmemberinfotbl
    WHERE household_member_id = ? AND fam_head_id = ?
");
$del->bind_param("is", $memberId, $residentId);
if (!$del->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to remove member.']);
    exit;
}
$del->close();

echo json_encode(['success' => true]);
?>
