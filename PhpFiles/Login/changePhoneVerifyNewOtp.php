<?php
require_once __DIR__ . '/changePhoneCommon.php';
require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cpn_json(405, ['success' => false, 'message' => 'Method not allowed']);
}

$userId = cpn_require_auth();

if (!isset($conn) || !($conn instanceof mysqli)) {
    cpn_json(500, ['success' => false, 'message' => 'Database connection unavailable']);
}

$payload = cpn_read_payload();
$otp = trim((string)($payload['otp'] ?? ''));
$newPhone10 = cpn_sanitize_phone10((string)($payload['new_phone'] ?? ''));

// Require old verification within 10 minutes
$old = $_SESSION['change_phone_old_verified'] ?? null;
if (!is_array($old) || empty($old['verified']) || empty($old['verified_at']) || (time() - (int)$old['verified_at']) > 600) {
    cpn_json(403, ['success' => false, 'message' => 'Please verify your identity first.']);
}

$pending = $_SESSION['change_phone_pending_new_phone'] ?? null;
if (!is_array($pending) || empty($pending['phone10'])) {
    cpn_json(400, ['success' => false, 'message' => 'No pending phone number change found. Please start again.']);
}

$pendingPhone10 = cpn_sanitize_phone10((string)$pending['phone10']);
if ($newPhone10 === '' || $newPhone10 !== $pendingPhone10) {
    cpn_json(400, ['success' => false, 'message' => 'Phone number mismatch. Please start again.']);
}

if (!cpn_is_valid_phone10($newPhone10)) {
    cpn_json(400, ['success' => false, 'message' => 'Phone number must start with 9 and be exactly 10 digits.']);
}

try {
    // Verify OTP for new phone
    cpn_verify_latest_otp($conn, $userId, $newPhone10, 'change_phone_new_phone', $otp);

    // Ensure phone isn't already used by another account (re-check at commit time)
    $phoneHash = pii_lookup_hash($newPhone10, 'useraccount.phone');
    $chk = $conn->prepare("SELECT user_id FROM useraccountstbl WHERE phone_lookup_hash = ? AND user_id <> ? LIMIT 1");
    if ($chk) {
        $chk->bind_param('ss', $phoneHash, $userId);
        $chk->execute();
        $res = $chk->get_result();
        $exists = $res && $res->num_rows > 0;
        $chk->close();
        if ($exists) {
            cpn_json(400, ['success' => false, 'message' => 'That phone number is already registered.']);
        }
    }

    $prepared = pii_prepare_useraccount_contacts('', $newPhone10);
    $up = $conn->prepare("UPDATE useraccountstbl SET phone_number = ?, phone_lookup_hash = ?, phoneNum_verify = 1 WHERE user_id = ?");
    if (!$up) {
        throw new Exception('Failed to prepare phone update.');
    }
    $up->bind_param('sss', $prepared['phone_number'], $prepared['phone_lookup_hash'], $userId);
    if (!$up->execute()) {
        $up->close();
        throw new Exception('Failed to update phone number.');
    }
    $up->close();

    // Audit (best-effort): don't store phone numbers in logs.
    try {
        $actorRole = (string)($_SESSION['role'] ?? 'Resident');
        insertUnifiedAuditLog(
            $conn,
            $userId,
            $actorRole,
            'Resident Profile',
            'UserAccount',
            $userId,
            'PHONE_NUMBER_CHANGED',
            'phone_number',
            'N/A',
            'N/A',
            null,
            null
        );
    } catch (Throwable $e) {
        // ignore audit failures
    }

    unset($_SESSION['change_phone_old_verified']);
    unset($_SESSION['change_phone_pending_new_phone']);

    cpn_json(200, ['success' => true, 'message' => 'Mobile number changed successfully.']);
} catch (Throwable $e) {
    cpn_json(500, ['success' => false, 'message' => 'Server error. Please try again.']);
}
