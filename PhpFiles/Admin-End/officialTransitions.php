<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/security.php';
require_once __DIR__ . '/../General/audit.php';

requireRoleSession(['SuperAdmin']);

header('Content-Type: application/json; charset=utf-8');

// ── Helpers ───────────────────────────────────────────────────────────────────

function otJson(array $payload, int $code = 200): never {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function otError(string $message, int $code = 400): never {
    otJson(['success' => false, 'message' => $message], $code);
}

function otGenerateTransitionId(mysqli $conn): string {
    $prefix = 'TRN-' . date('Ymd') . '-';
    $res = $conn->query("SELECT transition_id FROM officialtransitionstbl WHERE transition_id LIKE '{$prefix}%' ORDER BY transition_id DESC LIMIT 1");
    $last = $res instanceof mysqli_result ? $res->fetch_assoc() : null;
    $seq = 1;
    if ($last) {
        $parts = explode('-', (string)($last['transition_id'] ?? ''));
        $seq = ((int)end($parts)) + 1;
    }
    return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
}

function otGetStatusId(mysqli $conn, string $statusType, string $statusName): ?int {
    $stmt = $conn->prepare("SELECT status_id FROM statuslookuptbl WHERE status_type = ? AND status_name = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('ss', $statusType, $statusName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['status_id'] : null;
}

function otGetOfficialUser(mysqli $conn, string $officialId): ?array {
    $stmt = $conn->prepare("
        SELECT oi.official_id, oi.user_id, oi.firstname, oi.lastname, oi.middlename, oi.suffix,
               COALESCE(oi.position_access, oi.role_access) AS position,
               oi.department, oi.area_number,
               ua.email, ua.phone_number, ua.role_access AS ua_role,
               ua.status_id_account,
               COALESCE(se.status_name, '') AS employment_status,
               COALESCE(sa.status_name, '') AS account_status_name
        FROM officialinformationtbl oi
        INNER JOIN useraccountstbl ua ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
        LEFT JOIN statuslookuptbl se ON se.status_id = oi.status_id_employment
        LEFT JOIN statuslookuptbl sa ON sa.status_id = ua.status_id_account
        WHERE oi.official_id = ?
        LIMIT 1
    ");
    if (!$stmt) return null;
    $stmt->bind_param('s', $officialId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function otSendEmail(string $email, string $subject, string $body): void {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return;
    try {
        $autoloadPath = __DIR__ . '/../../composer-email-handler/vendor/autoload.php';
        if (!file_exists($autoloadPath)) return;
        require_once $autoloadPath;
        if (!class_exists('EmailSender')) return;
        $mailConfig = require __DIR__ . '/../General/mailConfigurations.php';
        $sender = new EmailSender($mailConfig);
        $sender->send([
            'to'      => $email,
            'subject' => $subject,
            'body'    => nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')),
        ]);
    } catch (\Throwable) { /* best-effort */ }
}

function otSendSMS(string $phone, string $message): void {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if ($phone === '') return;
    try {
        $smsPath = __DIR__ . '/../General/sendSMS.php';
        if (!file_exists($smsPath)) return;
        require_once $smsPath;
        if (function_exists('sendSMS')) @sendSMS($phone, $message);
    } catch (\Throwable) { /* best-effort */ }
}

function otNotifyUser(mysqli $conn, string $userId, string $subject, string $message): void {
    $stmt = $conn->prepare("SELECT email, phone_number FROM useraccountstbl WHERE user_id = ? LIMIT 1");
    if (!$stmt) return;
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $acct = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$acct) return;
    $email = trim((string)($acct['email'] ?? ''));
    $phone = trim((string)($acct['phone_number'] ?? ''));
    otSendEmail($email, $subject, $message);
    otSendSMS($phone, $message);
}

// ── Route ──────────────────────────────────────────────────────────────────
$action = trim((string)($_POST['action'] ?? $_GET['action'] ?? ''));
$actorId   = (string)($_SESSION['user_id'] ?? '');
$actorRole = (string)($_SESSION['role'] ?? 'SuperAdmin');

// ── Table existence guard (migration may not have run yet) ───────────────────
function otTableExists(mysqli $conn, string $table): bool {
    $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

// ════════════════════════════════════════════════════════════════════════════
// FETCH: council seats (for New Transition / New Batch modals)
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'fetch_council_seats') {
    if (!otTableExists($conn, 'barangaycounciltbl')) {
        otJson(['success' => true, 'seats' => []]);
    }

    $res = $conn->query("
        SELECT
            bc.council_id,
            bc.seat_name,
            bc.selection_method,
            bc.seat_group,
            bc.sort_order,
            bc.term_start,
            bc.term_end,
            bc.is_active,
            bc.current_official_id,
            CONCAT(oi.lastname, ', ', oi.firstname,
                   IFNULL(CONCAT(' ', oi.middlename),''),
                   IFNULL(CONCAT(' ', oi.suffix),''))     AS current_official_name,
            COALESCE(sa.status_name,'')                    AS account_status
        FROM barangaycounciltbl bc
        LEFT JOIN officialinformationtbl oi
               ON oi.official_id = bc.current_official_id
        LEFT JOIN useraccountstbl ua
               ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
        LEFT JOIN statuslookuptbl sa
               ON sa.status_id = ua.status_id_account
        WHERE bc.is_active = 1
        ORDER BY bc.sort_order, bc.council_id
    ");

    $seats = [];
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) $seats[] = $row;
        $res->close();
    }

    otJson(['success' => true, 'seats' => $seats]);
}

// ════════════════════════════════════════════════════════════════════════════
// FETCH: transitions list
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'fetch_transitions') {
    if (!otTableExists($conn, 'officialtransitionstbl')) {
        otJson(['success' => true, 'data' => [], 'total' => 0, 'notice' => 'Migration not yet applied.']);
    }
    $tab    = trim((string)($_GET['tab']    ?? 'active'));
    $q      = trim((string)($_GET['q']      ?? ''));
    $type   = trim((string)($_GET['type']   ?? ''));
    $limit  = min(max((int)($_GET['limit'] ?? 100), 1), 500);
    $offset = max((int)($_GET['offset'] ?? 0), 0);

    $where = [];
    $params = [];
    $types  = '';

    if ($tab === 'history') {
        $where[] = "t.status IN ('Completed','Cancelled')";
    } else {
        $where[] = "t.status NOT IN ('Completed','Cancelled')";
    }
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = "(t.transition_id LIKE ? OR t.position LIKE ? OR t.batch_label LIKE ? OR CONCAT(oi.lastname,' ',oi.firstname) LIKE ?)";
        $params = array_merge($params, [$like, $like, $like, $like]);
        $types .= 'ssss';
    }
    if ($type !== '') {
        $where[] = "t.transition_type = ?";
        $params[] = $type;
        $types .= 's';
    }

    $whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "
        SELECT t.*,
               CONCAT(oi.lastname, ', ', oi.firstname, IFNULL(CONCAT(' ', oi.middlename),''), IFNULL(CONCAT(' ', oi.suffix),'')) AS outgoing_name,
               COALESCE(oi.position_access, oi.role_access) AS outgoing_position,
               (SELECT COUNT(*) FROM upcomingofficialstbl u WHERE u.transition_id = t.transition_id) AS candidate_count
        FROM officialtransitionstbl t
        LEFT JOIN officialinformationtbl oi ON oi.official_id = t.outgoing_official_id
        {$whereClause}
        ORDER BY t.created_at DESC
        LIMIT ? OFFSET ?
    ";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $stmt = $conn->prepare($sql);
    if (!$stmt) otError('Query prepare failed: ' . $conn->error);

    $refs = [$types];
    foreach ($params as $k => $v) $refs[] = &$params[$k];
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Total count
    $countSql = "SELECT COUNT(*) AS cnt FROM officialtransitionstbl t LEFT JOIN officialinformationtbl oi ON oi.official_id = t.outgoing_official_id {$whereClause}";
    $countTypes = substr($types, 0, -2);
    $countParams = array_slice($params, 0, -2);
    $countStmt = $conn->prepare($countSql);
    $total = 0;
    if ($countStmt) {
        if ($countTypes !== '' && $countParams) {
            $crefs = [$countTypes];
            foreach ($countParams as $k => $v) $crefs[] = &$countParams[$k];
            call_user_func_array([$countStmt, 'bind_param'], $crefs);
        }
        $countStmt->execute();
        $total = (int)($countStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        $countStmt->close();
    }

    otJson(['success' => true, 'data' => $rows, 'total' => $total]);
}

// ════════════════════════════════════════════════════════════════════════════
// FETCH: inactive/suspended officials (for Restore Access)
// ════════════════════════════════════════════════════════════════════════════
// (no transition table needed — queries officialinformationtbl directly)
if ($action === 'fetch_inactive_officials') {
    $q = trim((string)($_GET['q'] ?? ''));
    $sql = "
        SELECT oi.official_id,
               CONCAT(oi.lastname, ', ', oi.firstname) AS full_name,
               COALESCE(oi.position_access, oi.role_access) AS position,
               oi.department,
               COALESCE(sa.status_name,'') AS account_status,
               COALESCE(se.status_name,'') AS employment_status,
               oi.transition_out_type,
               oi.transition_out_date,
               oi.can_return
        FROM officialinformationtbl oi
        INNER JOIN useraccountstbl ua ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
        LEFT JOIN statuslookuptbl sa ON sa.status_id = ua.status_id_account
        LEFT JOIN statuslookuptbl se ON se.status_id = oi.status_id_employment
        WHERE sa.status_name IN ('Inactive','Suspended','Revoked','Disabled')
    ";
    $params = [];
    $types  = '';
    if ($q !== '') {
        $like = '%' . $q . '%';
        $sql .= " AND (oi.official_id LIKE ? OR oi.firstname LIKE ? OR oi.lastname LIKE ? OR COALESCE(oi.position_access,oi.role_access) LIKE ?)";
        $params = [$like, $like, $like, $like];
        $types  = 'ssss';
    }
    $sql .= " ORDER BY oi.lastname, oi.firstname LIMIT 100";

    $stmt = $conn->prepare($sql);
    if (!$stmt) otError('Query failed.');
    if ($types !== '') {
        $refs = [$types];
        foreach ($params as $k => $v) $refs[] = &$params[$k];
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    otJson(['success' => true, 'data' => $rows]);
}

// ════════════════════════════════════════════════════════════════════════════
// FETCH: candidates for a transition
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'fetch_candidates') {
    if (!otTableExists($conn, 'officialtransitionstbl')) {
        otJson(['success' => true, 'candidates' => [], 'transition' => null]);
    }
    $transitionId = trim((string)($_GET['transition_id'] ?? ''));
    if ($transitionId === '') otError('Missing transition_id.');

    $stmt = $conn->prepare("SELECT * FROM upcomingofficialstbl WHERE transition_id = ? ORDER BY upcoming_id ASC");
    if (!$stmt) otError('Query failed.');
    $stmt->bind_param('s', $transitionId);
    $stmt->execute();
    $candidates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $tStmt = $conn->prepare("SELECT t.*, CONCAT(oi.lastname,', ',oi.firstname) AS outgoing_name, COALESCE(oi.position_access,oi.role_access) AS outgoing_position FROM officialtransitionstbl t LEFT JOIN officialinformationtbl oi ON oi.official_id = t.outgoing_official_id WHERE t.transition_id = ? LIMIT 1");
    $transition = null;
    if ($tStmt) {
        $tStmt->bind_param('s', $transitionId);
        $tStmt->execute();
        $transition = $tStmt->get_result()->fetch_assoc();
        $tStmt->close();
    }

    otJson(['success' => true, 'candidates' => $candidates, 'transition' => $transition]);
}

// ════════════════════════════════════════════════════════════════════════════
// POST: create individual transition
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'new_transition') {
    if (!otTableExists($conn, 'officialtransitionstbl')) {
        otError('Database migration has not been applied yet. Please run the migration before using this module.', 503);
    }
    $councilId    = (int)($_POST['council_id']               ?? 0);
    $transType    = trim((string)($_POST['transition_type']  ?? ''));
    $effectiveDate= trim((string)($_POST['effective_date']   ?? ''));
    $reason       = trim((string)($_POST['reason']           ?? ''));
    $batchLabel   = trim((string)($_POST['batch_label']      ?? ''));
    $electionDate = trim((string)($_POST['election_date']    ?? ''));

    if ($councilId <= 0)  otError('Council seat is required.');
    if ($transType === '') otError('Transition type is required.');
    if ($transType === 'Removal' && $reason === '') otError('Reason is required for Removal.');

    // Load seat info (position, current holder, selection method)
    $seatStmt = $conn->prepare("
        SELECT bc.seat_name, bc.selection_method, bc.current_official_id,
               oi.department, oi.area_number
        FROM barangaycounciltbl bc
        LEFT JOIN officialinformationtbl oi ON oi.official_id = bc.current_official_id
        WHERE bc.council_id = ? AND bc.is_active = 1
        LIMIT 1
    ");
    if (!$seatStmt) otError('Failed to load seat: ' . $conn->error);
    $seatStmt->bind_param('i', $councilId);
    $seatStmt->execute();
    $seat = $seatStmt->get_result()->fetch_assoc();
    $seatStmt->close();
    if (!$seat) otError('Council seat not found or inactive.');

    $position   = (string)($seat['seat_name']             ?? '');
    $outgoingId = (string)($seat['current_official_id']   ?? '');
    $department = (string)($seat['department']            ?? '');
    $areaN      = (string)($seat['area_number']           ?? '');

    $transId = otGenerateTransitionId($conn);

    $stmt = $conn->prepare("
        INSERT INTO officialtransitionstbl
            (transition_id, council_id, batch_label, election_date, transition_type, position,
             department, area_number, outgoing_official_id, effective_date, reason, status, created_by)
        VALUES (?, NULLIF(?,0), NULLIF(?,''), NULLIF(?,''), ?, ?,
                NULLIF(?,''), NULLIF(?,''), NULLIF(?,''), NULLIF(?,''), NULLIF(?,''), 'Open', ?)
    ");
    if (!$stmt) otError('Insert failed: ' . $conn->error);
    $stmt->bind_param('sissssssssss',
        $transId, $councilId, $batchLabel, $electionDate,
        $transType, $position, $department, $areaN,
        $outgoingId, $effectiveDate, $reason, $actorId
    );
    if (!$stmt->execute()) otError('Failed to create transition: ' . $stmt->error);
    $stmt->close();

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'transition', $transId,
        'create_transition', 'transition_type', null, $transType,
        "Seat: {$position} (council_id={$councilId})" . ($batchLabel ? " | Batch: {$batchLabel}" : '')
    );

    otJson(['success' => true, 'message' => 'Transition created.', 'transition_id' => $transId]);
}

// ════════════════════════════════════════════════════════════════════════════
// POST: create batch transition
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'new_batch') {
    if (!otTableExists($conn, 'officialtransitionstbl')) {
        otError('Database migration has not been applied yet.', 503);
    }
    $batchLabel   = trim((string)($_POST['batch_label']   ?? ''));
    $batchType    = trim((string)($_POST['batch_type']    ?? ''));
    $electionDate = trim((string)($_POST['election_date'] ?? ''));
    $officials    = $_POST['officials'] ?? [];

    if ($batchLabel === '')   otError('Batch label is required.');
    if ($batchType === '')    otError('Batch type is required.');
    if ($electionDate === '') otError('Election date is required.');
    if (empty($officials))   otError('Select at least one official.');

    // $officials is now an array of council_id values (not official IDs)
    $created = [];
    $conn->begin_transaction();
    try {
        foreach ($officials as $rawCouncilId) {
            $councilId = (int)$rawCouncilId;
            if ($councilId <= 0) continue;

            // Load seat from barangaycounciltbl
            $seatStmt = $conn->prepare("
                SELECT bc.seat_name, bc.current_official_id,
                       oi.department, oi.area_number
                FROM barangaycounciltbl bc
                LEFT JOIN officialinformationtbl oi ON oi.official_id = bc.current_official_id
                WHERE bc.council_id = ? AND bc.is_active = 1
                LIMIT 1
            ");
            if (!$seatStmt) throw new Exception('Seat query failed.');
            $seatStmt->bind_param('i', $councilId);
            $seatStmt->execute();
            $seat = $seatStmt->get_result()->fetch_assoc();
            $seatStmt->close();
            if (!$seat) continue;

            $transId    = otGenerateTransitionId($conn);
            $position   = (string)($seat['seat_name']           ?? '');
            $outgoingId = (string)($seat['current_official_id'] ?? '');
            $dept       = (string)($seat['department']          ?? '');
            $area       = (string)($seat['area_number']         ?? '');

            $stmt = $conn->prepare("
                INSERT INTO officialtransitionstbl
                    (transition_id, council_id, batch_label, election_date, transition_type, position,
                     department, area_number, outgoing_official_id, effective_date, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, NULLIF(?,''), NULLIF(?,''), NULLIF(?,''), ?, 'Open', ?)
            ");
            if (!$stmt) throw new Exception('Insert failed: ' . $conn->error);
            $stmt->bind_param('sisssssssss',
                $transId, $councilId, $batchLabel, $electionDate, $batchType,
                $position, $dept, $area, $outgoingId, $electionDate, $actorId
            );
            if (!$stmt->execute()) throw new Exception('Failed: ' . $stmt->error);
            $stmt->close();

            $created[] = $transId;
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        otError($e->getMessage());
    }

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'batch', $batchLabel,
        'create_batch', 'count', null, (string)count($created),
        "Election: {$electionDate} | Type: {$batchType}"
    );

    otJson(['success' => true, 'message' => count($created) . ' transitions created for batch.', 'created' => $created]);
}

// ════════════════════════════════════════════════════════════════════════════
// POST: update election date for batch
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'update_election_date') {
    $batchLabel   = trim((string)($_POST['batch_label']   ?? ''));
    $electionDate = trim((string)($_POST['election_date'] ?? ''));

    if ($batchLabel === '')   otError('Batch label is required.');
    if ($electionDate === '') otError('Election date is required.');

    $stmt = $conn->prepare("
        UPDATE officialtransitionstbl
        SET election_date = ?,
            notify_3mo_sent = 0, notify_3mo_sent_at = NULL,
            notify_1mo_sent = 0, notify_1mo_sent_at = NULL,
            deactivated_7d_before = 0, deactivated_7d_before_at = NULL,
            notify_post_sent = 0, notify_post_sent_at = NULL
        WHERE batch_label = ?
          AND notify_3mo_sent = 0
          AND notify_1mo_sent = 0
          AND deactivated_7d_before = 0
          AND notify_post_sent = 0
    ");
    if (!$stmt) otError('Update failed.');
    $stmt->bind_param('ss', $electionDate, $batchLabel);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'batch', $batchLabel,
        'update_election_date', 'election_date', null, $electionDate, null
    );

    otJson(['success' => true, 'message' => "Election date updated for {$affected} transition(s)."]);
}

// ════════════════════════════════════════════════════════════════════════════
// POST: add candidate
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'add_candidate') {
    $transitionId    = trim((string)($_POST['transition_id']     ?? ''));
    $candidateName   = trim((string)($_POST['candidate_name']    ?? ''));
    $candidateType   = trim((string)($_POST['candidate_type']    ?? 'New'));
    $candidateContact= trim((string)($_POST['candidate_contact'] ?? ''));
    $linkedOfficialId= trim((string)($_POST['linked_official_id']  ?? ''));
    $linkedResidentId= trim((string)($_POST['linked_resident_id']  ?? ''));
    $notes           = trim((string)($_POST['notes']             ?? ''));

    if ($transitionId  === '') otError('Missing transition_id.');
    if ($candidateName === '') otError('Candidate name is required.');

    $validTypes = ['New','ReturningOfficial','ActiveOfficial','Resident'];
    if (!in_array($candidateType, $validTypes, true)) $candidateType = 'New';

    $stmt = $conn->prepare("
        INSERT INTO upcomingofficialstbl
            (transition_id, candidate_type, candidate_name, candidate_contact,
             linked_official_id, linked_resident_id, notes, encoded_by)
        VALUES (?, ?, ?, NULLIF(?,''), NULLIF(?,''), NULLIF(?,''), NULLIF(?,''), ?)
    ");
    if (!$stmt) otError('Insert failed: ' . $conn->error);
    $stmt->bind_param('ssssssss',
        $transitionId, $candidateType, $candidateName, $candidateContact,
        $linkedOfficialId, $linkedResidentId, $notes, $actorId
    );
    if (!$stmt->execute()) otError('Failed to add candidate: ' . $stmt->error);
    $newId = $conn->insert_id;
    $stmt->close();

    // Update transition status to CandidateEncoding if still Open
    $conn->query("UPDATE officialtransitionstbl SET status='CandidateEncoding' WHERE transition_id='{$conn->real_escape_string($transitionId)}' AND status='Open'");

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'candidate', (string)$newId,
        'add_candidate', 'candidate_name', null, $candidateName,
        "Transition: {$transitionId} | Type: {$candidateType}"
    );

    otJson(['success' => true, 'message' => 'Candidate added.', 'upcoming_id' => $newId]);
}

// ════════════════════════════════════════════════════════════════════════════
// POST: remove candidate
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'remove_candidate') {
    $upcomingId = (int)($_POST['upcoming_id'] ?? 0);
    if ($upcomingId <= 0) otError('Invalid candidate ID.');

    $row = $conn->query("SELECT candidate_name, transition_id FROM upcomingofficialstbl WHERE upcoming_id = {$upcomingId} LIMIT 1")->fetch_assoc();
    if (!$row) otError('Candidate not found.');

    $conn->query("DELETE FROM upcomingofficialstbl WHERE upcoming_id = {$upcomingId}");

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'candidate', (string)$upcomingId,
        'remove_candidate', 'candidate_name', (string)$row['candidate_name'], null,
        "Transition: {$row['transition_id']}"
    );

    otJson(['success' => true, 'message' => 'Candidate removed.']);
}

