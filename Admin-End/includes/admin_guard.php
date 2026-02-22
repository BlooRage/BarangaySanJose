<?php
require_once __DIR__ . "/../../PhpFiles/General/security.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . "/../../PhpFiles/General/officialInviteCommon.php";

// Enforce auth + 30-min inactivity timeout for admin-panel pages.
requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin', 'Employee'], false);

$roleNorm = strtolower(trim((string)($_SESSION['role'] ?? '')));
if ($roleNorm === 'officials') $roleNorm = 'official';
if ($roleNorm === 'personnels') $roleNorm = 'personnel';
if ($roleNorm === 'admin') $roleNorm = 'official';
if ($roleNorm === 'employee') $roleNorm = 'official';

if (in_array($roleNorm, ['official', 'personnel'], true) && isset($conn) && $conn instanceof mysqli) {
    oi_ensure_invite_table($conn);
    $userId = (string)($_SESSION['user_id'] ?? '');
    if ($userId !== '') {
        $stmt = $conn->prepare("
            SELECT status
            FROM officialinvitetbl
            WHERE user_id = ?
            ORDER BY invite_id DESC
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param("s", $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $inviteStatus = strtolower(trim((string)($row['status'] ?? '')));
            if ($inviteStatus !== 'completed') {
                header('Location: ' . appUrl('/Guest-End/official_onboarding.php'));
                exit;
            }
        }
    }
}
