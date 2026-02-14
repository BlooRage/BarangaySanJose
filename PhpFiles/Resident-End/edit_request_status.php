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

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
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

function getResidentId(mysqli $conn, string $userId): ?string {
    $stmt = $conn->prepare("
        SELECT resident_id
        FROM residentinformationtbl
        WHERE user_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $stmt->bind_result($residentId);
    $stmt->fetch();
    $stmt->close();
    return $residentId ?: null;
}

$residentId = getResidentId($conn, $_SESSION['user_id']);
if (!$residentId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Resident profile not found.']);
    exit;
}

$pendingStatusId = getStatusId($conn, 'PendingRequest', 'EditRequest');
if ($pendingStatusId === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Edit request status missing.']);
    exit;
}

$deniedStatusId = getStatusId($conn, 'DeniedRequest', 'EditRequest');

$pendingTypes = [];
$stmt = $conn->prepare("
    SELECT request_type
    FROM resident_edit_requesttbl
    WHERE resident_id = ? AND status_id = ?
");
$stmt->bind_param("si", $residentId, $pendingStatusId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $pendingTypes[] = $row['request_type'];
}
$stmt->close();

$deniedByType = [];
if ($deniedStatusId !== null) {
    $stmt = $conn->prepare("
        SELECT request_type, admin_notes, reviewed_at
        FROM resident_edit_requesttbl
        WHERE resident_id = ? AND status_id = ?
        ORDER BY reviewed_at DESC, request_id DESC
    ");
    $stmt->bind_param("si", $residentId, $deniedStatusId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        if (!isset($deniedByType[$row['request_type']])) {
            $deniedByType[$row['request_type']] = [
                'remarks' => $row['admin_notes'] ?? '',
                'reviewed_at' => $row['reviewed_at'] ?? null,
            ];
        }
    }
    $stmt->close();
}

echo json_encode([
    'success' => true,
    'pending' => [
        'address' => in_array('address', $pendingTypes, true),
        'emergency' => in_array('emergency', $pendingTypes, true),
        'profile' => in_array('profile', $pendingTypes, true),
    ],
    'denied' => [
        'address' => $deniedByType['address'] ?? null,
        'emergency' => $deniedByType['emergency'] ?? null,
        'profile' => $deniedByType['profile'] ?? null,
    ],
]);
