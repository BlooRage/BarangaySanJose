<?php
$allowUnregistered = false;
require_once __DIR__ . "/includes/resident_access_guard.php";
require_once "../PhpFiles/GET/getResidentProfile.php";
require_once "../PhpFiles/General/uploadLimits.php";
require_once "../PhpFiles/General/uniqueIDGenerate.php";

$data = getResidentProfileData($conn, $_SESSION['user_id']);
$residentinformationtbl = $data['residentinformationtbl'] ?? [];
$residentaddresstbl = $data['residentaddresstbl'] ?? [];
$useraccountstbl = $data['useraccountstbl'] ?? [];

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
$editStatusKey = $residentStatusKey !== '' ? $residentStatusKey : 'notverified';
$canEditProfile = !in_array($editStatusKey, ['notverified', 'pendingverification'], true);
$editBlockMessage = 'Your account must be verified before you can edit your profile, address, or emergency contact.';
$residentCsrfToken = ensureCsrfToken();
$profileImageFlash = ['type' => '', 'message' => ''];

if (!empty($_SESSION['resident_profile_image_flash']) && is_array($_SESSION['resident_profile_image_flash'])) {
    $profileImageFlash = $_SESSION['resident_profile_image_flash'];
    unset($_SESSION['resident_profile_image_flash']);
}

function rp_set_image_flash(string $type, string $message): void {
    $_SESSION['resident_profile_image_flash'] = ['type' => $type, 'message' => $message];
}

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

if (!function_exists('toDbWebPath')) {
function toDbWebPath(string $absolutePath): string {
    $absolutePath = str_replace("\\", "/", trim($absolutePath));
    $projectRoot = realpath(__DIR__ . "/..");
    $marker = "/UnifiedFileAttachment/";
    $markerPos = strpos($absolutePath, $marker);
    if ($markerPos !== false) {
        return ltrim(substr($absolutePath, $markerPos), "/");
    }
    if ($projectRoot) {
        $rootNorm = str_replace("\\", "/", $projectRoot);
        if (strpos($absolutePath, $rootNorm) === 0) {
            return ltrim(substr($absolutePath, strlen($rootNorm)), "/");
        }
    }
    return ltrim($absolutePath, "/");
}
}

if (!function_exists('publicPathExists')) {
function publicPathExists(?string $publicPath): bool {
    $publicPath = trim((string)$publicPath);
    if ($publicPath === '') {
        return false;
    }
    if (preg_match('#^https?://#i', $publicPath)) {
        return true;
    }

    $webRoot = realpath(__DIR__ . "/..");
    if ($webRoot === false) {
        return false;
    }

    $relative = '/' . ltrim($publicPath, '. /');
    $absolute = realpath($webRoot . $relative);
    if ($absolute === false) {
        return false;
    }

    return is_file($absolute);
}
}

