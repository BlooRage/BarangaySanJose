<?php
$allowUnregistered = false;
require_once __DIR__ . "/includes/resident_access_guard.php";
require_once "../PhpFiles/GET/getResidentProfile.php";

$data = getResidentProfileData($conn, $_SESSION['user_id']);
$residentinformationtbl = $data['residentinformationtbl'];
$residentaddresstbl = $data['residentaddresstbl'];
$useraccountstbl = $data['useraccountstbl'];

$emailVerified = (int)($useraccountstbl['email_verify'] ?? 0) === 1;

$computedAge = '';
if (!empty($residentinformationtbl['birthdate'])) {
    $dobDate = DateTime::createFromFormat('Y-m-d', $residentinformationtbl['birthdate']);
    if (!$dobDate) {
        try {
            $dobDate = new DateTime($residentinformationtbl['birthdate']);
        } catch (Exception $e) {
            $dobDate = null;
        }
    }
    if ($dobDate) {
        $computedAge = $dobDate->diff(new DateTime('today'))->y;
    }
}

$profileImage = '../Images/Profile-Placeholder.png';
$residentId = $residentinformationtbl['resident_id'] ?? '';
$headOfFamilyRaw = $residentinformationtbl['head_of_family'] ?? '';
$headOfFamilyNormalized = strtolower(trim((string)$headOfFamilyRaw));
$isHeadOfFamily = in_array($headOfFamilyNormalized, ['yes', 'true', '1', 'y'], true);
$residentStatusRaw = trim((string)($residentinformationtbl['status_name_resident'] ?? ''));
$residentStatusKey = strtolower(str_replace([' ', '_', '-'], '', $residentStatusRaw));
$isResidentVerified = in_array($residentStatusKey, ['verifiedresident', 'verified'], true);
$canSendHouseholdInvite = $isHeadOfFamily && $isResidentVerified;
$editStatusKey = $residentStatusKey !== '' ? $residentStatusKey : 'notverified';
$canEditProfile = !in_array($editStatusKey, ['notverified', 'pendingverification'], true);
$editBlockMessage = 'Your account must be verified before you can edit your profile, address, or emergency contact.';

