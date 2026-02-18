<?php
session_start();
require_once __DIR__ . "/../General/connection.php";
require_once __DIR__ . "/../General/residentTransaction.php";

use setasign\Fpdi\Fpdi;

require_once __DIR__ . "/../../composer-email-handler/vendor/autoload.php";

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
    exit;
}

$conn->set_charset('utf8mb4');
$userId = (string)$_SESSION['user_id'];

function cleanString($v): string {
    $text = trim((string)$v);
    $text = preg_replace('/\s+/u', ' ', $text);
    return (string)$text;
}

function getStatusId(mysqli $conn, string $name, string $type): int {
    $q = $conn->prepare("SELECT status_id FROM statuslookuptbl WHERE status_name=? AND status_type=? LIMIT 1");
    if (!$q) throw new Exception("Prepare failed (getStatusId): " . $conn->error);
    $q->bind_param("ss", $name, $type);
    $q->execute();
    $res = $q->get_result()->fetch_assoc();
    $q->close();
    if (!$res || !isset($res['status_id'])) {
        throw new Exception("Status not found: {$name} ({$type})");
    }
    return (int)$res['status_id'];
}

function getDocumentTypeId(mysqli $conn, string $name): int {
    $q = $conn->prepare("SELECT document_type_id FROM documenttypelookuptbl WHERE LOWER(document_type_name)=LOWER(?) AND document_category='ResidentProfiling' LIMIT 1");
    if (!$q) throw new Exception("Prepare failed (getDocumentTypeId): " . $conn->error);
    $q->bind_param("s", $name);
    $q->execute();
    $res = $q->get_result()->fetch_assoc();
    $q->close();
    if ($res && isset($res['document_type_id'])) {
        return (int)$res['document_type_id'];
    }

    $ins = $conn->prepare("INSERT INTO documenttypelookuptbl (document_type_name, document_category) VALUES (?, 'ResidentProfiling')");
    if (!$ins) throw new Exception("Prepare failed (create document type): " . $conn->error);
    $ins->bind_param("s", $name);
    if (!$ins->execute()) {
        $ins->close();
        throw new Exception("Failed to create document type: {$name}");
    }
    $id = (int)$ins->insert_id;
    $ins->close();
    return $id;
}

function isHeicExt(string $ext): bool {
    return in_array(strtolower($ext), ['heic', 'heif'], true);
}

function sanitizeDocTypeToken(string $docType): string {
    $token = preg_replace('/[^A-Za-z0-9]+/', '', $docType);
    return $token !== '' ? $token : 'Document';
}

function buildAttachmentFileName(string $docType, string $userId, string $ext, int $index = 0): string {
    $base = sanitizeDocTypeToken($docType) . $userId;
    if ($index > 0) $base .= '_' . $index;
    return $base . '.' . strtolower($ext);
}

function toDbWebPath(string $absolutePath): string {
    $absolutePath = str_replace("\\", "/", trim($absolutePath));
    $marker = "/UnifiedFileAttachment/";
    $markerPos = strpos($absolutePath, $marker);
    if ($markerPos !== false) {
        return ltrim(substr($absolutePath, $markerPos), "/");
    }
    return ltrim($absolutePath, "/");
}

function moveUploadedFileWithDocName(string $tmpName, string $dir, string $docType, string $userId, string $ext): array {
    $index = 0;
    $fileName = buildAttachmentFileName($docType, $userId, $ext, $index);
    $target = rtrim($dir, "/") . "/" . $fileName;

    while (file_exists($target)) {
        $index++;
        $fileName = buildAttachmentFileName($docType, $userId, $ext, $index);
        $target = rtrim($dir, "/") . "/" . $fileName;
    }

    if (!move_uploaded_file($tmpName, $target)) {
        throw new Exception("Failed to upload file.");
    }

    return [
        'file_name' => $fileName,
        'file_path' => toDbWebPath($target),
        'disk_path' => $target,
    ];
}

function appendImagePageToPdf(Fpdi $pdf, string $imagePath): void {
    $imageInfo = @getimagesize($imagePath);
    if ($imageInfo === false || !isset($imageInfo[0], $imageInfo[1])) {
        throw new Exception("Invalid image file for PDF merge.");
    }

    $imgW = (float)$imageInfo[0];
    $imgH = (float)$imageInfo[1];
    $orientation = $imgW > $imgH ? 'L' : 'P';
    $pdf->AddPage($orientation, 'A4');

    $margin = 10.0;
    $pageW = (float)$pdf->GetPageWidth();
    $pageH = (float)$pdf->GetPageHeight();
    $maxW = $pageW - ($margin * 2);
    $maxH = $pageH - ($margin * 2);

    $scale = min($maxW / $imgW, $maxH / $imgH);
    $drawW = $imgW * $scale;
    $drawH = $imgH * $scale;
    $x = ($pageW - $drawW) / 2;
    $y = ($pageH - $drawH) / 2;

    $pdf->Image($imagePath, $x, $y, $drawW, $drawH);
}

