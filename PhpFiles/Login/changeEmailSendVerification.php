<?php
require_once __DIR__ . '/changeEmailCommon.php';
require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/audit.php';
require_once __DIR__ . '/../EmailHandlers/emailSender.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cem_json(405, ['success' => false, 'message' => 'Method not allowed']);
}

$userId = cem_require_auth();

if (!isset($conn) || !($conn instanceof mysqli)) {
    cem_json(500, ['success' => false, 'message' => 'Database connection unavailable']);
}

// Require identity verification within 10 minutes.
$old = $_SESSION['change_email_old_verified'] ?? null;
if (!is_array($old) || empty($old['verified']) || empty($old['verified_at']) || (time() - (int)$old['verified_at']) > 600) {
    cem_json(403, ['success' => false, 'message' => 'Please verify your identity first.']);
}

$payload = cem_read_payload();
$newEmail = trim((string)($payload['new_email'] ?? ''));

if ($newEmail === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    cem_json(400, ['success' => false, 'message' => 'Please enter a valid email address.']);
}

try {
    $acct = cem_get_user_account($conn, $userId);
    $oldEmail = trim((string)($acct['email'] ?? ''));

    if (strcasecmp($newEmail, $oldEmail) === 0) {
        cem_json(400, ['success' => false, 'message' => 'New email must be different from your current email.']);
    }

    // Ensure email isn't already used by another account.
    $chk = $conn->prepare("SELECT user_id FROM useraccountstbl WHERE email = ? AND user_id <> ? LIMIT 1");
    if ($chk) {
        $chk->bind_param('ss', $newEmail, $userId);
        $chk->execute();
        $res = $chk->get_result();
        $exists = $res && $res->num_rows > 0;
        $chk->close();
        if ($exists) {
            cem_json(400, ['success' => false, 'message' => 'That email address is already registered.']);
        }
    }

    cem_set_manila_tz();

    // Build verification token (raw in email, hash in DB).
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = password_hash($rawToken, PASSWORD_DEFAULT);
    $expiresAt = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');

    $conn->begin_transaction();

    // Update email + mark unverified until link click
    $up = $conn->prepare("UPDATE useraccountstbl SET email = ?, email_verify = 0 WHERE user_id = ? LIMIT 1");
    if (!$up) throw new Exception('Failed to prepare email update.');
    $up->bind_param('ss', $newEmail, $userId);
    if (!$up->execute()) {
        $up->close();
        throw new Exception('Failed to update email.');
    }
    $up->close();

    // Save/overwrite token (same table used for initial verify)
    $ins = $conn->prepare("
      INSERT INTO emailverificationtokens (user_id, token_hash, expires_at)
      VALUES (?, ?, ?)
      ON DUPLICATE KEY UPDATE
        token_hash = VALUES(token_hash),
        expires_at = VALUES(expires_at),
        used_at = NULL
    ");
    if (!$ins) throw new Exception('Failed to prepare token upsert.');
    $ins->bind_param('sss', $userId, $tokenHash, $expiresAt);
    if (!$ins->execute()) {
        $ins->close();
        throw new Exception('Failed to store verification token.');
    }
    $ins->close();

    $conn->commit();

    // Build verification link (same as sendEmailVerify.php)
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $rootPath = dirname(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/BarangaySanJose/PhpFiles/Login/changeEmailSendVerification.php')));
    if ($host === '' || stripos($host, 'localhost') !== false || strpos($host, '127.0.0.1') === 0) {
        $baseUrl = 'https://barangaysanjose-montalban.com';
    } else {
        $baseUrl = rtrim($scheme . "://" . $host . $rootPath, '/');
    }
    $verifyUrl = $baseUrl . "/Guest-End/verifyEmail.php?uid=" . urlencode($userId) . "&token=" . urlencode($rawToken);

    // Send verification email (verify template)
    $smtpConfig = require __DIR__ . '/../General/mailConfigurations.php';
    $emailSender = new EmailSender($smtpConfig);
    $sent = $emailSender->send([
        'to' => $newEmail,
        'subject' => "Barangay San Jose - Verify Your Email",
        'type' => 'verify',
        'data' => [
            'headline' => "MALIGAYANG BATI KA-BARANGAY SAN JOSE!",
            'verifyUrl' => $verifyUrl,
            'buttonText' => "VERIFY EMAIL",
            'expiresNote' => "This link will expire in 15 minutes.",
        ],
    ]);

    if (!$sent) {
        // Best-effort revert so user doesn't get stuck with a wrong/unreachable email.
        try {
            $conn->begin_transaction();
            $rev = $conn->prepare("UPDATE useraccountstbl SET email = ? WHERE user_id = ? LIMIT 1");
            if ($rev) {
                $rev->bind_param('ss', $oldEmail, $userId);
                $rev->execute();
                $rev->close();
            }
            $del = $conn->prepare("UPDATE emailverificationtokens SET used_at = NOW() WHERE user_id = ? LIMIT 1");
            if ($del) {
                $del->bind_param('s', $userId);
                $del->execute();
                $del->close();
            }
            $conn->commit();
        } catch (Throwable $e) {
            @$conn->rollback();
        }

        cem_json(500, ['success' => false, 'message' => 'Unable to send verification email. Please try again.']);
    }

    // Audit (best-effort): do not store actual emails.
    try {
        $actorRole = (string)($_SESSION['role'] ?? 'Resident');
        insertUnifiedAuditLog(
            $conn,
            $userId,
            $actorRole,
            'Resident Profile',
            'UserAccount',
            $userId,
            'EMAIL_CHANGED',
            'email',
            'N/A',
            'N/A',
            null,
            null
        );
    } catch (Throwable $e) {
        // ignore audit failures
    }

    unset($_SESSION['change_email_old_verified']);

    cem_json(200, [
        'success' => true,
        'messageHtml' => "An email verification has been sent. Click the verify button to proceed.<br><b>The verify link will expire in 15 minutes.</b>",
    ]);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        @$conn->rollback();
    }
    cem_json(500, ['success' => false, 'message' => 'Server error. Please try again.']);
}

