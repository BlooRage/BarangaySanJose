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
require_once __DIR__ . "/../General/sendSMS.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$phoneNumbersRaw = trim((string)($payload['phone_numbers'] ?? ''));
$expiresDays = (int)($payload['expires_days'] ?? 7);
$expiresDays = $expiresDays > 0 ? $expiresDays : 7;

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

function isVerifiedResidentStatus(?string $statusName): bool {
    $statusKey = strtolower(str_replace([' ', '_', '-'], '', (string)$statusName));
    return in_array($statusKey, ['verifiedresident', 'verified'], true);
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
    exit;
}

// Resolve resident + head-of-family check
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
$stmt->bind_result($residentId, $headOfFamily);
if ($stmt->fetch()) {
    $isHead = ((int)$headOfFamily) === 1;
}
$stmt->close();

if ($residentId === '' || !$isHead) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only the head of the family can send invites.']);
    exit;
}

// Block unverified accounts from sending SMS invite codes.
$statusName = null;
$stmtStatus = $conn->prepare("
    SELECT s.status_name
    FROM residentinformationtbl r
    LEFT JOIN statuslookuptbl s ON r.status_id_resident = s.status_id
    WHERE r.resident_id = ?
    LIMIT 1
");
if ($stmtStatus) {
    $stmtStatus->bind_param("s", $residentId);
    $stmtStatus->execute();
    $stmtStatus->bind_result($statusName);
    $stmtStatus->fetch();
    $stmtStatus->close();
}

if (!isVerifiedResidentStatus($statusName)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Account must be verified before sending household invite codes.',
    ]);
    exit;
}

$householdStatusId = getStatusId($conn, 'Active', 'Household');
$inviteActiveStatusId = getStatusId($conn, 'Active', 'HouseholdInvite');
$memberActiveStatusId = getStatusId($conn, 'Active', 'HouseholdMember');

if ($householdStatusId === null || $inviteActiveStatusId === null || $memberActiveStatusId === null) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Required household statuses are missing in statuslookuptbl.',
    ]);
    exit;
}