function appendPdfFilePages(Fpdi $pdf, string $pdfPath): void {
    $pageCount = $pdf->setSourceFile($pdfPath);
    for ($i = 1; $i <= $pageCount; $i++) {
        $tpl = $pdf->importPage($i);
        $size = $pdf->getTemplateSize($tpl);
        $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
        $pdf->AddPage($orientation, [$size['width'], $size['height']]);
        $pdf->useTemplate($tpl);
    }
}

function buildMergedIdPdf(string $frontPath, string $frontExt, string $backPath, string $backExt, string $outputPath): void {
    $pdf = new Fpdi();
    if ($frontExt === 'pdf') {
        appendPdfFilePages($pdf, $frontPath);
    } else {
        appendImagePageToPdf($pdf, $frontPath);
    }

    if ($backExt === 'pdf') {
        appendPdfFilePages($pdf, $backPath);
    } else {
        appendImagePageToPdf($pdf, $backPath);
    }

    $pdf->Output('F', $outputPath);
}

function upsertSectorMembershipStatusFromUpload(mysqli $conn, string $residentId, string $sectorKey, int $sectorStatusId, int $latestAttachmentId): void {
    $stmt = $conn->prepare("\n        INSERT INTO residentsectormembershiptbl\n            (resident_id, sector_key, sector_status_id, latest_attachment_id, remarks, upload_timestamp, last_update_user_id)\n        VALUES\n            (?, ?, ?, ?, NULL, NOW(), NULL)\n        ON DUPLICATE KEY UPDATE\n            sector_status_id = VALUES(sector_status_id),\n            latest_attachment_id = VALUES(latest_attachment_id),\n            remarks = NULL,\n            upload_timestamp = VALUES(upload_timestamp),\n            last_update_user_id = NULL,\n            updated_at = CURRENT_TIMESTAMP\n    ");
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('ssii', $residentId, $sectorKey, $sectorStatusId, $latestAttachmentId);
    $stmt->execute();
    $stmt->close();
}

function resolveRequirementState(int $pending, int $rejected, int $verified): string {
    if ($pending > 0) return 'pending';
    if ($rejected > 0) return 'rejected';
    if ($verified > 0) return 'verified';
    return 'missing';
}

function buildUploadTransactionSourceType(): string {
    // Keep source_type unique per submission to preserve transaction history on resubmits.
    // Max column length is 50 chars.
    $suffix = '';
    try {
        $suffix = substr(bin2hex(random_bytes(3)), 0, 6);
    } catch (Throwable $e) {
        $suffix = substr((string)mt_rand(100000, 999999), 0, 6);
    }
    return 'RESIDENT_UPLOAD_' . date('YmdHis') . '_' . $suffix;
}

