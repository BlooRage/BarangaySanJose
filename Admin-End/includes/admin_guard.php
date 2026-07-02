<?php
require_once __DIR__ . "/../../PhpFiles/General/security.php";
require_once __DIR__ . "/../../PhpFiles/General/adminModulePermissions.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . "/../../PhpFiles/General/officialInviteCommon.php";

// Enforce auth + 30-min inactivity timeout for admin-panel pages.
requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin'], false);
$adminGuardLightMode = defined('ADMIN_GUARD_LIGHT') && ADMIN_GUARD_LIGHT === true;

$roleNorm = strtolower(trim((string)($_SESSION['role'] ?? '')));
if ($roleNorm === 'officials') $roleNorm = 'official';
if ($roleNorm === 'personnels') $roleNorm = 'personnel';
if ($roleNorm === 'admin') $roleNorm = 'official';
if ($roleNorm === 'employee') $roleNorm = 'personnel';

$adminGuardNeedsDb = !$adminGuardLightMode || $roleNorm !== 'superadmin';
if ($adminGuardNeedsDb) {
    require_once __DIR__ . "/../../PhpFiles/General/connection.php";
}

$currentUserId = (string)($_SESSION['user_id'] ?? '');
$currentOfficialAccount = null;

if ($adminGuardNeedsDb && isset($conn) && $conn instanceof mysqli) {
    amp_ensure_permission_storage($conn);
}

if ($currentUserId !== '' && isset($conn) && $conn instanceof mysqli) {
    $currentOfficialAccount = amp_get_official_account_by_user_id($conn, $currentUserId);
    if ($currentOfficialAccount) {
        amp_force_expired_account_inactive($conn, $currentOfficialAccount);

        if (amp_is_account_expired($currentOfficialAccount)) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params['path'], $params['domain'],
                    $params['secure'], $params['httponly']
                );
            }
            session_destroy();
            header('Location: ' . appUrl('/PhpFiles/Login/logout.php'));
            exit;
        }
    }
}

if (in_array($roleNorm, ['official', 'personnel'], true) && isset($conn) && $conn instanceof mysqli) {
    oi_ensure_invite_table($conn);
    if ($currentUserId !== '') {
        $stmt = $conn->prepare("
            SELECT status
            FROM officialinvitetbl
            WHERE user_id = ?
            ORDER BY invite_id DESC
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param("s", $currentUserId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $inviteStatus = strtolower(trim((string)($row['status'] ?? '')));
            if ($inviteStatus !== 'completed') {
                header('Location: ' . appUrl('/official-onboarding'));
                exit;
            }
        }
    }
}

if ($currentUserId !== '' && isset($conn) && $conn instanceof mysqli) {
    $requiredPermissionKey = amp_resolve_request_permission_key();
    if ($requiredPermissionKey !== null) {
        $allowedPermissions = amp_get_allowed_permission_keys($conn, $currentUserId, (string)($_SESSION['role'] ?? ''));
        if (!amp_permission_key_allowed($allowedPermissions, $requiredPermissionKey)) {
            $fallbackPath = amp_get_first_allowed_path($allowedPermissions);
            if ($fallbackPath !== '') {
                header('Location: ' . appUrl('/' . ltrim($fallbackPath, '/')));
                exit;
            }

            http_response_code(403);
            echo 'Access denied.';
            exit;
        }
    }
}
