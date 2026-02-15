<?php
declare(strict_types=1);

/**
 * Best-effort audit logging.
 * If the table doesn't exist or schema differs, this function must not break the main workflow.
 */

function auditClamp(?string $value, int $maxLen = 2000): ?string {
    if ($value === null) return null;
    $s = trim((string)$value);
    if ($s === '') return null;
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($s, 'UTF-8') > $maxLen) {
            return mb_substr($s, 0, $maxLen, 'UTF-8');
        }
        return $s;
    }
    return strlen($s) > $maxLen ? substr($s, 0, $maxLen) : $s;
}

function insertUnifiedAuditLog(
    mysqli $conn,
    ?string $userId,
    string $roleAccess,
    string $moduleAffected,
    string $targetType,
    string $targetId,
    string $actionType,
    ?string $fieldChanged = null,
    ?string $oldValue = null,
    ?string $newValue = null,
    ?string $remarks = null,
    ?int $statusIdAudit = null
): void {
    $stmt = $conn->prepare("
        INSERT INTO unifiedauditlogstbl
            (user_id, role_access, module_affected, target_type, target_id, action_type, field_changed, old_value, new_value, remarks, status_id_audit)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        return;
    }

    $userId = auditClamp($userId, 12);
    $roleAccess = (string)auditClamp($roleAccess, 64);
    $moduleAffected = (string)auditClamp($moduleAffected, 128);
    $targetType = (string)auditClamp($targetType, 64);
    $targetId = (string)auditClamp($targetId, 64);
    $actionType = (string)auditClamp($actionType, 64);

    $fieldChanged = auditClamp($fieldChanged, 128);
    $oldValue = auditClamp($oldValue, 2000);
    $newValue = auditClamp($newValue, 2000);
    $remarks = auditClamp($remarks, 2000);

    $statusIdAudit = $statusIdAudit !== null ? (int)$statusIdAudit : null;

    // "i" cannot bind null reliably in some configs; bind as string and let MySQL coerce where needed.
    $statusIdStr = $statusIdAudit !== null ? (string)$statusIdAudit : null;

    $stmt->bind_param(
        "sssssssssss",
        $userId,
        $roleAccess,
        $moduleAffected,
        $targetType,
        $targetId,
        $actionType,
        $fieldChanged,
        $oldValue,
        $newValue,
        $remarks,
        $statusIdStr
    );

    $stmt->execute();
    $stmt->close();
}