function getCurrentUploadRequirements(mysqli $conn, string $residentId): array {
    $sql = "
        SELECT
            SUM(CASE WHEN dt.document_type_name = '2x2 Picture' AND s.status_name = 'Verified' THEN 1 ELSE 0 END) AS verified_2x2,
            SUM(CASE WHEN dt.document_type_name = '2x2 Picture' AND s.status_name = 'PendingReview' THEN 1 ELSE 0 END) AS pending_2x2,
            SUM(CASE WHEN dt.document_type_name = '2x2 Picture' AND s.status_name = 'Rejected' THEN 1 ELSE 0 END) AS rejected_2x2,
            SUM(CASE WHEN dt.document_type_name <> '2x2 Picture'
                        AND (uf.remarks IS NULL OR uf.remarks NOT LIKE 'sector:%')
                        AND s.status_name = 'Verified'
                     THEN 1 ELSE 0 END) AS verified_proof,
            SUM(CASE WHEN dt.document_type_name <> '2x2 Picture'
                        AND (uf.remarks IS NULL OR uf.remarks NOT LIKE 'sector:%')
                        AND s.status_name = 'PendingReview'
                     THEN 1 ELSE 0 END) AS pending_proof,
            SUM(CASE WHEN dt.document_type_name <> '2x2 Picture'
                        AND (uf.remarks IS NULL OR uf.remarks NOT LIKE 'sector:%')
                        AND s.status_name = 'Rejected'
                     THEN 1 ELSE 0 END) AS rejected_proof
        FROM unifiedfileattachmenttbl uf
        INNER JOIN documenttypelookuptbl dt
            ON uf.document_type_id = dt.document_type_id
        LEFT JOIN statuslookuptbl s
            ON uf.status_id_verify = s.status_id
        WHERE uf.source_type = 'ResidentProfiling'
          AND uf.source_id = ?
          AND dt.document_category = 'ResidentProfiling'
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Prepare failed (requirements lookup): ' . $conn->error);
    $stmt->bind_param('s', $residentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $pictureState = resolveRequirementState(
        (int)($row['pending_2x2'] ?? 0),
        (int)($row['rejected_2x2'] ?? 0),
        (int)($row['verified_2x2'] ?? 0)
    );
    $proofState = resolveRequirementState(
        (int)($row['pending_proof'] ?? 0),
        (int)($row['rejected_proof'] ?? 0),
        (int)($row['verified_proof'] ?? 0)
    );

    return [
        'picture_state' => $pictureState,
        'proof_state' => $proofState,
        'needs_picture' => in_array($pictureState, ['missing', 'rejected'], true),
        'needs_proof' => in_array($proofState, ['missing', 'rejected'], true),
    ];
}

try {
    $residentId = '';
    $residentStmt = $conn->prepare("SELECT resident_id FROM residentinformationtbl WHERE user_id = ? LIMIT 1");
    if (!$residentStmt) {
        throw new Exception('Prepare failed (resident lookup): ' . $conn->error);
    }
    $residentStmt->bind_param('s', $userId);
    $residentStmt->execute();
    $residentStmt->bind_result($residentId);
    if (!$residentStmt->fetch() || $residentId === '') {
        $residentStmt->close();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Resident profile not found.']);
        exit;
    }
    $residentStmt->close();

    $requirements = getCurrentUploadRequirements($conn, $residentId);
    $needsProof = (bool)$requirements['needs_proof'];
    $needsPicture = (bool)$requirements['needs_picture'];

    $proofType = cleanString($_POST['proofType'] ?? '');
    if ($needsProof && !in_array($proofType, ['ID', 'Document'], true)) {
        throw new Exception('Please select a valid proof type.');
    }
    if (!$needsProof) {
        $proofType = '';
    }

    $idType = cleanString($_POST['idType'] ?? '');
    $idNumber = cleanString($_POST['idNumber'] ?? '');
    $documentType = cleanString($_POST['documentType'] ?? '');

    $selectedSectors = [];
    if (isset($_POST['sectorMembership']) && is_array($_POST['sectorMembership'])) {
        $selectedSectors = array_values(array_filter(array_map('trim', $_POST['sectorMembership']), static function ($v) {
            return $v !== '';
        }));
    }

    $allowedIdTypes = ['Passport', "Driver's License", 'PhilHealth ID', "Voter's ID", 'National ID', 'Barangay ID'];
    $allowedDocTypes = ['Billing Statement', 'HOA Signed Certification of Residency'];

    if ($proofType === 'ID') {
        if (!in_array($idType, $allowedIdTypes, true)) {
            throw new Exception('Please select a valid ID type.');
        }
        if (!preg_match('/^[A-Za-z0-9-]{3,50}$/', $idNumber)) {
            throw new Exception('ID Number must be 3-50 characters (letters, numbers, hyphen only).');
        }
    }

    if ($proofType === 'Document' && !in_array($documentType, $allowedDocTypes, true)) {
        throw new Exception('Please select a valid document type.');
    }

    if ($needsPicture && (!isset($_FILES['picture']) || (int)$_FILES['picture']['error'] !== UPLOAD_ERR_OK)) {
        throw new Exception('2x2 picture is required.');
    }

    if (!$needsProof && !$needsPicture) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'There are no missing required documents to upload right now.'
        ]);
        exit;
    }

    $conn->begin_transaction();

    $statusVerifyId = getStatusId($conn, 'PendingReview', 'ResidentDocumentProfiling');
    $rejectedDocStatusId = getStatusId($conn, 'Rejected', 'ResidentDocumentProfiling');
    $pendingResidentStatusId = getStatusId($conn, 'PendingVerification', 'Resident');
    $didUploadAny = false;
    $didUploadProof = false;
    $didUploadSector = false;
    $uploadedSectorKeys = [];
    $proofAttachmentIds = [];
    $sectorAttachmentIds = [];
    $txSourceType = buildUploadTransactionSourceType();

    $sourceType = 'ResidentProfiling';
    $userFolder = preg_replace('/[^A-Za-z0-9_-]/', '', $userId);
    if ($userFolder === '') {
        throw new Exception('Invalid user folder name.');
    }

    $uploadDirDocs = __DIR__ . "/../../UnifiedFileAttachment/Documents/{$userFolder}/";
    $uploadDirPic = __DIR__ . "/../../UnifiedFileAttachment/IDPictures/{$userFolder}/";
    if (!is_dir($uploadDirDocs) && !mkdir($uploadDirDocs, 0777, true)) {
        throw new Exception('Failed to create document upload directory.');
    }
    if (!is_dir($uploadDirPic) && !mkdir($uploadDirPic, 0777, true)) {
        throw new Exception('Failed to create ID picture upload directory.');
    }

    if ($needsProof) {
        $cleanupProofRejected = $conn->prepare("
            DELETE uf
            FROM unifiedfileattachmenttbl uf
            INNER JOIN documenttypelookuptbl dt ON uf.document_type_id = dt.document_type_id
            WHERE uf.source_type = 'ResidentProfiling'
              AND uf.source_id = ?
              AND uf.status_id_verify = ?
              AND dt.document_category = 'ResidentProfiling'
              AND dt.document_type_name <> '2x2 Picture'
              AND (uf.remarks IS NULL OR uf.remarks NOT LIKE 'sector:%')
        ");
        if ($cleanupProofRejected) {
            $cleanupProofRejected->bind_param('si', $residentId, $rejectedDocStatusId);
            $cleanupProofRejected->execute();
            $cleanupProofRejected->close();
        }
    }

    if ($needsPicture) {
        $cleanup2x2Rejected = $conn->prepare("
            DELETE uf
            FROM unifiedfileattachmenttbl uf
            INNER JOIN documenttypelookuptbl dt ON uf.document_type_id = dt.document_type_id
            WHERE uf.source_type = 'ResidentProfiling'
              AND uf.source_id = ?
              AND uf.status_id_verify = ?
              AND dt.document_category = 'ResidentProfiling'
              AND dt.document_type_name = '2x2 Picture'
        ");
        if ($cleanup2x2Rejected) {
            $cleanup2x2Rejected->bind_param('si', $residentId, $rejectedDocStatusId);
            $cleanup2x2Rejected->execute();
            $cleanup2x2Rejected->close();
        }
    }

    if ($proofType === 'ID') {
        $isPassport = strcasecmp($idType, 'Passport') === 0;
        $idFiles = $isPassport ? ['idFront'] : ['idFront', 'idBack'];
        foreach ($idFiles as $fileKey) {
            if (!isset($_FILES[$fileKey]) || (int)$_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
                throw new Exception($isPassport ? 'Passport file is required.' : 'ID Front and Back are required.');
            }
        }

        $allowedIdExt = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        if ($isPassport) {
            $tmpName = (string)$_FILES['idFront']['tmp_name'];
            $name = basename((string)$_FILES['idFront']['name']);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (isHeicExt($ext)) throw new Exception('HEIC is not supported on the server. Please upload JPG or PNG.');
            if (!in_array($ext, $allowedIdExt, true)) throw new Exception('Invalid file type for passport.');

            $moved = moveUploadedFileWithDocName($tmpName, $uploadDirDocs, $idType, $userId, $ext);
            $docTypeId = getDocumentTypeId($conn, $idType);
            $remarks = 'idSingle';

            $ins = $conn->prepare("\n                INSERT INTO unifiedfileattachmenttbl\n                (source_type, source_id, document_type_id, file_name, file_path, file_type, user_id_uploaded_by, status_id_verify, remarks, id_number)\n                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n            ");
            if (!$ins) throw new Exception('Prepare failed (insert passport): ' . $conn->error);
            $ins->bind_param('ssissssiss', $sourceType, $residentId, $docTypeId, $moved['file_name'], $moved['file_path'], $ext, $userId, $statusVerifyId, $remarks, $idNumber);
            if (!$ins->execute()) throw new Exception('Passport attachment insert failed: ' . $ins->error);
            $newAttachmentId = (int)$ins->insert_id;
            $ins->close();
            $didUploadAny = true;
            $didUploadProof = true;
            if ($newAttachmentId > 0) {
                $proofAttachmentIds[] = $newAttachmentId;
            }
        } else {
            $savedIdFiles = [];
            foreach (['idFront', 'idBack'] as $fileKey) {
                $tmpName = (string)$_FILES[$fileKey]['tmp_name'];
                $name = basename((string)$_FILES[$fileKey]['name']);
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                if (isHeicExt($ext)) throw new Exception('HEIC is not supported on the server. Please upload JPG or PNG.');
                if (!in_array($ext, $allowedIdExt, true)) throw new Exception("Invalid file type for {$fileKey}.");

                $fileMoved = moveUploadedFileWithDocName($tmpName, $uploadDirDocs, $idType . ' ' . $fileKey, $userId, $ext);
                $savedIdFiles[$fileKey] = ['path' => $fileMoved['disk_path'], 'ext' => $ext];
            }

            $mergedFileName = buildAttachmentFileName($idType, $userId, 'pdf');
            $mergedPath = $uploadDirDocs . $mergedFileName;
            $mergeIndex = 0;
            while (file_exists($mergedPath)) {
                $mergeIndex++;
                $mergedFileName = buildAttachmentFileName($idType, $userId, 'pdf', $mergeIndex);
                $mergedPath = $uploadDirDocs . $mergedFileName;
            }

            buildMergedIdPdf($savedIdFiles['idFront']['path'], $savedIdFiles['idFront']['ext'], $savedIdFiles['idBack']['path'], $savedIdFiles['idBack']['ext'], $mergedPath);

            foreach ($savedIdFiles as $saved) {
                $path = (string)($saved['path'] ?? '');
                if ($path !== '' && file_exists($path)) {
                    @unlink($path);
                }
            }

            $docTypeId = getDocumentTypeId($conn, $idType);
            $remarks = 'idMerged';
            $fileType = 'pdf';
            $mergedPathDb = toDbWebPath($mergedPath);
            $ins = $conn->prepare("\n                INSERT INTO unifiedfileattachmenttbl\n                (source_type, source_id, document_type_id, file_name, file_path, file_type, user_id_uploaded_by, status_id_verify, remarks, id_number)\n                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n            ");
            if (!$ins) throw new Exception('Prepare failed (insert merged ID): ' . $conn->error);
            $ins->bind_param('ssissssiss', $sourceType, $residentId, $docTypeId, $mergedFileName, $mergedPathDb, $fileType, $userId, $statusVerifyId, $remarks, $idNumber);
            if (!$ins->execute()) throw new Exception('Merged ID attachment insert failed: ' . $ins->error);
            $newAttachmentId = (int)$ins->insert_id;
            $ins->close();
            $didUploadAny = true;
            $didUploadProof = true;
            if ($newAttachmentId > 0) {
                $proofAttachmentIds[] = $newAttachmentId;
            }
        }
    }

    if ($proofType === 'Document') {
        $hasDocumentProof = false;
        if (isset($_FILES['documentProof']) && is_array($_FILES['documentProof']['name'])) {
            foreach ($_FILES['documentProof']['error'] as $i => $errCode) {
                if ((int)$errCode === UPLOAD_ERR_OK) {
                    $hasDocumentProof = true;
                    break;
                }
            }
        }
        if (!$hasDocumentProof) {
            throw new Exception('Supporting document upload is required.');
        }

        $docAllowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        $docTypeId = getDocumentTypeId($conn, $documentType);

        foreach ($_FILES['documentProof']['name'] as $i => $docName) {
            if ((int)$_FILES['documentProof']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $tmpName = (string)$_FILES['documentProof']['tmp_name'][$i];
            $ext = strtolower(pathinfo((string)$docName, PATHINFO_EXTENSION));
            if (isHeicExt($ext)) throw new Exception('HEIC is not supported on the server. Please upload JPG or PNG.');
            if (!in_array($ext, $docAllowed, true)) throw new Exception('Invalid file type for document attachment.');

            $moved = moveUploadedFileWithDocName($tmpName, $uploadDirDocs, $documentType, $userId, $ext);
            $remarks = 'document';
            $idNumberNull = null;
            $ins = $conn->prepare("\n                INSERT INTO unifiedfileattachmenttbl\n                (source_type, source_id, document_type_id, file_name, file_path, file_type, user_id_uploaded_by, status_id_verify, remarks, id_number)\n                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n            ");
            if (!$ins) throw new Exception('Prepare failed (insert document): ' . $conn->error);
            $ins->bind_param('ssissssiss', $sourceType, $residentId, $docTypeId, $moved['file_name'], $moved['file_path'], $ext, $userId, $statusVerifyId, $remarks, $idNumberNull);
            if (!$ins->execute()) throw new Exception('Document attachment insert failed: ' . $ins->error);
            $newAttachmentId = (int)$ins->insert_id;
            $ins->close();
            $didUploadAny = true;
            $didUploadProof = true;
            if ($newAttachmentId > 0) {
                $proofAttachmentIds[] = $newAttachmentId;
            }
        }
    }

    if ($needsPicture) {
        $picTmp = (string)$_FILES['picture']['tmp_name'];
        $picName = basename((string)$_FILES['picture']['name']);
        $picExt = strtolower(pathinfo($picName, PATHINFO_EXTENSION));
        $allowedPic = ['jpg', 'jpeg', 'png', 'webp'];
        if (isHeicExt($picExt)) throw new Exception('HEIC is not supported on the server. Please upload JPG or PNG.');
        if (!in_array($picExt, $allowedPic, true)) throw new Exception('Invalid file type for 2x2 picture.');

        $movedPic = moveUploadedFileWithDocName($picTmp, $uploadDirPic, '2x2 Picture', $userId, $picExt);
        $picDocTypeId = getDocumentTypeId($conn, '2x2 Picture');
        $picRemarks = '2x2';
        $idNumberNull = null;
        $insPic = $conn->prepare("\n        INSERT INTO unifiedfileattachmenttbl\n        (source_type, source_id, document_type_id, file_name, file_path, file_type, user_id_uploaded_by, status_id_verify, remarks, id_number)\n        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n    ");
        if (!$insPic) throw new Exception('Prepare failed (insert 2x2): ' . $conn->error);
        $insPic->bind_param('ssissssiss', $sourceType, $residentId, $picDocTypeId, $movedPic['file_name'], $movedPic['file_path'], $picExt, $userId, $statusVerifyId, $picRemarks, $idNumberNull);
        if (!$insPic->execute()) throw new Exception('2x2 attachment insert failed: ' . $insPic->error);
        $newAttachmentId = (int)$insPic->insert_id;
        $insPic->close();
        $didUploadAny = true;
        $didUploadProof = true;
        if ($newAttachmentId > 0) {
            $proofAttachmentIds[] = $newAttachmentId;
        }
    }

    // Sector proofs
    $sectorPostToKey = [
        'PWD' => 'PWD',
        'Single Parent' => 'SingleParent',
        'Student' => 'Student',
        'Senior Citizen' => 'SeniorCitizen',
        'Indigenous People' => 'IndigenousPeople',
    ];

    $selectedSectorKeys = [];
    foreach ($selectedSectors as $label) {
        if (isset($sectorPostToKey[$label])) {
            $selectedSectorKeys[] = $sectorPostToKey[$label];
        }
    }

    $isIdProofSelected = ($proofType === 'ID');
    $idTypeNorm = preg_replace('/[^a-z0-9]/', '', strtolower($idType));
    $isNationalIdSelected = in_array($idTypeNorm, ['nationalid', 'philsysid', 'philsysidephilid', 'ephilid'], true);

    foreach ($selectedSectorKeys as $sectorKey) {
        $docTypeValue = cleanString($_POST['sectorDocType'][$sectorKey] ?? '');
        $file = $_FILES['sectorDocFile'] ?? null;
        $sectorErrors = ($file && isset($file['error'][$sectorKey]) && is_array($file['error'][$sectorKey])) ? $file['error'][$sectorKey] : [];

        $hasFile = false;
        foreach ($sectorErrors as $errCode) {
            if ((int)$errCode === UPLOAD_ERR_OK) {
                $hasFile = true;
                break;
            }
        }

        $isRequired = !in_array($sectorKey, ['SingleParent', 'SeniorCitizen'], true);
        if ($sectorKey === 'IndigenousPeople' && $isIdProofSelected && $isNationalIdSelected) {
            $isRequired = false;
        }

        if ($sectorKey === 'SeniorCitizen' && $isIdProofSelected) {
            if ($hasFile || $docTypeValue !== '') {
                throw new Exception('Senior Citizen proof upload is not allowed when using ID as proof of identity.');
            }
            continue;
        }

        if ($sectorKey === 'IndigenousPeople' && $isIdProofSelected && $isNationalIdSelected) {
            if ($hasFile || $docTypeValue !== '') {
                throw new Exception('Indigenous People proof upload is not allowed when using National ID/PhilSys as proof of identity.');
            }
            continue;
        }

        $attemptedSectorUpload = ($docTypeValue !== '' || $hasFile);
        if (!$attemptedSectorUpload) {
            continue;
        }
        if ($docTypeValue === '' || !$hasFile) {
            throw new Exception('Please complete the sector document type and upload for your selected sector.');
        }

        $docTypeId = getDocumentTypeId($conn, $docTypeValue);
        $allowedSector = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        $allowedSectorIdOnly = ['jpg', 'jpeg', 'png', 'webp'];

        $sectorNames = isset($file['name'][$sectorKey]) && is_array($file['name'][$sectorKey]) ? $file['name'][$sectorKey] : [];
        $sectorTmpNames = isset($file['tmp_name'][$sectorKey]) && is_array($file['tmp_name'][$sectorKey]) ? $file['tmp_name'][$sectorKey] : [];
        $sectorErrorCodes = isset($file['error'][$sectorKey]) && is_array($file['error'][$sectorKey]) ? $file['error'][$sectorKey] : [];

        $isIdLikeDocType = (bool)preg_match('/\bid\b/i', (string)$docTypeValue);
        $okIndexes = [];
        foreach ($sectorErrorCodes as $idx => $errCode) {
            if ((int)$errCode === UPLOAD_ERR_OK) {
                $okIndexes[] = (int)$idx;
            }
        }

        if ($isIdLikeDocType) {
            if (count($okIndexes) !== 2) {
                throw new Exception('Please upload both the front and back photos for the selected ID proof.');
            }

            $frontIdx = $okIndexes[0];
            $backIdx = $okIndexes[1];

            $frontOriginal = basename((string)($sectorNames[$frontIdx] ?? 'front'));
            $backOriginal = basename((string)($sectorNames[$backIdx] ?? 'back'));
            $frontTmp = (string)($sectorTmpNames[$frontIdx] ?? '');
            $backTmp = (string)($sectorTmpNames[$backIdx] ?? '');

            $frontExt = strtolower(pathinfo($frontOriginal, PATHINFO_EXTENSION));
            $backExt = strtolower(pathinfo($backOriginal, PATHINFO_EXTENSION));
            if (isHeicExt($frontExt) || isHeicExt($backExt)) throw new Exception('HEIC is not supported on the server. Please upload JPG or PNG.');
            if (!in_array($frontExt, $allowedSectorIdOnly, true) || !in_array($backExt, $allowedSectorIdOnly, true)) throw new Exception('Invalid file type for ID proof. Please upload an image (JPG/PNG/WebP).');

            $frontMoved = moveUploadedFileWithDocName($frontTmp, $uploadDirDocs, $docTypeValue . ' sector front', $userId, $frontExt);
            $backMoved = moveUploadedFileWithDocName($backTmp, $uploadDirDocs, $docTypeValue . ' sector back', $userId, $backExt);

            $mergedFileName = buildAttachmentFileName($docTypeValue, $userId, 'pdf');
            $mergedPath = $uploadDirDocs . $mergedFileName;
            $mergeIndex = 0;
            while (file_exists($mergedPath)) {
                $mergeIndex++;
                $mergedFileName = buildAttachmentFileName($docTypeValue, $userId, 'pdf', $mergeIndex);
                $mergedPath = $uploadDirDocs . $mergedFileName;
            }

            buildMergedIdPdf((string)$frontMoved['disk_path'], $frontExt, (string)$backMoved['disk_path'], $backExt, $mergedPath);

            @unlink((string)$frontMoved['disk_path']);
            @unlink((string)$backMoved['disk_path']);

            $remarks = 'sector:' . $sectorKey;
            $fileType = 'pdf';
            $mergedPathDb = toDbWebPath($mergedPath);
            $idNumberNull = null;
            $ins = $conn->prepare("\n                INSERT INTO unifiedfileattachmenttbl\n                (source_type, source_id, document_type_id, file_name, file_path, file_type, user_id_uploaded_by, status_id_verify, remarks, id_number)\n                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n            ");
            if (!$ins) throw new Exception('Prepare failed (insert merged sector ID): ' . $conn->error);
            $ins->bind_param('ssissssiss', $sourceType, $residentId, $docTypeId, $mergedFileName, $mergedPathDb, $fileType, $userId, $statusVerifyId, $remarks, $idNumberNull);
            if (!$ins->execute()) throw new Exception('Merged sector ID attachment insert failed: ' . $ins->error);
            $newAttachmentId = (int)$ins->insert_id;
            $ins->close();
            $didUploadAny = true;
            $didUploadSector = true;
            if (!in_array($sectorKey, $uploadedSectorKeys, true)) {
                $uploadedSectorKeys[] = $sectorKey;
            }

            if ($newAttachmentId > 0) {
                upsertSectorMembershipStatusFromUpload($conn, $residentId, $sectorKey, $statusVerifyId, $newAttachmentId);
                $sectorAttachmentIds[] = $newAttachmentId;
            }
            continue;
        }

        foreach ($sectorNames as $idx => $originalRawName) {
            $errCode = (int)($sectorErrorCodes[$idx] ?? UPLOAD_ERR_NO_FILE);
            if ($errCode !== UPLOAD_ERR_OK) continue;

            $originalName = basename((string)$originalRawName);
            $tmpName = (string)($sectorTmpNames[$idx] ?? '');
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (isHeicExt($ext)) throw new Exception('HEIC is not supported on the server. Please upload JPG or PNG.');
            if (!in_array($ext, $allowedSector, true)) throw new Exception('Invalid file type for sector proof.');

            $moved = moveUploadedFileWithDocName($tmpName, $uploadDirDocs, $docTypeValue, $userId, $ext);
            $remarks = 'sector:' . $sectorKey;
            $idNumberNull = null;
            $ins = $conn->prepare("\n                INSERT INTO unifiedfileattachmenttbl\n                (source_type, source_id, document_type_id, file_name, file_path, file_type, user_id_uploaded_by, status_id_verify, remarks, id_number)\n                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n            ");
            if (!$ins) throw new Exception('Prepare failed (insert sector document): ' . $conn->error);
            $ins->bind_param('ssissssiss', $sourceType, $residentId, $docTypeId, $moved['file_name'], $moved['file_path'], $ext, $userId, $statusVerifyId, $remarks, $idNumberNull);
            if (!$ins->execute()) throw new Exception('Sector document attachment insert failed: ' . $ins->error);
            $newAttachmentId = (int)$ins->insert_id;
            $ins->close();
            $didUploadAny = true;
            $didUploadSector = true;
            if (!in_array($sectorKey, $uploadedSectorKeys, true)) {
                $uploadedSectorKeys[] = $sectorKey;
            }

            if ($newAttachmentId > 0) {
                upsertSectorMembershipStatusFromUpload($conn, $residentId, $sectorKey, $statusVerifyId, $newAttachmentId);
                $sectorAttachmentIds[] = $newAttachmentId;
            }
        }
    }

    if (!$didUploadAny) {
        throw new Exception('No new documents were uploaded.');
    }

    $sectorJoined = implode(',', $selectedSectors);
    $upSector = $conn->prepare("UPDATE residentinformationtbl SET sector_membership = ?, status_id_resident = ? WHERE resident_id = ?");
    if (!$upSector) throw new Exception('Prepare failed (update resident): ' . $conn->error);
    $upSector->bind_param('sis', $sectorJoined, $pendingResidentStatusId, $residentId);
    if (!$upSector->execute()) throw new Exception('Failed to update resident status.');
    $upSector->close();

    // Resident transaction logging (best-effort)
    try {
        if ($didUploadProof) {
            createResidentTransaction(
                $conn,
                (string)$userId,
                (string)$userId,
                $txSourceType,
                (string)$residentId,
                'PROOF_OF_RESIDENCY',
                'Proof of Residency Submission',
                (int)$statusVerifyId,
                'Resident uploaded/resubmitted proof documents for verification.',
                [
                    'upload_channel' => 'DocumentUpload',
                    'submission_kind' => 'resubmit',
                    'proof_type' => $proofType !== '' ? $proofType : null,
                    'has_2x2' => $needsPicture ? 1 : 0,
                    'attachment_ids' => array_values(array_unique(array_map('intval', $proofAttachmentIds))),
                ]
            );
        }

        if ($didUploadSector) {
            $sectorKeyToLabel = [
                'PWD' => 'PWD',
                'SingleParent' => 'Single Parent',
                'Student' => 'Student',
                'SeniorCitizen' => 'Senior Citizen',
                'IndigenousPeople' => 'Indigenous People',
            ];
            $sectorLabels = [];
            foreach ($uploadedSectorKeys as $k) {
                $sectorLabels[] = $sectorKeyToLabel[$k] ?? $k;
            }

            createResidentTransaction(
                $conn,
                (string)$userId,
                (string)$userId,
                $txSourceType,
                (string)$residentId,
                'SECTOR_MEMBERSHIP',
                'Sector Membership Declaration',
                (int)$statusVerifyId,
                'Resident uploaded/resubmitted sector proof documents for verification.',
                [
                    'upload_channel' => 'DocumentUpload',
                    'submission_kind' => 'resubmit',
                    'sectors' => array_values($sectorLabels),
                    'has_sector_proof' => 1,
                    'attachment_ids' => array_values(array_unique(array_map('intval', $sectorAttachmentIds))),
                ]
            );
        }
    } catch (Throwable $txe) {
        error_log('[residentDocumentUpload][transaction] ' . $txe->getMessage());
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Documents uploaded successfully. Your account is now pending verification.',
        'redirect' => 'resident_dashboard.php',
    ]);
    exit;
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        @($conn->rollback());
    }

    error_log('[residentDocumentUpload] ' . $e->getMessage());
    $msg = 'Unable to submit documents right now. Please try again.';
    $status = 500;

    $known = strtolower((string)$e->getMessage());
    if (
        strpos($known, 'required') !== false ||
        strpos($known, 'invalid') !== false ||
        strpos($known, 'please select') !== false ||
        strpos($known, 'not allowed') !== false ||
        strpos($known, 'no new documents') !== false ||
        strpos($known, 'heic is not supported') !== false ||
        strpos($known, 'failed to upload file') !== false
    ) {
        $msg = $e->getMessage();
        $status = 400;
    }

    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}
