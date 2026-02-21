<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../PhpFiles/General/connection.php";
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . "/../PhpFiles/General/officialInviteCommon.php";

oi_ensure_invite_table($conn);

// Ensure new official profile columns exist.
$conn->query("ALTER TABLE officialinformationtbl ADD COLUMN IF NOT EXISTS position_access VARCHAR(100) NULL AFTER role_access");
$conn->query("ALTER TABLE officialinformationtbl ADD COLUMN IF NOT EXISTS emergency_contact_name VARCHAR(150) NULL");
$conn->query("ALTER TABLE officialinformationtbl ADD COLUMN IF NOT EXISTS emergency_contact_relationship VARCHAR(80) NULL");
$conn->query("ALTER TABLE officialinformationtbl ADD COLUMN IF NOT EXISTS emergency_contact_phone VARCHAR(15) NULL");
$conn->query("ALTER TABLE officialinformationtbl ADD COLUMN IF NOT EXISTS emergency_contact_address VARCHAR(255) NULL");
$conn->query("ALTER TABLE officialinformationtbl ADD COLUMN IF NOT EXISTS house_number VARCHAR(50) NULL");
$conn->query("ALTER TABLE officialinformationtbl ADD COLUMN IF NOT EXISTS street_name VARCHAR(150) NULL");
$conn->query("ALTER TABLE officialinformationtbl ADD COLUMN IF NOT EXISTS barangay VARCHAR(150) NULL");
$conn->query("ALTER TABLE officialinformationtbl ADD COLUMN IF NOT EXISTS municipality_city VARCHAR(150) NULL");
$conn->query("ALTER TABLE officialinformationtbl ADD COLUMN IF NOT EXISTS province VARCHAR(150) NULL");

$userId = (string)($_SESSION['user_id'] ?? '');
$account = null;
$profile = null;

if ($userId !== '') {
    $stmtAcc = $conn->prepare("SELECT email, email_verify, phone_number, phoneNum_verify, role_access FROM useraccountstbl WHERE user_id = ? LIMIT 1");
    if ($stmtAcc) {
        $stmtAcc->bind_param("s", $userId);
        $stmtAcc->execute();
        $account = $stmtAcc->get_result()->fetch_assoc() ?: null;
        $stmtAcc->close();
    }

    $stmtProf = $conn->prepare("SELECT * FROM officialinformationtbl WHERE user_id = ? LIMIT 1");
    if ($stmtProf) {
        $stmtProf->bind_param("s", $userId);
        $stmtProf->execute();
        $profile = $stmtProf->get_result()->fetch_assoc() ?: null;
        $stmtProf->close();
    }
}

if (!$account) {
    header('Location: ../Guest-End/login.php');
    exit;
}

if (!$profile) {
    header('Location: ../Guest-End/official_onboarding.php');
    exit;
}

$emailVerified = (int)($account['email_verify'] ?? 0) === 1;
$positionAccess = (string)($profile['position_access'] ?? $profile['role_access'] ?? '');