// Find or create household
$householdId = null;
$stmt = $conn->prepare("
    SELECT household_id
    FROM householdtbl
    WHERE head_resident_id = ?
    ORDER BY household_id DESC
    LIMIT 1
");
$stmt->bind_param("s", $residentId);
$stmt->execute();
$stmt->bind_result($householdId);
$stmt->fetch();
$stmt->close();

if (!$householdId) {
    $ins = $conn->prepare("
        INSERT INTO householdtbl (head_resident_id, status_id)
        VALUES (?, ?)
    ");
    $ins->bind_param("si", $residentId, $householdStatusId);
    if (!$ins->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create household.']);
        exit;
    }
    $householdId = $ins->insert_id;
    $ins->close();
}

// Ensure head is registered as household member
$exists = $conn->prepare("
    SELECT 1
    FROM householdmemberresidenttbl
    WHERE household_id = ? AND resident_id = ?
    LIMIT 1
");
$exists->bind_param("is", $householdId, $residentId);
$exists->execute();
$exists->store_result();
if ($exists->num_rows === 0) {
    $insHead = $conn->prepare("
        INSERT INTO householdmemberresidenttbl
            (household_id, resident_id, role, status_id, invited_by_resident_id, joined_at)
        VALUES (?, ?, 'Head', ?, ?, NOW())
    ");
    $insHead->bind_param("isis", $householdId, $residentId, $memberActiveStatusId, $residentId);
    $insHead->execute();
    $insHead->close();
}
$exists->close();

// Generate invite code
$code = strtoupper(bin2hex(random_bytes(4)));
$codeHash = hash('sha256', $code);
$expiresAt = date('Y-m-d H:i:s', strtotime('+' . $expiresDays . ' days'));

// Parse phone numbers (store SMS numbers as 09XXXXXXXXX, lookup uses 10-digit)
$rawParts = preg_split('/[\s,]+/', $phoneNumbersRaw, -1, PREG_SPLIT_NO_EMPTY);
$parsedNumbers = [];
$invalidNumbers = [];
foreach ($rawParts as $part) {
    $digits = preg_replace('/[^0-9]/', '', $part);
    if (strlen($digits) === 10 && $digits[0] === '9') {
        $digits = '0' . $digits;
    }
    if (strlen($digits) === 11 && strpos($digits, '09') === 0) {
        $parsedNumbers[$digits] = true;
    } else {
        $invalidNumbers[] = $part;
    }
}
$parsedNumbers = array_keys($parsedNumbers);

// Filter to existing, verified resident accounts
$validNumbers = [];
$nonExistingNumbers = [];
$unverifiedNumbers = [];
if (!empty($parsedNumbers)) {
    $lookupNumbers = array_values(array_unique(array_map(static function ($num) {
        return substr($num, 1);
    }, $parsedNumbers)));

    $placeholders = implode(',', array_fill(0, count($lookupNumbers), '?'));
    $types = str_repeat('s', count($lookupNumbers));
    $sql = "
        SELECT phone_number, phoneNum_verify
        FROM useraccountstbl
        WHERE role_access = 'Resident'
          AND phone_number IN ({$placeholders})
    ";
    $stmtLookup = $conn->prepare($sql);
    if ($stmtLookup) {
        $stmtLookup->bind_param($types, ...$lookupNumbers);
        $stmtLookup->execute();
        $res = $stmtLookup->get_result();
        $accountMap = [];
        while ($row = $res->fetch_assoc()) {
            $accountMap[$row['phone_number']] = (int)$row['phoneNum_verify'];
        }
        $stmtLookup->close();

        foreach ($parsedNumbers as $smsNumber) {
            $key = substr($smsNumber, 1);
            if (!isset($accountMap[$key])) {
                $nonExistingNumbers[] = $smsNumber;
                continue;
            }
            if ($accountMap[$key] !== 1) {
                $unverifiedNumbers[] = $smsNumber;
                continue;
            }
            $validNumbers[] = $smsNumber;
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to validate phone numbers.']);
        exit;
    }
}

$invalidNumbers = array_values(array_unique(array_merge($invalidNumbers, $nonExistingNumbers, $unverifiedNumbers)));

if (empty($validNumbers)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Only existing, verified resident accounts can receive household invite codes.',
        'invalid_numbers' => $invalidNumbers,
        'non_existing_numbers' => $nonExistingNumbers,
        'unverified_numbers' => $unverifiedNumbers,
    ]);
    exit;
}

$maxUses = (int)($payload['max_uses'] ?? 0);
if ($maxUses <= 0) {
    $maxUses = count($validNumbers) > 0 ? count($validNumbers) : 1;
}

// Create invite record
$inviteStmt = $conn->prepare("
    INSERT INTO householdinvitetbl
        (household_id, code_hash, expires_at, max_uses, uses_count, created_by_resident_id, status_id)
    VALUES (?, ?, ?, ?, 0, ?, ?)
");
$inviteStmt->bind_param("issisi", $householdId, $codeHash, $expiresAt, $maxUses, $residentId, $inviteActiveStatusId);
if (!$inviteStmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create invite code.']);
    exit;
}
$inviteStmt->close();

$sentCount = 0;
$failedNumbers = [];
if (!empty($validNumbers)) {
    $expiresLabel = date('M j, Y', strtotime($expiresAt));
    $message = "Your household invite code is {$code}. Expires on {$expiresLabel}.";
    foreach ($validNumbers as $number) {
        $sent = sendSMS($number, $message);
        if ($sent) {
            $sentCount++;
        } else {
            $failedNumbers[] = $number;
        }
    }
}

echo json_encode([
    'success' => true,
    'code' => $code,
    'sent_count' => $sentCount,
    'invalid_numbers' => $invalidNumbers,
    'non_existing_numbers' => $nonExistingNumbers,
    'unverified_numbers' => $unverifiedNumbers,
    'failed_numbers' => $failedNumbers,
]);
?>
