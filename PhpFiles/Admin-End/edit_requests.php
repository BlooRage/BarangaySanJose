<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'Employee'], true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/uniqueIDGenerate.php';

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

function normalizeRequestType(string $value): string {
    $value = strtolower(trim($value));
    if (!in_array($value, ['profile', 'address', 'emergency'], true)) {
        return '';
    }
    return $value;
}

function fetchLatestAddress(mysqli $conn, string $residentId): ?array {
    $stmt = $conn->prepare("
        SELECT address_id, unit_number, street_number, street_name, phase_number, subdivision, area_number,
               house_type, house_ownership, residency_duration, status_id_residency
        FROM residentaddresstbl
        WHERE resident_id = ?
        ORDER BY address_id DESC
        LIMIT 1
    ");
    $stmt->bind_param("s", $residentId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['fetch'])) {
    $statusPendingId = getStatusId($conn, 'PendingRequest', 'EditRequest');
    $statusApprovedId = getStatusId($conn, 'ApprovedRequest', 'EditRequest');
    $statusDeniedId = getStatusId($conn, 'DeniedRequest', 'EditRequest');

    $sql = "
        SELECT
            r.request_id,
            r.resident_id,
            CONCAT(ri.firstname, ' ', ri.lastname) AS resident_name,
            r.request_type,
            r.status_id,
            s.status_name,
            r.created_at,
            r.reviewed_at
        FROM resident_edit_requesttbl r
        INNER JOIN residentinformationtbl ri ON ri.resident_id = r.resident_id
        LEFT JOIN statuslookuptbl s ON s.status_id = r.status_id
        ORDER BY r.created_at DESC, r.request_id DESC
    ";

    $result = $conn->query($sql);
    if (!$result) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load edit requests.']);
        exit;
    }

    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    $result->free();

    $pendingCount = 0;
    if ($statusPendingId !== null) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM resident_edit_requesttbl WHERE status_id = ?");
        $stmt->bind_param("i", $statusPendingId);
        $stmt->execute();
        $stmt->bind_result($pendingCount);
        $stmt->fetch();
        $stmt->close();
    }

    echo json_encode([
        'success' => true,
        'requests' => $requests,
        'pending_count' => (int)$pendingCount,
        'status_ids' => [
            'pending' => $statusPendingId,
            'approved' => $statusApprovedId,
            'denied' => $statusDeniedId,
        ],
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['view'])) {
    $requestId = (int)($_GET['view'] ?? 0);
    if ($requestId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request id.']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT
            r.request_id,
            r.resident_id,
            r.user_id,
            r.request_type,
            r.status_id,
            s.status_name,
            r.created_at,
            r.reviewed_at,
            r.reviewed_by,
            r.requested_changes,
            ri.firstname,
            ri.middlename,
            ri.lastname,
            ri.suffix,
            ri.civil_status,
            ri.religion,
            ri.occupation,
            ri.occupation_detail,
            ri.sector_membership,
            ri.voter_status
        FROM resident_edit_requesttbl r
        INNER JOIN residentinformationtbl ri ON ri.resident_id = r.resident_id
        LEFT JOIN statuslookuptbl s ON s.status_id = r.status_id
        WHERE r.request_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Request not found.']);
        exit;
    }

    $requestedChanges = json_decode($row['requested_changes'] ?? '', true);
    if (!is_array($requestedChanges)) {
        $requestedChanges = [];
    }

    $address = fetchLatestAddress($conn, $row['resident_id']);

    $emergency = null;
    if (!empty($row['user_id'])) {
        $stmt = $conn->prepare("
            SELECT last_name, first_name, middle_name, suffix, phone_number, relationship, address
            FROM emergencycontacttbl
            WHERE user_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $row['user_id']);
        $stmt->execute();
        $emergency = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    $reviewedByName = null;
    if (!empty($row['reviewed_by'])) {
        $stmt = $conn->prepare("
            SELECT firstname, middlename, lastname, suffix, role_access, department
            FROM officialinformationtbl
            WHERE user_id = ?
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param("s", $row['reviewed_by']);
            $stmt->execute();
            $reviewer = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($reviewer) {
                $reviewedByName = trim(
                    $reviewer['firstname'] . ' ' .
                    ($reviewer['middlename'] ? $reviewer['middlename'][0] . '. ' : '') .
                    $reviewer['lastname'] .
                    ($reviewer['suffix'] ? ' ' . $reviewer['suffix'] : '')
                );
            }
        }
        if (!$reviewedByName) {
            $reviewedByName = $row['reviewed_by'];
        }
    }

    echo json_encode([
        'success' => true,
        'request' => [
            'request_id' => $row['request_id'],
            'resident_id' => $row['resident_id'],
            'resident_name' => trim($row['firstname'] . ' ' . $row['lastname']),
            'request_type' => $row['request_type'],
            'status_name' => $row['status_name'],
            'created_at' => $row['created_at'],
            'reviewed_at' => $row['reviewed_at'],
            'reviewed_by_name' => $reviewedByName,
        ],
        'current' => [
            'profile' => [
                'firstname' => $row['firstname'],
                'middlename' => $row['middlename'],
                'lastname' => $row['lastname'],
                'suffix' => $row['suffix'],
                'civil_status' => $row['civil_status'],
                'religion' => $row['religion'],
                'occupation' => (int)$row['occupation'] === 1 ? 'Employed' : 'Unemployed',
                'occupation_detail' => $row['occupation_detail'],
                'sector_membership' => $row['sector_membership'],
                'voter_status' => (int)$row['voter_status'] === 1 ? 'Registered' : 'Not Registered',
            ],
            'address' => $address,
            'emergency' => $emergency,
        ],
        'requested_changes' => $requestedChanges,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $action = strtolower(trim((string)($payload['action'] ?? '')));
    $requestId = (int)($payload['request_id'] ?? 0);
    $adminNotes = trim((string)($payload['admin_notes'] ?? ''));

    if (!in_array($action, ['approve', 'deny'], true) || $requestId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    $statusPendingId = getStatusId($conn, 'PendingRequest', 'EditRequest');
    $statusApprovedId = getStatusId($conn, 'ApprovedRequest', 'EditRequest');
    $statusDeniedId = getStatusId($conn, 'DeniedRequest', 'EditRequest');
    if ($statusPendingId === null || $statusApprovedId === null || $statusDeniedId === null) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Edit request statuses missing.']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT request_id, resident_id, user_id, request_type, status_id, requested_changes
        FROM resident_edit_requesttbl
        WHERE request_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Request not found.']);
        exit;
    }

    if ((int)$row['status_id'] !== $statusPendingId) {
        echo json_encode(['success' => false, 'message' => 'Request already reviewed.']);
        exit;
    }

    $requestType = normalizeRequestType($row['request_type']);
    if ($requestType === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request type.']);
        exit;
    }

    $changes = json_decode($row['requested_changes'] ?? '', true);
    if (!is_array($changes)) {
        $changes = [];
    }

    $newStatusId = $action === 'approve' ? $statusApprovedId : $statusDeniedId;

    $conn->begin_transaction();
    try {
        if ($action === 'approve') {
            if ($requestType === 'profile') {
                $allowed = [
                    'lastname' => 'lastname',
                    'firstname' => 'firstname',
                    'middlename' => 'middlename',
                    'suffix' => 'suffix',
                    'civil_status' => 'civil_status',
                    'religion' => 'religion',
                    'sector_membership' => 'sector_membership',
                ];
                $set = [];
                $params = [];
                $types = '';
                foreach ($allowed as $key => $col) {
                    if (array_key_exists($key, $changes)) {
                        $set[] = "{$col} = ?";
                        $params[] = $changes[$key];
                        $types .= 's';
                    }
                }

                if (array_key_exists('voter_status', $changes)) {
                    $set[] = "voter_status = ?";
                    $params[] = (int)$changes['voter_status'];
                    $types .= 'i';
                }

                if (array_key_exists('occupation', $changes)) {
                    $set[] = "occupation = ?";
                    $params[] = (int)$changes['occupation'];
                    $types .= 'i';
                }
                if (array_key_exists('occupation_detail', $changes)) {
                    $set[] = "occupation_detail = ?";
                    $params[] = $changes['occupation_detail'];
                    $types .= 's';
                }

                if ($set) {
                    $sql = "UPDATE residentinformationtbl SET " . implode(', ', $set) . " WHERE resident_id = ?";
                    $params[] = $row['resident_id'];
                    $types .= 's';
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    if (!$stmt->execute()) {
                        throw new Exception('Failed to update resident profile.');
                    }
                    $stmt->close();
                }
            } elseif ($requestType === 'address') {
                $latest = fetchLatestAddress($conn, $row['resident_id']);
                if (!$latest) {
                    throw new Exception('No address record found.');
                }

                $addressStatusId = getStatusId($conn, 'PendingVerification', 'AddressResidency');
                if ($addressStatusId === null) {
                    $addressStatusId = (int)$latest['status_id_residency'];
                }
                $newAddress = [
                    'unit_number' => (string)($changes['unit_number'] ?? $latest['unit_number']),
                    'street_number' => (string)($changes['street_number'] ?? $latest['street_number']),
                    'street_name' => (string)($changes['street_name'] ?? $latest['street_name']),
                    'phase_number' => (string)($changes['phase_number'] ?? $latest['phase_number']),
                    'subdivision' => (string)($changes['subdivision'] ?? $latest['subdivision']),
                    'area_number' => (string)($changes['area_number'] ?? $latest['area_number']),
                ];
                $newAddressId = GenerateAddressID($conn, $newAddress['area_number']);

                $stmt = $conn->prepare("
                    INSERT INTO residentaddresstbl
                        (address_id, resident_id, unit_number, street_number, street_name, phase_number, subdivision, area_number,
                         house_type, house_ownership, residency_duration, status_id_residency)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "sssssssssssi",
                    $newAddressId,
                    $row['resident_id'],
                    $newAddress['unit_number'],
                    $newAddress['street_number'],
                    $newAddress['street_name'],
                    $newAddress['phase_number'],
                    $newAddress['subdivision'],
                    $newAddress['area_number'],
                    $latest['house_type'],
                    $latest['house_ownership'],
                    $latest['residency_duration'],
                    $addressStatusId
                );
                if (!$stmt->execute()) {
                    throw new Exception('Failed to insert address.');
                }
                $stmt->close();

                $memberActiveStatusId = getStatusId($conn, 'Active', 'HouseholdMember');
                $memberRemovedStatusId = getStatusId($conn, 'Removed', 'HouseholdMember');
                if ($memberActiveStatusId && $memberRemovedStatusId) {
                    $householdId = null;
                    $currentRole = null;
                    $stmt = $conn->prepare("
                        SELECT household_id, role
                        FROM householdmemberresidenttbl
                        WHERE resident_id = ? AND status_id = ?
                        LIMIT 1
                    ");
                    $stmt->bind_param("si", $row['resident_id'], $memberActiveStatusId);
                    $stmt->execute();
                    $stmt->bind_result($householdId, $currentRole);
                    $stmt->fetch();
                    $stmt->close();

                    if ($householdId) {
                        if ($currentRole === 'Head') {
                            $newHeadId = trim((string)($changes['new_head_resident_id'] ?? ''));
                            if ($newHeadId === '') {
                                throw new Exception('Missing new head for household.');
                            }
                            $stmt = $conn->prepare("
                                SELECT 1
                                FROM householdmemberresidenttbl
                                WHERE household_id = ? AND resident_id = ? AND status_id = ? AND role <> 'Head'
                                LIMIT 1
                            ");
                            $stmt->bind_param("isi", $householdId, $newHeadId, $memberActiveStatusId);
                            $stmt->execute();
                            $eligible = $stmt->get_result()->num_rows > 0;
                            $stmt->close();
                            if (!$eligible) {
                                throw new Exception('Selected member is not eligible to become head.');
                            }

                            $upd = $conn->prepare("UPDATE householdtbl SET head_resident_id = ? WHERE household_id = ?");
                            $upd->bind_param("si", $newHeadId, $householdId);
                            if (!$upd->execute()) {
                                throw new Exception('Failed to reassign household head.');
                            }
                            $upd->close();

                            $upd = $conn->prepare("
                                UPDATE householdmemberresidenttbl
                                SET role = CASE
                                    WHEN resident_id = ? THEN 'Head'
                                    WHEN resident_id = ? THEN 'Member'
                                    ELSE role
                                END
                                WHERE household_id = ? AND status_id = ?
                            ");
                            $upd->bind_param("ssii", $newHeadId, $row['resident_id'], $householdId, $memberActiveStatusId);
                            if (!$upd->execute()) {
                                throw new Exception('Failed to update household roles.');
                            }
                            $upd->close();
                        }

                        $upd = $conn->prepare("
                            UPDATE householdmemberresidenttbl
                            SET status_id = ?
                            WHERE household_id = ? AND resident_id = ? AND status_id = ?
                        ");
                        $upd->bind_param("iisi", $memberRemovedStatusId, $householdId, $row['resident_id'], $memberActiveStatusId);
                        if (!$upd->execute()) {
                            throw new Exception('Failed to remove from household.');
                        }
                        $upd->close();
                    }
                }
            } elseif ($requestType === 'emergency') {
                $requiredKeys = ['last_name', 'first_name', 'phone_number', 'relationship', 'address'];
                foreach ($requiredKeys as $key) {
                    if (!array_key_exists($key, $changes) || trim((string)$changes[$key]) === '') {
                        throw new Exception('Missing emergency contact details.');
                    }
                }

                $stmt = $conn->prepare("
                    INSERT INTO emergencycontacttbl
                        (user_id, last_name, first_name, middle_name, suffix, phone_number, relationship, address)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        last_name = VALUES(last_name),
                        first_name = VALUES(first_name),
                        middle_name = VALUES(middle_name),
                        suffix = VALUES(suffix),
                        phone_number = VALUES(phone_number),
                        relationship = VALUES(relationship),
                        address = VALUES(address)
                ");
                $middleName = $changes['middle_name'] ?? null;
                $suffix = $changes['suffix'] ?? null;
                $stmt->bind_param(
                    "ssssssss",
                    $row['user_id'],
                    $changes['last_name'],
                    $changes['first_name'],
                    $middleName,
                    $suffix,
                    $changes['phone_number'],
                    $changes['relationship'],
                    $changes['address']
                );
                if (!$stmt->execute()) {
                    throw new Exception('Failed to update emergency contact.');
                }
                $stmt->close();
            }
        }

        $stmt = $conn->prepare("
            UPDATE resident_edit_requesttbl
            SET status_id = ?, admin_notes = ?, reviewed_at = NOW(), reviewed_by = ?
            WHERE request_id = ?
        ");
        $adminId = $_SESSION['user_id'];
        $stmt->bind_param("issi", $newStatusId, $adminNotes, $adminId, $requestId);
        if (!$stmt->execute()) {
            throw new Exception('Failed to update request status.');
        }
        $stmt->close();

        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
