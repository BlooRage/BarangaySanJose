<?php
session_start();
require_once "../General/connection.php";
require_once "../General/security.php";

requireRoleSession(['Admin', 'Employee']);

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['fetch_audit_logs'])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Not found']);
    exit;
}

try {
    $q = trim((string)($_GET['q'] ?? ''));
    $limit = (int)($_GET['limit'] ?? 200);
    if ($limit <= 0) $limit = 200;
    if ($limit > 500) $limit = 500;

    $bindParams = static function (mysqli_stmt $stmt, string $types, array $params): void {
        if ($types === '') return;
        $refs = [];
        $refs[] = $types;
        foreach ($params as $k => $v) {
            $refs[] = &$params[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
    };

    $sql = "
        SELECT
            a.audit_id,
            a.user_id,
            a.role_access,
            a.module_affected,
            a.target_type,
            a.target_id,
            a.action_type,
            a.field_changed,
            a.old_value,
            a.new_value,
            a.remarks,
            a.action_timestamp,
            oi.firstname AS o_firstname,
            oi.middlename AS o_middlename,
            oi.lastname AS o_lastname,
            oi.suffix AS o_suffix,
            ri.firstname AS r_firstname,
            ri.middlename AS r_middlename,
            ri.lastname AS r_lastname,
            ri.suffix AS r_suffix
        FROM unifiedauditlogstbl a
        LEFT JOIN officialinformationtbl oi
            ON oi.user_id COLLATE utf8mb4_general_ci = a.user_id COLLATE utf8mb4_general_ci
        LEFT JOIN residentinformationtbl ri
            ON ri.user_id COLLATE utf8mb4_general_ci = a.user_id COLLATE utf8mb4_general_ci
    ";

    $params = [];
    $types = '';
    if ($q !== '') {
        $sql .= "
            WHERE
                a.user_id LIKE ?
                OR a.role_access LIKE ?
                OR a.module_affected LIKE ?
                OR a.target_type LIKE ?
                OR a.target_id LIKE ?
                OR a.action_type LIKE ?
                OR a.field_changed LIKE ?
                OR a.old_value LIKE ?
                OR a.new_value LIKE ?
                OR a.remarks LIKE ?
                OR oi.firstname LIKE ?
                OR oi.lastname LIKE ?
                OR ri.firstname LIKE ?
                OR ri.lastname LIKE ?
        ";
        $like = '%' . $q . '%';
        $params = array_fill(0, 14, $like);
        $types = str_repeat('s', 14);
    }

    $sql .= " ORDER BY a.action_timestamp DESC, a.audit_id DESC LIMIT ?";
    $params[] = $limit;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // bind dynamically
    $bindParams($stmt, $types, $params);

    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $formatName = static function ($fn, $mn, $ln, $suf): string {
            $fn = trim((string)$fn);
            $mn = trim((string)$mn);
            $ln = trim((string)$ln);
            $suf = trim((string)$suf);
            if ($fn === '' && $ln === '') return '';
            $mid = $mn !== '' ? (substr($mn, 0, 1) . '. ') : '';
            $name = trim($fn . ' ' . $mid . $ln);
            if ($suf !== '') $name .= ' ' . $suf;
            return trim($name);
        };

        $officialName = $formatName($row['o_firstname'] ?? '', $row['o_middlename'] ?? '', $row['o_lastname'] ?? '', $row['o_suffix'] ?? '');
        $residentName = $formatName($row['r_firstname'] ?? '', $row['r_middlename'] ?? '', $row['r_lastname'] ?? '', $row['r_suffix'] ?? '');
        $row['display_name'] = $officialName !== '' ? $officialName : ($residentName !== '' ? $residentName : '');

        unset(
            $row['o_firstname'], $row['o_middlename'], $row['o_lastname'], $row['o_suffix'],
            $row['r_firstname'], $row['r_middlename'], $row['r_lastname'], $row['r_suffix']
        );
        $rows[] = $row;
    }
    $stmt->close();

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