if (!function_exists('toPublicPath')) {
function toPublicPath($path): ?string {
    $path = trim((string)$path);
    if ($path === '') {
        return null;
    }

    $normalized = str_replace("\\", "/", $path);
    $normalized = preg_replace('#/+#', '/', $normalized);

    $parts = explode('/', $normalized);
    $cleanParts = [];
    foreach ($parts as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            array_pop($cleanParts);
            continue;
        }
        $cleanParts[] = $part;
    }
    $normalized = '/' . implode('/', $cleanParts);

    $marker = '/UnifiedFileAttachment/';
    $markerPos = stripos($normalized, $marker);
    if ($markerPos !== false) {
        $public = substr($normalized, $markerPos);
        return '..' . $public;
    }

    $webRoot = realpath(__DIR__ . "/..");
    if ($webRoot) {
        $rootNorm = str_replace("\\", "/", $webRoot);
        if (strpos($normalized, $rootNorm) === 0) {
            $rel = substr($normalized, strlen($rootNorm));
            if ($rel === '') {
                return null;
            }
            if ($rel[0] !== '/') {
                $rel = '/' . $rel;
            }
            return '../' . ltrim($rel, '/');
        }
    }

    return '../' . ltrim($normalized, '/');
}
}
if ($residentId !== '' && isset($conn) && $conn instanceof mysqli) {
    $stmtPic = $conn->prepare("
        SELECT uf.file_path
        FROM unifiedfileattachmenttbl uf
        INNER JOIN documenttypelookuptbl dt
            ON uf.document_type_id = dt.document_type_id
        INNER JOIN statuslookuptbl s
            ON uf.status_id_verify = s.status_id
        WHERE uf.source_type = 'ResidentProfiling'
          AND uf.source_id = ?
          AND dt.document_type_name = '2x2 Picture'
          AND dt.document_category = 'ResidentProfiling'
          AND s.status_name = 'Verified'
          AND s.status_type = 'ResidentDocumentProfiling'
        ORDER BY uf.upload_timestamp DESC, uf.attachment_id DESC
        LIMIT 1
    ");
    if ($stmtPic) {
        $stmtPic->bind_param("s", $residentId);
        $stmtPic->execute();
        $stmtPic->bind_result($verifiedPicPath);
        if ($stmtPic->fetch() && !empty($verifiedPicPath)) {
            $publicPath = toPublicPath($verifiedPicPath);
            if (!empty($publicPath)) {
                $profileImage = $publicPath;
            }
        }
        $stmtPic->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    
  <link rel="icon" href="/Images/favicon_sanjose.png?v=20260211">
	<title>Resident Profile</title>

    <script>
      // Used by JS change-phone flow to decide if email OTP option is allowed.
      window.RESIDENT_PROFILE_EMAIL_VERIFIED = <?= $emailVerified ? 'true' : 'false' ?>;
      window.RESIDENT_PROFILE_EDIT_ALLOWED = <?= $canEditProfile ? 'true' : 'false' ?>;
      window.RESIDENT_PROFILE_EDIT_BLOCK_MESSAGE = <?= json_encode($editBlockMessage, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
      window.RESIDENT_PROFILE_AGE = <?= $computedAge !== '' ? (int)$computedAge : 'null' ?>;
      window.RESIDENT_PROFILE_SEX = <?= json_encode((string)($residentinformationtbl['sex'] ?? ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <script src="../JS-Script-Files/modalHandler.js" defer></script>
  <script src="../JS-Script-Files/Resident-End/householdMembers.js" defer></script>
  <script src="../JS-Script-Files/Resident-End/profileOccupation.js" defer></script>
  <script src="../JS-Script-Files/Resident-End/profileSidebar.js" defer></script>
  <script src="../JS-Script-Files/Resident-End/profileVerifyEmail.js" defer></script>
  <script src="../JS-Script-Files/Resident-End/householdInviteModal.js" defer></script>
  <script src="../JS-Script-Files/Resident-End/householdJoin.js" defer></script>
  <script src="../JS-Script-Files/Resident-End/profileTabs.js" defer></script>
  <script src="../JS-Script-Files/Resident-End/profileAddress.js" defer></script>
  <script src="../JS-Script-Files/Resident-End/profileEmergency.js" defer></script>
	  <script src="../JS-Script-Files/Resident-End/profileChangePassword.js?v=20260215-1" defer></script>
	  <script src="../JS-Script-Files/Resident-End/profileChangePhone.js?v=20260215-1" defer></script>
	  <script src="../JS-Script-Files/Resident-End/profileChangeEmail.js?v=20260215-1" defer></script>
	  <script src="../JS-Script-Files/Resident-End/profileUploadedDocuments.js?v=20260215-1" defer></script>
	  <script src="../JS-Script-Files/Resident-End/profileEdit.js" defer></script>
	    <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/residentDashboard.css">
</head>

<body>

    <div class="d-flex" style="min-height: 100vh;">

        <?php include 'includes/resident_sidebar.php'; ?>

        <header id="mobile-header">
            <div class="d-flex align-items-center px-3 py-2 shadow-sm bg-white">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn" id="btn-burger" type="button">
                        <i class="fa-solid fa-bars fa-lg"></i>
                    </button>
                    <img src="../Images/San_Jose_LOGO.jpg" alt="Logo" style="width:32px;height:32px">
                    <span class="logo-name">Barangay San Jose</span>
                </div>
            </div>
        </header>

        <main id="div-mainDisplay" class="flex-grow-1 px-4 pb-4 pt-0 px-md-5 pb-md-5 pt-md-0 bg-light">

            <div class="main-head text-center py-1 rounded my-2">
                <h3 class="mb-0 text-black">ACCOUNT</h3>
            </div>
            <hr class="mt-1 mb-2">

            <ul class="nav profile-tabs mb-3" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-profile" data-bs-toggle="tab" data-bs-target="#pane-profile" type="button" role="tab" aria-controls="pane-profile" aria-selected="true">
                        Profile
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-household" data-bs-toggle="tab" data-bs-target="#pane-household" type="button" role="tab" aria-controls="pane-household" aria-selected="false">
                        Household
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-uploaded-docs" data-bs-toggle="tab" data-bs-target="#pane-uploaded-docs" type="button" role="tab" aria-controls="pane-uploaded-docs" aria-selected="false">
                        Uploaded Documents
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="pane-profile" role="tabpanel" aria-labelledby="tab-profile" tabindex="0">
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between">
                    <strong>PERSONAL INFORMATION</strong>
                    <button class="btn btn-primary btn-sm" id="btnOpenEditProfile">
                        Edit
                    </button>
                </div>

                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-12 col-md-12 col-lg-3 d-flex align-items-center justify-content-center">
                            <img src="<?= htmlspecialchars($profileImage) ?>"
                                id="img-profileAvatar"
                                class="img-fluid rounded-circle mb-2"
                                style="width:170px; height: 170px;">
                        </div>
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="d-flex flex-column gap-2">
                                <?php
                                  $lastName = trim((string)($residentinformationtbl['lastname'] ?? ''));
                                  $firstName = trim((string)($residentinformationtbl['firstname'] ?? ''));
                                  $middleName = trim((string)($residentinformationtbl['middlename'] ?? ''));
                                  $middleInitial = $middleName !== '' ? strtoupper(substr($middleName, 0, 1)) . '.' : '';
                                  $fullNameDisplay = '—';
                                  if ($lastName !== '' || $firstName !== '') {
                                      $fullNameDisplay = trim($lastName . ', ' . $firstName);
                                      if ($middleInitial !== '') {
                                          $fullNameDisplay .= ', ' . $middleInitial;
                                      }
                                  }
                                ?>
                                <div><strong>Full Name:</strong> <?= htmlspecialchars($fullNameDisplay, ENT_QUOTES, 'UTF-8') ?></div>
                                <div><strong>Age:</strong> <?= $computedAge !== '' ? $computedAge : '—' ?></div>
                                <div><strong>Birthdate:</strong> <?= $residentinformationtbl['birthdate'] ?></div>
                                <div><strong>Sex:</strong> <?= $residentinformationtbl['sex'] ?></div>
                                <div><strong>Civil Status:</strong> <?= $residentinformationtbl['civil_status'] ?></div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-5">
                            <div class="d-flex flex-column gap-2">
                                <div><strong>Religion:</strong> <?= $residentinformationtbl['religion'] ?></div>
                                <div><strong>Voter Status:</strong> <?= $residentinformationtbl['voter_status'] ?></div>
                                <div><strong>Head of the Family:</strong> <?= $residentinformationtbl['head_of_family'] ?></div>
                                <div><strong>Occupation:</strong> <?= $residentinformationtbl['occupation'] ?></div>
                                <?php
                                  $sectorText = trim((string)($residentinformationtbl['sector_membership'] ?? ''));
                                  $pendingSector = (int)($residentinformationtbl['sector_membership_pending_review'] ?? 0);
                                  $pendingSectorLabels = trim((string)($residentinformationtbl['sector_membership_pending_labels'] ?? ''));
                                  $hasVerifiedSector = $sectorText !== '' && strcasecmp($sectorText, 'none') !== 0;
                                  $pendingLabel = $pendingSectorLabels !== ''
                                      ? ($pendingSectorLabels . ' (Pending Review)')
                                      : 'Pending Review';
                                ?>
                                <?php if ($hasVerifiedSector): ?>
                                    <div>
                                        <strong>Sector Membership:</strong> <?= htmlspecialchars($sectorText, ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <?php if ($pendingSector > 0): ?>
                                        <div class="small text-muted">
                                            <strong>Other sector membership:</strong> <?= htmlspecialchars($pendingLabel, ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    <?php endif; ?>
                                <?php elseif ($pendingSector > 0): ?>
                                    <div>
                                        <strong>Sector Membership:</strong> <?= htmlspecialchars($pendingLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php else: ?>
                                    <div>
                                        <strong>Sector Membership:</strong> N/A
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between">
                    <strong>ADDRESS INFORMATION</strong>
                    <button class="btn btn-primary btn-sm" id="btnOpenEditAddress">
                        Change Address
                    </button>
                </div>
	                <div class="card-body">
	                    <div class="row g-3 align-items-start">
	                        <div class="col-12">
	                            <div class="text-muted small mb-1">Full Address</div>
	                            <div class="fw-semibold">
	                                <?php
	                                  $unitNumber = trim((string)($residentaddresstbl['unit_number'] ?? ''));
	                                  $houseNo = trim((string)($residentaddresstbl['street_number'] ?? ''));
                                  $streetName = trim((string)($residentaddresstbl['street_name'] ?? ''));
                                  $phase = trim((string)($residentaddresstbl['phase_number'] ?? ''));
                                  $subdivision = trim((string)($residentaddresstbl['subdivision'] ?? ''));
                                  $area = trim((string)($residentaddresstbl['area_number'] ?? ''));

                                  $streetDisplay = $streetName;
                                  if ($streetName !== '' && stripos($streetName, 'block') === false) {
                                    $streetDisplay = $streetName . ' Street';
                                  }
                                  $parts = [];
                                  if ($unitNumber !== '') $parts[] = 'Unit ' . $unitNumber;
                                  if ($houseNo !== '') $parts[] = $houseNo;
                                  if ($streetDisplay !== '') $parts[] = $streetDisplay;
                                  if ($phase !== '') $parts[] = $phase;
                                  if ($subdivision !== '') $parts[] = $subdivision;
                                  $parts[] = 'San Jose';
                                  if ($area !== '') $parts[] = $area;
                                  $parts[] = 'Rodriguez';
                                  $parts[] = 'Rizal';
                                  $parts[] = '1860';

	                                  echo implode(', ', array_filter($parts, fn($v) => $v !== ''));
	                                ?>
	                            </div>
	                        </div>
	                        <div class="col-12 col-md-4">
	                            <div class="text-muted small mb-1">House Type</div>
	                            <div class="fw-semibold">
	                                <?= htmlspecialchars(trim((string)($residentaddresstbl['house_type'] ?? '')) !== '' ? (string)$residentaddresstbl['house_type'] : '—') ?>
	                            </div>
	                        </div>
	                        <div class="col-12 col-md-4">
	                            <div class="text-muted small mb-1">House Ownership</div>
	                            <div class="fw-semibold">
	                                <?= htmlspecialchars(trim((string)($residentaddresstbl['house_ownership'] ?? '')) !== '' ? (string)$residentaddresstbl['house_ownership'] : '—') ?>
	                            </div>
	                        </div>
	                        <div class="col-12 col-md-4">
	                            <div class="text-muted small mb-1">Length of Residency</div>
	                            <div class="fw-semibold">
	                                <?= htmlspecialchars(trim((string)($residentaddresstbl['residency_duration'] ?? '')) !== '' ? (string)$residentaddresstbl['residency_duration'] : '—') ?>
	                            </div>
	                        </div>
	                    </div>
	                </div>
	            </div>
             <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between">
                    <strong>EMERGENCY CONTACT INFORMATION</strong>
                    <button class="btn btn-primary btn-sm" id="btnOpenEditEmergency">
                        Edit
                    </button>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <strong>Contact Person:</strong> <?= htmlspecialchars(($residentinformationtbl['emergency_name'] ?? '—') !== '' ? $residentinformationtbl['emergency_name'] : '—') ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <strong>Contact Number:</strong> <?= htmlspecialchars(($residentinformationtbl['emergency_contact'] ?? '—') !== '' ? $residentinformationtbl['emergency_contact'] : '—') ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <strong>Relationship:</strong> <?= htmlspecialchars(($residentinformationtbl['emergency_relationship'] ?? '—') !== '' ? $residentinformationtbl['emergency_relationship'] : '—') ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <strong>Address:</strong> <?= htmlspecialchars(($residentinformationtbl['emergency_address'] ?? '—') !== '' ? $residentinformationtbl['emergency_address'] : '—') ?>
                        </div>
                    </div>
                </div>
            </div>

	            <div class="card shadow-sm">
	                <div class="card-header"><strong>ACCOUNT INFORMATION</strong></div>
                <div class="card-body small">
                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <div><strong>Account Type:</strong> <?= $useraccountstbl['type'] ?></div>
                            <div><strong>Account Created:</strong> <?= $useraccountstbl['created'] ?></div>
                            <div>
                                <strong>Account Status:</strong>
                                <?php
                                  $statusLabelRaw = trim((string)($residentinformationtbl['status_name_resident'] ?? ''));
                                  $statusLabel = $statusLabelRaw !== '' ? $statusLabelRaw : 'NotVerified';
                                  $statusKey = strtolower(str_replace([' ', '_', '-'], '', $statusLabel));
                                  $statusClass = 'status-badge status-badge--default';

                                  if ($statusKey === 'pendingverification' || $statusKey === 'pendingreview') {
                                      $statusClass = 'status-badge status-badge--pending';
                                  } elseif ($statusKey === 'verifiedresident' || $statusKey === 'verified') {
                                      $statusClass = 'status-badge status-badge--verified';
                                  } elseif ($statusKey === 'notverified') {
                                      $statusClass = 'status-badge status-badge--denied';
                                  } elseif ($statusKey === 'archived') {
                                      $statusClass = 'status-badge status-badge--archived';
                                  }

                                  $statusDisplay = preg_replace('/(?<!^)([A-Z])/', ' $1', $statusLabel);
                                ?>
                                <span class="<?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>">
                                  <?= htmlspecialchars($statusDisplay, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div><strong>Mobile Number:</strong> +63<?= $useraccountstbl['phone_number'] ?></div>
                            <div><strong>Email:</strong> <?= $useraccountstbl['email'] ?>
                                <?php
                                  $emailVerifyClass = $emailVerified
                                      ? 'status-badge status-badge--verified'
                                      : 'status-badge status-badge--denied';
                                  $emailVerifyLabel = $emailVerified ? 'Verified' : 'Not Verified';
                                ?>
                                <span class="<?= htmlspecialchars($emailVerifyClass, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($emailVerifyLabel, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <hr class="my-3">
	                    <div class="row g-2">
	                        <div class="col-12 col-md-6"><a href="javascript:void(0)" id="changePhoneLink">Change Phone Number</a></div>
	                        <div class="col-12 col-md-6"><a href="javascript:void(0)" id="changeEmailLink">Change Email</a></div>
	                        <div class="col-12 col-md-6">
	                            <a href="javascript:void(0)" id="changePasswordLink">Change Password</a>
	                            <?php if (!empty($useraccountstbl['last_password_change'])): ?>
	                            <div class="text-muted fst-italic small mt-1">
	                                Last changed:
	                                <?= htmlspecialchars($useraccountstbl['last_password_change'], ENT_QUOTES, 'UTF-8') ?>
	                            </div>
	                            <?php endif; ?>
                        </div>
                        <?php if (!(int)($useraccountstbl['email_verify'] ?? 0)): ?>
                            <div class="col-12 col-md-6">
                                <a href="javascript:void(0)" id="verifyEmailLink">Verify Email</a>
                            </div>
                        <?php else: ?>
                            <div class="col-12 col-md-6 text-muted fst-italic">Email already verified</div>
                        <?php endif; ?>
                    </div>
                </div>
	            </div>

                </div>
                <div class="tab-pane fade" id="pane-household" role="tabpanel" aria-labelledby="tab-household" tabindex="0">
                    <?php if (!$isHeadOfFamily): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <strong>JOIN HOUSEHOLD</strong>
                        </div>
                        <div class="card-body">
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-md-8">
                                    <label for="householdJoinCode" class="form-label small text-muted">Invite Code</label>
                                    <input type="text" class="form-control" id="householdJoinCode" placeholder="Enter invite code">
                                </div>
                                <div class="col-12 col-md-4">
                                    <button class="btn btn-primary w-100" id="btnJoinHousehold">Join Household</button>
                                </div>
                            </div>
                            <div id="householdJoinResult" class="small mt-2"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>HOUSEHOLD INFORMATION</strong>
                            <?php if ($isHeadOfFamily): ?>
                            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#householdInviteModal">
                                Add Household Member
                            </button>
                            <?php else: ?>
                            <button class="btn btn-danger btn-sm" id="btnLeaveHousehold">
                                Leave Household
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="text-muted small">Address</div>
                                <div id="householdAddress" class="fw-semibold">—</div>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6 col-md-4">
                                    <div class="text-muted small">Minors</div>
                                    <div id="householdMinorCount" class="fw-semibold">0</div>
                                </div>
                                <div class="col-6 col-md-4">
                                    <div class="text-muted small">Adults</div>
                                    <div id="householdAdultCount" class="fw-semibold">0</div>
                                </div>
                            </div>
                            <div id="householdMembersGrid" class="row g-3"></div>
                            <div id="householdMembersEmpty" class="text-muted small mt-2 d-none">
                                No household members yet.
                            </div>
                            <div class="mt-3 text-muted small">
                                Only the head of the family can add or manage household members.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="pane-uploaded-docs" role="tabpanel" aria-labelledby="tab-uploaded-docs" tabindex="0">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>VERIFIED UPLOADED DOCUMENTS</strong>
                            <button class="btn btn-outline-secondary btn-sm" id="btnRefreshUploadedDocs" type="button">
                                Refresh
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="text-muted small mb-2">
                                Only documents marked as Verified by the barangay will appear here.
                            </div>

                            <div id="uploadedDocsError" class="alert alert-danger d-none" role="alert"></div>

                            <div id="uploadedDocsLoading" class="text-muted small">Loading...</div>

                            <div class="table-responsive d-none" id="uploadedDocsTableWrap">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Document</th>
                                            <th>Category</th>
                                            <th>Uploaded</th>
                                            <th>ID Number</th>
                                            <th>File</th>
                                        </tr>
                                    </thead>
                                    <tbody id="uploadedDocsTbody"></tbody>
                                </table>
                            </div>

                            <div id="uploadedDocsEmpty" class="text-muted small d-none">
                                No verified documents found.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <?php if ($isHeadOfFamily): ?>
    <div class="modal fade" id="householdInviteModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Invite Household Members</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (!$isResidentVerified): ?>
                        <div class="alert alert-warning small mb-2">
                            Your account must be verified before sending household invite codes via SMS.
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <p class="text small mb-2">
                            Invite members with accounts via SMS.
                        </p>
                        <div id="householdInvitePhoneList" class="d-flex flex-column gap-2">
                            <div class="input-group">
                                <span class="input-group-text">+63</span>
                                <input type="text" class="form-control household-invite-phone" placeholder="9XXXXXXXXX" inputmode="numeric" pattern="^\d{10}$" maxlength="10">
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm mt-2" id="btnAddInvitePhone">
                            Add Another Number
                        </button>
                        <div class="form-text mt-2">Use PH format starting with +63.</div>
                        <div id="householdInviteResult" class="small mt-2"></div>
                    </div>
                    <hr class="my-3">
                    <div>
                        <p class="text small mb-2">
                            Add member without an account.
                        </p>
                        <div class="row g-2">
                            <div class="col-12 col-md-6">
                                <label class="form-label small text-muted">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="hmLastName" placeholder="Last Name">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small text-muted">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="hmFirstName" placeholder="First Name">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small text-muted">Middle Name</label>
                                <input type="text" class="form-control" id="hmMiddleName" placeholder="Middle Name">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small text-muted">Suffix</label>
                                <input type="text" class="form-control" id="hmSuffix" placeholder="Suffix (e.g. Jr.)">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small text-muted">Birthdate</label>
                                <input type="date" class="form-control" id="hmBirthdate">
                            </div>
                        </div>
                        <div id="householdMemberAddResult" class="small mt-2"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-outline-primary" id="btnAddHouseholdMemberInfo" disabled>Add Member</button>
                    <button class="btn btn-success" id="btnSendHouseholdInvite" data-verified="<?= $isResidentVerified ? '1' : '0' ?>" <?= $canSendHouseholdInvite ? '' : 'disabled' ?>>Send Invites</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="modal fade" id="editProfileModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">Edit Profile</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div id="profileSaveResult" class="small mb-2"></div>

                    <label class="form-label">Full Name</label>
                    <div class="d-flex gap-2 mb-3">
                        <input class="form-control" id="editFirstName" value="<?= $residentinformationtbl['firstname'] ?>" placeholder="First Name" >
                        <input class="form-control" id="editMiddleName" value="<?= $residentinformationtbl['middlename'] ?>" placeholder="Middle Name" >
                        <input class="form-control" id="editLastName" value="<?= $residentinformationtbl['lastname'] ?>" placeholder="Last Name" >
                        <select class="form-control" id="editSuffix">
                            <option value="">N/A</option>
                            <option value="Jr." <?= ($residentinformationtbl['suffix'] ?? '') === 'Jr.' ? 'selected' : '' ?>>Jr.</option>
                            <option value="Sr." <?= ($residentinformationtbl['suffix'] ?? '') === 'Sr.' ? 'selected' : '' ?>>Sr.</option>
                        </select>
                    </div>
                    <div class="alert alert-warning small mb-3 d-none" id="nameDocNoticeInline">
                        Changing your name requires a valid ID photo. Select an ID type in the upload step.
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Sex</label>
                            <input class="form-control input-readonly" value="<?= $residentinformationtbl['sex'] ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Birthdate</label>
                            <input class="form-control input-readonly" value="<?= $residentinformationtbl['birthdate'] ?>" readonly>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Civil Status</label>
                            <select class="form-control" id="editCivilStatus">
                                <option value="Single" <?= ($residentinformationtbl['civil_status'] ?? '') === 'Single' ? 'selected' : '' ?>>Single</option>
                                <option value="Married" <?= ($residentinformationtbl['civil_status'] ?? '') === 'Married' ? 'selected' : '' ?>>Married</option>
                                <option value="Widowed" <?= ($residentinformationtbl['civil_status'] ?? '') === 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                                <option value="Divorced" <?= ($residentinformationtbl['civil_status'] ?? '') === 'Divorced' ? 'selected' : '' ?>>Divorced</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Religion</label>
                            <select class="form-control" id="editReligion">
                                <option value="Roman Catholic" <?= ($residentinformationtbl['religion'] ?? '') === 'Roman Catholic' ? 'selected' : '' ?>>Roman Catholic</option>
                                <option value="Iglesia ni Cristo" <?= ($residentinformationtbl['religion'] ?? '') === 'Iglesia ni Cristo' ? 'selected' : '' ?>>Iglesia ni Cristo</option>
                                <option value="Muslim" <?= ($residentinformationtbl['religion'] ?? '') === 'Muslim' ? 'selected' : '' ?>>Muslim</option>
                                <option value="Others" <?= ($residentinformationtbl['religion'] ?? '') === 'Others' ? 'selected' : '' ?>>Others</option>
                            </select>
                        </div>
                    </div>
                    <div class="alert alert-warning small mb-3 d-none" id="civilStatusDocNoticeInline">
                        Civil status updates need proof: marriage certificate (Married) or spouse's death certificate (Widowed).
                    </div>
                    <div class="row mb-3 d-none" id="newSurnameRow">
                        <div class="col-md-6">
                            <label class="form-label">New Surname</label>
                            <input type="text" class="form-control" id="editNewSurname" placeholder="Enter new surname">
                        </div>
                    </div>

                    <div class="row mb-3">

                         <div class="col-md-6">
                            <label class="form-label">Voter Status</label>
                            <?php
                              $voterStatusText = trim((string)($residentinformationtbl['voter_status'] ?? ''));
                              $isRegisteredVoter = strcasecmp($voterStatusText, 'Registered Voter') === 0;
                            ?>
                            <select class="form-select" name="voter_status" id="editVoterStatus">
                                <option value="Registered" <?= $isRegisteredVoter ? 'selected' : '' ?>>Registered</option>
                                <option value="Not Registered" <?= !$isRegisteredVoter ? 'selected' : '' ?>>Not Registered</option>
                            </select>
                        </div>

                         <div class="col-md-6">
                            <label class="form-label">Employment Status</label>
                        <select class="form-select" name="employment_status" id="employmentStatus" onchange="toggleOccupation()">
                            <option value="Employed" <?= ($residentinformationtbl['employment_status'] == 'Employed') ? 'selected' : '' ?>>Employed</option>
                            <option value="Unemployed" <?= ($residentinformationtbl['employment_status'] == 'Unemployed') ? 'selected' : '' ?>>Unemployed</option>
                        </select>
                        </div>

                    </div>

                     <div class="row mb-3" id="occupationRow" style="display: none;">
                        <div class="col-md-12">
                            <label class="form-label">Occupation</label>
                            <input type="text"
                                class="form-control"
                                id="editOccupation"
                                name="occupation"
                                value="<?= $residentinformationtbl['occupation'] ?>">
                        </div>
                    </div>


                    <div class="mb-3">
                        <label class="form-label">Sector Membership</label>
                        <?php
                          $sectorSelected = array_filter(array_map('trim', explode(',', (string)($residentinformationtbl['sector_membership'] ?? ''))));
                        ?>
                        <div class="row g-2">
                            <div class="col-12 col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="sectorPWD" name="sectorMembership[]" value="PWD" <?= in_array('PWD', $sectorSelected, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="sectorPWD">Person with Disability (PWD)</label>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="sectorSingleParent" name="sectorMembership[]" value="Single Parent" <?= in_array('Single Parent', $sectorSelected, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="sectorSingleParent">Single Parent</label>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="sectorStudent" name="sectorMembership[]" value="Student" <?= in_array('Student', $sectorSelected, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="sectorStudent">Student</label>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="sectorSenior" name="sectorMembership[]" value="Senior Citizen" <?= in_array('Senior Citizen', $sectorSelected, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="sectorSenior">Senior Citizen</label>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="sectorIndigenous" name="sectorMembership[]" value="Indigenous People" <?= in_array('Indigenous People', $sectorSelected, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="sectorIndigenous">Indigenous People</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" id="btnProfileReview" type="button">Review Changes</button>
                </div>

                </div>

            </div>
        </div>
    </div>

    <div class="modal fade" id="editProfileUploadModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Supporting Documents</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="doc-required-box mb-3 d-none" id="supportReligionSection">
                        <div class="small fw-semibold text-muted mb-2">For Religion Change</div>
                        <label class="form-label">Supporting Document Type</label>
                        <select class="form-select mb-2" id="supportReligionType">
                            <option value="">Select document type</option>
                            <option value="Certificate of Employment">Certificate of Employment</option>
                            <option value="Proof of Income">Proof of Income</option>
                            <option value="Voter Certification">Voter Certification</option>
                            <option value="Proof of Residency">Proof of Residency</option>
                            <option value="Barangay Clearance">Barangay Clearance</option>
                            <option value="Affidavit">Affidavit</option>
                            <option value="Other Supporting Document">Other Supporting Document</option>
                        </select>
                        <label class="form-label">Supporting Document</label>
                        <input type="file" class="form-control" id="supportReligionFile" name="supporting_religion_file[]" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple>
                        <div class="form-text">Upload at least one supporting document for this change. You can select multiple files.</div>
                    </div>
                    <div class="doc-required-box mb-3 d-none" id="supportVoterSection">
                        <div class="small fw-semibold text-muted mb-2">For Voter Status Change</div>
                        <label class="form-label">Supporting Document Type</label>
                        <select class="form-select mb-2" id="supportVoterType">
                            <option value="">Select document type</option>
                            <option value="Certificate of Employment">Certificate of Employment</option>
                            <option value="Proof of Income">Proof of Income</option>
                            <option value="Voter Certification">Voter Certification</option>
                            <option value="Proof of Residency">Proof of Residency</option>
                            <option value="Barangay Clearance">Barangay Clearance</option>
                            <option value="Affidavit">Affidavit</option>
                            <option value="Other Supporting Document">Other Supporting Document</option>
                        </select>
                        <label class="form-label">Supporting Document</label>
                        <input type="file" class="form-control" id="supportVoterFile" name="supporting_voter_file[]" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple>
                        <div class="form-text">Upload at least one supporting document for this change. You can select multiple files.</div>
                    </div>
                    <div class="doc-required-box mb-3 d-none" id="supportEmploymentSection">
                        <div class="small fw-semibold text-muted mb-2">For Employment Status Change</div>
                        <label class="form-label">Supporting Document Type</label>
                        <select class="form-select mb-2" id="supportEmploymentType">
                            <option value="">Select document type</option>
                            <option value="Certificate of Employment">Certificate of Employment</option>
                            <option value="Proof of Income">Proof of Income</option>
                            <option value="Voter Certification">Voter Certification</option>
                            <option value="Proof of Residency">Proof of Residency</option>
                            <option value="Barangay Clearance">Barangay Clearance</option>
                            <option value="Affidavit">Affidavit</option>
                            <option value="Other Supporting Document">Other Supporting Document</option>
                        </select>
                        <label class="form-label">Supporting Document</label>
                        <input type="file" class="form-control" id="supportEmploymentFile" name="supporting_employment_file[]" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple>
                        <div class="form-text">Upload at least one supporting document for this change. You can select multiple files.</div>
                    </div>
                    <div class="doc-required-box mb-3 d-none" id="supportSectorSection">
                        <div class="small fw-semibold text-muted mb-2">For Sector Membership Change</div>
                        <label class="form-label">Supporting Document Type</label>
                        <select class="form-select mb-2" id="supportSectorType">
                            <option value="">Select document type</option>
                            <option value="Certificate of Employment">Certificate of Employment</option>
                            <option value="Proof of Income">Proof of Income</option>
                            <option value="Voter Certification">Voter Certification</option>
                            <option value="Proof of Residency">Proof of Residency</option>
                            <option value="Barangay Clearance">Barangay Clearance</option>
                            <option value="Affidavit">Affidavit</option>
                            <option value="Other Supporting Document">Other Supporting Document</option>
                        </select>
                        <label class="form-label">Supporting Document</label>
                        <input type="file" class="form-control" id="supportSectorFile" name="supporting_sector_file[]" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple>
                        <div class="form-text">Upload at least one supporting document for this change. You can select multiple files.</div>
                    </div>
                    <div class="alert alert-warning small mb-3 d-none" id="nameDocNotice">
                        To change your name, select a valid ID type and upload a photo of your ID.
                    </div>
                    <div class="doc-required-box d-none mb-3" id="nameDocSection">
                        <div class="small fw-semibold text-muted mb-2">For Name Change</div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Valid ID Type</label>
                                <select class="form-select" id="nameIdType">
                                    <option value="">Select ID</option>
                                    <option value="Passport">Passport</option>
                                    <option value="Driver's License">Driver's License</option>
                                    <option value="PhilHealth ID">PhilHealth ID</option>
                                    <option value="Voter's ID">Voter's ID</option>
                                    <option value="National ID">National ID</option>
                                    <option value="Barangay ID">Barangay ID</option>
                                    <option value="PRC ID">PRC ID</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Valid ID Photo</label>
                                <input type="file" class="form-control" id="nameIdFile" name="name_id_file[]" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple>
                                <div class="form-text">Clear photo of your valid ID. You can select multiple files.</div>
                            </div>
                        </div>
                    </div>
                    <div class="doc-required-box d-none" id="civilStatusDocSection">
                        <div class="small fw-semibold text-muted mb-2">For Civil Status Change</div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label" id="civilStatusDocLabel">Document</label>
                                <input type="file" class="form-control" id="civilStatusFile" name="civil_status_file[]" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple>
                            </div>
                            <div class="col-md-6">
                                <div class="form-text mt-4" id="civilStatusDocHelp"></div>
                            </div>
                        </div>
                    </div>
                    <div class="doc-required-box d-none" id="studentUntickSection">
                        <div class="small fw-semibold text-muted mb-2">For Student Sector Change</div>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-7">
                                <label class="form-label">Diploma / Proof (Optional if stopped studying)</label>
                                <input type="file" class="form-control" id="studentStatusFile" name="student_status_file[]" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple>
                            </div>
                            <div class="col-md-5">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" id="studentStoppedSwitch">
                                    <label class="form-check-label" for="studentStoppedSwitch">I stopped studying</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" id="btnProfileBackToForm" type="button">Back</button>
                    <button class="btn btn-primary" id="btnProfileSave" type="button">Submit Request</button>
                </div>
            </div>
        </div>
    </div>


     <div class="modal fade" id="addAddressModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header justify-content-center position-relative">
                    <h5 class="modal-title text-center w-100">Change Address</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-warning small mb-3">
                        Changing your address will remove you from your household.
                    </div>
                    <?php if ($isHeadOfFamily): ?>
                    <div id="headReassignBlock" class="border rounded p-2 mb-3 d-none" data-is-head="<?= $isHeadOfFamily ? '1' : '0' ?>">
                        <label class="form-label fw-bold mb-1">Assign New Head of Household</label>
                        <select class="form-select" id="newHeadResidentId">
                            <option value="">Select a member</option>
                        </select>
                        <div class="form-text">Required before a household head can change address.</div>
                        <div id="headReassignLoading" class="text-muted small mt-2 d-none">Loading household members...</div>
                        <div id="headReassignEmpty" class="text-danger small mt-2 d-none">
                            You must have at least one other active household member to reassign the head role.
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="row g-2 mb-2">
                        <div class="col-12">
                            <label class="form-label mb-1" for="addressSystemEdit">Address System <span class="text-danger">*</span></label>
                            <select class="form-select" id="addressSystemEdit">
                                <option value="" selected>Select</option>
                                <option value="house">House Numbering System</option>
                                <option value="lot_block">Lot/Block System</option>
                            </select>
                        </div>
                    </div>

                    <div id="addressHouseWrapper" class="d-none border rounded p-2 mb-2">
                        <div class="small fw-semibold text-muted mb-2">House Numbering System</div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-4">
                                <label class="form-label mb-1" for="addressUnitNumberHouse">Unit / Apartment Number</label>
                                <input type="text" class="form-control" id="addressUnitNumberHouse" value="">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label mb-1" for="addressStreetNumberHouse">House Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="addressStreetNumberHouse" value="">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label mb-1" for="addressStreetNameHouse">Street Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="addressStreetNameHouse" value="">
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-4">
                                <label class="form-label mb-1" for="addressPhaseNumberHouse">Phase</label>
                                <input type="text" class="form-control" id="addressPhaseNumberHouse" value="">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label mb-1" for="addressSubdivisionHouse">Subdivision</label>
                                <input type="text" class="form-control" id="addressSubdivisionHouse" value="">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label mb-1" for="addressAreaNumberHouse">Area <span class="text-danger">*</span></label>
                                <select class="form-select" id="addressAreaNumberHouse">
                                    <option value="">Select Area</option>
                                    <?php
                                      $areaOptions = ['Area 01', 'Area 1A', 'Area 02', 'Area 03', 'Area 04', 'Area 05', 'Area 06'];
                                    ?>
                                    <?php foreach ($areaOptions as $areaOption): ?>
                                        <option value="<?= htmlspecialchars($areaOption) ?>">
                                            <?= htmlspecialchars($areaOption) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="addressLotBlockWrapper" class="d-none border rounded p-2 mb-2">
                        <div class="small fw-semibold text-muted mb-2">Lot/Block System</div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-3">
                                <label class="form-label mb-1" for="addressUnitNumberLot">Unit / Apartment Number</label>
                                <input type="text" class="form-control" id="addressUnitNumberLot" value="">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-1" for="addressLotNumber">Lot <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="addressLotNumber" value="">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-1" for="addressBlockNumber">Block <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="addressBlockNumber" value="">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-1" for="addressStreetNameLot">Street Name</label>
                                <input type="text" class="form-control" id="addressStreetNameLot" value="">
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <label class="form-label mb-1" for="addressSubdivisionLot">Subdivision</label>
                                <input type="text" class="form-control" id="addressSubdivisionLot" value="">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label mb-1" for="addressAreaNumberLot">Area <span class="text-danger">*</span></label>
                                <select class="form-select" id="addressAreaNumberLot">
                                    <option value="">Select Area</option>
                                    <?php foreach ($areaOptions as $areaOption): ?>
                                        <option value="<?= htmlspecialchars($areaOption) ?>">
                                            <?= htmlspecialchars($areaOption) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label class="form-label mb-1">Barangay</label>
                            <input type="text" class="form-control bg-light text-secondary" value="Barangay San Jose" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1">Municipality / City</label>
                            <input type="text" class="form-control bg-light text-secondary" value="Rodriguez" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1">Province</label>
                            <input type="text" class="form-control bg-light text-secondary" value="Rizal" readonly>
                        </div>
                    </div>

                    <hr>

                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label class="form-label mb-1" for="addressHouseOwnership">House Ownership <span class="text-danger">*</span></label>
                            <select class="form-select" id="addressHouseOwnership">
                                <option value="">Select</option>
                                <option value="Owner">Owner</option>
                                <option value="Tenant">Tenant</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1" for="addressHouseType">House Type <span class="text-danger">*</span></label>
                            <?php $houseTypeOptions = ['Concrete', 'Semi-Concrete', 'Wood/Light Materials', 'Makeshift/Salvaged Materials', 'Shanty/Informal']; ?>
                            <select class="form-select" id="addressHouseType">
                                <option value="">Select</option>
                                <?php foreach ($houseTypeOptions as $opt): ?>
                                    <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                                <?php endforeach; ?>
                                <option value="Other">Other</option>
                            </select>
                            <input type="text" class="form-control mt-2 d-none" id="addressHouseTypeOther" placeholder="Specify house type" value="">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-1" for="addressResidencyDuration">Residency Duration</label>
                            <select class="form-select bg-light text-secondary" id="addressResidencyDuration" disabled>
                                <option value="Less than 6 months" selected>Less than 6 months</option>
                            </select>
                        </div>
                    </div>
                    <div id="addressSaveResult" class="small mt-2"></div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-success" id="btnSaveAddress" type="button">Save</button>
                </div>

            </div>
        </div>
    </div>

<div class="modal fade" id="editEmergencyContactModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5>Edit Emergency Contact</h5>
                        <button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <label class="form-label">Contact Person</label>
                        <div class="d-flex gap-2 mb-3">
                            <input class="form-control" id="emergencyLastName" value="<?= $residentinformationtbl['emergency_last_name'] ?? '' ?>" placeholder="Last Name">
                            <input class="form-control" id="emergencyFirstName" value="<?= $residentinformationtbl['emergency_first_name'] ?? '' ?>" placeholder="First Name">
                            <input class="form-control" id="emergencyMiddleName" value="<?= $residentinformationtbl['emergency_middle_name'] ?? '' ?>" placeholder="Middle Name">
                            <select class="form-control" id="emergencySuffix">
                                <option value="" <?= empty($residentinformationtbl['emergency_suffix']) ? 'selected' : '' ?>>N/A</option>
                                <option value="Jr." <?= ($residentinformationtbl['emergency_suffix'] ?? '') === 'Jr.' ? 'selected' : '' ?>>Jr.</option>
                                <option value="Sr." <?= ($residentinformationtbl['emergency_suffix'] ?? '') === 'Sr.' ? 'selected' : '' ?>>Sr.</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <div class="input-group">
                                <span class="input-group-text">+63</span>
                                <input class="form-control" id="emergencyContact" inputmode="numeric" maxlength="10" placeholder="9XXXXXXXXX" value="<?= $residentinformationtbl['emergency_contact'] ?? '' ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Relationship</label>
                            <?php
                              $relRaw = $residentinformationtbl['emergency_relationship'] ?? '';
                              $relNorm = strtolower(trim((string)$relRaw));
                              $relOptions = ['parent' => 'Parent', 'child' => 'Child', 'spouse' => 'Spouse', 'other' => 'Other'];
                              $relSelectedKey = array_key_exists($relNorm, $relOptions) ? $relNorm : ($relNorm !== '' ? 'other' : '');
                              $relOtherValue = ($relSelectedKey === 'other' && $relNorm !== '' && !array_key_exists($relNorm, $relOptions)) ? $relRaw : '';
                            ?>
                            <select class="form-select" id="emergencyRelationship">
                                <option value="" <?= $relSelectedKey === '' ? 'selected' : '' ?>>Select relationship</option>
                                <option value="Parent" <?= $relSelectedKey === 'parent' ? 'selected' : '' ?>>Parent</option>
                                <option value="Child" <?= $relSelectedKey === 'child' ? 'selected' : '' ?>>Child</option>
                                <option value="Spouse" <?= $relSelectedKey === 'spouse' ? 'selected' : '' ?>>Spouse</option>
                                <option value="Other" <?= $relSelectedKey === 'other' ? 'selected' : '' ?>>Other</option>
                            </select>
                            <input class="form-control mt-2 d-none" id="emergencyRelationshipOther" placeholder="Please specify" value="<?= htmlspecialchars($relOtherValue) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <input class="form-control" id="emergencyAddress" placeholder="Emergency Address" value="<?= $residentinformationtbl['emergency_address'] ?? '' ?>">
                        </div>
                        <div id="emergencySaveResult" class="small mt-2"></div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary" id="btnSaveEmergency" type="button">Save</button>
                    </div>
                </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-black" style="color:#000;">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="changePasswordError" class="alert alert-danger small d-none" role="alert"></div>

                    <div class="mb-3">
                        <div class="input-group">
                            <input type="password" class="form-control" id="currentPassword" autocomplete="current-password" placeholder="Current password" aria-label="Current password">
                            <span class="input-group-text" style="cursor: pointer" data-toggle-password="currentPassword" data-eye-id="eyeCurrentPw" aria-label="Show/Hide password">
                                <i id="eyeCurrentPw" class="bi bi-eye"></i>
                            </span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="input-group">
                            <input type="password" class="form-control" id="newPassword" autocomplete="new-password" placeholder="New password" aria-label="New password">
                            <span class="input-group-text" style="cursor: pointer" data-toggle-password="newPassword" data-eye-id="eyeNewPw" aria-label="Show/Hide password">
                                <i id="eyeNewPw" class="bi bi-eye"></i>
                            </span>
                        </div>
                    </div>
                    <div class="mb-2">
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirmNewPassword" autocomplete="new-password" placeholder="Confirm new password" aria-label="Confirm new password">
                            <span class="input-group-text" style="cursor: pointer" data-toggle-password="confirmNewPassword" data-eye-id="eyeConfirmPw" aria-label="Show/Hide password">
                                <i id="eyeConfirmPw" class="bi bi-eye"></i>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="btnOpenConfirmChangePassword">Change</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirm Change Password Modal -->
    <div class="modal fade" id="confirmChangePasswordModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-black" style="color:#000;">Confirm</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to change your password?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmChangePassword">Yes, Change</button>
                </div>
            </div>
        </div>
    </div>

	    <!-- Change Phone Number Modal -->
	    <div class="modal fade" id="changePhoneModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
	        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
	            <div class="modal-content">
	                <div class="modal-header">
	                    <h5 class="modal-title text-black" style="color:#000;">Change Phone Number</h5>
	                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
	                </div>

	                    <div class="modal-body p-0">
	                    <div class="change-phone-shell">
	                        <div class="change-phone-content">
	                            <div id="cpnError" class="alert alert-danger small d-none" role="alert"></div>

                            <!-- Step 1: Choose verification method -->
                            <div class="cpn-step" data-step="choose">
                                <p class="mb-3 text-muted">
                                    To change your mobile number, verify your identity first.
                                </p>
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-primary" id="btnCpnVerifyViaPhone">Verify via Phone</button>
                                    <button type="button" class="btn btn-outline-primary d-none" id="btnCpnVerifyViaEmail">Verify via Email</button>
                                </div>
                            </div>

                            <!-- Step 2: Verify OTP (old phone/email) -->
                            <div class="cpn-step d-none" data-step="otp_old">
                                <div class="text-center mb-2">
                                    <img src="../Images/SMS-OTP.png" alt="OTP" class="cpn-otp-icon" />
                                </div>
                                <p class="text-center mb-3" id="cpnOtpOldMessage">
                                    Check your phone. An OTP has been sent.
                                </p>
                                <div class="otp-inputs cpn-otp-inputs" id="cpnOtpOldInputs">
                                    <input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" />
                                    <input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" />
                                </div>
                                <button type="button" class="btn btn-primary w-100 mt-3" id="btnCpnVerifyOldOtp">Verify OTP</button>
                                <div class="text-center mt-3">
                                    <div class="d-flex justify-content-center align-items-center gap-2">
                                        <a href="javascript:void(0)" id="cpnResendOldOtp" class="text-primary text-decoration-underline">Resend OTP</a>
                                        <span id="cpnResendOldTimer" class="small text-muted"></span>
                                    </div>
                                    <div class="mt-2">
                                        <a href="javascript:void(0)" id="cpnBackToChoose" class="text-primary text-decoration-underline">Back</a>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 3: Enter new phone -->
                            <div class="cpn-step d-none" data-step="new_phone">
                                <p class="mb-2 text-muted">Enter your new mobile number.</p>
                                <div class="input-group mb-2">
                                    <span class="input-group-text">
                                        <img src="https://upload.wikimedia.org/wikipedia/commons/9/99/Flag_of_the_Philippines.svg" alt="PH" width="22" style="margin-right:6px;">+63
                                    </span>
                                    <input type="tel" class="form-control" id="cpnNewPhone" placeholder="9XXXXXXXXX" inputmode="numeric" maxlength="10" />
                                </div>
                                <button type="button" class="btn btn-primary w-100" id="btnCpnSendNewOtp">Send OTP</button>
                                <div class="text-center mt-3">
                                    <a href="javascript:void(0)" id="cpnBackToOtpOld" class="text-primary text-decoration-underline">Back</a>
                                </div>
                            </div>

                            <!-- Step 4: Verify OTP (new phone) -->
                            <div class="cpn-step d-none" data-step="otp_new">
                                <div class="text-center mb-2">
                                    <img src="../Images/SMS-OTP.png" alt="OTP" class="cpn-otp-icon" />
                                </div>
                                <p class="text-center mb-3" id="cpnOtpNewMessage">
                                    Check your phone. An OTP has been sent.
                                </p>
                                <div class="otp-inputs cpn-otp-inputs" id="cpnOtpNewInputs">
                                    <input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" />
                                    <input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" />
                                </div>
                                <button type="button" class="btn btn-success w-100 mt-3" id="btnCpnVerifyNewOtp">Verify & Change</button>
                                <div class="text-center mt-3">
                                    <div class="d-flex justify-content-center align-items-center gap-2">
                                        <a href="javascript:void(0)" id="cpnResendNewOtp" class="text-primary text-decoration-underline">Resend OTP</a>
                                        <span id="cpnResendNewTimer" class="small text-muted"></span>
                                    </div>
                                    <div class="mt-2">
                                        <a href="javascript:void(0)" id="cpnBackToNewPhone" class="text-primary text-decoration-underline">Back</a>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
	        </div>
	    </div>

	    <!-- Change Email Modal -->
	    <div class="modal fade" id="changeEmailModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
	        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
	            <div class="modal-content">
	                <div class="modal-header">
	                    <h5 class="modal-title text-black" style="color:#000;">Change Email</h5>
	                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
	                </div>

	                <div class="modal-body p-0">
	                    <div class="change-email-shell">
	                        <div class="change-email-content">
	                            <div id="cemError" class="alert alert-danger small d-none" role="alert"></div>

	                            <!-- Step 1: Choose verification method -->
	                            <div class="cem-step" data-step="choose">
	                                <p class="mb-3 text-muted">
	                                    To change your email, verify your identity first.
	                                </p>
	                                <div class="d-grid gap-2">
	                                    <button type="button" class="btn btn-primary" id="btnCemVerifyViaPhone">Verify via Phone</button>
	                                    <button type="button" class="btn btn-outline-primary d-none" id="btnCemVerifyViaEmail">Verify via Email</button>
	                                </div>
	                            </div>

	                            <!-- Step 2: Verify OTP (old phone/email) -->
	                            <div class="cem-step d-none" data-step="otp_old">
	                                <div class="text-center mb-2">
	                                    <img src="../Images/SMS-OTP.png" alt="OTP" class="cem-otp-icon" />
	                                </div>
	                                <p class="text-center mb-3" id="cemOtpOldMessage">
	                                    Check your phone. An OTP has been sent.
	                                </p>
	                                <div class="otp-inputs cem-otp-inputs" id="cemOtpOldInputs">
	                                    <input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" />
	                                    <input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" /><input maxlength="1" inputmode="numeric" />
	                                </div>
	                                <button type="button" class="btn btn-primary w-100 mt-3" id="btnCemVerifyOldOtp">Verify OTP</button>
	                                <div class="text-center mt-3">
	                                    <div class="d-flex justify-content-center align-items-center gap-2">
	                                        <a href="javascript:void(0)" id="cemResendOldOtp" class="text-primary text-decoration-underline">Resend OTP</a>
	                                        <span id="cemResendOldTimer" class="small text-muted"></span>
	                                    </div>
	                                    <div class="mt-2">
	                                        <a href="javascript:void(0)" id="cemBackToChoose" class="text-primary text-decoration-underline">Back</a>
	                                    </div>
	                                </div>
	                            </div>

	                            <!-- Step 3: Enter new email -->
	                            <div class="cem-step d-none" data-step="new_email">
	                                <p class="mb-2 text-muted">Enter your new email address.</p>
	                                <div class="input-group mb-2">
	                                    <input type="email" class="form-control" id="cemNewEmail" placeholder="name@example.com" autocomplete="email" />
	                                </div>
	                                <button type="button" class="btn btn-primary w-100" id="btnCemSendVerification">Send Verification Email</button>
	                                <div class="text-center mt-3">
	                                    <a href="javascript:void(0)" id="cemBackToOtpOld" class="text-primary text-decoration-underline">Back</a>
	                                </div>
	                            </div>

	                            <!-- Step 4: Verification email sent -->
	                            <div class="cem-step d-none" data-step="sent">
	                                <div class="text-center mb-2">
	                                    <img src="../Images/SMS-OTP.png" alt="Email" class="cem-otp-icon" />
	                                </div>
	                                <h6 class="text-center mb-2">Verification Email Sent</h6>
	                                <p class="text-center text-muted mb-0" id="cemSentMessage">
	                                    Check your email and click the Verify Email button. This link will expire in 15 minutes.
	                                </p>
	                            </div>
	                        </div>
	                    </div>
	                </div>

	                <div class="modal-footer">
	                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
	                </div>
	            </div>
	        </div>
	    </div>

        <!-- Uploaded Document Viewer Modal -->
        <div class="modal fade" id="modalUploadedDocViewer" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width: 1100px; width: 92vw;">
                <div class="modal-content">
                    <div class="modal-header">
                        <div class="w-100">
                            <h5 class="fw-bold mb-0" id="udvTitle">Document Preview</h5>
                            <div class="small text-muted" id="udvSubtitle"></div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="udvBody" class="w-100"></div>
                    </div>
                    <div class="modal-footer">
                        <a href="#" class="btn btn-outline-primary d-none" id="udvOpenNewTab" target="_blank" rel="noopener">Open</a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
        </div>
    </div>

    <div class="modal fade" id="beforeEditModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 justify-content-center">
                    <h5 class="modal-title text-dark text-center w-100">Before You Continue</h5>
                </div>
                <div class="modal-body">
                    <p class="mb-0 text-center">
                        Saving changes will send a request for review. Every applied change request requires supporting document/s for verification.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="btnBeforeEditContinue">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <!-- GENERIC NOTICE MODAL -->
    <div class="modal fade" id="residentNoticeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 justify-content-center">
                    <h5 class="modal-title text-center w-100 text-dark" id="residentNoticeTitle">Notice</h5>
                </div>
                <hr class="my-0">
                <div class="modal-body text-center" id="residentNoticeBody"></div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary w-100 text-center" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            document.addEventListener("DOMContentLoaded", () => {
                if (!window.bootstrap?.Modal) return;

                // Enforce one-modal-at-a-time for resident profile page.
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