// ════════════════════════════════════════════════════════════════════════════
// POST: mark transition as pending decision
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'mark_pending_decision') {
    $transitionId = trim((string)($_POST['transition_id'] ?? ''));
    if ($transitionId === '') otError('Missing transition_id.');

    $count = (int)$conn->query("SELECT COUNT(*) AS c FROM upcomingofficialstbl WHERE transition_id='{$conn->real_escape_string($transitionId)}'")->fetch_assoc()['c'];
    if ($count === 0) otError('Add at least one candidate before marking ready for decision.');

    $stmt = $conn->prepare("UPDATE officialtransitionstbl SET status='PendingDecision' WHERE transition_id=? AND status IN ('CandidateEncoding','Open')");
    if (!$stmt) otError('Update failed.');
    $stmt->bind_param('s', $transitionId);
    $stmt->execute();
    $stmt->close();

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'transition', $transitionId,
        'mark_pending_decision', 'status', 'CandidateEncoding', 'PendingDecision', null
    );

    otJson(['success' => true, 'message' => 'Transition marked as ready for decision.']);
}

// ════════════════════════════════════════════════════════════════════════════
// POST: complete transition (select winner + process)
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'complete_transition') {
    $transitionId  = trim((string)($_POST['transition_id']    ?? ''));
    $candidateId   = (int)($_POST['selected_candidate_id']    ?? 0);
    $outcome       = trim((string)($_POST['outcome']          ?? ''));
    $actingUntil   = trim((string)($_POST['acting_until_date']?? ''));

    if ($transitionId === '') otError('Missing transition_id.');
    if ($outcome === '')      otError('Outcome is required.');

    $validOutcomes = ['ReElected','NewPerson','Reactivated','PositionChange','ActingReplacement','NoSuccessor'];
    if (!in_array($outcome, $validOutcomes, true)) otError('Invalid outcome.');

    // Load transition
    $tStmt = $conn->prepare("SELECT * FROM officialtransitionstbl WHERE transition_id = ? LIMIT 1");
    if (!$tStmt) otError('Query failed.');
    $tStmt->bind_param('s', $transitionId);
    $tStmt->execute();
    $transition = $tStmt->get_result()->fetch_assoc();
    $tStmt->close();
    if (!$transition) otError('Transition not found.');
    if ((string)($transition['status'] ?? '') === 'Completed') otError('Transition already completed.');

    $outgoingId = (string)($transition['outgoing_official_id'] ?? '');
    $outgoing   = $outgoingId !== '' ? otGetOfficialUser($conn, $outgoingId) : null;

    // ── Load selected candidate (required for most outcomes) ─────────────────
    $candidate = null;
    if ($outcome !== 'NoSuccessor' && $candidateId > 0) {
        $cStmt = $conn->prepare("SELECT * FROM upcomingofficialstbl WHERE upcoming_id = ? AND transition_id = ? LIMIT 1");
        if ($cStmt) {
            $cStmt->bind_param('is', $candidateId, $transitionId);
            $cStmt->execute();
            $candidate = $cStmt->get_result()->fetch_assoc();
            $cStmt->close();
        }
    }

    // ── Position uniqueness check (skip for ReElected & NoSuccessor) ─────────
    if (!in_array($outcome, ['ReElected','NoSuccessor'], true)) {
        $posToCheck = (string)($transition['position'] ?? '');
        if ($posToCheck !== '') {
            $posCheck = $conn->prepare("
                SELECT oi.official_id FROM officialinformationtbl oi
                INNER JOIN useraccountstbl ua ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
                INNER JOIN statuslookuptbl sa ON sa.status_id = ua.status_id_account
                WHERE COALESCE(oi.position_access, oi.role_access) = ?
                  AND sa.status_name = 'Active'
                  AND oi.official_id != ?
                LIMIT 1
            ");
            if ($posCheck) {
                $safeOutId = $outgoingId ?: 'NONE';
                $posCheck->bind_param('ss', $posToCheck, $safeOutId);
                $posCheck->execute();
                $existing = $posCheck->get_result()->fetch_assoc();
                $posCheck->close();
                if ($existing) {
                    otError("Position '{$posToCheck}' already has an active official. Resolve that first.");
                }
            }
        }
    }

    $inactiveStatusId = otGetStatusId($conn, 'UserAccount', 'Inactive');
    $activeStatusId   = otGetStatusId($conn, 'UserAccount', 'Active');
    $suspendedStatusId= otGetStatusId($conn, 'UserAccount', 'Suspended');

    $conn->begin_transaction();
    try {
        // ── Process outgoing official ─────────────────────────────────────────
        if ($outgoing && $outgoingId !== '') {
            $outUserId = (string)($outgoing['user_id'] ?? '');
            $transType = (string)($transition['transition_type'] ?? '');

            // Map transition type → employment status label
            $empStatusMap = [
                'BarangayElection' => 'Term Ended',
                'SKElection'       => 'Term Ended',
                'Resignation'      => 'Resigned',
                'Removal'          => 'Removed',
                'Retirement'       => 'Retired',
                'Reappointment'    => 'Reappointed',
                'Appointment'      => 'Reappointed',
            ];
            $newEmpStatus = $empStatusMap[$transType] ?? 'Term Ended';

            if ($outcome === 'ReElected') {
                // No status change — just refresh term dates
                $conn->query("UPDATE officialinformationtbl SET term_start = CURDATE(), last_updated = CURRENT_TIMESTAMP WHERE official_id = '{$conn->real_escape_string($outgoingId)}'");
                $newEmpStatus = 'Re-Elected';
            } elseif ($outcome === 'ActingReplacement') {
                // Suspend (not deactivate) original
                if ($suspendedStatusId && $outUserId !== '') {
                    $upStmt = $conn->prepare("UPDATE useraccountstbl SET status_id_account = ?, updated_at = NOW() WHERE user_id = ? LIMIT 1");
                    if ($upStmt) { $upStmt->bind_param('is', $suspendedStatusId, $outUserId); $upStmt->execute(); $upStmt->close(); }
                }
            } else {
                // Deactivate outgoing
                if ($inactiveStatusId && $outUserId !== '') {
                    $upStmt = $conn->prepare("UPDATE useraccountstbl SET status_id_account = ?, updated_at = NOW() WHERE user_id = ? LIMIT 1");
                    if ($upStmt) { $upStmt->bind_param('is', $inactiveStatusId, $outUserId); $upStmt->execute(); $upStmt->close(); }
                }
                // can_return = 0 for Removed officials
                if ($transType === 'Removal') {
                    $conn->query("UPDATE officialinformationtbl SET can_return = 0 WHERE official_id = '{$conn->real_escape_string($outgoingId)}'");
                }
            }

            // Record transition-out info on the official's record
            $empStatusId = otGetStatusId($conn, 'Employment', $newEmpStatus);
            $batchLabel  = (string)($transition['batch_label'] ?? '');
            $transReason = (string)($transition['reason'] ?? '');
            $outDate     = (string)($transition['effective_date'] ?? date('Y-m-d'));
            $upOi = $conn->prepare("
                UPDATE officialinformationtbl
                SET transition_out_type   = ?,
                    transition_out_date   = ?,
                    transition_out_reason = NULLIF(?,''),
                    batch_label           = NULLIF(?,''),
                    status_id_employment  = COALESCE(?, status_id_employment),
                    last_updated          = CURRENT_TIMESTAMP
                WHERE official_id = ?
            ");
            if ($upOi) {
                $upOi->bind_param('ssssis', $newEmpStatus, $outDate, $transReason, $batchLabel, $empStatusId, $outgoingId);
                $upOi->execute();
                $upOi->close();
            }

            // Notify outgoing official
            $outName    = trim(($outgoing['firstname'] ?? '') . ' ' . ($outgoing['lastname'] ?? ''));
            $position   = (string)($transition['position'] ?? '');
            if ($outcome === 'ReElected') {
                $subject = 'System Access Restored — Welcome Back';
                $msg     = "Dear {$outName},\n\nYour Barangay San Jose system account has been restored for your new term as {$position}. Please contact the IT Administrator if you need to update your credentials.\n\nBarangay San Jose";
            } elseif ($outcome === 'ActingReplacement') {
                $subject = 'System Access — Temporary Suspension';
                $msg     = "Dear {$outName},\n\nYour system access has been temporarily suspended as part of an acting assignment for {$position}. Your access will be restored when the acting period ends. Please contact the IT Administrator for details.\n\nBarangay San Jose";
            } else {
                $subject = 'System Access Update — Official Transition';
                $msg     = "Dear {$outName},\n\nYour Barangay San Jose system account has been updated following the official transition for {$position}. Your employment status has been recorded as: {$newEmpStatus}.\n\nIf you have concerns, please contact the IT Administrator.\n\nBarangay San Jose";
            }
            if ($outUserId !== '') otNotifyUser($conn, $outUserId, $subject, $msg);
        }

        // ── Process incoming (winner) ─────────────────────────────────────────
        $incomingOfficialId = null;

        if ($outcome === 'Reactivated' && $candidate) {
            $linkedId = (string)($candidate['linked_official_id'] ?? '');
            if ($linkedId !== '' && $activeStatusId) {
                $retOfficial = otGetOfficialUser($conn, $linkedId);
                if ($retOfficial) {
                    $retUserId = (string)($retOfficial['user_id'] ?? '');
                    $upStmt = $conn->prepare("UPDATE useraccountstbl SET status_id_account = ?, updated_at = NOW() WHERE user_id = ? LIMIT 1");
                    if ($upStmt) { $upStmt->bind_param('is', $activeStatusId, $retUserId); $upStmt->execute(); $upStmt->close(); }
                    // Update position on their record
                    $newPos = (string)($transition['position'] ?? '');
                    $conn->query("UPDATE officialinformationtbl SET position_access = '{$conn->real_escape_string($newPos)}', transition_out_type = NULL, transition_out_date = NULL, transition_out_reason = NULL, term_start = CURDATE(), can_return = 1, last_updated = CURRENT_TIMESTAMP WHERE official_id = '{$conn->real_escape_string($linkedId)}'");
                    $incomingOfficialId = $linkedId;
                    // Notify returning official
                    $retName = trim(($retOfficial['firstname'] ?? '') . ' ' . ($retOfficial['lastname'] ?? ''));
                    otNotifyUser($conn, $retUserId,
                        'System Access Restored — Welcome Back',
                        "Dear {$retName},\n\nYour Barangay San Jose system account has been restored for your new term as {$transition['position']}. Please contact the IT Administrator if you need to update your credentials.\n\nBarangay San Jose"
                    );
                }
            }
        } elseif ($outcome === 'PositionChange' && $candidate) {
            $linkedId = (string)($candidate['linked_official_id'] ?? '');
            if ($linkedId !== '') {
                $newPos = (string)($transition['position'] ?? '');
                $conn->query("UPDATE officialinformationtbl SET position_access = '{$conn->real_escape_string($newPos)}', term_start = CURDATE(), last_updated = CURRENT_TIMESTAMP WHERE official_id = '{$conn->real_escape_string($linkedId)}'");
                $incomingOfficialId = $linkedId;
                // Auto-open a new transition for the vacated position
                $vacOfficialData = otGetOfficialUser($conn, $linkedId);
                if ($vacOfficialData) {
                    $vacTransId  = otGenerateTransitionId($conn);
                    $vacPosition = (string)($vacOfficialData['position'] ?? '');
                    $vacDept     = (string)($vacOfficialData['department'] ?? '');
                    $vacArea     = (string)($vacOfficialData['area_number'] ?? '');
                    $autoStmt = $conn->prepare("INSERT INTO officialtransitionstbl (transition_id, transition_type, position, department, area_number, status, created_by, reason) VALUES (?, 'Replacement', NULLIF(?,''), NULLIF(?,''), NULLIF(?,''), 'Open', ?, 'Auto-opened: official moved to another position')");
                    if ($autoStmt) {
                        $autoStmt->bind_param('sssss', $vacTransId, $vacPosition, $vacDept, $vacArea, $actorId);
                        $autoStmt->execute();
                        $autoStmt->close();
                    }
                }
            }
        } elseif ($outcome === 'ActingReplacement' && $candidate) {
            $linkedId = (string)($candidate['linked_official_id'] ?? '');
            if ($linkedId !== '' && $activeStatusId) {
                $actOfficial = otGetOfficialUser($conn, $linkedId);
                if ($actOfficial) {
                    $actUserId = (string)($actOfficial['user_id'] ?? '');
                    $upStmt = $conn->prepare("UPDATE useraccountstbl SET status_id_account = ?, updated_at = NOW() WHERE user_id = ? LIMIT 1");
                    if ($upStmt) { $upStmt->bind_param('is', $activeStatusId, $actUserId); $upStmt->execute(); $upStmt->close(); }
                    // Mark acting relationship on the acting official
                    $conn->query("UPDATE officialinformationtbl SET acting_for_id = '{$conn->real_escape_string($outgoingId)}', acting_until_date = " . ($actingUntil !== '' ? "'{$conn->real_escape_string($actingUntil)}'" : 'NULL') . ", last_updated = CURRENT_TIMESTAMP WHERE official_id = '{$conn->real_escape_string($linkedId)}'");
                    $incomingOfficialId = $linkedId;
                }
            }
        } elseif ($outcome === 'NewPerson' && $candidate) {
            // Invite flow — link_invite_id will be set when the invite is created externally
            // Mark candidate with the transition for later linkage
            $conn->query("UPDATE upcomingofficialstbl SET is_selected = 1 WHERE upcoming_id = {$candidateId}");
        }

        // ── Mark selected candidate ───────────────────────────────────────────
        if ($candidateId > 0) {
            $conn->query("UPDATE upcomingofficialstbl SET is_selected = 0 WHERE transition_id = '{$conn->real_escape_string($transitionId)}'");
            $conn->query("UPDATE upcomingofficialstbl SET is_selected = 1 WHERE upcoming_id = {$candidateId}");
        }

        // ── Update transition record ──────────────────────────────────────────
        $isActing   = $outcome === 'ActingReplacement' ? 1 : 0;
        $actingDate = ($isActing && $actingUntil !== '') ? $actingUntil : null;
        $upTrans = $conn->prepare("
            UPDATE officialtransitionstbl
            SET status                = 'Completed',
                outcome               = ?,
                selected_candidate_id = NULLIF(?,0),
                incoming_official_id  = NULLIF(?,''),
                is_acting             = ?,
                acting_until_date     = ?,
                decided_at            = NOW(),
                completed_at          = NOW()
            WHERE transition_id = ?
        ");
        if ($upTrans) {
            $upTrans->bind_param('siisss',
                $outcome, $candidateId, $incomingOfficialId,
                $isActing, $actingDate, $transitionId
            );
            $upTrans->execute();
            $upTrans->close();
        }

        // ── Update barangaycounciltbl seat ────────────────────────────────────
        $councilId = (int)($transition['council_id'] ?? 0);
        if ($councilId > 0 && otTableExists($conn, 'barangaycounciltbl')) {
            $effDate = (string)($transition['effective_date'] ?? date('Y-m-d'));
            if ($outcome === 'NoSuccessor') {
                // Seat becomes vacant
                $conn->query("UPDATE barangaycounciltbl SET current_official_id = NULL, term_start = NULL, term_end = NULL, updated_at = NOW() WHERE council_id = {$councilId}");
            } elseif ($outcome === 'ReElected') {
                // Same person — refresh term start only
                $conn->query("UPDATE barangaycounciltbl SET term_start = '{$conn->real_escape_string($effDate)}', updated_at = NOW() WHERE council_id = {$councilId}");
            } elseif ($incomingOfficialId !== null && $incomingOfficialId !== '') {
                // New holder takes the seat
                $safeIncoming = $conn->real_escape_string($incomingOfficialId);
                $conn->query("UPDATE barangaycounciltbl SET current_official_id = '{$safeIncoming}', term_start = '{$conn->real_escape_string($effDate)}', term_end = NULL, updated_at = NOW() WHERE council_id = {$councilId}");
            }
            // ActingReplacement: keep original in current_official_id (they're only suspended, not replaced)
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        otError($e->getMessage());
    }

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'transition', $transitionId,
        'complete_transition', 'outcome', null, $outcome,
        "Outgoing: {$outgoingId} | Candidate: {$candidateId}"
    );

    otJson(['success' => true, 'message' => 'Transition completed successfully.']);
}

// ════════════════════════════════════════════════════════════════════════════
// POST: cancel transition
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'cancel_transition') {
    $transitionId = trim((string)($_POST['transition_id'] ?? ''));
    $reason       = trim((string)($_POST['reason']        ?? ''));
    if ($transitionId === '') otError('Missing transition_id.');

    $stmt = $conn->prepare("UPDATE officialtransitionstbl SET status='Cancelled', reason=CONCAT(COALESCE(reason,''),' [Cancelled: ',?,' at ',NOW(),']') WHERE transition_id=? AND status != 'Completed'");
    if (!$stmt) otError('Update failed.');
    $stmt->bind_param('ss', $reason, $transitionId);
    $stmt->execute();
    $stmt->close();

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'transition', $transitionId,
        'cancel_transition', 'status', null, 'Cancelled', $reason ?: null
    );

    otJson(['success' => true, 'message' => 'Transition cancelled.']);
}

// ════════════════════════════════════════════════════════════════════════════
// POST: restore access (Quick Action)
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'restore_access') {
    $officialId = trim((string)($_POST['official_id'] ?? ''));
    if ($officialId === '') otError('Missing official_id.');

    $official = otGetOfficialUser($conn, $officialId);
    if (!$official) otError('Official not found.');

    $activeStatusId = otGetStatusId($conn, 'UserAccount', 'Active');
    if (!$activeStatusId) otError('Active status not found in lookup table.');

    $userId = (string)($official['user_id'] ?? '');
    if ($userId === '') otError('No linked user account found.');

    $oldStatus = (string)($official['account_status_name'] ?? '');

    $stmt = $conn->prepare("UPDATE useraccountstbl SET status_id_account = ?, updated_at = NOW() WHERE user_id = ? LIMIT 1");
    if (!$stmt) otError('Update failed.');
    $stmt->bind_param('is', $activeStatusId, $userId);
    $stmt->execute();
    $stmt->close();

    // Clear acting fields if they were suspended as acting
    $conn->query("UPDATE officialinformationtbl SET acting_for_id = NULL, acting_until_date = NULL, transition_out_type = NULL, transition_out_date = NULL, transition_out_reason = NULL, last_updated = CURRENT_TIMESTAMP WHERE official_id = '{$conn->real_escape_string($officialId)}'");

    // Notify official
    $name = trim(($official['firstname'] ?? '') . ' ' . ($official['lastname'] ?? ''));
    otNotifyUser($conn, $userId,
        'System Access Restored',
        "Dear {$name},\n\nYour Barangay San Jose system account has been reactivated by the IT Administrator. Please log in and contact the IT Administrator if you need to update your credentials.\n\nBarangay San Jose"
    );

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'official', $officialId,
        'restore_access', 'account_status', $oldStatus, 'Active', 'Quick Action: restore access'
    );

    otJson(['success' => true, 'message' => "Access restored for {$name}."]);
}