if (!function_exists('getStatusId')) {
function getStatusId(mysqli $conn, string $name, string $type): ?int {
    $stmt = $conn->prepare("SELECT status_id FROM statuslookuptbl WHERE status_name = ? AND status_type = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param("ss", $name, $type);
    $stmt->execute();
    $stmt->bind_result($statusId);
    $found = $stmt->fetch();
    $stmt->close();
    return $found ? (int)$statusId : null;
}
}

if (!function_exists('getDocumentTypeIdByCategory')) {
function getDocumentTypeIdByCategory(mysqli $conn, string $name, string $category): int {
    $q = $conn->prepare("SELECT document_type_id FROM documenttypelookuptbl WHERE LOWER(document_type_name) = LOWER(?) AND document_category = ? LIMIT 1");
    if (!$q) throw new RuntimeException("Failed to prepare document type lookup.");
    $q->bind_param("ss", $name, $category);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    $q->close();
    if ($row && isset($row['document_type_id'])) return (int)$row['document_type_id'];

    $ins = $conn->prepare("INSERT INTO documenttypelookuptbl (document_type_name, document_category) VALUES (?, ?)");
    if (!$ins) throw new RuntimeException("Failed to prepare document type create.");
    $ins->bind_param("ss", $name, $category);
    if (!$ins->execute()) {
        $ins->close();
        throw new RuntimeException("Failed to create document type.");
    }
    $newId = (int)$ins->insert_id;
    $ins->close();
    if ($newId <= 0) throw new RuntimeException("Failed to resolve document type.");
    return $newId;
}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'update_profile_image') {
    try {
        verifyCsrfToken(false);
        $userId = (string)($_SESSION['user_id'] ?? '');
        if ($userId === '') throw new RuntimeException('Session expired. Please login again.');
        if ($residentId === '') throw new RuntimeException('Resident profile not found.');
        $pendingRequestStatusId = getStatusId($conn, 'PendingRequest', 'EditRequest');
        if ($pendingRequestStatusId === null) throw new RuntimeException('Edit request status is missing.');

        $dupStmt = $conn->prepare("
            SELECT request_id
            FROM resident_edit_requesttbl
            WHERE resident_id = ? AND request_type = 'profile' AND status_id = ?
            LIMIT 1
        ");
        if (!$dupStmt) throw new RuntimeException('Failed to check pending edit request.');
        $dupStmt->bind_param("si", $residentId, $pendingRequestStatusId);
        $dupStmt->execute();
        $dup = $dupStmt->get_result()->fetch_assoc();
        $dupStmt->close();
        if ($dup) throw new RuntimeException('You already have a pending profile edit request.');

        if (!isset($_FILES['profile_image']) || !is_array($_FILES['profile_image'])) {
            throw new RuntimeException('Please select an image to upload.');
        }
        $uploadError = app_upload_validate_file($_FILES['profile_image'], 'resident', 'Image', true);
        if ($uploadError !== null) throw new RuntimeException($uploadError);
        $tmpName = (string)($_FILES['profile_image']['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) throw new RuntimeException('Invalid upload source.');
        $size = @filesize($tmpName);
        if ($size === false || (int)$size <= 0) throw new RuntimeException('Uploaded image is empty.');

        $origName = (string)($_FILES['profile_image']['name'] ?? '');
        $ext = strtolower((string)pathinfo($origName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            throw new RuntimeException('Invalid image type. Allowed: JPG, JPEG, PNG, WEBP.');
        }
        $imgInfo = @getimagesize($tmpName);
        if ($imgInfo === false) {
            throw new RuntimeException('Uploaded file must be a valid image.');
        }

        $uploadDir = __DIR__ . "/../UnifiedFileAttachment/Documents/{$userId}/";
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
            throw new RuntimeException('Failed to prepare upload directory.');
        }
        $base = "2x2Picture" . $userId;
        $index = 0;
        do {
            $fileName = $base . ($index > 0 ? "_{$index}" : '') . "." . $ext;
            $target = rtrim($uploadDir, "/") . "/" . $fileName;
            $index++;
        } while (file_exists($target));

        if (!move_uploaded_file($tmpName, $target)) {
            throw new RuntimeException('Failed to upload image.');
        }

        $statusIdVerify = getStatusId($conn, 'PendingReview', 'ResidentDocumentProfiling');
        if ($statusIdVerify === null) throw new RuntimeException('Document verification status is missing.');

        $changesJson = json_encode(['profile_image' => '2x2 Picture'], JSON_UNESCAPED_SLASHES);
        $requestId = insertResidentEditRequest(
            $conn,
            (string)$residentId,
            (string)$userId,
            'profile',
            (int)$pendingRequestStatusId,
            $changesJson
        );
        if ($requestId <= 0) throw new RuntimeException('Failed to create edit request.');

        $docTypeId = getDocumentTypeIdByCategory($conn, '2x2 Picture', 'EditRequest');
        $sourceType = 'ResidentEditRequest';
        $sourceId = (string)$requestId;
        $remarks = 'edit_request_profile_image';
        $idNumber = null;
        $filePathDb = toDbWebPath($target);
        insertUnifiedFileAttachment($conn, [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'document_type_id' => $docTypeId,
            'file_name' => $fileName,
            'file_path' => $filePathDb,
            'file_type' => $ext,
            'user_id_uploaded_by' => $userId,
            'status_id_verify' => $statusIdVerify,
            'remarks' => $remarks,
            'id_number' => $idNumber,
        ], 'uploaded image');
        rp_set_image_flash('success', 'Profile image change request submitted for review.');
    } catch (Throwable $e) {
        rp_set_image_flash('danger', $e->getMessage());
    }
    header('Location: ' . appUrl('/Resident-End/resident_profile.php'));
    exit;
}

if ($residentId !== '' && isset($conn) && $conn instanceof mysqli) {
    $stmtPic = $conn->prepare("
        SELECT uf.file_path
        FROM unifiedfileattachmenttbl uf
        INNER JOIN documenttypelookuptbl dt
            ON uf.document_type_id = dt.document_type_id
        LEFT JOIN statuslookuptbl s
            ON uf.status_id_verify = s.status_id
        LEFT JOIN resident_edit_requesttbl rer
            ON uf.source_type = 'ResidentEditRequest'
           AND rer.request_id = uf.source_id
        LEFT JOIN statuslookuptbl rs
            ON rer.status_id = rs.status_id
        WHERE LOWER(dt.document_type_name) = LOWER('2x2 Picture')
          AND (dt.document_category = 'ResidentProfiling' OR dt.document_category = 'EditRequest' OR dt.document_category IS NULL)
          AND (
                (
                    uf.source_type IN ('ResidentProfiling', 'RESIDENT_PROFILE')
                    AND uf.source_id = ?
                    AND (s.status_name = 'Verified' OR s.status_name = 'Approved')
                )
                OR
                (
                    uf.source_type = 'ResidentEditRequest'
                    AND rer.resident_id = ?
                    AND rer.request_type = 'profile'
                    AND rs.status_name = 'ApprovedRequest'
                )
          )
        ORDER BY uf.upload_timestamp DESC, uf.attachment_id DESC
        LIMIT 1
    ");
    if ($stmtPic) {
        $stmtPic->bind_param("ss", $residentId, $residentId);
        $stmtPic->execute();
        $stmtPic->bind_result($verifiedPicPath);
        if ($stmtPic->fetch() && !empty($verifiedPicPath)) {
            $publicPath = toPublicPath($verifiedPicPath);
            if (!empty($publicPath) && publicPathExists($publicPath)) {
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
    
  <link rel="icon" href="../Images/favicon_sanjose.png?v=20260211">
	<title>Resident Profile</title>

    <script>
      // Used by JS change-phone flow to decide if email OTP option is allowed.
      window.RESIDENT_PROFILE_EMAIL_VERIFIED = <?= $emailVerified ? 'true' : 'false' ?>;
      window.RESIDENT_PROFILE_EDIT_ALLOWED = <?= $canEditProfile ? 'true' : 'false' ?>;
      window.RESIDENT_PROFILE_EDIT_BLOCK_MESSAGE = <?= json_encode($editBlockMessage, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
      window.RESIDENT_PROFILE_AGE = <?= $computedAge !== '' ? (int)$computedAge : 'null' ?>;
      window.RESIDENT_PROFILE_SEX = <?= json_encode((string)($residentinformationtbl['sex'] ?? ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
      window.RESIDENT_CSRF_TOKEN = <?= json_encode($residentCsrfToken, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    </script>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <script src="../JS-Script-Files/modalHandler.js" defer></script>
  <script src="../JS-Script-Files/Resident-End/profileOccupation.js" defer></script>
  <script src="../JS-Script-Files/Resident-End/profileSidebar.js" defer></script>
  <script src="../JS-Script-Files/Resident-End/profileVerifyEmail.js" defer></script>
  <script src="../JS-Script-Files/Resident-End/profileTabs.js" defer></script>
  <script src="../JS-Script-Files/Resident-End/profileAddress.js" defer></script>
  <script src="../JS-Script-Files/Resident-End/profileEmergency.js" defer></script>
	  <script src="../JS-Script-Files/Resident-End/profileChangePassword.js?v=20260215-1" defer></script>
	  <script src="../JS-Script-Files/Resident-End/profileChangePhone.js?v=20260215-1" defer></script>
	  <script src="../JS-Script-Files/Resident-End/profileChangeEmail.js?v=20260215-1" defer></script>
  <script src="../JS-Script-Files/Resident-End/profileUploadedDocuments.js?v=20260623-1" defer></script>
	  <script src="../JS-Script-Files/Resident-End/profileEdit.js?v=20260804-head-of-family" defer></script>
	    <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/residentDashboard.css">
    <style>
      .resident-avatar-wrap {
        position: relative;
        width: 170px;
        height: 170px;
      }
      .resident-avatar-wrap #img-profileAvatar {
        width: 170px;
        height: 170px;
        object-fit: cover;
      }
      .resident-avatar-edit-btn {
        position: absolute;
        right: 8px;
        bottom: 8px;
        width: 34px;
        height: 34px;
        border-radius: 999px;
        border: 1px solid #d0d5dd;
        background: #ffffff;
        color: #175cd3;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 10px rgba(0,0,0,.2);
      }
      .resident-avatar-edit-btn:hover {
        background: #eff6ff;
        color: #1e3a8a;
      }
      .upload-dropzone {
        position: relative;
        border: 1.5px dashed #cbd5e1;
        border-radius: 10px;
        background: #f8fafc;
        min-height: 92px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 14px;
        transition: border-color .2s ease, background-color .2s ease, box-shadow .2s ease;
      }
      .upload-dropzone:hover {
        border-color: #f97316;
        background: #fff7ed;
      }
      .upload-dropzone.is-dragover {
        border-color: #f97316;
        background: #fff7ed;
        box-shadow: 0 0 0 3px rgba(249, 115, 22, .12);
      }
      .upload-dropzone__content {
        text-align: center;
        pointer-events: none;
      }
      .upload-dropzone__title {
        font-weight: 600;
        color: #1f2937;
      }
      .upload-dropzone__meta {
        font-size: .82rem;
        color: #6b7280;
      }
      .upload-dropzone-input {
        position: absolute;
        inset: 0;
        opacity: 0;
        cursor: pointer;
      }
      .uploaded-docs-shell {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 1rem;
        box-shadow: 0 0.125rem 0.25rem rgba(15, 23, 42, 0.04);
        overflow: hidden;
      }
      .uploaded-docs-shell .card-header {
        background: #ffffff;
        border-bottom: 1px solid #e5e7eb;
        padding: 1rem 1.2rem;
      }
      .uploaded-docs-shell .card-body {
        padding: 1.15rem 1.2rem 1.2rem;
      }
      .uploaded-docs-toolbar {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
      }
      .uploaded-docs-title {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 800;
        color: #111827;
      }
      .uploaded-docs-copy {
        margin: 0.28rem 0 0;
        color: #6b7280;
        font-size: 0.92rem;
        line-height: 1.55;
      }
      .uploaded-docs-actions {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-left: auto;
      }
      .uploaded-docs-summary {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        background: linear-gradient(180deg, #fff6ec 0%, #ffe9d1 100%);
        color: #a35300;
        border: 1px solid rgba(254, 153, 60, 0.45);
        border-radius: 999px;
        padding: 8px 14px;
        font-weight: 700;
      }
      .uploaded-docs-summary .count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 28px;
        height: 28px;
        border-radius: 999px;
        background: #de710c;
        color: #fff;
        padding: 0 8px;
        font-size: 0.85rem;
      }
      .btn-uploaded-refresh {
        border-radius: 0.75rem;
        font-weight: 700;
      }
      .uploaded-docs-table-wrap {
        overflow-x: auto;
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
      }
      .uploaded-docs-table-wrap::-webkit-scrollbar {
        height: 8px;
      }
      .uploaded-docs-table-wrap::-webkit-scrollbar-thumb {
        background: rgba(108, 117, 125, 0.45);
        border-radius: 999px;
      }
      .uploaded-docs-table-wrap::-webkit-scrollbar-track {
        background: transparent;
      }
      .uploaded-docs-table {
        width: 100%;
      }
      .uploaded-docs-table thead th {
        padding: 0.7rem 0.95rem;
        font-size: 0.95rem;
        font-weight: 700;
        color: #111827;
        background: #f7f8fa;
        border-bottom: 1px solid #e5e7eb;
        white-space: normal;
        line-height: 1.2;
      }
      .uploaded-docs-table tbody td {
        padding: 0.78rem 0.95rem;
        font-size: 0.94rem;
        color: #1f2937;
        border-bottom: 1px solid #eceff3;
        vertical-align: middle;
        background: #fff;
      }
      .uploaded-docs-table tbody tr:last-child td {
        border-bottom: 0;
      }
      .uploaded-docs-table tbody tr:hover td {
        background: #fcfcfd;
      }
      .uploaded-doc-name {
        font-weight: 700;
        color: #111827;
      }
      .uploaded-doc-type {
        color: #6b7280;
        font-size: 0.84rem;
      }
      .uploaded-doc-category {
        color: #6b7280;
        font-size: 0.92rem;
      }
      .uploaded-doc-id {
        font-weight: 600;
        color: #7c3f00;
      }
      .uploaded-doc-actions {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        flex-wrap: nowrap;
      }
      .btn-udv-view {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        min-height: 34px;
        padding: 0.42rem 0.78rem;
        border-radius: 0.75rem;
        font-size: 0.82rem;
        line-height: 1.15;
        font-weight: 700 !important;
        color: #fff !important;
        border: 1px solid #de710c !important;
        background: #de710c !important;
        box-shadow: 0 8px 18px rgba(222, 113, 12, 0.14);
      }
      .btn-udv-view:hover,
      .btn-udv-view:focus-visible {
        color: #fff !important;
        border-color: #b95606 !important;
        background: #b95606 !important;
      }
      #uploadedDocsCards {
        display: none;
      }
      .uploaded-doc-card {
        border: 1px solid #eceff3;
        border-radius: 14px;
        padding: 0.95rem;
        background: #fff;
        margin-bottom: 0.75rem;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
      }
      .uploaded-doc-card-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
      }
      .uploaded-doc-card-meta {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.5rem;
      }
      .uploaded-doc-label {
        font-size: 0.78rem;
        color: #495057;
        text-transform: uppercase;
        letter-spacing: .02em;
        font-weight: 800;
      }
      .uploaded-doc-value {
        font-size: 0.96rem;
        color: #212529;
        word-break: break-word;
        white-space: normal;
      }
      #modalUploadedDocViewer .modal-dialog {
        max-width: 1850px;
        width: min(97vw, 1850px);
      }
      #modalUploadedDocViewer .modal-content {
        border: 1px solid rgba(15, 23, 42, 0.12);
        border-radius: 1.1rem;
        overflow: hidden;
        box-shadow: 0 20px 55px rgba(15, 23, 42, 0.14);
      }
      #modalUploadedDocViewer .modal-header {
        border-bottom: 1px solid #e9ecef;
        padding: 1.1rem 1.45rem;
        background: #ffffff;
      }
      #modalUploadedDocViewer .modal-body {
        padding: 1rem 1.45rem 1.15rem;
        background: #ffffff;
      }
      #modalUploadedDocViewer .modal-footer {
        border-top: 1px solid #e9ecef;
        padding: 0.95rem 1.45rem 1.1rem;
        justify-content: flex-end;
        background: #ffffff;
      }
      .uploaded-doc-viewer-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 800;
        color: #111827;
        line-height: 1.25;
      }
      .uploaded-doc-preview-shell {
        border: 1px solid #d9e1ea;
        border-radius: 1rem;
        background: #ffffff;
        padding: 1rem;
        min-height: 72vh;
      }
      .uploaded-doc-preview-body {
        width: 100%;
        min-height: calc(72vh - 2rem);
      }
      .uploaded-doc-preview-frame {
        width: 100%;
        height: 72vh;
        border: 0;
        border-radius: 0.8rem;
        background: #ffffff;
      }
      .uploaded-doc-preview-image {
        display: block;
        max-width: 100%;
        max-height: 72vh;
        margin: 0 auto;
        border-radius: 0.85rem;
        background: #ffffff;
      }
      .uploaded-doc-preview-empty {
        min-height: 72vh;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #6b7280;
        background: #ffffff;
        border: 1px dashed #d9e0e7;
        border-radius: 0.85rem;
        padding: 1.25rem;
      }
      .uploaded-doc-modal-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.75rem;
        margin: 0;
        width: 100%;
      }
      .uploaded-doc-modal-btn {
        min-height: 44px;
        border-radius: 0.8rem;
        padding: 0.68rem 1.1rem;
        font-weight: 700;
        box-shadow: none;
        transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease;
      }
      .uploaded-doc-modal-btn:hover,
      .uploaded-doc-modal-btn:focus {
        transform: none;
        box-shadow: none;
      }
      .btn-uploaded-doc-open {
        color: #175cd3;
        background: #ffffff;
        border: 1px solid #175cd3;
      }
      .btn-uploaded-doc-open:hover,
      .btn-uploaded-doc-open:focus {
        color: #1849a9;
        background: #eff4ff;
        border-color: #1849a9;
      }
      .btn-uploaded-doc-close {
        background: #6b7280;
        color: #ffffff;
        border: 1px solid #6b7280;
      }
      .btn-uploaded-doc-close:hover,
      .btn-uploaded-doc-close:focus {
        background: #4b5563;
        color: #ffffff;
        border-color: #4b5563;
      }
      @media (max-width: 991.98px) {
        .uploaded-docs-toolbar {
          flex-direction: column;
          align-items: stretch;
        }
        .uploaded-docs-actions {
          margin-left: 0;
          justify-content: flex-start;
        }
      }
      @media (max-width: 767.98px) {
        .uploaded-docs-table-wrap {
          display: none !important;
        }
        #uploadedDocsCards {
          display: block;
          margin-top: 0.25rem;
        }
        .uploaded-doc-card-header {
          flex-direction: column;
          align-items: stretch;
        }
        .uploaded-doc-preview-shell {
          padding: 0.75rem;
          min-height: 62vh;
        }
        .uploaded-doc-preview-frame {
          height: 62vh;
        }
        .uploaded-doc-preview-empty {
          min-height: 62vh;
        }
        .uploaded-doc-modal-actions {
          flex-direction: column;
          align-items: stretch;
        }
      }
    </style>
</head>

<body>

    <div class="d-flex" style="min-height: 100vh;">

        <?php include __DIR__ . '/includes/resident_sidebar.php'; ?>

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

        <main id="div-mainDisplay" class="flex-grow-1 p-4 p-md-4 bg-light">

            <div class="main-head text-center rounded mb-2">
                <h3 class="mb-0 text-black">ACCOUNT</h3>
            </div>
            <?php if (!empty($profileImageFlash['message'])): ?>
                <div class="alert alert-<?= htmlspecialchars((string)($profileImageFlash['type'] ?: 'info'), ENT_QUOTES, 'UTF-8') ?> mb-2">
                    <?= htmlspecialchars((string)$profileImageFlash['message'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <hr class="mt-1 mb-2">

            <ul class="nav profile-tabs mb-3" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-profile" data-bs-toggle="tab" data-bs-target="#pane-profile" type="button" role="tab" aria-controls="pane-profile" aria-selected="true">
                        Profile
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
                            <div class="resident-avatar-wrap mb-2">
                                <img src="<?= htmlspecialchars($profileImage) ?>"
                                    id="img-profileAvatar"
                                    onerror="this.onerror=null;this.src='../Images/Profile-Placeholder.png';"
                                    class="img-fluid rounded-circle">
                                <button type="button"
                                        class="resident-avatar-edit-btn"
                                        data-bs-toggle="modal"
                                        data-bs-target="#residentProfileImageModal"
                                        aria-label="Edit profile image">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                            </div>
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
                                          $fullNameDisplay .= ' ' . $middleInitial;
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
                                  $pendingSectorLabels = trim((string)($residentinformationtbl['sector_membership_pending_labels'] ?? ''));
                                  $verifiedSectors = [];
                                  if ($sectorText !== '' && strcasecmp($sectorText, 'none') !== 0) {
                                      $verifiedSectors = array_values(array_filter(array_map('trim', explode(',', $sectorText))));
                                  }
                                  $pendingSectors = $pendingSectorLabels !== ''
                                      ? array_values(array_filter(array_map('trim', explode(',', $pendingSectorLabels))))
                                      : [];

                                  $verifiedCount = count($verifiedSectors);
                                  $pendingCount = count($pendingSectors);

                                  $sectorMembershipDisplay = 'N/A';
                                  if ($verifiedCount > 0) {
                                      $sectorMembershipDisplay = implode(', ', $verifiedSectors);
                                  } elseif ($pendingCount === 1) {
                                      $sectorMembershipDisplay = $pendingSectors[0] . ' - Pending Verification';
                                  } elseif ($pendingCount > 1) {
                                      $sectorMembershipDisplay = 'Applied Sectors - Pending Verification';
                                  }
                                ?>
                                <div>
                                    <strong>Sector Membership:</strong> <?= htmlspecialchars($sectorMembershipDisplay, ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <?php if ($verifiedCount > 0 && $pendingCount > 0): ?>
                                    <?php if ($pendingCount === 1): ?>
                                        <div class="small text-muted">
                                            <strong>Other sector membership:</strong> <?= htmlspecialchars($pendingSectors[0] . ' - Pending Verification', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="small text-muted">
                                            <strong>Other sector memberships:</strong> Applied Sectors - Pending Verification
                                        </div>
                                    <?php endif; ?>
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
                                  if ($houseNo !== '' && $streetDisplay !== '') {
                                    $parts[] = $houseNo . ' ' . $streetDisplay;
                                  } else {
                                    if ($houseNo !== '') $parts[] = $houseNo;
                                    if ($streetDisplay !== '') $parts[] = $streetDisplay;
                                  }
                                  if ($phase !== '') $parts[] = $phase;
                                  if ($subdivision !== '') $parts[] = $subdivision;
                                  $parts[] = 'San Jose';
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
                <div class="tab-pane fade" id="pane-uploaded-docs" role="tabpanel" aria-labelledby="tab-uploaded-docs" tabindex="0">
                    <div class="card uploaded-docs-shell shadow-sm mb-4">
                        <div class="card-header">
                            <div class="uploaded-docs-toolbar">
                                <div>
                                    <h2 class="uploaded-docs-title">Verified Uploaded Documents</h2>
                                    <p class="uploaded-docs-copy">Only documents marked as verified by the barangay will appear here.</p>
                                </div>
                                <div class="uploaded-docs-actions">
                                    <div class="uploaded-docs-summary">
                                        <span>Verified Files</span>
                                        <span class="count" id="uploadedDocsCount">0</span>
                                    </div>
                                    <button class="btn btn-outline-secondary btn-sm btn-uploaded-refresh" id="btnRefreshUploadedDocs" type="button">
                                        Refresh
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="uploadedDocsError" class="alert alert-danger d-none" role="alert"></div>

                            <div id="uploadedDocsLoading" class="text-muted small">Loading...</div>

                            <div class="table-responsive uploaded-docs-table-wrap d-none" id="uploadedDocsTableWrap">
                                <table class="table table-hover align-middle mb-0 uploaded-docs-table">
                                    <thead>
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

                            <div id="uploadedDocsCards" class="mt-2"></div>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

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
                                <option value="Annulled" <?= ($residentinformationtbl['civil_status'] ?? '') === 'Annulled' ? 'selected' : '' ?>>Annulled</option>
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
                            <div class="form-text" id="voterStatusHelp"></div>
                        </div>

                         <div class="col-md-6">
                            <label class="form-label">Employment Status</label>
                        <select class="form-select" name="employment_status" id="employmentStatus" onchange="toggleOccupation()">
                            <option value="Employed" <?= ($residentinformationtbl['employment_status'] == 'Employed') ? 'selected' : '' ?>>Employed</option>
                            <option value="Self-Employed" <?= ($residentinformationtbl['employment_status'] == 'Self-Employed') ? 'selected' : '' ?>>Self-Employed</option>
                            <option value="Unemployed" <?= ($residentinformationtbl['employment_status'] == 'Unemployed') ? 'selected' : '' ?>>Unemployed</option>
                        </select>
                        </div>

                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="editHeadOfFamily">Resident Role</label>
                            <select class="form-select" name="head_of_family" id="editHeadOfFamily" <?= $isHeadOfFamily ? 'disabled' : '' ?>>
                                <option value="0" <?= !$isHeadOfFamily ? 'selected' : '' ?>>Resident</option>
                                <option value="1" <?= $isHeadOfFamily ? 'selected' : '' ?>>Head of the Family</option>
                            </select>
                            <div class="form-text">
                                <?= $isHeadOfFamily
                                    ? 'You are already registered as the head of the family.'
                                    : 'Changing this role requires administrator verification.' ?>
                            </div>
                        </div>
                    </div>

                     <div class="row mb-3" id="occupationRow" style="display: none;">
                        <div class="col-md-12">
                            <label class="form-label">Occupation / Business</label>
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
                          $sectorVerifiedSelected = array_filter(array_map('trim', explode(',', (string)($residentinformationtbl['sector_membership'] ?? ''))));
                          $sectorPendingSelected = array_filter(array_map('trim', explode(',', (string)($residentinformationtbl['sector_membership_pending_labels'] ?? ''))));
                          $sectorSelected = array_values(array_unique(array_merge($sectorVerifiedSelected, $sectorPendingSelected)));
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
                        <?php if (!empty($sectorPendingSelected)): ?>
                            <div class="small text-muted mt-2">
                                Pending sector verification: <?= htmlspecialchars(implode(', ', $sectorPendingSelected), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>
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
                            </select>
                            <label class="form-label">Supporting Document</label>
                            <div class="upload-dropzone" data-upload-input="supportReligionFile">
                                <div class="upload-dropzone__content">
                                    <div class="upload-dropzone__title"><i class="fa-solid fa-upload me-1"></i>Drag and drop files or click to upload</div>
                                    <div class="upload-dropzone__meta" id="supportReligionFileMeta">PDF or image, multiple files allowed</div>
                                </div>
                                <input type="file" class="form-control upload-dropzone-input" id="supportReligionFile" name="supporting_religion_file[]" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple>
                            </div>
                            <div class="form-text">Upload at least one supporting document for this change. You can select multiple files.</div>
                        </div>
                        <div class="doc-required-box mb-3 d-none" id="supportVoterSection">
                            <div class="small fw-semibold text-muted mb-2">For Voter Status Change</div>
                            <label class="form-label">Supporting Document Type</label>
                            <select class="form-select mb-2" id="supportVoterType">
                                <option value="">Select document type</option>
                            </select>
                            <label class="form-label">Supporting Document</label>
                            <div class="upload-dropzone" data-upload-input="supportVoterFile">
                                <div class="upload-dropzone__content">
                                    <div class="upload-dropzone__title"><i class="fa-solid fa-upload me-1"></i>Drag and drop files or click to upload</div>
                                    <div class="upload-dropzone__meta" id="supportVoterFileMeta">PDF or image, multiple files allowed</div>
                                </div>
                                <input type="file" class="form-control upload-dropzone-input" id="supportVoterFile" name="supporting_voter_file[]" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple>
                            </div>
                            <div class="form-text">Upload at least one supporting document for this change. You can select multiple files.</div>
                        </div>
                        <div class="doc-required-box mb-3 d-none" id="supportEmploymentSection">
                            <div class="small fw-semibold text-muted mb-2">For Employment Status Change</div>
                            <label class="form-label">Supporting Document Type</label>
                            <select class="form-select mb-2" id="supportEmploymentType">
                                <option value="">Select document type</option>
                            </select>
                            <label class="form-label">Supporting Document</label>
                            <div class="upload-dropzone" data-upload-input="supportEmploymentFile">
                                <div class="upload-dropzone__content">
                                    <div class="upload-dropzone__title"><i class="fa-solid fa-upload me-1"></i>Drag and drop files or click to upload</div>
                                    <div class="upload-dropzone__meta" id="supportEmploymentFileMeta">PDF or image, multiple files allowed</div>
                                </div>
                                <input type="file" class="form-control upload-dropzone-input" id="supportEmploymentFile" name="supporting_employment_file[]" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple>
                            </div>
                            <div class="form-text">Upload at least one supporting document for this change. You can select multiple files.</div>
                        </div>
                        <div class="doc-required-box mb-3 d-none" id="supportSectorSection">
                            <div class="small fw-semibold text-muted mb-2">For Sector Membership Change</div>
                            <label class="form-label">Supporting Document Type</label>
                            <select class="form-select mb-2" id="supportSectorType">
                                <option value="">Select document type</option>
                            </select>
                            <label class="form-label">Supporting Document</label>
                            <div class="upload-dropzone" data-upload-input="supportSectorFile">
                                <div class="upload-dropzone__content">
                                    <div class="upload-dropzone__title"><i class="fa-solid fa-upload me-1"></i>Drag and drop files or click to upload</div>
                                    <div class="upload-dropzone__meta" id="supportSectorFileMeta">PDF or image, multiple files allowed</div>
                                </div>
                                <input type="file" class="form-control upload-dropzone-input" id="supportSectorFile" name="supporting_sector_file[]" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple>
                            </div>
                            <div class="form-text">Upload at least one supporting document for this change. You can select multiple files.</div>
                        </div>
                    <div class="doc-required-box d-none mb-3" id="nameDocSection">
                        <div class="small fw-semibold text-muted mb-2">For Name Change</div>
                        <div class="row g-2">
                            <div class="col-12">
                                <label class="form-label">Valid ID Type</label>
                                <select class="form-select" id="nameIdType">
                                    <option value="">Select ID</option>
                                    <option value="Philippine Passport">Philippine Passport</option>
                                    <option value="Unified Multi-Purpose ID (UMID)">Unified Multi-Purpose ID (UMID)</option>
                                    <option value="Driver's License">Driver's License</option>
                                    <option value="Professional Regulation Commission (PRC) ID">Professional Regulation Commission (PRC) ID</option>
                                    <option value="Postal ID">Postal ID</option>
                                    <option value="National ID / PhilSys ID">National ID / PhilSys ID</option>
                                    <option value="Social Security System (SSS) ID">Social Security System (SSS) ID</option>
                                    <option value="Government Service Insurance System (GSIS) ID">Government Service Insurance System (GSIS) ID</option>
                                    <option value="PSA Birth Certificate">PSA Birth Certificate</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Valid ID Photo</label>
                                <div class="upload-dropzone" data-upload-input="nameIdFile">
                                    <div class="upload-dropzone__content">
                                        <div class="upload-dropzone__title"><i class="fa-solid fa-upload me-1"></i>Drag and drop files or click to upload</div>
                                        <div class="upload-dropzone__meta" id="nameIdFileMeta">PDF or image, multiple files allowed</div>
                                    </div>
                                    <input type="file" class="form-control upload-dropzone-input" id="nameIdFile" name="name_id_file[]" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple>
                                </div>
                                <div class="form-text">Clear photo of your valid ID. You can select multiple files.</div>
                            </div>
                        </div>
                    </div>
                    <div class="doc-required-box d-none" id="civilStatusDocSection">
                        <div class="small fw-semibold text-muted mb-2">For Civil Status Change</div>
                        <div class="row g-2">
                            <div class="col-12">
                                <label class="form-label" id="civilStatusDocLabel">Supporting Document Type</label>
                                <select class="form-select mb-2" id="civilStatusType">
                                    <option value="">Select document type</option>
                                </select>
                                <label class="form-label">Supporting Document</label>
                                <div class="upload-dropzone" data-upload-input="civilStatusFile">
                                    <div class="upload-dropzone__content">
                                        <div class="upload-dropzone__title"><i class="fa-solid fa-upload me-1"></i>Drag and drop files or click to upload</div>
                                        <div class="upload-dropzone__meta" id="civilStatusFileMeta">PDF or image, multiple files allowed</div>
                                    </div>
                                    <input type="file" class="form-control upload-dropzone-input" id="civilStatusFile" name="civil_status_file[]" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-text" id="civilStatusDocHelp"></div>
                            </div>
                        </div>
                    </div>
                    <div class="doc-required-box d-none" id="studentUntickSection">
                        <div class="small fw-semibold text-muted mb-2">For Student Sector Change</div>
                        <div class="row g-2">
                            <div class="col-12">
                                <label class="form-label">Diploma / Proof (Optional if stopped studying)</label>
                                <div class="upload-dropzone" data-upload-input="studentStatusFile">
                                    <div class="upload-dropzone__content">
                                        <div class="upload-dropzone__title"><i class="fa-solid fa-upload me-1"></i>Drag and drop files or click to upload</div>
                                        <div class="upload-dropzone__meta" id="studentStatusFileMeta">PDF or image, multiple files allowed</div>
                                    </div>
                                    <input type="file" class="form-control upload-dropzone-input" id="studentStatusFile" name="student_status_file[]" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch mt-2">
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

    <?php
        $addressSystemCurrent = strtolower(trim((string)($residentaddresstbl['address_system'] ?? 'house')));
        if (!in_array($addressSystemCurrent, ['house', 'lot_block'], true)) {
            $addressSystemCurrent = 'house';
        }
        $addressUnitCurrent = trim((string)($residentaddresstbl['unit_number'] ?? ''));
        $addressStreetNumberCurrent = trim((string)($residentaddresstbl['street_number'] ?? ''));
        $addressStreetNameCurrent = trim((string)($residentaddresstbl['street_name'] ?? ''));
        $addressPhaseCurrent = trim((string)($residentaddresstbl['phase_number'] ?? ''));
        $addressSubdivisionCurrent = trim((string)($residentaddresstbl['subdivision'] ?? ''));
        $addressAreaCurrent = trim((string)($residentaddresstbl['area_number'] ?? ''));
        $addressHouseOwnershipCurrent = trim((string)($residentaddresstbl['house_ownership'] ?? ''));
        $addressHouseTypeCurrent = trim((string)($residentaddresstbl['house_type'] ?? ''));
        $addressKnownHouseTypes = ['Concrete', 'Semi-Concrete', 'Wood/Light Materials', 'Makeshift/Salvaged Materials', 'Shanty/Informal'];
        $addressHouseTypeIsOther = $addressHouseTypeCurrent !== '' && !in_array($addressHouseTypeCurrent, $addressKnownHouseTypes, true);
        $addressHouseTypeSelectValue = $addressHouseTypeIsOther ? 'Other' : $addressHouseTypeCurrent;

        $addressUnitHouseValue = $addressSystemCurrent === 'house' ? $addressUnitCurrent : '';
        $addressStreetNumberHouseValue = $addressSystemCurrent === 'house' ? $addressStreetNumberCurrent : '';
        $addressStreetNameHouseValue = $addressSystemCurrent === 'house' ? $addressStreetNameCurrent : '';
        $addressPhaseHouseValue = $addressSystemCurrent === 'house' ? $addressPhaseCurrent : '';
        $addressSubdivisionHouseValue = $addressSystemCurrent === 'house' ? $addressSubdivisionCurrent : '';
        $addressAreaHouseValue = $addressSystemCurrent === 'house' ? $addressAreaCurrent : '';

        $addressLotNumberValue = $addressStreetNumberCurrent;
        if ($addressSystemCurrent === 'lot_block') {
            $addressLotNumberValue = preg_replace('/^\s*Lot\s*/i', '', $addressLotNumberValue);
        } else {
            $addressLotNumberValue = '';
        }
        $addressBlockNumberValue = $addressPhaseCurrent;
        if ($addressSystemCurrent === 'lot_block' && $addressBlockNumberValue === '') {
            $addressBlockNumberValue = preg_replace('/^\s*Block\s*/i', '', $addressStreetNameCurrent);
        }
        if ($addressSystemCurrent !== 'lot_block') {
            $addressBlockNumberValue = '';
        }
        $addressStreetNameLotValue = $addressSystemCurrent === 'lot_block'
            ? preg_replace('/^\s*Block\s*/i', '', ($addressPhaseCurrent === '' ? '' : $addressStreetNameCurrent))
            : '';
        if ($addressSystemCurrent === 'lot_block' && preg_match('/^\s*Block\b/i', $addressStreetNameCurrent)) {
            $addressStreetNameLotValue = '';
        }
        $addressUnitLotValue = $addressSystemCurrent === 'lot_block' ? $addressUnitCurrent : '';
        $addressSubdivisionLotValue = $addressSystemCurrent === 'lot_block' ? $addressSubdivisionCurrent : '';
        $addressAreaLotValue = $addressSystemCurrent === 'lot_block' ? $addressAreaCurrent : '';
    ?>

     <div
        class="modal fade"
        id="addAddressModal"
        tabindex="-1"
        data-bs-backdrop="static"
        data-bs-keyboard="false"
        data-current-address-system="<?= htmlspecialchars($addressSystemCurrent, ENT_QUOTES, 'UTF-8') ?>"
        data-current-unit-number="<?= htmlspecialchars($addressUnitCurrent, ENT_QUOTES, 'UTF-8') ?>"
        data-current-street-number="<?= htmlspecialchars($addressStreetNumberCurrent, ENT_QUOTES, 'UTF-8') ?>"
        data-current-street-name="<?= htmlspecialchars($addressStreetNameCurrent, ENT_QUOTES, 'UTF-8') ?>"
        data-current-phase-number="<?= htmlspecialchars($addressPhaseCurrent, ENT_QUOTES, 'UTF-8') ?>"
        data-current-subdivision="<?= htmlspecialchars($addressSubdivisionCurrent, ENT_QUOTES, 'UTF-8') ?>"
        data-current-area-number="<?= htmlspecialchars($addressAreaCurrent, ENT_QUOTES, 'UTF-8') ?>"
        data-current-house-ownership="<?= htmlspecialchars($addressHouseOwnershipCurrent, ENT_QUOTES, 'UTF-8') ?>"
        data-current-house-type="<?= htmlspecialchars($addressHouseTypeCurrent, ENT_QUOTES, 'UTF-8') ?>"
        data-current-residency-duration="<?= htmlspecialchars((string)($residentaddresstbl['residency_duration'] ?? 'Less than 6 months'), ENT_QUOTES, 'UTF-8') ?>"
    >
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header justify-content-center position-relative">
                    <h5 class="modal-title text-center w-100">Change Address</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-warning small mb-3">
                        <?php if ($isHeadOfFamily): ?>
                            Changing your address will update the household address under your family record.
                        <?php else: ?>
                            Changing your address will remove you from your current household.
                        <?php endif; ?>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-12">
                            <label class="form-label mb-1" for="addressSystemEdit">Address System <span class="text-danger">*</span></label>
                            <select class="form-select" id="addressSystemEdit">
                                <option value="">Select</option>
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
                            <select class="form-select" id="addressHouseType">
                                <option value="">Select</option>
                                <?php foreach ($addressKnownHouseTypes as $opt): ?>
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
                    <button class="btn btn-primary" id="btnAddressReview" type="button">Review Changes</button>
                </div>

            </div>
        </div>
    </div>

    <div class="modal fade" id="editAddressUploadModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Supporting Documents</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="doc-required-box mb-3">
                        <div class="small fw-semibold text-muted mb-2">For Address Change</div>
                        <label class="form-label">Supporting Document Type</label>
                        <select class="form-select mb-2" id="addressSupportType">
                            <option value="">Select document type</option>
                            <option value="Contract of Lease">Contract of Lease</option>
                            <option value="Transfer Certificate of Title">Transfer Certificate of Title</option>
                            <option value="Tax Declaration">Tax Declaration</option>
                        </select>
                        <label class="form-label">Supporting Document</label>
                        <div class="upload-dropzone" data-upload-input="addressSupportFile">
                            <div class="upload-dropzone__content">
                                <div class="upload-dropzone__title"><i class="fa-solid fa-upload me-1"></i>Drag and drop files or click to upload</div>
                                <div class="upload-dropzone__meta" id="addressSupportFileMeta">PDF or image, multiple files allowed</div>
                            </div>
                            <input type="file" class="form-control upload-dropzone-input" id="addressSupportFile" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple>
                        </div>
                        <div class="form-text">Upload at least one proof of address document for this change. You can select multiple files.</div>
                    </div>
                    <div id="addressUploadResult" class="small mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" id="btnAddressBackToForm" type="button">Back</button>
                    <button class="btn btn-primary" id="btnSaveAddress" type="button">Submit Request</button>
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
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="uploaded-doc-viewer-title w-100 mb-0" id="udvTitle">Document Preview</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="uploaded-doc-preview-shell">
                            <div id="udvBody" class="uploaded-doc-preview-body"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="uploaded-doc-modal-actions">
                            <a href="#" class="btn btn-uploaded-doc-open uploaded-doc-modal-btn d-none" id="udvOpenNewTab" target="_blank" rel="noopener">Open Attachment in New Tab</a>
                            <button type="button" class="btn btn-uploaded-doc-close uploaded-doc-modal-btn" data-bs-dismiss="modal">Close</button>
                        </div>
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
    
    <div class="modal fade" id="residentProfileImageModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-black">Update Profile Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_profile_image">
                        <?= csrfTokenField() ?>
                        <div class="alert alert-info small mb-3">
                            <div class="fw-bold mb-1">Upload Requirements</div>
                            <ul class="mb-0 ps-3">
                                <li>Accepted formats: JPG, JPEG, PNG, WEBP</li>
                                <li>Maximum file size: 25MB</li>
                                <li>Use a clear 2x2-style portrait image for review</li>
                                <li>Change will be submitted as a pending edit request</li>
                            </ul>
                        </div>
                        <div class="text-center mb-3">
                            <img id="residentProfileImagePreview"
                                 src="<?= htmlspecialchars($profileImage, ENT_QUOTES, 'UTF-8') ?>"
                                 alt="Profile Preview"
                                 onerror="this.onerror=null;this.src='../Images/Profile-Placeholder.png';"
                                 class="rounded-circle border"
                                 style="width:120px;height:120px;object-fit:cover;">
                        </div>
                        <label class="form-label">Choose image (JPG, JPEG, PNG, WEBP)</label>
                        <div class="upload-dropzone" data-upload-input="residentProfileImageInput">
                            <div class="upload-dropzone__content">
                                <div class="upload-dropzone__title"><i class="fa-solid fa-upload me-1"></i>Drag and drop image or click to upload</div>
                                <div class="upload-dropzone__meta" id="residentProfileImageInputMeta">JPG, JPEG, PNG, WEBP only</div>
                            </div>
                            <input type="file"
                                   class="form-control upload-dropzone-input"
                                   id="residentProfileImageInput"
                                   name="profile_image"
                                   accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                   required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Image</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="../JS-Script-Files/Resident-End/dateFieldModal.js?v=20260707-date-proxy-white"></script>
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

            document.addEventListener("DOMContentLoaded", () => {
                const dropzones = document.querySelectorAll(".upload-dropzone[data-upload-input]");
                if (!dropzones.length) return;

                const setMeta = (input, metaEl) => {
                    if (!metaEl || !input) return;
                    const files = input.files ? Array.from(input.files) : [];
                    if (!files.length) return;
                    if (files.length === 1) {
                        metaEl.textContent = files[0].name;
                        return;
                    }
                    metaEl.textContent = files.length + " files selected";
                };

                dropzones.forEach((zone) => {
                    const inputId = zone.getAttribute("data-upload-input");
                    if (!inputId) return;
                    const input = document.getElementById(inputId);
                    const meta = document.getElementById(inputId + "Meta");
                    if (!input) return;

                    input.addEventListener("change", () => setMeta(input, meta));

                    ["dragenter", "dragover"].forEach((eventName) => {
                        zone.addEventListener(eventName, (event) => {
                            event.preventDefault();
                            zone.classList.add("is-dragover");
                        });
                    });
                    ["dragleave", "dragend"].forEach((eventName) => {
                        zone.addEventListener(eventName, (event) => {
                            event.preventDefault();
                            zone.classList.remove("is-dragover");
                        });
                    });
                    zone.addEventListener("drop", (event) => {
                        event.preventDefault();
                        zone.classList.remove("is-dragover");
                        const droppedFiles = event.dataTransfer ? event.dataTransfer.files : null;
                        if (!droppedFiles || !droppedFiles.length) return;
                        const dt = new DataTransfer();
                        Array.from(droppedFiles).forEach((file) => dt.items.add(file));
                        input.files = dt.files;
                        input.dispatchEvent(new Event("change", { bubbles: true }));
                    });
                });
            });

            document.addEventListener("DOMContentLoaded", () => {
                const input = document.getElementById("residentProfileImageInput");
                const preview = document.getElementById("residentProfileImagePreview");
                if (!input || !preview) return;
                input.addEventListener("change", () => {
                    const file = input.files && input.files[0] ? input.files[0] : null;
                    if (!file) return;
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        if (e.target && typeof e.target.result === "string") {
                            preview.src = e.target.result;
                        }
                    };
                    reader.readAsDataURL(file);
                });
            });

            document.addEventListener("DOMContentLoaded", () => {
                const sidebarAvatar = document.getElementById("img-sidebarAvatar");
                const profileAvatar = document.getElementById("img-profileAvatar");
                const previewAvatar = document.getElementById("residentProfileImagePreview");
                if (!sidebarAvatar || !profileAvatar) return;

                const copyFromSidebar = () => {
                    const src = String(sidebarAvatar.getAttribute("src") || "").trim();
                    if (!src) return;
                    profileAvatar.src = src;
                    if (previewAvatar) {
                        previewAvatar.src = src;
                    }
                };

                copyFromSidebar();
                sidebarAvatar.addEventListener("load", copyFromSidebar);
            });
        </script>
</body>
</html>