$profileImageUrl = "../Images/Profile-Placeholder.png";
$stmtAvatar = $conn->prepare("
    SELECT uf.file_path
    FROM unifiedfileattachmenttbl uf
    INNER JOIN documenttypelookuptbl dt
      ON dt.document_type_id = uf.document_type_id
    WHERE uf.source_type = 'OFFICIAL_PROFILE'
      AND uf.source_id = ?
      AND LOWER(dt.document_type_name) = LOWER('2x2 Picture')
      AND dt.document_category = 'OfficialProfiling'
    ORDER BY COALESCE(uf.updated_at, uf.upload_timestamp) DESC, uf.attachment_id DESC
    LIMIT 1
");
if ($stmtAvatar) {
    $stmtAvatar->bind_param("s", $userId);
    $stmtAvatar->execute();
    $avatar = $stmtAvatar->get_result()->fetch_assoc();
    $stmtAvatar->close();
    if ($avatar && !empty($avatar['file_path'])) {
        $path = str_replace("\\", "/", trim((string)$avatar['file_path']));
        $path = ltrim($path, "/");
        if (stripos($path, "BarangaySanJose/") === 0) {
            $path = substr($path, strlen("BarangaySanJose/"));
        }
        if ($path !== '') {
            $profileImageUrl = "../" . $path;
        }
    }
}

$displayName = trim((string)($profile['firstname'] ?? '') . ' ' . (string)($profile['lastname'] ?? ''));
if ($displayName === '') {
    $displayName = "Official User";
}

function ap_view_value($value): string
{
    $v = trim((string)$value);
    return $v !== '' ? $v : 'N/A';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Profile</title>
    <script>
      window.RESIDENT_PROFILE_EMAIL_VERIFIED = <?= $emailVerified ? 'true' : 'false' ?>;
    </script>
    <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
    <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <style>
        .profile-page-title {
            font-family: 'Charis SIL Bold', serif;
            color: #DE710C;
            font-size: clamp(2rem, 4vw, 3rem);
            margin-bottom: 0;
        }
        .profile-topbar {
            background: linear-gradient(135deg, #fff7ed 0%, #ffffff 55%, #f8fafc 100%);
            border: 1px solid #fed7aa;
            border-radius: 16px;
            padding: 1.2rem 1.3rem;
            margin-bottom: 1.25rem;
        }
        .profile-avatar-wrap {
            width: 108px;
            height: 108px;
            border-radius: 999px;
            border: 3px solid #fff;
            box-shadow: 0 2px 12px rgba(0,0,0,.12);
            overflow: hidden;
            background: #fff;
            flex-shrink: 0;
        }
        .profile-avatar-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .profile-topbar-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1f2937;
        }
        .profile-topbar-role {
            font-size: 0.92rem;
            color: #6b7280;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            border-radius: 999px;
            padding: .3rem .7rem;
            font-size: .82rem;
            font-weight: 700;
        }
        .status-pill-ok {
            background: #ecfdf3;
            color: #027a48;
            border: 1px solid #abefc6;
        }
        .status-pill-pending {
            background: #fffaeb;
            color: #b54708;
            border: 1px solid #fedf89;
        }
        .profile-card {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 6px 20px rgba(16, 24, 40, 0.06);
        }
        .profile-card .card-header {
            font-weight: 700;
            border-bottom: 1px solid #f2f4f7;
            background: #fff;
            padding: .9rem 1.1rem;
        }
        .profile-card .card-body {
            padding: 1.05rem 1.1rem 1.2rem;
        }
        .view-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .8rem;
        }
        @media (max-width: 1200px) {
            .view-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        @media (max-width: 900px) {
            .view-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 575.98px) {
            .view-grid { grid-template-columns: 1fr; }
        }
        .view-item {
            background: #f8fafc;
            border: 1px solid #eaecf0;
            border-radius: 12px;
            padding: .7rem .8rem;
            min-height: 68px;
        }
        .view-label {
            font-size: .78rem;
            color: #667085;
            margin-bottom: .25rem;
            text-transform: uppercase;
            letter-spacing: .02em;
        }
        .view-value {
            color: #1f2937;
            font-weight: 700;
            word-break: break-word;
        }
        .account-info {
            font-weight: 600;
            color: #1f2937;
        }
        .account-label {
            color: #667085;
            font-size: .83rem;
            margin-bottom: .15rem;
        }
        .account-action-btn {
            border-radius: 10px;
            padding: .55rem .9rem;
            font-weight: 600;
            border: 1px solid #d0d5dd;
            background: #fff;
            color: #344054;
            text-decoration: none;
        }
        .account-action-btn:hover {
            border-color: #98a2b3;
            color: #1d2939;
            background: #f9fafb;
        }
    </style>
</head>
<body class="bg-light">
<div class="d-flex" style="min-height: 100vh;">
    <?php include 'includes/sidebar.php'; ?>

    <main id="main-display" class="flex-grow-1 p-4 p-md-5 bg-light">
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
            <h2 class="profile-page-title">My Profile</h2>
        </div>

        <div class="profile-topbar">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="profile-avatar-wrap">
                        <img src="<?= htmlspecialchars($profileImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Official Profile Image" onerror="this.onerror=null;this.src='../Images/Profile-Placeholder.png';">
                    </div>
                    <div>
                        <div class="profile-topbar-name">
                            <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div class="profile-topbar-role">
                            <?= htmlspecialchars($positionAccess !== '' ? $positionAccess : (string)($account['role_access'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="status-pill <?= ((int)($account['email_verify'] ?? 0) === 1) ? 'status-pill-ok' : 'status-pill-pending' ?>">
                        <i class="bi <?= ((int)($account['email_verify'] ?? 0) === 1) ? 'bi-check-circle-fill' : 'bi-clock-fill' ?>"></i>
                        Email <?= ((int)($account['email_verify'] ?? 0) === 1) ? 'Verified' : 'Pending' ?>
                    </span>
                    <span class="status-pill <?= ((int)($account['phoneNum_verify'] ?? 0) === 1) ? 'status-pill-ok' : 'status-pill-pending' ?>">
                        <i class="bi <?= ((int)($account['phoneNum_verify'] ?? 0) === 1) ? 'bi-check-circle-fill' : 'bi-clock-fill' ?>"></i>
                        Phone <?= ((int)($account['phoneNum_verify'] ?? 0) === 1) ? 'Verified' : 'Pending' ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4 profile-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Account Settings</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="account-label">Email</div>
                        <div class="account-info"><?= htmlspecialchars((string)$account['email'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="account-label">Phone</div>
                        <div class="account-info">+63<?= htmlspecialchars((string)$account['phone_number'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="col-md-12 d-flex flex-wrap gap-2 pt-1">
                        <a href="javascript:void(0)" id="changePhoneLink" class="account-action-btn">Change Phone Number</a>
                        <a href="javascript:void(0)" id="changeEmailLink" class="account-action-btn">Change Email</a>
                        <a href="javascript:void(0)" id="changePasswordLink" class="account-action-btn">Change Password</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4 profile-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Personal Information</span>
            </div>
            <div class="card-body">
                <div class="view-grid">
                    <div class="view-item"><div class="view-label">Last Name</div><div class="view-value"><?= htmlspecialchars(ap_view_value($profile['lastname'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div></div>
                    <div class="view-item"><div class="view-label">First Name</div><div class="view-value"><?= htmlspecialchars(ap_view_value($profile['firstname'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div></div>
                    <div class="view-item"><div class="view-label">Middle Name</div><div class="view-value"><?= htmlspecialchars(ap_view_value($profile['middlename'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div></div>
                    <div class="view-item"><div class="view-label">Suffix</div><div class="view-value"><?= htmlspecialchars(ap_view_value($profile['suffix'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div></div>
                    <div class="view-item"><div class="view-label">Birthdate</div><div class="view-value"><?= htmlspecialchars(ap_view_value($profile['birthdate'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div></div>
                    <div class="view-item"><div class="view-label">Sex</div><div class="view-value"><?= htmlspecialchars(ap_view_value($profile['sex'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div></div>
                    <div class="view-item"><div class="view-label">Civil Status</div><div class="view-value"><?= htmlspecialchars(ap_view_value($profile['civil_status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div></div>
                    <div class="view-item"><div class="view-label">Department</div><div class="view-value"><?= htmlspecialchars(ap_view_value($profile['department'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div></div>
                    <div class="view-item"><div class="view-label">System Role</div><div class="view-value"><?= htmlspecialchars(ap_view_value($account['role_access'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div></div>
                    <div class="view-item"><div class="view-label">Position Access</div><div class="view-value"><?= htmlspecialchars(ap_view_value($positionAccess), ENT_QUOTES, 'UTF-8') ?></div></div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4 profile-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Emergency Contact</span>
            </div>
            <div class="card-body">
                <div class="view-grid">
                    <div class="view-item"><div class="view-label">Contact Name</div><div class="view-value"><?= htmlspecialchars(ap_view_value($profile['emergency_contact_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div></div>
                    <div class="view-item"><div class="view-label">Relationship</div><div class="view-value"><?= htmlspecialchars(ap_view_value($profile['emergency_contact_relationship'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div></div>
                    <div class="view-item"><div class="view-label">Contact Number (+63)</div><div class="view-value"><?= htmlspecialchars(ap_view_value($profile['emergency_contact_phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div></div>
                    <div class="view-item"><div class="view-label">Address</div><div class="view-value"><?= htmlspecialchars(ap_view_value($profile['emergency_contact_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div></div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4 profile-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Address</span>
            </div>
            <div class="card-body">
                <div class="view-grid">
                    <div class="view-item"><div class="view-label">House Number</div><div class="view-value"><?= htmlspecialchars(ap_view_value($profile['house_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div></div>
                    <div class="view-item"><div class="view-label">Street Name</div><div class="view-value"><?= htmlspecialchars(ap_view_value($profile['street_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div></div>
                    <div class="view-item"><div class="view-label">Barangay</div><div class="view-value"><?= htmlspecialchars(ap_view_value($profile['barangay'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div></div>
                    <div class="view-item"><div class="view-label">Municipality / City</div><div class="view-value"><?= htmlspecialchars(ap_view_value($profile['municipality_city'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div></div>
                    <div class="view-item"><div class="view-label">Province</div><div class="view-value"><?= htmlspecialchars(ap_view_value($profile['province'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div></div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title text-black">Change Password</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div id="changePasswordError" class="alert alert-danger d-none"></div>
      <div class="mb-3">
        <label class="form-label">Current Password</label>
        <div class="input-group"><input type="password" class="form-control" id="currentPassword"><span class="input-group-text" data-toggle-password="currentPassword" data-eye-id="eyeCurrentPw"><i class="bi bi-eye" id="eyeCurrentPw"></i></span></div>
      </div>
      <div class="mb-3">
        <label class="form-label">New Password</label>
        <div class="input-group"><input type="password" class="form-control" id="newPassword"><span class="input-group-text" data-toggle-password="newPassword" data-eye-id="eyeNewPw"><i class="bi bi-eye" id="eyeNewPw"></i></span></div>
      </div>
      <div class="mb-3">
        <label class="form-label">Confirm New Password</label>
        <div class="input-group"><input type="password" class="form-control" id="confirmNewPassword"><span class="input-group-text" data-toggle-password="confirmNewPassword" data-eye-id="eyeConfirmPw"><i class="bi bi-eye" id="eyeConfirmPw"></i></span></div>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-primary" id="btnOpenConfirmChangePassword">Continue</button></div>
  </div></div>
</div>

<div class="modal fade" id="confirmChangePasswordModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title text-black">Confirm</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">Are you sure you want to change your password?</div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger" id="btnConfirmChangePassword">Yes, Change</button></div>
  </div></div>
</div>

<!-- Change Phone Number Modal -->
<div class="modal fade" id="changePhoneModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title text-black">Change Phone Number</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body p-0"><div class="change-phone-shell"><div class="change-phone-content">
      <div id="cpnError" class="alert alert-danger small d-none" role="alert"></div>
      <div class="cpn-step" data-step="choose"><p class="mb-3 text-muted">To change your mobile number, verify your identity first.</p><div class="d-grid gap-2"><button type="button" class="btn btn-primary" id="btnCpnVerifyViaPhone">Verify via Phone</button><button type="button" class="btn btn-outline-primary d-none" id="btnCpnVerifyViaEmail">Verify via Email</button></div></div>
      <div class="cpn-step d-none" data-step="otp_old"><div class="text-center mb-2"><img src="../Images/SMS-OTP.png" alt="OTP" class="cpn-otp-icon" /></div><p class="text-center mb-3" id="cpnOtpOldMessage">Check your phone. An OTP has been sent.</p><div class="otp-inputs cpn-otp-inputs" id="cpnOtpOldInputs"><input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" /></div><button type="button" class="btn btn-primary w-100 mt-3" id="btnCpnVerifyOldOtp">Verify OTP</button><div class="text-center mt-3"><div class="d-flex justify-content-center align-items-center gap-2"><a href="javascript:void(0)" id="cpnResendOldOtp" class="text-primary text-decoration-underline">Resend OTP</a><span id="cpnResendOldTimer" class="small text-muted"></span></div><div class="mt-2"><a href="javascript:void(0)" id="cpnBackToChoose" class="text-primary text-decoration-underline">Back</a></div></div></div>
      <div class="cpn-step d-none" data-step="new_phone"><p class="mb-2 text-muted">Enter your new mobile number.</p><div class="input-group mb-2"><span class="input-group-text"><img src="https://upload.wikimedia.org/wikipedia/commons/9/99/Flag_of_the_Philippines.svg" alt="PH" width="22" style="margin-right:6px;">+63</span><input type="tel" class="form-control" id="cpnNewPhone" placeholder="9XXXXXXXXX" inputmode="numeric" maxlength="10" /></div><button type="button" class="btn btn-primary w-100" id="btnCpnSendNewOtp">Send OTP</button><div class="text-center mt-3"><a href="javascript:void(0)" id="cpnBackToOtpOld" class="text-primary text-decoration-underline">Back</a></div></div>
      <div class="cpn-step d-none" data-step="otp_new"><div class="text-center mb-2"><img src="../Images/SMS-OTP.png" alt="OTP" class="cpn-otp-icon" /></div><p class="text-center mb-3" id="cpnOtpNewMessage">Check your phone. An OTP has been sent.</p><div class="otp-inputs cpn-otp-inputs" id="cpnOtpNewInputs"><input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" /></div><button type="button" class="btn btn-success w-100 mt-3" id="btnCpnVerifyNewOtp">Verify & Change</button><div class="text-center mt-3"><div class="d-flex justify-content-center align-items-center gap-2"><a href="javascript:void(0)" id="cpnResendNewOtp" class="text-primary text-decoration-underline">Resend OTP</a><span id="cpnResendNewTimer" class="small text-muted"></span></div><div class="mt-2"><a href="javascript:void(0)" id="cpnBackToNewPhone" class="text-primary text-decoration-underline">Back</a></div></div></div>
    </div></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
  </div></div>
</div>

<!-- Change Email Modal -->
<div class="modal fade" id="changeEmailModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title text-black">Change Email</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body p-0"><div class="change-email-shell"><div class="change-email-content">
      <div id="cemError" class="alert alert-danger small d-none" role="alert"></div>
      <div class="cem-step" data-step="choose"><p class="mb-3 text-muted">To change your email, verify your identity first.</p><div class="d-grid gap-2"><button type="button" class="btn btn-primary" id="btnCemVerifyViaPhone">Verify via Phone</button><button type="button" class="btn btn-outline-primary d-none" id="btnCemVerifyViaEmail">Verify via Email</button></div></div>
      <div class="cem-step d-none" data-step="otp_old"><div class="text-center mb-2"><img src="../Images/SMS-OTP.png" alt="OTP" class="cem-otp-icon" /></div><p class="text-center mb-3" id="cemOtpOldMessage">Check your phone. An OTP has been sent.</p><div class="otp-inputs cem-otp-inputs" id="cemOtpOldInputs"><input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" /></div><button type="button" class="btn btn-primary w-100 mt-3" id="btnCemVerifyOldOtp">Verify OTP</button><div class="text-center mt-3"><div class="d-flex justify-content-center align-items-center gap-2"><a href="javascript:void(0)" id="cemResendOldOtp" class="text-primary text-decoration-underline">Resend OTP</a><span id="cemResendOldTimer" class="small text-muted"></span></div><div class="mt-2"><a href="javascript:void(0)" id="cemBackToChoose" class="text-primary text-decoration-underline">Back</a></div></div></div>
      <div class="cem-step d-none" data-step="new_email"><p class="mb-2 text-muted">Enter your new email address.</p><div class="input-group mb-2"><input type="email" class="form-control" id="cemNewEmail" placeholder="name@example.com" autocomplete="email" /></div><button type="button" class="btn btn-primary w-100" id="btnCemSendVerification">Send Verification Email</button><div class="text-center mt-3"><a href="javascript:void(0)" id="cemBackToOtpOld" class="text-primary text-decoration-underline">Back</a></div></div>
      <div class="cem-step d-none" data-step="sent"><div class="text-center mb-2"><img src="../Images/SMS-OTP.png" alt="Email" class="cem-otp-icon" /></div><h6 class="text-center mb-2">Verification Email Sent</h6><p class="text-center text-muted mb-0" id="cemSentMessage">Check your email and click the Verify Email button. This link will expire in 15 minutes.</p></div>
    </div></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
  </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../JS-Script-Files/modalHandler.js"></script>
<script src="../JS-Script-Files/Resident-End/profileChangePassword.js"></script>
<script src="../JS-Script-Files/Resident-End/profileChangePhone.js"></script>
<script src="../JS-Script-Files/Resident-End/profileChangeEmail.js"></script>
<script>
  document.addEventListener("DOMContentLoaded", () => {
    if (!window.bootstrap?.Modal) return;
    document.querySelectorAll(".modal").forEach((targetModal) => {
      targetModal.addEventListener("show.bs.modal", () => {
        document.querySelectorAll(".modal.show").forEach((openModalEl) => {
          if (openModalEl === targetModal) return;
          const instance = bootstrap.Modal.getInstance(openModalEl);
          if (instance) instance.hide();
        });
      });
    });
  });
</script>
</body>
</html>
