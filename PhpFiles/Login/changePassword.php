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
require_once __DIR__ . '/../General/audit.php';
require_once __DIR__ . '/../General/uniqueIDGenerate.php';

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
$newPassword = (string)($payload['new_password'] ?? '');

if (trim($currentPassword) === '' || $newPassword === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// Same policy as registration.
if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[\\W_]).{8,}$/', $newPassword)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters with uppercase, lowercase, number, and special character.']);
    exit;
}

$userId = (string)$_SESSION['user_id'];

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT password_hash FROM useraccountstbl WHERE user_id = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception('Failed to prepare query.');
    }
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $stmt->bind_result($currentHash);
    if (!$stmt->fetch()) {
        $stmt->close();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Account not found.']);
        exit;
    }
    $stmt->close();

    if (!password_verify($currentPassword, (string)$currentHash)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }

    if (password_verify($newPassword, (string)$currentHash)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'New password must be different from current password.']);
        exit;
    }

    // Password history policy (best-effort if table exists).
    $historyOk = true;
    $historyStmt = $conn->prepare("
        SELECT old_pw_hash, change_timestamp
        FROM userpasswordhistorytbl
        WHERE user_id = ?
        ORDER BY change_timestamp DESC
    ");
    if ($historyStmt) {
        $historyStmt->bind_param('s', $userId);
        $historyStmt->execute();
        $historyStmt->bind_result($oldHash, $changeTimestamp);

        $now = new DateTime();
        $historyIndex = 0;
        while ($historyStmt->fetch()) {
            $usedAt = new DateTime((string)$changeTimestamp);
            $diff = $now->diff($usedAt);
            $monthsAgo = ($diff->y * 12) + $diff->m;

            if ($historyIndex < 3 && password_verify($newPassword, (string)$oldHash)) {
                $historyOk = false;
                break;
            }
            if ($monthsAgo < 6 && password_verify($newPassword, (string)$oldHash)) {
                $historyOk = false;
                break;
            }
            $historyIndex++;
        }
        $historyStmt->close();
    }

    if (!$historyOk) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'You\'ve already used this password.']);
        exit;
    }

    $conn->begin_transaction();

    // Save old hash to history if possible.
    insertPasswordHistoryEntry($conn, $userId, (string)$currentHash);

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $up = $conn->prepare("UPDATE useraccountstbl SET password_hash = ?, last_password_changed = NOW() WHERE user_id = ?");
    if (!$up) {
        throw new Exception('Failed to prepare update.');
    }
    $up->bind_param('ss', $newHash, $userId);
    if (!$up->execute()) {
        $up->close();
        throw new Exception('Failed to update password.');
    }
    $up->close();

    // Trim history (best-effort).
    $trim = $conn->prepare("
        DELETE FROM userpasswordhistorytbl
        WHERE user_id = ?
          AND pw_history_id NOT IN (
              SELECT pw_history_id FROM (
                  SELECT pw_history_id
                  FROM userpasswordhistorytbl
                  WHERE user_id = ?
                  ORDER BY change_timestamp DESC
                  LIMIT 3
              ) x
          )
          AND change_timestamp < NOW() - INTERVAL 6 MONTH
    ");
    if ($trim) {
        $trim->bind_param('ss', $userId, $userId);
        $trim->execute();
        $trim->close();
    }

    $conn->commit();

    // Audit (best-effort): do not store hashes. Record "N/A" for old/new values.
    try {
        $actorRole = (string)($_SESSION['role'] ?? 'Resident');
        insertUnifiedAuditLog(
            $conn,
            $userId,
            $actorRole,
            'Resident Profile',
            'UserAccount',
            $userId,
            'PASSWORD_CHANGED',
            'password_hash',
            'N/A',
            'N/A',
            null,
            null
        );
    } catch (Throwable $e) {
        // ignore audit failures
    }

    echo json_encode(['success' => true, 'message' => 'Password changed successfully.']);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        @$conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
}
