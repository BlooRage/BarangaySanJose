<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../PhpFiles/General/security.php";
require_once __DIR__ . "/../PhpFiles/General/connection.php";
require_once __DIR__ . "/../PhpFiles/General/officialInviteCommon.php";
require_once __DIR__ . "/../PhpFiles/General/sendSMS.php";
require_once __DIR__ . "/../PhpFiles/EmailHandlers/emailSender.php";
require_once __DIR__ . "/../PhpFiles/General/mailConfigurations.php";
require_once __DIR__ . "/../PhpFiles/General/audit.php";

requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin', 'Employee'], false);
oi_ensure_invite_table($conn);

$flash = ['type' => '', 'message' => ''];
if (!empty($_SESSION['official_invite_flash']) && is_array($_SESSION['official_invite_flash'])) {
    $flash = $_SESSION['official_invite_flash'];
    unset($_SESSION['official_invite_flash']);
}

$departmentOptions = [
    'Office of the Punong Barangay',
    'Sangguniang Barangay',
    'Barangay Secretariat',
    'Barangay Treasurer Office',
    'Barangay Health Office',
    'Barangay Peace and Order',
    'Barangay Social Services',
    'Barangay Operations',
];
$positionAccessOptions = [
    'Barangay Secretary',
    'Certificate Issuance',
    'Treasury',
    'Records Management',
    'Public Assistance Desk',
    'Monitoring',
    'Barangay Police',
    'Area Coordinator',
];
$deptRes = $conn->query("
    SELECT DISTINCT department
    FROM officialinformationtbl
    WHERE department IS NOT NULL AND TRIM(department) <> ''
    ORDER BY department ASC
");
if ($deptRes) {
    while ($d = $deptRes->fetch_assoc()) {
        $val = trim((string)($d['department'] ?? ''));
        if ($val !== '' && !in_array($val, $departmentOptions, true)) {
            $departmentOptions[] = $val;
        }
    }
}
sort($departmentOptions);

function set_invite_flash(string $type, string $message): void {
    $_SESSION['official_invite_flash'] = ['type' => $type, 'message' => $message];
}

function redirect_self(): void {
    header("Location: OfficialInvites.php");
    exit;
}

function fetch_invite_by_id(mysqli $conn, int $inviteId): ?array {
    $stmt = $conn->prepare("SELECT * FROM officialinvitetbl WHERE invite_id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param("i", $inviteId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function verify_actor_password_or_fail(mysqli $conn, string $actorUserId, bool $isSuperAdminActor, string $actorPassword): void {
    if (!$isSuperAdminActor) {
        return;
    }
    if (trim($actorPassword) === '') {
        set_invite_flash('danger', 'Please enter your current password to authorize this action.');
        redirect_self();
    }
    $pwdStmt = $conn->prepare("SELECT password_hash FROM useraccountstbl WHERE user_id = ? LIMIT 1");
    if (!$pwdStmt) {
        set_invite_flash('danger', 'Unable to verify your password right now.');
        redirect_self();
    }
    $pwdStmt->bind_param("s", $actorUserId);
    $pwdStmt->execute();
    $pwdRow = $pwdStmt->get_result()->fetch_assoc();
    $pwdStmt->close();
    $actorHash = (string)($pwdRow['password_hash'] ?? '');
    if ($actorHash === '' || !password_verify($actorPassword, $actorHash)) {
        set_invite_flash('danger', 'Authorization failed: incorrect current password.');
        redirect_self();
    }
}

function send_invite_email(array $invite, string $rawToken): bool {
    $smtpConfig = require __DIR__ . "/../PhpFiles/General/mailConfigurations.php";
    $sender = new EmailSender($smtpConfig);
    $baseUrl = oi_app_base_url();
    $link = $baseUrl . "/Guest-End/official_onboarding.php?invite=" . urlencode($rawToken);
    $fullName = trim((string)($invite['firstname'] ?? '') . ' ' . (string)($invite['lastname'] ?? ''));
    $role = (string)($invite['role_access'] ?? 'Official');

    $html = ''
        . '<p>Hello ' . htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') . ',</p>'
        . '<p>You were invited to create your Barangay San Jose ' . htmlspecialchars($role, ENT_QUOTES, 'UTF-8') . ' account.</p>'
        . '<p><strong>STRICTLY ONE-TIME ACCESS:</strong> this invite link can only be used once and will expire automatically.</p>'
        . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:10px 16px;background:#0d6efd;color:#fff;text-decoration:none;border-radius:6px;">Open Invite</a></p>'
        . '<p>If the button does not work, use this link:<br>' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '</p>';

    return $sender->send([
        'to' => (string)$invite['invite_email'],
        'subject' => 'Barangay San Jose Official Account Invite',
        'bodyHtml' => $html,
        'bodyText' => "You were invited to create your account.\nSTRICTLY ONE-TIME ACCESS.\nOpen: {$link}",
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    $actorUserId = (string)($_SESSION['user_id'] ?? '');
    $actorRole = (string)($_SESSION['role'] ?? 'SuperAdmin');
    $isSuperAdminActor = in_array($actorRole, ['SuperAdmin'], true);

    if ($action === 'create_invite') {
        $lastName = trim((string)($_POST['last_name'] ?? ''));
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $middleName = trim((string)($_POST['middle_name'] ?? ''));
        $suffix = trim((string)($_POST['suffix'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $phone10 = oi_normalize_phone10((string)($_POST['phone_number'] ?? ''));
        $roleAccess = trim((string)($_POST['role_access'] ?? ''));
        $positionAccess = trim((string)($_POST['position_access'] ?? ''));
        $department = trim((string)($_POST['department'] ?? ''));
        $actorPassword = (string)($_POST['actor_password'] ?? '');

        if ($lastName === '' || $firstName === '' || $email === '' || $roleAccess === '' || $department === '') {
            set_invite_flash('danger', 'Last name, first name, email, role, and department are required.');
            redirect_self();
        }
        if (!preg_match('/^[A-Za-z][A-Za-z .\'-]{0,99}$/', $lastName) || !preg_match('/^[A-Za-z][A-Za-z .\'-]{0,99}$/', $firstName)) {
            set_invite_flash('danger', 'Invalid name format.');
            redirect_self();
        }
        if ($middleName !== '' && !preg_match('/^[A-Za-z][A-Za-z .\'-]{0,99}$/', $middleName)) {
            set_invite_flash('danger', 'Invalid middle name format.');
            redirect_self();
        }
        if ($suffix !== '' && !preg_match('/^[A-Za-z0-9 .\'-]{1,20}$/', $suffix)) {
            set_invite_flash('danger', 'Invalid suffix format.');
            redirect_self();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_invite_flash('danger', 'Invalid email address.');
            redirect_self();
        }
        if (!oi_is_valid_phone10($phone10)) {
            set_invite_flash('danger', 'Invalid mobile number. Use 9XXXXXXXXX.');
            redirect_self();
        }
        if ($roleAccess === 'Officials') {
            $roleAccess = 'Official';
        } elseif ($roleAccess === 'Personnels') {
            $roleAccess = 'Personnel';
        }
        if (!in_array($roleAccess, ['Official', 'Personnel', 'SuperAdmin'], true)) {
            set_invite_flash('danger', 'Role must be Official, Personnel, or SuperAdmin.');
            redirect_self();
        }
        if (in_array($roleAccess, ['Official', 'Personnel'], true) && $positionAccess === '') {
            set_invite_flash('danger', 'Position access is required for Official and Personnel.');
            redirect_self();
        }
        if ($roleAccess === 'SuperAdmin' && $positionAccess === '') {
            $positionAccess = 'System Administrator';
        }

        verify_actor_password_or_fail($conn, $actorUserId, $isSuperAdminActor, $actorPassword);

        $exists = $conn->prepare("SELECT user_id FROM useraccountstbl WHERE email = ? OR phone_number = ? LIMIT 1");
        if ($exists) {
            $exists->bind_param("ss", $email, $phone10);
            $exists->execute();
            $hit = $exists->get_result()->fetch_assoc();
            $exists->close();
            if ($hit) {
                set_invite_flash('danger', 'Email or phone number is already tied to an existing account.');
                redirect_self();
            }
        }

        $token = oi_generate_invite_token();
        $expiresAt = (new DateTimeImmutable('+48 hours'))->format('Y-m-d H:i:s');

        $stmt = $conn->prepare("
            INSERT INTO officialinvitetbl
                (invite_token_hash, invite_email, invite_phone, firstname, middlename, lastname, suffix, role_access, position_access, department, status, onboarding_step, invited_by_user_id, expires_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 'password', ?, ?)
        ");
        if (!$stmt) {
            set_invite_flash('danger', 'Failed to create invite.');
            redirect_self();
        }
        $stmt->bind_param(
            "ssssssssssss",
            $token['hash'],
            $email,
            $phone10,
            $firstName,
            $middleName,
            $lastName,
            $suffix,
            $roleAccess,
            $positionAccess,
            $department,
            $actorUserId,
            $expiresAt
        );
        $ok = $stmt->execute();
        $inviteId = (int)$stmt->insert_id;
        $stmt->close();

        if (!$ok || $inviteId <= 0) {
            set_invite_flash('danger', 'Failed to create invite.');
            redirect_self();
        }

        $invite = fetch_invite_by_id($conn, $inviteId);
        $emailSent = $invite ? send_invite_email($invite, $token['raw']) : false;
        $smsSent = sendSMS('0' . $phone10, 'Barangay San Jose: You were invited as an official account. Please check your email for your account invite link.');

        insertUnifiedAuditLog(
            $conn,
            $actorUserId,
            $actorRole,
            'Official Invites',
            'OfficialInvite',
            (string)$inviteId,
            'OFFICIAL_INVITE_CREATE',
            'invite_email',
            null,
            $email,
            'Invite sent via email; SMS notification dispatched.',
            null
        );

        $msg = $emailSent
            ? 'Invite created and email sent.'
            : 'Invite created but email send failed (check SMTP).';
        if (!$smsSent) {
            $msg .= ' SMS notification failed.';
        }
        set_invite_flash($emailSent ? 'success' : 'warning', $msg);
        redirect_self();
    }

    if ($action === 'revoke_invite') {
        $inviteId = (int)($_POST['invite_id'] ?? 0);
        $actorPassword = (string)($_POST['actor_password'] ?? '');
        verify_actor_password_or_fail($conn, $actorUserId, $isSuperAdminActor, $actorPassword);
        if ($inviteId <= 0) {
            set_invite_flash('danger', 'Invalid invite.');
            redirect_self();
        }
        $stmt = $conn->prepare("
            UPDATE officialinvitetbl
            SET status = 'Revoked', revoked_at = NOW()
            WHERE invite_id = ? AND status IN ('Pending', 'InProgress')
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param("i", $inviteId);
            $stmt->execute();
            $stmt->close();
        }
        set_invite_flash('success', 'Invite revoked.');
        redirect_self();
    }

    if ($action === 'resend_invite') {
        $inviteId = (int)($_POST['invite_id'] ?? 0);
        $actorPassword = (string)($_POST['actor_password'] ?? '');
        verify_actor_password_or_fail($conn, $actorUserId, $isSuperAdminActor, $actorPassword);
        $invite = fetch_invite_by_id($conn, $inviteId);
        if (!$invite) {
            set_invite_flash('danger', 'Invite not found.');
            redirect_self();
        }
        if ((string)$invite['status'] !== 'Pending' || !empty($invite['token_used_at'])) {
            set_invite_flash('warning', 'Only unused pending invites can be resent.');
            redirect_self();
        }
        $token = oi_generate_invite_token();
        $expiresAt = (new DateTimeImmutable('+48 hours'))->format('Y-m-d H:i:s');
        $up = $conn->prepare("
            UPDATE officialinvitetbl
            SET invite_token_hash = ?, expires_at = ?, updated_at = NOW()
            WHERE invite_id = ?
            LIMIT 1
        ");
        if ($up) {
            $up->bind_param("ssi", $token['hash'], $expiresAt, $inviteId);
            $up->execute();
            $up->close();
        }
        $invite = fetch_invite_by_id($conn, $inviteId);
        $emailSent = $invite ? send_invite_email($invite, $token['raw']) : false;
        $smsSent = sendSMS('0' . (string)$invite['invite_phone'], 'Barangay San Jose: Your official invite was resent. Please check your email.');
        set_invite_flash($emailSent ? 'success' : 'warning', $emailSent ? 'Invite resent.' : 'Invite updated but email failed.');
        if (!$smsSent) {
            set_invite_flash('warning', 'Invite resent email processed, but SMS notification failed.');
        }
        redirect_self();
    }
}

$rows = [];
$q = $conn->query("
    SELECT invite_id, invite_email, invite_phone, firstname, middlename, lastname, suffix, role_access, position_access, department, status, onboarding_step, expires_at, created_at, updated_at
    FROM officialinvitetbl
    ORDER BY invite_id DESC
    LIMIT 100
");
if ($q) {
    while ($row = $q->fetch_assoc()) {
        $rows[] = $row;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Official Invites</title>
    <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
</head>
<body class="bg-light">
<div class="d-flex" style="min-height: 100vh;">
    <?php include 'includes/sidebar.php'; ?>

    <main id="main-display" class="flex-grow-1 p-4 p-md-5 bg-light">
        <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C; font-size: 48px;">
            Official Invites
        </h2>
        <hr>
        <br>

        <?php if (!empty($flash['message'])): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type'] ?: 'info', ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars((string)$flash['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title">Send Invite</h5>
                <form method="post">
                    <input type="hidden" name="action" value="create_invite">
                        <p class="small text-muted mb-2"><span class="text-danger">*</span> Required fields</p>
                        <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input class="form-control" name="last_name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Middle Name</label>
                            <input class="form-control" name="middle_name">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Suffix</label>
                            <select class="form-select" name="suffix">
                                <option value="">None</option>
                                <option value="Jr.">Jr.</option>
                                <option value="Sr.">Sr.</option>
                                <option value="II">II</option>
                                <option value="III">III</option>
                                <option value="IV">IV</option>
                                <option value="V">V</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input class="form-control" name="email" type="email" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">+63</span>
                                <input
                                    class="form-control"
                                    name="phone_number"
                                    placeholder="9XXXXXXXXX"
                                    inputmode="numeric"
                                    pattern="9[0-9]{9}"
                                    maxlength="10"
                                    required
                                >
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role_access" required>
                                <option value="" selected disabled>Select Role</option>
                                <option value="Official">Official</option>
                                <option value="Personnel">Personnel</option>
                                <option value="SuperAdmin">SuperAdmin</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Department <span class="text-danger">*</span></label>
                            <select class="form-select" name="department" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departmentOptions as $dept): ?>
                                    <option value="<?= htmlspecialchars((string)$dept, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$dept, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Position Access <span class="text-danger">*</span></label>
                            <select class="form-select" name="position_access">
                                <option value="">Select Position Access</option>
                                <?php foreach ($positionAccessOptions as $position): ?>
                                    <option value="<?= htmlspecialchars((string)$position, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$position, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Required for Official and Personnel roles.</div>
                        </div>
                        <?php if (in_array((string)($_SESSION['role'] ?? ''), ['SuperAdmin'], true)): ?>
                        <div class="col-md-8">
                            <label class="form-label">Your Current Password <span class="text-danger">*</span></label>
                            <input
                                class="form-control"
                                type="password"
                                name="actor_password"
                                autocomplete="current-password"
                                required
                            >
                            <div class="form-text">Security check required before sending an invite.</div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">Send Invite</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Recent Invites</h5>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Position Access</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Step</th>
                            <th>Expires</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $name = trim(($r['firstname'] ?? '') . ' ' . (($r['middlename'] ?? '') !== '' ? ($r['middlename'] . ' ') : '') . ($r['lastname'] ?? '') . (($r['suffix'] ?? '') !== '' ? (' ' . $r['suffix']) : ''));
                            ?>
                            <tr>
                                <td><?= (int)$r['invite_id'] ?></td>
                                <td><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string)$r['role_access'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string)($r['position_access'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <div><?= htmlspecialchars((string)$r['invite_email'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="text-muted">+63<?= htmlspecialchars((string)$r['invite_phone'], ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                <td><?= htmlspecialchars((string)$r['status'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string)$r['onboarding_step'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string)$r['expires_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?php if ((string)$r['status'] === 'Pending'): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="resend_invite">
                                            <input type="hidden" name="invite_id" value="<?= (int)$r['invite_id'] ?>">
                                            <?php if (in_array((string)($_SESSION['role'] ?? ''), ['SuperAdmin'], true)): ?>
                                                <input type="password" name="actor_password" class="form-control form-control-sm d-inline-block me-1" style="width: 170px;" placeholder="Your password" autocomplete="current-password" required>
                                            <?php endif; ?>
                                            <button type="submit" class="btn btn-outline-primary btn-sm">Resend</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (in_array((string)$r['status'], ['Pending', 'InProgress'], true)): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="revoke_invite">
                                            <input type="hidden" name="invite_id" value="<?= (int)$r['invite_id'] ?>">
                                            <?php if (in_array((string)($_SESSION['role'] ?? ''), ['SuperAdmin'], true)): ?>
                                                <input type="password" name="actor_password" class="form-control form-control-sm d-inline-block me-1" style="width: 170px;" placeholder="Your password" autocomplete="current-password" required>
                                            <?php endif; ?>
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Revoke</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
