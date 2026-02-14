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
            a.action_timestamp
        FROM unifiedauditlogstbl a
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
        ";
        $like = '%' . $q . '%';
        $params = array_fill(0, 10, $like);
        $types = str_repeat('s', 10);
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
        $rows[] = $row;
    }
    $stmt->close();

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