// ════════════════════════════════════════════════════════════════════════════
// POST: change credentials (Quick Action)
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'change_credentials') {
    $officialId        = trim((string)($_POST['official_id']          ?? ''));
    $newEmail          = trim((string)($_POST['email']                ?? ''));
    $newPhone          = trim((string)($_POST['phone']                ?? ''));
    $forcePasswordReset= (int)($_POST['force_password_reset']        ?? 0);

    if ($officialId === '') otError('Missing official_id.');

    $official = otGetOfficialUser($conn, $officialId);
    if (!$official) otError('Official not found.');

    $userId   = (string)($official['user_id'] ?? '');
    if ($userId === '') otError('No linked user account.');

    $setParts = ['updated_at = NOW()'];
    $params   = [];
    $types    = '';

    if ($newEmail !== '') {
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) otError('Invalid email address.');
        $setParts[] = 'email = ?';
        $params[]   = $newEmail;
        $types     .= 's';
    }
    if ($newPhone !== '') {
        $setParts[] = 'phone_number = ?';
        $params[]   = $newPhone;
        $types     .= 's';
    }
    if ($forcePasswordReset) {
        // Set a flag or blank the password hash to force reset — implementation depends on auth flow
        $setParts[] = 'password_hash = NULL';
    }

    if (count($setParts) <= 1) otError('Nothing to update.');

    $params[] = $userId;
    $types   .= 's';
    $sql      = 'UPDATE useraccountstbl SET ' . implode(', ', $setParts) . ' WHERE user_id = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) otError('Update failed.');
    $refs = [$types];
    foreach ($params as $k => $v) $refs[] = &$params[$k];
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
    $stmt->close();

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'official', $officialId,
        'change_credentials', 'fields', null,
        implode(', ', array_filter([
            $newEmail ? 'email' : '',
            $newPhone ? 'phone' : '',
            $forcePasswordReset ? 'password_reset' : '',
        ])),
        'Quick Action: credential update'
    );

    otJson(['success' => true, 'message' => 'Credentials updated.']);
}

