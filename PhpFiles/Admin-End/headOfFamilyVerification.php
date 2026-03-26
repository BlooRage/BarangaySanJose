<?php
session_start();
require_once "../General/connection.php";
require_once "../General/security.php";
require_once "../General/uniqueIDGenerate.php";

requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin', 'Employee']);

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['fetch'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$createSql = "
    CREATE TABLE IF NOT EXISTS householdheadverificationtbl (
        verification_id INT NOT NULL PRIMARY KEY,
        group_key VARCHAR(255) NOT NULL,
        address_id VARCHAR(100) DEFAULT NULL,
        address_display VARCHAR(255) DEFAULT NULL,
        area_number VARCHAR(64) DEFAULT NULL,
        selected_resident_id VARCHAR(100) DEFAULT NULL,
        decision_status ENUM('Pending', 'Approved', 'Declined') NOT NULL DEFAULT 'Pending',
        remarks TEXT DEFAULT NULL,
        decided_by_user_id VARCHAR(100) DEFAULT NULL,
        decided_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_group_key (group_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";
$conn->query($createSql);
idg_ensure_numeric_generated_key($conn, 'householdheadverificationtbl', 'verification_id', 'INT NOT NULL');

$q = trim((string)($_GET['q'] ?? ''));
$limit = (int)($_GET['limit'] ?? 300);
if ($limit <= 0) $limit = 300;
if ($limit > 1000) $limit = 1000;

if ($q !== '') {
    $sql = "
        SELECT
            verification_id,
            group_key,
            address_id,
            address_display,
            area_number,
            selected_resident_id,
            decision_status,
            remarks,
            decided_by_user_id,
            decided_at,
            updated_at
        FROM householdheadverificationtbl
        WHERE
            group_key LIKE CONCAT('%', ?, '%')
            OR address_id LIKE CONCAT('%', ?, '%')
            OR address_display LIKE CONCAT('%', ?, '%')
            OR area_number LIKE CONCAT('%', ?, '%')
            OR selected_resident_id LIKE CONCAT('%', ?, '%')
            OR decision_status LIKE CONCAT('%', ?, '%')
            OR remarks LIKE CONCAT('%', ?, '%')
            OR decided_by_user_id LIKE CONCAT('%', ?, '%')
        ORDER BY verification_id DESC
        LIMIT ?
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to prepare query.']);
        exit;
    }
    $stmt->bind_param("ssssssssi", $q, $q, $q, $q, $q, $q, $q, $q, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $sql = "
        SELECT
            verification_id,
            group_key,
            address_id,
            address_display,
            area_number,
            selected_resident_id,
            decision_status,
            remarks,
            decided_by_user_id,
            decided_at,
            updated_at
        FROM householdheadverificationtbl
        ORDER BY verification_id DESC
        LIMIT ?
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to prepare query.']);
        exit;
    }
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

echo json_encode([
    'success' => true,
    'data' => $rows,
]);
