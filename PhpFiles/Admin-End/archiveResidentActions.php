<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once "../General/connection.php";
require_once "../General/security.php";

requireRoleSession(['Admin', 'Employee']);

$raw = file_get_contents("php://input");
$body = json_decode($raw, true);

$action = $body['action'] ?? '';
$residentId = trim((string)($body['resident_id'] ?? ''));

if (!preg_match('/^\\d{10}$/', $residentId) || ($action !== 'restore' && $action !== 'delete')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$conn->begin_transaction();

try {
    if ($action === 'restore') {
        // Get VerifiedResident status id
        $statusId = null;
        $stmt = $conn->prepare("
            SELECT status_id
            FROM statuslookuptbl
            WHERE status_name = 'VerifiedResident'
              AND status_type = 'Resident'
            LIMIT 1
        ");
        $stmt->execute();
        $stmt->bind_result($statusId);
        $stmt->fetch();
        $stmt->close();

        if (!$statusId) {
            throw new Exception("VerifiedResident status not found.");
        }

        // Get user_id (for archived_at reset if column exists)
        $userId = null;
        $stmt = $conn->prepare("
            SELECT user_id
            FROM residentinformationtbl
            WHERE resident_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $residentId);
        $stmt->execute();
        $stmt->bind_result($userId);
        $stmt->fetch();
        $stmt->close();

        // Update resident status
        $stmt = $conn->prepare("
            UPDATE residentinformationtbl
            SET status_id_resident = ?
            WHERE resident_id = ?
        ");
        $stmt->bind_param("is", $statusId, $residentId);
        $stmt->execute();
        $stmt->close();

        // Clear archived_at if column exists
        $colExists = 0;
        $stmt = $conn->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'useraccountstbl'
              AND COLUMN_NAME = 'archived_at'
        ");
        $stmt->execute();
        $stmt->bind_result($colExists);
        $stmt->fetch();
        $stmt->close();

        if ($colExists && $userId) {
            $stmt = $conn->prepare("
                UPDATE useraccountstbl
                SET archived_at = NULL
                WHERE user_id = ?
            ");
            $stmt->bind_param("s", $userId);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Resident restored.']);
        exit;
    }

    // action === 'delete'
    // Find user_id and delete user (cascades to resident info)
    $userId = null;
    $stmt = $conn->prepare("
        SELECT user_id
        FROM residentinformationtbl
        WHERE resident_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $residentId);
    $stmt->execute();
    $stmt->bind_result($userId);
    $stmt->fetch();
    $stmt->close();

    if (!$userId) {
        throw new Exception("Resident not found.");
    }

    $stmt = $conn->prepare("
        DELETE FROM useraccountstbl
        WHERE user_id = ?
    ");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Resident deleted permanently.']);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