// ════════════════════════════════════════════════════════════════════════════
// POST: end acting assignment (Quick Action)
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'end_acting') {
    $actingOfficialId = trim((string)($_POST['acting_official_id']   ?? ''));
    if ($actingOfficialId === '') otError('Missing acting_official_id.');

    $actingOfficial = otGetOfficialUser($conn, $actingOfficialId);
    if (!$actingOfficial) otError('Acting official not found.');

    // Find the original official this one was covering for
    $actingForId = '';
    $aRes = $conn->query("SELECT acting_for_id FROM officialinformationtbl WHERE official_id = '{$conn->real_escape_string($actingOfficialId)}' LIMIT 1");
    if ($aRes instanceof mysqli_result) {
        $aRow = $aRes->fetch_assoc();
        $actingForId = trim((string)($aRow['acting_for_id'] ?? ''));
    }

    $inactiveStatusId = otGetStatusId($conn, 'UserAccount', 'Inactive');
    $activeStatusId   = otGetStatusId($conn, 'UserAccount', 'Active');

    $conn->begin_transaction();
    try {
        // Deactivate acting official
        $actUserId = (string)($actingOfficial['user_id'] ?? '');
        if ($inactiveStatusId && $actUserId !== '') {
            $stmt = $conn->prepare("UPDATE useraccountstbl SET status_id_account = ?, updated_at = NOW() WHERE user_id = ? LIMIT 1");
            if ($stmt) { $stmt->bind_param('is', $inactiveStatusId, $actUserId); $stmt->execute(); $stmt->close(); }
        }
        $conn->query("UPDATE officialinformationtbl SET acting_for_id = NULL, acting_until_date = NULL, transition_out_type = 'ActingEnded', transition_out_date = CURDATE(), last_updated = CURRENT_TIMESTAMP WHERE official_id = '{$conn->real_escape_string($actingOfficialId)}'");

        // Restore original official
        if ($actingForId !== '') {
            $originalOfficial = otGetOfficialUser($conn, $actingForId);
            if ($originalOfficial && $activeStatusId) {
                $origUserId = (string)($originalOfficial['user_id'] ?? '');
                $stmt = $conn->prepare("UPDATE useraccountstbl SET status_id_account = ?, updated_at = NOW() WHERE user_id = ? LIMIT 1");
                if ($stmt) { $stmt->bind_param('is', $activeStatusId, $origUserId); $stmt->execute(); $stmt->close(); }
                $conn->query("UPDATE officialinformationtbl SET transition_out_type = NULL, transition_out_date = NULL, transition_out_reason = NULL, last_updated = CURRENT_TIMESTAMP WHERE official_id = '{$conn->real_escape_string($actingForId)}'");
                // Notify original official
                $origName = trim(($originalOfficial['firstname'] ?? '') . ' ' . ($originalOfficial['lastname'] ?? ''));
                otNotifyUser($conn, $origUserId,
                    'System Access Restored — Acting Period Ended',
                    "Dear {$origName},\n\nThe acting assignment for your position has ended. Your system access has been restored. Please log in and contact the IT Administrator if you need assistance.\n\nBarangay San Jose"
                );
            }
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        otError($e->getMessage());
    }

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'official', $actingOfficialId,
        'end_acting', 'acting_for_id', $actingForId, null, 'Quick Action: end acting assignment'
    );

    otJson(['success' => true, 'message' => 'Acting assignment ended. Original official restored.']);
}

