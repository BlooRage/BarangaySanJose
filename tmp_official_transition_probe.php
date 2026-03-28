<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$remoteAddr = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
if (!in_array($remoteAddr, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden.'], JSON_UNESCAPED_SLASHES);
    exit;
}

$token = trim((string)($_GET['token'] ?? ''));
if ($token !== 'codex-seat-probe-20260328') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid token.'], JSON_UNESCAPED_SLASHES);
    exit;
}

require_once __DIR__ . '/PhpFiles/General/connection.php';

$action = trim((string)($_GET['action'] ?? 'inspect'));
$seatName = trim((string)($_GET['seat_name'] ?? 'Kagawad Seat 7'));

if (!function_exists('tmp_probe_json')) {
    function tmp_probe_json(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
}

$seatStmt = $conn->prepare("
    SELECT council_id, seat_name, selection_method, seat_group, current_official_id, term_start, term_end, is_active
    FROM barangaycounciltbl
    WHERE seat_name = ?
    LIMIT 1
");
if (!$seatStmt) {
    tmp_probe_json(['success' => false, 'message' => 'Failed to prepare seat query.', 'db_error' => $conn->error], 500);
}
$seatStmt->bind_param('s', $seatName);
$seatStmt->execute();
$seat = $seatStmt->get_result()->fetch_assoc() ?: null;
$seatStmt->close();

if (!$seat) {
    tmp_probe_json(['success' => false, 'message' => 'Seat not found.'], 404);
}

$official = null;
$user = null;
$transitionRows = [];
$officialId = trim((string)($seat['current_official_id'] ?? ''));

if ($officialId !== '') {
    $officialStmt = $conn->prepare("
        SELECT official_id, user_id, firstname, middlename, lastname, suffix,
               role_access, position_access, department, area_number, term_start, term_end, date_hired
        FROM officialinformationtbl
        WHERE official_id = ?
        LIMIT 1
    ");
    if ($officialStmt) {
        $officialStmt->bind_param('s', $officialId);
        $officialStmt->execute();
        $official = $officialStmt->get_result()->fetch_assoc() ?: null;
        $officialStmt->close();
        if ($official) {
            $official = pii_decrypt_official_row($official) ?? $official;
            $official = pii_decrypt_assoc($official, ['firstname', 'middlename', 'lastname', 'suffix']);
        }
    }

    $userId = trim((string)($official['user_id'] ?? ''));
    if ($userId !== '') {
        $userStmt = $conn->prepare("
            SELECT user_id, username, email, phone_number, role_access, status_id_account
            FROM useraccountstbl
            WHERE user_id = ?
            LIMIT 1
        ");
        if ($userStmt) {
            $userStmt->bind_param('s', $userId);
            $userStmt->execute();
            $user = $userStmt->get_result()->fetch_assoc() ?: null;
            $userStmt->close();
            if ($user) {
                $user = pii_decrypt_useraccount_row($user) ?? $user;
            }
        }
    }

    if ($conn->query("SHOW TABLES LIKE 'officialtransitionstbl'") instanceof mysqli_result) {
        $transitionStmt = $conn->prepare("
            SELECT transition_id, transition_type, status, outcome, position, department,
                   effective_date, completed_at, incoming_official_id
            FROM officialtransitionstbl
            WHERE council_id = ?
               OR incoming_official_id = ?
            ORDER BY completed_at DESC, effective_date DESC, transition_id DESC
            LIMIT 10
        ");
        if ($transitionStmt) {
            $councilId = (int)($seat['council_id'] ?? 0);
            $transitionStmt->bind_param('is', $councilId, $officialId);
            $transitionStmt->execute();
            $transitionRes = $transitionStmt->get_result();
            while ($row = $transitionRes->fetch_assoc()) {
                $transitionRows[] = $row;
            }
            $transitionStmt->close();
        }
    }
}

if ($action === 'inspect') {
    tmp_probe_json([
        'success' => true,
        'seat' => $seat,
        'official' => $official,
        'user' => $user,
        'transitions' => $transitionRows,
    ]);
}

if ($action !== 'delete_assignment') {
    tmp_probe_json(['success' => false, 'message' => 'Unknown action.'], 400);
}

$conn->begin_transaction();

try {
    $updatedSeat = false;
    $deleteOfficial = false;
    $deleteUser = false;

    $seatUpdateStmt = $conn->prepare("
        UPDATE barangaycounciltbl
        SET current_official_id = NULL,
            term_start = NULL,
            term_end = NULL,
            updated_at = NOW()
        WHERE council_id = ?
        LIMIT 1
    ");
    if (!$seatUpdateStmt) {
        throw new RuntimeException('Failed to prepare seat update: ' . $conn->error);
    }
    $councilId = (int)($seat['council_id'] ?? 0);
    $seatUpdateStmt->bind_param('i', $councilId);
    $seatUpdateStmt->execute();
    $updatedSeat = $seatUpdateStmt->affected_rows >= 0;
    $seatUpdateStmt->close();

    if ($officialId !== '' && $official) {
        $otherSeatStmt = $conn->prepare("
            SELECT COUNT(*) AS cnt
            FROM barangaycounciltbl
            WHERE current_official_id = ?
              AND council_id <> ?
        ");
        if (!$otherSeatStmt) {
            throw new RuntimeException('Failed to prepare seat reference check: ' . $conn->error);
        }
        $otherSeatStmt->bind_param('si', $officialId, $councilId);
        $otherSeatStmt->execute();
        $otherSeatCount = (int)(($otherSeatStmt->get_result()->fetch_assoc()['cnt'] ?? 0));
        $otherSeatStmt->close();

        if ($otherSeatCount === 0) {
            $officialDeleteStmt = $conn->prepare("DELETE FROM officialinformationtbl WHERE official_id = ? LIMIT 1");
            if (!$officialDeleteStmt) {
                throw new RuntimeException('Failed to prepare official delete: ' . $conn->error);
            }
            $officialDeleteStmt->bind_param('s', $officialId);
            $officialDeleteStmt->execute();
            $deleteOfficial = $officialDeleteStmt->affected_rows === 1;
            $officialDeleteStmt->close();

            $userId = trim((string)($official['user_id'] ?? ''));
            if ($userId !== '') {
                $otherOfficialStmt = $conn->prepare("
                    SELECT COUNT(*) AS cnt
                    FROM officialinformationtbl
                    WHERE user_id = ?
                ");
                if (!$otherOfficialStmt) {
                    throw new RuntimeException('Failed to prepare user reference check: ' . $conn->error);
                }
                $otherOfficialStmt->bind_param('s', $userId);
                $otherOfficialStmt->execute();
                $otherOfficialCount = (int)(($otherOfficialStmt->get_result()->fetch_assoc()['cnt'] ?? 0));
                $otherOfficialStmt->close();

                if ($otherOfficialCount === 0) {
                    $userDeleteStmt = $conn->prepare("DELETE FROM useraccountstbl WHERE user_id = ? LIMIT 1");
                    if (!$userDeleteStmt) {
                        throw new RuntimeException('Failed to prepare user delete: ' . $conn->error);
                    }
                    $userDeleteStmt->bind_param('s', $userId);
                    $userDeleteStmt->execute();
                    $deleteUser = $userDeleteStmt->affected_rows === 1;
                    $userDeleteStmt->close();
                }
            }
        }
    }

    $conn->commit();

    tmp_probe_json([
        'success' => true,
        'seat_cleared' => $updatedSeat,
        'official_deleted' => $deleteOfficial,
        'user_deleted' => $deleteUser,
        'seat' => $seat,
        'official_id' => $officialId,
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    tmp_probe_json([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}
