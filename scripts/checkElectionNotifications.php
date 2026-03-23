<?php
declare(strict_types=1);

/**
 * checkElectionNotifications.php
 *
 * Cron script — run daily at midnight.
 * Crontab entry:
 *   0 0 * * * /usr/bin/php /path/to/BarangaySanJose/scripts/checkElectionNotifications.php >> /tmp/election_notif.log 2>&1
 *
 * Checks all election batches and fires four timed actions:
 *   1. ≈90 days before  → Notify SuperAdmin/IT Admin to prepare
 *   2. ≈30 days before  → Notify all outgoing officials (access will change)
 *   3. ≈7  days before  → Auto-deactivate all outgoing officials in the batch
 *   4. Election passed  → Notify SuperAdmin/IT Admin to encode newly elected officials
 */

// Bootstrap
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['user_id']       = 'CRON';
$_SESSION['role']          = 'SuperAdmin';
$_SESSION['last_activity'] = time();

require_once __DIR__ . '/../PhpFiles/General/connection.php';
require_once __DIR__ . '/../PhpFiles/General/audit.php';

// ── Helpers ───────────────────────────────────────────────────────────────────

function cronLog(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

function cronSendEmail(string $email, string $subject, string $body): void {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return;
    try {
        $autoload = __DIR__ . '/../composer-email-handler/vendor/autoload.php';
        if (!file_exists($autoload)) return;
        require_once $autoload;
        if (!class_exists('EmailSender')) return;
        $mailConfig = require __DIR__ . '/../PhpFiles/General/mailConfigurations.php';
        $sender = new EmailSender($mailConfig);
        $sender->send([
            'to'      => $email,
            'subject' => $subject,
            'body'    => nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')),
        ]);
    } catch (\Throwable $e) {
        cronLog("Email failed to {$email}: " . $e->getMessage());
    }
}

function cronSendSMS(string $phone, string $message): void {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if ($phone === '') return;
    try {
        $smsFile = __DIR__ . '/../PhpFiles/General/sendSMS.php';
        if (!file_exists($smsFile)) return;
        require_once $smsFile;
        if (function_exists('sendSMS')) @sendSMS($phone, $message);
    } catch (\Throwable) { /* best-effort */ }
}

function cronNotifyUser(mysqli $conn, string $userId, string $subject, string $message): void {
    $stmt = $conn->prepare("SELECT email, phone_number FROM useraccountstbl WHERE user_id = ? LIMIT 1");
    if (!$stmt) return;
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $acct = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$acct) return;
    $email = trim((string)($acct['email'] ?? ''));
    $phone = trim((string)($acct['phone_number'] ?? ''));
    if ($email !== '') cronSendEmail($email, $subject, $message);
    if ($phone !== '') cronSendSMS($phone, $message);
}

/**
 * Fetch user_ids of all SuperAdmin / IT Admin accounts.
 */
function cronGetAdminUserIds(mysqli $conn): array {
    $res = $conn->query("
        SELECT user_id FROM useraccountstbl
        WHERE role_access IN ('SuperAdmin','ITAdmin')
        AND status_id_account IN (
            SELECT status_id FROM statuslookuptbl WHERE status_name = 'Active'
        )
    ");
    $ids = [];
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $ids[] = (string)($row['user_id'] ?? '');
        }
        $res->close();
    }
    return array_filter($ids);
}

/**
 * Fetch user_ids of all officials tied to a batch's outgoing positions.
 */
function cronGetBatchOfficialUserIds(mysqli $conn, string $batchLabel): array {
    $escaped = $conn->real_escape_string($batchLabel);
    $res = $conn->query("
        SELECT ua.user_id
        FROM officialtransitionstbl t
        INNER JOIN officialinformationtbl oi ON oi.official_id = t.outgoing_official_id
        INNER JOIN useraccountstbl ua ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
        WHERE t.batch_label = '{$escaped}'
          AND t.outgoing_official_id IS NOT NULL
          AND t.status NOT IN ('Cancelled','Completed')
    ");
    $ids = [];
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) $ids[] = (string)($row['user_id'] ?? '');
        $res->close();
    }
    return array_filter($ids);
}