// ════════════════════════════════════════════════════════════════════════════
// POST: manual resend notification trigger
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'resend_notification') {
    $batchLabel   = trim((string)($_POST['batch_label']   ?? ''));
    $electionDate = trim((string)($_POST['election_date'] ?? ''));
    if ($batchLabel === '' || $electionDate === '') otError('Missing batch_label or election_date.');

    // Reset all unsent flags so cron picks them up again
    $conn->query("
        UPDATE officialtransitionstbl
        SET notify_3mo_sent = 0, notify_3mo_sent_at = NULL,
            notify_1mo_sent = 0, notify_1mo_sent_at = NULL,
            deactivated_7d_before = 0, deactivated_7d_before_at = NULL,
            notify_post_sent = 0, notify_post_sent_at = NULL
        WHERE batch_label = '{$conn->real_escape_string($batchLabel)}'
          AND election_date = '{$conn->real_escape_string($electionDate)}'
    ");

    insertUnifiedAuditLog($conn, $actorId, $actorRole,
        'OfficialTransitions', 'batch', $batchLabel,
        'resend_notification', 'election_date', null, $electionDate,
        'Manual: reset notification flags for re-trigger'
    );

    otJson(['success' => true, 'message' => 'Notification flags reset. The cron will re-evaluate and send on its next run.']);
}

// ── Fallthrough ───────────────────────────────────────────────────────────────
otError('Unknown action.', 404);
