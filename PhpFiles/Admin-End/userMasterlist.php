<?php
session_start();
require_once "../General/connection.php";
require_once "../General/security.php";

requireRoleSession(['SuperAdmin']);

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['fetch_user_masterlist'])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Not found']);
    exit;
}

function normalizeRole(string $role): string {
    $k = strtolower(trim($role));
    if ($k === 'officials' || $k === 'official') return 'Official';
    if ($k === 'personnels' || $k === 'personnel') return 'Personnel';
    if ($k === 'admin' || $k === 'employee') return 'Official';
    if ($k === 'superadmin') return 'SuperAdmin';
    if ($k === 'resident') return 'Resident';
    return trim($role) !== '' ? trim($role) : 'Unknown';
}

try {
    $q = trim((string)($_GET['q'] ?? ''));
    $roleFilter = normalizeRole((string)($_GET['role'] ?? 'ALL'));
    $verificationFilter = strtolower(trim((string)($_GET['verification'] ?? 'ALL')));
    $limit = (int)($_GET['limit'] ?? 500);
    if ($limit <= 0) $limit = 500;
    if ($limit > 1000) $limit = 1000;

    $sql = "
        SELECT
            ua.user_id,
            ua.email,
            ua.phone_number,
            ua.email_verify,
            ua.phoneNum_verify,
            ua.role_access,
            ua.account_created,
            ua.last_login,
            COALESCE(sa.status_name, CONCAT('Status #', ua.status_id_account)) AS account_status,
            ri.firstname AS r_firstname,
            ri.middlename AS r_middlename,
            ri.lastname AS r_lastname,
            ri.suffix AS r_suffix,
            oi.firstname AS o_firstname,
            oi.middlename AS o_middlename,
            oi.lastname AS o_lastname,
            oi.suffix AS o_suffix
        FROM useraccountstbl ua
        LEFT JOIN statuslookuptbl sa ON sa.status_id = ua.status_id_account
        LEFT JOIN residentinformationtbl ri ON ri.user_id COLLATE utf8mb4_general_ci = ua.user_id COLLATE utf8mb4_general_ci
        LEFT JOIN officialinformationtbl oi ON oi.user_id COLLATE utf8mb4_general_ci = ua.user_id COLLATE utf8mb4_general_ci
    ";

    $params = [];
    $types = '';
    if ($q !== '') {
        $sql .= "
            WHERE (
                ua.user_id LIKE ?
                OR ua.email LIKE ?
                OR ua.phone_number LIKE ?
                OR ua.role_access LIKE ?
                OR ri.firstname LIKE ?
                OR ri.lastname LIKE ?
                OR oi.firstname LIKE ?
                OR oi.lastname LIKE ?
            )
        ";
        $like = '%' . $q . '%';
        $params = [$like, $like, $like, $like, $like, $like, $like, $like];
        $types = 'ssssssss';
    }

    $sql .= " ORDER BY ua.account_created DESC, ua.user_id DESC LIMIT ?";
    $params[] = $limit;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);

    $refs = [];
    $refs[] = $types;
    foreach ($params as $k => $v) {
        $refs[] = &$params[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);

    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    $pendingCount = 0;
    while ($row = $res->fetch_assoc()) {
        $mkName = static function ($fn, $mn, $ln, $suf): string {
            $fn = trim((string)$fn);
            $mn = trim((string)$mn);
            $ln = trim((string)$ln);
            $suf = trim((string)$suf);
            if ($fn === '' && $ln === '') return '';
            $mi = $mn !== '' ? substr($mn, 0, 1) . '. ' : '';
            return trim($fn . ' ' . $mi . $ln . ($suf !== '' ? ' ' . $suf : ''));
        };

        $nameOfficial = $mkName($row['o_firstname'] ?? '', $row['o_middlename'] ?? '', $row['o_lastname'] ?? '', $row['o_suffix'] ?? '');
        $nameResident = $mkName($row['r_firstname'] ?? '', $row['r_middlename'] ?? '', $row['r_lastname'] ?? '', $row['r_suffix'] ?? '');
        $displayName = $nameOfficial !== '' ? $nameOfficial : ($nameResident !== '' ? $nameResident : '—');

        $normalizedRole = normalizeRole((string)($row['role_access'] ?? ''));
        $isVerified = ((int)($row['email_verify'] ?? 0) === 1) && ((int)($row['phoneNum_verify'] ?? 0) === 1);
        $verification = $isVerified ? 'Verified' : 'Pending';

        if ($verification === 'Pending') {
            $pendingCount++;
        }

        if ($roleFilter !== 'ALL' && $normalizedRole !== $roleFilter) {
            continue;
        }
        if ($verificationFilter === 'pending' && $verification !== 'Pending') {
            continue;
        }
        if ($verificationFilter === 'verified' && $verification !== 'Verified') {
            continue;
        }

        $rows[] = [
            'user_id' => (string)$row['user_id'],
            'display_name' => $displayName,
            'email' => (string)$row['email'],
            'phone_number' => (string)$row['phone_number'],
            'role_access' => $normalizedRole,
            'account_status' => (string)$row['account_status'],
            'verification_status' => $verification,
            'account_created' => (string)($row['account_created'] ?? ''),
            'last_login' => (string)($row['last_login'] ?? ''),
        ];
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'data' => $rows,
        'pending_count' => $pendingCount,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