function cronGetStatusId(mysqli $conn, string $type, string $name): ?int {
    $stmt = $conn->prepare("SELECT status_id FROM statuslookuptbl WHERE status_type = ? AND status_name = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('ss', $type, $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['status_id'] : null;
}

// ── Verify table exists ────────────────────────────────────────────────────────
$tableCheck = $conn->query("SHOW TABLES LIKE 'officialtransitionstbl'");
if (!($tableCheck instanceof mysqli_result) || $tableCheck->num_rows === 0) {
    cronLog('officialtransitionstbl not found. Run the migration first. Exiting.');
    exit(1);
}

// ── Fetch distinct batches with election dates ─────────────────────────────────
$batches = [];
$bRes = $conn->query("
    SELECT batch_label, election_date,
           MAX(notify_3mo_sent)       AS n3,
           MAX(notify_1mo_sent)       AS n1,
           MAX(deactivated_7d_before) AS n7,
           MAX(notify_post_sent)      AS np
    FROM officialtransitionstbl
    WHERE election_date IS NOT NULL
      AND batch_label IS NOT NULL AND batch_label <> ''
      AND status NOT IN ('Cancelled')
    GROUP BY batch_label, election_date
");
if ($bRes instanceof mysqli_result) {
    while ($r = $bRes->fetch_assoc()) $batches[] = $r;
    $bRes->close();
}

if (empty($batches)) {
    cronLog('No election batches found. Nothing to do.');
    exit(0);
}

cronLog('Found ' . count($batches) . ' election batch(es) to evaluate.');

$inactiveStatusId = cronGetStatusId($conn, 'UserAccount', 'Inactive');
$today = new DateTimeImmutable('today');

foreach ($batches as $batch) {
    $batchLabel   = (string)($batch['batch_label']   ?? '');
    $electionDate = (string)($batch['election_date'] ?? '');
    $n3  = (int)($batch['n3'] ?? 0);
    $n1  = (int)($batch['n1'] ?? 0);
    $n7  = (int)($batch['n7'] ?? 0);
    $np  = (int)($batch['np'] ?? 0);

    try {
        $eDate = new DateTimeImmutable($electionDate);
    } catch (\Throwable) {
        cronLog("Invalid election_date for batch '{$batchLabel}': {$electionDate}. Skipping.");
        continue;
    }

    $daysUntil = (int)$today->diff($eDate)->days * ($today <= $eDate ? 1 : -1);
    $edFmt     = $eDate->format('F d, Y');

    cronLog("Batch: '{$batchLabel}' | Election: {$edFmt} | Days: {$daysUntil}");

    $escaped = $conn->real_escape_string($batchLabel);
    $adminIds = cronGetAdminUserIds($conn);

    // ────────────────────────────────────────────────────────────────────────
    // TRIGGER 1: ~90 days before → notify admins to prepare
    // ────────────────────────────────────────────────────────────────────────
    if (!$n3 && $daysUntil >= 85 && $daysUntil <= 95) {
        cronLog("  → Trigger 1 (3-month notice) for '{$batchLabel}'");

        $subject = "Action Required: Barangay Election in ~3 Months ({$batchLabel})";
        $message = "Good day,\n\nThe {$batchLabel} is scheduled on {$edFmt}, approximately 3 months from now.\n\nPlease log in to the Official Transition Module to:\n• Confirm or update the election date\n• Prepare the list of positions for transition\n• Begin encoding candidates for each position\n\nBarangay San Jose — IT Administrator";

        foreach ($adminIds as $uid) {
            cronNotifyUser($conn, $uid, $subject, $message);
        }

        $conn->query("UPDATE officialtransitionstbl SET notify_3mo_sent = 1, notify_3mo_sent_at = NOW() WHERE batch_label = '{$escaped}' AND election_date = '{$electionDate}'");
        cronLog("  ✓ 3-month notices sent to " . count($adminIds) . " admin(s).");

        insertUnifiedAuditLog($conn, 'CRON', 'System', 'OfficialTransitions', 'batch', $batchLabel, 'notify_3mo_sent', null, null, 'sent', "Election: {$electionDate}");
    }

    // ────────────────────────────────────────────────────────────────────────
    // TRIGGER 2: ~30 days before → notify outgoing officials
    // ────────────────────────────────────────────────────────────────────────
    if (!$n1 && $daysUntil >= 27 && $daysUntil <= 33) {
        cronLog("  → Trigger 2 (1-month notice) for '{$batchLabel}'");

        $subject = "Notice: Upcoming Barangay Election — System Access Update";
        $message = "Good day,\n\nThe {$batchLabel} is scheduled on {$edFmt}, approximately 1 month from now.\n\nFollowing the election results, your system access and role assignment may be subject to change. Your account will be temporarily deactivated 1 week before the election as part of the official transition process.\n\nPlease coordinate with the Barangay IT Administrator for any concerns.\n\nBarangay San Jose";

        $officialUserIds = cronGetBatchOfficialUserIds($conn, $batchLabel);
        foreach ($officialUserIds as $uid) {
            cronNotifyUser($conn, $uid, $subject, $message);
        }

        $conn->query("UPDATE officialtransitionstbl SET notify_1mo_sent = 1, notify_1mo_sent_at = NOW() WHERE batch_label = '{$escaped}' AND election_date = '{$electionDate}'");
        cronLog("  ✓ 1-month notices sent to " . count($officialUserIds) . " official(s).");

        insertUnifiedAuditLog($conn, 'CRON', 'System', 'OfficialTransitions', 'batch', $batchLabel, 'notify_1mo_sent', null, null, 'sent', "Election: {$electionDate}");
    }

    // ────────────────────────────────────────────────────────────────────────
    // TRIGGER 3: ~7 days before → auto-deactivate outgoing officials
    // ────────────────────────────────────────────────────────────────────────
    if (!$n7 && $daysUntil >= 5 && $daysUntil <= 9) {
        cronLog("  → Trigger 3 (7-day deactivation) for '{$batchLabel}'");

        $deactivatedCount = 0;
        if ($inactiveStatusId) {
            // Fetch all outgoing officials in this batch
            $offRes = $conn->query("
                SELECT t.outgoing_official_id, oi.user_id,
                       CONCAT(oi.firstname, ' ', oi.lastname) AS full_name,
                       COALESCE(oi.position_access, oi.role_access) AS position
                FROM officialtransitionstbl t
                INNER JOIN officialinformationtbl oi ON oi.official_id = t.outgoing_official_id
                WHERE t.batch_label = '{$escaped}'
                  AND t.election_date = '{$electionDate}'
                  AND t.outgoing_official_id IS NOT NULL
                  AND t.status NOT IN ('Cancelled','Completed')
            ");

            if ($offRes instanceof mysqli_result) {
                while ($off = $offRes->fetch_assoc()) {
                    $uid      = (string)($off['user_id']    ?? '');
                    $name     = (string)($off['full_name']  ?? '');
                    $position = (string)($off['position']   ?? '');

                    if ($uid === '') continue;

                    // Deactivate account
                    $upStmt = $conn->prepare("UPDATE useraccountstbl SET status_id_account = ?, updated_at = NOW() WHERE user_id = ? LIMIT 1");
                    if ($upStmt) {
                        $upStmt->bind_param('is', $inactiveStatusId, $uid);
                        $upStmt->execute();
                        $upStmt->close();
                        $deactivatedCount++;
                    }

                    // Notify the official
                    $subject = "System Access Update — Barangay Election ({$batchLabel})";
                    $message = "Dear {$name},\n\nYour Barangay San Jose system access has been temporarily deactivated as part of the official election transition process for the {$batchLabel} scheduled on {$edFmt}.\n\nIf you are re-elected, your access will be restored by the IT Administrator following the official results.\n\nFor concerns, please contact your Barangay IT Administrator.\n\nBarangay San Jose";

                    cronNotifyUser($conn, $uid, $subject, $message);
                    cronLog("    Deactivated: {$name} ({$position})");

                    insertUnifiedAuditLog($conn, 'CRON', 'System', 'OfficialTransitions', 'official', $uid, 'auto_deactivate_7d', 'account_status', 'Active', 'Inactive', "Batch: {$batchLabel} | Election: {$electionDate}");
                }
                $offRes->close();
            }
        } else {
            cronLog("  ⚠ Inactive status ID not found in statuslookuptbl. Deactivation skipped.");
        }

        $conn->query("UPDATE officialtransitionstbl SET deactivated_7d_before = 1, deactivated_7d_before_at = NOW() WHERE batch_label = '{$escaped}' AND election_date = '{$electionDate}'");
        cronLog("  ✓ 7-day deactivation done. Deactivated {$deactivatedCount} official(s).");
    }

    // ────────────────────────────────────────────────────────────────────────
    // TRIGGER 4: Election has passed (0 to 30 days after) → notify admins
    // ────────────────────────────────────────────────────────────────────────
    if (!$np && $daysUntil <= 0 && $daysUntil >= -30) {
        cronLog("  → Trigger 4 (post-election notice) for '{$batchLabel}'");

        $subject = "Action Required: Encode Newly Elected Officials ({$batchLabel})";
        $message = "Good day,\n\nThe {$batchLabel} has concluded (Election date: {$edFmt}).\n\nPlease log in to the Official Transition Module to:\n• Review transition outcomes for each position\n• Encode the information and credentials of newly elected officials\n• Select winners and complete the transition process\n\nRe-elected officials will need their accounts reactivated.\n\nBarangay San Jose — IT Administrator";

        foreach ($adminIds as $uid) {
            cronNotifyUser($conn, $uid, $subject, $message);
        }

        $conn->query("UPDATE officialtransitionstbl SET notify_post_sent = 1, notify_post_sent_at = NOW() WHERE batch_label = '{$escaped}' AND election_date = '{$electionDate}'");
        cronLog("  ✓ Post-election notices sent to " . count($adminIds) . " admin(s).");

        insertUnifiedAuditLog($conn, 'CRON', 'System', 'OfficialTransitions', 'batch', $batchLabel, 'notify_post_sent', null, null, 'sent', "Election: {$electionDate}");
    }
}

cronLog('Done.');
exit(0);
