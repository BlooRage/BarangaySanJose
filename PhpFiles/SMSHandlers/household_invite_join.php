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

require_once __DIR__ . "/../General/connection.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$codeInput = strtoupper(trim((string)($payload['code'] ?? '')));
if ($codeInput === '') {
    echo json_encode(['success' => false, 'message' => 'Invite code is required.']);
    exit;
}

function getStatusId(mysqli $conn, string $name, string $type): ?int {
    $stmt = $conn->prepare("
        SELECT status_id
        FROM statuslookuptbl
        WHERE status_name = ? AND status_type = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("ss", $name, $type);
    $stmt->execute();
    $stmt->bind_result($statusId);
    $statusId = $stmt->fetch() ? (int)$statusId : null;
    $stmt->close();
    return $statusId;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
    exit;
}

// Get resident id
$residentId = '';
$stmt = $conn->prepare("
    SELECT resident_id
    FROM residentinformationtbl
    WHERE user_id = ?
    LIMIT 1
");
$stmt->bind_param("s", $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($residentId);
$stmt->fetch();
$stmt->close();

if ($residentId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Resident profile not found.']);
    exit;
}

$inviteActiveStatusId = getStatusId($conn, 'Active', 'HouseholdInvite');
$inviteExpiredStatusId = getStatusId($conn, 'Expired', 'HouseholdInvite');
$memberActiveStatusId = getStatusId($conn, 'Active', 'HouseholdMember');

if ($inviteActiveStatusId === null || $inviteExpiredStatusId === null || $memberActiveStatusId === null) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Required household statuses are missing in statuslookuptbl.',
    ]);
    exit;
}

$codeHash = hash('sha256', $codeInput);

// Find active invite
$stmt = $conn->prepare("
    SELECT invite_id, household_id, uses_count, max_uses, created_by_resident_id
    FROM householdinvitetbl
    WHERE code_hash = ?
      AND status_id = ?
      AND expires_at > NOW()
      AND uses_count < max_uses
    LIMIT 1
");
$stmt->bind_param("si", $codeHash, $inviteActiveStatusId);
$stmt->execute();
$stmt->bind_result($inviteId, $householdId, $usesCount, $maxUses, $createdByResidentId);
$found = $stmt->fetch();
$stmt->close();

if (!$found) {
    echo json_encode(['success' => false, 'message' => 'Invite code is invalid or expired.']);
    exit;
}

// Already a member?
$exists = $conn->prepare("
    SELECT 1
    FROM householdmemberresidenttbl
    WHERE household_id = ? AND resident_id = ?
    LIMIT 1
");
$exists->bind_param("is", $householdId, $residentId);
$exists->execute();
$exists->store_result();
if ($exists->num_rows > 0) {
    $exists->close();
    echo json_encode(['success' => true, 'message' => 'Already a household member.']);
    exit;
}
$exists->close();

// Insert membership
$ins = $conn->prepare("
    INSERT INTO householdmemberresidenttbl
        (household_id, resident_id, role, status_id, invited_by_resident_id, joined_at)
    VALUES (?, ?, 'Member', ?, ?, NOW())
");
$ins->bind_param("isis", $householdId, $residentId, $memberActiveStatusId, $createdByResidentId);
if (!$ins->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to join household.']);
    exit;
}
$ins->close();

// Increment use and expire if maxed out
$newUses = $usesCount + 1;
$newStatusId = $newUses >= $maxUses ? $inviteExpiredStatusId : $inviteActiveStatusId;
$upd = $conn->prepare("
    UPDATE householdinvitetbl
    SET uses_count = ?, status_id = ?
    WHERE invite_id = ?
");
$upd->bind_param("iii", $newUses, $newStatusId, $inviteId);
$upd->execute();
$upd->close();

echo json_encode(['success' => true]);
?>
