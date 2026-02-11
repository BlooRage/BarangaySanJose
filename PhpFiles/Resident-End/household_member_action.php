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
require_once __DIR__ . '/../General/mailConfigurations.php';
require_once __DIR__ . '/../EmailHandlers/emailSender.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$action = strtolower(trim((string)($payload['action'] ?? '')));
$targetResidentId = trim((string)($payload['resident_id'] ?? ''));

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

// Resolve resident id for current user
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
    echo json_encode(['success' => false, 'message' => 'Resident profile not found.']);
    exit;
}

$memberActiveStatusId = getStatusId($conn, 'Active', 'HouseholdMember');
$memberRemovedStatusId = getStatusId($conn, 'Removed', 'HouseholdMember');
if ($memberActiveStatusId === null || $memberRemovedStatusId === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Household member statuses missing.']);
    exit;
}

// Find household and role for current user
$householdId = null;
$currentRole = null;
$stmt = $conn->prepare("
    SELECT household_id, role
    FROM householdmemberresidenttbl
    WHERE resident_id = ? AND status_id = ?
    LIMIT 1
");
$stmt->bind_param("si", $residentId, $memberActiveStatusId);
$stmt->execute();
$stmt->bind_result($householdId, $currentRole);
$stmt->fetch();
$stmt->close();

if (!$householdId) {
    echo json_encode(['success' => false, 'message' => 'Household not found.']);
    exit;
}

if ($action === 'leave') {
    if ($currentRole === 'Head') {
        echo json_encode(['success' => false, 'message' => 'Head cannot leave the household.']);
        exit;
    }

    $upd = $conn->prepare("
        UPDATE householdmemberresidenttbl
        SET status_id = ?
        WHERE household_id = ? AND resident_id = ? AND status_id = ?
    ");
    $upd->bind_param("iisi", $memberRemovedStatusId, $householdId, $residentId, $memberActiveStatusId);
    if (!$upd->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to leave household.']);
        exit;
    }
    $upd->close();

    // Notify head via email
    $stmt = $conn->prepare("
        SELECT h.head_resident_id, r.firstname, r.lastname
        FROM householdtbl h
        INNER JOIN residentinformationtbl r ON r.resident_id = ?
        WHERE h.household_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("si", $residentId, $householdId);
    $stmt->execute();
    $stmt->bind_result($headResidentId, $memberFirst, $memberLast);
    $stmt->fetch();
    $stmt->close();

    if (!empty($headResidentId)) {
        $stmt = $conn->prepare("
            SELECT u.email
            FROM residentinformationtbl r
            INNER JOIN useraccountstbl u ON u.user_id = r.user_id
            WHERE r.resident_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $headResidentId);
        $stmt->execute();
        $stmt->bind_result($headEmail);
        $stmt->fetch();
        $stmt->close();

        if (!empty($headEmail)) {
            $smtpConfig = require __DIR__ . "/../General/mailConfigurations.php";
            $emailSender = new EmailSender($smtpConfig);
            $memberName = trim($memberFirst . ' ' . $memberLast);
            $emailSender->send([
                'to' => $headEmail,
                'subject' => 'Household Member Left',
                'template' => 'emails/transactionNotification.php',
                'data' => [
                    'transactionId' => null,
                    'status' => null,
                    'amount' => null,
                    'action' => 'Member Left Household',
                    'details' => [
                        'Member' => $memberName,
                        'Household ID' => $householdId,
                        'Date' => date('M j, Y g:i A'),
                    ],
                    'ctaText' => null,
                    'ctaUrl' => null,
                ],
            ]);
        }
    }

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'remove') {
    if ($currentRole !== 'Head') {
        echo json_encode(['success' => false, 'message' => 'Only the head can remove members.']);
        exit;
    }
    if ($targetResidentId === '') {
        echo json_encode(['success' => false, 'message' => 'Missing resident to remove.']);
        exit;
    }
    if ($targetResidentId === $residentId) {
        echo json_encode(['success' => false, 'message' => 'Head cannot remove self.']);
        exit;
    }

    $upd = $conn->prepare("
        UPDATE householdmemberresidenttbl
        SET status_id = ?
        WHERE household_id = ? AND resident_id = ? AND status_id = ?
    ");
    $upd->bind_param("iisi", $memberRemovedStatusId, $householdId, $targetResidentId, $memberActiveStatusId);
    if (!$upd->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to remove member.']);
        exit;
    }
    $upd->close();

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
?>
